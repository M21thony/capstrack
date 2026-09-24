<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ArchivedDocument;
use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Support\ActivityScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $now = now();
        $currentYear = (int) $now->format('Y');
        $currentMonth = (int) $now->format('n');

        $syStart = $currentMonth >= 6 ? $currentYear : $currentYear - 1;
        $syEnd = $syStart + 1;
        $currentSchoolYearLabel = 'SY '.$syStart.'–'.$syEnd;

        $totalStudents = Student::count();
        $totalExperts = CapstoneExpert::where('approved_status', true)->count();
        $activeTeams = Team::where('is_confirmed', false)->where('team_status', '!=', 'Full')->count();
        $completedTeams = Team::where('is_confirmed', true)->count();
        $pendingVerificationTeams = Team::submittedForChecking()->where('is_confirmed', false)->count();
        $pendingApprovals = CapstoneExpert::where('approved_status', false)
            ->whereNull('rejection_reason')
            ->count();
        $archivedCapstones = ArchivedDocument::count();

        $selectedYear = request('year');
        $years = ArchivedDocument::selectRaw('DISTINCT academic_year')
            ->orderBy('academic_year', 'desc')
            ->pluck('academic_year');
        $yearlyQuery = ArchivedDocument::selectRaw('academic_year, COUNT(*) as total')
            ->groupBy('academic_year')
            ->orderBy('academic_year');
        if ($selectedYear) {
            $yearlyQuery->where('academic_year', $selectedYear);
        }
        $yearlyArchive = $yearlyQuery->pluck('total', 'academic_year');
        $maxYearlyCount = $yearlyArchive->max() ?: 1;

        $verifiedTeams = Team::where('is_confirmed', true)->get();
        $tagCounts = [];
        foreach ($verifiedTeams as $team) {
            if ($team->tags && is_array($team->tags)) {
                foreach ($team->tags as $tag) {
                    $tag = trim($tag);
                    if ($tag !== '') {
                        $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
                    }
                }
            }
        }
        arsort($tagCounts);
        $totalTags = array_sum($tagCounts);
        $colorPalette = ['#1d4ed8', '#7c3aed', '#16a34a', '#b45309', '#c9960c', '#0891b2', '#dc2626', '#ca8a04', '#9333ea', '#0d9488'];
        $topTags = array_slice($tagCounts, 0, 10, true);
        $otherCount = $totalTags - array_sum($topTags);
        if ($otherCount > 0) {
            $topTags['Others'] = $otherCount;
        }
        $categories = [];
        $cumulativeOffset = 25;
        $i = 0;
        foreach ($topTags as $tag => $count) {
            $pct = $totalTags > 0 ? round(($count / $totalTags) * 100) : 0;
            $categories[] = [
                'name' => $tag,
                'count' => $count,
                'percentage' => $pct,
                'color' => $colorPalette[$i % count($colorPalette)],
                'dasharray' => max($pct, 1).' '.max(100 - $pct, 0),
                'dashoffset' => -$cumulativeOffset,
            ];
            $cumulativeOffset += $pct;
            $i++;
        }

        $pendingExperts = CapstoneExpert::where('approved_status', false)
            ->whereNull('rejection_reason')
            ->with(['roles', 'expertise'])
            ->latest('created_at')
            ->take(5)
            ->get();

        $recentActivity = $this->buildRecentActivity();

        return view('capsa.dashboard', compact(
            'totalStudents',
            'totalExperts',
            'activeTeams',
            'completedTeams',
            'pendingVerificationTeams',
            'pendingApprovals',
            'currentSchoolYearLabel',
            'pendingExperts',
            'recentActivity',
            'archivedCapstones',
            'categories',
            'totalTags',
            'years',
            'yearlyArchive',
            'maxYearlyCount',
            'selectedYear',
        ));
    }

    /**
     * The dashboard feed, newest first: expert sign-ups and approvals, teams
     * applying for experts, and what students did (activity_log). Each item
     * names its type, so the feed draws its icon (App\Support\ActivityIcons),
     * and carries its time as a date - it used to sort on '2 weeks ago' as
     * text, which put entries in alphabetical order rather than by time.
     */
    private function buildRecentActivity(): array
    {
        $activities = collect();
        $name = fn ($e) => e(trim(($e->first_name ?? '').' '.($e->last_name ?? '')));

        CapstoneExpert::where('approved_status', false)
            ->whereNull('rejection_reason')
            ->latest('created_at')
            ->take(5)
            ->get()
            ->each(fn ($e) => $activities->push([
                'type' => 'expert_requested',
                'text' => '<strong>'.$name($e).'</strong> requested an expert account',
                'at' => $e->created_at,
            ]));

        CapstoneExpert::where('approved_status', true)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->each(fn ($e) => $activities->push([
                'type' => 'expert_approved',
                'text' => '<strong>'.$name($e).'</strong> was approved as an expert',
                'at' => $e->created_at,
            ]));

        TeamExpertAssignment::with(['team', 'expert', 'role', 'expertRole.role'])
            ->where('status', 'pending')
            ->latest('created_at')
            ->take(10)
            ->get()
            ->each(function ($a) use ($activities, $name) {
                // role() is belongsToMany (via expert_roles) - always a collection, never a single model.
                $roleName = $a->role->first()?->role_name ?? '';

                $activities->push([
                    'type' => 'expert_application',
                    'text' => '<strong>Team '.e($a->team?->team_name ?? 'Unknown').'</strong> applied for '
                        .($a->expert ? $name($a->expert) : 'an expert').($roleName ? ' as '.e(Str::title($roleName)) : ''),
                    'at' => $a->created_at,
                ]);
            });

        // Same rule as the bell and the Activity Log page: a CaPSA sees the
        // sections they cover, the admin sees everything.
        $visibleIds = ActivityScope::studentIdsFor(Auth::guard('expert')->user());

        ActivityLog::when($visibleIds !== null, fn ($q) => $q->whereIn('student_id', $visibleIds))
            ->latest('created_at')
            ->take(10)
            ->get()
            ->each(fn ($log) => $activities->push([
                'type' => $log->type,
                // Written by the app, which escapes what it puts in.
                'text' => $log->description,
                'at' => $log->created_at,
            ]));

        return $activities
            ->filter(fn ($a) => $a['at'] !== null)
            ->sortByDesc(fn ($a) => $a['at']->timestamp)
            ->values()
            ->take(12)
            ->toArray();
    }
}
