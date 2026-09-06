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
}
