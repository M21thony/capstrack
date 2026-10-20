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
 * The Team page for a leader whose team still has seats: pending invites in
 * the members table, a row per open slot, and what the team still needs.
 */
class LeaderScreenTest extends TestCase
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
            'email' => "mate{$this->people}@my.cspc.edu.ph", 'f_name' => $first, 'l_name' => 'Reyes',
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · B', 'p_picture' => 'avatar-01',
        ]);
        StudentSchoolYear::create([
            'student_id' => $student->student_id, 'school_year' => 2026, 'program' => 'BSIT',
            's_role_id' => $role ? StudentRole::where('s_role_name', $role)->value('s_role_id') : null,
        ]);

        return $student;
    }

    /** @return array{0: Team, 1: Student} a team of three (leader, a UI designer, a tester) and its leader */
    private function teamOfThree(): array
    {
        $leader = $this->classmate('Lea', 'Project Head/Database Designer');
        $team = Team::create(['team_name' => 'Team Bulbasaur', 'leader_id' => $leader->student_id, 'team_status' => 'Active']);
        $ui = $this->classmate('Ian', 'Network Designer/UI Designer');
        $qa = $this->classmate('Alexa', 'QA Tester/Documentation Writer');
        StudentSchoolYear::whereIn('student_id', [$leader->student_id, $ui->student_id, $qa->student_id])->update(['team_id' => $team->team_id]);

        return [$team, $leader];
    }

    private function invite(Team $team, Student $leader, Student $to): TeamInvitation
    {
        return TeamInvitation::create(['sender_id' => $leader->student_id, 'receiver_id' => $to->student_id, 'team_id' => $team->team_id, 'type' => 'invite', 'status' => 'pending']);
    }

    private function page(Student $viewer): string
    {
        return $this->actingAs($viewer, 'web')->get(route('student.team'))->assertOk()->getContent();
    }

    public function test_a_pending_invite_is_listed_with_its_cancel_and_holds_a_seat(): void
    {
        [$team, $leader] = $this->teamOfThree();
        $invite = $this->invite($team, $leader, $this->classmate('Angela'));

        $html = $this->page($leader);

        $this->assertMatchesRegularExpression('/Angela Reyes<\/b>.*Picks a role after joining.*PENDING.*cancelPendingInvite\('.$invite->id.'/s', $html);
        $this->assertSame(1, substr_count($html, 'Open slot</b><span>· Invite a classmate'));
        $this->assertMatchesRegularExpression('/<dt>Invites sent<\/dt>\s*<dd>1 pending<\/dd>/', $html);
        $this->assertStringContainsString('Forming · 2 slots open', $html);
    }

    public function test_cancelling_a_pending_invite_frees_its_seat(): void
    {
        [$team, $leader] = $this->teamOfThree();
        $invite = $this->invite($team, $leader, $this->classmate('Angela'));

        $this->actingAs($leader, 'web')->patchJson(route('invitations.update', $invite), ['status' => 'declined'])->assertOk();

        $this->assertSame(2, substr_count($this->page($leader), 'Open slot</b><span>· Invite a classmate'));
    }

    public function test_an_open_slot_opens_the_classmate_picker(): void
    {
        [, $leader] = $this->teamOfThree();

        $html = $this->page($leader);

        $this->assertStringContainsString('onclick="openOpenSlotInviteModal()" aria-haspopup="dialog"', $html);
        $this->assertStringContainsString('id="openSlotInviteModal"', $html);
        $this->assertStringContainsString('They pick their role after joining.', $html);
        $this->assertMatchesRegularExpression('/id="osl-send" disabled/', $html);
    }

    public function test_the_team_still_needs_only_the_roles_with_free_seats(): void
    {
        [$team, $leader] = $this->teamOfThree();
        $tester = $this->classmate('Carlo', 'QA Tester/Documentation Writer');
        StudentSchoolYear::where('student_id', $tester->student_id)->update(['team_id' => $team->team_id]);

        $html = $this->page($leader);

        $this->assertStringContainsString('<span>Your team still needs</span><b>Network Designer / UI Designer</b></div>', $html);
    }

    public function test_team_selection_opens_on_students_looking_for_a_team(): void
    {
        [, $leader] = $this->teamOfThree();

        $html = $this->page($leader);

        $this->assertStringContainsString('data-ts-goal="find_a_team"', $html);
        $this->assertMatchesRegularExpression('/data-ts-goal="find_a_team" aria-pressed="true"/', $html);
    }

    public function test_with_joining_closed_the_open_slots_say_so_and_cannot_be_clicked(): void
    {
        [$team, $leader] = $this->teamOfThree();
        Setting::set(Team::RULES_KEY.$team->section(), json_encode(['max' => 5, 'create' => true, 'join' => false]));

        $html = $this->page($leader);

        $this->assertSame(2, substr_count($html, 'Open slot</b><span>· Joining is closed'));
        $this->assertStringNotContainsString('onclick="openOpenSlotInviteModal()"', $html);
    }

    public function test_a_full_team_draws_no_open_slots(): void
    {
        [$team, $leader] = $this->teamOfThree();
        Setting::set(Team::RULES_KEY.$team->section(), json_encode(['max' => 3, 'create' => true, 'join' => true]));

        $html = $this->page($leader);

        $this->assertStringNotContainsString('Open slot</b>', $html);
        $this->assertStringContainsString('3 / 3 · Full', $html);
        $this->assertStringContainsString('class="ts-fold"', $html);
    }
}
