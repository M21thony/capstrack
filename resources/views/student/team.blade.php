<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Team Management - CapsTrack  </title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/Styles/team.css')
</head>
<style>
  .pm-close-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; padding: 0; background-color: var(--bg2); color: var(--text3); border: none; border-radius: 50%; cursor: pointer; transition: all 0.15s ease-in-out; outline: none; }
  .pm-close-btn:hover { background-color: var(--border); color: var(--text); transform: scale(1.05); }
  .tag-input-field { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); min-height: 42px; box-sizing: border-box; cursor: text; }
  #tag-search-input { border: none !important; background: transparent !important; color: var(--text) !important; font-size: 13px !important; outline: none !important; flex: 1 !important; min-width: 60px !important; padding: 4px 0 !important; }
  .custom-tag-chip { display: inline-flex; align-items: center; gap: 6px; background: var(--blue-bg); color: var(--blue); font-size: 12px; font-weight: 500; padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(29,78,216,0.25); animation: tagFadeIn 0.15s ease-in-out; }
  .custom-tag-chip-remove { display: inline-flex; align-items: center; justify-content: center; background: rgba(29,78,216,0.15); border: none; color: var(--blue); border-radius: 50%; width: 14px; height: 14px; cursor: pointer; font-size: 10px; padding: 0; transition: background 0.1s; }
  .custom-tag-chip-remove:hover { background: rgba(29,78,216,0.25); }

  [data-theme="dark"] .expert-removed-item { border-bottom-color: var(--border) !important; }
  [data-theme="dark"] #expertRemovedModal > div { background: var(--bg2) !important; box-shadow: 0 24px 60px rgba(0,0,0,0.5) !important; }
  [data-theme="dark"] #expertRemovedModal h3 { color: var(--text) !important; }
  [data-theme="dark"] #expertRemovedContent { color: var(--text2) !important; }
  [data-theme="dark"] .expert-removed-item > div:first-child { color: var(--text) !important; }
  [data-theme="dark"] .expert-removed-item > div:last-child { color: var(--text3) !important; }
  [data-theme="dark"] #expertRemovedOkBtn { background: var(--bg4) !important; color: var(--text) !important; }

  [data-theme="dark"] #ccm-card { background: var(--bg2) !important; box-shadow: 0 24px 60px rgba(0,0,0,0.5) !important; }
  [data-theme="dark"] #ccm-title { color: var(--text) !important; }
  [data-theme="dark"] #ccm-message { color: var(--text2) !important; }
  [data-theme="dark"] #ccm-cancel { border-color: var(--border) !important; background: var(--bg3) !important; color: var(--text2) !important; }
  [data-theme="dark"] #ccm-cancel:hover { background: var(--bg4) !important; }

  .tag-suggestions-box { position: absolute; bottom: 100%; left: 0; width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; max-height: 160px; overflow-y: auto; z-index: 10; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 4px; }
  .tag-suggestion-item { padding: 10px 14px; font-size: 13px; color: var(--text); cursor: pointer; transition: background 0.1s; }
  .tag-suggestion-item:hover { background: var(--bg2); color: var(--blue); }
  @keyframes tagFadeIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
  @keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .sk-overlay { position: absolute; inset: 0; background: var(--bg); z-index: 10; }
  .sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }
  .content { position: relative; }

  /* ── Open Slot Modal Pending Invites ── */
  .open-slot-classmate-option.pending-invite { position: relative; }
  .open-slot-classmate-option.pending-invite::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--blue); border-radius: 3px 0 0 3px; }
  .open-slot-classmate-option.pending-invite .osl-radio { display: none; }

  [data-theme="dark"] .open-slot-classmate-option.pending-invite { background: var(--blue-bg) !important; border-color: rgba(29,78,216,0.4) !important; }

  #detailModal #action-btn-text { background: #f3f4f6; color: #111827; border: 1px solid #d1d5db; box-shadow: none; }
  #detailModal #action-btn-text:hover { background: #e5e7eb; transform: none; box-shadow: none; }
  #detailModal #action-btn-text[disabled] { background: #f3f4f6; color: #111827; }

  /* ── Compose Card ── */
  .compose-card{border-style:dashed!important;border-width:1.5px!important;border-color:var(--border2)!important;background:var(--bg3);cursor:pointer;transition:border-color 0.2s,background 0.2s,box-shadow 0.2s}
  .compose-card:hover{border-color:var(--accent3)!important;background:var(--bg2);box-shadow:0 2px 10px rgba(0,0,0,0.06)}
  .compose-card-inner{display:flex;align-items:center;gap:14px}
  .compose-plus{width:42px;height:42px;border-radius:12px;background:var(--bg2);border:1.5px solid var(--border2);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:500;color:var(--text3);flex-shrink:0;transition:all 0.2s}
  .compose-card:hover .compose-plus{border-color:var(--accent3);color:var(--accent3)}
  .compose-title{font-size:14px;font-weight:600;color:var(--text)}
  .compose-sub{font-size:12px;color:var(--text3);margin-top:2px;line-height:1.4}

  /* ── Recruitment Grid ── */
  .profile-grid{grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;margin-top:12px}

  /* ── Your Post ── */
  .my-post-wrapper{background:var(--bg2);border:1px solid var(--border);border-radius:16px;padding:18px;box-shadow:0 1px 4px rgba(0,0,0,0.05)}
  .my-post-header-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
  .my-post-header-title{display:flex;align-items:center;gap:11px}
  .my-post-icon{width:38px;height:38px;border-radius:12px;background:var(--bg2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--text);flex-shrink:0;box-shadow:0 3px 8px rgba(0,0,0,0.08)}
  .my-post-title{font-size:13px;font-weight:700;color:var(--text);letter-spacing:0.3px}
  .my-post-subtitle{font-size:11px;color:var(--text3);margin-top:1px}
  .my-post-header-actions{display:flex;gap:8px}
  .btn-edit-post{background:#fff;color:#000;border:1px solid #000!important;font-weight:600;padding:8px 16px;font-size:12px}
  .btn-edit-post:hover{background:#f3f4f6;color:#000;border-color:#000!important;box-shadow:0 4px 12px rgba(0,0,0,0.12)}
  .btn-edit-post:active{background:#e5e7eb}
  [data-theme="dark"] .btn-edit-post{background:var(--bg3);color:#fff;border-color:rgba(255,255,255,0.45)!important}
  [data-theme="dark"] .btn-edit-post:hover{background:var(--bg4);color:#fff;border-color:rgba(255,255,255,0.7)!important}
  [data-theme="dark"] .btn-edit-post:active{background:var(--navy-hover)}
  .my-post-card{background:var(--bg2);border:1px solid var(--border);border-radius:14px;padding:18px;display:flex;gap:16px;border-left:3px solid var(--accent);box-shadow:0 2px 10px rgba(0,0,0,0.05)}
  .my-post-card .avatar-ring{width:54px;height:54px;margin:0;flex-shrink:0}
  .my-post-card .avatar-ring img,.my-post-card .avatar-ring .avatar-text{font-size:15px}
  .my-post-body{flex:1;min-width:0;display:flex;flex-direction:column;gap:8px}
  .my-post-name{font-size:14px;font-weight:700;color:var(--text)}
  .my-post-goal{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px}
  .my-post-goal.is-leader{color:#16a34a}
  .my-post-goal.is-member{color:#3b82f6}
  .my-post-text{font-size:12.5px;color:var(--text2);line-height:1.5}
  .my-post-tags{display:flex;flex-wrap:wrap;gap:6px}
  .my-post-tags .expertise-tag{margin:0;font-size:10px;padding:4px 10px}
  .my-post-closed{display:inline-flex;align-items:center;gap:6px;background:rgba(180,83,9,0.1);color:#b45309;border:1px solid rgba(180,83,9,0.2);font-size:10px;font-weight:700;padding:5px 12px;border-radius:20px}

  /* ── Posts Toolbar ── */
  .feed-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:22px;padding:14px 0;border-bottom:1px solid var(--border)}
  .feed-toolbar-left{display:flex;align-items:center;gap:10px}
  .feed-title{font-size:15px;font-weight:700;color:var(--text);letter-spacing:-0.1px}
  .feed-count{font-size:12px;color:var(--text3);background:var(--bg3);border:1px solid var(--border);border-radius:20px;padding:3px 12px;font-weight:600}
  .feed-filter{position:relative}
  .feed-filter-select{appearance:none;-webkit-appearance:none;padding:8px 36px 8px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg2);color:var(--text);font-family:inherit;font-size:13px;font-weight:500;cursor:pointer;outline:none;transition:border-color 0.15s,box-shadow 0.15s;min-width:160px}
  .feed-filter-select:hover{border-color:var(--border2)}
  .feed-filter-select:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
  .feed-filter::after{content:"";position:absolute;right:13px;top:50%;transform:translateY(-50%);width:0;height:0;border-left:5px solid transparent;border-right:5px solid transparent;border-top:6px solid var(--text3);pointer-events:none}

  /* ── Feed Post Cards ── */
  .feed-post-card{background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:18px;cursor:pointer;transition:border-color 0.2s,box-shadow 0.2s;display:flex;flex-direction:column;gap:12px;text-align:left;box-shadow:0 1px 3px rgba(0,0,0,0.04)}
  .feed-post-card:hover{border-color:var(--border2);box-shadow:0 3px 10px rgba(0,0,0,0.07)}
  .feed-post-top{display:flex;align-items:center;gap:12px}
  .feed-post-top .avatar-ring{width:46px;height:46px;margin:0;flex-shrink:0}
  .feed-post-top .avatar-ring .avatar-text{font-size:13px}
  .feed-post-head{flex:1;min-width:0}
  .feed-post-head .name-label{font-size:13px}
  .feed-post-text{font-size:12.5px;color:var(--text2);line-height:1.55;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:0}
  .feed-post-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:2px}
  .feed-post-tags .expert-tag{display:inline-block;background:var(--bg3);color:var(--text2);font-size:10px;padding:3px 9px;border-radius:7px;border:1px solid var(--border);font-weight:600}
  .feed-empty{grid-column:1 / -1;text-align:center;padding:36px;color:var(--text3);font-size:13px;border:1px dashed var(--border2);border-radius:12px}
  .goal-lead{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#16a34a}
  .goal-member{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#3b82f6}

  /* ── Compact Feed & Unified Dashboard ── */
  .compact-feed .feed-post-card { padding: 14px; gap: 10px; border-radius: 12px; }
  .compact-feed .feed-post-card .avatar-ring { width: 36px; height: 36px; margin: 0; }
  .compact-feed .feed-post-card .avatar-ring .avatar-text { font-size: 11px; }
  .compact-feed .feed-post-card .feed-post-head .name-label { font-size: 12px; }
  .compact-feed .feed-post-card .goal-label { font-size: 8px; }
  .compact-feed .feed-post-card .feed-post-text { font-size: 10.5px; }
  .compact-feed .feed-post-card .expert-tag { font-size: 7px; padding: 1px 4px; }

  /* ── Team Dashboard Grid ── */
  .team-dashboard-grid { gap: 16px; }
  .team-dashboard-grid .team-members-panel,
  .team-dashboard-grid .team-overview-panel { border-radius: 14px; padding: 14px; }
  .team-actions-bar { margin-top: 14px; }
  .team-leave-panel { padding: 14px; }

  /* ── Main Content Grid ── */
  .main-content .team-container { gap: 16px; }
  .feed-toolbar.compact { padding: 8px 0; }
  .feed-toolbar.compact .feed-title { font-size: 12px; }
  .feed-toolbar.compact .feed-count { font-size: 10px; padding: 2px 8px; }
  @media (max-width:768px){
    .profile-grid{grid-template-columns:repeat(auto-fill,minmax(240px,1fr))!important;gap:12px!important;margin-top:12px!important}
    .feed-post-card{padding:14px!important;gap:10px!important;border-radius:12px!important}
    .feed-post-top{gap:10px!important}
    .feed-post-top .avatar-ring{width:42px!important;height:42px!important}
    .feed-post-text{font-size:11.5px!important}
    .feed-toolbar{flex-wrap:wrap!important}
    .feed-filter{width:100%!important}
    .feed-filter-select{width:100%!important}
    .my-post-card{padding:16px!important;gap:12px!important}
    .my-post-card .avatar-ring{width:46px!important;height:46px!important}
  }

  @media (max-width:480px){
    .profile-grid{grid-template-columns:repeat(auto-fill,minmax(150px,1fr))!important;gap:8px!important;margin-top:12px!important}
    .feed-post-card{padding:12px!important;gap:8px!important;border-radius:11px!important}
    .feed-post-top .avatar-ring{width:38px!important;height:38px!important}
    .feed-post-text{font-size:11px!important;-webkit-line-clamp:2!important}
    .feed-post-tags .expert-tag{font-size:9px!important;padding:2px 7px!important}
    .my-post-wrapper{padding:14px!important;border-radius:13px!important}
    .my-post-header-row{flex-direction:column!important;align-items:flex-start!important;gap:10px!important}
    .my-post-header-actions{width:100%!important}
    .my-post-header-actions .btn{flex:1!important;justify-content:center!important}
    .my-post-card{padding:14px!important;gap:10px!important;flex-direction:column!important;align-items:flex-start!important}
    .my-post-card .avatar-ring{width:46px!important;height:46px!important}
    .my-post-text{font-size:11.5px!important}
    .name-label{font-size:10px!important}
    .goal-label{font-size:8px!important;letter-spacing:0.3px!important}
  }
</style>
<body>
@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Team Management</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'team'])

    <div id="contentWrapper" style="position: relative;">
    <div id="skeleton-content" class="sk-overlay">
      @if($myTeam)
      <div class="card" style="margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 14px;">
          <div class="sk" style="width: 56px; height: 56px; border-radius: 14px; flex-shrink: 0;"></div>
          <div style="flex: 1; min-width: 0;">
            <div class="sk" style="width: 60%; height: 18px; border-radius: 6px; margin-bottom: 8px;"></div>
            <div class="sk" style="width: 40%; height: 14px; border-radius: 5px;"></div>
          </div>
          <div class="sk" style="width: 80px; height: 24px; border-radius: 20px; flex-shrink: 0;"></div>
        </div>
        <div class="team-dash-grid" style="margin-top: 20px;">
          <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 16px;">
            <div class="sk" style="width: 110px; height: 11px; border-radius: 4px; margin-bottom: 14px;"></div>
            @for ($i = 0; $i < 2; $i++)
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
              <div class="sk" style="width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;"></div>
              <div style="flex: 1;">
                <div class="sk" style="width: 55%; height: 13px; border-radius: 5px; margin-bottom: 4px;"></div>
                <div class="sk" style="width: 35%; height: 10px; border-radius: 3px;"></div>
              </div>
              <div class="sk" style="width: 55px; height: 18px; border-radius: 6px;"></div>
            </div>
            @endfor
          </div>
          <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 16px;">
            <div class="sk" style="width: 110px; height: 11px; border-radius: 4px; margin-bottom: 14px;"></div>
            @for ($i = 0; $i < 5; $i++)
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
              <div class="sk" style="width: 50px; height: 12px; border-radius: 3px;"></div>
              <div class="sk" style="width: 100px; height: 12px; border-radius: 3px;"></div>
            </div>
            @endfor
          </div>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; padding-top: 10px; border-top: 1px solid var(--border);">
          <div class="sk" style="width: 100px; height: 32px; border-radius: 6px;"></div>
          <div class="sk" style="width: 130px; height: 32px; border-radius: 6px;"></div>
        </div>
      </div>
      @endif

      <main class="main-content">
        <div class="page-header">
          <div class="sk" style="width: 220px; height: 22px; border-radius: 6px;"></div>
        </div>
        <div class="team-container">
          <div style="display: flex; flex-direction: column; gap: 20px;">
            <div class="profile-grid">
              @for ($i = 0; $i < 6; $i++)
              <div class="profile-card-compact">
                <div class="sk" style="width: 56px; height: 56px; border-radius: 50%; margin: 0 auto;"></div>
                <div class="sk" style="width: 70%; height: 12px; border-radius: 4px; margin: 10px auto 6px;"></div>
                <div class="sk" style="width: 45%; height: 10px; border-radius: 4px; margin: 0 auto;"></div>
              </div>
              @endfor
            </div>
          </div>
          <div style="display: flex; flex-direction: column; gap: 20px;">
            <div class="card">
              <div class="sk" style="width: 120px; height: 14px; border-radius: 4px; margin-bottom: 15px;"></div>
              @for ($i = 0; $i < 3; $i++)
              <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                <div class="sk" style="width: 90px; height: 11px; border-radius: 3px;"></div>
                <div class="sk" style="width: 30px; height: 11px; border-radius: 3px;"></div>
              </div>
              @endfor
            </div>
            <div class="card">
              <div class="sk" style="width: 100px; height: 14px; border-radius: 4px; margin-bottom: 15px;"></div>
              @for ($i = 0; $i < 2; $i++)
              <div class="invitation-card">
                <div style="display: flex; gap: 10px; align-items: center;">
                  <div class="sk" style="width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;"></div>
                  <div style="flex: 1;">
                    <div class="sk" style="width: 65%; height: 11px; border-radius: 3px; margin-bottom: 4px;"></div>
                    <div class="sk" style="width: 40%; height: 9px; border-radius: 3px;"></div>
                  </div>
                </div>
              </div>
              @endfor
            </div>
          </div>
        </div>
      </main>
    </div>

    <div id="real-content" style="opacity:0">
    @if($myTeam)
    <!-- TEAM DASHBOARD -->
    <div class="team-dashboard-compact" style="margin-bottom: 16px;">
      <div class="team-dash-header" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 14px 18px; background: var(--bg); border: 1px solid var(--border); border-radius: 14px;">
        <div class="team-dash-logo" style="width: 44px; height: 44px; border-radius: 10px; background: #fbbf24; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 15px; color: #1a1a1a; flex-shrink: 0;">
          {{ substr($myTeam->team_name, 0, 2) }}
        </div>
        @php
          $statusColors = [
            'on-going' => ['bg' => '#16a34a', 'label' => 'ON-GOING'],
            'complete' => ['bg' => '#2563eb', 'label' => 'COMPLETED'],
            'closed' => ['bg' => '#6b7280', 'label' => 'CLOSED'],
          ];
          $statusData = $statusColors[$myTeam->team_status] ?? ['bg' => '#16a34a', 'label' => 'ON-GOING'];
          $isVerified = $myTeam->is_confirmed;
          $isPendingVerification = $myTeam->team_status === 'complete' && ! $myTeam->is_confirmed;
        @endphp
        <div style="flex: 1; min-width: 0;">
          @if($myTeam->project_title)
            <div style="font-size: 14px; color: var(--text); font-weight: 700; margin-bottom: 2px; line-height: 1.3;">{{ $myTeam->project_title }}</div>
          @endif
          <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text3);">{{ $myTeam->team_name }}</div>
            <div class="team-status-badge team-status-badge--inline" style="background: {{ $statusData['bg'] }}; color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: flex; align-items: center; gap: 6px;">
              <span style="width: 6px; height: 6px; border-radius: 50%; background: #fff; display: inline-block;"></span>{{ $statusData['label'] }}
              @if($isVerified)
                <span class="verified-chip-inline" style="display:inline-flex;align-items:center;gap:3px;background:rgba(255,255,255,0.2);color:#fff;padding:1px 8px 1px 5px;border-radius:20px;font-size:8px;font-weight:600;backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,0.15);">
                  <svg viewBox="0 0 24 24" width="8" height="8" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                  Verified
                </span>
              @elseif($isPendingVerification)
                <span class="pending-chip-inline" style="display:inline-flex;align-items:center;gap:3px;background:rgba(255,255,255,0.2);color:#fff;padding:1px 8px 1px 5px;border-radius:20px;font-size:8px;font-weight:600;backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,0.15);">
                  <svg viewBox="0 0 24 24" width="8" height="8" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  Pending
                </span>
              @endif
            </div>
            @if($isVerified)
            <div class="verified-phone-badge" style="display:none;align-items:center;gap:4px;background:#fff;border:1px solid #a7f3d0;color:#059669;padding:2px 10px 2px 6px;border-radius:20px;font-size:10px;font-weight:700;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
              <svg viewBox="0 0 24 24" width="10" height="10" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
              Verified
            </div>
            @endif
          </div>
          <div style="font-size: 12px; color: var(--text3); margin-top: 4px;">{{ auth()->user()->studentSchoolYear()->first()?->program ?? 'BSIT-4A' }} · Capstone SY 2025–2026</div>
        </div>
        <div class="team-status-badge team-status-badge--desktop" style="background: {{ $statusData['bg'] }}; color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: flex; align-items: center; gap: 6px;">
          <span style="width: 6px; height: 6px; border-radius: 50%; background: #fff; display: inline-block;"></span>{{ $statusData['label'] }}
          @if($isVerified)
            <span class="verified-chip-desktop" style="display:inline-flex;align-items:center;gap:3px;background:rgba(255,255,255,0.2);color:#fff;padding:1px 8px 1px 5px;border-radius:20px;font-size:8px;font-weight:600;backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,0.15);">
              <svg viewBox="0 0 24 24" width="8" height="8" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
              Verified
            </span>
          @elseif($isPendingVerification)
            <span class="pending-chip-desktop" style="display:inline-flex;align-items:center;gap:3px;background:rgba(255,255,255,0.2);color:#fff;padding:1px 8px 1px 5px;border-radius:20px;font-size:8px;font-weight:600;backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,0.15);">
              <svg viewBox="0 0 24 24" width="8" height="8" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              Pending
            </span>
          @endif
        </div>
      </div>

      <div class="team-dashboard-grid" style="margin-top: 14px; display: grid; grid-template-columns: 1.1fr 1fr; gap: 16px;">
        <!-- Team Members -->
        <div class="team-members-panel" style="background: var(--bg); border: 1px solid var(--border); border-radius: 14px; padding: 14px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--text3);">TEAM MEMBERS</div>
            <span style="color: var(--accent3); font-weight: 600; font-size: 10px;">· Max 5</span>
          </div>
          @php
            $memberSlots = 5;
            $currentMembers = $teamMembers;
            $memberCount = $currentMembers->count();
            $availableSlots = $memberSlots - $memberCount;
            // Get pending invitations sent by this leader
            $pendingInvites = isset($outgoingInvitations) ? $outgoingInvitations : collect();
          @endphp
          <div style="display: flex; flex-direction: column; gap: 10px;">
            @for($i = 0; $i < $memberSlots; $i++)
              @if($i < $memberCount)
                @php $member = $currentMembers[$i]; $isThisUser = $member->student_id == auth()->user()->student_id; $isMemberLeader = $member->student_id == $myTeam->leader_id; @endphp
                 <div style="display: flex; align-items: center; gap: 10px;">
                   <div style="width: 32px; height: 32px; border-radius: 50%; background: {{ $isMemberLeader ? '#fbbf24' : ($isThisUser ? '#fbbf24' : '#7c3aed') }}; display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: 700; color: #1a1a1a; overflow: hidden; flex-shrink: 0;">
                     @if ($member->avatarUrl())
                       <img src="{{ $member->avatarUrl() }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                     @else
                       {{ $member->avatarInitials() }}
                     @endif
                   </div>
                   <div style="flex: 1;">
                     <div style="font-size: 12px; font-weight: 600; color: var(--text);">{{ trim(($member->f_name ?? '') . ' ' . ($member->l_name ?? '')) ?: $member->email ?? 'Member' }}</div>
                     <div style="font-size: 10px; color: var(--text3);">{{ $isMemberLeader ? 'Team Leader' : 'Team Member' }}</div>
                   </div>
                   <div style="background: {{ $isMemberLeader ? '#fef3c7' : 'var(--bg2)' }}; color: {{ $isMemberLeader ? '#92400e' : 'var(--text3)' }}; padding: 2px 6px; border-radius: 6px; font-size: 8px; font-weight: 700;">{{ $isMemberLeader ? 'LEADER' : 'MEMBER' }}</div>
                  @if($isLeader && !$isThisUser && !$myTeam->is_confirmed)
                    <button type="button" onclick="openRemoveMemberModal({{ $member->student_id }}, '{{ addslashes($member->f_name . ' ' . $member->l_name) }}', '{{ addslashes($member->email ?? '') }}', '{{ addslashes(($member->course ?? '') . ($member->year_section ? ' · ' . $member->year_section : '')) }}', '{{ addslashes($member->avatarUrl()) }}')" style="background:none;border:none;color:#ef4444;cursor:pointer;padding:3px;font-size:12px;line-height:1;" title="Remove member">✕</button>
                  @endif
                </div>
              @else
                @php
                  $pendingIndex = $i - $memberCount;
                  $hasPendingInvite = $pendingIndex < $pendingInvites->count();
                  $pendingInvite = $hasPendingInvite ? $pendingInvites[$pendingIndex] : null;
                  $invitee = $pendingInvite ? $pendingInvite->receiver : null;
                @endphp
                @if($hasPendingInvite && $invitee)
                  <!-- Pending Invitation Slot -->
                  <div style="display: flex; align-items: center; gap: 10px; border: 1px solid rgba(29,78,216,0.3); border-radius: 10px; padding: 6px 12px; background: var(--blue-bg);">
                  <div style="width: 32px; height: 32px; border-radius: 50%; background: #7c3aed; display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: 700; color: #1a1a1a; overflow: hidden; flex-shrink: 0;">
                      @if ($invitee->avatarUrl())
                        <img src="{{ $invitee->avatarUrl() }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                      @else
                        {{ $invitee->avatarInitials() }}
                      @endif
                    </div>
                    <div style="flex: 1; min-width: 0;">
                      <div style="font-size: 11px; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ trim(($invitee->f_name ?? '') . ' ' . ($invitee->l_name ?? '')) }}</div>
                    </div>
                    <span style="font-size: 8px; font-weight: 700; color: var(--blue); background: rgba(29,78,216,0.15); padding: 2px 6px; border-radius: 4px;">PENDING</span>
                    @if($isLeader && !$myTeam->is_confirmed)
                      <button type="button" onclick="cancelPendingInvite({{ $pendingInvite->id }}, '{{ addslashes(trim(($invitee->f_name ?? '') . ' ' . ($invitee->l_name ?? ''))) }}')" style="background:none;border:none;color:#ef4444;cursor:pointer;padding:2px;font-size:11px;line-height:1;" title="Cancel invitation">✕</button>
                    @endif
                  </div>
                @elseif($isLeader && !$myTeam->is_confirmed)
                   <button type="button" onclick="openOpenSlotInviteModal()" style="display: flex; align-items: center; gap: 8px; border: 1px dashed var(--border2); border-radius: 10px; padding: 6px 10px; opacity: 0.75; background: transparent; cursor: pointer; width: 100%; text-align: left; transition: all 0.2s;" onmouseover="this.style.opacity='1'; this.style.borderColor='var(--blue)'; this.style.background='var(--blue-bg)'" onmouseout="this.style.opacity='0.75'; this.style.borderColor='var(--border2)'; this.style.background='transparent'">
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--bg2); border: 1.5px dashed var(--border2); display: flex; align-items: center; justify-content: center; font-size: 12px; color: var(--text3);">+</div>
                    <div style="flex: 1;">
                      <div style="font-size: 11px; font-weight: 600; color: var(--text3);">Open Slot</div>
                      <div style="font-size: 10px; color: var(--text3);">Invite a classmate</div>
                    </div>
                  </button>
                @else
                  <div style="display: flex; align-items: center; gap: 8px; border: 1px dashed var(--border2); border-radius: 10px; padding: 6px 10px; opacity: 0.75;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--bg2); border: 1.5px dashed var(--border2); display: flex; align-items: center; justify-content: center; font-size: 12px; color: var(--text3);">+</div>
                    <div style="flex: 1;">
                      <div style="font-size: 11px; font-weight: 600; color: var(--text3);">Open Slot</div>
                      <div style="font-size: 10px; color: var(--text3);">Waiting for a member...</div>
                    </div>
                  </div>
                @endif
              @endif
            @endfor
          </div>
          <div style="margin-top: 10px; display: flex; align-items: center; gap: 4px; font-size: 9px; color: var(--text3);;">
            @php
              $total = $memberSlots;
              $count = \App\Models\StudentSchoolYear::where('team_id', $myTeam->team_id)->count();
            @endphp
            @for($i = 0; $i < $total; $i++)
              <span style="width: 12px; height: 3px; border-radius: 2px; display: inline-block; background: {{ $i < $count ? '#fbbf24' : 'var(--border)' }};"></span>
            @endfor
            <span style="margin-left: 4px;">{{ $count }} / {{ $total }}</span>
          </div>
        </div>


        <!-- Team Overview -->
        <div style="display: flex; flex-direction: column; gap: 0;">
          <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 14px; padding: 14px;">
            <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 12px;">TEAM OVERVIEW</div>
            <div style="display: flex; flex-direction: column; gap: 8px;">
              <div style="display: flex; justify-content: space-between; font-size: 11px;">
                <span style="color: var(--text3);">Section</span>
                @php
                  $authStudent = auth()->user();
                  $program = $authStudent->studentSchoolYear()->first()?->program ?? '';
                  $yearSection = $authStudent->year_section ?? '';
                  $parts = array_map('trim', explode('·', $yearSection));
                  $year = $parts[0] ?? '';
                  $section = $parts[1] ?? '';
                  $yearNum = preg_replace('/[^0-9]/', '', $year);
                  $sectionLabel = $program
                    ? $program . ' - ' . $yearNum . $section
                    : $yearSection;
                @endphp
                <span style="font-weight: 700; color: var(--text);">{{ $sectionLabel }}</span>
              </div>
              <div style="display: flex; justify-content: space-between; font-size: 11px; border-top: 1px solid var(--border); padding-top: 8px;">
                <span style="color: var(--text3);">Team Name</span>
                <span style="font-weight: 700; color: var(--text);">{{ $myTeam->team_name }}</span>
              </div>
              <div style="display: flex; justify-content: space-between; font-size: 11px; border-top: 1px solid var(--border); padding-top: 8px;">
                <span style="color: var(--text3);">Status</span>
                <span style="font-weight: 700; color: {{ $statusData['bg'] }};">{{ $statusData['label'] }}</span>
              </div>
              <div style="display: flex; justify-content: space-between; font-size: 11px; border-top: 1px solid var(--border); padding-top: 8px;">
                <span style="color: var(--text3);">Members</span>
                <span style="font-weight: 700; color: var(--text);">{{ \App\Models\StudentSchoolYear::where('team_id', $myTeam->team_id)->count() }} / 5</span>
              </div>
              <div style="display: flex; justify-content: space-between; font-size: 11px; border-top: 1px solid var(--border); padding-top: 8px;">
                <span style="color: var(--text3);">Your Role</span>
                <span style="font-weight: 700; color: {{ (int) $myTeam->leader_id === (int) auth()->user()->student_id ? '#fbbf24' : 'var(--text3)' }};">{{ (int) $myTeam->leader_id === (int) auth()->user()->student_id ? 'Leader' : ($userRole ?? 'Member') }}</span>
              </div>
              <div style="display: flex; justify-content: space-between; font-size: 11px; border-top: 1px solid var(--border); padding-top: 8px;">
                <span style="color: var(--text3);">Date Formed</span>
                <span style="font-weight: 700; color: var(--text);">{{ $myTeam->created_at ? \Carbon\Carbon::parse($myTeam->created_at)->format('M j, Y') : \Carbon\Carbon::now()->format('M j, Y') }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      @if($isLeader && $pendingLeaveApprovals->count() > 0)
      <div class="team-leave-panel" style="background: var(--bg); border: 1px solid var(--border); border-radius: 14px; padding: 14px; margin-top: 12px;">
        <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 10px;">PENDING LEAVE REQUESTS</div>
        <div style="display: flex; flex-direction: column; gap: 8px;">
          @foreach($pendingLeaveApprovals as $approval)
            @php
              $leaveMember = $approval->student;
              $leaveName = trim(($leaveMember->f_name ?? '').' '.($leaveMember->l_name ?? '')) ?: 'A member';
            @endphp
            <div style="display: flex; align-items: center; gap: 8px; padding: 8px; border: 1px solid var(--border); border-radius: 8px;">
              <div style="width: 30px; height: 30px; border-radius: 50%; background: var(--bg2); display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: 700; color: var(--text); overflow: hidden; flex-shrink: 0;">
                @if ($leaveMember && $leaveMember->avatarUrl())
                  <img src="{{ $leaveMember->avatarUrl() }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                @else
                  {{ $leaveMember ? $leaveMember->avatarInitials() : '?' }}
                @endif
              </div>
              <div style="flex: 1; min-width: 0;">
                <div style="font-size: 11px; font-weight: 600; color: var(--text);">{{ $leaveName }}</div>
                <div style="font-size: 9px; color: var(--text3);">Requesting to leave (attempt {{ $approval->attempt }}/{{ \App\Models\LeaveApproval::MAX_ATTEMPTS }})</div>
              </div>
              <div style="display: flex; gap: 5px; flex-shrink: 0;">
                <form method="POST" action="{{ route('team.leave.approve', $approval->id) }}" style="display:inline;">
                  @csrf
                  <button type="submit" style="padding: 4px 8px; border-radius: 5px; border: 1px solid var(--green); background: var(--green-bg); color: var(--green); font-size: 9px; font-weight: 700; cursor: pointer;">Approve</button>
                </form>
                <form method="POST" action="{{ route('team.leave.decline', $approval->id) }}" style="display:inline;">
                  @csrf
                  <button type="submit" style="padding: 4px 8px; border-radius: 5px; border: 1px solid var(--red); background: var(--red-bg); color: var(--red); font-size: 9px; font-weight: 700; cursor: pointer;">Decline</button>
                </form>
              </div>
            </div>
          @endforeach
        </div>
      </div>
      @endif

      @php
        $teamMemberCount = \App\Models\StudentSchoolYear::where('team_id', $myTeam->team_id)->count();
      @endphp
      <div class="team-actions-bar" style="margin-top: 14px; display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 14px;">
        <div>
          @if($isLeader && $myTeam->team_status === 'complete' && !$myTeam->is_confirmed)
            <span class="pending-badge-inline" style="display:inline-flex;align-items:center;gap:4px;background:linear-gradient(135deg,#d97706,#b45309);color:#fff;padding:4px 10px;border-radius:20px;font-size:9px;font-weight:600;">
              <svg viewBox="0 0 24 24" width="10" height="10" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              Pending Verification
            </span>
          @elseif($isLeader && $myTeam->is_confirmed)
            <span class="verified-badge-inline" style="display:inline-flex;align-items:center;gap:4px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;padding:4px 10px;border-radius:20px;font-size:9px;font-weight:600;">
              <svg viewBox="0 0 24 24" width="10" height="10" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
              Verified
            </span>
          @else
            <span style="font-size: 10px; color: var(--text3);">Ready to recruit</span>
          @endif
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end;">
          @if($isLeader)
            <button type="button" onclick="@if($teamMemberCount > 1)openTransferModal()@else void(0)@endif" style="padding: 5px 12px; border-radius: 6px; border: 1px solid var(--amber); background: var(--amber-bg); color: var(--amber); font-size: 10px; font-weight: 700; @if($teamMemberCount <= 1)cursor: not-allowed; opacity: 0.5;@else cursor: pointer;@endif">Transfer Leadership</button>
          @endif
          @if($isLeader)
            <form method="POST" action="{{ route('team.destroy') }}" id="leave-team-form" style="display: inline;">
              @csrf
              @method('DELETE')
              <button type="button" onclick="confirmLeaveTeam()" style="padding: 5px 12px; border-radius: 6px; border: 1px solid var(--red); background: var(--red-bg); color: var(--red); font-size: 10px; font-weight: 700; cursor: pointer;">Leave Team</button>
            </form>
          @else
            @php
              $leavePending = $hasPendingLeaveApproval;
              $leaveLabel = $leavePending ? 'Leave Pending' : 'Leave Team';
              $leaveOnclick = $leavePending ? '' : 'openLeaveRequestModal()';
              $leaveStateStyle = $leavePending ? 'opacity:0.6;cursor:not-allowed;' : '';
            @endphp
            <button type="button" onclick="{{ $leaveOnclick }}" title="Leave Team" style="padding: 5px 12px; border-radius: 6px; border: 1px solid var(--red); background: var(--red-bg); color: var(--red); font-size: 10px; font-weight: 700; cursor: pointer; {{ $leaveStateStyle }}">{{ $leaveLabel }}</button>
          @endif
        </div>
      </div>
    </div>
    @endif

    @if($errors->any())
      <div class="alert" style="background: #fef2f2; border: 1px solid #fecaca; color: #ef4444; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px;">
        @foreach($errors->all() as $error)
          <div>{{ $error }}</div>
        @endforeach
      </div>
    @endif

    @if(session('status'))
      <div class="alert" style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px;">
        {{ session('status') }}
      </div>
    @endif

     <!-- RECRUITMENT FEED & SIDEBAR -->
     <main class="main-content">
       @if(!$myTeam)
       <div class="page-header">
         <div>
           <h1>Team Selection</h1>
           <p>Find teammates or a capstone team within your section</p>
         </div>
       </div>
       @endif


  <div class="team-container" style="display: grid; @if($myTeam) grid-template-columns: 1fr 300px; @else grid-template-columns: 1fr 300px; @endif gap: 16px;">
    <!-- LEFT COLUMN -->
    <div style="display: flex; flex-direction: column; gap: 16px;">
      @if(!$myTeam && !$myRecruitment)
      <div class="card compose-card" onclick="openPostModal()" style="padding: 14px; margin-bottom: 14px;">
        <div class="compose-card-inner" style="display: flex; align-items: center; gap: 10px;">
          <div class="compose-plus" style="font-size: 18px;">+</div>
          <div>
            <div class="compose-title" style="font-size: 13px; font-weight: 600;">Create a recruitment post</div>
            <div class="compose-sub" style="font-size: 10px; color: var(--text3);">Looking for a team or members? Share your needs here...</div>
          </div>
        </div>
      </div>
      @endif

      @if($myRecruitment && ($myRecruitment->recruitment_goal !== 'build_a_team' || $myTeam))
        @php
          $recruitIsLeader = $myRecruitment->recruitment_goal === 'build_a_team';
          $goalLabel = $recruitIsLeader ? 'BUILD A TEAM' : 'Find a Team';
          $ringClass = $recruitIsLeader ? 'hiring' : 'looking';
          $dotColor = $recruitIsLeader ? '#22c55e' : '#3b82f6';
          $initials = auth()->user()->avatarInitials();
          $introText = $recruitIsLeader
            ? ($myRecruitment->looking_for ?? '')
            : ($myRecruitment->introduction ?? $myRecruitment->looking_for ?? '');
        @endphp
        @if(!(isset($postHidden) && $postHidden))
        <div class="my-post-wrapper" id="my-post-section" style="margin-bottom: 14px; padding: 12px; border-radius: 12px;">
          <div class="my-post-header-row" style="display: flex; align-items: center; justify-content: space-between;">
            <div class="my-post-header-title" style="display: flex; align-items: center; gap: 8px;">
              <div class="my-post-icon" style="width: 30px; height: 30px; border-radius: 8px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </div>
              <div>
                <div class="my-post-title" style="font-size: 12px;">Your Post</div>
                <div class="my-post-subtitle" style="font-size: 10px;">Manage your recruitment post</div>
              </div>
            </div>
            <div class="my-post-header-actions" style="display: flex; gap: 5px;">
              <button type="button" class="btn btn-edit-post" onclick="openEditModal()" style="padding: 5px 10px; font-size: 9px;">
                Edit
              </button>
              <button type="button" class="btn" style="padding:5px 10px; font-size:9px; background:var(--red-bg); color:var(--red); border:1px solid rgba(220,38,38,0.2);" onclick="openDeleteModal()">
                Delete
              </button>
            </div>
          </div>
          <div class="my-post-card" id="my-active-post" style="padding: 10px; gap: 8px;">
            <div class="avatar-ring {{ $ringClass }}" style="width: 32px; height: 32px; margin: 0;">
              @if (auth()->user()->avatarUrl())
                <img src="{{ auth()->user()->avatarUrl() }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
              @else
                <div class="avatar-text" style="font-size: 11px;">{{ $initials }}</div>
              @endif
              <div class="status-badge-dot" style="width: 6px; height: 6px; background:{{ $dotColor }};"></div>
            </div>
            <div class="my-post-body" style="flex: 1; min-width: 0;">
              <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                <div class="my-post-name" style="font-size: 11px;">{{ auth()->user()->f_name }} {{ auth()->user()->l_name }}</div>
                <div class="my-post-goal {{ $recruitIsLeader ? 'is-leader' : 'is-member' }}" style="font-size: 8px;">{{ $goalLabel }}</div>
                @if($isTeamFull && $recruitIsLeader)
                  <span class="my-post-closed" style="font-size: 8px; padding: 1px 4px;">TEAM FULL</span>
                @endif
              </div>
              @if($recruitIsLeader && auth()->user()->team)
                <div style="display:flex; align-items:center; gap:4px; font-size:9px; color:var(--text3);">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                  <span>{{ auth()->user()->team->team_name }}</span>
                </div>
              @endif
              @if($introText)
                <div class="my-post-text" style="font-size: 10px;">{{ $introText }}</div>
              @endif
              @if(!empty($skills))
                <div class="my-post-tags">
                  @foreach($skills as $skill)
                    <span class="expertise-tag" style="font-size: 8px; padding: 1px 4px;">{{ $skill }}</span>
                  @endforeach
                </div>
              @endif
            </div>
          </div>
        </div>
        @endif
      @endif

      <div class="feed-toolbar compact" style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px;">
        <div style="display: flex; align-items: center; gap: 6px;">
          <span class="feed-title" style="font-size: 12px; font-weight: 700; color: var(--text);">Posts</span>
          <span class="feed-count" id="feed-count" style="font-size: 10px; color: var(--text3);"></span>
        </div>
        <div class="feed-filter">
          <select class="feed-filter-select" id="feed-filter" onchange="filterFeed()" style="padding: 4px 8px; font-size: 10px; border-radius: 5px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); cursor: pointer;">
            <option value="all">All Posts</option>
            <option value="build_a_team">Find Member</option>
            <option value="find_a_team">Find Team</option>
          </select>
        </div>
      </div>

      <div class="profile-grid compact-feed" id="recruitment-feed">
          @foreach($otherRecruitments as $recruit)
            @php
              $recruitIsLeader = $recruit->recruitment_goal === 'build_a_team';
              $goalLabel = $recruitIsLeader ? 'BUILD A TEAM' : 'Find a Team';
              $ringClass = $recruitIsLeader ? 'hiring' : 'looking';
              $dotColor = $recruitIsLeader ? '#22c55e' : '#3b82f6';
              $initials = $recruit->student->avatarInitials();
              $feedIntro = $recruitIsLeader
                ? ($recruit->looking_for ?? '')
                : ($recruit->introduction ?? $recruit->looking_for ?? '');
            @endphp
            <div class="feed-post-card compact" data-goal="{{ $recruit->recruitment_goal }}" onclick='viewPostDetails(this, "{{ addslashes($recruit->student->f_name . " " . $recruit->student->l_name) }}", "{{ implode(", ", $recruit->roles ?? []) }}", "{{ $recruit->looking_for }}", "{{ $recruit->student->team->team_name ?? 'No Team' }}", "{{ $recruit->student->team->project_title ?? 'No project set' }}", "{{ $recruit->student->student_number }}", false, "{{ $recruitIsLeader ? 'Leader' : 'Individual' }}", {{ $recruit->student_id }}, {{ $recruit->student->team->team_id ?? 'null' }}, "{{ $recruit->student->avatarUrl() }}", "{{ addslashes($recruit->introduction ?? '') }}")'>
              <div class="feed-post-top">
                <div class="avatar-ring {{ $ringClass }}" style="width: 34px; height: 34px;">
                  @if ($recruit->student->avatarUrl())
                    <img src="{{ $recruit->student->avatarUrl() }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                  @else
                    <div class="avatar-text" style="font-size: 10px;">{{ $initials }}</div>
                  @endif
                  <div class="status-badge-dot" style="width: 7px; height: 7px; background:{{ $dotColor }};"></div>
                </div>
                <div class="feed-post-head" style="min-width: 0;">
                  <div class="name-label" style="font-size: 11px;">{{ $recruit->student->f_name }} {{ $recruit->student->l_name }}</div>
                  <div class="goal-label {{ $recruitIsLeader ? 'goal-lead' : 'goal-member' }}" style="font-size: 8px;">{{ $goalLabel }}</div>
                </div>
              </div>
              @if($feedIntro)
                <div class="feed-post-text" style="font-size: 10px; -webkit-line-clamp: 2;">{{ $feedIntro }}</div>
              @endif
              @if(!empty($recruit->roles))
                <div class="feed-post-tags">
                  @foreach($recruit->roles as $role)
                    <span class="expert-tag" style="font-size: 7px; padding: 1px 4px;">{{ $role }}</span>
                  @endforeach
                </div>
              @endif
            </div>
          @endforeach
      </div>
      <div class="feed-empty" id="feed-filter-empty" style="display:none; font-size: 11px; padding: 16px; text-align: center;">
        @if(!$myRecruitment || ($myRecruitment->recruitment_goal === 'build_a_team' && !$myTeam))
          No recruitment posts yet. Be the first to post!
        @else
          No other recruitment posts yet.
        @endif
      </div>
    </div>

    <!-- RIGHT COLUMN -->
    <div style="display: flex; flex-direction: column; gap: 14px;">
      <div class="card" style="padding: 14px;">
        <div class="section-title" style="font-size: 11px;">Section Overview</div>
        <div style="margin-top: 10px;">
          <div style="display: flex; justify-content: space-between; margin-bottom: 6px; border-bottom: 1px solid var(--border); padding-bottom: 5px;">
            <span style="font-size: 10px; color: var(--text3);">Total Students</span>
            <span style="font-size: 10px; font-weight: 700;">{{ $totalStudents }}</span>
          </div>
          <div style="display: flex; justify-content: space-between; margin-bottom: 6px; border-bottom: 1px solid var(--border); padding-bottom: 5px;">
            <span style="font-size: 10px; color: var(--text3);">Teamed Up</span>
            <span style="font-size: 10px; font-weight: 700; color: var(--green);">{{ $teamedUp }}</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span style="font-size: 10px; color: var(--text3);">Still Individual</span>
            <span style="font-size: 10px; font-weight: 700; color: var(--amber);">{{ $stillIndividual }}</span>
          </div>
        </div>
      </div>
      <div class="card" style="padding: 14px;">
        <div class="section-title" style="display: flex; justify-content: space-between; align-items: center; font-size: 11px;">
          Team Invites
          @if($invites->count() > 0)
            <span style="background: var(--bg3); color: var(--text); border: 1px solid var(--border); padding: 1px 6px; border-radius: 4px; font-size: 9px;">{{ $invites->count() }} New</span>
          @endif
        </div>
        
        <div style="margin-top: 10px;">
          @forelse($invites as $invite)
            <div class="invitation-card" style="padding: 10px; border: 1px solid var(--border); border-radius: 10px; margin-bottom: 8px;">
              <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 6px;">
                <div class="avatar" style="width: 28px; height: 28px; font-size: 9px; overflow: hidden; flex-shrink: 0;">
                  @if ($invite->sender->avatarUrl())
                    <img src="{{ $invite->sender->avatarUrl() }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                  @else
                    {{ strtoupper(substr($invite->sender->f_name ?? 'U', 0, 1) . substr($invite->sender->l_name ?? '', 0, 1)) }}
                  @endif
                </div>
                <div>
                  <div style="font-size: 11px; font-weight: 600;">{{ $invite->team->team_name ?? 'A Team' }} invited you</div>
                  <div style="font-size: 9px; color: var(--text3);">From {{ $invite->sender->f_name ?? '' }} {{ $invite->sender->l_name ?? '' }}</div>
                </div>
              </div>
              <div class="invitation-actions" style="display: flex; gap: 5px;">
                <form method="POST" action="{{ route('invitations.accept', $invite->id) }}" id="accept-form-{{ $invite->id }}" style="display:inline;">
                  @csrf
                  <button class="btn-accept" type="button" style="cursor:pointer; border:none; outline:none; padding: 4px 8px; font-size: 9px; font-weight: 700; border-radius: 5px; background: var(--green-bg); color: var(--green);"
                    onclick="showConfirm({title:'Join Team',message:'Accept invitation from <strong>{{ addslashes($invite->team->team_name ?? 'this team') }}</strong>? Your current post will be removed.',icon:'team',confirmText:'Accept',confirmClass:'success',onConfirm:function(){document.getElementById('accept-form-{{ $invite->id }}').submit();}})">
                    Accept
                  </button>
                </form>
                <form method="POST" action="{{ route('invitations.decline', $invite->id) }}" id="decline-form-{{ $invite->id }}" style="display:inline;">
                  @csrf
                  <button class="btn-decline" title="Decline" type="button" onclick="openDeclineModal({{ $invite->id }}, '{{ addslashes($invite->sender->f_name . ' ' . $invite->sender->l_name) }}', '{{ $invite->type }}', '{{ addslashes($invite->team->team_name ?? '') }}', '{{ addslashes($invite->sender->avatarUrl()) }}', '{{ addslashes($invite->sender->email ?? '') }}', '{{ addslashes(($invite->sender->course ?? '') . ($invite->sender->year_section ? ' · ' . $invite->sender->year_section : '')) }}')" style="padding: 4px 6px; cursor:pointer; border:none; outline:none; color:var(--red);">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                  </button>
                </form>
              </div>
            </div>
          @empty
            <div style="text-align: center; padding: 12px; color: var(--text3); font-size: 10px;">
              No pending invites.
            </div>
          @endforelse

          @if($incomingRequests->isNotEmpty())
            <div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid var(--border);">
              <div style="font-size: 9px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 6px;">Join Requests</div>
              @foreach($incomingRequests as $request)
                <div class="invitation-card" style="padding: 8px; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 6px;">
                  <div style="display: flex; gap: 6px; align-items: center; margin-bottom: 4px;">
                    <div class="avatar" style="width: 24px; height: 24px; font-size: 8px; overflow: hidden; flex-shrink: 0;">
                      @if ($request->sender->avatarUrl())
                        <img src="{{ $request->sender->avatarUrl() }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                      @else
                        {{ strtoupper(substr($request->sender->f_name ?? 'U', 0, 1) . substr($request->sender->l_name ?? '', 0, 1)) }}
                      @endif
                    </div>
                    <div>
                      <div style="font-size: 10px; font-weight: 600;">{{ $request->sender->f_name ?? 'Someone' }} {{ $request->sender->l_name ?? '' }} wants to join</div>
                      <div style="font-size: 8px; color: var(--text3);">Requested to join your team</div>
                    </div>
                  </div>
                  <div class="invitation-actions" style="display: flex; gap: 4px;">
                    <form method="POST" action="{{ route('invitations.accept', $request->id) }}" id="accept-form-{{ $request->id }}" style="display:inline;">
                      @csrf
                      <button class="btn-accept" type="button" style="cursor:pointer; border:none; outline:none; padding: 3px 6px; font-size: 8px; font-weight: 700; border-radius: 4px; background: var(--green-bg); color: var(--green);"
                        onclick="showConfirm({title:'Accept Request',message:'Let <strong>{{ addslashes(($request->sender->f_name ?? '') . ' ' . ($request->sender->l_name ?? '')) }}</strong> join your team? Their post will be removed.',icon:'team',confirmText:'Accept',confirmClass:'success',onConfirm:function(){document.getElementById('accept-form-{{ $request->id }}').submit();}})">
                        Accept
                      </button>
                    </form>
                    <form method="POST" action="{{ route('invitations.decline', $request->id) }}" id="decline-form-{{ $request->id }}" style="display:inline;">
                      @csrf
                      <button class="btn-decline" title="Decline" type="button" onclick="openDeclineModal({{ $request->id }}, '{{ addslashes($request->sender->f_name . ' ' . $request->sender->l_name) }}', '{{ $request->type }}', '{{ addslashes($request->team->team_name ?? '') }}', '{{ addslashes($request->sender->avatarUrl()) }}', '{{ addslashes($request->sender->email ?? '') }}', '{{ addslashes(($request->sender->course ?? '') . ($request->sender->year_section ? ' · ' . $request->sender->year_section : '')) }}')" style="padding: 3px 5px; cursor:pointer; border:none; outline:none; color:var(--red);">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                      </button>
                    </form>
                  </div>
                </div>
              @endforeach
            </div>
          @endif

          @if($outgoingRequests->isNotEmpty())
            <div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid var(--border);">
              <div style="font-size: 9px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 6px;">Your Pending Requests</div>
              @foreach($outgoingRequests as $request)
                <div class="invitation-card" style="padding: 6px 8px; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 5px; display: flex; gap: 6px; align-items: center;">
                  <div class="avatar" style="width: 22px; height: 22px; font-size: 8px; overflow: hidden; flex-shrink: 0; background: var(--amber-bg);">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--amber);"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                  </div>
                  <div style="flex: 1;">
                    <div style="font-size: 10px; font-weight: 600;">{{ $request->team->team_name ?? ($request->receiver->f_name . ' ' . $request->receiver->l_name) }}</div>
                    <div style="font-size: 8px; color: var(--text3);">Awaiting leader's response</div>
                  </div>
                  <span style="font-size: 8px; font-weight: 700; color: var(--amber); background: var(--amber-bg); padding: 1px 5px; border-radius: 4px;">PENDING</span>
                </div>
              @endforeach
              <div style="margin-top: 6px; font-size: 9px; color: var(--text3);">
                You can only send one join request at a time.
              </div>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
  </div>
</main>
    </div>

  @if ($otherRecruitments->hasPages())
  <div class="pagination-container">
      @if ($otherRecruitments->onFirstPage())
          <div class="pg-arrow disabled" title="Previous" style="opacity: 0.5; cursor: not-allowed; display: flex; align-items: center; justify-content: center;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
          </div>
      @else
          <a href="{{ $otherRecruitments->previousPageUrl() }}" class="pg-arrow" title="Previous" style="color: inherit; text-decoration: none; display: flex; align-items: center; justify-content: center;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
          </a>
      @endif
      
      @foreach ($otherRecruitments->getUrlRange(1, $otherRecruitments->lastPage()) as $page => $url)
          @if ($page == $otherRecruitments->currentPage())
              <div class="pg-btn active">{{ $page }}</div>
          @else
              <a href="{{ $url }}" class="pg-btn" style="color: inherit; text-decoration: none;">{{ $page }}</a>
          @endif
      @endforeach

      @if ($otherRecruitments->hasMorePages())
          <a href="{{ $otherRecruitments->nextPageUrl() }}" class="pg-arrow" title="Next" style="color: inherit; text-decoration: none; display: flex; align-items: center; justify-content: center;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
          </a>
      @else
          <div class="pg-arrow disabled" title="Next" style="opacity: 0.5; cursor: not-allowed; display: flex; align-items: center; justify-content: center;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
          </div>
      @endif
  </div>
  @endif

  <div class="tutorial-note">
      <div class="tutorial-item">
          <div class="dot-guide" style="background: #16a34a;"></div>
          <span>Find Members</span>
      </div>
      <div class="tutorial-item">
          <div class="dot-guide" style="background: #3b82f6;"></div>
          <span>Find Team</span>
      </div>
  </div>

  <div class="legal-credits">
      <div class="credits-title">System Developers</div>
      <div class="credits-names">Jolina · John Kenneth · Mark Anthony · Ian Cyrus · Alexa Nicole</div>
  </div>

  <div class="modal-overlay" id="detailModal" onclick="if(event.target==this) closeDetailModal()">
      <div class="card" style="width: 380px; padding: 28px; position: relative;">
        <button type="button" onclick="closeDetailModal()" class="pm-close-btn" aria-label="Close" style="position: absolute; top: 16px; right: 16px;">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 18px;">
              <div id="detail-avatar" class="avatar" style="width: 52px; height: 52px; font-size: 18px; flex-shrink: 0;"></div>
              <div style="flex: 1; min-width: 0;">
                  <h3 id="detail-name" style="margin: 0; font-size: 16px; font-weight: 700; color: var(--text);"></h3>
                  <p id="detail-student-id" style="margin: 2px 0 0; font-size: 11px; color: var(--text3);"></p>
              </div>
          </div>

          <p id="detail-owner-msg" style="font-size: 12px; font-weight: 700; color: var(--accent); margin: 0 0 14px; display: none;">Your Post</p>

          <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: 12px 14px; margin-bottom: 14px; display: flex; flex-direction: column; gap: 6px;">
              <div style="display: flex; justify-content: space-between; font-size: 12px;">
                  <span style="color: var(--text3);">Looking For</span>
                  <span id="detail-looking-for" style="font-weight: 600; color: var(--text); text-align: right; max-width: 60%;"></span>
              </div>
              <div style="display: flex; justify-content: space-between; font-size: 12px; border-top: 1px solid var(--border); padding-top: 6px; margin-top: 2px;">
                  <span style="color: var(--text3);">Team</span>
                  <span id="detail-team-name" style="font-weight: 600; color: var(--text); text-align: right; max-width: 60%;"></span>
              </div>
              <div style="display: flex; justify-content: space-between; font-size: 12px; border-top: 1px solid var(--border); padding-top: 6px; margin-top: 2px;">
                  <span style="color: var(--text3);">Role</span>
                  <span id="detail-role" style="font-weight: 600; color: var(--text); text-align: right; max-width: 60%;"></span>
              </div>
          </div>

          <div style="margin-bottom: 18px;">
              <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 8px; letter-spacing: 0.5px;">Expertise</div>
              <div id="detail-skills" style="display: flex; flex-wrap: wrap; gap: 6px;"></div>
          </div>

          <button class="btn-primary" style="width: 100%;" id="action-btn-text">Send Request</button>
      </div>
  </div>

<div class="modal-overlay" id="postModal" onclick="if(event.target==this) closePostModal()">
  <div class="card modal-card modal-card--recruitment" style="width: 480px; padding: 30px; border: 1px solid var(--accent); position: relative;">
    <button type="button" onclick="closePostModal()" class="pm-close-btn" aria-label="Close" style="position: absolute; top: 20px; right: 20px;">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div class="section-title" id="modal-title">Build a Team</div>
    <div style="margin-top: 20px;">
      <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 8px;">Recruitment Goal</label>
      <select class="custom-select" id="post-goal" style="margin-bottom: 20px;" onchange="updateModalUI()">
        <option value="leader">Build a Team (Find Members)</option>
        <option value="individual">Find a Team (Join Others)</option>
      </select>
      <div class="modal-tutorial" id="modal-note-box">
        <p id="modal-note-text"><strong>CapsTip:</strong> Your team can have up to 5 members including you (the leader). Slots are filled automatically as members join.</p>
      </div>
      <div id="leader-fields">
        <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 8px;">Team Name</label>
        <input type="text" id="team-name" name="team_name" placeholder="Enter your team name..." autocomplete="off" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); font-size: 13px; margin-bottom: 0; box-sizing: border-box;">
        <div id="team-name-msg" style="font-size: 11px; color: var(--red); margin: 6px 0 16px; display: none;"></div>
        <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 8px;">Short Introduction</label>
        <textarea id="leader-looking-for" name="looking_for" rows="3" placeholder="Briefly introduce your Capstone Team..." style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); font-size: 13px; resize: vertical; margin-bottom: 16px; box-sizing: border-box;"></textarea>
      </div>
      <div id="individual-fields" style="display:none;">
        <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 8px;">Short Introduction</label>
        <textarea id="individual-intro" rows="3" placeholder="Briefly introduce what you contribute to the team..." style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); font-size: 13px; resize: vertical; margin-bottom: 16px; box-sizing: border-box;"></textarea>
      </div>
      <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 8px;">Expertise & Roles</label>
      <div class="tag-wrapper-box" style="position: relative; margin-bottom: 25px;">
        <div class="tag-input-field" id="tag-container" onclick="document.getElementById('tag-search-input').focus()">
          <input type="text" id="tag-search-input" placeholder="" oninput="filterTagSuggestions()" onfocus="filterTagSuggestions()">
        </div>
        <div class="tag-suggestions-box" id="tag-suggestions" style="display: none;"></div>
      </div>
      <div class="modal-actions modal-actions--dual" style="display:grid;grid-template-columns: 1fr 1fr;gap:12px;">
        <form method="POST" action="{{ route('recruitment.store') }}" style="width:100%;" id="recruitment-form" onsubmit="publishPost(event)">
          @csrf
          <input type="hidden" name="_method" id="form-method-input" value="">
          <input type="hidden" name="recruitment_goal" id="recruitment-goal-input" value="build_a_team">
          <input type="hidden" name="looking_for" id="looking-for-input" value="">
          <input type="hidden" name="introduction" id="introduction-input" value="">
          <input type="hidden" name="team_name" id="team-name-hidden" value="">
          <input type="hidden" name="member_count" id="member-count-input" value="5">
          <input type="hidden" name="tags" id="tags-hidden-input" value="">
          <button type="submit" class="btn-primary" style="width: 100%; background: var(--blue-bg); color: var(--blue); border: 1.5px solid rgba(29,78,216,0.25); box-shadow: none;" id="form-submit-btn">Publish Post</button>
        </form>
        <button class="google-btn" style="width:100%;border:none;background:transparent;color:var(--text);" onclick="closePostModal()">Cancel</button>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="deleteModal" onclick="if(event.target==this) closeDeleteModal()">
  <div class="card" style="width: 360px; padding: 30px; text-align: center;">
    <div class="section-title">Delete Post</div>
    <p style="margin-top: 12px; font-size: 13px; color: var(--text2);">Are you sure you want to remove your recruitment post? This action cannot be undone.</p>
    <div style="display: flex; gap: 10px; margin-top: 20px;">
      <button class="btn-primary" style="flex: 1; background: #ef4444;" onclick="submitDeleteForm()">Delete</button>
      <button class="google-btn" style="flex: 1; border: none;" onclick="closeDeleteModal()">Cancel</button>
    </div>
  </div>
