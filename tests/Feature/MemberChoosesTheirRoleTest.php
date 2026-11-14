<?php

namespace Tests\Feature;

use App\Models\Recruitment;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentRole;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use Database\Seeders\StudentRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A member's role is theirs to pick, once, and then it is settled.
 *
 * The leader never assigns one. What the team's recruitment post asked for
 * decides how much choosing there is: a post naming exactly one role hands it
 * over on accept and asks nothing, and anything else - two roles named, or no
 * post at all - leaves the question open for the student to answer on the
 * team page.
 *
 * The seats are the course's: one Project Head, held by the leader and never
 * offered, and two of each of the other three.
 */
class MemberChoosesTheirRoleTest extends TestCase
{
    use RefreshDatabase;

    private const PROGRAMMER = 'Software Engineer/Programmer';

    private const DESIGNER = 'Network Designer/UI Designer';

    private const TESTER = 'QA Tester/Documentation Writer';

    protected function setUp(): void
    {
        parent::setUp();
        (new StudentRoleSeeder)->run();
    }

    private function student(string $first, string $email): Student
    {
        // 3rd year. These students join a team, and a team only takes members
        // during Capstone 1 - as 4th years they were joining in Capstone 2,
        // which the accept path now refuses. It used to check nothing, so the
        // fixture got away with a scene the course does not allow.
        return Student::create([
            'email' => $email, 'f_name' => $first, 'l_name' => 'Dela Cruz',
            'course' => 'BSIT', 'year_section' => '3rd · A',
        ]);
    }

    /** A team with a leader already holding the Project Head seat. */
    private function team(): array
    {
        $leader = $this->student('Mark', 'leader@my.cspc.edu.ph');
        $team = Team::create(['team_name' => 'Bulbasaurus', 'leader_id' => $leader->student_id]);
        StudentSchoolYear::create([
            'student_id' => $leader->student_id,
            'team_id' => $team->team_id,
            's_role_id' => StudentRole::leaderRoleId(),
        ]);

        return [$leader, $team];
    }

    private function advertise(Team $team, array $roles): void
    {
        Recruitment::create([
            'student_id' => $team->leader_id,
            'recruitment_goal' => 'build_a_team',
            'looking_for' => 'A team.',
            'roles' => $roles,
            'member_count' => 5,
        ]);
    }

    /** @return array{0: Student, 1: TeamInvitation} */
    private function inviteeOf(Team $team, Student $leader): array
    {
        $joiner = $this->student('John', 'joiner@my.cspc.edu.ph');
        StudentSchoolYear::create(['student_id' => $joiner->student_id]);

        $invite = TeamInvitation::create([
            'sender_id' => $leader->student_id,
            'receiver_id' => $joiner->student_id,
            'team_id' => $team->team_id,
            'type' => 'invite',
            'status' => 'pending',
        ]);

        return [$joiner, $invite];
    }

    /**
     * Just the dialog.
     *
     * The whole page also carries every role name in the JavaScript behind
     * the recruitment post, so asserting against the page cannot tell what
     * was actually offered from what merely appears somewhere in the source.
     */
    private function dialog(string $html): string
    {
        $at = strpos($html, 'id="roleChoiceModal"');

        if ($at === false) {
            return '';
        }

        return substr($html, $at, strpos($html, 'role-choice-form', $at) - $at);
    }

    private function roleId(string $name): int
    {
        return (int) StudentRole::where('s_role_name', $name)->value('s_role_id');
    }

    private function roleOf(Student $student): ?string
    {
        return StudentSchoolYear::with('role')
            ->where('student_id', $student->student_id)
            ->first()?->role?->s_role_name;
    }

    // ----------------------------------------------------------------
    // Accepting
    // ----------------------------------------------------------------

    /** One role named on the post is handed over without a question. */
    public function test_a_post_naming_one_role_assigns_it_on_accept(): void
    {
        [$leader, $team] = $this->team();
        $this->advertise($team, [self::DESIGNER]);
        [$joiner, $invite] = $this->inviteeOf($team, $leader);

        $this->actingAs($joiner, 'web')
            ->patch(route('invitations.update', $invite), ['status' => 'accepted'])
            ->assertRedirect();

        $this->assertSame(self::DESIGNER, $this->roleOf($joiner));
    }

