<?php

namespace Tests\Feature;

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
 * The Team page for a student without a team: what to do first, and the
 * invites and requests waiting on them.
 */
class NoTeamScreenTest extends TestCase
{
    use RefreshDatabase;

    private int $people = 0;

    protected function setUp(): void
    {
        parent::setUp();
        (new StudentRoleSeeder)->run();
    }

    private function classmate(string $yearSection = '3rd · B'): Student
    {
        $this->people++;
        $student = Student::create([
            'email' => "mate{$this->people}@my.cspc.edu.ph", 'f_name' => 'Mate', 'l_name' => 'Number'.$this->people,
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => $yearSection, 'p_picture' => 'avatar-01',
        ]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'school_year' => 2026, 'program' => 'BSIT']);

        return $student;
    }

    /** A team of the given size whose leader is building it up. */
    private function team(int $size): Team
    {
        $leader = $this->classmate();
        $team = Team::create(['team_name' => 'Team Pidgey', 'leader_id' => $leader->student_id, 'team_status' => 'Active', 'project_title' => 'Payroll for small offices']);
        StudentSchoolYear::where('student_id', $leader->student_id)->update(['team_id' => $team->team_id]);
        for ($i = 1; $i < $size; $i++) {
            StudentSchoolYear::where('student_id', $this->classmate()->student_id)->update(['team_id' => $team->team_id]);
        }

        return $team;
    }

    private function page(Student $viewer): string
    {
        return $this->actingAs($viewer)->get(route('student.team'))->assertOk()->getContent();
    }

    public function test_the_hero_offers_building_a_team_and_posting_for_one(): void
    {
        $html = $this->page($this->classmate());

        $this->assertStringContainsString('You’re not in a team yet', $html);
        $this->assertStringContainsString('onclick="openPostModal()"', $html);
        $this->assertStringContainsString('onclick="openPostModal(\'individual\')"', $html);
        $this->assertStringContainsString('No pending invites', $html);
    }

    public function test_the_hero_and_post_form_give_the_section_member_limit(): void
    {
        $viewer = $this->classmate();
        Setting::set(Team::RULES_KEY.$viewer->sectionLabel(), json_encode(['max' => 4, 'create' => true, 'join' => true]));

        $html = $this->page($viewer);

        $this->assertStringContainsString('Teams have up to 4 members.', $html);
        $this->assertStringContainsString('Your team can have up to 4 members including you', $html);
        $this->assertStringNotContainsString('CapsTip', $html);
    }

    public function test_an_invite_shows_its_team_and_its_details_list_the_open_roles(): void
    {
        $viewer = $this->classmate();
        $team = $this->team(3);
        $invite = TeamInvitation::create(['sender_id' => $team->leader_id, 'receiver_id' => $viewer->student_id, 'team_id' => $team->team_id, 'type' => 'invite', 'status' => 'pending']);

        $html = $this->page($viewer);

        $this->assertStringContainsString('1 pending', $html);
        $this->assertStringContainsString('Team Pidgey · 3 / 5', $html);
        $this->assertStringContainsString('id="invite-detail-'.$invite->id.'"', $html);
        $this->assertStringContainsString('Payroll for small offices', $html);
        $this->assertStringContainsString('<b>Network Designer / UI Designer</b>', $html);
        $this->assertStringContainsString('<b>QA Tester / Documentation Writer</b>', $html);
        $this->assertStringContainsString('id="decline-form-'.$invite->id.'"', $html);
    }

    public function test_an_invite_lists_only_the_roles_that_still_have_seats(): void
    {
        $viewer = $this->classmate();
        $team = $this->team(1);
        $tester = StudentRole::where('s_role_name', 'QA Tester/Documentation Writer')->value('s_role_id');
        foreach ([1, 2] as $i) {
            StudentSchoolYear::where('student_id', $this->classmate()->student_id)->update(['team_id' => $team->team_id, 's_role_id' => $tester]);
        }
        TeamInvitation::create(['sender_id' => $team->leader_id, 'receiver_id' => $viewer->student_id, 'team_id' => $team->team_id, 'type' => 'invite', 'status' => 'pending']);

        $html = $this->page($viewer);

        $this->assertStringContainsString('<b>Network Designer / UI Designer</b>', $html);
        $this->assertStringNotContainsString('<b>QA Tester / Documentation Writer</b>', $html);
    }

    public function test_a_request_the_student_sent_waits_in_the_invites_card(): void
    {
        $viewer = $this->classmate();
        $team = $this->team(2);
        TeamInvitation::create(['sender_id' => $viewer->student_id, 'receiver_id' => $team->leader_id, 'team_id' => $team->team_id, 'type' => 'request', 'status' => 'pending']);

        $html = $this->page($viewer);

        $this->assertStringContainsString('You asked to join <b>Team Pidgey</b>', $html);
        $this->assertStringContainsString('PENDING', $html);
        $this->assertStringNotContainsString('No pending invites', $html);
    }

    public function test_in_capstone_2_the_hero_says_team_forming_is_closed(): void
    {
        $html = $this->page($this->classmate('4th · B'));

        $this->assertStringContainsString('Capstone 2 has started', $html);
        $this->assertStringNotContainsString('data-nt-build', $html);
    }

    public function test_with_creating_closed_build_a_team_is_locked_and_posting_for_a_team_still_works(): void
    {
        $viewer = $this->classmate();
        Setting::set(Team::RULES_KEY.$viewer->sectionLabel(), json_encode(['max' => 5, 'create' => false, 'join' => true]));

        $html = $this->page($viewer);

        $this->assertStringContainsString('<b>Creating a team is closed</b> in BSIT-3B', $html);
        $this->assertMatchesRegularExpression('/data-nt-build\s+disabled title="Creating a team is closed in BSIT-3B"/', $html);
        $this->assertStringContainsString('onclick="openPostModal(\'individual\')"', $html);
    }

    public function test_with_joining_closed_an_invite_cannot_be_accepted_but_can_be_declined(): void
    {
        $viewer = $this->classmate();
        Setting::set(Team::RULES_KEY.$viewer->sectionLabel(), json_encode(['max' => 5, 'create' => true, 'join' => false]));
        $team = $this->team(2);
        $invite = TeamInvitation::create(['sender_id' => $team->leader_id, 'receiver_id' => $viewer->student_id, 'team_id' => $team->team_id, 'type' => 'invite', 'status' => 'pending']);

        $html = $this->page($viewer);

        $this->assertStringContainsString('<b>Joining a team is closed</b> in BSIT-3B', $html);
        $this->assertStringNotContainsString('acceptInvite('.$invite->id, $html);
        $this->assertStringContainsString('openDeclineModal('.$invite->id, $html);
        $this->assertStringContainsString('onclick="openPostModal()"', $html);
    }
}
