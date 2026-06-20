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
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'dashboard'])
<div class="grid-4" style="margin-bottom:16px">
  <div class="stat-chip"><div class="stat-n" style="color:var(--accent)">67%</div><div class="stat-l">Overall Progress</div></div>
  <div class="stat-chip"><div class="stat-n" style="color:var(--green)">8</div><div class="stat-l">Docs Submitted</div></div>
  <div class="stat-chip"><div class="stat-n" style="color:var(--amber)">2</div><div class="stat-l">Pending Reviews</div></div>
  <div class="stat-chip"><div class="stat-n" style="color:var(--purple)">4</div><div class="stat-l">Team Members</div></div>
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
      <div class="activity-item">
        <div class="act-icon" style="background:var(--green-bg)"><svg viewBox="0 0 24 24" style="stroke:var(--green)"><polyline points="20 6 9 17 4 12"/></svg></div>
        <div class="act-text"><strong>Chapter 2 Draft</strong> submitted for review<div class="act-time">2 hours ago</div></div>
      </div>
      <div class="activity-item">
        <div class="act-icon" style="background:var(--accent-glow)"><svg viewBox="0 0 24 24" style="stroke:var(--accent)"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
        <div class="act-text"><strong>Dr. Santos</strong> left 3 annotations<div class="act-time">Yesterday, 3:42 PM</div></div>
      </div>
      <div class="activity-item">
        <div class="act-icon" style="background:var(--amber-bg)"><svg viewBox="0 0 24 24" style="stroke:var(--amber)"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <div class="act-text"><strong>Consultation</strong> booked for May 12, 2pm<div class="act-time">2 days ago</div></div>
      </div>
      <div class="activity-item">
        <div class="act-icon" style="background:var(--purple-bg)"><svg viewBox="0 0 24 24" style="stroke:var(--purple)"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
        <div class="act-text"><strong>Juan Reyes</strong> joined the team<div class="act-time">3 days ago</div></div>
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
          <div class="card-header"><span class="card-title">Team Genesis</span></div>
          <div class="history-list" style="margin-top: 10px;">
              <div class="history-item">
                  <div class="member-dot"></div>
                  <div>
                      <div style="font-size: 12px; font-weight: 600;">Mark Anthony (Lead)</div>
                      <div style="font-size: 10px; color: var(--text3);">Created Team Nexus · Sept 12</div>
                  </div>
              </div>
              <div class="history-item">
                  <div class="member-dot"></div>
                  <div>
                      <div style="font-size: 12px; font-weight: 600;">Jolina, Alexa, Kenneth, Ian</div>
                      <div style="font-size: 10px; color: var(--text3);">Team Completion · Sept 15</div>
                  </div>
              </div>
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
function quickLogin() {
  const studentId = document.getElementById('quickStudentId').value;
  const password = document.getElementById('quickPassword').value;
  
  if (!studentId || !password) {
    alert('Please enter both Student ID and Password');
    return;
  }
  
  // Here you would typically make an API call to authenticate
  // For now, we'll just show a success message
  alert('Signed in as ' + studentId);
  
  // Clear the form
  document.getElementById('quickStudentId').value = '';
  document.getElementById('quickPassword').value = '';
}

function googleLogin() {
  // Redirect to login page
  window.location.href = '../login.html';
}

// Allow Enter key to submit
document.addEventListener('DOMContentLoaded', function() {
  const passwordInput = document.getElementById('quickPassword');
  if (passwordInput) {
    passwordInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        quickLogin();
      }
    });
  }
});


  function openReceipt(src) {
    const modal = document.getElementById('receiptModal');
    const modalImg = document.getElementById('fullReceiptImg');
    modalImg.src = src;
    modal.classList.add('active');
}
</script>
</body>
</html>
