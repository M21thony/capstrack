<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Defenses - CapsTRACK</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
@vite(['resources/css/defenses-page.css', 'resources/js/defenses/defenses.js'])
  @include('partials.render-whole')
</head>
<body>

@include('capsa._sidebar')
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Defenses</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  {{-- Drawn by resources/js/defenses/defenses.js from the JSON below. A team
       moves left to right along four steps - Panel not final, Not ready,
       Ready to schedule, Scheduled - for the stage in view. --}}
  <div class="dp" id="dp"
       data-state="{{ json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
       data-urls="{{ json_encode([
           'self' => route('capsa.defenses'),
           'board' => route('api.admin.panel_assignment.board'),
           'store' => route('api.admin.panel_assignment.store', '__ID__'),
           'bulk' => route('api.admin.panel_assignment.store.bulk'),
           'finalize' => route('api.admin.panel_assignment.finalize'),
           'replace' => route('api.admin.panel_assignment.replacements.store', '__ID__'),
           'away' => route('api.admin.panel_assignment.away.store'),
           'returnee' => route('api.admin.panel_assignment.returnee.update', '__ID__'),
           'capa' => route('api.admin.panel_assignment.capa.store', '__ID__'),
           'window' => route('api.admin.panel_assignment.windows.update', '__STAGE__'),
           'drafts' => route('api.admin.panel_assignment.schedule-drafts.store'),
           'schedules' => route('api.admin.panel_assignment.schedules.update'),
           'schedule' => route('api.admin.panel_assignment.schedule.update', '__ID__'),
           'rubrics' => route('capsa.rubric_management'),
       ], JSON_UNESCAPED_SLASHES) }}">

    <div class="mini" id="mini" aria-label="Quick controls" inert></div>

    <main class="content">
      <div class="head">
        <div>
          <h1>Defenses</h1>
          <p>Give each team its panel, then schedule its defenses. Your sections: <b>{{ $state['sections'] ? implode(', ', $state['sections']) : 'All sections' }}</b>. Other sections' bookings are checked too, so no room or panelist is booked twice.</p>
        </div>
        <div class="head-acts">
          <button type="button" class="keys-btn" data-act="keys" aria-label="Keyboard shortcuts" title="Keyboard shortcuts (?)"><kbd>?</kbd></button>
          {{-- Every defense for the stage in view, in the department's spreadsheet
               layout (App\Support\DefenseScheduleWorkbook). Kept in step with the
               stage in JS, since the stage switches without a reload. --}}
          <a class="btn ghost" id="exportBtn" href="{{ route('capsa.defenses', ['defense_type' => $state['stage'], 'export' => 'xlsx']) }}" download><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>Export to Excel</a>
          <button type="button" class="btn gold" data-act="generate" id="genBtn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l1.9 5.8L20 10l-6.1 1.2L12 17l-1.9-5.8L4 10l6.1-1.2z"/></svg>Generate schedule</button>
        </div>
      </div>

      <div class="stagebar">
        <div class="stages" role="tablist" aria-label="Defense stage" id="stages"></div>
        <div class="window-chip" id="windowChip"></div>
      </div>

      {{-- Only a warning: scheduling goes ahead, but nobody can grade this stage
           until a rubric exists. Printed only when this stage has none. --}}
      <div class="banner gold" id="rubricWarn" role="status" @if ($state['has_rubric']) hidden @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
        <span class="txt" id="rubricWarnText">@unless ($state['has_rubric'])No active grading rubric for {{ $state['stage'] }}@if ($state['rubric_missing_programs']) for {{ implode(', ', $state['rubric_missing_programs']) }}@endif yet. Panelists can't grade these defenses until one is set up in <a href="{{ route('capsa.rubric_management') }}">Grading Rubrics</a>.@endunless</span>
      </div>

      <div class="pipe" role="tablist" aria-label="Where each team is" id="pipe"></div>

      <section class="card board" aria-labelledby="boardTitle">
        <h2 class="sr" id="boardTitle">Teams</h2>
        <div class="tools" id="tools"></div>
        <div class="selbar" id="selbar" hidden></div>
        <div id="boardBody" data-export="Defenses"></div>
      </section>
    </main>

    <dialog class="dlg" id="dlg" aria-labelledby="dlgTitle"></dialog>
    <button type="button" class="totop" id="totop" data-act="top" aria-label="Back to the top (T)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>Top</button>
  </div>
</div>

<script nonce="{{ Vite::cspNonce() }}">
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() { if (sidebarEl) { sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar(); } }
function openSidebar() { if (!sidebarEl || !sidebarOverlay) return; sidebarEl.classList.add('mobile-open'); sidebarOverlay.classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeSidebar() { if (!sidebarEl || !sidebarOverlay) return; sidebarEl.classList.remove('mobile-open'); sidebarOverlay.classList.remove('open'); document.body.style.overflow = ''; }
</script>
</body>
</html>
