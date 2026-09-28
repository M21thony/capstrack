<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\Team;
use App\Services\Notifier;
use App\Support\ChecklistAccess;
use App\Support\ChecklistTracker;
use App\Support\MilestoneProgress;
use App\Support\TeamChecklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The CaPSA's check of one team's signed paper: complete, or sent back with
 * a note saying what to fix. The system has no e-signing, so a signed form
 * and a signed CLF both close this way.
 */
class DeliverableCheckController extends Controller
{
    public function __construct(
        private ChecklistAccess $access,
        private TeamChecklist $checklists,
    ) {}

    public function update(Request $request, Deliverable $deliverable, Team $team): JsonResponse
    {
        if ($refusal = $this->refusal($deliverable, $team)) {
            return $refusal;
        }

        $data = $request->validate([
            'status' => ['required', 'in:'.DeliverableCheck::COMPLETE.','.DeliverableCheck::SENT_BACK],
            'note' => ['nullable', 'required_if:status,'.DeliverableCheck::SENT_BACK, 'string', 'max:1000'],
            'file_id' => ['nullable', 'integer'],
        ]);

        // The same lock the team's upload is held to: nothing is closed past it.
        $current = MilestoneProgress::forTeam($deliverable->milestone->checklist, $team->team_id)
            ->items->first(fn ($r) => $r->item->id === $deliverable->id);

        if ($current?->locked) {
            return response()->json(['message' => $deliverable->title." can't be checked yet: it waits for ".$current->waits_for.'.'], 422);
        }

        DeliverableCheck::updateOrCreate(
            ['deliverable_id' => $deliverable->id, 'team_id' => $team->team_id],
            [
                'status' => $data['status'],
                'note' => $data['status'] === DeliverableCheck::SENT_BACK ? trim($data['note']) : null,
                'file_id' => $data['file_id'] ?? null,
                'checked_by' => $this->access->capsa()->expert_id,
                'checked_at' => now(),
            ],
        );

        // Sent back: the file stays; the team is told what to change.
        if ($data['status'] === DeliverableCheck::SENT_BACK) {
            Notifier::toStudents(
                $team->members,
                'paper_sent_back',
                'Sent back: '.$deliverable->title,
                'Your CaPSA sent back <strong>'.e($deliverable->title).'</strong>: “'.e(trim($data['note'])).'” Your file is kept - replace it with a corrected copy.',
                route('student.progress'),
                ['team_id' => $team->team_id, 'deliverable_id' => $deliverable->id],
            );
        }

        return response()->json($this->row($deliverable, $team));
    }

    /** Undo a check. */
    public function destroy(Deliverable $deliverable, Team $team): JsonResponse
    {
        if ($refusal = $this->refusal($deliverable, $team)) {
            return $refusal;
        }

        DeliverableCheck::where('deliverable_id', $deliverable->id)->where('team_id', $team->team_id)->delete();

        return response()->json($this->row($deliverable, $team));
    }

    private function refusal(Deliverable $deliverable, Team $team): ?JsonResponse
    {
        $this->access->deliverable($deliverable);
        $section = $this->access->team($team);

        if (! in_array($deliverable->kind, Deliverable::CHECKED_BY_CAPSA, true)) {
            return response()->json(['message' => 'This one closes on its own when the upload or the defense happens.'], 422);
        }

        if ($this->checklists->forSection($section)?->id !== $deliverable->milestone->checklist_id) {
            return response()->json(['message' => "That deliverable is not on this team's checklist."], 422);
        }

        return null;
    }

    /** The team's row for this deliverable, as the tracker redraws it. */
    private function row(Deliverable $deliverable, Team $team): array
    {
        $row = MilestoneProgress::forTeam($deliverable->milestone->checklist, $team->team_id)
            ->items
            ->first(fn ($r) => $r->item->id === $deliverable->id);

        return $row ? ChecklistTracker::row($row, $team->team_id) : [];
    }
}
