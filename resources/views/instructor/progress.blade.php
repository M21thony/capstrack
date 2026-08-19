<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CapsTRACK â€” Expert Portal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/expert.css') }}" />
</head>
<body>
@include('instructor.partials.sidebar', ['currentPage' => 'progress'])
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Progress Monitoring</div>
    <div class="topbar-actions"><div class="notif-btn"><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><div class="notif-dot"></div></div></div>
  </div>
  <div id="skeleton-content" class="sk-overlay">
    <div class="grid-4" style="margin-bottom:16px">
      <div class="sk" style="height:78px;border-radius:14px"></div>
      <div class="sk" style="height:78px;border-radius:14px"></div>
      <div class="sk" style="height:78px;border-radius:14px"></div>
      <div class="sk" style="height:78px;border-radius:14px"></div>
    </div>
    <div class="row" style="margin-bottom:14px;gap:8px">
      <div class="sk" style="width:260px;height:34px;border-radius:8px"></div>
      <div class="sk" style="width:160px;height:34px;border-radius:8px"></div>
      <div class="sk" style="width:160px;height:34px;border-radius:8px"></div>
    </div>
    <div class="team-progress-grid">
      <div class="sk" style="height:150px;border-radius:14px"></div>
      <div class="sk" style="height:150px;border-radius:14px"></div>
      <div class="sk" style="height:150px;border-radius:14px"></div>
    </div>
  </div>

  <div id="real-content" style="display:none">
  <div class="content">
    <div class="grid-4" style="margin-bottom:16px">
      <div class="stat-chip"><div class="stat-n" style="color:var(--green)">{{ $teamsMonitored }}</div><div class="stat-l">Teams Monitored</div></div>
      <div class="stat-chip"><div class="stat-n" style="color:var(--accent3)">{{ $avgCompletion }}%</div><div class="stat-l">Avg. Completion</div></div>
      <div class="stat-chip"><div class="stat-n" style="color:var(--red)">{{ $overdueMilestones }}</div><div class="stat-l">Overdue Milestones</div></div>
      <div class="stat-chip"><div class="stat-n" style="color:var(--blue)">{{ $onTrack }}</div><div class="stat-l">On Track</div></div>
    </div>

    <div class="row" style="margin-bottom:14px;gap:8px">
      <input class="input" placeholder="Search teams..." style="max-width:260px"/>
      <select class="input" style="width:160px"><option>All Sections</option><option>BSCS-4A</option><option>BSIT-4A</option><option>BSIT-4B</option></select>
      <select class="input" style="width:160px"><option>Sort: Progress</option><option>Sort: Team Name</option><option>Sort: Overdue First</option></select>
    </div>

    <div class="team-progress-grid">
    @forelse($teams as $entry)
      <div class="team-progress-card">
        <div class="tp-header">
          <div class="tp-avatar" style="background:linear-gradient(135deg,#c9a82c,#8a6e10)">{{ substr($entry->team->team_name, 0, 2) }}</div>
          <div style="flex:1">
            <div class="tp-team">{{ $entry->team->team_name }} <span class="badge badge-gold" style="font-size:10px;margin-left:4px;vertical-align:middle">{{ $entry->section }}</span></div>
            <div class="tp-title">{{ $entry->team->project_title ?? 'No title set' }}</div>
          </div>
          <div style="text-align:right">
            <div style="font-family:'Space Mono',monospace;font-size:16px;font-weight:700;color:{{ $entry->progress >= 70 ? 'var(--green)' : ($entry->progress >= 40 ? 'var(--accent3)' : 'var(--red)') }}">{{ $entry->progress }}%</div>
            <div style="font-size:10px;color:var(--text3)">overall</div>
          </div>
        </div>
        <div class="tp-milestones">
          <div style="font-size:12px;color:var(--text2);padding:8px 0">
            {{ $entry->completed }}/{{ $entry->total }} milestones completed
          </div>
        </div>
        <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);display:flex;gap:6px">
          @if($entry->overdue > 0)
            <span class="badge badge-amber" style="font-size:10px">{{ $entry->overdue }} Overdue</span>
          @else
            <span class="badge badge-green" style="font-size:10px">On Track</span>
          @endif
        </div>
      </div>
    @empty
      <div style="grid-column:1/-1;text-align:center;padding:48px;color:var(--text3);font-size:13px">No teams assigned yet.</div>
    @endforelse
    </div>
  </div>
  </div>
