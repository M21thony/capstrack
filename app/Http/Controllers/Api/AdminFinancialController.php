<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\ExpensePost;
use App\Models\FinancialRecord;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\DefenseBoard;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminFinancialController extends Controller
{
    // Mirrors AdminPanelAssignmentController::DEFENSE_TYPES - kept as its
    // own copy rather than a cross-controller reference since both are
    // small, stable, literal lists and this avoids coupling two unrelated
    // controllers together for a handful of string values.
    private const DEFENSE_TYPES = [
        'Pre-proposal Defense',
        'Title Defense',
        'Pre Oral Defense',
        'Final Oral Defense',
    ];

    public function index(Request $request): JsonResponse
    {
        $course = $request->query('course', '');
        $status = $request->query('status', '');
        $titleId = $request->query('title_id', '');

        $courses = $this->getCourses();

        $stats = $this->getStats();

        $teams = $this->getTeams($titleId, $course, $status);

        $financialRecords = $this->getFinancialRecords($request);

        $expensePosts = ExpensePost::orderByDesc('created_at')->get()->map(fn ($post) => [
            'id' => $post->id,
            'title' => $post->title,
            'course' => $post->course,
            'year_level' => $post->year_level,
            'defense_type' => $post->defense_type,
            'amount' => (float) $post->amount,
            'description' => $post->description,
            'is_active' => $post->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Request completed successfully.',
            'data' => [
                'stats' => $stats,
                'teams' => $teams,
                'financial_records' => $financialRecords,
                'expense_posts' => $expensePosts,
                'courses' => $courses,
                'filters' => [
                    'course' => $course,
                    'status' => $status,
                    'title_id' => $titleId,
                ],
            ],
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Request completed successfully.',
            'data' => $this->getStats(),
        ]);
    }

    public function teams(Request $request): JsonResponse
    {
        $titleId = $request->query('title_id', '');
        $course = $request->query('course', '');
        $status = $request->query('status', '');

        return response()->json([
            'success' => true,
            'message' => 'Request completed successfully.',
            'data' => $this->getTeams($titleId, $course, $status),
        ]);
    }

    public function records(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Request completed successfully.',
            'data' => $this->getFinancialRecords($request),
        ]);
    }

    public function record(Request $request, FinancialRecord $financialRecord): JsonResponse
    {
        $financialRecord->load(['student', 'expensePost']);

        return response()->json([
            'success' => true,
            'message' => 'Request completed successfully.',
            'data' => $this->formatRecord($financialRecord),
        ]);
    }

    /**
     * Approving and rejecting both land the record on a new status, so one
     * PATCH carries which one. The deciding stays in the two methods that
     * already do it - this only routes to them.
     */
    public function update(Request $request, FinancialRecord $financialRecord): JsonResponse
    {
        if (! $this->covers($financialRecord)) {
            return response()->json(['success' => false, 'message' => 'That receipt is not from your sections.'], 403);
        }

        return $request->validate([
            'status' => ['required', 'in:approved,rejected'],
        ])['status'] === 'approved'
            ? $this->approve($request, $financialRecord)
            : $this->reject($request, $financialRecord);
    }

    /**
     * PATCH /financial/records - verify several waiting receipts at once.
     * Only receipts still waiting, from the CaPSA's own sections, change.
     */
    public function updateMany(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'status' => ['required', 'in:approved'],
        ]);

        $by = auth('expert')->user()->name;
        $waiting = FinancialRecord::whereIn('id', $validated['ids'])
            ->where('f_record_status', 'pending')
            ->with('student')
            ->get()
            ->filter(fn (FinancialRecord $r) => $this->covers($r));

        // A receipt with an OR number is checked against its photo, one at a
        // time; only the older ones without a number can go through unopened.
        [$records, $skipped] = $waiting->partition(fn (FinancialRecord $r) => blank($r->receipt_number));

        foreach ($records as $record) {
            $record->update(['f_record_status' => 'verified', 'verified_at' => now(), 'verified_by' => $by]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verified '.$records->count().' '.($records->count() === 1 ? 'receipt' : 'receipts').'.'
                .($skipped->isNotEmpty() ? ' '.$skipped->count().' with an OR number '.($skipped->count() === 1 ? 'needs' : 'need').' checking one by one.' : ''),
            'data' => ['verified' => $records->pluck('id')->values(), 'skipped' => $skipped->pluck('id')->values()],
        ]);
    }

    /** Whether the receipt's student is on a team in this CaPSA's sections. */
    private function covers(FinancialRecord $record): bool
    {
        $team = $record->student?->team;

        return ! $team || app(DefenseBoard::class)->covers(auth('expert')->user(), $team);
    }

    public function approve(Request $request, FinancialRecord $financialRecord): JsonResponse
    {
        /** @var CapstoneExpert $admin */
        $admin = auth('expert')->user();

        if ($financialRecord->f_record_status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'This receipt is already verified.',
            ], 422);
        }

        // A student can mistype the number they read off the receipt. The
        // CaPSA, holding the photo, puts right what they typed - saved with
        // the verification, never on its own.
        $corrected = $request->validate([
            'receipt_number' => ['sometimes', ...FinancialRecord::orNumberRules()],
        ], [
            'receipt_number.required' => 'Type the OR number printed on the receipt.',
            'receipt_number.regex' => 'Type the OR number as it is printed: letters, numbers and dashes.',
        ])['receipt_number'] ?? null;

        if ($corrected !== null) {
            $financialRecord->receipt_number = FinancialRecord::normaliseOrNumber($corrected);
        }

        // The point of the OR number: somebody looked at the photo and saw it.
        if ($financialRecord->receipt_number && ! $request->boolean('or_confirmed')) {
            return response()->json([
                'success' => false,
                'message' => 'Check that the OR number on the photo is '.$financialRecord->receipt_number.', then tick the box to verify.',
            ], 422);
        }

        $financialRecord->update([
            'f_record_status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $admin->name,
        ]);

        $financialRecord->load(['student', 'expensePost']);

        return response()->json([
            'success' => true,
            'message' => 'Receipt verified successfully.',
            'data' => $this->formatRecord($financialRecord),
        ]);
    }

    public function reject(Request $request, FinancialRecord $financialRecord): JsonResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        /** @var CapstoneExpert $admin */
        $admin = auth('expert')->user();

        if ($financialRecord->f_record_status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot reject a receipt that has already been verified.',
            ], 422);
        }

        $financialRecord->update([
            'f_record_status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'verified_at' => now(),
            'verified_by' => $admin->name,
        ]);

        $financialRecord->load(['student', 'expensePost']);

        return response()->json([
            'success' => true,
            'message' => 'Receipt rejected.',
            'data' => $this->formatRecord($financialRecord),
        ]);
    }

    public function expensePosts(): JsonResponse
    {
        $expensePosts = ExpensePost::orderByDesc('created_at')->get()->map(fn ($post) => [
            'id' => $post->id,
            'title' => $post->title,
            'course' => $post->course,
            'year_level' => $post->year_level,
            'defense_type' => $post->defense_type,
            'amount' => (float) $post->amount,
            'description' => $post->description,
            'is_active' => $post->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Request completed successfully.',
            'data' => $expensePosts,
        ]);
    }

    /**
     * POST /financial/expense-posts - a defense fee. Only Title, Pre Oral and
     * Final Oral Defense have one; Pre-proposal Defense has no fee. One fee
     * per defense per program.
     */
    public function storeExpensePost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'course' => ['required', 'string', 'in:BSIT,BSCS,BLIS,BSIS'],
            'year_level' => ['nullable', 'string', 'in:3rd,4th'],
            'defense_type' => ['required', 'string', 'in:'.implode(',', DefenseBoard::PAID_STAGES)],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'defense_type.in' => 'Pre-proposal Defense has no fee. Pick Title, Pre Oral or Final Oral Defense.',
        ]);

        if (ExpensePost::where('defense_type', $validated['defense_type'])->where('course', $validated['course'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => $validated['defense_type'].' already has a fee for '.$validated['course'].'.',
            ], 422);
        }

        /** @var CapstoneExpert $admin */
        $admin = auth('expert')->user();

        $post = ExpensePost::create([
            'admin_id' => $admin->expert_id,
            'title' => $validated['title'] ?? $validated['defense_type'].' Fee',
            'course' => $validated['course'],
            // Title Defense closes Capstone 1 (3rd year); Pre Oral and Final Oral are Capstone 2.
            'year_level' => $validated['year_level'] ?? ($validated['defense_type'] === 'Title Defense' ? '3rd' : '4th'),
            'defense_type' => $validated['defense_type'],
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense post created successfully.',
            'data' => [
                'id' => $post->id,
                'title' => $post->title,
                'course' => $post->course,
                'year_level' => $post->year_level,
                'defense_type' => $post->defense_type,
                'amount' => (float) $post->amount,
                'description' => $post->description,
                'is_active' => $post->is_active,
            ],
        ], 201);
    }

    public function updateExpensePost(Request $request, ExpensePost $expensePost): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'course' => ['required', 'string', 'in:BSIT,BSCS,BLIS,BSIS'],
            'year_level' => ['required', 'string', 'in:3rd,4th'],
            'defense_type' => ['nullable', 'string', 'in:'.implode(',', self::DEFENSE_TYPES)],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $expensePost->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Expense post updated successfully.',
            'data' => [
                'id' => $expensePost->id,
                'title' => $expensePost->title,
                'course' => $expensePost->course,
                'year_level' => $expensePost->year_level,
                'defense_type' => $expensePost->defense_type,
                'amount' => (float) $expensePost->amount,
                'description' => $expensePost->description,
                'is_active' => $expensePost->is_active,
            ],
        ]);
    }

    public function destroyExpensePost(ExpensePost $expensePost): JsonResponse
    {
        $expensePost->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense post deleted successfully.',
        ]);
    }

    private function getCourses(): array
    {
        try {
            $columnInfo = DB::select("SHOW COLUMNS FROM student_s_y WHERE Field = 'program'");
            if (! empty($columnInfo)) {
                preg_match("/^enum\((.+)\)$/", $columnInfo[0]->Type, $matches);
                if (! empty($matches[1])) {
                    return collect(str_getcsv($matches[1]))
                        ->map(fn ($v) => trim($v, "'"))
                        ->sort()
                        ->values()
                        ->toArray();
                }
            }
        } catch (\Exception $e) {
            // Fallback
        }

        return StudentSchoolYear::whereNotNull('program')
            ->distinct()
            ->pluck('program')
            ->sort()
            ->values()
            ->toArray();
    }

    private function getStats(): array
    {
        $teams = Team::with('members')->get();

        $allMemberIds = $teams->pluck('members.*.student_id')->flatten()->unique()->filter()->toArray();

        $records = FinancialRecord::whereIn('student_id', $allMemberIds)
            ->select('student_id', 'f_record_status')
            ->get()
            ->groupBy('student_id');

        $fullyPaidTeams = 0;
        $hasPendingTeams = 0;
        $noSubmissionTeams = 0;

        foreach ($teams as $team) {
            $memberIds = $team->members->pluck('student_id')->filter();
            $verifiedCount = 0;
            $pendingCount = 0;

            foreach ($memberIds as $sid) {
                $sRecords = $records->get($sid, collect());
                $hasVerified = $sRecords->where('f_record_status', 'verified')->isNotEmpty();
                $hasPending = $sRecords->where('f_record_status', 'pending')->isNotEmpty();

                if ($hasVerified) {
                    $verifiedCount++;
                } elseif ($hasPending) {
                    $pendingCount++;
                }
            }

            if ($verifiedCount === $memberIds->count() && $memberIds->isNotEmpty()) {
                $fullyPaidTeams++;
            } elseif ($verifiedCount > 0 || $pendingCount > 0) {
                $hasPendingTeams++;
            } else {
                $noSubmissionTeams++;
            }
        }

        $totalStudents = Student::count();
        $totalTeams = $teams->count();

        $allStudentIdsWithRecords = FinancialRecord::distinct()->pluck('student_id');
        $verifiedStudentIds = FinancialRecord::where('f_record_status', 'verified')->distinct()->pluck('student_id');

        $paidStudents = $verifiedStudentIds->count();
        $pendingStudents = $allStudentIdsWithRecords->diff($verifiedStudentIds)->count();
        $noSubmissionStudents = $totalStudents - $allStudentIdsWithRecords->count();

        $teamPercentFull = $totalTeams > 0 ? round(($fullyPaidTeams / $totalTeams) * 100, 2) : 0;
        $teamPercentPending = $totalTeams > 0 ? round(($hasPendingTeams / $totalTeams) * 100, 2) : 0;
        $teamPercentNone = $totalTeams > 0 ? round(($noSubmissionTeams / $totalTeams) * 100, 2) : 0;

        $studPercentPaid = $totalStudents > 0 ? round(($paidStudents / $totalStudents) * 100, 2) : 0;
        $studPercentPending = $totalStudents > 0 ? round(($pendingStudents / $totalStudents) * 100, 2) : 0;
        $studPercentNone = $totalStudents > 0 ? round(($noSubmissionStudents / $totalStudents) * 100, 2) : 0;

        return [
            'total_students' => $totalStudents,
            'total_teams' => $totalTeams,
            'paid_students' => $paidStudents,
            'pending_students' => $pendingStudents,
            'no_submission_students' => $noSubmissionStudents,
            'fully_paid_teams' => $fullyPaidTeams,
            'has_pending_teams' => $hasPendingTeams,
            'no_submission_teams' => $noSubmissionTeams,
            'team_percentages' => [
                'fully_paid' => $teamPercentFull,
                'has_pending' => $teamPercentPending,
                'no_submission' => $teamPercentNone,
            ],
            'student_percentages' => [
                'paid' => $studPercentPaid,
                'pending' => $studPercentPending,
                'no_submission' => $studPercentNone,
            ],
        ];
    }

    private function getTeams(string $titleId = '', string $course = '', string $status = ''): array
    {
        $teams = Team::with(['members', 'leader'])->get();

        $allMemberIds = $teams->pluck('members.*.student_id')->flatten()->unique()->filter()->toArray();

        $records = FinancialRecord::whereIn('student_id', $allMemberIds)
            ->select('student_id', 'f_record_status', 'amount', 'expense_post_id')
            ->get()
            ->groupBy('student_id');

        $teamCourseCache = [];
        $allPostIds = collect();

        // Pre-compute stats per team
        $teamData = [];
        foreach ($teams as $team) {
            $memberIds = $team->members->pluck('student_id')->filter();
            $memberCount = $memberIds->count();
            $verifiedCount = 0;
            $pendingCount = 0;
            $verifiedAmount = 0;
            $postIds = collect();

            foreach ($memberIds as $sid) {
                $sRecords = $records->get($sid, collect());
                $hasVerified = $sRecords->where('f_record_status', 'verified')->isNotEmpty();
                $hasPending = $sRecords->where('f_record_status', 'pending')->isNotEmpty();

                if ($hasVerified) {
                    $verifiedCount++;
                    $verifiedAmount += $sRecords->where('f_record_status', 'verified')->sum('amount');
                } elseif ($hasPending) {
                    $pendingCount++;
                }

                $postIds = $postIds->concat(
                    $sRecords->pluck('expense_post_id')->filter()
                );
            }

            $uniquePostIds = $postIds->unique()->filter();
            $allPostIds = $allPostIds->concat($uniquePostIds);

            $teamData[$team->team_id] = [
                'member_count' => $memberCount,
                'verified_count' => $verifiedCount,
                'pending_count' => $pendingCount,
                'verified_amount' => $verifiedAmount,
                'post_ids' => $uniquePostIds,
            ];
        }

        // Load all needed expense post titles in one query
        $expensePostTitles = ExpensePost::whereIn('id', $allPostIds->unique()->filter())->pluck('title', 'id');

        // Load team courses in bulk
        $teamCourses = StudentSchoolYear::whereIn('team_id', $teams->pluck('team_id'))
            ->whereNotNull('program')
            ->get()
            ->keyBy('team_id');

        return $teams
            ->filter(function ($team) use ($teamData, $titleId, $course, $status, $teamCourses) {
                $data = $teamData[$team->team_id];
                $memberCount = $data['member_count'];
                $verifiedCount = $data['verified_count'];
                $pendingCount = $data['pending_count'];
                $postIds = $data['post_ids'];

                if ($titleId && ! $postIds->contains((int) $titleId)) {
                    return false;
                }

                $teamCourse = $teamCourses->get($team->team_id)?->program
                    ?? $team->members->first()?->course
                    ?? 'N/A';

                if ($course && $teamCourse !== $course) {
                    return false;
                }

                $teamStatus = 'no_submission';
                if ($verifiedCount === $memberCount && $memberCount > 0) {
                    $teamStatus = 'fully_paid';
                } elseif ($verifiedCount > 0 || $pendingCount > 0) {
                    $teamStatus = 'has_pending';
                }

                if ($status && $teamStatus !== $status) {
                    return false;
                }

                return true;
            })
            ->map(function ($team) use ($teamData, $expensePostTitles, $teamCourses) {
                $data = $teamData[$team->team_id];
                $memberCount = $data['member_count'];
                $verifiedCount = $data['verified_count'];
                $pendingCount = $data['pending_count'];
                $verifiedAmount = $data['verified_amount'];
                $postIds = $data['post_ids'];
                $uniquePostIds = $postIds;

                $teamTitles = $uniquePostIds->map(fn ($id) => $expensePostTitles[$id] ?? null)->filter()->values();
                $teamTitle = $teamTitles->isNotEmpty() ? $teamTitles->implode(', ') : '—';

                $teamCourse = $teamCourses->get($team->team_id)?->program
                    ?? $team->members->first()?->course
                    ?? 'N/A';

                $dataStatus = 'no_submission';
                if ($verifiedCount === $memberCount && $memberCount > 0) {
                    $dataStatus = 'fully_paid';
                } elseif ($verifiedCount > 0 || $pendingCount > 0) {
                    $dataStatus = 'has_pending';
                }

                $initials = collect(explode(' ', $team->team_name))
                    ->map(fn ($w) => strtoupper(substr($w, 0, 1)))
                    ->take(2)
                    ->join('');

                return [
                    'team_id' => $team->team_id,
                    'team_name' => $team->team_name,
                    'initials' => $initials,
                    'course' => $teamCourse,
                    'member_count' => $memberCount,
                    'verified_count' => $verifiedCount,
                    'pending_count' => $pendingCount,
                    'total_verified_amount' => (float) $totalVerifiedAmount,
                    'title' => $teamTitle,
                    'status' => $dataStatus,
                    'post_ids' => $postIds->values()->toArray(),
                ];
            })
            ->values()
            ->toArray();
    }

    private function getFinancialRecords(Request $request): array
    {
        $query = FinancialRecord::with(['student', 'expensePost'])
            ->orderByDesc('uploaded_at');

        if ($request->filled('status')) {
            $query->where('f_record_status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                    ->orWhere('payment_for', 'like', "%{$search}%")
                    ->orWhereHas('student', fn ($q) => $q->where('f_name', 'like', "%{$search}%")
                        ->orWhere('l_name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('title_id')) {
            $query->where('expense_post_id', $request->title_id);
        }

        return $query->get()->map(fn ($record) => $this->formatRecord($record))->toArray();
    }

    private function formatRecord(FinancialRecord $record): array
    {
        $statusColor = match ($record->f_record_status) {
            'verified' => 'badge-green',
            'pending' => 'badge-amber',
            'rejected' => 'badge-red',
            default => 'badge-amber',
        };
        $statusText = match ($record->f_record_status) {
            'verified' => 'Verified',
            'pending' => 'Pending',
            'rejected' => 'Rejected',
            default => 'Pending',
        };

        $studentName = $record->student
            ? ($record->student->f_name.' '.$record->student->l_name)
            : 'Unknown';

        $expenseTitle = $record->expensePost?->title ?? $record->payment_for ?? 'N/A';
        $teamName = $record->student?->team?->team_name ?? 'N/A';

        return [
            'id' => $record->id,
            'student_id' => $record->student_id,
            'expense_post_id' => $record->expense_post_id,
            'receipt_image' => $record->receipt_image ? asset('storage/'.$record->receipt_image) : null,
            'receipt_number' => $record->receipt_number,
            'status' => $record->f_record_status,
            'status_color' => $statusColor,
            'status_text' => $statusText,
            'payment_for' => $record->payment_for,
            'expense_title' => $expenseTitle,
            'amount' => (float) ($record->amount ?? 0),
            'description' => $record->description,
            'rejection_reason' => $record->rejection_reason,
            'student_name' => $studentName,
            'team_name' => $teamName,
            'uploaded_at' => $record->uploaded_at?->format('Y-m-d H:i:s'),
            'uploaded_at_formatted' => $record->uploaded_at
                ? Carbon::parse($record->uploaded_at)->format('M d, Y')
                : 'N/A',
            'verified_at' => $record->verified_at?->format('Y-m-d H:i:s'),
            'verified_at_formatted' => $record->verified_at
                ? Carbon::parse($record->verified_at)->format('M d, Y')
                : null,
            'verified_by' => $record->verified_by,
            'verified_at_display' => $record->verified_at
                ? Carbon::parse($record->verified_at)->format('M d, Y')
                : 'N/A',
        ];
    }
}
