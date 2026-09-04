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

  .std-toolbar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center;justify-content:space-between}
  .std-search{max-width:360px;flex:1;min-width:220px}
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

  <div class="content">
    <div class="std-toolbar">
      <form method="GET" class="std-search">
        <input class="input" type="text" name="search" value="{{ $search }}" placeholder="Search by name, email, or student number...">
      </form>
      <button type="button" class="btn btn-primary" onclick="openStudentModal()">+ Add Student</button>
    </div>

    <div class="card" style="padding:0;overflow:hidden">
      <table class="table">
        <thead>
          <tr>
            <th style="padding-left:20px">Name</th>
            <th>Email</th>
            <th>Student No.</th>
            <th>Program</th>
            <th>Year</th>
            <th>Section</th>
            <th style="padding-right:20px">Action</th>
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
              <td style="padding-left:20px">
                <div class="std-who">
                  <img src="{{ $student->avatarUrl() }}" alt="" loading="lazy" class="std-av"
                       onerror="this.onerror=null;this.src='{{ asset('images/default-avatar.jpg') }}';">
                  <span class="std-name">{{ trim($student->f_name.' '.$student->l_name) }}</span>
                </div>
              </td>
              <td class="std-email" title="{{ $student->email }}">{{ $student->email }}</td>
              <td class="std-num">{{ $student->student_number ?? '—' }}</td>
              @php
                // Program, year and section are three things read down a
                // column. They were one string - the full course name and
                // "4th · B" run together - which reads as neither.
                $program = $student->course ? \App\Models\Student::mapCourseToProgram($student->course) : null;
                $ys = array_map('trim', explode('·', (string) $student->year_section));
                $year = count($ys) === 2 ? $ys[0] : '';
                $section = count($ys) === 2 ? $ys[1] : ($ys[0] ?? '');
              @endphp
              <td title="{{ $student->course }}">
                @if ($program)
                  <span class="std-prog">{{ $program }}</span>
                @else
                  <span class="std-none">{{ $student->course ?: '—' }}</span>
                @endif
              </td>
              <td class="std-num">{{ $year !== '' ? $year : '—' }}</td>
              <td class="std-num">{{ $section !== '' ? $section : '—' }}</td>
              <td style="padding-right:20px">
                {{-- A pencil and a bin, with the words on the button rather
                     than beside it: at this row height the labels were most
                     of the width of the table. --}}
                <div class="std-actions">
                  <button type="button" class="std-ico" title="Edit" aria-label="Edit" onclick='openStudentModal({{ Illuminate\Support\Js::from($studentPayload) }})'>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                  </button>
                  <button type="button" class="std-ico std-ico-danger" title="Remove" aria-label="Remove" onclick="openRemoveModal({{ $student->student_id }}, {{ Illuminate\Support\Js::from(trim($student->f_name.' '.$student->l_name)) }})">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M7 6l1 14h8l1-14"/><path d="M10 10v6"/><path d="M14 10v6"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" style="padding:24px 20px;text-align:center;color:var(--text3);font-size:13px">No student accounts found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div style="margin-top:16px">{{ $students->links() }}</div>
  </div>
</div>

{{-- Toast container --}}
<div id="ct-toast-container"></div>

{{-- ══════════════════════════════════════════
     ADD / EDIT STUDENT — Form Modal
══════════════════════════════════════════ --}}
<div id="studentModal" class="ct-modal-overlay">
  <div class="ct-modal-card" style="max-width:480px">
    <h3 class="ct-modal-title" id="studentModalTitle">Add Student</h3>
    <div class="ct-modal-grid">
      <div class="full">
        <label class="ct-modal-label">Email <span style="color:#ef4444">*</span></label>
        <input type="email" id="studentEmailInput" class="ct-modal-input" placeholder="juan.delacruz@my.cspc.edu.ph">
      </div>
      <div>
        <label class="ct-modal-label">First Name <span style="color:#ef4444">*</span></label>
        <input type="text" id="studentFNameInput" class="ct-modal-input" placeholder="Juan">
      </div>
      <div>
        <label class="ct-modal-label">Last Name <span style="color:#ef4444">*</span></label>
        <input type="text" id="studentLNameInput" class="ct-modal-input" placeholder="Dela Cruz">
      </div>
      <div>
        <label class="ct-modal-label">Middle Name</label>
        <input type="text" id="studentMNameInput" class="ct-modal-input" placeholder="Optional">
      </div>
      <div>
        <label class="ct-modal-label">Suffix</label>
        <input type="text" id="studentSuffixInput" class="ct-modal-input" placeholder="Jr., III...">
      </div>
      <div>
        <label class="ct-modal-label">Student Number</label>
        <input type="text" id="studentNumberInput" class="ct-modal-input" placeholder="2021-00123">
      </div>
      <div>
        <label class="ct-modal-label">Course</label>
        <input type="text" id="studentCourseInput" class="ct-modal-input" placeholder="BSIT">
      </div>
      <div class="full">
        <label class="ct-modal-label">Year &amp; Section</label>
        <input type="text" id="studentSectionInput" class="ct-modal-input" placeholder="4th · A">
      </div>
    </div>
    <p id="studentModalError" style="display:none;color:#dc2626;font-size:12px;margin:-6px 0 14px;text-align:left"></p>
    <div class="ct-modal-actions">
      <button type="button" class="ct-btn ct-btn-secondary" onclick="closeStudentModal()">Cancel</button>
      <button type="button" id="studentSaveBtn" class="ct-btn ct-btn-primary" onclick="saveStudent()">Save</button>
    </div>
  </div>
