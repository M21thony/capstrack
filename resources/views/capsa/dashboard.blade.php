<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Dashboard - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/dashboard-redesign.css') }}" rel="stylesheet">
@include('partials.skeleton-head', ['skReveal' => 'flex'])
  @include('partials.render-whole')
</head>
<body>
@include('partials.dashboard-sprite')

@include('capsa._sidebar')
@include('partials.welcome-modal')

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Dashboard</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="sk" style="width:260px;height:16px;border-radius:4px;margin-bottom:14px"></div>
    <div class="grid-4" style="margin-bottom:16px">
      <div class="sk" style="height:78px;border-radius:14px"></div>
      <div class="sk" style="height:78px;border-radius:14px"></div>
      <div class="sk" style="height:78px;border-radius:14px"></div>
      <div class="sk" style="height:78px;border-radius:14px"></div>
    </div>
    <div class="grid-2">
      <div class="card"><div class="sk" style="height:150px;border-radius:12px"></div></div>
      <div class="card"><div class="sk" style="height:150px;border-radius:12px"></div></div>
    </div>
  </div>
  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content {{ ($capsa || $admin) ? 'db-page' : '' }}">
    @if ($capsa && $stats['capsa'])
      @php
        $c = $stats['capsa'];
        $coverageRate = $c['teams'] > 0 ? round($c['covered'] / $c['teams'] * 100, 1) : 0;
        $openIssues = collect($capsa['issues'])->where('count', '>', 0);
        $issueTotal = $openIssues->sum('count');
        $inDays = function ($date) {
            $days = (int) now()->startOfDay()->diffInDays($date->copy()->startOfDay());

            return match ($days) { 0 => 'Today', 1 => 'Tomorrow', default => "In {$days} days" };
        };
        $activityIcon = [
            'team_joined' => ['team', 't-purple'],
            'team_left' => ['team', 't-red'],
            'team_status_changed' => ['pulse', 't-blue'],
            'team_confirmed' => ['check', 't-green'],
            'team_verified' => ['check', 't-green'],
            'expert_removed' => ['warn', 't-red'],
        ];
      @endphp
      <main class="db db-capsa">
        <section class="db-banner">
          <div class="db-banner-text">
            <div>
              <h1>Welcome back, <span>{{ $viewer->name }}!</span></h1>
              <p>CaPSA · {{ implode(' · ', $viewer->handledPrograms()) }} · {{ $c['sections'] }} {{ \Illuminate\Support\Str::plural('section', $c['sections']) }}</p>
            </div>
          </div>
          <div class="db-banner-photo" aria-hidden="true"></div>
          <div class="db-banner-year"><svg class="i c-orange"><use href="#i-cal"/></svg><div>Academic Year<b>S.Y. {{ str_replace('-', '–', $capsa['year']) }}</b></div></div>
        </section>

        <div class="db-tiles">
          <a class="db-tile tile-gold" href="{{ route('capsa.team_management', ['tab' => 'teams']) }}">
            <span class="db-ico t-purple"><svg class="i"><use href="#i-team"/></svg></span>
            <div class="db-tile-body"><b>{{ $c['teams'] }}</b><span class="lab">Total Teams</span></div>
            <span class="db-delta {{ $c['newTeams'] ? 'up' : '' }}" title="New in the last 30 days">{{ $c['newTeams'] ? '↑ +'.$c['newTeams'] : '→ 0' }}</span>
          </a>
          <a class="db-tile tile-slate" href="{{ route('capsa.sections') }}">
            <span class="db-ico t-blue"><svg class="i"><use href="#i-cap"/></svg></span>
            <div class="db-tile-body"><b>{{ $c['students'] }}</b><span class="lab">Total Students</span></div>
            <span class="db-delta {{ $c['newStudents'] ? 'up' : '' }}" title="New in the last 30 days">{{ $c['newStudents'] ? '↑ +'.$c['newStudents'] : '→ 0' }}</span>
          </a>
          <a class="db-tile tile-green" href="{{ route('capsa.reports.show', 'master') }}">
            <span class="db-ico t-green"><svg class="i"><use href="#i-shield"/></svg></span>
            <div class="db-tile-body"><b>{{ $coverageRate }}%</b><span class="lab">Coverage Rate</span><span class="db-bar"><span style="width:{{ $coverageRate }}%"></span></span></div>
          </a>
          <a class="db-tile alert" href="#issues">
            <span class="db-ico t-red"><svg class="i"><use href="#i-warn"/></svg></span>
            <div class="db-tile-body"><b>{{ $issueTotal }}</b><span class="lab">Pending Issues</span></div>
            <span class="db-delta">→</span>
          </a>
        </div>

        <div class="db-row db-row-2">
          <section class="db-card">
            <div class="db-head">
              <h2 class="db-title"><svg class="i c-teal"><use href="#i-chart"/></svg>Team &amp; Expert Coverage</h2>
              <a class="db-more" href="{{ route('capsa.reports.show', 'master') }}">View details →</a>
            </div>
            <div class="db-split">
              <div class="db-ring">
                <svg viewBox="0 0 104 104" role="img" aria-label="{{ $coverageRate }}% of teams have all three experts">
                  <circle cx="52" cy="52" r="44" fill="none" stroke="{{ $c['teams'] > 0 ? '#dc2626' : 'var(--border)' }}" stroke-width="14"/>
                  <circle cx="52" cy="52" r="44" fill="none" stroke="#16a34a" stroke-width="14" stroke-dasharray="{{ round($coverageRate / 100 * 276.5, 1) }} 276.5" transform="rotate(-90 52 52)"/>
                </svg>
                <div><b>{{ $coverageRate }}%</b>Coverage Rate</div>
              </div>
              <ul class="db-legend">
                <li><i style="background:var(--green)"></i><span>Fully Covered</span><b>{{ $c['covered'] }}</b></li>
                <li><i style="background:var(--red)"></i><span>Missing Experts</span><b>{{ $c['teams'] - $c['covered'] }}</b></li>
                @foreach ($c['coverage'] as $role)
                  <li class="sub"><span>{{ $role['label'] }}</span><b>{{ $role['have'] }} / {{ $c['teams'] }}</b></li>
                @endforeach
              </ul>
              @if ($capsa['status'])
                @php $short = $capsa['status'] === \App\Support\ExpertCapacity::SHORT; @endphp
                <div class="db-status {{ $short ? 'short' : ($capsa['status'] === \App\Support\ExpertCapacity::OK ? 'ok' : 'even') }}">
                  <svg class="i"><use href="#i-{{ $short ? 'warn' : 'check' }}"/></svg>Overall Status<strong>{{ $capsa['status'] }}</strong>
                  <em>{{ $short ? $capsa['shortage']['program'].' · '.$capsa['shortage']['who'] : implode(' · ', $viewer->handledPrograms()) }}</em>
                </div>
              @endif
            </div>
          </section>

          <section class="db-card" id="issues">
            <div class="db-head">
              <h2 class="db-title red"><svg class="i c-red"><use href="#i-warn"/></svg>Pending Issues</h2>
              <span class="db-meta db-grow" style="text-align:right">{{ $issueTotal }} Unresolved</span>
              <button type="button" class="db-more" onclick="document.getElementById('issuesModal').showModal()">View all →</button>
            </div>
            @if ($openIssues->isEmpty())
              <p class="db-empty">Nothing is waiting on you.</p>
            @else
              <ul class="db-list" style="--empty:{{ 4 - min(4, $openIssues->count()) }}">
                @foreach ($openIssues->take(4) as $issue)
                  <li><a class="rowlink" href="{{ $issue['href'] }}"><span class="db-ico db-issue-ico {{ $issue['tone'] }}"><svg class="i"><use href="#i-{{ $issue['icon'] }}"/></svg></span><div class="db-stack"><b class="db-clip">{{ $issue['label'] }}</b><span class="db-meta db-clip">{{ $issue['detail'] }}</span></div>@if ($issue['critical'] ?? false)<span class="db-pill crit">Critical</span>@endif<svg class="i db-chev"><use href="#i-chev"/></svg></a></li>
                @endforeach
              </ul>
            @endif
          </section>
        </div>

        <div class="db-row db-row-3">
          <section class="db-card">
            <div class="db-head">
              <h2 class="db-title"><svg class="i c-orange"><use href="#i-cal"/></svg>Upcoming Deadlines</h2>
              <a class="db-more" id="deadlines-more" href="{{ route('capsa.defenses') }}">View all →</a>
            </div>
            <div class="db-tabs" role="tablist">
              <button class="db-tab" role="tab" aria-selected="true" data-tab="defenses" data-more="{{ route('capsa.defenses') }}">Defenses</button>
              <button class="db-tab" role="tab" aria-selected="false" data-tab="requirements" data-more="{{ route('capsa.team_management', ['tab' => 'milestones']) }}">Requirements</button>
            </div>
            <ul class="db-list" data-panel="defenses" style="--empty:{{ 3 - max(1, count($capsa['defenses'])) }}">
              @forelse ($capsa['defenses'] as $def)
                <li><div class="db-stack"><span class="db-clip"><b>{{ $def->team?->team_name ?? 'A team' }}</b> · {{ $def->defense_type }}</span><time>{{ $def->schedule_date->format('M j') }}@if ($def->schedule_date->format('H:i') !== '00:00') · {{ $def->schedule_date->format('g:i A') }}@endif</time></div><span class="db-pill {{ $loop->first ? 'today' : '' }}">{{ $inDays($def->schedule_date) }}</span></li>
              @empty
                <li class="db-empty">No defenses scheduled.</li>
              @endforelse
            </ul>
            <ul class="db-list" data-panel="requirements" style="--empty:{{ 3 - max(1, count($capsa['requirements'])) }}" hidden>
              @forelse ($capsa['requirements'] as $req)
                <li><div class="db-stack"><span class="db-clip"><b>{{ $req['title'] }}</b> · {{ $req['left'] }} {{ \Illuminate\Support\Str::plural('team', $req['left']) }} left</span><time>{{ $req['due']->format('M j') }}</time></div><span class="db-pill {{ $loop->first ? 'today' : '' }}">{{ $inDays($req['due']) }}</span></li>
              @empty
                <li class="db-empty">No due dates coming up.</li>
              @endforelse
            </ul>
          </section>

          <section class="db-card">
            <div class="db-head">
              <h2 class="db-title"><svg class="i c-teal"><use href="#i-pulse"/></svg>Recent Activity</h2>
              <a class="db-more" href="{{ route('capsa.activitylog') }}">View all →</a>
            </div>
            <ul class="db-list" style="--empty:{{ 4 - max(1, count($capsa['activity'])) }}">
              @forelse ($capsa['activity'] as $log)
                @php [$icon, $tone] = $activityIcon[$log->type] ?? ['pulse', 't-blue']; @endphp
                <li><span class="db-ico {{ $tone }}"><svg class="i"><use href="#i-{{ $icon }}"/></svg></span><span class="db-clip db-grow">{{ strip_tags($log->description) }}</span><time>{{ $log->created_at->format('M j · g:i A') }}</time></li>
              @empty
                <li class="db-empty">No activity yet.</li>
              @endforelse
            </ul>
          </section>

          <section class="db-card db-qa-dark">
            <div class="db-head">
              <h2 class="db-title"><svg class="i c-orange"><use href="#i-bolt"/></svg>Quick Actions</h2>
            </div>
            <div class="db-qa-list">
              <a class="db-qa-row" href="{{ route('capsa.reports.show', 'master') }}"><svg class="i c-green"><use href="#i-shield"/></svg>Check Coverage<svg class="i db-chev"><use href="#i-chev"/></svg></a>
              <a class="db-qa-row" href="{{ route('capsa.team_management', ['tab' => 'milestones']) }}"><svg class="i c-orange"><use href="#i-cal"/></svg>View Milestones<svg class="i db-chev"><use href="#i-chev"/></svg></a>
              <a class="db-qa-row" href="{{ route('capsa.team_management', ['tab' => 'teams']) }}"><svg class="i c-purple"><use href="#i-team"/></svg>Manage Teams<svg class="i db-chev"><use href="#i-chev"/></svg></a>
              <a class="db-qa-row" href="{{ route('capsa.reports') }}"><svg class="i c-teal"><use href="#i-chart"/></svg>View Reports<svg class="i db-chev"><use href="#i-chev"/></svg></a>
            </div>
          </section>
        </div>

        <dialog id="issuesModal" class="db-dialog">
          <div class="db-head">
            <h2 class="db-title red"><svg class="i c-red"><use href="#i-warn"/></svg>Pending Issues</h2>
            <button type="button" class="db-more" onclick="this.closest('dialog').close()">Close</button>
          </div>
          <ul class="db-list">
            @foreach ($capsa['issues'] as $issue)
              <li><a class="rowlink" href="{{ $issue['href'] }}"><span class="db-ico db-issue-ico {{ $issue['tone'] }}"><svg class="i"><use href="#i-{{ $issue['icon'] }}"/></svg></span><div class="db-stack"><b class="db-clip">{{ $issue['label'] }}</b><span class="db-meta db-clip">{{ $issue['count'] ? $issue['detail'] : 'Nothing waiting' }}</span></div><svg class="i db-chev"><use href="#i-chev"/></svg></a></li>
            @endforeach
          </ul>
        </dialog>
      </main>
      <script nonce="{{ Vite::cspNonce() }}">
        document.querySelectorAll('.db-tab').forEach(function (tab) {
          tab.addEventListener('click', function () {
            tab.parentNode.querySelectorAll('.db-tab').forEach(function (t) { t.setAttribute('aria-selected', t === tab); });
            tab.closest('.db-card').querySelectorAll('[data-panel]').forEach(function (p) { p.hidden = p.dataset.panel !== tab.dataset.tab; });
            document.getElementById('deadlines-more').href = tab.dataset.more;
          });
        });
      </script>
    @endif

    @if ($admin && $stats['admin'])
      @php
        $a = $stats['admin'];
        $ad = $a['defenses'];
        $k = $ad['kpis'];
        $fee = $a['fees'];

        // Students per school year as bars. The axis has three steps, each a round
        // 1, 2 or 5 times a power of ten, the smallest whose top fits the tallest year.
        $series = $a['studentsByYear'];
        $years = count($series);
        $peak = max(1, (int) collect($series)->max('value'));
        $mag = 10 ** max(0, (int) floor(log10($peak / 3)));
        $step = collect([1, 2, 5, 10])->map(fn ($m) => $m * $mag)->first(fn ($s) => $s * 3 >= $peak);
        $activePercent = $a['students'] > 0 ? (int) round($a['active'] / $a['students'] * 100) : 0;

        $roleMax = collect($a['byRole'])->max('value') ?: 1;
        $roleColors = ['var(--purple)', 'var(--blue)', 'var(--green)', 'var(--accent)'];
        $yearMax = collect($a['archive']['years'])->max('value') ?: 1;

        $uncovered = count($admin['uncovered']);
        $many = fn (int $n, string $word) => $n.' '.\Illuminate\Support\Str::plural($word, $n);
        $chips = array_values(array_filter([
            ! $admin['schoolYear'] ? ['red', 'i-cal', 'School year', 'is not set', route('admin.settings')] : null,
            $uncovered ? ['red', 'i-team-solid', $many($uncovered, 'Section'), 'with no CaPSA', route('admin.experts')] : null,
            $admin['noSections'] ? ['orange', 'i-book-open', $many($admin['noSections'], 'Program'), 'with no sections', route('admin.settings')] : null,
            $a['archive']['missing'] ? ['purple', 'i-cloud', $many($a['archive']['missing'], 'Year'), 'with no uploads', route('admin.archive')] : null,
        ]));
      @endphp
      <main class="db db-admin">

        <div class="ad-tiles">
          <a class="db-tile tile-blue" href="{{ route('admin.students') }}">
            <span class="db-ico t-blue"><svg class="i"><use href="#i-cap"/></svg></span>
            <b>{{ $a['students'] }}</b><span class="ad-cap">Students</span><span class="ad-sub">With accounts</span>
          </a>
          <a class="db-tile tile-orange" href="{{ route('admin.analytics') }}">
            <span class="db-ico t-orange"><svg class="i"><use href="#i-team-solid"/></svg></span>
            <b>{{ $a['teams'] }}</b><span class="ad-cap">Teams</span><span class="ad-sub">{{ $a['verifiedTeams'] }} verified</span>
          </a>
          <a class="db-tile tile-purple" href="{{ route('admin.experts') }}">
            <span class="db-ico t-purple"><svg class="i"><use href="#i-hall"/></svg></span>
            <b>{{ $a['experts'] }}</b><span class="ad-cap">Experts</span><span class="ad-sub">Approved faculty</span>
          </a>
          <a class="db-tile tile-green" href="{{ route('admin.experts') }}">
            <span class="db-ico t-green"><svg class="i"><use href="#i-shield"/></svg></span>
            <b>{{ $admin['capsas'] }}</b><span class="ad-cap" style="text-transform:none">CAPSAs</span><span class="ad-sub">Running the subject</span>
          </a>
          <a class="db-tile tile-red" href="{{ route('admin.experts') }}">
            <span class="db-ico t-red"><svg class="i"><use href="#i-doc"/></svg></span>
            <b>{{ $uncovered }}</b><span class="ad-cap">Sections without<br>a CaPSA</span>
          </a>
          <a class="db-tile tile-gold" href="{{ route('admin.archive') }}">
            <span class="db-ico t-gold"><svg class="i"><use href="#i-box"/></svg></span>
            <b>{{ $a['archive']['total'] }}</b><span class="ad-cap">Archived Capstones</span><span class="ad-sub">Finished and published</span>
          </a>
        </div>

        <div class="ad-row-2">
          <section class="db-card">
            <div class="db-head">
              <h2 class="db-title"><svg class="i c-orange"><use href="#i-chart"/></svg>Students Overview</h2>
            </div>
            <div class="ad-over">
              <div class="ad-donut">
                <div class="ad-ring">
                  <svg viewBox="0 0 36 36"><circle cx="18" cy="18" r="15.5" fill="none" stroke="var(--border)" stroke-width="3.4"/><circle cx="18" cy="18" r="15.5" fill="none" stroke="var(--accent)" stroke-width="3.4" stroke-linecap="round" pathLength="100" stroke-dasharray="{{ $activePercent }} 100" transform="rotate(-90 18 18)"/></svg>
                  <div><b>{{ $activePercent }}%</b>Active<br>Students</div>
                </div>
                <div class="ad-legend">
                  <span><span class="ad-dot"></span>Active <b>{{ $a['active'] }}</b></span>
                  <span><span class="ad-dot" style="background:var(--border2)"></span>Inactive <b>{{ $a['inactive'] }}</b></span>
                </div>
              </div>
              @if ($years)
                <div class="ad-chart">
                  <div class="ad-axis"><span>{{ $step * 3 }}</span><span>{{ $step * 2 }}</span><span>{{ $step }}</span><span>0</span></div>
                  <div class="ad-plot">
                    @foreach ($series as $row)
                      <span class="ad-col"><b style="height:{{ round($row['value'] / ($step * 3) * 100, 2) }}%"><em>{{ $row['value'] }}</em></b></span>
                    @endforeach
                  </div>
                  <div class="ad-months">@foreach ($series as $row)<span>{{ $row['label'] }}</span>@endforeach</div>
                </div>
              @else
                <div class="ad-none">No students yet.</div>
              @endif
            </div>
          </section>

          <section class="db-card">
            <div class="db-head">
              <h2 class="db-title"><svg class="i c-purple"><use href="#i-team-solid"/></svg>Experts by Role</h2>
              <a class="db-btn-dark" href="{{ route('admin.experts') }}">View Details →</a>
            </div>
            <div class="ad-experts">
              <div class="ad-roles">
                @forelse ($a['byRole'] as $i => $row)
                  <div class="ad-role"><span>{{ $row['label'] }}</span><span class="ad-bar"><span style="width:{{ round($row['value'] / $roleMax * 100) }}%;background:{{ $roleColors[$i % 4] }}"></span></span><b>{{ $row['value'] }}</b></div>
                @empty
                  <div class="ad-none">No approved experts yet.</div>
                @endforelse
              </div>
              <div class="ad-people">
                <svg viewBox="0 0 120 76" aria-hidden="true">
                  <path d="M28 62a32 32 0 0 1 64 0Z" fill="#f3e3a6"/>
                  <circle cx="30" cy="34" r="9" fill="#4a5060"/><path d="M12 76c0-16 8-26 18-26s18 10 18 26Z" fill="#4a5060"/>
                  <circle cx="90" cy="34" r="9" fill="#4a5060"/><path d="M72 76c0-16 8-26 18-26s18 10 18 26Z" fill="#4a5060"/>
                  <circle cx="68" cy="26" r="11" fill="#18191f"/><path d="M44 76c0-22 10-36 24-36s24 14 24 36Z" fill="#18191f"/>
                  <circle cx="50" cy="40" r="9" fill="#c9a82c"/><path d="M32 76c0-14 8-24 18-24s18 10 18 24Z" fill="#c9a82c"/>
                </svg>
                Building a stronger academic community.
              </div>
            </div>
          </section>
        </div>

        <div class="ad-row-3">
          <section class="db-card">
            <div class="db-head">
              <h2 class="db-title"><svg class="i c-gold"><use href="#i-shield"/></svg>Defense Results</h2>
              <a class="db-btn-dark" href="{{ route('admin.analytics') }}">View Details →</a>
            </div>
            <div class="ad-sub-line">SY. {{ str_replace('-', '–', $ad['year']) }} • {{ $many($k['teams'], 'team') }}, {{ $k['rate'] === null ? 'nothing decided yet' : $k['rate'].'% pass rate overall' }}</div>
            <div class="ad-def">
              <div class="ad-ring small">
                <svg viewBox="0 0 36 36"><circle cx="18" cy="18" r="15.5" fill="none" stroke="var(--border)" stroke-width="3.4"/><circle cx="18" cy="18" r="15.5" fill="none" stroke="var(--green)" stroke-width="3.4" pathLength="100" stroke-dasharray="{{ $k['rate'] ?? 0 }} 100" transform="rotate(-90 18 18)"/></svg>
                <div><b>{{ $k['rate'] === null ? '—' : $k['rate'].'%' }}</b>Pass Rate</div>
              </div>
              <div class="ad-kv">
                <div><span><span class="ad-dot" style="background:var(--green)"></span>Passed</span><b>{{ $k['passed'] }}</b></div>
                <div><span><span class="ad-dot" style="background:var(--border2)"></span>Not Scheduled</span><b>{{ $ad['unscheduled'] }}</b></div>
              </div>
              <div class="ad-title-res">
                <h3>Title Results</h3>
                <div class="ad-none">{{ $k['title_rate'] === null ? 'No result yet' : $k['title_rate'].'% pass' }}<br>({{ $many($k['title_teams'], 'team') }})</div>
              </div>
            </div>
          </section>

          <section class="db-card">
            <div class="db-head">
              <h2 class="db-title"><svg class="i c-gold"><use href="#i-coins"/></svg>Fees and Archive</h2>
              <a class="db-btn-dark" href="{{ route('admin.fees') }}">View Details →</a>
            </div>
            <div class="ad-money">
              <div>
                <div><span class="ad-amount">₱{{ number_format($fee['received'], 2) }}</span><span class="ad-note">Fees Received<br>from CaPSAs</span></div>
              </div>
              <div class="ad-receipts">
                <div class="ad-receipt"><span class="db-ico t-green"><svg class="i" style="fill:none"><use href="#i-check"/></svg></span><span>Sections Sent<b>{{ $fee['sent'] }}</b></span></div>
                <div class="ad-receipt"><span class="db-ico t-orange"><svg class="i" style="fill:none"><use href="#i-clock"/></svg></span><span>Sections Not Sent Yet<b>{{ $fee['notSent'] }}</b></span></div>
              </div>
              <div class="ad-years">
                <h3>Archived Capstones by Year</h3>
                @forelse ($a['archive']['years'] as $row)
                  <div class="ad-year-row"><span>{{ $row['label'] }}</span><span class="ad-bar"><span style="width:{{ round($row['value'] / $yearMax * 100) }}%"></span></span><b>{{ $row['value'] }}</b></div>
                @empty
                  <div class="ad-none">Nothing archived yet.</div>
                @endforelse
              </div>
            </div>
          </section>
        </div>

        @if ($chips)
          <section class="ad-attn">
            <div class="ad-attn-main">
              <svg class="i"><use href="#i-warn-dark"/></svg>
              <div><b>Needs Attention</b><span>There are pending items that require your attention.</span></div>
            </div>
            <div class="ad-chips">
              @foreach ($chips as [$tone, $icon, $count, $what, $href])
                <a class="ad-chip {{ $tone }}" href="{{ $href }}"><svg class="i" @style(['fill:currentColor' => $icon === 'i-team-solid'])><use href="#{{ $icon }}"/></svg><div><b>{{ $count }}</b><span>{{ $what }}</span></div></a>
              @endforeach
            </div>
            <a class="db-btn-dark" href="{{ $chips[0][4] }}">View All →</a>
          </section>
        @endif

      </main>
    @endif
  </div>
  </div>
</div>

@include('capsa.partials.skeleton-toggle')

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<script nonce="{{ Vite::cspNonce() }}">
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() { sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar(); }
function openSidebar() { sidebarEl.classList.add('mobile-open'); sidebarOverlay.classList.add('open'); }
function closeSidebar() { sidebarEl.classList.remove('mobile-open'); sidebarOverlay.classList.remove('open'); }
</script>

@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>
