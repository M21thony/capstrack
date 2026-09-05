<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<title>Resources - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
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

@include('capsa._sidebar')
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Templates &amp; Lessons</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="sk" style="height:56px;border-radius:12px;margin-bottom:14px"></div>
    <div class="res-upload-list-grid" style="gap:16px">
      <div class="card" style="padding:16px">
        <div class="sk" style="height:120px;border-radius:10px;margin-bottom:12px"></div>
        <div class="sk" style="height:38px;border-radius:8px;margin-bottom:10px"></div>
        <div class="sk" style="height:38px;border-radius:8px"></div>
      </div>
      <div class="card" style="padding:16px">
        <div class="sk" style="height:56px;border-radius:8px;margin-bottom:10px"></div>
        <div class="sk" style="height:56px;border-radius:8px;margin-bottom:10px"></div>
        <div class="sk" style="height:56px;border-radius:8px"></div>
      </div>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content">

    {{-- One line saying what this page is for, the Drive state as a dot
         rather than a banner, and the way in. --}}
    <div class="lib-head">
      <div class="lib-head-text">
        <div class="lib-rule">Students only see files shared with their section</div>
        <div class="lib-count" id="lib-count">&nbsp;</div>
      </div>
      <div class="lib-head-actions">
        <span class="drive-pill" id="gdrive-banner">
          <span class="drive-dot" id="gdrive-dot"></span>
          <span id="gdrive-status-text">Checking Google Drive…</span>
        </span>
        <a href="{{ route('gdrive.connect') }}" class="btn btn-primary" id="gdrive-connect-btn" style="font-size:12px;display:none">Connect Google Drive</a>
        <button class="btn btn-ghost" id="gdrive-disconnect-btn" style="font-size:12px;display:none" onclick="disconnectDrive()">Disconnect</button>
        @if ($pickerKey && $googleClientId)
          <button class="btn btn-ghost" id="pick-drive-btn" style="font-size:12.5px;display:none" onclick="openDrivePicker()">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 4h5l2 2h9v13H4z"/></svg>
            Choose from Drive
          </button>
        @endif
        <button class="btn btn-primary" id="open-upload-btn" style="font-size:12.5px" onclick="openUpload()">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
          Upload
        </button>
      </div>
    </div>

    {{-- The upload panel sits over the page and is opened from the button
         above, so the library gets the whole width the rest of the time. --}}
    <div class="upload-scrim" id="upload-scrim" hidden onclick="closeUpload(event)">
      <div class="upload-sheet" onclick="event.stopPropagation()">
        <div class="card" style="padding:16px;box-shadow:none;border:0">
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
            {{-- A browser cannot send a folder, only what is inside one, so this
                 quietly turns into a list of files. --}}
            <label class="folder-pick">
              <input type="file" id="folder-input" webkitdirectory multiple onchange="onFolderSelect(event)">
              <svg viewBox="0 0 24 24"><path d="M4 4h5l2 2h9v13H4z"/></svg>
              Upload a whole folder instead
            </label>
            <div class="upload-preview" id="upload-preview">
              <div id="preview-ftype" class="ftype ftype-pdf" style="width:32px;height:32px;font-size:8px">PDF</div>
              <div class="upload-preview-name" id="preview-name">document.pdf</div>
              <button class="upload-preview-clear" onclick="clearFile()">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
          </div>

          <!-- Resource Name -->
          <div class="form-group" id="res-name-group">
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

          {{-- Who it is for. A CaPSA hands material to their own sections, so
               those are what is offered - the program list used to include
               programs they do not handle. Tick none to reach all of yours.
               Checkboxes rather than a dropdown: with a handful of sections
               you can see the whole choice without opening anything. --}}
          @if (! $isAdmin && count($mySections))
          <div class="form-group">
            <label class="label">
              Who should be able to open this?
              <span class="req-chip">Pick at least one</span>
            </label>
            <div class="section-picker" id="res-sections">
              @foreach ($mySections as $sectionLabel)
                <label class="section-box">
                  <input type="checkbox" value="{{ $sectionLabel }}">
                  <span class="section-tick">
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                  </span>
                  <span class="section-name">{{ $sectionLabel }}</span>
                </label>
              @endforeach
            </div>
            <p class="section-hint" id="res-sections-hint">Tick every section to hand it to all your students.</p>
          </div>
          @else
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
          @endif

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

      </div>
    </div>

    {{-- What the Picker hands back, before any of it is written down. Close
         this and nothing was imported. --}}
    <div class="upload-scrim" id="import-scrim" hidden onclick="closeImport(event)">
      <div class="upload-sheet" onclick="event.stopPropagation()">
        <div class="card" style="padding:16px;box-shadow:none;border:0">
          <div class="card-header">
            <span class="card-title">Take these on</span>
            <button onclick="closeImport()" style="background:none;border:none;font-size:11px;color:var(--text3);cursor:pointer">Cancel</button>
          </div>

          <p class="import-warn">
            <svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
            <span>These files stay in your Drive — nothing is copied. They will be made
            <b>viewable by anyone with the link</b>, which is how students open them.</span>
          </p>

          <div class="import-list" id="import-list"></div>

          <div class="form-group" style="margin-top:14px">
            <label class="label">Category</label>
            <select class="input" id="import-category">
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

          @if (! $isAdmin && count($mySections))
          <div class="form-group">
            <label class="label">
              Who should be able to open these?
              <span class="req-chip">Pick at least one</span>
            </label>
            <div class="section-picker" id="import-sections">
              @foreach ($mySections as $sectionLabel)
                <label class="section-box">
                  <input type="checkbox" value="{{ $sectionLabel }}">
                  <span class="section-tick">
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                  </span>
                  <span class="section-name">{{ $sectionLabel }}</span>
                </label>
              @endforeach
            </div>
            <p class="section-hint">The same sections apply to everything in this batch.</p>
          </div>
          @endif

          <button class="btn btn-primary" id="import-btn" style="width:100%;justify-content:center;font-size:13px;margin-top:4px" onclick="runImport()">Take on&nbsp;<span id="import-count">0</span>&nbsp;files</button>
        </div>
      </div>
    </div>

    {{-- Four filters, one row, left to right in the order someone narrows:
         what kind of file, who it is for, where it lives, then free text. --}}
    <div class="lib-bar">
      <select class="input lib-filter" id="category-filter" onchange="renderCards()">
        <option value="all">All categories</option>
        <option value="pre-proposal">Pre-Proposal</option>
        <option value="ch1-3">Ch. 1–3</option>
        <option value="ch4-5">Ch. 4–5</option>
        <option value="final">Final MS</option>
        <option value="presentation">Presentation</option>
        <option value="lesson">Lessons</option>
        <option value="forms">Forms</option>
      </select>
      <select class="input lib-filter" id="section-filter" onchange="renderCards()">
        <option value="all">All sections</option>
        @foreach ($mySections as $sectionLabel)
          <option value="{{ $sectionLabel }}">{{ $sectionLabel }}</option>
        @endforeach
        <option value="__none__">Not shared with a section</option>
      </select>
      <select class="input lib-filter" id="folder-filter" onchange="renderCards()">
        <option value="all">All folders</option>
      </select>
      <input class="input lib-search" id="search-input" placeholder="Search templates and lessons…" oninput="renderCards()"/>
    </div>

    {{-- The strip that stops anyone having to ask what the labels mean. --}}
    <div class="lib-head-row" id="lib-head-row" hidden>
      <span class="h-file">File</span>
      <span class="h-who">Shared with</span>
      <span class="h-when">Added</span>
      <span class="h-acts"></span>
    </div>

    <div id="resource-list"></div>
  </div>
  </div>