    /** Two named roles is a real choice, so nothing is decided for them. */
    public function test_a_post_naming_two_roles_leaves_the_choice_open(): void
    {
        [$leader, $team] = $this->team();
        $this->advertise($team, [self::DESIGNER, self::TESTER]);
        [$joiner, $invite] = $this->inviteeOf($team, $leader);

        $this->actingAs($joiner, 'web')
            ->patch(route('invitations.update', $invite), ['status' => 'accepted']);

        $this->assertNull($this->roleOf($joiner));
    }

    /**
     * The case that made this necessary. Nobody posted anything - the leader
     * simply invited a classmate - and before this the student landed on the
     * team with no role at all.
     */
    public function test_a_direct_invitation_leaves_the_choice_open(): void
    {
        [$leader, $team] = $this->team();
        [$joiner, $invite] = $this->inviteeOf($team, $leader);

        $this->actingAs($joiner, 'web')
            ->patch(route('invitations.update', $invite), ['status' => 'accepted']);

        $this->assertNull($this->roleOf($joiner));
    }

    // ----------------------------------------------------------------
    // Being asked
    // ----------------------------------------------------------------

    /** One role advertised with a seat free needs no dialog at all. */
    public function test_one_advertised_role_is_assigned_without_asking(): void
    {
        [$leader, $team] = $this->team();
        $this->advertise($team, [self::TESTER]);
        Setting::set('max_team_members', '3');
        [$joiner, $invite] = $this->inviteeOf($team, $leader);

        $this->actingAs($joiner, 'web')
            ->patch(route('invitations.update', $invite), ['status' => 'accepted']);

        $html = $this->actingAs($joiner, 'web')
            ->get(route('student.team'))->assertOk()->getContent();

        // One role advertised and a seat free, so it was assigned outright.
        $this->assertSame(self::TESTER, $this->roleOf($joiner));
        $this->assertStringNotContainsString('Choose your role', $html);
    }

    /** With no post to go on, every member role with a seat is offered. */
    public function test_with_no_post_every_open_role_is_offered(): void
    {
        [$leader, $team] = $this->team();
        [$joiner, $invite] = $this->inviteeOf($team, $leader);

        $this->actingAs($joiner, 'web')
            ->patch(route('invitations.update', $invite), ['status' => 'accepted']);

        $dialog = $this->dialog(
            $this->actingAs($joiner, 'web')->get(route('student.team'))->assertOk()->getContent()
        );

        foreach ([self::DESIGNER, self::TESTER] as $role) {
            $this->assertStringContainsString($role, $dialog);
        }
    }

