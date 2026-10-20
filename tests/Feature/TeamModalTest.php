<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The Team page's modals share the system's pattern: a dark blurred overlay,
 * a white card and a round close button - one component, so a new modal
 * cannot drift from the rest.
 */
class TeamModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_modal_renders_the_system_pattern_with_a_close_button(): void
    {
        $html = Blade::render('<x-team-modal id="demo" title-id="demo-t" width="440" on-close="closeDemo()"><h2 id="demo-t">Hi</h2></x-team-modal>');

        $this->assertStringContainsString('class="modal-overlay" id="demo"', $html);
        $this->assertStringContainsString('role="dialog" aria-modal="true" aria-labelledby="demo-t"', $html);
        $this->assertStringContainsString('class="card modal-card team-modal"', $html);
        $this->assertStringContainsString('max-width: 440px', $html);
        $this->assertStringContainsString('class="pm-close-btn team-modal-close" onclick="closeDemo()" aria-label="Close"', $html);
        $this->assertStringContainsString('<h2 id="demo-t">Hi</h2>', $html);
    }

    /** Where a choice must be made, there is nothing to close it with. */
    public function test_a_modal_that_must_be_answered_has_no_close_button_or_backdrop_close(): void
    {
        $html = Blade::render('<x-team-modal id="must" title-id="must-t" :closable="false"><h2 id="must-t">Pick</h2></x-team-modal>');

        $this->assertStringNotContainsString('pm-close-btn', $html);
        $this->assertStringNotContainsString('onclick=', $html);
        $this->assertStringContainsString('data-no-escape', $html);
    }

    /** The two pop-ups that drew their own darker overlay now use the system's. */
    public function test_the_confirm_and_expert_notice_use_the_system_overlay(): void
    {
        $student = Student::create(['email' => 'a@my.cspc.edu.ph', 'f_name' => 'A', 'l_name' => 'B', 'course' => 'BSIT', 'year_section' => '3rd · A']);
        StudentSchoolYear::create(['student_id' => $student->student_id]);

        $html = $this->actingAs($student)->get(route('student.team'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="customConfirmModal" style="[^"]*background:rgba\(24,25,31,0\.9\); backdrop-filter:blur\(4px\)/', $html);
    }
}
