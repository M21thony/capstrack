<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Capstone Expert - CapsTRACK</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/Styles/experts.css')
</head>
<body>
@include('student.partials.sidebar')

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="page-title">Capstone Expert</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
<div class="content">
    
    <div class="experts-main">
      @include('student.partials.page-tutorial', ['page' => 'experts'])
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; position: sticky; margin-top: -20px; background: var(--bg); z-index: 10; padding: 20px 0;">
        <input class="input" placeholder="Search experts..." style="flex: 1; max-width: 350px;" />
        <div class="filter-bar" style="margin-bottom: 0;">
          <div class="filter-chip active">All</div>
          <div class="filter-chip">Advisers</div>
          <div class="filter-chip">Consultants</div>
          <div class="filter-chip">Grammarian</div>
        </div>
      </div>
  <div class="expert-grid">
      <div class="expert-card-compact" onclick="applyForExpert('Dr. Danilo P.')">
        <div class="role-dot" style="background: var(--accent);"></div>
        <div class="expert-avatar-wrap">
          <div class="avatar" style="width:100%; height:100%; font-size:18px;">DP</div>
            </div>
            <div style="font-size: 14px; font-weight: 700; color: var(--text);">Dr. Danilo P.</div>
            <div style="font-size: 11px; color: var(--text3); margin-top: 4px;">AI & Data Science</div>
            
            <div class="mini-slot-bar"><div class="mini-slot-fill" style="width: 66%;"></div></div>
            <div style="font-size: 10px; font-weight: 700; color: var(--text3); letter-spacing: 0.5px;">SLOTS: 2/3</div>
            
            <button class="btn-primary" style="width: 100%; margin-top: 15px;">Apply Now</button>
        </div>

        <div class="expert-card-compact" onclick="applyForExpert('Engr. Rico L.')">
            <div class="role-dot" style="background: #3b82f6;"></div>
            <div class="expert-avatar-wrap">
                <div class="avatar" style="width:100%; height:100%; font-size:18px;">RL</div>
            </div>
            <div style="font-size: 14px; font-weight: 700; color: var(--text);">Engr. Rico L.</div>
            <div style="font-size: 11px; color: var(--text3); margin-top: 4px;">Web Development</div>
            
            <div class="mini-slot-bar"><div class="mini-slot-fill" style="width: 20%; background: #22c55e;"></div></div>
            <div style="font-size: 10px; font-weight: 700; color: var(--text3); letter-spacing: 0.5px;">SLOTS: 1/5</div>
            
            <button class="btn-primary" style="width: 100%; margin-top: 15px;">Apply Now</button>
        </div>

        <div class="expert-card-compact" onclick="applyForExpert('Engr. Rico L.')">
            <div class="role-dot" style="background: #3b82f6;"></div>
            <div class="expert-avatar-wrap">
                <div class="avatar" style="width:100%; height:100%; font-size:18px;">RL</div>
            </div>
            <div style="font-size: 14px; font-weight: 700; color: var(--text);">Engr. Rico L.</div>
            <div style="font-size: 11px; color: var(--text3); margin-top: 4px;">Web Development</div>
            
            <div class="mini-slot-bar"><div class="mini-slot-fill" style="width: 20%; background: #22c55e;"></div></div>
            <div style="font-size: 10px; font-weight: 700; color: var(--text3); letter-spacing: 0.5px;">SLOTS: 1/5</div>
            
            <button class="btn-primary" style="width: 100%; margin-top: 15px;">Apply Now</button>
        </div>

        <div class="expert-card-compact">
            <div class="role-dot" style="background: #9333ea;"></div>
            <div class="expert-avatar-wrap">
                <div class="avatar" style="width:100%; height:100%; font-size:18px;">MC</div>
            </div>
            <div style="font-size: 14px; font-weight: 700; color: var(--text);">Ma'am Maria C.</div>
            <div style="font-size: 11px; color: var(--text3); margin-top: 4px;">Technical Writing</div>
            
            <div class="mini-slot-bar"><div class="mini-slot-fill" style="width: 100%; background: #ef4444;"></div></div>
            <div style="font-size: 10px; font-weight: 700; color: #ef4444;">FULL CAPACITY</div>
            
            <button class="btn-primary" style="width: 100%; margin-top: 15px; opacity: 0.5;" disabled>Full</button>
        </div>

        <div class="expert-card-compact" onclick="applyForExpert('Engr. Rico L.')">
            <div class="role-dot" style="background: #3b82f6;"></div>
            <div class="expert-avatar-wrap">
                <div class="avatar" style="width:100%; height:100%; font-size:18px;">RL</div>
            </div>
            <div style="font-size: 14px; font-weight: 700; color: var(--text);">Engr. Rico L.</div>
            <div style="font-size: 11px; color: var(--text3); margin-top: 4px;">Web Development</div>
            
            <div class="mini-slot-bar"><div class="mini-slot-fill" style="width: 20%; background: #22c55e;"></div></div>
            <div style="font-size: 10px; font-weight: 700; color: var(--text3); letter-spacing: 0.5px;">SLOTS: 1/5</div>
            
            <button class="btn-primary" style="width: 100%; margin-top: 15px;">Apply Now</button>
        </div>

        <div class="expert-card-compact">
            <div class="role-dot" style="background: #9333ea;"></div>
            <div class="expert-avatar-wrap">
                <div class="avatar" style="width:100%; height:100%; font-size:18px;">MC</div>
            </div>
            <div style="font-size: 14px; font-weight: 700; color: var(--text);">Ma'am Maria C.</div>
            <div style="font-size: 11px; color: var(--text3); margin-top: 4px;">Technical Writing</div>
            
            <div class="mini-slot-bar"><div class="mini-slot-fill" style="width: 100%; background: #ef4444;"></div></div>
            <div style="font-size: 10px; font-weight: 700; color: #ef4444;">FULL CAPACITY</div>
            
            <button class="btn-primary" style="width: 100%; margin-top: 15px; opacity: 0.5;" disabled>Full</button>
        </div>
    </div>

