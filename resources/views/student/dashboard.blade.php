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
<style>
  @keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .sk-overlay { position: absolute; inset: 0; background: var(--bg); z-index: 10; }
  .sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }

  .ann-list { display:flex;flex-direction:column;gap:0;max-height:320px;overflow-y:auto; }
  .ann-list::-webkit-scrollbar { width:4px; }
  .ann-list::-webkit-scrollbar-thumb { background:var(--border);border-radius:4px; }
  .ann-item { display:flex;gap:14px;padding:14px 16px;border-bottom:1px solid var(--border);transition:background 0.12s; }
  .ann-item:last-child { border-bottom:none; }
  .ann-item:hover { background:var(--bg3); }
  .ann-priority-bar { width:4px;border-radius:4px;flex-shrink:0;align-self:stretch; }
  .ann-content { flex:1;min-width:0; }
  .ann-header { display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:4px; }
  .ann-title { font-size:14px;font-weight:700;color:var(--text);line-height:1.3; }
  .ann-meta-top { display:flex;align-items:center;gap:6px;flex-shrink:0; }
  .ann-priority-tag { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.3px;padding:1px 7px;border-radius:4px; }
  .ann-priority-tag.important { background:rgba(217,119,6,0.1);color:#d97706; }
  .ann-priority-tag.urgent { background:rgba(220,38,38,0.1);color:#dc2626; }
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
  .ann-modal-priority{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px}
  .ann-modal-title{font-size:17px;font-weight:700;color:var(--text);line-height:1.3;margin-bottom:12px}
  .ann-modal-body{font-size:14px;color:var(--text2);line-height:1.7;margin-bottom:16px;white-space:pre-wrap}
  .ann-modal-tags{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:16px}
  .ann-modal-tags .ann-tag{font-size:11px;padding:3px 10px}
  .ann-modal-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-top:14px;border-top:1px solid var(--border)}
  .ann-modal-sender{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--text)}
  .ann-modal-date{font-size:12px;color:var(--text3)}
