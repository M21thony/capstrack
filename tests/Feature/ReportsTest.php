<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\ExpensePost;
use App\Models\ExpertRole;
use App\Models\FinancialRecord;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Reports: the index picks the period, each report opens as a table of the
 * CaPSA's own teams, and the PDF and the spreadsheet carry the columns,
 * order and merged reports shown on screen.
 */
class ReportsTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    public function test_the_index_lists_every_report_and_counts_only_her_teams(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        $this->teamInSection('Alpha');
        $this->teamInSection('Outsider', yearSection: '4th · A');

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports'))
            ->assertOk()
            ->assertSeeInOrder(['Team Progress', 'Team Master List', 'Defense Results', 'Fee Collection', 'Expert Load', 'Defense Schedule'])
            ->assertSee(route('capsa.reports.show', 'experts'), false)
            ->assertViewHas('teamCount', 1);
    }

    /** A team with overdue requirements - the everyday case - opens with them named in its side panel. */
    public function test_a_team_with_overdue_items_opens_and_names_them(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        $checklist = $this->emptyChecklist($capsa);
        $this->addDeliverable($checklist, ['title' => 'Signed Project Team Assignment Form', 'due_date' => now()->subWeek()->toDateString()]);
        [$team] = $this->teamInSection('Alpha');

        foreach (['progress', 'master', 'results', 'fees', 'schedule', 'experts'] as $report) {
            $this->actingAs($capsa, 'expert')->get(route('capsa.reports.show', $report))->assertOk();
        }

        $this->get(route('capsa.reports.show', 'progress'))
            ->assertViewHas('details', fn ($d) => $d[$team->team_id]['overdue'] === ['Signed Project Team Assignment Form']);
    }

    /** A hand-edited or old link with list-style parameters opens the report instead of failing. */
    public function test_odd_query_parameters_do_not_break_the_table(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        $this->teamInSection('Alpha');
        $this->actingAs($capsa, 'expert');

        $odd = ['merge' => ['master'], 'hide' => ['section'], 'order' => ['team'], 'q' => ['a'], 'sort' => ['team'], 'dir' => ['desc'], 'rows' => ['sections'], 'year' => ['x'], 'section' => ['y']];
        $this->get(route('capsa.reports.show', ['report' => 'progress'] + $odd))->assertOk();
        $this->get(route('capsa.reports.show', ['report' => 'progress', 'download' => 'xlsx'] + $odd))->assertOk();
        $this->get(route('capsa.reports', $odd))->assertOk();
    }

    /** The merge dialog lists each report's columns without building every report. */
    public function test_the_merge_dialog_lists_the_other_reports_columns(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        $this->teamInSection('Alpha');

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports.show', 'progress'))
            ->assertOk()
            ->assertViewHas('mergeable', fn ($m) => collect($m)->pluck('key')->all() === ['master', 'results', 'fees']
                && $m[0]['columns'] === ['title' => 'Project title', 'members' => 'Members', 'adviser' => 'Adviser', 'consultant' => 'Consultant', 'grammarian' => 'Grammarian']);
    }

    public function test_team_progress_has_a_row_per_team_and_no_verified_column(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A', 'BSIT-3B']);
        $this->teamInSection('Alpha');
        $this->teamInSection('Charlie', yearSection: '3rd · B');
        $this->teamInSection('Outsider', yearSection: '4th · A');

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports.show', 'progress'))
            ->assertOk()
            ->assertSee('Alpha')
            ->assertSee('Charlie')
            ->assertDontSee('Outsider')
            ->assertViewHas('table', fn ($t) => array_keys($t['columns']) === ['team', 'section', 'milestone', 'done', 'overdue', 'status']);
    }

    public function test_section_totals_add_up_her_sections(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A', 'BSIT-3B']);
        $this->teamInSection('Alpha');
        $this->teamInSection('Bravo');
        $this->teamInSection('Charlie', yearSection: '3rd · B');

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports.show', ['report' => 'progress', 'rows' => 'sections']))
            ->assertOk()
            ->assertViewHas('table', fn ($t) => array_map(fn ($r) => [$r['cells']['section'][1], $r['cells']['teams'][1]], $t['rows']) === [['BSIT-3A', 2], ['BSIT-3B', 1]]
                && $t['total']['teams'] === ['b', '3']);
    }

    public function test_a_section_link_opens_the_report_filtered_to_it_and_only_hers(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A', 'BSIT-3B']);
        $this->teamInSection('Alpha');
        $this->teamInSection('Charlie', yearSection: '3rd · B');
        $this->actingAs($capsa, 'expert');

        $this->get(route('capsa.reports.section', 'BSIT-3A'))
            ->assertRedirect(route('capsa.reports.show', ['report' => 'progress', 'section' => 'BSIT-3A']));

        $this->get(route('capsa.reports.show', ['report' => 'progress', 'section' => 'BSIT-3A']))
            ->assertOk()
            ->assertSee('Team Progress for BSIT-3A.')
            ->assertSee('Alpha')
            ->assertDontSee('Charlie');

        $this->get(route('capsa.reports.section', 'BSIT-4A'))->assertNotFound();
    }

    public function test_old_tab_links_open_the_new_table(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports', ['tab' => 'fees']))
            ->assertRedirect(route('capsa.reports.show', 'fees'));
    }

    public function test_fees_say_when_the_fee_is_not_posted_and_count_verified_receipts(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [, $leader] = $this->teamInSection('Alpha');
        $this->actingAs($capsa, 'expert');

        $this->get(route('capsa.reports.show', 'fees'))
            ->assertOk()
            ->assertSee('The Title Defense fee has not been posted yet');

        $post = ExpensePost::create(['title' => 'Title Defense fee', 'course' => 'BSIT', 'year_level' => '3rd', 'defense_type' => 'Title Defense', 'amount' => 500, 'is_active' => true]);
        FinancialRecord::create(['student_id' => $leader->student_id, 'expense_post_id' => $post->id, 'receipt_image' => 'receipts/x.jpg', 'f_record_status' => 'verified', 'uploaded_at' => now()]);

        $this->get(route('capsa.reports.show', 'fees'))
            ->assertOk()
            ->assertViewHas('table', fn ($t) => $t['rows'][0]['cells']['status'] === ['chip', 'Paid', 'ok']
                && $t['total']['collected'] === ['b', '₱500']);
    }

    public function test_results_count_the_latest_attempt_only(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [$team] = $this->teamInSection('Alpha');

        foreach ([1 => DefenseOutcome::REDEFENSE, 2 => DefenseOutcome::PASSED] as $attempt => $outcome) {
            DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => DefenseOutcome::TITLE_DEFENSE, 'attempt' => $attempt, 'outcome' => $outcome, 'outcome_recorded_at' => now()]);
        }

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports.show', ['report' => 'results', 'stage' => DefenseOutcome::TITLE_DEFENSE]))
            ->assertOk()
            ->assertViewHas('table', fn ($t) => $t['rows'][0]['cells']['verdict'][1] === DefenseOutcome::label(DefenseOutcome::PASSED, DefenseOutcome::TITLE_DEFENSE)
                && $t['rows'][0]['cells']['verdict'][2] === 'ok');
    }

    public function test_the_master_list_names_the_teams_experts_but_not_the_capsa(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [$team] = $this->teamInSection('Alpha');
        $adviser = $this->engage($team, 'adviser', 'Ada', 'Lovelace');
        $this->engage($team, CapstoneExpert::CAPSA_ROLE, 'Casey', 'Capsa');

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports.show', 'master'))
            ->assertOk()
            ->assertViewHas('table', fn ($t) => $t['rows'][0]['cells']['adviser'] === $adviser->name
                && $t['rows'][0]['cells']['consultant'] === '—');
    }

    public function test_the_schedule_lists_dates_rooms_and_panels_with_unscheduled_teams_last(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [$later] = $this->teamInSection('Later');
        [$sooner] = $this->teamInSection('Sooner');
        $this->teamInSection('Waiting');
        $chair = CapstoneExpert::factory()->create(['approved_status' => true, 'first_name' => 'Chair', 'last_name' => 'Person']);

        DefenseSchedule::create(['team_id' => $later->team_id, 'defense_type' => DefenseOutcome::TITLE_DEFENSE, 'attempt' => 1, 'schedule_date' => now()->addDays(3)->setTime(9, 0), 'venue' => 'Room 2']);
        $s = DefenseSchedule::create(['team_id' => $sooner->team_id, 'defense_type' => DefenseOutcome::TITLE_DEFENSE, 'attempt' => 1, 'schedule_date' => now()->addDay()->setTime(8, 0), 'end_date' => now()->addDay()->setTime(9, 0), 'venue' => 'Room 1']);
        DefensePanel::create(['defense_id' => $s->defense_id, 'expert_id' => $chair->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports.show', ['report' => 'schedule', 'stage' => DefenseOutcome::TITLE_DEFENSE]))
            ->assertOk()
            ->assertViewHas('table', fn ($t) => array_map(fn ($r) => $r['cells']['team'][1], $t['rows']) === ['Sooner', 'Later', 'Waiting']
                && $t['rows'][0]['cells']['room'] === 'Room 1'
                && $t['rows'][0]['cells']['time'] === '8:00 AM–9:00 AM'
                && $t['rows'][0]['cells']['chair'] === $chair->name
                && $t['rows'][2]['cells']['date'] === ['chip', 'Not scheduled', 'none']);
    }

    public function test_expert_load_counts_each_role_this_year(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [$alpha] = $this->teamInSection('Alpha');
        [$bravo] = $this->teamInSection('Bravo');
        $expert = $this->engage($alpha, 'adviser', 'Busy', 'Expert');
        $this->engageExisting($bravo, $expert, 'consultant');

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports.show', 'experts'))
            ->assertOk()
            ->assertViewHas('table', function ($t) use ($expert) {
                $row = collect($t['rows'])->first(fn ($r) => $r['cells']['expert'][1] === $expert->name);

                return $row['cells']['adviser'] === ['num', 1] && $row['cells']['consultant'] === ['num', 1]
                    && $row['cells']['now'] === ['num', 2] && $row['cells']['change'] === ['chip', 'New', 'info'];
            });
    }

    public function test_merging_adds_the_other_reports_columns_beside_each_team(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [$team] = $this->teamInSection('Alpha');
        $team->update(['project_title' => 'Crop Yield Prediction']);

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports.show', ['report' => 'progress', 'merge' => 'master', 'rows' => 'sections']))
            ->assertOk()
            // Section totals and a merge cannot both apply; the totals give way.
            ->assertViewHas('bySection', true)
            ->assertViewHas('merged', []);

        $this->get(route('capsa.reports.show', ['report' => 'progress', 'merge' => 'master']))
            ->assertOk()
            ->assertSee('Crop Yield Prediction')
            ->assertViewHas('merged', ['master'])
            ->assertViewHas('table', fn ($t) => isset($t['columns']['master.title'], $t['columns']['master.adviser'])
                && ! isset($t['columns']['master.team'])
                && $t['rows'][0]['cells']['master.title'] === 'Crop Yield Prediction');
    }

    public function test_the_downloads_carry_the_order_and_columns_shown(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        $this->teamInSection('Alpha');
        $this->actingAs($capsa, 'expert');
        // dompdf embeds the whole DejaVu font (for the peso sign), about 35MB on top of the test's own.
        ini_set('memory_limit', '512M');

        $pdf = $this->get(route('capsa.reports.show', ['report' => 'progress', 'download' => 'pdf']))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString('attachment', $pdf->headers->get('content-disposition'));
        $this->assertStringContainsString('inline', $this->get(route('capsa.reports.show', ['report' => 'progress', 'download' => 'pdf', 'inline' => 1]))->headers->get('content-disposition'));

        $xlsx = $this->get(route('capsa.reports.show', [
            'report' => 'progress', 'download' => 'xlsx', 'order' => 'team,status,done', 'hide' => 'section,milestone,overdue',
        ]))->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'rpt');
        file_put_contents($path, $xlsx->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);
        $rows = $sheet->toArray();

        // Title and scope on top, then the headings in the order picked, less the hidden ones.
        $this->assertSame('Team Progress', $rows[0][0]);
        $this->assertSame(['Team', 'Status', 'Requirements done'], array_slice($rows[3], 0, 3));
        $this->assertNull($rows[3][3] ?? null);
        $this->assertSame('Alpha', $rows[4][0]);
        $this->assertSame('C9A82C', $sheet->getStyle('A4')->getFill()->getStartColor()->getRGB());
        $this->assertSame('B5', $sheet->getFreezePane());
    }

    private function engage(Team $team, string $roleName, string $first, string $last): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'first_name' => $first, 'last_name' => $last]);
        $this->engageExisting($team, $expert, $roleName);

        return $expert;
    }

    private function engageExisting(Team $team, CapstoneExpert $expert, string $roleName): void
    {
        $role = Role::firstOrCreate(['role_name' => $roleName]);
        $expert->roles()->syncWithoutDetaching([$role->role_id]);

        TeamExpertAssignment::create([
            'team_id' => $team->team_id, 'expert_id' => $expert->expert_id, 'status' => 'approved',
            'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)->where('role_id', $role->role_id)->value('expert_role_id'),
        ]);
    }
}
