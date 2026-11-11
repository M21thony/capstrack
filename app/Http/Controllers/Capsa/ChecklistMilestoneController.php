<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\Checklist;
use App\Models\ChecklistMilestone;
use App\Services\Notifier;
use App\Support\ChecklistAccess;
use App\Support\TeamChecklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Adding, renaming, archiving and removing a milestone on a CaPSA/TSA's checklist. */
class ChecklistMilestoneController extends Controller
{
    public function __construct(private ChecklistAccess $access, private TeamChecklist $checklists) {}

    /** A new milestone goes at the end; the CaPSA/TSA drags it where it belongs. */
    public function store(Request $request, Checklist $checklist): JsonResponse
    {
        $this->access->checklist($checklist);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'due_on' => ['nullable', 'date', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'requires_previous' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $milestone = $checklist->milestones()->create([
            'name' => trim($data['name']),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'due_on' => $data['due_on'] ?? null,
            'requires_previous' => $data['requires_previous'] ?? false,
            'position' => (int) $checklist->milestones()->withTrashed()->max('position') + 1,
        ]);

        Notifier::toStudents(
            $this->checklists->studentsFollowing($checklist),
            'checklist_item_added',
            'New in your checklist',
            '"'.$milestone->name.'" was added to your checklist.',
            route('student.progress'),
        );

        return response()->json($this->present($milestone), 201);
    }

    /**
     * Rename it, describe it, give it a due date, close or reopen it, or
     * archive it - hidden from teams with its deliverables - or bring it back.
     */
    public function update(Request $request, ChecklistMilestone $milestone): JsonResponse
    {
        $this->access->milestone($milestone);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'archived' => ['sometimes', 'boolean'],
            'requires_previous' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'due_on' => ['sometimes', 'nullable', 'date', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'closed' => ['sometimes', 'boolean'],
            'opened' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['name'])) {
            $milestone->name = trim($data['name']);
        }

        if (array_key_exists('archived', $data)) {
            $milestone->archived_at = $data['archived'] ? now() : null;
        }

        if (array_key_exists('requires_previous', $data)) {
            $milestone->requires_previous = $data['requires_previous'];
        }

        if (array_key_exists('description', $data)) {
            $milestone->description = trim((string) $data['description']) ?: null;
        }

        if (array_key_exists('due_on', $data)) {
            $milestone->due_on = $data['due_on'];
        }

        // Closing twice keeps the first date: that is when it was closed.
        if (array_key_exists('closed', $data)) {
            $milestone->closed_at = $data['closed'] ? ($milestone->closed_at ?? now()) : null;
            // A finished one was open, so Reopen leaves it open.
            $milestone->opened_at ??= $milestone->closed_at;
        }

        // Opened by the CaPSA/TSA for the whole section; nothing opens it on its own.
        if (array_key_exists('opened', $data)) {
            $milestone->opened_at = $data['opened'] ? ($milestone->opened_at ?? now()) : null;
        }

        $milestone->save();

        return response()->json($this->present($milestone));
    }

    /** Removed at once, and purged for good after a few days; PUT /removals undoes it. */
    public function destroy(ChecklistMilestone $milestone): JsonResponse
    {
        $this->access->milestone($milestone);
        $milestone->delete();

        return response()->json(['removed' => $milestone->id]);
    }

    private function present(ChecklistMilestone $milestone): array
    {
        return [
            'id' => $milestone->id,
            'name' => $milestone->name,
            'archived' => (bool) $milestone->archived_at,
            'requires_previous' => (bool) $milestone->requires_previous,
            'description' => $milestone->description,
            'due_on' => $milestone->due_on?->toDateString(),
            'closed' => $milestone->isClosed(),
            'closed_at' => $milestone->closed_at?->toDateString(),
            'opened' => $milestone->isOpened(),
        ];
    }
}
