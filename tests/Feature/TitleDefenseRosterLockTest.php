<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefenseSchedule;
use App\Models\LeaveApproval;
use App\Models\Notification;
use App\Models\Recruitment;
use App\Models\Role;
use App\Models\RosterChangeRequest;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Services\DefenseOutcomeRecorder;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Passing Title Defense locks a team's members.
 *
 * Nobody joins after it, and nobody leaves on their own or the leader's
 * say-so - a member who has to go asks the section's CaPSA, with a reason.
 */
class TitleDefenseRosterLockTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $email, string $year = '3rd · A'): Student
    {
        $s = Student::create([
            'email' => $email, 'f_name' => ucfirst(strtok($email, '@')), 'l_name' => 'Tester',
            'course' => 'BSIT', 'year_section' => $year,
        ]);
        StudentSchoolYear::create(['student_id' => $s->student_id]);

        return $s;
    }

    /** @return array{0: Team, 1: Student, 2: Student, 3: Student} team, leader, two members */
    private function team(string $year = '3rd · A'): array
    {
        $leader = $this->student('leader@my.cspc.edu.ph', $year);
        $a = $this->student('ana@my.cspc.edu.ph', $year);
        $b = $this->student('ben@my.cspc.edu.ph', $year);

        $team = Team::create(['team_name' => 'Lockers', 'leader_id' => $leader->student_id, 'team_status' => 'Active', 'is_confirmed' => true]);
        StudentSchoolYear::whereIn('student_id', [$leader->student_id, $a->student_id, $b->student_id])
            ->update(['team_id' => $team->team_id]);

        return [$team, $leader, $a, $b];
    }

    private function titleDefense(Team $team, ?string $outcome, int $attempt = 1): DefenseSchedule
    {
        return DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => DefenseOutcome::TITLE_DEFENSE,
            'attempt' => $attempt,
            'outcome' => $outcome,
            'outcome_recorded_at' => $outcome ? now() : null,
        ]);
    }

    /** @return array{0: Team, 1: Student, 2: Student, 3: Student} */
    private function lockedTeam(): array
    {
        $made = $this->team();
        $this->titleDefense($made[0], DefenseOutcome::PASSED);

        return $made;
    }

    private function capsa(array $sections = ['BSIT-3A']): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'sections' => $sections]);
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        return $capsa;
    }

    private function inTeam(Student $s, Team $team): bool
    {
        return StudentSchoolYear::where('student_id', $s->student_id)->where('team_id', $team->team_id)->exists();
    }

    private function askToLeave(Student $member, string $reason = 'My family is moving to Cebu next month.')
    {
        return $this->actingAs($member, 'web')->postJson(route('team.roster-requests.store'), [
            'type' => RosterChangeRequest::LEAVE, 'reason' => $reason,
        ]);
    }

    // ── the lock itself ──────────────────────────────────────────────────

    public function test_passing_title_defense_locks_the_team_and_closes_what_was_open(): void
    {
        [$team, $leader, $a] = $this->team();
        $outsider = $this->student('out@my.cspc.edu.ph');
        $asker = $this->student('asker@my.cspc.edu.ph');

        $invite = TeamInvitation::create(['sender_id' => $leader->student_id, 'receiver_id' => $outsider->student_id, 'team_id' => $team->team_id, 'type' => 'invite', 'status' => 'pending']);
        $request = TeamInvitation::create(['sender_id' => $asker->student_id, 'receiver_id' => $leader->student_id, 'team_id' => null, 'type' => 'request', 'status' => 'pending']);
        Recruitment::create(['student_id' => $leader->student_id, 'recruitment_goal' => 'build_a_team', 'roles' => ['Programmer']]);
        $leave = LeaveApproval::create(['student_id' => $a->student_id, 'team_id' => $team->team_id, 'attempt' => 1, 'status' => LeaveApproval::STATUS_PENDING, 'expires_at' => now()->addHour()]);

        $chair = CapstoneExpert::factory()->create(['approved_status' => true]);
        $schedule = $this->titleDefense($team, null);

        (new DefenseOutcomeRecorder)->record($schedule, DefenseOutcome::PASSED, $chair);

        $team = $team->fresh();
        $this->assertTrue($team->isRosterLocked());
        $this->assertSame(2, $team->capstoneStage());
        $this->assertNotNull($team->rosterLockedAt());

        $this->assertSame('declined', $invite->fresh()->status);
        $this->assertSame('declined', $request->fresh()->status);
        $this->assertSame(0, Recruitment::count());
        $this->assertSame(LeaveApproval::STATUS_CANCELLED, $leave->fresh()->status);

        // Everyone it touched is told, not left to find out.
        $this->assertTrue(Notification::where('student_id', $outsider->student_id)->where('type', 'team_roster_locked')->exists());
        $this->assertTrue(Notification::where('student_id', $asker->student_id)->where('type', 'team_roster_locked')->exists());
        $this->assertSame(3, Notification::where('type', 'team_roster_locked')->where('title', 'Team members locked')->count());
    }

    public function test_a_redefense_does_not_lock_the_team(): void
    {
        [$team] = $this->team();
        $chair = CapstoneExpert::factory()->create(['approved_status' => true]);

        (new DefenseOutcomeRecorder)->record($this->titleDefense($team, null), DefenseOutcome::REDEFENSE, $chair);

        $this->assertFalse($team->fresh()->isRosterLocked());
        $this->assertTrue($team->fresh()->mayAddMembers());
    }

    /** Passing a different stage is not the end of Capstone 1. */
    public function test_passing_the_pre_proposal_defense_does_not_lock_the_team(): void
    {
        [$team] = $this->team();
        DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => DefenseOutcome::PRE_PROPOSAL, 'attempt' => 1, 'outcome' => DefenseOutcome::PASSED]);

        $this->assertFalse($team->fresh()->isRosterLocked());
    }

    /** Listed as 4th year, but still defending the title: the members are not settled yet. */
    public function test_a_team_still_on_title_defense_stays_open_whatever_year_it_is_listed_as(): void
    {
        [$team] = $this->team('4th · A');
        $this->assertSame(2, $team->fresh()->capstoneStage(), 'never scheduled: the year decides');

        $this->titleDefense($team, DefenseOutcome::REDEFENSE);
        $this->titleDefense($team, null, 2);

        $this->assertSame(1, $team->fresh()->capstoneStage());
        $this->assertTrue($team->fresh()->mayAddMembers());
    }

    // ── every door is closed ─────────────────────────────────────────────

    public function test_no_one_can_be_added_to_a_locked_team(): void
    {
        [$team, $leader] = $this->lockedTeam();

        $this->actingAs($leader, 'web')
            ->postJson(route('invitations.store'), [
                'receiver_id' => $this->student('late@my.cspc.edu.ph')->student_id,
                'team_id' => $team->team_id, 'type' => 'invite',
            ])
            ->assertStatus(422)
            ->assertJson(['message' => Team::LOCKED_NO_JOINING]);
    }

    public function test_a_member_cannot_just_leave_a_locked_team(): void
    {
        [$team, , $a] = $this->lockedTeam();

        $this->actingAs($a, 'web')->delete(route('team.destroy'))->assertSessionHas('error', Team::ROSTER_LOCKED);
        $this->actingAs($a, 'web')->post(route('team.leave-request'))->assertSessionHas('error', Team::ROSTER_LOCKED);
        $this->actingAs($a, 'web')->deleteJson('/api/teams/'.$team->team_id)->assertStatus(422);

        $this->assertTrue($this->inTeam($a, $team));
        $this->assertSame(0, LeaveApproval::count());
    }

    public function test_the_leader_cannot_remove_a_member_of_a_locked_team(): void
    {
        [$team, $leader, $a] = $this->lockedTeam();

        $this->actingAs($leader, 'web')->delete(route('team.members.remove', $a))->assertSessionHas('error', Team::ROSTER_LOCKED);
        $this->actingAs($leader, 'web')->deleteJson('/api/teams/'.$team->team_id.'/members/'.$a->student_id)->assertStatus(422);

        $this->assertTrue($this->inTeam($a, $team));
    }

    /** A leave request from before the lock must not approve itself when its timer runs out. */
    public function test_an_old_leave_request_does_not_approve_itself_after_the_lock(): void
    {
        [$team, , $a, $b] = $this->lockedTeam();
        $old = LeaveApproval::create(['student_id' => $a->student_id, 'team_id' => $team->team_id, 'attempt' => 1, 'status' => LeaveApproval::STATUS_PENDING, 'expires_at' => now()->subHour()]);

        // Any leave action sweeps expired requests first.
        $this->actingAs($b, 'web')->post(route('team.leave-request'));

        $this->assertSame(LeaveApproval::STATUS_CANCELLED, $old->fresh()->status);
        $this->assertTrue($this->inTeam($a, $team));
    }

    // ── asking the CaPSA ─────────────────────────────────────────────────

    public function test_a_member_asks_their_capsa_to_leave(): void
    {
        [$team, $leader, $a] = $this->lockedTeam();

        $this->askToLeave($a)->assertCreated();

        $this->assertDatabaseHas('roster_change_requests', [
            'team_id' => $team->team_id, 'student_id' => $a->student_id, 'requested_by' => $a->student_id,
            'type' => 'leave', 'status' => 'pending',
        ]);
        $this->assertTrue($this->inTeam($a, $team), 'asking is not leaving');
        $this->assertTrue(Notification::where('student_id', $leader->student_id)->where('type', 'roster_request_sent')->exists());
    }

    public function test_a_request_needs_a_real_reason(): void
    {
        [, , $a] = $this->lockedTeam();

        $this->askToLeave($a, 'bye')->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->assertSame(0, RosterChangeRequest::count());
    }

    /** Before Title Defense, leaving still goes through the leader - this is not a way around them. */
    public function test_an_unlocked_team_cannot_use_roster_requests(): void
    {
        [, , $a] = $this->team();

        $this->askToLeave($a)->assertStatus(422);
        $this->assertSame(0, RosterChangeRequest::count());
    }

    public function test_the_leader_asks_to_remove_and_the_member_is_told(): void
    {
        [$team, $leader, $a] = $this->lockedTeam();

        $this->actingAs($leader, 'web')->postJson(route('team.roster-requests.store'), [
            'type' => 'remove', 'student_id' => $a->student_id, 'reason' => 'Has not attended a single meeting since June.',
        ])->assertCreated();

        $this->assertTrue(Notification::where('student_id', $a->student_id)->where('title', 'Removal requested')->exists());
    }

    public function test_who_may_ask_for_what(): void
    {
        [, $leader, $a, $b] = $this->lockedTeam();
        $reason = 'A reason long enough to decide on.';

        // A leader leaving would leave nobody leading.
        $this->askToLeave($leader)->assertStatus(422);
        // Only the leader asks for a removal.
        $this->actingAs($a, 'web')->postJson(route('team.roster-requests.store'), ['type' => 'remove', 'student_id' => $b->student_id, 'reason' => $reason])->assertStatus(403);
        // And not of themself.
        $this->actingAs($leader, 'web')->postJson(route('team.roster-requests.store'), ['type' => 'remove', 'student_id' => $leader->student_id, 'reason' => $reason])->assertStatus(422);

        $this->assertSame(0, RosterChangeRequest::count());
    }

    public function test_one_request_about_a_member_at_a_time(): void
    {
        [, $leader, $a] = $this->lockedTeam();

        $this->askToLeave($a)->assertCreated();
        $this->actingAs($leader, 'web')->postJson(route('team.roster-requests.store'), [
            'type' => 'remove', 'student_id' => $a->student_id, 'reason' => 'Asked to go anyway, removing to be sure.',
        ])->assertStatus(422);

        $this->assertSame(1, RosterChangeRequest::count());
    }

    public function test_the_student_who_asked_can_withdraw_while_it_waits(): void
    {
        [, $leader, $a] = $this->lockedTeam();
        $this->askToLeave($a);
        $request = RosterChangeRequest::first();

        $this->actingAs($leader, 'web')->patchJson(route('team.roster-requests.update', $request), ['status' => 'withdrawn'])->assertStatus(403);
        $this->actingAs($a, 'web')->patchJson(route('team.roster-requests.update', $request), ['status' => 'withdrawn'])->assertOk();

        $this->assertSame(RosterChangeRequest::WITHDRAWN, $request->fresh()->status);
    }

    // ── the CaPSA decides ────────────────────────────────────────────────

    public function test_the_capsa_approves_and_the_member_is_released(): void
    {
        [$team, $leader, $a, $b] = $this->lockedTeam();
        $this->askToLeave($a);
        $request = RosterChangeRequest::first();
        $capsa = $this->capsa();

        $this->actingAs($capsa, 'expert')
            ->patchJson(route('capsa.roster_requests.update', $request), ['status' => 'approved', 'note' => 'Transfer papers seen.'])
            ->assertOk()
            ->assertJsonPath('request.status', 'approved');

        $this->assertFalse($this->inTeam($a, $team));
        $this->assertTrue($this->inTeam($b, $team));
        $this->assertSame($capsa->expert_id, $request->fresh()->decided_by);
        $this->assertTrue(Notification::where('student_id', $a->student_id)->where('title', 'Leave approved')->exists());
        $this->assertTrue(Notification::where('student_id', $leader->student_id)->where('title', 'Member left the team')->exists());
    }

    public function test_declining_needs_a_reason_and_keeps_the_member(): void
    {
        [$team, , $a] = $this->lockedTeam();
        $this->askToLeave($a);
        $request = RosterChangeRequest::first();
        $capsa = $this->capsa();

        $this->actingAs($capsa, 'expert')
            ->patchJson(route('capsa.roster_requests.update', $request), ['status' => 'declined'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('note');

        $this->actingAs($capsa, 'expert')
            ->patchJson(route('capsa.roster_requests.update', $request), ['status' => 'declined', 'note' => 'Finish the defense first; we can talk after.'])
            ->assertOk();

        $this->assertTrue($this->inTeam($a, $team));
        $this->assertTrue(Notification::where('student_id', $a->student_id)->where('title', 'Request declined')->exists());
    }

    public function test_a_capsa_of_another_section_cannot_decide(): void
    {
        [$team, , $a] = $this->lockedTeam();
        $this->askToLeave($a);

        $this->actingAs($this->capsa(['BSIT-3B']), 'expert')
            ->patchJson(route('capsa.roster_requests.update', RosterChangeRequest::first()), ['status' => 'approved'])
            ->assertStatus(403);

        $this->assertTrue($this->inTeam($a, $team));
    }

    public function test_a_decided_request_cannot_be_decided_again(): void
    {
        [, , $a] = $this->lockedTeam();
        $this->askToLeave($a);
        $request = RosterChangeRequest::first();
        $capsa = $this->capsa();

        $this->actingAs($capsa, 'expert')->patchJson(route('capsa.roster_requests.update', $request), ['status' => 'declined', 'note' => 'Not now.'])->assertOk();
        $this->actingAs($capsa, 'expert')->patchJson(route('capsa.roster_requests.update', $request), ['status' => 'approved'])->assertStatus(409);
    }

    /** Leadership moved to the member while the request waited. */
    public function test_the_leader_cannot_be_released(): void
    {
        [$team, , $a] = $this->lockedTeam();
        $this->askToLeave($a);
        $team->update(['leader_id' => $a->student_id]);

        $this->actingAs($this->capsa(), 'expert')
            ->patchJson(route('capsa.roster_requests.update', RosterChangeRequest::first()), ['status' => 'approved'])
            ->assertStatus(422);

        $this->assertTrue($this->inTeam($a, $team));
    }

    public function test_the_page_shows_what_is_waiting_and_what_approving_does(): void
    {
        [, , $a] = $this->lockedTeam();
        $this->askToLeave($a, 'My family is moving to Cebu next month.');

        $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.roster_requests'))
            ->assertOk()
            ->assertSee('My family is moving to Cebu next month.')
            ->assertSee('below the minimum of 3')
            ->assertViewHas('lockedTeams', 1);
    }

    public function test_the_team_page_shows_the_lock(): void
    {
        [, , $a] = $this->lockedTeam();

        $this->actingAs($a, 'web')
            ->get(route('student.team'))
            ->assertOk()
            ->assertSee('Members locked since')
            ->assertSee('Ask CaPSA to Leave');
    }
}
