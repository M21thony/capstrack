<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();

        $query = ActivityLog::where('student_id', $student->student_id);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('team_id')) {
            $query->where('metadata->team_id', $request->input('team_id'));
        }

        $activities = $query->latest('created_at')->paginate(50);

        return ActivityLogResource::collection($activities)->response();
    }
}
