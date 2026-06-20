<?php

namespace App\Http\Controllers;

use App\Models\Recruitment;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function show()
    {
        $student = auth()->user();

        $teamId = StudentSchoolYear::where('student_id', $student->student_id)
            ->whereNotNull('team_id')
            ->value('team_id');

        $myTeam = $teamId ? Team::find($teamId) : null;

        $myRecruitment = Recruitment::where('student_id', $student->student_id)->latest()->first();

        $skills = [];
        if ($myRecruitment && is_array($myRecruitment->roles)) {
            $skills = $myRecruitment->roles;
        }

        $availableProfiles = Student::whereHas('studentSchoolYear', function ($query) use ($student) {
            $query->where('school_year', $student->studentSchoolYear()->first()?->school_year ?? null);
        })->get()->take(12);

        $currentSchoolYear = $student->studentSchoolYear()->first()?->school_year;

        $otherRecruitments = Recruitment::where('student_id', '!=', $student->student_id)
            ->whereIn('student_id', StudentSchoolYear::where('school_year', $currentSchoolYear)->pluck('student_id'))
            ->latest()
            ->get()
            ->take(12);

        return view('student.team', [
            'myTeam' => $myTeam,
            'myRecruitment' => $myRecruitment,
            'skills' => $skills,
            'availableProfiles' => $availableProfiles,
            'otherRecruitments' => $otherRecruitments,
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

        DB::table('student_s_y')
            ->where('student_id', $student->student_id)
            ->update(['team_id' => null]);

        return redirect()
            ->route('student.team')
            ->with('status', 'You have left the team.');
    }
}
