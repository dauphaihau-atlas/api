<?php

use App\Exceptions\ServiceUnavailableException;
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

Route::prefix('v1')->middleware(['throttle.api', 'deprecated.api'])->group(function (): void {
    require base_path('routes/api/v1.php');
});

Route::prefix('v2')->middleware('throttle.api')->group(function (): void {
    require base_path('routes/api/v2.php');
});

Route::prefix('internal')->group(function (): void {
    require base_path('routes/api/internal.php');
});
