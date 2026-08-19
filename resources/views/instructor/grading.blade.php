<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CapsTRACK — Expert Portal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/expert.css') }}" />
</head>
<body>

@include('instructor.partials.sidebar', ['currentPage' => 'grading'])
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Team Grading</div>
    <div class="topbar-actions">
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </div>

  <div class="content">

    @if (session('error'))
      <div class="card" style="border-color:var(--red);background:var(--red-bg);color:var(--red);padding:14px 18px;margin-bottom:16px;font-size:13px">
        {{ session('error') }}
      </div>
    @endif

    @if($rows->isEmpty())
      <div style="text-align:center;padding:64px 24px">
        <div style="margin-bottom:16px;opacity:0.3">
          <svg viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        </div>
        <div style="font-size:18px;font-weight:600;color:var(--text2);margin-bottom:8px">No Teams Assigned Yet</div>
        <div style="font-size:13px;color:var(--text3);max-width:400px;margin:0 auto">You haven't been placed on a defense panel yet. Once an admin assigns you to a team's panel, it will show up here.</div>
      </div>
    @else
      <div class="team-progress-grid">
        @foreach($rows as $row)
        <a href="{{ route('instructor.grading.show', $row->defense_id) }}" class="team-progress-card" style="text-decoration:none;color:inherit;display:block">
          <div class="tp-header">
            <div class="tp-avatar" style="background:linear-gradient(135deg,#1d4ed8,#7c3aed)">{{ substr($row->team_name, 0, 2) }}</div>
            <div style="flex:1;min-width:0">
              <div class="tp-team">{{ $row->team_name }}</div>
              <div class="tp-title">{{ $row->project_title ?? 'No title set' }}</div>
            </div>
          </div>

          <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px">
            <span class="badge badge-gold" style="font-size:10px">{{ $row->defense_type }}</span>
            <span class="badge badge-blue" style="font-size:10px">{{ $row->panel_role }}</span>
          </div>

          <div style="font-size:12px;color:var(--text3);margin-bottom:10px">
            @if($row->schedule_date)
              {{ $row->schedule_date->format('M j, Y') }}
              @if($row->schedule_date->format('H:i') !== '00:00')
                {{ $row->schedule_date->format('g:i A') }}–{{ ($row->end_date ?? $row->schedule_date->copy()->addHours(2))->format('g:i A') }}
              @endif
              · {{ $row->venue }}
            @else
              Not yet scheduled
            @endif
          </div>

          <div style="padding-top:10px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
            @if($row->status === 'submitted')
              <span class="badge badge-green" style="font-size:10px">Submitted{{ $row->total_score !== null ? ' · '.rtrim(rtrim(number_format((float) $row->total_score, 2), '0'), '.') : '' }}</span>
            @elseif($row->status === 'draft')
              <span class="badge badge-amber" style="font-size:10px">Draft Saved</span>
            @else
              <span class="badge badge-gray" style="font-size:10px">Not Started</span>
            @endif
            <span style="font-size:11px;color:var(--accent3);font-weight:600">Grade Team →</span>
          </div>
        </a>
        @endforeach
      </div>
    @endif

  </div>
</div>

@include('instructor.partials.expert-profile')

<script>
/* Mobile sidebar toggle */
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');

function toggleSidebar() {
  if (!sidebarEl) return;
  var isOpen = sidebarEl.classList.contains('mobile-open');
  if (isOpen) { closeSidebar(); } else { openSidebar(); }
}
function openSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.add('mobile-open');
  sidebarOverlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.remove('mobile-open');
  sidebarOverlay.classList.remove('open');
  document.body.style.overflow = '';
}
</script>
</body>
</html>
