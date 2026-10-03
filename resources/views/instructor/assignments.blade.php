<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Team Assignments - CapsTRACK</title>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/expert.css') }}" />
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
/* The page is a queue with the application open beside it. The page itself
   never scrolls on a desktop: the list scrolls inside its card and the panel
   inside itself, so the tabs, the slots and the decision buttons stay put. */
.ta-page { flex: 1; min-height: 0; display: flex; flex-direction: column; }
.ta-content { flex: 1; min-height: 0; display: flex; flex-direction: column; gap: 16px; padding: 22px 26px; overflow: hidden; }

.ta-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; flex-wrap: wrap; }
.ta-tc { margin: 0 0 18px; padding: 16px 18px; border: 1px solid var(--border); border-radius: 12px; background: var(--bg2); }
.ta-tc-h { display: flex; align-items: center; gap: 8px; margin: 0 0 10px; font-size: 15px; font-weight: 600; }
.ta-tc-item { padding: 12px 0; border-top: 1px solid var(--border); }
.ta-tc-team { font-size: 14px; font-weight: 600; }
.ta-tc-when { font-weight: 400; color: var(--text2); font-size: 12.5px; }
.ta-tc-titles { display: grid; grid-template-columns: 80px 1fr; gap: 4px 12px; margin: 8px 0; font-size: 13.5px; }
.ta-tc-titles dt { color: var(--text2); }
.ta-tc-titles dd { margin: 0; line-height: 1.45; }
.ta-tc-new { font-weight: 600; }
.ta-tc-note-l { display: block; font-size: 12.5px; color: var(--text2); margin-bottom: 4px; }
.ta-tc-note { width: 100%; box-sizing: border-box; border: 1px solid var(--border); border-radius: 8px; padding: 8px 10px; font: inherit; font-size: 13.5px; background: var(--bg); color: inherit; resize: vertical; }
.ta-tc-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 8px; flex-wrap: wrap; }
.ta-tc-btn { min-height: 38px; padding: 0 14px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); color: inherit; font: inherit; font-size: 13.5px; font-weight: 600; cursor: pointer; }
.ta-tc-ok { background: #1f7a4d; border-color: #1f7a4d; color: #fff; }
.ta-tc-err { margin-top: 6px; font-size: 12.5px; color: #b42318; }
.ta-intro { font-size: 14px; color: var(--text2); margin: 0; max-width: 640px; line-height: 1.5; }

/* Same pill for every slot, five to a row; an expert with 15 slots gets
   three rows instead of a card three times as wide. */
.ta-slots { display: flex; align-items: center; gap: 14px; padding: 10px 16px; background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; }
.ta-slots-label { font-size: 11px; font-weight: 600; color: var(--text2); text-transform: uppercase; letter-spacing: 0.04em; }
.ta-slots-text { font-size: 14px; color: var(--text); }
.ta-slots-open { color: var(--green); font-weight: 600; }
.ta-slots-full { color: var(--red); font-weight: 600; }
.ta-pills { display: flex; flex-wrap: wrap; gap: 4px; width: 156px; }
.ta-pill { width: 28px; height: 8px; border-radius: 4px; background: var(--bg4); }
.ta-pill.used { background: var(--accent); }

.ta-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
.ta-tabs { display: flex; gap: 22px; }
.ta-tab { display: inline-flex; align-items: center; gap: 8px; height: 42px; padding: 0 2px; border: 0; border-bottom: 2px solid transparent; background: none; font-family: inherit; font-size: 14px; font-weight: 500; color: var(--text2); cursor: pointer; }
.ta-tab.active { color: var(--text); font-weight: 600; border-bottom-color: var(--text); }
.ta-count { min-width: 22px; height: 20px; padding: 0 7px; box-sizing: border-box; border-radius: 999px; background: var(--bg4); color: var(--text2); font-size: 11.5px; font-weight: 700; display: inline-grid; place-items: center; }
.ta-count.hot { background: var(--accent); color: #1e2128; }
.ta-tools { display: flex; align-items: center; gap: 8px; padding-bottom: 6px; }
.ta-search { position: relative; }
.ta-search svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 15px; height: 15px; stroke: var(--text3); fill: none; stroke-width: 2; }
.ta-search input, .ta-tools select { height: 36px; border: 1px solid var(--border2); border-radius: 7px; background: var(--bg2); color: var(--text); font-family: inherit; font-size: 13.5px; box-sizing: border-box; }
.ta-search input { width: clamp(160px, 18vw, 240px); padding: 0 12px 0 32px; }
.ta-tools select { padding: 0 8px; }

.ta-split { flex: 1; min-height: 0; display: grid; grid-template-columns: minmax(0, 1fr) clamp(320px, 34%, 420px); gap: 18px; }

.ta-list { min-height: 0; display: flex; flex-direction: column; background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
.ta-scroll { flex: 1; min-height: 0; overflow-y: auto; }
.ta-table { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 0; }
.ta-table th { position: sticky; top: 0; z-index: 1; background: var(--bg3); padding: 10px 14px; font-size: 11px; font-weight: 600; color: var(--text2); text-transform: uppercase; letter-spacing: 0.04em; text-align: left; border-bottom: 1px solid var(--border); white-space: nowrap; }
.ta-table th.ta-right, .ta-table td.ta-right { text-align: right; }
.ta-table td { padding: 12px 14px; border-bottom: 1px solid var(--bg4); vertical-align: middle; font-size: 13.5px; color: var(--text); }
.ta-row { cursor: pointer; }
.ta-row:hover td { background: var(--bg3); }
.ta-row.is-selected td { background: var(--accent-light); }
.ta-row.is-selected td:first-child { box-shadow: inset 3px 0 0 var(--accent); }
.ta-row-btn { display: flex; align-items: center; gap: 12px; width: 100%; border: 0; background: none; padding: 0; font: inherit; color: inherit; text-align: left; cursor: pointer; }
.ta-tile { width: 34px; height: 34px; flex-shrink: 0; border-radius: 8px; background: var(--bg4); color: var(--text); display: grid; place-items: center; font-size: 12px; font-weight: 700; }
.ta-names { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.ta-one { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ta-team { font-size: 14.5px; font-weight: 600; }
.ta-sub { font-size: 12.5px; color: var(--text3); }
.ta-sub em { font-style: italic; }
.ta-when { color: var(--text2); white-space: nowrap; }
.ta-when.stale { color: var(--amber); font-weight: 600; }
.ta-foot { padding: 10px 16px; font-size: 12.5px; color: var(--text2); border-top: 1px solid var(--border); background: var(--bg3); }
.ta-nomatch td { text-align: center; color: var(--text3); padding: 28px 14px; }
.ta-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; text-align: center; padding: 48px 24px; color: var(--text2); height: 100%; box-sizing: border-box; }
.ta-empty svg { width: 32px; height: 32px; stroke: var(--text3); fill: none; stroke-width: 1.6; }
.ta-empty b { font-size: 16px; color: var(--text); }
.ta-empty p { margin: 0; font-size: 13.5px; max-width: 420px; line-height: 1.5; }

.ta-chip { display: inline-flex; align-items: center; height: 22px; padding: 0 9px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
.ta-chip.adviser { background: var(--accent-light); color: var(--accent3); }
.ta-chip.consultant { background: #e8f0fb; color: #1d4f91; }
.ta-chip.grammarian, .ta-chip.expert { background: var(--bg4); color: var(--text2); }
.ta-chip.newtitle, .ta-chip.pending { background: var(--amber-bg); color: var(--amber); }
.ta-chip.approved { background: var(--green-bg); color: var(--green); }
.ta-chip.rejected { background: var(--red-bg); color: var(--red); }

.ta-panel { min-height: 0; display: flex; flex-direction: column; background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
.ta-panel-empty { margin: auto; padding: 32px 20px; text-align: center; font-size: 13px; color: var(--text3); }
.ta-panel-head { padding: 18px 20px 14px; border-bottom: 1px solid var(--border); display: flex; flex-direction: column; gap: 10px; }
.ta-panel-who { display: flex; align-items: center; gap: 12px; }
.ta-panel-who .ta-tile { width: 42px; height: 42px; background: var(--accent-light); font-size: 14px; }
.ta-panel-who h2 { margin: 0; font-size: 19px; font-weight: 700; color: var(--text); }
.ta-panel-who p { margin: 2px 0 0; font-size: 13px; color: var(--text2); }
.ta-panel-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 12.5px; color: var(--text2); }
.ta-panel-body { flex: 1; min-height: 0; overflow-y: auto; padding: 16px 20px; display: flex; flex-direction: column; gap: 18px; }
.ta-sec { display: flex; flex-direction: column; gap: 7px; }
.ta-sec-h { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 11px; font-weight: 600; color: var(--text2); text-transform: uppercase; letter-spacing: 0.04em; }
.ta-rec { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; font-weight: 600; color: var(--green); text-transform: none; letter-spacing: 0; }
.ta-rec svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; }
.ta-title { font-size: 15px; font-weight: 600; line-height: 1.45; color: var(--text); margin: 0; word-break: break-word; }
.ta-title.none { font-size: 13.5px; font-weight: 400; color: var(--text3); }
.ta-muted { font-size: 13.5px; color: var(--text3); margin: 0; }
.ta-text { font-size: 13.5px; color: var(--text); margin: 0; line-height: 1.5; }
.ta-quote { margin: 0; padding: 10px 12px; border-radius: 8px; background: var(--bg3); font-size: 13.5px; line-height: 1.5; color: var(--text); white-space: pre-line; word-break: break-word; }
.ta-reason { margin: 0; padding: 10px 12px; border-radius: 8px; background: var(--red-bg); font-size: 13.5px; line-height: 1.5; color: var(--red); white-space: pre-line; word-break: break-word; }
.ta-strike { font-size: 13.5px; color: var(--text3); text-decoration: line-through; margin: 0; line-height: 1.45; }
.ta-person { display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: 13.5px; }
.ta-person a { font-size: 12.5px; color: var(--text2); text-decoration: none; }
.ta-person a:hover { color: var(--accent3); }
.ta-links { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.ta-panel-foot { padding: 12px 20px; border-top: 1px solid var(--border); background: var(--bg3); display: flex; flex-direction: column; gap: 8px; }
.ta-note { font-size: 12.5px; color: var(--text2); text-align: center; line-height: 1.45; margin: 0; }
.ta-note.warn { color: var(--amber); text-align: left; }
.ta-pair { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }

/* One button look for the page, sized for a mouse and a thumb alike. */
.ta-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 40px; padding: 0 14px; border-radius: 8px; border: 1px solid var(--border2); background: var(--bg2); color: var(--text); font-family: inherit; font-size: 13.5px; font-weight: 600; cursor: pointer; text-decoration: none; box-sizing: border-box; }
.ta-btn:hover { background: var(--bg3); }
.ta-btn svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2; flex-shrink: 0; }
.ta-btn.go { background: var(--green); border-color: var(--green); color: #fff; }
.ta-btn.go:hover { filter: brightness(1.06); }
.ta-btn.stop { color: var(--red); border-color: rgba(220,38,38,0.35); }
.ta-btn.stop:hover { background: var(--red-bg); }
.ta-btn.stop-solid { background: var(--red); border-color: var(--red); color: #fff; }
.ta-btn.ghost { border-color: transparent; background: transparent; color: var(--text2); }
.ta-btn:disabled { opacity: 0.45; cursor: not-allowed; filter: none; }
.ta-btn.wide { width: 100%; }

/* Dialogs */
.modal-overlay { position: fixed; inset: 0; background: rgba(17,19,24,0.5); z-index: 300; display: none; align-items: center; justify-content: center; padding: 16px; box-sizing: border-box; }
.modal-overlay.open { display: flex; }
.ta-dialog { width: 480px; max-width: 100%; max-height: calc(100vh - 32px);max-height: calc(100dvh - 32px); display: flex; flex-direction: column; background: var(--bg2); border-radius: 14px; box-shadow: 0 24px 60px rgba(0,0,0,0.3); overflow: hidden; animation: taIn 0.18s ease; }
@keyframes taIn { from { opacity: 0; transform: translateY(8px) scale(0.98); } to { opacity: 1; transform: none; } }
.ta-dialog-head { display: flex; align-items: flex-start; gap: 14px; padding: 20px 22px 6px; }
.ta-dialog-icon { width: 42px; height: 42px; flex-shrink: 0; border-radius: 999px; display: grid; place-items: center; }
.ta-dialog-icon svg { width: 21px; height: 21px; fill: none; stroke-width: 2.2; }
.ta-dialog-icon.go { background: var(--green-bg); } .ta-dialog-icon.go svg { stroke: var(--green); }
.ta-dialog-icon.stop { background: var(--red-bg); } .ta-dialog-icon.stop svg { stroke: var(--red); }
.ta-dialog-icon.warn { background: var(--amber-bg); } .ta-dialog-icon.warn svg { stroke: var(--amber); }
.ta-dialog h3 { margin: 0; font-size: 17px; font-weight: 700; color: var(--text); line-height: 1.35; }
.ta-dialog-sub { margin: 4px 0 0; font-size: 13.5px; color: var(--text2); line-height: 1.5; }
.ta-dialog-body { padding: 10px 22px 18px; display: flex; flex-direction: column; gap: 12px; overflow-y: auto; }
.ta-dialog-foot { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 22px; border-top: 1px solid var(--border); background: var(--bg3); }
.ta-kv { display: grid; grid-template-columns: 110px minmax(0, 1fr); gap: 7px 12px; padding: 12px 14px; border-radius: 8px; background: var(--bg3); font-size: 13.5px; }
.ta-kv dt { color: var(--text2); margin: 0; }
.ta-kv dd { margin: 0; color: var(--text); font-weight: 500; word-break: break-word; }
.ta-warnbox { display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border-radius: 8px; background: var(--amber-bg); color: var(--amber); font-size: 13px; line-height: 1.45; }
.ta-warnbox svg { width: 16px; height: 16px; flex-shrink: 0; stroke: currentColor; fill: none; stroke-width: 2; margin-top: 1px; }
.ta-field { display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600; color: var(--text); }
.ta-field span { font-weight: 400; color: var(--text2); }
.ta-field textarea { width: 100%; min-height: 104px; padding: 10px 12px; border: 1px solid var(--border2); border-radius: 8px; background: var(--bg2); color: var(--text); font-family: inherit; font-size: 14px; line-height: 1.5; resize: vertical; box-sizing: border-box; }
.ta-field textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
.ta-hint { display: flex; justify-content: space-between; gap: 10px; font-size: 12px; color: var(--text2); }
.ta-quick { display: flex; flex-wrap: wrap; gap: 6px; }
.ta-quick button { height: 32px; padding: 0 12px; border-radius: 999px; border: 1px solid var(--border2); background: var(--bg2); color: var(--text); font-family: inherit; font-size: 12.5px; cursor: pointer; }
.ta-quick button[aria-pressed="true"] { background: var(--text); border-color: var(--text); color: var(--bg2); }

/* Reading a pre-proposal: the document, what you need to decide, the decision. */
.ta-viewer { width: 1180px; max-width: 100%; height: calc(100vh - 32px);height: calc(100dvh - 32px); }
.ta-viewer-head { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 12px 18px; border-bottom: 1px solid var(--border); }
.ta-viewer-head h3 { font-size: 16px; }
.ta-viewer-main { flex: 1; min-height: 0; display: flex; }
.ta-viewer-main iframe { flex: 1; min-width: 0; border: 0; background: #dcdee4; }
.ta-viewer-side { width: 300px; flex-shrink: 0; border-left: 1px solid var(--border); overflow-y: auto; padding: 16px 18px; display: flex; flex-direction: column; gap: 16px; box-sizing: border-box; }
.ta-viewer-foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 18px; border-top: 1px solid var(--border); background: var(--bg3); }
.ta-viewer-foot p { margin: 0; font-size: 12.5px; color: var(--text2); }
.ta-x { width: 36px; height: 36px; border: 0; border-radius: 8px; background: none; color: var(--text2); cursor: pointer; display: grid; place-items: center; }
.ta-x:hover { background: var(--bg3); }
.ta-x svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2; }

.msg-toast { position: fixed; bottom: 26px; right: 26px; background: #1e2128; color: #fff; padding: 13px 18px; border-radius: 10px; font-size: 13.5px; font-weight: 500; z-index: 500; display: none; align-items: center; gap: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); max-width: min(460px, calc(100vw - 32px)); }
.msg-toast.success { border-left: 4px solid var(--green); }
.msg-toast.error { border-left: 4px solid var(--red); }
.msg-toast.show { display: flex; }

/* Hamburger + sidebar (unchanged from the rest of the expert pages) */
.hamburger-btn{display:none;align-items:center;justify-content:center;width:36px;height:36px;border-radius:9px;background:rgba(255,255,255,0.06);border:1px solid rgba(201,168,44,0.2);cursor:pointer;color:var(--navy-text);flex-shrink:0;transition:background 0.15s}
.hamburger-btn:hover{background:rgba(201,168,44,0.12)}
.hamburger-btn svg{width:16px;height:16px;stroke:rgba(255,255,200,0.7);fill:none;stroke-width:2;stroke-linecap:butt}
.sidebar-overlay{display:none;position:fixed;inset:0;z-index:999;background:rgba(0,0,0,0.5)}
@media(max-width:1024px){
  .hamburger-btn{display:inline-flex}
  .sidebar{display:flex!important;position:fixed!important;left:0!important;top:0!important;height:100vh!important;height:100dvh!important;z-index:1000;transform:translateX(-100%);transition:transform 0.25s cubic-bezier(0.16,1,0.3,1)}
  .sidebar-open .sidebar{transform:translateX(0)}
  .sidebar-open .sidebar-overlay{display:block}
  body.sidebar-open{overflow:hidden}
}

/* Narrow screens: one column. The page scrolls here because there is no
   room for two independent scroll areas; the list keeps a height cap so a
   long history never pushes the application off the bottom. */
@media (max-width: 900px) {
  /* One scrolling area, the page's own: a second one inside it held the
     application in a box shorter than the screen, its buttons out of reach. */
  .ta-content { flex: none; overflow: visible; padding: 16px; }
  .ta-split { display: flex; flex-direction: column; flex: none; }
  .ta-list { max-height: 60vh;max-height: 60dvh; }
  .ta-panel { overflow: visible; }
  .ta-panel-body { overflow: visible; }
  .ta-viewer-side { display: none; }
}
@media (max-width: 640px) {
  .topbar{padding:0 12px!important}
  .ta-head { align-items: stretch; }
  .ta-slots { width: 100%; justify-content: space-between; box-sizing: border-box; }
  .ta-bar { align-items: stretch; }
  .ta-tabs { gap: 14px; overflow-x: auto; }
  .ta-tools { width: 100%; }
  .ta-search { flex: 1; }
  .ta-search input { width: 100%; }
  .ta-col-leader { display: none; }
  /* The team's name gets the room: at the desktop widths of the role and
     waiting columns it was cut to its first few letters. */
  .ta-table col:nth-child(2) { width: 100px !important; }
  .ta-table col:last-child { width: 84px !important; }
  .ta-table th, .ta-table td { padding-left: 8px; padding-right: 8px; }
  .ta-table th:first-child, .ta-table td:first-child { padding-left: 12px; }
  .ta-table .ta-chip { padding: 0 7px; }
  .ta-row-btn { gap: 10px; }
  .ta-table .ta-tile { width: 30px; height: 30px; }
  .ta-dialog-foot { flex-direction: column-reverse; }
  .ta-dialog-foot .ta-btn { width: 100%; }
  .msg-toast { left: 12px; right: 12px; bottom: 12px; max-width: none; }
}
</style>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
</head>
<body>
@include('instructor.partials.sidebar', ['currentPage' => 'assignments'])
<div class="sidebar-overlay" onclick="closeSidebar()"></div>

@php
  // The queue is one list: requests to approve and new titles to confirm,
  // longest-waiting first.
  $queue = collect();
  foreach ($pendingAssignments as $a) {
      $queue->push(['kind' => 'asg', 'id' => $a->assignment_id, 'at' => $a->created_at]);
  }
  foreach ($titleConfirmations as $tc) {
      $queue->push(['kind' => 'title', 'id' => $tc->id, 'at' => $tc->created_at]);
  }
  $queue = $queue->sortBy(fn ($q) => $q['at']?->timestamp ?? 0)->values();
  $titlesById = $titleConfirmations->keyBy('id');

  $isFull = $maxSlots > 0 && $currentSlots >= $maxSlots;
  // Open on the queue when something is waiting; otherwise on your teams.
  $defaultTab = ($queue->isNotEmpty() || $approvedAssignments->isEmpty()) ? 'pending' : 'approved';
  $tabStyle = fn ($tab) => $tab === $defaultTab ? 'display:contents' : 'display:none';
  $tabHidden = fn ($tab) => $tab === $defaultTab ? '' : 'hidden';
  $initials = fn ($name) => mb_strtoupper(mb_substr(trim($name ?: 'T'), 0, 1)).mb_strtolower(mb_substr(trim($name ?: 'T'), 1, 1));
  $roleClass = fn ($role) => in_array(strtolower($role), ['adviser', 'consultant', 'grammarian']) ? strtolower($role) : 'expert';
@endphp

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
    <div class="content" style="padding-top:22px;">
      <div style="display:flex;justify-content:space-between;gap:16px;margin-bottom:18px;">
        <div class="sk" style="height:18px;width:420px;border-radius:6px;"></div>
        <div class="sk" style="height:58px;width:300px;border-radius:10px;"></div>
      </div>
      <div style="display:flex;gap:18px;margin-bottom:16px;">
        <div class="sk" style="height:30px;width:140px;border-radius:8px;"></div>
        <div class="sk" style="height:30px;width:110px;border-radius:8px;"></div>
        <div class="sk" style="height:30px;width:100px;border-radius:8px;"></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 380px;gap:18px;">
        <div class="sk" style="height:420px;border-radius:10px;"></div>
        <div class="sk" style="height:420px;border-radius:10px;"></div>
      </div>
    </div>
  </div>

  <div id="real-content" class="ta-page" style="display:none;">
    <div class="ta-content">

      <div class="ta-head">
        <p class="ta-intro">Teams asking you to be their adviser, consultant or grammarian. Pick one to read their application.</p>
        <div class="ta-slots" aria-label="Your slots: {{ $currentSlots }}{{ $maxSlots > 0 ? ' of '.$maxSlots : '' }} used">
          <div>
            <div class="ta-slots-label">Your slots</div>
            <div class="ta-slots-text">
              @if ($maxSlots > 0)
                <strong>{{ $currentSlots }} of {{ $maxSlots }}</strong> used ·
                @if ($isFull)
                  <span class="ta-slots-full">Full</span>
                @else
                  <span class="ta-slots-open">{{ $maxSlots - $currentSlots }} open</span>
                @endif
              @else
                <strong>{{ $currentSlots }}</strong> {{ $currentSlots === 1 ? 'team' : 'teams' }} · no limit set
              @endif
            </div>
          </div>
          @if ($maxSlots > 0)
            <div class="ta-pills" aria-hidden="true">
              @for ($i = 0; $i < $maxSlots; $i++)
                <span class="ta-pill {{ $i < $currentSlots ? 'used' : '' }}"></span>
              @endfor
            </div>
          @endif
        </div>
      </div>

      {{-- A passed team asking to change part of its title. Only an approval changes it. --}}
      @if ($titleChanges->isNotEmpty())
        <section class="ta-tc" aria-labelledby="ta-tc-h">
          <h2 id="ta-tc-h" class="ta-tc-h">Title changes to approve <span class="ta-count">{{ $titleChanges->count() }}</span></h2>
          @foreach ($titleChanges as $change)
            <article class="ta-tc-item">
              <div class="ta-tc-team">{{ $change->team?->team_name }} <span class="ta-tc-when">· asked {{ $change->created_at?->diffForHumans() }}{{ $change->requester ? ' by '.trim($change->requester->f_name.' '.$change->requester->l_name) : '' }}</span></div>
              <dl class="ta-tc-titles">
                <dt>Now</dt><dd>{{ $change->current_title }}</dd>
                <dt>Change to</dt><dd class="ta-tc-new">{{ $change->new_title }}</dd>
                <dt>Why</dt><dd>{{ $change->reason }}</dd>
              </dl>
              <form method="POST" action="{{ route('instructor.title-changes.update', $change) }}" class="ta-tc-form">
                @csrf
                @method('PATCH')
                <label class="ta-tc-note-l" for="tc-note-{{ $change->id }}">Note to the team <span>(needed if you decline)</span></label>
                <textarea id="tc-note-{{ $change->id }}" name="note" class="ta-tc-note" rows="2" maxlength="1000">{{ old('note') }}</textarea>
                <div class="ta-tc-actions">
                  <button type="submit" name="decision" value="decline" class="ta-tc-btn">Keep the title</button>
                  <button type="submit" name="decision" value="approve" class="ta-tc-btn ta-tc-ok">Approve change</button>
                </div>
              </form>
            </article>
          @endforeach
          @error('note')<div class="ta-tc-err" role="alert">{{ $message }}</div>@enderror
        </section>
      @endif

      <div class="ta-bar">
        <div class="ta-tabs" role="tablist" aria-label="Applications">
          <button type="button" role="tab" class="ta-tab" id="tab-btn-pending" aria-controls="tab-pending" aria-selected="false" onclick="switchTab('pending')">
            Waiting for you <span class="ta-count {{ $queue->count() ? 'hot' : '' }}">{{ $queue->count() }}</span>
          </button>
          <button type="button" role="tab" class="ta-tab" id="tab-btn-approved" aria-controls="tab-approved" aria-selected="false" onclick="switchTab('approved')">
            Approved <span class="ta-count">{{ $approvedAssignments->count() }}</span>
          </button>
          <button type="button" role="tab" class="ta-tab" id="tab-btn-rejected" aria-controls="tab-rejected" aria-selected="false" onclick="switchTab('rejected')">
            Declined <span class="ta-count">{{ $rejectedAssignments->count() }}</span>
          </button>
        </div>
        <div class="ta-tools">
          <label class="ta-search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input type="search" id="ta-search" placeholder="Search team or leader" aria-label="Search team or leader" oninput="applyFilter()">
          </label>
          <select id="ta-role" aria-label="Role" onchange="applyFilter()">
            <option value="">All roles</option>
            <option value="adviser">Adviser</option>
            <option value="consultant">Consultant</option>
            <option value="grammarian">Grammarian</option>
          </select>
        </div>
      </div>

      <div class="ta-split">
        <section class="ta-list" aria-label="Applications">

          {{-- Waiting for you --}}
          <div class="ta-tabpanel" id="tab-pending" role="tabpanel" aria-labelledby="tab-btn-pending" {{ $tabHidden('pending') }} style="{{ $tabStyle('pending') }}">
            @if ($queue->isEmpty())
              <div class="ta-empty">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 13l3-8h12l3 8v6H3z"/><path d="M3 13h5l1 3h6l1-3h5"/></svg>
                <b>No teams are waiting for you</b>
                <p>When a team asks you to be their adviser, consultant or grammarian, it shows up here and you get a notification.</p>
              </div>
            @else
              <div class="ta-scroll">
                <table class="ta-table" data-export="Teams Waiting for You">
                  <colgroup><col><col style="width:122px"><col class="ta-col-leader" style="width:140px"><col style="width:112px"></colgroup>
                  <thead><tr><th scope="col">Team</th><th scope="col">Role</th><th scope="col" class="ta-col-leader">Leader</th><th scope="col" class="ta-right">Waiting</th></tr></thead>
                  <tbody>
                    @foreach ($queue as $item)
                      @if ($item['kind'] === 'asg')
                        @php $d = $assignmentData[$item['id']]; @endphp
                        <tr class="ta-row" data-kind="asg" data-id="{{ $item['id'] }}" data-role="{{ strtolower($d['role']) }}" data-search="{{ mb_strtolower($d['team'].' '.$d['leader'].' '.($d['approved_title'] ?? $d['project'])) }}">
                          <td>
                            <button type="button" class="ta-row-btn" onclick="selectAssignment({{ $item['id'] }})">
                              <span class="ta-tile" aria-hidden="true">{{ $initials($d['team']) }}</span>
                              <span class="ta-names">
                                <span class="ta-team ta-one">{{ $d['team'] }}</span>
                                <span class="ta-sub ta-one">
                                  @if ($d['approved_title']) {{ $d['approved_title'] }} @else <em>No project title yet</em> @endif
                                </span>
                              </span>
                            </button>
                          </td>
                          <td><span class="ta-chip {{ $roleClass($d['role']) }}">{{ $d['role'] }}</span></td>
                          <td class="ta-col-leader"><span class="ta-one">{{ $d['leader'] }}</span></td>
                          <td class="ta-right"><span class="ta-when {{ $d['stale'] ? 'stale' : '' }}">{{ $d['waiting'] }}</span></td>
                        </tr>
                      @else
                        @php $tc = $titlesById[$item['id']]; $t = $titleData[$item['id']]; @endphp
                        <tr class="ta-row" data-kind="title" data-id="{{ $item['id'] }}" data-role="adviser" data-search="{{ mb_strtolower($t['team'].' '.$t['title']) }}">
                          <td>
                            <button type="button" class="ta-row-btn" onclick="selectTitle({{ $item['id'] }})">
                              <span class="ta-tile" aria-hidden="true">{{ $initials($t['team']) }}</span>
                              <span class="ta-names">
                                <span class="ta-team ta-one">{{ $t['team'] }}</span>
                                <span class="ta-sub ta-one">{{ $tc->title }}</span>
                              </span>
                            </button>
                          </td>
                          <td><span class="ta-chip newtitle">New title</span></td>
                          <td class="ta-col-leader"><span class="ta-one" style="color:var(--text3)">Your team</span></td>
                          <td class="ta-right"><span class="ta-when {{ $t['stale'] ? 'stale' : '' }}">{{ $t['waiting'] }}</span></td>
                        </tr>
                      @endif
                    @endforeach
                    <tr class="ta-nomatch" hidden><td colspan="4">No teams match your search.</td></tr>
                  </tbody>
                </table>
              </div>
              <div class="ta-foot">{{ $queue->count() }} waiting · oldest first</div>
            @endif
          </div>

          {{-- Approved --}}
          <div class="ta-tabpanel" id="tab-approved" role="tabpanel" aria-labelledby="tab-btn-approved" {{ $tabHidden('approved') }} style="{{ $tabStyle('approved') }}">
            @if ($approvedAssignments->isEmpty())
              <div class="ta-empty">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg>
                <b>No approved teams yet</b>
                <p>Teams you approve show up here, with what to do next for each.</p>
              </div>
            @else
              <div class="ta-scroll">
                <table class="ta-table" data-export="Approved Teams">
                  <colgroup><col><col style="width:122px"><col class="ta-col-leader" style="width:140px"><col style="width:132px"></colgroup>
                  <thead><tr><th scope="col">Team</th><th scope="col">Role</th><th scope="col" class="ta-col-leader">Leader</th><th scope="col" class="ta-right">Assigned</th></tr></thead>
                  <tbody>
                    @foreach ($approvedAssignments as $a)
                      @php $d = $assignmentData[$a->assignment_id]; @endphp
                      <tr class="ta-row" data-kind="asg" data-id="{{ $a->assignment_id }}" data-role="{{ strtolower($d['role']) }}" data-search="{{ mb_strtolower($d['team'].' '.$d['leader'].' '.($d['approved_title'] ?? $d['project'])) }}">
                        <td>
                          <button type="button" class="ta-row-btn" onclick="selectAssignment({{ $a->assignment_id }})">
                            <span class="ta-tile" aria-hidden="true">{{ $initials($d['team']) }}</span>
                            <span class="ta-names">
                              <span class="ta-team ta-one">{{ $d['team'] }}</span>
                              <span class="ta-sub ta-one">
                                @if ($d['approved_title']) {{ $d['approved_title'] }} @else <em>No project title yet</em> @endif
                              </span>
                            </span>
                          </button>
                        </td>
                        <td><span class="ta-chip {{ $roleClass($d['role']) }}">{{ $d['role'] }}</span></td>
                        <td class="ta-col-leader"><span class="ta-one">{{ $d['leader'] }}</span></td>
                        <td class="ta-right"><span class="ta-when">{{ $a->created_at?->diffForHumans() }}</span></td>
                      </tr>
                    @endforeach
                    <tr class="ta-nomatch" hidden><td colspan="4">No teams match your search.</td></tr>
                  </tbody>
                </table>
              </div>
              <div class="ta-foot">{{ $approvedAssignments->count() }} {{ $approvedAssignments->count() === 1 ? 'team' : 'teams' }} · newest first</div>
            @endif
          </div>

          {{-- Declined --}}
          <div class="ta-tabpanel" id="tab-rejected" role="tabpanel" aria-labelledby="tab-btn-rejected" {{ $tabHidden('rejected') }} style="{{ $tabStyle('rejected') }}">
            @if ($rejectedAssignments->isEmpty())
              <div class="ta-empty">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
                <b>Nothing declined</b>
                <p>Requests you decline stay here for a while, with the reason the team read.</p>
              </div>
            @else
              <div class="ta-scroll">
                <table class="ta-table" data-export="Declined Requests">
                  <colgroup><col><col style="width:122px"><col class="ta-col-leader" style="width:140px"><col style="width:132px"></colgroup>
                  <thead><tr><th scope="col">Team</th><th scope="col">Role</th><th scope="col" class="ta-col-leader">Leader</th><th scope="col" class="ta-right">Declined</th></tr></thead>
                  <tbody>
                    @foreach ($rejectedAssignments as $a)
                      @php $d = $assignmentData[$a->assignment_id]; @endphp
                      <tr class="ta-row" data-kind="asg" data-id="{{ $a->assignment_id }}" data-role="{{ strtolower($d['role']) }}" data-search="{{ mb_strtolower($d['team'].' '.$d['leader'].' '.($d['approved_title'] ?? $d['project'])) }}">
                        <td>
                          <button type="button" class="ta-row-btn" onclick="selectAssignment({{ $a->assignment_id }})">
                            <span class="ta-tile" aria-hidden="true">{{ $initials($d['team']) }}</span>
                            <span class="ta-names">
                              <span class="ta-team ta-one">{{ $d['team'] }}</span>
                              <span class="ta-sub ta-one">
                                @if ($d['approved_title']) {{ $d['approved_title'] }} @else <em>No project title yet</em> @endif
                              </span>
                            </span>
                          </button>
                        </td>
                        <td><span class="ta-chip {{ $roleClass($d['role']) }}">{{ $d['role'] }}</span></td>
                        <td class="ta-col-leader"><span class="ta-one">{{ $d['leader'] }}</span></td>
                        <td class="ta-right"><span class="ta-when">{{ $a->updated_at?->diffForHumans() }}</span></td>
                      </tr>
                    @endforeach
                    <tr class="ta-nomatch" hidden><td colspan="4">No teams match your search.</td></tr>
                  </tbody>
                </table>
              </div>
              <div class="ta-foot">{{ $rejectedAssignments->count() }} {{ $rejectedAssignments->count() === 1 ? 'team' : 'teams' }} · newest first</div>
            @endif
          </div>
        </section>

        {{-- The application itself, beside the queue rather than on top of it. --}}
        <aside class="ta-panel" id="asg-panel" aria-live="polite">
          <div class="ta-panel-empty" id="asg-empty">Pick a team on the left to read their application.</div>

          <div id="asg-detail" style="display:none;flex-direction:column;flex:1;min-height:0;">
            <div class="ta-panel-head">
              <div class="ta-panel-who">
                <span class="ta-tile" id="p-tile" aria-hidden="true"></span>
                <div>
                  <h2 id="p-team"></h2>
                  <p id="p-sub"></p>
                </div>
              </div>
              <div class="ta-panel-meta">
                <span class="ta-chip" id="p-status"></span>
                <span class="ta-chip pending" id="p-wait" style="display:none"></span>
                <span id="p-date"></span>
              </div>
            </div>

            <div class="ta-panel-body">
              <div class="ta-sec" id="p-next" style="display:none">
                <div class="ta-sec-h">What's next</div>
                <div class="ta-links">
                  <a class="ta-btn" href="{{ route('instructor.docreview') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>
                    Document Review
                  </a>
                  <a class="ta-btn" href="{{ route('instructor.teams') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/></svg>
                    My Teams
                  </a>
                </div>
              </div>

              <div class="ta-sec">
                <div class="ta-sec-h"><span id="p-title-label">Project title</span><span class="ta-rec" id="p-rec" style="display:none"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/></svg>Recommended by their CaPSA</span></div>
                <p class="ta-title" id="p-title"></p>
                <div id="p-proposal"></div>
              </div>

              <div class="ta-sec" id="p-replaces-wrap" style="display:none">
                <div class="ta-sec-h">Replaces</div>
                <div id="p-replaces"></div>
              </div>

              <div class="ta-sec" id="p-dup-wrap" style="display:none">
                <div class="ta-sec-h">Duplication check</div>
                <p class="ta-text" id="p-dup"></p>
              </div>

              <div class="ta-sec" id="p-people-wrap">
                <div class="ta-sec-h">People</div>
                <div class="ta-person"><span id="p-leader"></span><a id="p-email" href="#" style="display:none">Email</a></div>
                <div id="p-members"></div>
              </div>

              <div class="ta-sec" id="p-message-wrap">
                <div class="ta-sec-h">Their message</div>
                <p class="ta-quote" id="p-message"></p>
              </div>

              <div class="ta-sec" id="asg-reason-wrap" style="display:none">
                <div class="ta-sec-h">Why you declined</div>
                <p class="ta-reason" id="asg-reason"></p>
              </div>
            </div>

            <div class="ta-panel-foot" id="asg-acts"></div>
          </div>
        </aside>
      </div>
    </div>
  </div>
</div>

{{-- Reading a pre-proposal or proposal: the document, what you need to
     decide, and the decision, in one window. --}}
<div class="modal-overlay" id="pdf-preview-modal">
  <div class="ta-dialog ta-viewer" role="dialog" aria-modal="true" aria-labelledby="pv-title">
    <div class="ta-viewer-head">
      <div>
        <h3 id="pv-title"></h3>
        <p class="ta-dialog-sub" id="pv-sub"></p>
      </div>
      <div style="display:flex;align-items:center;gap:6px;">
        <a class="ta-btn ghost" id="pv-open" href="#" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/></svg>
          Open in new tab
        </a>
        <button type="button" class="ta-x" onclick="closePdfPreview()" aria-label="Close">
          <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>
    </div>
    <div class="ta-viewer-main">
      <iframe id="pdf-preview-frame" src="about:blank" title="Proposal PDF preview"></iframe>
      <aside class="ta-viewer-side" id="pv-side" aria-label="About this request"></aside>
    </div>
    <div class="ta-viewer-foot">
      <p id="pv-note"></p>
      <div style="display:flex;gap:8px;" id="pv-acts"></div>
    </div>
  </div>
</div>

<!-- Approve: the decision the expert cannot take back -->
<div class="modal-overlay" id="approve-modal">
  <div class="ta-dialog" role="dialog" aria-modal="true" aria-labelledby="approve-title">
    <div class="ta-dialog-head">
      <span class="ta-dialog-icon go"><svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg></span>
      <div>
        <h3 id="approve-title">Approve this team?</h3>
        <p class="ta-dialog-sub">The team is told right away and you join them in this role.</p>
      </div>
    </div>
    <div class="ta-dialog-body">
      <dl class="ta-kv" id="approve-detail"></dl>
      <div class="ta-warnbox">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
        <span>You can't remove a team after approving it. If you need to stop later, their CaPSA has to make the change.</span>
      </div>
    </div>
    <div class="ta-dialog-foot">
      <button type="button" class="ta-btn" onclick="closeModal('approve-modal')">Cancel</button>
      <button type="button" class="ta-btn go" id="btn-confirm-approve" onclick="confirmApprove()">Approve</button>
    </div>
  </div>
</div>

<!-- Decline: a reason is required; quick reasons fill it in -->
<div class="modal-overlay" id="reject-modal">
  <div class="ta-dialog" style="width:540px" role="dialog" aria-modal="true" aria-labelledby="reject-title">
    <div class="ta-dialog-head">
      <span class="ta-dialog-icon stop"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></span>
      <div>
        <h3 id="reject-title">Decline this request?</h3>
        <p class="ta-dialog-sub" id="reject-modal-sub"></p>
      </div>
    </div>
    <div class="ta-dialog-body">
      <div class="ta-field">Quick reason
        <div class="ta-quick" id="reject-quick">
          <button type="button" aria-pressed="false" data-text="My slots are full this term, so I can't take another team. Please ask another expert.">My slots are full</button>
          <button type="button" aria-pressed="false" data-text="Your topic is outside my field, so I wouldn't be the right fit. Please ask an expert who works in this area.">Topic is outside my field</button>
          <button type="button" aria-pressed="false" data-text="Your proposal needs more work first. Tighten the problem statement and scope, then apply again.">Proposal needs more work</button>
          <button type="button" aria-pressed="false" data-text="I'm already working with a team on a very similar project, so I'd rather not take this one.">Already advising a similar project</button>
        </div>
      </div>
      <label class="ta-field" for="reject-reason">Reason <span>(the team reads this; edit it as you like)</span>
        <textarea id="reject-reason" maxlength="500" oninput="validateReject()" placeholder="Most teams can apply again. Say what to fix, or that they should look elsewhere."></textarea>
      </label>
      <div class="ta-hint"><span id="reject-hint">The team reads this and can apply again.</span><span id="reject-count">0 / 500</span></div>
    </div>
    <div class="ta-dialog-foot">
      <button type="button" class="ta-btn" onclick="closeModal('reject-modal')">Cancel</button>
      <button type="button" class="ta-btn stop-solid" id="btn-confirm-reject" onclick="confirmReject()">Decline request</button>
    </div>
  </div>
</div>

<!-- Clear a declined request off the list -->
<div class="modal-overlay" id="clear-modal">
  <div class="ta-dialog" role="dialog" aria-modal="true" aria-labelledby="clear-title">
    <div class="ta-dialog-head">
      <span class="ta-dialog-icon stop"><svg viewBox="0 0 24 24"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg></span>
      <div>
        <h3 id="clear-title">Remove this from your list?</h3>
        <p class="ta-dialog-sub" id="clear-sub"></p>
      </div>
    </div>
    <div class="ta-dialog-body">
      <p class="ta-text">This only clears it from your Declined tab. The team isn't told again, and they can still apply to you.</p>
    </div>
    <div class="ta-dialog-foot">
      <button type="button" class="ta-btn" onclick="closeModal('clear-modal')">Cancel</button>
      <button type="button" class="ta-btn stop-solid" id="btn-confirm-clear" onclick="confirmClear()">Remove from list</button>
    </div>
  </div>
</div>

<!-- Approve a new title -->
<div class="modal-overlay" id="title-approve-modal">
  <form class="ta-dialog" role="dialog" aria-modal="true" aria-labelledby="title-approve-title" method="POST" id="title-approve-form" action="#" onsubmit="return lockSubmit(this)">
    @csrf
    @method('PATCH')
    <input type="hidden" name="decision" value="approve">
    <div class="ta-dialog-head">
      <span class="ta-dialog-icon go"><svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg></span>
      <div>
        <h3 id="title-approve-title">Approve this new title?</h3>
        <p class="ta-dialog-sub" id="title-approve-sub"></p>
      </div>
    </div>
    <div class="ta-dialog-body">
      <p class="ta-title" id="title-approve-text"></p>
      <p class="ta-text" style="color:var(--text2)">It becomes the team's project title and they're told right away.</p>
    </div>
    <div class="ta-dialog-foot">
      <button type="button" class="ta-btn" onclick="closeModal('title-approve-modal')">Cancel</button>
      <button type="submit" class="ta-btn go">Approve</button>
    </div>
  </form>
</div>

<!-- Ask for another title -->
<div class="modal-overlay" id="title-decline-modal">
  <form class="ta-dialog" style="width:520px" role="dialog" aria-modal="true" aria-labelledby="title-decline-title" method="POST" id="title-decline-form" action="#" onsubmit="return lockSubmit(this)">
    @csrf
    @method('PATCH')
    <input type="hidden" name="decision" value="decline">
    <div class="ta-dialog-head">
      <span class="ta-dialog-icon warn"><svg viewBox="0 0 24 24"><path d="M4 12a8 8 0 1 0 2.3-5.7M4 4v5h5"/></svg></span>
      <div>
        <h3 id="title-decline-title">Ask for another title?</h3>
        <p class="ta-dialog-sub" id="title-decline-sub"></p>
      </div>
    </div>
    <div class="ta-dialog-body">
      <label class="ta-field" for="title-decline-note">What should change? <span>(the team reads this)</span>
        <textarea id="title-decline-note" name="note" minlength="10" maxlength="1000" required oninput="validateTitleDecline()" placeholder="e.g. Talk to your consultant about the scope, then send a narrower title."></textarea>
      </label>
      <div class="ta-hint"><span>At least 10 characters.</span><span id="title-decline-count">0 / 1000</span></div>
    </div>
    <div class="ta-dialog-foot">
      <button type="button" class="ta-btn" onclick="closeModal('title-decline-modal')">Cancel</button>
      <button type="submit" class="ta-btn stop-solid" id="btn-title-decline">Ask for another</button>
    </div>
  </form>
</div>

<div class="msg-toast" id="msg-toast" role="status"></div>

@include('instructor.partials.skeleton-toggle', ['revealDisplay' => 'flex'])

<script>
const assignmentData = {{ Js::from($assignmentData) }};
const titleData = {{ Js::from($titleData) }};
const SLOTS = { used: {{ (int) $currentSlots }}, max: {{ (int) $maxSlots }} };
const SLOTS_FULL = SLOTS.max > 0 && SLOTS.used >= SLOTS.max;
const ASSIGNMENTS_URL = {{ Js::from(url('instructor/assignments')) }};
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

let current = null;      // { kind: 'asg'|'title', id }
let activeTab = null;

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function el(tag, attrs, text) {
  const n = document.createElement(tag);
  Object.entries(attrs || {}).forEach(([k, v]) => {
    if (k === 'class') n.className = v; else if (k === 'style') n.style.cssText = v; else n.setAttribute(k, v);
  });
  if (text !== undefined && text !== null) n.textContent = text;
  return n;
}
const ICON = {
  check: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg>',
  file: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>',
  trash: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>',
};
function button(label, cls, onClick, icon) {
  const b = el('button', { type: 'button', class: 'ta-btn ' + (cls || '') });
  if (icon) b.insertAdjacentHTML('beforeend', ICON[icon]);
  b.appendChild(document.createTextNode(label));
  b.addEventListener('click', onClick);
  return b;
}
function initials(name) {
  const t = (name || 'T').trim();
  return t.charAt(0).toUpperCase() + t.charAt(1).toLowerCase();
}

// ── tabs, search, role filter ─────────────────────────────────────────────
function rowsIn(tab) { return Array.from(document.querySelectorAll('#tab-' + tab + ' .ta-row')); }

function switchTab(tab) {
  activeTab = tab;
  ['pending', 'approved', 'rejected'].forEach((t) => {
    const on = t === tab;
    document.getElementById('tab-btn-' + t).classList.toggle('active', on);
    document.getElementById('tab-btn-' + t).setAttribute('aria-selected', on ? 'true' : 'false');
    const panel = document.getElementById('tab-' + t);
    panel.hidden = !on;
    panel.style.display = on ? 'contents' : 'none';
  });
  applyFilter();
  selectFirstVisible();
}

function applyFilter() {
  if (!activeTab) return;
  const q = document.getElementById('ta-search').value.trim().toLowerCase();
  const role = document.getElementById('ta-role').value;
  let shown = 0;
  rowsIn(activeTab).forEach((r) => {
    const ok = (!q || (r.dataset.search || '').includes(q)) && (!role || r.dataset.role === role);
    r.hidden = !ok;
    if (ok) shown++;
  });
  const none = document.querySelector('#tab-' + activeTab + ' .ta-nomatch');
  if (none) none.hidden = shown > 0 || rowsIn(activeTab).length === 0;
}

function markSelected(kind, id) {
  document.querySelectorAll('.ta-row').forEach((r) => {
    r.classList.toggle('is-selected', r.dataset.kind === kind && Number(r.dataset.id) === Number(id));
  });
}

function selectFirstVisible() {
  const first = rowsIn(activeTab).find((r) => !r.hidden);
  if (!first) { showEmptyPanel(); return; }
  // Picked for them, not by them: the phone stays at the top of the queue.
  if (first.dataset.kind === 'title') selectTitle(Number(first.dataset.id), false); else selectAssignment(Number(first.dataset.id), false);
}

function showEmptyPanel() {
  current = null;
  document.getElementById('asg-empty').style.display = 'block';
  document.getElementById('asg-detail').style.display = 'none';
}

// The whole row picks the team, not only the name.
document.addEventListener('click', (e) => {
  const row = e.target.closest('.ta-row');
  if (!row || e.target.closest('.ta-row-btn')) return;
  row.querySelector('.ta-row-btn').click();
});

// ── the panel beside the queue ────────────────────────────────────────────
// Filled with DOM APIs and textContent only: team names, titles and messages
// are typed by students and never become markup.
function resetPanel() {
  document.getElementById('asg-empty').style.display = 'none';
  document.getElementById('asg-detail').style.display = 'flex';
  ['p-next', 'p-replaces-wrap', 'p-dup-wrap', 'asg-reason-wrap', 'p-rec', 'p-wait', 'p-email'].forEach((id) => {
    document.getElementById(id).style.display = 'none';
  });
  document.getElementById('p-people-wrap').style.display = 'flex';
  document.getElementById('p-message-wrap').style.display = 'flex';
  document.getElementById('p-proposal').replaceChildren();
  document.getElementById('asg-acts').replaceChildren();
}

function setStatusChip(kind, label) {
  const s = document.getElementById('p-status');
  s.className = 'ta-chip ' + kind;
  s.textContent = label;
}

function revealPanelOnPhone() {
  if (window.matchMedia('(max-width: 900px)').matches) {
    document.getElementById('asg-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

function selectAssignment(id, reveal = true) {
  const d = assignmentData[id];
  if (!d) return;
  current = { kind: 'asg', id: id };
  markSelected('asg', id);
  resetPanel();

  document.getElementById('p-tile').textContent = initials(d.team);
  document.getElementById('p-team').textContent = d.team;
  const sub = document.getElementById('p-sub');
  sub.replaceChildren(document.createTextNode(d.status === 'pending' ? 'wants you as their ' : 'asked you to be their '), el('strong', {}, (d.role || 'expert').toLowerCase()));

  const labels = { pending: ['pending', 'Waiting for you'], approved: ['approved', 'Approved'], rejected: ['rejected', 'Declined'] };
  const st = labels[d.status] || labels.pending;
  setStatusChip(st[0], st[1]);
  if (d.status === 'pending' && d.stale) {
    const w = document.getElementById('p-wait');
    w.textContent = 'Waiting ' + d.waiting;
    w.style.display = 'inline-flex';
  }
  document.getElementById('p-date').textContent = (d.status === 'rejected' ? 'Declined ' + d.decided : 'Applied ' + d.date);

  if (d.status === 'approved') document.getElementById('p-next').style.display = 'flex';

  document.getElementById('p-title-label').textContent = 'Project title';
  const title = document.getElementById('p-title');
  const t = d.approved_title || (d.project && d.project !== 'No project title' ? d.project : '');
  title.textContent = t || 'No project title yet. They applied before their pre-proposal was recommended.';
  title.classList.toggle('none', !t);
  if (d.approved_title) document.getElementById('p-rec').style.display = 'inline-flex';

  const prop = document.getElementById('p-proposal');
  if (d.proposal) {
    prop.appendChild(button(d.approved_title ? 'View their pre-proposal' : 'View their proposal', 'wide', () => openPdfPreview('asg', id), 'file'));
  } else {
    prop.appendChild(el('p', { class: 'ta-muted' }, 'No proposal attached'));
  }

  const leader = document.getElementById('p-leader');
  leader.replaceChildren(el('strong', {}, d.leader || '—'), document.createTextNode(' · Leader'));
  if (d.leader_email) {
    const mail = document.getElementById('p-email');
    mail.href = 'mailto:' + d.leader_email;
    mail.style.display = 'inline';
  }
  const members = document.getElementById('p-members');
  members.replaceChildren();
  if (d.members && d.members.length) {
    d.members.forEach((m) => members.appendChild(el('div', { class: 'ta-text' }, m.name)));
  } else {
    members.appendChild(el('p', { class: 'ta-muted' }, 'No other members listed yet'));
  }

  document.getElementById('p-message').textContent = d.message || 'They did not write one.';

  if (d.rejection_reason) {
    document.getElementById('asg-reason-wrap').style.display = 'flex';
    document.getElementById('asg-reason').textContent = d.rejection_reason;
  }

  // Which buttons belong here depends on where the application got to: a
  // pending one is decided, an approved one stays, a declined one can be
  // cleared off the list.
  const acts = document.getElementById('asg-acts');
  if (d.status === 'pending') {
    if (SLOTS_FULL) {
      acts.appendChild(el('p', { class: 'ta-note warn' }, 'All ' + SLOTS.max + ' of your slots are used, so you can\'t approve another team right now.'));
    }
    const pair = el('div', { class: 'ta-pair' });
    pair.appendChild(button('Decline', 'stop', () => openRejectModal(id)));
    const ok = button('Approve', 'go', () => openApproveModal(id), 'check');
    if (SLOTS_FULL) ok.disabled = true;
    pair.appendChild(ok);
    acts.appendChild(pair);
    if (!SLOTS_FULL) {
      const open = SLOTS.max > 0 ? SLOTS.max - SLOTS.used : null;
      acts.appendChild(el('p', { class: 'ta-note' }, (open !== null ? 'Approving uses 1 of your ' + open + ' open ' + (open === 1 ? 'slot' : 'slots') + '. ' : '') + 'You can\'t remove the team afterwards.'));
    }
  } else if (d.status === 'approved') {
    acts.appendChild(el('p', { class: 'ta-note' }, 'This team is assigned to you' + (SLOTS.max > 0 ? ' and uses 1 of your ' + SLOTS.max + ' slots.' : '.')));
  } else {
    const row = el('div', { style: 'display:flex;align-items:center;justify-content:space-between;gap:10px;' });
    row.appendChild(el('span', { class: 'ta-note', style: 'text-align:left' }, 'They can apply to you again.'));
    row.appendChild(button('Remove from list', 'ghost', () => openClearModal(id), 'trash'));
    acts.appendChild(row);
  }
  if (reveal) revealPanelOnPhone();
}

function selectTitle(id, reveal = true) {
  const t = titleData[id];
  if (!t) return;
  current = { kind: 'title', id: id };
  markSelected('title', id);
  resetPanel();

  document.getElementById('p-tile').textContent = initials(t.team);
  document.getElementById('p-team').textContent = t.team;
  const sub = document.getElementById('p-sub');
  sub.replaceChildren(document.createTextNode('your team needs you to approve a '), el('strong', {}, 'new title'));
  setStatusChip('newtitle', 'Needs your approval');
  if (t.stale) {
    const w = document.getElementById('p-wait');
    w.textContent = 'Waiting ' + t.waiting;
    w.style.display = 'inline-flex';
  }
  document.getElementById('p-date').textContent = 'Sent ' + t.date;

  document.getElementById('p-title-label').textContent = 'New title';
  const title = document.getElementById('p-title');
  title.textContent = t.title;
  title.classList.remove('none');
  document.getElementById('p-rec').style.display = 'inline-flex';
  if (t.document) {
    document.getElementById('p-proposal').appendChild(button('View their pre-proposal', 'wide', () => openPdfPreview('title', id), 'file'));
  }

  if (t.replaces && t.replaces.length) {
    const box = document.getElementById('p-replaces');
    box.replaceChildren();
    t.replaces.forEach((r) => box.appendChild(el('p', { class: 'ta-strike' }, r)));
    document.getElementById('p-replaces-wrap').style.display = 'flex';
  }
  document.getElementById('p-dup').textContent = dupLine(t);
  document.getElementById('p-dup-wrap').style.display = 'flex';

  document.getElementById('p-people-wrap').style.display = 'none';
  const msg = document.getElementById('p-message');
  document.getElementById('p-message-wrap').querySelector('.ta-sec-h').textContent = 'Why this came to you';
  msg.textContent = 'The panel sent this team back for a new title. Their CaPSA recommended the one above; as their adviser, you approve it before they continue.';

  const acts = document.getElementById('asg-acts');
  const pair = el('div', { class: 'ta-pair' });
  pair.appendChild(button('Ask for another', '', () => openTitleDeclineModal(id)));
  pair.appendChild(button('Approve', 'go', () => openTitleApproveModal(id), 'check'));
  acts.appendChild(pair);
  acts.appendChild(el('p', { class: 'ta-note' }, 'Asking for another sends the team your note on what to change.'));
  if (reveal) revealPanelOnPhone();
}

function dupLine(t) {
  if (!t.verdict) return 'No duplication report';
  return t.verdict + (t.top_score !== null && t.top_score !== undefined ? ' · top match ' + t.top_score + '%' : '');
}

// ── reading the document, with the decision beside it ─────────────────────
function openPdfPreview(kind, id) {
  const isTitle = kind === 'title';
  const d = isTitle ? titleData[id] : assignmentData[id];
  if (!d) return;
  const url = isTitle ? d.document : d.proposal;
  if (!url) return;

  document.getElementById('pv-title').textContent = d.team + (isTitle || d.approved_title ? ' · pre-proposal' : ' · proposal');
  document.getElementById('pv-sub').textContent = isTitle
    ? 'New title for your team · Recommended by their CaPSA'
    : ((d.approved_title ? 'Recommended by their CaPSA · ' : '') + (d.role || 'Expert') + ' request');
  document.getElementById('pv-open').href = url;
  document.getElementById('pdf-preview-frame').src = url + '#toolbar=0&navpanes=0';

  const side = document.getElementById('pv-side');
  side.replaceChildren();
  const block = (label, node) => {
    const s = el('div', { class: 'ta-sec' });
    s.appendChild(el('div', { class: 'ta-sec-h' }, label));
    s.appendChild(node);
    side.appendChild(s);
  };
  if (isTitle) {
    block('New title', el('p', { class: 'ta-title' }, d.title));
    if (d.replaces && d.replaces.length) {
      const box = el('div');
      d.replaces.forEach((r) => box.appendChild(el('p', { class: 'ta-strike' }, r)));
      block('Replaces', box);
    }
    block('Duplication check', el('p', { class: 'ta-text' }, dupLine(d)));
  } else {
    const kv = el('dl', { class: 'ta-kv', style: 'grid-template-columns:80px minmax(0,1fr);padding:10px 12px' });
    const add = (k, v) => { kv.appendChild(el('dt', {}, k)); kv.appendChild(el('dd', {}, v)); };
    add('Role', d.role);
    if (d.status === 'pending') add('Waiting', d.waiting);
    add('Leader', d.leader || '—');
    add('Members', d.members && d.members.length ? d.members.map((m) => m.name).join(', ') : 'None listed');
    block('Request', kv);
    block('Project title', el('p', { class: d.approved_title ? 'ta-title' : 'ta-muted' }, d.approved_title || 'No project title yet'));
    block('Their message', el('p', { class: 'ta-quote' }, d.message || 'They did not write one.'));
  }

  const acts = document.getElementById('pv-acts');
  const note = document.getElementById('pv-note');
  acts.replaceChildren();
  note.textContent = '';
  if (isTitle) {
    note.textContent = 'Asking for another sends the team your note on what to change.';
    acts.appendChild(button('Ask for another', '', () => openTitleDeclineModal(id)));
    acts.appendChild(button('Approve', 'go', () => openTitleApproveModal(id), 'check'));
  } else if (d.status === 'pending') {
    note.textContent = SLOTS_FULL ? 'All your slots are used, so you can\'t approve right now.' : 'Approving can\'t be undone, so read it first.';
    acts.appendChild(button('Decline', 'stop', () => openRejectModal(id)));
    const ok = button('Approve', 'go', () => openApproveModal(id), 'check');
    if (SLOTS_FULL) ok.disabled = true;
    acts.appendChild(ok);
  } else {
    acts.appendChild(button('Close', '', closePdfPreview));
  }
  openModal('pdf-preview-modal');
}

function closePdfPreview() {
  closeModal('pdf-preview-modal');
  // Stop the PDF from continuing to load/render in the background once closed.
  document.getElementById('pdf-preview-frame').src = 'about:blank';
}

// ── after a decision: the next request opens, and the result is said ─────
function nextInQueueAfter(kind, id) {
  const rows = rowsIn('pending');
  const i = rows.findIndex((r) => r.dataset.kind === kind && Number(r.dataset.id) === Number(id));
  const next = rows[i + 1] || rows[i - 1];
  return next ? next.dataset.kind + ':' + next.dataset.id : '';
}
function reloadWith(message, selectKey) {
  try {
    sessionStorage.setItem('ta-flash', message || '');
    if (selectKey) sessionStorage.setItem('ta-select', selectKey); else sessionStorage.removeItem('ta-select');
  } catch (e) {}
  location.reload();
}
function lockSubmit(form) {
  // One click, one decision: a double click cannot send the form twice.
  const b = form.querySelector('button[type="submit"]');
  if (b) { b.disabled = true; b.textContent = 'Saving…'; }
  try { sessionStorage.setItem('ta-select', nextInQueueAfter('title', current ? current.id : 0)); } catch (e) {}
  return true;
}

async function send(method, id, body) {
  const r = await fetch(ASSIGNMENTS_URL + '/' + id, {
    method: method,
    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  let data = {};
  try { data = await r.json(); } catch (e) {}
  if (!r.ok && !data.message) data.message = 'Something went wrong. Please try again.';
  return data;
}

// ── approve ───────────────────────────────────────────────────────────────
let currentApproveId = null;
function openApproveModal(id) {
  const d = assignmentData[id];
  if (!d || SLOTS_FULL) return;
  currentApproveId = id;
  document.getElementById('approve-title').textContent = 'Approve ' + d.team + ' as your ' + (d.role || 'expert').toLowerCase() + '?';
  const kv = document.getElementById('approve-detail');
  kv.replaceChildren();
  const add = (k, v) => { kv.appendChild(el('dt', {}, k)); kv.appendChild(el('dd', {}, v)); };
  add('Team', d.team);
  add('Role', d.role);
  add('Leader', d.leader || '—');
  if (SLOTS.max > 0) add('Your slots', SLOTS.used + ' → ' + (SLOTS.used + 1) + ' of ' + SLOTS.max + ' used');
  const btn = document.getElementById('btn-confirm-approve');
  btn.disabled = false;
  btn.textContent = 'Approve';
  openModal('approve-modal');
  btn.focus();
}

async function confirmApprove() {
  if (!currentApproveId) return;
  const btn = document.getElementById('btn-confirm-approve');
  if (btn.disabled) return;
  btn.disabled = true;
  btn.textContent = 'Approving…';
  const d = assignmentData[currentApproveId];
  const next = nextInQueueAfter('asg', currentApproveId);
  try {
    const data = await send('PATCH', currentApproveId, { status: 'approved' });
    if (data.success) {
      reloadWith(d.team + ' approved. They\'ve been told.', next);
      return;
    }
    closeModal('approve-modal');
    showToast(data.message || 'Failed to approve', 'error');
  } catch (e) {
    closeModal('approve-modal');
    showToast('Network error. Please try again.', 'error');
  }
  btn.disabled = false;
  btn.textContent = 'Approve';
}

// ── decline ───────────────────────────────────────────────────────────────
let currentRejectId = null;
function openRejectModal(id) {
  const d = assignmentData[id];
  if (!d) return;
  currentRejectId = id;
  document.getElementById('reject-title').textContent = 'Decline ' + d.team + '?';
  document.getElementById('reject-modal-sub').textContent = 'They asked you to be their ' + (d.role || 'expert').toLowerCase() + '. Tell them why, so they know whether to fix something and apply again, or look for someone else.';
  document.getElementById('reject-reason').value = '';
  document.querySelectorAll('#reject-quick button').forEach((b) => b.setAttribute('aria-pressed', 'false'));
  validateReject();
  document.getElementById('btn-confirm-reject').textContent = 'Decline request';
  openModal('reject-modal');
  document.getElementById('reject-reason').focus();
}

document.querySelectorAll('#reject-quick button').forEach((b) => {
  b.addEventListener('click', () => {
    document.querySelectorAll('#reject-quick button').forEach((x) => x.setAttribute('aria-pressed', x === b ? 'true' : 'false'));
    const box = document.getElementById('reject-reason');
    box.value = b.dataset.text;
    validateReject();
    box.focus();
  });
});

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
  document.getElementById('reject-count').textContent = document.getElementById('reject-reason').value.length + ' / 500';
}

async function confirmReject() {
  if (!currentRejectId) return;
  const reason = document.getElementById('reject-reason').value.trim();
  if (reason.length < 20) return;
  const btn = document.getElementById('btn-confirm-reject');
  if (btn.disabled) return;
  btn.disabled = true;
  btn.textContent = 'Declining…';
  const d = assignmentData[currentRejectId];
  const next = nextInQueueAfter('asg', currentRejectId);
  try {
    const data = await send('PATCH', currentRejectId, { status: 'rejected', reason: reason });
    if (data.success) {
      reloadWith(d.team + ' declined. They\'ve been told why.', next);
      return;
    }
    showToast(data.message || 'Failed to decline', 'error');
  } catch (e) {
    showToast('Network error. Please try again.', 'error');
  }
  btn.disabled = false;
  btn.textContent = 'Decline request';
}

// ── clear a declined request ──────────────────────────────────────────────
let currentClearId = null;
function openClearModal(id) {
  const d = assignmentData[id];
  if (!d || d.status !== 'rejected') return;
  currentClearId = id;
  document.getElementById('clear-title').textContent = 'Remove ' + d.team + ' from your list?';
  document.getElementById('clear-sub').textContent = d.role + ' request · declined ' + d.decided;
  const btn = document.getElementById('btn-confirm-clear');
  btn.disabled = false;
  btn.textContent = 'Remove from list';
  openModal('clear-modal');
}

async function confirmClear() {
  if (!currentClearId) return;
  const btn = document.getElementById('btn-confirm-clear');
  if (btn.disabled) return;
  btn.disabled = true;
  btn.textContent = 'Removing…';
  try {
    const data = await send('DELETE', currentClearId, {});
    if (data.success) {
      try { sessionStorage.setItem('ta-tab', 'rejected'); } catch (e) {}
      reloadWith(data.message, '');
      return;
    }
    showToast(data.message || 'Failed to remove', 'error');
  } catch (e) {
    showToast('Network error. Please try again.', 'error');
  }
  btn.disabled = false;
  btn.textContent = 'Remove from list';
}

// ── new titles ────────────────────────────────────────────────────────────
function openTitleApproveModal(id) {
  const t = titleData[id];
  if (!t) return;
  current = { kind: 'title', id: id };
  const form = document.getElementById('title-approve-form');
  form.action = t.update_url;
  const b = form.querySelector('button[type="submit"]');
  b.disabled = false;
  b.textContent = 'Approve';
  document.getElementById('title-approve-title').textContent = 'Approve ' + t.team + '\'s new title?';
  document.getElementById('title-approve-sub').textContent = 'Recommended by their CaPSA · duplication check: ' + dupLine(t);
  document.getElementById('title-approve-text').textContent = t.title;
  openModal('title-approve-modal');
  b.focus();
}

function openTitleDeclineModal(id) {
  const t = titleData[id];
  if (!t) return;
  current = { kind: 'title', id: id };
  const form = document.getElementById('title-decline-form');
  form.action = t.update_url;
  document.getElementById('title-decline-title').textContent = 'Ask ' + t.team + ' for another title?';
  document.getElementById('title-decline-sub').textContent = 'They were sent back for a new title, and this is the one their CaPSA recommended. Say what should change.';
  document.getElementById('title-decline-note').value = '';
  validateTitleDecline();
  const b = document.getElementById('btn-title-decline');
  b.textContent = 'Ask for another';
  openModal('title-decline-modal');
  document.getElementById('title-decline-note').focus();
}

function validateTitleDecline() {
  const v = document.getElementById('title-decline-note').value;
  document.getElementById('btn-title-decline').disabled = v.trim().length < 10;
  document.getElementById('title-decline-count').textContent = v.length + ' / 1000';
}

// ── messages ──────────────────────────────────────────────────────────────
function showToast(msg, type) {
  if (window.ctToast) { return ctToast(msg, type); }
  const toast = document.getElementById('msg-toast');
  toast.textContent = msg;
  toast.className = 'msg-toast show ' + type;
  clearTimeout(showToast.t);
  showToast.t = setTimeout(() => toast.classList.remove('show'), 4000);
}

// ── first paint ───────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  let flash = '', select = '', tab = '';
  try {
    flash = sessionStorage.getItem('ta-flash') || '';
    select = sessionStorage.getItem('ta-select') || '';
    tab = sessionStorage.getItem('ta-tab') || '';
    sessionStorage.removeItem('ta-flash');
    sessionStorage.removeItem('ta-select');
    sessionStorage.removeItem('ta-tab');
  } catch (e) {}

  // Open on the queue when something is waiting; otherwise on your teams.
  const hasQueue = rowsIn('pending').length > 0;
  switchTab(tab || (hasQueue || !rowsIn('approved').length ? 'pending' : 'approved'));

  if (select) {
    const [kind, id] = select.split(':');
    const row = document.querySelector('#tab-' + activeTab + ' .ta-row[data-kind="' + kind + '"][data-id="' + Number(id) + '"]');
    if (row) { if (kind === 'title') selectTitle(Number(id)); else selectAssignment(Number(id)); }
  }

  @if (session('status'))
    flash = flash || {{ Js::from(session('status')) }};
  @endif
  if (flash) showToast(flash, 'success');
  @if ($errors->has('note'))
    showToast({{ Js::from($errors->first('note')) }}, 'error');
  @endif
});

// ── Mobile Sidebar ──
function toggleSidebar() { document.body.classList.toggle('sidebar-open'); }
function closeSidebar() { document.body.classList.remove('sidebar-open'); }
</script>

@include('instructor.partials.expert-profile')
@include('partials.escape-closes-modals')
</body>
</html>
