<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $expert->delete();

        return response()->json([
            'success' => true,
            'message' => "Expert {$expert->name} has been removed.",
        ]);
    }

    public function transferRights(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'expert_id' => ['required', 'integer', 'exists:capstone_experts,expert_id'],
        ]);

        $fromExpert = auth('expert')->user();
        $toExpertId = (int) $validated['expert_id'];

        if ($fromExpert->expert_id === $toExpertId) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot transfer rights to yourself.',
            ], 422);
        }

        DB::transaction(function () use ($fromExpert, $toExpertId) {
            DB::table('expert_roles')
                ->where('expert_id', $fromExpert->expert_id)
                ->where('role_id', 1)
                ->delete();

            DB::table('expert_roles')->insert([
                'expert_id' => $toExpertId,
                'role_id' => 1,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Admin rights transferred successfully.',
        ]);
    }
}
