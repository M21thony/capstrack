<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Defense Scheduling - CapsTRACK</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
@vite(['resources/css/defenses.css', 'resources/js/defenses/defense-scheduling.js'])
</head>
<body>

@include('capsa._sidebar')
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Defense Scheduling</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  {{-- Drawn by resources/js/defenses/defense-scheduling.js from the JSON below. --}}
  <div class="dx" id="dx-sched" style="grid-template-rows:auto auto 1fr"
       data-stage="{{ $state['stage'] }}"
       data-state="{{ json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
       data-urls="{{ json_encode([
           'self' => route('capsa.defense_scheduling'),
           'board' => route('api.admin.panel_assignment.board'),
           'window' => route('api.admin.panel_assignment.windows.update', '__STAGE__'),
           'drafts' => route('api.admin.panel_assignment.schedule-drafts.store'),
           'schedules' => route('api.admin.panel_assignment.schedules.update'),
           'schedule' => route('api.admin.panel_assignment.schedule.update', '__ID__'),
       ], JSON_UNESCAPED_SLASHES) }}">

    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
      <symbol id="dx-i-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></symbol>
      <symbol id="dx-i-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 6 6 18M6 6l12 12"/></symbol>
      <symbol id="dx-i-info" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></symbol>
      <symbol id="dx-i-cal" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></symbol>
      <symbol id="dx-i-spark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M9 15l2 2 4-4"/></symbol>
      <symbol id="dx-i-edit" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></symbol>
    </svg>

    <header class="dx-head">
      <div><h1>Defense Scheduling</h1><p>You schedule your own sections. The schedule maker also checks the other sections' defense schedules, so no room or panelist is ever booked twice.</p></div>
    </header>

    <div class="dx-sched-top">
      @include('capsa.partials.defense-stage-tabs', ['sched' => true])
      <div class="dx-filters" style="padding:0;border:0">
        <button class="dx-btn" id="open-window"><svg><use href="#dx-i-cal"/></svg>Defense window: <span id="window-label"></span></button>
        <span class="dx-scope">Your sections: <b id="my-sections"></b></span>
        <span class="dx-grow"></span>
        <button class="dx-btn dx-btn-gold" id="open-generate"><svg><use href="#dx-i-spark"/></svg>Generate schedule</button>
      </div>
      {{-- Only a warning: scheduling goes ahead, but nobody can grade this
           stage until a rubric exists. Redrawn when the stage changes. The
           text prints only when this stage actually has none - a page with
           a rubric must not carry the warning at all, hidden or not. --}}
      <div class="dx-info dx-gold" id="rubric-warn" role="status" data-rubric-url="{{ route('capsa.rubric_management') }}" @if ($state['has_rubric']) hidden @endif><svg><use href="#dx-i-info"/></svg><span id="rubric-warn-text">@unless ($state['has_rubric']) No active grading rubric for {{ $state['stage'] }} yet. Panelists can't grade these defenses until one is set up in <a href="{{ route('capsa.rubric_management') }}">Grading Rubrics</a>. @endunless</span></div>
    </div>

    <div class="dx-sched-main">
      <section class="dx-board" aria-label="Teams by scheduling status">
        <div class="dx-tabs" role="tablist">
          <button role="tab" data-tab="ready">Can be scheduled <span class="dx-n dx-ok" id="n-ready"></span></button>
          <button role="tab" data-tab="blocked">Can't be scheduled <span class="dx-n dx-no" id="n-blocked"></span></button>
          <button role="tab" data-tab="scheduled">Scheduled <span class="dx-n dx-wait" id="n-scheduled"></span></button>
        </div>
        <div class="dx-filters">
          <span class="dx-muted" id="tab-hint"></span><span class="dx-grow"></span>
          <select class="dx-field" id="reason-filter" aria-label="Filter by what's missing" hidden>
            <option value="">Everything missing</option><option value="experts">Missing experts</option><option value="prev">Previous stage not passed</option><option value="req">Requirements not done</option><option value="panel">No panel</option><option value="fees">Fees not verified</option>
          </select>
          <label class="dx-toggle" id="others-toggle" hidden><input type="checkbox" id="s-others" checked>Show other sections' defense schedules</label>
          <label class="dx-field dx-search"><svg><use href="#dx-i-search"/></svg><input id="s-search" type="search" placeholder="Search team" aria-label="Search teams"></label>
        </div>
        <div class="dx-table-wrap"><table id="sched-table"></table></div>
      </section>
    </div>
  </div>
</div>

