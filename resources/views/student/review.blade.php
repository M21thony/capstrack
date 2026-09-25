<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Review Viewer - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
@vite(['resources/student-ui/styles.css', 'resources/js/student-review.jsx'])
<x-dashboard.styles />
<style>
  @keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .sk-overlay { position: absolute; inset: 0; background: var(--bg); z-index: 10; }
  .sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }

  /* The review fills the page under the top bar: which review is open and
     what to do about it, then the document and its remarks. */
  .content:has(.rv-shell) { padding: 0; overflow: hidden; }
  .content:has(.rv-shell) #contentWrapper { height: 100%; }
  .content:has(.rv-shell) #real-content { height: 100%; }
  .rv-shell { display: grid; grid-template-rows: auto minmax(0, 1fr); height: 100%; }
  .rv-bar { display: flex; align-items: center; gap: 10px; padding: 10px 16px; background: var(--bg2); border-bottom: 1px solid var(--border); flex-wrap: wrap; }
  .rv-picker { position: relative; }
  .rv-pick { list-style: none; display: grid; grid-template-columns: auto 1fr auto; gap: 0 10px; align-items: center; border: 1px solid var(--border); background: var(--bg2); border-radius: 8px; padding: 6px 10px; cursor: pointer; min-width: 320px; max-width: 520px; }
  .rv-pick::-webkit-details-marker { display: none; }
  .rv-pick:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
  .rv-picker[open] .rv-pick { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(201,168,44,.15); }
  .rv-pick-ic { width: 32px; height: 32px; border-radius: 8px; background: #f5ecd0; color: #6b5410; display: grid; place-items: center; }
  .rv-pick-ic svg, .rv-chev { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
  .rv-pick-t { display: grid; min-width: 0; }
  .rv-pick-t b { font-size: 14px; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .rv-pick-t small { font-size: 12px; color: var(--text3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .rv-chev { color: var(--text3); }
  .rv-menu { position: absolute; top: calc(100% + 6px); left: 0; width: 460px; max-width: 90vw; background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 14px 34px rgba(24,25,31,.18); padding: 6px; z-index: 60; display: grid; gap: 2px; max-height: 70vh; overflow: auto; }
  .rv-menu-h { margin: 8px 8px 2px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--text3); }
  .rv-opt { display: flex; justify-content: space-between; gap: 10px; align-items: center; text-decoration: none; color: var(--text); border: 1px solid transparent; border-radius: 8px; padding: 7px 10px; }
  .rv-opt > span:first-child { display: grid; min-width: 0; }
  .rv-opt b { font-size: 13px; font-weight: 600; }
  .rv-opt small { font-size: 12px; color: var(--text3); }
  a.rv-opt:hover, a.rv-opt:focus-visible { background: var(--accent-light); border-color: #e3d08f; outline: none; }
  .rv-opt[aria-current="true"] { background: var(--accent-light); border-color: var(--accent); }
  .rv-opt.off { opacity: .7; }
  .rv-pill { display: inline-flex; align-items: center; font-size: 12px; font-weight: 600; padding: 2px 10px; border-radius: 999px; white-space: nowrap; background: var(--bg4); color: var(--text2); }
  .rv-pill.revise { background: rgba(180,83,9,.09); color: #b45309; }
  .rv-pill.ok { background: rgba(22,163,74,.09); color: #15803d; }
  .rv-btn { display: inline-flex; align-items: center; gap: 6px; border: 1px solid transparent; border-radius: 6px; padding: 6px 12px; font-size: 13px; font-weight: 600; text-decoration: none; min-height: 34px; color: var(--text2); }
  .rv-btn svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
  .rv-btn.ghost:hover { background: var(--accent-light); color: var(--text); }
  .rv-btn.gold { background: var(--accent); border-color: var(--accent); color: #18191f; font-weight: 700; }
  .rv-btn.gold:hover { background: var(--accent2); }
  .rv-btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
  .rv-work { min-height: 0; }
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
    <div class="page-title">Review Viewer</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'review'])
    <div id="contentWrapper" style="position: relative;">
<div id="skeleton-content" class="sk-overlay">
      <div style="display:flex;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border)">
        <div class="sk" style="width:340px;height:46px;border-radius:8px"></div>
        <span style="flex:1"></span>
        <div class="sk" style="width:140px;height:34px;border-radius:6px"></div>
      </div>
      <div style="display:grid;grid-template-columns:72px 1fr 360px;height:calc(100% - 68px)">
        <div></div>
        <div style="padding:20px;display:grid;place-items:start center"><div class="sk" style="width:70%;aspect-ratio:8.5/11"></div></div>
        <div style="padding:12px;display:grid;gap:8px;align-content:start">
          @for ($i = 0; $i < 4; $i++)
            <div class="sk" style="height:92px;border-radius:8px"></div>
          @endfor
        </div>
      </div>
    </div>

    <div id="real-content" style="opacity:0">
@if ($turnedIn->isEmpty())
    <div class="card" style="margin:26px;padding:24px;text-align:center;color:var(--text3)">
        No turned-in reviews yet. Once a reviewer marks one of your submissions as reviewed, it'll show up here.
    </div>
@else
@php
    $doc = $selected['document'];
    $rev = $selected['review'];
    $reviewerName = optional($rev->expert)->name ?? 'Unknown Reviewer';
@endphp
<div class="rv-shell">
  <div class="rv-bar">
    <details class="rv-picker">
      <summary class="rv-pick" aria-label="Choose which review to read">
        <span class="rv-pick-ic" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
        <span class="rv-pick-t">
          <b>{{ $doc->file_name }} · {{ $doc->roundLabel() }}</b>
          <small>{{ $reviewerName }} · {{ \App\Http\Controllers\StudentReviewController::roleLabel($rev->role) }} · reviewed {{ optional($rev->reviewed_at)->format('M j, Y') ?? '—' }}</small>
        </span>
        <svg class="rv-chev" viewBox="0 0 24 24" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="rv-menu">
        @foreach ($documents as $d)
          <div class="rv-menu-h">{{ $d->file_name }} · {{ $d->roundLabel() }}</div>
          @foreach ($d->reviews as $r)
            @php
                $done = $r->review_status === \App\Models\CapstoneDocumentReview::REVIEWED;
                $label = \App\Http\Controllers\StudentReviewController::verdictLabel($r);
                $tone = $r->verdict === 'approved' ? 'ok' : ($r->verdict === 'needs_revision' ? 'revise' : '');
            @endphp
            @if ($done)
              <a class="rv-opt" href="{{ route('student.review', ['review_id' => $r->review_id]) }}" @if ($r->review_id === $rev->review_id) aria-current="true" @endif>
                <span>
                  <b>{{ \App\Http\Controllers\StudentReviewController::roleLabel($r->role) }} · {{ optional($r->expert)->name ?? 'Unknown Reviewer' }}</b>
                  <small>reviewed {{ optional($r->reviewed_at)->format('M j, Y') ?? '—' }}</small>
                </span>
                <span class="rv-pill {{ $tone }}">{{ $label }}</span>
              </a>
            @else
              <span class="rv-opt off">
                <span>
                  <b>{{ \App\Http\Controllers\StudentReviewController::roleLabel($r->role) }} · {{ optional($r->expert)->name ?? 'Unknown Reviewer' }}</b>
                  <small>not finished yet</small>
                </span>
                <span class="rv-pill">{{ $label }}</span>
              </span>
            @endif
          @endforeach
        @endforeach
      </div>
    </details>

    @if ($rev->verdict)
      <span class="rv-pill {{ $rev->verdict === 'approved' ? 'ok' : 'revise' }}">{{ \App\Http\Controllers\StudentReviewController::verdictLabel($rev) }}</span>
    @endif
    <span style="flex:1"></span>
    <a href="{{ route('student.review.clf', $rev->review_id) }}" class="rv-btn ghost">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>Download CLF
    </a>
    @if ($rev->drive_web_view_link)
      <a href="{{ $rev->drive_web_view_link }}" target="_blank" rel="noopener" class="rv-btn ghost">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>Download Annotated PDF
      </a>
    @endif
    @if ($rev->verdict === \App\Models\CapstoneDocumentReview::NEEDS_REVISION)
      <button type="button" class="rv-btn gold" onclick="window.openSubmission && window.openSubmission('{{ $decision['itemId'] }}', this)">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>Submit {{ $decision['next'] }}
      </button>
    @endif
  </div>

  <div
    id="student-pdf-viewer-root"
    class="rv-work"
    data-pdf-url="{{ route('student.review.pdf', $rev->review_id) }}"
    data-remarks-url="{{ route('student.review.remarks', $rev->review_id) }}"
    data-reviewer="{{ $reviewerName }}"
    data-general="{{ json_encode($general, JSON_UNESCAPED_UNICODE) }}"
    data-decision="{{ json_encode($decision, JSON_UNESCAPED_UNICODE) }}"
    data-others="{{ json_encode($others, JSON_UNESCAPED_UNICODE) }}"
  ></div>
</div>
@endif
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

// The review picker closes when you click away from it or press Escape.
document.addEventListener('click', function (e) {
  document.querySelectorAll('.rv-picker[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
});
document.addEventListener('keydown', function (e) {
  if (e.key !== 'Escape') return;
  document.querySelectorAll('.rv-picker[open]').forEach(function (d) { d.removeAttribute('open'); d.querySelector('summary').focus(); });
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

@include('partials.escape-closes-modals')
</body>
</html>
