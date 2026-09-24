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
            ->assertSeeInOrder(['Pre-proposal content', 'Title', self::TITLE, 'SDG', 'SDG 3 – Good Health and Well-being'])
            ->assertSee('<span class="rp-pc sim-close">75', false)
            ->assertSee('Close to existing work')
            ->assertSee('1 close')
            ->assertSee('1 some overlap')
            ->assertSee('1 little overlap')
            ->assertSee('<b class="sim-some">50%</b>', false)
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

    public function test_more_than_three_goals_fold_into_a_more_button(): void
    {
        $check = $this->report(['sdg_goals' => 'SDG 1 – No Poverty, SDG 3 – Good Health and Well-being, SDG 4 – Quality Education, SDG 9 – Industry, Innovation and Infrastructure, SDG 16 – Peace, Justice and Strong Institutions']);

        $this->actingAs($this->leader)->get(route('student.duplication.report', $check->id))
            ->assertSee('+2 more');
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

    /** More suggestions do not mean more rows: one column per role, the rest folded. */
    public function test_many_suggested_experts_stay_one_column_per_role(): void
    {
        $check = $this->report();
        $suggested = [];
        foreach ([['Mark Balilla, MIT', 'adviser', 'Adviser'], ['Kenn Aurillas', 'adviser', 'Adviser'], ['Ian Tumambing', 'adviser', 'Adviser'], ['Maria Santos', 'consultant', 'Consultant']] as [$name, $role, $label]) {
            $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
            $suggested[] = ['id' => $expert->expert_id, 'name' => $name, 'expertise' => 'Web Development', 'reason' => 'Fits the study.', 'role' => $role, 'role_label' => $label];
        }
        $check->update(['instructors_suggested' => $suggested]);

        $this->actingAs($this->leader)->get(route('student.duplication.report', $check->id))
            ->assertSee('style="--roles: 2"', false)
            ->assertSee('Recommended Adviser <span>&middot; 3 suggested</span>', false)
            ->assertSeeInOrder(['Mark Balilla, MIT', 'Also suggested:', 'Kenn Aurillas · Ian Tumambing', 'Recommended Consultant', 'Maria Santos'])
            // The folded ones are still whole, one click away.
            ->assertSee('<details class="rp-also">', false);
    }
}
