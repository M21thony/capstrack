<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>CapsTRACK – Expert Portal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/schedule.css') }}" />
</head>
<body>
@include('instructor.partials.sidebar', ['currentPage' => 'schedule'])

<div class="main">
  <div class="topbar">
    <div class="page-title">Schedule Management</div>
    <div class="topbar-actions">
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </div>

  <div class="content">

    <!-- Mode Banner -->
    <div class="mode-banner add-banner" id="add-banner" style="display:none">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
      <span><strong>Add Slot Mode:</strong> Click any available date on the calendar to add a time slot.</span>
    </div>
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
                <button class="mode-btn active" id="btn-view" onclick="setMode('view')">View</button>
                <button class="mode-btn" id="btn-add" onclick="setMode('add')">+ Add Slot</button>
                <button class="mode-btn block-mode" id="btn-block" onclick="setMode('block')">🚫 Block</button>
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
                <div class="guide-step"><div class="guide-step-num">1</div>Switch to <strong style="color:var(--accent)">Add Slot</strong> mode then click any date on the calendar.</div>
                <div class="guide-step"><div class="guide-step-num">2</div>Fill in the time, slot type, and max bookings in the panel that appears.</div>
                <div class="guide-step"><div class="guide-step-num">3</div>Choose <strong style="color:var(--accent)">One-time</strong> or <strong style="color:var(--accent)">Recurring</strong> and click Save.</div>
                <div class="guide-step"><div class="guide-step-num">4</div>Use <strong style="color:var(--red)">Block</strong> to mark days you're unavailable – students won't be able to book them.</div>
                <div class="guide-step"><div class="guide-step-num">5</div>Click any date in View mode to see and manage existing slots.</div>
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

        <!-- Add Slot Form -->
        <div class="form-card" id="add-slot-form" style="display:none">
          <div class="form-card-title">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
            Add Available Slot
          </div>

          <div class="selected-date-chip" id="selected-date-chip" style="display:none">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span id="selected-date-text">No date selected</span>
            <button class="clear-date" onclick="clearSelectedDate()">
              <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <div id="no-date-hint" style="font-size:12px;color:var(--text3);margin-bottom:12px;padding:10px;background:var(--bg3);border-radius:8px;border:1px dashed var(--border);text-align:center;">
            ← Click a date on the calendar to select it
          </div>

          <div class="time-row" style="margin-bottom:11px">
            <div class="f-group" style="margin-bottom:0">
              <label class="f-label">Start Time</label>
              <input class="f-input" type="time" id="f-start" value="09:00"/>
            </div>
            <div class="f-group" style="margin-bottom:0">
              <label class="f-label">End Time</label>
              <input class="f-input" type="time" id="f-end" value="10:00"/>
            </div>
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

          <div class="f-group" style="margin-bottom:0">
            <label class="f-label">Recurrence</label>
            <select class="f-input" id="f-recur">
              <option value="once">One-time only</option>
              <option value="weekly">Every week (same day)</option>
              <option value="biweekly">Every 2 weeks</option>
            </select>
          </div>

          <div class="form-actions">
            <button class="btn btn-primary content" id="save-slot-btn" onclick="saveSlot()">
              <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              Save Slot
            </button>
            <button class="btn btn-ghost content" onclick="resetForm()" style="flex:none;padding:7px 12px">Clear</button>
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

<!-- ─── SLOT DETAIL MODAL ──────────────────────────────────────────────────── -->
<div class="slot-modal-overlay" id="slot-modal-overlay">
  <div class="slot-modal">
    <div class="slot-modal-header">
      <div>
        <div class="slot-modal-title" id="smd-title">Slots for May 14</div>
        <div class="slot-modal-sub" id="smd-sub">Wednesday</div>
      </div>
      <button class="slot-modal-close" onclick="closeSlotModal()">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="slot-modal-body">
      <div style="font-size:12px;color:var(--text3);margin-bottom:4px">Slots on this date:</div>
      <div class="slot-detail-list" id="smd-list"></div>
      <div id="smd-empty" style="display:none;font-size:12px;color:var(--text3);font-style:italic;padding:12px 0;text-align:center">No slots on this date.</div>
    </div>
    <div class="slot-modal-footer">
      <button class="btn btn-primary content" onclick="closeSlotModal();setMode('add')">
        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Slot to This Date
      </button>
    </div>
  </div>
