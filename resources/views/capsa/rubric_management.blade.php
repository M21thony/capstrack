<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Rubric Management - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
  @include('partials.render-whole')
</head>
<body>

<!-- ARCHIVE MODAL -->
<div class="modal-overlay" id="del-modal">
  <div class="modal-box">
    <div class="modal-icon-wrap"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="var(--amber)" stroke-width="2.5"><path d="M12 5v14"/><polyline points="18 11 12 17 6 11"/></svg></div>
    <div class="modal-title" id="del-modal-title">Archive Rubric?</div>
    <div class="modal-sub" id="del-modal-sub">This will archive the rubric. You can restore it later with Show archived.</div>
    <div class="modal-actions">
      <button class="btn btn-ghost" style="color:var(--text2);border-color:var(--border)" onclick="closeDelModal()">Cancel</button>
      <button class="btn" id="del-confirm-btn" style="background:var(--amber-bg);color:var(--amber);border:1px solid var(--amber)">Archive</button>
    </div>
  </div>
</div>

<dialog class="cm rb-new" id="rb-dialog" aria-labelledby="rb-dlg-title"><div class="cm-box">
  <div>
    <h3 id="rb-dlg-title">New Rubric</h3>
    <div class="sub" id="rb-dlg-sub">Name it and pick the stage. The criteria come next, on the full page.</div>
  </div>
  <input type="hidden" id="rb-type" value=""/>
  <div class="mk-f">
    <label class="label" for="rb-name">Rubric name</label>
    <input class="input" id="rb-name" placeholder="e.g. Pre-Proposal Defense Rubric" maxlength="255"/>
  </div>
  <div class="mk-f">
    <label class="label">Stage</label>
    <div class="mk-stages">
      <button type="button" style="--c:var(--blue)" data-type="pre_proposal" onclick="selectStage('pre_proposal')">Pre-Proposal</button>
      <button type="button" style="--c:var(--accent3)" data-type="title_defense" onclick="selectStage('title_defense')">Title Defense</button>
      <button type="button" style="--c:var(--purple)" data-type="pre_oral_defense" onclick="selectStage('pre_oral_defense')">Pre-Oral</button>
      <button type="button" style="--c:var(--green)" data-type="final_oral_defense" onclick="selectStage('final_oral_defense')">Final Oral</button>
    </div>
  </div>
  <div class="mk-2col">
    <div class="mk-f">
      <label class="label" for="rb-program">Program</label>
      <select class="input" id="rb-program">
        <option value="">All programs</option>
        @foreach (\App\Models\CapstoneExpert::PROGRAMS as $program)
          <option value="{{ $program }}">{{ $program }} &ndash; {{ \App\Models\Student::PROGRAM_COURSES[$program] ?? $program }}</option>
        @endforeach
      </select>
      <div class="input-hint">Teams in this program are graded with it. A program with no rubric of its own for this stage uses the one for all programs.</div>
    </div>
    <div class="mk-f">
      <label class="label" for="rb-desc">Description <span style="font-weight:400;color:var(--text3)">(optional)</span></label>
      <textarea class="input" id="rb-desc" rows="3" placeholder="Brief description of this rubric's purpose and usage context&hellip;"></textarea>
    </div>
  </div>
  <div class="mk-ai" id="rb-ai-box">
    <div class="t"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M5.6 18.4l2.8-2.8M15.6 8.4l2.8-2.8"/></svg> Let the AI draft the criteria</div>
    <div class="mk-f" id="rb-ai-field-topic">
      <label class="label" for="rb-ai-topic">What should the criteria focus on? <span style="font-weight:400;color:var(--text3)">(optional)</span></label>
      <textarea class="input" id="rb-ai-topic" rows="4" placeholder="e.g. &quot;BSIT capstone systems&quot;"></textarea>
    </div>
    <div class="row2">
      <div class="mk-f cnt" id="rb-ai-field-count">
        <label class="label" for="rb-ai-count">Number of criteria (3&ndash;15)</label>
        <input type="number" class="input" id="rb-ai-count" min="3" max="15" value="5"/>
      </div>
      <div class="hint" id="rb-ai-hint"></div>
    </div>
  </div>
  <div class="mk-foot" id="rb-foot-new">
    <button type="button" class="mk-btn ghost" onclick="closeDialog()">Cancel</button>
    <button type="button" class="mk-btn" onclick="startBlank()">Start blank</button>
    <button type="button" class="mk-btn pri" id="rb-ai-btn" onclick="aiGenerateRubric()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M5.6 18.4l2.8-2.8M15.6 8.4l2.8-2.8"/></svg> <span id="rb-ai-btn-label">Draft with AI</span></button>
  </div>
  <div class="mk-foot" id="rb-foot-details" hidden>
    <button type="button" class="mk-btn ghost" onclick="closeDialog()">Cancel</button>
    <button type="button" class="mk-btn pri" onclick="applyDetails()">Apply</button>
  </div>
</div></dialog>

<div class="toast" id="toast"></div>

