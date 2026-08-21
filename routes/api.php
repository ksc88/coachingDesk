<?php

use App\Http\Controllers\Api\V1\MobileApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
        Route::get('/me', [MobileApiController::class, 'me']);
        Route::get('/students', [MobileApiController::class, 'students']);
        Route::get('/attendance', [MobileApiController::class, 'attendance']);
        Route::get('/invoices', [MobileApiController::class, 'invoices']);
        Route::get('/receipts', [MobileApiController::class, 'receipts']);
        Route::get('/announcements', [MobileApiController::class, 'announcements']);
        Route::get('/notes', [MobileApiController::class, 'notes']);
    });
});
