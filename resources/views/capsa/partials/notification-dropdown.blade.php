@php
  // Whose activity this viewer may see - see App\Support\ActivityScope. The
  // bell used to list every student in the school regardless of the sections
  // the CaPSA actually covers.
  $visibleIds = App\Support\ActivityScope::studentIdsFor(auth('expert')->user());

  $recentLogs = App\Models\ActivityLog::when(
      $visibleIds !== null,
      fn ($q) => $q->whereIn('student_id', $visibleIds)
    )
    ->latest('created_at')
    ->take(20)
    ->get();

  $allLogIds = $recentLogs->pluck('id')->toArray();
  $seenLogIds = session('admin_notification_seen_ids', []);
  $unseenCount = count(array_diff($allLogIds, $seenLogIds));
@endphp
<button class="notif-btn" id="notif-btn" type="button" aria-label="Notifications">
  <svg viewBox="0 0 24 24" style="pointer-events:none"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
  @if ($unseenCount > 0)
    <div class="notif-dot">{{ $unseenCount > 9 ? '9+' : $unseenCount }}</div>
  @endif
</button>
<div class="notif-dropdown" id="notif-dropdown">
  <div class="notif-dropdown-header">
    <div class="notif-dropdown-title">Recent Activity</div>
    @if (count($recentLogs) > 0)
      <button class="notif-mark-all-btn" id="notif-mark-all-btn">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 6 9 17 4 12"/></svg>
        <span>Mark all read</span>
      </button>
    @endif
  </div>
  <div class="notif-list">
    @forelse($recentLogs as $log)
      @php
        $logIcon = match ($log->type) {
          'submitted_document' => ['bg' => 'rgba(22,163,74,0.12)', 'fg' => '#16a34a', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>'],
          'booked_consultation' => ['bg' => 'rgba(59,130,246,0.12)', 'fg' => '#3b82f6', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>'],
          'applied_expert' => ['bg' => 'rgba(124,58,237,0.12)', 'fg' => '#7c3aed', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'],
          'submitted_receipt' => ['bg' => 'rgba(245,158,11,0.12)', 'fg' => '#f59e0b', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1z"/><polyline points="8 7 16 7"/><polyline points="8 11 16 11"/><polyline points="8 15 12 15"/></svg>'],
          'ran_duplication_check' => ['bg' => 'rgba(6,182,212,0.12)', 'fg' => '#06b6d4', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/></svg>'],
          'updated_milestone' => ['bg' => 'rgba(251,191,36,0.12)', 'fg' => '#fbbf24', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>'],
          default => ['bg' => 'rgba(100,116,139,0.12)', 'fg' => '#64748b', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'],
        };
        $logDesc = Str::limit($log->description, 80);
      @endphp
      <div class="notif-item" data-id="{{ $log->id }}">
        <div class="notif-item-icon" style="background:{{ $logIcon['bg'] }};color:{{ $logIcon['fg'] }}">{!! $logIcon['svg'] !!}</div>
        <div class="notif-item-body">
          <div class="notif-item-title">{{ str_replace('_', ' ', ucwords($log->type, '_')) }}</div>
          <div class="notif-item-msg">{!! $logDesc !!}</div>
          <div class="notif-item-time">{{ $log->created_at->diffForHumans() }}</div>
        </div>
      </div>
    @empty
      <div class="notif-empty">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:.3;margin-bottom:8px"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div>No activity yet</div>
      </div>
    @endforelse
  </div>
</div>

<style>
.topbar-actions{position:relative}
.notif-dot{position:absolute;top:-4px;right:-4px;min-width:18px;height:18px;border-radius:9px;background:#ef4444;border:2px solid var(--navy-mid);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;line-height:1;padding:0 4px}

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

.notif-item{display:flex;align-items:flex-start;gap:12px;padding:12px 16px;text-decoration:none;transition:background .1s;border-bottom:1px solid var(--border);position:relative;cursor:pointer}
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
document.addEventListener('click', function(e) {
  var btn = e.target.closest('#notif-btn');
  var dropdown = document.getElementById('notif-dropdown');
  if (!dropdown) return;

  if (btn) {
    e.stopPropagation();
    var wasClosed = !dropdown.classList.contains('open');
    dropdown.classList.toggle('open');
    if (wasClosed) {
      var ids = [{{ $recentLogs->pluck('id')->join(',') }}];
      if (ids.length) {
        fetch('/capsa/notifications/seen', {
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
});

document.addEventListener('click', function(e) {
  if (!e.target.closest('#notif-mark-all-btn')) return;
  fetch('/capsa/notifications/seen', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json' },
    body: JSON.stringify({ ids: [{{ $recentLogs->pluck('id')->join(',') }}] })
  }).then(function() {
    var dot = document.querySelector('.notif-dot');
    if (dot) dot.remove();
    var markBtn = document.getElementById('notif-mark-all-btn');
    if (markBtn) markBtn.remove();
    var list = document.querySelector('.notif-list');
    if (list) list.innerHTML =
      '<div class="notif-empty">' +
      '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:.3;margin-bottom:8px"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>' +
      '<div>No activity yet</div></div>';
  });
});
</script>
