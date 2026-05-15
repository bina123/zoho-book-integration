<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\TransactionsController;
use App\Http\Controllers\ZohoAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('zoho')->group(function () {
    Route::get('/status', [ZohoAuthController::class, 'status']);
});

Route::prefix('report')->group(function () {
    Route::get('/transactions', TransactionsController::class);
    Route::get('/attachments/{type}/{id}', AttachmentController::class)
        ->where('type', 'invoice|bill')
        ->where('id', '[A-Za-z0-9_-]+');
});
