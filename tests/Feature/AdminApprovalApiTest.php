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

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['role_name' => 'adviser', 'description' => 'Adviser'],
            ['role_name' => 'consultant', 'description' => 'Consultant'],
            ['role_name' => 'grammarian', 'description' => 'Grammarian'],
            ['role_name' => 'capstone_adviser', 'description' => 'Capstone Adviser'],
        ]);
    }

    /** @test */
    public function test_approve_endpoint_returns_success()
    {
        Mail::fake();

        $admin = $this->makeCapsa(CapstoneExpert::create([
            'email' => 'admin.test@gmail.com',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'is_admin' => true,
            'approved_status' => true,
        ]));

        $expert = CapstoneExpert::create([
            'email' => 'instructor.test@gmail.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'approved_status' => false,
            'max_teams' => 4,
        ]);

        $adviserRoleId = DB::table('roles')->where('role_name', 'adviser')->value('role_id');
        $consultantRoleId = DB::table('roles')->where('role_name', 'consultant')->value('role_id');

        DB::table('expert_expertise')->insert([
            'expert_id' => $expert->expert_id,
            'expertise_field' => 'Web Development',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Role is no longer picked at request time - CAPSA assigns it here,
        // as part of approving the request.
        $response = $this->actingAs($admin, 'expert')
            ->from(route('capsa.approval'))
            ->patchJson(route('capsa.approval.update', ['expert_id' => $expert->expert_id]), [
                'status' => 'approved',
                'role_ids' => [$adviserRoleId, $consultantRoleId],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account approved and email sent.',
            ]);

        $this->assertDatabaseHas('capstone_experts', [
            'expert_id' => $expert->expert_id,
            'approved_status' => true,
        ]);

        $this->assertDatabaseHas('expert_roles', ['expert_id' => $expert->expert_id, 'role_id' => $adviserRoleId]);
        $this->assertDatabaseHas('expert_roles', ['expert_id' => $expert->expert_id, 'role_id' => $consultantRoleId]);

        Mail::assertQueued(RequestStatusMail::class);
    }

    /** @test */
    public function test_approve_requires_at_least_one_role(): void
    {
        $admin = $this->makeCapsa(CapstoneExpert::create([
            'email' => 'admin.test@gmail.com',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'is_admin' => true,
            'approved_status' => true,
        ]));

        $expert = CapstoneExpert::create([
            'email' => 'instructor.test@gmail.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'approved_status' => false,
            'max_teams' => 4,
        ]);

        // Same lenient assertion test_reject_requires_reason already uses -
        // this app's exception config only renders clean JSON errors for
        // /api/* routes (bootstrap/app.php's shouldRenderJsonWhen), not
        // /admin/*, so a validation failure here doesn't come back as a
        // simple 422 in tests. What actually matters either way: it must
        // not succeed, and the expert must stay unapproved.
        $response = $this->actingAs($admin, 'expert')
            ->patchJson(route('capsa.approval.update', ['expert_id' => $expert->expert_id]), ['status' => 'approved']);

        $this->assertNotEquals(200, $response->getStatusCode());

        $this->assertDatabaseHas('capstone_experts', [
            'expert_id' => $expert->expert_id,
            'approved_status' => false,
        ]);
    }

    /** @test */
    public function test_reject_endpoint_returns_success()
    {
        Mail::fake();

        $admin = $this->makeCapsa(CapstoneExpert::create([
            'email' => 'admin.test@gmail.com',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'is_admin' => true,
            'approved_status' => true,
        ]));

        $expert = CapstoneExpert::create([
            'email' => 'instructor.test@gmail.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'approved_status' => false,
            'max_teams' => 4,
        ]);

        $response = $this->actingAs($admin, 'expert')
            ->from(route('capsa.approval'))
            ->patchJson(route('capsa.approval.update', ['expert_id' => $expert->expert_id]), [
                'status' => 'rejected',
                'reason' => 'Insufficient credentials.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account rejected and email sent.',
            ]);

        $this->assertDatabaseHas('capstone_experts', [
            'expert_id' => $expert->expert_id,
            'rejection_reason' => 'Insufficient credentials.',
        ]);

        Mail::assertQueued(RequestStatusMail::class);
    }

    /** @test */
    public function test_reject_requires_reason()
    {
        $admin = $this->makeCapsa(CapstoneExpert::create([
            'email' => 'admin.test@gmail.com',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'is_admin' => true,
            'approved_status' => true,
        ]));

        $expert = CapstoneExpert::create([
            'email' => 'instructor.test@gmail.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'approved_status' => false,
            'max_teams' => 4,
        ]);

        $response = $this->actingAs($admin, 'expert')
            ->patchJson(route('capsa.approval.update', ['expert_id' => $expert->expert_id]), ['status' => 'rejected']);

        $this->assertNotEquals(200, $response->getStatusCode());

        $this->assertDatabaseHas('capstone_experts', [
            'expert_id' => $expert->expert_id,
            'approved_status' => false,
        ]);
    }

    /** @test */
    public function test_approve_returns_404_for_nonexistent_request()
    {
        $admin = $this->makeCapsa(CapstoneExpert::create([
            'email' => 'admin.test@gmail.com',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'is_admin' => true,
            'approved_status' => true,
        ]));

        $response = $this->actingAs($admin, 'expert')
            ->from(route('capsa.approval'))
            ->patchJson(route('capsa.approval.update', ['expert_id' => 99999]), ['status' => 'approved']);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Request not found.',
            ]);
    }

    /** @test */
    public function test_reject_returns_404_for_nonexistent_request()
    {
        $admin = $this->makeCapsa(CapstoneExpert::create([
            'email' => 'admin.test@gmail.com',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'is_admin' => true,
            'approved_status' => true,
        ]));

        $response = $this->actingAs($admin, 'expert')
            ->from(route('capsa.approval'))
            ->patchJson(route('capsa.approval.update', ['expert_id' => 99999]), [
                'status' => 'rejected',
                'reason' => 'Some reason.',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Request not found.',
            ]);
    }
}
