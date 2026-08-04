<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    protected array $allowedTypes = [
        'team_joined',
        'team_left',
        'team_status_changed',
        'team_confirmed',
        'expert_removed',
    ];

    public function index(Request $request)
    {
        $query = ActivityLog::with('student.currentTeam')
            ->whereIn('type', $this->allowedTypes)
            ->latest('created_at');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($q) use ($search) {
                        $q->where('f_name', 'like', "%{$search}%")
                            ->orWhere('l_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        $logs = $query->paginate(10);

        $logDataJson = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'time' => $log->created_at->format('M j, Y · H:i'),
                'student' => ($log->student?->f_name ?? '').' '.($log->student?->l_name ?? ''),
                'team' => $log->student?->currentTeam?->team_name ?? $log->metadata['team_name'] ?? 'N/A',
                'action' => $log->type,
                'description' => $log->description,
            ];
        })->toJson();

        return view('admin.activitylog', [
            'logs' => $logs,
            'types' => collect($this->allowedTypes),
            'logDataJson' => $logDataJson,
        ]);
    }
}
