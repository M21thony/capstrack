<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Resources - CapsTRACK</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/styles.css')
</head>
<body>

<div class="toast" id="toast"></div>

<!-- SIDEBAR -->
<div class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-badge">
      <div class="brand-icon">
        <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
      </div>
      <div>
        <div class="brand-name">CapsTRACK</div>
        <div class="brand-sub">Student Portal</div>
      </div>
    </div>
  </div>
  <div class="sidebar-user" id="profile-trigger" onclick="openProfile()">
    <div class="avatar">MA</div>
    <div class="user-info">
      <div class="user-name">Mark Anthony B.</div>
      <div class="user-role">Team Nexus · BSCS-4A</div>
    </div>
    <div class="status-dot"></div>
    <div class="sidebar-user-caret">
      <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
  </div>
  <nav class="nav">
    <div class="nav-section">Main</div>
    <a href="/student/dashboard" class="nav-item">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <a href="/student/team" class="nav-item">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Team Management
    </a>
    <a href="/student/experts" class="nav-item">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
      Capstone Expert
      <span class="nav-badge warn">2</span>
    </a>
    <div class="nav-section">Documents</div>
    <a href="/student/submission" class="nav-item">
      <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      Document Submission
    </a>
    <a href="/student/review" class="nav-item">
      <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
      Review Viewer
      <span class="nav-badge">3</span>
    </a>
    <a href="/student/duplication" class="nav-item">
      <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      Duplication Checker
    </a>
    <div class="nav-section">Schedule</div>
    <a href="/student/consultation" class="nav-item">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Consultation
    </a>
    <div class="nav-section">Tracking</div>
    <a href="/student/progress" class="nav-item">
      <svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
      Progress Tracker
    </a>
    <a href="/student/financial" class="nav-item">
      <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
      Financial Records
    </a>
    <div class="nav-section">Reference</div>
    <a href="/student/resources" class="nav-item active">
      <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      Resources
      <span class="nav-badge">2</span>
    </a>
    <a href="/student/archive" class="nav-item">
      <svg viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
      Archived Capstones
    </a>
    <div class="nav-section">Account</div>
    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="inline">
        @csrf
        <button type="submit" class="nav-item" style="background:none;border:none;color:#fefefe;cursor:pointer;display:flex;align-items:center;width:100%">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Sign Out
        </button>
    </form>
  </nav>
</div>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="page-title">Resources</div>
    <div class="topbar-actions">
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </div>

  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'resources'])

    <!-- STAT ROW -->
    <div class="grid-4" style="margin-bottom:18px">
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--accent3)" id="stat-total">7</div>
        <div class="stat-l">Total Files</div>
      </div>
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--blue)" id="stat-new">2</div>
        <div class="stat-l">New This Week</div>
      </div>
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--green)" id="stat-dl">3</div>
        <div class="stat-l">Downloaded</div>
      </div>
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--purple)" id="stat-cats">5</div>
        <div class="stat-l">Categories</div>
      </div>
    </div>

    <!-- TOOLBAR -->
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;flex-wrap:wrap">
      <div class="ftabs" id="ftabs">
        <button class="ftab active" onclick="setFilter('all',this)">All</button>
        <button class="ftab" onclick="setFilter('pre-proposal',this)">Pre-Proposal</button>
        <button class="ftab" onclick="setFilter('ch1-3',this)">Ch. 1–3</button>
        <button class="ftab" onclick="setFilter('ch4-5',this)">Ch. 4–5</button>
        <button class="ftab" onclick="setFilter('final',this)">Final MS</button>
        <button class="ftab" onclick="setFilter('presentation',this)">Presentation</button>
        <button class="ftab" onclick="setFilter('lesson',this)">Lessons</button>
        <button class="ftab" onclick="setFilter('forms',this)">Forms</button>
      </div>
      <div style="display:flex;gap:8px;align-items:center">
        <div style="position:relative">
          <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:13px;height:13px;stroke:var(--text3);fill:none;stroke-width:2;pointer-events:none" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input class="input" id="q" placeholder="Search resources…" style="padding-left:32px;width:210px;font-size:12px" oninput="render()"/>
        </div>
        <select class="input" id="srt" style="width:145px;font-size:12px" onchange="render()">
          <option value="newest">Newest First</option>
          <option value="oldest">Oldest First</option>
          <option value="az">Name A–Z</option>
          <option value="popular">Most Downloaded</option>
        </select>
      </div>
    </div>

    <!-- LIST -->
    <div id="res-list" class="gap-12"></div>

  </div>
</div>

</body>
</html>
