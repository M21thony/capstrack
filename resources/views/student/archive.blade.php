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
  #pageSkeleton { position: absolute; inset: 0; z-index: 50; background: var(--bg, #f2f3f5); transition: opacity 0.6s ease, visibility 0.6s ease; }
  #pageSkeleton.hidden { opacity: 0; visibility: hidden; pointer-events: none; }
  #contentWrapper { opacity: 1; transition: opacity 0.6s ease; }
  #contentWrapper.loading { opacity: 0; }
  .sk-block { position: relative; overflow: hidden; background: rgba(0,0,0,0.06); }
  .sk-shimmer { position: absolute; inset: 0; background: linear-gradient(90deg, transparent, rgba(0,0,0,0.08), transparent); animation: skShimmer 1.5s ease-in-out infinite; }
  @keyframes skShimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
</style>
</head>
<body>
@include('student.partials.sidebar')

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="page-title">Archived Capstones</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'archive'])
    <div id="contentWrapper" class="loading" style="position: relative;">

    {{-- Skeleton loader --}}
    <div id="pageSkeleton">
      <div class="row" style="margin-bottom:16px;gap:8px;flex-wrap:wrap">
        <div class="sk-block" style="flex:1;min-width:200px;max-width:380px;height:36px;border-radius:8px"><div class="sk-shimmer"></div></div>
        <div class="sk-block" style="width:120px;height:36px;border-radius:8px"><div class="sk-shimmer"></div></div>
        <div class="sk-block" style="width:160px;height:36px;border-radius:8px"><div class="sk-shimmer"></div></div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
        @for ($i = 0; $i < 6; $i++)
        <div class="archive-card">
          <div class="sk-block" style="width:35%;height:10px;border-radius:4px;margin-bottom:8px"><div class="sk-shimmer"></div></div>
          <div class="sk-block" style="width:90%;height:14px;border-radius:4px;margin-bottom:10px"><div class="sk-shimmer"></div></div>
          <div class="sk-block" style="width:100%;height:11px;border-radius:4px;margin-bottom:4px"><div class="sk-shimmer"></div></div>
          <div class="sk-block" style="width:75%;height:11px;border-radius:4px;margin-bottom:12px"><div class="sk-shimmer"></div></div>
          <div class="sk-block" style="width:55%;height:18px;border-radius:10px"><div class="sk-shimmer"></div></div>
        </div>
        @endfor
      </div>
    </div>

    {{-- Content --}}
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

<script>
var __skeleton = document.getElementById('pageSkeleton');
var __content = document.getElementById('contentWrapper');
if (__skeleton && __content) {
  (function() {
    function hideSkeleton() {
      __skeleton.classList.add('hidden');
      __content.classList.remove('loading');
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', hideSkeleton);
    } else {
      hideSkeleton();
    }
  })();
}
</script>

</body>
</html>
