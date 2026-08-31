<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\Milestone;
use App\Models\MilestoneItem;
use App\Models\Team;
use App\Support\MilestoneTemplate;
use App\Support\SectionScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Creating and editing milestones, properly.
 *
 * A milestone used to appear only as a side effect of posting an
 * announcement, came out with team_id null - so every reader that looks them
 * up by team could not see it - and had no way to be edited or deleted
 * afterwards. Everything here is deliberate: a milestone is created for a
 * team, on purpose, and can be changed or removed.
 *
 * The section-wide create is the one that matters in practice. A CaPSA runs a
 * section and every team in it works from the same checklist, so making
 * nineteen items for one team at a time would be the wrong unit of work.
 */
class MilestoneController extends Controller
{
    public function __construct(private SectionScope $sections) {}

    /**
     * Create a milestone, for one team or for every team in a section.
     *
     * Applying a template is the normal path; a bare milestone with no items
     * is allowed so a CaPSA can build Capstone 2 by hand, which is exactly
     * what they will have to do until that checklist settles.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['required', Rule::in(['team', 'section'])],
            'team_id' => ['required_if:scope,team', 'nullable', 'integer', 'exists:team,team_id'],
            'section' => ['required_if:scope,section', 'nullable', 'string'],
            'title' => ['required_without:template', 'nullable', 'string', 'max:255'],
            'template' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        if (($validated['template'] ?? null) && ! MilestoneTemplate::exists($validated['template'])) {
            return response()->json(['message' => 'That checklist does not exist.'], 422);
        }

        $teams = $this->targetTeams($validated);

        if ($teams->isEmpty()) {
            return response()->json(['message' => 'No teams of yours matched that.'], 422);
        }

        $title = $validated['title']
            ?? MilestoneTemplate::nameFor($validated['template'])
            ?? 'Milestone';

        $created = [];

        foreach ($teams as $team) {
            // One milestone of a given name per team. Running the same
            // section-wide create twice should not leave every team with two
            // copies of the same checklist.
            $milestone = Milestone::firstOrNew([
                'team_id' => $team->team_id,
                'title' => $title,
            ]);

            $milestone->fill([
                'description' => $validated['description'] ?? $milestone->description,
                'due_date' => $validated['due_date'] ?? $milestone->due_date,
                'status' => $milestone->status ?? 'todo',
            ])->save();

            if ($validated['template'] ?? null) {
                MilestoneTemplate::applyTo($milestone, $validated['template']);
            }

            $created[] = $this->present($milestone->fresh());
        }

        return response()->json(['success' => true, 'data' => $created]);
    }

    public function update(Request $request, Milestone $milestone): JsonResponse
    {
        $this->authorizeMilestone($milestone);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
        ]);

        $milestone->update($validated);

        return response()->json(['success' => true, 'data' => $this->present($milestone->fresh())]);
    }

    public function destroy(Milestone $milestone): JsonResponse
    {
        $this->authorizeMilestone($milestone);
        $milestone->delete();

        return response()->json(['success' => true]);
    }

    /** Apply (or re-apply) a checklist over an existing milestone. */
    public function applyTemplate(Request $request, Milestone $milestone): JsonResponse
    {
        $this->authorizeMilestone($milestone);

        $validated = $request->validate(['template' => ['required', 'string']]);

        if (! MilestoneTemplate::exists($validated['template'])) {
            return response()->json(['message' => 'That checklist does not exist.'], 422);
        }

        // Safe to re-run: items already ticked keep their date, and anything
        // the CaPSA added themselves is untouched.
        MilestoneTemplate::applyTo($milestone, $validated['template']);

        return response()->json(['success' => true, 'data' => $this->present($milestone->fresh())]);
    }