</div>

<!-- ─── BLOCK CONFIRM ─────────────────────────────────────────────────────── -->
<div class="slot-modal-overlay" id="block-confirm-overlay">
  <div class="block-confirm">
    <div class="block-confirm-title" id="bc-title">Block this date?</div>
    <div class="block-confirm-sub" id="bc-sub">Students won't be able to book any slot on this day.</div>
    <div class="block-confirm-actions">
      <button class="btn btn-danger-solid btn content" id="bc-confirm-btn" onclick="confirmBlock()">Block Date</button>
      <button class="btn btn-ghost content" onclick="closeBlockConfirm()" style="margin-top:8px">Cancel</button>
    </div>
  </div>
</div>

<!-- ─── EXPERT PROFILE SIDE PANEL ─────────────────────────────────────────── -->
<div class="ep-overlay" id="ep-overlay" onclick="handleEpOverlay(event)">
  <div class="ep-modal" id="ep-modal">

    <div class="ep-header">
      <div class="ep-avatar">CS</div>
      <div style="flex:1;min-width:0">
        <div class="ep-header-name">Dr. Neil A. Inocencio</div>
        <div class="ep-header-sub">Capstone Expert · Active</div>
      </div>
      <button class="ep-close" onclick="closeExpertProfile()">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div class="ep-body">

      <div class="ep-field">
        <div class="ep-label">Full Name</div>
        <div class="ep-value readonly">
          <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Dr. Neil A. Inocencio
        </div>
      </div>

      <div class="ep-field">
        <div class="ep-label">Institutional Email</div>
        <div class="ep-value readonly">
          <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          nainocencio@cspc.edu.ph
        </div>
      </div>

      <div class="ep-divider"></div>

      <div class="ep-field">
        <div class="ep-label">Assigned Roles</div>
        <div class="ep-tags-wrap">
          <span class="role-pill role-adviser">Adviser</span>
          <span class="role-pill role-panel">Panel</span>
          <span class="role-pill role-consultant">Consultant</span>
          <span class="role-pill role-grammarian">Grammarian</span>
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:8px;display:flex;align-items:center;gap:4px">
          <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Managed by the system administrator
        </div>
      </div>

      <div class="ep-divider"></div>

      <div class="ep-field">
        <div class="ep-label">Team Capacity</div>
        <div class="ep-value readonly">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Max 4 teams · Currently advising 3
        </div>
      </div>

      <div class="ep-divider"></div>

      <div class="ep-field">
        <div class="ep-label">Areas of Expertise</div>
        <div class="ep-tags-wrap" id="ep-tags-wrap"></div>
        <div class="ep-tag-input-row">
          <input class="ep-tag-input" id="ep-tag-input" type="text" placeholder="Add expertise…" onkeydown="handleTagKey(event)"/>
          <button class="ep-tag-add-btn" onclick="addTag()">+ Add</button>
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:7px">
          Press Enter or click Add. Click × to remove.
        </div>
      </div>

    </div>

    <div class="ep-footer">
      <button class="btn content btn-ghost" style="color:#FFFF00" onclick="closeExpertProfile()">Close</button>
      <button class="btn btn-primary" onclick="saveExpertProfile()">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Save
      </button>
    </div>

  </div>
</div>

<script>
// ══════════════════════════════════════════════════════════════
//  CONSTANTS
// ══════════════════════════════════════════════════════════════
const DAYS        = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const DAY_NAMES   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
const MONTHS      = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const TYPE_LABELS = { consult:'Consultation', docreview:'Doc Review', defense:'Defense' };
const TYPE_CLASS  = { consult:'type-consult', docreview:'type-docreview', defense:'type-defense' };
const CSRF        = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// ══════════════════════════════════════════════════════════════
//  STATE
// ══════════════════════════════════════════════════════════════
let viewYear, viewMonth;
let selectedDate     = null;
let currentMode      = 'view';
let pendingBlockDate = null;
let isSaving         = false;   // FIX: guard against double-clicks

