<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Document Submission - CapsTRACK</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/Styles/submission.css')
</head>
<body>
@include('student.partials.sidebar')

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="page-title">Document Submission</div>
    <div class="topbar-actions">
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'submission'])
<div class="submission-container">


    <div class="upload-card">
      <h2 style="margin-bottom: 8px;">Submit Document</h2>
      <p style="color: var(--text3); font-size: 14px; margin-bottom: 24px;">Upload your manuscript or report for review.</p>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
          <div class="pm-field">
              <label class="pm-label">Document Title</label>
              <input type="text" class="input" id="doc-title" placeholder="e.g., Chapter 1-3 Final">
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

<!-- ── RECIPIENT SELECTOR (compact) ── -->
<div class="pm-field" style="margin-top: 16px;">
    <label class="pm-label">Send To</label>
    <div class="recipient-group" id="recipientGroup">

        <label class="recipient-check">
            <input type="checkbox" name="recipients[]" value="adviser" checked>
            <span class="recipient-text">
                Capstone Project Adviser
                <small>Juan Dela Cruz</small>
            </span>
            <span class="badge badge-gold recipient-tag">Primary</span>
        </label>

        <label class="recipient-check">
            <input type="checkbox" name="recipients[]" value="consultant">
            <span class="recipient-text">
                Consultant
                <small>Maria Santos</small>
            </span>
        </label>

        <label class="recipient-check">
            <input type="checkbox" name="recipients[]" value="grammarian">
            <span class="recipient-text">
                Grammarian
                <small>Maria Santos</small>
            </span>
        </label>

        <label class="recipient-check">
            <input type="checkbox" name="recipients[]" value="subject_adviser">
            <span class="recipient-text">
                Capstone Project Subject Adviser
                <small>Prof. Reyes</small>
            </span>
        </label>

    </div>
    <p class="recipient-hint">Select at least one recipient.</p>
</div>
<!-- ── END RECIPIENT SELECTOR ── -->

      <div class="drop-zone" id="dropZone">
          <input type="file" id="fileInput" accept=".pdf" style="display:none" />
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
          </svg>
          <div class="drop-info">
              <p id="dropText">Click or drag PDF file to upload</p>
              <span id="dropSub">PDF up to 25MB</span>
          </div>
      </div>

      <button class="btn-primary" style="width: 100%; padding: 12px; margin-top: 20px; font-weight: 600;" id="submitBtn">
          Submit to Adviser
      </button>
  </div>

    <div class="overview-panel">
      <div class="guide-card">
        <h3 style="font-size: 14px; margin-bottom: 16px;">Submission Guide</h3>
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

      <div class="card" style="margin-top: 16px;">
        <h3 style="font-size: 14px; margin-bottom: 12px;">Recent Activity</h3>
        <div style="font-size: 12px; color: var(--text2);">
            <div style="border-left: 2px solid var(--accent); padding-left: 12px; margin-bottom: 12px;">
                <div style="font-weight: 600;">Chapter 1 Review</div>
                <div style="font-size: 10px; color: var(--text3);">Oct 20, 2026 · 2:30 PM</div>
            </div>
        </div>
      </div>
      <div class="legal-credits">
        <div class="credits-title">System Developers</div>
        <div class="credits-names">Jolina · John Kenneth · Mark Anthony · Ian Cyrus · Alexa Nicole</div>
      </div>
    </div>

  </div>
</div>

<script>
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

  if (!selectedFile) {
    alert('Please attach a PDF file before submitting.');
    return;
  }

  const fd = new FormData();
  fd.append('title', title);
  fd.append('document_type', document.getElementById('doc-type').value);
  fd.append('message', document.getElementById('doc-message').value);
  fd.append('file', selectedFile);
  checked.forEach(cb => fd.append('recipients[]', cb.value));

  const btn = this;
  const originalText = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Submitting…';

  fetch(SUBMIT_URL, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
    body: fd,
  })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.textContent = originalText;

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
      btn.textContent = originalText;
      alert('❌ Submission failed. Please try again.');
    });
});
</script>

</body>
</html>