<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefenseCancellation;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Tests\TestCase;

/**
 * "Export to Excel" on Defenses: the same address as the page itself, with
 * ?export=xlsx, streaming a spreadsheet with the department's own columns in
 * CapsTrack's look (App\Support\DefenseScheduleWorkbook) instead of the view.
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
            'Experts', null, 'Panel', null, null, 'Secretary', 'Similarity %',
        ], $sheet->rangeToArray('A5:O5')[0]);
    }

    /** A title band above the columns: what the sheet is, and how many teams stand where. */
    public function test_the_sheet_opens_with_its_title_and_counts(): void
    {
        $this->schedule($this->team('Alpha'), '2027-01-15 08:00:00');
        $this->team('Bravo');

        $sheet = $this->download()->getActiveSheet();

        $this->assertSame('Title Defense Schedule', $sheet->getCell('A1')->getValue());
        $this->assertStringContainsString('BSIT-3A', (string) $sheet->getCell('A2')->getValue());
        $counts = (string) $sheet->getCell('A3')->getValue();
        $this->assertStringContainsString('2 teams', $counts);
        $this->assertStringContainsString('1 scheduled', $counts);
        $this->assertStringContainsString('1 not scheduled', $counts);
    }

    public function test_pre_proposal_defense_has_no_fee_so_the_payment_header_says_so(): void
    {
        $sheet = $this->download(DefenseOutcome::PRE_PROPOSAL)->getActiveSheet();

        $this->assertSame('Payment', $sheet->getCell('F5')->getValue());
    }

    public function test_a_teams_block_carries_its_section_venue_title_and_status(): void
    {
        $team = $this->team('Aptenodytes');
        $this->schedule($team, '2027-01-15 08:00:00', DefenseOutcome::PASSED);

        $sheet = $this->download()->getActiveSheet();

        // The day's band, then the team: merged team-level cells keep their value on the block's first row.
        $this->assertSame('FRIDAY, JANUARY 15, 2027   ·   1 defense', $sheet->getCell('A6')->getValue());
        $this->assertSame('BSIT-3A', $sheet->getCell('A7')->getValue());
        $this->assertSame('Aptenodytes', $sheet->getCell('G7')->getValue());
        $this->assertSame('Aptenodytes Title', $sheet->getCell('H7')->getValue());
        $this->assertSame('Mac Lab', $sheet->getCell('C7')->getValue());
        $this->assertSame('PASSED', $sheet->getCell('D7')->getValue());
        $this->assertSame('8:00 AM – 10:00 AM', $sheet->getCell('B7')->getValue());
    }

    public function test_a_member_is_listed_and_the_role_labels_are_present(): void
    {
        $team = $this->team('Aptenodytes');
        $this->schedule($team);

        $sheet = $this->download()->getActiveSheet();
        $leader = StudentSchoolYear::where('team_id', $team->team_id)->first()->student;

        $this->assertSame($leader->l_name.', '.$leader->f_name, mb_substr((string) $sheet->getCell('E7')->getValue(), 0, mb_strlen($leader->l_name.', '.$leader->f_name)));
        $this->assertSame('Adviser:', $sheet->getCell('I7')->getValue());
        $this->assertSame('Chair:', $sheet->getCell('K7')->getValue());
    }

    public function test_a_returnee_is_marked_after_their_name(): void
    {
        $team = $this->team('Aptenodytes');
        $leader = StudentSchoolYear::where('team_id', $team->team_id)->first()->student;
        $leader->update(['is_returnee' => true]);

        $sheet = $this->download()->getActiveSheet();

        $this->assertStringEndsWith('(Returnee)', (string) $sheet->getCell('E7')->getValue());
        $this->assertStringContainsString('1 returnee', (string) $sheet->getCell('A3')->getValue());
    }

    public function test_a_team_with_no_schedule_waits_at_the_end(): void
    {
        $this->team('Bravo');

        $sheet = $this->download()->getActiveSheet();

        $this->assertSame('WAITING FOR A DATE   ·   1 team', $sheet->getCell('A6')->getValue());
        $this->assertSame('NOT SCHEDULED', $sheet->getCell('D7')->getValue());
        $this->assertSame('Not yet scheduled', $sheet->getCell('B7')->getValue());
    }

    public function test_a_defense_the_capa_cancelled_says_so_and_what_it_lost(): void
    {
        $team = $this->team('Bravo');
        $schedule = $this->schedule($team);
        DefenseCancellation::create(['defense_id' => $schedule->defense_id, 'team_id' => $team->team_id, 'defense_type' => self::STAGE, 'was_scheduled_at' => '2027-01-15 10:00:00', 'venue' => 'Mac Lab', 'note' => 'Chapter 3 not done', 'cancelled_by' => $this->capsa->expert_id]);

        $sheet = $this->download()->getActiveSheet();

        $this->assertSame('CANCELLED · WAITING FOR A NEW DATE', $sheet->getCell('D7')->getValue());
        $this->assertStringContainsString('Was Jan 15, 10:00 AM. CaPA: not ready (Chapter 3 not done).', (string) $sheet->getCell('B7')->getValue());
    }

    public function test_a_defense_cancelled_on_the_day_for_open_requirements_says_so(): void
    {
        $team = $this->team('Bravo');
        $schedule = $this->schedule($team);
        DefenseCancellation::create(['defense_id' => $schedule->defense_id, 'team_id' => $team->team_id, 'defense_type' => self::STAGE, 'was_scheduled_at' => '2027-01-15 10:00:00', 'venue' => 'Mac Lab', 'note' => 'Signed Hearing Notice']);

        $sheet = $this->download()->getActiveSheet();

        $this->assertStringContainsString('Was Jan 15, 10:00 AM. Requirements not complete (Signed Hearing Notice).', (string) $sheet->getCell('B7')->getValue());
    }

    public function test_a_team_up_for_a_redefense_says_so_while_it_waits(): void
    {
        $team = $this->team('Bravo');
        $this->schedule($team, '2027-01-15 08:00:00', DefenseOutcome::REDEFENSE);
        DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => self::STAGE, 'attempt' => 2]);

        $sheet = $this->download()->getActiveSheet();

        $this->assertSame('REDEFENSE', $sheet->getCell('D7')->getValue());
        $this->assertStringContainsString('Defended Jan 15.', (string) $sheet->getCell('B7')->getValue());
    }

    public function test_a_team_needs_at_least_three_rows_for_the_three_roles(): void
    {
        $team = $this->team('Solo');
        $this->schedule($team, '2027-01-15 08:00:00');
        $second = $this->team('Duo');
        $this->schedule($second, '2027-01-16 08:00:00');

        $sheet = $this->download()->getActiveSheet();

        // A 3-row block from row 7, the next day's band at row 10, then the second team.
        $this->assertSame('Solo', $sheet->getCell('G7')->getValue());
        $this->assertStringStartsWith('SATURDAY, JANUARY 16, 2027', (string) $sheet->getCell('A10')->getValue());
        $this->assertSame('Duo', $sheet->getCell('G11')->getValue());
    }

    /** Teams on the same day sit back to back under one band. */
    public function test_teams_sharing_a_date_share_one_band(): void
    {
        $first = $this->team('Solo');
        $this->schedule($first, '2027-01-15 08:00:00');
        $second = $this->team('Duo');
        $this->schedule($second, '2027-01-15 10:00:00');

        $sheet = $this->download()->getActiveSheet();

        $this->assertSame('FRIDAY, JANUARY 15, 2027   ·   2 defenses', $sheet->getCell('A6')->getValue());
        $this->assertSame('Solo', $sheet->getCell('G7')->getValue());
        $this->assertSame('Duo', $sheet->getCell('G10')->getValue());
    }

    /** Printed: the header repeats on every page, one page wide, on long bond paper. */
    public function test_it_prints_with_the_header_on_every_page(): void
    {
        $sheet = $this->download()->getActiveSheet();

        $this->assertSame([5, 5], $sheet->getPageSetup()->getRowsToRepeatAtTop());
        $this->assertSame(PageSetup::ORIENTATION_LANDSCAPE, $sheet->getPageSetup()->getOrientation());
        $this->assertSame(PageSetup::PAPERSIZE_FOLIO, $sheet->getPageSetup()->getPaperSize());
        $this->assertSame('E6', $sheet->getFreezePane());
    }

    public function test_export_is_scoped_to_the_capsas_sections(): void
    {
        $this->team('Mine', '3rd · A');
        $this->team('Theirs', '3rd · B');

        $sheet = $this->download()->getActiveSheet();
        $aliases = array_column($sheet->rangeToArray('G6:G30'), 0);

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
        $aliases = array_column(IOFactory::load($path)->getActiveSheet()->rangeToArray('G6:G30'), 0);

        $this->assertContains('SectionA', $aliases);
        $this->assertContains('SectionB', $aliases);
    }

    public function test_a_capsa_without_the_query_parameter_still_gets_the_page(): void
    {
        $this->actingAs($this->capsa, 'expert')
            ->get(route('capsa.defense_scheduling'))
            ->assertOk()
            ->assertViewIs('capsa.defenses');
    }
}
