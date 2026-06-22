<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\CapstoneExpert;

class AdminApprovalApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_api_index_returns_experts_list()
    {
        // Create sample experts
        CapstoneExpert::factory()->count(3)->create();

        $response = $this->withoutMiddleware()
                         ->getJson(route('api.admin.approval.index'));

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data'
                 ]);
    }

    /** @test */
    public function test_approve_endpoint_returns_success()
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => false]);

        $response = $this->withoutMiddleware()
                         ->postJson(route('api.admin.approval.approve', ['expert' => $expert->id]));

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Account approved and email sent.',
                 ]);
    }
}