</div>

<form id="invite-request-form" method="POST" action="{{ route('invitations.store') }}" style="display:none;">
    @csrf
    <input type="hidden" name="receiver_id" id="ir-receiver-id">
    <input type="hidden" name="team_id" id="ir-team-id">
    <input type="hidden" name="type" id="ir-type">
</form>

@if($myRecruitment)
<form method="POST" action="{{ route('recruitment.destroy', $myRecruitment->recruitment_id) }}" id="delete-recruitment-form" style="display:none;">
    @csrf
    @method('DELETE')
</form>
@endif

<form method="POST" action="" id="remove-member-form" style="display:none;">
    @csrf
    @method('DELETE')
</form>

<form method="POST" action="{{ route('team.leave-request') }}" id="leave-request-form" style="display:none;">
    @csrf
</form>

@if($myTeam && !$isLeader)
{{-- Leave Request Modal --}}
<div class="modal-overlay" id="leaveRequestModal" onclick="if(event.target==this) closeLeaveRequestModal()">
  <div class="card modal-card modal-card--team" style="width: 440px; padding: 30px; text-align: center; position: relative;">
    <button type="button" onclick="closeLeaveRequestModal()" class="pm-close-btn" aria-label="Close" style="position: absolute; top: 20px; right: 20px;">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div style="width:56px;height:56px;border-radius:50%;background:rgba(239,68,68,0.08);color:#ef4444;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
      <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    </div>
    <div class="section-title">Request to Leave Team</div>
    <div id="lrm-member" style="display:flex;align-items:center;gap:12px;margin-top:18px;padding:12px;border:1px solid var(--border);border-radius:12px;background:var(--bg2);text-align:left;">
      <div id="lrm-avatar" style="width:44px;height:44px;border-radius:50%;background:#7c3aed;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#1a1a1a;overflow:hidden;flex-shrink:0;">
        @if(auth()->user()->avatarUrl())
          <img src="{{ auth()->user()->avatarUrl() }}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
        @else
          {{ auth()->user()->avatarInitials() }}
        @endif
      </div>
      <div style="flex:1;min-width:0;">
        <div style="font-size:14px;font-weight:600;color:var(--text);">{{ auth()->user()->f_name }} {{ auth()->user()->l_name }}</div>
        <div style="font-size:12px;color:var(--text3);">{{ $myTeam->team_name }}</div>
      </div>
    </div>
    <p style="margin-top:16px;font-size:13px;color:var(--text2);line-height:1.6;text-align:left;">
      Your request will be sent to <strong style="color:var(--text);">{{ $teamLeaderName ?? 'your team leader' }}</strong> for approval.
    </p>
    <ul style="margin:10px 0 0;padding-left:18px;font-size:12px;color:var(--text2);line-height:1.7;text-align:left;">
      <li>If the leader does not respond within <strong style="color:var(--text);">3 hours</strong>, your leave will be <strong style="color:var(--text);">automatically approved</strong>.</li>
      <li>If declined, you can try again — up to <strong style="color:var(--text);">3 times</strong>. After 3 declines, you will be <strong style="color:var(--text);">automatically removed</strong>.</li>
      <li>Your recruitment post will be removed once you leave.</li>
    </ul>
    <div style="display:flex;gap:10px;margin-top:20px;">
      <button type="button" class="btn-primary" style="flex:1;background:#ef4444;border-color:#ef4444;" onclick="submitLeaveRequest()">Send Leave Request</button>
      <button type="button" class="google-btn" style="flex:1;border:none;" onclick="closeLeaveRequestModal()">Cancel</button>
    </div>
  </div>
