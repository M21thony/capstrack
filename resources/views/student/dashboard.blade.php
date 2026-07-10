<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Dashboard - CapsTRACK</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/Styles/dashboard.css')
<style>
  @keyframes shimmer { 0% { background-position: -400px 0; } 100% { background-position: calc(400px + 100%) 0; } }
  .skeleton-box { background: linear-gradient(90deg, var(--bg2) 25%, var(--bg) 50%, var(--bg2) 75%); background-size: 400px 100%; animation: shimmer 1.6s ease-in-out infinite; border-radius: 6px; }
  #pageSkeleton { position: absolute; inset: 0; z-index: 50; padding: 0; opacity: 1; transition: opacity 0.35s ease; pointer-events: auto; }
  #pageSkeleton.loaded { opacity: 0; pointer-events: none; }
</style>
</head>
<body>
@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Dashboard</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'dashboard'])
    <div id="contentWrapper" style="position: relative;">
    <div id="pageSkeleton">
      <div class="grid-4" style="margin-bottom:16px">
        <div class="stat-chip"><div class="skeleton-box" style="width:50px;height:28px;border-radius:6px;margin-bottom:6px;"></div><div class="skeleton-box" style="width:80px;height:10px;border-radius:4px;"></div></div>
        <div class="stat-chip"><div class="skeleton-box" style="width:40px;height:28px;border-radius:6px;margin-bottom:6px;"></div><div class="skeleton-box" style="width:90px;height:10px;border-radius:4px;"></div></div>
        <div class="stat-chip"><div class="skeleton-box" style="width:45px;height:28px;border-radius:6px;margin-bottom:6px;"></div><div class="skeleton-box" style="width:85px;height:10px;border-radius:4px;"></div></div>
        <div class="stat-chip"><div class="skeleton-box" style="width:30px;height:28px;border-radius:6px;margin-bottom:6px;"></div><div class="skeleton-box" style="width:70px;height:10px;border-radius:4px;"></div></div>
      </div>
      <div class="card" style="margin-bottom:16px">
        <div class="card-header"><span class="skeleton-box" style="width:110px;height:12px;border-radius:4px;"></span><span class="skeleton-box" style="width:40px;height:18px;border-radius:12px;"></span></div>
        <div>
          <div class="activity-item" style="border-bottom:1px solid var(--border)"><div class="skeleton-box" style="width:30px;height:30px;border-radius:9px;flex-shrink:0;"></div><div class="act-text"><div class="skeleton-box" style="width:70%;height:13px;border-radius:4px;margin-bottom:6px;"></div><div class="skeleton-box" style="width:40%;height:11px;border-radius:4px;"></div></div></div>
          <div class="activity-item" style="border-bottom:1px solid var(--border)"><div class="skeleton-box" style="width:30px;height:30px;border-radius:9px;flex-shrink:0;"></div><div class="act-text"><div class="skeleton-box" style="width:60%;height:13px;border-radius:4px;margin-bottom:6px;"></div><div class="skeleton-box" style="width:35%;height:11px;border-radius:4px;"></div></div></div>
          <div class="activity-item"><div class="skeleton-box" style="width:30px;height:30px;border-radius:9px;flex-shrink:0;"></div><div class="act-text"><div class="skeleton-box" style="width:65%;height:13px;border-radius:4px;margin-bottom:6px;"></div><div class="skeleton-box" style="width:50%;height:11px;border-radius:4px;"></div></div></div>
        </div>
      </div>
      <div class="grid-2" style="margin-bottom:16px">
        <div class="card">
          <div class="card-header"><span class="skeleton-box" style="width:140px;height:12px;border-radius:4px;"></span><span class="skeleton-box" style="width:70px;height:18px;border-radius:12px;"></span></div>
          <div class="gap-12">
            <div><div class="skeleton-box" style="width:85%;height:11px;border-radius:4px;margin-bottom:6px;"></div><div class="skeleton-box" style="width:100%;height:6px;border-radius:3px;"></div></div>
            <div><div class="skeleton-box" style="width:75%;height:11px;border-radius:4px;margin-bottom:6px;"></div><div class="skeleton-box" style="width:80%;height:6px;border-radius:3px;"></div></div>
            <div><div class="skeleton-box" style="width:60%;height:11px;border-radius:4px;margin-bottom:6px;"></div><div class="skeleton-box" style="width:45%;height:6px;border-radius:3px;"></div></div>
            <div><div class="skeleton-box" style="width:70%;height:11px;border-radius:4px;margin-bottom:6px;"></div><div class="skeleton-box" style="width:10%;height:6px;border-radius:3px;"></div></div>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><span class="skeleton-box" style="width:120px;height:12px;border-radius:4px;"></span></div>
          <div>
            <div class="activity-item"><div class="skeleton-box" style="width:30px;height:30px;border-radius:9px;flex-shrink:0;"></div><div class="act-text"><div class="skeleton-box" style="width:55%;height:13px;border-radius:4px;margin-bottom:6px;"></div><div class="skeleton-box" style="width:35%;height:11px;border-radius:4px;"></div></div></div>
            <div class="activity-item"><div class="skeleton-box" style="width:30px;height:30px;border-radius:9px;flex-shrink:0;"></div><div class="act-text"><div class="skeleton-box" style="width:65%;height:13px;border-radius:4px;margin-bottom:6px;"></div><div class="skeleton-box" style="width:40%;height:11px;border-radius:4px;"></div></div></div>
            <div class="activity-item"><div class="skeleton-box" style="width:30px;height:30px;border-radius:9px;flex-shrink:0;"></div><div class="act-text"><div class="skeleton-box" style="width:45%;height:13px;border-radius:4px;margin-bottom:6px;"></div><div class="skeleton-box" style="width:30%;height:11px;border-radius:4px;"></div></div></div>
          </div>
        </div>
      </div>
      <div class="card" style="margin-bottom:16px">
        <div class="card-header"><span class="skeleton-box" style="width:140px;height:12px;border-radius:4px;"></span><span class="skeleton-box" style="width:70px;height:26px;border-radius:6px;"></span></div>
        <table class="table">
          <thead><tr><th><div class="skeleton-box" style="width:60px;height:10px;border-radius:3px;"></div></th><th><div class="skeleton-box" style="width:50px;height:10px;border-radius:3px;"></div></th><th><div class="skeleton-box" style="width:40px;height:10px;border-radius:3px;"></div></th><th><div class="skeleton-box" style="width:70px;height:10px;border-radius:3px;"></div></th></tr></thead>
          <tbody>
            <tr><td><div class="skeleton-box" style="width:55%;height:13px;border-radius:4px;"></div></td><td><div class="skeleton-box" style="width:70px;height:13px;border-radius:4px;"></div></td><td><div class="skeleton-box" style="width:60px;height:18px;border-radius:12px;"></div></td><td><div class="skeleton-box" style="width:50px;height:13px;border-radius:4px;"></div></td></tr>
            <tr><td><div class="skeleton-box" style="width:45%;height:13px;border-radius:4px;"></div></td><td><div class="skeleton-box" style="width:70px;height:13px;border-radius:4px;"></div></td><td><div class="skeleton-box" style="width:50px;height:18px;border-radius:12px;"></div></td><td><div class="skeleton-box" style="width:70px;height:13px;border-radius:4px;"></div></td></tr>
            <tr><td><div class="skeleton-box" style="width:50%;height:13px;border-radius:4px;"></div></td><td><div class="skeleton-box" style="width:70px;height:13px;border-radius:4px;"></div></td><td><div class="skeleton-box" style="width:65px;height:18px;border-radius:12px;"></div></td><td><div class="skeleton-box" style="width:65px;height:13px;border-radius:4px;"></div></td></tr>
            <tr><td><div class="skeleton-box" style="width:40%;height:13px;border-radius:4px;"></div></td><td><div class="skeleton-box" style="width:70px;height:13px;border-radius:4px;"></div></td><td><div class="skeleton-box" style="width:60px;height:18px;border-radius:12px;"></div></td><td><div class="skeleton-box" style="width:65px;height:13px;border-radius:4px;"></div></td></tr>
          </tbody>
        </table>
      </div>
      <div class="dash-grid-v2">
        <div class="card">
          <div class="card-header"><span class="skeleton-box" style="width:180px;height:12px;border-radius:4px;"></span></div>
          <div class="history-list">
            <div class="history-item"><div style="flex:1"><div class="skeleton-box" style="width:55%;height:13px;border-radius:4px;margin-bottom:4px;"></div><div class="skeleton-box" style="width:40%;height:10px;border-radius:4px;"></div></div><div class="skeleton-box" style="width:40px;height:40px;border-radius:6px;flex-shrink:0;"></div></div>
            <div class="history-item"><div style="flex:1"><div class="skeleton-box" style="width:45%;height:13px;border-radius:4px;margin-bottom:4px;"></div><div class="skeleton-box" style="width:35%;height:10px;border-radius:4px;"></div></div><div class="skeleton-box" style="width:40px;height:40px;border-radius:6px;flex-shrink:0;"></div></div>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><span class="skeleton-box" style="width:120px;height:12px;border-radius:4px;"></span></div>
          <div class="history-list" style="margin-top:10px">
            <div class="history-item"><div class="skeleton-box" style="width:8px;height:8px;border-radius:50%;flex-shrink:0;"></div><div style="flex:1;min-width:0;"><div class="skeleton-box" style="width:40%;height:11px;border-radius:4px;margin-bottom:4px;"></div><div class="skeleton-box" style="width:55%;height:12px;border-radius:4px;"></div></div></div>
            <div class="history-item"><div class="skeleton-box" style="width:8px;height:8px;border-radius:50%;flex-shrink:0;"></div><div style="flex:1;min-width:0;"><div class="skeleton-box" style="width:35%;height:11px;border-radius:4px;margin-bottom:4px;"></div><div class="skeleton-box" style="width:50%;height:12px;border-radius:4px;"></div></div></div>
            <div class="history-item"><div class="skeleton-box" style="width:8px;height:8px;border-radius:50%;flex-shrink:0;"></div><div style="flex:1;min-width:0;"><div class="skeleton-box" style="width:45%;height:11px;border-radius:4px;margin-bottom:4px;"></div><div class="skeleton-box" style="width:40%;height:12px;border-radius:4px;"></div></div></div>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><span class="skeleton-box" style="width:80px;height:12px;border-radius:4px;"></span></div>
          <div class="history-list" style="margin-top:10px">
            <div class="history-item"><div class="skeleton-box" style="width:8px;height:8px;border-radius:50%;flex-shrink:0;"></div><div><div class="skeleton-box" style="width:50%;height:12px;border-radius:4px;margin-bottom:4px;"></div><div class="skeleton-box" style="width:65%;height:10px;border-radius:4px;"></div></div></div>
            <div class="history-item"><div class="skeleton-box" style="width:8px;height:8px;border-radius:50%;flex-shrink:0;"></div><div><div class="skeleton-box" style="width:40%;height:12px;border-radius:4px;margin-bottom:4px;"></div><div class="skeleton-box" style="width:55%;height:10px;border-radius:4px;"></div></div></div>
            <div class="history-item"><div class="skeleton-box" style="width:8px;height:8px;border-radius:50%;flex-shrink:0;"></div><div><div class="skeleton-box" style="width:45%;height:12px;border-radius:4px;margin-bottom:4px;"></div><div class="skeleton-box" style="width:50%;height:10px;border-radius:4px;"></div></div></div>
          </div>
        </div>
      </div>
      <div class="legal-credits">
        <div class="skeleton-box" style="width:100px;height:8px;border-radius:4px;margin:0 auto 6px;"></div>
        <div class="skeleton-box" style="width:200px;height:9px;border-radius:4px;margin:0 auto;"></div>
      </div>
    </div>
