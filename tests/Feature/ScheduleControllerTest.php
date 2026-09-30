<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ConsultationSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_get_slots_returns_ok(): void
    {
        $expert = CapstoneExpert::create([
            'email' => 'instructor@test.com',
            'first_name' => 'Test',
            'last_name' => 'Instructor',
            'approved_status' => true,
        ]);

        $response = $this->actingAs($expert, 'expert')->get('/instructor/schedule/slots');

        $response->assertStatus(200);
    }

    private function slot(string $date): array
    {
        return [
            'available_date' => Carbon::parse($date)->format('l'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'consultation_limit' => 1,
            'recur' => 'once',
            'specific_date' => $date,
        ];
    }

    public function test_a_one_time_slot_cannot_be_added_on_a_day_that_has_passed(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);

        $this->actingAs($expert, 'expert')
            ->postJson('/instructor/schedule/slots', $this->slot(today()->subDay()->toDateString()))
            ->assertStatus(422)
            ->assertJsonPath('error', 'That day has already passed. Pick today or a later date.');

        $this->assertSame(0, ConsultationSchedule::count());
    }

    public function test_a_one_time_slot_can_still_be_added_today_or_later(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);

        foreach ([today(), today()->addDays(3)] as $day) {
            $response = $this->actingAs($expert, 'expert')
                ->postJson('/instructor/schedule/slots', $this->slot($day->toDateString()));

            $this->assertNotSame(422, $response->status(), 'A slot on '.$day->toDateString().' should be accepted: '.$response->getContent());
        }
    }
}