</div>
@endif

{{-- Open Slot Invite Modal --}}
<div class="modal-overlay" id="openSlotInviteModal" onclick="if(event.target===this) closeOpenSlotInviteModal()">
  <div class="card modal-card modal-card--team" style="width: 460px; padding: 0; overflow: hidden; max-height: 80vh; display: flex; flex-direction: column;">
    <div style="background: linear-gradient(135deg, rgba(29,78,216,0.10), rgba(29,78,216,0.02)); border-bottom: 1px solid var(--border); padding: 22px 26px 18px; position: relative;">
      <button type="button" onclick="closeOpenSlotInviteModal()" class="pm-close-btn" aria-label="Close" style="position: absolute; top: 16px; right: 16px;">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
      <div style="display: flex; align-items: center; gap: 14px;">
        <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(29,78,216,0.14); color: var(--blue); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div>
          <div style="font-size: 17px; font-weight: 700; color: var(--text); letter-spacing: -0.2px;">Invite Classmate</div>
          <div style="font-size: 12px; color: var(--text3); margin-top: 2px;">{{ $myTeam?->team_name }}</div>
        </div>
      </div>
    </div>

    <div style="padding: 22px 26px 18px; border-bottom: 1px solid var(--border);">
      <div style="display: flex; align-items: center; gap: 8px; padding: 10px 14px; background: var(--blue-bg); border: 1px solid rgba(29,78,216,0.2); border-radius: 10px; color: var(--blue); font-size: 12px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
        <span>Classmates in your section appear here. Only those without a team can be invited.</span>
      </div>
    </div>

    <div style="flex: 1; overflow-y: auto; padding: 18px 26px 26px; min-height: 0;">
      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
        <div style="position: relative; flex: 1;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text3); pointer-events: none;"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
          <input type="text" id="open-slot-search" placeholder="Search classmates..." oninput="filterOpenSlotClassmates()" style="width: 100%; padding: 10px 12px 10px 40px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); font-size: 13px; box-sizing: border-box;">
        </div>
      </div>

      <div id="open-slot-classmates-list" style="display: flex; flex-direction: column; gap: 8px; max-height: 400px; overflow-y: auto; padding-right: 4px;">
        {{-- Classmates will be loaded dynamically --}}
        <div id="open-slot-loading" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; color: var(--text3); font-size: 13px;">
          <div class="sk" style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid var(--border); border-top-color: var(--blue); animation: spin 1s linear infinite; margin-bottom: 12px;"></div>
          Loading classmates...
        </div>
        <div id="open-slot-empty" style="display: none; text-align: center; padding: 40px 20px; color: var(--text3); font-size: 13px; border: 1px dashed var(--border2); border-radius: 12px;">
          No available classmates found in your section.
        </div>
      </div>
    </div>

    <div style="padding: 16px 26px 22px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px;">
      <button type="button" class="google-btn" style="border: none;" onclick="closeOpenSlotInviteModal()">Cancel</button>
    </div>
  </div>
