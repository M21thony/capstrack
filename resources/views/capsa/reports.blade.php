<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>{{ $view === 'report' ? $name.' - ' : '' }}Reports - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
<style>
.crumbs{display:flex;align-items:center;gap:8px;font-size:14px;min-width:0;flex:1}
.crumbs > *{white-space:nowrap}
.crumbs > span:first-child, .crumbs a{color:var(--navy-text2);text-decoration:none}
.crumbs a:hover{color:var(--navy-text);text-decoration:underline}
.crumbs .sep{color:var(--navy-text3)}
.crumbs .here{font-weight:600;color:var(--navy-text);overflow:hidden;text-overflow:ellipsis;min-width:0}

.rpt{font-size:14px;line-height:1.5;--shadow:0 1px 2px rgba(24,25,31,.05),0 2px 8px rgba(24,25,31,.04);--gold-line:rgba(201,168,44,.45)}
.rpt [hidden]{display:none!important}
.rpt svg.i{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
.rpt a{color:inherit;text-decoration:none}
.rpt :focus-visible{outline:2px solid var(--accent);outline-offset:2px;border-radius:4px}

.rpt .head{display:flex;flex-wrap:wrap;gap:16px;align-items:center;margin-bottom:22px}
.rpt .head-ico{width:56px;height:56px;border-radius:14px;background:var(--accent);color:#fff;display:grid;place-items:center;box-shadow:0 4px 12px rgba(201,168,44,.28)}
.rpt .head-ico svg.i{width:26px;height:26px;stroke-width:2.2}
.rpt .head h1{font-size:28px;line-height:1.15;margin:0;letter-spacing:-.02em;color:var(--text)}
.rpt .head p{margin:3px 0 0;color:var(--text3)}
.rpt .head-txt{flex:1 1 280px;min-width:0}

.rpt .rbtn{display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border:1px solid var(--border2);border-radius:8px;background:var(--bg2);color:var(--text);font:600 13px/1.4 'DM Sans',sans-serif;white-space:nowrap;cursor:pointer;transition:border-color .15s,background .15s}
.rpt .rbtn:hover{border-color:var(--text3)}
.rpt .rbtn svg.i{width:16px;height:16px}
.rpt .rbtn-gold{background:var(--accent);border-color:var(--accent);color:#18191f}
.rpt .rbtn-gold:hover{background:var(--accent2);border-color:var(--accent2)}
.rpt .rbtn-sm{padding:5px 10px;font-size:12.5px}
.rpt .rbtn-plain{border-color:transparent;background:none;color:var(--text2)}
.rpt .rbtn-plain:hover{background:var(--bg3);color:var(--text)}
.rpt .rbtn:disabled{opacity:.45;cursor:default}

.rpt .kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:30px}
.rpt .kpi{display:flex;align-items:flex-start;gap:14px;padding:18px;background:var(--bg2);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);transition:border-color .15s}
.rpt .kpi:hover{border-color:var(--gold-line)}
.rpt .kpi-ico{width:46px;height:46px;border-radius:50%;display:grid;place-items:center;flex-shrink:0}
.rpt .kpi-ico svg.i{width:22px;height:22px}
.rpt .kpi .grow{flex:1;min-width:0}
.rpt .kpi .grow > span{display:block;font-size:13px;font-weight:600;color:var(--text2)}
.rpt .kpi b{display:block;font-size:26px;line-height:1.2;letter-spacing:-.02em;margin:2px 0;font-variant-numeric:tabular-nums}
.rpt .kpi small{color:var(--text3);font-size:12px}
.rpt .kpi > svg.i{color:var(--text3);align-self:center;width:16px;height:16px}
.rpt .t-gold{background:#fbf3d9;color:var(--accent3)}
.rpt .t-blue{background:#e4ecfd;color:var(--blue)}
.rpt .t-green{background:#dcf5e5;color:var(--green)}
.rpt .t-red{background:#fde4e4;color:var(--red)}

.rpt .sec-h{margin-bottom:14px}
.rpt .sec-h h2{margin:0;font-size:18px;letter-spacing:-.01em}
.rpt .sec-h p{margin:2px 0 0;color:var(--text3);font-size:13px}

.rpt .cats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.rpt .cat{display:flex;flex-direction:column;align-items:flex-start;gap:6px;padding:20px;background:var(--bg2);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);transition:border-color .15s,box-shadow .15s}
.rpt .cat:hover{border-color:var(--accent);box-shadow:0 6px 20px rgba(24,25,31,.07)}
.rpt .cat-ico{width:46px;height:46px;border-radius:50%;display:grid;place-items:center;margin-bottom:8px}
.rpt .cat-ico svg.i{width:22px;height:22px}
.rpt .c-gold{background:var(--accent);color:#fff}
.rpt .c-dark{background:var(--navy-dark);color:#fff}
.rpt .cat h3{margin:0;font-size:16px}
.rpt .cat p{margin:0;color:var(--text3);font-size:13px;line-height:1.55;flex:1}
.rpt .cat-go{display:flex;align-items:center;gap:6px;margin-top:12px;font-size:13px;font-weight:600;color:var(--accent3)}
.rpt .cat-go svg.i{width:15px;height:15px;transition:transform .2s cubic-bezier(.2,.8,.2,1)}
.rpt .cat:hover .cat-go svg.i{transform:translateX(3px)}

.rpt .panel{background:var(--bg2);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow)}
.rpt .panel-h{display:flex;gap:12px;align-items:center;padding:18px 20px}
.rpt .panel-h svg.i{width:24px;height:24px;color:var(--accent3)}
.rpt .panel-h h2{margin:0;font-size:17px}
.rpt .panel-h p{margin:1px 0 0;font-size:12.5px;color:var(--text3)}
.rpt .pipe-panel{margin-bottom:30px}
.rpt .pipe{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));padding:4px 20px 20px}
.rpt .step{display:flex;flex-direction:column;gap:2px;padding:14px 16px;border:1px solid var(--border);background:var(--bg2);margin-left:-1px;transition:background .15s}
.rpt .step:first-child{border-radius:10px 0 0 10px;margin-left:0}
.rpt .step:last-child{border-radius:0 10px 10px 0}
.rpt .step:hover{background:var(--accent-light)}
.rpt .step-n{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:600;color:var(--text3)}
.rpt .step-n i{font-style:normal;width:20px;height:20px;border-radius:50%;display:grid;place-items:center;font-size:11px;background:var(--accent);color:#18191f}
.rpt .step h3{margin:6px 0 0;font-size:14px}
.rpt .step b{font-size:26px;line-height:1.15;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
.rpt .step small{font-size:12px;color:var(--text3)}
.rpt .meter{height:4px;border-radius:2px;background:var(--bg3);margin-top:10px;overflow:hidden}
.rpt .meter span{display:block;height:100%;background:var(--accent)}

.rpt .bar,.rpt .bar-cols{display:flex;flex-wrap:wrap;gap:10px 12px;align-items:flex-end;padding:16px 18px;border-bottom:1px solid var(--border)}
.rpt .bar-cols{padding:12px 18px;background:var(--bg3)}
.rpt .grow{flex:1}
.rpt .fld{display:flex;flex-direction:column;gap:3px;min-width:0}
.rpt .fld label{font-size:11px;color:var(--text3);font-weight:600}
.rpt select{border:1px solid var(--border2);border-radius:8px;background:var(--bg2);color:var(--text);padding:7px 10px;font:13px 'DM Sans',sans-serif;min-width:0;max-width:100%;cursor:pointer}
.rpt select:hover{border-color:var(--text3)}
.rpt .bar-cols .lbl{font-size:12.5px;font-weight:600;color:var(--text2);padding-bottom:8px}
.rpt .bar-cols .arrow{color:var(--text3);padding-bottom:6px}
.rpt .bar-cols .arrow svg.i{width:16px;height:16px}
.rpt .hint{font-size:12px;color:var(--text3);padding-bottom:8px}
.rpt .addback{display:flex;flex-wrap:wrap;gap:6px;align-items:center;font-size:12.5px;color:var(--text3);padding-bottom:3px}
.rpt .chipbtn{display:inline-flex;align-items:center;gap:4px;border:1px dashed var(--border2);background:var(--bg2);border-radius:6px;padding:3px 9px;font:600 12px 'DM Sans',sans-serif;color:var(--text2);cursor:pointer}
.rpt .chipbtn:hover{border-color:var(--accent);color:var(--accent3)}
.rpt .chipbtn svg.i{width:12px;height:12px}

.rpt .doc-sum{display:flex;flex-wrap:wrap;gap:10px;padding:16px 20px}
.rpt .doc-sum div{flex:1 1 120px;padding:10px 14px;background:var(--bg3);border:1px solid var(--border);border-radius:6px}
.rpt .doc-sum b{display:block;font-size:20px;line-height:1.2;font-variant-numeric:tabular-nums}
.rpt .doc-sum span{font-size:12px;color:var(--text3)}

.rpt .tw{overflow-x:auto}
.rpt table{width:100%;border-collapse:collapse}
.rpt .doc thead th{background:var(--bg3);color:var(--text3);font-size:11px;letter-spacing:.07em;text-transform:uppercase;font-weight:600;padding:0;vertical-align:bottom;line-height:1.35;text-align:left;border-block:1px solid var(--border)}
.rpt .doc thead th.n{text-align:right}
.rpt .th{display:flex;align-items:center;gap:2px;padding:0 6px 0 0}
.rpt .th-sort{flex:1;min-width:0;border:0;background:none;color:inherit;padding:10px 4px 10px 12px;text-align:inherit;font:inherit;letter-spacing:inherit;text-transform:inherit;cursor:pointer}
.rpt .th-sort:hover{color:var(--text)}
.rpt .th-x{border:0;background:none;color:var(--border2);padding:3px;border-radius:4px;display:grid;place-items:center;cursor:pointer}
.rpt th:hover .th-x{color:var(--text3)}
.rpt .th-x:hover{background:#fde4e4;color:var(--red)!important}
.rpt .th-x svg.i{width:13px;height:13px;stroke-width:2.4}
.rpt .doc td,.rpt .doc tbody th,.rpt .doc tfoot th{padding:10px 12px;border-bottom:1px solid var(--border);font-size:13px;white-space:nowrap;text-align:left;color:var(--text)}
.rpt .doc tbody th{font-weight:600}
.rpt .doc td.n{text-align:right;font-variant-numeric:tabular-nums}
.rpt .doc td.none{color:var(--text3)}
.rpt .doc tbody tr:hover td,.rpt .doc tbody tr:hover th{background:var(--accent-light)}
.rpt .doc tfoot td,.rpt .doc tfoot th{font-weight:700;background:var(--bg3);border-top:1px solid var(--border2);border-bottom:0}
.rpt .sec-link{font-weight:600;color:var(--accent3)}
.rpt .sec-link:hover{text-decoration:underline;text-underline-offset:3px}
.rpt .seat{color:var(--text3);font-size:12px}
.rpt .dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:currentColor;margin:0 6px 1px 0;vertical-align:middle}
.rpt .ok{color:var(--green)}.rpt .warn{color:var(--amber)}.rpt .bad{color:var(--red)}.rpt .none{color:var(--text3)}
.rpt .empty{padding:44px 20px;text-align:center;color:var(--text3)}
.rpt .empty b{display:block;color:var(--text);font-size:14px;margin-bottom:4px}
.rpt .as-of{margin:0;padding:12px 20px;font-size:12px;color:var(--text3);border-top:1px solid var(--border)}

.rpt dialog{border:0;border-radius:14px;padding:0;width:min(1100px,calc(100% - 32px));max-height:calc(100% - 48px);box-shadow:0 24px 70px rgba(24,25,31,.3);color:var(--text)}
.rpt dialog::backdrop{background:rgba(24,25,31,.5)}
.rpt .dl-h{display:flex;gap:12px;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border)}
.rpt .dl-h .xl{width:38px;height:38px;border-radius:10px;background:#dcf5e5;color:#15803d;display:grid;place-items:center}
.rpt .dl-h .xl.is-pdf{background:#fde4e4;color:var(--red)}
.rpt .dl-h h2{margin:0;font-size:16px}
.rpt .dl-h p{margin:1px 0 0;font-size:12.5px;color:var(--text3);word-break:break-all}
.rpt .dl-b{padding:18px 20px;overflow:auto;max-height:62vh;background:var(--bg3)}
.rpt .dl-f{display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding:14px 20px;border-top:1px solid var(--border)}
.rpt .dl-f .grow{font-size:12.5px;color:var(--text3)}
.rpt .xbtn{border:0;background:none;padding:6px;border-radius:8px;color:var(--text3);display:grid;place-items:center;cursor:pointer}
.rpt .xbtn:hover{background:var(--bg3);color:var(--text)}
.rpt .xls{background:#fff;border:1px solid #c6c9cf;font:12px/1.4 Calibri,"Segoe UI",sans-serif;display:inline-block;min-width:100%}
.rpt .xls table{width:auto}
.rpt .xls td,.rpt .xls th{border:1px solid #d9dbe0;padding:4px 8px;white-space:nowrap;font-size:12px;text-align:left}
.rpt .xls .rh,.rpt .xls .ch{background:#f1f2f4;color:#6b7280;text-align:center;font-weight:400}
.rpt .xls .rh{width:34px}
.rpt .xls .hdr{background:#c9a82c;color:#18191f;font-weight:700}
.rpt .xls .ttl{font-weight:700;font-size:14px}
.rpt .xls .muted{color:#6b7280}
.rpt .xls .lg{font-weight:700}
.rpt .xls .n{text-align:right}
.rpt .f-ok{background:#dcfce7!important;color:#166534}.rpt .f-warn{background:#fef3c7!important;color:#92400e}.rpt .f-bad{background:#fee2e2!important;color:#991b1b}.rpt .f-none{background:#eef0f3!important;color:#475569}
.rpt .paper{background:#fff;border:1px solid var(--border);box-shadow:0 2px 10px rgba(24,25,31,.08);padding:28px 32px;max-width:1000px;margin:0 auto;overflow-x:auto}
.rpt .pp-top{display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;font-size:11.5px;color:var(--text3);padding-bottom:12px;border-bottom:2px solid var(--accent)}
.rpt .pp-brand{display:flex;align-items:center;gap:8px;font-weight:700;color:var(--text)}
.rpt .pp-brand i{font-style:normal;width:20px;height:20px;border-radius:5px;background:var(--accent);color:#18191f;display:grid;place-items:center;font-size:11px}
.rpt .paper h1{margin:16px 0 2px;font-size:22px;letter-spacing:-.01em}
.rpt .pp-scope{margin:0 0 14px;color:var(--text2);font-size:13px}
.rpt .pp-sum{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
.rpt .pp-sum div{flex:1 1 120px;padding:8px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:6px}
.rpt .pp-sum b{display:block;font-size:17px;font-variant-numeric:tabular-nums}
.rpt .pp-sum span{font-size:11.5px;color:var(--text3)}
.rpt .pp-t th,.rpt .pp-t td{border:1px solid var(--border);padding:6px 10px;text-align:left;white-space:nowrap;font-size:12px}
.rpt .pp-t thead th{white-space:normal;vertical-align:bottom;background:var(--bg3);font-size:11px;color:var(--text2)}
.rpt .pp-t .n{text-align:right;font-variant-numeric:tabular-nums}
.rpt .pp-t .total td{font-weight:700;background:var(--bg3)}
.rpt .pp-leg{display:flex;flex-wrap:wrap;gap:6px 16px;margin:12px 0 0;font-size:11.5px;color:var(--text2)}

@media (max-width:1040px){.rpt .kpis,.rpt .cats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:860px){
  .rpt .pipe{grid-template-columns:1fr}
  .rpt .step,.rpt .step:first-child,.rpt .step:last-child{margin:-1px 0 0;border-radius:0}
  .rpt .step:first-child{margin-top:0;border-radius:10px 10px 0 0}
  .rpt .step:last-child{border-radius:0 0 10px 10px}
  .rpt .paper{padding:18px}
}
@media (max-width:520px){.rpt .kpis,.rpt .cats{grid-template-columns:1fr}.rpt .head h1{font-size:23px}}
</style>
</head>
<body>

@include('capsa._sidebar')
@include('partials.sidebar-toggle')
@php
  $paths = [
      'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
      'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
      'check' => '<path d="M12 22s8-3.5 8-10V5l-8-3-8 3v7c0 6.5 8 10 8 10z"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
      'alert' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
      'grade' => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/>',
      'coin' => '<circle cx="12" cy="12" r="9"/><path d="M15 9.5A3 3 0 0 0 12 8c-1.7 0-3 .9-3 2s1.3 1.6 3 2 3 .9 3 2-1.3 2-3 2a3 3 0 0 1-3-1.5M12 6v2M12 16v2"/>',
      'download' => '<path d="M12 3v12m0 0-4-4m4 4 4-4M4 21h16"/>',
      'right' => '<path d="m9 6 6 6-6 6"/>',
      'arrow' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
      'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
      'plus' => '<path d="M12 5v14M5 12h14"/>',
      'printer' => '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
      'sheet' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18"/>',
      'cap' => '<path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/>',
  ];
  $svg = fn ($name) => '<svg class="i" viewBox="0 0 24 24" aria-hidden="true">'.$paths[$name].'</svg>';

  if ($view === 'report') {
      $table = $report['table'];
      $keys = array_keys($table['columns']);
      $first = $keys[0];
      $pickable = array_slice($keys, 1);
      $isNumber = fn ($key) => in_array($key, $table['numeric'], true);
      // Every value is escaped here; the markup around it is the view's own.
      $cell = function ($v) use ($keep, $tones) {
          if (! is_array($v)) {
              return e($v);
          }

          return match ($v[0]) {
              'b' => '<b>'.e($v[1]).'</b>',
              'sec' => '<a class="sec-link" href="'.e(route('capsa.reports.section', ['section' => $v[1]] + $keep)).'">'.e($v[1]).'</a>',
              'res' => '<span class="dot '.($tones[$v[1]] ?? 'none').'"></span>'.e($v[1]),
              'seat' => '<span class="seat">'.e($v[1]).'</span><br><b>'.e($v[2]).'</b>',
          };
      };
      $quiet = fn ($v) => in_array((string) (is_array($v) ? $v[1] : $v), ['—', '0', ''], true);
      $headDot = fn ($key) => isset($tones[$key]) ? '<span class="dot '.$tones[$key].'"></span>' : '';
      $hasRows = (bool) $table['rows'];
      $title = $section !== null ? $name.' for '.$section : $name;
      $lines = [...$sheet['rows'], ...($sheet['total'] ? [$sheet['total']] : [])];
  }
@endphp
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <nav class="crumbs" aria-label="Breadcrumb">
      @if ($view === 'overview')
        <span>Reports</span>
        <span class="sep">›</span>
        <span class="here" aria-current="page">Overview</span>
      @else
        <a href="{{ route('capsa.reports') }}">Reports</a>
        <span class="sep">›</span>
        @if ($section === null)
          <span class="here" aria-current="page">{{ $name }}</span>
        @else
          <a href="{{ route('capsa.reports', $keep) }}">{{ $name }}</a>
          <span class="sep">›</span>
          <span class="here" aria-current="page">{{ $section }}</span>
        @endif
      @endif
    </nav>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="sk" style="width:260px;height:44px;border-radius:10px;margin-bottom:24px"></div>
    <div class="grid-4" style="margin-bottom:24px">
      <div class="sk" style="height:96px;border-radius:14px"></div>
      <div class="sk" style="height:96px;border-radius:14px"></div>
      <div class="sk" style="height:96px;border-radius:14px"></div>
      <div class="sk" style="height:96px;border-radius:14px"></div>
    </div>
    <div class="card">
      <div class="sk" style="width:220px;height:16px;border-radius:4px;margin-bottom:16px"></div>
      <div class="sk" style="height:200px;border-radius:10px"></div>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content rpt">
  @if ($view === 'overview')
    <header class="head">
      <div class="head-ico">{!! $svg('chart') !!}</div>
      <div class="head-txt">
        <h1>Reports</h1>
        <p>Totals by section for {{ $period }}. Open a report to download it as PDF or Excel.</p>
      </div>
    </header>

    <div class="kpis">
      @foreach ($cards as [$to, $ico, $tint, $label, $value, $sub])
        <a class="kpi" href="{{ route('capsa.reports', ['tab' => $to]) }}">
          <span class="kpi-ico t-{{ $tint }}">{!! $svg($ico) !!}</span>
          <span class="grow"><span>{{ $label }}</span><b>{{ $value }}</b><small>{{ $sub }}</small></span>
          {!! $svg('right') !!}
        </a>
      @endforeach
    </div>

    <section class="panel pipe-panel">
      <div class="panel-h">
        {!! $svg('cap') !!}
        <div>
          <h2>Where the teams are now</h2>
          <p>All {{ $teamCount }} {{ Str::plural('team', $teamCount) }}, by the defense they are on. Click one to see its results by section.</p>
        </div>
      </div>
      <div class="pipe">
        @foreach ($pipeline as $stage => $count)
          <a class="step" href="{{ route('capsa.reports', ['tab' => 'results', 'stage' => $stage]) }}">
            <span class="step-n"><i>{{ $loop->iteration }}</i>Defense {{ $loop->iteration }} of {{ count($pipeline) }}</span>
            <h3>{{ $stage }}</h3>
            <b>{{ $count }}</b>
            <small>{{ Str::plural('team', $count) }} · {{ $teamCount ? round($count / $teamCount * 100) : 0 }}%</small>
            <span class="meter"><span style="width:{{ $teamCount ? $count / $teamCount * 100 : 0 }}%"></span></span>
          </a>
        @endforeach
      </div>
    </section>

    <div class="sec-h"><h2>All reports</h2><p>Pick one to see its totals.</p></div>
    <div class="cats">
      @foreach ($reports as $to => [$ico, $tone, $reportName, $desc])
        <a class="cat" href="{{ route('capsa.reports', ['tab' => $to]) }}">
          <span class="cat-ico c-{{ $tone }}">{!! $svg($ico) !!}</span>
          <h3>{{ $reportName }}</h3>
          <p>{{ $desc }}</p>
          <span class="cat-go">Open report {!! $svg('arrow') !!}</span>
        </a>
      @endforeach
    </div>
  @else
    <header class="head">
      <div class="head-ico">{!! $svg($icon) !!}</div>
      <div class="head-txt">
        <h1>{{ $section ?? $name }}</h1>
        <p>{{ $lede }}</p>
      </div>
      <button type="button" class="rbtn" data-dl="pdf" @disabled(! $hasRows)>{!! $svg('printer') !!}Download PDF</button>
      <button type="button" class="rbtn rbtn-gold" data-dl="xlsx" @disabled(! $hasRows)>{!! $svg('sheet') !!}Download Excel</button>
    </header>

    <form id="rf" method="GET" action="{{ url()->current() }}" class="panel">
      <input type="hidden" name="tab" value="{{ $tab }}">
      <input type="hidden" name="hide" value="{{ implode(',', $hidden) }}">
      <input type="hidden" name="download" value="" disabled>
      <div class="bar">
        @foreach ($fields as [$key, $label, $options, $value])
          <div class="fld">
            <label for="f-{{ $key }}">{{ $label }}</label>
            <select id="f-{{ $key }}" name="{{ $key }}" onchange="this.form.submit()">
              @foreach ($options as $optValue => $optLabel)
                <option value="{{ $optValue }}" @selected((string) $optValue === (string) $value)>{{ $optLabel }}</option>
              @endforeach
            </select>
          </div>
        @endforeach
        <span class="grow"></span>
        @if ($filtered)
          <a class="rbtn rbtn-plain rbtn-sm" href="{{ $clear }}">{!! $svg('x') !!}Clear filters</a>
        @endif
      </div>

      @if ($pickable)
        <div class="bar-cols">
          <span class="lbl">Show columns</span>
          <div class="fld">
            <label for="f-from">From</label>
            <select id="f-from" name="from">
              @foreach ($pickable as $key)
                <option value="{{ $key }}" @selected($key === $from)>{{ $table['columns'][$key] }}</option>
              @endforeach
            </select>
          </div>
          <span class="arrow">{!! $svg('arrow') !!}</span>
          <div class="fld">
            <label for="f-to">To</label>
            <select id="f-to" name="to">
              @foreach ($pickable as $key)
                <option value="{{ $key }}" @selected($key === $to)>{{ $table['columns'][$key] }}</option>
              @endforeach
            </select>
          </div>
          <div class="addback" id="addback" @if (! $hidden) hidden @endif><span>Hidden columns:</span></div>
          <span class="hint" id="hint">To hide a column, point at its heading and click ✕.</span>
        </div>
      @endif

      <div class="doc-sum">
        @foreach ($tiles as $tile)
          <div><b>{{ $tile['value'] }}</b><span>{{ $tile['label'] }}</span></div>
        @endforeach
      </div>

      @if (! $hasRows)
        <div class="empty">
          <b>{{ $table['empty'] }}</b>
          @if ($notPosted)
            It shows up here once the fee is posted on Financial Records.
          @elseif ($filtered)
            Clear the filters to see the running semester.
          @endif
        </div>
      @else
        <div class="empty" id="no-cols" hidden><b>Every column is hidden</b>Show one again above, or change From and To.</div>
        <div id="doc">
          <div class="tw">
            <table class="doc">
              <thead>
                <tr>
                  @foreach ($keys as $key)
                    <th scope="col" class="{{ $isNumber($key) ? 'n' : '' }}" data-col="{{ $key }}" aria-sort="none" @if (! in_array($key, $visible, true)) hidden @endif>
                      <div class="th">
                        <button type="button" class="th-sort" title="Sort by {{ $table['columns'][$key] }}">{!! $headDot($key) !!}{{ $table['columns'][$key] }}<span class="arr"></span></button>
                        @if ($key !== $first)
                          <button type="button" class="th-x" data-drop="{{ $key }}" aria-label="Hide the {{ $table['columns'][$key] }} column" title="Hide column">{!! $svg('x') !!}</button>
                        @endif
                      </div>
                    </th>
                  @endforeach
                </tr>
              </thead>
              <tbody>
                @foreach ($table['rows'] as $row)
                  <tr>
                    @foreach ($keys as $key)
                      @if ($key === $first)
                        <th scope="row" data-col="{{ $key }}">{!! $cell($row[$key]) !!}</th>
                      @else
                        <td class="{{ trim(($isNumber($key) ? 'n ' : '').($quiet($row[$key] ?? '') ? 'none' : '')) }}" data-col="{{ $key }}" @if (! in_array($key, $visible, true)) hidden @endif>{!! $cell($row[$key] ?? '') !!}</td>
                      @endif
                    @endforeach
                  </tr>
                @endforeach
              </tbody>
              @if ($table['total'])
                <tfoot>
                  <tr>
                    @foreach ($keys as $key)
                      @if ($key === $first)
                        <th scope="row" data-col="{{ $key }}">All sections</th>
                      @else
                        <td class="{{ $isNumber($key) ? 'n' : '' }}" data-col="{{ $key }}" @if (! in_array($key, $visible, true)) hidden @endif>{!! $cell($table['total'][$key] ?? '') !!}</td>
                      @endif
                    @endforeach
                  </tr>
                </tfoot>
              @endif
            </table>
          </div>
          <p class="as-of">Numbers as of {{ now()->format('M j, Y') }} · {{ $scope }}</p>
        </div>
      @endif
    </form>

    @if ($hasRows)
      <dialog id="dl" aria-labelledby="dl-title">
        <div class="dl-h">
          <span class="xl is-pdf" data-kind="pdf">{!! $svg('printer') !!}</span>
          <span class="xl" data-kind="xlsx">{!! $svg('sheet') !!}</span>
          <div class="grow">
            <h2 id="dl-title"><span data-kind="pdf">PDF preview</span><span data-kind="xlsx">Excel preview</span></h2>
            <p><span data-kind="pdf">{{ $fileName }}.pdf</span><span data-kind="xlsx">{{ $fileName }}.xlsx</span></p>
          </div>
          <button type="button" class="xbtn" data-close aria-label="Close preview">{!! $svg('x') !!}</button>
        </div>
        <div class="dl-b">
          <article class="paper" data-kind="pdf">
            <div class="pp-top"><span class="pp-brand"><i>C</i>CapsTrack · CaPSA</span><span>Generated {{ now()->format('M j, Y') }} by {{ $by }}</span></div>
            <h1>{{ $title }}</h1>
            <p class="pp-scope">{{ $scope }}</p>
            <div class="pp-sum">
              @foreach ($tiles as $tile)
                <div><b>{{ $tile['value'] }}</b><span>{{ $tile['label'] }}</span></div>
              @endforeach
            </div>
            <table class="pp-t">
              <thead>
                <tr>
                  @foreach ($sheet['keys'] as $i => $key)
                    <th class="{{ $sheet['numeric'][$i] ? 'n' : '' }}" data-col="{{ $key }}">{!! $headDot($key) !!}{{ $sheet['headings'][$i] }}</th>
                  @endforeach
                </tr>
              </thead>
              <tbody>
                @foreach ($lines as $line)
                  <tr class="{{ $loop->last && $sheet['total'] ? 'total' : '' }}">
                    @foreach ($line as $i => $c)
                      <td class="{{ $sheet['numeric'][$i] ? 'n' : '' }}" data-col="{{ $sheet['keys'][$i] }}">@if ($c['tone'] && ! $sheet['tones'][$i])<span class="dot {{ $c['tone'] }}"></span>@endif{{ $c['text'] }}</td>
                    @endforeach
                  </tr>
                @endforeach
              </tbody>
            </table>
            @if ($sheet['legend'])
              <p class="pp-leg" data-legend><b>Legend</b>
                @foreach ($sheet['legend'] as $text => [$tone, $key])
                  <span data-col="{{ $key }}"><span class="dot {{ $tone }}"></span>{{ $text }}</span>
                @endforeach
              </p>
            @endif
          </article>

          @php $width = count($sheet['keys']); $n = 1; @endphp
          <div class="xls" data-kind="xlsx">
            <table>
              <thead>
                <tr>
                  <th class="ch"></th>
                  @foreach ($sheet['keys'] as $key)
                    <th class="ch" data-col="{{ $key }}" data-letter></th>
                  @endforeach
                </tr>
              </thead>
              <tbody>
                <tr><td class="rh">{{ $n++ }}</td><td class="ttl" colspan="{{ $width }}">{{ $title }}</td></tr>
                <tr><td class="rh">{{ $n++ }}</td><td class="muted" colspan="{{ $width }}">{{ $scope }} · Generated {{ now()->format('M j, Y') }} by {{ $by }}</td></tr>
                <tr><td class="rh">{{ $n++ }}</td>
                  @foreach ($tiles as $tile)
                    <td><span class="muted">{{ $tile['label'] }}:</span> <b>{{ $tile['value'] }}</b></td>
                  @endforeach
                </tr>
                @if ($sheet['legend'])
                  <tr data-legend><td class="rh">{{ $n++ }}</td><td class="lg">Legend</td>
                    @foreach ($sheet['legend'] as $text => [$tone, $key])
                      <td class="f-{{ $tone }}" data-col="{{ $key }}">{{ $text }}</td>
                    @endforeach
                  </tr>
                @endif
                <tr><td class="rh">{{ $n++ }}</td>
                  @foreach ($sheet['keys'] as $i => $key)
                    <td class="hdr" data-col="{{ $key }}">{{ $sheet['headings'][$i] }}</td>
                  @endforeach
                </tr>
                @foreach ($lines as $line)
                  <tr><td class="rh">{{ $n++ }}</td>
                    @foreach ($line as $i => $c)
                      <td class="{{ trim(($sheet['numeric'][$i] ? 'n ' : '').($c['tone'] ? 'f-'.$c['tone'].' ' : '').($loop->parent->last && $sheet['total'] ? 'lg' : '')) }}" data-col="{{ $sheet['keys'][$i] }}">{{ $c['text'] }}</td>
                    @endforeach
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
        <div class="dl-f">
          <span class="grow">
            <span data-kind="pdf">Prints on one landscape page. The legend under the table explains the coloured dots.</span>
            <span data-kind="xlsx" data-note="toned">The coloured columns stay coloured in the file. The Legend row at the top says what each colour means.</span>
            <span data-kind="xlsx" data-note="plain">This report has no coloured columns, so the file has no legend.</span>
          </span>
          <button type="button" class="rbtn" data-close>Cancel</button>
          <button type="button" class="rbtn rbtn-gold" id="dl-go">{!! $svg('download') !!}<span data-kind="pdf">Download PDF</span><span data-kind="xlsx">Download Excel</span></button>
        </div>
      </dialog>
    @endif
  @endif
  </div>
  </div>
</div>
@include('capsa.partials.skeleton-toggle')

@if ($view === 'report' && $hasRows)
<script>
/* Columns: every column is on the page already, so From, To and the crosses
   only show and hide cells. The choice rides along in the form, so a filter
   change, a reload and both downloads keep the same columns. */
(function () {
  var form = document.getElementById('rf');
  var cols = @json($pickable);
  var labels = @json($table['columns']);
  var from = form.elements.from, to = form.elements.to, hide = form.elements.hide;
  var plus = @json($svg('plus'));

  function letter(n) { var s = ''; n++; while (n) { var m = (n - 1) % 26; s = String.fromCharCode(65 + m) + s; n = Math.floor((n - 1) / 26); } return s; }

  function apply() {
    var a = from ? cols.indexOf(from.value) : 0, b = to ? cols.indexOf(to.value) : cols.length - 1;
    if (to && b < a) { to.value = cols[cols.length - 1]; b = cols.length - 1; }
    if (to) Array.prototype.forEach.call(to.options, function (o, i) { o.hidden = i < a; });

    var off = (hide.value ? hide.value.split(',') : []).filter(function (k) { var i = cols.indexOf(k); return i >= a && i <= b; });
    hide.value = off.join(',');
    var shown = function (k) { var i = cols.indexOf(k); return i === -1 || (i >= a && i <= b && off.indexOf(k) === -1); };

    document.querySelectorAll('.rpt [data-col]').forEach(function (el) { el.hidden = !shown(el.dataset.col); });

    var any = cols.length === 0 || cols.some(shown);
    document.getElementById('doc').hidden = !any;
    document.getElementById('no-cols').hidden = any;
    document.querySelectorAll('[data-dl]').forEach(function (btn) { btn.disabled = !any; });

    var back = document.getElementById('addback');
    if (back) {
      back.querySelectorAll('button').forEach(function (btn) { btn.remove(); });
      off.forEach(function (k) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'chipbtn';
        btn.title = 'Show ' + labels[k] + ' again';
        btn.innerHTML = plus;
        btn.appendChild(document.createTextNode(labels[k]));
        btn.addEventListener('click', function () { hide.value = off.filter(function (x) { return x !== k; }).join(','); apply(); });
        back.appendChild(btn);
      });
      back.hidden = off.length === 0;
      document.getElementById('hint').hidden = off.length > 0;
    }

    // The previews: the Excel letters close up, and a legend with nothing left to explain goes.
    var n = 0;
    document.querySelectorAll('.xls [data-letter]').forEach(function (th) { if (!th.hidden) th.textContent = letter(n++); });
    var toned = false;
    document.querySelectorAll('[data-legend]').forEach(function (leg) {
      var left = Array.prototype.some.call(leg.querySelectorAll('[data-col]'), function (el) { return !el.hidden; });
      leg.hidden = !left;
      toned = toned || left;
    });
    document.querySelector('[data-note="toned"]').dataset.show = toned ? '1' : '';
    document.querySelector('[data-note="plain"]').dataset.show = toned ? '' : '1';

    try { history.replaceState(null, '', '?' + new URLSearchParams(new FormData(form)).toString()); } catch (e) {}
  }

  if (from) from.addEventListener('change', apply);
  if (to) to.addEventListener('change', apply);
  document.querySelectorAll('[data-drop]').forEach(function (btn) {
    btn.addEventListener('click', function () { hide.value = (hide.value ? hide.value + ',' : '') + btn.dataset.drop; apply(); });
  });
  apply();

  /* Sorting: a click on a heading orders the rows by it, a second click reverses. */
  document.querySelectorAll('.doc .th-sort').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var th = btn.closest('th'), idx = Array.prototype.indexOf.call(th.parentNode.children, th);
      var now = th.getAttribute('aria-sort');
      var dir = now === 'ascending' ? 'descending' : now === 'descending' ? 'ascending' : (idx === 0 ? 'ascending' : 'descending');
      th.parentNode.querySelectorAll('th').forEach(function (h) { h.setAttribute('aria-sort', 'none'); h.querySelector('.arr').textContent = ''; });
      th.setAttribute('aria-sort', dir);
      btn.querySelector('.arr').textContent = dir === 'ascending' ? ' ↑' : ' ↓';

      var value = function (tr) {
        var text = tr.children[idx].textContent.trim();
        var num = text.replace(/[₱,]/g, '').match(/-?\d+(\.\d+)?/);
        return { text: text, num: num ? parseFloat(num[0]) : null, blank: text === '—' || text === '' };
      };
      var body = th.closest('table').tBodies[0];
      Array.prototype.slice.call(body.rows).sort(function (r1, r2) {
        var x = value(r1), y = value(r2);
        if (x.blank !== y.blank) return x.blank ? 1 : -1;
        var c = x.num !== null && y.num !== null && x.num !== y.num ? x.num - y.num : x.text.localeCompare(y.text, undefined, { numeric: true });
        return dir === 'ascending' ? c : -c;
      }).forEach(function (tr) { body.appendChild(tr); });
    });
  });

  /* Downloads: a preview first, then the same form asks for the file. */
  var dialog = document.getElementById('dl'), kind = null;
  document.querySelectorAll('[data-dl]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      kind = btn.dataset.dl;
      dialog.querySelectorAll('[data-kind]').forEach(function (el) {
        el.hidden = el.dataset.kind !== kind || (el.dataset.note !== undefined && !el.dataset.show);
      });
      dialog.showModal();
    });
  });
  dialog.querySelectorAll('[data-close]').forEach(function (btn) { btn.addEventListener('click', function () { dialog.close(); }); });
  document.getElementById('dl-go').addEventListener('click', function () {
    var input = form.elements.download;
    input.value = kind;
    input.disabled = false;
    form.submit();
    input.disabled = true;
    dialog.close();
  });
})();
</script>
@endif

@include('partials.escape-closes-modals')
</body>
</html>
