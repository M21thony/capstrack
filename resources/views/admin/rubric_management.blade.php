<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Rubric Management - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
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
  <div class="modal-box modal-box-lg">
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
    <div id="modal-step-1">
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
    <div id="modal-step-2" style="display:none">
      <div class="rb-criteria-toolbar">
        <div>
          <div style="font-size:13px;font-weight:600;color:var(--text)">Scoring Criteria</div>
          <div style="font-size:11px;color:var(--text3);margin-top:1px">Each criterion has 4 performance levels. Total must equal 100 pts.</div>
        </div>
        <div class="rb-pts-badge" id="total-pts-label">0 / 100 pts</div>
      </div>

      <div id="criteria-list" style="display:flex;flex-direction:column;gap:10px;max-height:340px;overflow-y:auto;padding-right:4px;margin-bottom:12px"></div>

      <button class="rb-btn-add-criterion" onclick="addCriterion()">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Criterion
      </button>

      <div style="display:flex;gap:8px;margin-top:20px;justify-content:space-between">
        <button class="btn btn-ghost" style="color:var(--text2);border-color:var(--border)" onclick="goToStep1()">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
          Back
        </button>
        <button class="btn btn-primary" onclick="saveRubric()">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          <span id="form-save-label">Save Rubric</span>
        </button>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

@include('admin._sidebar')
<div class="main">
  <div class="topbar">
    <div class="page-title">Grading Rubrics</div>
    <div class="topbar-actions">
      @include('admin.partials.notification-dropdown')
    </div>
  </div>

  <div class="content">

    <!-- Page Header: Stats + New Button -->
    <div class="rb-page-header">
      <div class="rb-stat-strip">
        <div class="rb-stat-item">
          <div class="rb-stat-n" id="stat-total" style="color:var(--accent3)">4</div>
          <div class="rb-stat-l">Total Rubrics</div>
        </div>
        <div class="rb-stat-divider"></div>
        <div class="rb-stat-item">
          <div class="rb-stat-n" id="stat-criteria" style="color:var(--blue)">13</div>
          <div class="rb-stat-l">Total Criteria</div>
        </div>
        <div class="rb-stat-divider"></div>
        <div class="rb-stat-item">
          <div class="rb-stat-n" style="color:var(--purple)" id="stat-types">4</div>
          <div class="rb-stat-l">Rubric Types</div>
        </div>
      </div>
      <button class="btn btn-primary" onclick="openNewRubricModal()">
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
            All <span class="rb-tab-count" id="tc-all">4</span>
          </button>
          <button class="rb-tab-btn" onclick="filterType('pre_proposal',this)" data-type="pre_proposal">
            Pre-Proposal <span class="rb-tab-count" id="tc-pre_proposal">1</span>
          </button>
          <button class="rb-tab-btn" onclick="filterType('title_defense',this)" data-type="title_defense">
            Title Defense <span class="rb-tab-count" id="tc-title_defense">1</span>
          </button>
          <button class="rb-tab-btn" onclick="filterType('pre_oral_defense',this)" data-type="pre_oral_defense">
            Pre Oral <span class="rb-tab-count" id="tc-pre_oral_defense">1</span>
          </button>
          <button class="rb-tab-btn" onclick="filterType('final_oral_defense',this)" data-type="final_oral_defense">
            Final Oral <span class="rb-tab-count" id="tc-final_oral_defense">1</span>
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
        <div style="font-size:12px;color:var(--text3)" id="pg-info">Showing 1-4 of 4</div>
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

