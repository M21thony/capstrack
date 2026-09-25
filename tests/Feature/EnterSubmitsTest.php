<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Enter - the Done or Go key on a phone's keyboard - presses the button
 * that finishes what is being filled in, on every portal's pages. What it
 * presses, and what it never does, is tested in tests/e2e/enter-submits.
 */
class EnterSubmitsTest extends TestCase
{
    use RefreshDatabase;

    private const LISTENER = "if (event.key !== 'Enter' || event.defaultPrevented || event.isComposing || event.keyCode === 229) return;";

    public function test_every_portal_has_enter_press_the_finishing_button(): void
    {
        $team = Team::create(['team_name' => 'Enter Team', 'project_title' => 'Enter', 'team_status' => 'Active']);
        $student = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
        ]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => 'BSIT']);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee(self::LISTENER, false);

        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);
        $this->actingAs($expert, 'expert')->get(route('instructor.dashboard'))
            ->assertOk()
            ->assertSee(self::LISTENER, false);

        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-3A']]));
        $this->actingAs($capsa, 'expert')->get(route('capsa.sections'))
            ->assertOk()
            ->assertSee(self::LISTENER, false)
            ->assertSee('onclick="saveNewStudent()">Add student</button>', false);

        $admin = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);
        $this->actingAs($admin, 'expert')->get(route('admin.students'))
            ->assertOk()
            ->assertSee(self::LISTENER, false);
    }

    public function test_the_listener_is_on_a_page_once(): void
    {
        $admin = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);

        $html = $this->actingAs($admin, 'expert')->get(route('admin.students'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, self::LISTENER));
    }

    public function test_it_never_presses_cancel_or_a_delete_and_labels_the_phone_key(): void
    {
        $source = File::get(resource_path('views/partials/enter-submits.blade.php'));

        $this->assertStringContainsString('var NEVER = /\b(cancel|close|back|clear|reset|delete|remove|archive|reject|decline|disband|leave|revoke|kick|discard|sign ?out|log ?out)\b/i;', $source);
        $this->assertStringContainsString("field.setAttribute('enterkeyhint', hint);", $source);
    }

    /** The milestone editor's name fields save on Enter themselves. */
    public function test_fields_that_handle_enter_themselves_keep_it(): void
    {
        $editor = File::get(resource_path('js/milestones/editor.js'));

        $this->assertSame(2, substr_count($editor, "if (e.key !== 'Enter') return;\n      e.preventDefault();"));
    }
}
