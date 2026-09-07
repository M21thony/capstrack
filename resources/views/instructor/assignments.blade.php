<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Team Assignments - CapsTRACK</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/expert.css') }}" />
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
/* Tabs: the look is the shared .tab-bar/.tab-btn component. */
.tab-bar { margin-bottom: 20px; }
.tab-content { display: none; }
.tab-content.active { display: block; }
.stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }
.stat-card { background: var(--bg2); border: 1px solid var(--border); border-radius: 14px; padding: 18px 20px; }
.stat-card-n { font-size: 28px; font-weight: 700; color: var(--text); }
.stat-card-l { font-size: 12px; color: var(--text3); margin-top: 2px; }
.stat-card-sub { font-size: 11px; color: var(--text3); margin-top: 4px; }
/* A queue is scanned, not read. The project title moved into the panel on
   the right, which leaves one line: who applied, as what, when. Twice as
   many applications fit on screen, which is the whole point of a queue. */
.req-card { background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; padding: 7px 11px; margin-bottom: 5px; transition: 0.15s; display: flex; align-items: center; gap: 10px; cursor: pointer; }
.req-card:hover { border-color: var(--accent); }
.req-card.is-selected { border-color: var(--accent); background: var(--accent-light); box-shadow: inset 3px 0 0 var(--accent); }
.req-card-left { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
.req-team-icon { width: 24px; height: 24px; border-radius: 6px; font-size: 10px !important; background: var(--accent); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; flex-shrink: 0; }
.req-info { flex: 1; min-width: 0; line-height: 1.3; display: flex; align-items: baseline; gap: 8px; }
.req-team-name { font-size: 13px; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.req-project { font-size: 11px; color: var(--text3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.req-leader { font-size: 10px; color: var(--text3); margin-top: 1px; }
.req-card-right { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.req-role-badge { font-size: 9px; font-weight: 600; padding: 2px 8px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.3px; background: var(--accent-light); color: var(--accent3); white-space: nowrap; }
.req-actions { display: flex; gap: 6px; flex-shrink: 0; }
.req-actions .btn { font-size: 11px; padding: 5px 10px; border-radius: 6px; }
.req-actions .btn svg { width: 12px; height: 12px; flex-shrink: 0; }

/* Reading an application should not hide the queue it came from. The list
   stays put on the left; the one you clicked opens beside it. */
.asg-split { display: grid; grid-template-columns: 1fr 320px; gap: 14px; align-items: start; }
.asg-panel { background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; padding: 14px 15px; position: sticky; top: 14px; }
.asg-panel h4 { margin: 0 0 9px; font-size: 10px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase; color: var(--text3); }
.asg-kv { display: flex; justify-content: space-between; gap: 10px; padding: 5px 0; border-bottom: 1px solid var(--border); font-size: 12px; }
.asg-kv:last-of-type { border-bottom: none; }
.asg-kv span:first-child { color: var(--text3); flex-shrink: 0; }
.asg-kv span:last-child { color: var(--text); font-weight: 500; text-align: right; }
.asg-body { font-size: 12px; line-height: 1.55; color: var(--text2); margin: 0; }
.asg-empty { color: var(--text3); font-size: 12px; text-align: center; padding: 26px 8px; }
.asg-acts { display: flex; gap: 7px; margin-top: 13px; }
.asg-acts .btn { flex: 1; justify-content: center; font-size: 12px; }
.asg-slots { font-size: 10.5px; color: var(--text3); text-align: center; margin-top: 8px; }
@media (max-width: 900px) {
  .asg-split { grid-template-columns: 1fr; }
  .asg-panel { position: static; }
}
.status-dot-sm { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.btn { display: inline-flex; align-items: center; gap: 6px; border: none; font-family: 'DM Sans', sans-serif; font-weight: 600; cursor: pointer; transition: all 0.15s; border-radius: 8px; }
.btn:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-sm { font-size: 12px; padding: 7px 14px; }
.btn-soft { background: var(--bg3); color: var(--text2); border: 1px solid var(--border); }
.btn-soft:hover { background: var(--border); border-color: var(--text3); }
.btn-soft:active { background: var(--bg2); }
.modal-actions .btn-success { background: var(--green); color: #fff; border: none; }
.modal-actions .btn-success:hover { filter: brightness(1.08); }
.modal-actions .btn-danger { background: var(--red); color: #fff; border: none; }
.modal-actions .btn-danger:hover { filter: brightness(1.08); }

.empty-state { text-align: center; padding: 40px; color: var(--text3); background: var(--bg2); border-radius: 12px; border: 1px dashed var(--border); }
.empty-state svg { width: 40px; height: 40px; stroke: var(--border2); margin-bottom: 10px; }
.empty-state p { font-size: 14px; }

/* Hamburger (hidden on desktop) */
.hamburger-btn{display:none;align-items:center;justify-content:center;width:36px;height:36px;border-radius:9px;background:rgba(255,255,255,0.06);border:1px solid rgba(201,168,44,0.2);cursor:pointer;color:var(--navy-text);flex-shrink:0;transition:background 0.15s}
.hamburger-btn:hover{background:rgba(201,168,44,0.12)}
.hamburger-btn svg{width:16px;height:16px;stroke:rgba(255,255,200,0.7);fill:none;stroke-width:2;stroke-linecap:butt}

/* Sidebar overlay (mobile) */
.sidebar-overlay{display:none;position:fixed;inset:0;z-index:999;background:rgba(0,0,0,0.5)}

/* ── Responsive: Tablet & below — sidebar fixed on left ── */
@media(max-width:1024px){
  .hamburger-btn{display:inline-flex}
  .sidebar{display:flex!important;position:fixed!important;left:0!important;top:0!important;height:100vh!important;z-index:1000;transform:translateX(-100%);transition:transform 0.25s cubic-bezier(0.16,1,0.3,1)}
  .sidebar-open .sidebar{transform:translateX(0)}
  .sidebar-open .sidebar-overlay{display:block}
  body.sidebar-open{overflow:hidden}
}

/* ── Responsive: Tablet Portrait (≤768px) ── */
@media(max-width:768px){
  .topbar{padding:0 12px!important}
  .topbar .page-title{font-size:15px!important}
  .content{padding:14px!important}

  /* Stat cards: 3 columns always */
  .stat-row{grid-template-columns:repeat(3,1fr)!important;gap:8px!important}
  .stat-card{padding:14px 16px!important}
  .stat-card-n{font-size:22px!important}

  /* Tab bar: wrap */
  .tab-bar{width:100%!important;overflow-x:auto!important;flex-wrap:nowrap!important;gap:2px!important}
  .tab-btn{padding:7px 14px!important;font-size:12px!important;white-space:nowrap!important}

  /* Request cards: stack on mobile */
  .req-card{flex-direction:column!important;align-items:stretch!important;padding:12px!important;gap:8px!important}
  .req-card-left{gap:8px!important}
  .req-team-icon{width:30px!important;height:30px!important;font-size:11px!important}
  .req-team-name{font-size:12px!important}
  .req-project{font-size:10px!important}
  .req-leader{font-size:9px!important}
  .req-card-right{justify-content:space-between!important;padding-top:6px!important;border-top:1px solid var(--border)!important}
  .req-role-badge{font-size:8px!important;padding:2px 6px!important}
  .req-actions .btn{font-size:10px!important;padding:5px 8px!important}

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
  .stat-row{grid-template-columns:repeat(3,1fr)!important;gap:6px!important}
  .stat-card{padding:12px 14px!important}
  .stat-card-n{font-size:18px!important}
  .stat-card-l{font-size:10px!important}

  .req-card{padding:10px!important;gap:6px!important}
  .req-team-icon{width:26px!important;height:26px!important;font-size:10px!important}
  .req-team-name{font-size:11px!important}
  .req-project{font-size:9px!important}
  .req-leader{font-size:8px!important}
  .req-role-badge{font-size:7px!important;padding:1px 5px!important}
  .req-card-right{padding-top:4px!important}
  .req-actions .btn{font-size:9px!important;padding:4px 6px!important}

  .tab-btn{padding:6px 10px!important;font-size:11px!important}
  .tab-bar{gap:2px!important}

  .modal-box{padding:16px!important}
  .modal-box h3{font-size:14px!important}
  .modal-actions{flex-direction:column!important}
  .modal-actions .btn{width:100%!important;justify-content:center!important;padding:10px!important}
  .modal-box textarea{font-size:14px!important;min-height:80px!important;padding:12px 14px!important}
  .confirm-box .confirm-icon{width:36px!important;height:36px!important}
  .confirm-box .confirm-icon svg{width:18px!important;height:18px!important}

  .msg-toast{left:10px!important;right:10px!important;text-align:center;justify-content:center;font-size:12px!important;padding:12px 16px!important}
}
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 300; display: none; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
.modal-overlay.open { display: flex; }
.modal-box { background: var(--bg2); border-radius: 16px; padding: 28px; width: 440px; max-width: 90vw; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: modalIn 0.2s ease; max-height: 85vh; overflow-y: auto;scrollbar-width:none;-ms-overflow-style:none}
.modal-box::-webkit-scrollbar{display:none}
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
.modal-box h3 { font-size: 17px; font-weight: 600; color: var(--text); margin-bottom: 4px; }
.modal-box .modal-sub { font-size: 13px; color: var(--text3); margin-bottom: 16px; }
.modal-box .modal-divider { height: 1px; background: var(--border); margin: 14px 0; }
.modal-box textarea { width: 100%; padding: 14px 16px; border-radius: 12px; border: 1.5px solid var(--border); background: var(--bg2); color: var(--text); font-size: 15px; font-family: 'DM Sans', sans-serif; outline: none; resize: vertical; min-height: 100px; box-sizing: border-box; transition: border-color 0.2s, box-shadow 0.2s; line-height: 1.5; }
.modal-box textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
.modal-box textarea::placeholder { color: var(--text3); font-size: 14px; }
/* Dialog buttons size to their words and sit at the end, which is what
   expert.css already says (.modal-actions{justify-content:flex-end}) and
   what every dialog convention expects. flex:1 here overrode that and made
   a one-word Close into a 422px bar across the whole panel - the wider the
   modal, the more absurd the button. Full width is right on a phone, and
   the narrow breakpoint below still does that. */
.modal-actions { display: flex; gap: 10px; margin-top: 18px; justify-content: flex-end; }
.modal-actions .btn { justify-content: center; padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.15s; border: none; font-family: 'DM Sans', sans-serif; display: inline-flex; align-items: center; gap: 6px; }
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

/* PDF preview modal - opens over whichever modal triggered it */
.pdf-box { width: 900px; max-width: 95vw; height: 88vh; max-height: 88vh; display: flex; flex-direction: column; overflow: hidden; }
.pdf-box .pdf-box-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-shrink: 0; }
.pdf-box .pdf-box-header h3 { margin: 0; }
.pdf-box iframe { flex: 1; width: 100%; border: 1px solid var(--border); border-radius: 8px; min-height: 0; }

.msg-toast { position: fixed; bottom: 30px; right: 30px; background: #1e2128; color: white; padding: 14px 22px; border-radius: 12px; font-size: 13px; font-weight: 500; z-index: 500; display: none; align-items: center; gap: 10px; box-shadow: 0 8px 30px rgba(0,0,0,0.3); animation: slideUp 0.3s ease; }
.msg-toast.success { border-left: 4px solid var(--green); }
.msg-toast.error { border-left: 4px solid var(--red); }
.msg-toast.show { display: flex; }
@keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

/* Skeleton styles now live in expert.css, shared across every instructor page */
</style>
@include('partials.skeleton-head', ['skReveal' => 'block'])
</head>
<body>
@include('instructor.partials.sidebar', ['currentPage' => 'assignments'])
<div class="sidebar-overlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
      <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="page-title">Team Assignments</div>
      <div class="topbar-actions">
        @include('instructor.partials.notification-dropdown')
      </div>
    </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="content" style="padding-top:0;">
      <div class="stat-row">
        <div class="sk" style="height:78px;border-radius:14px;"></div>
        <div class="sk" style="height:78px;border-radius:14px;"></div>
        <div class="sk" style="height:78px;border-radius:14px;"></div>
      </div>
      <div style="display:flex;gap:6px;margin-bottom:20px;">
        <div class="sk" style="height:32px;width:88px;border-radius:8px;"></div>
        <div class="sk" style="height:32px;width:88px;border-radius:8px;"></div>
        <div class="sk" style="height:32px;width:78px;border-radius:8px;"></div>
      </div>
      <div class="sk" style="height:48px;border-radius:10px;margin-bottom:8px;"></div>
      <div class="sk" style="height:48px;border-radius:10px;margin-bottom:8px;width:92%;"></div>
      <div class="sk" style="height:48px;border-radius:10px;margin-bottom:8px;"></div>
      <div class="sk" style="height:48px;border-radius:10px;margin-bottom:8px;width:85%;"></div>
    </div>
  </div>

  <div id="real-content" style="display:none;">
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

      <div class="asg-split">
      <div>
      <div id="tab-pending" class="tab-content active">
        @forelse($pendingAssignments as $assignment)
          <div class="req-card" data-asg="{{ $assignment->assignment_id }}" onclick="selectAssignment({{ $assignment->assignment_id }})">
            <div class="req-card-left">
              <div class="req-team-icon">{{ substr($assignment->team?->team_name ?? 'T', 0, 2) }}</div>
              <div class="req-info">
                <div class="req-team-name">{{ $assignment->team?->team_name ?? 'Unknown Team' }}</div>
                <div class="req-leader">{{ $assignment->created_at ? $assignment->created_at->diffForHumans() : '' }}</div>
              </div>
            </div>
            <div class="req-card-right">
              <span class="req-role-badge">{{ ucfirst($assignment->engaged_role_name ?? '') }}</span>
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
          <div class="req-card" data-asg="{{ $assignment->assignment_id }}" onclick="selectAssignment({{ $assignment->assignment_id }})">
            <div class="req-card-left">
              <div class="req-team-icon" style="background: var(--green);">{{ substr($assignment->team?->team_name ?? 'T', 0, 2) }}</div>
              <div class="req-info">
                <div class="req-team-name">{{ $assignment->team?->team_name ?? 'Unknown Team' }}</div>
                <div class="req-leader">Assigned {{ $assignment->created_at ? $assignment->created_at->diffForHumans() : '' }}</div>
              </div>
            </div>
            <div class="req-card-right">
              <span class="req-role-badge">{{ ucfirst($assignment->engaged_role_name ?? '') }}</span>
              <span class="status-dot-sm" style="background:var(--green);"></span>
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
          <div class="req-card" data-asg="{{ $assignment->assignment_id }}" onclick="selectAssignment({{ $assignment->assignment_id }})">
            <div class="req-card-left">
              <div class="req-team-icon" style="background: var(--red);">{{ substr($assignment->team?->team_name ?? 'T', 0, 2) }}</div>
              <div class="req-info">
                <div class="req-team-name">{{ $assignment->team?->team_name ?? 'Unknown Team' }}</div>
                <div class="req-leader">Declined {{ $assignment->updated_at ? $assignment->updated_at->diffForHumans() : '' }}</div>
              </div>
            </div>
            <div class="req-card-right">
              <span class="req-role-badge">{{ ucfirst($assignment->engaged_role_name ?? '') }}</span>
              <span class="status-dot-sm" style="background:var(--red);"></span>
            </div>
          </div>
        @empty
          <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <p>No rejected requests.</p>
          </div>
        @endforelse
      </div>
      </div>{{-- /list column --}}

      {{-- The application itself, beside the queue rather than on top of it.
           Its buttons open the same confirm dialogs the rows used to, so
           approving and declining are unchanged - only the reading is. --}}
      <div class="asg-panel" id="asg-panel">
        <div id="asg-empty" class="asg-empty">
          Pick a team on the left to read their application.
        </div>

        <div id="asg-detail" style="display:none">
          <h4 id="asg-team"></h4>
          <div class="asg-kv"><span>Applying as</span><span id="asg-role"></span></div>
          <div class="asg-kv"><span>Leader</span><span id="asg-leader"></span></div>
          <div class="asg-kv"><span>Members</span><span id="asg-members"></span></div>
          <div class="asg-kv"><span>Applied</span><span id="asg-date"></span></div>
          <div class="asg-kv" id="asg-status-row"><span>Status</span><span id="asg-status"></span></div>

          <h4 style="margin-top:14px">Title</h4>
          <p class="asg-body" id="asg-title"></p>

          <div id="asg-proposal-wrap" style="margin-top:10px"></div>

          <h4 style="margin-top:14px">Their message</h4>
          <p class="asg-body" id="asg-message"></p>

          <div id="asg-reason-wrap" style="display:none">
            <h4 style="margin-top:14px">Why this was declined</h4>
            <p class="asg-body" id="asg-reason" style="color:var(--red)"></p>
          </div>

          <div class="asg-acts" id="asg-acts"></div>
          <div class="asg-slots">{{ $currentSlots }} of {{ $maxSlots }} slots used</div>
          <div style="text-align:center;margin-top:6px">
            <button class="btn btn-ghost" id="asg-more" style="font-size:11px">Full details</button>
          </div>
        </div>
      </div>
      </div>{{-- /asg-split --}}
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
    {{-- When the team applied with the pre-proposal their CaPSA approved,
         name it. An expert should be able to see the title got through
         rather than take the team's word for a PDF they attached. --}}
    <div class="info-row" id="tm-approved-row" style="display:none;">
      <div class="info-label">Approved title</div>
      <div class="info-value" id="tm-approved"></div>
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

<!-- PDF Preview Modal - opened from the Proposal row above instead of
     navigating to the file in a new tab -->
<div class="modal-overlay" id="pdf-preview-modal">
  <div class="modal-box pdf-box">
    <div class="pdf-box-header">
      <h3>Proposal Preview</h3>
      <button onclick="closePdfPreview()" style="background:none;border:none;font-size:22px;cursor:pointer;color:var(--text3);padding:0;line-height:1" title="Close" aria-label="Close">&times;</button>
    </div>
    <iframe id="pdf-preview-frame" src="about:blank" title="Proposal PDF preview"></iframe>
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
      <button class="btn btn-success" id="btn-confirm-approve" onclick="confirmApprove()">
        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Yes, Approve
      </button>
    </div>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="reject-modal">
  <div class="modal-box">
    <h3>Decline Team Request</h3>
    <div class="modal-sub" id="reject-modal-sub"></div>

    <div class="modal-divider"></div>

    <label style="font-size: 13px; font-weight: 600; color: var(--text2); display: block; margin-bottom: 8px;">
      Reason for Declining <span style="color: var(--red)">*</span>
    </label>
    <textarea id="reject-reason" maxlength="500" oninput="validateReject()"
              placeholder="Most teams can apply again. Say what to fix, or that they should look elsewhere."></textarea>
    <div id="reject-hint" style="font-size:12px;color:var(--text3);margin-top:6px">
      The team reads this and can apply again.
    </div>

    <div class="modal-actions">
      <button class="btn btn-soft" onclick="closeModal('reject-modal')">Cancel</button>
      <button class="btn btn-danger" id="btn-confirm-reject" onclick="confirmReject()">
        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        Decline
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

    {{-- Dropping a team used to be one click, with nothing written down and
         the team never told. Same two steps their own side asks for: say why,
         and type the word. --}}
    <div style="text-align:left;margin-top:16px">
      <label style="display:block;font-size:12px;font-weight:600;color:var(--text);margin-bottom:6px">Reason <span style="color:var(--red)">*</span></label>
      <textarea id="instructor-remove-reason" rows="3" maxlength="500" oninput="validateInstructorRemove()"
                placeholder="Why are you no longer working with this team? They will be told."
                style="width:100%;box-sizing:border-box;padding:9px 11px;border:1px solid var(--border);border-radius:8px;background:var(--bg2);color:var(--text);font:inherit;font-size:13px;resize:vertical"></textarea>

      <label style="display:block;font-size:12px;font-weight:600;color:var(--text);margin:12px 0 6px">Type <strong>remove</strong> to confirm <span style="color:var(--red)">*</span></label>
      <input type="text" id="instructor-remove-confirm" autocomplete="off" oninput="validateInstructorRemove()"
             placeholder='type "remove" here'
             style="width:100%;box-sizing:border-box;padding:9px 11px;border:1px solid var(--border);border-radius:8px;background:var(--bg2);color:var(--text);font:inherit;font-size:13px" />

      <div id="instructor-remove-error" style="display:none;font-size:11.5px;color:var(--red);margin-top:6px"></div>
    </div>

    <div class="modal-actions" style="margin-top: 18px;">
      <button class="btn btn-soft" onclick="closeModal('instructor-remove-modal')">Cancel</button>
      <button class="btn btn-danger" id="btn-confirm-instructor-remove" onclick="confirmInstructorRemove()">
        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        Yes, Remove
      </button>
    </div>
  </div>
</div>

<div class="msg-toast" id="msg-toast"></div>

@include('instructor.partials.skeleton-toggle')

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function switchTab(tab, btn) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  selectFirstVisible();
}

const assignmentData = {{ Js::from($assignmentData) }};

// ── the panel beside the queue ───────────────────────────────────────────
// Selecting a row fills this instead of opening a modal over the list. The
// Approve and Decline buttons still call the same confirm dialogs, so the
// decision itself is unchanged - what changes is that reading an
// application no longer hides the queue it came from.
function selectAssignment(id) {
  const d = assignmentData[id];
  if (!d) return;

  document.querySelectorAll('.req-card').forEach((c) => {
    c.classList.toggle('is-selected', Number(c.dataset.asg) === Number(id));
  });

  document.getElementById('asg-empty').style.display = 'none';
  document.getElementById('asg-detail').style.display = 'block';

  document.getElementById('asg-team').textContent = d.team;
  document.getElementById('asg-role').textContent = d.role;
  document.getElementById('asg-leader').textContent = d.leader || '—';
  document.getElementById('asg-members').textContent =
    d.members.length ? d.members.length + ' listed' : 'None listed';
  document.getElementById('asg-date').textContent = d.date;

  const statusColors = { approved: 'var(--green)', pending: 'var(--amber)', rejected: 'var(--red)' };
  const status = document.getElementById('asg-status');
  status.textContent = d.status.charAt(0).toUpperCase() + d.status.slice(1);
  status.style.color = statusColors[d.status] || 'var(--text3)';

  // The CaPSA-approved title is the one that matters; the team's own field
  // is the fallback for an application made before they had one.
  const title = document.getElementById('asg-title');
  title.textContent = d.approved_title || d.project || 'No title yet';
  title.style.color = d.approved_title ? 'var(--text)' : 'var(--text3)';

  document.getElementById('asg-message').textContent = d.message || 'They did not write one.';

  // Built with DOM APIs, not string concatenation - a file path inside an
  // onclick string is how the Approve button broke once already.
  const proposal = document.getElementById('asg-proposal-wrap');
  proposal.innerHTML = '';
  if (d.proposal) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-ghost';
    btn.style.cssText = 'width:100%;font-size:11px';
    btn.textContent = 'View their proposal';
    btn.addEventListener('click', () => openPdfPreview(d.proposal));
    proposal.appendChild(btn);
  }

  const reasonWrap = document.getElementById('asg-reason-wrap');
  if (d.rejection_reason) {
    reasonWrap.style.display = 'block';
    document.getElementById('asg-reason').textContent = d.rejection_reason;
  } else {
    reasonWrap.style.display = 'none';
  }

  // Which buttons belong here depends on where the application already got
  // to: a pending one is decided, a settled one is only undone.
  const acts = document.getElementById('asg-acts');
  acts.innerHTML = '';

  const add = (label, cls, fn) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'btn ' + cls;
    b.textContent = label;
    b.addEventListener('click', fn);
    acts.appendChild(b);
  };

  if (d.status === 'pending') {
    add('Approve', 'btn-success', () => openApproveModal(id));
    add('Decline', 'btn-danger', () => openRejectModal(id));
  } else if (d.status === 'approved') {
    add('Remove', 'btn-danger', () => openInstructorRemoveModal(id));
  } else {
    add('Remove from list', 'btn-danger', () => openInstructorRemoveModal(id));
  }

  document.getElementById('asg-more').onclick = () => openTeamModal(id);
}

// Open on the first row of whichever tab is showing, so the panel is never
// an empty box next to a full list.
function selectFirstVisible() {
  const first = document.querySelector('.tab-content.active .req-card');

  if (first) {
    selectAssignment(Number(first.dataset.asg));
  } else {
    document.getElementById('asg-empty').style.display = 'block';
    document.getElementById('asg-detail').style.display = 'none';
  }
}

document.addEventListener('DOMContentLoaded', selectFirstVisible);

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

  const approvedRow = document.getElementById('tm-approved-row');
  if (d.approved_title) {
    document.getElementById('tm-approved').innerHTML =
      '<span style="color:var(--green);font-weight:600">✓ CaPSA-approved</span> &middot; ' + d.approved_title;
    approvedRow.style.display = 'flex';
  } else {
    approvedRow.style.display = 'none';
  }

  const proposalEl = document.getElementById('tm-proposal');
  proposalEl.innerHTML = '';
  if (d.proposal) {
    // Built via DOM APIs rather than string concatenation so the file URL
    // never has to be embedded inside an inline onclick/href string - see
    // the Approve/Reject button fix earlier for why that's fragile.
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'proposal-link';
    btn.style.cssText = 'border:none;cursor:pointer;font:inherit';
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="12" y2="12"/><line x1="15" y1="15" x2="12" y2="12"/></svg> View Proposal';
    btn.addEventListener('click', () => openPdfPreview(d.proposal));
    proposalEl.appendChild(btn);
  } else {
    proposalEl.innerHTML = '<span style="color:var(--text3);">No file attached</span>';
  }
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

function openPdfPreview(url) {
  // #toolbar=0&navpanes=0 hides Chrome/Edge's built-in PDF viewer chrome
  // (download/print/zoom bar, thumbnail panel) - just the document itself.
  document.getElementById('pdf-preview-frame').src = url + '#toolbar=0&navpanes=0';
  openModal('pdf-preview-modal');
}

function closePdfPreview() {
  closeModal('pdf-preview-modal');
  // Stop the PDF from continuing to load/render in the background once closed.
  document.getElementById('pdf-preview-frame').src = 'about:blank';
}

let currentApproveId = null;
function openApproveModal(id) {
  const d = assignmentData[id];
  if (!d) return;
  currentApproveId = id;
  document.getElementById('approve-detail').innerHTML =
    '<strong>' + d.team + '</strong> — ' + d.role + '<br>Leader: ' + (d.leader || 'N/A')
    + '<span class="slot-warn">This will use 1 of your ' + {{ $currentSlots }} + '/' + {{ $maxSlots }} + ' available slots.</span>';
  document.getElementById('btn-confirm-approve').disabled = false;
  document.getElementById('btn-confirm-approve').innerHTML =
    '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Yes, Approve';
  openModal('approve-modal');
}

let currentRejectId = null;
function openRejectModal(id) {
  const d = assignmentData[id];
  if (!d) return;
  currentRejectId = id;
  document.getElementById('reject-modal-sub').textContent = d.team + ' — ' + d.role;
  document.getElementById('reject-reason').value = '';
  validateReject();
  document.getElementById('btn-confirm-reject').innerHTML =
    '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Decline';
  openModal('reject-modal');
}

function confirmApprove() {
  if (!currentApproveId) return;
  const btn = document.getElementById('btn-confirm-approve');
  btn.disabled = true;
  btn.innerHTML = 'Approving...';

  fetch('{{ url("instructor/assignments") }}/' + currentApproveId, {
    method: 'PATCH',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ status: 'approved' }),
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

// A decline lands on the team as something they have to act on, and most of
// the time what an expert means is "tighten this and come back". That is
// unsayable without a sentence, so the button stays off until there is one.
function validateReject() {
  const reason = document.getElementById('reject-reason').value.trim();
  const btn = document.getElementById('btn-confirm-reject');
  const hint = document.getElementById('reject-hint');

  btn.disabled = reason.length < 20;
  hint.textContent = reason.length === 0
    ? 'The team reads this and can apply again.'
    : (reason.length < 20 ? 'A line or two, so they know what to change.' : 'The team reads this and can apply again.');
}

function confirmReject() {
  if (!currentRejectId) return;
  const reason = document.getElementById('reject-reason').value.trim();
  if (reason.length < 20) return;
  const btn = document.getElementById('btn-confirm-reject');
  btn.disabled = true;
  btn.innerHTML = 'Declining...';

  fetch('{{ url("instructor/assignments") }}/' + currentRejectId, {
    method: 'PATCH',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Accept': 'application/json',
    },
    body: JSON.stringify({ status: 'rejected', reason: reason }),
  })
  .then(r => r.json())
  .then(data => {
    closeModal('reject-modal');
    if (data.success) {
      showToast(data.message, 'success');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(data.message || 'Failed to decline', 'error');
      btn.disabled = false;
      btn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Decline';
    }
  })
  .catch(() => {
    closeModal('reject-modal');
    showToast('Network error. Please try again.', 'error');
    btn.disabled = false;
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Decline';
  });
}

let currentInstructorRemoveId = null;

function openInstructorRemoveModal(id) {
  const d = assignmentData[id];
  if (!d) return;
  currentInstructorRemoveId = id;
  document.getElementById('instructor-remove-detail').innerHTML =
    '<strong>' + d.team + '</strong> — ' + d.role + '<br><span style="font-size:11px;color:var(--red);">This action cannot be undone.</span>';
  document.getElementById('instructor-remove-reason').value = '';
  document.getElementById('instructor-remove-confirm').value = '';
  document.getElementById('instructor-remove-error').style.display = 'none';
  validateInstructorRemove();
  closeModal('reject-modal');
  closeModal('approve-modal');
  openModal('instructor-remove-modal');
}

/** Both fields have to be filled in before Remove does anything. */
function validateInstructorRemove() {
  const reason = document.getElementById('instructor-remove-reason').value.trim();
  const typed = document.getElementById('instructor-remove-confirm').value.trim();
  const ok = reason.length >= 3 && typed.toLowerCase() === 'remove';
  const btn = document.getElementById('btn-confirm-instructor-remove');
  btn.disabled = !ok;
  btn.style.opacity = ok ? '1' : '0.5';
  btn.style.cursor = ok ? 'pointer' : 'not-allowed';

  return ok;
}

function confirmInstructorRemove() {
  if (!currentInstructorRemoveId) return;
  if (!validateInstructorRemove()) {
    const err = document.getElementById('instructor-remove-error');
    err.textContent = 'Give a reason and type "remove" to confirm.';
    err.style.display = 'block';

    return;
  }

  const btn = document.getElementById('btn-confirm-instructor-remove');
  btn.disabled = true;
  btn.innerHTML = 'Removing...';

  fetch('{{ url("instructor/assignments") }}/' + currentInstructorRemoveId, {
    method: 'DELETE',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      reason: document.getElementById('instructor-remove-reason').value.trim(),
      confirm: document.getElementById('instructor-remove-confirm').value.trim(),
    }),
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
@include('partials.escape-closes-modals')
</body>
</html>

