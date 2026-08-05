<?php

use App\Http\Controllers\Api\AdAccountController;
use App\Http\Controllers\Api\AdsAnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanySettingController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/login', [AuthController::class, 'login']);
Route::get('/company-settings', [CompanySettingController::class, 'show']);

// Google OAuth
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

// Protected — any approved, logged-in user
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/client-ad-accounts', [AdAccountController::class, 'index']);

    Route::get('/ads/stats', [AdsAnalyticsController::class, 'getDashboardStats']);

    Route::post('/sync/{clientAdAccount}', [SyncController::class, 'sync']);
    Route::post('/sync-all', [SyncController::class, 'syncAll']);

    // Export
    Route::get('/export/pdf', [ExportController::class, 'exportPdf']);
    Route::get('/export/excel', [ExportController::class, 'exportExcel']);
});

// Protected — admin only (manages API keys/tokens, users, and branding)
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/client-ad-accounts', [AdAccountController::class, 'store']);
    Route::put('/client-ad-accounts/{clientAdAccount}', [AdAccountController::class, 'update']);
    Route::delete('/client-ad-accounts/{clientAdAccount}', [AdAccountController::class, 'destroy']);

    Route::post('/company-settings', [CompanySettingController::class, 'update']);

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::post('/users/{user}/approve', [UserController::class, 'approve']);
});