@include('capsa._sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Grading Rubrics</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="sk" style="height:70px;border-radius:12px;margin-bottom:16px"></div>
    <div class="card" style="padding:16px">
      <div class="sk" style="height:36px;width:100%;border-radius:8px;margin-bottom:16px"></div>
      <div class="sk" style="height:64px;border-radius:10px;margin-bottom:10px"></div>
      <div class="sk" style="height:64px;border-radius:10px;margin-bottom:10px"></div>
      <div class="sk" style="height:64px;border-radius:10px"></div>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content">
    <div class="mk" id="rb-list-view">
      <div class="mk-head">
        <div class="mk-tiles" id="rb-tiles"></div>
        <button type="button" class="mk-btn pri big" onclick="openNewRubricModal()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> New Rubric</button>
      </div>
      <div class="mk-card">
        <div class="mk-bar">
          <span class="ttl" id="rb-list-title">All rubrics</span>
          <span class="cnt" id="rb-count"></span>
          <label class="mk-search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input id="rb-search" oninput="renderList()" placeholder="Search by name, stage or program" aria-label="Search rubrics"/></label>
          <label class="mk-check"><input type="checkbox" id="rb-show-archived" onchange="toggleArchived(this.checked)"/> Show archived</label>
        </div>
        <div class="mk-scroll">
          <table class="mk-t">
            <thead><tr><th>Rubric</th><th>Stage</th><th>Program</th><th>Criteria</th><th>Points</th><th>Last edited</th><th></th></tr></thead>
            <tbody id="rb-list"></tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="mk" id="rb-editor-view" hidden>
      <div class="mk-detail-head">
        <div class="mk-dh-main" id="rb-ed-head"></div>
        <div class="mk-meter" id="mk-total">
          <div class="v"><span>Total</span><span><b>0</b> / 100 pts</span></div>
          <div class="track"><div class="fill"></div></div>
          <div class="hint"></div>
        </div>
        <div class="mk-dh-btns">
          <button type="button" class="mk-btn" onclick="showList()">Cancel</button>
          <button type="button" class="mk-btn pri" id="form-save-btn" onclick="saveRubric()"><span id="form-save-label">Save changes</span></button>
        </div>
      </div>
      <div class="mk-card">
        <div class="mk-scroll">
          <table class="mk-t mk-matrix">
            <colgroup><col style="width:210px"><col><col><col><col><col style="width:40px"></colgroup>
            <thead><tr><th>Criterion</th><th class="lv" style="--c:var(--green)">Excellent</th><th class="lv" style="--c:var(--blue)">Good</th><th class="lv" style="--c:var(--amber)">Fair</th><th class="lv" style="--c:var(--red)">Poor</th><th></th></tr></thead>
            <tbody id="rb-matrix"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  </div>
</div>
@include('capsa.partials.skeleton-toggle')

<script nonce="{{ Vite::cspNonce() }}">
const TYPE_LABEL = {
  pre_proposal:"Pre-Proposal Defense",
  title_defense:"Title Defense",
  pre_oral_defense:"Pre Oral Defense",
  final_oral_defense:"Final Oral Defense",
};
const TYPE_COLOR = {
  pre_proposal:"var(--blue)",
  title_defense:"var(--accent3)",
  pre_oral_defense:"var(--purple)",
  final_oral_defense:"var(--green)",
};
const TYPE_BG = {
  pre_proposal:"var(--blue-bg)",
  title_defense:"rgba(201,168,44,0.1)",
  pre_oral_defense:"var(--purple-bg)",
  final_oral_defense:"var(--green-bg)",
};
const LEVEL_LABELS = ["Excellent","Good","Fair","Poor"];
const LEVEL_COLORS = ["var(--green)","var(--blue)","var(--amber)","var(--red)"];

const STAGE_SHORT = {
  pre_proposal: "Pre-Proposal",
  title_defense: "Title Defense",
  pre_oral_defense: "Pre-Oral",
  final_oral_defense: "Final Oral"
};
const SVG16 = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
const STAGE_ICON = {
  pre_proposal: SVG16 + '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>',
  title_defense: SVG16 + '<polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>',
  pre_oral_defense: SVG16 + '<path d="M2 3h20"/><path d="M21 3v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3"/><path d="m7 21 5-5 5 5"/></svg>',
  final_oral_defense: SVG16 + '<path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/></svg>'
};
const ICON = {
  plus: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
  ok: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>',
  warn: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
  clock: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
  archive: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="5" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/></svg>',
  chev: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m9 18 6-6-6-6"/></svg>',
  restore: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><polyline points="18 11 12 17 6 11"/></svg>'
};
const WARN_SVG = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
const PROGRAMS = @json(\App\Models\CapstoneExpert::PROGRAMS);

function escHtml(str) {
  return String(str == null ? '' : str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

// A criterion is scored by picking exactly ONE of its four performance
// levels - its point value is the first (Excellent) level's score, not the
// sum of all four. Good/Fair/Poor are lower alternative scores for the
// same criterion, not additional points on top of it.
// What a criterion is worth: its highest-scoring level.
//
// This used to read levels[0] - the Excellent box - on the assumption that
// it is always the biggest. Nothing enforces that, so a criterion with
// Excellent 0.25 and Good 1.25 was counted as 0.25 here while the server's
// scoreLevelsMax() stored it as 1.25, and the total on screen disagreed with
// the total that got saved.
function criterionScore(c) {
  if (!c || !c.levels || !c.levels.length) return 0;

  return c.levels.reduce(function (max, lvl) {
    var score = (lvl && lvl.score !== '' && lvl.score !== null && !isNaN(+lvl.score)) ? +lvl.score : 0;

    return score > max ? score : max;
  }, 0);
}

// A criterion is worth its top level, so making room for a new one means
// scaling the OTHER criteria's levels - not just a number.
//
// Two things this is careful about, both learned the hard way:
//
//  - Values snap to 0.25, the same step the point inputs use. Plain
//    proportional scaling turned a tidy 3 / 5 / 10 rubric into 3.07 / 5.12 /
//    10.23, which is nobody's idea of a rubric.
//  - The rounding leftover is spread a quarter-point at a time across the
//    largest criteria rather than dumped on one, so a single criterion does
//    not jump from 10 to 11.75 absorbing everyone else's rounding.
//
// Mirrors rebalanceCriteria() in Api\AdminGradingRubricController.
function fitCriteriaTo100(exceptIndex) {
  // What is left for the adjustable ones: 100 minus the criterion just
  // edited, minus every other figure already set by hand. Leaving the other
  // set figures out of this sum was what pushed the rubric past 100.
  var fixed = draftCriteria.reduce(function (sum, c, i) {
    if (i === exceptIndex || c.pinned) return sum + criterionScore(c);
    return sum;
  }, 0);

  var target = round2(100 - fixed);

  // The points come out of criteria you have NOT set yourself.
  //
  // Taking them from everything meant setting the second criterion to 10
  // clawed points back off the first one you had just set to 10 - so nothing
  // ever stayed where it was put and the rubric could not be typed in. A
  // figure you have entered is now left alone, and only the ones still
  // holding their default absorb the difference.
  var others = draftCriteria.filter(function (c, i) { return i !== exceptIndex && !c.pinned; });
  var current = others.reduce(function (sum, c) { return sum + criterionScore(c); }, 0);

  if (!others.length || current <= 0 || target <= 0) return false;
  if (Math.abs(current - target) < 0.005) return false;

  var factor = target / current;

  others.forEach(function (c) {
    (c.levels || []).forEach(function (lvl) {
      if (lvl.score === '' || lvl.score === null || isNaN(+lvl.score)) return;
      lvl.score = snapToQuarter(+lvl.score * factor);
    });
  });

  spreadRemainder(others, round2(target - others.reduce(function (s, c) { return s + criterionScore(c); }, 0)));

  return true;
}

// Nearest quarter point - what the score inputs step by, so the numbers stay
// the kind a person would have written themselves.
function snapToQuarter(n) {
  return Math.round(n * 4) / 4;
}

// Hand out (or take back) whatever the rounding left over, a quarter at a
// time, largest criteria first. Never takes a criterion below a quarter
// point, and is bounded so a pathological rubric cannot spin here.
function spreadRemainder(criteria, drift) {
  if (Math.abs(drift) < 0.01) return;

  var step = drift > 0 ? 0.25 : -0.25;
  var ranked = criteria.slice().sort(function (a, b) { return criterionScore(b) - criterionScore(a); });
  var i = 0;
  var guard = 0;

  while (Math.abs(drift) >= 0.01 && guard++ < 2000) {
    var c = ranked[i % ranked.length];
    i++;

    if (step < 0 && criterionScore(c) <= 0.25) continue;
    if (!c.levels || !c.levels[0]) continue;

    c.levels[0].score = round2(criterionScore(c) + step);
    drift = round2(drift - step);
  }
}

function round2(n) {
  return Math.round(n * 100) / 100;
}

// Setting what a criterion is worth takes those points off the others, so
// the rubric stays at 100 without anyone subtracting by hand.
//
// Runs for ANY criterion, not just a newly added one, and on commit (blur or
// Enter) rather than each keystroke - so the rest of the rubric does not
// shuffle about while a number is half typed.
function fitOnScoreCommitted(index) {
  newCriterionIndex = null;

  // You typed this figure, so it stops being something the rubric can take
  // points from. Click the marker on the row to release it again.
  if (draftCriteria[index]) draftCriteria[index].pinned = true;

  if (draftCriteria.length < 2) { updateTotalPts(); refreshPinMarkers(); return; }

  // Written straight into the inputs already on the page rather than
  // re-rendering - rebuilding the list destroyed the very box being typed in,
  // which is what made the Excellent field impossible to change.
  splitLowerLevels(index);
  var moved = fitCriteriaTo100(index);
  syncScoreInputs();
  updateTotalPts();
  refreshPinMarkers();

  if (!moved && !isAtHundred(draftTotal())) {
    toast('Every other criterion is set too, so there is nothing left to adjust.', 'info');
  }
}

// Paint the "you set this" markers without rebuilding the list, so the box
// being typed in survives.
function refreshPinMarkers() {
  draftCriteria.forEach(function (c, i) {
    var card = document.getElementById('crc-' + i);
    if (card) card.classList.toggle('pinned', !!c.pinned);
  });
}

// Let a figure go back to being adjustable.
function unpinCriterion(i) {
  if (!draftCriteria[i]) return;
  draftCriteria[i].pinned = false;
  refreshPinMarkers();
}

// Setting what a criterion is worth spreads that down its lower levels.
//
// Applies to levels that already have numbers, not only blank ones - the
// whole point is that changing the top figure carries the rest with it, so
// they do not sit at values that belonged to the old one.
//
// It only ever runs on the criterion whose top score was just committed, so
// criteria you have not touched keep whatever shape you gave them.
function splitLowerLevels(index) {
  var cr = draftCriteria[index];
  if (!cr || !cr.levels || cr.levels.length < 2) return false;

  var top = +cr.levels[0].score || 0;
  if (!top) return false;

  var shares = shareLadderFor(cr.levels.length);

  for (var li = 1; li < cr.levels.length; li++) {
    cr.levels[li].score = snapToQuarter(top * shares[li]);
  }

  return true;
}

// How the levels step down, by how many there are. Four levels fall in even
// quarters; three skip the middle, which is the shape this rubric mostly
// uses already (Excellent 5 / Fair 2.5 / Poor 1).
function shareLadderFor(count) {
  if (count <= 2) return [1, 0.5];
  if (count === 3) return [1, 0.5, 0.25];

  var ladder = [];
  for (var i = 0; i < count; i++) {
    ladder.push(1 - (i * (0.75 / (count - 1))));
  }

  return ladder;
}

// Push the draft's score values back into the inputs already on the page,
// leaving whichever one has focus alone so an edit in progress survives.
function syncScoreInputs() {
  var focused = document.activeElement;

  draftCriteria.forEach(function (c, i) {
    (c.levels || []).forEach(function (lvl, li) {
      var next = String(lvl.score === '' || lvl.score === null ? '' : lvl.score);
      var el = document.getElementById('sc-' + i + '-' + li);
      if (!el || el === focused) return;
      if (el.value !== next) el.value = next;
    });
  });

  refreshCategoryTotals();
}

let rubrics = [];
let stageFilter = '';
let showArchived = false;
let editingId = null;
let draftCriteria = [];
// Which criterion was just added, so scoring it makes room while editing an
// existing one's points does not cascade.
let newCriterionIndex = null;
let nameErrors = [];
let descErrors = [];
let _tt;

const csrfToken = document.querySelector('meta[name="csrf-token"]');
const csrf = csrfToken ? csrfToken.getAttribute('content') : '';

// ── API ──────────────────────────────────────────────────────────────────────
async function loadRubrics() {
  try {
    const res = await fetch('/api/admin/grading-rubrics', {
      headers: { 'Accept': 'application/json' },
    });
    const json = await res.json();
    if (json.success && json.data) {
      rubrics = json.data.map(normalizeRubric);
      renderList();
      updateStats();
    }
  } catch (e) {
    console.error('Failed to load rubrics', e);
    toast('Failed to load rubrics.', 'error');
  }
}

function normalizeRubric(r) {
  return {
    id: r.rubric_id,
    name: r.rubric_name,
    type: r.rubric_type,
    program: r.program || '',
    description: r.description || '',
    isArchived: r.is_archived || false,
    updatedAt: r.updated_at,
    criteria: (r.criteria || []).map(normalizeCriterion),
  };
}

function normalizeCriterion(c) {
  return {
    id: c.criterion_id,
    name: c.criterion_name,
    category: c.category || '',
    categoryMaxPoints: c.category_max_points,
    maxScore: c.max_points,
    levels: (c.score_levels || []).map(function(lv, i) {
      return { label: lv.label || LEVEL_LABELS[i] || '', score: lv.score, desc: lv.desc || '' };
    }),
  };
}

function blankCriterion(category) {
  return { name: '', category: category || '', categoryMaxPoints: null, maxScore: 100, levels: LEVEL_LABELS.map(function(label) { return { label: label, score: '', desc: '' }; }) };
}

// Put an empty criterion at the given place and bring it into view.
// Nothing to make room for until it is given points - that happens in
// fitOnScoreCommitted() when the score is committed.
function addCriterion(at, category) {
  draftCriteria.splice(at, 0, blankCriterion(category));
  newCriterionIndex = at;
  nameErrors = [];
  descErrors = [];
  renderMatrix();
  var row = document.getElementById('crc-' + at);
  if (row) row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  return row;
}

function removeCriterion(idx) {
  draftCriteria.splice(idx, 1);
  nameErrors = [];
  descErrors = [];
  renderMatrix();
}

// The categories in the order they first appear. Criteria with none share
// one band.
function categoryOrder() {
  var cats = [];
  draftCriteria.forEach(function (c) {
    var cat = (c.category || '').trim();
    if (cats.indexOf(cat) === -1) cats.push(cat);
  });
  return cats;
}

// The bands as last drawn, so a band's buttons can name it by position.
var renderedCats = [];

function renderMatrix() {
  renderedCats = categoryOrder();
  var html = draftCriteria.length ? '' : '<tr><td colspan="6" class="mk-empty">No criteria yet. Add a category to start.</td></tr>';

  renderedCats.forEach(function (cat, ci) {
    html += '<tr class="mk-cat" data-cat="' + escHtml(cat) + '"><td colspan="6">' +
      '<input class="mk-cat-in" value="' + escHtml(cat) + '" placeholder="Uncategorised" aria-label="Category name" onchange="renameCategory(' + ci + ', this.value)"/><span></span></td></tr>';
    draftCriteria.forEach(function (cr, i) {
      if ((cr.category || '').trim() === cat) html += criterionRow(cr, i);
    });
    html += '<tr class="mk-add-in"><td colspan="6"><button type="button" onclick="addCriterionToCategory(' + ci + ')">' + ICON.plus + ' Add criterion to ' + escHtml(cat || 'Uncategorised') + '</button></td></tr>';
  });
  html += '<tr class="mk-add"><td colspan="6"><button type="button" onclick="addCategory()">' + ICON.plus + ' Add category</button></td></tr>';

  document.getElementById('rb-matrix').innerHTML = html;
  renderEditorHead();
  updateTotalPts();
  refreshPinMarkers();
}

function criterionRow(cr, i) {
  var nameErr = !!nameErrors[i];
  var html = '<tr id="crc-' + i + '" class="' + (cr.pinned ? 'pinned' : '') + (nameErr ? ' rb-card-error' : '') + '"><td>' +
    '<div class="mk-crit-row"><input class="mk-crit-in rb-crit-name-input' + (nameErr ? ' rb-error' : '') + '" value="' + escHtml(cr.name || '') + '" placeholder="Criterion name" aria-label="Criterion name" oninput="draftCriteria[' + i + '].name=this.value;clearNameError(' + i + ',this)"/>' +
    '<button type="button" class="rb-pin" onclick="unpinCriterion(' + i + ')" title="You set this, so its points are left alone. Click to let it adjust again."><svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></button></div>' +
    (topLevelOutranked(cr) ? '<div class="rb-crit-warn">' + WARN_SVG + escHtml(highestLevel(cr).label || 'A lower level') + ' is worth ' + formatPts(highestLevel(cr).score) + ' pts, more than ' + escHtml(cr.levels[0].label || 'Excellent') + ' at ' + formatPts(+cr.levels[0].score || 0) + '. This criterion counts as ' + formatPts(criterionScore(cr)) + ' pts.</div>' : '') +
    (nameErr ? '<div class="rb-crit-name-error">' + WARN_SVG + ' Criterion Name is required.</div>' : '') +
    '</td>';

  (cr.levels || []).forEach(function (lv, li) {
    var descErr = !!(descErrors[i] && descErrors[i][li]);
    var label = escHtml(lv.label || LEVEL_LABELS[li]);
    // Every level, not only the top one: a criterion is worth its highest,
    // so raising Good above Excellent changes what it is worth and the rest
    // of the rubric moves with it.
    html += '<td><div class="mk-cell">' +
      '<div class="mk-score"><input type="number" id="sc-' + i + '-' + li + '" value="' + escHtml(lv.score === null ? '' : lv.score) + '" min="0" step="0.25" placeholder="0" aria-label="' + label + ' points" oninput="draftCriteria[' + i + '].levels[' + li + '].score=+this.value;syncScoreInputs();updateTotalPts()" onchange="fitOnScoreCommitted(' + i + ')"/> pts</div>' +
      '<textarea class="mk-desc rb-desc-area' + (descErr ? ' rb-error' : '') + '" placeholder="Describe this performance level&hellip;" aria-label="' + label + ' description" oninput="draftCriteria[' + i + '].levels[' + li + '].desc=this.value;clearDescError(' + i + ',' + li + ',this)">' + escHtml(lv.desc || '') + '</textarea>' +
      (descErr ? '<div class="rb-level-desc-error" id="ldesc-err-' + i + '-' + li + '">' + WARN_SVG + ' Performance level description is required.</div>' : '') +
      '</div></td>';
  });

  return html + '<td><button onclick="removeCriterion(' + i + ')" class="rb-remove-btn" title="Remove criterion"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></td></tr>';
}

// A new criterion goes in after the band's last one.
function addCriterionToCategory(ci) {
  var cat = renderedCats[ci], at = draftCriteria.length;
  draftCriteria.forEach(function (c, i) { if ((c.category || '').trim() === cat) at = i + 1; });
  var row = addCriterion(at, cat);
  if (row) row.querySelector('.mk-crit-in').focus({ preventScroll: true });
}

function addCategory() {
  var cats = categoryOrder(), name = 'New category', n = 2;
  while (cats.indexOf(name) !== -1) name = 'New category ' + n++;
  addCriterion(draftCriteria.length, name);
  var band = document.querySelectorAll('#rb-matrix .mk-cat-in')[renderedCats.indexOf(name)];
  if (band) { band.focus({ preventScroll: true }); band.select(); }
}

// Renaming a band renames it on every criterion in it. Giving it another
// band's name merges the two.
function renameCategory(ci, val) {
  var from = renderedCats[ci], to = val.trim();
  draftCriteria.forEach(function (c) { if ((c.category || '').trim() === from) c.category = to; });
  renderMatrix();
}

// A criterion is worth its highest level, so a Good scored above Excellent
// quietly decides the criterion's worth - which reads as the Excellent box
// "not working" when the number you type there is not the one that counts.
function highestLevel(cr) {
  return (cr.levels || []).reduce(function (top, lvl) {
    var score = (lvl && lvl.score !== '' && lvl.score !== null && !isNaN(+lvl.score)) ? +lvl.score : 0;
    var best = (top && top.score !== '' && top.score !== null && !isNaN(+top.score)) ? +top.score : 0;

    return score > best ? lvl : top;
  }, cr.levels && cr.levels[0]);
}

function topLevelOutranked(cr) {
  if (!cr || !cr.levels || cr.levels.length < 2) return false;

  var top = +cr.levels[0].score || 0;

  return criterionScore(cr) > top + 0.001;
}

function clearNameError(i, el) {
  if (nameErrors[i]) {
    nameErrors[i] = false;
    if (el) el.classList.remove('rb-error');
    var card = document.getElementById('crc-' + i);
    if (card) {
      card.classList.remove('rb-card-error');
      var err = card.querySelector('.rb-crit-name-error');
      if (err) err.remove();
    }
  }
}

function clearDescError(i, li, el) {
  if (descErrors[i] && descErrors[i][li]) {
    descErrors[i][li] = false;
    if (el) el.classList.remove('rb-error');
    var err = document.getElementById('ldesc-err-' + i + '-' + li);
    if (err) err.remove();
  }
}

function validateCriterionNames() {
  nameErrors = draftCriteria.map(function(c) { return !(c.name || '').trim(); });
  renderMatrix();
  return nameErrors.indexOf(true) === -1;
}

// Bring the first criterion with a problem into view, so an error is never
// left off-screen.
function openFirstProblemCriterion() {
  for (var i = 0; i < draftCriteria.length; i++) {
    if (nameErrors[i] || (descErrors[i] && descErrors[i].some(Boolean))) {
      var row = document.getElementById('crc-' + i);
      if (row) row.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
  }
}

function validateLevelDescriptions() {
  descErrors = draftCriteria.map(function(c) {
    return (c.levels || []).map(function(lv) { return !(lv.desc || '').trim(); });
  });
  renderMatrix();
  return descErrors.some(function(errs) { return errs.some(Boolean); }) === false;
}

// Adding up the criteria. Rounded, because adding a lot of decimals in
// binary floating point does not land where you expect: scaling 27 criteria
// to fit 100 produced 100.00000000000001, which is not equal to 100, so the
// page insisted the rubric was over and asked to "Reduce 1.42e-14 points".
function draftTotal() {
  return round2(draftCriteria.reduce(function (sum, c) {
    return sum + criterionScore(c);
  }, 0));
}

// Anything inside half a hundredth IS 100 - below the precision the points
// are even stored at, so it is not a difference anyone can act on.
function isAtHundred(total) {
  return Math.abs(total - 100) < 0.005;
}

function updateTotalPts() {
  var total = draftTotal();
  var atHundred = isAtHundred(total);
  var diff = round2(100 - total);
  var meter = document.getElementById('mk-total');
  var num = meter.querySelector('.v b');
  var fill = meter.querySelector('.fill');

  num.textContent = formatPts(total);
  num.classList.toggle('amb', !atHundred);
  fill.style.width = Math.max(0, Math.min(total, 100)) + '%';
  fill.classList.toggle('amb', !atHundred);
  meter.querySelector('.hint').textContent = atHundred ? 'Adds up to 100.'
    : diff > 0 ? formatPts(diff) + ' points still unassigned.'
    : formatPts(-diff) + ' points over.';

  // Never disabled on the total. A rubric is under 100 for the whole time it
  // is being built, and blocking the save meant you could not stop halfway -
  // the page warns instead.
  document.getElementById('form-save-btn').disabled = false;
  refreshCategoryTotals();
}

// Each category's running subtotal. Redrawn on every change, not only on a
// full re-render, or the header keeps showing what it was worth before.
function refreshCategoryTotals() {
  document.querySelectorAll('#rb-matrix tr.mk-cat').forEach(function (band) {
    var cat = band.dataset.cat;
    var total = round2(draftCriteria.reduce(function (sum, c) {
      return ((c.category || '').trim() === cat) ? sum + criterionScore(c) : sum;
    }, 0));
    band.querySelector('span').textContent = formatPts(total) + ' pts';
  });
}

// Drop trailing zeros so a whole number reads as "25", not "25.00".
function formatPts(n) {
  return String(round2(n));
}

// ── DIALOG AND EDITOR ─────────────────────────────────────────────────────────
var rbDialog = document.getElementById('rb-dialog');

// The same dialog starts a rubric and edits its details; only a new rubric
// gets the AI box and Start blank.
function showDialog(isNew) {
  document.getElementById('rb-dlg-title').textContent = isNew ? 'New Rubric' : 'Edit details';
  document.getElementById('rb-dlg-sub').textContent = isNew
    ? 'Name it and pick the stage. The criteria come next, on the full page.'
    : 'Change the name, stage, program or description.';
  document.getElementById('rb-ai-box').hidden = !isNew;
  document.getElementById('rb-foot-new').hidden = !isNew;
  document.getElementById('rb-foot-details').hidden = isNew;
  CapsModal.open(rbDialog, { focus: '#rb-name' });
}

function openNewRubricModal() {
  editingId = null;
  ['rb-name', 'rb-program', 'rb-desc', 'rb-ai-topic'].forEach(function (id) {
    document.getElementById(id).value = '';
  });
  selectStage('');
  document.getElementById('form-save-label').textContent = 'Save rubric';
  draftCriteria = [];
  nameErrors = [];
  descErrors = [];
  newCriterionIndex = null;
  showDialog(true);
}

function openDetails() { showDialog(false); }

function closeDialog() {
  if (rbDialog.open) CapsModal.close(rbDialog);
}

// A name and a stage are all a rubric needs before its criteria.
function detailsValid() {
  var name = document.getElementById('rb-name');
  if (!name.value.trim()) { toast('Enter a rubric name.', 'error'); name.focus(); return false; }
  if (!document.getElementById('rb-type').value) { toast('Select a rubric type.', 'error'); return false; }
  return true;
}

function startBlank() {
  if (!detailsValid()) return;
  draftCriteria = [];
  openEditor();
}

function applyDetails() {
  if (!detailsValid()) return;
  closeDialog();
  renderEditorHead();
}

function selectStage(type) {
  document.getElementById('rb-type').value = type;
  document.querySelectorAll('.mk-stages button[data-type]').forEach(function (b) {
    b.classList.toggle('on', b.dataset.type === type);
  });
  updateAiPanelForType(type);
}

// The editor's heading, drawn from the dialog's fields so Edit details
// shows straight away. The top bar becomes the way back to the list.
function renderEditorHead() {
  var name = document.getElementById('rb-name').value.trim();
  var type = document.getElementById('rb-type').value;
  var prog = document.getElementById('rb-program').value;
  var desc = document.getElementById('rb-desc').value.trim();
  var n = draftCriteria.length, k = categoryOrder().length;

  document.getElementById('rb-ed-head').innerHTML = '<h2>' + escHtml(name) + '</h2>' +
    '<div class="mk-meta"><span class="mk-stage" style="--c:' + (TYPE_COLOR[type] || 'var(--text3)') + '">' + escHtml(TYPE_LABEL[type] || '') + '</span>' +
    '<span class="sep">|</span>' + escHtml(prog || 'All programs') +
    '<span class="sep">|</span>' + n + (n === 1 ? ' criterion' : ' criteria') + ' in ' + k + (k === 1 ? ' category' : ' categories') +
    '<span class="sep">|</span><a href="#" onclick="openDetails();return false">Edit details</a></div>' +
    (desc ? '<div class="mk-dh-desc">' + escHtml(desc) + '</div>' : '');

  document.querySelector('.page-title').innerHTML = '<nav class="mk-crumb"><a href="#" onclick="showList();return false">Grading Rubrics</a><span class="sep">&rsaquo;</span><span>' + escHtml(name) + '</span></nav>';
}

function openEditor() {
  closeDialog();
  document.getElementById('rb-list-view').hidden = true;
  document.getElementById('rb-editor-view').hidden = false;
  renderMatrix();
}

function showList() {
  document.getElementById('rb-editor-view').hidden = true;
  document.getElementById('rb-list-view').hidden = false;
  document.querySelector('.page-title').textContent = 'Grading Rubrics';
}

document.getElementById('del-modal').addEventListener('click', function(e) {
  if (e.target === e.currentTarget) closeDelModal();
});

// Pre-Proposal Defense reproduces the school's real rubric (all 12 of its
// actual criteria) - there's nothing to type or choose a count for, so
// those fields hide and Draft with AI becomes a literal one-click action.
function updateAiPanelForType(type) {
  var isPreProposal = type === 'pre_proposal';
  var topicField = document.getElementById('rb-ai-field-topic');
  var countField = document.getElementById('rb-ai-field-count');
  var hint = document.getElementById('rb-ai-hint');
  if (topicField) topicField.style.display = isPreProposal ? 'none' : '';
  if (countField) countField.style.display = isPreProposal ? 'none' : '';
  if (hint) {
    hint.textContent = isPreProposal
      ? 'Generates the school\'s real Pre-Proposal Defense Rubric (all 12 criteria) — just click Draft with AI.'
      : 'Filled in for this stage; change it as you like. You check and change everything the AI drafts before saving.';
  }

  // A bare "BSIT Capstone" left the model guessing what the stage grades, and
  // it drafted presentation criteria only. Naming the categories fixes that.
  // Only a blank box or another type's template is replaced, never typing.
  var topicInput = document.getElementById('rb-ai-topic');
  if (topicInput) {
    var current = topicInput.value.trim();
    var isTemplate = Object.keys(AI_FOCUS_TEMPLATES).some(function (k) { return AI_FOCUS_TEMPLATES[k] === current; });
    if (current === '' || isTemplate) topicInput.value = AI_FOCUS_TEMPLATES[type] || '';
  }
}

var AI_FOCUS_TEMPLATES = {
  pre_oral_defense: 'Capstone Pre-Oral Defense. Use 3 categories: Manuscript (Chapters 1-5, system design, testing), System Prototype (working features, UI, database), Oral Presentation (delivery, answering the panel).',
  final_oral_defense: 'Capstone Final Oral Defense. Use 3 categories: Manuscript (complete Chapters 1-5, results, conclusions), Final System (all features working, test results, user acceptance), Oral Presentation (delivery, answering the panel).',
};

// ── AI DRAFT ──────────────────────────────────────────────────────────────────
// Drafts name/description/criteria via the same Ollama model used by the
// duplication checker, then opens it in the rubric editor so it's just a
// normal editable draft — nothing is saved
// until the admin clicks Save/Update Rubric like any other rubric.
async function aiGenerateRubric() {
  var type = document.getElementById('rb-type').value;
  if (!type) { toast('Select a rubric type first.', 'error'); return; }

  var btn = document.getElementById('rb-ai-btn');
  var label = document.getElementById('rb-ai-btn-label');
  btn.disabled = true;
  label.textContent = 'Drafting…';

  try {
    var res = await fetch('/api/admin/grading-rubrics/drafts', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({
        rubric_type: type,
        topic: document.getElementById('rb-ai-topic').value.trim(),
        criteria_count: +document.getElementById('rb-ai-count').value,
      }),
    });
    var json = await res.json();
    if (!res.ok || !json.success) {
      toast(json.message || 'AI generation failed.', 'error');
      return;
    }

    var data = json.data;
    document.getElementById('rb-name').value = data.rubric_name || '';
    document.getElementById('rb-desc').value = data.description || '';
    draftCriteria = (data.criteria || []).map(function(c) {
      return {
        name: c.criterion_name || '',
        category: c.category || '',
        categoryMaxPoints: c.category_max_points,
        maxScore: c.max_points,
        levels: (c.levels || []).map(function(lv) {
          return { label: lv.label || '', score: lv.score, desc: lv.desc || '' };
        }),
      };
    });
    nameErrors = [];
    descErrors = [];

    toast('Draft generated — review the criteria before saving.');
    openEditor();
  } catch (e) {
    toast('AI generation failed. Please try again.', 'error');
  } finally {
    btn.disabled = false;
    label.textContent = 'Draft with AI';
  }
}

// ── SAVE via API ──────────────────────────────────────────────────────────────
async function saveRubric() {
  var name = document.getElementById('rb-name').value.trim();
  var type = document.getElementById('rb-type').value;
  var program = document.getElementById('rb-program').value;
  var desc = document.getElementById('rb-desc').value.trim();
  if (!name) { toast('Enter a rubric name.', 'error'); openDetails(); return; }
  if (!type) { toast('Select a rubric type.', 'error'); openDetails(); return; }
  if (!draftCriteria.length) { toast('Add at least one criterion.', 'error'); return; }
  if (!validateCriterionNames()) { toast('Criterion Name is required for every criterion.', 'error'); openFirstProblemCriterion(); return; }
  if (!validateLevelDescriptions()) { toast('Performance level description is required for every performance level.', 'error'); openFirstProblemCriterion(); return; }
  var total = draftTotal();
  // Off 100 is allowed - the rubric is saved as it stands and the total is
  // shown wherever it is used. Warned once, not blocked.
  if (!isAtHundred(total)) {
    toast('Saved with ' + formatPts(total) + ' points, not 100.', 'info');
  }

  var url, method;
  if (editingId) {
    url = '/api/admin/grading-rubrics/' + editingId;
    method = 'PUT';
  } else {
    url = '/api/admin/grading-rubrics';
    method = 'POST';
  }

  try {
    var res = await fetch(url, {
      method: method,
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ rubric_name: name, rubric_type: type, program: program || null, description: desc, total_points: 100 }),
    });
    var json = await res.json();
    if (!res.ok) {
      toast(json.message || 'Something went wrong.', 'error');
      return;
    }

    // Replace the rubric's criteria with the ones on the form. PUT is the
    // whole list; POST to the same address adds a single criterion.
    var rubricId = json.data.rubric_id;
    var syncRes = await fetch('/api/admin/grading-rubrics/' + rubricId + '/criteria', {
      method: 'PUT',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ criteria: draftCriteria.map(function(c) {
        return {
          criterion_name: c.name,
          category: (c.category || '').trim() || null,
          category_max_points: c.categoryMaxPoints || null,
          max_points: criterionScore(c),
          score_levels: JSON.stringify(c.levels.map(function(lv) {
            return { label: lv.label || '', score: lv.score, desc: lv.desc };
          })),
          sort_order: 0,
        };
      }) }),
    });
    var syncJson = await syncRes.json();
    if (!syncRes.ok) {
      toast(syncJson.message || 'Failed to save criteria.', 'error');
      return;
    }

    showList();
    await loadRubrics();
    toast(editingId ? 'Rubric updated.' : 'Rubric saved.');
  } catch (e) {
    toast('Something went wrong.', 'error');
  }
}