let rubrics = [
  {id:1,name:"Pre-Proposal Defense Rubric",type:"pre_proposal",description:"Used for evaluating pre-proposal oral presentations.",criteria:[
    {id:1,name:"Oral Examination / Presentation",maxScore:20,levels:[{score:5,desc:"All members ready and presentable"},{score:3.75,desc:"All ready but not all presentable"},{score:2.5,desc:"Only 50% ready and presentable"},{score:1.25,desc:"Less than 50% ready and presentable"}]},
    {id:2,name:"Topic Quality and Proposal Merit",maxScore:30,levels:[{score:7.5,desc:"Highly feasible and original"},{score:5.625,desc:"Feasible with minor concerns"},{score:3.75,desc:"Feasible with major concerns"},{score:1.875,desc:"Not feasible"}]},
    {id:3,name:"Proposal Content and Clarity",maxScore:50,levels:[{score:12.5,desc:"All content clear and complete"},{score:9.375,desc:"Mostly clear, minor gaps"},{score:6.25,desc:"Some clarity issues"},{score:3.125,desc:"Content unclear or incomplete"}]},
  ]},
  {id:2,name:"Title Defense Rubric",type:"title_defense",description:"Rubric for assessing title defense presentations.",criteria:[
    {id:1,name:"Research Background & Rationale",maxScore:25,levels:[{score:6.25,desc:"Exceptional background provided"},{score:4.69,desc:"Adequate background"},{score:3.13,desc:"Background needs improvement"},{score:1.56,desc:"Background insufficient"}]},
    {id:2,name:"Problem Statement & Objectives",maxScore:25,levels:[{score:6.25,desc:"Clear and well-defined"},{score:4.69,desc:"Mostly clear"},{score:3.13,desc:"Somewhat vague"},{score:1.56,desc:"Unclear or missing"}]},
    {id:3,name:"Methodology Overview",maxScore:25,levels:[{score:6.25,desc:"Comprehensive methodology"},{score:4.69,desc:"Adequate methodology"},{score:3.13,desc:"Methodology needs work"},{score:1.56,desc:"Methodology insufficient"}]},
    {id:4,name:"Oral Communication",maxScore:25,levels:[{score:6.25,desc:"Excellent delivery and clarity"},{score:4.69,desc:"Good delivery, minor lapses"},{score:3.13,desc:"Fair delivery"},{score:1.56,desc:"Poor delivery"}]},
  ]},
  {id:3,name:"Pre-Oral Defense Rubric",type:"pre_oral_defense",description:"Rubric for pre-oral capstone defense evaluation.",criteria:[
    {id:1,name:"Chapters 1-3 Completeness",maxScore:40,levels:[{score:10,desc:"All chapters complete and polished"},{score:7.5,desc:"Minor revisions needed"},{score:5,desc:"Major revisions needed"},{score:2.5,desc:"Incomplete"}]},
    {id:2,name:"Methodology Soundness",maxScore:30,levels:[{score:7.5,desc:"Methodology is sound and appropriate"},{score:5.625,desc:"Methodology mostly sound"},{score:3.75,desc:"Methodology has significant gaps"},{score:1.875,desc:"Methodology is inadequate"}]},
    {id:3,name:"Oral Defense",maxScore:30,levels:[{score:7.5,desc:"Confident and thorough answers"},{score:5.625,desc:"Mostly confident answers"},{score:3.75,desc:"Struggled with some questions"},{score:1.875,desc:"Unable to answer adequately"}]},
  ]},
  {id:4,name:"Final Oral Defense Rubric",type:"final_oral_defense",description:"Comprehensive rubric for the final capstone oral defense.",criteria:[
    {id:1,name:"Completeness of the Study",maxScore:30,levels:[{score:7.5,desc:"All chapters fully complete"},{score:5.625,desc:"Mostly complete"},{score:3.75,desc:"Significant portions missing"},{score:1.875,desc:"Largely incomplete"}]},
    {id:2,name:"Technical Implementation",maxScore:40,levels:[{score:10,desc:"System fully functional and tested"},{score:7.5,desc:"Mostly functional, minor issues"},{score:5,desc:"Partially functional"},{score:2.5,desc:"System not functional"}]},
    {id:3,name:"Oral Defense Quality",maxScore:30,levels:[{score:7.5,desc:"Excellent answers and delivery"},{score:5.625,desc:"Good answers, minor lapses"},{score:3.75,desc:"Fair answers"},{score:1.875,desc:"Poor answers"}]},
  ]},
];
let nextId = 5;
let nextCritId = 100;
let activeFilter = "all";
let editingId = null;
let draftCriteria = [];

// -- CRITERION BUILDER ---------------------------------------------------------
function addCriterion(preload=null){
  const crit = preload || {id:nextCritId++,name:"",maxScore:10,levels:[{score:"",desc:""},{score:"",desc:""},{score:"",desc:""},{score:"",desc:""}]};
  if(!preload) draftCriteria.push(crit);
  renderCriteriaBuilder();
}

function removeCriterion(idx){
  draftCriteria.splice(idx,1);
  renderCriteriaBuilder();
}

