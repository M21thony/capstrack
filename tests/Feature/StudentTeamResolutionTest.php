<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\StudentTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * student_s_y holds one row per school year, so a returning student has
 * several. Resolving their team off whichever row comes back first reads the
 * team as null whenever the earliest enrolment predates it - and the pages
 * that ask decide from it whether you may open the dashboard, apply for an
 * expert, or fill in a pre-proposal.
 */
class StudentTeamResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        return Student::create([
            'email' => 'returning@my.cspc.edu.ph',
            'f_name' => 'Returning',
            'l_name' => 'Student',
            'course' => 'BSIT',
            'year_section' => '4 · B',
        ]);
    }

    private function team(Student $leader, string $name = 'This Year'): Team
    {
        return Team::create([
            'team_name' => $name, 'team_status' => 'Active',
            'leader_id' => $leader->student_id, 'is_confirmed' => true, 'member_count' => 5,
        ]);
    }

    /** The case the old lookup got wrong. */
    public function test_an_older_teamless_row_does_not_hide_this_year_s_team(): void
    {
        $student = $this->student();
        $team = $this->team($student);

        // Last year: enrolled, never joined a team. Written first, so it is
        // the row an unordered first() hands back.
        StudentSchoolYear::create([
            'student_id' => $student->student_id, 'school_year' => '2025-2026',
        ]);

        StudentSchoolYear::create([
            'student_id' => $student->student_id, 'school_year' => '2026-2027', 'team_id' => $team->team_id,
        ]);

        $this->assertSame((int) $team->team_id, StudentTeam::idFor($student));
        $this->assertTrue(StudentTeam::has($student));
        $this->assertSame('This Year', StudentTeam::for($student)?->team_name);

        // And the old spelling really does get it wrong, which is why this
        // test exists rather than a comment.
        $naive = StudentSchoolYear::where('student_id', $student->student_id)->first();
        $this->assertNull($naive?->team_id);
    }

    /** Two teams across two years: the newer one is the answer. */
    public function test_the_newest_team_wins(): void
    {
        $student = $this->student();
        $old = $this->team($student, 'Last Year');
        $new = $this->team($student, 'This Year');

        StudentSchoolYear::create(['student_id' => $student->student_id, 'school_year' => '2025-2026', 'team_id' => $old->team_id]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'school_year' => '2026-2027', 'team_id' => $new->team_id]);

        $this->assertSame('This Year', StudentTeam::for($student)?->team_name);
    }

    public function test_a_student_in_no_team_resolves_to_null(): void
    {
        $student = $this->student();
        StudentSchoolYear::create(['student_id' => $student->student_id, 'school_year' => '2026-2027']);

        $this->assertNull(StudentTeam::idFor($student));
        $this->assertNull(StudentTeam::for($student));
        $this->assertFalse(StudentTeam::has($student));
        $this->assertNull(StudentTeam::rowFor($student));
    }

    public function test_a_student_with_no_enrolment_row_at_all_resolves_to_null(): void
    {
        $this->assertNull(StudentTeam::idFor($this->student()));
        $this->assertFalse(StudentTeam::has(null));
        $this->assertNull(StudentTeam::idFor(null));
    }

    /** It takes an id as readily as a model, since the callers have both. */
    public function test_it_accepts_an_id_or_a_model(): void
    {
        $student = $this->student();
        $team = $this->team($student);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'school_year' => '2026-2027', 'team_id' => $team->team_id]);

        $this->assertSame(StudentTeam::idFor($student), StudentTeam::idFor($student->student_id));
    }

    /**
     * The pages that ask, asked through the real routes: a returning student
     * with an older teamless row still sees their team.
     */
    public function test_the_dashboard_finds_the_team_for_a_returning_student(): void
    {
        $student = $this->student();
        $team = $this->team($student, 'Bulbasaur');

        StudentSchoolYear::create(['student_id' => $student->student_id, 'school_year' => '2025-2026']);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'school_year' => '2026-2027', 'team_id' => $team->team_id]);

        $this->actingAs($student, 'web')->get('/student/dashboard')->assertStatus(200)->assertSee('Bulbasaur');
    }

    public function test_the_pre_proposal_page_finds_it_too(): void
    {
        $student = $this->student();
        $team = $this->team($student);

        StudentSchoolYear::create(['student_id' => $student->student_id, 'school_year' => '2025-2026']);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'school_year' => '2026-2027', 'team_id' => $team->team_id]);

        // No "you are not in a team yet" notice: it found the team.
        $this->actingAs($student, 'web')->get('/student/duplication')
            ->assertStatus(200)
            ->assertDontSee('You are not in a team yet');
    }
}
