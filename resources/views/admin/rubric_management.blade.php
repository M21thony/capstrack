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
          <div style="font-size:11px;color:var(--text3);margin-top:1px">Score-level points across all criteria must total exactly 100 pts.</div>
        </div>
        <div class="rb-pts-status">
          <div class="rb-pts-badge" id="total-pts-label">0 / 100 pts</div>
          <div class="rb-pts-hint" id="total-pts-hint">Total points are valid.</div>
        </div>
      </div>

      <div class="rb-pts-note" id="total-pts-note">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span>Total score-level points must equal exactly 100 points before the rubric can be saved.</span>
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

@include('admin._sidebar')
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

  <div class="content">

    <div id="rb-main">

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

    <!-- SKELETON LOADING -->
    <div id="rb-skeleton" class="rb-skeleton" style="display:none">

      <div class="rb-page-header">
        <div class="rb-stat-strip">
          <div class="rb-stat-item" style="align-items:center">
            <div class="sk rb-sk-n"></div>
            <div class="sk rb-sk-l"></div>
          </div>
          <div class="rb-stat-divider"></div>
          <div class="rb-stat-item" style="align-items:center">
            <div class="sk rb-sk-n"></div>
            <div class="sk rb-sk-l"></div>
          </div>
          <div class="rb-stat-divider"></div>
          <div class="rb-stat-item" style="align-items:center">
            <div class="sk rb-sk-n"></div>
            <div class="sk rb-sk-l"></div>
          </div>
          <div class="rb-stat-divider rb-mobile-divider"></div>
          <div class="rb-stat-item rb-mobile-new-btn">
            <div class="sk rb-sk-new-mobile"></div>
          </div>
        </div>
        <div class="sk rb-sk-new-btn rb-desktop-new-btn" style="flex-shrink:0"></div>
      </div>

      <div class="card" style="padding:0;overflow:hidden">

        <div class="rb-tab-header">
          <div class="rb-tab-nav" style="pointer-events:none">
            <div class="sk rb-sk-tab" style="width:52px"></div>
            <div class="sk rb-sk-tab" style="width:96px"></div>
            <div class="sk rb-sk-tab" style="width:98px"></div>
            <div class="sk rb-sk-tab" style="width:76px"></div>
            <div class="sk rb-sk-tab" style="width:78px"></div>
            <div class="sk rb-sk-tab" style="width:86px"></div>
          </div>
          <div class="rb-tab-search">
            <div class="sk rb-sk-searchbar"></div>
          </div>
        </div>

        <div style="padding:20px 16px;display:flex;flex-direction:column;gap:12px">

          <div class="sk rb-sk-line" style="width:100%"></div>
          <div class="sk rb-sk-line" style="width:88%"></div>
          <div class="sk rb-sk-line" style="width:72%"></div>
          <div class="sk rb-sk-line" style="width:94%"></div>
          <div class="sk rb-sk-line" style="width:64%"></div>

        </div>

        <div class="rb-pagination" style="justify-content:flex-start">
          <div class="sk rb-sk-pginfo"></div>
        </div>

      </div>

    </div>
  </div>
</div>

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

let rubrics = [];
let activeFilter = "all";
let editingId = null;
let draftCriteria = [];
let nameErrors = [];
let descErrors = [];
let _tt;

const csrfToken = document.querySelector('meta[name="csrf-token"]');
const csrf = csrfToken ? csrfToken.getAttribute('content') : '';

// ── API ──────────────────────────────────────────────────────────────────────
async function loadRubrics() {
  showSkeleton();
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
  } finally {
    hideSkeleton();
  }
}

function showSkeleton() {
  var sk = document.getElementById('rb-skeleton');
  var main = document.getElementById('rb-main');
  if (sk) sk.style.display = 'block';
  if (main) main.style.display = 'none';
}

function hideSkeleton() {
  var sk = document.getElementById('rb-skeleton');
  var main = document.getElementById('rb-main');
  if (sk) sk.style.display = 'none';
  if (main) main.style.display = '';
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
    maxScore: c.max_points,
    levels: (c.score_levels || []).map(function(lv, i) {
      return { label: lv.label || LEVEL_LABELS[i] || '', score: lv.score, desc: lv.desc || '' };
    }),
  };
}

