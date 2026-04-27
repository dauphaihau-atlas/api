<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use App\Exceptions\ConflictException;
use App\Exceptions\ValidationException;
use App\Infrastructure\Persistence\Eloquent\Models\IdempotencyKeyModel;
use App\Infrastructure\Tenant\TenantContext;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    /**
     * Allowed characters: A-Z, a-z, 0-9, period, underscore, colon, hyphen.
     * Length enforced separately (16–255).
     */
    private const KEY_PATTERN = '/^[A-Za-z0-9._:\-]{16,255}$/';

    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $routeAction = $this->resolveRouteAction($request);

        if (! $this->isRequiredAction($routeAction)) {
            return $next($request);
        }

        $rawKey = $request->header('Idempotency-Key');

        if ($rawKey === null || $rawKey === '') {
            throw new ValidationException(
                'Idempotency-Key header is required for this endpoint.',
                'MISSING_IDEMPOTENCY_KEY'
            );
        }

        if (! is_string($rawKey) || ! preg_match(self::KEY_PATTERN, $rawKey)) {
            throw new ValidationException(
                'Idempotency-Key must be 16–255 characters and may only contain A–Z, a–z, 0–9, period, underscore, colon, or hyphen.',
                'INVALID_IDEMPOTENCY_KEY'
            );
        }

        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        $userId = (int) $user->getAuthIdentifier();
        $tenantId = $this->tenantContext->getTenantId();

        $scopeHash = $this->computeScopeHash($tenantId, $userId, $routeAction, $rawKey);
        $requestHash = $this->computeRequestHash($request, $routeAction);

        $existing = IdempotencyKeyModel::where('scope_hash', $scopeHash)->first();

        if ($existing !== null) {
            if ($existing->expires_at->isPast()) {
                $existing->delete();
            } elseif ($existing->request_hash !== $requestHash) {
                throw new ConflictException(
                    'Idempotency key already used with a different request payload.',
                    'IDEMPOTENCY_CONFLICT'
                );
            } else {
                return $this->buildReplayResponse($existing);
            }
        }

        $record = $this->insertRecord($scopeHash, $requestHash, $rawKey, $routeAction, $userId, $tenantId);

        if ($record === null) {
            // Concurrent duplicate — re-read what the winning request inserted
            $existing = IdempotencyKeyModel::where('scope_hash', $scopeHash)->first();
            if ($existing === null) {
                return $next($request);
            }
            if ($existing->request_hash !== $requestHash) {
                throw new ConflictException(
                    'Idempotency key already used with a different request payload.',
                    'IDEMPOTENCY_CONFLICT'
                );
            }

            return $this->buildReplayResponse($existing);
        }

        $response = $next($request);

        $record->update([
            'status' => 'completed',
            'response_status' => $response->getStatusCode(),
            'response_body' => json_decode((string) $response->getContent(), true),
        ]);

        return $response;
    }

    private function buildReplayResponse(IdempotencyKeyModel $record): Response
    {
        if ($record->response_body !== null && $record->response_status !== null) {
            return response()
                ->json($record->response_body, $record->response_status)
                ->header('Idempotency-Replayed', 'true');
        }

        // True concurrent request: first is still processing
        return response()
            ->json(['data' => null, 'message' => 'Request is already being processed.'], 202)
            ->header('Idempotency-Replayed', 'true');
    }

    private function insertRecord(
        string $scopeHash,
        string $requestHash,
        string $idempotencyKey,
        string $routeAction,
        int $userId,
        ?int $tenantId
    ): ?IdempotencyKeyModel {
        try {
            return IdempotencyKeyModel::create([
                'scope_hash' => $scopeHash,
                'request_hash' => $requestHash,
                'idempotency_key' => $idempotencyKey,
                'route_action' => $routeAction,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'status' => 'processing',
                'expires_at' => now()->addHours(config('idempotency.ttl_hours', 24)),
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    private function resolveRouteAction(Request $request): string
    {
        $route = $request->route();

        return $route instanceof Route ? $route->getActionName() : '';
    }

    private function isRequiredAction(string $routeAction): bool
    {
        return in_array($routeAction, config('idempotency.required_actions', []), true);
    }

    private function computeScopeHash(?int $tenantId, int $userId, string $routeAction, string $idempotencyKey): string
    {
        return hash('sha256', implode('|', [
            $tenantId ?? 'null',
            $userId,
            $routeAction,
            $idempotencyKey,
        ]));
    }

    private function computeRequestHash(Request $request, string $routeAction): string
    {
        $parts = [$request->method(), $routeAction];

        foreach ($request->allFiles() as $paramName => $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $parts[] = "file:{$paramName}:".hash_file('sha256', $file->getRealPath());
            }
        }

        $input = $request->except(array_keys($request->allFiles()));
        ksort($input);
        $parts[] = 'input:'.json_encode($input);

        return hash('sha256', implode("\n", $parts));
    }
}
