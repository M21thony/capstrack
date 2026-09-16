<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Document Submission - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
@vite(['resources/student-ui/Styles/submission.css', 'resources/js/submission-preview.js'])
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
@include('partials.skeleton-head', ['skReveal' => 'opacity'])
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

          <!-- File Name / Detected Chapter / Version -->
          <div style="display:flex;gap:14px;margin-bottom:20px">
            <div class="sk" style="flex:1;height:40px;border-radius:8px"></div>
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

      @if ($checklistItems->isNotEmpty())
      <div class="form-section">
        <div class="section-label">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          What is this for?
        </div>
        <select class="input" id="checklistItem" aria-label="Checklist requirement this file is for">
          <option value="">Not a checklist requirement (feedback only)</option>
          @foreach ($checklistItems->groupBy('phase') as $phase => $group)
            <optgroup label="{{ $phase }}">
              @foreach ($group as $ci)
                <option value="{{ $ci->id }}" @selected($ci->id === $selectedItemId) data-kind="{{ $ci->kind }}" data-role="{{ $ci->role }}">{{ $ci->title }}{{ $ci->note ? ' — '.$ci->note : '' }}</option>
              @endforeach
            </optgroup>
          @endforeach
        </select>
        <p class="recipient-hint" id="checklistHint">Pick the requirement and your Progress Tracker updates on its own.</p>
      </div>
      @endif

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
          <p class="recipient-hint">Tick who this submission goes to.</p>
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
            <input type="file" id="fileInput" accept=".pdf,.jpg,.jpeg,.png" multiple style="display:none" />
            <div class="drop-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
              </svg>
            </div>
            <div class="drop-info">
                <p id="dropText">Click or drag files to upload</p>
                <span id="dropSub">PDF up to 25MB each - several at once is fine</span>
            </div>
        </div>
        {{-- One row per file, each with the checklist line it goes to. --}}
        <style>
          .file-list{display:grid;gap:6px;margin-top:10px}
          .file-list:empty{display:none}
          .file-row{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.3fr) auto;gap:10px;align-items:center;padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--bg2)}
          .file-row-name{font-size:13px;font-weight:600;color:var(--text);min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
          .file-row-name small{display:block;font-weight:400;color:var(--text3);font-size:11.5px}
          .file-row select{min-width:0;font-size:12.5px}
          .file-row-remove{font:inherit;font-size:12px;font-weight:600;border:1px solid var(--border);background:var(--bg2);color:var(--text2);border-radius:6px;padding:5px 10px;cursor:pointer}
          .file-row-remove:hover{border-color:var(--red);color:var(--red)}
          .file-row-acts{display:flex;gap:6px}
          .file-row-preview{font:inherit;font-size:12px;font-weight:600;border:1px solid var(--border);background:var(--bg2);color:var(--text);border-radius:6px;padding:5px 10px;cursor:pointer}
          .file-row-preview:hover{border-color:var(--accent)}
          @media (max-width:700px){.file-row{grid-template-columns:1fr auto}.file-row select{grid-column:1 / -1}}
          /* The preview window: the file as it will be sent, before sending it. */
          .preview-dialog{border:0;border-radius:12px;padding:0;margin:auto;inset:0;width:min(860px,calc(100vw - 32px));height:calc(100vh - 48px);box-shadow:0 24px 70px rgba(24,25,31,.3);background:var(--bg2,#fff)}
          .preview-dialog::backdrop{background:rgba(24,25,31,.55)}
          .preview-dialog[open]{display:flex;flex-direction:column}
          .preview-head{display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:1px solid var(--border)}
          .preview-head h2{margin:0;flex:1;min-width:0;font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
          .preview-body{flex:1;min-height:0;overflow:auto;background:var(--bg3,#f2f3f5);padding:12px;display:grid;justify-items:center;align-content:start;gap:12px}
          .preview-body canvas,.preview-body img{max-width:100%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.15)}
          .preview-note{color:var(--text3);font-size:13px;margin:40px 0}
        </style>
        <div class="file-list" id="fileList" aria-label="Files to submit"></div>
        <dialog class="preview-dialog" id="previewDialog" aria-labelledby="previewTitle">
          <div class="preview-head">
            <h2 id="previewTitle">Preview</h2>
            <form method="dialog"><button class="file-row-remove" type="submit">Close</button></form>
          </div>
          <div class="preview-body" id="previewBody"></div>
        </dialog>
      </div>

      <div class="form-section">
        <div class="doc-info-grid">
          <div>
            <label class="pm-label">File Name</label>
            <div class="pm-value readonly" id="fileNameLabel">No file selected yet</div>
          </div>
          <div>
            <label class="pm-label">Detected Chapter</label>
            <div class="pm-value readonly" id="chapterLabel">—</div>
          </div>
          <div>
            <label class="pm-label">Version</label>
            <div class="pm-value readonly" id="versionLabel">Version {{ $nextVersion }}</div>
          </div>
        </div>
        <p class="recipient-hint" id="chapterHint">Name your file with "Chapter N" in it (e.g. Chapter1_2026-08-12.pdf) so resubmissions keep the same version count even if the rest of the file name changes.</p>
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
    // partials/skeleton-head already decided this in <head>, before the first
    // paint: the content is on screen and no skeleton was ever shown. Without
    // this, every save replayed the 600ms fade over content already there.
    if (document.documentElement.classList.contains('sk-seen')) {
      sk.remove();

      return;
    }

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
        try {
          sessionStorage.setItem('sk-seen:{{ substr(sha1(session()->getId()), 0, 12) }}:' + window.location.pathname, '1');
        } catch (e) {}
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

const NEXT_VERSION_URL = "{{ route('student.submission.next-version') }}";

const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const dropText = document.getElementById('dropText');
const dropSub = document.getElementById('dropSub');
const fileNameLabel = document.getElementById('fileNameLabel');
const versionLabel = document.getElementById('versionLabel');
const chapterLabel = document.getElementById('chapterLabel');

dropZone.addEventListener('click', () => fileInput.click());
dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', (e) => {
  e.preventDefault();
  dropZone.classList.remove('drag-over');
  if (e.dataTransfer.files.length) addFiles(e.dataTransfer.files);
});
fileInput.addEventListener('change', (e) => {
  if (e.target.files.length) addFiles(e.target.files);
  fileInput.value = '';
});

// -- Checklist requirement --
// A signed paper - a form, or a CLF your adviser or consultant signed by
// hand - goes to the CaPSA to check, so it needs no reviewer and a phone
// photo will do.
const checklistSelect = document.getElementById('checklistItem');
const checklistHint = document.getElementById('checklistHint');

// Several files can go at once, each to its own checklist line.
let files = []; // [{ file, itemId }]
const fileList = document.getElementById('fileList');

function optionFor(id) {
  return checklistSelect && id ? checklistSelect.querySelector('option[value="' + id + '"]') : null;
}
function paperItem(id) {
  const option = optionFor(id);
  return !!option && ['form', 'approval', 'custom'].includes(option.dataset.kind);
}
// Signed papers only: no reviewer needed. With files queued, every one must be.
function isPaper() {
  return files.length ? files.every(f => paperItem(f.itemId)) : paperItem(checklistSelect && checklistSelect.value);
}

// "09 Signed CLF Approving Chapter 1 (CaPA-Adviser).pdf" -> that checklist
// line. Letters and digits only, so "-" for "/" and a leading number match.
const nameKey = s => (s || '').toLowerCase().replace(/\.[^.]+$/, '').replace(/^\s*\d+[\s._-]*/, '').replace(/[^a-z0-9]/g, '');

function matchItem(fileName) {
  if (!checklistSelect) return '';
  const key = nameKey(fileName);
  let best = '';
  let strength = 0;
  checklistSelect.querySelectorAll('option').forEach(option => {
    const title = option.value ? nameKey(option.textContent.split(' — ')[0]) : '';
    if (!title) return;
    if (key === title) {
      best = option.value;
      strength = 2;
    } else if (strength < 1 && Math.min(key.length, title.length) >= 8 && (key.includes(title) || title.includes(key))) {
      best = option.value;
      strength = 1;
    }
  });
  return best;
}

function applyChecklistItem() {
  if (!checklistSelect) return;
  const boxes = document.querySelectorAll('#recipientGroup input[type="checkbox"]:not(:disabled)');

  if (isPaper()) {
    boxes.forEach(cb => { cb.checked = false; });
    checklistHint.textContent = 'Upload a scan or a clear phone photo of the signed paper. It goes to your CaPSA to check — no reviewer needed.';
  } else {
    checklistHint.textContent = 'Pick the requirement and your Progress Tracker updates on its own.';
  }

  if (!files.length) dropSub.textContent = isPaper() ? 'PDF, JPG or PNG up to 25MB each' : 'PDF up to 25MB each - several at once is fine';
}

if (checklistSelect) {
  checklistSelect.addEventListener('change', () => {
    // Files that matched no line take the one picked here.
    files.forEach(f => { if (!f.itemId) f.itemId = checklistSelect.value; });
    renderFiles();
    applyChecklistItem();
  });
  applyChecklistItem();
}

function addFiles(list) {
  for (const file of list) {
    const ext = file.name.split('.').pop().toLowerCase();
    if (!['pdf', 'jpg', 'jpeg', 'png'].includes(ext)) {
      showToast(file.name + ': only PDF, JPG or PNG files are accepted.', 'error');
      continue;
    }
    if (file.size > 25 * 1024 * 1024) {
      showToast(file.name + ' is too large. Max 25MB.', 'error');
      continue;
    }
    files.push({ file: file, itemId: matchItem(file.name) || (checklistSelect ? checklistSelect.value : '') });
  }
  renderFiles();
  applyChecklistItem();
}

function renderFiles() {
  dropZone.classList.toggle('has-file', files.length > 0);
  dropText.textContent = files.length ? files.length + (files.length === 1 ? ' file' : ' files') + ' ready' : 'Click or drag files to upload';
  if (files.length) dropSub.textContent = 'Click or drop to add more';
  fileList.innerHTML = '';

  files.forEach((entry, i) => {
    const row = document.createElement('div');
    row.className = 'file-row';

    const name = document.createElement('div');
    name.className = 'file-row-name';
    name.title = entry.file.name;
    name.textContent = entry.file.name;
    const size = document.createElement('small');
    size.textContent = (entry.file.size / (1024 * 1024)).toFixed(2) + ' MB';
    name.appendChild(size);
    row.appendChild(name);

    if (checklistSelect) {
      const select = checklistSelect.cloneNode(true);
      select.removeAttribute('id');
      select.value = entry.itemId;
      select.setAttribute('aria-label', 'Requirement for ' + entry.file.name);
      select.addEventListener('change', () => { entry.itemId = select.value; applyChecklistItem(); });
      row.appendChild(select);
    } else {
      row.appendChild(document.createElement('span'));
    }

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'file-row-remove';
    remove.textContent = 'Remove';
    remove.addEventListener('click', () => { files.splice(i, 1); renderFiles(); applyChecklistItem(); });

    // Drawn from the file itself - pages on canvases, no iframe or viewer menus.
    const preview = document.createElement('button');
    preview.type = 'button';
    preview.className = 'file-row-preview';
    preview.textContent = 'Preview';
    preview.addEventListener('click', () => window.previewSubmissionFile && window.previewSubmissionFile(entry.file));

    const acts = document.createElement('div');
    acts.className = 'file-row-acts';
    acts.append(preview, remove);
    row.appendChild(acts);

    fileList.appendChild(row);
  });

  if (files.length === 1) {
    fileNameLabel.textContent = files[0].file.name;
    updateVersionLabel(files[0].file.name);
  } else {
    fileNameLabel.textContent = files.length ? files.length + ' files' : 'No file selected yet';
    versionLabel.textContent = files.length ? 'Each file is versioned on its own' : 'Version 1';
    if (chapterLabel) chapterLabel.textContent = '—';
  }
}

// Version only carries forward for a resubmission of the *same* document.
// The server (SubmissionController::detectChapter) looks for a "Chapter N"
// reference in the name and groups by that when present, so a changing
// date/suffix around it doesn't reset the version count; otherwise it
// falls back to matching the exact file name.
function updateVersionLabel(fileName) {
  if (!versionLabel) return;
  const title = fileName.replace(/\.[^/.]+$/, '');
  versionLabel.textContent = 'Checking…';
  if (chapterLabel) chapterLabel.textContent = '—';
  fetch(NEXT_VERSION_URL + '?title=' + encodeURIComponent(title))
    .then(r => r.json())
    .then(data => {
      versionLabel.textContent = 'Version ' + (data.version || 1);
      if (chapterLabel) {
        chapterLabel.textContent = data.chapter || 'Not detected (using exact file name)';
      }
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

// Each file is its own submission, sent one after another, so every file is
// stored, versioned and ticked on the checklist line it was given.
document.getElementById('submitBtn').addEventListener('click', async function () {
  const checked = Array.from(document.querySelectorAll('#recipientGroup input[type="checkbox"]:checked')).map(cb => cb.value);

  if (!files.length) {
    showToast('Please attach a file before submitting.', 'error');
    return;
  }
  if (checked.length === 0 && !isPaper()) {
    showToast('Please select at least one recipient for the documents being reviewed.', 'error');
    return;
  }
  const notPdf = files.find(f => !paperItem(f.itemId) && !f.file.name.toLowerCase().endsWith('.pdf'));
  if (notPdf) {
    showToast(notPdf.file.name + ': a document sent for review must be a PDF. Only signed papers can be photos.', 'error');
    return;
  }
  if (folderWrap && folderWrap.style.display !== 'none' && !folderSelect.value) {
    showToast('Please select which folder to submit to for your Subject Adviser.', 'error');
    return;
  }

  const btn = this;
  const originalText = btn.innerHTML;
  btn.disabled = true;

  const total = files.length;
  let sent = 0;
  const driveProblems = [];
  let failure = null;

  for (const entry of files) {
    btn.innerHTML = 'Submitting ' + (sent + 1) + ' of ' + total + '…';

    const fd = new FormData();
    fd.append('file', entry.file);
    // A signed paper goes to the CaPSA, not to a reviewer.
    if (!paperItem(entry.itemId)) checked.forEach(role => fd.append('recipients[]', role));
    if (entry.itemId) fd.append('deliverable_id', entry.itemId);
    if (!paperItem(entry.itemId) && folderWrap && folderWrap.style.display !== 'none' && folderSelect.value) {
      fd.append('subject_adviser_folder_id', folderSelect.value);
    }

    try {
      const data = await fetch(SUBMIT_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }, body: fd }).then(r => r.json());
      if (!data.success) {
        failure = entry.file.name + ': ' + (data.message || 'Submission failed.');
        break;
      }
      (data.recipients || [])
        .filter(r => ['not_connected', 'failed', 'unresolved'].includes(r.status))
        .forEach(r => driveProblems.push(entry.file.name + ' - ' + r.role + ' (' + r.status + ')'));
      sent++;
    } catch (e) {
      failure = entry.file.name + ': submission failed. Please try again.';
      break;
    }
  }

  btn.disabled = false;
  btn.innerHTML = originalText;

  if (failure) {
    // What went through is off the list, so trying again never sends it twice.
    files = files.slice(sent);
    renderFiles();
    applyChecklistItem();
    showToast((sent ? sent + ' of ' + total + ' sent. Stopped at ' : '') + failure, 'error');
    return;
  }

  let msg = total === 1 ? 'Document submitted successfully.' : 'All ' + total + ' files submitted.';
  if (driveProblems.length) msg += ' Some recipients could not receive a Drive copy: ' + driveProblems.join(', ');
  showToast(msg, driveProblems.length ? 'error' : 'success');
  setTimeout(() => window.location.reload(), 1800);
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

@include('partials.escape-closes-modals')
</body>
</html>