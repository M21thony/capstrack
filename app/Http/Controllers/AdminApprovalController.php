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
        $pendingIds = DB::table('capstone_experts')
            ->where('approved_status', false)
            ->whereNull('rejection_reason')
            ->orderBy('created_at', 'desc')
            ->pluck('expert_id');

        $approvedIds = DB::table('capstone_experts')
            ->where('approved_status', true)
            ->orderBy('created_at', 'desc')
            ->pluck('expert_id');

        $rejectedIds = DB::table('capstone_experts')
            ->where('approved_status', false)
            ->whereNotNull('rejection_reason')
            ->orderBy('created_at', 'desc')
            ->pluck('expert_id');

        $allIds = $pendingIds->merge($approvedIds)->merge($rejectedIds);

        $instructors = DB::table('capstone_experts')
            ->whereIn('expert_id', $allIds)
            ->orderByRaw('FIELD(expert_id, '.$allIds->map(fn ($id) => (int) $id)->implode(',').')')
            ->get()
            ->map(function ($expert) {
                $expert->status = $expert->approved_status ? 'approved' : ($expert->rejection_reason ? 'rejected' : 'pending');

                return $expert;
            });

        $specializations = [];
        $expertise = [];

        $expertRoles = DB::table('expert_roles')
            ->join('roles', 'expert_roles.role_id', '=', 'roles.role_id')
            ->whereIn('expert_roles.expert_id', $allIds)
            ->get()
            ->groupBy('expert_id');

        foreach ($expertRoles as $expertId => $roles) {
            $specializations[(int) $expertId] = $roles->pluck('role_name')->toArray();
        }

        $expertExpertise = DB::table('expert_expertise')
            ->whereIn('expert_id', $allIds)
            ->get()
            ->groupBy('expert_id');

        foreach ($expertExpertise as $expertId => $exp) {
            $expertise[(int) $expertId] = $exp->pluck('expertise_field')->toArray();
        }

        $instructors->map(function ($expert) use ($specializations, $expertise) {
            $expert->specialization = $specializations[$expert->expert_id] ?? [];
            $expert->expertise = $expertise[$expert->expert_id] ?? [];
            $expert->id = $expert->expert_id;

            return $expert;
        });

        return view('admin.approval', [
            'instructors' => $instructors,
            'pendingCount' => $pendingIds->count(),
        ]);
    }

    public function apiIndex(): JsonResponse
    {
        $expertIds = DB::table('capstone_experts')
            ->orderBy('created_at', 'desc')
            ->pluck('expert_id');

        $experts = DB::table('capstone_experts')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($expert) {
                $expert->status = $expert->approved_status ? 'approved' : ($expert->rejection_reason ? 'rejected' : 'pending');

                return $expert;
            });

        $expertRoles = DB::table('expert_roles')
            ->join('roles', 'expert_roles.role_id', '=', 'roles.role_id')
            ->whereIn('expert_roles.expert_id', $expertIds)
            ->get()
            ->groupBy('expert_id');

        $specializations = [];
        foreach ($expertRoles as $expertId => $roles) {
            $specializations[(int) $expertId] = $roles->pluck('role_name')->toArray();
        }

        $expertExpertise = DB::table('expert_expertise')
            ->whereIn('expert_id', $expertIds)
            ->get()
            ->groupBy('expert_id');

        $expertise = [];
        foreach ($expertExpertise as $expertId => $exp) {
            $expertise[(int) $expertId] = $exp->pluck('expertise_field')->toArray();
        }

        $experts->map(function ($expert) use ($specializations, $expertise) {
            $expert->specialization = $specializations[$expert->expert_id] ?? [];
            $expert->expertise = $expertise[$expert->expert_id] ?? [];

            return $expert;
        });

        return response()->json(['success' => true, 'data' => $experts]);
    }

    public function approve($id): JsonResponse
    {
        $expert = DB::table('capstone_experts')->where('expert_id', $id)->first();

        if (! $expert) {
            return response()->json(['success' => false, 'message' => 'Request not found.'], 404);
        }

        if ($expert->approved_status) {
            return response()->json(['success' => false, 'message' => 'Request already processed.'], 422);
        }

        DB::table('capstone_experts')->where('expert_id', $id)->update([
            'approved_status' => true,
            'rejection_reason' => null,
        ]);

        $approvedExpert = DB::table('capstone_experts')->where('expert_id', $id)->first();

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

        $expert = DB::table('capstone_experts')->where('expert_id', $id)->first();

        if (! $expert) {
            return response()->json(['success' => false, 'message' => 'Request not found.'], 404);
        }

        if ($expert->approved_status) {
            return response()->json(['success' => false, 'message' => 'Request already processed.'], 422);
        }

        $currentAttempts = (int) $expert->reject_attempts;
        $cooldownUntil = $currentAttempts >= 2 ? now()->addSeconds(60) : null;

        DB::table('capstone_experts')->where('expert_id', $id)->update([
            'rejection_reason' => $validated['reason'],
            'reject_cooldown_until' => $cooldownUntil,
            'reject_attempts' => DB::raw('reject_attempts + 1'),
        ]);

        $updated = DB::table('capstone_experts')->where('expert_id', $id)->first();

        Mail::to($updated->email)->queue(new RequestStatusMail(
            expert: (array) $updated,
            status: 'rejected',
            reason: $validated['reason'],
        ));

        return response()->json(['success' => true, 'message' => 'Account rejected and email sent.']);
    }
}
