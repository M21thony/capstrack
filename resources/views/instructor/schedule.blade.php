<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CapsTRACK â€” Expert Portal</title>
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
    <div class="mode-banner add-banner" id="add-banner">
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
              <div class="cal-month-label" id="cal-month-label">Loadingâ€¦</div>
            </div>
            <div class="cal-controls">
              <button class="cal-nav-btn" onclick="prevMonth()">
                <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
              </button>
              <div class="cal-month-display" id="cal-month-display">â€”</div>
              <button class="cal-nav-btn" onclick="nextMonth()">
                <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
              </button>
              <div class="mode-toggle">
                <button class="mode-btn active" id="btn-view" onclick="setMode('view')">View</button>
                <button class="mode-btn" id="btn-add" onclick="setMode('add')">+ Add Slot</button>
                <button class="mode-btn block-mode" id="btn-block" onclick="setMode('block')">ðŸš« Block</button>
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
            Legend, Guide & Tips
          </div>

          <!-- Legend swatches -->
          <div class="legend-grid">
            <div class="legend-item"><div class="legend-swatch sw-consult"></div>Consultation</div>
            <div class="legend-item"><div class="legend-swatch sw-docreview"></div>Doc Review</div>
            <div class="legend-item"><div class="legend-swatch sw-defense"></div>Defense</div>
            <div class="legend-item"><div class="legend-swatch sw-booked"></div>Booked</div>
            <div class="legend-item"><div class="legend-swatch sw-blocked"></div>Blocked Day</div>
            <div class="legend-item"><div class="legend-swatch sw-today"></div>Today</div>
          </div>

          <div class="guide-tips-grid">
            <!-- How to Use Guide -->
            <div class="guide-box">
              <div class="guide-box-title">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                How to Use
              </div>
              <div class="guide-list">
                <div class="guide-step"><div class="guide-step-num">1</div>Switch to <strong style="color:var(--accent)">Add Slot</strong> mode then click any date on the calendar.</div>
                <div class="guide-step"><div class="guide-step-num">2</div>Fill in the time, slot type, and max bookings in the panel that appears.</div>
                <div class="guide-step"><div class="guide-step-num">3</div>Choose <strong style="color:var(--accent)">One-time</strong> or <strong style="color:var(--accent)">Recurring</strong> and click Save.</div>
                <div class="guide-step"><div class="guide-step-num">4</div>Use <strong style="color:var(--red)">Block</strong> to mark days you're unavailable â€” students won't be able to book them.</div>
                <div class="guide-step"><div class="guide-step-num">5</div>Click any date in View mode to see and manage existing slots.</div>
              </div>
            </div>
            <!-- Tips -->
            <div class="guide-box">
              <div class="guide-box-title">
                <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                Tips for Experts
              </div>
              <div class="guide-list">
                <div class="tip-item"><div class="tip-icon">ðŸ’¡</div><span>Set recurring slots for days you're regularly available â€” saves you time every week.</span></div>
                <div class="tip-item"><div class="tip-icon">âš ï¸</div><span>Block entire days in advance for holidays, meetings, or personal leave.</span></div>
                <div class="tip-item"><div class="tip-icon">ðŸ“‹</div><span>Keep max bookings at 1â€“2 per slot for focused, quality consultations.</span></div>
                <div class="tip-item"><div class="tip-icon">ðŸ”µ</div><span>A cyan dot on a date means it has recurring slots. These apply every week automatically.</span></div>
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

          <!-- Selected date chip -->
          <div class="selected-date-chip" id="selected-date-chip" style="display:none">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span id="selected-date-text">No date selected</span>
            <button class="clear-date" onclick="clearSelectedDate()">
              <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <div id="no-date-hint" style="font-size:12px;color:var(--text3);margin-bottom:12px;padding:10px;background:var(--bg3);border-radius:8px;border:1px dashed var(--border);text-align:center;">
            â† Click a date on the calendar to select it
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
            <button class="btn btn-primary content" onclick="saveSlot()">
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
            <div class="upcoming-count" id="apt-count">5</div>
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
<div class="slot-modal-overlay" id="block-confirm-overlay">
  <div class="block-confirm">
    <div class="block-confirm-title" id="bc-title">Block this date?</div>
    <div class="block-confirm-sub" id="bc-sub">Students won't be able to book any slot on this day.</div>
    <div class="block-confirm-actions">
      <button class="btn btn-danger-solid btn content" id="bc-confirm-btn" onclick="confirmBlock()">Block Date</button>
    </div>
  </div>
