<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Dashboard - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
<x-dashboard.styles />
@include('partials.skeleton-head', ['skReveal' => 'flex'])
  @include('partials.render-whole')
</head>
<body>

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
  <div class="content">
    <style nonce="{{ Vite::cspNonce() }}">
      .dash-lead{font-size:13.5px;color:var(--text2);margin:0 0 12px}
      .dash-lead b{color:var(--text)}
      .dash-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:16px}
      .dash-tiles .stat-chip.quiet .stat-n{color:var(--text3)!important}
      /* Two across on a phone, as on the expert dashboard: one a row, the four
         of them took the whole first screen. */
      @media (max-width:480px){.dash-tiles{grid-template-columns:1fr 1fr;gap:10px}}
      .dash-facts{display:flex;flex-direction:column;gap:8px}
      .dash-fact{display:flex;justify-content:space-between;gap:12px;padding:10px 12px;border-radius:10px;background:var(--bg3);font-size:13.5px}
      .dash-fact b{font-variant-numeric:tabular-nums}
      .dash-fact.bad b{color:var(--red)}
      .dash-link{display:inline-block;margin-top:12px;font-size:12.5px;font-weight:600;color:var(--accent3);text-decoration:none}
      .dash-link:hover{text-decoration:underline}
      .dash-def{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-top:1px solid var(--border);font-size:13.5px}
      .dash-def:first-child{border-top:0;padding-top:0}
      .dash-def small{display:block;color:var(--text3);font-size:12px}
      .dash-sec{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
      .dash-sec span{padding:3px 9px;border-radius:999px;background:var(--red-bg);color:var(--red);font-size:12px;font-weight:600}
    </style>

    @if ($capsa && $stats['capsa'])
      @php
        $c = $stats['capsa'];
        $d = $c['defenses'];
        $p = $c['progress'];
        $onTrack = $p['statuses'][0]['value'];
        $cover = collect($c['coverage']);
        $tile = fn ($n) => $n ? '' : 'quiet';
      @endphp
      <p class="dc-lead"><b>Your sections at a glance</b> · {{ $capsa['period'] }}</p>
      <div class="dash-tiles">
        <x-dashboard.stat-chip :href="route('capsa.team_management')" value="{{ $c['teams'] }}" label="Teams" icon="team" sub="{{ $c['verified'] }} verified · {{ $c['sections'] }} {{ \Illuminate\Support\Str::plural('section', $c['sections']) }}" color="var(--accent3)" />
        <x-dashboard.stat-chip :href="route('capsa.sections')" value="{{ $c['students'] }}" label="Students" icon="student" sub="In your teams" color="var(--blue)" />
        <x-dashboard.stat-chip :href="route('capsa.reports.show', 'progress')" value="{{ $p['percent'] === null ? '—' : $p['percent'].'%' }}" label="Requirements Done" icon="progress" sub="{{ $p['done'] }} of {{ $p['total'] }} checklist items" color="var(--green)" />
        <x-dashboard.stat-chip :href="route('capsa.analytics')" value="{{ $d['kpis']['rate'] === null ? '—' : $d['kpis']['rate'].'%' }}" label="Defense Pass Rate" icon="defense" sub="{{ $d['kpis']['passed'] }} passed of {{ $d['kpis']['decided'] }} decided" color="var(--purple)" />
      </div>

      <div class="dc-grid wide">
        <div class="card">
          <div class="card-header"><span class="card-title">Team Progress</span></div>
          <p class="dc-sub">Where the checklist stands for the teams in your sections.</p>
          <x-dashboard.ring :percent="$p['percent']" title="{{ $p['done'] }} of {{ $p['total'] }} requirements done" sub="{{ $onTrack }} of {{ $c['teams'] }} {{ \Illuminate\Support\Str::plural('team', $c['teams']) }} on track" color="var(--green)" />
          <div style="margin-top:16px"><x-dashboard.stack :parts="$p['statuses']" /></div>
          @if ($p['milestones'])
            <h4 class="dc-h" style="margin-top:18px">Teams at each milestone</h4>
            <x-dashboard.bars :rows="$p['milestones']" color="var(--accent)" />
          @endif
          <a class="dc-more" href="{{ route('capsa.reports.show', 'progress') }}">See Team Progress →</a>
        </div>

        <div class="card">
          <div class="card-header"><span class="card-title">Defense Results</span></div>
          <p class="dc-sub">S.Y. {{ $d['year'] }} · {{ $d['kpis']['teams'] }} {{ \Illuminate\Support\Str::plural('team', $d['kpis']['teams']) }}. The pass rate counts only teams that have been decided.</p>
          <div class="dc-rows">
            @foreach ($d['stages'] as $stage)
              <div class="dc-row">
                <div class="dc-row-head"><b>{{ $stage['stage'] }}</b><span>{{ $stage['rate'] === null ? 'No result yet' : $stage['rate'].'% pass' }} · {{ $stage['teams'] }} {{ \Illuminate\Support\Str::plural('team', $stage['teams']) }}</span></div>
                <x-dashboard.stack :parts="$stage['parts']" :hide-zero="true" />
              </div>
            @endforeach
          </div>
          <a class="dc-more" href="{{ route('capsa.analytics') }}">Open Analytics →</a>
        </div>
      </div>

      <div class="dc-grid wide">
        <div class="card">
          <div class="card-header"><span class="card-title">Fee Collection</span></div>
          <p class="dc-sub">Verified teams in your sections, by defense fee.</p>
          <div class="dc-rows">
            @foreach ($c['fees'] as $fee)
              <div class="dc-row">
                <div class="dc-row-head"><b>{{ $fee['stage'] }}</b><span>{{ $fee['posted'] ? $fee['collected'].' collected' : 'Fee not posted yet' }}</span></div>
                @if ($fee['posted'] && $fee['teams'])
                  <x-dashboard.stack :parts="$fee['parts']" :hide-zero="true" />
                @else
                  <div class="dc-empty" style="padding:6px 0">{{ $fee['posted'] ? 'No verified teams yet.' : 'Post the fee on Financial Records.' }}</div>
                @endif
              </div>
            @endforeach
          </div>
          <a class="dc-more" href="{{ route('capsa.financial') }}">Open Financial Records →</a>
        </div>

        <div class="card">
          <div class="card-header"><span class="card-title">Experts and Sections</span></div>
          <p class="dc-sub">How many of your {{ $c['teams'] }} {{ \Illuminate\Support\Str::plural('team', $c['teams']) }} have each expert.</p>
          <x-dashboard.bars :rows="$cover->map(fn ($r) => ['label' => $r['label'], 'value' => $r['have']])->all()" color="var(--blue)" />
          <h4 class="dc-h" style="margin-top:18px">Teams per section</h4>
          <x-dashboard.bars :rows="$c['bySection']" color="var(--accent)" empty="No teams in your sections yet." />
        </div>
      </div>

      <p class="dc-lead" style="margin-top:6px"><b>Waiting on you</b></p>
      <div class="dash-tiles">
        <x-dashboard.stat-chip :href="route('capsa.team_management')" class="{{ $tile($capsa['toVerify']) }}" value="{{ $capsa['toVerify'] }}" label="Teams to Verify" icon="verified" sub="{{ $capsa['toVerify'] ? 'Complete, waiting for you' : 'Nothing waiting' }}" color="var(--amber)" />
        <x-dashboard.stat-chip :href="route('capsa.team_management')" class="{{ $tile($capsa['titles']) }}" value="{{ $capsa['titles'] }}" label="Titles to Review" icon="file-check" sub="{{ $capsa['titles'] ? 'Turned in by teams' : 'Nothing waiting' }}" color="var(--accent3)" />
        <x-dashboard.stat-chip :href="route('capsa.financial')" class="{{ $tile($capsa['receipts']) }}" value="{{ $capsa['receipts'] }}" label="Receipts to Check" icon="receipt" sub="{{ $capsa['receipts'] ? 'Sent in by team leaders' : 'Nothing waiting' }}" color="var(--green)" />
        <x-dashboard.stat-chip :href="route('capsa.team_management')" class="{{ $tile($capsa['leaveRequests']) }}" value="{{ $capsa['leaveRequests'] }}" label="Member Requests" icon="user-clock" sub="{{ $capsa['leaveRequests'] ? 'Asking to leave or remove' : 'Nothing waiting' }}" color="var(--red)" />
      </div>

      <x-dashboard.info-card title="Defenses in the Next Two Weeks" :empty="$capsa['defenses']->isEmpty()" emptyMessage="No defenses in the next two weeks." emptyIcon="defense" style="margin-bottom:16px">
        @foreach ($capsa['defenses'] as $def)
          <div class="dash-def">
            <span><b>{{ $def->team?->team_name ?? 'A team' }}</b><small>{{ $def->defense_type }}{{ $def->venue ? ' · '.$def->venue : '' }}</small></span>
            <span style="text-align:right;white-space:nowrap">{{ $def->schedule_date->format('D, M j') }}@if ($def->schedule_date->format('H:i') !== '00:00')<small>{{ $def->schedule_date->format('g:i A') }}</small>@endif</span>
          </div>
        @endforeach
      </x-dashboard.info-card>
    @endif

    @if ($admin && $stats['admin'])
      @php
        $a = $stats['admin'];
        $ad = $a['defenses'];
        $fee = $a['fees'];
      @endphp
      <p class="dc-lead"><b>The whole system</b> · every section, every program</p>
      <div class="dash-tiles">
        <x-dashboard.stat-chip :href="route('admin.students')" value="{{ $a['students'] }}" label="Students" icon="student" sub="With accounts" color="var(--blue)" />
        <x-dashboard.stat-chip value="{{ $a['teams'] }}" label="Teams" icon="team" sub="{{ $a['verifiedTeams'] }} verified" color="var(--accent3)" />
        <x-dashboard.stat-chip :href="route('admin.experts')" value="{{ $a['experts'] }}" label="Experts" icon="expert" sub="{{ $a['awaitingApproval'] ? $a['awaitingApproval'].' waiting for approval' : 'Approved faculty' }}" color="var(--purple)" />
        <x-dashboard.stat-chip :href="route('admin.experts')" value="{{ $admin['capsas'] }}" label="CaPSAs" icon="expert" sub="Running the subject" color="var(--green)" />
        <x-dashboard.stat-chip :href="route('admin.experts')" value="{{ count($admin['uncovered']) }}" label="Sections Without a CaPSA" icon="section-list" sub="{{ count($admin['uncovered']) ? 'Nobody verifies these teams' : 'Every section is covered' }}" color="{{ count($admin['uncovered']) ? 'var(--red)' : 'var(--green)' }}" />
        <x-dashboard.stat-chip :href="route('capsa.archive')" value="{{ $a['archive']['total'] }}" label="Archived Capstones" icon="archive" sub="Finished and published" color="var(--amber)" />
      </div>

      <div class="dc-grid wide">
        <div class="card">
          <div class="card-header"><span class="card-title">Students by Program</span></div>
          <x-dashboard.bars :rows="$a['byProgram']" color="var(--blue)" empty="No students yet." />
        </div>
        <div class="card">
          <div class="card-header"><span class="card-title">Experts by Role</span></div>
          <p class="dc-sub">An expert with two roles counts under each.</p>
          <x-dashboard.bars :rows="$a['byRole']" color="var(--purple)" empty="No approved experts yet." />
        </div>
      </div>

      <div class="dc-grid wide">
        <div class="card">
          <div class="card-header"><span class="card-title">Defense Results</span></div>
          <p class="dc-sub">S.Y. {{ $ad['year'] }} · {{ $ad['kpis']['teams'] }} {{ \Illuminate\Support\Str::plural('team', $ad['kpis']['teams']) }}, {{ $ad['kpis']['rate'] === null ? 'nothing decided yet' : $ad['kpis']['rate'].'% pass rate overall' }}.</p>
          <div class="dc-rows">
            @foreach ($ad['stages'] as $stage)
              <div class="dc-row">
                <div class="dc-row-head"><b>{{ $stage['stage'] }}</b><span>{{ $stage['rate'] === null ? 'No result yet' : $stage['rate'].'% pass' }} · {{ $stage['teams'] }} {{ \Illuminate\Support\Str::plural('team', $stage['teams']) }}</span></div>
                <x-dashboard.stack :parts="$stage['parts']" :hide-zero="true" />
              </div>
            @endforeach
          </div>
          <a class="dc-more" href="{{ route('capsa.analytics') }}">Open Analytics →</a>
        </div>
        <div class="card">
          <div class="card-header"><span class="card-title">Money and Archive</span></div>
          <div class="dash-facts">
            <div class="dash-fact"><span>Fees collected (verified receipts)</span><b>₱{{ number_format($fee['collected'], 2) }}</b></div>
            <div class="dash-fact"><span>Receipts verified</span><b>{{ $fee['verifiedCount'] }}</b></div>
            <div class="dash-fact"><span>Receipts waiting on a CaPSA</span><b>{{ $fee['waiting'] }}</b></div>
            <div class="dash-fact"><span>Receipts still with team leaders</span><b>{{ $fee['heldByLeaders'] }}</b></div>
          </div>
          <h4 class="dc-h" style="margin-top:18px">Archived capstones by year</h4>
          <x-dashboard.bars :rows="$a['archive']['years']" color="var(--amber)" empty="Nothing archived yet." />
        </div>
      </div>

      @if ($admin['uncovered'] || ! $admin['schoolYear'] || $a['awaitingApproval'])
        <div class="card" style="margin-bottom:16px">
          <div class="card-header"><span class="card-title">Needs Attention</span></div>
          <div class="dash-facts">
            @if (! $admin['schoolYear'])
              <div class="dash-fact bad"><span>The current school year is not set. Uploads and reports are stamped with it.</span><a class="dash-link" style="margin:0" href="{{ route('admin.settings') }}">Set it →</a></div>
            @endif
            @if ($a['awaitingApproval'])
              <div class="dash-fact"><span>{{ $a['awaitingApproval'] }} {{ \Illuminate\Support\Str::plural('expert', $a['awaitingApproval']) }} waiting to be approved.</span><a class="dash-link" style="margin:0" href="{{ route('admin.experts') }}">Review →</a></div>
            @endif
            @if ($admin['uncovered'])
              <div class="dash-fact bad" style="display:block">
                <span>{{ count($admin['uncovered']) }} {{ \Illuminate\Support\Str::plural('section', count($admin['uncovered'])) }} with no CaPSA. Their teams cannot be verified until someone covers them.</span>
                <div class="dash-sec">@foreach (array_slice($admin['uncovered'], 0, 24) as $section)<span>{{ $section }}</span>@endforeach @if (count($admin['uncovered']) > 24)<span>+{{ count($admin['uncovered']) - 24 }} more</span>@endif</div>
                <a class="dash-link" href="{{ route('admin.experts') }}">Make or update a CaPSA →</a>
              </div>
            @endif
          </div>
        </div>
      @endif
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
</body>
</html>
