<?php

use App\Presentation\Http\Controllers\Api\V1\AuthController;
use App\Presentation\Http\Controllers\Api\V1\InvitationController;
use App\Presentation\Http\Controllers\Api\V1\RoleController;
use App\Presentation\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

// Public — login with optional tenant resolution (for super admin login without header)
Route::post('login', [AuthController::class, 'login'])
    ->middleware(['throttle.api:login', 'resolve.tenant.optional']);
Route::post('register', [AuthController::class, 'register']);
Route::get('invitations/accept', [InvitationController::class, 'show']);
Route::post('invitations/accept', [InvitationController::class, 'accept']);

// Public — signed download URL (no auth required, protected by signature)
Route::get('users/export/download', [UserController::class, 'downloadExport'])
    ->middleware('signed')
    ->name('export.download');

// Authenticated — no tenant header required
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout']);
    require __DIR__.'/v1/tenants.php';
});

// Authenticated + tenant-scoped (X-Tenant-ID header required)
Route::middleware(['auth:sanctum', 'resolve.tenant'])->group(function (): void {
    require __DIR__.'/v1/me.php';
    Route::get('roles/assignable', [RoleController::class, 'assignable'])
        ->middleware('authorize.user:create');
    require __DIR__.'/v1/users.php';
    require __DIR__.'/v1/activity-logs.php';
});
