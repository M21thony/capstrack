<?php

namespace Tests\Feature;

use App\Models\ArchivedDocTag;
use App\Models\ArchivedDocument;
use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DefenseSchedule;
use App\Models\DefenseWindow;
use App\Models\DuplicationCheck;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\Term;
use App\Support\DefenseAnalytics;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Analytics page: how many teams pass each defense, and what kind of
 * project they are.
 *
 * It used to be numbers typed into the view. These tests are the reason it
 * can be trusted now - each one is a rule about what gets counted, and each
 * is written the way somebody reading the page would ask it.
 */
class DefenseAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const PPD = 'Pre-proposal Defense';

    private const TD = 'Title Defense';

    private const POD = 'Pre Oral Defense';

    private const FOD = 'Final Oral Defense';

    /** In the running school year (2026-2027), and one before it. */
    private const THIS_YEAR = '2026-10-05 09:00:00';

    private const LAST_YEAR = '2025-10-05 09:00:00';

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // A term is already there from the migrations; make it the one this test year is in.
        Term::query()->update(['is_active' => false]);
        Term::updateOrCreate(
            ['school_year' => '2026-2027', 'semester' => 1, 'capstone_stage' => 1],
            ['is_active' => true]
        );
    }

    private function admin(): CapstoneExpert
    {
        return CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);
    }

    private function capsa(array $sections): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => $sections]);
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        return $capsa;
    }

    /**
     * A confirmed team in a section, optionally tagged through a pre-proposal.
     *
     * @param  array<int, string>  $tags
     */
    private function team(string $name, string $section = '3rd · A', array $tags = [], string $course = 'Bachelor of Science in Information Technology'): Team
    {
        $this->seq++;

        $leader = Student::factory()->withCompleteProfile()->create([
            'email' => "member{$this->seq}@my.cspc.edu.ph",
            'course' => $course,
            'year_section' => $section,
        ]);

        $team = Team::create([
            'team_name' => $name,
            'project_title' => $name.' Project',
            'team_status' => 'Active',
            'leader_id' => $leader->student_id,
            'is_confirmed' => true,
            'member_count' => 5,
        ]);

        $sy = StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        if ($tags !== []) {
            $this->proposal($team, $sy, $tags);
        }

        return $team;
    }

    /** @param  array<int, string>  $tags */
    private function proposal(Team $team, StudentSchoolYear $sy, array $tags, bool $approved = true, bool $chosen = false): CapstonePreProposal
    {
        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id,
            'document_path' => 'checks/x.pdf',
            'concepts' => ['title' => 'x'],
            'top_score' => 20,
            'verdict' => 'Low Similarity',
            'matches' => [],
            'instructors_suggested' => [],
        ]);

        $proposal = CapstonePreProposal::create([
            'team_id' => $team->team_id,
            'duplication_check_id' => $check->id,
            'slot' => CapstonePreProposal::where('team_id', $team->team_id)->count() + 1,
            'title' => 'A Title '.$this->seq,
            'sdg_goals' => 'SDG 4',
            'research_agenda' => ['Applications and Systems Development'],
            'problem_statement' => 'A problem.',
            'proposed_solution' => 'A solution.',
            'scope' => 'A scope.',
            'limitations' => 'A limitation.',
            'sdm' => 'Agile',
            'tags' => $tags,
            'approved_status' => $approved,
            'submitted_at' => now(),
        ]);

        if ($chosen) {
            $team->update(['selected_pre_proposal_id' => $proposal->id]);
        }

        return $proposal;
    }

    private function defend(Team $team, string $stage, ?string $outcome, string $when = self::THIS_YEAR, int $attempt = 1): DefenseSchedule
    {
        return DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => $stage,
            'attempt' => $attempt,
            'schedule_date' => $when,
            'outcome' => $outcome,
        ]);
    }

    /** @return array<string, mixed> */
    private function analyse(CapstoneExpert $viewer, array $filters = []): array
    {
        return app(DefenseAnalytics::class)->build($viewer, $filters);
    }

    public function test_it_counts_how_many_teams_passed_each_defense(): void
    {
        foreach (['A', 'B', 'C'] as $name) {
            $this->defend($this->team($name), self::PPD, DefenseOutcome::PASSED);
        }
        $this->defend($this->team('D'), self::PPD, DefenseOutcome::REDEFENSE);
        $this->defend($this->team('E'), self::TD, DefenseOutcome::PASSED);

        $stages = $this->analyse($this->admin())['stages'];

        $this->assertSame(3, $stages[self::PPD]['passed']);
        $this->assertSame(1, $stages[self::PPD]['redefense']);
        // Team E has only been through Title Defense, so it has not been scheduled here yet.
        $this->assertSame(1, $stages[self::PPD]['not_scheduled']);
        $this->assertSame(5, $stages[self::PPD]['teams']);
        // Three of the four teams that have a result passed.
        $this->assertEquals(75, $stages[self::PPD]['rate']);
        $this->assertSame(1, $stages[self::TD]['passed_total']);
        // Nobody has been through the last two, so there is no rate to give.
        $this->assertNull($stages[self::FOD]['rate']);
    }

    public function test_passing_with_revision_is_a_pass_and_is_counted_apart(): void
    {
        $this->defend($this->team('A'), self::PPD, DefenseOutcome::PASSED);
        $this->defend($this->team('B'), self::PPD, DefenseOutcome::PASSED_WITH_REVISION);

        $ppd = $this->analyse($this->admin())['stages'][self::PPD];

        $this->assertSame(1, $ppd['passed']);
        $this->assertSame(1, $ppd['revision']);
        $this->assertSame(2, $ppd['passed_total']);
        $this->assertEquals(100, $ppd['rate']);
    }

    /**
     * A redefense is what a second chance is for. A team that was sent back
     * and then passed has passed - it must not also be counted as a failure.
     */
    public function test_a_team_that_passed_on_a_later_attempt_is_one_pass_not_a_fail_and_a_pass(): void
    {
        $team = $this->team('Second Try');
        $this->defend($team, self::TD, DefenseOutcome::REDEFENSE, self::THIS_YEAR, 1);
        $this->defend($team, self::TD, DefenseOutcome::PASSED, self::THIS_YEAR, 2);

        $td = $this->analyse($this->admin())['stages'][self::TD];

        $this->assertSame(1, $td['teams']);
        $this->assertSame(1, $td['passed']);
        $this->assertSame(0, $td['redefense']);
        // It did not pass first time, and the page says so.
        $this->assertEquals(0, $td['first_try_rate']);
    }

    /** Cancelled on the day for open requirements: never heard, so not a try and not a failed first try. */
    public function test_a_defense_cancelled_on_the_day_is_not_a_try(): void
    {
        $team = $this->team('Cancelled First');
        $this->defend($team, self::TD, DefenseOutcome::CANCELLED, self::THIS_YEAR, 1);
        $this->defend($team, self::TD, DefenseOutcome::PASSED, self::THIS_YEAR, 2);

        $td = $this->analyse($this->admin())['stages'][self::TD];

        $this->assertSame(1, $td['passed']);
        $this->assertEquals(100, $td['first_try_rate']);
    }

    public function test_teams_still_waiting_for_a_result_are_not_counted_against_the_pass_rate(): void
    {
        $this->defend($this->team('Done'), self::PPD, DefenseOutcome::PASSED);
        $this->defend($this->team('Waiting'), self::PPD, null);
        $this->team('Never scheduled');

        $analysis = $this->analyse($this->admin());
        $ppd = $analysis['stages'][self::PPD];

        $this->assertSame(1, $ppd['awaiting']);
        $this->assertSame(1, $ppd['not_scheduled']);
        $this->assertSame(1, $ppd['decided']);
        $this->assertEquals(100, $ppd['rate']);
        $this->assertSame(1, $analysis['kpis']['awaiting']);
    }

    public function test_running_out_of_time_is_a_fail_once_the_window_has_closed(): void
    {
        DefenseWindow::create([
            'defense_type' => self::PPD,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);
        $this->defend($this->team('Too Late'), self::PPD, DefenseOutcome::REDEFENSE);

        $ppd = $this->analyse($this->admin())['stages'][self::PPD];

        $this->assertSame(1, $ppd['missed']);
        $this->assertSame(0, $ppd['redefense']);
        $this->assertEquals(0, $ppd['rate']);
    }

    // -- tags ---------------------------------------------------------------

    public function test_results_are_broken_down_by_project_tag(): void
    {
        $this->defend($this->team('Farm', tags: ['IoT']), self::TD, DefenseOutcome::PASSED);
        $this->defend($this->team('Barn', tags: ['IoT']), self::TD, DefenseOutcome::REDEFENSE);
        $this->defend($this->team('Shop', tags: ['Web-based']), self::TD, DefenseOutcome::PASSED);

        $byTag = collect($this->analyse($this->admin())['byTag'])->keyBy('tag');

        $this->assertSame(2, $byTag['IoT']['teams']);
        $this->assertSame(1, $byTag['IoT']['stages'][self::TD]['passed_total']);
        $this->assertEquals(50, $byTag['IoT']['stages'][self::TD]['rate']);
        $this->assertEquals(100, $byTag['Web-based']['stages'][self::TD]['rate']);
    }

    public function test_a_team_with_two_tags_counts_under_both_but_only_once_as_a_team(): void
    {
        $this->defend($this->team('Smart', tags: ['IoT', 'AI / Machine Learning']), self::PPD, DefenseOutcome::PASSED);

        $analysis = $this->analyse($this->admin());
        $byTag = collect($analysis['byTag'])->keyBy('tag');

        $this->assertSame(1, $analysis['kpis']['teams']);
        $this->assertSame(1, $byTag['IoT']['stages'][self::PPD]['passed']);
        $this->assertSame(1, $byTag['AI / Machine Learning']['stages'][self::PPD]['passed']);
        // But the headline numbers count the team, not its tags.
        $this->assertSame(1, $analysis['stages'][self::PPD]['passed']);
    }

    public function test_the_chosen_pre_proposal_decides_a_teams_tags(): void
    {
        $team = $this->team('Undecided', tags: ['Web-based']);
        $sy = StudentSchoolYear::where('team_id', $team->team_id)->first();
        $this->proposal($team, $sy, ['Mobile App'], approved: false, chosen: true);
        $this->defend($team, self::PPD, DefenseOutcome::PASSED);

        $tags = collect($this->analyse($this->admin())['byTag'])->pluck('tag')->all();

        $this->assertSame(['Mobile App'], $tags);
    }

    public function test_a_team_with_no_tag_is_filed_under_untagged_and_listed_last(): void
    {
        $this->defend($this->team('Bare'), self::PPD, DefenseOutcome::PASSED);
        $this->defend($this->team('A', tags: ['IoT']), self::PPD, DefenseOutcome::PASSED);
        $this->defend($this->team('B', tags: ['IoT']), self::PPD, DefenseOutcome::PASSED);
        $this->defend($this->team('C', tags: ['IoT']), self::PPD, DefenseOutcome::PASSED);

        $analysis = $this->analyse($this->admin());
        $tags = collect($analysis['byTag'])->pluck('tag')->all();

        $this->assertSame(['IoT', DefenseAnalytics::UNTAGGED], $tags);
        $this->assertSame(3, $analysis['kpis']['tagged']);
    }

    public function test_a_tag_that_is_not_on_the_list_is_ignored(): void
    {
        $this->defend($this->team('Odd', tags: ['Blockchain', 'Nonsense']), self::PPD, DefenseOutcome::PASSED);

        $tags = collect($this->analyse($this->admin())['byTag'])->pluck('tag')->all();

        $this->assertSame(['Blockchain'], $tags);
    }

    public function test_filtering_by_tag_narrows_every_number_but_keeps_the_other_chips(): void
    {
        $this->defend($this->team('Farm', tags: ['IoT']), self::PPD, DefenseOutcome::PASSED);
        $this->defend($this->team('Shop', tags: ['Web-based']), self::PPD, DefenseOutcome::REDEFENSE);

        $analysis = $this->analyse($this->admin(), ['tag' => 'IoT']);

        $this->assertSame(1, $analysis['kpis']['teams']);
        $this->assertSame(1, $analysis['stages'][self::PPD]['teams']);
        $this->assertEquals(100, $analysis['stages'][self::PPD]['rate']);
        // Otherwise choosing one tag would make every other chip read zero.
        $this->assertEqualsCanonicalizing(['IoT', 'Web-based'], collect($analysis['tagChips'])->pluck('tag')->all());
        $this->assertSame(2, $analysis['teamsInScope']);
    }

    // -- school years -------------------------------------------------------

    public function test_a_team_is_counted_in_the_school_year_it_defended_in(): void
    {
        $this->defend($this->team('Old'), self::PPD, DefenseOutcome::PASSED, self::LAST_YEAR);
        $this->defend($this->team('New'), self::PPD, DefenseOutcome::REDEFENSE, self::THIS_YEAR);

        $this->assertSame(1, $this->analyse($this->admin())['stages'][self::PPD]['teams']);
        $this->assertSame(1, $this->analyse($this->admin(), ['school_year' => '2025-2026'])['stages'][self::PPD]['passed']);
        $this->assertSame(2, $this->analyse($this->admin(), ['school_year' => DefenseAnalytics::ALL_YEARS])['stages'][self::PPD]['teams']);
    }

    /** June and July still belong to the year that ended; August starts the next. */
    public function test_august_starts_the_next_school_year(): void
    {
        $this->defend($this->team('July'), self::PPD, DefenseOutcome::PASSED, '2026-07-15 09:00:00');
        $this->defend($this->team('August'), self::PPD, DefenseOutcome::PASSED, '2026-08-15 09:00:00');

        $years = array_keys($this->analyse($this->admin(), ['school_year' => DefenseAnalytics::ALL_YEARS])['byYear']);

        $this->assertSame(['2025-2026', '2026-2027'], $years);
    }

    public function test_last_years_numbers_do_not_change_when_a_team_defends_again_this_year(): void
    {
        $team = $this->team('Moves On');
        $this->defend($team, self::PPD, DefenseOutcome::PASSED, self::LAST_YEAR);
        $before = $this->analyse($this->admin(), ['school_year' => '2025-2026'])['stages'][self::PPD]['passed'];

        $this->defend($team, self::TD, DefenseOutcome::PASSED, self::THIS_YEAR);
        $after = $this->analyse($this->admin(), ['school_year' => '2025-2026'])['stages'][self::PPD]['passed'];

        $this->assertSame(1, $before);
        $this->assertSame($before, $after);
    }

    /**
     * A team that did not get through a stage is not going to reach the ones
     * after it. Counting those as "not scheduled yet" would keep every team
     * that dropped out last year in this year's numbers as work still to come.
     */
    public function test_a_team_that_dropped_out_is_not_work_still_to_come(): void
    {
        $this->defend($this->team('Dropped'), self::TD, DefenseOutcome::REDEFENSE, self::LAST_YEAR);
        $this->team('Fresh');

        $analysis = $this->analyse($this->admin());

        // Only the team nobody has scheduled yet is waiting on the later stages.
        $this->assertSame(1, $analysis['stages'][self::POD]['not_scheduled']);
        $this->assertSame(1, $analysis['stages'][self::FOD]['not_scheduled']);
        $this->assertSame(['Fresh'], collect($analysis['teams'])->pluck('name')->all());

        // Looked at in the year it dropped out, the team says what happened
        // and does not pretend its later stages are still to come.
        $lastYear = collect($this->analyse($this->admin(), ['school_year' => '2025-2026'])['teams'])->firstWhere('name', 'Dropped');
        $this->assertSame('missed', $lastYear['stages'][self::TD]);
        $this->assertSame('closed', $lastYear['stages'][self::POD]);
    }

    public function test_a_team_between_stages_is_still_work_to_come(): void
    {
        $team = $this->team('Passed Last Year');
        $this->defend($team, self::PPD, DefenseOutcome::PASSED, self::LAST_YEAR);

        $analysis = $this->analyse($this->admin());

        // Its pre-proposal belongs to last year; its title defense is this year's to schedule.
        $this->assertSame(0, $analysis['stages'][self::PPD]['teams']);
        $this->assertSame(1, $analysis['stages'][self::TD]['not_scheduled']);
        $this->assertSame(1, $analysis['kpis']['teams']);
    }

    public function test_a_past_year_with_no_pass_is_closed_not_still_open(): void
    {
        $this->defend($this->team('Stalled'), self::TD, DefenseOutcome::REDEFENSE, self::LAST_YEAR);

        $td = $this->analyse($this->admin(), ['school_year' => '2025-2026'])['stages'][self::TD];

        $this->assertSame(1, $td['missed']);
        $this->assertSame(0, $td['redefense']);
    }

    public function test_the_year_by_year_view_ignores_the_year_filter(): void
    {
        $this->defend($this->team('Old'), self::PPD, DefenseOutcome::PASSED, self::LAST_YEAR);
        $this->defend($this->team('New'), self::PPD, DefenseOutcome::PASSED, self::THIS_YEAR);

        $byYear = $this->analyse($this->admin(), ['school_year' => '2026-2027'])['byYear'];

        $this->assertSame(['2025-2026', '2026-2027'], array_keys($byYear));
    }

    // -- who sees what ------------------------------------------------------

    public function test_a_capsa_sees_only_the_teams_in_her_sections(): void
    {
        $this->defend($this->team('Mine', '3rd · A'), self::PPD, DefenseOutcome::PASSED);
        $this->defend($this->team('Not Mine', '3rd · B'), self::PPD, DefenseOutcome::PASSED);

        $mine = $this->analyse($this->capsa(['BSIT-3A']));
        $everyone = $this->analyse($this->admin());

        $this->assertSame(['Mine'], collect($mine['teams'])->pluck('name')->all());
        $this->assertSame(1, $mine['stages'][self::PPD]['teams']);
        $this->assertSame(2, $everyone['stages'][self::PPD]['teams']);
    }

    public function test_the_program_and_section_filters_narrow_the_teams(): void
    {
        $this->defend($this->team('IT Team', '3rd · A'), self::PPD, DefenseOutcome::PASSED);
        $this->defend($this->team('CS Team', '3rd · A', course: 'Bachelor of Science in Computer Science'), self::PPD, DefenseOutcome::PASSED);

        $admin = $this->admin();

        $this->assertSame(['CS Team'], collect($this->analyse($admin, ['program' => 'BSCS'])['teams'])->pluck('name')->all());
        $this->assertSame(['IT Team'], collect($this->analyse($admin, ['section' => 'BSIT-3A'])['teams'])->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['BSCS', 'BSIT'], $this->analyse($admin)['options']['programs']);
    }

    // -- archive ------------------------------------------------------------

    public function test_the_archive_is_counted_by_year_and_folded_into_project_types(): void
    {
        $iot = ArchivedDocTag::create(['tag_name' => 'IoT', 'tag_color' => '#16a34a']);
        $web = ArchivedDocTag::create(['tag_name' => 'Web Based', 'tag_color' => '#1d4ed8']);
        $odd = ArchivedDocTag::create(['tag_name' => 'Robotics', 'tag_color' => '#000000']);

        foreach ([['2023-2024', [$iot]], ['2023-2024', [$web, $iot]], ['2024-2025', [$odd]], ['2024-2025', []]] as $i => [$year, $tags]) {
            $document = ArchivedDocument::create([
                'team_id' => $this->team('Archived '.$i)->team_id,
                'title' => 'Capstone '.$i, 'abstract' => 'x', 'authors' => 'x', 'academic_year' => $year,
                'program' => 'BSIT', 'file_name' => 'x.pdf', 'file_path' => 'x.pdf', 'file_size' => 1,
            ]);
            $document->tags()->sync(collect($tags)->pluck('tag_id')->all());
        }

        $archive = $this->analyse($this->admin())['archive'];

        $this->assertSame(4, $archive['total']);
        $this->assertSame(2, $archive['years']['2023-2024']['total']);
        $this->assertSame(2, $archive['years']['2023-2024']['tags']['IoT']);
        $this->assertSame(1, $archive['years']['2023-2024']['tags']['Web-based']);
        // Unrecognised and missing tags both land in Other.
        $this->assertSame(2, $archive['years']['2024-2025']['tags']['Other']);
    }

    // -- the page -----------------------------------------------------------

    public function test_the_page_shows_the_real_numbers_and_the_tags(): void
    {
        $this->defend($this->team('Farm', tags: ['IoT']), self::TD, DefenseOutcome::PASSED);
        $this->defend($this->team('Barn', tags: ['IoT']), self::TD, DefenseOutcome::REDEFENSE);
        $this->defend($this->team('Shop', tags: ['Web-based']), self::TD, DefenseOutcome::PASSED);

        $this->actingAs($this->admin(), 'expert')->get(route('admin.analytics'))
            ->assertOk()
            ->assertSee('How many teams pass each defense')
            ->assertSee('Pass rate by project type')
            ->assertSeeInOrder(['IoT', 'Web-based'])
            ->assertSee('Team by team')
            ->assertSee('Farm')
            // The numbers that used to be typed in are gone.
            ->assertDontSee('SY 2018')
            ->assertDontSee('265');
    }

    public function test_the_page_opens_with_nothing_recorded_yet(): void
    {
        $this->actingAs($this->admin(), 'expert')->get(route('admin.analytics'))
            ->assertOk()
            ->assertSee('No teams to show for these filters');
    }

    public function test_a_filter_that_matches_nothing_says_so_and_offers_a_way_out(): void
    {
        $this->defend($this->team('Farm', tags: ['IoT']), self::PPD, DefenseOutcome::PASSED);

        $this->actingAs($this->admin(), 'expert')->get(route('admin.analytics', ['tag' => 'Blockchain']))
            ->assertOk()
            ->assertSee('Nothing matches this combination')
            ->assertSee('Reset the filters');
    }

    public function test_a_capsa_opens_the_page_scoped_to_her_sections(): void
    {
        $this->defend($this->team('Mine', '3rd · A'), self::PPD, DefenseOutcome::PASSED);
        $this->defend($this->team('Elsewhere', '3rd · B'), self::PPD, DefenseOutcome::PASSED);

        $this->actingAs($this->capsa(['BSIT-3A']), 'expert')->get(route('capsa.analytics'))
            ->assertOk()
            ->assertSee('Mine')
            ->assertDontSee('Elsewhere');
    }

    public function test_the_csv_export_follows_the_filters_and_neutralises_formulas(): void
    {
        $this->defend($this->team('Farm', tags: ['IoT']), self::PPD, DefenseOutcome::PASSED);
        $this->defend($this->team('=cmd()', tags: ['Web-based']), self::PPD, DefenseOutcome::REDEFENSE);

        $response = $this->actingAs($this->admin(), 'expert')->get(route('admin.analytics', ['tag' => 'IoT', 'export' => 'csv']));

        $response->assertOk()->assertDownload();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Farm', $csv);
        $this->assertStringContainsString('Passed', $csv);
        $this->assertStringNotContainsString('cmd()', $csv);

        $all = $this->actingAs($this->admin(), 'expert')->get(route('admin.analytics', ['export' => 'csv']))->streamedContent();
        $this->assertStringContainsString("'=cmd()", $all);
    }

    public function test_a_bad_export_value_is_refused(): void
    {
        $this->actingAs($this->admin(), 'expert')->get(route('admin.analytics', ['export' => 'exe']))
            ->assertSessionHasErrors('export');
    }
}
