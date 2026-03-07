<?php

use App\Presentation\Http\Controllers\Api\V1\TenantController;
use Illuminate\Support\Facades\Route;

Route::middleware('authorize.tenant:viewAny')->group(function (): void {
    Route::get('tenants', [TenantController::class, 'index']);
});

Route::middleware('authorize.tenant:view')->group(function (): void {
    Route::get('tenants/{id}', [TenantController::class, 'show'])->where('id', '[0-9]+');
});

Route::middleware('authorize.tenant:create')->group(function (): void {
    Route::post('tenants', [TenantController::class, 'store']);
});

Route::middleware('authorize.tenant:update')->group(function (): void {
    Route::put('tenants/{id}', [TenantController::class, 'update'])->where('id', '[0-9]+');
});

Route::middleware('authorize.tenant:delete')->group(function (): void {
    Route::delete('tenants/{id}', [TenantController::class, 'destroy'])->where('id', '[0-9]+');
});
