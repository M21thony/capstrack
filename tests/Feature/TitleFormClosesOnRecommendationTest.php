<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\Checklist;
use App\Models\Deliverable;
use App\Models\DuplicationCheck;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\ChecklistTracker;
use App\Support\MilestoneProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * The CaPSA recommends a pre-proposal title in CapsTrack, so the Recommended
 * Pre-Proposal Title Form closes on that and the team uploads nothing for it.
 */
class TitleFormClosesOnRecommendationTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private Team $team;

    private Student $leader;

    private CapstoneExpert $capsa;

    private Checklist $checklist;

    private Deliverable $titleForm;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->team, $this->leader] = $this->teamInSection('Treecko');
        $this->capsa = $this->capsaFor();
        $this->checklist = $this->departmentChecklist($this->capsa);
        $this->titleForm = $this->line($this->checklist, Deliverable::TITLE_RECOMMENDATION);
    }

    private function title(int $slot, array $attributes = []): CapstonePreProposal
    {
        $check = DuplicationCheck::create([
            'student_s_y_id' => StudentSchoolYear::where('team_id', $this->team->team_id)->value('s_y_id'), 'document_path' => 'checks/1.pdf',
            'concepts' => [], 'top_score' => 10, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);

        return CapstonePreProposal::create($attributes + [
            'team_id' => $this->team->team_id, 'duplication_check_id' => $check->id, 'slot' => $slot, 'title' => 'Title '.$slot,
            'sdg_goals' => 'SDG 4', 'research_agenda' => ['x'], 'problem_statement' => 'p', 'proposed_solution' => 's',
            'scope' => 'sc', 'limitations' => 'l', 'sdm' => 'Agile', 'submitted_at' => now()->subDays(2),
        ]);
    }

    private function state(): object
    {
        return $this->progressOf($this->checklist, $this->team, $this->titleForm);
    }

    public function test_it_is_open_until_titles_are_submitted(): void
    {
        $this->assertSame(MilestoneProgress::NOT_STARTED, $this->state()->state);
    }

    public function test_submitted_titles_wait_on_the_capsas_recommendation_not_a_check(): void
    {
        $this->title(1);

        $row = $this->state();
        $this->assertSame(MilestoneProgress::HANDED_IN, $row->state);
        $this->assertSame(MilestoneProgress::WAITING_ON_TITLE_REVIEW, $row->waiting_on);
        $this->assertFalse(ChecklistTracker::toCheck($row));
    }

    public function test_the_capsa_recommending_a_title_closes_it(): void
    {
        $title = $this->title(1);
        $this->title(2, ['rejection_reason' => 'Too broad']);

        $this->actingAs($this->capsa, 'expert')
            ->patchJson(route('capsa.verify_teams.pre_proposals.update', $title), ['status' => 'approved'])
            ->assertOk();

        $this->assertSame(MilestoneProgress::DONE, $this->state()->state);
    }

    public function test_an_upload_for_it_is_not_waited_on(): void
    {
        $this->upload($this->leader, $this->titleForm);

        $row = $this->state();
        $this->assertSame(MilestoneProgress::NOT_STARTED, $row->state);
        $this->assertNull($row->file_id);
    }

    public function test_every_title_rejected_leaves_it_open(): void
    {
        $this->title(1, ['rejection_reason' => 'Exists already']);

        $this->assertSame(MilestoneProgress::NOT_STARTED, $this->state()->state);
    }

    public function test_the_team_is_not_asked_to_upload_it(): void
    {
        $this->actingAs($this->leader, 'web');

        $this->get('/student/progress')
            ->assertOk()
            ->assertSee('Closed when your CaPSA recommends one of your pre-proposal titles');

        $this->get(route('student.submission'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertViewHas('checklistItems', fn ($items) => ! $items->contains('id', $this->titleForm->id)
                && $items->contains('id', $this->line($this->checklist, 'team_assignment_form')->id));
    }

    /** The other forms still wait on an upload and the CaPSA's check. */
    public function test_other_forms_are_unchanged(): void
    {
        $form = $this->line($this->checklist, 'team_assignment_form');
        $this->upload($this->leader, $form);

        $this->assertSame(MilestoneProgress::WAITING_ON_CAPSA, $this->progressOf($this->checklist, $this->team, $form)->waiting_on);
    }
}
