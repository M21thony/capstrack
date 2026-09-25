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
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Capsa\ChecklistController;
use App\Http\Controllers\Capsa\ChecklistMilestoneController;
use App\Http\Controllers\Capsa\DefenseSchedulingController;
use App\Http\Controllers\Capsa\DeliverableCheckController;
use App\Http\Controllers\Capsa\DeliverableController;
use App\Http\Controllers\Capsa\DocumentFileController;
use App\Http\Controllers\Capsa\ExpertRemovalRequestController;
use App\Http\Controllers\Capsa\ExpertRosterController;
use App\Http\Controllers\Capsa\RemovalController;
use App\Http\Controllers\Capsa\SectionProgressController;
use App\Http\Controllers\Capsa\StudentController as CapsaStudentController;
use App\Http\Controllers\Capsa\StudentRosterController;
use App\Http\Controllers\Capsa\TeamManagementController;
use App\Http\Controllers\Capsa\TeamRulesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\DuplicationController;
use App\Http\Controllers\ExpertController;
use App\Http\Controllers\ExpertNotificationController;
use App\Http\Controllers\IdleSessionController;
use App\Http\Controllers\Instructor\AssignmentController as InstructorAssignmentController;
use App\Http\Controllers\Instructor\ConsultationLogController;
use App\Http\Controllers\Instructor\DashboardController as InstructorDashboardController;
use App\Http\Controllers\Instructor\DocReviewController;
use App\Http\Controllers\Instructor\GradingController;
use App\Http\Controllers\Instructor\ProfileController as InstructorProfileController;
use App\Http\Controllers\Instructor\ProgressController as InstructorProgressController;
use App\Http\Controllers\Instructor\ScheduleController;
use App\Http\Controllers\Instructor\TeamController as InstructorTeamController;
use App\Http\Controllers\Instructor\TitleConfirmationController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PdfAnnotationController;
use App\Http\Controllers\PersonalInfoController;
use App\Http\Controllers\PreProposalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RecruitmentController;
use App\Http\Controllers\Student\ArchiveController as StudentArchiveController;
use App\Http\Controllers\Student\DefenseScoresController;
use App\Http\Controllers\Student\ExpertController as StudentExpertController;
use App\Http\Controllers\Student\PortalTourController;
use App\Http\Controllers\Student\ProgressController as StudentProgressController;
use App\Http\Controllers\Student\ResourceController as StudentResourceController;
use App\Http\Controllers\StudentConsultationController;
use App\Http\Controllers\StudentFinancialController;
use App\Http\Controllers\StudentReviewController;
use App\Http\Controllers\StudentSectionController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\TeamController;
use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Same /admin URL and route-name space as before - only the middleware on
// each sub-group changed, so no existing route('admin.xxx') link anywhere
// needed touching. See app/Models/CapstoneExpert.php's is_admin/is_capsa/
// is_staff for what each bucket means.
// Pages both roles open. They are registered under BOTH prefixes rather
// than shared from one, so an Admin never sees /capsa in the address of a
// page that is theirs, and a CaPSA never sees /admin. Same controllers
// either way - only the address and the guard in front of it differ.
$pagesBothRolesOpen = function () {
    // Expert List upload is shared. It only creates approved accounts
    // with NO roles attached (see Capsa\ExpertRosterController), so it
    // hands Admin no role-assignment power - it just gets people into
    // the system. That matters on a fresh install: Admin can populate
    // the list from the .xlsx without hand-typing anyone's email, then
    // make one of them CaPSA, who assigns everyone's roles from there.
    Route::get('/rosters/experts/template', [ExpertRosterController::class, 'template'])->name('rosters.experts.template');
    Route::post('/rosters/experts/preview', [ExpertRosterController::class, 'preview'])->name('rosters.experts.preview');
    Route::post('/rosters/experts', [ExpertRosterController::class, 'store'])->name('rosters.experts.store');

    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [AdminProfileController::class, 'index'])->name('profile');
    Route::put('/profile', [AdminProfileController::class, 'update'])->name('profile.update');
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
    // What the Panel Chair rule reads: Master's degree or higher.
    Route::put('/experts/{expert}/degree', [AdminExpertsController::class, 'updateDegree'])->name('experts.degree.update');
    Route::delete('/experts/{expert}', [AdminExpertsController::class, 'destroy'])->name('experts.destroy');
    // Not bound to the model: route-model binding cannot find a deactivated
    // account, which is the only kind this is ever called on.
    Route::put('/experts/{expert}/activation', [AdminExpertsController::class, 'reactivate'])->name('experts.activation.update');
    Route::put('/notifications/seen', function (Request $request) {
        $ids = $request->input('ids', []);
        if (! empty($ids)) {
            $seen = session('admin_notification_seen_ids', []);
            $seen = array_unique(array_merge($seen, $ids));
            session(['admin_notification_seen_ids' => $seen]);
        }

        return response()->json(['success' => true]);
    })->name('notifications.seen');
};
// Admin's own surface, and nothing else: naming a CaPSA, the student
// accounts, and the system settings. Everything else an Admin can open is
// shared with CaPSA and lives under /capsa with it.
Route::middleware(['expert.auth', 'no.cache', 'track.expert'])->prefix('admin')->name('admin.')->group(function () use ($pagesBothRolesOpen) {

    Route::middleware('expert.admin')->group(function () {
        // Making a CaPSA from an email alone, for someone who has no account
        // yet - there is no permanent CaPSA, so this happens often enough
        // that it should not need a spreadsheet with one row in it.
        Route::post('/experts/capsa', [AdminExpertsController::class, 'storeCapsa'])->name('experts.capsa.store');
        Route::put('/experts/{expert}/capsa', [AdminExpertsController::class, 'toggleCapsa'])->name('experts.capsa.update');
        // Changing sections is not a change of role, so it does not go
        // through the toggle - see AdminExpertsController::updateSections.
        Route::put('/experts/{expert}/sections', [AdminExpertsController::class, 'updateSections'])->name('experts.sections');
        // A register, not an editor. Students belong to their CaPSA - enrolled
        // from the section list and the roster import, both on that side - so the
        // write routes are gone rather than hidden: a button an admin cannot use
        // is a smaller problem than an endpoint they still can.
        Route::get('/students', [StudentAccountsController::class, 'index'])->name('students');
        Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings');
        Route::put('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
        // Sections have to exist before anybody can be made a CaPSA over them,
        // and they cannot be worked out from students on an install that has
        // none yet - see App\Support\SectionCatalog.
        Route::post('/settings/sections', [AdminSettingsController::class, 'storeSections'])->name('settings.sections.store');
        Route::delete('/settings/sections', [AdminSettingsController::class, 'destroySection'])->name('settings.sections.destroy');
    });

    Route::middleware('expert.admin')->group($pagesBothRolesOpen);
});

// The CaPSA's pages answered on /admin until now, so a bookmark, an open tab
// or a pasted link still points there. These send those to where the page
// actually lives rather than showing a 404 for an address that worked
// yesterday. Safe to delete once nobody is on an old link - the paths kept
// under /admin are listed above, and none of them is matched here.
// Who is already in the system, for the add-one-at-a-time boxes. Staff only:
// a plain adviser has no reason to be able to page through the college.
Route::middleware(['expert.auth', 'expert.staff'])
    ->get('/directory/people', [DirectoryController::class, 'people'])
    ->name('directory.people');

Route::redirect('/admin', '/capsa/dashboard');
Route::get('/admin/{path}', function (string $path) {
    // Only what actually moved. /admin/students, /admin/settings and
    // /admin/gdrive are still served from /admin, and a catch-all that
    // swallowed them would redirect a working page into a loop.
    return redirect('/capsa/'.$path);
})->where('path', '(?!students|settings|gdrive|experts)[a-zA-Z0-9/_-]+');

// The CaPSA's surface. This used to sit under /admin with everything else,
// which meant a CaPSA saw "admin" in the address of every page they opened -
// including the ones an Admin cannot reach at all. The pages both roles open
// (dashboard, profile, the expert list, the sections) live here too: the
// system is overwhelmingly the CaPSA's to run, so their reading of the URL is
// the one that should be right.
Route::middleware(['expert.auth', 'no.cache', 'track.expert'])->prefix('capsa')->name('capsa.')->group(function () use ($pagesBothRolesOpen) {
    Route::middleware('expert.capsa')->group(function () {
        // Approving an account also assigns that expert's role(s) - approve()
        // requires role_ids - so this stays CaPSA-only: assigning expert
        // categories is CaPSA's call, not Admin's.
        Route::get('/approval', [AdminApprovalController::class, 'index'])->name('approval');
        Route::patch('/approval/{expert_id}', [AdminApprovalController::class, 'update'])->name('approval.update');
        // Changing them afterwards is the same call, so it sits beside it rather
        // than in the shared block. Admin's copy of Expert Management lists
        // CaPSAs and only CaPSAs, so there was never an expert on that page whose
        // roles an Admin could be setting - and a button they cannot use is a
        // smaller problem than an endpoint they still can.
        Route::put('/experts/{expert}/roles', [AdminExpertsController::class, 'updateRoles'])->name('experts.update-roles');
        Route::get('/panel_assignment', [AdminPanelAssignmentController::class, 'index'])->name('panel_assignment');
        // Dates for the teams that can defend - its actions are the panel_assignment scheduling endpoints.
        Route::get('/defense-scheduling', [DefenseSchedulingController::class, 'index'])->name('defense_scheduling');
        // Team Management is the single page for everything about the teams
        // in a CaPSA's sections. Team Verification's actions keep their old
        // route names so nothing that links to them breaks, but its page
        // redirects here - it was only ever half of this.
        Route::get('/team-management', [TeamManagementController::class, 'index'])->name('team_management');
        // One page per level of detail, each an ordinary URL, so the browser's
        // Back button, a reload, a new tab and a bookmark all work on their own.
        Route::get('/team-management/{team}', [TeamManagementController::class, 'show'])->name('team_management.show');
        Route::get('/team-management/{team}/pre-proposals', [TeamManagementController::class, 'preProposals'])->name('team_management.pre_proposals');
        Route::get('/team-management/{team}/pre-proposals/{preProposal}', [TeamManagementController::class, 'title'])->name('team_management.title');
        Route::get('/team-management/{team}/pre-proposals/{preProposal}/similar/{rank}', [TeamManagementController::class, 'similar'])->name('team_management.similar');
        // The rules every team is formed under: its size, and whether students
        // may create or join one. One resource, replaced whole on save.
        Route::get('/team-rules', [TeamRulesController::class, 'show'])->name('team_rules');
        Route::put('/team-rules', [TeamRulesController::class, 'update'])->name('team_rules.update');
        // A member leaving a team that passed Title Defense - the CaPSA decides.
        Route::get('/leave-requests', [App\Http\Controllers\Capsa\LeaveRequestController::class, 'index'])->name('leave_requests');
        Route::patch('/leave-requests/{leaveRequest}', [App\Http\Controllers\Capsa\LeaveRequestController::class, 'update'])->name('leave_requests.update');
        // A team changing an expert who already took them on - the CaPSA decides.
        Route::patch('/expert-removal-requests/{expertRemovalRequest}', [ExpertRemovalRequestController::class, 'update'])->name('expert_removal_requests.update');
        Route::get('/verify-teams', fn () => redirect()->route('capsa.team_management'))->name('verify_teams');
        Route::patch('/verify-teams/{team}', [AdminTeamVerificationController::class, 'verify'])->name('verify_teams.verify');
        Route::patch('/verify-teams/pre-proposals/{preProposal}', [AdminTeamVerificationController::class, 'updatePreProposal'])->name('verify_teams.pre_proposals.update');
        Route::get('/verify-teams/pre-proposals/{preProposal}/document', [AdminTeamVerificationController::class, 'viewPreProposalDocument'])->name('verify_teams.pre_proposals.document');
        // A CaPSA's checklists, edited in the Edit milestones window on the
        // Milestones tab. One per program serves every section of that program.
        Route::get('/checklists', [ChecklistController::class, 'index'])->name('checklists.index');
        Route::post('/checklists', [ChecklistController::class, 'store'])->name('checklists.store');
        Route::get('/checklists/{checklist}', [ChecklistController::class, 'show'])->name('checklists.show');
        // Any drag or arrow-key move sends the whole order at once.
        Route::put('/checklists/{checklist}/order', [ChecklistController::class, 'updateOrder'])->name('checklists.order.update');
        Route::post('/checklists/{checklist}/milestones', [ChecklistMilestoneController::class, 'store'])->name('checklists.milestones.store');
        Route::patch('/checklist-milestones/{milestone}', [ChecklistMilestoneController::class, 'update'])->name('checklist_milestones.update');
        Route::delete('/checklist-milestones/{milestone}', [ChecklistMilestoneController::class, 'destroy'])->name('checklist_milestones.destroy');
        Route::post('/checklist-milestones/{milestone}/deliverables', [DeliverableController::class, 'store'])->name('checklist_milestones.deliverables.store');
        // The bulk bar changes or removes several at once.
        Route::patch('/deliverables', [DeliverableController::class, 'updateMany'])->name('deliverables.update_many');
        Route::delete('/deliverables', [DeliverableController::class, 'destroyMany'])->name('deliverables.destroy_many');
        Route::patch('/deliverables/{deliverable}', [DeliverableController::class, 'update'])->name('deliverables.update');
        // Undo for a remove.
        Route::put('/removals', [RemovalController::class, 'update'])->name('removals.update');
        // The CaPSA's check of one team's signed paper.
        Route::put('/deliverables/{deliverable}/checks/{team}', [DeliverableCheckController::class, 'update'])->name('deliverables.checks.update');
        Route::delete('/deliverables/{deliverable}/checks/{team}', [DeliverableCheckController::class, 'destroy'])->name('deliverables.checks.destroy');
        // One section of the tracker, loaded when its tab is picked.
        Route::get('/sections/{section}/progress', [SectionProgressController::class, 'show'])->name('sections.progress');
        // The file a team uploaded for a line, opened by the CaPSA to check it.
        Route::get('/documents/{document}/file', [DocumentFileController::class, 'show'])->name('documents.file');
        Route::get('/rubric_management', [GradingRubricController::class, 'index'])->name('rubric_management');
        Route::get('/financial', [AdminFinancialController::class, 'index'])->name('financial');
        Route::post('/financial/expense-post', [AdminFinancialController::class, 'storeExpensePost'])->name('financial.expense-post.store');
        Route::put('/financial/expense-post/{expensePost}', [AdminFinancialController::class, 'updateExpensePost'])->name('financial.expense-post.update');
        Route::delete('/financial/expense-post/{expensePost}', [AdminFinancialController::class, 'destroyExpensePost'])->name('financial.expense-post.destroy');
        Route::patch('/financial/receipts/{financialRecord}', [AdminFinancialController::class, 'updateReceipt'])->name('financial.receipt.update');
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

        // Scoped to the sections the viewer covers, and an Admin covers none -
        // the page is empty for them, and Student Accounts below is the
        // unscoped list they actually want.
        Route::get('/sections', [StudentSectionController::class, 'index'])->name('sections');

        // A CaPSA's own students. These moved off the admin page, where the person
        // who runs the subject had to ask someone else to fix a section.
        // PATCH rather than PUT: the row editor sends everything and setting a
        // section for a group sends only that, and both are partial changes to the
        // same record. DELETE takes them off the roster - the account stays,
        // because their team history and submissions hang off it.
        Route::post('/students', [CapsaStudentController::class, 'store'])->name('students.store');
        Route::patch('/students/{student}', [CapsaStudentController::class, 'update'])->name('students.update');
        Route::delete('/students/{student}', [CapsaStudentController::class, 'destroy'])->name('students.destroy');
    });

    Route::middleware('expert.capsa')->group($pagesBothRolesOpen);
});

Route::middleware(['expert.auth', 'expert.approved', 'no.cache', 'track.expert'])->prefix('instructor')->name('instructor.')->group(function () {
    Route::get('/dashboard', [InstructorDashboardController::class, 'index'])->name('dashboard');
    Route::get('/teams', [InstructorTeamController::class, 'index'])->name('teams');
    // An adviser confirming a team's new title after "Redefense with a new title".
    Route::patch('/title-confirmations/{preProposal}', [TitleConfirmationController::class, 'update'])->name('title-confirmations.update');
    Route::get('/title-confirmations/{preProposal}/document', [TitleConfirmationController::class, 'document'])->name('title-confirmations.document');
    Route::get('/progress', [InstructorProgressController::class, 'index'])->name('progress');
    Route::get('/profile', [InstructorProfileController::class, 'index'])->name('profile');
    Route::put('/profile', [InstructorProfileController::class, 'update'])->name('profile.update');
    Route::view('/schedule', 'instructor.schedule')->name('schedule');
    Route::get('/grading', [GradingController::class, 'index'])->name('grading');
    Route::get('/grading/team-defenses', [GradingController::class, 'teamDefenses'])->name('grading.team-defenses');
    Route::get('/grading/{defense}', [GradingController::class, 'show'])->name('grading.show');
    Route::put('/grading/{defense}', [GradingController::class, 'save'])->name('grading.save');
    Route::patch('/grading/{defense}', [GradingController::class, 'submit'])->name('grading.submit');
    Route::post('/grading/{defense}/outcome', [GradingController::class, 'recordOutcome'])->name('grading.outcome');
    Route::post('/grading/{defense}/abstain', [GradingController::class, 'abstain'])->name('grading.abstain');
    Route::delete('/grading/{defense}/abstain', [GradingController::class, 'withdrawAbstention'])->name('grading.abstain.withdraw');
    Route::get('/docreview', [DocReviewController::class, 'index'])->name('docreview');
    Route::get('/docreview/{review}/annotate', [DocReviewController::class, 'annotate'])->name('docreview.annotate');
    Route::get('/assignments', [InstructorAssignmentController::class, 'index'])->name('assignments');
    Route::patch('/assignments/{assignment}', [InstructorAssignmentController::class, 'update'])->name('assignments.update');
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
    Route::put('/{review}/drive-file', [PdfAnnotationController::class, 'syncDrive'])->name('sync-drive');
    // The team's earlier uploads of the same requirement, to compare against.
    Route::get('/{review}/versions/{document}/file', [PdfAnnotationController::class, 'versionFile'])->name('versions.file');
    Route::get('/{review}/versions/{document}/remarks', [PdfAnnotationController::class, 'versionRemarks'])->name('versions.remarks');
});

Route::get('/', function () {
    return redirect()->route('login', request()->query());
});

Route::get('/login', [AuthController::class, 'login'])->name('login');

// Ten idle minutes and the session ends, with no warning (partials/idle-logout).
Route::post('/session/keep-alive', [IdleSessionController::class, 'keepAlive'])->name('session.keep-alive');
Route::get('/session/expired', [IdleSessionController::class, 'expired'])->name('session.expired');
Route::get('/team', [AuthController::class, 'team'])->name('team');
Route::post('/login/google', [AuthController::class, 'initiateGoogleLogin'])->name('login.google.initiate');
Route::get('/login/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('login.google.callback');
Route::get('/login/google/mock', [AuthController::class, 'showMockLogin'])->name('login.google.mock');
Route::post('/login/google/mock', [AuthController::class, 'handleMockLogin'])->name('login.google.mock.post');
Route::get('/login/rejected', [AuthController::class, 'rejected'])->name('login.rejected');
// Faculty applying for their own account, switched off rather than deleted.
//
// A CaPSA now puts faculty in herself - on the uploaded list, or one at a
// time with Add Expert on Expert Management - so an account is only ever made
// by somebody who already knows who the person is. Leaving the form reachable
// alongside that gave two ways in with different vetting behind them, and a
// queue somebody had to remember to watch.
// How a faculty member who is not on any uploaded list gets an account. The
// login page has always told them to "request account access"; for a while
// these two lines were commented out and there was nowhere to do it.
// Applying for an account is gone. Faculty arrive one of two ways - on the
// list a CaPSA uploads, or added one at a time by address - so an account is
// only ever made by somebody who already knows who this person is. Asking a
// stranger to fill in a form and then queueing it for approval was a third
// way in that duplicated both and needed policing.
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
    // Above /team/{team}, or route-model binding tries to resolve a team
    // called "leader" and 404s before this is ever reached.
    Route::put('/team/leader', [TeamController::class, 'transferLeadership'])->name('team.transfer-leadership');
    Route::put('/team/role', [TeamController::class, 'updateRole'])->name('team.role.update');
    Route::put('/team/{team}', [TeamController::class, 'update'])->name('team.update');
    Route::get('/team/check-name', [TeamController::class, 'checkName'])->name('team.check-name');
    // Before anything matching /team/{something}, so the literal path is not
    // swallowed by a route-model-binding parameter.
    Route::get('/team/available-classmates', [TeamController::class, 'getAvailableClassmates'])->name('team.available-classmates');
    Route::patch('/team/leadership/{notification}', [TeamController::class, 'updateLeadership'])->name('team.leadership.update');
    Route::put('/team/leadership/{notification}/reminder', [TeamController::class, 'remindLeadershipLater'])->name('team.leadership.remind-later');
    Route::delete('/team/members/{student}', [TeamController::class, 'removeMember'])->name('team.members.remove');
    Route::delete('/team', [TeamController::class, 'destroy'])->name('team.destroy');
    // After Title Defense, leaving or removing a member is asked of the CaPSA.
    Route::post('/team/capsa-leave-requests', [LeaveRequestController::class, 'store'])->name('team.capsa-leave-requests.store');
    Route::patch('/team/capsa-leave-requests/{leaveRequest}', [LeaveRequestController::class, 'update'])->name('team.capsa-leave-requests.update');
    Route::post('/recruitment', [RecruitmentController::class, 'store'])->name('recruitment.store');
    Route::put('/recruitment/{recruitment}', [RecruitmentController::class, 'update'])->name('recruitment.update');
    Route::delete('/recruitment/{recruitment}', [RecruitmentController::class, 'destroy'])->name('recruitment.destroy');

    // The collection first, so the literal path is matched before any
    // {invitation} route below could swallow it.
    Route::get('/invitations', [InvitationController::class, 'index'])->name('invitations.index');
    Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
    Route::patch('/invitations/{invitation}', [InvitationController::class, 'update'])->name('invitations.update');
    // Putting off an answer is a thing you set on the invitation, so it is a
    // PUT on a sub-resource rather than a verb in the path.
    Route::put('/invitations/{invitation}/reminder', [InvitationController::class, 'remindLater'])->name('invitations.remind-later');

    // NOTE: DuplicationController's index/check are now dead code - the
    // standalone casual checker was retired in favor of student.duplication
    // (below) always being tied to a real proposal submission. Left in place
    // rather than deleted in case something elsewhere still references it.
    Route::get('/duplication-legacy', [DuplicationController::class, 'index'])->name('duplication.index');
    // Running a check produces a result that is kept and referred to later
    // by id, so it is a thing being created. Nothing reaches this one - the
    // pre-proposal route below is what the page calls.
    Route::post('/duplication-checks', [DuplicationController::class, 'check'])->name('duplication-checks.store');

    Route::post('/pre-proposal/duplication-checks', [PreProposalController::class, 'runCheck'])->name('student.pre-proposal.check');
    Route::delete('/pre-proposal/duplication-checks/{check}', [PreProposalController::class, 'destroyCheck'])->name('student.pre-proposal.check.destroy');
    // A check's result read as pages rather than a modal: the whole report for
    // one title, and one archived capstone it turned up. Keyed off the check
    // because that one row covers both a scan just run and a saved title's.
    Route::get('/pre-proposal/duplication-checks/{check}', [PreProposalController::class, 'report'])->name('student.duplication.report');
    Route::get('/pre-proposal/duplication-checks/{check}/matches/{rank}', [PreProposalController::class, 'similar'])->name('student.duplication.similar');
    Route::post('/pre-proposal', [PreProposalController::class, 'store'])->name('student.pre-proposal.store');
    Route::patch('/pre-proposal', [PreProposalController::class, 'updateStatus'])->name('student.pre-proposal.submit');
    // Rewording a title the panel passed with revision.
    Route::patch('/pre-proposal/title', [PreProposalController::class, 'updateTitle'])->name('student.pre-proposal.title.update');
    Route::post('/pre-proposal/{preProposal}/choose', [PreProposalController::class, 'choose'])->name('student.pre-proposal.choose');
    Route::delete('/pre-proposal/{preProposal}', [PreProposalController::class, 'destroy'])->name('student.pre-proposal.destroy');
    Route::get('/pre-proposal/{preProposal}/document', [PreProposalController::class, 'document'])->name('student.pre-proposal.document');

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
            Route::get('/defense-scores', [DefenseScoresController::class, 'index'])->name('student.defense-scores');
            Route::patch('/progress/milestones', [StudentProgressController::class, 'markDone'])->name('student.progress.milestones.mark-done');
            Route::put('/progress/milestones/picture', [StudentProgressController::class, 'updatePicture'])->name('student.progress.milestones.update-picture');
            Route::get('/financial', [StudentFinancialController::class, 'index'])->name('student.financial');
            Route::post('/financial/receipts', [StudentFinancialController::class, 'submitReceipt'])->name('student.financial.submit');
            Route::get('/resources', [StudentResourceController::class, 'index'])->name('student.resources');
            Route::get('/archive', [StudentArchiveController::class, 'index'])->name('student.archive');
            // The manuscript a finished team hands in, from the archive page.
            Route::post('/archive/manuscript', [StudentArchiveController::class, 'submit'])->name('student.archive.manuscript.submit');
            Route::redirect('/profile', '/student/personal-info/edit')->name('student.profile');
            // Finished or skipped the welcome tour; either way, done with it.
            Route::put('/tour', [PortalTourController::class, 'update'])->name('student.tour.update');
        });
    });

    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/count', [NotificationController::class, 'count'])->name('notifications.count');
    Route::put('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::patch('/notifications', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::put('/notifications/seen', [NotificationController::class, 'markSeen'])->name('notifications.seen');
    Route::put('/notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
    // The browser hands us the address its push service gave it.
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
});
// The bell on every expert page - CaPSA, admin and instructor alike, since one
// person is often more than one of those. Deliberately not behind
// expert.approved: an unapproved expert still needs to hear a team apply to them.
// Finished or skipped the portal tour - CaPSA, Admin and Expert alike, from
// whichever sidebar they have.
Route::put('instructor/tour', [App\Http\Controllers\Instructor\PortalTourController::class, 'update'])
    ->middleware(['expert.auth'])
    ->name('instructor.tour.update');

