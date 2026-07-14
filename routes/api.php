<?php

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

// Admin API routes (session based auth for experts)
Route::middleware(['web', 'auth:expert', 'expert.admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/experts', [AdminExpertController::class, 'index'])->name('api.admin.experts.index');
        Route::delete('/experts/{expert}', [AdminExpertController::class, 'destroy'])->name('api.admin.experts.destroy');
        Route::post('/turnover', [AdminExpertController::class, 'transferRights'])->name('api.admin.turnover');

        // Grading Rubrics
        Route::prefix('grading-rubrics')->name('api.admin.grading-rubrics.')->group(function () {
            Route::get('/', [AdminGradingRubricController::class, 'index'])->name('index');
            Route::get('/{gradingRubric}', [AdminGradingRubricController::class, 'show'])->name('show');
            Route::post('/', [AdminGradingRubricController::class, 'store'])->name('store');
            Route::put('/{gradingRubric}', [AdminGradingRubricController::class, 'update'])->name('update');
            Route::delete('/{gradingRubric}', [AdminGradingRubricController::class, 'destroy'])->name('destroy');
            Route::post('/{gradingRubric}/criteria', [AdminGradingRubricController::class, 'storeCriterion'])->name('criteria.store');
            Route::put('/{gradingRubric}/criteria/{rubricCriterion}', [AdminGradingRubricController::class, 'updateCriterion'])->name('criteria.update');
            Route::delete('/{gradingRubric}/criteria/{rubricCriterion}', [AdminGradingRubricController::class, 'destroyCriterion'])->name('criteria.destroy');
            Route::post('/{gradingRubric}/criteria/sync', [AdminGradingRubricController::class, 'syncCriteria'])->name('criteria.sync');
        });

        // Financial Records
        Route::prefix('financial')->name('api.admin.financial.')->group(function () {
            Route::get('/', [AdminFinancialController::class, 'index'])->name('index');
            Route::get('/stats', [AdminFinancialController::class, 'stats'])->name('stats');
            Route::get('/teams', [AdminFinancialController::class, 'teams'])->name('teams');
            Route::get('/records', [AdminFinancialController::class, 'records'])->name('records');
            Route::get('/records/{financialRecord}', [AdminFinancialController::class, 'record'])->name('record');
            Route::post('/records/{financialRecord}/approve', [AdminFinancialController::class, 'approve'])->name('approve');
            Route::post('/records/{financialRecord}/reject', [AdminFinancialController::class, 'reject'])->name('reject');
            Route::get('/expense-posts', [AdminFinancialController::class, 'expensePosts'])->name('expense-posts');
            Route::post('/expense-posts', [AdminFinancialController::class, 'storeExpensePost'])->name('expense-posts.store');
            Route::put('/expense-posts/{expensePost}', [AdminFinancialController::class, 'updateExpensePost'])->name('expense-posts.update');
            Route::delete('/expense-posts/{expensePost}', [AdminFinancialController::class, 'destroyExpensePost'])->name('expense-posts.destroy');
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

    // Student Financial
    Route::prefix('student/financial')->name('api.student.financial.')->group(function () {
        Route::get('/', [StudentFinancialController::class, 'index'])->name('index');
        Route::post('/submit', [StudentFinancialController::class, 'store'])->name('submit');
        Route::get('/submissions', [StudentFinancialController::class, 'submissions'])->name('submissions');
        Route::get('/expense-posts', [StudentFinancialController::class, 'expensePosts'])->name('expense-posts');
        Route::get('/submissions/{financialRecord}', [StudentFinancialController::class, 'show'])->name('show');
    });

    // Activities
    Route::get('/activities', [ActivityLogController::class, 'index']);
});
