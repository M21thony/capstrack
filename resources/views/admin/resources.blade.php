<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
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

<!-- NEW FOLDER MODAL -->
<div class="modal-overlay" id="folder-modal-overlay">
  <div class="modal-box">
    <div class="modal-title">New Google Drive Folder</div>
    <div class="form-group" style="margin-top:10px">
      <input class="input" id="new-folder-name" placeholder="e.g. Chapter 1-3 Templates"/>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" style="color:var(--text2);border-color:var(--border)" onclick="closeFolderModal()">Cancel</button>
      <button class="btn btn-primary" id="folder-modal-confirm-btn" onclick="submitNewFolder()">Create Folder</button>
    </div>
  </div>
</div>

@include('admin._sidebar')
<div class="main">
  <div class="topbar">
    <div class="page-title">Templates &amp; Lessons</div>
    <div class="topbar-actions">
      @include('admin.partials.notification-dropdown')
    </div>
  </div>

  <div class="content">

    <!-- GOOGLE DRIVE CONNECTION BANNER -->
    <div class="card" id="gdrive-banner" style="padding:14px 16px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:12px">
      <div style="display:flex;align-items:center;gap:10px">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><path d="M12 15V3"/>
        </svg>
        <span id="gdrive-status-text" style="font-size:13px;color:var(--text2)">Checking Google Drive connection…</span>
      </div>
      <div>
        <a href="{{ route('gdrive.connect') }}" class="btn btn-primary" id="gdrive-connect-btn" style="font-size:12px;display:none">Connect Google Drive</a>
        <button class="btn btn-ghost" id="gdrive-disconnect-btn" style="font-size:12px;display:none" onclick="disconnectDrive()">Disconnect</button>
      </div>
    </div>

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

          <!-- Program -->
          <div class="form-group">
            <label class="label">Program</label>
            <select class="input" id="res-program">
              <option value="">All Programs</option>
              <option value="BSIT">BSIT</option>
              <option value="BSCS">BSCS</option>
              <option value="BLIS">BLIS</option>
              <option value="BSIS">BSIS</option>
            </select>
          </div>

          <!-- Drive Folder -->
          <div class="form-group" style="margin-bottom:0">
            <label class="label">Google Drive Folder</label>
            <div style="display:flex;gap:6px">
              <select class="input" id="res-folder" style="flex:1">
                <option value="">No folder (Drive root)</option>
              </select>
              <button class="btn btn-ghost" style="padding:0 12px;white-space:nowrap" onclick="openFolderModal()">+ New</button>
            </div>
          </div>

          <div style="margin-top:16px">
            <button class="btn btn-primary" id="upload-btn" style="width:100%;justify-content:center;font-size:13px" onclick="uploadResource()">
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
              <div class="n" id="stat-total" style="color:var(--accent3)">0</div>
              <div class="l">Total Files</div>
            </div>
            <div class="alltime-chip">
              <div class="n" id="stat-downloads" style="color:var(--blue)">0</div>
              <div class="l">Downloads</div>
            </div>
            <div class="alltime-chip">
              <div class="n" id="stat-bsit" style="color:var(--green)">0</div>
              <div class="l">BSIT</div>
            </div>
            <div class="alltime-chip">
              <div class="n" id="stat-all-programs" style="color:var(--purple)">0</div>
              <div class="l">All Programs</div>
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT: FILE LIBRARY -->
      <div>
        <!-- Filter bar -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:10px;flex-wrap:wrap">
          <div style="display:flex;align-items:center;gap:8px">
            <select class="input" id="category-filter" style="width:170px;font-size:12px" onchange="renderCards()">
              <option value="all">All Categories</option>
              <option value="pre-proposal">Pre-Proposal</option>
              <option value="ch1-3">Ch. 1–3</option>
              <option value="ch4-5">Ch. 4–5</option>
              <option value="final">Final MS</option>
              <option value="presentation">Presentation</option>
              <option value="lesson">Lessons</option>
              <option value="forms">Forms</option>
            </select>
            <select class="input" id="folder-filter" style="width:170px;font-size:12px" onchange="renderCards()">
              <option value="all">All Folders</option>
            </select>
          </div>
          <div style="display:flex;align-items:center;gap:8px">
            <input class="input" id="search-input" placeholder="Search resources…" style="width:200px;font-size:12px" oninput="renderCards()"/>
            <select class="input" style="width:130px;font-size:12px" id="program-filter" onchange="renderCards()">
              <option value="all">All Programs</option>
              <option value="BSIT">BSIT</option>
              <option value="BSCS">BSCS</option>
              <option value="BLIS">BLIS</option>
              <option value="BSIS">BSIS</option>
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
// -- SERVER DATA -------------------------------------------------------------
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
let GDRIVE_CONNECTED = @json($driveConnected ?? false);
let driveFolders = @json($folders->map(fn($f) => ['id' => $f->drive_folder_id, 'name' => $f->name])->values());
let rawResources = @json($resources ?? []);

