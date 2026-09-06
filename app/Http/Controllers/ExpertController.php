<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'credentials' => ['nullable', 'string', 'max:50'],
            // The same two domains sign-in accepts for faculty. This used to
            // take @gmail.com only, so a lecturer refused at sign-in with
            // their real @cspc.edu.ph address landed on this form with that
            // address filled in and no way to submit it.
            'email' => ['required', 'email', 'regex:/@(gmail\.com|cspc\.edu\.ph)$/i'],

            // Role is not picked here - a CaPSA assigns it when approving the
            // request. See AdminApprovalController::approve().

            // Both of these are the applicant's to skip. Somebody who does not
            // yet know which programs they will take teams from, or does not
            // think of themselves as having a research field, should not be
            // stopped at the door over it - both are on their own profile page
            // afterwards, and this said so on screen.
            'program_slots' => ['sometimes', 'array'],
            'program_slots.*' => ['integer', 'min:0', 'max:10'],
            'expertise' => ['sometimes', 'array'],
            'expertise.*' => ['string', 'max:100'],
        ]);

        $validator->after(function ($v) use ($request) {
            foreach (array_keys((array) $request->input('program_slots', [])) as $program) {
                if (! in_array($program, CapstoneExpert::PROGRAMS, true)) {
                    $v->errors()->add('program_slots', "\"{$program}\" isn't a program you can take teams from.");

                    return;
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $validated = $validator->validated();
        $existing = CapstoneExpert::where('email', $validated['email'])->first();

        if (! $existing) {
            $expertId = DB::table('capstone_experts')->insertGetId(
                $this->requestFields($validated) + [
                    'email' => $validated['email'],
                    // No role, no is_admin - a fresh request never sets either.
                    'reject_attempts' => 0,
                    'created_at' => now(),
                ]
            );

            $this->writeExpertise($expertId, $validated['expertise'] ?? []);

            return response()->json([
                'success' => true,
                'message' => 'Account request submitted successfully.',
            ]);
        }

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

        // Three rejections buys a three-day wait before the next attempt.
        $resetting = (int) $existing->reject_attempts >= 3;

        if ($resetting && $existing->reject_cooldown_until && now()->lt($existing->reject_cooldown_until)) {
            $waitSeconds = now()->diffInSeconds($existing->reject_cooldown_until);

            return response()->json([
                'success' => false,
                'message' => "Submission temporarily blocked due to multiple rejections. Please try again in {$waitSeconds} seconds.",
            ], 429);
        }

        // Role is deliberately untouched on a resubmit as well: a rejected
        // applicant does not get to re-pick it either.
        $fields = $this->requestFields($validated) + ['rejection_reason' => null];

        if ($resetting) {
            $fields += ['reject_attempts' => 0, 'reject_cooldown_until' => null];
        }

        DB::table('capstone_experts')->where('expert_id', $existing->expert_id)->update($fields);
        $this->writeExpertise($existing->expert_id, $validated['expertise'] ?? []);

        return response()->json([
            'success' => true,
            'message' => 'Your account request has been re-submitted successfully.',
        ]);
    }

    /**
     * The columns a request writes, whether it is a first attempt or a resubmit.
     *
     * Written once because it was written three times, and the three had
     * already drifted - only one of them cleared the cooldown.
     *
     * @param  array<string,mixed>  $validated
     * @return array<string,mixed>
     */
    private function requestFields(array $validated): array
    {
        $slots = CapstoneExpert::packProgramSlots($validated['program_slots'] ?? []);

        return [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'suffix' => $validated['suffix'] ?? null,
            'credentials' => $validated['credentials'] ?? null,
            'approved_status' => false,
            // Empty when they skipped it, which leaves them exactly where an
            // approved expert who has never opened their profile already is:
            // on the list, and not assignable until they say how many teams
            // they will take.
            'program_slots' => $slots['raw'] ?: null,
            'max_teams' => $slots['total'] ?: null,
        ];
    }

    /**
     * Replace what they said they know. Skipping leaves them with none, which
     * is a thing their profile page can fix any time.
     *
     * @param  array<int,string>  $fields
     */
    private function writeExpertise(int $expertId, array $fields): void
    {
        DB::table('expert_expertise')->where('expert_id', $expertId)->delete();

        foreach ($fields as $field) {
            $field = is_string($field) ? trim($field) : '';

            if ($field !== '') {
                DB::table('expert_expertise')->insert([
                    'expert_id' => $expertId,
                    'expertise_field' => $field,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
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
