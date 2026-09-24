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
        $this->actingAs($this->leader())->get(route('student.duplication'))
            ->assertOk()
            ->assertSee('No document checked')
            ->assertSee('<span class="stateline s-red" id="result-badge" hidden>', false)
            // Without this the badge's own display beat [hidden] and the red
            // "Highly similar" showed on an empty page.
            ->assertSee('.stateline[hidden]{display:none}', false);
    }

    public function test_the_announcement_pop_up_has_no_empty_picture(): void
    {
        $html = $this->actingAs($this->leader())->get(route('student.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('<img class="ann-avatar" id="annModalAvatar" alt="" hidden>', $html);
        $this->assertStringNotContainsString('id="annModalAvatar" src=""', $html);
        $this->assertStringContainsString('avatar.hidden = !el.dataset.adminAvatar;', $html);
    }

    public function test_the_closed_submission_drawer_casts_no_shadow_onto_the_page(): void
    {
        $css = file_get_contents(public_path('css/submission-drawer.css'));

        // Closed: out of sight and out of the tab order, with no shadow.
        $this->assertMatchesRegularExpression('/\.dw\{[^}]*visibility:hidden;[^}]*\}/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.dw\{[^}]*box-shadow[^}]*\}/', $css);
        // Open: the shadow comes with it.
        $this->assertMatchesRegularExpression('/\.dw\.on\{[^}]*visibility:visible;[^}]*box-shadow:[^}]*\}/', $css);
    }

    public function test_a_receipt_photo_whose_file_is_gone_shows_a_placeholder(): void
    {
        $this->assertStringContainsString("box.classList.add('is-missing');", file_get_contents(resource_path('js/financial/student.js')));
        $this->assertStringContainsString('.fx-mine-thumb.is-missing{', file_get_contents(resource_path('css/financial.css')));
    }
}