// ── EDIT ──────────────────────────────────────────────────────────────────────
function editRubric(id) {
  var rb = rubrics.find(function(r) { return r.id === id; });
  if (!rb) return;
  editingId = id;
  document.getElementById('rb-name').value = rb.name;
  document.getElementById('rb-program').value = rb.program || '';
  document.getElementById('rb-desc').value = rb.description || '';
  selectStage(rb.type);
  document.getElementById('form-save-label').textContent = 'Save changes';
  draftCriteria = JSON.parse(JSON.stringify(rb.criteria));
  // A rubric arrives with nothing pinned - only figures set in this sitting
  // are held.
  draftCriteria.forEach(function (c) { c.pinned = false; });
  nameErrors = [];
  descErrors = [];
  newCriterionIndex = null;
  openEditor();
}

// ── ARCHIVE via API ────────────────────────────────────────────────────────────────
var pendingDelId = null;
var isDeleting = false;
var pendingRestoreId = null;
var isRestoring = false;
function confirmDelete(id) {
  isDeleting = false;
  pendingDelId = id;
  var btn = document.getElementById('del-confirm-btn');
  btn.disabled = false;
  btn.textContent = 'Archive';
  document.getElementById('del-modal-title').textContent = 'Archive Rubric?';
  var rb = rubrics.find(function(r) { return r.id === id; });
  document.getElementById('del-modal-sub').textContent = '"' + (rb ? rb.name : '') + '" will be archived. You can restore it later with Show archived.';
  document.getElementById('del-modal').classList.add('show');
  btn.onclick = executeArchive;
}

