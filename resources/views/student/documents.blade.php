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
  /* Documents: the team's papers for its experts, one card per paper - the
     newest version, where each reviewer is, and what to do next - with the
     earlier versions folded under it. A paper sent back comes first and says
     what was asked. A version opens on this page with its remarks beside it.
     Signed papers are not here: they are handed in on Progress Tracker. */
  .dl { max-width: 1100px; container: dl / inline-size; }
  .dl [hidden] { display: none !important; }
  .dl-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
  .dl-head { display: flex; align-items: center; justify-content: space-between; gap: 12px 16px; flex-wrap: wrap; margin-bottom: 20px; }
  .dl-head h2 { margin: 0; font-size: 22px; font-weight: 700; letter-spacing: -.01em; color: var(--text); }
  .dl-head p { margin: 4px 0 0; font-size: 14px; color: var(--text2); }
  .dl-head .up-btn { height: 44px; padding: 0 20px; font-size: 14.5px; }
  .dl-flash { margin: 0 0 16px; padding: 10px 14px; border-radius: 10px; font-size: 13.5px; color: #15803d; background: rgba(22,163,74,.08); }
  .dl-flash.bad { color: #b45309; background: rgba(180,83,9,.08); }
  .dl-tools { position: sticky; top: 0; z-index: 3; display: flex; align-items: center; justify-content: space-between; gap: 10px 12px; flex-wrap: wrap; margin-top: -12px; padding: 12px 0; background: var(--bg); border-bottom: 1px solid var(--border); }
  /* Stuck under the top bar, it also covers the page's top padding, or the cards would show through above it. */
  .dl-tools.stuck { box-shadow: 0 calc(-1 * var(--dl-pad, 0px)) 0 var(--bg); }
  .dl-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
  .dl-tab { display: inline-flex; align-items: center; gap: 8px; min-height: 40px; padding: 0 14px; border-radius: 999px; border: 1px solid var(--border); background: var(--bg2); color: var(--text2); font: 500 14px/1 inherit; font-family: inherit; cursor: pointer; }
  .dl-tab:hover { border-color: var(--border2); color: var(--text); }
  .dl-tab b { min-width: 22px; height: 22px; padding: 0 6px; box-sizing: border-box; border-radius: 999px; display: inline-grid; place-items: center; background: var(--bg4); color: var(--text2); font-size: 12px; }
  .dl-tab[aria-pressed="true"] { background: var(--text); border-color: var(--text); color: #fff; }
  .dl-tab[aria-pressed="true"] b { background: rgba(255,255,255,.18); color: #fff; }
  .dl-search { flex: 0 1 260px; display: flex; align-items: center; gap: 8px; min-height: 40px; padding: 0 12px; box-sizing: border-box; border: 1px solid var(--border); border-radius: 9px; background: var(--bg2); color: var(--text3); }
  .dl-search input { flex: 1; min-width: 0; height: 38px; padding: 0; border: 0; outline: 0; background: none; -webkit-appearance: none; appearance: none; font: inherit; font-size: 14px; color: var(--text); }
  .dl-list { display: flex; flex-direction: column; gap: 12px; padding-top: 16px; }
  .dl-doc { background: var(--bg2); border: 1px solid var(--border); border-radius: 14px; }
  .dl-doc:hover { border-color: var(--border2); }
  .dl-doc.attn { border-color: #ecd9b8; }
  .dl-row { display: grid; grid-template-columns: 42px minmax(0, 1fr) auto; column-gap: 16px; align-items: start; padding: 18px 22px; }
  .dl-body { min-width: 0; display: flex; flex-direction: column; gap: 10px; }
  .dl-title { display: flex; align-items: center; gap: 6px 10px; flex-wrap: wrap; }
  .dl-title h3 { margin: 0; font-size: 16px; font-weight: 600; color: var(--text); }
  .dl-meta { margin: 4px 0 0; font-size: 13px; line-height: 1.5; color: var(--text2); overflow-wrap: anywhere; }
  .dl-file { font: 12.5px ui-monospace, Menlo, Consolas, monospace; color: var(--text); }
  .dl-pill { display: inline-flex; align-items: center; height: 24px; padding: 0 10px; border-radius: 999px; font-size: 12.5px; font-weight: 600; white-space: nowrap; background: var(--bg4); color: var(--text2); }
  .dl-pill.ok { background: rgba(22,163,74,.09); color: #15803d; }
  .dl-pill.revise { background: rgba(180,83,9,.09); color: #b45309; }
  .dl-pill.now { background: rgba(29,78,216,.08); color: #1d4ed8; }
  .dl-st { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: 6px 18px; }
  .dl-st li { display: inline-flex; align-items: center; gap: 6px; font-size: 13.5px; color: var(--text2); white-space: nowrap; }
  .dl-st li b { font-weight: 600; color: var(--text); }
  .dl-st svg, .dl-more svg, .dl-search svg { width: 15px; height: 15px; flex-shrink: 0; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
  .dl-st .ok svg { color: #15803d; stroke-width: 2.6; } .dl-st .now svg { color: #1d4ed8; } .dl-st .wait svg { color: var(--text3); }
  .dl-st .revise, .dl-st .revise b { color: #b45309; }
  .dl-remark { padding: 12px 14px; border-radius: 10px; background: #fdf8f1; border: 1px solid #f0dcc4; }
  .dl-remark q { display: block; font-size: 14.5px; line-height: 1.55; color: var(--text); quotes: "\201C" "\201D"; }
  .dl-remark p { margin: 6px 0 0; font-size: 13px; color: var(--text2); }
  .dl-remark p b { font-weight: 600; color: #b45309; }
  .dl-remark a { color: var(--accent3); font-weight: 600; }
  .dl-more { align-self: flex-start; display: inline-flex; align-items: center; gap: 4px; min-height: 32px; margin: -4px 0 -6px -8px; padding: 0 8px; border: 0; border-radius: 7px; background: none; color: var(--text2); font: inherit; font-size: 13px; font-weight: 500; cursor: pointer; }
  .dl-more:hover { background: var(--bg4); color: var(--text); }
  .dl-more svg { transition: transform .15s; }
  .dl-more[aria-expanded="true"] svg { transform: rotate(180deg); }
  .dl-acts { display: flex; align-items: center; justify-content: flex-end; gap: 8px; flex-wrap: wrap; align-self: center; }
  .dl-acts .up-btn, .dl-hrow .up-btn { height: 40px; text-decoration: none; }
  .dl-acts .up-btn.danger, .dl-hrow .up-btn.danger { color: #b45309; }
  .dl-hist { border-top: 1px solid var(--bg4); padding: 4px 22px 8px 80px; }
  .dl-hrow { display: grid; grid-template-columns: 44px minmax(0, 1fr) auto; column-gap: 14px; row-gap: 6px; align-items: center; padding: 12px 0; }
  .dl-hrow + .dl-hrow { border-top: 1px solid var(--bg4); }
  .dl-ver { padding: 6px 0; border-radius: 6px; background: var(--bg4); text-align: center; font: 600 12.5px/1 ui-monospace, Menlo, Consolas, monospace; color: var(--text2); }
  .dl-hrow .dl-meta { margin: 0 0 6px; }
  .dl-none, .dl-empty { margin: 16px 0 0; padding: 36px 20px; border: 1px dashed var(--border2); border-radius: 14px; text-align: center; font-size: 14px; color: var(--text2); }
  .dl-tab:focus-visible, .dl-more:focus-visible, .dl-search:focus-within, .dl-remark a:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
  .dc-ic { width: 42px; height: 50px; flex-shrink: 0; box-sizing: border-box; border-radius: 7px; border: 1px solid var(--border2); background: var(--bg2); display: grid; place-items: end center; padding-bottom: 7px; font: 700 9px/1 ui-monospace, Menlo, Consolas, monospace; color: #c42424; }
  .dc-ic.drawio { color: #c2410c; font-size: 7.5px; }
  .dc-ic.vsdx { color: #1d4ed8; font-size: 8px; }
  @container dl (max-width: 720px) {
    .dl-row { grid-template-columns: 42px minmax(0, 1fr); padding: 16px; }
    .dl-acts { grid-column: 2; justify-content: flex-start; margin-top: 14px; }
    .dl-hist { padding: 4px 16px 8px; }
    .dl-hrow { grid-template-columns: 44px minmax(0, 1fr); }
    .dl-hrow .dl-acts { grid-column: 2; margin-top: 0; }
    .dl-search { flex: 1 1 100%; }
    /* On a phone the tabs stay one row that scrolls, and the bar scrolls away with the page. */
    .dl-tools { position: static; margin-top: 0; padding-top: 0; }
    .dl-tabs { flex-wrap: nowrap; overflow-x: auto; margin: 0 -12px; padding: 2px 12px; scrollbar-width: none; }
    .dl-tab { flex-shrink: 0; }
  }
  @container dl (max-width: 420px) {
    .dl-row { grid-template-columns: minmax(0, 1fr); }
    .dl-row > .dc-ic { display: none; }
    .dl-acts { grid-column: 1; }
    .dl-acts .up-btn { flex: 1; }
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
        $icons = [
            'ok' => '<polyline points="20 6 9 17 4 12"/>',
            'revise' => '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>',
            'now' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
            'wait' => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>',
        ];
        $whoSent = fn ($v) => ($v->uploader ? trim($v->uploader->f_name.' '.$v->uploader->l_name) : 'A teammate').($v->isMine ? ' (you)' : '');
    @endphp
    <section class="dl" aria-labelledby="dl-h">
      <div class="dl-head">
        <div>
          <h2 id="dl-h">Your team's documents</h2>
          <p>{{ $counts['all'] }} {{ Str::plural('paper', $counts['all']) }}@if ($counts['revise']) · {{ $counts['revise'] }} {{ $counts['revise'] === 1 ? 'needs' : 'need' }} your revision @else · newest first @endif</p>
        </div>
        @if ($hasTeam)
        <button type="button" class="up-btn primary" onclick="window.openSubmission && window.openSubmission('none', this)"
                @if ($selectedItemId) data-open-upload="{{ $selectedItemId }}" @endif>
          <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          Submit a paper
        </button>
        @endif
      </div>
      @if (session('status') || session('error'))
        <p class="dl-flash {{ session('error') ? 'bad' : '' }}" role="status">{{ session('error') ?? session('status') }}</p>
      @endif
      @if (! $hasTeam)
        <p class="dl-empty">Documents opens once you are in a team. Chapters are uploaded by a team, so there is nowhere to put them yet. <a href="{{ route('student.team') }}">Go to Team Management →</a></p>
      @elseif ($groups->isEmpty())
        <p class="dl-empty">Nothing sent yet. The papers your team sends show up here, with your reviewers' remarks.</p>
      @else
        <div class="dl-tools">
          <div class="dl-tabs" role="group" aria-label="Show">
            @foreach (['all' => 'All', 'revise' => 'Needs revision', 'review' => 'In review', 'ok' => 'Approved'] as $key => $label)
              <button type="button" class="dl-tab" data-filter="{{ $key }}" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}">{{ $label }} <b>{{ $counts[$key] }}</b></button>
            @endforeach
          </div>
          <label class="dl-search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <span class="dl-sr">Search papers</span>
            <input type="search" id="dl-search" placeholder="Search papers" autocomplete="off">
          </label>
        </div>
        <div class="dl-list" data-dl-list>
          @foreach ($groups as $group)
            @php
                $ext = \App\Http\Controllers\Student\DocumentsController::fileKind($group->latest);
                $latest = $group->versions->first();
                $d = $latest->document;
                $diagramCount = $d->isDiagram() ? 0 : count($d->diagram_files ?? []);
                $older = $group->versions->slice(1);
                $search = Str::lower($group->title.' '.$group->versions->map(fn ($v) => $v->document->file_name)->implode(' '));
            @endphp
            <article class="dl-doc{{ $group->revise ? ' attn' : '' }}" data-filter="{{ $group->filter }}" data-search="{{ $search }}">
              <div class="dl-row">
                <span class="dc-ic {{ $ext }}" aria-hidden="true">{{ strtoupper($ext) }}</span>
                <div class="dl-body">
                  <div>
                    <div class="dl-title"><h3>{{ $group->title }}</h3><span class="dl-pill {{ $group->status[1] }}">{{ $group->status[0] }}</span></div>
                    <p class="dl-meta"><span class="dl-file">{{ $d->file_name }}</span> · v{{ (int) $d->file_version }} · {{ $d->roundLabel() }} · {{ $whoSent($latest) }} · {{ $d->created_at?->format('M j, Y g:i A') }}@if ($diagramCount) · + {{ $diagramCount }} {{ Str::plural('diagram file', $diagramCount) }}@endif</p>
                  </div>
                  <ul class="dl-st" aria-label="Reviewers">
                    @forelse ($latest->reviews as $r)
                      <li class="{{ $r['tone'] ?: 'wait' }}" title="{{ $r['name'] }}"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons[$r['tone'] ?: 'wait'] !!}</svg><span><b>{{ $r['role'] }}</b> · {{ $r['text'] }}</span></li>
                    @empty
                      <li class="wait"><span>Sent to nobody</span></li>
                    @endforelse
                  </ul>
                  @if ($group->remark)
                    <div class="dl-remark">
                      @if ($group->remark['first'])<q>{{ $group->remark['first'] }}</q>@endif
                      <p>{{ $group->remark['reviewer'] ? $group->remark['reviewer'].', ' : '' }}{{ $group->remark['role'] }}{{ $group->remark['date'] ? ' · '.$group->remark['date'] : '' }}@if ($group->due) · <b>Due {{ $group->due }}</b>@endif · <a href="{{ $group->remark['url'] }}">Read {{ $group->remark['count'] > 1 ? 'all '.$group->remark['count'].' remarks' : 'the remark' }}</a></p>
                    </div>
                  @endif
                  @if ($older->isNotEmpty())
                    @php $earlier = $older->count().' earlier '.Str::plural('version', $older->count()); @endphp
                    <button type="button" class="dl-more" data-dl-more data-label="{{ $earlier }}" aria-expanded="false" aria-controls="dl-h-{{ $d->file_id }}"><span>Show {{ $earlier }}</span><svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg></button>
                  @endif
                </div>
                <div class="dl-acts">
                  @if ($group->revise)
                    <button type="button" class="up-btn primary" onclick="window.openSubmission && window.openSubmission('{{ $group->revise['itemId'] }}', this, {{ (int) $group->revise['fileId'] }})">Submit {{ $group->revise['next'] }}</button>
                  @endif
                  <a class="up-btn" href="{{ route('student.documents', ['file' => $d->file_id]) }}" aria-label="View {{ $d->file_name }}, version {{ (int) $d->file_version }}">View</a>
                  @if ($latest->canUnsubmit)
                    <form class="dc-undo" method="POST" action="{{ route('student.submission.unsubmit', $d->file_id) }}" data-name="{{ $d->file_name }}">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="up-btn danger">Unsubmit</button>
                    </form>
                  @endif
                </div>
              </div>
              @if ($older->isNotEmpty())
                <div class="dl-hist" id="dl-h-{{ $d->file_id }}" hidden>
                  @foreach ($older as $v)
                    @php $o = $v->document; @endphp
                    <div class="dl-hrow">
                      <span class="dl-ver">v{{ (int) $o->file_version }}</span>
                      <div style="min-width: 0">
                        <p class="dl-meta"><span class="dl-file">{{ $o->file_name }}</span> · {{ $o->roundLabel() }} · {{ $whoSent($v) }} · {{ $o->created_at?->format('M j, Y g:i A') }}</p>
                        <ul class="dl-st">
                          @forelse ($v->reviews as $r)
                            <li class="{{ $r['tone'] ?: 'wait' }}" title="{{ $r['name'] }}"><span><b>{{ $r['role'] }}</b> · {{ $r['text'] }}</span></li>
                          @empty
                            <li class="wait"><span>Sent to nobody</span></li>
                          @endforelse
                        </ul>
                      </div>
                      <div class="dl-acts">
                        <a class="up-btn" href="{{ route('student.documents', ['file' => $o->file_id]) }}" aria-label="View {{ $o->file_name }}, version {{ (int) $o->file_version }}">View</a>
                        @if ($v->canUnsubmit)
                          <form class="dc-undo" method="POST" action="{{ route('student.submission.unsubmit', $o->file_id) }}" data-name="{{ $o->file_name }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="up-btn danger">Unsubmit</button>
                          </form>
                        @endif
                      </div>
                    </div>
                  @endforeach
                </div>
              @endif
            </article>
          @endforeach
        </div>
        <p class="dl-none" id="dl-none" role="status" hidden>No papers match.</p>
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
  const cards = [...list.querySelectorAll('.dl-doc')];
  const tabs = [...document.querySelectorAll('.dl-tab')];
  const search = document.getElementById('dl-search');
  const none = document.getElementById('dl-none');
  let filter = 'all';
  const apply = () => {
    const words = search.value.trim().toLowerCase();
    let shown = 0;
    cards.forEach(card => {
      const fits = (filter === 'all' || card.dataset.filter === filter) && (!words || card.dataset.search.includes(words));
      card.hidden = !fits;
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
  const tools = document.querySelector('.dl-tools');
  const page = tools.closest('.content');
  if (page) {
    const pad = parseFloat(getComputedStyle(page).paddingTop) || 0;
    tools.style.setProperty('--dl-pad', pad + 'px');
    const stick = () => tools.classList.toggle('stuck', page.scrollTop > 0 && tools.getBoundingClientRect().top <= page.getBoundingClientRect().top + pad + 1);
    page.addEventListener('scroll', stick, { passive: true });
    stick();
  }
  // The earlier versions of a paper fold away under it.
  list.addEventListener('click', event => {
    const button = event.target.closest('[data-dl-more]');
    if (!button) return;
    const open = button.getAttribute('aria-expanded') !== 'true';
    button.setAttribute('aria-expanded', String(open));
    document.getElementById(button.getAttribute('aria-controls')).hidden = !open;
    button.querySelector('span').textContent = (open ? 'Hide ' : 'Show ') + button.dataset.label;
  });
})();
function toggleSidebar() { document.body.classList.toggle('sidebar-open'); }
function closeSidebar() { document.body.classList.remove('sidebar-open'); }
</script>
</body>
</html>
