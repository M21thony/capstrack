@php
  use App\Http\Controllers\AdminBluebookController as BB;
  use App\Support\Bluebook;
  $team = isset($row);
  $state = function ($l) {
      return match (true) {
          Bluebook::done($l) => ['ok', 'Accepted'],
          $l->waiting_on === 'team' => ['back', 'Sent back'],
          $l->waiting_on === 'dean' => ['wait', 'Waiting'],
          default => ['none', 'Not sent'],
      };
  };
  $kindClass = fn ($k) => match ($k) { 'PDF' => 'k-pdf', 'Video' => 'k-vid', 'DOCX' => 'k-docx', default => 'k-none' };
  $kindIcon = fn ($k) => $k === 'Video' ? 'vid' : 'doc';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Bluebook Clearance - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
  @include('partials.render-whole')
<style nonce="{{ Vite::cspNonce() }}">
.bb{flex:1;min-height:0;display:flex;flex-direction:column;gap:8px;padding:8px 14px 10px;font-size:13px;color:var(--text);background:var(--bg3)}
.bb svg.i{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;flex:none}
.bb-card{background:#fff;border:1px solid var(--border);border-radius:10px;min-width:0}
.bb-grow{flex:1}
.bb .bb-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;height:30px;padding:0 12px;border:0;border-radius:8px;background:#18191f;color:#fff;font:inherit;font-size:12.5px;font-weight:600;white-space:nowrap;text-decoration:none;cursor:pointer}
.bb .bb-btn svg.i{width:14px;height:14px}
.bb .bb-btn[disabled]{background:#c8cad4;cursor:not-allowed}
.bb-field{height:30px;padding:0 9px;border:1px solid var(--border);border-radius:8px;background:#fff;font:inherit;font-size:12.5px;color:var(--text);min-width:0}
.bb-state{display:inline-flex;align-items:center;gap:6px;white-space:nowrap}
.bb-state i{width:8px;height:8px;border-radius:50%;background:var(--c)}
.s-wait{--c:#ea6a0a}.s-back{--c:#dc2626}.s-ok{--c:#16a34a}.s-none{--c:#9aa0ac}
.k-pdf{background:#e7f0ff;color:#2563eb}.k-vid{background:#efe9ff;color:#7c3aed}.k-docx{background:#dff5f3;color:#0f766e}.k-none{background:var(--bg4);color:var(--text3)}
.bb-ico{flex:none;display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px}
.bb-err{color:#dc2626;font-size:12px;white-space:nowrap}

.bb-tiles{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;flex:none}
.bb-tile{display:flex;align-items:center;gap:9px;height:52px;padding:0 12px;background:#fff;border:1px solid var(--border);border-radius:10px;color:inherit;text-decoration:none}
.bb-tile.on{border-color:#18191f;box-shadow:inset 0 0 0 1px #18191f}
.bb-tile b{display:block;font-size:18px;line-height:1.1}
.bb-tile span.c{display:block;font-size:11.5px;font-weight:600;color:var(--text2);white-space:nowrap}
.t-none{background:#e7f0ff;color:#2563eb}.t-wait{background:#fdeadb;color:#c2570c}.t-back{background:#fde4e4;color:#dc2626}.t-ok{background:#dff5e6;color:#16a34a}
.bb-list{flex:1;min-height:0;display:flex;flex-direction:column;overflow:hidden}
.bb-bar{display:flex;align-items:center;gap:8px;flex:none;padding:7px 10px;border-bottom:1px solid var(--border)}
.bb-bar h2{margin:0;font-size:13.5px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bb-tools{display:contents}
.bb-scroll{flex:1;min-height:0;overflow:auto}
.bb-scroll table{width:100%;border-collapse:collapse}
.bb .bb-scroll th{position:sticky;top:0;z-index:1;height:30px;padding:0 10px;background:#18191f;border-bottom:0;font-size:11.5px;font-weight:600;letter-spacing:.4px;text-align:left;text-transform:uppercase;color:#d4d6de;white-space:nowrap}
.bb .bb-scroll td{height:32px;padding:0 10px;border-bottom:1px solid var(--bg4);font-size:13px;font-variant-numeric:tabular-nums;white-space:nowrap}
.bb .bb-scroll td.t{max-width:0;width:40%;overflow:hidden;text-overflow:ellipsis;color:var(--text2)}
.bb .bb-scroll td a{color:inherit;font-weight:600;text-decoration:none}
.bb .bb-scroll tr[data-href]{cursor:pointer}
.bb .bb-scroll tr[data-href]:hover td{background:var(--bg3)}
.bb .bb-scroll td.empty{height:80px;text-align:center;color:var(--text3)}

.bb-crumb{display:flex;align-items:center;gap:10px;flex:none;min-height:34px}
.bb-crumb nav{font-size:13.5px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bb-crumb nav a{color:var(--text2);font-weight:600;text-decoration:none}
.bb-crumb nav u{margin:0 6px;color:var(--text2);text-decoration:none}
.bb-crumb form{display:contents}
.bb-count{font-size:12.5px;color:var(--text2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bb-count b{color:var(--text)}
.bb-head{flex:none;display:flex;align-items:center;gap:16px;padding:10px 14px}
.bb-head > div:first-child{min-width:0;flex:1}
.bb-head h2{margin:0;font-size:15px;font-weight:700}
.bb-head p{margin:1px 0 0;font-size:12.5px;color:var(--text2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bb-head p b{color:var(--text);font-weight:600}
.bb-head p.m{display:flex;flex-wrap:wrap;gap:0 12px}
.bb-head form{display:contents}
.bb-prog{flex:none;width:170px;font-size:12.5px;color:var(--text2)}
.bb-prog b{color:var(--text)}
.bb-prog span{display:block;height:5px;margin-top:5px;border-radius:3px;background:var(--bg4);overflow:hidden}
.bb-prog span i{display:block;height:100%;background:var(--accent)}
.bb-split{flex:1;min-height:0;display:grid;grid-template-columns:minmax(0,1fr) 310px;gap:8px}
.bb-side{display:flex;flex-direction:column;min-height:0;overflow:hidden}
.bb-items{flex:1;min-height:0;overflow:auto}
.bb-sub{height:27px;display:flex;align-items:center;padding:0 12px;background:var(--bg3);border-bottom:1px solid var(--border);font-size:11.5px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:var(--text2)}
.bb-item{display:flex;align-items:center;gap:9px;padding:6px 12px;border-bottom:1px solid var(--bg4);color:inherit;text-decoration:none}
.bb-item:hover{background:var(--bg3)}
.bb-item.on{background:var(--accent-light);box-shadow:inset 3px 0 0 var(--accent)}
.bb-item div{min-width:0;flex:1}
.bb-item b{display:block;font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bb-item small{display:block;font-size:11.5px;color:var(--text2)}
.bb-item .bb-state{font-size:12px}

.bb-viewer{display:flex;flex-direction:column;min-height:0;overflow:hidden}
.bb-viewer .bb-bar .bb-count{min-width:0}
.bb-stage{flex:1;min-height:0;display:flex;align-items:center;justify-content:center;gap:10px;padding:10px;background:#3a3d46;overflow:hidden;color:#fff}
.bb-stage iframe{width:100%;height:100%;border:0;background:#fff}
.bb-stage iframe.vid{background:#000}
.bb-stage .bb-none{display:flex;flex-direction:column;align-items:center;gap:10px;font-size:13px;color:#d4d6dc}
.bb-stage .bb-none .bb-btn{background:#fff;color:#18191f}
.bb-foot{flex:none;display:flex;align-items:center;gap:8px;padding:7px 10px;border-top:1px solid var(--border)}
.bb-foot form{display:contents}
.bb-foot .bb-field{flex:1}
.bb-foot .bb-note{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text2)}
.bb-stage{position:relative}
.bb-stage dialog.pv.pv{position:absolute;z-index:1}
.bb-stage .pv-card{width:100%;height:100%;border-radius:0}
.bb-stage .pv-x{display:none}

.bb-sheet{width:560px;max-width:calc(100vw - 28px);max-height:calc(100vh - 28px);margin:auto;padding:0;border:0;border-radius:12px;background:#fff;overflow:auto}
.bb-sheet::backdrop{background:rgba(20,22,28,.45)}
.bb-sheet .bb-foot{justify-content:flex-end}

@media(max-width:760px){
  #real-content{overflow:auto !important}
  .bb{flex:none;padding:8px 12px 84px}
  .bb-split,.bb-list,.bb-items{flex:none}
  .bb-side,.bb-viewer,.bb-items,.bb-list{overflow:visible}
  .bb-scroll{overflow-x:auto}
  .bb-tiles{grid-template-columns:repeat(2,minmax(0,1fr))}
  .bb-bar{flex-wrap:wrap}
  .bb-bar h2{flex:1 1 100%}
  .bb-bar .bb-field{flex:1 1 40%}
  .bb .bb-opt{display:none}
  .bb-crumb{flex-wrap:wrap}
  .bb-split{display:flex;flex-direction:column}
  .bb-head{flex-wrap:wrap;gap:8px 14px}
  .bb-head > div:first-child{flex:1 1 100%}
  .bb-head p{white-space:normal}
  .bb-prog{flex:1}
  .bb-stage{height:380px;flex:none}
  .bb-foot{flex-wrap:wrap}
  .bb-foot .bb-field{flex:1 1 100%}
  .bb-foot .bb-btn{flex:1}
}
@media print{
  body *{visibility:hidden}
  #bb-slip,#bb-slip *{visibility:visible}
  #bb-slip{position:absolute;inset:0 auto auto 0;width:100%;max-width:none;max-height:none;overflow:visible}
  #bb-slip .bb-foot{display:none}
}
</style>
</head>
<body>

@include('capsa._sidebar')
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Bluebook Clearance</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="card" style="padding:16px">
      <div class="sk" style="height:36px;border-radius:8px;margin-bottom:8px"></div>
      <div class="sk" style="height:36px;border-radius:8px;margin-bottom:8px"></div>
      <div class="sk" style="height:36px;border-radius:8px"></div>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="bb-up" viewBox="0 0 24 24"><path d="M12 16V5M7 10l5-5 5 5M4 20h16"/></symbol>
  <symbol id="bb-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
  <symbol id="bb-back" viewBox="0 0 24 24"><path d="M9 14 4 9l5-5M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></symbol>
  <symbol id="bb-check" viewBox="0 0 24 24"><path d="M5 12.5 10 17 19 7.5"/></symbol>
  <symbol id="bb-save" viewBox="0 0 24 24"><path d="M12 3v12M7 11l5 5 5-5M4 20h16"/></symbol>
  <symbol id="bb-doc" viewBox="0 0 24 24"><path d="M14 3H6v18h12V7zM14 3v4h4M9 13h6M9 17h6"/></symbol>
  <symbol id="bb-vid" viewBox="0 0 24 24"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 11 5-3v8l-5-3"/></symbol>
  <symbol id="bb-open" viewBox="0 0 24 24"><path d="M14 4h6v6M20 4l-9 9M18 14v6H4V6h6"/></symbol>
  <symbol id="bb-print" viewBox="0 0 24 24"><path d="M7 9V3h10v6M7 17H4v-8h16v8h-3M7 14h10v7H7z"/></symbol>
  <symbol id="bb-seal" viewBox="0 0 24 24"><circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 7 5-3 5 3-1.5-7M9.5 9l2 2 3-3"/></symbol>
</svg>

<main class="bb">
@if (! $team)
  @php $keep = request()->only('program', 'section', 'q'); @endphp
  <div class="bb-tiles">
    @foreach (BB::TABS as $key => $label)
      @php [$tc, $ti] = [Bluebook::NOT_SUBMITTED => ['t-none', 'up'], Bluebook::WAITING => ['t-wait', 'clock'], Bluebook::SENT_BACK => ['t-back', 'back'], Bluebook::CLEARED => ['t-ok', 'seal']][$key]; @endphp
      <a class="bb-tile {{ $status === $key ? 'on' : '' }}" href="{{ route('admin.bluebook', $keep + ['status' => $key]) }}">
        <span class="bb-ico {{ $tc }}"><svg class="i"><use href="#bb-{{ $ti }}"/></svg></span>
        <span><b>{{ $counts[$key] ?? 0 }}</b><span class="c">{{ $label }}</span></span>
      </a>
    @endforeach
  </div>

  <div class="bb-card bb-list">
    <form class="bb-bar" method="GET" action="{{ route('admin.bluebook') }}" id="bb-filter">
      <h2>{{ BB::TABS[$status] }}</h2><span class="bb-grow"></span>
      <input type="hidden" name="status" value="{{ $status }}">
      <select class="bb-field" name="program" aria-label="Program">
        <option value="">All programs</option>
        @foreach ($programs as $p)<option value="{{ $p }}" @selected(request('program') === $p)>{{ $p }}</option>@endforeach
      </select>
      <select class="bb-field" name="section" aria-label="Section">
        <option value="">All sections</option>
        @foreach ($sections as $s)<option value="{{ $s }}" @selected(request('section') === $s)>{{ $s }}</option>@endforeach
      </select>
      <input class="bb-field" type="search" name="q" value="{{ request('q') }}" placeholder="Search team" aria-label="Search team">
      <span class="bb-tools" id="bb-tools"></span>
    </form>
    <div class="bb-scroll">
      <table data-export="Bluebook Clearance - {{ BB::TABS[$status] }}" data-export-into="#bb-tools">
        <thead>
        @if ($status === Bluebook::CLEARED)
          <tr><th>Team</th><th class="bb-opt">Title</th><th class="bb-opt">Program</th><th>Section</th><th>Cleared On</th><th class="bb-opt">Cleared By</th><th>Slip No.</th></tr>
        @else
          <tr><th>Team</th><th class="bb-opt">Title</th><th class="bb-opt">Program</th><th>Section</th><th>Checked</th><th class="bb-opt">Submitted</th><th>Status</th></tr>
        @endif
        </thead>
        <tbody>
        @forelse ($rows as $r)
          @php $b = $r->bluebook; $url = route('admin.bluebook.show', $r->team); @endphp
          <tr data-href="{{ $url }}">
            <td><a href="{{ $url }}">{{ $r->team->team_name }}</a></td>
            <td class="t bb-opt">{{ $r->team->project_title ?: '—' }}</td>
            <td class="bb-opt">{{ $r->program ?: '—' }}</td>
            <td>{{ $r->section }}</td>
          @if ($status === Bluebook::CLEARED)
            <td>{{ $b->cleared_on?->format('M j, Y') }}</td>
            <td class="bb-opt">{{ $b->cleared_by ?? '—' }}</td>
            <td>{{ $b->slip_no }}</td>
          @else
            @php
              [$sc, $sw] = match ($status) {
                  Bluebook::SENT_BACK => ['back', 'Sent back'],
                  Bluebook::NOT_SUBMITTED => ['none', 'Not submitted'],
                  default => $b->accepted === $b->lines->count() ? ['ok', 'Ready to clear'] : ['wait', 'Waiting'],
              };
            @endphp
            <td>{{ $b->accepted }} of {{ $b->lines->count() }}</td>
            <td class="bb-opt">{{ $r->submitted?->format('M j, Y') ?? '—' }}</td>
            <td><span class="bb-state s-{{ $sc }}"><i></i>{{ $sw }}</span></td>
          @endif
          </tr>
        @empty
          <tr><td class="empty" colspan="7">No teams here.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
@else
  @php
    $b = $row->bluebook;
    $all = $b->lines->isNotEmpty() && $b->accepted === $b->lines->count();
    $file = $line?->file;
    $kind = Bluebook::kind($file);
    $video = $file ? Bluebook::driveId($file->file_path) : null;
  @endphp
  <div class="bb-crumb">
    <nav><a href="{{ route('admin.bluebook') }}">Bluebook Clearance</a><u>›</u>{{ $row->team->team_name }}</nav>
  </div>

  <div class="bb-card bb-head">
    <div>
      <h2>{{ $row->team->team_name }}</h2>
      <p>{{ $row->team->project_title ?: '—' }}</p>
      <p class="m">@if ($row->program)<span><b>Program</b> {{ $row->program }}</span>@endif<span><b>Section</b> {{ $row->section }}</span><span title="{{ $row->members->join(', ') }}"><b>Members</b> {{ $row->members->count() }}</span>@if ($row->submitted)<span>Submitted {{ $row->submitted->format('M j, Y') }}</span>@endif</p>
    </div>
    @if ($b->cleared)
      <span class="bb-count">Cleared <b>{{ $b->cleared_on->format('M j, Y') }}</b> by <b>{{ $b->cleared_by ?? '—' }}</b></span>
      <button class="bb-btn" type="button" id="bb-slip-open"><svg class="i"><use href="#bb-print"/></svg>View Slip</button>
    @else
      <div class="bb-prog"><b>{{ $b->accepted }} of {{ $b->lines->count() }}</b> accepted<span><i style="width:{{ $b->lines->count() ? round($b->accepted / $b->lines->count() * 100) : 0 }}%"></i></span></div>
      <form method="POST" action="{{ route('admin.bluebook.clear', $row->team) }}" id="bb-clear" data-team="{{ $row->team->team_name }}">
        @csrf
        <button class="bb-btn" type="submit" @disabled(! $all || ! $b->clearance)><svg class="i"><use href="#bb-seal"/></svg>Clear for Bluebook</button>
      </form>
    @endif
  </div>

  <div class="bb-split">
    <section class="bb-card bb-viewer">
    @if ($line)
      <div class="bb-bar">
        <h2>{{ $line->item->title }}</h2>
        @if ($file)
          <span class="bb-count">{{ $video ? 'Google Drive link · uploaded' : 'Uploaded' }} by {{ $uploader ? trim($uploader->f_name.' '.$uploader->l_name) : '—' }}, {{ $file->created_at?->format('M j, Y') }}</span>
        @endif
        <span class="bb-grow"></span>
        @if ($file && ! $video && $kind !== 'PDF')
          <a class="bb-btn" href="{{ route('admin.bluebook.file', $file) }}"><svg class="i"><use href="#bb-save"/></svg>Download</a>
        @endif
      </div>
      <div class="bb-stage">
        @if ($video)
          <iframe class="vid" src="https://drive.google.com/file/d/{{ $video }}/preview" allow="autoplay; fullscreen" allowfullscreen title="{{ $line->item->title }}"></iframe>
        @elseif ($kind === 'PDF')
          <div class="bb-none">{{ $file->file_name }}<button class="bb-btn" type="button" id="bb-view" data-url="{{ route('admin.bluebook.file', $file) }}" data-title="{{ $line->item->title }}" data-meta="{{ $row->team->team_name }} · {{ $row->section }}" data-file="{{ $file->file_name }}"><svg class="i"><use href="#bb-open"/></svg>View</button></div>
        @elseif ($file)
          <div class="bb-none">{{ $file->file_name }}<a class="bb-btn" href="{{ route('admin.bluebook.file', $file) }}"><svg class="i"><use href="#bb-save"/></svg>Download</a></div>
        @else
          <div class="bb-none">Not sent yet</div>
        @endif
      </div>
      @if ($file)
        <div class="bb-foot">
          @if (Bluebook::done($line))
            <span class="bb-state s-ok"><i></i>Accepted {{ $line->closed_at?->format('M j, Y') }}</span>
            <span class="bb-grow"></span>
            @unless ($b->cleared)
              <form method="POST" action="{{ route('admin.bluebook.undo', [$row->team, $line->item]) }}">
                @csrf @method('DELETE')
                <button class="bb-btn" type="submit"><svg class="i"><use href="#bb-back"/></svg>Undo</button>
              </form>
            @endunless
          @elseif ($line->waiting_on === 'team')
            <span class="bb-state s-back"><i></i>Sent back</span>
            <span class="bb-note">{{ $line->note }}</span>
            <span class="bb-grow"></span>
            <form method="POST" action="{{ route('admin.bluebook.undo', [$row->team, $line->item]) }}">
              @csrf @method('DELETE')
              <button class="bb-btn" type="submit"><svg class="i"><use href="#bb-back"/></svg>Undo</button>
            </form>
          @else
            <form method="POST" action="{{ route('admin.bluebook.check', [$row->team, $line->item]) }}">
              @csrf @method('PUT')
              <input class="bb-field" name="note" value="{{ old('note') }}" maxlength="1000" placeholder="Reason, if sending back" aria-label="Reason, if sending back">
              @error('note')<span class="bb-err" role="alert">{{ $message }}</span>@enderror
              <button class="bb-btn" type="submit" name="status" value="sent_back"><svg class="i"><use href="#bb-back"/></svg>Send back</button>
              <button class="bb-btn" type="submit" name="status" value="complete"><svg class="i"><use href="#bb-check"/></svg>Accept</button>
            </form>
          @endif
        </div>
      @endif
    @else
      <div class="bb-stage"><div class="bb-none">No requirements on this checklist</div></div>
    @endif
    </section>

    <aside class="bb-card bb-side">
      <div class="bb-bar"><h2>Submitted Files</h2><span class="bb-grow"></span><span class="bb-count">{{ $b->lines->whereNotNull('file')->count() }} of {{ $b->lines->count() }}</span></div>
      <div class="bb-items">
        @foreach ($b->lines as $l)
          @php [$sc, $sw] = $state($l); $k = Bluebook::kind($l->file); @endphp
          <a class="bb-item {{ $line && $l->item->id === $line->item->id ? 'on' : '' }}" href="{{ route('admin.bluebook.show', [$row->team, 'line' => $l->item->id]) }}">
            <span class="bb-ico {{ $kindClass($k) }}"><svg class="i"><use href="#bb-{{ $kindIcon($k) }}"/></svg></span>
            <div><b>{{ $l->item->title }}</b><small>{{ $k ?? 'Nothing sent' }}</small></div>
            <span class="bb-state s-{{ $sc }}"><i></i>{{ $sw }}</span>
          </a>
        @endforeach
      </div>
    </aside>
  </div>

  @if ($b->cleared)
    <dialog class="bb-sheet" id="bb-slip" aria-label="Clearance slip">
      @include('admin.bluebook-slip')
      <div class="bb-foot">
        <button class="bb-btn" type="button" id="bb-slip-close">Close</button>
        <a class="bb-btn" href="{{ route('admin.bluebook.slip', $row->team) }}"><svg class="i"><use href="#bb-save"/></svg>Download PDF</a>
        <button class="bb-btn" type="button" id="bb-print"><svg class="i"><use href="#bb-print"/></svg>Print</button>
      </div>
    </dialog>
  @endif
@endif
</main>
  </div>
</div>
@include('capsa.partials.skeleton-toggle')

<script nonce="{{ Vite::cspNonce() }}">
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() { if (sidebarEl) { sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar(); } }
function openSidebar() { if (!sidebarEl || !sidebarOverlay) return; sidebarEl.classList.add('mobile-open'); sidebarOverlay.classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeSidebar() { if (!sidebarEl || !sidebarOverlay) return; sidebarEl.classList.remove('mobile-open'); sidebarOverlay.classList.remove('open'); document.body.style.overflow = ''; }

(function () {
  var filter = document.getElementById('bb-filter');
  if (filter) filter.querySelectorAll('select').forEach(function (s) { s.addEventListener('change', function () { filter.submit(); }); });

  document.querySelectorAll('tr[data-href]').forEach(function (tr) {
    tr.addEventListener('click', function (e) { if (!e.target.closest('a')) location.href = tr.dataset.href; });
  });

  var clear = document.getElementById('bb-clear');
  if (clear) clear.addEventListener('submit', function (e) {
    if (clear.dataset.ok) return;
    e.preventDefault();
    window.confirmSheet({ title: 'Clear ' + clear.dataset.team + ' for Bluebook?', confirmLabel: 'Clear' }).then(function (ok) {
      if (ok) { clear.dataset.ok = '1'; clear.submit(); }
    });
  });

  // A PDF shows in the app's PDF window, placed inside the stage box. View reopens it after Esc.
  var view = document.getElementById('bb-view');
  if (view) {
    var openPdf = function () {
      view.closest('.bb-stage').append(document.getElementById('previewDialog'));
      window.PdfViewer.open({ url: view.dataset.url, title: view.dataset.title, meta: view.dataset.meta, fileName: view.dataset.file, inPage: true });
      document.documentElement.style.overflow = '';
    };
    view.addEventListener('click', openPdf);
    window.addEventListener('load', openPdf);
  }

  var slip = document.getElementById('bb-slip');
  if (slip) {
    document.getElementById('bb-slip-open').addEventListener('click', function () { slip.showModal(); });
    document.getElementById('bb-print').addEventListener('click', function () { window.print(); });
    document.getElementById('bb-slip-close').addEventListener('click', function () { slip.close(); });
    if (new URLSearchParams(location.search).has('slip')) slip.showModal();
  }
})();
</script>
@include('partials.app-dialog')
@include('partials.pdf-viewer')
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>
