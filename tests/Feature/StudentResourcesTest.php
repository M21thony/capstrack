<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\GradingRubric;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A student's Resources page: their CaPSA's files, and the blank rubric
 * their panel grades each defense with, for their own program.
 */
class StudentResourcesTest extends TestCase
{
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    protected function setUp(): void
    {
        parent::setUp();

        // Dompdf outgrows PHP's default 128M, and a test that runs out of
        // memory dies without a word.
        ini_set('memory_limit', '512M');

        $this->capsa = $this->capsa(['BSIT-4B']);
    }

    private function capsa(array $sections): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => $sections]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'capstone_adviser'])->role_id);

        return $expert;
    }

    private function student(string $course = 'BSIT'): Student
    {
        $student = Student::create([
            'email' => strtolower($course).rand(1000, 9999).'@my.cspc.edu.ph',
            'f_name' => 'A', 'l_name' => 'Student',
            'course' => $course,
            'year_section' => '4 · B',
        ]);

        StudentSchoolYear::create(['student_id' => $student->student_id, 'school_year' => '2026-2027']);

        return $student;
    }

    private function rubric(string $name, string $stage, ?string $program = null, array $extra = []): GradingRubric
    {
        $rubric = GradingRubric::create(array_merge([
            'rubric_name' => $name, 'rubric_type' => $stage, 'program' => $program,
            'total_points' => 100, 'is_active' => true, 'is_archived' => false,
        ], $extra));

        $rubric->criteria()->create([
            'criterion_name' => 'Presentation', 'description' => 'Clear and organised delivery.', 'category' => 'Delivery',
            'category_max_points' => 100, 'max_points' => 100, 'sort_order' => 1,
            'score_levels' => json_encode([['score' => 100, 'label' => 'Excellent'], ['score' => 50, 'label' => 'Fair']]),
        ]);

        return $rubric;
    }

    private function file(string $title, ?int $by = null, array $extra = []): Resource
    {
        return Resource::create(array_merge([
            'resource_type' => 'template', 'category' => 'ch1-3', 'resource_for' => 'BSIT',
            'title' => $title, 'file_name' => $title.'.docx', 'file_type' => 'docx', 'file_size' => 114000,
            'content_type' => 'file', 'is_downloadable' => true, 'drive_file_id' => 'drive-'.md5($title),
            'uploaded_by' => $by ?? $this->capsa->expert_id, 'uploaded_at' => now()->subDays(3),
        ], $extra));
    }

    public function test_the_page_lists_the_files_and_the_rubrics_together(): void
    {
        $this->file('Chapter 1-3 Template');
        $this->rubric('Title Defense Rubric', 'Title Defense');

        $this->actingAs($this->student(), 'web')->get(route('student.resources'))
            ->assertOk()
            ->assertSee('Chapter 1-3 Template')
            ->assertSee('Title Defense Rubric')
            ->assertSee('Grading rubric · For all programs · 100 pts')
            ->assertSee(route('student.resources.rubric', 'title-defense'), false)
            ->assertSee('<option value="rubrics">Rubrics</option>', false)
            ->assertSee('<option value="ch1-3">Chapters 1–3</option>', false);
    }

    public function test_the_rubric_for_the_students_program_wins_over_the_one_for_everyone(): void
    {
        $this->rubric('Title Defense Rubric', 'Title Defense');
        $this->rubric('Title Defense Rubric (IT)', 'Title Defense', 'BSIT');

        $this->actingAs($this->student(), 'web')->get(route('student.resources'))
            ->assertSee('Grading rubric · BSIT version · 100 pts')
            ->assertDontSee('Grading rubric · For all programs');
    }

    public function test_another_programs_rubric_is_not_offered(): void
    {
        $this->rubric('Pre Oral Defense Rubric (CS)', 'Pre Oral Defense', 'BSCS');

        $student = $this->student();

        $this->actingAs($student, 'web')->get(route('student.resources'))
            ->assertDontSee('Pre Oral Defense Rubric');

        $this->actingAs($student, 'web')->get(route('student.resources.rubric', 'pre-oral-defense'))
            ->assertNotFound();
    }

    public function test_an_archived_or_inactive_rubric_is_not_offered(): void
    {
        $this->rubric('Old Title Rubric', 'Title Defense', null, ['is_archived' => true]);
        $this->rubric('Draft Final Rubric', 'Final Oral Defense', null, ['is_active' => false]);

        $this->actingAs($this->student(), 'web')->get(route('student.resources'))
            ->assertDontSee('Old Title Rubric')
            ->assertDontSee('Final Oral Defense Rubric');
    }

    public function test_the_blank_rubric_downloads_as_a_pdf(): void
    {
        $this->rubric('Title Defense Rubric', 'Title Defense');

        $response = $this->actingAs($this->student(), 'web')->get(route('student.resources.rubric', 'title-defense'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="Title Defense Rubric.pdf"', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_the_blank_rubric_can_be_viewed_in_the_page(): void
    {
        $this->rubric('Title Defense Rubric', 'Title Defense');

        $response = $this->actingAs($this->student(), 'web')->get(route('student.resources.rubric', ['stage' => 'title-defense', 'view' => 1]));

        $response->assertOk();
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
    }

    public function test_a_stage_without_a_rubric_is_not_found(): void
    {
        $this->actingAs($this->student(), 'web')->get(route('student.resources.rubric', 'final-oral-defense'))
            ->assertNotFound();
    }

    public function test_a_file_downloads_from_drive_and_is_counted(): void
    {
        $file = $this->file('Chapter 1-3 Template');

        $this->actingAs($this->student(), 'web')->get(route('student.resources.download', $file))
            ->assertRedirect('https://drive.google.com/uc?export=download&id='.$file->drive_file_id);

        $this->assertSame(1, (int) $file->fresh()->downloads);
    }

    public function test_a_file_from_another_capsa_cannot_be_downloaded(): void
    {
        $theirs = $this->capsa(['BSCS-4B']);
        $file = $this->file('Not Yours', $theirs->expert_id);

        $this->actingAs($this->student(), 'web')->get(route('student.resources.download', $file))
            ->assertNotFound();

        $this->assertSame(0, (int) $file->fresh()->downloads);
    }

    public function test_a_file_that_is_not_downloadable_is_not_handed_over(): void
    {
        $file = $this->file('A Google Doc', null, ['is_downloadable' => false]);

        $this->actingAs($this->student(), 'web')->get(route('student.resources.download', $file))
            ->assertNotFound();
    }

    public function test_a_student_with_nothing_posted_is_told_so(): void
    {
        $this->actingAs($this->student(), 'web')->get(route('student.resources'))
            ->assertOk()
            ->assertSee('Nothing here yet');
    }
}
