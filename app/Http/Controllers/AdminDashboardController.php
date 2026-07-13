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
        $pendingApprovals = CapstoneExpert::where('approved_status', false)
            ->whereNull('rejection_reason')
            ->count();
        $archivedCapstones = ArchivedDocument::count();

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
            'pendingApprovals',
            'currentSchoolYearLabel',
            'pendingExperts',
            'recentActivity',
            'archivedCapstones',
        ));
    }

    private function buildRecentActivity(): array
    {
        $activities = [];

        $recentSignups = CapstoneExpert::where('approved_status', false)
            ->whereNull('rejection_reason')
            ->latest('created_at')
            ->take(5)
            ->get();

        foreach ($recentSignups as $e) {
            $activities[] = [
                'icon' => 'person',
                'iconBg' => 'rgba(201,150,12,0.1)',
                'iconColor' => 'var(--accent3)',
                'iconViewBox' => '0 0 24 24',
                'iconPath' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
                'text' => "<strong>{$e->first_name} {$e->last_name}</strong> requested an expert account",
                'time' => $e->created_at,
            ];
        }

        $recentApprovals = CapstoneExpert::where('approved_status', true)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        foreach ($recentApprovals as $e) {
            $activities[] = [
                'icon' => 'check',
                'iconBg' => 'var(--green-bg)',
                'iconColor' => 'var(--green)',
                'iconViewBox' => '0 0 24 24',
                'iconPath' => '<polyline points="20 6 9 17 4 12"/>',
                'text' => "<strong>{$e->first_name} {$e->last_name}</strong> was approved as an expert",
                'time' => $e->created_at,
            ];
        }

        $assignments = TeamExpertAssignment::with(['team', 'expert', 'role'])
            ->latest('created_at')
            ->take(10)
            ->get();

        foreach ($assignments as $a) {
            $teamName = $a->team?->team_name ?? 'Unknown';
            $expertName = $a->expert ? "{$a->expert->first_name} {$a->expert->last_name}" : 'Unknown';
            $roleName = $a->role?->role_name ?? '';

            if ($a->status === 'pending') {
                $activities[] = [
                    'icon' => 'clipboard',
                    'iconBg' => 'rgba(201,150,12,0.1)',
                    'iconColor' => 'var(--accent3)',
                    'iconViewBox' => '0 0 24 24',
                    'iconPath' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
                    'text' => "<strong>Team {$teamName}</strong> applied for {$expertName} as ".Str::title($roleName),
                    'time' => $a->created_at,
                ];
            }
        }

        $logEntries = ActivityLog::latest('created_at')
            ->take(10)
            ->get();

        foreach ($logEntries as $log) {
            $activities[] = [
                'icon' => 'activity',
                'iconBg' => 'var(--blue-bg)',
                'iconColor' => 'var(--blue)',
                'iconViewBox' => '0 0 24 24',
                'iconPath' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
                'text' => $log->description,
                'time' => $log->created_at,
            ];
        }

        usort($activities, function ($a, $b) {
            return $b['time']->timestamp <=> $a['time']->timestamp;
        });

        return array_slice($activities, 0, 10);
    }
}
