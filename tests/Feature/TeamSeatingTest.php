<?php

namespace Tests\Feature;

use App\Models\Recruitment;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentRole;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Support\TeamSeating;
use Database\Seeders\StudentRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TeamSeating answers, in one place, which member roles a team still has
 * room for and what a joining member is given. These tests ask it directly;
 * MemberChoosesTheirRoleTest covers the same rules through the pages.
 */
class TeamSeatingTest extends TestCase
{
    use RefreshDatabase;

    private const DESIGNER = 'Network Designer/UI Designer';

    private const TESTER = 'QA Tester/Documentation Writer';

    private int $people = 0;

    protected function setUp(): void
    {
        parent::setUp();
        (new StudentRoleSeeder)->run();
    }

    private function student(): Student
    {
        $this->people++;

        return Student::create([
            'email' => "student{$this->people}@my.cspc.edu.ph", 'f_name' => 'Student', 'l_name' => (string) $this->people,
            'course' => 'BSIT', 'year_section' => '3rd · A',
        ]);
    }

    private function team(): Team
    {
        $leader = $this->student();
        $team = Team::create(['team_name' => 'Bulbasaurus', 'leader_id' => $leader->student_id]);
        StudentSchoolYear::create([
            'student_id' => $leader->student_id,
            'team_id' => $team->team_id,
            's_role_id' => StudentRole::leaderRoleId(),
        ]);

        return $team;
    }

    private function seat(Team $team, string $role): void
    {
        StudentSchoolYear::create([
            'student_id' => $this->student()->student_id,
            'team_id' => $team->team_id,
            's_role_id' => $this->roleId($role),
        ]);
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

    private function roleId(string $name): int
    {
        return (int) StudentRole::where('s_role_name', $name)->value('s_role_id');
    }

    public function test_a_new_team_still_needs_both_member_roles(): void
    {
        $this->assertSame([self::DESIGNER, self::TESTER], TeamSeating::for($this->team())->openRoles());
    }

    public function test_a_role_with_every_seat_taken_is_no_longer_needed(): void
    {
        $team = $this->team();
        $this->seat($team, self::TESTER);
        $this->seat($team, self::TESTER);

        $this->assertSame([self::DESIGNER], TeamSeating::for($team)->openRoles());
    }

    /** The member picks their role after joining, so an invitation holds no role seat. */
    public function test_a_pending_invitation_does_not_hold_a_role_seat(): void
    {
        $team = $this->team();
        $this->seat($team, self::TESTER);
        TeamInvitation::create([
            'sender_id' => $team->leader_id,
            'receiver_id' => $this->student()->student_id,
            'team_id' => $team->team_id,
            'type' => 'invite',
            'status' => 'pending',
        ]);

        $this->assertSame([self::DESIGNER, self::TESTER], TeamSeating::for($team)->openRoles());
    }

    public function test_seats_follow_the_capsa_limit(): void
    {
        $team = $this->team();
        Setting::set('max_team_members', '3');
        $this->seat($team, self::DESIGNER);

        $this->assertSame([self::TESTER], TeamSeating::for($team)->openRoles());
    }

    public function test_one_advertised_role_with_a_seat_is_given_on_join(): void
    {
        $team = $this->team();
        $this->advertise($team, [self::TESTER]);

        $this->assertSame($this->roleId(self::TESTER), TeamSeating::for($team)->roleOnJoin());
    }

    public function test_two_advertised_roles_leave_the_choice_to_the_member(): void
    {
        $team = $this->team();
        $this->advertise($team, [self::DESIGNER, self::TESTER]);

        $this->assertNull(TeamSeating::for($team)->roleOnJoin());
    }

    public function test_with_no_post_nothing_is_given_on_join(): void
    {
        $this->assertNull(TeamSeating::for($this->team())->roleOnJoin());
    }

    public function test_an_advertised_role_that_is_full_is_not_given_on_join(): void
    {
        $team = $this->team();
        $this->advertise($team, [self::TESTER]);
        $this->seat($team, self::TESTER);
        $this->seat($team, self::TESTER);

        $this->assertNull(TeamSeating::for($team)->roleOnJoin());
    }

    public function test_choices_are_narrowed_to_what_the_post_asked_for(): void
    {
        $team = $this->team();
        $this->advertise($team, [self::TESTER]);

        $this->assertSame([self::TESTER], array_column(TeamSeating::for($team)->choices(), 'name'));
    }

    public function test_choices_fall_back_to_every_role_once_the_asked_role_is_full(): void
    {
        $team = $this->team();
        $this->advertise($team, [self::TESTER]);
        $this->seat($team, self::TESTER);
        $this->seat($team, self::TESTER);

        $choices = collect(TeamSeating::for($team)->choices())->keyBy('name');

        $this->assertSame([self::DESIGNER, self::TESTER], $choices->keys()->all());
        $this->assertFalse($choices[self::DESIGNER]['full']);
        $this->assertTrue($choices[self::TESTER]['full']);
        $this->assertSame(2, $choices[self::TESTER]['taken']);
        $this->assertSame(2, $choices[self::TESTER]['seats']);
        $this->assertCount(2, $choices[self::TESTER]['held_by']);
    }

    public function test_seats_follow_the_sections_team_rules(): void
    {
        $team = $this->team();
        Setting::set('max_team_members', '5');
        Setting::set(Team::RULES_KEY.$team->section(), json_encode(['max' => 3, 'create' => true, 'join' => true]));
        $this->seat($team, self::DESIGNER);

        $this->assertSame([self::TESTER], TeamSeating::for($team)->openRoles());
    }
}
