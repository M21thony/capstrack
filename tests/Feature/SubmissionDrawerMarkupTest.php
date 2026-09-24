<?php

namespace Tests\Feature;

use App\Models\CapstoneDocument;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Handing in a file happens where the student already is: the Document
 * Submission page from the menu, or the form opened in place under a line on
 * Progress or in the Review Viewer. The in-place form is fetched, so asked for
 * that way the route answers with the form alone.
 */
class SubmissionDrawerMarkupTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $team = Team::create(['team_name' => 'Team Alpha', 'project_title' => 'Alpha', 'team_status' => 'Active']);
        $this->student = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
        ]);
        StudentSchoolYear::create([
            'student_id' => $this->student->student_id, 'team_id' => $team->team_id, 'program' => 'BSIT',
        ]);
    }

    public function test_opened_in_place_the_route_answers_with_the_short_form_alone(): void
    {
        $response = $this->actingAs($this->student)
            ->get(route('student.submission', ['inline' => 1]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $response->assertSee('id="submission-form-root"', false);
        $response->assertSee('data-mode="inline"', false);
        $response->assertSee('data-submission-close', false);
        $this->assertStringNotContainsString('<html', $response->getContent());
    }

    public function test_from_the_menu_it_is_a_page_with_the_teams_submissions(): void
    {
        CapstoneDocument::create([
            'student_id' => $this->student->student_id, 'file_name' => 'Chapter1_final', 'chapter' => 'Chapter 1',
            'file_path' => 'submissions/c1.pdf', 'file_version' => 2, 'document_status' => 'Pending',
        ]);

        $response = $this->actingAs($this->student)->get(route('student.submission'))->assertOk();

        $this->assertStringContainsString('<html', $response->getContent());
        $response->assertSee('data-mode="page"', false);
        $response->assertSee("Your team's submissions", false);
        $response->assertSee('Chapter1_final');
        $response->assertSee('version 2');
        // Nothing to cancel on its own page.
        $response->assertDontSee('data-submission-close', false);
    }

    public function test_every_student_page_can_open_the_form_but_does_not_carry_it(): void
    {
        $response = $this->actingAs($this->student)->get(route('student.dashboard'))->assertOk();

        $response->assertSee('name="submission-form-url"', false);
        $response->assertSee('id="submission-toast"', false);
        $response->assertDontSee('id="submission-form-root"', false);
        // The slide-in panel is gone.
        $response->assertDontSee('id="submission-drawer"', false);
    }
}
