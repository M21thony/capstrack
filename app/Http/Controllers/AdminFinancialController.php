<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use App\Models\ExpensePost;
use App\Models\FinancialRecord;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminFinancialController extends Controller
{
    public function index(Request $request): View
    {
        $course = $request->query('course', '');
        $status = $request->query('status', '');
        $titleId = $request->query('title_id', '');

        // Get available courses from the database column definition
        $courses = collect();
        try {
            $columnInfo = DB::select("SHOW COLUMNS FROM student_s_y WHERE Field = 'program'");
            if (! empty($columnInfo)) {
                preg_match("/^enum\((.+)\)$/", $columnInfo[0]->Type, $matches);
                if (! empty($matches[1])) {
                    $courses = collect(str_getcsv($matches[1]))
                        ->map(fn ($v) => trim($v, "'"))
                        ->sort()
                        ->values();
                }
            }
        } catch (\Exception $e) {
            $courses = StudentSchoolYear::whereNotNull('program')
                ->distinct()
                ->pluck('program')
                ->sort()
                ->values();
        }

        // Load teams and related data
        $teams = Team::with(['members', 'leader'])->get();

        // Pre-compute team stats in bulk to eliminate N+1 queries in the view
        $allMemberIds = $teams->pluck('members.*.student_id')->flatten()->unique()->filter()->toArray();

        // Single query: all relevant financial records
        $records = FinancialRecord::whereIn('student_id', $allMemberIds)
            ->select('student_id', 'f_record_status', 'amount', 'expense_post_id')
            ->get()
            ->groupBy('student_id');

        $teamStats = [];
        foreach ($teams as $team) {
            $memberIds = $team->members->pluck('student_id')->filter();
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

            $teamStats[$team->team_id] = [
                'verified_count' => $verifiedCount,
                'pending_count' => $pendingCount,
                'verified_amount' => $verifiedAmount,
                'post_ids' => $postIds->unique()->filter(),
            ];
        }

        // Load all expense post titles used by any team (one query instead of per-team)
        $allPostIds = collect($teamStats)->pluck('post_ids')->flatten()->unique()->filter()->toArray();
        $expensePostTitles = ExpensePost::whereIn('id', $allPostIds)->pluck('title', 'id');

        // Compute overview stats from pre-computed team data
        $fullyPaidTeams = 0;
        $hasPendingTeams = 0;
        $noSubmissionTeams = 0;

        foreach ($teams as $team) {
            $stats = $teamStats[$team->team_id];
            $memberCount = $team->members->count();

            if ($stats['verified_count'] === $memberCount && $memberCount > 0) {
                $fullyPaidTeams++;
            } elseif ($stats['verified_count'] > 0 || $stats['pending_count'] > 0) {
                $hasPendingTeams++;
            } else {
                $noSubmissionTeams++;
            }
        }

        $financialRecords = FinancialRecord::with(['student', 'expensePost'])->orderByDesc('uploaded_at')->get();

        $expensePosts = ExpensePost::orderByDesc('created_at')->get();

        $totalStudents = Student::count();
        $totalTeams = $teams->count();

        $allStudentIdsWithRecords = FinancialRecord::distinct()->pluck('student_id');
        $verifiedStudentIds = FinancialRecord::where('f_record_status', 'verified')->distinct()->pluck('student_id');

        $paidStudents = $verifiedStudentIds->count();
        $pendingStudents = $allStudentIdsWithRecords->diff($verifiedStudentIds)->count();
        $noSubmissionStudents = $totalStudents - $allStudentIdsWithRecords->count();

        // Pre-compute team details for the interactive Team Details modal
        $teamDetails = [];
        $teamCourses = StudentSchoolYear::whereIn('team_id', $teams->pluck('team_id'))
            ->get()
            ->groupBy('team_id');

        foreach ($teams as $team) {
            $memberIds = $team->members->pluck('student_id')->filter();
            $membersData = [];
            foreach ($team->members as $member) {
                if ($member->student_id) {
                    $membersData[(int) $member->student_id] = ($member->f_name ?? '').' '.($member->l_name ?? '');
                }
            }

            $ssy = $teamCourses->get($team->team_id)?->first();
            $leaderName = $team->leader
                ? (($team->leader->f_name ?? '').' '.($team->leader->l_name ?? ''))
                : 'N/A';

            $postIds = $teamStats[$team->team_id]['post_ids'] ?? collect();
            $titlesData = [];

            foreach ($postIds as $pid) {
                $memberStatuses = [];
                foreach ($memberIds as $mid) {
                    $mid = (int) $mid;
                    $memberName = $membersData[$mid] ?? 'Unknown';
                    $sRecords = $records->get($mid, collect());
                    $postRecords = $sRecords->where('expense_post_id', $pid);

                    if ($postRecords->where('f_record_status', 'verified')->isNotEmpty()) {
                        $status = 'Verified';
                    } elseif ($postRecords->where('f_record_status', 'pending')->isNotEmpty()) {
                        $status = 'Has Pending';
                    } else {
                        $status = 'No Submission';
                    }

                    $memberStatuses[] = [
                        'name' => $memberName,
                        'status' => $status,
                    ];
                }

                $titlesData[] = [
                    'post_id' => (int) $pid,
                    'title' => $expensePostTitles[$pid] ?? 'Unknown',
                    'members' => $memberStatuses,
                ];
            }

            $teamDetails[] = [
                'id' => (int) $team->team_id,
                'name' => $team->team_name,
                'leader' => $leaderName,
                'course' => $ssy?->program ?? $team->members->first()?->course ?? 'N/A',
                'section' => $team->members->first()?->year_section ?? 'N/A',
                'member_count' => $memberIds->count(),
                'titles' => $titlesData,
            ];
        }

        $teamDetailsJson = json_encode($teamDetails);

        return view('capsa.financial', compact(
            'teams',
            'teamStats',
            'expensePostTitles',
            'financialRecords',
            'expensePosts',
            'totalStudents',
            'totalTeams',
            'paidStudents',
            'pendingStudents',
            'noSubmissionStudents',
            'fullyPaidTeams',
            'hasPendingTeams',
            'noSubmissionTeams',
            'courses',
            'course',
            'status',
            'titleId',
            'teamDetailsJson',
        ));
    }

    public function storeExpensePost(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'course' => ['required', 'string', 'in:BSIT,BSCS,BLIS,BSIS'],
            'year_level' => ['required', 'string', 'in:3rd,4th'],
            'defense_type' => ['nullable', 'string', 'in:Pre-proposal Defense,Title Defense,Pre Oral Defense,Final Oral Defense'],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var CapstoneExpert $admin */
        $admin = auth()->user();

        ExpensePost::create([
            'admin_id' => $admin->expert_id,
            'title' => $validated['title'],
            'course' => $validated['course'],
            'year_level' => $validated['year_level'],
            'defense_type' => $validated['defense_type'] ?? null,
            'amount' => $validated['amount'],
            'description' => $validated['description'],
        ]);

        return redirect()->back()->with('success', 'Expense post created successfully.');
    }

    public function updateExpensePost(Request $request, ExpensePost $expensePost): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'course' => ['required', 'string', 'in:BSIT,BSCS,BLIS,BSIS'],
            'year_level' => ['required', 'string', 'in:3rd,4th'],
            'defense_type' => ['nullable', 'string', 'in:Pre-proposal Defense,Title Defense,Pre Oral Defense,Final Oral Defense'],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $expensePost->update($validated);

        return redirect()->back()->with('success', 'Expense post updated successfully.');
    }

    public function destroyExpensePost(ExpensePost $expensePost): RedirectResponse
    {
        $expensePost->delete();

        return redirect()->back()->with('success', 'Expense post deleted successfully.');
    }

    /**
     * Approving and rejecting both land the record on a new status, so one
     * PATCH carries which one. The deciding stays in the two methods that
     * already do it - this only routes to them.
     */
    public function updateReceipt(Request $request, FinancialRecord $financialRecord): RedirectResponse
    {
        return $request->validate([
            'status' => ['required', 'in:approved,rejected'],
        ])['status'] === 'approved'
            ? $this->approveReceipt($financialRecord)
            : $this->rejectReceipt($request, $financialRecord);
    }

    public function approveReceipt(FinancialRecord $financialRecord): RedirectResponse
    {
        /** @var CapstoneExpert $admin */
        $admin = auth()->user();

        $financialRecord->update([
            'f_record_status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $admin->name,
        ]);

        return redirect()->back()->with('success', 'Receipt verified successfully.');
    }

    public function rejectReceipt(Request $request, FinancialRecord $financialRecord): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        /** @var CapstoneExpert $admin */
        $admin = auth()->user();

        $financialRecord->update([
            'f_record_status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'verified_at' => now(),
            'verified_by' => $admin->name,
        ]);

        return redirect()->back()->with('success', 'Receipt rejected.');
    }
}
