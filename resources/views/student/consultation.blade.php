<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Consultation - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/Styles/consultation.css')
<x-dashboard.styles />
<style>
  @keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .sk-overlay { position: absolute; inset: 0; background: var(--bg); z-index: 10; }
  .sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }

  #consult-toast { position: fixed; bottom: 30px; right: 30px; background: var(--navy-dark); color: #fff; padding: 14px 22px; border-radius: 12px; font-size: 13px; font-weight: 500; z-index: 2000; box-shadow: 0 8px 30px rgba(0,0,0,0.3); opacity: 0; transform: translateY(12px); pointer-events: none; transition: opacity 0.25s ease, transform 0.25s ease; border-left: 4px solid transparent; display: flex; align-items: center; gap: 10px; }
  #consult-toast.show { opacity: 1; transform: translateY(0); }
  #consult-toast.success { border-left-color: var(--green); }
  #consult-toast.error { border-left-color: var(--red); }
</style>
</head>
<body>
@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Consultation</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'consultation'])
    <div id="contentWrapper" style="position: relative;">
<div id="skeleton-content" class="sk-overlay">
      <div class="consultation-container">
        <div class="left-panel">
          <div class="expert-filter-area">
            <div class="sk" style="width:110px;height:14px;border-radius:4px"></div>
            <div class="sk" style="flex:1;height:36px;border-radius:8px"></div>
          </div>
          <div class="calendar-card">
            <div class="sk" style="width:140px;height:16px;border-radius:4px;margin-bottom:14px"></div>
            <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px">
              @for ($i = 0; $i < 35; $i++)
              <div class="sk" style="height:60px;border-radius:6px"></div>
              @endfor
            </div>
          </div>
        </div>
        <div class="appt-sidebar">
          <div class="sessions-card">
            <div class="sessions-card-header">
              <div class="sk" style="width:130px;height:12px;border-radius:4px"></div>
            </div>
            <div style="padding:12px">
              <div class="sk" style="width:100%;height:60px;border-radius:8px"></div>
            </div>
          </div>
          <div class="sessions-card">
            <div class="sessions-card-header">
              <div class="sk" style="width:100px;height:12px;border-radius:4px"></div>
            </div>
            <div style="padding:12px">
              <div class="sk" style="width:100%;height:60px;border-radius:8px"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="real-content" style="opacity:0">
  <div class="consultation-container">

    <div class="left-panel">

      <div class="expert-filter-area">
        <label class="pm-label" style="margin-bottom:0">Target Expert:</label>
        <div class="expert-dropdown">
          <select id="expertSelect" onchange="onExpertChange()">
            @if($experts->isEmpty())
              <option value="" disabled selected>No adviser/expert assigned to your team yet</option>
            @endif
            @foreach($experts as $expert)
              <option value="{{ $expert->expert_id }}" @selected($expert->expert_id === $defaultExpertId)>
                {{ $expert->first_name }} {{ $expert->last_name }} ({{ ucfirst($expert->role_name) }})
              </option>
            @endforeach
          </select>
        </div>
        <div style="font-size:11px; color:var(--text3); max-width:150px; line-height:1.3">
          Availability syncs with their schedule.
        </div>
      </div>

      <div class="calendar-card">
        <div class="calendar-header">
          <div>
            <h2 style="font-size:18px; font-weight:600" id="cal-month">Select an Expert</h2>
            <p style="font-size:11px; color:var(--text3)" id="cal-hint">Choose an expert above to view their available consultation slots.</p>
          </div>
          <div class="cal-nav-group">
            <div class="cal-nav">
              <button class="cal-btn" onclick="changeMonth(-1)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg></button>
              <button class="cal-btn" onclick="changeMonth(1)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg></button>
            </div>
          </div>
        </div>

        <div class="calendar-grid" id="calendar-grid">
          <div class="day-label">Sun</div><div class="day-label">Mon</div><div class="day-label">Tue</div>
          <div class="day-label">Wed</div><div class="day-label">Thu</div><div class="day-label">Fri</div><div class="day-label">Sat</div>
        </div>
      </div>

      <div class="cal-notes">
        <h4 style="font-size: 11px; color: var(--text); text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px;">Consultation Legend & Guide</h4>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px;">
          <div style="display: flex; flex-direction: column; gap: 4px;">
            <div class="note-item">
              <div style="width: 12px; height: 12px; background: var(--bg2); border: 1px solid var(--border); position: relative; overflow: hidden; border-radius: 3px; flex-shrink: 0;">
                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to top right, transparent 45%, var(--text3) 50%, transparent 55%); opacity: 0.5;"></div>
                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to top left, transparent 45%, var(--text3) 50%, transparent 55%); opacity: 0.5;"></div>
              </div>
              <span><strong>Unavailable:</strong> Expert is blocked or fully booked.</span>
            </div>
            <div class="note-item">
              <div style="width: 12px; height: 12px; background: #526D82; border-radius: 3px; opacity: 0.5; flex-shrink: 0;"></div>
              <span><strong>Past Dates:</strong> Previous dates (Dark Gray) are disabled.</span>
            </div>
          </div>

          <div style="display: flex; flex-direction: column; gap: 4px;">
            <div class="note-item">
              <span style="color: var(--accent2); font-weight: 700; font-size: 13px;">✔</span>
              <span><strong>Available:</strong> Click a date to book a slot.</span>
            </div>
            <div class="note-item">
              <div style="width: 6px; height: 6px; background: var(--accent); border-radius: 50%; flex-shrink: 0; margin-top: 3px;"></div>
              <span><strong>Your Booking:</strong> Pending appointments.</span>
            </div>
          </div>
        </div>

        <div style="margin-top: 6px; padding-top: 6px; border-top: 1px dashed var(--border); font-size: 10px; color: var(--text3); font-style: italic;">
          CapsTip: Select an expert, then click an available date to book a specific time slot.
        </div>
      </div>
    </div>

    @php
      // Same type→color mapping as instructor/schedule's renderUpcoming() typeColors,
      // so a slot's type badge looks identical on both pages.
      $slotTypeColors = [
        'consult'   => ['bg' => 'rgba(29,78,216,0.1)',  'color' => 'var(--blue)'],
        'docreview' => ['bg' => 'rgba(124,58,237,0.1)', 'color' => 'var(--purple)'],
        'defense'   => ['bg' => 'rgba(180,83,9,0.1)',   'color' => 'var(--amber)'],
      ];
    @endphp

    <div class="appt-sidebar">
      <div class="sessions-card">
        <div class="sessions-card-header">
          <h3 class="sessions-card-title">Ongoing / Booked Session</h3>
          <span class="sessions-card-count">{{ $upcomingBookings->count() }}</span>
        </div>
        <div id="upcoming-bookings-list" class="session-list">
          @forelse($upcomingBookings as $booking)
            @php $tc = $slotTypeColors[$booking->schedule->slot_type ?? 'consult'] ?? $slotTypeColors['consult']; @endphp
            <div class="session-list-item session-list-item-upcoming">
              <div class="appt-row-top">
                <div class="appt-expert">{{ $booking->schedule->expert->first_name ?? 'Expert' }} {{ $booking->schedule->expert->last_name ?? '' }}</div>
                <div class="appt-badges">
                  <span class="appt-type-badge" style="background:{{ $tc['bg'] }};color:{{ $tc['color'] }}">{{ ucfirst($booking->schedule->slot_type ?? 'Consult') }}</span>
                  <span class="appt-status-badge appt-status-badge-upcoming">
                    {{ $booking->session_date && $booking->session_date->isToday() ? 'Today' : 'Upcoming' }}
                  </span>
                </div>
              </div>
              <div class="appt-meta">
                <span><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>{{ $booking->session_date ? $booking->session_date->format('M d, Y') : $booking->schedule->available_date }}</span>
                <span><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>{{ \Carbon\Carbon::parse($booking->schedule->start_time)->format('h:i A') }}</span>
              </div>
              <div class="appt-team">Team: {{ $booking->team->team_name ?? 'N/A' }}</div>
            </div>
          @empty
            <div class="session-list-empty">No upcoming consultations.</div>
          @endforelse
        </div>
      </div>

      <div class="sessions-card">
        <div class="sessions-card-header">
          <h3 class="sessions-card-title">Recent Sessions</h3>
          <span class="sessions-card-count">{{ $recentBookings->count() }}</span>
        </div>
        <div id="bookings-list" class="session-list">
          @forelse($recentBookings as $booking)
            @php $tc = $slotTypeColors[$booking->schedule->slot_type ?? 'consult'] ?? $slotTypeColors['consult']; @endphp
            <div class="session-list-item session-list-item-past">
              <div class="appt-row-top">
                <div class="appt-expert">{{ $booking->schedule->expert->first_name ?? 'Expert' }} {{ $booking->schedule->expert->last_name ?? '' }}</div>
                <div class="appt-badges">
                  <span class="appt-type-badge" style="background:{{ $tc['bg'] }};color:{{ $tc['color'] }}">{{ ucfirst($booking->schedule->slot_type ?? 'Consult') }}</span>
                  <span class="appt-status-badge">Completed</span>
                </div>
              </div>
              <div class="appt-meta">
                <span><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>{{ $booking->session_date ? $booking->session_date->format('M d, Y') : $booking->schedule->available_date }}</span>
                <span><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>{{ \Carbon\Carbon::parse($booking->schedule->start_time)->format('h:i A') }}</span>
              </div>
              <div class="appt-team">Team: {{ $booking->team->team_name ?? 'N/A' }}</div>
            </div>
          @empty
            <div class="session-list-empty">No past consultations.</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>

  <div class="legal-credits">
    <div class="credits-title">System Developers</div>
    <div class="credits-names">Jolina · John Kenneth · Mark Anthony · Ian Cyrus · Alexa Nicole</div>
  </div>
    </div>