<div class="grid-4" style="margin-bottom:16px">
  <div class="stat-chip"><div class="stat-n" style="color:var(--accent)">{{ $overallProgress }}%</div><div class="stat-l">Overall Progress</div></div>
  <div class="stat-chip"><div class="stat-n" style="color:var(--green)">{{ $docsSubmitted }}</div><div class="stat-l">Docs Submitted</div></div>
  <div class="stat-chip"><div class="stat-n" style="color:var(--amber)">{{ $pendingReviews }}</div><div class="stat-l">Pending Reviews</div></div>
  <div class="stat-chip"><div class="stat-n" style="color:var(--purple)">{{ $teamGroupsCount }}</div><div class="stat-l">Team Groups</div></div>
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
      <div class="act-text"><strong>Workshop: Advanced Research Methods</strong><br>Join us for a free workshop on May 5th at 2 PM in Room 301.<div class="act-time">March 28, 2026</div></div>
    </div>
    <div class="activity-item">
      <div class="act-icon" style="background:var(--amber-bg)"><svg viewBox="0 0 24 24" style="stroke:var(--amber)"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
      <div class="act-text"><strong>Important: Document Submission Deadline</strong><br>All final documents must be submitted by May 20th. Late submissions will not be accepted.<div class="act-time">March 15, 2026</div></div>
    </div>
  </div>
