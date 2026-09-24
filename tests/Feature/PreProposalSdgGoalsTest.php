<?php

namespace Tests\Feature;

use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\SustainableDevelopmentGoals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * A study often serves more than one UN Sustainable Development Goal, so a
 * pre-proposal keeps every goal the team ticks, on one line.
 */
class PreProposalSdgGoalsTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private const TITLE = 'Digital Inventory Patient Records Management System Designed Serving Small Lying Clinics';

    private Team $team;

    private Student $leader;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->team, $this->leader] = $this->teamInSection('Turtwig');
    }

    private function check(): DuplicationCheck
    {
        return DuplicationCheck::create([
            'student_s_y_id' => StudentSchoolYear::where('student_id', $this->leader->student_id)->value('s_y_id'),
            'document_path' => 'checks/x.pdf', 'concepts' => ['title' => self::TITLE],
            'top_score' => 20, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);
    }

    private function save(mixed $goals)
    {
        return $this->actingAs($this->leader)->post(route('student.pre-proposal.store'), [
            'title' => self::TITLE, 'sdg_goals' => $goals, 'research_agenda' => ['Health'],
            'problem_statement' => 'x', 'proposed_solution' => 'x', 'scope' => 'x', 'limitations' => 'x',
            'sdm' => 'Agile', 'duplication_check_id' => $this->check()->id, 'tags' => ['IoT'],
            'clients' => 'Barangay Hall', 'client_address' => 'Naga City',
            'client_representative' => 'Ana Cruz', 'client_position' => 'Secretary',
        ]);
    }

    public function test_several_goals_are_saved_on_one_line(): void
    {
        $this->save(['SDG 3 – Good Health and Well-being', 'SDG 9 – Industry, Innovation and Infrastructure'])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'SDG 3 – Good Health and Well-being, SDG 9 – Industry, Innovation and Infrastructure',
            CapstonePreProposal::firstOrFail()->sdg_goals,
        );
    }

    public function test_every_goal_fits(): void
    {
        $this->save(SustainableDevelopmentGoals::ALL)->assertSessionHasNoErrors();

        $this->assertSame(SustainableDevelopmentGoals::ALL, SustainableDevelopmentGoals::split(CapstonePreProposal::firstOrFail()->sdg_goals));
    }

    public function test_at_least_one_goal_is_needed(): void
    {
        $this->save([])->assertSessionHasErrors(['sdg_goals' => 'Choose at least one Sustainable Development Goal.']);
        $this->assertSame(0, CapstonePreProposal::count());
    }

    /** A page opened before the change still sends one goal as text. */
    public function test_a_single_goal_sent_as_text_is_still_saved(): void
    {
        $this->save('SDG 4 – Quality Education')->assertSessionHasNoErrors();

        $this->assertSame('SDG 4 – Quality Education', CapstonePreProposal::firstOrFail()->sdg_goals);
    }

    public function test_a_goal_whose_name_has_a_comma_is_not_split_in_two(): void
    {
        $this->assertSame(
            ['SDG 9 – Industry, Innovation and Infrastructure', 'SDG 4 – Quality Education'],
            SustainableDevelopmentGoals::split('SDG 9 – Industry, Innovation and Infrastructure, SDG 4 – Quality Education'),
        );
    }

    public function test_the_form_offers_every_goal_as_a_tick_and_keeps_them_after_a_refused_save(): void
    {
        $this->check();

        $this->actingAs($this->leader)
            ->withSession(['_old_input' => ['sdg_goals' => ['SDG 4 – Quality Education', 'SDG 13 – Climate Action']]])
            ->get(route('student.duplication'))
            ->assertOk()
            ->assertSee('name="sdg_goals[]" value="SDG 17 – Partnerships for the Goals"', false)
            ->assertSee('name="sdg_goals[]" value="SDG 4 – Quality Education" onchange="updateSdgCount(); clearFieldError(this)" checked', false)
            ->assertSee('name="sdg_goals[]" value="SDG 13 – Climate Action" onchange="updateSdgCount(); clearFieldError(this)" checked', false)
            ->assertDontSee('<select name="sdg_goals"', false);
    }
}
