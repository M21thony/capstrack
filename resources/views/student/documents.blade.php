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
  /* Documents: Document Submission and Review Viewer as one page. The list
     is one group per line with every version under it - who sent it, when,
     where each reviewer is. A version opens on this page with its remarks
     beside it. The list is a table when there is room and one compact row
     per version when there is not. */
  .dc-card { max-width: 1160px; margin: 0 auto; container: dc-list / inline-size; }
  .dc-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
  .dc-table { width: 100%; border-collapse: collapse; }
  .dc-table thead th { text-align: left; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--text3); background: var(--bg3); padding: 8px 14px; border-bottom: 1px solid var(--border); white-space: nowrap; }
  .dc-table thead th:first-child { padding-left: 66px; }
  .dc-table tbody + tbody .dc-group th { border-top: 1px solid var(--border); }
  .dc-group th { text-align: left; font-weight: 400; padding: 14px 16px 4px; }
  .dc-group-row { display: flex; align-items: center; gap: 8px 16px; flex-wrap: wrap; }
  .dc-title { flex: 1 1 240px; min-width: 0; display: flex; align-items: center; gap: 16px; }
  .dc-title b { display: block; font-size: 14px; color: var(--text); }
  .dc-title small { display: block; font-size: 12px; color: var(--text3); }
  .dc-acts { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .dc-acts .up-btn { height: 34px; font-size: 13px; }
  .dc-ic { width: 34px; height: 40px; flex-shrink: 0; border-radius: 6px; border: 1px solid var(--border2); background: var(--bg2); display: grid; place-items: end center; padding-bottom: 5px; font: 700 8px/1 ui-monospace, Menlo, Consolas, monospace; color: #c42424; }
  .dc-ic.drawio { color: #c2410c; font-size: 6.5px; }
  .dc-ic.vsdx { color: #1d4ed8; font-size: 7px; }
  .dc-row td { padding: 8px 14px; font-size: 13px; vertical-align: middle; }
  .dc-row:last-child td { padding-bottom: 14px; }
  .dc-row:hover td { background: var(--bg3); }
  .dc-v { padding-left: 66px !important; white-space: nowrap; width: 1%; }
  .dc-v b { font: 600 12.5px/1 ui-monospace, Menlo, Consolas, monospace; color: var(--text); margin-right: 6px; }
  .dc-v small { font-size: 12px; color: var(--text3); }
  .dc-file { display: block; font: 12.5px/1.4 ui-monospace, Menlo, Consolas, monospace; color: var(--text2); overflow-wrap: break-word; }
  .dc-f small { display: block; font-size: 11.5px; color: var(--text3); }
  .dc-row.old .dc-file, .dc-row.old .dc-v b { color: var(--text3); }
  .dc-who { display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; }
  .dc-av { width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; flex-shrink: 0; background: var(--bg4); color: var(--text2); font-size: 10px; font-weight: 700; }
  .dc-av.me { background: var(--accent, #c9a82c); color: #1b1e26; }
  .dc-nm { display: block; color: var(--text); }
  .dc-nm small { color: var(--text3); font-size: 12px; }
  .dc-date { display: block; font-size: 12px; color: var(--text3); font-variant-numeric: tabular-nums; }
  .dc-chips { display: flex; flex-wrap: wrap; gap: 5px; }
  .dc-chip { display: inline-flex; align-items: center; gap: 5px; height: 22px; padding: 0 8px; border-radius: 999px; font-size: 11.5px; font-weight: 500; background: var(--bg4); color: var(--text2); white-space: nowrap; text-decoration: none; }
  .dc-chip.ok { background: rgba(22,163,74,.09); color: #15803d; }
  .dc-chip.revise { background: rgba(180,83,9,.09); color: #b45309; }
  .dc-chip.now { background: rgba(29,78,216,.08); color: #1d4ed8; }
  .dc-a { text-align: right; white-space: nowrap; width: 1%; }
  .dc-a > * { display: inline-flex; vertical-align: middle; margin: 0 0 0 6px; }
  .dc-a .up-btn { height: 32px; font-size: 12.5px; text-decoration: none; }
  .dc-a .up-btn.danger { color: #b45309; }
  .sub-status.now { background: rgba(29,78,216,.08); color: #1d4ed8; }
  .dc-card .sub-status { font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 999px; background: var(--bg4); color: var(--text2); white-space: nowrap; }
  .dc-card .sub-status.revise { background: rgba(180,83,9,.09); color: #b45309; }
  .dc-card .sub-status.ok { background: rgba(22,163,74,.09); color: #15803d; }

  @container dc-list (max-width: 860px) {
    .dc-table thead { display: none; }
    .dc-table, .dc-table tbody, .dc-group, .dc-group th { display: block; }
    .dc-row { display: grid; grid-template-columns: 34px minmax(0, 1fr) 190px auto; grid-template-areas: "v f w a" ". r r a"; gap: 4px 16px; align-items: center; padding: 8px 16px; }
    .dc-row:last-child { padding-bottom: 14px; }
    .dc-row td, .dc-row:last-child td { padding: 0 !important; background: none !important; }
    .dc-v { grid-area: v; } .dc-f { grid-area: f; min-width: 0; } .dc-w { grid-area: w; min-width: 0; } .dc-r { grid-area: r; } .dc-a { grid-area: a; }
    .dc-v small { display: none; }
    .dc-file { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dc-who { min-width: 0; } .dc-who > span { min-width: 0; overflow: hidden; }
    .dc-nm, .dc-date { overflow: hidden; text-overflow: ellipsis; }
    .dc-a { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
    .dc-a > * { margin: 0; }
  }
  @container dc-list (max-width: 560px) {
    .dc-row { grid-template-columns: 34px minmax(0, 1fr) auto; grid-template-areas: "v f a" ". w a" ". r r"; }
    .dc-who > span { white-space: nowrap; text-overflow: ellipsis; }
    .dc-nm, .dc-date { display: inline; }
    .dc-date { margin-left: 4px; } .dc-date::before { content: "· "; }
    .dc-av { width: 22px; height: 22px; font-size: 9px; }
    .dc-acts .up-btn, .dc-a .up-btn { height: 40px; }
  }

  /* One version open: what it is and who reviewed it, then the file and its remarks. */
  .content:has(.rv-shell) { padding: 0; overflow: hidden; }
  .rv-shell { display: grid; grid-template-rows: auto auto minmax(0, 1fr); height: 100%; }
  .rv-bar { display: flex; align-items: center; gap: 8px 12px; padding: 10px 16px; background: var(--bg2); border-bottom: 1px solid var(--border); flex-wrap: wrap; }
  .dc-back { width: 36px; height: 36px; flex-shrink: 0; border: 1px solid var(--border); border-radius: 8px; display: grid; place-items: center; color: var(--text2); text-decoration: none; }
  .dc-back:hover { color: var(--text); border-color: var(--border2); }
  .dc-back svg, .rv-btn svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
  .dc-rid { flex: 1 1 260px; min-width: 0; }
  .dc-rid b { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 15px; color: var(--text); }
  .dc-rid small { display: block; font-size: 12.5px; color: var(--text3); overflow-wrap: anywhere; }
  .dc-vers { display: inline-flex; gap: 2px; background: var(--bg4); border-radius: 8px; padding: 3px; }
  .dc-vers a { min-width: 34px; height: 28px; padding: 0 8px; border-radius: 6px; display: grid; place-items: center; font: 600 12px/1 ui-monospace, Menlo, Consolas, monospace; color: var(--text2); text-decoration: none; }
  .dc-vers a[aria-current="true"] { background: var(--bg2); color: var(--text); box-shadow: 0 1px 2px rgba(15,18,26,.12); }
  .rv-btn { display: inline-flex; align-items: center; gap: 6px; border: 1px solid transparent; border-radius: 6px; padding: 6px 12px; font-size: 13px; font-weight: 600; text-decoration: none; min-height: 34px; color: var(--text2); background: none; cursor: pointer; font-family: inherit; }
  .rv-btn.ghost:hover { background: var(--accent-light); color: var(--text); }
  .rv-btn.gold { background: var(--accent); border-color: var(--accent); color: #18191f; font-weight: 700; }
  .rv-btn.gold:hover { background: var(--accent2); }
  .rv-btn.danger { color: #b45309; }
  .rv-btn:focus-visible, .dc-back:focus-visible, .dc-vers a:focus-visible, .dc-revs a:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
  .dc-revs { display: flex; align-items: center; gap: 6px 10px; flex-wrap: wrap; padding: 8px 16px; background: var(--bg2); border-bottom: 1px solid var(--border); }
  .dc-revs > span:first-child { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--text3); }
  .dc-revs .dc-chip { height: 30px; padding: 0 12px; font-size: 12.5px; border: 1px solid transparent; }
  .dc-revs a.dc-chip:hover { border-color: var(--border2); }
  .dc-revs .dc-chip[aria-current="true"] { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(201,168,44,.15); }
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
    @if ($version->isLatest && $rev?->verdict === \App\Models\CapstoneDocumentReview::NEEDS_REVISION)
      <button type="button" class="rv-btn gold" onclick="window.openSubmission && window.openSubmission('{{ $reader->decision['itemId'] }}', this, {{ (int) $reader->decision['fileId'] }})">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>Submit {{ $reader->decision['next'] }}
      </button>
    @endif
  </div>

  <div class="dc-revs" aria-label="Reviewers">
    <span>Review</span>
    @forelse ($version->reviews as $r)
      @if ($r['done'])
        <a class="dc-chip {{ $r['tone'] }}" href="{{ route('student.documents', ['file' => $doc->file_id, 'review' => $r['id']]) }}" @if ($rev?->review_id === $r['id']) aria-current="true" @endif title="Read {{ $r['name'] ?? $r['role'] }}’s remarks"><b>{{ $r['role'] }}</b> · {{ $r['text'] }}</a>
      @else
        <span class="dc-chip {{ $r['tone'] }}" title="{{ $r['name'] }}"><b>{{ $r['role'] }}</b> · {{ $r['tone'] === 'now' ? 'still reviewing' : 'not opened yet' }}</span>
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
    @include('student.partials.page-tutorial', ['page' => 'documents'])
    <section class="sub-card dc-card" aria-labelledby="dc-h">
      <div class="sub-card-h">
        <div>
          <h2 id="dc-h">Your team's documents</h2>
          <p>{{ $groups->count() }} {{ Str::plural('file', $groups->count()) }} · newest first · press View to read a reviewer's remarks</p>
        </div>
        <button type="button" class="up-btn primary" onclick="window.openSubmission && window.openSubmission('none', this)"
                @if ($selectedItemId) data-open-upload="{{ $selectedItemId }}" @endif>
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          Submit a file
        </button>
      </div>
      @if (session('status') || session('error'))
        <p class="sub-flash {{ session('error') ? 'bad' : '' }}" role="status">{{ session('error') ?? session('status') }}</p>
      @endif
      @if ($groups->isEmpty())
        <p class="sub-empty">Nothing sent yet. What your team submits shows up here, with your reviewers' remarks.</p>
      @else
        <table class="dc-table">
          <thead><tr><th scope="col">Version</th><th scope="col">File</th><th scope="col">Submitted</th><th scope="col">Review</th><th scope="col"><span class="dc-sr">Actions</span></th></tr></thead>
          @foreach ($groups as $group)
            @php $ext = strtolower(pathinfo((string) $group->latest->file_path, PATHINFO_EXTENSION)) ?: 'pdf'; @endphp
            <tbody>
              <tr class="dc-group">
                <th colspan="5" scope="rowgroup">
                  <div class="dc-group-row">
                    <span class="dc-title"><span class="dc-ic {{ $ext }}" aria-hidden="true">{{ strtoupper($ext) }}</span><span><b>{{ $group->title }}</b><small>{{ $group->versions->count() }} {{ Str::plural('submission', $group->versions->count()) }}</small></span></span>
                    <span class="dc-acts">
                      <span class="sub-status {{ $group->status[1] }}">{{ $group->status[0] }}</span>
                      @if ($group->revise)
                        <button type="button" class="up-btn primary" onclick="window.openSubmission && window.openSubmission('{{ $group->revise['itemId'] }}', this, {{ (int) $group->revise['fileId'] }})">Submit {{ $group->revise['next'] }}</button>
                      @endif
                    </span>
                  </div>
                </th>
              </tr>
              @foreach ($group->versions as $v)
                @php
                    $d = $v->document;
                    $who = $v->uploader ? trim($v->uploader->f_name.' '.$v->uploader->l_name) : 'A teammate';
                    $diagramCount = $d->isDiagram() ? 0 : count($d->diagram_files ?? []);
                @endphp
                <tr class="dc-row{{ $v->isLatest ? '' : ' old' }}">
                  <td class="dc-v"><b>v{{ (int) $d->file_version }}</b><small>{{ $d->roundLabel() }}</small></td>
                  <td class="dc-f"><span class="dc-file" title="{{ $d->file_name }}">{{ $d->file_name }}</span>@if ($diagramCount)<small>+ {{ $diagramCount }} {{ Str::plural('diagram file', $diagramCount) }}</small>@endif</td>
                  <td class="dc-w"><span class="dc-who"><span class="dc-av{{ $v->isMine ? ' me' : '' }}" aria-hidden="true">{{ $v->uploader?->avatarInitials() ?? '?' }}</span><span><span class="dc-nm">{{ $who }}@if ($v->isMine) <small>(you)</small>@endif</span><span class="dc-date">{{ $d->created_at?->format('M j, Y g:i A') }}</span></span></span></td>
                  <td class="dc-r"><span class="dc-chips">@forelse ($v->reviews as $r)<span class="dc-chip {{ $r['tone'] }}" title="{{ $r['name'] }}">{{ $r['role'] }} · {{ $r['text'] }}</span>@empty<span class="dc-chip">Sent to nobody</span>@endforelse</span></td>
                  <td class="dc-a">
                    <a class="up-btn" href="{{ route('student.documents', ['file' => $d->file_id]) }}" aria-label="View {{ $d->file_name }}, version {{ (int) $d->file_version }}">View</a>
                    @if ($v->canUnsubmit)
                      <form class="dc-undo" method="POST" action="{{ route('student.submission.unsubmit', $d->file_id) }}" data-name="{{ $d->file_name }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="up-btn danger">Unsubmit</button>
                      </form>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          @endforeach
        </table>
      @endif
    </section>
@endif
  </div>
</div>

@include('partials.escape-closes-modals')
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
function toggleSidebar() { document.body.classList.toggle('sidebar-open'); }
function closeSidebar() { document.body.classList.remove('sidebar-open'); }
</script>
</body>
</html>