</div>
<div class="grid-2" style="margin-bottom:16px">
  <div class="card">
    <div class="card-header"><span class="card-title">Milestone Progress</span><span class="badge badge-blue">In Progress</span></div>
    <div class="gap-12">
      <div>
        <div class="row" style="margin-bottom:6px;justify-content:space-between"><span style="font-size:12px;color:var(--text2)">Chapter 1 — Introduction</span><span style="font-size:11px;color:var(--green)">100%</span></div>
        <div class="progress-bar"><div class="progress-fill progress-green" style="width:100%"></div></div>
      </div>
      <div>
        <div class="row" style="margin-bottom:6px;justify-content:space-between"><span style="font-size:12px;color:var(--text2)">Chapter 2 — Review of Literature</span><span style="font-size:11px;color:#8a6500">80%</span></div>
        <div class="progress-bar"><div class="progress-fill progress-blue" style="width:80%"></div></div>
      </div>
      <div>
        <div class="row" style="margin-bottom:6px;justify-content:space-between"><span style="font-size:12px;color:var(--text2)">Chapter 3 — Methodology</span><span style="font-size:11px;color:#8a6500">45%</span></div>
        <div class="progress-bar"><div class="progress-fill progress-blue" style="width:45%"></div></div>
      </div>
      <div>
        <div class="row" style="margin-bottom:6px;justify-content:space-between"><span style="font-size:12px;color:var(--text2)">Chapter 4 — Results</span><span style="font-size:11px;color:var(--text3)">0%</span></div>
        <div class="progress-bar"><div class="progress-fill progress-blue" style="width:0%"></div></div>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><span class="card-title">Recent Activity</span></div>
    <div>
      <div class="act-scroll">
      @forelse($activities as $activity)
        <div class="activity-item">
          <div class="act-icon" style="background:{!! $activity->icon_bg !!}">{!! $activity->icon_svg !!}</div>
          <div class="act-text">{!! $activity->text !!}<div class="act-time">{{ $activity->time }}</div></div>
        </div>
      @empty
        <div style="text-align: center; padding: 24px; color: var(--text3); font-size: 13px;">
          No recent activity.
        </div>
      @endforelse
      </div>
    </div>
  </div>
