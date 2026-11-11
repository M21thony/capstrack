<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content" />
<title>Team Management - CapsTrack  </title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
@vite(['resources/student-ui/Styles/team.css', 'resources/css/financial.css', 'resources/js/financial/student.js'])
@include('partials.skeleton-head', ['skReveal' => 'opacity'])
  @include('partials.render-whole')
</head>
<style nonce="{{ Vite::cspNonce() }}">
@keyframes spin { to { transform: rotate(360deg); } }
  .pm-close-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; padding: 0; background-color: var(--bg2); color: var(--text3); border: none; border-radius: 50%; cursor: pointer; transition: all 0.15s ease-in-out; outline: none; }
  .pm-close-btn:hover { background-color: var(--border); color: var(--text); transform: scale(1.05); }
  .tag-input-field { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); min-height: 42px; box-sizing: border-box; cursor: text; }
  #tag-search-input { border: none !important; background: transparent !important; color: var(--text) !important; font-size: 13px !important; outline: none !important; flex: 1 !important; min-width: 60px !important; padding: 4px 0 !important; }
  .custom-tag-chip { display: inline-flex; align-items: center; gap: 6px; background: var(--blue-bg); color: var(--blue); font-size: 12px; font-weight: 500; padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(29,78,216,0.25); animation: tagFadeIn 0.15s ease-in-out; }
  .custom-tag-chip-remove { display: inline-flex; align-items: center; justify-content: center; background: rgba(29,78,216,0.15); border: none; color: var(--blue); border-radius: 50%; width: 14px; height: 14px; cursor: pointer; font-size: 10px; padding: 0; transition: background 0.1s; }
  .custom-tag-chip-remove:hover { background: rgba(29,78,216,0.25); }
  #editTeamModal .edit-team-save-btn { background: var(--bg3) !important; color: var(--text) !important; border: 1px solid var(--border) !important; box-shadow: none !important; }
  #editTeamModal .edit-team-save-btn:hover { background: var(--bg4) !important; transform: none !important; box-shadow: none !important; }
  #editTeamModal .edit-team-cancel-btn { background: var(--navy-dark) !important; color: #fff !important; border: 1px solid var(--navy-dark) !important; }
  #editTeamModal .edit-team-cancel-btn:hover { background: var(--navy-mid) !important; color: #fff !important; }

  [data-theme="dark"] #editTeamModal .edit-team-save-btn { background: #2a2f3a !important; color: #f3f4f6 !important; border-color: rgba(255,255,255,0.12) !important; }
  [data-theme="dark"] #editTeamModal .edit-team-save-btn:hover { background: #343a47 !important; }
  [data-theme="dark"] #editTeamModal .edit-team-cancel-btn { background: #1e222b !important; color: #fff !important; border-color: rgba(255,255,255,0.08) !important; }
  [data-theme="dark"] #editTeamModal .edit-team-cancel-btn:hover { background: #262b36 !important; }
  /* Project tags in Update Team: the title's tags as chips, and a list to add from. */
  #editTeamModal .ut-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
  #editTeamModal .ut-tags .selected-tag { padding: 5px 6px 5px 10px; font-size: 12px; }
  #editTeamModal .ut-x { width: 18px; height: 18px; display: inline-grid; place-items: center; border: 0; border-radius: 50%; background: transparent; color: inherit; font-size: 14px; line-height: 1; cursor: pointer; opacity: .65; padding: 0; }
  #editTeamModal .ut-x:hover { opacity: 1; background: rgba(29,78,216,.12); }
  #editTeamModal .ut-x[hidden] { display: none; }
  #editTeamModal .ut-add { width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px dashed var(--border2, var(--border)); background: var(--bg2); color: var(--text2); font: inherit; font-size: 12.5px; cursor: pointer; box-sizing: border-box; }
  #editTeamModal .ut-add:disabled { cursor: not-allowed; opacity: .6; }
  #editTeamModal .ut-msg { margin-top: 6px; font-size: 11.5px; color: var(--red); }
  #editTeamModal .ut-msg[hidden] { display: none; }
  #editTeamModal .ut-note { margin-top: 6px; font-size: 11px; color: var(--text3); }
  #editTeamModal .ut-none { font-size: 12px; color: var(--amber, #b45309); padding: 4px 0; }
  #editTeamModal .ut-title { display: block; width: 100%; min-height: 58px; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); font: inherit; font-size: 13px; font-weight: 600; line-height: 1.5; resize: vertical; box-sizing: border-box; }
  #editTeamModal .ut-title:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(201,168,44,.18); }
  #editTeamModal .ut-empty { padding: 10px 12px; border-radius: 8px; border: 1px dashed var(--border); background: var(--bg3); font-size: 12px; line-height: 1.5; color: var(--text3); }

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

  [data-theme="dark"] #editTeamModal div[style*="background:#fef3c7"] { background: rgba(245,158,11,0.12) !important; border-color: rgba(245,158,11,0.25) !important; color: var(--amber) !important; }
  [data-theme="dark"] #editTeamModal div[style*="color:#6b7280"] { color: var(--text2) !important; }

  .tag-suggestions-box { position: absolute; bottom: 100%; left: 0; width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; max-height: 160px; overflow-y: auto; z-index: 10; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 4px;scrollbar-width:none;-ms-overflow-style:none}
