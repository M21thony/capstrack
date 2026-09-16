<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A student's enrolment is per term, not once and for all.
 *
 * student_s_y is named and shaped for exactly that, but every writer used to
 * upsert on student_id alone - so it held one row per student forever, and
 * the ~50 places asking for "this student's row" could assume there was only
 * one. A team that does not pass Capstone 1 retakes it, keeping the same
 * members if they want, so a student genuinely has a row per term now.
 */
class TermScopingTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(string $email = 'retaker@my.cspc.edu.ph'): Student
    {
        return Student::factory()->withCompleteProfile()->create(['email' => $email]);
    }

    private function makeTeam(string $name): Team
    {
        return Team::create([
            'team_name' => $name,
            'team_status' => 'Active',
            'is_confirmed' => false,
            'member_count' => 5,
        ]);
    }

    public function test_the_migration_leaves_exactly_one_active_term(): void
    {
        $this->assertSame(1, Term::where('is_active', true)->count());
        $this->assertNotNull(Term::activeId());
    }

    public function test_a_new_enrolment_joins_the_active_term_without_being_told(): void
    {
        $student = $this->makeStudent();
        $team = $this->makeTeam('Team One');

        $sy = StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
        ]);

        $this->assertSame(Term::activeId(), $sy->fresh()->term_id);
    }

    // The case the whole change exists for: a team retakes Capstone 1 with
    // the same members. Their old enrolment has to survive alongside the new.
    public function test_a_student_can_be_enrolled_in_two_terms(): void
    {
        $student = $this->makeStudent();
        $firstTeam = $this->makeTeam('First Attempt');

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $firstTeam->team_id,
        ]);

        $firstTerm = Term::active();

        $secondTerm = Term::create([
            'school_year' => '2027-2028',
            'semester' => 1,
            'capstone_stage' => 1,
            'is_active' => false,
        ]);
        $secondTerm->activate();

        $retakeTeam = $this->makeTeam('Retake');
        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $retakeTeam->team_id,
        ]);

        // Both records exist...
        $this->assertSame(2, StudentSchoolYear::acrossAllTerms()->where('student_id', $student->student_id)->count());

        // ...but "this student's row" means the current term's.
        $current = StudentSchoolYear::where('student_id', $student->student_id)->get();
        $this->assertCount(1, $current);
        $this->assertSame($retakeTeam->team_id, $current->first()->team_id);

        // And last term's is still reachable when asked for deliberately.
        $previous = StudentSchoolYear::forTerm($firstTerm->term_id)
            ->where('student_id', $student->student_id)
            ->first();
        $this->assertSame($firstTeam->team_id, $previous->team_id);
    }

    // Activating a term stands the others down - only one runs at a time.
    public function test_activating_a_term_deactivates_the_previous_one(): void
    {
        $first = Term::active();

        $second = Term::create([
            'school_year' => '2027-2028',
            'semester' => 2,
            'capstone_stage' => 2,
            'is_active' => false,
        ]);
        $second->activate();

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertSame($second->term_id, Term::activeId());
    }

    // Rows written before terms existed, or by a raw insert that does not
    // know about them, must not vanish from the app.
    public function test_rows_with_no_term_are_still_visible(): void
    {
        $student = $this->makeStudent('legacy@my.cspc.edu.ph');
        $team = $this->makeTeam('Legacy Team');

        \DB::table('student_s_y')->insert([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            'term_id' => null,
        ]);

        $this->assertSame(1, StudentSchoolYear::where('student_id', $student->student_id)->count());
    }

    public function test_a_capstone_2_term_knows_which_term_precedes_it(): void
    {
        $one = Term::create(['school_year' => '2030-2031', 'semester' => 1, 'capstone_stage' => 1]);
        $two = Term::create(['school_year' => '2030-2031', 'semester' => 2, 'capstone_stage' => 2]);

        $this->assertSame($one->term_id, $two->previousStage()?->term_id);
        $this->assertNull($one->previousStage());
    }

    public function test_a_team_created_this_term_records_the_term(): void
    {
        $student = $this->makeStudent('leader@my.cspc.edu.ph');
        $student->update(['year_section' => '3rd · A']); // teams are formed in Capstone 1

        $this->actingAs($student, 'web')->post(route('team.store'), [
            'team_name' => 'Brand New Team',
        ]);

        $sy = StudentSchoolYear::where('student_id', $student->student_id)->first();

        $this->assertNotNull($sy);
        $this->assertSame(Term::activeId(), $sy->term_id);
    }
}
