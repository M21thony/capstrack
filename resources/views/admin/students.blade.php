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
            <th style="width:40%">Student</th>
            <th class="tk-opt2" style="width:16%">Student no.</th>
            <th style="width:16%">Section</th>
            <th class="tk-opt" style="width:20%">Team</th>
            <th style="width:64px"><span class="tk-vh">Actions</span></th>
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
              <td data-label="Section" title="{{ $student->course }}">
                <span>
                  @if ($program)
                    <span class="tk-prog">{{ $program }}</span>
                  @elseif ($student->course)
                    <span class="tk-prog">{{ $student->course }}</span>
                  @endif
                  @if ($sectionShort !== '')
                    <span class="tk-secsoft">{{ $sectionShort }}</span>
                  @elseif (! $program && ! $student->course)
                    <span class="tk-empty">&mdash;</span>
                  @endif
                </span>
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
            <tr class="tk-note"><td colspan="5" style="padding:24px 20px;text-align:center;color:var(--text3);font-size:13px">
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

      {{-- Laravel's own pager is written for Tailwind, which this page does
           not load. Where you are, how many to a page, and the pages either
           side of this one. --}}
      @php
        $current = $students->currentPage();
        $last = $students->lastPage();
        $pageNumbers = collect([1, $last, $current - 1, $current, $current + 1])
            ->filter(fn ($n) => $n >= 1 && $n <= $last)
            ->unique()->sort()->values();
      @endphp
      <div class="tk-foot">
        @if ($students->total() > 0)
          <span>Showing <strong>{{ $students->firstItem() }}–{{ $students->lastItem() }}</strong> of <strong>{{ $students->total() }}</strong></span>
        @else
          <span>Showing <strong>0</strong></span>
        @endif
        <div class="tk-foot-end">
          <label class="tk-rows">Rows per page
            <select class="input tk-select" form="studentFilters" name="per_page" onchange="this.form.submit()">
              @foreach ($perPageOptions as $size)
                <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
              @endforeach
            </select>
          </label>
          @if ($students->hasPages())
            <nav class="tk-pg" aria-label="Pages">
              <a class="tk-pgn {{ $students->onFirstPage() ? 'off' : '' }}" href="{{ $students->previousPageUrl() ?? '#' }}" aria-label="Previous page" @if ($students->onFirstPage()) aria-disabled="true" tabindex="-1" @endif><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></a>
              @foreach ($pageNumbers as $i => $n)
                @if ($i > 0 && $n - $pageNumbers[$i - 1] > 1)<span class="tk-gap" aria-hidden="true">&hellip;</span>@endif
                <a class="tk-pgn" href="{{ $students->url($n) }}" @if ($n === $current) aria-current="page" @endif>{{ $n }}</a>
              @endforeach
              <a class="tk-pgn {{ $students->hasMorePages() ? '' : 'off' }}" href="{{ $students->nextPageUrl() ?? '#' }}" aria-label="Next page" @unless ($students->hasMorePages()) aria-disabled="true" tabindex="-1" @endunless><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></a>
            </nav>
          @endif
        </div>
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
</body>
</html>
