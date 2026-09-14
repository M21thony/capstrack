<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\Checklist;
use App\Models\ChecklistMilestone;
use App\Support\ChecklistAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Adding, renaming, archiving and removing a milestone on a CaPSA's checklist. */
class ChecklistMilestoneController extends Controller
{
    public function __construct(private ChecklistAccess $access) {}

    /** A new milestone goes at the end; the CaPSA drags it where it belongs. */
    public function store(Request $request, Checklist $checklist): JsonResponse
    {
        $this->access->checklist($checklist);

        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $milestone = $checklist->milestones()->create([
            'name' => trim($data['name']),
            'position' => (int) $checklist->milestones()->withTrashed()->max('position') + 1,
        ]);

        return response()->json($this->present($milestone), 201);
    }

    /** Rename it, or archive it - hidden from teams with its deliverables - or bring it back. */
    public function update(Request $request, ChecklistMilestone $milestone): JsonResponse
    {
        $this->access->milestone($milestone);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'archived' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['name'])) {
            $milestone->name = trim($data['name']);
        }

        if (array_key_exists('archived', $data)) {
            $milestone->archived_at = $data['archived'] ? now() : null;
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
        return ['id' => $milestone->id, 'name' => $milestone->name, 'archived' => (bool) $milestone->archived_at];
    }
}
