<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\ChecklistMilestone;
use App\Models\Deliverable;
use App\Services\Notifier;
use App\Support\ChecklistAccess;
use App\Support\DefenseBoard;
use App\Support\SubmissionRecipients;
use App\Support\TeamChecklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Deliverables on a CaPSA/TSA's checklist: added many at once, edited in place,
 * changed together from the bulk bar, archived or removed.
 */
class DeliverableController extends Controller
{
    /** A real due date. A half-typed year like 0200 is a date too, so the range is what refuses it. */
    private const DATE_RULE = ['date', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'];

    private const DATE_MESSAGES = [
        'due_date.date' => 'Enter a real due date.',
        'due_date.after_or_equal' => 'Enter a due date from the year 2000 on.',
        'due_date.before_or_equal' => 'Enter a due date before the year 2101.',
    ];

    public function __construct(private ChecklistAccess $access, private TeamChecklist $checklists) {}

    /** One deliverable per line pasted into "Add deliverables", all with the same settings. */
    public function store(Request $request, ChecklistMilestone $milestone): JsonResponse
    {
        $this->access->milestone($milestone);

        $data = $request->validate([
            'titles' => ['required', 'array', 'min:1', 'max:100'],
            'titles.*' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in(Deliverable::KINDS)],
            'role' => ['nullable', 'required_if:kind,'.Deliverable::APPROVAL, Rule::in(['adviser', 'consultant'])],
            'defense_type' => ['nullable', Rule::in(DefenseBoard::STAGES)],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', ...self::DATE_RULE],
            'recipients' => ['nullable', 'array'],
            'recipients.*' => [Rule::in(SubmissionRecipients::ROLES)],
        ], self::DATE_MESSAGES);

        $next = (int) $milestone->deliverables()->withTrashed()->max('position') + 1;

        $created = collect($data['titles'])->values()->map(fn (string $title, int $i) => $milestone->deliverables()->create([
            'title' => trim($title),
            'kind' => $data['kind'],
            'role' => $data['kind'] === Deliverable::APPROVAL ? $data['role'] : null,
            'defense_type' => $data['kind'] === Deliverable::DEFENSE ? ($data['defense_type'] ?? null) : null,
            'instructions' => $data['instructions'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'recipients' => ($data['recipients'] ?? null) ?: null,
            'position' => $next + $i,
        ]));

        $this->notifyAdded($milestone, $created->pluck('title'));

        return response()->json(['deliverables' => $created->map(fn ($d) => ChecklistController::deliverable($d))->values()], 201);
    }

    /**
     * Every team on this checklist hears about a new requirement the moment
     * it lands on their tracker, not the next time they happen to open it -
     * the bell, and the desktop popup for whoever has allowed it (see
     * Notifier).
     */
    private function notifyAdded(ChecklistMilestone $milestone, \Illuminate\Support\Collection $titles): void
    {
        $checklist = $milestone->checklist;

        if (! $checklist || $titles->isEmpty()) {
            return;
        }

        $message = $titles->count() === 1
            ? '"'.$titles->first().'" was added to '.$milestone->name.'.'
            : $titles->count().' new items were added to '.$milestone->name.'.';

        Notifier::toStudents(
            $this->checklists->studentsFollowing($checklist),
            'checklist_item_added',
            'New in your checklist',
            $message,
            route('student.progress'),
        );
    }

    /** One cell edited in place. */
    public function update(Request $request, Deliverable $deliverable): JsonResponse
    {
        $this->access->deliverable($deliverable);

        $deliverable->update($this->changes($request->validate($this->rules(), self::DATE_MESSAGES)));

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
        ], self::DATE_MESSAGES)->validate();

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
            ], self::DATE_MESSAGES)->validate();

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
            'defense_type' => ['sometimes', 'nullable', Rule::in(DefenseBoard::STAGES)],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'due_date' => ['sometimes', 'nullable', ...self::DATE_RULE],
            'archived' => ['sometimes', 'boolean'],
            'recipients' => ['sometimes', 'nullable', 'array'],
            'recipients.*' => [Rule::in(SubmissionRecipients::ROLES)],
            'closed' => ['sometimes', 'boolean'],
        ];
    }

    /** Validated input as columns. A CLF always says who signs it; nothing else does. */
    private function changes(array $data): array
    {
        $out = array_intersect_key($data, array_flip(['title', 'instructions', 'kind', 'role', 'defense_type', 'due_date']));

        if (isset($out['title'])) {
            $out['title'] = trim($out['title']);
        }

        if (isset($out['kind'])) {
            $out['role'] = $out['kind'] === Deliverable::APPROVAL ? ($data['role'] ?? 'adviser') : null;
        }

        if (array_key_exists('archived', $data)) {
            $out['archived_at'] = $data['archived'] ? now() : null;
        }

        if (array_key_exists('recipients', $data)) {
            $out['recipients'] = $data['recipients'] ?: null;
        }

        if (array_key_exists('closed', $data)) {
            $out['closed_at'] = $data['closed'] ? now() : null;
        }

        return $out;
    }
}
