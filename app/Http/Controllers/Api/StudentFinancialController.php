<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpensePost;
use App\Models\FinancialRecord;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentFinancialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

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
            ->get()
            ->map(fn ($post) => [
                'id' => $post->id,
                'title' => $post->title,
                'amount' => (float) $post->amount,
                'description' => $post->description,
            ]);

        $submissions = $this->getSubmissionsQuery($student)->get();

        $team = $student->team;
        $teamStatus = $team?->team_status;
        $teamNotComplete = $team && $teamStatus !== 'complete';

        $receiptCategories = $submissions->pluck('category')->unique()->filter()->values();

        return response()->json([
            'success' => true,
            'message' => 'Request completed successfully.',
            'data' => [
                'expense_posts' => $expensePosts,
                'submissions' => $submissions,
                'team_status' => $teamStatus,
                'team_not_complete' => $teamNotComplete,
                'receipt_categories' => $receiptCategories,
            ],
        ]);
    }

    public function submissions(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $query = $this->getSubmissionsQuery($student);

        if ($request->filled('status')) {
            $query->where('f_record_status', $request->status);
        }

        if ($request->filled('category')) {
            $query->where(function ($q) use ($request) {
                $q->where('payment_for', $request->category)
                    ->orWhereHas('expensePost', fn ($q) => $q->where('title', $request->category));
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                    ->orWhere('payment_for', 'like', "%{$search}%")
                    ->orWhereHas('expensePost', fn ($q) => $q->where('title', 'like', "%{$search}%"));
            });
        }

        $sort = $request->sort === 'oldest' ? 'asc' : 'desc';
        $query->orderBy('uploaded_at', $sort);

        return response()->json([
            'success' => true,
            'message' => 'Request completed successfully.',
            'data' => $query->get(),
        ]);
    }

    public function show(Request $request, FinancialRecord $financialRecord): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        if ((int) $financialRecord->student_id !== (int) $student->student_id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $financialRecord->load('expensePost');

        return response()->json([
            'success' => true,
            'message' => 'Request completed successfully.',
            'data' => $this->formatSubmission($financialRecord),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'expense_post_id' => ['required', 'exists:expense_posts,id'],
            'receipt_image' => ['required', 'image', 'max:10240'],
        ]);

        /** @var Student $student */
        $student = $request->user();

        $team = $student->team;

        // is_confirmed carries the teams that were verified before the
        // pre-proposal flow existed - they have no proposals to have
        // submitted, and were able to file receipts under the old
        // team_status gate. Verification is the later milestone anyway, so a
        // verified team is past this bar by definition.
        if (! $team || ! ($team->hasSubmittedPreProposals() || $team->is_confirmed)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'team_status' => ['Your team has to be marked Complete before anyone can submit a receipt. Your team leader does that by submitting your pre-proposals for checking on the Duplication Checker page.'],
                ],
            ], 422);
        }

        // A first receipt, or a new photo for one still waiting or sent back.
        $record = FinancialRecord::submitFor($student, ExpensePost::findOrFail($validated['expense_post_id']), $request->file('receipt_image'));

        $record->load('expensePost');

        return response()->json([
            'success' => true,
            'message' => 'Receipt submitted successfully.',
            'data' => $this->formatSubmission($record),
        ], 201);
    }

    public function expensePosts(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

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
            ->get()
            ->map(fn ($post) => [
                'id' => $post->id,
                'title' => $post->title,
                'amount' => (float) $post->amount,
                'description' => $post->description,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Request completed successfully.',
            'data' => $expensePosts,
        ]);
    }

    private function getSubmissionsQuery(Student $student)
    {
        return FinancialRecord::with('expensePost')
            ->where('student_id', $student->student_id)
            ->orderByDesc('uploaded_at')
            ->get()
            ->map(fn ($record) => $this->formatSubmission($record));
    }

    private function formatSubmission(FinancialRecord $record): array
    {
        $expenseTitle = $record->expensePost?->title ?? $record->payment_for ?? 'N/A';

        return [
            'id' => $record->id,
            'student_id' => $record->student_id,
            'expense_post_id' => $record->expense_post_id,
            'receipt_image' => $record->receipt_image ? asset('storage/'.$record->receipt_image) : null,
            'receipt_number' => $record->receipt_number,
            'status' => $record->f_record_status,
            'payment_for' => $record->payment_for,
            'category' => $expenseTitle,
            'amount' => (float) ($record->amount ?? 0),
            'description' => $record->description,
            'rejection_reason' => $record->rejection_reason,
            'uploaded_at' => $record->uploaded_at?->format('Y-m-d H:i:s'),
            'uploaded_at_formatted' => $record->uploaded_at?->format('M d, Y \\a\\t g:i A'),
            'verified_at' => $record->verified_at?->format('Y-m-d H:i:s'),
            'verified_at_formatted' => $record->verified_at?->format('M d, Y \\a\\t g:i A'),
            'verified_by' => $record->verified_by,
            'display_text' => $record->description
                ? $expenseTitle.' — '.$record->description
                : $expenseTitle,
        ];
    }
}
