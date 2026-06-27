<?php

namespace App\Http\Controllers;

use App\Mail\RequestStatusMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AdminApprovalController extends Controller
{
    public function index()
    {
        $pendingRequests = DB::table('account_requests')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($req) {
                $req->specialization = $req->specialization ? json_decode($req->specialization, true) : [];
                $req->expertise = $req->expertise ? json_decode($req->expertise, true) : [];
                $req->expert_id = $req->id;

                return $req;
            });

        $approvedExperts = DB::table('capstone_experts')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($expert) {
                $expert->id = $expert->expert_id;
                $expert->specialization = $this->getExpertSpecialization($expert->expert_id);
                $expert->expertise = $this->getExpertExpertise($expert->expert_id);
                $expert->status = 'approved';

                return $expert;
            });

        $rejectedRequests = DB::table('account_requests')
            ->where('status', 'rejected')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($req) {
                $req->specialization = $req->specialization ? json_decode($req->specialization, true) : [];
                $req->expertise = $req->expertise ? json_decode($req->expertise, true) : [];
                $req->expert_id = $req->id;

                return $req;
            });

        $instructors = collect()
            ->merge($pendingRequests)
            ->merge($approvedExperts)
            ->merge($rejectedRequests);

        return view('admin.approval', [
            'instructors' => $instructors,
            'pendingCount' => $pendingRequests->count(),
        ]);
    }

    public function apiIndex(): JsonResponse
    {
        $requests = DB::table('account_requests')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($req) {
                $req->specialization = $req->specialization ? json_decode($req->specialization, true) : [];
                $req->expertise = $req->expertise ? json_decode($req->expertise, true) : [];

                return $req;
            });

        return response()->json(['success' => true, 'data' => $requests]);
    }

    public function approve($id): JsonResponse
    {
        $requestRecord = DB::table('account_requests')->where('id', $id)->first();

        if (! $requestRecord) {
            return response()->json(['success' => false, 'message' => 'Request not found.'], 404);
        }

        if ($requestRecord->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Request already processed.'], 422);
        }

        $specialization = $requestRecord->specialization ? json_decode($requestRecord->specialization, true) : [];
        $expertise = $requestRecord->expertise ? json_decode($requestRecord->expertise, true) : [];

        DB::transaction(function () use ($requestRecord, $specialization, $expertise) {
            $expertId = DB::table('capstone_experts')->insertGetId([
                'email' => $requestRecord->email,
                'first_name' => $requestRecord->first_name,
                'last_name' => $requestRecord->last_name,
                'credentials' => $requestRecord->credentials,
                'department' => $requestRecord->department,
                'max_teams' => $requestRecord->max_teams,
                'is_admin' => false,
                'approved_status' => true,
                'created_at' => now(),
            ]);

            $roles = DB::table('roles')->whereIn('role_name', $specialization)->pluck('role_id', 'role_name');

            foreach ($specialization as $roleName) {
                $roleId = $roles[$roleName] ?? null;
                if ($roleId) {
                    DB::table('expert_roles')->insert([
                        'expert_id' => $expertId,
                        'role_id' => $roleId,
                    ]);
                }
            }

            foreach ($expertise as $field) {
                if (is_string($field) && trim($field) !== '') {
                    DB::table('expert_expertise')->insert([
                        'expert_id' => $expertId,
                        'expertise_field' => $field,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('account_requests')->where('id', $requestRecord->id)->update([
                'status' => 'approved',
                'rejection_reason' => null,
                'updated_at' => now(),
            ]);
        });

        $approvedExpert = DB::table('capstone_experts')
            ->where('email', $requestRecord->email)
            ->first();

        Mail::to($approvedExpert->email)->queue(new RequestStatusMail(
            expert: (array) $approvedExpert,
            status: 'approved',
        ));

        return response()->json(['success' => true, 'message' => 'Account approved and email sent.']);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $requestRecord = DB::table('account_requests')->where('id', $id)->first();

        if (! $requestRecord) {
            return response()->json(['success' => false, 'message' => 'Request not found.'], 404);
        }

        if ($requestRecord->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Request already processed.'], 422);
        }

        $currentAttempts = (int) $requestRecord->reject_attempts;
        $cooldownUntil = $currentAttempts >= 3 ? now()->addSeconds(60) : null;

        DB::table('account_requests')->where('id', $id)->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['reason'],
            'reject_cooldown_until' => $cooldownUntil,
            'updated_at' => now(),
        ]);

        $updated = DB::table('account_requests')->where('id', $id)->first();

        Mail::to($updated->email)->queue(new RequestStatusMail(
            expert: (array) $updated,
            status: 'rejected',
            reason: $validated['reason'],
        ));

        return response()->json(['success' => true, 'message' => 'Account rejected and email sent.']);
    }

    private function getExpertSpecialization(int $expertId): array
    {
        return DB::table('expert_roles')
            ->join('roles', 'expert_roles.role_id', '=', 'roles.role_id')
            ->where('expert_roles.expert_id', $expertId)
            ->pluck('roles.role_name')
            ->toArray();
    }

    private function getExpertExpertise(int $expertId): array
    {
        return DB::table('expert_expertise')
            ->where('expert_id', $expertId)
            ->pluck('expertise_field')
            ->toArray();
    }
}
