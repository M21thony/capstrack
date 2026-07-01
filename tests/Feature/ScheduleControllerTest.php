<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
