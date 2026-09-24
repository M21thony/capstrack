<?php

namespace App\Support;

use App\Models\Checklist;
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