</div>
@include('capsa.partials.skeleton-toggle')

<script>
// -- SERVER DATA -------------------------------------------------------------
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
let GDRIVE_CONNECTED = @json($driveConnected ?? false);
let driveFolders = @json($folders->map(fn($f) => ['id' => $f->drive_folder_id, 'name' => $f->name])->values());
let rawResources = @json($resources ?? []);

const ROUTES = {
  status: "{{ route('gdrive.status') }}",
  connect: "{{ route('gdrive.connect') }}",
  disconnect: "{{ route('gdrive.connection.destroy') }}",
  folders: "{{ route('gdrive.folders.index') }}",
  createFolder: "{{ route('gdrive.folders.store') }}",
  upload: "{{ route('gdrive.resources.store') }}",
  import: "{{ route('gdrive.imports.store') }}",
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
    // Empty means it was never aimed at particular sections, so it reaches
    // every student this CaPSA handles.
    sections: (r.sections || []).map(function (s) { return s.section; }),
    date: date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
    shortDate: date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
  };
}

const MY_SECTIONS = @json($mySections);
const PICKER_KEY = @json($pickerKey);
const GOOGLE_CLIENT_ID = @json($googleClientId);

let resources = rawResources.map(mapResource);

// Says what ticking nothing means, so an empty picker does not read as
// "nobody".
document.addEventListener('change', function (e) {
  if (!e.target.closest('#res-sections')) return;

  const picked = document.querySelectorAll('#res-sections input:checked').length;
  const hint = document.getElementById('res-sections-hint');
  if (!hint) return;

  // Name who is in and who is out, before anything is uploaded.
  const chosen = Array.prototype.map.call(
    document.querySelectorAll('#res-sections input:checked'), function (i) { return i.value; }
  );
  const left = MY_SECTIONS.filter(function (s) { return chosen.indexOf(s) === -1; });

  if (picked === 0) {
    hint.textContent = 'Tick every section to hand it to all your students.';
  } else if (!left.length) {
    hint.textContent = 'Every student you handle will see this.';
  } else {
    hint.textContent = 'Students in ' + chosen.join(' and ') + ' will see this. '
      + left.join(' and ') + ' will not.';
  }
});
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
// Who can open this file, in the words the header asks the question in.
// A file aimed at no section reaches everyone this CaPSA handles, and is
// drawn differently on purpose so nobody reads it as a section name.
function sharedWith(r){
  if(!r.sections.length){
    return '<span class="pill pill-all">All your students</span>';
  }
  return r.sections.map(function(s){
    return '<span class="pill pill-section">'+esc(s)+'</span>';
  }).join('');
}

