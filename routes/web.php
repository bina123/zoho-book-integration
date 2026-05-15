<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionsController;
use App\Http\Controllers\ZohoAuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/report');

Route::name('report.')->prefix('report')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::post('/refresh', [ReportController::class, 'refresh'])->name('refresh');
    Route::post('/budgets', [BudgetController::class, 'update'])->name('budgets.update');
    Route::get('/transactions', TransactionsController::class)->name('transactions');
    Route::get('/attachments/{type}/{id}', AttachmentController::class)
        ->where('type', 'invoice|bill')
        ->where('id', '[A-Za-z0-9_-]+')
        ->name('attachments');
});

Route::name('zoho.')->prefix('auth/zoho')->group(function () {
    Route::get('/', [ZohoAuthController::class, 'redirect'])->name('redirect');
    Route::get('/callback', [ZohoAuthController::class, 'callback'])->name('callback');
    Route::get('/organizations', [ZohoAuthController::class, 'showOrganizations'])->name('organizations.show');
    Route::post('/organizations', [ZohoAuthController::class, 'chooseOrganization'])->name('organizations.choose');
    Route::post('/logout', [ZohoAuthController::class, 'logout'])->name('logout');
    Route::get('/status', [ZohoAuthController::class, 'status'])->name('status');
});
