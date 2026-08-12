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
  #submission-toast { position: fixed; bottom: 30px; right: 30px; background: var(--navy-dark); color: #fff; padding: 14px 22px; border-radius: 12px; font-size: 13px; font-weight: 500; z-index: 2000; box-shadow: 0 8px 30px rgba(0,0,0,0.3); opacity: 0; transform: translateY(12px); pointer-events: none; transition: opacity 0.25s ease, transform 0.25s ease; border-left: 4px solid transparent; display: flex; align-items: center; gap: 10px; max-width: 380px; white-space: pre-line; }
  #submission-toast.show { opacity: 1; transform: translateY(0); }
  #submission-toast.success { border-left-color: var(--green); }
  #submission-toast.error { border-left-color: var(--red); }
</style>
</head>
<body>
@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div id="submission-toast"></div>

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

          <!-- Send To -->
          <div class="sk" style="width:70px;height:11px;border-radius:4px;margin-bottom:14px"></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:24px">
            @for ($i = 0; $i < 4; $i++)
            <div class="sk" style="width:100%;height:52px;border-radius:11px"></div>
            @endfor
          </div>

          <!-- Attach File -->
          <div class="sk" style="width:90px;height:11px;border-radius:4px;margin-bottom:14px"></div>
          <div class="sk" style="width:100%;height:96px;border-radius:14px;margin-bottom:20px"></div>

          <!-- File Name / Version -->
          <div style="display:flex;gap:14px;margin-bottom:20px">
            <div class="sk" style="flex:1;height:40px;border-radius:8px"></div>
            <div class="sk" style="flex:1;height:40px;border-radius:8px"></div>
          </div>

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
          $roleIcons = [
              'adviser' => 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
              'consultant' => 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
              'grammarian' => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15Z',
              'subject_adviser' => 'M12 2 3 7v6c0 5.25 3.75 9.75 9 11 5.25-1.25 9-5.75 9-11V7l-9-5Z',
          ];
          $assignedRecipients = collect($roleLabels)->filter(fn ($label, $roleKey) => ! is_null(($recipients[$roleKey]['expert_id'] ?? null)));
      @endphp

      <div class="form-section">
        <div class="section-label">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Send To
        </div>
        <div class="recipient-group" id="recipientGroup">
          @foreach ($roleLabels as $roleKey => $roleLabel)
            @php
                $info = $recipients[$roleKey] ?? ['expert_id' => null, 'name' => null];
                $isAssigned = ! is_null($info['expert_id']);
            @endphp
            <label class="recipient-check {{ ! $isAssigned ? 'recipient-check--disabled' : '' }}">
                <input
                    type="checkbox"
                    name="recipients[]"
                    value="{{ $roleKey }}"
                    data-role="{{ $roleKey }}"
                    {{ $isAssigned ? 'checked' : '' }}
                    {{ ! $isAssigned ? 'disabled' : '' }}
                >
                <span class="recipient-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="{{ $roleIcons[$roleKey] }}"/></svg>
                </span>
                <span class="recipient-text">
                    {{ $roleLabel }}
                    <small>{{ $isAssigned ? $info['name'] : 'Not yet assigned' }}</small>
                </span>
            </label>
          @endforeach
        </div>
        @if ($assignedRecipients->isEmpty())
          <p class="recipient-hint">No adviser, consultant, grammarian, or subject adviser is assigned to your team yet — once one is, you'll be able to select them here.</p>
        @else
          <p class="recipient-hint">Uncheck anyone you don't want this particular submission sent to.</p>
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

      <div class="form-section">
        <div class="doc-info-grid">
          <div>
            <label class="pm-label">File Name</label>
            <div class="pm-value readonly" id="fileNameLabel">No file selected yet</div>
          </div>
          <div>
            <label class="pm-label">Version</label>
            <div class="pm-value readonly" id="versionLabel">Version {{ $nextVersion }}</div>
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

function showToast(message, type) {
  type = type || 'success';
  const toast = document.getElementById('submission-toast');
  if (!toast) return;
  toast.textContent = (type === 'error' ? '✕ ' : '✓ ') + message;
  toast.classList.remove('success', 'error');
  toast.classList.add('show', type);
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => toast.classList.remove('show'), 4000);
}

let selectedFile = null;

const NEXT_VERSION_URL = "{{ route('student.submission.next-version') }}";