function esc(v){
  return String(v).replace(/[&<>"']/g, function(c){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
  });
}

function renderCards(){
  const q = document.getElementById('search-input').value.trim().toLowerCase();
  const cf = document.getElementById('category-filter').value;
  const sf = document.getElementById('section-filter').value;
  const ff = document.getElementById('folder-filter').value;
  const list = document.getElementById('resource-list');
  const headRow = document.getElementById('lib-head-row');

  let filtered = resources.filter(r=>{
    const matchCat = cf==='all' || r.category===cf;
    const matchQ = !q || r.name.toLowerCase().includes(q) || (CAT_LABELS[r.category]||'').toLowerCase().includes(q);
    const matchSection = sf==='all'
      || (sf==='__none__' ? !r.sections.length : r.sections.indexOf(sf) !== -1);
    const matchFolder = ff==='all' || r.driveFolderId===ff;
    return matchCat && matchQ && matchSection && matchFolder;
  });

  updateStats(filtered.length);

  if(!filtered.length){
    headRow.hidden = true;
    // An empty library and a filter hiding everything are different things,
    // and saying the wrong one makes a new page look broken.
    list.innerHTML = resources.length
      ? `<div class="lib-empty">
           <b>Nothing matches</b>
           <span>No file here is in that category, section or folder. Clear the filters to see all ${resources.length}.</span>
           <button class="btn btn-ghost" style="font-size:12px" onclick="clearFilters()">Clear filters</button>
         </div>`
      : `<div class="lib-empty">
           <b>You haven't uploaded anything yet</b>
           <span>Files you upload here show up for the students in the sections you pick.</span>
           <button class="btn btn-primary" style="font-size:12.5px" onclick="openUpload()">
             <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
             Upload your first resource
           </button>
         </div>`;
    return;
  }

  headRow.hidden = false;

  list.innerHTML = '<div class="lib-list">' + filtered.map(r=>`
    <div class="lib-row" id="rcard-${r.id}">
      <span class="lib-file">
        ${extIcon(r.ext)}
        <span class="lib-file-text">
          <b title="${esc(r.name)}">${esc(r.name)}</b>
          <span class="lib-sub">${esc(CAT_LABELS[r.category]||'Other')} · ${esc(r.size)}</span>
        </span>
      </span>
      <span class="lib-who">${sharedWith(r)}</span>
      <span class="lib-when" title="${esc(r.date)}">${esc(r.shortDate)}</span>
      <span class="lib-acts">
        <button class="lib-icon" title="Open in Drive" onclick="downloadResource(${r.id},this)">
          <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14L21 3"/></svg>
        </button>
        <button class="lib-icon lib-icon-danger" title="Remove" onclick="confirmDelete(${r.id},'${r.name.replace(/'/g,"\\'")}')">
          <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
        </button>
      </span>
    </div>
  `).join('') + '</div>';
}

function clearFilters(){
  document.getElementById('category-filter').value = 'all';
  document.getElementById('section-filter').value = 'all';
  document.getElementById('folder-filter').value = 'all';
  document.getElementById('search-input').value = '';
  renderCards();
}

