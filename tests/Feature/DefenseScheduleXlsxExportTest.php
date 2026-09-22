<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Tests\TestCase;

/**
 * "Export to Excel" on Defense Scheduling: the same address as the page
 * itself, with ?export=xlsx, streaming a spreadsheet in the department's own
 * layout (App\Support\DefenseScheduleWorkbook) instead of rendering the view.
 *
 * These read the actual bytes that come back, the way a CaPSA would open
 * them in Excel - not just that a controller method ran.
 */
class DefenseScheduleXlsxExportTest extends TestCase
{
    use RefreshDatabase;

    private const STAGE = 'Title Defense';

    private CapstoneExpert $capsa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIT-3A']]));
    }

    private function team(string $name, string $section = '3rd · A'): Team
    {
        $leader = Student::factory()->withCompleteProfile()->create([
            'email' => strtolower($name).'@my.cspc.edu.ph',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => $section,
        ]);
        $team = Team::create([
            'team_name' => $name, 'project_title' => $name.' Title', 'team_status' => 'Active',
            'leader_id' => $leader->student_id, 'is_confirmed' => true, 'member_count' => 5,
        ]);
        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        return $team;
    }

    private function schedule(Team $team, ?string $when = null, ?string $outcome = null): DefenseSchedule
    {
        $schedule = DefenseSchedule::create([
            'team_id' => $team->team_id, 'defense_type' => self::STAGE, 'attempt' => 1,
            'schedule_date' => $when, 'end_date' => $when ? date('Y-m-d H:i:s', strtotime($when.' +2 hours')) : null,
            'venue' => $when ? 'Mac Lab' : null, 'outcome' => $outcome, 'outcome_recorded_at' => $outcome ? now() : null,
        ]);
        DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);

        return $schedule;
    }

    /** Downloads the file, saves it, and hands back the loaded workbook. */
    private function download(?string $stage = self::STAGE): Spreadsheet
    {
        $response = $this->actingAs($this->capsa, 'expert')
            ->get(route('capsa.defense_scheduling', array_filter(['defense_type' => $stage, 'export' => 'xlsx'])));

        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );

        $path = tempnam(sys_get_temp_dir(), 'sched').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        return IOFactory::load($path);
    }

    public function test_the_header_row_matches_the_departments_own_columns(): void
    {
        $sheet = $this->download()->getActiveSheet();

        $this->assertSame([
            'Section', 'Schedule', 'Venue', 'STATUS', 'Name', 'Title Defense Payment', 'Team Alias', 'Title',
            null, 'Experts', null, 'Panel', null, 'Secretary', 'Similarity %',
        ], $sheet->rangeToArray('A1:O1')[0]);
    }

    public function test_pre_proposal_defense_has_no_fee_so_the_payment_header_says_so(): void
    {
        $sheet = $this->download(DefenseOutcome::PRE_PROPOSAL)->getActiveSheet();

        $this->assertSame('Payment', $sheet->getCell('F1')->getValue());
    }

    public function test_a_teams_block_carries_its_section_venue_title_and_status(): void
    {
        $team = $this->team('Aptenodytes');
        $this->schedule($team, '2027-01-15 08:00:00', DefenseOutcome::PASSED);

        $sheet = $this->download()->getActiveSheet();

        // Merged team-level cells: the value lives on the block's first row.
        $this->assertSame('BSIT-3A', $sheet->getCell('A2')->getValue());
        $this->assertSame('Aptenodytes', $sheet->getCell('G2')->getValue());
        $this->assertSame('Aptenodytes Title', $sheet->getCell('H2')->getValue());
        $this->assertSame('Mac Lab', $sheet->getCell('C2')->getValue());
        $this->assertSame('PASSED', $sheet->getCell('D2')->getValue());
        $this->assertStringContainsString('Friday, January 15,', (string) $sheet->getCell('B2')->getValue());
    }

    public function test_a_member_is_listed_and_the_role_labels_are_present(): void
    {
        $team = $this->team('Aptenodytes');
        $this->schedule($team);

        $sheet = $this->download()->getActiveSheet();
        $leader = StudentSchoolYear::where('team_id', $team->team_id)->first()->student;

        $this->assertSame($leader->l_name.', '.$leader->f_name, mb_substr((string) $sheet->getCell('E2')->getValue(), 0, mb_strlen($leader->l_name.', '.$leader->f_name)));
        $this->assertSame('Adviser:', $sheet->getCell('I2')->getValue());
        $this->assertSame('Chair:', $sheet->getCell('K2')->getValue());
    }

    public function test_a_team_with_no_schedule_still_gets_a_block(): void
    {
        $this->team('Bravo');

        $sheet = $this->download()->getActiveSheet();

        $this->assertSame('NOT SCHEDULED', $sheet->getCell('D2')->getValue());
        $this->assertSame('Not yet scheduled', $sheet->getCell('B2')->getValue());
    }

    public function test_a_team_needs_at_least_three_rows_for_the_three_roles(): void
    {
        $team = $this->team('Solo');
        $this->schedule($team, '2027-01-15 08:00:00');

        // Row 5 is the spacer after a 3-row block starting at row 2; a second
        // team's block has to start at row 6, not row 5.
        $second = $this->team('Duo');
        $this->schedule($second, '2027-01-16 08:00:00');

        $sheet = $this->download()->getActiveSheet();

        $this->assertSame('Solo', $sheet->getCell('G2')->getValue());
        $this->assertSame('Duo', $sheet->getCell('G6')->getValue());
    }

    /**
     * The sample file the department already keeps only leaves a blank row
     * where the date changes; several teams defending the same day sit back
     * to back with nothing between them.
     */
    public function test_teams_sharing_a_date_are_not_separated_by_a_spacer_row(): void
    {
        $first = $this->team('Solo');
        $this->schedule($first, '2027-01-15 08:00:00');
        $second = $this->team('Duo');
        $this->schedule($second, '2027-01-15 10:00:00');

        $sheet = $this->download()->getActiveSheet();

        $this->assertSame('Solo', $sheet->getCell('G2')->getValue());
        $this->assertSame('Duo', $sheet->getCell('G5')->getValue(), 'no spacer row between two teams on the same day');
    }

    /** Row heights match the sample's own file, points for points. */
    public function test_row_heights_match_the_departments_own_file(): void
    {
        $first = $this->team('Solo');
        $this->schedule($first, '2027-01-15 08:00:00');
        $second = $this->team('Duo');
        $this->schedule($second, '2027-01-16 08:00:00');

        $sheet = $this->download()->getActiveSheet();

        $this->assertEqualsWithDelta(24.75, $sheet->getRowDimension(1)->getRowHeight(), 0.01);
        $this->assertEqualsWithDelta(15.75, $sheet->getRowDimension(2)->getRowHeight(), 0.01);
        $this->assertEqualsWithDelta(10.5, $sheet->getRowDimension(5)->getRowHeight(), 0.01, 'the spacer row, between the two dates');
    }

    /** "Experts" and "Panel" read as section titles over their columns, left-set rather than centred - the sample's own choice. */
    public function test_the_experts_and_panel_headers_are_left_aligned(): void
    {
        $sheet = $this->download()->getActiveSheet();

        $this->assertSame(Alignment::HORIZONTAL_GENERAL, $sheet->getStyle('J1')->getAlignment()->getHorizontal());
        $this->assertSame(Alignment::HORIZONTAL_GENERAL, $sheet->getStyle('L1')->getAlignment()->getHorizontal());
        $this->assertSame(Alignment::HORIZONTAL_CENTER, $sheet->getStyle('D1')->getAlignment()->getHorizontal());
    }

    public function test_export_is_scoped_to_the_capsas_sections(): void
    {
        $this->team('Mine', '3rd · A');
        $this->team('Theirs', '3rd · B');

        $sheet = $this->download()->getActiveSheet();
        $aliases = array_column($sheet->rangeToArray('G2:G20'), 0);

        $this->assertContains('Mine', $aliases);
        $this->assertNotContains('Theirs', $aliases);
    }

    public function test_export_reads_the_defense_type_query_parameter(): void
    {
        $team = $this->team('PreProposalTeam');
        DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => DefenseOutcome::PRE_PROPOSAL, 'attempt' => 1]);

        $book = $this->download(DefenseOutcome::PRE_PROPOSAL);

        $this->assertSame(DefenseOutcome::PRE_PROPOSAL, $book->getActiveSheet()->getTitle());
    }

    public function test_admin_sees_every_section(): void
    {
        // An Admin without sections of her own sees every team - the same
        // "no sections means unscoped" rule DefenseBoard::teamsFor() applies
        // everywhere else. Defense Scheduling is CaPSA-gated, so, as in
        // GenerateDefenseScheduleTest, this is an admin who also holds the
        // CaPSA role rather than a bare is_admin account.
        $admin = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]));
        $this->team('SectionA', '3rd · A');
        $this->team('SectionB', '3rd · B');

        $response = $this->actingAs($admin, 'expert')
            ->get(route('capsa.defense_scheduling', ['defense_type' => self::STAGE, 'export' => 'xlsx']));

        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'sched').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $aliases = array_column(IOFactory::load($path)->getActiveSheet()->rangeToArray('G2:G20'), 0);

        $this->assertContains('SectionA', $aliases);
        $this->assertContains('SectionB', $aliases);
    }

    public function test_a_capsa_without_the_query_parameter_still_gets_the_page(): void
    {
        $this->actingAs($this->capsa, 'expert')
            ->get(route('capsa.defense_scheduling'))
            ->assertOk()
            ->assertViewIs('capsa.defense_scheduling');
    }
}