</div>
<div class="card">
  <div class="card-header"><span class="card-title">Upcoming Deadlines</span><button class="btn btn-ghost" style="font-size:11px;padding:4px 10px">View All</button></div>
  <table class="table">
    <thead><tr><th>Deliverable</th><th>Due Date</th><th>Status</th><th>Assigned To</th></tr></thead>
    <tbody>
      <tr><td class="td-main" data-label="Deliverable">Chapter 3 Draft Submission</td><td data-label="Due Date">May 15, 2025</td><td data-label="Status"><span class="badge badge-amber">In Progress</span></td><td data-label="Assigned To">Maria A.</td></tr>
      <tr><td class="td-main" data-label="Deliverable">Financial Receipt Upload</td><td data-label="Due Date">May 18, 2025</td><td data-label="Status"><span class="badge badge-red">Overdue</span></td><td data-label="Assigned To">All Members</td></tr>
      <tr><td class="td-main" data-label="Deliverable">Expert Consultation</td><td data-label="Due Date">May 22, 2025</td><td data-label="Status"><span class="badge badge-blue">Scheduled</span></td><td data-label="Assigned To">Team Nexus</td></tr>
      <tr><td class="td-main" data-label="Deliverable">Final Oral Defense</td><td data-label="Due Date">Jun 10, 2025</td><td data-label="Status"><span class="badge badge-cyan">Upcoming</span></td><td data-label="Assigned To">Team Nexus</td></tr>
    </tbody>
  </table>
