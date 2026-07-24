<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function index()
    {
        /** @var Student $student */
        $student = auth()->user();

        $studentSy = StudentSchoolYear::where('student_id', $student->student_id)->first();
        $teamId = $studentSy?->team_id;

        $milestones = collect();
        if ($teamId) {
            $milestones = Milestone::where('team_id', $teamId)
                ->orderBy('due_date')
                ->get();
        }

        $completed = $milestones->where('status', 'completed')->count();
        $inProgress = $milestones->where('status', 'in_progress')->count();
        $todo = $milestones->where('status', 'todo')->count();
        $overdue = $milestones->where('status', 'overdue')->count();

        return view('student.progress', [
            'milestones' => $milestones,
            'completed' => $completed,
            'inProgress' => $inProgress,
            'todo' => $todo,
            'overdue' => $overdue,
            'teamId' => $teamId,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'due_date' => 'nullable|date',
        ]);

        /** @var Student $student */
        $student = auth()->user();
        $studentSy = StudentSchoolYear::where('student_id', $student->student_id)->first();
        $teamId = $studentSy?->team_id;

        if (! $teamId) {
            return response()->json(['message' => 'No team found.'], 400);
        }

        $milestone = Milestone::create([
            'team_id' => $teamId,
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'due_date' => $data['due_date'] ?? null,
            'status' => 'todo',
        ]);

        return response()->json($milestone);
    }
}
