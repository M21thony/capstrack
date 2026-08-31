<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>CapsTRACK – Expert Portal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
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
    <div class="page-title">Schedule Management</div>
    <div class="topbar-actions">
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
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

    <!-- Mode Banner -->
    <div class="mode-banner block-banner" id="block-banner" style="display:none">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
      <span><strong>Block Mode:</strong> Click any date to mark it as unavailable. Click a blocked date to unblock it.</span>
    </div>

    <div class="sched-layout">

      <!-- LEFT: Calendar -->
      <div>
        <div class="cal-card">
          <div class="cal-header">
            <div class="cal-title-group">
              <div class="cal-title">Availability Calendar</div>
              <div class="cal-month-label" id="cal-month-label">Loading…</div>
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
                <button class="mode-btn block-mode" id="btn-block" onclick="toggleBlockMode()">🚫 Block Mode</button>
              </div>
            </div>
          </div>

          <!-- Calendar Grid -->
          <div class="cal-grid" id="cal-grid">
            <!-- Rendered by JS -->
          </div>
        </div>

        <!-- Legend + Guide -->
        <div class="legend-section">
          <div class="legend-section-title">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            Legend, Guide &amp; Tips
          </div>

          <div class="legend-grid">
            <div class="legend-item"><div class="legend-swatch sw-consult"></div>Consultation</div>
            <div class="legend-item"><div class="legend-swatch sw-docreview"></div>Doc Review</div>
            <div class="legend-item"><div class="legend-swatch sw-defense"></div>Defense</div>
            <div class="legend-item"><div class="legend-swatch sw-booked"></div>Booked</div>
            <div class="legend-item"><div class="legend-swatch sw-blocked"></div>Blocked Day</div>
            <div class="legend-item"><div class="legend-swatch sw-today"></div>Today</div>
          </div>

          <div class="guide-tips-grid">
            <div class="guide-box">
              <div class="guide-box-title">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                How to Use
              </div>
              <div class="guide-list">
                <div class="guide-step"><div class="guide-step-num">1</div>Click any date on the calendar. Its existing slots (if any) will appear right here, along with a form to add a new one.</div>
                <div class="guide-step"><div class="guide-step-num">2</div>Fill in the time, slot type, and max bookings, then choose <strong style="color:var(--accent)">One-time</strong> or <strong style="color:var(--accent)">Recurring</strong> and click Save.</div>
                <div class="guide-step"><div class="guide-step-num">3</div>The date stays selected afterward so you can keep adding more slots to it without reclicking the calendar.</div>
                <div class="guide-step"><div class="guide-step-num">4</div>Toggle a slot's availability or delete it directly from the list next to the form — no need to open anything else.</div>
                <div class="guide-step"><div class="guide-step-num">5</div>Use <strong style="color:var(--red)">Block Mode</strong> to mark days you're unavailable – students won't be able to book them.</div>
              </div>
            </div>
            <div class="guide-box">
              <div class="guide-box-title">
                <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                Tips for Experts
              </div>
              <div class="guide-list">
                <div class="tip-item"><div class="tip-icon">💡</div><span>Set recurring slots for days you're regularly available – saves you time every week.</span></div>
                <div class="tip-item"><div class="tip-icon">⚠️</div><span>Block entire days in advance for holidays, meetings, or personal leave.</span></div>
                <div class="tip-item"><div class="tip-icon">📋</div><span>Keep max bookings at 1–2 per slot for focused, quality consultations.</span></div>
                <div class="tip-item"><div class="tip-icon">🔵</div><span>A cyan dot on a date means it has recurring slots. These apply every week automatically.</span></div>
              </div>
            </div>
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
            <button class="clear-date" onclick="clearSelectedDate()">
              <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <div id="no-date-hint" style="font-size:12px;color:var(--text3);margin-bottom:12px;padding:10px;background:var(--bg3);border-radius:8px;border:1px dashed var(--border);text-align:center;">
            ← Click a date on the calendar to see its slots and add a new one
          </div>

          <!-- Existing slots for the selected date — lets you view/toggle/remove
               them right here, in the same panel used to add new ones. -->
          <div id="selected-date-slots" style="display:none;margin-bottom:12px;">
            <div style="font-size:11px;color:var(--text3);margin-bottom:6px;font-weight:600;">Existing slots on this date</div>
            <div id="selected-date-slots-list" style="display:flex;flex-direction:column;gap:6px;"></div>
          </div>

          <div id="add-slot-fields" style="display:none">
            <div class="time-row" style="margin-bottom:11px">
              <div class="f-group" style="margin-bottom:0">
                <label class="f-label">Start Time</label>
                <input class="f-input" type="time" id="f-start" value="09:00" onchange="checkTimeOverlap()" oninput="checkTimeOverlap()"/>
              </div>
              <div class="f-group" style="margin-bottom:0">
                <label class="f-label">End Time</label>
                <input class="f-input" type="time" id="f-end" value="10:00" onchange="checkTimeOverlap()" oninput="checkTimeOverlap()"/>
              </div>
            </div>

            <div id="time-overlap-warning" style="display:none;padding:8px 12px;margin-bottom:12px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:8px;color:#dc2626;font-size:12px;font-weight:600;align-items:center;gap:8px">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
              </svg>
              <span id="time-overlap-text">This time overlaps with an existing slot.</span>
            </div>

            <div class="f-group">
              <label class="f-label">Slot Type</label>
              <select class="f-input" id="f-type">
                <option value="consult">Consultation</option>
                <option value="docreview">Document Review</option>
                <option value="defense">Defense</option>
              </select>
            </div>

            <div class="f-group">
              <label class="f-label">Max Teams per Slot</label>
              <input class="f-input" type="number" id="f-max" value="1" min="1" max="5"/>
            </div>

            <div class="f-group" style="margin-bottom:0" id="f-recur-group">
              <label class="f-label">Recurrence</label>
              <select class="f-input" id="f-recur">
                <option value="once">One-time only</option>
                <option value="weekly">Every week (same day)</option>
                <option value="biweekly">Every 2 weeks</option>
              </select>
              <div id="f-recur-locked-note" style="display:none;font-size:11px;color:var(--text3);margin-top:4px">Switching to a single date? Pick the date above.</div>
            </div>

            <div id="edit-mode-banner" style="display:none;padding:8px 12px;margin-bottom:11px;background:var(--accent-glow,rgba(0,229,255,0.08));border:1px solid var(--accent,#00e5ff);border-radius:8px;color:var(--accent,#00e5ff);font-size:12px;font-weight:600">
              Editing existing slot
            </div>

            <div class="form-actions">
              <button class="btn btn-primary content" id="save-slot-btn" onclick="saveSlot()">
                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Save Slot
              </button>
              <button class="btn btn-ghost content" onclick="resetForm()" style="flex:none;padding:7px 12px">Clear</button>
            </div>
          </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="upcoming-card">
          <div class="upcoming-header">
            <div class="upcoming-title">
              <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              Upcoming Appointments
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
            This Month's Slots
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
<style>#sched-toast.show{opacity:1;transform:translateY(0);pointer-events:auto}</style>

