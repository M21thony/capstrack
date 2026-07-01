<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Recruitment;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $studentId = auth()->id() ?? 8;
        $student = Student::find($studentId);

        if (! $student) {
            $student = auth()->user();
            $studentId = $student ? $student->student_id : 8;
        }

        $studentSy = StudentSchoolYear::where('student_id', $studentId)->first();
        $teamId = $studentSy?->team_id;

        $myTeam = null;
        $teamMembers = collect();

        if ($teamId) {
            $myTeam = Team::find($teamId);
            $teamMembers = Student::join('student_s_y', 'students.student_id', '=', 'student_s_y.student_id')
                ->where('student_s_y.team_id', $teamId)
                ->get();
        }

        $myRecruitment = Recruitment::where('student_id', $studentId)->latest()->first();

        $skills = [];
        if ($myRecruitment && is_array($myRecruitment->roles)) {
            $skills = $myRecruitment->roles;
        }

        $memberCount = $myRecruitment?->member_count ?? 5;
        $isLeader = $myRecruitment && $myRecruitment->recruitment_goal === 'build_a_team';

        $leaderIds = [];
        if ($teamId) {
            $leaderIds = Recruitment::whereIn('student_id', $teamMembers->pluck('student_id'))
                ->where('recruitment_goal', 'build_a_team')
                ->pluck('student_id')
                ->toArray();

            $leaderStudentId = $leaderIds[0] ?? null;
            $teamMembers = $teamMembers->sortBy(function ($member) use ($leaderStudentId, $studentId) {
                if ($member->student_id === $leaderStudentId) {
                    return 0;
                }
                if ($member->student_id === $studentId) {
                    return 1;
                }

                return 2;
            })->values();
        }

        $currentSchoolYear = $studentSy?->school_year;

        // Dynamic Section Overview Statistics (filtered by same course, year_section and school year)
        $totalStudents = StudentSchoolYear::join('students', 'student_s_y.student_id', '=', 'students.student_id')
            ->where('student_s_y.school_year', $currentSchoolYear)
            ->where('students.course', $student->course)
            ->where('students.year_section', $student->year_section)
            ->count();

        $teamedUp = StudentSchoolYear::join('students', 'student_s_y.student_id', '=', 'students.student_id')
            ->where('student_s_y.school_year', $currentSchoolYear)
            ->where('students.course', $student->course)
            ->where('students.year_section', $student->year_section)
            ->whereNotNull('student_s_y.team_id')
            ->count();

        $stillIndividual = StudentSchoolYear::join('students', 'student_s_y.student_id', '=', 'students.student_id')
            ->where('student_s_y.school_year', $currentSchoolYear)
            ->where('students.course', $student->course)
            ->where('students.year_section', $student->year_section)
            ->whereNull('student_s_y.team_id')
            ->count();

        // Feed recruitment posts (only from students in the same course, year_section and school year)
        $otherRecruitments = Recruitment::with(['student.team'])
            ->where('student_id', '!=', $studentId)
            ->whereIn('student_id', function ($query) use ($student, $currentSchoolYear) {
                $query->select('student_s_y.student_id')
                    ->from('student_s_y')
                    ->join('students', 'student_s_y.student_id', '=', 'students.student_id')
                    ->where('student_s_y.school_year', $currentSchoolYear)
                    ->where('students.course', $student->course)
                    ->where('students.year_section', $student->year_section);
            })
            ->latest()
            ->paginate(5);

        // Pending Invites Collection: both team invites sent to this student AND join requests sent to this student
        $invites = TeamInvitation::with(['team', 'sender', 'receiver'])
            ->where('receiver_id', $studentId)
            ->where('status', 'pending')
            ->get();

        return view('student.team', [
            'myTeam' => $myTeam,
            'teamMembers' => $teamMembers,
            'myRecruitment' => $myRecruitment,
            'myRecruitmentGoal' => $myRecruitment?->recruitment_goal,
            'skills' => $skills,
            'memberCount' => $memberCount,
            'isLeader' => $isLeader,
            'otherRecruitments' => $otherRecruitments,
            'totalStudents' => $totalStudents,
            'teamedUp' => $teamedUp,
            'stillIndividual' => $stillIndividual,
            'invites' => $invites,
            'leaderIds' => $leaderIds,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'team_name' => ['required', 'string', 'max:100'],
            'project_title' => ['nullable', 'string', 'max:150'],
            'team_status' => ['nullable', Rule::in(['Active', 'Full', 'Need Members', 'Done'])],
        ]);

        $student = $request->user();

        $team = Team::create([
            'team_name' => $validated['team_name'],
            'project_title' => $validated['project_title'] ?? null,
            'team_status' => $validated['team_status'] ?? 'Active',
        ]);

        DB::table('student_s_y')
            ->where('student_id', $student->student_id)
            ->update(['team_id' => $team->team_id]);

        // Auto-delete the creator's recruitment post
        Recruitment::where('student_id', $student->student_id)->delete();

        $this->logActivity(
            $student->student_id,
            'team_created',
            'Created team <strong>'.e($team->team_name).'</strong>',
            ['team_id' => $team->team_id, 'team_name' => $team->team_name]
        );

        // Notify team creator
        Notification::create([
            'student_id' => $student->student_id,
            'type' => 'team_created',
            'title' => 'Team Created',
            'message' => 'Your team <strong>'.e($team->team_name).'</strong> has been created.',
            'link' => route('student.team'),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Team created successfully.',
                'redirect_url' => route('student.team'),
                'team' => $team,
            ], 201);
        }

        return redirect()
            ->route('student.team')
            ->with('status', 'Team "'.$team->team_name.'" created successfully!');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $student = $request->user();
        $studentId = $student ? $student->student_id : 8;

        $studentSy = StudentSchoolYear::where('student_id', $studentId)->first();
        $teamId = $studentSy ? $studentSy->team_id : null;

        if (! $teamId) {
            return redirect()
                ->route('student.team')
                ->with('status', 'You are not in a team.');
        }

        $leavingRecruit = Recruitment::where('student_id', $studentId)->first();
        $isLeader = $leavingRecruit && $leavingRecruit->recruitment_goal === 'build_a_team';

        if ($isLeader) {
            return redirect()
                ->route('student.team')
                ->with('error', 'As the team leader, you cannot leave directly. Transfer leadership to another member first.');
        }

        // Regular member leaves — just unassign them
        $studentSy->update(['team_id' => null]);

        $this->logActivity(
            $studentId,
            'team_left',
            'Left team <strong>'.e(Team::find($teamId)?->team_name ?? 'the team').'</strong>',
            ['team_id' => $teamId]
        );

        // Notify team leader that a member left
        $team = Team::find($teamId);
        if ($team) {
            $leaderId = Recruitment::where('recruitment_goal', 'build_a_team')
                ->whereIn('student_id', function ($q) use ($teamId) {
                    $q->select('student_id')->from('student_s_y')->where('team_id', $teamId);
                })->value('student_id');
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

        if ($leavingRecruit) {
            $leavingRecruit->delete();
        }

        // If no members remain, clean up the team
        $remainingCount = StudentSchoolYear::where('team_id', $teamId)->count();
        if ($remainingCount === 0) {
            TeamInvitation::where('team_id', $teamId)->delete();
            Team::where('team_id', $teamId)->delete();
        }

        return redirect()
            ->route('student.team')
            ->with('status', 'You have left the team.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $student = $request->user();
        $studentId = $student->student_id;

        $myRecruitment = Recruitment::where('student_id', $studentId)->first();
        $isLeader = $myRecruitment && $myRecruitment->recruitment_goal === 'build_a_team';

        if (! $isLeader) {
            return redirect()->route('student.team')
                ->with('error', 'Only the team leader can confirm the team.');
        }

        $myTeam = Team::find(
            StudentSchoolYear::where('student_id', $studentId)->value('team_id')
        );

        if (! $myTeam) {
            return redirect()->route('student.team')
                ->with('error', 'You are not in a team.');
        }

        if ($myTeam->is_confirmed) {
            return redirect()->route('student.team')
                ->with('status', 'Team is already confirmed.');
        }

        $myTeam->update(['is_confirmed' => true]);

        $this->logActivity(
            $studentId,
            'team_confirmed',
            'Confirmed team <strong>'.e($myTeam->team_name).'</strong>',
            ['team_id' => $myTeam->team_id]
        );

        // Notify all team members
        $memberIds = StudentSchoolYear::where('team_id', $myTeam->team_id)
            ->where('student_id', '!=', $studentId)
            ->pluck('student_id');
        foreach ($memberIds as $memberId) {
            Notification::create([
                'student_id' => $memberId,
                'type' => 'team_confirmed',
                'title' => 'Team Confirmed',
                'message' => 'Your team <strong>'.e($myTeam->team_name).'</strong> has been confirmed!',
                'link' => route('student.team'),
            ]);
        }

        return redirect()->route('student.team')
            ->with('status', 'Team confirmed successfully!');
    }

    public function removeMember(Request $request, Student $student): RedirectResponse
    {
        $authUser = $request->user();
        $authId = $authUser->student_id;

        $myRecruitment = Recruitment::where('student_id', $authId)->first();
        $isLeader = $myRecruitment && $myRecruitment->recruitment_goal === 'build_a_team';

        if (! $isLeader) {
            return redirect()->route('student.team')
                ->with('error', 'Only the team leader can remove members.');
        }

        $myTeam = Team::find(
            StudentSchoolYear::where('student_id', $authId)->value('team_id')
        );

        if (! $myTeam) {
            return redirect()->route('student.team')
                ->with('error', 'You are not in a team.');
        }

        if ($myTeam->is_confirmed) {
            return redirect()->route('student.team')
                ->with('error', 'Cannot remove members after the team is confirmed.');
        }

        if ($student->student_id === $authId) {
            return redirect()->route('student.team')
                ->with('error', 'You cannot remove yourself. Transfer leadership instead.');
        }

        // Verify the target student is actually in this team
        $memberSy = StudentSchoolYear::where('student_id', $student->student_id)
            ->where('team_id', $myTeam->team_id)
            ->first();

        if (! $memberSy) {
            return redirect()->route('student.team')
                ->with('error', 'That student is not in your team.');
        }

        $memberSy->update(['team_id' => null]);

        Recruitment::where('student_id', $student->student_id)->delete();

        TeamInvitation::where(function ($q) use ($student) {
            $q->where('sender_id', $student->student_id)
                ->orWhere('receiver_id', $student->student_id);
        })->where('team_id', $myTeam->team_id)->delete();

        $this->logActivity(
            $authId,
            'member_removed',
            'Removed <strong>'.e($student->f_name.' '.$student->l_name).'</strong> from the team',
            ['removed_student_id' => $student->student_id, 'team_id' => $myTeam->team_id]
        );

        // Notify removed member
        Notification::create([
            'student_id' => $student->student_id,
            'type' => 'member_removed',
            'title' => 'Removed from Team',
            'message' => 'You have been removed from the team <strong>'.e($myTeam->team_name).'</strong>.',
            'link' => route('student.team'),
        ]);

        return redirect()->route('student.team')
            ->with('status', $student->f_name.' '.$student->l_name.' has been removed from the team.');
    }

    public function transferLeadership(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'new_leader_id' => ['required', 'integer', 'exists:students,student_id'],
        ]);

        $authUser = $request->user();
        $authId = $authUser->student_id;

        $myRecruitment = Recruitment::where('student_id', $authId)->first();
        $isLeader = $myRecruitment && $myRecruitment->recruitment_goal === 'build_a_team';

        if (! $isLeader) {
            return redirect()->route('student.team')
                ->with('error', 'Only the team leader can transfer leadership.');
        }

        $myTeam = Team::find(
            StudentSchoolYear::where('student_id', $authId)->value('team_id')
        );

        if (! $myTeam) {
            return redirect()->route('student.team')
                ->with('error', 'You are not in a team.');
        }

        $newLeaderId = (int) $validated['new_leader_id'];

        if ($newLeaderId === $authId) {
            return redirect()->route('student.team')
                ->with('error', 'You are already the leader.');
        }

        // Verify new leader is in the team
        $isMember = StudentSchoolYear::where('student_id', $newLeaderId)
            ->where('team_id', $myTeam->team_id)
            ->exists();

        if (! $isMember) {
            return redirect()->route('student.team')
                ->with('error', 'That student is not a member of your team.');
        }

        // Demote old leader: delete their build_a_team post
        $myRecruitment->delete();

        // Promote new leader: create or update a build_a_team post
        $newLeaderRecruit = Recruitment::where('student_id', $newLeaderId)->first();
        if ($newLeaderRecruit) {
            $newLeaderRecruit->update(['recruitment_goal' => 'build_a_team']);
        } else {
            Recruitment::create([
                'student_id' => $newLeaderId,
                'recruitment_goal' => 'build_a_team',
                'roles' => [],
            ]);
        }

        $newLeader = Student::find($newLeaderId);
        $this->logActivity(
            $authId,
            'leadership_transferred',
            'Transferred leadership to <strong>'.e($newLeader?->f_name.' '.$newLeader?->l_name ?? 'another member').'</strong>',
            ['new_leader_id' => $newLeaderId, 'team_id' => $myTeam->team_id]
        );

        // Notify new leader
        Notification::create([
            'student_id' => $newLeaderId,
            'type' => 'leadership_transferred',
            'title' => 'Leadership Transferred',
            'message' => 'You are now the team leader of <strong>'.e($myTeam->team_name).'</strong>.',
            'link' => route('student.team'),
        ]);

        return redirect()->route('student.team')
            ->with('status', 'Leadership transferred successfully!');
    }
}
