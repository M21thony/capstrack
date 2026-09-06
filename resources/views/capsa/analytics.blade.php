<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Analytics - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
</head>
<body>

@include('capsa._sidebar')
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Analytics</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
      <button class="btn btn-ghost-nav">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><path d="M12 15V3"/></svg>
        Export Report
      </button>
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="grid-4" style="margin-bottom:16px">
      <div class="sk" style="height:78px;border-radius:14px"></div>
      <div class="sk" style="height:78px;border-radius:14px"></div>
      <div class="sk" style="height:78px;border-radius:14px"></div>
      <div class="sk" style="height:78px;border-radius:14px"></div>
    </div>
    <div class="card" style="margin-bottom:16px">
      <div class="sk" style="width:220px;height:16px;border-radius:4px;margin-bottom:16px"></div>
      <div class="sk" style="height:180px;border-radius:10px"></div>
    </div>
    <div class="grid-4" style="margin-bottom:16px">
      <div class="sk" style="height:100px;border-radius:14px"></div>
      <div class="sk" style="height:100px;border-radius:14px"></div>
      <div class="sk" style="height:100px;border-radius:14px"></div>
      <div class="sk" style="height:100px;border-radius:14px"></div>
    </div>
    <div class="card">
      <div class="sk" style="width:220px;height:16px;border-radius:4px;margin-bottom:16px"></div>
      <div class="sk" style="height:200px;border-radius:10px"></div>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content">

    <!-- TOP STAT CHIPS -->
    <div class="grid-4" style="margin-bottom:16px">
      <div class="stat-chip"><div class="stat-n" style="color:var(--accent3)">{{ $totalArchived }}</div><div class="stat-l">Total Archived</div><div class="stat-sub">System-wide</div></div>
      <div class="stat-chip"><div class="stat-n" style="color:var(--green)">{{ $completionRate }}%</div><div class="stat-l">Avg Completion Rate</div><div class="stat-sub">{{ $completedTeams }}/{{ $totalTeams }} teams completed</div></div>
      <div class="stat-chip"><div class="stat-n" style="color:var(--blue)">{{ $totalExperts }}</div><div class="stat-l">Active Experts</div><div class="stat-sub">{{ $totalStudents }} students enrolled</div></div>
      <div class="stat-chip"><div class="stat-n" style="color:var(--red)">{{ $overdueTeams }}</div><div class="stat-l">Overdue Teams</div><div class="stat-sub">{{ $activeTeams }} active teams</div></div>
    </div>

    <div class="grid-1" style="margin-bottom:16px">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Capstones Submitted Per Year</span>
          <select onchange="window.location='?year='+this.value" style="font-family:'DM Sans',sans-serif;font-size:11px;font-weight:600;padding:4px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg2);color:var(--text2);outline:none;cursor:pointer;">
            <option value="">All Years</option>
            @foreach($years as $year)
            <option value="{{ $year }}" @selected((string) $selectedYear === (string) $year)>{{ $year }}</option>
            @endforeach
          </select>
        </div>
        <div style="display:flex;align-items:flex-end;width:100%;gap:10px;height:180px;padding:0 16px 8px 16px">
          @forelse($yearlyArchive as $year => $count)
          <div style="display:flex;flex-direction:column;align-items:center;flex:1">
            <span style="font-size:10px;color:var(--text3);font-variant-numeric:tabular-nums;margin-bottom:2px">{{ $count }}</span>
            <div style="width:100%;background:{{ (string) $selectedYear === (string) $year ? 'var(--accent)' : 'var(--bg4)' }};border-radius:4px 4px 0 0;height:{{ max(round(($count / $maxYearlyCount) * 130), 8) }}px"></div>
            <div style="font-size:10px;color:{{ (string) $selectedYear === (string) $year ? 'var(--accent3)' : 'var(--text3)' }};font-weight:{{ (string) $selectedYear === (string) $year ? 700 : 400 }};margin-top:4px">{{ substr($year, 2) }}</div>
          </div>
          @empty
          <div style="width:100%;text-align:center;font-size:12px;color:var(--text3);padding-bottom:40px;">No archived capstones yet.</div>
          @endforelse
        </div>
      </div>
    </div>

    <!-- CURRENT AY DEFENSE CARDS -->
    <div class="grid-4" style="margin-bottom:16px">
      <div class="card">
        <div class="card-header"><span class="card-title">Pre-Proposal Defense</span><span class="badge badge-green">Active</span></div>
        <div style="display:flex;align-items:center;gap:12px">
          <div style="flex:1"><div style="font-size:24px;font-variant-numeric:tabular-nums;font-weight:700;color:var(--green)">36</div><div style="font-size:10px;color:var(--text3);margin-top:2px">Passed</div></div>
          <div style="width:1px;height:24px;background:var(--border)"></div>
          <div style="flex:1"><div style="font-size:24px;font-variant-numeric:tabular-nums;font-weight:700;color:var(--red)">12</div><div style="font-size:10px;color:var(--text3);margin-top:2px">Failed</div></div>
          <div style="width:1px;height:24px;background:var(--border)"></div>
          <div style="flex:1;text-align:center"><div style="font-size:18px;font-variant-numeric:tabular-nums;font-weight:700;color:var(--accent3)">75%</div><div style="font-size:10px;color:var(--text3);margin-top:2px">Rate</div></div>
        </div>
        <div style="margin-top:12px;font-size:11px;color:var(--text3)">48 teams · AY 2025–2026</div>
      </div>
      <div class="card">
        <div class="card-header"><span class="card-title">Title Defense</span><span class="badge badge-green">Active</span></div>
        <div style="display:flex;align-items:center;gap:12px">
          <div style="flex:1"><div style="font-size:24px;font-variant-numeric:tabular-nums;font-weight:700;color:var(--green)">32</div><div style="font-size:10px;color:var(--text3);margin-top:2px">Passed</div></div>
          <div style="width:1px;height:24px;background:var(--border)"></div>
          <div style="flex:1"><div style="font-size:24px;font-variant-numeric:tabular-nums;font-weight:700;color:var(--red)">4</div><div style="font-size:10px;color:var(--text3);margin-top:2px">Failed</div></div>
          <div style="width:1px;height:24px;background:var(--border)"></div>
          <div style="flex:1;text-align:center"><div style="font-size:18px;font-variant-numeric:tabular-nums;font-weight:700;color:var(--accent3)">89%</div><div style="font-size:10px;color:var(--text3);margin-top:2px">Rate</div></div>
        </div>
        <div style="margin-top:12px;font-size:11px;color:var(--text3)">36 teams · AY 2025–2026</div>
      </div>
      <div class="card">
        <div class="card-header"><span class="card-title">Pre-Oral Defense</span><span class="badge badge-green">Active</span></div>
        <div style="display:flex;align-items:center;gap:12px">
          <div style="flex:1"><div style="font-size:24px;font-variant-numeric:tabular-nums;font-weight:700;color:var(--green)">24</div><div style="font-size:10px;color:var(--text3);margin-top:2px">Passed</div></div>
          <div style="width:1px;height:24px;background:var(--border)"></div>
          <div style="flex:1"><div style="font-size:24px;font-variant-numeric:tabular-nums;font-weight:700;color:var(--red)">8</div><div style="font-size:10px;color:var(--text3);margin-top:2px">Failed</div></div>
          <div style="width:1px;height:24px;background:var(--border)"></div>
          <div style="flex:1;text-align:center"><div style="font-size:18px;font-variant-numeric:tabular-nums;font-weight:700;color:var(--accent3)">75%</div><div style="font-size:10px;color:var(--text3);margin-top:2px">Rate</div></div>
        </div>
        <div style="margin-top:12px;font-size:11px;color:var(--text3)">32 teams · AY 2025–2026</div>
      </div>
      <div class="card">
        <div class="card-header"><span class="card-title">Final Oral Defense</span><span class="badge badge-amber">In Progress</span></div>
        <div style="display:flex;align-items:center;gap:12px">
          <div style="flex:1"><div style="font-size:24px;font-variant-numeric:tabular-nums;font-weight:700;color:var(--green)">18</div><div style="font-size:10px;color:var(--text3);margin-top:2px">Passed</div></div>
          <div style="width:1px;height:24px;background:var(--border)"></div>
          <div style="flex:1"><div style="font-size:24px;font-variant-numeric:tabular-nums;font-weight:700;color:var(--red)">6</div><div style="font-size:10px;color:var(--text3);margin-top:2px">Failed</div></div>
          <div style="width:1px;height:24px;background:var(--border)"></div>
          <div style="flex:1;text-align:center"><div style="font-size:18px;font-variant-numeric:tabular-nums;font-weight:700;color:var(--accent3)">75%</div><div style="font-size:10px;color:var(--text3);margin-top:2px">Rate</div></div>
          <div style="width:1px;height:24px;background:var(--border)"></div>
          <div style="flex:1"><div style="font-size:24px;font-variant-numeric:tabular-nums;font-weight:700;color:var(--text3)">8</div><div style="font-size:10px;color:var(--text3);margin-top:2px">Pending</div></div>
        </div>
        <div style="margin-top:12px;font-size:11px;color:var(--text3)">32 teams · AY 2025–2026</div>
      </div>
    </div>

    <!-- DEFENSE STAGE ANALYTICS · TABBED HISTORICAL -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header" style="flex-wrap:wrap;gap:10px">
        <span class="card-title">Defense Stage Analytics</span>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <div class="tab-bar" id="defense-tabs">
            <button class="tab-btn active" data-tab="ppd">PPD</button>
            <button class="tab-btn" data-tab="td">TD</button>
            <button class="tab-btn" data-tab="pod">POD</button>
            <button class="tab-btn" data-tab="fod">FOD</button>
          </div>
          <span class="badge badge-gray" style="font-size:10px">2018–2026</span>
        </div>
      </div>

      <div class="tab-panel active" id="panel-ppd">
        <div style="font-size:11px;color:var(--text3);margin-bottom:12px">Pre-Proposal Defense · Pass/Fail per Academic Year</div>
        <div class="tbl-scroll"><table class="yt">
          <thead><tr><th>Academic Year</th><th>Teams</th><th>Passed</th><th>Failed</th><th>Pass Rate</th></tr></thead>
          <tbody>
            <tr><td>SY 2018–2019</td><td class="total">28</td><td class="pass">22</td><td class="fail">6</td><td class="rate">79%</td></tr>
            <tr><td>SY 2019–2020</td><td class="total">34</td><td class="pass">27</td><td class="fail">7</td><td class="rate">79%</td></tr>
            <tr><td>SY 2020–2021</td><td class="total">22</td><td class="pass">18</td><td class="fail">4</td><td class="rate">82%</td></tr>
            <tr><td>SY 2021–2022</td><td class="total">41</td><td class="pass">31</td><td class="fail">10</td><td class="rate">76%</td></tr>
            <tr><td>SY 2022–2023</td><td class="total">45</td><td class="pass">38</td><td class="fail">7</td><td class="rate">84%</td></tr>
            <tr><td>SY 2023–2024</td><td class="total">52</td><td class="pass">43</td><td class="fail">9</td><td class="rate">83%</td></tr>
            <tr><td>SY 2024–2025</td><td class="total">58</td><td class="pass">50</td><td class="fail">8</td><td class="rate">86%</td></tr>
            <tr class="yt-current"><td>SY 2025–2026 ?</td><td class="total">48</td><td class="pass">36</td><td class="fail">12</td><td class="rate">75%</td></tr>
          </tbody>
          <tfoot><tr><td>All-Time Total</td><td class="total">328</td><td class="pass">265</td><td class="fail">63</td><td class="rate">81%</td></tr></tfoot>
        </table></div>
      </div>

      <div class="tab-panel" id="panel-td">
        <div style="font-size:11px;color:var(--text3);margin-bottom:12px">Title Defense · Pass/Fail per Academic Year</div>
        <div class="tbl-scroll"><table class="yt">
          <thead><tr><th>Academic Year</th><th>Teams</th><th>Passed</th><th>Failed</th><th>Pass Rate</th></tr></thead>
          <tbody>
            <tr><td>SY 2018–2019</td><td class="total">22</td><td class="pass">19</td><td class="fail">3</td><td class="rate">86%</td></tr>
            <tr><td>SY 2019–2020</td><td class="total">27</td><td class="pass">24</td><td class="fail">3</td><td class="rate">89%</td></tr>
            <tr><td>SY 2020–2021</td><td class="total">18</td><td class="pass">16</td><td class="fail">2</td><td class="rate">89%</td></tr>
            <tr><td>SY 2021–2022</td><td class="total">31</td><td class="pass">28</td><td class="fail">3</td><td class="rate">90%</td></tr>
            <tr><td>SY 2022–2023</td><td class="total">38</td><td class="pass">35</td><td class="fail">3</td ><td class="rate">92%</td ></tr>
            <tr><td>SY 2023–2024</td><td class="total">43</td><td class="pass">39</td ><td class="fail">4</td ><td class="rate">91%</td ></tr>
            <tr><td>SY 2024–2025</td><td class="total">50</td><td class="pass">46</td><td class="fail">4</td><td class="rate">92%</td></tr>
            <tr class="yt-current"><td>SY 2025–2026 ?</td><td class="total">36</td><td class="pass">32</td><td class="fail">4</td><td class="rate">89%</td></tr>
          </tbody>
          <tfoot><tr><td>All-Time Total</td><td class="total">265</td><td class="pass">239</td><td class="fail">26</td><td class="rate">90%</td></tr></tfoot>
        </table></div>
      </div>

      <div class="tab-panel" id="panel-pod">
        <div style="font-size:11px;color:var(--text3);margin-bottom:12px">Pre-Oral Defense · Pass/Fail per Academic Year</div>
        <div class="tbl-scroll"><table class="yt">
          <thead><tr><th>Academic Year</th><th>Teams</th><th>Passed</th><th>Failed</th><th>Pass Rate</th></tr></thead>
          <tbody>
            <tr><td>SY 2018–2019</td><td class="total">19</td><td class="pass">13</td><td class="fail">6</td><td class="rate">68%</td></tr>
            <tr><td>SY 2019–2020</td><td class="total">24</td><td class="pass">17</td><td class="fail">7</td><td class="rate">71%</td></tr>
            <tr><td>SY 2020–2021</td><td class="total">16</td><td class="pass">11</td><td class="fail">5</td><td class="rate">69%</td></tr>
            <tr><td>SY 2021–2022</td><td class="total">28</td><td class="pass">20</td><td class="fail">8</td><td class="rate">71%</td></tr>
            <tr><td>SY 2022–2023</td><td class="total">35</td><td class="pass">26</td><td class="fail">9</td><td class="rate">74%</td></tr>
            <tr><td>SY 2023–2024</td><td class="total">39</td><td class="pass">30</td><td class="fail">9</td><td class="rate">77%</td></tr>
            <tr><td>SY 2024–2025</td><td class="total">46</td><td class="pass">37</td><td class="fail">9</td><td class="rate">80%</td></tr>
            <tr class="yt-current"><td>SY 2025–2026 ?</td><td class="total">32</td><td class="pass">24</td><td class="fail">8</td><td class="rate">75%</td></tr>
          </tbody>
          <tfoot><tr><td>All-Time Total</td><td class="total">239</td><td class="pass">178</td><td class="fail">61</td><td class="rate">74%</td></tr></tfoot>
        </table></div>
      </div>

      <div class="tab-panel" id="panel-fod">
        <div style="font-size:11px;color:var(--text3);margin-bottom:12px">Final Oral Defense · Pass/Fail per Academic Year</div>
        <div class="tbl-scroll"><table class="yt">
          <thead><tr><th>Academic Year</th><th>Teams</th><th>Passed</th><th>Failed</th><th>Pending</th><th>Pass Rate</th></tr></thead>
          <tbody>
            <tr><td>SY 2018–2019</td><td class="total">13</td><td class="pass">11</td><td class="fail">2</td><td style="color:var(--text3)">·</td><td class="rate">85%</td></tr>
            <tr><td>SY 2019–2020</td><td class="total">17</td><td class="pass">14</td><td class="fail">3</td><td style="color:var(--text3)">·</td><td class="rate">82%</td></tr>
            <tr><td>SY 2020–2021</td><td class="total">11</td><td class="pass">9</td><td class="fail">2</td><td style="color:var(--text3)">·</td><td class="rate">82%</td></tr>
            <tr><td>SY 2021–2022</td><td class="total">20</td><td class="pass">17</td><td class="fail">3</td><td style="color:var(--text3)">·</td><td class="rate">85%</td></tr>
            <tr><td>SY 2022–2023</td><td class="total">26</td><td class="pass">22</td><td class="fail">4</td><td style="color:var(--text3)">·</td><td class="rate">85%</td></tr>
            <tr><td>SY 2023–2024</td><td class="total">30</td><td class="pass">26</td><td class="fail">4</td><td style="color:var(--text3)">·</td><td class="rate">87%</td></tr>
            <tr><td>SY 2024–2025</td><td class="total">37</td><td class="pass">32</td><td class="fail">5</td><td style="color:var(--text3)">·</td><td class="rate">86%</td></tr>
            <tr class="yt-current"><td>SY 2025–2026 ?</td><td class="total">32</td><td class="pass">18</td><td class="fail">6</td><td style="font-variant-numeric:tabular-nums;color:var(--text3)">8</td><td class="rate">75%*</td></tr>
          </tbody>
          <tfoot><tr><td>All-Time Total</td><td class="total">186</td><td class="pass">149</td><td class="fail">29</td><td style="font-variant-numeric:tabular-nums;color:var(--text3)">8</td><td class="rate">84%</td></tr></tfoot>
        </table></div>
        <div style="font-size:10px;color:var(--text3);margin-top:8px">* SY 2025–2026 rate excludes 8 pending teams</div>
      </div>
    </div>

    <!-- CATEGORY DISTRIBUTION ANALYTICS · HISTORICAL -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header" style="flex-wrap:wrap;gap:10px">
        <span class="card-title">Category Distribution Analytics</span>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <div class="tab-bar" id="cat-tabs">
            <button class="tab-btn active" data-cattab="yearly">By Year</button>
            <button class="tab-btn" data-cattab="alltime">All-Time</button>
          </div>
          <span class="badge badge-gray" style="font-size:10px">2018–2026</span>
        </div>
      </div>

      <div class="tab-panel active" id="panel-cat-yearly">
        <div style="font-size:11px;color:var(--text3);margin-bottom:14px">Capstone category breakdown per Academic Year</div>
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:14px">
          <div style="display:flex;align-items:center;gap:5px"><div class="cat-dot" style="background:#1d4ed8"></div><span style="font-size:11px;color:var(--text2)">Web-Based</span></div>
          <div style="display:flex;align-items:center;gap:5px"><div class="cat-dot" style="background:#7c3aed"></div><span style="font-size:11px;color:var(--text2)">Mobile</span></div>
          <div style="display:flex;align-items:center;gap:5px"><div class="cat-dot" style="background:#16a34a"></div><span style="font-size:11px;color:var(--text2)">IoT</span></div>
          <div style="display:flex;align-items:center;gap:5px"><div class="cat-dot" style="background:#b45309"></div><span style="font-size:11px;color:var(--text2)">AI/ML</span></div>
          <div style="display:flex;align-items:center;gap:5px"><div class="cat-dot" style="background:#0891b2"></div><span style="font-size:11px;color:var(--text2)">Management Systems</span></div>
          <div style="display:flex;align-items:center;gap:5px"><div class="cat-dot" style="background:#94a3b8"></div><span style="font-size:11px;color:var(--text2)">Others</span></div>
        </div>
        <div style="display:flex;flex-direction:column;gap:5px">
          <div class="sbar-row"><div class="sbar-label">2018</div><div class="sbar-track"><div class="sbar-seg" style="width:32%;background:#1d4ed8"></div><div class="sbar-seg" style="width:21%;background:#7c3aed"></div><div class="sbar-seg" style="width:15%;background:#16a34a"></div><div class="sbar-seg" style="width:11%;background:#b45309"></div><div class="sbar-seg" style="width:10%;background:#0891b2"></div><div class="sbar-seg" style="width:11%;background:#94a3b8"></div></div><div class="sbar-meta">9·6·4·3·3·3</div></div>
          <div class="sbar-row"><div class="sbar-label">2019</div><div class="sbar-track"><div class="sbar-seg" style="width:32%;background:#1d4ed8"></div><div class="sbar-seg" style="width:24%;background:#7c3aed"></div><div class="sbar-seg" style="width:15%;background:#16a34a"></div><div class="sbar-seg" style="width:12%;background:#b45309"></div><div class="sbar-seg" style="width:9%;background:#0891b2"></div><div class="sbar-seg" style="width:8%;background:#94a3b8"></div></div><div class="sbar-meta">11·8·5·4·3·3</div></div>
          <div class="sbar-row"><div class="sbar-label">2020</div><div class="sbar-track"><div class="sbar-seg" style="width:32%;background:#1d4ed8"></div><div class="sbar-seg" style="width:23%;background:#7c3aed"></div><div class="sbar-seg" style="width:18%;background:#16a34a"></div><div class="sbar-seg" style="width:14%;background:#b45309"></div><div class="sbar-seg" style="width:9%;background:#0891b2"></div><div class="sbar-seg" style="width:4%;background:#94a3b8"></div></div><div class="sbar-meta">7·5·4·3·2·1</div></div>
          <div class="sbar-row"><div class="sbar-label">2021</div><div class="sbar-track"><div class="sbar-seg" style="width:32%;background:#1d4ed8"></div><div class="sbar-seg" style="width:22%;background:#7c3aed"></div><div class="sbar-seg" style="width:17%;background:#16a34a"></div><div class="sbar-seg" style="width:12%;background:#b45309"></div><div class="sbar-seg" style="width:10%;background:#0891b2"></div><div class="sbar-seg" style="width:7%;background:#94a3b8"></div></div><div class="sbar-meta">13·9·7·5·4·3</div></div>
          <div class="sbar-row"><div class="sbar-label">2022</div><div class="sbar-track"><div class="sbar-seg" style="width:33%;background:#1d4ed8"></div><div class="sbar-seg" style="width:22%;background:#7c3aed"></div><div class="sbar-seg" style="width:18%;background:#16a34a"></div><div class="sbar-seg" style="width:13%;background:#b45309"></div><div class="sbar-seg" style="width:9%;background:#0891b2"></div><div class="sbar-seg" style="width:5%;background:#94a3b8"></div></div><div class="sbar-meta">15·10·8·6·4·2</div></div>
          <div class="sbar-row"><div class="sbar-label">2023</div><div class="sbar-track"><div class="sbar-seg" style="width:33%;background:#1d4ed8"></div><div class="sbar-seg" style="width:23%;background:#7c3aed"></div><div class="sbar-seg" style="width:17%;background:#16a34a"></div><div class="sbar-seg" style="width:13%;background:#b45309"></div><div class="sbar-seg" style="width:8%;background:#0891b2"></div><div class="sbar-seg" style="width:6%;background:#94a3b8"></div></div><div class="sbar-meta">17·12·9·7·4·3</div></div>
          <div class="sbar-row"><div class="sbar-label">2024</div><div class="sbar-track"><div class="sbar-seg" style="width:33%;background:#1d4ed8"></div><div class="sbar-seg" style="width:24%;background:#7c3aed"></div><div class="sbar-seg" style="width:17%;background:#16a34a"></div><div class="sbar-seg" style="width:14%;background:#b45309"></div><div class="sbar-seg" style="width:7%;background:#0891b2"></div><div class="sbar-seg" style="width:5%;background:#94a3b8"></div></div><div class="sbar-meta">19·14·10·8·4·3</div></div>
          <div class="sbar-row" style="background:var(--accent-bg,#fef9ec);border-radius:6px;padding:2px 4px;border-left:3px solid var(--accent,#d97706)">
            <div class="sbar-label" style="color:var(--accent3);font-weight:700">2025?</div>
            <div class="sbar-track"><div class="sbar-seg" style="width:33%;background:#1d4ed8"></div><div class="sbar-seg" style="width:25%;background:#7c3aed"></div><div class="sbar-seg" style="width:17%;background:#16a34a"></div><div class="sbar-seg" style="width:15%;background:#b45309"></div><div class="sbar-seg" style="width:6%;background:#0891b2"></div><div class="sbar-seg" style="width:4%;background:#94a3b8"></div></div>
            <div class="sbar-meta" style="color:var(--accent3);font-weight:700">16·12·8·7·3·2</div>
          </div>
        </div>
      </div>

      <div class="tab-panel" id="panel-cat-alltime">
        <div style="font-size:11px;color:var(--text3);margin-bottom:14px">Cumulative category totals across all 8 academic years (2018–2026)</div>
        <div class="tbl-scroll"><table class="yt">
          <thead><tr><th>Category</th><th>2018</th><th>2019</th><th>2020</th><th>2021</th><th>2022</th><th>2023</th><th>2024</th><th>2025?</th><th>Total</th></tr></thead>
          <tbody>
            <tr><td><span style="display:inline-flex;align-items:center;gap:6px"><span class="cat-dot" style="background:#1d4ed8"></span>Web-Based</span></td><td>9</td><td>11</td><td>7</td><td>13</td><td>15</td><td>17</td><td>19</td><td class="rate">16</td><td class="total">107</td></tr>
            <tr><td><span style="display:inline-flex;align-items:center;gap:6px"><span class="cat-dot" style="background:#7c3aed"></span>Mobile</span></td><td>6</td><td>8</td><td>5</td><td>9</td><td>10</td><td>12</td><td>14</td><td class="rate">12</td><td class="total">76</td></tr>
            <tr><td><span style="display:inline-flex;align-items:center;gap:6px"><span class="cat-dot" style="background:#16a34a"></span>IoT</span></td><td>4</td><td>5</td><td>4</td><td>7</td><td>8</td><td>9</td><td>10</td><td class="rate">8</td><td class="total">55</td></tr>
            <tr><td><span style="display:inline-flex;align-items:center;gap:6px"><span class="cat-dot" style="background:#b45309"></span>AI/ML</span></td><td>3</td><td>4</td><td>3</td><td>5</td><td>6</td><td>7</td><td>8</td><td class="rate">7</td><td class="total">43</td></tr>
            <tr><td><span style="display:inline-flex;align-items:center;gap:6px"><span class="cat-dot" style="background:#0891b2"></span>Mgmt Systems</span></td><td>3</td><td>3</td><td>2</td><td>4</td><td>4</td><td>4</td><td>4</td><td class="rate">3</td><td class="total">27</td></tr>
            <tr><td><span style="display:inline-flex;align-items:center;gap:6px"><span class="cat-dot" style="background:#94a3b8"></span>Others</span></td><td>3</td><td>3</td><td>1</td><td>3</td><td>2</td><td>3</td><td>3</td><td class="rate">2</td><td class="total">20</td></tr>
          </tbody>
          <tfoot><tr><td>Total</td><td class="total">28</td><td class="total">34</td><td class="total">22</td><td class="total">41</td><td class="total">45</td><td class="total">52</td><td class="total">58</td><td class="total">48</td><td class="total">328</td></tr></tfoot>
        </table></div>
        <div style="margin-top:16px">
          <div class="subsection">All-Time Totals</div>
          <div class="alltime-grid">
            <div class="alltime-chip"><div class="n" style="color:#1d4ed8">107</div><div class="l">Web-Based · 33% of all capstones</div></div>
            <div class="alltime-chip"><div class="n" style="color:#7c3aed">76</div><div class="l">Mobile · 23% of all capstones</div></div>
            <div class="alltime-chip"><div class="n" style="color:#16a34a">55</div><div class="l">IoT · 17% of all capstones</div></div>
            <div class="alltime-chip"><div class="n" style="color:#b45309">43</div><div class="l">AI/ML · 13% of all capstones</div></div>
            <div class="alltime-chip"><div class="n" style="color:#0891b2">27</div><div class="l">Management Systems · 8%</div></div>
            <div class="alltime-chip"><div class="n" style="color:var(--text3)">20</div><div class="l">Others · 6% of all capstones</div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- BOTTOM ROW -->
    <div class="grid-3" style="margin-bottom:16px">
      <div class="card">
        <div class="card-header"><span class="card-title">Defense Stage Summary</span><span class="badge badge-gold">AY 2025–2026</span></div>
        <div class="gap-12" style="margin-top:4px">
          <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:var(--green-bg);border-radius:8px">
            <div style="display:flex;align-items:center;gap:8px"><svg viewBox="0 0 24 24" width="16" height="16" stroke="var(--green)" fill="none" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><span style="font-size:13px;font-weight:500;color:var(--text)">Pre-Proposal Defense</span></div>
            <span style="font-size:14px;font-variant-numeric:tabular-nums;font-weight:700;color:var(--green)">36 passed</span>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:var(--green-bg);border-radius:8px">
            <div style="display:flex;align-items:center;gap:8px"><svg viewBox="0 0 24 24" width="16" height="16" stroke="var(--green)" fill="none" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><span style="font-size:13px;font-weight:500;color:var(--text)">Title Defense</span></div>
            <span style="font-size:14px;font-variant-numeric:tabular-nums;font-weight:700;color:var(--green)">32 passed</span>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:var(--green-bg);border-radius:8px">
            <div style="display:flex;align-items:center;gap:8px"><svg viewBox="0 0 24 24" width="16" height="16" stroke="var(--green)" fill="none" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><span style="font-size:13px;font-weight:500;color:var(--text)">Pre-Oral Defense</span></div>
            <span style="font-size:14px;font-variant-numeric:tabular-nums;font-weight:700;color:var(--green)">24 passed</span>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:var(--amber-bg);border-radius:8px">
            <div style="display:flex;align-items:center;gap:8px"><svg viewBox="0 0 24 24" width="16" height="16" stroke="var(--amber)" fill="none" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span style="font-size:13px;font-weight:500;color:var(--text)">Final Oral Defense</span></div>
            <span style="font-size:14px;font-variant-numeric:tabular-nums;font-weight:500;color:var(--amber)">18 passed · 8 pending</span>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><span class="card-title">Defense Stage Results</span></div>
        <div class="gap-12" style="margin-top:4px">
          <div>
            <div style="display:flex;justify-content:space-between;margin-bottom:5px"><span style="font-size:12px;color:var(--text2)">Title Defense</span><span style="font-size:11px;font-variant-numeric:tabular-nums;color:var(--green)">92%</span></div>
            <div class="pbar"><div class="pbar-fill pf-green" style="width:92%"></div></div>
          </div>
          <div>
            <div style="display:flex;justify-content:space-between;margin-bottom:5px"><span style="font-size:12px;color:var(--text2)">Pre-oral Defense</span><span style="font-size:11px;font-variant-numeric:tabular-nums;color:var(--accent3)">68%</span></div>
            <div class="pbar"><div class="pbar-fill pf-gold" style="width:68%"></div></div>
          </div>
          <div>
            <div style="display:flex;justify-content:space-between;margin-bottom:5px"><span style="font-size:12px;color:var(--text2)">Final Oral Defense</span><span style="font-size:11px;font-variant-numeric:tabular-nums;color:var(--blue)">54%</span></div>
            <div class="pbar"><div class="pbar-fill pf-blue" style="width:54%"></div></div>
          </div>
          <div style="font-size:11px;color:var(--text3);margin-top:4px">Based on AY 2025–2026 data</div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><span class="card-title">All-Time Defense Summary</span></div>
        <div class="gap-12" style="margin-top:4px">
          <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--border)">
            <span style="font-size:12px;color:var(--text2)">Pre-Proposal Defense</span>
            <div style="text-align:right"><div style="font-size:13px;font-variant-numeric:tabular-nums;font-weight:700;color:var(--green)">265</div><div style="font-size:10px;color:var(--text3)">passed · 81% rate</div></div>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--border)">
            <span style="font-size:12px;color:var(--text2)">Title Defense</span>
            <div style="text-align:right"><div style="font-size:13px;font-variant-numeric:tabular-nums;font-weight:700;color:var(--green)">239</div><div style="font-size:10px;color:var(--text3)">passed · 90% rate</div></div>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--border)">
            <span style="font-size:12px;color:var(--text2)">Pre-Oral Defense</span>
            <div style="text-align:right"><div style="font-size:13px;font-variant-numeric:tabular-nums;font-weight:700;color:var(--green)">178</div><div style="font-size:10px;color:var(--text3)">passed · 74% rate</div></div>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0">
            <span style="font-size:12px;color:var(--text2)">Final Oral Defense</span>
            <div style="text-align:right"><div style="font-size:13px;font-variant-numeric:tabular-nums;font-weight:700;color:var(--green)">149</div><div style="font-size:10px;color:var(--text3)">passed · 84% rate</div></div>
          </div>
        </div>
      </div>
    </div>

  </div>
  </div>
</div>
@include('capsa.partials.skeleton-toggle')

<script>
document.getElementById('defense-tabs').addEventListener('click', function(e) {
  const btn = e.target.closest('.tab-btn');
  if (!btn) return;
  this.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('#panel-ppd,#panel-td,#panel-pod,#panel-fod').forEach(p => p.classList.remove('active'));
  document.getElementById('panel-' + btn.dataset.tab).classList.add('active');
});

document.getElementById('cat-tabs').addEventListener('click', function(e) {
  const btn = e.target.closest('.tab-btn');
  if (!btn) return;
  this.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('#panel-cat-yearly,#panel-cat-alltime').forEach(p => p.classList.remove('active'));
  document.getElementById('panel-cat-' + btn.dataset.cattab).classList.add('active');
});


/* Mobile sidebar toggle */
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() {
  if (!sidebarEl) return;
  sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar();
}
function openSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.add('mobile-open');
  sidebarOverlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.remove('mobile-open');
  sidebarOverlay.classList.remove('open');
  document.body.style.overflow = '';
}
</script>

</body>
</html>