</div>
<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     EXPERT PROFILE SIDE PANEL
     Sits flush against the right edge of the
     sidebar (left: 250px). No dark overlay.
     â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<div class="ep-overlay" id="ep-overlay" onclick="handleEpOverlay(event)">
  <div class="ep-modal" id="ep-modal">

    <!-- Sticky Header -->
    <div class="ep-header">
      <div class="ep-avatar">CS</div>
      <div style="flex:1;min-width:0">
        <div class="ep-header-name">Dr. Neil A. Inocencio</div>
        <div class="ep-header-sub">Capstone Expert Â· Active</div>
      </div>
      <button class="ep-close" onclick="closeExpertProfile()">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <!-- Scrollable Body -->
    <div class="ep-body">

      <!-- Full Name â€” readonly -->
      <div class="ep-field">
        <div class="ep-label">Full Name</div>
        <div class="ep-value readonly">
          <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Dr. Neil A. Inocencio
        </div>
      </div>

      <!-- Email â€” readonly -->
      <div class="ep-field">
        <div class="ep-label">Institutional Email</div>
        <div class="ep-value readonly">
          <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          nainocencio@cspc.edu.ph
        </div>
      </div>

      <div class="ep-divider"></div>

        <!-- Roles â€” readonly, assigned by admin -->
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

      <!-- Max Teams â€” readonly -->
      <div class="ep-field">
        <div class="ep-label">Team Capacity</div>
        <div class="ep-value readonly">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Max 4 teams Â· Currently advising 3
        </div>
      </div>

      <div class="ep-divider"></div>

      <!-- Expertise Tags â€” editable -->
      <div class="ep-field">
        <div class="ep-label">Areas of Expertise</div>
        <div class="ep-tags-wrap" id="ep-tags-wrap"></div>
        <div class="ep-tag-input-row">
          <input
            class="ep-tag-input"
            id="ep-tag-input"
            type="text"
            placeholder="Add expertiseâ€¦"
            onkeydown="handleTagKey(event)"
          />
          <button class="ep-tag-add-btn" onclick="addTag()">+ Add</button>
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:7px">
          Press Enter or click Add. Click Ã— to remove.
        </div>
      </div>

    </div>

    <!-- Sticky Footer -->
    <div class="ep-footer">
      <button class="btn content btn-ghost" style="color: #FFFF00" onclick="closeExpertProfile()">Close</button>
      <button class="btn btn-primary" onclick="saveExpertProfile()">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Save
      </button>
    </div>

  </div>
</div>
<script>
    // Profile Modal Logic
  const EP_OVERLAY = document.getElementById('ep-overlay');
const EP_TRIGGER = document.getElementById('ep-trigger');
const EP_TAGS_EL = document.getElementById('ep-tags-wrap');
const EP_INPUT   = document.getElementById('ep-tag-input');

const DAYS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const TYPE_LABELS = { consult:'Consultation', docreview:'Doc Review', defense:'Defense' };
const TYPE_CLASS  = { consult:'type-consult', docreview:'type-docreview', defense:'type-defense' };

let viewYear, viewMonth;
let selectedDate = null; // 'YYYY-MM-DD'
let currentMode = 'view'; // 'view' | 'add' | 'block'
let pendingBlockDate = null;

// Slots: { 'YYYY-MM-DD': [ {id, start, end, type, maxBookings, recur, booked} ] }
let slots = {};
// Blocked dates set
let blockedDates = new Set();

