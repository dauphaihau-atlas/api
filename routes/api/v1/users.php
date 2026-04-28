<?php

use App\Presentation\Http\Controllers\Api\V1\AvatarController;
use App\Presentation\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

// User management routes (authorized via UserPolicy)
Route::get('users', [UserController::class, 'index'])
    ->middleware('authorize.user:viewAny');
Route::get('users/stats', [UserController::class, 'stats'])
    ->middleware('authorize.user:viewAny');
Route::post('users', [UserController::class, 'store'])
    ->middleware('authorize.user:create');

Route::get('users/import/template', [UserController::class, 'downloadImportTemplate'])
    ->middleware('authorize.user:import');

Route::middleware(['authorize.user:import', 'throttle.api:heavy'])->group(function (): void {
    Route::post('users/import', [UserController::class, 'import'])->middleware('idempotency');
    Route::get('users/import/{id}/status', [UserController::class, 'importStatus']);
});

Route::middleware(['authorize.user:export', 'throttle.api:heavy'])->group(function (): void {
    Route::get('users/export', [UserController::class, 'export'])->middleware('idempotency');
    Route::get('users/export/last', [UserController::class, 'lastExport']);
});

Route::delete('users/import/{id}', [UserController::class, 'cancelImport'])
    ->middleware('authorize.user:cancelImport')
    ->where('id', '[0-9]+');

Route::patch('users/{id}', [UserController::class, 'update'])
    ->middleware('authorize.user:adminUpdate')
    ->where('id', '[0-9]+');
Route::delete('users/{id}', [UserController::class, 'destroy'])
    ->middleware('authorize.user:delete')
    ->where('id', '[0-9]+');
Route::post('users/{id}/restore', [UserController::class, 'restore'])
    ->middleware('authorize.user:restore')
    ->where('id', '[0-9]+');
Route::delete('users/{id}/force', [UserController::class, 'forceDestroy'])
    ->middleware('authorize.user:forceDelete')
    ->where('id', '[0-9]+');

Route::post('users/{user}/avatar', [AvatarController::class, 'updateUser'])
    ->middleware('can:update,user');
