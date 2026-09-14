<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Panel Assignment - CapsTRACK</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
@vite(['resources/css/defenses.css', 'resources/js/defenses/panel-assignment.js'])
</head>
<body>

@include('capsa._sidebar')
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Panel Assignment</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  {{-- Drawn by resources/js/defenses/panel-assignment.js from the JSON below. --}}
  <div class="dx" id="dx-panel"
       data-state="{{ json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
       data-urls="{{ json_encode([
           'self' => route('capsa.panel_assignment'),
           'bulk' => route('api.admin.panel_assignment.store.bulk'),
           'store' => route('api.admin.panel_assignment.store', '__ID__'),
       ], JSON_UNESCAPED_SLASHES) }}">

    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
      <symbol id="dx-i-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></symbol>
      <symbol id="dx-i-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></symbol>
      <symbol id="dx-i-more" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></symbol>
      <symbol id="dx-i-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 6 6 18M6 6l12 12"/></symbol>
      <symbol id="dx-i-info" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></symbol>
      <symbol id="dx-i-users" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.6 3.4-5.5 6.5-5.5s5.7 1.9 6.5 5.5"/><circle cx="17" cy="9" r="2.6"/><path d="M16.5 14.6c2.6.2 4.4 2 5 5.4"/></symbol>
      <symbol id="dx-i-badge" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c3.5 2.5 8.5 2.5 12 0v-5"/></symbol>
      <symbol id="dx-i-receipt" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 2h14v20l-3-2-2 2-2-2-2 2-2-2-3 2Z"/><path d="M9 7h6M9 11h6M9 15h4"/></symbol>
      <symbol id="dx-i-trash" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></symbol>
    </svg>

    <header class="dx-head">
      <div><h1>Panel Assignment</h1><p>Give each team its defense panel. Dates are set on the Defense Scheduling page.</p></div>
    </header>

    <section class="dx-rules" aria-label="Rules">
      <div class="dx-rule"><div class="dx-rule-ico"><svg><use href="#dx-i-users"/></svg></div>
        <div><h3>Before a panel</h3><ul><li>The team has its adviser, consultant and grammarian.</li><li>The team's own adviser and consultant are left out of its panel list.</li></ul></div></div>
      <div class="dx-rule"><div class="dx-rule-ico"><svg><use href="#dx-i-badge"/></svg></div>
        <div><h3>Panel Chair</h3><ul><li>Needs a Master's degree or higher.</li><li>A Bachelor's degree can still sit as a member.</li></ul></div></div>
      <div class="dx-rule"><div class="dx-rule-ico"><svg><use href="#dx-i-receipt"/></svg></div>
        <div><h3>Who gets scheduled</h3><ul><li>Title, Pre Oral and Final Oral Defense: fees verified and requirements done.</li><li>Pre-proposal Defense has no fee, only the requirements.</li></ul></div></div>
    </section>

    <div class="dx-main">
      <section class="dx-board" aria-label="Teams">
        <div class="dx-filters">
          <label class="dx-field dx-search"><svg><use href="#dx-i-search"/></svg><input id="f-search" type="search" placeholder="Search team or capstone title" aria-label="Search team or capstone title"></label>
          <select class="dx-field" id="f-stage" aria-label="Defense stage">
            @foreach ($state['stages'] as $stage)
              <option>{{ $stage }}</option>
            @endforeach
          </select>
          <select class="dx-field" id="f-section" aria-label="Section"><option value="">All sections</option></select>
          <select class="dx-field" id="f-status" aria-label="Status"><option value="">All statuses</option><option value="notready">Missing experts</option><option value="unassigned">Unassigned</option><option value="assigned">Assigned</option><option value="scheduled">Scheduled</option><option value="passed">Passed</option></select>
          <select class="dx-field" id="f-paid" aria-label="Fees"><option value="">All fees</option><option value="paid">Fees verified</option><option value="unpaid">Fees not verified</option></select>
          <span class="dx-grow"></span>
          <button class="dx-btn dx-btn-gold" id="open-assign"><svg><use href="#dx-i-plus"/></svg>Assign Panel</button>
        </div>
        <div class="dx-selbar" id="selbar" hidden>
          <strong id="sel-count"></strong><span id="sel-chips" style="display:contents"></span>
          <span class="dx-grow"></span>
          <button class="dx-btn dx-btn-sm" id="sel-clear">Clear</button>
          <button class="dx-btn dx-btn-sm dx-btn-gold" id="sel-assign"><svg><use href="#dx-i-users"/></svg>Assign panel to selected</button>
        </div>
        <div class="dx-table-wrap">
          <table>
            <colgroup><col style="width:34px"><col style="width:14%"><col style="width:17%"><col style="width:18%"><col style="width:18%"><col style="width:14%"><col style="width:140px"></colgroup>
            <thead><tr><th><input type="checkbox" id="pick-all" aria-label="Select every team that can take a panel"></th><th>Team</th><th>Capstone title</th><th>Team experts</th><th>Panel</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead>
            <tbody id="rows"></tbody>
          </table>
        </div>
        <div class="dx-foot"><span id="showing"></span></div>
      </section>
    </div>
  </div>
</div>

<dialog id="d-assign" class="dx-dialog dx-wide" aria-labelledby="d-assign-t">
  <div class="dx-dlg">
    <div class="dx-dlg-head"><div class="dx-grow"><h2 id="d-assign-t">Assign panel</h2><p id="a-sub"></p></div><button class="dx-icon-btn" data-close aria-label="Close"><svg><use href="#dx-i-x"/></svg></button></div>
    <div class="dx-dlg-body">
      <div class="dx-info dx-gold" id="a-prop" hidden><svg><use href="#dx-i-info"/></svg><span>A Pre-proposal Defense panel is the team's panel until the end of Capstone 2: it is copied to Title, Pre Oral and Final Oral Defense, and a redefense keeps it. Changing one of those later only changes that stage.</span></div>
      <div class="dx-assign">
        <div>
          <div class="dx-col-title"><span>Teams (<span id="a-team-count">0</span>)</span></div>
          <label class="dx-field dx-search" style="margin-bottom:8px"><svg><use href="#dx-i-search"/></svg><input id="a-team-search" type="search" placeholder="Search teams" aria-label="Search teams"></label>
          <div class="dx-list" id="a-teams" style="max-height:440px"></div>
        </div>
        <div>
          <div class="dx-col-title"><span>Panel members</span><span class="dx-muted" id="a-hidden"></span></div>
          <label class="dx-field dx-search" style="margin-bottom:8px"><svg><use href="#dx-i-search"/></svg><input id="a-expert-search" type="search" placeholder="Search name or expertise" aria-label="Search experts"></label>
          <div id="a-groups"></div>
        </div>
      </div>
    </div>
    <div class="dx-dlg-foot"><span class="dx-muted dx-grow" id="a-summary"></span><button class="dx-btn" data-close>Cancel</button><button class="dx-btn dx-btn-gold" id="a-save">Save panel</button></div>
  </div>
</dialog>

<div class="dx-menu" id="row-menu" popover role="menu"><button role="menuitem" class="dx-danger" id="menu-remove"><svg><use href="#dx-i-trash"/></svg>Remove panel</button></div>

<script>
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() { if (sidebarEl) { sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar(); } }
function openSidebar() { if (!sidebarEl || !sidebarOverlay) return; sidebarEl.classList.add('mobile-open'); sidebarOverlay.classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeSidebar() { if (!sidebarEl || !sidebarOverlay) return; sidebarEl.classList.remove('mobile-open'); sidebarOverlay.classList.remove('open'); document.body.style.overflow = ''; }
</script>
</body>
</html>
