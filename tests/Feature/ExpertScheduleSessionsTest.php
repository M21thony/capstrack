<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSchedule;
use App\Models\Student;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The expert's Schedule page: the side column lists cancel requests and the
 * sessions teams booked, each with what the team will bring, and every
 * pop-up is the CapsTrack modal. The menu counts the requests waiting.
 */
class ExpertScheduleSessionsTest extends TestCase
{
    use RefreshDatabase;

    private function expertWithRequest(): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['first_name' => 'Ana', 'last_name' => 'Adviser', 'approved_status' => true]);
        $slot = ConsultationSchedule::create([
            'expert_id' => $expert->expert_id, 'available_date' => 'Wednesday', 'start_time' => '09:00:00', 'end_time' => '09:30:00',
            'consultation_limit' => 1, 'is_available' => true, 'recur' => 'weekly', 'slot_type' => 'consult',
        ]);
        $student = Student::factory()->withCompleteProfile()->create();
        $team = Team::create(['team_name' => 'Team Lumina', 'leader_id' => $student->student_id]);
        ConsultationBooking::create([
            'student_id' => $student->student_id, 'team_id' => $team->team_id, 'schedule_id' => $slot->schedule_id,
            'session_date' => now()->next('Wednesday')->addWeek()->toDateString(), 'booking_status' => 'scheduled',
            'topics' => ['paper'], 'note' => 'The survey questions.',
            'cancellation_requested_at' => now(), 'cancellation_reason' => 'Midterms.', 'cancellation_requested_by' => 'Mark Reyes',
        ]);

        return $expert;
    }

    public function test_the_menu_counts_the_cancel_requests_waiting(): void
    {
        $html = $this->actingAs($this->expertWithRequest(), 'expert')->get(route('instructor.schedule'))->assertOk()->getContent();

        $this->assertStringContainsString('<span class="nav-badge red" title="1 cancel request">1</span>', $html);
    }

    public function test_a_phone_keyboard_resizes_the_page_instead_of_covering_it(): void
    {
        $html = $this->actingAs($this->expertWithRequest(), 'expert')->get(route('instructor.schedule'))->assertOk()->getContent();

        $this->assertStringContainsString('interactive-widget=resizes-content', $html);
    }

    public function test_the_side_column_lists_requests_and_sessions_with_what_the_team_brings(): void
    {
        $html = $this->actingAs($this->expertWithRequest(), 'expert')->get(route('instructor.schedule'))->assertOk()->getContent();

        $this->assertStringContainsString('id="sc-requests"', $html);
        $this->assertStringContainsString('reqs.slice(0, 2)', $html);
        $this->assertStringContainsString('list.slice(0, 4)', $html);
        $this->assertStringContainsString("<span>About ' + escHtml(b.topics)", $html);
        $this->assertStringContainsString('function openRequestReview(id)', $html);
        $this->assertStringContainsString("onclick=\"setSessionsTab('past')\"", $html);
        $this->assertStringNotContainsString('Next sessions', $html);
    }

    public function test_a_refused_cancellation_reads_as_rejected(): void
    {
        $html = $this->actingAs($this->expertWithRequest(), 'expert')->get(route('instructor.schedule'))->assertOk()->getContent();

        $this->assertStringContainsString("'Reject this cancellation?'", $html);
        $this->assertStringNotContainsString('Keep booking', $html);
        $this->assertStringNotContainsString('Keep this booking?', $html);
    }

    public function test_every_pop_up_is_the_capstrack_modal(): void
    {
        $html = $this->actingAs($this->expertWithRequest(), 'expert')->get(route('instructor.schedule'))->assertOk()->getContent();

        $this->assertStringContainsString('<dialog class="cm cm-ask" id="cancel-decision"', $html);
        $this->assertStringContainsString('<dialog class="cm cm-panel cm-wide" id="avail-overlay"', $html);
        $this->assertStringContainsString('<dialog class="cm cm-ask" id="scope-ask"', $html);
        $this->assertStringContainsString('window.CapsModal = {open: open, close: close}', $html);
        $this->assertStringNotContainsString('slot-modal-overlay', $html);
    }

    public function test_a_new_consultation_can_repeat_every_other_week_from_its_date(): void
    {
        $html = $this->actingAs($this->expertWithRequest(), 'expert')->get(route('instructor.schedule'))->assertOk()->getContent();

        $this->assertStringContainsString("onclick=\"setQuickRecur('biweekly')\">Every 2 weeks</button>", $html);
        $this->assertSame(2, substr_count($html, "specific_date:      recur === 'once' || recur === 'biweekly' ? selectedDate : null,"));
    }

    public function test_a_laptop_sees_the_whole_page_and_a_short_day_counts_what_it_hides(): void
    {
        $css = File::get(public_path('css/schedule.css'));
        $this->assertStringContainsString('@media (min-width:1025px) and (min-height:740px){', $css);
        $this->assertStringContainsString('.sched-layout .cal-card{flex:1 1 0;min-height:0;', $css);

        $html = $this->actingAs($this->expertWithRequest(), 'expert')->get(route('instructor.schedule'))->assertOk()->getContent();
        $this->assertStringContainsString('fitDayPills(grid);', $html);
        $this->assertStringContainsString('more.textContent = `+${Number(cell.dataset.slots) - 1} more`;', $html);
    }

    public function test_the_side_cards_are_styled(): void
    {
        $css = File::get(public_path('css/schedule.css'));

        $this->assertStringContainsString('.sc-req{', $css);
        $this->assertStringContainsString('.sc-date{width:42px;height:46px;', $css);
        $this->assertStringContainsString('.cal-slot-pill.is-asked{', $css);
    }
}
