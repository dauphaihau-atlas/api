<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Presentation\Http\Middleware\LogApiRequests;
use App\Presentation\Http\Middleware\RateLimitMiddleware;
use App\Exceptions\ApiException;
use App\Core\Domain\Exceptions\InvalidEmailException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('api', LogApiRequests::class);

        $middleware->alias([
            'log.api' => LogApiRequests::class,
            'throttle.api' => RateLimitMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request === null || !$request->expectsJson()) {
                return null;
            }

            if ($e instanceof ApiException) {
                $payload = ['message' => $e->getMessage()];
                if ($e->getErrorCode() !== null) {
                    $payload['error_code'] = $e->getErrorCode();
                }
                $context = $e->getContext();
                if ($context !== null && $context !== []) {
                    $payload = array_merge($payload, $context);
                }
                return response()->json($payload, $e->getHttpStatusCode());
            }

            // Domain exception: InvalidEmailException maps to 422
            if ($e instanceof InvalidEmailException) {
                throw new ValidationException($e->getMessage());
            }

            // Authorization: gate/policy denied (403)
            if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException || $e instanceof ForbiddenException) {
                throw new ForbiddenException($e->getMessage());
            }
            return null;
        });
    })->create();