/* Where your team is: progress on the left, what is next on the right. */
.now-card .card-header{align-items:center}
.now-link{font-size:12.5px;font-weight:600;color:var(--accent3,#8a6e10);text-decoration:none}
.now-link:hover{text-decoration:underline}
.now-empty{margin:0;font-size:13.5px;color:var(--text2)}
.now-empty a{color:var(--accent3,#8a6e10);font-weight:600}
.now-grid{display:grid;grid-template-columns:minmax(200px,1fr) 2fr;gap:24px;align-items:start}
.now-pct{font-size:34px;font-weight:700;line-height:1;letter-spacing:-.02em;color:var(--text);font-variant-numeric:tabular-nums}
.now-bar{height:8px;border-radius:4px;background:var(--bg4,#eceef2);overflow:hidden;margin:12px 0 8px}
.now-bar span{display:block;height:100%;background:var(--accent,#c9a82c);border-radius:4px}
.now-sub{font-size:12.5px;color:var(--text2)}
.now-list{display:flex;flex-direction:column;gap:8px}
.now-row{display:flex;gap:12px;align-items:baseline;padding:10px 12px;border-radius:10px;background:var(--bg3,#f7f8fa);font-size:13.5px;color:var(--text)}
.now-row b{flex-shrink:0;min-width:120px;font-weight:700}
.now-row span{color:var(--text2);min-width:0}
.now-row.bad{background:rgba(220,38,38,.07)}
.now-row.bad b{color:var(--red,#dc2626)}
.now-row.ok b{color:var(--green,#16a34a)}
@media (max-width:760px){.now-grid{grid-template-columns:1fr}.now-row{flex-direction:column;gap:2px}.now-row b{min-width:0}}
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
    <div class="page-title">Dashboard</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'dashboard'])

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
    {{-- What the team has to do next, from its section's checklist: the
         one card worth reading first. Everything else on this page is
         something that happened, not something to do. --}}
    <div class="card now-card" id="now-card" style="margin-bottom:16px">
      <div class="card-header">
        <span class="card-title">Where your team is</span>
        @if ($progress)
          <a class="now-link" href="{{ route('student.progress') }}">Open Progress Tracker →</a>
        @endif
      </div>
      @if (! $myTeam)
        <p class="now-empty">You are not in a team yet. <a href="{{ route('student.team') }}">Form or join a team</a> to start your capstone.</p>
      @elseif (! $progress)
        <p class="now-empty">Your section's checklist is not set up yet. Your CaPSA adds it; it shows here once they do.</p>
      @else
        <div class="now-grid">
          <div class="now-progress">
            <div class="now-pct">{{ $overallProgress }}%</div>
            <div class="now-bar"><span style="width:{{ $overallProgress }}%"></span></div>
            <div class="now-sub">{{ $progress->completed }} of {{ $progress->total }} requirements done{{ $currentMilestone ? ' · now on '.$currentMilestone : '' }}</div>
          </div>
          <div class="now-list">
            @if ($overdueItems->isNotEmpty())
              <div class="now-row bad">
                <b>{{ $overdueItems->count() }} overdue</b>
                <span>{{ $overdueItems->take(2)->map(fn ($r) => $r->item->title)->implode(', ') }}{{ $overdueItems->count() > 2 ? ' and '.($overdueItems->count() - 2).' more' : '' }}</span>
              </div>
            @endif
            @if ($nextItem)
              <div class="now-row">
                <b>Next due {{ \Carbon\Carbon::parse($nextItem->due_on)->format('M j') }}</b>
                <span>{{ $nextItem->item->title }}</span>
              </div>
            @elseif ($overdueItems->isEmpty())
              <div class="now-row ok"><b>Nothing due</b><span>You are on track.</span></div>
            @endif
            @if ($nextDefense)
              <div class="now-row">
                <b>{{ $nextDefense->defense_type }}</b>
                <span>{{ $nextDefense->schedule_date->format('D, M j') }}@if ($nextDefense->schedule_date->format('H:i') !== '00:00') · {{ $nextDefense->schedule_date->format('g:i A') }}@endif{{ $nextDefense->venue ? ' · '.$nextDefense->venue : '' }}</span>
              </div>
            @endif
          </div>
        </div>
      @endif
    </div>

    <x-dashboard.info-card
      id="announcements-card"
      title="Announcements"
      :badge="$announcements->isNotEmpty() ? $announcements->count() : null"
      :badgeClass="'badge-accent'"
      :empty="$announcements->isEmpty()"
      emptyMessage="No announcements for your section." emptyIcon="announcement"
    >
      <div class="ann-list">
        @foreach ($announcements as $announcement)
          @php
            $priorityColor = match ($announcement->priority) {
              'urgent' => '#dc2626',
              'important' => '#d97706',
              default => 'var(--text3)',
            };
            $adminAvatar = $announcement->admin?->avatarUrl() ?? asset('images/default-avatar.jpg');
          @endphp
           <div class="ann-item" data-priority="{{ $announcement->priority }}" onclick="openAnnModal(this)"
                data-title="{{ $announcement->title }}"
                data-body="{{ $announcement->body }}"
                data-priority="{{ $announcement->priority }}"
                data-admin-name="{{ $announcement->admin?->name ?? 'Admin' }}"
                data-admin-avatar="{{ $adminAvatar }}"
                data-date="{{ $announcement->created_at->format('M j, Y · g:i A') }}"
                data-sections="{{ json_encode(array_values(array_filter(array_map(fn($ta) => str_starts_with((string) $ta, 'section:') ? substr((string) $ta, 8) : null, (array) $announcement->target_audience)))) }}"
                data-team="{{ $announcement->team?->team_name ?? '' }}"
                data-milestone-due="{{ $announcement->milestone?->due_date?->format('M j, Y') ?? '' }}">
            <div class="ann-priority-bar" style="background:{{ $priorityColor }}"></div>
            <div class="ann-content">
              <div class="ann-header">
                <div class="ann-title">{{ $announcement->title }}</div>
                <div class="ann-meta-top">
                  @if ($announcement->priority !== 'normal')
                    <span class="ann-priority-tag {{ $announcement->priority }}">{{ ucfirst($announcement->priority) }}</span>
                  @endif
                </div>
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
    </x-dashboard.info-card>

    {{-- Announcement Modal --}}
    <div class="ann-modal-overlay" id="annModal" onclick="if(event.target===this)closeAnnModal()">
        <div class="ann-modal-box">
          <button class="ann-modal-close" onclick="closeAnnModal()">&times;</button>
          <div class="ann-modal-priority" id="annModalPriority"></div>
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

    <div style="height:16px"></div>
    <div class="dash-grid-v3">
      <x-dashboard.info-card title="Capstone Expert" :empty="!$myTeam || $myTeam->expertAssignments->isEmpty()" emptyMessage="No experts assigned to your team." emptyIcon="expert">
        @if ($myTeam && $myTeam->expertAssignments->isNotEmpty())
          @foreach ($myTeam->expertAssignments as $assignment)
            @php
              // role() is belongsToMany — always a collection, never a single model
              $roleKey = strtolower($assignment->engaged_role_name ?? '');
              $dotColor = match ($roleKey) { 'adviser' => 'var(--accent)', 'consultant' => '#3b82f6', 'grammarian' => '#9333ea', 'panel' => '#f59e0b', default => '#6b7280' };
              $fgColor = match ($roleKey) { 'adviser' => 'var(--accent3)', 'consultant' => 'var(--blue)', 'grammarian' => 'var(--purple)', 'panel' => '#d97706', default => '#6b7280' };
              $isApproved = $assignment->status === 'approved';
            @endphp
            <div class="history-item">
              <div style="width:8px;height:8px;border-radius:50%;background:{{ $dotColor }};flex-shrink:0;"></div>
              <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                  <span style="font-size:11px;font-weight:700;color:{{ $fgColor }};text-transform:uppercase;letter-spacing:0.3px;">{{ $assignment->engaged_role_name ?? 'Unknown' }}</span>
                  <span style="font-size:9px;padding:1px 6px;border-radius:4px;background:{{ $isApproved ? 'rgba(16,185,129,0.1)' : 'rgba(245,158,11,0.1)' }};color:{{ $isApproved ? '#059669' : '#d97706' }};font-weight:700;">{{ $isApproved ? 'Approved' : ucfirst($assignment->status) }}</span>
                </div>
                <div style="font-size:12px;font-weight:600;margin-top:1px;">{{ $assignment->expert?->name ?? 'Unassigned' }}</div>
              </div>
            </div>
          @endforeach
        @endif
      </x-dashboard.info-card>

      <x-dashboard.info-card title="Team ({{ $myTeam ? $myTeam->team_name : 'No Team' }})" :empty="$teamMembers->isEmpty()" emptyMessage="Join a team to see members here." emptyIcon="team">
        @foreach ($teamMembers as $member)
          @php $isLeader = isset($leaderStudentId) && $member->student_id === $leaderStudentId; @endphp
          <div class="history-item">
            <div class="member-dot" style="background:{{ $isLeader ? '#fbbf24' : ($member->student_id === auth()->id() ? 'var(--accent)' : 'var(--text3)') }}"></div>
            <div>
              <div style="font-size:12px;font-weight:600;">
                {{ $member->f_name }} {{ $member->l_name }}
                @if ($isLeader) <span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px;margin-left:4px;">Leader</span> @endif
                @if ($member->student_id === auth()->id()) <span style="color:var(--accent);font-size:10px;font-weight:normal;">(You)</span> @endif
              </div>
              <div style="font-size:10px;color:var(--text3);">{{ $member->student_number }} · {{ $member->course }}</div>
            </div>
          </div>
        @endforeach
      </x-dashboard.info-card>
    </div>

    <div style="margin-top:16px;margin-bottom:16px">
    <x-dashboard.info-card
      title="Defense Results"
      :empty="$defenseResults->isEmpty()"
      emptyMessage="No defense stages scheduled yet." emptyIcon="defense"
    >
      <div class="milestone-list">
        @foreach ($defenseResults as $result)
          @php
            $allSubmitted = $result->submitted_count > 0 && $result->submitted_count >= $result->assigned_count;
            $partialSubmitted = $result->submitted_count > 0 && ! $allSubmitted;
          @endphp
          <div class="m-item">
            <div class="m-icon @if($allSubmitted) m-icon-done @elseif($partialSubmitted) m-icon-active @else m-icon-todo @endif">
              @if ($allSubmitted)
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              @elseif ($partialSubmitted)
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              @else
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>
              @endif
            </div>
            <div class="m-body">
              <div class="m-top">
                <span class="m-title">{{ $result->defense_type }}@if(($result->attempt ?? 1) > 1) <span style="font-weight:500;color:var(--text3)">&middot; Redefense {{ $result->attempt }}</span>@endif</span>
                <span class="m-badge @if($allSubmitted) m-badge-done @elseif($partialSubmitted) m-badge-active @else m-badge-todo @endif">
                  {{-- The score only appears once every panelist has graded:
                       it is their totals divided by the size of the panel, so
                       a partial one would read as final and then move. --}}
                  @if($result->average_score !== null)
                    {{ rtrim(rtrim(number_format($result->average_score, 2), '0'), '.') }} pts
                  @elseif($partialSubmitted)
                    Awaiting the rest of the panel
                  @else
                    Not graded yet
                  @endif
                </span>
              </div>
              <div style="font-size:11px;color:var(--text3);margin-top:2px">
                {{ $result->submitted_count }} of {{ $result->assigned_count }} panelist{{ $result->assigned_count === 1 ? '' : 's' }} graded
                @if($result->schedule_date)
                  · {{ $result->schedule_date->format('M j, Y') }}
                  @if($result->schedule_date->format('H:i') !== '00:00')
                    {{ $result->schedule_date->format('g:i A') }}–{{ ($result->end_date ?? $result->schedule_date->copy()->addHours(2))->format('g:i A') }}
                  @endif
                @endif
              </div>
            </div>
          </div>
        @endforeach
      </div>
    </x-dashboard.info-card>

    </div>

    <div class="legal-credits">
      <div class="credits-title">System Developers</div>
      <div class="credits-names">Jolina · John Kenneth · Mark Anthony · Ian Cyrus · Alexa Nicole</div>
    </div>
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

  var priority = el.dataset.priority;
  var priorityEl = document.getElementById('annModalPriority');
  var pColor = priority === 'urgent' ? '#dc2626' : priority === 'important' ? '#d97706' : 'var(--text3)';
  priorityEl.textContent = priority === 'normal' ? '' : priority;
  priorityEl.style.color = pColor;

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
</body>
</html>

