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
use Illuminate\Support\Facades\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * The team's rating sheet on paper - PDF or Excel - that the team prints, one
 * for each panelist, and brings to the defense. If CapsTrack cannot be reached
 * there, the panel grades by hand and enters the scores in Team Grading later.
 */
class GradingSheetTest extends TestCase
{
    use BuildsChecklists, RefreshDatabase;

    private DefenseSchedule $defense;

    private Student $student;

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

        // The leader puts the team in BSIT-3A, the section capsaFor() handles.
        $this->student = Student::factory()->withCompleteProfile()->create([
            'f_name' => 'Maria', 'l_name' => 'Cruz',
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
        ]);
        $team = Team::create(['team_name' => 'Team1', 'project_title' => 'Clinic Queue System', 'team_status' => 'Active', 'is_confirmed' => true, 'member_count' => 5, 'leader_id' => $this->student->student_id]);
        StudentSchoolYear::create(['student_id' => $this->student->student_id, 'team_id' => $team->team_id]);

        $this->defense = DefenseSchedule::create([
            'team_id' => $team->team_id, 'defense_type' => 'Title Defense', 'attempt' => 1,
            'schedule_date' => now()->addDay()->setTime(9, 0), 'end_date' => now()->addDay()->setTime(10, 0), 'venue' => 'Mac Lab',
        ]);
        $this->chair = CapstoneExpert::factory()->create(['approved_status' => true, 'first_name' => 'Rosa', 'last_name' => 'Villanueva']);
        DefensePanel::create(['defense_id' => $this->defense->defense_id, 'expert_id' => $this->chair->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);
    }

    public function test_the_team_downloads_a_printable_pdf(): void
    {
        $response = $this->actingAs($this->student, 'web')
            ->get(route('student.defense.sheet', [$this->defense->defense_id, 'pdf']))
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

        $sheet = app(GradingSheet::class)->for($this->defense->fresh(), GradingRubric::first());
        $html = view('instructor.pdf.grading-sheet', ['sheet' => $sheet])->render();

        foreach (['Republic of the Philippines', 'Camarines Sur Polytechnic Colleges', 'ISO 9001:2015 Certified',
            'Title', 'Clinic Queue System', 'Date', $this->defense->schedule_date->format('F j, Y'), 'Team Alias', 'Team1',
            'TITLE DEFENSE RUBRIC', 'Delivery', 'Excellent - Presentation', 'Fair - Presentation', 'Score', 'Total',
            'Verdict:', 'Remarks:', 'Jose Reyes', 'Panel Member 1', 'Panel Member 2', 'Rosa Villanueva', 'Panel Chairman',
            'one for each panelist', 'Team Grading', 'Defense #'.$this->defense->defense_id] as $text) {
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
        $response = $this->actingAs($this->student, 'web')
            ->get(route('student.defense.sheet', [$this->defense->defense_id, 'xlsx']))
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

    /** The Resources page's rubric, once the team is scheduled for that stage, names its title and panel. */
    public function test_the_resources_rubric_names_the_teams_panel(): void
    {
        $member = CapstoneExpert::factory()->create(['approved_status' => true, 'first_name' => 'Jose', 'last_name' => 'Reyes']);
        DefensePanel::create(['defense_id' => $this->defense->defense_id, 'expert_id' => $member->expert_id, 'panel_role' => 'Panel Member 1', 'created_at' => now()]);

        $sheet = null;
        View::composer('instructor.pdf.grading-sheet', function ($view) use (&$sheet) {
            $sheet = $view->getData()['sheet'];
        });

        $this->actingAs($this->student, 'web')->get(route('student.resources.rubric', 'title-defense'))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->assertSame('Clinic Queue System', $sheet['title']);
        $this->assertSame('Team1', $sheet['team']);
        $this->assertSame('Jose Reyes', $sheet['signatures']['Panel Member 1']);
        $this->assertSame('Rosa Villanueva', $sheet['signatures']['Panel Chair']);
    }

    /** Another team's defense is not found, the same as one that does not exist. */
    public function test_only_the_team_gets_its_sheet(): void
    {
        $other = Student::factory()->withCompleteProfile()->create();
        $otherTeam = Team::create(['team_name' => 'Team2', 'team_status' => 'Active', 'is_confirmed' => true, 'member_count' => 5]);
        StudentSchoolYear::create(['student_id' => $other->student_id, 'team_id' => $otherTeam->team_id]);
        $noTeam = Student::factory()->withCompleteProfile()->create();

        foreach ([$other, $noTeam] as $student) {
            $this->actingAs($student, 'web')
                ->get(route('student.defense.sheet', [$this->defense->defense_id, 'pdf']))
                ->assertNotFound();
        }
    }

    public function test_no_rubric_means_no_sheet(): void
    {
        GradingRubric::query()->update(['is_active' => false]);

        $this->actingAs($this->student, 'web')
            ->get(route('student.defense.sheet', [$this->defense->defense_id, 'pdf']))
            ->assertNotFound();
    }

    /** The team prints the sheets, not the panel: the dashboard offers them, Team Grading does not. */
    public function test_the_dashboard_offers_the_sheet_and_team_grading_does_not(): void
    {
        $pdf = route('student.defense.sheet', [$this->defense->defense_id, 'pdf']);
        $xlsx = route('student.defense.sheet', [$this->defense->defense_id, 'xlsx']);

        // The dashboard's next-defense line shows once the section has a checklist.
        $checklist = $this->emptyChecklist($this->capsaFor());
        $this->addDeliverable($checklist, ['title' => 'Chapter 1']);

        $this->actingAs($this->student, 'web')->get(route('student.dashboard'))->assertOk()
            ->assertSee('Print one rating sheet for each panelist')
            ->assertSee($pdf, false)->assertSee($xlsx, false);

        $this->actingAs($this->chair, 'expert')->get(route('instructor.grading'))->assertOk()
            ->assertDontSee('sheet.pdf', false)->assertDontSee('in case CapsTrack cannot be reached');
        $this->actingAs($this->chair, 'expert')->get(route('instructor.grading.show', $this->defense->defense_id))->assertOk()
            ->assertDontSee('Paper copy')->assertDontSee('sheet.pdf', false);
    }
}
