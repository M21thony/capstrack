<?php

namespace Tests\Feature;

use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Pre-Proposal Details form keeps what a team types, per check, so going
 * back or reloading does not send them to retype it. The page tells its draft
 * script which check it is drawing - the one draft worth keeping.
 */
class PreProposalDraftAutosaveTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Student, 1: StudentSchoolYear, 2: Team} */
    private function leader(): array
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
        ]);
        $team = Team::create(['team_name' => 'Draft Team', 'team_status' => 'Active', 'leader_id' => $student->student_id]);
        $sy = StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => 'BSIT']);

        return [$student, $sy, $team];
    }

    private function check(StudentSchoolYear $sy): DuplicationCheck
    {
        return DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'checks/draft.pdf', 'concepts' => ['title' => 'From the PDF'],
            'top_score' => 20, 'verdict' => 'Low Similarity', 'matches' => [], 'instructors_suggested' => [],
        ]);
    }

    public function test_the_form_saves_as_it_is_typed_and_keeps_the_current_checks_draft(): void
    {
        [$student, $sy] = $this->leader();
        $check = $this->check($sy);

        $this->actingAs($student)->get(route('student.duplication'))
            ->assertOk()
            ->assertSee('id="pp-autosave"', false)
            ->assertSee('Your answers save automatically as you type.')
            ->assertSee("if (e.target.closest && e.target.closest('#pp-form')) saveDraft();", false)
            // The check being drawn is the one whose draft survives the cleanup.
            ->assertSee('const current = "'.$check->id.'";', false)
            // And its saved answers are laid over the PDF's after it is drawn.
            ->assertSee('applyDraft(ppDraft);', false);
    }

    public function test_once_the_check_is_saved_as_a_pre_proposal_no_draft_is_kept(): void
    {
        [$student, $sy, $team] = $this->leader();
        $check = $this->check($sy);
        CapstonePreProposal::create([
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1,
            'title' => 'A Saved Pre-Proposal', 'sdg_goals' => 'SDG 4', 'research_agenda' => ['Systems'],
            'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc', 'limitations' => 'l', 'sdm' => 'Agile',
        ]);

        // The page no longer draws that check, so every stored draft is cleared.
        $this->actingAs($student)->get(route('student.duplication'))
            ->assertOk()
            ->assertSee('const current = null;', false);
    }
}