// One line of text where three coloured tiles used to be. It says the same
// thing and stops competing with the library for attention.
function updateStats(shown){
  const total = resources.length;
  const el = document.getElementById('lib-count');
  if(!el) return;

  if(!total){ el.textContent = 'Nothing here yet'; return; }

  const files = total + (total === 1 ? ' file' : ' files');
  el.textContent = (shown !== undefined && shown !== total)
    ? shown + ' of ' + files
    : files + (MY_SECTIONS.length ? ' · ' + MY_SECTIONS.join(', ') : '');
}

// ── the upload panel ──
function openUpload(){
  document.getElementById('upload-scrim').hidden = false;
  document.body.style.overflow = 'hidden';
  const name = document.getElementById('res-name');
  if(name) name.focus();
}

function closeUpload(e){
  if(e && e.target !== document.getElementById('upload-scrim')) return;
  document.getElementById('upload-scrim').hidden = true;
  document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e){
  if(e.key === 'Escape' && !document.getElementById('upload-scrim').hidden) closeUpload();
});

// -- FILE SELECT ---------------------------------------------------------------
let selectedFile = null;
let selectedFolder = [];   // set instead of selectedFile when a folder is picked
function onFileSelect(e){ handleFile(e.target.files[0]); }

const MAX_FOLDER_FILES = 25;

// The folder input hands over every file inside, subfolders included. Only the
// ones we accept are kept, and the rest are counted so the CaPSA is told what
// was left behind rather than wondering.
function onFolderSelect(e){
  const allowed = ['pdf','doc','docx','ppt','pptx'];
  const all = Array.prototype.slice.call(e.target.files);
  const usable = all.filter(function(f){
    return allowed.indexOf(f.name.split('.').pop().toLowerCase()) !== -1
        && f.size <= 25*1024*1024;
  });

  if(!usable.length){
    showToast('Nothing in that folder can be uploaded — PDF, DOCX or PPTX only.');
    e.target.value = '';
    return;
  }

  const skipped = all.length - usable.length;
  selectedFolder = usable.slice(0, MAX_FOLDER_FILES);
  selectedFile = null;

  const over = usable.length - selectedFolder.length;
  const parts = [selectedFolder.length + (selectedFolder.length === 1 ? ' file' : ' files')];
  if(skipped) parts.push(skipped + ' skipped');
  if(over) parts.push(over + ' over the ' + MAX_FOLDER_FILES + ' limit');

  const prev = document.getElementById('upload-preview');
  prev.classList.add('show');
  document.getElementById('preview-name').textContent = parts.join(' · ');
  const ftEl = document.getElementById('preview-ftype');
  ftEl.textContent = 'DIR';
  ftEl.className = 'ftype ftype-doc';

  // Each file is named after itself, so the one name box does not apply.
  const nameGroup = document.getElementById('res-name-group');
  if(nameGroup) nameGroup.style.display = 'none';
}
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
  selectedFolder = [];
  document.getElementById('file-input').value='';
  const folderInput = document.getElementById('folder-input');
  if(folderInput) folderInput.value='';
  document.getElementById('upload-preview').classList.remove('show');
  const nameGroup = document.getElementById('res-name-group');
  if(nameGroup) nameGroup.style.display = '';
}

// -- CHOOSE FROM DRIVE ------------------------------------------------------
// The Picker runs in the browser and hands back ids, not files. Nothing is
// uploaded: the server is told which files to take on, and the only change
// made to them is the sharing.
let picked = [];
let pickerReady = false;
let driveToken = null;

function loadPicker(){
  if(!PICKER_KEY || !GOOGLE_CLIENT_ID) return;

  const gapiScript = document.createElement('script');
  gapiScript.src = 'https://apis.google.com/js/api.js';
  gapiScript.onload = function(){
    gapi.load('picker', function(){
      pickerReady = true;
      const btn = document.getElementById('pick-drive-btn');
      if(btn && GDRIVE_CONNECTED) btn.style.display = 'inline-flex';
    });
  };
  document.head.appendChild(gapiScript);

  const gsi = document.createElement('script');
  gsi.src = 'https://accounts.google.com/gsi/client';
  document.head.appendChild(gsi);
}

