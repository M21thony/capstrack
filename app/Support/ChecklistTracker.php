<?php

namespace App\Support;

use App\Models\Checklist;
use App\Models\ChecklistMilestone;
use App\Models\Deliverable;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Support\Collection;

/**
 * What the milestone tracker draws: a section's teams, each with every
 * deliverable on the section's checklist and where it stands.
 */
class ChecklistTracker
{
    public function __construct(
        private SectionScope $sections,
        private TeamChecklist $checklists,
    ) {}

    /**
     * Every section's team count and signed papers to check, plus one section
     * in full - the chosen one, or the first.
     *
     * @param  array<int, string>  $sections  empty means: the sections the teams are in
     * @param  array<int, int>|null  $onlyTeamIds  an adviser sees only the teams she advises
     */
    public function overview(array $sections, ?string $selected = null, bool $canCheck = true, ?array $onlyTeamIds = null): array
    {
        [$bySection, $syRecords] = $this->teamsBySection($onlyTeamIds);

        $sections = array_values($sections ?: $bySection->keys()->reject(fn ($s) => $s === '')->sort()->all());
        $selected = in_array($selected, $sections, true) ? $selected : ($sections[0] ?? null);

        $summary = [];
        $current = null;

        foreach ($sections as $section) {
            $teams = $bySection->get($section, collect())->values();
            $checklist = $this->checklists->forSection($section);
            $progress = $checklist ? MilestoneProgress::forTeams($checklist, $teams->pluck('team_id')) : [];

            $summary[] = [
                'name' => $section,
                'teams' => $teams->count(),
                'to_check' => collect($progress)->sum(fn ($p) => $p->items->filter(fn ($row) => self::toCheck($row))->count()),
            ];

            if ($section === $selected) {
                $current = $this->payload($section, $checklist, $teams, $syRecords, $progress, $canCheck);
            }
        }

        return ['sections' => $summary, 'current' => $current];
    }

    /**
     * The milestones list: every milestone on the checklists the CaPSA's
     * sections follow, each with how many of those teams have finished it.
     *
     * One checklist serves every section of its program, so the sections are
     * gathered under their checklist and its teams counted together.
     *
     * @param  array<int, string>  $sections
     * @return array<int, array{checklist: Checklist, sections: array<int, string>, teams: int, milestones: Collection<int, object>, done: array<int, int>}>
     */
    public function milestoneList(array $sections): array
    {
        [$bySection] = $this->teamsBySection(null);

        $boards = [];

        foreach ($sections as $section) {
            $checklist = $this->checklists->forSection($section);

            if (! $checklist) {
                continue;
            }

            $boards[$checklist->id] ??= ['checklist' => $checklist, 'sections' => [], 'teamIds' => collect()];
            $boards[$checklist->id]['sections'][] = $section;
            $boards[$checklist->id]['teamIds'] = $boards[$checklist->id]['teamIds']
                ->merge($bySection->get($section, collect())->pluck('team_id'));
        }

        return collect($boards)
            ->map(fn (array $board) => $this->board($board['checklist'], $board['teamIds'], $board['sections']))
            ->values()
            ->all();
    }

    /**
     * One checklist's milestones and how far the given teams are with each.
     *
     * @param  Collection<int, int>  $teamIds
     * @param  array<int, string>  $sections
     * @return array{checklist: Checklist, sections: array<int, string>, teams: int, milestones: Collection<int, object>, done: array<int, int>}
     */
    public function board(Checklist $checklist, Collection $teamIds, array $sections = []): array
    {
        $progress = MilestoneProgress::forTeams($checklist, $teamIds);
        $teams = count($progress);
        $number = 0;
        $previous = null;

        // Hidden ones too: they are listed as finished, so they can be shown again.
        $milestones = $checklist->milestones()
            ->with(['deliverables' => fn ($query) => $query->whereNull('archived_at')])
            ->get()
            ->map(function (ChecklistMilestone $milestone) use ($progress, $teams, &$number, &$previous) {
                $summaries = collect($progress)
                    ->map(fn ($team) => $team->milestones->first(fn ($m) => $m->milestone->id === $milestone->id))
                    ->filter();
                $complete = $summaries->filter(fn ($m) => $m->total > 0 && $m->completed === $m->total)->count();
                $hidden = $milestone->archived_at !== null;

                $row = (object) [
                    'milestone' => $milestone,
                    // Numbered as teams see the list, which leaves hidden ones out.
                    'number' => $hidden ? null : ++$number,
                    'previous' => $previous,
                    'capstone2' => $milestone->deliverables->contains(fn ($d) => str_starts_with((string) $d->template_key, 'c2_')),
                    'deliverables' => $milestone->deliverables->count(),
                    'due_on' => $milestone->due_on ?? $milestone->deliverables->pluck('due_date')->filter()->max(),
                    'teams' => $teams,
                    'complete' => $complete,
                    'open' => $teams - $complete,
                    'percent' => $teams === 0 ? 0 : (int) round($complete / $teams * 100),
                    'past_due' => $summaries->where('past_due', true)->count(),
                ];

                $previous = $hidden ? $previous : $milestone->name;

                return $row;
            });

        return [
            'checklist' => $checklist,
            'sections' => $sections,
            'teams' => $teams,
            'milestones' => $milestones,
            // How many teams have finished each requirement, by its id.
            'done' => collect($progress)
                ->flatMap(fn ($team) => $team->items)
                ->filter(fn ($row) => in_array($row->state, MilestoneProgress::COMPLETE_STATES, true))
                ->countBy(fn ($row) => $row->item->id)
                ->all(),
        ];
    }

