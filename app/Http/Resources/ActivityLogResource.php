<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $icon = $this->resolveIcon();

        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'type' => $this->type,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'icon' => $icon,
            'time_ago' => $this->created_at->diffForHumans(),
            'created_at' => $this->created_at,
        ];
    }

    private function resolveIcon(): array
    {
        return match ($this->type) {
            'team_created', 'team_joined' => [
                'bg' => 'var(--purple-bg)',
                'svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--purple)"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>',
            ],
            'team_left' => [
                'bg' => 'rgba(239,68,68,0.1)',
                'svg' => '<svg viewBox="0 0 24 24" style="stroke:#ef4444"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
            ],
            'team_confirmed' => [
                'bg' => 'rgba(22,163,74,0.1)',
                'svg' => '<svg viewBox="0 0 24 24" style="stroke:#16a34a"><polyline points="20 6 9 17 4 12"/></svg>',
            ],
            'member_removed' => [
                'bg' => 'rgba(239,68,68,0.1)',
                'svg' => '<svg viewBox="0 0 24 24" style="stroke:#ef4444"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
            ],
            'leadership_transferred' => [
                'bg' => 'rgba(251,191,36,0.15)',
                'svg' => '<svg viewBox="0 0 24 24" style="stroke:#fbbf24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            ],
            'request_accepted' => [
                'bg' => 'rgba(22,163,74,0.1)',
                'svg' => '<svg viewBox="0 0 24 24" style="stroke:#16a34a"><polyline points="20 6 9 17 4 12"/></svg>',
            ],
            'invitation_sent' => [
                'bg' => 'var(--accent-glow)',
                'svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--accent)"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
            ],
            'post_created' => [
                'bg' => 'var(--accent-glow)',
                'svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--accent)"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="m18.5 2.5 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
            ],
            'post_updated' => [
                'bg' => 'var(--amber-bg)',
                'svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--amber)"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>',
            ],
            'request_sent' => [
                'bg' => 'var(--accent-glow)',
                'svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--accent)"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
            ],
            'invitation_declined', 'request_declined', 'post_deleted' => [
                'bg' => 'rgba(239,68,68,0.1)',
                'svg' => '<svg viewBox="0 0 24 24" style="stroke:#ef4444"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
            ],
            'team_status_changed' => [
                'bg' => 'var(--amber-bg)',
                'svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--amber)"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>',
            ],
            'member_count_changed' => [
                'bg' => 'var(--purple-bg)',
                'svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--purple)"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            ],
            'team_auto_closed' => [
                'bg' => 'rgba(239,68,68,0.1)',
                'svg' => '<svg viewBox="0 0 24 24" style="stroke:#ef4444"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
            ],
            default => [
                'bg' => 'var(--accent-glow)',
                'svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--accent)"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
            ],
        };
    }
}
