<?php

namespace App\Support;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\TeamExpertAssignment;

/**
 * Teams against expert slots, per program and role: is there room for every
 * team that still needs an adviser, consultant or grammarian?
 *
 * The panel asked for this cross-check to be automatic. Teams still pick
 * their own experts - this only counts, so the CaPSA/TSA hears about a shortage
 * before the teams run into full experts one by one.
 *
 * A team "needs" a role until it has an approved expert in it: a pending
 * application has not used a slot yet (only approved ones do, see
 * ExpertApplications::fullAt), so it still needs one to be free.
 *
 * Free slots follow the same rules as applying: the expert is approved, not
 * on study leave, has set which programs they take, accepts this one, and
 * has room under both their program cap and their overall cap. An expert
 * with no cap at all has no limit, and the free count reads as null.
 */
final class ExpertCapacity
{
    public const ROLES = ['adviser' => 'Adviser', 'consultant' => 'Consultant', 'grammarian' => 'Grammarian'];

    public const OK = 'OK';

    public const JUST_ENOUGH = 'Just enough';

    public const SHORT = 'Short';

    /**
     * One row per role, then an "All roles" row: an expert's slots are shared
     * by every role they hold, so the per-role free counts overlap and can all
     * look fine while the experts together are still short.
     *
     * @param  list<string>  $programs
     * @return array<string, list<array{role: string, label: string, need: int, free: int|null, short: int, status: string}>>
     */
    public function forPrograms(array $programs): array
    {
        if (! $programs) {
            return [];
        }

        $teamPrograms = $this->teamPrograms();
        $filled = $this->filledRoles(array_keys($teamPrograms));
        $experts = $this->openExperts();

        $result = [];

        foreach ($programs as $program) {
            $teams = array_keys(array_filter($teamPrograms, fn ($p) => $p === $program));
            $accepting = array_filter($experts, fn ($e) => $e['expert']->acceptsProgram($program));
            $rows = [];

            foreach (self::ROLES as $role => $label) {
                $need = count(array_filter($teams, fn ($teamId) => ! in_array($role, $filled[$teamId] ?? [], true)));
                $holders = array_filter($accepting, fn ($e) => in_array($role, $e['roles'], true));
                $rows[] = $this->row($role, $label, $need, $this->freeSlots($holders, $program));
            }

            $rows[] = $this->row('all', 'All roles', array_sum(array_column($rows, 'need')), $this->freeSlots($accepting, $program));

            $result[$program] = $rows;
        }

        return $result;
    }

    /**
     * This term's teams in these programs, each with the roles it still needs -
     * the same "needs" forPrograms() counts, team by team.
     *
     * @param  list<string>  $programs
     * @return array<int, array{program: string, missing: list<string>}>
     */
    public function teamsMissing(array $programs): array
    {
        $teamPrograms = array_filter($this->teamPrograms(), fn ($p) => in_array($p, $programs, true));
        $filled = $this->filledRoles(array_keys($teamPrograms));
        $out = [];

        foreach ($teamPrograms as $teamId => $program) {
            $out[$teamId] = ['program' => $program, 'missing' => array_values(array_diff(array_keys(self::ROLES), $filled[$teamId] ?? []))];
        }

        return $out;
    }

    /**
     * The shortages in plain words, one line each - what the CaPSA/TSA's notice says.
     *
     * @param  array<string, list<array{role: string, label: string, need: int, free: int|null, short: int, status: string}>>  $capacity
     * @return list<string>
     */
    public static function shortages(array $capacity): array
    {
        $lines = [];

        foreach ($capacity as $program => $rows) {
            foreach ($rows as $row) {
                if ($row['short'] < 1) {
                    continue;
                }

                $slots = $row['short'] === 1 ? 'slot' : 'slots';
                $lines[] = $row['role'] === 'all'
                    ? "{$program} needs {$row['short']} more expert {$slots} in all (some experts hold more than one role)."
                    : "{$program} needs {$row['short']} more ".strtolower($row['label'])." {$slots}.";
            }
        }

        return $lines;
    }