const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const dropText = document.getElementById('dropText');
const dropSub = document.getElementById('dropSub');
const fileNameLabel = document.getElementById('fileNameLabel');
const versionLabel = document.getElementById('versionLabel');

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
    showToast('Only PDF files are accepted.', 'error');
    return;
  }
  if (file.size > 25 * 1024 * 1024) {
    showToast('File too large. Max 25MB.', 'error');
    return;
  }
  selectedFile = file;
  dropZone.classList.add('has-file');
  dropText.textContent = file.name;
  dropSub.textContent = (file.size / (1024 * 1024)).toFixed(2) + ' MB — click to change';
  fileNameLabel.textContent = file.name;
  updateVersionLabel(file.name);
}

// Version only carries forward for a resubmission of the same-named
// document (see SubmissionController::nextVersionForTitle) - a
// differently-named file always starts at Version 1, it doesn't inherit
// whatever version an unrelated prior submission reached.
function updateVersionLabel(fileName) {
  if (!versionLabel) return;
  const title = fileName.replace(/\.[^/.]+$/, '');
  versionLabel.textContent = 'Checking…';
  fetch(NEXT_VERSION_URL + '?title=' + encodeURIComponent(title))
    .then(r => r.json())
    .then(data => {
      versionLabel.textContent = 'Version ' + (data.version || 1);
    })
    .catch(() => {
      versionLabel.textContent = 'Version 1';
    });
}

// -- Subject Adviser folder picker --
// Only present when Subject Adviser is one of the assigned recipients (see
// the condition in the blade above). Fetches the adviser's named folders
// once and shows the picker only while the Subject Adviser checkbox is
// actually checked and they've set folders up - otherwise the submission
// just goes to the default per-team folder, same as any other recipient.
const folderWrap = document.getElementById('folderSelectWrap');
const folderSelect = document.getElementById('subjectAdviserFolder');
const subjectAdviserCheckbox = document.querySelector('#recipientGroup input[data-role="subject_adviser"]');
let subjectAdviserFolders = [];

function refreshFolderPickerVisibility() {
  if (!folderWrap || !subjectAdviserCheckbox) return;
  folderWrap.style.display = (subjectAdviserCheckbox.checked && subjectAdviserFolders.length) ? '' : 'none';
}

if (folderWrap && folderSelect) {
  fetch("{{ route('student.submission.subject-adviser-folders') }}")
    .then(r => r.json())
    .then(data => {
      subjectAdviserFolders = data.folders || [];
      if (!subjectAdviserFolders.length) return;
      folderSelect.innerHTML = '<option value="" disabled selected>Select a folder</option>' +
        subjectAdviserFolders.map(f => `<option value="${f.drive_folder_id}">${f.name}</option>`).join('');
      refreshFolderPickerVisibility();
    })
    .catch(() => {});

  if (subjectAdviserCheckbox) {
    subjectAdviserCheckbox.addEventListener('change', refreshFolderPickerVisibility);
  }
}

document.getElementById('submitBtn').addEventListener('click', function () {
  const checked = Array.from(document.querySelectorAll('#recipientGroup input[type="checkbox"]:checked')).map(cb => cb.value);
  if (checked.length === 0) {
    showToast('Please select at least one recipient before submitting.', 'error');
    return;
  }

  if (!selectedFile) {
    showToast('Please attach a PDF file before submitting.', 'error');
    return;
  }

  if (folderWrap && folderWrap.style.display !== 'none' && !folderSelect.value) {
    showToast('Please select which folder to submit to for your Subject Adviser.', 'error');
    return;
  }

  const fd = new FormData();
  fd.append('file', selectedFile);
  checked.forEach(role => fd.append('recipients[]', role));
  if (folderWrap && folderWrap.style.display !== 'none' && folderSelect.value) {
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
        showToast(data.message || 'Submission failed.', 'error');
        return;
      }

      const failedRecipients = (data.recipients || []).filter(r => r.status === 'not_connected' || r.status === 'failed' || r.status === 'unresolved');
      let msg = 'Document submitted successfully.';
      if (failedRecipients.length) {
        msg += ' Some recipients could not receive a Drive copy: ' +
          failedRecipients.map(r => `${r.role} (${r.status})`).join(', ');
      }
      showToast(msg, failedRecipients.length ? 'error' : 'success');
      setTimeout(() => window.location.reload(), 1800);
    })
    .catch(() => {
      btn.disabled = false;
      btn.innerHTML = originalText;
      showToast('Submission failed. Please try again.', 'error');
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