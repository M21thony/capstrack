<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Support\TeamManagementPage;
use Database\Seeders\StudentRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * What a student's Team Management page shows, asked of the one module that
 * assembles it: which screen they are on and that screen's data.
 */
class TeamManagementPageTest extends TestCase
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
        $student = Student::create([
            'email' => "student{$this->people}@my.cspc.edu.ph", 'f_name' => 'Student', 'l_name' => (string) $this->people,
            'course' => 'BSIT', 'year_section' => '3rd · A',
        ]);
        StudentSchoolYear::create(['student_id' => $student->student_id]);

        return $student;
    }

    /** @return array{0: Team, 1: Student, 2: Student} the team, its leader and one member */
    private function team(int $size): array
    {
        $leader = $this->student();
        $team = Team::create(['team_name' => 'Team '.$leader->student_id, 'leader_id' => $leader->student_id, 'team_status' => 'Active']);
        StudentSchoolYear::where('student_id', $leader->student_id)->update(['team_id' => $team->team_id]);

        $member = $leader;
        for ($i = 1; $i < $size; $i++) {
            $member = $this->student();
            StudentSchoolYear::where('student_id', $member->student_id)->update(['team_id' => $team->team_id]);
        }

        return [$team, $leader, $member];
    }

    public function test_a_student_without_a_team_is_on_the_no_team_screen(): void
    {
        $page = TeamManagementPage::for($this->student());

        $this->assertSame(TeamManagementPage::NO_TEAM, $page['screen']);
    }

    public function test_a_member_of_a_team_is_on_the_member_screen(): void
    {
        [, , $member] = $this->team(3);

        $this->assertSame(TeamManagementPage::MEMBER, TeamManagementPage::for($member)['screen']);
    }

    public function test_a_leader_with_free_seats_sees_the_roles_the_team_still_needs(): void
    {
        [, $leader] = $this->team(2);

        $page = TeamManagementPage::for($leader);

        $this->assertSame(TeamManagementPage::LEADER_OPEN, $page['screen']);
        $this->assertSame(['Network Designer/UI Designer', 'QA Tester/Documentation Writer'], $page['openRoles']);
    }

    public function test_a_leader_whose_team_reached_the_section_limit_is_on_the_full_screen(): void
    {
        [$team, $leader] = $this->team(3);
        Setting::set(Team::RULES_KEY.$team->section(), json_encode(['max' => 3, 'create' => true, 'join' => true]));

        $page = TeamManagementPage::for($leader);

        $this->assertSame(TeamManagementPage::LEADER_FULL, $page['screen']);
        $this->assertSame(3, $page['memberCount']);
        $this->assertSame([], $page['openRoles']);
    }

    public function test_team_selection_is_folded_for_a_member_and_open_for_a_leader_with_seats(): void
    {
        [, $leader, $member] = $this->team(2);

        $this->assertTrue(TeamManagementPage::for($member)['selectionFolded']);
        $this->assertFalse(TeamManagementPage::for($leader)['selectionFolded']);
    }

    public function test_open_slots_leave_out_the_seats_held_by_pending_invites(): void
    {
        [$team, $leader] = $this->team(2);
        TeamInvitation::create(['sender_id' => $leader->student_id, 'receiver_id' => $this->student()->student_id, 'team_id' => $team->team_id, 'type' => 'invite', 'status' => 'pending']);

        $this->assertSame(2, TeamManagementPage::for($leader)['teamSeatsFree']);
    }

    /**
     * The page lays out what it is given. A query from inside the view is a
     * rule living in the template, where nothing can test it.
     *
     * @return list<string>
     */
    private function queriesWhileRendering(Student $student): array
    {
        $this->actingAs($student, 'web');
        $data = TeamManagementPage::for($student);

        // The app's shell (sidebar, bell, tutorial) is shared by every student
        // page and fetches its own data; only the Team page's own markup is
        // under test, so the shell renders empty here.
        $shell = sys_get_temp_dir().'/team-page-shell-'.getmypid();
        @mkdir($shell.'/student/partials', 0777, true);
        foreach (['sidebar', 'notification-dropdown', 'page-tutorial'] as $partial) {
            file_put_contents("{$shell}/student/partials/{$partial}.blade.php", '');
        }
        View::getFinder()->prependLocation($shell);
        // The student view composer works out the shell's data once a request;
        // let it run before counting.
        view('student.partials.sidebar')->render();

        DB::flushQueryLog();
        DB::enableQueryLog();
        view('student.team', $data + ['errors' => new ViewErrorBag])->render();
        DB::disableQueryLog();

        return array_column(DB::getQueryLog(), 'query');
    }

    public function test_the_page_runs_no_queries_of_its_own_for_a_student_without_a_team(): void
    {
        $this->assertSame([], $this->queriesWhileRendering($this->student()));
    }

    public function test_the_page_runs_no_queries_of_its_own_for_a_member(): void
    {
        [, , $member] = $this->team(3);

        $this->assertSame([], $this->queriesWhileRendering($member));
    }

    public function test_the_page_runs_no_queries_of_its_own_for_a_leader(): void
    {
        [, $leader] = $this->team(2);

        $this->assertSame([], $this->queriesWhileRendering($leader));
    }
}
