<?php

use App\Exceptions\ServiceUnavailableException;
use App\Presentation\Http\Controllers\Api\V1\AuthController;
use App\Presentation\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

// Health check: verifies connectivity to the database (e.g. PostgreSQL container) and Redis
Route::get('health', function (): \Illuminate\Http\JsonResponse {
    $checks = [
        'database' => null,
        'redis' => null,
    ];

    try {
        DB::connection()->getPdo();
        DB::select('SELECT 1');
        $checks['database'] = 'ok';
    } catch (\Throwable $e) {
        $checks['database'] = 'error';
        $context = ['status' => 'unhealthy', 'checks' => $checks];
        if (config('app.debug')) {
            $context['error'] = $e->getMessage();
        }
        throw new ServiceUnavailableException('Database connection failed', 'DB_CONNECTION_FAILED', $context, 0, $e);
    }

    try {
        Redis::ping();
        $checks['redis'] = 'ok';
    } catch (\Throwable $e) {
        $checks['redis'] = 'error';
        $context = ['status' => 'unhealthy', 'checks' => $checks];
        if (config('app.debug')) {
            $context['error'] = $e->getMessage();
        }
        throw new ServiceUnavailableException('Redis connection failed', 'REDIS_CONNECTION_FAILED', $context, 0, $e);
    }

    return response()->json([
        'status' => 'healthy',
        'checks' => $checks,
    ], 200);
});

Route::prefix('v1')->middleware('throttle.api')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::apiResource('users', UserController::class)->only(['index', 'store']);
    });
});
