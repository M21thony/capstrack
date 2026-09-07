<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_google_callback_captures_avatar_from_google_account(): void
    {
        $state = 'real-state-token';
        $this->withSession(['oauth_state' => $state]);

        // A student's section has to be on an uploaded list before they can
        // sign in (see StudentSignupGateTest). This test is about what the
        // callback does with Google's answer, so put them on one.
        StudentRoster::create([
            'email' => 'juan.delacruz@my.cspc.edu.ph',
            'name' => 'Dela Cruz, Juan',
            'section' => '4th '.json_decode('"\u00b7"').' B',
            'program' => 'BSIT',
            'school_year' => '2026-2027',
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'fake-access-token',
                'refresh_token' => 'fake-refresh-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'www.googleapis.com/oauth2/v3/userinfo*' => Http::response([
                'email' => 'juan.delacruz@my.cspc.edu.ph',
                'given_name' => 'Juan',
                'family_name' => 'Dela Cruz',
                'picture' => 'https://lh3.googleusercontent.com/a/fake-photo=s96-c',
            ]),
        ]);

        $this->get("/login/google/callback?state={$state}&code=fake-code")
            ->assertRedirect(route('student.dashboard'));

        $student = Student::where('email', 'juan.delacruz@my.cspc.edu.ph')->first();
        $this->assertNotNull($student);
        $this->assertEquals('https://lh3.googleusercontent.com/a/fake-photo=s96-c', $student->p_picture);
        $this->assertEquals('https://lh3.googleusercontent.com/a/fake-photo=s96-c', $student->avatarUrl());
    }

    public function test_real_google_callback_refreshes_avatar_on_repeat_login(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'returning.student@my.cspc.edu.ph',
            'p_picture' => 'https://lh3.googleusercontent.com/a/old-photo=s96-c',
        ]);

        // Being listed is what admits them - an account on its own is not a
        // way in any more. This test is about what the callback does with
        // Google's answer, so put them on the list like its sibling above.
        StudentRoster::create([
            'email' => 'returning.student@my.cspc.edu.ph',
            'name' => 'Returning Student',
            'section' => '4th '.json_decode('"·"').' B',
            'program' => 'BSIT',
            'school_year' => '2026-2027',
        ]);

        $state = 'repeat-login-state';
        $this->withSession(['oauth_state' => $state]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'fake-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'www.googleapis.com/oauth2/v3/userinfo*' => Http::response([
                'email' => 'returning.student@my.cspc.edu.ph',
                'given_name' => 'Returning',
                'family_name' => 'Student',
                'picture' => 'https://lh3.googleusercontent.com/a/new-photo=s96-c',
            ]),
        ]);

        $this->get("/login/google/callback?state={$state}&code=fake-code")
            ->assertRedirect(route('student.dashboard'));

        $this->assertEquals(
            'https://lh3.googleusercontent.com/a/new-photo=s96-c',
            $student->fresh()->p_picture
        );
    }

    public function test_real_google_callback_backfills_null_avatar_for_existing_expert(): void
    {
        $expert = CapstoneExpert::factory()->create([
            'email' => 'existing.expert@gmail.com',
            'approved_status' => true,
            'profile_picture' => null,
        ]);

        $state = 'expert-backfill-state';
        $this->withSession(['oauth_state' => $state]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'fake-access-token',
                'refresh_token' => 'fake-refresh-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'www.googleapis.com/oauth2/v3/userinfo*' => Http::response([
                'email' => 'existing.expert@gmail.com',
                'given_name' => 'Existing',
                'family_name' => 'Expert',
                'picture' => 'https://lh3.googleusercontent.com/a/expert-photo=s96-c',
            ]),
        ]);

        $this->get("/login/google/callback?state={$state}&code=fake-code")
            ->assertRedirect('/instructor/dashboard');

        $expert->refresh();
        $this->assertEquals('https://lh3.googleusercontent.com/a/expert-photo=s96-c', $expert->profile_picture);
        $this->assertEquals('https://lh3.googleusercontent.com/a/expert-photo=s96-c', $expert->avatarUrl());
    }

    public function test_login_route_renders_the_sign_in_page(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Sign in to CapsTrack');
    }

    public function test_initiate_google_login_requires_recaptcha_token(): void
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

    public function test_initiate_google_login_fails_when_recaptcha_verification_fails(): void
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

    public function test_initiate_google_login_returns_auth_url_on_success(): void
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

    public function test_mock_login_get_requires_valid_state(): void
    {
        $response = $this->getJson('/login/google/mock?state=invalid-state');

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid session state. Please try again.',
        ]);
    }

    public function test_mock_login_get_returns_info_with_valid_state(): void
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

    /**
     * A faculty address nobody has put in the system is turned away.
     *
     * It used to be sent to a form to apply. A CaPSA adds faculty now - on
     * the list, or one at a time - so there is nothing to apply to.
     */
    public function test_mock_login_turns_away_an_unlisted_faculty_address(): void
    {
        $state = 'test-state-token';
        $this->withSession(['oauth_state' => $state]);

        $response = $this->postJson('/login/google/mock', [
            'state' => $state,
            'name' => 'John Doe',
            'email' => 'john.doe@gmail.com',
        ]);

        $response->assertStatus(403);
        $this->assertStringContainsString('CaPSA', $response->json('message'));
        $this->assertFalse(Auth::check());

        // Nothing was created on the way out.
        $this->assertNull(CapstoneExpert::where('email', 'john.doe@gmail.com')->first());
    }

    public function test_mock_login_denies_non_institutional_non_gmail_email(): void
    {
        $state = 'test-state-token';
        $this->withSession(['oauth_state' => $state]);

        $response = $this->postJson('/login/google/mock', [
            'state' => $state,
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertFalse(Auth::check());
    }

    public function test_mock_login_allows_institutional_email_and_logs_user_in(): void
    {
        $state = 'test-state-token';
        $this->withSession(['oauth_state' => $state]);

        // On the list, so the gate lets them through - what this test is
        // about is that an institutional address is accepted at all.
        StudentRoster::create([
            'email' => 'jose.rizal@my.cspc.edu.ph',
            'name' => 'Rizal, Jose',
            'section' => '4th '.json_decode('"\u00b7"').' B',
            'program' => 'BSIT',
            'school_year' => '2026-2027',
        ]);

        $response = $this->postJson('/login/google/mock', [
            'state' => $state,
            'name' => 'Jose Rizal',
            'email' => 'jose.rizal@my.cspc.edu.ph',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'authenticated',
            'redirect_url' => route('student.dashboard'),
        ]);
        $this->assertTrue(Auth::check());

        $student = Auth::user();
        $this->assertEquals('jose.rizal@my.cspc.edu.ph', $student->email);
        $this->assertEquals('Jose Rizal', $student->f_name.' '.$student->l_name);

        $this->assertDatabaseHas('students', [
            'email' => 'jose.rizal@my.cspc.edu.ph',
        ]);
    }

    /**
     * A typed name is a placeholder; the person's own account is the source.
     *
     * Whoever added an expert typed a name from what they knew. Google is
     * holding the one they actually go by, and they cannot edit it here - the
     * profile panel shows it locked - so signing in is the moment the guess
     * gets corrected.
     */
    public function test_signing_in_corrects_the_name_somebody_typed_for_an_expert(): void
    {
        $expert = CapstoneExpert::create([
            'email' => 'typed.name@gmail.com',
            // what a CaPSA guessed when they added them
            'first_name' => 'Markanthonybalilla21',
            'last_name' => '',
            'approved_status' => true,
        ]);

        $state = 'name-sync-state';
        $this->withSession(['oauth_state' => $state]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'fake-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'www.googleapis.com/oauth2/v3/userinfo*' => Http::response([
                'email' => 'typed.name@gmail.com',
                'given_name' => 'Mark Anthony',
                'family_name' => 'Balilla',
            ]),
        ]);

        $this->get("/login/google/callback?state={$state}&code=fake-code");

        $expert->refresh();

        $this->assertSame('Mark Anthony', $expert->first_name);
        $this->assertSame('Balilla', $expert->last_name);
    }

    /** Google telling us nothing leaves the name that was there. */
    public function test_a_sign_in_without_a_name_from_google_keeps_the_one_on_file(): void
    {
        $expert = CapstoneExpert::create([
            'email' => 'keeps.name@gmail.com',
            'first_name' => 'Rosa',
            'last_name' => 'Villanueva',
            'approved_status' => true,
        ]);

        $state = 'name-keep-state';
        $this->withSession(['oauth_state' => $state]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'fake-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'www.googleapis.com/oauth2/v3/userinfo*' => Http::response([
                'email' => 'keeps.name@gmail.com',
            ]),
        ]);

        $this->get("/login/google/callback?state={$state}&code=fake-code");

        $expert->refresh();

        $this->assertSame('Rosa', $expert->first_name);
        $this->assertSame('Villanueva', $expert->last_name);
    }
}
