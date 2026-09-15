<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>CapsTRACK – Expert Portal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/schedule.css') }}" />
@include('partials.skeleton-head', ['skReveal' => 'block'])
</head>
<body>
@include('instructor.partials.sidebar', ['currentPage' => 'schedule'])
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Consultations</div>
    <div class="topbar-actions">
      @include('instructor.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="sched-layout">
      <div>
        <div class="cal-card">
          <div class="sk" style="width:220px;height:20px;border-radius:4px;margin-bottom:16px"></div>
          <div class="sk" style="height:340px;border-radius:12px"></div>
        </div>
      </div>
      <div class="side-col">
        <div class="form-card">
          <div class="sk" style="width:130px;height:16px;border-radius:4px;margin-bottom:14px"></div>
          <div class="sk" style="height:60px;border-radius:8px"></div>
        </div>
        <div class="form-card" style="padding:16px">
          <div class="sk" style="width:170px;height:16px;border-radius:4px;margin-bottom:12px"></div>
          <div class="sk" style="height:38px;border-radius:8px;margin-bottom:8px"></div>
          <div class="sk" style="height:38px;border-radius:8px"></div>
        </div>
      </div>
    </div>
  </div>

  <div id="real-content" style="display:none">
  <div class="content">

    <div class="sched-layout">

      <!-- LEFT: Calendar -->
      <div>
        <div class="cal-card">
          <div class="cal-header">
            <div class="cal-title-group">
              <div class="cal-title">Availability</div>
              <div class="cal-month-label" id="cal-month-label">Occurrences of your weekly slots</div>
            </div>
            <div class="cal-controls">
              <button class="cal-nav-btn" onclick="prevMonth()">
                <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
              </button>
              <div class="cal-month-display" id="cal-month-display">–</div>
              <button class="cal-nav-btn" onclick="nextMonth()">
                <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
              </button>
              <div class="mode-toggle">
                <button class="mode-btn view-btn is-on" id="btn-view-month" onclick="setCalView('month')">Month</button>
                <button class="mode-btn view-btn" id="btn-view-week" onclick="setCalView('week')">Week</button>
                {{-- Set a term's worth of hours in one go, instead of one
                     slot at a time through the form on the right. --}}
                <button class="mode-btn" onclick="openAvail()">Set availability</button>              </div>
            </div>
          </div>

          <!-- Calendar Grid -->
          <div class="cal-grid" id="cal-grid">
            <!-- Rendered by JS -->
          </div>

          {{-- The same slots against a time axis. A month answers "which
               day"; only a week answers "am I free Thursday afternoon",
               which is the question this page actually gets asked. It reads
               the same slots[] the month grid does, so nothing here has its
               own idea of what is booked. --}}
          <div class="wk" id="week-grid" hidden>
            <div class="wk-head" id="wk-head"></div>
            <div class="wk-body" id="wk-body"></div>
          </div>
        </div>

        <!-- Legend + Guide -->
        <!-- Two sibling cards, not one card wrapping two more. The nesting
             made the guidance look like a subsection of the legend, and the
             legend like a heading for it. They are separate things. -->
        <div class="legend-row">
          <div class="legend-section">
            <div class="legend-section-title">Legend</div>
            <div class="legend-grid">
              <div class="legend-item"><div class="legend-swatch sw-consult"></div>Consultation</div>
              <div class="legend-item"><div class="legend-swatch sw-booked"></div>Booked</div>
              <div class="legend-item"><div class="legend-swatch sw-defense"></div>Defense</div>
              <div class="legend-item"><div class="legend-swatch sw-today"></div>Today</div>            </div>
          </div>

          <div class="legend-section">
            <div class="legend-section-title">How weekly slots work</div>
            <ul class="guide-list">
              <li>A slot repeats on its weekday until you delete it.</li>              <li>A slot with a booking can't be moved &mdash; cancel the booking first.</li>
              <li>Editing a repeating slot asks whether to change one week or all of them.</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- RIGHT: Side column -->
      <div class="side-col">

        <!-- Selected Date: Slots + Add Form (always visible, merged View+Add) -->
        <div class="form-card" id="add-slot-form">
          <div class="form-card-title">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Selected Date
          </div>

          <div class="selected-date-chip" id="selected-date-chip" style="display:none">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span id="selected-date-text">No date selected</span>
            
          </div>

          <div id="no-date-hint" style="font-size:12px;color:var(--text3);margin-bottom:12px;padding:10px;background:var(--bg3);border-radius:8px;border:1px dashed var(--border);text-align:center;">
            ← Click a date on the calendar to see its slots and add a new one
          </div>

          <!-- Existing slots for the selected date — lets you view/toggle/remove
               them right here, in the same panel used to add new ones. -->
          <!-- Every row below is a weekly rule, not an entry on this date.
               Said once here so the modal that asks is not the first the
               adviser hears of it. -->
          <div class="recur-banner" id="recur-banner" style="display:none">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 2.1 21 6l-4 3.9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/>
              <path d="M7 21.9 3 18l4-3.9"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>
            </svg>
            <span id="recur-banner-text"></span>
          </div>

          <div id="selected-date-slots" style="display:none;margin-bottom:12px;">
            <div style="font-size:11px;color:var(--text3);margin-bottom:6px;font-weight:600;">Existing slots on this date</div>
            <div id="selected-date-slots-list" style="display:flex;flex-direction:column;gap:6px;"></div>
          </div>

          <!-- The sample opens with what adding one MEANS, not with two time
               fields. The form is one tap behind it. -->
          <button type="button" class="add-slot-lead" id="add-slot-lead"
                  onclick="openAddSlot()" style="display:none">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
            <span id="add-slot-lead-text">Add a weekly slot</span>
          </button>

          <div id="add-slot-fields" style="display:none">
            {{-- Two decisions carry this form: when it starts and how long it
                 runs. Type, teams and repeat have a sensible answer already,
                 so they read as a sentence you skim past rather than three
                 more fields at the same volume - and they are one tap away
                 when a slot is not the usual kind. --}}
            <div class="f-group">
              <label class="f-label" for="f-start">Starts</label>
              <input class="f-input" type="time" id="f-start" value="09:00"
                     style="max-width:130px"
                     onchange="onStartChanged()" oninput="onStartChanged()"/>
            </div>

            <div class="f-group">
              <label class="f-label">Runs for</label>
              <div class="dur-row">
                {{-- Short labels so all four sit on one line; "2 hours" spelt
                     out wrapped alone onto a second row. --}}
                <button type="button" class="dur-chip" data-mins="30" onclick="setDuration(30)" title="30 minutes">30m</button>
                <button type="button" class="dur-chip is-on" data-mins="60" onclick="setDuration(60)" title="1 hour">1h</button>
                <button type="button" class="dur-chip" data-mins="90" onclick="setDuration(90)" title="1 hour 30 minutes">1&frac12;h</button>
                <button type="button" class="dur-chip" data-mins="120" onclick="setDuration(120)" title="2 hours">2h</button>
              </div>
            </div>

            <p class="slot-derived" id="slot-derived"></p>

            <div id="time-overlap-warning" style="display:none">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
              </svg>
              <span id="time-overlap-text">This time overlaps with an existing slot.</span>
            </div>

            <button type="button" class="slot-more-toggle" id="slot-more-toggle"
                    aria-expanded="false" aria-controls="slot-more" onclick="toggleSlotMore()">
              Change type, teams or repeat
              <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </button>

            <div id="slot-more" hidden>
              <div class="f-group">
                <label class="f-label" for="f-end">Ends</label>
                <input class="f-input" type="time" id="f-end" value="10:00"
                       style="max-width:130px"
                       onchange="onEndChanged()" oninput="onEndChanged()"/>
              </div>

              <div class="f-group">
                <label class="f-label" for="f-type">Slot Type</label>
                <select class="f-input" id="f-type" onchange="updateSlotDerived()">
                  <option value="consult">Consultation</option>
                  <option value="defense">Defense</option>
                </select>
              </div>

              <div class="f-group">
                <label class="f-label" for="f-max">Max Teams per Slot</label>
                <input class="f-input" type="number" id="f-max" value="1" min="1" max="5" onchange="updateSlotDerived()"/>
              </div>

              <div class="f-group" style="margin-bottom:0" id="f-recur-group">
                <label class="f-label" for="f-recur">Recurrence</label>
                <select class="f-input" id="f-recur" onchange="updateSlotDerived()">
                  <option value="weekly" selected>Every week (same day)</option>
                  <option value="once">One-time only</option>
                  <option value="biweekly">Every 2 weeks</option>
                </select>
                <div id="f-recur-locked-note" style="display:none;font-size:11px;color:var(--text3);margin-top:4px">Switching to a single date? Pick the date above.</div>
              </div>
            </div>

            <div id="edit-mode-banner" style="display:none;padding:8px 12px;margin-bottom:11px;background:var(--accent-glow,rgba(0,229,255,0.08));border:1px solid var(--accent,#00e5ff);border-radius:8px;color:var(--accent,#00e5ff);font-size:12px;font-weight:600">
              Editing existing slot
            </div>

            <div class="form-actions">
              <button class="btn btn-ghost" onclick="resetForm()" style="flex:none;padding:7px 14px">Cancel</button>
              <button class="btn btn-primary" id="save-slot-btn" onclick="saveSlot()">
                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Save Slot
              </button>
            </div>
            <!-- Slots are pushed to the adviser's calendar on save. Saying so
                 here is the difference between trusting the button and going
                 to check. -->
            <div class="gcal-note">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              Saved slots appear in your Google Calendar
            </div>
          </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="upcoming-card">
          <div class="upcoming-header">
            <div class="upcoming-title">
              <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              Next sessions
            </div>
            <div class="upcoming-count" id="apt-count">0</div>
          </div>
          <div class="upcoming-list" id="upcoming-list">
            <!-- Rendered by JS -->
          </div>
        </div>

        <!-- Slot type quick stats -->
        <div class="form-card" style="padding:16px">
          <div class="form-card-title" style="margin-bottom:12px">
            <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            This month&rsquo;s slots
          </div>
          <div style="display:flex;flex-direction:column;gap:8px" id="slot-stats">
            <!-- Rendered by JS -->
          </div>
        </div>

      </div>
    </div>
  </div>
  </div>
</div>
{{-- The sidebar draws a clickable name that calls openExpertProfile(),
     which is declared in this partial. Without it the trigger was on the
     page and the function was not, so clicking your own name here did
     nothing at all. --}}
@include('instructor.partials.expert-profile')
@include('instructor.partials.skeleton-toggle')

{{-- The expert profile panel is included once for every instructor page by
     the sidebar (capsa._sidebar -> instructor.partials.expert-profile). This
     page used to carry its own older copy of it, which redeclared EP_TAGS_EL
     and EP_INPUT. A repeated const is a SyntaxError, and it took the whole
     script block down with it - which is why nothing on this page ran. --}}
<!-- ─── TOAST ─────────────────────────────────────────────────────────────── -->
<div id="sched-toast" style="
  position:fixed;bottom:24px;right:24px;z-index:9999;
  background:var(--bg2,#1e1e2e);color:var(--text,#e0e0e0);
  padding:12px 18px;border-radius:10px;border-left:4px solid var(--accent,#00e5ff);
  font-size:13px;font-family:'DM Sans',sans-serif;box-shadow:0 4px 24px rgba(0,0,0,.4);
  display:flex;align-items:center;gap:10px;
  opacity:0;transform:translateY(10px);transition:opacity .25s,transform .25s;
  pointer-events:none;">
  <span id="toast-msg"></span>
</div>
<style>
#sched-toast.show{opacity:1;transform:translateY(0);pointer-events:auto}

/* The defaults, as a sentence rather than three more fields at the same
   volume as the two decisions that matter. */
.slot-derived{font-size:11.5px;color:var(--text3);line-height:1.5;
  margin:-4px 0 12px;padding-left:1px}
.slot-derived b{color:var(--text2);font-weight:600}

.slot-more-toggle{display:flex;align-items:center;gap:5px;background:none;border:none;
  padding:0 0 12px;font:600 11.5px 'DM Sans',sans-serif;color:var(--accent3);cursor:pointer}
.slot-more-toggle svg{transition:transform .15s}
.slot-more-toggle.is-open svg{transform:rotate(90deg)}
.slot-more-toggle:hover{color:var(--accent2)}

/* Small and inline. It was a red block in the middle of the form, louder
   than anything it sat between. */
#time-overlap-warning{display:flex;align-items:flex-start;gap:7px;
  background:var(--red-bg);border-radius:7px;padding:7px 9px;margin-bottom:12px;
  font-size:11.5px;line-height:1.4;color:var(--red)}
#time-overlap-warning svg{flex-shrink:0;margin-top:1px}

/* Duration as a tap rather than a typed end time. */
.dur-row{display:flex;gap:5px;flex-wrap:wrap}
.dur-chip{border:1px solid var(--border);background:var(--bg2);color:var(--text2);
  border-radius:20px;padding:5px 11px;font:600 11px/1.3 inherit;cursor:pointer;transition:.15s}
.dur-chip:hover{border-color:var(--accent)}
.dur-chip.is-on{background:var(--accent-light);border-color:var(--accent);color:var(--accent3)}

/* The week view. Hours are rows, days are columns, and a slot is drawn at
   its real position and length - so an empty afternoon looks empty. */

/* [hidden] is display:none from the browser's own stylesheet, which any
   class setting a display loses to - .cal-grid{display:grid} kept the month
   on screen underneath the week. */
[hidden]{display:none!important}
.view-btn.is-on{background:var(--accent-light);border-color:var(--accent);color:var(--accent3)}
/* One scroll container, so the header and the body are the same width and
   their columns cannot drift apart when a scrollbar appears. */
.wk{border:1px solid var(--border);border-radius:12px;background:var(--bg2);
  max-height:560px;overflow-y:auto}
.wk-head{display:grid;grid-template-columns:52px repeat(7,1fr);background:var(--bg3);
  border-bottom:1px solid var(--border);position:sticky;top:0;z-index:2}
.wk-head div{padding:7px 4px;text-align:center;font-size:11px;font-weight:600;color:var(--text2);
  border-left:1px solid var(--border)}
.wk-head div:first-child{border-left:none}
.wk-head div.is-today{background:var(--accent-light);color:var(--accent3)}
.wk-head span{display:block;font-size:15px;font-weight:700;color:var(--text);
  font-variant-numeric:tabular-nums;line-height:1.25}
.wk-head div.is-today span{color:var(--accent3)}
.wk-body{display:grid;grid-template-columns:52px repeat(7,1fr)}
.wk-hours{position:relative}
.wk-hours i{display:block;height:44px;font-style:normal;font-size:10px;color:var(--text3);
  text-align:right;padding:1px 6px 0 0;font-family:inherit;font-variant-numeric:tabular-nums}
.wk-col{position:relative;border-left:1px solid var(--border);cursor:pointer;
  background:repeating-linear-gradient(var(--bg2) 0 43px, var(--border) 43px 44px)}
.wk-col:hover{background:repeating-linear-gradient(var(--bg3) 0 43px, var(--border) 43px 44px)}
.wk-col.is-selected{background:repeating-linear-gradient(var(--accent-light) 0 43px, var(--border) 43px 44px)}
.wk-slot{position:absolute;left:2px;right:2px;border-radius:5px;padding:3px 5px;overflow:hidden;
  font-size:10px;line-height:1.25;cursor:pointer;border-left:3px solid;background:var(--bg2)}
.wk-slot b{display:block;font-weight:700;color:var(--text);white-space:nowrap;
  overflow:hidden;text-overflow:ellipsis}
.wk-slot span{color:var(--text3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block}
.wk-slot.is-booked{background:var(--navy-dark,#1e2028);border-left-color:var(--accent)}
.wk-slot.is-booked b{color:#fff}
.wk-slot.is-booked span{color:rgba(255,255,255,.6)}
.wk-slot.is-open{background:var(--accent-light);border-left-color:var(--accent)}
/* A session whose time has passed: still shown, plainly finished. */
.cal-slot-pill.is-ended,.slot-tag.is-ended{background:var(--bg4,#eceef2);color:var(--text3);border-color:var(--border)}
/* A team asked to cancel: the expert approves or keeps the booking. */
.slot-tag.is-request{background:var(--amber-bg,#fdf6e9);color:var(--amber,#b45309);border-color:#f0d9a8}
.slot-request{display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;margin-top:6px;padding:7px 9px;border-radius:8px;background:var(--amber-bg,#fdf6e9);border:1px solid #f0d9a8;font-size:12px;color:var(--text2)}
.slot-request b{color:var(--amber,#b45309)}
.slot-request .slot-acts{margin-left:auto;display:flex;gap:6px}
.wk-empty{padding:26px 8px;text-align:center;font-size:12px;color:var(--text3);grid-column:1/-1}

/* Setting a term's worth of availability in one go. */
.av-rows{display:flex;flex-direction:column;gap:7px;margin-bottom:14px}
.av-row{display:flex;align-items:center;gap:8px}
.av-day{width:52px;flex-shrink:0;border:1px solid var(--border);background:var(--bg2);
  color:var(--text3);border-radius:20px;padding:5px 0;font:700 10px/1.3 inherit;cursor:pointer;text-align:center}
.av-day.is-on{background:var(--accent-light);border-color:var(--accent);color:var(--accent3)}
.av-row input[type=time]{flex:1;min-width:0;padding:5px 8px;border:1px solid var(--border);
  border-radius:7px;background:var(--bg2);color:var(--text);font:inherit;font-size:12px}
.av-row.is-off input[type=time]{opacity:.35;pointer-events:none}
.av-row em{font-style:normal;font-size:11px;color:var(--text3);flex-shrink:0}
.av-count{background:var(--accent-light);border:1px solid var(--accent);border-radius:8px;
  padding:9px 11px;font-size:12px;color:var(--text2);line-height:1.5}
.av-count b{color:var(--accent3)}

/* Which edit did they mean? A weekly slot is a rule, and rewriting the rule
   when they meant one week is how a whole term's Mondays used to move at
   once. Asked as a modal rather than a browser confirm, so the two outcomes
   can be spelled out instead of compressed into OK and Cancel. */
#scope-ask{position:fixed;inset:0;z-index:120;display:none;align-items:center;
  justify-content:center;background:rgba(15,17,23,.45);padding:20px}
#scope-ask.show{display:flex}
#scope-ask .box{background:var(--bg2);border:1px solid var(--border);border-radius:14px;
  max-width:400px;width:100%;padding:20px;box-shadow:0 18px 44px -18px rgba(0,0,0,.45)}
#scope-ask h3{margin:0 0 6px;font:600 15px 'DM Sans',sans-serif;color:var(--text)}
#scope-ask p{margin:0 0 15px;font-size:12.5px;line-height:1.5;color:var(--text3)}
#scope-ask .opt{display:block;width:100%;text-align:left;background:var(--bg3);
  border:1px solid var(--border);border-radius:9px;padding:10px 12px;margin-bottom:8px;
  cursor:pointer;font:inherit;color:var(--text)}
#scope-ask .opt:hover{border-color:var(--accent);background:var(--accent-light)}
#scope-ask .opt b{display:block;font:600 13px 'DM Sans',sans-serif}
#scope-ask .opt span{display:block;font-size:11.5px;color:var(--text3);margin-top:2px}
#scope-ask .cancel{width:100%;background:none;border:none;padding:6px;cursor:pointer;
  font:500 12.5px 'DM Sans',sans-serif;color:var(--text3)}
#scope-ask .cancel:hover{color:var(--text)}
</style>

<!-- ─── CANCELLATION DECISION ─────────────────────────────────────────────── -->
<div class="slot-modal-overlay" id="cancel-decision-overlay" onclick="if(event.target===this)closeCancellationDecision()">
  <div class="block-confirm" role="dialog" aria-modal="true" aria-labelledby="cd-title">
    <div class="block-confirm-title" id="cd-title">Cancel this consultation?</div>
    <div class="block-confirm-sub" id="cd-sub"></div>
    <div id="cd-reason-wrap" hidden style="margin-bottom:14px">
      <label for="cd-reason" style="display:block;font-size:12px;font-weight:600;color:var(--text2);margin-bottom:6px">Why can't they cancel?</label>
      <textarea id="cd-reason" rows="3" maxlength="500" placeholder="e.g. This is the last session before your defense, so we need to meet."
                style="width:100%;box-sizing:border-box;padding:8px 10px;border:1px solid var(--border);border-radius:8px;font:inherit;font-size:13px;resize:vertical;background:var(--bg2);color:var(--text)"></textarea>
      <div id="cd-error" style="font-size:11.5px;color:var(--red,#dc2626);margin-top:4px"></div>
    </div>
    <div class="block-confirm-actions">
      <button class="btn btn-danger-solid" id="cd-yes" onclick="confirmCancellationDecision()">Yes</button>
      <button class="btn btn-ghost" onclick="closeCancellationDecision()">No</button>
    </div>
  </div>
</div>


<!-- ─── SET AVAILABILITY ──────────────────────────────────────────────────
     Adding slots one at a time is why setting up a term is a chore: nine
     consultations a week is nine trips through the form. Google Calendar's
     answer is that you never make a slot - you say which hours you are free
     and how long a consultation runs, and the slots follow. Same idea here,
     and it needs no new endpoint: a window is just several of the slots the
     add form already posts. -->
<div class="slot-modal-overlay" id="avail-overlay" onclick="if(event.target===this)closeAvail()">
  <div class="slot-modal" style="max-width:440px">
    <div class="slot-modal-header">
      <div style="flex:1;min-width:0">
        <div class="slot-modal-title">Set your weekly availability</div>
        <div class="slot-modal-sub">Repeats every week until you change it</div>
      </div>
      <button class="slot-modal-close" onclick="closeAvail()">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="slot-modal-body">
      <div class="f-group">
        <label class="f-label">Each consultation runs</label>
        <div class="dur-row" id="av-durations">
          <button type="button" class="dur-chip" data-mins="30" onclick="setAvailDuration(30)">30 min</button>
          <button type="button" class="dur-chip is-on" data-mins="60" onclick="setAvailDuration(60)">1 hour</button>
          <button type="button" class="dur-chip" data-mins="90" onclick="setAvailDuration(90)">1&frac12; hours</button>
        </div>
      </div>

      <div class="f-group">
        <label class="f-label">Hours you are free</label>
        <div class="av-rows" id="av-rows"></div>
      </div>

      <div class="av-count" id="av-count"></div>

      <div class="form-actions" style="margin-top:14px">
        <button class="btn btn-primary" id="av-save" onclick="saveAvailability()">Save availability</button>
        <button class="btn btn-ghost" onclick="closeAvail()" style="flex:none;padding:7px 12px">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- ─── EXPERT PROFILE SIDE PANEL ─────────────────────────────────────────── -->
<div id="scope-ask" role="dialog" aria-modal="true" aria-labelledby="scope-ask-title">
  <div class="box">
    <h3 id="scope-ask-title">Change which weeks?</h3>
    <p id="scope-ask-note">This slot repeats every week.</p>
    <button type="button" class="opt" data-scope="occurrence">
      <b id="scope-ask-one">Just this date</b>
      <span>Every other week keeps the time it has now.</span>
    </button>
    <button type="button" class="opt" data-scope="series">
      <b id="scope-ask-all">Every week</b>
      <span>Changes this date and every one after it.</span>
    </button>
    <button type="button" class="cancel" data-scope="">Cancel</button>
  </div>
</div>

<script>
// ══════════════════════════════════════════════════════════════
//  CONSTANTS
// ══════════════════════════════════════════════════════════════
const DAYS        = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const DAY_NAMES   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
const MONTHS      = ['January','February','March','April','May','June','July','August','September','October','November','December'];
// Consultation is the only type a slot is created as. The others stay in the
// lookups so a row saved before that still draws with a name and a colour
// rather than as 'undefined' in grey.
const TYPE_LABELS = { consult:'Consultation', docreview:'Doc Review', defense:'Defense' };
const TYPE_CLASS  = { consult:'type-consult', docreview:'type-docreview', defense:'type-defense' };
const TYPE_COLORS = { consult:'var(--blue)', docreview:'var(--purple)', defense:'var(--amber)' };
const CSRF        = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// ══════════════════════════════════════════════════════════════
//  STATE
// ══════════════════════════════════════════════════════════════
let viewYear, viewMonth;
let selectedDate     = null;
let isSaving         = false;   // FIX: guard against double-clicks
let editingSlotId    = null;    // schedule_id currently being edited, or null when adding a new slot
let editingSlotKey   = null;    // the 'YYYY-MM-DD' key the slot being edited lives under

// slots[key] = [ { ...slot } ]  — keyed by 'YYYY-MM-DD'
let slots      = {};
// slotsByDay[dayName] = [ ...rawApiSlots ]  — source of truth for recurring slots
let slotsByDay = {};

// ══════════════════════════════════════════════════════════════
//  HELPERS
// ══════════════════════════════════════════════════════════════
// FIX: always build dates from numeric parts — never from ISO strings
// to avoid UTC-midnight timezone shifts (new Date('2025-06-29') shifts
// back 1 day in UTC+8 because it's parsed as midnight UTC).
function makeDate(y, m1, d) {
  // m1 is 1-based month (as it comes from splitting 'YYYY-MM-DD')
  return new Date(y, m1 - 1, d);
}

function formatKey(y, m, d) {
  // m here is 0-based (JS month from viewMonth)
  return `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}

const hr12 = t => {
  if (!t) return '';
  const [h, mm] = t.split(':');
  const h12 = +h % 12 || 12;
  return `${h12}:${mm} ${+h < 12 ? 'AM' : 'PM'}`;
};

// Adds `mins` minutes to a 'HH:MM' time string, wrapping at midnight.
function addMinutesToTime(t, mins) {
  const [h, m] = t.split(':').map(Number);
  let total = (h * 60 + m + mins) % (24 * 60);
  if (total < 0) total += 24 * 60;
  const hh = Math.floor(total / 60);
  const mm = total % 60;
  return `${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}`;
}

// Minutes since midnight for a 'HH:MM' or 'HH:MM:SS' time string. Overlap
// checks below compare a slot's server-supplied time (always 'HH:MM:SS',
// straight from a MySQL TIME column) against an <input type="time"> value
// (always 'HH:MM', no seconds) — plain string '<'/'>' treats the longer
// string as greater even when both represent the exact same instant (e.g.
// '10:00:00' > '10:00'), which misread two back-to-back slots sharing a
// boundary as overlapping. Comparing as numbers instead fixes that.
function timeToMinutes(t) {
  if (!t) return 0;
  const [h, m] = t.split(':').map(Number);
  return h * 60 + m;
}

// Returns true if `s` is happening right now — i.e. its date is today and
// the current time falls within its start/end window.
function isSlotOngoing(s, key) {
  const now      = new Date();
  const todayKey = formatKey(now.getFullYear(), now.getMonth(), now.getDate());
  if (key !== todayKey) return false;

  const sStart = s.start_time || s.start;
  const sEnd   = s.end_time || s.end;
  if (!sStart || !sEnd) return false;

  const nowMin = now.getHours() * 60 + now.getMinutes();
  return timeToMinutes(sStart) <= nowMin && nowMin < timeToMinutes(sEnd);
}

// Returns true if the slot date/time has completely passed.
function isSlotDone(s, key) {
  const now      = new Date();
  const todayKey = formatKey(now.getFullYear(), now.getMonth(), now.getDate());

  if (key < todayKey) return true;

  const sEnd = s.end_time || s.end;
  if (!sEnd || key !== todayKey) return false;

  const nowMin = now.getHours() * 60 + now.getMinutes();
  return nowMin >= timeToMinutes(sEnd);
}

// A slot's display status. 'ongoing' (booked + time reached) takes
// priority over plain 'booked', which takes priority over 'open'.
function getSlotStatusLabel(s, key) {
  if ((s.booked || !s.is_available) && isSlotOngoing(s, key)) return 'ongoing';
  // A team's session whose end time has passed.
  if ((s.teamName || s.hasBooking) && isSlotDone(s, key)) return 'ended';
  if (s.booked || !s.is_available) return 'booked';
  return 'open';
}

// ══════════════════════════════════════════════════════════════
//  API FETCH WRAPPER
// ══════════════════════════════════════════════════════════════
async function apiFetch(url, options = {}) {
  const res = await fetch(url, {
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': CSRF,
      'Accept': 'application/json',
      ...options.headers,
    },
    ...options,
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message ?? `HTTP ${res.status}`);
  }
  return res.json();
}

// ══════════════════════════════════════════════════════════════
// Dates a repeating slot has been taken out of, keyed by schedule_id. A slot
// stores a weekday, so without these the calendar would still draw the week
// the adviser moved, next to the one-off that replaced it.
let skippedDates = {};

function isSkipped(scheduleId, dateKey) {
  return (skippedDates[scheduleId] || []).indexOf(dateKey) !== -1;
}

// Which weeks an edit should touch. Resolves to 'occurrence', 'series', or
// '' when the adviser backs out.
function askEditScope(dayName) {
  return new Promise(resolve => {
    const box = document.getElementById('scope-ask');
    document.getElementById('scope-ask-note').textContent =
      'This slot repeats every ' + dayName + '.';
    document.getElementById('scope-ask-all').textContent = 'Every ' + dayName;
    box.classList.add('show');

    function done(scope) {
      box.classList.remove('show');
      box.removeEventListener('click', onClick);
      document.removeEventListener('keydown', onKey);
      resolve(scope);
    }
    function onClick(e) {
      const btn = e.target.closest('[data-scope]');
      if (btn) { done(btn.dataset.scope); return; }
      if (e.target === box) { done(''); }
    }
    function onKey(e) { if (e.key === 'Escape') { done(''); } }

    box.addEventListener('click', onClick);
    document.addEventListener('keydown', onKey);
  });
}

//  LOAD SLOTS FROM DB
// ══════════════════════════════════════════════════════════════
async function loadSlots() {
  try {
    const payload = await apiFetch('/instructor/schedule/slots');

    // The endpoint used to return a bare array. It now also reports the dates
    // each repeating slot has been taken out of, so both shapes are read.
    const data = Array.isArray(payload) ? payload : (payload.slots || []);
    skippedDates = {};
    ((Array.isArray(payload) ? [] : payload.exceptions) || []).forEach(x => {
      const key = String(x.exception_date).slice(0, 10);
      (skippedDates[x.schedule_id] = skippedDates[x.schedule_id] || []).push(key);
    });

    // The API returns one row per (schedule, booking) pair — a recurring
    // slot booked on several different weeks comes back as several rows
    // sharing the same schedule_id. Collapse those back into one slot
    // definition per schedule_id, keeping each booking's own session_date
    // so fanOutSlots() can tell which specific calendar occurrence it
    // belongs to instead of treating the whole recurring slot as booked.
    const bySchedule = {};
    data.forEach(s => {
      if (!bySchedule[s.schedule_id]) {
        bySchedule[s.schedule_id] = { ...s, bookings: [] };
      }
      if (s.booking_id && s.session_date) {
        bySchedule[s.schedule_id].bookings.push({
          booking_id: s.booking_id,
          cancel_requested: !!s.cancellation_requested_at,
          cancel_reason: s.cancellation_reason || '',
          session_date: s.session_date,
          team_name: s.team_name,
          student_fname: s.student_fname,
          student_lname: s.student_lname,
        });
      }
    });

    slotsByDay = {};
    Object.values(bySchedule).forEach(s => {
      const day = s.available_date; // 'Monday', 'Tuesday', etc.
      if (!slotsByDay[day]) slotsByDay[day] = [];
      slotsByDay[day].push(s);
    });

    fanOutSlots();

  } catch (e) {
    showToast('Failed to load slots: ' + e.message, 'error');
  }
}

// ══════════════════════════════════════════════════════════════
//  FAN OUT — convert slotsByDay → date-keyed slots[]
//  Called on init and every time viewYear/viewMonth changes.
//  FIX: this is now the ONLY place slots[] is populated from
//  slotsByDay. saveSlot() updates slotsByDay then calls this —
//  it never pushes directly into slots[] for recurring entries.
// ══════════════════════════════════════════════════════════════
function fanOutSlots() {
  slots = {};
  const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();

  for (let d = 1; d <= daysInMonth; d++) {
    const dateObj = new Date(viewYear, viewMonth, d);
    const dayName = DAY_NAMES[dateObj.getDay()];
    const key     = formatKey(viewYear, viewMonth, d);

    if (!slotsByDay[dayName]) continue;

    const matchingSlots = slotsByDay[dayName].filter(s => {
      // A week this slot was moved out of, or skipped, is not one of its
      // occurrences any more - whatever replaced it stands on its own.
      if (isSkipped(s.schedule_id, key)) return false;
      // One-time slots: only show on their specific date
      if (s.recur === 'once' || (!s.recur && s.specific_date)) {
        return s.specific_date === key;
      }
      // Biweekly: only every 2nd matching weekday from the specific_date
      if (s.recur === 'biweekly' && s.specific_date) {
        const origin  = new Date(s.specific_date);  // already a stored date string
        const [oy, om, od] = s.specific_date.split('-').map(Number);
        const originDate   = new Date(oy, om - 1, od);
        const diffDays     = Math.round((dateObj - originDate) / 86400000);
        return diffDays >= 0 && diffDays % 14 === 0;
      }
      // Weekly: show on every matching weekday
      return true;
    });

    if (matchingSlots.length > 0) {
      slots[key] = matchingSlots.map(s => {
        // Recurring slots share one schedule row across every occurrence of
        // that weekday — is_available is a manual "block this slot
        // entirely" toggle, not a per-date booked flag. Whether THIS
        // specific date is booked comes from matching a booking's own
        // session_date instead, so booking one Monday doesn't hide every
        // other Monday.
        const dateBooking = (s.bookings || []).find(b => b.session_date === key);
        const bookedName  = dateBooking
          ? (dateBooking.team_name || [dateBooking.student_fname, dateBooking.student_lname].filter(Boolean).join(' ') || '—')
          : '';

        return {
          hasBooking:         !!dateBooking,
          booking:            dateBooking || null,
          schedule_id:        s.schedule_id,
          google_event_id:    s.google_event_id ?? null,
          id:                 s.schedule_id,
          start:              s.start_time,
          end:                s.end_time,
          start_time:         s.start_time,
          end_time:           s.end_time,
          type:               s.slot_type ?? 'consult',
          maxBookings:        s.consultation_limit ?? 1,
          consultation_limit: s.consultation_limit ?? 1,
          recur:              s.recur ?? 'weekly',
          specific_date:      s.specific_date ?? null,
          is_available:       s.is_available ?? true,
          booked:             !!dateBooking || !(s.is_available ?? true),
          teamName:           bookedName,
          topic:              s.topic ?? '',
          available_date:     s.available_date,
        };
      });
    }
  }
}

// ══════════════════════════════════════════════════════════════
//  SEED DEMO DATA (only when API returns nothing)
// ══════════════════════════════════════════════════════════════
function seedDemoData() {
  const today = new Date();
  const y = today.getFullYear(), m = today.getMonth();
  const pad = n => String(n).padStart(2, '0');

  const offsets = [1, 2, 3, 5, 7, 8];
  const sampleSlots = [
    { start_time:'09:00', end_time:'10:00', slot_type:'consult',   consultation_limit:2, recur:'weekly',   is_available:false, teamName:'Team Nexus',  topic:'Chapter 3 Discussion' },
    { start_time:'10:00', end_time:'11:00', slot_type:'consult',   consultation_limit:1, recur:'weekly',   is_available:true,  teamName:'',            topic:'' },
    { start_time:'15:00', end_time:'16:00', slot_type:'defense',   consultation_limit:3, recur:'biweekly', is_available:false, teamName:'Team Sigma',  topic:'Final Defense Rehearsal' },
    { start_time:'13:00', end_time:'14:00', slot_type:'consult',   consultation_limit:2, recur:'once',     is_available:true,  teamName:'',            topic:'' },
  ];

  offsets.forEach((offset, i) => {
    // FIX: numeric date constructor
    const d = new Date(y, m, today.getDate() + offset);
    if (d.getDay() === 0 || d.getDay() === 6) return;
    const dayName = DAY_NAMES[d.getDay()];
    const s = sampleSlots[i] || sampleSlots[0];

    if (!slotsByDay[dayName]) slotsByDay[dayName] = [];
    // Only seed if that day doesn't already have a demo slot
    if (!slotsByDay[dayName].find(x => x._demo)) {
      slotsByDay[dayName].push({
        schedule_id:        null,
        google_event_id:    null,
        available_date:     dayName,
        _demo:              true,
        ...s,
      });
    }
  });

  fanOutSlots();
}

// ══════════════════════════════════════════════════════════════
//  INIT
// ══════════════════════════════════════════════════════════════
async function initCalendar() {
  const now  = new Date();
  viewYear   = now.getFullYear();
  viewMonth  = now.getMonth();

  await loadSlots();

  redrawCalendar();
  renderUpcoming();
  renderSlotStats();
}

// ══════════════════════════════════════════════════════════════
//  MONTH NAVIGATION
// ══════════════════════════════════════════════════════════════
function prevMonth() {
  if (calView === 'week') {
    weekAnchor.setDate(weekAnchor.getDate() - 7);
    renderWeek();

    return;
  }

  viewMonth--;
  if (viewMonth < 0) { viewMonth = 11; viewYear--; }
  fanOutSlots();   // re-fan for new month — no duplicate risk
  redrawCalendar();
}

function nextMonth() {
  if (calView === 'week') {
    weekAnchor.setDate(weekAnchor.getDate() + 7);
    renderWeek();

    return;
  }

  viewMonth++;
  if (viewMonth > 11) { viewMonth = 0; viewYear++; }
  fanOutSlots();
  redrawCalendar();
}

// ══════════════════════════════════════════════════════════════
//  CALENDAR RENDER
// ══════════════════════════════════════════════════════════════
/** Redraw whatever is on screen - month grid or week grid. */
function redrawCalendar() {
  if (calView === 'week') {
    renderWeek();
  } else {
    renderCalendar();
  }
}

function renderCalendar() {
  const grid    = document.getElementById('cal-grid');
  const today   = new Date();
  const todayKey = formatKey(today.getFullYear(), today.getMonth(), today.getDate());

  document.getElementById('cal-month-display').textContent = `${MONTHS[viewMonth].substring(0, 3).toUpperCase()} ${viewYear}`;
  // Says what the grid is showing. The month is already in the control to
  // its right, so repeating it there spent the line on a duplicate.
  document.getElementById('cal-month-label').textContent   = 'Occurrences of your weekly slots';

  grid.innerHTML = '';

  ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(d => {
    const el = document.createElement('div');
    el.className   = 'cal-day-label';
    el.textContent = d;
    grid.appendChild(el);
  });

  const firstDay        = new Date(viewYear, viewMonth, 1).getDay();
  const daysInMonth     = new Date(viewYear, viewMonth + 1, 0).getDate();
  const daysInPrevMonth = new Date(viewYear, viewMonth, 0).getDate();

  // Padding — previous month
  for (let i = firstDay - 1; i >= 0; i--) {
    const el = document.createElement('div');
    el.className = 'cal-cell other-month';
    el.innerHTML = `<div class="cal-date-num">${daysInPrevMonth - i}</div>`;
    grid.appendChild(el);
  }

  // Current month
  for (let d = 1; d <= daysInMonth; d++) {
    const key       = formatKey(viewYear, viewMonth, d);
    // FIX: numeric constructor — no UTC shift
    const dateObj   = new Date(viewYear, viewMonth, d);
    const dayName   = DAY_NAMES[dateObj.getDay()];
    const isToday   = key === todayKey;
    const daySlots  = slots[key] || [];
    const todayDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const isPast    = dateObj < todayDate;
    const isWeekend = dateObj.getDay() === 0 || dateObj.getDay() === 6;

    const el = document.createElement('div');
    el.className = 'cal-cell';
    if (isToday)   el.classList.add('today');
    if (daySlots.length > 0) el.classList.add('has-slot');
    if (isPast || isWeekend) el.classList.add('past-day');
    if (key === selectedDate) el.classList.add('is-selected');

    el.dataset.key = key;
    el.onclick = () => handleCellClick(key, d, isPast || isWeekend, dayName);

    const dateEl = document.createElement('div');
    dateEl.className   = 'cal-date-num';
    dateEl.textContent = d;
    el.appendChild(dateEl);

    {
      daySlots.slice(0, 2).forEach(s => {
        const pill = document.createElement('div');
        const status = getSlotStatusLabel(s, key);
        let cls = TYPE_CLASS[s.type] || 'type-consult';
        if (status === 'booked' || status === 'ongoing') cls = 'is-booked';
        if (status === 'ended') cls = 'is-ended';
        pill.className = `cal-slot-pill ${cls}`;
        if (status === 'ongoing') {
          pill.textContent = `🔴 Ongoing · ${TYPE_LABELS[s.type] || 'Consultation'}`;
          pill.style.fontWeight = '700';
        } else if (status === 'ended') {
          pill.textContent = 'Ended ' + hr12(s.start || s.start_time).replace(' ', '');
        } else {
          // "9:00a" saved four pixels and cost the reader a word. The space
          // goes, the meridiem stays.
          pill.textContent = hr12(s.start || s.start_time).replace(' ', '');
        }
        pill.title = `${hr12(s.start || s.start_time)}–${hr12(s.end || s.end_time)} · Max ${s.maxBookings || s.consultation_limit}${s.teamName ? ' · Team: ' + s.teamName : ''}${status === 'ongoing' ? ' · Ongoing now' : ''}`;
        el.appendChild(pill);
      });

      if (daySlots.length > 2) {
        const more = document.createElement('div');
        more.className   = 'cal-more';
        more.textContent = `+${daySlots.length - 2} more`;
        el.appendChild(more);
      }

      if (daySlots.length > 0) {
        const badge = document.createElement('div');
        badge.className   = 'cell-slot-count';
        badge.textContent = daySlots.length;
        el.appendChild(badge);
      }

      if (daySlots.some(s => s.recur && s.recur !== 'once')) {
        const ri = document.createElement('div');
        ri.className = 'recur-indicator';
        ri.title     = 'Has recurring slot';
        el.appendChild(ri);
      }

      if (daySlots.some(s => s.google_event_id)) {
        const gc = document.createElement('div');
        gc.style.cssText = 'font-size:9px;color:var(--accent);margin-top:2px';
        gc.textContent   = '📅 GCal';
        el.appendChild(gc);
      }
    }

    grid.appendChild(el);
  }

  // Trailing padding
  const totalCells = firstDay + daysInMonth;
  const remainder  = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
  for (let d = 1; d <= remainder; d++) {
    const el = document.createElement('div');
    el.className = 'cal-cell other-month';
    el.innerHTML = `<div class="cal-date-num">${d}</div>`;
    grid.appendChild(el);
  }
}

// ══════════════════════════════════════════════════════════════
//  WEEK VIEW
//  The same slots against a time axis. renderCalendar() is left
//  alone; this is a second way of drawing slots[], so there is no
//  second idea of what is booked.
// ══════════════════════════════════════════════════════════════
const WK_FIRST_HOUR = 7;    // 7am
const WK_LAST_HOUR  = 19;   // 7pm
const WK_HOUR_PX    = 44;

let calView    = 'month';
let weekAnchor = null;      // any date inside the week being shown

/** The Sunday of the week containing this date. */
function weekStart(date) {
  const start = new Date(date.getFullYear(), date.getMonth(), date.getDate());
  start.setDate(start.getDate() - start.getDay());

  return start;
}

function setCalView(view) {
  calView = view;

  document.getElementById('btn-view-month').classList.toggle('is-on', view === 'month');
  document.getElementById('btn-view-week').classList.toggle('is-on', view === 'week');
  document.getElementById('cal-grid').hidden = view !== 'month';
  document.getElementById('week-grid').hidden = view !== 'week';

  if (view === 'week') {
    if (!weekAnchor) {
      // Open on the week you were already looking at, or this one.
      const [y, m, d] = (selectedDate || '').split('-').map(Number);
      weekAnchor = selectedDate ? makeDate(y, m, d) : new Date();
    }
    renderWeek();
  } else {
    renderCalendar();
  }
}

function renderWeek() {
  const head  = document.getElementById('wk-head');
  const body  = document.getElementById('wk-body');
  const start = weekStart(weekAnchor);
  const today = new Date();
  const todayKey = formatKey(today.getFullYear(), today.getMonth(), today.getDate());

  head.innerHTML = '';
  body.innerHTML = '';

  // The label above the grid follows the week, not the month it starts in.
  const last = new Date(start.getFullYear(), start.getMonth(), start.getDate() + 6);
  const sameMonth = start.getMonth() === last.getMonth();
  document.getElementById('cal-month-display').textContent =
    `${start.getDate()} ${MONTHS[start.getMonth()].substring(0, 3).toUpperCase()}`
    + ` – ${last.getDate()} ${MONTHS[last.getMonth()].substring(0, 3).toUpperCase()}`;
  document.getElementById('cal-month-label').textContent = sameMonth
    ? `${MONTHS[start.getMonth()]} ${start.getFullYear()}`
    : `${MONTHS[start.getMonth()]} – ${MONTHS[last.getMonth()]} ${last.getFullYear()}`;

  head.appendChild(document.createElement('div'));

  const hours = document.createElement('div');
  hours.className = 'wk-hours';

  for (let h = WK_FIRST_HOUR; h <= WK_LAST_HOUR; h++) {
    const label = document.createElement('i');
    label.textContent = h === 12 ? '12pm' : (h > 12 ? (h - 12) + 'pm' : h + 'am');
    hours.appendChild(label);
  }

  body.appendChild(hours);

  for (let i = 0; i < 7; i++) {
    const date = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
    const key  = formatKey(date.getFullYear(), date.getMonth(), date.getDate());

    const th = document.createElement('div');
    th.className = key === todayKey ? 'is-today' : '';
    th.innerHTML = DAYS[date.getDay()] + '<span>' + date.getDate() + '</span>';
    head.appendChild(th);

    const col = document.createElement('div');
    col.className = 'wk-col'
      + (key === selectedDate ? ' is-selected' : '');
    col.style.height = ((WK_LAST_HOUR - WK_FIRST_HOUR + 1) * WK_HOUR_PX) + 'px';

    col.addEventListener('click', () => {
      selectDate(key, date.getDate());
      renderWeek();
    });

    (slots[key] || []).forEach((slot, index) => {
      const from = timeToMinutes(slot.start || slot.start_time);
      const to   = timeToMinutes(slot.end || slot.end_time);
      const top  = ((from - WK_FIRST_HOUR * 60) / 60) * WK_HOUR_PX;

      // A slot outside the drawn hours would otherwise land on top of the
      // 7am row; clamp it into view rather than lose it.
      const height = Math.max(((to - from) / 60) * WK_HOUR_PX, 17);

      const el = document.createElement('div');
      el.className = 'wk-slot ' + (slot.booked ? 'is-booked' : 'is-open');
      el.style.top = Math.max(top, 0) + 'px';
      el.style.height = height + 'px';
      el.style.borderLeftColor = TYPE_COLORS[slot.type] || 'var(--accent)';
      el.title = hr12(slot.start || slot.start_time) + '–' + hr12(slot.end || slot.end_time)
        + ' · ' + (TYPE_LABELS[slot.type] || 'Consultation')
        + (slot.teamName ? ' · ' + slot.teamName : '');

      const name = document.createElement('b');
      name.textContent = slot.booked ? (slot.teamName || 'Booked') : 'Open';
      el.appendChild(name);

      if (height > 30) {
        const when = document.createElement('span');
        when.textContent = hr12(slot.start || slot.start_time).replace(':00', '');
        el.appendChild(when);
      }

      el.addEventListener('click', (event) => {
        // Otherwise the column underneath also takes the click and the
        // panel jumps back to "add a slot" the moment you open one.
        event.stopPropagation();
        selectDate(key, date.getDate());
        renderWeek();
        editSlot(slot.schedule_id, key, index);
      });

      col.appendChild(el);
    });

    body.appendChild(col);
  }
}

// ══════════════════════════════════════════════════════════════
//  CELL CLICK
//  Clicking a date shows its existing slots and the add form together
//  in the side panel.
// ══════════════════════════════════════════════════════════════
function handleCellClick(key, dayNum, isDisabled, dayName) {
  // Highlight selected cell
  document.querySelectorAll('.cal-cell.selected').forEach(el => el.classList.remove('selected'));
  const cell = document.querySelector(`.cal-cell[data-key="${key}"]`);
  if (cell) cell.classList.add('selected');

  // The side panel lists this date's slots already, right beside the form
  // for changing them. This used to also pop a modal listing the same
  // slots with its own delete button - a leftover from before View and Add
  // were merged into that panel.
  selectDate(key, dayNum);
}

// ══════════════════════════════════════════════════════════════
//  DATE SELECTION
// ══════════════════════════════════════════════════════════════
function selectDate(key, dayNum) {
  // Remove previous conflict highlight
  document.querySelectorAll('.cal-cell.has-conflict').forEach(el => el.classList.remove('has-conflict'));

  selectedDate = key;
  // FIX: split into numbers and use numeric constructor — no UTC shift
  const [y, m1, d] = key.split('-').map(Number);
  const dateObj    = makeDate(y, m1, d);
  const label      = `${DAYS[dateObj.getDay()]}, ${MONTHS[m1 - 1]} ${d}, ${y}`;
  document.getElementById('selected-date-text').textContent   = label;
  document.getElementById('selected-date-chip').style.display = 'flex';
  document.getElementById('no-date-hint').style.display       = 'none';
  document.getElementById('add-slot-fields').style.display    = 'block';

  // Open on a time this date is actually free, so clicking a busy morning
  // does not greet you with a warning about a clash the form just created.
  if (!editingSlotId) {
    const free = firstFreeStart(key);
    document.getElementById('f-start').value = free;
    document.getElementById('f-end').value = minutesToTime(timeToMinutes(free) + (currentDurationMinutes() || 60));
  }

  checkTimeOverlap();
  updateSlotDerived();
  renderSelectedDateSlots(key);
  redrawCalendar();

  // Re-apply selected highlight after renderCalendar rebuilds the grid
  const selectedCell = document.querySelector(`.cal-cell[data-key="${key}"]`);
  if (selectedCell) selectedCell.classList.add('selected');

  // Highlight cell if current time selection overlaps
  const cell = document.querySelector(`.cal-cell[data-key="${key}"]`);
  const warning = document.getElementById('time-overlap-warning');
  if (cell && warning && warning.style.display !== 'none') {
    cell.classList.add('has-conflict');
  }
}

function clearSelectedDate() {
  selectedDate = null;
  document.getElementById('selected-date-chip').style.display = 'none';
  document.getElementById('no-date-hint').style.display       = 'block';
  document.getElementById('add-slot-fields').style.display    = 'none';
  document.getElementById('time-overlap-warning').style.display = 'none';
  document.getElementById('selected-date-slots').style.display = 'none';
  document.querySelectorAll('.cal-cell.has-conflict').forEach(el => el.classList.remove('has-conflict'));
  redrawCalendar();
}

// ══════════════════════════════════════════════════════════════
//  EXISTING SLOTS FOR THE SELECTED DATE (shown inside the merged panel)
//  FIX: lets the user see + toggle + remove existing slots on the date
//  they've selected, right alongside the add-slot form — no separate
//  modal needed anymore.
// ══════════════════════════════════════════════════════════════
// The commit button says which weeks it commits. "Save Slot" is true of a
// one-off and of a rule covering the rest of the term.
// The form starts closed. Opening it is a decision - "add a weekly slot on
// Mondays" - rather than two time inputs sitting there waiting.
function openAddSlot() {
  const fields = document.getElementById('add-slot-fields');
  const lead = document.getElementById('add-slot-lead');
  if (fields) fields.style.display = 'block';
  if (lead) lead.style.display = 'none';
  const actions = document.querySelector('.form-actions');
  if (actions) actions.style.display = 'flex';
}

// Shown whenever a date is selected and the form is not already open.
function showAddSlotLead(key) {
  const lead = document.getElementById('add-slot-lead');
  const fields = document.getElementById('add-slot-fields');
  if (!lead || !key) return;
  const [y, m, d] = key.split('-').map(Number);
  const day = DAY_NAMES[makeDate(y, m, d).getDay()];
  document.getElementById('add-slot-lead-text').textContent =
    'Add a weekly slot on ' + day + 's';
  const open = fields && fields.style.display !== 'none';
  lead.style.display = open ? 'none' : 'flex';
  const actions = document.querySelector('.form-actions');
  if (actions) actions.style.display = open ? 'flex' : 'none';
}

function labelSaveButton(key) {
  const btn = document.getElementById('save-slot-btn');
  if (!btn || !key) return;
  const [y, m, d] = key.split('-').map(Number);
  const day = DAY_NAMES[makeDate(y, m, d).getDay()];
  const recur = (document.getElementById('f-recur') || {}).value || 'weekly';
  const tick = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> ';
  btn.innerHTML = tick + (recur === 'once' ? 'Save this date' : 'Save every ' + day);
}

function renderSelectedDateSlots(key) {
  const wrap = document.getElementById('selected-date-slots');
  const list = document.getElementById('selected-date-slots-list');
  if (!wrap || !list) return;

  labelSaveButton(key);
  showAddSlotLead(key);

  const daySlots = slots[key] || [];
  list.innerHTML = '';

  // The banner belongs to the date, not to any one row: if anything here
  // repeats, everything the adviser does on this panel needs the warning.
  const banner = document.getElementById('recur-banner');
  const repeating = daySlots.filter(s => (s.recur || 'weekly') !== 'once');
  if (banner) {
    if (repeating.length) {
      const [by, bm, bd] = key.split('-').map(Number);
      const day = repeating[0].available_date || DAY_NAMES[makeDate(by, bm, bd).getDay()];
      document.getElementById('recur-banner-text').innerHTML =
        'These are <b>weekly slots</b>. Editing one changes <b>every ' + day +
        '</b> — not just ' + bd + ' ' + MONTHS[bm - 1] + '.';
      banner.style.display = 'flex';
    } else {
      banner.style.display = 'none';
    }
  }

  if (daySlots.length === 0) {
    wrap.style.display = 'none';
    return;
  }

  wrap.style.display = 'block';
  daySlots.forEach((s, i) => {
    const startStr = hr12(s.start || s.start_time);
    const endStr   = hr12(s.end   || s.end_time);
    const status   = getSlotStatusLabel(s, key);

    // A team's name already says the slot is taken, so "Booked" beside it is
    // the same fact twice. Max only means anything while nobody has it.
    const who = s.teamName
      ? ' · ' + s.teamName
      : (status === 'booked' ? ' · Booked' : ' · Max ' + (s.maxBookings || s.consultation_limit || 1));
    // The tag beside it already names the type, so this carries only who
    // has it and what for.
    const statusText = (status === 'ongoing' ? '🔴 Ongoing' : who.replace(/^ · /, ''))
      + (s.topic ? ' · “' + s.topic + '”' : '');
    const row = document.createElement('div');
    // Two lines, not one: the times and who has them, then what the slot IS.
    // A slot stores a weekday, so most of these rows repeat every week and
    // the row never said so - which is how a term's Mondays got edited at
    // once by someone who thought they were editing this one.
    row.className = 'date-slot';
    if (s.schedule_id) {
      row.style.cursor = 'pointer';
      row.title = 'Edit this slot';
      row.onclick = () => editSlot(s.schedule_id, key, i);
    }

    const repeats = (s.recur || 'weekly') !== 'once';
    // slotsByDay is keyed by weekday, so available_date is always set here;
    // the date is only a fallback for a row built some other way.
    const [ky, km, kd] = key.split('-').map(Number);
    const dayWord = s.available_date || DAY_NAMES[makeDate(ky, km, kd).getDay()];
    // The sample says what the slot is, not only when it repeats: unchanged,
    // or booked for this one date with the rule left alone.
    const stateWord = s.hasBooking
      ? ''
      : (s.is_available ? ' · unchanged' : ' · unavailable');
    const recurLine = repeats
      ? 'Every ' + dayWord
      : 'This date only';

    // Skipping one week is not deleting the slot, so it is its own control.
    // Availability in words. The crossed circle it replaces was the one
    // control on the card nobody could name without hovering it.
    // Not offered when a team has the slot. The server refuses it anyway, but
    // a control that always fails is worse than no control - it reads as
    // something the adviser did wrong.
    const availLink = (s.schedule_id && !s.hasBooking) ? `
      <button onclick="event.stopPropagation();toggleSlot(${s.schedule_id}, '${key}', ${i})"
              class="slot-act">${s.is_available ? 'mark unavailable' : 'mark available'}</button>` : '';

    const skipBtn = (repeats && s.schedule_id && !s.hasBooking) ? `
      <button onclick="event.stopPropagation();skipThisWeek(${s.schedule_id}, '${key}')"
              class="slot-act"
              title="Remove only this week">skip this week</button>` : '';

    const ended = status === 'ended';
    const asked = !ended && !!(s.booking && s.booking.cancel_requested);
    const tagClass = s.hasBooking ? (ended ? 'is-ended' : asked ? 'is-request' : 'is-booked') : ('type-' + (s.type || 'consult'));
    const tagText = s.hasBooking ? (ended ? 'Ended' : asked ? 'Cancel requested' : 'Booked') : (TYPE_LABELS[s.type] || 'Consultation');

    // Who has the slot and what for, in its own block rather than trailing
    // off the end of the time as truncated text.
    const initials = (s.teamName || '')
      .split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
    const bookingBlock = s.hasBooking ? `
      <span class="slot-booking">
        <span class="who-dot">${initials || '—'}</span>
        <span><b>${s.teamName || 'Booked'}</b>${s.topic ? '<br>' + s.topic : ''}</span>
      </span>` : '';

    // The team asked to cancel; nothing changes until the expert says so.
    const requestBlock = asked ? `
      <span class="slot-request">
        <span><b>Cancellation requested</b>${s.booking.cancel_reason ? ' · “' + escHtml(s.booking.cancel_reason) + '”' : ''}</span>
        <span class="slot-acts">
          <button type="button" class="slot-act" onclick="event.stopPropagation();decideCancellation(${s.booking.booking_id}, 'approve', '${key}')">Approve</button>
          <button type="button" class="slot-act" onclick="event.stopPropagation();decideCancellation(${s.booking.booking_id}, 'decline', '${key}')">Keep booking</button>
        </span>
      </span>` : '';

    row.innerHTML = `
      <span class="date-slot-top">
        <span class="slot-times">
          <span>${startStr}</span><span class="slot-arrow">→</span><span>${endStr}</span>
          <span class="slot-tag ${tagClass}">${tagText}</span>
        </span>
        <span class="date-slot-actions">
          ${s.hasBooking ? `
            <span class="slot-held" title="A team has booked this slot - cancel the booking to change it">
              <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
            </span>` : `
          <button onclick="event.stopPropagation();deleteSlot('${key}', ${i}${s.schedule_id ? ',' + s.schedule_id : ''})"
                  class="slot-del"
                  title="${repeats ? 'Delete every week' : 'Delete slot'}">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
          </button>`}
        </span>
      </span>
      ${bookingBlock}${requestBlock}
      <span class="date-slot-meta">
        <svg class="recur-mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M17 2.1 21 6l-4 3.9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/>
          <path d="M7 21.9 3 18l4-3.9"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>
        </svg>
        <span>${recurLine}${stateWord}</span>
        <span class="slot-acts">${skipBtn}${availLink}</span>
      </span>`;
    list.appendChild(row);
  });
}

// Remove one week from a repeating slot. The slot stays; every other week
// keeps the time it has. This is the only way to clear a single date -
// deleting the slot takes the whole term with it.
async function skipThisWeek(scheduleId, dateKey) {
  const dayWord = DAY_NAMES[makeDate(...dateKey.split('-').map(Number)).getDay()];

  try {
    await apiFetch(`/instructor/schedule/slots/${scheduleId}/occurrences/${dateKey}`, {
      method: 'DELETE',
    });
    await loadSlots();
    showToast('Skipped this ' + dayWord + '. Every other ' + dayWord + ' is unchanged.');
  } catch (e) {
    showToast('Could not skip this week: ' + e.message, 'error');
  }
}

function checkTimeOverlap() {
  const warning = document.getElementById('time-overlap-warning');
  const text = document.getElementById('time-overlap-text');
  const startInput = document.getElementById('f-start');
  const endInput = document.getElementById('f-end');

  // Remove previous conflict highlight
  document.querySelectorAll('.cal-cell.has-conflict').forEach(el => el.classList.remove('has-conflict'));

  if (!selectedDate || !startInput || !endInput) {
    if (warning) warning.style.display = 'none';
    return;
  }

  const start = startInput.value;
  const end = endInput.value;
  if (!start || !end || start >= end) {
    if (warning) warning.style.display = 'none';
    return;
  }

  // A slot being edited is not a conflict with itself. saveSlot() already
  // knew that; this warning did not, so opening any existing slot said it
  // overlapped a 9-10 consultation - which was the slot in the form.
  const daySlots = (slots[selectedDate] || [])
    .filter(s => !editingSlotId || s.schedule_id !== editingSlotId);
  const startMin = timeToMinutes(start);
  const endMin = timeToMinutes(end);
  const overlapping = daySlots.filter(s => {
    const sStart = s.start_time || s.start;
    const sEnd = s.end_time || s.end;
    return timeToMinutes(sStart) < endMin && timeToMinutes(sEnd) > startMin;
  });

  if (overlapping.length > 0) {
    const names = overlapping.map(s => {
      const type = TYPE_LABELS[s.type] || 'Consultation';
      return `${hr12(s.start_time || s.start)}–${hr12(s.end_time || s.end)} ${type}`;
    }).join(', ');

    text.textContent = `Overlaps with: ${names}. Please choose a different time.`;
    warning.style.display = 'flex';
    startInput.style.borderColor = 'rgba(239,68,68,0.6)';
    endInput.style.borderColor = 'rgba(239,68,68,0.6)';

    // Highlight the selected calendar cell
    const cell = document.querySelector(`.cal-cell[data-key="${selectedDate}"]`);
    if (cell) cell.classList.add('has-conflict');
  } else {
    warning.style.display = 'none';
    startInput.style.borderColor = '';
    endInput.style.borderColor = '';
  }
}

// ══════════════════════════════════════════════════════════════
//  SAVE SLOT
//
//  FIX summary:
//  1. isSaving guard prevents double-submit on slow networks.
//  2. dayName derived with numeric Date constructor — no UTC shift.
//  3. After API success: push raw record into slotsByDay[dayName]
//     then call fanOutSlots(). Do NOT also push into slots[key]
//     directly and do NOT run a separate fan-out loop here.
//     fanOutSlots() is the single source of truth for slots[].
//  4. The date is no longer cleared after a successful save — it stays
//     selected and the time fields advance to right after the slot
//     just added, so stacking several slots on one day is one
//     continuous flow instead of reselecting the date each time.
// ══════════════════════════════════════════════════════════════
async function saveSlot() {
  if (isSaving) return;   // FIX: prevent double-submit
  if (!selectedDate) { showToast('Please select a date first.', 'error'); return; }

  const start = document.getElementById('f-start').value;
  const end   = document.getElementById('f-end').value;
  if (start >= end) { showToast('End time must be after start time.', 'error'); return; }

  // Prevent overlap with existing slots on the selected date — when
  // editing, the slot being edited doesn't count as its own conflict.
  const daySlots = (slots[selectedDate] || []).filter(s => !editingSlotId || s.schedule_id !== editingSlotId);
  const startMin = timeToMinutes(start);
  const endMin = timeToMinutes(end);
  const hasOverlap = daySlots.some(s => {
    const sStart = s.start_time || s.start;
    const sEnd = s.end_time || s.end;
    return timeToMinutes(sStart) < endMin && timeToMinutes(sEnd) > startMin;
  });

  if (hasOverlap) {
    showToast('This time overlaps with an existing slot on the selected date. Please choose a different time.', 'error');
    return;
  }

  const type  = document.getElementById('f-type').value;
  const maxB  = parseInt(document.getElementById('f-max').value) || 1;
  const recur = document.getElementById('f-recur').value;

  // FIX: numeric Date constructor — avoids UTC midnight timezone shift
  const [y, m1, d] = selectedDate.split('-').map(Number);
  const dayName    = DAY_NAMES[makeDate(y, m1, d).getDay()];

  // FIX: disable button while saving
  isSaving = true;
  const btn = document.getElementById('save-slot-btn');
  if (btn) { btn.disabled = true; btn.textContent = editingSlotId ? 'Updating…' : 'Saving…'; }

  if (editingSlotId) {
    const scheduleId = editingSlotId;

    // A repeating slot is a rule covering every one of those weekdays, so
    // saving it blindly rewrites the whole term. Ask which was meant.
    const editing = (slotsByDay[dayName] || []).find(s => s.schedule_id === scheduleId);
    const repeats = editing && (editing.recur || 'weekly') !== 'once';
    let scope = 'series';

    if (repeats) {
      scope = await askEditScope(dayName);
      if (!scope) {
        isSaving = false;
        if (btn) {
          btn.disabled = false;
          labelSaveButton(selectedDate);
        }
        return;
      }
    }

    try {
      // One week is its own resource under the slot, addressed by its date.
      // Replacing it leaves the weekly rule, and every other week, alone.
      if (scope === 'occurrence') {
        await apiFetch(`/instructor/schedule/slots/${scheduleId}/occurrences/${selectedDate}`, {
          method: 'PUT',
          body: JSON.stringify({ start_time: start, end_time: end, slot_type: type }),
        });

        // The rule did not change and a new slot now holds this date, so
        // there is no local record to mutate - read the day back instead.
        await loadSlots();
        showToast('This ' + dayName + ' updated. Every other ' + dayName + ' is unchanged.');
        cancelEdit();
        return;
      }

      const data = await apiFetch(`/instructor/schedule/slots/${scheduleId}`, {
        method: 'PATCH',
        body: JSON.stringify({
          available_date:     dayName,
          start_time:         start,
          end_time:           end,
          consultation_limit: maxB,
          slot_type:          type,
          recur:              recur,
          specific_date:      recur === 'once' ? selectedDate : null,
        }),
      });

      // Mutate the matching raw record in place — slotsByDay is the
      // single source of truth fanOutSlots() rebuilds slots[] from.
      const raw = (slotsByDay[dayName] || []).find(s => s.schedule_id === scheduleId);
      if (raw) {
        raw.start_time         = start;
        raw.end_time           = end;
        raw.consultation_limit = maxB;
        raw.slot_type          = type;
        raw.recur              = recur;
        raw.specific_date      = recur === 'once' ? selectedDate : null;
      }

      fanOutSlots();
      redrawCalendar();
      renderSlotStats();
      renderUpcoming();
      renderSelectedDateSlots(selectedDate);
      showToast('Slot updated.');
      cancelEdit();

    } catch (e) {
      showToast('Failed to update slot: ' + e.message, 'error');
    } finally {
      isSaving = false;
      if (btn) {
        btn.disabled = false;
        labelSaveButton(selectedDate);
      }
    }
    return;
  }

  try {
    const data = await apiFetch('/instructor/schedule/slots', {
      method: 'POST',
      body: JSON.stringify({
        available_date:     dayName,
        start_time:         start,
        end_time:           end,
        consultation_limit: maxB,
        slot_type:          type,
        recur:              recur,
        specific_date:      recur === 'once' ? selectedDate : null,
      }),
    });

    // FIX: push into slotsByDay — the single source of truth.
    // fanOutSlots() will then distribute this across all matching
    // dates in the viewed month. We never push into slots[] here.
    if (!slotsByDay[dayName]) slotsByDay[dayName] = [];
    slotsByDay[dayName].push({
      schedule_id:        data.schedule_id,
      google_event_id:    data.google_event_id ?? null,
      available_date:     dayName,
      start_time:         start,
      end_time:           end,
      consultation_limit: maxB,
      slot_type:          type,
      recur:              recur,
      specific_date:      recur === 'once' ? selectedDate : null,
      is_available:       data.is_available ?? true,
    });

    // FIX: single re-fan covers the whole month correctly
    fanOutSlots();
    redrawCalendar();
    renderSlotStats();
    renderUpcoming();

    const syncMsg = data.google_synced ? ' & synced to Google Calendar ✓' : '';
    const overlapMsg = data.is_available === false ? ' (overlap detected — marked unavailable)' : '';
    showToast(`Slot added for every ${dayName}${syncMsg}${overlapMsg}`);

    // FIX: keep the date selected instead of clearing it, and advance the
    // time fields to right after the slot just added — so adding several
    // back-to-back slots on the same day doesn't require re-clicking the
    // calendar or retyping the times each time.
    const duration = (() => {
      const [sh, sm] = start.split(':').map(Number);
      const [eh, em] = end.split(':').map(Number);
      return (eh * 60 + em) - (sh * 60 + sm);
    })();
    document.getElementById('f-start').value = end;
    document.getElementById('f-end').value   = addMinutesToTime(end, duration > 0 ? duration : 60);
    renderSelectedDateSlots(selectedDate);
    checkTimeOverlap();

  } catch (e) {
    // API failed — fall back to local-only, same pattern
    await loadSlots();
    redrawCalendar();
    renderSlotStats();
    renderUpcoming();
    showToast(`Slot saved locally (server error: ${e.message})`, 'error');
    clearSelectedDate();

  } finally {
    // FIX: always re-enable button
    isSaving = false;
    if (btn) {
      btn.disabled = false;
      labelSaveButton(selectedDate);
    }
  }
}

// ── Edit an existing slot ──────────────────────────────────────
// Populates the same add-slot form with the slot's current values and
// switches saveSlot() into update mode. Day and recurrence aren't
// editable here (updateSlot() on the backend doesn't accept them, and
// moving a slot to a different weekday/date is a bigger UX question
// than "fix the time/capacity/type") — recur is disabled with a note
// instead of silently doing nothing.
function editSlot(scheduleId, key, index) {
  const s = slots[key] && slots[key][index];
  if (!s) return;

  editingSlotId  = scheduleId;
  editingSlotKey = key;

  document.getElementById('f-start').value = (s.start_time || s.start || '').slice(0, 5);
  document.getElementById('f-end').value   = (s.end_time || s.end || '').slice(0, 5);
  document.getElementById('f-type').value  = s.slot_type || s.type || 'consult';
  document.getElementById('f-max').value   = s.consultation_limit || 1;
  document.getElementById('f-recur').value = s.recur || 'weekly';

  // Recurrence is editable now: updateSlot took neither recur nor
  // specific_date, so changing it here did nothing and the field was
  // disabled to hide that. Both are accepted, so it is a real control.
  const recurSelect = document.getElementById('f-recur');
  const recurNote   = document.getElementById('f-recur-locked-note');
  if (recurSelect) recurSelect.disabled = false;
  if (recurNote) recurNote.style.display = 'block';

  // Say what is being edited. "Editing existing slot" is true of a one-off
  // and of a rule covering every Monday for the rest of the term, and the
  // difference is the whole risk of this screen.
  const banner = document.getElementById('edit-mode-banner');
  if (banner) {
    const repeats = (s.recur || 'weekly') !== 'once';
    const dayWord = s.available_date || DAY_NAMES[makeDate(...key.split('-').map(Number)).getDay()];
    banner.textContent = repeats
      ? 'Editing a slot that repeats every ' + dayWord + '. You will be asked whether to change this ' + dayWord + ' or all of them.'
      : 'Editing a slot that happens on this date only.';
    banner.style.fontWeight = '500';
    banner.style.lineHeight = '1.45';
    banner.style.display = 'block';
  }

  const btn = document.getElementById('save-slot-btn');
  if (btn) labelSaveButton(selectedDate);

  document.getElementById('add-slot-fields').style.display = 'block';
  checkTimeOverlap();

  document.getElementById('add-slot-form')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

  markDurationChip(timeToMinutes(document.getElementById('f-end').value) - timeToMinutes(document.getElementById('f-start').value));
  updateSlotDerived();
}

function cancelEdit() {
  editingSlotId  = null;
  editingSlotKey = null;

  const recurSelect = document.getElementById('f-recur');
  const recurNote   = document.getElementById('f-recur-locked-note');
  if (recurSelect) recurSelect.disabled = false;
  if (recurNote) recurNote.style.display = 'none';

  const banner = document.getElementById('edit-mode-banner');
  if (banner) {
    banner.textContent = 'Editing existing slot';
    banner.style.display = 'none';
  }

  const btn = document.getElementById('save-slot-btn');
  if (btn) labelSaveButton(selectedDate);
}

function resetForm() {
  cancelEdit();
  clearSelectedDate();
  document.getElementById('f-start').value = '09:00';
  document.getElementById('f-end').value   = '10:00';
  document.getElementById('f-type').value  = 'consult';
  document.getElementById('f-max').value   = '1';
  document.getElementById('f-recur').value = 'once';
  markDurationChip(60);

  const more = document.getElementById('slot-more');
  if (more && !more.hidden) toggleSlotMore();

  updateSlotDerived();
}

// ── Toggle availability ────────────────────────────────────────
async function toggleSlot(scheduleId, key, index) {
  try {
    // The page already knows what the slot is showing, so it asks for the
    // opposite outright rather than for a flip.
    const wanted = !(slots[key] && slots[key][index] ? slots[key][index].is_available : true);
    const data = await apiFetch(`/instructor/schedule/slots/${scheduleId}/availability`, {
      method: 'PUT',
      body: JSON.stringify({ is_available: wanted }),
    });

    // Update both slots[] and the underlying slotsByDay[] entry
    if (slots[key] && slots[key][index]) {
      slots[key][index].is_available = data.is_available;
      slots[key][index].booked       = !data.is_available;

      // Keep slotsByDay in sync so fanOutSlots() reflects the change
      const dayName = slots[key][index].available_date;
      if (slotsByDay[dayName]) {
        const raw = slotsByDay[dayName].find(s => s.schedule_id === scheduleId);
        if (raw) raw.is_available = data.is_available;
      }
    }

    redrawCalendar();

    // FIX: refresh the panel in place — no modal to worry about anymore.
    if (selectedDate === key) renderSelectedDateSlots(key);

    showToast(data.is_available ? 'Slot marked as available.' : 'Slot marked as unavailable.');
  } catch (e) {
    showToast('Failed to update slot: ' + e.message, 'error');
  }
}

// ── Refresh Google Calendar event with correct timezone ───────────────
async function refreshGcalSlot(scheduleId) {
  try {
    const data = await apiFetch(`/instructor/schedule/slots/${scheduleId}/refresh-gcal`, { method: 'PATCH' });

    showToast('Google Calendar event refreshed with correct timezone ✓');
    redrawCalendar();
  } catch (e) {
    showToast('Failed to refresh: ' + e.message, 'error');
  }
}

// ── Delete slot ────────────────────────────────────────────────
async function deleteSlot(key, index, scheduleId) {
  try {
    if (scheduleId) {
      await apiFetch(`/instructor/schedule/slots/${scheduleId}`, { method: 'DELETE' });
    }

    // Deleting the slot currently open in the edit form would otherwise
    // leave saveSlot() pointed at a schedule_id that no longer exists.
    if (editingSlotId && editingSlotId === scheduleId) {
      cancelEdit();
    }

    // Remove from slots[]
    const removed = slots[key]?.splice(index, 1)?.[0];
    if (slots[key] && slots[key].length === 0) delete slots[key];

    // FIX: also remove from slotsByDay so fanOutSlots() won't re-add it
    if (removed) {
      const dayName = removed.available_date;
      if (slotsByDay[dayName]) {
        if (scheduleId) {
          slotsByDay[dayName] = slotsByDay[dayName].filter(s => s.schedule_id !== scheduleId);
        } else {
          // For local-only slots, match by time
          slotsByDay[dayName] = slotsByDay[dayName].filter(
            s => !(s.start_time === removed.start_time && s.end_time === removed.end_time && s.schedule_id == null)
          );
        }
        if (slotsByDay[dayName].length === 0) delete slotsByDay[dayName];
      }
    }

    redrawCalendar();
    renderSlotStats();
    renderUpcoming();

    // FIX: refresh in place instead of closing anything — the panel
    // stays open so several slots can be removed back-to-back without
    // reselecting the date.
    if (selectedDate === key) renderSelectedDateSlots(key);

    showToast('Slot removed.');
  } catch (e) {
    showToast('Failed to delete slot: ' + e.message, 'error');
  }
}

// ══════════════════════════════════════════════════════════════
//  UPCOMING APPOINTMENTS
// ══════════════════════════════════════════════════════════════
function renderUpcoming() {
  const list = document.getElementById('upcoming-list');

  const today       = new Date();
  const todayKey    = formatKey(today.getFullYear(), today.getMonth(), today.getDate());
  const nowMinutes  = today.getHours() * 60 + today.getMinutes();
  const upcoming    = [];

  Object.entries(slots)
    // Today included, not just strictly-future dates — an appointment
    // later today is still upcoming.
    .filter(([k]) => k >= todayKey)
    .sort(([a], [b]) => a.localeCompare(b))
    .forEach(([k, daySlots]) => {
      // Only slots somebody has actually booked. This used to ask for
      // `booked || !is_available`, but `booked` is itself true whenever a
      // slot is unavailable - so every slot the expert had blocked, and
      // every duplicate the server had flagged, was listed as an
      // appointment with a dash where the team name goes.
      daySlots.filter(s => s.hasBooking).forEach(s => {
        // For today specifically, a slot whose end time has already
        // passed is done, not upcoming (the backend marks its booking
        // 'done' the same way — this just keeps the list in sync without
        // waiting for the next full slot reload).
        if (k === todayKey) {
          const endTime = s.end || s.end_time;
          if (endTime) {
            const [eh, em] = endTime.split(':').map(Number);
            if (eh * 60 + em <= nowMinutes) return;
          }
        }

        const [y, m1, d] = k.split('-').map(Number);
        upcoming.push({
          team:  s.teamName || 'Booked',
          date:  `${MONTHS[m1 - 1]} ${d}`,
          time:  `${hr12(s.start || s.start_time)}–${hr12(s.end || s.end_time)}`,
          type:  s.type || 'consult',
          topic: s.topic || '',
        });
      });
    });

  document.getElementById('apt-count').textContent = upcoming.length;
  list.innerHTML = '';

  if (upcoming.length === 0) {
    list.innerHTML = '<div class="no-upcoming">No upcoming appointments.</div>';
    return;
  }

  const typeColors = {
    consult:   { bg:'rgba(29,78,216,0.1)',  color:'var(--blue)'   },
    docreview: { bg:'rgba(124,58,237,0.1)', color:'var(--purple)' },
    defense:   { bg:'rgba(180,83,9,0.1)',   color:'var(--amber)'  },
  };

  upcoming.forEach(apt => {
    const tc  = typeColors[apt.type] || typeColors.consult;
    const row = document.createElement('div');
    row.className = 'apt-row';
    row.innerHTML = `
      <div class="apt-row-top">
        <div class="apt-team">${apt.team}</div>
        <div class="apt-type-badge" style="background:${tc.bg};color:${tc.color}">${TYPE_LABELS[apt.type]}</div>
      </div>
      <div class="apt-meta">
        <span><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>${apt.date}</span>
        <span><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>${apt.time}</span>
      </div>
      ${apt.topic ? `<div class="apt-topic">${apt.topic}</div>` : ''}`;
    list.appendChild(row);
  });
}

// ══════════════════════════════════════════════════════════════
//  SLOT STATS
// ══════════════════════════════════════════════════════════════
function renderSlotStats() {
  const container = document.getElementById('slot-stats');
  container.innerHTML = '';

  // A type nobody can pick any more should not hold a permanent zero row in
  // the panel. It reappears on its own if a slot saved earlier still has it.
  let counts = { consult: 0, defense: 0 };
  Object.values(slots).forEach(daySlots => daySlots.forEach(s => {
    const t = s.type || s.slot_type;
    if (t === 'docreview') counts.docreview = counts.docreview || 0;
  }));
  Object.values(slots).forEach(daySlots => {
    daySlots.forEach(s => {
      const t = s.type || s.slot_type || 'consult';
      if (counts[t] !== undefined) counts[t]++;
    });
  });

  const total = Object.values(counts).reduce((a, b) => a + b, 0);

  if (total === 0) {
    container.innerHTML = '<div style="font-size:12px;color:var(--text3);font-style:italic;text-align:center;padding:8px">No slots this month.</div>';
    return;
  }

  const colors = { consult:'var(--blue)', docreview:'var(--purple)', defense:'var(--amber)' };
  Object.entries(counts).forEach(([type, count]) => {
    const pct = total ? Math.round(count / total * 100) : 0;
    const div = document.createElement('div');
    div.style.cssText = 'display:flex;flex-direction:column;gap:4px';
    div.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:12px;color:var(--text2);font-weight:500">${TYPE_LABELS[type]}</span>
        <span style="font-variant-numeric:tabular-nums;font-size:11px;font-weight:700;color:var(--text)">${count}</span>
      </div>
      <div style="height:5px;background:var(--bg4);border-radius:3px;overflow:hidden">
        <div style="height:100%;width:${pct}%;background:${colors[type]};border-radius:3px;transition:width 0.4s"></div>
      </div>`;
    container.appendChild(div);
  });
}

// ══════════════════════════════════════════════════════════════
//  TOAST
// ══════════════════════════════════════════════════════════════
let toastTimer;
// ══════════════════════════════════════════════════════════════
//  DURATION AND AVAILABILITY
// ══════════════════════════════════════════════════════════════

/** Minutes since midnight -> "HH:MM", clamped to the end of the day. */
function minutesToTime(mins) {
  const capped = Math.min(mins, 23 * 60 + 59);

  return String(Math.floor(capped / 60)).padStart(2, '0') + ':' + String(capped % 60).padStart(2, '0');
}

/** The end time is arithmetic on the start time; nobody thinks in end times. */
function setDuration(mins) {
  const start = document.getElementById('f-start').value;

  if (start) {
    document.getElementById('f-end').value = minutesToTime(timeToMinutes(start) + mins);
  }

  markDurationChip(mins);
  checkTimeOverlap();
  updateSlotDerived();
}

function markDurationChip(mins) {
  document.querySelectorAll('#add-slot-fields .dur-chip').forEach((c) => {
    c.classList.toggle('is-on', Number(c.dataset.mins) === mins);
  });
}

/** Moving the start carries the length with it, rather than the end. */
function onStartChanged() {
  const start = document.getElementById('f-start').value;
  const end = document.getElementById('f-end');

  if (start && end.value) {
    const length = currentDurationMinutes();

    if (length > 0) {
      end.value = minutesToTime(timeToMinutes(start) + length);
    }
  }

  checkTimeOverlap();
  updateSlotDerived();
}

/** Typing an odd end time is still allowed; the chips just stop claiming one. */
function onEndChanged() {
  const start = document.getElementById('f-start').value;
  const end = document.getElementById('f-end').value;

  markDurationChip(start && end ? timeToMinutes(end) - timeToMinutes(start) : -1);
  checkTimeOverlap();
  updateSlotDerived();
}

function currentDurationMinutes() {
  const lit = document.querySelector('#add-slot-fields .dur-chip.is-on');

  if (lit) return Number(lit.dataset.mins);

  const start = document.getElementById('f-start').value;
  const end = document.getElementById('f-end').value;

  return start && end ? timeToMinutes(end) - timeToMinutes(start) : 0;
}

function toggleSlotMore() {
  const more = document.getElementById('slot-more');
  const toggle = document.getElementById('slot-more-toggle');
  const opening = more.hidden;

  more.hidden = !opening;
  toggle.classList.toggle('is-open', opening);
  toggle.setAttribute('aria-expanded', String(opening));
}

/**
 * The settings that are not worth a field each, said as a sentence.
 *
 * Type, teams and repeat almost always keep their defaults, so three more
 * labelled controls at the same weight as the time buried the two decisions
 * that matter. This is what they currently say; the toggle above opens them.
 */
function updateSlotDerived() {
  const out = document.getElementById('slot-derived');

  if (!out) return;

  const end = document.getElementById('f-end').value;
  const type = document.getElementById('f-type');
  const max = parseInt(document.getElementById('f-max').value, 10) || 1;
  const recur = document.getElementById('f-recur').value;

  const typeLabel = (type.options[type.selectedIndex]?.text || 'Consultation').toLowerCase();
  const repeats = { once: 'once', weekly: 'every week', biweekly: 'every 2 weeks' }[recur] || 'every week';

  out.innerHTML = (end ? 'Ends <b>' + hr12(end).toLowerCase() + '</b> · ' : '')
    + 'a ' + typeLabel + ' for <b>' + max + ' team' + (max === 1 ? '' : 's') + '</b>, <b>' + repeats + '</b>';
}

/**
 * The first time this date is free, so the form does not open on a clash.
 *
 * It used to default to 09:00 whatever was already booked, which meant
 * clicking a busy morning greeted you with an overlap warning about a slot
 * the form had just put you on top of.
 */
function firstFreeStart(key) {
  const taken = (slots[key] || [])
    .map((s) => [timeToMinutes(s.start_time || s.start), timeToMinutes(s.end_time || s.end)])
    .sort((a, b) => a[0] - b[0]);

  const length = currentDurationMinutes() || 60;
  let at = 9 * 60;

  for (const [from, until] of taken) {
    if (at + length > from && until > at) at = until;
  }

  // Past the end of a working day is no better than the clash; leave it.
  return at + length > 21 * 60 ? '09:00' : minutesToTime(at);
}

// ── the weekly window ──
const AV_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
let avDuration = 60;
const avWindows = {};

AV_DAYS.forEach((day) => {
  // Off by default: an expert saying which days they are free is the point,
  // and a form that has already answered for them gets saved unread.
  avWindows[day] = { on: false, from: '09:00', until: '12:00' };
});

/**
 * Open the dialog on the availability that is actually set.
 *
 * It used to open blank every time, which is why removing anything needed a
 * "replace what I have" tick box and a separate clearing mode: the dialog
 * could only ever describe a new week, never the current one. Showing what
 * is set makes both unnecessary - Monday is lit because Monday is open, and
 * turning it off is how Monday closes.
 */
function openAvail() {
  loadAvailFromSlots();
  drawAvailRows();
  countAvailSlots();

  document.getElementById('avail-overlay').classList.add('open');
}

/**
 * Close it.
 *
 * The X, Cancel, a click on the backdrop and the end of a save all call
 * this. It was lost when this block was rewritten, so every one of them
 * called a function that no longer existed - which throws and leaves the
 * dialog sitting there.
 */
function closeAvail() {
  document.getElementById('avail-overlay').classList.remove('open');
}

/** Read the week back out of the slots that exist. */
function loadAvailFromSlots() {
  AV_DAYS.forEach((day) => {
    avWindows[day] = { on: false, from: '09:00', until: '12:00' };
  });

  const weekly = Object.values(slotsByDay)
    .flat()
    .filter((s) => (s.recur ?? 'weekly') !== 'once');

  const lengths = [];

  AV_DAYS.forEach((day) => {
    const mine = weekly.filter((s) => s.available_date === day);

    if (!mine.length) return;

    const starts = mine.map((s) => timeToMinutes(s.start_time));
    const ends = mine.map((s) => timeToMinutes(s.end_time));

    avWindows[day] = {
      on: true,
      from: minutesToTime(Math.min.apply(null, starts)),
      until: minutesToTime(Math.max.apply(null, ends)),
    };

    mine.forEach((s) => lengths.push(timeToMinutes(s.end_time) - timeToMinutes(s.start_time)));
  });

  // The length most of the slots already use, so reopening the dialog and
  // saving straight back out changes nothing.
  if (lengths.length) {
    const tally = {};
    lengths.forEach((n) => { tally[n] = (tally[n] || 0) + 1; });

    const commonest = Number(Object.keys(tally).sort((a, b) => tally[b] - tally[a])[0]);

    if ([30, 60, 90].indexOf(commonest) !== -1) {
      avDuration = commonest;
    }
  }

  document.querySelectorAll('#av-durations .dur-chip').forEach((c) => {
    c.classList.toggle('is-on', Number(c.dataset.mins) === avDuration);
  });
}

function setAvailDuration(mins) {
  avDuration = mins;
  document.querySelectorAll('#av-durations .dur-chip').forEach((c) => {
    c.classList.toggle('is-on', Number(c.dataset.mins) === mins);
  });
  countAvailSlots();
}

function toggleAvailDay(day) {
  avWindows[day].on = !avWindows[day].on;
  drawAvailRows();
}

function drawAvailRows() {
  const host = document.getElementById('av-rows');
  host.innerHTML = '';

  AV_DAYS.forEach((day) => {
    const w = avWindows[day];
    const row = document.createElement('div');
    row.className = 'av-row' + (w.on ? '' : ' is-off');

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'av-day' + (w.on ? ' is-on' : '');
    btn.textContent = day.slice(0, 3);
    btn.addEventListener('click', () => toggleAvailDay(day));
    row.appendChild(btn);

    const time = (value, key) => {
      const input = document.createElement('input');
      input.type = 'time';
      input.value = value;
      input.addEventListener('change', () => {
        w[key] = input.value;
        countAvailSlots();
      });

      return input;
    };

    row.appendChild(time(w.from, 'from'));

    const to = document.createElement('em');
    to.textContent = 'to';
    row.appendChild(to);

    row.appendChild(time(w.until, 'until'));
    host.appendChild(row);
  });

  countAvailSlots();
}

/** Every slice the chosen windows make. */
function availSlots() {
  const out = [];

  AV_DAYS.forEach((day) => {
    const w = avWindows[day];

    if (!w.on) return;

    const from = timeToMinutes(w.from);
    const until = timeToMinutes(w.until);

    for (let at = from; at + avDuration <= until; at += avDuration) {
      out.push({ day: day, start: minutesToTime(at), end: minutesToTime(at + avDuration) });
    }
  });

  return out;
}

/** The weekly slots that exist now, in the same day/start/end shape. */
function existingWeeklySlots() {
  return Object.values(slotsByDay)
    .flat()
    .filter((s) => (s.recur ?? 'weekly') !== 'once')
    .map((s) => ({
      schedule_id: s.schedule_id,
      day: s.available_date,
      start: (s.start_time || '').slice(0, 5),
      end: (s.end_time || '').slice(0, 5),
      booked: (s.bookings || []).length > 0,
    }));
}

const slotKey = (s) => s.day + ' ' + s.start + '-' + s.end;

/** What saving would change: what goes, what arrives, what cannot go. */
function availChanges() {
  const wanted = availSlots();
  const wantedKeys = new Set(wanted.map(slotKey));
  const existing = existingWeeklySlots();
  const existingKeys = new Set(existing.map(slotKey));

  return {
    adding: wanted.filter((s) => !existingKeys.has(slotKey(s))),
    // A slot a team has booked is never removed - they are expecting to turn
    // up to it - so it is reported instead.
    removing: existing.filter((s) => !wantedKeys.has(slotKey(s)) && !s.booked),
    keptBooked: existing.filter((s) => !wantedKeys.has(slotKey(s)) && s.booked),
  };
}

function countAvailSlots() {
  const made = availSlots();
  const changes = availChanges();
  const label = avDuration === 60 ? 'one-hour' : avDuration + '-minute';
  const box = document.getElementById('av-count');
  const save = document.getElementById('av-save');

  const parts = [];

  if (made.length) {
    parts.push('<b>' + made.length + ' bookable slot' + (made.length === 1 ? '' : 's')
      + ' a week</b>, in ' + label + ' pieces.');
  } else {
    parts.push('<b>No availability</b> - teams will have nothing of yours to book.');
  }

  if (changes.adding.length) parts.push(changes.adding.length + ' new.');
  if (changes.removing.length) parts.push(changes.removing.length + ' removed.');
  if (changes.keptBooked.length) {
    parts.push(changes.keptBooked.length + ' already booked, so '
      + (changes.keptBooked.length === 1 ? 'it stays' : 'they stay') + '.');
  }

  box.innerHTML = parts.join(' ');

  // One button, one label. Turning every day off and saving is how you take
  // your availability down - it does not need a second name for the same
  // action, and the panel above already says what saving will do.
  save.disabled = !changes.adding.length && !changes.removing.length;
  save.textContent = 'Save availability';
}

/**
 * Make the week match what the dialog shows.
 *
 * Slots are compared by day and time, so saving the same thing twice does
 * nothing and reopening the dialog shows exactly what was saved. Anything a
 * team has booked survives regardless.
 */
async function saveAvailability() {
  const changes = availChanges();

  if (!changes.adding.length && !changes.removing.length) return;

  const save = document.getElementById('av-save');
  save.disabled = true;
  save.textContent = 'Saving...';

  let created = 0;
  let removed = 0;
  let failed = 0;

  for (const slot of changes.removing) {
    try {
      await apiFetch('/instructor/schedule/slots/' + slot.schedule_id, { method: 'DELETE' });
      removed++;
    } catch (e) {
      failed++;
    }
  }

  for (const slot of changes.adding) {
    try {
      await apiFetch('/instructor/schedule/slots', {
        method: 'POST',
        body: JSON.stringify({
          available_date: slot.day,
          start_time: slot.start,
          end_time: slot.end,
          consultation_limit: 1,
          slot_type: 'consult',
          recur: 'weekly',
          specific_date: null,
        }),
      });
      created++;
    } catch (e) {
      failed++;
    }
  }

  save.disabled = false;
  save.textContent = 'Save availability';
  closeAvail();

  const said = [];
  if (created) said.push(created + ' opened');
  if (removed) said.push(removed + ' removed');
  if (failed) said.push(failed + ' could not be changed');

  showToast(said.join(', ') || 'Nothing changed', failed && !created && !removed ? 'error' : 'success');

  setTimeout(() => window.location.reload(), 1200);
}


function showToast(msg, type = 'success') {
  const toast = document.getElementById('sched-toast');
  document.getElementById('toast-msg').textContent = msg;
  toast.style.borderLeftColor = type === 'error' ? 'var(--red,#f87171)' : 'var(--accent,#00e5ff)';
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 3500);
}

// ── KICK OFF ────────────────────────────────────────────────────
const escHtml = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

// A team asked to cancel: approve frees the slot, keep leaves it booked.
// Both ask first; keeping it also needs the reason the team will read.
let pendingCancellation = null;

function decideCancellation(bookingId, decision, key) {
  pendingCancellation = { bookingId, decision, key };
  const approve = decision === 'approve';
  document.getElementById('cd-title').textContent = approve ? 'Approve this cancellation?' : 'Keep this booking?';
  document.getElementById('cd-sub').textContent = approve
    ? 'The consultation is cancelled and the slot opens for other teams.'
    : 'The consultation stays on. Tell the team why it cannot be cancelled.';
  document.getElementById('cd-reason-wrap').hidden = approve;
  document.getElementById('cd-reason').value = '';
  document.getElementById('cd-error').textContent = '';
  document.getElementById('cd-yes').textContent = approve ? 'Yes, cancel it' : 'Yes, keep it';
  document.getElementById('cancel-decision-overlay').classList.add('open');
  if (!approve) document.getElementById('cd-reason').focus();
}

function closeCancellationDecision() {
  document.getElementById('cancel-decision-overlay').classList.remove('open');
  pendingCancellation = null;
}

async function confirmCancellationDecision() {
  if (!pendingCancellation) return;
  const { bookingId, decision, key } = pendingCancellation;
  const reason = document.getElementById('cd-reason').value.trim();

  if (decision === 'decline' && reason.length < 10) {
    document.getElementById('cd-error').textContent = 'Give the team a reason (at least 10 characters).';
    return;
  }

  const yes = document.getElementById('cd-yes');
  yes.disabled = true;
  try {
    await apiFetch(`/instructor/schedule/bookings/${bookingId}/cancellation`, {
      method: 'PATCH',
      body: JSON.stringify(decision === 'decline' ? { decision, reason } : { decision }),
    });
    closeCancellationDecision();
    showToast(decision === 'approve' ? 'Cancellation approved. The slot is open again.' : 'Booking kept. The team has been told.', 'success');
    await loadSlots();
    redrawCalendar();
    renderUpcoming();
    renderSelectedDateSlots(key);
  } catch (e) {
    document.getElementById('cd-error').textContent = e.message;
    showToast(e.message, 'error');
  } finally {
    yes.disabled = false;
  }
}

initCalendar();

// Re-render time-dependent UI (the 'Ongoing' status) once a minute, so a
// slot flips from 'Booked' to 'Ongoing' — and back to past — without the
// user needing to refresh the page.
setInterval(() => {
  redrawCalendar();
  if (selectedDate) renderSelectedDateSlots(selectedDate);
  renderUpcoming();
}, 60000);


/* Mobile sidebar toggle */
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');

function toggleSidebar() {
  if (!sidebarEl) return;
  var isOpen = sidebarEl.classList.contains('mobile-open');
  if (isOpen) { closeSidebar(); } else { openSidebar(); }
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
@include('partials.escape-closes-modals')
</body>
</html>