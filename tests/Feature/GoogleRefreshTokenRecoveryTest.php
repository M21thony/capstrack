<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\GoogleDriveToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Getting an account's offline access back once it has none.
 *
 * Google issues a refresh token on an account's FIRST consent and never
 * again. The grant lives on the Google account, not in this database, so an
 * expert whose row was deleted and remade signs back in and gets an access
 * token with no refresh token behind it - Google sees nothing new to
 * consent to. An hour later the access token expires, nothing can renew it,
 * and Drive and Calendar sync stop with no way back through the login.
 */
class GoogleRefreshTokenRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function expert(): CapstoneExpert
    {
        return CapstoneExpert::create([
            'email' => 'expert@gmail.com',
            'first_name' => 'Grom',
            'last_name' => 'Hellscream',
            'approved_status' => true,
        ]);
    }

    /**
     * What Google hands back, one entry per sign-in attempt.
     *
     * A sequence rather than repeated Http::fake() calls - a second fake
     * does not replace the first, so the earliest matching stub keeps
     * answering and the consent round trip cannot be told apart from the
     * attempt before it.
     *
     * @param  array<int, array<string, mixed>>  $tokens
     */
    private function googleReturns(array ...$tokens): void
    {
        $sequence = Http::sequence();

        foreach ($tokens as $token) {
            $sequence->push($token + [
                'access_token' => 'access-1',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                // An expert account that has already given Calendar and Drive.
                'scope' => 'openid https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/drive.file',
            ]);
        }

        Http::fake([
            'oauth2.googleapis.com/token' => $sequence,
            'www.googleapis.com/oauth2/v3/userinfo*' => Http::response([
                'email' => 'expert@gmail.com',
                'given_name' => 'Grom',
                'family_name' => 'Hellscream',
            ]),
        ]);
    }

    private function signIn(string $state): TestResponse
    {
        return $this->withSession(['oauth_state' => $state])
            ->get("/login/google/callback?state={$state}&code=fake-code");
    }

    public function test_an_account_with_no_refresh_token_is_sent_back_for_consent(): void
    {
        $this->expert();
        $this->googleReturns([]); // Google sends no refresh_token.

        $response = $this->signIn('state-1');

        $response->assertRedirectContains('accounts.google.com');
        $response->assertRedirectContains('prompt=consent');

        // Signing in is not finished until offline access is settled.
        $this->assertGuest('expert');
    }

    public function test_the_consent_round_trip_stores_the_refresh_token_in_both_places(): void
    {
        $expert = $this->expert();

        // Nothing the first time; the consent screen's answer carries one.
        $this->googleReturns([], ['refresh_token' => 'refresh-1']);

        $this->signIn('state-1')->assertRedirectContains('prompt=consent');
        $this->signIn(session('oauth_state'))->assertRedirect('/instructor/dashboard');

        $this->assertSame(
            'refresh-1',
            json_decode($expert->fresh()->google_token, true)['refresh_token']
        );
        $this->assertSame(
            'refresh-1',
            GoogleDriveToken::where('user_id', $expert->expert_id)->value('refresh_token')
        );
    }

    /** Asked for twice in a row would be a loop, so it is asked for once. */
    public function test_it_does_not_bounce_a_second_time_if_google_still_sends_none(): void
    {
        $this->expert();

        $this->googleReturns([], []);

        $this->signIn('state-1')->assertRedirectContains('prompt=consent');
        $this->signIn(session('oauth_state'))->assertRedirect('/instructor/dashboard');
    }

    /**
     * The Drive connection is the same grant stored a second time, and the
     * dedicated connect flow forces consent - so it can be holding a refresh
     * token when the expert row is not. That is not a reason to ask again.
     */
    public function test_a_refresh_token_in_the_drive_store_repairs_the_expert_row(): void
    {
        $expert = $this->expert();

        GoogleDriveToken::create([
            'user_id' => $expert->expert_id,
            'access_token' => json_encode(['access_token' => 'old']),
            'refresh_token' => 'refresh-from-drive',
            'expires_at' => now()->addHour(),
        ]);

        $this->googleReturns([]);
        $this->signIn('state-1')->assertRedirect('/instructor/dashboard');

        $this->assertSame(
            'refresh-from-drive',
            json_decode($expert->fresh()->google_token, true)['refresh_token']
        );
    }

    /** A routine repeat login keeps the one it has and shows no consent screen. */
    public function test_a_repeat_login_reuses_the_stored_refresh_token(): void
    {
        $expert = $this->expert();
        $expert->update(['google_token' => json_encode([
            'access_token' => 'old', 'refresh_token' => 'refresh-kept', 'token_type' => 'Bearer',
        ])]);

        $this->googleReturns([]);
        $this->signIn('state-1')->assertRedirect('/instructor/dashboard');

        $this->assertSame(
            'refresh-kept',
            json_decode($expert->fresh()->google_token, true)['refresh_token']
        );
    }
}
