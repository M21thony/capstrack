@php
    $categories = $rubric->criteria->groupBy(fn ($c) => $c->category ?? 'General');
    $existingScores = $grade?->scores ?? [];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CapsTRACK — Expert Portal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/expert.css') }}" />
</head>
<body>

@include('instructor.partials.sidebar', ['currentPage' => 'grading'])
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Team Grading</div>
    <div class="topbar-actions">
      <a href="{{ route('instructor.grading') }}" class="btn btn-ghost" style="text-decoration:none;font-size:12px;padding:8px 14px">← Back to Teams</a>
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="sk" style="height:88px;border-radius:14px;margin-bottom:16px"></div>
    <div class="sk" style="height:70px;border-radius:14px;margin-bottom:20px"></div>
    <div class="card" style="margin-bottom:14px">
      <div class="sk" style="width:140px;height:16px;border-radius:4px;margin-bottom:14px"></div>
      <div class="sk" style="height:64px;border-radius:10px;margin-bottom:10px"></div>
      <div class="sk" style="height:64px;border-radius:10px;margin-bottom:10px"></div>
      <div class="sk" style="height:64px;border-radius:10px"></div>
    </div>
    <div class="card">
      <div class="sk" style="width:100px;height:16px;border-radius:4px;margin-bottom:14px"></div>
      <div class="sk" style="height:80px;border-radius:10px"></div>
    </div>
  </div>

  <div id="real-content" style="display:none">
  <div class="content">

    <p class="pi-wizard-error" id="formError" role="alert" hidden style="color:var(--red);font-size:13px;margin-bottom:12px"></p>

    @if($grade?->status === 'submitted')
      <div class="card" style="border-color:var(--green);background:var(--green-bg);color:var(--green);padding:12px 16px;margin-bottom:16px;font-size:13px">
        You already submitted a grade for this team. You can still update it below — saving will replace your previous submission.
      </div>
    @endif

    <div class="team-selector-wrap">
      <div class="team-selector-avatar" style="background:linear-gradient(135deg,#1d4ed8,#7c3aed)">{{ substr($defense->team?->team_name ?? '??', 0, 2) }}</div>
      <div style="flex:1">
        <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:6px">Grading For</div>
        <div style="font-size:16px;font-weight:700">{{ $defense->team?->team_name ?? 'Unknown Team' }} — {{ $defense->team?->project_title ?? 'No title' }}</div>
      </div>
      <div style="text-align:right">
        <div style="font-size:11px;color:var(--text3);margin-bottom:4px">Rubric</div>
        <span class="badge badge-gold">{{ $rubric->rubric_name }}</span>
      </div>
      <div style="text-align:right">
        <div style="font-size:11px;color:var(--text3);margin-bottom:4px">Your Role</div>
        <span class="role-pill role-panel">{{ $panelRole }}</span>
      </div>
    </div>

    <div class="tally-bar">
      <div>
        <div class="tally-total" id="tallyTotal">{{ $grade?->total_score !== null ? rtrim(rtrim(number_format((float) $grade->total_score, 2), '0'), '.') : 0 }}</div>
        <div class="tally-label">/ {{ rtrim(rtrim(number_format((float) $rubric->total_points, 2), '0'), '.') }} pts</div>
      </div>
      <div style="width:1px;height:40px;background:var(--border)"></div>
      <div class="tally-breakdown" id="categoryTally">
        @foreach($categories as $categoryName => $criteria)
          <div class="tally-part">
            <div class="tally-part-n" id="cat-total-{{ $loop->index }}" data-cat-index="{{ $loop->index }}">0<span style="font-size:10px;color:var(--text3)">/{{ rtrim(rtrim(number_format((float) ($criteria->first()->category_max_points ?? $criteria->sum('max_points')), 2), '0'), '.') }}</span></div>
            <div class="tally-part-l">{{ $categoryName }}</div>
          </div>
        @endforeach
      </div>
      <div class="tally-progress-wrap" style="max-width:220px">
        <div class="tally-progress-track">
          <div class="tally-progress-fill" id="tallyFill" style="width:0%"></div>
        </div>
        <div class="tally-progress-labels">
          <span>0</span>
          <span id="tallyPct">0%</span>
          <span>{{ rtrim(rtrim(number_format((float) $rubric->total_points, 2), '0'), '.') }}</span>
        </div>
      </div>
    </div>

    <form id="gradingForm">
      @csrf

      <div class="rubric-pages-container">
        @foreach($categories as $categoryName => $criteria)
          <div class="rubric-page active">
            <div class="rubric-section">
              <div class="rubric-section-header">
                <div class="rubric-section-title">{{ $categoryName }}</div>
                <span class="rubric-section-max">Max: {{ rtrim(rtrim(number_format((float) ($criteria->first()->category_max_points ?? $criteria->sum('max_points')), 2), '0'), '.') }} pts</span>
              </div>

              @foreach($criteria as $criterion)
                <div class="criterion" data-cat-index="{{ $loop->parent->index }}">
                  <div class="criterion-desc"><strong>{{ $criterion->criterion_name }}</strong>@if($criterion->description) — {{ $criterion->description }}@endif</div>
                  <div class="score-options">
                    @foreach($criterion->decodedScoreLevels() as $level)
                      <label class="score-option">
                        <input
                          type="radio"
                          name="scores[{{ $criterion->criterion_id }}]"
                          value="{{ $level['score'] }}"
                          onchange="updateScore()"
                          @if(array_key_exists($criterion->criterion_id, $existingScores) && abs((float) $existingScores[$criterion->criterion_id] - (float) $level['score']) < 0.001) checked @endif
                        />
                        <div class="score-option-label">
                          <span class="score-val">{{ rtrim(rtrim(number_format((float) $level['score'], 2), '0'), '.') }}</span>
                          <span class="score-desc">{{ $level['desc'] ?? ($level['label'] ?? '') }}</span>
                        </div>
                      </label>
                    @endforeach
                  </div>
                </div>
              @endforeach
            </div>
          </div>
        @endforeach

        <div class="rubric-page active">
          {{-- Remarks are this panelist's own. The verdict is not: the panel
               confers and gives the team one result, so it is recorded once
               by the chair below rather than collected from each panelist
               and tallied - there was never a rule for what a split meant. --}}
          <div class="card" style="margin-bottom:14px">
            <div class="card-header"><span class="card-title">Your Remarks</span></div>
            <textarea name="remarks" class="remarks-field" placeholder="Your comments for the team - what you want them to fix, or what worked.">{{ $grade?->remarks }}</textarea>
          </div>

          <div class="card" style="margin-bottom:14px">
            <div class="card-header">
              <span class="card-title">Panel Decision</span>
              @if ($defense->hasOutcome())
                <span class="badge badge-green" style="margin-left:auto">Recorded</span>
              @endif
            </div>

            @if ($defense->hasOutcome())
              <div class="outcome-recorded">
                <div class="outcome-recorded-label">{{ $defense->outcomeLabel() }}</div>
                <div class="outcome-recorded-sub">
                  {{ \App\Support\DefenseOutcome::description($defense->outcome, $defense->defense_type) }}
                </div>
                @if ($defense->outcome_remarks)
                  <div class="outcome-recorded-remarks">{{ $defense->outcome_remarks }}</div>
                @endif
                <div class="outcome-recorded-meta">
                  Recorded by {{ $defense->outcomeRecordedBy?->name ?? 'the Panel Chair' }}
                  @if ($defense->outcome_recorded_at)
                    on {{ $defense->outcome_recorded_at->format('M j, Y \a\t g:i A') }}
                  @endif
                </div>
              </div>
            @elseif (! $isChair)
              <p class="outcome-note">
                Your Panel Chair records the panel&rsquo;s decision after the briefing. Score the rubric and leave your remarks.
              </p>
            @elseif ($gradesIn < $panelSize)
              <p class="outcome-note">
                {{ $gradesIn }} of {{ $panelSize }} panelists have submitted their scores. The decision can be recorded once everyone has.
              </p>
            @else
              <p class="outcome-note">
                All {{ $panelSize }} panelists have scored. Record what the panel agreed &mdash; the team is told this, and it is what happens next.
              </p>

              <div class="outcome-options">
                @foreach ($outcomeOptions as $option)
                  <label class="outcome-option outcome-{{ str_replace('_', '-', $option) }}">
                    <input type="radio" name="panel_outcome" value="{{ $option }}"/>
                    <div class="outcome-option-body">
                      <div class="outcome-option-label">{{ \App\Support\DefenseOutcome::label($option, $defense->defense_type) }}</div>
                      <div class="outcome-option-desc">{{ \App\Support\DefenseOutcome::description($option, $defense->defense_type) }}</div>
                    </div>
                  </label>
                @endforeach
              </div>

              <div class="card-header" style="margin-top:10px"><span class="card-title">Panel Remarks (optional)</span></div>
              <textarea id="outcome-remarks" class="remarks-field" placeholder="What the panel wants the team to know about this decision."></textarea>

              <button type="button" class="btn btn-primary" id="record-outcome-btn" style="margin-top:10px;width:100%;justify-content:center" onclick="recordOutcome()">
                Record Panel Decision
              </button>
            @endif
          </div>
        </div>
      </div>

      <div class="grading-footer">
        <div class="footer-left">
          <span style="font-size:12px;color:var(--text3)" id="saveStatus"></span>
        </div>
        <div style="display: flex; gap: 10px;">
          <button type="button" class="btn btn-ghost" onclick="saveDraft()">Save Draft</button>
          <button type="button" class="btn btn-primary" onclick="submitGrade()">
            Submit Grade
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-left:4px;"><polyline points="20 6 9 17 4 12"/></svg>
          </button>
        </div>
      </div>
    </form>

  </div>
  </div>
