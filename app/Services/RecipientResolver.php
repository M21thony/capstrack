<?php

namespace App\Services;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\SectionScope;
use Illuminate\Support\Facades\DB;

class RecipientResolver
{
    /** The three roles a team engages an expert as. */
    private const TEAM_ROLES = ['adviser', 'consultant', 'grammarian'];

    /**
     * The expert a team engaged as adviser, consultant or grammarian.
     *
     * Keyed off the assignment's own expert_role_id, which records the role
     * the team engaged them as. It used to join expert_roles by expert_id
     * and match on the role's name, which asks a different question: not
     * "what did this team engage them as" but "what roles does this person
     * hold anywhere". Somebody approved as a team's adviser who also holds
     * the consultant role - which is ordinary, most experts do both - came
     * back as that team's consultant too, and the submission page offered to
     * send them the document twice over.
     */
    public function resolveTeamRoleExpert(int $teamId, string $role): ?int
    {
        if (! in_array($role, self::TEAM_ROLES, true)) {
            return null;
        }

        $row = DB::table('capstone_expert_application as a')
            ->join('expert_roles as er', 'er.expert_role_id', '=', 'a.expert_role_id')
            ->join('roles as r', 'r.role_id', '=', 'er.role_id')
            ->where('a.team_id', $teamId)
            ->where('a.status', 'approved')
            ->whereRaw('LOWER(r.role_name) = ?', [$role])
            ->select('a.expert_id')
            ->first();

        return $row->expert_id ?? null;
    }

    /**
     * Resolve the admin acting as Subject Adviser for a given program
     * (e.g. BSIT, BSCS, BLIS, BSIS), matched via capstone_experts.progrtment.
     */
    public function resolveSubjectAdviserAdmin(?int $teamId): ?int
    {
        return $this->subjectAdviser($teamId)?->expert_id;
    }

    /**
     * The CaPSA responsible for this team, found by section.
     *
     * It used to match capstone_experts.program against student_s_y.program.
     * Neither column is filled by anything: a CaPSA is given sections when
     * they are named, and a student's programme lives on students.course. So
     * both sides were NULL, and "WHERE program = NULL" is never true in SQL -
     * which is why the Subject Adviser was permanently "Not yet assigned"
     * without anything ever erroring.
     *
     * Sections are how the rest of the system decides which CaPSA owns a
     * team, so this asks SectionScope rather than inventing a second answer.
     */
    private function subjectAdviser(?int $teamId): ?CapstoneExpert
    {
        if (! $teamId) {
            return null;
        }

        $team = Team::find($teamId);

        if (! $team) {
            return null;
        }

        $section = app(SectionScope::class)->sectionFor($team);

        if ($section === '') {
            return null;
        }

        return CapstoneExpert::query()
            ->whereHas('roles', fn ($q) => $q->where('role_name', CapstoneExpert::CAPSA_ROLE))
            ->get()
            ->first(fn ($capsa) => in_array($section, (array) $capsa->sections, true));
    }

    /**
     * Convenience: resolve any of the four recipient roles in one call.
     */
    public function resolve(string $role, int $teamId, string $program = ''): ?int
    {
        if ($role === 'subject_adviser') {
            return $this->resolveSubjectAdviserAdmin($teamId);
        }

        return $this->resolveTeamRoleExpert($teamId, $role);
    }

    /**
     * Resolve expert_id + display name for all four recipient roles
     * in one pass, for populating the "Send To" UI dynamically.
     *
     * Returns:
     * [
     *   'adviser'         => ['expert_id' => int|null, 'name' => string|null],
     *   'consultant'      => [...],
     *   'grammarian'      => [...],
     *   'subject_adviser' => [...],
     * ]
     */
    public function getAssignedNames(int $teamId, string $program = ''): array
    {
        $result = [];

        foreach (self::TEAM_ROLES as $role) {
            $expertId = $this->resolveTeamRoleExpert($teamId, $role);
            $expert = $expertId ? CapstoneExpert::find($expertId) : null;

            $result[$role] = [
                'expert_id' => $expert?->expert_id,
                'name' => $expert?->name ?: null,
            ];
        }

        $capsa = $this->subjectAdviser($teamId);

        $result['subject_adviser'] = [
            'expert_id' => $capsa?->expert_id,
            'name' => $capsa?->name ?: null,
        ];

        return $result;
    }
}