<?php

use App\Presentation\Http\Controllers\Api\V1\ActivityLogController;
use Illuminate\Support\Facades\Route;

// Activity logs (admin only)
Route::get('activity-logs', [ActivityLogController::class, 'index'])
    ->middleware('authorize.activity_log:viewAny');
Route::get('activity-logs/{id}', [ActivityLogController::class, 'show'])
    ->middleware('authorize.activity_log:view,id');
Route::get('users/{user}/activity-logs', [ActivityLogController::class, 'indexForUser'])
    ->middleware('authorize.activity_log:viewAny');
