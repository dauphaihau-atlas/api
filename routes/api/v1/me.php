<?php

use App\Presentation\Http\Controllers\Api\V1\AuthController;
use App\Presentation\Http\Controllers\Api\V1\AvatarController;
use Illuminate\Support\Facades\Route;

Route::get('me', [AuthController::class, 'me']);
Route::patch('me', [AuthController::class, 'updateProfile']);
Route::post('me/avatar', [AvatarController::class, 'updateMe']);
