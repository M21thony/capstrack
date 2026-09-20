<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Archived Capstones - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
@vite('resources/student-ui/styles.css')
<x-dashboard.styles />
<style>
  @keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .sk-overlay { position: absolute; inset: 0; background: var(--bg); z-index: 10; }
  .sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }
  /* Flash messages from the manuscript form - the page redirects back, so the
     only place a refusal can be read is here. */
  #archive-toast { position: fixed; bottom: 30px; right: 30px; background: var(--navy-dark); color: #fff; padding: 14px 22px; border-radius: 12px; font-size: 13px; font-weight: 500; z-index: 2000; box-shadow: 0 8px 30px rgba(0,0,0,0.3); opacity: 0; transform: translateY(12px); pointer-events: none; transition: opacity 0.25s ease, transform 0.25s ease; border-left: 4px solid transparent; display: flex; align-items: center; gap: 10px; max-width: 380px; }
  #archive-toast.show { opacity: 1; transform: translateY(0); }
  #archive-toast.success { border-left-color: var(--green); }
  #archive-toast.error { border-left-color: var(--red); }
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
{{-- The finished team's manuscript. Shown only once the journey is over, so
     it never competes with the archive itself for a team still working. --}}
@php $sub = $submission; @endphp
@if ($sub['team'] && ($sub['isFinished'] || $sub['existing']))
<div style="background:#fff;border:1.5px solid var(--accent);border-radius:12px;padding:16px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04)">
  <div class="row" style="justify-content:space-between;align-items:flex-start;gap:12px">
    <div>
      <div style="font-size:13px;font-weight:700;color:var(--text)">Submit your Capstone Manuscript</div>
      <div style="font-size:12px;color:var(--text3);margin-top:2px">
        Your team has completed its capstone journey. Hand in the final manuscript and it goes into the archive once it has been reviewed.
      </div>
    </div>
    @if ($sub['existing'])
      <span style="font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;white-space:nowrap;
        {{ $sub['existing']->submission_status === \App\Models\ArchivedDocument::PENDING ? 'background:var(--accent-glow);color:var(--text)' : 'background:rgba(34,153,84,0.12);color:var(--green)' }}">
        {{ $sub['existing']->submission_status === \App\Models\ArchivedDocument::PENDING ? 'Pending review' : 'In the archive' }}
      </span>
    @endif
  </div>

  @if ($sub['existing'])
    <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);font-size:12px;color:var(--text2);line-height:1.6">
      <div><strong style="color:var(--text)">{{ $sub['existing']->title }}</strong></div>
      <div>
        Handed in {{ $sub['existing']->submitted_at?->format('M j, Y') }}
        @if ($sub['existing']->file_name)
          &middot; {{ $sub['existing']->file_name }}
        @endif
      </div>
      @if ($sub['existing']->submission_status === \App\Models\ArchivedDocument::PENDING)
        <div style="color:var(--text3);margin-top:4px">Your CaPSA or the admin has to approve it before it appears in the archive. Only one manuscript per team.</div>
      @endif
    </div>
  @elseif (! $sub['isLeader'])
    {{-- Only the leader hands it in, so the rest of the team is told who to
         ask rather than shown a form that would refuse them. --}}
    <div style="margin-top:10px;font-size:12px;color:var(--text3)">
      Your team leader submits the manuscript for the team.
    </div>
  @else
    <form method="POST" action="{{ route('student.archive.manuscript.submit') }}" enctype="multipart/form-data"
      style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);display:grid;grid-template-columns:1fr 1fr;gap:12px">
      @csrf
      <div style="display:flex;flex-direction:column;gap:10px">
        <div>
          <label class="label" style="font-size:12px;color:var(--text2);display:block;margin-bottom:4px">Capstone Title</label>
          <input class="input" name="title" value="{{ old('title', $sub['defaultTitle']) }}" maxlength="255" required />
        </div>
        <div>
          <label class="label" style="font-size:12px;color:var(--text2);display:block;margin-bottom:4px">Manuscript (PDF)</label>
          <input class="input" type="file" name="file" accept="application/pdf,.pdf" required style="padding:8px 10px" />
          <div style="font-size:11px;color:var(--text3);margin-top:4px">PDF, up to 20MB.</div>
        </div>
        <div style="font-size:11px;color:var(--text3)">
          Recorded as {{ $sub['authors'] }} &middot; {{ $sub['program'] ?: 'CCS' }} &middot; {{ $sub['year'] }}
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:10px">
        <div style="flex:1;display:flex;flex-direction:column">
          <label class="label" style="font-size:12px;color:var(--text2);display:block;margin-bottom:4px">Abstract</label>
          <textarea class="input" name="abstract" required style="flex:1;min-height:150px">{{ old('abstract') }}</textarea>
        </div>
        <button class="btn btn-primary" type="submit" style="justify-content:center;padding:10px">Submit Manuscript</button>
      </div>
    </form>
  @endif
</div>
@endif

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

// ── Search / year / tag ───────────────────────────────────────────────────
// The three controls called this and it was never written, so every keystroke
// in the search box threw and nothing was ever filtered.
function filterArchive() {
  var q = (document.getElementById('archive-search').value || '').toLowerCase();
  var year = document.getElementById('archive-year').value;
  var tag = document.getElementById('archive-tag').value;
  var cards = document.querySelectorAll('#archive-grid .archive-card');
  var shown = 0;

  cards.forEach(function (card) {
    var title = (card.dataset.title || '').toLowerCase();
    var tags = card.dataset.tags || '';
    var match = (! q || title.indexOf(q) !== -1)
      && (! year || card.dataset.year === year)
      && (! tag || tags.indexOf(tag) !== -1);

    card.style.display = match ? '' : 'none';
    if (match) shown++;
  });

  var count = document.getElementById('archive-count');
  if (count) count.textContent = 'Showing ' + shown + ' studies';

  var empty = document.getElementById('archive-empty');
  if (empty) empty.style.display = shown === 0 ? 'block' : 'none';
}

// ── Flash messages ────────────────────────────────────────────────────────
function showToast(message, type) {
  var toast = document.getElementById('archive-toast');
  if (! toast) return;

  toast.textContent = (type === 'error' ? '✕ ' : '✓ ') + message;
  toast.classList.remove('success', 'error');
  toast.classList.add('show', type);
  clearTimeout(showToast._t);
  showToast._t = setTimeout(function () { toast.classList.remove('show'); }, 4000);
}

@if (session('success'))
  document.addEventListener('DOMContentLoaded', function () { showToast(@json(session('success')), 'success'); });
@endif
@if (session('error'))
  document.addEventListener('DOMContentLoaded', function () { showToast(@json(session('error')), 'error'); });
@endif
@if ($errors->any())
  document.addEventListener('DOMContentLoaded', function () { showToast(@json($errors->first()), 'error'); });
@endif
</script>

@include('partials.escape-closes-modals')
<div id="archive-toast"></div>
</body>
</html>
