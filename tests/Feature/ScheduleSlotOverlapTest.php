<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduleSlotOverlapTest extends TestCase
{
    use RefreshDatabase;

    private function makeExpert(): CapstoneExpert
    {
        return CapstoneExpert::create([
            'email' => 'instructor@test.com',
            'first_name' => 'Test',
            'last_name' => 'Instructor',
            'approved_status' => true,
        ]);
    }

    public function test_a_once_slot_is_not_blocked_by_an_unrelated_once_slot_on_a_different_date(): void
    {
        $expert = $this->makeExpert();

        // An old one-time slot that happened to fall on some Wednesday,
        // same weekday name and time window as the one being added below,
        // but on a completely unrelated date.
        DB::table('consultation_schedule')->insert([
            'expert_id' => $expert->expert_id,
            'available_date' => 'Wednesday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'consultation_limit' => 1,
            'is_available' => true,
            'recur' => 'once',
            'specific_date' => '2026-07-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($expert, 'expert')->postJson('/instructor/schedule/slots', [
            'available_date' => 'Wednesday',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'consultation_limit' => 1,
            'recur' => 'once',
            'specific_date' => '2026-08-05',
        ]);

        $response->assertOk();
        $response->assertJson(['is_available' => true]);
    }

    public function test_a_once_slot_is_blocked_by_a_weekly_slot_on_the_same_weekday_and_time(): void
    {
        $expert = $this->makeExpert();

        DB::table('consultation_schedule')->insert([
            'expert_id' => $expert->expert_id,
            'available_date' => 'Wednesday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'consultation_limit' => 1,
            'is_available' => true,
            'recur' => 'weekly',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($expert, 'expert')->postJson('/instructor/schedule/slots', [
            'available_date' => 'Wednesday',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'consultation_limit' => 1,
            'recur' => 'once',
            'specific_date' => '2026-08-05',
        ]);

        $response->assertOk();
        $response->assertJson(['is_available' => false]);
    }

    public function test_a_once_slot_is_blocked_by_another_once_slot_on_the_same_date(): void
    {
        $expert = $this->makeExpert();

        DB::table('consultation_schedule')->insert([
            'expert_id' => $expert->expert_id,
            'available_date' => 'Wednesday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'consultation_limit' => 1,
            'is_available' => true,
            'recur' => 'once',
            'specific_date' => '2026-08-05',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($expert, 'expert')->postJson('/instructor/schedule/slots', [
            'available_date' => 'Wednesday',
            'start_time' => '09:30',
            'end_time' => '10:30',
            'consultation_limit' => 1,
            'recur' => 'once',
            'specific_date' => '2026-08-05',
        ]);

        $response->assertOk();
        $response->assertJson(['is_available' => false]);
    }
}
