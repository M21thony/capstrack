<?php

namespace App\Http\Controllers;

use App\Mail\RequestStatusMail;
use App\Models\CapstoneExpert;
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

        // $allIds is already in display order (pending, then approved, then
        // rejected - each newest first), so we just need the rows back in
        // that same order. This used to be done with MySQL's FIELD(), which
        // no other driver has, and which also produced malformed SQL
        // ("FIELD(expert_id, )") whenever there were no experts at all.
        // Sorting the fetched rows avoids both problems.
        $position = $allIds->values()->flip();

        $instructors = DB::table('capstone_experts')
            ->whereIn('expert_id', $allIds)
            ->get()
            ->sortBy(fn ($expert) => $position[$expert->expert_id] ?? PHP_INT_MAX)
            ->values()
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

        return view('capsa.approval', [
            'instructors' => $instructors,
            'pendingCount' => $pendingIds->count(),
            // Role is assigned here now, not by the applicant at request
            // time - the review modal needs the real list to check off.
            'roles' => DB::table('roles')->orderBy('role_name')->get(['role_id', 'role_name']),
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

    /**
     * Approving and rejecting both land the record on a new status, so one
     * PATCH carries which one. The deciding stays in the two methods that
     * already do it - this only routes to them.
     */
    public function update(Request $request, $id): JsonResponse
    {
        return $request->validate([
            'status' => ['required', 'in:approved,rejected'],
        ])['status'] === 'approved'
            ? $this->approve($request, $id)
            : $this->reject($request, $id);
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $expert = DB::table('capstone_experts')->where('expert_id', $id)->first();

        if (! $expert) {
            return response()->json(['success' => false, 'message' => 'Request not found.'], 404);
        }

        if ($expert->approved_status) {
            return response()->json(['success' => false, 'message' => 'Request already processed.'], 422);
        }

        // Role used to be picked by the applicant at request time - it's now
        // CAPSA's call, made here, at approval.
        $validated = $request->validate([
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,role_id'],
        ]);

        DB::transaction(function () use ($id, $validated) {
            DB::table('capstone_experts')->where('expert_id', $id)->update([
                'approved_status' => true,
                'rejection_reason' => null,
            ]);

            // Diffed rather than delete-all: expert_role_id is what team
            // assignments point at, so reinserting an unchanged role would
            // hand it a new id and orphan them.
            CapstoneExpert::syncRoles((int) $id, $validated['role_ids']);
        });

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
        $cooldownUntil = $currentAttempts >= 2 ? now()->addDays(3) : null;

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
