<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Resources – CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
</head>
<body>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<!-- DELETE CONFIRM MODAL -->
<div class="modal-overlay" id="modal-overlay">
  <div class="modal-box">
    <div class="modal-title">Remove Resource?</div>
    <div class="modal-sub" id="modal-sub">This will permanently delete the file and it will no longer be visible to users.</div>
    <div class="modal-actions">
      <button class="btn btn-ghost" style="color:var(--text2);border-color:var(--border)" onclick="closeModal()">Cancel</button>
      <button class="btn btn-danger" id="modal-confirm-btn">Remove</button>
    </div>
  </div>
</div>

@include('admin._sidebar')
<div class="main">
  <div class="topbar">
    <div class="page-title">Templates &amp; Lessons</div>
    <div class="topbar-actions">
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </div>

  <div class="content">
    <div style="display:grid;grid-template-columns:340px 1fr;gap:16px">

      <!-- LEFT: UPLOAD FORM -->
      <div style="display:flex;flex-direction:column;gap:14px">
        <div class="card" style="padding:16px">
          <div class="card-header">
            <span class="card-title">Upload Resource</span>
            <button onclick="resetForm()" style="background:none;border:none;font-size:11px;color:var(--text3);cursor:pointer">Clear</button>
          </div>

          <!-- Dropzone -->
          <div class="form-group">
            <label class="label">File</label>
            <div class="dropzone" id="dropzone" ondragover="onDragOver(event)" ondragleave="onDragLeave(event)" ondrop="onDrop(event)">
              <input type="file" id="file-input" accept=".pdf,.doc,.docx,.ppt,.pptx" onchange="onFileSelect(event)"/>
              <div class="dropzone-icon">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              </div>
              <div class="dropzone-label">Drop file here or click to browse</div>
              <div class="dropzone-sub">PDF, DOCX, PPTX — max 25 MB</div>
            </div>
            <div class="upload-preview" id="upload-preview">
              <div id="preview-ftype" class="ftype ftype-pdf" style="width:32px;height:32px;font-size:8px">PDF</div>
              <div class="upload-preview-name" id="preview-name">document.pdf</div>
              <button class="upload-preview-clear" onclick="clearFile()">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
          </div>

          <!-- Resource Name -->
          <div class="form-group">
            <label class="label">Resource Name</label>
            <input class="input" id="res-name" placeholder="e.g. Chapter 1–3 Manuscript Template"/>
          </div>

          <!-- Category -->
          <div class="form-group">
            <label class="label">Category</label>
            <select class="input" id="res-category">
              <option value="">Select a category…</option>
              <option value="pre-proposal">Pre-Proposal Template</option>
              <option value="ch1-3">Chapter 1–3 Template</option>
              <option value="ch4-5">Chapter 4–5 Template</option>
              <option value="final">Final Manuscript Template</option>
              <option value="presentation">Presentation Template</option>
              <option value="lesson">Lesson / Reference Material</option>
              <option value="forms">Forms &amp; Checklists</option>
              <option value="other">Other</option>
            </select>
          </div>

          <!-- Visibility -->
          <div class="form-group" style="margin-bottom:0">
            <label class="label">Visible To</label>
            <div class="vis-toggle" id="vis-toggle">
              <label class="vis-opt" id="vis-students" onclick="setVisibility('students')">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                Students
              </label>
              <label class="vis-opt" id="vis-experts" onclick="setVisibility('experts')">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Experts
              </label>
              <label class="vis-opt checked-both" id="vis-both" onclick="setVisibility('both')">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="20 6 9 17 4 12"/></svg>
                Both
              </label>
            </div>
          </div>

          <div style="margin-top:16px">
            <button class="btn btn-primary" style="width:100%;justify-content:center;font-size:13px" onclick="uploadResource()">
              <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              Upload Resource
            </button>
          </div>
        </div>

        <!-- Quick stats -->
        <div class="card" style="padding:14px 16px">
          <div class="card-header" style="margin-bottom:12px">
            <span class="card-title">Library Summary</span>
          </div>
          <div class="grid-2" style="gap:8px">
            <div class="alltime-chip">
              <div class="n" id="stat-total" style="color:var(--accent3)">7</div>
              <div class="l">Total Files</div>
            </div>
            <div class="alltime-chip">
              <div class="n" id="stat-downloads" style="color:var(--blue)">142</div>
              <div class="l">Downloads</div>
            </div>
            <div class="alltime-chip">
              <div class="n" id="stat-students" style="color:var(--green)">4</div>
              <div class="l">Student-Visible</div>
            </div>
            <div class="alltime-chip">
              <div class="n" id="stat-experts" style="color:var(--purple)">3</div>
              <div class="l">Expert-Visible</div>
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT: FILE LIBRARY -->
      <div>
        <!-- Filter bar -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:10px;flex-wrap:wrap">
          <div class="filter-tabs" id="filter-tabs">
            <button class="filter-tab active" onclick="filterResources('all',this)">All</button>
            <button class="filter-tab" onclick="filterResources('pre-proposal',this)">Pre-Proposal</button>
            <button class="filter-tab" onclick="filterResources('ch1-3',this)">Ch. 1–3</button>
            <button class="filter-tab" onclick="filterResources('ch4-5',this)">Ch. 4–5</button>
            <button class="filter-tab" onclick="filterResources('final',this)">Final MS</button>
            <button class="filter-tab" onclick="filterResources('presentation',this)">Presentation</button>
            <button class="filter-tab" onclick="filterResources('lesson',this)">Lessons</button>
            <button class="filter-tab" onclick="filterResources('forms',this)">Forms</button>
          </div>
          <div style="display:flex;align-items:center;gap:8px">
            <input class="input" id="search-input" placeholder="Search resources…" style="width:200px;font-size:12px" oninput="renderCards()"/>
            <select class="input" style="width:130px;font-size:12px" id="vis-filter" onchange="renderCards()">
              <option value="all">All Visibility</option>
              <option value="students">Students Only</option>
              <option value="experts">Experts Only</option>
              <option value="both">Both</option>
            </select>
          </div>
        </div>

        <!-- Cards container -->
        <div id="resource-list" class="gap-12"></div>
      </div>
    </div>
  </div>