function renderCriteriaBuilder(){
  const el = document.getElementById("criteria-list");
  if(!draftCriteria.length){
    el.innerHTML=`<div class="rb-empty-criteria">
      <div class="rb-empty-criteria-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="var(--text3)" stroke-width="1.8"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
      <div style="font-size:13px;font-weight:500;color:var(--text2)">No criteria yet</div>
      <div style="font-size:12px;color:var(--text3);margin-top:3px">Click "Add Criterion" to get started</div>
    </div>`;
    updateTotalPts();
    return;
  }
  el.innerHTML = draftCriteria.map((cr,i)=>`
    <div class="rb-criterion-card" id="crc-${i}">
      <div class="rb-criterion-header">
        <div class="rb-crit-num">${i+1}</div>
        <input class="input rb-crit-name-input" value="${cr.name}" placeholder="Criterion name (e.g. Oral Presentation)&hellip;"
          oninput="draftCriteria[${i}].name=this.value"/>
        <div class="rb-maxpts-wrap">
          <span class="rb-maxpts-label">Max pts</span>
          <input type="number" class="input rb-maxpts-input" value="${cr.maxScore}" min="1" max="100"
            oninput="draftCriteria[${i}].maxScore=+this.value;updateTotalPts()"/>
        </div>
        <button onclick="removeCriterion(${i})" class="rb-remove-btn" title="Remove criterion">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="rb-levels-grid">
        ${cr.levels.map((lv,li)=>`
          <div class="rb-level-card">
            <div class="rb-level-badge" style="color:${LEVEL_COLORS[li]};background:${LEVEL_COLORS[li]}1a">${LEVEL_LABELS[li]}</div>
            <input type="number" class="input rb-score-tiny" value="${lv.score}" placeholder="pts"
              oninput="draftCriteria[${i}].levels[${li}].score=+this.value" min="0" step="0.25"/>
            <textarea class="input rb-desc-area" placeholder="Describe this performance level&hellip;"
              oninput="draftCriteria[${i}].levels[${li}].desc=this.value">${lv.desc}</textarea>
          </div>
        `).join("")}
      </div>
    </div>
  `).join("");
  updateTotalPts();
}

function updateTotalPts(){
  const total = draftCriteria.reduce((s,c)=>s+(+c.maxScore||0),0);
  const lbl = document.getElementById("total-pts-label");
  if(lbl){
    lbl.textContent = `${total} / 100 pts`;
    lbl.className = "rb-pts-badge"+(total===100?" rb-pts-ok":total>100?" rb-pts-over":"");
  }
}

// -- MODAL ---------------------------------------------------------------------
function openNewRubricModal(){
  editingId=null;
  document.getElementById("rb-name").value="";
  document.getElementById("rb-type").value="";
  document.getElementById("rb-desc").value="";
  document.querySelectorAll(".rb-type-opt").forEach(o=>o.classList.remove("selected"));
  document.querySelectorAll("input[name=rb-type-radio]").forEach(r=>r.checked=false);
  document.getElementById("modal-form-title").textContent="New Rubric";
  document.getElementById("modal-form-sub").textContent="Fill in the details below to create a grading rubric.";
  document.getElementById("form-save-label").textContent="Save Rubric";
  draftCriteria=[];
  goToStep1();
  document.getElementById("rubric-modal").classList.add("show");
}

function closeRubricModal(){
  document.getElementById("rubric-modal").classList.remove("show");
}

document.getElementById("rubric-modal").addEventListener("click",e=>{
  if(e.target===e.currentTarget) closeRubricModal();
});
document.getElementById("del-modal").addEventListener("click",e=>{
  if(e.target===e.currentTarget) closeDelModal();
});

function goToStep1(){
  document.getElementById("modal-step-1").style.display="";
  document.getElementById("modal-step-2").style.display="none";
  document.getElementById("step-ind-1").className="modal-step active";
  document.getElementById("step-ind-2").className="modal-step";
}

function goToStep2(){
  const name = document.getElementById("rb-name").value.trim();
  const type = document.getElementById("rb-type").value;
  if(!name){ document.getElementById("rb-name").focus(); toast("Enter a rubric name."); return; }
  if(!type){ toast("Select a rubric type."); return; }
  document.getElementById("modal-step-1").style.display="none";
  document.getElementById("modal-step-2").style.display="";
  document.getElementById("step-ind-1").className="modal-step done";
  document.getElementById("step-ind-2").className="modal-step active";
  renderCriteriaBuilder();
}

function selectRbType(radio){
  document.getElementById("rb-type").value=radio.value;
  document.querySelectorAll(".rb-type-opt").forEach(o=>o.classList.remove("selected"));
  radio.closest(".rb-type-opt").classList.add("selected");
}

