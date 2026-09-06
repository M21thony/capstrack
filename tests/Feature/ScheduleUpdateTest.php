<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ConsultationSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeExpert(): CapstoneExpert
    {
        return CapstoneExpert::create([
            'email' => 'schedule.expert@gmail.com',
            'first_name' => 'Schedule',
            'last_name' => 'Expert',
            'approved_status' => true,
        ]);
    }

    public function test_expert_can_update_own_slot(): void
    {
        $expert = $this->makeExpert();

        $slot = ConsultationSchedule::create([
            'expert_id' => $expert->expert_id,
            'available_date' => 'Monday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'consultation_limit' => 1,
            'slot_type' => 'consult',
            'is_available' => true,
            'recur' => 'weekly',
        ]);

        $response = $this->actingAs($expert, 'expert')->patchJson(
            "/instructor/schedule/slots/{$slot->schedule_id}",
            [
                'available_date' => 'Monday',
                'start_time' => '13:00',
                'end_time' => '14:00',
                'consultation_limit' => 3,
                'slot_type' => 'docreview',
            ]
        );

        $response->assertOk();
        $response->assertJson(['updated' => true]);

        $slot->refresh();
        $this->assertEquals('13:00', $slot->start_time);
        $this->assertEquals('14:00', $slot->end_time);
        $this->assertEquals(3, $slot->consultation_limit);
        $this->assertEquals('docreview', $slot->slot_type);
        // Recurrence is untouched by an update — only the frontend's add
        // flow ever sets it.
        $this->assertEquals('weekly', $slot->recur);
    }

    public function test_expert_cannot_update_another_experts_slot(): void
    {
        $owner = $this->makeExpert();
        $intruder = CapstoneExpert::create([
            'email' => 'intruder@gmail.com',
            'first_name' => 'Intruder',
            'last_name' => 'Expert',
            'approved_status' => true,
        ]);

        $slot = ConsultationSchedule::create([
            'expert_id' => $owner->expert_id,
            'available_date' => 'Tuesday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'consultation_limit' => 1,
            'slot_type' => 'consult',
            'is_available' => true,
            'recur' => 'weekly',
        ]);

        $response = $this->actingAs($intruder, 'expert')->patchJson(
            "/instructor/schedule/slots/{$slot->schedule_id}",
            ['start_time' => '13:00', 'end_time' => '14:00']
        );

        $response->assertStatus(404);

        $slot->refresh();
        $this->assertEquals('09:00:00', $slot->start_time);
    }

    public function test_update_slot_rejects_end_time_before_start_time(): void
    {
        $expert = $this->makeExpert();

        $slot = ConsultationSchedule::create([
            'expert_id' => $expert->expert_id,
            'available_date' => 'Wednesday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'consultation_limit' => 1,
            'slot_type' => 'consult',
            'is_available' => true,
            'recur' => 'weekly',
        ]);

        $response = $this->actingAs($expert, 'expert')->patchJson(
            "/instructor/schedule/slots/{$slot->schedule_id}",
            ['start_time' => '14:00', 'end_time' => '13:00']
        );

        $response->assertStatus(422);

        $slot->refresh();
        $this->assertEquals('09:00:00', $slot->start_time);
    }

    public function test_update_nonexistent_slot_returns_404(): void
    {
        $expert = $this->makeExpert();

        $response = $this->actingAs($expert, 'expert')->patchJson(
            '/instructor/schedule/slots/999999',
            ['start_time' => '13:00', 'end_time' => '14:00']
        );

        $response->assertStatus(404);
    }

    /**
     * addSlot has always taken recur and specific_date; updateSlot took
     * neither, so both were dropped on the way in and editing a slot's
     * recurrence looked like it did nothing at all.
     */
    public function test_a_slot_can_be_changed_from_weekly_to_a_single_date(): void
    {
        $expert = $this->makeExpert();

        $slot = ConsultationSchedule::create([
            'expert_id' => $expert->expert_id,
            'available_date' => 'Monday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'consultation_limit' => 1,
            'slot_type' => 'consult',
            'is_available' => true,
            'recur' => 'weekly',
        ]);

        $this->actingAs($expert, 'expert')->patchJson(
            "/instructor/schedule/slots/{$slot->schedule_id}",
            ['recur' => 'once', 'specific_date' => now()->addWeek()->toDateString()]
        )->assertOk();

        $slot->refresh();
        $this->assertSame('once', $slot->recur);
        $this->assertStringStartsWith(now()->addWeek()->toDateString(), (string) $slot->specific_date);
    }

    /** And back again - which has to clear the date the one-off carried. */
    public function test_going_back_to_weekly_clears_the_one_off_date(): void
    {
        $expert = $this->makeExpert();

        $slot = ConsultationSchedule::create([
            'expert_id' => $expert->expert_id,
            'available_date' => 'Tuesday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'consultation_limit' => 1,
            'slot_type' => 'consult',
            'is_available' => true,
            'recur' => 'once',
            'specific_date' => now()->addWeek()->toDateString(),
        ]);

        $this->actingAs($expert, 'expert')->patchJson(
            "/instructor/schedule/slots/{$slot->schedule_id}",
            ['recur' => 'weekly']
        )->assertOk();

        $slot->refresh();
        $this->assertSame('weekly', $slot->recur);
        $this->assertNull($slot->specific_date, 'A weekly slot kept a date belonging to when it was a one-off.');
    }

    /** A one-time slot with no date is not a slot. */
    public function test_switching_to_a_single_date_needs_a_date(): void
    {
        $expert = $this->makeExpert();

        $slot = ConsultationSchedule::create([
            'expert_id' => $expert->expert_id,
            'available_date' => 'Wednesday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'consultation_limit' => 1,
            'slot_type' => 'consult',
            'is_available' => true,
            'recur' => 'weekly',
        ]);

        $this->actingAs($expert, 'expert')->patchJson(
            "/instructor/schedule/slots/{$slot->schedule_id}",
            ['recur' => 'once']
        )->assertStatus(422);

        $this->assertSame('weekly', $slot->fresh()->recur);
    }

    /**
     * Adding an overlapping slot was refused; editing one into an overlap
     * was not, so the same clash could be reached by the back door.
     */
    public function test_an_edit_cannot_walk_a_slot_into_an_overlap(): void
    {
        $expert = $this->makeExpert();

        ConsultationSchedule::create([
            'expert_id' => $expert->expert_id,
            'available_date' => 'Thursday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'consultation_limit' => 1,
            'slot_type' => 'consult',
            'is_available' => true,
            'recur' => 'weekly',
        ]);

        $mover = ConsultationSchedule::create([
            'expert_id' => $expert->expert_id,
            'available_date' => 'Thursday',
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'consultation_limit' => 1,
            'slot_type' => 'consult',
            'is_available' => true,
            'recur' => 'weekly',
        ]);

        $this->actingAs($expert, 'expert')->patchJson(
            "/instructor/schedule/slots/{$mover->schedule_id}",
            ['start_time' => '09:30', 'end_time' => '10:30']
        )->assertStatus(422);

        $this->assertSame('14:00', substr((string) $mover->fresh()->start_time, 0, 5));
    }

    /** Editing a slot without moving it must not clash with itself. */
    public function test_a_slot_does_not_clash_with_itself(): void
    {
        $expert = $this->makeExpert();

        $slot = ConsultationSchedule::create([
            'expert_id' => $expert->expert_id,
            'available_date' => 'Friday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'consultation_limit' => 1,
            'slot_type' => 'consult',
            'is_available' => true,
            'recur' => 'weekly',
        ]);

        $this->actingAs($expert, 'expert')->patchJson(
            "/instructor/schedule/slots/{$slot->schedule_id}",
            ['consultation_limit' => 4]
        )->assertOk();

        $this->assertSame(4, (int) $slot->fresh()->consultation_limit);
    }
}
