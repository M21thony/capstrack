@php
    // Someone else on the panel grades for this panelist: this is that grade.
    $grader = $grade->gradedBy;
    $criteriaCount = $rubric->criteria()->count();
    $scored = count($grade->scores ?? []);
    $turnedIn = in_array($grade->status, [\App\Models\PanelGrade::SUBMITTED, \App\Models\PanelGrade::ABSTAINED], true);
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $status = match ($grade->status) {
        \App\Models\PanelGrade::SUBMITTED => 'Submitted · counts as your grade',
        \App\Models\PanelGrade::ABSTAINED => 'Abstained for the panel',
        default => 'In progress',
    };
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
<style nonce="{{ Vite::cspNonce() }}">
  .gj{flex:1;min-height:0;display:flex;flex-direction:column;background:var(--bg);color:var(--text);font-variant-numeric:tabular-nums;overflow:auto}
  .gj-top{flex:0 0 auto;display:flex;align-items:center;gap:14px;padding:10px 24px;background:var(--bg2);border-bottom:1px solid var(--border)}
  .gj-iconbtn{flex:0 0 40px;width:40px;height:40px;border-radius:8px;border:1px solid var(--border);background:var(--bg2);display:flex;align-items:center;justify-content:center;color:var(--text2);text-decoration:none;cursor:pointer}
  .gj-iconbtn svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
  .gj-burger{display:none}
  @media (min-width:769px) and (max-width:1024px){.gj-burger{display:flex}}
  .gj-top h1{margin:0;font-size:17px;font-weight:700;min-width:0}
  .gj-top h1 span{font-weight:500;color:var(--text2)}
  .gj-top small{display:block;font-size:12.5px;color:var(--text3);margin-top:2px}
  .gj-body{flex:1;display:flex;align-items:center;justify-content:center;padding:24px 16px}
  .gj-card{width:min(460px,100%);box-sizing:border-box;background:var(--bg2);border:1px solid var(--border);border-radius:16px;padding:28px;text-align:center}
  .gj-av{width:56px;height:56px;margin:0 auto;border-radius:50%;background:#18191f;color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700}
  .gj-card h2{margin:14px 0 2px;font-size:20px;font-weight:700}
  .gj-card .st{margin:0;font-size:13px;color:var(--text3)}
  .gj-card .st.in{color:#15803d;font-weight:600}
  .gj-segs{display:flex;gap:3px;margin:20px 0 8px}
  .gj-segs span{flex:1;height:6px;border-radius:2px;background:var(--border)}
  .gj-segs span.on{background:var(--accent)}
  .gj-segs.in span.on{background:var(--green)}
  .gj-nums{display:flex;justify-content:space-between;font-size:13px;color:var(--text2)}
  .gj-nums b{color:var(--text)}
  .gj-leave{font:inherit;font-size:14px;font-weight:600;display:flex;align-items:center;justify-content:center;width:100%;min-height:44px;margin-top:24px;border-radius:8px;border:1px solid var(--border);background:var(--bg2);color:var(--text2);cursor:pointer}
  .gj-leave:hover:not(:disabled){border-color:var(--border2);color:var(--text)}
  .gj-leave:disabled{opacity:.6;cursor:default}
  .gj-err{margin:12px 0 0;font-size:13px;color:var(--red)}
  .gj-err[hidden]{display:none}
  .gj-decided{margin:20px 0 0;font-size:13px;color:var(--text2)}
  .gj :focus-visible{outline:2px solid var(--accent3);outline-offset:2px}
</style>
</head>
<body>

@include('instructor.partials.sidebar', ['currentPage' => 'grading', 'sidebarRail' => true])
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="gj">
    <header class="gj-top">
      <button type="button" class="gj-iconbtn gj-burger" onclick="toggleSidebar()" aria-label="Open menu">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      </button>
      <a href="{{ route('instructor.grading') }}" class="gj-iconbtn" aria-label="Back to Team Grading">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
      </a>
      <div>
        <h1>{{ $defense->team?->team_name ?? 'Unknown Team' }} <span>— {{ $defense->team?->project_title ?? 'No title' }}</span></h1>
        <small>{{ $defense->defense_type }} · {{ $panelRole }}</small>
      </div>
    </header>

    <div class="gj-body">
      <section class="gj-card">
        <div class="gj-av" aria-hidden="true">{{ $grader?->avatarInitials() ?? '?' }}</div>
        <h2>{{ $grader?->name ?? 'Another panelist' }} is grading for you</h2>
        <p class="st {{ $turnedIn ? 'in' : '' }}">{{ $status }}</p>

        @unless ($grade->status === \App\Models\PanelGrade::ABSTAINED)
          <div class="gj-segs {{ $turnedIn ? 'in' : '' }}" role="progressbar" aria-label="Criteria scored" aria-valuemin="0" aria-valuemax="{{ $criteriaCount }}" aria-valuenow="{{ $scored }}">
            @for ($i = 0; $i < $criteriaCount; $i++)<span class="{{ $i < $scored ? 'on' : '' }}"></span>@endfor
          </div>
          <div class="gj-nums">
            <span>{{ $scored }} of {{ $criteriaCount }} scored</span>
            <span><b>{{ $grade->total_score !== null ? $fmt($grade->total_score) : '0' }}</b> / {{ $fmt($rubric->total_points) }}</span>
          </div>
        @endunless

        @if ($defense->hasOutcome())
          <p class="gj-decided">Panel decision: <b>{{ $defense->outcomeLabel() }}</b></p>
        @else
          <button type="button" class="gj-leave" id="gj-leave">Grade on my own instead</button>
          <p class="gj-err" id="gj-err" role="alert" hidden></p>
        @endif
      </section>
    </div>
  </div>
</div>

<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var btn = document.getElementById('gj-leave');
  if (!btn) return;
  btn.addEventListener('click', function () {
    btn.disabled = true;
    fetch('{{ route('instructor.grading.join.leave', $defense->defense_id) }}', {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.success) {
          var err = document.getElementById('gj-err');
          err.textContent = res.message || 'Could not leave.'; err.hidden = false; btn.disabled = false;
          return;
        }
        window.location.reload();
      })
      .catch(function () { btn.disabled = false; });
  });
})();

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
  document.body.classList.add('sidebar-open');
}
function closeSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.remove('mobile-open'); sidebarOverlay.classList.remove('open');
  document.body.classList.remove('sidebar-open');
}
</script>
@include('partials.escape-closes-modals')
</body>
</html>
