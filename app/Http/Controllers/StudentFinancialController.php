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

        // The page asked whether team_status was 'complete'. That value no
        // longer exists - a team is Active or Full now - so the lock could
        // never come off, while the submit endpoint had already moved on to
        // asking whether the team has turned its pre-proposals in. One rule,
        // and it is the endpoint's, so the form is open exactly when a
        // receipt would be accepted.
        $canSubmitReceipts = (bool) $team
            && ($team->hasSubmittedPreProposals() || $team->is_confirmed);

        $teamNotComplete = $team && ! $canSubmitReceipts;

        return view('student.financial', compact(
            'expensePosts',
            'submissions',
            'teamStatus',
            'canSubmitReceipts',
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

        // is_confirmed carries the teams that were verified before the
        // pre-proposal flow existed - they have no proposals to have
        // submitted, and were able to file receipts under the old
        // team_status gate. Verification is the later milestone anyway, so a
        // verified team is past this bar by definition.
        if (! $team || ! ($team->hasSubmittedPreProposals() || $team->is_confirmed)) {
            return redirect()->back()->withErrors([
                'team_status' => 'Your team has to be marked Complete before anyone can submit a receipt. Your team leader does that by submitting your pre-proposals for checking on the Duplication Checker page.',
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
