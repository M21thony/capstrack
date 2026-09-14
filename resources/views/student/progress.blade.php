<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Progress Tracker - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/styles.css')
<x-dashboard.styles />
<style>
  @keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .sk-overlay { position: absolute; inset: 0; background: var(--bg); z-index: 10; }
  .sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }

/* ── Our milestones ───────────────────────────────────────────────────
   Read-only: every deliverable is closed by somebody else. The team's own
   milestones, one dropdown open at a time; only the list scrolls. */
.spm-band{display:flex;align-items:center;gap:12px 26px;flex-wrap:wrap;background:var(--accent-light);border:1px solid #e3d08f;border-radius:11px;padding:10px 16px;margin-bottom:12px}
.spm-now{display:grid;line-height:1.15}
.spm-now small{font-size:10.5px;text-transform:uppercase;letter-spacing:.08em;color:var(--accent3);font-weight:700}
.spm-now b{font-size:23px;letter-spacing:-.01em;color:var(--text)}
.spm-now span{font-size:12.5px;color:var(--text2)}
.spm-stats{display:flex;gap:24px;margin-left:auto;flex-wrap:wrap}
.spm-stat b{display:block;font-size:17.5px;line-height:1.2;font-variant-numeric:tabular-nums;color:var(--text)}
.spm-stat span{font-size:11.5px;color:var(--text3)}
.spm-stat.red b{color:var(--red)} .spm-stat.gold b{color:var(--accent3)}
.spm-panel{background:var(--bg2);border:1px solid var(--border);border-radius:11px;overflow:hidden}
.spm-head{display:flex;align-items:center;gap:8px;padding:9px 16px;border-bottom:1px solid var(--border);font-size:12.5px;color:var(--text3)}
.spm-head b{font-size:14px;color:var(--text)}
.spm-list{max-height:calc(100vh - 290px);min-height:260px;overflow:auto;overscroll-behavior:contain}
.spm-group{border-bottom:1px solid var(--border)}
.spm-group summary{list-style:none;display:grid;grid-template-columns:18px minmax(160px,1fr) 110px 120px 150px;gap:10px;align-items:center;padding:9px 16px;cursor:pointer;font-size:12.5px;color:var(--text2)}
.spm-group summary::-webkit-details-marker{display:none}
.spm-group summary:hover{background:var(--bg3)}
.spm-group summary b{font-size:14.5px;color:var(--text)}
.spm-group.now summary{background:var(--accent-light);box-shadow:inset 3px 0 0 var(--accent)}
.spm-chev{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;transition:transform .15s}
.spm-group[open] .spm-chev{transform:rotate(90deg)}
.spm-pill{display:inline-flex;font-size:11.5px;font-weight:600;padding:1px 8px;border-radius:999px;white-space:nowrap;justify-self:start}
.spm-pill.red{background:var(--red-bg);color:var(--red)} .spm-pill.grn{background:var(--green-bg);color:var(--green)} .spm-pill.gold{background:#f5ecd0;color:var(--accent3)}
.spm-item{display:grid;grid-template-columns:16px minmax(0,1fr) 210px auto;gap:11px;align-items:center;padding:8px 16px 8px 46px;border-top:1px solid var(--border)}
.spm-dot{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.spm-done{color:var(--green)} .spm-handed_in{color:var(--amber)} .spm-overdue{color:var(--red)} .spm-not_started{color:var(--text3)}
.spm-title{font-weight:600;font-size:13px;color:var(--text)}
.spm-flag{font-size:10.5px;font-weight:700;padding:1px 6px;border-radius:4px;background:var(--red-bg);color:var(--red);margin-left:6px}
.spm-who{font-size:12px;color:var(--text3)}
.spm-note{display:block;font-size:12px;color:var(--amber)}
.spm-status{font-size:12.5px;color:var(--text2)}
.spm-upload{font-size:12px;font-weight:600;color:var(--text);border:1px solid var(--border2);border-radius:6px;padding:5px 11px;text-decoration:none;white-space:nowrap}
.spm-upload:hover{border-color:var(--accent)}
.spm-upload.urgent{background:var(--accent);border-color:var(--accent);color:#18191f}
@media (max-width:900px){
  .spm-group summary{grid-template-columns:18px 1fr auto}
  .spm-group summary > span:nth-of-type(n+3){display:none}
  .spm-item{grid-template-columns:16px 1fr;padding-left:24px}
  .spm-item > .spm-status,.spm-item > a{grid-column:2}
}
@media (prefers-reduced-motion:reduce){.spm-chev{transition:none}}
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
    <div class="page-title">Our milestones</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'progress'])
    <div id="contentWrapper" style="position: relative;">
<div id="skeleton-content" class="sk-overlay">
      <div class="sk" style="height:64px;border-radius:11px;margin-bottom:12px"></div>
      <div class="card">
        @for ($i = 0; $i < 5; $i++)
        <div style="display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--border)">
          <div class="sk" style="width:18px;height:18px;border-radius:50%;flex-shrink:0"></div>
          <div class="sk" style="width:40%;height:12px;border-radius:4px"></div>
        </div>
        @endfor
      </div>
    </div>

    <div id="real-content" style="opacity:0">

@if (! $teamId)
<div class="empty-state" style="text-align:center;padding:60px 20px">
  <div style="font-size:15px;font-weight:600;color:var(--text2);margin-bottom:6px">No team yet</div>
  <div style="font-size:12px;color:var(--text3)">Join or create a team and your milestones will appear here.</div>
</div>
@elseif (! $progress)
<div class="card">
  <div class="card-header"><span class="card-title">Our milestones</span></div>
  <div style="padding:22px;text-align:center;color:var(--text3);font-size:13px;line-height:1.6">
    Your CaPSA has not set up a checklist for your team yet.
  </div>
</div>
@else
@php
  $ord = fn (int $n) => $n.(in_array($n % 100, [11, 12, 13], true) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th'));
  $icons = [
      'done' => '<circle cx="12" cy="12" r="9"/><polyline points="8 12.5 11 15.5 16 9.5"/>',
      'handed_in' => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>',
      'overdue' => '<circle cx="12" cy="12" r="9"/><line x1="12" y1="7.5" x2="12" y2="13"/><circle cx="12" cy="16.3" r=".6" fill="currentColor"/>',
      'not_started' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/>',
  ];
@endphp

<div class="spm-band">
  <div class="spm-now">
    <small>Your team</small>
    <b>{{ $team->team_name }}</b>
    <span>{{ $section ?: 'No section yet' }} · Adviser {{ $adviser ?: 'not chosen yet' }} · Consultant {{ $consultant ?: 'not chosen yet' }}</span>
  </div>
  <div class="spm-stats">
    <div class="spm-stat"><b>{{ $progress->percent }}%</b><span>{{ $progress->completed }} of {{ $progress->total }} closed</span></div>
    <div class="spm-stat red"><b>{{ $progress->overdue }}</b><span>overdue</span></div>
    <div class="spm-stat"><b>{{ $progress->handed_in }}</b><span>waiting on someone else</span></div>
    <div class="spm-stat gold">
      <b>{{ $next ? $next->due_on->format('M j') : '—' }}</b>
      <span>{{ $next ? 'Next due: '.$next->item->title : 'Nothing left to do' }}</span>
    </div>
  </div>
</div>

<div class="spm-panel">
  <div class="spm-head"><b>Milestones</b><span>in order · open one to see its deliverables</span></div>
  <div class="spm-list">
    @foreach ($groups as $group)
      @php
        $isNow = $current === $group;
        $dues = $group->dues;
      @endphp
      {{-- details with a shared name: opening one closes the one that was open. --}}
      <details class="spm-group {{ $isNow ? 'now' : '' }}" name="our-milestones" @if ($isNow) open @endif>
        <summary>
          <svg class="spm-chev" viewBox="0 0 24 24" aria-hidden="true"><polyline points="9 6 15 12 9 18"/></svg>
          <b>{{ $group->name }}</b>
          @if ($group->overdue)
            <span class="spm-pill red">{{ $group->overdue }} overdue</span>
          @elseif ($group->closed === $group->rows->count())
            <span class="spm-pill grn">Done</span>
          @elseif ($isNow)
            <span class="spm-pill gold">You are here</span>
          @else
            <span></span>
          @endif
          <span>{{ $group->closed }} of {{ $group->rows->count() }} closed</span>
          <span>
            @if ($dues->isEmpty()) No due date
            @elseif ($dues->first()->equalTo($dues->last())) Due {{ $dues->first()->format('M j') }}
            @else Due {{ $dues->first()->format('M j') }} – {{ $dues->last()->format('M j') }}
            @endif
          </span>
        </summary>

        @foreach ($group->rows as $row)
          @php
            $item = $row->item;
            $done = in_array($row->state, ['done', 'done_late'], true);
            $status = match (true) {
                $done => 'Closed '.$row->closed_at->format('M j'),
                $row->state === 'overdue' => 'Overdue since '.$row->due_on->format('M j'),
                $row->state === 'not_started' => $row->due_on ? 'Due '.$row->due_on->format('M j, Y') : 'Not started',
                $row->waiting_on === 'capsa' => 'With your CaPSA to check',
                $row->waiting_on === 'team' && (bool) $row->note => 'Sent back by your CaPSA',
                $row->waiting_on === 'team' => $ord(max(1, $row->revision)).' revision requested',
                $row->waiting_on === 'signed_copy' => 'Approved — upload the signed copy',
                in_array($row->waiting_on, ['adviser', 'consultant'], true) => 'With your '.$row->waiting_on,
                default => 'Scheduled',
            };
            $closer = [
                'form' => 'Closed when your CaPSA checks the signed form',
                'approval' => 'Closed when your CaPSA checks the CLF signed by your '.($item->role ?: 'adviser'),
                'document' => 'Closes when you upload it',
                'defense' => 'Closes when the panel records a pass',
                'custom' => 'Ticked by your CaPSA',
            ][$item->kind] ?? '';
            // Everything is handed in through Document Submission; a defense is not.
            $canUpload = ! $done && $item->kind !== 'defense' && $item->kind !== 'custom'
                && (in_array($row->state, ['not_started', 'overdue'], true) || $row->changes_requested || $row->waiting_on === 'signed_copy');
            // A reviewer asks for changes on the chapter draft, so that is what the revision goes up as.
            $uploadFor = $item->kind === 'approval' && $row->changes_requested && ! $row->note
                ? ($group->rows->first(fn ($r) => $r->item->kind === 'document')?->item ?? $item)
                : $item;
            $uploadLabel = match (true) {
                (bool) $row->note => 'Upload again',
                $row->changes_requested => 'Upload '.$ord(max(1, $row->revision)).' revision',
                $row->waiting_on === 'signed_copy' => 'Upload signed CLF',
                default => 'Upload',
            };
            $iconKey = $done ? 'done' : $row->state;
          @endphp
          <div class="spm-item">
            <svg class="spm-dot spm-{{ $iconKey }}" viewBox="0 0 24 24" role="img" aria-label="{{ $status }}">{!! $icons[$iconKey] !!}</svg>
            <div>
              <div class="spm-title">
                {{ $item->title }}
                @if ($row->state === 'done_late')
                  <span class="spm-flag">{{ $row->days_late }} {{ Str::plural('day', $row->days_late) }} late</span>
                @endif
              </div>
              @if ($row->note)
                <span class="spm-note">“{{ $row->note }}”</span>
              @endif
              <div class="spm-who">{{ $closer }}</div>
            </div>
            <div class="spm-status">{{ $status }}</div>
            @if ($canUpload)
              <a class="spm-upload {{ $row->state === 'overdue' ? 'urgent' : '' }}" href="{{ route('student.submission', ['item' => $uploadFor->id]) }}">{{ $uploadLabel }}</a>
            @else
              <span></span>
            @endif
          </div>
        @endforeach
      </details>
    @endforeach
  </div>
</div>
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
    // paint: the content is on screen and no skeleton was ever shown.
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
