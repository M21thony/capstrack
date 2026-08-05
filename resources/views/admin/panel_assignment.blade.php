<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Panel Assignment - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
</head>
<body>

@include('admin._sidebar')
<div class="main">
  <div class="topbar">
    <div class="page-title">Panel Assignment</div>
    <div class="topbar-actions">
      @include('admin.partials.notification-dropdown')
    </div>
  </div>

  <div class="content">
    <div id="page-panel" class="page active">
      <div class="row" style="margin-bottom:16px;gap:8px">
        <input class="input" placeholder="Search team or capstone title..." style="max-width:300px"/>
        <select class="input" id="filter-section" style="width:160px">
          <option value="">All Sections</option>
          @foreach ($allSections as $section)
            <option value="{{ $section }}">{{ $section }}</option>
          @endforeach
        </select>
        <select class="input" id="filter-status" style="width:180px">
          <option value="">All Status</option>
          <option value="not_assigned">Not Assigned</option>
          <option value="complete">Complete</option>
        </select>
        <span style="flex:1"></span>
        <span style="font-size:12px;color:var(--text3)">{{ $teams->count() }} teams</span>
      </div>

      <!-- Panel assignment modal overlay -->
      <div id="panel-modal" style="display:none;position:fixed;inset:0;background:rgba(24,25,31,0.6);z-index:100;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:14px;padding:24px;width:520px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3)">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
            <div>
              <div style="font-size:15px;font-weight:600;color:var(--text)" id="modal-team-name">Team</div>
              <div style="font-size:12px;color:var(--text3);margin-top:2px" id="modal-team-title">Project Title</div>
            </div>
            <button onclick="closePanelModal()" style="background:none;border:none;font-size:22px;color:var(--text3);cursor:pointer;line-height:1">&times;</button>
          </div>
          <div style="background:rgba(201,168,44,0.06);border:1px solid rgba(201,168,44,0.2);border-radius:8px;padding:12px;margin-bottom:16px;font-size:12px;color:var(--text2)">
            <strong style="color:var(--accent3)">Note:</strong> Panel members are different from the team's adviser/consultant/grammarian. Assign experts specifically for defense evaluation.
          </div>
          <div class="form-group">
            <label class="label">Panel Chair</label>
            <select class="input" id="modal-chair">
              <option value="">— Select Panel Chair —</option>
              @foreach ($experts as $expert)
                <option value="{{ $expert->expert_id }}">{{ $expert->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group">
            <label class="label">Panel Member 1</label>
            <select class="input" id="modal-member1">
              <option value="">— Select Panel Member 1 —</option>
              @foreach ($experts as $expert)
                <option value="{{ $expert->expert_id }}">{{ $expert->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group">
            <label class="label">Panel Member 2</label>
            <select class="input" id="modal-member2">
              <option value="">— Select Panel Member 2 —</option>
              @foreach ($experts as $expert)
                <option value="{{ $expert->expert_id }}">{{ $expert->name }}</option>
              @endforeach
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
            @forelse ($teams as $team)
              @php
                $initials = collect(explode(' ', $team->team_name))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->implode('');
                $section = $teamSections[$team->team_id] ?? '';
                $colorMap = ['#c9a82c,#8a6e10', '#16a34a,#0891b2', '#1d4ed8,#7c3aed', '#b45309,#dc2626', '#0891b2,#16a34a'];
                $gradient = $colorMap[$loop->index % count($colorMap)];
              @endphp
              <tr data-panel-status="not_assigned" data-section="{{ $section }}">
                <td style="padding-left:20px">
                  <div style="display:flex;align-items:center;gap:8px">
                    <div style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,{{ $gradient }});display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">{{ $initials }}</div>
                    <span class="td-main">{{ $team->team_name }}</span>
                  </div>
                </td>
                <td style="font-size:12px;color:var(--text2);white-space:normal;word-break:break-word">{{ $team->project_title ?? '—' }}</td>
                <td>
                  @if ($section)
                    <span class="badge badge-gold" style="font-size:10px">{{ $section }}</span>
                  @else
                    <span style="font-size:12px;color:var(--text3)">—</span>
                  @endif
                </td>
                <td style="font-size:12px;color:var(--text3);font-style:italic">Not assigned</td>
                <td style="font-size:12px;color:var(--text3);font-style:italic">Not assigned</td>
                <td style="font-size:12px;color:var(--text3);font-style:italic">Not assigned</td>
                <td><span class="badge badge-red" style="font-size:10px">Unassigned</span></td>
                <td>
                  <button class="btn btn-primary" style="font-size:10px;padding:4px 10px" onclick="openPanelModal('{{ $team->team_name }}', '{{ $team->project_title ?? '' }}')">
                    <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Assign
                  </button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8" style="text-align:center;padding:40px;color:var(--text3);font-size:13px">
                  No verified teams found. Teams must be confirmed before they can be assigned a panel.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
        <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
          <span style="font-size:12px;color:var(--text3)">Showing {{ $teams->count() }} of {{ $teams->count() }} teams</span>
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

function applyFilters() {
  var sectionVal = document.getElementById('filter-section').value;
  var statusVal = document.getElementById('filter-status').value;
  var visibleCount = 0;
  document.querySelectorAll('#page-panel tbody tr').forEach(function (row) {
    var sectionMatch = !sectionVal || row.getAttribute('data-section') === sectionVal;
    var statusMatch = !statusVal || row.getAttribute('data-panel-status') === statusVal;
    if (sectionMatch && statusMatch) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });
  var countEl = document.querySelector('#page-panel .row span:last-child');
  if (countEl) countEl.textContent = visibleCount + ' teams';
}

document.getElementById('filter-section').addEventListener('change', applyFilters);
document.getElementById('filter-status').addEventListener('change', applyFilters);
</script>
</body>
</html>
