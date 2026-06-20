<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Duplication Checker - CapsTRACK</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/styles.css')
</head>
<body>
@include('student.partials.sidebar')

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="page-title">Duplication Checker</div>
    <div class="topbar-actions">
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'duplication'])

<div class="grid-2" style="margin-bottom:16px">
  <div class="card">
    <div class="card-header"><span class="card-title">Check Document</span></div>
    <div class="form-group">
      <label class="label">Document Type</label>
      <select class="input"><option>Pre-Proposal</option></select>
    </div>

    <form id="duplication-form">
      <div class="upload-zone" id="upload-zone" style="margin-bottom:12px;cursor:pointer">
        <div class="upload-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
        <div style="font-size:12px;color:var(--text2);font-weight:500;margin-bottom:4px" id="upload-label">Upload your document</div>
        <div style="font-size:10px;color:var(--text3)">PDF format only</div>
        <input type="file" id="file-input" name="document" accept="application/pdf" style="display:none" />
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center" id="check-btn">Run Similarity Check</button>
    </form>

    <div style="margin-top:12px;padding:10px;background:var(--bg3);border-radius:8px;font-size:11px;color:var(--text3)">Powered by Qwen 2.5 · This is just to check for potential duplicate of capstone project inside CSPC College of Computer Studies, Please note that the AI is not perfect and may make mistakes</div>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title">Latest Result</span>
      <span class="badge badge-green" id="result-badge">No Check Yet</span>
    </div>
    <div class="sim-score">
      <div class="sim-ring">
        <div class="sim-inner">
          <div class="sim-pct" id="sim-pct">--</div>
          <div class="sim-label" id="sim-label">Similar</div>
        </div>
      </div>
      <div style="font-size:13px;font-weight:500;color:var(--text);margin-bottom:4px" id="doc-title">No document checked</div>
      <div style="font-size:11px;color:var(--text3)" id="doc-meta">Upload a pre-proposal to begin</div>
    </div>
    <div style="border-top:1px solid var(--border);padding-top:14px">
      <div style="font-size:11px;color:var(--text3);margin-bottom:10px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600">Matched Capstone Projects</div>
      <div class="gap-12" id="matches-list">
        <div style="font-size:12px;color:var(--text3);text-align:center;padding:20px 0">No matches yet</div>
      </div>
    </div>
  </div>
</div>

<div class="card" id="instructor-card" style="display:none;margin-bottom:16px">
  <div class="card-header"><span class="card-title">Suggested Instructors</span></div>
  <div id="instructor-list" class="gap-12"></div>
</div>

  </div>
</div>

<!-- Loading overlay -->
<div id="check-loading" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);
     z-index:999;align-items:center;justify-content:center;flex-direction:column;gap:14px">
  <div style="width:48px;height:48px;border:3px solid rgba(255,255,255,0.2);border-top-color:#fff;
       border-radius:50%;animation:spin 0.8s linear infinite"></div>
  <div style="color:#fff;font-size:13px" id="loading-msg">Extracting PDF text...</div>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
