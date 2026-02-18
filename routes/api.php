<?php

use App\Exceptions\ServiceUnavailableException;
use App\Presentation\Http\Controllers\Api\V1\ActivityLogController;
use App\Presentation\Http\Controllers\Api\V1\AuthController;
use App\Presentation\Http\Controllers\Api\V1\AvatarController;
use App\Presentation\Http\Controllers\Api\V1\UserController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Health check
 *
 * Verifies connectivity to the database, Redis, and MinIO (when configured).
 *
 * @group Health
 *
 * @unauthenticated
 *
 * @response 200 {"status":"healthy","checks":{"database":"ok","redis":"ok","minio":null}}
 * @response 503 {"message":"Database connection failed","code":"DB_CONNECTION_FAILED","context":{"status":"unhealthy","checks":{"database":"error","redis":null,"minio":null}}}
 */
Route::get('health', function (): JsonResponse {
    $checks = [
        'database' => null,
        'redis' => null,
        'minio' => null,
    ];

    try {
        DB::connection()->getPdo();
        DB::select('SELECT 1');
        $checks['database'] = 'ok';
    } catch (Throwable $e) {
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
    } catch (Throwable $e) {
        $checks['redis'] = 'error';
        $context = ['status' => 'unhealthy', 'checks' => $checks];
        if (config('app.debug')) {
            $context['error'] = $e->getMessage();
        }
        throw new ServiceUnavailableException('Redis connection failed', 'REDIS_CONNECTION_FAILED', $context, 0, $e);
    }

    // MinIO / S3-compatible storage: only check when a minio endpoint is configured
    if (! empty(config('filesystems.disks.minio.endpoint'))) {
        try {
            Storage::disk('minio')->files('/');
            $checks['minio'] = 'ok';
        } catch (Throwable $e) {
            $checks['minio'] = 'error';
            $context = ['status' => 'unhealthy', 'checks' => $checks];
            if (config('app.debug')) {
                $context['error'] = $e->getMessage();
            }
            throw new ServiceUnavailableException('MinIO connection failed', 'MINIO_CONNECTION_FAILED', $context, 0, $e);
        }
    }

    return response()->json([
        'status' => 'healthy',
        'checks' => $checks,
    ], Response::HTTP_OK);
});

Route::prefix('v1')->middleware('throttle.api')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle.api:login');
    Route::post('register', [AuthController::class, 'register']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('me/avatar', [AvatarController::class, 'updateMe']);

        // User management routes (authorized via UserPolicy)
        Route::get('users', [UserController::class, 'index'])
            ->middleware('authorize.user:viewAny');
        Route::post('users', [UserController::class, 'store'])
            ->middleware('authorize.user:create');

        Route::middleware(['authorize.user:import', 'throttle.api:heavy'])->group(function (): void {
            Route::post('users/import', [UserController::class, 'import']);
            Route::get('users/import/{id}/status', [UserController::class, 'importStatus']);
        });

        Route::middleware(['authorize.user:export', 'throttle.api:heavy'])->group(function (): void {
            Route::get('users/export', [UserController::class, 'export']);
            Route::get('users/export/download', [UserController::class, 'downloadExport'])
                ->middleware('signed')
                ->name('export.download');
        });

        Route::post('users/{user}/avatar', [AvatarController::class, 'updateUser'])
            ->middleware('can:update,user');

        // Activity logs (admin only)
        Route::get('activity-logs', [ActivityLogController::class, 'index'])
            ->middleware('authorize.activity_log:viewAny');
        Route::get('activity-logs/{id}', [ActivityLogController::class, 'show'])
            ->middleware('authorize.activity_log:view,id');
        Route::get('users/{user}/activity-logs', [ActivityLogController::class, 'indexForUser'])
            ->middleware('authorize.activity_log:viewAny');
    });
});
