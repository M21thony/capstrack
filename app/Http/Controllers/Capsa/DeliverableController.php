<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\ChecklistMilestone;
use App\Models\Deliverable;
use App\Support\ChecklistAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Deliverables on a CaPSA's checklist: added many at once, edited in place,
 * changed together from the bulk bar, archived or removed.
 */
class DeliverableController extends Controller
{
    public function __construct(private ChecklistAccess $access) {}

    /** One deliverable per line pasted into "Add deliverables", all with the same settings. */
    public function store(Request $request, ChecklistMilestone $milestone): JsonResponse
    {
        $this->access->milestone($milestone);

        $data = $request->validate([
            'titles' => ['required', 'array', 'min:1', 'max:100'],
            'titles.*' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in(Deliverable::KINDS)],
            'role' => ['nullable', 'required_if:kind,'.Deliverable::APPROVAL, Rule::in(['adviser', 'consultant'])],
            'due_date' => ['nullable', 'date'],
        ]);

        $next = (int) $milestone->deliverables()->withTrashed()->max('position') + 1;

        $created = collect($data['titles'])->values()->map(fn (string $title, int $i) => $milestone->deliverables()->create([
            'title' => trim($title),
            'kind' => $data['kind'],
            'role' => $data['kind'] === Deliverable::APPROVAL ? $data['role'] : null,
            'due_date' => $data['due_date'] ?? null,
            'position' => $next + $i,
        ]));

        return response()->json(['deliverables' => $created->map(fn ($d) => ChecklistController::deliverable($d))->values()], 201);
    }

    /** One cell edited in place. */
    public function update(Request $request, Deliverable $deliverable): JsonResponse
    {
        $this->access->deliverable($deliverable);

        $deliverable->update($this->changes($request->validate($this->rules())));

        return response()->json(ChecklistController::deliverable($deliverable->fresh()));
    }

    /**
     * The bulk bar: one change to every ticked deliverable - move them, set
     * their type or due date, shift their dates, archive or restore them.
     *
     * Undo sends `items` instead: each deliverable with its own values from
     * before, put back exactly.
     */
    public function updateMany(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required_without:items', 'array'],
            'ids.*' => ['integer'],
            'change' => ['required_with:ids', 'array'],
            'items' => ['required_without:ids', 'array'],
            'items.*.id' => ['required', 'integer'],
        ]);

        if (isset($data['items'])) {
            return $this->restoreValues($request->input('items'));
        }

        $change = validator($data['change'], $this->rules() + [
            'milestone_id' => ['sometimes', 'integer'],
            'shift_days' => ['sometimes', 'integer', 'between:-365,365'],
        ])->validate();

        $rows = $this->access->deliverables($data['ids'])->sortBy('position')->values();

        if (isset($change['milestone_id'])) {
            $target = ChecklistMilestone::find($change['milestone_id']);
            $this->access->milestone($target);

            if (! $rows->every(fn (Deliverable $d) => $d->milestone->checklist_id === $target->checklist_id)) {
                return response()->json(['message' => 'Deliverables can only move within their own checklist.'], 422);
            }

            $next = (int) $target->deliverables()->withTrashed()->max('position') + 1;

            foreach ($rows as $i => $deliverable) {
                $deliverable->update(['checklist_milestone_id' => $target->id, 'position' => $next + $i]);
            }
        }

        if (isset($change['shift_days'])) {
            foreach ($rows->filter(fn (Deliverable $d) => $d->due_date) as $deliverable) {
                $deliverable->update(['due_date' => $deliverable->due_date->copy()->addDays($change['shift_days'])]);
            }
        }

        if ($plain = $this->changes($change)) {
            $rows->each->update($plain);
        }

        return response()->json(['deliverables' => $rows->map(fn ($d) => ChecklistController::deliverable($d->fresh()))->values()]);
    }

    /** Removed at once, and purged for good after a few days; PUT /removals undoes it. */
    public function destroyMany(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']]);

        $rows = $this->access->deliverables($data['ids']);
        $rows->each->delete();

        return response()->json(['removed' => $rows->pluck('id')->values()]);
    }

    private function restoreValues(array $items): JsonResponse
    {
        $rows = $this->access->deliverables(collect($items)->pluck('id')->map(fn ($id) => (int) $id)->all())->keyBy('id');

        foreach ($items as $item) {
            $valid = validator($item, $this->rules() + [
                'checklist_milestone_id' => ['sometimes', 'integer'],
                'position' => ['sometimes', 'integer', 'min:0'],
            ])->validate();

            $deliverable = $rows[(int) $item['id']];

            if (isset($valid['checklist_milestone_id'])) {
                $target = ChecklistMilestone::withTrashed()->find($valid['checklist_milestone_id']);
                $this->access->milestone($target);
                abort_unless($target->checklist_id === $deliverable->milestone->checklist_id, 422);
            }

            $deliverable->update($this->changes($valid) + array_intersect_key($valid, array_flip(['checklist_milestone_id', 'position'])));
        }

        return response()->json(['updated' => $rows->count()]);
    }

    private function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'kind' => ['sometimes', 'required', Rule::in(Deliverable::KINDS)],
            'role' => ['sometimes', 'nullable', Rule::in(['adviser', 'consultant'])],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'archived' => ['sometimes', 'boolean'],
        ];
    }

    /** Validated input as columns. A CLF always says who signs it; nothing else does. */
    private function changes(array $data): array
    {
        $out = array_intersect_key($data, array_flip(['title', 'kind', 'role', 'due_date']));

        if (isset($out['title'])) {
            $out['title'] = trim($out['title']);
        }

        if (isset($out['kind'])) {
            $out['role'] = $out['kind'] === Deliverable::APPROVAL ? ($data['role'] ?? 'adviser') : null;
        }

        if (array_key_exists('archived', $data)) {
            $out['archived_at'] = $data['archived'] ? now() : null;
        }

        return $out;
    }
}