</div>

{{-- Transfer Leadership Modal --}}
<div class="modal-overlay" id="transferModal" onclick="if(event.target==this) closeTransferModal()">
  <div class="card modal-card modal-card--transfer" style="width: 440px; padding: 0; overflow: hidden;">
    <div style="background: linear-gradient(135deg, rgba(201,168,44,0.10), rgba(201,168,44,0.02)); border-bottom: 1px solid var(--border); padding: 22px 26px 18px; position: relative;">
      <button type="button" onclick="closeTransferModal()" class="pm-close-btn" aria-label="Close" style="position: absolute; top: 16px; right: 16px;">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
      <div style="display: flex; align-items: center; gap: 14px;">
        <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(201,168,44,0.14); color: var(--accent3); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4h16"/><path d="M14 8h8"/><path d="M18 4v4"/><path d="M3 14l4-6 4 6"/><circle cx="7" cy="14" r="3"/><path d="M21 14l-4-6-4 6"/><circle cx="17" cy="14" r="3"/></svg>
        </div>
        <div>
          <div style="font-size: 17px; font-weight: 700; color: var(--text); letter-spacing: -0.2px;">Transfer Leadership</div>
          <div style="font-size: 12px; color: var(--text3); margin-top: 2px;">{{ $myTeam?->team_name }}</div>
        </div>
      </div>
    </div>

    <form method="POST" action="{{ route('team.transfer-leadership') }}" style="padding: 22px 26px 26px;">
      @csrf
      <div style="display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid var(--border); border-radius: 12px; background: var(--bg3); margin-bottom: 18px;">
        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--accent); color: var(--navy-dark); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; overflow: hidden; flex-shrink: 0;">
          @if(auth()->user()->avatarUrl())
            <img src="{{ auth()->user()->avatarUrl() }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
          @else
            {{ auth()->user()->avatarInitials() }}
          @endif
        </div>
        <div style="flex: 1; min-width: 0;">
          <div style="font-size: 11px; font-weight: 700; color: var(--text3); text-transform: uppercase; letter-spacing: 0.6px;">Current Leader</div>
          <div style="font-size: 13.5px; font-weight: 600; color: var(--text); margin-top: 1px;">{{ auth()->user()->f_name }} {{ auth()->user()->l_name }} <span style="color: var(--text3); font-weight: 500;">(You)</span></div>
        </div>
        <span style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 4px 10px; border-radius: 20px; background: var(--amber-bg); color: var(--amber); flex-shrink: 0;">Leader</span>
      </div>

      <div style="font-size: 11px; font-weight: 700; color: var(--text3); text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 8px;">Select New Leader</div>
      <div id="transfer-member-list" style="display: flex; flex-direction: column; gap: 8px; max-height: 220px; overflow-y: auto; margin-bottom: 16px; padding-right: 2px;">
        @php $hasCandidates = false; @endphp
        @foreach($teamMembers as $member)
          @if($member->student_id !== auth()->user()->student_id)
            @php $hasCandidates = true; @endphp
            <button type="button" class="transfer-member-option" data-id="{{ $member->student_id }}" data-name="{{ $member->f_name }} {{ $member->l_name }}" onclick="selectTransferMember(this)">
              <div class="tmo-avatar">
                @if($member->avatarUrl())
                  <img src="{{ $member->avatarUrl() }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                @else
                  {{ $member->avatarInitials() }}
                @endif
              </div>
              <div style="flex: 1; min-width: 0; text-align: left;">
                <div style="font-size: 13px; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $member->f_name }} {{ $member->l_name }}</div>
                <div style="font-size: 11px; color: var(--text3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $member->course }}{{ $member->year_section ? ' · '.$member->year_section : '' }}</div>
              </div>
              <span class="tmo-radio"></span>
            </button>
          @endif
        @endforeach
        @if(!$hasCandidates)
          <div style="text-align: center; padding: 20px 12px; color: var(--text3); font-size: 13px; border: 1px dashed var(--border2); border-radius: 12px;">No other members to transfer leadership to.</div>
        @endif
      </div>
      <input type="hidden" name="new_leader_id" id="transfer-new-leader-id" value="">
      <span id="transfer-selected-name" style="display: none;"></span>

      <div style="display: flex; gap: 9px; align-items: flex-start; background: var(--accent-light); border: 1px solid rgba(201,168,44,0.25); border-radius: 10px; padding: 11px 13px; margin-bottom: 20px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--accent3)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <p style="font-size: 12px; color: var(--text2); line-height: 1.55; margin: 0;">The selected member receives a request and must <strong style="color: var(--text);">approve</strong> it before becoming the leader. You remain the leader until they accept.</p>
      </div>

      <div class="modal-actions modal-actions--dual" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
        <button type="button" class="google-btn" style="flex: 1; border: none;" onclick="closeTransferModal()">Cancel</button>
        <button type="button" class="btn-primary" id="transfer-submit-btn" style="flex: 1; background: var(--amber); color: #fff; box-shadow: none;" onclick="confirmTransferLeadership()" disabled>Review &amp; Transfer</button>
      </div>
    </form>
  </div>