Route::prefix('instructor/notifications')
    ->middleware(['expert.auth', 'no.cache'])
    ->name('instructor.notifications.')
    ->group(function () {
        Route::get('/', [ExpertNotificationController::class, 'index'])->name('index');
        Route::get('/count', [ExpertNotificationController::class, 'count'])->name('count');
        Route::put('/preferences', [ExpertNotificationController::class, 'preferences'])->name('preferences');
        Route::put('/seen', [ExpertNotificationController::class, 'markSeen'])->name('seen');
        Route::put('/{notification}/read', [ExpertNotificationController::class, 'markAsRead'])->name('read');
        Route::patch('/', [ExpertNotificationController::class, 'markAllRead'])->name('mark-all-read');
    });
// The browser hands us the address its push service gave it.
Route::post('instructor/push/subscribe', [PushSubscriptionController::class, 'store'])
    ->middleware(['expert.auth'])
    ->name('instructor.push.subscribe');

Route::prefix('instructor/schedule')
    ->middleware(['expert.auth', 'no.cache', 'track.expert'])
    ->group(function () {
        Route::get('slots', [ScheduleController::class, 'getSlots']);
        // Appointment duration, weekly hours and scheduling window - saving rebuilds the weekly slots.
        Route::get('availability', [ScheduleController::class, 'showAvailability']);
        Route::put('availability', [ScheduleController::class, 'updateAvailability']);
        Route::patch('bookings/{bookingId}/cancellation', [ScheduleController::class, 'updateCancellation'])->whereNumber('bookingId');
        Route::post('slots', [ScheduleController::class, 'addSlot']);
        Route::patch('slots/{id}', [ScheduleController::class, 'updateSlot']);
        Route::delete('slots/{id}', [ScheduleController::class, 'deleteSlot']);
        Route::put('slots/{id}/availability', [ScheduleController::class, 'setAvailability']);

        // One week of a repeating slot, addressed by its date. A slot stores a
        // weekday, so 'Monday 10:00' is a rule; a single Monday is a resource
        // under it, and is replaced or removed like any other.
        Route::put('slots/{id}/occurrences/{date}', [ScheduleController::class, 'replaceOccurrence'])
            ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}');
        Route::delete('slots/{id}/occurrences/{date}', [ScheduleController::class, 'removeOccurrence'])
            ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}');
    });