</div>
</div>

<div class="modal-overlay" id="slotSelectModal" onclick="if(event.target === this) closeSlotModal()">
  <div class="modal-content">
    <div style="margin-bottom: 20px;">
      <h2 style="font-size: 18px; font-weight: 600;">Select a Time Slot</h2>
      <p style="font-size: 11px; color: var(--text3);" id="slot-modal-date">Choose an available time for this date.</p>
    </div>
    <div id="slot-modal-list"></div>
    <div class="modal-footer">
      <button class="btn-ghost content" onclick="closeSlotModal()" style="margin-top:8px">Cancel</button>
    </div>
  </div>
</div>

<div id="consult-toast"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var sk = document.getElementById('skeleton-content');
  var rc = document.getElementById('real-content');
  if (sk && rc) {
    requestAnimationFrame(function () {
      setTimeout(function () {
        sk.style.transition = 'opacity 0.3s ease';
        sk.style.opacity = '0';
        rc.style.transition = 'opacity 0.35s ease';
        rc.style.opacity = '1';
        setTimeout(function () {
          sk.remove();
          rc.style.transition = '';
        }, 350);
      }, 600);
    });
  }
});

const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const DAY_NAMES = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

const expertSlots = @json($availableSlots);
// This student's own scheduled sessions, by date — drives the "Your Booking" dot.
const myBookedDates = @json($upcomingBookings->merge($recentBookings)->pluck('session_date')->filter()->map(fn ($d) => $d->format('Y-m-d'))->values());
const slotTypeColors = @json($slotTypeColors);