async function executeArchive() {
  if (isDeleting) return;
  isDeleting = true;
  var id = pendingDelId;
  var btn = document.getElementById('del-confirm-btn');
  btn.disabled = true;
  btn.textContent = 'Archiving...';
  try {
    var res = await fetch('/api/admin/grading-rubrics/' + id, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
    });
    var json = await res.json();
    if (!res.ok) {
      if (res.status === 404) {
        closeDelModal();
        await loadRubrics();
        toast('Rubric not found. List refreshed.', 'error');
      } else {
        toast(json.message || 'Something went wrong.', 'error');
        btn.disabled = false;
        btn.textContent = 'Archive';
      }
      isDeleting = false;
      return;
    }
    closeDelModal();
    await loadRubrics();
    toast('Rubric archived.');
  } catch (e) {
    toast('Something went wrong.', 'error');
    btn.disabled = false;
    btn.textContent = 'Archive';
  }
  isDeleting = false;
}

function confirmRestore(id) {
  isRestoring = false;
  pendingRestoreId = id;
  var btn = document.getElementById('del-confirm-btn');
  btn.disabled = false;
  btn.textContent = 'Restore';
  document.getElementById('del-modal-title').textContent = 'Restore Rubric?';
  document.getElementById('del-modal-sub').textContent = 'This will restore the rubric and make it available for grading again.';
  document.getElementById('del-modal').classList.add('show');
  btn.onclick = executeRestore;
}

