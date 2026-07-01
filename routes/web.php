<?php

use App\Http\Controllers\AdminApprovalController;
use App\Http\Controllers\AdminExpertsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DuplicationController;
use App\Http\Controllers\ExpertController;
use App\Http\Controllers\Instructor\ScheduleController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\PersonalInfoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecruitmentController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:expert', 'expert.admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/dashboard', 'admin.dashboard')->name('dashboard');
    Route::get('/experts', [AdminExpertsController::class, 'index'])->name('experts');
    Route::get('/approval', [AdminApprovalController::class, 'index'])->name('approval');
    Route::post('/approval/{expert_id}/approve', [AdminApprovalController::class, 'approve'])->name('approval.approve');
    Route::post('/approval/{expert_id}/reject', [AdminApprovalController::class, 'reject'])->name('approval.reject');
    Route::post('/turnover', [AdminExpertsController::class, 'transferRights'])->name('turnover');
    Route::delete('/experts/{expert}', [AdminExpertsController::class, 'destroy'])->name('experts.destroy');
    Route::view('/panel_assignment', 'admin.panel_assignment')->name('panel_assignment');
    Route::view('/analytics', 'admin.analytics')->name('analytics');
    Route::view('/financial', 'admin.financial')->name('financial');
    Route::view('/archive', 'admin.archive')->name('archive');
    Route::view('/announcements', 'admin.announcements')->name('announcements');
    Route::view('/resources', 'admin.resources')->name('resources');
    Route::view('/rubric_management', 'admin.rubric_management')->name('rubric_management');
    Route::view('/activitylog', 'admin.activitylog')->name('activitylog');
});

Route::middleware(['auth:expert', 'expert.approved'])->prefix('instructor')->name('instructor.')->group(function () {
    Route::view('/dashboard', 'instructor.dashboard')->name('dashboard');
    Route::view('/applications', 'instructor.applications')->name('applications');
    Route::view('/teams', 'instructor.teams')->name('teams');
    Route::view('/consultations', 'instructor.consultations')->name('consultations');
    Route::view('/progress', 'instructor.progress')->name('progress');
    Route::view('/profile', 'instructor.profile')->name('profile');
    Route::view('/schedule', 'instructor.schedule')->name('schedule');
    Route::view('/grading', 'instructor.grading')->name('grading');
    Route::view('/docreview', 'instructor.docreview')->name('docreview');
    Route::view('/pending', 'instructor.pending')->name('pending');
    Route::view('/rejected', 'instructor.rejected')->name('rejected');
});

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login/google', [AuthController::class, 'initiateGoogleLogin'])->name('login.google.initiate');
Route::get('/login/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('login.google.callback');
Route::get('/login/google/mock', [AuthController::class, 'showMockLogin'])->name('login.google.mock');
Route::post('/login/google/mock', [AuthController::class, 'handleMockLogin'])->name('login.google.mock.post');
Route::get('/login/rejected', [AuthController::class, 'rejected'])->name('login.rejected');
Route::get('/request-account', [ExpertController::class, 'showRequestForm'])->name('account.request');
Route::post('/request-account', [ExpertController::class, 'storeRequest'])->name('account.request.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::prefix('expert')->group(function () {
    Route::get('/login', [ExpertController::class, 'login'])->name('expert.login');
    Route::post('/login/google', [ExpertController::class, 'initiateGoogleLogin'])->name('expert.login.google.initiate');
    Route::get('/login/google/callback', [ExpertController::class, 'handleGoogleCallback'])->name('expert.login.google.callback');
    Route::get('/login/google/mock', [ExpertController::class, 'showMockLogin'])->name('expert.login.google.mock');
    Route::post('/login/google/mock', [ExpertController::class, 'handleMockLogin'])->name('expert.login.google.mock.post');
    Route::post('/logout', [ExpertController::class, 'logout'])->name('expert.logout');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/team', [TeamController::class, 'store'])->name('team.store');
    Route::post('/team/confirm', [TeamController::class, 'confirm'])->name('team.confirm');
    Route::post('/team/transfer-leadership', [TeamController::class, 'transferLeadership'])->name('team.transfer-leadership');
    Route::delete('/team', [TeamController::class, 'destroy'])->name('team.destroy');
    Route::post('/recruitment', [RecruitmentController::class, 'store'])->name('recruitment.store');
    Route::put('/recruitment/{recruitment}', [RecruitmentController::class, 'update'])->name('recruitment.update');
    Route::delete('/recruitment/{recruitment}', [RecruitmentController::class, 'destroy'])->name('recruitment.destroy');

    Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
    Route::post('/invitations/{invitation}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');
    Route::post('/invitations/{invitation}/decline', [InvitationController::class, 'decline'])->name('invitations.decline');

    Route::get('/duplication', [DuplicationController::class, 'index'])->name('duplication.index');
    Route::post('/duplication/check', [DuplicationController::class, 'check'])->name('duplication.check');

    Route::prefix('student')->group(function () {
        Route::get('/personal-info', [PersonalInfoController::class, 'show'])->name('student.personal-info');
        Route::get('/personal-info/edit', [PersonalInfoController::class, 'edit'])->name('student.personal-info.edit');
        Route::post('/personal-info', [PersonalInfoController::class, 'store'])->name('student.personal-info.store');

        Route::middleware(['profile.complete'])->group(function () {
            Route::redirect('/student', '/student/dashboard');
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('student.dashboard');
            Route::get('/team', [TeamController::class, 'index'])->name('student.team');
            Route::view('/experts', 'student.experts')->name('student.experts');
            Route::view('/submission', 'student.submission')->name('student.submission');
            Route::view('/review', 'student.review')->name('student.review');
            Route::view('/duplication', 'student.duplication')->name('student.duplication');
            Route::view('/consultation', 'student.consultation')->name('student.consultation');
            Route::view('/progress', 'student.progress')->name('student.progress');
            Route::view('/financial', 'student.financial')->name('student.financial');
            Route::view('/resources', 'student.resources')->name('student.resources');
            Route::view('/archive', 'student.archive')->name('student.archive');
            Route::redirect('/profile', '/student/personal-info/edit')->name('student.profile');
        });
    });

    Route::post('/profile/update', [ProfileController::class, 'update'])->name('profile.update');
});
Route::prefix('instructor/schedule')
    ->middleware('auth:expert')
    ->group(function () {
        Route::get('slots', [ScheduleController::class, 'getSlots']);
        Route::post('slots', [ScheduleController::class, 'addSlot']);
        Route::patch('slots/{id}', [ScheduleController::class, 'updateSlot']);
        Route::delete('slots/{id}', [ScheduleController::class, 'deleteSlot']);
        Route::patch('slots/{id}/toggle', [ScheduleController::class, 'toggleAvailability']);
    });
