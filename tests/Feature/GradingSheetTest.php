<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\GradingSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * A panelist's rating sheet on paper - PDF or Excel - for a defense held
 * without signal or power. The panel grades by hand and enters the scores in
 * Team Grading once CapsTrack is back.
 */
class GradingSheetTest extends TestCase
{
    use RefreshDatabase;

    private DefenseSchedule $defense;

    private CapstoneExpert $chair;

    protected function setUp(): void
    {
        parent::setUp();

        // Dompdf and PhpSpreadsheet in one process outgrow PHP's default
        // 128M, and a test that runs out of memory dies without a word.
        ini_set('memory_limit', '512M');

        $rubric = GradingRubric::create(['rubric_name' => 'Title Defense Rubric', 'rubric_type' => 'Title Defense', 'total_points' => 30, 'is_active' => true]);
        $rubric->criteria()->create([
            'criterion_name' => 'Presentation', 'description' => 'Clear and organised delivery.', 'category' => 'Delivery',
            'category_max_points' => 20, 'max_points' => 20, 'sort_order' => 1,
            'score_levels' => json_encode([['score' => 20, 'label' => 'Excellent'], ['score' => 10, 'label' => 'Fair']]),
        ]);
        $rubric->criteria()->create([
            'criterion_name' => 'Answers to questions', 'category' => 'Defense',
            'category_max_points' => 10, 'max_points' => 10, 'sort_order' => 2, 'score_levels' => '[]',
        ]);

        $team = Team::create(['team_name' => 'Team1', 'project_title' => 'Clinic Queue System', 'team_status' => 'Active', 'is_confirmed' => true, 'member_count' => 5]);
        $student = Student::factory()->withCompleteProfile()->create(['f_name' => 'Maria', 'l_name' => 'Cruz']);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        $this->defense = DefenseSchedule::create([
            'team_id' => $team->team_id, 'defense_type' => 'Title Defense', 'attempt' => 1,
            'schedule_date' => now()->addDay()->setTime(9, 0), 'end_date' => now()->addDay()->setTime(10, 0), 'venue' => 'Mac Lab',
        ]);
        $this->chair = CapstoneExpert::factory()->create(['approved_status' => true, 'first_name' => 'Rosa', 'last_name' => 'Villanueva']);
        DefensePanel::create(['defense_id' => $this->defense->defense_id, 'expert_id' => $this->chair->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);
    }

    public function test_a_panelist_downloads_a_printable_pdf(): void
    {
        $response = $this->actingAs($this->chair, 'expert')
            ->get(route('instructor.grading.sheet', [$this->defense->defense_id, 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString('Team1 - Title Defense - Rating Sheet.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /** The department's rubric form: letterhead, title, date, alias, the rubric, verdict, remarks and the panel's signatures. */
    public function test_the_pdf_follows_the_departments_rubric_form(): void
    {
        $member = CapstoneExpert::factory()->create(['approved_status' => true, 'first_name' => 'Jose', 'last_name' => 'Reyes']);
        DefensePanel::create(['defense_id' => $this->defense->defense_id, 'expert_id' => $member->expert_id, 'panel_role' => 'Panel Member 1', 'created_at' => now()]);

        $sheet = app(GradingSheet::class)->for($this->defense->fresh(), GradingRubric::first(), $this->chair, DefensePanel::first());
        $html = view('instructor.pdf.grading-sheet', ['sheet' => $sheet])->render();

        foreach (['Republic of the Philippines', 'Camarines Sur Polytechnic Colleges', 'ISO 9001:2015 Certified',
            'Title', 'Clinic Queue System', 'Date', $this->defense->schedule_date->format('F j, Y'), 'Team Alias', 'Team1',
            'TITLE DEFENSE RUBRIC', 'Delivery', 'Excellent - Presentation', 'Fair - Presentation', 'Score', 'Total',
            'Verdict:', 'Remarks:', 'Jose Reyes', 'Panel Member 1', 'Panel Member 2', 'Rosa Villanueva', 'Panel Chairman',
            'Team Grading', 'Defense #'.$this->defense->defense_id] as $text) {
            $this->assertStringContainsString(e($text), $html, $text);
        }
        // The points in bold after each descriptor, as on the form.
        $this->assertStringContainsString('<b>(20pts)</b>', $html);
        $this->assertSame([20.0, 10.0], $sheet['sections'][0]['levelTotals'], 'each level\'s points for the category, best first');
        $this->assertSame('Jose Reyes', $sheet['signatures']['Panel Member 1']);
        $this->assertNull($sheet['signatures']['Panel Member 2'], 'an empty seat is a blank line');
    }

    public function test_the_excel_sheet_adds_itself_up(): void
    {
        $response = $this->actingAs($this->chair, 'expert')
            ->get(route('instructor.grading.sheet', [$this->defense->defense_id, 'xlsx']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'sheet').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $ws = IOFactory::load($path)->getActiveSheet();
        $cells = collect($ws->toArray(null, false, false))->flatten()->filter()->map(fn ($v) => (string) $v);

        $this->assertTrue($cells->contains('Excellent - Presentation (20pts)'));
        $this->assertTrue($cells->contains('TITLE DEFENSE RUBRIC'));
        $this->assertSame('landscape', $ws->getPageSetup()->getOrientation());

        // One score per category, as on the form: type them in and the total follows.
        $scoreCol = $ws->getHighestColumn();
        $scoreCells = [];
        $total = null;
        foreach ($ws->getRowIterator() as $row) {
            $r = $row->getRowIndex();
            if ($ws->getCell($scoreCol.$r)->getValue() === 'Score') {
                $scoreCells[] = $scoreCol.($r + 1);
            }
            if ($ws->getCell('A'.$r)->getValue() === 'Total') {
                $total = $scoreCol.$r;
            }
        }
        $this->assertCount(2, $scoreCells, 'Delivery and Defense');
        $ws->setCellValue($scoreCells[0], 18);
        $ws->setCellValue($scoreCells[1], 7);
        $this->assertEquals(25, $ws->getCell($total)->getCalculatedValue());
    }

    public function test_only_the_panel_gets_the_sheet(): void
    {
        $outsider = CapstoneExpert::factory()->create(['approved_status' => true]);

        $this->actingAs($outsider, 'expert')
            ->get(route('instructor.grading.sheet', [$this->defense->defense_id, 'pdf']))
            ->assertForbidden();
    }

    public function test_no_rubric_means_no_sheet(): void
    {
        GradingRubric::query()->update(['is_active' => false]);

        $this->actingAs($this->chair, 'expert')
            ->get(route('instructor.grading.sheet', [$this->defense->defense_id, 'pdf']))
            ->assertRedirect(route('instructor.grading'))
            ->assertSessionHas('error');
    }

    public function test_the_grading_pages_offer_the_paper_copy(): void
    {
        $pdf = route('instructor.grading.sheet', [$this->defense->defense_id, 'pdf']);
        $xlsx = route('instructor.grading.sheet', [$this->defense->defense_id, 'xlsx']);

        $this->actingAs($this->chair, 'expert')->get(route('instructor.grading'))->assertOk()
            ->assertSee($pdf, false)->assertSee($xlsx, false)
            ->assertSee('in case CapsTrack cannot be reached');

        $this->actingAs($this->chair, 'expert')->get(route('instructor.grading.show', $this->defense->defense_id))->assertOk()
            ->assertSee('Paper copy')
            ->assertSee($pdf, false)->assertSee($xlsx, false);
    }
}
