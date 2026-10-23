<?php

namespace Tests\Feature;

use App\Models\MemberLeaveRequest;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentRole;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Database\Seeders\StudentRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Team page for a member of a team: the hero, the members table, asking
 * the CaPSA to leave, and Team selection folded away.
 */
class MemberScreenTest extends TestCase
{
    use RefreshDatabase;

    private int $people = 0;

    protected function setUp(): void
    {
        parent::setUp();
        (new StudentRoleSeeder)->run();
    }

    private function classmate(string $first, ?string $role = null): Student
    {
        $this->people++;
        $student = Student::create([
            'email' => "mate{$this->people}@my.cspc.edu.ph", 'f_name' => $first, 'l_name' => 'Cruz',
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · B', 'p_picture' => 'avatar-01',
        ]);
        StudentSchoolYear::create([
            'student_id' => $student->student_id, 'school_year' => 2026, 'program' => 'BSIT',
            's_role_id' => $role ? StudentRole::where('s_role_name', $role)->value('s_role_id') : null,
        ]);

        return $student;
    }

    /** @return array{0: Team, 1: Student} the team and the viewing member, who joined after two others */
    private function teamWithMember(): array
    {
        $leader = $this->classmate('Lea', 'Project Head/Database Designer');
        $team = Team::create(['team_name' => 'Team Pidgey', 'leader_id' => $leader->student_id, 'team_status' => 'Active', 'project_title' => 'Payroll for small offices']);
        $other = $this->classmate('Oscar', 'Network Designer/UI Designer');
        $viewer = $this->classmate('Vince', 'QA Tester/Documentation Writer');
        StudentSchoolYear::whereIn('student_id', [$leader->student_id, $other->student_id, $viewer->student_id])->update(['team_id' => $team->team_id]);

        return [$team, $viewer];
    }

    private function page(Student $viewer): string
    {
        return $this->actingAs($viewer, 'web')->get(route('student.team'))->assertOk()->getContent();
    }

    public function test_the_members_table_lists_the_leader_then_the_viewer_with_their_roles(): void
    {
        [, $viewer] = $this->teamWithMember();

        $html = $this->page($viewer);

        $this->assertMatchesRegularExpression('/Lea Cruz.*Project Head \/ Database Designer.*LEADER.*Vince Cruz.*QA Tester \/ Documentation Writer.*YOU.*Oscar Cruz.*Network Designer \/ UI Designer/s', $html);
    }

    public function test_the_hero_gives_the_title_the_members_against_the_section_limit_and_the_viewers_role(): void
    {
        [$team, $viewer] = $this->teamWithMember();
        Setting::set(Team::RULES_KEY.$team->section(), json_encode(['max' => 4, 'create' => true, 'join' => true]));

        $html = $this->page($viewer);

        $this->assertStringContainsString('Payroll for small offices', $html);
        $this->assertMatchesRegularExpression('/<dt>Members<\/dt>\s*<dd>3 \/ 4/', $html);
        $this->assertMatchesRegularExpression('/<dt>Your role<\/dt>\s*<dd>QA Tester \/ Documentation Writer<\/dd>/', $html);
        $this->assertStringContainsString('Open slot</b><span>· Waiting for a member', $html);
    }

    public function test_a_full_team_says_so_beside_the_members(): void
    {
        [$team, $viewer] = $this->teamWithMember();
        Setting::set(Team::RULES_KEY.$team->section(), json_encode(['max' => 3, 'create' => true, 'join' => true]));

        $this->assertStringContainsString('3 / 3 · Full', $this->page($viewer));
    }

    public function test_a_member_asks_the_capsa_to_leave_with_a_reason_long_enough(): void
    {
        [$team, $viewer] = $this->teamWithMember();

        $this->assertStringContainsString("onclick=\"openCapsaLeaveModal('leave')\"", $this->page($viewer));

        $this->actingAs($viewer, 'web')->postJson(route('team.capsa-leave-requests.store'), ['type' => MemberLeaveRequest::LEAVE, 'reason' => 'Too short'])
            ->assertUnprocessable();
        $this->actingAs($viewer, 'web')->postJson(route('team.capsa-leave-requests.store'), ['type' => MemberLeaveRequest::LEAVE, 'reason' => 'My family is moving to Cebu next month.'])
            ->assertCreated();

        $html = $this->page($viewer);
        $this->assertStringContainsString('Leave request with CaPSA', $html);
        $this->assertStringContainsString('Waiting on your CaPSA', $html);
        $this->assertSame(1, MemberLeaveRequest::where('team_id', $team->team_id)->count());
    }

    public function test_team_selection_is_folded_for_a_member(): void
    {
        [, $viewer] = $this->teamWithMember();

        $html = $this->page($viewer);

        $this->assertStringContainsString('data-ts-fold aria-expanded="false" aria-controls="team-selection"', $html);
        $this->assertMatchesRegularExpression('/id="team-selection"[^>]*\shidden\s*>/', $html);
        $this->assertStringContainsString('in BSIT-3B · 3 of 3 teamed up · No pending invites', $html);
    }

    public function test_a_leader_with_free_seats_sees_team_selection_open(): void
    {
        $leader = $this->classmate('Lea', 'Project Head/Database Designer');
        $team = Team::create(['team_name' => 'Team Pidgey', 'leader_id' => $leader->student_id, 'team_status' => 'Active']);
        StudentSchoolYear::where('student_id', $leader->student_id)->update(['team_id' => $team->team_id]);

        $html = $this->page($leader);

        $this->assertStringNotContainsString('class="ts-fold"', $html);
        $this->assertDoesNotMatchRegularExpression('/id="team-selection"[^>]*\shidden\s*>/', $html);
    }

    public function test_post_details_tell_a_member_they_cannot_join_or_invite(): void
    {
        [, $viewer] = $this->teamWithMember();

        $html = $this->page($viewer);

        $this->assertStringContainsString('const VIEWER_IS_LEADER = false;', $html);
        $this->assertStringContainsString('You’re already on a team, so you can’t join another one.', $html);
        $this->assertStringContainsString('Only your team leader can invite classmates.', $html);
    }
}
