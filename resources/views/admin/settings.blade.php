<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>System Settings - CapsTRACK Admin</title>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/table-kit.css') }}"/>
<style nonce="{{ Vite::cspNonce() }}">
  .st-page{max-width:1360px}
  .st-card{padding:0;overflow:hidden}
  .st-ic{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex:none}
  .st-h{display:flex;align-items:center;gap:10px;margin:0;font-size:15px;font-weight:700;color:var(--text)}
  .st-meta{font-size:12.5px;color:var(--text2)}
  .st-err{font-size:12px;color:var(--red);margin:6px 0 0}

  .sy-card{display:flex;align-items:center;gap:24px;padding:16px 22px;flex-wrap:wrap}
  .sy-card .st-h{min-width:170px}
  .sy-form{display:flex;gap:10px;align-items:center}
  .sy-form .input{width:170px}
  .sy-now{margin-left:auto}
  .sy-now b{color:var(--text)}

  .st-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,3fr);gap:14px;margin-top:14px;align-items:start}
  .st-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 20px;border-bottom:1px solid var(--border)}
  .st-body{padding:18px 20px;display:grid;gap:16px}
  .st-foot{display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid var(--border)}
  .st-label{display:block;font-size:12.5px;font-weight:600;color:var(--text2);margin-bottom:7px;padding:0}

  .opt-row{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
  .opt{display:flex;align-items:center;gap:9px;height:40px;padding:0 14px;box-sizing:border-box;
       border:1.5px solid var(--border);border-radius:9px;cursor:pointer;font-size:13.5px;font-weight:600;color:var(--text2);background:var(--bg2)}
  .opt:hover{border-color:var(--border2)}
  .opt input{width:16px;height:16px;accent-color:var(--accent3);margin:0;cursor:pointer}
  .opt:has(input:checked){border-color:var(--accent);background:var(--accent-light);color:var(--accent3)}
  .opt:has(input:focus-visible){outline:2px solid var(--accent);outline-offset:2px}
  .st-range{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
  .st-range .input{width:100%}
  .st-range .input[readonly]{background:var(--bg3);color:var(--text2)}

  .st-preview{border:1px solid var(--border);border-radius:10px;background:var(--bg3);padding:12px 14px;font-size:13px;color:var(--text2)}
  .st-preview-h{display:flex;justify-content:space-between;margin-bottom:8px;font-size:12.5px}
  .st-preview-h b{color:var(--text)}
  .st-line{display:flex;justify-content:space-between;align-items:center;padding:4px 0;color:var(--text)}
  .st-new{font-size:11.5px;font-weight:700;padding:2px 8px;border-radius:6px;background:var(--green-bg);color:var(--green)}
  .st-new.none{background:rgba(180,83,9,0.08);color:#b45309}
  .st-skip{margin:8px 0 0;color:#b45309;font-size:12.5px}

  .st-table td{padding:10px 16px;font-size:13.5px}
  .st-table th{padding:10px 16px;background:var(--navy-dark);color:#d4d6de}
  .st-table th:last-child,.st-table td:last-child{text-align:right}
  .sec-prog{display:block;font-weight:700;color:var(--text)}
  .sec-year{display:block;font-size:12px;color:var(--text3)}
  .st-secs{color:var(--text2);word-spacing:2px}
  .st-total{font-weight:700;color:var(--text);font-variant-numeric:tabular-nums}
  .st-rm{background:none;border:0;padding:0;font:inherit;font-weight:700;color:var(--red);cursor:pointer}
  .st-rm:hover{text-decoration:underline}
  .st-chk{width:44px}
  .st-chk input{width:16px;height:16px;margin:0;accent-color:var(--accent3);cursor:pointer;vertical-align:middle}

  .sd-crumb{font-size:13.5px;font-weight:700;margin:0 0 14px}
  .sd-crumb button{background:none;border:0;padding:0;font:inherit;font-weight:600;color:var(--text2);cursor:pointer}
  .sd-crumb button:hover{text-decoration:underline}
  .sd-crumb u{margin:0 6px;color:var(--text2);text-decoration:none}
  .sd-tools{display:flex;align-items:center;gap:12px}

  @media (max-width:1100px){.st-grid{grid-template-columns:1fr}}
  @media (max-width:700px){.opt-row{grid-template-columns:repeat(2,1fr)}.st-range{grid-template-columns:1fr}.sy-now{margin-left:0}}
</style>
  @include('partials.render-whole')
</head>
<body>

@include('capsa._sidebar')
@include('partials.sidebar-toggle')

@php
  $ordinal = fn ($y) => $y.([1 => 'st', 2 => 'nd', 3 => 'rd'][(int) $y] ?? 'th');
  // Year, then program, then that program's labels - "BSIT-3A" read back as its parts.
  $grouped = [];
  foreach ($sections as $label) {
      preg_match('/^([A-Z]+)-(\d+)/', $label, $m);
      $grouped[(int) ($m[2] ?? 0)][$m[1] ?? $label][] = $label;
  }
  ksort($grouped);
  $tabYears = array_unique(array_merge([3, 4], array_keys($grouped)));
  sort($tabYears);
  $programCount = count(array_unique(array_merge([], ...array_map('array_keys', array_values($grouped)))));
@endphp

<svg style="display:none" xmlns="http://www.w3.org/2000/svg">
  <symbol id="st-folder" viewBox="0 0 24 24"><path d="M3 7V4h7l3 3h8v13H3V7Z"/></symbol>
</svg>

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">System Settings</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div class="content">
   <div class="st-page">
    @if (session('success'))
      <div class="card" style="border-color:var(--green);background:var(--green-bg);margin-bottom:14px;padding:12px 16px;color:var(--green);font-size:13px">{{ session('success') }}</div>
    @endif

    <div id="stMain">
      <section class="card sy-card" aria-labelledby="sy-h">
        <h2 class="st-h" id="sy-h"><svg class="st-ic" style="color:#d9480f" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18"/></svg>School year</h2>
        <form class="sy-form" method="POST" action="{{ route('admin.settings.update') }}">
          @csrf
          @method('PUT')
          <label class="tk-vh" for="current_school_year">School year</label>
          <input class="input" type="text" id="current_school_year" name="current_school_year" value="{{ old('current_school_year', $currentSchoolYear) }}" placeholder="2026-2027">
          <button type="submit" class="tk-btn tk-btn-dark">Save changes</button>
        </form>
        <span class="st-meta sy-now">Current <b>{{ $currentSchoolYear ?: '—' }}</b></span>
      </section>
      @error('current_school_year')<p class="st-err">{{ $message }}</p>@enderror

      <div class="st-grid">
        <section class="card st-card" aria-labelledby="add-h">
          <header class="st-head">
            <h2 class="st-h" id="add-h"><svg class="st-ic" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>Add sections</h2>
            <span class="st-meta">{{ $ordinal($years[0]) }} year</span>
          </header>
          <form method="POST" action="{{ route('admin.settings.sections.store') }}" id="sectionForm" onreset="setTimeout(previewSections)">
            @csrf
            <div class="st-body">
              <fieldset style="border:0;margin:0;padding:0">
                <legend class="st-label">Programs</legend>
                <div class="opt-row">
                  @foreach ($programs as $program)
                    <label class="opt"><input type="checkbox" name="programs[]" value="{{ $program }}" onchange="previewSections()">{{ $program }}</label>
                  @endforeach
                </div>
                @error('programs')<p class="st-err">{{ $message }}</p>@enderror
              </fieldset>

              <div class="st-range">
                <label><span class="st-label">Year level</span><input class="input" readonly value="{{ $ordinal($years[0]) }} year"></label>
                <label><span class="st-label">From</span>
                  <select class="input" name="from" id="fromLetter" onchange="previewSections()">
                    @foreach ($letters as $l)<option @selected($l === 'A')>{{ $l }}</option>@endforeach
                  </select>
                </label>
                <label><span class="st-label">To</span>
                  <select class="input" name="to" id="toLetter" onchange="previewSections()">
                    @foreach ($letters as $l)<option @selected($l === 'D')>{{ $l }}</option>@endforeach
                  </select>
                </label>
              </div>
              @error('from')<p class="st-err">{{ $message }}</p>@enderror

              <div class="st-preview" id="sectionPreview" aria-live="polite"></div>
            </div>
            <footer class="st-foot">
              <button type="reset" class="tk-btn tk-btn-ghost">Reset</button>
              <button type="submit" class="tk-btn tk-btn-pri" id="addSections">Add sections</button>
            </footer>
          </form>
        </section>

        <section class="card st-card" aria-labelledby="list-h">
          <header class="st-head">
            <h2 class="st-h" id="list-h"><svg class="st-ic" style="color:#7c3aed"><use href="#st-folder"/></svg>Sections</h2>
            <span class="st-meta">{{ count($sections) }} {{ Str::plural('section', count($sections)) }} · {{ $programCount }} {{ Str::plural('program', $programCount) }}</span>
          </header>
          <div class="tk-toolbar">
            <div class="tk-tabs" role="tablist" aria-label="Year level">
              @foreach ($tabYears as $y)
                <button type="button" class="tk-tab" role="tab" data-year="{{ $y }}" aria-selected="{{ $y === 3 ? 'true' : 'false' }}" onclick="pickYear({{ $y }})">{{ $ordinal($y) }} year <span class="n">{{ count(array_merge([], ...array_values($grouped[$y] ?? []))) }}</span></button>
              @endforeach
            </div>
            <label class="experts-search" style="margin-left:auto;flex:0 1 260px">
              <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <span class="tk-vh">Search program or section</span>
              <input class="input" type="search" id="stSearch" placeholder="Search program or section" oninput="filterRows()">
            </label>
          </div>
          <table class="table st-table">
            <thead><tr><th style="width:22%">Program</th><th>Sections</th><th style="width:12%">Total</th><th style="width:110px"><span class="tk-vh">Actions</span></th></tr></thead>
            <tbody>
              @foreach ($grouped as $y => $byProgram)
                @foreach ($byProgram as $program => $labels)
                  <tr data-year="{{ $y }}" data-program="{{ $program }}" data-sections="{{ json_encode($labels) }}">
                    <td><span class="sec-prog">{{ $program }}</span><span class="sec-year">{{ $ordinal($y) }} year</span></td>
                    <td class="st-secs">{{ implode(' · ', array_map(fn ($s) => substr($s, strlen($program) + 1), $labels)) }}</td>
                    <td class="st-total">{{ count($labels) }}</td>
                    <td><button type="button" class="tk-btn tk-btn-sm tk-btn-dark" onclick="manage(this.closest('tr'))">Manage</button></td>
                  </tr>
                @endforeach
              @endforeach
              <tr id="stNone" hidden><td colspan="4" class="tk-empty">No sections here.</td></tr>
            </tbody>
          </table>
          <div class="tk-foot"><span id="stShown"></span></div>
        </section>
      </div>
    </div>

    <div id="stDetail" hidden>
      <nav class="sd-crumb" aria-label="Breadcrumb"><button type="button" onclick="closeManage()">System Settings</button><u>›</u><span id="sdCrumb"></span></nav>
      <section class="card st-card">
        <header class="st-head">
          <h2 class="st-h"><svg class="st-ic" style="color:#7c3aed"><use href="#st-folder"/></svg><span id="sdTitle"></span></h2>
          <div class="sd-tools">
            <span class="st-meta" id="sdCount">No sections selected</span>
            <button type="button" class="tk-btn" id="sdRemove" disabled onclick="removeSections([].map.call(document.querySelectorAll('.sd-pick:checked'), function (c) { return c.value; }))">Remove selected</button>
          </div>
        </header>
        <table class="table st-table">
          <thead><tr><th class="st-chk"><input type="checkbox" id="sdAll" aria-label="Select all sections" onchange="var on = this.checked; document.querySelectorAll('.sd-pick').forEach(function (c) { c.checked = on; }); countPicked()"></th><th>Section</th><th>Year level</th><th><span class="tk-vh">Actions</span></th></tr></thead>
          <tbody id="sdRows"></tbody>
        </table>
        <div class="tk-foot"><span id="sdFoot"></span></div>
      </section>
    </div>

    <form id="removeForm" method="POST" action="{{ route('admin.settings.sections.destroy') }}" hidden>@csrf @method('DELETE')</form>
   </div>
  </div>
</div>

<script nonce="{{ Vite::cspNonce() }}">
var SECTION_LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
var EXISTING = @json($sections);
var START_YEAR = @json($years[0]);
var year = 3;

/** What the range would add, per program, with the ones already listed taken out. */
function previewSections(){
  var programs = [].map.call(document.querySelectorAll('input[name="programs[]"]:checked'), function (i) { return i.value; });
  var from = SECTION_LETTERS.indexOf(document.getElementById('fromLetter').value);
  var to = SECTION_LETTERS.indexOf(document.getElementById('toLetter').value);
  var box = document.getElementById('sectionPreview');
  var button = document.getElementById('addSections');
  var total = 0, skip = 0, lines = '';

  if (!programs.length || from > to) {
    box.innerHTML = from > to
      ? '<span style="color:var(--red)">Choose an end letter after or equal to the start letter.</span>'
      : '<span>Select at least one program.</span>';
    button.disabled = true;
    button.textContent = 'Add sections';
    return;
  }

  programs.forEach(function (p) {
    var fresh = 0;
    for (var i = from; i <= to; i++) EXISTING.indexOf(p + '-' + START_YEAR + SECTION_LETTERS[i]) === -1 ? fresh++ : skip++;
    total += fresh;
    lines += '<div class="st-line"><span><b>' + p + '</b> · ' + START_YEAR + SECTION_LETTERS[from]
      + (to > from ? '–' + START_YEAR + SECTION_LETTERS[to] : '') + '</span>'
      + '<span class="st-new' + (fresh ? '' : ' none') + '">' + fresh + ' new</span></div>';
  });

  var noun = ' section' + (total === 1 ? '' : 's');
  box.innerHTML = '<div class="st-preview-h"><b>Preview</b><span>' + total + ' new' + noun + '</span></div>' + lines
    + (skip ? '<p class="st-skip">' + skip + ' already listed, skipped.</p>' : '');
  button.disabled = !total;
  button.textContent = 'Add ' + total + noun;
}

function pickYear(y){
  year = y;
  document.querySelectorAll('.tk-tab').forEach(function (t) { t.setAttribute('aria-selected', String(+t.dataset.year === y)); });
  filterRows();
}

function filterRows(){
  var q = document.getElementById('stSearch').value.trim().toUpperCase();
  var shown = 0;
  document.querySelectorAll('tr[data-program]').forEach(function (r) {
    var on = +r.dataset.year === year && (!q || r.dataset.sections.indexOf(q) !== -1 || r.textContent.toUpperCase().indexOf(q) !== -1);
    r.hidden = !on;
    shown += on;
  });
  document.getElementById('stNone').hidden = shown > 0;
  document.getElementById('stShown').textContent = 'Showing ' + shown + ' program' + (shown === 1 ? '' : 's');
}

/** One program's sections on their own page, breadcrumb back to the list. */
function manage(row){
  var labels = JSON.parse(row.dataset.sections);
  var yearText = row.querySelector('.sec-year').textContent;
  document.getElementById('sdCrumb').textContent = row.dataset.program + ' sections';
  document.getElementById('sdTitle').textContent = row.dataset.program + ' · ' + yearText;
  document.getElementById('sdFoot').textContent = labels.length + ' section' + (labels.length === 1 ? '' : 's');
  var tbody = document.getElementById('sdRows');
  tbody.replaceChildren();
  labels.forEach(function (s) {
    var tr = tbody.insertRow();
    tr.innerHTML = '<td class="st-chk"><input type="checkbox" class="sd-pick" onchange="countPicked()"></td><td></td><td></td>'
      + '<td><button type="button" class="st-rm">Remove</button></td>';
    var pick = tr.querySelector('.sd-pick');
    pick.value = s;
    pick.setAttribute('aria-label', 'Select ' + s);
    tr.cells[1].textContent = s;
    tr.cells[2].textContent = yearText;
    tr.querySelector('.st-rm').setAttribute('aria-label', 'Remove ' + s);
    tr.querySelector('.st-rm').onclick = function () { removeSections([s]); };
  });
  document.getElementById('sdAll').checked = false;
  countPicked();
  document.getElementById('stMain').hidden = true;
  document.getElementById('stDetail').hidden = false;
  openKey = row.dataset.year + row.dataset.program;
}

var openKey = null;
function closeManage(){
  document.getElementById('stDetail').hidden = true;
  document.getElementById('stMain').hidden = false;
  openKey = null;
}

function countPicked(){
  var n = document.querySelectorAll('.sd-pick:checked').length;
  document.getElementById('sdCount').textContent = n ? n + ' selected' : 'No sections selected';
  document.getElementById('sdRemove').disabled = !n;
}

function removeSections(list){
  confirmSheet({
    title: list.length === 1 ? 'Remove ' + list[0] + '?' : 'Remove ' + list.length + ' sections?',
    names: list.length > 1 ? list : [],
    body: 'A removed section can be added again.',
    confirmLabel: list.length === 1 ? 'Remove section' : 'Remove ' + list.length + ' sections',
    danger: true,
  }).then(function (yes) {
    if (!yes) return;
    var form = document.getElementById('removeForm');
    list.forEach(function (s) {
      var i = document.createElement('input');
      i.type = 'hidden'; i.name = 'section[]'; i.value = s;
      form.appendChild(i);
    });
    try { if (openKey) sessionStorage.setItem('st-open', openKey); } catch (e) {}
    form.submit();
  });
}

previewSections();
pickYear(3);
(function () {
  // Removing reloads the page; come back to the program that was open.
  var open = null;
  try { open = sessionStorage.getItem('st-open'); sessionStorage.removeItem('st-open'); } catch (e) {}
  var row = open && [].find.call(document.querySelectorAll('tr[data-program]'), function (r) { return r.dataset.year + r.dataset.program === open; });
  if (row) manage(row);
})();
</script>
@include('partials.app-dialog')
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>