</div>

{{-- ══════════════════════════════════════════
     REMOVE STUDENT — Confirmation Modal
══════════════════════════════════════════ --}}
<div id="removeModal" class="ct-modal-overlay">
  <div class="ct-modal-card">
    <div class="ct-modal-icon danger">
      <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>
      </svg>
    </div>
    <h3 class="ct-modal-title">Remove Student</h3>
    <p class="ct-modal-desc">
      You are about to permanently remove <strong id="removeStudentName" style="color:#0f172a"></strong>'s account.
      This action <span style="color:#ef4444;font-weight:600">cannot be undone</span>.
    </p>
    <div class="ct-modal-actions">
      <button type="button" class="ct-btn ct-btn-secondary" onclick="closeRemoveModal()">Cancel</button>
      <button type="button" id="removeConfirmBtn" class="ct-btn ct-btn-danger" onclick="confirmRemoveStudent()">Remove</button>
    </div>
  </div>
</div>

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
      <p class="ct-toast-msg">${message}</p>
    </div>
    <button class="ct-toast-close" onclick="this.closest('.ct-toast').remove()">&times;</button>
  `;
  document.getElementById('ct-toast-container').appendChild(toast);
  setTimeout(() => {
    toast.classList.add('hiding');
    setTimeout(() => toast.remove(), 200);
  }, 4000);
}

// ── ADD / EDIT STUDENT MODAL ───────────────────────────
let editingStudentId = null;
const studentModal = document.getElementById('studentModal');

function openStudentModal(student = null) {
  editingStudentId = student ? student.id : null;
  document.getElementById('studentModalTitle').textContent = student ? 'Edit Student' : 'Add Student';
  document.getElementById('studentEmailInput').value = student?.email || '';
  document.getElementById('studentFNameInput').value = student?.f_name || '';
  document.getElementById('studentLNameInput').value = student?.l_name || '';
  document.getElementById('studentMNameInput').value = student?.m_name || '';
  document.getElementById('studentSuffixInput').value = student?.suffix || '';
  document.getElementById('studentNumberInput').value = student?.student_number || '';
  document.getElementById('studentCourseInput').value = student?.course || '';
  document.getElementById('studentSectionInput').value = student?.year_section || '';
  document.getElementById('studentModalError').style.display = 'none';
  studentModal.style.display = 'flex';
}

function closeStudentModal() {
  studentModal.style.display = 'none';
}

function saveStudent() {
  const errorEl = document.getElementById('studentModalError');
  const payload = {
    email: document.getElementById('studentEmailInput').value.trim(),
    f_name: document.getElementById('studentFNameInput').value.trim(),
    l_name: document.getElementById('studentLNameInput').value.trim(),
    m_name: document.getElementById('studentMNameInput').value.trim() || null,
    suffix: document.getElementById('studentSuffixInput').value.trim() || null,
    student_number: document.getElementById('studentNumberInput').value.trim() || null,
    course: document.getElementById('studentCourseInput').value.trim() || null,
    year_section: document.getElementById('studentSectionInput').value.trim() || null,
  };

  if (!payload.email || !payload.f_name || !payload.l_name) {
    errorEl.textContent = 'Email, first name, and last name are required.';
    errorEl.style.display = 'block';
    return;
  }

  const saveBtn = document.getElementById('studentSaveBtn');
  saveBtn.disabled = true;
  saveBtn.textContent = 'Saving...';

  const url = editingStudentId ? `/admin/students/${editingStudentId}` : '/admin/students';
  const method = editingStudentId ? 'PUT' : 'POST';

  fetch(url, {
    method,
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
    body: JSON.stringify(payload),
  })
    .then(res => res.json().then(data => ({ status: res.status, data })))
    .then(({ status, data }) => {
      if (status === 200 && data.success) {
        closeStudentModal();
        showToast(data.message, 'success');
        setTimeout(() => window.location.reload(), 700);
      } else {
        errorEl.textContent = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Could not save this student.');
        errorEl.style.display = 'block';
      }
    })
    .catch(() => {
      errorEl.textContent = 'Network error while saving.';
      errorEl.style.display = 'block';
    })
    .finally(() => {
      saveBtn.disabled = false;
      saveBtn.textContent = 'Save';
    });
}

// ── REMOVE STUDENT MODAL ───────────────────────────────
let removingStudentId = null;
const removeModal = document.getElementById('removeModal');

function openRemoveModal(id, name) {
  removingStudentId = id;
  document.getElementById('removeStudentName').textContent = name;
  removeModal.style.display = 'flex';
}

function closeRemoveModal() {
  removeModal.style.display = 'none';
  removingStudentId = null;
}

function confirmRemoveStudent() {
  const btn = document.getElementById('removeConfirmBtn');
  btn.disabled = true;
  btn.textContent = 'Removing...';

  fetch(`/admin/students/${removingStudentId}`, {
    method: 'DELETE',
    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
  })
    .then(r => r.json())
    .then(data => {
      closeRemoveModal();
      if (data.success) {
        document.getElementById(`student-row-${removingStudentId}`)?.remove();
        showToast(data.message, 'success');
      } else {
        showToast(data.message || 'Could not remove this student.', 'error');
      }
    })
    .catch(() => {
      closeRemoveModal();
      showToast('Network error while removing.', 'error');
    })
    .finally(() => {
      btn.disabled = false;
      btn.textContent = 'Remove';
    });
}
</script>
</body>
</html>
