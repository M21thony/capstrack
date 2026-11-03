<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\Checklist;
use App\Models\ChecklistMilestone;
use App\Models\Deliverable;
use App\Models\Team;
use App\Support\ChecklistAccess;
use App\Support\ChecklistTracker;
use App\Support\DefenseBoard;
use App\Support\MilestoneProgress;
use App\Support\MilestoneTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The milestone pages under Manage milestones: one milestone with its
 * requirements, and the forms that add or change either.
 *
 * Each is an ordinary page, so Back, reload and a new tab all work. Saving
 * goes through the same JSON endpoints the old editor window used.
 */
class MilestonePageController extends Controller
{
    /**
     * The type a requirement shows as: its defense, or Upload file for every
     * other kind. Null for the lines nothing is uploaded for.
     */
    public static function typeOf(Deliverable $deliverable): ?string
    {
        return match (true) {
            $deliverable->kind === Deliverable::DEFENSE => $deliverable->defense_type ?: 'Defense',
            $deliverable->closesOnTitleRecommendation(), $deliverable->closesOnArchiveEntry() => null,
            default => 'Upload file',
        };
    }

    public function __construct(private ChecklistAccess $access, private ChecklistTracker $tracker) {}

    /** One milestone and its requirements, with how many teams finished each. */
    public function show(ChecklistMilestone $milestone): View
    {
        $this->access->milestone($milestone);
        $board = $this->boardOf($milestone->checklist);
        $rows = collect($board['progress'] ?? [])
            ->flatMap(fn ($team, $teamId) => $team->items->map(fn ($row) => [$teamId, $row]))
            ->filter(fn ($pair) => (int) $pair[1]->item->checklist_milestone_id === $milestone->id);
        $late = $rows->filter(fn ($pair) => $pair[1]->state === MilestoneProgress::OVERDUE);
        $names = Team::whereIn('team_id', $late->pluck(0)->unique())->pluck('team_name', 'team_id');

        return view('capsa.team-management.milestone', [
            'milestone' => $milestone,
            'row' => $board['milestones']->first(fn ($row) => $row->milestone->id === $milestone->id),
            'teams' => $board['teams'],
            'done' => $board['done'],
            // Hand-ins waiting on her check, by requirement id.
            'toCheck' => $rows->filter(fn ($pair) => ChecklistTracker::toCheck($pair[1]))->countBy(fn ($pair) => $pair[1]->item->id)->all(),
            'late' => $late->map(fn ($pair) => [$names[$pair[0]] ?? 'A team', $pair[1]->item->title])
                ->sortBy(0)->values(),
            // Hidden ones too, labelled, so they can be shown again.
            'requirements' => $milestone->deliverables()->get(),
            'order' => self::orderOf($milestone->checklist),
            'trail' => $this->trail([[$milestone->name, null]]),
        ]);
    }

    public function create(Checklist $checklist): View
    {
        $this->access->checklist($checklist);

        return view('capsa.team-management.milestone-form', [
            'checklist' => $checklist,
            'milestone' => null,
            // A new one goes last, so it would wait for the last defense teams see.
            'previous' => $checklist->milestones()->active()
                ->whereHas('deliverables', fn ($query) => $query->where('kind', Deliverable::DEFENSE))
                ->get()->last()?->name,
            'place' => ($count = $checklist->milestones()->active()->count() + 1).' of '.$count,
            'trail' => $this->trail([['New milestone', null]]),
        ]);
    }

    public function edit(ChecklistMilestone $milestone): View
    {
        $this->access->milestone($milestone);
        $row = $this->boardOf($milestone->checklist)['milestones']->first(fn ($row) => $row->milestone->id === $milestone->id);

        return view('capsa.team-management.milestone-form', [
            'checklist' => $milestone->checklist,
            'milestone' => $milestone,
            'previous' => $row?->previous,
            // Numbered as teams see the list; a hidden one has no place in it.
            'place' => $row?->number ? $row->number.' of '.$milestone->checklist->milestones()->active()->count() : 'Hidden from teams',
            'trail' => $this->trail([
                [$milestone->name, route('capsa.team_management.milestones.show', $milestone)],
                ['Edit', null],
            ]),
        ]);
    }

