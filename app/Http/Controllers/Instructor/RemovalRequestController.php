<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\ExpertRemovalRequest;
use App\Models\Notification;
use App\Models\StudentSchoolYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * An expert deciding whether to let a team go.
 *
 * Once an expert has taken a team on, the team cannot drop them on its own -
 * it asks, and the expert it is asking about answers. Releasing the team
 * removes the assignment, which frees the role for the team to apply to
 * someone else; declining keeps the expert where they are.
 */
class RemovalRequestController extends Controller
{
    /** PATCH /instructor/removal-requests/{expertRemovalRequest} */
    public function update(Request $request, ExpertRemovalRequest $expertRemovalRequest): RedirectResponse
    {
        /** @var CapstoneExpert $expert */
        $expert = Auth::guard('expert')->user();
        $removal = $expertRemovalRequest->loadMissing(['team', 'assignment', 'expert']);

        // Only the expert the team asked about decides. Anyone else gets the
        // same answer as a request that does not exist.
        abort_unless((int) $removal->expert_id === (int) $expert->expert_id, 404);

        if (! $removal->isPending()) {
            return redirect()->route('instructor.assignments')
                ->with('status', 'That request was already '.$removal->status.'.');
        }

        // Its own error bag: the title confirmations on the same page also
        // have a "note" field, and a mistake here must not show up there.
        $validated = $request->validateWithBag('removal', [
            'decision' => ['required', 'in:release,decline'],
            'note' => ['required_if:decision,decline', 'nullable', 'string', 'min:10', 'max:1000'],
        ], [
            'note.required_if' => 'Tell the team why you are staying on - they asked for a reason and are owed one.',
            'note.min' => 'Tell the team why you are staying on - they asked for a reason and are owed one.',
        ]);

        $note = isset($validated['note']) ? trim($validated['note']) : null;
        $team = $removal->team;
        $teamName = '<strong>'.e($team->team_name).'</strong>';

        if ($validated['decision'] === 'release') {
            $assignment = $removal->assignment;

            // Things can move while a request waits: the expert may already
            // have let the team go from the Approved tab.
            if (! $assignment || $assignment->status !== 'approved') {
                $removal->update(['status' => ExpertRemovalRequest::WITHDRAWN]);

                return redirect()->route('instructor.assignments')
                    ->with('status', 'You are no longer with '.$team->team_name.', so there was nothing to release. The request has been closed.');
            }

            $role = $assignment->engaged_role_name ?: 'expert';

            if ($assignment->proposal_file) {
                Storage::disk('public')->delete($assignment->proposal_file);
            }

            $assignment->delete();

            $removal->update([
                'status' => ExpertRemovalRequest::APPROVED,
                'decided_by' => $expert->expert_id,
                'decision_note' => $note,
                'decided_at' => now(),
            ]);

            foreach (StudentSchoolYear::where('team_id', $team->team_id)->pluck('student_id') as $studentId) {
                Notification::create([
                    'student_id' => $studentId,
                    'type' => 'expert_removal_approved',
                    'title' => 'Expert released your team',
                    'message' => '<strong>'.e($expert->name).'</strong> agreed to stop being the '.e($role).' of '.$teamName
                        .'. You can apply to another '.e($role).' now.'.($note ? ' Note: "'.e($note).'"' : ''),
                    'link' => route('student.experts'),
                ]);
            }

            return redirect()->route('instructor.assignments')
                ->with('status', 'Released '.$team->team_name.'. You are no longer their '.$role.'.');
        }

        $removal->update([
            'status' => ExpertRemovalRequest::DECLINED,
            'decided_by' => $expert->expert_id,
            'decision_note' => $note,
            'decided_at' => now(),
        ]);

        Notification::create([
            'student_id' => $removal->requested_by,
            'type' => 'expert_removal_declined',
            'title' => 'Expert is staying on',
            'message' => '<strong>'.e($expert->name).'</strong> declined to be removed from '.$teamName
                .' and stays your expert. Their reason: "'.e($note).'"',
            'link' => route('student.experts'),
        ]);

        return redirect()->route('instructor.assignments')
            ->with('status', 'Declined. '.$team->team_name.' has been told why you are staying on.');
    }
}
