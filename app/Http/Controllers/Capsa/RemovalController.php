<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\ChecklistMilestone;
use App\Support\ChecklistAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Undo for a remove: brings removed milestones and deliverables back, until they are purged. */
class RemovalController extends Controller
{
    public function __construct(private ChecklistAccess $access) {}

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'milestones' => ['present', 'array'],
            'milestones.*' => ['integer'],
            'deliverables' => ['present', 'array'],
            'deliverables.*' => ['integer'],
        ]);

        $milestones = ChecklistMilestone::withTrashed()->with('checklist')->whereIn('id', $data['milestones'])->get();

        abort_unless($milestones->count() === count(array_unique($data['milestones'])), 404);
        $milestones->each(fn (ChecklistMilestone $milestone) => $this->access->checklist($milestone->checklist));
        $milestones->each->restore();

        $deliverables = $data['deliverables'] === [] ? collect() : $this->access->deliverables($data['deliverables'], withTrashed: true);
        $deliverables->each->restore();

        return response()->json(['milestones' => $milestones->pluck('id'), 'deliverables' => $deliverables->pluck('id')]);
    }
}
