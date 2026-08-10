<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Duplication Checker - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/styles.css')
<style>
@keyframes spin { to { transform: rotate(360deg); } }

.dup-layout {
  display: grid;
  grid-template-columns: 320px 1fr;
  grid-template-rows: auto 1fr;
  gap: 14px;
  height: calc(100vh - 120px);
}

.dup-left {
  display: flex;
  flex-direction: column;
  gap: 14px;
  grid-row: 1 / 3;
}

.dup-right-top {
  grid-column: 2;
  grid-row: 1;
}

.dup-right-bottom {
  grid-column: 2;
  grid-row: 2;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.matches-scroll {
  overflow-y: auto;
  flex: 1;
  padding-right: 4px;
}

.matches-scroll::-webkit-scrollbar { width: 4px; }
.matches-scroll::-webkit-scrollbar-track { background: transparent; }
.matches-scroll::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

.upload-zone.has-file { border-color: var(--primary, #5b5bd6); background: var(--bg3); }

.score-ring-sm {
  width: 72px; height: 72px;
  border-radius: 50%;
  border: 3px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  transition: border-color 0.3s;
}

.instructor-item {
  display: flex; gap: 10px; align-items: flex-start;
  padding: 8px 0;
  border-bottom: 1px solid var(--border);
}
.instructor-item:last-child { border-bottom: none; }

.avatar {
  width: 28px; height: 28px; border-radius: 50%;
  background: var(--primary-light, #e8e8fc);
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 600;
  color: var(--primary, #5b5bd6); flex-shrink: 0;
}

.match-item {
  display: flex; justify-content: space-between; align-items: flex-start;
  padding: 8px 0; border-bottom: 1px solid var(--border);
}
.match-item:last-child { border-bottom: none; }

  @keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .sk-overlay { position: absolute; inset: 0; background: var(--bg); z-index: 10; }
  .sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }
</style>
<x-dashboard.styles />
</head>
<body>
@include('student.partials.sidebar')

<div class="main">
  <div class="topbar">
    <div class="page-title">Duplication Checker</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>

  <div class="content" style="height:calc(100vh - 56px);overflow:hidden;padding-bottom:0;position: relative;">
    <div id="skeleton-content" class="sk-overlay">
      <div class="dup-layout">
        <div class="dup-left">
          <div class="card" style="flex-shrink:0">
            <div class="sk" style="width:120px;height:12px;border-radius:4px;margin-bottom:12px"></div>
            <div class="sk" style="width:100%;height:90px;border-radius:8px;margin-bottom:10px"></div>
            <div class="sk" style="width:100%;height:34px;border-radius:8px"></div>
          </div>
          <div class="card" style="flex:1">
            <div class="sk" style="width:150px;height:12px;border-radius:4px;margin-bottom:12px"></div>
            @for ($i = 0; $i < 3; $i++)
            <div style="display:flex;gap:10px;padding:8px 0">
              <div class="sk" style="width:28px;height:28px;border-radius:50%;flex-shrink:0"></div>
              <div style="flex:1">
                <div class="sk" style="width:70%;height:11px;border-radius:4px;margin-bottom:6px"></div>
                <div class="sk" style="width:50%;height:9px;border-radius:4px"></div>
              </div>
            </div>
            @endfor
          </div>
        </div>
        <div class="card dup-right-top" style="padding:14px">
          <div style="display:flex;align-items:center;gap:16px">
            <div class="sk" style="width:72px;height:72px;border-radius:50%;flex-shrink:0"></div>
            <div style="flex:1">
              <div class="sk" style="width:60%;height:14px;border-radius:4px;margin-bottom:8px"></div>
              <div class="sk" style="width:40%;height:10px;border-radius:4px"></div>
            </div>
          </div>
        </div>
        <div class="card dup-right-bottom">
          <div class="sk" style="width:180px;height:12px;border-radius:4px;margin-bottom:14px"></div>
          @for ($i = 0; $i < 5; $i++)
          <div style="padding:8px 0">
            <div class="sk" style="width:90%;height:11px;border-radius:4px;margin-bottom:6px"></div>
            <div class="sk" style="width:60%;height:9px;border-radius:4px"></div>
          </div>
          @endfor
        </div>
      </div>
    </div>

    <div id="real-content" style="opacity:0">
    <div class="dup-layout">

      <!-- LEFT COLUMN -->
      <div class="dup-left">

        <!-- Upload Card -->
        <div class="card" style="flex-shrink:0">
          <div class="card-header"><span class="card-title">Check Document</span></div>
          <form id="duplication-form">
            <div class="upload-zone" id="upload-zone" style="margin-bottom:10px;cursor:pointer;padding:14px">
              <div class="upload-icon" style="margin-bottom:6px">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              </div>
              <div style="font-size:12px;color:var(--text2);font-weight:500;margin-bottom:2px" id="upload-label">Upload your document</div>
              <div style="font-size:10px;color:var(--text3)">PDF format only</div>
              <input type="file" id="file-input" name="document" accept="application/pdf" style="display:none" />
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center" id="check-btn">
              Run Similarity Check
            </button>
          </form>
          <div style="margin-top:10px;padding:8px;background:var(--bg3);border-radius:8px;font-size:10px;color:var(--text3);line-height:1.5">
            Powered by Qwen 2.5 + nomic-embed-text · Local AI · Results may vary
          </div>
        </div>

        <!-- Suggested Instructors Card -->
        <div class="card" id="instructor-card" style="flex:1;overflow:hidden;display:flex;flex-direction:column">
          <div class="card-header" style="flex-shrink:0"><span class="card-title">Suggested Instructors</span></div>
          <div id="instructor-list" style="overflow-y:auto;flex:1">
            <div style="font-size:12px;color:var(--text3);text-align:center;padding:20px 0">
              Run a check to see suggestions
            </div>
          </div>
        </div>

      </div>

      <!-- RIGHT TOP — Score Summary -->
      <div class="card dup-right-top" style="padding:14px">
        <div style="display:flex;align-items:center;gap:16px">
          <div class="score-ring-sm" id="score-ring">
            <div style="text-align:center">
              <div style="font-family:'Space Mono',monospace;font-size:16px;font-weight:700;color:var(--text)" id="sim-pct">--</div>
              <div style="font-size:9px;color:var(--text3)" id="sim-label">Similar</div>
            </div>
          </div>
          <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap">
              <span style="font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:240px" id="doc-title">No document checked</span>
              <span class="badge badge-green" id="result-badge">No Check Yet</span>
            </div>
            <div style="font-size:11px;color:var(--text3)" id="doc-meta">Upload a pre-proposal PDF to begin</div>
            <div style="font-size:11px;color:var(--text3);margin-top:4px;display:none" id="doc-concepts"></div>
          </div>
        </div>
      </div>

      <!-- RIGHT BOTTOM — Matches List -->
      <div class="card dup-right-bottom">
        <div class="card-header" style="flex-shrink:0">
          <span class="card-title">Matched Capstone Projects</span>
          <span style="font-size:11px;color:var(--text3)" id="match-count"></span>
        </div>
        <div class="matches-scroll" id="matches-list">
          <div style="font-size:12px;color:var(--text3);text-align:center;padding:40px 0">
            No matches yet
          </div>
        </div>
      </div>

    </div>
    </div>
  </div>
</div>

<!-- Loading Overlay -->
<div id="check-loading" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);
     z-index:999;align-items:center;justify-content:center;flex-direction:column;gap:14px">
  <div style="width:44px;height:44px;border:3px solid rgba(255,255,255,0.15);border-top-color:#fff;
       border-radius:50%;animation:spin 0.8s linear infinite"></div>
  <div style="color:#fff;font-size:13px;font-weight:500" id="loading-msg">Extracting PDF text...</div>
  <div style="color:rgba(255,255,255,0.5);font-size:11px">This may take 1-2 minutes</div>
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

const STEPS = [
  'Extracting PDF text...',
  'Analyzing key concepts via AI...',
  'Expanding search terms semantically...',
  'Comparing against archived capstones...',
  'Generating similarity scores...',
  'Suggesting instructors...',
];

const fileInput   = document.getElementById('file-input');
const uploadZone  = document.getElementById('upload-zone');
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
  }, 8000);

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

    const contentType = res.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      const text = await res.text();
      console.error('Non-JSON response:', text);
      throw new Error('Server returned an error page. Check the browser console for details.');
    }

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

  // Badge
  const badge = document.getElementById('result-badge');
  badge.textContent = verdict;
  badge.className = 'badge ' + (
    verdict === 'High Similarity'     ? 'badge-red'   :
    verdict === 'Moderate Similarity' ? 'badge-amber' : 'badge-green'
  );

  // Score ring color — thresholds tuned to raw cosine × 100
  const ring = document.getElementById('score-ring');
  ring.style.borderColor = top_score >= 65 ? 'var(--red,#e5484d)'
                         : top_score >= 55 ? 'var(--amber,#f5a623)'
                         : 'var(--green,#30a46c)';

  document.getElementById('sim-pct').textContent   = top_score + '%';
  document.getElementById('sim-label').textContent = concepts.domain || 'Similar';
  document.getElementById('doc-title').textContent = fileName;

  const checkedDate = new Date().toLocaleDateString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric'
  });

  document.getElementById('doc-meta').textContent =
    'Checked ' + checkedDate +
    (top_score < 55 ? ' · No strong duplicates found' : '');

  // Keywords
  const conceptEl = document.getElementById('doc-concepts');
  if (concepts.keywords?.length) {
    conceptEl.style.display = 'block';
    conceptEl.textContent   = '🏷 ' + concepts.keywords.slice(0, 5).join(', ');
  }

  // Match count
  document.getElementById('match-count').textContent =
    matches?.length ? matches.length + ' match' + (matches.length > 1 ? 'es' : '') + ' found' : '';

  // Matches list
  const matchList = document.getElementById('matches-list');

  if (!matches || matches.length === 0) {
    matchList.innerHTML = `
      <div style="text-align:center;padding:40px 0">
        <div style="font-size:28px;margin-bottom:8px">✓</div>
        <div style="font-size:13px;font-weight:600;color:var(--green,#30a46c);margin-bottom:4px">No Similar Capstones Found</div>
        <div style="font-size:11px;color:var(--text3);max-width:280px;margin:0 auto">
          This pre-proposal appears to be original based on our archived database.
        </div>
      </div>`;
  } else if (top_score < 55) {
    matchList.innerHTML = `
      <div style="padding:10px 12px;background:var(--bg3);border-radius:8px;margin-bottom:12px;display:flex;align-items:center;gap:10px">
        <span style="font-size:18px">✓</span>
        <div>
          <div style="font-size:12px;font-weight:600;color:var(--green,#30a46c)">No Strong Duplicates Found</div>
          <div style="font-size:10px;color:var(--text3)">Closest matches scored below 55% — proposal appears original</div>
        </div>
      </div>
      <div style="font-size:10px;color:var(--text3);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600">
        Weak Matches (for reference only)
      </div>
      ${matches.map(m => renderMatchItem(m, true)).join('')}`;
  } else {
    matchList.innerHTML = matches.map(m => renderMatchItem(m, false)).join('');
  }

  // Instructors
  const instrList = document.getElementById('instructor-list');
  if (instructors && instructors.length) {
    instrList.innerHTML = instructors.map(i => `
      <div class="instructor-item">
        <div class="avatar">${escapeHtml(i.name.charAt(0))}</div>
        <div style="min-width:0">
          <div style="font-size:12px;font-weight:500;color:var(--text)">${escapeHtml(i.name)}</div>
          <div style="font-size:10px;color:var(--primary,#5b5bd6);margin-top:1px">${escapeHtml(i.expertise || '')}</div>
          <div style="font-size:10px;color:var(--text3);margin-top:2px">${escapeHtml(i.reason)}</div>
        </div>
      </div>`).join('');
  } else {
    instrList.innerHTML = `
      <div style="font-size:11px;color:var(--text3);text-align:center;padding:16px 0">
        No instructor suggestions available
      </div>`;
  }
}

