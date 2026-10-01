<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * After an action every portal shows the same Success / Error card - the
 * admin one - at the top center of the screen. Each page's own toast hands
 * off to it; the bell's notification pop-ups are a separate thing.
 */
class ActionToastTest extends TestCase
{
    /** Pages whose own toast now hands off to the shared card. */
    private const PAGES = [
        'views/admin/experts.blade.php',
        'views/admin/students.blade.php',
        'views/capsa/experts.blade.php',
        'views/capsa/partials/team-management/leave_requests.blade.php',
        'views/capsa/reports/table.blade.php',
        'views/capsa/resources.blade.php',
        'views/capsa/rubric_management.blade.php',
        'views/capsa/sections.blade.php',
        'views/capsa/team-management/_hub-script.blade.php',
        'views/instructor/assignments.blade.php',
        'views/instructor/schedule.blade.php',
        'views/student/archive.blade.php',
        'views/student/consultation.blade.php',
        'views/student/duplication.blade.php',
        'views/student/experts.blade.php',
        'views/student/resources.blade.php',
        'views/student/team.blade.php',
        'js/defenses/shared.js',
        'js/financial/shared.js',
        'js/milestones/common.js',
        'js/submission-drawer.js',
        'js/App.jsx',
    ];

    public function test_every_page_toast_hands_off_to_the_shared_card(): void
    {
        foreach (self::PAGES as $page) {
            $this->assertStringContainsString('window.ctToast', File::get(resource_path($page)), $page);
        }
    }

    public function test_every_portal_carries_the_card(): void
    {
        foreach ([
            'views/partials/notification-bell.blade.php',
            'views/student/partials/notification-dropdown.blade.php',
            'views/instructor/docreview-annotate.blade.php',
        ] as $host) {
            $this->assertStringContainsString("@include('partials.action-toast')", File::get(resource_path($host)), $host);
        }
    }

    public function test_the_card_renders_once_at_the_top_center(): void
    {
        $html = Blade::render("@include('partials.action-toast') @include('partials.action-toast')");

        $this->assertSame(1, substr_count($html, 'id="ct-action-toasts"'));
        $this->assertStringContainsString('popover="manual"', $html);
        $this->assertStringContainsString('inset: 18px 0 auto 0; margin: 0 auto;', $html);
        $this->assertStringContainsString("const titles = { success: 'Success', error: 'Error', info: 'Notice' };", $html);
    }

    public function test_messages_are_shown_as_text_not_markup(): void
    {
        $source = File::get(resource_path('views/partials/action-toast.blade.php'));

        $this->assertStringContainsString("card.querySelector('.ct-at-msg').textContent = message;", $source);
        $this->assertStringNotContainsString('+ message', $source);
    }

    public function test_error_messages_are_marked_as_errors(): void
    {
        $resources = File::get(resource_path('views/capsa/resources.blade.php'));
        $rubrics = File::get(resource_path('views/capsa/rubric_management.blade.php'));
        $defenses = File::get(resource_path('js/defenses/defense-scheduling.js'));

        $this->assertStringContainsString("showToast('Upload failed.', 'error');", $resources);
        $this->assertStringNotContainsString("showToast('?", $resources);
        $this->assertStringContainsString("toast('Something went wrong.', 'error');", $rubrics);
        $this->assertStringNotContainsString('toast(e.message);', $defenses);
    }

    public function test_an_error_card_says_why_the_request_failed(): void
    {
        $source = File::get(resource_path('views/partials/action-toast.blade.php'));

        $this->assertStringContainsString("419: 'Your session expired. Reload the page, then try again.'", $source);
        $this->assertStringContainsString("status >= 500 ? 'The server ran into a problem.", $source);
        $this->assertStringContainsString('You are offline. Check your internet connection', $source);
        $this->assertStringContainsString("textContent: 'Why: '", $source);
        // A server that answered was not a connection problem.
        $this->assertStringContainsString(".replace(/^Network error/i, 'Error')", $source);
    }

    public function test_confirm_buttons_are_short(): void
    {
        $long = ["Yes, it's complete", 'No, send it back', 'Send back with this note', 'Yes, approve', 'Yes, Remove', 'Yes, remove', 'No, keep it', 'Yes, submit', 'Yes, Leave', 'Yes, verify', 'Yes, cancel it', 'Yes, keep it', 'Yes, move'];

        foreach ([
            'js/milestones/tracker.js',
            'js/financial/capsa.js',
            'views/capsa/panel_assignment.blade.php',
            'views/capsa/sections.blade.php',
            'views/instructor/assignments.blade.php',
            'views/instructor/schedule.blade.php',
            'views/student/duplication.blade.php',
            'views/student/experts.blade.php',
            'views/student/team.blade.php',
        ] as $page) {
            $source = File::get(resource_path($page));
            foreach ($long as $label) {
                $this->assertStringNotContainsString($label, $source, $page);
            }
        }
    }
}