.tag-suggestions-box::-webkit-scrollbar{display:none}
  .tag-suggestion-item { padding: 10px 14px; font-size: 13px; color: var(--text); cursor: pointer; transition: background 0.1s; }
  .tag-suggestion-item:hover { background: var(--bg2); color: var(--blue); }
  @keyframes tagFadeIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
  @keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .sk-overlay { position: absolute; inset: 0; background: var(--bg); z-index: 10; }
  .sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }
  .content { position: relative; }
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
  .modal-tutorial.is-closed{background:var(--red-bg);border-color:rgba(220,38,38,.3);color:var(--red)}
  /* A long post must not push the box past the top of the screen. */
  #postModal .modal-card{max-height:calc(100vh - 32px);max-height:calc(100dvh - 32px);overflow-y:auto}
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
  .feed-post-skills{font-size:11.5px;color:var(--text2);line-height:1.5}
  .feed-post-skills b{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--text3);margin-right:6px}
  .feed-filters{display:flex;gap:8px;flex-wrap:wrap}
  .skill-pick{max-height:200px;overflow:auto;display:grid;gap:10px;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg2);margin-bottom:25px}
  .skill-pick-group b{display:block;font-size:11px;color:var(--text3);margin-bottom:4px}
  .skill-pick-group div{display:flex;flex-wrap:wrap;gap:4px 14px}
  .skill-pick label{display:flex;gap:6px;align-items:center;font-size:12px;color:var(--text);cursor:pointer}
  .feed-empty{grid-column:1 / -1;text-align:center;padding:36px;color:var(--text3);font-size:13px;border:1px dashed var(--border2);border-radius:12px}
  .goal-lead{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#16a34a}
  .goal-member{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#3b82f6}

  /* ── Post Sections Responsive ── */
  /* ── Compact Feed & Unified Dashboard ── */
  .compact-feed .feed-post-card { padding: 10px; gap: 8px; border-radius: 10px; border: 1px solid var(--border); box-shadow: 0 1px 2px rgba(0,0,0,0.03); transition: all 0.15s; }
  .compact-feed .feed-post-card:hover { border-color: var(--border2); box-shadow: 0 1px 4px rgba(0,0,0,0.05); }
  .compact-feed .feed-post-card .avatar-ring { width: 36px; height: 36px; margin: 0; }
  .compact-feed .feed-post-card .avatar-ring .avatar-text { font-size: 10px; }
  .compact-feed .feed-post-card .feed-post-head .name-label { font-size: 11px; }
  .compact-feed .feed-post-card .goal-label { font-size: 7.5px; padding: 1px 6px; }
  .compact-feed .feed-post-card .feed-post-text { font-size: 10.5px; }
  .compact-feed .feed-post-card .expert-tag { font-size: 7px; padding: 1px 4px; }

  /* ── Team Dashboard Grid ── */
  .team-dashboard-grid { gap: 12px; }
  /* The members and the overview sit side by side on a desktop; on a phone
     the overview goes under the members. !important: the columns are set
     inline on the element. */
  @media (max-width: 768px) {
    .team-dashboard-grid { grid-template-columns: minmax(0, 1fr) !important; }
    /* These two sit outside the scrolling area, so on a phone they stayed
       pinned over the bottom of the page above the tab bar. */
    .main > .tutorial-note, .main > .legal-credits { display: none; }
  }
  .team-dashboard-grid .team-members-panel,
  .team-dashboard-grid .team-overview-panel { border-radius: 14px; padding: 14px; }
  .team-actions-bar { margin-top: 14px; }
  .team-leave-panel { padding: 14px; }
  .team-dashboard-toggle { opacity: 0.5; }
  .team-dashboard-toggle:hover { opacity: 1; color: var(--text); }
  .team-dashboard-body { transition: opacity 0.2s; }

  /* ── Main Content Grid ── */
  .main-content .team-container { gap: 12px; }
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
  /* ---- Team | Financial Records ----
     Two tabs on one page. The team half is the page as it always was; the
     body's class hides it while Financial Records is showing, rather than
     moving a thousand lines of markup into a wrapper. */
  .tm-toprow { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin: 0 0 16px; }
  .tm-toprow-end { display: none; }
  body.tm-on-fin .tm-toprow-end { display: flex; }
  .tm-seg { display: flex; gap: 4px; padding: 4px; margin: 0; background: var(--bg4, #eceef2); border-radius: 12px; width: fit-content; max-width: 100%; }
  .tm-seg button { display: inline-flex; align-items: center; gap: 8px; min-height: 38px; padding: 0 16px; border-radius: 9px; border: 0; background: transparent; color: var(--text2); font: 600 13.5px 'DM Sans', 'DM Sans Fallback', sans-serif; cursor: pointer; white-space: nowrap; }
  .tm-seg button svg { width: 16px; height: 16px; flex-shrink: 0; }
  .tm-seg button:hover { color: var(--text); }
  .tm-seg button[aria-selected="true"] { background: var(--bg2); color: var(--text); box-shadow: 0 1px 3px rgba(15, 23, 42, .12); }
  .tm-seg button:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
  .tm-seg .n { min-width: 20px; height: 20px; padding: 0 6px; border-radius: 10px; display: inline-grid; place-items: center; font-size: 11.5px; font-weight: 700; background: var(--bg3); color: var(--text2); }
  .tm-seg .n.hot { background: var(--red, #dc2626); color: #fff; }
  body.tm-on-fin #contentWrapper,
  body.tm-on-fin .pagination-container,
  body.tm-on-fin .tutorial-note { display: none !important; }
  /* The credits sit at the foot of the screen when a tab is short, and
     right after the content when it is long: .tm-push takes the room
     between, never less than 24px. */
  .main > .content { display: flex; flex-direction: column; }
  .main > .content > * { flex-shrink: 0; }
  .main > .content > .tm-push { flex: 1 0 24px; }
  .main > .content > .sys-credits { margin-top: 0 !important; }
  /* Financial Records was a page of its own that filled the window; inside a
     tab it sits in the page's flow like the team half does. */
  .tm-fin .fx { flex: none; min-height: 0; overflow: visible; background: transparent; }
  @media (max-width: 768px) {
    .tm-seg { width: 100%; }
    .tm-seg button { flex: 1; justify-content: center; padding: 0 10px; }
  }
</style>
<body class="{{ ['financial' => 'tm-on-fin', 'rating' => 'tm-on-rating'][$tab] ?? '' }}">
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

    <div class="tm-toprow">
    <div class="tm-seg" role="tablist" aria-label="Team Management sections">
      <button type="button" role="tab" id="tm-tab-team" data-tm-tab="team" aria-selected="{{ $tab === 'team' ? 'true' : 'false' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Team
      </button>
      <button type="button" role="tab" id="tm-tab-financial" data-tm-tab="financial" aria-controls="tm-panel-financial" aria-selected="{{ $tab === 'financial' ? 'true' : 'false' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l3-2 3 2 3-2 3 2 3-2 1 .7V2l-1 .7-3-2-3 2-3-2-3 2-3-2z"/><path d="M8 7h8M8 11h8M8 15h5"/></svg>
        Financial Records
      </button>
      <button type="button" role="tab" id="tm-tab-rating" data-tm-tab="rating" aria-controls="tm-panel-rating" aria-selected="{{ $tab === 'rating' ? 'true' : 'false' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
        Team Rating
        @if ($rating['due'] > 0)<span class="n hot" title="{{ $rating['due'] }} due" aria-label="{{ $rating['due'] }} due">{{ $rating['due'] }}</span>@endif
      </button>
    </div>
    {{-- Financial Records puts its fee tabs here, shown only on that tab. --}}
    <div class="tm-toprow-end" id="tm-toprow-end"></div>
    </div>

    <section class="tm-fin" id="tm-panel-financial" role="tabpanel" aria-labelledby="tm-tab-financial" @if ($tab !== 'financial') hidden @endif>
      @include('student.partials.financial-panel', ['state' => $financialState])
    </section>
    <section id="tm-panel-rating" role="tabpanel" aria-labelledby="tm-tab-rating" @if ($tab !== 'rating') hidden @endif>
      @include('student.team-rating._panel')
    </section>
    <script nonce="{{ Vite::cspNonce() }}">
    (function () {
      var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-tm-tab]'));
      var panels = { financial: document.getElementById('tm-panel-financial'), rating: document.getElementById('tm-panel-rating') };

      // Switches in place, and keeps the address in step so a refresh or a
      // shared link opens the same tab.
      function show(which) {
        document.body.classList.toggle('tm-on-fin', which === 'financial');
        document.body.classList.toggle('tm-on-rating', which === 'rating');
        Object.keys(panels).forEach(function (k) { panels[k].hidden = k !== which; });
        tabs.forEach(function (b) { b.setAttribute('aria-selected', b.getAttribute('data-tm-tab') === which ? 'true' : 'false'); });
        var url = new URL(location.href);
        if (panels[which]) { url.searchParams.set('tab', which); } else { url.searchParams.delete('tab'); }
        history.replaceState(null, '', url.pathname + url.search + url.hash);
        // The first time a tab is opened since signing in, it comes up as
        // its skeleton (student/partials/content-skeleton), like a page.
        if (window.contentSkeleton) { window.contentSkeleton.show(which); }
      }

      if (window.contentSkeleton) { window.contentSkeleton.seen('{{ $tab }}'); }

      tabs.forEach(function (b, i) {
        b.addEventListener('click', function () { show(b.getAttribute('data-tm-tab')); });
        b.addEventListener('keydown', function (e) {
          if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') { return; }
          var next = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
          next.focus();
          show(next.getAttribute('data-tm-tab'));
        });
      });
    })();
    </script>

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
    {{-- A form on this page can submit from either the team card up here or
         the recruitment feed further down - Send to CaPSA/TSA, Update Team, a
         recruitment post, an invite response. Wherever it came from, the
         redirect lands back at the top of the page, so the confirmation
         belongs here too. It used to sit after the team card and before the
         feed, which put it off-screen below the fold for the one action
         (asking to leave, or to remove a member) that opens furthest from
         it - a request that had gone through read as if nothing had
         happened. --}}
    @if($errors->any())
      <div class="alert" style="background: #fef2f2; border: 1px solid #fecaca; color: #ef4444; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px;">
        @foreach($errors->all() as $error)
          <div>{{ $error }}</div>
        @endforeach
      </div>
    @endif

    {{-- LeaveRequestController::refuse() (and several other controllers that
         redirect back here) flash the reason under 'error', not 'status' -
         a request the CaPSA/TSA feature turned down (already pending, team not
         locked yet, a leader trying to leave without handing off first) had
         nothing on screen saying so, which is indistinguishable from the
         request never having been sent at all. --}}
    @if(session('error'))
      <div class="alert" style="background: #fef2f2; border: 1px solid #fecaca; color: #ef4444; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px;">
        {{ session('error') }}
      </div>
    @endif

    @if(session('status'))
      <div class="alert" style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px;">
        {{ session('status') }}
      </div>
    @endif

    @if($myTeam)
      @include('student.team._team')
    @endif

    <!-- SCREEN: no team / the team's own cards above Team selection -->
    <main class="main-content">
      @if (! $myTeam)
        @include('student.team._no-team')
      @else
        @include('student.team._team-extras')
      @endif
    </main>

    @include('student.team._selection')
    <script nonce="{{ Vite::cspNonce() }}">
      // The room between a short tab and the credits (see .tm-push above).
      (function () {
        var content = document.querySelector('.main > .content');
        if (content) { var push = document.createElement('div'); push.className = 'tm-push'; push.setAttribute('aria-hidden', 'true'); content.appendChild(push); }
      })();
    </script>

   {{-- The developers' credit comes from partials/mobile-tabbar, which puts
        it last on the page and holds it at the foot of the screen. A copy of
        its own here was left where it stood, halfway up a short page. --}}

  <div class="modal-overlay" id="detailModal" onclick="if(event.target==this) this.classList.remove('active')">
      <div class="card" style="width: 380px; padding: 28px; position: relative;">
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
              <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 8px; letter-spacing: 0.5px;">Roles</div>
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
        <p id="modal-note-text">Your team can have up to {{ $memberCount }} members including you (the leader). Slots are filled automatically as members join.</p>
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
      <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 8px;">Roles</label>
      {{-- What the post names decides what the joining student ends up as:
           one role is given to them on accept, two or more and they pick
           between exactly these. --}}
      <p style="margin:-2px 0 8px;font-size:11px;color:var(--text3);line-height:1.5">
        Leader, Member, or a role marked Primary or Secondary. Name one role and whoever joins becomes that; name both and they choose.
      </p>
      <div class="tag-wrapper-box" style="position: relative; margin-bottom: 25px;">
        <div class="tag-input-field" id="tag-container" onclick="document.getElementById('tag-search-input').focus()">
          <input type="text" id="tag-search-input" placeholder="" oninput="filterTagSuggestions()" onfocus="filterTagSuggestions()">
        </div>
        <div class="tag-suggestions-box" id="tag-suggestions" style="display: none;"></div>
      </div>
      {{-- The same catalogue experts pick from on their profile, narrowed to
           the student's programme; grammar is the grammarian's, not a member's. --}}
      <label id="skills-label" style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 8px;">Skills we need</label>
      <p style="margin:-2px 0 8px;font-size:11px;color:var(--text3);line-height:1.5">Optional. The same list experts pick from on their profile.</p>
      <div class="skill-pick" id="skill-pick">
        @foreach(collect(\App\Support\SkillCatalog::groupsForPrograms(array_filter([$mySchoolYear?->program])))->except('Language & Editing (Grammarian)') as $group => $items)
          <div class="skill-pick-group">
            <b>{{ \Illuminate\Support\Str::before($group, ' (') }}</b>
            <div>
              @foreach($items as $skill)
                <label><input type="checkbox" value="{{ $skill }}"> {{ $skill }}</label>
              @endforeach
            </div>
          </div>
        @endforeach
      </div>
      <div class="modal-actions modal-actions--dual" style="display:grid;grid-template-columns: 1fr 1fr;gap:12px;">
        <form method="POST" action="{{ route('recruitment.store') }}" style="width:100%;" id="recruitment-form" onsubmit="publishPost(event)">
          @csrf
          <input type="hidden" name="_method" id="form-method-input" value="">
          <input type="hidden" name="recruitment_goal" id="recruitment-goal-input" value="build_a_team">
          <input type="hidden" name="looking_for" id="looking-for-input" value="">
          <input type="hidden" name="introduction" id="introduction-input" value="">
          <input type="hidden" name="team_name" id="team-name-hidden" value="">
          {{-- Posted when a team is created. It is the CaPSA/TSA's number, not a
               fixed 5 - the column is only a record, since the cap itself is
               read from the setting. --}}
          <input type="hidden" name="member_count" id="member-count-input" value="{{ $memberCount }}">
          <input type="hidden" name="tags" id="tags-hidden-input" value="">
          <input type="hidden" name="skills" id="skills-hidden-input" value="[]">
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

@if($myTeam)
{{-- Leave request: leaving, or (after Title Defense) removing a member --}}
<div class="modal-overlay" id="capsaLeaveModal" onclick="if(event.target==this) closeCapsaLeaveModal()">
  <div class="card modal-card modal-card--team" role="dialog" aria-modal="true" aria-labelledby="rrm-title" style="width: 460px; padding: 30px; position: relative;">
    <button type="button" onclick="closeCapsaLeaveModal()" class="pm-close-btn" aria-label="Close" style="position: absolute; top: 20px; right: 20px;">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div style="width:48px;height:48px;border-radius:50%;background:var(--bg2);color:var(--accent3);display:flex;align-items:center;justify-content:center;margin-bottom:14px;">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    </div>
    <div class="section-title" id="rrm-title">Ask your CaPSA/TSA</div>
    <p id="rrm-lede" style="margin-top:8px;font-size:13px;color:var(--text2);line-height:1.6;"></p>
    <form method="POST" action="{{ route('team.capsa-leave-requests.store') }}" id="capsa-leave-form" novalidate style="margin-top:14px;">
      @csrf
      <input type="hidden" name="type" id="rrm-type">
      <input type="hidden" name="student_id" id="rrm-student">
      <label for="rrm-reason" style="display:block;font-size:12px;font-weight:600;color:var(--text);margin-bottom:6px;">Reason for your CaPSA/TSA</label>
      <textarea id="rrm-reason" name="reason" rows="4" minlength="{{ \App\Models\MemberLeaveRequest::REASON_MIN }}" maxlength="1000" required placeholder="What happened, and why it cannot be worked out within the team" style="width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid var(--border);border-radius:10px;background:var(--bg2);color:var(--text);font:inherit;font-size:13px;line-height:1.5;resize:vertical;"></textarea>
      <div style="display:flex;justify-content:space-between;gap:10px;font-size:11px;color:var(--text3);margin-top:4px;">
        <span id="rrm-hint">At least {{ \App\Models\MemberLeaveRequest::REASON_MIN }} characters. Your team sees this too.</span>
        <span id="rrm-count" style="white-space:nowrap;">0 / 1000</span>
      </div>
      <div id="rrm-error" role="alert" style="display:none;margin-top:6px;font-size:12px;color:#ef4444;"></div>
      <ul id="rrm-effects" style="margin:12px 0 0;padding-left:18px;font-size:12px;color:var(--text2);line-height:1.7;"></ul>
      <div style="display:flex;gap:10px;margin-top:18px;">
        <button type="submit" class="btn-primary" id="rrm-submit" style="flex:1;">Send to CaPSA/TSA</button>
        <button type="button" class="google-btn" style="flex:1;border:none;" onclick="closeCapsaLeaveModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>
@endif

@if($myTeam && $isLeader)
{{-- Edit Team Modal --}}
<div class="modal-overlay" id="editTeamModal" onclick="if(event.target==this) closeEditTeamModal()">
  <div class="card modal-card modal-card--team" style="width: 460px; padding: 30px; border: 1px solid var(--accent); position: relative;">
    <button type="button" onclick="closeEditTeamModal()" class="pm-close-btn" aria-label="Close" style="position: absolute; top: 20px; right: 20px;">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div class="section-title">Update Team</div>
    <form method="POST" action="{{ route('team.update', $myTeam->team_id) }}" style="margin-top: 20px;" onsubmit="return validateForm(event)">
      @csrf
      @method('PUT')
      <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 8px;">Team Name</label>
      <input type="text" name="team_name" id="edit-team-name" value="{{ $myTeam->team_name }}" placeholder="Enter team name..." autocomplete="off" style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg2);color:var(--text);font-size:13px;margin-bottom:0;box-sizing:border-box;">
      <div id="edit-team-name-msg" style="font-size: 11px; color: var(--red); margin: 6px 0 0; display: none;"></div>
      {{-- The title comes from the pre-proposal the CaPSA/TSA approves (and, when
           two are, the one the team picks). Once it is set the leader can
           correct it here - a misspelling, a word or two. A reworded title
           needs the panel's "passed with revision"; a different one goes
           through the CaPSA/TSA (TitleRevision). --}}
      <label for="edit-project-title" style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin: 16px 0 8px;">Project Title</label>
      @if ($myTeam->project_title)
        <textarea name="project_title" id="edit-project-title" class="ut-title" rows="2" maxlength="255" required
                  data-saved="{{ $myTeam->project_title }}">{{ old('project_title', $myTeam->project_title) }}</textarea>
        @error('project_title')<div class="ut-msg">{{ $message }}</div>@enderror
        <div class="ut-note" style="margin-bottom:16px;">
          @if ($titleCanBeReworded)
            Your panel passed the title with revision, so you can reword it until the Signed Final Project Title Form is in.
          @else
            Fix spelling or small mistakes. A different title goes through your CaPSA/TSA.
          @endif
        </div>
      @else
        <div style="padding:10px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg3);font-size:13px;margin-bottom:16px;box-sizing:border-box;line-height:1.5;">
          @if ($approvedProposalCount >= 2)
            <span style="color:var(--text2);">{{ $approvedProposalCount }} of your titles were approved &mdash; choose the one you want on the <a href="{{ route('student.duplication') }}" style="color:var(--accent3);font-weight:600;">Duplication Checker</a> page.</span>
          @else
            <span style="color:var(--text3);">Set once your CaPSA/TSA approves one of your pre-proposal titles.</span>
          @endif
        </div>
      @endif

      {{-- The capstone title's own tags - the ones the team picked on the
           pre-proposal that became its title. They come filled in; the leader
           adds from the same fixed list or removes, and a title always keeps
           at least one. Before there is a title there is nothing to tag. --}}
      @php $hasTitle = $teamHasTitle; @endphp
      <div id="tagsSection" style="margin-bottom:20px;">
        <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text3);margin-bottom:8px;">Project Tags</label>
        @if ($hasTitle)
          <input type="hidden" name="tags" id="tagsHiddenInput" value="{{ json_encode($titleTags) }}" data-saved="{{ json_encode($titleTags) }}">
          <div id="selectedTagsDisplay" class="ut-tags" aria-live="polite"></div>
          <select id="tagAddSelect" class="ut-add" aria-label="Add a tag" onchange="addProjectTag(this.value)">
            <option value="">+ Add a tag…</option>
            @foreach (\App\Support\ProjectTags::OPTIONS as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
          @php $tagsError = $errors->first('tags') ?: $errors->first('tags.*'); @endphp
          <div id="tagsMsg" class="ut-msg" role="alert" @if (! $tagsError) hidden @endif>{{ $tagsError }}</div>
          <div class="ut-note">From your capstone title. Keep at least {{ \App\Support\ProjectTags::MIN }}, up to {{ \App\Support\ProjectTags::MAX }}.</div>
        @else
          <div class="ut-empty">Tags come with your capstone title. They appear here once your CaPSA/TSA approves one of your pre-proposal titles, and you can add or remove them then.</div>
        @endif
      </div>
      <div class="modal-actions modal-actions--dual" style="display:grid;grid-template-columns: 1fr 1fr;gap:12px;">
        <button type="submit" class="btn-primary edit-team-save-btn" style="width:100%;">Save Changes</button>
        <button type="button" class="google-btn edit-team-cancel-btn" style="width:100%;border:none;background:transparent;" onclick="closeEditTeamModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>
@endif

{{-- Open Slot Invite Modal --}}
<div class="modal-overlay" id="openSlotInviteModal" onclick="if(event.target===this) closeOpenSlotInviteModal()">
  <div class="card modal-card modal-card--team" role="dialog" aria-modal="true" aria-labelledby="osl-title" style="width: 460px; max-width: 100%; padding: 0; border-radius: 14px; overflow: hidden; max-height: 80vh;max-height: 80dvh; display: flex; flex-direction: column; position: relative;">
    <div style="padding: 28px 28px 0; position: relative;">
      <button type="button" onclick="closeOpenSlotInviteModal()" class="pm-close-btn" aria-label="Close" style="position: absolute; top: 18px; right: 18px;">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
      <div style="display: flex; align-items: center; gap: 14px; padding-right: 40px;">
        <span style="width: 48px; height: 48px; border-radius: 50%; background: var(--bg4); color: #8a6500; display: flex; align-items: center; justify-content: center; flex-shrink: 0;" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg>
        </span>
        <div>
          <h2 id="osl-title" style="margin: 0; font-size: 18px; font-weight: 700; color: var(--text);">Invite a classmate</h2>
          <div style="font-size: 13px; color: var(--text3); margin-top: 2px;">Classmates in {{ $sectionLabel }} without a team. They pick their role after joining.</div>
        </div>
      </div>
    </div>

    {{-- Not a scroller. This scrolled AND the list inside it scrolled, so
         the dialog had two bars down its right edge and the search box slid
         away under the header. It lays the search and the list out; the list
         is the only thing that moves. --}}
    <div style="flex: 1; display: flex; flex-direction: column; min-height: 0; overflow: hidden; padding: 18px 28px 20px;">
      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
        <div style="position: relative; flex: 1;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text3); pointer-events: none;"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
          <input type="text" id="open-slot-search" placeholder="Search classmates..." oninput="filterOpenSlotClassmates()" style="width: 100%; padding: 10px 12px 10px 40px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); font-size: 13px; box-sizing: border-box;">
        </div>
      </div>

      <div id="open-slot-classmates-list" style="display: flex; flex-direction: column; gap: 8px; flex: 1; min-height: 0; overflow-y: auto; padding-right: 4px;">
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

    {{-- Sending is one button at the bottom rather than a confirmation that
         fired the moment you touched a name. Picking who to invite and
         deciding to invite them are two different acts, and inviting four
         people should not be four dialogs. --}}
    <div style="padding: 16px 28px 22px; border-top: 1px solid var(--border); display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
      <span id="osl-count" style="font-size: 12px; color: var(--text3); margin-right: auto;">Pick who to invite.</span>
      <button type="button" class="nt-btn-line" style="flex: none; border: 0; background: transparent; min-height: 40px;" onclick="closeOpenSlotInviteModal()">Cancel</button>
      <button type="button" class="nt-btn nt-btn-primary" id="osl-send" disabled
              style="opacity:.5;cursor:not-allowed;" onclick="sendOpenSlotInvites()">Send invite</button>
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
      @method('PUT')
      @csrf
      <div style="display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid var(--border); border-radius: 12px; background: var(--bg3); margin-bottom: 18px;">
        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--accent); color: var(--navy-dark); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; overflow: hidden; flex-shrink: 0;">
          @if(auth()->user()->avatarUrl())
            <img src="{{ auth()->user()->avatarUrl() }}" alt="" referrerpolicy="no-referrer" data-initials="{{ auth()->user()->avatarInitials() }}" onerror="this.replaceWith(this.dataset.initials)" style="width: 100%; height: 100%; object-fit: cover;">
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
                  <img src="{{ $member->avatarUrl() }}" alt="" referrerpolicy="no-referrer" data-initials="{{ $member->avatarInitials() }}" onerror="this.replaceWith(this.dataset.initials)" style="width: 100%; height: 100%; object-fit: cover;">
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
  $ltOldLeader = $previousLeader;
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
            <img src="{{ $ltOldLeader->avatarUrl() }}" alt="" referrerpolicy="no-referrer" data-initials="{{ $ltOldLeader->avatarInitials() }}" onerror="this.replaceWith(this.dataset.initials)" style="width: 100%; height: 100%; object-fit: cover;">
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

      <form method="POST" action="{{ route('team.leadership.update', $pendingLeadershipTransfer->id) }}" style="display:none;" id="leadership-pop-accept-form">
        @method('PATCH')
        <input type="hidden" name="status" value="accepted">
        @csrf
      </form>
      <form method="POST" action="{{ route('team.leadership.update', $pendingLeadershipTransfer->id) }}" style="display:none;" id="leadership-pop-decline-form">
        @method('PATCH')
        <input type="hidden" name="status" value="declined">
        @csrf
      </form>

      <div style="display: flex; gap: 10px;">
        <button type="button" onclick="showConfirm({title:'Accept Transfer',message:'You are about to accept leadership of <strong>' + {{ Js::from(e($ltData['team_name'] ?? 'the team')) }} + '</strong>. You will become the new team leader, and the current leader will be notified.',icon:'team',confirmText:'Yes',cancelText:'No',confirmClass:'success',onConfirm:function(){document.getElementById('leadership-pop-accept-form').submit();}})" style="flex:1;padding:11px 12px;border-radius:9px;border:1px solid var(--green);background:var(--green);color:#fff;font-size:12.5px;font-weight:700;cursor:pointer;transition:all 0.15s;">Approve</button>
        <button type="button" onclick="showConfirm({title:'Decline',message:'You are about to decline leadership of <strong>' + {{ Js::from(e($ltData['team_name'] ?? 'the team')) }} + '</strong>. You will stay a member, and the current leader will be notified.',icon:'request',confirmText:'Yes',cancelText:'No',confirmClass:'danger',onConfirm:function(){document.getElementById('leadership-pop-decline-form').submit();}})" style="flex:1;padding:11px 12px;border-radius:9px;border:1px solid var(--red);background:var(--red-bg);color:var(--red);font-size:12.5px;font-weight:700;cursor:pointer;transition:all 0.15s;">Decline</button>
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

{{-- Choose your role.

     Shown to a member who has joined but has no role yet. That is anybody
     invited directly, and anybody whose team's post named more than one
     thing it was short of - a post naming exactly one role assigns it on
     accept and this never appears.

     It has no close button on purpose. The choice is one click and the page
     behind it is unusable without it: every roster row, and the CaPSA/TSA's own
     verification list, reads this field. --}}
@if(!empty($needsRole) && !empty($roleChoices))
<div class="modal-overlay" id="roleChoiceModal" data-no-escape style="z-index:1200">
  <div class="card modal-card modal-card--team" style="width:460px;max-width:94vw;padding:28px;position:relative">
    <div style="width:46px;height:46px;border-radius:12px;background:var(--accent);display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#1a1a1a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m19 8 2 2 3-3"/></svg>
    </div>
    <div class="section-title" style="text-align:center">Choose your role</div>
    <p style="margin:8px 0 0;font-size:12.5px;color:var(--text3);text-align:center;line-height:1.55">
      You are on <strong style="color:var(--text)">{{ $myTeam->team_name }}</strong>. Pick what you
      will be doing — this is set for the semester, so choose carefully.
    </p>

    <div style="display:flex;flex-direction:column;gap:9px;margin-top:18px">
      @foreach($roleChoices as $choice)
        <button type="button"
                class="rolepick {{ $choice['full'] ? 'is-full' : '' }}"
                @if($choice['full']) disabled @else onclick="pickRole({{ $choice['id'] }}, this)" @endif>
          <span class="rolepick-main">
            <span class="rolepick-name">{{ $choice['name'] }}</span>
            @if($choice['duties'])
              <span class="rolepick-duties">{{ $choice['duties'] }}</span>
            @endif
          </span>
          <span class="rolepick-seats">
            @if($choice['full'])
              Full<br><span style="font-weight:500;opacity:.8">{{ implode(', ', $choice['held_by']) }}</span>
            @else
              {{ $choice['seats'] - $choice['taken'] }} of {{ $choice['seats'] }} free
            @endif
          </span>
        </button>
      @endforeach
    </div>

    {{-- Not one of the buttons above: the course has it played partly by
         every member, so it is already true of whoever is reading this. --}}
    <p class="rolepick-shared">
      <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 18l6-6-6-6"/><path d="M8 6l-6 6 6 6"/></svg>
      {{-- One span: the paragraph is a flex row, and loose text beside the
           <strong> became three narrow columns of their own on a phone. --}}
      <span>Everyone on the team is a <strong>{{ \App\Models\StudentRole::SHARED_ROLE_NAME }}</strong> as
      well — writing and testing the system is shared by all of you.</span>
    </p>

    <form method="POST" action="{{ route('team.role.update') }}" id="role-choice-form" style="display:none">
      @csrf
      @method('PUT')
      <input type="hidden" name="role_id" id="role-choice-input">
    </form>
  </div>
</div>

<style nonce="{{ Vite::cspNonce() }}">
  .rolepick{
    display:flex;align-items:flex-start;gap:12px;width:100%;text-align:left;
    padding:13px 14px;border-radius:11px;cursor:pointer;
    background:var(--bg2);border:1.5px solid var(--border);
    transition:border-color .13s ease, background .13s ease;
  }
  .rolepick:hover:not(.is-full){border-color:var(--accent);background:var(--bg3)}
  .rolepick.is-full{opacity:.5;cursor:not-allowed}
  .rolepick-main{flex:1;min-width:0;display:flex;flex-direction:column;gap:3px}
  .rolepick-name{font-size:13.5px;font-weight:700;color:var(--text);line-height:1.3}
  .rolepick-duties{font-size:11px;color:var(--text3);line-height:1.5}
  .rolepick-seats{
    flex-shrink:0;font-size:10.5px;font-weight:700;color:var(--text3);
    text-align:right;line-height:1.45;padding-top:2px;
  }
  .rolepick-shared{
    display:flex;align-items:flex-start;gap:8px;margin:14px 0 0;
    padding:10px 12px;border-radius:9px;background:var(--bg3);
    font-size:11.5px;line-height:1.55;color:var(--text3);text-align:left;
  }
  .rolepick-shared svg{flex-shrink:0;margin-top:2px;color:var(--text3)}
  .rolepick-shared strong{color:var(--text2);font-weight:700}
</style>

<script nonce="{{ Vite::cspNonce() }}">
// Behind the portal tour, and one at a time with the other pop-ups that
// cover the page (partials/portal-tour).
(function () {
  var roleModal = document.getElementById('roleChoiceModal');
  (window.afterPortalTour || function (fn) { fn(); })(function () { roleModal.classList.add('active'); }, function () { return roleModal.classList.contains('active'); });
})();

function pickRole(roleId, btn) {
  // Every button off at once - a double click was two people's worth of
  // seat, and the answer to the second one is an error message.
  document.querySelectorAll('#roleChoiceModal .rolepick').forEach(function (b) { b.disabled = true; });
  btn.style.borderColor = 'var(--accent)';
  document.getElementById('role-choice-input').value = roleId;
  document.getElementById('role-choice-form').submit();
}
</script>
@endif

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
@php
  $expertRemovedIds = $expertRemovedNotifications->pluck('id')->implode(',');
  // Still to see the portal tour: hidden from the first frame, shown once the tour closes.
  $expertRemovedAfterTour = (bool) auth('web')->user()?->needsPortalTour();
@endphp
<div id="expertRemovedModal" style="position:fixed;inset:0;z-index:99999;display:{{ $expertRemovedAfterTour ? 'none' : 'flex' }};align-items:center;justify-content:center;background:rgba(24,25,31,0.9);backdrop-filter:blur(4px);padding:20px;" data-ids="{{ $expertRemovedIds }}">
  <div style="background:#fff;border-radius:18px;padding:32px 28px 24px;width:100%;max-width:420px;box-shadow:0 24px 60px rgba(0,0,0,0.25);text-align:center;position:relative;animation:expertPopIn 0.25s cubic-bezier(0.16,1,0.3,1);">
    <div style="width:56px;height:56px;border-radius:50%;background:rgba(239,68,68,0.08);color:#ef4444;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
      <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </div>
    <h3 style="font-size:17px;font-weight:700;color:#1e2128;margin:0 0 6px;letter-spacing:-0.3px;">No longer with your team</h3>
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
<style nonce="{{ Vite::cspNonce() }}">
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

<style nonce="{{ Vite::cspNonce() }}">
  .msg-toast{position:fixed;bottom:30px;right:30px;background:var(--navy-dark);color:#fff;padding:14px 22px;border-radius:12px;font-size:13px;font-weight:500;z-index:99999;display:none;align-items:center;gap:10px;box-shadow:0 8px 30px rgba(0,0,0,0.3);animation:slideUp 0.3s ease;max-width:380px}
  .msg-toast.error{border-left:4px solid var(--red)}
  .msg-toast.show{display:flex}
  @keyframes slideUp{from{transform:translateY(16px);opacity:0}to{transform:translateY(0);opacity:1}}
</style>

<div class="msg-toast" id="msg-toast"></div>

{{-- Custom Confirm Modal --}}
<div id="customConfirmModal" style="display:none; position:fixed; inset:0; z-index:99999; align-items:center; justify-content:center; background:rgba(24,25,31,0.9); backdrop-filter:blur(4px); padding:20px;">
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

<script nonce="{{ Vite::cspNonce() }}">
const currentStudentTeamId = {{ $mySchoolYear?->team_id ?? 'null' }};
const currentStudentId = {{ auth()->user()->student_id }};
const myRecruitmentGoal = '{{ $myRecruitmentGoal ?? '' }}';
const isTeamFull = @json($isTeamFull ?? false);
const isPostHidden = @json(isset($postHidden) && $postHidden);
const hasPendingRequest = @json($hasPendingRequest ?? false);
// Who in a team may act on a post: only the leader invites, and only while a seat is free.
const VIEWER_IS_LEADER = @json((bool) ($isLeader ?? false));
const VIEWER_TEAM_FULL = @json((bool) ($isTeamFull ?? false));
let activeTags = [];
let isEditing = false;
let editingId = null;
// Create Post's six choices: Leader, Member, and Primary or Secondary of each
// member role (Recruitment::ROLE_OPTIONS). activeTags holds their keys.
@php $roleOptionList = collect(\App\Models\Recruitment::ROLE_OPTIONS)->map(fn ($o, $key) => ['key' => $key, 'label' => $o['label']])->values(); @endphp
const roleOptions = @json($roleOptionList, JSON_UNESCAPED_SLASHES);

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
  bind('edit-team-name', 'edit-team-name-msg', myTeamId);
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
    @php $teamMemberCount = $teamSize; @endphp
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
    @elseif($isLeader && $teamLocked)
    showToast('Your team passed Title Defense, so it cannot be ended by leaving. Talk to your CaPSA/TSA.', 'error');
    @elseif($isLeader)
    showConfirm({
        title: 'Leave & Terminate Team',
        message: 'You are the only member. Leaving will <strong>permanently terminate</strong> <strong>' + {{ Js::from(e($myTeam->team_name)) }} + '</strong>. This cannot be undone.',
        icon: 'leave',
        confirmText: 'Leave team',
        confirmClass: 'danger',
        onConfirm: function() {
            document.getElementById('leave-team-form').submit();
        }
    });
    @else
    showConfirm({
        title: 'Leave Team',
        message: 'Are you sure you want to leave <strong>' + {{ Js::from(e($myTeam->team_name)) }} + '</strong>?<br><br>\u2022 Your recruitment post will be removed<br>\u2022 You can only rejoin if the leader invites you again<br><br>This action cannot be undone.',
        icon: 'leave',
        confirmText: 'Leave',
        confirmClass: 'danger',
        onConfirm: function() {
            document.getElementById('leave-team-form').submit();
        }
    });
    @endif
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

@if($myTeam)
var LEAVE_REASON_MIN = {{ \App\Models\MemberLeaveRequest::REASON_MIN }};

function openCapsaLeaveModal(type, studentId, name) {
    var leave = type === 'leave';
    document.getElementById('rrm-type').value = type;
    document.getElementById('rrm-student').value = leave ? '' : studentId;
    document.getElementById('rrm-title').textContent = leave ? 'Ask your CaPSA/TSA to leave' : 'Ask your CaPSA/TSA to remove ' + name;
    document.getElementById('rrm-lede').textContent = leave
        ? 'Leaving a team needs your CaPSA/TSA\'s approval. Your CaPSA/TSA reads your reason and decides whether you can leave.'
        : 'Your team passed Title Defense, so you cannot remove members yourself. Your CaPSA/TSA reads your reason and decides.';

    var effects = leave && {{ ($leaderAsksCapsa ?? false) ? 'true' : 'false' }}
        ? ['Once your CaPSA/TSA approves, the team ends and your experts are told.', 'You stay on the team unless your CaPSA/TSA approves.', 'You can withdraw the request while it is waiting.']
        : leave
        ? ['Your team leader is told you asked.', 'You stay on the team unless your CaPSA/TSA approves.', 'You can withdraw the request while it is waiting.']
        : [name + ' is told you asked, and sees your reason.', name + ' stays on the team unless your CaPSA/TSA approves.', 'No one can be added to replace them.'];
    var list = document.getElementById('rrm-effects');
    list.innerHTML = '';
    effects.forEach(function (text) {
        var li = document.createElement('li');
        li.textContent = text;
        list.appendChild(li);
    });

    var reason = document.getElementById('rrm-reason');
    reason.value = '';
    showCapsaLeaveError('');
    var btn = document.getElementById('rrm-submit');
    btn.disabled = false;
    btn.textContent = 'Send to CaPSA/TSA';
    updateCapsaLeaveReason();
    document.getElementById('capsaLeaveModal').classList.add('active');
    reason.focus();
}

function closeCapsaLeaveModal() {
    document.getElementById('capsaLeaveModal').classList.remove('active');
}

// Send stays clickable. It used to be disabled until the reason was long
// enough, but nothing drew it as disabled - so a click on a short reason did
// nothing at all and read as the request being broken. Now the click says
// what is missing, right under the box.
function showCapsaLeaveError(message) {
    var box = document.getElementById('rrm-error');
    box.textContent = message;
    box.style.display = message ? 'block' : 'none';
    document.getElementById('rrm-reason').style.borderColor = message ? '#ef4444' : '';
}

function updateCapsaLeaveReason() {
    var n = document.getElementById('rrm-reason').value.trim().length;
    document.getElementById('rrm-count').textContent = n + ' / 1000';
    if (n >= LEAVE_REASON_MIN) showCapsaLeaveError('');
}

document.getElementById('rrm-reason').addEventListener('input', updateCapsaLeaveReason);
document.getElementById('capsa-leave-form').addEventListener('submit', function (event) {
    var reason = document.getElementById('rrm-reason');
    var n = reason.value.trim().length;
    if (n < LEAVE_REASON_MIN) {
        event.preventDefault();
        showCapsaLeaveError(n === 0
            ? 'Write a reason for your CaPSA/TSA first.'
            : 'Add a little more - at least ' + LEAVE_REASON_MIN + ' characters (' + (LEAVE_REASON_MIN - n) + ' to go).');
        reason.focus();
        return;
    }
    var btn = document.getElementById('rrm-submit');
    btn.disabled = true;
    btn.textContent = 'Sending…';
});
@endif

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
    // PUT, because that is what the route answers. This was a POST and
     // pressing Later on a leadership transfer crashed the page the same way
     // the invitation popup's did.
    fetch('{{ route('team.leadership.remind-later', $pendingLeadershipTransfer->id) }}', {
        method: 'PUT',
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
        message: 'You are about to transfer leadership of <strong>' + {{ Js::from(e($myTeam?->team_name ?? 'your team')) }} + '</strong> to <strong>' + name + '</strong>. They must approve it before becoming the leader.',
        icon: 'team',
        confirmText: 'Review & Transfer',
        confirmClass: 'primary',
        onConfirm: function() { document.querySelector('#transferModal form').submit(); }
    });
}

function openEditTeamModal() {
    document.getElementById('editTeamModal').classList.add('active');
}

function closeEditTeamModal() {
    document.getElementById('editTeamModal').classList.remove('active');
}
@endif

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function setAvatar(avatarEl, url, name) {
    if (url) {
        avatarEl.innerHTML = '<img src="' + url + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" referrerpolicy="no-referrer" onerror="this.remove()">';
    } else {
        avatarEl.innerHTML = '';
        avatarEl.textContent = (name || 'U').charAt(0).toUpperCase();
    }
}

var declineInvitationId = null;

function openDeclineModal(invitationId, name, type, teamName, avatarUrl, email, info) {
    declineInvitationId = invitationId;
    document.getElementById('dm-name').textContent = name;
    document.getElementById('dm-email').textContent = email || 'No email provided';
    document.getElementById('dm-info').textContent = info || '';
    setAvatar(document.getElementById('dm-avatar'), avatarUrl, name);

    var teamLabel = teamName ? ' <strong style="color:var(--text);">' + escapeHtml(teamName) + '</strong>' : '';
    document.getElementById('dm-message').innerHTML =
        'Are you sure you want to <strong style="color:#ef4444;">decline</strong>' + (type === 'invite' ? ' the invitation to join' + teamLabel + '?' : ' <strong style="color:var(--text);">' + escapeHtml(name) + '</strong>\'s request to join your team?') +
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

// Which Capstone is running, so the page can say why rather than opening a
// list nobody can be invited from.
const CAPSTONE_STAGE = {{ $capstoneStage }};
// The CaPSA/TSA's Team Rules. Checked the moment somebody starts to create, join
// or invite - never after they have filled in a form.
//
// Unconditional even though most of what follows is leader-only: updateModalUI()
// and the DOMContentLoaded handler below read these for every visitor, leader
// or not, team or none. They used to live inside the leader-only block below,
// gated on team leadership, so a plain member's browser threw "CREATION_ALLOWED
// is not defined" the moment the page tried to open the recruitment modal on
// load - which aborted that same handler before it ever reached the skeleton
// fade-out code further down, leaving the loading skeleton on screen forever
// for anyone who was not their team's leader.
const CREATION_ALLOWED = @json($creationAllowed);
const JOINING_ALLOWED = @json($joiningAllowed);
const CREATION_CLOSED_MSG = @json(\App\Models\Team::CREATION_CLOSED);
const JOINING_CLOSED_MSG = @json(\App\Models\Team::JOINING_CLOSED);

@if($myTeam && $isLeader)
const stopWords = new Set(['a','an','the','and','or','but','in','on','at','to','for',
    'of','by','with','from','is','are','was','were','be','been','being','have','has',
    'had','do','does','did','will','would','can','could','shall','should','may','might',
    'must','about','above','after','again','all','also','any','as','before','between',
    'both','each','few','more','most','other','some','such','no','nor','not','only',
    'own','same','so','than','that','then','there','these','they','this','those',
    'through','too','very','etc','up','down','out','off','over','under','here','what',
    'when','where','why','how','which','who','whom','whose']);

function autoExpand(el) {
    el.style.height = 'auto';
    el.style.height = Math.max(el.scrollHeight, 42) + 'px';
}

function countMeaningfulWords(text) {
    if (!text.trim()) return 0;
    return text.trim().split(/\s+/).filter(function(w) {
        var cleaned = w.replace(/[^a-zA-Z0-9']/g, '');
        return cleaned.length > 0 && !stopWords.has(cleaned.toLowerCase());
    }).length;
}

// ── Project tags in Update Team ─────────────────────────────────────
// The capstone title's own tags, from the fixed list the pre-proposal form
// uses. At least one always stays: the last chip cannot be removed.
var TAG_MIN = {{ \App\Support\ProjectTags::MIN }};
var TAG_MAX = {{ \App\Support\ProjectTags::MAX }};

function currentProjectTags() {
    var input = document.getElementById('tagsHiddenInput');
    if (!input) return [];
    try { return JSON.parse(input.value || '[]'); } catch (e) { return []; }
}

function showTagsMsg(text) {
    var msg = document.getElementById('tagsMsg');
    if (!msg) return;
    msg.textContent = text || '';
    msg.hidden = !text;
}

// Draws the chips and the "Add a tag" list from the hidden input, so the
// two can never disagree.
function renderProjectTags(tags) {
    var input = document.getElementById('tagsHiddenInput');
    var display = document.getElementById('selectedTagsDisplay');
    var select = document.getElementById('tagAddSelect');
    if (!input || !display) return;

    input.value = JSON.stringify(tags);
    display.textContent = '';
    if (!tags.length) {
        var none = document.createElement('span');
        none.className = 'ut-none';
        none.textContent = 'No tags yet — add at least one.';
        display.appendChild(none);
    }
    tags.forEach(function (tag) {
        var chip = document.createElement('span');
        chip.className = 'selected-tag';
        chip.setAttribute('data-tag', tag);
        chip.appendChild(document.createTextNode(tag));
        var x = document.createElement('button');
        x.type = 'button';
        x.className = 'ut-x';
        x.setAttribute('aria-label', 'Remove ' + tag);
        x.textContent = '×';
        x.hidden = tags.length <= TAG_MIN;
        x.addEventListener('click', function () { removeProjectTag(tag); });
        chip.appendChild(x);
        display.appendChild(chip);
    });

    if (select) {
        Array.prototype.forEach.call(select.options, function (opt) {
            if (opt.value) opt.hidden = opt.disabled = tags.indexOf(opt.value) !== -1;
        });
        select.value = '';
        select.disabled = tags.length >= TAG_MAX;
        select.options[0].textContent = tags.length >= TAG_MAX ? 'Up to ' + TAG_MAX + ' tags' : '+ Add a tag…';
    }
}

function addProjectTag(tag) {
    var tags = currentProjectTags();
    if (!tag || tags.indexOf(tag) !== -1) { renderProjectTags(tags); return; }
    if (tags.length >= TAG_MAX) { showTagsMsg('Pick at most ' + TAG_MAX + ' tags.'); renderProjectTags(tags); return; }
    tags.push(tag);
    showTagsMsg('');
    renderProjectTags(tags);
}

function removeProjectTag(tag) {
    var tags = currentProjectTags();
    if (tags.length <= TAG_MIN) { showTagsMsg('Keep at least one tag on your capstone title.'); return; }
    tags = tags.filter(function (t) { return t !== tag; });
    showTagsMsg('');
    renderProjectTags(tags);
}

function validateForm(event) {
    var editName = document.getElementById('edit-team-name');
    if (editName && editName.dataset.nameTaken === '1') {
        event.preventDefault();
        showToast('This team name is already taken.', 'error');
        editName.focus();
        return false;
    }
    if (document.getElementById('tagsHiddenInput') && currentProjectTags().length < TAG_MIN) {
        event.preventDefault();
        showTagsMsg('Keep at least one tag on your capstone title.');
        return false;
    }
    return true;
}

// Opening the modal starts from what is saved, so a cancelled edit is gone -
// except right after the server refused the title, when what was typed stays.
var keepTypedTitle = @json($errors->has('project_title'));
var keepTagsMsg = @json($errors->has('tags') || $errors->has('tags.*'));
var origOpenTeamModal = openEditTeamModal;
openEditTeamModal = function() {
    origOpenTeamModal();
    var title = document.getElementById('edit-project-title');
    if (title && !keepTypedTitle) title.value = title.dataset.saved || '';
    keepTypedTitle = false;
    var input = document.getElementById('tagsHiddenInput');
    if (input) {
        var saved = [];
        try { saved = JSON.parse(input.dataset.saved || '[]'); } catch (e) {}
        if (!keepTagsMsg) showTagsMsg('');
        keepTagsMsg = false;
        renderProjectTags(saved);
    }
};

// A refused title or tag list reopens the modal, with the reason under the field.
@if ($errors->hasAny(['project_title', 'tags']))
document.addEventListener('DOMContentLoaded', function () { openEditTeamModal(); });
@endif

// Cancelling an invitation is declining it - the same endpoint the invited
// student's own Decline button uses, so there is one way for an invitation to
// end rather than two that could drift apart.
function cancelPendingInvite(invitationId, name) {
    showConfirm({
        title: 'Cancel invitation',
        message: 'Cancel the invitation sent to <strong>' + escapeHtml(name) + '</strong>? They will not be able to accept it.',
        icon: 'invite',
        confirmText: 'Cancel invite',
        confirmClass: 'danger',
        onConfirm: function () {
            var csrf = document.querySelector('meta[name="csrf-token"]');

            fetch('{{ route("invitations.update", ":id") }}'.replace(':id', invitationId), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '{{ csrf_token() }}'
                },
                body: JSON.stringify({ status: 'declined' })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    showToast('Invitation cancelled.', 'success');
                    location.reload();
                } else {
                    showToast(data.message || 'Could not cancel the invitation.', 'error');
                }
            })
            .catch(function () {
                showToast('Could not cancel the invitation. Please try again.', 'error');
            });
        }
    });
}

function openOpenSlotInviteModal() {
    // The button is hidden in Capstone 2, but hidden is not gone - the
    // modal was still reachable and opened on an empty list of classmates,
    // which reads as a broken page rather than a closed one.
    if (CAPSTONE_STAGE !== 1) {
        showToast('Capstone 2 has started, so teams keep the members they finished '
            + 'Capstone 1 with. Members cannot be added now.', 'error');

        return;
    }

    if (!JOINING_ALLOWED) {
        showToast(JOINING_CLOSED_MSG, 'error');

        return;
    }

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
    refreshOpenSlotSendButton();
    // Clear existing classmate items (keep loading and empty)
    Array.from(listEl.children).forEach(function(child) {
        if (child.id !== 'open-slot-loading' && child.id !== 'open-slot-empty') {
            child.remove();
        }
    });

    fetch('{{ route('team.available-classmates') }}', {
        headers: { 'Accept': 'application/json' }
    })
    .then(function(r) {
        if (!r.ok) {
            return r.text().then(function(text) { 
                throw new Error('HTTP ' + r.status + ': ' + text); 
            });
        }
        return r.json();
    })
    .then(function(data) {
        if (loadingEl) loadingEl.style.display = 'none';

        if (typeof data.available_slots === 'number') {
            OPEN_SLOT_SEATS_FREE = data.available_slots;
            refreshOpenSlotSendButton();
        }

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
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'open-slot-classmate-option';
            item.setAttribute('data-id', classmate.student_id);
            item.setAttribute('data-name', classmate.name);
            item.setAttribute('data-email', classmate.email);
            item.setAttribute('data-avatar', classmate.avatar);
            item.setAttribute('data-student-number', classmate.student_number || '');
            item.setAttribute('data-has-team', classmate.has_team ? '1' : '0');
            var hasTeam = !!classmate.has_team;
            // Already invited and waiting: still listed, so you can see you
            // asked them, but not offered again.
            var isPendingInvite = !!classmate.is_pending_invite;
            if (isPendingInvite) item.className += ' pending-invite';
            item.setAttribute('data-is-pending-invite', isPendingInvite ? '1' : '0');

            var blocked = hasTeam || isPendingInvite;
            var baseStyle = 'display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:var(--bg2);cursor:' + (blocked ? 'not-allowed' : 'pointer') + ';text-align:left;transition:all 0.15s;';
            item.style.cssText = baseStyle
                + (hasTeam ? 'opacity:0.55;' : '')
                + (!hasTeam && isPendingInvite ? 'opacity:0.85;border-color:rgba(29,78,216,0.3);background:var(--blue-bg);' : '');

            if (!blocked) {
                item.onmouseover = function() { this.style.borderColor = 'var(--blue)'; this.style.background = 'var(--blue-bg)'; };
                item.onmouseout = function() { this.style.borderColor = 'var(--border)'; this.style.background = 'var(--bg2)'; };
                item.onclick = function() { selectOpenSlotClassmate(this); };
            } else if (hasTeam) {
                item.onclick = function() { showToast('This classmate already has a team.', 'error'); };
            } else {
                item.onclick = function() { showToast('You have already invited this classmate.', 'error'); };
            }

            var avatarHtml = classmate.avatar
                ? '<img src="' + classmate.avatar + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" referrerpolicy="no-referrer" onerror="this.remove()">'
                : (classmate.name || 'U').charAt(0).toUpperCase();

            var indicatorHtml = hasTeam
                ? '<span style="font-size:10px;font-weight:700;color:#f59e0b;white-space:nowrap;">Has Team</span>'
                : isPendingInvite
                ? '<span style="font-size:10px;font-weight:700;color:var(--blue);white-space:nowrap;">Invited</span>'
                : '<span class="osl-tick" style="width:19px;height:19px;border-radius:5px;border:1.5px solid var(--border2);flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all 0.15s;">'
                  + '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round" style="opacity:0;transition:opacity 0.15s;" class="osl-tick-mark"><polyline points="20 6 9 17 4 12"/></svg>'
                  + '</span>';

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

    // Picking somebody is a toggle, not a decision: Send Invite at the
    // bottom is the decision, and it can carry several people at once.
    var on = !el.classList.contains('selected');
    el.classList.toggle('selected', on);

    var tick = el.querySelector('.osl-tick');
    var mark = el.querySelector('.osl-tick-mark');
    if (tick) {
        tick.style.background = on ? 'var(--blue)' : 'transparent';
        tick.style.borderColor = on ? 'var(--blue)' : 'var(--border2)';
    }
    if (mark) mark.style.opacity = on ? '1' : '0';

    el.style.borderColor = on ? 'var(--blue)' : 'var(--border)';
    el.style.background = on ? 'var(--blue-bg)' : 'var(--bg2)';

    // The hover handlers would undo the selected look on the way out.
    el.onmouseout = on
        ? function () {}
        : function () { this.style.borderColor = 'var(--border)'; this.style.background = 'var(--bg2)'; };

    refreshOpenSlotSendButton();
}

// Seats left after the members already on the team and the invitations
// already waiting to be answered. The server refuses anything over this;
// the point of having it here is that the leader can see it before picking
// five people for two seats. Rendered with the page, then re-read from the
// classmate list every time the dialog opens, so it does not go stale after
// invitations are sent without a reload.
var OPEN_SLOT_SEATS_FREE = {{ $teamSeatsFree }};

/** How many are picked, and whether Send Invite is worth pressing. */
function refreshOpenSlotSendButton() {
    var picked = document.querySelectorAll('.open-slot-classmate-option.selected');
    var btn = document.getElementById('osl-send');
    var label = document.getElementById('osl-count');
    if (!btn) return;

    var tooMany = picked.length > OPEN_SLOT_SEATS_FREE;

    btn.disabled = picked.length === 0 || tooMany;
    btn.style.opacity = btn.disabled ? '.5' : '1';
    btn.style.cursor = btn.disabled ? 'not-allowed' : 'pointer';
    btn.textContent = picked.length > 1 ? 'Send ' + picked.length + ' invites' : 'Send invite';

    if (label) {
        if (tooMany) {
            label.style.color = 'var(--red)';
            label.textContent = OPEN_SLOT_SEATS_FREE === 0
                ? 'No seats left on your team.'
                : 'Only ' + OPEN_SLOT_SEATS_FREE + ' seat' + (OPEN_SLOT_SEATS_FREE === 1 ? '' : 's')
                  + ' left - you have picked ' + picked.length + '.';
        } else {
            label.style.color = 'var(--text3)';
            label.textContent = picked.length
                ? picked.length + ' of ' + OPEN_SLOT_SEATS_FREE + ' picked'
                : (OPEN_SLOT_SEATS_FREE
                    ? 'Pick who to invite - ' + OPEN_SLOT_SEATS_FREE + ' seat'
                      + (OPEN_SLOT_SEATS_FREE === 1 ? '' : 's') + ' free.'
                    : 'No seats left on your team.');
        }
    }
}

function sendOpenSlotInvites() {
    var teamId = {{ $myTeam?->team_id ?? 'null' }};
    if (!teamId) {
        showToast('No team found.', 'error');
        return;
    }

    var picked = Array.prototype.slice.call(
        document.querySelectorAll('.open-slot-classmate-option.selected')
    );
    if (!picked.length) return;

    var btn = document.getElementById('osl-send');
    btn.disabled = true;
    btn.textContent = 'Sending…';

    var csrf = document.querySelector('meta[name="csrf-token"]');
    var token = csrf ? csrf.getAttribute('content') : '{{ csrf_token() }}';

    // One after another, not Promise.all. Sent together, every request asks
    // "is there a seat?" before any of them has taken one, so three
    // invitations for two seats all passed. In order, the second sees the
    // first waiting.
    var sendOne = function (el) {
        return fetch('{{ route('invitations.store') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify({
                receiver_id: el.getAttribute('data-id'),
                team_id: teamId,
                type: 'invite'
            })
        })
            .then(function (r) { return r.json(); })
            .then(function (d) { return { ok: !!(d && d.success), name: el.getAttribute('data-name'), message: d && d.message }; })
            .catch(function () { return { ok: false, name: el.getAttribute('data-name') }; });
    };

    picked.reduce(function (chain, el) {
        return chain.then(function (results) {
            return sendOne(el).then(function (result) { return results.concat(result); });
        });
    }, Promise.resolve([])).then(function (results) {
        var sent = results.filter(function (r) { return r.ok; });
        var failed = results.filter(function (r) { return !r.ok; });

        if (sent.length) {
            showToast(
                sent.length === 1
                    ? 'Invitation sent to ' + sent[0].name + '.'
                    : sent.length + ' invitations sent.',
                failed.length ? 'error' : 'success'
            );
        }

        if (failed.length && !sent.length) {
            showToast((failed[0].message) || 'Could not send the invitation.', 'error');
            btn.disabled = false;
            refreshOpenSlotSendButton();
            return;
        }

        // The pending members list is rendered by the server, so it only knows
        // about these once the page has been asked again - which is why an
        // invitation used to appear only after a manual refresh.
        closeOpenSlotInviteModal();
        setTimeout(function () { window.location.reload(); }, 900);
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

function showConfirm({ title, message, icon = 'request', confirmText = 'Confirm', cancelText = 'Cancel', confirmClass = 'primary', onConfirm }) {
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

    document.getElementById('ccm-cancel').textContent = cancelText;

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

    const teamName = {{ Js::from($myTeam?->team_name ?? '') }};
    document.getElementById('team-name').value = teamName;

    const lookingFor = {{ Js::from($myRecruitment->looking_for ?? '') }};
    const introduction = {{ Js::from($myRecruitment->introduction ?? '') }};
    if (isLeader) {
        document.getElementById('leader-looking-for').value = lookingFor;
    } else {
        document.getElementById('individual-intro').value = introduction;
    }

    activeTags = @json($myRecruitment->tagKeyList());
    renderActiveTags();
    setPickedSkills(@json($myRecruitment->skills ?? []));

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
    setPickedSkills([]);
    document.getElementById('post-goal').value = 'leader';
    updateModalUI();
}

function pickedSkills() {
    return Array.from(document.querySelectorAll('#skill-pick input:checked')).map(function (el) { return el.value; });
}

function setPickedSkills(skills) {
    document.querySelectorAll('#skill-pick input').forEach(function (el) { el.checked = skills.indexOf(el.value) !== -1; });
}

// goal: 'individual' opens the form on "Find a team" (the hero's second button).
function openPostModal(goal) {
    if (!CREATION_ALLOWED && !JOINING_ALLOWED) {
        showToast('Creating and joining teams are both closed right now - your CaPSA/TSA has turned them off.', 'error');
        return;
    }
    resetPostModal();
    // Open on the goal asked for, or the one that is still allowed.
    if (!CREATION_ALLOWED || goal === 'individual') {
        document.getElementById('post-goal').value = 'individual';
        updateModalUI();
    }
    syncRecruitmentSelectLabels();
    document.getElementById('postModal').classList.add('active');
}

function closePostModal() {
    document.getElementById('postModal').classList.remove('active');
    resetPostModal();
}

function sendInviteOrRequest(receiverId, teamId, type) {
    if (!JOINING_ALLOWED) {
        showToast(JOINING_CLOSED_MSG, 'error');
        return;
    }
    const isInvite = type === 'invite';
    if (isInvite && typeof OPEN_SLOT_SEATS_FREE !== 'undefined' && OPEN_SLOT_SEATS_FREE < 1) {
        showToast('No seats left on your team.', 'error');
        return;
    }
    showConfirm({
        title: isInvite ? 'Invite to team' : 'Send Join Request',
        message: isInvite
            ? 'Invite this classmate to your team? They pick their role after joining.'
            : 'Send a request to join this team? Your post will remain visible until accepted.',
        icon: isInvite ? 'invite' : 'request',
        confirmText: isInvite ? 'Send invite' : 'Send Request',
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

        if (!JOINING_ALLOWED) {
            // Said on the post itself, before a request or invite is attempted.
            ownerMsg.style.display = 'block';
            ownerMsg.textContent = JOINING_CLOSED_MSG;
            actionBtn.textContent = 'Joining is closed';
            actionBtn.disabled = true;
            actionBtn.style.opacity = 0.5;
            actionBtn.style.cursor = 'not-allowed';
            actionBtn.onclick = null;
        } else if (type === 'Leader') {
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
                ownerMsg.style.display = 'block';
                ownerMsg.textContent = 'You’re already on a team, so you can’t join another one.';
                actionBtn.textContent = 'You’re on a team';
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
            if (currentStudentTeamId && !VIEWER_IS_LEADER) {
                ownerMsg.style.display = 'block';
                ownerMsg.textContent = 'Only your team leader can invite classmates.';
                actionBtn.textContent = 'Leader invites only';
                actionBtn.disabled = true;
                actionBtn.style.opacity = 0.5;
                actionBtn.style.cursor = 'not-allowed';
                actionBtn.onclick = null;
            } else if (currentStudentTeamId && VIEWER_TEAM_FULL) {
                ownerMsg.style.display = 'block';
                ownerMsg.textContent = 'Your team is full, so you can’t invite anyone else.';
                actionBtn.textContent = 'Team is full';
                actionBtn.disabled = true;
                actionBtn.style.opacity = 0.5;
                actionBtn.style.cursor = 'not-allowed';
                actionBtn.onclick = null;
            } else if (currentStudentTeamId) {
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
        avatarEl.innerHTML = '<img src="' + avatarUrl + '" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" referrerpolicy="no-referrer" onerror="this.remove()">';
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
        ? 'Your team can have up to {{ $memberCount }} members including you (the leader). Slots are filled automatically as members join.' 
        : 'Highlight your best technical strengths. Teams search for specific skills when filling up their empty slots.';
    
    // A closed goal says so at once, and hides its fields so nothing gets typed for nothing.
    const closedMsg = isLeader ? (CREATION_ALLOWED ? null : CREATION_CLOSED_MSG) : (JOINING_ALLOWED ? null : JOINING_CLOSED_MSG);
    const noteBox = document.getElementById('modal-note-box');
    if (closedMsg) {
        document.getElementById('modal-note-text').innerHTML = '<strong>Closed:</strong> ' + closedMsg;
    }
    noteBox.classList.toggle('is-closed', !!closedMsg);
    const submitBtn = document.getElementById('form-submit-btn');
    if (submitBtn) {
        submitBtn.disabled = !!closedMsg;
        submitBtn.style.opacity = closedMsg ? 0.5 : 1;
        submitBtn.style.cursor = closedMsg ? 'not-allowed' : 'pointer';
    }

    document.getElementById('skills-label').textContent = isLeader ? 'Skills we need' : 'Skills you have';
    document.getElementById('leader-fields').style.display = isLeader && !closedMsg ? 'block' : 'none';
    document.getElementById('individual-fields').style.display = !isLeader && !closedMsg ? 'block' : 'none';
    document.getElementById('recruitment-goal-input').value = isLeader ? 'build_a_team' : 'find_a_team';
    
    activeTags = [];
    renderActiveTags();
}

  syncRecruitmentSelectLabels();
  window.addEventListener('resize', syncRecruitmentSelectLabels);

function filterTagSuggestions() {
    const query = document.getElementById('tag-search-input').value.toLowerCase().trim();
    const filtered = roleOptions.filter(o => o.label.toLowerCase().includes(query) && !activeTags.includes(o.key));
    const box = document.getElementById('tag-suggestions');

    box.innerHTML = '';
    if (filtered.length === 0) {
        box.style.display = 'none';
        return;
    }

    filtered.forEach(option => {
        const item = document.createElement('div');
        item.className = 'tag-suggestion-item';
        item.textContent = option.label;
        item.onclick = function() { addTagToken(option.key); };
        box.appendChild(item);
    });
    box.style.display = 'block';
}

function roleLabel(key) {
    const option = roleOptions.find(o => o.key === key);
    return option ? option.label : key;
}

function addTagToken(key) {
    if (!activeTags.includes(key)) {
        activeTags.push(key);
    }
    document.getElementById('tag-search-input').value = '';
    document.getElementById('tag-suggestions').style.display = 'none';
    renderActiveTags();
}

function removeTagToken(key) {
    activeTags = activeTags.filter(t => t !== key);
    renderActiveTags();
}

// The chips follow the order the choices are offered in, whatever order they were picked.
function renderActiveTags() {
    const container = document.getElementById('tag-container');
    const input = document.getElementById('tag-search-input');
    container.querySelectorAll('.custom-tag-chip').forEach(el => el.remove());

    activeTags = roleOptions.map(o => o.key).filter(key => activeTags.includes(key));
    activeTags.forEach(key => {
        const chip = document.createElement('div');
        chip.className = 'custom-tag-chip';
        chip.textContent = roleLabel(key) + ' ';
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'custom-tag-chip-remove';
        remove.textContent = '×';
        remove.onclick = function () { removeTagToken(key); };
        chip.appendChild(remove);
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
    if (window.ctToast) { return ctToast(msg, type); }
    var el = document.getElementById('msg-toast');
    if (!el) return;
    el.textContent = msg;
    el.className = 'msg-toast show ' + (type || '');
    setTimeout(function() { el.classList.remove('show'); }, 4000);
}

function publishPost(event) {
    const closedGoal = document.getElementById('post-goal').value === 'leader' ? !CREATION_ALLOWED : !JOINING_ALLOWED;
    if (closedGoal) {
        event.preventDefault();
        showToast(document.getElementById('post-goal').value === 'leader' ? CREATION_CLOSED_MSG : JOINING_CLOSED_MSG, 'error');
        return;
    }
    if (activeTags.length === 0) {
        event.preventDefault();
        showToast('Choose at least one role.', 'error');
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
    document.getElementById('member-count-input').value = {{ $memberCount }};
    document.getElementById('tags-hidden-input').value = JSON.stringify(activeTags);
    document.getElementById('skills-hidden-input').value = JSON.stringify(pickedSkills());

    if (isEditing) {
        event.preventDefault();
        document.getElementById('recruitment-goal-input').value = goal === 'leader' ? 'build_a_team' : 'find_a_team';
        document.getElementById('member-count-input').value = {{ $memberCount }};
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
    initTeamNameCheck();

    // Skeleton fade-out
    var sk = document.getElementById('skeleton-content');
    var rc = document.getElementById('real-content');
    if (sk && rc) {
    // partials/skeleton-head already decided this in <head>, before the first
    // paint: the content is on screen and no skeleton was ever shown. Without
    // this, every save replayed the 600ms fade over content already there.
    // Skipping the fade must not skip the rest of this handler. This used
    // to `return`, which leaves the whole DOMContentLoaded callback - so on
    // every visit after the first, the leadership-transfer popup and the
    // expert-removed modal below simply never opened. The member being
    // handed the team was asked exactly once, on a page they had never
    // opened before, and never again.
    if (document.documentElement.classList.contains('sk-seen')) {
      sk.remove();
    } else {
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
                try {
                  sessionStorage.setItem('sk-seen:{{ substr(sha1(session()->getId()), 0, 12) }}:' + window.location.pathname, '1');
                } catch (e) {}
            }, 600);
        });
    }
    }

    @if(isset($pendingLeadershipTransfer) && $pendingLeadershipTransfer)
    var leadershipPopup = document.getElementById('leadershipPopup');
    if (leadershipPopup) {
        // After the portal tour, never on top of it (partials/portal-tour).
        setTimeout(function() {
            (window.afterPortalTour || function (fn) { fn(); })(function () { leadershipPopup.classList.add('active'); }, function () { return leadershipPopup.classList.contains('active'); });
        }, 550);
    }
    @endif

    // Expert Removed modal — mark each as read on dismiss
    var expertModal = document.getElementById('expertRemovedModal');
    var expertOkBtn = document.getElementById('expertRemovedOkBtn');
    if (expertModal && expertOkBtn) {
        // Held behind the portal tour, then shown (partials/portal-tour).
        if (expertModal.style.display === 'none') {
            (window.afterPortalTour || function (fn) { fn(); })(function () { expertModal.style.display = 'flex'; }, function () { return expertModal.style.display !== 'none'; });
        }
        var ids = (expertModal.getAttribute('data-ids') || '').split(',').filter(Boolean);
        expertOkBtn.addEventListener('click', function() {
            var token = document.querySelector('meta[name=csrf-token]').content;
            var promises = ids.map(function(id) {
                return fetch('/notifications/' + id + '/read', {
                    method: 'PUT',
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
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>
