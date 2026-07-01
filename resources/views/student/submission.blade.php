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
      @include('student.partials.notification-dropdown')
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
              <input type="text" class="input" placeholder="e.g., Chapter 1-3 Final">
          </div>
          <div class="pm-field">
              <label class="pm-label">Document Type</label>
              <select class="input">
                  <option>Proposal Draft</option>
                  <option>Final Manuscript</option>
              </select>
          </div>
      </div>

      <div class="pm-field" style="margin-top: 16px;">
          <label class="pm-label">Message to Adviser</label>
          <textarea class="input" rows="3" style="resize: none;" placeholder="Briefly describe your submission..."></textarea>
      </div>

      <div class="drop-zone" id="dropZone">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
          </svg>
          <div class="drop-info">
              <p>Click or drag PDF file to upload</p>
              <span>PDF up to 25MB</span>
          </div>
      </div>

      <button class="btn-primary" style="width: 100%; padding: 12px; margin-top: 20px; font-weight: 600;">
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

</body>
</html>
