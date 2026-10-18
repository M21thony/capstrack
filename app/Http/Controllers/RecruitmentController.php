<?php

namespace App\Http\Controllers;

use App\Models\Recruitment;
use App\Models\StudentRole;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\Term;
use App\Support\SkillCatalog;
use App\Support\StudentTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

        // Capstone 2: teams are settled, so there is nothing to recruit for.
        if ($student && $student->capstoneStage() !== 1) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => Team::NO_NEW_TEAMS], 422);
            }

            return back()->withErrors(['error' => Team::NO_NEW_TEAMS]);
        }

        // Prevent posting if the student already belongs to a team
        if (StudentTeam::has($studentId)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a team, so you cannot publish a recruitment post.',
                ], 422);
            }

            return back()->withErrors(['error' => 'You already have a team, so you cannot publish a recruitment post.']);
        }

        // What the post was tagged with: Create Post's six choices (Leader,
        // Member, and Primary or Secondary of each member role). Anything else
        // is dropped - it could never be assigned to whoever joins.
        $tags = self::pickedTags($request);

        // The team roles behind those choices, which the join flow matches on.
        // The team roles in the order the student picked them.
        $roles = array_values(array_intersect(Recruitment::rolesForTags(self::pickedTags($request, true)), self::advertisableRoles()));

        if ($tags === []) {
            $message = 'Choose at least one role.';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return back()->withErrors(['roles' => $message]);
        }

        // A build-a-team post with a name creates the team as it posts - team
        // creation by a second door - so it closes with the first.
        if ($validated['recruitment_goal'] === 'build_a_team' && ! empty($validated['team_name'])
            && ! Team::creationAllowed($student?->sectionLabel())) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => Team::CREATION_CLOSED], 422);
            }

            return back()->withErrors(['error' => Team::CREATION_CLOSED]);
        }

        // Looking for a team is the way in to joining one, so it closes with joining.
        if ($validated['recruitment_goal'] === 'find_a_team' && ! Team::joiningAllowed($student?->sectionLabel())) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => Team::JOINING_CLOSED], 422);
            }

            return back()->withErrors(['error' => Team::JOINING_CLOSED]);
        }

        $skills = self::pickedSkills($request);

        $recruitment = DB::transaction(function () use ($validated, $studentId, $roles, $tags, $skills): Recruitment {
            // If goal is build_a_team and team_name is provided, create team and link it
            if ($validated['recruitment_goal'] === 'build_a_team' && ! empty($validated['team_name'])) {
                $team = Team::create([
                    'team_name' => $validated['team_name'],
                    'team_status' => 'Active',
                    'leader_id' => $studentId,
                ]);

                // Through the model, not a raw upsert: the creating hook
                // stamps the row with its term, school year and program -
                // the raw write left program null, and a team whose
                // enrolment rows name no program counts for none of them on
                // the expert's capacity bars.
                StudentSchoolYear::updateOrCreate(
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
                'role_tags' => $tags,
                'skills' => $skills,
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

    /**
     * What the form tagged the post with, as Recruitment::ROLE_OPTIONS keys.
     * Create Post sends them as a JSON list in `tags`; an edit or an older
     * client may send `roles` as a list instead. See Recruitment::tagKeys().
     *
     * @return list<string>
     */
    private static function pickedTags(Request $request, bool $asPicked = false): array
    {
        $raw = $request->input('tags');
        $picked = is_string($raw) ? json_decode($raw, true) : $raw;

        if (! is_array($picked) || $picked === []) {
            $picked = (array) $request->input('roles', []);
        }

        return Recruitment::tagKeys($picked, $asPicked);
    }

    /**
     * The skills the post needs, sent as a JSON list like `tags`. Only
     * catalogue skills are kept, in catalogue order, so the feed's skill
     * filter never meets a spelling it does not know.
     *
     * @return list<string>
     */
    private static function pickedSkills(Request $request): array
    {
        $raw = $request->input('skills');
        $picked = is_string($raw) ? json_decode($raw, true) : $raw;

        return array_values(array_intersect(SkillCatalog::allSkills(), array_filter((array) $picked, 'is_string')));
    }

    /**
     * The roles a recruitment post may ask for.
     *
     * The member roles, straight out of student_roles. Project Head is
     * excluded: that seat is the leader's and is never advertised.
     *
     * @return array<int, string>
     */
    private static function advertisableRoles(): array
    {
        return StudentRole::memberRoles()->pluck('s_role_name')->all();
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
            'tags' => ['nullable'],
            'roles' => ['nullable', 'array'],
        ]);

        $tags = self::pickedTags($request);

        if ($tags === []) {
            throw ValidationException::withMessages(['roles' => 'Choose at least one role.']);
        }

        $recruitment->update([
            'recruitment_goal' => $validated['recruitment_goal'],
            'roles' => array_values(array_intersect(Recruitment::rolesForTags(self::pickedTags($request, true)), self::advertisableRoles())),
            'role_tags' => $tags,
            'skills' => $request->has('skills') ? self::pickedSkills($request) : $recruitment->skills,
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
