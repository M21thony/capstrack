<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One modal for the whole app: a small box in the middle to confirm or report,
 * a panel from the right for a form or a record, and a sheet from the bottom on
 * a phone. The shared confirm boxes wear it, so every page that asks before a
 * step looks the same.
 */
class ModalSystemTest extends TestCase
{
    use RefreshDatabase;

    private function partial(string $name): string
    {
        return file_get_contents(resource_path("views/partials/{$name}.blade.php"));
    }

    public function test_the_modal_has_its_three_shapes(): void
    {
        $modal = $this->partial('modal');

        $this->assertStringContainsString('@once', $modal);
        $this->assertStringContainsString('.cm-ask .cm-box{max-width:420px', $modal);
        $this->assertStringContainsString('.cm-panel .cm-box{max-width:480px;height:100%', $modal);
        // Pinned to the right edge: dialog.cm[open] centres, so the panel's rule must outrank it.
        $this->assertStringContainsString('dialog.cm-panel[open]{place-items:stretch end}', $modal);
        // On a phone both become a sheet as tall as what they hold.
        $this->assertMatchesRegularExpression('/@media \(max-width:768px\)\{.*max-height:92dvh;border-radius:20px 20px 0 0/s', $modal);
    }

    public function test_it_is_a_native_dialog_that_returns_focus_and_holds_the_page_still(): void
    {
        $modal = $this->partial('modal');

        // The top layer: above every hand-built overlay, whatever its z-index.
        $this->assertStringContainsString('dlg.showModal()', $modal);
        $this->assertStringContainsString('lastFocused.focus()', $modal);
        $this->assertStringContainsString("classList.add('cm-lock')", $modal);
        $this->assertStringContainsString('html.cm-lock,html.cm-lock body{overflow:hidden}', $modal);
        $this->assertStringContainsString('prefers-reduced-motion', $modal);
    }

    public function test_a_scrolling_body_keeps_its_text_clear_of_the_scrollbar_and_the_keyboard(): void
    {
        $modal = $this->partial('modal');

        // The CapsTrack handle sits 3px in and is 14px wide: text stops well before it.
        $this->assertStringContainsString('padding:20px 32px 20px 24px', $modal);
        // A field typed in on a phone moves above the keyboard.
        $this->assertStringContainsString("scrollIntoView({block: 'center'", $modal);
        $this->assertStringContainsString('.cm input,.cm select,.cm textarea{font-size:16px}', $modal);
    }

    public function test_the_shared_confirm_boxes_wear_the_modal(): void
    {
        $sheet = $this->partial('app-dialog');
        $this->assertStringContainsString("@include('partials.modal')", $sheet);
        $this->assertStringContainsString('<dialog class="cm cm-ask" id="ad-scrim"', $sheet);
        $this->assertStringContainsString('window.CapsModal.open(scrim', $sheet);
        $this->assertStringStartsWith('@once', trim(explode('--}}', $sheet, 2)[1]));

        $dialog = $this->partial('confirm-dialog');
        $this->assertStringContainsString("@include('partials.modal')", $dialog);
        $this->assertStringContainsString("d.className = 'cm cm-ask cd ' + tone", $dialog);
        $this->assertStringContainsString('window.CapsModal.open(d', $dialog);
    }

    public function test_the_scrollbar_follows_into_an_open_dialog(): void
    {
        $scrollbar = $this->partial('app-scrollbar');

        $this->assertStringContainsString("document.querySelectorAll('dialog[open]')", $scrollbar);
        $this->assertStringContainsString('home.appendChild(layer)', $scrollbar);
        $this->assertStringContainsString("document.addEventListener('capsmodal:change', rehome)", $scrollbar);
        // The page underneath an open dialog gets no handle.
        $this->assertStringContainsString('!layer.parentNode.contains(el)', $scrollbar);
    }

    public function test_a_page_that_loads_the_dialog_twice_draws_it_once(): void
    {
        // The documents page includes it, and so does the sidebar it sits in.
        $student = Student::create([
            'email' => 'mark@my.cspc.edu.ph', 'f_name' => 'Mark', 'l_name' => 'Balilla',
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);
        $team = Team::create(['team_name' => 'Team Lumina', 'leader_id' => $student->student_id]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        $html = $this->actingAs($student, 'web')->get(route('student.documents'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'id="ad-scrim"'));
        $this->assertSame(1, substr_count($html, 'window.CapsModal = {open: open, close: close}'));
    }
}
