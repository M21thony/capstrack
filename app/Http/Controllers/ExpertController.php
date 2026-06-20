<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ExpertController extends Controller
{
    public function login()
    {
        return response()->json([
            'success' => false,
            'message' => 'Use initiateGoogleLogin to start authentication.',
        ], 405);
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

        $expert = CapstoneExpert::firstOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'is_admin' => false,
            ]
        );

        Auth::guard('expert')->login($expert);

        $redirectUrl = '/instructor/dashboard';

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

        $expert = CapstoneExpert::firstOrCreate(
            ['email' => 'instructor.test@gmail.com'],
            [
                'first_name' => 'Instructor',
                'last_name' => 'Test',
                'is_admin' => true,
                'approved_status' => true,
            ]
        );

        if (! $expert->approved_status) {
            Auth::guard('expert')->login($expert);

            return view('auth.pending-approval');
        }

        Auth::guard('expert')->login($expert);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'status' => 'authenticated',
                'redirect_url' => '/instructor/dashboard',
                'user' => [
                    'id' => $expert->expert_id,
                    'email' => $expert->email,
                    'name' => $expert->first_name.' '.$expert->last_name,
                ],
            ]);
        }

        return redirect('/instructor/dashboard');
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
