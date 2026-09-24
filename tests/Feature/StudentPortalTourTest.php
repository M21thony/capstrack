<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A new account is walked through the portal once, and can ask for the walk
 * again from the sidebar whenever they like.
 */
class StudentPortalTourTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_account_is_shown_the_tour_on_its_own(): void
    {
        $student = Student::factory()->withCompleteProfile()->create(['f_name' => 'Juan']);

        $this->actingAs($student, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('id="portal-tour"', false)
            ->assertSee('data-auto-open="1"', false)
            ->assertSee('Welcome to CapsTrack, Juan!')
            ->assertSeeInOrder(['Start here: form your team', 'Choose your experts', 'Submit your title', 'Updates land here', 'You are all set!']);
    }

    public function test_it_is_there_on_every_page_not_only_the_dashboard(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();

        foreach (['student.team', 'student.experts', 'student.resources', 'student.archive'] as $page) {
            $this->actingAs($student, 'web')->get(route($page))
                ->assertOk()
                ->assertSee('data-auto-open="1"', false);
        }
    }

    public function test_finishing_or_skipping_it_means_it_does_not_open_again(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();

        $this->actingAs($student, 'web')->putJson(route('student.tour.update'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNotNull($student->fresh()->tutorial_completed_at);
        $this->actingAs($student, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('data-auto-open="0"', false);
    }

    public function test_replaying_it_keeps_when_they_first_finished(): void
    {
        $student = Student::factory()->withCompleteProfile()->withTourCompleted()->create();
        $firstFinished = $student->tutorial_completed_at->toDateTimeString();

        $this->actingAs($student, 'web')->putJson(route('student.tour.update'))->assertOk();

        $this->assertSame($firstFinished, $student->fresh()->tutorial_completed_at->toDateTimeString());
    }

    public function test_an_account_that_has_seen_it_can_still_open_it_from_the_sidebar(): void
    {
        $student = Student::factory()->withCompleteProfile()->withTourCompleted()->create();

        $this->actingAs($student, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('data-auto-open="0"', false)
            ->assertSee('onclick="openPortalTour()"', false)
            ->assertSee('Portal Tour');
    }

    public function test_a_fresh_google_sign_in_gets_it_before_the_profile_is_filled_in(): void
    {
        // Only what Google hands over: a name and an email, no student number.
        $student = Student::factory()->create(['f_name' => 'Maria', 'l_name' => 'Santos']);

        $this->assertFalse($student->hasCompletedProfile());
        $this->assertTrue($student->needsPortalTour());

        $this->actingAs($student, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('data-auto-open="1"', false)
            ->assertSee('Welcome to CapsTrack, Maria!');
    }

    public function test_a_guest_cannot_mark_it_done(): void
    {
        $this->putJson(route('student.tour.update'))->assertUnauthorized();
    }

    public function test_the_last_step_points_at_the_first_thing_left_to_do(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();

        $this->actingAs($student, 'web')->get(route('student.dashboard'))
            ->assertSee('Your first step', false)
            ->assertSee('href="'.route('student.team').'" class="gt-next-up"', false);
    }

    /**
     * Every step outlines something that is actually on the page: a sidebar
     * link carrying its data-tour-target, or an element with that id. A
     * renamed link would otherwise leave its step silently skipped.
     */
    public function test_every_step_points_at_something_on_the_page(): void
    {
        $steps = config('student-tour.steps');
        $student = Student::factory()->withCompleteProfile()->create();
        $page = $this->actingAs($student, 'web')->get(route('student.dashboard'))->assertOk();

        $this->assertNotEmpty($steps);
        foreach ($steps as $step) {
            $this->assertNotEmpty($step['label']);
            $this->assertNotEmpty($step['body']);

            foreach ($step['target'] as $target) {
                if (str_starts_with($target, '#')) {
                    $page->assertSee('id="'.substr($target, 1).'"', false);

                    continue;
                }

                $page->assertSee('data-tour-target="'.$target.'"', false);
                if (str_starts_with($target, 'student.')) {
                    $this->assertTrue(Route::has($target), "Tour step '{$step['key']}' points at missing route {$target}.");
                }
            }
        }
    }

    /** The welcome and the send-off point at nothing; everything between does. */
    /**
     * The tour used to reopen on every single page whenever the server would
     * not record it as finished, with nothing shown to say anything failed.
     * It now also remembers in the browser, and only a bad answer is noticed.
     */
    public function test_a_refused_save_still_stops_the_tour_for_this_visit(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();

        $html = $this->actingAs($student, 'web')->get(route('student.dashboard'))
            ->assertOk()->getContent();

        // Kept per portal, so finishing one portal's tour does not hide another's.
        $this->assertStringContainsString("sessionStorage.setItem('gt-done:' + tourKey, '1')", $html);
        $this->assertStringContainsString('data-tour-key="student"', $html);
        $this->assertStringContainsString('&& !markedDone()', $html);
        $this->assertStringContainsString('if (!r.ok)', $html);
    }

    public function test_it_opens_and_closes_on_a_card_and_points_at_things_in_between(): void
    {
        $steps = config('student-tour.steps');

        $this->assertSame([], $steps[0]['target']);
        $this->assertSame([], end($steps)['target']);
        foreach (array_slice($steps, 1, -1) as $step) {
            $this->assertNotEmpty($step['target'], "Tour step '{$step['key']}' points at nothing.");
        }
    }
}
