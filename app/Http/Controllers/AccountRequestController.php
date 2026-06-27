<?php

namespace App\Http\Controllers;

use App\Models\AccountRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountRequestController extends Controller
{
    public function create(Request $request)
    {
        $email = $request->query('email');
        $accountRequest = null;
        $cooldownSeconds = null;

        if ($email) {
            $accountRequest = AccountRequest::where('email', $email)->first();

            if ($accountRequest && $accountRequest->status === 'rejected') {
                $attempts = (int) $accountRequest->reject_attempts;

                if ($attempts >= 3 && $accountRequest->reject_cooldown_until && now()->lt($accountRequest->reject_cooldown_until)) {
                    $cooldownSeconds = now()->diffInSeconds($accountRequest->reject_cooldown_until);
                }
            }
        }

        return view('auth.request-account', [
            'accountRequest' => $accountRequest,
            'cooldownSeconds' => $cooldownSeconds,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'first_name' => ['required', 'string', 'max:50'],
                'last_name' => ['required', 'string', 'max:50'],
                'middle_name' => ['nullable', 'string', 'max:50'],
                'credentials' => ['nullable', 'string', 'max:50'],
                'email' => ['required', 'email', 'regex:/@gmail\.com$/i'],
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

        $existing = AccountRequest::where('email', $validated['email'])->first();

        if ($existing) {
            if ($existing->status === 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a pending account request. Please wait for the admin to review your submission.',
                ], 409);
            }

            if ($existing->status === 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has already been approved. Please sign in instead.',
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

                DB::table('account_requests')->where('id', $existing->id)->update([
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'middle_name' => $validated['middle_name'] ?? null,
                    'credentials' => $validated['credentials'] ?? null,
                    'department' => $validated['course'] ?? null,
                    'status' => 'pending',
                    'rejection_reason' => null,
                    'reject_attempts' => 0,
                    'reject_cooldown_until' => null,
                    'max_teams' => $validated['max_teams'] ?? 4,
                    'specialization' => json_encode($validated['specialization']),
                    'expertise' => json_encode($validated['expertise']),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Your account request has been re-submitted successfully.',
                ]);
            }

            DB::table('account_requests')->where('id', $existing->id)->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'credentials' => $validated['credentials'] ?? null,
                'department' => $validated['course'] ?? null,
                'status' => 'pending',
                'rejection_reason' => null,
                'reject_attempts' => DB::raw('reject_attempts + 1'),
                'max_teams' => $validated['max_teams'] ?? 4,
                'specialization' => json_encode($validated['specialization']),
                'expertise' => json_encode($validated['expertise']),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Your account request has been re-submitted successfully.',
            ]);
        }

        try {
            AccountRequest::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'credentials' => $validated['credentials'] ?? null,
                'email' => $validated['email'],
                'department' => $validated['course'] ?? null,
                'status' => 'pending',
                'max_teams' => $validated['max_teams'] ?? 4,
                'reject_attempts' => 1,
                'specialization' => $validated['specialization'],
                'expertise' => $validated['expertise'],
            ]);

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
}
