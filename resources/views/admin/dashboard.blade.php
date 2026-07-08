<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Dashboard - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
</head>
<body>

@include('admin._sidebar')

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
        <div class="stat-n" style="color:var(--blue)">{{ $totalStudents }}</div>
        <div class="stat-l">Total Students</div>
        <div class="stat-sub">Enrolled users</div>
      </div>
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--purple)">{{ $totalExperts }}</div>
        <div class="stat-l">Total Experts</div>
        <div class="stat-sub">Approved faculty accounts</div>
      </div>
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--green)">{{ $activeTeams }}</div>
        <div class="stat-l">Active Teams</div>
        <div class="stat-sub">{{ $currentSchoolYearLabel }}</div>
      </div>
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--accent3)">{{ $completedTeams }}</div>
        <div class="stat-l">Completed Teams</div>
        <div class="stat-sub">{{ $currentSchoolYearLabel }}</div>
      </div>
    </div>

    <div class="grid-2" style="margin-bottom:16px">
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--red)">{{ $pendingApprovals }}</div>
        <div class="stat-l">Pending Approvals</div>
        <div class="stat-sub">Expert accounts awaiting review</div>
      </div>
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--blue)">312</div>
        <div class="stat-l">Archived Capstones</div>
        <div class="stat-sub">2018–2025</div>
      </div>
    </div>

    <div class="grid-2" style="margin-bottom:16px">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Capstones Submitted per Year</span>
          <span class="badge badge-gold">2018–2025</span>
        </div>
        <div style="display:flex;align-items:flex-end;gap:6px;height:140px;padding-bottom:4px;position:relative">
          <div style="display:flex;flex-direction:column;align-items:center;flex:1"><span style="font-size:9px;color:var(--text3);font-family:'Space Mono',monospace;margin-bottom:4px">28</span><div style="width:100%;max-width:30px;background:var(--bg4);border-radius:4px 4px 0 0;height:42px"></div><div style="font-size:9px;color:var(--text3);margin-top:4px">18</div></div>
          <div style="display:flex;flex-direction:column;align-items:center;flex:1"><span style="font-size:9px;color:var(--text3);font-family:'Space Mono',monospace;margin-bottom:4px">34</span><div style="width:100%;max-width:30px;background:var(--bg4);border-radius:4px 4px 0 0;height:51px"></div><div style="font-size:9px;color:var(--text3);margin-top:4px">19</div></div>
          <div style="display:flex;flex-direction:column;align-items:center;flex:1"><span style="font-size:9px;color:var(--text3);font-family:'Space Mono',monospace;margin-bottom:4px">22</span><div style="width:100%;max-width:30px;background:var(--bg4);border-radius:4px 4px 0 0;height:33px"></div><div style="font-size:9px;color:var(--text3);margin-top:4px">20</div></div>
          <div style="display:flex;flex-direction:column;align-items:center;flex:1"><span style="font-size:9px;color:var(--text3);font-family:'Space Mono',monospace;margin-bottom:4px">41</span><div style="width:100%;max-width:30px;background:var(--bg4);border-radius:4px 4px 0 0;height:61px"></div><div style="font-size:9px;color:var(--text3);margin-top:4px">21</div></div>
          <div style="display:flex;flex-direction:column;align-items:center;flex:1"><span style="font-size:9px;color:var(--text3);font-family:'Space Mono',monospace;margin-bottom:4px">45</span><div style="width:100%;max-width:30px;background:var(--bg4);border-radius:4px 4px 0 0;height:67px"></div><div style="font-size:9px;color:var(--text3);margin-top:4px">22</div></div>
          <div style="display:flex;flex-direction:column;align-items:center;flex:1"><span style="font-size:9px;color:var(--text3);font-family:'Space Mono',monospace;margin-bottom:4px">52</span><div style="width:100%;max-width:30px;background:var(--bg4);border-radius:4px 4px 0 0;height:78px"></div><div style="font-size:9px;color:var(--text3);margin-top:4px">23</div></div>
          <div style="display:flex;flex-direction:column;align-items:center;flex:1"><span style="font-size:9px;color:var(--text3);font-family:'Space Mono',monospace;margin-bottom:4px">58</span><div style="width:100%;max-width:30px;background:var(--bg4);border-radius:4px 4px 0 0;height:87px"></div><div style="font-size:9px;color:var(--text3);margin-top:4px">24</div></div>
          <div style="display:flex;flex-direction:column;align-items:center;flex:1"><span style="font-size:9px;color:var(--accent3);font-family:'Space Mono',monospace;font-weight:700;margin-bottom:4px">48</span><div style="width:100%;max-width:30px;background:var(--accent);border-radius:4px 4px 0 0;height:72px"></div><div style="font-size:9px;color:var(--accent3);font-weight:700;margin-top:4px">25</div></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <span class="card-title">Capstone Categories</span>
          <span class="badge badge-gray">All Years</span>
        </div>
        <div class="donut-wrap" style="margin-top:8px">
          <svg class="donut" viewBox="0 0 36 36">
            <circle cx="18" cy="18" r="15.9" fill="none" stroke="#f0f2f5" stroke-width="3.8"/>
            <circle cx="18" cy="18" r="15.9" fill="none" stroke="#1d4ed8" stroke-width="3.8" stroke-dasharray="30 70" stroke-dashoffset="25" transform="rotate(-90 18 18)"/>
            <circle cx="18" cy="18" r="15.9" fill="none" stroke="#7c3aed" stroke-width="3.8" stroke-dasharray="22 78" stroke-dashoffset="-5" transform="rotate(-90 18 18)"/>
            <circle cx="18" cy="18" r="15.9" fill="none" stroke="#16a34a" stroke-width="3.8" stroke-dasharray="18 82" stroke-dashoffset="-27" transform="rotate(-90 18 18)"/>
            <circle cx="18" cy="18" r="15.9" fill="none" stroke="#b45309" stroke-width="3.8" stroke-dasharray="15 85" stroke-dashoffset="-45" transform="rotate(-90 18 18)"/>
            <circle cx="18" cy="18" r="15.9" fill="none" stroke="#c9960c" stroke-width="3.8" stroke-dasharray="15 85" stroke-dashoffset="-60" transform="rotate(-90 18 18)"/>
            <text x="18" y="20" text-anchor="middle" font-size="5" fill="#1e2128" font-family="Space Mono" font-weight="700">312</text>
          </svg>
          <div class="donut-legend">
            <div class="donut-item"><div class="donut-dot" style="background:#1d4ed8"></div><span style="flex:1">Web-Based</span><span style="font-family:'Space Mono',monospace;font-size:11px;color:var(--text2)">94 (30%)</span></div>
            <div class="donut-item"><div class="donut-dot" style="background:#7c3aed"></div><span style="flex:1">Mobile</span><span style="font-family:'Space Mono',monospace;font-size:11px;color:var(--text2)">69 (22%)</span></div>
            <div class="donut-item"><div class="donut-dot" style="background:#16a34a"></div><span style="flex:1">IoT</span><span style="font-family:'Space Mono',monospace;font-size:11px;color:var(--text2)">56 (18%)</span></div>
            <div class="donut-item"><div class="donut-dot" style="background:#b45309"></div><span style="flex:1">AI / ML</span><span style="font-family:'Space Mono',monospace;font-size:11px;color:var(--text2)">47 (15%)</span></div>
            <div class="donut-item"><div class="donut-dot" style="background:#c9960c"></div><span style="flex:1">Others</span><span style="font-family:'Space Mono',monospace;font-size:11px;color:var(--text2)">46 (15%)</span></div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid-2">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Pending Expert Approvals</span>
          <a href="{{ route('admin.approval') }}" class="btn btn-ghost" style="font-size:11px;padding:4px 10px">View All</a>
        </div>
        <div class="gap-12">
          <div class="expert-row">
            <div class="expert-av" style="background:linear-gradient(135deg,#1d4ed8,#7c3aed)">JD</div>
            <div style="flex:1">
              <div style="font-size:13px;font-weight:600;color:var(--text)">Dr. Jose Dela Cruz</div>
              <div style="font-size:11px;color:var(--text3)">Machine Learning · Faculty</div>
            </div>
            <div style="display:flex;gap:6px">
              <button class="btn btn-success" style="font-size:10px;padding:4px 10px">Approve</button>
              <button class="btn btn-danger" style="font-size:10px;padding:4px 10px">Reject</button>
            </div>
          </div>
          <div class="expert-row">
            <div class="expert-av" style="background:linear-gradient(135deg,#16a34a,#0891b2)">MR</div>
            <div style="flex:1">
              <div style="font-size:13px;font-weight:600;color:var(--text)">Prof. Maria Ramos</div>
              <div style="font-size:11px;color:var(--text3)">Web Development · Faculty</div>
            </div>
            <div style="display:flex;gap:6px">
              <button class="btn btn-success" style="font-size:10px;padding:4px 10px">Approve</button>
              <button class="btn btn-danger" style="font-size:10px;padding:4px 10px">Reject</button>
            </div>
          </div>
          <div class="expert-row">
            <div class="expert-av" style="background:linear-gradient(135deg,#b45309,#dc2626)">KL</div>
            <div style="flex:1">
              <div style="font-size:13px;font-weight:600;color:var(--text)">Dr. Karl Lim</div>
              <div style="font-size:11px;color:var(--text3)">Cybersecurity · Faculty</div>
            </div>
            <div style="display:flex;gap:6px">
              <button class="btn btn-success" style="font-size:10px;padding:4px 10px">Approve</button>
              <button class="btn btn-danger" style="font-size:10px;padding:4px 10px">Reject</button>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <span class="card-title">Recent Activity</span>
          <a href="{{ route('admin.activitylog') }}" class="btn btn-ghost" style="font-size:11px;padding:4px 10px">View All</a>
        </div>
        <div>
          <div class="act-item">
            <div class="act-dot" style="background:var(--green-bg)"><svg viewBox="0 0 24 24" style="stroke:var(--green)"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div class="act-body"><strong>Team Nexus</strong> submitted Chapter 2 for review<div class="act-time">Today, 9:42 AM</div></div>
          </div>
          <div class="act-item">
            <div class="act-dot" style="background:rgba(201,150,12,0.1)"><svg viewBox="0 0 24 24" style="stroke:var(--accent3)"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
            <div class="act-body"><strong>Team Alpha</strong> booked consultation with Dr. Santos<div class="act-time">Today, 8:15 AM</div></div>
          </div>
          <div class="act-item">
            <div class="act-dot" style="background:var(--blue-bg)"><svg viewBox="0 0 24 24" style="stroke:var(--blue)"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg></div>
            <div class="act-body"><strong>Team Prism</strong> applied for Dr. Lim as Consultant<div class="act-time">Yesterday, 4:30 PM</div></div>
          </div>
          <div class="act-item">
            <div class="act-dot" style="background:var(--purple-bg)"><svg viewBox="0 0 24 24" style="stroke:var(--purple)"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
            <div class="act-body"><strong>Team Syntax</strong> ran duplication check on pre-proposal<div class="act-time">Yesterday, 2:10 PM</div></div>
          </div>
          <div class="act-item">
            <div class="act-dot" style="background:var(--amber-bg)"><svg viewBox="0 0 24 24" style="stroke:var(--amber)"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <div class="act-body"><strong>Maria Andres</strong> uploaded receipt for Printing Fee<div class="act-time">Yesterday, 11:00 AM</div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

</body>
</html>
