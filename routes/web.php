<?php

use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NetworkController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/network/scan', [NetworkController::class, 'scan'])->name('network.scan');

    Route::get('/deployments/new', [DeploymentController::class, 'create'])->name('deployments.create');
    Route::get('/deployments/{deployment}', [DeploymentController::class, 'show'])
        ->name('deployments.show');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('/installers/browse', [DeploymentController::class, 'browse'])
        ->name('installers.browse');
    Route::post('/deployments', [DeploymentController::class, 'store'])
        ->name('deployments.store');

        
});

require __DIR__.'/auth.php';
