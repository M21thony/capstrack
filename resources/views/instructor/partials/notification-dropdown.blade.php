@php
  // Every instructor page carried a bell that was a <div> with no id, no
  // handler and a dot that was lit whether or not anything was waiting. This
  // is the same control the CaPSA pages use, with the one thing an expert
  // actually gets notified about: teams asking them to take a role.
  $expert = auth('expert')->user();

  $waiting = $expert
    ? App\Models\TeamExpertAssignment::where('expert_id', $expert->expert_id)
        ->where('status', 'pending')
        ->with(['team', 'expertRole.role'])
        ->latest('created_at')
        ->take(20)
        ->get()
    : collect();
@endphp

<div class="notif-wrap">
<button class="notif-btn" id="notif-btn" type="button" aria-label="Notifications" aria-expanded="false">
  <svg viewBox="0 0 24 24" style="pointer-events:none"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
  @if ($waiting->isNotEmpty())
    <div class="notif-dot">{{ $waiting->count() > 9 ? '9+' : $waiting->count() }}</div>
  @endif
</button>

<div class="notif-dropdown" id="notif-dropdown">
  <div class="notif-dropdown-header">
    <div class="notif-dropdown-title">Applications waiting</div>
    @if ($waiting->isNotEmpty())
      <span class="notif-count">{{ $waiting->count() }}</span>
    @endif
  </div>

  <div class="notif-list">
    @forelse ($waiting as $a)
      @php $role = $a->engaged_role_name; @endphp
      <a class="notif-item" href="{{ route('instructor.assignments') }}">
        <span class="notif-item-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </span>
        <span class="notif-item-body">
          <span class="notif-item-title">{{ $a->team?->team_name ?? 'A team' }}</span>
          <span class="notif-item-msg">asked you to be their {{ $role ?: 'expert' }}</span>
          <span class="notif-item-time">{{ $a->created_at?->diffForHumans() ?? '' }}</span>
        </span>
      </a>
    @empty
      <p class="notif-empty">Nothing waiting. Teams that apply to you will show up here.</p>
    @endforelse
  </div>

  @if ($waiting->isNotEmpty())
    <a class="notif-footer" href="{{ route('instructor.assignments') }}">Review all applications</a>
  @endif
</div>
</div>

<style>
/* The panel hangs off the bell. Without a positioned wrapper of its own it
   measured against <body> and appeared most of a page below the button - the
   host pages do not set position on their toolbar. */
.notif-wrap{position:relative;display:inline-flex}
.notif-btn{position:relative}
.notif-dot{
  position:absolute;top:-3px;right:-3px;min-width:15px;height:15px;padding:0 3px;border-radius:8px;
  background:var(--red,#ef4444);color:#fff;font-size:9px;font-weight:700;line-height:15px;text-align:center;
  border:none;
}
.notif-dropdown{
  position:absolute;top:calc(100% + 8px);right:0;width:340px;max-height:420px;overflow-y:auto;
  background:var(--bg2,#fff);border:1px solid var(--border,#e5e5e0);border-radius:12px;
  box-shadow:0 8px 30px rgba(0,0,0,0.14);z-index:9999;display:none;text-align:left;
}
.notif-dropdown.open{display:block}
.notif-dropdown-header{
  display:flex;align-items:center;justify-content:space-between;gap:8px;
  padding:13px 15px 10px;border-bottom:1px solid var(--border,#e5e5e0);
}
.notif-dropdown-title{font-size:13.5px;font-weight:700;color:var(--text,#1c1b18)}
.notif-count{
  font-size:10.5px;font-weight:700;color:#fff;background:var(--red,#ef4444);
  padding:2px 7px;border-radius:10px;
}
.notif-list{padding:6px}
.notif-item{
  display:flex;gap:10px;padding:9px 10px;border-radius:9px;text-decoration:none;color:inherit;
}
.notif-item:hover{background:var(--bg3,#f4f4f1)}
.notif-item-icon{
  width:30px;height:30px;flex-shrink:0;border-radius:8px;display:grid;place-items:center;
  background:rgba(124,58,237,0.12);color:#7c3aed;
}
.notif-item-icon svg{width:15px;height:15px}
.notif-item-body{display:flex;flex-direction:column;min-width:0}
.notif-item-title{font-size:12.5px;font-weight:700;color:var(--text,#1c1b18)}
.notif-item-msg{font-size:12px;color:var(--text2,#4a4842);line-height:1.4}
.notif-item-time{font-size:10.5px;color:var(--text3,#6f6d66);margin-top:2px}
.notif-empty{margin:0;padding:22px 16px;font-size:12.5px;line-height:1.5;color:var(--text3,#6f6d66);text-align:center}
.notif-footer{
  display:block;padding:11px;border-top:1px solid var(--border,#e5e5e0);text-align:center;
  font-size:12.5px;font-weight:600;color:var(--accent3,#8a6f10);text-decoration:none;
}
.notif-footer:hover{background:var(--bg3,#f4f4f1)}
</style>

<script>
// The bell is a real control now: a button with an id, and a handler that
// opens the panel and closes it when you click away or press Escape.
document.addEventListener('click', function (e) {
  var dropdown = document.getElementById('notif-dropdown');
  if (!dropdown) return;

  var btn = e.target.closest('#notif-btn');

  if (btn) {
    var open = dropdown.classList.toggle('open');
    btn.setAttribute('aria-expanded', String(open));
    return;
  }

  if (!e.target.closest('#notif-dropdown')) {
    dropdown.classList.remove('open');
    var b = document.getElementById('notif-btn');
    if (b) b.setAttribute('aria-expanded', 'false');
  }
});

document.addEventListener('keydown', function (e) {
  if (e.key !== 'Escape') return;
  var dropdown = document.getElementById('notif-dropdown');
  if (dropdown) dropdown.classList.remove('open');
  var b = document.getElementById('notif-btn');
  if (b) b.setAttribute('aria-expanded', 'false');
});
</script>
