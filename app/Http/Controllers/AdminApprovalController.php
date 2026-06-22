<?php

namespace App\Http\Controllers;

use App\Mail\InstructorApproved;
use App\Mail\InstructorRejected;
use App\Models\AccountRequest;
use App\Models\CapstoneExpert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminApprovalController extends Controller
{
    public function index()
    {
        // NOTE:
        // UI currently renders from CapstoneExpert, but decisions should be applied to the
        // request record for email recipient correctness.
        $experts = CapstoneExpert::with('expertise', 'roles')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.approval', ['instructors' => $experts]);
    }

    public function apiIndex(): JsonResponse
    {
        $experts = CapstoneExpert::with('expertise', 'roles')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $experts]);
    }

    private function getRequestByExpertId(CapstoneExpert $expert): ?AccountRequest
    {
        // Pending accounts in your workflow are from account_requests.
        // Since AccountRequest model currently has no relationships, we map by expert_id.
        // Fallback to null when not found.
        return AccountRequest::where('expert_id', $expert->expert_id)->first();
    }

    public function approve(CapstoneExpert $expert): JsonResponse
    {
        $expert->update(['approved_status' => true]);

        $accountRequest = $this->getRequestByExpertId($expert);
        $recipientEmail = $accountRequest?->email ?? $expert->email;

        if (!empty($recipientEmail)) {
            Mail::to($recipientEmail)->send(new InstructorApproved($expert));
        }

        return response()->json([
            'success' => true,
            'message' => 'Account approved and email sent.',
        ]);
    }

    public function reject(Request $request, CapstoneExpert $expert): JsonResponse
    {
        $reason = $request->input('reason');

        $expert->update([
            'approved_status' => false,
            'rejection_reason' => $reason,
        ]);

        $accountRequest = $this->getRequestByExpertId($expert);
        $recipientEmail = $accountRequest?->email ?? $expert->email;

        if (!empty($recipientEmail)) {
            Mail::to($recipientEmail)->send(new InstructorRejected($expert, $reason));
        }

        return response()->json([
            'success' => true,
            'message' => 'Account rejected and email sent.',
        ]);
    }
}

