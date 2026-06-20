<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CapsTRACK Expert Portal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/expert.css') }}" />
</head>
<body>
@include('instructor.partials.sidebar')
<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• MAIN â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<div class="main">
  <div class="topbar">
    <div class="page-title">Dashboard</div>
    <div class="topbar-actions">
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="grid-4" style="margin-bottom:16px">
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--accent3)">4</div>
        <div class="stat-l">Pending Applications</div>
        <div class="stat-sub">2 new this week</div>
      </div>
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--red)">5</div>
        <div class="stat-l">Docs for Review</div>
        <div class="stat-sub">Oldest: 3 days ago</div>
      </div>
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--blue)">2</div>
        <div class="stat-l">Upcoming Consults</div>
        <div class="stat-sub">Next: May 12 at 2 PM</div>
      </div>
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--green)">6</div>
        <div class="stat-l">Teams Advised</div>
        <div class="stat-sub">AY 2024â€“2025</div>
      </div>
    </div>
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">Announcements</span><span class="badge badge-accent">New</span></div>
      <div>
        <div class="activity-item" style="border-bottom:1px solid var(--border)">
          <div class="act-icon" style="background:var(--accent-glow)"><svg viewBox="0 0 24 24" style="stroke:var(--accent)"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="m18.5 2.5 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></div>
          <div class="act-text"><strong>Capstone Defense Schedule Updated</strong><br>The final defense schedule has been revised. Check your email for the new dates.<div class="act-time">April 3, 2026</div></div>
        </div>
        <div class="activity-item" style="border-bottom:1px solid var(--border)">
          <div class="act-icon" style="background:var(--green-bg)"><svg viewBox="0 0 24 24" style="stroke:var(--green)"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
          <div class="act-text"><strong>You have been assigned as team nexus panel chair</strong><br>You are now the panel chair for Team Nexus.<div class="act-time">March 28, 2026</div></div>
        </div>
        <div class="activity-item">
          <div class="act-icon" style="background:var(--amber-bg)"><svg viewBox="0 0 24 24" style="stroke:var(--amber)"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
          <div class="act-text"><strong>Important: Document Submission Deadline</strong><br>All final documents must be submitted by May 20th. Late submissions will not be accepted.<div class="act-time">March 15, 2026</div></div>
        </div>
      </div>
    </div>
    <div class="grid-2" style="margin-bottom:16px">
      <div class="card">
        <div class="card-header"><span class="card-title">Pending Applications</span><a href="{{ route('instructor.applications') }}" class="btn btn-ghost" style="font-size:11px;padding:4px 10px">View All</a></div>
        <table class="table">
          <thead><tr><th>Team</th><th>Role</th><th>Date</th><th>Action</th></tr></thead>
          <tbody>
            <tr>
              <td class="td-main">Team Nexus</td>
              <td>Adviser</td>
              <td>May 3</td>
              <td><div style="display:flex;gap:4px">
                <button class="btn btn-success" style="font-size:10px;padding:3px 8px">Accept</button>
                <button class="btn btn-danger" style="font-size:10px;padding:3px 8px">Reject</button>
              </div></td>
            </tr>
            <tr>
              <td class="td-main">Team Prism</td>
              <td>Panel</td>
              <td>May 4</td>
              <td><div style="display:flex;gap:4px">
                <button class="btn btn-success" style="font-size:10px;padding:3px 8px">Accept</button>
                <button class="btn btn-danger" style="font-size:10px;padding:3px 8px">Reject</button>
              </div></td>
            </tr>
            <tr>
              <td class="td-main">Team Syntax</td>
              <td>Consultant</td>
              <td>May 5</td>
              <td><div style="display:flex;gap:4px">
                <button class="btn btn-success" style="font-size:10px;padding:3px 8px">Accept</button>
                <button class="btn btn-danger" style="font-size:10px;padding:3px 8px">Reject</button>
              </div></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="card">
        <div class="card-header"><span class="card-title">Upcoming Consultations</span><a href="{{ route('instructor.consultations') }}" class="btn btn-ghost" style="font-size:11px;padding:4px 10px">View All</a></div>
        <div class="gap-12">
          <div class="consult-card">
            <div class="consult-header">
              <div>
                <div class="consult-team">Team Nexus</div>
                <div class="consult-meta">May 12, 2025 Â· 2:00 PM Â· 1 hour</div>
              </div>
              <span class="badge badge-blue">Upcoming</span>
            </div>
            <div class="consult-topic">Chapter 3 Methodology Review â€” feedback on research design and sampling approach</div>
            <div style="display:flex;gap:6px">
              <button class="btn btn-ghost" style="font-size:10px;padding:4px 10px">Reschedule</button>
              <button class="btn btn-primary" style="font-size:10px;padding:4px 10px">Join</button>
            </div>
          </div>
          <div class="consult-card">
            <div class="consult-header">
              <div>
                <div class="consult-team">Team Alpha</div>
                <div class="consult-meta">May 16, 2025 Â· 10:00 AM Â· 45 min</div>
              </div>
              <span class="badge badge-gold">Confirmed</span>
            </div>
            <div class="consult-topic">Web Architecture Discussion â€” cloud deployment options for the system</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">Recent Activity</span></div>
      <div>
        <div class="act-item">
          <div class="act-dot" style="background:var(--green-bg)"><svg viewBox="0 0 24 24" style="stroke:var(--green)"><polyline points="20 6 9 17 4 12"/></svg></div>
          <div class="act-body"><strong>Team Nexus</strong> submitted Chapter 2 Draft for review<div class="act-time">2 hours ago</div></div>
        </div>
        <div class="act-item">
          <div class="act-dot" style="background:rgba(201,168,44,0.1)"><svg viewBox="0 0 24 24" style="stroke:var(--accent3)"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg></div>
          <div class="act-body"><strong>Team Prism</strong> sent an application request â€” Panel role<div class="act-time">Yesterday, 4:10 PM</div></div>
        </div>
        <div class="act-item">
          <div class="act-dot" style="background:var(--blue-bg)"><svg viewBox="0 0 24 24" style="stroke:var(--blue)"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg></div>
          <div class="act-body"><strong>Team Alpha</strong> uploaded a receipt for verification<div class="act-time">2 days ago</div></div>
        </div>
        <div class="act-item">
          <div class="act-dot" style="background:var(--red-bg)"><svg viewBox="0 0 24 24" style="stroke:var(--red)"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
          <div class="act-body"><strong>Team Syntax</strong> has an overdue milestone â€” Chapter 3<div class="act-time">3 days ago</div></div>
        </div>
      </div>
    </div>
</div>
</div>

@include('instructor.partials.expert-profile')

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
