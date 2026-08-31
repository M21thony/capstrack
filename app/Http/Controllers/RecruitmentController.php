<?php

namespace App\Http\Controllers;

use App\Models\Recruitment;
use App\Models\StudentRole;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RecruitmentController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'recruitment_goal' => ['required', 'string', 'in:build_a_team,find_a_team'],
            'looking_for' => ['nullable', 'string', 'max:500'],
            'introduction' => ['nullable', 'string', 'max:500'],
            'team_name' => [
                'nullable', 'string', 'max:100',
                function ($attribute, $value, $fail) {
                    if ($value && Team::nameExists($value)) {
                        $fail('The team name has already been taken.');
                    }
                },
            ],
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

        $recruitment = DB::transaction(function () use ($validated, $studentId, $roles): Recruitment {
            // If goal is build_a_team and team_name is provided, create team and link it
            if ($validated['recruitment_goal'] === 'build_a_team' && ! empty($validated['team_name'])) {
                $team = Team::create([
                    'team_name' => $validated['team_name'],
                    'team_status' => 'Active',
                    'leader_id' => $studentId,
                ]);

                DB::table('student_s_y')
                    ->updateOrInsert(
                        ['student_id' => $studentId],
                        ['team_id' => $team->team_id, 's_role_id' => StudentRole::leaderRoleId()]
                    );
            }

            return Recruitment::create([
                'student_id' => $studentId,
                'recruitment_goal' => $validated['recruitment_goal'],
                'roles' => $roles,
                'looking_for' => $validated['looking_for'] ?? null,
                'introduction' => $validated['introduction'] ?? null,
                'member_count' => 5,
            ]);
        });

        if ($validated['recruitment_goal'] === 'build_a_team') {
            $teamName = $validated['team_name'] ?? 'Unknown';
            $this->logActivity(
                $studentId,
                'post_created',
                'Created a recruitment post to build team <strong>'.e($teamName).'</strong>',
                ['recruitment_id' => $recruitment->id, 'team_name' => $teamName, 'goal' => 'build_a_team']
            );
        } else {
            $this->logActivity(
                $studentId,
                'post_created',
                'Created a recruitment post looking for a team',
                ['recruitment_id' => $recruitment->id, 'goal' => 'find_a_team']
            );
        }

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

    public function update(Request $request, Recruitment $recruitment): JsonResponse|RedirectResponse
    {
        $student = $request->user();

        if ($recruitment->student_id !== $student->student_id) {
            abort(403);
        }

        $validated = $request->validate([
            'recruitment_goal' => ['required', Rule::in(['build_a_team', 'find_a_team'])],
            'looking_for' => ['nullable', 'string', 'max:500'],
            'introduction' => ['nullable', 'string', 'max:500'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'max:50'],
        ]);

        $recruitment->update([
            'recruitment_goal' => $validated['recruitment_goal'],
            'roles' => $validated['roles'],
            'looking_for' => $validated['looking_for'] ?? null,
            'introduction' => $validated['introduction'] ?? null,
            'member_count' => 5,
        ]);

        $this->logActivity(
            $student->student_id,
            'post_updated',
            'Updated recruitment post',
            ['recruitment_id' => $recruitment->id, 'goal' => $validated['recruitment_goal']]
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Recruitment post updated.',
                'recruitment' => $recruitment,
            ]);
        }

        return redirect()
            ->back()
            ->with('status', 'Recruitment post updated!');
    }

    public function destroy(Request $request, Recruitment $recruitment): RedirectResponse
    {
        $student = $request->user();
        $studentId = $student ? $student->student_id : 8;

        if ($recruitment->student_id !== $studentId) {
            abort(403);
        }

        $recruitment->delete();

        $this->logActivity(
            $studentId,
            'post_deleted',
            'Deleted recruitment post',
            ['recruitment_id' => $recruitment->id]
        );

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
