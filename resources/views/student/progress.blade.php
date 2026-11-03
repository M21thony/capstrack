<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content" />
<title>Progress Tracker - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
@vite('resources/student-ui/styles.css')
<x-dashboard.styles />
<style nonce="{{ Vite::cspNonce() }}">
  @keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .sk-overlay { position: absolute; inset: 0; background: var(--bg); z-index: 10; }
  .sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }

/* ── Progress tracker ─────────────────────────────────────────────────
   Read-only: every deliverable is closed by somebody else. A dark header
   with the team and every milestone as a stepper, the one thing due next,
   then the milestones as a timeline - one open at a time. */
.pt-complete{display:flex;align-items:center;gap:14px;flex-wrap:wrap;background:var(--green-bg);border:1px solid rgba(22,163,74,.3);border-radius:16px;padding:14px 18px;margin-bottom:14px}
.pt-complete svg{width:32px;height:32px;flex:none;fill:none;stroke:var(--green);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.pt-complete div{flex:1;min-width:200px;display:grid;gap:2px}
.pt-complete b{font-size:15px;color:var(--green)}
.pt-complete span{font-size:12.5px;color:var(--text2)}

.pt-hero{position:relative;overflow:hidden;border-radius:20px;padding:22px 22px 18px;margin-bottom:14px;color:#fff;
  background:radial-gradient(120% 140% at 100% 0%,rgba(201,168,44,.35) 0%,rgba(201,168,44,0) 55%),linear-gradient(135deg,#1c1f2b 0%,#262a38 100%);
  box-shadow:0 10px 30px rgba(24,25,31,.18)}
.pt-hero-row{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;flex-wrap:wrap}
.pt-team{min-width:0;display:grid;gap:4px}
.pt-team small{font-size:10.5px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#e7c85a}
.pt-team b{font-size:24px;line-height:1.15;letter-spacing:-.01em}
.pt-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
.pt-chips span{font-size:11.5px;padding:4px 10px;border-radius:999px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.14);color:rgba(255,255,255,.9)}
.pt-chips em{font-style:normal;color:rgba(255,255,255,.55);margin-right:3px}
.pt-pct{text-align:right;display:grid;gap:2px}
.pt-pct b{font-size:44px;line-height:1;font-weight:700;letter-spacing:-.03em}
.pt-pct b i{font-style:normal;font-size:22px;color:#e7c85a;margin-left:2px}
.pt-pct span{font-size:12px;color:rgba(255,255,255,.65)}
.pt-bar{height:8px;border-radius:99px;background:rgba(255,255,255,.12);margin:16px 0 18px;overflow:hidden}
.pt-bar i{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,#c9a82c,#f0d56b);transition:width .6s ease}

/* The stepper: every milestone in order, the line between them filled as they close. */
.pt-steps{list-style:none;margin:0;padding:0 0 2px;display:flex;overflow-x:auto;scrollbar-width:none}
.pt-steps::-webkit-scrollbar{display:none}
.pt-step{position:relative;flex:1 0 96px;min-width:96px}
.pt-step::before{content:"";position:absolute;top:15px;left:calc(-50% + 17px);right:calc(50% + 17px);height:2px;background:rgba(255,255,255,.18)}
.pt-step:first-child::before{display:none}
.pt-step.is-done::before,.pt-step.is-active::before,.pt-step.is-now::before,.pt-step.is-late::before{background:#c9a82c}
.pt-step a{display:flex;flex-direction:column;align-items:center;gap:7px;text-decoration:none;color:inherit;padding:0 4px}
.pt-node{width:32px;height:32px;flex:none;border-radius:50%;display:grid;place-items:center;font-size:12.5px;font-weight:700;
  background:rgba(255,255,255,.1);border:2px solid rgba(255,255,255,.25);color:rgba(255,255,255,.75);position:relative;z-index:1}
.pt-node svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}
.pt-step.is-done .pt-node{background:#22a05a;border-color:#22a05a;color:#fff}
.pt-step.is-active .pt-node{border-color:#c9a82c;color:#f0d56b}
.pt-step.is-late .pt-node{background:#dc2626;border-color:#dc2626;color:#fff}
.pt-step.is-now .pt-node{background:#c9a82c;border-color:#f0d56b;color:#1c1f2b;box-shadow:0 0 0 5px rgba(201,168,44,.28)}
.pt-step.is-now.is-late .pt-node{background:#dc2626;border-color:#fca5a5;color:#fff;box-shadow:0 0 0 5px rgba(220,38,38,.3)}
.pt-step-name{font-size:11px;line-height:1.3;text-align:center;color:rgba(255,255,255,.7);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.pt-step.is-now .pt-step-name{color:#fff;font-weight:700}

/* Up next, and the counts beside it. */
.pt-row{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:14px;margin-bottom:14px}
.pt-next{display:flex;align-items:center;gap:14px;padding:16px 18px;border-radius:16px;background:var(--bg2);border:1px solid var(--border);border-left:4px solid var(--accent);box-shadow:0 1px 3px rgba(24,25,31,.04)}
.pt-next.is-late{border-left-color:var(--red)}
.pt-next.is-clear{border-left-color:var(--green)}
.pt-next-ic{width:44px;height:44px;flex:none;border-radius:13px;display:grid;place-items:center;background:var(--accent-light,#faf7ed);color:var(--accent3,#8a6e10)}
.pt-next.is-late .pt-next-ic{background:var(--red-bg);color:var(--red)}
.pt-next.is-clear .pt-next-ic{background:var(--green-bg);color:var(--green)}
.pt-next-ic svg{width:21px;height:21px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.pt-next-body{flex:1;min-width:0;display:grid;gap:2px}
.pt-next-body small{font-size:10.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text3)}
.pt-next-body b{font-size:15px;color:var(--text);line-height:1.3}
.pt-next-body span{font-size:12px;color:var(--text3)}
.pt-next-side{display:grid;justify-items:end;gap:8px;flex:none}
.pt-when{font-size:12px;font-weight:700;padding:4px 10px;border-radius:999px;background:var(--accent-light,#faf7ed);color:var(--accent3,#8a6e10);white-space:nowrap}
.pt-next.is-late .pt-when{background:var(--red-bg);color:var(--red)}
.pt-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.pt-stat{display:grid;align-content:center;gap:2px;padding:12px 14px;border-radius:14px;background:var(--bg2);border:1px solid var(--border);text-decoration:none}
.pt-stat b{font-size:22px;line-height:1;font-variant-numeric:tabular-nums;color:var(--text)}
.pt-stat span{font-size:11.5px;color:var(--text3)}
.pt-stat.is-red.has b{color:var(--red)}
.pt-stat.is-amber b{color:var(--amber)}
.pt-stat.is-green b{color:var(--green)}
.pt-scores{grid-template-columns:auto 1fr;align-items:center;gap:8px;color:var(--text2)}
.pt-scores svg{width:18px;height:18px;fill:none;stroke:var(--accent3,#8a6e10);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.pt-scores span{font-size:12.5px;font-weight:600;color:var(--text)}
.pt-scores:hover{border-color:var(--accent)}

/* The milestones, as a timeline. */
.pt-list-wrap{background:var(--bg2);border:1px solid var(--border);border-radius:18px;padding:16px 18px 10px;box-shadow:0 1px 3px rgba(24,25,31,.04)}
.pt-list-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px}
.pt-list-head b{font-size:15px;color:var(--text)}
.pt-legend{display:flex;flex-wrap:wrap;gap:10px;font-size:11px;color:var(--text3)}
.pt-legend span{display:inline-flex;align-items:center;gap:5px}
.pt-legend i{width:8px;height:8px;border-radius:50%}
.pt-legend .g{background:var(--green)}.pt-legend .a{background:var(--amber)}.pt-legend .r{background:var(--red)}.pt-legend .n{background:var(--text3)}
.pt-timeline{position:relative}
.pt-timeline::before{content:"";position:absolute;left:17px;top:22px;bottom:22px;width:2px;border-radius:2px;background:var(--border)}
.pt-group{position:relative;margin:0 0 8px;scroll-margin-top:12px}
.pt-group summary{list-style:none;display:flex;align-items:center;gap:14px;padding:10px 10px 10px 0;cursor:pointer;border-radius:12px}
.pt-group summary::-webkit-details-marker{display:none}
.pt-group summary:hover{background:var(--bg3)}
.pt-group .pt-node{width:36px;height:36px;background:var(--bg2);border:2px solid var(--border2,#d1d5db);color:var(--text3);box-shadow:0 0 0 4px var(--bg2)}
.pt-group.is-done .pt-node{background:var(--green);border-color:var(--green);color:#fff}
.pt-group.is-active .pt-node{border-color:var(--accent);color:var(--accent3,#8a6e10)}
.pt-group.is-late .pt-node{background:var(--red);border-color:var(--red);color:#fff}
.pt-group.is-now .pt-node{background:var(--accent);border-color:var(--accent);color:#1c1f2b;box-shadow:0 0 0 4px var(--bg2),0 0 0 7px rgba(201,168,44,.25)}
.pt-group.is-now.is-late .pt-node{background:var(--red);border-color:var(--red);color:#fff;box-shadow:0 0 0 4px var(--bg2),0 0 0 7px rgba(220,38,38,.22)}
.pt-g-main{flex:1;min-width:0;display:grid;gap:4px}
.pt-g-name{display:flex;align-items:center;flex-wrap:wrap;gap:8px;font-size:14.5px;font-weight:700;color:var(--text)}
.pt-g-meta{display:flex;align-items:center;flex-wrap:wrap;gap:6px;font-size:12px;color:var(--text3)}
.pt-mini{width:70px;height:5px;border-radius:99px;background:var(--bg3);overflow:hidden}
.pt-mini i{display:block;height:100%;background:var(--accent);border-radius:99px}
.pt-group.is-done .pt-mini i{background:var(--green)}
.pt-group.is-late .pt-mini i{background:var(--red)}
.pt-chev{width:18px;height:18px;flex:none;fill:none;stroke:var(--text3);stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;transition:transform .2s}
.pt-group[open] .pt-chev{transform:rotate(180deg)}
.pt-pill{font-size:10.5px;font-weight:700;padding:2px 9px;border-radius:999px;white-space:nowrap}
.pt-pill.r{background:var(--red-bg);color:var(--red)}
.pt-pill.g{background:var(--green-bg);color:var(--green)}
.pt-pill.y{background:var(--accent-light,#faf7ed);color:var(--accent3,#8a6e10);border:1px solid rgba(201,168,44,.35)}

.pt-pill.n{background:var(--bg3);color:var(--text3);border:1px solid var(--border)}
.pt-gate{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:6px 0 10px 50px;padding:12px 14px;border-radius:12px;background:var(--bg3);border:1px solid var(--border);font-size:12.5px;color:var(--text2)}
.pt-gate > svg{width:20px;height:20px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;color:var(--text3)}
.pt-gate > div{flex:1 1 240px;min-width:0;display:flex;flex-direction:column;gap:2px;line-height:1.45}
.pt-gate b{color:var(--text);font-size:13px}
.pt-gate.is-eval{background:var(--accent-light,#faf7ed);border-color:rgba(201,168,44,.35)}
.pt-gate.is-eval > svg{fill:#e0a800;stroke:none}
.pt-desc{margin:4px 0 8px 50px;font-size:12.5px;line-height:1.5;color:var(--text2);white-space:pre-line}
.pt-items{list-style:none;margin:4px 0 10px 50px;padding:0;display:grid;gap:8px}
.pt-item{display:flex;align-items:flex-start;gap:12px;padding:12px 14px;border-radius:12px;border:1px solid var(--border);background:var(--bg);border-left:3px solid var(--text3)}
.pt-item.st-done{border-left-color:var(--green)}
.pt-item.st-overdue{border-left-color:var(--red);background:rgba(220,38,38,.03)}
.pt-item.st-handed_in{border-left-color:var(--amber)}
.pt-ic{width:22px;height:22px;flex:none;margin-top:1px;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;stroke:var(--text3)}
.pt-item.st-done .pt-ic{stroke:var(--green)}
.pt-item.st-overdue .pt-ic{stroke:var(--red)}
.pt-item.st-handed_in .pt-ic{stroke:var(--amber)}
.pt-item-body{flex:1;min-width:0;display:grid;gap:3px}
.pt-item-title{display:flex;align-items:center;flex-wrap:wrap;gap:6px;font-size:13.5px;font-weight:600;color:var(--text);line-height:1.35}
.pt-item-who{font-size:11.5px;color:var(--text3)}
.pt-kept{font-size:12px;color:var(--text2);margin-top:4px}
.pt-file{display:inline-flex;align-items:center;gap:5px;margin-top:4px;font-size:12px;font-weight:600;color:var(--accent3);text-decoration:underline;text-underline-offset:2px}
.pt-file:hover{color:var(--text)}
.pt-file svg{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.pt-note{font-size:12px;font-style:italic;color:var(--text2);padding:6px 10px;border-radius:8px;background:var(--bg3)}
.pt-status{justify-self:start;margin-top:3px;font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;background:var(--bg3);color:var(--text2)}
.pt-item.st-done .pt-status{background:var(--green-bg);color:var(--green)}
.pt-item.st-overdue .pt-status{background:var(--red-bg);color:var(--red)}
.pt-item.st-handed_in .pt-status{background:var(--amber-bg);color:var(--amber)}

.pt-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;flex:none;padding:8px 14px;border-radius:10px;border:1px solid var(--border);background:var(--bg2);color:var(--text);font:inherit;font-size:12.5px;font-weight:700;cursor:pointer;text-decoration:none;white-space:nowrap}
.pt-btn svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2.3;stroke-linecap:round;stroke-linejoin:round}
.pt-btn-primary{background:var(--accent);border-color:var(--accent);color:#1c1f2b}
.pt-btn-danger{background:var(--red);border-color:var(--red);color:#fff}

@media (max-width:900px){
  .pt-row{grid-template-columns:1fr}
}
@media (max-width:640px){
  .pt-hero{padding:18px 16px 14px;border-radius:18px}
  .pt-team b{font-size:20px}
  .pt-pct{text-align:left}
  .pt-pct b{font-size:36px}
  .pt-step{flex-basis:84px;min-width:84px}
  .pt-next{flex-wrap:wrap;padding:14px}
  .pt-next-side{width:100%;grid-auto-flow:column;justify-content:space-between;align-items:center}
  .pt-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
  .pt-list-wrap{padding:14px 12px 6px;border-radius:16px}
  .pt-legend{display:none}
  .pt-items,.pt-gate,.pt-desc{margin-left:0}
  .pt-item{flex-wrap:wrap}
  .pt-item .pt-btn{width:100%;margin-left:34px;width:calc(100% - 34px)}
}
@media (prefers-reduced-motion:reduce){.pt-bar i,.pt-chev{transition:none}}
</style>
@include('partials.skeleton-head', ['skReveal' => 'opacity'])
  @include('partials.render-whole')
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

@php
  $groupState = function ($g) {
      $n = $g->rows->count();
      return match (true) {
          $g->overdue > 0 || $g->past_due => 'late',
          $n > 0 && $g->closed === $n => 'done',
          $g->closed > 0 || $g->rows->contains(fn ($r) => ! in_array($r->state, ['not_started', 'overdue'], true)) => 'active',
          default => 'todo',
      };
  };
  $nextGroup = $next ? $groups->first(fn ($g) => $g->rows->contains($next)) : null;
  $nextDays = $next ? (int) now()->startOfDay()->diffInDays($next->due_on->copy()->startOfDay(), false) : null;
  $nextWhen = match (true) {
      $nextDays === null => null,
      $nextDays < 0 => abs($nextDays).' '.Str::plural('day', abs($nextDays)).' overdue',
      $nextDays === 0 => 'Due today',
      $nextDays === 1 => 'Due tomorrow',
      default => 'Due in '.$nextDays.' days',
  };
  $nextCanUpload = $next && ! in_array($next->item->kind, ['defense', 'custom'], true);
@endphp

@if ($progress->total > 0 && $progress->completed === $progress->total)
  {{-- Every line closed: say so, rather than leaving a finished team to read it off a percentage. --}}
  <div class="pt-complete" role="status">
    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="7.5 12.5 10.5 15.5 16.5 9"/></svg>
    <div>
      <b>{{ $team->team_name }} has completed every requirement on this checklist.</b>
      <span>All {{ $progress->total }} are closed. Capstone 1 is done - your CaPSA will set up what comes next.</span>
    </div>
    <a class="pt-btn" href="{{ route('student.defense-scores') }}">See panel scores</a>
  </div>
@endif

{{-- The team, how far along, and every milestone in order. --}}
<section class="pt-hero">
  <div class="pt-hero-row">
    <div class="pt-team">
      <small>Your team</small>
      <b>{{ $team->team_name }}</b>
      <div class="pt-chips">
        <span>{{ $section ?: 'No section yet' }}</span>
        <span><em>Adviser</em> {{ $adviser ?: 'not chosen yet' }}</span>
        <span><em>Consultant</em> {{ $consultant ?: 'not chosen yet' }}</span>
      </div>
    </div>
    <div class="pt-pct" aria-label="{{ $progress->percent }}% complete">
      <b>{{ $progress->percent }}<i>%</i></b>
      <span>{{ $progress->completed }} of {{ $progress->total }} closed</span>
    </div>
  </div>
  <div class="pt-bar" aria-hidden="true"><i style="width: {{ (int) $progress->percent }}%"></i></div>

  <ol class="pt-steps" aria-label="Milestones">
    @foreach ($groups as $group)
      @php $st = $groupState($group); @endphp
      <li class="pt-step is-{{ $st }} {{ $current === $group ? 'is-now' : '' }}">
        <a href="#pt-g-{{ $loop->iteration }}" onclick="ptOpen({{ $loop->iteration }})">
          <span class="pt-node">
            @if ($st === 'done')<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="5 12.5 10 17 19 7.5"/></svg>
            @elseif ($st === 'late')!
            @else{{ $loop->iteration }}@endif
          </span>
          <span class="pt-step-name">{{ $group->name }}</span>
        </a>
      </li>
    @endforeach
  </ol>
</section>

<div class="pt-row">
  {{-- The one thing to do next. --}}
  @if ($next)
    <section class="pt-next {{ $nextDays !== null && $nextDays < 0 ? 'is-late' : '' }}">
      <div class="pt-next-ic">
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      </div>
      <div class="pt-next-body">
        <small>Up next</small>
        <b>{{ $next->item->title }}</b>
        <span>{{ $nextGroup?->name }} · {{ $next->due_on->format('M j, Y') }}</span>
      </div>
      <div class="pt-next-side">
        <span class="pt-when">{{ $nextWhen }}</span>
        @if ($nextCanUpload)
          <button type="button" class="pt-btn pt-btn-primary" onclick="window.openSubmission && window.openSubmission('{{ $next->item->id }}', this)">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
            Upload
          </button>
        @endif
      </div>
    </section>
  @else
    <section class="pt-next is-clear">
      <div class="pt-next-ic"><svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="5 12.5 10 17 19 7.5"/></svg></div>
      <div class="pt-next-body"><small>Up next</small><b>Nothing due right now</b><span>New deadlines show here as your CaPSA sets them.</span></div>
    </section>
  @endif

  <div class="pt-stats">
    <div class="pt-stat is-red {{ $progress->overdue ? 'has' : '' }}"><b>{{ $progress->overdue }}</b><span>Overdue</span></div>
    <div class="pt-stat is-amber"><b>{{ $progress->handed_in }}</b><span>Waiting</span></div>
    <div class="pt-stat is-green"><b>{{ $progress->completed }}</b><span>Closed</span></div>
    <a class="pt-stat pt-scores" href="{{ route('student.defense-scores') }}">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/></svg>
      <span>Panel scores</span>
    </a>
  </div>
</div>

{{-- Every milestone, as a timeline. One open at a time. --}}
<section class="pt-list-wrap">
  <div class="pt-list-head">
    <b>Milestones</b>
    <div class="pt-legend" aria-hidden="true">
      <span><i class="g"></i>Closed</span><span><i class="a"></i>Waiting</span><span><i class="r"></i>Overdue</span><span><i class="n"></i>Not started</span>
    </div>
  </div>
  <div class="pt-timeline">
    @foreach ($groups as $group)
      @php
        $st = $groupState($group);
        $isNow = $current === $group;
        $dues = $group->dues;
        $rowCount = $group->rows->count();
        $groupPercent = $rowCount ? round($group->closed / $rowCount * 100) : 0;
      @endphp
      <details class="pt-group is-{{ $st }} {{ $isNow ? 'is-now' : '' }}" id="pt-g-{{ $loop->iteration }}" name="our-milestones" @if ($isNow) open @endif>
        <summary>
          <span class="pt-node">
            @if ($st === 'done')<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="5 12.5 10 17 19 7.5"/></svg>
            @elseif ($st === 'late')!
            @else{{ $loop->iteration }}@endif
          </span>
          <span class="pt-g-main">
            <span class="pt-g-name">{{ $group->name }}
              @if ($group->past_due)<span class="pt-pill r">Past due</span>
              @elseif ($group->overdue)<span class="pt-pill r">{{ $group->overdue }} overdue</span>
              @elseif ($group->evaluations_owed->isNotEmpty())<span class="pt-pill y">Evaluate your {{ \App\Support\ExpertEvaluations::whom($group->evaluations_owed) }}</span>
              @elseif ($st === 'done')<span class="pt-pill g">Done</span>
              @elseif ($group->locked)<span class="pt-pill n">Locked</span>
              @elseif ($isNow)<span class="pt-pill y">You are here</span>@endif
            </span>
            <span class="pt-g-meta">
              <span class="pt-mini"><i style="width: {{ $groupPercent }}%"></i></span>
              {{ $group->closed }} of {{ $rowCount }} closed
              <span class="pt-dot">·</span>
              @if ($group->due) Due {{ $group->due->format('M j, Y') }}
              @elseif ($dues->isEmpty()) No due date
              @elseif ($dues->first()->equalTo($dues->last())) Due {{ $dues->first()->format('M j') }}
              @else Due {{ $dues->first()->format('M j') }} – {{ $dues->last()->format('M j') }}
              @endif
            </span>
          </span>
          <svg class="pt-chev" viewBox="0 0 24 24" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
        </summary>

        @if ($group->evaluations_owed->isNotEmpty())
          {{-- Passing a defense ends with the leader evaluating each expert; the next phase waits for it. --}}
          <div class="pt-gate is-eval">
            <svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <div>
              <b>{{ $isLeader ? 'Evaluate your '.\App\Support\ExpertEvaluations::whom($group->evaluations_owed).' to unlock the next phase' : 'Waiting on your leader to evaluate your '.\App\Support\ExpertEvaluations::whom($group->evaluations_owed) }}</b>
              <span>{{ $group->evaluations_owed->map(fn ($e) => $e->name.' ('.ucfirst($e->role).')')->implode(', ') }} {{ $group->evaluations_owed->count() === 1 ? 'still needs' : 'still need' }} your anonymous evaluation for {{ $group->evaluations_owed->pluck('stage')->unique()->implode(' and ') }}.</span>
            </div>
            @if ($isLeader)<a class="pt-btn pt-btn-primary" href="{{ route('student.experts', ['tab' => 'evaluations']) }}">Evaluate now</a>@endif
          </div>
        @elseif ($group->locked && $group->requires)
          <div class="pt-gate">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            <div><b>Locked</b><span>Opens after {{ $group->requires }}.</span></div>
          </div>
        @endif
        @if ($group->description)
          <p class="pt-desc">{{ $group->description }}</p>
        @endif
        <ul class="pt-items">
        @foreach ($group->rows as $row)
          @php
            $item = $row->item;
            $done = in_array($row->state, ['done', 'done_late'], true);
            $status = match (true) {
                $done => 'Closed '.$row->closed_at->format('M j'),
                $row->state === 'overdue' => 'Overdue since '.$row->due_on->format('M j'),
                $row->state === 'not_started' => $row->due_on ? 'Due '.$row->due_on->format('M j, Y') : 'Not started',
                $row->waiting_on === 'capsa' && $item->closesOnArchiveEntry() => 'With your CaPSA to publish',
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
            if ($item->closesOnArchiveEntry()) {
                $closer = 'Closes when your CaPSA publishes it in the Archive';
            }
            // Everything is handed in through Document Submission; a defense is not, nor the recommended title.
            // A line still waiting on an earlier one cannot be handed in yet, so
            // it offers no Upload that could only say so.
            $canUpload = ! $done && ! $byRecommendation && ! $row->locked && $item->kind !== 'defense' && $item->kind !== 'custom'
                && (in_array($row->state, ['not_started', 'overdue'], true) || $row->changes_requested || $row->waiting_on === 'signed_copy');
            // A reviewer asks for changes on the chapter draft, so that is what the revision goes up as.
            $uploadFor = $item->kind === 'approval' && $row->changes_requested && ! $row->note
                ? ($group->rows->first(fn ($r) => $r->item->kind === 'document')?->item ?? $item)
                : $item;
            $uploadLabel = match (true) {
                (bool) $row->note => 'Replace file',
                $row->changes_requested => 'Upload '.$ord(max(1, $row->revision)).' revision',
                $row->waiting_on === 'signed_copy' => 'Upload signed CLF',
                default => 'Upload',
            };
            $iconKey = $done ? 'done' : $row->state;
          @endphp
          <li class="pt-item st-{{ $iconKey }}">
            <svg class="pt-ic" viewBox="0 0 24 24" role="img" aria-label="{{ $status }}">{!! $icons[$iconKey] !!}</svg>
            <div class="pt-item-body">
              <div class="pt-item-title">
                {{ $item->title }}
                @if ($row->state === 'done_late')
                  <span class="pt-pill r">{{ $row->days_late }} {{ Str::plural('day', $row->days_late) }} late</span>
                @endif
              </div>
              <div class="pt-item-who">{{ $closer }}</div>
              @if ($item->closesOnArchiveEntry() && ! $done)
                <a class="pt-file" href="{{ route('student.archive') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="14 3 14 9 20 9"/></svg>Open it in Archive</a>
              @endif
              @if ($row->file_id)
                {{-- A signed paper opens as the file itself; a draft opens on Documents with its remarks. --}}
                @if (in_array($item->kind, \App\Models\Deliverable::CHECKED_BY_CAPSA, true))
                  <a class="pt-file" href="{{ route('student.documents.file', $row->file_id) }}" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="14 3 14 9 20 9"/></svg>View the file you sent</a>
                @else
                  <a class="pt-file" href="{{ route('student.documents', ['file' => $row->file_id]) }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="14 3 14 9 20 9"/></svg>Open it in Documents</a>
                @endif
              @endif
              @if ($row->note)
                <div class="pt-note">“{{ $row->note }}”</div>
                @if ($row->waiting_on === 'team')
                  <div class="pt-kept">The file you sent is kept. Replace it with a corrected copy.</div>
                @endif
              @endif
              <span class="pt-status">{{ $status }}</span>
            </div>
            @if ($canUpload)
              <button type="button" class="pt-btn {{ $row->state === 'overdue' ? 'pt-btn-danger' : 'pt-btn-primary' }}" onclick="window.openSubmission && window.openSubmission('{{ $uploadFor->id }}', this)">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                {{ $uploadLabel }}
              </button>
            @endif
          </li>
        @endforeach
        </ul>
      </details>
    @endforeach
  </div>
</section>
<script nonce="{{ Vite::cspNonce() }}">
  // A step in the header opens its milestone below.
  function ptOpen(n) { var d = document.getElementById('pt-g-' + n); if (d) d.open = true; }
</script>
@endif
    </div>
</div>
</div>
</div>

<script nonce="{{ Vite::cspNonce() }}">
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
@include('partials.app-scrollbar')
<script nonce="{{ Vite::cspNonce() }}">
{{-- The hamburger button and the overlay both called these and nothing
     defined them, so the menu would not open on a phone. Same two lines as
     every other student page. --}}
function toggleSidebar() { document.body.classList.toggle('sidebar-open'); }
function closeSidebar() { document.body.classList.remove('sidebar-open'); }
</script>
</body>
</html>
