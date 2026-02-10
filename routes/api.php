<?php

use App\Presentation\Http\Controllers\Api\V1\AuthController;
use App\Presentation\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Health check: verifies connectivity to the database (e.g. PostgreSQL container)
Route::get('health', function (): \Illuminate\Http\JsonResponse {
    $checks = [
        'database' => null,
    ];

    try {
        DB::connection()->getPdo();
        DB::select('SELECT 1');
        $checks['database'] = 'ok';
    } catch (\Throwable $e) {
        $checks['database'] = 'error';
        return response()->json([
            'status' => 'unhealthy',
            'message' => 'Database connection failed',
            'checks' => $checks,
            'error' => config('app.debug') ? $e->getMessage() : null,
        ], 503);
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
