<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Progress Tracker - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
@vite('resources/student-ui/styles.css')
<x-dashboard.styles />
<style>
  @keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .sk-overlay { position: absolute; inset: 0; background: var(--bg); z-index: 10; }
  .sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }

/* ── The team's checklist ────────────────────────────────────────────
   Read-only: every line is closed by somebody else, so there is nothing
   here for a team to press. What it does say is who is holding each one. */
.sp-bar{width:120px;height:7px;border-radius:999px;background:var(--bg4);overflow:hidden;flex-shrink:0;margin-top:4px}
.sp-bar-fill{height:100%;border-radius:999px;background:var(--green)}
.sp-item{display:flex;gap:12px;align-items:flex-start;padding:11px 18px;border-bottom:1px solid var(--border)}
.sp-item:last-child{border-bottom:none}
.sp-dot{width:20px;height:20px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;margin-top:1px;border:1.5px solid var(--border)}
.sp-dot svg{width:11px;height:11px}
.sp-done,.sp-done_late{background:var(--green);border-color:var(--green);color:#fff}
.sp-handed_in{border-color:var(--amber);color:var(--amber)}
.sp-overdue{border-color:var(--red);color:var(--red)}
.sp-title{font-size:13px;font-weight:500;color:var(--text)}
.sp-title-done{color:var(--text3);font-weight:400}
.sp-meta{font-size:11.5px;color:var(--text3);margin-top:2px;text-transform:capitalize}
.sp-flag{font-variant-numeric:tabular-nums;font-size:8.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:1px 6px;border-radius:3px;background:var(--red-bg);color:var(--red);margin-left:7px}
</style>
@include('partials.skeleton-head', ['skReveal' => 'opacity'])
</head>
<body>
@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Progress Tracker</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'progress'])
    <div id="contentWrapper" style="position: relative;">
<div id="skeleton-content" class="sk-overlay">
      <div class="grid-4" style="margin-bottom:16px">
        @for ($i = 0; $i < 4; $i++)
        <div class="stat-chip">
          <div class="sk" style="width:30px;height:22px;border-radius:4px;margin-bottom:6px"></div>
          <div class="sk" style="width:60px;height:9px;border-radius:4px"></div>
        </div>
        @endfor
      </div>
      <div class="card">
        <div class="sk" style="width:140px;height:12px;border-radius:4px;margin-bottom:14px"></div>
        @for ($i = 0; $i < 4; $i++)
        <div style="display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)">
          <div class="sk" style="width:20px;height:20px;border-radius:50%;flex-shrink:0"></div>
          <div style="flex:1">
            <div class="sk" style="width:35%;height:12px;border-radius:4px;margin-bottom:6px"></div>
            <div class="sk" style="width:60%;height:9px;border-radius:4px"></div>
          </div>
        </div>
        @endfor
      </div>
    </div>

    <div id="real-content" style="opacity:0">
@if (session('success'))
<div style="padding:10px 14px;background:rgba(16,185,129,0.1);color:#059669;border-radius:8px;font-size:13px;margin-bottom:12px">{{ session('success') }}</div>
@endif
@if (session('error'))
<div style="padding:10px 14px;background:rgba(239,68,68,0.1);color:#dc2626;border-radius:8px;font-size:13px;margin-bottom:12px">{{ session('error') }}</div>
@endif

@if(!$teamId)
<div class="empty-state" style="text-align:center;padding:60px 20px">
  <div style="font-size:40px;margin-bottom:12px;opacity:0.3">&#128204;</div>
  <div style="font-size:15px;font-weight:600;color:var(--text2);margin-bottom:6px">No Team Yet</div>
  <div style="font-size:12px;color:var(--text3)">Join or create a team to start tracking milestones.</div>
</div>
@else
<div class="grid-4" style="margin-bottom:16px">
  <div class="stat-chip"><div class="stat-n" style="color:var(--green)">{{ $completed }}</div><div class="stat-l">Completed</div></div>
  <div class="stat-chip"><div class="stat-n" style="color:var(--amber)">{{ $pending }}</div><div class="stat-l">Pending</div></div>
  <div class="stat-chip"><div class="stat-n" style="color:var(--text3)">{{ $todo }}</div><div class="stat-l">To Do</div></div>
  <div class="stat-chip"><div class="stat-n" style="color:var(--red)">{{ $overdue }}</div><div class="stat-l">Overdue</div></div>
</div>

