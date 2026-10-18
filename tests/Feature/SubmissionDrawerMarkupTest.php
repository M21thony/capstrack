<?php

namespace Tests\Feature;

use App\Models\CapstoneDocument;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every upload opens the same small pop-up over the page the student is on.
 * The Document Submission page carries no form of its own - just what the
 * team has sent and a button that opens the pop-up.
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

    public function test_the_page_has_no_form_only_the_submissions_and_a_button(): void
    {
        CapstoneDocument::create([
            'student_id' => $this->student->student_id, 'file_name' => 'Chapter1_final', 'chapter' => 'Chapter 1',
            'file_path' => 'submissions/c1.pdf', 'file_version' => 2, 'document_status' => 'Pending',
        ]);

        // Document Submission is part of Documents now.
        $this->actingAs($this->student)->get(route('student.submission'))->assertRedirect(route('student.documents'));

        $response = $this->actingAs($this->student)->get(route('student.documents'))->assertOk();

        $response->assertSee("Your team's documents", false);
        $response->assertSee('Chapter1_final');
        $response->assertSee('v2');
        // A file for no line: no "What is this for?", straight to the file.
        $response->assertSee("window.openSubmission('none', this)", false);
        $response->assertSee('Submit a paper');
        $response->assertDontSee('id="submission-form-root"', false);
        $response->assertDontSee('id="dropZone"', false);
        $response->assertDontSee('data-open-upload', false);
    }

    public function test_the_old_in_place_form_is_gone(): void
    {
        $this->actingAs($this->student)
            ->followingRedirects()
            ->get(route('student.submission', ['inline' => 1]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertDontSee('id="submission-form-root"', false)
            ->assertDontSee('data-mode="inline"', false);
    }

    public function test_every_student_page_can_open_the_pop_up(): void
    {
        $response = $this->actingAs($this->student)->get(route('student.dashboard'))->assertOk();

        $response->assertSee('id="upload-config"', false);
        $response->assertSee(json_encode(route('student.submission.plan')), false);
        $response->assertSee(json_encode(route('student.submission.store')), false);
        $response->assertSee(json_encode(route('student.documents')), false);
        // The notice after a send can take it back, as Unsubmit does.
        $response->assertSee(json_encode(route('student.submission.unsubmit', ['document' => '__ID__'])), false);
        $response->assertSee('id="submission-toast"', false);
        $response->assertDontSee('name="submission-form-url"', false);
        $response->assertDontSee('id="submission-form-root"', false);
    }
}