function openDrivePicker(){
  if(!pickerReady){ showToast('Google Drive is still loading — try again in a moment.'); return; }

  // A fresh browser-side token rather than the one the server keeps, so the
  // stored credentials never reach the page.
  const client = google.accounts.oauth2.initTokenClient({
    client_id: GOOGLE_CLIENT_ID,
    scope: 'https://www.googleapis.com/auth/drive.file',
    callback: function(resp){
      if(resp.error){ showToast('Google would not grant access.'); return; }
      driveToken = resp.access_token;
      showPicker();
    },
  });

  client.requestAccessToken();
}

function showPicker(){
  const view = new google.picker.DocsView(google.picker.ViewId.DOCS)
    .setIncludeFolders(true)
    .setSelectFolderEnabled(true)
    .setMimeTypes([
      'application/pdf',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/vnd.ms-powerpoint',
      'application/vnd.openxmlformats-officedocument.presentationml.presentation',
      'application/vnd.google-apps.document',
      'application/vnd.google-apps.presentation',
    ].join(','));

  new google.picker.PickerBuilder()
    .setTitle('Choose templates and lessons')
    .setDeveloperKey(PICKER_KEY)
    .setOAuthToken(driveToken)
    .addView(view)
    .addView(new google.picker.DocsView(google.picker.ViewId.DOCS).setEnableDrives(true))
    .enableFeature(google.picker.Feature.MULTISELECT_ENABLED)
    .setCallback(onPicked)
    .build()
    .setVisible(true);
}

function onPicked(data){
  if(data.action !== google.picker.Action.PICKED) return;

  picked = (data.docs || []).map(function(d){
    return { id: d.id, name: d.name, mimeType: d.mimeType, isFolder: d.type === 'folder' };
  });

  if(!picked.length) return;
  openImport();
}

function openImport(){
  renderImportList();
  document.getElementById('import-scrim').hidden = false;
  document.body.style.overflow = 'hidden';
}

function closeImport(e){
  if(e && e.target !== document.getElementById('import-scrim')) return;
  document.getElementById('import-scrim').hidden = true;
  document.body.style.overflow = '';
}

function renderImportList(){
  const list = document.getElementById('import-list');

  list.innerHTML = picked.map(function(f, i){
    return '<div class="import-row">'
      + '<span class="import-name" title="' + esc(f.name) + '">' + esc(f.name) + '</span>'
      + (f.isFolder ? '<span class="pill pill-all">folder</span>' : '')
      + '<button class="lib-icon" title="Leave this out" onclick="dropPicked(' + i + ')">'
      + '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
      + '</button></div>';
  }).join('');

  document.getElementById('import-count').textContent = picked.length;
  document.getElementById('import-btn').disabled = !picked.length;
}

function dropPicked(i){
  picked.splice(i, 1);
  if(!picked.length){ closeImport(); return; }
  renderImportList();
}

function runImport(){
  const category = document.getElementById('import-category').value;
  const sections = Array.prototype.map.call(
    document.querySelectorAll('#import-sections input:checked'), function(i){ return i.value; }
  );

  if(!category){ document.getElementById('import-category').focus(); showToast('Please choose a category.'); return; }
  if(MY_SECTIONS.length && !sections.length){
    document.getElementById('import-sections').classList.add('needs-pick');
    showToast('Pick at least one section, so students can find these.');
    return;
  }

  const btn = document.getElementById('import-btn');
  const original = btn.innerHTML;
  btn.disabled = true;
  btn.textContent = 'Taking on ' + picked.length + '…';

  fetch(ROUTES.import, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': CSRF_TOKEN,
    },
    body: JSON.stringify({
      files: picked.map(function(f){
        return { drive_file_id: f.id, category: category, sections: sections };
      }),
    }),
  })
  .then(function(r){ return r.json(); })
  .then(function(data){
    btn.disabled = false;
    btn.innerHTML = original;

    const added = data.resources || [];
    added.slice().reverse().forEach(function(r){ resources.unshift(mapResource(r)); });

    if(!data.success){ showToast(data.message || 'Some files could not be taken on.'); }

    picked = [];
    closeImport();
    clearFilters();

    const parts = [];
    if(added.length) parts.push(added.length + (added.length === 1 ? ' file added' : ' files added'));
    if(data.skipped) parts.push(data.skipped + ' already here');
    if(parts.length) showToast(parts.join(' · ') + '.');
  })
  .catch(function(){
    btn.disabled = false;
    btn.innerHTML = original;
    showToast('Could not reach Google Drive.');
  });
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

      // The dot carries the state; the "?" here was a broken icon.
      const dot = document.getElementById('gdrive-dot');

      if (GDRIVE_CONNECTED) {
        text.textContent = 'Drive connected';
        dot.className = 'drive-dot drive-dot-on';
        connectBtn.style.display = 'none';
        disconnectBtn.style.display = 'inline-flex';
        const pickBtn = document.getElementById('pick-drive-btn');
        if(pickBtn && pickerReady) pickBtn.style.display = 'inline-flex';
      } else {
        text.textContent = 'Drive not connected';
        dot.className = 'drive-dot drive-dot-off';
        connectBtn.style.display = 'inline-flex';
        disconnectBtn.style.display = 'none';
      }
    })
    .catch(() => {
      document.getElementById('gdrive-status-text').textContent = 'Could not reach Google Drive';
      document.getElementById('gdrive-dot').className = 'drive-dot drive-dot-off';
    });
}

