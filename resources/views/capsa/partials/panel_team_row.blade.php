@php
  $initials = collect(explode(' ', $team->team_name))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->implode('');
  $section = $teamSections[$team->team_id] ?? '';
  $colorMap = ['#c9a82c,#8a6e10', '#16a34a,#0891b2', '#1d4ed8,#7c3aed', '#b45309,#dc2626', '#0891b2,#16a34a'];
  $gradient = $colorMap[$rowIndex % count($colorMap)];
  // Shows THIS stage's panel/schedule only - a team can have a separate
  // schedule row per defense stage (e.g. an already-scheduled Pre-proposal
  // Defense alongside a not-yet-assigned Title Defense), and this row must
  // always reflect whichever stage is currently selected above, not
  // whichever stage happens to have the newest row.
  // ...and the attempt in play: a stage can have several rows now (a
  // redefense is its own), so firstWhere() would keep returning the first.
  $latestSchedule = $team->defenseSchedules
      ->where('defense_type', $defenseType)
      ->sortByDesc('attempt')
      ->first();
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
<tr data-panel-status="{{ $status }}" data-section="{{ $section }}" data-team-id="{{ $team->team_id }}" data-paid="{{ $isPaid ? '1' : '0' }}" id="team-row-{{ $team->team_id }}">
  <td style="padding-left:20px;width:34px">
    <input type="checkbox" class="team-bulk-checkbox" value="{{ $team->team_id }}" onchange="onBulkCheckboxChange()"/>
  </td>
  <td>
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
      <div style="display:flex;align-items:center;gap:6px;flex-wrap:nowrap">
        <span class="cell-schedule-text">{{ $latestSchedule->schedule_date->format('M j, Y').$scheduleTimeLabel.' · '.$latestSchedule->venue }}</span>
        <button type="button" class="cell-schedule-edit" onclick="openScheduleModal({{ $team->team_id }}, {{ $latestSchedule->defense_id }}, '{{ $latestSchedule->schedule_date->toDateString() }}', '{{ $scheduleTimeValue ?? '08:00' }}', '{{ $latestSchedule->venue }}')" title="Override day/time/venue" style="background:rgba(201,168,44,0.12);border:1px solid rgba(201,168,44,0.35);color:var(--accent3);cursor:pointer;padding:4px 6px;border-radius:6px;display:inline-flex;align-items:center;flex-shrink:0">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.25"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>
      </div>
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
