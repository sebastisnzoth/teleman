<?php

use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

// Installer routes (only accessible if not installed)
Route::prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'index'])->name('index');
    Route::get('/requirements', [InstallController::class, 'requirements'])->name('requirements');
    Route::post('/requirements/check', [InstallController::class, 'checkRequirements'])->name('requirements.check');

    Route::get('/database', [InstallController::class, 'database'])->name('database');
    Route::post('/database/test', [InstallController::class, 'testDatabase'])->name('database.test');
    Route::post('/database/migrate', [InstallController::class, 'runMigrations'])->name('database.migrate');
    Route::post('/database/step', [InstallController::class, 'databaseStep'])->name('database.step');

    Route::get('/telephony', [InstallController::class, 'telephony'])->name('telephony');
    Route::post('/telephony', [InstallController::class, 'lockTelephonyProvider'])->name('telephony.lock');

    Route::get('/admin', [InstallController::class, 'admin'])->name('admin');
    Route::post('/admin/create', [InstallController::class, 'createAdmin'])->name('admin.create');

    Route::get('/complete', [InstallController::class, 'complete'])->name('complete');
});
