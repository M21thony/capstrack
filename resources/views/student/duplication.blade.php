<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content" />
<title>Pre-Proposal Submission - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
@vite('resources/student-ui/styles.css')
<style nonce="{{ Vite::cspNonce() }}">
@keyframes spin { to { transform: rotate(360deg); } }

.dup-layout {
  display: grid;
  grid-template-columns: 320px 1fr 280px;
  gap: 14px;
  /* Measured directly via Playwright at a 1440x900 viewport rather than
     estimated: .dup-layout's real top offset is 222px and .content has
     26px of bottom padding, so 900 - 222 - 26 = 652px is the exact
     height that reaches the bottom with no overflow and no dead gap -
     hence a 248px offset (900 - 652). This holds at other viewport
     heights too since it's a flat pixel offset, not a hardcoded height. */
  height: calc(100vh - 248px);height: calc(100dvh - 248px);
  min-height: 480px;
}

/* A phone has room for one column: the check, then the matches, then the
   expert, one under the other, each as tall as it needs. */
@media (max-width: 900px) {
  .dup-layout { grid-template-columns: minmax(0, 1fr); height: auto; min-height: 0; }
  .dup-layout > * { min-height: 0; }
  #matches-panel { min-height: 280px; }
}

.dup-left {
  display: flex;
  flex-direction: column;
  gap: 14px;
  overflow: hidden;
}

