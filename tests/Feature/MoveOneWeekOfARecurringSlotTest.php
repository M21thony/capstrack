<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Changing one week of a repeating slot.
 *
 * A slot stores a weekday, so "Monday 10:00-11:00" is every Monday. Editing it
 * rewrote the rule, which meant an adviser who wanted to shift a single week
 * shifted the whole term instead - and the week they were looking at
 * disappeared into the change along with the rest.
 *
 * Moving one week now leaves the rule untouched: the date is excluded from it,
 * and a one-off slot takes that date's place.
 */
class MoveOneWeekOfARecurringSlotTest extends TestCase
{
    use RefreshDatabase;

    private CapstoneExpert $expert;

    protected function setUp(): void
    {
        parent::setUp();

        $this->expert = CapstoneExpert::create([
            'email' => 'adviser@example.test',
            'first_name' => 'Rosa',
            'last_name' => 'Mendoza',
            'approved_status' => true,
        ]);
    }

    private function weeklySlot(string $day = 'Monday', string $from = '10:00', string $to = '11:00'): int
    {
        return DB::table('consultation_schedule')->insertGetId([
            'expert_id' => $this->expert->expert_id,
            'available_date' => $day, 'specific_date' => null, 'recur' => 'weekly',
            'start_time' => $from, 'end_time' => $to, 'consultation_limit' => 1,
            'slot_type' => 'consult', 'is_available' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** A Monday in the future, so nothing is filtered out for being past. */
    private function aMonday(int $weeksAhead = 1): string
    {
        return now()->startOfWeek()->addWeeks($weeksAhead)->format('Y-m-d');
    }

    public function test_moving_one_week_leaves_the_weekly_slot_untouched(): void
    {
        $id = $this->weeklySlot();
        $date = $this->aMonday();

        $this->actingAs($this->expert, 'expert')
            ->putJson("/instructor/schedule/slots/{$id}/occurrences/{$date}", [
                'start_time' => '13:00', 'end_time' => '14:00',
            ])->assertOk();

        $rule = DB::table('consultation_schedule')->where('schedule_id', $id)->first();
        $this->assertSame('10:00', substr((string) $rule->start_time, 0, 5),
            'every other Monday must be unchanged');
        $this->assertSame('weekly', $rule->recur);

        $this->assertDatabaseHas('consultation_schedule_exception', [
            'schedule_id' => $id, 'exception_date' => $date,
        ]);

        $moved = DB::table('consultation_schedule')
            ->where('specific_date', $date)->where('recur', 'once')->first();
        $this->assertNotNull($moved, 'the moved week must exist as its own slot');
        $this->assertSame('13:00', substr((string) $moved->start_time, 0, 5));
        $this->assertSame('Monday', $moved->available_date);
    }

    /** The bug in one assertion: the week being edited must not vanish. */
    public function test_the_edited_week_still_has_a_slot_on_it(): void
    {
        $id = $this->weeklySlot();
        $date = $this->aMonday();

        $this->actingAs($this->expert, 'expert')
            ->putJson("/instructor/schedule/slots/{$id}/occurrences/{$date}", [
                'start_time' => '15:00', 'end_time' => '16:00',
            ])->assertOk();

        $onThatDate = DB::table('consultation_schedule')
            ->where('expert_id', $this->expert->expert_id)
            ->where('specific_date', $date)->count();

        $this->assertSame(1, $onThatDate, 'exactly one slot on the moved date - not zero, not two');
    }

    public function test_skipping_one_week_does_not_delete_the_slot(): void
    {
        $id = $this->weeklySlot();
        $date = $this->aMonday(2);

        $this->actingAs($this->expert, 'expert')
            ->deleteJson("/instructor/schedule/slots/{$id}/occurrences/{$date}")
            ->assertOk()->assertJson(['removed' => true]);

        $this->assertDatabaseHas('consultation_schedule', ['schedule_id' => $id]);
        $this->assertDatabaseHas('consultation_schedule_exception', [
            'schedule_id' => $id, 'exception_date' => $date,
        ]);
    }

    public function test_skipping_the_same_week_twice_is_not_an_error(): void
    {
        $id = $this->weeklySlot();
        $date = $this->aMonday();
        $url = "/instructor/schedule/slots/{$id}/occurrences/{$date}";

        $this->actingAs($this->expert, 'expert')->deleteJson($url)->assertOk();
        $this->actingAs($this->expert, 'expert')->deleteJson($url)->assertOk();

        $this->assertSame(1, DB::table('consultation_schedule_exception')
            ->where('schedule_id', $id)->count(), 'the unique key must not surface as a 500');
    }

    public function test_a_booked_week_cannot_be_moved_out_from_under_the_student(): void
    {
        $id = $this->weeklySlot();
        $date = $this->aMonday();

        // A real student row: SQLite enforces the foreign key that MyISAM
        // ignores, so a made-up id passes in production and fails here.
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'booked.student@my.cspc.edu.ph',
        ]);

        DB::table('consultation_booking')->insert([
            'student_id' => $student->student_id, 'schedule_id' => $id, 'session_date' => $date,
            'booking_status' => 'scheduled', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->expert, 'expert')
            ->putJson("/instructor/schedule/slots/{$id}/occurrences/{$date}", [
                'start_time' => '13:00', 'end_time' => '14:00',
            ])->assertStatus(422);

        $this->assertDatabaseMissing('consultation_schedule_exception', ['schedule_id' => $id]);
    }

    /** A slot a team is expecting to turn up to cannot be hidden from them. */
    public function test_a_booked_slot_cannot_be_marked_unavailable(): void
    {
        $id = $this->weeklySlot();
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'booked.team@my.cspc.edu.ph',
        ]);

        DB::table('consultation_booking')->insert([
            'student_id' => $student->student_id, 'schedule_id' => $id,
            'session_date' => $this->aMonday(), 'booking_status' => 'scheduled',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->expert, 'expert')
            ->putJson("/instructor/schedule/slots/{$id}/availability", ['is_available' => false])
            ->assertStatus(422);

        $this->assertSame(1, (int) DB::table('consultation_schedule')
            ->where('schedule_id', $id)->value('is_available'),
            'the slot must still be available to the team holding it');
    }

    /** A cancelled booking is not a booking - the slot frees up again. */
    public function test_a_cancelled_booking_does_not_block_it(): void
    {
        $id = $this->weeklySlot();
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'cancelled.team@my.cspc.edu.ph',
        ]);

