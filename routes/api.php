<?php

use App\Http\Controllers\Api\AdminExpertController;
use Illuminate\Support\Facades\Route;

// API routes for admin panel (session based auth)
Route::middleware(['auth:expert', 'expert.admin'])
    ->prefix('admin')
    ->group(function () {
        // Experts management
        Route::get('/experts', [AdminExpertController::class, 'index'])->name('api.admin.experts.index');
        Route::delete('/experts/{expert}', [AdminExpertController::class, 'destroy'])->name('api.admin.experts.destroy');
        Route::post('/turnover', [AdminExpertController::class, 'transferRights'])->name('api.admin.turnover');
    });
