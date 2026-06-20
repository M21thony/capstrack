<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function login_route_returns_method_not_allowed(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(405);
        $response->assertJson([
            'success' => false,
            'message' => 'Use initiateGoogleLogin to start authentication.',
        ]);
    }

    /** @test */
    public function initiate_google_login_requires_recaptcha_token(): void
    {
        $response = $this->postJson('/login/google', [
            'g-recaptcha-response' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Please complete the reCAPTCHA verification.',
        ]);
    }

    /** @test */
    public function initiate_google_login_fails_when_recaptcha_verification_fails(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false]),
        ]);

        $response = $this->postJson('/login/google', [
            'g-recaptcha-response' => 'invalid-token',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'reCAPTCHA verification failed. Please try again.',
        ]);
    }

    /** @test */
    public function initiate_google_login_returns_auth_url_on_success(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        ]);

        $response = $this->postJson('/login/google', [
            'g-recaptcha-response' => 'valid-token',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonStructure([
            'success',
            'auth_url',
            'state',
        ]);
        $this->assertStringContainsString('accounts.google.com', $response->json('auth_url'));
    }

    /** @test */
    public function mock_login_get_requires_valid_state(): void
    {
        $response = $this->getJson('/login/google/mock?state=invalid-state');

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid session state. Please try again.',
        ]);
    }

    /** @test */
    public function mock_login_get_returns_info_with_valid_state(): void
    {
        $state = 'test-state-token';
        $this->withSession(['oauth_state' => $state]);

        $response = $this->getJson("/login/google/mock?state={$state}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'state' => $state,
        ]);
    }

    /** @test */
    public function mock_login_denies_non_institutional_email(): void
    {
        $state = 'test-state-token';
        $this->withSession(['oauth_state' => $state]);

        $response = $this->postJson('/login/google/mock', [
            'state' => $state,
            'name' => 'John Doe',
            'email' => 'john.doe@gmail.com',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertFalse(Auth::check());
    }

    /** @test */
    public function mock_login_allows_institutional_email_and_logs_user_in(): void
    {
        $state = 'test-state-token';
        $this->withSession(['oauth_state' => $state]);

        $response = $this->postJson('/login/google/mock', [
            'state' => $state,
            'name' => 'Jose Rizal',
            'email' => 'jose.rizal@my.cspc.edu.ph',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'authenticated',
            'redirect_url' => route('student.personal-info'),
        ]);
        $this->assertTrue(Auth::check());

        $student = Auth::user();
        $this->assertEquals('jose.rizal@my.cspc.edu.ph', $student->email);
        $this->assertEquals('Jose Rizal', $student->f_name.' '.$student->l_name);

        $this->assertDatabaseHas('students', [
            'email' => 'jose.rizal@my.cspc.edu.ph',
        ]);
    }
}
