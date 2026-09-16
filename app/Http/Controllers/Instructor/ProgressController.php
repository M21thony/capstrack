<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\TeamExpertAssignment;
use App\Support\ChecklistTracker;

class ProgressController extends Controller
{
    /**
     * Milestone progress for the teams this expert advises or consults for:
     * the CaPSA's tracker, read-only. Teams they grade as a panelist or read
     * as a grammarian are not theirs to follow.
     */
    public function index(ChecklistTracker $tracker)
    {
        $expert = auth()->guard('expert')->user();

        $teamIds = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'approved')
            ->with(['expertRole.role', 'role'])
            ->get()
            ->filter(fn ($assignment) => in_array($assignment->engaged_role_name, ['adviser', 'consultant'], true))
            ->pluck('team_id')
            ->unique()
            ->values()
            ->all();

        $overview = $tracker->overview([], null, false, $teamIds);

        // An expert follows a handful of teams, so every section comes at once
        // and switching between them needs no request.
        $overview['all'] = collect($overview['sections'])
            ->mapWithKeys(fn ($section) => [$section['name'] => $tracker->section($section['name'], false, $teamIds)])
            ->all();

        return view('instructor.progress', ['tracker' => $overview]);
    }
}
