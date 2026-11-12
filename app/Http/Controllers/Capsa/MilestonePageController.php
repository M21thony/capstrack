<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\Checklist;
use App\Models\ChecklistMilestone;
use App\Models\Deliverable;
use App\Support\ChecklistAccess;
use App\Support\ChecklistTracker;
use App\Support\DefenseBoard;
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
            $deliverable->closesOnTitleRecommendation(), $deliverable->closesOnArchiveEntry(), $deliverable->closesOnBluebookClearance() => null,
            default => 'Upload file',
        };
    }

    /** The icon a requirement shows, by how it is handed in. Its kind doubles as the colour class. */
    public static function iconOf(Deliverable $deliverable): string
    {
        return match ($deliverable->kind) {
            Deliverable::DOCUMENT => 'upload',
            Deliverable::APPROVAL => 'sign',
            Deliverable::DEFENSE => 'present',
            Deliverable::BLUEBOOK => 'book',
            default => 'clip',
        };
    }

    public function __construct(private ChecklistAccess $access, private ChecklistTracker $tracker) {}

    /** One milestone and its requirements, with how many teams finished each. */
    public function show(ChecklistMilestone $milestone): View
    {
        $this->access->milestone($milestone);
        $board = $this->boardOf($milestone->checklist);

        // Setup only; team progress lives in the tracker.
        return view('capsa.team-management.milestone', [
            'milestone' => $milestone,
            'row' => $board['milestones']->first(fn ($row) => $row->milestone->id === $milestone->id),
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
            'trail' => $this->trail([['New milestone', null]]),
        ]);
    }

    public function edit(ChecklistMilestone $milestone): View
    {
        $this->access->milestone($milestone);

        return view('capsa.team-management.milestone-form', [
            'checklist' => $milestone->checklist,
            'milestone' => $milestone,
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