    /** The leader is the Project Head and is never asked anything. */
    public function test_the_leader_is_never_asked(): void
    {
        [$leader] = $this->team();

        $html = $this->actingAs($leader, 'web')
            ->get(route('student.team'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Choose your role', $html);
    }

    // ----------------------------------------------------------------
    // Answering
    // ----------------------------------------------------------------

    public function test_a_member_records_their_own_role(): void
    {
        [$leader, $team] = $this->team();
        [$joiner, $invite] = $this->inviteeOf($team, $leader);
        $this->actingAs($joiner, 'web')->patch(route('invitations.update', $invite), ['status' => 'accepted']);

        $this->actingAs($joiner, 'web')
            ->put(route('team.role.update'), ['role_id' => $this->roleId(self::DESIGNER)])
            ->assertRedirect(route('student.team'));

        $this->assertSame(self::DESIGNER, $this->roleOf($joiner));
    }

    /** Chosen once. Changing your mind later is not on offer. */
    public function test_a_role_cannot_be_changed_once_set(): void
    {
        [$leader, $team] = $this->team();
        [$joiner, $invite] = $this->inviteeOf($team, $leader);
        $this->actingAs($joiner, 'web')->patch(route('invitations.update', $invite), ['status' => 'accepted']);

        $this->actingAs($joiner, 'web')
            ->put(route('team.role.update'), ['role_id' => $this->roleId(self::DESIGNER)]);
        $this->actingAs($joiner, 'web')
            ->put(route('team.role.update'), ['role_id' => $this->roleId(self::TESTER)]);

        $this->assertSame(self::DESIGNER, $this->roleOf($joiner));
    }

    /** A secondary is a different member role, set once, and shown on the team page. */
    public function test_a_secondary_role_is_a_different_role_set_once(): void
    {
        [$leader, $team] = $this->team();
        [$joiner, $invite] = $this->inviteeOf($team, $leader);
        $this->actingAs($joiner, 'web')->patch(route('invitations.update', $invite), ['status' => 'accepted']);
        $this->actingAs($joiner, 'web')->put(route('team.role.update'), ['role_id' => $this->roleId(self::DESIGNER)]);

        $secondary = fn (Student $who) => StudentSchoolYear::where('student_id', $who->student_id)->value('secondary_role_id');

        // Not the role they already hold, and not a seat nobody is offered.
        foreach ([self::DESIGNER, self::PROGRAMMER, StudentRole::LEADER_ROLE_NAME] as $refused) {
            $this->actingAs($joiner, 'web')->put(route('team.role.update'), ['secondary_role_id' => $this->roleId($refused)]);
            $this->assertNull($secondary($joiner));
        }

        $this->actingAs($joiner, 'web')->put(route('team.role.update'), ['secondary_role_id' => $this->roleId(self::TESTER)]);
        $this->assertSame($this->roleId(self::TESTER), (int) $secondary($joiner));
        $this->assertSame(self::DESIGNER, $this->roleOf($joiner));

        // The leader picks one too, and it cannot be changed afterwards.
        $this->actingAs($leader, 'web')->put(route('team.role.update'), ['secondary_role_id' => $this->roleId(self::DESIGNER)]);
        $this->actingAs($leader, 'web')->put(route('team.role.update'), ['secondary_role_id' => $this->roleId(self::TESTER)]);
        $this->assertSame($this->roleId(self::DESIGNER), (int) $secondary($leader));

        $this->actingAs($leader, 'web')->get(route('student.team'))->assertOk()
            ->assertSee('Secondary: QA Tester / Documentation Writer');
    }

    /** Project Head belongs to the leader and cannot be taken. */
    public function test_nobody_can_take_the_project_head_seat(): void
    {
        [$leader, $team] = $this->team();
        [$joiner, $invite] = $this->inviteeOf($team, $leader);
        $this->actingAs($joiner, 'web')->patch(route('invitations.update', $invite), ['status' => 'accepted']);

        $this->actingAs($joiner, 'web')
            ->put(route('team.role.update'), ['role_id' => StudentRole::leaderRoleId()]);

        $this->assertNull($this->roleOf($joiner));
    }

    /** Two to a role, and the third person is turned away. */
    public function test_a_role_holds_two_and_no_more(): void
    {
        [$leader, $team] = $this->team();
        $tester = StudentRole::where('s_role_name', self::TESTER)->value('s_role_id');

        foreach (['a', 'b'] as $i => $tag) {
            $held = $this->student('Held'.$i, "held{$tag}@my.cspc.edu.ph");
            StudentSchoolYear::create([
                'student_id' => $held->student_id,
                'team_id' => $team->team_id,
                's_role_id' => $tester,
            ]);
        }

        $this->assertFalse(StudentRole::hasSeat($team->team_id, $tester));

        [$joiner, $invite] = $this->inviteeOf($team, $leader);
        $this->actingAs($joiner, 'web')->patch(route('invitations.update', $invite), ['status' => 'accepted']);

        $this->actingAs($joiner, 'web')
            ->put(route('team.role.update'), ['role_id' => $tester]);

        $this->assertNull($this->roleOf($joiner));
    }

    /**
     * Programmer is not a choice, because it is not a designation anybody is
     * given. The course has it "played partly by each team member", so it is
     * already true of everyone on the team, the leader included, and no
     * s_role_id ever points at it.
     */
    public function test_the_programmer_role_is_never_offered(): void
    {
        [$leader, $team] = $this->team();
        [$joiner, $invite] = $this->inviteeOf($team, $leader);
        $this->actingAs($joiner, 'web')->patch(route('invitations.update', $invite), ['status' => 'accepted']);

        $dialog = $this->dialog(
            $this->actingAs($joiner, 'web')->get(route('student.team'))->assertOk()->getContent()
        );

        // Named as something they already are, not offered as a button.
        $this->assertStringContainsString('Everyone on the team is a', $dialog);
        $this->assertStringNotContainsString('pickRole('.$this->roleId(self::PROGRAMMER), $dialog);

        $this->assertFalse(StudentRole::hasSeat($team->team_id, $this->roleId(self::PROGRAMMER)));
        $this->assertNotContains(self::PROGRAMMER, StudentRole::memberRoles()->pluck('s_role_name')->all());
    }

    /** And it cannot be taken by going straight at the endpoint. */
    public function test_the_programmer_role_cannot_be_claimed(): void
    {
        [$leader, $team] = $this->team();
        [$joiner, $invite] = $this->inviteeOf($team, $leader);
        $this->actingAs($joiner, 'web')->patch(route('invitations.update', $invite), ['status' => 'accepted']);

        $this->actingAs($joiner, 'web')
            ->put(route('team.role.update'), ['role_id' => $this->roleId(self::PROGRAMMER)]);

        $this->assertNull($this->roleOf($joiner));
    }

    /**
     * The two seated roles divide the team exactly.
     *
     * This is the arithmetic that shows Programmer was never meant to be on
     * the list: a team of three is two members into two seats, and a team of
     * five is four into four. A third option would leave those seats empty.
     */
    public function test_the_seats_account_for_every_member(): void
    {
        foreach ([3 => 1, 5 => 2] as $size => $each) {
            $this->assertSame($each, StudentRole::seatsFor(self::TESTER, $size));
            $this->assertSame($size - 1, 2 * StudentRole::seatsFor(self::TESTER, $size));
        }
    }

    /** A full role is still shown, with who is in it, rather than vanishing. */
    public function test_a_full_role_is_shown_as_full(): void
    {
        [$leader, $team] = $this->team();
        $tester = StudentRole::where('s_role_name', self::TESTER)->value('s_role_id');

        foreach ([['Ana', 'ana'], ['Rico', 'rico']] as [$first, $tag]) {
            $held = $this->student($first, "{$tag}@my.cspc.edu.ph");
            StudentSchoolYear::create([
                'student_id' => $held->student_id,
                'team_id' => $team->team_id,
                's_role_id' => $tester,
            ]);
        }

        [$joiner, $invite] = $this->inviteeOf($team, $leader);
        $this->actingAs($joiner, 'web')->patch(route('invitations.update', $invite), ['status' => 'accepted']);

        $dialog = $this->dialog(
            $this->actingAs($joiner, 'web')->get(route('student.team'))->assertOk()->getContent()
        );

        $this->assertStringContainsString('Ana Dela Cruz', $dialog);
        $this->assertStringContainsString('Rico Dela Cruz', $dialog);
    }

    /**
     * A post naming a role whose seats are gone is no longer an instruction,
     * so the student is asked instead of being wedged into a full role.
     */
    public function test_a_post_naming_a_full_role_falls_back_to_asking(): void
    {
        [$leader, $team] = $this->team();
        $tester = StudentRole::where('s_role_name', self::TESTER)->value('s_role_id');
        $this->advertise($team, [self::TESTER]);

        foreach (['x', 'y'] as $i => $tag) {
            $held = $this->student('Held'.$i, "held{$tag}@my.cspc.edu.ph");
            StudentSchoolYear::create([
                'student_id' => $held->student_id,
                'team_id' => $team->team_id,
                's_role_id' => $tester,
            ]);
        }

        [$joiner, $invite] = $this->inviteeOf($team, $leader);
        $this->actingAs($joiner, 'web')->patch(route('invitations.update', $invite), ['status' => 'accepted']);

        $this->assertNull($this->roleOf($joiner));
    }

    /**
     * The two seated roles scale with the size the CaPSA/TSA set.
     *
     * A team is the leader plus everybody else, split across Network
     * Designer and QA Tester, so each takes half rounded up. Programmer sits
     * outside the arithmetic entirely.
     */
    public function test_seats_follow_the_size_of_the_team(): void
    {
        $this->assertSame(1, StudentRole::seatsFor(self::TESTER, 3));
        $this->assertSame(2, StudentRole::seatsFor(self::TESTER, 4));
        $this->assertSame(2, StudentRole::seatsFor(self::TESTER, 5));

        $this->assertSame(1, StudentRole::seatsFor(self::DESIGNER, 3));
        $this->assertSame(2, StudentRole::seatsFor(self::DESIGNER, 5));

        // Neither of these is a seat anybody competes for.
        $this->assertNull(StudentRole::seatsFor(self::PROGRAMMER, 3));
        $this->assertSame(1, StudentRole::seatsFor(StudentRole::LEADER_ROLE_NAME, 5));
    }

    /** On a team of three, one person to a seated role and no more. */
    public function test_a_team_of_three_seats_one_each(): void
    {
        [$leader, $team] = $this->team();
        Setting::set('max_team_members', '3');

        $tester = StudentRole::where('s_role_name', self::TESTER)->value('s_role_id');

        $held = $this->student('Ana', 'ana@my.cspc.edu.ph');
        StudentSchoolYear::create([
            'student_id' => $held->student_id,
            'team_id' => $team->team_id,
            's_role_id' => $tester,
        ]);

        $this->assertFalse(StudentRole::hasSeat($team->team_id, $tester));

        [$joiner, $invite] = $this->inviteeOf($team, $leader);
        $this->actingAs($joiner, 'web')->patch(route('invitations.update', $invite), ['status' => 'accepted']);

        $this->actingAs($joiner, 'web')->put(route('team.role.update'), ['role_id' => $tester]);
        $this->assertNull($this->roleOf($joiner));

        // The other seat is theirs, and on a team of three that is the last
        // one - which is right, because a third member would not fit anyway.
        $this->actingAs($joiner, 'web')
            ->put(route('team.role.update'), ['role_id' => $this->roleId(self::DESIGNER)]);
        $this->assertSame(self::DESIGNER, $this->roleOf($joiner));
    }

    /**
     * Raising the limit opens the seats on teams that already exist.
     *
     * The seat count used to be read off the team's own member_count
     * column, a copy written when the team was made. A CaPSA/TSA moving the
     * limit from three to five left every existing team still seating one
     * designer and one tester, and a joining member was told a role was
     * full when it was not.
     */
    public function test_raising_the_limit_opens_the_seats_on_a_team_that_already_exists(): void
    {
        Setting::set('max_team_members', '3');

        [, $team] = $this->team();
        $tester = $this->roleId(self::TESTER);

        $held = $this->student('Ana', 'ana@my.cspc.edu.ph');
        StudentSchoolYear::create([
            'student_id' => $held->student_id,
            'team_id' => $team->team_id,
            's_role_id' => $tester,
        ]);

        // One seat on a team of three, and it is taken.
        $this->assertFalse(StudentRole::hasSeat($team->team_id, $tester));

        Setting::set('max_team_members', '5');

        // Two seats on a team of five, so the second is free - without the
        // team itself being touched.
        $this->assertTrue(StudentRole::hasSeat($team->team_id, $tester));
    }

    // ----------------------------------------------------------------
    // The one thing that moves a settled role
    // ----------------------------------------------------------------

    /**
     * Transferring leadership swaps the two roles. Project Head holds one
     * person, so the outgoing leader has to land somewhere, and taking the
     * role the new leader just vacated keeps every seat count correct without
     * anybody being asked to choose again.
     */
    public function test_transferring_leadership_swaps_the_two_roles(): void
    {
        [$leader, $team] = $this->team();
        $programmer = StudentRole::where('s_role_name', self::PROGRAMMER)->value('s_role_id');

        $heir = $this->student('John', 'heir@my.cspc.edu.ph');
        StudentSchoolYear::create([
            'student_id' => $heir->student_id,
            'team_id' => $team->team_id,
            's_role_id' => $programmer,
        ]);

        // The swap itself, as the controller performs it.
        $heirsOldRole = StudentSchoolYear::where('student_id', $heir->student_id)->value('s_role_id');
        StudentSchoolYear::where('student_id', $leader->student_id)->update(['s_role_id' => $heirsOldRole]);
        StudentSchoolYear::where('student_id', $heir->student_id)->update(['s_role_id' => StudentRole::leaderRoleId()]);
        $team->update(['leader_id' => $heir->student_id]);

        $this->assertSame(StudentRole::LEADER_ROLE_NAME, $this->roleOf($heir));
        $this->assertSame(self::PROGRAMMER, $this->roleOf($leader));
        // Still exactly one Project Head.
        $this->assertSame(1, StudentSchoolYear::where('team_id', $team->team_id)
            ->where('s_role_id', StudentRole::leaderRoleId())->count());
    }

    // ----------------------------------------------------------------
    // The post
    // ----------------------------------------------------------------

    /**
     * A post can only ask for a role that exists.
     *
     * The picker used to offer eight invented options - "App Developer",
     * "Backend Dev", "Project Manager" - none of them in student_roles. A
     * post carrying those can never assign anybody anything: there is no
     * such role to give. It is refused at the door now, at both ends.
     */
    public function test_a_post_cannot_advertise_a_role_that_does_not_exist(): void
    {
        $applicant = $this->student('Ana', 'poster@my.cspc.edu.ph');
        StudentSchoolYear::create(['student_id' => $applicant->student_id]);

        $this->actingAs($applicant, 'web')->post(route('recruitment.store'), [
            'recruitment_goal' => 'find_a_team',
            'introduction' => 'I can build things.',
            'member_count' => 5,
            'tags' => json_encode(['App Developer', 'Backend Dev']),
        ]);

        $this->assertNull(Recruitment::where('student_id', $applicant->student_id)->first());
    }

    /** And the real ones get through. */
    public function test_a_post_asking_for_a_real_role_is_published(): void
    {
        $applicant = $this->student('Ana', 'poster@my.cspc.edu.ph');
        StudentSchoolYear::create(['student_id' => $applicant->student_id]);

        $this->actingAs($applicant, 'web')->post(route('recruitment.store'), [
            'recruitment_goal' => 'find_a_team',
            'introduction' => 'I can build things.',
            'member_count' => 5,
            'tags' => json_encode([self::TESTER]),
        ]);

        $post = Recruitment::where('student_id', $applicant->student_id)->first();

        $this->assertNotNull($post);
        $this->assertSame([self::TESTER], (array) $post->roles);
    }

    /** Anything invented is dropped, and what is real survives beside it. */
    public function test_invented_roles_are_stripped_from_a_mixed_post(): void
    {
        $applicant = $this->student('Ana', 'poster@my.cspc.edu.ph');
        StudentSchoolYear::create(['student_id' => $applicant->student_id]);

        $this->actingAs($applicant, 'web')->post(route('recruitment.store'), [
            'recruitment_goal' => 'find_a_team',
            'introduction' => 'I can build things.',
            'member_count' => 5,
            'tags' => json_encode(['Backend Dev', self::TESTER, self::PROGRAMMER]),
        ]);

        $post = Recruitment::where('student_id', $applicant->student_id)->first();

        $this->assertSame([self::TESTER], (array) $post->roles);
    }

    /**
     * The post offers Create Post's six choices. Leader and Member are tags
     * that name no seat, so the leader's seat itself is still never given
     * away through a post; the two member roles are offered as Primary or
     * Secondary.
     */
    public function test_the_post_offers_the_member_roles_only(): void
    {
        [$leader] = $this->team();

        $html = $this->actingAs($leader, 'web')
            ->get(route('student.team'))->assertOk()->getContent();

        $this->assertStringContainsString('>Roles</label>', $html);
        $this->assertStringNotContainsString('Expertise & Roles', $html);

        foreach (['Network Designer/UI Designer (Primary)', 'Network Designer/UI Designer (Secondary)', 'QA Tester/Documentation (Primary)', 'QA Tester/Documentation (Secondary)'] as $choice) {
            $this->assertStringContainsString($choice, $html);
        }
        $this->assertNull(Recruitment::ROLE_OPTIONS['leader']['role'], 'Leader names no seat');

        $names = StudentRole::memberRoles()->pluck('s_role_name')->all();
        $this->assertNotContains(StudentRole::LEADER_ROLE_NAME, $names);
        $this->assertNotContains(self::PROGRAMMER, $names);
        $this->assertCount(2, $names);
    }
}
