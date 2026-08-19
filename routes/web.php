<?php

use App\Http\Controllers\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Admin\AnalyticsController as AdminAnalyticsController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\ArchiveController as AdminArchiveController;
use App\Http\Controllers\Admin\GoogleDriveController;
use App\Http\Controllers\Admin\GradingRubricController;
use App\Http\Controllers\Admin\ResourcesController;
use App\Http\Controllers\AdminApprovalController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminExpertsController;
use App\Http\Controllers\AdminFinancialController;
use App\Http\Controllers\AdminPanelAssignmentController;
use App\Http\Controllers\AdminProfileController;
use App\Http\Controllers\AdminTeamVerificationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DuplicationController;
use App\Http\Controllers\ExpertController;
use App\Http\Controllers\Instructor\AssignmentController as InstructorAssignmentController;
use App\Http\Controllers\Instructor\DashboardController as InstructorDashboardController;
use App\Http\Controllers\Instructor\DocReviewController;
use App\Http\Controllers\Instructor\GradingController;
use App\Http\Controllers\Instructor\ProfileController as InstructorProfileController;
use App\Http\Controllers\Instructor\ProgressController as InstructorProgressController;
use App\Http\Controllers\Instructor\ScheduleController;
use App\Http\Controllers\Instructor\TeamController as InstructorTeamController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PdfAnnotationController;
use App\Http\Controllers\PersonalInfoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecruitmentController;
use App\Http\Controllers\Student\ArchiveController as StudentArchiveController;
use App\Http\Controllers\Student\ExpertController as StudentExpertController;
use App\Http\Controllers\Instructor\ConsultationLogController;
use App\Http\Controllers\Student\ProgressController as StudentProgressController;
use App\Http\Controllers\Student\ResourceController as StudentResourceController;
use App\Http\Controllers\StudentConsultationController;
use App\Http\Controllers\StudentFinancialController;
use App\Http\Controllers\StudentReviewController;
use App\Http\Controllers\StudentSectionController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\TeamController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware(['expert.auth', 'expert.admin', 'no.cache', 'track.expert'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/experts', [AdminExpertsController::class, 'index'])->name('experts');
    Route::get('/approval', [AdminApprovalController::class, 'index'])->name('approval');
    Route::post('/approval/{expert_id}/approve', [AdminApprovalController::class, 'approve'])->name('approval.approve');
    Route::post('/approval/{expert_id}/reject', [AdminApprovalController::class, 'reject'])->name('approval.reject');
    Route::post('/turnover', [AdminExpertsController::class, 'transferRights'])->name('turnover');
    Route::delete('/experts/{expert}', [AdminExpertsController::class, 'destroy'])->name('experts.destroy');
    Route::get('/panel_assignment', [AdminPanelAssignmentController::class, 'index'])->name('panel_assignment');
    Route::get('/verify-teams', [AdminTeamVerificationController::class, 'index'])->name('verify_teams');
    Route::post('/verify-teams/{team}/verify', [AdminTeamVerificationController::class, 'verify'])->name('verify_teams.verify');
    Route::get('/profile', [AdminProfileController::class, 'index'])->name('profile');
    Route::post('/profile', [AdminProfileController::class, 'update'])->name('profile.update');
    Route::get('/sections', [StudentSectionController::class, 'index'])->name('sections');
    Route::get('/analytics', [AdminAnalyticsController::class, 'index'])->name('analytics');
    Route::get('/financial', [AdminFinancialController::class, 'index'])->name('financial');
    Route::post('/financial/expense-post', [AdminFinancialController::class, 'storeExpensePost'])->name('financial.expense-post.store');
    Route::put('/financial/expense-post/{expensePost}', [AdminFinancialController::class, 'updateExpensePost'])->name('financial.expense-post.update');
    Route::delete('/financial/expense-post/{expensePost}', [AdminFinancialController::class, 'destroyExpensePost'])->name('financial.expense-post.destroy');
    Route::post('/financial/receipt/{financialRecord}/approve', [AdminFinancialController::class, 'approveReceipt'])->name('financial.receipt.approve');
    Route::post('/financial/receipt/{financialRecord}/reject', [AdminFinancialController::class, 'rejectReceipt'])->name('financial.receipt.reject');
    Route::get('/archive', [AdminArchiveController::class, 'index'])->name('archive');
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements');
    Route::get('/resources', [ResourcesController::class, 'index'])->name('resources');
    Route::get('/rubric_management', [GradingRubricController::class, 'index'])->name('rubric_management');
    Route::get('/activitylog', [AdminActivityLogController::class, 'index'])->name('activitylog');
    Route::post('/notifications/seen', function (Request $request) {
        $ids = $request->input('ids', []);
        if (! empty($ids)) {
            $seen = session('admin_notification_seen_ids', []);
            $seen = array_unique(array_merge($seen, $ids));
            session(['admin_notification_seen_ids' => $seen]);
        }

        return response()->json(['success' => true]);
    })->name('notifications.seen');
});

