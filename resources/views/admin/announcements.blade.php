<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Announcements - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
</head>
<body>

@include('admin._sidebar')
<div class="main">
  <div class="topbar">
    <div class="page-title">Announcements</div>
    <div class="topbar-actions">
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </div>

  <div class="content">
    <div style="display:grid;grid-template-columns:1fr 1.6fr;gap:16px">
      <div style="display:flex;flex-direction:column;gap:14px">
        <div class="card" id="announcement-form-card">
          <div class="card-header">
            <span class="card-title" id="ann-form-title">New Announcement</span>
            <button onclick="resetAnnForm()" style="background:none;border:none;font-size:11px;color:var(--text3);cursor:pointer">Clear</button>
          </div>
          <div class="form-group">
            <label class="label">Title</label>
            <input class="input" id="ann-title" placeholder="e.g. Schedule for Title Defense — AY 2025-2026"/>
          </div>
          <div class="form-group">
            <label class="label">Target Audience</label>
            <select class="input" id="ann-audience">
              <option value="all">All Users (Students &amp; Experts)</option>
              <option value="students">Students Only</option>
              <option value="experts">Capstone Experts Only</option>
            </select>
          </div>
          <div class="form-group">
            <label class="label">Priority</label>
            <select class="input" id="ann-priority">
              <option value="normal">Normal</option>
              <option value="important">Important</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
          <div class="form-group">
            <label class="label">Message</label>
            <textarea class="input" id="ann-message" style="min-height:110px" placeholder="Type your announcement here..."></textarea>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="label">Expiry Date (optional)</label>
            <input class="input" id="ann-expiry" type="date"/>
          </div>
          <div style="display:flex;gap:8px;margin-top:16px">
            <button class="btn btn-primary" style="flex:1;justify-content:center;font-size:13px" onclick="saveAnnouncement()">
              <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              <span id="ann-save-label">Post Announcement</span>
            </button>
            <button class="btn btn-ghost" style="font-size:13px" id="ann-cancel-btn" onclick="resetAnnForm()" style="display:none">Cancel</button>
          </div>
        </div>
      </div>

      <div class="gap-12">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px">
          <span style="font-size:12px;color:var(--text3)">3 active announcements</span>
          <select class="input" style="width:150px;font-size:12px;padding:6px 10px"><option>All Audiences</option><option>All Users</option><option>Students Only</option><option>Experts Only</option></select>
        </div>

        <div class="card announcement-card urgent">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:8px">
            <div style="flex:1">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                <span class="badge badge-red" style="font-size:10px">Urgent</span>
                <span class="badge badge-gray" style="font-size:10px">All Users</span>
              </div>
              <div style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:4px">Deadline Reminder: Financial Receipts Due May 18</div>
              <div style="font-size:12px;color:var(--text2);line-height:1.5">All teams must submit their capstone-related financial receipts to their respective advisers on or before May 18, 2025. Failure to comply may affect your capstone progress status.</div>
            </div>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;padding-top:10px;border-top:1px solid var(--border)">
            <div style="font-size:11px;color:var(--text3)">Posted Apr 5, 2026 · Expires May 18, 2026</div>
            <div style="display:flex;gap:6px">
              <button class="btn btn-ghost" style="font-size:10px;padding:4px 10px">Edit</button>
              <button class="btn btn-danger" style="font-size:10px;padding:4px 10px">Delete</button>
            </div>
          </div>
        </div>

        <div class="card announcement-card important">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:8px">
            <div style="flex:1">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                <span class="badge badge-amber" style="font-size:10px">Important</span>
                <span class="badge badge-gray" style="font-size:10px">Students Only</span>
              </div>
              <div style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:4px">Title Defense Schedule — AY 2025–2026</div>
              <div style="font-size:12px;color:var(--text2);line-height:1.5">The Title Defense for all BSIT and BSCS teams is scheduled from May 20–22, 2026. Teams must submit their complete Chapter 1–3 manuscript at least 5 days before their assigned date.</div>
            </div>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;padding-top:10px;border-top:1px solid var(--border)">
            <div style="font-size:11px;color:var(--text3)">Posted Apr 3, 2026 · Expires May 22, 2026</div>
            <div style="display:flex;gap:6px">
              <button class="btn btn-ghost" style="font-size:10px;padding:4px 10px">Edit</button>
              <button class="btn btn-danger" style="font-size:10px;padding:4px 10px">Delete</button>
            </div>
          </div>
        </div>

        <div class="card announcement-card normal">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:8px">
            <div style="flex:1">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                <span class="badge badge-gray" style="font-size:10px">Normal</span>
                <span class="badge badge-cyan" style="font-size:10px;background:rgba(8,145,178,0.08);color:var(--cyan)">Experts Only</span>
              </div>
              <div style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:4px">Updated Document Review Guidelines for AY 2025–2026</div>
              <div style="font-size:12px;color:var(--text2);line-height:1.5">Please refer to the updated annotation guidelines when reviewing Chapter submissions. All major revisions must include a specific annotation with page reference. Grammarians are reminded to focus on formatting consistency.</div>
            </div>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;padding-top:10px;border-top:1px solid var(--border)">
            <div style="font-size:11px;color:var(--text3)">Posted Mar 28, 2026 · No expiry</div>
            <div style="display:flex;gap:6px">
              <button class="btn btn-ghost" style="font-size:10px;padding:4px 10px">Edit</button>
              <button class="btn btn-danger" style="font-size:10px;padding:4px 10px">Delete</button>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
function resetAnnForm(){
  document.getElementById('ann-title').value='';
  document.getElementById('ann-audience').value='all';
  document.getElementById('ann-priority').value='normal';
  document.getElementById('ann-message').value='';
  document.getElementById('ann-expiry').value='';
  document.getElementById('ann-form-title').textContent='New Announcement';
  document.getElementById('ann-save-label').textContent='Post Announcement';
  document.getElementById('ann-cancel-btn').style.display='none';
}
function saveAnnouncement(){
  const t=document.getElementById('ann-title').value.trim();
  if(!t){document.getElementById('ann-title').focus();return;}
  resetAnnForm();
  alert('Announcement posted!');
}
</script>
</body>
</html>