</div>

<script>
// ── DATA ─────────────────────────────────────────────────────────────────────
let resources = [
  {id:1,name:'Pre-Proposal Form Template',category:'pre-proposal',ext:'docx',size:'124 KB',visibility:'both',downloads:38,date:'Mar 10, 2026'},
  {id:2,name:'Chapter 1–3 Manuscript Template',category:'ch1-3',ext:'docx',size:'256 KB',visibility:'students',downloads:54,date:'Mar 12, 2026'},
  {id:3,name:'Chapter 4–5 Results & Discussion Template',category:'ch4-5',ext:'docx',size:'198 KB',visibility:'students',downloads:22,date:'Mar 14, 2026'},
  {id:4,name:'Final Manuscript Formatting Guide',category:'final',ext:'pdf',size:'340 KB',visibility:'both',downloads:11,date:'Mar 20, 2026'},
  {id:5,name:'Defense Presentation Slide Deck',category:'presentation',ext:'pptx',size:'1.2 MB',visibility:'students',downloads:9,date:'Mar 28, 2026'},
  {id:6,name:'APA 7th Edition Quick Reference',category:'lesson',ext:'pdf',size:'87 KB',visibility:'both',downloads:5,date:'Apr 1, 2026'},
  {id:7,name:'Panel Evaluation & Grading Rubric',category:'forms',ext:'pdf',size:'112 KB',visibility:'experts',downloads:3,date:'Apr 3, 2026'},
];
let nextId = 8;
let currentFilter = 'all';
let pendingDeleteId = null;
let currentVisibility = 'both';

// ── RENDER ────────────────────────────────────────────────────────────────────
const CAT_LABELS = {
  'pre-proposal':'Pre-Proposal Template',
  'ch1-3':'Chapter 1–3 Template',
  'ch4-5':'Chapter 4–5 Template',
  'final':'Final Manuscript Template',
  'presentation':'Presentation Template',
  'lesson':'Lesson / Reference',
  'forms':'Forms & Checklists',
  'other':'Other',
};
const CAT_CLASS = {
  'pre-proposal':'cat-ch1','ch1-3':'cat-ch2','ch4-5':'cat-ch3',
  'final':'cat-ch4','presentation':'cat-ch5','lesson':'cat-ch6',
  'forms':'cat-ch1','other':'cat-ch4',
};
const VIS_LABEL = {students:'Students Only',experts:'Experts Only',both:'Students & Experts'};
const VIS_BADGE_STYLE = {
  students:'background:var(--blue-bg);color:var(--blue);border:1px solid rgba(29,78,216,.15)',
  experts:'background:var(--green-bg);color:var(--green);border:1px solid rgba(22,163,74,.15)',
  both:'background:rgba(201,168,44,.08);color:var(--accent3);border:1px solid rgba(201,168,44,.2)',
};
const EXT_CLASS = {pdf:'ftype-pdf',docx:'ftype-docx',pptx:'ftype-pptx',doc:'ftype-docx',ppt:'ftype-pptx'};

