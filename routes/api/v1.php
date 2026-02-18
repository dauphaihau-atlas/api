<?php

use App\Presentation\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('login', [AuthController::class, 'login'])
    ->middleware('throttle.api:login');
Route::post('register', [AuthController::class, 'register']);

// Authenticated
Route::middleware('auth:sanctum')->group(function (): void {
    require __DIR__.'/v1/me.php';
    require __DIR__.'/v1/users.php';
    require __DIR__.'/v1/activity-logs.php';
});
