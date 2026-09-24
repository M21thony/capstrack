{{--
  Signs the person out after config('session.idle_timeout') minutes of doing
  nothing, on every portal page (included once from partials/mobile-tabbar,
  which every portal's sidebar carries).

  Clicking, typing, scrolling and touching count; so does any other tab of
  CapsTrack in the same browser. A minute before the end a box asks whether
  they are still there. When time runs out the page goes to session.expired,
  which signs them out and tells them why on the sign-in page. The server
  keeps its own count (App\Http\Middleware\EndIdleSession), so a closed
  laptop or a stopped timer cannot keep a session open either.
--}}
@php
  $idleMinutes = (int) config('session.idle_timeout');
  $idleIsExpert = auth('expert')->check();
  $idleSignedIn = $idleIsExpert || auth('web')->check();
@endphp

@if ($idleMinutes > 0 && $idleSignedIn)
<div class="idl" id="idle-logout" role="alertdialog" aria-modal="true" aria-labelledby="idl-title" aria-describedby="idl-body" hidden
     data-minutes="{{ $idleMinutes }}"
     data-keep="{{ route('session.keep-alive') }}"
     data-expired="{{ route('session.expired') }}"
     data-csrf="{{ csrf_token() }}">
  <div class="idl-card">
    <span class="idl-ic" aria-hidden="true">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5M9 2h6M12 2v3"/></svg>
    </span>
    <h2 id="idl-title">Are you still there?</h2>
    <p id="idl-body">You have been inactive for a while. For your security, you will be signed out in</p>
    <div class="idl-count" id="idl-count" aria-live="assertive">1:00</div>
    <div class="idl-acts">
      <form method="POST" action="{{ $idleIsExpert ? route('expert.logout') : route('logout') }}">
        @csrf
        <button type="submit" class="idl-btn">Sign out</button>
      </form>
      <button type="button" class="idl-btn idl-btn-primary" id="idl-stay">Stay signed in</button>
    </div>
  </div>
</div>

<style>
.idl{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(15,16,22,.62);backdrop-filter:blur(4px)}
.idl[hidden]{display:none}
.idl-card{width:min(380px,100%);background:#fff;border-radius:18px;padding:26px 24px 20px;text-align:center;box-shadow:0 24px 70px rgba(0,0,0,.3);border-top:5px solid #c9a82c;animation:idlIn .22s cubic-bezier(.2,.8,.2,1)}
@keyframes idlIn{from{opacity:0;transform:translateY(10px) scale(.97)}to{opacity:1;transform:none}}
.idl-ic{width:52px;height:52px;margin:0 auto 12px;border-radius:50%;display:grid;place-items:center;background:rgba(201,168,44,.14);color:#8a6e10}
.idl-ic svg{width:26px;height:26px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.idl-card h2{margin:0;font-size:18px;font-weight:700;color:#18191f}
.idl-card p{margin:6px 0 0;font-size:13.5px;line-height:1.5;color:#4b5563}
.idl-count{margin:10px 0 18px;font-size:34px;font-weight:700;letter-spacing:-.02em;color:#b91c1c;font-variant-numeric:tabular-nums}
.idl-acts{display:flex;gap:10px}
.idl-acts form{flex:1;margin:0;display:flex}
.idl-btn{flex:1;padding:12px 14px;border-radius:11px;border:1px solid #e5e7eb;background:#fff;color:#374151;font:inherit;font-size:14px;font-weight:700;cursor:pointer}
.idl-btn:hover{background:#f3f4f6}
.idl-btn-primary{background:#c9a82c;border-color:#c9a82c;color:#18191f}
.idl-btn-primary:hover{background:#b8981f}
@media (max-width:480px){
  .idl{align-items:flex-end;padding:0}
  .idl-card{width:100%;border-radius:18px 18px 0 0;padding-bottom:calc(20px + env(safe-area-inset-bottom))}
}
@media (prefers-reduced-motion:reduce){.idl-card{animation:none}}
</style>

<script>
(function () {
  var box = document.getElementById('idle-logout');
  if (!box) return;

  var LIMIT = parseInt(box.dataset.minutes, 10) * 60000;
  var WARN = Math.min(60000, LIMIT / 2);
  var PING_EVERY = Math.min(120000, LIMIT / 3);
  var KEY = 'ct-last-activity';
  var countEl = document.getElementById('idl-count');
  var last = Date.now();
  var lastPing = Date.now();
  var busySincePing = false;
  var ended = false;

  // Other tabs of CapsTrack count: busy in one, the rest stay signed in.
  function shared() { try { return parseInt(localStorage.getItem(KEY), 10) || 0; } catch (e) { return 0; } }
  function share() { try { localStorage.setItem(KEY, String(last)); } catch (e) {} }

  // A modal <dialog> - the upload pop-up, its preview - makes the rest of the
  // page inert: the question would show behind it and could not be answered,
  // and the file being sent would be lost to the sign-out. So it is asked
  // inside the topmost one, and goes home once answered.
  var home = box.parentNode;
  function place() {
    var top = null;
    document.querySelectorAll('dialog[open]').forEach(function (d) {
      try { if (d.matches(':modal')) top = d; } catch (e) {}
    });
    var host = box.hidden ? home : (top || home);
    if (box.parentNode !== host) host.appendChild(box);
  }

  function end() {
    if (ended) return;
    ended = true;
    window.location.href = box.dataset.expired;
  }

  // Tells the server someone is here: it keeps its own count, and a page can
  // be read for ten minutes without sending it anything.
  function ping() {
    lastPing = Date.now();
    busySincePing = false;
    return fetch(box.dataset.keep, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': box.dataset.csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) { if (r.status === 401 || r.status === 419) end(); }).catch(function () {});
  }

  function active() {
    // While the question is up only its buttons answer it; a stray mouse
    // movement should not quietly dismiss a security prompt.
    if (ended || !box.hidden) return;
    var now = Date.now();
    if (now - last < 1000) return;
    last = now;
    busySincePing = true;
    share();
  }

  ['mousedown', 'mousemove', 'keydown', 'wheel', 'touchstart', 'input'].forEach(function (type) {
    document.addEventListener(type, active, { passive: true, capture: true });
  });
  document.addEventListener('scroll', active, { passive: true, capture: true });

  window.addEventListener('storage', function (e) {
    if (e.key === KEY && !box.hidden && shared() > last) { last = shared(); box.hidden = true; place(); }
  });

  function stay() {
    last = Date.now();
    share();
    box.hidden = true;
    place();
    ping();
  }
  document.getElementById('idl-stay').addEventListener('click', stay);

  function tick() {
    if (ended) return;
    // Carried off with a pop-up that has since been removed.
    if (!box.isConnected) home.appendChild(box);
    var now = Date.now();
    var idle = now - Math.max(last, shared());

    if (idle >= LIMIT) { end(); return; }

    if (idle >= LIMIT - WARN) {
      var left = Math.ceil((LIMIT - idle) / 1000);
      countEl.textContent = Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0');
      if (box.hidden) {
        box.hidden = false;
        place();
        document.getElementById('idl-stay').focus({ preventScroll: true });
      } else {
        place();
      }
    } else if (!box.hidden) {
      box.hidden = true;
      place();
    }

    if (busySincePing && now - lastPing >= PING_EVERY) ping();
  }

  share();
  setInterval(tick, 1000);
  // A phone asleep or a tab in the background slows timers right down;
  // check the moment the page is looked at again.
  document.addEventListener('visibilitychange', function () { if (!document.hidden) tick(); });
  window.addEventListener('focus', tick);
})();
</script>
@endif
