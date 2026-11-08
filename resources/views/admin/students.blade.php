<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Student Accounts - CapsTRACK Admin</title>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/table-kit.css') }}"/>
<style nonce="{{ Vite::cspNonce() }}">
  /* ── Modal / Toast system (matches admin/experts.blade.php) ── */
  .ct-modal-overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,0.5);z-index:9998;align-items:center;justify-content:center;animation:ctFadeIn 0.15s ease;padding:20px}
  .ct-modal-card{background:#fff;border-radius:14px;padding:28px;max-width:420px;width:100%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.25);animation:ctPopUp 0.2s cubic-bezier(0.16,1,0.3,1)}
  .ct-modal-icon{width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px}
  .ct-modal-icon.danger{background:#fee2e2;color:#dc2626}
  .ct-modal-title{font-size:17px;font-weight:700;color:#0f172a;margin-bottom:8px}
  .ct-modal-desc{font-size:13px;color:#64748b;line-height:1.5;margin-bottom:20px}
  .ct-modal-label{display:block;font-size:12px;font-weight:600;color:#334155;margin-bottom:6px}
  .ct-modal-input{width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;outline:none;transition:border 0.15s}
  .ct-modal-input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,0.1)}
  .ct-modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;text-align:left;margin-bottom:14px}
  .ct-modal-grid .full{grid-column:1 / -1}
  .ct-modal-actions{display:flex;gap:10px;margin-top:4px}
  .ct-btn{flex:1;padding:10px 16px;font-size:13px;font-weight:600;border-radius:8px;border:none;cursor:pointer;transition:all 0.12s ease;display:inline-flex;align-items:center;justify-content:center}
  .ct-btn-secondary{background:#f1f5f9;color:#475569}
  .ct-btn-secondary:hover{background:#e2e8f0;color:#1e293b}
  .ct-btn-danger{background:#dc2626;color:#fff}
  .ct-btn-danger:hover:not(:disabled){background:#b91c1c}
  .ct-btn-primary{background:#2563eb;color:#fff}
  .ct-btn-primary:hover:not(:disabled){background:#1d4ed8}
  .ct-btn:disabled{background:#f1f5f9;color:#cbd5e1;cursor:not-allowed}
  @keyframes ctFadeIn{from{opacity:0}to{opacity:1}}
  @keyframes ctPopUp{from{transform:scale(0.94) translateY(8px);opacity:0}to{transform:scale(1) translateY(0);opacity:1}}
  #ct-toast-container{position:fixed;bottom:24px;right:24px;z-index:99999;display:flex;flex-direction:column;gap:10px;align-items:flex-end}
  .ct-toast{display:flex;align-items:flex-start;gap:12px;min-width:280px;max-width:360px;background:#fff;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.12),0 0 0 1px rgba(0,0,0,0.06);padding:14px 16px;animation:toastIn 0.25s cubic-bezier(0.16,1,0.3,1);transition:opacity 0.2s,transform 0.2s}
  .ct-toast.hiding{opacity:0;transform:translateX(20px)}
  .ct-toast-icon{width:36px;height:36px;flex-shrink:0;border-radius:8px;display:flex;align-items:center;justify-content:center}
  .ct-toast-icon.success{background:#dcfce7;color:#16a34a}
  .ct-toast-icon.error{background:#fee2e2;color:#dc2626}
  .ct-toast-body{flex:1}
  .ct-toast-title{font-size:13px;font-weight:700;color:#0f172a;margin:0}
  .ct-toast-msg{font-size:12px;color:#64748b;margin:2px 0 0}
  .ct-toast-close{background:none;border:none;color:#94a3b8;cursor:pointer;font-size:16px;line-height:1;padding:0}
  @keyframes toastIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}
  .sd-crumb{font-size:13.5px;font-weight:700;margin:0 0 14px}
  .sd-crumb button{background:none;border:0;padding:0;font:inherit;font-weight:600;color:var(--text2);cursor:pointer}
  .sd-crumb button:hover{text-decoration:underline}
  .sd-crumb u{margin:0 6px;color:var(--text2);text-decoration:none}
  #stdDetail{max-width:1150px}
  .sd-profile{display:flex;align-items:center;gap:14px;padding:18px;margin-bottom:14px}
  .sd-profile .tk-av{width:46px;height:46px;font-size:18px}
  .sd-profile h2{font-size:21px;margin:0}
  .sd-profile p{margin:3px 0 0;color:var(--text2);font-size:12px}
  .sd-id{display:flex;align-items:center;gap:14px;margin-left:auto;padding-left:18px;border-left:1px solid var(--border)}
  .sd-id small{display:block;color:var(--text2);font-size:11px}
  .sd-id strong{font-size:17px;font-variant-numeric:tabular-nums}
  .sd-grid{display:grid;grid-template-columns:minmax(0,.85fr) minmax(0,1.15fr);gap:14px}
  .sd-card{padding:18px}
  .sd-title{display:flex;align-items:center;gap:12px;padding-bottom:14px;border-bottom:1px solid var(--border);margin-bottom:16px}
  .sd-title h3{font-size:15px;margin:0}
  .sd-icon{width:35px;height:35px;border-radius:9px;display:grid;place-items:center;background:#edf3ff;color:#2563eb}
  .sd-icon.purple{background:#f3edff;color:#7c3aed}
  .sd-icon svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
  .sd-card dl{display:grid;grid-template-columns:120px 1fr;gap:12px;margin:0;font-size:13.5px}
  .sd-card dt{color:var(--text2)}
  .sd-card dd{margin:0;font-weight:600;color:var(--text)}
  .sd-team{font-size:21px;margin:0 0 12px}
  .sd-label{color:var(--text2);font-size:11px;margin-bottom:5px}
  .sd-project{font-size:15px;font-weight:600;line-height:1.55;margin:0}
  #sdCopyId svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2}
  @media (max-width:700px){.sd-grid{grid-template-columns:1fr}.sd-profile{flex-wrap:wrap}.sd-id{width:100%;margin-left:0;border-left:0;border-top:1px solid var(--border);padding:12px 0 0;justify-content:space-between}}
</style>
  @include('partials.render-whole')
</head>
<body>

@include('capsa._sidebar')
@include('partials.sidebar-toggle')

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Student Accounts</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div class="content list-page tk">
    {{-- Students belong to their CaPSA: they are enrolled from the section
         list and the roster import, both of which live on that side. An admin
         who could add or edit one here would be doing a job from a page with
         different powers behind it, so this is a register - search it, take a
         copy of it. The one change it makes is removing an account. --}}
    @php
      // Every tab link keeps the search and the dropdowns, and goes back to
      // the first page - page 9 of "everyone" is not a page of "no team".
      $tabUrl = fn (string $value) => route('admin.students', array_filter(
          array_merge(request()->except(['page', 'team']), ['team' => $value]),
          fn ($v) => $v !== '' && $v !== null
      ));
      $tabs = ['' => 'All', 'with' => 'In a team', 'without' => 'No team'];
      // Section ("BSIT-3A") => its CaPSA, built once - TeamChecklist::capsaFor
      // is a query per call. Lowest id wins, as it does there.
      $capsaOf = \App\Models\CapstoneExpert::whereNotNull('sections')->orderByDesc('expert_id')->get()
          ->reduce(fn ($map, $c) => array_merge($map, array_fill_keys($c->sections ?? [], $c->name)), []);
    @endphp
    <div class="page-head">
      <div class="tk-head-text">
        <div class="page-head-text">
          <span class="page-count" id="studentCount">{{ $teamCounts[''] }}</span>
          <span class="page-count-noun" id="studentCountNoun">{{ Str::plural('student', $teamCounts['']) }}</span>
        </div>
        <span class="tk-info"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/></svg>Added and edited by each section's CaPSA</span>
      </div>
      {{-- View Report and Download Excel, from partials/table-export. Taking a
           copy of the list is what this page is for, so Download Excel is the
           gold one here. --}}
      <div class="tk-actions tk-actions-export" id="tkActions"></div>
    </div>

    <div class="card list-card">
      {{-- One form, so narrowing by program and then by section keeps whatever
           was typed in the box instead of throwing it away. --}}
      <form method="GET" id="studentFilters" class="tk-toolbar">
        @if ($team !== '')<input type="hidden" name="team" value="{{ $team }}">@endif
        <nav class="tk-tabs" aria-label="Team">
          @foreach ($tabs as $value => $label)
            <a class="tk-tab" href="{{ $tabUrl($value) }}" @if ($team === $value) aria-current="page" @endif>{{ $label }} <span class="n">{{ $teamCounts[$value] }}</span></a>
          @endforeach
        </nav>
        <label class="experts-search">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <span class="tk-vh">Search students</span>
          {{-- Searches a moment after typing stops, the same way the
               dropdowns apply as soon as they change. The page reloads to do
               it, so the box takes the cursor back with the caret at the end. --}}
          <input class="input" type="text" name="search" value="{{ $search }}" placeholder="Name, email or student number"
                 @if ($search !== '') autofocus onfocus="this.setSelectionRange(this.value.length, this.value.length)" @endif
                 oninput="clearTimeout(this._t); this._t = setTimeout(() => this.form.submit(), 500)">
        </label>
        <div class="tk-toolbar-end">
          <label class="tk-vh" for="filterProgram">Program</label>
          <select class="input tk-select" id="filterProgram" name="program" onchange="this.form.submit()">
            <option value="">All programs</option>
            @foreach (\App\Models\Student::PROGRAM_COURSES as $code => $fullName)
              <option value="{{ $code }}" @selected($program === $code)>{{ $code }}</option>
            @endforeach
          </select>
          <label class="tk-vh" for="filterYear">Year</label>
          <select class="input tk-select" id="filterYear" name="year" onchange="this.form.submit()">
            <option value="">All years</option>
            @foreach ($years as $y)
              <option value="{{ $y }}" @selected($year === $y)>{{ $y }} year</option>
            @endforeach
          </select>
          <label class="tk-vh" for="filterSection">Section</label>
          <select class="input tk-select" id="filterSection" name="section" onchange="this.form.submit()">
            <option value="">All sections</option>
            @foreach ($sections as $letter)
              <option value="{{ $letter }}" @selected($section === $letter)>Section {{ $letter }}</option>
            @endforeach
          </select>
          @if ($search !== '' || $program || $year || $section || $team !== '')
            <a class="tk-btn tk-btn-ghost" href="{{ route('admin.students') }}">Clear filters</a>
          @endif
        </div>
      </form>

      <div class="tk-scroll">
      <table class="table std-table" data-export="Student Accounts" data-export-into="#tkActions">
        <thead>
          <tr>
            <th style="width:32%">Student</th>
            <th class="tk-opt2" style="width:14%">Student no.</th>
            <th style="width:10%">Program</th>
            <th style="width:10%">Section</th>
            <th class="tk-opt">Team</th>
            <th style="width:170px"><span class="tk-vh">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($students as $student)
            @php
              $fullName = trim($student->f_name.' '.$student->l_name);
              // Program and section the way every other screen writes them -
              // BSIT and 3A - rather than the full course name and "3rd · A"
              // across two columns.
              $program = $student->course ? \App\Models\Student::mapCourseToProgram($student->course) : null;
              $ys = array_map('trim', explode('·', (string) $student->year_section));
              $year = count($ys) === 2 ? $ys[0] : '';
              $section = count($ys) === 2 ? $ys[1] : ($ys[0] ?? '');
              $sectionShort = trim(preg_replace('/[^0-9]/', '', $year).$section);
              $teamName = $student->studentSchoolYear->first()?->team?->team_name;
            @endphp
            <tr id="student-row-{{ $student->student_id }}">
              <td class="tk-lead" data-label="Student">
                <div class="tk-who">
                  @include('partials.table-kit.avatar', [
                    'image' => $student->avatarUrl(),
                    'initials' => $student->avatarInitials(),
                    'seed' => $student->student_id,
                  ])
                  <span class="tk-who-text">
                    <span class="tk-name">{{ $fullName ?: $student->email }}</span>
                    <span class="tk-sub" title="{{ $student->email }}">{{ $student->email }}</span>
                  </span>
                </div>
              </td>
              <td class="tk-opt2" data-label="Student no."><span class="tk-num">{{ $student->student_number ?? '—' }}</span></td>
              <td data-label="Program" title="{{ $student->course }}">
                @if ($program || $student->course)
                  <span class="tk-prog">{{ $program ?: $student->course }}</span>
                @else
                  <span class="tk-empty">&mdash;</span>
                @endif
              </td>
              <td data-label="Section">
                @if ($sectionShort !== '')
                  <span class="tk-secsoft">{{ $sectionShort }}</span>
                @else
                  <span class="tk-empty">&mdash;</span>
                @endif
              </td>
              <td class="tk-opt" data-label="Team">
                @if ($teamName)
                  <span class="tk-team"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>{{ $teamName }}</span>
                @else
                  <span class="tk-pill tk-p-neutral"><span class="tk-dot"></span>No team</span>
                @endif
              </td>
              {{-- Remove is in the menu, under a line, not a red button on every
                   row: thirty of them down the page made the one destructive
                   thing here the loudest. --}}
              <td class="tk-ac" data-label="Actions">
                <div class="tk-acts">
                  <button type="button" class="tk-btn tk-btn-sm tk-btn-dark" onclick="showProfile(this)"
                          data-num="{{ $student->student_number }}" data-prog="{{ $program ?: $student->course }}"
                          data-year="{{ $year !== '' ? $year.' year' : '' }}" data-sec="{{ $program && $sectionShort !== '' ? $program.'-'.$sectionShort : $sectionShort }}"
                          data-capsa="{{ $capsaOf[$program.'-'.$sectionShort] ?? '' }}" data-team="{{ $teamName ?? 'No team' }}"
                          data-title="{{ $student->studentSchoolYear->first()?->team?->project_title }}">View profile</button>
                  <div class="rowmenu">
                    <button type="button" class="icon-btn rowmenu-btn" aria-expanded="false"
                            aria-label="Actions for {{ $fullName ?: $student->email }}">
                      <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.7" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.7" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.7" fill="currentColor" stroke="none"/></svg>
                    </button>
                    <div class="rowmenu-pop" hidden>
                      <button type="button" class="rowmenu-item" data-email="{{ $student->email }}" onclick="copyEmail(this)">
                        <svg viewBox="0 0 24 24"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg> Copy email
                      </button>
                      <div class="rowmenu-sep"></div>
                      <button type="button" class="rowmenu-item danger std-remove"
                              data-url="{{ route('admin.students.destroy', $student->student_id) }}"
                              data-name="{{ $fullName ?: $student->email }}"
                              data-row="student-row-{{ $student->student_id }}"
                              onclick="removeStudent(this)">
                        <svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg> Remove student&hellip;
                      </button>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          @empty
            @php
              // Say what was looked for, so an empty table reads as "nobody
              // matched" rather than "there are no students".
              $lookedFor = collect([
                  $search !== '' ? '"'.$search.'"' : null,
                  $program ?: null,
                  $year ? $year.' year' : null,
                  $section ? 'Section '.$section : null,
                  $team === 'with' ? 'in a team' : ($team === 'without' ? 'without a team' : null),
              ])->filter();
            @endphp
            <tr class="tk-note"><td colspan="6"style="padding:24px 20px;text-align:center;color:var(--text3);font-size:13px">
              @if ($lookedFor->isEmpty())
                No student accounts yet.
              @else
                No students match {{ $lookedFor->implode(', ') }}.
                <a href="{{ route('admin.students') }}">Clear filters</a>
              @endif
            </td></tr>
          @endforelse
        </tbody>
      </table>
      </div>

      <div class="tk-foot">
        <span>Showing <strong>{{ $students->count() }}</strong> {{ Str::plural('student', $students->count()) }}</span>
      </div>
    </div>

    <div id="stdDetail" hidden>
      <nav class="sd-crumb" aria-label="Breadcrumb"><button type="button" onclick="closeProfile()">Student Accounts</button><u>›</u><span id="sdName"></span></nav>
      <section class="card sd-profile" aria-label="Student profile">
        <span id="sdAvatar"></span>
        <div><h2 id="sdFull"></h2><p id="sdEmail"></p></div>
        <div class="sd-id">
          <div><small>Student ID</small><strong id="sd-num"></strong></div>
          <button type="button" class="icon-btn" id="sdCopyId" aria-label="Copy student ID"><svg viewBox="0 0 24 24"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg></button>
        </div>
      </section>
      <div class="sd-grid">
        <section class="card sd-card">
          <div class="sd-title"><span class="sd-icon"><svg viewBox="0 0 24 24"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/></svg></span><h3>Academic information</h3></div>
          <dl><dt>Program</dt><dd id="sd-prog"></dd><dt>Year level</dt><dd id="sd-year"></dd><dt>Section</dt><dd id="sd-sec"></dd><dt>Managed by</dt><dd id="sd-capsa"></dd></dl>
        </section>
        <section class="card sd-card">
          <div class="sd-title"><span class="sd-icon purple"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><h3>Team membership</h3></div>
          <p class="sd-team" id="sd-team"></p>
          <div id="sdProject"><div class="sd-label">Project title</div><p class="sd-project" id="sd-title"></p></div>
        </section>
      </div>
    </div>
  </div>
</div>

{{-- Toast container --}}
<div id="ct-toast-container"></div>

<script nonce="{{ Vite::cspNonce() }}">
const CSRF_TOKEN = '{{ csrf_token() }}';

// ── TOAST ──────────────────────────────────────────────
function showToast(message, type = 'success') {
  if (window.ctToast) { return ctToast(message, type); }
  const icons = {
    success: `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>`,
    error: `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`,
  };
  const toast = document.createElement('div');
  toast.className = 'ct-toast';
  toast.innerHTML = `
    <div class="ct-toast-icon ${type}">${icons[type] || icons.success}</div>
    <div class="ct-toast-body">
      <p class="ct-toast-title">${type === 'error' ? 'Error' : 'Success'}</p>
      <p class="ct-toast-msg"></p>
    </div>
    <button class="ct-toast-close" onclick="this.closest('.ct-toast').remove()">&times;</button>
  `;
  // Names are data, not markup.
  toast.querySelector('.ct-toast-msg').textContent = message;
  document.getElementById('ct-toast-container').appendChild(toast);
  setTimeout(() => {
    toast.classList.add('hiding');
    setTimeout(() => toast.remove(), 200);
  }, 4000);
}


// ── COPY EMAIL ─────────────────────────────────────────
async function copyEmail(button) {
  try {
    await navigator.clipboard.writeText(button.dataset.email);
    showToast(button.dataset.email + ' copied.', 'success');
  } catch (e) {
    showToast('Could not copy the address.', 'error');
  }
}


// ── PROFILE ────────────────────────────────────────────
// Everything it shows is already on the row, so it swaps views in place;
// the list stays loaded and Back returns to the same scroll spot.
let listScroll = 0;
function profileOpen(open) {
  document.querySelector('.page-head').style.display = open ? 'none' : '';
  document.querySelector('.list-card').style.display = open ? 'none' : '';
  document.getElementById('stdDetail').hidden = !open;
}
function showProfile(btn) {
  const row = btn.closest('tr');
  listScroll = document.querySelector('.tk-scroll').scrollTop;
  const name = row.querySelector('.tk-name').textContent;
  document.getElementById('sdAvatar').replaceChildren(row.querySelector('.tk-av').cloneNode(true));
  document.getElementById('sdName').textContent = name;
  document.getElementById('sdFull').textContent = name;
  document.getElementById('sdEmail').textContent = row.querySelector('.tk-sub').textContent;
  for (const k of ['num', 'prog', 'year', 'sec', 'capsa', 'team', 'title']) {
    document.getElementById('sd-' + k).textContent = btn.dataset[k] || '—';
  }
  document.getElementById('sdProject').hidden = !btn.dataset.title;
  document.getElementById('sdCopyId').hidden = !btn.dataset.num;
  profileOpen(true);
}
document.getElementById('sdCopyId').addEventListener('click', async () => {
  const id = document.getElementById('sd-num').textContent;
  try { await navigator.clipboard.writeText(id); showToast(id + ' copied.', 'success'); }
  catch (e) { showToast('Could not copy the student ID.', 'error'); }
});
function closeProfile() {
  profileOpen(false);
  document.querySelector('.tk-scroll').scrollTop = listScroll;
}


// ── REMOVE ─────────────────────────────────────────────
// For good: the account, and their place on their team. Asked first, in the
// app's own dialog, with Cancel as the reflex.
async function removeStudent(button) {
  const name = button.dataset.name;
  const yes = await confirmSheet({
    title: 'Remove ' + name + '?',
    body: 'Their account is deleted and they leave their team. If they lead it, the next member takes over; if no one else is on it, the team is removed too. This cannot be undone.',
    confirmLabel: 'Remove student',
    danger: true,
  });
  if (!yes) return;

  button.disabled = true;
  try {
    const res = await fetch(button.dataset.url, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.success) throw new Error(data.message || 'Could not remove ' + name + '.');
    document.getElementById(button.dataset.row)?.remove();
    const count = document.getElementById('studentCount');
    const left = Math.max(0, parseInt(count.textContent, 10) - 1);
    count.textContent = left;
    document.getElementById('studentCountNoun').textContent = left === 1 ? 'student' : 'students';
    showToast(data.message, 'success');
  } catch (e) {
    button.disabled = false;
    showToast(e.message, 'error');
  }
}
</script>
@include('partials.row-menu')
@include('partials.app-dialog')
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>