// Weekly/biweekly slots share a single schedule row across every future
// occurrence of that weekday, so "is this slot booked out" has to be tracked
// per (schedule_id, date) rather than on the row itself — otherwise booking
// one Monday would hide every other Monday too. "schedule_id|YYYY-MM-DD"
// entries here mean that specific occurrence is full.
let fullOccurrences = new Set(@json($fullOccurrences));

function isOccurrenceFull(scheduleId, dateKey) {
  return fullOccurrences.has(scheduleId + '|' + dateKey);
}

function getSlotsForExpert(expertId) {
  return expertSlots.filter(s => s.expert_id == expertId);
}

// A "once" slot is only ever valid on its specific calendar date — matching
// it by weekday name (like weekly/biweekly slots) would make it recur
// forever, showing as available on that weekday in every future month.
function matchesDate(s, dayName, dateKey) {
  if (s.recur === 'once') {
    return s.specific_date === dateKey;
  }
  return s.available_date === dayName;
}

function getSlotsForDate(expertId, dayName, dateKey) {
  return getSlotsForExpert(expertId).filter(s => matchesDate(s, dayName, dateKey) && s.is_available && !isOccurrenceFull(s.schedule_id, dateKey));
}

function formatTime(t) {
  if (!t) return '';
  const [h, m] = t.split(':').map(Number);
  const ampm = h < 12 ? 'AM' : 'PM';
  const h12 = h % 12 || 12;
  return h12 + ':' + String(m).padStart(2, '0') + ' ' + ampm;
}