</div>

@if($pendingLeadershipTransfer)
{{-- Leadership Transfer Request Popup --}}
@php
  $ltData = $pendingLeadershipTransfer->data ?? [];
  $ltOldLeaderId = (int) ($ltData['old_leader_id'] ?? 0);
  $ltOldLeader = $ltOldLeaderId ? \App\Models\Student::find($ltOldLeaderId) : null;
@endphp
<div class="modal-overlay" id="leadershipPopup" onclick="if(event.target==this) leadershipPopDismiss()">
  <div class="card modal-card modal-card--team" style="width: 440px; padding: 0; overflow: hidden; position: relative;">
    <button type="button" onclick="leadershipPopDismiss()" class="pm-close-btn" aria-label="Close" style="position: absolute; top: 18px; right: 18px;">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div style="background: linear-gradient(135deg, rgba(201,168,44,0.10), rgba(201,168,44,0.02)); border-bottom: 1px solid var(--border); padding: 22px 26px 18px;">
      <div style="display: flex; align-items: center; gap: 14px;">
        <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(201,168,44,0.14); color: var(--accent3); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4h16"/><path d="M14 8h8"/><path d="M18 4v4"/><path d="M3 14l4-6 4 6"/><circle cx="7" cy="14" r="3"/><path d="M21 14l-4-6-4 6"/><circle cx="17" cy="14" r="3"/></svg>
        </div>
        <div>
          <div style="font-size: 17px; font-weight: 700; color: var(--text); letter-spacing: -0.2px;">Leadership Transfer Request</div>
          <div style="font-size: 12px; color: var(--text3); margin-top: 2px;">Awaiting your decision</div>
        </div>
      </div>
    </div>

    <div style="padding: 22px 26px 26px;">
      <div style="display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid var(--border); border-radius: 12px; background: var(--bg2); margin-bottom: 18px;">
        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--accent); color: var(--navy-dark); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; overflow: hidden; flex-shrink: 0;">
          @if($ltOldLeader && $ltOldLeader->avatarUrl())
            <img src="{{ $ltOldLeader->avatarUrl() }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
          @else
            {{ $ltOldLeader ? $ltOldLeader->avatarInitials() : '?' }}
          @endif
        </div>
        <div style="flex: 1; min-width: 0;">
          <div style="font-size: 13px; color: var(--text2); line-height: 1.5;">{!! $pendingLeadershipTransfer->message !!}</div>
        </div>
      </div>

      <div style="display: flex; align-items: flex-start; gap: 8px; background: var(--accent-light); border: 1px solid rgba(201,168,44,0.25); border-radius: 10px; padding: 11px 13px; margin-bottom: 22px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--accent3)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <p style="font-size: 12px; color: var(--text2); line-height: 1.55; margin: 0;">If you accept, you become the new team leader. You can also <strong style="color: var(--text);">remind me later</strong> and we'll ask you again in 3 hours.</p>
      </div>

      <form method="POST" action="{{ route('team.leadership.accept', $pendingLeadershipTransfer->id) }}" style="display:none;" id="leadership-pop-accept-form">
        @csrf
      </form>
      <form method="POST" action="{{ route('team.leadership.decline', $pendingLeadershipTransfer->id) }}" style="display:none;" id="leadership-pop-decline-form">
        @csrf
      </form>

      <div style="display: flex; gap: 10px;">
        <button type="button" onclick="showConfirm({title:'Approve and review?',message:'You are about to accept leadership of <strong>{{ addslashes($ltData['team_name'] ?? 'the team') }}</strong>. You will become the new team leader, and the current leader will be notified.',icon:'team',confirmText:'Approve & Review',confirmClass:'success',onConfirm:function(){document.getElementById('leadership-pop-accept-form').submit();}})" style="flex:1;padding:11px 12px;border-radius:9px;border:1px solid var(--green);background:var(--green);color:#fff;font-size:12.5px;font-weight:700;cursor:pointer;transition:all 0.15s;">Approve</button>
        <button type="button" onclick="showConfirm({title:'Decline and review?',message:'You are about to decline leadership of <strong>{{ addslashes($ltData['team_name'] ?? 'the team') }}</strong>. You will stay a member, and the current leader will be notified.',icon:'request',confirmText:'Decline & Review',confirmClass:'danger',onConfirm:function(){document.getElementById('leadership-pop-decline-form').submit();}})" style="flex:1;padding:11px 12px;border-radius:9px;border:1px solid var(--red);background:var(--red-bg);color:var(--red);font-size:12.5px;font-weight:700;cursor:pointer;transition:all 0.15s;">Decline</button>
        <button type="button" onclick="leadershipPopRemindLater()" style="flex:1;padding:11px 12px;border-radius:9px;border:1px solid var(--border);background:var(--bg3);color:var(--text2);font-size:12.5px;font-weight:700;cursor:pointer;transition:all 0.15s;">Remind Me Later</button>
      </div>
    </div>
  </div>