    public function storeItem(Request $request, Milestone $milestone): JsonResponse
    {
        $this->authorizeMilestone($milestone);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['nullable', Rule::in(MilestoneItem::KINDS)],
            'due_date' => ['nullable', 'date'],
            'chapter' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', 'string', 'max:32'],
            'defense_type' => ['nullable', 'string', 'max:64'],
        ]);

        $milestone->items()->create($validated + [
            'kind' => $validated['kind'] ?? MilestoneItem::CUSTOM,
            'position' => (int) $milestone->items()->max('position') + 1,
        ]);

        return response()->json(['success' => true, 'data' => $this->present($milestone->fresh())]);
    }

    public function updateItem(Request $request, MilestoneItem $item): JsonResponse
    {
        $this->authorizeMilestone($item->milestone);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'chapter' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', 'string', 'max:32'],
            'defense_type' => ['nullable', 'string', 'max:64'],
        ]);

        $item->update($validated);

        return response()->json(['success' => true, 'data' => $this->present($item->milestone->fresh())]);
    }

    public function destroyItem(MilestoneItem $item): JsonResponse
    {
        $milestone = $item->milestone;
        $this->authorizeMilestone($milestone);
        $item->delete();

        return response()->json(['success' => true, 'data' => $this->present($milestone->fresh())]);
    }

    /**
     * Tick or untick a deliverable, carrying the date it was accomplished -
     * the Date Accomplished column off the paper checklist.
     *
     * Only the hand-ticked kinds. A chapter draft, a CLF or a defense is
     * answered by the upload, the verdict or the panel's outcome, and letting
     * a CaPSA tick those by hand would put a second, disagreeing answer next
     * to the real one.
     */
    public function toggleItem(Request $request, MilestoneItem $item): JsonResponse
    {
        $this->authorizeMilestone($item->milestone);

        if (! $item->isTickedByHand()) {
            return response()->json([
                'message' => 'This one closes itself when the document, approval or defense it points at happens.',
            ], 422);
        }

        $validated = $request->validate([
            'done' => ['required', 'boolean'],
            'completed_at' => ['nullable', 'date'],
        ]);

        $item->update([
            'completed_at' => $validated['done']
                ? ($validated['completed_at'] ?? now())
                : null,
        ]);

        return response()->json(['success' => true, 'data' => $this->present($item->milestone->fresh())]);
    }

    public function reorderItems(Request $request, Milestone $milestone): JsonResponse
    {
        $this->authorizeMilestone($milestone);

        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
        ]);

        $owned = $milestone->items()->pluck('id')->all();

        foreach ($validated['item_ids'] as $position => $id) {
            if (! in_array($id, $owned, true)) {
                continue;
            }

            MilestoneItem::whereKey($id)->update(['position' => $position]);
        }

        return response()->json(['success' => true, 'data' => $this->present($milestone->fresh())]);
    }

    /** The teams this create applies to, always inside the CaPSA's sections. */
    private function targetTeams(array $validated): \Illuminate\Support\Collection
    {
        /** @var CapstoneExpert $capsa */
        $capsa = auth('expert')->user();
        $capsaSections = $capsa->sections ?? [];

        if ($validated['scope'] === 'team') {
            $team = Team::find($validated['team_id']);

            return $team && $this->canSee($team, $capsaSections) ? collect([$team]) : collect();
        }

        if (! $this->sections->covers($capsaSections, $validated['section'])) {
            return collect();
        }

        return Team::where('is_confirmed', true)
            ->get()
            ->filter(fn (Team $team) => $this->sections->sectionFor($team) === $validated['section'])
            ->values();
    }

    private function canSee(Team $team, array $capsaSections): bool
    {
        return $this->sections->covers($capsaSections, $this->sections->sectionFor($team));
    }

    /** A CaPSA may only touch milestones belonging to teams in her sections. */
    private function authorizeMilestone(?Milestone $milestone): void
    {
        abort_if($milestone === null || $milestone->team === null, 404);

        /** @var CapstoneExpert $capsa */
        $capsa = auth('expert')->user();

        abort_unless($this->canSee($milestone->team, $capsa->sections ?? []), 403);
    }

    /** A milestone with its items and their worked-out states. */
    private function present(Milestone $milestone): array
    {
        $progress = $milestone->progress();

        return [
            'id' => $milestone->id,
            'team_id' => $milestone->team_id,
            'title' => $milestone->title,
            'description' => $milestone->description,
            'due_date' => $milestone->due_date?->toDateString(),
            'total' => $progress->total,
            'completed' => $progress->completed,
            'handed_in' => $progress->handed_in,
            'late' => $progress->late,
            'overdue' => $progress->overdue,
            'percent' => $progress->percent,
            'items' => $progress->items->map(fn ($row) => [
                'id' => $row->item->id,
                'title' => $row->item->title,
                'kind' => $row->item->kind,
                'ticked_by_hand' => $row->item->isTickedByHand(),
                'template_key' => $row->item->template_key,
                'state' => $row->state,
                'due_on' => $row->due_on?->toDateString(),
                'closed_at' => $row->closed_at?->toDateString(),
                'days_late' => $row->days_late,
                'waiting_on' => $row->waiting_on,
            ])->values()->all(),
        ];
    }
}