async function executeRestore() {
  if (isRestoring) return;
  isRestoring = true;
  var id = pendingRestoreId;
  var btn = document.getElementById('del-confirm-btn');
  btn.disabled = true;
  btn.textContent = 'Restoring...';
  try {
    var res = await fetch('/api/admin/grading-rubrics/' + id + '/archive', {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
    });
    var json = await res.json();
    if (!res.ok) {
      if (res.status === 404) {
        closeDelModal();
        await loadRubrics();
        toast('Rubric not found. List refreshed.', 'error');
      } else {
        toast(json.message || 'Something went wrong.', 'error');
        btn.disabled = false;
        btn.textContent = 'Restore';
      }
      isRestoring = false;
      return;
    }
    closeDelModal();
    await loadRubrics();
    toast('Rubric restored.');
  } catch (e) {
    toast('Something went wrong.', 'error');
    btn.disabled = false;
    btn.textContent = 'Restore';
  }
  isRestoring = false;
}

function closeDelModal() {
  isDeleting = false;
  isRestoring = false;
  pendingDelId = null;
  pendingRestoreId = null;
  var btn = document.getElementById('del-confirm-btn');
  btn.disabled = false;
  btn.textContent = 'Archive';
  document.getElementById('del-modal-title').textContent = 'Archive Rubric?';
  document.getElementById('del-modal').classList.remove('show');
}

