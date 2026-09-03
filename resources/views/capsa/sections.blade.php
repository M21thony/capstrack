<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Student Section List - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<style>
  .ct-modal-overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,0.5);z-index:9998;align-items:center;justify-content:center;animation:ctFadeIn 0.15s ease;padding:20px}
  .ct-modal-card{background:#fff;border-radius:14px;padding:28px;max-width:420px;width:100%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.25);animation:ctPopUp 0.2s cubic-bezier(0.16,1,0.3,1)}
  .ct-modal-title{font-size:17px;font-weight:700;color:#0f172a;margin-bottom:8px}
  .ct-modal-desc{font-size:13px;color:#64748b;line-height:1.5;margin-bottom:16px}
  .ct-modal-input{width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;outline:none;margin-bottom:14px}
  .ct-modal-actions{display:flex;gap:10px;margin-top:4px}
  .ct-modal-card-wide{max-width:900px;text-align:left}
  /* Deliberately the loudest thing in the modal: a CaPSA who misses it types
     nine headings by hand, and one of them subtly wrong reads as no column. */
  .ct-template-card{display:flex;align-items:center;gap:13px;margin:0 0 16px;padding:15px 16px;border-radius:11px;
                    text-decoration:none;background:#0f172a;color:#fff;transition:background .15s}
  .ct-template-card:hover{background:#1e293b}
  .ct-template-icon{display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;
                    background:rgba(255,255,255,0.12);flex-shrink:0}
  .ct-template-text{display:flex;flex-direction:column;gap:2px;text-align:left}
  .ct-template-title{font-size:14px;font-weight:700}
  .ct-template-sub{font-size:11.5px;color:rgba(255,255,255,0.7)}
  .ct-preview-summary{display:flex;gap:16px;flex-wrap:wrap;font-size:12.5px;color:#475569;margin-bottom:12px}
  .ct-preview-summary strong{color:#0f172a}
  .ct-preview-scroll{max-height:340px;overflow:auto;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:14px}
  .ct-preview-table{width:100%;border-collapse:collapse;font-size:12px}
  .ct-preview-table th{position:sticky;top:0;background:#f8fafc;text-align:left;padding:8px 10px;font-weight:600;
                       color:#475569;border-bottom:1px solid #e2e8f0;white-space:nowrap}
  .ct-preview-table td{padding:7px 10px;border-bottom:1px solid #f1f5f9;color:#0f172a;white-space:nowrap}
  .ct-preview-table tr:last-child td{border-bottom:none}
  /* The badge is what a CaPSA scans the list by, so the three outcomes have
     to be tellable apart at a glance and not only by their wording. */
  .ct-tag{display:inline-block;padding:2px 8px;border-radius:6px;font-size:10.5px;font-weight:600;white-space:nowrap}
  .ct-tag-create{background:rgba(22,163,74,0.1);color:#15803d}
  .ct-tag-update{background:rgba(217,119,6,0.12);color:#b45309}
  .ct-tag-same{background:rgba(107,114,128,0.1);color:#64748b}
  .ct-preview-changes{color:#64748b;font-size:11px}
  .ct-btn{flex:1;padding:10px 16px;font-size:13px;font-weight:600;border-radius:8px;border:none;cursor:pointer;transition:all 0.12s ease}
  .ct-btn-secondary{background:#f1f5f9;color:#475569}
  .ct-btn-secondary:hover{background:#e2e8f0;color:#1e293b}
  .ct-btn-primary{background:#2563eb;color:#fff}
  .ct-btn-primary:hover:not(:disabled){background:#1d4ed8}
  .ct-btn:disabled{background:#f1f5f9;color:#cbd5e1;cursor:not-allowed}
  @keyframes ctFadeIn{from{opacity:0}to{opacity:1}}
  @keyframes ctPopUp{from{transform:scale(0.94) translateY(8px);opacity:0}to{transform:scale(1) translateY(0);opacity:1}}
  #ct-toast-container{position:fixed;bottom:24px;right:24px;z-index:99999;display:flex;flex-direction:column;gap:10px;align-items:flex-end}
  .ct-toast{display:flex;align-items:flex-start;gap:12px;min-width:280px;max-width:360px;background:#fff;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.12),0 0 0 1px rgba(0,0,0,0.06);padding:14px 16px;animation:toastIn 0.25s cubic-bezier(0.16,1,0.3,1)}
  .ct-toast.hiding{opacity:0;transform:translateX(20px);transition:all .2s}
  .ct-toast-icon{width:36px;height:36px;flex-shrink:0;border-radius:8px;display:flex;align-items:center;justify-content:center}
  .ct-toast-icon.success{background:#dcfce7;color:#16a34a}
  .ct-toast-icon.error{background:#fee2e2;color:#dc2626}
  .ct-toast-body{flex:1}
  .ct-toast-title{font-size:13px;font-weight:700;color:#0f172a;margin:0}
  .ct-toast-msg{font-size:12px;color:#64748b;margin:2px 0 0}
  .ct-toast-close{background:none;border:none;color:#94a3b8;cursor:pointer;font-size:16px;line-height:1;padding:0}
  @keyframes toastIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}
</style>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
</head>
<body>

@php
  $viewerIsCapsa = (bool) Auth::guard('expert')->user()?->is_capsa;
@endphp

@include('capsa._sidebar')
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Student Section List</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="card">
      <div class="sk" style="width:220px;height:18px;border-radius:4px;margin-bottom:18px"></div>
      <div class="sk" style="height:40px;border-radius:8px;margin-bottom:8px"></div>
      <div class="sk" style="height:40px;border-radius:8px;margin-bottom:8px"></div>
      <div class="sk" style="height:40px;border-radius:8px;margin-bottom:8px"></div>
      <div class="sk" style="height:40px;border-radius:8px"></div>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content">
    <div class="card">
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div>
          <span class="card-title">Students in Your Sections</span>
          @if (!empty($adminSections))
            <div style="font-size:12px;color:var(--text3)">Filtered by: {{ implode(', ', $adminSections) }}</div>
          @endif
        </div>

        <div style="display:flex;align-items:center;gap:8px">
          @if ($viewerIsCapsa)
          <button type="button" class="btn" onclick="openStudentRosterModal()">Upload List</button>
          @endif
          <form method="get" id="sectionFilterForm">
            <label for="section" style="font-size:13px;color:var(--text2);margin-right:8px">Section</label>
            <select name="section" id="section" onchange="document.getElementById('sectionFilterForm').submit()" style="padding:6px 10px;border-radius:8px;border:1px solid var(--muted);background:#fff">
              <option value="">All (assigned)</option>
              @foreach ($allSections as $section)
                <option value="{{ $section }}" {{ request()->query('section') == $section ? 'selected' : '' }}>{{ $section }}</option>
              @endforeach
            </select>
          </form>
        </div>
      </div>

      <div style="overflow-x:auto">
        @if (! empty($debug))
          <div style="padding:12px;background:#fff6; border:1px dashed var(--muted);margin-bottom:12px;border-radius:8px;font-size:13px">
            <strong>Debug:</strong>
            <div style="font-size:12px;color:var(--text3)">SY records: {{ $debug['sy_count'] }} — Admin sections: {{ implode(', ', $debug['adminSections'] ?? []) }} — Admin programs: {{ implode(', ', $debug['adminPrograms'] ?? []) }}</div>
            <div style="margin-top:8px;overflow:auto;max-height:160px">
              <table style="width:100%;font-size:12px;border-collapse:collapse">
                <thead><tr><th style="text-align:left;padding:6px;border-bottom:1px solid #eee">Student ID</th><th style="text-align:left;padding:6px;border-bottom:1px solid #eee">Team</th><th style="text-align:left;padding:6px;border-bottom:1px solid #eee">Program</th><th style="text-align:left;padding:6px;border-bottom:1px solid #eee">Year Section Raw</th><th style="text-align:left;padding:6px;border-bottom:1px solid #eee">Computed Label</th></tr></thead>
                <tbody>
                  @foreach ($debug['sample'] as $row)
                    <tr>
                      <td style="padding:6px;border-bottom:1px solid #f6f6f6">{{ $row['student_id'] }}</td>
                      <td style="padding:6px;border-bottom:1px solid #f6f6f6">{{ $row['team_id'] }}</td>
                      <td style="padding:6px;border-bottom:1px solid #f6f6f6">{{ $row['program'] }}</td>
                      <td style="padding:6px;border-bottom:1px solid #f6f6f6">{{ $row['year_section_raw'] }}</td>
                      <td style="padding:6px;border-bottom:1px solid #f6f6f6">{{ $row['computed_label'] }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        @endif
        <table class="table">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th>Student Name</th>
              <th>Program</th>
              <th>Year</th>
              <th>Section</th>
              <th>Team</th>
              <th>School Year</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($students as $index => $student)
              @php $sy = $student->currentSy; @endphp
              <tr>
                <td>{{ $index + 1 }}</td>
                <td style="font-weight:500">{{ $student->l_name }}, {{ $student->f_name }} {{ $student->m_name ? $student->m_name[0] . '.' : '' }}</td>
                <td>{{ $student->programAbbr ?? '—' }}</td>
                {{-- year_section is stored joined - "4th · B" - because that is
                     the shape every section lookup matches on. It is shown apart
                     here: Program, Year and Section are three things a CaPSA
                     reads down a column, not one string to pick through. --}}
                @php
                  $ys = array_map('trim', explode('·', (string) $student->year_section));
                  $year = count($ys) === 2 ? $ys[0] : '';
                  $section = count($ys) === 2 ? $ys[1] : ($ys[0] ?? '');
                @endphp
                <td>{{ $year !== '' ? $year : '—' }}</td>
                <td>{{ $section !== '' ? $section : '—' }}</td>
                <td>
                  @if ($sy && $sy->team)
                    {{ $sy->team->team_name ?? 'Team #' . $sy->team->team_id }}
                  @else
                    <span style="color:var(--text3)">—</span>
                  @endif
                </td>
                <td>{{ $sy->school_year ?? '—' }}</td>
                <td>
                  @if ($student->isOnline())
                    <span style="padding:3px 8px;border-radius:6px;font-size:11px;font-weight:600;background:rgba(22,163,74,0.1);color:var(--green)">Active</span>
                  @else
                    <span style="padding:3px 8px;border-radius:6px;font-size:11px;font-weight:600;background:rgba(107,114,128,0.1);color:var(--text3)">Offline</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8" style="text-align:center;padding:40px 0;color:var(--text3);font-size:13px">
                  @if (empty($adminSections))
                    No sections assigned yet. <a href="{{ route('admin.profile') }}" style="color:var(--accent)">Set your sections</a> to view students.
                  @else
                    No students found for the assigned sections.
                  @endif
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
  </div>
</div>
@include('capsa.partials.skeleton-toggle')

<script>
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() {
  sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar();
}
function openSidebar() {
  sidebarEl.classList.add('mobile-open');
  sidebarOverlay.classList.add('open');
}
function closeSidebar() {
  sidebarEl.classList.remove('mobile-open');
  sidebarOverlay.classList.remove('open');
}
</script>

@if ($viewerIsCapsa)
{{-- ══════════════════════════════════════════
     UPLOAD STUDENT ROSTER — Modal
══════════════════════════════════════════ --}}
<div id="ct-toast-container"></div>
<div id="studentRosterModal" class="ct-modal-overlay">
  <div class="ct-modal-card" id="studentRosterCard">
    <h3 class="ct-modal-title">Upload Student List</h3>

    <div id="studentRosterStep1">
      {{-- The template is the first thing in the modal and the largest thing
           in it. The columns it carries used to be described here in prose,
           which is the same information in the form nobody reads before
           reaching for the file picker below it. --}}
      <a class="ct-template-card" href="{{ route('admin.rosters.students.template') }}">
        <span class="ct-template-icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        </span>
        <span class="ct-template-text">
          <span class="ct-template-title">Download the template</span>
          <span class="ct-template-sub">Fill it in, then upload it below</span>
        </span>
      </a>

      <input type="file" id="studentRosterFile" class="ct-modal-input" accept=".xlsx">
      <div id="studentRosterResult" style="display:none;font-size:12px;text-align:left;margin:-6px 0 16px;"></div>
      <div class="ct-modal-actions">
        <button type="button" class="ct-btn ct-btn-secondary" onclick="closeStudentRosterModal()">Cancel</button>
        <button type="button" id="studentRosterPreviewBtn" class="ct-btn ct-btn-primary" onclick="previewStudentRoster()">Preview</button>
      </div>
    </div>

    {{-- Nothing has been written at this point. The upload rewrites course,
         section and school year on every account it matches, and there is no
         undo, so the list is shown as it will be applied before it is. --}}
    <div id="studentRosterStep2" style="display:none">
      <div class="ct-preview-summary" id="studentRosterSummary"></div>
      <div class="ct-preview-scroll"><table class="ct-preview-table" id="studentRosterPreviewTable"></table></div>
      <div id="studentRosterErrors" style="display:none"></div>
      <div id="studentRosterSaveResult" style="display:none;font-size:12px;margin-bottom:12px"></div>
      <div class="ct-modal-actions">
        <button type="button" class="ct-btn ct-btn-secondary" onclick="backToStudentRosterFile()">Choose another file</button>
        <button type="button" id="studentRosterUploadBtn" class="ct-btn ct-btn-primary" onclick="uploadStudentRoster()">Save to system</button>
      </div>
    </div>
  </div>
</div>

<script>
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
      <p class="ct-toast-msg">${message}</p>
    </div>
    <button class="ct-toast-close" onclick="this.closest('.ct-toast').remove()">&times;</button>
  `;
  document.getElementById('ct-toast-container').appendChild(toast);
  setTimeout(() => { toast.classList.add('hiding'); setTimeout(() => toast.remove(), 200); }, 4000);
}

const studentRosterModal = document.getElementById('studentRosterModal');

function openStudentRosterModal() {
  document.getElementById('studentRosterFile').value = '';
  document.getElementById('studentRosterResult').style.display = 'none';
  backToStudentRosterFile();
  studentRosterModal.style.display = 'flex';
}

function closeStudentRosterModal() {
  studentRosterModal.style.display = 'none';
}

/** Back to choosing a file, and back to the narrow card that step needs. */
function backToStudentRosterFile() {
  document.getElementById('studentRosterStep1').style.display = '';
  document.getElementById('studentRosterStep2').style.display = 'none';
  document.getElementById('studentRosterCard').classList.remove('ct-modal-card-wide');
}

/** Text from the file, put on the page as text and never as markup. */
function escRoster(value) {
  const box = document.createElement('div');
  box.textContent = value == null ? '' : String(value);
  return box.innerHTML;
}

const ROSTER_ACTIONS = {
  create: ['ct-tag-create', 'New account'],
  update: ['ct-tag-update', 'Update'],
  unchanged: ['ct-tag-same', 'No change'],
};

/** What the file would do, read back and shown before anything is written. */
function previewStudentRoster() {
  const fileInput = document.getElementById('studentRosterFile');
  const resultBox = document.getElementById('studentRosterResult');

  if (!fileInput.files.length) {
    resultBox.style.display = 'block';
    resultBox.innerHTML = '<span style="color:#dc2626">Choose a file first.</span>';
    return;
  }

  const btn = document.getElementById('studentRosterPreviewBtn');
  btn.disabled = true;
  btn.textContent = 'Reading...';

  const formData = new FormData();
  formData.append('file', fileInput.files[0]);

  fetch('{{ route('admin.rosters.students.preview') }}', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    body: formData,
  })
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        resultBox.style.display = 'block';
        resultBox.innerHTML = `<span style="color:#dc2626">${escRoster(data.message || 'That file could not be read.')}</span>`;
        return;
      }

      renderRosterPreview(data);

      document.getElementById('studentRosterStep1').style.display = 'none';
      document.getElementById('studentRosterStep2').style.display = '';
      document.getElementById('studentRosterCard').classList.add('ct-modal-card-wide');
    })
    .catch(() => {
      resultBox.style.display = 'block';
      resultBox.innerHTML = '<span style="color:#dc2626">Could not read that file. Check the connection and try again.</span>';
    })
    .finally(() => {
      btn.disabled = false;
      btn.textContent = 'Preview';
    });
}

function renderRosterPreview(data) {
  const summary = [
    `<span><strong>${data.rows.length}</strong> row${data.rows.length === 1 ? '' : 's'} read</span>`,
    `<span><strong>${data.create}</strong> new account${data.create === 1 ? '' : 's'}</span>`,
    `<span><strong>${data.update}</strong> to update</span>`,
    `<span><strong>${data.unchanged}</strong> unchanged</span>`,
  ];
  if (data.skipped) summary.push(`<span style="color:#b91c1c"><strong>${data.skipped}</strong> skipped</span>`);
  document.getElementById('studentRosterSummary').innerHTML = summary.join('');

  const head = `<thead><tr><th>Row</th><th>Name</th><th>Email</th><th>Student No.</th>
    <th>Program</th><th>Year</th><th>Section</th><th>School Year</th><th>What happens</th></tr></thead>`;

  const body = data.rows.map(row => {
    const [cls, label] = ROSTER_ACTIONS[row.action] || ROSTER_ACTIONS.unchanged;
    // Naming the fields is the point of the preview - "Update" alone does not
    // say that a re-upload is about to rewrite somebody's course.
    const changes = row.changes && row.changes.length
      ? `<div class="ct-preview-changes">${escRoster(row.changes.join(', '))}</div>`
      : '';

    return `<tr>
      <td>${escRoster(row.row)}</td>
      <td>${escRoster(row.name) || '&mdash;'}</td>
      <td>${escRoster(row.email)}</td>
      <td>${escRoster(row.student_number) || '&mdash;'}</td>
      <td>${escRoster(row.program) || '&mdash;'}</td>
      <td>${escRoster(row.year) || '&mdash;'}</td>
      <td>${escRoster(row.section) || '&mdash;'}</td>
      <td>${escRoster(row.school_year)}</td>
      <td><span class="ct-tag ${cls}">${label}</span>${changes}</td>
    </tr>`;
  }).join('');

  document.getElementById('studentRosterPreviewTable').innerHTML = head + '<tbody>' + body + '</tbody>';

  const errorBox = document.getElementById('studentRosterErrors');

  if (data.errors && data.errors.length) {
    errorBox.style.display = 'block';
    errorBox.innerHTML = '<div style="font-size:12px;color:#b91c1c;max-height:120px;overflow-y:auto;margin-bottom:12px">'
      + data.errors.map(e => `<div>${escRoster(e)}</div>`).join('') + '</div>';
  } else {
    errorBox.style.display = 'none';
    errorBox.innerHTML = '';
  }
}

function uploadStudentRoster() {
  const fileInput = document.getElementById('studentRosterFile');
  // Step 1 is hidden by the time this runs, and so is its result box - a
  // failure reported there would be reported to nobody.
  const resultBox = document.getElementById('studentRosterSaveResult');

  if (!fileInput.files.length) {
    resultBox.style.display = 'block';
    resultBox.innerHTML = '<span style="color:#dc2626">Choose a file first.</span>';
    return;
  }

  const btn = document.getElementById('studentRosterUploadBtn');
  btn.disabled = true;
  btn.textContent = 'Saving...';

  const formData = new FormData();
  formData.append('file', fileInput.files[0]);

  fetch('{{ route('admin.rosters.students.store') }}', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    body: formData,
  })
    .then(r => r.json())
    .then(data => {
      resultBox.style.display = 'block';

      if (!data.success) {
        resultBox.innerHTML = `<span style="color:#dc2626">${data.message || 'Upload failed.'}</span>`;
        return;
      }

      let html = `<strong>${data.inserted}</strong> read, <strong>${data.skipped}</strong> skipped.`;
      if (data.created) {
        html += `<div style="margin-top:6px;color:#15803d"><strong>${data.created}</strong> account${data.created === 1 ? '' : 's'} prepared for students who have not signed in yet.</div>`;
      }
      if (data.filled) {
        html += `<div style="margin-top:4px;color:#15803d"><strong>${data.filled}</strong> blank field${data.filled === 1 ? '' : 's'} filled in on ${data.matched} existing account${data.matched === 1 ? '' : 's'}.</div>`;
      }
      if (data.already_complete) {
        html += `<div style="margin-top:4px;color:var(--text3)">${data.already_complete} already had everything &mdash; nothing on those was changed.</div>`;
      }
      if (data.errors && data.errors.length) {
        html += '<div style="max-height:160px;overflow-y:auto;color:var(--text3);margin-top:6px">' +
          data.errors.map(e => `<div>${e}</div>`).join('') + '</div>';
      }
      resultBox.innerHTML = html;
      showToast('Student list uploaded.', 'success');
      setTimeout(() => window.location.reload(), 1200);
    })
    .catch(() => {
      resultBox.style.display = 'block';
      resultBox.innerHTML = '<span style="color:#dc2626">Network error while uploading.</span>';
    })
    .finally(() => {
      btn.disabled = false;
      btn.textContent = 'Upload';
    });
}
</script>
@endif

</body>
</html>
