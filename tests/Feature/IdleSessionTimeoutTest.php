<?php

namespace Tests\Feature;

use App\Http\Middleware\EndIdleSession;
use App\Models\CapstoneExpert;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Without anyone using CapsTrack - thirty days by default, ten minutes here -
 * a student or an expert is signed out on every portal, and the sign-in page
 * says why.
 */
class IdleSessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml turns it off for every other test.
        config(['session.idle_timeout' => 10]);
    }

    private function student(): Student
    {
        return Student::factory()->withCompleteProfile()->withTourCompleted()->create();
    }

    private function expert(): CapstoneExpert
    {
        return CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'tour_completed_at' => now()]);
    }

    public function test_ten_idle_minutes_sign_a_student_out_and_the_sign_in_page_says_why(): void
    {
        $student = $this->student();
        $this->actingAs($student, 'web')->get(route('student.dashboard'))->assertOk();

        $this->travel(10)->minutes();

        $this->get(route('student.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('session_expired', true);
        $this->assertGuest('web');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Session expired.')
            ->assertSee('You were signed out after 10 minutes of inactivity.');
    }

    public function test_using_the_portal_keeps_the_session(): void
    {
        $student = $this->student();
        $this->actingAs($student, 'web')->get(route('student.dashboard'))->assertOk();

        $this->travel(9)->minutes();
        $this->get(route('student.progress'))->assertOk();
        $this->travel(9)->minutes();

        // Eighteen minutes in, but never ten of them idle.
        $this->get(route('student.dashboard'))->assertOk();
        $this->assertAuthenticatedAs($student, 'web');
    }

    public function test_the_pages_own_background_checks_do_not_count_as_activity(): void
    {
        $student = $this->student();
        $this->actingAs($student, 'web')->get(route('student.dashboard'))->assertOk();

        $this->travel(5)->minutes();
        $this->getJson(route('notifications.index'), ['X-Idle-Passive' => '1'])->assertOk();
        $this->travel(5)->minutes();

        $this->get(route('student.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_a_request_from_a_page_script_after_the_timeout_gets_json(): void
    {
        $this->actingAs($this->student(), 'web')->get(route('student.dashboard'))->assertOk();

        $this->travel(11)->minutes();

        $this->getJson(route('notifications.index'))
            ->assertUnauthorized()
            ->assertJson(['status' => 'session_expired', 'redirect' => route('login')]);
    }

    public function test_an_expert_is_signed_out_the_same_way(): void
    {
        $expert = $this->expert();
        $this->actingAs($expert, 'expert')->get(route('instructor.dashboard'))->assertOk();

        $this->travel(10)->minutes();

        $this->get(route('instructor.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest('expert');
    }

    public function test_activity_reported_by_the_page_keeps_the_session_going(): void
    {
        $student = $this->student();
        $this->actingAs($student, 'web')->get(route('student.dashboard'))->assertOk();

        $this->travel(9)->minutes();
        $this->postJson(route('session.keep-alive'))->assertOk()->assertJson(['status' => 'active', 'idle_timeout' => 10]);
        $this->travel(9)->minutes();

        $this->get(route('student.dashboard'))->assertOk();
    }

    public function test_the_idle_time_running_out_signs_them_out(): void
    {
        $this->actingAs($this->student(), 'web')->get(route('student.dashboard'))->assertOk();

        $this->get(route('session.expired'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('session_expired', true);
        $this->assertGuest('web');

        $this->postJson(route('session.keep-alive'))->assertUnauthorized();
    }

    public function test_every_portal_page_carries_the_idle_timer(): void
    {
        $this->actingAs($this->student(), 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('id="idle-logout"', false)
            ->assertSee('data-minutes="10"', false)
            ->assertSee('data-expired="'.route('session.expired').'"', false);

        $expert = $this->expert();
        foreach (['instructor.dashboard'] as $page) {
            $this->actingAs($expert, 'expert')->get(route($page))
                ->assertOk()
                ->assertSee('id="idle-logout"', false)
                ->assertSee('data-expired="'.route('session.expired').'"', false);
        }

        $this->app['auth']->forgetGuards();
        $this->get(route('login'))->assertDontSee('id="idle-logout"', false);
    }

    /**
     * A modal pop-up (uploading a file, its preview) makes the rest of the page
     * inert, so the question is asked inside it - otherwise it would show
     * behind the pop-up where "Stay signed in" cannot be pressed.
     */
    /**
     * There used to be a question to answer, and it had to be moved inside an
     * open pop-up to be answerable. With no question there is nothing to
     * move: an open pop-up neither hides the sign-out nor holds it off.
     */
    public function test_an_open_pop_up_does_not_hold_off_the_sign_out(): void
    {
        $this->actingAs($this->student(), 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('host.appendChild(box)', false)
            ->assertDontSee("d.matches(':modal')", false)
            ->assertSee('if (now - Math.max(last, shared()) >= LIMIT) { end(); return; }', false);
    }

    public function test_a_timeout_of_zero_turns_it_off(): void
    {
        config(['session.idle_timeout' => 0]);
        $student = $this->student();
        $this->actingAs($student, 'web')->get(route('student.dashboard'))->assertOk()->assertDontSee('id="idle-logout"', false);

        $this->travel(60)->minutes();

        $this->get(route('student.dashboard'))->assertOk();
    }

    /**
     * Idle time ends the session on its own: no box asking whether they are
     * still there, and no countdown to watch.
     */
    public function test_there_is_no_warning_box_or_countdown(): void
    {
        $this->actingAs($this->student(), 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('Are you still there?')
            ->assertDontSee('Stay signed in')
            ->assertDontSee('id="idl-count"', false)
            ->assertDontSee('id="idl-stay"', false)
            // Straight to the sign-out once the time is up.
            ->assertSee('if (now - Math.max(last, shared()) >= LIMIT) { end(); return; }', false);
    }

    /**
     * Thirty days unless an install says otherwise, and the session cookie lasts as long, so
     * it is not the cookie that signs anyone out first.
     */
    public function test_the_idle_limit_is_thirty_days_by_default(): void
    {
        $config = file_get_contents(config_path('session.php'));

        $this->assertMatchesRegularExpression("/'idle_timeout' => \\(int\\) env\\('SESSION_IDLE_TIMEOUT', 43200\\)/", $config);
        $this->assertMatchesRegularExpression("/'lifetime' => \\(int\\) env\\('SESSION_LIFETIME', 43200\\)/", $config);
        $this->assertFalse((bool) config('session.expire_on_close'), 'closing the browser keeps the session');
    }

    /** Like Google: open it within thirty days and it stays signed in; leave it thirty days and it signs out. */
    public function test_thirty_days_without_opening_capstrack_signs_out_every_portal(): void
    {
        config(['session.idle_timeout' => 43200, 'session.lifetime' => 43200]);
        $student = $this->student();
        $this->actingAs($student, 'web')->get(route('student.dashboard'))->assertOk();

        // Back after 29 days: still signed in, and the thirty days start over.
        $this->travel(29)->days();
        $this->get(route('student.dashboard'))->assertOk();
        $this->travel(29)->days();
        $this->get(route('student.dashboard'))->assertOk();

        $this->travel(30)->days();
        $this->get(route('student.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->get(route('login'))->assertSee('You were signed out after 30 days of inactivity.');

        $expert = $this->expert();
        $this->actingAs($expert, 'expert')->get(route('instructor.dashboard'))->assertOk();
        $this->travel(30)->days();
        $this->get(route('instructor.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest('expert');
    }

    public function test_the_limit_reads_the_way_a_person_says_it(): void
    {
        $this->assertSame('30 days', EndIdleSession::limitForHumans(43200));
        $this->assertSame('1 day', EndIdleSession::limitForHumans(1440));
        $this->assertSame('2 hours', EndIdleSession::limitForHumans(120));
        $this->assertSame('10 minutes', EndIdleSession::limitForHumans(10));
        $this->assertSame('90 minutes', EndIdleSession::limitForHumans(90));
    }
}
