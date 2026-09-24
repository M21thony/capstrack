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
   Read-only: every deliverable is closed by somebody else. A summary card
   up top, then the team's milestones as numbered rows - one dropdown open
   at a time; only the list scrolls. */
.spm-complete{display:flex;align-items:center;gap:14px;flex-wrap:wrap;background:var(--green-bg);border:1px solid rgba(22,163,74,.3);border-radius:14px;padding:14px 18px;margin-bottom:16px}
.spm-complete svg{width:32px;height:32px;flex:none;fill:none;stroke:var(--green);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.spm-complete div{flex:1;min-width:200px;display:grid;gap:2px}
.spm-complete b{font-size:15px;color:var(--green)}
.spm-complete span{font-size:12.5px;color:var(--text2)}

/* Summary card: who we are, how far along, and what needs attention. */
.spm-hero{background:var(--bg2);border:1px solid var(--border);border-radius:16px;padding:20px 22px;margin-bottom:16px;box-shadow:0 1px 3px rgba(24,25,31,.04)}
.spm-hero-top{display:flex;align-items:center;gap:20px}
.spm-now{flex:1;min-width:0;display:grid;gap:6px}
.spm-now small{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--accent3);font-weight:700}
.spm-now b{font-size:24px;line-height:1.15;letter-spacing:-.01em;color:var(--text)}
.spm-meta{display:flex;flex-wrap:wrap;gap:6px;margin-top:2px}
.spm-chip{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--text);background:var(--bg3);border:1px solid var(--border);border-radius:999px;padding:4px 10px}
.spm-chip svg{width:13px;height:13px;flex:none;fill:none;stroke:var(--text3);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.spm-chip em{font-style:normal;color:var(--text3)}
.spm-ring{--p:0;position:relative;width:96px;height:96px;flex:none;border-radius:50%;background:conic-gradient(var(--accent) calc(var(--p) * 1%),var(--bg4) 0);display:grid;place-items:center}
.spm-ring::before{content:"";position:absolute;inset:9px;border-radius:50%;background:var(--bg2)}
.spm-ring div{position:relative;text-align:center;line-height:1.15}
.spm-ring b{display:block;font-size:22px;font-variant-numeric:tabular-nums;color:var(--text)}
.spm-ring span{font-size:10.5px;color:var(--text3)}
.spm-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr)) auto;gap:10px;margin-top:18px}
.spm-stat{display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:var(--bg3);min-width:0}
.spm-stat-ic{width:36px;height:36px;flex:none;border-radius:10px;display:grid;place-items:center}
.spm-stat-ic svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.spm-stat > div:last-child{min-width:0}
.spm-stat b{display:block;font-size:18px;line-height:1.2;font-variant-numeric:tabular-nums;color:var(--text)}
.spm-stat span{display:block;font-size:12px;color:var(--text3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.spm-stat.red .spm-stat-ic{background:var(--red-bg);color:var(--red)}
.spm-stat.red.has b{color:var(--red)}
.spm-stat.amber .spm-stat-ic{background:var(--amber-bg);color:var(--amber)}
.spm-stat.gold .spm-stat-ic{background:var(--accent-light);color:var(--accent3)}
.spm-stat.gold b{color:var(--accent3)}
.spm-scores{display:flex;align-items:center;justify-content:center;gap:8px;padding:0 18px;border-radius:12px;font-size:13px;font-weight:600;text-decoration:none;color:var(--text);border:1px solid var(--border2);background:var(--bg2);white-space:nowrap;transition:border-color .15s,background .15s}
.spm-scores:hover{border-color:var(--accent);background:var(--accent-glow)}
.spm-scores svg{width:16px;height:16px;fill:none;stroke:var(--accent3);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

/* Milestones: one card, a numbered row per milestone. */
.spm-panel{background:var(--bg2);border:1px solid var(--border);border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(24,25,31,.04)}
.spm-head{display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;padding:14px 20px;border-bottom:1px solid var(--border);font-size:12.5px;color:var(--text3)}
.spm-head b{font-size:15px;color:var(--text)}
.spm-legend{margin-left:auto;display:flex;gap:14px;flex-wrap:wrap}
.spm-legend span{display:inline-flex;align-items:center;gap:6px;font-size:11.5px;color:var(--text3)}
.spm-legend i{width:8px;height:8px;border-radius:50%}
.spm-list{max-height:calc(100vh - 340px);min-height:260px;overflow:auto;overscroll-behavior:contain}
.spm-group{border-bottom:1px solid var(--border)}
.spm-group:last-child{border-bottom:0}
.spm-group summary{list-style:none;display:grid;grid-template-columns:32px minmax(140px,1fr) 120px 140px 150px 18px;gap:14px;align-items:center;padding:14px 20px;cursor:pointer;font-size:12.5px;color:var(--text2);transition:background .15s}
.spm-group summary::-webkit-details-marker{display:none}
.spm-group summary:hover{background:var(--bg3)}
.spm-group summary b{font-size:14.5px;color:var(--text)}
.spm-group.now summary{background:var(--accent-light);box-shadow:inset 3px 0 0 var(--accent)}
.spm-num{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;font-size:13px;font-weight:700;background:var(--bg3);border:1.5px solid var(--border2);color:var(--text2)}
.spm-num svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2.8;stroke-linecap:round;stroke-linejoin:round}
.spm-group.now .spm-num{background:var(--accent);border-color:var(--accent);color:#18191f}
.spm-group.is-done .spm-num{background:var(--green-bg);border-color:rgba(22,163,74,.4);color:var(--green)}
.spm-group.is-late .spm-num{background:var(--red-bg);border-color:rgba(220,38,38,.35);color:var(--red)}
.spm-count{display:grid;gap:5px}
.spm-bar{height:5px;border-radius:999px;background:var(--bg4);overflow:hidden}
.spm-bar i{display:block;height:100%;border-radius:inherit;background:var(--accent)}
.spm-group.is-done .spm-bar i{background:var(--green)}
.spm-due{display:inline-flex;align-items:center;gap:6px}
.spm-due svg{width:14px;height:14px;flex:none;fill:none;stroke:var(--text3);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.spm-chev{width:18px;height:18px;fill:none;stroke:var(--text3);stroke-width:2;transition:transform .2s}
.spm-group[open] .spm-chev{transform:rotate(90deg)}
.spm-pill{display:inline-flex;align-items:center;font-size:11.5px;font-weight:600;padding:3px 10px;border-radius:999px;white-space:nowrap;justify-self:start}
.spm-pill.red{background:var(--red-bg);color:var(--red)} .spm-pill.grn{background:var(--green-bg);color:var(--green)} .spm-pill.gold{background:var(--accent);color:#18191f}

/* Deliverables inside an open milestone. */
.spm-items{padding:4px 20px 14px 66px;border-top:1px solid var(--border);background:var(--bg3)}
.spm-item{display:grid;grid-template-columns:30px minmax(0,1fr) auto auto;gap:12px;align-items:center;padding:12px 14px;margin-top:8px;border:1px solid var(--border);border-radius:12px;background:var(--bg2)}
.spm-dot{width:30px;height:30px;padding:6px;border-radius:9px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.spm-done{color:var(--green);background:var(--green-bg)} .spm-handed_in{color:var(--amber);background:var(--amber-bg)} .spm-overdue{color:var(--red);background:var(--red-bg)} .spm-not_started{color:var(--text3);background:var(--bg4)}
.spm-title{font-weight:600;font-size:13.5px;color:var(--text)}
.spm-flag{font-size:10.5px;font-weight:700;padding:1px 6px;border-radius:4px;background:var(--red-bg);color:var(--red);margin-left:6px}
.spm-who{font-size:12px;color:var(--text3);margin-top:2px}
.spm-note{display:block;margin-top:5px;font-size:12px;color:var(--amber);background:var(--amber-bg);border-radius:6px;padding:4px 8px}
.spm-status{font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;white-space:nowrap;background:var(--bg4);color:var(--text2)}
.spm-status.st-done{background:var(--green-bg);color:var(--green)}
.spm-status.st-handed_in{background:var(--amber-bg);color:var(--amber)}
.spm-status.st-overdue{background:var(--red-bg);color:var(--red)}
.spm-upload{display:inline-flex;align-items:center;gap:6px;font:inherit;font-size:12.5px;font-weight:600;color:var(--text);background:var(--bg2);border:1px solid var(--border2);border-radius:8px;padding:7px 13px;text-decoration:none;white-space:nowrap;cursor:pointer;transition:border-color .15s,background .15s,transform .15s}
.spm-upload svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.spm-upload:hover{border-color:var(--accent);background:var(--accent-glow)}
.spm-upload.urgent{background:var(--accent);border-color:var(--accent);color:#18191f}
.spm-upload.urgent:hover{background:var(--accent2);transform:translateY(-1px)}

@media (max-width:1100px){
  .spm-stats{grid-template-columns:repeat(3,minmax(0,1fr))}
  .spm-scores{grid-column:1 / -1;padding:11px}
}
@media (max-width:900px){
  .spm-list{max-height:none}
  .spm-group summary{grid-template-columns:32px 1fr auto 18px;padding:12px 16px}
  .spm-group summary > .spm-count,.spm-group summary > .spm-due{display:none}
  .spm-items{padding:4px 12px 12px}
  .spm-item{grid-template-columns:30px 1fr}
  .spm-item > .spm-status,.spm-item > .spm-upload{grid-column:2;justify-self:start}
  .spm-legend{display:none}
}
@media (max-width:640px){
  .spm-hero{padding:16px}
  .spm-hero-top{flex-direction:column-reverse;align-items:flex-start;gap:14px}
  .spm-ring{width:80px;height:80px}
  .spm-ring b{font-size:18px}
  .spm-now b{font-size:20px}
  .spm-stats{grid-template-columns:1fr}
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
      <div class="sk" style="height:150px;border-radius:16px;margin-bottom:16px"></div>
      <div class="card" style="border-radius:16px">
        @for ($i = 0; $i < 5; $i++)
        <div style="display:flex;align-items:center;gap:14px;padding:14px 0;border-bottom:1px solid var(--border)">
          <div class="sk" style="width:32px;height:32px;border-radius:50%;flex-shrink:0"></div>
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

@if ($progress->total > 0 && $progress->completed === $progress->total)
  {{-- Every line closed: say so, rather than leaving a finished team to read it off a percentage. --}}
  <div class="spm-complete" role="status">
    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="7.5 12.5 10.5 15.5 16.5 9"/></svg>
    <div>
      <b>{{ $team->team_name }} has completed every requirement on this checklist.</b>
      <span>All {{ $progress->total }} are closed. Capstone 1 is done - your CaPSA will set up what comes next.</span>
    </div>
    <a class="spm-upload" href="{{ route('student.defense-scores') }}">See panel scores</a>
  </div>
@endif

<div class="spm-hero">
  <div class="spm-hero-top">
    <div class="spm-now">
      <small>Your team</small>
      <b>{{ $team->team_name }}</b>
      <div class="spm-meta">
        <span class="spm-chip">
          <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18"/></svg>
          {{ $section ?: 'No section yet' }}
        </span>
        <span class="spm-chip">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <em>Adviser</em> {{ $adviser ?: 'not chosen yet' }}
        </span>
        <span class="spm-chip">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <em>Consultant</em> {{ $consultant ?: 'not chosen yet' }}
        </span>
      </div>
    </div>
    <div class="spm-ring" style="--p: {{ (int) $progress->percent }}" role="img" aria-label="{{ $progress->percent }}% complete">
      <div><b>{{ $progress->percent }}%</b><span>{{ $progress->completed }} of {{ $progress->total }} closed</span></div>
    </div>
  </div>

  <div class="spm-stats">
    <div class="spm-stat red {{ $progress->overdue ? 'has' : '' }}">
      <div class="spm-stat-ic"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5V13"/><path d="M12 16.5h.01"/></svg></div>
      <div><b>{{ $progress->overdue }}</b><span>overdue</span></div>
    </div>
    <div class="spm-stat amber">
      <div class="spm-stat-ic"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>
      <div><b>{{ $progress->handed_in }}</b><span>waiting on someone else</span></div>
    </div>
    <div class="spm-stat gold">
      <div class="spm-stat-ic"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></div>
      <div>
        <b>{{ $next ? $next->due_on->format('M j') : '—' }}</b>
        <span title="{{ $next ? 'Next due: '.$next->item->title : '' }}">{{ $next ? 'Next due: '.$next->item->title : 'Nothing left to do' }}</span>
      </div>
    </div>
    <a class="spm-scores" href="{{ route('student.defense-scores') }}">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/></svg>
      Panel scores
    </a>
  </div>
</div>

<div class="spm-panel">
  <div class="spm-head">
    <b>Milestones</b><span>in order · open one to see its deliverables</span>
    <div class="spm-legend" aria-hidden="true">
      <span><i style="background:var(--green)"></i>Closed</span>
      <span><i style="background:var(--amber)"></i>Waiting</span>
      <span><i style="background:var(--red)"></i>Overdue</span>
      <span><i style="background:var(--text3)"></i>Not started</span>
    </div>
  </div>
  <div class="spm-list">
    @foreach ($groups as $group)
      @php
        $isNow = $current === $group;
        $dues = $group->dues;
        $rowCount = $group->rows->count();
        $isDone = $rowCount > 0 && $group->closed === $rowCount;
        $groupPercent = $rowCount ? round($group->closed / $rowCount * 100) : 0;
      @endphp
      {{-- details with a shared name: opening one closes the one that was open. --}}
      <details class="spm-group {{ $isNow ? 'now' : '' }} {{ $group->overdue ? 'is-late' : ($isDone ? 'is-done' : '') }}" name="our-milestones" @if ($isNow) open @endif>
        <summary>
          <span class="spm-num">
            @if ($isDone && ! $group->overdue)
              <svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="5 12.5 10 17 19 7.5"/></svg>
            @else
              {{ $loop->iteration }}
            @endif
          </span>
          <b>{{ $group->name }}</b>
          @if ($group->overdue)
            <span class="spm-pill red">{{ $group->overdue }} overdue</span>
          @elseif ($isDone)
            <span class="spm-pill grn">Done</span>
          @elseif ($isNow)
            <span class="spm-pill gold">You are here</span>
          @else
            <span></span>
          @endif
          <span class="spm-count">
            {{ $group->closed }} of {{ $rowCount }} closed
            <span class="spm-bar"><i style="width: {{ $groupPercent }}%"></i></span>
          </span>
          <span class="spm-due">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            @if ($dues->isEmpty()) No due date
            @elseif ($dues->first()->equalTo($dues->last())) Due {{ $dues->first()->format('M j') }}
            @else Due {{ $dues->first()->format('M j') }} – {{ $dues->last()->format('M j') }}
            @endif
          </span>
          <svg class="spm-chev" viewBox="0 0 24 24" aria-hidden="true"><polyline points="9 6 15 12 9 18"/></svg>
        </summary>

        <div class="spm-items">
        @foreach ($group->rows as $row)
          @php
            $item = $row->item;
            $done = in_array($row->state, ['done', 'done_late'], true);
            $status = match (true) {
                $done => 'Closed '.$row->closed_at->format('M j'),
                $row->state === 'overdue' => 'Overdue since '.$row->due_on->format('M j'),
                $row->state === 'not_started' => $row->due_on ? 'Due '.$row->due_on->format('M j, Y') : 'Not started',
                $row->waiting_on === 'capsa' => 'With your CaPSA to check',
                $row->waiting_on === 'title_review' => 'With your CaPSA to recommend a title',
                $row->waiting_on === 'team' && (bool) $row->note => 'Sent back by your CaPSA',
                $row->waiting_on === 'team' => $ord(max(1, $row->revision)).' revision requested',
                $row->waiting_on === 'signed_copy' => 'Approved — upload the signed copy',
                in_array($row->waiting_on, ['adviser', 'consultant'], true) => 'With your '.$row->waiting_on,
                $row->waiting_on === 'redefense' => 'Re-defense needed — the panel asked for another defense',
                $item->kind === 'defense' && $row->revision > 0 => 'Re-defense scheduled',
                default => 'Scheduled',
            };
            $closer = [
                'form' => 'Closed when your CaPSA checks the signed form',
                'approval' => 'Closed when your CaPSA checks the CLF signed by your '.($item->role ?: 'adviser'),
                'document' => 'Closes when you upload it',
                'defense' => 'Closes when the panel records a pass',
                'custom' => 'Ticked by your CaPSA',
            ][$item->kind] ?? '';
            $byRecommendation = $item->closesOnTitleRecommendation();
            if ($byRecommendation) {
                $closer = 'Closed when your CaPSA recommends one of your pre-proposal titles — nothing to upload';
            }
            // Everything is handed in through Document Submission; a defense is not, nor the recommended title.
            $canUpload = ! $done && ! $byRecommendation && $item->kind !== 'defense' && $item->kind !== 'custom'
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
              <div class="spm-who">{{ $closer }}</div>
              @if ($row->note)
                <span class="spm-note">“{{ $row->note }}”</span>
              @endif
            </div>
            <div class="spm-status st-{{ $iconKey }}">{{ $status }}</div>
            @if ($canUpload)
              <button type="button" class="spm-upload {{ $row->state === 'overdue' ? 'urgent' : '' }}" onclick="window.openSubmissionDrawer && window.openSubmissionDrawer('{{ $uploadFor->id }}')">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                {{ $uploadLabel }}
              </button>
            @else
              <span></span>
            @endif
          </div>
        @endforeach
        </div>
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
<script>
{{-- The hamburger button and the overlay both called these and nothing
     defined them, so the menu would not open on a phone. Same two lines as
     every other student page. --}}
function toggleSidebar() { document.body.classList.toggle('sidebar-open'); }
function closeSidebar() { document.body.classList.remove('sidebar-open'); }
</script>
</body>
</html>
