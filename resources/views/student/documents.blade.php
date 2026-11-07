<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content" />
<title>Documents - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
@vite($reader ? ['resources/student-ui/styles.css', 'resources/js/student-review.jsx'] : ['resources/student-ui/styles.css'])
<x-dashboard.styles />
<style nonce="{{ Vite::cspNonce() }}">
  /* Documents: the team's papers for its experts, one row per paper - its
     newest version, where it stands, and what to do next. The list fills the
     space under the tabs and scrolls inside, so the page itself never grows.
     Earlier versions and Unsubmit sit in the row's menu. A version opens on
     this page with its remarks beside it. Signed papers are not here: they
     are handed in on Progress Tracker. */
  .dx { --gold: #c9a82c; display: flex; flex-direction: column; height: calc(100dvh - 211px); min-height: 360px; font-size: 13px; color: var(--text); }
  .dx [hidden] { display: none !important; }
  .dx-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
  .dx-head { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 16px; }
  .dx-head h2 { margin: 0; font-size: 24px; font-weight: 700; color: var(--text); }
  .dx-head p { margin: 3px 0 0; font-size: 13px; color: var(--text3); }
  .dx-sp { flex: 1; }
  .dx-btn { display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 18px; border: 0; border-radius: 9px; background: var(--gold); color: #1b1e26; font-family: inherit; font-size: 14px; font-weight: 700; line-height: 1; cursor: pointer; white-space: nowrap; text-decoration: none; }
  .dx-btn svg, .dx-act svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex: none; }
  .dx-flash { margin: 0 0 12px; padding: 10px 14px; border-radius: 10px; font-size: 13.5px; color: #15803d; background: rgba(22,163,74,.08); }
  .dx-flash.bad { color: #b45309; background: rgba(180,83,9,.08); }
  .dx-tools { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
  .dx-tabs { display: flex; gap: 10px; }
  .dx-tab { display: inline-flex; align-items: center; gap: 8px; height: 34px; padding: 0 14px; border: 1px solid var(--border); border-radius: 999px; background: #fff; color: var(--text); font-family: inherit; font-size: 12.5px; font-weight: 600; line-height: 1; cursor: pointer; }
  .dx-tab:hover { border-color: var(--border2); }
  .dx-tab[aria-pressed="true"] { border-color: var(--gold); color: #8a6d12; }
  .dx-tab b { min-width: 18px; height: 18px; padding: 0 5px; box-sizing: border-box; border-radius: 9px; background: #5f6573; color: #fff; font-size: 11px; display: inline-flex; align-items: center; justify-content: center; }
  .dx-tab[aria-pressed="true"] b { background: var(--gold); color: #1b1e26; }
  .dx-tab b.r { background: #dc2626; }
  .dx-tab b.y { background: #8a6d12; }
  .dx-tab b.g { background: #15803d; }
  .dx-search { margin-left: auto; display: flex; align-items: center; gap: 8px; width: 330px; height: 36px; padding: 0 12px; box-sizing: border-box; border: 1px solid var(--border); border-radius: 9px; background: #fff; color: var(--text3); }
  .dx-search svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; flex: none; }
  .dx-search input { flex: 1; min-width: 0; height: 34px; padding: 0; border: 0; outline: 0; background: none; -webkit-appearance: none; appearance: none; font: inherit; font-size: 13px; color: var(--text); }
  .dx-list { flex: 1; min-height: 0; display: flex; flex-direction: column; gap: 6px; overflow: auto; padding-right: 2px; }
  .dx-row { flex: none; display: grid; grid-template-columns: 44px minmax(0, 1fr) minmax(0, 1fr) 290px; align-items: center; gap: 14px; padding: 9px 14px; border: 1px solid var(--border); border-radius: 10px; background: #fff; }
  .dx-row:hover { border-color: var(--border2); }
  .dx-ic { width: 42px; height: 42px; box-sizing: border-box; border: 1px solid var(--border); border-radius: 9px; background: #fff; display: flex; align-items: center; justify-content: center; color: #dc2626; }
  .dx-ic.drawio { color: #c2410c; }
  .dx-ic.vsdx { color: #1d4ed8; }
  .dx-ic svg { width: 26px; height: 26px; }
  .dx-t { min-width: 0; }
  .dx-t b { display: block; font-size: 14.5px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .dx-t span { display: block; margin-top: 3px; font-size: 12px; color: var(--text3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .dx-t q { color: #b45309; }
  .dx-s { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--text3); white-space: nowrap; min-width: 0; }
  .dx-st { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; background: #eef0f3; color: #4b5563; }
  .dx-st.r { background: #fee2e2; color: #b42318; }
  .dx-st.y { background: #fef3c7; color: #92400e; }
  .dx-st.g { background: #dcfce7; color: #15803d; }
  .dx-acts { display: flex; align-items: center; justify-content: flex-end; gap: 8px; }
  .dx-act { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 12px; box-sizing: border-box; border: 1px solid var(--border); border-radius: 8px; background: #fff; color: var(--text); font-family: inherit; font-size: 12.5px; font-weight: 600; line-height: 1; white-space: nowrap; text-decoration: none; cursor: pointer; }
  .dx-act:hover { border-color: var(--border2); }
  .dx-act.go { background: var(--gold); border-color: var(--gold); color: #1b1e26; }
  .dx-more { width: 30px; height: 32px; flex: none; padding: 0; border: 0; border-radius: 7px; background: none; color: var(--text3); font-size: 20px; line-height: 1; cursor: pointer; }
  .dx-more:hover { background: var(--bg4); color: var(--text); }
  span.dx-more { visibility: hidden; }
  /* The row's menu opens above the page, so the list's scroll box cannot cut it off. */
  .dx-menu { position: fixed; inset: auto; margin: 0; min-width: 240px; max-width: min(360px, calc(100vw - 20px)); padding: 6px; border: 1px solid var(--border); border-radius: 10px; background: #fff; box-shadow: 0 10px 28px rgba(15,18,26,.16); font-size: 13px; color: var(--text); }
  .dx-menu small { display: block; padding: 6px 10px 4px; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--text3); }
  .dx-menu a, .dx-menu button { display: block; width: 100%; box-sizing: border-box; padding: 8px 10px; border: 0; border-radius: 7px; background: none; color: var(--text); font: inherit; text-align: left; text-decoration: none; cursor: pointer; }
  .dx-menu a:hover, .dx-menu button:hover { background: var(--bg4); }
  .dx-menu a span { display: block; margin-top: 2px; font-size: 12px; color: var(--text3); }
  .dx-menu button { color: #b45309; font-weight: 600; }
  .dx-menu form { margin: 0; }
  .dx-none { flex: none; margin: 0; padding: 30px 16px; border: 1px dashed var(--border2); border-radius: 10px; text-align: center; color: var(--text3); }
  .dx-empty { flex: 1; min-height: 0; box-sizing: border-box; position: relative; overflow: hidden; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 34px 16px 30px; border: 1px solid var(--border); border-radius: 14px; background: #fff; text-align: center; }
  .dx-empty > svg.sw { position: absolute; pointer-events: none; }
  .dx-empty > *:not(.sw) { position: relative; }
  .dx-empty h3 { margin: 12px 0 4px; font-size: 20px; font-weight: 700; }
  .dx-empty p { margin: 0 0 18px; font-size: 13.5px; color: var(--text3); }
  .dx-tab:focus-visible, .dx-more:focus-visible, .dx-act:focus-visible, .dx-btn:focus-visible, .dx-search:focus-within, .dx-menu a:focus-visible, .dx-menu button:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
  @media (max-width: 1280px) { .dx-search { width: 240px; } }
  @media (max-width: 760px) {
    .dx { height: calc(100dvh - 257px); }
    .dx-head { flex-direction: column; gap: 10px; }
    .dx-head .dx-btn { width: 100%; justify-content: center; }
    .dx-tools { flex-wrap: wrap; }
    .dx-tabs { width: 100%; gap: 8px; overflow-x: auto; scrollbar-width: none; }
    .dx-tab { flex: none; }
    .dx-search { width: 100%; margin: 0; }
    .dx-row { grid-template-columns: 42px minmax(0, 1fr); gap: 8px 12px; }
    .dx-s { grid-column: 1 / -1; flex-wrap: wrap; }
    .dx-acts { grid-column: 1 / -1; }
    .dx-acts .dx-act { flex: 1; justify-content: center; }
  }
  .dc-chip { display: inline-flex; align-items: center; gap: 5px; height: 22px; padding: 0 8px; border-radius: 999px; font-size: 11.5px; font-weight: 500; background: var(--bg4); color: var(--text2); white-space: nowrap; text-decoration: none; }
  .dc-chip.ok { background: rgba(22,163,74,.09); color: #15803d; }
  .dc-chip.revise { background: rgba(180,83,9,.09); color: #b45309; }
  .dc-chip.now { background: rgba(29,78,216,.08); color: #1d4ed8; }

  /* One version open: what it is and who reviewed it, then the file and its remarks. */
  .content:has(.rv-shell) { padding: 0; overflow: hidden; }
  .rv-shell { display: grid; grid-template-rows: auto auto minmax(0, 1fr); height: 100%; }
  .rv-bar { display: flex; align-items: center; gap: 8px 12px; padding: 10px 16px; background: var(--bg2); border-bottom: 1px solid var(--border); flex-wrap: wrap; }
  .dc-back { width: 36px; height: 36px; flex-shrink: 0; border: 1px solid var(--border); border-radius: 8px; display: grid; place-items: center; color: var(--text2); text-decoration: none; }
  .dc-back:hover { color: var(--text); border-color: var(--border2); }
  .dc-back svg, .rv-btn svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
  .dc-rid { flex: 1 1 260px; min-width: 0; }
  .dc-rid b { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 17px; color: var(--text); }
  .dc-rid b .dc-chip { height: 24px; padding: 0 10px; font-size: 12.5px; font-weight: 600; }
  .dc-rid small { display: block; margin-top: 2px; font-size: 13px; color: var(--text2); overflow-wrap: anywhere; }
  .dc-vers { display: inline-flex; gap: 2px; background: var(--bg4); border-radius: 8px; padding: 3px; }
  .dc-vers a { min-width: 34px; height: 28px; padding: 0 8px; border-radius: 6px; display: grid; place-items: center; font: 600 12px/1 ui-monospace, Menlo, Consolas, monospace; color: var(--text2); text-decoration: none; }
  .dc-vers a[aria-current="true"] { background: var(--bg2); color: var(--text); box-shadow: 0 1px 2px rgba(15,18,26,.12); }
  .rv-btn { display: inline-flex; align-items: center; gap: 6px; border: 1px solid transparent; border-radius: 6px; padding: 6px 12px; font-size: 13px; font-weight: 600; text-decoration: none; min-height: 34px; color: var(--text2); background: none; cursor: pointer; font-family: inherit; }
  .rv-btn.ghost:hover { background: var(--accent-light); color: var(--text); }
  .rv-btn.gold { background: var(--accent); border-color: var(--accent); color: #18191f; font-weight: 700; }
  .rv-btn.gold:hover { background: var(--accent2); }
  .rv-btn.danger { color: #b45309; }
  .rv-btn:focus-visible, .dc-back:focus-visible, .dc-vers a:focus-visible, .dc-revs a:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
  .dc-revs { display: flex; align-items: center; gap: 8px 10px; flex-wrap: wrap; padding: 10px 16px; background: var(--bg2); border-bottom: 1px solid var(--border); }
  .dc-revs > span:first-child { margin-right: 2px; font-size: 11.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--text2); }
  .dc-revs .dc-chip { gap: 6px; height: 36px; padding: 0 14px; font-size: 13px; border: 1px solid var(--border); background: var(--bg2); color: var(--text2); }
  .dc-revs .dc-chip b { color: var(--text); }
  .dc-revs .dc-chip svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
  .dc-revs .dc-chip.ok svg { color: #15803d; stroke-width: 2.6; } .dc-revs .dc-chip.now svg { color: #1d4ed8; }
  .dc-revs .dc-chip.revise, .dc-revs .dc-chip.revise b { color: #b45309; }
  .dc-revs a.dc-chip:hover { border-color: var(--border2); }
  .dc-revs .dc-chip[aria-current="true"] { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(201,168,44,.18); }
  .dc-revs .dc-chip b { font-weight: 600; }
  .rv-work { min-height: 0; }
  .dc-undo { margin: 0; display: inline-flex; }
</style>
  @include('partials.render-whole')
</head>
<body>
@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Documents</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>

  <div class="content">
@if ($reader)
@php
    $doc = $reader->document;
    $rev = $reader->review;
    $group = $reader->group;
    $version = $reader->version;
@endphp
<div class="rv-shell">
  <div class="rv-bar">
    <a class="dc-back" href="{{ route('student.documents') }}" aria-label="Back to documents">
      <svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
    </a>
    <div class="dc-rid">
      <b>{{ $group->title }} <span class="dc-chip {{ $reader->status[1] }}">{{ $reader->status[0] }}</span></b>
      <small>{{ $doc->file_name }} · {{ $doc->roundLabel() }} · sent by {{ $version->uploader ? trim($version->uploader->f_name.' '.$version->uploader->l_name) : 'a teammate' }} · {{ $doc->created_at?->format('M j, Y g:i A') }}</small>
    </div>
    @if ($group->versions->count() > 1)
      <nav class="dc-vers" aria-label="Versions">
        @foreach ($group->versions->reverse() as $v)
          <a href="{{ route('student.documents', ['file' => $v->document->file_id]) }}" @if ($v->document->is($doc)) aria-current="true" @endif title="{{ $v->document->roundLabel() }}">v{{ (int) $v->document->file_version }}</a>
        @endforeach
      </nav>
    @endif
    @if ($rev)
      <a href="{{ route('student.review.clf', $rev->review_id) }}" class="rv-btn ghost">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>Download CLF
      </a>
      @if ($rev->drive_web_view_link)
        <a href="{{ $rev->drive_web_view_link }}" target="_blank" rel="noopener" class="rv-btn ghost">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>Download Annotated PDF
        </a>
      @endif
    @endif
    @if ($version->canUnsubmit)
      <form class="dc-undo" method="POST" action="{{ route('student.submission.unsubmit', $doc->file_id) }}" data-name="{{ $doc->file_name }}">
        @csrf
        @method('DELETE')
        <button type="submit" class="rv-btn danger">Unsubmit</button>
      </form>
    @endif
    {{-- Whichever reviewer is open: any one asking for changes means a revision is due. --}}
    @if ($version->isLatest && $group->revise)
      <button type="button" class="rv-btn gold" onclick="window.openSubmission && window.openSubmission('{{ $group->revise['itemId'] }}', this, {{ (int) $group->revise['fileId'] }})">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>Submit {{ $group->revise['next'] }}
      </button>
    @endif
  </div>

  <div class="dc-revs" aria-label="Reviewers">
    @php
        $chipIcons = [
            'ok' => '<polyline points="20 6 9 17 4 12"/>',
            'revise' => '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>',
            'now' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
            '' => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>',
        ];
    @endphp
    <span>Remarks from</span>
    @forelse ($version->reviews as $r)
      @if ($r['done'])
        <a class="dc-chip {{ $r['tone'] }}" href="{{ route('student.documents', ['file' => $doc->file_id, 'review' => $r['id']]) }}" @if ($rev?->review_id === $r['id']) aria-current="true" @endif title="Read {{ $r['name'] ?? $r['role'] }}’s remarks"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $chipIcons[$r['tone']] ?? $chipIcons[''] !!}</svg><b>{{ $r['role'] }}</b> · {{ $r['text'] }}</a>
      @else
        <span class="dc-chip {{ $r['tone'] }}" title="{{ $r['name'] }}"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $chipIcons[$r['tone']] ?? $chipIcons[''] !!}</svg><b>{{ $r['role'] }}</b> · {{ $r['tone'] === 'now' ? 'still reviewing' : 'not opened yet' }}</span>
      @endif
    @empty
      <span class="dc-chip">Sent to nobody</span>
    @endforelse
  </div>

  <div
    id="student-pdf-viewer-root"
    class="rv-work"
    data-pdf-url="{{ $reader->pdfUrl }}"
    data-remarks-url="{{ $reader->remarksUrl }}"
    data-diagrams-url="{{ $reader->diagramsUrl }}"
    data-reviewer="{{ $reader->reviewer }}"
    data-general="{{ json_encode($reader->general, JSON_UNESCAPED_UNICODE) }}"
    data-decision="{{ json_encode($reader->decision, JSON_UNESCAPED_UNICODE) }}"
    data-others="[]"
  ></div>
</div>
@else
    @php
        $whoSent = fn ($v) => ($v->uploader ? trim($v->uploader->f_name.' '.$v->uploader->l_name) : 'A teammate').($v->isMine ? ' (you)' : '');
        $reviewers = fn ($v) => $v->reviews->isEmpty() ? 'Sent to nobody' : $v->reviews->map(fn ($r) => $r['role'].' · '.$r['text'])->implode(', ');
    @endphp
    <section class="dx" aria-labelledby="dl-h">
      <div class="dx-head">
        <div>
          <h2 id="dl-h">Your team's documents</h2>
          <p>Upload, manage, and track your capstone documents.</p>
        </div>
        <span class="dx-sp"></span>
        @if ($hasTeam)
        <button type="button" class="dx-btn" onclick="window.openSubmission && window.openSubmission('none', this)"
                @if ($selectedItemId) data-open-upload="{{ $selectedItemId }}" @endif><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>Submit a paper</button>
        @endif
      </div>
      @if (session('status') || session('error'))
        <p class="dx-flash {{ session('error') ? 'bad' : '' }}" role="status">{{ session('error') ?? session('status') }}</p>
      @endif
      <div class="dx-tools">
        <div class="dx-tabs" role="group" aria-label="Show">
          @foreach (['all' => ['All', ''], 'revise' => ['Needs Revision', 'r'], 'review' => ['In Review', 'y'], 'ok' => ['Approved', 'g']] as $key => [$label, $tone])
            <button type="button" class="dx-tab" data-filter="{{ $key }}" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}">{{ $label }} <b class="{{ $tone }}">{{ $counts[$key] }}</b></button>
          @endforeach
        </div>
        <label class="dx-search">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.6" y2="16.6"/></svg>
          <span class="dx-sr">Search documents</span>
          <input type="search" id="dl-search" placeholder="Search a document..." autocomplete="off">
        </label>
      </div>
      @if (! $hasTeam || $groups->isEmpty())
        <div class="dx-empty">
          <svg class="sw" viewBox="0 0 600 260" preserveAspectRatio="none" style="left:0;top:0;width:30%;height:80%" aria-hidden="true"><path d="M0 0H330C220 40 120 110 0 230Z" fill="#c9a82c" opacity=".55"/><path d="M0 0H360C250 50 140 130 0 260V230C120 110 220 40 330 0Z" fill="#c9a82c" opacity=".18"/></svg>
          <svg class="sw" viewBox="0 0 600 260" preserveAspectRatio="none" style="right:0;bottom:0;width:26%;height:50%" aria-hidden="true"><path d="M600 260H260C380 230 520 160 600 40Z" fill="#c9a82c" opacity=".55"/></svg>
          <svg viewBox="0 0 140 100" width="150" height="108" aria-hidden="true">
            <g stroke="#c9a82c" stroke-width="4" stroke-linecap="round"><line x1="16" y1="54" x2="26" y2="57"/><line x1="18" y1="70" x2="27" y2="66"/><line x1="112" y1="14" x2="120" y2="6"/><line x1="116" y1="26" x2="128" y2="22"/></g>
            <rect x="34" y="16" width="48" height="62" rx="6" fill="#4b4e5a" transform="rotate(-8 58 47)"/>
            <rect x="44" y="10" width="50" height="64" rx="6" fill="#f3f4f6" stroke="#c9ccd3" stroke-width="2" transform="rotate(6 69 42)"/>
            <g stroke="#9aa0ac" stroke-width="3.5" stroke-linecap="round" transform="rotate(6 69 42)"><line x1="53" y1="24" x2="84" y2="24"/><line x1="53" y1="34" x2="84" y2="34"/><line x1="53" y1="44" x2="78" y2="44"/><line x1="53" y1="54" x2="72" y2="54"/></g>
            <circle cx="96" cy="72" r="17" fill="#c9a82c"/><g stroke="#1b1e26" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" fill="none"><line x1="96" y1="81" x2="96" y2="63"/><polyline points="89 70 96 63 103 70"/></g>
          </svg>
          @if (! $hasTeam)
            <h3>No team yet</h3>
            <p>Documents opens once you are in a team.</p>
            <a class="dx-btn" href="{{ route('student.team') }}">Go to Team Management</a>
          @else
            <h3>No documents yet</h3>
            <p>Start by submitting your first document for review.</p>
            <button type="button" class="dx-btn" onclick="window.openSubmission && window.openSubmission('none', this)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>Submit a paper</button>
          @endif
        </div>
      @else
        <div class="dx-list" data-dl-list>
          @foreach ($groups as $group)
            @php
                $ext = \App\Http\Controllers\Student\DocumentsController::fileKind($group->latest);
                $latest = $group->versions->first();
                $d = $latest->document;
                $older = $group->versions->slice(1);
                $search = Str::lower($group->title.' '.$group->versions->map(fn ($v) => $v->document->file_name)->implode(' '));
                [$badge, $tone] = match (true) {
                    $group->filter === 'revise' => ['Needs Revision', 'r'],
                    $group->filter === 'ok' => ['Approved', 'g'],
                    $group->status[1] === 'now' => ['In Review', 'y'],
                    default => [$group->status[0], ''],
                };
                $on = fn ($at) => ($at ?? $d->created_at)?->format('M j, Y');
                $when = match ($group->filter) {
                    'revise' => 'Requested on '.$on($d->reviews->first()?->reviewed_at),
                    'ok' => 'Approved on '.$on($d->reviews->first()?->reviewed_at),
                    default => 'Submitted on '.$on($d->created_at),
                };
            @endphp
            <article class="dx-row" data-filter="{{ $group->filter }}" data-search="{{ $search }}">
              <span class="dx-ic {{ $ext }}" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M8 3h11l7 7v18a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" fill="#fff" stroke="currentColor" stroke-width="1.6"/><path d="M19 3v7h7" fill="none" stroke="currentColor" stroke-width="1.6"/><rect x="3" y="15" width="20" height="9" rx="2" fill="currentColor"/><text x="13" y="22" text-anchor="middle" font-size="6.5" font-weight="700" fill="#fff" font-family="Arial"@if (strlen($ext) > 3) textLength="17" lengthAdjust="spacingAndGlyphs"@endif>{{ strtoupper($ext) }}</text></svg></span>
              <div class="dx-t">
                <b>{{ $group->title }}</b>
                @if ($group->remark && $group->remark['first'])
                  <span title="{{ $group->remark['first'] }}"><q>{{ $group->remark['first'] }}</q> · {{ $group->remark['role'] }} · {{ $group->remark['count'] }} {{ Str::plural('remark', $group->remark['count']) }}</span>
                @else
                  <span title="{{ $d->file_name }} · {{ $reviewers($latest) }}">{{ $d->file_name }} · {{ $whoSent($latest) }} · {{ $reviewers($latest) }}</span>
                @endif
              </div>
              <div class="dx-s"><span class="dx-st {{ $tone }}">{{ $badge }}</span>• {{ $when }}@if ($group->due && $group->revise) · Due {{ $group->due }}@endif</div>
              <div class="dx-acts">
                @if ($group->revise)
                  <button type="button" class="dx-act go" onclick="window.openSubmission && window.openSubmission('{{ $group->revise['itemId'] }}', this, {{ (int) $group->revise['fileId'] }})"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>Submit {{ $group->revise['next'] }}</button>
                @endif
                <a class="dx-act" href="{{ route('student.documents', ['file' => $d->file_id]) }}" aria-label="View {{ $d->file_name }}, version {{ (int) $d->file_version }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>View</a>
                @if ($older->isNotEmpty() || $latest->canUnsubmit)
                  <button type="button" class="dx-more" popovertarget="dx-m-{{ $d->file_id }}" aria-label="More for {{ $group->title }}">⋮</button>
                  <div class="dx-menu" id="dx-m-{{ $d->file_id }}" popover>
                    @if ($older->isNotEmpty())
                      <small>Earlier versions</small>
                      @foreach ($older as $v)
                        @php $o = $v->document; @endphp
                        <a href="{{ route('student.documents', ['file' => $o->file_id]) }}">v{{ (int) $o->file_version }} · {{ $o->file_name }}<span>{{ $whoSent($v) }} · {{ $o->created_at?->format('M j, Y') }} · {{ $reviewers($v) }}</span></a>
                      @endforeach
                    @endif
                    @if ($latest->canUnsubmit)
                      <form class="dc-undo" method="POST" action="{{ route('student.submission.unsubmit', $d->file_id) }}" data-name="{{ $d->file_name }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit">Unsubmit v{{ (int) $d->file_version }}</button>
                      </form>
                    @endif
                  </div>
                @else
                  <span class="dx-more" aria-hidden="true"></span>
                @endif
              </div>
            </article>
          @endforeach
          <p class="dx-none" id="dl-none" role="status" hidden>No documents match.</p>
        </div>
      @endif
    </section>
@endif
  </div>
</div>

@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
@include('partials.app-dialog')
<script nonce="{{ Vite::cspNonce() }}">
document.querySelectorAll('form.dc-undo').forEach(form => form.addEventListener('submit', async event => {
  event.preventDefault();
  const agreed = await confirmSheet({
    title: 'Unsubmit this file?',
    names: [form.dataset.name],
    body: 'It goes back to not handed in and reviewers will no longer see it. Upload again when ready.',
    confirmLabel: 'Unsubmit',
    danger: true,
  });
  if (agreed) form.submit();
}));
// Which papers show: a tab, and what is typed in the search.
(() => {
  const list = document.querySelector('[data-dl-list]');
  if (!list) return;
  const rows = [...list.querySelectorAll('.dx-row')];
  const tabs = [...document.querySelectorAll('.dx-tab')];
  const search = document.getElementById('dl-search');
  const none = document.getElementById('dl-none');
  let filter = 'all';
  const apply = () => {
    const words = search.value.trim().toLowerCase();
    let shown = 0;
    rows.forEach(row => {
      const fits = (filter === 'all' || row.dataset.filter === filter) && (!words || row.dataset.search.includes(words));
      row.hidden = !fits;
      shown += fits ? 1 : 0;
    });
    none.hidden = shown > 0;
  };
  tabs.forEach(tab => tab.addEventListener('click', () => {
    filter = tab.dataset.filter;
    tabs.forEach(other => other.setAttribute('aria-pressed', String(other === tab)));
    apply();
  }));
  search.addEventListener('input', apply);
  // A row's menu opens under its button, or above it near the bottom of the screen.
  list.querySelectorAll('.dx-menu').forEach(menu => menu.addEventListener('toggle', event => {
    if (event.newState !== 'open') return;
    const at = list.querySelector('[popovertarget="' + menu.id + '"]').getBoundingClientRect();
    menu.style.left = Math.max(10, at.right - menu.offsetWidth) + 'px';
    menu.style.top = (at.bottom + 4 + menu.offsetHeight > innerHeight ? at.top - 4 - menu.offsetHeight : at.bottom + 4) + 'px';
  }));
  list.addEventListener('scroll', () => list.querySelectorAll('.dx-menu:popover-open').forEach(menu => menu.hidePopover()), { passive: true });
})();
function toggleSidebar() { document.body.classList.toggle('sidebar-open'); }
function closeSidebar() { document.body.classList.remove('sidebar-open'); }
</script>
</body>
</html>