Route::middleware(['expert.auth', 'expert.approved', 'no.cache', 'track.expert'])->prefix('instructor')->name('instructor.')->group(function () {
    Route::get('/dashboard', [InstructorDashboardController::class, 'index'])->name('dashboard');
    Route::get('/teams', [InstructorTeamController::class, 'index'])->name('teams');
    Route::view('/consultations', 'instructor.consultations')->name('consultations');
    Route::get('/progress', [InstructorProgressController::class, 'index'])->name('progress');
    Route::get('/profile', [InstructorProfileController::class, 'index'])->name('profile');
    Route::view('/schedule', 'instructor.schedule')->name('schedule');
    Route::get('/grading', [GradingController::class, 'index'])->name('grading');
    Route::get('/grading/{defense}', [GradingController::class, 'show'])->name('grading.show');
    Route::post('/grading/{defense}/save', [GradingController::class, 'save'])->name('grading.save');
    Route::post('/grading/{defense}/submit', [GradingController::class, 'submit'])->name('grading.submit');
    Route::get('/docreview', [DocReviewController::class, 'index'])->name('docreview');
    Route::get('/docreview/{review}/annotate', [DocReviewController::class, 'annotate'])->name('docreview.annotate');
    Route::get('/assignments', [InstructorAssignmentController::class, 'index'])->name('assignments');
    Route::post('/assignments/{assignment}/approve', [InstructorAssignmentController::class, 'approve'])->name('assignments.approve');
    Route::post('/assignments/{assignment}/reject', [InstructorAssignmentController::class, 'reject'])->name('assignments.reject');
    Route::delete('/assignments/{assignment}', [InstructorAssignmentController::class, 'remove'])->name('assignments.remove');
    Route::view('/pending', 'instructor.pending')->name('pending');
    Route::view('/rejected', 'instructor.rejected')->name('rejected');
    Route::get('/docreview/{reviewId}/clf', [ConsultationLogController::class, 'generate'])->name('docreview.clf');
});

// Annotator routes for PDF annotation and remarks
Route::middleware(['auth:expert', 'expert.approved', 'no.cache'])->prefix('reviews')->name('reviews.')->group(function () {
    Route::get('/{review}/pdf', [PdfAnnotationController::class, 'file'])->name('pdf');
    Route::get('/{review}/remarks', [PdfAnnotationController::class, 'index'])->name('remarks.index');
    Route::post('/{review}/remarks', [PdfAnnotationController::class, 'store'])->name('remarks.store');
    Route::get('/{review}/general-remarks', [PdfAnnotationController::class, 'generalIndex'])->name('general-remarks.index');
    Route::post('/{review}/general-remarks', [PdfAnnotationController::class, 'generalStore'])->name('general-remarks.store');
    Route::delete('/{review}/general-remarks/{remark}', [PdfAnnotationController::class, 'generalDestroy'])->name('general-remarks.destroy');
    Route::get('/{review}/status', [PdfAnnotationController::class, 'showStatus'])->name('status.show');
    Route::post('/{review}/status', [PdfAnnotationController::class, 'updateStatus'])->name('status.update');
    Route::post('/{review}/sync-drive', [PdfAnnotationController::class, 'syncDrive'])->name('sync-drive');
});

