<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Pre-Proposal Submission - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
@vite('resources/student-ui/styles.css')
<style>
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
  height: calc(100vh - 248px);
  min-height: 480px;
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

/* A real progress ring (conic-gradient), matching the pattern already used
   for the bigger .sim-ring elsewhere in the app - just sized for this
   compact sidebar slot instead of inventing a new visual language. */
.score-ring-sm {
  --pct: 0;
  --ring-color: var(--border);
  width: 72px; height: 72px;
  border-radius: 50%;
  background: conic-gradient(var(--ring-color) calc(var(--pct) * 1%), var(--border) 0);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  transition: background 0.5s ease;
  position: relative;
}
.score-ring-sm::before {
  content: '';
  position: absolute;
  inset: 6px;
  border-radius: 50%;
  background: var(--bg2);
}
.score-ring-sm > div { position: relative; z-index: 1; }

.pp-chosen{display:inline-flex;align-items:center;gap:5px;font-weight:700;color:var(--green,#16a34a)}
.pp-row.is-chosen{border-left:3px solid var(--green,#16a34a);padding-left:9px}
/* Still readable, just plainly not the one being worked on. */
.pp-row.is-set-aside{opacity:.62}
.sx-role{font-size:9px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;
    color:var(--accent3,#8a6e10);margin-bottom:2px}
  .instructor-item {
  display: flex; gap: 10px; align-items: flex-start;
  padding: 8px 0;
  border-bottom: 1px solid var(--border);
}
.instructor-item:last-child { border-bottom: none; }

/* Apply button on a suggested instructor. Locked until the pre-proposal
   this report belongs to has been approved. */
.dup-apply-btn { display:inline-flex; align-items:center; gap:5px; margin-left:auto; flex-shrink:0; padding:5px 11px; border-radius:6px; border:1.5px solid var(--accent,#c9a82c); background:var(--accent,#c9a82c); color:#18191f; font-family:inherit; font-size:11px; font-weight:700; text-decoration:none; cursor:pointer; transition:opacity .13s; }
.dup-apply-btn:hover { opacity:0.86; }
.dup-apply-btn.locked { background:transparent; border-color:var(--border); color:var(--text3); cursor:not-allowed; }
.dup-apply-btn.locked:hover { opacity:1; }
.dup-apply-note { font-size:10px; color:var(--text3); margin-top:8px; }

/* Tooltip explaining WHY Apply is locked. It sits on a wrapper because a
   disabled button emits no hover events of its own. */
.dup-apply-wrap { position:relative; margin-left:auto; flex-shrink:0; display:inline-flex; outline:none; }
.dup-apply-wrap .dup-apply-btn { margin-left:0; }
.dup-apply-wrap::after {
  content: attr(data-tip);
  position:absolute; bottom:calc(100% + 8px); right:0; z-index:40;
  width:max-content; max-width:230px; padding:7px 10px; border-radius:6px;
  background:var(--text, #1e2128); color:#fff; font-size:11px; font-weight:500;
  line-height:1.45; text-align:left; white-space:normal;
  box-shadow:0 6px 18px -4px rgba(0,0,0,0.35);
  opacity:0; visibility:hidden; transform:translateY(3px);
  transition:opacity .13s ease, transform .13s ease, visibility .13s;
  pointer-events:none;
}
.dup-apply-wrap::before {
  content:''; position:absolute; bottom:calc(100% + 3px); right:14px; z-index:41;
  border:5px solid transparent; border-top-color:var(--text, #1e2128);
  opacity:0; visibility:hidden; transition:opacity .13s ease, visibility .13s;
  pointer-events:none;
}
.dup-apply-wrap:hover::after, .dup-apply-wrap:focus-visible::after,
.dup-apply-wrap:hover::before, .dup-apply-wrap:focus-visible::before {
  opacity:1; visibility:visible; transform:translateY(0);
}
@media (prefers-reduced-motion: reduce) {
  .dup-apply-wrap::after, .dup-apply-wrap::before { transition:none; }
}

.avatar {
  width: 28px; height: 28px; border-radius: 50%;
  background: var(--primary-light, #e8e8fc);
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 600;
  color: var(--primary, #5b5bd6); flex-shrink: 0;
}

/* A bordered card per match, with the score as a pill badge - not a
   left-border color stripe, which reads as a generic dashboard cliche
   rather than an actual design decision. */
.match-item {
  display: flex; flex-direction: column;
  padding: 13px 14px;
  margin-bottom: 10px;
  border: 1px solid var(--border);
  border-radius: 11px;
}
.match-item:last-child { margin-bottom: 0; }
.match-item-row { display: flex; justify-content: space-between; align-items: flex-start; }
.match-score-pill {
  display: inline-flex; align-items: center;
  font-variant-numeric:tabular-nums; font-size: 12px; font-weight: 700;
  padding: 3px 10px; border-radius: 100px; flex-shrink: 0;
}

.match-abstract-toggle {
  align-self: flex-start;
  margin-top: 6px;
  padding: 0;
  border: none;
  background: none;
  cursor: pointer;
  font-family: 'DM Sans', sans-serif;
  font-size: 10px;
  font-weight: 600;
  color: var(--primary, #5b5bd6);
}
.match-abstract-toggle:hover { text-decoration: underline; }

.match-abstract {
  display: none;
  margin-top: 6px;
  padding: 8px 10px;
  background: var(--bg3);
  border-radius: 8px;
  font-size: 11px;
  line-height: 1.55;
  color: var(--text2);
  white-space: pre-line;
}
.match-abstract.open { display: block; }
/* Literal word overlap with the student's own pre-proposal, highlighted
   right inside the archived abstract - a mechanical, verifiable signal
   alongside the AI's own similarities/differences/suggestion sentences
   below, not a replacement for them. */
.match-abstract mark {
  background: rgba(201,168,44,0.28);
  color: var(--text);
  font-weight: 600;
  border-radius: 3px;
  padding: 0 2px;
}

.match-breakdown {
  display: none;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid var(--border);
  font-size: 12px;
  line-height: 1.55;
  color: var(--text3);
}
.match-breakdown.open { display: flex; flex-direction: column; gap: 9px; }
.match-breakdown strong { color: var(--text2); }
/* The suggestion is the one actionable line - it earns visual weight the
   plain similarities/differences lines don't need. */
.match-suggestion {
  padding: 8px 10px;
  background: var(--primary-bg, rgba(91,91,214,0.08));
  border-radius: 7px;
  color: var(--text2);
}
.match-suggestion strong { color: var(--primary, #5b5bd6); }

.pp-field { margin-bottom: 14px; }
.pp-field label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 6px; letter-spacing: 0.4px; }
.pp-field input[type="text"], .pp-field select, .pp-field textarea {
  width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border);
  background: var(--bg2); color: var(--text); font-size: 13px; box-sizing: border-box; font-family: inherit;
}
.pp-field textarea { min-height: 70px; resize: vertical; }
.pp-hint { font-size: 11px; color: var(--text3); margin-top: 4px; }
.pp-tags { border: none; padding: 0; margin: 0 0 16px; min-width: 0; }
.pp-tags legend { display: flex; align-items: center; gap: 8px; padding: 0; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); letter-spacing: 0.4px; margin-bottom: 4px; }
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
.pp-submit-bar { flex-shrink: 0; border-top: 1px solid var(--border); padding: 12px 0 4px; margin-top: 10px; }
.pp-btn-submit { width: 100%; justify-content: center; gap: 7px; font-size: 12.5px; padding: 9px 12px; }
.pp-btn-submit:disabled { background: var(--bg3); color: var(--text3); border: 1px solid var(--border); cursor: not-allowed; }
.pp-btn-unsubmit { width: 100%; justify-content: center; font-size: 12px; padding: 7px 12px; background: transparent; color: var(--text2); border: 1px solid var(--border); }
.pp-btn-unsubmit:hover { border-color: var(--red); color: var(--red); }
.pp-submit-note { font-size: 10.5px; color: var(--text3); margin-top: 7px; line-height: 1.5; text-align: center; }
.pp-submit-state { display: flex; align-items: center; gap: 9px; padding: 9px 11px; border-radius: 8px; background: var(--green-bg); color: var(--green); margin-bottom: 9px; }
.pp-submit-state svg { flex-shrink: 0; }
.pp-submit-state-title { font-size: 12px; font-weight: 700; }
.pp-submit-state-sub { font-size: 10.5px; opacity: 0.85; margin-top: 1px; }
.pp-badge-draft { background: var(--bg4); color: var(--text3); }

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
.pp-tag-list { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
.pp-tag { display: inline-flex; align-items: center; gap: 6px; background: var(--bg3); border: 1px solid var(--border); border-radius: 6px; padding: 4px 10px; font-size: 12px; color: var(--text2); }
.pp-tag button { border: none; background: none; color: var(--text3); cursor: pointer; font-size: 12px; padding: 0; }

.pp-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 1500; align-items: center; justify-content: center; padding: 24px; }
.pp-modal-overlay.show { display: flex; }
.pp-modal-box { width: 100%; max-width: 760px; max-height: 82vh; overflow-y: auto; position: relative; }
/* Every modal still scrolls with the wheel/trackpad - just no visible
   track running down it. */
.pp-modal-box { scrollbar-width: none; -ms-overflow-style: none; }
.pp-modal-box::-webkit-scrollbar { display: none; }
.pp-modal-close { position: absolute; top: 10px; right: 10px; border: none; background: none; font-size: 20px; line-height: 1; cursor: pointer; color: var(--text3); padding: 4px; }
.pp-modal-close:hover { color: var(--text); }

@keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
.sk-overlay { position: absolute; inset: 0; background: var(--bg); z-index: 10; }
.sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }

#pp-toast { position: fixed; bottom: 30px; right: 30px; background: var(--navy-dark); color: #fff; padding: 14px 22px; border-radius: 12px; font-size: 13px; font-weight: 500; z-index: 2000; box-shadow: 0 8px 30px rgba(0,0,0,0.3); opacity: 0; transform: translateY(12px); pointer-events: none; transition: opacity 0.25s ease, transform 0.25s ease; border-left: 4px solid transparent; display: flex; align-items: center; gap: 10px; }
#pp-toast.show { opacity: 1; transform: translateY(0); }
#pp-toast.success { border-left-color: var(--green); }
#pp-toast.error { border-left-color: var(--red); }
</style>
<x-dashboard.styles />
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

    <!-- At-a-glance result summary - bigger and more prominent than a
         status line buried in the upload card, since this is the one
         number a student actually came here to see. Same element ids
         renderResult() already updates - only the layout moved. -->
    <div class="card" style="display:flex;align-items:center;gap:18px;margin-bottom:16px;padding:18px 22px">
      <div class="score-ring-sm" id="score-ring" style="width:84px;height:84px;flex-shrink:0">
        <div style="font-variant-numeric:tabular-nums;font-size:19px;font-weight:700;color:var(--text)" id="sim-pct">--</div>
      </div>
      <div style="flex:1;min-width:0">
        <span class="badge badge-green" id="result-badge" style="display:inline-block">No Check Yet</span>
        <div style="font-size:13.5px;font-weight:600;color:var(--text);margin-top:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" id="doc-title">No document checked</div>
        <div style="font-size:11px;color:var(--text3);margin-top:2px" id="doc-meta">Upload a pre-proposal PDF to begin</div>
      </div>
      <button type="button" class="btn" id="full-report-btn" style="display:none;flex-shrink:0" onclick="openFreshReportModal()">Full Report</button>
      {{-- The result survives a reload now, which is the point - and means
           there has to be a way to be rid of it. A panel that cannot be
           dismissed is its own kind of stuck. --}}
      <button type="button" class="btn" id="clear-result-btn" title="Clear this result"
              style="display:none;flex-shrink:0;padding:6px 10px" onclick="clearScanResult()">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
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
        <div class="matches-scroll" id="matches-list">
          <div style="font-size:12px;color:var(--text3);text-align:center;padding:40px 0">No matches yet</div>
        </div>
      </div>

      <div class="card dup-portrait">
        <div class="card-header" style="flex-shrink:0;flex-direction:column;align-items:flex-start;gap:2px">
          <span class="card-title" style="display:inline-flex;align-items:center;gap:7px"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>Your Pre-Proposals</span>
          <span style="font-size:11px;color:var(--text3);font-weight:400">
            @if ($turnedIn)
              {{ $proposals->count() }} turned in
            @else
              {{ $proposals->count() }} saved &middot; {{ $recommendedProposals }} suggested
            @endif
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
              <div class="pp-row @if($rowChosen) is-chosen @elseif($rowSetAside) is-set-aside @endif"
                   style="padding:10px 0;border-top:1px solid var(--border)">
                @php
                  // A draft the leader could still choose to turn in. Nothing
                  // to tick once the set is in, and nothing for a member to
                  // tick at all.
                  $pickable = $isLeader && ! $turnedIn && ! $proposal->submitted_at;
                @endphp
                <div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:4px">
                  @if ($pickable)
                    {{-- Attached to the submit form further down the page by
                         its id, so unticking one is all it takes to leave a
                         title out. Before this the only way was to delete it,
                         which threw away its duplication report with it. --}}
                    <input type="checkbox" class="pp-pick" name="ids[]" value="{{ $proposal->id }}"
                           form="pp-submit-form" checked onchange="refreshSubmitCount()"
                           aria-label="Submit {{ $proposal->title }}"
                           style="margin-top:2px;width:15px;height:15px;flex-shrink:0;accent-color:var(--accent,#5b5bd6);cursor:pointer">
                  @endif
                  <div style="font-size:12px;font-weight:500;min-width:0;{{ $proposal->status === 'rejected' ? 'color:var(--text3);text-decoration:line-through' : 'color:var(--text)' }}">{{ $proposal->title }}</div>
                </div>
                @if ($proposal->submitted_at || $proposal->status !== 'pending')
                  <span class="badge {{ $proposal->status === 'approved' ? 'badge-green' : ($proposal->status === 'rejected' ? 'badge-red' : 'badge-amber') }}">{{ $proposal->status === 'approved' ? 'Recommended' : ucfirst($proposal->status) }}</span>
                @else
                  <span class="badge pp-badge-draft">Draft</span>
                @endif

                @if ($proposal->status === 'rejected')
                  <div class="pp-hint" style="color:var(--red)">{{ $proposal->rejection_reason }}</div>
                @elseif ($proposal->status === 'approved')
                  @if ($team?->selected_pre_proposal_id && (int) $team->selected_pre_proposal_id === (int) $proposal->id)
                    <div class="pp-hint pp-chosen">
                      <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                      This is your team's title.
                    </div>
                  @elseif ($team?->selected_pre_proposal_id)
                    <div class="pp-hint">Recommended, but not the title your team chose.</div>
                  @else
                    <div class="pp-hint">Locked in as a candidate.</div>
                  @endif
                  @if ($proposal->capsa_note)
                    <div class="pp-hint">Suggestion from your CaPSA: {{ $proposal->capsa_note }}</div>
                  @endif
                @elseif ($proposal->submitted_at)
                  <div class="pp-hint">Awaiting adviser review.</div>
                @else
                  <div class="pp-hint">Draft &mdash; not submitted yet.</div>
                @endif

                @if ($check)
                  <button type="button" class="match-abstract-toggle" style="margin-top:8px" onclick='openReportModal({{ $proposal->id }}, @json($proposal->title))'>
                    View Duplication Report →
                  </button>
                @endif

                @if ($proposal->status !== 'approved' && ! $turnedIn)
                  <form method="POST" action="{{ route('student.pre-proposal.destroy', $proposal->id) }}" id="remove-form-{{ $proposal->id }}" style="display:inline">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="match-abstract-toggle" style="margin-top:8px;margin-left:12px;color:var(--red)" onclick="openRemoveConfirm({{ $proposal->id }})">Remove</button>
                  </form>
                @endif

                @if ($check)
                  @php
                    $verdictTone = $check->verdict === 'High Similarity' ? 'red' : ($check->verdict === 'Moderate Similarity' ? 'amber' : 'green');
                    // Words from THIS pre-proposal worth highlighting wherever
                    // they turn up in a matched abstract below - a literal,
                    // student-verifiable signal in place of an AI-written
                    // similarities sentence.
                    $highlightWords = app(\App\Services\SimilarityService::class)->extractHighlightWords($check->concepts ?? []);
                    $highlightAbstract = fn (?string $text) => \App\Services\SimilarityService::highlight($text, $highlightWords);
                  @endphp
                  <div id="report-data-{{ $proposal->id }}" style="display:none">
                    <div style="display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:8px;background:var(--{{ $verdictTone }}-bg);margin-bottom:16px">
                      <span style="font-variant-numeric:tabular-nums;font-size:22px;font-weight:700;color:var(--{{ $verdictTone }})">{{ $check->top_score }}%</span>
                      <div>
                        <span class="badge badge-{{ $verdictTone }}">{{ $check->verdict }}</span>
                        <div style="font-size:11px;color:var(--text3);margin-top:3px">Highest similarity found across archived capstones</div>
                      </div>
                    </div>

                    @if (empty($check->matches))
                      <div style="text-align:center;padding:16px 0">
                        <div style="font-size:13px;font-weight:600;color:var(--green)">No Similar Capstones Found</div>
                      </div>
                    @else
                      <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text3);margin-bottom:6px;letter-spacing:0.4px">Matched Capstone Projects</div>
                      @foreach ($check->matchesWithAuthors() as $m)
                        @php
                          $mScore = $m['score'] ?? 0;
                          $mTone = $mScore >= 65 ? 'red' : ($mScore >= 55 ? 'amber' : 'green');
                          $hasBreakdown = ! empty($m['similarities']) || ! empty($m['differences']) || ! empty($m['suggestion']);
                        @endphp
                        <div class="match-item">
                          <div class="match-item-row">
                            <div style="flex:1;min-width:0;margin-right:10px">
                              {{-- The title in full. It used to be cut to one
                                   line with an ellipsis, which is the one part
                                   of a matched study nobody can do without. --}}
                              <div style="font-size:12px;font-weight:500;color:var(--text);line-height:1.4">{{ $m['title'] ?? '' }}</div>
                              @if (! empty($m['authors']))
                                <div style="font-size:10px;color:var(--text2);margin-top:3px">By {{ $m['authors'] }}</div>
                              @else
                                <div style="font-size:10px;color:var(--text3);margin-top:3px">Authors not recorded yet</div>
                              @endif
                              <div style="font-size:10px;color:var(--text3);margin-top:2px">{{ $m['academic_year'] ?? 'N/A' }} · {{ $m['program'] ?? '' }}</div>
                              @if (! empty($m['reason']))
                                <div style="font-size:10px;color:var(--text3);margin-top:4px;line-height:1.4">{{ $m['reason'] }}</div>
                              @endif
                            </div>
                            @if (($m['assessed'] ?? true))
                              <span class="match-score-pill" style="background:var(--{{ $mTone }}-bg);color:var(--{{ $mTone }})">{{ $mScore }}%</span>
                            @else
                              {{-- No model looked at this one. The number it
                                   carries is a demoted retrieval rank, which
                                   is not a similarity and was reaching the
                                   student as though it were. --}}
                              <span class="match-score-pill" style="background:var(--bg3);color:var(--text3)">Not assessed</span>
                            @endif
                          </div>
                          @if ($hasBreakdown)
                            <button type="button" class="match-abstract-toggle" onclick="toggleBreakdown(this)">Show similarities, differences &amp; suggestion ▾</button>
                            <div class="match-breakdown">
                              @if (! empty($m['similarities']))<div><strong>Similarities:</strong> {{ $m['similarities'] }}</div>@endif
                              @if (! empty($m['differences']))<div><strong>Differences:</strong> {{ $m['differences'] }}</div>@endif
                              @if (! empty($m['suggestion']))<div class="match-suggestion"><strong>Suggestion:</strong> {{ $m['suggestion'] }}</div>@endif
                            </div>
                          @endif
                          @if (! empty($m['abstract']))
                            <button type="button" class="match-abstract-toggle" onclick="toggleAbstract(this)">Show abstract to compare similarities ▾</button>
                            <div class="match-abstract">{!! $highlightAbstract($m['abstract']) !!}</div>
                          @endif
                        </div>
                      @endforeach
                    @endif

                    @if (count($check->instructors_suggested ?? []))
                      @php
                        // Applying is only open once this pre-proposal is the
                        // approved one - the expert is being asked to take on
                        // a topic that has cleared review, not a draft.
                        // Pre-proposal approval is CaPSA's, which is what the
                        // locked button's tooltip needs to say: "approved" on
                        // its own doesn't tell the student who to chase.
                        // A team with two approved pre-proposals picks one
                        // to be their title. Applying belongs to that one
                        // alone - an expert asked to advise the title the
                        // team set aside is being asked about work nobody is
                        // doing.
                        $chosenId = $team?->selected_pre_proposal_id;
                        $isChosen = $chosenId && (int) $chosenId === (int) $proposal->id;
                        $setAside = $chosenId && ! $isChosen;

                        $applyOpen = $proposal->status === 'approved' && $isLeader && ! $setAside;
                        $applyLockShort = match (true) {
                            $proposal->status === 'rejected' => 'Pre-proposal rejected',
                            $proposal->status !== 'approved' => 'Needs CaPSA recommendation',
                            $setAside => 'Not your chosen title',
                            default => 'Team leader only',
                        };
                        $applyLockReason = match (true) {
                            $proposal->status === 'rejected' => 'This pre-proposal was rejected, so you cannot apply to an expert with it. Revise it or use one that was recommended.',
                            $proposal->status !== 'approved' => 'Your CaPSA has to recommend this pre-proposal before your team can apply to an expert with it. It is still awaiting review.',
                            $setAside => 'Your team chose a different title, so this one is no longer the one you apply with.',
                            default => 'Only your team leader can apply to an expert.',
                        };
                      @endphp
                      <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border)">
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text3);margin-bottom:6px;letter-spacing:0.4px">Suggested Experts</div>
                        @foreach ($check->suggestionsForDisplay() as $i)
                          <div class="instructor-item">
                            <div class="avatar">{{ strtoupper(substr($i['name'] ?? '?', 0, 1)) }}</div>
                            <div style="min-width:0">
                              @if (! empty($i['role_label']))
                                {{-- Said where it is drawn, so scans that
                                     already ran read the same way. --}}
                                <div class="sx-role">Recommended {{ $i['role_label'] }}</div>
                              @endif
                              <div style="font-size:12px;font-weight:500;color:var(--text)">{{ $i['name'] ?? '' }}</div>
                              <div style="font-size:10px;color:var(--primary,#5b5bd6);margin-top:1px">{{ $i['expertise'] ?? '' }}</div>
                              <div style="font-size:10px;color:var(--text3);margin-top:2px">{{ $i['reason'] ?? '' }}</div>
                            </div>
                            @if (! empty($i['id']))
                              @if ($applyOpen)
                                <a class="dup-apply-btn" href="{{ route('student.experts', ['apply' => $i['id']]) }}">
                                  <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
                                  Apply
                                </a>
                              @else
                                {{-- The wrapper carries the tooltip: a disabled
                                     button fires no hover events of its own. --}}
                                <span class="dup-apply-wrap" data-tip="{{ $applyLockReason }}" tabindex="0" role="note" aria-label="{{ $applyLockReason }}">
                                  <button type="button" class="dup-apply-btn locked" disabled>
                                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    {{ $applyLockShort }}
                                  </button>
                                </span>
                              @endif
                            @endif
                          </div>
                        @endforeach
                        @unless ($applyOpen)
                          <div class="dup-apply-note">{{ $applyLockReason }}</div>
                        @endunless
                      </div>
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
            @if (! $isLeader)
              <div class="pp-submit-note">Only your team leader can submit these for checking.</div>
            @elseif ($canTurnIn)
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
              <div class="pp-submit-note" id="pp-submit-note">
                Hands
                {{ $draftCount === 1 ? 'your title' : 'all '.$draftCount.' titles' }}
                to your CaPSA to review.
              </div>
            @else
              <div class="pp-submit-note">Save a pre-proposal title first.</div>
            @endif
          @endif
        </div>
      </div>
    </div>

    @if ($canSubmitMore)
      <div class="pp-modal-overlay" id="pp-form-modal-overlay" onclick="if (event.target === this) closePpFormModal()">
      <div class="card pp-modal-box" id="pp-form-card">
        <button type="button" class="pp-modal-close" onclick="closePpFormModal()" aria-label="Close">&times;</button>
        <div class="card-header" style="flex-direction:column;align-items:flex-start;gap:2px">
          <span class="card-title">Pre-Proposal Details</span>
          <span style="font-size:12px;color:var(--text3);font-weight:400">Pulled from your uploaded PDF — review and edit before saving.</span>
        </div>
        <form method="POST" action="{{ route('student.pre-proposal.store') }}" id="pp-form"
              onsubmit="return ppFormIsComplete(this)">
          @csrf
          <input type="hidden" name="duplication_check_id" id="check-id" value="">

          <div class="pp-field">
            <label>Title</label>
            <input type="text" name="title" id="title" oninput="updateWordCount()">
            <div class="pp-hint">Must contain 11–12 meaningful words. Current count: <span id="word-count">0</span></div>
          </div>

          <div class="pp-field">
            <label>Applicable UN Sustainable Development Goal</label>
            <select name="sdg_goals" id="sdg">
              <option value="" disabled selected>Select a goal...</option>
              @foreach ([
                'SDG 1 – No Poverty', 'SDG 2 – Zero Hunger', 'SDG 3 – Good Health and Well-being',
                'SDG 4 – Quality Education', 'SDG 5 – Gender Equality', 'SDG 6 – Clean Water and Sanitation',
                'SDG 7 – Affordable and Clean Energy', 'SDG 8 – Decent Work and Economic Growth',
                'SDG 9 – Industry, Innovation and Infrastructure', 'SDG 10 – Reduced Inequality',
                'SDG 11 – Sustainable Cities and Communities', 'SDG 12 – Responsible Consumption and Production',
                'SDG 13 – Climate Action', 'SDG 14 – Life Below Water', 'SDG 15 – Life on Land',
                'SDG 16 – Peace, Justice and Strong Institutions', 'SDG 17 – Partnerships for the Goals',
              ] as $sdg)
                <option value="{{ $sdg }}">{{ $sdg }}</option>
              @endforeach
            </select>
          </div>

          <div class="pp-field">
            <label>Applicable Research Agenda and Priority Area</label>
            <div class="pp-tag-list" id="agenda-list"></div>
            <input type="text" id="agenda-input" placeholder="Type a priority area and press Enter...">
            <div id="agenda-hidden"></div>
          </div>

          <div class="pp-field"><label>Problem Statement</label><textarea name="problem_statement" id="problem"></textarea></div>
          <div class="pp-field"><label>Proposed Solution</label><textarea name="proposed_solution" id="solution"></textarea></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div class="pp-field"><label>Scope</label><textarea name="scope" id="scope" style="min-height:48px"></textarea></div>
            <div class="pp-field"><label>Limitations</label><textarea name="limitations" id="limitations" style="min-height:48px"></textarea></div>
          </div>
          <div class="pp-field"><label>Software Development Methodology</label><input type="text" name="sdm" id="sdm" value="Agile Development Methodology"></div>

          <fieldset class="pp-field pp-tags" id="pp-tags">
            <legend>Project Tags <span class="pp-required">Required</span></legend>
            <div class="pp-hint" style="margin:0 0 8px">What kind of project is this? Pick {{ \App\Support\ProjectTags::MIN }} to {{ \App\Support\ProjectTags::MAX }}.</div>
            <div class="pp-pills">
              @foreach (\App\Support\ProjectTags::OPTIONS as $tag)
                <label class="pp-pill">
                  <input type="checkbox" name="tags[]" value="{{ $tag }}" onchange="updateTagPills()">
                  <span>{{ $tag }}</span>
                </label>
              @endforeach
            </div>
            <div class="pp-hint" id="pp-tag-count" aria-live="polite">0 of {{ \App\Support\ProjectTags::MAX }} picked</div>
          </fieldset>

          <button type="submit" class="btn btn-primary">Save This Pre-Proposal</button>
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
      <button type="button" class="btn btn-primary" id="pp-sc-ok">Yes, submit</button>
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
      <button type="button" class="btn btn-primary" style="flex:1;justify-content:center;background:#dc3545;border-color:#dc3545" id="pp-confirm-yes-btn">Yes, Remove</button>
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

<div class="pp-modal-overlay" id="report-modal-overlay" onclick="if (event.target === this) closeReportModal()">
  <div class="card pp-modal-box">
    <button type="button" class="pp-modal-close" onclick="closeReportModal()" aria-label="Close">&times;</button>
    <div class="card-header"><span class="card-title" id="report-modal-title">Duplication Report</span></div>
    <div id="report-modal-body"></div>
  </div>
</div>

<script>
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

  if (picked === 0) {
    note.textContent = 'Tick at least one title to submit.';
  } else if (picked === total) {
    note.textContent = 'Hands ' + (total === 1 ? 'your title' : 'all ' + total + ' titles')
      + ' to your CaPSA to review.';
  } else {
    note.textContent = 'Hands ' + picked + ' of ' + total + ' to your CaPSA. The '
      + (total - picked === 1 ? 'other stays a draft' : 'others stay drafts') + ', report and all.';
  }
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

// Kept so "Full Report" can rebuild the report modal for a check that was
// just run - it isn't tied to a saved proposal id yet (that only exists
// after the student saves it), so it can't reuse openReportModal()'s
// hidden-div copy the way a saved proposal's "View Duplication Report" does.
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
  const toast = document.getElementById('pp-toast');
  if (!toast) { return; }
  toast.textContent = (type === 'error' ? '✕ ' : '✓ ') + message;
  toast.classList.remove('success', 'error');
  toast.classList.add('show', type);
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => toast.classList.remove('show'), 3500);
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
// duplication result the student just waited on is on that page. Losing a
// scan to a title one word short is a bad trade, and the count that catches
// it has been sitting on screen the whole time.
function ppFormIsComplete(form) {
  const title = document.getElementById('title');
  const words = countMeaningfulWords(title ? title.value : '');

  if (words < 11 || words > 12) {
    showToast('Your title needs 11-12 meaningful words. It currently has ' + words + '.', 'error');
    if (title) { title.focus(); }

    return false;
  }

  // The rest are plain "required" rules, named the way the form labels them.
  const needed = [
    ['sdg', 'Choose a Sustainable Development Goal.'],
    ['problem', 'Fill in the problem statement.'],
    ['solution', 'Fill in the proposed solution.'],
    ['scope', 'Fill in the scope.'],
    ['limitations', 'Fill in the limitations.'],
    ['sdm', 'Fill in the software development methodology.'],
  ];

  for (const [id, message] of needed) {
    const field = document.getElementById(id);

    if (field && !String(field.value || '').trim()) {
      showToast(message, 'error');
      field.focus();

      return false;
    }
  }

  if (!form.querySelectorAll('input[name="research_agenda[]"]').length) {
    showToast('Add at least one research agenda or priority area.', 'error');
    const agenda = document.getElementById('agenda-input');
    if (agenda) { agenda.focus(); }

    return false;
  }

  if (!form.querySelectorAll('input[name="tags[]"]:checked').length) {
    showToast('Pick at least one tag for what kind of project this is.', 'error');
    const firstTag = form.querySelector('input[name="tags[]"]');
    if (firstTag) { firstTag.focus(); }

    return false;
  }

  return true;
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
    count.textContent = picked + ' of ' + TAG_MAX + ' picked' + (picked >= TAG_MAX ? ' - that is the most you can pick' : '');
  }
}

function updateWordCount() {
  const el = document.getElementById('word-count');
  const titleEl = document.getElementById('title');
  if (el && titleEl) el.textContent = countMeaningfulWords(titleEl.value);
}
updateWordCount();

let agendaTags = [];

function renderAgendaTags() {
  const list = document.getElementById('agenda-list');
  const hidden = document.getElementById('agenda-hidden');
  if (!list || !hidden) return;
  list.innerHTML = '';
  hidden.innerHTML = '';
  agendaTags.forEach(function (tag, i) {
    const chip = document.createElement('span');
    chip.className = 'pp-tag';
    chip.textContent = tag + ' ';
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.textContent = '×';
    removeBtn.onclick = function () { agendaTags.splice(i, 1); renderAgendaTags(); };
    chip.appendChild(removeBtn);
    list.appendChild(chip);

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'research_agenda[]';
    input.value = tag;
    hidden.appendChild(input);
  });
}

const agendaInput = document.getElementById('agenda-input');
if (agendaInput) {
  agendaInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && this.value.trim()) {
      e.preventDefault();
      agendaTags.push(this.value.trim());
      this.value = '';
      renderAgendaTags();
    }
  });
}

function selectSdgOption(extractedValue) {
  // The extraction prompt allows for multiple SDGs ("Goal(s)"), so the AI
  // can return an array even though this dropdown only holds one selection -
  // normalize to a single string (using the first value) before matching,
  // rather than crashing on .toLowerCase() when it's not already a string.
  if (Array.isArray(extractedValue)) {
    extractedValue = extractedValue[0];
  }
  if (typeof extractedValue !== 'string' || !extractedValue) return;
  const select = document.getElementById('sdg');
  const options = Array.from(select.options);
  let match = options.find(o => o.value.toLowerCase() === extractedValue.toLowerCase());
  if (!match) {
    const numMatch = extractedValue.match(/SDG\s*(\d+)/i);
    if (numMatch) match = options.find(o => o.value.toLowerCase().startsWith('sdg ' + numMatch[1] + ' '));
  }
  if (match) select.value = match.value;
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
      'concepts' => $lastCheck->concepts,
      'top_score' => $lastCheck->top_score,
      'verdict' => $lastCheck->verdict,
      'matches' => $lastCheck->matches,
      'instructors' => $lastCheck->suggestionsForDisplay(),
      'highlight_words' => app(\App\Services\SimilarityService::class)
          ->extractHighlightWords($lastCheck->concepts ?? []),
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
  renderResult(@json($lastCheckPayload), @json($lastCheckName));
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

      showToast(d.message, 'success');
      setTimeout(() => window.location.reload(), 600);
    })
    .catch(() => showToast('Network error while clearing.', 'error'));
}

function renderResult(data, fileName) {
  lastCheckData = data;

  // Everything from here that writes into the Pre-Proposal Details form has
  // to check the form is there first. The checker itself is always on the
  // page now; the form is not, once a team has turned their titles in.
  const ppForm = document.getElementById('pp-form');
  const checkIdEl = document.getElementById('check-id');
  if (checkIdEl) checkIdEl.value = data.duplication_check_id;

  const clearBtn = document.getElementById('clear-result-btn');
  if (clearBtn) clearBtn.style.display = 'inline-flex';

  const badge = document.getElementById('result-badge');
  badge.textContent = data.verdict;
  badge.className = 'badge ' + (data.verdict === 'High Similarity' ? 'badge-red' : (data.verdict === 'Moderate Similarity' ? 'badge-amber' : 'badge-green'));

  const ring = document.getElementById('score-ring');
  ring.style.setProperty('--ring-color', data.top_score >= 65 ? 'var(--red,#e5484d)' : (data.top_score >= 55 ? 'var(--amber,#f5a623)' : 'var(--green,#30a46c)'));
  ring.style.setProperty('--pct', Math.max(0, Math.min(100, data.top_score)));

  document.getElementById('sim-pct').textContent = data.top_score + '%';
  document.getElementById('doc-title').textContent = data.concepts.title || fileName;
  document.getElementById('doc-meta').textContent = 'Checked just now' + (data.top_score < 55 ? ' · No strong duplicates found' : '');

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
    matchList.innerHTML = data.matches.map(m => renderMatchItem(m, data.highlight_words || [])).join('');
  }

  const instrList = document.getElementById('instructor-list');
  if (data.instructors && data.instructors.length) {
    instrList.innerHTML = data.instructors.map(i => `
      <div class="instructor-item">
        <div class="avatar">${escapeHtml((i.name || '?').charAt(0))}</div>
        <div style="min-width:0">
          ${i.role_label ? `<div class="sx-role">Recommended ${escapeHtml(i.role_label)}</div>` : ''}
          <div style="font-size:12px;font-weight:500;color:var(--text)">${escapeHtml(i.name)}</div>
          <div style="font-size:10px;color:var(--primary,#5b5bd6);margin-top:1px">${escapeHtml(i.expertise || '')}</div>
          <div style="font-size:10px;color:var(--text3);margin-top:2px">${escapeHtml(i.reason || '')}</div>
        </div>
      </div>`).join('');
  } else {
    instrList.innerHTML = `<div style="font-size:11px;color:var(--text3);text-align:center;padding:16px 0">No instructor suggestions available</div>`;
  }

  if (ppForm) {
    document.getElementById('title').value = data.concepts.title || '';
    document.getElementById('problem').value = data.concepts.problem_statement || '';
    document.getElementById('solution').value = data.concepts.proposed_solution || '';
    document.getElementById('scope').value = data.concepts.scope || '';
    document.getElementById('limitations').value = data.concepts.limitations || '';
    if (data.concepts.sdm) document.getElementById('sdm').value = data.concepts.sdm;
    // A new document is a new project: its tags are chosen fresh.
    document.querySelectorAll('#pp-tags input[name="tags[]"]').forEach(b => { b.checked = false; });
    updateTagPills();
    updateWordCount();
    selectSdgOption(data.concepts.sdg_goals);

    if (Array.isArray(data.concepts.research_agenda) && data.concepts.research_agenda.length) {
      agendaTags = data.concepts.research_agenda
        .map(item => typeof item === 'string' ? item : (item && (item.name || item.title || item.agenda || item.label || item.text || item.value)) || '')
        .map(s => String(s).trim())
        .filter(s => s.length > 0);
      renderAgendaTags();
    }
  }

  // One of these two is on the page, never both: the button while the team
  // can still add a title, the note once their set is turned in.
  const ppNext = document.getElementById('open-pp-form-btn');
  const ppLocked = document.getElementById('pp-locked-note');
  if (ppNext) ppNext.style.display = '';
  if (ppLocked) ppLocked.style.display = '';
  const fullReport = document.getElementById('full-report-btn');
  if (fullReport) fullReport.style.display = '';
}

