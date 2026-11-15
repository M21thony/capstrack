<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Analytics - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
<style nonce="{{ Vite::cspNonce() }}">
/* ── Analytics ──
   Colours are roles, not hex scattered through the markup. The chart colours
   come from one validated set: the seven project tags in a fixed order, a
   single blue ramp for anything ordered (the four stages, the heat cells),
   and the status colours for where a team stands - which mean the same thing
   here as they do everywhere else, and never carry it alone: every one is
   paired with an icon and a label. */
.an{
  --an-ink:#0b0b0b;--an-ink2:#52514e;--an-muted:#6d6b65;--an-grid:#e1e0d9;--an-axis:#c3c2b7;--an-surface:#fff;
  --st-passed:#0ca30c;--st-revision:#7fcf7f;--st-redefense:#fab219;--st-awaiting:#86b6ef;--st-missed:#d03b3b;--st-idle:#dcdad2;
  --ord-1:#86b6ef;--ord-2:#5598e7;--ord-3:#2a78d6;--ord-4:#1c5cab;
  --heat-0:#f0efec;--heat-1:#cde2fb;--heat-2:#9ec5f4;--heat-3:#6da7ec;--heat-4:#2a78d6;--heat-5:#184f95;
  display:flex;flex-direction:column;gap:16px;
}
.an *{min-width:0}
.an-fig[data-view="chart"] .an-twin,
.an-fig[data-view="table"] .an-chart{display:none}

