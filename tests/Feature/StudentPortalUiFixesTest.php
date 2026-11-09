<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Things that looked broken on the student portal: a verdict before any
 * check, broken-image boxes, and a grey band down the side of every page.
 */
class StudentPortalUiFixesTest extends TestCase
{
    use RefreshDatabase;

    private function leader(): Student
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
            'tutorial_completed_at' => now(),
        ]);
        $team = Team::create(['team_name' => 'UI Team', 'team_status' => 'Active', 'leader_id' => $student->student_id]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => 'BSIT']);

        return $student;
    }

    public function test_the_pre_proposal_page_says_nothing_about_similarity_before_a_check(): void
    {
        // The red badge is gone altogether: the scan card is empty until a
        // scan, so nothing can show on an empty page.
        $this->actingAs($this->leader())->get(route('student.duplication'))
            ->assertOk()
            ->assertSee('No document checked yet')
            ->assertSee('<div id="sc-result" hidden>', false)
            ->assertDontSee('id="result-badge"', false)
            ->assertDontSee('Highly similar');
    }

    public function test_the_closed_submission_drawer_casts_no_shadow_onto_the_page(): void
    {
        $css = file_get_contents(public_path('css/submission-drawer.css'));

        // The slide-in drawer that sat parked off the right edge is gone. The
        // upload pop-up is a <dialog>, which draws nothing - and so no
        // shadow - until it is open.
        $this->assertDoesNotMatchRegularExpression('/\.dw\{/', $css);
        $this->assertMatchesRegularExpression('/dialog\.up\{[^}]*box-shadow:[^}]*\}/', $css);
        $this->assertMatchesRegularExpression('/dialog\.up\[open\]\{display:flex/', $css);
    }

    public function test_a_receipt_photo_whose_file_is_gone_shows_a_placeholder(): void
    {
        $this->assertStringContainsString("box.classList.add('is-missing');", file_get_contents(resource_path('js/financial/student.js')));
        $this->assertStringContainsString('.fx-mine-thumb.is-missing{', file_get_contents(resource_path('css/financial.css')));
    }
}
