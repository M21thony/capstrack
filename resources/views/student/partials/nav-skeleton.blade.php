{{--
  The page a student is going to, drawn in outline the moment they click.

  Every student page arrives finished from the server, so between a click in
  the sidebar (or the phone's tab bar) and the next page there is nothing to
  show - the old page sat there unchanged, then the new one replaced it all at
  once, which read as a flash. Now the click swaps the content area, never the
  sidebar, for an outline shaped like the page that is coming: its cards,
  rows and columns in place, shimmering until the real page takes over.

  Each <template> below is one page's outline; the script matches a link's
  address to a template's data-path. A page with no template is left alone.
  If the next page never comes (a download, a cancelled load) the content is
  put back after a while, and coming Back to this page puts it back at once.
--}}
@php
    $navSkeletonPath = fn (string $route): string => parse_url(route($route), PHP_URL_PATH) ?: '/';
@endphp
<style nonce="{{ Vite::cspNonce() }}">
  @keyframes nsk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .nsk-page { display: flex; flex-direction: column; gap: 16px; }
  .nsk-card { background: #fff; border: 1px solid var(--border, #e0e2e8); border-radius: 14px; padding: 22px; box-shadow: 0 1px 4px rgba(0, 0, 0, .05); display: flex; flex-direction: column; gap: 12px; }
  .nsk-card.nsk-dashed { border-style: dashed; box-shadow: none; }
  .nsk { background: linear-gradient(90deg, #e3e5ea 25%, #f0f1f4 50%, #e3e5ea 75%); background-size: 200% 100%; animation: nsk-shimmer 1.4s ease-in-out infinite; border-radius: 7px; flex-shrink: 0; }
  .nsk-hero { background: var(--navy-mid, #1e2028); border-radius: 18px; padding: 26px 22px; display: flex; flex-direction: column; gap: 14px; }
  .nsk-hero .nsk { background: linear-gradient(90deg, #2a2d38 25%, #353946 50%, #2a2d38 75%); background-size: 200% 100%; }
  .nsk-row { display: flex; align-items: center; gap: 12px; }
  .nsk-col { display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 0; }
  .nsk-grow { flex: 1; }
  .nsk-round { border-radius: 50%; }
  .nsk-pill { border-radius: 999px; }
  .nsk-line { height: 11px; }
  .nsk-label { height: 9px; width: 120px; }
  .nsk-title { height: 16px; }
  .nsk-sep { border-top: 1px solid var(--border, #e0e2e8); }
  .nsk-grid { display: grid; gap: 16px; }
  .nsk-g2 { grid-template-columns: 1fr 1fr; }
  .nsk-g3 { grid-template-columns: repeat(3, 1fr); }
  .nsk-g4 { grid-template-columns: repeat(4, 1fr); }
  .nsk-team { grid-template-columns: 1.55fr 1fr; }
  .nsk-experts { grid-template-columns: 1fr 300px; }
  .nsk-check { grid-template-columns: 320px 1fr 280px; }
  .nsk-up-next { grid-template-columns: 1.5fr 1fr 1fr; }
  .nsk-cal { display: grid; grid-template-columns: repeat(7, 1fr); gap: 14px 10px; }
  @media (max-width: 900px) {
    .nsk-g2, .nsk-g3, .nsk-g4, .nsk-team, .nsk-experts, .nsk-check, .nsk-up-next { grid-template-columns: 1fr; }
  }
  @media (prefers-reduced-motion: reduce) { .nsk { animation: none; } }
</style>

{{-- Dashboard: where the team is, announcements, experts and team side by side, defense results. --}}
<template data-nav-skeleton data-path="{{ $navSkeletonPath('student.dashboard') }}" data-title="Dashboard">
  <div class="nsk-page" aria-hidden="true">
    <div class="nsk-card">
      <div class="nsk-row"><div class="nsk nsk-label"></div><div class="nsk-grow"></div><div class="nsk nsk-line" style="width:140px"></div></div>
      <div class="nsk-row" style="align-items:flex-start;gap:24px">
        <div class="nsk-col" style="max-width:360px">
          <div class="nsk" style="height:34px;width:90px"></div>
          <div class="nsk nsk-pill" style="height:8px;width:100%"></div>
          <div class="nsk nsk-line" style="width:70%"></div>
        </div>
        <div class="nsk" style="height:38px;flex:1;border-radius:10px"></div>
      </div>
    </div>
    <div class="nsk-card" style="min-height:200px">
      <div class="nsk nsk-label"></div>
      <div class="nsk nsk-round" style="width:52px;height:52px;margin:24px auto 0"></div>
      <div class="nsk nsk-line" style="width:220px;margin:0 auto"></div>
    </div>
    <div class="nsk-grid nsk-g2">
      @foreach (['Capstone expert', 'Team'] as $panel)
        <div class="nsk-card">
          <div class="nsk nsk-label" style="margin-bottom:10px"></div>
          @foreach (range(1, 3) as $row)
            <div class="nsk-row" style="padding:6px 0">
              <div class="nsk nsk-round" style="width:8px;height:8px"></div>
              <div class="nsk-col"><div class="nsk nsk-line" style="width:{{ $loop->odd ? 34 : 42 }}%;height:9px"></div><div class="nsk nsk-line" style="width:{{ $loop->odd ? 48 : 38 }}%"></div></div>
            </div>
            @unless ($loop->last)<div class="nsk-sep"></div>@endunless
          @endforeach
        </div>
      @endforeach
    </div>
    <div class="nsk-card" style="min-height:140px"><div class="nsk nsk-label"></div></div>
  </div>
</template>

{{-- Team Management: tabs, the team's header, members beside its overview and panel, actions. --}}
<template data-nav-skeleton data-path="{{ $navSkeletonPath('student.team') }}" data-title="Team Management">
  <div class="nsk-page" aria-hidden="true">
    <div class="nsk-row" style="gap:8px"><div class="nsk" style="width:92px;height:38px;border-radius:10px"></div><div class="nsk" style="width:160px;height:38px;border-radius:10px"></div></div>
    <div class="nsk-card" style="padding:16px 18px">
      <div class="nsk-row"><div class="nsk" style="width:44px;height:44px;border-radius:10px"></div><div class="nsk-col"><div class="nsk nsk-title" style="width:180px"></div><div class="nsk nsk-line" style="width:220px"></div></div></div>
    </div>
    <div class="nsk-grid nsk-team">
      <div class="nsk-card" style="padding:18px 16px">
        <div class="nsk nsk-label" style="width:150px"></div>
        @foreach (range(1, 3) as $member)
          <div class="nsk-row"><div class="nsk nsk-round" style="width:36px;height:36px"></div><div class="nsk-col"><div class="nsk nsk-line" style="width:140px"></div><div class="nsk nsk-line" style="width:90px;height:9px"></div></div><div class="nsk nsk-pill" style="width:62px;height:20px"></div></div>
        @endforeach
        <div class="nsk" style="height:52px;border-radius:10px"></div>
        <div class="nsk nsk-line" style="width:160px"></div>
      </div>
      <div class="nsk-col" style="gap:16px">
        <div class="nsk-card" style="padding:18px 16px">
          <div class="nsk nsk-label"></div>
          @foreach (range(1, 3) as $row)
            <div class="nsk-row"><div class="nsk nsk-line" style="width:80px"></div><div class="nsk-grow"></div><div class="nsk nsk-line" style="width:70px"></div></div>
          @endforeach
          <div class="nsk-grid nsk-g2" style="gap:8px"><div class="nsk" style="height:22px"></div><div class="nsk" style="height:22px"></div><div class="nsk" style="height:22px"></div><div class="nsk" style="height:22px"></div></div>
        </div>
        <div class="nsk-card" style="padding:18px 16px"><div class="nsk nsk-label"></div><div class="nsk nsk-line" style="width:85%"></div></div>
      </div>
    </div>
    <div class="nsk-row" style="justify-content:flex-end;gap:8px">
      @foreach ([88, 120, 96, 90] as $width)<div class="nsk" style="width:{{ $width }}px;height:26px;border-radius:7px"></div>@endforeach
    </div>
    <div class="nsk-col" style="gap:10px;margin-top:8px"><div class="nsk" style="height:28px;width:240px"></div><div class="nsk nsk-line" style="width:360px;max-width:100%"></div></div>
  </div>
</template>

{{-- Experts: the subtitle and tabs, the three chosen experts, the booking calendar and the consultations list. --}}
<template data-nav-skeleton data-path="{{ $navSkeletonPath('student.experts') }}" data-title="Experts">
  <div class="nsk-page" aria-hidden="true">
    <div class="nsk-row"><div class="nsk nsk-line" style="width:420px;max-width:50%"></div><div class="nsk-grow"></div><div class="nsk" style="width:380px;max-width:45%;height:42px;border-radius:10px"></div></div>
    <div class="nsk nsk-line" style="width:300px;height:9px"></div>
    <div class="nsk-grid nsk-g3">
      @foreach (range(1, 3) as $expert)
        <div class="nsk-card" style="padding:16px"><div class="nsk-row"><div class="nsk nsk-round" style="width:44px;height:44px"></div><div class="nsk-col"><div class="nsk" style="height:9px;width:70px"></div><div class="nsk nsk-line" style="width:120px"></div><div class="nsk nsk-line" style="width:100px;height:9px"></div></div></div></div>
      @endforeach
    </div>
    <div class="nsk-grid nsk-experts">
      <div class="nsk-card">
        <div class="nsk-row"><div class="nsk-col"><div class="nsk nsk-title" style="width:200px"></div><div class="nsk nsk-line" style="width:300px;max-width:100%"></div></div><div class="nsk" style="width:200px;height:38px;border-radius:10px"></div></div>
        <div class="nsk" style="height:44px;border-radius:10px"></div>
        <div class="nsk-row" style="align-items:flex-start;gap:20px">
          <div class="nsk-cal nsk-grow">
            @foreach (range(1, 35) as $day)<div class="nsk nsk-round" style="width:26px;height:26px;margin:auto"></div>@endforeach
          </div>
          <div class="nsk-col" style="max-width:340px;gap:12px"><div class="nsk nsk-line" style="width:120px"></div><div class="nsk" style="height:52px;border-radius:10px"></div><div class="nsk" style="height:100px;border-radius:10px;margin-top:60px"></div></div>
        </div>
      </div>
      <div class="nsk-card" style="align-self:start">
        <div class="nsk nsk-title" style="width:150px"></div>
        <div class="nsk" style="height:38px;border-radius:10px"></div>
        <div class="nsk" style="height:70px;border-radius:10px"></div>
        <div class="nsk nsk-line" style="width:90%"></div><div class="nsk nsk-line" style="width:70%"></div>
      </div>
    </div>
  </div>
</template>

{{-- Document Submission: one card with a heading, the Submit button and the list. --}}
<template data-nav-skeleton data-path="{{ $navSkeletonPath('student.submission') }}" data-title="Document Submission">
  <div class="nsk-page" aria-hidden="true">
    <div class="nsk-card" style="max-width:980px;padding:0;gap:0">
      <div class="nsk-row" style="padding:18px 20px"><div class="nsk-col"><div class="nsk nsk-title" style="width:180px"></div><div class="nsk nsk-line" style="width:300px;max-width:100%"></div></div><div class="nsk" style="width:176px;height:38px;border-radius:9px"></div></div>
      <div class="nsk-sep"></div>
      @foreach (range(1, 3) as $row)
        <div class="nsk-row" style="padding:16px 20px"><div class="nsk" style="width:34px;height:34px;border-radius:8px"></div><div class="nsk-col"><div class="nsk nsk-line" style="width:{{ 40 + $row * 8 }}%"></div><div class="nsk nsk-line" style="width:30%;height:9px"></div></div><div class="nsk nsk-pill" style="width:80px;height:22px"></div></div>
        @unless ($loop->last)<div class="nsk-sep"></div>@endunless
      @endforeach
    </div>
  </div>
</template>

{{-- Review Viewer: the list of reviewed submissions. --}}
<template data-nav-skeleton data-path="{{ $navSkeletonPath('student.review') }}" data-title="Review Viewer">
  <div class="nsk-page" aria-hidden="true" style="padding:0 26px">
    @foreach (range(1, 3) as $row)
      <div class="nsk-card" style="padding:18px 22px"><div class="nsk-row"><div class="nsk" style="width:38px;height:38px;border-radius:9px"></div><div class="nsk-col"><div class="nsk nsk-line" style="width:{{ 30 + $row * 10 }}%"></div><div class="nsk nsk-line" style="width:25%;height:9px"></div></div><div class="nsk" style="width:110px;height:32px;border-radius:8px"></div></div></div>
    @endforeach
  </div>
</template>

{{-- Progress Tracker: the dark team banner with its five stages, what is up next, then the milestones. --}}
<template data-nav-skeleton data-path="{{ $navSkeletonPath('student.progress') }}" data-title="Our milestones">
  <div class="nsk-page" aria-hidden="true">
    <div class="nsk-hero">
      <div class="nsk-row" style="align-items:flex-start"><div class="nsk-col"><div class="nsk" style="height:9px;width:80px"></div><div class="nsk" style="height:24px;width:140px"></div><div class="nsk-row" style="gap:8px">@foreach ([64, 140, 150] as $width)<div class="nsk nsk-pill" style="height:22px;width:{{ $width }}px"></div>@endforeach</div></div><div class="nsk" style="height:38px;width:80px"></div></div>
      <div class="nsk nsk-pill" style="height:7px"></div>
      <div class="nsk-row" style="justify-content:space-around">@foreach (range(1, 5) as $stage)<div class="nsk-col" style="align-items:center;flex:0 0 auto"><div class="nsk nsk-round" style="width:30px;height:30px"></div><div class="nsk" style="height:9px;width:70px"></div></div>@endforeach</div>
    </div>
    <div class="nsk-grid nsk-up-next">
      <div class="nsk-card" style="justify-content:center"><div class="nsk-row"><div class="nsk" style="width:44px;height:44px;border-radius:10px"></div><div class="nsk-col"><div class="nsk" style="height:9px;width:60px"></div><div class="nsk nsk-title" style="width:180px"></div><div class="nsk nsk-line" style="width:260px;max-width:100%"></div></div></div></div>
      @foreach (range(1, 2) as $column)
        <div class="nsk-col" style="gap:12px">@foreach (range(1, 2) as $stat)<div class="nsk-card" style="padding:14px 16px;gap:8px"><div class="nsk" style="height:20px;width:26px"></div><div class="nsk nsk-line" style="width:60px"></div></div>@endforeach</div>
      @endforeach
    </div>
    <div class="nsk-card">
      <div class="nsk-row"><div class="nsk nsk-title" style="width:110px"></div><div class="nsk-grow"></div><div class="nsk nsk-line" style="width:260px"></div></div>
      @foreach (range(1, 3) as $milestone)
        <div class="nsk-row" style="padding:8px 0"><div class="nsk nsk-round" style="width:40px;height:40px"></div><div class="nsk-col"><div class="nsk nsk-line" style="width:180px;height:13px"></div><div class="nsk nsk-line" style="width:150px;height:9px"></div></div></div>
        <div class="nsk" style="height:64px;border-radius:12px;margin-left:52px"></div>
      @endforeach
    </div>
  </div>
</template>

{{-- Pre-Proposal Submission: the document status, then check / matches / saved pre-proposals in three columns. --}}
<template data-nav-skeleton data-path="{{ $navSkeletonPath('student.duplication') }}" data-title="Pre-Proposal Submission">
  <div class="nsk-page" aria-hidden="true">
    <div class="nsk-card" style="padding:16px 18px"><div class="nsk-row"><div class="nsk" style="width:52px;height:52px;border-radius:10px"></div><div class="nsk-col"><div class="nsk nsk-title" style="width:200px"></div><div class="nsk nsk-line" style="width:460px;max-width:100%"></div></div></div></div>
    <div class="nsk-grid nsk-check">
      <div class="nsk-col" style="gap:16px">
        <div class="nsk-card"><div class="nsk nsk-label"></div><div class="nsk" style="height:128px;border-radius:10px"></div><div class="nsk" style="height:40px;border-radius:9px"></div><div class="nsk" style="height:44px;border-radius:8px"></div></div>
        <div class="nsk-card" style="min-height:300px"><div class="nsk nsk-label"></div><div class="nsk nsk-line" style="width:60%;margin:20px auto 0"></div></div>
      </div>
      <div class="nsk-card" style="min-height:640px"><div class="nsk nsk-label" style="width:200px"></div><div class="nsk nsk-line" style="width:90px;margin:50px auto 0"></div></div>
      <div class="nsk-card" style="min-height:640px;padding:0;gap:0"><div class="nsk-col" style="padding:22px"><div class="nsk nsk-label" style="width:150px"></div><div class="nsk" style="height:9px;width:130px"></div></div><div class="nsk-sep"></div><div class="nsk-col" style="padding:22px;align-items:center"><div class="nsk nsk-line" style="width:80%"></div><div class="nsk nsk-line" style="width:50%"></div></div></div>
    </div>
  </div>
</template>

{{-- Resources: four totals, the filters, then the files. --}}
<template data-nav-skeleton data-path="{{ $navSkeletonPath('student.resources') }}" data-title="Resources">
  <div class="nsk-page" aria-hidden="true">
    <div class="nsk-grid nsk-g4">
      @foreach (range(1, 4) as $total)<div class="nsk-card" style="padding:18px"><div class="nsk" style="height:22px;width:26px"></div><div class="nsk" style="height:9px;width:90px"></div></div>@endforeach
    </div>
    <div class="nsk-row" style="gap:8px;flex-wrap:wrap"><div class="nsk" style="width:160px;height:40px;border-radius:8px"></div><div class="nsk" style="width:210px;height:40px;border-radius:8px"></div><div class="nsk" style="width:180px;height:40px;border-radius:8px"></div></div>
    @foreach (range(1, 3) as $file)
      <div class="nsk-card" style="padding:14px 18px"><div class="nsk-row"><div class="nsk" style="width:36px;height:36px;border-radius:8px"></div><div class="nsk-col"><div class="nsk nsk-line" style="width:{{ 25 + $file * 9 }}%"></div><div class="nsk nsk-line" style="width:20%;height:9px"></div></div><div class="nsk" style="width:90px;height:30px;border-radius:8px"></div></div></div>
    @endforeach
  </div>
</template>

{{-- Archived Capstones: search and filters, the count, then the studies. --}}
<template data-nav-skeleton data-path="{{ $navSkeletonPath('student.archive') }}" data-title="Archived Capstones">
  <div class="nsk-page" aria-hidden="true">
    <div class="nsk-card" style="padding:10px;flex-direction:row;gap:8px"><div class="nsk nsk-grow" style="height:42px;border-radius:9px"></div><div class="nsk" style="width:190px;height:42px;border-radius:9px"></div><div class="nsk" style="width:190px;height:42px;border-radius:9px"></div></div>
    <div class="nsk nsk-line" style="width:110px;height:9px"></div>
    <div class="nsk-grid nsk-g2">
      @foreach (range(1, 4) as $study)
        <div class="nsk-card"><div class="nsk nsk-title" style="width:85%"></div><div class="nsk nsk-line" style="width:55%"></div><div class="nsk nsk-line" style="width:95%;height:9px"></div><div class="nsk nsk-line" style="width:80%;height:9px"></div><div class="nsk-row" style="gap:6px"><div class="nsk nsk-pill" style="width:70px;height:20px"></div><div class="nsk nsk-pill" style="width:56px;height:20px"></div></div></div>
      @endforeach
    </div>
  </div>
</template>

<script nonce="{{ Vite::cspNonce() }}">
(function () {
  if (window.__navSkeleton) return;
  window.__navSkeleton = true;

  // Looked up when a link is clicked: this runs from the sidebar, which comes
  // before the page's content in the document.
  var content = null;
  var title = null;
  var shown = null;

  function trim(path) { return path.replace(/\/+$/, '') || '/'; }

  function templateFor(url) {
    var templates = document.querySelectorAll('template[data-nav-skeleton]');
    for (var i = 0; i < templates.length; i++) {
      if (trim(templates[i].dataset.path) === trim(url.pathname)) return templates[i];
    }
    return null;
  }

  // Put the page back as it was: Back to this page, or a load that never came.
  function restore() {
    if (!shown) return;
    clearTimeout(shown.timer);
    content.replaceChildren(shown.page);
    content.scrollTop = shown.scrollTop;
    content.removeAttribute('aria-busy');
    if (title) title.textContent = shown.title;
    if (shown.link) shown.link.classList.remove('active');
    shown.wasActive.forEach(function (item) { item.classList.add('active'); });
    shown = null;
  }

  function show(template, link) {
    var page = document.createDocumentFragment();
    var scrollTop = content.scrollTop;
    while (content.firstChild) page.appendChild(content.firstChild);

    var wasActive = Array.prototype.slice.call(document.querySelectorAll('.sidebar .nav-item.active'));
    shown = { page: page, scrollTop: scrollTop, title: title ? title.textContent : '', wasActive: wasActive, link: null };

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
    if (trim(url.pathname) === trim(location.pathname) && url.search === location.search) return;

    var template = templateFor(url);
    if (!template || shown) return;

    content = document.querySelector('.main > .content');
    title = document.querySelector('.main .topbar .page-title');
    if (!content) return;

    // Whatever else answers this click (a confirm, a page's own handler) goes
    // first; if it stopped the navigation, the page stays as it is.
    setTimeout(function () {
      if (!event.defaultPrevented) show(template, link);
    }, 0);
  });

  window.addEventListener('pageshow', function (event) {
    if (event.persisted) restore();
  });
})();
</script>
