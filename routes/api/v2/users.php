<?php

use App\Presentation\Http\Controllers\Api\V2\UserController;
use Illuminate\Support\Facades\Route;

Route::get('users', [UserController::class, 'index'])
    ->middleware('authorize.user:viewAny');
Route::post('users', [UserController::class, 'store'])
    ->middleware('authorize.user:create');
