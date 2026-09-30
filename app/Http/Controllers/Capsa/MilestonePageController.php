<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\Checklist;
use App\Models\ChecklistMilestone;
use App\Models\Deliverable;
use App\Support\ChecklistAccess;
use App\Support\ChecklistTracker;
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
     * The ways a team can complete a requirement, as the form offers them.
     * A signed CLF is one kind with two signers, so it is two choices here.
     *
     * @var array<string, array{label: string, help: string, kind: string, role: ?string}>
     */
    public const HOW = [
        'form' => ['label' => 'Signed form', 'help' => 'Teams upload a scan of a signed paper, and you check it.', 'kind' => Deliverable::FORM, 'role' => null],
        'document' => ['label' => 'Upload a file', 'help' => 'Teams upload a file, like a chapter draft.', 'kind' => Deliverable::DOCUMENT, 'role' => null],
        'adviser' => ['label' => 'Signed CLF (adviser)', 'help' => 'The adviser signs a CLF; teams upload the scan.', 'kind' => Deliverable::APPROVAL, 'role' => 'adviser'],
        'consultant' => ['label' => 'Signed CLF (consultant)', 'help' => 'The consultant signs a CLF; teams upload the scan.', 'kind' => Deliverable::APPROVAL, 'role' => 'consultant'],
        'defense' => ['label' => 'Defense', 'help' => 'Done once the defense is held and graded.', 'kind' => Deliverable::DEFENSE, 'role' => null],
        'custom' => ['label' => 'Task', 'help' => 'Teams mark it done, and you check it.', 'kind' => Deliverable::CUSTOM, 'role' => null],
    ];

    public function __construct(private ChecklistAccess $access, private ChecklistTracker $tracker) {}

    /** One milestone and its requirements, with how many teams finished each. */
    public function show(ChecklistMilestone $milestone): View
    {
        $this->access->milestone($milestone);
        $board = $this->boardOf($milestone->checklist);

        return view('capsa.team-management.milestone', [
            'milestone' => $milestone,
            'row' => $board['milestones']->first(fn ($row) => $row->milestone->id === $milestone->id),
            'teams' => $board['teams'],
            'done' => $board['done'],
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
            // A new one goes last, so it would wait for the last one teams see.
            'previous' => $checklist->milestones()->active()->get()->last()?->name,
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
            'department' => collect(MilestoneTemplate::all())->flatMap(fn ($template) => $template['items'])->pluck('phase')->unique()->values(),
            'copies' => Checklist::where('program', $program)
                ->where('capsa_id', '!=', $capsa->expert_id)
                ->with('capsa')
                ->withCount(['milestones' => fn ($query) => $query->whereNull('archived_at')])
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
        $how = $deliverable
            ? ($deliverable->kind === Deliverable::APPROVAL ? ($deliverable->role ?: 'adviser') : $deliverable->kind)
            : 'form';

        return view('capsa.team-management.requirement-form', [
            'milestone' => $milestone,
            'deliverable' => $deliverable,
            'how' => $how,
            'ways' => self::HOW,
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