@if($boards->isEmpty())
<div class="card">
  <div class="card-header">
    <span class="card-title">Checklist</span>
  </div>
  <div style="padding:22px;text-align:center;color:var(--text3);font-size:13px;line-height:1.6">
    @if(!$teamId)
      Join or create a team and your checklist will appear here.
    @else
      Your CaPSA has not set up a checklist for your team yet.
    @endif
  </div>
</div>
@else
  @foreach($boards as $board)
  @php $m = $board->model; $p = $board->progress; @endphp
  <div class="card" style="margin-bottom:14px">
    <div class="card-header" style="align-items:flex-start;gap:12px;flex-wrap:wrap">
      <div style="flex:1;min-width:0">
        <span class="card-title">{{ $m->title }}</span>
        @if($m->due_date)
          <span style="font-variant-numeric:tabular-nums;font-size:11px;color:var(--text3);margin-left:8px">due {{ $m->due_date->format('j M Y') }}</span>
        @endif
        <div style="font-size:12px;color:var(--text3);margin-top:3px">
          <strong style="color:var(--text2)">{{ $p->completed }} of {{ $p->total }} complete</strong>
          @if($p->handed_in) &middot; {{ $p->handed_in }} waiting @endif
          @if($p->overdue) &middot; <span style="color:var(--red);font-weight:600">{{ $p->overdue }} overdue</span> @endif
        </div>
      </div>
      <div class="sp-bar" title="{{ $p->percent }}%"><div class="sp-bar-fill" style="width:{{ $p->percent }}%"></div></div>
    </div>

    <div style="padding:4px 0">
      @foreach($p->items as $row)
      @php $i = $row->item; $done = in_array($row->state, ['done', 'done_late'], true); @endphp
      <div class="sp-item">
        <div class="sp-dot sp-{{ $row->state }}">
          @if($done)
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2"><polyline points="20 6 9 17 4 12"/></svg>
          @elseif($row->state === 'handed_in')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>
          @elseif($row->state === 'overdue')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><line x1="12" y1="7" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/><circle cx="12" cy="12" r="9"/></svg>
          @endif
        </div>

        <div style="flex:1;min-width:0">
          <div class="sp-title {{ $done ? 'sp-title-done' : '' }}">
            {{ $i->title }}
            @if($row->state === 'done_late')
              <span class="sp-flag">{{ $row->days_late }} {{ Str::plural('day', $row->days_late) }} late</span>
            @endif
          </div>
          <div class="sp-meta">
            @if($row->closed_at)
              Done {{ $row->closed_at->format('j M Y') }}
            @elseif($row->state === 'handed_in')
              {{-- Naming who is holding it is the point: "pending" alone never
                   told a team whether to chase anybody. --}}
              <span style="color:var(--amber)">{{ match ($row->waiting_on) {
                  'team' => 'Changes requested — revise and resubmit',
                  'capsa' => 'With your CaPSA to check',
                  null => 'In progress',
                  default => 'With your '.$row->waiting_on,
              } }}</span>
            @elseif($row->state === 'overdue')
              <span style="color:var(--red)">Overdue{{ $row->due_on ? ' since '.$row->due_on->format('j M') : '' }}</span>
            @elseif($row->due_on)
              Due {{ $row->due_on->format('j M Y') }}
            @else
              Not started
            @endif
            &middot; {{ $i->kind === 'custom' ? 'set by your CaPSA' : $i->kind }}
          </div>
        </div>
      </div>
      @endforeach
    </div>
  </div>
  @endforeach
@endif
@endif
    </div>
</div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var sk = document.getElementById('skeleton-content');
  var rc = document.getElementById('real-content');
  if (sk && rc) {
    // partials/skeleton-head already decided this in <head>, before the first
    // paint: the content is on screen and no skeleton was ever shown. Without
    // this, every save replayed the 600ms fade over content already there.
    if (document.documentElement.classList.contains('sk-seen')) {
      sk.remove();

      return;
    }

    requestAnimationFrame(function () {
      setTimeout(function () {
        sk.style.transition = 'opacity 0.3s ease';
        sk.style.opacity = '0';
        rc.style.transition = 'opacity 0.35s ease';
        rc.style.opacity = '1';
        setTimeout(function () {
          sk.remove();
          rc.style.transition = '';
        }, 350);
        try {
          sessionStorage.setItem('sk-seen:{{ substr(sha1(session()->getId()), 0, 12) }}:' + window.location.pathname, '1');
        } catch (e) {}
      }, 600);
    });
  }
});

</script>

@include('partials.escape-closes-modals')
</body>
</html>