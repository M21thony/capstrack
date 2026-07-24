<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Consultation - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/Styles/consultation.css')
</head>
<body>
@include('student.partials.sidebar')

<!-- MAIN -->
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
          <select id="expertSelect">
            <option value="" disabled selected>Choose an Adviser/Expert...</option>
            <option>Dr. Alexa Nicole (Capstone Adviser)</option>
            <option>Prof. Ian Cyrus (Technical Expert)</option>
            <option>Ms. Jolina (UI/UX Consultant)</option>
          </select>
        </div>
        <div style="font-size:11px; color:var(--text3); max-width:150px; line-height:1.3">
          Availability syncs with their schedule.
        </div>
      </div>

      <div class="calendar-card">
        <div class="calendar-header">
          <div>
            <h2 style="font-size:18px; font-weight:600">October 2026</h2>
            <p style="font-size:11px; color:var(--text3)">Select an available date for your session.</p>
          </div>
          <div class="cal-nav-group">
            <div class="cal-nav">
              <button class="cal-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg></button>
              <button class="cal-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg></button>
            </div>
            <button class="btn-primary" style="padding:10px 20px" onclick="openModal()">+ New Request</button>
          </div>
        </div>

        <div class="calendar-grid">
          <div class="day-label">Sun</div><div class="day-label">Mon</div><div class="day-label">Tue</div>
          <div class="day-label">Wed</div><div class="day-label">Thu</div><div class="day-label">Fri</div><div class="day-label">Sat</div>
          
          <div class="calendar-day not-available">27</div><div class="calendar-day not-available">28</div>
          <div class="calendar-day not-available">29</div>
          <div class="calendar-day not-available">30</div>
          <div class="calendar-day past">1</div> 
          <div class="calendar-day past completed">2</div>
          <div class="calendar-day not-available">3</div>
          <div class="calendar-day not-available">4</div>
          <div class="calendar-day past">5</div> 
          <div class="calendar-day past">6</div>
          <div class="calendar-day today" onclick="openModal()">7</div>
          <div class="calendar-day" onclick="openModal()">8</div>
          <div class="calendar-day not-available">9</div>
          <div class="calendar-day not-available">10</div>
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
                <span><strong>Unavailable:</strong> Expert is blocked or fully booked (X).</span>
              </div>
              <div class="note-item">
                <div style="width: 14px; height: 14px; background: #526D82; border-radius: 3px; opacity: 0.5;"></div>
                <span><strong>Past Dates:</strong> Previous dates (Dark Gray) are disabled.</span>
              </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 8px;">
              <div class="note-item">
                <span style="color: var(--accent2); font-weight: 700; font-size: 14px;">✔</span>
                <span><strong>Completed:</strong> Successfully finished sessions.</span>
              </div>
              <div class="note-item">
                <div style="width: 6px; height: 6px; background: var(--accent); border-radius: 50%;"></div>
                <span><strong>Active/Pending:</strong> Your current appointments.</span>
              </div>
            </div>
          </div>

          <div style="margin-top: 12px; padding-top: 10px; border-top: 1px dashed var(--border); font-size: 11px; color: var(--text3); font-style: italic;">
            CapsTip: Click outside the booking modal to quickly go back to the calendar.
          </div>
        </div>
      </div>
    </div>

    <div class="appt-sidebar">
      <h3 class="overview-label">Recent Sessions</h3>
      <div class="appt-card" style="border-left:3px solid var(--accent)">
        <div class="appt-time">OCT 2 2026 • 10:30 AM</div>
        <div class="appt-title">UI Design Consultation</div>
        <div class="appt-expert">Ms. Jolina</div>
      </div>
      
      <div class="legal-credits">
        <div class="credits-title">System Developers</div>
        <div class="credits-names">Jolina · John Kenneth · Mark Anthony · Ian Cyrus · Alexa Nicole</div>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="bookingModal" onclick="if(event.target === this) closeModal()">
  <div class="modal-content">
    <div style="margin-bottom: 20px;">
      <h2 style="font-size: 18px; font-weight: 600;">Request Consultation</h2>
      <p style="font-size: 11px; color: var(--text3);">Provide details for your session request.</p>
    </div>
    
    <div class="pm-field">
      <label class="pm-label">Agenda / Topic</label>
      <input type="text" class="input" placeholder="e.g. Chapter 1 Methodology Review">
    </div>

    <div class="pm-field" style="margin-top: 15px;">
      <label class="pm-label">Preferred Time</label>
      <input type="time" class="input">
    </div>

    <div class="pm-field" style="margin-top: 15px;">
      <label class="pm-label">Message / Additional Comments</label>
      <textarea class="input" placeholder="Briefly describe what you need help with..."></textarea>
    </div>

    <div class="modal-footer">
      <button class="btn-primary" style="width: 100%; padding: 12px; font-weight: 600;" onclick="submitRequest()">
        Submit Consultation Request
      </button>
    </div>
  </div>
</div>

<script>

  function openModal() { document.getElementById('bookingModal').style.display = 'flex'; }
  function closeModal() { document.getElementById('bookingModal').style.display = 'none'; }
</script>
</body>
</html>
