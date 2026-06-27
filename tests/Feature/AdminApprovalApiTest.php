<?php

namespace Tests\Feature;

use App\Mail\RequestStatusMail;
use App\Models\CapstoneExpert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminApprovalApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_approve_endpoint_returns_success()
    {
        Mail::fake();

        $admin = CapstoneExpert::factory()->create([
            'is_admin' => true,
            'approved_status' => true,
        ]);

        $requestId = DB::table('account_requests')->insertGetId([
            'email' => 'instructor.test@gmail.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'status' => 'pending',
            'max_teams' => 4,
            'specialization' => json_encode(['adviser']),
            'expertise' => json_encode(['Web Development', 'UI/UX']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'expert')
            ->from(route('admin.approval'))
            ->postJson(route('admin.approval.approve', ['expert_id' => $requestId]));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account approved and email sent.',
            ]);

        $this->assertDatabaseHas('capstone_experts', [
            'email' => 'instructor.test@gmail.com',
            'approved_status' => true,
        ]);

        $this->assertDatabaseHas('account_requests', [
            'id' => $requestId,
            'status' => 'approved',
        ]);

        Mail::assertQueued(RequestStatusMail::class);
    }

    /** @test */
    public function test_reject_endpoint_returns_success()
    {
        Mail::fake();

        $admin = CapstoneExpert::factory()->create([
            'is_admin' => true,
            'approved_status' => true,
        ]);

        $requestId = DB::table('account_requests')->insertGetId([
            'email' => 'instructor.test@gmail.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'status' => 'pending',
            'max_teams' => 4,
            'specialization' => json_encode(['adviser']),
            'expertise' => json_encode(['Web Development']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'expert')
            ->from(route('admin.approval'))
            ->postJson(route('admin.approval.reject', ['expert_id' => $requestId]), [
                'reason' => 'Insufficient credentials.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account rejected and email sent.',
            ]);

        $this->assertDatabaseHas('account_requests', [
            'id' => $requestId,
            'status' => 'rejected',
            'rejection_reason' => 'Insufficient credentials.',
            'reject_attempts' => 0,
        ]);

        Mail::assertQueued(RequestStatusMail::class);
    }

    /** @test */
    public function test_reject_requires_reason()
    {
        $admin = CapstoneExpert::factory()->create([
            'is_admin' => true,
            'approved_status' => true,
        ]);

        $requestId = DB::table('account_requests')->insertGetId([
            'email' => 'instructor.test@gmail.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'status' => 'pending',
            'max_teams' => 4,
            'specialization' => json_encode(['adviser']),
            'expertise' => json_encode(['Web Development']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'expert')
            ->postJson(route('admin.approval.reject', ['expert_id' => $requestId]));

        $this->assertNotEquals(200, $response->getStatusCode());

        $this->assertDatabaseHas('account_requests', [
            'id' => $requestId,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function test_approve_returns_404_for_nonexistent_request()
    {
        $admin = CapstoneExpert::factory()->create([
            'is_admin' => true,
            'approved_status' => true,
        ]);

        $response = $this->actingAs($admin, 'expert')
            ->from(route('admin.approval'))
            ->postJson(route('admin.approval.approve', ['expert_id' => 99999]));

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Request not found.',
            ]);
    }

    /** @test */
    public function test_reject_returns_404_for_nonexistent_request()
    {
        $admin = CapstoneExpert::factory()->create([
            'is_admin' => true,
            'approved_status' => true,
        ]);

        $response = $this->actingAs($admin, 'expert')
            ->from(route('admin.approval'))
            ->postJson(route('admin.approval.reject', ['expert_id' => 99999]), [
                'reason' => 'Some reason.',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Request not found.',
            ]);
    }
}
