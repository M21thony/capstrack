<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Rubric Management - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
</head>
<body>

<!-- DELETE MODAL -->
<div class="modal-overlay" id="del-modal">
  <div class="modal-box">
    <div class="modal-icon-wrap"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="var(--red)" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg></div>
    <div class="modal-title">Delete Rubric?</div>
    <div class="modal-sub" id="del-modal-sub">This will permanently delete the rubric and all its criteria.</div>
    <div class="modal-actions">
      <button class="btn btn-ghost" style="color:var(--text2);border-color:var(--border)" onclick="closeDelModal()">Cancel</button>
      <button class="btn rb-btn-danger" id="del-confirm-btn">Delete</button>
    </div>
  </div>
</div>

<!-- ADD/EDIT RUBRIC MODAL -->
<div class="modal-overlay" id="rubric-modal">
  <div class="modal-box modal-box-lg" id="rb-modal-box">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px">
      <div>
        <div class="modal-title" id="modal-form-title">New Rubric</div>
        <div style="font-size:12px;color:var(--text3);margin-top:2px" id="modal-form-sub">Fill in the details below to create a grading rubric.</div>
      </div>
      <button onclick="closeRubricModal()" class="rb-close-btn">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <!-- Step Indicator -->
    <div class="modal-steps">
      <div class="modal-step active" id="step-ind-1">
        <div class="step-circle">1</div>
        <span>Rubric Info</span>
      </div>
      <div class="step-connector"></div>
      <div class="modal-step" id="step-ind-2">
        <div class="step-circle">2</div>
        <span>Criteria</span>
      </div>
    </div>

    <!-- STEP 1: Rubric Info -->
    <div id="modal-step-1" style="flex:1">
      <div class="form-group">
        <label class="label">Rubric Name <span class="required-star">*</span></label>
        <input class="input" id="rb-name" placeholder="e.g. Pre-Proposal Defense Rubric"/>
        <div class="input-hint">Give a clear, descriptive name for this rubric.</div>
      </div>
      <div class="form-group">
        <label class="label">Rubric Type <span class="required-star">*</span></label>
        <div class="rb-type-grid">
          <label class="rb-type-opt" data-val="pre_proposal">
            <input type="radio" name="rb-type-radio" value="pre_proposal" onchange="selectRbType(this)"/>
            <div class="rb-type-opt-inner">
              <div class="rb-type-icon" style="background:var(--blue-bg)">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="var(--blue)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              </div>
              <div class="rb-type-label">Pre-Proposal</div>
              <div class="rb-type-sublabel">Defense</div>
            </div>
          </label>
          <label class="rb-type-opt" data-val="title_defense">
            <input type="radio" name="rb-type-radio" value="title_defense" onchange="selectRbType(this)"/>
            <div class="rb-type-opt-inner">
              <div class="rb-type-icon" style="background:rgba(201,168,44,0.1)">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="var(--accent3)" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
              </div>
              <div class="rb-type-label">Title Defense</div>
              <div class="rb-type-sublabel">Evaluation</div>
            </div>
          </label>
          <label class="rb-type-opt" data-val="pre_oral_defense">
            <input type="radio" name="rb-type-radio" value="pre_oral_defense" onchange="selectRbType(this)"/>
            <div class="rb-type-opt-inner">
              <div class="rb-type-icon" style="background:var(--purple-bg)">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="var(--purple)" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              </div>
              <div class="rb-type-label">Pre Oral</div>
              <div class="rb-type-sublabel">Defense</div>
            </div>
          </label>
          <label class="rb-type-opt" data-val="final_oral_defense">
            <input type="radio" name="rb-type-radio" value="final_oral_defense" onchange="selectRbType(this)"/>
            <div class="rb-type-opt-inner">
              <div class="rb-type-icon" style="background:var(--green-bg)">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="var(--green)" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
              </div>
              <div class="rb-type-label">Final Oral</div>
              <div class="rb-type-sublabel">Defense</div>
            </div>
          </label>
        </div>
        <input type="hidden" id="rb-type" value=""/>
      </div>

      <div class="rb-ai-panel">
        <div class="rb-ai-panel-header">
          <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="var(--accent3)" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/><circle cx="12" cy="12" r="3"/></svg>
          <span>Draft with AI</span>
        </div>
        <div class="rb-ai-panel-row">
          <div class="rb-ai-field rb-ai-field-topic" id="rb-ai-field-topic">
            <label class="rb-ai-field-label" for="rb-ai-topic">Focus Area <span style="font-weight:400">(optional)</span></label>
            <input class="input" id="rb-ai-topic" placeholder="e.g. &quot;BSIT capstone systems&quot;"/>
          </div>
          <div class="rb-ai-field rb-ai-field-count" id="rb-ai-field-count">
            <label class="rb-ai-field-label" for="rb-ai-count"># of Criteria</label>
            <select class="input" id="rb-ai-count">
              <option value="3">3</option>
              <option value="4">4</option>
              <option value="5" selected>5</option>
              <option value="6">6</option>
              <option value="7">7</option>
              <option value="8">8</option>
              <option value="9">9</option>
              <option value="10">10</option>
              <option value="11">11</option>
              <option value="12">12</option>
              <option value="13">13</option>
              <option value="14">14</option>
              <option value="15">15</option>
            </select>
          </div>
          <button type="button" class="rb-ai-generate-btn" id="rb-ai-btn" onclick="aiGenerateRubric()">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/><circle cx="12" cy="12" r="3"/></svg>
            <span id="rb-ai-btn-label">Generate</span>
          </button>
        </div>
        <div class="input-hint" id="rb-ai-hint">Select a rubric type above, then generate a draft name, description, and criteria you can review and edit before saving. "# of Criteria" sets how many scoring criteria the draft will include.</div>
      </div>

      <div class="form-group" style="margin-bottom:0">
        <label class="label">Description <span style="color:var(--text3);font-weight:400">(optional)</span></label>
        <textarea class="input" id="rb-desc" style="min-height:72px" placeholder="Brief description of this rubric's purpose and usage context…"></textarea>
      </div>
      <div style="display:flex;justify-content:flex-end;margin-top:20px">
        <button class="btn btn-primary" onclick="goToStep2()">
          Next: Add Criteria
          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </div>
    </div>

    <!-- STEP 2: Criteria -->
    <div id="modal-step-2" style="display:none;flex:1;flex-direction:column;overflow:hidden;min-height:0">
      <div class="rb-criteria-toolbar" style="flex-shrink:0">
        <div>
          <div style="font-size:13px;font-weight:600;color:var(--text)">Scoring Criteria</div>
          <div style="font-size:11px;color:var(--text3);margin-top:1px">Set what a criterion is worth and the others give up the points to match. A criterion is worth its highest-scoring level.</div>
        </div>
        <div class="rb-pts-status">
          <div class="rb-pts-badge" id="total-pts-label">0 / 100 pts</div>
          <div class="rb-pts-hint" id="total-pts-hint">Total points are valid.</div>
        </div>
      </div>

      <div class="rb-pts-note" id="total-pts-note">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span>A rubric totals 100 points across its criteria, each worth its highest-scoring level. Set what one is worth and the others give up the points to match.</span>
      </div>

      <div id="criteria-list" style="display:flex;flex-direction:column;gap:10px;padding-right:4px;margin-bottom:12px;flex:1;overflow-y:auto;min-height:0;scroll-behavior:smooth"></div>

      <button class="rb-btn-add-criterion" onclick="addCriterion()" style="flex-shrink:0">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Criterion
      </button>

      <div style="display:flex;gap:8px;margin-top:20px;justify-content:space-between;flex-shrink:0">
        <button class="btn btn-ghost" style="color:var(--text2);border-color:var(--border)" onclick="goToStep1()">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
          Back
        </button>
        <button class="btn btn-primary" id="form-save-btn" onclick="saveRubric()">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          <span id="form-save-label">Save Rubric</span>
        </button>
      </div>
    </div>
  </div>
