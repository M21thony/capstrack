<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Document Submission - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/Styles/submission.css')
<x-dashboard.styles />
<style>
  @keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .sk-overlay { position: absolute; inset: 0; background: var(--bg); z-index: 10; }
  .sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }
</style>
</head>
<body>
@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Document Submission</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'submission'])
    <div id="contentWrapper" style="position: relative;">
<div id="skeleton-content" class="sk-overlay">
      <div class="submission-container">
        <div class="upload-card">
          <div style="display:flex;gap:14px;align-items:center;margin-bottom:20px">
            <div class="sk" style="width:42px;height:42px;border-radius:10px;flex-shrink:0"></div>
            <div style="flex:1">
              <div class="sk" style="width:180px;height:16px;border-radius:4px;margin-bottom:8px"></div>
              <div class="sk" style="width:240px;height:11px;border-radius:4px"></div>
            </div>
          </div>

          <!-- Sending To -->
          <div class="sk" style="width:90px;height:11px;border-radius:4px;margin-bottom:14px"></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px">
            @for ($i = 0; $i < 2; $i++)
            <div class="sk" style="width:150px;height:26px;border-radius:20px"></div>
            @endfor
          </div>

          <!-- Attach File -->
          <div class="sk" style="width:90px;height:11px;border-radius:4px;margin-bottom:14px"></div>
          <div class="sk" style="width:100%;height:96px;border-radius:14px;margin-bottom:20px"></div>

          <!-- Submit -->
          <div class="sk" style="width:100%;height:46px;border-radius:8px"></div>
        </div>

        <div class="overview-panel">
          <div class="guide-card">
            <div class="sk" style="width:140px;height:16px;border-radius:4px;margin-bottom:16px"></div>
            @for ($i = 0; $i < 3; $i++)
            <div style="display:flex;gap:12px;margin-bottom:15px">
              <div class="sk" style="width:24px;height:24px;border-radius:50%;flex-shrink:0"></div>
              <div class="sk" style="flex:1;height:13px;border-radius:4px;margin-top:5px"></div>
            </div>
            @endfor
          </div>
          <div class="card activity-card">
            <div class="sk" style="width:130px;height:16px;border-radius:4px;margin-bottom:14px"></div>
            <div class="sk" style="width:70%;height:12px;border-radius:4px;margin-bottom:6px"></div>
            <div class="sk" style="width:45%;height:10px;border-radius:4px"></div>
          </div>
        </div>
      </div>
    </div>

    <div id="real-content" style="opacity:0">
<div class="submission-container">

    <div class="upload-card">
      <div class="upload-card-header">
        <div class="upload-card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <path d="M14 2v6h6"/>
          </svg>
        </div>
        <div>
          <h2>Submit Document</h2>
          <p>Upload your manuscript or report for review.</p>
        </div>
      </div>

      @php
          $roleLabels = [
              'adviser' => 'Adviser',
              'consultant' => 'Consultant',
              'grammarian' => 'Grammarian',
              'subject_adviser' => 'Subject Adviser',
          ];
          $assignedRecipients = collect($roleLabels)->filter(fn ($label, $roleKey) => ! is_null(($recipients[$roleKey]['expert_id'] ?? null)));
      @endphp

      <div class="form-section">
        <div class="section-label">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Sending To
        </div>
        @if ($assignedRecipients->isNotEmpty())
          <div class="recipient-summary" id="recipientSummary">
            @foreach ($assignedRecipients as $roleKey => $roleLabel)
              <span class="recipient-chip" data-role="{{ $roleKey }}">{{ $roleLabel }} — {{ $recipients[$roleKey]['name'] }}</span>
            @endforeach
          </div>
        @else
          <p class="recipient-hint">No adviser, consultant, or grammarian is assigned to your team yet — once one is, your submissions will be sent to them automatically.</p>
        @endif

        @if ($assignedRecipients->has('subject_adviser'))
          <div class="folder-select-wrap" id="folderSelectWrap" style="display:none">
            <label class="pm-label" for="subjectAdviserFolder">Folder for Subject Adviser</label>
            <select class="input" id="subjectAdviserFolder">
              <option value="">Loading folders…</option>
            </select>
            <p class="folder-select-hint">Pick the folder your Subject Adviser told you to submit to.</p>
          </div>
        @endif
      </div>

      <div class="form-section">
        <div class="section-label">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
          Attach File
        </div>
        <div class="drop-zone" id="dropZone">
            <input type="file" id="fileInput" accept=".pdf" style="display:none" />
            <div class="drop-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
              </svg>
            </div>
            <div class="drop-info">
                <p id="dropText">Click or drag PDF file to upload</p>
                <span id="dropSub">PDF up to 25MB</span>
            </div>
        </div>
      </div>

      <button class="btn-primary btn-submit" id="submitBtn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2 11 13M22 2 15 22l-4-9-9-4 20-7Z"/></svg>
          Submit for Review
      </button>
  </div>

    <div class="overview-panel">
      <div class="guide-card">
        <h3 class="guide-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
          Submission Guide
        </h3>
        <div class="step-item">
          <div class="step-num">1</div>
          <div><strong>Format Check:</strong> Ensure document follows the CCS standard template.</div>
        </div>
        <div class="step-item">
          <div class="step-num">2</div>
          <div><strong>Originality:</strong> Run through the duplication checker first.</div>
        </div>
        <div class="step-item">
          <div class="step-num">3</div>
          <div><strong>Adviser Notif:</strong> Your adviser will be notified once submitted.</div>
        </div>
      </div>

      <div class="card activity-card">
        <h3 class="guide-title" style="margin-bottom: 14px;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
          Recent Activity
        </h3>
        <div class="activity-entry">
            <div class="activity-title">Chapter 1 Review</div>
            <div class="activity-time">Oct 20, 2026 · 2:30 PM</div>
        </div>
      </div>
      <div class="legal-credits">
        <div class="credits-title">System Developers</div>
        <div class="credits-names">Jolina · John Kenneth · Mark Anthony · Ian Cyrus · Alexa Nicole</div>
      </div>
    </div>

    </div>
  </div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var sk = document.getElementById('skeleton-content');
  var rc = document.getElementById('real-content');
  if (sk && rc) {
    requestAnimationFrame(function () {
      setTimeout(function () {
        sk.style.transition = 'opacity 0.3s ease';
        sk.style.opacity = '0';
        rc.style.transition = 'opacity 0.35s ease';
        rc.style.opacity = '1';
        setTimeout(function () {
          sk.remove();
          rc.style.transition = '';
        }, 350);
      }, 600);
    });
  }
});

