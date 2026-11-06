<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content" />
<title>Dashboard - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
@vite('resources/student-ui/Styles/dashboard.css')
<x-dashboard.styles />
<link href="{{ \App\Support\PublicAsset::url('css/dashboard-redesign.css') }}" rel="stylesheet">
<style nonce="{{ Vite::cspNonce() }}">
  @keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .sk-overlay { position: absolute; inset: 0; background: var(--bg); z-index: 10; }
  .sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }

  .ann-list { display:flex;flex-direction:column;gap:0;max-height:320px;overflow-y:auto; }
  .ann-list::-webkit-scrollbar { width:4px; }
  .ann-list::-webkit-scrollbar-thumb { background:var(--border);border-radius:4px; }
  .ann-item { display:flex;gap:14px;padding:14px 16px;border-bottom:1px solid var(--border);transition:background 0.12s; }
  .ann-item:last-child { border-bottom:none; }
  .ann-item:hover { background:var(--bg3); }
  .ann-content { flex:1;min-width:0; }
  .ann-header { display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:4px; }
  .ann-title { font-size:14px;font-weight:700;color:var(--text);line-height:1.3; }
  .ann-body { font-size:13px;color:var(--text2);line-height:1.55;margin-bottom:8px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden; }
  .ann-footer { display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px; }
  .ann-sender { display:flex;align-items:center;gap:6px; }
  .ann-avatar { width:22px;height:22px;border-radius:50%;flex-shrink:0;object-fit:cover; }
  .ann-name { font-size:11px;color:var(--text3);font-weight:500; }
  .ann-date { font-size:11px;color:var(--text3); }
  .ann-tags { display:flex;align-items:center;gap:5px;flex-wrap:wrap; }
  .ann-tag { font-size:10px;font-weight:600;padding:2px 8px;border-radius:5px; }
  .ann-tag.section { background:rgba(16,185,129,0.1);color:#059669; }
  .ann-tag.team { background:rgba(59,130,246,0.1);color:#2563eb; }

  @keyframes ann-highlight { 0%,100% { background:transparent; } 50% { background:rgba(99,102,241,0.06); } }
  .ann-item.highlight { animation:ann-highlight 2s ease; }

  .ann-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center;z-index:1000;opacity:0;pointer-events:none;transition:opacity .2s}
  .ann-modal-overlay.open{opacity:1;pointer-events:auto}
  .ann-modal-box{background:var(--bg2);border-radius:16px;padding:28px;max-width:520px;width:90%;max-height:80vh;max-height:80dvh;overflow-y:auto;position:relative;scrollbar-width:none;-ms-overflow-style:none}
.ann-modal-box::-webkit-scrollbar{display:none}
  .ann-modal-close{position:absolute;top:14px;right:16px;background:none;border:none;font-size:22px;color:var(--text3);cursor:pointer;padding:4px;line-height:1}
  .ann-modal-close:hover{color:var(--text)}
  .ann-modal-title{font-size:17px;font-weight:700;color:var(--text);line-height:1.3;margin-bottom:12px}
  .ann-modal-body{font-size:14px;color:var(--text2);line-height:1.7;margin-bottom:16px;white-space:pre-wrap}
  .ann-modal-tags{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:16px}
  .ann-modal-tags .ann-tag{font-size:11px;padding:3px 10px}
  .ann-modal-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-top:14px;border-top:1px solid var(--border)}
  .ann-modal-sender{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--text)}
  .ann-modal-date{font-size:12px;color:var(--text3)}
