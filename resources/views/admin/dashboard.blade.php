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

@include('partials.welcome-modal')

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
        <div class="stat-n" style="color:var(--blue)">{{ $archivedCapstones }}</div>
        <div class="stat-l">Archived Capstones</div>
        <div class="stat-sub">All time</div>
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
          @forelse ($pendingExperts as $expert)
            <div class="expert-row">
              <div class="expert-av" style="background:linear-gradient(135deg,#1d4ed8,#7c3aed)">{{ $expert->avatarInitials() }}</div>
              <div style="flex:1">
                <div style="font-size:13px;font-weight:600;color:var(--text)">{{ $expert->name }}</div>
                <div style="font-size:11px;color:var(--text3)">
                  {{ $expert->specialization ?: 'No role set' }}
                  @if ($expert->department)
                    · {{ $expert->department }}
                  @endif
                </div>
              </div>
              <div style="display:flex;gap:6px">
                <a href="{{ route('admin.approval') }}" class="btn btn-success" style="font-size:10px;padding:4px 10px">Review</a>
              </div>
            </div>
          @empty
            <div style="padding:24px 16px;text-align:center;color:var(--text3);font-size:13px">No pending approvals.</div>
          @endforelse
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <span class="card-title">Recent Activity</span>
          <a href="{{ route('admin.activitylog') }}" class="btn btn-ghost" style="font-size:11px;padding:4px 10px">View All</a>
        </div>
        <div>
          @forelse ($recentActivity as $act)
            <div class="act-item">
              <div class="act-dot" style="background:{{ $act['iconBg'] }}"><svg viewBox="{{ $act['iconViewBox'] }}" style="stroke:{{ $act['iconColor'] }}">{!! $act['iconPath'] !!}</svg></div>
              <div class="act-body">{!! $act['text'] !!}<div class="act-time">{{ $act['time'] ? \Carbon\Carbon::parse($act['time'])->diffForHumans() : '' }}</div></div>
            </div>
          @empty
            <div style="padding:24px 16px;text-align:center;color:var(--text3);font-size:13px">No recent activity.</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>
</div>

</body>
</html>
