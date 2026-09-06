<?php

namespace App\Http\Controllers;

use App\Models\ExpensePost;
use App\Models\FinancialRecord;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentFinancialController extends Controller
{
    public function index(): View
    {
        /** @var Student $student */
        $student = auth()->user();

        $program = Student::mapCourseToProgram($student->course ?? '');

        $yearLevel = null;
        if ($student->year_section) {
            $parts = explode(' ', $student->year_section);
            $yearLevel = $parts[0] ?? null;
        }

        $hiddenExpenseIds = FinancialRecord::where('student_id', $student->student_id)
            ->whereIn('f_record_status', ['pending', 'verified'])
            ->pluck('expense_post_id');

        $expensePosts = ExpensePost::where('is_active', true)
            ->when($program, function ($q) use ($program) {
                $q->where('course', $program);
            })
            ->when($yearLevel, function ($q) use ($yearLevel) {
                $q->where('year_level', $yearLevel);
            })
            ->whereNotIn('id', $hiddenExpenseIds)
            ->orderByDesc('created_at')
            ->get();

        $submissions = FinancialRecord::with('expensePost')
            ->where('student_id', $student->student_id)
            ->orderByDesc('uploaded_at')
            ->get();

        $team = $student->team;
        $teamStatus = $team?->team_status;

        $teamNotComplete = $team && $teamStatus !== 'complete';

        return view('student.financial', compact(
            'expensePosts',
            'submissions',
            'teamStatus',
            'teamNotComplete',
        ));
    }

    public function submitReceipt(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'expense_post_id' => ['required', 'exists:expense_posts,id'],
            'receipt_image' => ['required', 'image', 'max:10240'],
            'receipt_number' => ['required', 'string', 'max:100'],
        ]);

        /** @var Student $student */
        $student = auth()->user();

        $team = $student->team;

        if (! $team || $team->team_status !== 'complete') {
            return redirect()->back()->withErrors([
                'team_status' => 'Your Team Leader must first mark your team as Complete in Team Management before any team member can submit a receipt.',
            ])->withInput();
        }

        $expensePost = ExpensePost::findOrFail($validated['expense_post_id']);

        $imagePath = $request->file('receipt_image')->store('receipts', 'public');

        FinancialRecord::create([
            'student_id' => $student->student_id,
            'expense_post_id' => $expensePost->id,
            'receipt_image' => $imagePath,
            'receipt_number' => $validated['receipt_number'],
            'f_record_status' => 'pending',
            'payment_for' => $expensePost->title,
            'amount' => $expensePost->amount,
            'description' => $expensePost->description,
            'uploaded_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Receipt submitted successfully.');
    }
}
