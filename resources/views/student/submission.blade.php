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
  #pageSkeleton { position: absolute; inset: 0; z-index: 50; background: var(--bg, #f2f3f5); transition: opacity 0.6s ease, visibility 0.6s ease; }
  #pageSkeleton.hidden { opacity: 0; visibility: hidden; pointer-events: none; }
  #contentWrapper { opacity: 1; transition: opacity 0.6s ease; }
  #contentWrapper.loading { opacity: 0; }
  .sk-block { position: relative; overflow: hidden; background: rgba(0,0,0,0.06); }
  .sk-shimmer { position: absolute; inset: 0; background: linear-gradient(90deg, transparent, rgba(0,0,0,0.08), transparent); animation: skShimmer 1.5s ease-in-out infinite; }
  @keyframes skShimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
</style>
</head>
<body>
@include('student.partials.sidebar')

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
    <div id="contentWrapper" class="loading" style="position: relative;">

    {{-- Skeleton loader --}}
    <div id="pageSkeleton">
      <div class="submission-container">
        <div class="upload-card">
          <div style="display:flex;gap:14px;align-items:center;margin-bottom:20px">
            <div class="sk-block" style="width:42px;height:42px;border-radius:10px;flex-shrink:0"><div class="sk-shimmer"></div></div>
            <div style="flex:1">
              <div class="sk-block" style="width:180px;height:16px;border-radius:4px;margin-bottom:8px"><div class="sk-shimmer"></div></div>
              <div class="sk-block" style="width:240px;height:11px;border-radius:4px"><div class="sk-shimmer"></div></div>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px">
            @for ($i = 0; $i < 3; $i++)
            <div>
              <div class="sk-block" style="width:90px;height:9px;border-radius:4px;margin-bottom:6px"><div class="sk-shimmer"></div></div>
              <div class="sk-block" style="width:100%;height:38px;border-radius:8px"><div class="sk-shimmer"></div></div>
            </div>
            @endfor
          </div>
          <div class="sk-block" style="width:100%;height:70px;border-radius:8px;margin-bottom:20px"><div class="sk-shimmer"></div></div>
          <div class="sk-block" style="width:130px;height:12px;border-radius:4px;margin-bottom:12px"><div class="sk-shimmer"></div></div>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            @for ($i = 0; $i < 4; $i++)
            <div class="sk-block" style="width:160px;height:44px;border-radius:8px"><div class="sk-shimmer"></div></div>
            @endfor
          </div>
        </div>
      </div>
    </div>

    {{-- Content --}}
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

      <div class="form-section">
        <div class="section-label">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h7"/></svg>
          Document Details
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
            <div class="pm-field">
                <label class="pm-label">Document Title</label>
                <input type="text" class="input" id="doc-title" placeholder="e.g., Chapter 1-3 Final">
            </div>
            <div class="pm-field">
                <label class="pm-label">Chapter</label>
                <select class="input" id="doc-chapter">
                    <option value="" disabled selected>Select chapter</option>
                    <option value="Chapter 1">Chapter 1</option>
                    <option value="Chapter 2">Chapter 2</option>
                    <option value="Chapter 3">Chapter 3</option>
                    <option value="Chapter 4">Chapter 4</option>
                    <option value="Chapter 5">Chapter 5</option>
                </select>
            </div>
            <div class="pm-field">
                <label class="pm-label">Document Type</label>
                <select class="input" id="doc-type">
                    <option value="Proposal Draft">Proposal Draft</option>
                    <option value="Final Manuscript">Final Manuscript</option>
                </select>
            </div>
        </div>

        <div class="pm-field" style="margin-top: 16px;">
            <label class="pm-label">Message to Adviser</label>
            <textarea class="input" id="doc-message" rows="3" style="resize: none;" placeholder="Briefly describe your submission..."></textarea>
        </div>
      </div>

      <div class="form-section">
        <div class="section-label">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Send To
        </div>
        <div class="recipient-group" id="recipientGroup">

            @php
                $roleLabels = [
                    'adviser' => 'Capstone Project Adviser',
                    'consultant' => 'Consultant',
                    'grammarian' => 'Grammarian',
                    'subject_adviser' => 'Capstone Project Subject Adviser',
                ];
                $roleIcons = [
                    'adviser' => 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
                    'consultant' => 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
                    'grammarian' => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15Z',
                    'subject_adviser' => 'M12 2 3 7v6c0 5.25 3.75 9.75 9 11 5.25-1.25 9-5.75 9-11V7l-9-5Z',
                ];
            @endphp

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
                        {{ $roleKey === 'adviser' ? 'checked' : '' }}
                        {{ ! $isAssigned ? 'disabled' : '' }}
                    >
                    <span class="recipient-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="{{ $roleIcons[$roleKey] }}"/></svg>
                    </span>
                    <span class="recipient-text">
                        {{ $roleLabel }}
                        <small>{{ $isAssigned ? $info['name'] : 'Not yet assigned' }}</small>
                    </span>
                    @if ($roleKey === 'adviser')
                        <span class="badge badge-gold recipient-tag">Primary</span>
                    @endif
                </label>
            @endforeach

        </div>
        <p class="recipient-hint">Select at least one recipient for this submission.</p>
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
          Submit to Adviser
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

<script>
var __skeleton = document.getElementById('pageSkeleton');
var __content = document.getElementById('contentWrapper');
if (__skeleton && __content) {
  (function() {
    function hideSkeleton() {
      __skeleton.classList.add('hidden');
      __content.classList.remove('loading');
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', hideSkeleton);
    } else {
      hideSkeleton();
    }
  })();
}

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

document.getElementById('submitBtn').addEventListener('click', function () {
  const checked = document.querySelectorAll('#recipientGroup input[type="checkbox"]:checked');
  if (checked.length === 0) {
    alert('Please select at least one recipient before submitting.');
    return;
  }

  const title = document.getElementById('doc-title').value.trim();
  if (!title) {
    alert('Please enter a document title.');
    return;
  }

  const chapter = document.getElementById('doc-chapter').value;
  if (!chapter) {
    alert('Please select a chapter.');
    return;
  }

  if (!selectedFile) {
    alert('Please attach a PDF file before submitting.');
    return;
  }

  const fd = new FormData();
  fd.append('title', title);
  fd.append('chapter', chapter);
  fd.append('document_type', document.getElementById('doc-type').value);
  fd.append('message', document.getElementById('doc-message').value);
  fd.append('file', selectedFile);
  checked.forEach(cb => fd.append('recipients[]', cb.value));

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
</script>

</body>
</html>