const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
const SUBMIT_URL = "{{ route('student.submission.store') }}";

let selectedFile = null;

const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const dropText = document.getElementById('dropText');
const dropSub = document.getElementById('dropSub');

dropZone.addEventListener('click', () => fileInput.click());
dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', (e) => {
  e.preventDefault();
  dropZone.classList.remove('drag-over');
  if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]);
});
fileInput.addEventListener('change', (e) => {
  if (e.target.files.length) handleFile(e.target.files[0]);
});

function handleFile(file) {
  const ext = file.name.split('.').pop().toLowerCase();
  if (ext !== 'pdf') {
    alert('Only PDF files are accepted.');
    return;
  }
  if (file.size > 25 * 1024 * 1024) {
    alert('File too large. Max 25MB.');
    return;
  }
  selectedFile = file;
  dropZone.classList.add('has-file');
  dropText.textContent = file.name;
  dropSub.textContent = (file.size / (1024 * 1024)).toFixed(2) + ' MB — click to change';
}

// -- Subject Adviser folder picker --
// Only present when Subject Adviser is one of the assigned recipients (see
// the condition in the blade above). Fetches the adviser's named folders and
// shows the picker only if they've actually set any up - otherwise the
// submission just goes to the default per-team folder, same as any other
// recipient.
const folderWrap = document.getElementById('folderSelectWrap');
const folderSelect = document.getElementById('subjectAdviserFolder');
if (folderWrap && folderSelect) {
  fetch("{{ route('student.submission.subject-adviser-folders') }}")
    .then(r => r.json())
    .then(data => {
      const folders = data.folders || [];
      if (!folders.length) return;
      folderSelect.innerHTML = '<option value="" disabled selected>Select a folder</option>' +
        folders.map(f => `<option value="${f.drive_folder_id}">${f.name}</option>`).join('');
      folderWrap.style.display = '';
    })
    .catch(() => {});
}

document.getElementById('submitBtn').addEventListener('click', function () {
  const assignedRecipients = @json($assignedRecipients->keys());
  if (assignedRecipients.length === 0) {
    alert('No adviser, consultant, or grammarian is assigned to your team yet, so there\'s no one to send this to.');
    return;
  }

  if (!selectedFile) {
    alert('Please attach a PDF file before submitting.');
    return;
  }

  if (folderWrap && folderWrap.style.display !== 'none' && !folderSelect.value) {
    alert('Please select which folder to submit to for your Subject Adviser.');
    return;
  }

  const fd = new FormData();
  fd.append('file', selectedFile);
  assignedRecipients.forEach(role => fd.append('recipients[]', role));
  if (folderSelect && folderSelect.value) {
    fd.append('subject_adviser_folder_id', folderSelect.value);
  }

  const btn = this;
  const originalText = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = 'Submitting…';

  fetch(SUBMIT_URL, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
    body: fd,
  })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = originalText;

      if (!data.success) {
        alert('❌ ' + (data.message || 'Submission failed.'));
        return;
      }

      const failedRecipients = (data.recipients || []).filter(r => r.status === 'not_connected' || r.status === 'failed' || r.status === 'unresolved');
      let msg = '✓ Document submitted successfully.';
      if (failedRecipients.length) {
        msg += '\n\nNote: some recipients could not receive a Drive copy:\n' +
          failedRecipients.map(r => `- ${r.role}: ${r.status}`).join('\n');
      }
      alert(msg);
      window.location.reload();
    })
    .catch(() => {
      btn.disabled = false;
      btn.innerHTML = originalText;
      alert('❌ Submission failed. Please try again.');
    });
});

// -- Mobile Sidebar --
function toggleSidebar() {
    document.body.classList.toggle('sidebar-open');
    document.body.style.overflow = document.body.classList.contains('sidebar-open') ? 'hidden' : '';
}
function closeSidebar() {
    document.body.classList.remove('sidebar-open');
    document.body.style.overflow = '';
}
document.querySelectorAll('.nav-item').forEach(function(el) {
    el.addEventListener('click', function() {
        if (window.innerWidth <= 768) closeSidebar();
    });
});
</script>

</body>
</html>