<?php

use App\Http\Controllers\Admin\KycReviewController;
use App\Http\Controllers\KycController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| KYC Routes
|--------------------------------------------------------------------------
|
| Routes for Know Your Customer (KYC) verification system
| - User routes: Submit and verify KYC information
| - Admin routes: Review and approve/reject KYC submissions
|
*/

// User KYC Routes (Settings)
Route::middleware(['auth', 'verified'])->prefix('settings/kyc')->name('kyc.')->group(function () {
    
    // KYC Status Page
    Route::get('/', [KycController::class, 'index'])->name('index');
    
    // Tier 1: Basic (Phone Verification)
    Route::get('/basic', [KycController::class, 'showBasic'])->name('basic');
    Route::post('/basic', [KycController::class, 'storeBasic'])->name('basic.store');
    
    // Phone Verification
    Route::get('/verify-phone', [KycController::class, 'showVerifyPhone'])->name('verify-phone');
    Route::post('/verify-phone', [KycController::class, 'verifyPhone'])->name('verify-phone.submit');
    Route::post('/resend-code', [KycController::class, 'resendCode'])->name('resend-code');
    
    // Tier 2: Business (Full KYC)
    Route::get('/business', [KycController::class, 'showBusiness'])->name('business');
    Route::post('/business', [KycController::class, 'storeBusiness'])->name('business.store');
    
    // Document Download (own documents)
    Route::get('/document/{documentType}', [KycController::class, 'downloadDocument'])->name('document');
});

// Admin KYC Review Routes
Route::middleware(['auth', 'verified'])->prefix('admin/kyc')->name('admin.kyc.')->group(function () {
    
    // Settings Routes (must be before parameterized routes)
    Route::get('/settings', [\App\Http\Controllers\Admin\KycSettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [\App\Http\Controllers\Admin\KycSettingsController::class, 'update'])->name('settings.update');
    
    // Pending Queue
    Route::get('/', [KycReviewController::class, 'index'])->name('index');
    
    // Approve user directly (create KYC if not exists and approve)
    Route::post('/approve-user', [KycReviewController::class, 'approveUser'])->name('approve-user');
    
    // Review Detail
    Route::get('/{kyc}', [KycReviewController::class, 'show'])->name('show');
    
    // Approve/Reject Actions
    Route::post('/{kyc}/approve', [KycReviewController::class, 'approve'])->name('approve');
    
    Route::post('/{kyc}/reject', [KycReviewController::class, 'reject'])->name('reject');
    
    // Document Download (admin access)
    Route::get('/{kyc}/document/{type}', [KycReviewController::class, 'downloadDocument'])->name('document');
    
    // Update individual document status
    Route::patch('/{kyc}/document/{type}/status', [KycReviewController::class, 'updateDocumentStatus'])->name('document.status');
});
