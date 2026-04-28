<?php

use App\Presentation\Http\Controllers\Api\Internal\UserImportCallbackController;
use Illuminate\Support\Facades\Route;

Route::get('user-import-files', [UserImportCallbackController::class, 'downloadFile'])
    ->middleware('internal.token');

Route::prefix('user-imports')->middleware('internal.token')->group(function (): void {
    Route::post('{id}/started', [UserImportCallbackController::class, 'started'])->where('id', '[0-9]+');
    Route::post('{id}/chunks', [UserImportCallbackController::class, 'chunk'])->where('id', '[0-9]+');
    Route::post('{id}/complete', [UserImportCallbackController::class, 'complete'])->where('id', '[0-9]+');
    Route::post('{id}/fail', [UserImportCallbackController::class, 'fail'])->where('id', '[0-9]+');
});
