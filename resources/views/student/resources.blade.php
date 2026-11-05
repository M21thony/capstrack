<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content" />
<title>Resources - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
@vite('resources/student-ui/styles.css')
<x-dashboard.styles />
<style nonce="{{ Vite::cspNonce() }}">
  @keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .sk-overlay { position: absolute; inset: 0; background: var(--bg); z-index: 10; }
  .sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }

  /* Resources: one list of everything a student can download - their
     CaPSA's files and the rubrics their panel grades with. A row opens in
     place for its details; View opens the file itself in a side panel. */
  .rs { background: #fff; border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
  .rs-tools { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 14px 18px; border-bottom: 1px solid var(--border); }
  .rs-search { display: flex; align-items: center; gap: 8px; flex: 1 1 320px; max-width: 460px; min-height: 44px; padding: 0 12px; box-sizing: border-box; border: 1px solid var(--border); border-radius: 10px; background: var(--bg3); }
  .rs-search:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(201,168,44,.22); background: #fff; }
  .rs-search svg { width: 15px; height: 15px; flex: none; stroke: var(--text3); fill: none; stroke-width: 2; stroke-linecap: round; }
  .rs-search input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; font: inherit; font-size: 14.5px; color: var(--text); }
  .rs-select { position: relative; display: inline-flex; align-items: center; }
  .rs-select select { appearance: none; -webkit-appearance: none; height: 44px; padding: 0 36px 0 14px; border-radius: 10px; border: 1px solid var(--border); background: #fff; color: var(--text); font: inherit; font-size: 14px; font-weight: 600; cursor: pointer; }
  .rs-select select:hover { border-color: var(--border2); }
  .rs-select select:focus-visible { outline: 2px solid var(--accent3); outline-offset: 2px; }
  .rs-select svg { position: absolute; right: 12px; width: 13px; height: 13px; pointer-events: none; stroke: var(--text2); fill: none; stroke-width: 2.2; stroke-linecap: round; }
  .rs-count { margin-left: auto; font-size: 13px; color: #656b7a; }
  .rs [hidden], .rs-sheet [hidden] { display: none !important; }
  .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

  .rs-list { list-style: none; margin: 0; padding: 0; }
  .rs-item { border-bottom: 1px solid #eceef2; background: #fff; }
  .rs-item:last-child { border-bottom: 0; }
  .rs-item.is-open { background: #fffdf6; box-shadow: inset 3px 0 0 var(--accent); }
  .rs-row { width: 100%; display: flex; align-items: center; gap: 14px; min-height: 64px; padding: 10px 18px; border: 0; background: transparent; color: var(--text); text-align: left; font: inherit; cursor: pointer; }
  .rs-row:hover { background: #fafafb; }
  .rs-item.is-open .rs-row:hover { background: transparent; }
  .rs-row:focus-visible { outline: 2px solid var(--accent3); outline-offset: -2px; }
  .rs-ico { flex: 0 0 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 10.5px; font-weight: 700; letter-spacing: .02em; }
  .rs-ico.t-pdf { background: #fee2e2; color: #b91c1c; }
  .rs-ico.t-docx { background: #dbeafe; color: #1d4ed8; }
  .rs-ico.t-pptx { background: #ffedd5; color: #c2410c; }
  .rs-ico.t-rub { background: #faf3d9; color: #7d630e; }
  .rs-ico.t-other { background: var(--bg4); color: var(--text2); }
  .rs-text { flex: 1; min-width: 0; }
  .rs-title { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 15px; font-weight: 600; line-height: 1.35; }
  .rs-new { font-size: 10.5px; font-weight: 700; padding: 1px 7px; border-radius: 999px; background: #dbeafe; color: #1e40af; }
  .rs-meta { display: block; margin-top: 2px; font-size: 13px; color: #656b7a; }
  .rs-chev { display: flex; color: #656b7a; transition: transform .15s; }
  .rs-chev svg { width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; }
  .rs-item.is-open .rs-chev { transform: rotate(180deg); }
  .rs-more { padding: 0 18px 18px 76px; display: flex; flex-direction: column; gap: 10px; }
  .rs-more p { margin: 0; max-width: 70ch; font-size: 14px; line-height: 1.55; color: var(--text2); }
  .rs-facts { margin: 0; display: grid; grid-template-columns: 110px 1fr; gap: 4px 12px; font-size: 13.5px; }
  .rs-facts dt { color: #656b7a; }
  .rs-facts dd { margin: 0; }
  .rs-acts { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 2px; }
  .rs-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 42px; padding: 0 18px; box-sizing: border-box; border-radius: 10px; border: 1px solid #d6d8df; background: #fff; color: var(--text); font: inherit; font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
  .rs-btn:hover { border-color: var(--text); }
  .rs-btn:focus-visible { outline: 2px solid var(--accent3); outline-offset: 2px; }
  .rs-btn svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
  .rs-btn.dark { background: #1e2128; border-color: #1e2128; color: #fff; font-weight: 700; }
  .rs-btn.dark:hover { background: #2e3240; }
  .rs-btn.gold { background: var(--accent); border-color: var(--accent); color: #18191f; font-weight: 700; }
  .rs-btn.gold:hover { background: var(--accent2); }
  .rs-empty { padding: 40px 18px; display: flex; flex-direction: column; align-items: center; gap: 10px; text-align: center; }
  .rs-empty b { font-size: 15px; }
  .rs-empty span { font-size: 13.5px; color: #656b7a; max-width: 46ch; }

  /* The file itself, beside the list. Full screen on a phone. */
  .rs-sheet { position: fixed; inset: 0; z-index: 1200; display: flex; justify-content: flex-end; }
  .rs-sheet[hidden] { display: none; }
  .rs-scrim { flex: 1; border: 0; background: rgba(24,25,31,.38); cursor: pointer; }
  .rs-panel { flex: 0 0 min(560px, 100%); display: flex; flex-direction: column; background: #fff; box-shadow: -12px 0 32px rgba(24,25,31,.18); }
  .rs-panel-head { display: flex; align-items: flex-start; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--border); }
  .rs-panel-head h2 { margin: 0; font-size: 17px; font-weight: 700; line-height: 1.3; }
  .rs-panel-head .rs-meta { margin-top: 2px; }
  .rs-x { flex: 0 0 44px; height: 44px; border-radius: 10px; border: 1px solid var(--border); background: #fff; color: var(--text2); display: flex; align-items: center; justify-content: center; cursor: pointer; }
  .rs-x svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; }
  .rs-panel-body { flex: 1; min-height: 0; overflow: auto; display: flex; flex-direction: column; }
  .rs-frame { flex: 1; min-height: 420px; background: #eceef2; display: flex; }
  .rs-frame iframe { flex: 1; width: 100%; border: 0; background: #eceef2; }
  .rs-frame-none { margin: auto; padding: 24px; text-align: center; font-size: 14px; color: var(--text2); }
  .rs-panel-info { padding: 16px 20px; display: flex; flex-direction: column; gap: 12px; }
  .rs-panel-info p { margin: 0; font-size: 14px; line-height: 1.55; color: var(--text2); }
  .rs-panel-foot { display: flex; gap: 10px; padding: 14px 20px; border-top: 1px solid var(--border); }
  .rs-panel-foot .gold { flex: 1; min-height: 48px; }

  @media (max-width: 1024px) {
    .rs-search { flex-basis: 100%; max-width: none; }
  }
  @media (max-width: 640px) {
    .rs { border-radius: 12px; }
    .rs-tools { padding: 12px; gap: 8px; }
    .rs-select { flex: 1 1 130px; }
    .rs-select select { width: 100%; }
    .rs-count { display: none; }
    .rs-row { padding: 10px 12px; gap: 12px; }
    .rs-ico { flex-basis: 40px; height: 40px; }
    .rs-more { padding: 0 12px 16px; }
    .rs-facts { grid-template-columns: 90px 1fr; }
    .rs-acts > * { flex: 1 1 auto; }
    .rs-scrim { display: none; }
    .rs-panel { flex-basis: 100%; }
    .rs-frame { min-height: 360px; }
  }
  @media (prefers-reduced-motion: reduce) { .rs-chev { transition: none; } }
</style>
@include('partials.skeleton-head', ['skReveal' => 'opacity'])
  @include('partials.render-whole')
</head>
<body>

@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="toast" id="toast"></div>
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Resources</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>

  <div class="content">
    <div id="contentWrapper" style="position: relative;">
    <div id="skeleton-content" class="sk-overlay">
      <div class="rs">
        <div class="rs-tools">
          <div class="sk" style="flex:1 1 320px;max-width:460px;height:44px;border-radius:10px"></div>
          <div class="sk" style="width:160px;height:44px;border-radius:10px"></div>
          <div class="sk" style="width:130px;height:44px;border-radius:10px"></div>
        </div>
        @for ($i = 0; $i < 6; $i++)
          <div style="display:flex;align-items:center;gap:14px;padding:10px 18px;min-height:64px;border-bottom:1px solid #eceef2">
            <div class="sk" style="width:44px;height:44px;border-radius:10px;flex-shrink:0"></div>
            <div style="flex:1">
              <div class="sk" style="width:45%;height:13px;border-radius:4px;margin-bottom:7px"></div>
              <div class="sk" style="width:30%;height:10px;border-radius:4px"></div>
            </div>
          </div>
        @endfor
      </div>
    </div>

    <div id="real-content" style="opacity:0">
      <section class="rs" aria-label="Resources">
        <div class="rs-tools">
          <label class="rs-search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <span class="sr-only">Search resources</span>
            <input type="search" id="rs-q" placeholder="Search resources" autocomplete="off">
          </label>
          <label class="rs-select">
            <span class="sr-only">Category</span>
            <select id="rs-cat">
              <option value="all">All categories</option>
              @foreach ($categories as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
              @endforeach
            </select>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
          </label>
          <label class="rs-select">
            <span class="sr-only">Sort</span>
            <select id="rs-sort">
              <option value="new">Newest first</option>
              <option value="old">Oldest first</option>
              <option value="az">A to Z</option>
            </select>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
          </label>
          <span class="rs-count" id="rs-count" aria-live="polite">{{ $items->count() }} {{ Str::plural('file', $items->count()) }}</span>
        </div>

        <ul class="rs-list" id="rs-list">
          @foreach ($items as $item)
            @php
              $t = strtolower($item['type']);
              $icon = $item['kind'] === 'rubric' ? 't-rub' : (in_array($t, ['pdf', 'docx', 'pptx'], true) ? 't-'.$t : 't-other');
              $meta = $item['kind'] === 'rubric'
                  ? 'Grading rubric · '.$item['forProgram'].' · '.$item['points'].' pts'
                  : collect([$item['categoryLabel'], $item['type'], $item['size'], $item['date']])->filter()->implode(' · ');
            @endphp
            <li class="rs-item" data-key="{{ $item['key'] }}" data-cat="{{ $item['category'] }}" data-sort="{{ $loop->remaining }}" data-title="{{ Str::lower($item['title']) }}" data-find="{{ Str::lower($item['title'].' '.$item['categoryLabel']) }}">
              <button type="button" class="rs-row" aria-expanded="false" aria-controls="rs-more-{{ $item['key'] }}">
                <span class="rs-ico {{ $icon }}" aria-hidden="true">{{ $item['kind'] === 'rubric' ? 'RUB' : $item['type'] }}</span>
                <span class="rs-text">
                  <span class="rs-title">{{ $item['title'] }}@if ($item['isNew'])<span class="rs-new">New</span>@endif</span>
                  <span class="rs-meta">{{ $meta }}</span>
                </span>
                <span class="rs-chev" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></span>
              </button>
              <div class="rs-more" id="rs-more-{{ $item['key'] }}" hidden>
                @if ($item['description'])
                  <p>{{ $item['description'] }}</p>
                @endif
                <dl class="rs-facts">
                  @if ($item['kind'] === 'rubric')
                    <dt>For</dt><dd>{{ $item['forWho'] }}</dd>
                    <dt>Format</dt><dd>PDF, letter size</dd>
                  @else
                    <dt>Category</dt><dd>{{ $item['categoryLabel'] }}</dd>
                    <dt>Posted</dt><dd>{{ $item['date'] ? $item['date'].' by your CaPSA' : 'By your CaPSA' }}</dd>
                  @endif
                </dl>
                <div class="rs-acts">
                  @if ($item['previewUrl'] || $item['openUrl'])
                    <button type="button" class="rs-btn" data-view
                      data-title="{{ $item['title'] }}" data-meta="{{ $meta }}" data-icon="{{ $icon }}" data-label="{{ $item['kind'] === 'rubric' ? 'RUB' : $item['type'] }}"
                      data-desc="{{ $item['description'] }}" data-preview="{{ $item['previewUrl'] }}" data-open="{{ $item['openUrl'] }}" data-download="{{ $item['downloadUrl'] }}">View</button>
                  @endif
                  @if ($item['downloadUrl'])
                    <a class="rs-btn dark" href="{{ $item['downloadUrl'] }}" @if ($item['kind'] === 'rubric') download @endif>
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>
                      Download
                    </a>
                  @endif
                </div>
              </div>
            </li>
          @endforeach
        </ul>

        <div class="rs-empty" id="rs-nomatch" hidden>
          <b>Nothing matches</b>
          <span>Try another word, or show all categories.</span>
          <button type="button" class="rs-btn" id="rs-clear">Clear filters</button>
        </div>
        @if ($items->isEmpty())
          <div class="rs-empty">
            <b>Nothing here yet</b>
            <span>Templates, lessons and forms show up here once your CaPSA posts them.</span>
          </div>
        @endif
      </section>
    </div>
    </div>
  </div>
</div>

<div class="rs-sheet" id="rs-sheet" hidden>
  <button type="button" class="rs-scrim" aria-label="Close preview" data-close></button>
  <aside class="rs-panel" role="dialog" aria-modal="true" aria-labelledby="rs-pv-title">
    <div class="rs-panel-head">
      <span class="rs-ico" id="rs-pv-icon" aria-hidden="true"></span>
      <div style="flex:1;min-width:0">
        <h2 id="rs-pv-title"></h2>
        <span class="rs-meta" id="rs-pv-meta"></span>
      </div>
      <button type="button" class="rs-x" aria-label="Close" data-close>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
      </button>
    </div>
    <div class="rs-panel-body">
      <div class="rs-frame" id="rs-pv-frame"></div>
      <div class="rs-panel-info" id="rs-pv-info">
        <p id="rs-pv-desc"></p>
      </div>
    </div>
    <div class="rs-panel-foot">
      <a class="rs-btn gold" id="rs-pv-download" href="#">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>
        Download
      </a>
      <a class="rs-btn" id="rs-pv-open" href="#" target="_blank" rel="noopener">Open in Drive</a>
    </div>
  </aside>
</div>

<script nonce="{{ Vite::cspNonce() }}">
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

(function () {
  var list = document.getElementById('rs-list');
  var q = document.getElementById('rs-q');
  var cat = document.getElementById('rs-cat');
  var sort = document.getElementById('rs-sort');
  var count = document.getElementById('rs-count');
  var noMatch = document.getElementById('rs-nomatch');
  var items = Array.prototype.slice.call(list.children);

  function render() {
    var needle = q.value.trim().toLowerCase();
    var shown = 0;

    items.forEach(function (li) {
      var on = (cat.value === 'all' || li.dataset.cat === cat.value)
        && (!needle || li.dataset.find.indexOf(needle) !== -1);
      li.hidden = !on;
      if (on) { shown++; }
    });

    items.slice().sort(function (a, b) {
      if (sort.value === 'az') { return a.dataset.title.localeCompare(b.dataset.title); }
      var d = Number(a.dataset.sort) - Number(b.dataset.sort);
      return sort.value === 'old' ? d : -d;
    }).forEach(function (li) { list.appendChild(li); });

    count.textContent = shown + (shown === 1 ? ' file' : ' files');
    noMatch.hidden = shown > 0 || items.length === 0;
  }

  q.addEventListener('input', render);
  cat.addEventListener('change', render);
  sort.addEventListener('change', render);
  document.getElementById('rs-clear').addEventListener('click', function () {
    q.value = '';
    cat.value = 'all';
    render();
    q.focus();
  });

  // One row open at a time: opening another closes the last.
  list.addEventListener('click', function (e) {
    var row = e.target.closest('.rs-row');
    if (!row) { return; }
    var li = row.parentElement;
    var open = !li.classList.contains('is-open');

    items.forEach(function (other) {
      other.classList.remove('is-open');
      other.querySelector('.rs-row').setAttribute('aria-expanded', 'false');
      other.querySelector('.rs-more').hidden = true;
    });

    if (open) {
      li.classList.add('is-open');
      row.setAttribute('aria-expanded', 'true');
      li.querySelector('.rs-more').hidden = false;
    }
  });

  // The preview panel.
  var sheet = document.getElementById('rs-sheet');
  var frame = document.getElementById('rs-pv-frame');
  var opener = null;

  function openPreview(btn) {
    var d = btn.dataset;
    opener = btn;
    var icon = document.getElementById('rs-pv-icon');
    icon.className = 'rs-ico ' + d.icon;
    icon.textContent = d.label;
    document.getElementById('rs-pv-title').textContent = d.title;
    document.getElementById('rs-pv-meta').textContent = d.meta;
    var desc = document.getElementById('rs-pv-desc');
    desc.textContent = d.desc || '';
    document.getElementById('rs-pv-info').hidden = !d.desc;

    frame.textContent = '';
    if (d.preview) {
      var iframe = document.createElement('iframe');
      iframe.src = d.preview;
      iframe.title = d.title;
      iframe.loading = 'lazy';
      frame.appendChild(iframe);
    } else {
      var none = document.createElement('p');
      none.className = 'rs-frame-none';
      none.textContent = 'There is no preview for this one. Open it in Drive instead.';
      frame.appendChild(none);
    }

    var dl = document.getElementById('rs-pv-download');
    dl.hidden = !d.download;
    dl.href = d.download || '#';
    var open = document.getElementById('rs-pv-open');
    open.hidden = !d.open;
    open.href = d.open || '#';

    sheet.hidden = false;
    document.body.style.overflow = 'hidden';
    sheet.querySelector('.rs-x').focus();
  }

  function closePreview() {
    if (sheet.hidden) { return; }
    sheet.hidden = true;
    frame.textContent = '';
    document.body.style.overflow = '';
    if (opener) { opener.focus(); }
  }

  list.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-view]');
    if (btn) { openPreview(btn); }
  });
  sheet.addEventListener('click', function (e) {
    if (e.target.closest('[data-close]')) { closePreview(); }
  });
  document.addEventListener('keydown', function (e) {
    if (sheet.hidden) { return; }
    if (e.key === 'Escape') { closePreview(); return; }

    // Tab stays inside the panel while it is open, as a dialog's should.
    if (e.key === 'Tab') {
      var stops = Array.prototype.filter.call(
        sheet.querySelectorAll('.rs-panel button, .rs-panel a[href], .rs-panel iframe'),
        function (el) { return el.offsetParent !== null; }
      );
      if (!stops.length) { return; }
      var first = stops[0];
      var last = stops[stops.length - 1];
      if (e.shiftKey && (document.activeElement === first || !sheet.contains(document.activeElement))) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && (document.activeElement === last || !sheet.contains(document.activeElement))) {
        e.preventDefault();
        first.focus();
      }
    }
  });

  render();
})();

function showToast(msg, type) {
  if (window.ctToast) { return ctToast(msg, type || 'error'); }
  var toast = document.getElementById('toast');
  toast.textContent = msg;
  toast.classList.add('show');
  setTimeout(function () { toast.classList.remove('show'); }, 4000);
}

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
@include('partials.app-scrollbar')
</body>
</html>
