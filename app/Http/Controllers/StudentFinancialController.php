<?php

namespace App\Http\Controllers;

use App\Models\ExpensePost;
use App\Models\FinancialRecord;
use App\Models\Student;
use App\Support\FeeBoard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A student's Financial Records: each defense fee, their own receipt for it,
 * and where each teammate stands. Each member uploads their own receipt.
 */
class StudentFinancialController extends Controller
{
    public function index(FeeBoard $fees): View
    {
        /** @var Student $student */
        $student = auth()->user();

        return view('student.financial', ['state' => $fees->forStudent($student)]);
    }

    public function submitReceipt(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'expense_post_id' => ['required', 'exists:expense_posts,id'],
            'receipt_image' => ['required', 'image', 'max:10240'],
        ]);

        /** @var Student $student */
        $student = auth()->user();
        $team = $student->team;

        // A team verified before the pre-proposal flow existed has no
        // proposals to have submitted, and is past this bar by definition.
        if (! $team || ! ($team->hasSubmittedPreProposals() || $team->is_confirmed)) {
            return redirect()->back()->withErrors([
                'team_status' => 'Your team leader has to submit your pre-proposals for checking on the Duplication Checker page before anyone can upload receipts.',
            ])->withInput();
        }

        FinancialRecord::submitFor($student, ExpensePost::findOrFail($validated['expense_post_id']), $request->file('receipt_image'));

        return redirect()->back()->with('success', 'Receipt sent to your CaPSA.');
    }
}
