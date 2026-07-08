<?php

use App\Http\Controllers\Admin\GoogleDriveController;
use App\Http\Controllers\Admin\ResourcesController;
use App\Http\Controllers\AdminApprovalController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminExpertsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DuplicationController;
use App\Http\Controllers\ExpertController;
use App\Http\Controllers\Instructor\AssignmentController as InstructorAssignmentController;
use App\Http\Controllers\Instructor\DocReviewController;
use App\Http\Controllers\Instructor\ScheduleController;
use App\Http\Controllers\Instructor\TeamController as InstructorTeamController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PersonalInfoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecruitmentController;
use App\Http\Controllers\Student\ExpertController as StudentExpertController;
use App\Http\Controllers\StudentResourcesController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:expert', 'expert.admin', 'no.cache', 'track.expert'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
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
    // NOTE: restored to controller-based route (was Route::view in your latest file) —
    // resources.blade.php needs $driveConnected/$folders/$resources passed in by
    // ResourcesController@index. Revert to Route::view(...) if this was intentional.
    Route::get('/resources', [ResourcesController::class, 'index'])->name('resources');
    Route::view('/rubric_management', 'admin.rubric_management')->name('rubric_management');
    Route::view('/activitylog', 'admin.activitylog')->name('activitylog');
});

Route::middleware(['auth:expert', 'expert.approved', 'no.cache', 'track.expert'])->prefix('instructor')->name('instructor.')->group(function () {
    Route::view('/dashboard', 'instructor.dashboard')->name('dashboard');
    Route::view('/applications', 'instructor.applications')->name('applications');
    Route::get('/teams', [InstructorTeamController::class, 'index'])->name('teams');
    Route::view('/consultations', 'instructor.consultations')->name('consultations');
    Route::view('/progress', 'instructor.progress')->name('progress');
    Route::view('/profile', 'instructor.profile')->name('profile');
    Route::view('/schedule', 'instructor.schedule')->name('schedule');
    Route::view('/grading', 'instructor.grading')->name('grading');
    // NOTE: now controller-based to show real capstone_document_review rows
    // for the logged-in expert, instead of the static mock table.
    Route::get('/docreview', [DocReviewController::class, 'index'])->name('docreview');
    Route::get('/assignments', [InstructorAssignmentController::class, 'index'])->name('assignments');
    Route::post('/assignments/{assignment}/approve', [InstructorAssignmentController::class, 'approve'])->name('assignments.approve');
    Route::post('/assignments/{assignment}/reject', [InstructorAssignmentController::class, 'reject'])->name('assignments.reject');
    Route::delete('/assignments/{assignment}', [InstructorAssignmentController::class, 'remove'])->name('assignments.remove');
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

Route::middleware(['auth', 'no.cache', 'track.expert'])->group(function () {
    Route::post('/team', [TeamController::class, 'store'])->name('team.store');
    Route::put('/team/{team}', [TeamController::class, 'update'])->name('team.update');
    Route::post('/team/confirm', [TeamController::class, 'confirm'])->name('team.confirm');
    Route::post('/team/transfer-leadership', [TeamController::class, 'transferLeadership'])->name('team.transfer-leadership');
    Route::delete('/team/members/{student}', [TeamController::class, 'removeMember'])->name('team.members.remove');
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
            Route::redirect('/', '/student/dashboard');
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('student.dashboard');
            Route::get('/team', [TeamController::class, 'index'])->name('student.team');
            Route::get('/experts', [StudentExpertController::class, 'index'])->name('student.experts');
            Route::view('/submission', 'student.submission')->name('student.submission');
            // NEW: handles the actual file upload + multi-recipient Drive delivery
            Route::post('/submission', [SubmissionController::class, 'store'])->name('student.submission.store');
            Route::view('/review', 'student.review')->name('student.review');
            Route::view('/duplication', 'student.duplication')->name('student.duplication');
            Route::view('/consultation', 'student.consultation')->name('student.consultation');
            Route::view('/progress', 'student.progress')->name('student.progress');
            Route::view('/financial', 'student.financial')->name('student.financial');
            // NOTE: restored to controller-based routes (was Route::view in your latest
            // file) — needed for the Google-Drive-backed resource list + download
            // tracking. Revert to Route::view(...) if this was intentional.
            Route::get('/resources', [StudentResourcesController::class, 'index'])->name('student.resources');
            Route::get('/resources/{resource}/download', [StudentResourcesController::class, 'download'])->name('student.resources.download.file');
            Route::post('/resources/{resource}/download', [StudentResourcesController::class, 'trackDownload'])->name('student.resources.download');
            Route::view('/archive', 'student.archive')->name('student.archive');
            Route::redirect('/profile', '/student/personal-info/edit')->name('student.profile');
        });
    });

    Route::post('/profile/update', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/count', [NotificationController::class, 'count'])->name('notifications.count');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
});
Route::prefix('instructor/schedule')
    ->middleware(['auth:expert', 'no.cache', 'track.expert'])
    ->group(function () {
        Route::get('slots', [ScheduleController::class, 'getSlots']);
        Route::post('slots', [ScheduleController::class, 'addSlot']);
        Route::patch('slots/{id}', [ScheduleController::class, 'updateSlot']);
        Route::delete('slots/{id}', [ScheduleController::class, 'deleteSlot']);
        Route::patch('slots/{id}/toggle', [ScheduleController::class, 'toggleAvailability']);
    });

Route::post('/session/keep-alive', function () {
    if (! Auth::check() && ! Auth::guard('expert')->check()) {
        return response()->json(['success' => false], 401);
    }

    if (Auth::guard('expert')->check()) {
        $expert = Auth::guard('expert')->user();
        $expert->last_seen_at = now();
        $expert->save();
    }

    request()->session()->regenerate();
    request()->session()->put('_timeout_at', now()->addMinutes((int) config('session.lifetime'))->timestamp);

    return response()->json(['success' => true]);
});

// ── Google Drive OAuth + resource/submission delivery (from earlier work) ──
Route::middleware(['auth:expert', 'track.expert'])->prefix('admin/gdrive')->name('gdrive.')->group(function () {
    Route::get('/connect', [GoogleDriveController::class, 'connect'])->name('connect');
    Route::get('/callback', [GoogleDriveController::class, 'callback'])->name('callback');
    Route::get('/status', [GoogleDriveController::class, 'status'])->name('status');
    Route::post('/disconnect', [GoogleDriveController::class, 'disconnect'])->name('disconnect');
    Route::get('/folders', [GoogleDriveController::class, 'listFolders'])->name('folders.index');
    Route::post('/folders', [GoogleDriveController::class, 'createFolder'])->name('folders.store');
    Route::post('/upload', [GoogleDriveController::class, 'upload'])->name('upload');
    Route::post('/resources/{resource}/download', [GoogleDriveController::class, 'trackDownload'])->name('resources.download');
    Route::delete('/resources/{resource}', [GoogleDriveController::class, 'destroy'])->name('resources.destroy');
});