        DB::table('consultation_booking')->insert([
            'student_id' => $student->student_id, 'schedule_id' => $id,
            'session_date' => $this->aMonday(), 'booking_status' => 'cancelled',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->expert, 'expert')
            ->putJson("/instructor/schedule/slots/{$id}/availability", ['is_available' => false])
            ->assertOk();
    }

    /** Yesterday's session already happened; it cannot be turned up to. */
    public function test_a_past_booking_does_not_block_it(): void
    {
        $id = $this->weeklySlot();
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'past.team@my.cspc.edu.ph',
        ]);

        DB::table('consultation_booking')->insert([
            'student_id' => $student->student_id, 'schedule_id' => $id,
            'session_date' => now()->subWeeks(2)->format('Y-m-d'),
            'booking_status' => 'scheduled',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->expert, 'expert')
            ->putJson("/instructor/schedule/slots/{$id}/availability", ['is_available' => false])
            ->assertOk();
    }

    /** Turning a slot back ON is never blocked. */
    public function test_making_a_slot_available_again_is_always_allowed(): void
    {
        $id = $this->weeklySlot();
        DB::table('consultation_schedule')->where('schedule_id', $id)->update(['is_available' => 0]);

        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'reopen.team@my.cspc.edu.ph',
        ]);
        DB::table('consultation_booking')->insert([
            'student_id' => $student->student_id, 'schedule_id' => $id,
            'session_date' => $this->aMonday(), 'booking_status' => 'scheduled',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->expert, 'expert')
            ->putJson("/instructor/schedule/slots/{$id}/availability", ['is_available' => true])
            ->assertOk();
    }

    public function test_a_date_that_is_not_that_weekday_is_refused(): void
    {
        $id = $this->weeklySlot('Monday');
        $wednesday = now()->startOfWeek()->addWeek()->addDays(2)->format('Y-m-d');

        $this->actingAs($this->expert, 'expert')
            ->putJson("/instructor/schedule/slots/{$id}/occurrences/{$wednesday}", [
                'start_time' => '13:00', 'end_time' => '14:00',
            ])->assertStatus(422);
    }

    /** The rule being replaced must not block the slot replacing it. */
    public function test_the_moved_week_does_not_collide_with_its_own_rule(): void
    {
        $id = $this->weeklySlot('Monday', '10:00', '11:00');
        $date = $this->aMonday();

        $this->actingAs($this->expert, 'expert')
            ->putJson("/instructor/schedule/slots/{$id}/occurrences/{$date}", [
                'start_time' => '10:30', 'end_time' => '11:30',
            ])->assertOk();
    }

    /** A different weekly slot on that day is still a real conflict. */
    public function test_it_still_refuses_to_overlap_a_different_slot(): void
    {
        $moving = $this->weeklySlot('Monday', '10:00', '11:00');
        $this->weeklySlot('Monday', '14:00', '15:00');
        $date = $this->aMonday();

        $this->actingAs($this->expert, 'expert')
            ->putJson("/instructor/schedule/slots/{$moving}/occurrences/{$date}", [
                'start_time' => '14:30', 'end_time' => '15:30',
            ])->assertStatus(422);
    }

    public function test_deleting_the_rule_takes_its_exceptions_with_it(): void
    {
        $id = $this->weeklySlot();
        $date = $this->aMonday();

        $this->actingAs($this->expert, 'expert')
            ->deleteJson("/instructor/schedule/slots/{$id}/occurrences/{$date}")
            ->assertOk();
        $this->actingAs($this->expert, 'expert')
            ->deleteJson("/instructor/schedule/slots/{$id}")->assertOk();

        // MyISAM has no cascade, and TRUNCATE reuses ids - an orphan here
        // would attach itself to whichever slot is created next.
        $this->assertDatabaseMissing('consultation_schedule_exception', ['schedule_id' => $id]);
    }

    public function test_the_page_is_told_which_dates_are_excluded(): void
    {
        $id = $this->weeklySlot();
        $date = $this->aMonday();

        $this->actingAs($this->expert, 'expert')
            ->deleteJson("/instructor/schedule/slots/{$id}/occurrences/{$date}")
            ->assertOk();

        $this->actingAs($this->expert, 'expert')
            ->getJson('/instructor/schedule/slots')
            ->assertOk()
            ->assertJsonStructure(['slots', 'exceptions'])
            ->assertJsonFragment(['schedule_id' => $id]);
    }
}
