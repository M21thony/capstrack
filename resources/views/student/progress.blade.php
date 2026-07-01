<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Progress Tracker - CapsTRACK</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/styles.css')
</head>
<body>
@include('student.partials.sidebar')

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="page-title">Progress Tracker</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'progress'])
<div class="grid-4" style="margin-bottom:16px">
  <div class="stat-chip"><div class="stat-n" style="color:var(--green)">5</div><div class="stat-l">Completed</div></div>
  <div class="stat-chip"><div class="stat-n" style="color:var(--accent)">3</div><div class="stat-l">In Progress</div></div>
  <div class="stat-chip"><div class="stat-n" style="color:var(--text3)">4</div><div class="stat-l">To Do</div></div>
  <div class="stat-chip"><div class="stat-n" style="color:var(--red)">1</div><div class="stat-l">Overdue</div></div>
</div>
<div class="card">
  <div class="card-header"><span class="card-title">Milestone Board</span><button class="btn btn-ghost" style="font-size:11px;padding:4px 10px">+ Add Milestone</button></div>
  <div>
    <div class="milestone-row">
      <div class="milestone-dot m-done"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
      <div class="milestone-content">
        <div class="milestone-title">Pre-proposal Title Defense</div>
        <div class="milestone-meta">Completed Apr 5, 2025 · Maria A.</div>
      </div>
      <span class="badge badge-green">Done</span>
    </div>
    <div class="milestone-row">
      <div class="milestone-dot m-done"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
      <div class="milestone-content">
        <div class="milestone-title">Chapter 1 — Introduction</div>
        <div class="milestone-meta">Completed Apr 18, 2025 · All members</div>
      </div>
      <span class="badge badge-green">Done</span>
    </div>
    <div class="milestone-row">
      <div class="milestone-dot m-active"><svg viewBox="0 0 24 24" style="stroke-width:2"><circle cx="12" cy="12" r="4" fill="currentColor"/></svg></div>
      <div class="milestone-content">
        <div class="milestone-title">Chapter 2 — Review of Related Literature</div>
        <div class="milestone-meta">Due May 15, 2025 · Juan R. · <span style="color:var(--amber)">80% complete</span></div>
        <div class="progress-bar" style="margin-top:6px;width:200px"><div class="progress-fill progress-blue" style="width:80%"></div></div>
      </div>
      <span class="badge badge-blue">Active</span>
    </div>
    <div class="milestone-row">
      <div class="milestone-dot m-active"><svg viewBox="0 0 24 24" style="stroke-width:2"><circle cx="12" cy="12" r="4" fill="currentColor"/></svg></div>
      <div class="milestone-content">
        <div class="milestone-title">Chapter 3 — Methodology</div>
        <div class="milestone-meta">Due May 22, 2025 · Lea C. · <span style="color:#8a6500">45% complete</span></div>
        <div class="progress-bar" style="margin-top:6px;width:200px"><div class="progress-fill progress-blue" style="width:45%"></div></div>
      </div>
      <span class="badge badge-blue">Active</span>
    </div>
    <div class="milestone-row">
      <div class="milestone-dot m-todo"><svg viewBox="0 0 24 24" style="stroke-width:2"><circle cx="12" cy="12" r="4"/></svg></div>
      <div class="milestone-content">
        <div class="milestone-title">Chapter 4 — Results &amp; Discussion</div>
        <div class="milestone-meta">Due Jun 1, 2025 · Rico P.</div>
      </div>
      <span class="badge badge-cyan">Queued</span>
    </div>
    <div class="milestone-row">
      <div class="milestone-dot" style="background:var(--red-bg);color:var(--red)">
        <svg viewBox="0 0 24 24" width="10" height="10" style="stroke:var(--red);stroke-width:3;fill:none"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </div>
      <div class="milestone-content">
        <div class="milestone-title">Financial Records Submission</div>
        <div class="milestone-meta" style="color:var(--red)">Overdue since May 5, 2025 · All members</div>
      </div>
      <span class="badge badge-red">Overdue</span>
    </div>
    <div class="milestone-row">
      <div class="milestone-dot m-todo"><svg viewBox="0 0 24 24" style="stroke-width:2"><circle cx="12" cy="12" r="4"/></svg></div>
      <div class="milestone-content">
        <div class="milestone-title">Pre Oral Defense</div>
        <div class="milestone-meta">Due Jun 5, 2025 · All members</div>
      </div>
      <span class="badge badge-cyan">Queued</span>
    </div>
    <div class="milestone-row">
      <div class="milestone-dot m-todo"><svg viewBox="0 0 24 24" style="stroke-width:2"><circle cx="12" cy="12" r="4"/></svg></div>
      <div class="milestone-content">
        <div class="milestone-title">Final Oral Defense</div>
        <div class="milestone-meta">Due Jun 10, 2025 · All members</div>
      </div>
      <span class="badge badge-cyan">Queued</span>
    </div>
  </div>
</div>
</div>
</div>

</body>
</html>

<!-- SIDEBAR LOADER -->
