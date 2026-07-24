<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Dashboard - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/Styles/dashboard.css')
<x-dashboard.styles />
<style>
  #pageSkeleton { position: absolute; inset: 0; z-index: 50; padding: 0; opacity: 1; transition: opacity 0.35s ease; pointer-events: auto; }
  #pageSkeleton.loaded { opacity: 0; pointer-events: none; }

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
  .ann-modal-box{background:var(--bg2);border-radius:16px;padding:28px;max-width:520px;width:90%;max-height:80vh;overflow-y:auto;position:relative}
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
</style>
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
    <div id="contentWrapper" style="position: relative;">

    {{-- Skeleton loader --}}
    <div id="pageSkeleton">
      <div class="grid-4" style="margin-bottom:16px">
        <x-dashboard.stat-chip loading />
        <x-dashboard.stat-chip loading />
        <x-dashboard.stat-chip loading />
        <x-dashboard.stat-chip loading />
      </div>
      <div class="card" style="margin-bottom:16px">
        <div class="card-header"><span class="skeleton-box" style="width:110px;height:12px;border-radius:4px;"></span><span class="skeleton-box" style="width:40px;height:18px;border-radius:12px;"></span></div>
        <div><div class="skeleton-box" style="width:70%;height:13px;border-radius:4px;margin-bottom:6px;"></div><div class="skeleton-box" style="width:40%;height:11px;border-radius:4px;"></div></div>
      </div>
    </div>

    {{-- Content --}}
    <div class="grid-4" style="margin-bottom:16px">
      <x-dashboard.stat-chip value="{{ $overallProgress }}%" label="Overall Progress" color="var(--accent)" />
      <x-dashboard.stat-chip value="{{ $docsSubmitted }}" label="Docs Submitted" color="var(--green)" />
      <x-dashboard.stat-chip value="{{ $pendingReviews }}" label="Pending Reviews" color="var(--amber)" />
      <x-dashboard.stat-chip value="{{ $teamGroupsCount }}" label="Team Groups" color="var(--purple)" />
    </div>

    <x-dashboard.info-card
      id="announcements-card"
      title="Announcements"
      :badge="$announcements->isNotEmpty() ? $announcements->count() : null"
      :badgeClass="'badge-accent'"
      :empty="$announcements->isEmpty()"
      emptyMessage="No announcements for your section."
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
               data-team="{{ $announcement->team?->team_name ?? '' }}">
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
        <div class="ann-modal-body" id="annModalBody"></div>
        <div class="ann-modal-tags" id="annModalTags"></div>
        <div class="ann-modal-footer">
          <div class="ann-modal-sender">
            <img class="ann-avatar" id="annModalAvatar" src="" alt="">
            <span id="annModalName"></span>
          </div>
          <div class="ann-modal-date" id="annModalDate"></div>
        </div>
      </div>
    </div>

    <div style="height:16px"></div>

    <div class="grid-2" style="margin-bottom:16px">
      <x-dashboard.info-card
        title="Milestone Progress"
        :badge="$milestones->where('status', 'in_progress')->count() > 0 ? 'In Progress' : null"
        :badgeClass="'badge-blue'"
        :empty="$milestones->isEmpty()"
        emptyMessage="No milestones yet. Track your progress from the Progress page."
      >
        <div class="gap-12">
          @foreach ($milestones->take(4) as $milestone)
            @php
              $pct = $milestone->status === 'completed' ? 100 : ($milestone->status === 'in_progress' ? 50 : 0);
              $pctColor = $milestone->status === 'completed' ? 'var(--green)' : ($milestone->status === 'in_progress' ? '#8a6500' : 'var(--text3)');
              $barColor = $milestone->status === 'completed' ? 'progress-green' : 'progress-blue';
            @endphp
            <div>
              <div class="row" style="margin-bottom:6px;justify-content:space-between">
                <span style="font-size:12px;color:var(--text2)">{{ $milestone->title }}</span>
                <span style="font-size:11px;color:{{ $pctColor }}">{{ $pct }}%</span>
              </div>
              <div class="progress-bar"><div class="progress-fill {{ $barColor }}" style="width:{{ $pct }}%"></div></div>
            </div>
          @endforeach
        </div>
      </x-dashboard.info-card>

      <x-dashboard.info-card
        title="Recent Activity"
        :empty="$activities->isEmpty()"
        emptyMessage="No recent activity."
      >
        <div class="act-scroll" style="max-height:260px">
          @foreach ($activities as $activity)
            <div class="activity-item">
              <div class="act-icon" style="background:{!! $activity->icon_bg !!}">{!! $activity->icon_svg !!}</div>
              <div class="act-text">{!! $activity->text !!}<div class="act-time">{{ $activity->time }}</div></div>
            </div>
          @endforeach
        </div>
      </x-dashboard.info-card>
    </div>

    <div class="dash-grid-v3">
      <x-dashboard.info-card title="Capstone Expert" :empty="!$myTeam || $myTeam->expertAssignments->isEmpty()" emptyMessage="No experts assigned to your team.">
        @if ($myTeam && $myTeam->expertAssignments->isNotEmpty())
          @foreach ($myTeam->expertAssignments as $assignment)
            @php
              $roleKey = strtolower($assignment->role?->role_name ?? '');
              $dotColor = match ($roleKey) { 'adviser' => 'var(--accent)', 'consultant' => '#3b82f6', 'grammarian' => '#9333ea', 'panel' => '#f59e0b', default => '#6b7280' };
              $fgColor = match ($roleKey) { 'adviser' => 'var(--accent3)', 'consultant' => 'var(--blue)', 'grammarian' => 'var(--purple)', 'panel' => '#d97706', default => '#6b7280' };
              $isApproved = $assignment->status === 'approved';
            @endphp
            <div class="history-item">
              <div style="width:8px;height:8px;border-radius:50%;background:{{ $dotColor }};flex-shrink:0;"></div>
              <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                  <span style="font-size:11px;font-weight:700;color:{{ $fgColor }};text-transform:uppercase;letter-spacing:0.3px;">{{ $assignment->role?->role_name ?? 'Unknown' }}</span>
                  <span style="font-size:9px;padding:1px 6px;border-radius:4px;background:{{ $isApproved ? 'rgba(16,185,129,0.1)' : 'rgba(245,158,11,0.1)' }};color:{{ $isApproved ? '#059669' : '#d97706' }};font-weight:700;">{{ $isApproved ? 'Approved' : ucfirst($assignment->status) }}</span>
                </div>
                <div style="font-size:12px;font-weight:600;margin-top:1px;">{{ $assignment->expert?->name ?? 'Unassigned' }}</div>
              </div>
            </div>
          @endforeach
        @endif
      </x-dashboard.info-card>

      <x-dashboard.info-card title="Team ({{ $myTeam ? $myTeam->team_name : 'No Team' }})" :empty="$teamMembers->isEmpty()" emptyMessage="Join a team to see members here.">
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

    <div class="legal-credits">
      <div class="credits-title">System Developers</div>
      <div class="credits-names">Jolina · John Kenneth · Mark Anthony · Ian Cyrus · Alexa Nicole</div>
    </div>

  </div>
  </div>
</div>

<script>
var skeleton = document.getElementById('pageSkeleton');
if (skeleton) { setTimeout(function() { skeleton.classList.add('loaded'); }, 450); }
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

  var avatar = document.getElementById('annModalAvatar');
  avatar.src = el.dataset.adminAvatar;

  var priority = el.dataset.priority;
  var priorityEl = document.getElementById('annModalPriority');
  var pColor = priority === 'urgent' ? '#dc2626' : priority === 'important' ? '#d97706' : 'var(--text3)';
  priorityEl.textContent = priority === 'normal' ? '' : priority;
  priorityEl.style.color = pColor;

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
</body>
</html>
