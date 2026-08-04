<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Archived Capstones - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/styles.css')
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


</body>
</html>
