<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Expert Profile Panel &mdash; CapsTRACK</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/expert.css') }}"/>
@include('partials.skeleton-head', ['skReveal' => 'block'])
</head>
<body>

@include('instructor.partials.sidebar', ['currentPage' => $currentPage ?? ''])
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Profile Settings</div>
    <div class="topbar-actions">
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="card" style="max-width:420px">
      <div class="sk" style="width:150px;height:16px;border-radius:4px;margin-bottom:16px"></div>
      <div class="sk" style="width:100%;height:12px;border-radius:4px;margin-bottom:6px"></div>
      <div class="sk" style="width:70%;height:12px;border-radius:4px;margin-bottom:16px"></div>
      <div class="sk" style="width:100%;height:38px;border-radius:8px"></div>
    </div>
  </div>

  <div id="real-content" style="display:none">
  <div class="content">
    <div class="card" style="max-width:420px">
      <div class="card-header"><span class="card-title">Profile Information</span></div>
      <p style="font-size:13px;color:var(--text3);margin-bottom:16px">Manage your expert profile and preferences.</p>
      <button class="btn btn-primary" onclick="openExpertProfile()" style="width:100%">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 0 0 1 0 7.75"/></svg>
        Edit Profile
      </button>
    </div>
  </div>
  </div>
</div>

@include('instructor.partials.expert-profile')
@include('instructor.partials.skeleton-toggle')

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