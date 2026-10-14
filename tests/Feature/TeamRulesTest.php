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
use Database\Seeders\StudentRoleSeeder;
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
        (new StudentRoleSeeder)->run();
    }

    /** @param  array<int, string>  $sections */
    private function capsa(array $sections = ['BSIT-3A']): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => false, 'sections' => $sections,
        ]);
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        return $capsa;
    }

    /** A BSIT 3rd-year student, in section BSIT-3{letter}. */
    private function student(string $email, string $letter = 'A'): Student
    {
        $s = Student::create([
            'email' => $email, 'f_name' => 'A', 'l_name' => 'B',
            'course' => 'BSIT', 'year_section' => '3rd · '.$letter,
        ]);
        StudentRoster::create([
            'email' => $email, 'name' => 'A B', 'section' => '3rd · '.$letter,
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
                'max_members' => ['BSIT' => 4], 'allow_creation' => false, 'allow_joining' => false,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true, 'max_members' => ['BSIT' => 4],
                'allow_creation' => false, 'allow_joining' => false,
            ]);

        $this->assertSame(4, Team::maxMembers('BSIT-3A'));
        $this->assertFalse(Team::creationAllowed('BSIT-3A'));
        $this->assertFalse(Team::joiningAllowed('BSIT-3A'));
    }

    /** Team rules reach only the CaPSA's own sections; another CaPSA's section keeps its own. */
    public function test_a_save_changes_only_the_capsas_own_sections(): void
    {
        $this->actingAs($this->capsa(['BSIT-3A']), 'expert')
            ->putJson(route('capsa.team_rules.update'), [
                'max_members' => ['BSIT' => 3], 'allow_creation' => false, 'allow_joining' => false,
            ])
            ->assertOk();

        $this->assertSame(3, Team::maxMembers('BSIT-3A'));
        $this->assertSame(5, Team::maxMembers('BSIT-3B'));
        $this->assertTrue(Team::creationAllowed('BSIT-3B'));
        $this->assertTrue(Team::joiningAllowed('BSIT-3B'));
        $this->assertSame(5, Team::maxMembers());
    }

    /** A CaPSA with BSIT and BSIS sections may give each program its own size. */
    public function test_each_program_gets_its_own_max(): void
    {
        $this->actingAs($this->capsa(['BSIT-3A', 'BSIT-3B', 'BSIS-3A']), 'expert')
            ->putJson(route('capsa.team_rules.update'), [
                'max_members' => ['BSIT' => 5, 'BSIS' => 4], 'allow_creation' => true, 'allow_joining' => true,
            ])
            ->assertOk();

        $this->assertSame(5, Team::maxMembers('BSIT-3A'));
        $this->assertSame(5, Team::maxMembers('BSIT-3B'));
        $this->assertSame(4, Team::maxMembers('BSIS-3A'));
    }

    /** One resource, replaced whole: a save that leaves a rule out is refused, not half-applied. */
    public function test_a_save_that_leaves_a_rule_out_changes_nothing(): void
    {
        $this->actingAs($this->capsa(), 'expert')
            ->putJson(route('capsa.team_rules.update'), ['max_members' => ['BSIT' => 3]])
            ->assertStatus(422);

        $this->assertSame(5, Team::maxMembers('BSIT-3A'));
        $this->assertTrue(Team::creationAllowed('BSIT-3A'));
    }

    public function test_a_save_that_leaves_a_program_out_changes_nothing(): void
    {
        $this->actingAs($this->capsa(['BSIT-3A', 'BSIS-3A']), 'expert')
            ->putJson(route('capsa.team_rules.update'), [
                'max_members' => ['BSIT' => 3], 'allow_creation' => true, 'allow_joining' => true,
            ])
            ->assertStatus(422);

        $this->assertSame(5, Team::maxMembers('BSIT-3A'));
    }

    /**
     * The three rules are three small answers, so they open in a menu under
     * the Team Settings button on the list, not on a page of their own.
     */
    public function test_the_settings_are_a_menu_on_the_list(): void
    {
        $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.team_management'))
            ->assertOk()
            ->assertSee('id="setMenu"', false)
            ->assertSee('Max members per team')
            ->assertSee('Allow students to create teams')
            ->assertSee('Allow students to join teams');
    }

    /** Its old address is still bookmarked, so it lands on the list. */
    public function test_the_old_rules_address_goes_to_the_list(): void
    {
        $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.team_management', ['tab' => 'rules']))
            ->assertRedirect(route('capsa.team_management'));
    }

    /** The menu can say who a lower limit reaches before the CaPSA saves it. */
    public function test_the_menu_knows_how_many_teams_have_each_size(): void
    {
        $leader = $this->student('sized@my.cspc.edu.ph');
        $this->teamLedBy($leader, [
            $this->student('s1@my.cspc.edu.ph'), $this->student('s2@my.cspc.edu.ph'), $this->student('s3@my.cspc.edu.ph'),
        ]);

        $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.team_management'))
            ->assertOk()
            ->assertViewHas('rules', fn ($data) => ($data['programs'][0]['sizes'][4] ?? 0) === 1);
    }

    /** Teams in another CaPSA's section are not hers to count. */
    public function test_the_menu_counts_only_teams_in_her_sections(): void
    {
        $this->teamLedBy($this->student('elsewhere@my.cspc.edu.ph', 'B'), [$this->student('e1@my.cspc.edu.ph', 'B')]);

        $this->actingAs($this->capsa(['BSIT-3A']), 'expert')
            ->get(route('capsa.team_management'))
            ->assertOk()
            ->assertViewHas('rules', fn ($data) => $data['programs'][0]['sizes'] === []);
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

    /** Starting a team settles the founder's open invites and join requests, as accepting one would. */
    public function test_creating_a_team_clears_the_founders_pending_invites_and_requests(): void
    {
        $founder = $this->student('founder@my.cspc.edu.ph');
        $otherLeader = $this->student('other@my.cspc.edu.ph');
        $otherTeam = $this->teamLedBy($otherLeader);

        $invite = $this->invitation($otherLeader, $founder, $otherTeam);
        $request = TeamInvitation::create([
            'sender_id' => $founder->student_id, 'receiver_id' => $otherLeader->student_id,
            'team_id' => $otherTeam->team_id, 'type' => 'request', 'status' => 'pending',
        ]);

        $this->actingAs($founder, 'web')
            ->postJson(route('team.store'), ['team_name' => 'Fresh Start'])
            ->assertCreated();

        $this->assertSame('declined', $invite->fresh()->status);
        $this->assertSame('declined', $request->fresh()->status);
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

    /** Joining closed in one section leaves a team in another section open. */
    public function test_joining_closed_in_one_section_leaves_another_open(): void
    {
        Setting::set(Team::RULES_KEY.'BSIT-3A', json_encode(['max' => 5, 'create' => true, 'join' => false]));

        $closedLeader = $this->student('closed@my.cspc.edu.ph', 'A');
        $closedTeam = $this->teamLedBy($closedLeader);
        $openLeader = $this->student('open@my.cspc.edu.ph', 'B');
        $openTeam = $this->teamLedBy($openLeader);

        $this->actingAs($closedLeader, 'web')
            ->postJson(route('invitations.store'), [
                'receiver_id' => $this->student('to-a@my.cspc.edu.ph', 'A')->student_id,
                'team_id' => $closedTeam->team_id, 'type' => 'invite',
            ])
            ->assertStatus(422)
            ->assertJson(['message' => Team::JOINING_CLOSED]);

        $this->actingAs($openLeader, 'web')
            ->postJson(route('invitations.store'), [
                'receiver_id' => $this->student('to-b@my.cspc.edu.ph', 'B')->student_id,
                'team_id' => $openTeam->team_id, 'type' => 'invite',
            ])
            ->assertSuccessful();
    }

    /** A team's limit is its own section's, not the one other sections are under. */
    public function test_a_team_is_held_to_its_own_sections_limit(): void
    {
        Setting::set(Team::RULES_KEY.'BSIT-3A', json_encode(['max' => 3, 'create' => true, 'join' => true]));

        $small = $this->teamLedBy($this->student('small@my.cspc.edu.ph', 'A'));
        $large = $this->teamLedBy($this->student('large@my.cspc.edu.ph', 'B'));

        $this->assertSame(3, $small->memberLimit());
        $this->assertSame(5, $large->memberLimit());
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
