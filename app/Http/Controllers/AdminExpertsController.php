<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use App\Models\Notification;
use App\Models\StudentSchoolYear;
use App\Models\TeamExpertAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminExpertsController extends Controller
{
    /**
     * Display a listing of the experts.
     */
    public function index()
    {
        $experts = CapstoneExpert::where('approved_status', true)
            ->with('expertise', 'roles')
            ->withCount(['teamAssignments' => function ($q) {
                $q->where('status', 'approved');
            }])
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
     * Delete an expert and notify all affected team members.
     */
    public function destroy(Request $request, CapstoneExpert $expert): JsonResponse
    {
        $reason = trim($request->input('reason', ''));
        $expertName = $expert->name;
        $notifiedTeams = 0;

        $approvedAssignments = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'approved')
            ->with('team', 'role')
            ->get();

        foreach ($approvedAssignments as $assignment) {
            $team = $assignment->team;
            if (! $team) {
                continue;
            }

            $roleName = $assignment->role?->role_name ?? 'Expert';
            $teamName = $team->team_name ?? 'Unknown Team';

            $memberIds = StudentSchoolYear::where('team_id', $team->team_id)
                ->whereNotNull('student_id')
                ->pluck('student_id');

            foreach ($memberIds as $studentId) {
                $message = 'Your expert <strong>'.e($expertName)."</strong> ({$roleName}) has been removed from <strong>".e($teamName).'</strong>.';
                if ($reason) {
                    $message .= ' Reason: '.e($reason);
                }

                Notification::create([
                    'student_id' => $studentId,
                    'type' => 'expert_removed',
                    'title' => 'Expert Removed from Your Team',
                    'message' => $message,
                    'link' => route('student.team'),
                    'is_read' => false,
                    'created_at' => now(),
                ]);
            }

            $notifiedTeams++;
        }

        cache()->put('expert_removed_'.$expert->expert_id, [
            'name' => $expertName,
            'reason' => $reason,
        ], now()->addMinutes(10));

        $expert->delete();

        $msg = "Expert {$expertName} has been removed.";
        if ($notifiedTeams > 0) {
            $msg .= " {$notifiedTeams} team(s) have been notified.";
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
        ]);
    }
}
