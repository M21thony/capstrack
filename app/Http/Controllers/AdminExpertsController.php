<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use Illuminate\Http\JsonResponse;

class AdminExpertsController extends Controller
{
    /**
     * Display a listing of the experts.
     */
    public function index()
    {
        $experts = CapstoneExpert::where('approved_status', true)
            ->with('expertise', 'roles')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.experts', ['experts' => $experts]);
    }

    /**
     * Return a JSON list of approved experts for API consumers.
     */
    public function apiIndex(): JsonResponse
    {
        $experts = CapstoneExpert::where('approved_status', true)
            ->orderBy('created_at', 'desc')
            ->get();
        // Optionally eager load relationships if needed by the client
        $experts->load('expertise', 'roles');
        return response()->json(['success' => true, 'data' => $experts]);
    }

    /**
     * Transfer admin rights to another expert.
     */
    /**
     * Delete an expert.
     */
    public function destroy(CapstoneExpert $expert): JsonResponse
    {
        $expert->delete();

        return response()->json([
            'success' => true,
            'message' => "Expert {$expert->name} has been removed.",
        ]);
    }
}
