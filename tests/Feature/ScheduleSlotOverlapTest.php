<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduleSlotOverlapTest extends TestCase
{
    use RefreshDatabase;

    /** These slots are added through the endpoint, which refuses a day that has passed. */
    private function aFutureWednesday(): string
    {
        return today()->modify('next wednesday')->toDateString();
    }

    private function makeExpert(): CapstoneExpert
    {
        return CapstoneExpert::create([
            'email' => 'instructor@test.com',
            'first_name' => 'Test',
            'last_name' => 'Instructor',
            'approved_status' => true,
        ]);
    }

    /**
     * Saving availability a second time used to insert the same slots again.
     * The overlap check below flagged every copy unavailable, and an
     * unavailable slot reads as a booked one on the schedule page - so four
     * repeated saves showed up as four appointments with no team on them.
     */
    public function test_saving_the_same_slot_twice_does_not_make_a_second_one(): void
    {
        $expert = $this->makeExpert();

        $post = fn () => $this->actingAs($expert, 'expert')->postJson('/instructor/schedule/slots', [
            'available_date' => 'Tuesday',
            'start_time' => '09:00',
            'end_time' => '10:30',
            'consultation_limit' => 1,
            'recur' => 'weekly',
        ]);

        $first = $post();
        $first->assertOk()->assertJson(['is_available' => true]);

        $post()->assertOk()->assertJson([
            'schedule_id' => $first->json('schedule_id'),
            'is_available' => true,
            'already_existed' => true,
        ]);

        $this->assertSame(1, DB::table('consultation_schedule')->count());
    }

    public function test_a_one_time_slot_cannot_be_added_on_a_day_that_has_ended(): void
    {
        $expert = $this->makeExpert();
        $yesterday = now()->subDay();

        $this->actingAs($expert, 'expert')->postJson('/instructor/schedule/slots', [
            'available_date' => $yesterday->format('l'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'consultation_limit' => 1,
            'recur' => 'once',
            'specific_date' => $yesterday->toDateString(),
        ])->assertStatus(422);

        $this->assertSame(0, DB::table('consultation_schedule')->count());
    }

    private function onceSlot(CapstoneExpert $expert, string $date): int
    {
        return DB::table('consultation_schedule')->insertGetId([
            'expert_id' => $expert->expert_id,
            'available_date' => now()->parse($date)->format('l'),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'consultation_limit' => 1,
            'is_available' => true,
            'recur' => 'once',
            'specific_date' => $date,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_one_time_slot_that_has_ended_cannot_be_edited(): void
    {
        $expert = $this->makeExpert();
        $id = $this->onceSlot($expert, now()->subDay()->toDateString());

        $this->actingAs($expert, 'expert')
            ->patchJson("/instructor/schedule/slots/{$id}", ['consultation_limit' => 3])
            ->assertStatus(422);

        $this->assertSame(1, (int) DB::table('consultation_schedule')->where('schedule_id', $id)->value('consultation_limit'));
    }

    public function test_a_one_time_slot_cannot_be_moved_to_a_time_that_has_passed(): void
    {
        $expert = $this->makeExpert();
        $id = $this->onceSlot($expert, now()->addDays(3)->toDateString());

        $this->actingAs($expert, 'expert')
            ->patchJson("/instructor/schedule/slots/{$id}", ['specific_date' => now()->subDay()->toDateString()])
            ->assertStatus(422);
    }

    public function test_a_one_time_slot_still_to_come_can_be_edited(): void
    {
        $expert = $this->makeExpert();
        $id = $this->onceSlot($expert, now()->addDays(3)->toDateString());

        $this->actingAs($expert, 'expert')
            ->patchJson("/instructor/schedule/slots/{$id}", ['consultation_limit' => 3])
            ->assertOk();

        $this->assertSame(3, (int) DB::table('consultation_schedule')->where('schedule_id', $id)->value('consultation_limit'));
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
            'specific_date' => '2027-07-07',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($expert, 'expert')->postJson('/instructor/schedule/slots', [
            'available_date' => 'Wednesday',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'consultation_limit' => 1,
            'recur' => 'once',
            'specific_date' => $this->aFutureWednesday(),
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
            'specific_date' => $this->aFutureWednesday(),
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
            'specific_date' => $this->aFutureWednesday(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($expert, 'expert')->postJson('/instructor/schedule/slots', [
            'available_date' => 'Wednesday',
            'start_time' => '09:30',
            'end_time' => '10:30',
            'consultation_limit' => 1,
            'recur' => 'once',
            'specific_date' => $this->aFutureWednesday(),
        ]);

        $response->assertOk();
        $response->assertJson(['is_available' => false]);
    }
}
