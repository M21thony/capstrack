<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use Illuminate\Http\JsonResponse;

class AdminExpertController extends Controller
{
    public function index(): JsonResponse
    {
        $experts = CapstoneExpert::where('approved_status', true)
            ->withCount(['teamAssignments' => function ($q) {
                $q->where('status', 'approved');
            }])
            ->orderBy('created_at', 'desc')
            ->get()
            ->load('expertise', 'roles');

        return response()->json(['success' => true, 'data' => $experts]);
    }

    public function destroy(CapstoneExpert $expert): JsonResponse
    {
        if ($blocker = $expert->deactivationBlocker()) {
            return response()->json(['success' => false, 'message' => $blocker], 422);
        }

        $expert->delete();

        return response()->json([
            'success' => true,
            'message' => "Expert {$expert->name} has been removed.",
        ]);
    }

    // transferRights() used to live here - hardcoded role_id 1 and moved an
    // expert_roles pivot row, never actually touching is_admin/is_capsa
    // despite its name. Its only route (/api/admin/turnover) is gone;
    // replaced by AdminExpertsController::toggleCapsa(), the real thing
    // this was meant to do.
}
