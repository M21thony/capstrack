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
.rp-period{padding:16px 20px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px 16px;align-items:end}
.rp-period-foot{grid-column:1/-1;margin:0;font-size:13px;color:var(--text3)}
.rp-period-foot b{color:var(--text)}
.rp-list{overflow:hidden}
.rp-list table{width:100%;border-collapse:collapse;font-size:13.5px}
.rp-list thead th{padding:11px 16px;background:var(--navy-dark);font-size:11px;font-weight:600;color:#d4d6de;letter-spacing:.06em;text-transform:uppercase;text-align:left}
.rp-list td{padding:12px 16px;border-bottom:1px solid var(--bg4);vertical-align:middle}
.rp-list tr.grp td{background:var(--bg3);font-weight:700;font-size:12.5px;padding:9px 16px}
.rp-list .nm{font-weight:600;white-space:nowrap}
.rp-list .ab{color:var(--text2)}
.rp-list .none{color:var(--text3)}
.rp-list .rp-select{width:180px}
.rp-list .acts{display:flex;gap:8px;justify-content:flex-end}
@media (max-width:980px){.rp-period{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:760px){
  .rp-list thead{display:none}
  .rp-list tbody tr{display:grid;gap:8px;padding:14px 16px;border-bottom:1px solid var(--bg4)}
  .rp-list tbody tr.grp{padding:0;border:0}
  .rp-list td{padding:0;border:0}
  .rp-list tr.grp td{padding:9px 16px}
  .rp-list td.ex:has(.none){display:none}
  .rp-list .rp-select{width:100%}
  .rp-list .acts{display:grid;grid-template-columns:2fr 1fr 1fr}
}
</style>
  @include('partials.render-whole')
</head>
<body>

@include('capsa._sidebar')
@include('partials.sidebar-toggle')
@php
  $table = '<svg class="i" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M9 4v16"/></svg>';
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
    <div class="sk" style="height:110px;border-radius:10px;margin-bottom:16px"></div>
    <div class="sk" style="height:420px;border-radius:10px"></div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content rp">
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
      <p class="rp-period-foot">Every report below uses this period: <b>{{ $teamCount }} {{ \Illuminate\Support\Str::plural('team', $teamCount) }}</b> in <b>{{ $sectionCount }} {{ \Illuminate\Support\Str::plural('section', $sectionCount) }}</b>.
        <noscript><button type="submit" class="rp-btn">Apply</button></noscript></p>
    </form>

    {{-- One form per report; the controls in its row join it through form="". --}}
    <div hidden>
    @foreach (collect($groups)->flatten(1) as $r)
      <form method="GET" action="{{ route('capsa.reports.show', $r['key']) }}" id="f-{{ $r['key'] }}">
        @foreach ($period as $k => $v)
          <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endforeach
      </form>
    @endforeach
    </div>

    <section class="rp-card rp-list" aria-label="Reports">
      <table>
        <thead><tr>
          <th scope="col" style="width:190px">Report</th>
          <th scope="col">What's in it</th>
          <th scope="col" style="width:212px">Filter</th>
          <th scope="col" style="width:250px;text-align:right">Get it</th>
        </tr></thead>
        <tbody>
          @foreach ($groups as $group => $reports)
            <tr class="grp"><td colspan="4">{{ $group }}</td></tr>
            @foreach ($reports as $r)
              <tr>
                <td class="nm">{{ $r['name'] }}</td>
                <td class="ab">{{ $r['about'] }}</td>
                <td class="ex">
                  @forelse ($r['extra'] as $field => [$label, $options])
                    <select name="{{ $field }}" form="f-{{ $r['key'] }}" class="rp-select" aria-label="{{ $r['name'] }}: {{ $label }}">
                      @foreach ($options as $v => $text)
                        <option value="{{ $v }}" @selected($v === $ctx[$field])>{{ $text }}</option>
                      @endforeach
                    </select>
                  @empty
                    <span class="none">—</span>
                  @endforelse
                </td>
                <td>
                  <div class="acts">
                    <button type="submit" form="f-{{ $r['key'] }}" class="rp-btn dark">{!! $table !!}Open</button>
                    <button type="submit" form="f-{{ $r['key'] }}" name="download" value="pdf" class="rp-btn gold" aria-label="Download {{ $r['name'] }} as PDF">PDF</button>
                    <button type="submit" form="f-{{ $r['key'] }}" name="download" value="xlsx" class="rp-btn" aria-label="Download {{ $r['name'] }} as Excel">Excel</button>
                  </div>
                </td>
              </tr>
            @endforeach
          @endforeach
        </tbody>
      </table>
    </section>
    <p class="rp-note">Open shows the report as a table you can sort, search and merge. PDF and Excel download it straight away, with the college header and the period on top.</p>
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
