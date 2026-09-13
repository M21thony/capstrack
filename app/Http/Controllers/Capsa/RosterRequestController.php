<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\Notification;
use App\Models\RosterChangeRequest;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\RosterLock;
use App\Support\SectionScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Where a CaPSA decides whether a member may leave a team that passed Title
 * Defense.
 */
class RosterRequestController extends Controller
{
    public function __construct(private SectionScope $sections) {}

    public function index(): View
    {
        /** @var CapstoneExpert $capsa */
        $capsa = auth('expert')->user();

        $requests = RosterChangeRequest::visibleTo(
            $capsa,
            RosterChangeRequest::with(['team', 'student', 'requester', 'decider'])->latest()->limit(300)->get()
        );

        [$pending, $decided] = $requests->partition(fn (RosterChangeRequest $r) => $r->isPending());

        $teamIds = $requests->pluck('team_id')->unique()->values();

        return view('capsa.roster_requests', [
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
        ]);
    }

    public function update(Request $request, RosterChangeRequest $rosterRequest, RosterLock $lock): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([RosterChangeRequest::APPROVED, RosterChangeRequest::DECLINED])],
            'note' => ['nullable', 'string', 'max:1000', 'required_if:status,'.RosterChangeRequest::DECLINED],
        ], [
            'note.required_if' => 'Tell the team why you are declining.',
        ]);

        /** @var CapstoneExpert $capsa */
        $capsa = auth('expert')->user();

        if (RosterChangeRequest::visibleTo($capsa, collect([$rosterRequest->loadMissing('team')]))->isEmpty()) {
            return $this->fail('This request is not from a team in your sections.', 403);
        }

        if (! $rosterRequest->isPending()) {
            return $this->fail('This request was already '.$rosterRequest->status.'.', 409);
        }

        $note = isset($validated['note']) ? trim($validated['note']) : null;
        $team = $rosterRequest->team;
        $member = $rosterRequest->student;
        $memberName = trim(($member->f_name ?? '').' '.($member->l_name ?? '')) ?: 'The member';

        if ($validated['status'] === RosterChangeRequest::APPROVED) {
            // Things can move while a request waits.
            if (! StudentSchoolYear::where('student_id', $rosterRequest->student_id)->where('team_id', $team->team_id)->exists()) {
                $rosterRequest->update(['status' => RosterChangeRequest::WITHDRAWN]);

                return $this->fail($memberName.' is no longer on this team, so there is nothing to approve. The request has been closed.', 409);
            }

            if ((int) $team->leader_id === (int) $rosterRequest->student_id) {
                return $this->fail($memberName.' leads the team now. Leadership has to pass to another member before they can leave.', 422);
            }

            $lock->release($rosterRequest, $capsa, $note);
            $message = 'Approved. '.$memberName.' is no longer on '.$team->team_name.'.';
        } else {
            $rosterRequest->update([
                'status' => RosterChangeRequest::DECLINED,
                'decided_by' => $capsa->expert_id,
                'decision_note' => $note,
                'decided_at' => now(),
            ]);

            $this->tellDeclined($rosterRequest, $team, $note);
            $message = 'Declined. The team has been told why.';
        }

        $rosterRequest->refresh();

        return response()->json([
            'success' => true,
            'message' => $message,
            'request' => [
                'id' => $rosterRequest->id,
                'status' => $rosterRequest->status,
                'decided_at' => $rosterRequest->decided_at?->format('M j, Y g:i A'),
                'decision_note' => $rosterRequest->decision_note,
            ],
        ]);
    }

    private function tellDeclined(RosterChangeRequest $rosterRequest, Team $team, string $note): void
    {
        $teamName = '<strong>'.e($team->team_name).'</strong>';
        $why = ' Their reason: "'.e($note).'"';

        Notification::create([
            'student_id' => $rosterRequest->requested_by,
            'type' => 'roster_request_declined',
            'title' => 'Request declined',
            'message' => ($rosterRequest->type === RosterChangeRequest::LEAVE
                ? 'Your CaPSA declined your request to leave '.$teamName.'. You are still on the team.'
                : 'Your CaPSA declined your request to remove a member from '.$teamName.'.').$why,
            'link' => route('student.team'),
        ]);

        // The member a removal was about was told it was asked; they are told
        // how it ended too.
        if ($rosterRequest->type === RosterChangeRequest::REMOVE) {
            Notification::create([
                'student_id' => $rosterRequest->student_id,
                'type' => 'roster_request_declined',
                'title' => 'You stay on the team',
                'message' => 'Your CaPSA declined the request to remove you from '.$teamName.'.'.$why,
                'link' => route('student.team'),
            ]);
        }
    }

    private function lockedTeamCount(CapstoneExpert $capsa): int
    {
        $syRecords = StudentSchoolYear::whereNotNull('team_id')->with('student')->get()->groupBy('team_id');
        $teams = Team::whereIn('team_id', $syRecords->keys())->get();

        return $this->sections->filterTeams($teams, $syRecords, $capsa->sections ?? [])['teams']
            ->filter(fn (Team $t) => $t->isRosterLocked())
            ->count();
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
