<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * The notification panel and its popups are hidden by their own styles. Those
 * styles sat after the markup, so a browser that painted before reaching them
 * showed the panel full-size - giant icons and raw text - on a refresh, now
 * and then. The styles come first now, on every portal.
 */
class NotificationPanelStylesComeFirstTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private function assertStyledBeforeDrawn(string $html, array $classes): void
    {
        foreach ($classes as $class) {
            $drawn = strpos($html, 'class="'.$class.'"');
            $styled = strpos($html, '.'.$class.'{');

            $this->assertNotFalse($drawn, "$class is on the page");
            $this->assertNotFalse($styled, "$class has its style");
            $this->assertLessThan($drawn, $styled, "$class is styled before it is drawn");
        }
    }

    public function test_the_student_panel_and_popups_are_styled_before_they_are_drawn(): void
    {
        [, $student] = $this->teamInSection('Bell');

        $html = $this->actingAs($student)->get(route('student.dashboard'))->assertOk()->getContent();

        $this->assertStyledBeforeDrawn($html, ['notif-dropdown', 'ld-overlay', 'nld-confirm-overlay']);
    }

    public function test_the_staff_panel_is_styled_before_it_is_drawn(): void
    {
        $capsa = $this->capsaFor();
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);

        foreach ([[$capsa, 'capsa.dashboard'], [$expert, 'instructor.dashboard']] as [$who, $page]) {
            $html = $this->actingAs($who, 'expert')->get(route($page))->assertOk()->getContent();

            // The bell's own block, which is what places and hides its panel.
            $this->assertLessThan(
                strpos($html, 'class="notif-dropdown"'),
                strpos($html, 'The panel hangs off the bell.'),
                "$page styles the bell's panel before drawing it",
            );
        }
    }
}
