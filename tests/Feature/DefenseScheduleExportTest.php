<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\DefenseWindow;
use App\Models\DuplicationCheck;
use App\Models\ExpensePost;
use App\Models\ExpertRole;
use App\Models\FinancialRecord;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Support\DefenseOutcome;
use App\Support\DefenseScheduleExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Defense Scheduling summary's data: one row-group per team, in the
 * shape the department's own spreadsheet (TD_2526 - Schedule - CCS.xlsx)
 * expects - status, venue, members, experts, panel and similarity, all read
 * from the same records Defense Scheduling and Panel Assignment already use.
 */
class DefenseScheduleExportTest extends TestCase
{
    use RefreshDatabase;

    private const STAGE = 'Title Defense';

    private CapstoneExpert $capsa;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIT-3A']]));
    }

    private function export(): DefenseScheduleExport
    {
        return app(DefenseScheduleExport::class);
    }

    /** A confirmed team with $memberCount members, in the CaPSA/TSA's own section unless told otherwise. */
    private function team(string $name, int $memberCount = 3, string $section = '3rd · A'): Team
    {
        $this->seq++;
        $members = [];

        for ($i = 0; $i < $memberCount; $i++) {
            $members[] = Student::factory()->withCompleteProfile()->create([
                'email' => "member{$this->seq}_{$i}@my.cspc.edu.ph",
                'course' => 'Bachelor of Science in Information Technology',
                'year_section' => $section,
            ]);
        }

        $team = Team::create([
            'team_name' => $name,
            'project_title' => $name.' Title',
            'team_status' => 'Active',
            'leader_id' => $members[0]->student_id,
            'is_confirmed' => true,
            'member_count' => 5,
        ]);

        foreach ($members as $member) {
            StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id]);
        }

        return $team;
    }

    private function expert(Team $team, string $role): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        $roleModel = Role::firstOrCreate(['role_name' => $role]);
        $expert->roles()->attach($roleModel->role_id);
        TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $expert->expert_id,
            'status' => 'approved',
            'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)->where('role_id', $roleModel->role_id)->value('expert_role_id'),
        ]);

        return $expert;
    }

    private function panel(DefenseSchedule $schedule, CapstoneExpert $chair): void
    {
        DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => $chair->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);
    }

    private function schedule(Team $team, ?string $when = null, ?string $outcome = null, string $stage = self::STAGE): DefenseSchedule
    {
        return DefenseSchedule::create([
            'team_id' => $team->team_id, 'defense_type' => $stage, 'attempt' => 1,
            'schedule_date' => $when, 'end_date' => $when ? date('Y-m-d H:i:s', strtotime($when.' +2 hours')) : null,
            'venue' => $when ? 'Mac Lab' : null, 'outcome' => $outcome, 'outcome_recorded_at' => $outcome ? now() : null,
        ]);
    }

    // -- status ---------------------------------------------------------

    public function test_a_team_with_no_schedule_is_not_scheduled(): void
    {
        $this->team('Alpha');

        $status = $this->export()->rows($this->capsa, self::STAGE)[0]['status'];

        $this->assertSame('not_scheduled', $status['key']);
        $this->assertSame('NOT SCHEDULED', $status['label']);
        $this->assertSame('none', $status['tone']);
    }

    public function test_a_scheduled_team_with_no_outcome_and_a_future_date_is_ongoing(): void
    {
        $team = $this->team('Alpha');
        $this->schedule($team, now()->addDay()->format('Y-m-d H:i:s'));

        $status = $this->export()->rows($this->capsa, self::STAGE)[0]['status'];

        $this->assertSame('ongoing', $status['key']);
        $this->assertSame('ongoing', $status['tone']);
    }

    public function test_a_scheduled_team_with_no_outcome_and_a_past_date_is_awaiting_result(): void
    {
        $team = $this->team('Alpha');
        $this->schedule($team, now()->subDay()->format('Y-m-d H:i:s'));

        $status = $this->export()->rows($this->capsa, self::STAGE)[0]['status'];

        $this->assertSame('awaiting', $status['key']);
        $this->assertSame('AWAITING RESULT', $status['label']);
        $this->assertSame('major', $status['tone']);
    }

    public function test_a_passed_team_carries_the_pass_tone(): void
    {
        $team = $this->team('Alpha');
        $this->schedule($team, now()->subDay()->format('Y-m-d H:i:s'), DefenseOutcome::PASSED);

        $status = $this->export()->rows($this->capsa, self::STAGE)[0]['status'];

        $this->assertSame('PASSED', $status['label']);
        $this->assertSame('pass', $status['tone']);
    }

    public function test_a_redefense_team_carries_the_major_tone_and_the_windows_own_deadline(): void
    {
        DefenseWindow::create(['defense_type' => self::STAGE, 'start_date' => '2027-01-01', 'end_date' => '2027-02-20']);
        $team = $this->team('Alpha');
        $this->schedule($team, '2027-01-15 08:00:00', DefenseOutcome::REDEFENSE);

        $status = $this->export()->rows($this->capsa, self::STAGE)[0]['status'];

        $this->assertSame('major', $status['tone']);
        $this->assertStringContainsString('REDEFENSE', $status['label']);
        $this->assertStringContainsString('UNTIL FEB 20', $status['label']);
    }

    public function test_a_redefense_with_a_new_title_carries_the_fail_tone(): void
    {
        $team = $this->team('Alpha');
        $this->schedule($team, '2027-01-15 08:00:00', DefenseOutcome::REDEFENSE_NEW_TITLE);

        $status = $this->export()->rows($this->capsa, self::STAGE)[0]['status'];

        $this->assertSame('fail', $status['tone']);
    }

    // -- members ----------------------------------------------------------

    public function test_members_are_listed_lastname_first_with_a_middle_initial(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'f_name' => 'Juan', 'm_name' => 'Dela', 'l_name' => 'Cruz',
            'email' => 'juan@my.cspc.edu.ph', 'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
        ]);
        $team = Team::create(['team_name' => 'Alpha', 'team_status' => 'Active', 'leader_id' => $student->student_id, 'is_confirmed' => true, 'member_count' => 5]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        $members = $this->export()->rows($this->capsa, self::STAGE)[0]['members'];

        $this->assertSame('Cruz, Juan D.', $members[0]['name']);
    }

    public function test_row_count_is_at_least_three_even_with_one_member(): void
    {
        $team = $this->team('Alpha', memberCount: 1);

        $this->assertSame(3, $this->export()->rows($this->capsa, self::STAGE)[0]['row_count']);
    }

    public function test_row_count_grows_past_three_with_more_members(): void
    {
        $team = $this->team('Alpha', memberCount: 5);

        $this->assertSame(5, $this->export()->rows($this->capsa, self::STAGE)[0]['row_count']);
    }

    // -- payment ------------------------------------------------------------

    public function test_paid_is_tracked_per_member_for_a_paid_stage(): void
    {
        $team = $this->team('Alpha', memberCount: 2);
        $post = ExpensePost::create(['title' => 'TD fee', 'amount' => 500, 'defense_type' => self::STAGE]);
        $paidStudent = StudentSchoolYear::where('team_id', $team->team_id)->first()->student_id;

        FinancialRecord::create([
            'student_id' => $paidStudent, 'expense_post_id' => $post->id, 'receipt_image' => 'r.jpg',
            'f_record_status' => 'verified', 'verified_at' => now(),
        ]);

        $members = collect($this->export()->rows($this->capsa, self::STAGE)[0]['members']);

        // One member paid, the other did not - a spreadsheet a CaPSA/TSA hands to
        // the office is exactly where the one still owing has to be named.
        $this->assertTrue($members->contains(fn ($m) => $m['paid'] === true));
        $this->assertTrue($members->contains(fn ($m) => $m['paid'] === false));
    }

    public function test_pre_proposal_defense_has_no_payment_tracked(): void
    {
        $this->team('Alpha', memberCount: 1);

        $members = $this->export()->rows($this->capsa, DefenseOutcome::PRE_PROPOSAL)[0]['members'];

        $this->assertNull($members[0]['paid']);
    }

    // -- similarity ------------------------------------------------------

    public function test_similarity_comes_from_the_selected_pre_proposal(): void
    {
        $team = $this->team('Alpha');
        $sy = StudentSchoolYear::where('team_id', $team->team_id)->first();
        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'x.pdf', 'concepts' => ['title' => 'x'],
            'top_score' => 27, 'verdict' => 'Low Similarity', 'matches' => [], 'instructors_suggested' => [],
        ]);
        $proposal = CapstonePreProposal::create([
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1, 'title' => 'A Title',
            'sdg_goals' => 'SDG 4', 'research_agenda' => ['Applications'], 'problem_statement' => 'p', 'proposed_solution' => 's',
            'scope' => 's', 'limitations' => 'l', 'sdm' => 'Agile', 'approved_status' => true, 'submitted_at' => now(),
        ]);
        $team->update(['selected_pre_proposal_id' => $proposal->id]);

        $this->assertSame(27, $this->export()->rows($this->capsa, self::STAGE)[0]['similarity']);
    }

    public function test_similarity_is_null_with_no_chosen_pre_proposal(): void
    {
        $this->team('Alpha');

        $this->assertNull($this->export()->rows($this->capsa, self::STAGE)[0]['similarity']);
    }

    // -- experts and panel ---------------------------------------------------

    public function test_experts_and_panel_names_are_included(): void
    {
        $team = $this->team('Alpha');
        $adviser = $this->expert($team, 'adviser');
        $adviser->update(['first_name' => 'Ana', 'last_name' => 'Reyes']);
        $schedule = $this->schedule($team, '2027-01-15 08:00:00');
        $this->panel($schedule, $adviser);

        $row = $this->export()->rows($this->capsa, self::STAGE)[0];

        $this->assertStringContainsString('Reyes', $row['experts']['adviser']);
        $this->assertStringContainsString('Reyes', $row['panel']['chair']);
    }

    // -- scope ---------------------------------------------------------------

    public function test_a_team_outside_the_capsas_sections_is_left_out(): void
    {
        $this->team('Mine', section: '3rd · A');
        $this->team('Theirs', section: '3rd · B');

        $names = collect($this->export()->rows($this->capsa, self::STAGE))->pluck('alias');

        $this->assertSame(['Mine'], $names->all());
    }
}
