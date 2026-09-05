@php
  $allUnreadIds = collect($unreadNotifications ?? [])->pluck('id')->toArray();
  $seenNotifIds = session('student_seen_notification_ids', []);
  $unseenNotifCount = count(array_diff($allUnreadIds, $seenNotifIds));
@endphp
<button class="notif-btn" id="notif-btn" type="button" aria-label="Notifications">
  <svg viewBox="0 0 24 24" style="pointer-events:none"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
  @if ($unseenNotifCount > 0)
    <div class="notif-dot">{{ $unseenNotifCount > 9 ? '9+' : $unseenNotifCount }}</div>
  @endif
</button>
<!-- Notification Dropdown -->
<div class="notif-dropdown" id="notif-dropdown">
  <div class="notif-dropdown-header">
    <div class="notif-dropdown-title">Notifications</div>
    @if(isset($unreadCount) && $unreadCount > 0)
      <button class="notif-mark-all-btn" id="notif-mark-all-btn">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 6 9 17 4 12"/></svg>
        <span>Mark all read</span>
      </button>
    @endif
  </div>
  <div class="notif-list">
    @forelse($unreadNotifications ?? [] as $notif)
      @php
        $notifIcon = match ($notif->type) {
          'team_created', 'team_confirmed', 'team_verified' => ['bg' => 'rgba(22,163,74,0.12)', 'fg' => '#16a34a', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>'],
          'member_joined', 'request_accepted' => ['bg' => 'rgba(124,58,237,0.12)', 'fg' => '#7c3aed', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>'],
          'member_removed', 'member_left', 'expert_removed' => ['bg' => 'rgba(239,68,68,0.12)', 'fg' => '#ef4444', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'],
          'invitation_received', 'request_received' => ['bg' => 'rgba(59,130,246,0.12)', 'fg' => '#3b82f6', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'],
          'invitation_declined', 'request_declined' => ['bg' => 'rgba(245,158,11,0.12)', 'fg' => '#f59e0b', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'],
          'leadership_transferred' => ['bg' => 'rgba(251,191,36,0.15)', 'fg' => '#fbbf24', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'],
          'announcement' => ['bg' => 'rgba(249,115,22,0.12)', 'fg' => '#f97316', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>'],
          default => ['bg' => 'rgba(100,116,139,0.12)', 'fg' => '#64748b', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'],
        };
      @endphp
      @if($notif->type === 'leadership_transfer_request')
        <div class="notif-item notif-item--action" data-id="{{ $notif->id }}">
          <div class="notif-item-icon" style="background:{{ $notifIcon['bg'] }};color:{{ $notifIcon['fg'] }}">{!! $notifIcon['svg'] !!}</div>
          <div class="notif-item-body">
            <div class="notif-item-title">{{ $notif->title }}</div>
            <div class="notif-item-msg">{!! $notif->message !!}</div>
            <div class="notif-item-time">{{ $notif->created_at->diffForHumans() }}</div>
            <div class="notif-actions" style="display:flex;gap:8px;margin-top:10px;">
              <form method="POST" action="{{ route('team.leadership.update', $notif->id) }}" style="display:inline;" id="leadership-accept-{{ $notif->id }}">
              @method('PATCH')
              <input type="hidden" name="status" value="accepted">
                @csrf
                <button type="button" class="notif-action-btn" data-accept-id="{{ $notif->id }}" data-team="{{ addslashes($notif->data['team_name'] ?? 'your team') }}" style="flex:1;padding:6px 12px;border-radius:6px;border:1px solid var(--green);background:var(--green-bg);color:var(--green);font-size:11px;font-weight:700;cursor:pointer;">Approve</button>
              </form>
              <form method="POST" action="{{ route('team.leadership.update', $notif->id) }}" style="display:inline;" id="leadership-decline-{{ $notif->id }}">
              @method('PATCH')
              <input type="hidden" name="status" value="declined">
                @csrf
                <button type="button" class="notif-action-btn" data-decline-id="{{ $notif->id }}" data-team="{{ addslashes($notif->data['team_name'] ?? 'your team') }}" style="flex:1;padding:6px 12px;border-radius:6px;border:1px solid var(--red);background:var(--red-bg);color:var(--red);font-size:11px;font-weight:700;cursor:pointer;">Decline</button>
              </form>
            </div>
          </div>
        </div>
      @else
      <a href="{{ $notif->link ?? '#' }}" class="notif-item" data-id="{{ $notif->id }}">
        <div class="notif-item-icon" style="background:{{ $notifIcon['bg'] }};color:{{ $notifIcon['fg'] }}">{!! $notifIcon['svg'] !!}</div>
        <div class="notif-item-body">
          <div class="notif-item-title">{{ $notif->title }}</div>
          <div class="notif-item-msg">{!! $notif->message !!}</div>
          <div class="notif-item-time">{{ $notif->created_at->diffForHumans() }}</div>
        </div>
      </a>
      @endif
    @empty
      <div class="notif-empty">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:.3;margin-bottom:8px"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div>No new notifications</div>
      </div>
    @endforelse
  </div>
</div>

<!-- Leadership Review Confirm Modal -->
<div class="nld-confirm-overlay" id="nld-confirm-overlay" onclick="if(event.target==this) nldConfirmClose()">
  <div class="nld-confirm-card">
    <div class="nld-confirm-icon" id="nld-confirm-icon" style="background:rgba(22,163,74,0.1);color:#16a34a;">
      <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <div class="nld-confirm-title" id="nld-confirm-title">Approve and review?</div>
    <div class="nld-confirm-msg" id="nld-confirm-msg"></div>
    <div class="nld-confirm-actions">
      <button type="button" class="nld-confirm-btn nld-confirm-cancel" onclick="nldConfirmClose()">Cancel</button>
      <button type="button" class="nld-confirm-btn" id="nld-confirm-ok" style="background:#16a34a;">Approve &amp; Review</button>
    </div>
  </div>
</div>

<style>
.notif-dot{position:absolute;top:-4px;right:-4px;min-width:18px;height:18px;border-radius:9px;background:#ef4444;border:2px solid var(--navy-mid);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;line-height:1;padding:0 4px}

.nld-confirm-overlay{display:none;position:fixed;inset:0;z-index:99999;align-items:center;justify-content:center;background:rgba(15,16,22,0.75);backdrop-filter:blur(6px);padding:20px}
.nld-confirm-overlay.active{display:flex}
.nld-confirm-card{background:#fff;border-radius:18px;padding:28px 24px 22px;width:100%;max-width:380px;box-shadow:0 24px 60px rgba(0,0,0,0.25);text-align:center;transform:scale(0.92);transition:transform 0.22s cubic-bezier(0.16,1,0.3,1);position:relative}
.nld-confirm-overlay.active .nld-confirm-card{transform:scale(1)}
.nld-confirm-icon{width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px}
.nld-confirm-title{font-size:16px;font-weight:700;color:var(--text,#111827)}
.nld-confirm-msg{font-size:13px;color:var(--text2,#6b7280);margin-top:8px;line-height:1.5}
.nld-confirm-msg strong{color:var(--text,#111827)}
.nld-confirm-actions{display:flex;gap:10px;margin-top:22px}
.nld-confirm-btn{flex:1;padding:10px;border-radius:8px;border:none;font-size:13px;font-weight:700;cursor:pointer;color:#fff;transition:background .12s}
.nld-confirm-cancel{background:#f3f4f6;color:#374151}
.nld-confirm-cancel:hover{background:#e5e7eb}
[data-theme="dark"] .nld-confirm-card{background:#1f2433}
[data-theme="dark"] .nld-confirm-title{color:#f3f4f6}
[data-theme="dark"] .nld-confirm-msg strong{color:#f3f4f6}

.notif-dropdown{position:absolute;top:calc(100% + 8px);right:0;width:380px;max-height:480px;background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,0.12),0 0 0 1px rgba(0,0,0,0.04);z-index:9999;display:none}
.notif-dropdown.open{display:block;animation:notifFadeIn .12s ease}
@keyframes notifFadeIn{from{opacity:0;transform:translateY(-6px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}

.notif-dropdown-header{display:flex;align-items:center;justify-content:space-between;padding:14px 16px 10px;border-bottom:1px solid var(--border)}
.notif-dropdown-title{font-size:14px;font-weight:700;color:var(--text)}
.notif-mark-all-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border:none;border-radius:6px;background:rgba(37,99,235,0.08);color:#2563eb;font-size:11px;font-weight:600;cursor:pointer;transition:background .12s}
.notif-mark-all-btn:hover{background:rgba(37,99,235,0.15)}

.notif-list{max-height:380px;overflow-y:auto}
.notif-list::-webkit-scrollbar{width:4px}
.notif-list::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}

.notif-item{display:flex;align-items:flex-start;gap:12px;padding:12px 16px;text-decoration:none;transition:background .1s;border-bottom:1px solid var(--border);position:relative}
.notif-item:last-child{border-bottom:none}
.notif-item:hover{background:var(--bg3)}

.notif-item-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px}
.notif-item-icon svg{width:16px;height:16px}

.notif-item-body{flex:1;min-width:0}
.notif-item-title{font-size:13px;font-weight:600;color:var(--text);margin-bottom:2px;line-height:1.3}
.notif-item-msg{font-size:12px;color:var(--text2);line-height:1.4}
.notif-item-msg strong{color:var(--text);font-weight:600}
.notif-item-time{font-size:11px;color:var(--text3);margin-top:4px}

.notif-item::before{content:'';position:absolute;top:14px;left:0;width:3px;height:20px;border-radius:0 3px 3px 0;background:var(--accent);opacity:0;transition:opacity .1s}
.notif-item:hover::before{opacity:1}

.notif-empty{padding:40px 18px;text-align:center;font-size:13px;color:var(--text3);display:flex;flex-direction:column;align-items:center}
</style>

<script>
var nldConfirmUrl = null;
var nldConfirmOverlay = document.getElementById('nld-confirm-overlay');
var nldConfirmIcon = document.getElementById('nld-confirm-icon');
var nldConfirmTitle = document.getElementById('nld-confirm-title');
var nldConfirmMsg = document.getElementById('nld-confirm-msg');
var nldConfirmOk = document.getElementById('nld-confirm-ok');

function nldConfirmClose() {
  nldConfirmOverlay.classList.remove('active');
  nldConfirmUrl = null;
}
window.nldConfirmClose = nldConfirmClose;

document.addEventListener('click', function(e) {
  var btn = e.target.closest('.notif-action-btn');
  if (!btn) return;
  e.preventDefault();
  e.stopPropagation();

  var id = btn.getAttribute('data-accept-id') || btn.getAttribute('data-decline-id');
  var isAccept = btn.hasAttribute('data-accept-id');
  var team = btn.getAttribute('data-team') || 'the team';
  var formId = isAccept ? 'leadership-accept-' + id : 'leadership-decline-' + id;

  if (isAccept) {
    nldConfirmIcon.style.background = 'rgba(22,163,74,0.1)';
    nldConfirmIcon.style.color = '#16a34a';
    nldConfirmIcon.innerHTML = '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
    nldConfirmTitle.textContent = 'Approve and review?';
    nldConfirmMsg.innerHTML = 'You are about to accept leadership of <strong>' + team + '</strong>. You will become the new team leader, and the current leader will be notified.';
    nldConfirmOk.textContent = 'Approve & Review';
    nldConfirmOk.style.background = '#16a34a';
  } else {
    nldConfirmIcon.style.background = 'rgba(239,68,68,0.1)';
    nldConfirmIcon.style.color = '#ef4444';
    nldConfirmIcon.innerHTML = '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
    nldConfirmTitle.textContent = 'Decline and review?';
    nldConfirmMsg.innerHTML = 'You are about to decline leadership of <strong>' + team + '</strong>. You will stay a member, and the current leader will be notified.';
    nldConfirmOk.textContent = 'Decline & Review';
    nldConfirmOk.style.background = '#ef4444';
  }

  nldConfirmUrl = document.getElementById(formId);
  nldConfirmOverlay.classList.add('active');
});

if (nldConfirmOk) {
  nldConfirmOk.addEventListener('click', function() {
    if (nldConfirmUrl) nldConfirmUrl.submit();
  });
}

document.addEventListener('click', function(e) {
  var btn = e.target.closest('#notif-btn');
  var dropdown = document.getElementById('notif-dropdown');
  if (!dropdown) return;

  if (btn) {
    e.stopPropagation();
    var wasClosed = !dropdown.classList.contains('open');
    dropdown.classList.toggle('open');
    if (wasClosed) {
      var ids = [{{ collect($unreadNotifications ?? [])->pluck('id')->map(fn($id) => $id)->join(',') }}];
      if (ids.length) {
        fetch('/notifications/seen', {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json' },
          body: JSON.stringify({ ids: ids })
        }).then(function() {
          var dot = document.querySelector('.notif-dot');
          if (dot) dot.remove();
        });
      }
    }
    return;
  }

  if (!e.target.closest('#notif-dropdown')) {
    dropdown.classList.remove('open');
  }

  if (e.target.closest('.notif-actions')) return;

  var item = e.target.closest('.notif-item');
  if (item) {
    e.preventDefault();
    var id = item.getAttribute('data-id');
    var href = item.getAttribute('href');
    if (href === '#' || !href) return;
    item.style.opacity = '0.5';
    fetch('/notifications/' + id + '/read', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    }).then(function() { window.location.href = href; }).catch(function() { window.location.href = href; });
  }
});

document.addEventListener('click', function(e) {
  if (!e.target.closest('#notif-mark-all-btn')) return;
  fetch('/notifications', {
    method: 'PATCH',
    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
  }).then(function(r) { return r.json(); }).then(function(data) {
    if (data.success) {
      var dot = document.querySelector('.notif-dot');
      if (dot) dot.remove();
      fetch('/notifications/seen', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: data.ids || [] })
      });
      var markBtn = document.getElementById('notif-mark-all-btn');
      if (markBtn) markBtn.remove();
      var list = document.querySelector('.notif-list');
      if (list) list.innerHTML =
        '<div class="notif-empty">' +
        '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:.3;margin-bottom:8px"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>' +
        '<div>No new notifications</div></div>';
    }
  });
});
</script>