// -- SAVE ----------------------------------------------------------------------
function saveRubric(){
  const name = document.getElementById("rb-name").value.trim();
  const type = document.getElementById("rb-type").value;
  const desc = document.getElementById("rb-desc").value.trim();
  if(!name){ toast("Enter a rubric name."); goToStep1(); return; }
  if(!type){ toast("Select a rubric type."); goToStep1(); return; }
  if(!draftCriteria.length){ toast("Add at least one criterion."); return; }
  const total = draftCriteria.reduce((s,c)=>s+(+c.maxScore||0),0);
  if(total!==100){ toast("Total must be 100 pts (currently "+total+" pts)."); return; }
  if(editingId){
    const rb = rubrics.find(r=>r.id===editingId);
    if(rb){rb.name=name;rb.type=type;rb.description=desc;rb.criteria=JSON.parse(JSON.stringify(draftCriteria));}
    toast("Rubric updated.");
  } else {
    rubrics.push({id:nextId++,name,type,description:desc,criteria:JSON.parse(JSON.stringify(draftCriteria))});
    toast("Rubric saved.");
  }
  closeRubricModal();
  currentPage=1;
  renderList();
  updateStats();
}

// -- EDIT ----------------------------------------------------------------------
function editRubric(id){
  const rb = rubrics.find(r=>r.id===id);
  if(!rb) return;
  editingId=id;
  document.getElementById("rb-name").value=rb.name;
  document.getElementById("rb-type").value=rb.type;
  document.getElementById("rb-desc").value=rb.description||"";
  document.getElementById("modal-form-title").textContent="Edit Rubric";
  document.getElementById("modal-form-sub").textContent="Update the rubric details and criteria below.";
  document.getElementById("form-save-label").textContent="Update Rubric";
  document.querySelectorAll(".rb-type-opt").forEach(o=>o.classList.remove("selected"));
  document.querySelectorAll("input[name=rb-type-radio]").forEach(r=>{
    r.checked=(r.value===rb.type);
    if(r.checked) r.closest(".rb-type-opt").classList.add("selected");
  });
  draftCriteria=JSON.parse(JSON.stringify(rb.criteria));
  goToStep1();
  document.getElementById("rubric-modal").classList.add("show");
}

// -- DELETE --------------------------------------------------------------------
let pendingDelId=null;
function confirmDelete(id){
  pendingDelId=id;
  const rb=rubrics.find(r=>r.id===id);
  document.getElementById("del-modal-sub").textContent='"'+rb?.name+'" and all its criteria will be permanently deleted.';
  document.getElementById("del-modal").classList.add("show");
  document.getElementById("del-confirm-btn").onclick=()=>{
    rubrics=rubrics.filter(r=>r.id!==pendingDelId);
    closeDelModal(); renderList(); updateStats();
    toast("Rubric deleted.");
  };
}
function closeDelModal(){ document.getElementById("del-modal").classList.remove("show"); }

// -- FILTER --------------------------------------------------------------------
function filterType(type,btn){
  activeFilter=type; currentPage=1;
  document.querySelectorAll("#type-tabs .rb-tab-btn").forEach(b=>b.classList.remove("active"));
  btn.classList.add("active");
  renderList();
}

// -- PAGINATION ----------------------------------------------------------------
function getFiltered(){
  const q = (document.getElementById("rb-search").value||"").trim().toLowerCase();
  let list = activeFilter==="all" ? rubrics : rubrics.filter(r=>r.type===activeFilter);
  if(q) list=list.filter(r=>r.name.toLowerCase().includes(q)||(r.description||"").toLowerCase().includes(q)||(TYPE_LABEL[r.type]||"").toLowerCase().includes(q));
  return list;
}

function changePage(dir){
  const filtered=getFiltered();
  const totalPages=Math.max(1,Math.ceil(filtered.length/ITEMS_PER_PAGE));
  currentPage=Math.min(Math.max(1,currentPage+dir),totalPages);
  renderList();
}

function jumpPage(p){ currentPage=p; renderList(); }

