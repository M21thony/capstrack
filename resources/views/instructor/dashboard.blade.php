<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CapsTRACK Expert Portal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/expert.css') }}" />
<x-dashboard.styles />
<style>
  .ann-list { display:flex;flex-direction:column;gap:0;max-height:320px;overflow-y:auto; }
  .ann-list::-webkit-scrollbar { width:4px; }
  .ann-list::-webkit-scrollbar-thumb { background:var(--border);border-radius:4px; }
  .ann-item { display:flex;gap:14px;padding:14px 16px;border-bottom:1px solid var(--border);transition:background 0.12s;cursor:pointer; }
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
</style>
@include('partials.skeleton-head', ['skReveal' => 'block'])
</head>
<body>
@include('instructor.partials.sidebar', ['currentPage' => 'dashboard'])
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
@include('partials.welcome-modal')

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Dashboard</div>
    <div class="topbar-actions">
      @include('instructor.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="grid-3" style="margin-bottom:16px">
      <div class="sk" style="height:78px;border-radius:14px"></div>
      <div class="sk" style="height:78px;border-radius:14px"></div>
      <div class="sk" style="height:78px;border-radius:14px"></div>
    </div>
    <div class="grid-2" style="margin-bottom:16px">
      <div class="card">
        <div class="sk" style="width:140px;height:16px;border-radius:4px;margin-bottom:16px"></div>
        <div class="sk" style="height:56px;border-radius:8px;margin-bottom:10px"></div>
        <div class="sk" style="height:56px;border-radius:8px;margin-bottom:10px"></div>
        <div class="sk" style="height:56px;border-radius:8px"></div>
      </div>
      <div class="card">
        <div class="sk" style="width:180px;height:16px;border-radius:4px;margin-bottom:16px"></div>
        <div class="sk" style="height:56px;border-radius:8px;margin-bottom:10px"></div>
        <div class="sk" style="height:56px;border-radius:8px"></div>
      </div>
    </div>
  </div>

  <div id="real-content" style="display:none">
  <div class="content">
    {{-- What is waiting on this expert, each a link to where it is done.
         Totals that ask nothing of them (slots open, teams advised) went. --}}
    <div class="grid-3" style="margin-bottom:16px">
      <x-dashboard.stat-chip
        :href="route('instructor.assignments')"
        value="{{ $pendingApplications + $titlesToApprove }}"
        label="Team Requests" icon="inbox"
        sub="{{ $titlesToApprove ? $titlesToApprove.' '.\Illuminate\Support\Str::plural('title', $titlesToApprove).' to approve' : ($pendingApplicationsNew ? $pendingApplicationsNew.' new this week' : 'Nothing waiting') }}"
        color="var(--accent3)"
      />
      <x-dashboard.stat-chip
        :href="route('instructor.docreview')"
        value="{{ $docsForReview }}"
        label="Documents to Review" icon="file-review"
        {{-- Waiting since it arrived. It used to read reviewed_at, which is null
             on anything still waiting, so this rendered "Oldest: " and nothing. --}}
        sub="{{ $oldestDocReview ? 'Oldest: waiting '.$oldestDocReview->created_at?->diffForHumans(null, true) : 'Nothing waiting' }}"
        color="var(--red)"
      />
      <x-dashboard.stat-chip
        :href="route('instructor.grading')"
        value="{{ $gradesToSubmit }}"
        label="Grades to Submit" icon="grading"
        sub="{{ $gradesToSubmit ? 'Defenses you sat on' : 'All graded' }}"
        color="var(--blue)"
      />
    </div>

    <div class="grid-2" style="margin-bottom:16px">
      <x-dashboard.info-card
        title="Announcements"
        :badge="$announcements->isNotEmpty() ? $announcements->count() : null"
        :badgeClass="'badge-accent'"
        :empty="$announcements->isEmpty()"
        emptyMessage="No announcements." emptyIcon="announcement"
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
            <div class="ann-item" onclick="openAnnModal(this)"
                 data-title="{{ $announcement->title }}"
                 data-body="{{ $announcement->body }}"
                 data-priority="{{ $announcement->priority }}"
                 data-admin-name="{{ $announcement->admin?->name ?? 'Admin' }}"
                 data-admin-avatar="{{ $adminAvatar }}"
                 data-date="{{ $announcement->created_at->format('M j, Y · g:i A') }}"
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
              <img class="ann-avatar" id="annModalAvatar" src="" alt="">
              <span id="annModalName"></span>
            </div>
            <div class="ann-modal-date" id="annModalDate"></div>
          </div>
        </div>
      </div>

      <x-dashboard.info-card
        title="Coming Up"
        :empty="$upcoming->isEmpty()"
        emptyMessage="No defenses or consultations coming up." emptyIcon="consultation"
      >
        <div class="gap-12">
          @foreach ($upcoming as $item)
            <div class="consult-card">
              <div class="consult-header">
                <div>
                  <div class="consult-team">{{ $item->what }}{{ $item->who ? ' · '.$item->who : '' }}</div>
                  <div class="consult-meta">{{ $item->when->format('D, M j') }}{{ $item->timed ? ' · '.$item->when->format('g:i A') : '' }}{{ $item->where ? ' · '.$item->where : '' }}</div>
                </div>
                <span class="badge {{ $item->tag === 'Consultation' ? 'badge-blue' : 'badge-gold' }}">{{ $item->tag }}</span>
              </div>
            </div>
          @endforeach
        </div>
      </x-dashboard.info-card>
    </div>

  </div>
  </div>
</div>

@include('instructor.partials.expert-profile')
@include('instructor.partials.skeleton-toggle')
<script>
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

  var dueEl = document.getElementById('annModalDue');
  if (el.dataset.milestoneDue) {
    dueEl.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="vertical-align:text-bottom;margin-right:4px"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Due ' + el.dataset.milestoneDue;
    dueEl.style.display = 'block';
  } else {
    dueEl.style.display = 'none';
  }

  var tagsEl = document.getElementById('annModalTags');
  tagsEl.innerHTML = '';
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


/* Mobile sidebar toggle */
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');

function toggleSidebar() {
  if (!sidebarEl) return;
  var isOpen = sidebarEl.classList.contains('mobile-open');
  if (isOpen) { closeSidebar(); } else { openSidebar(); }
}
function openSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.add('mobile-open');
  sidebarOverlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.remove('mobile-open');
  sidebarOverlay.classList.remove('open');
  document.body.style.overflow = '';
}
</script>
@include('partials.escape-closes-modals')
</body>
</html>
