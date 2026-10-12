<?php

namespace App\Support;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The teams one person looks after, each summarised into one row.
 *
 * A CaPSA looks after every team in her sections; an expert looks after the
 * teams that took them on as adviser, consultant or grammarian, and the ones
 * they sit on a panel for. Someone who is both gets one list holding both,
 * each row saying whether it is hers as CaPSA ($row->inSections), as an
 * expert ($row->myRole), or both.
 *
 * One pass, a handful of queries, and every page below the list reads the
 * same rows - which is what lets a team page answer "is this team mine?"
 * without a second set of rules about who may see what.
 */
class TeamRows
{
    /** Which seat names a team, when someone holds more than one on it. */
    public const SEAT_ORDER = ['adviser', 'consultant', 'grammarian', 'panel'];

    /** The seats that follow a team's checklist. */
    public const FOLLOWS_PROGRESS = ['adviser', 'consultant'];

    /** The review role a CaPSA's format check is filed under. */
    private const FORMAT_CHECK = 'subject_adviser';

    public function __construct(
        private SectionScope $sections,
        private DefenseProgress $progress,
        private TeamChecklist $checklists,
    ) {}

    /**
     * @return Collection<int, object>
     */
    public function forViewer(
        CapstoneExpert $viewer,
        ?string $selectedSection = null,
        bool $asCapsa = true,
        bool $asExpert = true,
    ): Collection {
        $capsaSections = $asCapsa && $viewer->is_capsa ? ($viewer->sections ?? []) : [];
        $seats = $asExpert ? $this->seatsOf((int) $viewer->expert_id) : [];

        // Scoped to the term that is running - StudentSchoolYear does that by
        // default - so last semester's teams do not reappear alongside this
        // semester's, and a team retaking Capstone 1 is here on its new
        // enrolment rather than its old one.
        $syRecords = StudentSchoolYear::whereNotNull('team_id')
            ->with('student')
            ->get()
            ->groupBy('team_id');

        $candidates = Team::with(['leader', 'preProposals.duplicationCheck', 'allPreProposals.duplicationCheck'])
            ->whereIn('team_id', $syRecords->keys())
            ->orderByDesc('created_at')
            ->get();

        $teams = collect();
        $teamSections = [];
        $teamMembers = [];
        $inSections = [];

        foreach ($candidates as $team) {
            $records = $syRecords->get($team->team_id, collect());
            $section = $this->sections->sectionFor($team, $records);
            $covered = $capsaSections !== [] && $this->sections->covers($capsaSections, $section);

            if (! $covered && ! isset($seats[$team->team_id])) {
                continue;
            }

            if ($section !== '' && $selectedSection && $section !== $selectedSection) {
                continue;
            }

            $teamSections[$team->team_id] = $section !== '' ? $section : SectionScope::UNASSIGNED;
            $teamMembers[$team->team_id] = $records->map(fn ($sy) => $sy->student)->filter()->values();
            $inSections[$team->team_id] = $covered;
            $teams->push($team);
        }

        $teamIds = $teams->pluck('team_id');
        $milestones = $this->milestoneProgress($teams, $teamSections);
        $defenseSchedules = DefenseSchedule::whereIn('team_id', $teamIds)->get()->groupBy('team_id');
        $engagedRoles = $this->engagedRoles($teamIds);
        $papers = $this->papers((int) $viewer->expert_id, $teamIds);

        // Teams waiting on her first, the latest to turn a title in at the
        // very top: a team that just submitted sat wherever it was created,
        // often below the fold.
        return $teams->map(fn (Team $team) => $this->summarise(
            $team,
            $teamSections[$team->team_id],
            $teamMembers[$team->team_id],
            $milestones[$team->team_id] ?? null,
            $engagedRoles[$team->team_id] ?? collect(),
            $papers[$team->team_id] ?? collect(),
            $defenseSchedules[$team->team_id] ?? collect(),
            $inSections[$team->team_id],
            $seats[$team->team_id] ?? null,
        ))->sortBy([
            fn ($a, $b) => $b->needsCapsa <=> $a->needsCapsa,
            fn ($a, $b) => ($b->lastSubmittedAt?->getTimestamp() ?? 0) <=> ($a->lastSubmittedAt?->getTimestamp() ?? 0),
        ])->values();
    }

