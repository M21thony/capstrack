<?php

namespace App\Http\Controllers;

use App\Models\ExpensePost;
use App\Models\FinancialRecord;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A student's Financial Records: each defense fee, their own receipt for it,
 * and where each teammate stands. Each member uploads their own receipt to
 * their team leader, who sends the team's receipts to the CaPSA together.
 *
 * It is a tab of Team Management now (TeamController::index draws it), so
 * the old address opens that tab - links in notifications and bookmarks
 * still land in the right place.
 */
class StudentFinancialController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('student.team', ['tab' => 'financial']);
    }

    public function submitReceipt(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'expense_post_id' => ['required', 'exists:expense_posts,id'],
            'receipt_image' => ['required', 'image', 'max:10240'],
            'receipt_number' => FinancialRecord::orNumberRules(),
        ], FinancialRecord::orNumberMessages());

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

        FinancialRecord::submitFor($student, ExpensePost::findOrFail($validated['expense_post_id']), $request->file('receipt_image'), $validated['receipt_number']);

        return redirect()->back()->with('success', 'Receipt saved. Your team leader sends it to the CaPSA with everyone else\'s.');
    }
}
