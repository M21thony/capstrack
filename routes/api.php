<?php

use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\ArchiveController;
use App\Http\Controllers\AdminPanelAssignmentController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AdminExpertController;
use App\Http\Controllers\Api\AdminFinancialController;
use App\Http\Controllers\Api\AdminGradingRubricController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\RecruitmentController;
use App\Http\Controllers\Api\StudentExpertController;
use App\Http\Controllers\Api\StudentFinancialController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Support\Facades\Route;

// Admin API routes (session based auth for experts) - split the same way
// routes/web.php's /admin group is, see that file's comment for what each
// bucket means.
Route::middleware(['web', 'expert.auth'])
    ->prefix('admin')
    ->group(function () {

        // Managing the expert roster is shared between Admin and CAPSA -
        // see routes/web.php's matching comment. Nothing currently calls
        // this API duplicate of AdminExpertsController, but it's kept in
        // the same bucket for consistency.
        Route::middleware('expert.staff')->group(function () {
            Route::get('/experts', [AdminExpertController::class, 'index'])->name('api.admin.experts.index');
            Route::delete('/experts/{expert}', [AdminExpertController::class, 'destroy'])->name('api.admin.experts.destroy');
        });

        // ---- CAPSA: review/approve/assign ----
        Route::middleware('expert.capsa')->group(function () {
            // Panel Assignment (index() stays a web route since it returns a
            // Blade view - these are the JSON-consuming endpoints the page's
            // fetch() calls hit, which need to live under /api/* so validation
            // failures render as JSON instead of a redirect - see
            // bootstrap/app.php's shouldRenderJsonWhen(), which only renders
            // JSON for api/* routes regardless of the request's Accept header.
            Route::prefix('panel_assignment')->name('api.admin.panel_assignment.')->group(function () {
                // Registered before /{team} so "windows" isn't swallowed by
                // the team route-model-binding parameter.
                Route::get('/windows', [AdminPanelAssignmentController::class, 'indexWindows'])->name('windows.index');
                Route::put('/windows/{defenseType}', [AdminPanelAssignmentController::class, 'updateWindow'])->name('windows.update');
                Route::put('/schedule/{defense}', [AdminPanelAssignmentController::class, 'updateSchedule'])->name('schedule.update');
                // Generate a stage's schedule at once: suggest every ready team a slot, then save the reviewed set.
                Route::post('/schedule-drafts', [AdminPanelAssignmentController::class, 'draftSchedule'])->name('schedule-drafts.store');
                Route::put('/schedules', [AdminPanelAssignmentController::class, 'applySchedule'])->name('schedules.update');
                Route::post('/', [AdminPanelAssignmentController::class, 'storeMultiple'])->name('store.bulk');
                Route::post('/{team}', [AdminPanelAssignmentController::class, 'store'])->name('store');
            });

            // Grading Rubrics
            Route::prefix('grading-rubrics')->name('api.admin.grading-rubrics.')->group(function () {
                Route::post('/drafts', [AdminGradingRubricController::class, 'aiGenerate'])->name('drafts.store');
                Route::get('/', [AdminGradingRubricController::class, 'index'])->name('index');
                Route::get('/{gradingRubric}', [AdminGradingRubricController::class, 'show'])->name('show');
                Route::post('/', [AdminGradingRubricController::class, 'store'])->name('store');
                Route::put('/{gradingRubric}', [AdminGradingRubricController::class, 'update'])->name('update');
                Route::delete('/{gradingRubric}', [AdminGradingRubricController::class, 'destroy'])->name('destroy');
                Route::put('/{gradingRubric}/restore', [AdminGradingRubricController::class, 'restore'])->name('restore');

                // Out of the archive: the archive is the resource, deleting from it restores.
                Route::delete('/{gradingRubric}/archive', [AdminGradingRubricController::class, 'restore'])->name('restore');

                Route::post('/{gradingRubric}/criteria', [AdminGradingRubricController::class, 'storeCriterion'])->name('criteria.store');
                Route::put('/{gradingRubric}/criteria/{rubricCriterion}', [AdminGradingRubricController::class, 'updateCriterion'])->name('criteria.update');
                Route::delete('/{gradingRubric}/criteria/{rubricCriterion}', [AdminGradingRubricController::class, 'destroyCriterion'])->name('criteria.destroy');
                Route::put('/{gradingRubric}/criteria', [AdminGradingRubricController::class, 'syncCriteria'])->name('criteria.sync');
            });

            // Financial Records - CAPSA checks uploaded payment receipts to
            // clear students for defense scheduling, not Admin.
            Route::prefix('financial')->name('api.admin.financial.')->group(function () {
                Route::get('/', [AdminFinancialController::class, 'index'])->name('index');
                Route::get('/stats', [AdminFinancialController::class, 'stats'])->name('stats');
                Route::get('/teams', [AdminFinancialController::class, 'teams'])->name('teams');
                Route::get('/records', [AdminFinancialController::class, 'records'])->name('records');
                // Verify several at once.
                Route::patch('/records', [AdminFinancialController::class, 'updateMany'])->name('update-many');
                Route::get('/records/{financialRecord}', [AdminFinancialController::class, 'record'])->name('record');
                Route::patch('/records/{financialRecord}', [AdminFinancialController::class, 'update'])->name('update');
                Route::get('/expense-posts', [AdminFinancialController::class, 'expensePosts'])->name('expense-posts');
                Route::post('/expense-posts', [AdminFinancialController::class, 'storeExpensePost'])->name('expense-posts.store');
                Route::put('/expense-posts/{expensePost}', [AdminFinancialController::class, 'updateExpensePost'])->name('expense-posts.update');
                Route::delete('/expense-posts/{expensePost}', [AdminFinancialController::class, 'destroyExpensePost'])->name('expense-posts.destroy');
            });

            // Archive/Announcements are CaPSA's academic-process content,
            // not Admin's - see routes/web.php's matching comment.
            Route::post('/announcements', [AnnouncementController::class, 'store'])->name('api.admin.announcements.store');
            Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('api.admin.announcements.update');
            Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('api.admin.announcements.destroy');
            Route::patch('/announcements/{announcement}/submissions/{submission}', [AnnouncementController::class, 'verifySubmission'])->name('api.admin.announcements.submissions.update');

            Route::post('/archive', [ArchiveController::class, 'store'])->name('api.admin.archive.store');
            Route::put('/archive/{archive}', [ArchiveController::class, 'update'])->name('api.admin.archive.update');
            Route::delete('/archive/{archive}', [ArchiveController::class, 'destroy'])->name('api.admin.archive.destroy');
        });
    });