</div>

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
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
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

    <!-- Page Header: Stats + New Button -->
    <div class="rb-page-header">
      <div class="rb-stat-strip">
        <div class="rb-stat-item">
          <div class="rb-stat-n" id="stat-total" style="color:var(--accent3)">0</div>
          <div class="rb-stat-l">Total Rubrics</div>
        </div>
        <div class="rb-stat-divider"></div>
        <div class="rb-stat-item">
          <div class="rb-stat-n" id="stat-criteria" style="color:var(--blue)">0</div>
          <div class="rb-stat-l">Total Criteria</div>
        </div>
        <div class="rb-stat-divider"></div>
        <div class="rb-stat-item">
          <div class="rb-stat-n" style="color:var(--purple)" id="stat-types">0</div>
          <div class="rb-stat-l">Rubric Types</div>
        </div>
        <div class="rb-stat-divider rb-mobile-divider"></div>
        <div class="rb-stat-item rb-mobile-new-btn">
          <button class="btn btn-primary" onclick="openNewRubricModal()" style="width:100%;justify-content:center">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Rubric
          </button>
        </div>
      </div>
      <button class="btn btn-primary rb-desktop-new-btn" onclick="openNewRubricModal()">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Rubric
      </button>
    </div>

    <!-- Main Card with Tabs + List -->
    <div class="card" style="padding:0;overflow:hidden">

      <!-- Tab Header -->
      <div class="rb-tab-header">
        <div class="rb-tab-nav" id="type-tabs">
          <button class="rb-tab-btn active" onclick="filterType('all',this)" data-type="all">
            All <span class="rb-tab-count" id="tc-all">0</span>
          </button>
          <button class="rb-tab-btn" onclick="filterType('pre_proposal',this)" data-type="pre_proposal">
            Pre-Proposal <span class="rb-tab-count" id="tc-pre_proposal">0</span>
          </button>
          <button class="rb-tab-btn" onclick="filterType('title_defense',this)" data-type="title_defense">
            Title Defense <span class="rb-tab-count" id="tc-title_defense">0</span>
          </button>
          <button class="rb-tab-btn" onclick="filterType('pre_oral_defense',this)" data-type="pre_oral_defense">
            Pre Oral <span class="rb-tab-count" id="tc-pre_oral_defense">0</span>
          </button>
          <button class="rb-tab-btn" onclick="filterType('final_oral_defense',this)" data-type="final_oral_defense">
            Final Oral <span class="rb-tab-count" id="tc-final_oral_defense">0</span>
          </button>
        </div>
        <div class="rb-tab-search">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="var(--text3)" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input class="rb-search-input" id="rb-search" placeholder="Search rubrics&hellip;" oninput="renderList()"/>
        </div>
      </div>

      <!-- Rubric List -->
      <div id="rubric-list" style="padding:16px;display:flex;flex-direction:column;gap:12px;min-height:120px"></div>

      <!-- Pagination Footer -->
      <div class="rb-pagination" id="rb-pagination">
        <div style="font-size:12px;color:var(--text3)" id="pg-info">No results</div>
        <div class="rb-pg-btns">
          <button class="rb-pg-btn" id="pg-prev" onclick="changePage(-1)" disabled>
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <div class="rb-pg-pages" id="pg-pages"></div>
          <button class="rb-pg-btn" id="pg-next" onclick="changePage(1)">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>
      </div>

    </div>
  </div>
  </div>
</div>
@include('capsa.partials.skeleton-toggle')

<script>
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

const ITEMS_PER_PAGE = 3;
let currentPage = 1;

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
  var target = 100;
  var fixed = 0;

  if (typeof exceptIndex === 'number' && draftCriteria[exceptIndex]) {
    fixed = criterionScore(draftCriteria[exceptIndex]);
    target = round2(100 - fixed);
  }

  var others = draftCriteria.filter(function (c, i) { return i !== exceptIndex; });
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

  if (draftCriteria.length < 2) { updateTotalPts(); return; }

  // Written straight into the inputs already on the page rather than
  // re-rendering - rebuilding the list destroyed the very box being typed in,
  // which is what made the Excellent field impossible to change.
  splitLowerLevels(index);
  fitCriteriaTo100(index);
  syncScoreInputs();
  updateTotalPts();
}

// Setting the top level fills in the ones below it: three quarters, a half
// and a quarter of it, snapped to a quarter point.
//
// Only ever fills levels that are still blank or zero. A level you have
// actually written a number into is yours, and is left exactly as it is -
// the point is to save typing the obvious ones, not to overwrite judgement.
function splitLowerLevels(index) {
  var cr = draftCriteria[index];
  if (!cr || !cr.levels || cr.levels.length < 2) return false;

  var top = +cr.levels[0].score || 0;
  if (!top) return false;

  var shares = [1, 0.75, 0.5, 0.25];
  var filled = false;

  for (var li = 1; li < cr.levels.length; li++) {
    var lvl = cr.levels[li];
    var current = (lvl.score === '' || lvl.score === null) ? 0 : +lvl.score;

    if (current !== 0) continue;

    var share = shares[li] !== undefined ? shares[li] : (shares[shares.length - 1] / (li - shares.length + 2));
    lvl.score = snapToQuarter(top * share);
    filled = true;
  }

  return filled;
}

// Push the draft's score values back into the inputs already on the page,
// leaving whichever one has focus alone so an edit in progress survives.
function syncScoreInputs() {
  var focused = document.activeElement;

  draftCriteria.forEach(function (c, i) {
    (c.levels || []).forEach(function (lvl, li) {
      var next = String(lvl.score === '' || lvl.score === null ? '' : lvl.score);

      // The top level shows in two places - the collapsed row and the open
      // body - so both are written, and whichever has focus is left alone.
      [document.getElementById('sc-' + i + '-' + li), document.getElementById('scb-' + i + '-' + li)]
        .forEach(function (el) {
          if (!el || el === focused) return;
          if (el.value !== next) el.value = next;
        });
    });
  });

  refreshCategoryTotals();
}

let rubrics = [];
let activeFilter = "all";
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
      currentPage = 1;
      renderList();
      updateStats();
    }
  } catch (e) {
    console.error('Failed to load rubrics', e);
    toast('Failed to load rubrics.');
  }
}