Route::get('/', function () {
    return redirect()->route('login', request()->query());
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
    Route::get('/team/check-name', [TeamController::class, 'checkName'])->name('team.check-name');
    Route::post('/team/transfer-leadership', [TeamController::class, 'transferLeadership'])->name('team.transfer-leadership');
    Route::post('/team/leadership/{notification}/accept', [TeamController::class, 'acceptLeadership'])->name('team.leadership.accept');
    Route::post('/team/leadership/{notification}/decline', [TeamController::class, 'declineLeadership'])->name('team.leadership.decline');
    Route::post('/team/leadership/{notification}/remind-later', [TeamController::class, 'remindLeadershipLater'])->name('team.leadership.remind-later');
    Route::delete('/team/members/{student}', [TeamController::class, 'removeMember'])->name('team.members.remove');
    Route::delete('/team', [TeamController::class, 'destroy'])->name('team.destroy');
    Route::post('/team/leave', [TeamController::class, 'requestLeave'])->name('team.leave-request');
    Route::post('/team/leave/{approval}/approve', [TeamController::class, 'approveLeave'])->name('team.leave.approve');
    Route::post('/team/leave/{approval}/decline', [TeamController::class, 'declineLeave'])->name('team.leave.decline');
    Route::get('/team/leave/popups', [TeamController::class, 'leavePopups'])->name('team.leave.popups');
    Route::post('/recruitment', [RecruitmentController::class, 'store'])->name('recruitment.store');
    Route::put('/recruitment/{recruitment}', [RecruitmentController::class, 'update'])->name('recruitment.update');
    Route::delete('/recruitment/{recruitment}', [RecruitmentController::class, 'destroy'])->name('recruitment.destroy');

    Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
    Route::post('/invitations/{invitation}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');
    Route::post('/invitations/{invitation}/decline', [InvitationController::class, 'decline'])->name('invitations.decline');
    Route::post('/invitations/{invitation}/remind-later', [InvitationController::class, 'remindLater'])->name('invitations.remind-later');
    Route::get('/invitations/popup', [InvitationController::class, 'popup'])->name('invitations.popup');

    Route::get('/duplication', [DuplicationController::class, 'index'])->name('duplication.index');
    Route::post('/duplication/check', [DuplicationController::class, 'check'])->name('duplication.check');

    Route::middleware(['track.student'])->prefix('student')->group(function () {
        Route::get('/personal-info', [PersonalInfoController::class, 'show'])->name('student.personal-info');
        Route::get('/personal-info/edit', [PersonalInfoController::class, 'edit'])->name('student.personal-info.edit');
        Route::post('/personal-info', [PersonalInfoController::class, 'store'])->name('student.personal-info.store');

        Route::middleware(['profile.complete'])->group(function () {
            Route::redirect('/', '/student/dashboard');
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('student.dashboard');
            Route::get('/team', [TeamController::class, 'index'])->name('student.team');
            Route::get('/experts', [StudentExpertController::class, 'index'])->name('student.experts');
            Route::get('/submission', [SubmissionController::class, 'create'])->name('student.submission');
            // Handles the actual file upload + multi-recipient Drive delivery
            Route::post('/submission', [SubmissionController::class, 'store'])->name('student.submission.store');
            Route::get('/submission/subject-adviser-folders', [SubmissionController::class, 'subjectAdviserFolders'])->name('student.submission.subject-adviser-folders');
            Route::get('/submission/next-version', [SubmissionController::class, 'nextVersionForTitle'])->name('student.submission.next-version');
            Route::get('/review', [StudentReviewController::class, 'index'])->name('student.review');
            Route::get('/review/{review}/pdf', [StudentReviewController::class, 'file'])->name('student.review.pdf');
            Route::get('/review/{review}/remarks', [StudentReviewController::class, 'remarks'])->name('student.review.remarks');
            Route::get('/review/{reviewId}/clf', [ConsultationLogController::class, 'generate'])->name('student.review.clf');
            Route::view('/duplication', 'student.duplication')->name('student.duplication');
            Route::get('/consultation', [StudentConsultationController::class, 'index'])->name('student.consultation');
            Route::post('/consultation/book', [StudentConsultationController::class, 'book'])->name('student.consultation.book');
            Route::post('/consultation/cancel/{bookingId}', [StudentConsultationController::class, 'cancel'])->name('student.consultation.cancel');
            Route::get('/progress', [StudentProgressController::class, 'index'])->name('student.progress');
            Route::post('/progress/milestones/mark-done', [StudentProgressController::class, 'markDone'])->name('student.progress.milestones.mark-done');
            Route::post('/progress/milestones/update-picture', [StudentProgressController::class, 'updatePicture'])->name('student.progress.milestones.update-picture');
            Route::get('/financial', [StudentFinancialController::class, 'index'])->name('student.financial');
            Route::post('/financial/submit', [StudentFinancialController::class, 'submitReceipt'])->name('student.financial.submit');
            Route::get('/resources', [StudentResourceController::class, 'index'])->name('student.resources');
            Route::get('/archive', [StudentArchiveController::class, 'index'])->name('student.archive');
            Route::redirect('/profile', '/student/personal-info/edit')->name('student.profile');
        });
    });

    Route::post('/profile/update', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/count', [NotificationController::class, 'count'])->name('notifications.count');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::post('/notifications/seen', [NotificationController::class, 'markSeen'])->name('notifications.seen');
});
Route::prefix('instructor/schedule')
    ->middleware(['expert.auth', 'no.cache', 'track.expert'])
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
Route::middleware(['expert.auth', 'track.expert'])->prefix('admin/gdrive')->name('gdrive.')->group(function () {
    Route::get('/connect', [GoogleDriveController::class, 'connect'])->name('connect');
    Route::get('/callback', [GoogleDriveController::class, 'callback'])->name('callback');
    Route::get('/status', [GoogleDriveController::class, 'status'])->name('status');
    Route::post('/disconnect', [GoogleDriveController::class, 'disconnect'])->name('disconnect');
    Route::get('/folders', [GoogleDriveController::class, 'listFolders'])->name('folders.index');
    Route::post('/folders', [GoogleDriveController::class, 'createFolder'])->name('folders.store');
    Route::get('/submission-folders', [GoogleDriveController::class, 'listSubmissionFolders'])->name('submission-folders.index');
    Route::post('/submission-folders', [GoogleDriveController::class, 'storeSubmissionFolder'])->name('submission-folders.store');
    Route::delete('/submission-folders/{id}', [GoogleDriveController::class, 'destroySubmissionFolder'])->name('submission-folders.destroy');
    Route::post('/upload', [GoogleDriveController::class, 'upload'])->name('upload');
    Route::post('/resources/{resource}/download', [GoogleDriveController::class, 'trackDownload'])->name('resources.download');
    Route::delete('/resources/{resource}', [GoogleDriveController::class, 'destroy'])->name('resources.destroy');
});