    public function createRequirement(ChecklistMilestone $milestone): View
    {
        $this->access->milestone($milestone);

        return $this->requirementForm($milestone, null);
    }

    public function editRequirement(Deliverable $deliverable): View
    {
        $this->access->deliverable($deliverable);

        return $this->requirementForm($deliverable->milestone, $deliverable);
    }

    /** A program she handles with no checklist of her own yet. */
    public function setup(string $program): View|RedirectResponse
    {
        $capsa = $this->access->capsa();
        $program = strtoupper($program);

        abort_unless(in_array($program, $capsa->handledPrograms(), true), 404);

        if (Checklist::where('capsa_id', $capsa->expert_id)->where('program', $program)->exists()) {
            return redirect()->route('capsa.team_management', ['tab' => 'milestone_list']);
        }

        return view('capsa.team-management.milestone-setup', [
            'program' => $program,
            'sections' => collect($capsa->sections ?? [])->filter(fn ($section) => str_starts_with(strtoupper((string) $section), $program.'-'))->values(),
            // The department's milestones by template, each flagged when it holds a defense.
            'department' => collect(MilestoneTemplate::all())->map(fn ($template) => (object) [
                'name' => $template['name'],
                'milestones' => collect($template['items'])->groupBy('phase')
                    ->map(fn ($lines, $phase) => (object) ['name' => $phase, 'defense' => $lines->contains('kind', Deliverable::DEFENSE)])
                    ->values(),
            ])->values(),
            'copies' => Checklist::where('program', $program)
                ->where('capsa_id', '!=', $capsa->expert_id)
                ->with(['capsa', 'milestones' => fn ($query) => $query->whereNull('archived_at')->with('deliverables')])
                ->get(),
            'trail' => $this->trail([['Set up '.$program, null]]),
        ]);
    }

    /**
     * The whole order of a checklist, as PUT checklists/{id}/order wants it:
     * every milestone and every requirement, hidden ones included.
     *
     * @return array<int, array{id: int, deliverables: array<int, int>}>
     */
    public static function orderOf(Checklist $checklist): array
    {
        return $checklist->milestones()->with('deliverables')->get()
            ->map(fn (ChecklistMilestone $milestone) => [
                'id' => $milestone->id,
                'deliverables' => $milestone->deliverables->pluck('id')->all(),
            ])
            ->all();
    }

    private function requirementForm(ChecklistMilestone $milestone, ?Deliverable $deliverable): View
    {
        return view('capsa.team-management.requirement-form', [
            'milestone' => $milestone,
            'deliverable' => $deliverable,
            'defense' => $deliverable?->kind === Deliverable::DEFENSE,
            'stages' => DefenseBoard::STAGES,
            'trail' => $this->trail([
                [$milestone->name, route('capsa.team_management.milestones.show', $milestone)],
                [$deliverable ? 'Edit requirement' : 'Add requirement', null],
            ]),
        ]);
    }

    /** Her sections' teams on this checklist; none when no section of hers follows it. */
    private function boardOf(Checklist $checklist): array
    {
        return collect($this->tracker->milestoneList($this->access->capsa()->sections ?? []))
            ->first(fn (array $board) => $board['checklist']->id === $checklist->id)
            ?? $this->tracker->board($checklist, collect());
    }

    /** @param  array<int, array{0: string, 1: ?string}>  $tail */
    private function trail(array $tail): array
    {
        return [
            ['Team Management', route('capsa.team_management')],
            ['Milestones', route('capsa.team_management', ['tab' => 'milestones'])],
            ['Manage milestones', route('capsa.team_management', ['tab' => 'milestone_list'])],
            ...$tail,
        ];
    }
}
