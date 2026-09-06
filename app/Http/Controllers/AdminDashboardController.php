<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ArchivedDocument;
use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
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
        $activeTeams = Team::where('is_confirmed', false)->where('team_status', '!=', 'closed')->count();
        $completedTeams = Team::where('is_confirmed', true)->count();
        $pendingVerificationTeams = Team::where('team_status', 'complete')->where('is_confirmed', false)->count();
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

        return view('admin.dashboard', compact(
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

    private function buildRecentActivity(): array
    {
        $activities = collect();

        CapstoneExpert::where('approved_status', false)
            ->whereNull('rejection_reason')
            ->latest('created_at')
            ->take(5)
            ->get()
            ->each(function ($e) use ($activities) {
                $activities->push([
                    'iconSvg' => '<svg viewBox="0 0 24 24" style="stroke:var(--accent3);width:13px;height:13px;fill:none;stroke-width:2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
                    'text' => "<strong>{$e->first_name} {$e->last_name}</strong> requested an expert account",
                    'time' => $e->created_at?->diffForHumans(),
                ]);
            });

        CapstoneExpert::where('approved_status', true)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->each(function ($e) use ($activities) {
                $activities->push([
                    'iconSvg' => '<svg viewBox="0 0 24 24" style="stroke:var(--green);width:13px;height:13px;fill:none;stroke-width:2.5"><polyline points="20 6 9 17 4 12"/></svg>',
                    'text' => "<strong>{$e->first_name} {$e->last_name}</strong> was approved as an expert",
                    'time' => $e->created_at?->diffForHumans(),
                ]);
            });

        TeamExpertAssignment::with(['team', 'expert', 'role'])
            ->latest('created_at')
            ->take(10)
            ->get()
            ->each(function ($a) use ($activities) {
                $teamName = $a->team?->team_name ?? 'Unknown';
                $expertName = $a->expert ? "{$a->expert->first_name} {$a->expert->last_name}" : 'Unknown';
                // role() is belongsToMany (via expert_roles) — always a collection, never a single model.
                $roleName = $a->role->first()?->role_name ?? '';

                if ($a->status === 'pending') {
                    $activities->push([
                        'iconSvg' => '<svg viewBox="0 0 24 24" style="stroke:var(--accent3);width:13px;height:13px;fill:none;stroke-width:2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
                        'text' => "<strong>Team {$teamName}</strong> applied for {$expertName} as ".Str::title($roleName),
                        'time' => $a->created_at?->diffForHumans(),
                    ]);
                }
            });

        ActivityLog::latest('created_at')
            ->take(10)
            ->get()
            ->each(function ($log) use ($activities) {
                $activities->push([
                    'iconSvg' => '<svg viewBox="0 0 24 24" style="stroke:var(--blue);width:13px;height:13px;fill:none;stroke-width:2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
                    'text' => $log->description,
                    'time' => $log->created_at?->diffForHumans(),
                ]);
            });

        return $activities
            ->sortByDesc(function ($a) {
                return $a['time'] ?? '';
            })
            ->values()
            ->take(10)
            ->toArray();
    }
}
