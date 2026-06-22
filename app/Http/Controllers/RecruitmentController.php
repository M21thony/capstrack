<?php

namespace App\Http\Controllers;

use App\Models\Recruitment;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecruitmentController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'recruitment_goal' => ['required', 'string', 'in:build_a_team,find_a_team'],
            'looking_for' => ['nullable', 'string', 'max:500'],
            'introduction' => ['nullable', 'string', 'max:500'],
            'team_name' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'string'],
        ]);

        $student = $request->user();
        $studentId = $student ? $student->student_id : 8;

        // Prevent posting if the student already belongs to a team
        $studentSy = StudentSchoolYear::where('student_id', $studentId)->first();
        if ($studentSy?->team_id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a team, so you cannot publish a recruitment post.',
                ], 422);
            }

            return back()->withErrors(['error' => 'You already have a team, so you cannot publish a recruitment post.']);
        }

        // Decode the tags input
        $tagsJson = $request->input('tags');
        $roles = [];
        if ($tagsJson) {
            $roles = json_decode($tagsJson, true) ?? [];
        }

        // If goal is build_a_team and team_name is provided, create team and link it
        if ($validated['recruitment_goal'] === 'build_a_team' && ! empty($validated['team_name'])) {
            $team = Team::create([
                'team_name' => $validated['team_name'],
                'team_status' => 'Active',
            ]);

            StudentSchoolYear::where('student_id', $studentId)
                ->update(['team_id' => $team->team_id]);
        }

        $recruitment = Recruitment::create([
            'student_id' => $studentId,
            'recruitment_goal' => $validated['recruitment_goal'],
            'roles' => $roles,
            'looking_for' => $validated['looking_for'] ?? null,
            'introduction' => $validated['introduction'] ?? null,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Recruitment post published.',
                'recruitment' => $recruitment,
            ], 201);
        }

        return redirect()
            ->route('student.team')
            ->with('status', 'Recruitment post published!');
    }

    public function destroy(Request $request, Recruitment $recruitment): RedirectResponse
    {
        $student = $request->user();
        $studentId = $student ? $student->student_id : 8;

        if ($recruitment->student_id !== $studentId) {
            abort(403);
        }

        $recruitment->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Recruitment post removed.',
            ]);
        }

        return redirect()
            ->route('student.team')
            ->with('status', 'Recruitment post removed.');
    }
}
