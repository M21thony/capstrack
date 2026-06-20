<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CapsTRACK — Expert Portal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/expert.css') }}" />
</head>
<body>
@include('instructor.partials.sidebar', ['currentPage' => 'teams'])

<div class="main">
  <div class="topbar">
    <div class="page-title">Team Overview</div>
    <div class="topbar-actions">
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="grid-2" style="margin-bottom:16px">
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--blue)">12</div>
        <div class="stat-l">Total Teams</div>
        <div class="stat-sub">Across all roles</div>
      </div>
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--green)">6</div>
        <div class="stat-l">Teams Advised</div>
        <div class="stat-sub">This academic year</div>
      </div>
    </div>

    <div class="role-tabs" style="margin-bottom:16px">
      <button class="role-tab active" onclick="showRole('adviser')">Adviser</button>
      <button class="role-tab" onclick="showRole('panel')">Panel</button>
      <button class="role-tab" onclick="showRole('consultant')">Consultant</button>
    </div>

    <div id="adviser-teams" class="role-panel active">
      <div class="card" style="margin-bottom:12px">
        <div class="team-card-header">
          <div class="team-card-avatar" style="background:var(--blue)">N</div>
          <div class="team-card-info">
            <div class="team-card-name">Team Nexus <span class="role-pill role-adviser">Adviser</span></div>
            <div class="team-card-title">Intelligent Traffic Management System using Computer Vision</div>
          </div>
        </div>
        <div class="team-stats">
          <div class="team-stat">
            <div class="team-stat-n">4</div>
            <div class="team-stat-l">Members</div>
          </div>
          <div class="team-stat">
            <div class="team-stat-n">78%</div>
            <div class="team-stat-l">Progress</div>
          </div>
          <div class="team-stat">
            <div class="team-stat-n">3</div>
            <div class="team-stat-l">Docs Reviewed</div>
          </div>
        </div>
        <div class="member-list">
          <div class="member-row">
            <div class="member-avatar">S1</div>
            <div class="member-name">Sarah Chen</div>
            <div class="member-role-tag">Team Lead</div>
          </div>
          <div class="member-row">
            <div class="member-avatar">A2</div>
            <div class="member-name">Alex Reyes</div>
            <div class="member-role-tag">Developer</div>
          </div>
        </div>
        <div class="team-card-footer">
          <button class="btn btn-ghost" style="font-size:11px;padding:4px 10px">View Details</button>
        </div>
      </div>
    </div>

    <div id="panel-teams" class="role-panel">
      <div class="empty-state">
        <svg viewBox="0 0 24 24" style="stroke:var(--border2)"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 0 0 1 0 7.75"/></svg>
        <p>No teams assigned as panel member.</p>
      </div>
    </div>

    <div id="consultant-teams" class="role-panel">
      <div class="empty-state">
        <svg viewBox="0 0 24 24" style="stroke:var(--border2)"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 0 0 1 0 7.75"/></svg>
        <p>No teams assigned as consultant.</p>
      </div>
    </div>
  </div>
</div>

@include('instructor.partials.expert-profile')

<script>
function showRole(role) {
  document.querySelectorAll('.role-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
  document.getElementById(role + '-teams').classList.add('active');
  event.target.classList.add('active');
}
</script>
</body>
</html>