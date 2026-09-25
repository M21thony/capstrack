<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Student Accounts - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<style>
  /* ── Compact rows ────────────────────────────────────────────────────
     This list holds every student in the college, so the row height is the
     thing that decides how much of it you can see at once. */
  .table td{padding-top:8px;padding-bottom:8px;vertical-align:middle}
  .table thead th{position:sticky;top:0;z-index:2;background:var(--bg3)}
  .table tbody tr:hover{background:var(--accent-light)}
  .std-who{display:flex;align-items:center;gap:9px;min-width:0}
  .std-email{font-size:12px;color:var(--text3);max-width:230px;
             overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  /* Caps the column in the desktop table; on the phone card layout the same
     max-width left this row short of the card's own width, the same gap
     admin/experts' .cx-skills left behind before that got the same fix. */
  @media (max-width:768px){
    .table td.std-email{max-width:none}

    /* A phone card per student that reads at a glance: the name as its
       title, the email under it, and number, program, year and section as
       one line of chips - rather than six labelled rows a screen tall. */
    .std-table tbody tr{display:flex;flex-wrap:wrap;align-items:center;gap:6px}
    .std-table tbody td{width:auto;padding:0!important}
    .std-table tbody td::before{display:none}
    .std-table td[data-label="Name"]{order:1;flex:1 1 100%}
    .std-table td[data-label="Name"] .std-av{width:36px;height:36px;border-radius:10px}
    .std-table td[data-label="Name"] .std-name{font-size:14.5px;white-space:normal}
    .std-table td[data-label="Email"]{order:2;flex:1 1 100%;padding-left:44px!important;margin-top:-10px;
      font-size:12px;color:var(--text3)}
    .std-table td[data-label="Student No."],
    .std-table td[data-label="Program"],
    .std-table td[data-label="Year"],
    .std-table td[data-label="Section"]{order:3;margin-top:4px;font-size:11.5px;color:var(--text2);
      background:var(--bg3);border:1px solid var(--border);border-radius:999px;padding:3px 9px!important}
    .std-table td[data-label="Program"]{order:4;background:none;border:0;padding:0!important}
    .std-table td[data-label="Year"]{order:5}
    .std-table td[data-label="Section"]{order:6}
    .std-table td[data-label="Year"]::before{display:inline;content:'Year ';font-size:11px;font-weight:400;
      text-transform:none;letter-spacing:0;width:auto;color:var(--text3)}
    .std-table td[data-label="Section"]::before{display:inline;content:'Sec ';font-size:11px;font-weight:400;
      text-transform:none;letter-spacing:0;width:auto;color:var(--text3)}
    .std-table td[data-label="Student No."]::before{display:inline;content:'#';font-size:11px;font-weight:400;
      text-transform:none;letter-spacing:0;width:auto;color:var(--text3)}
  }
  /* Figures that line up down the column, which is all the old monospace
     was ever doing here. */
  .std-num{font-variant-numeric:tabular-nums;font-size:12px;color:var(--text2)}
  .std-prog{display:inline-block;padding:2px 7px;border-radius:6px;background:var(--blue-bg);
            color:var(--blue);font-size:11px;font-weight:600;white-space:nowrap}
  .std-none{color:var(--text3);font-size:12px}
  .std-ico{width:28px;height:28px;border-radius:6px;border:1px solid var(--border);background:var(--bg2);
           display:inline-flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text2);
           transition:border-color .12s,background .12s,color .12s}
  .std-ico svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2.2}
  .std-ico:hover{border-color:var(--border2);background:var(--bg3);color:var(--text)}
  .std-ico-danger{color:var(--red);border-color:rgba(220,38,38,0.2)}
  .std-ico-danger:hover{background:var(--red);border-color:var(--red);color:#fff}
  .std-actions{justify-content:flex-end}

  .std-av{width:26px;height:26px;border-radius:7px;flex-shrink:0;object-fit:cover}
  .std-actions{display:flex;gap:5px}
  .std-name{font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

  /* Pill icon-buttons, matching the same convention admin/experts.blade.php
     uses for its row actions. */
  .btn-pill{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:32px;font-size:12px;font-weight:600;padding:7px 14px;border-radius:999px;cursor:pointer;transition:transform 0.12s ease,box-shadow 0.12s ease,background 0.12s ease,border-color 0.12s ease,color 0.12s ease;user-select:none;border:1.5px solid transparent;white-space:nowrap;font-family:'DM Sans',sans-serif}
  .btn-pill svg{width:12px;height:12px;flex-shrink:0;stroke:currentColor;fill:none;stroke-width:2.2}
  .btn-pill:active{transform:translateY(1px)}
  .btn-edit{background:rgba(37,99,235,0.06);color:#2563eb;border-color:rgba(37,99,235,0.16)}
  .btn-edit:hover{background:rgba(37,99,235,0.1);border-color:rgba(37,99,235,0.28)}
  .btn-remove-pill{background:rgba(220,38,38,0.06);color:var(--red);border-color:rgba(220,38,38,0.16)}
  .btn-remove-pill:hover{background:rgba(220,38,38,0.1);border-color:rgba(220,38,38,0.28)}

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

  <div class="content list-page">
    {{-- Students belong to their CaPSA: they are enrolled from the section
         list and the roster import, both of which live on that side. An admin
         who could add or edit one here would be doing a job from a page with
         different powers behind it, so this is a register - search it, take a
         copy of it. Nothing on it destroys anything. --}}
    <div class="page-head">
      <div class="page-head-text">
        <span class="page-count">{{ $students->total() ?? $students->count() }}</span>
        <span class="page-count-noun">{{ Str::plural('student', $students->total() ?? $students->count()) }}</span>
        <span class="page-facts">
          <span class="fact">added and edited by their CaPSA</span>
        </span>
      </div>
    </div>

    {{-- One form, so narrowing by program and then by section keeps whatever
         was typed in the box instead of throwing it away. --}}
    <div class="page-filters">
      <form method="GET" id="studentFilters">
        <div class="experts-search">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input class="input" type="text" name="search" value="{{ $search }}" placeholder="Search by name, email, or student number...">
        </div>
        <select class="input page-filter" name="program" onchange="this.form.submit()">
          <option value="">All programs</option>
          @foreach (\App\Models\Student::PROGRAM_COURSES as $code => $fullName)
            <option value="{{ $code }}" @selected($program === $code)>{{ $code }}</option>
          @endforeach
        </select>
        <select class="input page-filter" name="year" onchange="this.form.submit()">
          <option value="">All years</option>
          @foreach ($years as $y)
            <option value="{{ $y }}" @selected($year === $y)>{{ $y }} year</option>
          @endforeach
        </select>
        <select class="input page-filter" name="section" onchange="this.form.submit()">
          <option value="">All sections</option>
          @foreach ($sections as $letter)
            <option value="{{ $letter }}" @selected($section === $letter)>Section {{ $letter }}</option>
          @endforeach
        </select>
      </form>
    </div>

    <div class="card list-card">
      <table class="table std-table">
        <thead>
          <tr>
            {{-- The unique key first: a student number names exactly one
                 person, and a name does not. --}}
            <th style="padding-left:20px">Student No.</th>
            <th>Name</th>
            <th>Email</th>
            <th>Program</th>
            <th>Year</th>
            <th>Section</th>
            <th aria-label="Actions"></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($students as $student)
            @php
              $studentPayload = [
                'id' => $student->student_id,
                'f_name' => $student->f_name,
                'm_name' => $student->m_name,
                'l_name' => $student->l_name,
                'suffix' => $student->suffix,
                'email' => $student->email,
                'student_number' => $student->student_number,
                'course' => $student->course,
                'year_section' => $student->year_section,
              ];
            @endphp
            <tr id="student-row-{{ $student->student_id }}">
              <td class="std-num" style="padding-left:20px" data-label="Student No.">{{ $student->student_number ?? '—' }}</td>
              <td data-label="Name">
                <div class="std-who">
                  <img src="{{ $student->avatarUrl() }}" alt="" loading="lazy" class="std-av"
                       onerror="this.onerror=null;this.src='{{ asset('images/default-avatar.jpg') }}';">
                  <span class="std-name">{{ trim($student->f_name.' '.$student->l_name) }}</span>
                </div>
              </td>
              <td class="std-email" title="{{ $student->email }}" data-label="Email">{{ $student->email }}</td>
              @php
                // Program, year and section are three things read down a
                // column. They were one string - the full course name and
                // "4th · B" run together - which reads as neither.
                $program = $student->course ? \App\Models\Student::mapCourseToProgram($student->course) : null;
                $ys = array_map('trim', explode('·', (string) $student->year_section));
                $year = count($ys) === 2 ? $ys[0] : '';
                $section = count($ys) === 2 ? $ys[1] : ($ys[0] ?? '');
              @endphp
              <td title="{{ $student->course }}" data-label="Program">
                @if ($program)
                  <span class="std-prog">{{ $program }}</span>
                @else
                  <span class="std-none">{{ $student->course ?: '—' }}</span>
                @endif
              </td>
              <td class="std-num" data-label="Year">{{ $year !== '' ? $year : '—' }}</td>
              <td class="std-num" data-label="Section">{{ $section !== '' ? $section : '—' }}</td>
              <td style="text-align:right;padding-right:16px">
                <button type="button" class="btn btn-sm btn-ghost std-remove"
                        data-url="{{ route('admin.students.destroy', $student->student_id) }}"
                        data-name="{{ trim($student->f_name.' '.$student->l_name) ?: $student->email }}"
                        data-row="student-row-{{ $student->student_id }}"
                        onclick="removeStudent(this)">Remove</button>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" style="padding:24px 20px;text-align:center;color:var(--text3);font-size:13px">No student accounts found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($students->hasPages())
      {{-- Laravel's own pager is written for Tailwind, which this page does
           not load, so its arrows render at full page height and push the
           table off the screen. Same three controls, this page's buttons. --}}
      <div class="list-foot">
        <span>Showing {{ $students->firstItem() }}–{{ $students->lastItem() }} of {{ $students->total() }}</span>
        <span class="list-foot-nav">
          @if ($students->onFirstPage())
            <span class="list-foot-off">Previous</span>
          @else
            <a class="btn btn-sm btn-ghost" href="{{ $students->previousPageUrl() }}">Previous</a>
          @endif
          <span>Page {{ $students->currentPage() }} of {{ $students->lastPage() }}</span>
          @if ($students->hasMorePages())
            <a class="btn btn-sm btn-ghost" href="{{ $students->nextPageUrl() }}">Next</a>
          @else
            <span class="list-foot-off">Next</span>
          @endif
        </span>
      </div>
    @endif
  </div>
</div>

{{-- Toast container --}}
<div id="ct-toast-container"></div>

<script>
const CSRF_TOKEN = '{{ csrf_token() }}';

// ── TOAST ──────────────────────────────────────────────
function showToast(message, type = 'success') {
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
    showToast(data.message, 'success');
  } catch (e) {
    button.disabled = false;
    showToast(e.message, 'error');
  }
}
</script>
@include('partials.app-dialog')
@include('partials.escape-closes-modals')
</body>
</html>