// -- RENDER LIST ---------------------------------------------------------------
function renderList(){
  const filtered=getFiltered();
  const totalPages=Math.max(1,Math.ceil(filtered.length/ITEMS_PER_PAGE));
  if(currentPage>totalPages) currentPage=totalPages;
  const start=(currentPage-1)*ITEMS_PER_PAGE;
  const paged=filtered.slice(start,start+ITEMS_PER_PAGE);

  ["all","pre_proposal","title_defense","pre_oral_defense","final_oral_defense"].forEach(t=>{
    const el=document.getElementById("tc-"+t);
    if(el) el.textContent=t==="all"?rubrics.length:rubrics.filter(r=>r.type===t).length;
  });

  const el=document.getElementById("rubric-list");
  if(!filtered.length){
    el.innerHTML=`<div class="rb-empty-state">
      <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="var(--border2)" stroke-width="1.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      <div style="font-size:14px;font-weight:500;color:var(--text2);margin-top:10px">No rubrics found</div>
      <div style="font-size:12px;color:var(--text3);margin-top:4px">Try a different filter or add a new rubric.</div>
    </div>`;
    renderPagination(0,1);
    return;
  }

  el.innerHTML=paged.map(rb=>{
    const total=rb.criteria.reduce((s,c)=>s+(+c.maxScore||0),0);
    const col=TYPE_COLOR[rb.type]||"var(--text3)";
    const bg=TYPE_BG[rb.type]||"var(--bg4)";
    return `
    <div class="card rb-rubric-card" style="padding:0;overflow:hidden">
      <div class="rb-rubric-header">
        <div style="flex:1;min-width:0">
          <div style="margin-bottom:6px"><span class="badge" style="background:${bg};color:${col};font-size:10px;font-weight:600">${TYPE_LABEL[rb.type]||rb.type}</span></div>
          <div style="font-size:15px;font-weight:600;color:var(--text);margin-bottom:3px">${rb.name}</div>
          ${rb.description?`<div style="font-size:12px;color:var(--text3)">${rb.description}</div>`:""}
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0">
          <div style="font-family:'Space Mono',monospace;font-size:22px;font-weight:700;color:${col}">${total}<span style="font-size:11px;color:var(--text3);font-family:'DM Sans',sans-serif"> pts</span></div>
          <div style="font-size:11px;color:var(--text3)">${rb.criteria.length} criteria</div>
        </div>
      </div>
      <div style="padding:0 18px 14px">
        ${rb.criteria.map((cr,i)=>`
          <div class="rb-crit-row">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
              <div style="font-size:12px;font-weight:600;color:var(--text)">${i+1}. ${cr.name}</div>
              <span style="font-family:'Space Mono',monospace;font-size:11px;color:${col};font-weight:700">${cr.maxScore} pts</span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:5px">
              ${cr.levels.map((lv,li)=>`
                <div class="rb-level-chip">
                  <div style="font-size:9px;font-weight:700;color:${LEVEL_COLORS[li]};margin-bottom:3px;text-transform:uppercase;letter-spacing:.4px">${LEVEL_LABELS[li]}</div>
                  <div class="rb-level-score">${lv.score} pts</div>
                  <div class="rb-level-desc">${lv.desc}</div>
                </div>
              `).join("")}
            </div>
          </div>
        `).join("")}
      </div>
      <div class="rb-rubric-footer">
        <button class="btn btn-ghost" style="font-size:11px;padding:5px 14px" onclick="editRubric(${rb.id})">
          <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="m18.5 2.5 3 3L12 15l-4 1 1-4z"/></svg>
          Edit
        </button>
        <button class="btn rb-btn-danger" style="font-size:11px;padding:5px 14px" onclick="confirmDelete(${rb.id})">
          <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
          Delete
        </button>
      </div>
    </div>`;
  }).join("");

  renderPagination(filtered.length,totalPages);
}

function renderPagination(total,totalPages){
  const start=(currentPage-1)*ITEMS_PER_PAGE+1;
  const end=Math.min(currentPage*ITEMS_PER_PAGE,total);
  document.getElementById("pg-info").textContent=total?`Showing ${start}–${end} of ${total}`:"No results";
  document.getElementById("pg-prev").disabled=currentPage<=1;
  document.getElementById("pg-next").disabled=currentPage>=totalPages;
  let html="";
  for(let p=1;p<=totalPages;p++){
    html+=`<button class="rb-pg-num${p===currentPage?" active":""}" onclick="jumpPage(${p})">${p}</button>`;
  }
  document.getElementById("pg-pages").innerHTML=html;
  const pgEl=document.getElementById("rb-pagination");
  pgEl.style.display=(totalPages<=1&&total>0)||total===0?"none":"flex";
}

function updateStats(){
  document.getElementById("stat-total").textContent=rubrics.length;
  document.getElementById("stat-criteria").textContent=rubrics.reduce((s,r)=>s+r.criteria.length,0);
  document.getElementById("stat-types").textContent=new Set(rubrics.map(r=>r.type)).size;
}

let _tt;
function toast(msg){
  const t=document.getElementById("toast");
  t.textContent=msg; t.classList.add("show");
  clearTimeout(_tt); _tt=setTimeout(()=>t.classList.remove("show"),2600);
}

renderList();
updateStats();
</script>
</body>
</html>