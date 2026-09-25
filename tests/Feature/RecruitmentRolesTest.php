<?php

namespace Tests\Feature;

use App\Models\Recruitment;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A recruitment post's roles are always a list. Some rows held the list
 * JSON-encoded twice; read back as a string, it made the Team page's
 * implode() fail - a 500 for everyone in the section who could see the post.
 */
class RecruitmentRolesTest extends TestCase
{
    use RefreshDatabase;

    private const DOUBLED = '"[\"Frontend Developer\",\"UI\\\\/UX Designer\"]"';

    private function classmate(string $email, string $number): Student
    {
        $student = Student::create([
            'email' => $email, 'f_name' => 'Class', 'l_name' => 'Mate', 'student_number' => $number,
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
        ]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => null, 'school_year' => 2026, 'program' => 'BSIT']);

        return $student;
    }

    private function doubledPost(Student $by): int
    {
        return DB::table('recruitment')->insertGetId([
            'student_id' => $by->student_id, 'recruitment_goal' => 'find_a_team',
            'roles' => self::DOUBLED, 'introduction' => 'Doubled', 'created_at' => now(), 'updated_at' => now(),
        ], 'recruitment_id');
    }

    public function test_roles_stored_twice_encoded_read_as_a_list(): void
    {
        $id = $this->doubledPost($this->classmate('poster@my.cspc.edu.ph', '2026-0701'));

        $this->assertSame(['Frontend Developer', 'UI/UX Designer'], Recruitment::find($id)->roles);
    }

    public function test_the_team_page_opens_with_a_twice_encoded_post_in_the_feed(): void
    {
        $this->doubledPost($this->classmate('poster@my.cspc.edu.ph', '2026-0701'));

        $this->actingAs($this->classmate('reader@my.cspc.edu.ph', '2026-0702'))
            ->get(route('student.team'))
            ->assertOk()
            ->assertSee('Frontend Developer, UI/UX Designer');
    }

    public function test_roles_are_written_once_and_read_back_as_given(): void
    {
        $post = Recruitment::create([
            'student_id' => $this->classmate('poster@my.cspc.edu.ph', '2026-0701')->student_id,
            'recruitment_goal' => 'find_a_team', 'roles' => ['Programmer'],
        ]);

        $this->assertSame('["Programmer"]', DB::table('recruitment')->where('recruitment_id', $post->recruitment_id)->value('roles'));
        $this->assertSame(['Programmer'], $post->fresh()->roles);
    }

    public function test_the_migration_rewrites_twice_encoded_rows_once(): void
    {
        $id = $this->doubledPost($this->classmate('poster@my.cspc.edu.ph', '2026-0701'));

        (require database_path('migrations/2026_09_25_174325_repair_double_encoded_recruitment_roles.php'))->up();

        $this->assertSame(['Frontend Developer', 'UI/UX Designer'], json_decode(DB::table('recruitment')->where('recruitment_id', $id)->value('roles'), true));
    }
}
