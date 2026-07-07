<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Team Assignments - CapsTRACK</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/expert.css') }}" />
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
.tab-bar { display: flex; gap: 4px; margin-bottom: 20px; background: var(--bg2); border-radius: 12px; padding: 4px; border: 1px solid var(--border); width: fit-content; }
.tab-btn { padding: 8px 20px; border-radius: 8px; border: none; background: transparent; color: var(--text3); font-size: 13px; font-weight: 500; cursor: pointer; font-family: 'DM Sans', sans-serif; transition: 0.15s; }
.tab-btn.active { background: var(--accent); color: white; }
.tab-btn:hover:not(.active) { color: var(--text); }
.tab-content { display: none; }
.tab-content.active { display: block; }
.stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }
.stat-card { background: var(--bg2); border: 1px solid var(--border); border-radius: 14px; padding: 18px 20px; }
.stat-card-n { font-size: 28px; font-weight: 700; color: var(--text); }
.stat-card-l { font-size: 12px; color: var(--text3); margin-top: 2px; }
.stat-card-sub { font-size: 11px; color: var(--text3); margin-top: 4px; }
.req-card { background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; padding: 12px 16px; margin-bottom: 8px; transition: 0.15s; display: flex; align-items: center; gap: 12px; }
.req-card:hover { border-color: var(--accent); }
.req-card-left { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
.req-team-icon { width: 34px; height: 34px; border-radius: 8px; background: var(--accent); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; flex-shrink: 0; }
.req-info { flex: 1; min-width: 0; line-height: 1.3; }
.req-team-name { font-size: 13px; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.req-project { font-size: 11px; color: var(--text3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.req-leader { font-size: 10px; color: var(--text3); margin-top: 1px; }
.req-card-right { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.req-role-badge { font-size: 9px; font-weight: 600; padding: 2px 8px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.3px; background: var(--accent-light); color: var(--accent3); white-space: nowrap; }
.req-actions { display: flex; gap: 4px; flex-shrink: 0; }

/* Icon buttons */
.btn-icon { width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2); color: var(--text3); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.15s; padding: 0; }
.btn-icon:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-light); }
.btn-icon-green:hover { border-color: var(--green); color: var(--green); background: var(--green-bg); }
.btn-icon-red:hover { border-color: var(--red); color: var(--red); background: var(--red-bg); }
.status-dot-sm { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.btn { display: inline-flex; align-items: center; gap: 6px; border: none; font-family: 'DM Sans', sans-serif; font-weight: 600; cursor: pointer; transition: all 0.15s; border-radius: 8px; }
.btn:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-sm { font-size: 12px; padding: 7px 14px; }
.btn-soft { background: var(--bg3); color: var(--text2); border: 1px solid var(--border); }
.btn-soft:hover { background: var(--border); border-color: var(--text3); }
.btn-soft:active { background: var(--bg2); }
.btn-solid-green { background: var(--green); color: white; }
.btn-solid-green:hover { filter: brightness(1.08); }
.btn-solid-green:active { filter: brightness(0.95); }
.btn-solid-red { background: var(--red); color: white; }
.btn-solid-red:hover { filter: brightness(1.08); }
.btn-solid-red:active { filter: brightness(0.95); }
.btn-soft-red { background: var(--red-bg); color: var(--red); border: 1px solid var(--red); }
.btn-soft-red:hover { filter: brightness(1.08); }
.btn-soft-red:active { filter: brightness(0.95); }

.empty-state { text-align: center; padding: 40px; color: var(--text3); background: var(--bg2); border-radius: 12px; border: 1px dashed var(--border); }
.empty-state svg { width: 40px; height: 40px; stroke: var(--border2); margin-bottom: 10px; }
.empty-state p { font-size: 14px; }

/* Hamburger (hidden on desktop) */
.hamburger-btn{display:none;align-items:center;justify-content:center;width:36px;height:36px;border-radius:9px;background:rgba(255,255,255,0.06);border:1px solid rgba(201,168,44,0.2);cursor:pointer;color:var(--navy-text);flex-shrink:0;transition:background 0.15s}
.hamburger-btn:hover{background:rgba(201,168,44,0.12)}
.hamburger-btn svg{width:18px;height:18px}

/* Sidebar overlay (mobile) */
.sidebar-overlay{display:none;position:fixed;inset:0;z-index:999;background:rgba(0,0,0,0.5)}

/* ── Responsive: Tablet Portrait (≤768px) ── */
@media(max-width:768px){
  .hamburger-btn{display:inline-flex;order:-1}
  .sidebar{position:fixed!important;z-index:1000;transform:translateX(-100%);transition:transform 0.25s cubic-bezier(0.16,1,0.3,1)}
  .sidebar-open .sidebar{transform:translateX(0)}
  .sidebar-open .sidebar-overlay{display:block}
  body.sidebar-open{overflow:hidden}
  .topbar{padding:0 12px!important}
  .topbar .page-title{font-size:15px!important}
  .content{padding:14px!important}

  /* Stat cards: 2 columns */
  .stat-row{grid-template-columns:repeat(2,1fr)!important;gap:10px!important}
  .stat-card{padding:14px 16px!important}
  .stat-card-n{font-size:22px!important}

  /* Tab bar: wrap */
  .tab-bar{width:100%!important;overflow-x:auto!important;flex-wrap:nowrap!important;gap:2px!important}
  .tab-btn{padding:7px 14px!important;font-size:12px!important;white-space:nowrap!important}

  /* Request cards: compact row */
  .req-card{padding:10px 12px!important;gap:8px!important}
  .req-team-icon{width:30px!important;height:30px!important;font-size:11px!important}
  .req-team-name{font-size:12px!important}
  .req-project{font-size:10px!important}
  .req-leader{font-size:9px!important}
  .req-role-badge{font-size:8px!important;padding:2px 6px!important}
  .btn-icon{width:26px!important;height:26px!important}
  .btn-icon svg{width:12px!important;height:12px!important}

  /* Modals: full-width */
  .modal-box{width:94%!important;padding:20px!important;max-width:94%!important}
  .modal-box h3{font-size:15px!important}
  .modal-box .modal-sub{font-size:12px!important}
  .modal-actions{gap:8px!important}
  .modal-actions .btn{padding:8px!important;font-size:12px!important}
  .confirm-box .confirm-icon{width:40px!important;height:40px!important}
  .confirm-box .confirm-icon svg{width:20px!important;height:20px!important}
  .confirm-box h3{font-size:16px!important}
  .confirm-desc{font-size:12px!important}
  .info-box .info-row{flex-direction:column!important;gap:2px!important;padding:6px 0!important}
  .info-box .info-label{width:auto!important;font-size:10px!important}
  .info-box .info-value{font-size:12px!important}
}

/* ── Responsive: Phone (≤480px) ── */
@media(max-width:480px){
  .content{padding:10px!important}
  .stat-row{grid-template-columns:1fr!important;gap:8px!important}
  .stat-card{padding:12px 14px!important}
  .stat-card-n{font-size:20px!important}
  .stat-card-l{font-size:11px!important}

  .req-card{padding:8px 10px!important;gap:6px!important}
  .req-team-icon{width:26px!important;height:26px!important;font-size:10px!important}
  .req-team-name{font-size:11px!important}
  .req-project{font-size:9px!important}
  .req-leader{font-size:8px!important}
  .req-role-badge{font-size:7px!important;padding:1px 5px!important}
  .btn-icon{width:24px!important;height:24px!important}
  .btn-icon svg{width:11px!important;height:11px!important}

  .tab-btn{padding:6px 10px!important;font-size:11px!important}
  .tab-bar{gap:2px!important}

  .modal-box{padding:16px!important}
  .modal-box h3{font-size:14px!important}
  .modal-actions{flex-direction:column!important}
  .modal-actions .btn{width:100%!important;justify-content:center!important;padding:10px!important}
  .modal-box textarea{font-size:12px!important;min-height:60px!important}
  .confirm-box .confirm-icon{width:36px!important;height:36px!important}
  .confirm-box .confirm-icon svg{width:18px!important;height:18px!important}

  .msg-toast{left:10px!important;right:10px!important;text-align:center;justify-content:center;font-size:12px!important;padding:12px 16px!important}
}
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 300; display: none; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
.modal-overlay.open { display: flex; }
.modal-box { background: var(--bg2); border-radius: 16px; padding: 28px; width: 440px; max-width: 90vw; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: modalIn 0.2s ease; max-height: 85vh; overflow-y: auto; }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
.modal-box h3 { font-size: 17px; font-weight: 600; color: var(--text); margin-bottom: 4px; }
.modal-box .modal-sub { font-size: 13px; color: var(--text3); margin-bottom: 16px; }
.modal-box .modal-divider { height: 1px; background: var(--border); margin: 14px 0; }
.modal-box textarea { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid var(--border); background: var(--bg3); color: var(--text); font-size: 13px; font-family: 'DM Sans', sans-serif; outline: none; resize: vertical; min-height: 80px; box-sizing: border-box; }
.modal-box textarea:focus { border-color: var(--red); }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }
.modal-actions .btn { flex: 1; justify-content: center; padding: 10px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.15s; border: none; font-family: 'DM Sans', sans-serif; display: inline-flex; align-items: center; gap: 6px; }
.modal-actions .btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* Confirmation modal */
.confirm-box { width: 380px; text-align: center; }
.confirm-box .confirm-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
.confirm-box .confirm-icon svg { width: 24px; height: 24px; stroke: white; fill: none; stroke-width: 2; }
.confirm-box h3 { font-size: 18px; }
.confirm-box .confirm-desc { font-size: 13px; color: var(--text3); margin: 6px 0 4px; line-height: 1.5; }
.confirm-box .confirm-detail { font-size: 12px; color: var(--text2); background: var(--bg3); border-radius: 8px; padding: 12px 16px; margin-top: 12px; text-align: left; line-height: 1.6; }
.confirm-box .confirm-detail strong { color: var(--text); }
.confirm-box .confirm-detail .slot-warn { display: block; margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--border); font-size: 11px; color: var(--amber); }

/* Team info modal */
.info-box { width: 480px; }
.info-box .info-row { display: flex; padding: 8px 0; border-bottom: 1px solid var(--border); }
.info-box .info-row:last-child { border-bottom: none; }
.info-box .info-label { font-size: 11px; font-weight: 600; color: var(--text3); text-transform: uppercase; letter-spacing: 0.3px; width: 100px; flex-shrink: 0; padding-top: 2px; }
.info-box .info-value { font-size: 13px; color: var(--text); flex: 1; word-break: break-word; }
.info-box .info-value .member-tag { display: block; font-size: 12px; background: var(--bg3); border: 1px solid var(--border); border-radius: 6px; padding: 6px 10px; margin-bottom: 4px; }
.info-box .info-value .proposal-link { display: inline-flex; align-items: center; gap: 5px; color: var(--accent); font-weight: 500; text-decoration: none; padding: 4px 10px; border-radius: 6px; background: var(--accent-light); }
.info-box .info-value .proposal-link:hover { background: var(--accent); color: white; }
.info-box .info-value .msg-text { font-size: 12px; color: var(--text2); line-height: 1.5; background: var(--bg3); border-radius: 6px; padding: 8px 10px; margin-top: 2px; }

.msg-toast { position: fixed; bottom: 30px; right: 30px; background: #1e2128; color: white; padding: 14px 22px; border-radius: 12px; font-size: 13px; font-weight: 500; z-index: 500; display: none; align-items: center; gap: 10px; box-shadow: 0 8px 30px rgba(0,0,0,0.3); animation: slideUp 0.3s ease; }
.msg-toast.success { border-left: 4px solid var(--green); }
.msg-toast.error { border-left: 4px solid var(--red); }
.msg-toast.show { display: flex; }
@keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>
</head>
<body>
@include('instructor.partials.sidebar', ['currentPage' => 'assignments'])
<div class="sidebar-overlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <div class="page-title">Team Assignments</div>
    <div class="topbar-actions">
      <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="stat-row">
      <div class="stat-card">
        <div class="stat-card-n">{{ $pendingAssignments->count() }}</div>
        <div class="stat-card-l">Pending Requests</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-n">{{ $approvedAssignments->count() }}</div>
        <div class="stat-card-l">Approved</div>
        <div class="stat-card-sub">{{ $currentSlots }}/{{ $maxSlots }} slots used</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-n">{{ $rejectedAssignments->count() }}</div>
        <div class="stat-card-l">Rejected</div>
        <div class="stat-card-sub">Recent history</div>
      </div>
    </div>

    <div class="tab-bar">
      <button class="tab-btn active" onclick="switchTab('pending', this)">Pending ({{ $pendingAssignments->count() }})</button>
      <button class="tab-btn" onclick="switchTab('approved', this)">Approved ({{ $approvedAssignments->count() }})</button>
      <button class="tab-btn" onclick="switchTab('rejected', this)">Rejected ({{ $rejectedAssignments->count() }})</button>
    </div>

    <div id="tab-pending" class="tab-content active">
      @forelse($pendingAssignments as $assignment)
        <div class="req-card">
          <div class="req-card-left">
            <div class="req-team-icon">{{ substr($assignment->team?->team_name ?? 'T', 0, 2) }}</div>
            <div class="req-info">
              <div class="req-team-name">{{ $assignment->team?->team_name ?? 'Unknown Team' }}</div>
              <div class="req-project">{{ $assignment->team?->project_title ?? 'No project title' }}</div>
              @if($assignment->team && $assignment->team->leader)
                <div class="req-leader">{{ $assignment->team->leader->displayFirstName() ?? $assignment->team->leader->f_name ?? '' }}</div>
              @endif
            </div>
          </div>
          <div class="req-card-right">
            <span class="req-role-badge">{{ ucfirst($assignment->role?->role_name ?? '') }}</span>
            <div class="req-actions">
              <button class="btn btn-icon" onclick="openTeamModal({{ $assignment->assignment_id }})" title="View Details">
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
              </button>
              <button class="btn btn-icon btn-icon-green" onclick="openApproveModal({{ $assignment->assignment_id }}, '{{ $assignment->team?->team_name ?? 'Unknown' }}', '{{ ucfirst($assignment->role?->role_name ?? '') }}', '{{ $assignment->team?->leader?->f_name ?? '' }}')" title="Approve">
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              </button>
              <button class="btn btn-icon btn-icon-red" onclick="openRejectModal({{ $assignment->assignment_id }}, '{{ $assignment->team?->team_name ?? 'Unknown' }}', '{{ ucfirst($assignment->role?->role_name ?? '') }}')" title="Reject">
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
          </div>
        </div>
      @empty
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          <p>No pending team requests at the moment.</p>
        </div>
      @endforelse
    </div>

    <div id="tab-approved" class="tab-content">
      @forelse($approvedAssignments as $assignment)
        <div class="req-card">
          <div class="req-card-left">
            <div class="req-team-icon" style="background: var(--green);">{{ substr($assignment->team?->team_name ?? 'T', 0, 2) }}</div>
            <div class="req-info">
              <div class="req-team-name">{{ $assignment->team?->team_name ?? 'Unknown Team' }}</div>
              <div class="req-project">{{ $assignment->team?->project_title ?? 'No project title' }}</div>
              <div class="req-leader">Assigned {{ $assignment->created_at ? $assignment->created_at->diffForHumans() : '' }}</div>
            </div>
          </div>
          <div class="req-card-right">
            <span class="req-role-badge">{{ ucfirst($assignment->role?->role_name ?? '') }}</span>
            <span class="status-dot-sm" style="background:var(--green);" title="Approved"></span>
            <div class="req-actions">
              <button class="btn btn-icon" onclick="openTeamModal({{ $assignment->assignment_id }})" title="View Details">
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
              </button>
              <button class="btn btn-icon btn-icon-red" onclick="openInstructorRemoveModal({{ $assignment->assignment_id }}, '{{ $assignment->team?->team_name ?? 'Unknown' }}', '{{ ucfirst($assignment->role?->role_name ?? '') }}')" title="Remove">
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
          </div>
        </div>
      @empty
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg>
          <p>No approved team assignments yet.</p>
        </div>
      @endforelse
    </div>

    <div id="tab-rejected" class="tab-content">
      @forelse($rejectedAssignments as $assignment)
        <div class="req-card">
          <div class="req-card-left">
            <div class="req-team-icon" style="background: var(--red);">{{ substr($assignment->team?->team_name ?? 'T', 0, 2) }}</div>
            <div class="req-info">
              <div class="req-team-name">{{ $assignment->team?->team_name ?? 'Unknown Team' }}</div>
              <div class="req-project">{{ $assignment->team?->project_title ?? 'No project title' }}</div>
              <div class="req-leader">Rejected {{ $assignment->updated_at ? $assignment->updated_at->diffForHumans() : '' }}</div>
            </div>
          </div>
          <div class="req-card-right">
            <span class="req-role-badge">{{ ucfirst($assignment->role?->role_name ?? '') }}</span>
            <span class="status-dot-sm" style="background:var(--red);" title="Rejected"></span>
            <div class="req-actions">
              <button class="btn btn-icon" onclick="openTeamModal({{ $assignment->assignment_id }})" title="View Details">
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
              </button>
              <button class="btn btn-icon btn-icon-red" onclick="openInstructorRemoveModal({{ $assignment->assignment_id }}, '{{ $assignment->team?->team_name ?? 'Unknown' }}', '{{ ucfirst($assignment->role?->role_name ?? '') }}')" title="Remove">
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
          </div>
        </div>
      @empty
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
          <p>No rejected requests.</p>
        </div>
      @endforelse
    </div>
  </div>
</div>

<!-- Team Info Modal -->
<div class="modal-overlay" id="team-modal">
  <div class="modal-box info-box">
    <h3>Team Details</h3>
    <div class="modal-sub" id="team-modal-sub"></div>

    <div class="info-row">
      <div class="info-label">Status</div>
      <div class="info-value" id="tm-status"></div>
    </div>
    <div class="info-row">
      <div class="info-label">Team</div>
      <div class="info-value" id="tm-team"></div>
    </div>
    <div class="info-row">
      <div class="info-label">Project</div>
      <div class="info-value" id="tm-project"></div>
    </div>
    <div class="info-row">
      <div class="info-label">Role</div>
      <div class="info-value" id="tm-role"></div>
    </div>
    <div class="info-row">
      <div class="info-label">Leader</div>
      <div class="info-value" id="tm-leader"></div>
    </div>
    <div class="info-row">
      <div class="info-label">Members</div>
      <div class="info-value" id="tm-members"></div>
    </div>
    <div class="info-row">
      <div class="info-label">Message</div>
      <div class="info-value" id="tm-message"></div>
    </div>
    <div class="info-row">
      <div class="info-label">Proposal</div>
      <div class="info-value" id="tm-proposal"></div>
    </div>
    <div class="info-row" id="tm-reason-row" style="display:none;">
      <div class="info-label">Reason</div>
      <div class="info-value" id="tm-reason" style="color:var(--red);"></div>
    </div>
    <div class="info-row">
      <div class="info-label">Sent</div>
      <div class="info-value" id="tm-date"></div>
    </div>

    <div class="modal-actions">
      <button class="btn btn-soft" onclick="closeModal('team-modal')">Close</button>
    </div>
  </div>
</div>

<!-- Approve Confirmation Modal -->
<div class="modal-overlay" id="approve-modal">
  <div class="modal-box confirm-box">
    <div class="confirm-icon" style="background: var(--green-bg);">
      <svg viewBox="0 0 24 24" stroke="var(--green)"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <h3>Approve Team Request?</h3>
    <div class="confirm-desc">This team will be assigned to you for the selected role.</div>
    <div class="confirm-detail" id="approve-detail"></div>
    <div class="modal-actions" style="margin-top: 18px;">
      <button class="btn btn-soft" onclick="closeModal('approve-modal')">Cancel</button>
      <button class="btn btn-solid-green" id="btn-confirm-approve" onclick="confirmApprove()" style="flex:1;justify-content:center;padding:10px;border-radius:8px;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;">
        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Yes, Approve
      </button>
    </div>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="reject-modal">
  <div class="modal-box">
    <h3>Reject Team Request</h3>
    <div class="modal-sub" id="reject-modal-sub"></div>

    <div class="modal-divider"></div>

    <label style="font-size: 12px; font-weight: 600; color: var(--text2); display: block; margin-bottom: 6px;">
      Reason for Rejection <span style="color: var(--text3); font-weight: 400;">(optional)</span>
    </label>
    <textarea id="reject-reason" placeholder="The team will be notified of your reason..."></textarea>

    <div class="modal-actions">
      <button class="btn btn-soft" onclick="closeModal('reject-modal')">Cancel</button>
      <button class="btn btn-solid-red" id="btn-confirm-reject" onclick="confirmReject()" style="flex:1;justify-content:center;padding:10px;border-radius:8px;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;">
        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        Reject Request
      </button>
    </div>
  </div>
</div>

<!-- Instructor Remove Confirmation Modal -->
<div class="modal-overlay" id="instructor-remove-modal">
  <div class="modal-box confirm-box">
    <div class="confirm-icon" style="background: var(--red-bg);">
      <svg viewBox="0 0 24 24" stroke="var(--red)"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
    </div>
    <h3>Remove Assignment?</h3>
    <div class="confirm-desc">This will permanently remove this assignment and free up a slot.</div>
    <div class="confirm-detail" id="instructor-remove-detail"></div>
    <div class="modal-actions" style="margin-top: 18px;">
      <button class="btn btn-soft" onclick="closeModal('instructor-remove-modal')">Cancel</button>
      <button class="btn btn-solid-red" id="btn-confirm-instructor-remove" onclick="confirmInstructorRemove()" style="flex:1;justify-content:center;padding:10px;border-radius:8px;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;">
        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        Yes, Remove
      </button>
    </div>
  </div>
</div>

<div class="msg-toast" id="msg-toast"></div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function switchTab(tab, btn) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
}

const assignmentData = {{ Js::from($assignmentData) }};

function openTeamModal(id) {
  const d = assignmentData[id];
  if (!d) return;
  document.getElementById('team-modal-sub').textContent = 'Review the team\'s information and request details.';
  const statusColors = { approved: 'var(--green)', pending: 'var(--amber)', rejected: 'var(--red)' };
  document.getElementById('tm-status').innerHTML = '<span style="color:' + (statusColors[d.status] || 'var(--text3)') + ';font-weight:600;text-transform:capitalize;">' + d.status + '</span>';
  document.getElementById('tm-team').textContent = d.team;
  document.getElementById('tm-project').textContent = d.project;
  document.getElementById('tm-role').innerHTML = '<span class="req-role-badge">' + d.role + '</span>';
  document.getElementById('tm-leader').innerHTML = d.leader + (d.leader_email ? '<br><span style="font-size:11px;color:var(--text3);">' + d.leader_email + '</span>' : '');
  document.getElementById('tm-members').innerHTML = d.members.length
    ? d.members.map(m => '<span class="member-tag">' + m.name + (m.email ? '<br><span style="font-size:10px;color:var(--text3);">' + m.email + '</span>' : '') + '</span>').join('')
    : '<span style="color:var(--text3);">No members loaded</span>';
  document.getElementById('tm-message').innerHTML = '<div class="msg-text">' + d.message + '</div>';
  document.getElementById('tm-proposal').innerHTML = d.proposal
    ? '<a href="' + d.proposal + '" target="_blank" class="proposal-link"><svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="12" y2="12"/><line x1="15" y1="15" x2="12" y2="12"/></svg> View Proposal</a>'
    : '<span style="color:var(--text3);">No file attached</span>';
  const reasonRow = document.getElementById('tm-reason-row');
  const reasonEl = document.getElementById('tm-reason');
  if (d.rejection_reason) {
    reasonRow.style.display = 'flex';
    reasonEl.textContent = d.rejection_reason;
  } else {
    reasonRow.style.display = 'none';
  }
  document.getElementById('tm-date').textContent = d.date;
  openModal('team-modal');
}

let currentApproveId = null;
function openApproveModal(id, teamName, roleName, leaderName) {
  currentApproveId = id;
  document.getElementById('approve-detail').innerHTML =
    '<strong>' + teamName + '</strong> — ' + roleName + '<br>Leader: ' + (leaderName || 'N/A')
    + '<span class="slot-warn">This will use 1 of your ' + {{ $currentSlots }} + '/' + {{ $maxSlots }} + ' available slots.</span>';
  document.getElementById('btn-confirm-approve').disabled = false;
  document.getElementById('btn-confirm-approve').innerHTML =
    '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Yes, Approve';
  openModal('approve-modal');
}

let currentRejectId = null;
function openRejectModal(id, teamName, roleName) {
  currentRejectId = id;
  document.getElementById('reject-modal-sub').textContent = teamName + ' — ' + roleName;
  document.getElementById('reject-reason').value = '';
  document.getElementById('btn-confirm-reject').disabled = false;
  document.getElementById('btn-confirm-reject').innerHTML =
    '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Reject Request';
  openModal('reject-modal');
}

function confirmApprove() {
  if (!currentApproveId) return;
  const btn = document.getElementById('btn-confirm-approve');
  btn.disabled = true;
  btn.innerHTML = 'Approving...';

  fetch('{{ url("instructor/assignments") }}/' + currentApproveId + '/approve', {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Accept': 'application/json',
    },
  })
  .then(r => r.json())
  .then(data => {
    closeModal('approve-modal');
    if (data.success) {
      showToast(data.message, 'success');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(data.message || 'Failed to approve', 'error');
      btn.disabled = false;
      btn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Yes, Approve';
    }
  })
  .catch(() => {
    closeModal('approve-modal');
    showToast('Network error. Please try again.', 'error');
    btn.disabled = false;
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Yes, Approve';
  });
}

