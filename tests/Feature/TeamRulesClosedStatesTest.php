<?php

namespace Tests\Feature;

use App\Models\Recruitment;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use Database\Seeders\StudentRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the Team page shows when the CaPSA/TSA's Team Rules close creating or
 * joining a team: a banner naming what is closed and what still works, and
 * every affected control locked. The server refuses the closed actions on
 * its own (TeamRulesTest); this is what the student sees before trying.
 */
class TeamRulesClosedStatesTest extends TestCase
{
    use RefreshDatabase;

    private const SECTION = 'BSIT-3B';

    private int $people = 0;

    protected function setUp(): void
    {
        parent::setUp();
        (new StudentRoleSeeder)->run();
    }

    private function classmate(string $first): Student
    {
        $this->people++;
        $student = Student::create([
            'email' => "mate{$this->people}@my.cspc.edu.ph", 'f_name' => $first, 'l_name' => 'Santos',
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · B', 'p_picture' => 'avatar-01',
        ]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'school_year' => 2026, 'program' => 'BSIT']);

        return $student;
    }

    private function rules(bool $create, bool $join): void
    {
        Setting::set(Team::RULES_KEY.self::SECTION, json_encode(['max' => 5, 'create' => $create, 'join' => $join]));
    }

    /** @return array{0: Team, 1: Student} a team of two building itself up, with a post */
    private function buildingTeam(): array
    {
        $leader = $this->classmate('Lea');
        $team = Team::create(['team_name' => 'Team Pidgey', 'leader_id' => $leader->student_id, 'team_status' => 'Active']);
        StudentSchoolYear::whereIn('student_id', [$leader->student_id, $this->classmate('Ian')->student_id])->update(['team_id' => $team->team_id]);
        Recruitment::create(['student_id' => $leader->student_id, 'recruitment_goal' => 'build_a_team', 'looking_for' => 'A tester', 'member_count' => 5]);

        return [$team, $leader];
    }

    private function seeker(): Student
    {
        $student = $this->classmate('Sam');
        Recruitment::create(['student_id' => $student->student_id, 'recruitment_goal' => 'find_a_team', 'introduction' => 'Hi', 'looking_for' => 'Hi', 'member_count' => 5]);

        return $student;
    }

    private function page(Student $viewer): string
    {
        return $this->actingAs($viewer, 'web')->get(route('student.team'))->assertOk()->getContent();
    }

    public function test_with_creating_closed_a_student_without_a_team_can_still_look_for_one(): void
    {
        $this->rules(create: false, join: true);
        $this->buildingTeam();

        $html = $this->page($this->classmate('Vic'));

        $this->assertStringContainsString('<b>Creating a team is closed</b> in '.self::SECTION, $html);
        $this->assertMatchesRegularExpression('/data-nt-build\s+disabled title="Creating a team is closed in '.self::SECTION.'"/', $html);
        $this->assertStringContainsString("onclick=\"openPostModal('individual')\"", $html);
        $this->assertStringContainsString('>Ask to join</button>', $html);
    }

    public function test_with_joining_closed_posts_say_closed_instead_of_ask_to_join(): void
    {
        $this->rules(create: true, join: false);
        $this->buildingTeam();

        $html = $this->page($this->classmate('Vic'));

        $this->assertStringContainsString('<b>Joining a team is closed</b> in '.self::SECTION, $html);
        $this->assertStringNotContainsString('>Ask to join</button>', $html);
        $this->assertStringContainsString('<span class="ts-state is-closed" title="Joining a team is closed in '.self::SECTION.'">', $html);
        $this->assertStringContainsString('onclick="openPostModal()"', $html, 'building a team still works');
    }

    public function test_with_joining_closed_a_leader_cannot_invite_but_can_still_cancel(): void
    {
        $this->rules(create: true, join: false);
        [$team, $leader] = $this->buildingTeam();
        $this->seeker();
        $invite = TeamInvitation::create(['sender_id' => $leader->student_id, 'receiver_id' => $this->classmate('Pia')->student_id, 'team_id' => $team->team_id, 'type' => 'invite', 'status' => 'pending']);

        $html = $this->page($leader);

        $this->assertStringContainsString('<b>Joining a team is closed</b> in '.self::SECTION.'. You can still cancel pending invites.', $html);
        $this->assertStringNotContainsString('>Invite to team</button>', $html);
        $this->assertStringContainsString('<span class="ts-state is-closed" title="Joining a team is closed in '.self::SECTION.'">', $html);
        $this->assertStringContainsString('Open slot</b><span>· Joining is closed', $html);
        $this->assertStringContainsString('cancelPendingInvite('.$invite->id, $html);
    }

    public function test_with_both_closed_a_student_without_a_team_is_told_to_speak_to_the_capsa(): void
    {
        $this->rules(create: false, join: false);

        $html = $this->page($this->classmate('Vic'));

        $this->assertStringContainsString('<b>Creating and joining teams are closed</b> in '.self::SECTION, $html);
        $this->assertStringNotContainsString('onclick="openPostModal()"', $html);
        $this->assertStringNotContainsString("onclick=\"openPostModal('individual')\"", $html);
    }

    public function test_a_member_sees_no_banner_while_joining_is_open(): void
    {
        $this->rules(create: false, join: true);
        [$team] = $this->buildingTeam();
        $member = Student::whereKeyNot($team->leader_id)->first();

        $this->assertStringNotContainsString('class="tm-closed"', $this->page($member));
    }

    public function test_a_sections_own_rules_win_over_the_school_wide_settings(): void
    {
        Setting::set('allow_team_joining', '0');
        Setting::set('allow_team_creation', '0');
        $this->rules(create: true, join: true);
        $this->buildingTeam();

        $html = $this->page($this->classmate('Vic'));

        $this->assertStringNotContainsString('class="nt-closed"', $html);
        $this->assertStringContainsString('>Ask to join</button>', $html);
    }
}