function extIcon(ext){
  const e=ext.toUpperCase();
  const c=EXT_CLASS[ext]||'ftype-pdf';
  return `<div class="ftype ${c}">${e}</div>`;
}

function renderCards(){
  const q = document.getElementById('search-input').value.trim().toLowerCase();
  const vf = document.getElementById('vis-filter').value;
  const list = document.getElementById('resource-list');

  let filtered = resources.filter(r=>{
    const matchCat = currentFilter==='all' || r.category===currentFilter;
    const matchQ = !q || r.name.toLowerCase().includes(q) || CAT_LABELS[r.category]?.toLowerCase().includes(q);
    const matchVis = vf==='all' || r.visibility===vf;
    return matchCat && matchQ && matchVis;
  });

  if(!filtered.length){
    list.innerHTML=`<div class="card empty-state">
      <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      <p>No resources match the current filter.</p>
    </div>`;
    return;
  }

  list.innerHTML = filtered.map(r=>`
    <div class="resource-card" id="rcard-${r.id}">
      ${extIcon(r.ext)}
      <div class="resource-meta">
        <div class="resource-name" title="${r.name}">${r.name}</div>
        <div style="display:flex;align-items:center;gap:6px;margin-top:5px;flex-wrap:wrap">
          <span class="cat-chip ${CAT_CLASS[r.category]||'cat-ch4'}">${CAT_LABELS[r.category]||'Other'}</span>
          <span class="badge" style="font-size:10px;padding:3px 9px;${VIS_BADGE_STYLE[r.visibility]}">${VIS_LABEL[r.visibility]}</span>
        </div>
        <div class="resource-info">
          <span>
            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><path d="M12 15V3"/></svg>
            ${r.downloads} downloads
          </span>
          <span>·</span>
          <span>
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            ${r.date}
          </span>
          <span>·</span>
          <span>${r.size}</span>
        </div>
        <div class="resource-actions">
          <button class="btn btn-ghost" style="font-size:11px;padding:4px 12px;color:var(--text2)" onclick="simulateDownload(${r.id},this)">
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><path d="M12 15V3"/></svg>
            Download
          </button>
          <button class="btn btn-danger" style="font-size:11px;padding:4px 12px" onclick="confirmDelete(${r.id},'${r.name.replace(/'/g,"\\'")}')">
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            Remove
          </button>
        </div>
      </div>
    </div>
  `).join('');

  updateStats();
}

function updateStats(){
  document.getElementById('stat-total').textContent = resources.length;
  document.getElementById('stat-downloads').textContent = resources.reduce((a,r)=>a+r.downloads,0);
  document.getElementById('stat-students').textContent = resources.filter(r=>r.visibility==='students'||r.visibility==='both').length;
  document.getElementById('stat-experts').textContent = resources.filter(r=>r.visibility==='experts'||r.visibility==='both').length;
}

// ── FILTER ────────────────────────────────────────────────────────────────────
function filterResources(cat, btn){
  currentFilter = cat;
  document.querySelectorAll('.filter-tab').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  renderCards();
}

// ── VISIBILITY TOGGLE ─────────────────────────────────────────────────────────
function setVisibility(v){
  currentVisibility = v;
  ['students','experts','both'].forEach(k=>{
    const el = document.getElementById('vis-'+k);
    el.className='vis-opt'+(k===v?' checked-'+k:'');
  });
}

