<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use App\Models\ExpertExpertise;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountRequestController extends Controller
{
    public function create()
    {
        return view('auth.request-account');
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'first_name' => ['required', 'string', 'max:50'],
                'last_name' => ['required', 'string', 'max:50'],
                'middle_name' => ['nullable', 'string', 'max:50'],
                'credentials' => ['nullable', 'string', 'max:50'],
                'email' => ['required', 'email', 'regex:/@gmail\.com$/i', 'unique:capstone_experts,email'],
                'specialization' => ['required', 'array', 'min:1'],
                'specialization.*' => ['string', 'in:adviser,consultant,grammarian,capstone_adviser'],
                'expertise' => ['required', 'array', 'min:1'],
                'expertise.*' => ['string', 'max:100'],
                'max_teams' => ['nullable', 'integer', 'min:1', 'max:10'],
                'department' => ['nullable', 'string', 'max:100'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            $expert = CapstoneExpert::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'credentials' => $validated['credentials'] ?? null,
                'email' => $validated['email'],
                'profile_picture' => null,
                'is_admin' => false,
                'approved_status' => false,
                'max_teams' => $validated['max_teams'] ?? 4,
                'department' => $validated['department'] ?? null,
            ]);

            foreach ($validated['specialization'] as $roleName) {
                $role = Role::where('role_name', $roleName)->first();
                if ($role) {
                    DB::table('expert_roles')->insert([
                        'expert_id' => $expert->expert_id,
                        'role_id' => $role->role_id,
                    ]);
                }
            }

            foreach ($validated['expertise'] as $field) {
                ExpertExpertise::create([
                    'expert_id' => $expert->expert_id,
                    'expertise_field' => $field,
                ]);
            }

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
