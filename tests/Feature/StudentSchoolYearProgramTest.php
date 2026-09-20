<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use Database\Seeders\StudentRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * student_s_y.program is what the per-program matching groups on - the
 * expert's capacity bars, the cap a student is stopped at - and the writers
 * that predate the column left it null, so teams counted for no program at
 * all. New rows derive it from the student's course; the old ones are
 * backfilled.
 */
class StudentSchoolYearProgramTest extends TestCase
{
    use RefreshDatabase;

    private function studentWithFullCourseName(string $email, string $course): Student
    {
        return Student::create([
            'email' => $email, 'f_name' => 'Course', 'l_name' => 'Carrier',
            'course' => $course, 'p_picture' => 'avatar-01',
        ]);
    }

    /** A row written without a program takes it from the student's course. */
    public function test_a_new_enrolment_row_takes_its_program_from_the_students_course(): void
    {
        $student = $this->studentWithFullCourseName('full.name@my.cspc.edu.ph', 'Bachelor of Science in Information Technology');

        $row = StudentSchoolYear::create(['student_id' => $student->student_id, 'school_year' => '2026-2027']);

        $this->assertSame('BSIT', $row->program);
    }

    /** The caller's word fills a gap; it does not overwrite what is there. */
    public function test_record_for_fills_a_missing_program_but_never_overwrites(): void
    {
        $student = $this->studentWithFullCourseName('record@my.cspc.edu.ph', 'Bachelor of Science in Information Systems');

        $row = StudentSchoolYear::recordFor($student->student_id, '2026-2027', 'BSIS');
        $this->assertSame('BSIS', $row->fresh()->program);

        // A later roster year must not rewrite what the profile says.
        $again = StudentSchoolYear::recordFor($student->student_id, '2027-2028', 'BSIT');
        $this->assertSame('BSIS', $again->fresh()->program);
        $this->assertSame('2027-2028', $again->fresh()->school_year);
    }

    /** The migration's engine: legacy nulls map from the course, and a
     * student with no course is left alone rather than guessed. */
    public function test_backfill_fills_legacy_rows_from_the_course(): void
    {
        $known = $this->studentWithFullCourseName('legacy@my.cspc.edu.ph', 'Bachelor of Science in Computer Science');
        $knownRow = StudentSchoolYear::create(['student_id' => $known->student_id, 'school_year' => '2026-2027']);

        $unknown = $this->studentWithFullCourseName('blank@my.cspc.edu.ph', '');
        $unknownRow = StudentSchoolYear::create(['student_id' => $unknown->student_id, 'school_year' => '2026-2027']);

        // Both rows exist as legacy rows did: no program.
        DB::table('student_s_y')->whereIn('s_y_id', [$knownRow->s_y_id, $unknownRow->s_y_id])->update(['program' => null]);

        $fixed = StudentSchoolYear::backfillPrograms();

        $this->assertSame(1, $fixed);
        $this->assertSame('BSCS', $knownRow->fresh()->program);
        $this->assertNull($unknownRow->fresh()->program);
    }

    /** The path Grom's teams came through: building a team from a recruitment
     * post stamps the enrolment row with the program, like every other writer. */
    public function test_building_a_team_stamps_the_enrolment_rows_program(): void
    {
        (new StudentRoleSeeder)->run();

        $student = $this->studentWithFullCourseName('builder@my.cspc.edu.ph', 'Bachelor of Science in Information Technology');
        $this->actingAs($student);

        $response = $this->post('/recruitment', [
            'recruitment_goal' => 'build_a_team',
            'team_name' => 'Stamped',
            'looking_for' => 'Need members',
            'tags' => json_encode(['QA Tester/Documentation Writer']),
        ]);

        $response->assertRedirect(route('student.team'));

        $row = StudentSchoolYear::where('student_id', $student->student_id)->first();
        $this->assertNotNull($row?->team_id);
        $this->assertSame('BSIT', $row->program);
    }
}
