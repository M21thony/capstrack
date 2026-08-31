<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use App\Models\Student;
<<<<<<< HEAD
use App\Models\StudentRoster;
=======
use App\Services\GoogleDriveService;
>>>>>>> updatedTeamManagement
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

        $isStudentDomain = Str::endsWith($email, '@my.cspc.edu.ph');
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
            $expert = CapstoneExpert::where('email', $email)->first();

            if ($expert) {
                if ($expert->approved_status) {
                    // Google only sends refresh_token on first consent (or when
                    // prompt=consent is forced); keep the old one if it's missing
                    // this time rather than overwriting a working connection with
                    // null (see the same fix in GoogleDriveService::storeToken).
                    $existingToken = json_decode($expert->google_token ?? '', true);
                    $refreshToken = $response->json('refresh_token') ?? ($existingToken['refresh_token'] ?? null);

                    $expert->update([
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
                        app(GoogleDriveService::class)->storeToken($expert->expert_id, [
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

                    $redirectUrl = $expert->is_staff ? '/admin/dashboard' : '/instructor/dashboard';

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

            return redirect('/request-account?email='.urlencode($email).'&first_name='.urlencode($firstName).'&last_name='.urlencode($lastName));
        }

        // Students can register and use the site immediately, whether or
        // not CAPSA has uploaded the official roster yet - it's never a
        // gate on account creation or access. If a roster row already
        // exists for this email (CAPSA uploaded it before this student's
        // first login), pre-fill their academic fields from it now; if
        // not, those fields just start null and get filled in later by
        // StudentRosterController's email-match sync whenever CAPSA's
        // roster does arrive - see that controller for the other half of
        // this.
        $roster = StudentRoster::where('email', $email)->first();

        $student = Student::firstOrCreate(
            ['email' => $email],
            [
                'f_name' => $firstName,
                'l_name' => $lastName,
                'p_picture' => $userInfo['picture'] ?? null,
                'student_number' => $roster->student_number ?? null,
                'course' => $roster->program ?? null,
                'year_section' => $roster->section ?? null,
            ]
        );

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

        $isStudent = Str::endsWith($email, '@my.cspc.edu.ph');
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
            $expert = CapstoneExpert::where('email', $email)->first();

            if ($expert) {
                if ($expert->approved_status) {
                    Auth::guard('expert')->login($expert);

                    $expert->markFirstLogin();

                    $redirectUrl = $expert->is_staff ? '/admin/dashboard' : '/instructor/dashboard';

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

            return redirect('/request-account?email='.urlencode($email).'&first_name='.urlencode($firstName).'&last_name='.urlencode($lastName));
        }

        $student = Student::firstOrCreate(
            ['email' => $email],
            [
                'f_name' => $firstName,
                'l_name' => $lastName,
            ]
        );

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
