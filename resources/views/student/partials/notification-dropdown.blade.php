@php
  $allUnreadIds = collect($unreadNotifications ?? [])->pluck('id')->toArray();
  $seenNotifIds = session('student_seen_notification_ids', []);
  $unseenNotifCount = count(array_diff($allUnreadIds, $seenNotifIds));
@endphp
{{-- Before the markup, not after it: a style further down arrives after
     the browser may already have painted the panel and its popups,
     which then flashed full-size on a refresh. --}}
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

/* ── The CaPSA's decision on a leave request ── */
.ld-overlay{display:none;position:fixed;inset:0;z-index:99990;align-items:center;justify-content:center;padding:20px;background:rgba(15,16,22,.6);backdrop-filter:blur(3px)}
.ld-overlay.show{display:flex}
.ld-card{width:min(480px,100%);max-height:calc(100vh - 40px);max-height:calc(100dvh - 40px);overflow:auto;background:#fff;border-radius:18px;box-shadow:0 24px 70px rgba(0,0,0,.3);display:flex;flex-direction:column}
.ld-head{display:flex;align-items:flex-start;gap:12px;padding:20px 20px 16px;border-bottom:1px solid var(--border);border-top:5px solid var(--text3)}
.ld-head.is-approved{border-top-color:var(--green)}
.ld-head.is-declined{border-top-color:var(--red)}
.ld-icon{width:40px;height:40px;flex:none;border-radius:12px;display:grid;place-items:center;background:var(--bg3);color:var(--text3)}
.ld-icon svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round}
.ld-head.is-approved .ld-icon{background:var(--green-bg);color:var(--green)}
.ld-head.is-declined .ld-icon{background:var(--red-bg);color:var(--red)}
.ld-head-text{flex:1;min-width:0}
.ld-status{display:inline-block;font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:2px 8px;border-radius:999px;background:var(--bg3);color:var(--text3)}
.ld-head.is-approved .ld-status{background:var(--green-bg);color:var(--green)}
.ld-head.is-declined .ld-status{background:var(--red-bg);color:var(--red)}
.ld-head h3{margin:6px 0 0;font-size:16.5px;font-weight:700;color:var(--text)}
.ld-head p{margin:3px 0 0;font-size:12.5px;line-height:1.45;color:var(--text2)}
.ld-x{width:32px;height:32px;flex:none;display:grid;place-items:center;border:1px solid var(--border);border-radius:9px;background:#fff;color:var(--text3);cursor:pointer}
.ld-x svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2.2}
.ld-body{padding:16px 20px;display:grid;gap:14px}
.ld-label{font-size:10.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text3);margin-bottom:5px}
.ld-quote{padding:12px 14px;border-radius:12px;background:var(--bg3);border-left:4px solid var(--accent)}
.ld-quote p{margin:0;font-size:14px;line-height:1.55;color:var(--text);white-space:pre-line}
.ld-quote p.is-empty{font-style:italic;color:var(--text3)}
.ld-by{margin-top:6px;font-size:12px;color:var(--text3)}
.ld-rows{margin:0;border:1px solid var(--border);border-radius:12px;overflow:hidden}
.ld-rows > div{display:flex;justify-content:space-between;gap:14px;padding:9px 14px;border-bottom:1px solid var(--border);font-size:13px}
.ld-rows > div:last-child{border-bottom:0}
.ld-rows > div[hidden]{display:none}
.ld-rows dt{color:var(--text3);flex:none}
.ld-rows dd{margin:0;text-align:right;font-weight:600;color:var(--text);min-width:0;overflow-wrap:anywhere}
.ld-own p{margin:0;font-size:13px;line-height:1.55;color:var(--text2);white-space:pre-line}
.ld-foot{display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid var(--border);background:var(--bg3)}
.ld-btn{display:inline-flex;align-items:center;justify-content:center;padding:9px 16px;border-radius:9px;border:1px solid var(--border);background:#fff;color:var(--text2);font:inherit;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none}
.ld-btn-primary{background:var(--accent);border-color:var(--accent);color:#18191f}
@media (max-width:560px){
  .ld-overlay{padding:0;align-items:flex-end}
  .ld-card{width:100%;max-height:92vh;max-height:92dvh;border-radius:18px 18px 0 0}
  .ld-foot{padding-bottom:calc(14px + env(safe-area-inset-bottom))}
  .ld-foot .ld-btn{flex:1}
}
/* Not yet read keeps a tint, so read and unread sit in one list and still
   read apart at a glance - the same as the expert bell. */
.notif-item.is-unread{background:rgba(37,99,235,0.05)}
.notif-item.is-unread .notif-item-title{font-weight:700}

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

.notif-ask{display:flex;align-items:center;gap:10px;padding:10px 16px;background:rgba(37,99,235,0.06);border-bottom:1px solid var(--border)}
.notif-ask-text{flex:1;font-size:11.5px;color:var(--text2);line-height:1.4}
.notif-ask-btn{padding:6px 12px;border:none;border-radius:6px;background:#2563eb;color:#fff;font-size:11px;font-weight:700;cursor:pointer;flex-shrink:0}
.notif-ask-btn:hover{background:#1d4ed8}
</style>

{{-- Nothing on screen: just where capstrack-push.js reads the keys from. It is
     a bare element rather than a wrapper round the bell because the bell and
     its panel are already positioned by the page, and wrapping them would move
     the panel. --}}
<span id="notif-wrap" hidden
      data-vapid="{{ config('services.webpush.public_key') }}"
      data-subscribe="{{ route('push.subscribe') }}"
      data-csrf="{{ csrf_token() }}"></span>
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
  {{-- Hidden on load. capstrack-push.js shows it only when the browser has
       never been asked, so nobody gets a permission box they did not click. --}}
  <div class="notif-ask" id="notif-ask" style="display:none">
    <div class="notif-ask-text">Get these on your desktop, even when CapsTrack is closed.</div>
    <button type="button" class="notif-ask-btn" id="notif-ask-btn">Turn on</button>
  </div>
  <div class="notif-list">
    @forelse($bellNotifications ?? $unreadNotifications ?? [] as $notif)
      @php
        $notifIcon = match ($notif->type) {
          'team_created', 'team_confirmed', 'team_verified' => ['bg' => 'rgba(22,163,74,0.12)', 'fg' => '#16a34a', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>'],
          'member_joined', 'request_accepted' => ['bg' => 'rgba(124,58,237,0.12)', 'fg' => '#7c3aed', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>'],
          'member_removed', 'member_left', 'expert_removed' => ['bg' => 'rgba(239,68,68,0.12)', 'fg' => '#ef4444', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'],
          'invitation_received', 'request_received' => ['bg' => 'rgba(59,130,246,0.12)', 'fg' => '#3b82f6', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'],
          'invitation_declined', 'request_declined' => ['bg' => 'rgba(245,158,11,0.12)', 'fg' => '#f59e0b', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'],
          'leadership_transferred' => ['bg' => 'rgba(251,191,36,0.15)', 'fg' => '#fbbf24', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'],
          'announcement' => ['bg' => 'rgba(249,115,22,0.12)', 'fg' => '#f97316', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>'],
          'review_finished' => ['bg' => 'rgba(22,163,74,0.12)', 'fg' => '#16a34a', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 15 11 17 15 13"/></svg>'],
          'milestone_due' => ['bg' => 'rgba(245,158,11,0.12)', 'fg' => '#f59e0b', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>'],
          'checklist_item_added' => ['bg' => 'rgba(201,168,44,0.14)', 'fg' => '#c9a82c', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4.5H6a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-5-5h-2"/><rect x="8" y="2.5" width="8" height="4" rx="1"/><line x1="12" y1="12" x2="12" y2="17"/><line x1="9.5" y1="14.5" x2="14.5" y2="14.5"/></svg>'],
          'consultation_reminder' => ['bg' => 'rgba(59,130,246,0.12)', 'fg' => '#3b82f6', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="12 13 12 16 14 17"/></svg>'],
          default => ['bg' => 'rgba(100,116,139,0.12)', 'fg' => '#64748b', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'],
        };
      @endphp
      {{-- Approve/Decline only while it is still waiting: once read it is a record. --}}
      @if($notif->type === 'leadership_transfer_request' && ! $notif->is_read)
        <div class="notif-item notif-item--action" data-id="{{ $notif->id }}" data-type="{{ $notif->type }}">
          <div class="notif-item-icon" style="background:{{ $notifIcon['bg'] }};color:{{ $notifIcon['fg'] }}">{!! $notifIcon['svg'] !!}</div>
          <div class="notif-item-body">
            <div class="notif-item-title">{{ $notif->title }}</div>
            <div class="notif-item-msg">{!! $notif->message !!}</div>
            <div class="notif-item-time" data-at="{{ $notif->created_at->toIso8601String() }}">{{ $notif->created_at->diffForHumans() }}</div>
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
      {{-- A CaPSA's decision on a leave request carries the whole of it, for
           the pop-up that opens instead of the Team page. --}}
      <a href="{{ $notif->link ?? '#' }}" class="notif-item {{ $notif->is_read ? '' : 'is-unread' }}" data-id="{{ $notif->id }}" data-type="{{ $notif->type }}"
         @isset($leaveDecisions[$notif->id]) data-leave="{{ json_encode($leaveDecisions[$notif->id]) }}" @endisset>
        <div class="notif-item-icon" style="background:{{ $notifIcon['bg'] }};color:{{ $notifIcon['fg'] }}">{!! $notifIcon['svg'] !!}</div>
        <div class="notif-item-body">
          <div class="notif-item-title">{{ $notif->title }}</div>
          <div class="notif-item-msg">{!! $notif->message !!}</div>
          <div class="notif-item-time" data-at="{{ $notif->created_at->toIso8601String() }}">{{ $notif->created_at->diffForHumans() }}</div>
        </div>
      </a>
      @endif
    @empty
      <div class="notif-empty">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:.3;margin-bottom:8px"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div>No notifications yet</div>
      </div>
    @endforelse
  </div>
</div>

{{-- The CaPSA's decision on a leave or removal request, opened from its
     notification: the decision, their reason, who and when, and the request
     it answers. Filled in by openLeaveDecision() below, as text only. --}}
<div class="ld-overlay" id="ld-overlay" onclick="if (event.target === this) closeLeaveDecision()">
  <div class="ld-card" role="dialog" aria-modal="true" aria-labelledby="ld-title">
    <div class="ld-head" id="ld-head">
      <span class="ld-icon" id="ld-icon" aria-hidden="true"></span>
      <div class="ld-head-text">
        <span class="ld-status" id="ld-status"></span>
        <h3 id="ld-title"></h3>
        <p id="ld-sub"></p>
      </div>
      <button type="button" class="ld-x" aria-label="Close" onclick="closeLeaveDecision()">
        <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="ld-body">
      <div class="ld-quote">
        <div class="ld-label">Reason from your CaPSA</div>
        <p id="ld-note"></p>
        <div class="ld-by" id="ld-by"></div>
      </div>
      <dl class="ld-rows">
        <div><dt>Team</dt><dd id="ld-team"></dd></div>
        <div><dt>Request</dt><dd id="ld-kind"></dd></div>
        <div id="ld-member-row"><dt>Member</dt><dd id="ld-member"></dd></div>
        <div><dt>Asked by</dt><dd id="ld-requested-by"></dd></div>
        <div><dt>Asked on</dt><dd id="ld-requested-on"></dd></div>
        <div><dt>Decided on</dt><dd id="ld-decided-on"></dd></div>
      </dl>
      <div class="ld-own">
        <div class="ld-label">The reason given with the request</div>
        <p id="ld-reason"></p>
      </div>
    </div>
    <div class="ld-foot">
      <button type="button" class="ld-btn" onclick="closeLeaveDecision()">Close</button>
      <a class="ld-btn ld-btn-primary" id="ld-go" href="#">Go to Team</a>
    </div>
  </div>
</div>

<!-- Leadership Review Confirm Modal -->
<div class="nld-confirm-overlay" id="nld-confirm-overlay" onclick="if(event.target==this) nldConfirmClose()">
  <div class="nld-confirm-card">
    <div class="nld-confirm-icon" id="nld-confirm-icon" style="background:rgba(22,163,74,0.1);color:#16a34a;">
      <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <div class="nld-confirm-title" id="nld-confirm-title">Accept Transfer</div>
    <div class="nld-confirm-msg" id="nld-confirm-msg"></div>
    <div class="nld-confirm-actions">
      <button type="button" class="nld-confirm-btn nld-confirm-cancel" onclick="nldConfirmClose()">No</button>
      <button type="button" class="nld-confirm-btn" id="nld-confirm-ok" style="background:#16a34a;">Yes</button>
    </div>
  </div>
</div>

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
    nldConfirmTitle.textContent = 'Accept Transfer';
    nldConfirmMsg.innerHTML = 'You are about to accept leadership of <strong>' + team + '</strong>. You will become the new team leader, and the current leader will be notified.';
    nldConfirmOk.textContent = 'Yes';
    nldConfirmOk.style.background = '#16a34a';
  } else {
    nldConfirmIcon.style.background = 'rgba(239,68,68,0.1)';
    nldConfirmIcon.style.color = '#ef4444';
    nldConfirmIcon.innerHTML = '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
    nldConfirmTitle.textContent = 'Decline';
    nldConfirmMsg.innerHTML = 'You are about to decline leadership of <strong>' + team + '</strong>. You will stay a member, and the current leader will be notified.';
    nldConfirmOk.textContent = 'Yes';
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
          method: 'PUT',
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

    // The CaPSA's decision on a leave request: read here, in full, rather
    // than on a page that only shows what is still waiting.
    if (item.dataset.leave) {
      fetch('/notifications/' + id + '/read', {
        method: 'PUT',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
      });
      item.classList.remove('is-unread');
      dropdown.classList.remove('open');
      openLeaveDecision(JSON.parse(item.dataset.leave), href);
      return;
    }
    if (href === '#' || !href) return;
    item.style.opacity = '0.5';
    fetch('/notifications/' + id + '/read', {
      method: 'PUT',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    }).then(function() { window.location.href = href; }).catch(function() { window.location.href = href; });
  }
});

// ── The CaPSA's decision on a leave request, in full ──
// Everything goes in as text: the reasons are what people typed.
function openLeaveDecision(d, href) {
  var approved = d.status === 'approved';
  var leave = d.kind === 'Request to leave the team';
  var set = function (id, text) { document.getElementById(id).textContent = text || '—'; };

  var head = document.getElementById('ld-head');
  head.classList.toggle('is-approved', approved);
  head.classList.toggle('is-declined', !approved);
  document.getElementById('ld-icon').innerHTML = approved
    ? '<svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>'
    : '<svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>';
  set('ld-status', approved ? 'Approved' : 'Declined');
  set('ld-title', leave
    ? (approved ? 'You may leave the team' : 'You are staying on the team')
    : (approved ? 'The member was removed' : 'The member stays on the team'));
  set('ld-sub', leave
    ? (approved ? 'Your CaPSA approved your request to leave ' + (d.team || 'your team') + '.' : 'Your CaPSA declined your request to leave ' + (d.team || 'your team') + '.')
    : (approved ? 'Your CaPSA approved the request to remove ' + (d.member || 'the member') + '.' : 'Your CaPSA declined the request to remove ' + (d.member || 'the member') + '.'));

  var note = document.getElementById('ld-note');
  note.textContent = d.decisionNote || 'No reason was written with this decision.';
  note.classList.toggle('is-empty', !d.decisionNote);
  set('ld-by', '— ' + (d.decidedBy || 'Your CaPSA') + ', CaPSA');

  set('ld-team', d.team);
  set('ld-kind', d.kind);
  document.getElementById('ld-member-row').hidden = leave;
  set('ld-member', d.member);
  set('ld-requested-by', d.requestedBy);
  set('ld-requested-on', d.requestedOn);
  set('ld-decided-on', d.decidedOn);
  set('ld-reason', d.reason);

  var go = document.getElementById('ld-go');
  go.href = href && href !== '#' ? href : '/student/team';

  document.getElementById('ld-overlay').classList.add('show');
  document.body.style.overflow = 'hidden';
  document.querySelector('#ld-overlay .ld-x').focus();
}

function closeLeaveDecision() {
  document.getElementById('ld-overlay').classList.remove('show');
  document.body.style.overflow = '';
}

document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape' && document.getElementById('ld-overlay').classList.contains('show')) closeLeaveDecision();
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
        method: 'PUT',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: data.ids || [] })
      });
      var markBtn = document.getElementById('notif-mark-all-btn');
      if (markBtn) markBtn.remove();
      // Read, not removed: the notifications stay in the list, without the
      // unread highlight. It used to empty the list, so they looked deleted.
      document.querySelectorAll('#notif-dropdown .notif-item.is-unread').forEach(function (el) {
        el.classList.remove('is-unread');
      });
    }
  });
});
</script>

@auth('web')
  @include('partials.notification-toasts', [
    'toastFeed' => route('notifications.index'),
    'toastRead' => url('notifications'),
    'toastOwner' => 's'.auth('web')->id(),
  ])
@endauth

<script src="/js/capstrack-push.js" defer></script>
<script>
// Deferred scripts finish before this fires, so CapsTrackPush is there by now.
document.addEventListener('DOMContentLoaded', function () {
  var ask = document.getElementById('notif-ask');
  var askBtn = document.getElementById('notif-ask-btn');
  if (!ask || !askBtn || !window.CapsTrackPush || !CapsTrackPush.supported) return;

  // 'default' means this browser has never been asked. Already granted or
  // already refused - leave the strip hidden either way.
  if (CapsTrackPush.state() === 'default') ask.style.display = 'flex';

  askBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    askBtn.disabled = true;
    askBtn.textContent = 'Wait...';
    CapsTrackPush.enable().then(function (permission) {
      if (permission !== 'denied') {
        ask.style.display = 'none';
        return;
      }

      // The strip stays, saying what happened where the button was.
      var text = ask.querySelector('.notif-ask-text');
      if (text) {
        text.textContent = 'Your browser blocked notifications. Turn them back on for CapsTrack in its site settings, then try again.';
      }
      askBtn.remove();
    });
  });
});
</script>
