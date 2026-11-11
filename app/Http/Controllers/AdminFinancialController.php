<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use App\Models\ExpensePost;
use App\Models\FinancialRecord;
use App\Support\FeeBoard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The CaPSA/TSA's Financial Records page. It is drawn from the JSON FeeBoard
 * builds; its actions call Api\AdminFinancialController.
 */
class AdminFinancialController extends Controller
{
    public function index(FeeBoard $fees): View
    {
        /** @var CapstoneExpert $capsa */
        $capsa = auth('expert')->user();

        return view('capsa.financial', ['state' => $fees->forCapsa($capsa)]);
    }

    /** The dean's page: what each section's CaPSA/TSA has sent so far. */
    public function fees(FeeBoard $fees): View
    {
        return view('admin.fees', ['rows' => $fees->forDean()]);
    }

    public function storeExpensePost(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'course' => ['required', 'string', 'in:BSIT,BSCS,BLIS,BSIS'],
            'year_level' => ['required', 'string', 'in:3rd,4th'],
            'defense_type' => ['nullable', 'string', 'in:Title Defense,Pre Oral Defense,Final Oral Defense'],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        ExpensePost::create($validated + ['admin_id' => auth('expert')->id()]);

        return redirect()->back()->with('success', 'Expense post created successfully.');
    }

    public function updateExpensePost(Request $request, ExpensePost $expensePost): RedirectResponse
    {
        $expensePost->update($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'course' => ['required', 'string', 'in:BSIT,BSCS,BLIS,BSIS'],
            'year_level' => ['required', 'string', 'in:3rd,4th'],
            'defense_type' => ['nullable', 'string', 'in:Title Defense,Pre Oral Defense,Final Oral Defense'],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]));

        return redirect()->back()->with('success', 'Expense post updated successfully.');
    }

    public function destroyExpensePost(ExpensePost $expensePost): RedirectResponse
    {
        $expensePost->delete();

        return redirect()->back()->with('success', 'Expense post deleted successfully.');
    }

    /** Verifying and sending back both land the record on a new status, so one PATCH carries which. */
    public function updateReceipt(Request $request, FinancialRecord $financialRecord): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', 'in:approved,rejected']])['status'];
        $by = auth('expert')->user()->name;

        if ($financialRecord->sent_to_dean_at) {
            return redirect()->back()->withErrors(['status' => 'This receipt was already sent to the dean and cannot be changed.']);
        }

        if ($status === 'approved') {
            if ($financialRecord->receipt_number && ! $request->boolean('or_confirmed')) {
                return redirect()->back()->withErrors(['or_confirmed' => 'Check that the OR number on the photo is '.$financialRecord->receipt_number.' before verifying.']);
            }

            $financialRecord->update(['f_record_status' => 'verified', 'verified_at' => now(), 'verified_by' => $by]);

            return redirect()->back()->with('success', 'Receipt verified successfully.');
        }

        $reason = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']])['rejection_reason'];
        $financialRecord->update(['f_record_status' => 'rejected', 'rejection_reason' => $reason, 'verified_at' => now(), 'verified_by' => $by]);

        return redirect()->back()->with('success', 'Receipt sent back.');
    }
}
