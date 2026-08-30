<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ExpertLoginTest extends TestCase
{
    use RefreshDatabase;

    // The /expert/login/google/mock route (and the rest of ExpertController's
    // OAuth pair) was dead code - nothing linked to it, and it duplicated
    // AuthController's real, live mock-login path below with a narrower
    // @gmail.com-only domain check. It was deleted as part of the CAPSA/Admin
    // split; the auth_controller_mock_login_* tests below cover the same
    // redirect behavior via the route that's actually used.

    /** @test */
    public function test_auth_controller_mock_login_shows_pending_approval_for_unapproved_non_admin(): void
    {
        $state = 'test-state-token';
        $this->withSession(['oauth_state' => $state]);

        CapstoneExpert::create([
            'email' => 'unapproved.expert@gmail.com',
            'first_name' => 'Unapproved',
            'last_name' => 'Expert',
            'approved_status' => false,
        ]);

        $response = $this->post('/login/google/mock', [
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
        CapstoneExpert::create([
            'email' => 'rejected.expert@gmail.com',
            'first_name' => 'Rejected',
            'last_name' => 'Expert',
            'approved_status' => false,
            'rejection_reason' => 'Insufficient credentials.',
        ]);

        $response = $this->get('/login/rejected?email=rejected.expert@gmail.com');
        $response->assertStatus(200);
        $response->assertSee('Account Request Rejected');
        $response->assertSee('Insufficient credentials.');
    }

    /** @test */
    public function test_admin_approval_page_can_be_rendered_successfully(): void
    {
        $admin = $this->makeCapsa(CapstoneExpert::create([
            'email' => 'admin.test@gmail.com',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'is_admin' => true,
            'approved_status' => true,
        ]));

        $this->actingAs($admin, 'expert');

        $response = $this->get('/admin/approval');
        $response->assertStatus(200);
        $response->assertSee('Expert Account Approval');
    }

    public function test_instructor_sidebar_shows_real_avatar_photo_not_hardcoded_initials(): void
    {
        $expert = CapstoneExpert::create([
            'email' => 'photo.instructor@gmail.com',
            'first_name' => 'Photo',
            'last_name' => 'Instructor',
            'is_admin' => false,
            'approved_status' => true,
            'profile_picture' => 'https://lh3.googleusercontent.com/a/instructor-photo=s96-c',
        ]);

        $this->actingAs($expert, 'expert');

        $response = $this->get('/instructor/dashboard');
        $response->assertOk();
        $response->assertSee('https://lh3.googleusercontent.com/a/instructor-photo=s96-c', false);
    }

    public function test_admin_sidebar_shows_real_avatar_photo_not_broken_preset_url(): void
    {
        $admin = CapstoneExpert::create([
            'email' => 'photo.admin@gmail.com',
            'first_name' => 'Photo',
            'last_name' => 'Admin',
            'is_admin' => true,
            'approved_status' => true,
            'profile_picture' => 'https://lh3.googleusercontent.com/a/admin-photo=s96-c',
        ]);

        $this->actingAs($admin, 'expert');

        $response = $this->get('/admin/dashboard');
        $response->assertOk();
        $response->assertSee('https://lh3.googleusercontent.com/a/admin-photo=s96-c', false);
        $response->assertDontSee('images/avatars/https://', false);
    }
}
