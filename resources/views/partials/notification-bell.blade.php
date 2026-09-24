{{--
  The one bell every expert page carries - instructor, CaPSA and admin alike.

  It is one partial rather than three because one person is often more than
  one of those: a CaPSA who also advises a team has to hear about that team
  from whichever page they happen to be on. It reads the notifications table,
  the same store the student bell reads, so both sides of CapsTrack behave
  identically.

  The wrapper carries everything /js/capstrack-push.js needs, so this is the
  only file that knows about push and the twenty-eight pages that include the
  bell need no edit at all.
--}}
@php
  use App\Models\Notification;

  $bellExpert = auth('expert')->user();

  $bellItems = $bellExpert
    ? Notification::where('expert_id', $bellExpert->expert_id)
        ->latest('created_at')
        ->take(15)
        ->get()
    : collect();

  $bellUnread = $bellItems->where('is_read', false)->count();

  // Every kind an expert can be sent, and the face it wears in the list.
  $bellIcons = [
    'expert_application' => ['rgba(124,58,237,0.12)', '#7c3aed', '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
    'consultation_booked' => ['rgba(59,130,246,0.12)', '#3b82f6', '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
    'consultation_cancelled' => ['rgba(239,68,68,0.12)', '#ef4444', '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="14" y1="14" x2="10" y2="18"/><line x1="10" y1="14" x2="14" y2="18"/>'],
    'consultation_reminder' => ['rgba(245,158,11,0.12)', '#f59e0b', '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>'],
    'document_submitted' => ['rgba(22,163,74,0.12)', '#16a34a', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
    'paper_to_check' => ['rgba(59,130,246,0.12)', '#3b82f6', '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>'],
    'review_reminder' => ['rgba(249,115,22,0.12)', '#f97316', '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>'],
    'leave_request_received' => ['rgba(239,68,68,0.12)', '#ef4444', '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="11" x2="23" y2="11"/>'],
  ];
@endphp

{{-- Before the markup, not after it: a style further down arrives after
     the browser may already have painted the panel and its popups,
     which then flashed full-size on a refresh. --}}
<style>
/* The panel hangs off the bell. Without a positioned wrapper of its own it
   measures against <body> and lands most of a page below the button. */
.notif-wrap{position:relative;display:inline-flex}
.notif-btn{position:relative}
.notif-dot{position:absolute;top:-4px;right:-4px;min-width:18px;height:18px;border-radius:9px;background:#ef4444;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;line-height:1;padding:0 4px}

.notif-dropdown{position:absolute;top:calc(100% + 8px);right:0;width:380px;max-height:480px;background:var(--bg2,#fff);border:1px solid var(--border);border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,0.12),0 0 0 1px rgba(0,0,0,0.04);z-index:9999;display:none;text-align:left}
.notif-dropdown.open{display:block;animation:notifFadeIn .12s ease}
@keyframes notifFadeIn{from{opacity:0;transform:translateY(-6px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}

.notif-dropdown-header{display:flex;align-items:center;justify-content:space-between;padding:14px 16px 10px;border-bottom:1px solid var(--border)}
.notif-dropdown-title{font-size:14px;font-weight:700;color:var(--text)}
.notif-mark-all-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border:none;border-radius:6px;background:rgba(37,99,235,0.08);color:#2563eb;font-size:11px;font-weight:600;cursor:pointer;transition:background .12s}
.notif-mark-all-btn:hover{background:rgba(37,99,235,0.15)}

.notif-ask{display:flex;align-items:center;gap:10px;justify-content:space-between;padding:11px 16px;border-bottom:1px solid var(--border);background:var(--bg3)}
.notif-ask-t{font-size:12.5px;font-weight:700;color:var(--text)}
.notif-ask-m{font-size:11px;color:var(--text3);margin-top:1px}
.notif-ask-btn{flex-shrink:0;padding:6px 12px;border:none;border-radius:6px;background:#2563eb;color:#fff;font-size:11.5px;font-weight:700;cursor:pointer}
.notif-ask-btn:hover{background:#1d4ed8}

.notif-list{max-height:360px;overflow-y:auto}
.notif-list::-webkit-scrollbar{width:4px}
.notif-list::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}

.notif-item{display:flex;align-items:flex-start;gap:12px;padding:12px 16px;text-decoration:none;transition:background .1s;border-bottom:1px solid var(--border);position:relative}
.notif-item:last-child{border-bottom:none}
.notif-item:hover{background:var(--bg3)}
/* Anything not yet opened keeps a tint, so a full list still reads at a glance. */
.notif-item.is-unread{background:rgba(37,99,235,0.05)}
.notif-item.is-unread .notif-item-title{font-weight:700}

.notif-item-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px}
.notif-item-icon svg{width:16px;height:16px}

.notif-item-body{flex:1;min-width:0}
.notif-item-title{font-size:13px;font-weight:600;color:var(--text);margin-bottom:2px;line-height:1.3}
.notif-item-msg{font-size:12px;color:var(--text2);line-height:1.4}
.notif-item-time{font-size:11px;color:var(--text3);margin-top:4px}

.notif-item::before{content:'';position:absolute;top:14px;left:0;width:3px;height:20px;border-radius:0 3px 3px 0;background:var(--accent);opacity:0;transition:opacity .1s}
.notif-item:hover::before{opacity:1}

.notif-empty{padding:40px 18px;text-align:center;font-size:13px;color:var(--text3);display:flex;flex-direction:column;align-items:center}
.notif-footer{display:flex;border-top:1px solid var(--border)}
.notif-footer a{flex:1;padding:11px 8px;text-align:center;font-size:11.5px;font-weight:600;color:var(--text3);text-decoration:none}
.notif-footer a + a{border-left:1px solid var(--border)}
.notif-footer a:hover{background:var(--bg3);color:var(--text2)}
</style>

<div class="notif-wrap" id="notif-wrap"
     data-vapid="{{ config('services.webpush.public_key') }}"
     data-subscribe="{{ route('instructor.push.subscribe') }}"
     data-csrf="{{ csrf_token() }}">
<button class="notif-btn" id="notif-btn" type="button" aria-label="Notifications" aria-expanded="false">
  <svg viewBox="0 0 24 24" style="pointer-events:none"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
  @if ($bellUnread > 0)
    <div class="notif-dot">{{ $bellUnread > 9 ? '9+' : $bellUnread }}</div>
  @endif
</button>

<div class="notif-dropdown" id="notif-dropdown">
  <div class="notif-dropdown-header">
    <div class="notif-dropdown-title">Notifications</div>
    @if ($bellUnread > 0)
      <button class="notif-mark-all-btn" id="notif-mark-all-btn" type="button">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 6 9 17 4 12"/></svg>
        <span>Mark all read</span>
      </button>
    @endif
  </div>

  {{-- The browser is asked here, on a click, rather than the moment a page
       loads: a permission prompt nobody invited is the fastest way to be
       told No for good. --}}
  <div class="notif-ask" id="notif-ask" hidden>
    <div>
      <div class="notif-ask-t">Get these on your desktop</div>
      <div class="notif-ask-m">Even when CapsTrack is in another tab.</div>
    </div>
    <button type="button" class="notif-ask-btn" id="notif-ask-btn">Turn on</button>
  </div>

  <div class="notif-list">
    @forelse ($bellItems as $n)
      @php [$bg, $fg, $path] = $bellIcons[$n->type] ?? ['rgba(100,116,139,0.12)', '#64748b', '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>']; @endphp
      <a href="{{ $n->link ?: '#' }}" class="notif-item {{ $n->is_read ? '' : 'is-unread' }}" data-id="{{ $n->id }}">
        <div class="notif-item-icon" style="background:{{ $bg }};color:{{ $fg }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $path !!}</svg>
        </div>
        <div class="notif-item-body">
          <div class="notif-item-title">{{ $n->title }}</div>
          {{-- Messages are written as HTML and escape their own values at
               the source, the same way the student bell renders them. --}}
          <div class="notif-item-msg">{!! $n->message !!}</div>
          <div class="notif-item-time">{{ $n->created_at?->diffForHumans() }}</div>
        </div>
      </a>
    @empty
      <div class="notif-empty">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:.3;margin-bottom:8px"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div>No notifications yet</div>
      </div>
    @endforelse
  </div>

  {{-- CaPSA and instructor keep separate profile pages, so the caller says
       which one this bell's settings live on. The CaPSA bell also keeps a way
       through to Recent Activity, which is what it used to show. --}}
  <div class="notif-footer">
    <a href="{{ $bellSettingsUrl }}#notifications">Choose what you get notified about</a>
    @isset ($bellActivityUrl)
      <a href="{{ $bellActivityUrl }}">Recent activity</a>
    @endisset
  </div>
</div>
</div>

<script src="/js/capstrack-push.js" defer></script>
<script>
(function () {
  var wrap = document.getElementById('notif-wrap');
  var panel = document.getElementById('notif-dropdown');
  if (!wrap || !panel) return;

  var csrf = wrap.dataset.csrf;
  var base = @json(url('instructor/notifications'));

  function post(url, method) {
    return fetch(url, { method: method, headers: { 'X-CSRF-TOKEN': csrf } });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('#notif-btn');
    if (btn) {
      e.stopPropagation();
      var open = panel.classList.toggle('open');
      btn.setAttribute('aria-expanded', String(open));
      return;
    }

    if (e.target.closest('#notif-ask-btn')) {
      e.preventDefault();
      var ask = document.getElementById('notif-ask');
      window.CapsTrackPush.enable().then(function () { if (ask) ask.hidden = true; });
      return;
    }

    if (!e.target.closest('#notif-dropdown')) {
      panel.classList.remove('open');
      var b = document.getElementById('notif-btn');
      if (b) b.setAttribute('aria-expanded', 'false');
      return;
    }

    // Opening one marks it read, then goes where it points.
    var item = e.target.closest('.notif-item');
    if (item) {
      e.preventDefault();
      var href = item.getAttribute('href');
      item.style.opacity = '0.5';
      var go = function () { if (href && href !== '#') window.location.href = href; };
      post(base + '/' + item.dataset.id + '/read', 'PUT').then(go, go);
    }
  });

  document.addEventListener('click', function (e) {
    var markBtn = e.target.closest('#notif-mark-all-btn');
    if (!markBtn) return;
    post(base, 'PATCH').then(function () {
      var dot = wrap.querySelector('.notif-dot');
      if (dot) dot.remove();
      markBtn.remove();
      panel.querySelectorAll('.notif-item.is-unread').forEach(function (el) {
        el.classList.remove('is-unread');
      });
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    panel.classList.remove('open');
    var b = document.getElementById('notif-btn');
    if (b) b.setAttribute('aria-expanded', 'false');
  });

  // Only offered where the browser can actually do it, and only while it has
  // neither been allowed nor refused.
  window.addEventListener('load', function () {
    var ask = document.getElementById('notif-ask');
    if (ask && window.CapsTrackPush && window.CapsTrackPush.state() === 'default') {
      ask.hidden = false;
    }
  });
})();
</script>
