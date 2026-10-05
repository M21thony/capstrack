@php
    $categories = $rubric->criteria->groupBy(fn ($c) => $c->category ?? 'General');
    $existingScores = $grade?->scores ?? [];
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');

    // The rubric as the sheet draws it: sections of criteria, each criterion
    // with its own levels. Built here so the page script only lays it out.
    $sections = $categories->map(fn ($criteria, $name) => [
        'name' => $name,
        'max' => (float) ($criteria->first()->category_max_points ?? $criteria->sum('max_points')),
        'items' => $criteria->map(fn ($c) => [
            'id' => (string) $c->criterion_id,
            'n' => $c->criterion_name,
            'd' => $c->description,
            'levels' => collect($c->decodedScoreLevels())->map(fn ($l) => [
                's' => (float) ($l['score'] ?? 0),
                'label' => $l['label'] ?? '',
                'desc' => $l['desc'] ?? '',
            ])->values(),
        ])->values(),
    ])->values();

    $savedScores = collect($existingScores)->mapWithKeys(fn ($v, $k) => [(string) $k => (float) $v]);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<title>Grading {{ $defense->team?->team_name }} — CapsTRACK</title>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/expert.css') }}" />
@include('partials.skeleton-head', ['skReveal' => 'block'])
<style nonce="{{ Vite::cspNonce() }}">
  /* The grading sheet fills the screen and never scrolls: a section that is
     too long for the window is split into pages that fit it. */
  #real-content{flex:1;min-height:0}
  .gs-content{height:100%;padding:0;overflow:hidden;display:flex;flex-direction:column}
  .gs{flex:1;min-height:0;display:grid;grid-template-rows:auto auto auto 1fr auto}
  .gs .num{font-variant-numeric:tabular-nums}
  .gs :focus-visible{outline:2px solid var(--accent3);outline-offset:2px}

  .gs-alert{margin:10px 20px 0;padding:9px 14px;border-radius:6px;font-size:13px}
  .gs-alert.ok{border:1px solid var(--green);background:var(--green-bg);color:var(--green)}
  .gs-alert.err{border:1px solid var(--red);background:var(--red-bg);color:var(--red)}

  .gs-top{display:flex;align-items:center;gap:18px;padding:10px 20px;background:var(--bg2);border-bottom:1px solid var(--border)}
  .gs-who{flex:1;min-width:0}
  .gs-who b{display:block;font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .gs-who small{font-size:12px;color:var(--text3)}
  .gs-stat{text-align:right;white-space:nowrap}
  .gs-stat b{font-size:22px;line-height:1}
  .gs-stat small{display:block;font-size:11.5px;color:var(--text3)}
  .gs-btn{font:inherit;font-size:13px;font-weight:600;padding:8px 14px;border-radius:6px;border:1px solid var(--border);background:var(--bg2);cursor:pointer;color:var(--text2);white-space:nowrap}
  .gs-btn:hover:not(:disabled){border-color:var(--border2);color:var(--text)}
  .gs-btn-primary{background:var(--navy-dark);border-color:var(--navy-dark);color:#fff}
  .gs-btn-primary:hover:not(:disabled){color:#fff}
  .gs-btn:disabled{opacity:.4;cursor:default}
  .gs-btn-abstain{color:var(--text3);border-style:dashed}
  .gs-btn-abstain:hover:not(:disabled){color:var(--red);border-color:var(--red)}
  .gs-btn-danger{background:var(--red);border-color:var(--red);color:#fff}
  .gs-btn-danger:hover:not(:disabled){color:#fff;filter:brightness(.95)}
  .gs-alert.abstained{border:1px solid var(--border2);background:var(--bg3);color:var(--text2)}
  .gs-alert.abstained b{color:var(--text)}
  .gs-alert.abstained .why{display:block;margin-top:3px;font-style:italic;color:var(--text3)}
  .abstain-box{width:min(460px,calc(100vw - 32px))}
  .abstain-label{display:block;font-size:12px;font-weight:600;color:var(--text);margin-bottom:6px}
  #abstain-reason{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font:inherit;font-size:13px;line-height:1.5;resize:vertical}
  #abstain-reason:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
  .abstain-count{margin-top:4px;font-size:11.5px;color:var(--text3)}

  .gs-steps{display:grid;grid-auto-flow:column;grid-auto-columns:minmax(0,1fr);background:var(--bg2);border-bottom:1px solid var(--border)}
  .gs-step{font:inherit;border:none;background:none;border-bottom:2px solid transparent;padding:8px 10px;text-align:left;cursor:pointer;min-width:0;display:grid;gap:1px}
  .gs-step + .gs-step{border-left:1px solid var(--border)}
  .gs-step .nm{font-size:12px;font-weight:600;color:var(--text2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .gs-step .st{font-size:11.5px;color:var(--text3);display:flex;justify-content:space-between;gap:6px}
  .gs-step.on{border-bottom-color:var(--accent);background:var(--bg3)}
  .gs-step.on .nm{color:var(--text)}
  .gs-step.done .mark{color:var(--green);font-weight:600}
  .gs-step.part .mark{color:var(--amber)}

  .gs-area{min-height:0;display:flex;flex-direction:column;padding:12px 20px 0}
  .gs-area[hidden]{display:none}
  .gs-head{display:flex;align-items:baseline;gap:12px;margin-bottom:8px}
  .gs-head h2{margin:0;font-size:16px}
  .gs-head .meta{font-size:12px;color:var(--text3)}
  .gs-head .pg{margin-left:auto;display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text3)}
  .gs-head .pg .gs-btn{padding:4px 10px;font-size:12px}

  .gs-sheet{flex:1;min-height:0;background:var(--bg2);border:1px solid var(--border);border-radius:12px;display:flex;flex-direction:column;overflow:hidden;margin-bottom:12px}
  .gs-rows{flex:1;min-height:0;overflow:hidden}
  .gs table{width:100%;border-collapse:collapse;table-layout:fixed}
  .gs th{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text3);text-align:left;padding:7px 12px;background:var(--bg3);border-bottom:1px solid var(--border)}
  .gs th:first-child{width:23%}
  .gs th.sc{width:84px;text-align:right}
  .gs th .key{display:inline-block;margin-right:6px;font-size:10px;padding:0 5px;border:1px solid var(--border2);border-bottom-width:2px;border-radius:4px;letter-spacing:0}
  .gs td{border-bottom:1px solid var(--border);padding:0;vertical-align:top}
  .gs tr.cur td{background:var(--bg3)}
  .gs tr.cur td:first-child{box-shadow:inset 3px 0 0 var(--accent)}
  .gs-crit{padding:9px 12px;cursor:pointer}
  .gs-crit b{display:block;font-size:13px}
  .gs-crit small{color:var(--text3);font-size:11.5px}
  .gs-cell{font:inherit;display:flex;flex-direction:column;gap:2px;width:100%;height:100%;min-height:62px;text-align:left;border:none;border-left:1px solid var(--border);background:none;padding:8px 12px;cursor:pointer;position:relative;color:var(--text)}
  .gs-cell b{font-size:13px;color:var(--accent3)}
  .gs-cell .lb{font-size:11px;font-weight:600;color:var(--text3)}
  .gs-cell span.ds{font-size:11.5px;color:var(--text2);line-height:1.35;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
  .gs-cell:hover{background:var(--accent-light)}
  .gs tr.scored .gs-cell{opacity:.55}
  .gs tr.scored .gs-cell:hover{opacity:1}
  .gs-cell[aria-pressed="true"]{opacity:1!important;background:var(--accent-light);box-shadow:inset 0 0 0 2px var(--accent)}
  .gs-cell[aria-pressed="true"]::after{content:"✓";position:absolute;top:7px;right:9px;font-size:12px;color:var(--accent3);font-weight:700}
  .gs-score{padding:9px 12px;text-align:right;font-weight:600}
  .gs-score.empty{color:var(--border2);font-weight:400}
  .gs-subtotal{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-top:1px solid var(--border);background:var(--bg3);font-size:12.5px;color:var(--text2)}
  .gs-subtotal b{color:var(--text)}

  .gs-remarks{flex:1;min-height:0;display:grid;grid-template-columns:1fr 1fr;gap:14px;padding-bottom:12px}
  .gs-panel{background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:14px;display:flex;flex-direction:column;gap:8px;min-height:0;overflow:auto}
  .gs-panel h3{margin:0;font-size:13px}
  .gs-panel .remarks-field{flex:1;min-height:90px;resize:none}
  .gs-overview{display:grid;grid-template-columns:1fr auto;gap:4px 12px;font-size:12.5px;color:var(--text2);margin-bottom:6px}
  .gs-overview b{color:var(--text)}

  /* A decision is coloured by how serious it is: pass green, redefense
     orange, a new title red - never all green. */
  .outcome-recorded.is-major{background:#fff7ed;border-color:#fed7aa}
  .outcome-recorded.is-major .outcome-recorded-label{color:#c2410c}
  .outcome-recorded.is-major .outcome-recorded-remarks{border-top-color:#fed7aa}
  .outcome-recorded.is-fail{background:var(--red-bg,#fef2f2);border-color:#fecaca}
  .outcome-recorded.is-fail .outcome-recorded-label{color:var(--red,#dc2626)}
  .outcome-recorded.is-fail .outcome-recorded-remarks{border-top-color:#fecaca}
  .outcome-option.is-pass input:checked+.outcome-option-body{border-color:var(--green);background:var(--green-bg);box-shadow:0 0 0 3px rgba(22,163,74,.1)}
  .outcome-option.is-pass input:checked+.outcome-option-body .outcome-option-label{color:var(--green)}
  .outcome-option.is-major input:checked+.outcome-option-body{border-color:#ea580c;background:#fff7ed;box-shadow:0 0 0 3px rgba(234,88,12,.1)}
  .outcome-option.is-major input:checked+.outcome-option-body .outcome-option-label{color:#c2410c}
  .outcome-option.is-fail input:checked+.outcome-option-body{border-color:var(--red);background:var(--red-bg);box-shadow:0 0 0 3px rgba(220,38,38,.1)}
  .outcome-option.is-fail input:checked+.outcome-option-body .outcome-option-label{color:var(--red)}

  .gs-foot{display:flex;align-items:center;gap:10px;padding:9px 20px;background:var(--bg2);border-top:1px solid var(--border)}
  .gs-foot .hint{flex:1;font-size:12px;color:var(--text3)}
  .gs-foot kbd{font:inherit;font-size:10.5px;padding:0 5px;border:1px solid var(--border2);border-bottom-width:2px;border-radius:4px;background:var(--bg3)}
  .gs-saved{font-size:12px;color:var(--text3)}

  /* A phone cannot fit a rubric sheet; there it scrolls instead of paging. */
  @media (max-width: 900px){
    .gs-content{overflow:auto}
    .gs{display:block}
    .gs-steps{grid-auto-flow:row;grid-template-columns:repeat(2,minmax(0,1fr))}
    .gs-sheet,.gs-rows{overflow:auto}
    .gs table{min-width:720px}
    .gs-remarks{grid-template-columns:1fr}
    .gs-foot .hint{display:none}
  }
  @media (prefers-reduced-motion: reduce){.gs *{transition:none!important}}
</style>
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
    <div class="sk" style="height:60px;border-radius:12px;margin-bottom:12px"></div>
    <div class="sk" style="height:44px;border-radius:12px;margin-bottom:12px"></div>
    <div class="sk" style="height:320px;border-radius:12px"></div>
  </div>

  <div id="real-content" style="display:none">
  <div class="content gs-content">
  <div class="gs">

    <div>
      <p class="gs-alert err" id="formError" role="alert" hidden></p>
      @if($grade?->isAbstained())
        <div class="gs-alert abstained">
          <b>You abstained from grading this team.</b> Your scores are left out of the team's average.
          <span class="why">Your reason: &ldquo;{{ $grade->remarks }}&rdquo;</span>
        </div>
      @elseif($grade?->status === 'submitted')
        <div class="gs-alert ok">You already submitted a grade for this team. You can still change it - submitting again replaces your previous grade.</div>
      @endif
    </div>

    <header class="gs-top">
      <div class="gs-who">
        <b>{{ $defense->team?->team_name ?? 'Unknown Team' }} — {{ $defense->team?->project_title ?? 'No title' }}</b>
        <small>{{ $defense->defense_type }} · You are {{ $panelRole }} · {{ $rubric->rubric_name }}</small>
      </div>
      <div class="gs-stat"><b class="num" id="gs-left">0</b><small>criteria left</small></div>
      <div class="gs-stat"><b class="num"><span id="gs-total">0</span><small style="display:inline;font-size:12px"> / {{ $fmt($rubric->total_points) }}</small></b><small>points</small></div>
      <button type="button" class="gs-btn" id="gs-jump">Next unscored</button>
    </header>

    <nav class="gs-steps" id="gs-steps" aria-label="Rubric sections"></nav>

    <div style="min-height:0;display:grid">
      <section class="gs-area" id="gs-sheet-area" aria-live="polite"></section>

      <section class="gs-area" id="gs-remarks-area" hidden>
        <div class="gs-head"><h2>Remarks and decision</h2></div>
        <div class="gs-remarks">
          
          <form id="gradingForm" class="gs-panel" onsubmit="return false">
            @csrf
            <h3>Your remarks</h3>
            {{-- Remarks are this panelist's own. The verdict is not: the panel
                 confers and gives the team one result, recorded once by the
                 chair beside this. --}}
            {{-- An abstention's remarks are its reason, not comments for the team. --}}
            <textarea name="remarks" class="remarks-field" placeholder="Your comments for the team - what you want them to fix, or what worked.">{{ $grade?->isAbstained() ? '' : $grade?->remarks }}</textarea>
          </form>

          <div class="gs-panel">
            <h3>Before you submit</h3>
            <div class="gs-overview" id="gs-overview"></div>

            <h3 style="display:flex;align-items:center;gap:8px">Panel decision
              @if ($defense->hasOutcome())<span class="badge {{ ['pass' => 'badge-green', 'major' => 'badge-amber', 'fail' => 'badge-red'][\App\Support\DefenseOutcome::tone($defense->outcome)] ?? 'badge-gray' }}">Recorded</span>@endif
            </h3>

            @if ($defense->hasOutcome())
              <div class="outcome-recorded is-{{ \App\Support\DefenseOutcome::tone($defense->outcome) }}">
                <div class="outcome-recorded-label">{{ $defense->outcomeLabel() }}</div>
                <div class="outcome-recorded-sub">{{ \App\Support\DefenseOutcome::description($defense->outcome, $defense->defense_type) }}</div>
                @if ($defense->outcome_remarks)
                  <div class="outcome-recorded-remarks">{{ $defense->outcome_remarks }}</div>
                @endif
                <div class="outcome-recorded-meta">
                  Recorded by {{ $defense->outcomeRecordedBy?->name ?? 'the Panel Chair' }}
                  @if ($defense->outcome_recorded_at) on {{ $defense->outcome_recorded_at->format('M j, Y \a\t g:i A') }} @endif
                </div>
              </div>
            @elseif (! $isChair)
              <p class="outcome-note">Your Panel Chair records the panel&rsquo;s decision after the briefing. Score the rubric and leave your remarks.</p>
            @elseif ($gradesIn < $panelSize)
              <p class="outcome-note">{{ $gradesIn }} of {{ $panelSize }} panelists have submitted their scores or abstained. The decision can be recorded once everyone has.</p>
            @else
              <p class="outcome-note">All {{ $panelSize }} panelists are in. Record what the panel agreed &mdash; the team is told this, and it is what happens next.</p>
              <div class="outcome-options">
                @foreach ($outcomeOptions as $option)
                  <label class="outcome-option is-{{ \App\Support\DefenseOutcome::tone($option) }}">
                    <input type="radio" name="panel_outcome" value="{{ $option }}"/>
                    <div class="outcome-option-body">
                      <div class="outcome-option-label">{{ \App\Support\DefenseOutcome::label($option, $defense->defense_type) }}</div>
                      <div class="outcome-option-desc">{{ \App\Support\DefenseOutcome::description($option, $defense->defense_type) }}</div>
                    </div>
                  </label>
                @endforeach
              </div>
              <button type="button" class="gs-btn gs-btn-primary" id="record-outcome-btn" onclick="recordOutcome()">Record panel decision</button>
            @endif
          </div>
        </div>
      </section>
    </div>

    <footer class="gs-foot">
      <div class="hint"><kbd>←</kbd><kbd>→</kbd> sections · <kbd>↑</kbd><kbd>↓</kbd> rows · <kbd>1</kbd>–<kbd>4</kbd> score and move on</div>
      @if ($grade?->isAbstained())
        <span class="gs-saved" id="saveStatus">Abstained</span>
        @unless ($defense->hasOutcome())
          <button type="button" class="gs-btn gs-btn-primary" id="gs-withdraw" onclick="withdrawAbstention()">Withdraw abstention and grade</button>
        @endunless
      @else
        <span class="gs-saved" id="saveStatus">{{ $grade ? ($grade->status === 'submitted' ? 'Submitted' : 'Draft saved') : '' }}</span>
        @unless ($defense->hasOutcome())
          <button type="button" class="gs-btn gs-btn-abstain" onclick="openAbstain()" title="Decline to score this team, with a reason">Abstain</button>
        @endunless
        <button type="button" class="gs-btn" onclick="saveDraft()">Save draft</button>
        <button type="button" class="gs-btn gs-btn-primary" id="gs-submit" onclick="submitGrade()" disabled>Submit grade</button>
      @endif
    </footer>

  </div>
  </div>
  </div>
</div>

{{-- Abstaining: declining to score this team, with the reason. --}}
<div class="modal-overlay" id="abstain-modal" onclick="if (event.target === this) closeAbstain()">
  <div class="modal-box abstain-box" role="dialog" aria-modal="true" aria-labelledby="abstain-title">
    <div class="modal-title" id="abstain-title">Abstain from grading {{ $defense->team?->team_name ?? 'this team' }}?</div>
    <div class="modal-sub">
      You will not score this team. Your abstention counts as turned in, so the panel is not held up,
      and the team's average is taken from the panelists who scored.
      The team sees that you abstained, not your reason.
    </div>
    <label for="abstain-reason" class="abstain-label">Reason</label>
    <textarea id="abstain-reason" rows="4" maxlength="1000" placeholder="e.g. I advised this team, so I should not grade it."></textarea>
    <div class="abstain-count" id="abstain-count">At least 10 characters.</div>
    <p class="gs-alert err" id="abstain-error" role="alert" hidden style="margin:10px 0 0"></p>
    <div class="modal-actions" style="margin-top:16px">
      <button type="button" class="gs-btn" aria-label="Close" onclick="closeAbstain()">Cancel</button>
      <button type="button" class="gs-btn gs-btn-danger" id="abstain-confirm" onclick="confirmAbstain()">Abstain</button>
    </div>
  </div>
</div>

@include('instructor.partials.expert-profile')
@include('instructor.partials.skeleton-toggle')

<script nonce="{{ Vite::cspNonce() }}">
const SECTIONS = {{ Js::from($sections) }};
const scores = {{ Js::from((object) $savedScores->all()) }};
const RUBRIC_TOTAL = {{ (float) $rubric->total_points }};

const ALL = SECTIONS.flatMap((s, si) => s.items.map(c => Object.assign(c, { sec: si })));
const REMARKS = SECTIONS.length;
const $ = id => document.getElementById(id);
const el = (t, c, x) => { const e = document.createElement(t); if (c) e.className = c; if (x != null) e.textContent = x; return e; };
const fmt = n => +(+n).toFixed(2);
const same = (a, b) => a !== undefined && Math.abs(a - b) < 0.001;
const secScore = s => fmt(s.items.reduce((a, c) => a + (scores[c.id] ?? 0), 0));
const secDone = s => s.items.filter(c => c.id in scores).length;
const narrow = () => window.matchMedia('(max-width: 900px)').matches;

let sec = 0, page = 0, pages = [[]], cur = 0;
let dirty = false;

// Open on the first criterion still to score.
(function start() {
  const first = ALL.find(c => !(c.id in scores));
  if (first) { cur = ALL.indexOf(first); sec = first.sec; } else { sec = REMARKS; }
})();

/* ── Section strip ───────────────────────────────────────────── */
function drawSteps() {
  const nav = $('gs-steps'); nav.innerHTML = '';
  SECTIONS.forEach((s, i) => {
    const done = secDone(s);
    const b = el('button', 'gs-step' + (i === sec ? ' on' : '') + (done === s.items.length ? ' done' : done ? ' part' : ''));
    b.type = 'button'; b.title = s.name; b.setAttribute('aria-current', i === sec ? 'step' : 'false');
    const st = el('span', 'st');
    st.append(el('span', 'num', fmt(secScore(s)) + '/' + fmt(s.max)), el('span', 'mark num', done === s.items.length ? '✓ done' : done + '/' + s.items.length));
    b.append(el('span', 'nm', s.name), st);
    b.onclick = () => go(i, 0);
    nav.append(b);
  });
  const r = el('button', 'gs-step' + (sec === REMARKS ? ' on' : ''));
  r.type = 'button';
  const st = el('span', 'st'); st.append(el('span', '', 'and decision'));
  r.append(el('span', 'nm', 'Remarks'), st);
  r.onclick = () => go(REMARKS, 0);
  nav.append(r);
}

/* ── The sheet ───────────────────────────────────────────────── */
// Column headers are shared only when every criterion in the section has the
// same levels; otherwise each cell names its own level.
function sharedLabels(s) {
  const first = s.items[0]?.levels.map(l => l.label).join('|');
  return s.items.every(c => c.levels.map(l => l.label).join('|') === first) ? s.items[0].levels.map(l => l.label) : null;
}

function shell(box, s) {
  const labels = sharedLabels(s);
  const cols = Math.max(...s.items.map(c => c.levels.length));
  const t = el('table'); const h = el('tr');
  h.append(el('th', '', 'Criterion'));
  for (let k = 0; k < cols; k++) {
    const th = el('th');
    if (k < 9) th.append(el('span', 'key', k + 1));
    th.append(labels ? labels[k] : 'Level ' + (k + 1));
    h.append(th);
  }
  h.append(el('th', 'sc', 'Score'));
  const thead = el('thead'); thead.append(h);
  const tb = el('tbody'); t.append(thead, tb); box.append(t);
  return { tb, labels, cols };
}

function row(c, shared, cols) {
  const tr = el('tr', (c.id in scores ? 'scored' : '') + (ALL.indexOf(c) === cur ? ' cur' : ''));
  const first = el('td'); const d = el('div', 'gs-crit');
  d.append(el('b', '', c.n)); if (c.d) d.append(el('small', '', c.d));
  d.onclick = () => { cur = ALL.indexOf(c); draw(); };
  first.append(d); tr.append(first);
  for (let k = 0; k < cols; k++) {
    const td = el('td'); const l = c.levels[k];
    if (l) {
      const b = el('button', 'gs-cell'); b.type = 'button';
      b.setAttribute('aria-pressed', same(scores[c.id], l.s));
      b.setAttribute('aria-label', c.n + ': ' + (l.label || 'level ' + (k + 1)) + ', ' + fmt(l.s) + ' points. ' + (l.desc || ''));
      b.append(el('b', 'num', fmt(l.s)));
      if (!shared && l.label) b.append(el('span', 'lb', l.label));
      if (l.desc) b.append(el('span', 'ds', l.desc));
      b.onclick = () => score(c, l.s);
      td.append(b);
    }
    tr.append(td);
  }
  tr.append(el('td', 'gs-score num' + (c.id in scores ? '' : ' empty'), c.id in scores ? fmt(scores[c.id]) : '—'));
  return tr;
}

function drawSheet() {
  const s = SECTIONS[sec];
  const area = $('gs-sheet-area'); area.innerHTML = '';
  const head = el('div', 'gs-head');
  head.append(el('h2', '', s.name), el('span', 'meta num', s.items.length + ' criteria · max ' + fmt(s.max) + ' pts'));
  const pg = el('div', 'pg'); const label = el('span', 'num');
  const prev = el('button', 'gs-btn', 'Previous'); const next = el('button', 'gs-btn', 'Next');
  prev.type = next.type = 'button';
  pg.append(label, prev, next); head.append(pg);
  const sheet = el('div', 'gs-sheet'); const rows = el('div', 'gs-rows'); const sub = el('div', 'gs-subtotal');
  sheet.append(rows, sub); area.append(head, sheet);

  // Fill a page until the next row would overflow, then start another.
  pages = [[]];
  if (narrow()) {
    pages = [s.items.slice()];
  } else {
    let sh = shell(rows, s);
    s.items.forEach(c => {
      sh.tb.append(row(c, sh.labels, sh.cols));
      if (rows.scrollHeight > rows.clientHeight + 1 && pages[pages.length - 1].length) {
        rows.innerHTML = ''; sh = shell(rows, s); sh.tb.append(row(c, sh.labels, sh.cols)); pages.push([]);
      }
      pages[pages.length - 1].push(c);
    });
  }
  page = Math.max(0, Math.min(page, pages.length - 1));
  rows.innerHTML = '';
  const sh = shell(rows, s);
  pages[page].forEach(c => sh.tb.append(row(c, sh.labels, sh.cols)));

  const first = s.items.indexOf(pages[page][0]) + 1, last = first + pages[page].length - 1;
  label.textContent = pages.length > 1 ? 'Criteria ' + first + '–' + last + ' of ' + s.items.length : '';
  prev.disabled = sec === 0 && page === 0;
  next.textContent = page < pages.length - 1 ? 'Next page' : (sec < SECTIONS.length - 1 ? 'Next section' : 'Remarks');
  prev.onclick = () => page > 0 ? go(sec, page - 1) : go(sec - 1, 99);
  next.onclick = () => page < pages.length - 1 ? go(sec, page + 1) : go(sec + 1, 0);

  sub.append(el('span', 'num', secDone(s) + ' of ' + s.items.length + ' scored in this section'), el('b', 'num', fmt(secScore(s)) + ' / ' + fmt(s.max)));
}

function drawOverview() {
  const ov = $('gs-overview'); ov.innerHTML = '';
  SECTIONS.forEach(s => { ov.append(el('span', '', s.name)); ov.append(el('b', 'num', fmt(secScore(s)) + ' / ' + fmt(s.max))); });
  const left = ALL.filter(c => !(c.id in scores)).length;
  ov.append(el('span', '', left ? left + ' criteria still to score' : 'Every criterion is scored'));
  ov.append(el('b', 'num', fmt(ALL.reduce((a, c) => a + (scores[c.id] ?? 0), 0)) + ' / ' + fmt(RUBRIC_TOTAL)));
}

function go(s, p) {
  if (s < 0 || s > REMARKS) return;
  sec = s; page = p;
  draw();
  if (sec < REMARKS && !pages[page].some(c => ALL.indexOf(c) === cur)) { cur = ALL.indexOf(pages[page][0]); draw(); }
}

function score(c, s) {
  scores[c.id] = s;
  dirty = true;
  $('saveStatus').textContent = 'Unsaved changes';
  const next = SECTIONS[c.sec].items.find(x => !(x.id in scores));
  if (next) {
    cur = ALL.indexOf(next);
    const p = pages.findIndex(pg => pg.includes(next));
    if (p >= 0) page = p;
  }
  draw();
}

function draw() {
  const onRemarks = sec === REMARKS;
  $('gs-sheet-area').hidden = onRemarks;
  $('gs-remarks-area').hidden = !onRemarks;
  drawSteps();
  if (onRemarks) drawOverview(); else drawSheet();
  const n = Object.keys(scores).length;
  const total = fmt(ALL.reduce((a, c) => a + (scores[c.id] ?? 0), 0));
  $('gs-total').textContent = total;
  $('gs-left').textContent = ALL.length - n;
  $('gs-jump').disabled = n === ALL.length;
  // The server refuses a submission with any criterion unscored. There is no
  // Submit button while abstaining.
  const submit = $('gs-submit');
  if (submit) {
    submit.disabled = n < ALL.length;
    submit.title = n < ALL.length ? 'Score every criterion first' : '';
  }
}

$('gs-jump').onclick = () => {
  const c = ALL.find(x => !(x.id in scores)); if (!c) return;
  cur = ALL.indexOf(c); sec = c.sec; page = 0; draw();
  const p = pages.findIndex(pg => pg.includes(c)); if (p > 0) { page = p; draw(); }
};

document.addEventListener('keydown', e => {
  if (e.target.closest && e.target.closest('textarea, input, select')) return;
  if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
    e.preventDefault();
    const forward = e.key === 'ArrowRight';
    if (sec < REMARKS && forward && page < pages.length - 1) go(sec, page + 1);
    else if (sec < REMARKS && !forward && page > 0) go(sec, page - 1);
    else go(sec + (forward ? 1 : -1), forward ? 0 : 99);
    return;
  }
  if (sec === REMARKS) return;
  const items = SECTIONS[sec].items, i = items.indexOf(ALL[cur]);
  if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
    e.preventDefault();
    const j = Math.max(0, Math.min(items.length - 1, (i < 0 ? 0 : i) + (e.key === 'ArrowDown' ? 1 : -1)));
    cur = ALL.indexOf(items[j]);
    const p = pages.findIndex(pg => pg.includes(items[j])); if (p !== page) page = p;
    draw();
  } else if (/^[1-9]$/.test(e.key) && i >= 0) {
    const level = items[i].levels[+e.key - 1];
    if (level) score(items[i], level.s);
  }
});

// The sheet fills a page until the next row would overflow, so it can only be
// drawn against a height that is real. Being visible is not enough: the moment
// the skeleton hands over, the area is on screen with no height yet, and
// measuring then puts a single criterion on every page.
let sheetHeight = 0;
let waited = 0;

(function whenVisible() {
  const area = $('gs-sheet-area');
  const remarks = $('gs-remarks-area');
  const ready = area.offsetParent !== null ? area.clientHeight > 0 : remarks.offsetParent !== null;

  // Waiting for a height is worth a couple of seconds, never the whole page:
  // if one never arrives, a badly paginated sheet still beats a blank one.
  if (!ready && waited++ < 180) {
    requestAnimationFrame(whenVisible);

    return;
  }

  sheetHeight = area.clientHeight;
  draw();
  // Rows are measured to fill the page; measure again once the font has loaded.
  document.fonts.ready.then(draw);

  // The height can still change under it - the font swapping, the sidebar
  // collapsing, a zoom. The rows are flex-sized and clipped, so redrawing them
  // cannot change this height back; only the page around them can.
  if (window.ResizeObserver) {
    new ResizeObserver(() => {
      const h = area.clientHeight;

      if (h > 0 && Math.abs(h - sheetHeight) > 4) {
        sheetHeight = h;
        draw();
      }
    }).observe(area);
  }
})();
let resizeTimer;
window.addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(draw, 120); });
window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

/* ── Saving ──────────────────────────────────────────────────── */
function showFormError(msg) {
  const box = $('formError');
  box.hidden = !msg;
  box.textContent = msg || '';
}

function collectPayload() {
  const remarksField = document.querySelector('#gradingForm textarea[name="remarks"]');
  return { scores: scores, remarks: remarksField ? remarksField.value : null };
}

// The panel's one decision, recorded by the chair after the briefing.
function recordOutcome() {
  const picked = document.querySelector('input[name="panel_outcome"]:checked');
  if (!picked) { showFormError('Choose what the panel decided.'); return; }

  const btn = $('record-outcome-btn');
  btn.disabled = true;
  btn.textContent = 'Recording...';

  fetch('{{ route('instructor.grading.outcome', $defense->defense_id) }}', {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ outcome: picked.value }),
  })
    .then(r => r.json())
    .then(res => {
      if (!res.success) {
        showFormError(res.message || 'Could not record the outcome.');
        btn.disabled = false; btn.textContent = 'Record panel decision';
        return;
      }
      dirty = false;
      window.location.reload();
    })
    .catch(() => {
      showFormError('Could not reach the server.');
      btn.disabled = false; btn.textContent = 'Record panel decision';
    });
}

function postGrade(url, statusMsg, method) {
  showFormError('');
  const saveStatus = $('saveStatus');
  saveStatus.textContent = 'Saving…';

  fetch(url, {
    method: method,
    credentials: 'same-origin',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify(collectPayload()),
  })
    .then(res => res.json().then(data => ({ ok: res.ok, data })))
    .then(({ ok, data }) => {
      if (!ok || !data.success) {
        saveStatus.textContent = 'Not saved';
        showFormError(data.message || 'Something went wrong. Please try again.');
        return;
      }
      dirty = false;
      saveStatus.textContent = statusMsg;
      if (data.data && data.data.status === 'submitted') {
        // If they can record outcome (Panel Chair, all grades in, no outcome yet),
        // navigate to the Remarks & Decision tab instead of redirecting
        if (data.data.can_record_outcome) {
          saveStatus.textContent = 'Submitted - everyone has scored, record the decision below';
          // Navigate to Remarks tab
          go({{ $sections->count() }}, 0);
          draw();
          return;
        }

        // For non-chair or when outcome already recorded, return to team list
        setTimeout(() => { window.location.href = '{{ route('instructor.grading') }}'; }, 900);
      }
    })
    .catch(() => {
      saveStatus.textContent = 'Not saved';
      showFormError('Network error. Nothing was saved.');
    });
}

/* ── Abstaining ──────────────────────────────────────────────── */
const ABSTAIN_MIN = 10;

function abstainFetch(method, body) {
  return fetch('{{ route('instructor.grading.abstain', $defense->defense_id) }}', {
    method: method,
    credentials: 'same-origin',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: body ? JSON.stringify(body) : null,
  }).then(res => res.json());
}

function updateAbstainCount() {
  const n = $('abstain-reason').value.trim().length;
  $('abstain-count').textContent = n < ABSTAIN_MIN
    ? (n ? (ABSTAIN_MIN - n) + ' more character' + (ABSTAIN_MIN - n === 1 ? '' : 's') + ' needed' : 'At least ' + ABSTAIN_MIN + ' characters.')
    : n + ' / 1000';
}

function openAbstain() {
  $('abstain-error').hidden = true;
  updateAbstainCount();
  $('abstain-modal').classList.add('show');
  $('abstain-reason').focus();
}

function closeAbstain() { $('abstain-modal').classList.remove('show'); }

function confirmAbstain() {
  const reason = $('abstain-reason').value.trim();
  const error = $('abstain-error');
  const btn = $('abstain-confirm');

  if (reason.length < ABSTAIN_MIN) {
    error.textContent = 'Give the reason you are abstaining - at least ' + ABSTAIN_MIN + ' characters.';
    error.hidden = false;
    $('abstain-reason').focus();
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Abstaining…';

  abstainFetch('POST', { reason: reason })
    .then(res => {
      if (!res.success) {
        error.textContent = res.message || 'Could not record your abstention.';
        error.hidden = false;
        btn.disabled = false;
        btn.textContent = 'Abstain';
        return;
      }
      dirty = false;
      window.location.reload();
    })
    .catch(() => {
      error.textContent = 'Could not reach the server.';
      error.hidden = false;
      btn.disabled = false;
      btn.textContent = 'Abstain';
    });
}

function withdrawAbstention() {
  const btn = $('gs-withdraw');
  btn.disabled = true;

  abstainFetch('DELETE')
    .then(res => {
      if (!res.success) { showFormError(res.message || 'Could not withdraw.'); btn.disabled = false; return; }
      window.location.reload();
    })
    .catch(() => { showFormError('Could not reach the server.'); btn.disabled = false; });
}

if ($('abstain-reason')) $('abstain-reason').addEventListener('input', updateAbstainCount);

function saveDraft() { postGrade('{{ route('instructor.grading.save', $defense->defense_id) }}', 'Draft saved', 'PUT'); }
function submitGrade() { postGrade('{{ route('instructor.grading.submit', $defense->defense_id) }}', 'Submitted - returning to your teams…', 'PATCH'); }

/* Mobile sidebar toggle */
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() {
  if (!sidebarEl) return;
  sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar();
}
function openSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.add('mobile-open'); sidebarOverlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.remove('mobile-open'); sidebarOverlay.classList.remove('open');
  document.body.style.overflow = '';
}
</script>
@include('partials.escape-closes-modals')
</body>
</html>