</div>
  <div class="dash-grid-v2">
      <div class="card">
        <div class="card-header"><span class="card-title">Recent Expenses & Receipts</span></div>
          <div class="history-list">
              <div class="history-item">
                  <div style="flex: 1;">
                      <div style="font-size: 13px; font-weight: 600;">Hard Binding Payment</div>
                      <div class="activity-meta">
                          <span style="font-size: 11px; color: var(--text3);">₱850.00 · Oct 28</span>
                      </div>
                  </div>
                  <img src="https://www.smartsheet.com/sites/default/files/2022-02/IC-Basic-Receipt-Template.png" 
                  alt="Receipt for Binding" 
                  class="receipt-thumb" 
                  title="Click to view full receipt"
                  onclick="openReceipt(this.src)">
              </div>
              <div class="history-item">
                  <div style="flex: 1;">
                      <div style="font-size: 13px; font-weight: 600;">System Hosting Fee</div>
                      <div class="activity-meta">
                          <span style="font-size: 11px; color: var(--text3);">₱1,200.00 · Oct 20</span>
                      </div>
                  </div>
                  <img src="https://officetemplatesonline.com/wp-content/uploads/2021/07/business-receipt-template-for-ms-word.jpg" 
                  alt="Receipt for Binding" 
                  class="receipt-thumb" 
                  title="Click to view full receipt"
                  onclick="openReceipt(this.src)">
              </div>
          </div>
      </div>

      <div class="card">
          <div class="card-header"><span class="card-title">Capstone Expert</span></div>
          <div class="history-list" style="margin-top: 10px;">
              @if($myTeam && $myTeam->expertAssignments->isNotEmpty())
                  @foreach($myTeam->expertAssignments as $assignment)
                      @php
                        $roleColors = [
                          'adviser' => ['dot' => 'var(--accent)', 'bg' => 'var(--accent-light)', 'fg' => 'var(--accent3)'],
                          'consultant' => ['dot' => '#3b82f6', 'bg' => 'var(--blue-bg)', 'fg' => 'var(--blue)'],
                          'grammarian' => ['dot' => '#9333ea', 'bg' => 'var(--purple-bg)', 'fg' => 'var(--purple)'],
                          'panel' => ['dot' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.1)', 'fg' => '#d97706'],
                        ];
                        $roleKey = strtolower($assignment->role?->role_name ?? '');
                        $roleStyle = $roleColors[$roleKey] ?? ['dot' => '#6b7280', 'bg' => 'rgba(107,114,128,0.1)', 'fg' => '#6b7280'];
                        $expertName = $assignment->expert?->name ?? 'Unassigned';
                        $isApproved = $assignment->status === 'approved';
                      @endphp
                      <div class="history-item">
                          <div style="width:8px;height:8px;border-radius:50%;background:{{ $roleStyle['dot'] }};flex-shrink:0;"></div>
                          <div style="flex:1;min-width:0;">
                              <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                  <span style="font-size:11px;font-weight:700;color:{{ $roleStyle['fg'] }};text-transform:uppercase;letter-spacing:0.3px;">{{ $assignment->role?->role_name ?? 'Unknown' }}</span>
                                  @if($isApproved)
                                    <span style="font-size:9px;padding:1px 6px;border-radius:4px;background:rgba(16,185,129,0.1);color:#059669;font-weight:700;">Approved</span>
                                  @else
                                    <span style="font-size:9px;padding:1px 6px;border-radius:4px;background:rgba(245,158,11,0.1);color:#d97706;font-weight:700;">{{ ucfirst($assignment->status) }}</span>
                                  @endif
                              </div>
                              <div style="font-size:12px;font-weight:600;margin-top:1px;">{{ $expertName }}</div>
                          </div>
                      </div>
                  @endforeach
              @elseif($myTeam)
                  <div class="history-item">
                      <div style="width:8px;height:8px;border-radius:50%;background:var(--border2);flex-shrink:0;"></div>
                      <div>
                          <div style="font-size:12px;font-weight:600;color:var(--text3);">No Experts Assigned</div>
                          <div style="font-size:10px;color:var(--text3);">Your team has no assigned capstone expert yet.</div>
                      </div>
                  </div>
              @else
                  <div class="history-item">
                      <div style="width:8px;height:8px;border-radius:50%;background:var(--border2);flex-shrink:0;"></div>
                      <div>
                          <div style="font-size:12px;font-weight:600;color:var(--text3);">No Team Joined</div>
                          <div style="font-size:10px;color:var(--text3);">Join a team to see your capstone expert.</div>
                      </div>
                  </div>
              @endif
          </div>
      </div>

      <div class="card">
          <div class="card-header"><span class="card-title">Team ({{ $myTeam ? $myTeam->team_name : 'No Team' }})</span></div>
          <div class="history-list" style="margin-top: 10px;">
              @forelse($teamMembers as $member)
                  @php $isLeaderMember = isset($leaderStudentId) && $member->student_id === $leaderStudentId; @endphp
                  <div class="history-item">
                      <div class="member-dot" style="background: {{ $isLeaderMember ? '#fbbf24' : ($member->student_id === auth()->id() ? 'var(--accent)' : 'var(--text3)') }}"></div>
                      <div>
                          <div style="font-size: 12px; font-weight: 600;">
                            {{ $member->f_name }} {{ $member->l_name }}
                            @if($isLeaderMember)
                                <span class="badge" style="background: #fef3c7; color: #92400e; font-size:10px; margin-left:4px;">Leader</span>
                            @endif
                            @if($member->student_id === auth()->id())
                                <span style="color: var(--accent); font-size: 10px; font-weight: normal;">(You)</span>
                            @endif
                        </div>
                          <div style="font-size: 10px; color: var(--text3);">{{ $member->student_number }} · {{ $member->course }}</div>
                      </div>
                  </div>
              @empty
                  <div class="history-item">
                      <div class="member-dot" style="background: var(--text3);"></div>
                      <div>
                          <div style="font-size: 12px; font-weight: 600; color: var(--text3);">No Team Joined</div>
                          <div style="font-size: 10px; color: var(--text3);">Create or join a team to see members here.</div>
                      </div>
                  </div>
              @endforelse
          </div>
      </div>
  <div class="legal-credits">
      <div class="credits-title">System Developers</div>
      <div class="credits-names">Jolina · John Kenneth · Mark Anthony · Ian Cyrus · Alexa Nicole</div>
  </div>
  </div>
    </div>
  <div class="modal-overlay" id="receiptModal" onclick="this.classList.remove('active')">
      <img src="" alt="Full Receipt" class="modal-img" id="fullReceiptImg">
  </div>
</div>

<script>
var skeleton = document.getElementById('pageSkeleton');
if (skeleton) {
    setTimeout(function() { skeleton.classList.add('loaded'); }, 450);
}
function openReceipt(src) {
    const modal = document.getElementById('receiptModal');
    const modalImg = document.getElementById('fullReceiptImg');
    modalImg.src = src;
    modal.classList.add('active');
}
function toggleSidebar() {
  document.body.classList.toggle('sidebar-open');
}
function closeSidebar() {
  document.body.classList.remove('sidebar-open');
}
</script>
</body>
</html>