</div>
@endif

{{-- Review & Remove Member Modal --}}
<div class="modal-overlay" id="removeMemberModal" onclick="if(event.target==this) closeRemoveMemberModal()">
  <div class="card modal-card modal-card--team" style="width: 420px; padding: 30px; text-align: center; position: relative;">
    <button type="button" onclick="closeRemoveMemberModal()" class="pm-close-btn" aria-label="Close" style="position: absolute; top: 20px; right: 20px;">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div style="width:56px;height:56px;border-radius:50%;background:rgba(239,68,68,0.08);color:#ef4444;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
      <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </div>
    <div class="section-title">Review &amp; Remove</div>
    <div id="rmm-member" style="display:flex;align-items:center;gap:12px;margin-top:18px;padding:12px;border:1px solid var(--border);border-radius:12px;background:var(--bg2);text-align:left;">
      <div id="rmm-avatar" style="width:44px;height:44px;border-radius:50%;background:#7c3aed;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#1a1a1a;overflow:hidden;flex-shrink:0;"></div>
      <div style="flex:1;min-width:0;">
        <div id="rmm-name" style="font-size:14px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></div>
        <div id="rmm-email" style="font-size:12px;color:var(--text3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></div>
        <div id="rmm-info" style="font-size:11px;color:var(--text3);margin-top:2px;"></div>
      </div>
    </div>
    <p style="margin-top:16px;font-size:13px;color:var(--text2);">Are you sure you want to <strong style="color:#ef4444;">remove</strong> this member from the team? They will be notified and their recruitment post will be removed. This action cannot be undone.</p>
    <div style="display:flex;gap:10px;margin-top:20px;">
      <button type="button" class="btn-primary" style="flex:1;background:#ef4444;border-color:#ef4444;" onclick="submitRemoveMember()">Remove Member</button>
      <button type="button" class="google-btn" style="flex:1;border:none;" onclick="closeRemoveMemberModal()">Cancel</button>
    </div>
  </div>
</div>

{{-- Review & Decline Modal --}}
<div class="modal-overlay" id="declineModal" onclick="if(event.target==this) closeDeclineModal()">
  <div class="card modal-card modal-card--team" style="width: 420px; padding: 30px; text-align: center; position: relative;">
    <button type="button" onclick="closeDeclineModal()" class="pm-close-btn" aria-label="Close" style="position: absolute; top: 20px; right: 20px;">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div style="width:56px;height:56px;border-radius:50%;background:rgba(239,68,68,0.08);color:#ef4444;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
      <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </div>
    <div class="section-title">Review &amp; Decline</div>
    <div id="dm-member" style="display:flex;align-items:center;gap:12px;margin-top:18px;padding:12px;border:1px solid var(--border);border-radius:12px;background:var(--bg2);text-align:left;">
      <div id="dm-avatar" style="width:44px;height:44px;border-radius:50%;background:#7c3aed;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#1a1a1a;overflow:hidden;flex-shrink:0;"></div>
      <div style="flex:1;min-width:0;">
        <div id="dm-name" style="font-size:14px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></div>
        <div id="dm-email" style="font-size:12px;color:var(--text3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></div>
        <div id="dm-info" style="font-size:11px;color:var(--text3);margin-top:2px;"></div>
      </div>
    </div>
    <p style="margin-top:16px;font-size:13px;color:var(--text2);" id="dm-message"></p>
    <div style="display:flex;gap:10px;margin-top:20px;">
      <button type="button" class="btn-primary" style="flex:1;background:#ef4444;border-color:#ef4444;" onclick="submitDecline()">Decline</button>
      <button type="button" class="google-btn" style="flex:1;border:none;" onclick="closeDeclineModal()">Cancel</button>
    </div>
  </div>
</div>

{{-- Expert Removed Popup Modal --}}
@if(($expertRemovedNotifications ?? collect())->isNotEmpty())
@php $expertRemovedIds = $expertRemovedNotifications->pluck('id')->implode(','); @endphp
<div id="expertRemovedModal" style="position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(15,16,22,0.75);backdrop-filter:blur(6px);padding:20px;" data-ids="{{ $expertRemovedIds }}">
  <div style="background:#fff;border-radius:18px;padding:32px 28px 24px;width:100%;max-width:420px;box-shadow:0 24px 60px rgba(0,0,0,0.25);text-align:center;position:relative;animation:expertPopIn 0.25s cubic-bezier(0.16,1,0.3,1);">
    <div style="width:56px;height:56px;border-radius:50%;background:rgba(239,68,68,0.08);color:#ef4444;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
      <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </div>
    <h3 style="font-size:17px;font-weight:700;color:#1e2128;margin:0 0 6px;letter-spacing:-0.3px;">Expert Removed</h3>
    <div id="expertRemovedContent" style="font-size:13px;color:#6b7280;line-height:1.6;margin:0 0 8px;text-align:left;max-height:200px;overflow-y:auto;padding:0 4px;">
      @foreach($expertRemovedNotifications as $notif)
        <div class="expert-removed-item" style="padding:12px 0;@if(!$loop->last)border-bottom:1px solid #f3f4f6;@endif">
          <div style="font-weight:600;color:#1e2128;margin-bottom:4px;">{{ $notif->title }}</div>
          <div>{!! $notif->message !!}</div>
          <div style="font-size:11px;color:#9ca3af;margin-top:4px;">{{ $notif->created_at->diffForHumans() }}</div>
        </div>
      @endforeach
    </div>
    <button id="expertRemovedOkBtn" type="button" style="width:100%;padding:11px 16px;border-radius:10px;border:none;background:#1e2128;color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:background 0.15s;margin-top:12px;">OK</button>
  </div>
</div>
<style>
  @keyframes expertPopIn { from { transform:scale(0.92) translateY(8px); opacity:0; } to { transform:scale(1) translateY(0); opacity:1; } }
  #expertRemovedContent::-webkit-scrollbar { width:4px; }
  #expertRemovedContent::-webkit-scrollbar-thumb { background:#e5e7eb; border-radius:4px; }
  #expertRemovedOkBtn:hover { background:#374151; }
  [data-theme="dark"] #expertRemovedContent::-webkit-scrollbar-thumb { background:var(--border) !important; }
  [data-theme="dark"] #expertRemovedOkBtn:hover { background:var(--navy-hover) !important; }
  .selected-tag{display:inline-flex;align-items:center;gap:4px;padding:4px 8px;border-radius:6px;font-size:11px;font-weight:500;background:var(--blue-bg);border:1px solid rgba(29,78,216,0.2);color:var(--blue)}
  [data-theme="dark"] .google-btn{color:#fff!important;background:var(--bg3)!important}
  [data-theme="dark"] .google-btn:hover{color:#fff!important;background:var(--bg4)!important;border-color:var(--border)!important}
  [data-theme="dark"] #form-submit-btn{color:#fff!important}
  #form-submit-btn:hover{background:rgba(29,78,216,0.12)!important;transform:translateY(-1px)!important;box-shadow:none!important}
</style>
@endif

<style>
  .msg-toast{position:fixed;bottom:30px;right:30px;background:var(--navy-dark);color:#fff;padding:14px 22px;border-radius:12px;font-size:13px;font-weight:500;z-index:99999;display:none;align-items:center;gap:10px;box-shadow:0 8px 30px rgba(0,0,0,0.3);animation:slideUp 0.3s ease;max-width:380px}
  .msg-toast.error{border-left:4px solid var(--red)}
  .msg-toast.show{display:flex}
  @keyframes slideUp{from{transform:translateY(16px);opacity:0}to{transform:translateY(0);opacity:1}}
</style>

<div class="msg-toast" id="msg-toast"></div>

{{-- Custom Confirm Modal --}}
<div id="customConfirmModal" style="display:none; position:fixed; inset:0; z-index:99999; align-items:center; justify-content:center; background:rgba(15,16,22,0.75); backdrop-filter:blur(6px); padding:20px;">
  <div id="ccm-card" style="background:#fff; border-radius:18px; padding:32px 28px 24px; width:100%; max-width:380px; box-shadow:0 24px 60px rgba(0,0,0,0.25); transform:scale(0.92); transition:transform 0.22s cubic-bezier(0.16,1,0.3,1); text-align:center; position:relative;">
    <div id="ccm-icon-wrap" style="width:56px; height:56px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; flex-shrink:0;"></div>
    <h3 id="ccm-title" style="font-size:17px; font-weight:700; color:#1e2128; margin:0 0 8px; letter-spacing:-0.3px;"></h3>
    <p id="ccm-message" style="font-size:13px; color:#6b7280; line-height:1.6; margin:0 0 24px;"></p>
    <div style="display:flex; gap:10px;">
      <button id="ccm-cancel" onclick="closeConfirm()" style="flex:1; padding:11px 16px; border-radius:10px; border:1px solid #e5e7eb; background:#f9fafb; color:#374151; font-size:13px; font-weight:600; cursor:pointer; font-family:inherit; transition:all 0.15s;">Cancel</button>
      <button id="ccm-confirm" style="flex:1; padding:11px 16px; border-radius:10px; border:none; font-size:13px; font-weight:700; cursor:pointer; font-family:inherit; transition:all 0.15s; letter-spacing:0.1px;"></button>
    </div>
  </div>
</div>

<script>
const currentStudentTeamId = {{ auth()->user()->studentSchoolYear()->first()?->team_id ?? 'null' }};
const currentStudentId = {{ auth()->user()->student_id }};
const myRecruitmentGoal = '{{ $myRecruitmentGoal ?? '' }}';
const isTeamFull = @json($isTeamFull ?? false);
const isPostHidden = @json(isset($postHidden) && $postHidden);
const hasPendingRequest = @json($hasPendingRequest ?? false);
let activeTags = [];
let isEditing = false;
let editingId = null;
const leaderPresets = ["UI Designer", "App Developer", "Backend Dev", "Documentation", "Fullstack Dev", "Web Dev", "Mobile Dev", "Project Manager"];
const individualPresets = ["Leadership", "Research", "Design", "Testing", "Frontend Dev", "Database Admin", "QA Engineer", "Web Dev", "UI Designer", "Backend Dev"];

const myTeamId = {{ $myTeam?->team_id ?? 'null' }};

function initTeamNameCheck() {
  function bind(inputId, msgId, ignoreTeamId) {
    var input = document.getElementById(inputId);
    var msg = document.getElementById(msgId);
    if (!input || !msg) return;
    var timer = null;
    input.addEventListener('input', function () {
      clearTimeout(timer);
      var val = input.value.trim();
      if (val.length < 2) {
        msg.style.display = 'none';
        input.style.borderColor = '';
        input.dataset.nameTaken = '0';
        return;
      }
      timer = setTimeout(function () {
        var url = '/team/check-name?name=' + encodeURIComponent(val);
        if (ignoreTeamId) url += '&ignore_team_id=' + ignoreTeamId;
        fetch(url, { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data.exists) {
              msg.textContent = data.message;
              msg.style.display = 'block';
              input.style.borderColor = '#ef4444';
              input.dataset.nameTaken = '1';
            } else {
              msg.style.display = 'none';
              input.style.borderColor = '';
              input.dataset.nameTaken = '0';
            }
          })
          .catch(function () {});
      }, 350);
    });
  }
  bind('team-name', 'team-name-msg', null);
}

function teamNameTaken() {
  var el = document.getElementById('team-name');
  return el && el.dataset.nameTaken === '1';
}

function syncRecruitmentSelectLabels() {
  const isMobile = window.innerWidth <= 480;
  const goalSelect = document.getElementById('post-goal');

  if (goalSelect && goalSelect.options.length >= 2) {
    goalSelect.options[0].text = isMobile ? 'Build Team' : 'Build a Team (Find Members)';
    goalSelect.options[1].text = isMobile ? 'Find Team' : 'Find a Team (Join Others)';
  }
}

@if($myTeam)
function confirmLeaveTeam() {
    @php $teamMemberCount = \App\Models\StudentSchoolYear::where('team_id', $myTeam->team_id)->count(); @endphp
    @if($isLeader && $teamMemberCount > 1)
    showConfirm({
        title: 'Cannot Leave',
        message: 'As the team leader, you cannot leave the team directly. Please <strong>Transfer Leadership</strong> to another member first, then you can leave as a regular member.',
        icon: 'leave',
        confirmText: 'Transfer Now',
        confirmClass: 'primary',
        onConfirm: function() {
            openTransferModal();
        }
    });
    @elseif($isLeader)
    showConfirm({
        title: 'Leave & Terminate Team',
        message: 'You are the only member. Leaving will <strong>permanently terminate</strong> <strong>{{ addslashes($myTeam->team_name) }}</strong>. This cannot be undone.',
        icon: 'leave',
        confirmText: 'Yes, Leave & Terminate',
        confirmClass: 'danger',
        onConfirm: function() {
            document.getElementById('leave-team-form').submit();
        }
    });
    @else
    showConfirm({
        title: 'Leave Team',
        message: 'Are you sure you want to leave <strong>{{ addslashes($myTeam->team_name) }}</strong>?<br><br>\u2022 Your recruitment post will be removed<br>\u2022 You can only rejoin if the leader invites you again<br><br>This action cannot be undone.',
        icon: 'leave',
        confirmText: 'Yes, Leave',
        confirmClass: 'danger',
        onConfirm: function() {
            document.getElementById('leave-team-form').submit();
        }
    });
    @endif
}

function openLeaveRequestModal() {
    var modal = document.getElementById('leaveRequestModal');
    if (modal) modal.classList.add('active');
}

function closeLeaveRequestModal() {
    var modal = document.getElementById('leaveRequestModal');
    if (modal) modal.classList.remove('active');
}

function submitLeaveRequest() {
    document.getElementById('leave-request-form').submit();
}

var removeMemberId = null;

function openRemoveMemberModal(studentId, name, email, info, avatarUrl) {
    removeMemberId = studentId;
    document.getElementById('rmm-name').textContent = name;
    document.getElementById('rmm-email').textContent = email || 'No email provided';
    document.getElementById('rmm-info').textContent = info || '';
    var avatar = document.getElementById('rmm-avatar');
    setAvatar(avatar, avatarUrl, name);
    document.getElementById('removeMemberModal').classList.add('active');
}

function setAvatar(avatarEl, url, name) {
    if (url) {
        avatarEl.innerHTML = '<img src="' + url + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">';
    } else {
        avatarEl.innerHTML = '';
        avatarEl.textContent = (name || 'U').charAt(0).toUpperCase();
    }
}

function closeRemoveMemberModal() {
    document.getElementById('removeMemberModal').classList.remove('active');
    removeMemberId = null;
}

function submitRemoveMember() {
    if (!removeMemberId) return;
    var form = document.getElementById('remove-member-form');
    form.action = '{{ url("/team/members") }}/' + removeMemberId;
    form.submit();
}

function cancelPendingInvite(invitationId, name) {
    showConfirm({
        title: 'Cancel Invitation',
        message: 'Are you sure you want to cancel the invitation sent to <strong>' + name + '</strong>? They will no longer be able to accept it.',
        icon: 'invite',
        confirmText: 'Cancel Invite',
        confirmClass: 'danger',
        onConfirm: function() {
            var csrf = document.querySelector('meta[name="csrf-token"]');
            fetch('{{ route("invitations.decline", ":id") }}'.replace(':id', invitationId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '{{ csrf_token() }}'
                }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast('Invitation cancelled.', 'success');
                    // Page will reload to reflect changes
                    location.reload();
                } else {
                    showToast(data.message || 'Failed to cancel invitation.', 'error');
                }
            })
            .catch(function() {
                showToast('Failed to cancel invitation. Please try again.', 'error');
            });
        }
    });
}