// slots[key] = [ { ...slot } ]  — keyed by 'YYYY-MM-DD'
let slots      = {};
// slotsByDay[dayName] = [ ...rawApiSlots ]  — source of truth for recurring slots
let slotsByDay = {};
let blockedDates = new Set();

// Expert profile tags
const EP_TAGS_EL = document.getElementById('ep-tags-wrap');
const EP_INPUT   = document.getElementById('ep-tag-input');
let expertTags   = ['Machine Learning', 'Data Science', 'Web Systems'];

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
    slotsByDay = {};
    data.forEach(s => {
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
      slots[key] = matchingSlots.map(s => ({
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
        booked:             !(s.is_available ?? true),
        teamName:           s.teamName ?? '',
        topic:              s.topic ?? '',
        available_date:     s.available_date,
      }));
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
  renderExpertTags();
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
        let cls = TYPE_CLASS[s.type] || 'type-consult';
        if (s.booked || !s.is_available) cls = 'is-booked';
        pill.className   = `cal-slot-pill ${cls}`;
        pill.textContent = `${hr12(s.start || s.start_time).replace(' AM','a').replace(' PM','p')} ${TYPE_LABELS[s.type] || 'Consultation'}`;
        pill.title       = `${hr12(s.start || s.start_time)}–${hr12(s.end || s.end_time)} · Max ${s.maxBookings || s.consultation_limit}`;
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
// ══════════════════════════════════════════════════════════════
function handleCellClick(key, dayNum, isDisabled, dayName) {
  if (currentMode === 'view') {
    openSlotModal(key, dayNum, dayName);
    return;
  }
  if (currentMode === 'add') {
    if (blockedDates.has(key)) { showToast('⚠️ This date is blocked. Unblock it first.', 'error'); return; }
    selectDate(key, dayNum);
    return;
  }
  if (currentMode === 'block') {
    if (blockedDates.has(key)) {
      blockedDates.delete(key);
      renderCalendar();
      showToast('Date unblocked — students can now book this day.');
    } else {
      pendingBlockDate = { key, dayNum };
      openBlockConfirm(key, dayNum);
    }
  }
}

// ══════════════════════════════════════════════════════════════
//  MODE
// ══════════════════════════════════════════════════════════════
function setMode(mode) {
  currentMode = mode;
  document.body.classList.remove('add-mode', 'block-mode');
  document.getElementById('add-banner').style.display    = 'none';
  document.getElementById('block-banner').style.display  = 'none';
  document.getElementById('add-slot-form').style.display = 'none';
  ['btn-view','btn-add','btn-block'].forEach(id => document.getElementById(id).classList.remove('active'));

  if (mode === 'add') {
    document.body.classList.add('add-mode');
    document.getElementById('add-banner').style.display    = 'flex';
    document.getElementById('add-slot-form').style.display = 'block';
    document.getElementById('btn-add').classList.add('active');
  } else if (mode === 'block') {
    document.body.classList.add('block-mode');
    document.getElementById('block-banner').style.display = 'flex';
    document.getElementById('btn-block').classList.add('active');
  } else {
    document.getElementById('btn-view').classList.add('active');
  }
}

// ══════════════════════════════════════════════════════════════
//  DATE SELECTION
// ══════════════════════════════════════════════════════════════
function selectDate(key, dayNum) {
  selectedDate = key;
  // FIX: split into numbers and use numeric constructor — no UTC shift
  const [y, m1, d] = key.split('-').map(Number);
  const dateObj    = makeDate(y, m1, d);
  const label      = `${DAYS[dateObj.getDay()]}, ${MONTHS[m1 - 1]} ${d}, ${y}`;
  document.getElementById('selected-date-text').textContent   = label;
  document.getElementById('selected-date-chip').style.display = 'flex';
  document.getElementById('no-date-hint').style.display       = 'none';
}

function clearSelectedDate() {
  selectedDate = null;
  document.getElementById('selected-date-chip').style.display = 'none';
  document.getElementById('no-date-hint').style.display       = 'block';
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
// ══════════════════════════════════════════════════════════════
async function saveSlot() {
  if (isSaving) return;   // FIX: prevent double-submit
  if (!selectedDate) { showToast('Please select a date first.', 'error'); return; }

  const start = document.getElementById('f-start').value;
  const end   = document.getElementById('f-end').value;
  if (start >= end) { showToast('End time must be after start time.', 'error'); return; }

  const type  = document.getElementById('f-type').value;
  const maxB  = parseInt(document.getElementById('f-max').value) || 1;
  const recur = document.getElementById('f-recur').value;

  // FIX: numeric Date constructor — avoids UTC midnight timezone shift
  const [y, m1, d] = selectedDate.split('-').map(Number);
  const dayName    = DAY_NAMES[makeDate(y, m1, d).getDay()];

  // FIX: disable button while saving
  isSaving = true;
  const btn = document.getElementById('save-slot-btn');
  if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

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
      is_available:       true,
    });

    // FIX: single re-fan covers the whole month correctly
    fanOutSlots();
    renderCalendar();
    renderSlotStats();
    renderUpcoming();

    const syncMsg = data.google_synced ? ' & synced to Google Calendar ✓' : '';
    showToast(`Slot added for every ${dayName}${syncMsg}`);
    clearSelectedDate();

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

function resetForm() {
  clearSelectedDate();
  document.getElementById('f-start').value = '09:00';
  document.getElementById('f-end').value   = '10:00';
  document.getElementById('f-type').value  = 'consult';
  document.getElementById('f-max').value   = '1';
  document.getElementById('f-recur').value = 'once';
}

// ══════════════════════════════════════════════════════════════
//  SLOT DETAIL MODAL
// ══════════════════════════════════════════════════════════════
function openSlotModal(key, dayNum, dayName) {
  // FIX: numeric constructor
  const [y, m1, d] = key.split('-').map(Number);
  const dateObj    = makeDate(y, m1, d);
  dayName = dayName || DAY_NAMES[dateObj.getDay()];

  document.getElementById('smd-title').textContent = `Slots for ${MONTHS[m1 - 1]} ${d}`;
  document.getElementById('smd-sub').textContent   = dayName;

  const daySlots = slots[key] || [];
  const list     = document.getElementById('smd-list');
  const empty    = document.getElementById('smd-empty');
  list.innerHTML = '';

  if (daySlots.length === 0) {
    list.style.display  = 'none';
    empty.style.display = 'block';
  } else {
    list.style.display  = 'flex';
    empty.style.display = 'none';

    daySlots.forEach((s, i) => {
      const row = document.createElement('div');
      row.className = 'slot-detail-row';

      const startStr = hr12(s.start || s.start_time);
      const endStr   = hr12(s.end   || s.end_time);

      const bookedBadge = (s.booked || !s.is_available)
        ? `<span class="badge badge-green" style="font-size:10px;padding:2px 7px">Booked${s.teamName ? ' · ' + s.teamName : ''}</span>`
        : `<span class="badge badge-gray"  style="font-size:10px;padding:2px 7px">Open</span>`;

      const recurBadge = (s.recur && s.recur !== 'once')
        ? `<span class="recur-chip">${s.recur === 'weekly' ? '↺ Weekly' : '↺ Bi-weekly'}</span>`
        : '';

      const gcalBadge = s.google_event_id
        ? `<span style="font-size:10px;color:var(--accent)">📅 GCal synced</span>`
        : '';

      row.innerHTML = `
        <div class="slot-detail-time">${startStr}–${endStr}</div>
        <div class="slot-detail-info">
          <div style="font-weight:600;font-size:12px;color:var(--text);margin-bottom:3px">
            ${TYPE_LABELS[s.type] || 'Consultation'} ${recurBadge}
          </div>
          <div style="display:flex;gap:5px;align-items:center;flex-wrap:wrap">
            ${bookedBadge}
            <span style="font-size:10px;color:var(--text3)">Max ${s.maxBookings || s.consultation_limit} team${(s.maxBookings || s.consultation_limit) > 1 ? 's' : ''}</span>
            ${gcalBadge}
          </div>
          ${s.topic ? `<div style="font-size:11px;color:var(--text2);margin-top:4px;font-style:italic">"${s.topic}"</div>` : ''}
        </div>
        <div style="display:flex;flex-direction:column;gap:4px">
          ${s.schedule_id ? `
          <button class="slot-delete-btn" onclick="toggleSlot(${s.schedule_id},'${key}',${i})"
                  title="${s.is_available ? 'Mark unavailable' : 'Mark available'}" style="margin-bottom:2px">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
            </svg>
          </button>` : ''}
          <button class="slot-delete-btn" onclick="deleteSlot('${key}',${i}${s.schedule_id ? ',' + s.schedule_id : ''})" title="Delete slot">
            <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
          </button>
        </div>`;
      list.appendChild(row);
    });
  }

  document.getElementById('slot-modal-overlay').classList.add('open');
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
    closeSlotModal();
    showToast(data.is_available ? 'Slot marked as available.' : 'Slot marked as unavailable.');
  } catch (e) {
    showToast('Failed to update slot: ' + e.message, 'error');
  }
}

// ── Delete slot ────────────────────────────────────────────────
async function deleteSlot(key, index, scheduleId) {
  try {
    if (scheduleId) {
      await apiFetch(`/instructor/schedule/slots/${scheduleId}`, { method: 'DELETE' });
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
    closeSlotModal();
    showToast('Slot removed.');
  } catch (e) {
    showToast('Failed to delete slot: ' + e.message, 'error');
  }
}

function closeSlotModal() {
  document.getElementById('slot-modal-overlay').classList.remove('open');
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

  const today    = new Date();
  const todayKey = formatKey(today.getFullYear(), today.getMonth(), today.getDate());
  const upcoming = [];

  Object.entries(slots)
    .filter(([k]) => k > todayKey)
    .sort(([a], [b]) => a.localeCompare(b))
    .forEach(([k, daySlots]) => {
      daySlots.filter(s => s.booked || !s.is_available).forEach(s => {
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
//  EXPERT PROFILE PANEL
// ══════════════════════════════════════════════════════════════
function openExpertProfile() {
  document.getElementById('ep-overlay').classList.add('open');
}

function closeExpertProfile() {
  document.getElementById('ep-overlay').classList.remove('open');
}

function handleEpOverlay(e) {
  if (e.target === document.getElementById('ep-overlay')) closeExpertProfile();
}

function renderExpertTags() {
  EP_TAGS_EL.innerHTML = '';
  expertTags.forEach((tag, i) => {
    const span = document.createElement('span');
    span.className = 'ep-tag';
    span.innerHTML = `${tag} <button onclick="removeTag(${i})" style="background:none;border:none;cursor:pointer;color:inherit;padding:0 0 0 4px;font-size:12px">×</button>`;
    EP_TAGS_EL.appendChild(span);
  });
}

function addTag() {
  const val = EP_INPUT.value.trim();
  if (!val) return;
  if (!expertTags.includes(val)) {
    expertTags.push(val);
    renderExpertTags();
  }
  EP_INPUT.value = '';
}

function removeTag(i) {
  expertTags.splice(i, 1);
  renderExpertTags();
}

function handleTagKey(e) {
  if (e.key === 'Enter') { e.preventDefault(); addTag(); }
}

async function saveExpertProfile() {
  try {
    await apiFetch('/instructor/profile/expertise', {
      method: 'POST',
      body: JSON.stringify({ tags: expertTags }),
    });
    showToast('Profile saved ✓');
    closeExpertProfile();
  } catch (e) {
    showToast('Saved locally (server error: ' + e.message + ')', 'error');
    closeExpertProfile();
  }
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
</script>
</body>
</html>