<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Dashboard - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<x-dashboard.styles />
</head>
<body>

@include('admin._sidebar')
@include('partials.welcome-modal')

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Dashboard</div>
    <div class="topbar-actions">
      @include('admin.partials.notification-dropdown')
    </div>
  </div>

  <div class="content">
    <div class="grid-4" style="margin-bottom:16px">
      <x-dashboard.stat-chip value="{{ $totalStudents }}" label="Total Students" sub="Enrolled users" color="var(--blue)" />
      <x-dashboard.stat-chip value="{{ $totalExperts }}" label="Total Experts" sub="Approved faculty accounts" color="var(--purple)" />
      <x-dashboard.stat-chip value="{{ $activeTeams }}" label="Active Teams" sub="{{ $currentSchoolYearLabel }}" color="var(--green)" />
      <x-dashboard.stat-chip value="{{ $completedTeams }}" label="Completed Teams" sub="{{ $currentSchoolYearLabel }}" color="var(--accent3)" />
    </div>

    <div class="grid-2" style="margin-bottom:16px">
      <x-dashboard.stat-chip value="{{ $pendingApprovals }}" label="Pending Approvals" sub="Expert accounts awaiting review" color="var(--red)" />
      <x-dashboard.stat-chip value="{{ $archivedCapstones }}" label="Archived Capstones" sub="All time" color="var(--blue)" />
    </div>

    <div class="grid-2" style="margin-bottom:16px">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Capstones Submitted per Year</span>
          <select onchange="window.location='?year='+this.value" style="font-family:'DM Sans',sans-serif;font-size:11px;font-weight:600;padding:4px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg2);color:var(--text2);outline:none;cursor:pointer;">
            <option value="">All Years</option>
            @foreach($years as $year)
            <option value="{{ $year }}" @selected((string) $selectedYear === (string) $year)>{{ $year }}</option>
            @endforeach
          </select>
        </div>
        <div style="display:flex;align-items:flex-end;gap:6px;height:140px;padding-bottom:4px;position:relative">
          @forelse($yearlyArchive as $year => $count)
          <div style="display:flex;flex-direction:column;align-items:center;flex:1">
            <span style="font-size:9px;color:var(--text3);font-family:'Space Mono',monospace;margin-bottom:4px">{{ $count }}</span>
            <div style="width:100%;max-width:30px;background:{{ (string) $selectedYear === (string) $year ? 'var(--accent)' : 'var(--bg4)' }};border-radius:4px 4px 0 0;height:{{ max(round(($count / $maxYearlyCount) * 90), 8) }}px"></div>
            <div style="font-size:9px;color:{{ (string) $selectedYear === (string) $year ? 'var(--accent3)' : 'var(--text3)' }};font-weight:{{ (string) $selectedYear === (string) $year ? 700 : 400 }};margin-top:4px">{{ substr($year, 2) }}</div>
          </div>
          @empty
          <div style="width:100%;text-align:center;font-size:12px;color:var(--text3);padding-bottom:20px;">No archived capstones yet.</div>
          @endforelse
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <span class="card-title">Capstone Categories</span>
          <span class="badge badge-gray">Verified Teams</span>
        </div>
        <div class="donut-wrap" style="margin-top:8px">
          <svg class="donut" viewBox="0 0 36 36">
            <circle cx="18" cy="18" r="15.9" fill="none" stroke="#f0f2f5" stroke-width="3.8"/>
            @foreach($categories as $cat)
            <circle cx="18" cy="18" r="15.9" fill="none" stroke="{{ $cat['color'] }}" stroke-width="3.8" stroke-dasharray="{{ $cat['dasharray'] }}" stroke-dashoffset="{{ $cat['dashoffset'] }}" transform="rotate(-90 18 18)"/>
            @endforeach
            <text x="18" y="20" text-anchor="middle" font-size="5" fill="#1e2128" font-family="Space Mono" font-weight="700">{{ $totalTags }}</text>
          </svg>
          <div class="donut-legend">
            @forelse($categories as $cat)
            <div class="donut-item"><div class="donut-dot" style="background:{{ $cat['color'] }}"></div><span style="flex:1">{{ $cat['name'] }}</span><span style="font-family:'Space Mono',monospace;font-size:11px;color:var(--text2)">{{ $cat['count'] }} ({{ $cat['percentage'] }}%)</span></div>
            @empty
            <div class="donut-item"><span style="color:var(--text3)">No verified team tags yet.</span></div>
            @endforelse
          </div>
        </div>
      </div>
    </div>

    <div class="dash-grid-v3">
      <x-dashboard.info-card
        title="Pending Expert Approvals"
        :actionUrl="route('admin.approval')"
        actionText="View All"
        :empty="$pendingExperts->isEmpty()"
        emptyMessage="No pending approvals."
      >
        <div class="gap-12">
          @foreach ($pendingExperts as $expert)
            <div class="expert-row">
              <div class="expert-av" style="background:linear-gradient(135deg,#1d4ed8,#7c3aed)">{{ $expert->avatarInitials() }}</div>
              <div style="flex:1">
                <div style="font-size:13px;font-weight:600;color:var(--text)">{{ $expert->name }}</div>
                <div style="font-size:11px;color:var(--text3)">
                  {{ $expert->specialization ?: 'No role set' }}
                  @if ($expert->department)
                    · {{ $expert->department }}
                  @endif
                </div>
              </div>
              <div style="display:flex;gap:6px">
                <a href="{{ route('admin.approval') }}" class="btn btn-success" style="font-size:10px;padding:4px 10px">Review</a>
              </div>
            </div>
          @endforeach
        </div>
      </x-dashboard.info-card>

      <x-dashboard.info-card
        title="Recent Activity"
        :actionUrl="route('admin.activitylog')"
        actionText="View All"
        :empty="empty($recentActivity)"
        emptyMessage="No recent activity."
      >
        <x-dashboard.activity-feed :items="$recentActivity" emptyMessage="No recent activity." />
      </x-dashboard.info-card>
    </div>
  </div>
</div>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<script>
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() { sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar(); }
function openSidebar() { sidebarEl.classList.add('mobile-open'); sidebarOverlay.classList.add('open'); }
function closeSidebar() { sidebarEl.classList.remove('mobile-open'); sidebarOverlay.classList.remove('open'); }
</script>

</body>
</html>
