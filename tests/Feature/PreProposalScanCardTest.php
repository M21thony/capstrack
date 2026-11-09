<?php

namespace Tests\Feature;

use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\StudentSchoolYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * The card at the top of the Pre-Proposal page used to show "Highly similar"
 * and a red and green bar before anything was checked. It is empty until a
 * scan, and says what a result means calmly once there is one.
 */
class PreProposalScanCardTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    public function test_before_a_scan_nothing_is_flagged(): void
    {
        [, $leader] = $this->teamInSection('Scan');

        $this->actingAs($leader)->get(route('student.duplication'))
            ->assertOk()
            ->assertSee('No document checked yet')
            ->assertSee('<div id="sc-result" hidden>', false)
            ->assertDontSee('Highly similar')
            ->assertDontSee('class="scalebar', false);
    }

    public function test_a_saved_title_reads_its_score_calmly(): void
    {
        [$team, $leader] = $this->teamInSection('Scan');
        $check = DuplicationCheck::create([
            'student_s_y_id' => StudentSchoolYear::where('student_id', $leader->student_id)->value('s_y_id'),
            'document_path' => 'checks/s.pdf', 'concepts' => [], 'top_score' => 75, 'verdict' => 'High Similarity',
            'matches' => [], 'instructors_suggested' => [],
        ]);
        CapstonePreProposal::create([
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1, 'title' => 'A Saved Title',
            'sdg_goals' => 'SDG 4', 'research_agenda' => ['x'], 'problem_statement' => 'p', 'proposed_solution' => 's',
            'scope' => 'sc', 'limitations' => 'l', 'sdm' => 'Agile',
        ]);

        $this->actingAs($leader)->get(route('student.duplication'))
            ->assertSee('<b class="sim-close">75%</b> closest match &middot; Close to existing work', false)
            ->assertDontSee('highly similar');
    }
}