    /**
     * The seat this expert holds on each team: the role the team engaged
     * them as, or "panel" when they only grade it.
     *
     * @return array<int, string>
     */
    public function seatsOf(int $expertId): array
    {
        $seats = [];

        $assignments = TeamExpertAssignment::where('expert_id', $expertId)
            ->where('status', 'approved')
            ->with(['expertRole.role', 'role'])
            ->get();

        foreach ($assignments as $assignment) {
            $role = $assignment->engaged_role_name;

            if ($role && $this->ranksBefore($role, $seats[$assignment->team_id] ?? null)) {
                $seats[$assignment->team_id] = $role;
            }
        }

        $panelTeams = DefensePanel::where('expert_id', $expertId)
            ->with('schedule:defense_id,team_id')
            ->get()
            ->map(fn (DefensePanel $panel) => $panel->schedule?->team_id)
            ->filter()
            ->unique();

        foreach ($panelTeams as $teamId) {
            $seats[$teamId] ??= 'panel';
        }

        return $seats;
    }

    private function ranksBefore(string $role, ?string $held): bool
    {
        if ($held === null) {
            return true;
        }

        $rank = fn (string $seat) => ($i = array_search($seat, self::SEAT_ORDER, true)) === false ? count(self::SEAT_ORDER) : $i;

        return $rank($role) < $rank($held);
    }

    /**
     * Each team's progress on its section's checklist, worked out a section at
     * a time so the whole page costs a handful of queries.
     *
     * The whole resolution is kept, not just the totals: the list shows the
     * strip, and the team page shows every item - all off this one pass.
     *
     * @param  array<int, string>  $teamSections
     * @return array<int, object>
     */
    private function milestoneProgress(Collection $teams, array $teamSections): array
    {
        $out = [];

        foreach ($teams->groupBy(fn (Team $team) => $teamSections[$team->team_id] ?? '') as $section => $group) {
            $checklist = $this->checklists->forSection((string) $section);

            if (! $checklist) {
                continue;
            }

            foreach (MilestoneProgress::forTeams($checklist, $group->pluck('team_id')) as $teamId => $progress) {
                $out[$teamId] = $progress;
            }
        }

        return $out;
    }

    /**
     * Which roles each team has actually filled.
     *
     * Read from the assignment's expert_role_id, so an expert who is both an
     * adviser and a consultant is counted as whichever the team engaged them
     * as - not both, and not a guess.
     *
     * @return array<int, Collection<string, CapstoneExpert>>
     */
    private function engagedRoles(Collection $teamIds): array
    {
        if ($teamIds->isEmpty()) {
            return [];
        }

        return TeamExpertAssignment::whereIn('team_id', $teamIds)
            ->where('status', 'approved')
            ->with(['expert.expertise', 'expertRole.role', 'role'])
            ->get()
            ->groupBy('team_id')
            ->map(function ($assignments) {
                $filled = collect();

                foreach ($assignments as $assignment) {
                    $role = $assignment->engaged_role_name;

                    if ($role && $assignment->expert) {
                        $filled->put($role, $assignment->expert);
                    }
                }

                return $filled;
            })
            ->all();
    }

    /**
     * Papers sent to this person, under either hat: the CaPSA's format check
     * (role = subject_adviser) or an expert's review of what a paper says.
     *
     * @return array<int, Collection<int, object>>
     */
    private function papers(int $viewerId, Collection $teamIds): array
    {
        if ($teamIds->isEmpty()) {
            return [];
        }

        return DB::table('capstone_document_review as r')
            ->join('capstone_documents as d', 'd.file_id', '=', 'r.file_id')
            ->join('student_s_y as sy', 'sy.student_id', '=', 'd.student_id')
            ->leftJoin('deliverables as dl', 'dl.id', '=', 'd.deliverable_id')
            ->where('r.expert_id', $viewerId)
            ->whereIn('sy.team_id', $teamIds)
            ->orderByDesc('d.created_at')
            ->get([
                'r.review_id',
                'r.review_status',
                'r.reviewed_at',
                'r.role',
                'd.file_name',
                'd.chapter',
                'dl.chapter as line_chapter',
                'd.file_version',
                'd.created_at as submitted_at',
                'sy.team_id',
            ])
            ->groupBy('team_id')
            ->all();
    }

