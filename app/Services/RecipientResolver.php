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
            ->where('a.status', 'approved')
            ->where('r.role_name', $roleNameMap[$role])
            ->select('a.expert_id')
            ->first();

        return $row->expert_id ?? null;
    }

    /**
     * Resolve the admin acting as Subject Adviser for a given program
     * (e.g. BSIT, BSCS, BLIS, BSIS), matched via capstone_experts.progrtment.
     */
    public function resolveSubjectAdviserAdmin(string $program): ?int
    {
        // Subject Adviser is no longer synonymous with admin rights - it's
        // its own role (capstone_adviser), assignable to any expert, so
        // this no longer requires e.is_admin.
        $row = DB::table('capstone_experts as e')
            ->join('expert_roles as er', 'er.expert_id', '=', 'e.expert_id')
            ->join('roles as r', 'r.role_id', '=', 'er.role_id')
            ->where('e.program', $program)
            ->where('r.role_name', 'capstone_adviser')
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
    public function getAssignedNames(int $teamId, string $program): array
    {
        $result = [];

        $teamRoles = [
            'adviser' => 'Adviser',
            'consultant' => 'Consultant',
            'grammarian' => 'Grammarian',
        ];

        foreach ($teamRoles as $role => $roleName) {
            $row = DB::table('capstone_expert_application as a')
                ->join('expert_roles as er', 'er.expert_id', '=', 'a.expert_id')
                ->join('roles as r', 'r.role_id', '=', 'er.role_id')
                ->join('capstone_experts as e', 'e.expert_id', '=', 'a.expert_id')
                ->where('a.team_id', $teamId)
                ->where('a.status', 'approved')
                ->where('r.role_name', $roleName)
                ->select('e.expert_id', 'e.first_name', 'e.last_name')
                ->first();

            $result[$role] = [
                'expert_id' => $row->expert_id ?? null,
                'name' => $row ? trim("{$row->first_name} {$row->last_name}") : null,
            ];
        }

        // Subject adviser — resolved by program/department, not team. No
        // longer requires e.is_admin (see resolveSubjectAdviserAdmin()).
        $adminRow = DB::table('capstone_experts as e')
            ->join('expert_roles as er', 'er.expert_id', '=', 'e.expert_id')
            ->join('roles as r', 'r.role_id', '=', 'er.role_id')
            ->where('e.program', $program)
            ->where('r.role_name', 'capstone_adviser')
            ->select('e.expert_id', 'e.first_name', 'e.last_name')
            ->first();

        $result['subject_adviser'] = [
            'expert_id' => $adminRow->expert_id ?? null,
            'name' => $adminRow ? trim("{$adminRow->first_name} {$adminRow->last_name}") : null,
        ];

        return $result;
    }
}