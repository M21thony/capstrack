<?php

namespace App\Providers;

use App\Models\CapstoneDocumentReview;
use App\Models\Notification;
use App\Models\StudentRole;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Services\OllamaSSHClient;
use App\Services\SimilarityService;
use App\Support\LeaveDecisionDetails;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OllamaSSHClient::class,
            fn () => new OllamaSSHClient
        );
        $this->app->singleton(SimilarityService::class,
            fn ($app) => new SimilarityService(
                $app->make(OllamaSSHClient::class)
            )
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        View::composer('student.*', function ($view) {
            if (auth()->check()) {
                $studentId = auth()->id();
                $unreadNotifications = Notification::where('student_id', $studentId)
                    ->where('is_read', false)
                    ->latest('created_at')
                    ->take(10)
                    ->get();
                $unreadCount = Notification::where('student_id', $studentId)
                    ->where('is_read', false)
                    ->count();
                // What the bell lists: the latest, read or not, so marking
                // them read changes how they look, not whether they are there
                // - the same list the expert bell shows.
                $bellNotifications = Notification::where('student_id', $studentId)
                    ->latest('created_at')
                    ->take(15)
                    ->get();
                // A CaPSA's decision on a leave request opens in a pop-up with
                // the whole of it, not just the line in the bell.
                $leaveDecisions = LeaveDecisionDetails::forNotifications($bellNotifications, (int) $studentId);
                // "Later" on a join-request popup stores its id in
                // dismissed_invitations (see InvitationController::remindLater())
                // rather than a persisted remind_me_at column - there's no
                // such column on team_invitations.
                $dismissedInvitationIds = (array) session('dismissed_invitations', []);

                $joinRequestPopups = TeamInvitation::with(['sender', 'team'])
                    ->where('receiver_id', $studentId)
                    ->where('type', 'request')
                    ->where('status', 'pending')
                    ->whereNotIn('id', $dismissedInvitationIds)
                    ->latest('created_at')
                    ->get();
                // The profile panel in the sidebar shows where this student
                // stands in their capstone, and the sidebar is on every
                // student page - so it is fetched once, here, rather than in
                // each of a dozen controllers.
                $enrolment = StudentSchoolYear::with('role')
                    ->where('student_id', $studentId)
                    ->first();

                $panelTeam = $enrolment?->team_id ? Team::find($enrolment->team_id) : null;
                $panelRole = $panelTeam && (int) $panelTeam->leader_id === (int) $studentId
                    ? StudentRole::LEADER_ROLE_NAME
                    : $enrolment?->role?->s_role_name;

                $view->with(compact(
                    'unreadNotifications', 'unreadCount', 'bellNotifications', 'leaveDecisions', 'joinRequestPopups',
                    'panelTeam', 'panelRole'
                ));
            }
        });

        Route::model('review', CapstoneDocumentReview::class);
    }
}
