<?php

namespace Tests\Feature;

use App\Models\DefenseSchedule;
use App\Models\ExpensePost;
use App\Models\FinancialRecord;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Reports: only the CaPSA's own sections are counted, a section opens on a
 * page of its own, and the PDF and the spreadsheet carry the columns shown.
 */
class ReportsTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    public function test_the_summary_counts_only_her_sections(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A', 'BSIT-3B']);
        $this->teamInSection('Alpha');
        $this->teamInSection('Bravo');
        $this->teamInSection('Charlie', yearSection: '3rd · B');
        $this->teamInSection('Outsider', yearSection: '4th · A');

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports', ['tab' => 'progress']))
            ->assertOk()
            ->assertSee('/capsa/reports/section/BSIT-3A', false)
            ->assertViewHas('report', fn ($report) => array_map(fn ($row) => [$row['section'][1], $row['teams']], $report['table']['rows']) === [['BSIT-3A', 2], ['BSIT-3B', 1]]
                && $report['table']['total']['teams'] === 3);
    }

    public function test_a_section_opens_on_its_own_page_and_only_hers(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A', 'BSIT-3B']);
        $this->teamInSection('Alpha');
        $this->teamInSection('Charlie', yearSection: '3rd · B');

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports.section', 'BSIT-3A'))
            ->assertOk()
            ->assertSee('Team Progress for BSIT-3A.')
            ->assertSee('Alpha')
            ->assertDontSee('Charlie');

        $this->get(route('capsa.reports.section', 'BSIT-4A'))->assertNotFound();
    }

    public function test_fees_say_when_the_fee_is_not_posted_and_count_verified_receipts(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [, $leader] = $this->teamInSection('Alpha');

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports', ['tab' => 'fees']))
            ->assertOk()
            ->assertSee('The Title Defense fee has not been posted yet');

        $post = ExpensePost::create(['title' => 'Title Defense fee', 'course' => 'BSIT', 'year_level' => '3rd', 'defense_type' => 'Title Defense', 'amount' => 500, 'is_active' => true]);
        FinancialRecord::create(['student_id' => $leader->student_id, 'expense_post_id' => $post->id, 'receipt_image' => 'receipts/x.jpg', 'f_record_status' => 'verified', 'uploaded_at' => now()]);

        $this->get(route('capsa.reports', ['tab' => 'fees']))
            ->assertOk()
            ->assertViewHas('report', fn ($report) => $report['kpis'][0]['label'] === 'Verified' && $report['kpis'][0]['value'] === 1
                && $report['table']['total']['collected'] === ['b', '₱500']);
    }

    public function test_results_count_the_latest_attempt_only(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [$team] = $this->teamInSection('Alpha');

        foreach ([1 => DefenseOutcome::REDEFENSE, 2 => DefenseOutcome::PASSED] as $attempt => $outcome) {
            DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => DefenseOutcome::TITLE_DEFENSE, 'attempt' => $attempt, 'outcome' => $outcome, 'outcome_recorded_at' => now()]);
        }

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports', ['tab' => 'results', 'stage' => DefenseOutcome::TITLE_DEFENSE]))
            ->assertOk()
            ->assertViewHas('report', fn ($report) => [$report['kpis'][1]['label'], $report['kpis'][1]['value'], $report['kpis'][2]['value']] === ['Passed', 1, 0]);
    }

    public function test_the_overview_counts_teams_at_the_defense_they_are_on(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        $this->teamInSection('Alpha');

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports'))
            ->assertOk()
            ->assertViewHas('view', 'overview')
            ->assertViewHas('teamCount', 1)
            ->assertViewHas('pipeline', fn ($pipeline) => array_sum($pipeline) === 1);
    }

    public function test_the_downloads_carry_only_the_columns_shown(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        $this->teamInSection('Alpha');
        $this->actingAs($capsa, 'expert');
        // dompdf embeds the whole DejaVu font (for the peso sign), about 35MB on top of the test's own.
        ini_set('memory_limit', '512M');

        $this->assertStringStartsWith('%PDF', $this->get(route('capsa.reports', ['tab' => 'progress', 'download' => 'pdf']))->assertOk()->getContent());

        // From and To pick a run of columns after the first; "hide" drops more from it.
        $xlsx = $this->get(route('capsa.reports', [
            'tab' => 'progress', 'download' => 'xlsx', 'from' => 'teams', 'to' => 'verified', 'hide' => 'verified',
        ]))->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'rpt');
        file_put_contents($path, $xlsx->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);
        $rows = $sheet->toArray();

        // Title, scope and figures on top; no coloured column, so no legend and the headings sit on row 5.
        $this->assertSame('Team Progress', $rows[0][0]);
        $this->assertSame(['Section', 'Teams'], array_slice($rows[4], 0, 2));
        $this->assertNull($rows[4][2] ?? null);
        $this->assertSame(['BSIT-3A', 'All sections'], [$rows[5][0], $rows[6][0]]);

        // A gold heading row kept in view, a line round every cell, numbers set right.
        $this->assertSame('C9A82C', $sheet->getStyle('A5')->getFill()->getStartColor()->getRGB());
        $this->assertSame('thin', $sheet->getStyle('B7')->getBorders()->getRight()->getBorderStyle());
        $this->assertSame('right', $sheet->getStyle('B6')->getAlignment()->getHorizontal());
        $this->assertSame('A6', $sheet->getFreezePane());
    }
}
