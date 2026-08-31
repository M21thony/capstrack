<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Archived Capstones - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/styles.css')
<x-dashboard.styles />
<style>
  @keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .sk-overlay { position: absolute; inset: 0; background: var(--bg); z-index: 10; }
  .sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }
</style>
@include('partials.skeleton-head', ['skReveal' => 'opacity'])
</head>
<body>
@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Archived Capstones</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'archive'])
    <div id="contentWrapper" style="position: relative;">
<div id="skeleton-content" class="sk-overlay">
      <div class="row" style="margin-bottom:16px;gap:8px;flex-wrap:wrap">
        <div class="sk" style="flex:1;min-width:200px;max-width:380px;height:36px;border-radius:8px"></div>
        <div class="sk" style="width:120px;height:36px;border-radius:8px"></div>
        <div class="sk" style="width:160px;height:36px;border-radius:8px"></div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
        @for ($i = 0; $i < 6; $i++)
        <div class="archive-card">
          <div class="sk" style="width:35%;height:10px;border-radius:4px;margin-bottom:8px"></div>
          <div class="sk" style="width:90%;height:14px;border-radius:4px;margin-bottom:10px"></div>
          <div class="sk" style="width:100%;height:11px;border-radius:4px;margin-bottom:4px"></div>
          <div class="sk" style="width:75%;height:11px;border-radius:4px;margin-bottom:12px"></div>
          <div class="sk" style="width:55%;height:18px;border-radius:10px"></div>
        </div>
        @endfor
      </div>
    </div>

    <div id="real-content" style="opacity:0">
<div class="row" style="margin-bottom:16px;gap:8px;flex-wrap:wrap">
  <input class="input" id="archive-search" placeholder="Search by title or keyword..." style="flex:1;min-width:200px;max-width:380px" oninput="filterArchive()" />
  <select class="input" id="archive-year" style="width:120px" onchange="filterArchive()">
    <option value="">All Years</option>
    @foreach($archives->pluck('academic_year')->unique()->sort() as $year)
    <option>{{ $year }}</option>
    @endforeach
  </select>
  <select class="input" id="archive-tag" style="width:160px" onchange="filterArchive()">
    <option value="">All Categories</option>
    @foreach($tags as $tag)
    <option value="{{ $tag->tag_name }}">{{ $tag->tag_name }}</option>
    @endforeach
  </select>
  <span style="margin-left:auto;font-size:12px;color:var(--text3)" id="archive-count">Showing {{ $archives->count() }} studies</span>
</div>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px" id="archive-grid">
  @foreach($archives as $archive)
  <div class="archive-card"
    data-tags="{{ $archive->tags->pluck('tag_name')->implode(',') }}"
    data-year="{{ $archive->academic_year }}"
    data-title="{{ strtolower($archive->title) }}">
    <div class="archive-year">{{ $archive->academic_year ?? 'N/A' }}</div>
    <div class="archive-title">{{ $archive->title }}</div>
    <div class="archive-proponents">{{ $archive->abstract ? Str::limit($archive->abstract, 80) : 'No description' }}</div>
    <div class="archive-tags">
      @foreach($archive->tags as $tag)
      <span class="archive-tag" style="background:{{ $tag->tag_color ?? 'var(--accent-glow)' }};color:var(--text1)">{{ $tag->tag_name }}</span>
      @endforeach
    </div>
    <div class="archive-footer">
      <span style="font-size:11px;color:var(--text3)">{{ $archive->program ?? 'N/A' }} &middot; CCS</span>
    </div>
  </div>
  @endforeach
</div>
<div id="archive-empty" style="display:none;text-align:center;padding:48px;color:var(--text3);font-size:13px">No archived capstones match your search.</div>
    </div>
  </div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var sk = document.getElementById('skeleton-content');
  var rc = document.getElementById('real-content');
  if (sk && rc) {
    // partials/skeleton-head already decided this in <head>, before the first
    // paint: the content is on screen and no skeleton was ever shown. Without
    // this, every save replayed the 600ms fade over content already there.
    if (document.documentElement.classList.contains('sk-seen')) {
      sk.remove();

      return;
    }

    requestAnimationFrame(function () {
      setTimeout(function () {
        sk.style.transition = 'opacity 0.3s ease';
        sk.style.opacity = '0';
        rc.style.transition = 'opacity 0.35s ease';
        rc.style.opacity = '1';
        setTimeout(function () {
          sk.remove();
          rc.style.transition = '';
        }, 350);
        try {
          sessionStorage.setItem('sk-seen:{{ substr(sha1(session()->getId()), 0, 12) }}:' + window.location.pathname, '1');
        } catch (e) {}
      }, 600);
    });
  }
});

// -- Mobile Sidebar --
function toggleSidebar() {
    document.body.classList.toggle('sidebar-open');
    document.body.style.overflow = document.body.classList.contains('sidebar-open') ? 'hidden' : '';
}
function closeSidebar() {
    document.body.classList.remove('sidebar-open');
    document.body.style.overflow = '';
}
document.querySelectorAll('.nav-item').forEach(function(el) {
    el.addEventListener('click', function() {
        if (window.innerWidth <= 768) closeSidebar();
    });
});
</script>

</body>
</html>