.dup-mid {
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.dup-portrait {
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.matches-scroll {
  overflow-y: auto;
  flex: 1;
  padding-right: 4px;
}

.matches-scroll::-webkit-scrollbar { width: 4px; }
.matches-scroll::-webkit-scrollbar-track { background: transparent; }
.matches-scroll::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

.upload-zone.has-file { border-color: var(--primary, #5b5bd6); background: var(--bg3); }

/* Your pre-proposals: one block per saved title, the card edge to edge. */
.pp-card{padding:0}
.pp-card>.card-header{padding:24px 24px 14px;margin:0;border-bottom:1px solid var(--border)}
.pp{padding:11px 24px;border-top:1px solid var(--border)}
.pp:first-child{border-top:none}
.pp-line{display:flex;gap:8px;align-items:flex-start}
.pp .t{font-size:12px;font-weight:600;line-height:1.4;color:var(--text);min-width:0}
.pp .t.is-rejected{color:var(--text3);text-decoration:line-through}
.pp .s{font-size:11.5px;font-variant-numeric:tabular-nums;font-weight:700;margin-top:5px}
.pp .st{font-size:11.5px;color:var(--text2);line-height:1.5;margin-top:3px}
.pp .lk{margin-top:6px;display:flex;align-items:center;gap:14px}
.pp .lk .match-abstract-toggle{margin:0}
.pick{width:15px;height:15px;margin-top:1px;flex-shrink:0;accent-color:var(--accent3);cursor:pointer}
.pp-chosen{display:inline-flex;align-items:center;gap:5px;font-weight:700;color:var(--green,#16a34a)}
.pp-row.is-chosen{border-left:3px solid var(--green,#16a34a);padding-left:21px}
/* Still readable, just plainly not the one being worked on. */
.pp-row.is-set-aside{opacity:.62}
/* The one small text button left on this page: Remove, on a saved title. */
.match-abstract-toggle {
  align-self: flex-start;
  margin-top: 6px;
  padding: 0;
  border: none;
  background: none;
  cursor: pointer;
  font-family: 'DM Sans','DM Sans Fallback', sans-serif;
  font-size: 10px;
  font-weight: 600;
  color: var(--primary, #5b5bd6);
}
.match-abstract-toggle:hover { text-decoration: underline; }

.pp-field { margin-bottom: 16px; min-width: 0; }
.pp-field:last-child { margin-bottom: 0; }
.pp-field > label, .pp-tags legend { display: block; font-size: 12.5px; font-weight: 600; color: var(--text); margin-bottom: 6px; }
.pp-field input[type="text"], .pp-field select, .pp-field textarea {
  width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid var(--border);
  background: var(--bg); color: var(--text); font-size: 13.5px; line-height: 1.5; box-sizing: border-box; font-family: inherit;
  transition: border-color .15s, box-shadow .15s;
}
.pp-field input[type="text"]:hover, .pp-field select:hover, .pp-field textarea:hover { border-color: var(--text3); }
.pp-field input[type="text"]:focus, .pp-field select:focus, .pp-field textarea:focus {
  outline: none; border-color: var(--accent, #c9a82c); box-shadow: 0 0 0 3px rgba(201,168,44,.18);
}
.pp-field textarea { min-height: 76px; resize: vertical; }
.pp-field textarea#title { min-height: 0; font-weight: 600; font-size: 14.5px; }
.pp-help { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; font-size: 11.5px; color: var(--text3); margin-top: 6px; line-height: 1.45; }
.pp-counter { flex-shrink: 0; font-variant-numeric: tabular-nums; font-weight: 600; color: var(--text2); }
.pp-counter.over { color: var(--red); }
.pp-counter.at-limit { color: var(--accent3, #8a6e10); }
.pp-error { font-size: 12px; color: var(--red); margin-top: 6px; line-height: 1.4; }
.pp-error:empty { display: none; }
.pp-field.has-error input[type="text"], .pp-field.has-error select, .pp-field.has-error textarea, .pp-field.has-error .pp-chipbox { border-color: var(--red); }
.pp-field.has-error .pp-pill span { border-color: var(--red); }
.pp-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
.pp-grid .pp-field:last-child { margin-bottom: 16px; }
.pp-section:last-child .pp-grid .pp-field:nth-last-child(-n+2) { margin-bottom: 0; }
#pp-sdgs { margin-bottom: 16px; }

/* Research agenda: chips and the box to add one, drawn as one field. */
.pp-chipbox { border: 1px solid var(--border); border-radius: 8px; background: var(--bg); padding: 6px; cursor: text; transition: border-color .15s, box-shadow .15s; }
.pp-chipbox:focus-within { border-color: var(--accent, #c9a82c); box-shadow: 0 0 0 3px rgba(201,168,44,.18); }
.pp-chipbox .pp-tag-list { margin: 0; }
.pp-chipbox .pp-tag-list:not(:empty) { margin-bottom: 6px; }
.pp-chip-add { display: flex; gap: 6px; align-items: center; }
.pp-field .pp-chip-add input[type="text"] { border: none; background: transparent; padding: 5px 6px; box-shadow: none; flex: 1; min-width: 0; }
.pp-suggest { display: flex; align-items: flex-start; gap: 8px; margin-top: 8px; font-size: 11.5px; color: var(--text3); }
.pp-suggest[hidden] { display: none; }
.pp-suggest > span { padding-top: 5px; flex-shrink: 0; }
.pp-suggest > div { display: flex; flex-wrap: wrap; gap: 6px; }
.pp-suggest-chip { border: 1px dashed var(--border); background: transparent; color: var(--text2); border-radius: 999px; padding: 4px 10px; font-size: 12px; font-family: inherit; cursor: pointer; text-align: left; }
.pp-suggest-chip:hover { border-style: solid; border-color: var(--accent, #c9a82c); color: var(--text); }

.pp-tags { border: none; padding: 0; margin: 0; min-width: 0; }
.pp-tags legend { padding: 0; }
.pp-required { font-size: 9.5px; font-weight: 700; letter-spacing: .3px; color: var(--red); background: var(--red-bg, rgba(220,38,38,.07)); padding: 1px 6px; border-radius: 999px; }
.pp-pills { display: flex; flex-wrap: wrap; gap: 6px; }
.pp-pill { position: relative; cursor: pointer; }
.pp-pill input { position: absolute; opacity: 0; width: 1px; height: 1px; }
.pp-pill span { display: inline-flex; align-items: center; min-height: 32px; padding: 5px 12px; border: 1px solid var(--border); border-radius: 999px; background: var(--bg2); font-size: 12px; color: var(--text2); transition: background .15s, border-color .15s, color .15s; }
.pp-pill:hover span { border-color: var(--text3); color: var(--text); }
.pp-pill input:checked + span { background: var(--accent, #c9a82c); border-color: var(--accent, #c9a82c); color: #18191f; font-weight: 600; }
.pp-pill input:checked + span::before { content: "✓"; margin-right: 5px; font-size: 11px; }
.pp-pill input:focus-visible + span { outline: 2px solid var(--accent3, #8a6e10); outline-offset: 2px; }
.pp-pill input:disabled + span { opacity: .45; cursor: not-allowed; }
@media (prefers-reduced-motion: reduce) { .pp-pill span { transition: none; } }

/* Turn-in bar at the foot of the Your Pre-Proposals card. */
.pp-submit-bar { flex-shrink: 0; margin-top: auto; border-top: 1px solid var(--border); padding: 13px 24px; background: var(--bg3); border-radius: 0 0 14px 14px; }
.pp-submit-q { font-size: 12px; color: var(--text2); line-height: 1.5; margin-bottom: 10px; }
.pp-submit-rejected { padding: 9px 11px; border-radius: 8px; background: var(--red-bg, rgba(220,38,38,.07)); color: var(--text); }
.pp-submit-rejected b { color: var(--red); }
.pp-submit-q b { color: var(--text); font-weight: 600; }
.pp-btn-submit { width: 100%; justify-content: center; gap: 7px; font-size: 12.5px; padding: 9px 12px; background: var(--navy-mid); color: #fff; box-shadow: none; }
.pp-btn-submit:hover { background: #2a2d38; transform: none; box-shadow: none; }
.pp-btn-submit:disabled { background: var(--bg3); color: var(--text3); border: 1px solid var(--border); cursor: not-allowed; }
.pp-btn-unsubmit { width: 100%; justify-content: center; font-size: 12px; padding: 7px 12px; background: transparent; color: var(--text2); border: 1px solid var(--border); }
.pp-btn-unsubmit:hover { border-color: var(--red); color: var(--red); }
.pp-submit-note { font-size: 10.5px; color: var(--text3); margin-top: 7px; line-height: 1.5; text-align: center; }
.pp-submit-state { display: flex; align-items: center; gap: 9px; padding: 9px 11px; border-radius: 8px; background: var(--green-bg); color: var(--green); margin-bottom: 9px; }
.pp-submit-state svg { flex-shrink: 0; }
.pp-submit-state-title { font-size: 12px; font-weight: 700; }
.pp-submit-state-sub { font-size: 10.5px; opacity: 0.85; margin-top: 1px; }
.pp-badge-draft { background: var(--bg4); color: var(--text3); }
.pp-autosave { display: inline-flex; align-items: center; gap: 6px; margin-top: 6px; font-size: 11.5px; font-weight: 500; color: var(--text3); }
.pp-autosave::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: var(--border2); }
.pp-autosave.is-saved { color: var(--green); }
.pp-autosave.is-saved::before { background: var(--green); }
.pp-autosave.is-restored { color: var(--accent3); }
.pp-autosave.is-restored::before { background: var(--accent); }

/* Tooltip on the locked Submit button, on a wrapper because a disabled
   button emits no hover events of its own. */
.pp-submit-wrap { position: relative; display: block; outline: none; }
.pp-submit-wrap::after {
  content: attr(data-tip);
  position: absolute; bottom: calc(100% + 8px); left: 50%; transform: translate(-50%, 3px); z-index: 40;
  width: max-content; max-width: 230px; padding: 7px 10px; border-radius: 6px;
  background: var(--text, #1e2128); color: #fff; font-size: 11px; font-weight: 500;
  line-height: 1.45; text-align: left; white-space: normal;
  box-shadow: 0 6px 18px -4px rgba(0,0,0,0.35);
  opacity: 0; visibility: hidden; pointer-events: none;
  transition: opacity .13s ease, transform .13s ease, visibility .13s;
}
.pp-submit-wrap::before {
  content: ''; position: absolute; bottom: calc(100% + 3px); left: 50%; margin-left: -5px; z-index: 41;
  border: 5px solid transparent; border-top-color: var(--text, #1e2128);
  opacity: 0; visibility: hidden; pointer-events: none;
  transition: opacity .13s ease, visibility .13s;
}
.pp-submit-wrap:hover::after, .pp-submit-wrap:focus-visible::after {
  opacity: 1; visibility: visible; transform: translate(-50%, 0);
}
.pp-submit-wrap:hover::before, .pp-submit-wrap:focus-visible::before { opacity: 1; visibility: visible; }
@media (prefers-reduced-motion: reduce) {
  .pp-submit-wrap::after, .pp-submit-wrap::before { transition: none; }
}
.pp-tag-list { display: flex; flex-wrap: wrap; gap: 6px; }
.pp-tag { display: inline-flex; align-items: center; gap: 4px; max-width: 100%; background: var(--bg3); border: 1px solid var(--border); border-radius: 6px; padding: 3px 4px 3px 10px; font-size: 12.5px; color: var(--text); line-height: 1.4; }
.pp-tag > span { overflow-wrap: anywhere; }
.pp-tag button { flex-shrink: 0; width: 22px; height: 22px; border: none; border-radius: 4px; background: none; color: var(--text3); cursor: pointer; font-size: 15px; line-height: 1; padding: 0; }
.pp-tag button:hover { background: var(--bg4, rgba(0,0,0,.06)); color: var(--red); }

.pp-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(12,16,28,0.55); z-index: 1500; align-items: center; justify-content: center; padding: 24px; }
.pp-modal-overlay.show { display: flex; }

.pp-hint { font-size: 11px; color: var(--text3); margin-top: 4px; }
.pp-modal-box { width: 100%; max-width: 760px; max-height: 82vh;max-height: 82dvh; overflow-y: auto; position: relative; }
/* Every modal still scrolls with the wheel/trackpad - just no visible
   track running down it. */
.pp-modal-box { scrollbar-width: none; -ms-overflow-style: none; }
.pp-modal-box::-webkit-scrollbar { display: none; }
.pp-modal-close { position: absolute; top: 10px; right: 10px; border: none; background: none; font-size: 20px; line-height: 1; cursor: pointer; color: var(--text3); padding: 4px; }
.pp-modal-close:hover { color: var(--text); }

/* Pre-Proposal Details: a fixed header and footer, only the fields scroll. */
.pp-dialog { width: 100%; max-width: 780px; max-height: min(92vh, 900px);max-height: min(92dvh, 900px); display: flex; background: var(--bg2); border: 1px solid var(--border); border-radius: 14px; box-shadow: 0 24px 60px rgba(0,0,0,.28); overflow: hidden; }
.pp-dialog form { display: flex; flex-direction: column; width: 100%; min-height: 0; }
.pp-dialog-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 18px 22px 14px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.pp-dialog-head h2 { margin: 0; font-size: 17px; font-weight: 700; color: var(--text); }
.pp-dialog-head p { margin: 3px 0 0; font-size: 12.5px; color: var(--text3); }
.pp-x { flex-shrink: 0; width: 32px; height: 32px; display: grid; place-items: center; border: none; border-radius: 8px; background: none; color: var(--text3); cursor: pointer; }
.pp-x:hover { background: var(--bg3); color: var(--text); }
.pp-x svg { width: 18px; height: 18px; stroke: currentColor; stroke-width: 2; fill: none; stroke-linecap: round; }
.pp-dialog-body { flex: 1; min-height: 0; overflow-y: auto; padding: 6px 22px 20px; overscroll-behavior: contain; }
.pp-section { padding: 18px 0; border-bottom: 1px solid var(--border); }
.pp-section:last-child { border-bottom: none; padding-bottom: 4px; }
.pp-section h3 { display: flex; align-items: center; gap: 10px; margin: 0 0 14px; font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--text2); }
.pp-section h3 span { width: 22px; height: 22px; display: grid; place-items: center; border-radius: 50%; background: var(--accent, #c9a82c); color: #18191f; font-size: 11px; letter-spacing: 0; }
.pp-section-note { margin: -8px 0 14px; font-size: 12px; color: var(--text3); }
.pp-summary { margin-top: 14px; padding: 12px 14px; border-radius: 10px; border: 1px solid var(--red); background: var(--red-bg, rgba(220,38,38,.07)); color: var(--text); font-size: 12.5px; }
.pp-summary b { color: var(--red); font-weight: 600; }
.pp-summary ul { margin: 6px 0 0; padding-left: 18px; color: var(--text2); }
.pp-dialog-foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 22px; border-top: 1px solid var(--border); background: var(--bg2); flex-shrink: 0; }
.pp-foot-note { font-size: 12px; color: var(--text3); line-height: 1.5; min-width: 0; }
.pp-foot-note.is-left { color: var(--text2); }
.pp-foot-note.is-left b { color: var(--red); font-weight: 600; }
.pp-foot-note.is-done { color: var(--green); font-weight: 600; }
.pp-jump { padding: 0; border: 0; background: none; font: inherit; color: var(--text); text-decoration: underline; text-underline-offset: 2px; cursor: pointer; }
.pp-jump:hover { color: var(--red); }
.pp-jump:focus-visible { outline: 2px solid var(--accent3, #8a6e10); outline-offset: 2px; border-radius: 2px; }
.pp-btn.primary.is-blocked { background: #e9d98f; border-color: #e9d98f; color: #6b5a1a; }
.pp-foot-actions { display: flex; gap: 8px; }
.pp-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 36px; padding: 0 16px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; }
.pp-btn:hover { border-color: var(--text3); }
.pp-btn.small { min-height: 30px; padding: 0 12px; font-size: 12px; flex-shrink: 0; }
.pp-btn.primary { background: var(--accent, #c9a82c); border-color: var(--accent, #c9a82c); color: #18191f; }
.pp-btn.primary:hover { filter: brightness(1.05); }
.pp-btn:disabled { opacity: .6; cursor: progress; }
.pp-btn:focus-visible, .pp-suggest-chip:focus-visible, .pp-tag button:focus-visible, .pp-x:focus-visible { outline: 2px solid var(--accent3, #8a6e10); outline-offset: 2px; }

@media (max-width: 640px) {
  .pp-modal-overlay { padding: 0; align-items: stretch; }
  .pp-dialog { max-width: none; max-height: none; height: 100%; border-radius: 0; border: none; }
  .pp-dialog-head, .pp-dialog-body, .pp-dialog-foot { padding-left: 16px; padding-right: 16px; }
  .pp-grid { grid-template-columns: 1fr; }
  .pp-section:last-child .pp-grid .pp-field:nth-last-child(2) { margin-bottom: 16px; }
  .pp-dialog-foot { flex-wrap: wrap; }
  .pp-foot-note { width: 100%; }
  .pp-foot-actions { width: 100%; }
  .pp-foot-actions .pp-btn { flex: 1; }
}
@media (prefers-reduced-motion: reduce) {
  .pp-field input[type="text"], .pp-field select, .pp-field textarea, .pp-chipbox { transition: none; }
}

@keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
.sk-overlay { position: absolute; inset: 0; background: var(--bg); z-index: 10; }
.sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }

#pp-toast { position: fixed; bottom: 30px; right: 30px; background: var(--navy-dark); color: #fff; padding: 14px 22px; border-radius: 12px; font-size: 13px; font-weight: 500; z-index: 2000; box-shadow: 0 8px 30px rgba(0,0,0,0.3); opacity: 0; transform: translateY(12px); pointer-events: none; transition: opacity 0.25s ease, transform 0.25s ease; border-left: 4px solid transparent; display: flex; align-items: center; gap: 10px; }
#pp-toast.show { opacity: 1; transform: translateY(0); }
#pp-toast.success { border-left-color: var(--green); }
#pp-toast.error { border-left-color: var(--red); }
</style>
@include('student.partials.duplication-ui')
<x-dashboard.styles />
  @include('partials.render-whole')
</head>
<body>
@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Pre-Proposal Submission</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>

  <div class="content">
    {{-- A pre-proposal belongs to a team, so without one there is nothing to
         save. The page still opens - being redirected away with no
         explanation read as a broken link - and says what is missing. --}}
    @if (! $team)
      <div class="card" style="margin-bottom:16px;border-color:rgba(180,83,9,0.25);background:var(--amber-bg);">
        <div class="card-header"><span class="card-title">You are not in a team yet</span></div>
        <div style="font-size:12.5px;color:var(--amber);line-height:1.55">
          You can run a duplication check on a title now &mdash; the result is kept against your own
          record. Saving a pre-proposal and turning the set in belong to a team, so those need one.
          <a href="{{ route('student.team') }}" style="color:inherit;font-weight:700;text-decoration:underline;text-underline-offset:2px;">Join or create a team</a>
          when you are ready to save.
        </div>
      </div>
    @endif

    @if ($needsNewTitle)
      {{-- The panel asked for a new title: the old ones are a record now. --}}
      <div class="card" style="margin-bottom:16px;border-color:rgba(220,38,38,0.25);background:var(--red-bg);">
        <div class="card-header"><span class="card-title">Your panel asked for a new title</span></div>
        <div style="font-size:13px;color:var(--text2);line-height:1.6">
          Run the duplication check on your new pre-proposal and turn it in below, the same way as before. Your CaPSA recommends it first, then your adviser approves it. Your adviser, consultant and grammarian stay with your team.
          @if ($awaitingAdviser)
            <div style="margin-top:8px;padding:8px 10px;border-radius:8px;background:var(--bg2);border:1px solid var(--border)">
              Your CaPSA recommended <b>{{ $awaitingAdviser->title }}</b>. Waiting for your adviser{{ $adviserName ? ', '.$adviserName.',' : '' }} to approve it.
            </div>
          @endif
          @if ($retiredProposals->isNotEmpty())
            <div style="margin-top:8px;font-size:12px;color:var(--text3)">Set aside, kept as a record:</div>
            <ul style="margin:4px 0 0 18px;padding:0;font-size:12.5px">
              @foreach ($retiredProposals as $old)
                <li>
                  {{ $old->title }}
                  @if ($old->adviser_status === \App\Support\TitleRevision::DECLINED)
                    <div style="color:var(--red)">Your adviser asked for another title: “{{ $old->adviser_note }}”</div>
                  @endif
                </li>
              @endforeach
            </ul>
          @endif
        </div>
      </div>
    @endif

    @if ($canReviseTitle)
      {{-- Passed with revision: the wording may change until the final title form is in. --}}
      <div class="card" style="margin-bottom:16px;border-color:rgba(180,83,9,0.25);background:var(--amber-bg);">
        <div class="card-header"><span class="card-title">Revise your title</span></div>
        <div style="font-size:13px;color:var(--text2);line-height:1.6;margin-bottom:10px">
          Your panel passed your title with revision. You can reword it until you upload the Signed Final Project Title Form - after that it is final.
        </div>
        @if ($isLeader)
          <form method="POST" action="{{ route('student.pre-proposal.title.update') }}" style="display:flex;gap:8px;flex-wrap:wrap">
            @csrf
            @method('PATCH')
            <input class="input" name="title" value="{{ old('title', $team->project_title) }}" maxlength="150" required aria-label="Revised title" style="flex:1;min-width:260px">
            <button class="btn btn-primary" type="submit">Save revised title</button>
          </form>
          @error('title')<div style="font-size:12px;color:var(--red);margin-top:6px">{{ $message }}</div>@enderror
        @else
          <div style="font-size:12.5px;color:var(--text3)">Current title: <b style="color:var(--text)">{{ $team->project_title }}</b>. Your team leader can revise it.</div>
        @endif
      </div>
    @endif

    @if ($canRequestTitleChange || $pendingTitleChange)
      {{-- After a pass: part of the title changes only with the adviser's approval. --}}
      <div class="card" id="title-change" style="margin-bottom:16px">
        <div class="card-header"><span class="card-title">Change part of your title</span></div>
        @if ($pendingTitleChange)
          <div style="font-size:13px;color:var(--text2);line-height:1.6">
            Waiting for {{ $adviserName ?: 'your adviser' }} to approve changing your title to
            <b style="color:var(--text)">{{ $pendingTitleChange->new_title }}</b>.
            Your title stays <b style="color:var(--text)">{{ $team->project_title }}</b> until then.
          </div>
        @else
          <div style="font-size:13px;color:var(--text2);line-height:1.6;margin-bottom:10px">
            Your title passed as <b style="color:var(--text)">{{ $team->project_title }}</b>. If part of it has to change, ask {{ $adviserName ?: 'your adviser' }}. It changes only once they approve.
          </div>
          @if ($lastTitleChange && $lastTitleChange->status === \App\Models\TitleChangeRequest::DECLINED)
            <div role="status" style="font-size:12.5px;color:var(--red);margin-bottom:10px">
              Your adviser kept the title as it is{{ $lastTitleChange->adviser_note ? ': “'.$lastTitleChange->adviser_note.'”' : '.' }}
            </div>
          @endif
          @if ($isLeader)
            <form method="POST" action="{{ route('student.pre-proposal.title-change.store') }}" style="display:flex;flex-direction:column;gap:8px">
              @csrf
              <label for="tc-new" style="font-size:12.5px;font-weight:600">New title</label>
              <input class="input" id="tc-new" name="new_title" value="{{ old('new_title', $team->project_title) }}" maxlength="255" required>
              @error('new_title')<div style="font-size:12px;color:var(--red)">{{ $message }}</div>@enderror
              <label for="tc-reason" style="font-size:12.5px;font-weight:600">Why it needs to change</label>
              <textarea class="input" id="tc-reason" name="reason" rows="2" maxlength="500" required placeholder="For example: the Title Defense panel asked us to name the client.">{{ old('reason') }}</textarea>
              @error('reason')<div style="font-size:12px;color:var(--red)">{{ $message }}</div>@enderror
              <div><button class="btn btn-primary" type="submit">Send to {{ $adviserName ?: 'adviser' }}</button></div>
            </form>
          @else
            <div style="font-size:12.5px;color:var(--text3)">Your team leader can ask for a change.</div>
          @endif
        @endif
      </div>
    @endif

    @if ($approvedProposals->count() >= 2 && ! $team?->selected_pre_proposal_id)
      <div class="card" style="margin-bottom:16px;border-color:rgba(180,83,9,0.25);background:var(--amber-bg);">
        <div class="card-header"><span class="card-title">Choose Your Final Title</span></div>
        <div style="font-size:12px;color:var(--amber);margin-bottom:12px">
          More than one of your titles was recommended &mdash;
          @if ($isLeader) pick the one your team will proceed with. @else your team leader needs to pick which one to proceed with. @endif
        </div>
        @foreach ($approvedProposals as $approved)
          <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-top:1px solid var(--border)">
            <span style="font-size:13px;font-weight:500;color:var(--text)">{{ $approved->title }}</span>
            @if ($isLeader)
              {{-- Asked first. This is the title the team carries through the
                   rest of the year, and the other one is dropped by it. --}}
              <form method="POST" action="{{ route('student.pre-proposal.choose', $approved->id) }}"
                    id="choose-form-{{ $approved->id }}">
                @csrf
                <button type="button" class="btn btn-primary" style="font-size:12px;padding:6px 14px;"
                        onclick="confirmChooseTitle({{ $approved->id }}, {{ Js::from($approved->title) }})">Choose This One</button>
              </form>
            @endif
          </div>
        @endforeach
      </div>
    @endif

    {{-- The one number a team came here to see, said calmly. It used to sit
         beside a red and green bar with "Highly similar" showing before any
         scan had run, which read as a verdict already given. Now it is empty
         until a scan, then gives the closest match (its percentage keeps its
         colour), how many matches are close, some or little overlap, and
         what the closest ones share. Recommending a title is still the
         CaPSA's call, not a number's. --}}
    <div class="card verdict scan-card">
      <div class="sc-empty" id="sc-empty">
        <span class="sc-empty-ic" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </span>
        <div class="doc">
          <div class="t">No document checked yet</div>
          <div class="m">Upload your pre-proposal PDF to see which archived capstones are close to it, and what they share.</div>
        </div>
      </div>
      <div class="sc-result" id="sc-result" hidden>
        <div class="sc-score">
          <div class="bigscore" id="sim-pct"></div>
          <div class="sc-cap">closest match</div>
        </div>
        <div class="doc">
          <div class="sc-line">
            <span class="band" id="sc-band"></span>
            <span class="sc-counts" id="sc-counts"></span>
          </div>
          <div class="t" id="doc-title"></div>
          <div class="m" id="doc-meta"></div>
        </div>
        <a class="pbtn" id="full-report-btn" style="display:none;flex-shrink:0" href="#">
          See what is similar
          <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        {{-- The result survives a reload now, which is the point - and means
             there has to be a way to be rid of it. A panel that cannot be
             dismissed is its own kind of stuck. --}}
        <button type="button" class="btn" id="clear-result-btn" title="Clear this result" aria-label="Clear this result"
                style="display:none;flex-shrink:0;padding:6px 10px" onclick="clearScanResult()">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
    </div>

    <div class="dup-layout">
      <div class="dup-left">
        <div class="card" style="flex-shrink:0">
          <div class="card-header"><span class="card-title" style="display:inline-flex;align-items:center;gap:7px"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Check Document</span></div>

          {{-- The checker itself is always open. Running a title past the
               archive tells a team whether it has been done before, and that
               is worth knowing whether or not their set is turned in - it
               used to disappear the moment they submitted, taking the tool
               away exactly when they had a rejection to answer. Saving a
               result into the submitted set is the part that stays shut. --}}
            <form class="duplication-form" onsubmit="return false;">
              <div class="upload-zone" id="upload-zone" style="margin-bottom:10px;cursor:pointer;padding:22px 14px;border:1.5px dashed var(--border2,var(--border));background:var(--bg3)" onclick="document.getElementById('file-input').click()">
                <div class="upload-icon" style="margin-bottom:8px">
                  <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="var(--text3)" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                </div>
                <div style="font-size:12px;color:var(--text2);font-weight:500;margin-bottom:2px" id="upload-label">Drop a PDF or click to browse</div>
                <div style="font-size:10px;color:var(--text3)">PDF format only</div>
                <input type="file" id="file-input" accept="application/pdf" style="display:none" onchange="handleFileSelected()" />
              </div>
              <button type="button" class="btn btn-primary" style="width:100%;justify-content:center" id="check-btn" onclick="runCheck()">
                Run Similarity Check
              </button>
            </form>

            @if ($canSubmitMore)
              <button type="button" class="btn btn-primary" id="open-pp-form-btn" style="display:none;width:100%;justify-content:center;margin-top:10px" onclick="openPpFormModal()">
                Complete Pre-Proposal Details &rarr;
              </button>
            @else
              <div id="pp-locked-note" style="display:none;margin-top:10px;font-size:11.5px;color:var(--text2);background:var(--bg3);border:1px dashed var(--border);border-radius:8px;padding:10px 12px;line-height:1.5">
                Your titles are turned in, so this check is for your own reading &mdash;
                it has not been added to them. Unsubmit first if you want to keep it.
              </div>
            @endif

          <div style="margin-top:10px;padding:8px;background:var(--bg3);border-radius:8px;font-size:10px;color:var(--text3);line-height:1.5">
            Powered by Qwen 2.5 + nomic-embed-text · Local AI · Results may vary
          </div>
        </div>

        <div class="card" id="instructor-card" style="flex:1;overflow:hidden;display:flex;flex-direction:column">
          <div class="card-header" style="flex-shrink:0"><span class="card-title" style="display:inline-flex;align-items:center;gap:7px"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Suggested Experts</span></div>
          <div id="instructor-list" style="overflow-y:auto;flex:1">
            <div style="font-size:12px;color:var(--text3);text-align:center;padding:20px 0">Run a check to see suggestions</div>
          </div>
        </div>
      </div>

      <div class="card dup-mid" id="matches-panel">
        <div class="card-header" style="flex-shrink:0">
          <span class="card-title" style="display:inline-flex;align-items:center;gap:7px"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>Matched Capstone Projects</span>
          <span style="font-size:11px;color:var(--text3)" id="match-count"></span>
        </div>
        <div class="matches-scroll dup-rows" id="matches-list">
          <div style="font-size:12px;color:var(--text3);text-align:center;padding:40px 0">No matches yet</div>
        </div>
      </div>

      <div class="card dup-portrait pp-card">
        <div class="card-header" style="flex-shrink:0;flex-direction:column;align-items:flex-start;gap:2px">
          <span class="card-title" style="display:inline-flex;align-items:center;gap:7px"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>Your Pre-Proposals</span>
          <span style="font-size:11px;color:var(--text3);font-weight:400">
            {{ $proposals->count() }} {{ $turnedIn ? 'turned in' : 'saved' }}
            &middot; {{ $proposals->where('status', 'approved')->count() }} recommended
          </span>
        </div>
        <div style="overflow-y:auto;flex:1">
          @if ($proposals->isEmpty())
            <div style="font-size:12px;color:var(--text3);text-align:center;padding:20px 10px">
              No pre-proposals saved yet — run a check and save your first one.
            </div>
          @else
            @foreach ($proposals as $proposal)
              @php
                $check = $proposal->duplicationCheck;
                $rowChosen = $team?->selected_pre_proposal_id
                    && (int) $team->selected_pre_proposal_id === (int) $proposal->id;
                $rowSetAside = $team?->selected_pre_proposal_id && ! $rowChosen
                    && $proposal->status === 'approved';
              @endphp
              @php
                // A draft the leader could still choose to turn in. Nothing
                // to tick once the set is in, and nothing for a member to
                // tick at all.
                $pickable = $isLeader && ! $turnedIn && ! $proposal->submitted_at;
                $removable = $proposal->status !== 'approved' && ! $turnedIn;
              @endphp
              <div class="pp pp-row @if($rowChosen) is-chosen @elseif($rowSetAside) is-set-aside @endif">
                <div class="pp-line">
                  @if ($pickable)
                    {{-- Attached to the submit form further down the page by
                         its id, so unticking one is all it takes to leave a
                         title out. Before this the only way was to delete it,
                         which threw away its duplication report with it. --}}
                    <input type="checkbox" class="pick pp-pick" name="ids[]" value="{{ $proposal->id }}"
                           form="pp-submit-form" checked onchange="refreshSubmitCount()"
                           aria-label="Submit {{ $proposal->title }}">
                  @endif
                  <div @class(['t', 'is-rejected' => $proposal->status === 'rejected'])>{{ $proposal->title }}</div>
                </div>

                @if ($check)
                  <div class="s"><b class="sim-{{ \App\Support\SimilarityBand::of($check->top_score) }}">{{ $check->top_score }}%</b> closest match &middot; {{ \App\Support\SimilarityBand::label($check->top_score) }}</div>
                @endif

                {{-- One line saying where the title stands, in place of a badge. --}}
                <div class="st">
                  @if ($proposal->status === 'rejected')
                    <b style="color:var(--red)">Rejected</b>@if ($proposal->rejection_reason) &mdash; &ldquo;{{ $proposal->rejection_reason }}&rdquo;@endif
                  @elseif ($proposal->status === 'approved')
                    @if ($rowChosen)
                      <span class="pp-chosen">
                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        This is your team's title.
                      </span>
                    @elseif ($team?->selected_pre_proposal_id)
                      <b style="color:var(--green)">Recommended by your CaPSA</b> &mdash; not the title your team chose.
                    @else
                      <b style="color:var(--green)">Recommended by your CaPSA</b> &mdash; waiting for your adviser.
                    @endif
                  @elseif ($proposal->submitted_at)
                    <b>Submitted</b> &mdash; waiting for your CaPSA to review.
                  @else
                    <b>Draft</b> &mdash; not submitted yet.
                  @endif
                </div>
                @if ($proposal->status === 'approved' && $proposal->capsa_note)
                  <div class="st">Suggestion from your CaPSA: {{ $proposal->capsa_note }}</div>
                @endif

                @if ($check || $removable)
                  <div class="lk">
                    @if ($check)
                      <a class="golink" href="{{ route('student.duplication.report', $check->id) }}">
                        Similarity Report
                        <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                      </a>
                    @endif
                    @if ($removable)
                      <form method="POST" action="{{ route('student.pre-proposal.destroy', $proposal->id) }}" id="remove-form-{{ $proposal->id }}" style="display:inline">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="match-abstract-toggle" style="color:var(--red)" onclick="openRemoveConfirm({{ $proposal->id }})">Remove</button>
                      </form>
                    @endif
                  </div>
                @endif
              </div>
            @endforeach
          @endif
        </div>

        {{-- Turning in, the way Google Classroom does it: saving a title keeps
             a draft, and this is the separate act that hands the set to your
             CaPSA. Before this existed, saving the third title silently put
             the team under review. --}}
        <div class="pp-submit-bar">
          @if ($turnedIn)
            <div class="pp-submit-state">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              <div>
                <div class="pp-submit-state-title">Submitted for checking</div>
                @if ($turnedInAt)
                  <div class="pp-submit-state-sub">{{ \Illuminate\Support\Carbon::parse($turnedInAt)->format('M j, Y \a\t g:i A') }}</div>
                @endif
              </div>
            </div>

            @if ($isLeader && ! $reviewStarted)
              <form method="POST" action="{{ route('student.pre-proposal.submit') }}">
                @method('PATCH')
                <input type="hidden" name="status" value="draft">
                @csrf
                <button type="submit" class="btn pp-btn-unsubmit">Unsubmit</button>
              </form>
              <div class="pp-submit-note">You can still take this back until your CaPSA starts reviewing.</div>
            @elseif ($isLeader)
              <div class="pp-submit-note">Your CaPSA has started reviewing, so this can no longer be unsubmitted.</div>
            @else
              <div class="pp-submit-note">Your team leader submitted these for checking.</div>
            @endif
          @else
            @if ($allRejected)
              <div class="pp-submit-q pp-submit-rejected" role="status">
                <b>Your CaPSA did not recommend any of these titles.</b>
                Save new titles{{ $isLeader ? ', then submit them for checking again' : ' - your team leader submits them' }}. You can remove the rejected ones.
              </div>
            @endif
            @if (! $isLeader)
              <div class="pp-submit-note">Only your team leader can submit these for checking.</div>
            @elseif ($canTurnIn)
              <div class="pp-submit-q" id="pp-submit-note">
                <b>{{ $draftCount === 1 ? 'Your title is ticked.' : 'All '.$draftCount.' of your titles are ticked.' }}</b>
                You can take them back until your CaPSA starts reviewing.
              </div>
              {{-- Turning in fewer than the suggested number is a decision, not
                   an error, so it is asked about once rather than forbidden. --}}
              <form method="POST" action="{{ route('student.pre-proposal.submit') }}"
                    id="pp-submit-form"
                    onsubmit="return confirmSubmitCount(event)"
                    data-recommended="{{ $recommendedProposals }}">
                @method('PATCH')
                <input type="hidden" name="status" value="submitted">
                {{-- Says a selection was sent, so unticking everything is
                     told apart from sending nothing. --}}
                <input type="hidden" name="choose" value="1">
                @csrf
                <button type="submit" class="btn btn-primary pp-btn-submit" id="pp-submit-btn">
                  <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/></svg>
                  Submit for Checking
                </button>
              </form>
            @else
              <div class="pp-submit-note">{{ $allRejected ? 'Save a new title to submit.' : 'Save a pre-proposal title first.' }}</div>
            @endif
          @endif
        </div>
      </div>
    </div>

    @if ($canSubmitMore)
      @php
        // What the student typed comes back after a failed save, so a rejected
        // field never costs them the rest of the form.
        $ppOld = [
          'title' => old('title', ''),
          'sdg_goals' => \App\Support\SustainableDevelopmentGoals::split(old('sdg_goals', [])),
          'problem_statement' => old('problem_statement', ''),
          'proposed_solution' => old('proposed_solution', ''),
          'scope' => old('scope', ''),
          'limitations' => old('limitations', ''),
          'sdm' => old('sdm', 'Agile Development Methodology'),
          'clients' => old('clients', ''),
          'client_address' => old('client_address', ''),
          'client_representative' => old('client_representative', ''),
          'client_position' => old('client_position', ''),
        ];
        $ppOldTags = (array) old('tags', []);
        $ppSdgs = \App\Support\SustainableDevelopmentGoals::ALL;
        $ppErr = fn ($key) => $errors->first($key) ?: $errors->first($key.'.*');
        // Refused after the checks passed (a repeated title, a missing scan):
        // the details still come back, with the reason on top of the form.
        $ppRefused = ! $errors->any() && session()->has('error') && old('duplication_check_id') !== null;
      @endphp
      <div class="pp-modal-overlay" id="pp-form-modal-overlay" onclick="if (event.target === this) closePpFormModal()">
      <div class="pp-dialog" id="pp-form-card" role="dialog" aria-modal="true" aria-labelledby="pp-form-heading">
        <form method="POST" action="{{ route('student.pre-proposal.store') }}" id="pp-form" novalidate
              onsubmit="return ppFormIsComplete(this)">
          @csrf
          <input type="hidden" name="duplication_check_id" id="check-id" value="{{ old('duplication_check_id') }}">

          <header class="pp-dialog-head">
            <div>
              <h2 id="pp-form-heading">Pre-Proposal Details</h2>
              <p>Filled in from your PDF. Check each part, change what needs changing, then save.</p>
              {{-- Filled in by the draft script below: what you type is kept on
                   this device until the pre-proposal is saved. --}}
              <span class="pp-autosave" id="pp-autosave" aria-live="polite">Your answers save automatically as you type.</span>
            </div>
            <button type="button" class="pp-x" onclick="closePpFormModal()" aria-label="Close">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
          </header>

          <div class="pp-dialog-body">
            <div class="pp-summary" id="pp-summary" role="alert" @if (! $errors->any() && ! $ppRefused) hidden @endif>
              @if ($ppRefused)
                <b>{{ session('error') }}</b>
              @else
                <b>Some details need a look before this can be saved.</b>
                <ul id="pp-summary-list">
                  @foreach ($errors->keys() as $key)
                    <li>{{ $errors->first($key) }}</li>
                  @endforeach
                </ul>
              @endif
            </div>

            <section class="pp-section">
              <h3><span>1</span> Title and focus</h3>

              <div class="pp-field{{ $ppErr('title') ? ' has-error' : '' }}" data-field="title">
                <label for="title">Title</label>
                <textarea name="title" id="title" rows="2" class="pp-grow" oninput="updateWordCount(); clearFieldError(this)" aria-describedby="title-help title-error">{{ $ppOld['title'] }}</textarea>
                <div class="pp-help" id="title-help">
                  <span>Up to {{ \App\Http\Controllers\PreProposalController::TITLE_MAX_WORDS }} meaningful words. Filler words like "a", "of" and "for" don't count.</span>
                  <span class="pp-counter" id="word-counter"><span id="word-count">0</span> / {{ \App\Http\Controllers\PreProposalController::TITLE_MAX_WORDS }}</span>
                </div>
                <div class="pp-error" id="title-error">{{ $ppErr('title') }}</div>
              </div>

              {{-- A study often serves more than one goal, so each is a tick. --}}
              <fieldset class="pp-field pp-tags{{ $ppErr('sdg_goals') ? ' has-error' : '' }}" id="pp-sdgs" data-field="sdg_goals" aria-describedby="sdg-error">
                <legend>UN Sustainable Development Goals</legend>
                <div class="pp-help" style="margin:0 0 8px"><span>Tick every goal your study serves.</span><span class="pp-counter" id="pp-sdg-count" aria-live="polite">0 picked</span></div>
                <div class="pp-pills">
                  @foreach ($ppSdgs as $sdg)
                    <label class="pp-pill">
                      <input type="checkbox" name="sdg_goals[]" value="{{ $sdg }}" onchange="updateSdgCount(); clearFieldError(this)" @checked(in_array($sdg, $ppOld['sdg_goals'], true))>
                      <span>{{ $sdg }}</span>
                    </label>
                  @endforeach
                </div>
                <div class="pp-error" id="sdg-error">{{ $ppErr('sdg_goals') }}</div>
              </fieldset>

              <div class="pp-field{{ $ppErr('research_agenda') ? ' has-error' : '' }}" data-field="research_agenda">
                <label for="agenda-input">Research agenda and priority areas</label>
                <div class="pp-chipbox" onclick="document.getElementById('agenda-input').focus()">
                  <div class="pp-tag-list" id="agenda-list" aria-live="polite"></div>
                  <div class="pp-chip-add">
                    <input type="text" id="agenda-input" placeholder="Add a priority area" aria-describedby="agenda-help agenda-error">
                    <button type="button" class="pp-btn small" onclick="addAgendaFromInput()">Add</button>
                  </div>
                </div>
                <div class="pp-help" id="agenda-help"><span>Press Enter or Add after each one. Add as many as apply.</span></div>
                <div class="pp-suggest" id="agenda-suggest" hidden>
                  <span>From your PDF:</span>
                  <div id="agenda-suggest-list"></div>
                </div>
                <div class="pp-error" id="agenda-error">{{ $ppErr('research_agenda') }}</div>
                <div id="agenda-hidden"></div>
              </div>

              <fieldset class="pp-field pp-tags{{ $ppErr('tags') ? ' has-error' : '' }}" id="pp-tags" data-field="tags">
                <legend>Project tags</legend>
                <div class="pp-help" style="margin:0 0 8px"><span>What kind of project is this? Pick {{ \App\Support\ProjectTags::MIN }} to {{ \App\Support\ProjectTags::MAX }}.</span><span class="pp-counter" id="pp-tag-count" aria-live="polite">0 of {{ \App\Support\ProjectTags::MAX }}</span></div>
                <div class="pp-pills">
                  @foreach (\App\Support\ProjectTags::OPTIONS as $tag)
                    <label class="pp-pill">
                      <input type="checkbox" name="tags[]" value="{{ $tag }}" onchange="updateTagPills(); clearFieldError(this)" @checked(in_array($tag, $ppOldTags, true))>
                      <span>{{ $tag }}</span>
                    </label>
                  @endforeach
                </div>
                <div class="pp-error" id="tags-error">{{ $ppErr('tags') }}</div>
              </fieldset>
            </section>

            <section class="pp-section">
              <h3><span>2</span> The project</h3>
              <div class="pp-field{{ $ppErr('problem_statement') ? ' has-error' : '' }}" data-field="problem_statement">
                <label for="problem">Problem statement</label>
                <textarea name="problem_statement" id="problem" class="pp-grow" rows="3" oninput="clearFieldError(this)">{{ $ppOld['problem_statement'] }}</textarea>
                <div class="pp-error">{{ $ppErr('problem_statement') }}</div>
              </div>
              <div class="pp-field{{ $ppErr('proposed_solution') ? ' has-error' : '' }}" data-field="proposed_solution">
                <label for="solution">Proposed solution</label>
                <textarea name="proposed_solution" id="solution" class="pp-grow" rows="3" oninput="clearFieldError(this)">{{ $ppOld['proposed_solution'] }}</textarea>
                <div class="pp-error">{{ $ppErr('proposed_solution') }}</div>
              </div>
              <div class="pp-grid">
                <div class="pp-field{{ $ppErr('scope') ? ' has-error' : '' }}" data-field="scope">
                  <label for="scope">Scope</label>
                  <textarea name="scope" id="scope" class="pp-grow" rows="3" oninput="clearFieldError(this)">{{ $ppOld['scope'] }}</textarea>
                  <div class="pp-error">{{ $ppErr('scope') }}</div>
                </div>
                <div class="pp-field{{ $ppErr('limitations') ? ' has-error' : '' }}" data-field="limitations">
                  <label for="limitations">Limitations</label>
                  <textarea name="limitations" id="limitations" class="pp-grow" rows="3" oninput="clearFieldError(this)">{{ $ppOld['limitations'] }}</textarea>
                  <div class="pp-error">{{ $ppErr('limitations') }}</div>
                </div>
              </div>
              <div class="pp-field{{ $ppErr('sdm') ? ' has-error' : '' }}" data-field="sdm">
                <label for="sdm">Software development methodology</label>
                <input type="text" name="sdm" id="sdm" value="{{ $ppOld['sdm'] }}" oninput="clearFieldError(this)">
                <div class="pp-error">{{ $ppErr('sdm') }}</div>
              </div>
            </section>

            <section class="pp-section">
              <h3><span>3</span> The client</h3>
              <p class="pp-section-note">The organization the system is being built for.</p>
              <div class="pp-grid">
                <div class="pp-field{{ $ppErr('clients') ? ' has-error' : '' }}" data-field="clients">
                  <label for="clients">Organization</label>
                  <input type="text" name="clients" id="clients" value="{{ $ppOld['clients'] }}" oninput="clearFieldError(this)">
                  <div class="pp-error">{{ $ppErr('clients') }}</div>
                </div>
                <div class="pp-field{{ $ppErr('client_address') ? ' has-error' : '' }}" data-field="client_address">
                  <label for="client_address">Address</label>
                  <input type="text" name="client_address" id="client_address" value="{{ $ppOld['client_address'] }}" oninput="clearFieldError(this)">
                  <div class="pp-error">{{ $ppErr('client_address') }}</div>
                </div>
                <div class="pp-field{{ $ppErr('client_representative') ? ' has-error' : '' }}" data-field="client_representative">
                  <label for="client_representative">Representative's name</label>
                  <input type="text" name="client_representative" id="client_representative" value="{{ $ppOld['client_representative'] }}" oninput="clearFieldError(this)">
                  <div class="pp-error">{{ $ppErr('client_representative') }}</div>
                </div>
                <div class="pp-field{{ $ppErr('client_position') ? ' has-error' : '' }}" data-field="client_position">
                  <label for="client_position">Their position</label>
                  <input type="text" name="client_position" id="client_position" value="{{ $ppOld['client_position'] }}" oninput="clearFieldError(this)">
                  <div class="pp-error">{{ $ppErr('client_position') }}</div>
                </div>
              </div>
            </section>
          </div>

          <footer class="pp-dialog-foot">
            <span class="pp-foot-note" id="pp-foot-note">Every field is needed to save.</span>
            <div class="pp-foot-actions">
              <button type="button" class="pp-btn" onclick="closePpFormModal()">Cancel</button>
              <button type="submit" class="pp-btn primary" id="pp-save-btn">Save pre-proposal</button>
            </div>
          </footer>
        </form>
      </div>
      </div>
    @endif
  </div>
</div>

{{-- Handing your titles in is the one act that puts your team in front of
     your CaPSA, so it asks first - every time, not only when there are fewer
     than we suggest. It was a window.confirm(), which is the one dialog on
     this page that looks like nothing else on it. --}}
<div class="pp-modal-overlay" id="pp-submit-confirm" onclick="if (event.target === this) closeSubmitConfirm()">
  <div class="card pp-modal-box" style="max-width:430px;padding:22px 24px 20px">
    <div style="display:flex;align-items:flex-start;gap:13px">
      <span id="pp-sc-icon" style="width:38px;height:38px;border-radius:11px;flex-shrink:0;display:grid;place-items:center;background:var(--amber-bg);color:var(--amber)">
        {{-- Swapped for a tick when the team has turned in the number they
             were asked for. It used to be a hazard triangle either way, so
             doing the right thing was met with a warning sign. --}}
        <svg id="pp-sc-glyph-warn" viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
        <svg id="pp-sc-glyph-ok" viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" hidden><path d="M20 6 9 17l-5-5"/></svg>
      </span>
      <div style="min-width:0">
        <h3 id="pp-sc-title" style="margin:0 0 5px;font-size:16px;font-weight:700;color:var(--text)"></h3>
        <p id="pp-sc-body" style="margin:0;font-size:13px;line-height:1.55;color:var(--text2)"></p>
      </div>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:9px;margin-top:20px">
      <button type="button" class="btn btn-soft" onclick="closeSubmitConfirm()">Go back</button>
      <button type="button" class="btn btn-primary" id="pp-sc-ok">Submit</button>
    </div>
  </div>
</div>

<div class="pp-modal-overlay" id="check-loading">
  <div class="card pp-modal-box" style="max-width:360px;text-align:center;padding:30px 26px">
    <div style="width:46px;height:46px;margin:0 auto 18px;border:3px solid var(--border);border-top-color:var(--primary,#5b5bd6);border-radius:50%;animation:spin 0.8s linear infinite"></div>
    <div style="color:var(--text);font-size:14px;font-weight:600;margin-bottom:16px">Checking your document</div>
    <!-- Time-based, not a real progress feed from the backend - close
         enough in practice that showing it as real steps (checkmarks
         included, per how the mockup was designed) reads better than
         being pedantic about the distinction. -->
    <div id="loading-steps" style="text-align:left;border-top:1px solid var(--border);padding-top:14px;margin-bottom:6px"></div>
    <div id="loading-still-working" style="display:none;font-size:11px;color:var(--text3);margin-bottom:10px">Still working — some checks take a few minutes.</div>
    <button type="button" class="btn" style="width:100%;justify-content:center;background:transparent;border:1px solid var(--border);color:var(--text2)" onclick="cancelCheck()">Cancel Similarity Check</button>
  </div>
</div>

<div class="pp-modal-overlay" id="pp-confirm-modal" onclick="if (event.target === this) closePpConfirmModal()">
  <div class="card pp-modal-box" style="max-width:380px">
    <div style="width:40px;height:40px;border-radius:50%;background:var(--red-bg);display:flex;align-items:center;justify-content:center;margin-bottom:14px">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    </div>
    <div style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:20px" id="pp-confirm-message">Are you sure?</div>
    <div style="display:flex;gap:8px">
      <button type="button" class="btn" style="flex:1;justify-content:center;background:transparent;border:1px solid var(--border);color:var(--text2)" onclick="closePpConfirmModal()">No</button>
      <button type="button" class="btn btn-primary" style="flex:1;justify-content:center;background:#dc3545;border-color:#dc3545" id="pp-confirm-yes-btn">Remove</button>
    </div>
  </div>
</div>

<div class="pp-modal-overlay" id="pp-choose-confirm" onclick="if (event.target === this) closeChooseConfirm()">
  <div class="card pp-modal-box" style="max-width:420px;padding:22px 24px 20px">
    <div style="display:flex;align-items:flex-start;gap:13px">
      <span style="width:38px;height:38px;border-radius:11px;flex-shrink:0;display:grid;place-items:center;background:var(--amber-bg);color:var(--amber)">
        <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
      </span>
      <div style="min-width:0">
        <h3 style="margin:0 0 5px;font-size:16px;font-weight:700;color:var(--text)">Are you sure you want to take on this title?</h3>
        <p style="margin:0;font-size:13px;line-height:1.55;color:var(--text2)">
          <span id="pp-cc-title" style="font-weight:600;color:var(--text)"></span>
          is the title your team carries from here. The other recommended title is
          set aside.
        </p>
      </div>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:9px;margin-top:20px">
      <button type="button" class="btn btn-soft" onclick="closeChooseConfirm()">Cancel</button>
      <button type="button" class="btn btn-primary" id="pp-cc-ok">Confirm</button>
    </div>
  </div>
</div>

<div id="pp-toast"></div>

<script nonce="{{ Vite::cspNonce() }}">
// Handing your titles in is the one act that puts your team in front of your
// CaPSA, so it always asks - not only when there are fewer than we suggest.
// Turning in fewer is allowed: it used to be refused outright, which pushed
// teams into writing titles they did not believe in to unlock a button.
let ppSubmitForm = null;

function confirmSubmitCount(e) {
  const form = e.target;

  // The second pass, once they have said yes.
  if (form.dataset.confirmed === '1') return true;

  e.preventDefault();
  ppSubmitForm = form;

  // What is ticked, not what is saved - the two are the same until somebody
  // leaves a title out.
  const count = pickedProposals().length;
  const total = document.querySelectorAll('.pp-pick').length;
  const recommended = Number(form.dataset.recommended || 4);

  if (count === 0) return false;

  const short = count < recommended;
  const titles = count === 1 ? 'one pre-proposal' : count + ' pre-proposals';
  const holding = total - count;
  const heldNote = holding > 0
    ? ' The ' + (holding === 1 ? 'other one stays a draft' : 'other ' + holding + ' stay drafts')
      + ', report and all.'
    : '';

  document.getElementById('pp-sc-title').textContent = 'Submit ' + titles + '?';
  document.getElementById('pp-sc-body').textContent = (short
    ? 'We suggest turning in at least ' + recommended + '. Your CaPSA decides on each '
      + 'title separately, so if one is turned down you still have others that could be '
      + 'recommended. You can go back and add more first.'
    : 'Your CaPSA will review all ' + count + '. You can take this back any time until '
      + 'they start reviewing.') + heldNote;

  // Amber and a warning triangle when it is worth a second thought; gold
  // and a tick when it is not.
  const icon = document.getElementById('pp-sc-icon');
  icon.style.background = short ? 'var(--amber-bg)' : 'var(--accent-light)';
  icon.style.color = short ? 'var(--amber)' : 'var(--accent3)';
  document.getElementById('pp-sc-glyph-warn').hidden = ! short;
  document.getElementById('pp-sc-glyph-ok').hidden = short;

  document.getElementById('pp-submit-confirm').classList.add('show');
  document.body.style.overflow = 'hidden';
  document.getElementById('pp-sc-ok').focus();

  return false;
}

/** Ticked boxes, or an empty list. */
function pickedProposals() {
  return Array.prototype.slice.call(document.querySelectorAll('.pp-pick:checked'));
}

/**
 * Keep the button and the line under it honest about what is being sent.
 *
 * Untick a title and it stays saved, with its report - it simply is not part
 * of this submission.
 */
function refreshSubmitCount() {
  const picked = pickedProposals().length;
  const total = document.querySelectorAll('.pp-pick').length;
  const btn = document.getElementById('pp-submit-btn');
  const note = document.getElementById('pp-submit-note');

  if (!btn) return;

  btn.disabled = picked === 0;
  btn.style.opacity = picked === 0 ? '.5' : '';
  btn.style.cursor = picked === 0 ? 'not-allowed' : '';

  if (!note) return;

  // Numbers only, so building it as HTML is safe.
  const ticked = picked === 0 ? 'No title is ticked.'
    : total === 1 ? 'Your title is ticked.'
    : picked === total ? 'All ' + total + ' of your titles are ticked.'
    : picked + ' of your ' + total + ' titles are ticked.';
  note.innerHTML = '<b>' + ticked + '</b> ' + (picked === 0
    ? 'Tick at least one title to submit.'
    : 'You can take them back until your CaPSA starts reviewing.');
}

document.addEventListener('DOMContentLoaded', refreshSubmitCount);

function closeSubmitConfirm() {
  document.getElementById('pp-submit-confirm').classList.remove('show');
  document.body.style.overflow = '';
  ppSubmitForm = null;
}

document.getElementById('pp-sc-ok').addEventListener('click', function () {
  if (!ppSubmitForm) return;
  ppSubmitForm.dataset.confirmed = '1';
  ppSubmitForm.submit();
});

document.addEventListener('keydown', function (e) {
  const box = document.getElementById('pp-submit-confirm');
  if (e.key === 'Escape' && box && box.classList.contains('show')) closeSubmitConfirm();
});

// The check just run, kept so "Full Report" knows which one to open and
// clearScanResult() knows which row to throw away.
let lastCheckData = null;

const STEPS = [
  'Extracting PDF text...',
  'Analyzing key concepts via AI...',
  'Expanding search terms semantically...',
  'Comparing against archived capstones...',
  'Generating similarity scores...',
  'Suggesting instructors...',
];

function renderLoadingSteps(currentIdx) {
  document.getElementById('loading-steps').innerHTML = STEPS.map((step, i) => {
    const done = i < currentIdx;
    const current = i === currentIdx;
    const icon = done
      ? `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--green,#16a34a)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><polyline points="20 6 9 17 4 12"/></svg>`
      : current
        ? `<div style="width:12px;height:12px;border:2px solid var(--border);border-top-color:var(--primary,#5b5bd6);border-radius:50%;animation:spin 0.7s linear infinite;flex-shrink:0"></div>`
        : `<div style="width:14px;height:14px;flex-shrink:0"></div>`;
    return `
      <div style="display:flex;align-items:center;gap:8px;padding:4px 0;font-size:11.5px;${current ? 'color:var(--text);font-weight:600' : done ? 'color:var(--text2)' : 'color:var(--text3)'}">
        ${icon}
        ${step.replace('...', '')}
      </div>
    `;
  }).join('');
}

function showToast(message, type) {
  if (window.ctToast) { return ctToast(message, type); }
  const toast = document.getElementById('pp-toast');
  if (!toast) { return; }
  toast.textContent = (type === 'error' ? '✕ ' : '✓ ') + message;
  toast.classList.remove('success', 'error');
  toast.classList.add('show', type);
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => toast.classList.remove('show'), 4000);
}

const stopWords = new Set(['a','an','the','and','or','but','in','on','at','to','for',
    'of','by','with','from','is','are','was','were','be','been','being','have','has',
    'had','do','does','did','will','would','can','could','shall','should','may','might',
    'must','about','above','after','again','all','also','any','as','before','between',
    'both','each','few','more','most','other','some','such','no','nor','not','only',
    'own','same','so','than','that','then','there','these','they','this','those',
    'through','too','very','etc','up','down','out','off','over','under','here','what',
    'when','where','why','how','which','who','whom','whose']);

function countMeaningfulWords(text) {
  if (!text.trim()) return 0;
  return text.trim().split(/\s+/).filter(function (w) {
    var cleaned = w.replace(/[^a-zA-Z0-9']/g, '');
    return cleaned.length > 0 && !stopWords.has(cleaned.toLowerCase());
  }).length;
}

// Everything the server would reject, checked before the form is posted.
//
// A rejection there answers with a redirect, which reloads the page - and the
// duplication result the student just waited on is on that page. So every
// problem is found here first and shown under the field it belongs to, all at
// once, instead of one toast at a time.
const TITLE_MAX_WORDS = {{ \App\Http\Controllers\PreProposalController::TITLE_MAX_WORDS }};

function setFieldError(key, message) {
  const field = document.querySelector('#pp-form [data-field="' + key + '"]');
  if (!field) return;
  const box = field.querySelector('.pp-error');
  if (box) box.textContent = message || '';
  field.classList.toggle('has-error', !!message);
}

function clearFieldError(input) {
  const field = input && input.closest('[data-field]');
  if (field && field.classList.contains('has-error')) setFieldError(field.dataset.field, '');
}

// Everything the form still needs, in the order it is laid out. `commit`
// adds whatever is typed in the agenda box but not added yet, which only a
// save does - checking as the student types must not move their words.
function ppProblems(form, commit) {
  const problems = [];
  const title = document.getElementById('title');
  const titleText = title ? title.value.trim() : '';
  const words = countMeaningfulWords(titleText);

  if (!titleText) {
    problems.push(['title', 'Give your pre-proposal a title.']);
  } else if (words > TITLE_MAX_WORDS) {
    problems.push(['title', 'Keep the title to ' + TITLE_MAX_WORDS + ' meaningful words or fewer. It has ' + words + '.']);
  }

  if (!form.querySelectorAll('input[name="sdg_goals[]"]:checked').length) {
    problems.push(['sdg_goals', 'Choose at least one Sustainable Development Goal.']);
  }

  // Something typed into the agenda box but not added yet still counts:
  // it is what the student meant, and losing it on save would be a surprise.
  const agendaInput = document.getElementById('agenda-input');
  if (commit && agendaInput && agendaInput.value.trim()) addAgendaFromInput();
  const agendaPending = !commit && agendaInput && agendaInput.value.trim();
  if (!form.querySelectorAll('input[name="research_agenda[]"]').length && !agendaPending) {
    problems.push(['research_agenda', 'Add at least one research agenda or priority area.']);
  }

  if (!form.querySelectorAll('input[name="tags[]"]:checked').length) {
    problems.push(['tags', 'Pick at least one tag for what kind of project this is.']);
  }

  const needed = [
    ['problem_statement', 'problem', 'Fill in the problem statement.'],
    ['proposed_solution', 'solution', 'Fill in the proposed solution.'],
    ['scope', 'scope', 'Fill in the scope.'],
    ['limitations', 'limitations', 'Fill in the limitations.'],
    ['sdm', 'sdm', 'Fill in the software development methodology.'],
    ['clients', 'clients', 'Name the organization this is for.'],
    ['client_address', 'client_address', 'Fill in the address of the organization.'],
    ['client_representative', 'client_representative', 'Name the representative of the organization.'],
    ['client_position', 'client_position', 'Fill in the position that representative holds.'],
  ];
  needed.forEach(function (n) {
    const el = document.getElementById(n[1]);
    if (el && !String(el.value || '').trim()) problems.push([n[0], n[2]]);
  });

  const representative = document.getElementById('client_representative');
  if (representative && /\d/.test(representative.value)) {
    problems.push(['client_representative', "A representative's name has no numbers."]);
  }

  // Laid out order, whatever order they were found in.
  const order = Array.from(form.querySelectorAll('[data-field]')).map(function (f) { return f.dataset.field; });
  return problems.sort(function (x, y) { return order.indexOf(x[0]) - order.indexOf(y[0]); });
}

// A field the server refused (a title already taken, say) keeps the server's
// reason until the student changes that field.
// Read as the script runs (the form is above it), before anything reopens it.
const ppServerErrors = new Set(Array.from(document.querySelectorAll('#pp-form [data-field].has-error'), function (f) { return f.dataset.field; }));

function ppFieldName(key) {
  const field = document.querySelector('#pp-form [data-field="' + key + '"]');
  const label = field && field.querySelector(':scope > label, legend');
  return label ? label.textContent.trim() : key;
}

// What is still missing, shown before Save is pressed: under each field, and
// counted in the footer beside Save, updated as the student types.
function refreshPpForm() {
  const form = document.getElementById('pp-form');
  if (!form) return [];
  const problems = ppProblems(form, false);
  const byField = Object.fromEntries(problems);

  form.querySelectorAll('[data-field]').forEach(function (f) {
    const key = f.dataset.field;
    if (ppServerErrors.has(key)) return;
    setFieldError(key, byField[key] || '');
  });

  const note = document.getElementById('pp-foot-note');
  const save = document.getElementById('pp-save-btn');
  if (note) {
    note.replaceChildren();
    note.classList.toggle('is-done', problems.length === 0);
    note.classList.toggle('is-left', problems.length > 0);
    if (!problems.length) {
      note.textContent = 'Everything is filled in. Ready to save.';
    } else {
      const lead = document.createElement('b');
      lead.textContent = problems.length === 1 ? '1 thing left: ' : problems.length + ' things left: ';
      note.append(lead);
      problems.slice(0, 3).forEach(function (p, i) {
        if (i) note.append(', ');
        const jump = document.createElement('button');
        jump.type = 'button';
        jump.className = 'pp-jump';
        jump.textContent = ppFieldName(p[0]);
        jump.addEventListener('click', function () { ppGoTo(p[0]); });
        note.append(jump);
      });
      if (problems.length > 3) note.append(' and ' + (problems.length - 3) + ' more');
    }
  }
  if (save) save.classList.toggle('is-blocked', problems.length > 0);
  if (save) save.setAttribute('aria-describedby', 'pp-foot-note');
  return problems;
}

function ppGoTo(key) {
  const field = document.querySelector('#pp-form [data-field="' + key + '"]');
  if (!field) return;
  field.scrollIntoView({ block: 'center', behavior: 'smooth' });
  const focusable = field.querySelector('input:not([type=hidden]):not([disabled]), select, textarea');
  if (focusable) focusable.focus({ preventScroll: true });
}

let ppRefreshTimer = null;
function queuePpRefresh(e) {
  const field = e && e.target && e.target.closest && e.target.closest('#pp-form [data-field]');
  if (field) ppServerErrors.delete(field.dataset.field);
  clearTimeout(ppRefreshTimer);
  ppRefreshTimer = setTimeout(refreshPpForm, 120);
}
document.addEventListener('input', function (e) { if (e.target.closest && e.target.closest('#pp-form')) queuePpRefresh(e); });
document.addEventListener('change', function (e) { if (e.target.closest && e.target.closest('#pp-form')) queuePpRefresh(e); });

function ppFormIsComplete(form) {
  const problems = ppProblems(form, true);
  const summary = document.getElementById('pp-summary');
  if (summary) summary.hidden = true;

  if (!problems.length) {
    const save = document.getElementById('pp-save-btn');
    if (save) { save.disabled = true; save.textContent = 'Saving…'; }
    // A rejected save comes back with the answers filled in by the server.
    forgetDraft((document.getElementById('check-id') || {}).value);
    return true;
  }

  ppServerErrors.clear();
  refreshPpForm();
  ppGoTo(problems[0][0]);
  showToast(problems.length === 1 ? problems[0][1] : problems.length + ' details need a look before this can be saved.', 'error');

  return false;
}

// Tag pills: at most MAX picked. Once the limit is reached the rest are
// disabled rather than silently ignored, so it is clear why a click did nothing.
const TAG_MAX = {{ \App\Support\ProjectTags::MAX }};

function updateTagPills() {
  const boxes = Array.from(document.querySelectorAll('#pp-tags input[name="tags[]"]'));
  const picked = boxes.filter(b => b.checked).length;
  boxes.forEach(b => { b.disabled = !b.checked && picked >= TAG_MAX; });
  const count = document.getElementById('pp-tag-count');
  if (count) {
    count.textContent = picked + ' of ' + TAG_MAX;
    count.classList.toggle('at-limit', picked >= TAG_MAX);
  }
}

function updateWordCount() {
  const el = document.getElementById('word-count');
  const titleEl = document.getElementById('title');
  if (!el || !titleEl) return;
  const words = countMeaningfulWords(titleEl.value);
  el.textContent = words;
  const counter = document.getElementById('word-counter');
  if (counter) counter.classList.toggle('over', words > TITLE_MAX_WORDS);
}

// Long answers are the norm here, so the boxes grow with what is typed
// instead of hiding it behind a tiny scrollbar.
function growTextarea(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight + 2, 320) + 'px';
}
function growAllTextareas() {
  document.querySelectorAll('#pp-form textarea.pp-grow').forEach(growTextarea);
}
document.addEventListener('input', function (e) {
  if (e.target.matches && e.target.matches('#pp-form textarea.pp-grow')) growTextarea(e.target);
});

// What the student has chosen, and what the PDF suggested. Removing a
// suggestion used to lose it for good; it now drops back into the
// "From your PDF" row, one click from coming back.
let agendaTags = @json(array_values(array_filter((array) old('research_agenda', []), fn ($v) => is_string($v) && trim($v) !== '')));
let agendaSuggested = [];

// What the student has typed, kept per scan until it is saved. Drawing the
// last scan again on load used to fill the form from the PDF once more, so a
// refresh brought back a research agenda the student had removed - and wiped
// every other edit with it. Kept in this browser only; saving the
// pre-proposal, or clearing the scan, lets it go.
const PP_DRAFT_FIELDS = ['title', 'problem', 'solution', 'scope', 'limitations', 'sdm',
  'clients', 'client_address', 'client_representative', 'client_position'];
let ppDraftReady = false;

function ppDraftKey(id) { return 'pp-draft:' + id; }

function saveDraft() {
  const id = (document.getElementById('check-id') || {}).value;
  if (!ppDraftReady || !id || !document.getElementById('pp-form')) return;
  const fields = {};
  PP_DRAFT_FIELDS.forEach(function (f) { const el = document.getElementById(f); if (el) fields[f] = el.value; });
  const tags = Array.from(document.querySelectorAll('#pp-tags input[name="tags[]"]:checked')).map(function (b) { return b.value; });
  const sdgs = Array.from(document.querySelectorAll('#pp-sdgs input[name="sdg_goals[]"]:checked')).map(function (b) { return b.value; });
  try {
    localStorage.setItem(ppDraftKey(id), JSON.stringify({ fields: fields, agenda: agendaTags, tags: tags, sdgs: sdgs }));
    ppAutosaveStatus('Saved on this device · ' + new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }), 'saved');
  } catch (e) {
    // Private browsing or storage full: the form still works, it just is not kept.
  }
}

function ppAutosaveStatus(text, state) {
  const el = document.getElementById('pp-autosave');
  if (!el) return;
  el.textContent = text;
  el.classList.toggle('is-saved', state === 'saved');
  el.classList.toggle('is-restored', state === 'restored');
}

// Drafts for a scan the page no longer shows - saved as a pre-proposal, or
// cleared - are done with.
(function () {
  const current = @json(isset($lastCheck) && $lastCheck ? (string) $lastCheck->id : null);
  try {
    Object.keys(localStorage)
      .filter(function (k) { return k.indexOf('pp-draft:') === 0 && k !== (current ? ppDraftKey(current) : ''); })
      .forEach(function (k) { localStorage.removeItem(k); });
  } catch (e) {}
})();

function loadDraft(id) {
  try { return JSON.parse(localStorage.getItem(ppDraftKey(id)) || 'null'); } catch (e) { return null; }
}

function forgetDraft(id) {
  try { localStorage.removeItem(ppDraftKey(id)); } catch (e) {}
}

function applyDraft(draft) {
  Object.entries(draft.fields || {}).forEach(function ([f, value]) {
    const el = document.getElementById(f);
    if (el) el.value = value;
  });
  document.querySelectorAll('#pp-tags input[name="tags[]"]').forEach(function (b) { b.checked = (draft.tags || []).includes(b.value); });
  // A draft from before goals were ticks kept one goal as a field.
  const sdgs = Array.isArray(draft.sdgs) ? draft.sdgs : (draft.fields && draft.fields.sdg ? [draft.fields.sdg] : []);
  document.querySelectorAll('#pp-sdgs input[name="sdg_goals[]"]').forEach(function (b) { b.checked = sdgs.includes(b.value); });
  updateSdgCount();
  agendaTags = Array.isArray(draft.agenda) ? draft.agenda.slice() : [];
}

document.addEventListener('input', function (e) { if (e.target.closest && e.target.closest('#pp-form')) saveDraft(); });
document.addEventListener('change', function (e) { if (e.target.closest && e.target.closest('#pp-form')) saveDraft(); });

function agendaHas(list, text) {
  const key = text.toLowerCase();
  return list.some(function (t) { return t.toLowerCase() === key; });
}

function addAgenda(text) {
  text = String(text || '').trim();
  if (!text || agendaHas(agendaTags, text)) return;
  agendaTags.push(text);
  renderAgendaTags();
  setFieldError('research_agenda', '');
}

function addAgendaFromInput() {
  const input = document.getElementById('agenda-input');
  if (!input) return;
  addAgenda(input.value);
  input.value = '';
  input.focus();
}

function renderAgendaTags() {
  const list = document.getElementById('agenda-list');
  const hidden = document.getElementById('agenda-hidden');
  if (!list || !hidden) return;
  list.innerHTML = '';
  hidden.innerHTML = '';
  agendaTags.forEach(function (tag, i) {
    const chip = document.createElement('span');
    chip.className = 'pp-tag';
    const label = document.createElement('span');
    label.textContent = tag;
    chip.appendChild(label);
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.textContent = '×';
    removeBtn.setAttribute('aria-label', 'Remove ' + tag);
    removeBtn.onclick = function (e) {
      e.stopPropagation();
      agendaTags.splice(i, 1);
      renderAgendaTags();
    };
    chip.appendChild(removeBtn);
    list.appendChild(chip);

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'research_agenda[]';
    input.value = tag;
    hidden.appendChild(input);
  });

  const suggestWrap = document.getElementById('agenda-suggest');
  const suggestList = document.getElementById('agenda-suggest-list');
  if (!suggestWrap || !suggestList) return;
  suggestList.innerHTML = '';
  const left = agendaSuggested.filter(function (t) { return !agendaHas(agendaTags, t); });
  left.forEach(function (tag) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'pp-suggest-chip';
    b.textContent = '+ ' + tag;
    b.setAttribute('aria-label', 'Add ' + tag + ' back');
    b.onclick = function () { addAgenda(tag); };
    suggestList.appendChild(b);
  });
  suggestWrap.hidden = left.length === 0;
  saveDraft();
  // Chips added or removed fire no input event on the form.
  if (document.getElementById('pp-form-modal-overlay')?.classList.contains('show')) queuePpRefresh();
}

function rememberAgendaSuggestions() {
  const id = (document.getElementById('check-id') || {}).value;
  if (!id) return;
  try { sessionStorage.setItem('pp-agenda-suggested', JSON.stringify({ id: id, items: agendaSuggested })); } catch (e) {}
}

// After a failed save the page reloads; the PDF's suggestions come back with
// it, as long as it is the same scan.
(function restoreAgendaSuggestions() {
  const id = (document.getElementById('check-id') || {}).value;
  if (!id) return;
  try {
    const saved = JSON.parse(sessionStorage.getItem('pp-agenda-suggested') || 'null');
    if (saved && String(saved.id) === String(id) && Array.isArray(saved.items)) agendaSuggested = saved.items;
  } catch (e) {}
})();

const agendaInput = document.getElementById('agenda-input');
if (agendaInput) {
  agendaInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      addAgendaFromInput();
    } else if (e.key === 'Backspace' && !this.value && agendaTags.length) {
      agendaTags.pop();
      renderAgendaTags();
    }
  });
}
// The title wraps like a paragraph but is one line: Enter must not put a
// line break into it, and a pasted one is folded into a space.
const titleBox = document.getElementById('title');
if (titleBox) {
  titleBox.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
  titleBox.addEventListener('input', function () {
    if (/[\r\n]/.test(this.value)) this.value = this.value.replace(/\s*[\r\n]+\s*/g, ' ');
  });
}
renderAgendaTags();
updateTagPills();
updateWordCount();

function updateSdgCount() {
  const count = document.getElementById('pp-sdg-count');
  if (count) count.textContent = document.querySelectorAll('#pp-sdgs input[name="sdg_goals[]"]:checked').length + ' picked';
}
updateSdgCount();

// Tick every goal the PDF names. The extraction may give a list, one line
// ("SDG 4 – Quality Education, SDG 9 – ..."), or only numbers ("SDG 4 and 9"),
// so each "SDG n" found is ticked, and anything else is matched by name.
function selectSdgOptions(extracted) {
  const boxes = Array.from(document.querySelectorAll('#pp-sdgs input[name="sdg_goals[]"]'));
  boxes.forEach(function (b) { b.checked = false; });
  const text = (Array.isArray(extracted) ? extracted : [extracted])
    .filter(function (v) { return typeof v === 'string'; })
    .join(', ');
  if (text) {
    const numbers = [];
    const re = /SDG\s*#?\s*(\d{1,2})/gi;
    let m;
    while ((m = re.exec(text)) !== null) numbers.push(m[1]);
    boxes.forEach(function (b) {
      const own = b.value.match(/^SDG (\d+) /)[1];
      if (numbers.includes(own) || text.toLowerCase().includes(b.value.toLowerCase())) b.checked = true;
    });
  }
  updateSdgCount();
}

let checkAbortController = null;
let checkTimeoutHandle = null;
let checkTimedOut = false;
let checkStepInterval = null;
let checkStillWorkingTimeout = null;

function handleFileSelected() {
  const fileInput = document.getElementById('file-input');
  if (!fileInput.files.length) return;

  document.getElementById('upload-zone').classList.add('has-file');
  document.getElementById('upload-label').textContent = fileInput.files[0].name;
}

async function runCheck() {
  const fileInput = document.getElementById('file-input');
  if (!fileInput.files.length) {
    showToast('Choose a PDF file first.', 'error');
    return;
  }

  const overlay = document.getElementById('check-loading');
  renderLoadingSteps(0);
  overlay.classList.add('show');
  document.getElementById('loading-still-working').style.display = 'none';
  // Steps alone finish cycling at 48s (6 x 8s) - past that, still visible
  // but static, a genuinely slow check can otherwise look stuck.
  checkStillWorkingTimeout = setTimeout(() => {
    document.getElementById('loading-still-working').style.display = '';
  }, 60000);
  let stepIdx = 0;
  checkStepInterval = setInterval(() => {
    if (stepIdx >= STEPS.length - 1) {
      // Real checks routinely run past 6x8=48s (up to 300s on a slow
      // model) - holding here instead of wrapping back to 0 avoids
      // looking like the whole process just restarted.
      clearInterval(checkStepInterval);
      return;
    }
    stepIdx++;
    renderLoadingSteps(stepIdx);
  }, 8000);

  const form = new FormData();
  form.append('document', fileInput.files[0]);

  checkAbortController = new AbortController();

  // The server gives Ollama 300 seconds, so anything past that is not going
  // to arrive. Without this the overlay spun until the request died on its
  // own and came back as an HTML error page - which is what a slow
  // connection during a presentation looks like.
  checkTimedOut = false;
  checkTimeoutHandle = setTimeout(() => {
    checkTimedOut = true;
    if (checkAbortController) checkAbortController.abort();
  }, 330000);

  try {
    const res = await fetch('/pre-proposal/duplication-checks', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
      body: form,
      signal: checkAbortController.signal,
    });

    // Read the body as text first. When the server dies mid-check, or a
    // proxy gives up on a slow connection, what comes back is an HTML error
    // page - and res.json() on that threw "Unexpected token '<', \"<!DOCTYPE\"",
    // which is the error a student was left looking at.
    const raw = await res.text();
    let data;

    try {
      data = JSON.parse(raw);
    } catch (parseError) {
      throw new Error(
        res.status === 419
          ? 'Your session expired while the check was running. Sign in again and retry - nothing was lost.'
          : 'The check did not finish. The connection may be too slow to hold it open - try again, and nothing you uploaded was lost.'
      );
    }

    clearInterval(checkStepInterval);
    clearTimeout(checkStillWorkingTimeout);
    clearTimeout(checkTimeoutHandle);
    overlay.classList.remove('show');

    if (!data.success) { showToast(data.message, 'error'); return; }

    renderResult(data, fileInput.files[0].name);
  } catch (err) {
    clearInterval(checkStepInterval);
    clearTimeout(checkStillWorkingTimeout);
    clearTimeout(checkTimeoutHandle);
    overlay.classList.remove('show');

    if (err.name === 'AbortError') {
      // Two ways to get here: the reader pressed Cancel, or the hard stop
      // above fired. Only the second one needs explaining.
      if (checkTimedOut) {
        showToast('The check ran out of time - the connection is too slow to hold it open. Try again; nothing you uploaded was lost.', 'error');
      }

      return;
    }

    showToast(err.message, 'error');
  } finally {
    checkAbortController = null;
    checkTimeoutHandle = null;
  }
}

function cancelCheck() {
  checkTimedOut = false;
  if (checkAbortController) {
    checkAbortController.abort();
  }
  clearInterval(checkStepInterval);
  clearTimeout(checkStillWorkingTimeout);
  clearTimeout(checkTimeoutHandle);
  document.getElementById('check-loading').classList.remove('show');
  showToast('Similarity check cancelled.', 'error');
}

@if(!empty($lastCheck))
@php
  // Built here rather than inline: @json() cannot take an array literal
  // spread over several lines - it reads to the first newline and leaves the
  // bracket unclosed, which is a parse error in the compiled view rather
  // than anything Blade complains about.
  $lastCheckPayload = [
      'duplication_check_id' => $lastCheck->id,
      // A scan saved before bulleted items were split apart.
      'concepts' => array_merge((array) $lastCheck->concepts, [
          'research_agenda' => \App\Support\ResearchAgenda::items(($lastCheck->concepts ?? [])['research_agenda'] ?? []),
      ]),
      'top_score' => $lastCheck->top_score,
      'verdict' => $lastCheck->verdict,
      'matches' => $lastCheck->matches,
      'instructors' => $lastCheck->suggestionsForDisplay(),
  ];
  $lastCheckName = basename($lastCheck->document_path ?? 'your document');
@endphp
// The last check this team ran, drawn again on load.
//
// runCheck() writes the result to the database before it answers, so it was
// never actually lost - it just was not drawn a second time, and every
// reload therefore looked like the scan had been thrown away. Unsubmitting
// reloads. So does a rejected form, or pressing back.
document.addEventListener('DOMContentLoaded', function () {
  renderResult(@json($lastCheckPayload), @json($lastCheckName), true);
});
@endif

// Throw the current scan away.
//
// The row goes with it: leaving it behind would only have the page draw it
// again on the next load, which is the very thing being cleared.
function clearScanResult() {
  if (!lastCheckData || !lastCheckData.duplication_check_id) {
    return;
  }

  // Asked in the page's own dialog, the same one that asks before removing a
  // pre-proposal. pendingRemoveId stays null, which is how the Yes button
  // below tells the two apart.
  pendingClearCheck = true;
  document.getElementById('pp-confirm-message').textContent =
    'Clear this result? The scan is thrown away. Your saved pre-proposals are not touched.';
  document.getElementById('pp-confirm-modal').classList.add('show');
}

function reallyClearScanResult() {
  fetch('{{ url('pre-proposal/duplication-checks') }}/' + lastCheckData.duplication_check_id, {
    method: 'DELETE',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Accept': 'application/json',
    },
  })
    .then(r => r.json())
    .then(function (d) {
      if (!d.success) {
        showToast(d.message || 'Could not clear that result.', 'error');

        return;
      }

      forgetDraft(lastCheckData.duplication_check_id);
      showToast(d.message, 'success');
      setTimeout(() => window.location.reload(), 600);
    })
    .catch(() => showToast('Network error while clearing.', 'error'));
}

// Both new pages are keyed off the check, so the page only has to be told
// the shape of the two addresses.
const DUP_REPORT_URL = @json(route('student.duplication.report', ['check' => '__C__']));
const DUP_MATCH_URL = @json(route('student.duplication.similar', ['check' => '__C__', 'rank' => '__R__']));
const ARCHIVE_TOTAL = {{ $archiveTotal }};

// The research agenda the PDF suggests, as plain distinct strings.
function pdfAgenda(data) {
  const items = data && data.concepts && Array.isArray(data.concepts.research_agenda) ? data.concepts.research_agenda : [];
  return items
    .map(item => typeof item === 'string' ? item : (item && (item.name || item.title || item.agenda || item.label || item.text || item.value)) || '')
    .map(s => String(s).trim())
    .filter((s, i, all) => s.length > 0 && all.findIndex(o => o.toLowerCase() === s.toLowerCase()) === i);
}

const PP_HAS_OLD_INPUT = @json(session()->hasOldInput('duplication_check_id'));

// onLoad: drawing the last scan again as the page opens, not a fresh scan.
// Then what the student already typed - a rejected save, or edits kept from
// before the refresh - is what the form shows, not the PDF again.
function renderResult(data, fileName, onLoad) {
  lastCheckData = data;

  // Everything from here that writes into the Pre-Proposal Details form has
  // to check the form is there first. The checker itself is always on the
  // page now; the form is not, once a team has turned their titles in.
  const ppForm = document.getElementById('pp-form');
  const checkIdEl = document.getElementById('check-id');
  if (checkIdEl) checkIdEl.value = data.duplication_check_id;

  const clearBtn = document.getElementById('clear-result-btn');
  if (clearBtn) clearBtn.style.display = 'inline-flex';

  // Said calmly: the percentage keeps its colour, the words say what it
  // means (the same bands as App\Support\SimilarityBand).
  document.getElementById('sc-empty').hidden = true;
  document.getElementById('sc-result').hidden = false;

  const band = simBand(data.top_score);
  const simPct = document.getElementById('sim-pct');
  simPct.className = 'bigscore sim-' + band;
  simPct.innerHTML = (data.top_score === null || data.top_score === undefined ? '—' : Number(data.top_score) + '<span style="font-size:22px">%</span>');

  const bandEl = document.getElementById('sc-band');
  bandEl.className = 'band band-' + band;
  bandEl.textContent = SIM_BANDS[band];

  const scored = (data.matches || []).map(m => m.assessed === false ? null : m.score);
  const count = key => scored.filter(s => simBand(s) === key).length;
  document.getElementById('sc-counts').innerHTML = ['close', 'some', 'little']
    .map(k => '<span class="band band-' + k + '">' + count(k) + ' ' + { close: 'close', some: 'some overlap', little: 'little overlap' }[k] + '</span>')
    .join('');

  document.getElementById('doc-title').textContent = data.concepts.title || fileName;

  // What the closest ones share, in the check's own words.
  const shared = (data.matches || [])
    .filter(m => m.assessed !== false && simBand(m.score) === 'close')
    .map(m => m.similarities || m.reason || '')
    .filter(Boolean)
    .slice(0, 3);
  const meta = document.getElementById('doc-meta');
  meta.textContent = '';
  if (band === 'close' && shared.length) {
    meta.append('The closest ones share ');
    const b = document.createElement('b');
    b.textContent = shared.join(' · ');
    meta.append(b, '. Similar work is common and is not a rejection: it shows where yours should stand apart.');
  } else if (band === 'close' || band === 'some') {
    meta.textContent = 'Some archived capstones overlap with it. Open them to see what they share, and where yours stands apart.';
  } else {
    meta.textContent = 'Nothing in the archive is close to it. Your CaPSA still reviews every title.';
  }

  document.getElementById('match-count').textContent = data.matches?.length ? data.matches.length + ' match' + (data.matches.length > 1 ? 'es' : '') + ' found' : '';

  const matchList = document.getElementById('matches-list');
  if (!data.matches || data.matches.length === 0) {
    matchList.innerHTML = `
      <div style="text-align:center;padding:40px 0">
        <div style="font-size:28px;margin-bottom:8px">✓</div>
        <div style="font-size:13px;font-weight:600;color:var(--green,#30a46c);margin-bottom:4px">No Similar Capstones Found</div>
        <div style="font-size:11px;color:var(--text3);max-width:280px;margin:0 auto">This pre-proposal appears to be original based on our archived database.</div>
      </div>`;
  } else {
    matchList.innerHTML = data.matches.map((m, i) => renderMatchItem(m, i, data.duplication_check_id)).join('');
  }

  const instrList = document.getElementById('instructor-list');
  if (data.instructors && data.instructors.length) {
    instrList.innerHTML = data.instructors.map(i => `
      <div class="instructor-item">
        <div class="avatar">${escapeHtml((i.name || '?').charAt(0))}</div>
        <div style="min-width:0">
          ${i.role_label ? `<div class="sx-role">Recommended ${escapeHtml(i.role_label)}</div>` : ''}
          <div style="font-size:12px;font-weight:500;color:var(--text)">${escapeHtml(i.name)}${i.study_leave ? '<span class="sx-leave">On study leave</span>' : ''}</div>
          <div style="font-size:10px;color:var(--primary,#5b5bd6);margin-top:1px">${escapeHtml(i.expertise || '')}</div>
          <div style="font-size:10px;color:var(--text3);margin-top:2px">${escapeHtml(i.reason || '')}</div>
        </div>
      </div>`).join('');
  } else {
    instrList.innerHTML = `<div style="font-size:11px;color:var(--text3);text-align:center;padding:16px 0">No instructor suggestions available</div>`;
  }

  const ppDraft = onLoad && ppForm ? loadDraft(data.duplication_check_id) : null;

  if (ppForm && onLoad && PP_HAS_OLD_INPUT) {
    // A rejected save: the server has put the student's own answers back in.
    agendaSuggested = pdfAgenda(data);
    renderAgendaTags();
  } else if (ppForm && ppDraft) {
    agendaSuggested = pdfAgenda(data);
    applyDraft(ppDraft);
    ppAutosaveStatus('Your saved answers were restored.', 'restored');
    updateTagPills();
    updateWordCount();
    renderAgendaTags();
  } else if (ppForm) {
    document.getElementById('title').value = data.concepts.title || '';
    document.getElementById('problem').value = data.concepts.problem_statement || '';
    document.getElementById('solution').value = data.concepts.proposed_solution || '';
    document.getElementById('scope').value = data.concepts.scope || '';
    document.getElementById('limitations').value = data.concepts.limitations || '';
    if (data.concepts.sdm) document.getElementById('sdm').value = data.concepts.sdm;
    // Not read out of the PDF - the team names their own client, so a new
    // document starts these blank instead of keeping the last answers.
    ['clients', 'client_address', 'client_representative', 'client_position']
      .forEach(id => { document.getElementById(id).value = ''; });
    // A new document is a new project: its tags are chosen fresh.
    document.querySelectorAll('#pp-tags input[name="tags[]"]').forEach(b => { b.checked = false; });
    updateTagPills();
    updateWordCount();
    selectSdgOptions(data.concepts.sdg_goals);

    agendaSuggested = pdfAgenda(data);
    agendaTags = agendaSuggested.slice();
    renderAgendaTags();
    rememberAgendaSuggestions();
    // A new scan starts clean: old complaints belong to the last document.
    document.querySelectorAll('#pp-form [data-field]').forEach(f => setFieldError(f.dataset.field, ''));
    const summary = document.getElementById('pp-summary');
    if (summary) summary.hidden = true;
  }

  // Only now: saving before the form was filled would keep the PDF's
  // answers over the student's own.
  ppDraftReady = true;
  saveDraft();

  // One of these two is on the page, never both: the button while the team
  // can still add a title, the note once their set is turned in.
  const ppNext = document.getElementById('open-pp-form-btn');
  const ppLocked = document.getElementById('pp-locked-note');
  if (ppNext) ppNext.style.display = '';
  if (ppLocked) ppLocked.style.display = '';
  const fullReport = document.getElementById('full-report-btn');
  if (fullReport) {
    fullReport.href = DUP_REPORT_URL.replace('__C__', data.duplication_check_id);
    fullReport.style.display = '';
  }
}

function openPpFormModal() {
  // Not on the page once a team has turned their titles in - the checker
  // stays open to them, the form does not.
  const ppOverlay = document.getElementById('pp-form-modal-overlay');
  if (!ppOverlay) return;
  ppOverlay.classList.add('show');
  document.body.style.overflow = 'hidden';
  growAllTextareas();
  updateWordCount();
  // What still needs filling in shows as it opens, not after Save.
  refreshPpForm();
}

function closePpFormModal() {
  const ppOverlay = document.getElementById('pp-form-modal-overlay');
  if (ppOverlay) ppOverlay.classList.remove('show');
  document.body.style.overflow = '';
}

// How close a score is, in the same bands as App\Support\SimilarityBand.
// A match nothing scored carries a retrieval rank rather than a similarity,
// so it shows no number at all.
const SIM_BANDS = @json(\App\Support\SimilarityBand::BANDS);
function simBand(score) {
  if (score === null || score === undefined) return 'none';
  return score >= {{ \App\Support\SimilarityBand::CLOSE }} ? 'close' : (score >= {{ \App\Support\SimilarityBand::SOME }} ? 'some' : 'little');
}

function renderMatchItem(m, i, checkId) {
  const s = m.assessed === false ? null : (m.score === undefined ? null : m.score);
  const why = m.similarities || m.reason || '';

  // from=pp tells the match page it was opened here and not from the full
  // report, so its Back button comes back to this page.
  return `
    <a class="mrow" href="${DUP_MATCH_URL.replace('__C__', checkId).replace('__R__', i + 1)}?from=pp">
      <div>
        <div class="pc sim-${simBand(s)}">${s === null ? '—' : s + '%'}</div>
        <div class="rk">#${i + 1}</div>
      </div>
      <div>
        <div class="t">${escapeHtml(m.title)}</div>
        <div class="mt">${m.academic_year || 'N/A'} · ${escapeHtml(m.program || '')}</div>
        ${why ? `<div class="wy">${escapeHtml(why)}</div>` : ''}
      </div>
    </a>`;
}

let pendingRemoveId = null;

let pendingClearCheck = false;

function openRemoveConfirm(proposalId) {
  pendingRemoveId = proposalId;
  document.getElementById('pp-confirm-message').textContent = 'Remove this proposal? This cannot be undone.';
  document.getElementById('pp-confirm-modal').classList.add('show');
}

/**
 * Ask before a title is settled on.
 *
 * Choosing is not reversible from this page - the other recommended title is
 * set aside by it - and the button sat one click away from doing that.
 */
function confirmChooseTitle(id, title) {
  document.getElementById('pp-cc-title').textContent = title;
  document.getElementById('pp-cc-ok').onclick = function () {
    document.getElementById('choose-form-' + id).submit();
  };
  document.getElementById('pp-choose-confirm').classList.add('show');
}

function closeChooseConfirm() {
  document.getElementById('pp-choose-confirm').classList.remove('show');
}

function closePpConfirmModal() {
  document.getElementById('pp-confirm-modal').classList.remove('show');
  pendingRemoveId = null;
  pendingClearCheck = false;
}

document.getElementById('pp-confirm-yes-btn').addEventListener('click', function () {
  if (pendingClearCheck) {
    pendingClearCheck = false;
    closePpConfirmModal();
    reallyClearScanResult();

    return;
  }

  if (pendingRemoveId) {
    document.getElementById('remove-form-' + pendingRemoveId).submit();
  }
});

document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    closePpFormModal();
    closePpConfirmModal();
  }
});

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str || '';
  return div.innerHTML;
}

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

@if (session('status'))
  showToast(@json(session('status')), 'success');
@endif
@if (session('error'))
  showToast(@json(session('error')), 'error');
@endif
@if (! $errors->any() && session()->has('error') && old('duplication_check_id') !== null)
  if (document.getElementById('pp-form-modal-overlay')) openPpFormModal();
@endif
@if ($errors->any())
  // The save was refused: bring the form back with everything that was typed
  // and the problems marked, rather than a toast over an empty page.
  if (document.getElementById('pp-form-modal-overlay')) {
    openPpFormModal();
    const firstBad = document.querySelector('#pp-form .pp-field.has-error');
    if (firstBad) firstBad.scrollIntoView({ block: 'center' });
  }
  showToast(@json($errors->count() > 1 ? $errors->count().' details need a look before this can be saved.' : $errors->first()), 'error');
@endif
</script>

@include('partials.escape-closes-modals')
</body>
</html>