<dialog id="d-window" class="dx-dialog" aria-labelledby="d-window-t">
  <div class="dx-dlg">
    <div class="dx-dlg-head"><div class="dx-grow"><h2 id="d-window-t">Defense window</h2><p id="w-sub"></p></div><button class="dx-icon-btn" data-close aria-label="Close"><svg><use href="#dx-i-x"/></svg></button></div>
    <div class="dx-dlg-body dx-stack">
      <div class="dx-two">
        <div><label class="dx-lbl" for="w-start">First day</label><input class="dx-field" type="date" id="w-start" style="width:100%"></div>
        <div><label class="dx-lbl" for="w-end">Last day</label><input class="dx-field" type="date" id="w-end" style="width:100%"></div>
      </div>
      <div>
        <label class="dx-lbl">Length of each defense</label>
        <div class="dx-seg" id="w-length" role="group" aria-label="Length of each defense">
          <button type="button" class="dx-seg-btn" data-minutes="60">1 hour</button>
          <button type="button" class="dx-seg-btn" data-minutes="90">1½ hours</button>
          <button type="button" class="dx-seg-btn" data-minutes="120">2 hours</button>
          <button type="button" class="dx-seg-btn" data-custom>Custom</button>
        </div>
        <div id="w-custom" hidden style="margin-top:8px">
          <label class="dx-lbl" for="w-minutes">Minutes per defense ({{ \App\Support\DefenseBoard::MIN_MINUTES }}–{{ \App\Support\DefenseBoard::MAX_MINUTES }})</label>
          <input class="dx-field" type="number" id="w-minutes" min="{{ \App\Support\DefenseBoard::MIN_MINUTES }}" max="{{ \App\Support\DefenseBoard::MAX_MINUTES }}" step="5" style="width:140px">
        </div>
      </div>
      <div class="dx-muted">Weekdays, 8 AM – 5 PM, lunch 12–1 kept free. Saving only stores the dates; nobody is scheduled until you use Generate schedule.</div>
    </div>
    <div class="dx-dlg-foot"><span class="dx-grow"></span><button class="dx-btn" data-close>Cancel</button><button class="dx-btn dx-btn-gold" id="w-save">Save window</button></div>
  </div>
</dialog>

<dialog id="d-generate" class="dx-dialog dx-wide" aria-labelledby="d-generate-t">
  <div class="dx-dlg">
    <div class="dx-dlg-head"><div class="dx-grow"><h2 id="d-generate-t">Generate schedule</h2><p id="g-sub"></p></div><button class="dx-icon-btn" data-close aria-label="Close"><svg><use href="#dx-i-x"/></svg></button></div>
    <div class="dx-dlg-body" id="g-body"></div>
    <div class="dx-dlg-foot"><span class="dx-muted dx-grow" id="g-summary"></span><button class="dx-btn" data-close>Cancel</button><button class="dx-btn dx-btn-gold" id="g-save">Save schedule</button></div>
  </div>
</dialog>

{{-- Schedule one team, for the stage chosen here. The stage is segmented
     buttons rather than a dropdown, and the slot is a grid of the window's
     days and blocks with taken ones greyed out - so a team is given a date
     without leaving the page or hunting through a select. --}}
<dialog id="d-schedule" class="dx-dialog dx-wide" aria-labelledby="d-schedule-t">
  <div class="dx-dlg">
    <div class="dx-dlg-head"><div class="dx-grow"><h2 id="d-schedule-t">Schedule a team</h2><p id="sc-sub"></p></div><button class="dx-icon-btn" data-close aria-label="Close"><svg><use href="#dx-i-x"/></svg></button></div>
    <div class="dx-dlg-body dx-stack">
      <div>
        <label class="dx-lbl">Defense type</label>
        <div class="dx-seg" id="sc-stages" role="group" aria-label="Defense type"></div>
      </div>
      <div id="sc-window-note" class="dx-info dx-gold" hidden><svg><use href="#dx-i-info"/></svg><span id="sc-window-note-text"></span></div>
      <div>
        <label class="dx-lbl">Venue</label>
        <div class="dx-seg dx-wrap" id="sc-venues" role="group" aria-label="Venue"></div>
      </div>
      <div>
        <label class="dx-lbl">Pick a slot</label>
        <div class="dx-days" id="sc-days"></div>
      </div>
      <div id="sc-conflict" class="dx-info dx-red" hidden><svg><use href="#dx-i-info"/></svg><span id="sc-conflict-text"></span></div>
    </div>
    <div class="dx-dlg-foot"><span class="dx-muted dx-grow" id="sc-summary"></span><button class="dx-btn" data-close>Cancel</button><button class="dx-btn dx-btn-gold" id="sc-save" disabled>Save schedule</button></div>
  </div>
</dialog>

<dialog id="d-override" class="dx-dialog" aria-labelledby="d-override-t">
  <div class="dx-dlg">
    <div class="dx-dlg-head"><div class="dx-grow"><h2 id="d-override-t">Change schedule</h2><p id="o-sub"></p></div><button class="dx-icon-btn" data-close aria-label="Close"><svg><use href="#dx-i-x"/></svg></button></div>
    <div class="dx-dlg-body dx-stack">
      <div class="dx-info dx-red" id="o-conflict" hidden><svg><use href="#dx-i-info"/></svg><span id="o-conflict-text"></span></div>
      <div><label class="dx-lbl" for="o-date">Date</label><input class="dx-field" type="date" id="o-date" style="width:100%"></div>
      <div class="dx-two">
        <div><label class="dx-lbl" for="o-time">Time</label><select class="dx-field" id="o-time" style="width:100%"></select></div>
        <div><label class="dx-lbl" for="o-venue">Venue</label><select class="dx-field" id="o-venue" style="width:100%"></select></div>
      </div>
    </div>
    <div class="dx-dlg-foot"><span class="dx-grow"></span><button class="dx-btn" data-close>Cancel</button><button class="dx-btn dx-btn-gold" id="o-save">Save</button></div>
  </div>
</dialog>

<script>
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() { if (sidebarEl) { sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar(); } }
function openSidebar() { if (!sidebarEl || !sidebarOverlay) return; sidebarEl.classList.add('mobile-open'); sidebarOverlay.classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeSidebar() { if (!sidebarEl || !sidebarOverlay) return; sidebarEl.classList.remove('mobile-open'); sidebarOverlay.classList.remove('open'); document.body.style.overflow = ''; }
</script>
</body>
</html>
