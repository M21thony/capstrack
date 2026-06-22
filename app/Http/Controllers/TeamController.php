<?php

namespace App\Http\Controllers;

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
            'otherRecruitments' => $otherRecruitments,
            'totalStudents' => $totalStudents,
            'teamedUp' => $teamedUp,
            'stillIndividual' => $stillIndividual,
            'invites' => $invites,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'team_name' => ['required', 'string', 'max:100'],
            'project_title' => ['nullable', 'string', 'max:150'],
            'team_status' => ['nullable', Rule::in(['Active', 'Full', 'Need Members', 'Done'])],
        ]);

        $team = Team::create([
            'team_name' => $validated['team_name'],
            'project_title' => $validated['project_title'] ?? null,
            'team_status' => $validated['team_status'] ?? 'Active',
        ]);

        $student = $request->user();

        DB::table('student_s_y')
            ->where('student_id', $student->student_id)
            ->update(['team_id' => $team->team_id]);

        // Auto-delete the creator's recruitment post
        Recruitment::where('student_id', $student->student_id)->delete();

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

        // Determine if the leaving student is the leader
        $leavingRecruit = Recruitment::where('student_id', $studentId)->first();
        $isLeader = $leavingRecruit && $leavingRecruit->recruitment_goal === 'build_a_team';

        if ($isLeader) {
            // Leader leaves → dissolve the entire team
            // Collect all member IDs first (before clearing team_id)
            $memberIds = StudentSchoolYear::where('team_id', $teamId)->pluck('student_id');

            // Unassign every member from the team
            StudentSchoolYear::where('team_id', $teamId)->update(['team_id' => null]);

            // Delete all recruitment posts of members
            Recruitment::whereIn('student_id', $memberIds)->delete();

            // Delete all invitations for this team
            TeamInvitation::where('team_id', $teamId)->delete();

            // Delete the team itself
            Team::where('team_id', $teamId)->delete();

            return redirect()
                ->route('student.team')
                ->with('status', 'You left the team. Since you were the leader, the team has been dissolved.');
        }

        // Regular member leaves — just unassign them
        $studentSy->update(['team_id' => null]);

        // Delete the member's recruitment post if they had one
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
}