</div>

@include('instructor.partials.expert-profile')
@include('instructor.partials.skeleton-toggle')

<script>
const rubricTotalPoints = {{ (float) $rubric->total_points }};

function fmt(n) {
  return n % 1 === 0 ? n : n.toFixed(2).replace(/\.?0+$/, '');
}

function updateScore() {
  const criteria = document.querySelectorAll('.criterion');
  const catTotals = {};

  criteria.forEach(function (el) {
    const catIndex = el.dataset.catIndex;
    const checked = el.querySelector('input[type="radio"]:checked');
    const val = checked ? parseFloat(checked.value) : 0;
    catTotals[catIndex] = (catTotals[catIndex] || 0) + val;
  });

  let total = 0;
  Object.keys(catTotals).forEach(function (idx) {
    total += catTotals[idx];
    const el = document.getElementById('cat-total-' + idx);
    if (el) {
      const maxSpan = el.querySelector('span');
      el.innerHTML = fmt(catTotals[idx]) + (maxSpan ? maxSpan.outerHTML : '');
    }
  });

  const t = document.getElementById('tallyTotal');
  t.textContent = fmt(total);
  t.style.color = total >= rubricTotalPoints * 0.75 ? 'var(--green)' : total >= rubricTotalPoints * 0.5 ? 'var(--accent3)' : 'var(--red)';

  const pct = rubricTotalPoints > 0 ? Math.round((total / rubricTotalPoints) * 100) : 0;
  document.getElementById('tallyFill').style.width = Math.min(pct, 100) + '%';
  document.getElementById('tallyFill').style.background = total >= rubricTotalPoints * 0.75 ? 'var(--green)' : total >= rubricTotalPoints * 0.5 ? 'var(--accent)' : 'var(--red)';
  document.getElementById('tallyPct').textContent = pct + '%';
}

