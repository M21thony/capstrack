<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<title>CapsTRACK — Notifications</title>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/expert.css') }}" />
<style nonce="{{ Vite::cspNonce() }}">
  .content.np-page{display:flex;flex-direction:column}
  .np-page .np-card{flex:1 1 0;min-height:260px;overflow:auto;padding:0}
  .np-page .notif-item{padding:10px 20px 10px 26px}
  .np-page .notif-item.is-unread::before{left:12px;top:17px}
  .np-pages{display:flex;align-items:center;justify-content:space-between;margin-top:12px;font-size:12px;color:var(--text2)}
</style>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
  @include('partials.render-whole')
</head>
<body>
@include('instructor.partials.sidebar', ['currentPage' => ''])
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Notifications</div>
    <div class="topbar-actions">@include(auth('expert')->user()->is_staff ? 'capsa.partials.notification-dropdown' : 'instructor.partials.notification-dropdown')</div>
  </div>
  <div id="skeleton-content" class="sk-overlay"></div>
  <div id="real-content" style="display:none;flex:1;min-height:0;flex-direction:column;overflow:hidden">
    <div class="content np-page">
      <div class="card np-card">
        @forelse ($notifications as $n)
          @include('partials.notification-row')
        @empty
          <div class="notif-empty">No notifications here.</div>
        @endforelse
      </div>
      @if ($notifications->hasPages())
        <div class="np-pages">
          <span>Page {{ $notifications->currentPage() }} of {{ $notifications->lastPage() }}</span>
          <span>
            @unless ($notifications->onFirstPage())
              <a href="{{ $notifications->previousPageUrl() }}" class="btn btn-ghost" style="font-size:11px;padding:5px 12px">Prev</a>
            @endunless
            @if ($notifications->hasMorePages())
              <a href="{{ $notifications->nextPageUrl() }}" class="btn btn-ghost" style="font-size:11px;padding:5px 12px">Next</a>
            @endif
          </span>
        </div>
      @endif
    </div>
  </div>
</div>
@include('instructor.partials.skeleton-toggle', ['revealDisplay' => 'flex'])
<script nonce="{{ Vite::cspNonce() }}">
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
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>
