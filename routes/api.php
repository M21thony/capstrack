<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AdminExpertController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\RecruitmentController;
use App\Http\Controllers\Api\StudentExpertController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Support\Facades\Route;

// Admin API routes (session based auth for experts)
Route::middleware(['web', 'expert.auth', 'expert.admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/experts', [AdminExpertController::class, 'index'])->name('api.admin.experts.index');
        Route::delete('/experts/{expert}', [AdminExpertController::class, 'destroy'])->name('api.admin.experts.destroy');
        Route::post('/turnover', [AdminExpertController::class, 'transferRights'])->name('api.admin.turnover');
    });

// Student API routes (session based auth for students)
Route::middleware(['web', 'auth'])->group(function () {
    // Teams
    Route::get('/teams', [TeamController::class, 'index']);
    Route::post('/teams', [TeamController::class, 'store']);
    Route::get('/teams/{team}', [TeamController::class, 'show']);
    Route::put('/teams/{team}', [TeamController::class, 'update']);
    Route::delete('/teams/{team}', [TeamController::class, 'destroy']);
    Route::post('/teams/{team}/confirm', [TeamController::class, 'confirm']);
    Route::post('/teams/{team}/transfer-leadership', [TeamController::class, 'transferLeadership']);
    Route::delete('/teams/{team}/members/{student}', [TeamController::class, 'removeMember']);

    // Recruitment
    Route::get('/recruitments', [RecruitmentController::class, 'index']);
    Route::post('/recruitments', [RecruitmentController::class, 'store']);
    Route::put('/recruitments/{recruitment}', [RecruitmentController::class, 'update']);
    Route::delete('/recruitments/{recruitment}', [RecruitmentController::class, 'destroy']);

    // Invitations
    Route::get('/invitations', [InvitationController::class, 'index']);
    Route::post('/invitations', [InvitationController::class, 'store']);
    Route::post('/invitations/{invitation}/accept', [InvitationController::class, 'accept']);
    Route::post('/invitations/{invitation}/decline', [InvitationController::class, 'decline']);

    // Expert assignments
    Route::get('/experts', [StudentExpertController::class, 'index'])->name('api.student.experts.index');
    Route::get('/experts/{expert}', [StudentExpertController::class, 'show'])->name('api.student.experts.show');
    Route::post('/assignments', [StudentExpertController::class, 'store'])->name('api.student.assignments.store');
    Route::delete('/assignments/{assignment}', [StudentExpertController::class, 'destroy'])->name('api.student.assignments.destroy');

    // Activities
    Route::get('/activities', [ActivityLogController::class, 'index']);
});