// ── CRITERION BUILDER ─────────────────────────────────────────────────────────
function addCriterion(preload) {
  var crit = preload || { name: '', maxScore: 100, levels: LEVEL_LABELS.map(function(label) { return { label: label, score: '', desc: '' }; }) };
  if (!preload) draftCriteria.push(crit);
  nameErrors = [];
  descErrors = [];
  renderCriteriaBuilder();
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
  el.innerHTML = draftCriteria.map(function(cr, i) {
    var hasNameErr = !!nameErrors[i];
    return '<div class="rb-criterion-card' + (hasNameErr ? ' rb-card-error' : '') + '" id="crc-' + i + '">' +
      '<div class="rb-criterion-header">' +
      '<div class="rb-crit-num">' + (i + 1) + '</div>' +
      '<input class="input rb-crit-name-input' + (hasNameErr ? ' rb-error' : '') + '" value="' + escHtml(cr.name) + '" placeholder="Criterion name (e.g. Oral Presentation)&hellip;" oninput="draftCriteria[' + i + '].name=this.value;clearNameError(' + i + ',this)"/>' +
      '<button onclick="removeCriterion(' + i + ')" class="rb-remove-btn" title="Remove criterion">' +
      '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
      '</button>' +
      '</div>' +
      (hasNameErr ? '<div class="rb-crit-name-error"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Criterion Name is required.</div>' : '') +
      '<div class="rb-levels-grid">' +
      cr.levels.map(function(lv, li) {
        var hasDescErr = !!(descErrors[i] && descErrors[i][li]);
        return '<div class="rb-level-card">' +
          '<div class="rb-level-label-wrap">' +
          '<input type="text" class="input rb-level-label-input" style="color:' + LEVEL_COLORS[li] + '" value="' + escHtml(lv.label) + '" placeholder="' + LEVEL_LABELS[li] + '" maxlength="255" oninput="draftCriteria[' + i + '].levels[' + li + '].label=this.value"/>' +
          '<div class="rb-level-input-wrap">' +
          '<input type="number" class="input rb-score-tiny" value="' + lv.score + '" placeholder="0" oninput="draftCriteria[' + i + '].levels[' + li + '].score=+this.value;updateTotalPts()" min="0" step="0.25"/>' +
          '<span class="rb-pts-unit">pts</span>' +
          '</div>' +
          '</div>' +
          '<textarea class="input rb-desc-area' + (hasDescErr ? ' rb-error' : '') + '" placeholder="Describe this performance level&hellip;" oninput="draftCriteria[' + i + '].levels[' + li + '].desc=this.value;clearDescError(' + i + ',' + li + ',this)">' + lv.desc + '</textarea>' +
          (hasDescErr ? '<div class="rb-level-desc-error" id="ldesc-err-' + i + '-' + li + '"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Performance level description is required for ' + escHtml(((lv.label || '').trim() || LEVEL_LABELS[li])) + '.</div>' : '') +
          '</div>';
      }).join('') +
      '</div>' +
      '</div>';
  }).join('');
  updateTotalPts();
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

function validateLevelDescriptions() {
  descErrors = draftCriteria.map(function(c) {
    return (c.levels || []).map(function(lv) { return !(lv.desc || '').trim(); });
  });
  renderCriteriaBuilder();
  return descErrors.some(function(errs) { return errs.some(Boolean); }) === false;
}

function updateTotalPts() {
  var total = draftCriteria.reduce(function(s, c) {
    return s + (c.levels || []).reduce(function(ss, lv) { return ss + (+lv.score || 0); }, 0);
  }, 0);
  var lbl = document.getElementById('total-pts-label');
  var hint = document.getElementById('total-pts-hint');
  var note = document.getElementById('total-pts-note');
  var diff = 100 - total;
  if (lbl) {
    lbl.textContent = total + ' / 100 pts';
    lbl.className = 'rb-pts-badge' + (total === 100 ? ' rb-pts-ok' : diff > 0 ? ' rb-pts-under' : ' rb-pts-over');
  }
  if (note) {
    note.classList.toggle('show', total !== 100);
  }
  if (hint) {
    if (total === 100) {
      hint.textContent = 'Total points are valid.';
      hint.className = 'rb-pts-hint rb-pts-hint-ok';
    } else if (diff > 0) {
      hint.textContent = 'Add ' + diff + ' more points.';
      hint.className = 'rb-pts-hint rb-pts-hint-warn';
    } else {
      hint.textContent = 'Reduce ' + (-diff) + ' points.';
      hint.className = 'rb-pts-hint rb-pts-hint-warn';
    }
  }
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
  document.getElementById('form-save-btn').disabled = false;
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
}

// ── SAVE via API ──────────────────────────────────────────────────────────────
var isSavingRubric = false;

async function saveRubric() {
  if (isSavingRubric) return;
  var name = document.getElementById('rb-name').value.trim();
  var type = document.getElementById('rb-type').value;
  var desc = document.getElementById('rb-desc').value.trim();
  if (!name) { toast('Enter a rubric name.'); goToStep1(); return; }
  if (!type) { toast('Select a rubric type.'); goToStep1(); return; }
  if (!draftCriteria.length) { toast('Add at least one criterion.'); return; }

  var namesOk = validateCriterionNames();
  var descsOk = validateLevelDescriptions();
  var total = draftCriteria.reduce(function(s, c) {
    return s + (c.levels || []).reduce(function(ss, lv) { return ss + (+lv.score || 0); }, 0);
  }, 0);
  var pointsOk = total === 100;

  if (!namesOk || !descsOk || !pointsOk) {
    showValidationSummary(namesOk, descsOk, pointsOk, total);
    return;
  }

  var url = editingId ? '/api/admin/grading-rubrics/' + editingId : '/api/admin/grading-rubrics';
  var method = editingId ? 'PUT' : 'POST';
  var criteriaPayload = draftCriteria.map(function(c) {
    return {
      criterion_name: c.name,
      max_points: (c.levels || []).reduce(function(ss, lv) { return ss + (+lv.score || 0); }, 0),
      score_levels: JSON.stringify(c.levels.map(function(lv) {
        return { label: lv.label || '', score: lv.score, desc: lv.desc };
      })),
      sort_order: 0,
    };
  });

  isSavingRubric = true;
  var saveBtn = document.getElementById('form-save-btn');
  var saveLabel = document.getElementById('form-save-label');
  saveBtn.disabled = true;
  saveLabel.textContent = editingId ? 'Updating...' : 'Saving...';

  try {
    var res = await fetch(url, {
      method: method,
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({
        rubric_name: name,
        rubric_type: type,
        description: desc,
        total_points: 100,
        criteria: criteriaPayload,
      }),
    });
    var json = await res.json();
    if (!res.ok) {
      var msgs = apiErrorMessages(json);
      showErrorList(msgs.length ? msgs : ['Something went wrong.']);
      return;
    }

    upsertRubricInList(json.data);
    closeRubricModal();
    toast(editingId ? 'Rubric updated.' : 'Rubric saved.');
  } catch (e) {
    toast('Something went wrong.');
  } finally {
    isSavingRubric = false;
    if (document.getElementById('rubric-modal').classList.contains('show')) {
      saveBtn.disabled = false;
      saveLabel.textContent = editingId ? 'Update Rubric' : 'Save Rubric';
    }
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
  document.getElementById('form-save-btn').disabled = false;
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
      return s + (c.levels || []).reduce(function(ss, lv) { return ss + (+lv.score || 0); }, 0);
    }, 0);
    var col = TYPE_COLOR[rb.type] || 'var(--text3)';
    var bg = TYPE_BG[rb.type] || 'var(--bg4)';
    return '<div class="card rb-rubric-card" style="padding:0;overflow:hidden">' +
      '<div class="rb-rubric-header">' +
      '<div style="flex:1;min-width:0">' +
      '<div style="margin-bottom:6px"><span class="badge" style="background:' + bg + ';color:' + col + ';font-size:10px;font-weight:600">' + (TYPE_LABEL[rb.type] || rb.type) + '</span></div>' +
      '<div style="font-size:15px;font-weight:600;color:var(--text);margin-bottom:3px">' + rb.name + '</div>' +
      (rb.description ? '<div style="font-size:12px;color:var(--text3)">' + rb.description + '</div>' : '') +
      '</div>' +
      '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0">' +
      '<div style="font-family:\'Space Mono\',monospace;font-size:22px;font-weight:700;color:' + col + '">' + total + '<span style="font-size:11px;color:var(--text3);font-family:\'DM Sans\',sans-serif"> pts</span></div>' +
      '<div style="font-size:11px;color:var(--text3)">' + rb.criteria.length + ' criteria</div>' +
      '</div>' +
      '</div>' +
      '<div style="padding:0 18px 14px">' +
      rb.criteria.map(function(cr, i) {
        return '<div class="rb-crit-row">' +
          '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">' +
          '<div style="font-size:12px;font-weight:600;color:var(--text)">' + (i + 1) + '. ' + cr.name + '</div>' +
          '<span style="font-family:\'Space Mono\',monospace;font-size:11px;color:' + col + ';font-weight:700">' + (cr.levels || []).reduce(function(ss, lv) { return ss + (+lv.score || 0); }, 0) + ' pts</span>' +
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
      '<div class="rb-rubric-footer">' +
      '<button class="btn btn-ghost" style="font-size:11px;padding:5px 14px" onclick="editRubric(' + rb.id + ')">' +
      '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="m18.5 2.5 3 3L12 15l-4 1 1-4z"/></svg> Edit</button>' +
      '<button class="btn rb-btn-danger" style="font-size:11px;padding:5px 14px" onclick="confirmDelete(' + rb.id + ')">' +
      '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg> Delete</button>' +
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

function upsertRubricInList(rubricData) {
  var rb = normalizeRubric(rubricData);
  var idx = rubrics.findIndex(function(r) { return r.id === rb.id; });
  if (idx >= 0) {
    rubrics[idx] = rb;
  } else {
    rubrics.unshift(rb);
  }
  renderList();
  updateStats();
}

function apiErrorMessages(json) {
  var msgs = [];
  if (json && json.errors && typeof json.errors === 'object') {
    Object.keys(json.errors).forEach(function(k) {
      var v = json.errors[k];
      if (Array.isArray(v)) v.forEach(function(m) { msgs.push(m); });
      else if (typeof v === 'string') msgs.push(v);
    });
  }
  if (!msgs.length && json && json.message) msgs.push(json.message);
  return msgs;
}

function showErrorList(msgs) {
  var t = document.getElementById('toast');
  if (!msgs.length) { toast('Something went wrong.'); return; }
  t.innerHTML = msgs.map(function(m) { return '<div>' + escHtml(m) + '</div>'; }).join('');
  t.classList.add('show');
  clearTimeout(_tt);
  _tt = setTimeout(function() { t.classList.remove('show'); t.innerHTML = ''; }, 3200);
}

function showValidationSummary(namesOk, descsOk, pointsOk, total) {
  var msgs = [];
  nameErrors.forEach(function(err, i) {
    if (err) msgs.push('Criterion Name is required for criterion ' + (i + 1) + '.');
  });
  descErrors.forEach(function(errs, i) {
    errs.forEach(function(err, li) {
      if (err) {
        var lv = draftCriteria[i] && draftCriteria[i].levels[li];
        var lbl = ((lv && lv.label) || '').trim() || LEVEL_LABELS[li];
        msgs.push('Performance level description is required for ' + lbl + '.');
      }
    });
  });
  if (!pointsOk) msgs.push('Total score-level points must equal exactly 100 points (currently ' + total + ').');
  showErrorList(msgs);
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
  border-radius: 8px;
  transition: none;
}
.rb-close-btn:hover { background: var(--bg4); color: var(--text); }
@keyframes modalFadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes modalSlideIn { from { opacity: 0; transform: translateY(12px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }

/* ── SKELETON LOADING ── */
.rb-skeleton { pointer-events: none; }
.sk {
  position: relative;
  overflow: hidden;
  background: rgba(148,163,184,.22);
  border-radius: 6px;
}
.sk::after {
  content: "";
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(90deg, transparent, rgba(255,255,255,.55), transparent);
  animation: rb-sk-shimmer 1.5s ease-in-out infinite;
}
@keyframes rb-sk-shimmer { 100% { transform: translateX(100%); } }
.rb-sk-n { width: 46px; height: 20px; border-radius: 5px; }
.rb-sk-l { width: 76px; height: 9px; border-radius: 4px; }
.rb-sk-new-btn { width: 122px; height: 40px; border-radius: 10px; }
.rb-sk-new-mobile { width: 100%; height: 52px; border-radius: 12px; }
.rb-sk-tab { height: 28px; border-radius: 8px; }
.rb-sk-searchbar { width: 176px; height: 30px; border-radius: 7px; }
.rb-sk-line { height: 18px; border-radius: 6px; flex-shrink: 0; }
.rb-sk-pginfo { width: 150px; height: 12px; border-radius: 4px; }

/* ── TOAST ── */
.toast { border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,.15); }
#toast { flex-direction: column; align-items: flex-start; gap: 3px; white-space: normal; max-width: 360px; }

/* ── ADD CRITERION ── */
.rb-btn-add-criterion {
  transition: all 0.2s ease;
  border-radius: 9px;
}
.rb-btn-add-criterion:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(201,168,44,.2);
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