<?php

namespace App\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogApiRequests
{
    /**
     * Log basic request/response metadata for API requests.
     *
     * Avoids logging sensitive headers/payloads by default.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);
        $request->headers->set('X-Request-Id', $requestId);

        $startNs = hrtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->logException($request, $requestId, $startNs, $e);
            throw $e;
        }

        $this->logResponse($request, $response, $requestId, $startNs);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $header = $request->header('X-Request-Id');
        if (is_string($header)) {
            $header = trim($header);
        }

        if (is_string($header) && $header !== '' && strlen($header) <= 128) {
            return $header;
        }

        return (string) Str::uuid();
    }

    private function logResponse(Request $request, Response $response, string $requestId, int $startNs): void
    {
        $durationMs = $this->durationMs($startNs);
        $status = $response->getStatusCode();

        $message = 'api.request';
        $context = $this->baseContext($request, $requestId, $durationMs);
        $context['status'] = $status;

        if ($status >= 500) {
            Log::error($message, $context);

            return;
        }

        if ($status >= 400) {
            Log::warning($message, $context);

            return;
        }

        Log::info($message, $context);
    }

    private function logException(Request $request, string $requestId, int $startNs, Throwable $e): void
    {
        $durationMs = $this->durationMs($startNs);

        $context = $this->baseContext($request, $requestId, $durationMs);
        $context['exception_class'] = $e::class;
        $context['exception_message'] = $e->getMessage();

        Log::error('api.exception', $context);
    }

    private function baseContext(Request $request, string $requestId, float $durationMs): array
    {
        $route = $request->route();

        return [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'path' => '/'.ltrim((string) $request->path(), '/'),
            'route_name' => is_object($route) && method_exists($route, 'getName') ? $route->getName() : null,
            'duration_ms' => $durationMs,
            'ip' => $request->ip(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'user_agent' => $request->userAgent(),
            'headers' => $this->safeHeaders($request),
        ];
    }

    private function safeHeaders(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $key => $values) {
            if (! is_string($key)) {
                continue;
            }

            $normalizedKey = strtolower($key);
            if ($this->isSensitiveHeader($normalizedKey)) {
                continue;
            }

            $headers[$key] = array_values(array_filter(array_map(static function ($v) {
                return is_scalar($v) ? (string) $v : null;
            }, is_array($values) ? $values : [$values])));
        }

        return $headers;
    }

    private function isSensitiveHeader(string $normalizedKey): bool
    {
        return in_array($normalizedKey, [
            'authorization',
            'cookie',
            'set-cookie',
            'x-api-key',
            'x-auth-token',
            'x-csrf-token',
        ], true);
    }

    private function durationMs(int $startNs): float
    {
        $endNs = hrtime(true);
        $deltaNs = max(0, $endNs - $startNs);

        return round($deltaNs / 1_000_000, 2);
    }
}
