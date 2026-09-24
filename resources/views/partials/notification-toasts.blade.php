{{--
  Pop-ups in the bottom corner for anything new in the bell - notifications
  and announcements alike - so an update is seen without opening the bell.

  Included by both bells (partials/notification-bell for every expert page,
  student/partials/notification-dropdown for every student page), so every
  portal gets it without touching its pages.

  On load it pops what the bell already lists as unread and has not popped
  before on this browser; after that it checks the feed every so often and
  pops whatever has arrived since. Clicking one does exactly what clicking it
  in the bell does.

  $toastFeed  the JSON list of this person's latest notifications
  $toastRead  the base URL that /{id}/read is appended to
  $toastOwner who this is, so two people on one browser do not share a memory
--}}
<div class="nt-stack" id="nt-stack" aria-live="polite" aria-label="New notifications"
     data-feed="{{ $toastFeed }}" data-read="{{ $toastRead }}" data-owner="{{ $toastOwner }}"
     data-csrf="{{ csrf_token() }}"></div>

<style>
.nt-stack{position:fixed;right:20px;bottom:20px;z-index:2500;display:flex;flex-direction:column;gap:10px;width:min(380px,calc(100vw - 32px));pointer-events:none}
.nt{pointer-events:auto;position:relative;display:flex;gap:12px;align-items:flex-start;padding:14px 40px 16px 14px;background:var(--bg2,#fff);border:1px solid var(--border,#e5e7eb);border-left:4px solid var(--accent,#c9a82c);border-radius:14px;box-shadow:0 14px 36px rgba(24,25,31,.2);overflow:hidden;cursor:pointer;text-align:left;animation:ntIn .3s cubic-bezier(.2,.8,.2,1)}
.nt:hover{box-shadow:0 16px 40px rgba(24,25,31,.26)}
.nt.is-announcement{border-left-color:#f97316}
.nt.is-summary{border-left-color:var(--navy-mid,#262a38)}
.nt.out{animation:ntOut .2s ease forwards}
@keyframes ntIn{from{opacity:0;transform:translateX(28px)}to{opacity:1;transform:none}}
@keyframes ntOut{to{opacity:0;transform:translateX(28px)}}
.nt-ic{width:36px;height:36px;flex:none;border-radius:10px;display:grid;place-items:center;background:rgba(201,168,44,.14);color:#8a6e10}
.nt-ic svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.nt-body{flex:1;min-width:0}
.nt-kind{font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text3,#6b7280)}
.nt.is-announcement .nt-kind{color:#ea580c}
.nt-title{margin-top:1px;font-size:13.5px;font-weight:700;line-height:1.3;color:var(--text,#18191f)}
.nt-msg{margin-top:3px;font-size:12.5px;line-height:1.45;color:var(--text2,#4b5563);display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.nt-foot{display:flex;align-items:center;gap:10px;margin-top:8px;font-size:11px;color:var(--text3,#6b7280)}
.nt-go{margin-left:auto;font-weight:700;color:var(--accent3,#8a6e10)}
.nt-x{position:absolute;top:8px;right:8px;width:28px;height:28px;display:grid;place-items:center;border:0;border-radius:8px;background:transparent;color:var(--text3,#6b7280);cursor:pointer}
.nt-x:hover{background:var(--bg3,#f3f4f6);color:var(--text,#18191f)}
.nt-x svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2.4;stroke-linecap:round}
.nt-bar{position:absolute;left:0;right:0;bottom:0;height:3px;background:var(--accent,#c9a82c);opacity:.55;transform-origin:left;animation:ntBar linear forwards}
.nt.is-announcement .nt-bar{background:#f97316}
.nt:hover .nt-bar{animation-play-state:paused}
@keyframes ntBar{from{transform:scaleX(1)}to{transform:scaleX(0)}}
/* On a phone the stack spans the screen and sits above the tab bar. */
@media (max-width:768px){
  .nt-stack{left:12px;right:12px;width:auto;gap:8px;bottom:calc(86px + env(safe-area-inset-bottom))}
  .nt{padding:12px 38px 13px 12px;gap:10px;border-radius:13px}
  .nt-ic{width:32px;height:32px;border-radius:9px}
  .nt-msg{-webkit-line-clamp:2}
  .nt-foot{margin-top:6px}
}
@media (prefers-reduced-motion:reduce){.nt,.nt.out{animation:none}.nt-bar{display:none}}
</style>

<script>
(function () {
  var stack = document.getElementById('nt-stack');
  if (!stack || !window.fetch) return;

  var key = 'ct-popped-' + stack.dataset.owner;
  var loadedAt = Date.now();
  var reduced = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  // Fewer at once on a phone, where each one covers far more of the screen.
  var MAX = window.matchMedia && matchMedia('(max-width: 768px)').matches ? 2 : 3;
  var popped = [];
  try { popped = JSON.parse(localStorage.getItem(key) || '[]'); } catch (e) {}

  function remember(ids) {
    ids.forEach(function (id) { if (popped.indexOf(String(id)) < 0) popped.push(String(id)); });
    popped = popped.slice(-300);
    try { localStorage.setItem(key, JSON.stringify(popped)); } catch (e) {}
  }
  function wasPopped(id) { return popped.indexOf(String(id)) >= 0; }

  // Messages are stored as HTML; a pop-up only ever shows their text.
  function textOf(html) {
    return new DOMParser().parseFromString(html || '', 'text/html').body.textContent.trim();
  }

  var ICONS = {
    announcement: ['rgba(249,115,22,0.14)', '#ea580c', '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>'],
    bell: ['rgba(201,168,44,0.16)', '#8a6e10', '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>']
  };

  function inBell(id) {
    return document.querySelector('#notif-dropdown [data-id="' + String(id).replace(/"/g, '') + '"]');
  }

  function openBell() {
    var btn = document.getElementById('notif-btn');
    var panel = document.getElementById('notif-dropdown');
    if (btn && panel && !panel.classList.contains('open')) btn.click();
  }

  function dismiss(el) {
    if (!el || el.classList.contains('out')) return;
    el.classList.add('out');
    setTimeout(function () { el.remove(); }, reduced ? 0 : 200);
  }

  // What clicking it in the bell would do: the bell's own handler if it is
  // listed there, otherwise mark it read and follow its link.
  function follow(n) {
    var item = inBell(n.id);
    var href = item ? item.getAttribute('href') : null;
    // The leave-decision pop-up opens from the item even without a link.
    if (item && item.matches('a.notif-item') && ((href && href !== '#') || item.dataset.leave)) { item.click(); return; }
    if (item || !n.link || n.link === '#') { openBell(); return; }
    var go = function () { window.location.href = n.link; };
    fetch(stack.dataset.read + '/' + n.id + '/read', { method: 'PUT', headers: { 'X-CSRF-TOKEN': stack.dataset.csrf } }).then(go, go);
  }

  function pop(n) {
    var announcement = n.type === 'announcement' || n.type === 'team_mention';
    var el = document.createElement('div');
    el.className = 'nt' + (announcement ? ' is-announcement' : '') + (n.summary ? ' is-summary' : '');
    el.setAttribute('role', 'status');

    var ic = document.createElement('span');
    ic.className = 'nt-ic';
    if (n.iconNode) {
      ic.style.background = n.iconNode.style.background;
      ic.style.color = n.iconNode.style.color;
      ic.innerHTML = n.iconNode.innerHTML;
    } else {
      var face = ICONS[announcement ? 'announcement' : 'bell'];
      ic.style.background = face[0];
      ic.style.color = face[1];
      ic.innerHTML = '<svg viewBox="0 0 24 24">' + face[2] + '</svg>';
    }

    var body = document.createElement('div');
    body.className = 'nt-body';
    var kind = document.createElement('div');
    kind.className = 'nt-kind';
    kind.textContent = n.summary ? 'Notifications' : (announcement ? 'Announcement' : 'New notification');
    var title = document.createElement('div');
    title.className = 'nt-title';
    title.textContent = n.title || 'Notification';
    body.appendChild(kind);
    body.appendChild(title);
    if (n.message) {
      var msg = document.createElement('div');
      msg.className = 'nt-msg';
      msg.textContent = n.message;
      body.appendChild(msg);
    }
    var foot = document.createElement('div');
    foot.className = 'nt-foot';
    if (n.time) { var t = document.createElement('span'); t.textContent = n.time; foot.appendChild(t); }
    var go = document.createElement('span');
    go.className = 'nt-go';
    go.textContent = n.summary ? 'Open notifications' : 'View';
    foot.appendChild(go);
    body.appendChild(foot);

    var x = document.createElement('button');
    x.type = 'button';
    x.className = 'nt-x';
    x.setAttribute('aria-label', 'Dismiss');
    x.innerHTML = '<svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>';

    var bar = document.createElement('span');
    bar.className = 'nt-bar';
    var life = announcement ? 12000 : 9000;
    bar.style.animationDuration = life + 'ms';
    bar.addEventListener('animationend', function () { dismiss(el); });
    if (reduced) setTimeout(function () { dismiss(el); }, life);

    el.appendChild(ic);
    el.appendChild(body);
    el.appendChild(x);
    el.appendChild(bar);
    el.addEventListener('click', function (e) {
      e.stopPropagation();
      dismiss(el);
      if (e.target.closest('.nt-x')) return;
      if (n.summary) { openBell(); return; }
      follow(n);
    });

    stack.appendChild(el);
  }

  // A few at a time, newest last so it lands nearest the corner; the rest
  // are counted in one that opens the bell.
  function popAll(list) {
    if (!list.length) return;
    remember(list.map(function (n) { return n.id; }));
    list.slice(0, MAX).reverse().forEach(function (n, i) {
      setTimeout(function () { pop(n); }, i * 180);
    });
    if (list.length > MAX) {
      var more = list.length - MAX;
      setTimeout(function () {
        pop({ summary: true, title: more + ' more new ' + (more === 1 ? 'notification' : 'notifications'), message: 'Open the bell to see them all.' });
      }, MAX * 180);
    }
  }

  function bumpDot(by) {
    var btn = document.getElementById('notif-btn');
    if (!btn || !by) return;
    var dot = btn.querySelector('.notif-dot');
    if (!dot) { dot = document.createElement('div'); dot.className = 'notif-dot'; dot.textContent = '0'; btn.appendChild(dot); }
    var now = (parseInt(dot.textContent, 10) || 0) + by;
    dot.textContent = now > 9 ? '9+' : String(now);
  }

  // What the bell already lists as unread, newest first as it is drawn.
  function fromBell() {
    return Array.prototype.map.call(
      document.querySelectorAll('#notif-dropdown .notif-item.is-unread[data-id], #notif-dropdown .notif-item--action[data-id]'),
      function (item) {
        var q = function (s) { var e = item.querySelector(s); return e ? e.textContent.trim() : ''; };
        return {
          id: item.dataset.id,
          title: q('.notif-item-title'),
          message: q('.notif-item-msg'),
          time: q('.notif-item-time'),
          type: item.dataset.type || '',
          iconNode: item.querySelector('.notif-item-icon')
        };
      }
    ).filter(function (n) { return !wasPopped(n.id); });
  }

  function check() {
    if (document.hidden) return;
    fetch(stack.dataset.feed, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data || !data.notifications) return;
        var fresh = [], old = [];
        data.notifications.forEach(function (n) {
          if (n.is_read || wasPopped(n.id)) return;
          // Anything already on the page, or older than the page itself, is
          // not news: the load above has had its say about those.
          if (inBell(n.id) || (n.created_at && Date.parse(n.created_at) < loadedAt - 120000)) { old.push(n.id); return; }
          fresh.push({ id: n.id, type: n.type, title: n.title, message: textOf(n.message), time: n.time, link: n.link });
        });
        remember(old);
        if (fresh.length) { bumpDot(fresh.length); popAll(fresh); }
      })
      .catch(function () {});
  }

  window.addEventListener('load', function () {
    setTimeout(function () { popAll(fromBell()); }, 900);
  });
  setInterval(check, 45000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) check(); });
})();
</script>