<div class="pagination">
        <div class="pg-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></div>
        <div class="pg-item active">1</div>
        <div class="pg-item">2</div>
        <div class="pg-item">3</div>
        <div class="pg-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>
    </div>
  <div class="tutorial-note" style="justify-content: flex-start; border-top:none; margin-top:15px;">
      <div class="tutorial-item"><div class="dot-guide" style="background:var(--accent)"></div><span>Adviser</span></div>
      <div class="tutorial-item"><div class="dot-guide" style="background:#3b82f6"></div><span>Consultant</span></div>
      <div class="tutorial-item"><div class="dot-guide" style="background:#9333ea"></div><span>Grammarian</span></div>
  </div>
</div>

<div class="overview-panel">
      <div class="card" style="padding: 16px; border-radius: 12px; background: var(--bg2); margin-top: -20px;">
        <div class="section-title" style="font-size: 13px; margin-bottom: 12px;">Overview</div>
        
        <div style="margin-bottom: 16px;">
          <label class="overview-label">Full Capacity</label>
          <div class="expert-stat-card">
            <div class="stat-name">
              <div class="avatar" style="width: 20px; height: 20px; font-size: 9px;">MC</div>
              Maria C.
            </div>
            <span class="status-pill status-full" style="font-size: 7px; padding: 1px 4px;">FULL</span>
          </div>
        </div>

        <div>
          <label class="overview-label">New Experts</label>
          <div class="expert-stat-card">
            <div class="stat-name">
              <div class="avatar" style="width: 20px; height: 20px; font-size: 9px;">JS</div>
              Joey S.
            </div>
            <span class="status-pill status-none" style="font-size: 7px; padding: 1px 4px;">NEW</span>
          </div>
          <div class="expert-stat-card">
            <div class="stat-name">
              <div class="avatar" style="width: 20px; height: 20px; font-size: 9px;">RB</div>
              Rod B.
            </div>
            <span class="status-pill status-none" style="font-size: 7px; padding: 1px 4px;">NEW</span>
          </div>
        </div>

        <div class="divider" style="margin: 16px 0;"></div>
        <p style="font-size: 10px; color: var(--text3); line-height: 1.4; opacity: 0.8;">
          Request approval depends on the expert's current workload.
        </p>
      </div>
      <div class="card" style="padding: 16px; border-radius: 12px; background: var(--bg2); margin-top: 15px;">
  <div class="section-title" style="font-size: 13px; margin-bottom: 12px;">My Team Experts</div>
  
  <div style="display: flex; flex-direction: column; gap: 10px;">
    <div class="expert-stat-card" style="border-left: 3px solid var(--accent); padding-left: 10px;">
      <div class="stat-name">
        <div class="avatar" style="width: 24px; height: 24px; font-size: 10px; background: var(--accent); color: white;">DP</div>
        <div style="display: flex; flex-direction: column;">
          <span style="font-size: 11px; font-weight: 700;">Dr. Danilo P.</span>
          <span style="font-size: 9px; color: var(--text3); text-transform: uppercase;">Adviser</span>
        </div>
      </div>
      <span class="status-pill" style="background: var(--green-bg); color: var(--green); font-size: 7.5px; font-weight: 600;">OCT 12 2025</span>
    </div>

    <div class="expert-stat-card" style="border-left: 3px solid #3b82f6; padding-left: 10px;">
      <div class="stat-name">
        <div class="avatar" style="width: 24px; height: 24px; font-size: 10px; background: #3b82f6; color: white;">RL</div>
        <div style="display: flex; flex-direction: column;">
          <span style="font-size: 11px; font-weight: 700;">Engr. Rico L.</span>
          <span style="font-size: 9px; color: var(--text3); text-transform: uppercase;">Consultant</span>
        </div>
      </div>
      <span class="status-pill" style="background: var(--green-bg); color: var(--green); font-size: 7.5px; font-weight: 600;">OCT 14 2025</span>
    </div>

    <div class="expert-stat-card" style="border-left: 3px solid #9333ea; padding-left: 10px;">
      <div class="stat-name">
        <div style="width: 24px; height: 24px; border-radius: 50%; border: 1px dashed var(--border); display: flex; align-items: center; justify-content: center; background: var(--bg3);">
          <svg viewBox="0 0 24 24" width="12" height="12" stroke="var(--text3)" fill="none" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        </div>
        <div style="display: flex; flex-direction: column;">
          <span style="font-size: 11px; font-weight: 500; color: var(--text3);">Select Grammarian</span>
          <span style="font-size: 9px; color: var(--text3); text-transform: uppercase;">Grammarian</span>
        </div>
      </div>
      <span style="font-size: 8px; color: var(--text3); font-weight: 600;">NONE</span>
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


<script>

// Mock User Data
const studentSession = {
    isLeader: true, // Only leaders can apply
    teamName: "Team Alpha"
};

function applyForExpert(name) {
    if (!studentSession.isLeader) {
        alert("Action Restricted: Only the Team Leader can apply for advisers/consultants.");
        return;
    }

    const confirmApply = confirm(`Are you sure you want to request ${name} for your team (${studentSession.teamName})?`);
    
    if (confirmApply) {
        alert("Application successfully submitted! You can track the status in your 'Team Requests' tab.");
        // Logic to update UI or send to DB goes here
    }
}
</script>
</body>
</html>