function disconnectDrive(){
  fetch(ROUTES.disconnect, {
    method: 'DELETE',
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
  sel.innerHTML = '<option value="all">All folders</option>' +
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
  // Empty means every section this CaPSA handles - the resource reaches
  // their own students either way, since that is who sees their uploads.
  const chosenSections = Array.prototype.map.call(
    document.querySelectorAll('#res-sections input:checked'), function (i) { return i.value; }
  );
  const programEl = document.getElementById('res-program');
  const program = programEl ? programEl.value : '';
  const folderId = document.getElementById('res-folder').value;

  const isFolder = selectedFolder.length > 0;

  if(!selectedFile && !isFolder){ showToast('Please choose a file or a folder first.'); return; }
  // A folder names its own files, so a title is only asked for one at a time.
  if(!isFolder && !name){ document.getElementById('res-name').focus(); showToast('Please enter a resource name.'); return; }
  if(!category){ document.getElementById('res-category').focus(); showToast('Please choose a category.'); return; }
  // Ticking nothing used to quietly mean everyone, which is a decision nobody
  // knew they were making.
  if(MY_SECTIONS.length && !chosenSections.length){
    document.getElementById('res-sections').classList.add('needs-pick');
    showToast('Pick at least one section, so students can find this.');
    return;
  }

  const fd = new FormData();
  if(isFolder){
    selectedFolder.forEach(function(f){ fd.append('files[]', f); });
  } else {
    fd.append('file', selectedFile);
    fd.append('title', name);
  }
  fd.append('category', category);
  chosenSections.forEach(function (s) { fd.append('sections[]', s); });
  if(program && !chosenSections.length) fd.append('resource_for', program);
  if(folderId) fd.append('drive_folder_id', folderId);

  const btn = document.getElementById('upload-btn');
  const originalHTML = btn.innerHTML;
  btn.disabled = true;
  btn.textContent = isFolder
    ? 'Uploading ' + selectedFolder.length + ' files…'
    : 'Uploading…';

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

    // One file or twenty-five, the answer is a list.
    const added = data.resources || (data.resource ? [data.resource] : []);
    added.slice().reverse().forEach(function(r){ resources.unshift(mapResource(r)); });

    resetForm();
    closeUpload();
    clearFilters();
    showToast(added.length > 1
      ? added.length + ' files uploaded to Google Drive.'
      : 'Uploaded to Google Drive.');
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
  document.getElementById('res-folder').value='';

  // A CaPSA sees section checkboxes where an admin sees the program dropdown,
  // so neither can be assumed to be on the page.
  const programEl = document.getElementById('res-program');
  if(programEl) programEl.value='';

  document.querySelectorAll('#res-sections input:checked').forEach(function(i){ i.checked = false; });
  const picker = document.getElementById('res-sections');
  if(picker) picker.classList.remove('needs-pick');
  const hint = document.getElementById('res-sections-hint');
  if(hint) hint.textContent = 'Tick every section to hand it to all your students.';
}

// -- DOWNLOAD -------------------------------------------------------------------
function downloadResource(id, btn){
  const r = resources.find(x=>x.id===id);
  if(!r || !r.driveLink){ showToast('? No file link available.'); return; }

  btn.classList.add('btn-downloading');
  btn.textContent='Opening…';

  window.open(r.driveLink, '_blank');
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
loadPicker();


/* Mobile sidebar toggle */
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() {
  if (!sidebarEl) return;
  sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar();
}
function openSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.add('mobile-open');
  sidebarOverlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.remove('mobile-open');
  sidebarOverlay.classList.remove('open');
  document.body.style.overflow = '';
}
</script>
</body>
</html>