const ROUTES = {
  status: "{{ route('gdrive.status') }}",
  connect: "{{ route('gdrive.connect') }}",
  disconnect: "{{ route('gdrive.disconnect') }}",
  folders: "{{ route('gdrive.folders.index') }}",
  createFolder: "{{ route('gdrive.folders.store') }}",
  upload: "{{ route('gdrive.upload') }}",
  download: (id) => `{{ url('admin/gdrive/resources') }}/${id}/download`,
  destroy: (id) => `{{ url('admin/gdrive/resources') }}/${id}`,
};

// -- NORMALIZE backend resource -> card display shape ----------------------
function mapResource(r) {
  const date = r.uploaded_at ? new Date(r.uploaded_at) : new Date();
  return {
    id: r.resource_id,
    name: r.title,
    category: r.category || 'other',
    program: r.resource_for || '',
    ext: (r.file_type || 'pdf').toLowerCase(),
    size: r.file_size ? formatSize(r.file_size) : '—',
    driveLink: r.drive_web_view_link,
    driveFolderId: r.drive_folder_id || null,
    downloads: r.downloads || 0,
    date: date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
  };
}

let resources = rawResources.map(mapResource);
let pendingDeleteId = null;

// -- CATEGORY LABELS ----------------------------------------------------------
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
const EXT_CLASS = {pdf:'ftype-pdf',docx:'ftype-docx',pptx:'ftype-pptx',doc:'ftype-docx',ppt:'ftype-pptx'};

function extIcon(ext){
  const e=(ext||'pdf').toUpperCase();
  const c=EXT_CLASS[ext]||'ftype-pdf';
  return `<div class="ftype ${c}">${e}</div>`;
}

function formatSize(bytes){
  bytes = Number(bytes) || 0;
  if(bytes<1024) return bytes+' B';
  if(bytes<1024*1024) return (bytes/1024).toFixed(0)+' KB';
  return (bytes/(1024*1024)).toFixed(1)+' MB';
}

