<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RecipientResolver
{
    /**
     * Resolve the expert_id filling a given role (adviser/consultant/grammarian)
     * for a specific team, via the accepted application + their role record.
     */
    public function resolveTeamRoleExpert(int $teamId, string $role): ?int
    {
        $roleNameMap = [
            'adviser' => 'Adviser',
            'consultant' => 'Consultant',
            'grammarian' => 'Grammarian',
        ];

        if (! isset($roleNameMap[$role])) {
            return null;
        }

        $row = DB::table('capstone_expert_application as a')
            ->join('expert_roles as er', 'er.expert_id', '=', 'a.expert_id')
            ->join('roles as r', 'r.role_id', '=', 'er.role_id')
            ->where('a.team_id', $teamId)
            ->where('a.applicatin_status', 'accepted')
            ->where('r.role_name', $roleNameMap[$role])
            ->select('a.expert_id')
            ->first();

        return $row->expert_id ?? null;
    }

    /**
     * Resolve the admin acting as Subject Adviser for a given program
     * (e.g. BSIT, BSCS, BLIS, BSIS), matched via capstone_experts.department.
     */
    public function resolveSubjectAdviserAdmin(string $program): ?int
    {
        $row = DB::table('capstone_experts as e')
            ->join('expert_roles as er', 'er.expert_id', '=', 'e.expert_id')
            ->join('roles as r', 'r.role_id', '=', 'er.role_id')
            ->where('e.is_admin', 1)
            ->where('e.department', $program)
            ->where('r.role_name', 'Capstone Project Subject Adviser')
            ->select('e.expert_id')
            ->first();

        return $row->expert_id ?? null;
    }

    /**
     * Convenience: resolve any of the four recipient roles in one call.
     */
    public function resolve(string $role, int $teamId, string $program): ?int
    {
        if ($role === 'subject_adviser') {
            return $this->resolveSubjectAdviserAdmin($program);
        }

        return $this->resolveTeamRoleExpert($teamId, $role);
    }
}
