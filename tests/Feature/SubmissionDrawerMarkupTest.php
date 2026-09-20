<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Handing in a file no longer means going anywhere: the panel is on every
 * student page, and the form is fetched into it. So the submission route has
 * to answer with a fragment - markup with no page around it - and every
 * student page has to carry the panel it drops into.
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

    public function test_the_submission_route_answers_with_a_bare_form_not_a_page(): void
    {
        $response = $this->actingAs($this->student)->get(route('student.submission'))->assertOk();

        $response->assertSee('id="submission-form-root"', false);
        $this->assertStringNotContainsString('<html', $response->getContent());
    }

    public function test_every_student_page_carries_the_panel_but_not_the_form(): void
    {
        $response = $this->actingAs($this->student)->get(route('student.dashboard'))->assertOk();

        $response->assertSee('id="submission-drawer"', false);
        $response->assertSee('id="submission-toast"', false);
        $response->assertDontSee('id="submission-form-root"', false);
    }
}