function renderMatchItem(m, dimmed) {
  // Color thresholds tuned to raw cosine × 100 scores
  const color = m.score >= 65 ? 'var(--red,#e5484d)'
              : m.score >= 55 ? 'var(--amber,#f5a623)'
              : 'var(--green,#30a46c)';
  // Vector similarity alone ranks by phrasing overlap, not real relevance —
  // items the AI itself judged as not a genuine conceptual match are still
  // shown (the score wasn't wrong to flag them for review) but visually
  // de-emphasized so the one real match doesn't read as "just another 84%".
  const isRelevant = m.is_relevant !== false;
  return `
    <div class="match-item" style="${dimmed || !isRelevant ? 'opacity:0.55' : ''}">
      <div style="flex:1;min-width:0;margin-right:10px">
        <div style="font-size:12px;font-weight:500;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
          ${escapeHtml(m.title)}
        </div>
        ${m.reason ? `<div style="font-size:10px;color:var(--text3);margin-top:2px;line-height:1.4">${escapeHtml(m.reason)}</div>` : ''}
        <div style="font-size:10px;color:var(--text3);margin-top:2px">${m.academic_year || 'N/A'} · ${escapeHtml(m.program)}</div>
      </div>
      <span style="font-family:'Space Mono',monospace;font-size:12px;font-weight:700;color:${color};white-space:nowrap;flex-shrink:0">${m.score}%</span>
    </div>`;
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str || '';
  return div.innerHTML;
}
</script>

</body>
</html>