</div>
@include('instructor.partials.skeleton-toggle')

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     EXPERT PROFILE SIDE PANEL
     Sits flush against the right edge of the
     sidebar (left: 250px). No dark overlay.
     â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<div class="ep-overlay" id="ep-overlay" onclick="handleEpOverlay(event)">
  <div class="ep-modal" id="ep-modal">

    <!-- Sticky Header -->
    <div class="ep-header">
      <div class="ep-avatar">CS</div>
      <div style="flex:1;min-width:0">
        <div class="ep-header-name">Dr. Neil A. Inocencio</div>
        <div class="ep-header-sub">Capstone Expert · Active</div>
      </div>
      <button class="ep-close" onclick="closeExpertProfile()">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <!-- Scrollable Body -->
    <div class="ep-body">

      <!-- Full Name â€” readonly -->
      <div class="ep-field">
        <div class="ep-label">Full Name</div>
        <div class="ep-value readonly">
          <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Dr. Neil A. Inocencio
        </div>
      </div>

      <!-- Email â€” readonly -->
      <div class="ep-field">
        <div class="ep-label">Institutional Email</div>
        <div class="ep-value readonly">
          <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          nainocencio@cspc.edu.ph
        </div>
      </div>

      <div class="ep-divider"></div>

        <!-- Roles â€” readonly, assigned by admin -->
        <div class="ep-field">
          <div class="ep-label">Assigned Roles</div>
          <div class="ep-tags-wrap">
            <span class="role-pill role-adviser">Adviser</span>
            <span class="role-pill role-panel">Panel</span>
            <span class="role-pill role-consultant">Consultant</span>
            <span class="role-pill role-grammarian">Grammarian</span>
          </div>
        <div style="font-size:11px;color:var(--text3);margin-top:8px;display:flex;align-items:center;gap:4px">
          <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Managed by the system administrator
        </div>
      </div>

      <div class="ep-divider"></div>

      <!-- Max Teams â€” readonly -->
      <div class="ep-field">
        <div class="ep-label">Team Capacity</div>
        <div class="ep-value readonly">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Max 4 teams · Currently advising 3
        </div>
      </div>

      <div class="ep-divider"></div>

      <!-- Expertise Tags â€” editable -->
      <div class="ep-field">
        <div class="ep-label">Areas of Expertise</div>
        <div class="ep-tags-wrap" id="ep-tags-wrap"></div>
        <div class="ep-tag-input-row">
          <input
            class="ep-tag-input"
            id="ep-tag-input"
            type="text"
            placeholder="Add expertiseâ€¦"
            onkeydown="handleTagKey(event)"
          />
          <button class="ep-tag-add-btn" onclick="addTag()">+ Add</button>
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:7px">
          Press Enter or click Add. Click Ã— to remove.
        </div>
      </div>

    </div>

    <!-- Sticky Footer -->
    <div class="ep-footer">
      <button class="btn content btn-ghost" style="color: #FFFF00" onclick="closeExpertProfile()">Close</button>
      <button class="btn btn-primary" onclick="saveExpertProfile()">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Save
      </button>
    </div>

  </div>
</div>

<script>
    // Profile Modal Logic
  const EP_OVERLAY = document.getElementById('ep-overlay');
const EP_TRIGGER = document.getElementById('ep-trigger');
const EP_TAGS_EL = document.getElementById('ep-tags-wrap');
const EP_INPUT   = document.getElementById('ep-tag-input');

let epTags = [
  'Web & Mobile Development',
  'Machine Learning & AI',
  'IoT & Embedded Systems',
  'Cybersecurity',
];

function renderTags(){
  EP_TAGS_EL.innerHTML = '';
  epTags.forEach((tag, i) => {
    const span = document.createElement('span');
    span.className = 'ep-tag';
    span.innerHTML = `${tag}
      <button class="ep-tag-remove" onclick="removeTag(${i})" title="Remove">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>`;
    EP_TAGS_EL.appendChild(span);
  });
}

function addTag(){
  const val = EP_INPUT.value.trim();
  if(!val) return;
  if(epTags.map(t=>t.toLowerCase()).includes(val.toLowerCase())){ EP_INPUT.select(); return; }
  epTags.push(val);
  EP_INPUT.value = '';
  renderTags();
  EP_INPUT.focus();
}

function removeTag(i){ epTags.splice(i,1); renderTags(); }
function handleTagKey(e){ if(e.key==='Enter'){ e.preventDefault(); addTag(); } }

function openExpertProfile(){
  EP_OVERLAY.classList.add('open');
  EP_TRIGGER.classList.add('ep-open');
  renderTags();
}

function closeExpertProfile(){
  EP_OVERLAY.classList.remove('open');
  EP_TRIGGER.classList.remove('ep-open');
}

// Close when clicking outside the modal panel (on main content)
function handleEpOverlay(e){
  if(e.target === EP_OVERLAY) closeExpertProfile();
}

function saveExpertProfile(){
  closeExpertProfile();
  // hook up to your actual save API here
}

document.addEventListener('keydown', e => {
  if(e.key === 'Escape') closeExpertProfile();
});


/* Mobile sidebar toggle */
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');

function toggleSidebar() {
  if (!sidebarEl) return;
  var isOpen = sidebarEl.classList.contains('mobile-open');
  if (isOpen) { closeSidebar(); } else { openSidebar(); }
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
