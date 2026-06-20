<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Dashboard - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
</head>
<body>

@include('admin._sidebar')
<div class="main">
  <div class="topbar">
    <div class="page-title">Panel Assignment</div>
    <div class="topbar-actions">
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </div>

  <div class="content">
<!-- ═══ PANEL ASSIGNMENT ═══ -->
    <div id="page-panel" class="page active">
      <div class="row" style="margin-bottom:16px;gap:8px">
        <input class="input" placeholder="Search team or capstone title..." style="max-width:300px"/>
        <select class="input" style="width:160px"><option>All Sections</option><option>BSCS-4A</option><option>BSIT-4A</option><option>BSIT-4B</option></select>
        <select class="input" style="width:180px"><option>All Status</option><option>Assigned</option><option>Not Assigned</option></select>
        <span style="flex:1"></span>
        <span style="font-size:12px;color:var(--text3)">48 teams · 32 assigned · 16 unassigned</span>
      </div>
 
      <!-- Panel assignment modal overlay -->
      <div id="panel-modal" style="display:none;position:fixed;inset:0;background:rgba(24,25,31,0.6);z-index:100;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:14px;padding:24px;width:520px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3)">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
            <div>
              <div style="font-size:15px;font-weight:600;color:var(--text)" id="modal-team-name">Team Nexus</div>
              <div style="font-size:12px;color:var(--text3);margin-top:2px" id="modal-team-title">AI-Powered Attendance System Using Facial Recognition</div>
            </div>
            <button onclick="closePanelModal()" style="background:none;border:none;font-size:22px;color:var(--text3);cursor:pointer;line-height:1">×</button>
          </div>
          <div style="background:rgba(201,168,44,0.06);border:1px solid rgba(201,168,44,0.2);border-radius:8px;padding:12px;margin-bottom:16px;font-size:12px;color:var(--text2)">
            <strong style="color:var(--accent3)">Note:</strong> Panel members are different from the team's adviser/consultant/grammarian. Assign experts specifically for defense evaluation.
          </div>
          <div class="form-group">
            <label class="label">Panel Chair</label>
            <select class="input" id="modal-chair">
              <option value="">— Select Panel Chair —</option>
              <option>Dr. Cynthia Santos</option>
              <option>Dr. Ladis Washerum</option>
              <option>Dr. Neil Inocencio</option>
              <option>Prof. Ricardo Medina</option>
              <option>Prof. Allisons Burger</option>
            </select>
          </div>
          <div class="form-group">
            <label class="label">Panel Member 1</label>
            <select class="input" id="modal-member1">
              <option value="">— Select Panel Member 1 —</option>
              <option>Dr. Cynthia Santos</option>
              <option>Dr. Ladis Washerum</option>
              <option>Dr. Neil Inocencio</option>
              <option>Prof. Ricardo Medina</option>
              <option>Prof. Allisons Burger</option>
            </select>
          </div>
          <div class="form-group">
            <label class="label">Panel Member 2</label>
            <select class="input" id="modal-member2">
              <option value="">— Select Panel Member 2 —</option>
              <option>Dr. Cynthia Santos</option>
              <option>Dr. Ladis Washerum</option>
              <option>Dr. Neil Inocencio</option>
              <option>Prof. Ricardo Medina</option>
              <option>Prof. Allisons Burger</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="label">Defense Type</label>
            <select class="input" id="modal-defense">
              <option>Title Defense</option>
              <option>Pre-oral Defense</option>
              <option>Final Oral Defense</option>
            </select>
          </div>
          <div style="display:flex;gap:8px;margin-top:16px">
            <button class="btn btn-primary" style="flex:1;justify-content:center;font-size:13px" onclick="closePanelModal()">
              <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              Save Panel Assignment
            </button>
            <button class="btn btn-ghost" style="font-size:13px;color:var(--text2);border-color:var(--border)" onclick="closePanelModal()">Cancel</button>
          </div>
        </div>
      </div>
 
      <div class="card" style="padding:0;overflow:hidden">
        <table class="table">
          <thead>
            <tr>
              <th style="padding-left:20px">Team</th>
              <th>Capstone Title</th>
              <th>Section</th>
              <th>Panel Chair</th>
              <th>Member 1</th>
              <th>Member 2</th>
              <th>Defense</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td style="padding-left:20px">
                <div style="display:flex;align-items:center;gap:8px">
                  <div style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,#c9a82c,#8a6e10);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">TN</div>
                  <span class="td-main">Team Nexus</span>
                </div>
              </td>
              <td style="font-size:12px;color:var(--text2);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">AI-Powered Attendance System</td>
              <td><span class="badge badge-gold" style="font-size:10px">BSCS-4A</span></td>
              <td style="font-size:12px;color:var(--text)">Dr. Santos</td>
              <td style="font-size:12px;color:var(--text)">Dr. Washerum</td>
              <td style="font-size:12px;color:var(--text)">Prof. Medina</td>
              <td><span class="badge badge-amber" style="font-size:10px">Title Defense</span></td>
              <td>
                <button class="btn btn-ghost" style="font-size:10px;padding:4px 10px" onclick="openPanelModal('Team Nexus','AI-Powered Attendance System Using Facial Recognition')">
                  <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Edit
                </button>
              </td>
            </tr>
            <tr>
              <td style="padding-left:20px">
                <div style="display:flex;align-items:center;gap:8px">
                  <div style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,#16a34a,#0891b2);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">TA</div>
                  <span class="td-main">Team Alpha</span>
                </div>
              </td>
              <td style="font-size:12px;color:var(--text2);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">Plant Disease Detection Mobile App</td>
              <td><span class="badge badge-cyan" style="font-size:10px">BSIT-4A</span></td>
              <td style="font-size:12px;color:var(--text)">Dr. Inocencio</td>
              <td style="font-size:12px;color:var(--text)">Dr. Santos</td>
              <td style="font-size:12px;color:var(--text)">Prof. Burger</td>
              <td><span class="badge badge-green" style="font-size:10px">Final Defense</span></td>
              <td>
                <button class="btn btn-ghost" style="font-size:10px;padding:4px 10px" onclick="openPanelModal('Team Alpha','Plant Disease Detection Using Deep Learning Mobile App')">
                  <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Edit
                </button>
              </td>
            </tr>
            <tr>
              <td style="padding-left:20px">
                <div style="display:flex;align-items:center;gap:8px">
                  <div style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,#1d4ed8,#7c3aed);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">TP</div>
                  <span class="td-main">Team Prism</span>
                </div>
              </td>
              <td style="font-size:12px;color:var(--text2);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">Blockchain-Based Document Verification</td>
              <td><span class="badge badge-purple" style="font-size:10px">BSIT-4B</span></td>
              <td style="font-size:12px;color:var(--text)">Dr. Washerum</td>
              <td style="font-size:12px;color:var(--text)">Prof. Medina</td>
              <td style="font-size:12px;color:var(--text)">Dr. Inocencio</td>
              <td><span class="badge badge-amber" style="font-size:10px">Pre-oral</span></td>
              <td>
                <button class="btn btn-ghost" style="font-size:10px;padding:4px 10px" onclick="openPanelModal('Team Prism','Blockchain-Based Document Verification for Academic Records')">
                  <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Edit
                </button>
              </td>
            </tr>
            <tr>
              <td style="padding-left:20px">
                <div style="display:flex;align-items:center;gap:8px">
                  <div style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,#b45309,#dc2626);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">TS</div>
                  <span class="td-main">Team Syntax</span>
                </div>
              </td>
              <td style="font-size:12px;color:var(--text2);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">Smart Campus Energy Monitoring IoT</td>
              <td><span class="badge badge-amber" style="font-size:10px">BSIT-4A</span></td>
              <td style="font-size:12px;color:var(--text3);font-style:italic">Not assigned</td>
              <td style="font-size:12px;color:var(--text3);font-style:italic">Not assigned</td>
              <td style="font-size:12px;color:var(--text3);font-style:italic">Not assigned</td>
              <td><span class="badge badge-red" style="font-size:10px">Unassigned</span></td>
              <td>
                <button class="btn btn-primary" style="font-size:10px;padding:4px 10px" onclick="openPanelModal('Team Syntax','Smart Campus Energy Monitoring Using IoT Sensors')">
                  <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                  Assign
                </button>
              </td>
            </tr>
            <tr>
              <td style="padding-left:20px">
                <div style="display:flex;align-items:center;gap:8px">
                  <div style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,#0891b2,#16a34a);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">TB</div>
                  <span class="td-main">Team Bulbasaur</span>
                </div>
              </td>
              <td style="font-size:12px;color:var(--text2);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">CapsTrack Capstone Management System</td>
              <td><span class="badge badge-gold" style="font-size:10px">BSIT-4A</span></td>
              <td style="font-size:12px;color:var(--text3);font-style:italic">Not assigned</td>
              <td style="font-size:12px;color:var(--text3);font-style:italic">Not assigned</td>
              <td style="font-size:12px;color:var(--text3);font-style:italic">Not assigned</td>
              <td><span class="badge badge-red" style="font-size:10px">Unassigned</span></td>
              <td>
                <button class="btn btn-primary" style="font-size:10px;padding:4px 10px" onclick="openPanelModal('Team Bulbasaur','CapsTrack: A Centralized Web-Based Capstone Project Management System')">
                  <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                  Assign
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
          <span style="font-size:12px;color:var(--text3)">Showing 5 of 48 teams</span>
          <div style="display:flex;gap:6px">
            <button class="btn btn-ghost" style="font-size:11px;padding:5px 12px">← Prev</button>
            <button class="btn btn-ghost" style="font-size:11px;padding:5px 12px">Next →</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function openPanelModal(teamName, teamTitle) {
  document.getElementById('modal-team-name').textContent = teamName;
  document.getElementById('modal-team-title').textContent = teamTitle;
  document.getElementById('panel-modal').style.display = 'flex';
}
function closePanelModal() {
  document.getElementById('panel-modal').style.display = 'none';
}
</script>
</body>
</html>
