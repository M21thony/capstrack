<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CapsTRACK — Milestone progress</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/expert.css') }}" />
@include('partials.skeleton-head', ['skReveal' => 'block'])
  @include('partials.render-whole')
</head>
<body>
@include('instructor.partials.sidebar', ['currentPage' => 'teams'])
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title"><a href="{{ route('instructor.teams') }}" style="color:var(--text3);text-decoration:none">Teams</a> <span style="color:var(--text3)">›</span> Milestone progress</div>
    <div class="topbar-actions">@include('instructor.partials.notification-dropdown')</div>
  </div>
  <div id="skeleton-content" class="sk-overlay">
    <div class="sk" style="height:64px;border-radius:11px;margin-bottom:12px"></div>
    <div class="sk" style="height:320px;border-radius:11px"></div>
  </div>

  <div id="real-content" style="display:none">
  <div class="content">
    {{-- The teams this expert advises or consults for, on the CaPSA/TSA's tracker,
         read-only. Drawn by resources/js/milestones/tracker.js. --}}
    <div class="ms-app" id="ms-tracker"
         data-mode="adviser"
         data-state="{{ json_encode($tracker, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
         data-urls="{}">
      <noscript>The milestone tracker needs JavaScript turned on.</noscript>
    </div>
  </div>
  </div>
</div>
@include('instructor.partials.skeleton-toggle')
@vite('resources/js/milestones/tracker.js')
<script nonce="{{ Vite::cspNonce() }}">
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() {
  if (!sidebarEl) return;
  if (sidebarEl.classList.contains('mobile-open')) { closeSidebar(); } else { openSidebar(); }
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
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>
