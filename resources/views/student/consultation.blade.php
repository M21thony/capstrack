<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Consultation - CapsTRACK</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/Styles/consultation.css')
</head>
<body>
@include('student.partials.sidebar')

<div class="main">
  <div class="topbar">
    <div class="page-title">Consultation</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'consultation'])
  <div class="consultation-container">

    <div class="left-panel">

      <div class="expert-filter-area">
        <label class="pm-label" style="margin-bottom:0">Target Expert:</label>
        <div class="expert-dropdown">
          <select id="expertSelect" onchange="onExpertChange()">
            <option value="" disabled selected>Choose an Adviser/Expert...</option>
            @foreach($experts as $expert)
              <option value="{{ $expert->expert_id }}">{{ $expert->first_name }} {{ $expert->last_name }}</option>
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

        <div class="cal-notes">
          <h4 style="font-size: 12px; color: var(--text); text-transform: uppercase; margin-bottom: 10px; letter-spacing: 0.5px;">Consultation Legend & Guide</h4>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <div style="display: flex; flex-direction: column; gap: 8px;">
              <div class="note-item">
                <div style="width: 14px; height: 14px; background: var(--bg2); border: 1px solid var(--border); position: relative; overflow: hidden; border-radius: 3px;">
                  <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to top right, transparent 45%, var(--text3) 50%, transparent 55%); opacity: 0.5;"></div>
                  <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to top left, transparent 45%, var(--text3) 50%, transparent 55%); opacity: 0.5;"></div>
                </div>
                <span><strong>Unavailable:</strong> Expert is blocked or fully booked.</span>
              </div>
              <div class="note-item">
                <div style="width: 14px; height: 14px; background: #526D82; border-radius: 3px; opacity: 0.5;"></div>
                <span><strong>Past Dates:</strong> Previous dates (Dark Gray) are disabled.</span>
              </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 8px;">
              <div class="note-item">
                <span style="color: var(--accent2); font-weight: 700; font-size: 14px;">✔</span>
                <span><strong>Available:</strong> Click a date to book a slot.</span>
              </div>
              <div class="note-item">
                <div style="width: 6px; height: 6px; background: var(--accent); border-radius: 50%;"></div>
                <span><strong>Your Booking:</strong> Pending appointments.</span>
              </div>
            </div>
          </div>

          <div style="margin-top: 12px; padding-top: 10px; border-top: 1px dashed var(--border); font-size: 11px; color: var(--text3); font-style: italic;">
            CapsTip: Select an expert, then click an available date to book a specific time slot.
          </div>
        </div>
      </div>
    </div>

    <div class="appt-sidebar">
      <h3 class="overview-label">Recent Sessions</h3>
      <div id="bookings-list">
        @forelse($myBookings as $booking)
          <div class="appt-card" style="border-left:3px solid var(--accent)">
            <div class="appt-time">{{ \Carbon\Carbon::parse($booking->schedule->available_date)->format('M d Y') }} • {{ \Carbon\Carbon::parse($booking->schedule->start_time)->format('h:i A') }}</div>
            <div class="appt-title">{{ ucfirst($booking->schedule->slot_type ?? 'consultation') }}</div>
            <div class="appt-expert">{{ $booking->schedule->expert->first_name ?? 'Expert' }} {{ $booking->schedule->expert->last_name ?? '' }}</div>
            <div class="appt-expert">Team: {{ $booking->team->team_name ?? 'N/A' }}</div>
            <button class="btn-ghost" style="margin-top:8px;font-size:10px;padding:4px 8px" onclick="cancelBooking({{ $booking->booking_id }})">Cancel</button>
          </div>
        @empty
          <div style="font-size:12px;color:var(--text3);padding:12px;text-align:center">No upcoming consultations.</div>
        @endforelse
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

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const DAY_NAMES = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

const expertSlots = @json($availableSlots);

function getSlotsForExpert(expertId) {
  return expertSlots.filter(s => s.expert_id == expertId);
}

function getSlotsForDate(expertId, dayName) {
  return getSlotsForExpert(expertId).filter(s => s.available_date === dayName && s.is_available);
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
    const daySlots = getSlotsForDate(expertId, dayName);
    const hasAvailableSlot = daySlots.length > 0;
    const hasBookedSlot = slots.some(s => s.available_date === dayName && !s.is_available);

    let cls = 'calendar-day';
    if (isPast) cls += ' past';
    else if (hasBookedSlot && !hasAvailableSlot) cls += ' booked';
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

    if (hasAvailableSlot && !isPast) {
      html += '<div class="' + cls + '" onclick="selectDate(' + d + ', \'' + dayName + '\')">' + cellContent + '</div>';
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

function selectDate(d, dayName) {
  const expertId = document.getElementById('expertSelect').value;
  if (!expertId) {
    alert('Please select an expert first.');
    return;
  }

  const daySlots = getSlotsForDate(expertId, dayName);
  if (daySlots.length === 0) {
    return;
  }

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

async function bookSlot(scheduleId) {
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
      }),
    });

    const data = await res.json();
    if (data.success) {
      closeSlotModal();
      alert('Booking request submitted successfully!');
      location.reload();
    } else {
      alert(data.message || 'Failed to book.');
    }
  } catch (e) {
    alert('Error: ' + e.message);
  }
}

async function cancelBooking(bookingId) {
  if (!confirm('Cancel this booking?')) return;

  try {
    const res = await fetch('{{ route("student.consultation.cancel", ["bookingId" => ":id"]) }}'.replace(':id', bookingId), {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': CSRF,
        'Accept': 'application/json',
      },
    });

    const data = await res.json();
    if (data.success) {
      location.reload();
    } else {
      alert('Failed to cancel booking.');
    }
  } catch (e) {
    alert('Error: ' + e.message);
  }
}

renderCalendar();
</script>
</body>
</html>