function openTransferModal() {
    document.getElementById('transferModal').classList.add('active');
    document.querySelectorAll('.transfer-member-option').forEach(function(el) { el.classList.remove('selected'); });
    document.getElementById('transfer-new-leader-id').value = '';
    var btn = document.getElementById('transfer-submit-btn');
    if (btn) btn.disabled = true;
}

function selectTransferMember(el) {
    document.querySelectorAll('.transfer-member-option').forEach(function(opt) { opt.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('transfer-new-leader-id').value = el.getAttribute('data-id');
    document.getElementById('transfer-selected-name').textContent = el.getAttribute('data-name');
    var btn = document.getElementById('transfer-submit-btn');
    if (btn) btn.disabled = false;
}

function closeTransferModal() {
    document.getElementById('transferModal').classList.remove('active');
}

function leadershipPopDismiss() {
    var popup = document.getElementById('leadershipPopup');
    if (popup) popup.classList.remove('active');
}

function leadershipPopRemindLater() {
    var csrf = document.querySelector('meta[name="csrf-token"]');
    @if($pendingLeadershipTransfer)
    fetch('{{ route('team.leadership.remind-later', $pendingLeadershipTransfer->id) }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    }).then(function(r) { return r.json(); }).then(function(data) {
        leadershipPopDismiss();
        if (data && data.success) {
            showToast('We will remind you again in 3 hours.', 'success');
        }
    }).catch(function() {
        leadershipPopDismiss();
    });
    @endif
}

function confirmTransferLeadership() {
    var newLeaderId = document.getElementById('transfer-new-leader-id').value;
    var nameEl = document.getElementById('transfer-selected-name');
    if (!newLeaderId) return;
    var name = nameEl ? nameEl.textContent : 'the selected member';
    showConfirm({
        title: 'Review and transfer?',
        message: 'You are about to transfer leadership of <strong>{{ addslashes($myTeam?->team_name ?? 'your team') }}</strong> to <strong>' + name + '</strong>. They must approve it before becoming the leader.',
        icon: 'team',
        confirmText: 'Review & Transfer',
        confirmClass: 'primary',
        onConfirm: function() { document.querySelector('#transferModal form').submit(); }
    });
}

@endif

var declineInvitationId = null;

function openDeclineModal(invitationId, name, type, teamName, avatarUrl, email, info) {
    declineInvitationId = invitationId;
    document.getElementById('dm-name').textContent = name;
    document.getElementById('dm-email').textContent = email || 'No email provided';
    document.getElementById('dm-info').textContent = info || '';
    setAvatar(document.getElementById('dm-avatar'), avatarUrl, name);

    var teamLabel = teamName ? ' <strong style="color:var(--text);">' + teamName + '</strong>' : '';
    document.getElementById('dm-message').innerHTML =
        'Are you sure you want to <strong style="color:#ef4444;">decline</strong>' + (type === 'invite' ? ' the invitation to join' + teamLabel + '?' : ' <strong style="color:var(--text);">' + name + '</strong>\'s request to join your team?') +
        ' They will be notified. This action cannot be undone.';

    document.getElementById('declineModal').classList.add('active');
}

function closeDeclineModal() {
    document.getElementById('declineModal').classList.remove('active');
    declineInvitationId = null;
}

function submitDecline() {
    if (!declineInvitationId) return;
    var form = document.getElementById('decline-form-' + declineInvitationId);
    if (form) form.submit();
}

@if($myTeam && $isLeader)

// Open Slot Invite Modal Functions
function openOpenSlotInviteModal() {
    var modal = document.getElementById('openSlotInviteModal');
    if (modal) {
        modal.classList.add('active');
        loadOpenSlotClassmates();
    }
}

function closeOpenSlotInviteModal() {
    var modal = document.getElementById('openSlotInviteModal');
    if (modal) modal.classList.remove('active');
    // Reset search
    var searchInput = document.getElementById('open-slot-search');
    if (searchInput) searchInput.value = '';
}

function loadOpenSlotClassmates() {
    var listEl = document.getElementById('open-slot-classmates-list');
    var loadingEl = document.getElementById('open-slot-loading');
    var emptyEl = document.getElementById('open-slot-empty');

    if (loadingEl) loadingEl.style.display = 'flex';
    if (emptyEl) emptyEl.style.display = 'none';
    // Clear existing classmate items (keep loading and empty)
    Array.from(listEl.children).forEach(function(child) {
        if (child.id !== 'open-slot-loading' && child.id !== 'open-slot-empty') {
            child.remove();
        }
    });

    console.log('Fetching available classmates from:', '{{ route('team.available-classmates') }}');
    fetch('{{ route('team.available-classmates') }}', {
        headers: { 'Accept': 'application/json' }
    })
    .then(function(r) {
        console.log('Response status:', r.status, r.statusText);
        if (!r.ok) {
            return r.text().then(function(text) { 
                console.error('Server error response:', text);
                throw new Error('HTTP ' + r.status + ': ' + text); 
            });
        }
        return r.json();
    })
    .then(function(data) {
        if (loadingEl) loadingEl.style.display = 'none';

        if (data.error) {
            showToast(data.error, 'error');
            if (emptyEl) {
                emptyEl.textContent = data.error;
                emptyEl.style.display = 'block';
            }
            return;
        }

        var classmates = data.classmates || [];
        if (classmates.length === 0) {
            if (emptyEl) emptyEl.style.display = 'block';
            return;
        }

        classmates.forEach(function(classmate) {
            var isPendingInvite = !!classmate.is_pending_invite;
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'open-slot-classmate-option' + (isPendingInvite ? ' pending-invite' : '');
            item.setAttribute('data-id', classmate.student_id);
            item.setAttribute('data-name', classmate.name);
            item.setAttribute('data-email', classmate.email);
            item.setAttribute('data-avatar', classmate.avatar);
            item.setAttribute('data-student-number', classmate.student_number || '');
            item.setAttribute('data-has-team', classmate.has_team ? '1' : '0');
            item.setAttribute('data-is-pending-invite', isPendingInvite ? '1' : '0');
            if (isPendingInvite) {
                item.setAttribute('data-invitation-id', classmate.invitation_id || '');
            }
            var hasTeam = !!classmate.has_team;
            var cursorStyle = (hasTeam || isPendingInvite) ? 'not-allowed' : 'pointer';
            var baseStyle = 'display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:var(--bg2);cursor:' + cursorStyle + ';text-align:left;transition:all 0.15s;';
            if (hasTeam) {
                item.style.cssText = baseStyle + 'opacity:0.55;';
            } else if (isPendingInvite) {
                item.style.cssText = baseStyle + 'opacity:0.85;border-color:rgba(29,78,216,0.3);background:var(--blue-bg);';
            } else {
                item.style.cssText = baseStyle;
            }
            if (!hasTeam && !isPendingInvite) {
                item.onmouseover = function() { this.style.borderColor = 'var(--blue)'; this.style.background = 'var(--blue-bg)'; };
                item.onmouseout = function() { this.style.borderColor = 'var(--border)'; this.style.background = 'var(--bg2)'; };
                item.onclick = function() { selectOpenSlotClassmate(this); };
            } else if (hasTeam) {
                item.onclick = function() { showToast('This classmate already has a team.', 'error'); };
            } else if (isPendingInvite) {
                item.onclick = function() { showToast('Invitation already sent. Waiting for response.', 'error'); };
            }

            var avatarHtml = classmate.avatar
                ? '<img src="' + classmate.avatar + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">'
                : (classmate.name || 'U').charAt(0).toUpperCase();

            var indicatorHtml = '';
            if (hasTeam) {
                indicatorHtml = '<span style="font-size:10px;font-weight:700;color:#f59e0b;white-space:nowrap;">Has Team</span>';
            } else if (isPendingInvite) {
                indicatorHtml = '<span style="font-size:10px;font-weight:700;color:var(--blue);background:rgba(29,78,216,0.15);padding:3px 8px;border-radius:6px;white-space:nowrap;">PENDING</span>';
            } else {
                indicatorHtml = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;transition:all 0.15s;" class="osl-radio"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4" style="opacity:0;transform:scale(0);transition:all 0.15s;" class="osl-radio-inner"/></svg>';
            }

            item.innerHTML =
                '<div style="width:40px;height:40px;border-radius:50%;background:#7c3aed;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#1a1a1a;overflow:hidden;flex-shrink:0;">' + avatarHtml + '</div>' +
                '<div style="flex:1;min-width:0;">' +
                '  <div style="font-size:13.5px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + classmate.name + '</div>' +
                '  <div style="font-size:11px;color:var(--text3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + (classmate.student_number || '') + ' · ' + (classmate.course || '') + (classmate.year_section ? ' · ' + classmate.year_section : '') + '</div>' +
                '</div>' +
                indicatorHtml;

            listEl.appendChild(item);
        });
    })
    .catch(function(err) {
        if (loadingEl) loadingEl.style.display = 'none';
        if (err.message) {
            showToast(err.message, 'error');
        }
        if (emptyEl) {
            emptyEl.textContent = 'Error: ' + (err.message || 'Unknown error');
            emptyEl.style.display = 'block';
        }
    });
}

function selectOpenSlotClassmate(el) {
    var studentId = el.getAttribute('data-id');
    var name = el.getAttribute('data-name');
    var email = el.getAttribute('data-email');
    var avatar = el.getAttribute('data-avatar');
    var hasTeam = el.getAttribute('data-has-team') === '1';

    if (hasTeam) {
        showToast('This classmate already has a team.', 'error');
        return;
    }

    // Update UI
    document.querySelectorAll('.open-slot-classmate-option').forEach(function(opt) {
        opt.classList.remove('selected');
        var radio = opt.querySelector('.osl-radio-inner');
        if (radio) { radio.style.opacity = '0'; radio.style.transform = 'scale(0)'; }
    });
    el.classList.add('selected');
    var radio = el.querySelector('.osl-radio-inner');
    if (radio) { radio.style.opacity = '1'; radio.style.transform = 'scale(1)'; radio.style.stroke = 'var(--blue)'; }

    // Show confirmation and send invite
    showConfirm({
        title: 'Invite ' + name + '?',
        message: 'Send a team invitation to <strong>' + name + '</strong>? They will receive a notification and can accept or decline.',
        icon: 'invite',
        confirmText: 'Send Invite',
        confirmClass: 'primary',
        onConfirm: function() { sendOpenSlotInvite(studentId, name, email, avatar); }
    });
}

function sendOpenSlotInvite(studentId, name, email, avatar) {
    var teamId = {{ $myTeam?->team_id ?? 'null' }};
    if (!teamId) {
        showToast('No team found.', 'error');
        return;
    }

    var csrf = document.querySelector('meta[name="csrf-token"]');
    fetch('{{ route('invitations.store') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            receiver_id: studentId,
            team_id: teamId,
            type: 'invite'
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showToast('Invitation sent to ' + name + '!', 'success');
            // Refresh the classmate list to show the pending invitation
            loadOpenSlotClassmates();
            // Clear search input
            var searchInput = document.getElementById('open-slot-search');
            if (searchInput) searchInput.value = '';
        } else {
            showToast(data.message || 'Failed to send invitation.', 'error');
        }
    })
    .catch(function() {
        showToast('Failed to send invitation. Please try again.', 'error');
    });
}

function filterOpenSlotClassmates() {
    var query = document.getElementById('open-slot-search').value.toLowerCase().trim();
    var items = document.querySelectorAll('.open-slot-classmate-option');
    var visibleCount = 0;

    items.forEach(function(item) {
        var name = (item.getAttribute('data-name') || '').toLowerCase();
        var email = (item.getAttribute('data-email') || '').toLowerCase();
        var studentNumber = (item.getAttribute('data-student-number') || '').toLowerCase();
        var show = !query || name.includes(query) || email.includes(query) || studentNumber.includes(query);
        item.style.display = show ? 'flex' : 'none';
        if (show) visibleCount++;
    });

    var emptyEl = document.getElementById('open-slot-empty');
    if (emptyEl && items.length > 0) {
        emptyEl.style.display = visibleCount === 0 ? 'block' : 'none';
        if (visibleCount === 0) {
            emptyEl.textContent = 'No classmates match "' + query + '"';
        }
    }
}

@endif

