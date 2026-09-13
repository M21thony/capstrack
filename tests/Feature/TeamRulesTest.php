<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentRoster;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The CaPSA's Team Rules: how large a team may be, and whether students may
 * create or join one at all.
 *
 * Each rule is enforced at every door it has. Team creation has two - making
 * a team, and a build-a-team post that names one. Joining has three - an
 * invitation, a request, and accepting either - and the last of those is the
 * one that had no check at all.
 */
class TeamRulesTest extends TestCase
{
    use RefreshDatabase;

    /** A recruitment post can only name a role that exists. */
    protected function setUp(): void
    {
        parent::setUp();
        (new \Database\Seeders\StudentRoleSeeder)->run();
    }

    private function capsa(): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-4A'],
        ]);
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        return $capsa;
    }

    private function student(string $email): Student
    {
        $s = Student::create([
            'email' => $email, 'f_name' => 'A', 'l_name' => 'B',
            'course' => 'BSIT', 'year_section' => '3rd · A',
        ]);
        StudentRoster::create([
            'email' => $email, 'name' => 'A B', 'section' => '3rd · A',
            'program' => 'BSIT', 'school_year' => Term::currentSchoolYear(),
        ]);

        // Joining a team is an update to this row, so every student needs one.
        StudentSchoolYear::create(['student_id' => $s->student_id]);

        return $s;
    }

    /** @param  array<int, Student>  $members */
    private function teamLedBy(Student $leader, array $members = []): Team
    {
        $team = Team::create([
            'team_name' => 'Team '.$leader->student_id,
            'leader_id' => $leader->student_id,
            'team_status' => 'Active',
        ]);

        foreach (array_merge([$leader], $members) as $s) {
            StudentSchoolYear::where('student_id', $s->student_id)->update(['team_id' => $team->team_id]);
        }

        return $team;
    }

    private function invitation(Student $from, Student $to, Team $team): TeamInvitation
    {
        return TeamInvitation::create([
            'sender_id' => $from->student_id, 'receiver_id' => $to->student_id,
            'team_id' => $team->team_id, 'type' => 'invite', 'status' => 'pending',
        ]);
    }

    private function close(string $rule): void
    {
        Setting::set($rule, '0');
    }

    // ── the page ──────────────────────────────────────────────────────────

    public function test_students_may_create_and_join_until_a_capsa_closes_it(): void
    {
        $this->assertTrue(Team::creationAllowed());
        $this->assertTrue(Team::joiningAllowed());
    }

    public function test_a_capsa_saves_every_rule_in_one_request(): void
    {
        $this->actingAs($this->capsa(), 'expert')
            ->putJson(route('capsa.team_rules.update'), [
                'max_members' => 4, 'allow_creation' => false, 'allow_joining' => false,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true, 'max_members' => 4,
                'allow_creation' => false, 'allow_joining' => false,
            ]);

        $this->assertSame(4, Team::maxMembers());
        $this->assertFalse(Team::creationAllowed());
        $this->assertFalse(Team::joiningAllowed());
    }

    /** One resource, replaced whole: a save that leaves a rule out is refused, not half-applied. */
    public function test_a_save_that_leaves_a_rule_out_changes_nothing(): void
    {
        $this->actingAs($this->capsa(), 'expert')
            ->putJson(route('capsa.team_rules.update'), ['max_members' => 3])
            ->assertStatus(422);

        $this->assertSame(5, Team::maxMembers());
        $this->assertTrue(Team::creationAllowed());
    }

    public function test_the_page_opens_for_a_capsa(): void
    {
        $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.team_management', ['tab' => 'rules']))
            ->assertOk()
            ->assertSee('Maximum members per team')
            ->assertSee('Allow students to create teams')
            ->assertSee('Allow students to join teams');
    }

    /** The page can say who a lower limit reaches before the CaPSA saves it. */
    public function test_the_page_knows_how_many_teams_have_each_size(): void
    {
        $leader = $this->student('sized@my.cspc.edu.ph');
        $this->teamLedBy($leader, [
            $this->student('s1@my.cspc.edu.ph'), $this->student('s2@my.cspc.edu.ph'), $this->student('s3@my.cspc.edu.ph'),
        ]);

        $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.team_management', ['tab' => 'rules']))
            ->assertOk()
            ->assertViewHas('tabData', fn ($data) => ($data['teamSizes'][4] ?? 0) === 1);
    }

    // ── creation ──────────────────────────────────────────────────────────

    public function test_with_creation_closed_a_student_cannot_start_a_team(): void
    {
        $this->close('allow_team_creation');

        $this->actingAs($this->student('maker@my.cspc.edu.ph'), 'web')
            ->postJson(route('team.store'), ['team_name' => 'Closed Out'])
            ->assertStatus(422)
            ->assertJson(['message' => Team::CREATION_CLOSED]);

        $this->assertSame(0, Team::count());
    }

    /** A named build-a-team post creates the team as it posts - the same rule by another door. */
    public function test_with_creation_closed_a_recruitment_post_cannot_start_one_either(): void
    {
        $this->close('allow_team_creation');

        $this->actingAs($this->student('poster@my.cspc.edu.ph'), 'web')
            ->postJson(route('recruitment.store'), [
                'recruitment_goal' => 'build_a_team',
                'team_name' => 'Back Door',
                'tags' => json_encode(['QA Tester/Documentation Writer']),
            ])
            ->assertStatus(422)
            ->assertJson(['message' => Team::CREATION_CLOSED]);

        $this->assertSame(0, Team::count());
    }

    // ── joining ───────────────────────────────────────────────────────────

    public function test_with_joining_closed_a_leader_cannot_invite(): void
    {
        $this->close('allow_team_joining');
        $leader = $this->student('lead@my.cspc.edu.ph');
        $team = $this->teamLedBy($leader);

        $this->actingAs($leader, 'web')
            ->postJson(route('invitations.store'), [
                'receiver_id' => $this->student('to@my.cspc.edu.ph')->student_id,
                'team_id' => $team->team_id, 'type' => 'invite',
            ])
            ->assertStatus(422)
            ->assertJson(['message' => Team::JOINING_CLOSED]);

        $this->assertSame(0, TeamInvitation::count());
    }

    public function test_with_joining_closed_a_student_cannot_ask_to_join(): void
    {
        $this->close('allow_team_joining');
        $leader = $this->student('lead2@my.cspc.edu.ph');
        $team = $this->teamLedBy($leader);

        $this->actingAs($this->student('asker@my.cspc.edu.ph'), 'web')
            ->postJson(route('invitations.store'), [
                'receiver_id' => $leader->student_id, 'team_id' => $team->team_id, 'type' => 'request',
            ])
            ->assertStatus(422)
            ->assertJson(['message' => Team::JOINING_CLOSED]);

        $this->assertSame(0, TeamInvitation::count());
    }

    /** Sent while joining was open, it cannot be accepted once joining has closed. */
    public function test_with_joining_closed_a_pending_invitation_cannot_be_accepted(): void
    {
        $leader = $this->student('lead3@my.cspc.edu.ph');
        $team = $this->teamLedBy($leader);
        $invitee = $this->student('invitee@my.cspc.edu.ph');
        $invitation = $this->invitation($leader, $invitee, $team);

        $this->close('allow_team_joining');

        $this->actingAs($invitee, 'web')
            ->patchJson(route('invitations.update', $invitation), ['status' => 'accepted'])
            ->assertStatus(422)
            ->assertJson(['message' => Team::JOINING_CLOSED]);

        $this->assertNull(StudentSchoolYear::where('student_id', $invitee->student_id)->value('team_id'));
    }

    /**
     * Two invitations out for the last seat, answered through the route the
     * page actually uses. Only the api route checked this before, and nothing
     * on the page calls the api route - so both used to get in.
     */
    public function test_the_last_seat_cannot_be_taken_twice_through_the_page(): void
    {
        Setting::set('max_team_members', '3');

        $leader = $this->student('lead4@my.cspc.edu.ph');
        $team = $this->teamLedBy($leader, [$this->student('sat@my.cspc.edu.ph')]);

        $first = $this->student('first@my.cspc.edu.ph');
        $second = $this->student('second@my.cspc.edu.ph');
        $toFirst = $this->invitation($leader, $first, $team);
        $toSecond = $this->invitation($leader, $second, $team);

        $this->actingAs($first, 'web')
            ->patchJson(route('invitations.update', $toFirst), ['status' => 'accepted'])
            ->assertSuccessful();

        $this->actingAs($second, 'web')
            ->patchJson(route('invitations.update', $toSecond), ['status' => 'accepted'])
            ->assertStatus(422);

        $this->assertNull(StudentSchoolYear::where('student_id', $second->student_id)->value('team_id'));
        $this->assertSame(3, StudentSchoolYear::where('team_id', $team->team_id)->count());
    }

    /** Lowering the limit removes nobody - but a team over it cannot grow. */
    public function test_a_team_above_a_lowered_limit_keeps_everyone_but_cannot_add(): void
    {
        $leader = $this->student('lead5@my.cspc.edu.ph');
        $team = $this->teamLedBy($leader, [
            $this->student('m1@my.cspc.edu.ph'), $this->student('m2@my.cspc.edu.ph'), $this->student('m3@my.cspc.edu.ph'),
        ]);

        Setting::set('max_team_members', '3');

        $this->assertSame(4, StudentSchoolYear::where('team_id', $team->team_id)->count());

        $this->actingAs($leader, 'web')
            ->postJson(route('invitations.store'), [
                'receiver_id' => $this->student('late@my.cspc.edu.ph')->student_id,
                'team_id' => $team->team_id, 'type' => 'invite',
            ])
            ->assertStatus(422);
    }
}
