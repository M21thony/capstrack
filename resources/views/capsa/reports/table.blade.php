<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>{{ $name }} - Reports - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
@include('capsa.reports._styles')
<style>
/* The page itself does not scroll on a desktop: the table card takes what is left and scrolls inside. */
.rp.fit{overflow:hidden;height:100%;box-sizing:border-box}
.rp-top{display:flex;flex-wrap:wrap;gap:12px 20px;align-items:flex-end;justify-content:space-between}
.rp-filters{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.rp-filters .rp-field{width:170px}
.rp-filters .rp-field.wide{width:220px}

.rp-tools{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.rp-tools .grow{flex:1}
.rp-search{position:relative;flex:0 1 280px;min-width:180px}
.rp-search svg{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--text3)}
.rp-search input{width:100%;height:36px;padding:0 12px 0 34px;border:1px solid var(--border2);border-radius:7px;background:var(--bg2);color:var(--text);font:400 14px 'DM Sans',sans-serif;box-sizing:border-box}
.rp-seg{display:inline-flex;border:1px solid var(--border2);border-radius:7px;overflow:hidden;height:36px}
.rp-seg a,.rp-seg span{display:inline-flex;align-items:center;padding:0 12px;font-size:13.5px;text-decoration:none;color:var(--text2);background:var(--bg2)}
.rp-seg > * + *{border-left:1px solid var(--border2)}
.rp-seg [aria-current="true"]{background:var(--accent-light);color:var(--text);font-weight:600}
.rp-seg [aria-disabled="true"]{opacity:.45;cursor:not-allowed}
.rp-pop-wrap{position:relative}
.rp-pop{position:absolute;top:calc(100% + 6px);right:0;z-index:30;background:var(--bg2);border:1px solid var(--border);border-radius:10px;box-shadow:0 12px 32px rgba(0,0,0,.14);padding:6px}
.rp-pop.cols{width:300px;max-height:min(70vh,560px);max-height:min(70dvh,560px);overflow:auto}
.rp-pop h3{margin:0;padding:6px 8px 8px;font-size:11.5px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.04em}
.rp-col{display:flex;align-items:center;gap:10px;min-height:34px;padding:0 8px;border-radius:6px;font-size:14px;cursor:grab;user-select:none}
.rp-col:hover{background:var(--bg3)}
.rp-col.dragging{opacity:.5}
.rp-col.locked{cursor:default}
.rp-col input{width:16px;height:16px;margin:0;accent-color:var(--text)}
.rp-col .lbl{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.rp-col .lbl small{color:var(--text3)}
.rp-col .grip{color:#a3a8b4;display:flex}
.rp-col .lock{font-size:12px;color:var(--text3)}
.rp-pop .foot{border-top:1px solid var(--border);margin-top:4px;padding:8px 8px 4px;display:flex;justify-content:space-between;font-size:13px}
.rp-pop .foot button{border:0;background:none;padding:0;font:inherit;cursor:pointer;color:var(--text2)}
.rp-pop .foot button.done{font-weight:600;color:var(--text)}
.rp-pop .hint{font-size:12px;color:var(--text3);padding:6px 8px}
.rp-menu a{display:flex;gap:10px;align-items:flex-start;padding:10px;border-radius:6px;text-decoration:none;width:230px}
.rp-menu a:hover{background:var(--bg3)}
.rp-menu b{display:block;font-size:14px}
.rp-menu small{display:block;font-size:12.5px;color:var(--text3)}

.rp-merged{display:flex;flex-wrap:wrap;gap:8px;align-items:center;font-size:13px;color:var(--text2)}
.band-chip{display:inline-flex;align-items:center;gap:4px;height:28px;padding:0 4px 0 10px;border-radius:999px;font-weight:600}
.band-chip a{display:grid;place-items:center;width:22px;height:22px;border-radius:50%;text-decoration:none}
.band-chip a:hover{background:rgba(0,0,0,.08)}
.band-grey{background:#eef0f3;color:var(--text2)}
.band-gold{background:var(--accent-light);color:var(--accent3)}
.band-blue{background:#e8f0fb;color:#1d4f91}
.band-green{background:#e7f5ec;color:#17663a}

.rp > .rp-tcard{flex:1 1 auto;min-height:0;display:flex;flex-direction:column;overflow:hidden;position:relative}
.rp-scroll{flex:1;min-height:0;overflow:auto;scroll-behavior:smooth}
.rp-table{border-collapse:separate;border-spacing:0;min-width:100%;font-size:14px}
.rp-table th,.rp-table td{padding:11px 16px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top;white-space:nowrap;background:var(--bg2);font-variant-numeric:tabular-nums}
.rp-table td.r,.rp-table th.r{text-align:right}
.rp-table thead th{position:sticky;top:var(--band-h,0px);z-index:2;background:var(--head);font-size:12.5px;font-weight:600;color:var(--text2);border-bottom:1px solid var(--border);padding:0}
.rp-table thead tr.bands th{top:0;height:30px;padding:0 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;box-sizing:border-box}
.rp-table thead tr.bands th.band-grey{background:#eef0f3;color:var(--text2)}
.rp-table thead tr.bands th.band-gold{background:var(--accent-light);color:var(--accent3)}
.rp-table thead tr.bands th.band-blue{background:#e8f0fb;color:#1d4f91}
.rp-table thead tr.bands th.band-green{background:#e7f5ec;color:#17663a}
.rp-table .gstart{border-left:2px solid var(--border)}
.rp-table th .sort{display:flex;align-items:center;gap:6px;width:100%;padding:11px 16px;border:0;background:none;font:inherit;color:inherit;cursor:pointer;text-align:inherit;white-space:nowrap}
.rp-table th.r .sort{justify-content:flex-end}
.rp-table th .sort:hover{color:var(--text)}
.rp-table th .arr{width:12px;color:var(--text3)}
.rp-table th[aria-sort="ascending"] .arr::after{content:"▲";font-size:9px;color:var(--text)}
.rp-table th[aria-sort="descending"] .arr::after{content:"▼";font-size:9px;color:var(--text)}
/* The first column names the row and stays put while the rest scroll under it. */
.rp-table th:first-child,.rp-table td:first-child{position:sticky;left:0;z-index:1;box-shadow:1px 0 0 var(--border)}
.rp-table thead th:first-child{z-index:3}
.rp-table tbody tr[data-team]{cursor:pointer}
.rp-table tbody tr[data-team]:hover td{background:#fbfaf5}
.rp-table tfoot td{background:var(--head);font-weight:700;border-top:1px solid var(--border);position:sticky;bottom:0}
.rp-table tfoot td:first-child{z-index:2}
.rp-table .dash{color:var(--text3)}
.rp-table .barw{display:inline-flex;align-items:center;gap:10px;justify-content:flex-end}
.rp-table .bar{display:block;width:72px;height:6px;border-radius:3px;background:var(--line);overflow:hidden}
.rp-table .bar i{display:block;height:6px;background:var(--accent)}
.rp-table .bar i.full{background:#b42318}
.rp-empty{padding:48px 20px;text-align:center;color:var(--text3)}
.rp-empty b{display:block;color:var(--text);font-size:15px;margin-bottom:4px}

.rp-edge{position:absolute;top:50%;transform:translateY(-50%);z-index:5;width:34px;height:56px;border:1px solid var(--border2);background:var(--bg2);border-radius:8px;display:grid;place-items:center;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.12);color:var(--text)}
.rp-edge:hover{border-color:var(--text3)}
.rp-edge.l{left:8px}.rp-edge.r{right:8px}
.rp-foot{display:flex;flex-wrap:wrap;gap:6px 16px;justify-content:space-between;padding:10px 16px;border-top:1px solid var(--border);font-size:13px;color:var(--text2)}
.rp-foot kbd{font:600 11px 'DM Sans',sans-serif;border:1px solid var(--border2);border-bottom-width:2px;border-radius:4px;padding:0 5px;background:var(--bg3)}

/* Dialogs and the side panel. */
.rp-dim{display:none;position:fixed;inset:0;z-index:1200;background:rgba(15,18,28,.5);align-items:center;justify-content:center;padding:24px}
.rp-dim.active{display:flex}
.rp-dialog{background:var(--bg2);border-radius:12px;box-shadow:0 24px 60px rgba(0,0,0,.28);display:flex;flex-direction:column;overflow:hidden;max-height:calc(100vh - 48px);max-height:calc(100dvh - 48px);width:100%}
.rp-dialog header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;padding:18px 22px;border-bottom:1px solid var(--border)}
.rp-dialog header h2{margin:0;font-size:18px}
.rp-dialog header p{margin:3px 0 0;font-size:13.5px;color:var(--text2)}
.rp-dialog .body{padding:18px 22px;overflow:auto;display:flex;flex-direction:column;gap:12px}
.rp-dialog footer{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 22px;border-top:1px solid var(--border)}
.rp-mcard{border:1px solid var(--border);border-radius:10px;padding:14px 16px}
.rp-mcard.on{border-color:var(--accent);background:var(--accent-light)}
.rp-mcard .top{display:flex;justify-content:space-between;align-items:center;gap:12px}
.rp-mcard .top b{font-size:15px}
.rp-mcard .top small{display:block;color:var(--text2);font-size:13px;font-weight:400}
.rp-mcard .opts{display:flex;flex-wrap:wrap;gap:6px 16px;margin-top:12px}
.rp-mcard .opts label{display:flex;align-items:center;gap:7px;font-size:13.5px;cursor:pointer}
.rp-mcard .opts input{accent-color:var(--text);width:15px;height:15px;margin:0}
.rp-mcard .rp-select{width:auto;height:32px;margin-top:10px}
.rp-preview{background:#dcdee4;flex:1;min-height:0;display:flex}
.rp-preview iframe{flex:1;border:0;width:100%;min-height:60vh;min-height:60dvh;background:#dcdee4}

.rp-dim.drawer{justify-content:flex-end;align-items:stretch;padding:0}
.rp-drawer{width:420px;max-width:100%;background:var(--bg2);height:100%;display:flex;flex-direction:column;box-shadow:-12px 0 40px rgba(0,0,0,.2)}
.rp-drawer header{padding:20px 22px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;gap:12px}
.rp-drawer header h2{margin:0;font-size:19px}
.rp-drawer header p{margin:3px 0 0;color:var(--text2);font-size:13.5px}
.rp-drawer .body{flex:1;overflow:auto;padding:18px 22px;display:flex;flex-direction:column;gap:18px}
.rp-drawer h3{margin:0 0 8px;font-size:11.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--text2)}
.rp-drawer dl{display:grid;grid-template-columns:auto 1fr;gap:6px 16px;margin:0;font-size:14px}
.rp-drawer dt{color:var(--text2)}
.rp-drawer dd{margin:0;text-align:right}
.rp-drawer ul{margin:0;padding-left:18px;font-size:14px;line-height:1.6}
.rp-drawer footer{padding:14px 22px;border-top:1px solid var(--border);display:flex;justify-content:flex-end}

.rp-toast{position:fixed;bottom:24px;right:24px;z-index:1300;background:var(--navy-dark);color:#fff;padding:12px 16px;border-radius:10px;font-size:13.5px;border-left:3px solid var(--accent);box-shadow:0 8px 24px rgba(0,0,0,.2);opacity:0;transform:translateY(8px);transition:opacity .2s,transform .2s;pointer-events:none}
.rp-toast.show{opacity:1;transform:none}

@media (max-width:900px){
  .rp.fit{overflow:auto;height:auto}
  .rp > .rp-tcard{flex:none;max-height:75vh;max-height:75dvh}
  .rp-filters .rp-field,.rp-filters .rp-field.wide{width:calc(50% - 5px)}
  .rp-search{flex:1 1 100%}
  .rp-tools .grow{display:none}
}
@media (max-width:520px){
  .rp-filters .rp-field,.rp-filters .rp-field.wide{width:100%}
  .rp-table th .sort,.rp-table td{padding-left:12px;padding-right:12px}
  .rp-edge{display:none}
}
@media (prefers-reduced-motion:reduce){.rp-scroll{scroll-behavior:auto}.rp-toast{transition:none}}
</style>
</head>
<body>

@include('capsa._sidebar')
@include('partials.sidebar-toggle')
@php
  use App\Support\CapsaReports;

  $icon = fn ($d) => '<svg class="i" viewBox="0 0 24 24" aria-hidden="true">'.$d.'</svg>';
  $I = [
      'search' => $icon('<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>'),
      'columns' => $icon('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16M15 4v16"/>'),
      'plus' => $icon('<path d="M12 5v14M5 12h14"/>'),
      'eye' => $icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>'),
      'download' => $icon('<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>'),
      'chev' => $icon('<path d="M6 9l6 6 6-6"/>'),
      'x' => $icon('<path d="M6 6l12 12M18 6L6 18"/>'),
      'left' => $icon('<path d="M15 6l-6 6 6 6"/>'),
      'right' => $icon('<path d="M9 6l6 6-6 6"/>'),
      'grip' => $icon('<path d="M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01"/>'),
      'file' => $icon('<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>'),
      'sheet' => $icon('<rect x="4" y="3" width="18" height="18" rx="2"/><path d="M4 9h16M4 15h16M10 3v18"/>'),
  ];

  $columns = $table['columns'];
  $first = $order[0];
  $groupOf = [];
  foreach ($groups as $g) {
      foreach ($g['keys'] as $k) {
          $groupOf[$k] = $g;
      }
  }

  // Every value is escaped here; the markup around it is the view's own.
  $cell = function ($v) {
      if (! is_array($v)) {
          return $v === CapsaReports::DASH || $v === '' ? '<span class="dash">'.e($v).'</span>' : e($v);
      }

      return match ($v[0]) {
          'b' => '<strong>'.e($v[1]).'</strong>',
          'chip' => '<span class="chip '.e($v[2]).'">'.e($v[1]).'</span>',
          'bar' => '<span class="barw"><span>'.e($v[1].' of '.$v[2]).'</span><span class="bar"><i style="width:'.($v[2] ? (int) round($v[1] / $v[2] * 100) : 0).'%"></i></span></span>',
          'slots' => $v[2] > 0
              ? '<span class="barw"><span>'.e($v[1].' of '.$v[2]).'</span><span class="bar"><i class="'.($v[1] >= $v[2] ? 'full' : '').'" style="width:'.min(100, (int) round($v[1] / $v[2] * 100)).'%"></i></span></span>'
              : e($v[1]).' <span class="dash">· no limit</span>',
          'lines' => e(implode(', ', $v[1])),
          default => e($v[1]),
      };
  };
  $cls = fn ($k) => trim(($columns[$k]['align'] === 'right' ? 'r ' : '').(isset($groupOf[$k]) && $groupOf[$k]['keys'][0] === $k && $groupOf[$k] !== $groups[0] ? 'gstart' : ''));
  $sortAttr = function ($v) {
      $s = CapsaReports::sortValue($v);

      return is_string($s) ? 's:'.$s : 'n:'.$s;
  };
  $hasRows = (bool) $table['rows'];
  $keepQuery = request()->except(['download', 'inline']);
  $link = fn (array $change) => route('capsa.reports.show', ['report' => $report] + array_filter(array_merge($keepQuery, $change), fn ($v) => $v !== null));
@endphp
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <nav class="crumbs" aria-label="Breadcrumb">
      <a href="{{ route('capsa.reports', $period) }}">Reports</a>
      <span class="sep">›</span>
      <span class="here" aria-current="page">{{ $name }}</span>
    </nav>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="sk" style="width:240px;height:34px;border-radius:8px;margin-bottom:16px"></div>
    <div class="sk" style="height:60px;border-radius:10px;margin-bottom:14px"></div>
    <div class="sk" style="height:40px;border-radius:8px;margin-bottom:14px"></div>
    <div class="sk" style="height:360px;border-radius:10px"></div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content rp fit">
    <div class="rp-top">
      <header class="rp-head">
        <h1>{{ $name }}</h1>
        <p>{{ $ctx['section'] !== '' && $report === 'progress' ? $name.' for '.$ctx['section'].'.' : $about }}</p>
      </header>

      <form method="GET" action="{{ route('capsa.reports.show', $report) }}" class="rp-filters" id="period" aria-label="Report period">
        @foreach (request()->except(['year', 'term', 'program', 'section', 'stage', 'fee', 'compare', 'download', 'inline']) as $k => $v)
          @if (is_string($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
        @endforeach
        <label class="rp-field">School year
          <select name="year" data-resets-term>
            @foreach ($ctx['years'] as $y)
              <option value="{{ $y }}" @selected($y === $ctx['year'])>{{ $y }}</option>
            @endforeach
          </select>
        </label>
        @if ($report !== 'experts')
          <label class="rp-field wide">Semester
            <select name="term">
              @foreach ($ctx['termOptions'] as $v => $label)
                <option value="{{ $v }}" @selected((string) $v === $ctx['term'])>{{ $label }}</option>
              @endforeach
            </select>
          </label>
          @if (count($ctx['programOptions']) > 1)
            <label class="rp-field">Program
              <select name="program">
                @foreach ($ctx['programOptions'] as $v => $label)
                  <option value="{{ $v }}" @selected((string) $v === $ctx['program'])>{{ $label }}</option>
                @endforeach
              </select>
            </label>
          @endif
          <label class="rp-field">Section
            <select name="section">
              @foreach ($ctx['sectionOptions'] as $v => $label)
                <option value="{{ $v }}" @selected((string) $v === $ctx['section'])>{{ $label }}</option>
              @endforeach
            </select>
          </label>
        @endif
        @foreach ($extra + (in_array('results', $merged, true) && ! isset($extra['stage']) ? ['stage' => $mergeExtra['stage']] : []) + (in_array('fees', $merged, true) && ! isset($extra['fee']) ? ['fee' => $mergeExtra['fee']] : []) as $field => [$label, $options])
          <label class="rp-field">{{ $label }}
            <select name="{{ $field }}">
              @foreach ($options as $v => $text)
                <option value="{{ $v }}" @selected($v === $ctx[$field])>{{ $text }}</option>
              @endforeach
            </select>
          </label>
        @endforeach
        <noscript><button type="submit" class="rp-btn">Apply</button></noscript>
      </form>
    </div>

    <div class="rp-tools" role="toolbar" aria-label="Table tools">
      <label class="rp-search">{!! $I['search'] !!}<input type="search" id="rp-q" placeholder="Search this table" value="{{ $search }}" aria-label="Search this table" @disabled(! $hasRows)></label>

      @if ($teamReport)
        <div class="rp-seg" role="group" aria-label="Rows">
          <a href="{{ $link(['rows' => null]) }}" aria-current="{{ $bySection ? 'false' : 'true' }}">One row per team</a>
          @if ($merged)
            <span aria-disabled="true" title="Remove the merged reports to see section totals">Section totals</span>
          @else
            <a href="{{ $link(['rows' => 'sections', 'sort' => null, 'order' => null, 'hide' => null]) }}" aria-current="{{ $bySection ? 'true' : 'false' }}">Section totals</a>
          @endif
        </div>
      @endif

      <span class="grow"></span>

      <div class="rp-pop-wrap">
        <button type="button" class="rp-btn" id="cols-btn" aria-expanded="false" aria-controls="cols-pop" @disabled(! $hasRows)>{!! $I['columns'] !!}Columns</button>
        <div class="rp-pop cols" id="cols-pop" hidden>
          <h3>{{ $name }} columns</h3>
          <div id="cols-list"></div>
          <div class="hint">Drag to reorder. The PDF and Excel file follow this order.</div>
          <div class="foot"><button type="button" id="cols-reset">Reset to default</button><button type="button" class="done" id="cols-done">Done</button></div>
        </div>
      </div>

      @if ($teamReport && ! $bySection)
        <button type="button" class="rp-btn {{ $merged ? 'on' : '' }}" id="merge-btn">{!! $I['plus'] !!}{{ $merged ? 'Merged reports ('.count($merged).')' : 'Merge report' }}</button>
      @endif

      <button type="button" class="rp-btn" id="preview-btn">{!! $I['eye'] !!}Preview PDF</button>

      <div class="rp-pop-wrap">
        <button type="button" class="rp-btn gold" id="dl-btn" aria-expanded="false" aria-controls="dl-pop">{!! $I['download'] !!}Download{!! $I['chev'] !!}</button>
        <div class="rp-pop rp-menu" id="dl-pop" hidden>
          <a href="#" data-dl="pdf">{!! $I['file'] !!}<span><b>PDF</b><small>With the college header, ready to print or submit</small></span></a>
          <a href="#" data-dl="xlsx">{!! $I['sheet'] !!}<span><b>Excel</b><small>The same columns, to sort or add to</small></span></a>
        </div>
      </div>
    </div>

    @if ($merged)
      <div class="rp-merged">
        <span>Merged with</span>
        @foreach (array_slice($groups, 1) as $i => $g)
          <span class="band-chip band-{{ $g['band'] }}">{{ $g['label'] }}<a href="{{ $link(['merge' => implode(',', array_diff($merged, [$merged[$i]])) ?: null]) }}" aria-label="Remove {{ $g['label'] }}">{!! $I['x'] !!}</a></span>
        @endforeach
      </div>
    @endif

    <section class="rp-card rp-tcard" aria-label="{{ $name }}">
      <button type="button" class="rp-edge l" id="edge-l" aria-label="Scroll columns left" hidden>{!! $I['left'] !!}</button>
      <button type="button" class="rp-edge r" id="edge-r" aria-label="Scroll columns right" hidden>{!! $I['right'] !!}</button>
      <div class="rp-scroll" id="rp-scroll" tabindex="0" aria-label="{{ $name }} table, scrolls sideways">
        @if ($hasRows)
          <table class="rp-table" id="rp-table">
            <thead>
              @if ($groups)
                <tr class="bands" id="bands"></tr>
              @endif
              <tr id="heads">
                @foreach ($order as $k)
                  <th scope="col" data-col="{{ $k }}" data-band="{{ $groupOf[$k]['band'] ?? '' }}" data-group="{{ $groupOf[$k]['label'] ?? '' }}" class="{{ $cls($k) }}" aria-sort="none" @if (in_array($k, $hidden, true)) hidden @endif>
                    <button type="button" class="sort">{{ $columns[$k]['label'] }}<span class="arr" aria-hidden="true"></span></button>
                  </th>
                @endforeach
              </tr>
            </thead>
            <tbody id="rows">
              @foreach ($table['rows'] as $row)
                <tr @if ($row['id'] && isset($details[$row['id']])) data-team="{{ $row['id'] }}" tabindex="-1" @endif>
                  @foreach ($order as $k)
                    <td data-col="{{ $k }}" data-v="{{ $sortAttr($row['cells'][$k] ?? '') }}" class="{{ $cls($k) }}" @if (in_array($k, $hidden, true)) hidden @endif>{!! $cell($row['cells'][$k] ?? '') !!}</td>
                  @endforeach
                </tr>
              @endforeach
            </tbody>
            @if ($table['total'])
              <tfoot>
                <tr>
                  @foreach ($order as $k)
                    <td data-col="{{ $k }}" class="{{ $cls($k) }}" @if (in_array($k, $hidden, true)) hidden @endif>{!! $cell($table['total'][$k] ?? '') !!}</td>
                  @endforeach
                </tr>
              </tfoot>
            @endif
          </table>
          <div class="rp-empty" id="no-match" hidden><b>No rows match your search</b>Try another word, or clear the search box.</div>
        @else
          <div class="rp-empty"><b>{{ $table['empty'] }}</b>Try another school year, semester or section above.</div>
        @endif
      </div>
      <div class="rp-foot">
        <span id="rp-summary">{{ $table['summary'] }}</span>
        @if ($hasRows)
          <span id="rp-scroll-hint" hidden>More columns to the side: use the ‹ › buttons or your <kbd>←</kbd> <kbd>→</kbd> keys. {{ $columns[$first]['label'] }} stays pinned.</span>
        @endif
      </div>
    </section>
  </div>
  </div>
</div>

{{-- Merge --}}
@if ($teamReport && ! $bySection)
<div class="rp-dim" id="merge-dialog" onclick="if (event.target === this) closeDim(this)">
  <div class="rp-dialog" role="dialog" aria-modal="true" aria-labelledby="merge-title" style="max-width:640px">
    <header>
      <div><h2 id="merge-title">Merge with another report</h2><p>Add another report's columns beside each team. The table, the PDF and the Excel file all get them.</p></div>
      <button type="button" class="rp-btn ghost icon" data-close aria-label="Close" onclick="closeDim(this.closest('.rp-dim'))">{!! $I['x'] !!}</button>
    </header>
    <div class="body">
      @foreach ($mergeable as $m)
        @php $isOn = in_array($m['key'], $merged, true); @endphp
        <div class="rp-mcard {{ $isOn ? 'on' : '' }}" data-merge="{{ $m['key'] }}">
          <div class="top">
            <div><b>{{ $m['name'] }}</b><small data-merge-sub>{{ $isOn ? 'Merged' : 'Adds: '.implode(', ', array_map('strtolower', $m['columns'])) }}</small></div>
            <button type="button" class="rp-btn {{ $isOn ? '' : 'gold' }}" data-merge-toggle>{{ $isOn ? 'Remove' : 'Merge' }}</button>
          </div>
          <div class="opts" @if (! $isOn) hidden @endif>
            @foreach ($m['columns'] as $ck => $clabel)
              <label><input type="checkbox" value="{{ $m['key'] }}.{{ $ck }}" @checked(! in_array($m['key'].'.'.$ck, $hidden, true))>{{ $clabel }}</label>
            @endforeach
          </div>
          @if ($m['key'] === 'results' && $report !== 'results')
            <select class="rp-select" data-merge-field="stage" aria-label="Defense" @if (! $isOn) hidden @endif>
              @foreach ($mergeExtra['stage'][1] as $v => $t)<option value="{{ $v }}" @selected($v === $ctx['stage'])>{{ $t }}</option>@endforeach
            </select>
          @elseif ($m['key'] === 'fees' && $report !== 'fees')
            <select class="rp-select" data-merge-field="fee" aria-label="Fee" @if (! $isOn) hidden @endif>
              @foreach ($mergeExtra['fee'][1] as $v => $t)<option value="{{ $v }}" @selected($v === $ctx['fee'])>{{ $t }}</option>@endforeach
            </select>
          @endif
        </div>
      @endforeach
    </div>
    <footer>
      <span style="font-size:13px;color:var(--text2)" id="merge-count"></span>
      <div style="display:flex;gap:8px"><button type="button" class="rp-btn" onclick="closeDim(this.closest('.rp-dim'))">Cancel</button><button type="button" class="rp-btn gold" id="merge-apply">Apply</button></div>
    </footer>
  </div>
</div>
@endif

{{-- Preview PDF --}}
<div class="rp-dim" id="preview-dialog" onclick="if (event.target === this) closeDim(this)">
  <div class="rp-dialog" role="dialog" aria-modal="true" aria-labelledby="preview-title" style="max-width:1180px;height:calc(100vh - 48px)">
    <header>
      <div><h2 id="preview-title">Preview PDF · {{ $name }}</h2><p>Letter, landscape. This is exactly the file Download PDF gives you.</p></div>
      <div style="display:flex;gap:8px">
        <button type="button" class="rp-btn" data-close onclick="closeDim(this.closest('.rp-dim'))">Close</button>
        <a href="#" class="rp-btn gold" data-dl="pdf">{!! $I['download'] !!}Download PDF</a>
      </div>
    </header>
    <div class="rp-preview"><iframe id="preview-frame" title="PDF preview of {{ $name }}"></iframe></div>
  </div>
</div>

{{-- A team, opened from its row --}}
<div class="rp-dim drawer" id="team-drawer" onclick="if (event.target === this) closeDim(this)">
  <aside class="rp-drawer" role="dialog" aria-modal="true" aria-labelledby="d-name">
    <header>
      <div><h2 id="d-name"></h2><p id="d-sub"></p></div>
      <button type="button" class="rp-btn ghost icon" data-close aria-label="Close" onclick="closeDim(this.closest('.rp-dim'))">{!! $I['x'] !!}</button>
    </header>
    <div class="body">
      <section><h3>Project title</h3><div id="d-title"></div></section>
      <section><h3>Progress</h3><dl id="d-progress"></dl></section>
      <section id="d-overdue-wrap"><h3>Overdue items</h3><ul id="d-overdue"></ul></section>
      <section><h3>Members</h3><ul id="d-members"></ul></section>
      <section><h3>Experts</h3><dl id="d-experts"></dl></section>
    </div>
    <footer><a class="rp-btn dark" id="d-open" href="#">Open team</a></footer>
  </aside>
</div>

<div class="rp-toast" id="rp-toast" role="status" aria-live="polite"></div>

@include('capsa.partials.skeleton-toggle', ['revealDisplay' => 'flex'])
<script>
var REPORT = {
  first: @json($first),
  defaultOrder: @json(array_keys($columns)),
  labels: @json(collect($columns)->map(fn ($c) => $c['label'])),
  groupOf: @json(collect($groupOf)->map(fn ($g) => $g['label'])),
  details: @json((object) $details),
  merged: @json($merged),
  baseUrl: @json(route('capsa.reports.show', $report)),
};

function closeDim(el) { el.classList.remove('active'); document.body.style.overflow = ''; }
function openDim(el) { el.classList.add('active'); document.body.style.overflow = 'hidden'; }

(function () {
  var params = new URLSearchParams(location.search);
  var table = document.getElementById('rp-table');
  var scroller = document.getElementById('rp-scroll');

  function toast(text) {
    var t = document.getElementById('rp-toast');
    t.textContent = text;
    t.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { t.classList.remove('show'); }, 4000);
  }

  // Everything the table shows lives in the address, so the period filters,
  // Preview and Download all carry the same columns, order, sort and search.
  function setParam(key, value) {
    if (value === null || value === '' || value === undefined) params.delete(key); else params.set(key, value);
    history.replaceState(null, '', location.pathname + (params.toString() ? '?' + params.toString() : ''));
    document.querySelectorAll('#period input[type="hidden"][name="' + key + '"]').forEach(function (i) { i.remove(); });
    if (value !== null && value !== '' && value !== undefined) {
      var input = document.createElement('input');
      input.type = 'hidden'; input.name = key; input.value = value;
      document.getElementById('period').appendChild(input);
    }
  }
  function urlWith(extra) {
    var p = new URLSearchParams(params.toString());
    Object.keys(extra).forEach(function (k) { p.set(k, extra[k]); });
    return REPORT.baseUrl + '?' + p.toString();
  }

  // ---- period filters apply on change
  var period = document.getElementById('period');
  period.querySelectorAll('select').forEach(function (select) {
    select.addEventListener('change', function () {
      if (select.hasAttribute('data-resets-term')) { var t = period.querySelector('[name="term"]'); if (t) t.disabled = true; }
      period.submit();
    });
  });

  // ---- popovers
  function popover(btnId, popId) {
    var btn = document.getElementById(btnId), pop = document.getElementById(popId);
    if (!btn || !pop) return;
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = pop.hidden;
      document.querySelectorAll('.rp-pop').forEach(function (p) { p.hidden = true; });
      document.querySelectorAll('[aria-controls]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
      pop.hidden = !open;
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    pop.addEventListener('click', function (e) { e.stopPropagation(); });
  }
  popover('cols-btn', 'cols-pop');
  popover('dl-btn', 'dl-pop');
  document.addEventListener('click', function () {
    document.querySelectorAll('.rp-pop').forEach(function (p) { p.hidden = true; });
  });
  // Escape closes the popover first, then the dialog or side panel on top.
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var pop = Array.prototype.find.call(document.querySelectorAll('.rp-pop'), function (p) { return !p.hidden; });
    if (pop) { pop.hidden = true; return; }
    var dim = document.querySelector('.rp-dim.active');
    if (dim) { closeDim(dim); e.preventDefault(); }
  });

  // ---- downloads and the preview
  document.querySelectorAll('[data-dl]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      location.href = urlWith({ download: a.dataset.dl });
      document.getElementById('dl-pop').hidden = true;
      toast(REPORT_NAME + (a.dataset.dl === 'pdf' ? '.pdf' : '.xlsx') + ' is downloading.');
    });
  });
  var REPORT_NAME = @json($name);
  document.getElementById('preview-btn').addEventListener('click', function () {
    document.getElementById('preview-frame').src = urlWith({ download: 'pdf', inline: '1' });
    openDim(document.getElementById('preview-dialog'));
  });

  if (!table) return;

  var heads = document.getElementById('heads');
  var body = document.getElementById('rows');
  var allRows = Array.prototype.slice.call(body.rows);

  // ---- columns: order and visibility
  function currentOrder() { return Array.prototype.map.call(heads.cells, function (th) { return th.dataset.col; }); }
  function hiddenCols() { return Array.prototype.filter.call(heads.cells, function (th) { return th.hidden; }).map(function (th) { return th.dataset.col; }); }

  function applyColumns(order, hidden) {
    Array.prototype.forEach.call(table.querySelectorAll('thead tr#heads, tbody tr, tfoot tr'), function (tr) {
      var byCol = {};
      Array.prototype.forEach.call(tr.cells, function (c) { byCol[c.dataset.col] = c; });
      order.forEach(function (k) { if (byCol[k]) { byCol[k].hidden = hidden.indexOf(k) !== -1; tr.appendChild(byCol[k]); } });
    });
    paintBands();
    var isDefault = order.join(',') === REPORT.defaultOrder.join(',');
    setParam('order', isDefault ? null : order.join(','));
    setParam('hide', hidden.length ? hidden.join(',') : null);
    measure();
  }

  // Merged reports get a band over their columns, one per run of neighbours.
  function paintBands() {
    var bands = document.getElementById('bands');
    if (!bands) return;
    bands.innerHTML = '';
    var last = null;
    Array.prototype.forEach.call(heads.cells, function (th, i) {
      th.classList.remove('gstart');
      if (th.hidden) return;
      // The pinned first column is a band of its own; the rest of its report carries on unlabelled.
      if (last && !last.dataset.pinned && last.dataset.group === th.dataset.group) { last.colSpan += 1; return; }
      var b = document.createElement('th');
      var starts = !last || last.dataset.group !== th.dataset.group;
      b.dataset.group = th.dataset.group;
      b.className = 'band-' + th.dataset.band;
      b.textContent = starts ? th.dataset.group : '';
      if (last && starts) { b.classList.add('gstart'); th.classList.add('gstart'); }
      if (i === 0) b.dataset.pinned = '1';
      bands.appendChild(b);
      last = b;
    });
    // Starting a band draws the same divider down its column.
    Array.prototype.forEach.call(table.querySelectorAll('tbody tr, tfoot tr'), function (tr) {
      Array.prototype.forEach.call(tr.cells, function (c, i) { c.classList.toggle('gstart', heads.cells[i].classList.contains('gstart')); });
    });
    table.style.setProperty('--band-h', '30px');
  }
  paintBands();

  function buildColumnList() {
    var list = document.getElementById('cols-list');
    list.innerHTML = '';
    var hidden = hiddenCols();
    currentOrder().forEach(function (k) {
      var locked = k === REPORT.first;
      var row = document.createElement('label');
      row.className = 'rp-col' + (locked ? ' locked' : '');
      row.dataset.col = k;
      row.draggable = !locked;
      row.innerHTML = '<span class="grip">' + @json($I['grip']) + '</span>';
      var box = document.createElement('input');
      box.type = 'checkbox';
      box.checked = hidden.indexOf(k) === -1;
      box.disabled = locked;
      box.addEventListener('change', function () {
        var h = hiddenCols().filter(function (x) { return x !== k; });
        if (!box.checked) h.push(k);
        applyColumns(currentOrder(), h);
      });
      var lbl = document.createElement('span');
      lbl.className = 'lbl';
      lbl.textContent = REPORT.labels[k];
      if (REPORT.merged.length && REPORT.groupOf[k] && REPORT.groupOf[k] !== REPORT.groupOf[REPORT.first]) {
        var small = document.createElement('small');
        small.textContent = ' · ' + REPORT.groupOf[k];
        lbl.appendChild(small);
      }
      row.appendChild(box);
      row.appendChild(lbl);
      if (locked) {
        var lock = document.createElement('span');
        lock.className = 'lock';
        lock.textContent = 'Always shown';
        row.appendChild(lock);
      }
      list.appendChild(row);
    });
  }

  // Drag a column in the list to move it; the table follows on drop.
  var dragging = null;
  var colsList = document.getElementById('cols-list');
  colsList.addEventListener('dragstart', function (e) {
    dragging = e.target.closest('.rp-col');
    if (!dragging) return;
    dragging.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
  });
  colsList.addEventListener('dragover', function (e) {
    if (!dragging) return;
    e.preventDefault();
    var over = e.target.closest('.rp-col');
    if (!over || over === dragging || over.classList.contains('locked')) return;
    var box = over.getBoundingClientRect();
    colsList.insertBefore(dragging, e.clientY > box.top + box.height / 2 ? over.nextSibling : over);
  });
  colsList.addEventListener('dragend', function () {
    if (!dragging) return;
    dragging.classList.remove('dragging');
    dragging = null;
    var order = Array.prototype.map.call(colsList.children, function (r) { return r.dataset.col; });
    applyColumns(order, hiddenCols());
  });
  // Keyboard: Alt+↑ / Alt+↓ on a column's checkbox moves it.
  colsList.addEventListener('keydown', function (e) {
    if (!e.altKey || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) return;
    var row = e.target.closest('.rp-col');
    if (!row || row.classList.contains('locked')) return;
    e.preventDefault();
    var sib = e.key === 'ArrowUp' ? row.previousElementSibling : row.nextElementSibling;
    if (!sib || sib.classList.contains('locked')) return;
    colsList.insertBefore(row, e.key === 'ArrowUp' ? sib : sib.nextSibling);
    applyColumns(Array.prototype.map.call(colsList.children, function (r) { return r.dataset.col; }), hiddenCols());
    row.querySelector('input').focus();
  });
  document.getElementById('cols-btn').addEventListener('click', buildColumnList);
  document.getElementById('cols-reset').addEventListener('click', function () { applyColumns(REPORT.defaultOrder, []); buildColumnList(); });
  document.getElementById('cols-done').addEventListener('click', function () { document.getElementById('cols-pop').hidden = true; });

  // ---- sorting
  function valueOf(td) {
    var v = td ? td.dataset.v : 's:';
    return v.slice(0, 2) === 'n:' ? parseFloat(v.slice(2)) : v.slice(2);
  }
  function sortBy(key, dir) {
    Array.prototype.forEach.call(heads.cells, function (th) { th.setAttribute('aria-sort', th.dataset.col === key ? (dir === 'desc' ? 'descending' : 'ascending') : 'none'); });
    var idx = function (tr) { return Array.prototype.find.call(tr.cells, function (c) { return c.dataset.col === key; }); };
    allRows.slice().sort(function (a, b) {
      var x = valueOf(idx(a)), y = valueOf(idx(b));
      if ((x === '') !== (y === '')) return x === '' ? 1 : -1;
      var c = typeof x === 'number' && typeof y === 'number' ? x - y : String(x).localeCompare(String(y), undefined, { numeric: true });
      return dir === 'desc' ? -c : c;
    }).forEach(function (tr) { body.appendChild(tr); });
    setParam('sort', key);
    setParam('dir', dir === 'desc' ? 'desc' : null);
  }
  heads.addEventListener('click', function (e) {
    var th = e.target.closest('th');
    if (!th) return;
    var now = th.getAttribute('aria-sort');
    sortBy(th.dataset.col, now === 'ascending' ? 'desc' : 'asc');
  });
  if (params.get('sort') && currentOrder().indexOf(params.get('sort')) !== -1) sortBy(params.get('sort'), params.get('dir'));

  // ---- search
  var q = document.getElementById('rp-q');
  var summary = document.getElementById('rp-summary');
  var summaryText = summary.textContent;
  function search() {
    var term = q.value.trim().toLowerCase();
    var shown = 0;
    allRows.forEach(function (tr) {
      var hit = !term || tr.textContent.toLowerCase().indexOf(term) !== -1;
      tr.hidden = !hit;
      if (hit) shown++;
    });
    document.getElementById('no-match').hidden = shown > 0;
    var foot = table.tFoot;
    if (foot) foot.hidden = !!term;
    summary.textContent = term ? shown + ' of ' + allRows.length + ' rows match "' + q.value.trim() + '"' : summaryText;
    setParam('q', term ? q.value.trim() : null);
  }
  q.addEventListener('input', search);
  if (q.value) search();

  // ---- sideways scrolling: the ‹ › buttons and the arrow keys
  var edgeL = document.getElementById('edge-l'), edgeR = document.getElementById('edge-r');
  var hint = document.getElementById('rp-scroll-hint');
  function measure() {
    var canL = scroller.scrollLeft > 2;
    var canR = scroller.scrollLeft + scroller.clientWidth < scroller.scrollWidth - 2;
    edgeL.hidden = !canL;
    edgeR.hidden = !canR;
    // Just right of the pinned column, so it never covers the names in it.
    edgeL.style.left = (heads.cells[0].getBoundingClientRect().width + 8) + 'px';
    hint.hidden = !(canL || canR);
  }
  function step(dir) { scroller.scrollBy({ left: dir * Math.max(240, Math.round(scroller.clientWidth * 0.6)) }); }
  edgeL.addEventListener('click', function () { step(-1); });
  edgeR.addEventListener('click', function () { step(1); });
  scroller.addEventListener('scroll', measure, { passive: true });
  window.addEventListener('resize', measure);
  // The page is revealed after its loading placeholder, so measure again once it has a size.
  if (window.ResizeObserver) new ResizeObserver(measure).observe(scroller);
  measure();
  document.addEventListener('keydown', function (e) {
    var t = e.target;
    if (e.altKey || e.ctrlKey || e.metaKey || document.querySelector('.rp-dim.active')) return;
    if (t.closest && t.closest('input, select, textarea, [contenteditable], .rp-pop')) return;
    if (e.key === 'ArrowRight') { step(1); e.preventDefault(); }
    else if (e.key === 'ArrowLeft') { step(-1); e.preventDefault(); }
    else if (e.key === 'Home') { scroller.scrollTo({ left: 0 }); e.preventDefault(); }
    else if (e.key === 'End') { scroller.scrollTo({ left: scroller.scrollWidth }); e.preventDefault(); }
  });

  // ---- a team's side panel
  var drawer = document.getElementById('team-drawer');
  function fillList(el, items, empty) {
    el.innerHTML = '';
    if (!items.length) { var li = document.createElement('li'); li.textContent = empty; li.style.listStyle = 'none'; li.style.marginLeft = '-18px'; li.style.color = 'var(--text3)'; el.appendChild(li); return; }
    items.forEach(function (text) { var li = document.createElement('li'); li.textContent = text; el.appendChild(li); });
  }
  function fillPairs(el, pairs) {
    el.innerHTML = '';
    pairs.forEach(function (p) {
      var dt = document.createElement('dt'); dt.textContent = p[0];
      var dd = document.createElement('dd'); dd.textContent = p[1];
      el.appendChild(dt); el.appendChild(dd);
    });
  }
  function openTeam(id) {
    var d = REPORT.details[id];
    if (!d) return;
    document.getElementById('d-name').textContent = d.name;
    document.getElementById('d-sub').textContent = d.section + (d.verified ? '' : ' · not verified yet');
    document.getElementById('d-title').textContent = d.title || 'No title yet';
    fillPairs(document.getElementById('d-progress'), [
      ['Current milestone', d.milestone || '—'],
      ['Requirements done', d.done + ' of ' + d.total],
      ['Overdue', String(d.overdue.length)],
    ]);
    document.getElementById('d-overdue-wrap').hidden = !d.overdue.length;
    fillList(document.getElementById('d-overdue'), d.overdue, '');
    fillList(document.getElementById('d-members'), d.members, 'No members on record');
    fillPairs(document.getElementById('d-experts'), d.experts.map(function (e) { return [e.role, e.name || 'None yet']; }));
    document.getElementById('d-open').href = d.url;
    openDim(drawer);
    drawer.querySelector('[data-close]').focus();
  }
  body.addEventListener('click', function (e) {
    var tr = e.target.closest('tr[data-team]');
    if (tr && !e.target.closest('a, button')) openTeam(tr.dataset.team);
  });
  body.addEventListener('keydown', function (e) {
    var tr = e.target.closest('tr[data-team]');
    if (tr && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); openTeam(tr.dataset.team); }
  });
  allRows.forEach(function (tr) { if (tr.dataset.team) tr.tabIndex = 0; });

  // ---- merge
  var mergeDialog = document.getElementById('merge-dialog');
  if (mergeDialog) {
    document.getElementById('merge-btn').addEventListener('click', function () { openDim(mergeDialog); });
    var cards = Array.prototype.slice.call(mergeDialog.querySelectorAll('[data-merge]'));
    function countMerged() {
      var n = cards.filter(function (c) { return c.classList.contains('on'); }).length;
      document.getElementById('merge-count').textContent = n ? n + ' ' + (n === 1 ? 'report' : 'reports') + ' to merge' : 'Nothing merged';
    }
    cards.forEach(function (card) {
      card.querySelector('[data-merge-toggle]').addEventListener('click', function () {
        var on = !card.classList.contains('on');
        card.classList.toggle('on', on);
        card.querySelector('.opts').hidden = !on;
        var sel = card.querySelector('[data-merge-field]');
        if (sel) sel.hidden = !on;
        this.textContent = on ? 'Remove' : 'Merge';
        this.classList.toggle('gold', !on);
        countMerged();
      });
    });
    countMerged();
    document.getElementById('merge-apply').addEventListener('click', function () {
      var on = cards.filter(function (c) { return c.classList.contains('on'); });
      var p = new URLSearchParams(params.toString());
      p.delete('rows');
      if (on.length) p.set('merge', on.map(function (c) { return c.dataset.merge; }).join(',')); else p.delete('merge');
      // Keep the table's own hidden columns; the merged ones follow their ticks.
      var hide = (p.get('hide') || '').split(',').filter(function (k) { return k && k.indexOf('.') === -1; });
      on.forEach(function (c) {
        c.querySelectorAll('.opts input').forEach(function (i) { if (!i.checked) hide.push(i.value); });
        var sel = c.querySelector('[data-merge-field]');
        if (sel) p.set(sel.dataset.mergeField, sel.value);
      });
      if (hide.length) p.set('hide', hide.join(',')); else p.delete('hide');
      p.delete('order');
      location.href = REPORT.baseUrl + '?' + p.toString();
    });
  }
})();
</script>
@include('partials.escape-closes-modals')
</body>
</html>
