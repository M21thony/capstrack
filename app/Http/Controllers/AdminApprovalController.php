<?php

namespace App\Http\Controllers;

use App\Mail\InstructorApproved;
use App\Models\CapstoneExpert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminApprovalController extends Controller
{
    public function index()
    {
        $experts = CapstoneExpert::with('expertise', 'roles')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.approval', ['experts' => $experts]);
    }

    public function approve(CapstoneExpert $expert): JsonResponse
    {
        $expert->update(['approved_status' => true]);

        Mail::to($expert->email)->send(new InstructorApproved($expert));

        return response()->json([
            'success' => true,
            'message' => 'Account approved and email sent.',
        ]);
    }

    public function reject(Request $request, CapstoneExpert $expert): JsonResponse
    {
        $expert->update(['approved_status' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Account rejected.',
        ]);
    }
}
