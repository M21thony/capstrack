<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\MemberLeaveRequest;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Database\Seeders\StudentRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A team's Full status follows its seats under its section's Team Rules.
 *
 * It closes when the last seat the CaPSA allows is taken, and it opens again
 * when somebody leaves - which nothing did before, so the CaPSA kept seeing
 * "Full" on a team with a free seat.
 */
class TeamStatusFollowsSeatsTest extends TestCase
{
    use RefreshDatabase;

    private int $people = 0;

    protected function setUp(): void
    {
        parent::setUp();
        (new StudentRoleSeeder)->run();
    }

    private function student(): Student
    {
        $this->people++;
        $s = Student::create([
            'email' => "s{$this->people}@my.cspc.edu.ph", 'f_name' => 'S', 'l_name' => (string) $this->people,
            'course' => 'BSIT', 'year_section' => '3rd · A',
        ]);
        StudentSchoolYear::create(['student_id' => $s->student_id]);

        return $s;
    }

    /** @return array{0: Team, 1: Student, 2: Student} the team, its leader and one member */
    private function team(int $size, string $status): array
    {
        $leader = $this->student();
        $team = Team::create(['team_name' => 'Team '.$leader->student_id, 'leader_id' => $leader->student_id, 'team_status' => $status]);
        StudentSchoolYear::where('student_id', $leader->student_id)->update(['team_id' => $team->team_id]);

        $member = null;
        for ($i = 1; $i < $size; $i++) {
            $member = $this->student();
            StudentSchoolYear::where('student_id', $member->student_id)->update(['team_id' => $team->team_id]);
        }

        return [$team, $leader, $member];
    }

    /** The CaPSA's Team Rules for the team's section: a limit of $max. */
    private function sectionLimit(Team $team, int $max): void
    {
        Setting::set(Team::RULES_KEY.$team->section(), json_encode(['max' => $max, 'create' => true, 'join' => true]));
    }

    private function capsa(): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-3A']]);
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        return $capsa;
    }

    public function test_the_section_limit_decides_when_a_team_is_full(): void
    {
        [$team] = $this->team(3, 'Active');
        Setting::set('max_team_members', '5');
        $this->sectionLimit($team, 3);

        $this->assertTrue($team->fresh()->isFull());
    }

    public function test_removing_a_member_opens_a_full_team_again(): void
    {
        [$team, $leader, $member] = $this->team(3, 'Full');
        $this->sectionLimit($team, 3);

        $this->actingAs($leader, 'web')->delete(route('team.members.remove', $member))->assertRedirect();

        $this->assertSame('Active', $team->fresh()->team_status);
    }

    public function test_removing_a_member_through_the_api_opens_a_full_team_again(): void
    {
        [$team, $leader, $member] = $this->team(3, 'Full');
        $this->sectionLimit($team, 3);

        $this->actingAs($leader, 'web')->deleteJson("/api/teams/{$team->team_id}/members/{$member->student_id}")->assertSuccessful();

        $this->assertSame('Active', $team->fresh()->team_status);
    }

    public function test_a_leave_the_capsa_approves_opens_a_full_team_again(): void
    {
        [$team, , $member] = $this->team(3, 'Full');
        $team->update(['is_confirmed' => true]);
        $this->sectionLimit($team, 3);

        $this->actingAs($member, 'web')->postJson(route('team.capsa-leave-requests.store'), [
            'type' => MemberLeaveRequest::LEAVE, 'reason' => 'My family is moving to Cebu next month.',
        ])->assertCreated();

        $this->actingAs($this->capsa(), 'expert')
            ->patchJson(route('capsa.leave_requests.update', MemberLeaveRequest::firstOrFail()), ['status' => 'approved', 'note' => 'Transfer papers seen.'])
            ->assertOk();

        $this->assertNull(StudentSchoolYear::where('student_id', $member->student_id)->value('team_id'));
        $this->assertSame('Active', $team->fresh()->team_status);
    }

    public function test_a_team_still_at_its_limit_stays_full_when_nobody_left(): void
    {
        [$team] = $this->team(3, 'Full');
        $this->sectionLimit($team, 3);

        $team->fresh()->reopenIfRoom();

        $this->assertSame('Full', $team->fresh()->team_status);
    }

    public function test_saving_the_team_closes_it_at_the_section_limit_not_at_five(): void
    {
        [$team, $leader] = $this->team(3, 'Active');
        $this->sectionLimit($team, 3);

        $this->actingAs($leader, 'web')->put(route('team.update', $team), ['team_name' => 'Renamed'])->assertRedirect();

        $this->assertSame('Full', $team->fresh()->team_status);
    }
}