// ── FILE SELECT ───────────────────────────────────────────────────────────────
let selectedFile = null;
function onFileSelect(e){ handleFile(e.target.files[0]); }
function onDragOver(e){ e.preventDefault(); document.getElementById('dropzone').classList.add('drag-over'); }
function onDragLeave(){ document.getElementById('dropzone').classList.remove('drag-over'); }
function onDrop(e){
  e.preventDefault();
  document.getElementById('dropzone').classList.remove('drag-over');
  if(e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]);
}

function handleFile(file){
  if(!file) return;
  const allowed=['pdf','doc','docx','ppt','pptx'];
  const ext = file.name.split('.').pop().toLowerCase();
  if(!allowed.includes(ext)){ showToast('❌ Unsupported file type. Use PDF, DOCX, or PPTX.'); return; }
  if(file.size>25*1024*1024){ showToast('❌ File too large. Max 25 MB.'); return; }
  selectedFile = {name:file.name,ext,size:formatSize(file.size)};
  const prev = document.getElementById('upload-preview');
  prev.classList.add('show');
  document.getElementById('preview-name').textContent = file.name;
  const ftEl = document.getElementById('preview-ftype');
  ftEl.textContent = ext.toUpperCase();
  ftEl.className = 'ftype '+(EXT_CLASS[ext]||'ftype-pdf');
  // Auto-fill name if empty
  const nameInput = document.getElementById('res-name');
  if(!nameInput.value){
    nameInput.value = file.name.replace(/\.[^.]+$/,'').replace(/[-_]/g,' ');
  }
}

function clearFile(){
  selectedFile = null;
  document.getElementById('file-input').value='';
  document.getElementById('upload-preview').classList.remove('show');
}

function formatSize(bytes){
  if(bytes<1024) return bytes+' B';
  if(bytes<1024*1024) return (bytes/1024).toFixed(0)+' KB';
  return (bytes/(1024*1024)).toFixed(1)+' MB';
}

// ── UPLOAD ─────────────────────────────────────────────────────────────────────
function uploadResource(){
  const name = document.getElementById('res-name').value.trim();
  const category = document.getElementById('res-category').value;
  if(!selectedFile){ showToast('⚠️ Please select a file first.'); return; }
  if(!name){ document.getElementById('res-name').focus(); showToast('⚠️ Please enter a resource name.'); return; }
  if(!category){ document.getElementById('res-category').focus(); showToast('⚠️ Please choose a category.'); return; }

  const today = new Date();
  const dateStr = today.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});

  resources.unshift({
    id: nextId++,
    name,
    category,
    ext: selectedFile.ext,
    size: selectedFile.size,
    visibility: currentVisibility,
    downloads: 0,
    date: dateStr,
  });

  resetForm();
  // Reset to "all" tab
  filterResources('all', document.querySelector('.filter-tab'));
  renderCards();
  showToast('✓ Resource uploaded successfully.');
}

function resetForm(){
  clearFile();
  document.getElementById('res-name').value='';
  document.getElementById('res-category').value='';
  setVisibility('both');
}

// ── DOWNLOAD (SIMULATED) ──────────────────────────────────────────────────────
function simulateDownload(id, btn){
  btn.classList.add('btn-downloading');
  btn.textContent='Preparing…';
  setTimeout(()=>{
    const r = resources.find(x=>x.id===id);
    if(r){ r.downloads++; renderCards(); }
    showToast('⬇ Download started.');
  },900);
}

// ── DELETE ─────────────────────────────────────────────────────────────────────
function confirmDelete(id, name){
  pendingDeleteId = id;
  document.getElementById('modal-sub').textContent = `"${name}" will be permanently removed and users will no longer be able to access it.`;
  const overlay = document.getElementById('modal-overlay');
  overlay.classList.add('show');
  document.getElementById('modal-confirm-btn').onclick = () => {
    resources = resources.filter(r=>r.id!==id);
    closeModal();
    renderCards();
    showToast('🗑 Resource removed.');
  };
}

function closeModal(){
  document.getElementById('modal-overlay').classList.remove('show');
  pendingDeleteId = null;
}

document.getElementById('modal-overlay').addEventListener('click', e=>{
  if(e.target===e.currentTarget) closeModal();
});

// ── TOAST ─────────────────────────────────────────────────────────────────────
let toastTimer;
function showToast(msg){
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(()=>t.classList.remove('show'),2800);
}

// ── INIT ──────────────────────────────────────────────────────────────────────
renderCards();
</script>
</body>
</html>