/* Filters: one row above everything they scope. */
.an-filters{display:flex;flex-direction:column;gap:12px;background:#fff;border:1px solid var(--border);border-radius:14px;padding:14px 18px;box-shadow:0 1px 4px rgba(0,0,0,0.05)}
.an-filter-row{display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px 14px}
.an-field{display:flex;flex-direction:column;gap:4px}
.an-field label{font-size:10.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text3)}
.an-select{font-family:'DM Sans','DM Sans Fallback',sans-serif;font-size:13px;font-weight:500;padding:8px 32px 8px 12px;border-radius:9px;border:1px solid var(--border2);background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23656b7a' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") no-repeat right 10px center;color:var(--text);appearance:none;-webkit-appearance:none;cursor:pointer;min-width:150px}
.an-select:hover{border-color:var(--accent)}
.an-select:focus-visible{outline:2px solid var(--accent);outline-offset:1px}
.an-reset{font-size:12.5px;font-weight:600;color:var(--accent3);text-decoration:none;padding:9px 4px}
.an-reset:hover{text-decoration:underline}
.an-chips{display:flex;flex-wrap:wrap;align-items:center;gap:7px}
.an-chips-label{font-size:10.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text3);margin-right:4px}
.an-chip{display:inline-flex;align-items:center;gap:7px;padding:6px 12px 6px 10px;border-radius:999px;border:1px solid var(--border2);background:#fff;color:var(--text2);font-size:12.5px;font-weight:600;text-decoration:none;transition:background .12s,border-color .12s}
.an-chip:hover{background:var(--bg3);border-color:var(--accent)}
.an-chip.is-active{background:var(--navy-dark);border-color:var(--navy-dark);color:#fff}
.an-chip:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
.an-chip-n{font-size:11px;font-weight:600;color:var(--text3);font-variant-numeric:tabular-nums}
.an-chip.is-active .an-chip-n{color:rgba(255,255,255,.7)}
.an-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0;box-shadow:0 0 0 2px #fff}
.an-chip.is-active .an-dot{box-shadow:0 0 0 2px var(--navy-dark)}
.an-scope{font-size:12px;color:var(--text3)}
.an-scope b{color:var(--text2);font-weight:600}

/* Headline numbers. The stat-chip look is the portal's own; only the
   pass-rate tile's meter is new. */
.an-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.an-kpis .stat-chip{gap:4px}
.an-kpis .stat-n{font-size:30px}
.an-kpi-meter{height:6px;border-radius:3px;background:var(--bg4);margin-top:8px;overflow:hidden}
.an-kpi-meter i{display:block;height:100%;border-radius:3px;background:var(--ord-3)}

/* Cards */
.an-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:22px 24px;box-shadow:0 1px 4px rgba(0,0,0,0.05)}
.an-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:16px}
.an-card-title{font-size:15px;font-weight:700;color:var(--text);letter-spacing:-.01em}
.an-card-sub{font-size:12.5px;color:var(--text3);margin-top:3px;line-height:1.5;max-width:64ch}
.an-toggle{font-family:'DM Sans','DM Sans Fallback',sans-serif;font-size:12px;font-weight:600;color:var(--text2);background:#fff;border:1px solid var(--border2);border-radius:8px;padding:6px 12px;cursor:pointer;white-space:nowrap}
.an-toggle:hover{border-color:var(--accent);color:var(--text)}
.an-toggle:focus-visible{outline:2px solid var(--accent);outline-offset:2px}

/* Legends */
.an-legend{display:flex;flex-wrap:wrap;gap:6px 16px;margin-bottom:14px}
.an-key{display:inline-flex;align-items:center;gap:7px;font-size:12px;color:var(--text2);font-weight:500}
.an-key i{width:12px;height:12px;border-radius:3px;flex-shrink:0}
.an-key svg{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round;color:var(--text3)}

/* Where every team stands: one stacked bar per stage. */
.an-stages{display:flex;flex-direction:column;gap:14px}
.an-stage{display:grid;grid-template-columns:170px minmax(0,1fr) 150px;align-items:center;gap:8px 18px}
.an-stage-name{display:flex;flex-direction:column;gap:1px}
.an-stage-name b{font-size:13.5px;font-weight:600;color:var(--text)}
.an-stage-name span{font-size:11.5px;color:var(--text3)}
.an-bar{display:flex;gap:2px;height:24px}
.an-bar.is-empty{background:var(--bg3);border-radius:4px;align-items:center;justify-content:center;font-size:11.5px;color:var(--text3)}
.an-seg{display:flex;align-items:center;justify-content:center;font-size:11.5px;font-weight:700;color:var(--an-ink);font-variant-numeric:tabular-nums;cursor:default;transition:filter .12s;min-width:6px}
.an-seg:first-child{border-radius:4px 0 0 4px}
.an-seg:last-child{border-radius:0 4px 4px 0}
.an-seg:only-child{border-radius:4px}
.an-seg:hover,.an-seg:focus-visible{filter:brightness(.93);outline:none}
.an-seg:focus-visible{box-shadow:0 0 0 2px var(--navy-dark) inset}
.an-seg.on-dark{color:#fff}
.an-stage-rate{display:flex;flex-direction:column;align-items:flex-end;text-align:right}
.an-stage-rate b{font-size:22px;font-weight:700;color:var(--text);line-height:1.1}
.an-stage-rate span{font-size:11.5px;color:var(--text3);line-height:1.35}

/* Heat matrix: tags down, stages across. */
.an-scroll{overflow-x:auto;margin:0 -4px;padding:0 4px}
.an-heat{width:100%;border-collapse:separate;border-spacing:0 3px;font-size:13px;min-width:640px}
.an-heat th{font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--text3);text-align:center;padding:0 4px 6px;white-space:nowrap}
.an-heat th:first-child{text-align:left;padding-left:2px}
.an-heat th:nth-child(2){text-align:left;padding-left:12px}
.an-heat td{padding:0}
.an-heat .an-tag-cell{padding:6px 12px 6px 2px;white-space:nowrap}
.an-tag-link{display:inline-flex;align-items:center;gap:9px;color:var(--text);font-weight:600;text-decoration:none;font-size:13px}
.an-tag-link:hover{text-decoration:underline}
.an-tag-link:focus-visible{outline:2px solid var(--accent);outline-offset:2px;border-radius:4px}
.an-teams-cell{padding:0 12px;width:110px}
.an-teams-cell b{display:block;font-size:13px;font-weight:600;color:var(--text);font-variant-numeric:tabular-nums;margin-bottom:3px}
.an-teams-bar{height:4px;background:var(--bg4);border-radius:2px;overflow:hidden}
.an-teams-bar i{display:block;height:100%;background:var(--ord-3);border-radius:2px}
.an-hm{height:46px;text-align:center;vertical-align:middle;border-radius:6px;font-variant-numeric:tabular-nums;color:var(--an-ink);box-shadow:0 0 0 2px #fff inset;cursor:default;transition:filter .12s}
.an-hm:hover,.an-hm:focus-visible{filter:brightness(.94);outline:none}
.an-hm b{display:block;font-size:14px;font-weight:700;line-height:1.15}
.an-hm small{display:block;font-size:10.5px;opacity:.85;line-height:1.3}
.an-hm.h0{background:var(--heat-0);color:var(--text3)}
.an-hm.h1{background:var(--heat-1)}.an-hm.h2{background:var(--heat-2)}.an-hm.h3{background:var(--heat-3)}
.an-hm.h4{background:var(--heat-4);color:#fff}.an-hm.h5{background:var(--heat-5);color:#fff}
.an-scale{display:flex;align-items:center;gap:8px;font-size:11.5px;color:var(--text3);margin-top:12px;flex-wrap:wrap}
.an-scale i{display:inline-block;width:26px;height:10px;border-radius:2px}
.an-swipe{display:none;font-size:11.5px;color:var(--text3);margin-top:8px}
.an-note{font-size:12px;color:var(--text3);line-height:1.5;margin-top:12px}

/* School years: grouped columns. */
.an-years{display:flex;gap:10px;overflow-x:auto;padding:8px 2px 2px}
.an-yaxis{display:flex;flex-direction:column;justify-content:space-between;height:200px;font-size:10.5px;color:var(--an-muted);text-align:right;font-variant-numeric:tabular-nums;padding-right:6px;flex-shrink:0;width:34px}
.an-plot{position:relative;display:flex;justify-content:space-evenly;gap:26px;min-width:0;flex:1}
.an-plot::before,.an-plot::after,.an-plot i{content:"";position:absolute;left:0;right:0;height:1px;background:var(--an-grid)}
.an-plot::before{top:0}.an-plot::after{bottom:0;background:var(--an-axis)}
.an-plot i{top:50%}
.an-grp{position:relative;display:flex;flex-direction:column;align-items:center;flex:0 1 240px;min-width:112px;z-index:1}
.an-grp-bars{height:200px;display:flex;align-items:flex-end;justify-content:center;gap:5px;width:100%}
.an-col{position:relative;width:22px;border-radius:4px 4px 0 0;min-height:3px;cursor:default;transition:filter .12s}
.an-col:hover,.an-col:focus-visible{filter:brightness(.92);outline:none}
.an-col:focus-visible{box-shadow:0 0 0 2px var(--navy-dark)}
.an-col.is-none{background:transparent !important;border:1px dashed var(--an-axis);border-bottom:none;height:6px !important}
.an-grp-x{margin-top:8px;font-size:12px;font-weight:600;color:var(--text2);white-space:nowrap}

/* Archive stacked bars. */
.an-archive{display:flex;flex-direction:column;gap:9px}
.an-arow{display:grid;grid-template-columns:86px minmax(0,1fr) 92px;align-items:center;gap:12px}
.an-arow-y{font-size:12.5px;font-weight:600;color:var(--text2);font-variant-numeric:tabular-nums;white-space:nowrap}
.an-arow-n{font-size:12px;color:var(--text3);text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.an-arow .an-bar{height:18px}

/* Tables */
.an-tbl{width:100%;border-collapse:collapse;font-size:13px}
.an-tbl th{font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--text3);text-align:left;padding:8px 10px;border-bottom:1px solid var(--border);white-space:nowrap}
.an-tbl td{padding:10px;border-bottom:1px solid var(--border);color:var(--text2);vertical-align:middle}
.an-tbl tbody tr:last-child td{border-bottom:none}
.an-tbl tbody tr:hover td{background:var(--bg3)}
.an-tbl .num{text-align:right;font-variant-numeric:tabular-nums}
.an-tbl th.num{text-align:right}
.an-team b{display:block;font-size:13px;font-weight:600;color:var(--text)}
.an-tbl td:nth-child(2){white-space:nowrap}
.an-team span{display:block;font-size:11.5px;color:var(--text3);margin-top:1px;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.an-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 9px 3px 7px;border-radius:999px;font-size:11.5px;font-weight:600;white-space:nowrap}
.an-pill svg{width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:2.6;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
.an-pill.st-passed{background:rgba(12,163,12,.12);color:#006300}
.an-pill.st-revision{background:rgba(12,163,12,.08);color:#006300}
.an-pill.st-redefense{background:rgba(250,178,25,.2);color:#7a4a00}
.an-pill.st-awaiting{background:rgba(85,152,231,.15);color:#184f95}
.an-pill.st-missed{background:rgba(208,59,59,.12);color:#a12626}
.an-pill.st-not_scheduled{background:var(--bg4);color:var(--text3)}
.an-dash{color:var(--text3)}
.an-mini-tags{display:flex;flex-wrap:wrap;gap:4px 10px}
.an-mini-tags span{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--text2);white-space:nowrap}
.an-search{font-family:'DM Sans','DM Sans Fallback',sans-serif;font-size:13px;padding:8px 12px 8px 34px;border-radius:9px;border:1px solid var(--border2);width:240px;max-width:100%;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23656b7a' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'/%3E%3C/svg%3E") no-repeat 11px center}
.an-search:focus-visible{outline:2px solid var(--accent);outline-offset:1px}
.an-more{display:block;margin:12px auto 0;font-family:'DM Sans','DM Sans Fallback',sans-serif;font-size:12.5px;font-weight:600;color:var(--accent3);background:none;border:none;cursor:pointer;padding:6px 12px}
.an-more:hover{text-decoration:underline}

.an-empty{display:flex;flex-direction:column;align-items:center;text-align:center;padding:34px 18px;color:var(--text3);font-size:13px;gap:8px}
.an-empty[hidden],.an-more[hidden]{display:none}
.an-empty svg{width:30px;height:30px;stroke:var(--text3);fill:none;stroke-width:1.6;opacity:.6}
.an-empty b{font-size:14px;color:var(--text2);font-weight:600}
.an-empty span{max-width:52ch;line-height:1.55}

/* The one tooltip: values lead, names follow. */
.an-tip{position:fixed;z-index:10000;pointer-events:none;background:var(--navy-dark);color:#fff;border-radius:9px;padding:9px 12px;font-size:12px;line-height:1.45;max-width:260px;box-shadow:0 8px 24px rgba(0,0,0,.25);opacity:0;transition:opacity .1s}
.an-tip.on{opacity:1}
.an-tip b{display:block;font-size:14px;font-weight:700;margin-bottom:1px;font-variant-numeric:tabular-nums}
.an-tip em{display:block;font-style:normal;color:rgba(255,255,255,.78)}
.an-tip .k{display:flex;align-items:center;gap:7px;margin-bottom:3px;font-weight:600}
.an-tip .k i{width:12px;height:3px;border-radius:2px;flex-shrink:0}

/* The table behind every chart, for anyone who cannot use the chart. */
.an-twin .an-tbl td,.an-twin .an-tbl th{padding:8px 10px}
.an-twin .an-tbl td:first-child{white-space:nowrap}

@media (max-width:1100px){
  .an-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
  .an-stage{grid-template-columns:140px minmax(0,1fr) 120px}
}
@media (max-width:720px){
  .an-card{padding:18px 16px}
  .an-stage{grid-template-columns:minmax(0,1fr) auto;grid-template-areas:"name rate" "bar bar"}
  .an-stage-name{grid-area:name}.an-stage-rate{grid-area:rate}.an .an-stage .an-bar{grid-area:bar}
  .an-arow{grid-template-columns:76px minmax(0,1fr) 26px;gap:8px}
  .an-arow-y{font-size:11.5px}
  .an-arow-word{display:none}
  .an-heat .an-tag-cell,.an-heat th:first-child{position:sticky;left:0;background:#fff;z-index:2;box-shadow:6px 0 8px -6px rgba(0,0,0,.12)}
  .an-swipe{display:block}
  .an-select{min-width:0;width:100%}
  .an-field{flex:1 1 130px}
  .an-tbl th:nth-child(n+5),.an-tbl td:nth-child(n+5){white-space:nowrap}
}
@media (max-width:480px){
  .an-kpis{gap:8px}
  .an-kpis .stat-chip{padding:14px}
  .an-kpis .stat-n{font-size:24px}
}
@media (prefers-reduced-motion:reduce){.an-seg,.an-hm,.an-col,.an-tip{transition:none}}
</style>
  @include('partials.render-whole')
</head>
<body>
@php
    $f = $a['filters'];
    $k = $a['kpis'];
    $stages = $a['stages'];
    $currentRoute = Route::currentRouteName();
    $isDefaultYear = $f['school_year'] === $a['currentYear'];
    $yearLabel = $f['school_year'] === \App\Support\DefenseAnalytics::ALL_YEARS ? 'All school years' : 'SY '.str_replace('-', '–', $f['school_year']);

    // The link every filter control builds on: what is chosen now, with one
    // thing changed. A blank value drops the filter altogether, so the
    // address stays short and "All" really is the absence of a filter.
    $query = fn (array $over = []) => route($currentRoute, array_filter(
        array_merge(array_intersect_key(request()->query(), array_flip(['school_year', 'program', 'section', 'tag'])), $over),
        fn ($v) => $v !== null && $v !== ''
    ));

    $status = [
        'passed' => ['label' => 'Passed', 'color' => 'var(--st-passed)', 'icon' => '<polyline points="20 6 9 17 4 12"/>'],
        'revision' => ['label' => 'Passed with revision', 'color' => 'var(--st-revision)', 'icon' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>'],
        'redefense' => ['label' => 'Needs a redefense', 'color' => 'var(--st-redefense)', 'icon' => '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>'],
        'awaiting' => ['label' => 'Waiting for the result', 'color' => 'var(--st-awaiting)', 'icon' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
        'missed' => ['label' => 'Did not pass', 'color' => 'var(--st-missed)', 'icon' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>'],
        'not_scheduled' => ['label' => 'Not scheduled yet', 'color' => 'var(--st-idle)', 'icon' => '<line x1="5" y1="12" x2="19" y2="12"/>'],
    ];

    $stageNames = \App\Support\DefenseProgress::STAGES;
    $ordinal = ['var(--ord-1)', 'var(--ord-2)', 'var(--ord-3)', 'var(--ord-4)'];
    $heat = fn (?float $rate) => $rate === null ? 0 : ($rate < 20 ? 1 : ($rate < 40 ? 2 : ($rate < 60 ? 3 : ($rate < 80 ? 4 : 5))));
    $plural = fn (int $n, string $word) => $n.' '.$word.($n === 1 ? '' : 's');
    $hasAny = $k['teams'] > 0;
    $maxTagTeams = max(1, collect($a['byTag'])->max('teams') ?? 1);
    $tagRowsTotal = collect($a['byTag'])->sum('teams');
    $archive = $a['archive'];
    $archiveKinds = collect(array_keys(\App\Support\DefenseAnalytics::TAG_COLORS))->push('Other')->filter(fn ($t) => isset($archive['tags'][$t]))->values();
@endphp

@include('capsa._sidebar')
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Analytics</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
      <button type="button" class="btn btn-ghost-nav" id="an-export" title="Preview the team table below, then download it">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><path d="M12 15V3"/></svg>
        Export report
      </button>
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="sk" style="height:96px;border-radius:14px;margin-bottom:16px"></div>
    <div class="grid-4" style="margin-bottom:16px">
      <div class="sk" style="height:96px;border-radius:12px"></div>
      <div class="sk" style="height:96px;border-radius:12px"></div>
      <div class="sk" style="height:96px;border-radius:12px"></div>
      <div class="sk" style="height:96px;border-radius:12px"></div>
    </div>
    <div class="sk" style="height:240px;border-radius:14px;margin-bottom:16px"></div>
    <div class="sk" style="height:300px;border-radius:14px"></div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content">
  <div class="an">

    {{-- FILTERS: one row above everything they scope --}}
    <form class="an-filters" id="an-filters" method="get" action="{{ route($currentRoute) }}">
      <div class="an-filter-row">
        <div class="an-field">
          <label for="f-year">School year</label>
          <select class="an-select" id="f-year" name="school_year">
            <option value="{{ \App\Support\DefenseAnalytics::ALL_YEARS }}" @selected($f['school_year'] === \App\Support\DefenseAnalytics::ALL_YEARS)>All school years</option>
            @foreach ($a['options']['years'] as $y)
              <option value="{{ $y }}" @selected($f['school_year'] === $y)>SY {{ str_replace('-', '–', $y) }}{{ $y === $a['currentYear'] ? ' (current)' : '' }}</option>
            @endforeach
          </select>
        </div>
        <div class="an-field">
          <label for="f-program">Program</label>
          <select class="an-select" id="f-program" name="program">
            <option value="">All programs</option>
            @foreach ($a['options']['programs'] as $p)
              <option value="{{ $p }}" @selected($f['program'] === $p)>{{ $p }}</option>
            @endforeach
          </select>
        </div>
        <div class="an-field">
          <label for="f-section">Section</label>
          <select class="an-select" id="f-section" name="section">
            <option value="">All sections</option>
            @foreach ($a['options']['sections'] as $s)
              <option value="{{ $s }}" @selected($f['section'] === $s)>{{ $s }}</option>
            @endforeach
          </select>
        </div>
        @if ($f['tag'])<input type="hidden" name="tag" value="{{ $f['tag'] }}">@endif
        <noscript><button class="an-toggle" type="submit">Apply</button></noscript>
        @if (! $isDefaultYear || $f['program'] || $f['section'] || $f['tag'])
          <a class="an-reset" href="{{ route($currentRoute) }}">Reset filters</a>
        @endif
      </div>

      <div class="an-chips" role="group" aria-label="Project type">
        <span class="an-chips-label">Project type</span>
        <a class="an-chip {{ $f['tag'] ? '' : 'is-active' }}" href="{{ $query(['tag' => null]) }}" @if (! $f['tag']) aria-current="true" @endif>
          All types <span class="an-chip-n">{{ $a['teamsInScope'] }}</span>
        </a>
        @foreach ($a['tagChips'] as $chip)
          <a class="an-chip {{ $f['tag'] === $chip['tag'] ? 'is-active' : '' }}" href="{{ $query(['tag' => $chip['tag']]) }}" @if ($f['tag'] === $chip['tag']) aria-current="true" @endif>
            <span class="an-dot" style="background:{{ $chip['color'] }}"></span>{{ $chip['tag'] }}
            <span class="an-chip-n">{{ $chip['teams'] }}</span>
          </a>
        @endforeach
      </div>

      <div class="an-scope">
        Showing <b>{{ $yearLabel }}</b> · <b>{{ $f['program'] ?: 'all programs' }}</b>@if ($f['section']) · <b>{{ $f['section'] }}</b>@endif @if ($f['tag']) · <b>{{ $f['tag'] }}</b> projects @endif
        · <b>{{ $plural($k['teams'], 'team') }}</b>. A team is counted in the school year its defense took place in.
      </div>
    </form>

    @if (! $hasAny)
      <div class="an-card">
        <div class="an-empty">
          <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
          <b>No teams to show for these filters</b>
          <span>
            @if ($f['tag'] || $f['program'] || $f['section'])
              Nothing matches this combination. <a href="{{ route($currentRoute) }}" style="color:var(--accent3);font-weight:600">Reset the filters</a> to see every team.
            @elseif (! $isDefaultYear)
              No team has a defense recorded in {{ $yearLabel }}.
            @else
              Once teams are confirmed and scheduled for a defense, how many pass each stage - and what kind of project they are - shows up here.
            @endif
          </span>
        </div>
      </div>
    @else

    {{-- HEADLINE NUMBERS --}}
    <div class="an-kpis">
      <div class="stat-chip">
        <div class="stat-n">{{ $k['teams'] }}</div>
        <div class="stat-l">Teams</div>
        <div class="stat-sub">{{ $k['tagged'] }} with a project tag{{ $k['teams'] - $k['tagged'] > 0 ? ' · '.($k['teams'] - $k['tagged']).' untagged' : '' }}</div>
      </div>
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--green)">{{ $k['title_passed'] }}</div>
        <div class="stat-l">Passed Title Defense</div>
        <div class="stat-sub">of {{ $plural($k['title_teams'], 'team') }}@if ($k['title_rate'] !== null) · {{ (int) $k['title_rate'] }}% pass rate @endif</div>
      </div>
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--accent3)">{{ $k['rate'] !== null ? (int) $k['rate'].'%' : '—' }}</div>
        <div class="stat-l">Pass rate, all stages</div>
        <div class="stat-sub">{{ $k['passed'] }} passed of {{ $k['decided'] }} decided</div>
        @if ($k['rate'] !== null)<div class="an-kpi-meter" aria-hidden="true"><i style="width:{{ (int) $k['rate'] }}%"></i></div>@endif
      </div>
      <div class="stat-chip">
        <div class="stat-n" style="color:{{ $k['awaiting'] ? 'var(--blue)' : 'var(--text)' }}">{{ $k['awaiting'] }}</div>
        <div class="stat-l">Waiting for a result</div>
        <div class="stat-sub">{{ $k['redefense'] }} need a redefense · {{ $k['missed'] }} did not pass</div>
      </div>
    </div>

    {{-- WHERE EVERY TEAM STANDS --}}
    <section class="an-card an-fig" data-view="chart" aria-labelledby="an-h-stages">
      <div class="an-card-head">
        <div>
          <div class="an-card-title" id="an-h-stages">How many teams pass each defense</div>
          <div class="an-card-sub">Every team appears once per stage, however many tries it took. The pass rate is passed over the teams that have a result, so teams still waiting are not counted against it.</div>
        </div>
        <button type="button" class="an-toggle" data-toggle-view>View as table</button>
      </div>

      <div class="an-chart">
        <div class="an-legend" aria-label="Legend">
          @foreach ($status as $key => $s)
            <span class="an-key"><i style="background:{{ $s['color'] }}"></i><svg viewBox="0 0 24 24" aria-hidden="true">{!! $s['icon'] !!}</svg>{{ $s['label'] }}</span>
          @endforeach
        </div>
        <div class="an-stages">
          @foreach ($stageNames as $stage)
            @php $st = $stages[$stage]; @endphp
            <div class="an-stage">
              <div class="an-stage-name"><b>{{ $stage }}</b><span>{{ $plural($st['teams'], 'team') }}</span></div>
              @if ($st['teams'] === 0)
                <div class="an-bar is-empty">No teams at this stage</div>
              @else
                <div class="an-bar" role="img" aria-label="{{ $stage }}: @foreach (\App\Support\DefenseAnalytics::STATUSES as $key){{ $st[$key] }} {{ strtolower($status[$key]['label']) }}{{ ! $loop->last ? ', ' : '' }}@endforeach">
                  @foreach (\App\Support\DefenseAnalytics::STATUSES as $key)
                    @if ($st[$key] > 0)
                      @php $share = $st[$key] / $st['teams'] * 100; @endphp
                      <span class="an-seg {{ $key === 'missed' ? 'on-dark' : '' }}" style="flex:{{ $st[$key] }} 1 0;background:{{ $status[$key]['color'] }}" tabindex="0"
                            data-tip-color="{{ $status[$key]['color'] }}" data-tip-title="{{ $st[$key] }}" data-tip-body="{{ $status[$key]['label'] }}||{{ $stage }} · {{ round($share) }}% of {{ $plural($st['teams'], 'team') }}">{{ $share >= 9 ? $st[$key] : '' }}</span>
                    @endif
                  @endforeach
                </div>
              @endif
              <div class="an-stage-rate">
                <b>{{ $st['rate'] !== null ? (int) $st['rate'].'%' : '—' }}</b>
                <span>{{ $st['decided'] > 0 ? $st['passed_total'].' passed of '.$st['decided'].' decided' : 'No result yet' }}@if ($st['first_try_rate'] !== null)<br>{{ (int) $st['first_try_rate'] }}% on the first try @endif</span>
              </div>
            </div>
          @endforeach
        </div>
      </div>

      <div class="an-twin an-scroll">
        <table class="an-tbl" data-export="Teams Passing Each Defense">
          <thead><tr><th>Stage</th><th class="num">Teams</th>@foreach ($status as $s)<th class="num">{{ $s['label'] }}</th>@endforeach<th class="num">Pass rate</th><th class="num">First-try rate</th></tr></thead>
          <tbody>
            @foreach ($stageNames as $stage)
              @php $st = $stages[$stage]; @endphp
              <tr><td>{{ $stage }}</td><td class="num">{{ $st['teams'] }}</td>@foreach (\App\Support\DefenseAnalytics::STATUSES as $key)<td class="num">{{ $st[$key] }}</td>@endforeach<td class="num">{{ $st['rate'] !== null ? (int) $st['rate'].'%' : '—' }}</td><td class="num">{{ $st['first_try_rate'] !== null ? (int) $st['first_try_rate'].'%' : '—' }}</td></tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>

    {{-- PASS RESULTS BY PROJECT TAG --}}
    <section class="an-card an-fig" data-view="chart" aria-labelledby="an-h-tags">
      <div class="an-card-head">
        <div>
          <div class="an-card-title" id="an-h-tags">Pass rate by project type</div>
          <div class="an-card-sub">Each cell is the share of teams with a result that passed that defense. Pick a project type to see only those teams across the whole page.</div>
        </div>
        <button type="button" class="an-toggle" data-toggle-view>View as table</button>
      </div>

      @if (count($a['byTag']) === 0)
        <div class="an-empty"><b>No project types yet</b><span>Teams are tagged when they write their pre-proposal.</span></div>
      @else
      <div class="an-chart">
        <div class="an-scroll">
          <table class="an-heat" data-export="Pass Rate by Project Type (by Stage)">
            <thead>
              <tr>
                <th>Project type</th><th>Teams</th>
                @foreach ($stageNames as $stage)<th>{{ \App\Support\DefenseAnalytics::SHORT[$stage] }}</th>@endforeach
                <th>All stages</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($a['byTag'] as $row)
                <tr>
                  <td class="an-tag-cell">
                    <a class="an-tag-link" href="{{ $query(['tag' => $f['tag'] === $row['tag'] ? null : $row['tag']]) }}">
                      <span class="an-dot" style="background:{{ $row['color'] }}"></span>{{ $row['tag'] }}
                    </a>
                  </td>
                  <td class="an-teams-cell">
                    <b>{{ $row['teams'] }}</b>
                    <div class="an-teams-bar" aria-hidden="true"><i style="width:{{ round($row['teams'] / $maxTagTeams * 100) }}%"></i></div>
                  </td>
                  @foreach ($stageNames as $stage)
                    @php $c = $row['stages'][$stage]; @endphp
                    <td class="an-hm h{{ $heat($c['rate']) }}" tabindex="0"
                        data-tip-title="{{ $c['rate'] !== null ? (int) $c['rate'].'%' : 'No result yet' }}"
                        data-tip-body="{{ $row['tag'] }} · {{ $stage }}||{{ $c['decided'] > 0 ? $c['passed_total'].' of '.$plural($c['decided'], 'team').' passed' : $plural($c['teams'], 'team').', none decided' }}@if ($c['redefense'] > 0)||{{ $c['redefense'] }} need a redefense @endif @if ($c['awaiting'] > 0)||{{ $c['awaiting'] }} waiting for the result @endif">
                      @if ($c['rate'] !== null)
                        <b>{{ (int) $c['rate'] }}%</b><small>{{ $c['passed_total'] }}/{{ $c['decided'] }}</small>
                      @else
                        <b>–</b><small>{{ $c['teams'] > 0 ? 'waiting' : 'none' }}</small>
                      @endif
                    </td>
                  @endforeach
                  <td class="an-hm h{{ $heat($row['rate']) }}" tabindex="0"
                      data-tip-title="{{ $row['rate'] !== null ? (int) $row['rate'].'%' : 'No result yet' }}"
                      data-tip-body="{{ $row['tag'] }} · all stages||{{ $row['passed'] }} of {{ $row['decided'] }} decided defenses passed">
                    @if ($row['rate'] !== null)<b>{{ (int) $row['rate'] }}%</b><small>{{ $row['passed'] }}/{{ $row['decided'] }}</small>@else<b>–</b><small>none</small>@endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <p class="an-swipe">Swipe sideways to see every defense.</p>
        <div class="an-scale" aria-label="Colour scale">
          Pass rate
          <span>0%</span>
          <i style="background:var(--heat-1)"></i><i style="background:var(--heat-2)"></i><i style="background:var(--heat-3)"></i><i style="background:var(--heat-4)"></i><i style="background:var(--heat-5)"></i>
          <span>100%</span>
          <span style="margin-left:10px"><i style="background:var(--heat-0)"></i> No result yet</span>
        </div>
        @if ($tagRowsTotal > $k['teams'])
          <p class="an-note">A team can carry up to {{ \App\Support\ProjectTags::MAX }} tags and counts under each of them, so the rows add up to more than the {{ $plural($k['teams'], 'team') }} in view.</p>
        @endif
      </div>

      <div class="an-twin an-scroll">
        <table class="an-tbl" data-export="Pass Rate by Project Type">
          <thead><tr><th>Project type</th><th class="num">Teams</th>@foreach ($stageNames as $stage)<th class="num">{{ $stage }}</th>@endforeach<th class="num">All stages</th></tr></thead>
          <tbody>
            @foreach ($a['byTag'] as $row)
              <tr>
                <td>{{ $row['tag'] }}</td><td class="num">{{ $row['teams'] }}</td>
                @foreach ($stageNames as $stage)@php $c = $row['stages'][$stage]; @endphp<td class="num">{{ $c['rate'] !== null ? $c['passed_total'].'/'.$c['decided'].' ('.(int) $c['rate'].'%)' : '—' }}</td>@endforeach
                <td class="num">{{ $row['rate'] !== null ? $row['passed'].'/'.$row['decided'].' ('.(int) $row['rate'].'%)' : '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @endif
    </section>

    @endif {{-- $hasAny --}}

    {{-- SCHOOL YEARS --}}
    <section class="an-card an-fig" data-view="chart" aria-labelledby="an-h-years">
      <div class="an-card-head">
        <div>
          <div class="an-card-title" id="an-h-years">Pass rate by school year</div>
          <div class="an-card-sub">How each defense has gone, year by year. This view ignores the school-year filter and keeps the rest.</div>
        </div>
        @if (count($a['byYear']) > 0)<button type="button" class="an-toggle" data-toggle-view>View as table</button>@endif
      </div>

      @if (count($a['byYear']) === 0)
        <div class="an-empty">
          <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
          <b>No defenses recorded yet</b>
          <span>Once a Panel Chair records a result, each school year's pass rate is drawn here.</span>
        </div>
      @else
      <div class="an-chart">
        <div class="an-legend">
          @foreach ($stageNames as $i => $stage)
            <span class="an-key"><i style="background:{{ $ordinal[$i] }}"></i>{{ $stage }}</span>
          @endforeach
        </div>
        <div class="an-years">
          <div class="an-yaxis" aria-hidden="true"><span>100%</span><span>50%</span><span>0%</span></div>
          <div class="an-plot">
            <i aria-hidden="true"></i>
            @foreach ($a['byYear'] as $year => $yStages)
              <div class="an-grp">
                <div class="an-grp-bars">
                  @foreach ($stageNames as $i => $stage)
                    @php $c = $yStages[$stage]; @endphp
                    <div class="an-col {{ $c['rate'] === null ? 'is-none' : '' }}" tabindex="0"
                         style="height:{{ $c['rate'] === null ? 0 : max((int) $c['rate'], 1) }}%;background:{{ $ordinal[$i] }}"
                         data-tip-color="{{ $ordinal[$i] }}" data-tip-title="{{ $c['rate'] !== null ? (int) $c['rate'].'%' : 'No result yet' }}"
                         data-tip-body="{{ $stage }}||SY {{ str_replace('-', '–', $year) }}||{{ $c['decided'] > 0 ? $c['passed_total'].' of '.$plural($c['decided'], 'team').' passed' : $plural($c['teams'], 'team').', none decided' }}">
                                    </div>
                  @endforeach
                </div>
                <div class="an-grp-x">{{ str_replace('-', '–', $year) }}</div>
              </div>
            @endforeach
          </div>
        </div>
      </div>

      <div class="an-twin an-scroll">
        <table class="an-tbl" data-export="Pass Rate by School Year">
          <thead><tr><th>School year</th>@foreach ($stageNames as $stage)<th class="num">{{ $stage }}</th>@endforeach</tr></thead>
          <tbody>
            @foreach ($a['byYear'] as $year => $yStages)
              <tr>
                <td>SY {{ str_replace('-', '–', $year) }}</td>
                @foreach ($stageNames as $stage)@php $c = $yStages[$stage]; @endphp<td class="num">{{ $c['rate'] !== null ? $c['passed_total'].'/'.$c['decided'].' ('.(int) $c['rate'].'%)' : '—' }}</td>@endforeach
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @endif
    </section>

    {{-- ARCHIVE --}}
    <section class="an-card an-fig" data-view="chart" aria-labelledby="an-h-archive">
      <div class="an-card-head">
        <div>
          <div class="an-card-title" id="an-h-archive">Archived capstones by year and project type</div>
          <div class="an-card-sub">The department's history: {{ $archive['total'] }} archived {{ $archive['total'] === 1 ? 'capstone' : 'capstones' }}. Not narrowed by the filters above.</div>
        </div>
        @if ($archive['total'] > 0)<button type="button" class="an-toggle" data-toggle-view>View as table</button>@endif
      </div>

      @if ($archive['total'] === 0)
        <div class="an-empty">
          <svg viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
          <b>Nothing archived yet</b>
          <span>Finished capstones added to the archive are counted here by school year and the kind of project they were.</span>
        </div>
      @else
      <div class="an-chart">
        <div class="an-legend">
          @foreach ($archiveKinds as $kind)
            <span class="an-key"><i style="background:{{ \App\Support\DefenseAnalytics::TAG_COLORS[$kind] ?? \App\Support\DefenseAnalytics::OTHER_COLOR }}"></i>{{ $kind }}</span>
          @endforeach
        </div>
        <div class="an-archive">
          @foreach ($archive['years'] as $year => $y)
            @php $assigned = array_sum($y['tags']); @endphp
            <div class="an-arow">
              <div class="an-arow-y">{{ str_replace('-', '–', $year) }}</div>
              <div class="an-bar" role="img" aria-label="{{ $year }}: @foreach ($y['tags'] as $kind => $n){{ $n }} {{ $kind }}{{ ! $loop->last ? ', ' : '' }}@endforeach">
                @foreach ($archiveKinds as $kind)
                  @if (($y['tags'][$kind] ?? 0) > 0)
                    @php $n = $y['tags'][$kind]; $c = \App\Support\DefenseAnalytics::TAG_COLORS[$kind] ?? \App\Support\DefenseAnalytics::OTHER_COLOR; @endphp
                    <span class="an-seg {{ in_array($kind, ['Decision Support System', 'Data Analytics'], true) ? 'on-dark' : '' }}" style="flex:{{ $n }} 1 0;background:{{ $c }}" tabindex="0"
                          data-tip-color="{{ $c }}" data-tip-title="{{ $n }}" data-tip-body="{{ $kind }}||{{ $year }} · {{ round($n / $assigned * 100) }}% of its tags">{{ $n / $assigned >= 0.1 ? $n : '' }}</span>
                  @endif
                @endforeach
              </div>
              <div class="an-arow-n">{{ $y['total'] }}<span class="an-arow-word"> {{ $y['total'] === 1 ? 'capstone' : 'capstones' }}</span></div>
            </div>
          @endforeach
        </div>
        <p class="an-note">A capstone with several tags counts under each of them; the bar shows how a year's tags split, and the number on the right is how many capstones it had.</p>
      </div>

      <div class="an-twin an-scroll">
        <table class="an-tbl" data-export="Archived Capstones by Year and Project Type">
          <thead><tr><th>Academic year</th><th class="num">Capstones</th>@foreach ($archiveKinds as $kind)<th class="num">{{ $kind }}</th>@endforeach</tr></thead>
          <tbody>
            @foreach ($archive['years'] as $year => $y)
              <tr><td>{{ $year }}</td><td class="num">{{ $y['total'] }}</td>@foreach ($archiveKinds as $kind)<td class="num">{{ $y['tags'][$kind] ?? 0 }}</td>@endforeach</tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @endif
    </section>

    {{-- TEAM BY TEAM --}}
    @if ($hasAny)
    <section class="an-card" aria-labelledby="an-h-teams">
      <div class="an-card-head">
        <div>
          <div class="an-card-title" id="an-h-teams">Team by team</div>
          <div class="an-card-sub">Where each team in view stands at every defense. This is also what Export report downloads.</div>
        </div>
        <input class="an-search" type="search" id="an-team-search" placeholder="Search a team or project" aria-label="Search teams">
      </div>
      <div class="an-scroll">
        <table class="an-tbl" id="an-team-table" data-export="Team by Team">
          <thead>
            <tr><th>Team</th><th>Section</th><th>Project type</th>@foreach ($stageNames as $stage)<th>{{ \App\Support\DefenseAnalytics::SHORT[$stage] }}</th>@endforeach</tr>
          </thead>
          <tbody>
            @foreach ($a['teams'] as $i => $team)
              <tr data-row data-search="{{ strtolower($team['name'].' '.$team['title'].' '.$team['section'].' '.implode(' ', $team['tags'])) }}" @if ($i >= 25) data-extra hidden @endif>
                <td><div class="an-team"><b>{{ $team['name'] }}</b>@if ($team['title'])<span title="{{ $team['title'] }}">{{ $team['title'] }}</span>@endif</div></td>
                <td>{{ $team['section'] }}</td>
                <td>
                  @if ($team['tags'])
                    <div class="an-mini-tags">
                      @foreach (array_slice($team['tags'], 0, 3) as $tag)
                        <span><i class="an-dot" style="background:{{ \App\Support\DefenseAnalytics::TAG_COLORS[$tag] ?? \App\Support\DefenseAnalytics::OTHER_COLOR }}"></i>{{ $tag }}</span>
                      @endforeach
                      @if (count($team['tags']) > 3)<span class="an-dash">+{{ count($team['tags']) - 3 }}</span>@endif
                    </div>
                  @else
                    <span class="an-dash">Untagged</span>
                  @endif
                </td>
                @foreach ($stageNames as $stage)
                  @php $s = $team['stages'][$stage]; @endphp
                  <td>
                    @if ($s === 'closed')
                      <span class="an-dash" title="Not reached: an earlier defense was not passed">–</span>
                    @elseif ($s)
                      <span class="an-pill st-{{ $s }}"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $status[$s]['icon'] !!}</svg>{{ ['passed' => 'Passed', 'revision' => 'Revision', 'redefense' => 'Redefense', 'awaiting' => 'Waiting', 'missed' => 'Did not pass', 'not_scheduled' => 'Not yet'][$s] }}</span>
                    @else
                      <span class="an-dash" title="Not in the school year being looked at">–</span>
                    @endif
                  </td>
                @endforeach
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="an-empty" id="an-team-none" hidden><b>No team matches that search</b></div>
      @if (count($a['teams']) > 25)
        <button type="button" class="an-more" id="an-team-more">Show all {{ count($a['teams']) }} teams</button>
      @endif
    </section>
    @endif

  </div>
  </div>
  </div>
</div>

<div class="an-tip" id="an-tip" role="tooltip" aria-hidden="true"></div>

@include('capsa.partials.skeleton-toggle')

<script nonce="{{ Vite::cspNonce() }}">
(function () {
  // Changing a filter applies it: there is no second button to find.
  var filters = document.getElementById('an-filters');
  if (filters) {
    filters.querySelectorAll('select').forEach(function (s) {
      s.addEventListener('change', function () { filters.submit(); });
    });
  }

  // Every chart has a table behind it; this swaps between the two.
  document.querySelectorAll('[data-toggle-view]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var fig = btn.closest('.an-fig');
      var asTable = fig.getAttribute('data-view') !== 'table';
      fig.setAttribute('data-view', asTable ? 'table' : 'chart');
      btn.textContent = asTable ? 'View as chart' : 'View as table';
    });
  });

  // Export report: the Reports Builder's PDF of the whole team table, previewed first.
  var exportBtn = document.getElementById('an-export');
  exportBtn.addEventListener('click', function () {
    var table = document.getElementById('an-team-table');
    if (!table) return window.ctToast('There are no teams to export.', 'error');
    exportBtn.disabled = true;
    window.txPreviewTable(table).then(function () { exportBtn.disabled = false; });
  });

  // Team table: search, and reveal the rows past the first page.
  var search = document.getElementById('an-team-search');
  var more = document.getElementById('an-team-more');
  var none = document.getElementById('an-team-none');
  var rows = document.querySelectorAll('#an-team-table [data-row]');
  var showAll = false;

  function applyRows() {
    var q = search ? search.value.trim().toLowerCase() : '';
    var shown = 0;
    rows.forEach(function (row) {
      var matches = !q || row.getAttribute('data-search').indexOf(q) !== -1;
      var hiddenByPage = row.hasAttribute('data-extra') && !showAll && !q;
      row.hidden = !matches || hiddenByPage;
      if (!row.hidden) shown++;
    });
    if (none) none.hidden = shown !== 0;
    if (more) more.hidden = showAll || !!q;
  }
  if (search) search.addEventListener('input', applyRows);
  if (more) more.addEventListener('click', function () { showAll = true; applyRows(); });

  // One tooltip for every mark. Text goes in as textContent - team names and
  // tags are typed by people, and this is not the place to trust them.
  var tip = document.getElementById('an-tip');

  function fill(el) {
    tip.textContent = '';
    var color = el.getAttribute('data-tip-color');
    var body = (el.getAttribute('data-tip-body') || '').split('||');

    if (color) {
      var key = document.createElement('div');
      key.className = 'k';
      var swatch = document.createElement('i');
      swatch.style.background = color;
      key.appendChild(swatch);
      var name = document.createElement('span');
      name.textContent = body.shift() || '';
      key.appendChild(name);
      tip.appendChild(key);
    }

    var value = document.createElement('b');
    value.textContent = el.getAttribute('data-tip-title') || '';
    tip.appendChild(value);

    body.forEach(function (line, i) {
      if (!line) return;
      var p = document.createElement('em');
      p.textContent = line;
      tip.appendChild(p);
    });
  }

  function place(x, y) {
    var w = tip.offsetWidth, h = tip.offsetHeight, pad = 12;
    var left = x + 14, top = y + 16;
    if (left + w + pad > window.innerWidth) left = x - w - 14;
    if (top + h + pad > window.innerHeight) top = y - h - 14;
    tip.style.left = Math.max(pad, left) + 'px';
    tip.style.top = Math.max(pad, top) + 'px';
  }

  function show(el, x, y) {
    fill(el);
    tip.classList.add('on');
    tip.setAttribute('aria-hidden', 'false');
    place(x, y);
  }

  function hide() {
    tip.classList.remove('on');
    tip.setAttribute('aria-hidden', 'true');
  }

  document.addEventListener('pointermove', function (e) {
    var el = e.target.closest ? e.target.closest('[data-tip-title]') : null;
    if (!el) { hide(); return; }
    show(el, e.clientX, e.clientY);
  });
  document.addEventListener('pointerleave', hide);
  document.addEventListener('scroll', hide, true);
  document.addEventListener('focusin', function (e) {
    var el = e.target.closest ? e.target.closest('[data-tip-title]') : null;
    if (!el) return;
    var r = el.getBoundingClientRect();
    show(el, r.left + r.width / 2, r.top);
  });
  document.addEventListener('focusout', hide);
})();

/* Mobile sidebar toggle */
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() {
  if (!sidebarEl) return;
  sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar();
}
function openSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.add('mobile-open');
  sidebarOverlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.remove('mobile-open');
  sidebarOverlay.classList.remove('open');
  document.body.style.overflow = '';
}
</script>

@include('partials.pdf-viewer')
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>
