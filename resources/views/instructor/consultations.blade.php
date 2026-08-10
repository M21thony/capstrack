<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CapsTRACK — Expert Portal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400,700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/expert.css') }}" />
</head>
<body>

@include('instructor.partials.sidebar', ['currentPage' => 'consultations'])
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Consultation Logs</div>
    <div class="topbar-actions"><div class="notif-btn"><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><div class="notif-dot"></div></div></div>
  </div>

  <div class="content">
    <div class="row" style="margin-bottom:16px;gap:8px">
      <select class="input" style="width:160px"><option>All Teams</option><option>Team Nexus</option><option>Team Alpha</option><option>Team Prism</option></select>
      <select class="input" style="width:150px"><option>All Status</option><option>Upcoming</option><option>Completed</option><option>Cancelled</option></select>
      <input class="input" type="date" style="width:160px"/>
      <span style="flex:1"></span>
      <button class="btn btn-primary">Export Logs</button>
    </div>
    <div class="gap-12">
      <div class="consult-card">
        <div class="consult-header">
          <div><div class="consult-team">Team Nexus — Maria Andres</div><div class="consult-meta">Mon, May 12, 2025 · 2:00–3:00 PM · In-person, Room 204</div></div>
          <span class="badge badge-blue">Upcoming</span>
        </div>
        <div class="consult-topic">Chapter 3 Methodology Review — research design, sampling approach, and instrument validation</div>
        <div style="display:flex;gap:6px;align-items:center">
          <button class="btn btn-primary" style="font-size:11px;padding:5px 12px">Mark as Done</button>
          <button class="btn btn-ghost" style="font-size:11px;padding:5px 12px">Reschedule</button>
          <button class="btn btn-danger" style="font-size:11px;padding:5px 12px">Cancel</button>
          <span style="margin-left:auto;font-size:10px;color:var(--text3)">Booked May 5, 2025</span>
        </div>
      </div>
      <div class="consult-card">
        <div class="consult-header">
          <div><div class="consult-team">Team Alpha — Juan Reyes</div><div class="consult-meta">Wed, May 16, 2025 · 10:00–10:45 AM · Online (Google Meet)</div></div>
          <span class="badge badge-gold">Confirmed</span>
        </div>
        <div class="consult-topic">Web architecture discussion — cloud deployment options and database scaling strategy</div>
        <div style="display:flex;gap:6px">
          <button class="btn btn-primary" style="font-size:11px;padding:5px 12px">Mark as Done</button>
          <button class="btn btn-ghost" style="font-size:11px;padding:5px 12px">Reschedule</button>
          <button class="btn btn-danger" style="font-size:11px;padding:5px 12px">Cancel</button>
        </div>
      </div>
      <div class="consult-card" style="opacity:0.7">
        <div class="consult-header">
          <div><div class="consult-team">Team Nexus — Lea Cruz</div><div class="consult-meta">Mon, May 5, 2025 · 2:00–3:00 PM · In-person, Room 204</div></div>
          <span class="badge badge-green">Completed</span>
        </div>
        <div class="consult-topic">Chapter 2 RRL — literature gaps, citation improvements, and scope refinement</div>
        <div style="display:flex;gap:8px;align-items:center">
          <span style="font-size:11px;color:var(--green);display:flex;align-items:center;gap:4px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><polyline points="20 6 9 17 4 12"/></svg>
            Marked done on May 5, 2025
          </span>
          <button class="btn btn-ghost" style="font-size:10px;padding:3px 10px;margin-left:auto">View Notes</button>
        </div>
      </div>
      <div class="consult-card" style="opacity:0.7">
        <div class="consult-header">
          <div><div class="consult-team">Team Prism — Rico Peña</div><div class="consult-meta">Fri, Apr 28, 2025 · 1:00–1:45 PM · Online</div></div>
          <span class="badge badge-gray">Cancelled</span>
        </div>
        <div class="consult-topic">Initial system design review — cancelled by team due to scheduling conflict</div>
      </div>
    </div>
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