<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Reports - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
@include('capsa.reports._styles')
<style nonce="{{ Vite::cspNonce() }}">
.rp-period{padding:18px 20px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;align-items:end}
.rp-period-foot{grid-column:1/-1;display:flex;justify-content:space-between;align-items:center;gap:12px;font-size:13px;color:var(--text3)}
.rp-list{overflow:hidden}
.rp-list table{width:100%;border-collapse:collapse}
.rp-list th{padding:12px 20px;font-size:12px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.04em;background:var(--head);border-bottom:1px solid var(--border);text-align:left}
.rp-list td{padding:16px 20px;border-bottom:1px solid var(--border);vertical-align:top}
.rp-list tr:last-child td{border-bottom:none}
.rp-list .nm{font-weight:600;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.rp-list .ab{color:var(--text2)}
.rp-list .none{color:var(--text3);display:inline-block;padding-top:8px}
.rp-list .acts{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}
@media (max-width:980px){.rp-period{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:760px){
  .rp-list thead{display:none}
  .rp-list tr{display:grid;grid-template-columns:1fr;gap:8px;padding:16px;border-bottom:1px solid var(--border)}
  .rp-list tr:last-child{border-bottom:none}
  .rp-list td{padding:0;border:none}
  .rp-list .acts{justify-content:flex-start}
  .rp-list .none{display:none}
}
@media (max-width:520px){.rp-period{grid-template-columns:1fr}.rp-period-foot{flex-direction:column;align-items:flex-start}}
</style>
  @include('partials.render-whole')
</head>
<body>

@include('capsa._sidebar')
@include('partials.sidebar-toggle')
@php
  $icon = fn ($d) => '<svg class="i" viewBox="0 0 24 24" aria-hidden="true">'.$d.'</svg>';
  $table = $icon('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M9 4v16"/>');
@endphp
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <nav class="crumbs" aria-label="Breadcrumb"><span class="here" aria-current="page">Reports</span></nav>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="sk" style="width:220px;height:34px;border-radius:8px;margin-bottom:18px"></div>
    <div class="sk" style="height:96px;border-radius:10px;margin-bottom:16px"></div>
    <div class="sk" style="height:380px;border-radius:10px"></div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content rp">
    <header class="rp-head">
      <p>Pick the period once. Open a report as a table to sort, search and merge it with another report, then download it as a PDF ready to submit, or as an Excel sheet.</p>
    </header>

    <form method="GET" action="{{ route('capsa.reports') }}" class="rp-card rp-period" id="period" aria-label="Report period">
      <label class="rp-field">School year
        <select name="year" data-resets-term>
          @foreach ($ctx['years'] as $y)
            <option value="{{ $y }}" @selected($y === $ctx['year'])>{{ $y }}{{ $y === $ctx['thisYear'] ? ' (current)' : '' }}</option>
          @endforeach
        </select>
      </label>
      <label class="rp-field">Semester
        <select name="term">
          @foreach ($ctx['termOptions'] as $v => $label)
            <option value="{{ $v }}" @selected((string) $v === $ctx['term'])>{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <label class="rp-field">Program
        <select name="program" @disabled(count($ctx['programOptions']) === 1)>
          @foreach ($ctx['programOptions'] as $v => $label)
            <option value="{{ $v }}" @selected((string) $v === $ctx['program'])>{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <label class="rp-field">Section
        <select name="section">
          @foreach ($ctx['sectionOptions'] as $v => $label)
            <option value="{{ $v }}" @selected((string) $v === $ctx['section'])>{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <div class="rp-period-foot">
        <span>{{ $teamCount }} {{ \Illuminate\Support\Str::plural('team', $teamCount) }} in {{ $sectionCount }} {{ \Illuminate\Support\Str::plural('section', $sectionCount) }} for this period.</span>
        <noscript><button type="submit" class="rp-btn">Apply</button></noscript>
      </div>
    </form>

    {{-- One form per report; the controls in its row join it through form="". --}}
    <div hidden>
    @foreach ($reports as $r)
      <form method="GET" action="{{ route('capsa.reports.show', $r['key']) }}" id="f-{{ $r['key'] }}">
        @foreach ($period as $k => $v)
          <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endforeach
      </form>
    @endforeach
    </div>

    <section class="rp-card rp-list" aria-label="Available reports">
      <table>
        <thead><tr>
          <th scope="col" style="width:220px">Report</th>
          <th scope="col">What's in it</th>
          <th scope="col" style="width:210px">Extra filter</th>
          <th scope="col" style="width:316px;text-align:right">Get it</th>
        </tr></thead>
        <tbody>
          @foreach ($reports as $r)
            <tr>
              <td><div class="nm">{{ $r['name'] }}@if ($r['new'])<span class="chip gold">New</span>@endif</div></td>
              <td class="ab">{{ $r['about'] }}</td>
              <td>
                @forelse ($r['extra'] as $field => [$label, $options])
                  <select name="{{ $field }}" form="f-{{ $r['key'] }}" class="rp-select" aria-label="{{ $r['name'] }}: {{ $label }}">
                    @foreach ($options as $v => $text)
                      <option value="{{ $v }}" @selected($v === $ctx[$field])>{{ $text }}</option>
                    @endforeach
                  </select>
                @empty
                  <span class="none">None</span>
                @endforelse
              </td>
              <td>
                <div class="acts">
                  <button type="submit" form="f-{{ $r['key'] }}" class="rp-btn dark">{!! $table !!}Open table</button>
                  <button type="submit" form="f-{{ $r['key'] }}" name="download" value="pdf" class="rp-btn gold" aria-label="Download {{ $r['name'] }} as PDF">PDF</button>
                  <button type="submit" form="f-{{ $r['key'] }}" name="download" value="xlsx" class="rp-btn" aria-label="Download {{ $r['name'] }} as Excel">Excel</button>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </section>
    <p class="rp-note">Open table shows the report on screen. PDF and Excel download it straight away; every PDF carries the college header and the period above.</p>
  </div>
  </div>
</div>

@include('capsa.partials.skeleton-toggle', ['revealDisplay' => 'flex'])
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var form = document.getElementById('period');
  form.querySelectorAll('select').forEach(function (select) {
    select.addEventListener('change', function () {
      // Another year has other semesters: let it open on its own running one.
      if (select.hasAttribute('data-resets-term')) form.querySelector('[name="term"]').disabled = true;
      form.submit();
    });
  });
})();
</script>
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>
