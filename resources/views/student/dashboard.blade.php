<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Dashboard - CapsTRACK</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/Styles/dashboard.css')
</head>
<body>
@include('student.partials.sidebar')

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="page-title">Dashboard</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'dashboard'])
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
      <tr><td class="td-main">Chapter 3 Draft Submission</td><td>May 15, 2025</td><td><span class="badge badge-amber">In Progress</span></td><td>Maria A.</td></tr>
      <tr><td class="td-main">Financial Receipt Upload</td><td>May 18, 2025</td><td><span class="badge badge-red">Overdue</span></td><td>All Members</td></tr>
      <tr><td class="td-main">Expert Consultation</td><td>May 22, 2025</td><td><span class="badge badge-blue">Scheduled</span></td><td>Team Nexus</td></tr>
      <tr><td class="td-main">Final Oral Defense</td><td>Jun 10, 2025</td><td><span class="badge badge-cyan">Upcoming</span></td><td>Team Nexus</td></tr>
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
        <div class="card-header"><span class="card-title">Title & Abstract Duplication History</span></div>
          <div class="history-list">
              <div class="history-item">
                  <div style="flex: 1;">
                      <div class="scan-type">Target: Capstone Title</div>
                      <div style="font-size: 12px; font-weight: 600; margin-top: 2px;">"CapsTrack: Centralized Web-based..."</div>
                  </div>
                  <div class="scan-badge scan-low">0% Match</div>
              </div>
              <div class="history-item">
                  <div style="flex: 1;">
                      <div class="scan-type">Target: Project Abstract</div>
                      <div style="font-size: 12px; font-weight: 600; margin-top: 2px;">Nexus_Abstract_Draft_v2.pdf</div>
                  </div>
                  <div class="scan-badge scan-low">8% Match</div>
              </div>
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
  <div class="modal-overlay" id="receiptModal" onclick="this.classList.remove('active')">
      <img src="" alt="Full Receipt" class="modal-img" id="fullReceiptImg">
  </div>
</div>

<script>
function openReceipt(src) {
    const modal = document.getElementById('receiptModal');
    const modalImg = document.getElementById('fullReceiptImg');
    modalImg.src = src;
    modal.classList.add('active');
}
</script>
</body>
</html>