function openPpFormModal() {
  // Not on the page once a team has turned their titles in - the checker
  // stays open to them, the form does not.
  const ppOverlay = document.getElementById('pp-form-modal-overlay');
  if (ppOverlay) ppOverlay.classList.add('show');
}

function closePpFormModal() {
  const ppOverlay = document.getElementById('pp-form-modal-overlay');
  if (ppOverlay) ppOverlay.classList.remove('show');
}

// Wraps any of the pre-proposal's own significant words wherever they occur
// in a matched abstract - escape first, then mark, so nothing in the
// (already-known-plain-text) word list can break out of the HTML it's
// injected into.
function highlightAbstract(text, words) {
  const escaped = escapeHtml(text || '');
  if (!words || !words.length) return escaped;
  const pattern = new RegExp('\\b(' + words.map(w => w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|') + ')\\b', 'gi');
  return escaped.replace(pattern, '<mark>$1</mark>');
}

function renderMatchItem(m, highlightWords) {
  const assessed = m.assessed !== false;
  const tone = m.score >= 65 ? 'red' : m.score >= 55 ? 'amber' : 'green';
  const hasBreakdown = m.similarities || m.differences || m.suggestion;
  return `
    <div class="match-item">
      <div class="match-item-row">
        <div style="flex:1;min-width:0;margin-right:10px">
          <div style="font-size:12px;font-weight:500;color:var(--text);line-height:1.4">${escapeHtml(m.title)}</div>
          ${m.authors ? `<div style="font-size:10px;color:var(--text2);margin-top:3px">By ${escapeHtml(m.authors)}</div>` : `<div style="font-size:10px;color:var(--text3);margin-top:3px">Authors not recorded yet</div>`}
          <div style="font-size:10px;color:var(--text3);margin-top:2px">${m.academic_year || 'N/A'} · ${escapeHtml(m.program || '')}</div>
          ${m.reason ? `<div style="font-size:10px;color:var(--text3);margin-top:4px;line-height:1.4">${escapeHtml(m.reason)}</div>` : ''}
        </div>
        ${assessed
          ? `<span class="match-score-pill" style="background:var(--${tone}-bg);color:var(--${tone})">${m.score}%</span>`
          : '<span class="match-score-pill" style="background:var(--bg3);color:var(--text3)">Not assessed</span>'}
      </div>
      ${hasBreakdown ? `
        <button type="button" class="match-abstract-toggle" onclick="toggleBreakdown(this)">Show similarities, differences &amp; suggestion ▾</button>
        <div class="match-breakdown">
          ${[
            m.similarities ? `<div><strong>Similarities:</strong> ${escapeHtml(m.similarities)}</div>` : '',
            m.differences ? `<div><strong>Differences:</strong> ${escapeHtml(m.differences)}</div>` : '',
          ].filter(Boolean).join('')}
          ${m.suggestion ? `<div class="match-suggestion"><strong>Suggestion:</strong> ${escapeHtml(m.suggestion)}</div>` : ''}
        </div>
      ` : ''}
      ${m.abstract ? `
        <button type="button" class="match-abstract-toggle" onclick="toggleAbstract(this)">Show abstract to compare similarities ▾</button>
        <div class="match-abstract">${highlightAbstract(m.abstract, highlightWords)}</div>
      ` : ''}
    </div>`;
}

function toggleBreakdown(btn) {
  const box = btn.nextElementSibling;
  const isOpen = box.classList.toggle('open');
  btn.textContent = isOpen ? 'Hide similarities, differences & suggestion ▴' : 'Show similarities, differences & suggestion ▾';
}

function openReportModal(proposalId, title) {
  const data = document.getElementById('report-data-' + proposalId);
  if (!data) return;
  document.getElementById('report-modal-title').textContent = title || 'Duplication Report';
  document.getElementById('report-modal-body').innerHTML = data.innerHTML;
  document.getElementById('report-modal-overlay').classList.add('show');
}

function closeReportModal() {
  document.getElementById('report-modal-overlay').classList.remove('show');
}

// "Full Report" opens this same modal for a check that was just run (no
// saved proposal id to key off yet), built straight from the AJAX response
// instead of a server-rendered hidden div.
function openFreshReportModal() {
  if (!lastCheckData) return;
  const data = lastCheckData;
  const tone = data.top_score >= 65 ? 'red' : (data.top_score >= 55 ? 'amber' : 'green');

  const matchesHtml = (!data.matches || data.matches.length === 0)
    ? `<div style="text-align:center;padding:16px 0">
        <div style="font-size:13px;font-weight:600;color:var(--green)">No Similar Capstones Found</div>
      </div>`
    : `<div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text3);margin-bottom:6px;letter-spacing:0.4px">Matched Capstone Projects</div>`
      + data.matches.map(m => renderMatchItem(m, data.highlight_words || [])).join('');

  // This report is for a check that was just run, so its pre-proposal has
  // not been saved - let alone approved - and every Apply is locked. The
  // saved-proposal report renders its own buttons server-side, where the
  // approval status is actually known.
  const instructorsHtml = (data.instructors && data.instructors.length)
    ? `<div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border)">
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text3);margin-bottom:6px;letter-spacing:0.4px">Suggested Experts</div>
        ${data.instructors.map(i => `
          <div class="instructor-item">
            <div class="avatar">${escapeHtml((i.name || '?').charAt(0))}</div>
            <div style="min-width:0">
              ${i.role_label ? `<div class="sx-role">Recommended ${escapeHtml(i.role_label)}</div>` : ''}
              <div style="font-size:12px;font-weight:500;color:var(--text)">${escapeHtml(i.name)}</div>
              <div style="font-size:10px;color:var(--primary,#5b5bd6);margin-top:1px">${escapeHtml(i.expertise || '')}</div>
              <div style="font-size:10px;color:var(--text3);margin-top:2px">${escapeHtml(i.reason || '')}</div>
            </div>
            <span class="dup-apply-wrap" data-tip="Save this pre-proposal first, then a CaPSA has to recommend it before your team can apply to an expert." tabindex="0" role="note">
              <button type="button" class="dup-apply-btn locked" disabled>
                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Needs CaPSA recommendation
              </button>
            </span>
          </div>`).join('')}
        <div class="dup-apply-note">Save this pre-proposal and wait for your CaPSA's recommendation before you can apply to an expert.</div>
      </div>`
    : '';

  document.getElementById('report-modal-title').textContent = data.concepts.title || 'Duplication Report';
  document.getElementById('report-modal-body').innerHTML = `
    <div style="display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:8px;background:var(--${tone}-bg);margin-bottom:16px">
      <span style="font-variant-numeric:tabular-nums;font-size:22px;font-weight:700;color:var(--${tone})">${data.top_score}%</span>
      <div>
        <span class="badge badge-${tone}">${escapeHtml(data.verdict)}</span>
        <div style="font-size:11px;color:var(--text3);margin-top:3px">Highest similarity found across archived capstones</div>
      </div>
    </div>
    ${matchesHtml}
    ${instructorsHtml}
  `;
  document.getElementById('report-modal-overlay').classList.add('show');
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
    closeReportModal();
    closePpFormModal();
    closePpConfirmModal();
  }
});

function toggleAbstract(btn) {
  const box = btn.nextElementSibling;
  const isOpen = box.classList.toggle('open');
  btn.textContent = isOpen ? 'Hide abstract ▴' : 'Show abstract to compare similarities ▾';
}

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
@if ($errors->any())
  showToast(@json($errors->first()), 'error');
@endif
</script>

@include('partials.escape-closes-modals')
</body>
</html>
