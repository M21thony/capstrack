<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CapsTRACK - Team Overview</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/expert.css') }}" />
<style>
.team-overview-stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 24px; }
.team-role-tabs { display: flex; flex-wrap: wrap; width: fit-content; max-width: 100%; border-bottom: 1px solid var(--border); margin-bottom: 20px; }
.team-role-tab { padding: 10px 16px; border: none; border-bottom: 2px solid transparent; margin-bottom: -1px; background: transparent; color: var(--text2); font-size: 12.5px; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; transition: 0.13s; white-space: nowrap; }
.team-role-tab.active { color: var(--accent3); border-bottom-color: var(--accent); }
.team-role-tab:hover:not(.active) { color: var(--text); background: var(--bg4); }
.team-role-tab:focus-visible { outline: 2px solid var(--accent); outline-offset: -2px; }
.team-role-panel { display: none; }
.team-role-panel.active { display: block; }

.team-card-modern { background: var(--bg2); border: 1.5px solid var(--border); border-radius: 16px; padding: 20px; margin-bottom: 14px; transition: border-color 0.2s, box-shadow 0.2s; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
.team-card-modern:hover { border-color: var(--accent); box-shadow: 0 6px 24px rgba(201,168,44,0.08); }
.team-card-top { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; }
.team-avatar-lg { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px; color: white; flex-shrink: 0; overflow: hidden; }
.team-avatar-lg img { width: 100%; height: 100%; object-fit: cover; }
.team-meta { flex: 1; min-width: 0; }
.team-name-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.team-name { font-size: 15px; font-weight: 600; color: var(--text); }
.team-project { font-size: 12px; color: var(--text3); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.role-pill-sm { font-size: 9px; font-weight: 600; padding: 3px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.4px; }
.role-pill-adviser { background: var(--accent-light); color: var(--accent3); }
.role-pill-consultant { background: #dbeafe; color: #1d4ed8; }
.role-pill-grammarian { background: #f3e8ff; color: #7c3aed; }
.role-pill-capstone_adviser { background: #fef3c7; color: #b45309; }

.team-members-section { margin-bottom: 0; }
.team-member-row { display: flex; align-items: center; gap: 10px; padding: 7px 0; cursor: pointer; border-radius: 8px; transition: background 0.12s; position: relative; }
.team-member-row:hover { background: var(--bg3); }
.team-member-row + .team-member-row { border-top: 1px solid var(--border); }
.member-avatar-sm { width: 30px; height: 30px; border-radius: 8px; background: var(--bg4); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; color: var(--text2); flex-shrink: 0; overflow: hidden; }
.member-avatar-sm img { width: 100%; height: 100%; object-fit: cover; }
.member-name-text { font-size: 13px; color: var(--text2); flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.member-lead-badge { font-size: 9px; font-weight: 600; padding: 2px 8px; border-radius: 20px; background: rgba(201,168,44,0.15); color: var(--accent3); white-space: nowrap; }

.team-card-footer { padding-top: 14px; margin-top: 14px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; }

/* Member Popover */
.popover-overlay { display: none; position: fixed; inset: 0; z-index: 200; }
.popover-overlay.open { display: block; }
.member-popover { display: none; position: fixed; z-index: 250; background: var(--bg2); border: 1.5px solid var(--border); border-radius: 14px; padding: 18px; width: 260px; max-width: calc(100vw - 24px); box-shadow: 0 12px 40px rgba(0,0,0,0.15); animation: popoverIn 0.12s ease; }
.member-popover.open { display: block; }
@keyframes popoverIn { from { opacity: 0; transform: translateY(4px) scale(0.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
.popover-avatar-wrap { width: 48px; height: 48px; border-radius: 10px; background: var(--bg4); border: 1px solid var(--border); overflow: hidden; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; }
.popover-avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }
.popover-avatar-wrap .initials { font-size: 16px; font-weight: 700; color: var(--text2); }
.popover-name { font-size: 15px; font-weight: 600; color: var(--text); }
.popover-detail { font-size: 11px; color: var(--text3); margin-top: 3px; line-height: 1.5; }
.popover-detail span { color: var(--text2); font-weight: 500; }
.popover-role-tag { display: inline-block; margin-top: 8px; font-size: 9px; font-weight: 600; padding: 3px 10px; border-radius: 20px; background: rgba(201,168,44,0.15); color: var(--accent3); }

/* Team Info Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 300; display: none; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
.modal-overlay.open { display: flex; }
.modal-box { background: var(--bg2); border-radius: 16px; padding: 28px; width: 460px; max-width: 92vw; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: modalIn 0.2s ease; max-height: 85vh; overflow-y: auto;scrollbar-width:none;-ms-overflow-style:none}
.modal-box::-webkit-scrollbar{display:none}
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
.modal-box h3 { font-size: 18px; font-weight: 600; color: var(--text); margin-bottom: 4px; }
.modal-sub { font-size: 13px; color: var(--text3); margin-bottom: 18px; }
.modal-divider { height: 1px; background: var(--border); margin: 14px 0; }
.info-grid { display: flex; flex-direction: column; gap: 2px; }
.info-row { display: flex; padding: 10px 0; border-bottom: 1px solid var(--border); gap: 12px; }
.info-row:last-child { border-bottom: none; }
.info-label { font-size: 11px; font-weight: 600; color: var(--text3); text-transform: uppercase; letter-spacing: 0.4px; width: 90px; flex-shrink: 0; padding-top: 1px; }
.info-value { font-size: 13px; color: var(--text2); flex: 1; word-break: break-word; line-height: 1.5; }
.info-member-item { display: flex; align-items: center; gap: 10px; padding: 6px 0; }
.info-member-item + .info-member-item { border-top: 1px solid var(--border); }
.info-member-avatar { width: 26px; height: 26px; border-radius: 7px; background: var(--bg4); border: 1px solid var(--border); overflow: hidden; display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: 700; color: var(--text2); flex-shrink: 0; }
.info-member-avatar img { width: 100%; height: 100%; object-fit: cover; }
.info-member-name { font-size: 13px; color: var(--text2); flex: 1; }
.info-lead-tag { font-size: 9px; font-weight: 600; padding: 2px 8px; border-radius: 20px; background: rgba(201,168,44,0.15); color: var(--accent3); white-space: nowrap; }

.btn { display: inline-flex; align-items: center; gap: 6px; border: none; font-family: 'DM Sans', sans-serif; font-weight: 600; cursor: pointer; transition: all 0.15s; border-radius: 8px; }
.btn-ghost { background: transparent; color: var(--text2); border: 1.5px solid var(--border); padding: 8px 16px; font-size: 12px; }
.btn-ghost:hover { background: var(--accent-light); color: var(--accent3); border-color: var(--accent); }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }
.modal-actions .btn { flex: 1; justify-content: center; padding: 10px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.15s; border: none; font-family: 'DM Sans', sans-serif; display: inline-flex; align-items: center; gap: 6px; }
.modal-actions .btn-soft { background: var(--bg3); color: var(--text2); border: 1px solid var(--border); }
.modal-actions .btn-soft:hover { background: var(--border); border-color: var(--text3); }

.empty-state { text-align: center; padding: 40px; color: var(--text3); background: var(--bg2); border-radius: 12px; border: 1px dashed var(--border); }
.empty-state svg { width: 40px; height: 40px; stroke: var(--border2); margin-bottom: 10px; }
.empty-state p { font-size: 14px; }

/* Skeleton styles now live in expert.css, shared across every instructor page */

/* -- RESPONSIVE -- */
.hamburger-btn { display:none; background:rgba(255,255,255,0.06); border:1px solid rgba(201,168,44,0.2); border-radius:9px; width:36px; height:36px; cursor:pointer; align-items:center; justify-content:center; flex-shrink:0; }
.hamburger-btn svg { width:16px; height:16px; stroke:rgba(255,255,200,0.7); fill:none; stroke-width:2; }
.sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:400; }
.sidebar-overlay.open { display:block; }
.sidebar.mobile-open { display:flex; position:fixed; left:0; top:0; z-index:500; height:100vh; }

@media (max-width: 768px) {
  .sidebar { display:none; }
  .hamburger-btn { display:flex; }
  .topbar { padding:0 16px; }
  .page-title { font-size:15px; }
  .content { padding:16px; }
  .team-overview-stat-row { grid-template-columns:repeat(2, 1fr); gap:10px; margin-bottom:18px; }
  .team-role-tabs { width:100%; overflow-x:auto; flex-wrap:nowrap; -webkit-overflow-scrolling:touch; }
  .team-role-tab { padding:8px 14px; font-size:12px; }
  .team-card-modern { padding:14px; }
  .team-avatar-lg { width:38px; height:38px; font-size:13px; }
  .team-name { font-size:14px; }
  .modal-box { padding:22px; width:90vw; }
  .info-label { width:76px; }
  .sk-overlay { padding:16px; }
  .sk-grid { grid-template-columns:repeat(2,1fr) !important; }
  .stat-n { font-size:22px; }
  .stat-chip { padding:12px; }
}

/* -- MOBILE (=480px) -- */
@media (max-width: 480px) {
  /* Layout */
  .topbar { padding:0 10px; }
  .page-title { font-size:14px; }
  .content { padding:10px; }

  /* Stats */
  .team-overview-stat-row { grid-template-columns:repeat(2, 1fr); gap:6px; margin-bottom:12px; }
  .stat-chip { padding:8px; }
  .stat-n { font-size:16px; }
  .stat-l { font-size:11px; }
  .stat-sub { font-size:9px; }

  /* Tabs */
  .team-role-tab { padding:8px 12px; }
  .team-role-tab { padding:6px 10px; font-size:11px; }

  /* -- Team Cards -- */
  .team-card-modern { padding:12px; border-radius:12px; margin-bottom:8px; border-width:1px; box-shadow:0 1px 3px rgba(0,0,0,0.04); }
  .team-card-modern:hover { border-color:var(--border); box-shadow:0 1px 3px rgba(0,0,0,0.04); }
  .team-card-top { gap:10px; margin-bottom:10px; }
  .team-avatar-lg { width:36px; height:36px; font-size:12px; border-radius:10px; }
  .team-name { font-size:13px; }
  .team-name-row { gap:6px; }
  .team-project { font-size:11px; white-space:normal; -webkit-line-clamp:2; display:-webkit-box; -webkit-box-orient:vertical; }
  .role-pill-sm { font-size:8px; padding:2px 8px; }

  /* Members */
  .team-members-section .team-member-row { padding:6px 0; gap:8px; }
  .team-member-row:active { background:var(--bg3); }
  .member-avatar-sm { width:26px; height:26px; font-size:9px; border-radius:6px; }
  .member-name-text { font-size:12px; }
  .member-lead-badge { font-size:8px; padding:1px 6px; }

  /* Card Footer */
  .team-card-footer { padding-top:10px; margin-top:10px; }
  .team-card-footer .btn { font-size:12px; padding:10px 14px; width:100%; justify-content:center; border-radius:10px; }

  /* -- Bottom Sheet Popover -- */
  .member-popover { width:100% !important; max-width:100% !important; left:0 !important; bottom:0 !important; top:auto !important; border-radius:16px 16px 0 0; padding:16px 20px 24px; border-bottom:none; box-shadow:0 -4px 24px rgba(0,0,0,0.15); animation:sheetUp 0.25s ease; }
  .member-popover::before { content:''; display:block; width:36px; height:4px; border-radius:2px; background:var(--border2); margin:-6px auto 14px; }
  @keyframes sheetUp { from { transform:translateY(100%); } to { transform:translateY(0); } }
  .popover-avatar-wrap { width:40px; height:40px; border-radius:10px; }
  .popover-name { font-size:16px; }
  .popover-detail { font-size:12px; margin-top:4px; }
  .popover-role-tag { font-size:10px; padding:4px 12px; }

  /* -- Bottom Sheet Modal -- */
  .modal-overlay { align-items:flex-end; }
  .modal-overlay.open { display:flex; }
  .modal-box { width:100% !important; max-width:100% !important; max-height:80vh; border-radius:16px 16px 0 0; padding:20px 18px 24px; animation:sheetUp 0.25s ease; margin-top:auto; }
  .modal-box::before { content:''; display:block; width:36px; height:4px; border-radius:2px; background:var(--border2); margin:-12px auto 14px; }
  .modal-box h3 { font-size:16px; }
  .modal-sub { font-size:12px; margin-bottom:14px; }
  .info-row { flex-direction:column; gap:1px; padding:8px 0; }
  .info-label { width:100%; font-size:10px; }
  .info-value { font-size:12px; }
  .info-member-item { padding:5px 0; }
  .info-member-avatar { width:24px; height:24px; }
  .info-member-name { font-size:12px; }
  .info-lead-tag { font-size:8px; padding:1px 6px; }
  .modal-actions { flex-direction:column; gap:8px; margin-top:14px; }
  .modal-actions .btn { font-size:13px; padding:12px; border-radius:10px; }
  .modal-divider { margin:10px 0; }

  /* Other */
  .empty-state { padding:20px 14px; }
  .empty-state p { font-size:13px; }
  .sk-overlay { padding:10px; }
}
</style>
@include('partials.skeleton-head', ['skReveal' => 'block'])
</head>
<body>
@include('instructor.partials.sidebar', ['currentPage' => 'teams', 'totalTeams' => $totalTeams])

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Team Overview</div>
    <div class="topbar-actions">
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="content" style="padding-top:0;">
      <div class="sk-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;">
        <div class="sk" style="height:78px;border-radius:14px;"></div>
        <div class="sk" style="height:78px;border-radius:14px;"></div>
        <div class="sk" style="height:78px;border-radius:14px;"></div>
        <div class="sk" style="height:78px;border-radius:14px;"></div>
      </div>
      <div class="sk" style="height:32px;width:120px;border-radius:8px;margin-bottom:20px;"></div>
      <div class="sk" style="height:120px;border-radius:14px;margin-bottom:16px;"></div>
      <div class="sk" style="height:120px;border-radius:14px;margin-bottom:16px;"></div>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
    <div class="content">
      <div class="team-overview-stat-row">
        @php
          $statColors = ['all' => 'var(--blue)', 'adviser' => 'var(--accent)', 'consultant' => '#3b82f6', 'panel' => '#9333ea'];
          $statLabels = ['all' => 'All', 'adviser' => 'Adviser', 'consultant' => 'Consultant', 'panel' => 'Panel'];
        @endphp
        @foreach($tabs as $tab)
          @php
            if ($tab === 'all') {
              $count = $approvedAssignments->count();
            } elseif ($tab === 'panel') {
              $count = ($grouped->get('grammarian', collect())->count() + $grouped->get('capstone_adviser', collect())->count());
            } else {
              $count = $grouped->get($tab, collect())->count();
            }
          @endphp
          <div class="stat-chip">
            <div class="stat-n" style="color:{{ $statColors[$tab] ?? 'var(--text)' }}">{{ $count }}</div>
            <div class="stat-l">{{ $statLabels[$tab] ?? ucfirst($tab) }}</div>
            <div class="stat-sub">Assigned teams</div>
          </div>
        @endforeach
      </div>

      <div class="team-role-tabs">
        @foreach($tabs as $index => $tab)
          @php $label = $tab === 'panel' ? 'Panel' : ($tab === 'all' ? 'All' : Str::title($tab)); @endphp
          <button class="team-role-tab {{ $index === 0 ? 'active' : '' }}" onclick="switchRoleTab('{{ $tab }}', this)">{{ $label }}</button>
        @endforeach
      </div>

      @foreach($tabs as $index => $tab)
        @php
          if ($tab === 'all') {
            $teams = $approvedAssignments;
          } elseif ($tab === 'panel') {
            $teams = $grouped->get('grammarian', collect())->merge($grouped->get('capstone_adviser', collect()));
          } else {
            $teams = $grouped->get($tab, collect());
          }
          $roleColors = [
            'adviser' => 'var(--accent)',
            'consultant' => '#3b82f6',
            'grammarian' => '#9333ea',
            'capstone_adviser' => '#b45309',
          ];
          $avatarColor = $roleColors[$tab] ?? 'var(--accent)';
        @endphp
        <div id="tab-{{ $tab }}" class="team-role-panel {{ $index === 0 ? 'active' : '' }}">

          @forelse($teams as $assignment)
            @php
              $team = $assignment->team;
              $leader = $team?->leader;
              $members = $team?->members ?? collect();
              $initials = $team ? strtoupper(substr($team->team_name ?? 'T', 0, 2)) : 'T';
              $roleName = $assignment->engaged_role_name ?? '';
            @endphp
            <div class="team-card-modern">
              <div class="team-card-top">
                <div class="team-avatar-lg" style="background:{{ $avatarColor }}">{{ $initials }}</div>
                <div class="team-meta">
                  <div class="team-name-row">
                    <span class="team-name">{{ $team?->team_name ?? 'Unknown Team' }}</span>
                    <span class="role-pill-sm role-pill-{{ $roleName }}">{{ Str::title(str_replace('_', ' ', $roleName)) }}</span>
                  </div>
                  <div class="team-project">{{ $team?->project_title ?? 'No project title' }}</div>
                </div>
              </div>

              <div class="team-members-section">
                @foreach($members as $member)
                  <div class="team-member-row" onclick="openPopover(event, this, {{ $member->student_id }})">
                    <div class="member-avatar-sm">
                      @if($member->avatarUrl())
                        <img src="{{ $member->avatarUrl() }}" alt="">
                      @else
                        {{ $member->avatarInitials() ?: '?' }}
                      @endif
                    </div>
                    <div class="member-name-text">{{ $member->displayFirstName() }} {{ $member->l_name ?? '' }}</div>
                    @if($member->student_id === $team?->leader_id)
                      <div class="member-lead-badge">Team Lead</div>
                    @endif
                  </div>
                @endforeach
                @if($members->isEmpty())
                  <div class="team-member-row" style="cursor:default;">
                    <div class="member-name-text" style="color:var(--text3);font-style:italic;">No members loaded</div>
                  </div>
                @endif
              </div>

              <div class="team-card-footer">
                <button class="btn btn-ghost" onclick="openTeamInfoModal({{ $assignment->assignment_id }})">
                  <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                  View Details
                </button>
              </div>
            </div>
          @empty
            <div class="empty-state">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              <p>No teams assigned as {{ $tab === 'panel' ? 'panel' : ($tab === 'all' ? 'any role' : strtolower($tab)) }}.</p>
            </div>
          @endforelse
        </div>
      @endforeach
    </div>
  </div>
</div>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

@include('instructor.partials.expert-profile')

<div class="popover-overlay" id="popover-overlay" onclick="closePopover()"></div>

<div class="member-popover" id="member-popover">
  <div id="popover-content"></div>
</div>

<div class="modal-overlay" id="team-info-modal">
  <div class="modal-box" style="width:480px;">
    <h3>Team Details</h3>
    <div class="modal-sub" id="ti-sub"></div>
    <div class="modal-divider"></div>
    <div class="info-grid" id="ti-content"></div>
    <div class="modal-actions">
      <button class="btn btn-soft" onclick="closeModal('team-info-modal')">Close</button>
    </div>
  </div>
</div>

<script>
const assignmentData = {{ Js::from($grouped->flatten()->mapWithKeys(function ($a) {
    $leader = $a->team?->leader;
    $members = $a->team?->members ?? collect();
    return [$a->assignment_id => [
        'team' => $a->team?->team_name ?? 'Unknown',
        'project' => $a->team?->project_title ?? 'No project title',
        'role' => ucfirst($a->role->first()?->role_name ?? ''),
        'leader' => $leader ? trim(($leader->f_name ?? '') . ' ' . ($leader->l_name ?? '')) : 'N/A',
        'leader_email' => $leader?->email ?? '',
        'member_count' => $members->count(),
        'members' => $members->map(fn($m) => [
            'id' => $m->student_id,
            'name' => trim(($m->f_name ?? '') . ' ' . ($m->l_name ?? '')),
            'email' => $m->email ?? '',
            'is_leader' => $m->student_id === $a->team?->leader_id,
            'avatar_url' => $m->avatarUrl(),
            'initials' => $m->avatarInitials(),
        ])->values()->toArray(),
    ]];
})->toArray()) }};

const memberData = {{ Js::from($grouped->flatten()->flatMap(fn($a) => $a->team?->members ?? collect())->unique('student_id')->mapWithKeys(fn($m) => [$m->student_id => [
    'name' => trim(($m->f_name ?? '') . ' ' . ($m->l_name ?? '')),
    'email' => $m->email ?? '',
    'student_number' => $m->student_number ?? 'N/A',
    'course' => $m->course ?? 'N/A',
    'initials' => $m->avatarInitials(),
    'avatar_url' => $m->avatarUrl(),
]])->toArray()) }};

document.addEventListener('DOMContentLoaded', function () {
  var sk = document.getElementById('skeleton-content');
  var rc = document.getElementById('real-content');
  if (sk && rc) {
    requestAnimationFrame(function () {
      setTimeout(function () {
        sk.style.transition = 'opacity 0.3s ease';
        sk.style.opacity = '0';
        rc.style.display = 'flex';
        rc.style.transition = 'opacity 0.35s ease';
        setTimeout(function () {
          sk.remove();
          rc.style.transition = '';
        }, 350);
      }, 600);
    });
  }
});

function switchRoleTab(tab, btn) {
  document.querySelectorAll('.team-role-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.team-role-tab').forEach(t => t.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  if (btn) btn.classList.add('active');
}

function openPopover(event, el, memberId) {
  const d = memberData[memberId];
  if (!d) return;

  const avatarHtml = d.avatar_url
    ? '<img src="' + d.avatar_url + '" alt="">'
    : '<span class="initials">' + (d.initials || '?') + '</span>';

  document.getElementById('popover-content').innerHTML =
    '<div class="popover-avatar-wrap">' + avatarHtml + '</div>' +
    '<div class="popover-name">' + d.name + '</div>' +
    '<div class="popover-detail">Email: <span>' + d.email + '</span></div>' +
    '<div class="popover-detail">Student #: <span>' + d.student_number + '</span></div>' +
    '<div class="popover-detail">Course: <span>' + d.course + '</span></div>' +
    '<div class="popover-role-tag">' + (el.querySelector('.member-lead-badge') ? 'Team Lead' : 'Member') + '</div>';

  const popover = document.getElementById('member-popover');
  popover.classList.add('open');
  document.getElementById('popover-overlay').classList.add('open');

  // Desktop: position near click; Mobile =480px: CSS handles bottom-sheet
  if (window.innerWidth > 480) {
    const clickX = event.clientX;
    const clickY = event.clientY;

    let top = clickY - 30;
    let left = clickX + 12;

    if (left + 260 > window.innerWidth - 10) {
      left = window.innerWidth - 270;
    }
    if (left < 10) left = 10;
    if (top + 250 > window.innerHeight - 10) {
      top = window.innerHeight - 260;
    }
    if (top < 10) top = 10;

    popover.style.left = left + 'px';
    popover.style.top = top + 'px';
  } else {
    // Reset inline styles so CSS bottom-sheet rules apply
    popover.style.left = '';
    popover.style.top = '';
  }
}

function closePopover() {
  document.getElementById('member-popover').classList.remove('open');
  document.getElementById('popover-overlay').classList.remove('open');
}

function openTeamInfoModal(id) {
  const d = assignmentData[id];
  if (!d) return;

  document.getElementById('ti-sub').textContent = d.team + ' · ' + d.role;

  let memberHtml = '';
  d.members.forEach(function(m) {
    const mAvatar = m.avatar_url
      ? '<img src="' + m.avatar_url + '" alt="">'
      : (m.initials || '?');
    memberHtml +=
      '<div class="info-member-item">' +
      '<div class="info-member-avatar">' + mAvatar + '</div>' +
      '<div class="info-member-name">' + m.name + (m.email ? '<br><span style="font-size:11px;color:var(--text3);">' + m.email + '</span>' : '') + '</div>' +
      (m.is_leader ? '<div class="info-lead-tag">Team Lead</div>' : '') +
      '</div>';
  });

  document.getElementById('ti-content').innerHTML =
    '<div class="info-row"><div class="info-label">Team</div><div class="info-value">' + d.team + '</div></div>' +
    '<div class="info-row"><div class="info-label">Project</div><div class="info-value">' + d.project + '</div></div>' +
    '<div class="info-row"><div class="info-label">Role</div><div class="info-value"><span class="role-pill-sm role-pill-' + d.role.toLowerCase() + '">' + d.role + '</span></div></div>' +
    '<div class="info-row"><div class="info-label">Leader</div><div class="info-value">' + d.leader + (d.leader_email ? '<br><span style="font-size:11px;color:var(--text3);">' + d.leader_email + '</span>' : '') + '</div></div>' +
    '<div class="info-row"><div class="info-label">Members (' + d.member_count + ')</div><div class="info-value">' + (memberHtml || '<span style="color:var(--text3);">No members</span>') + '</div></div>';

  openModal('team-info-modal');
}

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

/* Mobile sidebar toggle */
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');

function toggleSidebar() {
  if (!sidebarEl) return;
  var isOpen = sidebarEl.classList.contains('mobile-open');
  if (isOpen) {
    closeSidebar();
  } else {
    openSidebar();
  }
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
</script>
</body>
</html>
