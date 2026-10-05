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
 * Moving between pages is smooth: no skeleton standing in front of content
 * that is already there, no fade that reads as a flash, the next page
 * loading as the link is pointed at, and taps that land at once.
 */
class SmoothNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function studentPage(): string
    {
        $team = Team::create(['team_name' => 'Smooth Team', 'project_title' => 'Smooth', 'team_status' => 'Active']);
        $student = Student::factory()->withCompleteProfile()->create();
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => 'BSIT']);

        return $this->actingAs($student)->get(route('student.dashboard'))->assertOk()->getContent();
    }

    /** The page-to-page cross-fade read as the screen flashing on every click, so there is none. */
    public function test_pages_do_not_fade_and_the_next_one_loads_early(): void
    {
        $html = $this->studentPage();

        $this->assertStringNotContainsString('@view-transition', $html);
        $this->assertStringNotContainsString('::view-transition', $html);
        $this->assertSame(1, substr_count($html, '<script type="speculationrules" nonce='), 'once per page');

        preg_match('/<script type="speculationrules"[^>]*>(.*?)<\/script>/s', $html, $rules);
        $json = json_decode($rules[1], true);
        $this->assertNotNull($json, 'the speculation rules are valid JSON');
        $this->assertSame('moderate', $json['prefetch'][0]['eagerness']);
        $this->assertSame('.sidebar a[href], .mtab a[href]', $json['prefetch'][0]['where']['and'][0]['selector_matches']);
        $this->assertStringContainsString('touch-action: manipulation', $html);

        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);
        $this->actingAs($expert, 'expert')->get(route('instructor.dashboard'))->assertOk()
            ->assertSee('<script type="speculationrules" nonce=', false)
            ->assertDontSee('@view-transition', false);
    }

    /** Nothing is given a view-transition-name: it would lift the top bar's notifications under sticky headings. */
    public function test_no_element_is_lifted_onto_a_layer_of_its_own(): void
    {
        preg_match('/<style[^>]*>(.*?)<\/style>/s', File::get(resource_path('views/partials/smooth-navigation.blade.php')), $style);

        $this->assertStringNotContainsString('view-transition-name', $style[1]);
    }

    /**
     * The skeleton stood in front of server-drawn content for 400-600ms on a
     * first visit, and on the expert pages then jumped the page up as it left.
     */
    public function test_the_content_shows_at_once_instead_of_behind_a_skeleton(): void
    {
        $html = $this->studentPage();

        $this->assertStringContainsString("\">document.documentElement.className += ' sk-seen';</script>", $html);
        $this->assertStringNotContainsString('sessionStorage.getItem(key)', File::get(resource_path('views/partials/skeleton-head.blade.php')));
        $this->assertStringContainsString('html.sk-seen #skeleton-content { display: none !important; }', $html);
        // No fade-in either: on every page it read as a flash.
        $this->assertStringNotContainsString('sk-page-in', $html);
    }

    /** The schedule's month grid keeps its room until it is drawn, so the page below it holds still. */
    public function test_the_schedule_grid_keeps_its_room_until_drawn(): void
    {
        $this->assertStringContainsString('#cal-grid{min-height:401px}', File::get(public_path('css/schedule.css')));
    }
}
