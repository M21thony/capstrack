<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamResource;
use App\Models\Notification;
use App\Models\Recruitment;
use App\Models\Student;
use App\Models\StudentRole;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();
        $teams = Team::query();

        if ($student->course && $student->year_section) {
            $studentIds = StudentSchoolYear::join('students', 'student_s_y.student_id', '=', 'students.student_id')
                ->where('students.course', $student->course)
                ->where('students.year_section', $student->year_section)
                ->whereNotNull('student_s_y.team_id')
                ->pluck('student_s_y.team_id')
                ->unique();

            $teams->whereIn('team_id', $studentIds);
        }

        $teams = $teams->with('leader', 'members')->latest('created_at')->paginate(20);

        return TeamResource::collection($teams)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'team_name' => ['required', 'string', 'max:100'],
            'project_title' => ['nullable', 'string', 'max:150'],
            'team_status' => ['nullable', Rule::in(['on-going', 'complete', 'closed'])],
        ]);

        $student = $request->user();

        if (StudentSchoolYear::where('student_id', $student->student_id)->whereNotNull('team_id')->exists()) {
            return response()->json(['message' => 'You are already in a team.'], 422);
        }

        $team = Team::create([
            'team_name' => $validated['team_name'],
            'project_title' => $validated['project_title'] ?? null,
            'team_status' => $validated['team_status'] ?? 'on-going',
            'leader_id' => $student->student_id,
        ]);

        $leaderRoleId = StudentRole::where('s_role_name', 'Leader')->value('s_role_id');

        DB::table('student_s_y')
            ->where('student_id', $student->student_id)
            ->update([
                'team_id' => $team->team_id,
                's_role_id' => $leaderRoleId,
            ]);

        $recruitment = Recruitment::where('student_id', $student->student_id)
            ->where('recruitment_goal', 'build_a_team')
            ->first();
        if ($recruitment?->member_count) {
            $team->update(['member_count' => $recruitment->member_count]);
        }

        Recruitment::where('student_id', $student->student_id)
            ->where('recruitment_goal', 'find_a_team')
            ->delete();

        $this->logActivity(
            $student->student_id,
            'team_created',
            'Created team <strong>'.e($team->team_name).'</strong>',
            ['team_id' => $team->team_id, 'team_name' => $team->team_name]
        );

        Notification::create([
            'student_id' => $student->student_id,
            'type' => 'team_created',
            'title' => 'Team Created',
            'message' => 'Your team <strong>'.e($team->team_name).'</strong> has been created.',
            'link' => route('student.team'),
        ]);

        return response()->json([
            'message' => 'Team created successfully.',
            'team' => new TeamResource($team->load('leader', 'members')),
        ], 201);
    }

    public function show(Request $request, Team $team): JsonResponse
    {
        $student = $request->user();
        $studentId = $student->student_id;

        $team->load('leader', 'members');

        $members = $team->members->sortBy(function ($member) use ($team, $studentId) {
            $isLeader = $member->student_id === (int) $team->leader_id;
            $isSelf = $member->student_id === $studentId;

            if ($isLeader) {
                return 0;
            }
            if ($isSelf) {
                return 1;
            }

            return 2;
        })->values();

        return response()->json([
            'team' => new TeamResource($team),
            'members' => $members,
        ]);
    }

    public function update(Request $request, Team $team): JsonResponse
    {
        $student = $request->user();
        $studentId = $student->student_id;

        if ((int) $team->leader_id !== (int) $studentId) {
            return response()->json(['message' => 'Only the team leader can update the team.'], 403);
        }

        if ($team->is_confirmed && $request->has('team_status')) {
            return response()->json(['message' => 'Cannot change status. Team is already verified.'], 403);
        }

        $validated = $request->validate([
            'team_name' => ['required', 'string', 'max:100'],
            'project_title' => ['nullable', 'string', 'max:150'],
            'team_status' => ['nullable', Rule::in(['on-going', 'complete', 'closed'])],
            'member_count' => ['nullable', 'integer', Rule::in([3, 5])],
        ]);

        if ($validated['team_status'] === 'complete') {
            $validated = $request->validate([
                'project_title' => [
                    'required', 'string', 'max:150',
                    function ($attr, $value, $fail) {
                        $count = $this->countMeaningfulWords($value ?? '');

                        if ($count < 11 || $count > 12) {
                            $fail("Project title must contain 11–12 meaningful words (filler words like 'a', 'of', 'in' don't count). Current count: {$count}.");
                        }
                    },
                ],
            ]);
        }

        $originalStatus = $team->team_status;
        $originalMemberCount = $team->member_count;

        $team->update([
            'team_name' => $validated['team_name'],
            'project_title' => $validated['project_title'] ?? null,
            'team_status' => $validated['team_status'] ?? 'on-going',
            'member_count' => $validated['member_count'] ?? $team->member_count ?? 5,
        ]);

        if ($validated['member_count'] ?? false) {
            Recruitment::where('student_id', $studentId)
                ->where('recruitment_goal', 'build_a_team')
                ->update(['member_count' => $validated['member_count']]);
        }

        $autoClosed = false;
        if ($team->team_status === 'on-going') {
            $currentCount = StudentSchoolYear::where('team_id', $team->team_id)->count();
            if ($currentCount >= $team->member_count) {
                $team->update(['team_status' => 'closed']);
                $autoClosed = true;
                $this->logActivity(
                    $studentId,
                    'team_auto_closed',
                    'Team <strong>'.e($team->team_name).'</strong> is now closed (all slots filled)',
                    ['team_id' => $team->team_id]
                );
            }
        }

        if (! $autoClosed && $team->team_status !== $originalStatus) {
            $this->logActivity(
                $studentId,
                'team_status_changed',
                'Changed team status from <strong>'.e($originalStatus).'</strong> to <strong>'.e($team->team_status).'</strong>',
                ['team_id' => $team->team_id, 'old' => $originalStatus, 'new' => $team->team_status]
            );
        }

        if ($team->member_count !== $originalMemberCount) {
            $this->logActivity(
                $studentId,
                'member_count_changed',
                'Updated team member limit to <strong>'.$team->member_count.'</strong>',
                ['team_id' => $team->team_id, 'member_count' => $team->member_count]
            );
        }

        $this->logActivity(
            $studentId,
            'team_updated',
            'Updated team <strong>'.e($team->team_name).'</strong>',
            ['team_id' => $team->team_id]
        );

        return response()->json([
            'message' => 'Team updated successfully.',
            'team' => new TeamResource($team->load('leader', 'members')),
        ]);
    }

    public function destroy(Request $request, Team $team): JsonResponse
    {
        $student = $request->user();
        $studentId = $student->student_id;

        $isLeader = (int) $team->leader_id === (int) $studentId;

        if ($isLeader) {
            $memberCount = StudentSchoolYear::where('team_id', $team->team_id)->count();

            if ($memberCount > 1) {
                return response()->json([
                    'message' => 'As the team leader, you cannot leave directly. Transfer leadership to another member first.',
                ], 422);
            }

            // Solo leader — terminate the team
            StudentSchoolYear::where('team_id', $team->team_id)->update([
                'team_id' => null,
                's_role_id' => null,
            ]);
            $team->delete();

            return response()->json([
                'message' => 'You have left the team and the team has been terminated.',
            ]);
        }

        $studentSy = StudentSchoolYear::where('student_id', $studentId)->first();
        $studentSy->update(['team_id' => null, 's_role_id' => null]);

        $this->logActivity(
            $studentId,
            'team_left',
            'Left team <strong>'.e($team->team_name ?? 'the team').'</strong>',
            ['team_id' => $team->team_id]
        );

        if ($team) {
            $leaderId = $team->leader_id;
            if ($leaderId) {
                Notification::create([
                    'student_id' => $leaderId,
                    'type' => 'member_left',
                    'title' => 'Member Left',
                    'message' => ($student ? $student->f_name.' '.$student->l_name : 'A member').' has left the team.',
                    'link' => route('student.team'),
                ]);
            }
        }

        Recruitment::where('student_id', $studentId)->delete();

        $remainingCount = StudentSchoolYear::where('team_id', $team->team_id)->count();
        if ($remainingCount === 0) {
            TeamInvitation::where('team_id', $team->team_id)->delete();
            $team->delete();
        }

        return response()->json(['message' => 'You have left the team.']);
    }

    public function confirm(Request $request, Team $team): JsonResponse
    {
        $student = $request->user();
        $studentId = $student->student_id;

        $isLeader = (int) $team->leader_id === (int) $studentId;

        if (! $isLeader) {
            return response()->json(['message' => 'Only the team leader can confirm the team.'], 403);
        }

        if ($team->is_confirmed) {
            return response()->json(['message' => 'Team is already confirmed.'], 422);
        }

        $team->update(['is_confirmed' => true]);

        $this->logActivity(
            $studentId,
            'team_confirmed',
            'Confirmed team <strong>'.e($team->team_name).'</strong>',
            ['team_id' => $team->team_id]
        );

        $memberIds = StudentSchoolYear::where('team_id', $team->team_id)
            ->where('student_id', '!=', $studentId)
            ->pluck('student_id');

        foreach ($memberIds as $memberId) {
            Notification::create([
                'student_id' => $memberId,
                'type' => 'team_confirmed',
                'title' => 'Team Confirmed',
                'message' => 'Your team <strong>'.e($team->team_name).'</strong> has been confirmed!',
                'link' => route('student.team'),
            ]);
        }

        return response()->json([
            'message' => 'Team confirmed successfully!',
            'team' => new TeamResource($team->load('leader', 'members')),
        ]);
    }

    public function transferLeadership(Request $request, Team $team): JsonResponse
    {
        $validated = $request->validate([
            'new_leader_id' => ['required', 'integer', 'exists:students,student_id'],
        ]);

        $authUser = $request->user();
        $authId = $authUser->student_id;

        if ((int) $team->leader_id !== (int) $authId) {
            return response()->json(['message' => 'Only the team leader can transfer leadership.'], 403);
        }

        $newLeaderId = (int) $validated['new_leader_id'];

        if ($newLeaderId === $authId) {
            return response()->json(['message' => 'You are already the leader.'], 422);
        }

        $isMember = StudentSchoolYear::where('student_id', $newLeaderId)
            ->where('team_id', $team->team_id)
            ->exists();

        if (! $isMember) {
            return response()->json(['message' => 'That student is not a member of your team.'], 422);
        }

        $team->update(['leader_id' => $newLeaderId]);

        Recruitment::where('student_id', $authId)
            ->where('recruitment_goal', 'build_a_team')
            ->update(['student_id' => $newLeaderId]);

        Recruitment::where('student_id', $newLeaderId)
            ->where('recruitment_goal', '!=', 'build_a_team')
            ->delete();

        $leaderRoleId = StudentRole::where('s_role_name', 'Leader')->value('s_role_id');
        $memberRoleId = StudentRole::where('s_role_name', 'Member')->value('s_role_id');

        StudentSchoolYear::where('student_id', $authId)->update(['s_role_id' => $memberRoleId]);
        StudentSchoolYear::where('student_id', $newLeaderId)->update(['s_role_id' => $leaderRoleId]);

        $newLeader = Student::find($newLeaderId);
        $this->logActivity(
            $authId,
            'leadership_transferred',
            'Transferred leadership to <strong>'.e($newLeader?->f_name.' '.$newLeader?->l_name ?? 'another member').'</strong>',
            ['new_leader_id' => $newLeaderId, 'team_id' => $team->team_id]
        );

        Notification::create([
            'student_id' => $newLeaderId,
            'type' => 'leadership_transferred',
            'title' => 'Leadership Transferred',
            'message' => 'You are now the team leader of <strong>'.e($team->team_name).'</strong>.',
            'link' => route('student.team'),
        ]);

        return response()->json([
            'message' => 'Leadership transferred successfully!',
            'team' => new TeamResource($team->load('leader', 'members')),
        ]);
    }

    public function removeMember(Request $request, Team $team, Student $student): JsonResponse
    {
        $authUser = $request->user();
        $authId = $authUser->student_id;

        if ((int) $team->leader_id !== (int) $authId) {
            return response()->json(['message' => 'Only the team leader can remove members.'], 403);
        }

        if ($student->student_id === $authId) {
            return response()->json(['message' => 'You cannot remove yourself. Transfer leadership instead.'], 422);
        }

        $memberSy = StudentSchoolYear::where('student_id', $student->student_id)
            ->where('team_id', $team->team_id)
            ->first();

        if (! $memberSy) {
            return response()->json(['message' => 'That student is not in your team.'], 422);
        }

        $memberSy->update(['team_id' => null, 's_role_id' => null]);

        Recruitment::where('student_id', $student->student_id)->delete();

        TeamInvitation::where(function ($q) use ($student) {
            $q->where('sender_id', $student->student_id)
                ->orWhere('receiver_id', $student->student_id);
        })->where('team_id', $team->team_id)->delete();

        $this->logActivity(
            $authId,
            'member_removed',
            'Removed <strong>'.e($student->f_name.' '.$student->l_name).'</strong> from the team',
            ['removed_student_id' => $student->student_id, 'team_id' => $team->team_id]
        );

        Notification::create([
            'student_id' => $student->student_id,
            'type' => 'member_removed',
            'title' => 'Removed from Team',
            'message' => 'You have been removed from the team <strong>'.e($team->team_name).'</strong>.',
            'link' => route('student.team'),
        ]);

        return response()->json([
            'message' => $student->f_name.' '.$student->l_name.' has been removed from the team.',
            'team' => new TeamResource($team->load('leader', 'members')),
        ]);
    }
}
