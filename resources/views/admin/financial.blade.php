<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Financial Records - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
</head>
<body>

@include('admin._sidebar')
<div class="main">
  <div class="topbar">
    <div class="page-title">Financial Records</div>
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

    <!-- ── ANALYTICS OVERVIEW ── -->
    <div class="fin-overview">

      <!-- LEFT: Team payment status ring -->
      <div class="fin-split-card">
        <div class="fin-split-label">Team Payment Status</div>
        <div class="fin-ring-wrap">
          <!-- SVG donut ring: 33 paid out of 48 = 68.75% → stroke-dasharray on r=15.9 circumference≈99.9 → 68.75 31.25 -->
          <div class="fin-ring">
            <svg viewBox="0 0 36 36">
              <!-- track -->
              <circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--bg4)" stroke-width="4"/>
              <!-- paid (green) -->
              <circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--green)"
                stroke-width="4"
                stroke-dasharray="68.75 31.25"
                stroke-dashoffset="25"
                transform="rotate(-90 18 18)"/>
              <!-- pending (amber) — starts after green -->
              <circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--amber)"
                stroke-width="4"
                stroke-dasharray="12.5 87.5"
                stroke-dashoffset="-43.75"
                transform="rotate(-90 18 18)"/>
              <!-- unpaid (red) — remaining -->
              <circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--red)"
                stroke-width="4"
                stroke-dasharray="18.75 81.25"
                stroke-dashoffset="-56.25"
                transform="rotate(-90 18 18)"/>
            </svg>
            <div class="fin-ring-center">
              <span class="fin-ring-n" style="color:var(--text)">48</span>
              <span class="fin-ring-l">Teams</span>
            </div>
          </div>

          <div class="fin-legend">
            <!-- Paid -->
            <div class="fin-legend-row">
              <div class="fin-legend-top">
                <span class="fin-legend-label">
                  <span class="fin-legend-dot" style="background:var(--green)"></span>
                  Fully Paid
                </span>
                <span class="fin-legend-count" style="color:var(--green)">33</span>
              </div>
              <div class="fin-pbar">
                <div class="fin-pbar-fill" style="width:68.75%;background:var(--green)"></div>
              </div>
              <div style="font-size:10px;color:var(--text3);margin-top:2px">68.75% of all teams · All receipts verified</div>
            </div>
            <!-- Partial / Pending -->
            <div class="fin-legend-row">
              <div class="fin-legend-top">
                <span class="fin-legend-label">
                  <span class="fin-legend-dot" style="background:var(--amber)"></span>
                  Has Pending
                </span>
                <span class="fin-legend-count" style="color:var(--amber)">6</span>
              </div>
              <div class="fin-pbar">
                <div class="fin-pbar-fill" style="width:12.5%;background:var(--amber)"></div>
              </div>
              <div style="font-size:10px;color:var(--text3);margin-top:2px">12.5% · Submitted, awaiting verification</div>
            </div>
            <!-- No Submission -->
            <div class="fin-legend-row">
              <div class="fin-legend-top">
                <span class="fin-legend-label">
                  <span class="fin-legend-dot" style="background:var(--red)"></span>
                  No Submission
                </span>
                <span class="fin-legend-count" style="color:var(--red)">9</span>
              </div>
              <div class="fin-pbar">
                <div class="fin-pbar-fill" style="width:18.75%;background:var(--red)"></div>
              </div>
              <div style="font-size:10px;color:var(--text3);margin-top:2px">18.75% · Have not submitted any receipt</div>
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT: Student payment status quad -->
      <div class="fin-split-card">
        <div class="fin-split-label">Student Payment Status</div>

        <!-- stacked progress bar across all 200 students -->
        <div style="margin-bottom:10px">
          <div style="display:flex;height:10px;border-radius:5px;overflow:hidden;gap:1px">
            <div style="width:66%;background:var(--green)" title="Paid: 132"></div>
            <div style="width:10%;background:var(--amber)" title="Pending: 20"></div>
            <div style="width:24%;background:var(--red)" title="Not Submitted: 48"></div>
          </div>
          <div style="display:flex;gap:14px;margin-top:6px">
            <span style="font-size:10px;color:var(--text3);display:flex;align-items:center;gap:4px">
              <span style="width:8px;height:8px;border-radius:50%;background:var(--green);display:inline-block"></span>Paid
            </span>
            <span style="font-size:10px;color:var(--text3);display:flex;align-items:center;gap:4px">
              <span style="width:8px;height:8px;border-radius:50%;background:var(--amber);display:inline-block"></span>Pending
            </span>
            <span style="font-size:10px;color:var(--text3);display:flex;align-items:center;gap:4px">
              <span style="width:8px;height:8px;border-radius:50%;background:var(--red);display:inline-block"></span>No Submission
            </span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3);font-family:'Space Mono',monospace">200 students total</span>
          </div>
        </div>

        <div class="fin-quad">
          <!-- Paid -->
          <div class="fin-quad-cell">
            <div class="fin-quad-n" style="color:var(--green)">132</div>
            <div class="fin-quad-l" style="color:var(--green)">Paid Students</div>
            <div class="fin-quad-sub">66% · All receipts verified</div>
          </div>
          <!-- Unpaid -->
          <div class="fin-quad-cell">
            <div class="fin-quad-n" style="color:var(--red)">48</div>
            <div class="fin-quad-l" style="color:var(--red)">Unpaid Students</div>
            <div class="fin-quad-sub">24% · No submissions yet</div>
          </div>
          <!-- Pending verification -->
          <div class="fin-quad-cell">
            <div class="fin-quad-n" style="color:var(--amber)">20</div>
            <div class="fin-quad-l" style="color:var(--amber)">Pending Review</div>
            <div class="fin-quad-sub">10% · Awaiting verification</div>
          </div>
          <!-- Partially paid -->
          <div class="fin-quad-cell">
            <div class="fin-quad-n" style="color:var(--blue)">14</div>
            <div class="fin-quad-l" style="color:var(--blue)">Partially Paid</div>
            <div class="fin-quad-sub">7% · Some receipts verified</div>
          </div>
        </div>
      </div>

    </div>

    <!-- ── TEAM BREAKDOWN TABLE ── -->
    <div class="card" style="margin-bottom:18px;padding:0;overflow:hidden">
      <div style="padding:18px 20px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
        <span class="card-title">Team Breakdown</span>
        <div style="display:flex;gap:8px">
          <select class="input" style="width:140px;font-size:12px;padding:6px 10px"><option>All Sections</option><option>BSCS-4A</option><option>BSIT-4A</option><option>BSIT-4B</option></select>
          <select class="input" style="width:150px;font-size:12px;padding:6px 10px"><option>All Status</option><option>Fully Paid</option><option>Has Pending</option><option>No Submission</option></select>
        </div>
      </div>
      <table class="table">
        <thead>
          <tr>
            <th style="padding-left:20px">Team</th>
            <th>Section</th>
            <th>Members</th>
            <th>Paid Members</th>
            <th>Submission Rate</th>
            <th>Total Verified</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="padding-left:20px">
              <div style="display:flex;align-items:center;gap:8px">
                <div style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,#c9a82c,#8a6e10);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">TN</div>
                <span class="td-main">Team Nexus</span>
              </div>
            </td>
            <td><span class="badge badge-gold" style="font-size:10px">BSCS-4A</span></td>
            <td style="font-family:'Space Mono',monospace;font-size:12px">4</td>
            <td>
              <div style="font-family:'Space Mono',monospace;font-size:12px;font-weight:600;color:var(--green)">4 / 4</div>
            </td>
            <td>
              <div class="sub-rate-bar"><div class="sub-rate-fill" style="width:100%;background:var(--green)"></div></div>
              <div style="font-size:10px;color:var(--text3);margin-top:3px">100% · 4 receipts</div>
            </td>
            <td style="font-family:'Space Mono',monospace;font-size:12px;color:var(--green)">PHP 2,290</td>
            <td><span class="badge badge-green">Fully Paid</span></td>
          </tr>
          <tr>
            <td style="padding-left:20px">
              <div style="display:flex;align-items:center;gap:8px">
                <div style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,#16a34a,#0891b2);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">TA</div>
                <span class="td-main">Team Alpha</span>
              </div>
            </td>
            <td><span class="badge badge-cyan" style="font-size:10px">BSIT-4A</span></td>
            <td style="font-family:'Space Mono',monospace;font-size:12px">4</td>
            <td>
              <div style="font-family:'Space Mono',monospace;font-size:12px;font-weight:600;color:var(--amber)">3 / 4</div>
            </td>
            <td>
              <div class="sub-rate-bar"><div class="sub-rate-fill" style="width:75%;background:var(--amber)"></div></div>
              <div style="font-size:10px;color:var(--text3);margin-top:3px">75% · 3 receipts · 1 pending</div>
            </td>
            <td style="font-family:'Space Mono',monospace;font-size:12px;color:var(--text2)">PHP 1,640</td>
            <td><span class="badge badge-amber">Has Pending</span></td>
          </tr>
          <tr>
            <td style="padding-left:20px">
              <div style="display:flex;align-items:center;gap:8px">
                <div style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,#1d4ed8,#7c3aed);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">TP</div>
                <span class="td-main">Team Prism</span>
              </div>
            </td>
            <td><span class="badge badge-purple" style="font-size:10px">BSIT-4B</span></td>
            <td style="font-family:'Space Mono',monospace;font-size:12px">5</td>
            <td>
              <div style="font-family:'Space Mono',monospace;font-size:12px;font-weight:600;color:var(--green)">5 / 5</div>
            </td>
            <td>
              <div class="sub-rate-bar"><div class="sub-rate-fill" style="width:100%;background:var(--green)"></div></div>
              <div style="font-size:10px;color:var(--text3);margin-top:3px">100% · 5 receipts</div>
            </td>
            <td style="font-family:'Space Mono',monospace;font-size:12px;color:var(--green)">PHP 3,780</td>
            <td><span class="badge badge-green">Fully Paid</span></td>
          </tr>
          <tr>
            <td style="padding-left:20px">
              <div style="display:flex;align-items:center;gap:8px">
                <div style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,#b45309,#dc2626);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">TS</div>
                <span class="td-main">Team Syntax</span>
              </div>
            </td>
            <td><span class="badge badge-amber" style="font-size:10px">BSIT-4A</span></td>
            <td style="font-family:'Space Mono',monospace;font-size:12px">4</td>
            <td>
              <div style="font-family:'Space Mono',monospace;font-size:12px;font-weight:600;color:var(--red)">0 / 4</div>
            </td>
            <td>
              <div class="sub-rate-bar"><div class="sub-rate-fill" style="width:0%;background:var(--red)"></div></div>
              <div style="font-size:10px;color:var(--red);margin-top:3px;font-weight:500">No submission</div>
            </td>
            <td style="font-family:'Space Mono',monospace;font-size:12px;color:var(--text3)">—</td>
            <td><span class="badge badge-red">No Submission</span></td>
          </tr>
          <tr>
            <td style="padding-left:20px">
              <div style="display:flex;align-items:center;gap:8px">
                <div style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,#0891b2,#16a34a);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">TB</div>
                <span class="td-main">Team Bulbasaur</span>
              </div>
            </td>
            <td><span class="badge badge-gold" style="font-size:10px">BSIT-4A</span></td>
            <td style="font-family:'Space Mono',monospace;font-size:12px">5</td>
            <td>
              <div style="font-family:'Space Mono',monospace;font-size:12px;font-weight:600;color:var(--amber)">2 / 5</div>
            </td>
            <td>
              <div class="sub-rate-bar"><div class="sub-rate-fill" style="width:40%;background:var(--amber)"></div></div>
              <div style="font-size:10px;color:var(--text3);margin-top:3px">40% · 2 verified · 2 pending</div>
            </td>
            <td style="font-family:'Space Mono',monospace;font-size:12px;color:var(--text2)">PHP 1,200</td>
            <td><span class="badge badge-amber">Has Pending</span></td>
          </tr>
        </tbody>
      </table>
      <div style="padding:12px 20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
        <span style="font-size:12px;color:var(--text3)">Showing 5 of 48 teams</span>
        <div style="display:flex;gap:6px">
          <button class="btn btn-ghost" style="font-size:11px;padding:5px 12px">← Prev</button>
          <button class="btn btn-ghost" style="font-size:11px;padding:5px 12px">Next →</button>
        </div>
      </div>
    </div>

    <!-- ── RECEIPT QUEUE ── -->
    <div class="card" style="padding:0;overflow:hidden">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 20px 14px;border-bottom:1px solid var(--border)">
        <div>
          <div style="font-size:14px;font-weight:600;color:var(--text)">Receipt Queue</div>
          <div style="font-size:12px;color:var(--text3);margin-top:2px">Individual student receipt submissions pending admin action</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
          <select class="input" style="width:160px;font-size:12px;padding:6px 10px"><option>All Teams</option><option>Team Nexus</option><option>Team Alpha</option><option>Team Prism</option><option>Team Syntax</option></select>
          <select class="input" style="width:150px;font-size:12px;padding:6px 10px"><option>All Status</option><option>Pending</option><option>Verified</option><option>Rejected</option></select>
        </div>
      </div>
      <div class="gap-12" style="padding:16px 20px">
      <div class="receipt-card">
        <div class="receipt-thumb">
          <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        </div>
        <div class="receipt-info">
          <div class="receipt-name">Capstone 1 Title Defense Fee</div>
          <div class="receipt-meta">Team Nexus · <strong style="color:var(--text)">Maria Andres</strong> · Uploaded Apr 20, 2025</div>
          <div class="receipt-amount">PHP 960.00</div>
          <div class="receipt-actions">
            <button class="btn btn-success" style="font-size:11px;padding:5px 14px">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="11" height="11"><polyline points="20 6 9 17 4 12"/></svg>
              Approve
            </button>
            <button class="btn btn-danger" style="font-size:11px;padding:5px 14px">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="11" height="11"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              Reject
            </button>
            <button class="btn btn-ghost" style="font-size:11px;padding:5px 12px">View Image</button>
            <span class="badge badge-amber" style="margin-left:auto">Pending</span>
          </div>
        </div>
      </div>

      <div class="receipt-card">
        <div class="receipt-thumb">
          <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        </div>
        <div class="receipt-info">
          <div class="receipt-name">Capstone 1 Title Defense Fee</div>
          <div class="receipt-meta">Team Nexus · <strong style="color:var(--text)">Juan Reyes</strong> · Uploaded Apr 22, 2025</div>
          <div class="receipt-amount">PHP 960.00</div>
          <div class="receipt-actions">
            <button class="btn btn-success" style="font-size:11px;padding:5px 14px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="11" height="11"><polyline points="20 6 9 17 4 12"/></svg>Approve</button>
            <button class="btn btn-danger" style="font-size:11px;padding:5px 14px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="11" height="11"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Reject</button>
            <button class="btn btn-ghost" style="font-size:11px;padding:5px 12px">View Image</button>
            <span class="badge badge-amber" style="margin-left:auto">Pending</span>
          </div>
        </div>
      </div>

      <div class="receipt-card">
        <div class="receipt-thumb">
          <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        </div>
        <div class="receipt-info">
          <div class="receipt-name">Capstone 1 Title Defense Fee</div>
          <div class="receipt-meta">Team Alpha · <strong style="color:var(--text)">Lea Cruz</strong> · Uploaded Apr 28, 2025</div>
          <div class="receipt-amount">PHP 960.00</div>
          <div class="receipt-actions">
            <button class="btn btn-success" style="font-size:11px;padding:5px 14px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="11" height="11"><polyline points="20 6 9 17 4 12"/></svg>Approve</button>
            <button class="btn btn-danger" style="font-size:11px;padding:5px 14px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="11" height="11"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Reject</button>
            <button class="btn btn-ghost" style="font-size:11px;padding:5px 12px">View Image</button>
            <span class="badge badge-amber" style="margin-left:auto">Pending</span>
          </div>
        </div>
      </div>

      <div class="receipt-card" style="opacity:0.65">
        <div class="receipt-thumb" style="background:var(--green-bg);border-color:rgba(22,163,74,0.3)">
          <svg viewBox="0 0 24 24" style="stroke:var(--green)"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        </div>
        <div class="receipt-info">
          <div class="receipt-name">Capstone 1 Title Defense Fee</div>
          <div class="receipt-meta">Team Nexus · <strong style="color:var(--text)">Rico Peña</strong> · Uploaded Apr 15, 2025</div>
          <div class="receipt-amount">PHP 960.00</div>
          <div class="receipt-actions">
            <span style="font-size:11px;color:var(--green);display:flex;align-items:center;gap:4px">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><polyline points="20 6 9 17 4 12"/></svg>
              Verified on Apr 16, 2025
            </span>
            <span class="badge badge-green" style="margin-left:auto">Verified</span>
          </div>
        </div>
      </div>
      </div>
    </div>

  </div>
</div>

</body>
</html>
