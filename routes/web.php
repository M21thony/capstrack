<?php

use App\Http\Controllers\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Admin\AnalyticsController as AdminAnalyticsController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\ArchiveController as AdminArchiveController;
use App\Http\Controllers\Admin\GoogleDriveController;
use App\Http\Controllers\Admin\GradingRubricController;
use App\Http\Controllers\Admin\ResourcesController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\StudentAccountsController;
use App\Http\Controllers\AdminApprovalController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminExpertsController;
use App\Http\Controllers\AdminFinancialController;
use App\Http\Controllers\AdminPanelAssignmentController;
use App\Http\Controllers\AdminProfileController;
use App\Http\Controllers\AdminTeamVerificationController;
use App\Http\Controllers\Capsa\MilestoneController;
use App\Http\Controllers\Capsa\TeamManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Capsa\ExpertRosterController;
use App\Http\Controllers\Capsa\StudentRosterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DuplicationController;
use App\Http\Controllers\PreProposalController;
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

// Same /admin URL and route-name space as before - only the middleware on
// each sub-group changed, so no existing route('admin.xxx') link anywhere
// needed touching. See app/Models/CapstoneExpert.php's is_admin/is_capsa/
// is_staff for what each bucket means.
Route::middleware(['expert.auth', 'no.cache', 'track.expert'])->prefix('admin')->name('admin.')->group(function () {

    // ---- Admin only: creating a CAPSA, student accounts, system config ----
    Route::middleware('expert.admin')->group(function () {
        Route::post('/experts/{expert}/toggle-capsa', [AdminExpertsController::class, 'toggleCapsa'])->name('experts.toggle-capsa');
        // Changing sections is not a change of role, so it does not go
        // through the toggle - see AdminExpertsController::updateSections.
        Route::put('/experts/{expert}/sections', [AdminExpertsController::class, 'updateSections'])->name('experts.sections');
        Route::get('/students', [StudentAccountsController::class, 'index'])->name('students');
        Route::post('/students', [StudentAccountsController::class, 'store'])->name('students.store');
        Route::put('/students/{student}', [StudentAccountsController::class, 'update'])->name('students.update');
        Route::delete('/students/{student}', [StudentAccountsController::class, 'destroy'])->name('students.destroy');
        Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings');
        Route::post('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
    });

    // ---- CAPSA: review/approve/assign, bulk rosters ----
    Route::middleware('expert.capsa')->group(function () {
        // Approving an account also assigns that expert's role(s) - approve()
        // requires role_ids - so this stays CaPSA-only: assigning expert
        // categories is CaPSA's call, not Admin's.
        Route::get('/approval', [AdminApprovalController::class, 'index'])->name('approval');
        Route::post('/approval/{expert_id}/approve', [AdminApprovalController::class, 'approve'])->name('approval.approve');
        Route::post('/approval/{expert_id}/reject', [AdminApprovalController::class, 'reject'])->name('approval.reject');
        Route::get('/panel_assignment', [AdminPanelAssignmentController::class, 'index'])->name('panel_assignment');
        // Team Management is the single page for everything about the teams
        // in a CaPSA's sections. Team Verification's actions keep their old
        // route names so nothing that links to them breaks, but its page
        // redirects here - it was only ever half of this.
        Route::get('/team-management', [TeamManagementController::class, 'index'])->name('team_management');
        Route::get('/verify-teams', fn () => redirect()->route('admin.team_management', ['tab' => 'verification']))->name('verify_teams');
        Route::post('/verify-teams/{team}/verify', [AdminTeamVerificationController::class, 'verify'])->name('verify_teams.verify');
        Route::post('/verify-teams/pre-proposals/{preProposal}/approve', [AdminTeamVerificationController::class, 'approvePreProposal'])->name('verify_teams.pre_proposals.approve');
        Route::post('/verify-teams/pre-proposals/{preProposal}/reject', [AdminTeamVerificationController::class, 'rejectPreProposal'])->name('verify_teams.pre_proposals.reject');
        Route::get('/verify-teams/pre-proposals/{preProposal}/document', [AdminTeamVerificationController::class, 'viewPreProposalDocument'])->name('verify_teams.pre_proposals.document');
        // Milestones are created and edited deliberately here, rather than
        // falling out of posting an announcement - which is how the one
        // milestone in the database ended up with no team and no way to be
        // changed or removed.
        Route::post('/milestones', [MilestoneController::class, 'store'])->name('milestones.store');
        Route::post('/milestones/deliverable-due', [MilestoneController::class, 'setDeliverableDue'])->name('milestones.deliverable_due');
        Route::put('/milestones/{milestone}', [MilestoneController::class, 'update'])->name('milestones.update');
        Route::delete('/milestones/{milestone}', [MilestoneController::class, 'destroy'])->name('milestones.destroy');
        Route::post('/milestones/{milestone}/template', [MilestoneController::class, 'applyTemplate'])->name('milestones.template');
        Route::post('/milestones/{milestone}/items', [MilestoneController::class, 'storeItem'])->name('milestones.items.store');
        Route::post('/milestones/{milestone}/items/reorder', [MilestoneController::class, 'reorderItems'])->name('milestones.items.reorder');
        Route::put('/milestone-items/{item}', [MilestoneController::class, 'updateItem'])->name('milestones.items.update');
        Route::delete('/milestone-items/{item}', [MilestoneController::class, 'destroyItem'])->name('milestones.items.destroy');
        Route::post('/milestone-items/{item}/toggle', [MilestoneController::class, 'toggleItem'])->name('milestones.items.toggle');
        Route::get('/rubric_management', [GradingRubricController::class, 'index'])->name('rubric_management');
        Route::get('/financial', [AdminFinancialController::class, 'index'])->name('financial');
        Route::post('/financial/expense-post', [AdminFinancialController::class, 'storeExpensePost'])->name('financial.expense-post.store');
        Route::put('/financial/expense-post/{expensePost}', [AdminFinancialController::class, 'updateExpensePost'])->name('financial.expense-post.update');
        Route::delete('/financial/expense-post/{expensePost}', [AdminFinancialController::class, 'destroyExpensePost'])->name('financial.expense-post.destroy');
        Route::post('/financial/receipt/{financialRecord}/approve', [AdminFinancialController::class, 'approveReceipt'])->name('financial.receipt.approve');
        Route::post('/financial/receipt/{financialRecord}/reject', [AdminFinancialController::class, 'rejectReceipt'])->name('financial.receipt.reject');
        // Upload is a modal on Student Section List itself, not its own page.
        // The template is offered inside that same modal - a CaPSA who has to
        // go looking for it elsewhere ends up typing the headings by hand.
        Route::get('/rosters/students/template', [StudentRosterController::class, 'template'])->name('rosters.students.template');
        Route::post('/rosters/students/preview', [StudentRosterController::class, 'preview'])->name('rosters.students.preview');
        Route::post('/rosters/students', [StudentRosterController::class, 'store'])->name('rosters.students.store');

        // Archive/Announcements/Resources are academic-process content
        // (archived capstones, lesson templates, student-facing
        // announcements) - CaPSA's territory, not Admin's.
        Route::get('/archive', [AdminArchiveController::class, 'index'])->name('archive');
        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements');
        Route::get('/resources', [ResourcesController::class, 'index'])->name('resources');
    });

    // ---- Either Admin or CAPSA ----
    Route::middleware('expert.staff')->group(function () {
        // Expert List upload is shared. It only creates approved accounts
        // with NO roles attached (see Capsa\ExpertRosterController), so it
        // hands Admin no role-assignment power - it just gets people into
        // the system. That matters on a fresh install: Admin can populate
        // the list from the .xlsx without hand-typing anyone's email, then
        // make one of them CaPSA, who assigns everyone's roles from there.
        Route::get('/rosters/experts/template', [ExpertRosterController::class, 'template'])->name('rosters.experts.template');
        Route::post('/rosters/experts', [ExpertRosterController::class, 'store'])->name('rosters.experts.store');

        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/profile', [AdminProfileController::class, 'index'])->name('profile');
        Route::post('/profile', [AdminProfileController::class, 'update'])->name('profile.update');
        Route::get('/sections', [StudentSectionController::class, 'index'])->name('sections');
        Route::get('/analytics', [AdminAnalyticsController::class, 'index'])->name('analytics');
        Route::get('/activitylog', [AdminActivityLogController::class, 'index'])->name('activitylog');

        // Managing the expert ROSTER (viewing, editing, removing accounts)
        // is shared - both Admin and CAPSA need it day to day. Student
        // ACCOUNT management stays Admin-only (below) since CAPSA already
        // has Student Section List for their section-scoped student view -
        // a second, unscoped "manage every student" page on top of that
        // would just be a confusing duplicate.
        Route::get('/experts', [AdminExpertsController::class, 'index'])->name('experts');
        Route::post('/experts', [AdminExpertsController::class, 'store'])->name('experts.store');
        Route::put('/experts/{expert}', [AdminExpertsController::class, 'update'])->name('experts.update');
        Route::put('/experts/{expert}/roles', [AdminExpertsController::class, 'updateRoles'])->name('experts.update-roles');
        Route::delete('/experts/{expert}', [AdminExpertsController::class, 'destroy'])->name('experts.destroy');
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
});

Route::middleware(['expert.auth', 'expert.approved', 'no.cache', 'track.expert'])->prefix('instructor')->name('instructor.')->group(function () {
    Route::get('/dashboard', [InstructorDashboardController::class, 'index'])->name('dashboard');
    Route::get('/teams', [InstructorTeamController::class, 'index'])->name('teams');
    Route::get('/progress', [InstructorProgressController::class, 'index'])->name('progress');
    Route::get('/profile', [InstructorProfileController::class, 'index'])->name('profile');
    Route::post('/profile', [InstructorProfileController::class, 'update'])->name('profile.update');
    Route::view('/schedule', 'instructor.schedule')->name('schedule');
    Route::get('/grading', [GradingController::class, 'index'])->name('grading');
    Route::get('/grading/{defense}', [GradingController::class, 'show'])->name('grading.show');
    Route::post('/grading/{defense}/save', [GradingController::class, 'save'])->name('grading.save');
    Route::post('/grading/{defense}/submit', [GradingController::class, 'submit'])->name('grading.submit');
    Route::post('/grading/{defense}/outcome', [GradingController::class, 'recordOutcome'])->name('grading.outcome');
    Route::get('/docreview', [DocReviewController::class, 'index'])->name('docreview');
    Route::get('/docreview/{review}/annotate', [DocReviewController::class, 'annotate'])->name('docreview.annotate');
    Route::get('/assignments', [InstructorAssignmentController::class, 'index'])->name('assignments');
    Route::post('/assignments/{assignment}/approve', [InstructorAssignmentController::class, 'approve'])->name('assignments.approve');
    Route::post('/assignments/{assignment}/reject', [InstructorAssignmentController::class, 'reject'])->name('assignments.reject');
    Route::delete('/assignments/{assignment}', [InstructorAssignmentController::class, 'remove'])->name('assignments.remove');
    // The pre-proposal PDF a team applied with, when they used the one
    // their CaPSA approved instead of attaching a file.
    Route::get('/assignments/{assignment}/pre-proposal', [InstructorAssignmentController::class, 'preProposalDocument'])->name('assignments.pre_proposal');
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
    // login()/initiateGoogleLogin()/handleGoogleCallback()/showMockLogin()/
    // handleMockLogin() used to live here too - a second, near-duplicate
    // OAuth implementation nothing in the app ever linked to (the real
    // expert login path is AuthController's, above). Removed rather than
    // kept in sync, since it had drifted to only accept @gmail.com and
    // would need updating alongside AuthController's domain check forever
    // otherwise.
    Route::post('/logout', [ExpertController::class, 'logout'])->name('expert.logout');
});

Route::middleware(['auth', 'no.cache', 'track.expert'])->group(function () {
    Route::post('/team', [TeamController::class, 'store'])->name('team.store');
    Route::put('/team/{team}', [TeamController::class, 'update'])->name('team.update');
    Route::get('/team/check-name', [TeamController::class, 'checkName'])->name('team.check-name');
    // Before anything matching /team/{something}, so the literal path is not
    // swallowed by a route-model-binding parameter.
    Route::get('/team/available-classmates', [TeamController::class, 'getAvailableClassmates'])->name('team.available-classmates');
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

    // NOTE: DuplicationController's index/check are now dead code - the
    // standalone casual checker was retired in favor of student.duplication
    // (below) always being tied to a real proposal submission. Left in place
    // rather than deleted in case something elsewhere still references it.
    Route::get('/duplication-legacy', [DuplicationController::class, 'index'])->name('duplication.index');
    Route::post('/duplication/check', [DuplicationController::class, 'check'])->name('duplication.check');

    Route::post('/pre-proposal/check', [PreProposalController::class, 'runCheck'])->name('student.pre-proposal.check');
    Route::post('/pre-proposal', [PreProposalController::class, 'store'])->name('student.pre-proposal.store');
    Route::post('/pre-proposal/submit', [PreProposalController::class, 'submit'])->name('student.pre-proposal.submit');
    Route::post('/pre-proposal/unsubmit', [PreProposalController::class, 'unsubmit'])->name('student.pre-proposal.unsubmit');
    Route::post('/pre-proposal/{preProposal}/choose', [PreProposalController::class, 'choose'])->name('student.pre-proposal.choose');
    Route::delete('/pre-proposal/{preProposal}', [PreProposalController::class, 'destroy'])->name('student.pre-proposal.destroy');

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
            Route::get('/duplication', [PreProposalController::class, 'edit'])->name('student.duplication');
            Route::get('/consultation', [StudentConsultationController::class, 'index'])->name('student.consultation');
            Route::get('/consultation/refresh', [StudentConsultationController::class, 'refreshData'])->name('student.consultation.refresh');
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

// TEMPORARY - local-only, for visually verifying the student/duplication
// redesign via Playwright. Logs in as the real student behind tonight's
// actual testing (id 11) and redirects straight to the page. Remove
// after the visual check is done - never enable outside local.
if (app()->environment('local')) {
    Route::get('/__dev-login-student', function () {
        Auth::guard('web')->loginUsingId(11);

        return redirect()->route('student.duplication');
    });
}
