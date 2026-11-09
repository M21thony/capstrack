<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\SimilarityBand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * The Similarity Report fits one screen: the team's words beside what came
 * back, the suggested experts underneath, nothing scrolling inside anything,
 * and no red-and-green bar or "Highly similar" alarm.
 */
class SimilarityReportLayoutTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private const TITLE = 'MediTrack-MCH: A Digital Inventory and Patient Records Management System for a Lying-In Clinic';

    private Team $team;

    private Student $leader;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->team, $this->leader] = $this->teamInSection('Report');
    }

    private function report(array $proposal = [], ?array $matches = null): DuplicationCheck
    {
        $check = DuplicationCheck::create([
            'student_s_y_id' => StudentSchoolYear::where('student_id', $this->leader->student_id)->value('s_y_id'),
            'document_path' => 'checks/r.pdf', 'concepts' => [], 'top_score' => 75, 'verdict' => 'High Similarity',
            'matches' => $matches ?? [
                ['title' => 'MEDICARE: A WEB-BASED MEDICAL INVENTORY SYSTEM', 'academic_year' => '2023', 'program' => 'BSIT', 'score' => 75, 'reason' => 'Digitalization of medical inventory'],
                ['title' => 'A Payroll System', 'academic_year' => '2022', 'program' => 'BSIT', 'score' => 50, 'reason' => 'Web-based records'],
                ['title' => 'Child Healthcare Monitoring', 'academic_year' => '2025', 'program' => 'BSIT', 'score' => 15, 'reason' => 'Healthcare monitoring'],
            ],
            'instructors_suggested' => [],
        ]);

        CapstonePreProposal::create($proposal + [
            'team_id' => $this->team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1, 'title' => self::TITLE,
            'sdg_goals' => 'SDG 3 – Good Health and Well-being, SDG 9 – Industry, Innovation and Infrastructure',
            'research_agenda' => ['Health informatics', 'e-Services'], 'problem_statement' => 'Paper records get lost.',
            'proposed_solution' => 'A web-based records system.', 'scope' => 'Patient records and inventory.', 'limitations' => 'Web only.', 'sdm' => 'Agile',
        ]);

        return $check;
    }

    public function test_the_report_reads_calmly_and_shows_the_title_breadcrumb_and_counts(): void
    {
        $check = $this->report();

        $this->actingAs($this->leader)->get(route('student.duplication.report', $check->id))
            ->assertOk()
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('Similarity Report')
            // The team's title leads their own column, not only the header.
            ->assertSeeInOrder(['Your pre-proposal', self::TITLE, 'SDG 3 – Good Health and Well-being'])
            ->assertSee('<span class="band band-close">75% closest assessed match</span>', false)
            ->assertSee('<span class="band band-some">50%</span>', false)
            ->assertDontSee('Highly similar')
            ->assertDontSee('class="scalebar', false)
            ->assertDontSee('class="scrolls', false);
    }

    public function test_everything_the_team_wrote_is_one_click_away(): void
    {
        $check = $this->report();

        $this->actingAs($this->leader)->get(route('student.duplication.report', $check->id))
            ->assertSee('Read the full pre-proposal')
            ->assertSee('<dialog class="rp-dialog" id="rp-full"', false)
            ->assertSeeInOrder(['id="rp-full"', 'Research agenda and priority areas', 'Health informatics', 'Limitations', 'Web only.'], false);
    }

    public function test_every_goal_shows_on_the_report(): void
    {
        $check = $this->report(['sdg_goals' => 'SDG 1 – No Poverty, SDG 3 – Good Health and Well-being, SDG 4 – Quality Education, SDG 9 – Industry, Innovation and Infrastructure, SDG 16 – Peace, Justice and Strong Institutions']);

        $this->actingAs($this->leader)->get(route('student.duplication.report', $check->id))
            ->assertSeeInOrder(['SDG 1 – No Poverty', 'SDG 9 – Industry, Innovation and Infrastructure', 'SDG 16 – Peace, Justice and Strong Institutions']);
    }

    public function test_the_bands(): void
    {
        $this->assertSame('close', SimilarityBand::of(65));
        $this->assertSame('some', SimilarityBand::of(64));
        $this->assertSame('some', SimilarityBand::of(40));
        $this->assertSame('little', SimilarityBand::of(39));
        $this->assertSame('none', SimilarityBand::of(null));
        $this->assertSame(['close' => 1, 'some' => 0, 'little' => 0], SimilarityBand::counts([['score' => 80], ['score' => 90, 'assessed' => false]]));
    }

    /** The suggested experts wait behind one button and open in a window, advisers first. */
    public function test_suggested_experts_open_in_a_window(): void
    {
        $check = $this->report();
        $suggested = [];
        foreach ([['Maria Santos', 'consultant', 'Consultant'], ['Mark Balilla, MIT', 'adviser', 'Adviser'], ['Kenn Aurillas', 'adviser', 'Adviser'], ['Ian Tumambing', 'adviser', 'Adviser']] as [$name, $role, $label]) {
            $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
            $suggested[] = ['id' => $expert->expert_id, 'name' => $name, 'expertise' => 'Web Development', 'reason' => 'Fits the study.', 'role' => $role, 'role_label' => $label];
        }
        $check->update(['instructors_suggested' => $suggested]);

        $this->actingAs($this->leader)->get(route('student.duplication.report', $check->id))
            ->assertSee('Experts <span class="pc-n">4</span>', false)
            ->assertSee('id="rp-sx-dlg"', false)
            ->assertDontSee('<details', false)
            ->assertSeeInOrder(['Mark Balilla, MIT', 'Kenn Aurillas', 'Ian Tumambing', 'Maria Santos']);
    }

    public function test_three_or_fewer_experts_need_no_arrows(): void
    {
        $check = $this->report();
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        $check->update(['instructors_suggested' => [['id' => $expert->expert_id, 'name' => 'Mark Balilla, MIT', 'expertise' => 'Web', 'reason' => 'Fits.', 'role' => 'adviser', 'role_label' => 'Adviser']]]);

        $this->actingAs($this->leader)->get(route('student.duplication.report', $check->id))
            ->assertSee('Mark Balilla, MIT')
            ->assertDontSee('aria-label="Next experts"', false);
    }

    /** This year's recommended titles get their own card; the archive keeps its top 10, and each row links by its stored rank. */
    public function test_the_report_splits_this_years_titles_from_the_archive(): void
    {
        $check = $this->report(matches: [
            ['title' => 'Archive One', 'academic_year' => '2023', 'program' => 'BSIT', 'score' => 70, 'reason' => 'Inventory'],
            ['title' => 'ClinicQueue', 'academic_year' => '2026', 'program' => 'BSIT', 'score' => 84, 'reason' => 'Clinic records', 'recommended' => 'Recommended this year — BSIT 3B', 'pre_proposal_id' => 999],
            ['title' => 'Archive Two', 'academic_year' => '2022', 'program' => 'BSIS', 'score' => 45, 'reason' => 'Records'],
        ]);

        $this->actingAs($this->leader)->get(route('student.duplication.report', $check->id))
            ->assertSee('1 recommended this year')
            ->assertSeeInOrder(['Recommended this year <span class="pc-n">1</span>', 'Top 10 archived <span class="pc-n">2</span>', 'ClinicQueue', 'BSIT 3B', 'Archive One', 'Archive Two'], false)
            ->assertSee(route('student.duplication.similar', [$check->id, 2]), false)
            ->assertSee(route('student.duplication.similar', [$check->id, 3]), false);
    }

    public function test_a_recommended_match_shows_their_problem_solution_and_scope(): void
    {
        [$other, $otherLeader] = $this->teamInSection('Other');
        $theirCheck = DuplicationCheck::create([
            'student_s_y_id' => StudentSchoolYear::where('student_id', $otherLeader->student_id)->value('s_y_id'),
            'document_path' => 'checks/o.pdf', 'concepts' => [], 'top_score' => 20, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);
        $theirs = CapstonePreProposal::create([
            'team_id' => $other->team_id, 'duplication_check_id' => $theirCheck->id, 'slot' => 1, 'title' => 'ClinicQueue', 'approved_status' => true,
            'sdg_goals' => '3', 'research_agenda' => [], 'limitations' => 'None.', 'sdm' => 'Agile',
            'problem_statement' => 'Patients wait in long lines.', 'proposed_solution' => 'An online queue.', 'scope' => 'One clinic.',
        ]);
        $check = $this->report(matches: [
            ['title' => 'Archive One', 'academic_year' => '2023', 'program' => 'BSIT', 'score' => 70, 'reason' => 'Inventory'],
            ['title' => 'ClinicQueue', 'academic_year' => '2026', 'program' => 'BSIT', 'score' => 84, 'reason' => 'Clinic', 'recommended' => 'Recommended this year — BSIT 3B', 'pre_proposal_id' => $theirs->id],
        ]);

        $this->actingAs($this->leader)->get(route('student.duplication.similar', [$check->id, 2]))
            ->assertOk()
            ->assertSee('This year 1 of 1')
            ->assertSee('BSIT 3B')
            ->assertSeeInOrder(['Patients wait in long lines.', 'An online queue.', 'One clinic.'])
            ->assertSee('Back to report')
            ->assertDontSee('What your team wrote');

        // An archive match saved without its abstract still opens, saying so.
        $this->actingAs($this->leader)->get(route('student.duplication.similar', [$check->id, 1]))
            ->assertOk()
            ->assertSee('Archive 1 of 1')
            ->assertSee('No abstract was recorded for this capstone.');
    }
}
