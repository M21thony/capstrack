<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Activity Log - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
</head>
<body>

@include('admin._sidebar')
<div class="main">
  <div class="topbar">
    <div class="page-title">Activity Log</div>
    <div class="topbar-actions">
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
      <button class="btn btn-ghost-nav">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><path d="M12 15V3"/></svg>
        Export CSV
      </button>
    </div>
  </div>

  <div class="content">
    <div class="row" style="margin-bottom:16px;gap:8px">
      <input class="input" placeholder="Search by team, student, or action..." style="max-width:300px"/>
      <select class="input" style="width:180px"><option>All Actions</option><option>submitted_document</option><option>booked_consultation</option><option>applied_expert</option><option>submitted_receipt</option><option>ran_duplication_check</option><option>updated_milestone</option></select>
      <select class="input" style="width:160px"><option>All Teams</option><option>Team Nexus</option><option>Team Alpha</option><option>Team Prism</option><option>Team Syntax</option></select>
      <select class="input" style="width:140px"><option>All Dates</option><option>Today</option><option>This Week</option><option>This Month</option></select>
      <span style="flex:1"></span>
    </div>
    <div class="card" style="padding:0;overflow:hidden">
      <table class="table">
        <thead>
          <tr>
            <th style="padding-left:20px">Time</th>
            <th>Student</th>
            <th>Team</th>
            <th>Action</th>
            <th>Reference</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="padding-left:20px;font-family:'Space Mono',monospace;font-size:11px;color:var(--text3)">Apr 5 · 09:42</td>
            <td class="td-main">Maria Andres</td>
            <td><span class="badge badge-gold" style="font-size:10px">Team Nexus</span></td>
            <td><span class="badge badge-green" style="font-size:10px">submitted_document</span></td>
            <td style="font-size:12px;color:var(--text2)">Chapter 2 Draft · v1.0</td>
          </tr>
          <tr>
            <td style="padding-left:20px;font-family:'Space Mono',monospace;font-size:11px;color:var(--text3)">Apr 5 · 08:15</td>
            <td class="td-main">Juan Reyes</td>
            <td><span class="badge badge-gold" style="font-size:10px">Team Nexus</span></td>
            <td><span class="badge badge-blue" style="font-size:10px">booked_consultation</span></td>
            <td style="font-size:12px;color:var(--text2)">Dr. Santos · May 12, 2PM</td>
          </tr>
          <tr>
            <td style="padding-left:20px;font-family:'Space Mono',monospace;font-size:11px;color:var(--text3)">Apr 4 · 16:30</td>
            <td class="td-main">Rico Peña</td>
            <td><span class="badge badge-purple" style="font-size:10px">Team Prism</span></td>
            <td><span class="badge badge-cyan" style="font-size:10px">applied_expert</span></td>
            <td style="font-size:12px;color:var(--text2)">Applied for Dr. Lim as Consultant</td>
          </tr>
          <tr>
            <td style="padding-left:20px;font-family:'Space Mono',monospace;font-size:11px;color:var(--text3)">Apr 4 · 14:10</td>
            <td class="td-main">Lea Cruz</td>
            <td><span class="badge badge-cyan" style="font-size:10px">Team Alpha</span></td>
            <td><span class="badge badge-purple" style="font-size:10px">ran_duplication_check</span></td>
            <td style="font-size:12px;color:var(--text2)">Pre-Proposal v2 · Result: 12%</td>
          </tr>
          <tr>
            <td style="padding-left:20px;font-family:'Space Mono',monospace;font-size:11px;color:var(--text3)">Apr 4 · 11:00</td>
            <td class="td-main">Maria Andres</td>
            <td><span class="badge badge-gold" style="font-size:10px">Team Nexus</span></td>
            <td><span class="badge badge-amber" style="font-size:10px">submitted_receipt</span></td>
            <td style="font-size:12px;color:var(--text2)">Printing Fee · PHP 850.00</td>
          </tr>
          <tr>
            <td style="padding-left:20px;font-family:'Space Mono',monospace;font-size:11px;color:var(--text3)">Apr 4 · 09:55</td>
            <td class="td-main">Karl Torres</td>
            <td><span class="badge badge-amber" style="font-size:10px">Team Syntax</span></td>
            <td><span class="badge badge-green" style="font-size:10px">updated_milestone</span></td>
            <td style="font-size:12px;color:var(--text2)">Chapter 2 → In Progress</td>
          </tr>
          <tr>
            <td style="padding-left:20px;font-family:'Space Mono',monospace;font-size:11px;color:var(--text3)">Apr 3 · 15:20</td>
            <td class="td-main">Anna Flores</td>
            <td><span class="badge badge-purple" style="font-size:10px">Team Prism</span></td>
            <td><span class="badge badge-green" style="font-size:10px">submitted_document</span></td>
            <td style="font-size:12px;color:var(--text2)">Pre-Proposal Form · v1.0</td>
          </tr>
          <tr>
            <td style="padding-left:20px;font-family:'Space Mono',monospace;font-size:11px;color:var(--text3)">Apr 3 · 10:40</td>
            <td class="td-main">Lea Cruz</td>
            <td><span class="badge badge-cyan" style="font-size:10px">Team Alpha</span></td>
            <td><span class="badge badge-blue" style="font-size:10px">booked_consultation</span></td>
            <td style="font-size:12px;color:var(--text2)">Prof. Medina · May 14, 10AM</td>
          </tr>
          <tr>
            <td style="padding-left:20px;font-family:'Space Mono',monospace;font-size:11px;color:var(--text3)">Apr 3 · 08:30</td>
            <td class="td-main">Rico Peña</td>
            <td><span class="badge badge-purple" style="font-size:10px">Team Prism</span></td>
            <td><span class="badge badge-amber" style="font-size:10px">submitted_receipt</span></td>
            <td style="font-size:12px;color:var(--text2)">Transportation Fee · PHP 240.00</td>
          </tr>
          <tr>
            <td style="padding-left:20px;font-family:'Space Mono',monospace;font-size:11px;color:var(--text3)">Apr 2 · 17:05</td>
            <td class="td-main">Juan Reyes</td>
            <td><span class="badge badge-gold" style="font-size:10px">Team Nexus</span></td>
            <td><span class="badge badge-cyan" style="font-size:10px">applied_expert</span></td>
            <td style="font-size:12px;color:var(--text2)">Applied for Prof. Ramos as Consultant</td>
          </tr>
        </tbody>
      </table>
      <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
        <span style="font-size:12px;color:var(--text3)">Showing 10 of 284 records</span>
        <div style="display:flex;gap:6px">
          <button class="btn btn-ghost" style="font-size:11px;padding:5px 12px">← Prev</button>
          <button class="btn btn-ghost" style="font-size:11px;padding:5px 12px">Next →</button>
        </div>
      </div>
    </div>
  </div>
</div>

</body>
</html>