// Seed some demo data
(function seedData(){
  const today = new Date();
  const y = today.getFullYear(), m = today.getMonth();
  const pad = n => String(n).padStart(2,'0');

  // A few sample slots this month
  const dates = [
    new Date(y, m, today.getDate() + 1),
    new Date(y, m, today.getDate() + 2),
    new Date(y, m, today.getDate() + 3),
    new Date(y, m, today.getDate() + 5),
    new Date(y, m, today.getDate() + 7),
    new Date(y, m, today.getDate() + 8),
  ];

  const sampleSlots = [
    { start:'09:00', end:'10:00', type:'consult',   maxBookings:2, recur:'weekly',  booked:true,  teamName:'Team Nexus',   topic:'Chapter 3 Discussion' },
    { start:'14:00', end:'15:00', type:'docreview', maxBookings:1, recur:'once',    booked:true,  teamName:'Team Alpha',   topic:'Document Formatting Check' },
    { start:'10:00', end:'11:00', type:'consult',   maxBookings:1, recur:'weekly',  booked:false, teamName:'',             topic:'' },
    { start:'15:00', end:'16:00', type:'defense',   maxBookings:3, recur:'biweekly',booked:true,  teamName:'Team Sigma',   topic:'Final Defense Rehearsal' },
    { start:'13:00', end:'14:00', type:'consult',   maxBookings:2, recur:'once',    booked:false, teamName:'',             topic:'' },
    { start:'09:30', end:'10:30', type:'docreview', maxBookings:1, recur:'weekly',  booked:true,  teamName:'Team Orion',   topic:'Abstract & Introduction Review' },
  ];

  dates.forEach((d, i) => {
    if(d.getDay() === 0 || d.getDay() === 6) return; // skip weekends
    const key = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
    const s = sampleSlots[i] || sampleSlots[0];
    if(!slots[key]) slots[key] = [];
    slots[key].push({ id: Date.now()+i, ...s });
  });

  // Block a couple of future dates
  const blockDay1 = new Date(y, m, today.getDate() + 4);
  const blockDay2 = new Date(y, m, today.getDate() + 10);
  [blockDay1, blockDay2].forEach(d => {
    if(d.getDay() !== 0 && d.getDay() !== 6) {
      blockedDates.add(`${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`);
    }
  });
})();

// Upcoming appointments (booked slots in the future)
const UPCOMING = [
  { team:'Team Nexus',   date:'Tomorrow',   time:'9:00â€“10:00 AM',  type:'consult',   topic:'Chapter 3 Discussion' },
  { team:'Team Alpha',   date:'In 2 days',  time:'2:00â€“3:00 PM',   type:'docreview', topic:'Document Formatting Check' },
  { team:'Team Sigma',   date:'In 5 days',  time:'3:00â€“4:00 PM',   type:'defense',   topic:'Final Defense Rehearsal' },
  { team:'Team Orion',   date:'In 8 days',  time:'9:30â€“10:30 AM',  type:'docreview', topic:'Abstract & Introduction Review' },
  { team:'Team Phoenix', date:'In 10 days', time:'1:00â€“2:00 PM',   type:'consult',   topic:'Methodology Consultation' },
];

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  CALENDAR RENDER
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function initCalendar() {
  const now = new Date();
  viewYear  = now.getFullYear();
  viewMonth = now.getMonth();
  renderCalendar();
  renderUpcoming();
  renderSlotStats();
}

function prevMonth() { viewMonth--; if(viewMonth<0){viewMonth=11;viewYear--;} renderCalendar(); }
function nextMonth() { viewMonth++; if(viewMonth>11){viewMonth=0;viewYear++;} renderCalendar(); }

