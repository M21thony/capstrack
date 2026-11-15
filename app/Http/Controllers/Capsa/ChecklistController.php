<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\Checklist;
use App\Models\ChecklistMilestone;
use App\Models\Deliverable;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\ChecklistAccess;
use App\Support\MilestoneTemplate;
use App\Support\SectionScope;
use App\Support\TeamChecklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A CaPSA/TSA's checklists, as the Edit milestones window reads and reorders them.
 * One per program; every section of that program she handles follows it.
 */
class ChecklistController extends Controller
{
    public function __construct(
        private ChecklistAccess $access,
        private SectionScope $sections,
    ) {}

    /** Her checklists, and the programs she has none for yet. */
    public function index(): JsonResponse
    {
        $capsa = $this->access->capsa();
        $mine = Checklist::where('capsa_id', $capsa->expert_id)->orderBy('program')->get();

        return response()->json([
            // An earlier batch's list is shown only while a section still follows it.
            'checklists' => $mine->map(fn (Checklist $checklist) => $this->summary($checklist) + ['year' => $checklist->school_year])
                ->reject(fn (array $c) => $c['used_by'] === 'No sections' && $c['year'] !== \App\Models\Term::currentSchoolYear())
                ->values(),
            'missing' => collect($capsa->handledPrograms())
                ->diff($mine->pluck('program'))
                ->values()
                ->map(fn (string $program) => ['program' => $program]),
        ]);
    }

    /** Start one from the department checklist, or start empty. */
    public function store(Request $request): JsonResponse
    {
        $capsa = $this->access->capsa();

        $data = $request->validate([
            'program' => ['required', Rule::in($capsa->handledPrograms())],
            'empty' => ['sometimes', 'boolean'],
        ]);

        if (Checklist::where('capsa_id', $capsa->expert_id)->where('program', $data['program'])->exists()) {
            return response()->json(['message' => 'You already have a '.$data['program'].' checklist.'], 422);
        }

        if ($request->boolean('empty')) {
            $checklist = Checklist::create(['capsa_id' => $capsa->expert_id, 'program' => $data['program'], 'title' => 'Capstone 1 Checklist']);
        } else {
            $checklist = MilestoneTemplate::createChecklist($capsa->expert_id, $data['program']);
        }

        return response()->json($this->present($checklist), 201);
    }

    public function show(Checklist $checklist): JsonResponse
    {
        $this->access->checklist($checklist);

        return response()->json($this->present($checklist));
    }

    /**
     * Any drag or arrow-key move: the whole order of milestones and the
     * deliverables in each, sent at once. Refused if it leaves anything out,
     * so a stale window cannot quietly drop a line.
     */
    public function updateOrder(Request $request, Checklist $checklist): JsonResponse
    {
        $this->access->checklist($checklist);

        $data = $request->validate([
            'milestones' => ['required', 'array'],
            'milestones.*.id' => ['required', 'integer'],
            'milestones.*.deliverables' => ['present', 'array'],
            'milestones.*.deliverables.*' => ['integer'],
        ]);

        $current = $checklist->milestones()->with('deliverables')->get();
        $given = collect($data['milestones']);

        $sorted = fn ($ids) => collect($ids)->map(fn ($id) => (int) $id)->sort()->values()->all();

        if ($sorted($given->pluck('id')) !== $sorted($current->pluck('id'))
            || $sorted($given->pluck('deliverables')->flatten()) !== $sorted($current->flatMap->deliverables->pluck('id'))) {
            return response()->json(['message' => 'The checklist changed since you opened it. Reload and try again.'], 422);
        }

        foreach ($given->values() as $position => $row) {
            ChecklistMilestone::whereKey($row['id'])->update(['position' => $position]);

            foreach (array_values($row['deliverables']) as $at => $id) {
                Deliverable::whereKey($id)->update(['checklist_milestone_id' => $row['id'], 'position' => $at]);
            }
        }

        return response()->json($this->present($checklist->fresh()));
    }

    /** A deliverable as the editor edits it. */
    public static function deliverable(Deliverable $deliverable): array
    {
        return [
            'id' => $deliverable->id,
            'title' => $deliverable->title,
            'kind' => $deliverable->kind,
            'role' => $deliverable->role,
            'file_type' => $deliverable->file_type,
            'due' => $deliverable->due_date?->toDateString(),
            'archived' => (bool) $deliverable->archived_at,
        ];
    }

    private function present(Checklist $checklist): array
    {
        $checklist->load('milestones.deliverables');

        return $this->summary($checklist) + [
            'milestones' => $checklist->milestones->map(fn (ChecklistMilestone $milestone) => [
                'id' => $milestone->id,
                'name' => $milestone->name,
                'archived' => (bool) $milestone->archived_at,
                'description' => $milestone->description,
                'due_on' => $milestone->due_on?->toDateString(),
                'closed' => $milestone->isClosed(),
                'closed_at' => $milestone->closed_at?->toDateString(),
                'deliverables' => $milestone->deliverables->map(fn (Deliverable $d) => self::deliverable($d))->values(),
            ])->values(),
        ];
    }

    private function summary(Checklist $checklist): array
    {
        return [
            'id' => $checklist->id,
            'title' => $checklist->title,
            'program' => $checklist->program,
            'used_by' => $this->usedBy($checklist),
        ];
    }

    /** "12 teams in BSIT-3A, BSIT-3B" - who a change reaches. */
    private function usedBy(Checklist $checklist): string
    {
        $sections = collect($checklist->capsa?->sections ?? [])
            ->filter(fn ($section) => app(TeamChecklist::class)->forSection($section)?->id === $checklist->id)
            ->values();

        if ($sections->isEmpty()) {
            return 'No sections';
        }

        $syRecords = StudentSchoolYear::whereNotNull('team_id')->with('student')->get()->groupBy('team_id');
        $count = Team::whereIn('team_id', $syRecords->keys()->all())->get()
            ->filter(fn (Team $team) => $sections->contains($this->sections->sectionFor($team, $syRecords->get($team->team_id, collect()))))
            ->count();

        return $count.' '.Str::plural('team', $count).' in '.$sections->implode(', ');
    }
}