// ── Google Drive OAuth + resource/submission delivery (from earlier work) ──
Route::middleware(['expert.auth', 'track.expert'])->prefix('admin/gdrive')->name('gdrive.')->group(function () {
    Route::get('/connect', [GoogleDriveController::class, 'connect'])->name('connect');
    Route::get('/callback', [GoogleDriveController::class, 'callback'])->name('callback');
    Route::get('/status', [GoogleDriveController::class, 'status'])->name('status');
    Route::delete('/connection', [GoogleDriveController::class, 'disconnect'])->name('connection.destroy');
    Route::get('/folders', [GoogleDriveController::class, 'listFolders'])->name('folders.index');
    // What is inside a folder the CaPSA picked, so each file can be given
    // its own category before anything is taken on.
    Route::get('/folders/{folder}/files', [GoogleDriveController::class, 'folderFiles'])->name('folders.files.index');
    Route::post('/folders', [GoogleDriveController::class, 'createFolder'])->name('folders.store');
    Route::get('/submission-folders', [GoogleDriveController::class, 'listSubmissionFolders'])->name('submission-folders.index');
    Route::post('/submission-folders', [GoogleDriveController::class, 'storeSubmissionFolder'])->name('submission-folders.store');
    Route::delete('/submission-folders/{id}', [GoogleDriveController::class, 'destroySubmissionFolder'])->name('submission-folders.destroy');
    Route::post('/resources', [GoogleDriveController::class, 'upload'])->name('resources.store');
    // Taking on files that already exist in the CaPSA's Drive. An import
    // is the thing being made here - the files are not touched beyond
    // being opened to their students.
    Route::post('/imports', [GoogleDriveController::class, 'import'])->name('imports.store');
    Route::delete('/resources/{resource}', [GoogleDriveController::class, 'destroy'])->name('resources.destroy');
});

