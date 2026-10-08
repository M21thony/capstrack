<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\ExpertRemovalRequest;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Services\Notifier;
use App\Support\ExpertApplications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * The CaPSA deciding whether a team may let go of an expert who already took
 * them on. Approving removes the expert and sends the team's application to
 * the replacement it picked, who accepts or declines it like any other.
 */
class ExpertRemovalRequestController extends Controller
{
    public function __construct(private ExpertApplications $applications) {}

    /** PATCH /capsa/expert-removal-requests/{expertRemovalRequest} */
    public function update(Request $request, ExpertRemovalRequest $expertRemovalRequest): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([ExpertRemovalRequest::APPROVED, ExpertRemovalRequest::DECLINED])],
            'note' => ['nullable', 'string', 'max:1000', 'required_if:status,'.ExpertRemovalRequest::DECLINED],
        ], [
            'note.required_if' => 'Tell the team why you are declining.',
        ]);

        /** @var CapstoneExpert $capsa */
        $capsa = auth('expert')->user();
        $removal = $expertRemovalRequest->loadMissing(['team', 'assignment', 'expert']);

        if (ExpertRemovalRequest::visibleTo($capsa, collect([$removal]))->isEmpty()) {
            return $this->fail('This request is not from a team in your sections.', 403);
        }

        if (! $removal->isPending()) {
            return $this->fail('This request was already '.$removal->status.'.', 409);
        }

        $note = isset($validated['note']) ? trim($validated['note']) : null;
        $team = $removal->team;
        $expertName = $removal->expert?->name ?? 'The expert';
        $teamName = '<strong>'.e($team->team_name).'</strong>';

        if ($validated['status'] === ExpertRemovalRequest::APPROVED) {
            $assignment = $removal->assignment;

            // Things can move while a request waits: the expert may have
            // withdrawn from the team themselves.
            if (! $assignment || $assignment->status !== 'approved') {
                $removal->update(['status' => ExpertRemovalRequest::WITHDRAWN]);

                return $this->fail($expertName.' is no longer this team\'s expert, so there is nothing to approve. The request has been closed.', 409);
            }

            $role = $assignment->engaged_role_name ?: 'expert';
            $replacement = $removal->replacement;
            $leader = $team->leader_id ? Student::find($team->leader_id) : null;
            $swapping = $removal->replacement_expert_id && $leader;

            // The replacement is checked before anyone is removed, so the old
            // expert never goes for an application that cannot be sent. A
            // replacement who filled up while the request waited is the
            // CaPSA's to decline.
            if ($swapping) {
                $refusal = $replacement
                    ? $this->applications->refusal($team, $leader, $replacement, (int) $removal->replacement_expert_role_id, $assignment->assignment_id)
                    : ['message' => 'That expert is no longer in the system.'];

                if ($refusal) {
                    return $this->fail(($replacement?->name ?? 'The replacement they picked').' cannot take '.$team->team_name.': '
                        .lcfirst(rtrim($refusal['message'], '.')).'. Decline the request so the team can pick someone else.', 422);
                }
            }

            if ($assignment->proposal_file) {
                Storage::disk('public')->delete($assignment->proposal_file);
            }

            $assignment->delete();

            Notifier::toExpert(
                (int) $assignment->expert_id,
                'expert_application',
                'Removed from a team',
                'The CaPSA removed you as the '.e($role).' of '.$teamName.'.',
                route('instructor.teams'),
                ['team_id' => $team->team_id],
            );

            $replaced = $swapping ? $this->applications->send(
                $team,
                $replacement,
                (int) $removal->replacement_expert_role_id,
                (string) $removal->replacement_message,
                $removal->replacement_proposal_file,
                $team->selected_pre_proposal_id ?: null,
            ) : null;

            $removal->update([
                'status' => ExpertRemovalRequest::APPROVED,
                'decided_by' => $capsa->expert_id,
                'decision_note' => $note,
                'decided_at' => now(),
                'replacement_assignment_id' => $replaced?->assignment_id,
                'replacement_outcome' => $replaced ? ExpertRemovalRequest::REPLACEMENT_SENT : null,
            ]);

            foreach (StudentSchoolYear::where('team_id', $team->team_id)->pluck('student_id') as $studentId) {
                Notification::create([
                    'student_id' => $studentId,
                    'type' => 'expert_removal_approved',
                    'title' => 'Expert change approved',
                    'message' => 'Your CaPSA approved removing <strong>'.e($expertName).'</strong> as the '.e($role).' of '.$teamName.'. '
                        .($replaced
                            ? 'Your application went to <strong>'.e($replacement->name).'</strong>, who will accept or decline it.'
                            : 'You can apply to another '.e($role).' now.')
                        .($note ? ' Note: "'.e($note).'"' : ''),
                    'link' => route('student.experts'),
                ]);
            }

            $message = 'Approved. '.$expertName.' is no longer with '.$team->team_name.'.'
                .($replaced ? ' Their application went to '.$replacement->name.'.' : '');
        } else {
            // The PDF meant for the replacement never goes anywhere now.
            if ($removal->replacement_proposal_file) {
                Storage::disk('public')->delete($removal->replacement_proposal_file);
            }

            $removal->update([
                'status' => ExpertRemovalRequest::DECLINED,
                'decided_by' => $capsa->expert_id,
                'decision_note' => $note,
                'decided_at' => now(),
            ]);

            Notification::create([
                'student_id' => $removal->requested_by,
                'type' => 'expert_removal_declined',
                'title' => 'Expert change declined',
                'message' => 'Your CaPSA declined removing <strong>'.e($expertName).'</strong> from '.$teamName
                    .'. They stay your expert. Their reason: "'.e($note).'"',
                'link' => route('student.experts'),
            ]);

            $message = 'Declined. The team has been told why.';
        }

        $removal->refresh();

        return response()->json([
            'success' => true,
            'message' => $message,
            'request' => [
                'id' => $removal->id,
                'status' => $removal->status,
                'decided_at' => $removal->decided_at?->format('M j, Y g:i A'),
                'decision_note' => $removal->decision_note,
            ],
        ]);
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
