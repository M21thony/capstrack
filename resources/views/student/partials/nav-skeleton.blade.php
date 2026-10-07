{{--
  The outline of a student page, shown once per page per sign-in.

  Right after signing in, and the first time each page is opened, the content
  area - never the sidebar - shows the page's outline while it loads: its real
  headings and labels in place, with a soft shimmer where the team's own data
  will go. A page already opened since signing in comes straight up with no
  outline at all.

  Two moments use the same outline, so it reads as one load:
  - a click in the sidebar or the phone's tab bar to a page not yet opened
    swaps the content area for that page's outline while the server works;
  - the page itself, on its first opening, keeps the outline over its content
    from the very first paint until it has finished loading.

  "Opened" is kept in sessionStorage under the sign-in's session, so signing
  in again starts over. Each <template> is one page's outline, matched on
  data-path; a page without one is simply shown.
--}}
@php
    $navSkeletonPath = fn (string $route): string => parse_url(route($route), PHP_URL_PATH) ?: '/';
    $navSkeletonSession = substr(sha1(session()->getId()), 0, 12);
@endphp
<style nonce="{{ Vite::cspNonce() }}">
  @keyframes nsk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .nsk-page { display: flex; flex-direction: column; gap: 16px; font-family: inherit; }
  .nsk-card { background: #fff; border: 1px solid var(--border, #e0e2e8); border-radius: 14px; padding: 22px; box-shadow: 0 1px 4px rgba(0, 0, 0, .05); display: flex; flex-direction: column; gap: 12px; }
  .nsk { background: linear-gradient(90deg, #eceef2 25%, #f6f7f9 50%, #eceef2 75%); background-size: 200% 100%; animation: nsk-shimmer 1.6s ease-in-out infinite; border-radius: 6px; flex-shrink: 0; }
  .nsk-hero { background: var(--navy-mid, #1e2028); border-radius: 18px; padding: 24px 22px; display: flex; flex-direction: column; gap: 14px; color: rgba(255, 255, 255, .5); }
  .nsk-hero .nsk { background: linear-gradient(90deg, #2a2d38 25%, #333744 50%, #2a2d38 75%); background-size: 200% 100%; }
  .nsk-label { font-size: 10.5px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; color: var(--text3, #8a909e); }
  .nsk-heading { font-size: 15px; font-weight: 600; color: var(--text, #1e2128); }
  .nsk-big { font-size: 26px; font-weight: 700; color: var(--text, #1e2128); }
  .nsk-text { font-size: 13px; color: var(--text2, #4a5060); }
  .nsk-muted { font-size: 12px; color: var(--text3, #8a909e); }
  .nsk-link { font-size: 12.5px; font-weight: 600; color: var(--accent3, #8a6e10); }
  .nsk-tab { display: inline-flex; align-items: center; height: 38px; padding: 0 16px; border-radius: 10px; font-size: 13.5px; font-weight: 600; color: var(--text2, #4a5060); }
  .nsk-tab.on { background: #fff; color: var(--text, #1e2128); box-shadow: 0 1px 3px rgba(0, 0, 0, .08); }
  .nsk-tabs { display: inline-flex; gap: 4px; padding: 4px; border-radius: 12px; background: var(--bg4, #eceef2); align-self: flex-start; }
  .nsk-field { display: flex; align-items: center; height: 40px; padding: 0 14px; border: 1px solid var(--border, #e0e2e8); border-radius: 9px; background: #fff; font-size: 13px; color: var(--text3, #8a909e); }
  .nsk-button { display: inline-flex; align-items: center; justify-content: center; height: 38px; padding: 0 16px; border-radius: 9px; font-size: 13px; font-weight: 700; background: var(--accent, #c9a82c); color: #18191f; opacity: .55; }
  .nsk-row { display: flex; align-items: center; gap: 12px; }
  .nsk-col { display: flex; flex-direction: column; gap: 7px; flex: 1; min-width: 0; }
  .nsk-grow { flex: 1; }
  .nsk-round { border-radius: 50%; }
  .nsk-pill { border-radius: 999px; }
  .nsk-line { height: 10px; }
  .nsk-sep { border-top: 1px solid var(--border, #e0e2e8); }
  .nsk-grid { display: grid; gap: 16px; }
  .nsk-g2 { grid-template-columns: 1fr 1fr; }
  .nsk-g3 { grid-template-columns: repeat(3, 1fr); }
  .nsk-g4 { grid-template-columns: repeat(4, 1fr); }
  .nsk-team { grid-template-columns: 1.55fr 1fr; }
  .nsk-experts { grid-template-columns: 1fr 300px; }
  .nsk-check { grid-template-columns: 320px 1fr 280px; }
  .nsk-up-next { grid-template-columns: 1.5fr 1fr 1fr; }
  .nsk-cal { display: grid; grid-template-columns: repeat(7, 1fr); gap: 14px 10px; text-align: center; }

  /* The first opening of a page: the outline sits over the content area from
     the first paint - right of the sidebar, under the top bar. */
  #nsk-first { position: fixed; top: 60px; left: 250px; right: 0; bottom: 0; z-index: 50; overflow: hidden; padding: 26px; background: var(--bg, #f2f3f5); transition: opacity .18s ease; }
  #nsk-first.nsk-done { opacity: 0; pointer-events: none; }
  @media (max-width: 900px) {
    .nsk-g2, .nsk-g3, .nsk-g4, .nsk-team, .nsk-experts, .nsk-check, .nsk-up-next { grid-template-columns: 1fr; }
  }
  @media (max-width: 768px) { #nsk-first { left: 0; padding: 16px; } }
  @media (prefers-reduced-motion: reduce) { .nsk { animation: none; } #nsk-first { transition: none; } }
</style>

{{-- Dashboard --}}
<template data-nav-skeleton data-path="{{ $navSkeletonPath('student.dashboard') }}" data-title="Dashboard">
  <div class="nsk-page" aria-hidden="true">
    <div class="nsk-card">
      <div class="nsk-row"><span class="nsk-label">Where your team is</span><span class="nsk-grow"></span><span class="nsk-link">Open Progress Tracker →</span></div>
      <div class="nsk-row" style="align-items:flex-start;gap:24px">
        <div class="nsk-col" style="max-width:360px">
          <div class="nsk" style="height:30px;width:80px"></div>
          <div class="nsk nsk-pill" style="height:7px;width:100%"></div>
          <div class="nsk nsk-line" style="width:65%"></div>
        </div>
        <div class="nsk" style="height:38px;flex:1;border-radius:10px"></div>
      </div>
    </div>
    <div class="nsk-card" style="min-height:190px">
      <span class="nsk-label">Announcements</span>
      <div class="nsk nsk-line" style="width:70%"></div><div class="nsk nsk-line" style="width:45%"></div>
    </div>
    <div class="nsk-grid nsk-g2">
      @foreach (['Capstone expert', 'Team'] as $panel)
        <div class="nsk-card">
          <span class="nsk-label" style="margin-bottom:6px">{{ $panel }}</span>
          @foreach (range(1, 3) as $row)
            <div class="nsk-row" style="padding:5px 0"><div class="nsk nsk-round" style="width:8px;height:8px"></div><div class="nsk-col"><div class="nsk nsk-line" style="width:{{ $loop->odd ? 32 : 40 }}%;height:8px"></div><div class="nsk nsk-line" style="width:{{ $loop->odd ? 46 : 36 }}%"></div></div></div>
            @unless ($loop->last)<div class="nsk-sep"></div>@endunless
          @endforeach
        </div>
      @endforeach
    </div>
    <div class="nsk-card" style="min-height:120px"><span class="nsk-label">Defense results</span></div>
  </div>
</template>

{{-- Team Management --}}
<template data-nav-skeleton data-path="{{ $navSkeletonPath('student.team') }}" data-title="Team Management">
  <div class="nsk-page" aria-hidden="true">
    <div class="nsk-tabs"><span class="nsk-tab on">Team</span><span class="nsk-tab">Financial Records</span></div>
    <div class="nsk-card" style="padding:16px 18px">
      <div class="nsk-row"><div class="nsk" style="width:44px;height:44px;border-radius:10px"></div><div class="nsk-col"><div class="nsk" style="height:14px;width:160px"></div><div class="nsk nsk-line" style="width:210px"></div></div></div>
    </div>
    <div class="nsk-grid nsk-team">
      <div class="nsk-card" style="padding:18px 16px">
        <span class="nsk-label">Team members · max 5</span>
        @foreach (range(1, 3) as $member)
          <div class="nsk-row"><div class="nsk nsk-round" style="width:36px;height:36px"></div><div class="nsk-col"><div class="nsk nsk-line" style="width:140px"></div><div class="nsk nsk-line" style="width:90px;height:8px"></div></div><div class="nsk nsk-pill" style="width:62px;height:20px"></div></div>
        @endforeach
        <div class="nsk nsk-line" style="width:150px;margin-top:6px"></div>
      </div>
      <div class="nsk-col" style="gap:16px">
        <div class="nsk-card" style="padding:18px 16px;gap:10px">
          <span class="nsk-label">Team overview</span>
          @foreach (['Section', 'Team Name', 'Status'] as $label)
            <div class="nsk-row"><span class="nsk-muted">{{ $label }}</span><span class="nsk-grow"></span><div class="nsk nsk-line" style="width:64px"></div></div>
          @endforeach
        </div>
        <div class="nsk-card" style="padding:18px 16px;gap:10px"><span class="nsk-label">Defense panel</span><div class="nsk nsk-line" style="width:80%"></div></div>
      </div>
    </div>
    <div class="nsk-col" style="gap:6px;margin-top:10px">
      <span class="nsk-big">Team Selection</span>
      <span class="nsk-text">Find teammates or a capstone team within your section</span>
    </div>
  </div>
</template>

{{-- Experts --}}
<template data-nav-skeleton data-path="{{ $navSkeletonPath('student.experts') }}" data-title="Experts">
  <div class="nsk-page" aria-hidden="true">
    <div class="nsk-row" style="flex-wrap:wrap"><span class="nsk-text">Your team's adviser, consultant and grammarian, and the sessions you book with them.</span><span class="nsk-grow"></span><div class="nsk-tabs"><span class="nsk-tab on">Consultations</span><span class="nsk-tab">Find experts</span><span class="nsk-tab">Evaluations</span></div></div>
    <span class="nsk-label">Your team's experts</span>
    <div class="nsk-grid nsk-g3">
      @foreach (['Adviser', 'Consultant', 'Grammarian'] as $role)
        <div class="nsk-card" style="padding:16px"><div class="nsk-row"><div class="nsk nsk-round" style="width:44px;height:44px"></div><div class="nsk-col"><span class="nsk-label">{{ $role }}</span><div class="nsk nsk-line" style="width:120px"></div><div class="nsk nsk-line" style="width:90px;height:8px"></div></div></div></div>
      @endforeach
    </div>
    <div class="nsk-grid nsk-experts">
      <div class="nsk-card">
        <div class="nsk-row"><div class="nsk-col"><div class="nsk" style="height:14px;width:200px"></div><div class="nsk nsk-line" style="width:280px;max-width:100%"></div></div><div class="nsk" style="width:190px;height:36px;border-radius:10px"></div></div>
        <div class="nsk-cal" style="margin-top:8px">
          @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)<span class="nsk-label">{{ $day }}</span>@endforeach
          @foreach (range(1, 35) as $date)<div class="nsk nsk-round" style="width:24px;height:24px;margin:auto"></div>@endforeach
        </div>
      </div>
      <div class="nsk-card" style="align-self:start">
        <span class="nsk-heading">My consultations</span>
        <div class="nsk-tabs" style="align-self:stretch"><span class="nsk-tab on" style="flex:1;justify-content:center">Upcoming</span><span class="nsk-tab" style="flex:1;justify-content:center">Past</span></div>
        <div class="nsk" style="height:64px;border-radius:10px"></div>
      </div>
    </div>
  </div>
</template>

{{-- Document Submission --}}
<template data-nav-skeleton data-path="{{ $navSkeletonPath('student.submission') }}" data-title="Document Submission">
  <div class="nsk-page" aria-hidden="true">
    <div class="nsk-card" style="max-width:980px;padding:0;gap:0">
      <div class="nsk-row" style="padding:18px 20px"><div class="nsk-col" style="gap:4px"><span class="nsk-heading">Your team's submissions</span><span class="nsk-muted">Newest first. Open the Review Viewer to read a reviewer's remarks.</span></div><span class="nsk-button">Submit a document</span></div>
      <div class="nsk-sep"></div>
      @foreach (range(1, 3) as $row)
        <div class="nsk-row" style="padding:16px 20px"><div class="nsk" style="width:34px;height:34px;border-radius:8px"></div><div class="nsk-col"><div class="nsk nsk-line" style="width:{{ 36 + $row * 8 }}%"></div><div class="nsk nsk-line" style="width:26%;height:8px"></div></div><div class="nsk nsk-pill" style="width:80px;height:22px"></div></div>
        @unless ($loop->last)<div class="nsk-sep"></div>@endunless
      @endforeach
    </div>
  </div>
</template>

{{-- Review Viewer --}}
<template data-nav-skeleton data-path="{{ $navSkeletonPath('student.review') }}" data-title="Review Viewer">
  <div class="nsk-page" aria-hidden="true" style="padding:0 26px">
    @foreach (range(1, 3) as $row)
      <div class="nsk-card" style="padding:18px 22px"><div class="nsk-row"><div class="nsk" style="width:38px;height:38px;border-radius:9px"></div><div class="nsk-col"><div class="nsk nsk-line" style="width:{{ 28 + $row * 10 }}%"></div><div class="nsk nsk-line" style="width:22%;height:8px"></div></div><div class="nsk" style="width:110px;height:32px;border-radius:8px"></div></div></div>
    @endforeach
  </div>
</template>

{{-- Progress Tracker --}}
<template data-nav-skeleton data-path="{{ $navSkeletonPath('student.progress') }}" data-title="Our milestones">
  <div class="nsk-page" aria-hidden="true">
    <div class="nsk-hero">
      <div class="nsk-row" style="align-items:flex-start"><div class="nsk-col"><span class="nsk-label" style="color:var(--accent, #c9a82c)">Your team</span><div class="nsk" style="height:22px;width:130px"></div><div class="nsk-row" style="gap:8px">@foreach ([60, 140, 150] as $width)<div class="nsk nsk-pill" style="height:22px;width:{{ $width }}px"></div>@endforeach</div></div><div class="nsk" style="height:36px;width:76px"></div></div>
      <div class="nsk nsk-pill" style="height:6px"></div>
      <div class="nsk-row" style="justify-content:space-around">@foreach (range(1, 5) as $stage)<div class="nsk-col" style="align-items:center;flex:0 0 auto"><div class="nsk nsk-round" style="width:28px;height:28px"></div><div class="nsk" style="height:8px;width:66px"></div></div>@endforeach</div>
    </div>
    <div class="nsk-grid nsk-up-next">
      <div class="nsk-card" style="justify-content:center"><div class="nsk-row"><div class="nsk" style="width:42px;height:42px;border-radius:10px"></div><div class="nsk-col"><span class="nsk-label">Up next</span><div class="nsk" style="height:13px;width:170px"></div><div class="nsk nsk-line" style="width:240px;max-width:100%"></div></div></div></div>
      @foreach ([['Overdue', 'Closed'], ['Waiting', 'Panel scores']] as $column)
        <div class="nsk-col" style="gap:12px">@foreach ($column as $stat)<div class="nsk-card" style="padding:14px 16px;gap:6px"><div class="nsk" style="height:18px;width:24px"></div><span class="nsk-muted">{{ $stat }}</span></div>@endforeach</div>
      @endforeach
    </div>
    <div class="nsk-card">
      <div class="nsk-row"><span class="nsk-heading">Milestones</span><span class="nsk-grow"></span><span class="nsk-muted">Closed · Waiting · Overdue · Not started</span></div>
      @foreach (range(1, 3) as $milestone)
        <div class="nsk-row" style="padding:6px 0"><div class="nsk nsk-round" style="width:38px;height:38px"></div><div class="nsk-col"><div class="nsk nsk-line" style="width:170px;height:12px"></div><div class="nsk nsk-line" style="width:140px;height:8px"></div></div></div>
      @endforeach
    </div>
  </div>
</template>

{{-- Pre-Proposal Submission --}}
<template data-nav-skeleton data-path="{{ $navSkeletonPath('student.duplication') }}" data-title="Pre-Proposal Submission">
  <div class="nsk-page" aria-hidden="true">
    <div class="nsk-card" style="padding:16px 18px"><div class="nsk-row"><div class="nsk" style="width:50px;height:50px;border-radius:10px"></div><div class="nsk-col"><div class="nsk" style="height:14px;width:190px"></div><div class="nsk nsk-line" style="width:440px;max-width:100%"></div></div></div></div>
    <div class="nsk-grid nsk-check">
      <div class="nsk-col" style="gap:16px">
        <div class="nsk-card"><span class="nsk-label">Check document</span><div class="nsk" style="height:120px;border-radius:10px"></div><span class="nsk-button" style="width:100%">Run Similarity Check</span></div>
        <div class="nsk-card" style="min-height:200px"><span class="nsk-label">Suggested experts</span></div>
      </div>
      <div class="nsk-card" style="min-height:420px"><span class="nsk-label">Matched capstone projects</span><div class="nsk nsk-line" style="width:40%"></div><div class="nsk nsk-line" style="width:60%"></div></div>
      <div class="nsk-card" style="min-height:420px"><span class="nsk-label">Your pre-proposals</span><div class="nsk nsk-line" style="width:55%;height:8px"></div></div>
    </div>
  </div>
</template>

{{-- Resources --}}
<template data-nav-skeleton data-path="{{ $navSkeletonPath('student.resources') }}" data-title="Resources">
  <div class="nsk-page" aria-hidden="true">
    <div class="nsk-grid nsk-g4">
      @foreach (['Total files', 'New this week', 'Downloaded', 'Categories'] as $total)<div class="nsk-card" style="padding:18px;gap:8px"><div class="nsk" style="height:22px;width:26px"></div><span class="nsk-label">{{ $total }}</span></div>@endforeach
    </div>
    <div class="nsk-row" style="gap:8px;flex-wrap:wrap"><span class="nsk-field" style="width:160px">All Categories</span><span class="nsk-field" style="width:210px">Search resources…</span><span class="nsk-field" style="width:180px">All Folders</span></div>
    @foreach (range(1, 3) as $file)
      <div class="nsk-card" style="padding:14px 18px"><div class="nsk-row"><div class="nsk" style="width:36px;height:36px;border-radius:8px"></div><div class="nsk-col"><div class="nsk nsk-line" style="width:{{ 24 + $file * 9 }}%"></div><div class="nsk nsk-line" style="width:18%;height:8px"></div></div></div></div>
    @endforeach
  </div>
</template>

{{-- Archived Capstones --}}
<template data-nav-skeleton data-path="{{ $navSkeletonPath('student.archive') }}" data-title="Archived Capstones">
  <div class="nsk-page" aria-hidden="true">
    <div class="nsk-card" style="padding:10px;flex-direction:row;gap:8px"><span class="nsk-field nsk-grow" style="background:var(--bg3, #f7f8fa)">Search titles, proponents or abstracts</span><span class="nsk-field" style="width:190px">All school years</span><span class="nsk-field" style="width:190px">All categories</span></div>
    <div class="nsk-grid nsk-g2">
      @foreach (range(1, 4) as $study)
        <div class="nsk-card"><div class="nsk" style="height:14px;width:80%"></div><div class="nsk nsk-line" style="width:50%"></div><div class="nsk nsk-line" style="width:92%;height:8px"></div><div class="nsk nsk-line" style="width:76%;height:8px"></div></div>
      @endforeach
    </div>
  </div>
</template>

{{-- The first opening of this page: drawn here, before the page's content, so
     it is already in place at the first paint. --}}
<div id="nsk-first" hidden></div>
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  if (window.__navSkeleton) return;
  window.__navSkeleton = true;

  var SESSION = 'nsk:{{ $navSkeletonSession }}:';

  function trim(path) { return path.replace(/\/+$/, '') || '/'; }
  function opened(path) { try { return sessionStorage.getItem(SESSION + trim(path)) === '1'; } catch (e) { return true; } }
  function markOpened(path) { try { sessionStorage.setItem(SESSION + trim(path), '1'); } catch (e) {} }

  function templateFor(path) {
    var templates = document.querySelectorAll('template[data-nav-skeleton]');
    for (var i = 0; i < templates.length; i++) {
      if (trim(templates[i].dataset.path) === trim(path)) return templates[i];
    }
    return null;
  }

  // This page, opened for the first time since signing in: hold its outline
  // over the content until the page has finished loading.
  var here = location.pathname;
  var first = document.getElementById('nsk-first');
  var ownTemplate = templateFor(here);

  if (!opened(here) && ownTemplate && first) {
    first.appendChild(ownTemplate.content.cloneNode(true));
    first.hidden = false;
    var shownAt = Date.now();

    window.addEventListener('load', function () {
      setTimeout(function () {
        first.classList.add('nsk-done');
        setTimeout(function () { first.remove(); }, 200);
      }, Math.max(0, 350 - (Date.now() - shownAt)));
    });
  } else if (first) {
    first.remove();
  }
  markOpened(here);

  // A click to a page not opened yet: its outline straight away, in the
  // content area only, while the server works.
  var shown = null;

  function restore() {
    if (!shown) return;
    clearTimeout(shown.timer);
    shown.content.replaceChildren(shown.page);
    shown.content.scrollTop = shown.scrollTop;
    shown.content.removeAttribute('aria-busy');
    if (shown.title) shown.title.textContent = shown.titleText;
    if (shown.link) shown.link.classList.remove('active');
    shown.wasActive.forEach(function (item) { item.classList.add('active'); });
    shown = null;
  }

  function show(template, link) {
    var content = document.querySelector('.main > .content');
    if (!content) return;

    var title = document.querySelector('.main .topbar .page-title');
    var page = document.createDocumentFragment();
    var scrollTop = content.scrollTop;
    while (content.firstChild) page.appendChild(content.firstChild);

    var wasActive = Array.prototype.slice.call(document.querySelectorAll('.sidebar .nav-item.active'));
    shown = { content: content, page: page, scrollTop: scrollTop, title: title, titleText: title ? title.textContent : '', wasActive: wasActive, link: null };

    content.appendChild(template.content.cloneNode(true));
    content.scrollTop = 0;
    content.setAttribute('aria-busy', 'true');
    if (title && template.dataset.title) title.textContent = template.dataset.title;

    if (link.classList.contains('nav-item')) {
      wasActive.forEach(function (item) { item.classList.remove('active'); });
      link.classList.add('active');
      shown.link = link;
    }

    shown.timer = setTimeout(restore, 15000);
  }

  document.addEventListener('click', function (event) {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    var link = event.target.closest('.sidebar a[href], .mtab a[href]');
    if (!link || link.target || link.hasAttribute('download')) return;

    var url = new URL(link.href, location.href);
    if (url.origin !== location.origin) return;
    if (trim(url.pathname) === trim(location.pathname)) return;
    if (opened(url.pathname)) return;

    var template = templateFor(url.pathname);
    if (!template || shown) return;

    // Whatever else answers this click goes first; if it stopped the
    // navigation, the page stays as it is.
    setTimeout(function () {
      if (!event.defaultPrevented) show(template, link);
    }, 0);
  });

  window.addEventListener('pageshow', function (event) {
    if (event.persisted) restore();
  });
})();
</script>