function normalizeRubric(r) {
  return {
    id: r.rubric_id,
    name: r.rubric_name,
    type: r.rubric_type,
    description: r.description || '',
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

// ── CRITERION BUILDER ─────────────────────────────────────────────────────────
function addCriterion(preload) {
  var crit = preload || { name: '', category: '', categoryMaxPoints: null, maxScore: 100, levels: LEVEL_LABELS.map(function(label) { return { label: label, score: '', desc: '' }; }) };
  if (!preload) {
    draftCriteria.push(crit);
    newCriterionIndex = draftCriteria.length - 1;
    // Open it: a new criterion is the one case where the levels do need
    // filling in straight away.
    openCriterion = newCriterionIndex;
  }
  nameErrors = [];
  descErrors = [];
  renderCriteriaBuilder();
  // Nothing to make room for until it is given points - that happens in
  // fitOnScoreCommitted() when the score is committed.
  if (!preload) {
    var list = document.getElementById('criteria-list');
    var cards = list.querySelectorAll('.rb-criterion-card');
    if (cards.length) {
      cards[cards.length - 1].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }
}

function removeCriterion(idx) {
  draftCriteria.splice(idx, 1);
  nameErrors = [];
  descErrors = [];
  renderCriteriaBuilder();
}

function renderCriteriaBuilder() {
  var el = document.getElementById('criteria-list');
  if (!draftCriteria.length) {
    el.innerHTML = '<div class="rb-empty-criteria">' +
      '<div class="rb-empty-criteria-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="var(--text3)" stroke-width="1.8"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>' +
      '<div style="font-size:13px;font-weight:500;color:var(--text2)">No criteria yet</div>' +
      '<div style="font-size:12px;color:var(--text3);margin-top:3px">Click "Add Criterion" to get started</div>' +
      '</div>';
    updateTotalPts();
    return;
  }
  // Draft criteria are grouped by category (AI-generated drafts arrive
  // pre-grouped; manually-added criteria default to no category and just
  // render under a plain "Ungrouped" heading). A header only renders when
  // the category changes from the previous card, so criteria sharing a
  // category stay visually clustered instead of repeating headers.
  var html = '';
  var lastCategory = undefined;

  draftCriteria.forEach(function(cr, i) {
    var cat = (cr.category || '').trim();
    if (cat !== lastCategory) {
      lastCategory = cat;
      var catTotal = round2(draftCriteria.reduce(function(s, c) {
        return ((c.category || '').trim() === cat) ? s + criterionScore(c) : s;
      }, 0));
      html += '<div class="rb-category-header">' +
        '<span>' + (cat ? escHtml(cat) : 'Ungrouped') + '</span>' +
        '<span class="rb-category-header-pts">' + formatPts(catTotal) + ' pts</span>' +
        '</div>';
    }
    html += renderCriterionCard(cr, i);
  });

  el.innerHTML = html;
  updateTotalPts();
}

// One row per criterion, collapsed.
//
// Every criterion used to render as a full card with its four performance
// levels open - about 400px each, so a 27-criterion rubric was a 10,000-pixel
// scroll to change one number. The row carries what you usually want (the
// name and what it is worth, both editable in place), and the level
// descriptions open only when you ask for them.
function renderCriterionCard(cr, i) {
  var hasNameErr = !!nameErrors[i];
  var hasDescErr = !!(descErrors[i] && descErrors[i].some(Boolean));
  var open = openCriterion === i;

  return '<div class="rb-criterion-card' + (open ? ' open' : '') + (hasNameErr || hasDescErr ? ' rb-card-error' : '') + '" id="crc-' + i + '">' +

    // ---- the row that is always visible ----
    '<div class="rb-crit-row">' +
      '<button type="button" class="rb-crit-toggle" onclick="toggleCriterion(' + i + ')" title="Show performance levels" aria-expanded="' + (open ? 'true' : 'false') + '">' +
        '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>' +
      '</button>' +
      '<div class="rb-crit-num">' + (i + 1) + '</div>' +
      '<input class="input rb-crit-name-input' + (hasNameErr ? ' rb-error' : '') + '" value="' + escHtml(cr.name) + '" placeholder="Criterion name (e.g. Oral Presentation)&hellip;" oninput="draftCriteria[' + i + '].name=this.value;clearNameError(' + i + ',this)"/>' +
      '<div class="rb-crit-row-pts">' +
        '<input type="number" class="input rb-score-tiny" id="sc-' + i + '-0" value="' + cr.levels[0].score + '" placeholder="0" title="What this criterion is worth" oninput="draftCriteria[' + i + '].levels[0].score=+this.value;updateTotalPts()" onchange="fitOnScoreCommitted(' + i + ')" min="0" step="0.25"/>' +
        '<span class="rb-pts-unit">pts</span>' +
      '</div>' +
      '<button onclick="removeCriterion(' + i + ')" class="rb-remove-btn" title="Remove criterion">' +
        '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
      '</button>' +
    '</div>' +

    (topLevelOutranked(cr)
      ? '<div class="rb-crit-warn">' +
          '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' +
          escHtml(highestLevel(cr).label || 'A lower level') + ' is worth ' + formatPts(highestLevel(cr).score) + ' pts, more than ' +
          escHtml(cr.levels[0].label || 'Excellent') + ' at ' + formatPts(+cr.levels[0].score || 0) + '. This criterion counts as ' + formatPts(criterionScore(cr)) + ' pts.' +
        '</div>'
      : '') +
    (hasNameErr ? '<div class="rb-crit-name-error"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Criterion Name is required.</div>' : '') +
    (!open && hasDescErr ? '<div class="rb-crit-name-error"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>A performance level still needs a description &mdash; open this criterion to fill it in.</div>' : '') +

    // ---- the part that opens ----
    '<div class="rb-crit-body">' +
      '<input class="input rb-crit-category-input" value="' + escHtml(cr.category || '') + '" placeholder="Category (optional, e.g. Oral Examination/Presentation)&hellip;" oninput="draftCriteria[' + i + '].category=this.value" onchange="renderCriteriaBuilder()"/>' +
      '<div class="rb-levels-grid">' +
      cr.levels.map(function(lv, li) {
        var levelErr = !!(descErrors[i] && descErrors[i][li]);
        return '<div class="rb-level-card">' +
          '<div class="rb-level-label-wrap">' +
          '<input type="text" class="input rb-level-label-input" style="color:' + LEVEL_COLORS[li] + '" value="' + escHtml(lv.label) + '" placeholder="' + LEVEL_LABELS[li] + '" maxlength="255" oninput="draftCriteria[' + i + '].levels[' + li + '].label=this.value"/>' +
          '<div class="rb-level-input-wrap">' +
          // The top level also appears on the collapsed row, so both write to
          // the same value and each refreshes the other.
          '<input type="number" class="input rb-score-tiny" id="scb-' + i + '-' + li + '" value="' + lv.score + '" placeholder="0" oninput="draftCriteria[' + i + '].levels[' + li + '].score=+this.value;syncScoreInputs();updateTotalPts()"' +
            // Every level, not only the top one: a criterion is worth
            // its highest, so raising Good above Excellent changes what it
            // is worth and the rest of the rubric moves with it.
            ' onchange="fitOnScoreCommitted(' + i + ')" min="0" step="0.25"/>' +
          '<span class="rb-pts-unit">pts</span>' +
          '</div>' +
          '</div>' +
          '<textarea class="input rb-desc-area' + (levelErr ? ' rb-error' : '') + '" placeholder="Describe this performance level&hellip;" oninput="draftCriteria[' + i + '].levels[' + li + '].desc=this.value;clearDescError(' + i + ',' + li + ',this)">' + lv.desc + '</textarea>' +
          (levelErr ? '<div class="rb-level-desc-error" id="ldesc-err-' + i + '-' + li + '"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Performance level description is required.</div>' : '') +
          '</div>';
      }).join('') +
      '</div>' +
    '</div>' +
    '</div>';
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

// Which criterion is open. One at a time - opening them all is the scroll
// this replaced.
let openCriterion = null;

// Which rubric's criteria preview is expanded on the list. One at a time,
// so the list stays a list.
let openRubric = null;

function toggleRubricPreview(id) {
  openRubric = (openRubric === id) ? null : id;
  renderList();
}

function toggleCriterion(i) {
  openCriterion = (openCriterion === i) ? null : i;
  renderCriteriaBuilder();

  if (openCriterion === i) {
    var card = document.getElementById('crc-' + i);
    if (card) card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
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
  renderCriteriaBuilder();
  return nameErrors.indexOf(true) === -1;
}

// Open the first criterion with a problem, so an error is never hidden
// inside a collapsed row.
function openFirstProblemCriterion() {
  for (var i = 0; i < draftCriteria.length; i++) {
    if (nameErrors[i] || (descErrors[i] && descErrors[i].some(Boolean))) {
      openCriterion = i;
      renderCriteriaBuilder();
      var card = document.getElementById('crc-' + i);
      if (card) card.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
  }
}

function validateLevelDescriptions() {
  descErrors = draftCriteria.map(function(c) {
    return (c.levels || []).map(function(lv) { return !(lv.desc || '').trim(); });
  });
  renderCriteriaBuilder();
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

  var lbl = document.getElementById('total-pts-label');
  var hint = document.getElementById('total-pts-hint');
  var note = document.getElementById('total-pts-note');
  var saveBtn = document.getElementById('form-save-btn');

  if (lbl) {
    lbl.textContent = formatPts(total) + ' / 100 pts';
    lbl.className = 'rb-pts-badge' + (atHundred ? ' rb-pts-ok' : diff > 0 ? ' rb-pts-under' : ' rb-pts-over');
  }
  // The banner waits until you have stopped typing.
  //
  // It used to toggle on every keystroke, so ramping a number up one press at
  // a time made it blink in and out as the total crossed 100. The small
  // badge above stays live - that is the useful running feedback - while the
  // banner only settles in once a score box no longer has focus.
  if (note) {
    var editingAScore = document.activeElement && document.activeElement.classList
      && document.activeElement.classList.contains('rb-score-tiny');

    if (!editingAScore) {
      note.classList.toggle('show', !atHundred);
    } else if (atHundred) {
      // Landing on 100 mid-edit is worth reflecting straight away - that is
      // the banner going away, never appearing.
      note.classList.remove('show');
    }
  }
  if (hint) {
    if (atHundred) {
      hint.textContent = 'Adds up to 100.';
      hint.className = 'rb-pts-hint rb-pts-hint-ok';
    } else if (diff > 0) {
      hint.textContent = formatPts(diff) + ' points still unassigned.';
      hint.className = 'rb-pts-hint rb-pts-hint-warn';
    } else {
      hint.textContent = formatPts(-diff) + ' points over.';
      hint.className = 'rb-pts-hint rb-pts-hint-warn';
    }
  }
  // Never disabled on the total. A rubric is under 100 for the whole time it
  // is being built, and blocking the save meant you could not stop halfway -
  // the page warns instead.
  if (saveBtn) {
    saveBtn.disabled = false;
  }
  refreshCategoryTotals();
}

// Each category's running subtotal. Redrawn on every change, not only on a
// full re-render, or the header keeps showing what it was worth before.
function refreshCategoryTotals() {
  document.querySelectorAll('.rb-category-header').forEach(function (header) {
    var name = (header.firstElementChild && header.firstElementChild.textContent || '').trim();
    var cat = name === 'Ungrouped' ? '' : name;
    var total = round2(draftCriteria.reduce(function (sum, c) {
      return ((c.category || '').trim() === cat) ? sum + criterionScore(c) : sum;
    }, 0));

    var ptsEl = header.querySelector('.rb-category-header-pts');
    if (ptsEl) ptsEl.textContent = formatPts(total) + ' pts';
  });
}

// Drop trailing zeros so a whole number reads as "25", not "25.00".
function formatPts(n) {
  return String(round2(n));
}

// ── MODAL ─────────────────────────────────────────────────────────────────────
function openNewRubricModal() {
  editingId = null;
  document.getElementById('rb-name').value = '';
  document.getElementById('rb-type').value = '';
  document.getElementById('rb-desc').value = '';
  document.querySelectorAll('.rb-type-opt').forEach(function(o) { o.classList.remove('selected'); });
  document.querySelectorAll('input[name=rb-type-radio]').forEach(function(r) { r.checked = false; });
  document.getElementById('modal-form-title').textContent = 'New Rubric';
  document.getElementById('modal-form-sub').textContent = 'Fill in the details below to create a grading rubric.';
  document.getElementById('form-save-label').textContent = 'Save Rubric';
  draftCriteria = [];
  nameErrors = [];
  descErrors = [];
  goToStep1();
  document.getElementById('rubric-modal').classList.add('show');
}

function closeRubricModal() {
  document.getElementById('rubric-modal').classList.remove('show');
}

document.getElementById('rubric-modal').addEventListener('click', function(e) {
  if (e.target === e.currentTarget) closeRubricModal();
});
document.getElementById('del-modal').addEventListener('click', function(e) {
  if (e.target === e.currentTarget) closeDelModal();
});

function goToStep1() {
  document.getElementById('rb-modal-box').classList.remove('modal-criteria-mode');
  document.getElementById('modal-step-1').style.display = '';
  document.getElementById('modal-step-2').style.display = 'none';
  document.getElementById('step-ind-1').className = 'modal-step active';
  document.getElementById('step-ind-2').className = 'modal-step';
}

function goToStep2() {
  var name = document.getElementById('rb-name').value.trim();
  var type = document.getElementById('rb-type').value;
  if (!name) { document.getElementById('rb-name').focus(); toast('Enter a rubric name.'); return; }
  if (!type) { toast('Select a rubric type.'); return; }
  document.getElementById('modal-step-1').style.display = 'none';
  document.getElementById('modal-step-2').style.display = 'flex';
  document.getElementById('rb-modal-box').classList.add('modal-criteria-mode');
  document.getElementById('step-ind-1').className = 'modal-step done';
  document.getElementById('step-ind-2').className = 'modal-step active';
  renderCriteriaBuilder();
}

function selectRbType(radio) {
  document.getElementById('rb-type').value = radio.value;
  document.querySelectorAll('.rb-type-opt').forEach(function(o) { o.classList.remove('selected'); });
  radio.closest('.rb-type-opt').classList.add('selected');
  updateAiPanelForType(radio.value);
}

// Pre-Proposal Defense reproduces the school's real rubric (all 12 of its
// actual criteria) - there's nothing to type or choose a count for, so
// those fields hide and Generate becomes a literal one-click action.
function updateAiPanelForType(type) {
  var isPreProposal = type === 'pre_proposal';
  var topicField = document.getElementById('rb-ai-field-topic');
  var countField = document.getElementById('rb-ai-field-count');
  var hint = document.getElementById('rb-ai-hint');
  if (topicField) topicField.style.display = isPreProposal ? 'none' : '';
  if (countField) countField.style.display = isPreProposal ? 'none' : '';
  if (hint) {
    hint.textContent = isPreProposal
      ? 'Generates the school\'s real Pre-Proposal Defense Rubric (all 12 criteria) — just click Generate.'
      : 'Select a rubric type above, then generate a draft name, description, and criteria you can review and edit before saving. "# of Criteria" sets how many scoring criteria the draft will include.';
  }
}

// ── AI DRAFT ──────────────────────────────────────────────────────────────────
// Drafts name/description/criteria via the same Ollama model used by the
// duplication checker, then drops the result straight into the Step 2
// criteria builder so it's just a normal editable draft — nothing is saved
// until the admin clicks Save/Update Rubric like any other rubric.
async function aiGenerateRubric() {
  var type = document.getElementById('rb-type').value;
  if (!type) { toast('Select a rubric type first.'); return; }

  var btn = document.getElementById('rb-ai-btn');
  var label = document.getElementById('rb-ai-btn-label');
  btn.disabled = true;
  label.textContent = 'Generating…';

  try {
    var res = await fetch('/api/admin/grading-rubrics/ai-generate', {
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
      toast(json.message || 'AI generation failed.');
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

    toast('Draft generated — review the criteria below before saving.');
    goToStep2();
  } catch (e) {
    toast('AI generation failed. Please try again.');
  } finally {
    btn.disabled = false;
    label.textContent = 'Generate';
  }
}

// ── SAVE via API ──────────────────────────────────────────────────────────────
async function saveRubric() {
  var name = document.getElementById('rb-name').value.trim();
  var type = document.getElementById('rb-type').value;
  var desc = document.getElementById('rb-desc').value.trim();
  if (!name) { toast('Enter a rubric name.'); goToStep1(); return; }
  if (!type) { toast('Select a rubric type.'); goToStep1(); return; }
  if (!draftCriteria.length) { toast('Add at least one criterion.'); return; }
  if (!validateCriterionNames()) { toast('Criterion Name is required for every criterion.'); openFirstProblemCriterion(); return; }
  if (!validateLevelDescriptions()) { toast('Performance level description is required for every performance level.'); openFirstProblemCriterion(); return; }
  var total = draftTotal();
  // Off 100 is allowed - the rubric is saved as it stands and the total is
  // shown wherever it is used. Warned once, not blocked.
  if (!isAtHundred(total)) {
    toast('Saved with ' + formatPts(total) + ' points, not 100.');
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
      body: JSON.stringify({ rubric_name: name, rubric_type: type, description: desc, total_points: 100 }),
    });
    var json = await res.json();
    if (!res.ok) {
      toast(json.message || 'Something went wrong.');
      return;
    }

    // Sync criteria
    var rubricId = json.data.rubric_id;
    var syncRes = await fetch('/api/admin/grading-rubrics/' + rubricId + '/criteria/sync', {
      method: 'POST',
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
      toast(syncJson.message || 'Failed to save criteria.');
      return;
    }

    closeRubricModal();
    await loadRubrics();
    toast(editingId ? 'Rubric updated.' : 'Rubric saved.');
  } catch (e) {
    toast('Something went wrong.');
  }
}

// ── EDIT ──────────────────────────────────────────────────────────────────────
function editRubric(id) {
  var rb = rubrics.find(function(r) { return r.id === id; });
  if (!rb) return;
  editingId = id;
  document.getElementById('rb-name').value = rb.name;
  document.getElementById('rb-type').value = rb.type;
  document.getElementById('rb-desc').value = rb.description || '';
  document.getElementById('modal-form-title').textContent = 'Edit Rubric';
  document.getElementById('modal-form-sub').textContent = 'Update the rubric details and criteria below.';
  document.getElementById('form-save-label').textContent = 'Update Rubric';
  document.querySelectorAll('.rb-type-opt').forEach(function(o) { o.classList.remove('selected'); });
  document.querySelectorAll('input[name=rb-type-radio]').forEach(function(r) {
    r.checked = (r.value === rb.type);
    if (r.checked) r.closest('.rb-type-opt').classList.add('selected');
  });
  draftCriteria = JSON.parse(JSON.stringify(rb.criteria));
  nameErrors = [];
  descErrors = [];
  goToStep1();
  document.getElementById('rubric-modal').classList.add('show');
}

// ── DELETE via API ────────────────────────────────────────────────────────────
var pendingDelId = null;
var isDeleting = false;
function confirmDelete(id) {
  isDeleting = false;
  pendingDelId = id;
  var btn = document.getElementById('del-confirm-btn');
  btn.disabled = false;
  btn.textContent = 'Delete';
  var rb = rubrics.find(function(r) { return r.id === id; });
  document.getElementById('del-modal-sub').textContent = '"' + (rb ? rb.name : '') + '" and all its criteria will be permanently deleted.';
  document.getElementById('del-modal').classList.add('show');
  btn.onclick = executeDelete;
}

async function executeDelete() {
  if (isDeleting) return;
  isDeleting = true;
  var id = pendingDelId;
  var btn = document.getElementById('del-confirm-btn');
  btn.disabled = true;
  btn.textContent = 'Deleting...';
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
        toast('Rubric not found. List refreshed.');
      } else {
        toast(json.message || 'Something went wrong.');
        btn.disabled = false;
        btn.textContent = 'Delete';
      }
      isDeleting = false;
      return;
    }
    closeDelModal();
    await loadRubrics();
    toast('Rubric deleted.');
  } catch (e) {
    toast('Something went wrong.');
    btn.disabled = false;
    btn.textContent = 'Delete';
  }
  isDeleting = false;
}

function closeDelModal() {
  isDeleting = false;
  pendingDelId = null;
  var btn = document.getElementById('del-confirm-btn');
  btn.disabled = false;
  btn.textContent = 'Delete';
  document.getElementById('del-modal').classList.remove('show');
}

// ── FILTER ────────────────────────────────────────────────────────────────────
function filterType(type, btn) {
  activeFilter = type;
  currentPage = 1;
  document.querySelectorAll('#type-tabs .rb-tab-btn').forEach(function(b) { b.classList.remove('active'); });
  btn.classList.add('active');
  renderList();
}

// ── PAGINATION ────────────────────────────────────────────────────────────────
function getFiltered() {
  var q = (document.getElementById('rb-search').value || '').trim().toLowerCase();
  var list = activeFilter === 'all' ? rubrics : rubrics.filter(function(r) { return r.type === activeFilter; });
  if (q) {
    list = list.filter(function(r) {
      return r.name.toLowerCase().includes(q) ||
        (r.description || '').toLowerCase().includes(q) ||
        (TYPE_LABEL[r.type] || '').toLowerCase().includes(q);
    });
  }
  return list;
}

function changePage(dir) {
  var filtered = getFiltered();
  var totalPages = Math.max(1, Math.ceil(filtered.length / ITEMS_PER_PAGE));
  currentPage = Math.min(Math.max(1, currentPage + dir), totalPages);
  renderList();
}

function jumpPage(p) { currentPage = p; renderList(); }

// ── RENDER LIST ───────────────────────────────────────────────────────────────
function renderList() {
  var filtered = getFiltered();
  var totalPages = Math.max(1, Math.ceil(filtered.length / ITEMS_PER_PAGE));
  if (currentPage > totalPages) currentPage = totalPages;
  var start = (currentPage - 1) * ITEMS_PER_PAGE;
  var paged = filtered.slice(start, start + ITEMS_PER_PAGE);

  ['all', 'pre_proposal', 'title_defense', 'pre_oral_defense', 'final_oral_defense'].forEach(function(t) {
    var el = document.getElementById('tc-' + t);
    if (el) el.textContent = t === 'all' ? rubrics.length : rubrics.filter(function(r) { return r.type === t; }).length;
  });

  var el = document.getElementById('rubric-list');
  if (!filtered.length) {
    el.innerHTML = '<div class="rb-empty-state">' +
      '<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="var(--border2)" stroke-width="1.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>' +
      '<div style="font-size:14px;font-weight:500;color:var(--text2);margin-top:10px">No rubrics found</div>' +
      '<div style="font-size:12px;color:var(--text3);margin-top:4px">Try a different filter or add a new rubric.</div>' +
      '</div>';
    renderPagination(0, 1);
    return;
  }

  el.innerHTML = paged.map(function(rb) {
    var total = rb.criteria.reduce(function(s, c) {
      return s + criterionScore(c);
    }, 0);
    var col = TYPE_COLOR[rb.type] || 'var(--text3)';
    var bg = TYPE_BG[rb.type] || 'var(--bg4)';
    // Edit and Delete live in the header, beside the rubric's name.
    //
    // They used to sit in a footer BELOW every criterion, so acting on a
    // 27-criterion rubric meant scrolling past all 27 to reach the button,
    // then scrolling back up. The criteria are a preview, not the point of
    // this page, so they fold away too - the list is for finding a rubric
    // and doing something with it.
    var open = openRubric === rb.id;

    return '<div class="card rb-rubric-card' + (open ? ' open' : '') + '" style="padding:0;overflow:hidden">' +
      '<div class="rb-rubric-header">' +
      '<div style="flex:1;min-width:0">' +
      '<div style="margin-bottom:6px"><span class="badge" style="background:' + bg + ';color:' + col + ';font-size:10px;font-weight:600">' + (TYPE_LABEL[rb.type] || rb.type) + '</span></div>' +
      '<div style="font-size:15px;font-weight:600;color:var(--text);margin-bottom:3px">' + rb.name + '</div>' +
      (rb.description ? '<div style="font-size:12px;color:var(--text3)">' + rb.description + '</div>' : '') +
      '</div>' +

      '<div class="rb-card-side">' +
      '<div style="text-align:right">' +
      '<div style="font-family:\'Space Mono\',monospace;font-size:22px;font-weight:700;color:' + col + '">' + formatPts(round2(total)) + '<span style="font-size:11px;color:var(--text3);font-family:\'DM Sans\',sans-serif"> pts</span></div>' +
      '<div style="font-size:11px;color:var(--text3)">' + rb.criteria.length + ' criteria</div>' +
      '</div>' +
      '<div class="rb-card-actions">' +
      '<button class="btn btn-ghost" style="font-size:11px;padding:5px 14px" onclick="editRubric(' + rb.id + ')">' +
      '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="m18.5 2.5 3 3L12 15l-4 1 1-4z"/></svg> Edit</button>' +
      '<button class="btn rb-btn-danger" style="font-size:11px;padding:5px 14px" onclick="confirmDelete(' + rb.id + ')">' +
      '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg> Delete</button>' +
      '</div>' +
      '</div>' +
      '</div>' +

      (rb.criteria.length
        ? '<button type="button" class="rb-preview-toggle" onclick="toggleRubricPreview(' + rb.id + ')" aria-expanded="' + (open ? 'true' : 'false') + '">' +
            '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>' +
            (open ? 'Hide the ' + rb.criteria.length + ' criteria' : 'Show the ' + rb.criteria.length + ' criteria') +
          '</button>'
        : '') +

      '<div class="rb-rubric-preview" style="padding:0 18px 14px">' +
      rb.criteria.map(function(cr, i) {
        return '<div class="rb-crit-row">' +
          '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">' +
          '<div style="font-size:12px;font-weight:600;color:var(--text)">' + (i + 1) + '. ' + cr.name + '</div>' +
          '<span style="font-family:\'Space Mono\',monospace;font-size:11px;color:' + col + ';font-weight:700">' + formatPts(criterionScore(cr)) + ' pts</span>' +
          '</div>' +
          '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:5px">' +
          cr.levels.map(function(lv, li) {
            return '<div class="rb-level-chip">' +
              '<div style="font-size:9px;font-weight:700;color:' + LEVEL_COLORS[li] + ';margin-bottom:3px;text-transform:uppercase;letter-spacing:.4px">' + escHtml(lv.label || LEVEL_LABELS[li]) + '</div>' +
              '<div class="rb-level-score">' + lv.score + ' pts</div>' +
              '<div class="rb-level-desc">' + lv.desc + '</div>' +
              '</div>';
          }).join('') +
          '</div>' +
          '</div>';
      }).join('') +
      '</div>' +
      '</div>';
  }).join('');

  renderPagination(filtered.length, totalPages);
}

function renderPagination(total, totalPages) {
  var start = (currentPage - 1) * ITEMS_PER_PAGE + 1;
  var end = Math.min(currentPage * ITEMS_PER_PAGE, total);
  document.getElementById('pg-info').textContent = total ? 'Showing ' + start + '\u2013' + end + ' of ' + total : 'No results';
  document.getElementById('pg-prev').disabled = currentPage <= 1;
  document.getElementById('pg-next').disabled = currentPage >= totalPages;
  var html = '';
  for (var p = 1; p <= totalPages; p++) {
    html += '<button class="rb-pg-num' + (p === currentPage ? ' active' : '') + '" onclick="jumpPage(' + p + ')">' + p + '</button>';
  }
  document.getElementById('pg-pages').innerHTML = html;
  document.getElementById('rb-pagination').style.display = 'flex';
}

function updateStats() {
  document.getElementById('stat-total').textContent = rubrics.length;
  document.getElementById('stat-criteria').textContent = rubrics.reduce(function(s, r) { return s + r.criteria.length; }, 0);
  document.getElementById('stat-types').textContent = new Set(rubrics.map(function(r) { return r.type; })).size;
}

function toast(msg) {
  var t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(_tt);
  _tt = setTimeout(function() { t.classList.remove('show'); }, 2600);
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

loadRubrics();
</script>

<style>
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

/* ── STATS CARDS (desktop + mobile) ── */
.rb-stat-item {
  align-items: center;
  text-align: center;
  gap: 4px;
  transition: all 0.2s ease;
}
.rb-stat-item:hover { transform: translateY(-2px); }
.rb-stat-n { text-align: center; font-size: 24px; transition: color 0.2s; }
.rb-stat-l { text-align: center; font-size: 11px; letter-spacing: 0.6px; }

.rb-desktop-new-btn { display: inline-flex; }
.rb-mobile-new-btn,
.rb-mobile-divider { display: none; }

/* ── NEW RUBRIC BUTTON ── */
.rb-desktop-new-btn {
  padding: 10px 24px;
  border-radius: 10px;
  font-size: 14px;
  gap: 8px;
  transition: all 0.2s ease;
}
.rb-desktop-new-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(201,168,44,.3);
}

/* ── TABS ── */
.rb-tab-btn { padding: 8px 16px; font-size: 13px; }
.rb-tab-btn.active { background: var(--navy-dark); color: #fff; font-weight: 600; }
.rb-search-input:focus { width: 260px; }

/* ── RUBRIC CARDS ── */
.rb-rubric-card {
  border-radius: 14px;
  border: 1px solid var(--border);
  transition: box-shadow 0.25s ease, transform 0.25s ease;
}
.rb-rubric-card:hover {
  box-shadow: 0 8px 28px rgba(0,0,0,.1) !important;
  transform: translateY(-2px);
}

/* ── RUBRIC FOOTER BUTTONS ── */
.rb-rubric-footer .btn {
  border-radius: 8px;
  transition: all 0.2s ease;
}
.rb-rubric-footer .btn:hover { transform: translateY(-1px); }

.rb-btn-danger {
  border-radius: 8px;
  transition: all 0.2s ease;
}
.rb-btn-danger:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(220,38,38,.2);
}

/* ── PAGINATION ── */
.rb-pagination { padding: 14px 20px; }
.rb-pg-btn { border-radius: 8px; transition: all 0.2s ease; }
.rb-pg-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
.rb-pg-num { border-radius: 8px; transition: all 0.2s ease; }
.rb-pg-num:hover:not(.active) {
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
.rb-pg-num.active {
  background: var(--navy-dark);
  border-color: var(--navy-dark);
  color: #fff;
  box-shadow: 0 2px 8px rgba(0,0,0,.15);
}

/* ── MODALS ── */
.modal-box {
  border-radius: 18px;
  box-shadow: 0 24px 80px rgba(0,0,0,.25);
  animation: modalSlideIn 0.25s ease;
}
.modal-overlay.show { animation: modalFadeIn 0.2s ease; }
.modal-actions .btn { padding: 8px 20px; border-radius: 10px; }
.rb-close-btn {
  transition: all 0.2s ease;
  border-radius: 8px;
}
.rb-close-btn:hover { background: var(--bg4); color: var(--text); }
@keyframes modalFadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes modalSlideIn { from { opacity: 0; transform: translateY(12px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }

/* ── TOAST ── */
.toast { border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,.15); }

/* ── ADD CRITERION ── */
.rb-btn-add-criterion {
  transition: all 0.2s ease;
  border-radius: 9px;
}
.rb-btn-add-criterion:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(201,168,44,.2);
}

/* ── AI DRAFT PANEL ── */
.rb-ai-panel {
  background: linear-gradient(135deg, rgba(201,168,44,.08), rgba(201,168,44,.03));
  border: 1px dashed rgba(201,168,44,.4);
  border-radius: 12px;
  padding: 12px 14px;
  margin-bottom: 16px;
}
.rb-ai-panel-header {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  color: var(--accent3);
  margin-bottom: 10px;
}
.rb-ai-panel-row {
  display: flex;
  align-items: flex-end;
  gap: 8px;
}
.rb-ai-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.rb-ai-field-topic { flex: 1; min-width: 0; }
.rb-ai-field-count { width: 90px; flex-shrink: 0; }
.rb-ai-field-label {
  font-size: 10px;
  font-weight: 700;
  color: var(--text2);
  text-transform: uppercase;
  letter-spacing: .4px;
}
.rb-ai-panel-row .input {
  padding: 8px 10px;
  font-size: 12px;
  background: #fff;
}
/* Own styling instead of .btn-ghost - that class is tuned for the dark
   navy topbar/sidebar (near-white text on a near-transparent fill), which
   is unreadable against this panel's near-white background. */
.rb-ai-generate-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  white-space: nowrap;
  gap: 6px;
  font-family: 'DM Sans', sans-serif;
  font-size: 12px;
  font-weight: 700;
  padding: 9px 16px;
  border-radius: 9px;
  border: 1.5px solid var(--accent);
  background: var(--accent);
  color: #18191f;
  cursor: pointer;
  transition: all 0.15s ease;
}
.rb-ai-generate-btn:hover:not(:disabled) {
  background: var(--accent2);
  border-color: var(--accent2);
}
.rb-ai-generate-btn:disabled {
  opacity: .6;
  cursor: not-allowed;
}
@media (max-width: 480px) {
  .rb-ai-panel-row { flex-wrap: wrap; }
  .rb-ai-field-topic { flex: 1 1 100%; }
  .rb-ai-field-count { flex: 1 1 auto; width: auto; }
  .rb-ai-generate-btn { flex: 1 1 100%; justify-content: center; }
}

/* ── EMPTY STATE ── */
.rb-empty-state { padding: 56px 24px; }

/* ================================================================
   RESPONSIVE
   ================================================================ */

@media (max-width: 768px) {
  .hamburger-btn { display: inline-flex; }
  .sidebar {
    display: flex !important;
    position: fixed !important;
    z-index: 1000;
    transform: translateX(-100%);
    transition: transform 0.25s cubic-bezier(0.16,1,0.3,1);
    width: 250px !important;
    min-width: 250px !important;
  }
  .sidebar-open .sidebar { transform: translateX(0); }
  .sidebar-open .sidebar-overlay { display: block; }
  body.sidebar-open { overflow: hidden; }
  .topbar { padding: 0 14px; gap: 10px; }
  .content { padding: 14px; }
  .page-title { font-size: 15px; }

  .rb-desktop-new-btn { display: none; }
  .rb-mobile-new-btn,
  .rb-mobile-divider { display: block; }

  .rb-page-header { flex-direction: column; align-items: stretch; gap: 12px; }
  .rb-stat-strip {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    overflow: visible;
    background: transparent;
    border: none;
    box-shadow: none;
    border-radius: 0;
  }
  .rb-stat-divider { display: none; }
  .rb-stat-item {
    padding: 16px 20px;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 4px;
    min-width: 0;
  }
  .rb-mobile-new-btn {
    padding: 0;
    border: none;
    background: transparent;
    box-shadow: none;
    border-radius: 0;
  }
  .rb-mobile-new-btn .btn-primary {
    height: 100%;
    min-height: 56px;
    border-radius: 12px;
    font-size: 13px;
  }
  .rb-stat-n { font-size: 20px; }
  .rb-stat-l { font-size: 9px; }

  .rb-tab-header { flex-direction: column; }
  .rb-tab-nav { padding: 8px 10px; }
  .rb-tab-btn { font-size: 11px; padding: 5px 10px; }
  .rb-tab-search { border-left: none; border-top: 1px solid var(--border); padding: 8px 12px; }
  .rb-search-input { width: 100%; }
  .rb-search-input:focus { width: 100%; }

  .rb-rubric-header { flex-direction: column; gap: 10px; }
  .rb-rubric-header > div:last-child { flex-direction: row; align-items: center; width: 100%; justify-content: space-between; }
  .rb-rubric-footer { flex-direction: row; flex-wrap: wrap; gap: 6px; }
  .rb-rubric-footer .btn { flex: 1; min-width: 0; }

  .modal-box-lg { width: min(95vw, 700px); }
  .rb-type-grid { grid-template-columns: repeat(2, 1fr); gap: 6px; }
  .rb-type-opt-inner { padding: 10px 6px; }
  .rb-levels-grid { grid-template-columns: 1fr; gap: 6px; }
  .rb-criteria-toolbar { flex-direction: column; gap: 8px; align-items: flex-start; }
  .rb-pts-status { align-items: flex-start; }
  /* Only appears while the rubric is off 100 - see updateTotalPts(). */
  .modal-steps { padding: 10px 12px; }
  .step-connector { width: 24px; margin: 0 4px; }
  .rb-pagination { flex-wrap: wrap; gap: 8px; }
  .toast { left: 16px; right: 16px; bottom: 16px; text-align: center; }
}

@media (max-width: 640px) {
  .content { padding: 12px; }
  .rb-stat-item { padding: 14px 16px; }
  .rb-stat-n { font-size: 18px; }
  .rb-mobile-new-btn .btn-primary { min-height: 50px; font-size: 12px; }
  .rb-tab-btn { font-size: 10px; padding: 4px 8px; }
  .rb-tab-count { min-width: 15px; height: 15px; font-size: 9px; padding: 0 4px; }
  .rb-rubric-footer .btn { font-size: 10px; padding: 5px 10px; }
  .rb-pagination { flex-wrap: wrap; gap: 6px; }
  #pg-info { font-size: 11px; }
}

@media (max-width: 576px) {
  .topbar { padding: 0 10px; }
  .page-title { font-size: 14px; }
  .content { padding: 10px; }
  .rb-stat-strip { gap: 8px; }
  .rb-stat-item { padding: 12px 14px; }
  .rb-stat-n { font-size: 16px; }
  .rb-mobile-new-btn .btn-primary { min-height: 46px; font-size: 12px; }
  .rb-tab-btn { font-size: 10px; padding: 4px 8px; }
  #pg-info { font-size: 11px; }
}

@media (max-width: 480px) {
  .topbar { padding: 0 8px; }
  .page-title { font-size: 13px; }
  .content { padding: 8px; }
  .rb-page-header { gap: 8px; }
  .rb-stat-strip { gap: 6px; }
  .rb-stat-item { padding: 10px 12px; }
  .rb-stat-n { font-size: 15px; }
  .rb-stat-l { font-size: 8px; }
  .rb-mobile-new-btn .btn-primary { min-height: 42px; font-size: 11px; }
  .rb-tab-btn { font-size: 10px; padding: 4px 8px; }
  .rb-tab-count { min-width: 15px; height: 15px; font-size: 9px; padding: 0 4px; }
  .rb-rubric-footer { flex-direction: column; gap: 6px; }
  .rb-rubric-footer .btn { width: 100%; justify-content: center; }
  .rb-rubric-header > div:last-child { flex-direction: row; align-items: center; width: 100%; justify-content: space-between; }
  .rb-type-grid { grid-template-columns: repeat(2, 1fr); gap: 4px; }
  .rb-type-opt-inner { padding: 8px 4px; gap: 4px; }
  .rb-type-icon { width: 30px; height: 30px; }
  .rb-type-label { font-size: 11px; }
  .rb-type-sublabel { font-size: 9px; }
  .rb-criterion-card { padding: 10px; }
  .rb-criterion-header { flex-wrap: wrap; gap: 6px; }
  .rb-crit-name-input { width: 100%; }
  .rb-maxpts-wrap { margin-left: auto; }
  .rb-crit-num { width: 20px; height: 20px; font-size: 10px; }
  .rb-level-card { padding: 8px; }
  .modal-overlay { padding: 10px; align-items: flex-start; padding-top: 20px; }
  .modal-box { padding: 16px !important; }
  .modal-box-lg { padding: 16px !important; }
  .rb-criteria-toolbar { padding: 8px 10px; }
  .rb-pts-badge { font-size: 11px; }
  #pg-info { font-size: 11px; }
  .rb-pg-btn { width: 26px; height: 26px; }
  .rb-pg-num { width: 26px; height: 26px; font-size: 11px; }
}

@media (max-width: 425px) {
  .topbar { padding: 0 6px; gap: 6px; }
  .content { padding: 6px; }
  .rb-stat-item { padding: 8px 10px; }
  .rb-stat-n { font-size: 14px; }
  .rb-mobile-new-btn .btn-primary { min-height: 38px; font-size: 10px; }
  .rb-tab-btn { font-size: 9px; padding: 3px 6px; }
  .rb-rubric-header { gap: 6px; padding: 12px 14px; }
  .rb-rubric-header > div:last-child { flex-direction: row; align-items: center; width: 100%; justify-content: space-between; }
}

@media (max-width: 390px) {
  .content { padding: 5px; }
  .rb-stat-strip { gap: 5px; }
  .rb-stat-item { padding: 6px 8px; }
  .rb-stat-n { font-size: 13px; }
  .rb-stat-l { font-size: 7px; }
  .rb-mobile-new-btn .btn-primary { min-height: 34px; font-size: 9px; }
}

@media (max-width: 375px) {
  .rb-stat-strip { gap: 5px; }
  .rb-stat-item { padding: 6px 8px; }
  .rb-mobile-new-btn .btn-primary { min-height: 34px; }
  .rb-type-grid { grid-template-columns: 1fr 1fr; }
  .rb-crit-name-input { font-size: 12px !important; }
  .rb-maxpts-input { width: 54px !important; font-size: 11px !important; }
  .rb-score-tiny { font-size: 11px !important; }
  .rb-level-label-input { font-size: 11px !important; padding: 5px 7px !important; }
  .rb-desc-area { font-size: 11px !important; min-height: 44px; }
  .rb-level-badge { font-size: 9px; padding: 2px 6px; }
}

@media (max-width: 320px) {
  .rb-stat-strip { grid-template-columns: 1fr; }
  .rb-stat-item { min-width: 0; }
  .rb-stat-l { font-size: 7px; }
  .rb-mobile-new-btn .btn-primary { min-height: 32px; font-size: 9px; }
  .rb-type-grid { grid-template-columns: 1fr; }
  .modal-steps { flex-direction: column; gap: 6px; align-items: flex-start; }
  .step-connector { width: 2px; height: 16px; margin: 0 0 0 10px; }
  .modal-step { width: 100%; }
  .rb-pagination { flex-direction: column; gap: 4px; align-items: flex-start; padding: 8px 10px; }
  #pg-info { white-space: normal; word-break: break-word; }
  .rb-pg-btn { width: 24px; height: 24px; }
  .rb-pg-num { width: 24px; height: 24px; font-size: 10px; }
}

@media (max-width: 280px) {
  .topbar { padding: 0 4px; gap: 4px; }
  .hamburger-btn { width: 30px; height: 30px; }
  .hamburger-btn svg { width: 14px; height: 14px; }
  .page-title { font-size: 11px; }
  .content { padding: 4px; }
  .rb-stat-strip { gap: 4px; }
  .rb-stat-item { padding: 5px 6px; }
  .rb-stat-n { font-size: 12px; }
  .rb-mobile-new-btn .btn-primary { min-height: 28px; font-size: 8px; }
  .rb-pagination { flex-direction: column; gap: 4px; align-items: flex-start; padding: 6px 8px; }
  #pg-info { font-size: 10px; }
  .rb-pg-btn { width: 22px; height: 22px; }
  .rb-pg-num { width: 22px; height: 22px; font-size: 10px; }
}

@media (max-width: 200px) {
  .topbar { padding: 0 3px; gap: 3px; }
  .page-title { font-size: 10px; }
  .content { padding: 3px; }
  .sidebar { width: 180px !important; min-width: 180px !important; }
  .rb-stat-item { padding: 3px 4px; }
  .rb-stat-n { font-size: 10px; }
  .rb-mobile-new-btn .btn-primary { min-height: 24px; font-size: 7px; }
  .modal-box { padding: 10px !important; }
  .rb-pagination { flex-direction: column; gap: 3px; align-items: flex-start; padding: 4px 6px; }
  #pg-info { font-size: 9px; white-space: normal; word-break: break-word; }
  .rb-pg-btn { width: 20px; height: 20px; }
  .rb-pg-num { width: 20px; height: 20px; font-size: 9px; }
}

@media (max-width: 100px) {
  .topbar { padding: 0 2px; gap: 2px; }
  .hamburger-btn { width: 24px; height: 24px; }
  .hamburger-btn svg { width: 12px; height: 12px; }
  .page-title { font-size: 8px; }
  .content { padding: 2px; }
  .rb-stat-item { padding: 2px 3px; }
  .rb-stat-n { font-size: 8px; }
  .rb-mobile-new-btn .btn-primary { min-height: 20px; font-size: 6px; }
  .rb-pagination { flex-direction: column; gap: 2px; align-items: stretch; padding: 3px 4px; }
  #pg-info { font-size: 8px; white-space: normal; word-break: break-word; }
}

@media (max-width: 50px) {
  .topbar { height: 40px; min-height: 40px; padding: 0 1px; }
  .page-title { font-size: 6px; }
  .content { padding: 1px; }
  .rb-stat-item { padding: 2px 2px; }
  .rb-stat-n { font-size: 7px; }
  .rb-mobile-new-btn .btn-primary { min-height: 16px; font-size: 5px; }
  .rb-pagination { flex-direction: column; gap: 2px; align-items: stretch; padding: 2px 3px; }
  #pg-info { font-size: 7px; white-space: normal; word-break: break-word; }
}
</style>
</body>
</html>