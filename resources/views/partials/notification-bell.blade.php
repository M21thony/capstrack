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

  $bellTabs = [
    'all' => ['All', $bellItems->count()],
    'unread' => ['Unread', $bellUnread],
    'documents' => ['Documents', $bellItems->whereIn('type', ['document_submitted', 'paper_to_check', 'review_reminder'])->count()],
    'consultations' => ['Consultations', $bellItems->whereIn('type', ['consultation_booked', 'consultation_cancelled', 'consultation_reminder'])->count()],
  ];
@endphp

{{-- Before the markup, not after it: a style further down arrives after
     the browser may already have painted the panel and its popups,
     which then flashed full-size on a refresh. --}}
<style nonce="{{ Vite::cspNonce() }}">
/* The panel hangs off the bell. Without a positioned wrapper of its own it
   measures against <body> and lands most of a page below the button. */
.notif-wrap{position:relative;display:inline-flex}
.notif-btn{position:relative}
.notif-dot{position:absolute;top:-4px;right:-4px;min-width:18px;height:18px;border-radius:9px;background:#ef4444;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;line-height:1;padding:0 4px}

.notif-dropdown{position:absolute;top:calc(100% + 8px);right:0;width:380px;max-width:calc(100vw - 24px);max-height:calc(100vh - 82px);max-height:calc(100dvh - 82px);background:var(--bg2,#fff);border:1px solid var(--border);border-radius:12px;box-shadow:0 12px 32px rgba(24,25,31,0.18);z-index:9999;display:none;flex-direction:column;overflow:hidden;text-align:left}
.notif-dropdown.open{display:flex;animation:notifFadeIn .12s ease}
@keyframes notifFadeIn{from{opacity:0;transform:translateY(-6px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}

.notif-dropdown-header{display:flex;align-items:center;justify-content:space-between;padding:12px 16px 8px;flex-shrink:0}
.notif-dropdown-title{display:flex;align-items:center;gap:8px;font-size:15px;font-weight:700;color:var(--text)}
.notif-dropdown-title svg{width:18px;height:18px;color:#ea580c}
.notif-mark-all-btn{all:unset;cursor:pointer;display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:var(--accent3)}
.notif-mark-all-btn svg{width:14px;height:14px;color:#16a34a}
.notif-mark-all-btn:hover{text-decoration:underline}

.notif-ask{display:flex;align-items:center;gap:10px;justify-content:space-between;padding:11px 16px;border-bottom:1px solid var(--border);background:var(--bg3);flex-shrink:0}
.notif-ask[hidden]{display:none}
.notif-ask-t{font-size:12.5px;font-weight:700;color:var(--text)}
.notif-ask-m{font-size:11px;color:var(--text3);margin-top:1px}
.notif-ask-btn{flex-shrink:0;padding:6px 12px;border:none;border-radius:6px;background:#2563eb;color:#fff;font-size:11.5px;font-weight:700;cursor:pointer}
.notif-ask-btn:hover{background:#1d4ed8}

.np-tabs{display:flex;gap:14px;padding:0 16px;border-bottom:1px solid var(--border);flex-shrink:0}
.np-tab{all:unset;cursor:pointer;padding:9px 0 10px;margin-bottom:-1px;font-size:12px;font-weight:600;color:var(--text2);border-bottom:2px solid transparent}
.np-tab[aria-selected=true]{color:var(--text);border-bottom-color:var(--accent2)}
.np-tab:focus-visible{outline:2px solid var(--accent2);outline-offset:2px}
.np-tab-n{margin-left:4px;color:var(--text2);font-weight:500}

.notif-list{overflow-y:auto;max-height:202px}
.notif-list::-webkit-scrollbar{width:4px}
.notif-list::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}

.notif-item{position:relative;display:flex;align-items:center;gap:10px;padding:5px 16px 5px 20px;border-bottom:1px solid var(--bg4);text-decoration:none;cursor:pointer}
.notif-item:hover{background:var(--bg3)}
/* Anything not yet opened keeps a dot, so a full list still reads at a glance. */
.notif-item.is-unread::before{content:'';position:absolute;left:7px;top:12px;width:7px;height:7px;border-radius:50%;background:var(--accent)}
.notif-item.is-unread[data-kind=announcement]::before{background:var(--accent2)}

.notif-item-icon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;align-self:flex-start}
.notif-item-icon svg{width:19px;height:19px}

.notif-item-body{flex:1;min-width:0;font-size:12px;color:var(--text2);line-height:1.35}
.np-titlerow{display:flex;align-items:center;gap:6px}
.notif-item-title{font-size:13px;font-weight:600;color:var(--text)}
.np-tag{font-size:11px;font-weight:600;color:var(--accent3)}
.notif-item-time{margin-top:1px}
.np-go{flex-shrink:0;display:inline-flex;align-items:center;min-height:28px;padding:0 10px;border-radius:8px;background:var(--bg4);color:var(--text);font-size:12px;font-weight:600}
.np-go:hover{background:var(--border)}
.np-chev{width:14px;height:14px;flex-shrink:0;color:var(--text3)}

.notif-empty,.np-empty{padding:28px 16px;text-align:center;font-size:13px;color:var(--text2)}
.notif-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 16px;border-top:1px solid var(--bg4);font-size:12px;font-weight:600;flex-shrink:0}
.notif-footer a{color:var(--accent3);text-decoration:none}
.notif-footer a:hover{text-decoration:underline}
.notif-footer a:first-child:not(:last-child){color:var(--text2)}
</style>

@include('partials.page-guide')
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
    <div class="notif-dropdown-title">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a2 2 0 0 0 3.4 0"/></svg>
      Notifications
    </div>
    @if ($bellUnread > 0)
      <button class="notif-mark-all-btn" id="notif-mark-all-btn" type="button">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5 10 17 19 7.5"/></svg>
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

  {{-- Tabs sort what the bell already holds, the latest 15. --}}
  <div class="np-tabs" role="tablist">
    @foreach ($bellTabs as $filter => [$label, $count])
      <button type="button" class="np-tab" role="tab" aria-selected="{{ $filter === 'all' ? 'true' : 'false' }}" data-filter="{{ $filter }}">{{ $label }}<span class="np-tab-n">{{ $count }}</span></button>
    @endforeach
  </div>

  <div class="notif-list">
    @forelse ($bellItems as $n)
      @include('partials.notification-row')
    @empty
      <div class="notif-empty">No notifications here.</div>
    @endforelse
  </div>
  @if ($bellItems->isNotEmpty())
    <div class="np-empty" hidden>No notifications here.</div>
  @endif

  {{-- CaPSA and instructor keep separate profile pages, so the caller says
       which one this bell's settings live on. An Admin has neither, so their
       bell keeps the way through to Recent Activity instead. --}}
  <div class="notif-footer">
    @if ($bellSettingsUrl)
      <a href="{{ $bellSettingsUrl }}#notifications">Notification settings</a>
    @elseif (isset($bellActivityUrl))
      <a href="{{ $bellActivityUrl }}">Recent activity</a>
    @endif
    <a href="{{ route('instructor.notifications.page') }}">See all notifications →</a>
  </div>
</div>
</div>

@if ($bellExpert)
  @include('partials.notification-toasts', [
    'toastFeed' => route('instructor.notifications.index'),
    'toastRead' => url('instructor/notifications'),
    'toastOwner' => 'e'.$bellExpert->expert_id,
  ])
@endif
@include('partials.action-toast')

<script src="/js/capstrack-push.js" defer></script>
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var wrap = document.getElementById('notif-wrap');
  var panel = document.getElementById('notif-dropdown');
  if (!wrap || !panel) return;

  var csrf = wrap.dataset.csrf;
  var base = @json(url('instructor/notifications'));

  function post(url, method) {
    return fetch(url, { method: method, headers: { 'X-CSRF-TOKEN': csrf } });
  }

  function fits(item, filter) {
    if (filter === 'all') return true;
    return filter === 'unread' ? item.classList.contains('is-unread') : item.dataset.kind === filter;
  }

  // Counts, the red dot and the open tab all follow what is in the panel now.
  function refresh() {
    var items = Array.prototype.slice.call(panel.querySelectorAll('.notif-item'));
    panel.querySelectorAll('.np-tab').forEach(function (tab) {
      var matching = items.filter(function (item) { return fits(item, tab.dataset.filter); });
      tab.querySelector('.np-tab-n').textContent = matching.length;
    });

    var filter = panel.querySelector('.np-tab[aria-selected="true"]').dataset.filter;
    var shown = 0;
    items.forEach(function (item) {
      var show = fits(item, filter);
      item.style.display = show ? '' : 'none';
      if (show) shown++;
    });
    var none = panel.querySelector('.np-empty');
    if (none) none.hidden = shown > 0;

    var unread = panel.querySelectorAll('.notif-item.is-unread').length;
    var dot = wrap.querySelector('.notif-dot');
    if (dot) { if (unread) { dot.textContent = unread > 9 ? '9+' : unread; } else { dot.remove(); } }
    var markBtn = document.getElementById('notif-mark-all-btn');
    if (markBtn && !unread) markBtn.remove();
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

    var tab = e.target.closest('.np-tab');
    if (tab) {
      panel.querySelectorAll('.np-tab').forEach(function (other) {
        other.setAttribute('aria-selected', String(other === tab));
      });
      refresh();
      return;
    }

    // The same rows are on the See all page, outside the panel.
    var item = e.target.closest('.notif-item');

    if (!e.target.closest('#notif-dropdown')) {
      panel.classList.remove('open');
      var b = document.getElementById('notif-btn');
      if (b) b.setAttribute('aria-expanded', 'false');
      if (!item) return;
    }

    // Opening one marks it read, then goes where it points.
    if (item) {
      e.preventDefault();
      var href = item.getAttribute('href');
      item.style.opacity = '0.5';
      var go = function () {
        document.querySelectorAll('.notif-item[data-id="' + item.dataset.id + '"]').forEach(function (el) {
          el.classList.remove('is-unread');
        });
        item.style.opacity = '';
        refresh();
        if (href && href !== '#') window.location.href = href;
      };
      post(base + '/' + item.dataset.id + '/read', 'PUT').then(go, go);
    }
  });

  document.addEventListener('click', function (e) {
    if (!e.target.closest('#notif-mark-all-btn')) return;
    post(base, 'PATCH').then(function () {
      document.querySelectorAll('.notif-item.is-unread').forEach(function (el) {
        el.classList.remove('is-unread');
      });
      refresh();
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