function formatKey(y,m,d) {
  return `${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
}

function renderCalendar() {
  const grid = document.getElementById('cal-grid');
  const today = new Date();
  const todayKey = formatKey(today.getFullYear(), today.getMonth(), today.getDate());

  // Update header
  document.getElementById('cal-month-display').textContent = `${MONTHS[viewMonth].substring(0,3).toUpperCase()} ${viewYear}`;
  document.getElementById('cal-month-label').textContent = `${MONTHS[viewMonth]} ${viewYear}`;

  grid.innerHTML = '';

  // Day labels (Monâ€“Sun layout, skip Sat/Sun for weekday focus but show full month)
  const dayLabels = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  dayLabels.forEach(d => {
    const el = document.createElement('div');
    el.className = 'cal-day-label';
    el.textContent = d;
    grid.appendChild(el);
  });

  // First day of month
  const firstDay = new Date(viewYear, viewMonth, 1).getDay();
  const daysInMonth = new Date(viewYear, viewMonth+1, 0).getDate();
  const daysInPrevMonth = new Date(viewYear, viewMonth, 0).getDate();

  // Previous month padding
  for(let i = firstDay - 1; i >= 0; i--) {
    const el = document.createElement('div');
    el.className = 'cal-cell other-month';
    el.innerHTML = `<div class="cal-date-num">${daysInPrevMonth - i}</div>`;
    grid.appendChild(el);
  }

  // Current month days
  for(let d = 1; d <= daysInMonth; d++) {
    const key = formatKey(viewYear, viewMonth, d);
    const isToday = key === todayKey;
    const isBlocked = blockedDates.has(key);
    const daySlots = slots[key] || [];
    const isPast = new Date(viewYear, viewMonth, d) < new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const isWeekend = new Date(viewYear, viewMonth, d).getDay() === 0 || new Date(viewYear, viewMonth, d).getDay() === 6;

    const el = document.createElement('div');
    el.className = 'cal-cell';
    if(isToday) el.classList.add('today');
    if(isBlocked) el.classList.add('is-blocked');
    if(daySlots.length > 0) el.classList.add('has-slot');
    if(isPast) el.classList.add('past-day');
    if(isWeekend) el.classList.add('past-day'); // treat weekends as non-clickable

    el.dataset.key = key;
    el.dataset.day = d;
    el.onclick = () => handleCellClick(key, d, isPast || isWeekend);

    // Date number
    const dateEl = document.createElement('div');
    dateEl.className = 'cal-date-num';
    dateEl.textContent = d;
    el.appendChild(dateEl);

    // Blocked label
    if(isBlocked) {
      const bl = document.createElement('div');
      bl.className = 'blocked-label';
      bl.textContent = 'âœ• Unavailable';
      el.appendChild(bl);
    } else {
      // Slot pills (max 2 visible)
      const visible = daySlots.slice(0, 2);
      visible.forEach(s => {
        const pill = document.createElement('div');
        let cls = TYPE_CLASS[s.type] || 'type-consult';
        if(s.booked) cls = 'is-booked';
        pill.className = `cal-slot-pill ${cls}`;
        const hr12 = t => { const [h,m] = t.split(':'); const h12 = +h%12||12; return `${h12}:${m}${+h<12?'a':'p'}`; };
        pill.textContent = `${hr12(s.start)} ${TYPE_LABELS[s.type]}`;
        pill.title = `${hr12(s.start)}â€“${hr12(s.end)} Â· ${TYPE_LABELS[s.type]} Â· Max ${s.maxBookings}`;
        el.appendChild(pill);
      });
      if(daySlots.length > 2) {
        const more = document.createElement('div');
        more.className = 'cal-more';
        more.textContent = `+${daySlots.length-2} more`;
        el.appendChild(more);
      }
      // Slot count badge
      if(daySlots.length > 0) {
        const badge = document.createElement('div');
        badge.className = 'cell-slot-count';
        badge.textContent = daySlots.length;
        el.appendChild(badge);
      }
      // Recurring indicator
      const hasRecurring = daySlots.some(s => s.recur !== 'once');
      if(hasRecurring) {
        const ri = document.createElement('div');
        ri.className = 'recur-indicator';
        ri.title = 'Has recurring slot';
        el.appendChild(ri);
      }
    }

    grid.appendChild(el);
  }

  // Fill remaining cells
  const totalCells = firstDay + daysInMonth;
  const remainder = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
  for(let d = 1; d <= remainder; d++) {
    const el = document.createElement('div');
    el.className = 'cal-cell other-month';
    el.innerHTML = `<div class="cal-date-num">${d}</div>`;
    grid.appendChild(el);
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  CELL CLICK HANDLER
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function handleCellClick(key, dayNum, blocked) {
  if(currentMode === 'view') {
    openSlotModal(key, dayNum);
    return;
  }
  if(currentMode === 'add') {
    if(blockedDates.has(key)) { showToast('âš ï¸ This date is blocked. Unblock it first.','error'); return; }
    selectDate(key, dayNum);
    return;
  }
  if(currentMode === 'block') {
    if(blockedDates.has(key)) {
      // Unblock
      blockedDates.delete(key);
      renderCalendar();
      showToast('Date unblocked â€” students can now book this day.');
    } else {
      pendingBlockDate = { key, dayNum };
      openBlockConfirm(key, dayNum);
    }
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  MODE MANAGEMENT
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function setMode(mode) {
  currentMode = mode;
  document.body.classList.remove('add-mode','block-mode');
  document.getElementById('add-banner').style.display = 'none';
  document.getElementById('block-banner').style.display = 'none';
  document.getElementById('add-slot-form').style.display = 'none';

  ['btn-view','btn-add','btn-block'].forEach(id => document.getElementById(id).classList.remove('active'));

  if(mode === 'add') {
    document.body.classList.add('add-mode');
    document.getElementById('add-banner').style.display = 'flex';
    document.getElementById('add-slot-form').style.display = 'block';
    document.getElementById('btn-add').classList.add('active');
  } else if(mode === 'block') {
    document.body.classList.add('block-mode');
    document.getElementById('block-banner').style.display = 'flex';
    document.getElementById('btn-block').classList.add('active');
  } else {
    document.getElementById('btn-view').classList.add('active');
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  DATE SELECTION (Add mode)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function selectDate(key, dayNum) {
  selectedDate = key;
  const [y, m, d] = key.split('-').map(Number);
  const dateObj = new Date(y, m-1, d);
  const label = `${DAYS[dateObj.getDay()]}, ${MONTHS[m-1]} ${d}, ${y}`;
  document.getElementById('selected-date-text').textContent = label;
  document.getElementById('selected-date-chip').style.display = 'flex';
  document.getElementById('no-date-hint').style.display = 'none';
}

function clearSelectedDate() {
  selectedDate = null;
  document.getElementById('selected-date-chip').style.display = 'none';
  document.getElementById('no-date-hint').style.display = 'block';
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  SAVE SLOT
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function saveSlot() {
  if(!selectedDate) { showToast('Please select a date first.','error'); return; }
  const start = document.getElementById('f-start').value;
  const end   = document.getElementById('f-end').value;
  if(start >= end) { showToast('End time must be after start time.','error'); return; }

  const type   = document.getElementById('f-type').value;
  const maxB   = parseInt(document.getElementById('f-max').value) || 1;
  const recur  = document.getElementById('f-recur').value;

  if(!slots[selectedDate]) slots[selectedDate] = [];

  const newSlot = { id: Date.now(), start, end, type, maxBookings: maxB, recur, booked: false, teamName:'', topic:'' };
  slots[selectedDate].push(newSlot);

  // If recurring, also add to future dates of same weekday this month
  if(recur === 'weekly' || recur === 'biweekly') {
    const [y, m, d] = selectedDate.split('-').map(Number);
    const base = new Date(y, m-1, d);
    const targetDay = base.getDay();
    const interval = recur === 'weekly' ? 7 : 14;
    for(let i = interval; i <= 42; i += interval) {
      const next = new Date(base.getTime() + i * 86400000);
      if(next.getMonth() !== base.getMonth()) break;
      const nKey = formatKey(next.getFullYear(), next.getMonth(), next.getDate());
      if(!slots[nKey]) slots[nKey] = [];
      slots[nKey].push({ ...newSlot, id: Date.now()+i });
    }
  }

  renderCalendar();
  renderSlotStats();
  showToast(`Slot added: ${TYPE_LABELS[type]} on ${document.getElementById('selected-date-text').textContent}`);
  clearSelectedDate();
}

function resetForm() {
  clearSelectedDate();
  document.getElementById('f-start').value = '09:00';
  document.getElementById('f-end').value = '10:00';
  document.getElementById('f-type').value = 'consult';
  document.getElementById('f-max').value = '1';
  document.getElementById('f-recur').value = 'once';
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  SLOT DETAIL MODAL
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function openSlotModal(key, dayNum) {
  const [y,m,d] = key.split('-').map(Number);
  const dateObj = new Date(y, m-1, d);
  document.getElementById('smd-title').textContent = `Slots for ${MONTHS[m-1]} ${d}`;
  document.getElementById('smd-sub').textContent = DAYS[dateObj.getDay()];

  const daySlots = slots[key] || [];
  const list = document.getElementById('smd-list');
  const empty = document.getElementById('smd-empty');
  list.innerHTML = '';

  if(daySlots.length === 0) {
    list.style.display = 'none';
    empty.style.display = 'block';
  } else {
    list.style.display = 'flex';
    empty.style.display = 'none';
    const hr12 = t => { const [h,mm] = t.split(':'); const h12 = +h%12||12; return `${h12}:${mm} ${+h<12?'AM':'PM'}`; };
    daySlots.forEach((s, i) => {
      const row = document.createElement('div');
      row.className = 'slot-detail-row';
      const bookedBadge = s.booked ? `<span class="badge badge-green" style="font-size:10px;padding:2px 7px">Booked Â· ${s.teamName}</span>` : `<span class="badge badge-gray" style="font-size:10px;padding:2px 7px">Open</span>`;
      const recurBadge = s.recur !== 'once' ? `<span class="recur-chip">${s.recur === 'weekly' ? 'â†º Weekly' : 'â†º Bi-weekly'}</span>` : '';
      row.innerHTML = `
        <div class="slot-detail-time">${hr12(s.start)}â€“${hr12(s.end)}</div>
        <div class="slot-detail-info">
          <div style="font-weight:600;font-size:12px;color:var(--text);margin-bottom:3px">${TYPE_LABELS[s.type]} ${recurBadge}</div>
          <div style="display:flex;gap:5px;align-items:center;flex-wrap:wrap">${bookedBadge}<span style="font-size:10px;color:var(--text3)">Max ${s.maxBookings} team${s.maxBookings>1?'s':''}</span></div>
          ${s.booked && s.topic ? `<div style="font-size:11px;color:var(--text2);margin-top:4px;font-style:italic">"${s.topic}"</div>` : ''}
        </div>
        <button class="slot-delete-btn" onclick="deleteSlot('${key}',${i})" title="Delete slot">
          <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        </button>`;
      list.appendChild(row);
    });
  }

  document.getElementById('slot-modal-overlay').classList.add('open');
}

function deleteSlot(key, index) {
  if(slots[key]) {
    slots[key].splice(index, 1);
    if(slots[key].length === 0) delete slots[key];
  }
  renderCalendar();
  renderSlotStats();
  closeSlotModal();
  showToast('Slot removed.');
}

function closeSlotModal() { document.getElementById('slot-modal-overlay').classList.remove('open'); }

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  BLOCK CONFIRM
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function openBlockConfirm(key, dayNum) {
  const [y,m,d] = key.split('-').map(Number);
  document.getElementById('bc-title').textContent = `Block ${MONTHS[m-1]} ${d}, ${y}?`;
  document.getElementById('bc-sub').textContent = `Students won't be able to book any slot on this day. Any existing open slots will be hidden to students.`;
  document.getElementById('block-confirm-overlay').classList.add('open');
}
function closeBlockConfirm() {
  document.getElementById('block-confirm-overlay').classList.remove('open');
  pendingBlockDate = null;
}
function confirmBlock() {
  if(!pendingBlockDate) return;
  blockedDates.add(pendingBlockDate.key);
  renderCalendar();
  closeBlockConfirm();
  showToast('Date blocked â€” students cannot book this day.');
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  UPCOMING APPOINTMENTS
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function renderUpcoming() {
  const list = document.getElementById('upcoming-list');
  document.getElementById('apt-count').textContent = UPCOMING.length;
  list.innerHTML = '';

  if(UPCOMING.length === 0) {
    list.innerHTML = '<div class="no-upcoming">No upcoming appointments.</div>';
    return;
  }

  UPCOMING.forEach(apt => {
    const typeColors = {
      consult: { bg:'rgba(29,78,216,0.1)', color:'var(--blue)' },
      docreview: { bg:'rgba(124,58,237,0.1)', color:'var(--purple)' },
      defense: { bg:'rgba(180,83,9,0.1)', color:'var(--amber)' },
    };
    const tc = typeColors[apt.type] || typeColors.consult;
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
      <div class="apt-topic">${apt.topic}</div>`;
    list.appendChild(row);
  });
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  SLOT STATS
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function renderSlotStats() {
  const container = document.getElementById('slot-stats');
  container.innerHTML = '';

  let counts = { consult: 0, docreview: 0, defense: 0 };
  Object.values(slots).forEach(daySlots => {
    daySlots.forEach(s => { if(counts[s.type] !== undefined) counts[s.type]++; });
  });
  const total = Object.values(counts).reduce((a,b)=>a+b,0);
  if(total === 0) { container.innerHTML = '<div style="font-size:12px;color:var(--text3);font-style:italic;text-align:center;padding:8px">No slots this month.</div>'; return; }

  const colors = { consult: 'var(--blue)', docreview: 'var(--purple)', defense: 'var(--amber)' };
  Object.entries(counts).forEach(([type, count]) => {
    const pct = total ? Math.round(count/total*100) : 0;
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

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  TOAST
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
let toastTimer;
function showToast(msg, type = 'success') {
  const toast = document.getElementById('sched-toast');
  document.getElementById('toast-msg').textContent = msg;
  toast.style.borderLeftColor = type === 'error' ? 'var(--red)' : 'var(--accent)';
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 3000);
}

// â”€â”€ INIT â”€â”€
initCalendar();
</script>
</body>
</html>