<!-- ─── BLOCK CONFIRM ─────────────────────────────────────────────────────── -->
<div class="slot-modal-overlay" id="block-confirm-overlay" onclick="if(event.target===this)closeBlockConfirm()">
  <div class="block-confirm">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
      <div class="block-confirm-title" id="bc-title" style="margin-bottom:0">Block this date?</div>
      <button onclick="closeBlockConfirm()" style="background:none;border:none;cursor:pointer;color:var(--text3);padding:4px;display:flex;border-radius:4px;transition:color 0.15s" onmouseenter="this.style.color='var(--text)'" onmouseleave="this.style.color='var(--text3)'">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="block-confirm-sub" id="bc-sub">Students won't be able to book any slot on this day. Any existing open slots will be hidden to students.</div>
    <div class="block-confirm-actions">
      <button class="btn btn-danger-solid btn content" id="bc-confirm-btn" onclick="confirmBlock()">Block Date</button>
      <button class="btn btn-ghost content" onclick="closeBlockConfirm()">Cancel</button>
    </div>
  </div>
</div>

<!-- ─── SLOT DETAIL MODAL ─────────────────────────────────────────────────── -->
<div class="slot-modal-overlay" id="slot-modal-overlay" onclick="if(event.target===this)closeSlotModal()">
  <div class="slot-modal">
    <div class="slot-modal-header">
      <div style="flex:1;min-width:0">
        <div class="slot-modal-title" id="slot-modal-title">Slots</div>
        <div class="slot-modal-sub" id="slot-modal-sub"></div>
      </div>
      <button class="slot-modal-close" onclick="closeSlotModal()">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="slot-modal-body" id="slot-modal-body">
      <!-- rendered by JS -->
    </div>
  </div>
