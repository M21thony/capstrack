<?php

namespace App\Support;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;

/**
 * A team applying to an expert: whether it may, and sending it.
 *
 * Asked by Apply on the Experts page and by an approved change request,
 * which applies to the replacement the team picked. Both go through the same
 * checks, so a replacement who filled up while the CaPSA/TSA was deciding is
 * refused just as a direct application would be.
 */
class ExpertApplications
{
    /**
     * Why this team may not apply to this expert in this role, or null when
     * it may.
     *
     * @param  int|null  $replacing  an approved assignment the application is
     *                               about to take over from, which is left out of "this role is taken"
     * @return array{message: string, status: int}|null
     */
    public function refusal(Team $team, Student $leader, CapstoneExpert $expert, int $expertRoleId, ?int $replacing = null): ?array
    {
        if (! $expert->approved_status || $expert->trashed()) {
            return ['message' => 'Expert not found or not yet approved.', 'status' => 404];
        }

        // Nobody is engaged before they have said what they take. An expert
        // who has never filled in their programmes and slots is still listed
        // - hiding them helps nobody - but a team cannot apply to them,
        // because there is no answer yet to whether they have room.
        if (! $expert->hasSetPrograms()) {
            return ['message' => 'This expert has not set which programs they take teams from yet, so they cannot be applied to.', 'status' => 422];
        }

        $program = Student::mapCourseToProgram($leader->course ?? '');

        if (! $expert->acceptsProgram($program)) {
            return ['message' => 'This expert does not accept teams from your program.', 'status' => 422];
        }

        $programCap = $expert->slotsForProgram($program);

        if ($programCap !== null && $programCap < 1) {
            return ['message' => 'This expert does not accept teams from your program.', 'status' => 422];
        }

        if ($full = $this->fullAt($expert, $program)) {
            return ['message' => $full['program']
                ? "This expert has already reached their limit of {$full['cap']} {$full['program']} team(s)."
                : 'This expert has reached their maximum number of teams.',
                'status' => 422];
        }

        // The chosen role has to be one this expert actually holds, and one a
        // team may engage them for.
        $roleName = $this->roleName($expert, $expertRoleId);

        if (! $roleName) {
            return ['message' => 'That role is not available for this expert.', 'status' => 422];
        }

        // One application per role per team, waiting or settled.
        $spokenFor = TeamExpertAssignment::with(['expertRole.role', 'expert'])
            ->where('team_id', $team->team_id)
            ->whereIn('status', ['approved', 'pending'])
            ->when($replacing, fn ($q) => $q->where('assignment_id', '!=', $replacing))
            // Approved before pending, so a role that is both reads as the
            // settled thing it is.
            ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")
            ->get()
            ->first(fn ($a) => strcasecmp((string) $a->engaged_role_name, $roleName) === 0);

        if ($spokenFor) {
            $heldByName = $spokenFor->expert?->name ?? 'another expert';
            $role = ucfirst($roleName);

            return ['message' => $spokenFor->status === 'approved'
                ? 'Your team already has an approved '.$role.' ('.$heldByName.'). Remove them first if you want a different one.'
                : 'Your team is already waiting on '.$heldByName.' to answer as your '.$role.'. Wait for that answer, or withdraw it, before applying to another.',
                'status' => 409];
        }

        // One role per expert per team: engaging someone as your adviser
        // takes them off the table as your consultant or grammarian.
        $existing = TeamExpertAssignment::where('team_id', $team->team_id)
            ->where('expert_id', $expert->expert_id)
            ->whereIn('status', ['pending', 'approved'])
            ->when($replacing, fn ($q) => $q->where('assignment_id', '!=', $replacing))
            ->first();

        if ($existing) {
            $heldAs = $existing->engaged_role_name;

            return ['message' => $heldAs
                ? "Your team already has a {$existing->status} application with this expert as your ".ucfirst($heldAs).'. An expert can only take one role per team.'
                : 'Your team already has a pending or approved application with this expert.',
                'status' => 409];
        }

        return null;
    }

    /**
     * The cap one more team from this program would break, or null when the
     * expert has room. Asked when a team applies and again when the expert
     * approves, since several applications can wait at once and each fits
     * on its own.
     *
     * Per-program cap first, when the expert set one: "1 - BSIT, 5 - BSIS"
     * has to mean one BSIT team, not just six teams in total. The overall cap
     * still applies on top. A capacity nobody has set is not a capacity of
     * nought: an expert who has not opened their profile yet would otherwise
     * be full from the moment their account was made.
     *
     * @return array{cap: int, program: string|null}|null
     */
    public function fullAt(CapstoneExpert $expert, ?string $program): ?array
    {
        $programCap = $expert->slotsForProgram($program);

        if ($programCap !== null && $expert->approvedTeamCountForProgram($program) >= $programCap) {
            return ['cap' => $programCap, 'program' => $program];
        }

        $maxTeams = $expert->max_teams ?? 0;
        $slotsUsed = TeamExpertAssignment::where('expert_id', $expert->expert_id)->where('status', 'approved')->count();

        return $maxTeams > 0 && $slotsUsed >= $maxTeams ? ['cap' => $maxTeams, 'program' => null] : null;
    }

    /** The role an expert_roles row stands for, when it is this expert's and one a team may engage. */
    public function roleName(CapstoneExpert $expert, int $expertRoleId): ?string
    {
        return DB::table('expert_roles')
            ->join('roles', 'roles.role_id', '=', 'expert_roles.role_id')
            ->where('expert_roles.expert_role_id', $expertRoleId)
            ->where('expert_roles.expert_id', $expert->expert_id)
            ->whereIn('roles.role_name', CapstoneExpert::STUDENT_FACING_ROLES)
            ->value('roles.role_name');
    }

    /** Send the application, and tell the expert. Checks are refusal()'s job. */
    public function send(Team $team, CapstoneExpert $expert, int $expertRoleId, string $message, ?string $proposalFile, ?int $preProposalId): TeamExpertAssignment
    {
        $assignment = TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $expertRoleId,
            'status' => 'pending',
            'proposal_file' => $proposalFile,
            'pre_proposal_id' => $preProposalId,
            'message' => $message,
        ]);

        $role = $assignment->engaged_role_name;
        Notifier::toExpert(
            (int) $assignment->expert_id,
            'expert_application',
            'New application',
            '<strong>'.e($team->team_name).'</strong> applied to you'.($role ? ' as their '.e($role) : '').'.',
            route('instructor.teams', ['tab' => 'applications']),
            ['team_id' => $team->team_id],
        );

        return $assignment;
    }
}
