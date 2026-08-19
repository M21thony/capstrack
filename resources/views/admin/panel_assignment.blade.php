<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Panel Assignment - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<style>
.picker-slot { width:100%; text-align:left; background:#fff; border:1px solid var(--border); border-radius:8px; padding:9px 12px; font-size:13px; cursor:pointer; display:flex; align-items:center; justify-content:space-between; gap:8px; color:var(--text3); font-family:inherit }
.picker-slot:hover { border-color:var(--accent3) }
.picker-slot.filled { color:var(--text) }
.picker-slot .slot-change { font-size:11px; color:var(--accent3) }
.expert-picker-option { padding:10px 12px; cursor:pointer; font-size:13px; display:flex; align-items:center; justify-content:space-between; gap:8px; border-radius:8px }
.expert-picker-option:hover { background:rgba(201,168,44,0.08) }
.expert-picker-option .opt-name { color:var(--text) }
.expert-picker-option .opt-tags { font-size:10px; color:var(--text3) }
.expert-picker-option.suggested .opt-name::before { content:"★ "; color:var(--accent3) }
.expert-picker-empty { padding:16px 12px; font-size:12px; color:var(--text3); text-align:center }
/* Calendar - matches instructor/schedule's cal-* classes for visual parity */
.cal-card { border:1px solid var(--border); border-radius:14px; padding:16px; margin-top:6px }
.cal-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; flex-wrap:wrap; gap:10px }
.cal-title-group { display:flex; flex-direction:column; gap:2px }
.cal-title { font-size:13px; font-weight:700; color:var(--text) }
.cal-month-label { font-family:'Space Mono',monospace; font-size:11px; color:var(--text3); letter-spacing:0.5px }
.cal-controls { display:flex; align-items:center; gap:8px }
.cal-nav-btn { width:30px; height:30px; border-radius:8px; border:1.5px solid var(--border); background:var(--bg3); cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.15s; color:var(--text2) }
.cal-nav-btn:hover { background:var(--accent-light); border-color:var(--accent); color:var(--accent3) }
.cal-nav-btn svg { width:14px; height:14px; stroke:currentColor; fill:none; stroke-width:2.5 }
.cal-month-display { font-family:'Space Mono',monospace; font-size:13px; font-weight:700; color:var(--text); min-width:96px; text-align:center }
.cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:1px; background:var(--border); border:1px solid var(--border); border-radius:10px; overflow:hidden }
.cal-day-label { background:var(--navy-dark); padding:9px 4px; text-align:center; font-size:10px; font-weight:700; color:var(--navy-text2); letter-spacing:0.8px; text-transform:uppercase }
.cal-cell { background:#fff; min-height:86px; padding:8px; display:flex; flex-direction:column; gap:3px; position:relative; cursor:pointer; transition:all 0.15s; user-select:none }
.cal-cell.other-month { background:var(--bg3); opacity:0.5; cursor:default }
.cal-cell:not(.other-month):hover { background:rgba(201,168,44,0.08) }
.cal-cell.today { background:rgba(201,168,44,0.04) }
.cal-cell.today .cal-date-num { background:var(--accent); color:#18191f; border-radius:50%; width:22px; height:22px; display:flex; align-items:center; justify-content:center; font-weight:700 }
.cal-cell.in-range { background:rgba(201,168,44,0.08) }
.cal-cell.selected { background:rgba(29,78,216,0.06)!important; box-shadow:inset 0 0 0 2px rgba(29,78,216,0.35)!important }
.cal-date-num { font-family:'Space Mono',monospace; font-size:11px; font-weight:700; color:var(--text3); line-height:1; margin-bottom:2px; width:22px; height:22px; display:flex; align-items:center; justify-content:center }
.cal-slot-pill { font-size:9px; font-weight:700; padding:2px 5px; border-radius:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; line-height:1.4 }
.cal-slot-pill.type-defense { background:rgba(180,83,9,0.1); color:var(--amber); border-left:2px solid var(--amber) }
.cal-more { font-size:9px; color:var(--text3); font-weight:600; margin-top:2px }
.win-cal-range-label { margin-top:10px; font-size:12px; color:var(--text3); text-align:center }
</style>
</head>
<body>

@include('admin._sidebar')
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Panel Assignment</div>
    <div class="topbar-actions">
      @include('admin.partials.notification-dropdown')
    </div>
  </div>

  <div class="content">
    <div id="page-panel" class="page active">
      <div class="row" style="margin-bottom:16px;gap:8px">
        <input class="input" id="team-search-input" placeholder="Search team or capstone title..." style="max-width:300px"/>
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
        <button class="btn btn-ghost" style="font-size:12px;color:var(--text2);border-color:var(--border)" onclick="openWindowModal()">
          <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          Defense Window
        </button>
        <span style="flex:1"></span>
        <span style="font-size:12px;color:var(--text3)" id="team-count">{{ $teams->count() }} teams</span>
      </div>

      <!-- Defense window modal overlay -->
      <div id="window-modal" style="display:none;position:fixed;inset:0;background:rgba(24,25,31,0.6);z-index:100;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:14px;padding:24px;width:720px;box-shadow:0 20px 60px rgba(0,0,0,0.3)">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
            <div style="font-size:15px;font-weight:600;color:var(--text)">Defense Window</div>
            <button onclick="closeWindowModal()" style="background:none;border:none;font-size:22px;color:var(--text3);cursor:pointer;line-height:1">&times;</button>
          </div>
          <div style="font-size:11px;color:var(--text3);margin-bottom:16px">
            The date range a defense stage is scheduled within. Panels are automatically assigned
            a day and venue inside this window.
          </div>
          <div id="window-modal-msg" style="display:none;font-size:12px;border-radius:8px;padding:10px 12px;margin-bottom:14px"></div>

          <div class="form-group">
            <label class="label">Defense Type</label>
            <select class="input" id="window-defense-type">
              <option value="Pre-proposal Defense">Pre-proposal Defense</option>
              <option value="Title Defense">Title Defense</option>
              <option value="Pre Oral Defense">Pre Oral Defense</option>
              <option value="Final Oral Defense">Final Oral Defense</option>
            </select>
          </div>

          <div class="cal-card">
            <div class="cal-header">
              <div class="cal-title-group">
                <div class="cal-title">Defense Window</div>
                <div class="cal-month-label" id="win-cal-month-label">–</div>
              </div>
              <div class="cal-controls">
                <button type="button" class="cal-nav-btn" onclick="winCalNav(-1)" aria-label="Previous month">
                  <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <div class="cal-month-display" id="win-cal-month-display">–</div>
                <button type="button" class="cal-nav-btn" onclick="winCalNav(1)" aria-label="Next month">
                  <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
              </div>
            </div>
            <div class="cal-grid" id="win-cal-grid"></div>
          </div>
          <div class="win-cal-range-label" id="win-cal-range-label">Click a date to set the start, then another to set the end.</div>
          <input type="hidden" id="window-start-date"/>
          <input type="hidden" id="window-end-date"/>

          <div style="display:flex;gap:8px;margin-top:16px">
            <button class="btn btn-primary" style="flex:1;justify-content:center;font-size:13px" id="window-save-btn" onclick="saveWindow()">
              <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              Save
            </button>
            <button class="btn btn-ghost" style="font-size:13px;color:var(--text2);border-color:var(--border)" onclick="closeWindowModal()">Close</button>
          </div>
        </div>
      </div>

      <!-- Manual schedule override modal — lets the admin override the
           auto-generated day/venue for one team's defense stage. A
           conflict (double-booked venue or panelist) doesn't block the
           save; it's shown so the admin can confirm and override anyway. -->
      <div id="schedule-modal" style="display:none;position:fixed;inset:0;background:rgba(24,25,31,0.6);z-index:100;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:14px;padding:24px;width:420px;box-shadow:0 20px 60px rgba(0,0,0,0.3)">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
            <div style="font-size:15px;font-weight:600;color:var(--text)">Override Schedule</div>
            <button onclick="closeScheduleModal()" style="background:none;border:none;font-size:22px;color:var(--text3);cursor:pointer;line-height:1">&times;</button>
          </div>
          <div style="font-size:11px;color:var(--text3);margin-bottom:16px">
            Manually set this team's day, time, and venue, overriding what the automatic scheduler assigned. Defense day runs 8am-5pm in fixed 2-hour sessions.
          </div>
          <div id="schedule-modal-conflicts" style="display:none;font-size:12px;background:rgba(220,38,38,0.08);border:1px solid rgba(220,38,38,0.25);color:#b91c1c;border-radius:8px;padding:10px 12px;margin-bottom:14px">
            <div style="font-weight:600;margin-bottom:6px">This conflicts with an existing booking:</div>
            <ul id="schedule-modal-conflicts-list" style="margin:0;padding-left:16px"></ul>
          </div>

          <div class="form-group">
            <label class="label">Date</label>
            <input class="input" type="date" id="schedule-modal-date"/>
          </div>
          <div class="row" style="gap:10px">
            <div class="form-group" style="flex:1">
              <label class="label">Time</label>
              <select class="input" id="schedule-modal-time">
                <option value="08:00">8:00 AM – 10:00 AM</option>
                <option value="10:00">10:00 AM – 12:00 PM</option>
                <option value="12:00">12:00 PM – 2:00 PM</option>
                <option value="14:00">2:00 PM – 4:00 PM</option>
              </select>
            </div>
            <div class="form-group" style="flex:1">
              <label class="label">Venue</label>
              <select class="input" id="schedule-modal-venue">
                <option value="Mac Lab">Mac Lab</option>
                <option value="Open Lab">Open Lab</option>
              </select>
            </div>
          </div>

          <div style="display:flex;gap:8px;margin-top:16px">
            <button class="btn btn-primary" style="flex:1;justify-content:center;font-size:13px" id="schedule-save-btn" onclick="saveScheduleOverride()">
              <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              <span id="schedule-save-btn-label">Save</span>
            </button>
            <button class="btn btn-ghost" style="font-size:13px;color:var(--text2);border-color:var(--border)" onclick="closeScheduleModal()">Cancel</button>
          </div>
        </div>
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
          <div id="modal-error" style="display:none;background:rgba(220,38,38,0.08);border:1px solid rgba(220,38,38,0.25);color:#b91c1c;border-radius:8px;padding:10px 12px;margin-bottom:14px;font-size:12px"></div>

          <div class="form-group">
            <label class="label">Defense Type</label>
            <select class="input" id="modal-defense">
              <option value="Pre-proposal Defense">Pre-proposal Defense</option>
              <option value="Title Defense">Title Defense</option>
              <option value="Pre Oral Defense">Pre Oral Defense</option>
              <option value="Final Oral Defense">Final Oral Defense</option>
            </select>
          </div>
          <div id="modal-schedule-result" style="display:none;font-size:12px;color:var(--text2);background:rgba(22,163,74,0.08);border:1px solid rgba(22,163,74,0.2);border-radius:8px;padding:10px 12px;margin-bottom:16px">
            Scheduled for <strong id="modal-schedule-result-text"></strong>
          </div>
          <div style="font-size:11px;color:var(--text3);margin-bottom:16px">
            Assign panels for every team first. Day and venue aren't set here - once everyone
            has a panel, use the "Defense Window" button to generate the schedule for all of
            them at once.
          </div>

          <div class="form-group" id="picker-chair">
            <label class="label">Panel Chair</label>
            <input type="hidden" class="picker-value"/>
            <button type="button" class="picker-slot" onclick="openExpertPicker('chair')">
              <span class="slot-text">Click to select an expert</span>
              <span class="slot-change" style="display:none">Change</span>
            </button>
          </div>
          <div class="form-group" id="picker-member1">
            <label class="label">Panel Member 1</label>
            <input type="hidden" class="picker-value"/>
            <button type="button" class="picker-slot" onclick="openExpertPicker('member1')">
              <span class="slot-text">Click to select an expert</span>
              <span class="slot-change" style="display:none">Change</span>
            </button>
          </div>
          <div class="form-group" id="picker-member2" style="margin-bottom:0">
            <label class="label">Panel Member 2</label>
            <input type="hidden" class="picker-value"/>
            <button type="button" class="picker-slot" onclick="openExpertPicker('member2')">
              <span class="slot-text">Click to select an expert</span>
              <span class="slot-change" style="display:none">Change</span>
            </button>
          </div>

          <div style="display:flex;gap:8px;margin-top:16px">
            <button class="btn btn-primary" style="flex:1;justify-content:center;font-size:13px" id="modal-save-btn" onclick="savePanelAssignment()">
              <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              Save Panel Assignment
            </button>
            <button class="btn btn-ghost" style="font-size:13px;color:var(--text2);border-color:var(--border)" onclick="closePanelModal()">Cancel</button>
          </div>
        </div>
      </div>

      <!-- Expert picker modal - opened from a picker-slot button, sits on
           top of the panel-assignment modal (higher z-index) -->
      <div id="expert-picker-modal" style="display:none;position:fixed;inset:0;background:rgba(24,25,31,0.6);z-index:200;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:14px;padding:20px;width:420px;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,0.3)">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
            <div style="font-size:15px;font-weight:600;color:var(--text)" id="expert-picker-title">Select Expert</div>
            <button onclick="closeExpertPicker()" style="background:none;border:none;font-size:22px;color:var(--text3);cursor:pointer;line-height:1">&times;</button>
          </div>
          <input class="input" id="expert-picker-search" placeholder="Search by name or expertise..." autocomplete="off" style="margin-bottom:10px"/>
          <div id="expert-picker-results" style="overflow-y:auto;flex:1"></div>
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
              <th>Schedule</th>
              <th>Status</th>
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
                $latestSchedule = $team->defenseSchedules->sortByDesc('defense_id')->first();
                $chair = $latestSchedule?->panels->firstWhere('panel_role', 'Panel Chair');
                $member1 = $latestSchedule?->panels->firstWhere('panel_role', 'Panel Member 1');
                $member2 = $latestSchedule?->panels->firstWhere('panel_role', 'Panel Member 2');
                $status = $chair ? 'complete' : 'not_assigned';
                $scheduleTimeLabel = null;
                $scheduleTimeValue = null;
                if ($latestSchedule?->schedule_date && $latestSchedule->schedule_date->format('H:i') !== '00:00') {
                    $scheduleTimeValue = $latestSchedule->schedule_date->format('H:i');
                    $scheduleEnd = $latestSchedule->end_date ?? $latestSchedule->schedule_date->copy()->addHours(2);
                    $scheduleTimeLabel = ' · '.$latestSchedule->schedule_date->format('g:i A').'–'.$scheduleEnd->format('g:i A');
                }
              @endphp
              <tr data-panel-status="{{ $status }}" data-section="{{ $section }}" data-team-id="{{ $team->team_id }}" id="team-row-{{ $team->team_id }}">
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
                <td class="cell-chair" style="font-size:12px;{{ $chair ? 'color:var(--text)' : 'color:var(--text3);font-style:italic' }}">{{ $chair?->expert?->name ?? 'Not assigned' }}</td>
                <td class="cell-member1" style="font-size:12px;{{ $member1 ? 'color:var(--text)' : 'color:var(--text3);font-style:italic' }}">{{ $member1?->expert?->name ?? 'Not assigned' }}</td>
                <td class="cell-member2" style="font-size:12px;{{ $member2 ? 'color:var(--text)' : 'color:var(--text3);font-style:italic' }}">{{ $member2?->expert?->name ?? 'Not assigned' }}</td>
                <td class="cell-schedule" style="font-size:12px;color:var(--text2)">
                  @if ($latestSchedule?->schedule_date)
                    <span class="cell-schedule-text">{{ $latestSchedule->schedule_date->format('M j, Y').$scheduleTimeLabel.' · '.$latestSchedule->venue }}</span>
                    <button type="button" class="cell-schedule-edit" onclick="openScheduleModal({{ $team->team_id }}, {{ $latestSchedule->defense_id }}, '{{ $latestSchedule->schedule_date->toDateString() }}', '{{ $scheduleTimeValue ?? '08:00' }}', '{{ $latestSchedule->venue }}')" title="Override day/time/venue" style="background:rgba(201,168,44,0.12);border:1px solid rgba(201,168,44,0.35);color:var(--accent3);cursor:pointer;padding:4px 6px;border-radius:6px;vertical-align:middle;margin-left:6px;display:inline-flex;align-items:center">
                      <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.25"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                  @else
                    <span class="cell-schedule-text">—</span>
                  @endif
                </td>
                <td class="cell-status"><span class="badge {{ $chair ? 'badge-green' : 'badge-red' }}" style="font-size:10px">{{ $chair ? 'Assigned' : 'Unassigned' }}</span></td>
                <td>
                  <button class="btn btn-primary cell-assign-btn" style="font-size:10px;padding:4px 10px" onclick="openPanelModal({{ $team->team_id }})">
                    <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span class="assign-btn-label">{{ $chair ? 'Update' : 'Assign' }}</span>
                  </button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="9" style="text-align:center;padding:40px;color:var(--text3);font-size:13px">
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

<div id="pa-toast" style="
  position:fixed;bottom:24px;right:24px;z-index:9999;
  background:var(--bg2,#1e1e2e);color:var(--text,#e0e0e0);
  padding:12px 18px;border-radius:10px;border-left:4px solid var(--accent,#c9a82c);
  font-size:13px;font-family:'DM Sans',sans-serif;box-shadow:0 4px 24px rgba(0,0,0,.4);
  display:flex;align-items:center;gap:10px;
  opacity:0;transform:translateY(10px);transition:opacity .25s,transform .25s;
  pointer-events:none;">
  <span id="pa-toast-msg"></span>
</div>
<style>#pa-toast.show{opacity:1;transform:translateY(0);pointer-events:auto}</style>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]');
const csrf = csrfToken ? csrfToken.getAttribute('content') : '';

// ── Data from the server ────────────────────────────────────────────────
const TEAMS = @json($teamsForJs);
const EXPERTS = @json($expertsForJs);

const TEAMS_BY_ID = Object.fromEntries(TEAMS.map(t => [t.team_id, t]));

let currentTeamId = null;
let currentSuggestedIds = [];

// ── Expert picker (opens a dedicated modal, not an inline dropdown) ──────
const PICKER_LABELS = { chair: 'Panel Chair', member1: 'Panel Member 1', member2: 'Panel Member 2' };
let currentPickerRole = null;

function escHtml(s) {
  return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function getPickerSlot(role) {
  return document.getElementById('picker-' + role);
}

function setPickerValue(role, id) {
  const slot = getPickerSlot(role);
  const hidden = slot.querySelector('.picker-value');
  const btn = slot.querySelector('.picker-slot');
  const textEl = btn.querySelector('.slot-text');
  const changeEl = btn.querySelector('.slot-change');
  const expert = EXPERTS.find(e => e.id === id);

  hidden.value = id || '';

  if (expert) {
    textEl.textContent = expert.name;
    btn.classList.add('filled');
    changeEl.style.display = 'inline';
  } else {
    textEl.textContent = 'Click to select an expert';
    btn.classList.remove('filled');
    changeEl.style.display = 'none';
  }
}

function getPickerValue(role) {
  const v = getPickerSlot(role).querySelector('.picker-value').value;
  return v ? parseInt(v, 10) : null;
}

function renderExpertPickerResults(term) {
  const excluded = TEAMS_BY_ID[currentTeamId]?.excluded_expert_ids || [];
  const otherRoles = Object.keys(PICKER_LABELS).filter(r => r !== currentPickerRole);
  const takenByOtherRoles = otherRoles.map(r => getPickerValue(r)).filter(Boolean);

  const normalizedTerm = (term || '').toLowerCase().trim();
  const matches = EXPERTS.filter(e => {
    if (excluded.includes(e.id) || takenByOtherRoles.includes(e.id)) return false;
    if (!normalizedTerm) return true;
    return e.name.toLowerCase().includes(normalizedTerm)
      || e.tags.some(t => t.toLowerCase().includes(normalizedTerm));
  });

  const suggested = matches.filter(e => currentSuggestedIds.includes(e.id));
  const rest = matches.filter(e => !currentSuggestedIds.includes(e.id));
  const ordered = suggested.concat(rest);

  const results = document.getElementById('expert-picker-results');

  if (ordered.length === 0) {
    results.innerHTML = '<div class="expert-picker-empty">No experts found.</div>';
    return;
  }

  results.innerHTML = ordered.map(e => {
    const isSuggested = currentSuggestedIds.includes(e.id);
    return '<div class="expert-picker-option' + (isSuggested ? ' suggested' : '') + '" data-id="' + e.id + '">'
      + '<span class="opt-name">' + escHtml(e.name) + '</span>'
      + (e.tags.length ? '<span class="opt-tags">' + escHtml(e.tags.join(', ')) + '</span>' : '')
      + '</div>';
  }).join('');
}

function openExpertPicker(role) {
  currentPickerRole = role;
  document.getElementById('expert-picker-title').textContent = 'Select ' + PICKER_LABELS[role];
  document.getElementById('expert-picker-search').value = '';
  renderExpertPickerResults('');
  document.getElementById('expert-picker-modal').style.display = 'flex';
  document.getElementById('expert-picker-search').focus();
}

function closeExpertPicker() {
  document.getElementById('expert-picker-modal').style.display = 'none';
  currentPickerRole = null;
}

document.getElementById('expert-picker-search').addEventListener('input', (ev) => renderExpertPickerResults(ev.target.value));
document.getElementById('expert-picker-results').addEventListener('click', (ev) => {
  const opt = ev.target.closest('.expert-picker-option');
  if (!opt || !currentPickerRole) return;
  setPickerValue(currentPickerRole, parseInt(opt.getAttribute('data-id'), 10));
  closeExpertPicker();
});

// ── Modal open/close ────────────────────────────────────────────────────
function formatScheduleText(schedule) {
  if (!schedule || !schedule.schedule_date) return null;
  const d = new Date(schedule.schedule_date);
  const dateLabel = d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  const timeLabel = formatScheduleTimeRange(schedule);
  return dateLabel + (timeLabel ? ' · ' + timeLabel : '') + ' · ' + schedule.venue;
}

// A midnight (00:00) time means no real time was ever set for this slot
// (legacy data from before sessions had a time component) - only show a
// range when there's an actual time to show.
function formatScheduleTimeRange(schedule) {
  if (!schedule || !schedule.schedule_date) return null;
  const d = new Date(schedule.schedule_date);
  if (d.getHours() === 0 && d.getMinutes() === 0) return null;
  const end = schedule.end_date ? new Date(schedule.end_date) : new Date(d.getTime() + 2 * 60 * 60 * 1000);
  const fmt = (t) => t.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
  return fmt(d) + '–' + fmt(end);
}

function isoDateOnly(schedule) {
  // schedule_date comes through as 'YYYY-MM-DDTHH:MM' — just the date part.
  return schedule && schedule.schedule_date ? schedule.schedule_date.slice(0, 10) : null;
}

function isoTimeOnly(schedule) {
  if (!schedule || !schedule.schedule_date) return '08:00';
  const time = schedule.schedule_date.slice(11, 16);
  return time === '00:00' || !time ? '08:00' : time;
}

function scheduleCellHtml(teamId, schedule) {
  const text = formatScheduleText(schedule);
  if (!text || !schedule.defense_id) {
    return `<span class="cell-schedule-text">${text || '—'}</span>`;
  }
  const dateOnly = isoDateOnly(schedule);
  const timeOnly = isoTimeOnly(schedule);
  return `<span class="cell-schedule-text">${text}</span>` +
    `<button type="button" class="cell-schedule-edit" onclick="openScheduleModal(${teamId}, ${schedule.defense_id}, '${dateOnly}', '${timeOnly}', '${schedule.venue}')" title="Override day/time/venue" style="background:rgba(201,168,44,0.12);border:1px solid rgba(201,168,44,0.35);color:var(--accent3);cursor:pointer;padding:4px 6px;border-radius:6px;vertical-align:middle;margin-left:6px;display:inline-flex;align-items:center">` +
    `<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.25"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>` +
    `</button>`;
}

function fillModalFromSchedule(defenseType) {
  const team = TEAMS_BY_ID[currentTeamId];
  const schedule = team.schedules.find(s => s.defense_type === defenseType);

  setPickerValue('chair', schedule?.chair_expert_id ?? null);
  setPickerValue('member1', schedule?.member1_expert_id ?? null);
  setPickerValue('member2', schedule?.member2_expert_id ?? null);

  const resultText = formatScheduleText(schedule);
  const resultEl = document.getElementById('modal-schedule-result');
  if (resultText) {
    document.getElementById('modal-schedule-result-text').textContent = resultText;
    resultEl.style.display = 'block';
  } else {
    resultEl.style.display = 'none';
  }
}

function openPanelModal(teamId) {
  currentTeamId = teamId;
  const team = TEAMS_BY_ID[teamId];

  document.getElementById('modal-team-name').textContent = team.team_name;
  document.getElementById('modal-team-title').textContent = team.project_title || '';
  document.getElementById('modal-error').style.display = 'none';

  const defenseSelect = document.getElementById('modal-defense');
  const latest = team.schedules[team.schedules.length - 1];
  defenseSelect.value = latest ? latest.defense_type : 'Pre-proposal Defense';
  fillModalFromSchedule(defenseSelect.value);

  document.getElementById('panel-modal').style.display = 'flex';

  currentSuggestedIds = [];
  fetch(`/api/admin/panel_assignment/${teamId}/suggest`, {
    headers: { 'Accept': 'application/json' },
  })
    .then(res => res.json())
    .then(json => {
      if (json.success) {
        currentSuggestedIds = json.data.map(s => s.expert_id);
      }
    })
    .catch(() => {});
}

document.getElementById('modal-defense').addEventListener('change', (ev) => fillModalFromSchedule(ev.target.value));

function closePanelModal() {
  document.getElementById('panel-modal').style.display = 'none';
  currentTeamId = null;
}

function showModalError(message) {
  const el = document.getElementById('modal-error');
  el.textContent = message;
  el.style.display = 'block';
}

// ── Save ─────────────────────────────────────────────────────────────────
async function savePanelAssignment() {
  if (!currentTeamId) return;

  const chairId = getPickerValue('chair');
  if (!chairId) {
    showModalError('A Panel Chair is required.');
    return;
  }

  const payload = {
    defense_type: document.getElementById('modal-defense').value,
    chair_expert_id: chairId,
    member1_expert_id: getPickerValue('member1'),
    member2_expert_id: getPickerValue('member2'),
  };

  const btn = document.getElementById('modal-save-btn');
  btn.disabled = true;

  try {
    const res = await fetch(`/api/admin/panel_assignment/${currentTeamId}`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const json = await res.json();

    if (!res.ok || !json.success) {
      showModalError(json.message || 'Could not save this assignment. Please try again.');
      return;
    }

    updateTeamRow(currentTeamId, json.data);
    closePanelModal();
  } catch (e) {
    showModalError('Could not reach the server. Please try again.');
  } finally {
    btn.disabled = false;
  }
}

function updateTeamRow(teamId, scheduleData) {
  const team = TEAMS_BY_ID[teamId];
  const idx = team.schedules.findIndex(s => s.defense_type === scheduleData.defense_type);
  const panelByRole = Object.fromEntries(scheduleData.panels.map(p => [p.panel_role, p]));
  const updated = {
    defense_id: scheduleData.defense_id,
    defense_type: scheduleData.defense_type,
    schedule_date: scheduleData.schedule_date ? scheduleData.schedule_date.replace(' ', 'T').slice(0, 16) : null,
    venue: scheduleData.venue,
    chair_expert_id: panelByRole['Panel Chair']?.expert_id ?? null,
    member1_expert_id: panelByRole['Panel Member 1']?.expert_id ?? null,
    member2_expert_id: panelByRole['Panel Member 2']?.expert_id ?? null,
  };
  if (idx >= 0) team.schedules[idx] = updated; else team.schedules.push(updated);

  const row = document.getElementById('team-row-' + teamId);
  if (!row) return;

  const chairName = panelByRole['Panel Chair']?.expert_name;
  const m1Name = panelByRole['Panel Member 1']?.expert_name;
  const m2Name = panelByRole['Panel Member 2']?.expert_name;

  const chairCell = row.querySelector('.cell-chair');
  chairCell.textContent = chairName || 'Not assigned';
  chairCell.style.color = chairName ? 'var(--text)' : 'var(--text3)';
  chairCell.style.fontStyle = chairName ? 'normal' : 'italic';

  const m1Cell = row.querySelector('.cell-member1');
  m1Cell.textContent = m1Name || 'Not assigned';
  m1Cell.style.color = m1Name ? 'var(--text)' : 'var(--text3)';
  m1Cell.style.fontStyle = m1Name ? 'normal' : 'italic';

  const m2Cell = row.querySelector('.cell-member2');
  m2Cell.textContent = m2Name || 'Not assigned';
  m2Cell.style.color = m2Name ? 'var(--text)' : 'var(--text3)';
  m2Cell.style.fontStyle = m2Name ? 'normal' : 'italic';

  const scheduleCell = row.querySelector('.cell-schedule');
  scheduleCell.innerHTML = scheduleCellHtml(teamId, updated);

  const statusBadge = row.querySelector('.cell-status .badge');
  statusBadge.textContent = chairName ? 'Assigned' : 'Unassigned';
  statusBadge.className = 'badge ' + (chairName ? 'badge-green' : 'badge-red');
  row.setAttribute('data-panel-status', chairName ? 'complete' : 'not_assigned');

  const assignBtnLabel = row.querySelector('.assign-btn-label');
  if (assignBtnLabel) assignBtnLabel.textContent = chairName ? 'Update' : 'Assign';
}

// ── Manual schedule override ──────────────────────────────────────────────
let currentScheduleTeamId = null;
let currentScheduleDefenseId = null;
let scheduleOverrideConfirmed = false;

function openScheduleModal(teamId, defenseId, dateStr, timeStr, venue) {
  currentScheduleTeamId = teamId;
  currentScheduleDefenseId = defenseId;
  scheduleOverrideConfirmed = false;

  document.getElementById('schedule-modal-date').value = dateStr || '';
  document.getElementById('schedule-modal-time').value = timeStr || '08:00';
  document.getElementById('schedule-modal-venue').value = venue || 'Mac Lab';
  document.getElementById('schedule-modal-conflicts').style.display = 'none';
  document.getElementById('schedule-save-btn-label').textContent = 'Save';

  document.getElementById('schedule-modal').style.display = 'flex';
}

function closeScheduleModal() {
  document.getElementById('schedule-modal').style.display = 'none';
  currentScheduleTeamId = null;
  currentScheduleDefenseId = null;
  scheduleOverrideConfirmed = false;
}

async function saveScheduleOverride() {
  if (!currentScheduleDefenseId) return;

  const date = document.getElementById('schedule-modal-date').value;
  const time = document.getElementById('schedule-modal-time').value;
  const venue = document.getElementById('schedule-modal-venue').value;
  if (!date) { showToast('Please pick a date.', 'error'); return; }

  const btn = document.getElementById('schedule-save-btn');
  btn.disabled = true;

  try {
    const res = await fetch(`/api/admin/panel_assignment/schedule/${currentScheduleDefenseId}`, {
      method: 'PUT',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ schedule_date: date, start_time: time, venue: venue, confirm: scheduleOverrideConfirmed }),
    });
    const json = await res.json();

    if (res.status === 409 && json.requires_confirmation) {
      const list = document.getElementById('schedule-modal-conflicts-list');
      list.innerHTML = json.conflicts.map(c => `<li>${c}</li>`).join('');
      document.getElementById('schedule-modal-conflicts').style.display = 'block';
      document.getElementById('schedule-save-btn-label').textContent = 'Save Anyway';
      scheduleOverrideConfirmed = true;
      return;
    }

    if (!res.ok || !json.success) {
      showToast(json.message || 'Could not save this schedule. Please try again.', 'error');
      return;
    }

    updateTeamRow(currentScheduleTeamId, json.data);
    showToast('Schedule updated.');
    closeScheduleModal();
  } catch (e) {
    showToast('Could not reach the server. Please try again.', 'error');
  } finally {
    btn.disabled = false;
  }
}

// ── Defense Window (single form, reused per defense type) ────────────────
let WINDOWS_BY_TYPE = {};

function showWindowMsg(text, isError) {
  const el = document.getElementById('window-modal-msg');
  el.textContent = text;
  el.style.display = 'block';
  el.style.background = isError ? 'rgba(220,38,38,0.08)' : 'rgba(22,163,74,0.08)';
  el.style.border = '1px solid ' + (isError ? 'rgba(220,38,38,0.25)' : 'rgba(22,163,74,0.2)');
  el.style.color = isError ? '#b91c1c' : 'var(--green, #16a34a)';
}

function fillWindowForm(defenseType) {
  const w = WINDOWS_BY_TYPE[defenseType];
  winCalRange = { start: w?.start_date || null, end: w?.end_date || null };
  document.getElementById('window-start-date').value = winCalRange.start || '';
  document.getElementById('window-end-date').value = winCalRange.end || '';

  if (winCalRange.start) {
    const [y, m] = winCalRange.start.split('-').map(Number);
    winCalView = { year: y, month: m - 1 };
  } else {
    const now = new Date();
    winCalView = { year: now.getFullYear(), month: now.getMonth() };
  }
  renderWinCal();
}

// ── Defense Window calendar ─────────────────────────────────────────────
let winCalView = { year: new Date().getFullYear(), month: new Date().getMonth() };
let winCalRange = { start: null, end: null };

function pad2(n) { return String(n).padStart(2, '0'); }
function winCalDateKey(y, m, d) { return `${y}-${pad2(m + 1)}-${pad2(d)}`; }

function winCalFormatLabel(key) {
  const [y, m, d] = key.split('-').map(Number);
  return new Date(y, m - 1, d).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

function winCalScheduledDays() {
  const map = {};
  TEAMS.forEach(t => (t.schedules || []).forEach(s => {
    if (!s.schedule_date) return;
    const day = s.schedule_date.slice(0, 10);
    (map[day] = map[day] || []).push(t.team_name);
  }));
  return map;
}

function winCalNav(delta) {
  winCalView.month += delta;
  if (winCalView.month < 0) { winCalView.month = 11; winCalView.year--; }
  if (winCalView.month > 11) { winCalView.month = 0; winCalView.year++; }
  renderWinCal();
}

function winCalPick(key) {
  if (!winCalRange.start || (winCalRange.start && winCalRange.end)) {
    winCalRange = { start: key, end: null };
  } else if (key < winCalRange.start) {
    winCalRange = { start: key, end: null };
  } else {
    winCalRange.end = key;
  }
  document.getElementById('window-start-date').value = winCalRange.start || '';
  document.getElementById('window-end-date').value = winCalRange.end || '';
  renderWinCal();
}

function renderWinCal() {
  const grid = document.getElementById('win-cal-grid');
  const label = document.getElementById('win-cal-month-label');
  const display = document.getElementById('win-cal-month-display');
  if (!grid || !label) return;

  const { year, month } = winCalView;
  const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  label.textContent = `${MONTHS[month]} ${year}`;
  display.textContent = `${MONTHS[month].substring(0, 3).toUpperCase()} ${year}`;

  const scheduled = winCalScheduledDays();
  const firstDow = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const daysInPrevMonth = new Date(year, month, 0).getDate();
  const now = new Date();
  const todayKey = winCalDateKey(now.getFullYear(), now.getMonth(), now.getDate());

  let html = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
    .map(d => `<div class="cal-day-label">${d}</div>`).join('');

  // Leading padding — previous month
  for (let i = firstDow - 1; i >= 0; i--) {
    html += `<div class="cal-cell other-month"><div class="cal-date-num">${daysInPrevMonth - i}</div></div>`;
  }

  for (let d = 1; d <= daysInMonth; d++) {
    const key = winCalDateKey(year, month, d);
    const classes = ['cal-cell'];
    if (key === todayKey) classes.push('today');
    if (winCalRange.start === key || winCalRange.end === key) classes.push('selected');
    else if (winCalRange.start && winCalRange.end && key > winCalRange.start && key < winCalRange.end) classes.push('in-range');

    const teams = scheduled[key] || [];
    let extra = '';
    teams.slice(0, 2).forEach(name => {
      extra += `<div class="cal-slot-pill type-defense" title="${name}">${name}</div>`;
    });
    if (teams.length > 2) extra += `<div class="cal-more">+${teams.length - 2} more</div>`;

    html += `<div class="${classes.join(' ')}" onclick="winCalPick('${key}')"><div class="cal-date-num">${d}</div>${extra}</div>`;
  }

  // Trailing padding — next month
  const totalCells = firstDow + daysInMonth;
  const remainder = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
  for (let d = 1; d <= remainder; d++) {
    html += `<div class="cal-cell other-month"><div class="cal-date-num">${d}</div></div>`;
  }

  grid.innerHTML = html;

  const rangeLabelEl = document.getElementById('win-cal-range-label');
  if (winCalRange.start && winCalRange.end) {
    rangeLabelEl.textContent = winCalFormatLabel(winCalRange.start) + ' – ' + winCalFormatLabel(winCalRange.end);
  } else if (winCalRange.start) {
    rangeLabelEl.textContent = winCalFormatLabel(winCalRange.start) + ' – pick an end date';
  } else {
    rangeLabelEl.textContent = 'Click a date to set the start, then another to set the end.';
  }
}

function openWindowModal() {
  document.getElementById('window-modal-msg').style.display = 'none';
  document.getElementById('window-modal').style.display = 'flex';
  renderWinCal();

  fetch('/api/admin/panel_assignment/windows', { headers: { 'Accept': 'application/json' } })
    .then(res => res.json())
    .then(json => {
      if (!json.success) return;
      WINDOWS_BY_TYPE = Object.fromEntries(json.data.map(w => [w.defense_type, w]));
      fillWindowForm(document.getElementById('window-defense-type').value);
    })
    .catch(() => {});
}

function closeWindowModal() {
  document.getElementById('window-modal').style.display = 'none';
}

document.getElementById('window-defense-type').addEventListener('change', (ev) => fillWindowForm(ev.target.value));

async function saveWindow() {
  const defenseType = document.getElementById('window-defense-type').value;
  const start = document.getElementById('window-start-date').value;
  const end = document.getElementById('window-end-date').value;

  if (!start || !end) {
    showWindowMsg('Pick both a start and end date.', true);
    return;
  }

  const btn = document.getElementById('window-save-btn');
  btn.disabled = true;

  try {
    const res = await fetch('/api/admin/panel_assignment/windows/' + encodeURIComponent(defenseType), {
      method: 'PUT',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ start_date: start, end_date: end }),
    });
    const json = await res.json();

    if (!res.ok || !json.success) {
      showWindowMsg(json.message || 'Could not save.', true);
      return;
    }

    WINDOWS_BY_TYPE[defenseType] = json.data;

    const count = json.scheduled_count || 0;
    if (count > 0) {
      showWindowMsg(`Saved. Scheduled ${count} team${count === 1 ? '' : 's'} — refreshing...`, false);
      setTimeout(() => window.location.reload(), 1200);
    } else {
      showWindowMsg('Saved. No unscheduled panels to assign yet.', false);
    }
  } catch (e) {
    showWindowMsg('Could not reach the server.', true);
  } finally {
    btn.disabled = false;
  }
}

// ── Filters ──────────────────────────────────────────────────────────────
function applyFilters() {
  var searchVal = document.getElementById('team-search-input').value.toLowerCase().trim();
  var sectionVal = document.getElementById('filter-section').value;
  var statusVal = document.getElementById('filter-status').value;
  var visibleCount = 0;
  document.querySelectorAll('#page-panel tbody tr[data-team-id]').forEach(function (row) {
    var sectionMatch = !sectionVal || row.getAttribute('data-section') === sectionVal;
    var statusMatch = !statusVal || row.getAttribute('data-panel-status') === statusVal;
    var searchMatch = !searchVal || row.textContent.toLowerCase().includes(searchVal);
    if (sectionMatch && statusMatch && searchMatch) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });
  document.getElementById('team-count').textContent = visibleCount + ' teams';
}

document.getElementById('team-search-input').addEventListener('input', applyFilters);
document.getElementById('filter-section').addEventListener('change', applyFilters);
document.getElementById('filter-status').addEventListener('change', applyFilters);


/* Mobile sidebar toggle */
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() {
  if (!sidebarEl) return;
  sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar();
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

let paToastTimer;
function showToast(msg, type = 'success') {
  const toast = document.getElementById('pa-toast');
  document.getElementById('pa-toast-msg').textContent = msg;
  toast.style.borderLeftColor = type === 'error' ? 'var(--red,#dc2626)' : 'var(--accent,#c9a82c)';
  toast.classList.add('show');
  clearTimeout(paToastTimer);
  paToastTimer = setTimeout(() => toast.classList.remove('show'), 3500);
}
</script>
</body>
</html>
