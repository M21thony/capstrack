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
@include('instructor.partials.sidebar', ['currentPage' => 'progress'])
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Progress Monitoring</div>
    <div class="topbar-actions"><div class="notif-btn"><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><div class="notif-dot"></div></div></div>
  </div>
  <div id="skeleton-content" class="sk-overlay">
    <div class="grid-4" style="margin-bottom:16px">
      <div class="sk" style="height:78px;border-radius:14px"></div>
      <div class="sk" style="height:78px;border-radius:14px"></div>
      <div class="sk" style="height:78px;border-radius:14px"></div>
      <div class="sk" style="height:78px;border-radius:14px"></div>
    </div>
    <div class="row" style="margin-bottom:14px;gap:8px">
      <div class="sk" style="width:260px;height:34px;border-radius:8px"></div>
      <div class="sk" style="width:160px;height:34px;border-radius:8px"></div>
      <div class="sk" style="width:160px;height:34px;border-radius:8px"></div>
    </div>
    <div class="team-progress-grid">
      <div class="sk" style="height:150px;border-radius:14px"></div>
      <div class="sk" style="height:150px;border-radius:14px"></div>
      <div class="sk" style="height:150px;border-radius:14px"></div>
    </div>
  </div>

  <div id="real-content" style="display:none">
  <div class="content">
    <div class="grid-4" style="margin-bottom:16px">
      <div class="stat-chip"><div class="stat-n" style="color:var(--green)">{{ $teamsMonitored }}</div><div class="stat-l">Teams Monitored</div></div>
      <div class="stat-chip"><div class="stat-n" style="color:var(--accent3)">{{ $avgCompletion }}%</div><div class="stat-l">Avg. Completion</div></div>
      <div class="stat-chip"><div class="stat-n" style="color:var(--red)">{{ $overdueMilestones }}</div><div class="stat-l">Overdue Milestones</div></div>
      <div class="stat-chip"><div class="stat-n" style="color:var(--blue)">{{ $onTrack }}</div><div class="stat-l">On Track</div></div>
    </div>

    <div class="row" style="margin-bottom:14px;gap:8px">
      <input class="input" placeholder="Search teams..." style="max-width:260px"/>
      <select class="input" style="width:160px"><option>All Sections</option><option>BSCS-4A</option><option>BSIT-4A</option><option>BSIT-4B</option></select>
      <select class="input" style="width:160px"><option>Sort: Progress</option><option>Sort: Team Name</option><option>Sort: Overdue First</option></select>
    </div>

    <div class="team-progress-grid">
    @forelse($teams as $entry)
      <div class="team-progress-card">
        <div class="tp-header">
          <div class="tp-avatar" style="background:linear-gradient(135deg,#c9a82c,#8a6e10)">{{ substr($entry->team->team_name, 0, 2) }}</div>
          <div style="flex:1">
            <div class="tp-team">{{ $entry->team->team_name }} <span class="badge badge-gold" style="font-size:10px;margin-left:4px;vertical-align:middle">{{ $entry->section }}</span></div>
            <div class="tp-title">{{ $entry->team->project_title ?? 'No title set' }}</div>
          </div>
          <div style="text-align:right">
            <div style="font-family:'Space Mono',monospace;font-size:16px;font-weight:700;color:{{ $entry->progress >= 70 ? 'var(--green)' : ($entry->progress >= 40 ? 'var(--accent3)' : 'var(--red)') }}">{{ $entry->progress }}%</div>
            <div style="font-size:10px;color:var(--text3)">overall</div>
          </div>
        </div>
        <div class="tp-milestones">
          <div style="font-size:12px;color:var(--text2);padding:8px 0">
            {{ $entry->completed }}/{{ $entry->total }} milestones completed
          </div>
        </div>
        <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);display:flex;gap:6px">
          @if($entry->overdue > 0)
            <span class="badge badge-amber" style="font-size:10px">{{ $entry->overdue }} Overdue</span>
          @else
            <span class="badge badge-green" style="font-size:10px">On Track</span>
          @endif
        </div>
      </div>
    @empty
      <div style="grid-column:1/-1;text-align:center;padding:48px;color:var(--text3);font-size:13px">No teams assigned yet.</div>
    @endforelse
    </div>
  </div>
  </div>
</div>
@include('instructor.partials.skeleton-toggle')

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     EXPERT PROFILE SIDE PANEL
     Sits flush against the right edge of the
     sidebar (left: 250px). No dark overlay.
     â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<script>
{{-- The expert profile panel is included once for every instructor page by
     the sidebar (capsa._sidebar -> instructor.partials.expert-profile). This
     page used to carry its own older copy of it, which redeclared EP_TAGS_EL
     and EP_INPUT. A repeated const is a SyntaxError, and it took the whole
     script block down with it - which is why nothing on this page ran. --}}
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
