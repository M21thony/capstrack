<?php

use App\Http\Controllers\Api\AdminExpertController;
use App\Http\Controllers\AdminApprovalController;
use Illuminate\Support\Facades\Route;

// API routes for admin panel (session based auth)
Route::middleware(['auth:expert', 'expert.admin'])
    ->prefix('admin')
    ->group(function () {
        // Experts management
        Route::get('/experts', [AdminExpertController::class, 'index'])->name('api.admin.experts.index');
        Route::delete('/experts/{expert}', [AdminExpertController::class, 'destroy'])->name('api.admin.experts.destroy');
        Route::post('/turnover', [AdminExpertController::class, 'transferRights'])->name('api.admin.turnover');

        // Account approval
        Route::get('/approval', [AdminApprovalController::class, 'apiIndex'])->name('api.admin.approval.index');
        Route::post('/approval/{expert}/approve', [AdminApprovalController::class, 'approve'])->name('api.admin.approval.approve');
        Route::post('/approval/{expert}/reject', [AdminApprovalController::class, 'reject'])->name('api.admin.approval.reject');
    });
