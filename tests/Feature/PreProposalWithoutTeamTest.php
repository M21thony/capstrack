<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A student with no team can open the Pre-Proposal page and run a duplication
 * check on a title. They cannot save one or turn a set in: a pre-proposal
 * belongs to a team.
 *
 * The page used to redirect them to Team Management, and that page showed no
 * reason, so it read as a broken link.
 */
class PreProposalWithoutTeamTest extends TestCase
{
    use RefreshDatabase;

    private function teamless(): Student
    {
        $student = Student::create([
            'email' => 'noteam@my.cspc.edu.ph',
            'f_name' => 'No',
            'l_name' => 'Team',
            'course' => 'BSIT',
            'year_section' => '4 · B',
        ]);

        // Enrolled, but in no team - the row exists with team_id null.
        StudentSchoolYear::create(['student_id' => $student->student_id, 'school_year' => '2026-2027']);

        return $student;
    }

    public function test_the_page_opens_without_a_team(): void
    {
        $response = $this->actingAs($this->teamless(), 'web')->get('/student/duplication');

        $response->assertStatus(200);
        $response->assertSee('You are not in a team yet');
    }

    public function test_it_no_longer_redirects_to_team_management(): void
    {
        $response = $this->actingAs($this->teamless(), 'web')->get('/student/duplication');

        $response->assertStatus(200);
        $this->assertNull($response->headers->get('Location'));
    }

    /** Saving belongs to a team, and still says so. */
    public function test_saving_still_needs_a_team(): void
    {
        $response = $this->actingAs($this->teamless(), 'web')->post('/pre-proposal', [
            'title' => 'A title with no team behind it',
        ]);

        $response->assertRedirect();
        $this->assertNotNull(session('error'));
    }

    public function test_turning_the_set_in_still_needs_a_team(): void
    {
        $response = $this->actingAs($this->teamless(), 'web')->patch('/pre-proposal', ['status' => 'submitted']);

        $response->assertRedirect();
        $this->assertNotNull(session('error'));
    }

    /**
     * The check is recorded against the student's own enrolment row, never the
     * team, so it was only ever the gate that needed a team - not the work.
     */
    public function test_the_duplication_check_does_not_ask_for_a_team(): void
    {
        $this->withoutExceptionHandling();

        // The first thing it objects to is the missing PDF, not the missing
        // team - which is the whole point: the gate is gone and the work
        // itself never needed one.
        try {
            $this->actingAs($this->teamless(), 'web')->postJson('/pre-proposal/duplication-checks', []);
            $this->fail('The check should have rejected the empty request.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
            $this->assertStringNotContainsStringIgnoringCase('team', json_encode($e->errors()));
        }
    }

    /**
     * A failed check has to come back as JSON, because that is what the page
     * asked for.
     *
     * The handler used to render JSON only for api/* paths, so a validation
     * failure here returned an HTML error page while the page was calling
     * .json() on it - the "Unexpected token '<'" a student was left looking
     * at. The workaround in the view reads the body as text first; this is
     * the cause.
     */
    public function test_a_failed_check_answers_in_json_when_json_was_asked_for(): void
    {
        $this->actingAs($this->teamless(), 'web')
            ->postJson('/pre-proposal/duplication-checks', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('document');
    }

    /** A student who does have a team still sees the real page. */
    public function test_a_student_with_a_team_sees_no_notice(): void
    {
        $student = $this->teamless();

        $team = Team::create([
            'team_name' => 'Has A Team', 'team_status' => 'Active',
            'leader_id' => $student->student_id, 'is_confirmed' => true, 'member_count' => 5,
        ]);

        StudentSchoolYear::where('student_id', $student->student_id)->update(['team_id' => $team->team_id]);

        $response = $this->actingAs($student, 'web')->get('/student/duplication');

        $response->assertStatus(200);
        $response->assertDontSee('You are not in a team yet');
    }
}