    /** One section in full. */
    public function section(string $section, bool $canCheck = true, ?array $onlyTeamIds = null): array
    {
        [$bySection, $syRecords] = $this->teamsBySection($onlyTeamIds);

        $teams = $bySection->get($section, collect())->values();
        $checklist = $this->checklists->forSection($section);
        $progress = $checklist ? MilestoneProgress::forTeams($checklist, $teams->pluck('team_id')) : [];

        return $this->payload($section, $checklist, $teams, $syRecords, $progress, $canCheck);
    }

    /** One deliverable for one team, as a tracker row. */
    public static function row(object $row, int $teamId, bool $canCheck = true): array
    {
        $item = $row->item;
        $checkable = $canCheck && in_array($item->kind, Deliverable::CHECKED_BY_CAPSA, true);

        return [
            'id' => $item->id,
            'milestone' => $row->milestone->name,
            'title' => $item->title,
            'kind' => $item->kind,
            'role' => $item->role,
            'by_recommendation' => $item->closesOnTitleRecommendation(),
            'by_archive' => $item->closesOnArchiveEntry(),
            'state' => $row->state,
            'waiting_on' => $row->waiting_on,
            'to_check' => self::toCheck($row),
            'due' => $row->due_on?->toDateString(),
            'closed' => $row->closed_at?->toDateString(),
            'days_late' => $row->days_late,
            'waits_for' => $row->waits_for ?? null,
            'revision' => $row->revision,
            'changes_requested' => $row->changes_requested,
            'note' => $row->note,
            'approved' => $row->approved_at?->toDateString(),
            'check' => $row->check ? ['status' => $row->check->status, 'note' => $row->check->note] : null,
            'file_id' => $checkable ? $row->file_id : null,
            'file_url' => $checkable && $row->file_id ? route('capsa.documents.file', $row->file_id) : null,
            'file_is_image' => $row->file_path !== null
                && in_array(strtolower(pathinfo($row->file_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true),
            'check_url' => $checkable ? route('capsa.deliverables.checks.update', [$item->id, $teamId]) : null,
        ];
    }

    /** A signed paper uploaded and waiting for the CaPSA. */
    public static function toCheck(object $row): bool
    {
        return $row->waiting_on === MilestoneProgress::WAITING_ON_CAPSA
            && in_array($row->item->kind, Deliverable::CHECKED_BY_CAPSA, true);
    }

    /** @return array{0: Collection<string, Collection<int, Team>>, 1: Collection<int, Collection<int, StudentSchoolYear>>} */
    private function teamsBySection(?array $onlyTeamIds): array
    {
        $syRecords = StudentSchoolYear::whereNotNull('team_id')->with('student')->get()->groupBy('team_id');

        $teams = Team::whereIn('team_id', $onlyTeamIds ?? $syRecords->keys()->all())
            ->orderBy('team_name')
            ->get();

        return [
            $teams->groupBy(fn (Team $team) => $this->sections->sectionFor($team, $syRecords->get($team->team_id, collect()))),
            $syRecords,
        ];
    }

    private function payload(string $section, ?Checklist $checklist, Collection $teams, Collection $syRecords, array $progress, bool $canCheck): array
    {
        $experts = TeamExpertAssignment::whereIn('team_id', $teams->pluck('team_id'))
            ->where('status', 'approved')
            ->with(['expert', 'expertRole.role', 'role'])
            ->get()
            ->groupBy('team_id');

        return [
            'section' => $section,
            'checklist' => $checklist ? ['id' => $checklist->id, 'title' => $checklist->title, 'program' => $checklist->program] : null,
            'milestones' => $checklist ? $checklist->milestones()->active()->pluck('name')->all() : [],
            'teams' => $teams->map(function (Team $team) use ($syRecords, $experts, $progress, $canCheck) {
                $engaged = $experts->get($team->team_id, collect());
                $named = fn (string $role) => $engaged->first(fn ($a) => $a->engaged_role_name === $role)?->expert?->name;

                return [
                    'id' => $team->team_id,
                    'name' => $team->team_name,
                    'adviser' => $named('adviser'),
                    'consultant' => $named('consultant'),
                    'members' => $syRecords->get($team->team_id, collect())
                        ->map(fn ($sy) => trim(($sy->student->f_name ?? '').' '.($sy->student->l_name ?? '')))
                        ->filter()
                        ->values(),
                    'rows' => isset($progress[$team->team_id])
                        ? $progress[$team->team_id]->items->map(fn ($row) => self::row($row, $team->team_id, $canCheck))->values()
                        : [],
                    // Milestone name => the one it waits for, for each still locked.
                    'locked' => isset($progress[$team->team_id])
                        ? $progress[$team->team_id]->milestones->where('locked', true)
                            ->mapWithKeys(fn ($m) => [$m->milestone->name => $m->requires])
                        : [],
                ];
            })->values(),
        ];
    }
}