.upload-zone.has-file { border-color: var(--primary, #5b5bd6); background: var(--bg3); }
</style>

<script>
const STEPS = [
  'Extracting PDF text...',
  'Analyzing key concepts via AI...',
  'Comparing against archived capstones...',
  'Suggesting instructors...',
];

const fileInput  = document.getElementById('file-input');
const uploadZone = document.getElementById('upload-zone');
const uploadLabel = document.getElementById('upload-label');

uploadZone.addEventListener('click', () => fileInput.click());

fileInput.addEventListener('change', () => {
  if (fileInput.files.length) {
    uploadLabel.textContent = fileInput.files[0].name;
    uploadZone.classList.add('has-file');
  }
});

document.getElementById('duplication-form').addEventListener('submit', async (e) => {
  e.preventDefault();

  if (!fileInput.files.length) {
    alert('Please upload a PDF first.');
    return;
  }

  const overlay = document.getElementById('check-loading');
  const msgEl   = document.getElementById('loading-msg');
  overlay.style.display = 'flex';

  let stepIdx = 0;
  const interval = setInterval(() => {
    stepIdx = (stepIdx + 1) % STEPS.length;
    msgEl.textContent = STEPS[stepIdx];
  }, 5000);

  const form = new FormData();
  form.append('document', fileInput.files[0]);

  try {
    const res = await fetch("{{ route('duplication.check') }}", {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json',
      },
      body: form,
    });

    const data = await res.json();

    clearInterval(interval);
    overlay.style.display = 'none';

    if (!data.success) throw new Error(data.message);

    renderResults(data, fileInput.files[0].name);

  } catch (err) {
    clearInterval(interval);
    overlay.style.display = 'none';
    alert('Error: ' + err.message);
  }
});

function renderResults(data, fileName) {
  const { top_score, verdict, concepts, matches, instructors } = data;

  // Badge + score ring
  const badge = document.getElementById('result-badge');
  badge.textContent = verdict;
  badge.className = 'badge ' + (
    verdict === 'High Similarity' ? 'badge-red' :
    verdict === 'Moderate Similarity' ? 'badge-amber' : 'badge-green'
  );

  document.getElementById('sim-pct').textContent = top_score + '%';
  document.getElementById('sim-label').textContent = concepts.domain || 'Similar';
  document.getElementById('doc-title').textContent = fileName;
  document.getElementById('doc-meta').textContent =
    'Checked ' + new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

  // Matches list
  const matchList = document.getElementById('matches-list');
  if (!matches.length) {
    matchList.innerHTML = '<div style="font-size:12px;color:var(--text3);text-align:center;padding:20px 0">No similar capstones found</div>';
  } else {
    matchList.innerHTML = matches.map(m => `
      <div class="row" style="justify-content:space-between;align-items:flex-start;padding:8px 0;border-bottom:1px solid var(--border)">
        <div style="flex:1;margin-right:8px">
          <div style="font-size:12px;color:var(--text);font-weight:500">${escapeHtml(m.title)}</div>
          <div style="font-size:10px;color:var(--text3);margin-top:2px">${escapeHtml(m.reason)}</div>
          <div style="font-size:10px;color:var(--text3)">${m.academic_year || 'N/A'} · ${escapeHtml(m.program)}</div>
        </div>
        <span style="font-family:'Space Mono',monospace;font-size:12px;white-space:nowrap;
              color:${m.score >= 70 ? 'var(--red, #e5484d)' : m.score >= 40 ? 'var(--amber, #f5a623)' : 'var(--green, #30a46c)'}">${m.score}%</span>
      </div>
    `).join('');
  }

  // Instructors
  const instrCard = document.getElementById('instructor-card');
  const instrList = document.getElementById('instructor-list');

  if (instructors && instructors.length) {
    instrCard.style.display = 'block';
    instrList.innerHTML = instructors.map(i => `
      <div class="row" style="gap:10px;align-items:flex-start;padding:8px 0">
        <div style="width:32px;height:32px;border-radius:50%;background:var(--primary-light,#e8e8fc);
             display:flex;align-items:center;justify-content:center;font-size:13px;
             font-weight:600;color:var(--primary,#5b5bd6);flex-shrink:0">
          ${i.name.charAt(0)}
        </div>
        <div>
          <div style="font-size:12px;font-weight:500;color:var(--text)">${escapeHtml(i.name)}</div>
          <div style="font-size:10px;color:var(--text3)">${escapeHtml(i.expertise || '')}</div>
          <div style="font-size:10px;color:var(--text3);margin-top:2px">${escapeHtml(i.reason)}</div>
        </div>
      </div>
    `).join('');
  } else {
    instrCard.style.display = 'none';
  }
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str || '';
  return div.innerHTML;
}
</script>

</body>
</html>