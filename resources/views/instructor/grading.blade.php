<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CapsTRACK â€” Expert Portal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/expert.css') }}" />
</head>
<body>

@include('instructor.partials.sidebar', ['currentPage' => 'grading'])

<div class="main">
  <div class="topbar">
    <div class="page-title">Team Grading</div>
    <div class="topbar-actions">
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </div>

  <div class="content">

  @if(!$isPanel)
    <div style="text-align:center;padding:64px 24px">
      <div style="font-size:48px;margin-bottom:16px;opacity:0.3">
        <svg viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
      </div>
      <div style="font-size:18px;font-weight:600;color:var(--text2);margin-bottom:8px">Grading Not Available</div>
      <div style="font-size:13px;color:var(--text3);max-width:400px;margin:0 auto">You are not assigned as a panelist. Only panel members can access the grading interface.</div>
    </div>
  @else

    <div class="team-selector-wrap">
      <div class="team-selector-avatar" id="teamAvatar" style="background:linear-gradient(135deg,#1d4ed8,#7c3aed)">{{ optional($teams->first())->name ? substr($teams->first()->name, 0, 2) : '?' }}</div>
      <div style="flex:1">
        <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:6px">Grading For</div>
        <select class="input" style="max-width:360px" id="teamSelect" onchange="switchTeam(this)">
          @foreach($teams as $team)
          <option value="{{ $team->id }}|linear-gradient(135deg,#1d4ed8,#7c3aed)|{{ $team->name }}|{{ $team->project_title ?? 'No title' }}|{{ $team->section }}">{{ $team->name }} — {{ $team->project_title ?? 'No title' }}</option>
          @endforeach
        </select>
      </div>
      <div style="text-align:right">
        <div style="font-size:11px;color:var(--text3);margin-bottom:4px">Rubric Type</div>
        <span class="badge badge-gold">{{ optional($rubrics->first())->name ?? 'Pre-Proposal Defense' }}</span>
      </div>
      <div style="text-align:right">
        <div style="font-size:11px;color:var(--text3);margin-bottom:4px">Your Role</div>
        <span class="role-pill role-panel">Panel</span>
      </div>
    </div>

    <div class="tally-bar">
      <div>
        <div class="tally-total" id="tallyTotal">0</div>
        <div class="tally-label">/ 100 pts</div>
      </div>
      <div style="width:1px;height:40px;background:var(--border)"></div>
      <div class="tally-breakdown">
        <div class="tally-part" style="cursor:pointer" onclick="goToPage(1)">
          <div class="tally-part-n" id="sec1Score" style="color:var(--blue)">0<span style="font-size:10px;color:var(--text3)">/20</span></div>
          <div class="tally-part-l">Oral / Presentation</div>
        </div>
        <div style="width:1px;height:30px;background:var(--border);align-self:center"></div>
        <div class="tally-part" style="cursor:pointer" onclick="goToPage(2)">
          <div class="tally-part-n" id="sec2Score" style="color:var(--purple)">0<span style="font-size:10px;color:var(--text3)">/30</span></div>
          <div class="tally-part-l">Topic Quality & Merit</div>
        </div>
        <div style="width:1px;height:30px;background:var(--border);align-self:center"></div>
        <div class="tally-part" style="cursor:pointer" onclick="goToPage(3)">
          <div class="tally-part-n" id="sec3Score" style="color:var(--green)">0<span style="font-size:10px;color:var(--text3)">/50</span></div>
          <div class="tally-part-l">Proposal Content & Clarity</div>
        </div>
      </div>
      <div class="tally-progress-wrap" style="max-width:220px">
        <div class="tally-progress-track">
          <div class="tally-progress-fill" id="tallyFill" style="width:0%"></div>
        </div>
        <div class="tally-progress-labels">
          <span>0</span>
          <span id="tallyPct">0%</span>
          <span>100</span>
        </div>
      </div>
    </div>

    <div class="rubric-pages-container">
      
      <div class="rubric-page active" id="rubric-page-1">
        <div class="rubric-section">
          <div class="rubric-section-header">
            <div class="rubric-section-title">
              <span style="width:28px;height:28px;border-radius:8px;background:var(--blue-bg);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="var(--blue)" stroke-width="2.5"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
              </span>
              Oral Examination / Presentation
            </div>
            <div style="display:flex;align-items:center;gap:12px">
              <span class="rubric-section-max">Max: 20 pts</span>
              <div class="rubric-section-score" id="sec1Display">0</div>
            </div>
          </div>

          <div class="criterion">
            <div class="criterion-desc">Team members' appearance â€” readiness and presentability during the defense.</div>
            <div class="score-options">
              <label class="score-option">
                <input type="radio" name="c1" value="5" onchange="updateScore()"/>
                <div class="score-option-label">
                  <span class="score-val">5</span>
                  <span class="score-desc">All members ready and presentable</span>
                </div>
              </label>
              <label class="score-option">
                <input type="radio" name="c1" value="3.75" onchange="updateScore()"/>
                <div class="score-option-label">
                  <span class="score-val">3.75</span>
                  <span class="score-desc">All ready but not all presentable</span>
                </div>
              </label>
              <label class="score-option">
                <input type="radio" name="c1" value="2.5" onchange="updateScore()"/>
                <div class="score-option-label">
                  <span class="score-val">2.5</span>
                  <span class="score-desc">Only 50% ready and presentable</span>
                </div>
              </label>
              <label class="score-option">
                <input type="radio" name="c1" value="1.25" onchange="updateScore()"/>
                <div class="score-option-label">
                  <span class="score-val">1.25</span>
                  <span class="score-desc">Less than 50% ready and presentable</span>
                </div>
              </label>
            </div>
          </div>

          <div class="criterion">
            <div class="criterion-desc">Use of presentation slides â€” intelligently and strategically utilized.</div>
            <div class="score-options">
              <label class="score-option">
                <input type="radio" name="c2" value="5" onchange="updateScore()"/>
                <div class="score-option-label">
                  <span class="score-val">5</span>
                  <span class="score-desc">All slides intelligently used</span>
                </div>
              </label>
              <label class="score-option">
                <input type="radio" name="c2" value="3.75" onchange="updateScore()"/>
                <div class="score-option-label">
                  <span class="score-val">3.75</span>
                  <span class="score-desc">At least 75% of slides well-used</span>
                </div>
              </label>
              <label class="score-option">
                <input type="radio" name="c2" value="2.5" onchange="updateScore()"/>
                <div class="score-option-label">
                  <span class="score-val">2.5</span>
                  <span class="score-desc">Only 50% of slides well-used</span>
                </div>
              </label>
              <label class="score-option">
                <input type="radio" name="c2" value="1.25" onchange="updateScore()"/>
                <div class="score-option-label">
                  <span class="score-val">1.25</span>
                  <span class="score-desc">Less than 50% of slides well-used</span>
                </div>
              </label>
            </div>
          </div>

          <div class="criterion">
            <div class="criterion-desc">Delivery â€” clarity, confidence, and articulation; command of language.</div>
            <div class="score-options">
              <label class="score-option">
                <input type="radio" name="c3" value="5" onchange="updateScore()"/>
                <div class="score-option-label">
                  <span class="score-val">5</span>
                  <span class="score-desc">Clear, confident, articulate; excellent command</span>
                </div>
              </label>
              <label class="score-option">
                <input type="radio" name="c3" value="3.75" onchange="updateScore()"/>
                <div class="score-option-label">
                  <span class="score-val">3.75</span>
                  <span class="score-desc">Clear with minor lapses</span>
                </div>
              </label>
              <label class="score-option">
                <input type="radio" name="c3" value="2.5" onchange="updateScore()"/>
                <div class="score-option-label">
                  <span class="score-val">2.5</span>
                  <span class="score-desc">Understandable but lacks confidence</span>
                </div>
              </label>
              <label class="score-option">
                <input type="radio" name="c3" value="1.25" onchange="updateScore()"/>
                <div class="score-option-label">
                  <span class="score-val">1.25</span>
                  <span class="score-desc">Unclear and difficult to follow</span>
                </div>
              </label>
            </div>
          </div>

          <div class="criterion">
            <div class="criterion-desc">Response to panel questions and queries â€” confidence and thoroughness of answers.</div>
            <div class="score-options">
              <label class="score-option">
                <input type="radio" name="c4" value="5" onchange="updateScore()"/>
                <div class="score-option-label">
                  <span class="score-val">5</span>
                  <span class="score-desc">All questions confidently answered</span>
                </div>
              </label>
              <label class="score-option">
                <input type="radio" name="c4" value="3.75" onchange="updateScore()"/>
                <div class="score-option-label">
                  <span class="score-val">3.75</span>
                  <span class="score-desc">At least 75% satisfactorily answered</span>
                </div>
              </label>
              <label class="score-option">
                <input type="radio" name="c4" value="2.5" onchange="updateScore()"/>
                <div class="score-option-label">
                  <span class="score-val">2.5</span>
                  <span class="score-desc">Only 50% satisfactorily answered</span>
                </div>
              </label>
              <label class="score-option">
                <input type="radio" name="c4" value="1.25" onchange="updateScore()"/>
                <div class="score-option-label">
                  <span class="score-val">1.25</span>
                  <span class="score-desc">Less than 50% satisfactorily answered</span>
                </div>
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="rubric-page" id="rubric-page-2">
        <div class="rubric-section">
          <div class="rubric-section-header">
            <div class="rubric-section-title">
              <span style="width:28px;height:28px;border-radius:8px;background:var(--purple-bg);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="var(--purple)" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
              </span>
              Topic Quality and Proposal Merit
            </div>
            <div style="display:flex;align-items:center;gap:12px">
              <span class="rubric-section-max">Max: 30 pts</span>
              <div class="rubric-section-score" id="sec2Display">0</div>
            </div>
          </div>

          <div class="criterion">
            <div class="criterion-desc">Project feasibility â€” achievable within time, skills, and resources; risks well-considered.</div>
            <div class="score-options">
              <label class="score-option">
                <input type="radio" name="c5" value="6" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">6</span><span class="score-desc">Fully achievable; risks well-considered</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c5" value="4" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">4</span><span class="score-desc">Achievable; risks considered</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c5" value="2.5" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">2.5</span><span class="score-desc">Partially achievable; some risks noted</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c5" value="1.25" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">1.25</span><span class="score-desc">Feasibility concerns; risks unclear</span></div>
              </label>
            </div>
          </div>

          <div class="criterion">
            <div class="criterion-desc">Topic engagement â€” highly engaging with strong academic and practical value.</div>
            <div class="score-options">
              <label class="score-option">
                <input type="radio" name="c6" value="6" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">6</span><span class="score-desc">Highly engaging; strong academic & practical value</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c6" value="4" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">4</span><span class="score-desc">Engaging with good value</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c6" value="2.5" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">2.5</span><span class="score-desc">Somewhat engaging; moderate value</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c6" value="1.25" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">1.25</span><span class="score-desc">Limited engagement or value</span></div>
              </label>
            </div>
          </div>

          <div class="criterion">
            <div class="criterion-desc">Originality â€” demonstrates strong originality or innovative approach.</div>
            <div class="score-options">
              <label class="score-option">
                <input type="radio" name="c7" value="6" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">6</span><span class="score-desc">Highly original and innovative</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c7" value="4" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">4</span><span class="score-desc">Strong originality; mostly innovative</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c7" value="2.5" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">2.5</span><span class="score-desc">Some originality; incremental innovation</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c7" value="1.25" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">1.25</span><span class="score-desc">Minimal originality; largely derivative</span></div>
              </label>
            </div>
          </div>

          <div class="criterion">
            <div class="criterion-desc">Ethical issues and data privacy â€” clearly identified and properly addressed.</div>
            <div class="score-options">
              <label class="score-option">
                <input type="radio" name="c8" value="6" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">6</span><span class="score-desc">Ethics & privacy clearly identified and addressed</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c8" value="4" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">4</span><span class="score-desc">Properly addressed with minor gaps</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c8" value="2.5" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">2.5</span><span class="score-desc">Partially addressed</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c8" value="1.25" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">1.25</span><span class="score-desc">Largely unaddressed</span></div>
              </label>
            </div>
          </div>

          <div class="criterion">
            <div class="criterion-desc">Alignment â€” highly aligned with research agenda, SDGs, and stakeholder needs.</div>
            <div class="score-options">
              <label class="score-option">
                <input type="radio" name="c9" value="6" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">6</span><span class="score-desc">Highly aligned with agenda, SDGs & stakeholders</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c9" value="4" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">4</span><span class="score-desc">Well-aligned with most criteria</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c9" value="2.5" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">2.5</span><span class="score-desc">Partially aligned</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c9" value="1.25" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">1.25</span><span class="score-desc">Weakly aligned</span></div>
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="rubric-page" id="rubric-page-3">
        <div class="rubric-section">
          <div class="rubric-section-header">
            <div class="rubric-section-title">
              <span style="width:28px;height:28px;border-radius:8px;background:var(--green-bg);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="var(--green)" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              </span>
              Proposal Content and Clarity
            </div>
            <div style="display:flex;align-items:center;gap:12px">
              <span class="rubric-section-max">Max: 50 pts</span>
              <div class="rubric-section-score" id="sec3Display">0</div>
            </div>
          </div>

          <div class="criterion">
            <div class="criterion-desc">Problem statement â€” clear, specific, well-justified, and evidence-based.</div>
            <div class="score-options">
              <label class="score-option">
                <input type="radio" name="c10" value="20" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">20</span><span class="score-desc">Clear, specific, well-justified & evidence-based</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c10" value="12.5" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">12.5</span><span class="score-desc">Clear and evidence-based with minor gaps</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c10" value="8.5" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">8.5</span><span class="score-desc">Present but lacks justification</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c10" value="4.25" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">4.25</span><span class="score-desc">Vague or weakly supported</span></div>
              </label>
            </div>
          </div>

          <div class="criterion">
            <div class="criterion-desc">Proposed solution â€” directly addresses the problem; scope and limitations well-defined.</div>
            <div class="score-options">
              <label class="score-option">
                <input type="radio" name="c11" value="20" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">20</span><span class="score-desc">Directly addresses problem; scope & limitations clear</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c11" value="12.5" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">12.5</span><span class="score-desc">Addresses problem; scope mostly defined</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c11" value="8.5" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">8.5</span><span class="score-desc">Partial alignment; scope unclear</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c11" value="4.25" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">4.25</span><span class="score-desc">Weak alignment; scope undefined</span></div>
              </label>
            </div>
          </div>

          <div class="criterion">
            <div class="criterion-desc">Software development methodology (SDM) â€” appropriate and clearly justified.</div>
            <div class="score-options">
              <label class="score-option">
                <input type="radio" name="c12" value="10" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">10</span><span class="score-desc">Appropriate SDM; clearly justified</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c12" value="5" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">5</span><span class="score-desc">Appropriate but justification is basic</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c12" value="3" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">3</span><span class="score-desc">Partially appropriate; weak justification</span></div>
              </label>
              <label class="score-option">
                <input type="radio" name="c12" value="8.5" onchange="updateScore()"/>
                <div class="score-option-label"><span class="score-val">8.5</span><span class="score-desc">Unclear or inappropriate SDM</span></div>
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="rubric-page" id="rubric-page-4">
        <div class="card" style="margin-bottom:14px">
          <div class="card-header"><span class="card-title">Verdict</span></div>
          <div class="verdict-options">
            <label class="verdict-option verdict-approved">
              <input type="radio" name="verdict" value="approved"/>
              <div class="verdict-option-label">
                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                Approved
              </div>
            </label>
            <label class="verdict-option verdict-revision">
              <input type="radio" name="verdict" value="revision"/>
              <div class="verdict-option-label">
                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Approved with Revision
              </div>
            </label>
            <label class="verdict-option verdict-rejected">
              <input type="radio" name="verdict" value="rejected"/>
              <div class="verdict-option-label">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                Disapproved / Rejected
              </div>
            </label>
          </div>

          <div class="card-header" style="margin-top:8px"><span class="card-title">Remarks</span></div>
          <textarea class="remarks-field" placeholder="Write your remarks, suggestions, or feedback for the teamâ€¦"></textarea>
        </div>
      </div>
      
    </div><div class="grading-footer">
      <div class="footer-left">
        <button class="btn btn-ghost" id="btnPrev" onclick="prevPage()" disabled>
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" style="transform: rotate(180deg); margin-right: 4px;"><polyline points="12 5 19 12 12 19"/></svg>
          Back
        </button>
        <span class="page-indicator" id="pageIndicator">Page 1 of 4</span>
      </div>
      <div style="display: flex; gap: 10px;">
        <button class="btn btn-ghost" onclick="saveDraft()">Save Draft</button>
        <button class="btn btn-primary" id="btnNext" onclick="nextPage()">
          Next Section
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-left: 4px;"><polyline points="12 5 19 12 12 19"/></svg>
        </button>
      </div>
    </div>
  @endif

  </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     EXPERT PROFILE SIDE PANEL
     Sits flush against the right edge of the
     sidebar (left: 250px). No dark overlay.
     â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<div class="ep-overlay" id="ep-overlay" onclick="handleEpOverlay(event)">
  <div class="ep-modal" id="ep-modal">

    <!-- Sticky Header -->
    <div class="ep-header">
      <div class="ep-avatar">CS</div>
      <div style="flex:1;min-width:0">
        <div class="ep-header-name">Dr. Neil A. Inocencio</div>
        <div class="ep-header-sub">Capstone Expert · Active</div>
      </div>
      <button class="ep-close" onclick="closeExpertProfile()">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <!-- Scrollable Body -->
    <div class="ep-body">

      <!-- Full Name â€” readonly -->
      <div class="ep-field">
        <div class="ep-label">Full Name</div>
        <div class="ep-value readonly">
          <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Dr. Neil A. Inocencio
        </div>
      </div>

      <!-- Email â€” readonly -->
      <div class="ep-field">
        <div class="ep-label">Institutional Email</div>
        <div class="ep-value readonly">
          <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          nainocencio@cspc.edu.ph
        </div>
      </div>

      <div class="ep-divider"></div>

        <!-- Roles â€” readonly, assigned by admin -->
        <div class="ep-field">
          <div class="ep-label">Assigned Roles</div>
          <div class="ep-tags-wrap">
            <span class="role-pill role-adviser">Adviser</span>
            <span class="role-pill role-panel">Panel</span>
            <span class="role-pill role-consultant">Consultant</span>
            <span class="role-pill role-grammarian">Grammarian</span>
          </div>
        <div style="font-size:11px;color:var(--text3);margin-top:8px;display:flex;align-items:center;gap:4px">
          <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Managed by the system administrator
        </div>
      </div>

      <div class="ep-divider"></div>

      <!-- Max Teams â€” readonly -->
      <div class="ep-field">
        <div class="ep-label">Team Capacity</div>
        <div class="ep-value readonly">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Max 4 teams · Currently advising 3
        </div>
      </div>

      <div class="ep-divider"></div>

      <!-- Expertise Tags â€” editable -->
      <div class="ep-field">
        <div class="ep-label">Areas of Expertise</div>
        <div class="ep-tags-wrap" id="ep-tags-wrap"></div>
        <div class="ep-tag-input-row">
          <input
            class="ep-tag-input"
            id="ep-tag-input"
            type="text"
            placeholder="Add expertiseâ€¦"
            onkeydown="handleTagKey(event)"
          />
          <button class="ep-tag-add-btn" onclick="addTag()">+ Add</button>
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:7px">
          Press Enter or click Add. Click Ã— to remove.
        </div>
      </div>

    </div>

    <!-- Sticky Footer -->
    <div class="ep-footer">
      <button class="btn content btn-ghost" style="color: #FFFF00" onclick="closeExpertProfile()">Close</button>
      <button class="btn btn-primary" onclick="saveExpertProfile()">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Save
      </button>
    </div>

  </div>
