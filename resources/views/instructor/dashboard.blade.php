<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CapsTRACK Expert Portal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/expert.css') }}" />
<x-dashboard.styles />
</head>
<body>
@include('instructor.partials.sidebar')
@include('partials.welcome-modal')

<div class="main">
  <div class="topbar">
    <div class="page-title">Dashboard</div>
    <div class="topbar-actions">
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="grid-4" style="margin-bottom:16px">
      <x-dashboard.stat-chip value="{{ $pendingApplications }}" label="Pending Applications" sub="{{ $pendingApplicationsNew }} new this week" color="var(--accent3)" />
      <x-dashboard.stat-chip
        value="{{ $docsForReview }}"
        label="Docs for Review"
        sub="{{ $oldestDocReview ? 'Oldest: '.$oldestDocReview->reviewed_at?->diffForHumans() : 'No pending reviews' }}"
        color="var(--red)"
      />
      <x-dashboard.stat-chip value="{{ $upcomingConsults }}" label="Upcoming Consults" sub="Available slots" color="var(--blue)" />
      <x-dashboard.stat-chip value="{{ $teamsAdvised }}" label="Teams Advised" sub="Current" color="var(--green)" />
    </div>

    <div class="grid-2" style="margin-bottom:16px">
      <x-dashboard.info-card
        title="Announcements"
        :badge="$announcements->isNotEmpty() ? 'New' : null"
        :empty="$announcements->isEmpty()"
        emptyMessage="No announcements."
        scrollable
        maxHeight="320"
      >
        @foreach ($announcements as $announcement)
          @php
            $priorityColor = match ($announcement->priority) {
              'urgent' => '#dc2626',
              'important' => '#d97706',
              default => '#6b7280',
            };
            $priorityBg = match ($announcement->priority) {
              'urgent' => 'rgba(220,38,38,0.08)',
              'important' => 'rgba(217,119,6,0.08)',
              default => 'transparent',
            };
          @endphp
          <div class="activity-item" style="border-left:3px solid {{ $priorityColor }};padding-left:12px;margin-bottom:8px;border-bottom:1px solid var(--border);padding-bottom:10px;">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;flex-wrap:wrap;">
              <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.3px;padding:1px 6px;border-radius:4px;background:{{ $priorityBg }};color:{{ $priorityColor }};">{{ ucfirst($announcement->priority) }}</span>
              @if ($announcement->target_audience)
                @foreach ((array) $announcement->target_audience as $ta)
                  @if (str_starts_with($ta, 'section:'))
                    <span style="font-size:10px;font-weight:600;padding:1px 6px;border-radius:4px;background:rgba(16,185,129,0.1);color:#059669;">{{ substr($ta, 8) }}</span>
                  @else
                    <span style="font-size:10px;font-weight:600;padding:1px 6px;border-radius:4px;background:rgba(99,102,241,0.1);color:#6366f1;">{{ $ta }}</span>
                  @endif
                @endforeach
              @endif
              @if ($announcement->team)
                <span style="font-size:10px;font-weight:600;padding:1px 6px;border-radius:4px;background:rgba(59,130,246,0.1);color:#2563eb;">@ {{ $announcement->team->team_name }}</span>
              @endif
            </div>
            <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:2px;">{{ $announcement->title }}</div>
            <div style="font-size:12px;color:var(--text2);line-height:1.5;margin-bottom:4px;">{{ $announcement->body }}</div>
            <div style="font-size:11px;color:var(--text3);display:flex;gap:8px;">
              <span>{{ $announcement->created_at->format('M j, Y') }}</span>
            </div>
          </div>
        @endforeach
      </x-dashboard.info-card>

      <x-dashboard.info-card
        title="Upcoming Consultations"
        :actionUrl="route('instructor.consultations')"
        actionText="View All"
        :empty="$consultations->isEmpty()"
        emptyMessage="No upcoming consultations."
      >
        <div class="gap-12">
          @foreach ($consultations as $consult)
            <div class="consult-card">
              <div class="consult-header">
                <div>
                  <div class="consult-team">Schedule #{{ $consult->schedule_id }}</div>
                  <div class="consult-meta">{{ $consult->specific_date ? \Carbon\Carbon::parse($consult->specific_date)->format('M j, Y') : 'TBA' }} {{ $consult->start_time ? '· '.\Carbon\Carbon::parse($consult->start_time)->format('g:i A') : '' }}</div>
                </div>
                <span class="badge badge-blue">Upcoming</span>
              </div>
            </div>
          @endforeach
        </div>
      </x-dashboard.info-card>
    </div>

    <x-dashboard.info-card
      title="Recent Activity"
      :empty="empty($recentActivity)"
      emptyMessage="No recent activity."
    >
      <x-dashboard.activity-feed :items="$recentActivity" emptyMessage="No recent activity." />
    </x-dashboard.info-card>
  </div>
</div>

@include('instructor.partials.expert-profile')
</body>
</html>
