<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CapsTRACK Expert Portal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400,700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/expert.css') }}" />
</head>
<body>

@include('instructor.partials.sidebar', ['currentPage' => 'applications'])
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Application Management</div>
    <div class="topbar-actions"><div class="notif-btn"><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><div class="notif-dot"></div></div></div>
  </div>

  <div class="content">
    <div class="row" style="margin-bottom:16px;gap:8px">
      <input class="input" placeholder="Search teams or capstone title..." style="flex:1;max-width:340px"/>
      <select class="input" style="width:140px"><option>All Roles</option><option>Adviser</option><option>Panel</option><option>Consultant</option></select>
      <select class="input" style="width:140px"><option>All Status</option><option>Pending</option><option>Accepted</option><option>Rejected</option></select>
      <span style="margin-left:auto;font-size:12px;color:var(--text3)">4 pending · 2 accepted</span>
    </div>

    <div class="app-cards-grid" style="margin-bottom:16px">
      <div class="app-card">
        <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:10px">
          <div class="app-avatar" style="background:linear-gradient(135deg,#c9a82c,#8a6e10)">TN</div>
          <div style="flex:1">
            <div class="app-team">Team Nexus</div>
            <div class="app-title">AI-Powered Attendance System Using Facial Recognition for Higher Education</div>
            <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
              <span class="badge badge-gold">Adviser</span>
              <span class="badge badge-gray">BSCS-4A</span>
              <span style="font-size:10px;color:var(--text3);display:flex;align-items:center;gap:3px">Applied May 3, 2025</span>
            </div>
          </div>
        </div>
        <div style="background:var(--bg3);border-radius:7px;padding:10px;font-size:12px;color:var(--text2);margin-bottom:10px;border-left:3px solid var(--accent)">
          <strong style="color:var(--text);font-size:11px">Message from team:</strong><br/>We would be honored to have you as our adviser. Your expertise in ML and CV directly aligns with our research.
        </div>
        <div class="app-actions">
          <button class="btn btn-success" style="flex:1;justify-content:center"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><polyline points="20 6 9 17 4 12"/></svg>Accept</button>
          <button class="btn btn-danger" style="flex:1;justify-content:center"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Reject</button>
          <button class="btn btn-ghost" style="font-size:11px;padding:6px 10px">View Full</button>
        </div>
      </div>

      <div class="app-card">
        <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:10px">
          <div class="app-avatar" style="background:linear-gradient(135deg,#1d4ed8,#7c3aed)">TP</div>
          <div style="flex:1">
            <div class="app-team">Team Prism</div>
            <div class="app-title">Blockchain-Based Document Verification for Academic Records</div>
            <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
              <span class="badge badge-purple">Panel</span>
              <span class="badge badge-gray">BSIT-4B</span>
              <span style="font-size:10px;color:var(--text3)">Applied May 4, 2025</span>
            </div>
          </div>
        </div>
        <div style="background:var(--bg3);border-radius:7px;padding:10px;font-size:12px;color:var(--text2);margin-bottom:10px;border-left:3px solid var(--purple)">
          <strong style="color:var(--text);font-size:11px">Message from team:</strong><br/>We need a panel member with blockchain and security expertise for our defense evaluation.
        </div>
        <div class="app-actions">
          <button class="btn btn-success" style="flex:1;justify-content:center"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><polyline points="20 6 9 17 4 12"/></svg>Accept</button>
          <button class="btn btn-danger" style="flex:1;justify-content:center"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Reject</button>
          <button class="btn btn-ghost" style="font-size:11px;padding:6px 10px">View Full</button>
        </div>
      </div>

      <div class="app-card">
        <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:10px">
          <div class="app-avatar" style="background:linear-gradient(135deg,#0891b2,#16a34a)">TS</div>
          <div style="flex:1">
            <div class="app-team">Team Syntax</div>
            <div class="app-title">Smart Campus Energy Monitoring System Using IoT Sensors</div>
            <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
              <span class="badge badge-cyan">Consultant</span>
              <span class="badge badge-gray">BSIT-4A</span>
              <span style="font-size:10px;color:var(--text3)">Applied May 5, 2025</span>
            </div>
          </div>
        </div>
        <div style="background:var(--bg3);border-radius:7px;padding:10px;font-size:12px;color:var(--text2);margin-bottom:10px;border-left:3px solid var(--cyan)">
          <strong style="color:var(--text);font-size:11px">Message from team:</strong><br/>We're looking for a consultant in the IoT and data analytics space to guide our methodology.
        </div>
        <div class="app-actions">
          <button class="btn btn-success" style="flex:1;justify-content:center"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><polyline points="20 6 9 17 4 12"/></svg>Accept</button>
          <button class="btn btn-danger" style="flex:1;justify-content:center"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><line x1="18" y1="6" x2="6" y2="18"/><line x="6" y1="6" x2="18" y2="18"/></svg>Reject</button>
          <button class="btn btn-ghost" style="font-size:11px;padding:6px 10px">View Full</button>
        </div>
      </div>

      <div class="app-card" style="border-color:var(--green);opacity:0.75">
        <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:10px">
          <div class="app-avatar" style="background:linear-gradient(135deg,#16a34a,#0891b2)">TA</div>
          <div style="flex:1">
            <div class="app-team">Team Alpha</div>
            <div class="app-title">Plant Disease Detection Using Deep Learning Mobile App</div>
            <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
              <span class="badge badge-gold">Adviser</span>
              <span class="badge badge-green">Accepted</span>
              <span style="font-size:10px;color:var(--text3)">May 1, 2025</span>
            </div>
          </div>
        </div>
        <div style="text-align:center;padding:10px 0;font-size:12px;color:var(--green)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" style="margin-bottom:4px;display:block;margin:0 auto 6px"><polyline points="20 6 9 17 4 12"/></svg>
          Application accepted on May 1, 2025
        </div>
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