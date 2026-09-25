<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * When the CaPSA turns down every title a team submitted, that round is over:
 * the team saves new titles and submits them. The page used to stay on
 * "Submitted for checking" with nothing to press.
 */
class PreProposalRejectedRoundTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private Team $team;

    private Student $leader;

    private CapstoneExpert $capsa;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->team, $this->leader] = $this->teamInSection('Chimchar');
        $this->capsa = $this->capsaFor();
    }

    private function title(int $slot, array $attributes = []): CapstonePreProposal
    {
        $check = DuplicationCheck::create([
            'student_s_y_id' => StudentSchoolYear::where('team_id', $this->team->team_id)->value('s_y_id'), 'document_path' => 'checks/'.$slot.'.pdf',
            'concepts' => [], 'top_score' => 10, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);

        return CapstonePreProposal::create($attributes + [
            'team_id' => $this->team->team_id, 'duplication_check_id' => $check->id, 'slot' => $slot, 'title' => 'Title '.$slot,
            'sdg_goals' => 'SDG 4', 'research_agenda' => ['x'], 'problem_statement' => 'p', 'proposed_solution' => 's',
            'scope' => 'sc', 'limitations' => 'l', 'sdm' => 'Agile', 'submitted_at' => now()->subDays(2),
        ]);
    }

    public function test_once_every_title_is_rejected_the_page_asks_for_new_ones(): void
    {
        $this->title(1, ['rejection_reason' => 'Too broad']);
        $this->title(2, ['rejection_reason' => 'Already done']);

        $this->actingAs($this->leader)->get(route('student.duplication'))
            ->assertOk()
            ->assertDontSee('Submitted for checking')
            ->assertSee('Your CaPSA did not recommend any of these titles.')
            ->assertSee('Save a new title to submit.')
            // Nothing new is saved yet, so there is nothing to submit.
            ->assertDontSee('id="pp-submit-btn"', false);
    }

    public function test_a_new_title_is_submitted_and_the_rejected_ones_stay_rejected(): void
    {
        $rejected = $this->title(1, ['rejection_reason' => 'Too broad']);
        $new = $this->title(2, ['submitted_at' => null]);

        $this->actingAs($this->leader)->get(route('student.duplication'))
            ->assertSee('id="pp-submit-btn"', false);

        $this->actingAs($this->leader)
            ->patch(route('student.pre-proposal.submit'), ['status' => 'submitted'])
            ->assertSessionHas('status');

        $this->assertNotNull($new->fresh()->submitted_at);
        $this->assertSame('rejected', $rejected->fresh()->status);

        // The new round is with the CaPSA, and an old rejection does not count
        // as her having started on it: it can still be taken back.
        $this->actingAs($this->leader)->get(route('student.duplication'))
            ->assertSee('Submitted for checking')
            ->assertSee('Unsubmit');

        $this->actingAs($this->leader)
            ->patch(route('student.pre-proposal.submit'), ['status' => 'draft'])
            ->assertSessionHas('status');

        $this->assertNull($new->fresh()->submitted_at);
        $this->assertNotNull($rejected->fresh()->submitted_at);
    }

    public function test_a_rejected_title_can_be_removed_once_the_round_is_over(): void
    {
        $rejected = $this->title(1, ['rejection_reason' => 'Too broad']);

        $this->actingAs($this->leader)
            ->delete(route('student.pre-proposal.destroy', $rejected))
            ->assertSessionHas('status');

        $this->assertModelMissing($rejected);
    }

    public function test_a_set_with_titles_still_waiting_stays_turned_in(): void
    {
        $this->title(1, ['rejection_reason' => 'Too broad']);
        $this->title(2);

        $this->actingAs($this->leader)->get(route('student.duplication'))
            ->assertSee('Submitted for checking')
            ->assertDontSee('Your CaPSA did not recommend any of these titles.');

        $this->actingAs($this->leader)
            ->patch(route('student.pre-proposal.submit'), ['status' => 'submitted'])
            ->assertSessionHas('error');
    }

    public function test_rejecting_the_last_title_tells_the_team_and_the_capsa_list_says_so(): void
    {
        $this->title(1, ['rejection_reason' => 'Too broad']);
        $last = $this->title(2);

        $this->actingAs($this->capsa, 'expert')
            ->patchJson(route('capsa.verify_teams.pre_proposals.update', $last), ['status' => 'rejected', 'reason' => 'Not feasible'])
            ->assertOk();

        $this->assertTrue(Notification::where('student_id', $this->leader->student_id)->where('type', 'titles_rejected')->exists());

        $this->actingAs($this->capsa, 'expert')->get(route('capsa.team_management'))
            ->assertSee('All titles turned down - the team is writing new ones');
    }
}
