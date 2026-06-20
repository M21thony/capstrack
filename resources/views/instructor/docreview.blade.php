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
@include('instructor.partials.sidebar', ['currentPage' => 'docreview'])
<div class="main">
  <div class="topbar">
    <div class="page-title">Document Review</div>
    <div class="topbar-actions"><div class="notif-btn"><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><div class="notif-dot"></div></div></div>
  </div>
  <div class="content">
    <div class="row" style="margin-bottom:14px;gap:8px">
      <input class="input" placeholder="Search documents or teams..." style="max-width:280px"/>
      <select class="input" style="width:160px"><option>All Teams</option><option>Team Nexus</option><option>Team Alpha</option><option>Team Prism</option></select>
      <select class="input" style="width:150px"><option>All Status</option><option>Pending</option><option>In Progress</option><option>Approved</option></select>
      <span style="flex:1"></span>
      <span style="font-size:12px;color:var(--text3)">5 documents Â· 3 pending</span>
    </div>
    <div class="card" style="padding:0;overflow:hidden">
      <table class="table">
        <thead>
          <tr>
            <th style="padding-left:20px">Document</th>
            <th>Team</th>
            <th>Submitted</th>
            <th>Pages</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="padding-left:20px">
              <div style="display:flex;align-items:center;gap:10px">
                <div class="doc-icon" style="background:rgba(201,168,44,0.1);border:1px solid rgba(201,168,44,0.25)">
                  <svg viewBox="0 0 24 24" style="stroke:var(--accent3)"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <div><div class="td-main">Chapter 2 Draft</div><div style="font-size:11px;color:var(--text3)">Review of Related Literature Â· v1.0</div></div>
              </div>
            </td>
            <td><span class="badge badge-gold">Team Nexus</span></td>
            <td style="font-size:12px">May 3, 2025</td>
            <td style="font-family:'Space Mono',monospace;font-size:12px">24</td>
            <td><span class="badge badge-red">Pending</span></td>
            <td>
              <button class="btn btn-primary" style="font-size:11px;padding:5px 14px">
                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Open Annotator
              </button>
            </td>
          </tr>
          <tr>
            <td style="padding-left:20px">
              <div style="display:flex;align-items:center;gap:10px">
                <div class="doc-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                <div><div class="td-main">Chapter 3 Draft</div><div style="font-size:11px;color:var(--text3)">Methodology Â· v1.0</div></div>
              </div>
            </td>
            <td><span class="badge badge-cyan">Team Alpha</span></td>
            <td style="font-size:12px">May 4, 2025</td>
            <td style="font-family:'Space Mono',monospace;font-size:12px">18</td>
            <td><span class="badge badge-red">Pending</span></td>
            <td>
              <button class="btn btn-primary" style="font-size:11px;padding:5px 14px">
                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Open Annotator
              </button>
            </td>
          </tr>
          <tr>
            <td style="padding-left:20px">
              <div style="display:flex;align-items:center;gap:10px">
                <div class="doc-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                <div><div class="td-main">Pre-Proposal</div><div style="font-size:11px;color:var(--text3)">Pre-proposal Form Â· v2.0</div></div>
              </div>
            </td>
            <td><span class="badge badge-purple">Team Prism</span></td>
            <td style="font-size:12px">May 2, 2025</td>
            <td style="font-family:'Space Mono',monospace;font-size:12px">12</td>
            <td><span class="badge badge-amber">In Progress</span></td>
            <td>
              <button class="btn btn-primary" style="font-size:11px;padding:5px 14px">
                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Continue Reviewing
              </button>
            </td>
          </tr>
          <tr style="opacity:0.65">
            <td style="padding-left:20px">
              <div style="display:flex;align-items:center;gap:10px">
                <div class="doc-icon" style="background:rgba(22,163,74,0.08);border:1px solid rgba(22,163,74,0.2)">
                  <svg viewBox="0 0 24 24" style="stroke:var(--green)"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <div><div class="td-main">Chapter 1 Final</div><div style="font-size:11px;color:var(--text3)">Introduction Â· v3.0</div></div>
              </div>
            </td>
            <td><span class="badge badge-gold">Team Nexus</span></td>
            <td style="font-size:12px">Apr 18, 2025</td>
            <td style="font-family:'Space Mono',monospace;font-size:12px">20</td>
            <td><span class="badge badge-green">Approved</span></td>
            <td>
              <button class="btn btn-ghost" style="font-size:11px;padding:5px 14px">
                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                View Annotations
              </button>
            </td>
          </tr>
          <tr style="opacity:0.65">
            <td style="padding-left:20px">
              <div style="display:flex;align-items:center;gap:10px">
                <div class="doc-icon" style="background:rgba(22,163,74,0.08);border:1px solid rgba(22,163,74,0.2)">
                  <svg viewBox="0 0 24 24" style="stroke:var(--green)"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <div><div class="td-main">Title Defense Form</div><div style="font-size:11px;color:var(--text3)">Defense Documents Â· v1.0</div></div>
              </div>
            </td>
            <td><span class="badge badge-cyan">Team Alpha</span></td>
            <td style="font-size:12px">Apr 5, 2025</td>
            <td style="font-family:'Space Mono',monospace;font-size:12px">8</td>
            <td><span class="badge badge-green">Approved</span></td>
            <td>
              <button class="btn btn-ghost" style="font-size:11px;padding:5px 14px">
                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                View Annotations
              </button>
            </td>
          </tr>
        </tbody>
      </table>
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
