<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\ExpertRemovalRequest;
use App\Models\MemberLeaveRequest;
use App\Models\Notification;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\TeamLock;
use App\Support\SectionScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Where a CaPSA decides whether a member may leave a team, or be removed from
 * one that passed Title Defense.
 */
class LeaveRequestController extends Controller
{
    public function __construct(private SectionScope $sections) {}

    /** A tab on Team Management now; old links land on it. */
    public function index(): RedirectResponse
    {
        return redirect()->route('capsa.team_management', ['tab' => 'leave_requests']);
    }

    /** What the Leave Requests tab shows. */
    public function pageData(CapstoneExpert $capsa): array
    {
        $requests = MemberLeaveRequest::visibleTo(
            $capsa,
            MemberLeaveRequest::with(['team', 'student', 'requester', 'decider'])->latest()->limit(300)->get()
        );

        [$pending, $decided] = $requests->partition(fn (MemberLeaveRequest $r) => $r->isPending());

        $teamIds = $requests->pluck('team_id')->unique()->values();

        // Teams asking to change an expert who already took them on.
        $expertRequests = ExpertRemovalRequest::visibleTo(
            $capsa,
            ExpertRemovalRequest::with(['team', 'expert', 'assignment.expertRole.role', 'requester', 'decider'])->latest()->limit(300)->get()
        );
        [$expertPending, $expertDecided] = $expertRequests->partition(fn ($r) => $r->isPending());

        return [
            'expertPending' => $expertPending->sortBy('created_at')->values(),
            'expertDecided' => $expertDecided->sortByDesc(fn ($r) => $r->decided_at ?? $r->updated_at)->take(50)->values(),
            'expertSectionOf' => $expertRequests->mapWithKeys(fn ($r) => [
                $r->id => $this->sections->sectionFor($r->team) ?: SectionScope::UNASSIGNED,
            ]),
            // Oldest first: whoever has waited longest is at the top.
            'pending' => $pending->sortBy('created_at')->values(),
            'decided' => $decided->sortByDesc(fn ($r) => $r->decided_at ?? $r->updated_at)->take(50)->values(),
            'memberCounts' => StudentSchoolYear::whereIn('team_id', $teamIds)
                ->selectRaw('team_id, count(*) as members')
                ->groupBy('team_id')
                ->pluck('members', 'team_id'),
            'sectionOf' => $requests->mapWithKeys(fn ($r) => [
                $r->id => $this->sections->sectionFor($r->team) ?: SectionScope::UNASSIGNED,
            ]),
            'lockedTeams' => $this->lockedTeamCount($capsa),
        ];
    }

    public function update(Request $request, MemberLeaveRequest $leaveRequest, TeamLock $lock): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([MemberLeaveRequest::APPROVED, MemberLeaveRequest::DECLINED])],
            'note' => ['nullable', 'string', 'max:1000', 'required_if:status,'.MemberLeaveRequest::DECLINED],
        ], [
            'note.required_if' => 'Tell the team why you are declining.',
        ]);

        /** @var CapstoneExpert $capsa */
        $capsa = auth('expert')->user();

        if (MemberLeaveRequest::visibleTo($capsa, collect([$leaveRequest->loadMissing('team')]))->isEmpty()) {
            return $this->fail('This request is not from a team in your sections.', 403);
        }

        if (! $leaveRequest->isPending()) {
            return $this->fail('This request was already '.$leaveRequest->status.'.', 409);
        }

        $note = isset($validated['note']) ? trim($validated['note']) : null;
        $team = $leaveRequest->team;
        $member = $leaveRequest->student;
        $memberName = trim(($member->f_name ?? '').' '.($member->l_name ?? '')) ?: 'The member';

        if ($validated['status'] === MemberLeaveRequest::APPROVED) {
            // Things can move while a request waits.
            if (! StudentSchoolYear::where('student_id', $leaveRequest->student_id)->where('team_id', $team->team_id)->exists()) {
                $leaveRequest->update(['status' => MemberLeaveRequest::WITHDRAWN]);

                return $this->fail($memberName.' is no longer on this team, so there is nothing to approve. The request has been closed.', 409);
            }

            $members = StudentSchoolYear::where('team_id', $team->team_id)->count();

            // A leader on their own may go: the team ends with them.
            if ((int) $team->leader_id === (int) $leaveRequest->student_id && $members > 1) {
                return $this->fail($memberName.' leads the team now. Leadership has to pass to another member before they can leave.', 422);
            }

            $lock->release($leaveRequest, $capsa, $note);
            $message = 'Approved. '.$memberName.' is no longer on '.$team->team_name.'.'
                .($members === 1 ? ' The team has ended and its experts were told.' : '');
        } else {
            $leaveRequest->update([
                'status' => MemberLeaveRequest::DECLINED,
                'decided_by' => $capsa->expert_id,
                'decision_note' => $note,
                'decided_at' => now(),
            ]);

            $this->tellDeclined($leaveRequest, $team, $note);
            $message = 'Declined. The team has been told why.';
        }

        $leaveRequest->refresh();

        return response()->json([
            'success' => true,
            'message' => $message,
            'request' => [
                'id' => $leaveRequest->id,
                'status' => $leaveRequest->status,
                'decided_at' => $leaveRequest->decided_at?->format('M j, Y g:i A'),
                'decision_note' => $leaveRequest->decision_note,
            ],
        ]);
    }

    private function tellDeclined(MemberLeaveRequest $leaveRequest, Team $team, string $note): void
    {
        $teamName = '<strong>'.e($team->team_name).'</strong>';
        $why = ' Their reason: "'.e($note).'"';

        Notification::create([
            'student_id' => $leaveRequest->requested_by,
            'type' => 'leave_request_declined',
            'title' => 'Request declined',
            'message' => ($leaveRequest->type === MemberLeaveRequest::LEAVE
                ? 'Your CaPSA declined your request to leave '.$teamName.'. You are still on the team.'
                : 'Your CaPSA declined your request to remove a member from '.$teamName.'.').$why,
            'link' => route('student.team'),
            // Which request, so tapping it can show the whole decision.
            'data' => ['leave_request_id' => $leaveRequest->id],
        ]);

        // The member a removal was about was told it was asked; they are told
        // how it ended too.
        if ($leaveRequest->type === MemberLeaveRequest::REMOVE) {
            Notification::create([
                'student_id' => $leaveRequest->student_id,
                'type' => 'leave_request_declined',
                'title' => 'You stay on the team',
                'message' => 'Your CaPSA declined the request to remove you from '.$teamName.'.'.$why,
                'link' => route('student.team'),
                // Which request, so tapping it can show the whole decision.
                'data' => ['leave_request_id' => $leaveRequest->id],
            ]);
        }
    }

    private function lockedTeamCount(CapstoneExpert $capsa): int
    {
        $syRecords = StudentSchoolYear::whereNotNull('team_id')->with('student')->get()->groupBy('team_id');
        $teams = Team::whereIn('team_id', $syRecords->keys())->get();

        return $this->sections->filterTeams($teams, $syRecords, $capsa->sections ?? [])['teams']
            ->filter(fn (Team $t) => $t->isTeamLocked())
            ->count();
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