function showFormError(msg) {
  const el = document.getElementById('formError');
  if (!msg) { el.hidden = true; el.textContent = ''; return; }
  el.hidden = false;
  el.textContent = msg;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function collectPayload() {
  const form = document.getElementById('gradingForm');
  const scores = {};
  form.querySelectorAll('input[name^="scores["]:checked').forEach(function (input) {
    const match = input.name.match(/scores\[(\d+)\]/);
    if (match) scores[match[1]] = parseFloat(input.value);
  });
  // No verdict here - that is the panel's one decision, recorded by the
  // chair through /outcome, not gathered from each panelist.
  return { scores: scores, remarks: remarks };
}

// The panel's one decision, recorded by the chair after the briefing.
function recordOutcome() {
  const picked = document.querySelector('input[name="panel_outcome"]:checked');

  if (!picked) {
    showFormError('Choose what the panel decided.');
    return;
  }

  const btn = document.getElementById('record-outcome-btn');
  const remarksEl = document.getElementById('outcome-remarks');
  btn.disabled = true;
  btn.textContent = 'Recording...';

  fetch('{{ route('instructor.grading.outcome', $defense->defense_id) }}', {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ outcome: picked.value, remarks: remarksEl ? remarksEl.value : null }),
  })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (!res.success) {
        showFormError(res.message || 'Could not record the outcome.');
        btn.disabled = false;
        btn.textContent = 'Record Panel Decision';
        return;
      }
      window.location.reload();
    })
    .catch(function () {
      showFormError('Could not reach the server.');
      btn.disabled = false;
      btn.textContent = 'Record Panel Decision';
    });
}

function postGrade(url, statusMsg) {
  showFormError('');
  const saveStatus = document.getElementById('saveStatus');
  saveStatus.textContent = 'Saving…';

  fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify(collectPayload()),
  })
    .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
    .then(function ({ ok, data }) {
      if (!ok || !data.success) {
        saveStatus.textContent = '';
        showFormError(data.message || 'Something went wrong. Please try again.');
        return;
      }
      saveStatus.textContent = statusMsg;
      if (data.data && data.data.status === 'submitted') {
        setTimeout(function () { window.location.href = '{{ route('instructor.grading') }}'; }, 900);
      }
    })
    .catch(function () {
      saveStatus.textContent = '';
      showFormError('Network error. Please try again.');
    });
}

function saveDraft() { postGrade('{{ route('instructor.grading.save', $defense->defense_id) }}', 'Draft saved.'); }
function submitGrade() { postGrade('{{ route('instructor.grading.submit', $defense->defense_id) }}', 'Submitted! Redirecting…'); }

updateScore();

/* Mobile sidebar toggle */
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');

function toggleSidebar() {
  if (!sidebarEl) return;
  var isOpen = sidebarEl.classList.contains('mobile-open');
  if (isOpen) { closeSidebar(); } else { openSidebar(); }
}
function openSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.add('mobile-open');
  sidebarOverlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.remove('mobile-open');
  sidebarOverlay.classList.remove('open');
  document.body.style.overflow = '';
}
</script>
</body>
</html>