function confirmReject() {
  if (!currentRejectId) return;
  const reason = document.getElementById('reject-reason').value.trim();
  const btn = document.getElementById('btn-confirm-reject');
  btn.disabled = true;
  btn.innerHTML = 'Rejecting...';

  fetch('{{ url("instructor/assignments") }}/' + currentRejectId + '/reject', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Accept': 'application/json',
    },
    body: JSON.stringify({ reason: reason || null }),
  })
  .then(r => r.json())
  .then(data => {
    closeModal('reject-modal');
    if (data.success) {
      showToast(data.message, 'success');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(data.message || 'Failed to reject', 'error');
      btn.disabled = false;
      btn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Reject Request';
    }
  })
  .catch(() => {
    closeModal('reject-modal');
    showToast('Network error. Please try again.', 'error');
    btn.disabled = false;
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Reject Request';
  });
}

let currentInstructorRemoveId = null;

function openInstructorRemoveModal(id, teamName, roleName) {
  currentInstructorRemoveId = id;
  document.getElementById('instructor-remove-detail').innerHTML =
    '<strong>' + teamName + '</strong> — ' + roleName + '<br><span style="font-size:11px;color:var(--red);">This action cannot be undone.</span>';
  closeModal('reject-modal');
  closeModal('approve-modal');
  openModal('instructor-remove-modal');
}

