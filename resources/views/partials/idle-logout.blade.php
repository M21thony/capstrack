{{--
  Signs the person out after config('session.idle_timeout') minutes of doing
  nothing, on every portal page (included once from partials/mobile-tabbar,
  which every portal's sidebar carries).

  Clicking, typing, scrolling and touching count; so does any other tab of
  CapsTrack in the same browser. There is no warning box or countdown: when
  the time is up the page goes straight to session.expired, which signs them
  out and tells them why on the sign-in page. The server keeps its own count
  (App\Http\Middleware\EndIdleSession), so a closed laptop or a stopped timer
  cannot keep a session open either.
--}}
@php
  $idleMinutes = (int) config('session.idle_timeout');
  $idleSignedIn = auth('expert')->check() || auth('web')->check();
@endphp

@if ($idleMinutes > 0 && $idleSignedIn)
<div id="idle-logout" hidden
     data-minutes="{{ $idleMinutes }}"
     data-keep="{{ route('session.keep-alive') }}"
     data-expired="{{ route('session.expired') }}"
     data-csrf="{{ csrf_token() }}"></div>

<script>
(function () {
  var box = document.getElementById('idle-logout');
  if (!box) return;

  var LIMIT = parseInt(box.dataset.minutes, 10) * 60000;
  var PING_EVERY = Math.min(120000, LIMIT / 3);
  var KEY = 'ct-last-activity';
  var last = Date.now();
  var lastPing = Date.now();
  var busySincePing = false;
  var ended = false;

  // Other tabs of CapsTrack count: busy in one, the rest stay signed in.
  function shared() { try { return parseInt(localStorage.getItem(KEY), 10) || 0; } catch (e) { return 0; } }
  function share() { try { localStorage.setItem(KEY, String(last)); } catch (e) {} }

  function end() {
    if (ended) return;
    ended = true;
    window.location.href = box.dataset.expired;
  }

  // Tells the server someone is here: it keeps its own count, and a page can
  // be read for the whole idle limit without sending it anything.
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
    if (ended) return;
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

  function tick() {
    if (ended) return;
    var now = Date.now();
    if (now - Math.max(last, shared()) >= LIMIT) { end(); return; }
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