// Student API routes (session based auth for students)
Route::middleware('auth')->group(function () {
    // Teams
    Route::get('/teams', [TeamController::class, 'index']);
    Route::post('/teams', [TeamController::class, 'store']);
    Route::get('/teams/{team}', [TeamController::class, 'show']);
    Route::put('/teams/{team}', [TeamController::class, 'update']);
    Route::delete('/teams/{team}', [TeamController::class, 'destroy']);
    Route::put('/teams/{team}/leader', [TeamController::class, 'transferLeadership']);
    Route::patch('/team/leadership/{notification}', [TeamController::class, 'updateLeadership']);
    Route::delete('/teams/{team}/members/{student}', [TeamController::class, 'removeMember']);

    // Recruitment
    Route::get('/recruitments', [RecruitmentController::class, 'index']);
    Route::post('/recruitments', [RecruitmentController::class, 'store']);
    Route::put('/recruitments/{recruitment}', [RecruitmentController::class, 'update']);
    Route::delete('/recruitments/{recruitment}', [RecruitmentController::class, 'destroy']);

    // Invitations
    Route::get('/invitations', [InvitationController::class, 'index']);
    Route::post('/invitations', [InvitationController::class, 'store']);
    Route::patch('/invitations/{invitation}', [InvitationController::class, 'update']);

    // Expert assignments
    Route::get('/experts', [StudentExpertController::class, 'index'])->name('api.student.experts.index');
    Route::get('/experts/{expert}', [StudentExpertController::class, 'show'])->name('api.student.experts.show');
    Route::post('/assignments', [StudentExpertController::class, 'store'])->name('api.student.assignments.store');
    Route::delete('/assignments/{assignment}', [StudentExpertController::class, 'destroy'])->name('api.student.assignments.destroy');

    // Student Financial
    Route::prefix('student/financial')->name('api.student.financial.')->group(function () {
        Route::get('/', [StudentFinancialController::class, 'index'])->name('index');
        Route::post('/receipts', [StudentFinancialController::class, 'store'])->name('submit');
        Route::get('/submissions', [StudentFinancialController::class, 'submissions'])->name('submissions');
        Route::get('/expense-posts', [StudentFinancialController::class, 'expensePosts'])->name('expense-posts');
        Route::get('/submissions/{financialRecord}', [StudentFinancialController::class, 'show'])->name('show');
    });

    // Activities
    Route::get('/activities', [ActivityLogController::class, 'index']);
});