// -- RENDER --------------------------------------------------------------------
function renderCards(){
  const q = document.getElementById('search-input').value.trim().toLowerCase();
  const pf = document.getElementById('program-filter').value;
  const cf = document.getElementById('category-filter').value;
  const ff = document.getElementById('folder-filter').value;
  const list = document.getElementById('resource-list');

  let filtered = resources.filter(r=>{
    const matchCat = cf==='all' || r.category===cf;
    const matchQ = !q || r.name.toLowerCase().includes(q) || CAT_LABELS[r.category]?.toLowerCase().includes(q);
    const matchProgram = pf==='all' || r.program===pf || !r.program;
    const matchFolder = ff==='all' || r.driveFolderId===ff;
    return matchCat && matchQ && matchProgram && matchFolder;
  });

  if(!filtered.length){
    list.innerHTML=`<div class="card empty-state">
      <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      <p>No resources match the current filter.</p>
    </div>`;
    updateStats();
    return;
  }

  list.innerHTML = filtered.map(r=>`
    <div class="resource-card" id="rcard-${r.id}">
      ${extIcon(r.ext)}
      <div class="resource-meta">
        <div class="resource-name" title="${r.name}">${r.name}</div>
        <div style="display:flex;align-items:center;gap:6px;margin-top:5px;flex-wrap:wrap">
          <span class="cat-chip ${CAT_CLASS[r.category]||'cat-ch4'}">${CAT_LABELS[r.category]||'Other'}</span>
          ${r.program ? `<span class="badge" style="font-size:10px;padding:3px 9px">${r.program}</span>` : ''}
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
          <button class="btn btn-ghost" style="font-size:11px;padding:4px 12px;color:var(--text2)" onclick="downloadResource(${r.id},this)">
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
  document.getElementById('stat-bsit').textContent = resources.filter(r=>r.program==='BSIT').length;
  document.getElementById('stat-all-programs').textContent = resources.filter(r=>!r.program).length;
}

// -- FILE SELECT ---------------------------------------------------------------
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
  if(!allowed.includes(ext)){ showToast('? Unsupported file type. Use PDF, DOCX, or PPTX.'); return; }
  if(file.size>25*1024*1024){ showToast('? File too large. Max 25 MB.'); return; }
  selectedFile = file;
  const prev = document.getElementById('upload-preview');
  prev.classList.add('show');
  document.getElementById('preview-name').textContent = file.name;
  const ftEl = document.getElementById('preview-ftype');
  ftEl.textContent = ext.toUpperCase();
  ftEl.className = 'ftype '+(EXT_CLASS[ext]||'ftype-pdf');
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

// -- GOOGLE DRIVE CONNECTION STATUS -----------------------------------------
function refreshDriveStatus(){
  fetch(ROUTES.status)
    .then(r => r.json())
    .then(data => {
      GDRIVE_CONNECTED = data.connected;
      const text = document.getElementById('gdrive-status-text');
      const connectBtn = document.getElementById('gdrive-connect-btn');
      const disconnectBtn = document.getElementById('gdrive-disconnect-btn');

      if (GDRIVE_CONNECTED) {
        text.textContent = '? Google Drive connected — uploads sync automatically.';
        connectBtn.style.display = 'none';
        disconnectBtn.style.display = 'inline-flex';
      } else {
        text.textContent = 'Connect Google Drive to upload and store resources.';
        connectBtn.style.display = 'inline-flex';
        disconnectBtn.style.display = 'none';
      }
    })
    .catch(() => {
      document.getElementById('gdrive-status-text').textContent = 'Could not check Google Drive status.';
    });
}

function disconnectDrive(){
  fetch(ROUTES.disconnect, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
  })
  .then(r => r.json())
  .then(() => {
    showToast('Google Drive disconnected.');
    refreshDriveStatus();
  });
}

// -- FOLDERS ----------------------------------------------------------------
function populateFolderSelect(){
  const sel = document.getElementById('res-folder');
  sel.innerHTML = '<option value="">No folder (Drive root)</option>' +
    driveFolders.map(f => `<option value="${f.id}">${f.name}</option>`).join('');
}

function populateFolderFilter(){
  const sel = document.getElementById('folder-filter');
  const current = sel.value || 'all';
  sel.innerHTML = '<option value="all">All Folders</option>' +
    driveFolders.map(f => `<option value="${f.id}">${f.name}</option>`).join('');
  // keep the current selection if it still exists among the folders
  if ([...sel.options].some(o => o.value === current)) {
    sel.value = current;
  }
}

function openFolderModal(){
  if(!GDRIVE_CONNECTED){ showToast('?? Connect Google Drive first.'); return; }
  document.getElementById('new-folder-name').value = '';
  document.getElementById('folder-modal-overlay').classList.add('show');
}
function closeFolderModal(){
  document.getElementById('folder-modal-overlay').classList.remove('show');
}
function submitNewFolder(){
  const name = document.getElementById('new-folder-name').value.trim();
  if(!name){ showToast('?? Enter a folder name.'); return; }

  const btn = document.getElementById('folder-modal-confirm-btn');
  btn.disabled = true;
  btn.textContent = 'Creating…';

  fetch(ROUTES.createFolder, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
    body: JSON.stringify({ name }),
  })
  .then(r => r.json())
  .then(data => {
    btn.disabled = false;
    btn.textContent = 'Create Folder';
    if(!data.success){ showToast('? '+(data.message||'Failed to create folder.')); return; }

    driveFolders.push({ id: data.folder.drive_folder_id, name: data.folder.name });
    populateFolderSelect();
    populateFolderFilter();
    document.getElementById('res-folder').value = data.folder.drive_folder_id;
    closeFolderModal();
    showToast('? Folder created.');
  })
  .catch(() => {
    btn.disabled = false;
    btn.textContent = 'Create Folder';
    showToast('? Failed to create folder.');
  });
}

// -- UPLOAD ---------------------------------------------------------------------
function uploadResource(){
  if(!GDRIVE_CONNECTED){ showToast('?? Connect Google Drive first.'); return; }

  const name = document.getElementById('res-name').value.trim();
  const category = document.getElementById('res-category').value;
  const program = document.getElementById('res-program').value;
  const folderId = document.getElementById('res-folder').value;

  if(!selectedFile){ showToast('?? Please select a file first.'); return; }
  if(!name){ document.getElementById('res-name').focus(); showToast('?? Please enter a resource name.'); return; }
  if(!category){ document.getElementById('res-category').focus(); showToast('?? Please choose a category.'); return; }

  const fd = new FormData();
  fd.append('file', selectedFile);
  fd.append('title', name);
  fd.append('category', category);
  if(program) fd.append('resource_for', program);
  if(folderId) fd.append('drive_folder_id', folderId);

  const btn = document.getElementById('upload-btn');
  const originalHTML = btn.innerHTML;
  btn.disabled = true;
  btn.textContent = 'Uploading…';

  fetch(ROUTES.upload, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
    body: fd,
  })
  .then(r => r.json())
  .then(data => {
    btn.disabled = false;
    btn.innerHTML = originalHTML;
    if(!data.success){ showToast('? '+(data.message||'Upload failed.')); return; }

    resources.unshift(mapResource(data.resource));
    resetForm();
    document.getElementById('category-filter').value = 'all';
    document.getElementById('folder-filter').value = 'all';
    renderCards();
    showToast('? Uploaded to Google Drive.');
  })
  .catch(() => {
    btn.disabled = false;
    btn.innerHTML = originalHTML;
    showToast('? Upload failed.');
  });
}

function resetForm(){
  clearFile();
  document.getElementById('res-name').value='';
  document.getElementById('res-category').value='';
  document.getElementById('res-program').value='';
  document.getElementById('res-folder').value='';
}

// -- DOWNLOAD -------------------------------------------------------------------
function downloadResource(id, btn){
  const r = resources.find(x=>x.id===id);
  if(!r || !r.driveLink){ showToast('? No file link available.'); return; }

  btn.classList.add('btn-downloading');
  btn.textContent='Opening…';

  fetch(ROUTES.download(id), {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
  })
  .then(r2 => r2.json())
  .then(data => {
    if(data.success){ r.downloads = data.downloads; renderCards(); }
    window.open(r.driveLink, '_blank');
  })
  .catch(() => window.open(r.driveLink, '_blank'));
}

// -- DELETE ---------------------------------------------------------------------
function confirmDelete(id, name){
  pendingDeleteId = id;
  document.getElementById('modal-sub').textContent = `"${name}" will be permanently removed from Google Drive and users will no longer be able to access it.`;
  const overlay = document.getElementById('modal-overlay');
  overlay.classList.add('show');
  document.getElementById('modal-confirm-btn').onclick = () => {
    const confirmBtn = document.getElementById('modal-confirm-btn');
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Removing…';

    fetch(ROUTES.destroy(id), {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
    })
    .then(r => r.json())
    .then(data => {
      confirmBtn.disabled = false;
      confirmBtn.textContent = 'Remove';
      if(!data.success){ showToast('? '+(data.message||'Failed to remove resource.')); return; }

      resources = resources.filter(r=>r.id!==id);
      closeModal();
      renderCards();
      showToast('?? Resource removed.');
    })
    .catch(() => {
      confirmBtn.disabled = false;
      confirmBtn.textContent = 'Remove';
      showToast('? Failed to remove resource.');
    });
  };
}

function closeModal(){
  document.getElementById('modal-overlay').classList.remove('show');
  pendingDeleteId = null;
}

document.getElementById('modal-overlay').addEventListener('click', e=>{
  if(e.target===e.currentTarget) closeModal();
});
document.getElementById('folder-modal-overlay').addEventListener('click', e=>{
  if(e.target===e.currentTarget) closeFolderModal();
});

// -- TOAST ---------------------------------------------------------------------
let toastTimer;
function showToast(msg){
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(()=>t.classList.remove('show'),2800);
}

// -- INIT ----------------------------------------------------------------------
populateFolderSelect();
populateFolderFilter();
renderCards();
refreshDriveStatus();
</script>
</body>
</html>