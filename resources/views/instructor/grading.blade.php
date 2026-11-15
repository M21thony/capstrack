<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CapsTRACK — Expert Portal</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/expert.css') }}" />
@include('partials.skeleton-head', ['skReveal' => 'block'])
<style nonce="{{ Vite::cspNonce() }}">
  /* One row per defense you sit on: what is left to grade reads first. */
  .gl{background:var(--bg2);border:1px solid var(--border);border-radius:12px;display:flex;flex-direction:column;max-height:calc(100vh - 120px);max-height:calc(100dvh - 120px);overflow:hidden}
  .gl-head{display:flex;align-items:center;gap:12px;padding:10px 14px;border-bottom:1px solid var(--border);flex-wrap:wrap}
  .gl-head b{font-size:14px}
  .gl-head .sub{font-size:12.5px;color:var(--text3)}
  .gl-filter{margin-left:auto;display:flex;gap:4px}
  .gl-filter button{font:inherit;font-size:12px;font-weight:600;border:1px solid var(--border);background:var(--bg2);color:var(--text2);border-radius:999px;padding:3px 11px;cursor:pointer;min-height:28px}
  .gl-filter button[aria-pressed="true"]{background:var(--accent-light);border-color:var(--accent);color:var(--accent3)}
  .gl-scroll{overflow:auto;min-height:0}
  .gl table{width:100%;border-collapse:collapse}
  .gl th{position:sticky;top:0;background:#18191f;font-size:10.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#d4d6de;text-align:left;padding:8px 14px;border-bottom:0;white-space:nowrap}
  .gl td{padding:10px 14px;border-bottom:1px solid var(--border);font-size:13px;vertical-align:middle}
  .gl tr:last-child td{border-bottom:0}
  .gl tbody tr:hover td{background:var(--bg3)}
  .gl-team{display:flex;align-items:center;gap:10px;min-width:0}
  .gl-av{flex:none;width:32px;height:32px;border-radius:50%;background:var(--navy-dark,#1e2028);color:#fff;font-size:11px;font-weight:700;display:grid;place-items:center}
  .gl-team b{display:block;font-size:13.5px}
  .gl-team small{display:block;color:var(--text3);font-size:12px;max-width:44ch;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .gl-muted{color:var(--text3);font-size:12px}
  .gl-outcome{font-size:12px;font-weight:600}
  .gl-outcome.is-pass{color:var(--green)}
  .gl-outcome.is-major{color:#c2410c}
  .gl-outcome.is-fail{color:var(--red)}
  .gl-num{font-variant-numeric:tabular-nums}
  .gl-go{font:inherit;font-size:12px;font-weight:600;border-radius:6px;padding:6px 12px;border:1px solid var(--border2);background:var(--bg2);color:var(--text);text-decoration:none;white-space:nowrap}
  .gl-go.primary{background:var(--accent);border-color:var(--accent);color:#18191f}
  .gl-empty{padding:40px 16px;text-align:center;color:var(--text3);font-size:13px}

  .gl-sel{width:36px}
  .gl-sel input{width:16px;height:16px;accent-color:var(--accent);cursor:pointer}
  .gl-method b{display:block;font-size:13px}
  .gl-method small{display:block;color:var(--text3);font-size:12px}
  .gl-setup{font:inherit;font-size:12px;font-weight:600;display:inline-flex;align-items:center;gap:6px;border-radius:6px;padding:5px 12px;min-height:30px;border:1px solid #18191f;background:#18191f;color:#fff;cursor:pointer}
  .gl-setup svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

  /* Grade Setup */
  .su{border:0;border-radius:12px;padding:0;width:min(560px,calc(100vw - 32px));max-height:calc(100dvh - 48px);margin:auto;background:var(--bg2);color:var(--text);box-shadow:0 20px 50px rgba(0,0,0,.25)}
  .su::backdrop{background:rgba(15,16,20,.45)}
  .su form{display:flex;flex-direction:column;max-height:calc(100dvh - 48px)}
  .su-h{padding:14px 18px;border-bottom:1px solid var(--border)}
  .su-h b{font-size:15px}
  .su-h p{margin:2px 0 0;font-size:12.5px;color:var(--text3)}
  .su-b{padding:12px 18px;overflow:auto;display:flex;flex-direction:column;gap:14px}
  .su-b h3{margin:0 0 6px;font-size:10.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text3)}
  .su-seg{display:flex;gap:4px}
  .su-seg button{font:inherit;font-size:12px;font-weight:600;border:1px solid var(--border);background:var(--bg2);color:var(--text2);border-radius:999px;padding:4px 12px;cursor:pointer;min-height:30px}
  .su-seg button[aria-pressed="true"]{background:var(--accent-light);border-color:var(--accent);color:var(--accent3)}
  .su-teams{margin-top:8px;border:1px solid var(--border);border-radius:8px;max-height:150px;overflow:auto}
  .su-team{display:flex;align-items:center;gap:8px;padding:6px 10px;font-size:12.5px;border-bottom:1px solid var(--border)}
  .su-team:last-child{border-bottom:0}
  .su-team span{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .su-team small{color:var(--text3);font-size:11.5px}
  .su-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
  .su-card{display:flex;gap:8px;align-items:flex-start;border:1px solid var(--border);border-radius:8px;padding:9px 10px;cursor:pointer}
  .su-card:has(input:checked){border-color:var(--accent);background:var(--accent-light)}
  .su-card input{margin-top:2px;accent-color:var(--accent)}
  .su-card b{display:block;font-size:13px}
  .su-card small{display:block;font-size:11.5px;color:var(--text3)}
  .su-seats{display:flex;gap:14px;flex-wrap:wrap;font-size:13px}
  .su-seats label{display:flex;align-items:center;gap:6px;cursor:pointer}
  .su-seats input{accent-color:var(--accent)}
  .su select{font:inherit;font-size:13px;padding:6px 8px;border:1px solid var(--border2);border-radius:6px;background:var(--bg2);color:var(--text);min-width:200px}
  .su-impact{font-size:12.5px;color:var(--text2);background:var(--bg3);border-radius:8px;padding:8px 10px}
  .su-err{font-size:12.5px;color:var(--red)}
  .su-err ul{margin:4px 0 0;padding-left:18px}
  .su-f{display:flex;justify-content:flex-end;gap:8px;padding:12px 18px;border-top:1px solid var(--border)}
  .su-f button{font:inherit;font-size:13px;font-weight:600;border-radius:6px;padding:7px 14px;border:1px solid var(--border2);background:var(--bg2);color:var(--text);cursor:pointer}
  .su-f button.p{background:#18191f;border-color:#18191f;color:#fff}
  .su-f button:disabled{opacity:.45;cursor:not-allowed}
  @media (max-width:560px){.su-cards{grid-template-columns:1fr}}

  /* Phones and tablets get one card per defense instead of a seven-column
     row: the tick box and team on top, the rest under it, the button last. */
  @media (max-width:700px){
    .gl-filter{margin-left:0;width:100%}
    .gl-filter button{flex:1}
  }
  @media (max-width:1024px){
    .gl{max-height:none;overflow:visible}
    .gl-scroll{overflow:visible}
    .gl table,.gl tbody{display:block}
    .gl thead{display:none}
    .gl tbody tr{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:8px 12px;padding:14px;border-bottom:1px solid var(--border)}
    .gl tbody tr:last-of-type{border-bottom:0}
    .gl tbody tr:hover td{background:none}
    .gl td{display:block;padding:0;border:0;font-size:12.5px;min-width:0;width:auto}
    .gl td:nth-child(1){grid-column:1;grid-row:1}
    .gl td:nth-child(2){grid-column:2;grid-row:1}
    .gl td:nth-child(3){grid-column:3;grid-row:1;text-align:right;color:var(--text3);font-size:12px}
    .gl td:nth-child(4),.gl td:nth-child(5),.gl td:nth-child(6){grid-column:2 / -1}
    .gl td:nth-child(7){grid-column:1 / -1;text-align:left!important}
    .gl-team small{max-width:none;white-space:normal;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
    .gl-go{display:block;text-align:center;padding:10px 12px;font-size:13px}
    .gl-none td{grid-column:1 / -1}
    /* display:grid above would otherwise show rows the filter hid. */
    .gl tbody tr[hidden]{display:none}
  }
  @media (min-width:701px) and (max-width:1024px){
    .gl tbody tr{grid-template-columns:auto minmax(0,1.3fr) minmax(0,1fr) auto auto;align-items:center;gap:6px 18px;padding:14px 16px}
    .gl td:nth-child(1){grid-column:1;grid-row:1 / span 2}
    .gl td:nth-child(2){grid-column:2;grid-row:1 / span 2}
    .gl td:nth-child(3){grid-column:3;grid-row:1;text-align:left}
    .gl td:nth-child(5){grid-column:3;grid-row:2}
    .gl td:nth-child(4){grid-column:4;grid-row:1}
    .gl td:nth-child(6){grid-column:4;grid-row:2}
    .gl td:nth-child(7){grid-column:5;grid-row:1 / span 2;text-align:right!important}
    .gl-go{display:inline-block}
  }
</style>
  @include('partials.render-whole')
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
      @include('instructor.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="team-progress-grid">
      <div class="sk" style="height:180px;border-radius:14px"></div>
      <div class="sk" style="height:180px;border-radius:14px"></div>
      <div class="sk" style="height:180px;border-radius:14px"></div>
    </div>
  </div>

  <div id="real-content" style="display:none">
  <div class="content">

    @if (session('error'))
      <div class="card" style="border-color:var(--red);background:var(--red-bg);color:var(--red);padding:14px 18px;margin-bottom:16px;font-size:13px">
        {{ session('error') }}
      </div>
    @endif

    @php
      $chaired = $rows->where('chair', true);
      $methodLabel = ['individual' => 'Individual', 'joint' => 'Joint', 'mixed' => 'Mixed'];
    @endphp
    @if($rows->isEmpty())
      <div style="text-align:center;padding:64px 24px">
        <div style="margin-bottom:16px;opacity:0.3">
          <svg viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        </div>
        <div style="font-size:18px;font-weight:600;color:var(--text2);margin-bottom:8px">No Teams Assigned Yet</div>
        <div style="font-size:13px;color:var(--text3);max-width:400px;margin:0 auto">You haven't been placed on a defense panel yet. Once an admin assigns you to a team's panel, it will show up here.</div>
      </div>
    @else
      @php $toGrade = $rows->whereNotIn('status', ['submitted', 'abstained'])->count(); @endphp
      <section class="gl" aria-label="Defenses to grade">
        <div class="gl-head">
          <b>Your defenses</b>
          <span class="sub">{{ $toGrade }} to grade · {{ $rows->count() - $toGrade }} done</span>
          <div class="gl-filter" role="group" aria-label="Show">
            <button type="button" data-show="all" aria-pressed="true">All</button>
            <button type="button" data-show="todo" aria-pressed="false">To grade</button>
            <button type="button" data-show="done" aria-pressed="false">Done</button>
          </div>
          @if ($chaired->isNotEmpty())
            <button type="button" class="gl-setup" id="gl-setup-open"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/></svg>Grading setup</button>
          @endif
        </div>
        <div class="gl-scroll">
          <table data-export="Grading">
            <thead><tr><th class="gl-sel">Select</th><th>Team &amp; defense</th><th>Schedule</th><th>Your role</th><th>Method</th><th>Your grading</th><th>Action</th></tr></thead>
            <tbody id="grading-tbody">
              @foreach($rows as $row)
                @php
                  $url = route('instructor.grading.show', $row->defense_id);
                  // Abstaining finishes a panelist's part as much as scoring does.
                  $done = in_array($row->status, ['submitted', 'abstained'], true);
                  $isRedefense = ($row->attempt ?? 1) > 1;
                @endphp
                @php $locked = $row->decided ? 'Decided' : ($row->started ? 'Grading started' : null); @endphp
                <tr data-state="{{ $done ? 'done' : 'todo' }}">
                  <td class="gl-sel">
                    @if ($row->chair && ! $locked)
                      <input type="checkbox" data-select="{{ $row->defense_id }}" aria-label="Select {{ $row->team_name }}">
                    @endif
                  </td>
                  <td>
                    <div class="gl-team">
                      <span class="gl-av" aria-hidden="true">{{ strtoupper(substr($row->team_name, 0, 2)) }}</span>
                      <span style="min-width:0"><b>{{ $row->team_name }}</b><small title="{{ $row->project_title }}">{{ $row->project_title ?? 'No title set' }}</small><small>{{ $row->defense_type }}@if($isRedefense) · Redefense @endif</small></span>
                    </div>
                  </td>
                  <td class="gl-num">
                    {{ $row->schedule_date->format('M j, Y') }}
                    <div class="gl-muted">
                      @if($row->schedule_date->format('H:i') !== '00:00')
                        {{ $row->schedule_date->format('g:i A') }}–{{ ($row->end_date ?? $row->schedule_date->copy()->addHours(2))->format('g:i A') }} ·
                      @endif
                      {{ $row->venue }}
                    </div>
                  </td>
                  <td>{{ $row->panel_role }}</td>
                  <td class="gl-method">
                    <b>{{ $methodLabel[$row->method] ?? 'Individual' }}</b>
                    @if ($row->recorder)
                      <small>Recorder: {{ $row->recorder }}</small>
                    @elseif ($row->method !== 'individual')
                      <small>You grade on your own</small>
                    @endif
                  </td>
                  <td>
                    @if($row->joined && ! $done)
                      <span class="badge badge-amber" style="font-size:10px">Grading for you</span>
                    @elseif($row->status === 'abstained')
                      <span class="badge badge-gray" style="font-size:10px">Abstained</span>
                    @elseif($done)
                      <span class="badge badge-green" style="font-size:10px">Submitted{{ $row->total_score !== null ? ' · '.rtrim(rtrim(number_format((float) $row->total_score, 2), '0'), '.') : '' }}</span>
                    @elseif($row->status === 'draft')
                      <span class="badge badge-amber" style="font-size:10px">Draft saved</span>
                    @else
                      <span class="badge badge-gray" style="font-size:10px">Not started</span>
                    @endif
                  </td>
                  <td style="text-align:right"><a class="gl-go {{ $done || $row->joined ? '' : 'primary' }}" href="{{ $url }}">{{ $done ? 'Open' : ($row->joined ? 'View' : ($row->status === 'draft' ? 'Continue' : 'Grade')) }}</a></td>
                </tr>
              @endforeach
              <tr class="gl-none" hidden><td colspan="7" class="gl-empty">Nothing here.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    @endif

  </div>
  </div>
</div>

@include('instructor.partials.skeleton-toggle')
@if ($chaired->isNotEmpty())
<dialog class="su" id="su" aria-labelledby="su-title">
  <form method="dialog">
    <div class="su-h"><b id="su-title">Grading setup</b><p>Set the grading method for teams you chair.</p></div>
    <div class="su-b">
      <section>
        <h3>Apply to</h3>
        <div class="su-seg" role="group" aria-label="Apply to">
          <button type="button" data-scope="all" aria-pressed="true">My chaired teams</button>
          <button type="button" data-scope="selected" aria-pressed="false">Selected teams</button>
        </div>
        <div class="su-teams" id="su-teams"></div>
      </section>
      <section>
        <h3>Grading method</h3>
        <div class="su-cards">
          <label class="su-card"><input type="radio" name="method" value="individual" checked><span><b>Individual</b><small>3 separate rubrics</small></span></label>
          <label class="su-card"><input type="radio" name="method" value="joint"><span><b>Joint</b><small>1 agreed rubric</small></span></label>
          <label class="su-card"><input type="radio" name="method" value="mixed"><span><b>Mixed</b><small>2 shared + 1 individual</small></span></label>
        </div>
      </section>
      <section id="su-shared" hidden>
        <h3>Shared rubric group</h3>
        <div class="su-seats">
          @foreach (['Panel Chair', 'Panel Member 1', 'Panel Member 2'] as $seat)
            <label><input type="checkbox" name="shared" value="{{ $seat }}" @checked($seat !== 'Panel Member 2')> {{ $seat }}</label>
          @endforeach
        </div>
      </section>
      <section id="su-rec" hidden>
        <h3>Recorder</h3>
        <select id="su-recorder" aria-label="Recorder"></select>
      </section>
      <div class="su-impact" id="su-impact"></div>
      <div class="su-err" id="su-err" hidden></div>
    </div>
    <div class="su-f">
      <button type="button" id="su-cancel">Cancel</button>
      <button type="button" class="p" id="su-apply">Apply</button>
    </div>
  </form>
</dialog>
<script type="application/json" id="su-data">{!! Js::encode($chaired->map(fn ($r) => ['id' => $r->defense_id, 'team' => $r->team_name, 'type' => $r->defense_type, 'method' => $methodLabel[$r->method] ?? 'Individual', 'locked' => $r->decided ? 'Decided' : ($r->started ? 'Grading started' : null)])->values()) !!}</script>
@endif

<script nonce="{{ Vite::cspNonce() }}">
/* Grade Setup: the Panel Chair sets who grades on many defenses at once. */
(function () {
  var dlg = document.getElementById('su');
  if (!dlg) return;
  var teams = JSON.parse(document.getElementById('su-data').textContent);
  var SEATS = ['Panel Chair', 'Panel Member 1', 'Panel Member 2'];
  var scope = 'all', done = false;
  var $ = function (id) { return document.getElementById(id); };
  var esc = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
  var selected = function () { return Array.from(document.querySelectorAll('[data-select]:checked')).map(function (c) { return +c.dataset.select; }); };
  var method = function () { return dlg.querySelector('[name=method]:checked').value; };
  var shared = function () { return Array.from(dlg.querySelectorAll('[name=shared]:checked')).map(function (c) { return c.value; }); };
  var plural = function (n, one, many) { return n + ' ' + (n === 1 ? one : many); };

  function inScope() {
    var sel = selected();
    return teams.filter(function (t) { return scope === 'all' || sel.indexOf(t.id) !== -1; });
  }

  function render() {
    var m = method(), list = inScope(), open = list.filter(function (t) { return !t.locked; });
    dlg.querySelectorAll('[data-scope]').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.scope === scope); });
    $('su-teams').innerHTML = list.length ? list.map(function (t) {
      return '<div class="su-team"><span><b>' + esc(t.team) + '</b> <small>' + esc(t.type) + ' · now ' + esc(t.method) + '</small></span>'
        + (t.locked
          ? '<span class="badge badge-gray" style="font-size:10px">Locked · ' + esc(t.locked) + '</span>'
          : '<span class="badge badge-green" style="font-size:10px">Available</span>') + '</div>';
    }).join('') : '<div class="su-team"><small>Tick teams in the list first.</small></div>';

    $('su-shared').hidden = m !== 'mixed';
    $('su-rec').hidden = m === 'individual';
    var seats = m === 'mixed' ? shared() : SEATS;
    var keep = $('su-recorder').value;
    $('su-recorder').innerHTML = seats.map(function (s) { return '<option>' + esc(s) + '</option>'; }).join('');
    if (seats.indexOf(keep) !== -1) $('su-recorder').value = keep;

    var mixedOk = m !== 'mixed' || shared().length === 2;
    var locked = list.length - open.length;
    $('su-impact').textContent = !mixedOk ? 'Pick exactly two seats for the shared grade.'
      : plural(open.length, 'team', 'teams') + ' will change.' + (locked ? ' ' + plural(locked, 'locked team stays', 'locked teams stay') + ' as before.' : '');
    $('su-apply').disabled = !open.length || !mixedOk;
    $('su-apply').textContent = 'Apply to ' + plural(open.length, 'team', 'teams');
  }

  $('gl-setup-open').addEventListener('click', function () {
    scope = selected().length ? 'selected' : 'all';
    done = false;
    $('su-err').hidden = true;
    $('su-apply').hidden = false;
    $('su-cancel').textContent = 'Cancel';
    render();
    dlg.showModal();
  });
  dlg.querySelectorAll('[data-scope]').forEach(function (b) { b.addEventListener('click', function () { scope = b.dataset.scope; render(); }); });
  dlg.addEventListener('change', render);
  dlg.addEventListener('close', function () { if (done) location.reload(); });
  $('su-cancel').addEventListener('click', function () { dlg.close(); });

  $('su-apply').addEventListener('click', function () {
    var m = method();
    var body = { defense_ids: inScope().filter(function (t) { return !t.locked; }).map(function (t) { return t.id; }), method: m };
    if (m === 'mixed') body.shared = shared();
    if (m !== 'individual') body.recorder = $('su-recorder').value;
    $('su-apply').disabled = true;
    fetch(@json(route('instructor.grading.setup')), {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
      body: JSON.stringify(body)
    }).then(function (r) { return r.json(); }).then(function (res) {
      var skipped = (res.data && res.data.skipped) || [];
      if (res.success && !skipped.length) { location.reload(); return; }
      done = !!res.success;
      $('su-err').hidden = false;
      $('su-err').innerHTML = esc(res.message || 'Something went wrong.')
        + (skipped.length ? '<ul>' + skipped.map(function (s) { return '<li><b>' + esc(s.team) + '</b>: ' + esc(s.reason) + '</li>'; }).join('') + '</ul>' : '');
      if (done) { $('su-apply').hidden = true; $('su-cancel').textContent = 'Done'; } else { render(); }
    }).catch(function () {
      $('su-err').hidden = false;
      $('su-err').textContent = 'Could not save. Check your connection and try again.';
      render();
    });
  });
})();

document.querySelectorAll('.gl-filter button').forEach(function (b) {
  b.addEventListener('click', function () {
    document.querySelectorAll('.gl-filter button').forEach(function (x) { x.setAttribute('aria-pressed', x === b); });
    var shown = 0;
    document.querySelectorAll('.gl tbody tr[data-state]').forEach(function (tr) {
      tr.hidden = b.dataset.show !== 'all' && tr.dataset.state !== b.dataset.show;
      if (!tr.hidden) shown++;
    });
    document.querySelector('.gl-none').hidden = shown > 0;
  });
});

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
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>