// Local only, so the browser tests can reach a signed-in page: the real way
// in is Google, which cannot be driven from a test run.
//
// It used to log in as student id 11 by number. That student was deleted at
// some point, loginUsingId() answered false without complaining, and every
// browser test since has been quietly redirected to /login and failed on the
// assertion after it rather than on the sign-in. It resolves a real student
// now - one who is in a team, since every page worth looking at needs one.
if (app()->environment('local')) {
    Route::get('/__dev-login-student/{page?}', function (?string $page = null) {
        $studentId = StudentSchoolYear::whereNotNull('team_id')->value('student_id')
            ?? Student::query()->value('student_id');

        abort_unless($studentId && Auth::guard('web')->loginUsingId($studentId), 404, 'No student to sign in as.');

        $route = 'student.'.($page ?: 'duplication');

        return redirect()->route(Route::has($route) ? $route : 'student.duplication');
    })->name('dev.login.student');

    // The same door for the staff side. Admin and CaPSA are different
    // accounts with different pages, and a browser test cannot sign in
    // through Google any more than a student one can.
    Route::get('/__dev-login-expert/{role}/{page?}', function (string $role, ?string $page = null) {
        // admin and capsa reach their own portals; any other role name means
        // a working expert - somebody holding it who is neither, which is
        // whose instructor/* pages are under test.
        // A numeric role is an expert id, for when the page under test only
        // has data on one particular account.
        $expert = ctype_digit($role) ? CapstoneExpert::find($role) : match ($role) {
            'admin' => CapstoneExpert::where('is_admin', true)->first(),
            'capsa' => CapstoneExpert::whereHas(
                'roles',
                fn ($q) => $q->where('role_name', CapstoneExpert::CAPSA_ROLE)
            )->first(),
            default => CapstoneExpert::where('is_admin', false)
                ->whereHas('roles', fn ($q) => $q->whereRaw('LOWER(role_name) = ?', [strtolower($role)]))
                ->whereDoesntHave('roles', fn ($q) => $q->where('role_name', CapstoneExpert::CAPSA_ROLE))
                ->first(),
        };

        abort_unless($expert, 404, 'No '.$role.' account to sign in as.');

        Auth::guard('expert')->login($expert);

        $prefix = ctype_digit($role) ? 'instructor.' : match ($role) {
            'admin' => 'admin.',
            'capsa' => 'capsa.',
            default => 'instructor.',
        };

        $route = $prefix.($page ?: 'dashboard');

        return redirect()->route(Route::has($route) ? $route : $prefix.'dashboard');
    })->name('dev.login.expert');
}
