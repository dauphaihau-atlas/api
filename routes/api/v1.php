<?php

use App\Presentation\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

// Public — login with optional tenant resolution (for super admin login without header)
Route::post('login', [AuthController::class, 'login'])
    ->middleware(['throttle.api:login', 'resolve.tenant.optional']);
Route::post('register', [AuthController::class, 'register']);

// Authenticated — no tenant header required
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout']);
    require __DIR__.'/v1/tenants.php';
});

// Authenticated + tenant-scoped (X-Tenant-ID header required)
Route::middleware(['auth:sanctum', 'resolve.tenant'])->group(function (): void {
    require __DIR__.'/v1/me.php';
    require __DIR__.'/v1/users.php';
    require __DIR__.'/v1/activity-logs.php';
});