/* Your next step: the one setup step left, until the team has an adviser. */
.ns-card{border:1px solid rgba(201,168,44,.35);border-left:5px solid var(--accent,#c9a82c);background:rgba(245,193,0,.06);border-radius:14px;padding:16px 18px;margin-bottom:16px;display:flex;flex-direction:column;gap:12px}
.ns-card.wait{border-color:rgba(22,163,74,.3);border-left-color:var(--green,#16a34a);background:rgba(22,163,74,.05)}
.ns-lab{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--accent3,#8a6e10)}
.ns-card.wait .ns-lab{color:var(--green,#16a34a)}
.ns-card h3{margin:0;font-size:19px;color:var(--text)}
.ns-why{margin:0;font-size:14px;color:var(--text2);max-width:60ch}
.ns-how{margin:0;padding:0;list-style:none;counter-reset:h;display:flex;flex-direction:column;gap:6px}
.ns-how li{counter-increment:h;display:flex;gap:10px;font-size:13.5px;color:var(--text)}
.ns-how li::before{content:counter(h);flex:none;width:20px;height:20px;border-radius:50%;background:rgba(245,193,0,.22);color:var(--accent3,#8a6e10);font-weight:700;font-size:11.5px;display:grid;place-items:center;margin-top:1px}
.ns-go{align-self:flex-start;text-decoration:none}
.ns-rows{list-style:none;margin:0;padding:12px 0 0;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:8px}
.ns-rows li{display:flex;align-items:center;gap:10px;font-size:13.5px;color:var(--text)}
.ns-rows li>span:last-child{min-width:0}
.ns-rows svg{width:18px;height:18px;flex:none;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.ns-rows .k{width:64px;flex:none;color:var(--text2)}
.ns-rows .ok svg{stroke:var(--green,#16a34a)}
.ns-rows .todo svg{stroke:var(--accent3,#8a6e10)}
.ns-rows .wait svg{stroke:#d97706}
.ns-rows .lock{color:var(--text3)}
.ns-rows .lock svg{stroke:var(--text3)}
</style>
@include('partials.skeleton-head', ['skReveal' => 'opacity'])
  @include('partials.render-whole')
</head>
<body>
@include('partials.dashboard-sprite')
@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Dashboard</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content db-page">

    {{-- Skeleton loader --}}
    <div id="contentWrapper" style="position: relative;">
<div id="skeleton-content" class="sk-overlay">
      <div class="sk" style="height:132px;border-radius:14px;margin-bottom:16px"></div>
      <div class="card" style="margin-bottom:16px">
        <div class="card-header">
          <span class="sk" style="width:130px;height:14px;border-radius:4px;"></span>
          <span class="sk" style="width:40px;height:18px;border-radius:12px;"></span>
        </div>
        @for ($i = 0; $i < 3; $i++)
        <div style="display:flex;gap:14px;padding:14px 16px;border-bottom:1px solid var(--border)">
          <div class="sk" style="width:4px;height:44px;border-radius:4px;flex-shrink:0"></div>
          <div style="flex:1">
            <div class="sk" style="width:60%;height:13px;border-radius:4px;margin-bottom:8px"></div>
            <div class="sk" style="width:90%;height:11px;border-radius:4px;margin-bottom:6px"></div>
            <div class="sk" style="width:40%;height:11px;border-radius:4px"></div>
          </div>
        </div>
        @endfor
      </div>
      <div style="height:16px"></div>
      <div class="dash-grid-v3">
        <div class="card">
          <div class="card-header"><span class="sk" style="width:130px;height:14px;border-radius:4px;"></span></div>
          @for ($i = 0; $i < 2; $i++)
          <div style="display:flex;gap:10px;padding:8px 0;align-items:center">
            <div class="sk" style="width:8px;height:8px;border-radius:50%;flex-shrink:0"></div>
            <div style="flex:1">
              <div class="sk" style="width:50%;height:11px;border-radius:4px;margin-bottom:5px"></div>
              <div class="sk" style="width:70%;height:12px;border-radius:4px"></div>
            </div>
          </div>
          @endfor
        </div>
        <div class="card">
          <div class="card-header"><span class="sk" style="width:100px;height:14px;border-radius:4px;"></span></div>
          @for ($i = 0; $i < 3; $i++)
          <div style="display:flex;gap:10px;padding:8px 0;align-items:center">
            <div class="sk" style="width:8px;height:8px;border-radius:50%;flex-shrink:0"></div>
            <div style="flex:1">
              <div class="sk" style="width:60%;height:12px;border-radius:4px;margin-bottom:5px"></div>
              <div class="sk" style="width:40%;height:10px;border-radius:4px"></div>
            </div>
          </div>
          @endfor
        </div>
      </div>
      <div class="card" style="margin-top:16px">
        <div class="card-header"><span class="sk" style="width:170px;height:14px;border-radius:4px;"></span></div>
        @for ($i = 0; $i < 3; $i++)
        <div style="display:flex;gap:12px;padding:12px 0;align-items:center">
          <div class="sk" style="width:8px;height:8px;border-radius:50%;flex-shrink:0"></div>
          <div style="flex:1">
            <div class="sk" style="width:55%;height:12px;border-radius:4px;margin-bottom:6px"></div>
            <div class="sk" style="width:35%;height:10px;border-radius:4px"></div>
          </div>
          <div class="sk" style="width:40px;height:40px;border-radius:6px;flex-shrink:0"></div>
        </div>
        @endfor
      </div>
    </div>

    {{-- Content --}}
    <div id="real-content" style="opacity:0">
    @php
      // What the announcement popup reads off a row, for the card and the View all list alike.
      $annAttrs = fn ($announcement) => collect([
        'title' => $announcement->title,
        'body' => $announcement->body,
        'admin-name' => $announcement->admin?->name ?? 'Admin',
        'admin-avatar' => $announcement->admin?->avatarUrl() ?? asset('images/default-avatar.jpg'),
        'date' => $announcement->created_at->format('M j, Y · g:i A'),
        'sections' => json_encode(array_values(array_filter(array_map(fn ($ta) => str_starts_with((string) $ta, 'section:') ? substr((string) $ta, 8) : null, (array) $announcement->target_audience)))),
        'team' => $announcement->team?->team_name ?? '',
        'milestone-due' => $announcement->milestone?->due_date?->format('M j, Y') ?? '',
      ])->map(fn ($value, $key) => 'data-'.$key.'="'.e($value).'"')->implode(' ');
      $behind = $overdueItems->count();
      $avatarColours = ['background:#b5941f;color:#18191f', 'background:#4a5060', 'background:#8a6e10', 'background:#18191f;color:var(--accent)'];
    @endphp

    {{-- A team still setting up, or one with a defense coming, has more to show than one screen holds. --}}
    <main class="db db-student {{ $nextStep || $nextDefense ? 'db-auto' : '' }}">

      <section class="db-banner">
        <div class="db-banner-text">
          <div>
            <h1>Welcome back, <span>{{ auth()->user()->f_name }}!</span></h1>
            <p>Let's keep going on your capstone journey.</p>
          </div>
        </div>
        <div class="db-banner-photo" aria-hidden="true"></div>
        <div class="db-banner-year"><svg class="i c-orange"><use href="#i-cal"/></svg><div>Academic Year<b>S.Y. {{ str_replace('-', '–', $schoolYear) }}</b></div></div>
      </section>

      {{-- Until the team has an adviser: the one setup step left, and why.
           See App\Support\NextStep. --}}
      @if ($nextStep)
        <section class="ns-card {{ $nextStep->waiting ? 'wait' : '' }}" id="next-step-card">
          <div class="ns-lab">{{ $nextStep->waiting ? 'Nothing to do right now' : 'Your next step' }}</div>
          <h3>{{ $nextStep->title }}</h3>
          <p class="ns-why">{{ $nextStep->why }}</p>
          @if ($nextStep->how)
            <ol class="ns-how">
              @foreach ($nextStep->how as $line)<li><span>{{ $line }}</span></li>@endforeach
            </ol>
          @endif
          <a class="btn {{ $nextStep->waiting ? 'btn-ghost' : 'btn-primary' }} ns-go" href="{{ $nextStep->url }}">{{ $nextStep->button }}</a>
          <ul class="ns-rows" aria-label="Where your team stands">
            @foreach ($nextStep->rows as [$label, $state, $text])
              <li class="{{ $state }}">
                @switch($state)
                  @case('ok')<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="8 12.5 11 15.5 16 9.5"/></svg>@break
                  @case('wait')<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 7 12 12 15 14"/></svg>@break
                  @case('lock')<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>@break
                  @default<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/></svg>
                @endswitch
                <span class="k">{{ $label }}</span>
                <span>{{ $text }}</span>
              </li>
            @endforeach
          </ul>
        </section>
      @endif

      <div class="db-row db-row-top">
        <section class="db-card db-progress">
          <div class="db-head">
            <h2 class="db-title"><svg class="i c-teal"><use href="#i-chart"/></svg>Your Capstone Progress</h2>
            @if ($progress)
              <a class="db-more" href="{{ route('student.progress') }}">View details →</a>
            @endif
          </div>
          @if (! $myTeam)
            <p class="db-empty">Join a team to see your progress here.</p>
          @elseif (! $progress)
            <p class="db-empty">Your section's checklist is not set up yet. Your CaPSA adds it; it shows here once they do.</p>
          @else
            <div class="db-progress-body">
              <div class="db-ring-wrap">
                <div class="db-ring">
                  <svg width="76" height="76" viewBox="0 0 104 104" role="img" aria-label="{{ $overallProgress }} percent complete">
                    <circle cx="52" cy="52" r="44" fill="none" stroke="#faf7ed" stroke-width="11"/>
                    <circle cx="52" cy="52" r="44" fill="none" stroke="#b5941f" stroke-width="11" stroke-linecap="round" stroke-dasharray="{{ round(276.5 * $overallProgress / 100, 1) }} 276.5" transform="rotate(-90 52 52)"/>
                  </svg>
                  <span>{{ $overallProgress }}%</span>
                </div>
                <div style="flex:1;min-width:0">
                  <div class="db-count"><b>{{ $progress->completed }}</b> of {{ $progress->total }} requirements done</div>
                  <span class="db-bar"><span style="width:{{ $overallProgress }}%"></span></span>
                </div>
              </div>
              @if ($behind)
                <div class="db-overdue">
                  <b><svg class="i c-red"><use href="#i-warn"/></svg>{{ $behind }} Overdue</b>
                  <span>Don't forget to complete your pending requirements.</span>
                </div>
              @endif
              @if ($nextItem)
                <div class="db-nextdue">
                  <svg class="i c-orange"><use href="#i-cal"/></svg>
                  <div style="min-width:0">
                    <div class="db-meta" style="font-weight:600">Next due</div>
                    <strong>{{ \Carbon\Carbon::parse($nextItem->due_on)->format('M j, Y') }}</strong>
                    <div class="db-meta db-clip">{{ $nextItem->item->title }}</div>
                  </div>
                </div>
              @elseif (! $behind)
                <div class="db-nextdue">
                  <svg class="i c-green"><use href="#i-check"/></svg>
                  <div>
                    <strong>Nothing due</strong>
                    <div class="db-meta">You are on track.</div>
                  </div>
                </div>
              @endif
            </div>
          @endif
          @if ($nextDefense)
            <div class="db-notice db-defense">
              <svg class="i c-orange"><use href="#i-cal"/></svg>
              <div style="flex:1;min-width:0">
                <b>{{ $nextDefense->defense_type }} · {{ $nextDefense->schedule_date->format('D, M j') }}@if ($nextDefense->schedule_date->format('H:i') !== '00:00') · {{ $nextDefense->schedule_date->format('g:i A') }}@endif{{ $nextDefense->venue ? ' · '.$nextDefense->venue : '' }}</b>
                @if ($sheetReady)
                  <span>Print one rating sheet for each panelist and bring them, in case CapsTrack cannot be reached: <a href="{{ route('student.defense.sheet', [$nextDefense->defense_id, 'pdf']) }}">PDF</a> · <a href="{{ route('student.defense.sheet', [$nextDefense->defense_id, 'xlsx']) }}">Excel</a></span>
                @endif
              </div>
            </div>
          @endif
        </section>

        <section class="db-card">
          <div class="db-head">
            <h2 class="db-title"><svg class="i c-orange"><use href="#i-bolt"/></svg>Quick Actions</h2>
          </div>
          <div class="db-qa-grid">
            <a class="db-qa" href="{{ route('student.duplication') }}"><svg class="i c-blue"><use href="#i-send"/></svg>Submit Pre-Proposal</a>
            <a class="db-qa" href="{{ route('student.documents') }}"><svg class="i c-blue"><use href="#i-doc"/></svg>View Documents</a>
            <a class="db-qa" href="{{ route('student.consultation') }}"><svg class="i c-orange"><use href="#i-cal"/></svg>Check Schedule</a>
            <a class="db-qa" href="{{ route('student.progress') }}"><svg class="i c-teal"><use href="#i-chart"/></svg>View Progress</a>
          </div>
        </section>
      </div>

      <div class="db-row db-row-fill">
        <section class="db-card" id="announcements-card">
          <div class="db-head">
            <h2 class="db-title"><svg class="i c-orange"><use href="#i-megaphone"/></svg>Announcements</h2>
            @if ($announcements->isNotEmpty())
              <button type="button" class="db-more" onclick="document.getElementById('annListModal').classList.add('open')">View all</button>
            @endif
          </div>
          @if ($announcements->isEmpty())
            <p class="db-empty">No announcements for your section.</p>
          @else
            <ul class="db-list">
              @foreach ($announcements->take(3) as $announcement)
                <li class="ann-item db-ann-row" onclick="openAnnModal(this)" {!! $annAttrs($announcement) !!}>
                  <span class="db-dot {{ $announcement->created_at->gt(now()->subWeek()) ? '' : 'read' }}"></span>
                  <div class="db-ann">
                    <div class="db-ann-top"><span class="ann-title db-clip">{{ $announcement->title }}</span><time>{{ $announcement->created_at->format('M j') }}</time></div>
                    <span class="db-meta db-clip">{{ $announcement->body }}</span>
                  </div>
                </li>
              @endforeach
            </ul>
          @endif
        </section>

        <section class="db-card">
          <div class="db-head">
            <h2 class="db-title"><svg class="i c-teal"><use href="#i-chat"/></svg>Upcoming Consultations</h2>
            <a class="db-more" href="{{ route('student.consultation') }}">View all</a>
          </div>
          @if ($upcomingConsultations->isEmpty())
            <p class="db-empty">No consultation booked yet.</p>
            @if ($myTeam)
              <a class="db-btn-dark" style="align-self:flex-start" href="{{ route('student.consultation') }}">Book a consultation</a>
            @endif
          @else
            <ul class="db-list">
              @foreach ($upcomingConsultations as $consultation)
                <li>
                  <span class="db-date {{ $consultation->days <= 1 ? 'soon' : '' }}"><small>{{ $consultation->date->format('M') }}</small>{{ $consultation->date->format('j') }}</span>
                  <div class="db-cons"><b>{{ $consultation->who }}</b><span class="db-meta db-clip">{{ $consultation->when }}</span></div>
                  <span class="db-pill {{ $consultation->days <= 1 ? 'today' : '' }}">{{ match ($consultation->days) { 0 => 'Today', 1 => 'Tomorrow', default => 'In '.$consultation->days.' days' } }}</span>
                </li>
              @endforeach
            </ul>
          @endif
        </section>

        <section class="db-track db-qa-dark">
          <svg class="db-track-rings" aria-hidden="true" width="280" height="280" viewBox="0 0 280 280">
            <circle cx="140" cy="140" r="56" fill="none" stroke="rgba(201,168,44,0.18)"/>
            <circle cx="140" cy="140" r="88" fill="none" stroke="rgba(201,168,44,0.11)"/>
            <circle cx="140" cy="140" r="120" fill="none" stroke="rgba(201,168,44,0.06)"/>
          </svg>
          <span class="db-track-badge">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 12.5V8.5a1.5 1.5 0 0 1 3 0V11M9 11V7.5a1.5 1.5 0 0 1 3 0V11M12 11V7.5a1.5 1.5 0 0 1 3 0V11M15 11V8.5a1.5 1.5 0 0 1 3 0V14c0 2-.8 3.2-2 4v3.5H9V18c-2-.8-3-2.5-3-4.5v-1Z"/><path d="M6 12.5c0-.8.7-1.5 1.5-1.5H12a1.75 1.75 0 0 1 0 3.5h-1.5"/><path d="M12 3.5V1.8M6.8 4.3 5.800 3M17.2 4.3l1-1.3"/></svg>
          </span>
          <div>
            @if ($behind)
              <b>Let's catch up!</b>
              <p>{{ $behind }} {{ Str::plural('requirement', $behind) }} overdue. Catch up to stay on track.</p>
            @elseif ($myTeam)
              <b>You're on the right track!</b>
              <p>Keep pushing towards your defense.</p>
            @else
              <b>Let's get started!</b>
              <p>Join a team to begin your capstone journey.</p>
            @endif
          </div>
        </section>
      </div>

      <div class="db-row db-row-fill">
        <section class="db-card">
          <div class="db-head">
            <h2 class="db-title"><svg class="i c-purple"><use href="#i-team"/></svg>Your Team</h2>
          </div>
          @if ($myTeam)
            <div class="db-team-top">
              <div>
                <div class="db-team-name">{{ $myTeam->team_name }}</div>
                <div class="db-active">Active team</div>
              </div>
              <a class="db-btn-dark" href="{{ route('student.team') }}">Manage team →</a>
            </div>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="db-avatars">
                @foreach ($teamMembers->take(4) as $member)
                  <span style="{{ $avatarColours[$loop->index] }}" title="{{ $member->f_name }} {{ $member->l_name }}">{{ strtoupper(Str::substr($member->f_name ?? '', 0, 1).Str::substr($member->l_name ?? '', 0, 1)) }}</span>
                @endforeach
              </div>
              <span class="db-meta">{{ $teamMembers->count() }} {{ Str::plural('member', $teamMembers->count()) }}{{ $section ? ' · '.$section : '' }}</span>
            </div>
          @else
            <p class="db-empty">Join a team to see members here.</p>
          @endif
        </section>

        <section class="db-card">
          <div class="db-head">
            <h2 class="db-title"><svg class="i c-teal"><use href="#i-pulse"/></svg>Recent Activity</h2>
            @if ($recentActivity->isNotEmpty())
              {{-- All of it lives in the bell; stopPropagation keeps this click from closing the panel again. --}}
              <button type="button" class="db-more" onclick="event.stopPropagation();document.getElementById('notif-btn').click()">View all</button>
            @endif
          </div>
          @if ($recentActivity->isEmpty())
            <p class="db-empty">No activity yet.</p>
          @else
            <ul class="db-list">
              @foreach ($recentActivity as $activity)
                <li>
                  <span class="db-ico t-{{ $activity->tone }}"><svg class="i"><use href="#i-{{ $activity->icon }}"/></svg></span>
                  <span class="db-clip" style="flex:1;min-width:0;font-size:13px;font-weight:500">{{ $activity->text }}</span>
                  <time class="db-meta" style="white-space:nowrap">{{ $activity->at->format('M j · g:i A') }}</time>
                </li>
              @endforeach
            </ul>
          @endif
        </section>

        <section class="db-card">
          <div class="db-head">
            <h2 class="db-title"><svg class="i c-blue"><use href="#i-book"/></svg>Resources</h2>
            <a class="db-more" href="{{ route('student.resources') }}">View all</a>
          </div>
          @if ($newResourceCount)
            <div class="db-notice">
              <svg class="i c-orange"><use href="#i-megaphone"/></svg>
              <b>Your CaPSA uploaded {{ $newResourceCount }} new {{ Str::plural('resource', $newResourceCount) }}</b>
              <time class="db-meta">{{ $newResourceDate->format('M j') }}</time>
            </div>
          @endif
          @forelse ($recentResources as $resource)
            <a class="db-res" href="{{ $resource->url }}">
              <svg class="i {{ $resource->category === 'Rubrics' ? 'c-green' : 'c-blue' }}"><use href="#i-{{ $resource->category === 'Rubrics' ? 'tasks' : 'doc' }}"/></svg>
              <span class="db-clip">{{ $resource->title }}</span>
              <span class="db-pill">{{ $resource->category }}</span>
            </a>
          @empty
            <p class="db-empty">No resources yet.</p>
          @endforelse
        </section>
      </div>
    </main>

    {{-- Every announcement, opened from View all --}}
    <div class="ann-modal-overlay" id="annListModal" onclick="if(event.target===this)this.classList.remove('open')">
      <div class="ann-modal-box">
        <button class="ann-modal-close" onclick="document.getElementById('annListModal').classList.remove('open')">&times;</button>
        <div class="ann-modal-title">Announcements</div>
        <div class="ann-list">
          @foreach ($announcements as $announcement)
            @php $adminAvatar = $announcement->admin?->avatarUrl() ?? asset('images/default-avatar.jpg'); @endphp
            <div class="ann-item" onclick="openAnnModal(this)" {!! $annAttrs($announcement) !!}>
              <div class="ann-content">
                <div class="ann-header">
                  <div class="ann-title">{{ $announcement->title }}</div>
                </div>
                @if ($announcement->milestone)
                  <div style="display:flex;align-items:center;gap:6px;margin:4px 0 6px;font-size:12px;flex-wrap:wrap">
                    <span class="ann-tag" style="background:rgba(139,92,246,0.15);color:#7c3aed">Milestone</span>
                    @if ($announcement->milestone->due_date)
                      <span style="color:var(--amber);font-weight:500">
                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="vertical-align:text-bottom;margin-right:2px"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Due {{ $announcement->milestone->due_date->format('M j, Y') }}
                      </span>
                    @endif
                  </div>
                @endif
                <div class="ann-body">{{ $announcement->body }}</div>
                <div class="ann-footer">
                  <div class="ann-sender">
                    @if ($announcement->admin)
                      <img class="ann-avatar" src="{{ $adminAvatar }}" alt="{{ $announcement->admin->name }}">
                      <span class="ann-name">{{ $announcement->admin->name }}</span>
                    @endif
                  </div>
                  <div class="ann-date">{{ $announcement->created_at->format('M j, Y · g:i A') }}</div>
                </div>
                @if ($announcement->target_audience || $announcement->team)
                  <div class="ann-tags">
                    @foreach ((array) $announcement->target_audience as $ta)
                      @if (str_starts_with($ta, 'section:'))
                        <span class="ann-tag section">{{ substr($ta, 8) }}</span>
                      @endif
                    @endforeach
                    @if ($announcement->team)
                      <span class="ann-tag team">@ {{ $announcement->team->team_name }}</span>
                    @endif
                  </div>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    {{-- Announcement Modal --}}
    <div class="ann-modal-overlay" id="annModal" onclick="if(event.target===this)closeAnnModal()">
        <div class="ann-modal-box">
          <button class="ann-modal-close" onclick="closeAnnModal()">&times;</button>
          <div class="ann-modal-title" id="annModalTitle"></div>
          <div class="ann-modal-due" id="annModalDue" style="font-size:13px;color:var(--amber);font-weight:500;margin-bottom:10px;display:none"></div>
          <div class="ann-modal-body" id="annModalBody"></div>
          <div class="ann-modal-tags" id="annModalTags"></div>
          <div class="ann-modal-footer">
          <div class="ann-modal-sender">
            <img class="ann-avatar" id="annModalAvatar" alt="" hidden>
            <span id="annModalName"></span>
          </div>
          <div class="ann-modal-date" id="annModalDate"></div>
        </div>
      </div>
    </div>

    <div class="legal-credits">
      <div class="credits-title">System Developers</div>
      <div class="credits-names">Jolina · John Kenneth · Mark Anthony · Ian Cyrus · Alexa Nicole</div>
    </div>
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
function toggleSidebar() { document.body.classList.toggle('sidebar-open'); }
function closeSidebar() { document.body.classList.remove('sidebar-open'); }

// Scroll to announcements card if URL has #announcements-card
if (window.location.hash === '#announcements-card') {
  setTimeout(function() {
    var card = document.getElementById('announcements-card');
    if (card) {
      card.scrollIntoView({ behavior: 'smooth', block: 'start' });
      var items = card.querySelectorAll('.ann-item');
      if (items.length > 0) {
        items[0].classList.add('highlight');
        setTimeout(function() { items[0].classList.remove('highlight'); }, 2000);
      }
    }
  }, 600);
}

// Announcement modal
function openAnnModal(el) {
  var modal = document.getElementById('annModal');
  document.getElementById('annModalTitle').textContent = el.dataset.title;
  document.getElementById('annModalBody').textContent = el.dataset.body;
  document.getElementById('annModalDate').textContent = el.dataset.date;
  document.getElementById('annModalName').textContent = el.dataset.adminName;

  // No photo, or one that will not load, shows no picture rather than a
  // broken-image box beside the sender's name.
  var avatar = document.getElementById('annModalAvatar');
  avatar.onerror = function () { avatar.hidden = true; };
  avatar.hidden = !el.dataset.adminAvatar;
  if (el.dataset.adminAvatar) avatar.src = el.dataset.adminAvatar;
  else avatar.removeAttribute('src');


  var dueEl = document.getElementById('annModalDue');
  if (el.dataset.milestoneDue) {
    dueEl.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="vertical-align:text-bottom;margin-right:4px"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Due ' + el.dataset.milestoneDue;
    dueEl.style.display = 'block';
  } else {
    dueEl.style.display = 'none';
  }

  var tagsEl = document.getElementById('annModalTags');
  tagsEl.innerHTML = '';
  try {
    var sections = JSON.parse(el.dataset.sections || '[]');
    sections.forEach(function(s) {
      var span = document.createElement('span');
      span.className = 'ann-tag section';
      span.textContent = s;
      tagsEl.appendChild(span);
    });
  } catch(e) {}
  if (el.dataset.team) {
    var span = document.createElement('span');
    span.className = 'ann-tag team';
    span.textContent = '@ ' + el.dataset.team;
    tagsEl.appendChild(span);
  }

  modal.classList.add('open');
}

function closeAnnModal() {
  document.getElementById('annModal').classList.remove('open');
}
</script>

@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>