</div>

<script>
const sec1Fields = ['c1','c2','c3','c4'];
const sec2Fields = ['c5','c6','c7','c8','c9'];
const sec3Fields = ['c10','c11','c12'];

// â”€â”€ PAGINATION SYSTEM CONFIGURATION â”€â”€
let activePage = 1;
const totalPages = 4;

function goToPage(pageNum) {
  if (pageNum < 1 || pageNum > totalPages) return;
  activePage = pageNum;
  
  // Itatago ang lahat ng pahina at ang pinili lang ang bibigyan ng class na `.active`
  document.querySelectorAll('.rubric-page').forEach((page, i) => {
    page.classList.toggle('active', (i + 1) === activePage);
  });
  
  // I-disable ang Back button kung nasa Page 1 na
  document.getElementById('btnPrev').disabled = (activePage === 1);
  
  // Palitan ang disenyo o label ng Next button kung huling pahina na
  const nextBtn = document.getElementById('btnNext');
  if (activePage === totalPages) {
    nextBtn.innerHTML = `Submit Grade <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-left:4px;"><polyline points="20 6 9 17 4 12"/></svg>`;
    nextBtn.className = 'btn btn-success'; // Kung may success button variant ka
  } else {
    nextBtn.innerHTML = `Next Section <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-left:4px;"><polyline points="12 5 19 12 12 19"/></svg>`;
    nextBtn.className = 'btn btn-primary';
  }
  
  // I-update ang Page Indicator text
  document.getElementById('pageIndicator').textContent = `Page ${activePage} of ${totalPages}`;
  
  // Awtomatikong mag-scroll pabalik sa itaas para malinis tingnan ang bagong page
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function nextPage() {
  if (activePage === totalPages) {
    submitGrade();
  } else {
    goToPage(activePage + 1);
  }
}

function prevPage() {
  goToPage(activePage - 1);
}

function getVal(name) {
  const el = document.querySelector(`input[name="${name}"]:checked`);
  return el ? parseFloat(el.value) : 0;
}

function updateScore() {
  const s1 = sec1Fields.reduce((a, n) => a + getVal(n), 0);
  const s2 = sec2Fields.reduce((a, n) => a + getVal(n), 0);
  const s3 = sec3Fields.reduce((a, n) => a + getVal(n), 0);
  const total = s1 + s2 + s3;

  document.getElementById('sec1Score').innerHTML = `${fmt(s1)}<span style="font-size:10px;color:var(--text3)">/20</span>`;
  document.getElementById('sec2Score').innerHTML = `${fmt(s2)}<span style="font-size:10px;color:var(--text3)">/30</span>`;
  document.getElementById('sec3Score').innerHTML = `${fmt(s3)}<span style="font-size:10px;color:var(--text3)">/50</span>`;
  document.getElementById('sec1Display').textContent = fmt(s1);
  document.getElementById('sec2Display').textContent = fmt(s2);
  document.getElementById('sec3Display').textContent = fmt(s3);

  const t = document.getElementById('tallyTotal');
  t.textContent = fmt(total);
  t.style.color = total >= 75 ? 'var(--green)' : total >= 50 ? 'var(--accent3)' : 'var(--red)';

  const pct = Math.round((total / 100) * 100);
  document.getElementById('tallyFill').style.width = pct + '%';
  document.getElementById('tallyFill').style.background = total >= 75 ? 'var(--green)' : total >= 50 ? 'var(--accent)' : 'var(--red)';
  document.getElementById('tallyPct').textContent = pct + '%';
}

function fmt(n) {
  return n % 1 === 0 ? n : n.toFixed(2).replace(/\.?0+$/, '');
}

function switchTeam(sel) {
  const parts = sel.value.split('|');
  document.getElementById('teamAvatar').style.background = parts[1];
  document.getElementById('teamAvatar').textContent = parts[0];
  // Reset all radios
  document.querySelectorAll('input[type="radio"]').forEach(r => r.checked = false);
  updateScore();
}

  // Profile Modal Logic
  const EP_OVERLAY = document.getElementById('ep-overlay');
const EP_TRIGGER = document.getElementById('ep-trigger');
const EP_TAGS_EL = document.getElementById('ep-tags-wrap');
const EP_INPUT   = document.getElementById('ep-tag-input');

let epTags = [
  'Web & Mobile Development',
  'Machine Learning & AI',
  'IoT & Embedded Systems',
  'Cybersecurity',
];

function renderTags(){
  EP_TAGS_EL.innerHTML = '';
  epTags.forEach((tag, i) => {
    const span = document.createElement('span');
    span.className = 'ep-tag';
    span.innerHTML = `${tag}
      <button class="ep-tag-remove" onclick="removeTag(${i})" title="Remove">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>`;
    EP_TAGS_EL.appendChild(span);
  });
}

function addTag(){
  const val = EP_INPUT.value.trim();
  if(!val) return;
  if(epTags.map(t=>t.toLowerCase()).includes(val.toLowerCase())){ EP_INPUT.select(); return; }
  epTags.push(val);
  EP_INPUT.value = '';
  renderTags();
  EP_INPUT.focus();
}

function removeTag(i){ epTags.splice(i,1); renderTags(); }
function handleTagKey(e){ if(e.key==='Enter'){ e.preventDefault(); addTag(); } }

function openExpertProfile(){
  EP_OVERLAY.classList.add('open');
  EP_TRIGGER.classList.add('ep-open');
  renderTags();
}

function closeExpertProfile(){
  EP_OVERLAY.classList.remove('open');
  EP_TRIGGER.classList.remove('ep-open');
}

// Close when clicking outside the modal panel (on main content)
function handleEpOverlay(e){
  if(e.target === EP_OVERLAY) closeExpertProfile();
}

function saveExpertProfile(){
  closeExpertProfile();
  // hook up to your actual save API here
}

document.addEventListener('keydown', e => {
  if(e.key === 'Escape') closeExpertProfile();
});

</script>
</body>
</html>
