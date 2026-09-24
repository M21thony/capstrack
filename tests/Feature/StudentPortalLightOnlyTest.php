<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The student portal has no dark mode: no toggle in the sidebar, and a theme
 * saved in the browser from when it had one is not read back.
 */
class StudentPortalLightOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_pages_are_always_light_and_offer_no_dark_mode(): void
    {
        $team = Team::create(['team_name' => 'Team Alpha', 'project_title' => 'Alpha', 'team_status' => 'Active']);
        $student = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
        ]);
        StudentSchoolYear::create([
            'student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => 'BSIT',
        ]);

        foreach (['student.dashboard', 'student.progress'] as $page) {
            $this->actingAs($student)->get(route($page))
                ->assertOk()
                ->assertSee("setAttribute('data-theme','light')", false)
                ->assertDontSee("localStorage.getItem('theme')", false)
                ->assertDontSee('toggleTheme', false)
                ->assertDontSee('Dark Mode');
        }
    }
}
