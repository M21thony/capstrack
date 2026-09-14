<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Financial Records - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
@vite(['resources/student-ui/styles.css', 'resources/css/financial.css', 'resources/js/financial/student.js'])
<x-dashboard.styles />
</head>
<body>
@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Financial Records</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>

  @include('student.partials.page-tutorial', ['page' => 'financial'])

  {{-- Drawn by resources/js/financial/student.js from the JSON below. --}}
  <div class="fx" id="fx-student"
       data-state="{{ json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
       data-urls="{{ json_encode(['upload' => route('api.student.financial.submit')], JSON_UNESCAPED_SLASHES) }}">

    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
      <symbol id="fx-i-upload" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/></symbol>
      <symbol id="fx-i-camera" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3z"/><circle cx="12" cy="13" r="3"/></symbol>
    </svg>

    <div class="fx-student">
      <header class="fx-page-head">
        <div><h1 style="margin-bottom:2px">Financial Records</h1><p class="fx-who">
          @if ($state['team'])
            Team {{ $state['team']['name'] }}@if ($state['section']) · {{ $state['section'] }}@endif
          @else
            You are not on a team yet.
          @endif
        </p></div>
        <div class="fx-tabs" role="tablist" aria-label="Fee" id="s-fees"></div>
      </header>
      <div class="fx-mine" id="s-mine"></div>
      <div class="fx-team-head"><h2>Your team</h2><span class="fx-muted" style="font-size:13px">All must be verified before the team can be scheduled.</span><span class="fx-grow"></span><div class="fx-bar" id="s-bar" style="width:140px"></div><span class="fx-num fx-muted" id="s-count"></span></div>
      <div class="fx-scroll">
        <table>
          <colgroup><col><col style="width:160px"><col class="fx-col-up" style="width:130px"></colgroup>
          <thead><tr><th>Member</th><th>Status</th><th class="fx-col-up">Uploaded</th></tr></thead>
          <tbody id="s-team"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<dialog id="fx-d-image" class="fx-dialog fx-image" aria-label="Receipt, full size"><img id="fx-img-full" alt=""></dialog>

<script>
function toggleSidebar() {
  document.body.classList.toggle('sidebar-open');
  document.body.style.overflow = document.body.classList.contains('sidebar-open') ? 'hidden' : '';
}
function closeSidebar() {
  document.body.classList.remove('sidebar-open');
  document.body.style.overflow = '';
}
document.querySelectorAll('.nav-item').forEach(function (el) {
  el.addEventListener('click', function () { if (window.innerWidth <= 768) closeSidebar(); });
});
</script>
@include('partials.escape-closes-modals')
</body>
</html>