    /** @return array{role: string, label: string, need: int, free: int|null, short: int, status: string} */
    private function row(string $role, string $label, int $need, ?int $free): array
    {
        $short = $free === null ? 0 : max(0, $need - $free);

        return [
            'role' => $role,
            'label' => $label,
            'need' => $need,
            'free' => $free,
            'short' => $short,
            'status' => $short > 0 ? self::SHORT : ($need > 0 && $free === $need ? self::JUST_ENOUGH : self::OK),
        ];
    }

    /**
     * This term's teams and the program each is in - from the enrolment row,
     * or a member's course when the row does not say, as
     * CapstoneExpert::approvedTeamCountsByProgram() reads it.
     *
     * @return array<int, string>
     */
    private function teamPrograms(): array
    {
        return StudentSchoolYear::query()
            ->whereNotNull('team_id')
            ->whereHas('team')
            ->with('student:student_id,course')
            ->get(['team_id', 'student_id', 'program'])
            ->groupBy('team_id')
            ->map(function ($rows) {
                foreach ($rows as $row) {
                    $program = $row->program ?: Student::mapCourseToProgram((string) ($row->student?->course ?? ''));

                    if ($program) {
                        return $program;
                    }
                }

                return null;
            })
            ->filter()
            ->all();
    }

    /**
     * The roles each team already has an approved expert in.
     *
     * @param  list<int>  $teamIds
     * @return array<int, list<string>>
     */
    private function filledRoles(array $teamIds): array
    {
        return TeamExpertAssignment::with('expertRole.role')
            ->whereIn('team_id', $teamIds)
            ->where('status', 'approved')
            ->get()
            ->groupBy('team_id')
            ->map(fn ($assignments) => $assignments->map(fn ($a) => strtolower(trim((string) $a->engaged_role_name)))->filter()->unique()->values()->all())
            ->all();
    }

    /**
     * Experts a team could apply to today, with what they have already taken.
     *
     * @return list<array{expert: CapstoneExpert, roles: list<string>, used: int, byProgram: array<string, int>}>
     */
    private function openExperts(): array
    {
        $used = TeamExpertAssignment::where('status', 'approved')
            ->selectRaw('expert_id, count(*) as n')->groupBy('expert_id')->pluck('n', 'expert_id');

        return CapstoneExpert::where('approved_status', true)
            ->where(fn ($q) => $q->where('on_study_leave', false)->orWhereNull('on_study_leave'))
            ->with('roles')
            ->get()
            ->filter(fn (CapstoneExpert $e) => $e->hasSetPrograms())
            ->map(fn (CapstoneExpert $e) => [
                'expert' => $e,
                'roles' => $e->roles->pluck('role_name')->intersect(array_keys(self::ROLES))->values()->all(),
                'used' => (int) ($used[$e->expert_id] ?? 0),
                'byProgram' => $e->approvedTeamCountsByProgram(),
            ])
            ->filter(fn ($e) => $e['roles'] !== [])
            ->values()
            ->all();
    }

    /**
     * Room left across these experts for one more team from $program, or null
     * when any of them has no limit. Same two caps as ExpertApplications::fullAt.
     *
     * @param  array<int, array{expert: CapstoneExpert, roles: list<string>, used: int, byProgram: array<string, int>}>  $experts
     */
    private function freeSlots(array $experts, string $program): ?int
    {
        $free = 0;

        foreach ($experts as $e) {
            $left = [];
            $programCap = $e['expert']->slotsForProgram($program);

            if ($programCap !== null) {
                $left[] = $programCap - ($e['byProgram'][$program] ?? 0);
            }

            $maxTeams = $e['expert']->max_teams ?? 0;

            if ($maxTeams > 0) {
                $left[] = $maxTeams - $e['used'];
            }

            if ($left === []) {
                return null;
            }

            $free += max(0, min($left));
        }

        return $free;
    }
}
