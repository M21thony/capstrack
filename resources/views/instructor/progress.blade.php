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
<div class="main">
  <div class="topbar">
    <div class="page-title">Progress Monitoring</div>
    <div class="topbar-actions"><div class="notif-btn"><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><div class="notif-dot"></div></div></div>
  </div>
  <div class="content">
    <div class="grid-4" style="margin-bottom:16px">
      <div class="stat-chip"><div class="stat-n" style="color:var(--green)">6</div><div class="stat-l">Teams Monitored</div></div>
      <div class="stat-chip"><div class="stat-n" style="color:var(--accent3)">68%</div><div class="stat-l">Avg. Completion</div></div>
      <div class="stat-chip"><div class="stat-n" style="color:var(--red)">2</div><div class="stat-l">Overdue Milestones</div></div>
      <div class="stat-chip"><div class="stat-n" style="color:var(--blue)">4</div><div class="stat-l">On Track</div></div>
    </div>

    <div class="row" style="margin-bottom:14px;gap:8px">
      <input class="input" placeholder="Search teams..." style="max-width:260px"/>
      <select class="input" style="width:160px"><option>All Sections</option><option>BSCS-4A</option><option>BSIT-4A</option><option>BSIT-4B</option></select>
      <select class="input" style="width:160px"><option>Sort: Progress â†“</option><option>Sort: Team Name</option><option>Sort: Overdue First</option></select>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div class="team-progress-card">
        <div class="tp-header">
          <div class="tp-avatar" style="background:linear-gradient(135deg,#c9a82c,#8a6e10)">TN</div>
          <div style="flex:1">
            <div class="tp-team">Team Nexus <span class="badge badge-gold" style="font-size:10px;margin-left:4px;vertical-align:middle">BSCS-4A</span></div>
            <div class="tp-title">AI-Powered Attendance System Using Facial Recognition</div>
          </div>
          <div style="text-align:right">
            <div style="font-family:'Space Mono',monospace;font-size:16px;font-weight:700;color:var(--accent3)">67%</div>
            <div style="font-size:10px;color:var(--text3)">overall</div>
          </div>
        </div>
        <div class="tp-milestones">
          <div class="tp-m"><span class="tp-m-label">Title Defense</span><div class="tp-m-bar"><div class="tp-m-fill pf-green" style="width:100%"></div></div><span class="tp-m-pct" style="color:var(--green)">100</span></div>
          <div class="tp-m"><span class="tp-m-label">Chapter 1</span><div class="tp-m-bar"><div class="tp-m-fill pf-green" style="width:100%"></div></div><span class="tp-m-pct" style="color:var(--green)">100</span></div>
          <div class="tp-m"><span class="tp-m-label">Chapter 2</span><div class="tp-m-bar"><div class="tp-m-fill pf-gold" style="width:80%"></div></div><span class="tp-m-pct">80</span></div>
          <div class="tp-m"><span class="tp-m-label">Chapter 3</span><div class="tp-m-bar"><div class="tp-m-fill pf-gold" style="width:45%"></div></div><span class="tp-m-pct">45</span></div>
          <div class="tp-m"><span class="tp-m-label">Chapter 4</span><div class="tp-m-bar"><div class="tp-m-fill pf-blue" style="width:0%"></div></div><span class="tp-m-pct">0</span></div>
        </div>
        <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);display:flex;gap:6px">
          <span class="badge badge-amber" style="font-size:10px">1 Overdue</span>
          <button class="btn btn-ghost" style="font-size:10px;padding:3px 10px;margin-left:auto">View Full Report</button>
        </div>
      </div>

      <div class="team-progress-card">
        <div class="tp-header">
          <div class="tp-avatar" style="background:linear-gradient(135deg,#16a34a,#0891b2)">TA</div>
          <div style="flex:1">
            <div class="tp-team">Team Alpha <span class="badge badge-cyan" style="font-size:10px;margin-left:4px;vertical-align:middle">BSIT-4A</span></div>
            <div class="tp-title">Plant Disease Detection Using Deep Learning Mobile App</div>
          </div>
          <div style="text-align:right">
            <div style="font-family:'Space Mono',monospace;font-size:16px;font-weight:700;color:var(--green)">85%</div>
            <div style="font-size:10px;color:var(--text3)">overall</div>
          </div>
        </div>
        <div class="tp-milestones">
          <div class="tp-m"><span class="tp-m-label">Title Defense</span><div class="tp-m-bar"><div class="tp-m-fill pf-green" style="width:100%"></div></div><span class="tp-m-pct" style="color:var(--green)">100</span></div>
          <div class="tp-m"><span class="tp-m-label">Chapter 1</span><div class="tp-m-bar"><div class="tp-m-fill pf-green" style="width:100%"></div></div><span class="tp-m-pct" style="color:var(--green)">100</span></div>
          <div class="tp-m"><span class="tp-m-label">Chapter 2</span><div class="tp-m-bar"><div class="tp-m-fill pf-green" style="width:100%"></div></div><span class="tp-m-pct" style="color:var(--green)">100</span></div>
          <div class="tp-m"><span class="tp-m-label">Chapter 3</span><div class="tp-m-bar"><div class="tp-m-fill pf-gold" style="width:75%"></div></div><span class="tp-m-pct">75</span></div>
          <div class="tp-m"><span class="tp-m-label">Chapter 4</span><div class="tp-m-bar"><div class="tp-m-fill pf-blue" style="width:30%"></div></div><span class="tp-m-pct" style="color:var(--blue)">30</span></div>
        </div>
        <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);display:flex;gap:6px">
          <span class="badge badge-green" style="font-size:10px">On Track</span>
          <button class="btn btn-ghost" style="font-size:10px;padding:3px 10px;margin-left:auto">View Full Report</button>
        </div>
      </div>

      <div class="team-progress-card">
        <div class="tp-header">
          <div class="tp-avatar" style="background:linear-gradient(135deg,#1d4ed8,#7c3aed)">TP</div>
          <div style="flex:1">
            <div class="tp-team">Team Prism <span class="badge badge-purple" style="font-size:10px;margin-left:4px;vertical-align:middle">BSIT-4B</span></div>
            <div class="tp-title">Blockchain-Based Document Verification for Academic Records</div>
          </div>
          <div style="text-align:right">
            <div style="font-family:'Space Mono',monospace;font-size:16px;font-weight:700;color:var(--red)">42%</div>
            <div style="font-size:10px;color:var(--text3)">overall</div>
          </div>
        </div>
        <div class="tp-milestones">
          <div class="tp-m"><span class="tp-m-label">Title Defense</span><div class="tp-m-bar"><div class="tp-m-fill pf-green" style="width:100%"></div></div><span class="tp-m-pct" style="color:var(--green)">100</span></div>
          <div class="tp-m"><span class="tp-m-label">Chapter 1</span><div class="tp-m-bar"><div class="tp-m-fill pf-green" style="width:100%"></div></div><span class="tp-m-pct" style="color:var(--green)">100</span></div>
          <div class="tp-m"><span class="tp-m-label">Chapter 2</span><div class="tp-m-bar"><div class="tp-m-fill pf-gold" style="width:60%"></div></div><span class="tp-m-pct">60</span></div>
          <div class="tp-m"><span class="tp-m-label">Chapter 3</span><div class="tp-m-bar"><div class="tp-m-fill pf-blue" style="width:10%"></div></div><span class="tp-m-pct" style="color:var(--red)">10</span></div>
          <div class="tp-m"><span class="tp-m-label">Chapter 4</span><div class="tp-m-bar"><div class="tp-m-fill pf-blue" style="width:0%"></div></div><span class="tp-m-pct">0</span></div>
        </div>
        <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);display:flex;gap:6px">
          <span class="badge badge-red" style="font-size:10px">2 Overdue</span>
          <span class="badge badge-amber" style="font-size:10px">Needs Attention</span>
          <button class="btn btn-ghost" style="font-size:10px;padding:3px 10px;margin-left:auto">View Full Report</button>
        </div>
      </div>

      <div class="team-progress-card">
        <div class="tp-header">
          <div class="tp-avatar" style="background:linear-gradient(135deg,#b45309,#dc2626)">TS</div>
          <div style="flex:1">
            <div class="tp-team">Team Syntax <span class="badge badge-amber" style="font-size:10px;margin-left:4px;vertical-align:middle">BSIT-4A</span></div>
            <div class="tp-title">Smart Campus Energy Monitoring Using IoT Sensors</div>
          </div>
          <div style="text-align:right">
            <div style="font-family:'Space Mono',monospace;font-size:16px;font-weight:700;color:var(--accent3)">55%</div>
            <div style="font-size:10px;color:var(--text3)">overall</div>
          </div>
        </div>
        <div class="tp-milestones">
          <div class="tp-m"><span class="tp-m-label">Title Defense</span><div class="tp-m-bar"><div class="tp-m-fill pf-green" style="width:100%"></div></div><span class="tp-m-pct" style="color:var(--green)">100</span></div>
          <div class="tp-m"><span class="tp-m-label">Chapter 1</span><div class="tp-m-bar"><div class="tp-m-fill pf-green" style="width:100%"></div></div><span class="tp-m-pct" style="color:var(--green)">100</span></div>
          <div class="tp-m"><span class="tp-m-label">Chapter 2</span><div class="tp-m-bar"><div class="tp-m-fill pf-gold" style="width:90%"></div></div><span class="tp-m-pct">90</span></div>
          <div class="tp-m"><span class="tp-m-label">Chapter 3</span><div class="tp-m-bar"><div class="tp-m-fill pf-gold" style="width:40%"></div></div><span class="tp-m-pct">40</span></div>
          <div class="tp-m"><span class="tp-m-label">Chapter 4</span><div class="tp-m-bar"><div class="tp-m-fill pf-blue" style="width:0%"></div></div><span class="tp-m-pct">0</span></div>
        </div>
        <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);display:flex;gap:6px">
          <span class="badge badge-amber" style="font-size:10px">1 Overdue</span>
          <button class="btn btn-ghost" style="font-size:10px;padding:3px 10px;margin-left:auto">View Full Report</button>
        </div>
      </div>
    </div>
  </div>
</div>

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
        <div class="ep-header-sub">Capstone Expert Â· Active</div>
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
          Max 4 teams Â· Currently advising 3
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
</script>
</body>
</html>
