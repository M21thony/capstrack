<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content" />
<title>Resources - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
@vite('resources/student-ui/styles.css')
<x-dashboard.styles />
<style nonce="{{ Vite::cspNonce() }}">
  @keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .sk-overlay { position: absolute; inset: 0; background: var(--bg); z-index: 10; }
  .sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }
  /* On a phone the three filters stack, each the full width, rather than
     running off the right edge in one fixed-width row. */
  @media (max-width: 768px) {
    .res-filters { flex-wrap: wrap; width: 100%; }
    .res-filters > * { flex: 1 1 100%; width: 100% !important; }
    .res-filters input { width: 100% !important; }
  }
</style>
@include('partials.skeleton-head', ['skReveal' => 'opacity'])
  @include('student.partials.render-whole')
</head>
<body>

@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="toast" id="toast"></div>
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Resources</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>

  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'resources'])
    <div id="contentWrapper" style="position: relative;">
<div id="skeleton-content" class="sk-overlay">
      <div class="grid-4" style="margin-bottom:18px">
        @for ($i = 0; $i < 4; $i++)
        <div class="stat-chip">
          <div class="sk" style="width:30px;height:22px;border-radius:4px;margin-bottom:6px"></div>
          <div class="sk" style="width:70px;height:9px;border-radius:4px"></div>
        </div>
        @endfor
      </div>
      <div style="display:flex;gap:8px;margin-bottom:14px">
        <div class="sk" style="width:160px;height:34px;border-radius:8px"></div>
        <div class="sk" style="width:210px;height:34px;border-radius:8px"></div>
        <div class="sk" style="width:180px;height:34px;border-radius:8px"></div>
      </div>
      <div class="gap-12">
        @for ($i = 0; $i < 5; $i++)
        <div style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--bg2);border:1px solid var(--border);border-radius:10px">
          <div class="sk" style="width:36px;height:36px;border-radius:8px;flex-shrink:0"></div>
          <div style="flex:1">
            <div class="sk" style="width:55%;height:12px;border-radius:4px;margin-bottom:6px"></div>
            <div class="sk" style="width:35%;height:9px;border-radius:4px"></div>
          </div>
          <div class="sk" style="width:70px;height:26px;border-radius:8px"></div>
        </div>
        @endfor
      </div>
    </div>

    <div id="real-content" style="opacity:0">
    <div class="grid-4" style="margin-bottom:18px">
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--accent3)" id="stat-total">0</div>
        <div class="stat-l">Total Files</div>
      </div>
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--blue)" id="stat-new">0</div>
        <div class="stat-l">New This Week</div>
      </div>
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--green)" id="stat-dl">0</div>
        <div class="stat-l">Downloaded</div>
      </div>
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--purple)" id="stat-cats">0</div>
        <div class="stat-l">Categories</div>
      </div>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;flex-wrap:wrap">
      <div class="res-filters" style="display:flex;gap:8px;align-items:center">
        <select class="input" id="cat-filter" style="width:160px;font-size:12px">
          <option value="all">All Categories</option>
          <option value="pre-proposal">Pre-Proposal</option>
          <option value="ch1-3">Ch. 1–3</option>
          <option value="ch4-5">Ch. 4–5</option>
          <option value="final">Final MS</option>
          <option value="presentation">Presentation</option>
          <option value="lesson">Lessons</option>
          <option value="forms">Forms</option>
        </select>
        <div style="position:relative">
          <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:13px;height:13px;stroke:var(--text3);fill:none;stroke-width:2;pointer-events:none" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input class="input" id="q" placeholder="Search resources…" style="padding-left:32px;width:210px;font-size:12px" />
        </div>
        <select class="input" id="folder-filter" style="width:180px;font-size:12px">
          <option value="all">All Folders</option>
          @foreach($folders as $folder)
            <option value="{{ $folder->drive_folder_id }}">{{ $folder->name }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div id="res-list" class="gap-12"></div>
    </div>
  </div>
</div>
</div>

<script nonce="{{ Vite::cspNonce() }}">
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
const resources = @json($resources);
const folders = @json($folders);

let searchTerm = '';

function updateStats(list) {
  const total = list.length;
  const weekAgo = new Date();
  weekAgo.setDate(weekAgo.getDate() - 7);
  const newThisWeek = list.filter(r => new Date(r.uploaded_at) > weekAgo).length;
  const totalDownloads = list.reduce((sum, r) => sum + (parseInt(r.downloads) || 0), 0);
  const cats = [...new Set(list.map(r => r.category).filter(Boolean))].length;

  document.getElementById('stat-total').textContent = total;
  document.getElementById('stat-new').textContent = newThisWeek;
  document.getElementById('stat-dl').textContent = totalDownloads;
  document.getElementById('stat-cats').textContent = cats;
}

function matchesSearch(r, term) {
  if (!term) return true;
  const q = term.toLowerCase();
  const haystack = [
    r.title,
    r.category,
    r.resource_for,
    r.file_type,
    r.file_name,
  ].filter(Boolean).join(' ').toLowerCase();

  return haystack.split(' ').some(word => word.startsWith(q));
}

function fileTypeBadge(r) {
  const ext = (r.file_type || r.file_name || '').split('.').pop().toLowerCase();
  const map = {
    pdf: { cls: 'ftype-pdf', label: 'PDF' },
    docx: { cls: 'ftype-docx', label: 'DOCX' },
    doc: { cls: 'ftype-docx', label: 'DOC' },
    pptx: { cls: 'ftype-pptx', label: 'PPTX' },
    ppt: { cls: 'ftype-pptx', label: 'PPT' },
  };
  const m = map[ext] || { cls: 'ftype-pdf', label: 'FILE' };
  return `<div class="ftype-badge ${m.cls}"><span>${m.label}</span></div>`;
}

function categoryChip(r) {
  const cat = (r.category || '').toLowerCase();
  const label = cat.replace(/[_-]/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
  const cls = cat ? 'cc-gold' : 'cc-gold';
  return `<span class="cat-chip ${cls}">${label || 'Uncategorized'}</span>`;
}

function formatSize(bytes) {
  if (!bytes) return '';
  const mb = bytes / (1024 * 1024);
  return mb >= 1 ? mb.toFixed(1) + ' MB' : (bytes / 1024).toFixed(0) + ' KB';
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function drivePreviewUrl(r) {
  if (!r.drive_file_id) return '#';
  return `https://drive.google.com/file/d/${r.drive_file_id}/preview`;
}

function driveDocsUrl(r) {
  if (!r.drive_file_id) return '#';
  return `https://docs.google.com/document/d/${r.drive_file_id}/edit`;
}

function mapResource(r) {
  return {
    id: r.resource_id,
    title: r.title || r.file_name || 'Untitled',
    category: r.category || 'Uncategorized',
    program: r.resource_for || '',
    fileType: r.file_type || '',
    fileSize: formatSize(r.file_size),
    date: formatDate(r.uploaded_at),
    downloads: parseInt(r.downloads) || 0,
    drivePreview: drivePreviewUrl(r),
    driveDocs: driveDocsUrl(r),
    driveFileId: r.drive_file_id || null,
  };
}

function renderCard(item) {
  return `
    <div class="res-card">
      ${fileTypeBadge(item)}
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
          ${categoryChip(item)}
          ${item.program ? `<span class="cat-chip cc-cyan">${item.program}</span>` : ''}
        </div>
        <div style="font-size:14px;font-weight:600;color:var(--text);line-height:1.35;margin-bottom:6px">${item.title}</div>
        <div class="res-meta-row">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <span>${item.date}</span>
          ${item.fileSize ? `<span class="res-meta-sep">·</span><span>${item.fileSize}</span>` : ''}
          <span class="res-meta-sep">·</span><span>${item.downloads} downloads</span>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0">
        <button class="btn-dl" onclick="downloadResource(${item.id}, this)">
          <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Download
        </button>
        <button class="btn-dl" onclick="viewInDrive('${item.drivePreview}', '${item.driveFileId || ''}')">
          <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          View
        </button>
      </div>
    </div>
  `;
}

function render() {
  const term = document.getElementById('q').value.trim();
  searchTerm = term;

  const category = document.getElementById('cat-filter').value;
  const folder = document.getElementById('folder-filter').value;

  let list = resources.map(mapResource);

  if (category !== 'all') {
    list = list.filter(r => r.category.toLowerCase().replace(/[_\s-]+/g, '-') === category);
  }

  if (folder !== 'all') {
    list = list.filter(r => String(r.driveFolderId) === String(folder));
  }

  if (searchTerm) {
    list = list.filter(r => matchesSearch(r, searchTerm));
  }

  const container = document.getElementById('res-list');
  if (!list.length) {
    const category = document.getElementById('cat-filter').value;
    const folder = document.getElementById('folder-filter').value;
    let msg = 'No resources available.';
    if (folder !== 'all') {
      const folderName = folders.find(f => String(f.drive_folder_id) === String(folder))?.name || 'this folder';
      msg = `No resources in "${folderName}".`;
    } else if (category !== 'all') {
      const catLabel = category.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
      msg = `No resources in "${catLabel}".`;
    } else if (searchTerm) {
      msg = `No results found for "${searchTerm}".`;
    }
    container.innerHTML = `<div class="empty-res"><p>${msg}</p></div>`;
  } else {
    container.innerHTML = list.map(renderCard).join('');
  }

  updateStats(list);
}

function showToast(msg) {
  if (window.ctToast) { return ctToast(msg, 'error'); }
  const toast = document.getElementById('toast');
  toast.textContent = msg;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 4000);
}

async function downloadResource(id, btn) {
  btn.classList.add('loading');
  try {
    const res = await fetch(`/student/resources/${id}/download`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
    });
    const data = await res.json();
    if (data.success) {
      window.location.href = `/student/resources/${id}/download`;
    } else {
      showToast(data.message || 'Download failed.');
    }
  } catch (e) {
    showToast('Download failed. Please try again.');
  } finally {
    btn.classList.remove('loading');
  }
}

function viewInDrive(link, fileId) {
  if (!link || link === '#') {
    showToast('No preview available.');
    return;
  }
  window.open(link, '_blank');
}

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('q').addEventListener('input', render);
  document.getElementById('cat-filter').addEventListener('change', render);
  document.getElementById('folder-filter').addEventListener('change', render);

  updateStats(resources.map(mapResource));
  render();
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