function renderCalendar() {
  const grid = document.getElementById('calendar-grid');
  const monthLabel = document.getElementById('cal-month');
  const hint = document.getElementById('cal-hint');

  const expertId = document.getElementById('expertSelect').value;
  if (!expertId) {
    monthLabel.textContent = 'Select an Expert';
    hint.textContent = 'Choose an expert above to view their available slots.';
    grid.innerHTML = '<div class="day-label">Sun</div><div class="day-label">Mon</div><div class="day-label">Tue</div><div class="day-label">Wed</div><div class="day-label">Thu</div><div class="day-label">Fri</div><div class="day-label">Sat</div>';
    return;
  }

  monthLabel.textContent = MONTHS[currentMonth] + ' ' + currentYear;
  hint.textContent = 'Click an available date to book a slot.';

  const slots = getSlotsForExpert(expertId);

  const firstDay = new Date(currentYear, currentMonth, 1).getDay();
  const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
  const daysInPrevMonth = new Date(currentYear, currentMonth, 0).getDate();

  const today = new Date();
  const todayKey = today.getFullYear() + '-' + String(today.getMonth()+1).padStart(2,'0') + '-' + String(today.getDate()).padStart(2,'0');

  let html = '<div class="day-label">Sun</div><div class="day-label">Mon</div><div class="day-label">Tue</div><div class="day-label">Wed</div><div class="day-label">Thu</div><div class="day-label">Fri</div><div class="day-label">Sat</div>';

  for (let i = firstDay - 1; i >= 0; i--) {
    html += '<div class="calendar-day other-month">' + (daysInPrevMonth - i) + '</div>';
  }

  for (let d = 1; d <= daysInMonth; d++) {
    const dateObj = new Date(currentYear, currentMonth, d);
    const dayName = DAY_NAMES[dateObj.getDay()];
    const dateKey = currentYear + '-' + String(currentMonth+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
    const isToday = dateKey === todayKey;
    const isPast = dateObj < new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const daySlots = getSlotsForDate(expertId, dayName, dateKey);
    const hasAvailableSlot = daySlots.length > 0;
    const hasBookedSlot = slots.some(s => matchesDate(s, dayName, dateKey) && (!s.is_available || isOccurrenceFull(s.schedule_id, dateKey)));
    const isMyBooking = myBookedDates.includes(dateKey);

    // Matches the legend exactly: not-available (X pattern) for fully
    // booked/blocked days, available for open ones, past for prior dates.
    // A date the student booked themselves takes priority over
    // not-available — that slot IS unavailable to other students, but to
    // the person who booked it, showing an X/blocked pattern read as if
    // something had gone wrong rather than "you're booked here."
    let cls = 'calendar-day';
    if (isPast) cls += ' past';
    else if (isMyBooking) cls += ' my-booking';
    else if (hasBookedSlot && !hasAvailableSlot) cls += ' not-available';
    else if (hasAvailableSlot) cls += ' available';
    if (isToday) cls += ' today';

    let cellContent = '<div class="cal-date-num">' + d + '</div>';

    if (hasAvailableSlot && !isPast) {
      const slotsHtml = daySlots.slice(0, 2).map(s =>
        '<div class="cal-slot-pill">' + formatTime(s.start_time) + ' – ' + formatTime(s.end_time) + '</div>'
      ).join('');
      const moreHtml = daySlots.length > 2 ? '<div class="cal-more">+' + (daySlots.length - 2) + '</div>' : '';
      cellContent += '<div class="cal-slots">' + slotsHtml + moreHtml + '</div>';
    }

    if (isMyBooking) {
      cellContent += '<div class="event-dot" title="You have a booking on this date"></div>';
    }

    if (hasAvailableSlot && !isPast) {
      html += '<div class="' + cls + '" onclick="selectDate(' + d + ', \'' + dayName + '\', \'' + dateKey + '\')">' + cellContent + '</div>';
    } else if (hasBookedSlot && !isPast) {
      html += '<div class="' + cls + '" title="Fully booked">' + cellContent + '</div>';
    } else {
      html += '<div class="' + cls + '">' + cellContent + '</div>';
    }
  }

  const totalCells = firstDay + daysInMonth;
  const remainder = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
  for (let d = 1; d <= remainder; d++) {
    html += '<div class="calendar-day other-month">' + d + '</div>';
  }

  grid.innerHTML = html;
}

function changeMonth(delta) {
  currentMonth += delta;
  if (currentMonth < 0) { currentMonth = 11; currentYear--; }
  if (currentMonth > 11) { currentMonth = 0; currentYear++; }
  renderCalendar();
}

function onExpertChange() {
  currentMonth = new Date().getMonth();
  currentYear = new Date().getFullYear();
  renderCalendar();
}

let currentSlotModalDateKey = null;

function selectDate(d, dayName, dateKey) {
  const expertId = document.getElementById('expertSelect').value;
  if (!expertId) {
    showToast('Please select an expert first.', 'error');
    return;
  }

  const daySlots = getSlotsForDate(expertId, dayName, dateKey);
  if (daySlots.length === 0) {
    return;
  }

  currentSlotModalDateKey = dateKey;

  const dateLabel = DAY_NAMES[new Date(currentYear, currentMonth, d).getDay()] + ', ' + MONTHS[currentMonth] + ' ' + d;
  document.getElementById('slot-modal-date').textContent = dateLabel;

  const slotList = document.getElementById('slot-modal-list');
  slotList.innerHTML = '';

  daySlots.forEach(s => {
    const item = document.createElement('div');
    item.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px;background:var(--bg3);border-radius:8px;margin-bottom:8px;border-left:3px solid var(--accent);cursor:pointer;transition:all 0.15s';
    item.innerHTML = `
      <div>
        <div style="font-weight:600;font-size:13px;color:var(--text)">${formatTime(s.start_time)} – ${formatTime(s.end_time)}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:2px">${ucfirst(s.slot_type || 'consultation')} · Max ${s.consultation_limit || 1} team(s)</div>
      </div>
      <button class="btn-primary" style="padding:6px 14px;font-size:11px;font-weight:600">Book</button>
    `;
    item.onmouseenter = () => { item.style.background = 'var(--bg4)'; item.style.borderLeftColor = 'var(--accent2)'; };
    item.onmouseleave = () => { item.style.background = 'var(--bg3)'; item.style.borderLeftColor = 'var(--accent)'; };
    item.onclick = () => bookSlot(s.schedule_id);
    slotList.appendChild(item);
  });

  document.getElementById('slotSelectModal').style.display = 'flex';
}

function closeSlotModal() {
  document.getElementById('slotSelectModal').style.display = 'none';
}

function ucfirst(s) {
  return s.charAt(0).toUpperCase() + s.slice(1);
}

function formatDateLabel(dateKey) {
  const [y, m, d] = dateKey.split('-').map(Number);
  return MONTHS[m - 1].slice(0, 3) + ' ' + String(d).padStart(2, '0') + ', ' + y;
}

function showToast(message, type) {
  type = type || 'success';
  const toast = document.getElementById('consult-toast');
  if (!toast) { return; }
  toast.textContent = (type === 'error' ? '✕ ' : '✓ ') + message;
  toast.classList.remove('success', 'error');
  toast.classList.add('show', type);
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => toast.classList.remove('show'), 3500);
}

