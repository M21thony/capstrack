<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ExpertLoginTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_expert_controller_mock_login_redirects_admin_to_admin_dashboard(): void
    {
        $state = 'test-state-token';
        $this->withSession(['oauth_state' => $state]);

        $expert = CapstoneExpert::create([
            'email' => 'admin.expert@gmail.com',
            'first_name' => 'Admin',
            'last_name' => 'Expert',
            'is_admin' => true,
            'approved_status' => true,
        ]);

        $response = $this->postJson('/expert/login/google/mock', [
            'state' => $state,
            'email' => 'admin.expert@gmail.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'authenticated',
            'redirect_url' => '/admin/dashboard',
        ]);

        $this->assertTrue(Auth::guard('expert')->check());
        $this->assertEquals($expert->expert_id, Auth::guard('expert')->id());
    }

    /** @test */
    public function test_expert_controller_mock_login_redirects_approved_instructor_to_instructor_dashboard(): void
    {
        $state = 'test-state-token';
        $this->withSession(['oauth_state' => $state]);

        $expert = CapstoneExpert::create([
            'email' => 'instructor.expert@gmail.com',
            'first_name' => 'Instructor',
            'last_name' => 'Expert',
            'is_admin' => false,
            'approved_status' => true,
        ]);

        $response = $this->postJson('/expert/login/google/mock', [
            'state' => $state,
            'email' => 'instructor.expert@gmail.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'authenticated',
            'redirect_url' => '/instructor/dashboard',
        ]);

        $this->assertTrue(Auth::guard('expert')->check());
        $this->assertEquals($expert->expert_id, Auth::guard('expert')->id());
    }

    /** @test */
    public function test_expert_controller_mock_login_shows_pending_approval_for_unapproved_non_admin(): void
    {
        $state = 'test-state-token';
        $this->withSession(['oauth_state' => $state]);

        $expert = CapstoneExpert::create([
            'email' => 'unapproved.expert@gmail.com',
            'first_name' => 'Unapproved',
            'last_name' => 'Expert',
            'is_admin' => false,
            'approved_status' => false,
        ]);

        $response = $this->post('/expert/login/google/mock', [
            'state' => $state,
            'email' => 'unapproved.expert@gmail.com',
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('auth.pending-approval');
    }

    /** @test */
    public function test_auth_controller_mock_login_redirects_admin_to_admin_dashboard(): void
    {
        $state = 'test-state-token';
        $this->withSession(['oauth_state' => $state]);

        $expert = CapstoneExpert::create([
            'email' => 'admin.auth@gmail.com',
            'first_name' => 'Admin',
            'last_name' => 'Auth',
            'is_admin' => true,
            'approved_status' => true,
        ]);

        $response = $this->postJson('/login/google/mock', [
            'state' => $state,
            'email' => 'admin.auth@gmail.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'authenticated',
            'redirect_url' => '/admin/dashboard',
        ]);

        $this->assertTrue(Auth::guard('expert')->check());
        $this->assertEquals($expert->expert_id, Auth::guard('expert')->id());
    }

    /** @test */
    public function test_auth_controller_mock_login_redirects_approved_instructor_to_instructor_dashboard(): void
    {
        $state = 'test-state-token';
        $this->withSession(['oauth_state' => $state]);

        $expert = CapstoneExpert::create([
            'email' => 'instructor.auth@gmail.com',
            'first_name' => 'Instructor',
            'last_name' => 'Auth',
            'is_admin' => false,
            'approved_status' => true,
        ]);

        $response = $this->postJson('/login/google/mock', [
            'state' => $state,
            'email' => 'instructor.auth@gmail.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'authenticated',
            'redirect_url' => '/instructor/dashboard',
        ]);

        $this->assertTrue(Auth::guard('expert')->check());
        $this->assertEquals($expert->expert_id, Auth::guard('expert')->id());
    }

    /** @test */
    public function test_pending_and_rejected_views_can_be_rendered_without_guard_errors(): void
    {
        $expert = CapstoneExpert::create([
            'email' => 'test.expert@gmail.com',
            'first_name' => 'Test',
            'last_name' => 'Expert',
            'is_admin' => false,
            'approved_status' => true,
        ]);

        $this->actingAs($expert, 'expert');

        // Render the instructor.pending view directly to ensure it has no guard errors
        $response = $this->get('/instructor/pending');
        $response->assertStatus(200);
        $response->assertSee('Test Expert');

        // Render the instructor.rejected view directly to ensure it has no guard errors
        $response = $this->get('/instructor/rejected');
        $response->assertStatus(200);
    }

    /** @test */
    public function test_admin_approval_page_can_be_rendered_successfully(): void
    {
        $admin = CapstoneExpert::create([
            'email' => 'admin.test@gmail.com',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'is_admin' => true,
            'approved_status' => true,
        ]);

        $this->actingAs($admin, 'expert');

        $response = $this->get('/admin/approval');
        $response->assertStatus(200);
        $response->assertSee('Expert Account Approval');
    }
}
