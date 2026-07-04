<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Resources - CapsTRACK</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/styles.css')
</head>
<body>

@include('student.partials.sidebar')

<div class="main">
  <div class="toast" id="toast"></div>
  <div class="topbar">
    <div class="page-title">Resources</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>

  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'resources'])

    <!-- STAT ROW -->
    <div class="grid-4" style="margin-bottom:18px">
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--accent3)" id="stat-total">7</div>
        <div class="stat-l">Total Files</div>
      </div>
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--blue)" id="stat-new">2</div>
        <div class="stat-l">New This Week</div>
      </div>
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--green)" id="stat-dl">3</div>
        <div class="stat-l">Downloaded</div>
      </div>
      <div class="stat-chip">
        <div class="stat-n" style="color:var(--purple)" id="stat-cats">5</div>
        <div class="stat-l">Categories</div>
      </div>
    </div>

    <!-- TOOLBAR -->
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;flex-wrap:wrap">
      <div class="ftabs" id="ftabs">
        <button class="ftab active" onclick="setFilter('all',this)">All</button>
        <button class="ftab" onclick="setFilter('pre-proposal',this)">Pre-Proposal</button>
        <button class="ftab" onclick="setFilter('ch1-3',this)">Ch. 1–3</button>
        <button class="ftab" onclick="setFilter('ch4-5',this)">Ch. 4–5</button>
        <button class="ftab" onclick="setFilter('final',this)">Final MS</button>
        <button class="ftab" onclick="setFilter('presentation',this)">Presentation</button>
        <button class="ftab" onclick="setFilter('lesson',this)">Lessons</button>
        <button class="ftab" onclick="setFilter('forms',this)">Forms</button>
      </div>
      <div style="display:flex;gap:8px;align-items:center">
        <div style="position:relative">
          <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:13px;height:13px;stroke:var(--text3);fill:none;stroke-width:2;pointer-events:none" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input class="input" id="q" placeholder="Search resources…" style="padding-left:32px;width:210px;font-size:12px" oninput="render()"/>
        </div>
        <select class="input" id="srt" style="width:145px;font-size:12px" onchange="render()">
          <option value="newest">Newest First</option>
          <option value="oldest">Oldest First</option>
          <option value="az">Name A–Z</option>
          <option value="popular">Most Downloaded</option>
        </select>
      </div>
    </div>

    <!-- LIST -->
    <div id="res-list" class="gap-12"></div>

  </div>
</div>

</body>
</html>