</div>

<!-- ─── EXPERT PROFILE SIDE PANEL ─────────────────────────────────────────── -->
<script>
// ══════════════════════════════════════════════════════════════
//  CONSTANTS
// ══════════════════════════════════════════════════════════════
const DAYS        = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const DAY_NAMES   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
const MONTHS      = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const TYPE_LABELS = { consult:'Consultation', docreview:'Doc Review', defense:'Defense' };
const TYPE_CLASS  = { consult:'type-consult', docreview:'type-docreview', defense:'type-defense' };
const TYPE_COLORS = { consult:'var(--blue)', docreview:'var(--purple)', defense:'var(--amber)' };
const CSRF        = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// ══════════════════════════════════════════════════════════════
//  STATE
// ══════════════════════════════════════════════════════════════
let viewYear, viewMonth;
let selectedDate     = null;
// currentMode is now just 'normal' or 'block' — View and Add are merged
// into the always-visible panel, so there's no separate mode for them.
let currentMode      = 'normal';
let pendingBlockDate = null;
let isSaving         = false;   // FIX: guard against double-clicks
let editingSlotId    = null;    // schedule_id currently being edited, or null when adding a new slot
let editingSlotKey   = null;    // the 'YYYY-MM-DD' key the slot being edited lives under

// slots[key] = [ { ...slot } ]  — keyed by 'YYYY-MM-DD'
let slots      = {};
// slotsByDay[dayName] = [ ...rawApiSlots ]  — source of truth for recurring slots
let slotsByDay = {};
let blockedDates = new Set();

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
//  LOAD SLOTS FROM DB
// ══════════════════════════════════════════════════════════════
async function loadSlots() {
  try {
    const data = await apiFetch('/instructor/schedule/slots');

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
    { start_time:'14:00', end_time:'15:00', slot_type:'docreview', consultation_limit:1, recur:'once',     is_available:false, teamName:'Team Alpha',  topic:'Document Formatting Check' },
    { start_time:'10:00', end_time:'11:00', slot_type:'consult',   consultation_limit:1, recur:'weekly',   is_available:true,  teamName:'',            topic:'' },
    { start_time:'15:00', end_time:'16:00', slot_type:'defense',   consultation_limit:3, recur:'biweekly', is_available:false, teamName:'Team Sigma',  topic:'Final Defense Rehearsal' },
    { start_time:'13:00', end_time:'14:00', slot_type:'consult',   consultation_limit:2, recur:'once',     is_available:true,  teamName:'',            topic:'' },
    { start_time:'09:30', end_time:'10:30', slot_type:'docreview', consultation_limit:1, recur:'weekly',   is_available:false, teamName:'Team Orion',  topic:'Abstract & Introduction Review' },
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

  // Block a couple of days
  [4, 10].forEach(offset => {
    const d = new Date(y, m, today.getDate() + offset);
    if (d.getDay() !== 0 && d.getDay() !== 6) {
      blockedDates.add(`${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`);
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

  renderCalendar();
  renderUpcoming();
  renderSlotStats();
}

// ══════════════════════════════════════════════════════════════
//  MONTH NAVIGATION
// ══════════════════════════════════════════════════════════════
function prevMonth() {
  viewMonth--;
  if (viewMonth < 0) { viewMonth = 11; viewYear--; }
  fanOutSlots();   // re-fan for new month — no duplicate risk
  renderCalendar();
}

function nextMonth() {
  viewMonth++;
  if (viewMonth > 11) { viewMonth = 0; viewYear++; }
  fanOutSlots();
  renderCalendar();
}

// ══════════════════════════════════════════════════════════════
//  CALENDAR RENDER
// ══════════════════════════════════════════════════════════════
function renderCalendar() {
  const grid    = document.getElementById('cal-grid');
  const today   = new Date();
  const todayKey = formatKey(today.getFullYear(), today.getMonth(), today.getDate());

  document.getElementById('cal-month-display').textContent = `${MONTHS[viewMonth].substring(0, 3).toUpperCase()} ${viewYear}`;
  document.getElementById('cal-month-label').textContent   = `${MONTHS[viewMonth]} ${viewYear}`;

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
    const isBlocked = blockedDates.has(key);
    const daySlots  = slots[key] || [];
    const todayDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const isPast    = dateObj < todayDate;
    const isWeekend = dateObj.getDay() === 0 || dateObj.getDay() === 6;

    const el = document.createElement('div');
    el.className = 'cal-cell';
    if (isToday)   el.classList.add('today');
    if (isBlocked) el.classList.add('is-blocked');
    if (daySlots.length > 0) el.classList.add('has-slot');
    if (isPast || isWeekend) el.classList.add('past-day');
    if (key === selectedDate) el.classList.add('is-selected');

    el.dataset.key = key;
    el.onclick = () => handleCellClick(key, d, isPast || isWeekend, dayName);

    const dateEl = document.createElement('div');
    dateEl.className   = 'cal-date-num';
    dateEl.textContent = d;
    el.appendChild(dateEl);

    if (isBlocked) {
      const bl = document.createElement('div');
      bl.className   = 'blocked-label';
      bl.textContent = '✕ Unavailable';
      el.appendChild(bl);
    } else {
      daySlots.slice(0, 2).forEach(s => {
        const pill = document.createElement('div');
        const status = getSlotStatusLabel(s, key);
        let cls = TYPE_CLASS[s.type] || 'type-consult';
        if (status === 'booked' || status === 'ongoing') cls = 'is-booked';
        pill.className = `cal-slot-pill ${cls}`;
        if (status === 'ongoing') {
          pill.textContent = `🔴 Ongoing · ${TYPE_LABELS[s.type] || 'Consultation'}`;
          pill.style.fontWeight = '700';
        } else {
          pill.textContent = `${hr12(s.start || s.start_time).replace(' AM','a').replace(' PM','p')} ${TYPE_LABELS[s.type] || 'Consultation'}${s.teamName ? ' · ' + s.teamName : ''}`;
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
//  CELL CLICK
//  View and Add are merged — clicking any (non-blocked) date shows
//  its existing slots and the add form together in the side panel.
//  If the date has slots, also show the slot detail modal.
//  Block mode is the only separate mode.
// ══════════════════════════════════════════════════════════════
function handleCellClick(key, dayNum, isDisabled, dayName) {
  if (currentMode === 'block') {
    if (blockedDates.has(key)) {
      blockedDates.delete(key);
      renderCalendar();
      showToast('Date unblocked — students can now book this day.');
    } else {
      pendingBlockDate = { key, dayNum };
      openBlockConfirm(key, dayNum);
    }
    return;
  }

  if (blockedDates.has(key)) {
    showToast('⚠️ This date is blocked. Unblock it first (via Block Mode) to add slots.', 'error');
    return;
  }

  // Highlight selected cell
  document.querySelectorAll('.cal-cell.selected').forEach(el => el.classList.remove('selected'));
  const cell = document.querySelector(`.cal-cell[data-key="${key}"]`);
  if (cell) cell.classList.add('selected');

  // Show slot modal if date has slots
  const daySlots = slots[key] || [];
  if (daySlots.length > 0) {
    openSlotModal(key, dayNum, dayName);
  }

  selectDate(key, dayNum);
}

// ══════════════════════════════════════════════════════════════
//  MODE
//  FIX: only two states now — 'normal' (default) and 'block'. Toggling
//  Block Mode on/off no longer hides/shows the Add panel, since that
//  panel is always visible.
// ══════════════════════════════════════════════════════════════
function toggleBlockMode() {
  if (currentMode === 'block') {
    currentMode = 'normal';
    document.body.classList.remove('block-mode');
    document.getElementById('block-banner').style.display = 'none';
    document.getElementById('btn-block').classList.remove('active');
  } else {
    currentMode = 'block';
    document.body.classList.add('block-mode');
    document.getElementById('block-banner').style.display = 'flex';
    document.getElementById('btn-block').classList.add('active');
  }
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
  checkTimeOverlap();
  renderSelectedDateSlots(key);
  renderCalendar();

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
  renderCalendar();
}

// ══════════════════════════════════════════════════════════════
//  EXISTING SLOTS FOR THE SELECTED DATE (shown inside the merged panel)
//  FIX: lets the user see + toggle + remove existing slots on the date
//  they've selected, right alongside the add-slot form — no separate
//  modal needed anymore.
// ══════════════════════════════════════════════════════════════
function renderSelectedDateSlots(key) {
  const wrap = document.getElementById('selected-date-slots');
  const list = document.getElementById('selected-date-slots-list');
  if (!wrap || !list) return;

  const daySlots = slots[key] || [];
  list.innerHTML = '';

  if (daySlots.length === 0) {
    wrap.style.display = 'none';
    return;
  }

  wrap.style.display = 'block';
  daySlots.forEach((s, i) => {
    const startStr = hr12(s.start || s.start_time);
    const endStr   = hr12(s.end   || s.end_time);
    const status   = getSlotStatusLabel(s, key);
    const statusText = status === 'ongoing' ? ' · 🔴 Ongoing' : (status === 'booked' ? ' · Booked' : '');
    const row = document.createElement('div');
    row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:8px;padding:6px 8px;background:var(--bg3);border-radius:6px;font-size:11px;color:var(--text2)';

    const toggleBtn = s.schedule_id ? `
      <button onclick="toggleSlot(${s.schedule_id}, '${key}', ${i})"
              style="background:none;border:none;color:var(--text3);cursor:pointer;font-size:13px;line-height:1;padding:2px 4px;flex-shrink:0"
              title="${s.is_available ? 'Mark unavailable' : 'Mark available'}">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
        </svg>
      </button>` : '';

    const editBtn = s.schedule_id ? `
      <button onclick="editSlot(${s.schedule_id}, '${key}', ${i})"
              style="background:none;border:none;color:var(--text3);cursor:pointer;font-size:13px;line-height:1;padding:2px 4px;flex-shrink:0"
              title="Edit slot">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
        </svg>
      </button>` : '';

    row.innerHTML = `
      <span>${startStr}–${endStr} · ${TYPE_LABELS[s.type] || 'Consultation'}${statusText}</span>
      <span style="display:flex;align-items:center;gap:2px;flex-shrink:0">
        ${editBtn}
        ${toggleBtn}
        <button onclick="deleteSlot('${key}', ${i}${s.schedule_id ? ',' + s.schedule_id : ''})"
                style="background:none;border:none;color:var(--red,#f87171);cursor:pointer;font-size:14px;line-height:1;padding:2px 4px"
                title="Delete slot">✕</button>
      </span>`;
    list.appendChild(row);
  });
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

  const daySlots = slots[selectedDate] || [];
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
    try {
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
      renderCalendar();
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
        btn.innerHTML = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Save Slot';
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
    renderCalendar();
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
    renderCalendar();
    renderSlotStats();
    renderUpcoming();
    showToast(`Slot saved locally (server error: ${e.message})`, 'error');
    clearSelectedDate();

  } finally {
    // FIX: always re-enable button
    isSaving = false;
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Save Slot';
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

  const banner = document.getElementById('edit-mode-banner');
  if (banner) banner.style.display = 'block';

  const btn = document.getElementById('save-slot-btn');
  if (btn) btn.innerHTML = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Update Slot';

  document.getElementById('add-slot-fields').style.display = 'block';
  checkTimeOverlap();

  document.getElementById('add-slot-form')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function cancelEdit() {
  editingSlotId  = null;
  editingSlotKey = null;

  const recurSelect = document.getElementById('f-recur');
  const recurNote   = document.getElementById('f-recur-locked-note');
  if (recurSelect) recurSelect.disabled = false;
  if (recurNote) recurNote.style.display = 'none';

  const banner = document.getElementById('edit-mode-banner');
  if (banner) banner.style.display = 'none';

  const btn = document.getElementById('save-slot-btn');
  if (btn) btn.innerHTML = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Save Slot';
}

function resetForm() {
  cancelEdit();
  clearSelectedDate();
  document.getElementById('f-start').value = '09:00';
  document.getElementById('f-end').value   = '10:00';
  document.getElementById('f-type').value  = 'consult';
  document.getElementById('f-max').value   = '1';
  document.getElementById('f-recur').value = 'once';
}

// ── Toggle availability ────────────────────────────────────────
async function toggleSlot(scheduleId, key, index) {
  try {
    const data = await apiFetch(`/instructor/schedule/slots/${scheduleId}/toggle`, { method: 'PATCH' });

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

    renderCalendar();

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
    renderCalendar();
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

    renderCalendar();
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
//  BLOCK / UNBLOCK
// ══════════════════════════════════════════════════════════════
function openBlockConfirm(key, dayNum) {
  const [y, m1, d] = key.split('-').map(Number);
  document.getElementById('bc-title').textContent = `Block ${MONTHS[m1 - 1]} ${d}, ${y}?`;
  document.getElementById('bc-sub').textContent   = `Students won't be able to book any slot on this day. Any existing open slots will be hidden to students.`;
  document.getElementById('block-confirm-overlay').classList.add('open');
}

function closeBlockConfirm() {
  document.getElementById('block-confirm-overlay').classList.remove('open');
  pendingBlockDate = null;
}

// ══════════════════════════════════════════════════════════════
//  SLOT DETAIL MODAL
// ══════════════════════════════════════════════════════════════
function openSlotModal(key, dayNum, dayName) {
  const [y, m1, d] = key.split('-').map(Number);
  const dateObj = makeDate(y, m1, d);
  const label = `${DAYS[dateObj.getDay()]}, ${MONTHS[m1 - 1]} ${d}, ${y}`;

  document.getElementById('slot-modal-title').textContent = label;
  document.getElementById('slot-modal-sub').textContent   = `${slots[key]?.length || 0} slot(s) scheduled`;
  renderSlotModal(key);

  document.getElementById('slot-modal-overlay').classList.add('open');
}

function closeSlotModal() {
  document.getElementById('slot-modal-overlay').classList.remove('open');
}

function renderSlotModal(key) {
  const body = document.getElementById('slot-modal-body');
  const daySlots = slots[key] || [];

  if (daySlots.length === 0) {
    body.innerHTML = '<div style="text-align:center;padding:24px;color:var(--text3);font-size:13px">No slots scheduled for this date.</div>';
    return;
  }

  body.innerHTML = '';
  daySlots.forEach((s, i) => {
    const startStr = hr12(s.start || s.start_time);
    const endStr   = hr12(s.end   || s.end_time);
    const type     = TYPE_LABELS[s.type] || 'Consultation';
    const typeColor= TYPE_COLORS[s.type] || 'var(--blue)';
    const done     = isSlotDone(s, key);
    const status   = done
      ? '<span style="color:#9ca3af;font-weight:600;font-size:11px">Done</span>'
      : (s.booked || !s.is_available)
        ? '<span style="color:var(--red);font-weight:600;font-size:11px">Booked</span>'
        : '<span style="color:var(--green);font-weight:600;font-size:11px">Open</span>';
    const row = document.createElement('div');
    row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;background:var(--bg3);border-radius:8px;margin-bottom:8px;border-left:3px solid ' + typeColor;

    const info = document.createElement('div');
    info.style.flex = '1';
    info.innerHTML = `
      <div style="font-weight:600;font-size:13px;color:var(--text)">${startStr} – ${endStr}</div>
      <div style="font-size:11px;color:var(--text3);margin-top:2px;display:flex;align-items:center;gap:6px;flex-wrap:wrap">
        <span style="font-weight:600;color:${typeColor}">${type}</span>
        ${status}
        <span>· Max ${s.maxBookings || s.consultation_limit || 1}</span>
        ${s.teamName ? `<span>· ${s.teamName}</span>` : ''}
        ${s.topic ? `<span>· "${s.topic}"</span>` : ''}
      </div>`;

    const actions = document.createElement('div');
    actions.style.display = 'flex';
    actions.style.gap = '4px';

    if (s.schedule_id) {
      const toggleBtn = document.createElement('button');
      toggleBtn.title = s.is_available ? 'Mark unavailable' : 'Mark available';
      toggleBtn.style.cssText = 'background:none;border:none;cursor:pointer;color:var(--text3);padding:4px;border-radius:4px;transition:background 0.15s';
      toggleBtn.innerHTML = `<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>`;
      toggleBtn.onmouseenter = () => toggleBtn.style.background = 'rgba(0,0,0,0.06)';
      toggleBtn.onmouseleave = () => toggleBtn.style.background = 'none';
      toggleBtn.onclick = () => {
        toggleSlot(s.schedule_id, key, i);
        setTimeout(() => renderSlotModal(key), 600);
      };

      const delBtn = document.createElement('button');
      delBtn.title = 'Delete slot';
      delBtn.style.cssText = 'background:none;border:none;cursor:pointer;color:var(--red,#f87171);padding:4px;border-radius:4px;transition:background 0.15s';
      delBtn.innerHTML = `<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>`;
      delBtn.onmouseenter = () => delBtn.style.background = 'rgba(239,68,68,0.08)';
      delBtn.onmouseleave = () => delBtn.style.background = 'none';
      delBtn.onclick = () => {
        deleteSlot(key, i, s.schedule_id);
        setTimeout(() => renderSlotModal(key), 400);
      };

      actions.appendChild(toggleBtn);
      actions.appendChild(delBtn);
    }

    row.appendChild(info);
    row.appendChild(actions);
    body.appendChild(row);
  });
}

function confirmBlock() {
  if (!pendingBlockDate) return;
  blockedDates.add(pendingBlockDate.key);
  renderCalendar();
  closeBlockConfirm();
  showToast('Date blocked — students cannot book this day.');
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
      daySlots.filter(s => s.booked || !s.is_available).forEach(s => {
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
          team:  s.teamName || '—',
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

  let counts = { consult: 0, docreview: 0, defense: 0 };
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
        <span style="font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:var(--text)">${count}</span>
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
function showToast(msg, type = 'success') {
  const toast = document.getElementById('sched-toast');
  document.getElementById('toast-msg').textContent = msg;
  toast.style.borderLeftColor = type === 'error' ? 'var(--red,#f87171)' : 'var(--accent,#00e5ff)';
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 3500);
}

// ── KICK OFF ────────────────────────────────────────────────────
initCalendar();

// Re-render time-dependent UI (the 'Ongoing' status) once a minute, so a
// slot flips from 'Booked' to 'Ongoing' — and back to past — without the
// user needing to refresh the page.
setInterval(() => {
  renderCalendar();
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
</body>
</html>