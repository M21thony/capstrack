<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Fees - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/table-kit.css') }}"/>
<style nonce="{{ Vite::cspNonce() }}">
  .fe-ic{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex:none}
  .fe-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
  .fe-stat{display:flex;align-items:center;gap:14px;padding:14px 18px}
  .fe-stat-ic{width:36px;height:36px;border-radius:9px;display:grid;place-items:center;background:#eef2ff;color:#2563eb;flex:none}
  .fe-stat b{display:block;font-size:20px;font-weight:700;color:var(--text);font-variant-numeric:tabular-nums}
  .fe-stat span{font-size:12.5px;color:var(--text2)}
  .fe-stat .fe-side{margin-left:auto;text-align:right;font-size:12px;color:var(--text2)}
  .fe-bar{display:block;height:4px;width:90px;border-radius:2px;background:var(--bg3);margin-top:6px;overflow:hidden}
  .fe-bar i{display:block;height:100%;background:var(--green)}

  .fe-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:14px}
  .fe-card{padding:0;overflow:hidden;display:flex;flex-direction:column}
  .fe-head{display:flex;align-items:center;gap:10px;padding:14px 18px;border-bottom:1px solid var(--border)}
  .fe-head h2{display:flex;align-items:center;gap:10px;margin:0;font-size:15px;font-weight:700;color:var(--text)}
  .fe-foot{display:flex;justify-content:space-between;padding:10px 18px;border-top:1px solid var(--border);font-size:12px;color:var(--text2);margin-top:auto}

  .fe-bars{display:grid;grid-template-columns:60px 1fr;gap:12px 14px;align-items:center;padding:20px 18px;margin:0}
  .fe-bars dt{font-size:13px;font-weight:600;color:var(--text)}
  .fe-bars dd{margin:0;display:flex;align-items:center;gap:8px;font-size:12.5px;font-weight:600;color:var(--text);font-variant-numeric:tabular-nums}
  .fe-bars dd i{height:18px;border-radius:3px;min-width:2px}

  .fe-ring-wrap{display:flex;align-items:center;gap:28px;padding:22px 24px}
  .fe-ring{width:124px;height:124px;border-radius:50%;display:grid;place-items:center;flex:none}
  .fe-ring div{width:100px;height:100px;border-radius:50%;background:#fff;display:grid;place-content:center;text-align:center}
  .fe-ring b{font-size:24px;font-weight:700;color:var(--text)}
  .fe-ring small{font-size:11px;color:var(--text2)}
  .fe-legend{flex:1;display:grid;gap:12px;font-size:13px;color:var(--text2)}
  .fe-legend div{display:flex;align-items:center;gap:8px}
  .fe-legend i{width:9px;height:9px;border-radius:2px}
  .fe-legend b{margin-left:auto;color:var(--text)}

  .fe-table-card{margin-top:14px}
  .fe-table td{padding:10px 16px;font-size:13.5px}
  .fe-table th{padding:10px 16px}
  .fe-table .fe-num{text-align:right;font-variant-numeric:tabular-nums}
  .fe-pill{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:600;padding:2px 8px;border-radius:6px;background:var(--bg3);color:var(--text2)}
  .fe-pill::before{content:"";width:5px;height:5px;border-radius:50%;background:currentColor}
  .fe-pill.on{background:var(--green-bg);color:var(--green)}

  @media (max-width:1100px){.fe-stats,.fe-grid{grid-template-columns:1fr}}
</style>
  @include('partials.render-whole')
</head>
<body>

@include('capsa._sidebar')
@include('partials.sidebar-toggle')

@php
  $rows = collect($rows);
  $sent = $rows->where('is_sent', true)->count();
  $total = $rows->count();
  $pct = $total ? round($sent / $total * 100) : 0;
  $fromSections = $rows->where('receipts', '>', 0)->count();
  // Program is the label before the dash: "BSIT-3A" is BSIT's.
  $byProgram = $rows->groupBy(fn ($r) => strtok($r['section'], '-'))->map->sum('amount')->sortDesc();
  $top = max($byProgram->max() ?: 0, 1);
  $colors = ['BSIT' => '#2563eb', 'BSCS' => '#7c3aed', 'BSIS' => '#0d9488', 'BLIS' => '#ea580c'];
  // Pre-proposal Defense has no fee, so only the three paid defenses show.
  $byStage = collect(\App\Support\DefenseBoard::PAID_STAGES)->mapWithKeys(fn ($s) => [$s => $rows->sum(fn ($r) => $r['stages'][$s] ?? 0)]);
  $stageTop = max($byStage->max() ?: 0, 1);
  $stageColors = ['Title Defense' => '#0ea5e9', 'Pre Oral Defense' => '#f59e0b', 'Final Oral Defense' => '#16a34a'];
@endphp

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Fees</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div class="content">
    <div class="fe-stats">
      <div class="card fe-stat">
        <span class="fe-stat-ic"><svg class="fe-ic" viewBox="0 0 24 24"><path d="M6 2h12v20l-3-2-3 2-3-2-3 2V2Z"/><path d="M9 7h6M9 11h6M9 15h4"/></svg></span>
        <div><b>₱{{ number_format($rows->sum('amount'), 2) }}</b><span>Total fees forwarded</span></div>
      </div>
      <div class="card fe-stat">
        <span class="fe-stat-ic"><svg class="fe-ic" viewBox="0 0 24 24"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"/></svg></span>
        <div><b>{{ $rows->sum('receipts') }}</b><span>Verified receipts forwarded</span></div>
        <span class="fe-side">From {{ $fromSections }} {{ Str::plural('section', $fromSections) }}</span>
      </div>
      <div class="card fe-stat">
        <span class="fe-stat-ic" style="background:#f5f3ff;color:#7c3aed"><svg class="fe-ic" viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M17 4a4 4 0 0 1 0 8M22 21a7 7 0 0 0-4-6.3"/></svg></span>
        <div><b>{{ $sent }} / {{ $total }}</b><span>Sections forwarded</span></div>
        <span class="fe-side">{{ $total - $sent }} not forwarded<span class="fe-bar"><i style="width:{{ $pct }}%"></i></span></span>
      </div>
    </div>

    <div class="fe-grid">
      <section class="card fe-card" aria-labelledby="fe-def">
        <header class="fe-head"><h2 id="fe-def"><svg class="fe-ic" style="color:#0ea5e9" viewBox="0 0 24 24"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/></svg>Forwarded fees by defense</h2></header>
        <dl class="fe-bars" style="grid-template-columns:130px 1fr">
          @foreach ($byStage as $stage => $amount)
            <dt>{{ $stage }}</dt>
            <dd><i style="width:{{ $amount / $stageTop * 70 }}%;background:{{ $stageColors[$stage] }}"></i>₱{{ number_format($amount) }}</dd>
          @endforeach
        </dl>
        <div class="fe-foot"><span>3 defenses</span><b>₱{{ number_format($byStage->sum()) }}</b></div>
      </section>

      <section class="card fe-card" aria-labelledby="fe-prog">
        <header class="fe-head"><h2 id="fe-prog"><svg class="fe-ic" style="color:#2563eb" viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-6M22 20H2"/></svg>Forwarded fees by program</h2></header>
        <dl class="fe-bars">
          @forelse ($byProgram as $program => $amount)
            <dt>{{ $program }}</dt>
            <dd><i style="width:{{ $amount / $top * 85 }}%;background:{{ $colors[$program] ?? '#64748b' }}"></i>₱{{ number_format($amount) }}</dd>
          @empty
            <dt></dt><dd style="color:var(--text3);font-weight:400">No sections yet.</dd>
          @endforelse
        </dl>
        <div class="fe-foot"><span>{{ $byProgram->count() }} {{ Str::plural('program', $byProgram->count()) }}</span></div>
      </section>

      <section class="card fe-card" aria-labelledby="fe-cov">
        <header class="fe-head"><h2 id="fe-cov"><svg class="fe-ic" style="color:#7c3aed" viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/></svg>Submission coverage</h2></header>
        <div class="fe-ring-wrap">
          <div class="fe-ring" style="background:conic-gradient(var(--green) {{ $pct }}%, var(--bg3) 0)" role="img" aria-label="{{ $sent }} of {{ $total }} sections forwarded">
            <div><b>{{ $pct }}%</b><small>{{ $sent }} of {{ $total }} sections</small></div>
          </div>
          <div class="fe-legend">
            <div><i style="background:var(--green)"></i>Forwarded<b>{{ $sent }}</b></div>
            <div><i style="background:var(--bg4)"></i>Not forwarded<b>{{ $total - $sent }}</b></div>
          </div>
        </div>
        <div class="fe-foot"><span>{{ $total - $sent }} {{ Str::plural('section', $total - $sent) }} not forwarded</span></div>
      </section>
    </div>

    <section class="card fe-card fe-table-card" aria-labelledby="fe-tbl">
      <header class="fe-head"><h2 id="fe-tbl">Section breakdown</h2><div id="feTools" style="margin-left:auto;display:flex;gap:8px"></div></header>
      <div class="tk-toolbar">
        <label class="experts-search" style="flex:0 1 280px">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <span class="tk-vh">Search section or CaPSA</span>
          <input class="input" type="search" id="feSearch" placeholder="Search section or CaPSA" oninput="feFilter()">
        </label>
        <div class="tk-tabs tk-toolbar-end" role="tablist" aria-label="Status">
          <button type="button" class="tk-tab" role="tab" data-show="all" aria-selected="true" onclick="feShow(this)">All <span class="n">{{ $total }}</span></button>
          <button type="button" class="tk-tab" role="tab" data-show="1" aria-selected="false" onclick="feShow(this)">Forwarded <span class="n">{{ $sent }}</span></button>
          <button type="button" class="tk-tab" role="tab" data-show="0" aria-selected="false" onclick="feShow(this)">Not forwarded <span class="n">{{ $total - $sent }}</span></button>
        </div>
      </div>
      <table class="table fe-table" data-export="Fees" data-export-into="#feTools">
        <thead>
          <tr><th>Section</th><th class="fe-num">Amount</th><th class="fe-num">Receipts</th><th>Sent on</th><th>Sent by</th><th>Status</th></tr>
        </thead>
        <tbody>
        @forelse ($rows as $row)
          <tr data-sent="{{ $row['is_sent'] ? 1 : 0 }}">
            <td class="td-main" data-label="Section">{{ $row['section'] }}</td>
            <td class="fe-num" data-label="Amount" style="font-weight:700">{{ $row['amount'] ? '₱'.number_format($row['amount'], 2) : '—' }}</td>
            <td class="fe-num" data-label="Receipts">{{ $row['receipts'] }}</td>
            <td data-label="Sent on">{{ $row['sent_on'] ?? '—' }}</td>
            <td data-label="Sent by">{{ $row['sent_by'] ?? '—' }}</td>
            <td data-label="Status"><span class="fe-pill {{ $row['is_sent'] ? 'on' : '' }}">{{ $row['is_sent'] ? 'Forwarded' : 'Not forwarded' }}</span></td>
          </tr>
        @empty
          <tr><td colspan="6" class="tk-empty">No sections yet.</td></tr>
        @endforelse
        </tbody>
      </table>
      <div class="fe-foot"><span id="feShown">{{ $total }} of {{ $total }} sections</span><span>₱{{ number_format($rows->sum('amount'), 2) }} · {{ $rows->sum('receipts') }} receipts</span></div>
    </section>
  </div>
</div>

<script nonce="{{ Vite::cspNonce() }}">
var feStatus = 'all';
function feShow(tab){
  feStatus = tab.dataset.show;
  document.querySelectorAll('[data-show]').forEach(function (t) { t.setAttribute('aria-selected', String(t === tab)); });
  feFilter();
}
function feFilter(){
  var q = document.getElementById('feSearch').value.trim().toLowerCase();
  var rows = document.querySelectorAll('tr[data-sent]'), shown = 0;
  rows.forEach(function (r) {
    var on = (feStatus === 'all' || r.dataset.sent === feStatus) && (!q || r.textContent.toLowerCase().indexOf(q) !== -1);
    r.hidden = !on;
    shown += on;
  });
  document.getElementById('feShown').textContent = shown + ' of ' + rows.length + ' sections';
}
</script>
@include('partials.app-dialog')
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>
