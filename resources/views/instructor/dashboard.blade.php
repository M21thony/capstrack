<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CapsTRACK Expert Portal</title>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/expert.css') }}" />
<x-dashboard.styles />
<link href="{{ \App\Support\PublicAsset::url('css/dashboard-redesign.css') }}" rel="stylesheet">
<style nonce="{{ Vite::cspNonce() }}">
  .ann-list { display:flex;flex-direction:column;gap:0;max-height:320px;overflow-y:auto; }
  .ann-list::-webkit-scrollbar { width:4px; }
  .ann-list::-webkit-scrollbar-thumb { background:var(--border);border-radius:4px; }
  .ann-item { display:flex;gap:14px;padding:14px 16px;border-bottom:1px solid var(--border);transition:background 0.12s;cursor:pointer; }
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
</style>
@include('partials.skeleton-head', ['skReveal' => 'block'])
  @include('partials.render-whole')
</head>
<body>
@include('partials.dashboard-sprite')
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
  <div class="content db-page">
    @php
      $slots = $stats['slots'];
      $slotPct = $slots['max'] ? min(100, (int) round($slots['used'] / $slots['max'] * 100)) : null;
      $roles = collect($stats['byRole'])->filter(fn ($role) => $role['value'] > 0)->pluck('label');
      $consultCount = $stats['consultations']['upcoming'];

      // Team Distribution: four sections and the rest as Others, so the ring stays readable.
      $sections = collect($stats['bySection']);
      if ($sections->count() > 5) {
          $sections = $sections->take(4)->push(['label' => 'Others', 'value' => $sections->slice(4)->sum('value')]);
      }
      $sectionTotal = $sections->sum('value');
      $ringColours = ['#3b82f6', '#eab308', '#8b5cf6', '#16a34a', '#64748b'];
      $ring = 2 * M_PI * 38;
      $done = 0;
      $slices = [];
      foreach ($sections->values() as $i => $row) {
          $length = $row['value'] / max(1, $sectionTotal) * $ring;
          $middle = ($done + $length / 2) / 38;
          $slices[] = $row + [
              'colour' => $ringColours[$i],
              'length' => round($length, 1),
              'offset' => round(-$done, 1),
              'x' => round(52 + 38 * sin($middle)),
              'y' => round(52 - 38 * cos($middle) + 4),
          ];
          $done += $length;
      }
    @endphp

    <main class="db db-expert">
      <section class="db-banner">
        <div class="db-banner-text">
          <div>
            <h1>Good day, <span>{{ $expert->name }}!</span></h1>
            <p>{{ $roles->push($stats['teams'].' '.\Illuminate\Support\Str::plural('team', $stats['teams']))->implode(' · ') }}</p>
          </div>
        </div>
        <div class="db-banner-photo" aria-hidden="true"></div>
        <div class="db-banner-year"><svg class="i c-orange"><use href="#i-cal"/></svg><div>Academic Year<b>S.Y. {{ str_replace('-', '–', $schoolYear) }}</b></div></div>
      </section>

      <div class="db-tiles">
        <a class="db-tile tile-gold" href="{{ route('instructor.teams') }}">
          <span class="db-ico t-purple"><svg class="i"><use href="#i-team"/></svg></span>
          <div class="db-tile-body"><b>{{ $stats['teams'] }}</b><span class="lab">Assigned Teams</span></div>
          <span class="db-tile-more">View Teams →</span>
        </a>
        <a class="db-tile tile-slate" href="{{ route('instructor.docreview') }}">
          <span class="db-ico t-blue"><svg class="i"><use href="#i-doc"/></svg></span>
          <div class="db-tile-body"><b>{{ $docsForReview }}</b><span class="lab">Pending Reviews</span></div>
          <span class="db-tile-more">View Documents →</span>
        </a>
        <a class="db-tile tile-green" href="{{ route('instructor.grading') }}">
          <span class="db-ico t-green"><svg class="i"><use href="#i-checkc"/></svg></span>
          <div class="db-tile-body"><b>{{ $stats['panels']['graded'] }}</b><span class="lab">Defenses Graded</span></div>
          <span class="db-tile-more">View Grading →</span>
        </a>
        <a class="db-tile tile-dark" href="{{ route('instructor.schedule') }}">
          <span class="db-ico t-orange"><svg class="i"><use href="#i-cal"/></svg></span>
          <div class="db-tile-body"><b>{{ $consultCount }}</b><span class="lab">Upcoming Consultations</span></div>
          <span class="db-tile-more">View Schedule →</span>
        </a>
      </div>

      <div class="db-xgrid">
        <section class="db-card db-x-work">
          <div class="db-head">
            <h2 class="db-title"><svg class="i c-teal"><use href="#i-chart"/></svg>My Workload</h2>
          </div>
          <div class="db-split">
            <div class="db-ring">
              <svg viewBox="0 0 104 104" role="img" aria-label="{{ $slotPct === null ? 'Team limit not set' : "{$slots['used']} of {$slots['max']} team slots used, {$slotPct} percent" }}">
                <circle cx="52" cy="52" r="44" fill="none" stroke="#eceef2" stroke-width="14"/>
                @if ($slotPct)
                  <circle cx="52" cy="52" r="44" fill="none" stroke="#16a34a" stroke-width="14" stroke-linecap="round" stroke-dasharray="{{ round($slotPct / 100 * 276.5, 1) }} 276.5" transform="rotate(-90 52 52)"/>
                @endif
              </svg>
              <div><b>{{ $slotPct === null ? '—' : $slotPct.'%' }}</b>Slots Used</div>
            </div>
            <ul class="db-legend">
              <li><i style="background:#16a34a"></i><span>{{ $slotPct === null ? 'Team limit not set' : 'Team Slots' }}</span><b>{{ $slotPct === null ? $slots['used'] : $slots['used'].' / '.$slots['max'] }}</b></li>
              <li><i style="background:#3b82f6"></i><span>Pending Reviews</span><b>{{ $docsForReview }}</b></li>
              <li><i style="background:#eab308"></i><span>Grades to Submit</span><b>{{ $gradesToSubmit }}</b></li>
              <li><i style="background:#8b5cf6"></i><span>Consultations</span><b>{{ $consultCount }}</b></li>
            </ul>
          </div>
        </section>

        <section class="db-card db-x-team">
          <div class="db-head">
            <h2 class="db-title"><svg class="i c-purple"><use href="#i-team"/></svg>Team Distribution</h2>
            <a class="db-more" href="{{ route('instructor.teams') }}">View all →</a>
          </div>
          @if ($slices)
            <div class="db-split">
              <div class="db-ring">
                <svg viewBox="0 0 104 104" role="img" aria-label="{{ collect($slices)->map(fn ($slice) => $slice['label'].' '.$slice['value'])->implode(', ') }}">
                  @foreach ($slices as $slice)
                    <circle cx="52" cy="52" r="38" fill="none" stroke="{{ $slice['colour'] }}" stroke-width="26" stroke-dasharray="{{ $slice['length'] }} {{ round($ring, 1) }}" stroke-dashoffset="{{ $slice['offset'] }}" transform="rotate(-90 52 52)"/>
                  @endforeach
                  <g fill="#fff" font-size="11" font-weight="700" text-anchor="middle">
                    @foreach ($slices as $slice)
                      <text x="{{ $slice['x'] }}" y="{{ $slice['y'] }}">{{ $slice['value'] }}</text>
                    @endforeach
                  </g>
                </svg>
                <div><b>{{ $sectionTotal }}</b>Teams</div>
              </div>
              <ul class="db-legend">
                @foreach ($slices as $slice)
                  <li><i style="background:{{ $slice['colour'] }}"></i><span>{{ $slice['label'] }}</span><b>{{ $slice['value'] }}</b></li>
                @endforeach
              </ul>
            </div>
          @else
            <p class="db-empty">No teams yet.</p>
          @endif
        </section>

        <div class="db-xside">
          <section class="db-card">
            <div class="db-head">
              <h2 class="db-title"><svg class="i c-teal"><use href="#i-chat"/></svg>Consultations</h2>
              <a class="db-more" href="{{ route('instructor.schedule') }}">View all →</a>
            </div>
            <ul class="db-list" style="--empty:{{ 3 - max(1, $upcoming->count()) }}">
              @forelse ($upcoming as $item)
                @php $days = (int) today()->diffInDays($item->when->copy()->startOfDay()); @endphp
                <li>
                  <div class="db-stack">
                    <b class="db-clip">{{ $item->who }}</b>
                    <span class="db-meta db-clip">{{ collect([$item->when->format('M j'), $item->timed ? $item->when->format('g:i A') : null, $item->what, $item->where])->filter()->implode(' · ') }}</span>
                  </div>
                  <span class="db-pill {{ $days < 2 ? 'today' : '' }}">{{ $days === 0 ? 'Today' : ($days === 1 ? 'Tomorrow' : "In {$days} days") }}</span>
                </li>
              @empty
                <li class="db-empty">Nothing coming up.</li>
              @endforelse
            </ul>
          </section>

          <section class="db-card">
            <div class="db-head">
              <h2 class="db-title"><svg class="i c-green"><use href="#i-tasks"/></svg>Upcoming Tasks</h2>
              <a class="db-more" href="{{ route('instructor.teams', ['tab' => 'applications']) }}">View all →</a>
            </div>
            <ul class="db-list" style="--empty:{{ 3 - max(1, count($tasks)) }}">
              @forelse ($tasks as $task)
                <li>
                  <a class="rowlink" href="{{ $task['link'] }}">
                    <span class="db-ico t-{{ $task['tint'] }}"><svg class="i"><use href="#i-{{ $task['icon'] }}"/></svg></span>
                    <div class="db-stack"><b class="db-clip">{{ $task['title'] }}</b><span class="db-meta db-clip">{{ $task['meta'] }}</span></div>
                  </a>
                </li>
              @empty
                <li class="db-empty">Nothing is waiting on you.</li>
              @endforelse
            </ul>
          </section>

          <section class="db-card">
            <div class="db-head">
              <h2 class="db-title"><svg class="i c-orange"><use href="#i-megaphone"/></svg>Announcements</h2>
              @if ($announcements->isNotEmpty())
                <button type="button" class="db-more" onclick="document.getElementById('annListModal').classList.add('open')">View all →</button>
              @endif
            </div>
            @if ($latest = $announcements->first())
              <div class="db-notice" style="cursor:pointer" onclick="openAnnModal(document.querySelector('#annListModal .ann-item'))">
                <div class="db-stack">
                  <b class="db-clip">{{ $latest->title }}</b>
                  <span class="db-meta db-clip">{{ $latest->body }}</span>
                  <time>{{ $latest->created_at->format('M j') }}</time>
                </div>
              </div>
            @else
              <p class="db-empty">No announcements.</p>
            @endif
          </section>
        </div>

        <section class="db-card db-x-act">
          <div class="db-head">
            <h2 class="db-title"><svg class="i c-teal"><use href="#i-pulse"/></svg>Recent Activity</h2>
          </div>
          <ul class="db-list" style="--empty:{{ 4 - max(1, count($recentActivity)) }}">
            @forelse ($recentActivity as $activity)
              <li>
                <span class="db-ico t-{{ $activity['tint'] }}"><svg class="i"><use href="#i-{{ $activity['icon'] }}"/></svg></span>
                <div class="db-stack"><span class="db-clip">{{ $activity['text'] }}</span><time>{{ $activity['at'] }}</time></div>
              </li>
            @empty
              <li class="db-empty">Nothing yet.</li>
            @endforelse
          </ul>
        </section>

        <section class="db-card db-x-quick">
          <div class="db-head">
            <h2 class="db-title"><svg class="i c-orange"><use href="#i-bolt"/></svg>Quick Access</h2>
          </div>
          <div class="db-qa-grid">
            <a class="db-qa" href="{{ route('instructor.teams') }}"><svg class="i c-purple"><use href="#i-team"/></svg>My Teams</a>
            <a class="db-qa" href="{{ route('instructor.docreview') }}"><svg class="i c-blue"><use href="#i-doc"/></svg>Documents</a>
            <a class="db-qa" href="{{ route('instructor.schedule') }}"><svg class="i c-orange"><use href="#i-cal"/></svg>Schedule</a>
            <a class="db-qa" href="{{ route('instructor.grading') }}"><svg class="i c-green"><use href="#i-check"/></svg>Grading</a>
            <a class="db-qa" href="{{ route('instructor.evaluations') }}"><svg class="i c-orange"><use href="#i-star"/></svg>Evaluations</a>
            <a class="db-qa" href="{{ route('instructor.profile') }}"><svg class="i c-purple"><use href="#i-user"/></svg>Profile</a>
          </div>
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
            @php
              $adminAvatar = $announcement->admin?->avatarUrl() ?? asset('images/default-avatar.jpg');
            @endphp
            <div class="ann-item" onclick="openAnnModal(this)"
                 data-title="{{ $announcement->title }}"
                 data-body="{{ $announcement->body }}"
                 data-admin-name="{{ $announcement->admin?->name ?? 'Admin' }}"
                 data-admin-avatar="{{ $adminAvatar }}"
                 data-date="{{ $announcement->created_at->format('M j, Y · g:i A') }}"
                 data-team="{{ $announcement->team?->team_name ?? '' }}"
                 data-milestone-due="{{ $announcement->milestone?->due_date?->format('M j, Y') ?? '' }}">
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
              <img class="ann-avatar" id="annModalAvatar" src="" alt="">
              <span id="annModalName"></span>
            </div>
            <div class="ann-modal-date" id="annModalDate"></div>
          </div>
        </div>
      </div>

  </div>
  </div>
</div>

@include('instructor.partials.skeleton-toggle')
<script nonce="{{ Vite::cspNonce() }}">
// Announcement modal
function openAnnModal(el) {
  var modal = document.getElementById('annModal');
  document.getElementById('annModalTitle').textContent = el.dataset.title;
  document.getElementById('annModalBody').textContent = el.dataset.body;
  document.getElementById('annModalDate').textContent = el.dataset.date;
  document.getElementById('annModalName').textContent = el.dataset.adminName;

  var avatar = document.getElementById('annModalAvatar');
  avatar.src = el.dataset.adminAvatar;


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
@include('partials.app-scrollbar')
</body>
</html>