// ── LIST ──────────────────────────────────────────────────────────────────────
// A tile filters the list to its stage; clicking it again shows every stage.
function setStage(t) {
  stageFilter = stageFilter === t ? '' : t;
  renderList();
  updateStats();
}

function toggleArchived(on) {
  showArchived = on;
  renderList();
}

function getFiltered() {
  var q = (document.getElementById('rb-search').value || '').trim().toLowerCase();
  return rubrics.filter(function (r) {
    if (r.isArchived && !showArchived) return false;
    if (stageFilter && r.type !== stageFilter) return false;
    if (!q) return true;
    return [r.name, r.description, TYPE_LABEL[r.type], r.program || 'all programs'].join(' ').toLowerCase().indexOf(q) !== -1;
  });
}

function fmtDate(d) {
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function relTime(d) {
  var days = Math.round((new Date().setHours(0, 0, 0, 0) - new Date(d).setHours(0, 0, 0, 0)) / 864e5);
  if (days <= 0) return 'Today';
  if (days === 1) return 'Yesterday';
  if (days < 7) return days + ' days ago';
  if (days < 60) { var w = Math.floor(days / 7); return w + (w === 1 ? ' week ago' : ' weeks ago'); }
  return Math.floor(days / 30) + ' months ago';
}

// Criteria with no category count as one category between them.
function categoryCount(r) {
  var seen = {};
  (r.criteria || []).forEach(function (c) { seen[(c.category || '').trim()] = 1; });
  return Object.keys(seen).length;
}

function joinAnd(a) {
  return a.length < 2 ? a.join('') : a.slice(0, -1).join(', ') + ' and ' + a[a.length - 1];
}

// Which programs a stage's rubrics cover. Archived ones grade nobody.
function coverage(type) {
  var live = rubrics.filter(function (r) { return r.type === type && !r.isArchived; });
  var own = PROGRAMS.filter(function (p) { return live.some(function (r) { return r.program === p; }); });
  var missing = PROGRAMS.filter(function (p) { return own.indexOf(p) === -1; });

  if (live.some(function (r) { return !r.program; })) {
    return { ok: true, text: 'All programs' + (own.length ? ' · ' + joinAnd(own) + (own.length === 1 ? ' has its own' : ' have their own') : '') };
  }
  if (own.length && !missing.length) return { ok: true, text: 'Every program has its own' };
  if (own.length) return { ok: false, text: joinAnd(missing) + (missing.length === 1 ? ' has none' : ' have none') };
  return { ok: false, text: 'None yet' };
}

function rubricRow(r) {
  var crit = r.criteria || [];
  var k = categoryCount(r);
  var total = round2(crit.reduce(function (s, c) { return s + criterionScore(c); }, 0));
  var diff = round2(100 - total);
  var when = r.updatedAt ? new Date(r.updatedAt) : null;
  var color = TYPE_COLOR[r.type] || 'var(--text3)';

  return '<tr' + (r.isArchived ? ' style="opacity:.6"' : '') + '>' +
    '<td><div class="mk-rub"><span class="mk-ic" style="--c:' + color + '">' + (STAGE_ICON[r.type] || '') + '</span><div class="tx">' +
      '<div class="nm">' + escHtml(r.name) + '</div>' +
      (r.description ? '<div class="ds">' + escHtml(r.description) + '</div>' : '<div class="ds none">No description yet</div>') +
    '</div></div></td>' +
    '<td><span class="mk-stage" style="--c:' + color + '">' + escHtml(TYPE_LABEL[r.type] || r.type) + '</span></td>' +
    '<td class="nw">' + escHtml(r.program || 'All programs') + '</td>' +
    '<td class="mk-num"><b>' + crit.length + '</b><div class="sub">' + k + (k === 1 ? ' category' : ' categories') + '</div></td>' +
    '<td class="mk-num">' + (isAtHundred(total)
      ? '<span class="mk-ok">' + ICON.ok + '100 / 100</span><div class="sub">Adds up</div>'
      : '<span class="mk-short">' + ICON.warn + formatPts(total) + ' / 100</span><div class="sub amb">' + formatPts(Math.abs(diff)) + (diff > 0 ? ' points short' : ' points over') + '</div>') + '</td>' +
    '<td class="nw">' + (when && !isNaN(when)
      ? '<span class="mk-when">' + ICON.clock + fmtDate(when) + '</span><div class="sub">' + relTime(when) + '</div>'
      : '&mdash;') + '</td>' +
    '<td><div class="mk-act"><button type="button" class="mk-btn" onclick="editRubric(' + r.id + ')">Open' + ICON.chev + '</button>' +
      (r.isArchived
        ? '<button type="button" class="mk-btn ghost" onclick="confirmRestore(' + r.id + ')">' + ICON.restore + 'Restore</button>'
        : '<button type="button" class="mk-btn ghost" onclick="confirmDelete(' + r.id + ')">' + ICON.archive + 'Archive</button>') +
    '</div></td></tr>';
}

function renderList() {
  var list = getFiltered();
  document.getElementById('rb-count').textContent = list.length + (list.length === 1 ? ' rubric' : ' rubrics');
  document.getElementById('rb-list-title').textContent = stageFilter ? STAGE_SHORT[stageFilter] : 'All rubrics';
  document.getElementById('rb-list').innerHTML = list.length ? list.map(rubricRow).join('')
    : '<tr><td colspan="7"><div class="mk-empty"><svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="var(--border2)" stroke-width="1.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>' +
      '<div style="font-weight:600;color:var(--text2);margin-top:8px">No rubrics found</div><div style="font-size:13px;margin-top:2px">Try a different filter or add a new rubric.</div></div></td></tr>';
}

function updateStats() {
  document.getElementById('rb-tiles').innerHTML = Object.keys(TYPE_LABEL).map(function (t) {
    var n = rubrics.filter(function (r) { return r.type === t && !r.isArchived; }).length;
    var cov = coverage(t);
    return '<button type="button" class="mk-tile' + (stageFilter === t ? ' on' : '') + '" style="--c:' + TYPE_COLOR[t] + '" aria-pressed="' + (stageFilter === t) + '" onclick="setStage(\'' + t + '\')">' +
      '<div class="top"><span class="mk-ic">' + STAGE_ICON[t] + '</span><b>' + STAGE_SHORT[t] + '</b></div>' +
      '<div class="n">' + n + '<small>' + (n === 1 ? 'rubric' : 'rubrics') + '</small></div>' +
      '<div class="cov' + (cov.ok ? '' : ' none') + '">' + (cov.ok ? ICON.ok : ICON.warn) + '<span>' + escHtml(cov.text) + '</span></div></button>';
  }).join('');
}

function toast(msg, type) {
  if (window.ctToast) { return ctToast(msg, type); }
  var t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(_tt);
  _tt = setTimeout(function() { t.classList.remove('show'); }, 4000);
}

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

// Drawn from the list that came with the page; asked for only if it did not.
@if (isset($initialRubrics))
rubrics = (@json($initialRubrics)).map(normalizeRubric);
renderList();
updateStats();
@else
loadRubrics();
@endif
</script>

<style nonce="{{ Vite::cspNonce() }}">
/* ── SIDEBAR ── */
.hamburger-btn {
  display: none;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 9px;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(201,168,44,0.2);
  cursor: pointer;
  color: var(--navy-text);
  flex-shrink: 0;
  transition: background 0.2s;
}
.hamburger-btn:hover { background: rgba(255,255,255,0.1); }
.hamburger-btn svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; }

