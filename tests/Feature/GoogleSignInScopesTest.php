<?php

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * What the Google sign-in asks for.
 *
 * Google shows "This app hasn't been verified" to anyone asked for a
 * sensitive scope - Calendar is one - by an app it has not verified, and
 * caps such an app at 100 users. Only experts' accounts are used for
 * Calendar and Drive, so signing in asks everyone only who they are, and an
 * expert's account is asked once more, for Calendar events and Drive.
 */
class GoogleSignInScopesTest extends TestCase
{
    use RefreshDatabase;

    private const SIGN_IN = 'openid https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile';

    private const EXPERT = self::SIGN_IN.' https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/drive.file';

    /** @return array<string, string> */
    private function query(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    /**
     * What Google hands back, one entry per round trip.
     *
     * @param  array<int, string>  $granted
     */
    private function googleGrants(string $email, string ...$granted): void
    {
        $sequence = Http::sequence();

        foreach ($granted as $scope) {
            $sequence->push([
                'access_token' => 'access-1',
                'refresh_token' => 'refresh-1',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'scope' => $scope,
            ]);
        }

        Http::fake([
            'oauth2.googleapis.com/token' => $sequence,
            'www.googleapis.com/oauth2/v3/userinfo*' => Http::response(['email' => $email, 'given_name' => 'Rosa', 'family_name' => 'Villanueva']),
        ]);
    }

    private function returnFromGoogle(string $state): TestResponse
    {
        return $this->withSession(['oauth_state' => $state])->get("/login/google/callback?state={$state}&code=fake-code");
    }

    private function expert(): CapstoneExpert
    {
        return CapstoneExpert::create(['email' => 'rosa.villanueva@gmail.com', 'first_name' => 'Rosa', 'last_name' => 'Villanueva', 'approved_status' => true]);
    }

    public function test_signing_in_asks_only_who_someone_is(): void
    {
        config(['services.google.client_id' => 'capstrack-client']);
        Http::fake(['https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true])]);

        $query = $this->query($this->postJson('/login/google', ['g-recaptcha-response' => 'ok'])->assertOk()->json('auth_url'));

        $this->assertSame('openid email profile', $query['scope']);
        $this->assertSame('true', $query['include_granted_scopes']);
        $this->assertStringNotContainsString('calendar', $query['scope']);
        $this->assertStringNotContainsString('drive', $query['scope']);
    }

    public function test_a_student_is_never_asked_for_calendar_or_drive(): void
    {
        StudentRoster::create([
            'email' => 'juan.delacruz@my.cspc.edu.ph', 'name' => 'Dela Cruz, Juan', 'student_number' => '221001156',
            'section' => '4th · B', 'program' => 'BSIT', 'school_year' => '2026-2027',
        ]);
        Student::factory()->create(['email' => 'juan.delacruz@my.cspc.edu.ph', 'student_number' => '221001156']);
        $this->googleGrants('juan.delacruz@my.cspc.edu.ph', self::SIGN_IN);

        $this->returnFromGoogle('state-1')->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticated('web');
    }

    public function test_an_expert_is_asked_once_for_calendar_events_and_drive(): void
    {
        $this->expert();
        $this->googleGrants('rosa.villanueva@gmail.com', self::SIGN_IN);

        $response = $this->returnFromGoogle('state-1');

        $response->assertRedirectContains('accounts.google.com');
        $query = $this->query($response->headers->get('Location'));
        $this->assertSame('openid email profile '.implode(' ', AuthController::GOOGLE_EXPERT_SCOPES), $query['scope']);
        $this->assertSame('rosa.villanueva@gmail.com', $query['login_hint']);
        $this->assertSame('true', $query['include_granted_scopes']);
        $this->assertGuest('expert');
    }

    public function test_the_expert_signs_in_once_calendar_and_drive_are_given(): void
    {
        $expert = $this->expert();
        $this->googleGrants('rosa.villanueva@gmail.com', self::SIGN_IN, self::EXPERT);

        $this->returnFromGoogle('state-1')->assertRedirectContains('accounts.google.com');
        $this->returnFromGoogle(session('oauth_state'))->assertRedirect('/instructor/dashboard');

        $this->assertAuthenticatedAs($expert, 'expert');
        $this->assertSame('refresh-1', json_decode($expert->fresh()->google_token, true)['refresh_token']);
    }

    /** Asked twice in a row would be a loop: an expert who unticks it still gets in. */
    public function test_an_expert_who_declines_is_not_asked_again_in_a_loop(): void
    {
        $this->expert();
        $this->googleGrants('rosa.villanueva@gmail.com', self::SIGN_IN, self::SIGN_IN);

        $this->returnFromGoogle('state-1')->assertRedirectContains('accounts.google.com');
        $this->returnFromGoogle(session('oauth_state'))->assertRedirect('/instructor/dashboard');
    }

    /** What an account has given before comes back with the sign-in, so a returning expert goes straight in. */
    public function test_a_returning_expert_is_not_asked_again(): void
    {
        $this->expert();
        $this->googleGrants('rosa.villanueva@gmail.com', self::EXPERT);

        $this->returnFromGoogle('state-1')->assertRedirect('/instructor/dashboard');
    }

    /** Calendar given under the old, wider scope covers its events. */
    public function test_an_expert_who_gave_the_whole_calendar_before_is_not_asked_again(): void
    {
        $this->expert();
        $this->googleGrants('rosa.villanueva@gmail.com', self::SIGN_IN.' https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/drive.file');

        $this->returnFromGoogle('state-1')->assertRedirect('/instructor/dashboard');
    }

    /** Only the events are ever asked for, never the whole calendar. */
    public function test_the_whole_calendar_is_never_asked_for(): void
    {
        $this->assertNotContains('https://www.googleapis.com/auth/calendar', AuthController::GOOGLE_EXPERT_SCOPES);
        $this->assertContains('https://www.googleapis.com/auth/calendar.events', AuthController::GOOGLE_EXPERT_SCOPES);
    }
}