function prependUpcomingBooking(booking, dateKey) {
  const list = document.getElementById('upcoming-bookings-list');
  const empty = list.querySelector('.session-list-empty');
  if (empty) { empty.remove(); }

  const tc = slotTypeColors[booking.slot_type] || slotTypeColors['consult'];
  const today = new Date();
  const todayKey = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
  const statusLabel = dateKey === todayKey ? 'Today' : 'Upcoming';

  const item = document.createElement('div');
  item.className = 'session-list-item session-list-item-upcoming';
  item.innerHTML = `
    <div class="appt-row-top">
      <div class="appt-expert">${booking.expert_name}</div>
      <div class="appt-badges">
        <span class="appt-type-badge" style="background:${tc.bg};color:${tc.color}">${ucfirst(booking.slot_type || 'Consult')}</span>
        <span class="appt-status-badge appt-status-badge-upcoming">${statusLabel}</span>
      </div>
    </div>
    <div class="appt-meta">
      <span><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>${formatDateLabel(dateKey)}</span>
      <span><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>${formatTime(booking.start_time)}</span>
    </div>
    <div class="appt-team">Team: ${booking.team_name}</div>
  `;
  list.prepend(item);

  const count = document.querySelector('#upcoming-bookings-list').closest('.sessions-card').querySelector('.sessions-card-count');
  if (count) { count.textContent = String(Number(count.textContent || '0') + 1); }
}