.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(0,0,0,0.5);
}

/* ── ARCHIVE CONFIRM + TOAST ── */
.modal-box{border-radius:18px;box-shadow:0 24px 80px rgba(0,0,0,.25);animation:modalSlideIn .25s ease}
.modal-overlay.show{animation:modalFadeIn .2s ease}
.modal-actions .btn{padding:8px 20px;border-radius:10px}
@keyframes modalFadeIn{from{opacity:0}to{opacity:1}}
@keyframes modalSlideIn{from{opacity:0;transform:translateY(-16px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
.toast{border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.15)}

/* ── LIST AND EDITOR ── */
.mk{display:flex;flex-direction:column;gap:16px;height:100%;min-height:0}
.mk *{box-sizing:border-box}
.mk-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px}

/* stage tiles */
.mk-tiles{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;flex:1}
.mk-tile{background:#fff;border:1px solid var(--border);border-radius:12px;padding:12px 14px 12px 16px;position:relative;cursor:pointer;text-align:left;font:inherit;color:inherit}
.mk-tile::before{content:"";position:absolute;left:0;top:12px;bottom:12px;width:3px;border-radius:0 3px 3px 0;background:var(--c)}
.mk-tile.on{border-color:var(--c);box-shadow:0 0 0 1px var(--c) inset}
.mk-tile b{display:block;font-size:13px;color:var(--text)}
.mk-tile .n{font-size:22px;font-weight:700;color:var(--text);font-variant-numeric:tabular-nums;line-height:1.2;margin-top:4px}
.mk-tile .n small{font-size:12px;font-weight:500;color:var(--text3);margin-left:4px}
.mk-tile .cov{display:flex;align-items:center;gap:5px;font-size:12px;color:var(--text2);margin-top:2px}
.mk-tile .cov.none{color:var(--amber);font-weight:600}
.mk-tile .top{display:flex;align-items:center;gap:10px}
.mk-tile .cov svg{color:var(--green);flex-shrink:0}
.mk-tile .cov.none svg{color:var(--amber)}
/* stage icon: the stage colour on a light tint of itself */
.mk-ic{flex-shrink:0;display:grid;place-items:center;width:34px;height:34px;border-radius:9px;color:var(--c);background:color-mix(in srgb,var(--c) 13%,#fff)}
.mk-tile .mk-ic{width:28px;height:28px;border-radius:8px}

/* the list card */
.mk-card{background:#fff;border:1px solid var(--border);border-radius:14px;display:flex;flex-direction:column;min-height:0;flex:1;overflow:hidden}
.mk-bar{display:flex;align-items:center;gap:16px;padding:12px 16px;border-bottom:1px solid var(--border)}
.mk-bar .ttl{font-weight:700;font-size:14px}
.mk-bar .cnt{color:var(--text3);font-size:13px}
.mk-search{margin-left:auto;display:flex;align-items:center;gap:8px;border:1px solid var(--border);border-radius:8px;padding:7px 10px;width:280px;color:var(--text3);font-size:13px}
.mk-search input{border:none;outline:none;background:none;font:inherit;color:var(--text);flex:1;min-width:0}
.mk-check{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text2);cursor:pointer;white-space:nowrap}
.mk-check input{accent-color:var(--accent3)}
.mk-scroll{overflow:auto;min-height:0;flex:1}
table.mk-t{width:100%;border-collapse:separate;border-spacing:0;font-size:13px}
.mk-t th{position:sticky;top:0;background:#18191f;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#d4d6de;padding:10px 12px;border-bottom:0;z-index:1}
.mk-t td{padding:14px 12px;border-bottom:1px solid var(--border);vertical-align:middle;color:var(--text2)}
.mk-t tr:hover td{background:var(--accent-light)}
.mk-t .nm{font-weight:700;color:var(--text);font-size:14px}
.mk-t .ds{font-size:12px;color:var(--text3);margin-top:2px;max-width:270px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mk-t .ds.none{font-style:italic}
.mk-rub{display:flex;align-items:center;gap:12px;min-width:0}
.mk-rub .tx{min-width:0}
.mk-t .sub{font-size:12px;color:var(--text3);margin-top:2px;font-weight:400}
.mk-t .sub.amb{color:var(--amber)}
.mk-t .mk-num b{font-size:14px;color:var(--text)}
.mk-ok,.mk-short,.mk-when{display:inline-flex;align-items:center;gap:6px}
.mk-when{color:var(--text2)}
.mk-when svg{color:var(--text3)}
.mk-stage{display:flex;align-items:center;gap:8px;white-space:nowrap}
.mk-stage::before{content:"";width:8px;height:8px;border-radius:50%;background:var(--c)}
.mk-num{font-variant-numeric:tabular-nums;white-space:nowrap}
.mk-t td.nw{white-space:nowrap}
.mk-ok{color:var(--green);font-weight:700}
.mk-short{color:var(--amber);font-weight:700}
.mk-act{display:flex;gap:6px;justify-content:flex-end}
.mk-btn{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--border);background:#fff;border-radius:8px;padding:6px 12px;font-family:inherit;font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;cursor:pointer}
.mk-btn:disabled{opacity:.6;cursor:default}
.mk-btn.ghost{border-color:transparent;color:var(--text3);font-weight:500}
.mk-btn.pri{background:var(--accent);border-color:var(--accent);color:#18191f;font-weight:700}
.mk-btn.big{padding:9px 16px;font-size:14px}
.mk-empty{padding:32px;text-align:center;color:var(--text3)}

/* editor */
.mk-detail-head{background:#fff;border:1px solid var(--border);border-radius:14px;padding:16px 20px;display:flex;gap:24px;align-items:center}
.mk-dh-main{flex:1;min-width:0}
.mk-dh-main h2{margin:0;font-size:20px;color:var(--text)}
.mk-meta{display:flex;gap:10px;align-items:center;font-size:13px;color:var(--text2);margin-top:6px;flex-wrap:wrap}
.mk-meta .sep{color:var(--border2)}
.mk-meta a{color:var(--accent3);font-weight:600;text-decoration:none}
.mk-dh-desc{font-size:13px;color:var(--text3);margin-top:6px}
.mk-meter{width:230px}
.mk-meter .v{display:flex;justify-content:space-between;align-items:baseline;font-size:12px;color:var(--text3)}
.mk-meter .v b{font-size:20px;color:var(--green);font-variant-numeric:tabular-nums}
.mk-meter .v b.amb{color:var(--amber)}
.mk-meter .track{height:6px;border-radius:3px;background:var(--bg4);margin-top:6px;overflow:hidden}
.mk-meter .fill{height:100%;background:var(--green)}
.mk-meter .fill.amb{background:var(--amber)}
.mk-meter .hint{font-size:12px;color:var(--text3);margin-top:6px}
.mk-dh-btns{display:flex;gap:8px}

.mk-matrix{table-layout:fixed}
.mk-matrix th{padding:10px 12px;background:var(--bg3);color:var(--text3);border-bottom:1px solid var(--border)}
.mk-matrix th.lv{color:var(--c)}
.mk-matrix td{padding:10px 12px;vertical-align:top}
.mk-matrix tr:hover td{background:transparent}
.mk-cat td{background:var(--bg3);padding:8px 12px;font-size:12px;font-weight:700;color:var(--text2);letter-spacing:.04em;text-transform:uppercase}
.mk-cat td span{float:right;font-weight:600;color:var(--text3);text-transform:none;letter-spacing:0;line-height:28px}
.mk-cell{display:flex;flex-direction:column;gap:6px}
.mk-score{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text3)}
.mk-matrix .mk-score input{width:62px;border:1px solid var(--border);border-radius:6px;padding:4px 6px;font:inherit;font-size:13px;font-weight:700;color:var(--text);text-align:right;font-variant-numeric:tabular-nums;background:#fff}
.mk-matrix .mk-desc{width:100%;resize:vertical;font:inherit;font-size:12px;border:1px solid var(--border);border-radius:6px;padding:6px 8px;line-height:1.45;color:var(--text2);min-height:52px;background:#fff}
.mk-matrix .rb-error{border-color:var(--red);background:var(--red-bg)}
.mk-matrix .rb-crit-name-error{margin:4px 0 0}
.mk-crit-row{display:flex;align-items:center;gap:6px}
tr.pinned .rb-pin{display:inline-flex}
tr.rb-card-error td:first-child{box-shadow:inset 3px 0 0 var(--red)}
.mk-add td{padding:10px 12px;border-bottom:none}
.mk-add button{display:inline-flex;align-items:center;gap:6px;border:1px dashed var(--border2);background:#fff;border-radius:8px;padding:8px 14px;font:inherit;font-size:13px;font-weight:600;color:var(--text2);cursor:pointer}
.mk-add-in td{padding:4px 12px 8px;border-bottom:none}
.mk-add-in button{display:inline-flex;align-items:center;gap:6px;border:none;background:none;padding:4px 0;font:inherit;font-size:12.5px;font-weight:600;color:var(--accent3);cursor:pointer}
.mk-matrix .mk-cat-in{border:1px solid var(--border);border-radius:6px;padding:4px 8px;font:inherit;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--text);background:#fff;width:260px;max-width:60%}
.mk-matrix .mk-crit-in{width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 8px;font:inherit;font-size:13px;font-weight:700;color:var(--text);background:#fff}
.mk-matrix .mk-cat-in:focus,.mk-matrix .mk-crit-in:focus,.mk-matrix .mk-desc:focus,.mk-matrix .mk-score input:focus{border-color:var(--accent);outline:none}

/* topbar breadcrumb, same look as Team Management's */
.mk-crumb{display:flex;align-items:center;gap:6px;min-width:0;white-space:nowrap}
.mk-crumb a{color:rgba(255,255,255,.55);font-weight:500;text-decoration:none}
.mk-crumb .sep{color:rgba(255,255,255,.3)}
.mk-crumb span:last-child{overflow:hidden;text-overflow:ellipsis}

/* ── NEW RUBRIC DIALOG ── */
.rb-new .cm-box{display:flex;flex-direction:column;max-width:640px;width:100%;background:#fff;border-radius:16px;padding:24px;gap:16px;max-height:calc(100dvh - 32px);overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.25)}
.rb-new h3{margin:0;font-size:17px}
.rb-new .sub{font-size:13px;color:var(--text3);margin-top:4px}
.mk-f label{display:block;font-size:13px;font-weight:600;color:var(--text);margin-bottom:6px}
.mk-stages{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
.mk-stages button{border:1px solid var(--border);border-radius:8px;padding:9px 10px;font:inherit;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;background:#fff;color:var(--text);cursor:pointer;text-align:left}
.mk-stages button::before{content:"";width:8px;height:8px;border-radius:50%;background:var(--c);flex-shrink:0}
.mk-stages button.on{border-color:var(--c);box-shadow:0 0 0 1px var(--c) inset}
.mk-2col{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.mk-ai{border:1px dashed #e2cf88;background:var(--accent-light);border-radius:10px;padding:12px 14px;display:flex;flex-direction:column;gap:10px}
.mk-ai .t{font-size:13px;font-weight:700;color:var(--accent3);display:flex;align-items:center;gap:6px}
#rb-ai-topic{min-height:104px;line-height:1.55;font-size:14px;background:#fff}
.mk-ai .row2{display:flex;align-items:flex-end;gap:16px}
.mk-ai .cnt{flex-shrink:0}
.mk-ai .cnt label{white-space:nowrap}
#rb-ai-count{width:96px;font-weight:600;font-variant-numeric:tabular-nums;background:#fff}
.mk-ai .hint{font-size:12px;color:var(--text2);line-height:1.45;padding-bottom:3px}
.mk-foot{display:flex;justify-content:flex-end;gap:8px;padding-top:4px}

/* the list or the editor fills the page; each scrolls in its own box */
#real-content .content{display:flex;flex-direction:column;min-height:0;overflow:hidden}
#real-content .content > .mk{flex:1}
.mk[hidden],.rb-new [hidden]{display:none!important}

@media (max-width:768px){
  .hamburger-btn{display:inline-flex}
  .sidebar{display:flex!important;position:fixed!important;z-index:1000;transform:translateX(-100%);transition:transform .25s cubic-bezier(0.16,1,0.3,1);width:250px!important;min-width:250px!important}
  .sidebar-open .sidebar{transform:translateX(0)}
  .sidebar-open .sidebar-overlay{display:block}
  body.sidebar-open{overflow:hidden}
  .topbar{padding:0 14px;gap:10px}
  .content{padding:14px}
  .page-title{font-size:15px}
  .toast{left:16px;right:16px;bottom:16px;text-align:center}
  #real-content .content{overflow-y:auto}
  .mk-card{min-height:360px}
  .mk-head{flex-direction:column;align-items:stretch}
  .mk-tiles{grid-template-columns:repeat(2,minmax(0,1fr))}
  .mk-bar{flex-wrap:wrap}
  .mk-search{width:auto;margin-left:0;flex:1 1 100%;order:3}
  .mk-matrix{min-width:900px}
  .mk-detail-head{flex-wrap:wrap}
  .mk-meter{width:100%}
  .rb-new .cm-box{max-width:none;border-radius:16px 16px 0 0;padding:16px}
  .mk-2col{grid-template-columns:1fr}
  .mk-stages{grid-template-columns:repeat(2,1fr)}
}
@media (max-width:640px){
  .content{padding:12px}
}
@media (max-width:576px){
  .topbar{padding:0 10px}
  .page-title{font-size:14px}
  .content{padding:10px}
}
/* The same edges and title as the portal's other pages on a phone
   (admin.css). This page used to shrink everything a step further at every
   width below here - 5px edges, 7px labels, 9px button text by 390px. */
@media (max-width:480px){
  .topbar{padding:0 10px 0 16px}
  .page-title{font-size:14px}
  .content{padding:10px}
}
@media (max-width:280px){
  .topbar{padding:0 4px;gap:4px}
  .hamburger-btn{width:30px;height:30px}
  .hamburger-btn svg{width:14px;height:14px}
  .page-title{font-size:11px}
  .content{padding:4px}
}
@media (max-width:200px){
  .topbar{padding:0 3px;gap:3px}
  .page-title{font-size:10px}
  .content{padding:3px}
  .sidebar{width:180px!important;min-width:180px!important}
}
@media (max-width:100px){
  .topbar{padding:0 2px;gap:2px}
  .hamburger-btn{width:24px;height:24px}
  .hamburger-btn svg{width:12px;height:12px}
  .page-title{font-size:8px}
  .content{padding:2px}
}
@media (max-width:50px){
  .topbar{height:40px;min-height:40px;padding:0 1px}
  .page-title{font-size:6px}
  .content{padding:1px}
}
</style>
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
@include('partials.modal')
</body>
</html>