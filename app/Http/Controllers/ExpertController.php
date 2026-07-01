<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExpertController extends Controller
{
    public function login()
    {
        return redirect('/');
    }

    public function showRequestForm(Request $request)
    {
        $email = $request->query('email');
        $capstoneExpert = null;
        $cooldownSeconds = null;

        if ($email) {
            $capstoneExpert = CapstoneExpert::where('email', $email)->first();

            if ($capstoneExpert && $capstoneExpert->status === 'rejected') {
                $attempts = (int) $capstoneExpert->reject_attempts;

                if ($attempts >= 3 && $capstoneExpert->reject_cooldown_until && now()->lt($capstoneExpert->reject_cooldown_until)) {
                    $cooldownSeconds = now()->diffInSeconds($capstoneExpert->reject_cooldown_until);
                }
            }
        }

        return view('auth.request-account', [
            'accountRequest' => $capstoneExpert,
            'cooldownSeconds' => $cooldownSeconds,
        ]);
    }

    public function storeRequest(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'first_name' => ['required', 'string', 'max:50'],
                'last_name' => ['required', 'string', 'max:50'],
                'middle_name' => ['nullable', 'string', 'max:50'],
                'credentials' => ['nullable', 'string', 'max:50'],
                'email' => ['required', 'email', 'regex:/@gmail\\.com$/i'],
                'specialization' => ['required', 'array', 'min:1'],
                'specialization.*' => ['string', 'in:adviser,consultant,grammarian,capstone_adviser'],
                'expertise' => ['required', 'array', 'min:1'],
                'expertise.*' => ['string', 'max:100'],
                'max_teams' => ['nullable', 'integer', 'min:1', 'max:10'],
                'course' => ['nullable', 'string', 'max:100'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        $existing = CapstoneExpert::where('email', $validated['email'])->first();

        if ($existing) {
            if ($existing->approved_status) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has already been approved. Please sign in instead.',
                ], 409);
            }

            if ($existing->status === 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a pending account request. Please wait for the admin to review your submission.',
                ], 409);
            }

            if ((int) $existing->reject_attempts >= 3) {
                $cooldownUntil = $existing->reject_cooldown_until;
                if ($cooldownUntil && now()->lt($cooldownUntil)) {
                    $waitSeconds = now()->diffInSeconds($cooldownUntil);

                    return response()->json([
                        'success' => false,
                        'message' => "Submission temporarily blocked due to multiple rejections. Please try again in {$waitSeconds} seconds.",
                    ], 429);
                }

                DB::transaction(function () use ($existing, $validated) {
                    DB::table('capstone_experts')->where('expert_id', $existing->expert_id)->update([
                        'first_name' => $validated['first_name'],
                        'last_name' => $validated['last_name'],
                        'middle_name' => $validated['middle_name'] ?? null,
                        'credentials' => $validated['credentials'] ?? null,
                        'department' => $validated['course'] ?? null,
                        'approved_status' => false,
                        'rejection_reason' => null,
                        'reject_attempts' => 0,
                        'reject_cooldown_until' => null,
                        'max_teams' => $validated['max_teams'] ?? 4,
                    ]);

                    DB::table('expert_roles')->where('expert_id', $existing->expert_id)->delete();
                    foreach ($validated['specialization'] as $roleName) {
                        $roleId = DB::table('roles')->where('role_name', $roleName)->value('role_id');
                        if ($roleId) {
                            DB::table('expert_roles')->insert([
                                'expert_id' => $existing->expert_id,
                                'role_id' => $roleId,
                            ]);
                        }
                    }

                    DB::table('expert_expertise')->where('expert_id', $existing->expert_id)->delete();
                    foreach ($validated['expertise'] as $field) {
                        if (is_string($field) && trim($field) !== '') {
                            DB::table('expert_expertise')->insert([
                                'expert_id' => $existing->expert_id,
                                'expertise_field' => $field,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                });

                return response()->json([
                    'success' => true,
                    'message' => 'Your account request has been re-submitted successfully.',
                ]);
            }

            DB::transaction(function () use ($existing, $validated) {
                DB::table('capstone_experts')->where('expert_id', $existing->expert_id)->update([
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'middle_name' => $validated['middle_name'] ?? null,
                    'credentials' => $validated['credentials'] ?? null,
                    'department' => $validated['course'] ?? null,
                    'approved_status' => false,
                    'rejection_reason' => null,
                    'reject_attempts' => DB::raw('reject_attempts + 1'),
                    'max_teams' => $validated['max_teams'] ?? 4,
                ]);

                DB::table('expert_roles')->where('expert_id', $existing->expert_id)->delete();
                foreach ($validated['specialization'] as $roleName) {
                    $roleId = DB::table('roles')->where('role_name', $roleName)->value('role_id');
                    if ($roleId) {
                        DB::table('expert_roles')->insert([
                            'expert_id' => $existing->expert_id,
                            'role_id' => $roleId,
                        ]);
                    }
                }

                DB::table('expert_expertise')->where('expert_id', $existing->expert_id)->delete();
                foreach ($validated['expertise'] as $field) {
                    if (is_string($field) && trim($field) !== '') {
                        DB::table('expert_expertise')->insert([
                            'expert_id' => $existing->expert_id,
                            'expertise_field' => $field,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Your account request has been re-submitted successfully.',
            ]);
        }

        try {
            DB::transaction(function () use ($validated) {
                $expertId = DB::table('capstone_experts')->insertGetId([
                    'email' => $validated['email'],
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'middle_name' => $validated['middle_name'] ?? null,
                    'credentials' => $validated['credentials'] ?? null,
                    'department' => $validated['course'] ?? null,
                    'approved_status' => false,
                    'max_teams' => $validated['max_teams'] ?? 4,
                    'reject_attempts' => 1,
                    'is_admin' => false,
                    'created_at' => now(),
                ]);

                foreach ($validated['specialization'] as $roleName) {
                    $roleId = DB::table('roles')->where('role_name', $roleName)->value('role_id');
                    if ($roleId) {
                        DB::table('expert_roles')->insert([
                            'expert_id' => $expertId,
                            'role_id' => $roleId,
                        ]);
                    }
                }

                foreach ($validated['expertise'] as $field) {
                    if (is_string($field) && trim($field) !== '') {
                        DB::table('expert_expertise')->insert([
                            'expert_id' => $expertId,
                            'expertise_field' => $field,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Account request submitted successfully.',
            ]);
        } catch (\Throwable $e) {
            logger()->error('Account request DB error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error: '.$e->getMessage(),
            ], 500);
        }
    }

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
            'scope' => 'openid email profile',
            'state' => $state,
            'hd' => 'gmail.com',
            'prompt' => 'select_account',
        ]);

        return response()->json([
            'success' => true,
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth?'.$query,
            'state' => $state,
        ]);
    }

    public function handleGoogleCallback(Request $request)
    {
        $state = $request->input('state');
        if (empty($state) || $state !== session('oauth_state')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid session state. Please try again.',
            ], 422);
        }
        session()->forget('oauth_state');

        $code = $request->input('code');
        if (! $code) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed. No authorization code returned from Google.',
            ], 422);
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
            return response()->json([
                'success' => false,
                'message' => 'Failed to obtain access token from Google.',
            ], 422);
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
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user information from Google.',
            ], 422);
        }

        $userInfo = $userInfoResponse->json();
        $email = $userInfo['email'] ?? null;
        $firstName = $userInfo['given_name'] ?? null;
        $lastName = $userInfo['family_name'] ?? null;

        if (! $firstName && ! $lastName) {
            $nameParts = explode(' ', $userInfo['name'] ?? 'Instructor User', 2);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? $nameParts[0];
        }

        if (! $email || ! Str::endsWith($email, '@gmail.com')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only Gmail accounts (@gmail.com) are allowed.',
            ], 403);
        }

        $expert = CapstoneExpert::where('email', $email)->first();

        if ($expert) {
            if ($expert->approved_status) {
                $expert->update([
                    'google_token' => json_encode([
                        'access_token' => $accessToken,
                        'refresh_token' => $response->json('refresh_token'),
                        'token_type' => $response->json('token_type'),
                    ]),
                    'google_token_expires_at' => now()->addSeconds($response->json('expires_in', 3600)),
                ]);

                Auth::guard('expert')->login($expert);

                $redirectUrl = $expert->is_admin ? '/admin/dashboard' : '/instructor/dashboard';

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

            return view('auth.pending-approval', ['accountRequest' => $expert]);
        }

        return redirect('/request-account?email='.urlencode($email).'&first_name='.urlencode($firstName).'&last_name='.urlencode($lastName));
    }

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

        $email = $request->input('email', 'instructor.test@gmail.com');
        $fullName = 'Instructor Test';

        if (! $email || ! Str::endsWith($email, '@gmail.com')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only Gmail accounts (@gmail.com) are allowed.',
            ], 403);
        }

        $nameParts = explode(' ', $fullName, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? $nameParts[0];

        $expert = CapstoneExpert::where('email', $email)->first();

        if ($expert) {
            if ($expert->approved_status) {
                Auth::guard('expert')->login($expert);

                $redirectUrl = $expert->is_admin ? '/admin/dashboard' : '/instructor/dashboard';

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

            return view('auth.pending-approval', ['accountRequest' => $expert]);
        }

        return redirect('/request-account?email='.urlencode($email).'&first_name='.urlencode($firstName).'&last_name='.urlencode($lastName));
    }

    public function logout(Request $request)
    {
        Auth::guard('expert')->logout();
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
}