async function bookSlot(scheduleId) {
  const dateKey = currentSlotModalDateKey;
  try {
    const res = await fetch('{{ route("student.consultation.book") }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CSRF,
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        schedule_id: scheduleId,
        session_date: dateKey,
      }),
    });

    const data = await res.json();
    if (data.success) {
      closeSlotModal();

      fullOccurrences = new Set(data.fullOccurrences || []);
      if (dateKey && ! myBookedDates.includes(dateKey)) {
        myBookedDates.push(dateKey);
      }
      if (data.newBooking && dateKey) {
        prependUpcomingBooking(data.newBooking, dateKey);
      }
      renderCalendar();

      showToast('Booking request submitted successfully!', 'success');
    } else {
      showToast(data.message || 'Failed to book.', 'error');
    }
  } catch (e) {
    showToast('Error: ' + e.message, 'error');
  }
}

renderCalendar();

// -- Mobile Sidebar --
function toggleSidebar() {
    document.body.classList.toggle('sidebar-open');
    document.body.style.overflow = document.body.classList.contains('sidebar-open') ? 'hidden' : '';
}
function closeSidebar() {
    document.body.classList.remove('sidebar-open');
    document.body.style.overflow = '';
}
document.querySelectorAll('.nav-item').forEach(function(el) {
    el.addEventListener('click', function() {
        if (window.innerWidth <= 768) closeSidebar();
    });
});
</script>
</body>
</html>
