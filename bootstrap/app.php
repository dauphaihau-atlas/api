<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'throttle.api' => \App\Presentation\Http\Middleware\RateLimitMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request === null || !$request->expectsJson()) {
                return null;
            }

            if ($e instanceof \App\Exceptions\ApiException) {
                $payload = ['message' => $e->getMessage()];
                if ($e->getErrorCode() !== null) {
                    $payload['error_code'] = $e->getErrorCode();
                }
                return response()->json($payload, $e->getHttpStatusCode());
            }

            // Domain exception: InvalidEmailException maps to 422
            if ($e instanceof \App\Core\Domain\Exceptions\InvalidEmailException) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return null;
        });
    })->create();
