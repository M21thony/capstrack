<?php

namespace App\Providers;

use App\Models\CapstoneDocumentReview;
use App\Models\DefenseSchedule;
use App\Models\Notification;
use App\Models\StudentRole;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Models\TeamInvitation;
use App\Services\OllamaSSHClient;
use App\Services\SimilarityService;
use App\Support\LeaveDecisionDetails;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Scripts that draw their page from data embedded in it.
     *
     * @var list<string>
     */
    public const PAGE_DRAWING_SCRIPTS = [
        'resources/js/financial/capsa.js',
        'resources/js/financial/student.js',
        'resources/js/defenses/defenses.js',
    ];

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

        // The pages these scripts draw - everything below the header comes
        // from them - are held off the screen until the script has run, so
        // their first frame is the finished page. Drawn first and filled in
        // after, the page jumped once as it filled, on every visit.
        Vite::useScriptTagAttributes(
            fn (string $src): array => in_array($src, self::PAGE_DRAWING_SCRIPTS, true) ? ['blocking' => 'render'] : []
        );

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

                // The rest of where they stand: who is on the team, who their
                // experts are, and the next defense. Worked out once a request,
                // however many student partials this composer runs for.
                ['members' => $panelMembers, 'experts' => $panelExperts, 'defense' => $panelDefense] = $panelTeam
                    ? once(fn () => $this->panelDetails($panelTeam))
                    : ['members' => collect(), 'experts' => [], 'defense' => null];

                $view->with(compact(
                    'unreadNotifications', 'unreadCount', 'bellNotifications', 'leaveDecisions', 'joinRequestPopups',
                    'panelTeam', 'panelRole', 'panelMembers', 'panelExperts', 'panelDefense'
                ));
            }
        });

        Route::model('review', CapstoneDocumentReview::class);
    }

    /**
     * The team, its experts and its next defense, for the profile panel.
     *
     * @return array{members: Collection<int, array{name: string, initials: string, leader: bool, id: int}>, experts: array<string, ?string>, defense: ?DefenseSchedule}
     */
    private function panelDetails(Team $team): array
    {
        $members = StudentSchoolYear::where('team_id', $team->team_id)->with('student')->get()
            ->filter(fn ($sy) => $sy->student)
            ->unique('student_id')
            ->map(fn ($sy) => [
                'id' => (int) $sy->student_id,
                'name' => trim($sy->student->f_name.' '.$sy->student->l_name) ?: $sy->student->email,
                'initials' => $sy->student->avatarInitials(),
                'leader' => (int) $team->leader_id === (int) $sy->student_id,
            ])
            ->sortBy(fn ($m) => [! $m['leader'], $m['name']])
            ->values();

        $engaged = TeamExpertAssignment::where('team_id', $team->team_id)->where('status', 'approved')->with('expert')->get();
        $experts = collect(['adviser' => 'Adviser', 'consultant' => 'Consultant', 'grammarian' => 'Grammarian'])
            ->mapWithKeys(fn ($label, $key) => [$label => $engaged
                ->first(fn ($a) => strtolower(trim((string) $a->engaged_role_name)) === $key)?->expert?->name])
            ->all();

        $defense = DefenseSchedule::where('team_id', $team->team_id)
            ->where('schedule_date', '>=', now()->startOfDay())
            ->orderBy('schedule_date')
            ->first();

        return ['members' => $members, 'experts' => $experts, 'defense' => $defense];
    }
}
