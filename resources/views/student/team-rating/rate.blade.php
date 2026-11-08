{{--
  Team Rating › Rate one teammate: every criterion of the rubric, a row of
  radios per activity the rating covers. Each pick saves as a draft; the
  ratings only reach the CaPSA from Review & submit on the Team Rating tab.
--}}
@php
  use App\Http\Controllers\TeamRatingController as TR;
  use App\Support\PeerRatingRubric;
  $items = $form->items;
  $idx = $mates->search(fn ($m) => (int) $m->student->student_id === (int) $current->student_id);
  $prev = $mates[$idx - 1] ?? null;
  $next = $mates[$idx + 1] ?? null;
  // On the last teammate, "Review & submit" goes to whoever still has gaps first.
  $left = $mates->first(fn ($m, $i) => $i !== $idx && $m->done < $need);
  $finish = $left
      ? route('student.team_rating.rate', [$form, $left->student->student_id])
      : route('student.team', ['tab' => 'rating', 'review' => $form->id]);
  $done = $mates[$idx]->done;
  $rateUrl = fn ($m) => route('student.team_rating.rate', [$form, $m->student->student_id]);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content" />
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Rate {{ TR::name($current) }} - CapsTrack</title>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
@vite('resources/student-ui/styles.css')
<x-dashboard.styles />
@include('partials.render-whole')
<style nonce="{{ Vite::cspNonce() }}">

  .trr-crumb { font-size: 13px; color: var(--text3); margin-bottom: 14px; display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
  .trr-crumb a { color: var(--text2); text-decoration: none; font-weight: 500; }
  .trr-crumb a:hover { color: var(--accent3); text-decoration: underline; }
  .trr-crumb .cur { color: var(--text); font-weight: 600; }
  .trr { display: grid; grid-template-columns: 260px minmax(0, 1fr); gap: 16px; height: calc(100vh - 160px); height: calc(100dvh - 160px); min-height: 420px; }
  .trr-card { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; min-height: 0; }
  .trr-steps { overflow: auto; }
  .trr-step { display: flex; gap: 11px; align-items: center; padding: 12px 16px; border-top: 1px solid var(--border); border-left: 3px solid transparent; color: var(--text); text-decoration: none; }
  .trr-step:first-child { border-top: none; }
  .trr-step:hover { background: var(--bg3); }
  .trr-step.on { background: color-mix(in srgb, var(--accent) 12%, var(--bg2)); border-left-color: var(--accent); }
  .trr-step .s { font-size: 12px; color: var(--text2); }
  .trr-step .ok { margin-left: auto; color: var(--green); font-weight: 700; }
  .trr-av { width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; font-weight: 700; font-size: 13px; color: #fff; background: var(--navy-light, #272a36); flex-shrink: 0; }
  .trr-panel { display: flex; flex-direction: column; }
  .trr-head { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
  .trr-head .who b { font-size: 16px; color: var(--text); }
  .trr-head .who div { font-size: 12.5px; color: var(--text2); }
  .trr-req { color: var(--red); font-weight: 700; }
  .trr-tots { margin-left: auto; display: flex; gap: 16px; text-align: right; }
  .trr-tots div { display: flex; flex-direction: column; }
  .trr-tots span { font-size: 11.5px; color: var(--text3); }
  .trr-tots b { font-size: 18px; color: var(--text); font-variant-numeric: tabular-nums; }
  .trr-list { flex: 1; overflow: auto; position: relative; padding: 0 20px 14px; min-height: 0; }
  .trr-crit { padding: 14px 0; border-top: 1px solid var(--border); }
  .trr-crit:first-of-type { border-top: none; }
  .trr-crit.miss { background: var(--red-bg); margin: 0 -20px; padding: 14px 20px; border-left: 3px solid var(--red); }
  .trr-crit h3 { margin: 0 0 6px; font-size: 14px; display: flex; gap: 8px; align-items: baseline; color: var(--text); }
  .trr-crit h3 .n { color: var(--text3); font-weight: 500; font-size: 12px; }
  .trr-crit .err { color: var(--red); font-size: 12px; font-weight: 600; margin-left: auto; }
  .mx { display: grid; grid-template-columns: 170px repeat(5, minmax(56px, 96px)); align-items: center; }
  .mx-h { position: sticky; top: 0; background: var(--bg2); z-index: 1; font-size: 12px; color: var(--text2); padding: 8px 0; border-bottom: 1px solid var(--border); text-align: center; }
  .mx-h span:first-child { text-align: left; }
  .mx-h b { display: block; font-size: 14px; color: var(--text); }
  .mx .al { font-size: 13px; color: var(--text2); }
  .mx label { display: grid; place-items: center; min-height: 34px; cursor: pointer; border-radius: 6px; }
  .mx label:hover { background: var(--bg3); }
  .mx input { width: 17px; height: 17px; accent-color: #b8961f; margin: 0; cursor: pointer; }
  .mx input:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
  .mx.miss .al { color: var(--red); font-weight: 600; }
  .trr-foot { display: flex; align-items: center; gap: 12px; padding: 14px 20px; border-top: 1px solid var(--border); flex-wrap: wrap; font-size: 13px; color: var(--text2); }
  .trr-foot .saved { font-size: 12px; color: var(--text3); }
  .trr-foot .r { margin-left: auto; display: flex; gap: 8px; }
  .trr-btn { border: 1px solid var(--border); background: var(--bg2); color: var(--text); border-radius: 8px; padding: 8px 14px; font: 600 13px 'DM Sans', 'DM Sans Fallback', sans-serif; cursor: pointer; min-height: 36px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
  .trr-btn:hover { border-color: var(--text3); }
  .trr-btn.pri { background: var(--accent); border-color: var(--accent); color: #18191f; }
  .trr-btn.pri:hover { background: var(--accent2); border-color: var(--accent2); }
  .trr-btn.pri[aria-disabled="true"] { opacity: .5; cursor: not-allowed; }
  .trr-btn.off { opacity: .45; pointer-events: none; }
  .trr-btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
  .trr-dlg { border: none; border-radius: 14px; padding: 0; margin: auto; width: min(1100px, calc(100% - 32px)); max-height: calc(100vh - 32px); max-height: calc(100dvh - 32px); box-shadow: 0 20px 50px rgba(0, 0, 0, .25); color: var(--text); background: var(--bg2); }
  .trr-dlg::backdrop { background: rgba(20, 20, 24, .5); }
  .trr-dlg[open] { display: flex; flex-direction: column; }
  .trr-dlg .mh { padding: 18px 22px 14px; display: flex; align-items: flex-start; gap: 12px; border-bottom: 1px solid var(--border); }
  .trr-dlg .mh h3 { margin: 0; font-size: 17px; } .trr-dlg .mh p { margin: 4px 0 0; color: var(--text2); font-size: 13px; }
  .trr-dlg .x { margin-left: auto; border: none; background: none; font-size: 20px; cursor: pointer; color: var(--text3); width: 36px; height: 36px; border-radius: 8px; flex-shrink: 0; }
  .trr-dlg .x:hover { background: var(--bg4); }
  .trr-dlg .mb { padding: 18px 22px; overflow: auto; }
  .trr-dlg .mf { padding: 14px 22px; border-top: 1px solid var(--border); background: var(--bg3); display: flex; justify-content: flex-end; }
  .trr-dlg table { width: 100%; border-collapse: collapse; }
  .trr-dlg th { background: var(--navy-mid, #1e2028); color: #e9e9ee; font-weight: 600; text-align: left; padding: 10px 12px; font-size: 12px; white-space: nowrap; }
  .trr-dlg td { padding: 10px 12px; border-top: 1px solid var(--border); vertical-align: top; font-size: 12.5px; line-height: 1.45; color: var(--text2); }
  .trr-dlg td:first-child { font-weight: 600; color: var(--text); width: 160px; }
  .trr-toast { position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%); background: var(--red); color: #fff; padding: 10px 16px; border-radius: 10px; font-size: 13.5px; font-weight: 600; box-shadow: 0 8px 24px rgba(0, 0, 0, .2); z-index: 100; }
  @media (max-width: 900px) {
    .trr { grid-template-columns: minmax(0, 1fr); height: auto; }
    .trr-steps { max-height: 180px; }
    .trr-list { overflow: visible; }
  }
  @media (max-width: 600px) {
    .mx { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    .mx .al { grid-column: 1 / -1; padding-top: 6px; }
    .mx-h span:first-child { display: none; }
    .trr-tots { margin-left: 0; }
    .trr-dlg { width: 100%; max-width: 100%; height: 100%; max-height: 100%; border-radius: 0; }
  }
</style>
</head>
<body>
@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Team Management</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    <div class="trr-page">
      <nav class="trr-crumb" aria-label="Breadcrumb">
        <a href="{{ route('student.team') }}" data-trr-go>Team Management</a>›
        <a href="{{ route('student.team', ['tab' => 'rating']) }}" data-trr-go>Team Rating</a>›
        <a href="{{ route('student.team', ['tab' => 'rating']) }}" data-trr-go>{{ $form->name }}</a>›
        <span class="cur">Rate {{ TR::name($current) }}</span>
      </nav>

      <div class="trr">
        <nav class="trr-card trr-steps" aria-label="Teammates">
          @foreach ($mates as $i => $m)
            <a class="trr-step {{ $i === $idx ? 'on' : '' }}" href="{{ $rateUrl($m) }}" data-trr-go @if ($i === $idx) aria-current="page" @endif>
              <span class="trr-av" aria-hidden="true">{{ $m->student->avatarInitials() }}</span>
              <div><div style="font-weight:700">{{ TR::name($m->student) }}</div><div class="s" @if ($i === $idx) id="trr-step-s" @endif>{{ $m->done }} of {{ $need }} scores</div></div>
              @if ($m->done >= $need)<span class="ok" aria-label="done">✓</span>@endif
            </a>
          @endforeach
        </nav>

        <div class="trr-card trr-panel">
          <div class="trr-head">
            <span class="trr-av" aria-hidden="true">{{ $current->avatarInitials() }}</span>
            <div class="who">
              <b>{{ TR::name($current) }}</b>
              <div>{{ $current->s_role_name ?: 'Member' }} · teammate {{ $idx + 1 }} of {{ $mates->count() }} · <span class="trr-req">*</span> every score is required</div>
            </div>
            <button type="button" class="trr-btn" id="trr-rub-open">View full rubric</button>
            <div class="trr-tots" id="trr-tots" aria-label="Totals out of {{ PeerRatingRubric::MAX }}">
              @foreach ($items as $item)
                @php $p = $picks[$item->id]; @endphp
                <div><span>{{ $item->label }}</span><b data-tot="{{ $item->id }}">{{ PeerRatingRubric::isComplete($p) ? array_sum($p) : '—' }}</b></div>
              @endforeach
            </div>
          </div>

          <div class="trr-list" id="trr-list">
            <div class="mx mx-h" aria-hidden="true"><span>Activity</span>@foreach (PeerRatingRubric::LEVELS as $v => $w)<span><b>{{ $v }}</b>{{ $w }}</span>@endforeach</div>
            @foreach (array_keys(PeerRatingRubric::CRITERIA) as $ci => $crit)
              <div class="trr-crit" id="trr-c{{ $ci }}">
                <h3><span class="n">{{ $ci + 1 }}</span>{{ $crit }}<span class="trr-req" aria-hidden="true">*</span><span class="err" hidden>Score every activity</span></h3>
                @foreach ($items as $item)
                  <div class="mx" role="radiogroup" aria-required="true" aria-label="{{ $crit }}: {{ $item->label }}" id="trr-m{{ $ci }}-{{ $item->id }}">
                    <span class="al">{{ $item->label }}</span>
                    @foreach (PeerRatingRubric::LEVELS as $v => $w)
                      <label><input type="radio" name="c{{ $ci }}a{{ $item->id }}" value="{{ $v }}" data-ci="{{ $ci }}" data-item="{{ $item->id }}" @checked($picks[$item->id][$ci] === $v) aria-label="{{ $v }}, {{ $w }}"></label>
                    @endforeach
                  </div>
                @endforeach
              </div>
            @endforeach
          </div>

          <div class="trr-foot">
            <span id="trr-cnt"><b style="color:var(--text)">{{ $done }} of {{ $need }}</b> scores picked</span>
            <span class="saved" id="trr-saved">{{ $savedAt ? 'Draft saved '.\Illuminate\Support\Carbon::parse($savedAt)->format('g:i A') : 'Saved as you pick' }}</span>
            <div class="r">
              @if ($prev)
                <a class="trr-btn" href="{{ $rateUrl($prev) }}" data-trr-go>‹ Previous</a>
              @else
                <span class="trr-btn off" aria-disabled="true">‹ Previous</span>
              @endif
              <a class="trr-btn pri" id="trr-next" href="{{ $next ? $rateUrl($next) : $finish }}" data-left="{{ ! $next && $left ? TR::name($left->student) : '' }}" aria-disabled="{{ $done < $need ? 'true' : 'false' }}">{{ $next ? 'Next teammate ›' : 'Review & submit' }}</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<dialog class="trr-dlg" id="trr-rub" aria-labelledby="trr-rub-h">
  <div class="mh">
    <div><h3 id="trr-rub-h">Member's Participation Rubric</h3><p>Each criterion gets 10, 7, 5, 3 or 1 point, for every activity the rating covers.</p></div>
    <button class="x" type="button" data-trr-close aria-label="Close">×</button>
  </div>
  <div class="mb">
    <div style="overflow:auto">
      <table>
        <thead><tr><th>Criteria</th>@foreach (PeerRatingRubric::LEVELS as $v => $w)<th>{{ $w }} ({{ $v }})</th>@endforeach</tr></thead>
        <tbody>
          @foreach (PeerRatingRubric::CRITERIA as $crit => $descs)
            <tr><td>{{ $crit }}</td>@foreach ($descs as $d)<td>{{ $d }}</td>@endforeach</tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  <div class="mf"><button type="button" class="trr-btn pri" data-trr-close>Close</button></div>
</dialog>

<script nonce="{{ Vite::cspNonce() }}">
function toggleSidebar() {
    document.body.classList.toggle('sidebar-open');
    document.body.style.overflow = document.body.classList.contains('sidebar-open') ? 'hidden' : '';
}
function closeSidebar() {
    document.body.classList.remove('sidebar-open');
    document.body.style.overflow = '';
}
document.querySelectorAll('.nav-item').forEach(function (el) {
    el.addEventListener('click', function () { if (window.innerWidth <= 768) closeSidebar(); });
});

(function () {
  var N = {{ count(PeerRatingRubric::CRITERIA) }}, NEED = {{ $need }};
  var picks = @json($picks);
  var url = @json(route('student.team_rating.draft', [$form, $current->student_id]));
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var list = document.getElementById('trr-list');
  var next = document.getElementById('trr-next');
  var saved = document.getElementById('trr-saved');
  var timer = null, pending = null;

  function count() { var n = 0; Object.keys(picks).forEach(function (k) { picks[k].forEach(function (v) { if (v !== null) n++; }); }); return n; }

  function toast(msg) {
    var t = document.createElement('div'); t.className = 'trr-toast'; t.setAttribute('role', 'alert'); t.textContent = msg;
    document.body.appendChild(t); setTimeout(function () { t.remove(); }, 3000);
  }

  function save() {
    clearTimeout(timer); timer = null;
    saved.textContent = 'Saving…';
    pending = fetch(url, { method: 'PATCH', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ picks: picks }) })
      .then(function (r) { if (!r.ok) { throw new Error(); } return r.json(); })
      .then(function (j) { saved.textContent = 'Draft saved ' + j.savedAt; })
      .catch(function () { saved.textContent = 'Not saved. Check your connection.'; });
    return pending;
  }

  // Leaving the page waits for the last pick to be saved.
  function go(href) {
    (timer ? save() : (pending || Promise.resolve())).then(function () { location.href = href; }, function () { location.href = href; });
  }

  list.addEventListener('change', function (e) {
    var i = e.target; if (i.type !== 'radio') { return; }
    var ci = +i.dataset.ci, item = i.dataset.item;
    picks[item][ci] = +i.value;
    document.getElementById('trr-m' + ci + '-' + item).classList.remove('miss');
    var c = document.getElementById('trr-c' + ci);
    if (!c.querySelector('.mx.miss')) { c.classList.remove('miss'); c.querySelector('.err').hidden = true; }
    var full = picks[item].every(function (v) { return v !== null; });
    document.querySelector('[data-tot="' + item + '"]').textContent = full ? picks[item].reduce(function (a, b) { return a + b; }, 0) : '—';
    var n = count();
    document.getElementById('trr-cnt').innerHTML = '<b style="color:var(--text)">' + n + ' of ' + NEED + '</b> scores picked';
    document.getElementById('trr-step-s').textContent = n + ' of ' + NEED + ' scores';
    next.setAttribute('aria-disabled', String(n < NEED));
    clearTimeout(timer); timer = setTimeout(save, 400);
  });

  document.querySelectorAll('[data-trr-go]').forEach(function (a) {
    a.addEventListener('click', function (e) { if (e.ctrlKey || e.metaKey || e.shiftKey) { return; } e.preventDefault(); go(a.href); });
  });

  next.addEventListener('click', function (e) {
    e.preventDefault();
    var miss = [];
    for (var ci = 0; ci < N; ci++) { Object.keys(picks).forEach(function (item) { if (picks[item][ci] === null) { miss.push([ci, item]); } }); }
    if (miss.length) {
      miss.forEach(function (m) {
        var c = document.getElementById('trr-c' + m[0]); c.classList.add('miss'); c.querySelector('.err').hidden = false;
        document.getElementById('trr-m' + m[0] + '-' + m[1]).classList.add('miss');
      });
      list.scrollTo({ top: document.getElementById('trr-c' + miss[0][0]).offsetTop - 44, behavior: 'smooth' });
      document.querySelector('#trr-m' + miss[0][0] + '-' + miss[0][1] + ' input').focus({ preventScroll: true });
      toast('Pick ' + miss.length + ' more ' + (miss.length > 1 ? 'scores' : 'score') + ' first');
      return;
    }
    if (next.dataset.left) { toast(next.dataset.left + ' still needs scores'); }
    go(next.href);
  });

  // Coming back to a half-done teammate: start at the first unscored criterion.
  if (count() > 0) {
    for (var c = 1; c < N; c++) {
      if (Object.keys(picks).some(function (k) { return picks[k][c] === null; })) { list.scrollTop = document.getElementById('trr-c' + c).offsetTop - 44; break; }
    }
  }

  var rub = document.getElementById('trr-rub');
  document.getElementById('trr-rub-open').addEventListener('click', function () { rub.showModal(); });
  rub.querySelectorAll('[data-trr-close]').forEach(function (x) { x.addEventListener('click', function () { rub.close(); }); });
  rub.addEventListener('click', function (e) { if (e.target === rub) { rub.close(); } });
})();
</script>
@include('partials.app-scrollbar')
</body>
</html>
