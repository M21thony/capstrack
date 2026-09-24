<?php

namespace Tests\Feature;

use App\Models\DuplicationCheck;
use App\Models\StudentSchoolYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * The last scan is drawn again when the page opens. That used to fill the
 * Pre-Proposal Details form from the PDF once more, so a research agenda the
 * student had removed came back on refresh - and a rejected save lost the
 * student's own answers the same way. The page now tells the form it is a
 * reload, and whether the server has put answers back.
 */
class PreProposalFormKeepsEditsTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private function scan($student): DuplicationCheck
    {
        return DuplicationCheck::create([
            'student_s_y_id' => StudentSchoolYear::where('student_id', $student->student_id)->value('s_y_id'),
            'document_path' => 'checks/proposal.pdf',
            'concepts' => ['title' => 'Smart Campus Parking Finder', 'research_agenda' => ['Smart Cities', 'Applications and Systems Development']],
            'top_score' => 10, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);
    }

    public function test_the_last_scan_is_drawn_as_a_reload_so_the_students_edits_win(): void
    {
        [, $student] = $this->teamInSection('Bulbasaur');
        $this->scan($student);

        $this->actingAs($student, 'web')->get(route('student.duplication'))
            ->assertOk()
            ->assertSee('const PP_HAS_OLD_INPUT = false;', false)
            ->assertSee('function saveDraft()', false)
            ->assertSee(', true);', false);
    }

    public function test_a_rejected_save_keeps_the_students_answers(): void
    {
        [, $student] = $this->teamInSection('Bulbasaur');
        $check = $this->scan($student);

        // Research agenda emptied, as the student left it: the save is refused.
        $this->actingAs($student, 'web')
            ->from(route('student.duplication'))
            ->post(route('student.pre-proposal.store'), ['duplication_check_id' => $check->id, 'title' => 'Campus Parking Finder with Live Slots', 'research_agenda' => []])
            ->assertRedirect(route('student.duplication'))
            ->assertSessionHasErrors('research_agenda');

        $this->get(route('student.duplication'))
            ->assertOk()
            ->assertSee('const PP_HAS_OLD_INPUT = true;', false)
            ->assertSee('Campus Parking Finder with Live Slots');
    }
}
