<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The CapsTrack scrollbar on the student Team page.
 *
 * Team selection is a scrolling list inside a scrolling page. Pointing at it
 * used to wake a handle for both boxes, so two bars sat side by side; only
 * the innermost box under the pointer gets one now.
 */
class AppScrollbarTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        $team = Team::create(['team_name' => 'Scroll Team', 'project_title' => 'Scrolling', 'team_status' => 'Active']);
        $student = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
        ]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => 'BSIT']);

        return $student;
    }

    public function test_the_team_page_carries_the_capstrack_scrollbar_once(): void
    {
        $html = $this->actingAs($this->student())->get(route('student.team'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'window.__appScrollbar = true'));
        $this->assertStringContainsString('#sb-layer .sb-handle', $html);
    }

    public function test_only_the_innermost_scroll_box_under_the_pointer_gets_a_handle(): void
    {
        $partial = file_get_contents(resource_path('views/partials/app-scrollbar.blade.php'));

        $this->assertStringContainsString('var now = boxesAt(e.target).slice(0, 1);', $partial);
        $this->assertStringNotContainsString('var now = boxesAt(e.target);', $partial);
    }
}
