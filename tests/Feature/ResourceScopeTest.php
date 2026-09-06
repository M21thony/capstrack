<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Resources are uploaded by a CaPSA and read by students.
 *
 * A CaPSA sees what they uploaded and nobody else's. A student sees the ones
 * for their own program plus the ones marked for every program - the filter
 * used to be skipped whenever the student's program could not be read, which
 * showed them every program's material instead.
 */
class ResourceScopeTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(array $sections, bool $isAdmin = false): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true,
            'is_admin' => $isAdmin,
            'sections' => $sections,
        ]);

        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'capstone_adviser'])->role_id);

        return $expert;
    }

    private function resource(string $title, ?string $for, ?int $by, array $sections = []): Resource
    {
        $resource = Resource::create([
            'resource_type' => 'template',
            'category' => 'Chapter 1',
            'resource_for' => $for,
            'title' => $title,
            'file_name' => $title.'.pdf',
            'file_path' => 'resources/'.$title.'.pdf',
            'content_type' => 'file',
            'uploaded_by' => $by,
            'uploaded_at' => now(),
        ]);

        foreach ($sections as $section) {
            $resource->sections()->create(['section' => $section]);
        }

        return $resource->load('sections');
    }

    private function student(?string $course, ?string $program = null): Student
    {
        $student = Student::create([
            'email' => strtolower($course ?? 'none').rand(1000, 9999).'@my.cspc.edu.ph',
            'f_name' => 'A', 'l_name' => 'Student',
            'course' => $course,
            'year_section' => '4 · B',
        ]);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'school_year' => '2026-2027',
            'program' => $program,
        ]);

        return $student;
    }

    public function test_a_capsa_sees_only_what_they_uploaded(): void
    {
        $mine = $this->capsa(['BSIT-4A']);
        $theirs = $this->capsa(['BSIS-4A']);

        $this->resource('Mine', 'BSIT', $mine->expert_id);
        $this->resource('Theirs', 'BSIS', $theirs->expert_id);

        $response = $this->actingAs($mine, 'expert')->get('/capsa/resources');

        $response->assertStatus(200);
        $response->assertSee('Mine');
        $response->assertDontSee('Theirs');
    }

    /** Even one marked for every program, if another CaPSA put it there. */
    public function test_an_all_programs_upload_by_someone_else_is_still_theirs(): void
    {
        $mine = $this->capsa(['BSIT-4A']);
        $theirs = $this->capsa(['BSIS-4A']);

        $this->resource('Everyones But Not Mine', null, $theirs->expert_id);

        $this->actingAs($mine, 'expert')->get('/capsa/resources')
            ->assertDontSee('Everyones But Not Mine');
    }

    public function test_the_admin_sees_all_of_them(): void
    {
        $admin = $this->capsa([], true);
        $capsa = $this->capsa(['BSIT-4B']);

        $this->resource('Somebody Elses', 'BSIT', $capsa->expert_id);

        $this->actingAs($admin, 'expert')->get('/capsa/resources')
            ->assertSee('Somebody Elses');
    }

    /** The case that is live today: no program on the enrolment row. */
    public function test_a_student_without_a_program_on_their_row_still_gets_their_course(): void
    {
        $capsa = $this->capsa(['BSIT-4B']);

        $this->resource('For BSIT', 'BSIT', $capsa->expert_id);
        $this->resource('For BSIS', 'BSIS', $capsa->expert_id);
        $this->resource('For Everyone', null, $capsa->expert_id);

        // program column null, exactly as every row in the real database is.
        $student = $this->student('BSIT');

        $response = $this->actingAs($student, 'web')->get('/student/resources');

        $response->assertStatus(200);
        $response->assertSee('For BSIT');
        $response->assertSee('For Everyone');
        $response->assertDontSee('For BSIS');
    }

    /**
     * A student we cannot place has no CaPSA covering them, so they get
     * nothing - deliberately, rather than everything. An empty scope read as
     * "no filter" is how this page came to show everybody everything.
     */
    public function test_a_student_we_cannot_place_sees_nothing_rather_than_everything(): void
    {
        $capsa = $this->capsa(['BSIT-4B']);

        $this->resource('For BSIT', 'BSIT', $capsa->expert_id);
        $this->resource('For Everyone', null, $capsa->expert_id);

        $student = $this->student(null);

        $response = $this->actingAs($student, 'web')->get('/student/resources');

        $response->assertDontSee('For Everyone');
        $response->assertDontSee('For BSIT');
    }

    /** Another CaPSA's upload is not theirs to read, program or not. */
    public function test_a_student_does_not_see_another_capsas_uploads(): void
    {
        $mine = $this->capsa(['BSIT-4B']);
        $theirs = $this->capsa(['BSCS-4B']);

        $this->resource('From My CaPSA', 'BSIT', $mine->expert_id);
        $this->resource('From Another CaPSA', 'BSIT', $theirs->expert_id);

        $response = $this->actingAs($this->student('BSIT'), 'web')->get('/student/resources');

        $response->assertSee('From My CaPSA');
        $response->assertDontSee('From Another CaPSA');
    }

    /** The program on the row still wins when it is there. */
    public function test_the_program_on_the_enrolment_row_is_used_when_present(): void
    {
        $capsa = $this->capsa(['BSIT-4B', 'BSIS-4B']);

        $this->resource('For BSIS', 'BSIS', $capsa->expert_id);
        $this->resource('For BSIT', 'BSIT', $capsa->expert_id);

        $student = $this->student('BSIT', 'BSIS');

        $response = $this->actingAs($student, 'web')->get('/student/resources');

        $response->assertSee('For BSIS');
        $response->assertDontSee('For BSIT');
    }

    /**
     * A resource can be aimed at one section. It stores the year and letter
     * only - the program is resource_for - so the two together are the label
     * and neither is written down twice.
     */
    public function test_a_resource_aimed_at_one_section_reaches_only_that_section(): void
    {
        $capsa = $this->capsa(['BSIT-4A', 'BSIT-4B']);

        $this->resource('For 4B Only', null, $capsa->expert_id, ['BSIT-4B']);
        $this->resource('For 4A Only', null, $capsa->expert_id, ['BSIT-4A']);
        $this->resource('For All BSIT', 'BSIT', $capsa->expert_id);

        // student() makes a 4 · B student, so their section is BSIT-4B.
        $response = $this->actingAs($this->student('BSIT'), 'web')->get('/student/resources');

        $response->assertSee('For 4B Only');
        $response->assertSee('For All BSIT');
        $response->assertDontSee('For 4A Only');
    }

    /** Several at once - the point of the picker. */
    public function test_a_resource_can_be_aimed_at_several_sections(): void
    {
        $capsa = $this->capsa(['BSIT-4A', 'BSIT-4B', 'BSIT-3A']);

        $this->resource('For 4A and 4B', null, $capsa->expert_id, ['BSIT-4A', 'BSIT-4B']);
        $this->resource('For 3A only', null, $capsa->expert_id, ['BSIT-3A']);

        $response = $this->actingAs($this->student('BSIT'), 'web')->get('/student/resources');

        $response->assertSee('For 4A and 4B');
        $response->assertDontSee('For 3A only');
    }

    /** Named sections carry the program, so the program column stays empty. */
    public function test_targeting_sections_records_them_and_leaves_the_program_alone(): void
    {
        $capsa = $this->capsa(['BSIT-4A', 'BSIT-4B']);
        $resource = $this->resource('Targeted', null, $capsa->expert_id, ['BSIT-4A', 'BSIT-4B']);

        $this->assertSame(['BSIT-4A', 'BSIT-4B'], $resource->targetSections());
        $this->assertNull($resource->resource_for);
        $this->assertSame([], $this->resource('Untargeted', 'BSIT', $capsa->expert_id)->targetSections());
    }

    /** A CaPSA's programs come from the sections they handle. */
    public function test_handled_programs_are_read_off_the_sections(): void
    {
        $capsa = $this->capsa(['BSIT-4A', 'BSIT-4B', 'BSIS-3A']);

        $this->assertSame(['BSIT', 'BSIS'], $capsa->handledPrograms());
        $this->assertSame([], $this->capsa([])->handledPrograms());
    }
}
