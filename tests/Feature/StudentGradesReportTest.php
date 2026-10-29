<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\PanelGrade;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Panel comments 14 and 17: a CaPSA report with one row per student - their
 * team's panel average at each defense, the average so far and the team's
 * requirements done - downloadable like every other report.
 */
class StudentGradesReportTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private function member(Team $team, string $first, string $last, string $yearSection = '3rd · A'): Student
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => strtolower($first.'.'.$last).'.'.uniqid().'@my.cspc.edu.ph',
            'f_name' => $first,
            'l_name' => $last,
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => $yearSection,
        ]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        return $student;
    }

    /** A defense on a rubric out of $points; a null total is a panelist who has not graded yet. */
    private function defense(Team $team, string $stage, int $points, array $totals): DefenseSchedule
    {
        $rubric = GradingRubric::create(['rubric_name' => $stage.' rubric', 'rubric_type' => $stage, 'total_points' => $points, 'is_active' => true]);
        $defense = DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => $stage, 'schedule_date' => now()->subDay()]);

        foreach (array_values($totals) as $i => $total) {
            $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
            DefensePanel::create(['defense_id' => $defense->defense_id, 'expert_id' => $expert->expert_id, 'panel_role' => $i ? 'Panel Member '.$i : 'Panel Chair', 'created_at' => now()]);
            if ($total !== null) {
                $this->grade($defense, $expert->expert_id, $rubric->rubric_id, $total);
            }
        }

        return $defense;
    }

    private function grade(DefenseSchedule $defense, int $expertId, int $rubricId, float $total): void
    {
        PanelGrade::create([
            'defense_id' => $defense->defense_id, 'expert_id' => $expertId, 'rubric_id' => $rubricId,
            'scores' => [], 'total_score' => $total, 'status' => PanelGrade::SUBMITTED, 'submitted_at' => now(),
        ]);
    }

    private function rows(array $query = []): array
    {
        $table = $this->get(route('capsa.reports.show', ['report' => 'grades'] + $query))->assertOk()->viewData('table');

        return array_map(fn ($r) => $r['cells'] + ['group' => $r['group'] ?? null], $table['rows']);
    }

    public function test_every_student_carries_their_teams_grade_at_each_defense_and_the_average(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [$team, $leader] = $this->teamInSection('Alpha');
        $this->member($team, 'Bea', 'Santos');
        // Title Defense is out of 30, Pre-oral out of 100: 25/30 is 83.33%, so the average is (83.33 + 85) / 2.
        $this->defense($team, 'Title Defense', 30, [26, 24]);
        $this->defense($team, 'Pre Oral Defense', 100, [80, 90]);

        $this->actingAs($capsa, 'expert');
        $rows = $this->rows();

        $this->assertCount(2, $rows);
        foreach ($rows as $cells) {
            $this->assertSame(['num', '25 / 30', 83.33], $cells['title']);
            $this->assertSame(['num', '85', 85.0], $cells['pod']);
            $this->assertSame('—', $cells['fod']);
            $this->assertSame(['num', '84.17%', 84.17], $cells['average']);
            $this->assertSame('Alpha', $cells['team']);
        }
        $this->assertContains(['b', 'Santos, Bea'], array_column($rows, 'name'));
        $this->assertContains(['b', $leader->l_name.', '.$leader->f_name], array_column($rows, 'name'));
    }

    public function test_rows_run_by_section_then_team_then_last_name_with_course_and_year(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A', 'BSIT-3B']);
        [$zeta, $zetaLead] = $this->teamInSection('Zeta');
        [, $alphaLead] = $this->teamInSection('Alpha', yearSection: '3rd · B');
        [$beta, $betaLead] = $this->teamInSection('Beta');
        foreach ([$zetaLead, $alphaLead, $betaLead] as $lead) {
            $lead->update(['l_name' => 'Zz'.$lead->l_name]);
        }
        $this->member($zeta, 'Ana', 'Abad');
        $this->member($beta, 'Carl', 'Cruz');

        $this->actingAs($capsa, 'expert');
        $rows = $this->rows();

        $this->assertSame(['Beta', 'Beta', 'Zeta', 'Zeta', 'Alpha'], array_column($rows, 'team'));
        $this->assertSame(['b', 'Cruz, Carl'], $rows[0]['name']);
        $this->assertSame(['b', 'Abad, Ana'], $rows[2]['name']);
        $this->assertSame('BSIT-3A', $rows[0]['group']);
        $this->assertSame('BSIT-3B', $rows[4]['group']);
        $this->assertSame($zetaLead->fresh()->student_number, $rows[3]['number']);

        // The section filter keeps one section's students.
        $this->assertSame(['Alpha'], array_column($this->rows(['section' => 'BSIT-3B']), 'team'));
    }

    public function test_a_defense_the_panel_is_still_grading_shows_a_dash_and_is_left_out_of_the_average(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [$team] = $this->teamInSection('Alpha');
        $defense = $this->defense($team, 'Title Defense', 100, [88, null]);

        $this->actingAs($capsa, 'expert');
        $cells = $this->rows()[0];

        $this->assertSame('—', $cells['title']);
        $this->assertSame('—', $cells['average']);
        $this->get(route('capsa.reports.show', 'grades'))->assertSee('1 student · 0 graded at a defense so far.');

        // The last panelist grades: now it counts.
        $this->grade($defense, (int) DefensePanel::where('panel_role', 'Panel Member 1')->value('expert_id'), (int) GradingRubric::value('rubric_id'), 92);

        $cells = $this->rows()[0];
        $this->assertSame(['num', '90', 90.0], $cells['title']);
        $this->assertSame(['num', '90%', 90.0], $cells['average']);
    }

    public function test_it_downloads_as_excel_with_the_same_columns_and_rows(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [$team, $leader] = $this->teamInSection('Alpha');
        $this->defense($team, 'Final Oral Defense', 100, [70, 80, 90]);

        $xlsx = $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports.show', ['report' => 'grades', 'download' => 'xlsx']))->assertOk();
        $this->assertStringContainsString('student-grades_', $xlsx->headers->get('content-disposition'));

        $path = tempnam(sys_get_temp_dir(), 'grd');
        file_put_contents($path, $xlsx->streamedContent());
        $rows = IOFactory::load($path)->getActiveSheet()->toArray();
        unlink($path);

        $this->assertSame('Student Grades', $rows[0][0]);
        $this->assertSame(['Student no.', 'Section', 'Name', 'Team', 'Title Defense', 'Pre-oral', 'Final', 'Average'], array_slice($rows[3], 0, 8));
        $this->assertSame([$leader->student_number, 'BSIT-3A', $leader->l_name.', '.$leader->f_name, 'Alpha', '—', '—', '80', '80%'], array_map('strval', array_slice($rows[4], 0, 8)));
    }

    public function test_it_is_on_the_reports_list_and_shows_only_her_sections_with_requirements_done(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        $checklist = $this->emptyChecklist($capsa);
        $this->addDeliverable($checklist, ['title' => 'Signed Project Team Assignment Form']);
        [, $mine] = $this->teamInSection('Alpha');
        [, $theirs] = $this->teamInSection('Other', yearSection: '4th · A');

        $this->actingAs($capsa, 'expert')->get(route('capsa.reports.show', 'progress'))->assertOk()
            ->assertSee('Student Grades')
            ->assertSee(route('capsa.reports.show', 'grades'), false);

        $rows = $this->rows();
        $this->assertSame([$mine->student_number], array_column($rows, 'number'));
        $this->assertNotContains($theirs->student_number, array_column($rows, 'number'));
    }
}