// ── Custom Confirm Modal ──
const ccmIcons = {
    leave: { svg: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>', bg: 'rgba(220,38,38,0.08)', color: '#dc2626' },
    team:  { svg: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>', bg: 'rgba(22,163,74,0.08)', color: '#16a34a' },
    invite:{ svg: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>', bg: 'rgba(201,168,44,0.1)', color: '#b5941f' },
    request:{ svg: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>', bg: 'rgba(8,145,178,0.08)', color: '#0891b2' },
};
const ccmStyles = {
    danger:  { bg: '#dc2626', hover: '#b91c1c', text: '#fff' },
    success: { bg: '#16a34a', hover: '#15803d', text: '#fff' },
    primary: { bg: '#f3f4f6', hover: '#e5e7eb', text: '#111827' },
};
let ccmCallback = null;

function showConfirm({ title, message, icon = 'request', confirmText = 'Confirm', confirmClass = 'primary', onConfirm }) {
    ccmCallback = onConfirm;
    const modal = document.getElementById('customConfirmModal');
    const card  = document.getElementById('ccm-card');
    const iconData = ccmIcons[icon] || ccmIcons.request;
    const styleData = ccmStyles[confirmClass] || ccmStyles.primary;

    document.getElementById('ccm-icon-wrap').style.background = iconData.bg;
    document.getElementById('ccm-icon-wrap').style.color = iconData.color;
    document.getElementById('ccm-icon-wrap').innerHTML = iconData.svg;
    document.getElementById('ccm-title').textContent = title;
    document.getElementById('ccm-message').innerHTML = message;

    const btn = document.getElementById('ccm-confirm');
    btn.textContent = confirmText;
    btn.style.background = styleData.bg;
    btn.style.color = styleData.text;
    btn.onclick = function() { const callback = ccmCallback; closeConfirm(); if (callback) callback(); };
    btn.onmouseenter = function() { this.style.background = styleData.hover; };
    btn.onmouseleave = function() { this.style.background = styleData.bg; };

    modal.style.display = 'flex';
    requestAnimationFrame(() => { card.style.transform = 'scale(1)'; });
}

function closeConfirm() {
    const modal = document.getElementById('customConfirmModal');
    const card  = document.getElementById('ccm-card');
    card.style.transform = 'scale(0.92)';
    setTimeout(() => { modal.style.display = 'none'; }, 180);
    ccmCallback = null;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeConfirm();
});

// ── Mobile Sidebar ──
function toggleSidebar() {
    document.body.classList.toggle('sidebar-open');
    document.body.style.overflow = document.body.classList.contains('sidebar-open') ? 'hidden' : '';
}

function closeSidebar() {
    document.body.classList.remove('sidebar-open');
    document.body.style.overflow = '';
}

document.querySelectorAll('.nav-item').forEach(function(el) {
    el.addEventListener('click', function() {
        if (window.innerWidth <= 768) closeSidebar();
    });
});

function openEditModal() {
    @if($myRecruitment)
    isEditing = true;
    editingId = {{ $myRecruitment->recruitment_id }};

    const form = document.getElementById('recruitment-form');
    form.action = '/recruitment/' + editingId;
    document.getElementById('form-method-input').value = 'PUT';
    document.getElementById('form-submit-btn').textContent = 'Update Post';
    document.getElementById('modal-title').textContent = 'Edit Post';

    const goal = '{{ $myRecruitment->recruitment_goal }}';
    const isLeader = goal === 'build_a_team';
    document.getElementById('post-goal').value = isLeader ? 'leader' : 'individual';
    updateModalUI();

    const teamName = '{{ addslashes($myTeam?->team_name ?? '') }}';
    document.getElementById('team-name').value = teamName;

    const lookingFor = '{{ addslashes($myRecruitment->looking_for ?? '') }}';
    const introduction = '{{ addslashes($myRecruitment->introduction ?? '') }}';
    if (isLeader) {
        document.getElementById('leader-looking-for').value = lookingFor;
    } else {
        document.getElementById('individual-intro').value = introduction;
    }

    activeTags = JSON.parse('{{ addslashes(json_encode($myRecruitment->roles ?? [])) }}'.replace(/&quot;/g, '"').replace(/&#039;/g, "'"));
    renderActiveTags();

    syncRecruitmentSelectLabels();
    document.getElementById('postModal').classList.add('active');
    @endif
}

function resetPostModal() {
    isEditing = false;
    editingId = null;
    const form = document.getElementById('recruitment-form');
    form.action = '{{ route('recruitment.store') }}';
    document.getElementById('form-method-input').value = '';
    document.getElementById('form-submit-btn').textContent = 'Publish Post';
    document.getElementById('team-name').value = '';
    document.getElementById('team-name').dataset.nameTaken = '0';
    document.getElementById('team-name').style.borderColor = '';
    var teamNameMsg = document.getElementById('team-name-msg');
    if (teamNameMsg) teamNameMsg.style.display = 'none';
    document.getElementById('leader-looking-for').value = '';
    document.getElementById('individual-intro').value = '';
    activeTags = [];
    renderActiveTags();
    document.getElementById('post-goal').value = 'leader';
    updateModalUI();
}

function openPostModal() {
    resetPostModal();
    syncRecruitmentSelectLabels();
    document.getElementById('postModal').classList.add('active');
}

function closePostModal() {
    document.getElementById('postModal').classList.remove('active');
    resetPostModal();
}

function sendInviteOrRequest(receiverId, teamId, type) {
    const isInvite = type === 'invite';
    showConfirm({
        title: isInvite ? 'Invite Student' : 'Send Join Request',
        message: isInvite
            ? 'Send a team invitation to this student?'
            : 'Send a request to join this team? Your post will remain visible until accepted.',
        icon: isInvite ? 'invite' : 'request',
        confirmText: isInvite ? 'Send Invite' : 'Send Request',
        confirmClass: 'primary',
        onConfirm: function() {
            document.getElementById('ir-receiver-id').value = receiverId;
            document.getElementById('ir-team-id').value = teamId;
            document.getElementById('ir-type').value = type;
            document.getElementById('invite-request-form').submit();
        }
    });
}

function viewPostDetails(cardEl, name, skills, lookingFor, teamName, projectTitle, studentNumber, isOwner, type, postOwnerId, postOwnerTeamId, avatarUrl, introduction) {
    const modal = document.getElementById('detailModal');

    const goalLabelEl = cardEl.querySelector('.goal-label');
    if (isOwner) {
        goalLabelEl.textContent = 'Your Post';
    } else {
        goalLabelEl.textContent = type === 'Leader' ? 'BUILD A TEAM' : 'Find a Team';
    }

    document.getElementById('detail-name').textContent = name;
    document.getElementById('detail-student-id').textContent = 'Student ID: ' + studentNumber;

    const isLeaderType = type === 'Leader';
    if (isLeaderType) {
        document.getElementById('detail-looking-for').previousElementSibling.textContent = 'Looking For';
        document.getElementById('detail-looking-for').textContent = lookingFor || '-';
    } else {
        document.getElementById('detail-looking-for').previousElementSibling.textContent = 'Introduction';
        document.getElementById('detail-looking-for').textContent = introduction || lookingFor || '-';
    }
    document.getElementById('detail-team-name').textContent = teamName || '-';
    document.getElementById('detail-role').textContent = isLeaderType ? 'Leader' : 'Member';
    
    const ownerMsg = document.getElementById('detail-owner-msg');
    const actionBtn = document.getElementById('action-btn-text');
    if (isOwner) {
        ownerMsg.style.display = 'block';
        if (isTeamFull || isPostHidden) {
            ownerMsg.textContent = isTeamFull ? 'Team is full — post closed' : 'Post closed';
            actionBtn.textContent = isTeamFull ? 'Team Full' : 'Closed';
            actionBtn.disabled = true;
            actionBtn.style.opacity = 0.5;
            actionBtn.style.cursor = 'not-allowed';
            actionBtn.onclick = null;
        } else {
            ownerMsg.textContent = 'Your Post';
            actionBtn.textContent = 'Edit Post';
            actionBtn.disabled = false;
            actionBtn.style.opacity = 1;
            actionBtn.style.cursor = 'pointer';
            actionBtn.onclick = function() { modal.classList.remove('active'); openEditModal(); };
        }
    } else {
        ownerMsg.style.display = 'none';
        actionBtn.disabled = false;
        actionBtn.style.opacity = 1;
        actionBtn.style.cursor = 'pointer';

        if (type === 'Leader') {
            // Viewer wants to send a join request to a build_a_team poster
            if (currentStudentTeamId && currentStudentTeamId === postOwnerTeamId) {
                // Viewer is already in this same team
                actionBtn.textContent = 'Already in this team';
                actionBtn.disabled = true;
                actionBtn.style.opacity = 0.5;
                actionBtn.style.cursor = 'not-allowed';
                actionBtn.onclick = null;
            } else if (currentStudentTeamId) {
                // Already in a team — cannot request to join another
                actionBtn.textContent = 'Leave your team first';
                actionBtn.disabled = true;
                actionBtn.style.opacity = 0.5;
                actionBtn.style.cursor = 'not-allowed';
                actionBtn.onclick = null;
            } else if (myRecruitmentGoal === 'build_a_team') {
                // Current user posted build_a_team but has no team yet — still can't request to join another leader
                actionBtn.textContent = 'You are recruiting — cannot request';
                actionBtn.disabled = true;
                actionBtn.style.opacity = 0.5;
                actionBtn.style.cursor = 'not-allowed';
                actionBtn.onclick = null;
            } else if (hasPendingRequest) {
                // Current user already has a pending join request elsewhere
                actionBtn.textContent = 'Request pending — waiting for response';
                actionBtn.disabled = true;
                actionBtn.style.opacity = 0.5;
                actionBtn.style.cursor = 'not-allowed';
                actionBtn.onclick = null;
            } else {
                actionBtn.textContent = 'Send Request';
                actionBtn.onclick = function() {
                    sendInviteOrRequest(postOwnerId, postOwnerTeamId, 'request');
                };
            }
        } else {
            // find_a_team poster — current user can invite them if they have a team
            if (currentStudentTeamId) {
                actionBtn.textContent = 'Invite to Team';
                actionBtn.onclick = function() {
                    sendInviteOrRequest(postOwnerId, currentStudentTeamId, 'invite');
                };
            } else {
                actionBtn.textContent = 'Invite to Team (Requires Team)';
                actionBtn.disabled = true;
                actionBtn.style.opacity = 0.5;
                actionBtn.style.cursor = 'not-allowed';
                actionBtn.onclick = null;
            }
        }
    }

    const avatarEl = document.getElementById('detail-avatar');
    if (avatarUrl) {
        avatarEl.innerHTML = '<img src="' + avatarUrl + '" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">';
    } else {
        const initials = name.split(' ').map(function(n) { return n[0]; }).join('').substring(0, 2).toUpperCase();
        avatarEl.textContent = initials;
    }

    const skillContainer = document.getElementById('detail-skills');
    if (skills) {
        skillContainer.innerHTML = skills.split(',').map(function(s) {
            return '<span class="expertise-tag" style="margin:0; font-size:10px">' + s.trim() + '</span>';
        }).join('');
    } else {
        skillContainer.innerHTML = '';
    }

    modal.classList.add('active');
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.remove('active');
}

function updateModalUI() {
  syncRecruitmentSelectLabels();
    const goal = document.getElementById('post-goal').value;
    const isLeader = (goal === 'leader');
    document.getElementById('modal-title').textContent = isLeader ? 'Build a Team' : 'Find a Team';
    document.getElementById('modal-note-text').innerHTML = isLeader 
        ? '<strong>CapsTip:</strong> Your team can have up to 5 members including you (the leader). Slots are filled automatically as members join.' 
        : '<strong>CapsTip:</strong> Highlight your best technical strengths. Teams search for specific skills when filling up their empty slots.';
    
    document.getElementById('leader-fields').style.display = isLeader ? 'block' : 'none';
    document.getElementById('individual-fields').style.display = isLeader ? 'none' : 'block';
    document.getElementById('recruitment-goal-input').value = isLeader ? 'build_a_team' : 'find_a_team';
    
    activeTags = [];
    renderActiveTags();
}

  syncRecruitmentSelectLabels();
  window.addEventListener('resize', syncRecruitmentSelectLabels);

function filterTagSuggestions() {
    const goal = document.getElementById('post-goal').value;
    const query = document.getElementById('tag-search-input').value.toLowerCase().trim();
    const pool = (goal === 'leader') ? leaderPresets : individualPresets;
    const filtered = pool.filter(s => s.toLowerCase().includes(query) && !activeTags.includes(s));
    const box = document.getElementById('tag-suggestions');
    
    box.innerHTML = '';
    if (filtered.length === 0) {
        box.style.display = 'none';
        return;
    }
    
    filtered.forEach(tag => {
        const item = document.createElement('div');
        item.className = 'tag-suggestion-item';
        item.textContent = tag;
        item.onclick = function() { addTagToken(tag); };
        box.appendChild(item);
    });
    box.style.display = 'block';
}

function addTagToken(tag) {
    if (!activeTags.includes(tag)) {
        activeTags.push(tag);
    }
    document.getElementById('tag-search-input').value = '';
    document.getElementById('tag-suggestions').style.display = 'none';
    renderActiveTags();
}

function removeTagToken(tag) {
    activeTags = activeTags.filter(t => t !== tag);
    renderActiveTags();
}

function renderActiveTags() {
    const container = document.getElementById('tag-container');
    const input = document.getElementById('tag-search-input');
    container.querySelectorAll('.custom-tag-chip').forEach(el => el.remove());
    
    activeTags.forEach(tag => {
        const chip = document.createElement('div');
        chip.className = 'custom-tag-chip';
        chip.innerHTML = `${tag} <button type="button" class="custom-tag-chip-remove" onclick="removeTagToken('${tag.replace(/'/g, "\\'")}')">×</button>`;
        container.insertBefore(chip, input);
    });
    document.getElementById('tags-hidden-input').value = JSON.stringify(activeTags);
}

function openDeleteModal() {
    document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
}

function submitDeleteForm() {
    document.getElementById('delete-recruitment-form').submit();
}

function showToast(msg, type) {
    var el = document.getElementById('msg-toast');
    if (!el) return;
    el.textContent = msg;
    el.className = 'msg-toast show ' + (type || '');
    setTimeout(function() { el.classList.remove('show'); }, 3000);
}

function filterFeed() {
    var value = document.getElementById('feed-filter').value;
    var cards = document.querySelectorAll('#recruitment-feed .feed-post-card');
    var empty = document.getElementById('feed-filter-empty');
    var visible = 0;

    cards.forEach(function(card) {
        var goal = card.getAttribute('data-goal');
        var show = value === 'all' || goal === value;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    var countEl = document.getElementById('feed-count');
    if (countEl) countEl.textContent = visible + ' post' + (visible === 1 ? '' : 's');

    if (empty) {
        empty.style.display = visible === 0 ? 'block' : 'none';
        if (visible === 0 && value !== 'all') {
            empty.textContent = 'No posts match this filter.';
        }
    }
}

function publishPost(event) {
    if (activeTags.length === 0) {
        event.preventDefault();
        showToast('Please select at least one expertise/role.', 'error');
        return;
    }
    const goal = document.getElementById('post-goal').value;
    if (goal === 'leader') {
        var teamName = document.getElementById('team-name').value.trim();
        var lookingFor = document.getElementById('leader-looking-for').value.trim();
        if (!teamName) {
            event.preventDefault();
            showToast('Please enter your team name.', 'error');
            document.getElementById('team-name').focus();
            return;
        }
        if (teamNameTaken()) {
            event.preventDefault();
            showToast('This team name is already taken.', 'error');
            document.getElementById('team-name').focus();
            return;
        }
        if (!lookingFor) {
            event.preventDefault();
            showToast('Please introduce your capstone team.', 'error');
            document.getElementById('leader-looking-for').focus();
            return;
        }
    } else {
        var intro = document.getElementById('individual-intro').value.trim();
        if (!intro) {
            event.preventDefault();
            showToast('Please introduce yourself and what you contribute.', 'error');
            document.getElementById('individual-intro').focus();
            return;
        }
    }
    document.getElementById('member-count-input').value = 5;
    document.getElementById('tags-hidden-input').value = JSON.stringify(activeTags);

    if (isEditing) {
        event.preventDefault();
        document.getElementById('recruitment-goal-input').value = goal === 'leader' ? 'build_a_team' : 'find_a_team';
        document.getElementById('member-count-input').value = 5;
        if (goal === 'leader') {
            document.getElementById('team-name-hidden').value = document.getElementById('team-name').value;
            document.getElementById('looking-for-input').value = document.getElementById('leader-looking-for').value;
            document.getElementById('introduction-input').value = '';
        } else {
            document.getElementById('team-name-hidden').value = '';
            document.getElementById('looking-for-input').value = '';
            document.getElementById('introduction-input').value = document.getElementById('individual-intro').value;
        }
        const form = document.getElementById('recruitment-form');
        form.querySelectorAll('input[name="roles[]"]').forEach(function(el) { el.remove(); });
        activeTags.forEach(function(tag) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'roles[]';
            input.value = tag;
            form.appendChild(input);
        });
        document.getElementById('tags-hidden-input').disabled = true;
        form.submit();
        return;
    }

    if (goal === 'leader') {
        document.getElementById('team-name-hidden').value = document.getElementById('team-name').value;
        document.getElementById('looking-for-input').value = document.getElementById('leader-looking-for').value;
        document.getElementById('introduction-input').value = '';
    } else {
        document.getElementById('team-name-hidden').value = '';
        document.getElementById('looking-for-input').value = '';
        document.getElementById('introduction-input').value = document.getElementById('individual-intro').value;
    }
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.tag-wrapper-box')) {
        document.getElementById('tag-suggestions').style.display = 'none';
    }
});

document.addEventListener('DOMContentLoaded', function() {
    updateModalUI();
    filterFeed();
    initTeamNameCheck();

    // Skeleton fade-out
    var sk = document.getElementById('skeleton-content');
    var rc = document.getElementById('real-content');
    if (sk && rc) {
        requestAnimationFrame(function () {
            setTimeout(function () {
                sk.style.transition = 'opacity 0.3s ease';
                sk.style.opacity = '0';
                rc.style.transition = 'opacity 0.35s ease';
                rc.style.opacity = '1';
                setTimeout(function () {
                    sk.remove();
                    rc.style.transition = '';
                }, 350);
            }, 600);
        });
    }

    @if(isset($pendingLeadershipTransfer) && $pendingLeadershipTransfer)
    var leadershipPopup = document.getElementById('leadershipPopup');
    if (leadershipPopup) {
        setTimeout(function() { leadershipPopup.classList.add('active'); }, 550);
    }
    @endif

    // Expert Removed modal — mark each as read on dismiss
    var expertModal = document.getElementById('expertRemovedModal');
    var expertOkBtn = document.getElementById('expertRemovedOkBtn');
    if (expertModal && expertOkBtn) {
        var ids = (expertModal.getAttribute('data-ids') || '').split(',').filter(Boolean);
        expertOkBtn.addEventListener('click', function() {
            var token = document.querySelector('meta[name=csrf-token]').content;
            var promises = ids.map(function(id) {
                return fetch('/notifications/' + id + '/read', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token }
                });
            });
            Promise.all(promises).finally(function() {
                expertModal.style.display = 'none';
            });
        });
    }
});
</script>
</body>
</html>