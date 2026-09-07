<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentRoster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Show the sign-in page.
     */
    public function login()
    {
        return view('auth.login', [
            'removedExpert' => request()->has('removed_expert'),
            'removedExpertName' => request('name', ''),
            'removedExpertReason' => request('reason', ''),
        ]);
    }

    /**
     * Initiate Google Login with reCAPTCHA verification.
     */
    public function initiateGoogleLogin(Request $request): JsonResponse
    {
        $recaptchaToken = $request->input('g-recaptcha-response');
        if (! $recaptchaToken) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete the reCAPTCHA verification.',
            ], 422);
        }

        $http = Http::asForm();
        if (app()->environment('local')) {
            $http = $http->withOptions(['verify' => false]);
        }
        $response = $http->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('services.recaptcha.secret_key'),
            'response' => $recaptchaToken,
            'remoteip' => $request->ip(),
        ]);
        logger()->info('reCAPTCHA response', $response->json());
        $errorCodes = $response->json('error-codes', []);
        if (! empty($errorCodes)) {
            logger()->warning('reCAPTCHA error codes', $errorCodes);
        }

        if (! $response->json('success')) {
            return response()->json([
                'success' => false,
                'message' => 'reCAPTCHA verification failed. Please try again.',
            ], 422);
        }

        $clientId = config('services.google.client_id');

        $state = Str::random(40);
        session(['oauth_state' => $state]);

        if (empty($clientId)) {
            return response()->json([
                'success' => false,
                'message' => 'Google OAuth is not configured. Please set GOOGLE_CLIENT_ID in .env.',
            ], 500);
        }

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => config('services.google.redirect_uri'),
            'response_type' => 'code',
            // Added Drive scope (drive.file = access only to files the app creates/opens,
            // safer than full 'drive' scope) alongside the existing calendar/profile scopes.
            'scope' => 'openid email profile https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/drive.file',
            'state' => $state,
            // access_type=offline (without forcing prompt=consent) still gets
            // a refresh_token on first-ever authorization, and again anytime
            // Google has no valid grant on file (e.g. after a revoke) — it's
            // only omitted on routine repeat logins, where handleGoogleCallback
            // just keeps reusing the previously stored one. Forcing
            // prompt=consent unconditionally used to make every single login
            // show the full permissions screen again, which is exactly the
            // "asked to continue and continue" friction this avoids.
            'access_type' => 'offline',
        ]);

        return response()->json([
            'success' => true,
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth?'.$query,
            'state' => $state,
        ]);
    }

    /**
     * Process real Google OAuth callback.
     */
    public function handleGoogleCallback(Request $request)
    {
        $state = $request->input('state');
        if (empty($state) || $state !== session('oauth_state')) {
            return redirect('/login')->withErrors(['message' => 'Invalid session state. Please try again.']);
        }
        session()->forget('oauth_state');

        $code = $request->input('code');
        if (! $code) {
            return redirect('/login')->withErrors(['message' => 'Authentication failed. No authorization code returned from Google.']);
        }

        $http = Http::asForm();
        if (app()->environment('local')) {
            $http = $http->withOptions(['verify' => false]);
        }
        $response = $http->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect_uri'),
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            return redirect('/login')->withErrors(['message' => 'Failed to obtain access token from Google.']);
        }

        $accessToken = $response->json('access_token');

        $httpUserInfo = Http::asForm();
        if (app()->environment('local')) {
            $httpUserInfo = $httpUserInfo->withOptions(['verify' => false]);
        }
        $userInfoResponse = $httpUserInfo->get('https://www.googleapis.com/oauth2/v3/userinfo', [
            'access_token' => $accessToken,
        ]);

        if (! $userInfoResponse->successful()) {
            return redirect('/login')->withErrors(['message' => 'Failed to retrieve user information from Google.']);
        }

        $userInfo = $userInfoResponse->json();
        $email = $userInfo['email'] ?? null;
        $firstName = $userInfo['given_name'] ?? null;
        $lastName = $userInfo['family_name'] ?? null;

        if (! $firstName && ! $lastName) {
            $nameParts = explode(' ', $userInfo['name'] ?? 'Google User', 2);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? $nameParts[0];
        }

        $isStudentDomain = Str::endsWith($email, Student::EMAIL_DOMAIN);
        // '@cspc.edu.ph' (faculty) is a DIFFERENT domain than the student
        // '@my.cspc.edu.ph' subdomain above - Str::endsWith only matches the
        // literal suffix, so there's no overlap risk despite how similar
        // they look. Lets CAPSA-uploaded Adviser/Consultant/Grammarian
        // accounts sign in with their real CSPC email, not just @gmail.com.
        $isExpertDomain = Str::endsWith($email, '@gmail.com') || Str::endsWith($email, '@cspc.edu.ph');

        if (! $isStudentDomain && ! $isExpertDomain) {
            return redirect('/login')->withErrors(['message' => 'Access denied. Use @my.cspc.edu.ph (student) or @cspc.edu.ph/@gmail.com (instructor/admin).']);
        }

        if ($isExpertDomain) {
            $expert = CapstoneExpert::withTrashed()->where('email', $email)->first();

            if ($expert && $expert->deactivated_at) {
                return $this->refuseDeactivatedExpert($request);
            }

            if ($expert) {
                if ($expert->approved_status) {
                    // Google only sends refresh_token on first consent (or when
                    // prompt=consent is forced); keep the old one if it's missing
                    // this time rather than overwriting a working connection with
                    // null (see the same fix in GoogleDriveService::storeToken).
                    $existingToken = json_decode($expert->google_token ?? '', true);
                    $refreshToken = $response->json('refresh_token') ?? ($existingToken['refresh_token'] ?? null);

                    // Whoever added them typed a name from what they knew,
                    // which is a guess until the person themselves turns up.
                    // Google is holding the name they actually go by, and
                    // they cannot edit it here - the profile panel shows it
                    // locked - so their own account is the only source of it
                    // there is. Taken on every sign-in, like the photo beside
                    // it, so a name corrected on Google arrives here too.
                    $fromGoogle = [];

                    if (filled($userInfo['given_name'] ?? null)) {
                        $fromGoogle['first_name'] = $userInfo['given_name'];
                    }

                    if (filled($userInfo['family_name'] ?? null)) {
                        $fromGoogle['last_name'] = $userInfo['family_name'];
                    }

                    $expert->update($fromGoogle + [
                        'google_token' => json_encode([
                            'access_token' => $accessToken,
                            'refresh_token' => $refreshToken,
                            'token_type' => $response->json('token_type'),
                        ]),
                        'google_token_expires_at' => now()->addSeconds($response->json('expires_in', 3600)),
                        'profile_picture' => $userInfo['picture'] ?? $expert->profile_picture,
                    ]);

                    // Reuse this same login token as the Drive connection —
                    // it already carries the drive.file scope, so most
                    // experts never need the separate "Connect Google Drive"
                    // step at all. Never let a hiccup here block login.
                    try {
                        app(\App\Services\GoogleDriveService::class)->storeToken($expert->expert_id, [
                            'access_token' => $accessToken,
                            'refresh_token' => $response->json('refresh_token'),
                            'expires_in' => $response->json('expires_in', 3600),
                            'token_type' => $response->json('token_type'),
                        ]);
                    } catch (\Exception $e) {
                        logger()->warning('Could not store Drive token from login', [
                            'expert_id' => $expert->expert_id,
                            'message' => $e->getMessage(),
                        ]);
                    }

                    Auth::guard('expert')->login($expert);

                    $expert->markFirstLogin();

                    $redirectUrl = match (true) {
                        // Admin and CaPSA each have their own copy of the
                        // dashboard, guarded by their own role - sending an
                        // Admin to the CaPSA one is a 403 at the door.
                        (bool) $expert->is_admin => '/admin/dashboard',
                        (bool) $expert->is_capsa => '/capsa/dashboard',
                        default => '/instructor/dashboard',
                    };

                    return redirect($redirectUrl);
                }

                $status = $expert->status;

                if ($status === 'rejected') {
                    $attempts = (int) $expert->reject_attempts;
                    $cooldownUntil = $expert->reject_cooldown_until;

                    if ($attempts >= 3 && $cooldownUntil && now()->lt($cooldownUntil)) {
                        return redirect('/request-account?email='.urlencode($email).'&blocked=cooldown');
                    }

                    return redirect('/login/rejected?email='.urlencode($email));
                }

                return view('auth.pending-approval', ['accountRequest' => $expert]);
            }

            // Applying for an account is switched off - see the commented
            // routes in routes/web.php. A CaPSA puts faculty in, either on
            // the uploaded list or one at a time with Add Expert, so an
            // address nobody has listed has nowhere to go but back.
            return $this->refuseUnlistedExpert($request);
        }

        // Who may sign in, and what their account starts as, are the same
        // two questions in both sign-in paths - see studentSigningIn().
        $student = $this->studentSigningIn($email, $firstName, $lastName, $userInfo['picture'] ?? null);

        if (! $student) {
            return $this->refuseUnlistedStudent($request);
        }

        Auth::login($student);

        // Keep the avatar in sync with their Google account on every login,
        // not just at creation — picks up photo changes and backfills
        // accounts created before this was tracked.
        $student->update([
            'last_seen_at' => now(),
            'p_picture' => $userInfo['picture'] ?? $student->p_picture,
        ]);

        // Personal-info is no longer forced on new sign-ins (per the
        // panel's request) - straight to the dashboard regardless of
        // profile completeness. The page is still reachable any time from
        // the student's own profile settings.
        $redirectUrl = route('student.dashboard');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'status' => 'authenticated',
                'redirect_url' => $redirectUrl,
                'user' => [
                    'id' => $student->student_id,
                    'email' => $student->email,
                    'name' => $student->f_name.' '.$student->l_name,
                    'profile_complete' => $student->hasCompletedProfile(),
                ],
            ]);
        }

        return redirect($redirectUrl);
    }

    /**
     * Show mock login screen (Local Development Only).
     */
    public function showMockLogin(Request $request): JsonResponse
    {
        if (! app()->environment('local', 'testing')) {
            abort(403, 'Mock login is only available in local development environment.');
        }

        $state = $request->input('state');
        if (empty($state) || $state !== session('oauth_state')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid session state. Please try again.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'state' => $state,
            'message' => 'Mock login endpoint available. Use handleMockLogin to authenticate.',
        ]);
    }

    /**
     * Process mock login form submission (Local Development Only).
     */
    public function handleMockLogin(Request $request)
    {
        if (! app()->environment('local', 'testing')) {
            abort(403);
        }

        $state = $request->input('state');
        if (empty($state) || $state !== session('oauth_state')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid session state. Please try again.',
            ], 422);
        }
        session()->forget('oauth_state');

        $email = $request->input('email');
        $fullName = $request->input('name', 'Mock User');

        $isStudent = Str::endsWith($email, Student::EMAIL_DOMAIN);
        $isExpert = Str::endsWith($email, '@gmail.com');

        if (! $isStudent && ! $isExpert) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Use @my.cspc.edu.ph (student) or @gmail.com (instructor).',
            ], 403);
        }

        $nameParts = explode(' ', $fullName, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? $nameParts[0];

        if ($isExpert) {
            $expert = CapstoneExpert::withTrashed()->where('email', $email)->first();

            if ($expert && $expert->deactivated_at) {
                return $this->refuseDeactivatedExpert($request);
            }

            if ($expert) {
                if ($expert->approved_status) {
                    Auth::guard('expert')->login($expert);

                    $expert->markFirstLogin();

                    $redirectUrl = match (true) {
                        // Admin and CaPSA each have their own copy of the
                        // dashboard, guarded by their own role - sending an
                        // Admin to the CaPSA one is a 403 at the door.
                        (bool) $expert->is_admin => '/admin/dashboard',
                        (bool) $expert->is_capsa => '/capsa/dashboard',
                        default => '/instructor/dashboard',
                    };

                    if ($request->expectsJson()) {
                        return response()->json([
                            'success' => true,
                            'status' => 'authenticated',
                            'redirect_url' => $redirectUrl,
                            'user' => [
                                'id' => $expert->expert_id,
                                'email' => $expert->email,
                                'name' => $expert->first_name.' '.$expert->last_name,
                            ],
                        ]);
                    }

                    return redirect($redirectUrl);
                }

                $status = $expert->status;

                if ($status === 'rejected') {
                    $attempts = (int) $expert->reject_attempts;
                    $cooldownUntil = $expert->reject_cooldown_until;

                    if ($attempts >= 3 && $cooldownUntil && now()->lt($cooldownUntil)) {
                        return redirect('/request-account?email='.urlencode($email).'&blocked=cooldown');
                    }

                    return redirect('/login/rejected?email='.urlencode($email));
                }

                Auth::guard('expert')->login($expert);

                return view('auth.pending-approval', ['accountRequest' => $expert]);
            }

            // Applying for an account is switched off - see the commented
            // routes in routes/web.php. A CaPSA puts faculty in, either on
            // the uploaded list or one at a time with Add Expert, so an
            // address nobody has listed has nowhere to go but back.
            return $this->refuseUnlistedExpert($request);
        }

        $student = $this->studentSigningIn($email, $firstName, $lastName);

        if (! $student) {
            return $this->refuseUnlistedStudent($request);
        }

        Auth::login($student);

        $student->update(['last_seen_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'status' => 'authenticated',
                'redirect_url' => route('student.dashboard'),
                'user' => [
                    'id' => $student->student_id,
                    'email' => $student->email,
                    'name' => $student->f_name.' '.$student->l_name,
                    'profile_complete' => $student->hasCompletedProfile(),
                ],
            ]);
        }

        return redirect()->route('student.dashboard');
    }

    /**
     * Log the user out and invalidate the session.
     */
    /**
     * The account this student signs in to, or null if nobody has listed them.
     *
     * CaPSA uploads the section's list before its students may have accounts,
     * so being on it is what makes somebody a student here. Anyone who
     * already has an account keeps it - they were let in before this rule
     * existed, and taking that away would lock out the students who have been
     * using the site all along.
     *
     * A list uploaded before their first sign-in already says who they are,
     * so the account arrives filled in rather than empty. This is the only
     * place that decides either of those things: the real Google callback and
     * the local mock both come through here, so what happens on a developer's
     * machine is what happens in front of a student.
     */
    private function studentSigningIn(string $email, string $firstName, string $lastName, ?string $picture = null): ?Student
    {
        // The class list is the whole of the answer. A student is on it
        // because a CaPSA put them there, and taking them off it is how
        // access is withdrawn - it is the only removal the system has.
        //
        // This used to also admit anyone who already had a students row,
        // which made an account permanent once created: removing somebody
        // from their section deletes their roster row, and they signed
        // straight back in on the account instead. Nothing revoked
        // anything. Their account is still kept on removal - work and
        // history hang off it - it just stops being a way in.
        //
        // Matched without regard to case, because Google hands back the
        // address as the person typed it and a capital letter was enough
        // to miss the list.
        $roster = StudentRoster::whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();

        if (! $roster) {
            return null;
        }

        $existing = Student::whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();

        if ($existing) {
            return $existing;
        }

        return Student::firstOrCreate(
            ['email' => $email],
            [
                'f_name' => $firstName,
                'l_name' => $lastName,
                'p_picture' => $picture,
                'student_number' => $roster->student_number ?? null,
                // The list stores the program code; everywhere else stores the
                // course's full name, and a mix of the two means two students
                // on the same course never match each other.
                'course' => isset($roster->program)
                    ? (Student::PROGRAM_COURSES[$roster->program] ?? $roster->program)
                    : null,
                'year_section' => $roster->section ?? null,
            ]
        );
    }

    /**
     * Turn away a faculty member nobody has put in the system.
     *
     * They used to be sent to a form to apply, which a CaPSA then approved.
     * That path is switched off: a CaPSA adds faculty herself, on the list or
     * one at a time, so an account is only ever made by somebody who already
     * knows who this person is.
     *
     * The message names the way in, because the person reading it cannot fix
     * this themselves and "access denied" would leave them checking their own
     * address for a typo that is not there.
     */
    private function refuseUnlistedExpert(Request $request)
    {
        // Two ways in, and the one they can act on themselves comes first.
        // Being told to go and ask somebody is a dead end at nine at night.
        $message = 'There is no account for this address yet. Request one below, or ask '
            .'a CaPSA to add you to the expert list.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'request_url' => route('account.request'),
            ], 403);
        }

        // The address is carried over so they are not made to type it twice,
        // and so the form can tell them where a previous request got to.
        return redirect()->route('account.request', ['email' => $request->input('email')])
            ->withErrors(['message' => $message]);
    }

    /**
     * Turn away someone whose account has been switched off.
     *
     * Distinct from refuseUnlistedExpert() on purpose: that one sends people
     * to the request form, and telling somebody to apply for the account they
     * already have - and were deliberately removed from - is worse than
     * telling them nothing.
     */
    private function refuseDeactivatedExpert(Request $request)
    {
        $message = 'This account has been deactivated. Ask a CaPSA to switch it back on.';

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        return redirect('/login')->withErrors(['message' => $message]);
    }

    /**
     * Turn away a student who is not on any uploaded list.
     *
     * CaPSA asked that a section's list be uploaded before its students can
     * have accounts, so that nobody arrives with a profile the department
     * never put there. The message names the way out, because the student
     * cannot fix this themselves and "access denied" would leave them
     * guessing at which of their details was wrong.
     */
    private function refuseUnlistedStudent(Request $request)
    {
        $message = "Your account is not on your section's list yet. Ask your CaPSA to upload "
            .'the student list for your section, then sign in again.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 403);
        }

        return redirect('/login')->withErrors(['message' => $message]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'status' => 'logged_out',
                'message' => 'Logged out successfully.',
            ])->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => 'Sat, 01 Jan 1990 00:00:00 GMT',
            ]);
        }

        return redirect('/');
    }

    public function rejected(Request $request)
    {
        $email = $request->query('email');

        if (! $email) {
            return redirect('/login');
        }

        $expert = CapstoneExpert::where('email', $email)->first();

        return view('instructor.rejected', [
            'accountRequest' => $expert,
            'email' => $email,
        ]);
    }
}
