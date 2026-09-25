<?php

namespace Tests\Feature;

use App\Models\DuplicationCheck;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Pre-Proposal Details form says what is still missing before Save is
 * pressed: under each field as it opens, and counted beside Save, updated as
 * the student types. Save used to be the first moment anything was said.
 */
class PreProposalFormShowsWhatIsLeftTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        $student = Student::factory()->withCompleteProfile()->create(['course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A']);
        $team = Team::create(['team_name' => 'Draft Team', 'team_status' => 'Active', 'leader_id' => $student->student_id]);
        $sy = StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => 'BSIT']);
        DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'checks/draft.pdf',
            'concepts' => ['title' => 'A Web-Based Monitoring System', 'problem_statement' => 'Teams lose track.'],
            'top_score' => 20, 'verdict' => 'Low', 'matches' => [], 'instructors_suggested' => [],
        ]);

        return $this->actingAs($student)->get(route('student.duplication'))->assertOk()->getContent();
    }

    public function test_the_form_checks_itself_as_it_opens_and_as_it_is_typed_in(): void
    {
        $html = $this->page();

        // Opening the form checks it straight away.
        $this->assertMatchesRegularExpression('/function openPpFormModal\(\) \{.*?refreshPpForm\(\);\s*\}/s', $html);
        // Typing, ticking and adding agenda items check it again.
        $this->assertStringContainsString("document.addEventListener('input', function (e) { if (e.target.closest && e.target.closest('#pp-form')) queuePpRefresh(e); });", $html);
        $this->assertStringContainsString("classList.contains('show')) queuePpRefresh();", $html);
    }

    public function test_what_is_left_is_counted_beside_save_and_each_one_jumps_to_its_field(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('<span class="pp-foot-note" id="pp-foot-note">', $html);
        $this->assertStringContainsString("' things left: '", $html);
        $this->assertStringContainsString("'Everything is filled in. Ready to save.'", $html);
        $this->assertStringContainsString("jump.addEventListener('click', function () { ppGoTo(p[0]); });", $html);
        // Still shown on a phone, above the buttons.
        $this->assertStringContainsString('.pp-foot-note { width: 100%; }', $html);
        $this->assertStringNotContainsString('.pp-foot-note { display: none; }', $html);
    }

    public function test_a_reason_the_server_gave_stays_until_that_field_is_changed(): void
    {
        $html = $this->page();

        $this->assertStringContainsString("const ppServerErrors = new Set(Array.from(document.querySelectorAll('#pp-form [data-field].has-error')", $html);
        $this->assertStringContainsString('if (ppServerErrors.has(key)) return;', $html);
    }
}
