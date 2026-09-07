<?php

namespace Tests\Feature;

use App\Models\Recruitment;
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
        return Student::create([
            'email' => $email, 'f_name' => $first, 'l_name' => 'Dela Cruz',
            'course' => 'BSIT', 'year_section' => '4th · A',
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
        $this->advertise($team, [self::PROGRAMMER]);
        [$joiner, $invite] = $this->inviteeOf($team, $leader);

        $this->actingAs($joiner, 'web')
            ->patch(route('invitations.update', $invite), ['status' => 'accepted'])
            ->assertRedirect();

        $this->assertSame(self::PROGRAMMER, $this->roleOf($joiner));
    }

    /** Two named roles is a real choice, so nothing is decided for them. */
    public function test_a_post_naming_two_roles_leaves_the_choice_open(): void
    {
        [$leader, $team] = $this->team();
        $this->advertise($team, [self::PROGRAMMER, self::TESTER]);
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

    /** The team page asks, and offers only what the post asked for. */
    public function test_the_team_page_asks_and_offers_the_posted_roles(): void
    {
        [$leader, $team] = $this->team();
        $this->advertise($team, [self::PROGRAMMER, self::TESTER]);
        [$joiner, $invite] = $this->inviteeOf($team, $leader);

        $this->actingAs($joiner, 'web')
            ->patch(route('invitations.update', $invite), ['status' => 'accepted']);

        $html = $this->actingAs($joiner, 'web')
            ->get(route('student.team'))->assertOk()->getContent();

        $this->assertStringContainsString('Choose your role', $html);

        $dialog = $this->dialog($html);
        $this->assertStringContainsString(self::PROGRAMMER, $dialog);
        $this->assertStringContainsString(self::TESTER, $dialog);
        // Not advertised, so not on the list.
        $this->assertStringNotContainsString(self::DESIGNER, $dialog);
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

        foreach ([self::PROGRAMMER, self::DESIGNER, self::TESTER] as $role) {
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

        $roleId = StudentRole::where('s_role_name', self::PROGRAMMER)->value('s_role_id');

        $this->actingAs($joiner, 'web')
            ->put(route('team.role.update'), ['role_id' => $roleId])
            ->assertRedirect(route('student.team'));

        $this->assertSame(self::PROGRAMMER, $this->roleOf($joiner));
    }

    /** Chosen once. Changing your mind later is not on offer. */
    public function test_a_role_cannot_be_changed_once_set(): void
    {
        [$leader, $team] = $this->team();
        [$joiner, $invite] = $this->inviteeOf($team, $leader);
        $this->actingAs($joiner, 'web')->patch(route('invitations.update', $invite), ['status' => 'accepted']);

        $programmer = StudentRole::where('s_role_name', self::PROGRAMMER)->value('s_role_id');
        $tester = StudentRole::where('s_role_name', self::TESTER)->value('s_role_id');

        $this->actingAs($joiner, 'web')->put(route('team.role.update'), ['role_id' => $programmer]);
        $this->actingAs($joiner, 'web')->put(route('team.role.update'), ['role_id' => $tester]);

        $this->assertSame(self::PROGRAMMER, $this->roleOf($joiner));
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
        $programmer = StudentRole::where('s_role_name', self::PROGRAMMER)->value('s_role_id');

        foreach (['a', 'b'] as $i => $tag) {
            $held = $this->student('Held'.$i, "held{$tag}@my.cspc.edu.ph");
            StudentSchoolYear::create([
                'student_id' => $held->student_id,
                'team_id' => $team->team_id,
                's_role_id' => $programmer,
            ]);
        }

        $this->assertFalse(StudentRole::hasSeat($team->team_id, $programmer));

        [$joiner, $invite] = $this->inviteeOf($team, $leader);
        $this->actingAs($joiner, 'web')->patch(route('invitations.update', $invite), ['status' => 'accepted']);

        $this->actingAs($joiner, 'web')
            ->put(route('team.role.update'), ['role_id' => $programmer]);

        $this->assertNull($this->roleOf($joiner));
    }

    /** A full role is still shown, with who is in it, rather than vanishing. */
    public function test_a_full_role_is_shown_as_full(): void
    {
        [$leader, $team] = $this->team();
        $programmer = StudentRole::where('s_role_name', self::PROGRAMMER)->value('s_role_id');

        foreach ([['Ana', 'ana'], ['Rico', 'rico']] as [$first, $tag]) {
            $held = $this->student($first, "{$tag}@my.cspc.edu.ph");
            StudentSchoolYear::create([
                'student_id' => $held->student_id,
                'team_id' => $team->team_id,
                's_role_id' => $programmer,
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
        $programmer = StudentRole::where('s_role_name', self::PROGRAMMER)->value('s_role_id');
        $this->advertise($team, [self::PROGRAMMER]);

        foreach (['x', 'y'] as $i => $tag) {
            $held = $this->student('Held'.$i, "held{$tag}@my.cspc.edu.ph");
            StudentSchoolYear::create([
                'student_id' => $held->student_id,
                'team_id' => $team->team_id,
                's_role_id' => $programmer,
            ]);
        }

        [$joiner, $invite] = $this->inviteeOf($team, $leader);
        $this->actingAs($joiner, 'web')->patch(route('invitations.update', $invite), ['status' => 'accepted']);

        $this->assertNull($this->roleOf($joiner));
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

    /** The post advertises the real roles, and never the leader's seat. */
    public function test_the_post_offers_the_member_roles_only(): void
    {
        [$leader] = $this->team();

        $html = $this->actingAs($leader, 'web')
            ->get(route('student.team'))->assertOk()->getContent();

        $this->assertStringContainsString('>Roles</label>', $html);
        $this->assertStringNotContainsString('Expertise & Roles', $html);

        foreach ([self::PROGRAMMER, self::DESIGNER, self::TESTER] as $role) {
            $this->assertStringContainsString($role, $html);
        }

        $names = StudentRole::memberRoles()->pluck('s_role_name')->all();
        $this->assertNotContains(StudentRole::LEADER_ROLE_NAME, $names);
        $this->assertCount(3, $names);
    }
}
