<?php

namespace Tests\Feature;

use App\Mail\ConsultationBookedMail;
use App\Mail\ConsultationReminderMail;
use App\Models\CapstoneExpert;
use App\Models\ConsultationAvailability;
use App\Models\Student;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Availability set like Google Calendar's appointment schedules - duration,
 * weekly hours, scheduling window - and only booked consultations reaching
 * the expert's Google Calendar.
 */
class ConsultationAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function expert(bool $google = false): CapstoneExpert
    {
        return CapstoneExpert::factory()->create([
            'approved_status' => true,
            'google_token' => $google ? json_encode(['access_token' => 'x', 'refresh_token' => 'y']) : null,
        ]);
    }

    private function save(CapstoneExpert $expert, array $overrides = [])
    {
        return $this->actingAs($expert, 'expert')->putJson('/instructor/schedule/availability', array_merge([
            'duration_minutes' => 60,
            'buffer_minutes' => 0,
            'max_per_day' => null,
            'hours' => ['Monday' => [['09:00', '12:00']], 'Wednesday' => [['13:00', '15:00']]],
            'starts_on' => null,
            'ends_on' => null,
            'max_days_ahead' => 60,
            'min_notice_hours' => 24,
        ], $overrides));
    }

    private function weeklySlots(CapstoneExpert $expert): array
    {
        return DB::table('consultation_schedule')->where('expert_id', $expert->expert_id)
            ->orderBy('available_date')->orderBy('start_time')->get()
            ->map(fn ($s) => $s->available_date.' '.substr($s->start_time, 0, 5).'-'.substr($s->end_time, 0, 5))
            ->all();
    }

    public function test_saving_cuts_the_weekly_hours_into_slots_of_the_chosen_duration(): void
    {
        $expert = $this->expert();

        $this->save($expert)->assertOk()->assertJson(['added' => 5, 'removed' => 0]);

        $this->assertEqualsCanonicalizing([
            'Monday 09:00-10:00', 'Monday 10:00-11:00', 'Monday 11:00-12:00',
            'Wednesday 13:00-14:00', 'Wednesday 14:00-15:00',
        ], $this->weeklySlots($expert));

        // Saving the same thing again changes nothing.
        $this->save($expert)->assertOk()->assertJson(['added' => 0, 'removed' => 0]);

        $this->assertSame(60, ConsultationAvailability::where('expert_id', $expert->expert_id)->value('duration_minutes'));
    }

    public function test_narrowing_the_hours_removes_open_slots_but_keeps_booked_ones(): void
    {
        $expert = $this->expert();
        $this->save($expert)->assertOk();

        $booked = DB::table('consultation_schedule')->where('expert_id', $expert->expert_id)
            ->where('available_date', 'Wednesday')->where('start_time', 'like', '13:00%')->value('schedule_id');
        DB::table('consultation_booking')->insert([
            'student_id' => Student::factory()->withCompleteProfile()->create()->student_id,
            'schedule_id' => $booked,
            'session_date' => now()->addWeek()->toDateString(),
            'booking_status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->save($expert, ['hours' => ['Monday' => [['09:00', '10:00']]]])
            ->assertOk()
            ->assertJson(['added' => 0, 'removed' => 3, 'kept_booked' => 1]);

        $this->assertEqualsCanonicalizing(['Monday 09:00-10:00', 'Wednesday 13:00-14:00'], $this->weeklySlots($expert));
    }

    public function test_overlapping_hours_on_one_day_are_refused(): void
    {
        $this->save($this->expert(), ['hours' => ['Monday' => [['09:00', '12:00'], ['11:00', '13:00']]]])
            ->assertStatus(422)
            ->assertJson(['error' => 'On Monday, two sets of hours overlap.']);
    }

    public function test_open_slots_never_go_on_the_experts_google_calendar(): void
    {
        $expert = $this->expert(google: true);

        $this->mock(GoogleCalendarService::class, fn ($mock) => $mock->shouldNotReceive('createBookingEvent'));

        $this->save($expert)->assertOk();
        $this->actingAs($expert, 'expert')->postJson('/instructor/schedule/slots', [
            'available_date' => 'Friday', 'start_time' => '09:00', 'end_time' => '10:00',
            'consultation_limit' => 1, 'slot_type' => 'consult', 'recur' => 'weekly',
        ])->assertOk();
    }

    /** @return array{0: Student, 1: int} */
    private function studentWithSlot(CapstoneExpert $expert, string $day = 'Monday', string $start = '09:00:00'): array
    {
        $student = Student::factory()->withCompleteProfile()->create();
        $team = DB::table('team')->insertGetId(['team_name' => 'Team Window', 'team_status' => 'Active']);
        DB::table('student_s_y')->insert(['student_id' => $student->student_id, 'team_id' => $team, 'program' => 'BSIT', 'student_status' => 'Active']);

        $scheduleId = DB::table('consultation_schedule')->insertGetId([
            'expert_id' => $expert->expert_id, 'available_date' => $day, 'start_time' => $start,
            'end_time' => '10:00:00', 'consultation_limit' => 1, 'is_available' => true, 'recur' => 'weekly',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$student, $scheduleId];
    }

    public function test_a_booking_has_to_fall_inside_the_scheduling_window(): void
    {
        // Sunday noon: the next Monday 9:00 is 21 hours away, the one after is
        // 8 days, and the third is 15.
        Carbon::setTestNow('2026-09-20 12:00:00');
        $expert = $this->expert();
        ConsultationAvailability::create([
            'expert_id' => $expert->expert_id, 'duration_minutes' => 60, 'hours' => [],
            'max_days_ahead' => 10, 'min_notice_hours' => 24,
        ]);
        [$student, $scheduleId] = $this->studentWithSlot($expert);

        $book = fn (string $date) => $this->actingAs($student, 'web')
            ->postJson(route('student.consultation.book'), ['schedule_id' => $scheduleId, 'session_date' => $date]);

        // Too soon: less than 24 hours' notice.
        $book('2026-09-21')->assertStatus(422);
        // Too far: more than 10 days ahead.
        $book('2026-10-05')->assertStatus(422);
        // Inside the window.
        $book('2026-09-28')->assertOk()->assertJson(['success' => true]);
    }

    public function test_a_booked_consultation_gets_its_own_event_on_the_experts_calendar(): void
    {
        $expert = $this->expert(google: true);
        [$student, $scheduleId] = $this->studentWithSlot($expert);

        $this->mock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('createBookingEvent')->once()->andReturn('booking-event-1');
            $mock->shouldNotReceive('updateBookingEvent');
        });

        $this->actingAs($student, 'web')
            ->postJson(route('student.consultation.book'), ['schedule_id' => $scheduleId])
            ->assertOk();

        $this->assertDatabaseHas('consultation_booking', ['schedule_id' => $scheduleId, 'google_event_id' => 'booking-event-1']);
    }

    public function test_buffer_time_leaves_a_gap_between_appointments(): void
    {
        $expert = $this->expert();

        // 9 to 12 in hour-long appointments with half an hour between fits
        // 9:00, 10:30 and 12:00 - and 12:00 would end after the hours do.
        $this->save($expert, [
            'buffer_minutes' => 30,
            'hours' => ['Monday' => [['09:00', '12:00']]],
        ])->assertOk();

        $this->assertSame(['Monday 09:00-10:00', 'Monday 10:30-11:30'], $this->weeklySlots($expert));
    }

    public function test_an_appointment_can_be_any_length_not_only_the_offered_ones(): void
    {
        $expert = $this->expert();

        $this->save($expert, ['duration_minutes' => 20, 'hours' => ['Monday' => [['09:00', '10:00']]]])->assertOk();

        $this->assertSame(['Monday 09:00-09:20', 'Monday 09:20-09:40', 'Monday 09:40-10:00'], $this->weeklySlots($expert));

        // Still refused when it is not a real length.
        $this->save($expert, ['duration_minutes' => 3])->assertStatus(422);
    }

    public function test_a_day_takes_no_more_bookings_than_the_expert_allows(): void
    {
        Carbon::setTestNow('2026-09-20 12:00:00');
        $expert = $this->expert();
        ConsultationAvailability::create([
            'expert_id' => $expert->expert_id, 'duration_minutes' => 60, 'hours' => [],
            'max_days_ahead' => 60, 'min_notice_hours' => 0, 'max_per_day' => 1,
        ]);

        // Two slots on the same Monday, each free.
        [$first, $firstSlot] = $this->studentWithSlot($expert, 'Monday', '09:00:00');
        $secondSlot = DB::table('consultation_schedule')->insertGetId([
            'expert_id' => $expert->expert_id, 'available_date' => 'Monday', 'start_time' => '11:00:00',
            'end_time' => '12:00:00', 'consultation_limit' => 1, 'is_available' => true, 'recur' => 'weekly',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($first, 'web')
            ->postJson(route('student.consultation.book'), ['schedule_id' => $firstSlot, 'session_date' => '2026-09-21'])
            ->assertOk();

        // A different team, so the one-booking-per-expert rule is not what stops it.
        $other = Student::factory()->withCompleteProfile()->create();
        $otherTeam = DB::table('team')->insertGetId(['team_name' => 'Team Second', 'team_status' => 'Active']);
        DB::table('student_s_y')->insert(['student_id' => $other->student_id, 'team_id' => $otherTeam, 'program' => 'BSIT', 'student_status' => 'Active']);

        $this->actingAs($other, 'web')
            ->postJson(route('student.consultation.book'), ['schedule_id' => $secondSlot, 'session_date' => '2026-09-21'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Your expert is fully booked on that day. Pick another date.']);
    }

    public function test_a_booking_sends_one_email_with_the_team_copied_in(): void
    {
        Mail::fake();
        $expert = $this->expert();
        [$student, $scheduleId] = $this->studentWithSlot($expert);

        // Two more on the team: before this, each of them got their own copy.
        $teamId = DB::table('student_s_y')->where('student_id', $student->student_id)->value('team_id');
        $mates = collect(range(1, 2))->map(function () use ($teamId) {
            $mate = Student::factory()->withCompleteProfile()->create();
            DB::table('student_s_y')->insert(['student_id' => $mate->student_id, 'team_id' => $teamId, 'program' => 'BSIT', 'student_status' => 'Active']);

            return $mate;
        });

        $this->actingAs($student, 'web')
            ->postJson(route('student.consultation.book'), ['schedule_id' => $scheduleId])
            ->assertOk();

        Mail::assertQueued(ConsultationBookedMail::class, 1);
        Mail::assertQueued(ConsultationBookedMail::class, fn ($mail) => $mail->hasTo($expert->email)
            && $mail->hasCc($student->email)
            && $mail->hasCc($mates->first()->email));
    }

    public function test_the_daily_reminder_sends_one_email_for_each_session(): void
    {
        Mail::fake();
        $expert = $this->expert();
        [$student, $scheduleId] = $this->studentWithSlot($expert);

        DB::table('consultation_schedule')->where('schedule_id', $scheduleId)->update(['end_time' => '23:30:00']);
        DB::table('consultation_booking')->insert([
            'student_id' => $student->student_id,
            'team_id' => DB::table('student_s_y')->where('student_id', $student->student_id)->value('team_id'),
            'schedule_id' => $scheduleId,
            'session_date' => today()->toDateString(),
            'booking_status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('consultations:send-reminders')->assertSuccessful();

        Mail::assertQueued(ConsultationReminderMail::class, 1);
        Mail::assertQueued(ConsultationReminderMail::class, fn ($mail) => $mail->hasTo($expert->email) && $mail->hasCc($student->email));
    }
}
