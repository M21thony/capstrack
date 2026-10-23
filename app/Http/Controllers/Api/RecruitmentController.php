<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecruitmentResource;
use App\Models\Recruitment;
use App\Models\StudentRole;
use App\Models\Team;
use App\Models\Term;
use App\Support\StudentTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RecruitmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();

        $fullTeamStudentIds = Recruitment::leadersOfFullTeams();

        $recruitments = Recruitment::with(['student.team'])
            ->where('student_id', '!=', $student->student_id)
            // Off the feed only once the team reaches the CaPSA's limit.
            ->whereNotIn('student_id', $fullTeamStudentIds)
            ->whereIn('student_id', function ($query) use ($student) {
                $query->select('student_s_y.student_id')
                    ->from('student_s_y')
                    ->join('students', 'student_s_y.student_id', '=', 'students.student_id')
                    ->where('students.course', $student->course)
                    ->where('students.year_section', $student->year_section);
            })
            ->latest()
            ->paginate(5);

        return RecruitmentResource::collection($recruitments)->response();
    }

    public function store(Request $request): JsonResponse
    {
        // Capstone 2: teams are settled, so there is nothing to recruit for
        // or form. Refused here as well as hidden on the page, and with a
        // reason - an empty list with no explanation reads as a broken page.
        if ($request->user()->capstoneStage() !== 1) {
            return response()->json([
                'message' => 'Capstone 2 has started. Teams keep the members they '
                    .'finished Capstone 1 with, so new teams and recruitment posts are closed.',
            ], 422);
        }
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

        if ($validated['recruitment_goal'] === 'build_a_team' && ! empty($validated['team_name'])
            && ! Team::creationAllowed($request->user()->sectionLabel())) {
            return response()->json(['message' => Team::CREATION_CLOSED], 422);
        }

        if ($validated['recruitment_goal'] === 'find_a_team' && ! Team::joiningAllowed($request->user()->sectionLabel())) {
            return response()->json(['message' => Team::JOINING_CLOSED], 422);
        }

        $student = $request->user();
        $studentId = $student->student_id;

        if (StudentTeam::has($studentId)) {
            return response()->json([
                'message' => 'You already have a team, so you cannot publish a recruitment post.',
            ], 422);
        }

        $tagsJson = $request->input('tags');
        $roles = [];
        if ($tagsJson) {
            $roles = json_decode($tagsJson, true) ?? [];
        }

        $recruitment = DB::transaction(function () use ($validated, $studentId, $roles): Recruitment {
            if ($validated['recruitment_goal'] === 'build_a_team' && ! empty($validated['team_name'])) {
                $team = Team::create([
                    'team_name' => $validated['team_name'],
                    'team_status' => 'Active',
                    'leader_id' => $studentId,
                ]);

                DB::table('student_s_y')
                    ->updateOrInsert(
                        // Keyed on the term too: this table is one row per student PER
                        // TERM, so keying on the student alone would overwrite last
                        // semester's record instead of starting this one.
                        ['student_id' => $studentId, 'term_id' => Term::activeId()],
                        ['team_id' => $team->team_id, 's_role_id' => StudentRole::leaderRoleId()]
                    );
            }

            return Recruitment::create([
                'student_id' => $studentId,
                'recruitment_goal' => $validated['recruitment_goal'],
                'roles' => $roles,
                'looking_for' => $validated['looking_for'] ?? null,
                'introduction' => $validated['introduction'] ?? null,
                'member_count' => Team::maxMembers($request->user()->sectionLabel()),
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

        return response()->json([
            'message' => 'Recruitment post published.',
            'recruitment' => new RecruitmentResource($recruitment->load('student')),
        ], 201);
    }

    public function update(Request $request, Recruitment $recruitment): JsonResponse
    {
        $student = $request->user();

        if ($recruitment->student_id !== $student->student_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
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
            'member_count' => Team::maxMembers($request->user()->sectionLabel()),
        ]);

        $this->logActivity(
            $student->student_id,
            'post_updated',
            'Updated recruitment post',
            ['recruitment_id' => $recruitment->id, 'goal' => $validated['recruitment_goal']]
        );

        return response()->json([
            'message' => 'Recruitment post updated.',
            'recruitment' => new RecruitmentResource($recruitment->load('student')),
        ]);
    }

    public function destroy(Request $request, Recruitment $recruitment): JsonResponse
    {
        $student = $request->user();

        if ($recruitment->student_id !== $student->student_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $recruitment->delete();

        $this->logActivity(
            $student->student_id,
            'post_deleted',
            'Deleted recruitment post',
            ['recruitment_id' => $recruitment->id]
        );

        return response()->json(['message' => 'Recruitment post removed.']);
    }
}
