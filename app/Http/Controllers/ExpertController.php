<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpertController extends Controller
{
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
                'suffix' => ['nullable', 'string', 'max:50'],
                'credentials' => ['nullable', 'string', 'max:50'],
                'email' => ['required', 'email', 'regex:/@gmail\\.com$/i'],
                // Role is no longer picked here - CAPSA assigns it when
                // approving the request (see AdminApprovalController::approve()).
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
                        'suffix' => $validated['suffix'] ?? null,
                        'credentials' => $validated['credentials'] ?? null,
                        'program' => $validated['course'] ?? null,
                        'approved_status' => false,
                        'rejection_reason' => null,
                        'reject_attempts' => 0,
                        'reject_cooldown_until' => null,
                        'max_teams' => $validated['max_teams'] ?? 4,
                    ]);

                    // Role is deliberately left untouched here - a resubmit
                    // after rejection doesn't get to re-pick it either;
                    // CAPSA still assigns it at approval time.

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
                    'suffix' => $validated['suffix'] ?? null,
                    'credentials' => $validated['credentials'] ?? null,
                    'program' => $validated['course'] ?? null,
                    'approved_status' => false,
                    'rejection_reason' => null,
                    'max_teams' => $validated['max_teams'] ?? 4,
                ]);

                // Role is deliberately left untouched here too - see the
                // comment in the branch above.

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
                    'suffix' => $validated['suffix'] ?? null,
                    'credentials' => $validated['credentials'] ?? null,
                    'program' => $validated['course'] ?? null,
                    'approved_status' => false,
                    'max_teams' => $validated['max_teams'] ?? 4,
                    'reject_attempts' => 0,
                    'created_at' => now(),
                ]);

                // No role, no is_admin/is_capsa - a fresh request never sets
                // any of these; CAPSA assigns the role when approving it.

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

    // initiateGoogleLogin()/handleGoogleCallback()/showMockLogin()/
    // handleMockLogin() used to live here too - a second, near-duplicate
    // OAuth implementation nothing in the app ever linked to (the real
    // expert login path is AuthController's). Removed rather than kept in
    // sync, since it had drifted to only accept @gmail.com and would need
    // updating alongside AuthController's domain check forever otherwise.

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