function confirmInstructorRemove() {
  if (!currentInstructorRemoveId) return;
  const btn = document.getElementById('btn-confirm-instructor-remove');
  btn.disabled = true;
  btn.innerHTML = 'Removing...';

  fetch('{{ url("instructor/assignments") }}/' + currentInstructorRemoveId, {
    method: 'DELETE',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Accept': 'application/json',
    },
  })
  .then(r => r.json())
  .then(data => {
    closeModal('instructor-remove-modal');
    if (data.success) {
      showToast(data.message, 'success');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(data.message || 'Failed to remove', 'error');
      btn.disabled = false;
      btn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Yes, Remove';
    }
  })
  .catch(() => {
    closeModal('instructor-remove-modal');
    showToast('Network error. Please try again.', 'error');
    btn.disabled = false;
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Yes, Remove';
  });
}

function showToast(msg, type) {
  const toast = document.getElementById('msg-toast');
  toast.textContent = msg;
  toast.className = 'msg-toast show ' + type;
  setTimeout(() => toast.classList.remove('show'), 4000);
}

// ── Mobile Sidebar ──
function toggleSidebar() { document.body.classList.toggle('sidebar-open'); }
function closeSidebar() { document.body.classList.remove('sidebar-open'); }
</script>

@include('instructor.partials.expert-profile')
</body>
</html>
