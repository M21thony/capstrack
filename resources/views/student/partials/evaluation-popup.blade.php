{{-- Nudges a team leader whose team passed a defense but has not yet
     evaluated the experts for it. Shown on every student page except the
     Evaluations tab itself, open from the page's first paint; "Remind me
     later" quiets it on this device for an hour. --}}
@php
  $evaluationsOwed = $evaluationsOwed ?? collect();
  $onEvaluationsTab = request()->routeIs('student.experts', 'student.consultation') && request('tab') === 'evaluations';
  // Still to see the portal tour: it goes first, and this waits for it.
  $tourFirst = (bool) auth('web')->user()?->needsPortalTour();
@endphp
@if($evaluationsOwed->isNotEmpty() && ! $onEvaluationsTab)
@php
  $owedPhases = $evaluationsOwed->groupBy('defense_id')->map(fn ($rows) => [
    'name' => $rows->first()->stage,
    'experts' => $rows->map(fn ($r) => $r->expert.' · '.$r->role_label)->unique()->values(),
  ])->values();
  // Changes whenever something new is owed, so a fresh defense brings the
  // popup back even inside a snooze.
  $owedKey = md5($evaluationsOwed->map(fn ($r) => $r->defense_id.'-'.$r->expert_id.'-'.$r->role)->implode(','));
@endphp
<style nonce="{{ Vite::cspNonce() }}">
  #evp-overlay{position:fixed;inset:0;z-index:1100;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(24,25,31,.5);backdrop-filter:blur(3px);opacity:0;visibility:hidden;transition:opacity .2s,visibility .2s}
  #evp-overlay.active{opacity:1;visibility:visible}
  #evp-overlay.evp-now,#evp-overlay.evp-now #evp-card{transition:none;animation:none}
  #evp-card{position:relative;width:440px;max-width:100%;max-height:calc(100dvh - 32px);overflow:auto;background:var(--bg2);color:var(--text);border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.18),0 8px 24px rgba(0,0,0,.12);padding:24px;animation:evpIn .2s ease}
  @keyframes evpIn{from{opacity:0;transform:translateY(10px) scale(.985)}to{opacity:1;transform:none}}
  #evp-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:rgba(201,168,44,.16);color:#b8961c;margin-bottom:14px}
  #evp-title{font-size:17px;font-weight:700;letter-spacing:-.2px}
  #evp-sub{font-size:13px;color:var(--text2);margin-top:6px;line-height:1.55}
  #evp-sub b{color:var(--text)}
  #evp-list{list-style:none;margin:16px 0 0;padding:0;display:flex;flex-direction:column;gap:8px}
  #evp-list li{padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:var(--bg3)}
  #evp-list b{display:block;font-size:13px}
  #evp-list span{display:block;font-size:12px;color:var(--text2);margin-top:2px}
  #evp-actions{display:flex;gap:8px;margin-top:20px}
  #evp-actions a,#evp-actions button{flex:1;display:inline-flex;align-items:center;justify-content:center;min-height:42px;border-radius:10px;font:inherit;font-size:13px;font-weight:700;text-decoration:none;cursor:pointer}
  #evp-later{background:var(--bg3);color:var(--text2);border:1px solid var(--border)}
  #evp-later:hover{background:var(--bg4);color:var(--text)}
  #evp-keep{margin:12px 0 0;font-size:11.5px;color:var(--text3);text-align:center;line-height:1.45}
  #evp-go{background:var(--accent,#c9a82c);color:#18191f;border:0}
  #evp-go:hover{filter:brightness(1.06)}
  @media (max-width:480px){#evp-overlay{align-items:flex-end;padding:0}#evp-card{border-radius:20px 20px 0 0;padding:20px 16px}}
</style>
<div id="evp-overlay" role="dialog" aria-modal="true" aria-labelledby="evp-title" data-key="{{ $owedKey }}">
  <div id="evp-card">
    <div id="evp-icon" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
    </div>
    <div id="evp-title">Time to evaluate your {{ \App\Support\ExpertEvaluations::whom($evaluationsOwed) }}</div>
    <div id="evp-sub">
      Your team passed {{ $owedPhases->pluck('name')->implode(' and ') }}.
      As team leader, answer your CaPSA/TSA's short evaluation of each one, 1 to 5 stars per question. It is anonymous. <b>The next phase stays locked until you do.</b>
    </div>
    <ul id="evp-list">
      @foreach($owedPhases->take(3) as $phase)
        <li><b>{{ $phase['name'] }}</b>@foreach($phase['experts'] as $who)<span>{{ $who }}</span>@endforeach</li>
      @endforeach
      @if($owedPhases->count() > 3)
        <li><span>and {{ $owedPhases->count() - 3 }} more defense{{ $owedPhases->count() - 3 === 1 ? '' : 's' }}</span></li>
      @endif
    </ul>
    <div id="evp-actions">
      <button type="button" id="evp-later">Remind me later</button>
      <a id="evp-go" href="{{ route('student.experts', ['tab' => 'evaluations']) }}">Evaluate now</a>
    </div>
    <p id="evp-keep">It stays at the top of your notifications until it is done.</p>
  </div>
</div>
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var overlay = document.getElementById('evp-overlay');
  if (!overlay) { return; }
  var KEY = 'capstrack.evaluationSnooze';
  var key = overlay.dataset.key;
  var snooze = null;
  try { snooze = JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) { snooze = null; }
  if (snooze && snooze.key === key && snooze.until > Date.now()) { return; }

  function later() {
    try { localStorage.setItem(KEY, JSON.stringify({ key: key, until: Date.now() + 60 * 60 * 1000 })); } catch (e) { /* the popup simply comes back next page */ }
    overlay.classList.remove('active');
  }
  document.getElementById('evp-later').addEventListener('click', later);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) { later(); } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && overlay.classList.contains('active')) { later(); } });
  // Open from the first paint, with no fade: it waited 700ms and faded in
  // before, so every page came up bright and then dimmed under it - a flash
  // on each click while it was owed. Shown at once, the screen stays dim from
  // one page to the next.
  // Except for an account still to see the portal tour: the tour comes first,
  // and this not even for a frame - only once the tour is closed
  // (partials/portal-tour).
  if (@json($tourFirst)) {
    document.addEventListener('DOMContentLoaded', function () {
      (window.afterPortalTour || function (fn) { fn(); })(function () { overlay.classList.add('active'); }, function () { return overlay.classList.contains('active'); });
    });
    return;
  }
  overlay.classList.add('evp-now', 'active');
})();
</script>
@endif
