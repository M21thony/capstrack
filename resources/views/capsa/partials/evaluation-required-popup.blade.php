{{-- Tells a CaPSA that a defense is scheduled in their sections with no
     expert evaluation for its stage. Leaders owe an evaluation only if it
     exists before the defense is decided, so this asks as soon as there is a
     date. Not on the Expert Evaluations pages themselves; "Later" quiets it
     on this device for four hours, until another stage needs one. --}}
@php
  $missing = $missing ?? collect();
  $onEvaluations = str_starts_with((string) request()->route()?->getName(), 'capsa.evaluations');
@endphp
@if($missing->isNotEmpty() && ! $onEvaluations)
@php $missingKey = md5($missing->pluck('stage')->implode('|')); @endphp
<style nonce="{{ Vite::cspNonce() }}">
  #erp-overlay{position:fixed;inset:0;z-index:1100;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(24,25,31,.5);backdrop-filter:blur(3px);opacity:0;visibility:hidden;transition:opacity .2s,visibility .2s}
  #erp-overlay.active{opacity:1;visibility:visible}
  #erp-card{position:relative;width:460px;max-width:100%;max-height:calc(100dvh - 32px);overflow:auto;background:#fff;color:var(--text);border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.18),0 8px 24px rgba(0,0,0,.12);padding:24px;animation:erpIn .2s ease;font-family:'DM Sans','DM Sans Fallback',sans-serif}
  @keyframes erpIn{from{opacity:0;transform:translateY(10px) scale(.985)}to{opacity:1;transform:none}}
  #erp-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:rgba(201,168,44,.16);color:#b8961c;margin-bottom:14px}
  #erp-title{font-size:17px;font-weight:700;letter-spacing:-.2px}
  #erp-sub{font-size:13px;color:var(--text2);margin-top:6px;line-height:1.55}
  #erp-sub b{color:var(--text)}
  #erp-list{list-style:none;margin:16px 0 0;padding:0;display:flex;flex-direction:column;gap:8px}
  #erp-list li{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:var(--bg3)}
  #erp-list b{display:block;font-size:13px}
  #erp-list span{display:block;font-size:12px;color:var(--text2);margin-top:2px}
  #erp-list form{flex-shrink:0}
  .erp-std{min-height:32px;padding:0 10px;border-radius:8px;border:1px solid var(--border);background:#fff;font:inherit;font-size:12px;font-weight:700;color:var(--text);cursor:pointer}
  .erp-std:hover{border-color:var(--accent);background:var(--accent-light)}
  #erp-actions{display:flex;gap:8px;margin-top:20px}
  #erp-actions a,#erp-actions button{flex:1;display:inline-flex;align-items:center;justify-content:center;min-height:42px;border-radius:10px;font:inherit;font-size:13px;font-weight:700;text-decoration:none;cursor:pointer}
  #erp-later{background:var(--bg3);color:var(--text2);border:1px solid var(--border)}
  #erp-later:hover{background:var(--bg4);color:var(--text)}
  #erp-go{background:var(--accent,#c9a82c);color:#18191f;border:0}
  #erp-go:hover{filter:brightness(1.06)}
  @media (max-width:480px){#erp-overlay{align-items:flex-end;padding:0}#erp-card{border-radius:20px 20px 0 0;padding:20px 16px}#erp-list li{flex-direction:column;align-items:stretch}}
</style>
<div id="erp-overlay" role="dialog" aria-modal="true" aria-labelledby="erp-title" data-key="{{ $missingKey }}">
  <div id="erp-card">
    <div id="erp-icon" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
    </div>
    <div id="erp-title">Expert evaluation required</div>
    <div id="erp-sub">
      A defense is scheduled in your sections with no expert evaluation for it. When a team passes, its leader answers your questions about the experts. <b>Set it up before the results are in</b>, or those leaders will not be asked.
    </div>
    <ul id="erp-list">
      @foreach($missing as $m)
        <li>
          <div><b>{{ $m->stage }}</b><span>{{ $m->teams }} {{ $m->teams === 1 ? 'team' : 'teams' }} · from {{ $m->first?->format('M j, Y') }}</span></div>
          <form method="POST" action="{{ route('capsa.evaluations.store') }}">
            @csrf
            <input type="hidden" name="standard" value="1">
            <input type="hidden" name="defense_type" value="{{ $m->stage }}">
            <button type="submit" class="erp-std">Use the 5 standard questions</button>
          </form>
        </li>
      @endforeach
    </ul>
    <div id="erp-actions">
      <button type="button" id="erp-later">Remind me later</button>
      <a id="erp-go" href="{{ route('capsa.evaluations.create', ['stage' => $missing->first()->stage]) }}">Create new evaluation</a>
    </div>
  </div>
</div>
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var overlay = document.getElementById('erp-overlay');
  if (!overlay) { return; }
  var KEY = 'capstrack.evaluationSetupSnooze';
  var key = overlay.dataset.key;
  var snooze = null;
  try { snooze = JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) { snooze = null; }
  if (snooze && snooze.key === key && snooze.until > Date.now()) { return; }

  function later() {
    try { localStorage.setItem(KEY, JSON.stringify({ key: key, until: Date.now() + 4 * 60 * 60 * 1000 })); } catch (e) { /* the popup simply comes back next page */ }
    overlay.classList.remove('active');
  }
  document.getElementById('erp-later').addEventListener('click', later);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) { later(); } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && overlay.classList.contains('active')) { later(); } });
  // After the page settles, and after the portal tour if it is showing.
  var show = function () {
    setTimeout(function () {
      (window.afterPortalTour || function (fn) { fn(); })(function () { overlay.classList.add('active'); }, function () { return overlay.classList.contains('active'); });
    }, 700);
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', show, { once: true });
  else show();
})();
</script>
@endif