    /** One row: everything the list and the team page need at a glance. */
    private function summarise(
        Team $team,
        string $section,
        Collection $members,
        ?object $milestones,
        Collection $engagedRoles,
        Collection $papers,
        Collection $defenseSchedules,
        bool $inSections,
        ?string $myRole,
    ): object {
        $submitted = $team->hasSubmittedPreProposals();

        // Only titles the team has actually turned in. A draft they are still
        // working on is not the CaPSA's to read.
        $proposals = $team->preProposals->whereNotNull('submitted_at')->values();
        $approved = $proposals->where('approved_status', true);
        $pending = $proposals->filter(fn ($p) => ! $p->approved_status && ! $p->rejection_reason);

        $titleState = match (true) {
            (bool) $team->project_title => 'settled',
            $approved->count() >= 2 => 'awaiting_team_choice',
            $approved->count() === 1 => 'settling',
            $proposals->isEmpty() => 'none_submitted',
            $pending->isEmpty() => 'all_rejected',
            default => 'awaiting_review',
        };

        $initials = collect(explode(' ', $team->team_name))
            ->map(fn ($w) => strtoupper(substr($w, 0, 1)))
            ->take(2)->implode('');

        // Keyed on team_id rather than row position, so a team keeps the same
        // colour wherever it is shown.
        $palette = ['#c9a82c,#8a6e10', '#16a34a,#0891b2', '#1d4ed8,#7c3aed', '#b45309,#dc2626', '#0891b2,#16a34a'];
        $gradient = $palette[$team->team_id % count($palette)];

        $tags = is_array($team->tags) ? array_slice(array_filter(array_map('trim', $team->tags)), 0, 3) : [];

        $leaderName = $team->leader
            ? trim(($team->leader->f_name ?? '').' '.($team->leader->l_name ?? ''))
            : '—';

        $stages = $this->progress->allStages($team, $defenseSchedules);

        // A CaPSA's format checks only count on teams in her sections; the
        // rest of her reviews are an expert's, and wait on her as one.
        [$formatChecks, $reviews] = $papers->partition(fn ($p) => $p->role === self::FORMAT_CHECK);
        $formatChecks = $inSections ? $formatChecks->values() : collect();
        $unchecked = $formatChecks->where('review_status', '!=', 'Reviewed');

        return (object) [
            'team' => $team,
            'section' => $section,
            'members' => $members,
            'stages' => $stages,
            'currentStage' => $this->progress->currentStage($team, $defenseSchedules),
            'failedStage' => collect($stages)->filter(fn ($s) => $s === DefenseProgress::FAILED)->keys()->first(),
            'defenseSchedules' => $defenseSchedules,
            'initials' => $initials,
            'gradient' => $gradient,
            'tags' => $tags,
            'leaderName' => $leaderName,
            // A team's checklist is its own, its CaPSA's and the two experts it
            // chose - not its grammarian's or its panel's.
            'milestones' => $inSections || in_array($myRole, self::FOLLOWS_PROGRESS, true) ? $milestones : null,
            'engagedRoles' => $engagedRoles,
            'inSections' => $inSections,
            'myRole' => $myRole,
            // The adviser is who the department lets see a team's defense
            // scores, besides its CaPSA.
            'seesScores' => $inSections || $myRole === 'adviser',
            // Papers sent to this person on this team, under the hat the page
            // is worn in: format checks for a CaPSA, reviews for an expert.
            'papers' => $inSections ? $formatChecks : $reviews->values(),
            'formatChecks' => $formatChecks,
            'reviews' => $reviews->values(),
            'papersToCheck' => $unchecked->count(),
            'reviewsToDo' => $reviews->where('review_status', '!=', 'Reviewed')->count(),
            'proposals' => $proposals,
            'pendingProposalCount' => $inSections ? $pending->count() : 0,
            // When the newest title still waiting for her came in.
            'lastSubmittedAt' => $inSections ? $pending->max('submitted_at') : null,
            'needsCapsa' => $inSections && ($pending->isNotEmpty()
                || ($submitted && ! $team->is_confirmed)
                || $unchecked->isNotEmpty()),
            'approvedProposalCount' => $approved->count(),
            'titleState' => $titleState,
            'awaitingVerification' => $submitted && ! $team->is_confirmed,
            'canVerify' => $inSections
                && $submitted
                && ! $team->is_confirmed
                && (bool) $team->selected_pre_proposal_id,
        ];
    }
}
