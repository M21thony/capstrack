<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Archived Capstones - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
</head>
<body>

@include('admin._sidebar')
<div class="main">
  <div class="topbar">
    <div class="page-title">Archived Capstone Management</div>
    <div class="topbar-actions">
      @include('admin.partials.notification-dropdown')
      <button class="btn btn-ghost-nav" id="topbar-action" onclick="showAddArchive()">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <span id="action-label">Add Capstone</span>
      </button>
    </div>
  </div>

  <div class="content">
    <div class="grid-2" style="margin-bottom:16px;align-items:start">
      <div class="row" style="gap:8px;flex-wrap:wrap">
        <input class="input" id="arch-search" placeholder="Search title..." style="flex:1;min-width:180px" oninput="filterArch()"/>
        <select class="input" id="arch-year" style="width:120px" onchange="filterArch()">
          <option value="">All Years</option>
          @foreach($archives->pluck('academic_year')->unique()->sort() as $year)
          <option value="{{ $year }}">{{ $year }}</option>
          @endforeach
        </select>
        <select class="input" id="arch-tag" style="width:150px" onchange="filterArch()">
          <option value="">All Categories</option>
          @foreach($tags as $tag)
          <option value="{{ $tag->tag_name }}">{{ $tag->tag_name }}</option>
          @endforeach
        </select>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px;align-items:center">
        <span style="font-size:12px;color:var(--text3)" id="arch-count">Showing 8 studies</span>
      </div>
    </div>

    <div id="add-archive-modal" style="display:none;background:#fff;border:1.5px solid var(--accent);border-radius:12px;padding:20px;margin-bottom:16px;box-shadow:0 4px 16px rgba(201,150,12,0.12)">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <span style="font-size:13px;font-weight:600;color:var(--text)">Add Archived Capstone</span>
        <button onclick="hideAddArchive()" style="background:none;border:none;cursor:pointer;color:var(--text3);font-size:18px;line-height:1">&times;</button>
      </div>
      <div class="grid-2" style="gap:12px">
        <div>
          <div class="form-group"><label class="label">Capstone Title</label><input class="input" placeholder="Full title of the capstone study"/></div>
          <div class="form-group"><label class="label">Proponents (comma-separated)</label><input class="input" placeholder="e.g. Dela Cruz, J., Santos, M."/></div>
          <div class="grid-2" style="gap:8px">
            <div class="form-group"><label class="label">Year</label><select class="input"><option>2025</option><option>2024</option><option>2023</option><option>2022</option></select></div>
            <div class="form-group"><label class="label">Program</label><select class="input"><option>BSIT</option><option>BSCS</option><option>BSIS</option><option>BLIS</option></select></div>
          </div>
          <div class="form-group"><label class="label">Category Tags</label>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:2px">
              <label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer"><input type="checkbox" style="accent-color:var(--accent)"/> Web-Based</label>
              <label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer"><input type="checkbox" style="accent-color:var(--accent)"/> Mobile</label>
              <label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer"><input type="checkbox" style="accent-color:var(--accent)"/> IoT</label>
              <label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer"><input type="checkbox" style="accent-color:var(--accent)"/> AI/ML</label>
              <label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer"><input type="checkbox" style="accent-color:var(--accent)"/> Desktop</label>
              <label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer"><input type="checkbox" style="accent-color:var(--accent)"/> Data Analytics</label>
              <label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer"><input type="checkbox" style="accent-color:var(--accent)"/> Blockchain</label>
            </div>
          </div>
        </div>
        <div>
          <div class="form-group"><label class="label">Abstract</label><textarea class="input" style="min-height:120px" placeholder="Paste the capstone abstract here..."></textarea></div>
          <div class="form-group"><label class="label">Upload PDF (optional)</label>
            <div style="border:1.5px dashed var(--border2);border-radius:8px;padding:20px;text-align:center;cursor:pointer;transition:all 0.15s" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border2)'">
              <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="var(--text3)" stroke-width="2" style="margin-bottom:6px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <div style="font-size:12px;color:var(--text2);font-weight:500">Click to upload PDF</div>
              <div style="font-size:10px;color:var(--text3)">PDF up to 50MB</div>
            </div>
          </div>
          <button class="btn btn-primary" style="width:100%;justify-content:center;padding:10px;font-size:13px">Save to Archive</button>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px" id="arch-grid">
    @forelse($archives as $archive)
      <div class="archive-card"
        data-tags="{{ $archive->tags->pluck('tag_name')->implode(',') }}"
        data-year="{{ $archive->academic_year }}"
        data-title="{{ strtolower($archive->title) }}">
        <div class="archive-year">{{ $archive->academic_year ?? 'N/A' }}</div>
        <div class="archive-title-text">{{ $archive->title }}</div>
        <div class="archive-proponents">{{ Str::limit($archive->abstract ?? 'No description', 60) }}</div>
        <div class="archive-tags">
          @foreach($archive->tags as $tag)
          <span class="atag" style="background:{{ $tag->tag_color ?? 'var(--accent-glow)' }}">{{ $tag->tag_name }}</span>
          @endforeach
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;padding-top:8px;border-top:1px solid var(--border)">
          <span style="font-size:11px;color:var(--text3)">{{ $archive->program ?? 'N/A' }} — CCS</span>
          <button class="btn btn-danger" style="font-size:10px;padding:3px 10px" onclick="deleteArchive({{ $archive->archived_id }})">Remove</button>
        </div>
      </div>
    @empty
      <div style="grid-column:1/-1;text-align:center;padding:48px;color:var(--text3);font-size:13px">No archived capstones yet.</div>
    @endforelse
    </div>
    <div id="arch-empty" style="display:none;text-align:center;padding:48px;color:var(--text3);font-size:13px">No archived capstones match your search.</div>
  </div>
</div>

<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

function showAddArchive(){document.getElementById('add-archive-modal').style.display='block'}
function hideAddArchive(){document.getElementById('add-archive-modal').style.display='none'}
function filterArch(){
  const q=(document.getElementById('arch-search').value||'').toLowerCase();
  const yr=document.getElementById('arch-year').value;
  const tg=document.getElementById('arch-tag').value;
  const cards=document.querySelectorAll('#arch-grid .archive-card');
  let c=0;
  cards.forEach(card=>{
    const t=(card.dataset.title||'').toLowerCase();
    const y=card.dataset.year||'';
    const tags=card.dataset.tags||'';
    const show=(!q||t.includes(q))&&(!yr||y===yr)&&(!tg||tags.includes(tg));
    card.style.display=show?'':'none';
    if(show)c++;
  });
  document.getElementById('arch-count').textContent='Showing '+c+' studies';
  document.getElementById('arch-empty').style.display=c===0?'block':'none';
}
function deleteArchive(id) {
  if (!confirm('Delete this archived capstone?')) return;
  fetch('/admin/archive/' + id, {
    method: 'DELETE',
    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
  }).then(() => location.reload());
}
</script>
</body>
</html>
