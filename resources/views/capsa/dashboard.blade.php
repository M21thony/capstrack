<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Dashboard - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<x-dashboard.styles />
@include('partials.skeleton-head', ['skReveal' => 'flex'])
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
    <style>
      .dash-lead{font-size:13.5px;color:var(--text2);margin:0 0 12px}
      .dash-lead b{color:var(--text)}
      .dash-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:16px}
      .dash-tiles .stat-chip.quiet .stat-n{color:var(--text3)!important}
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

    @if ($capsa)
      @php
        $tile = fn ($n) => $n ? '' : 'quiet';
      @endphp
      <p class="dash-lead"><b>Waiting on you</b> · {{ $capsa['period'] }} · {{ $capsa['teamCount'] }} {{ \Illuminate\Support\Str::plural('team', $capsa['teamCount']) }} in your {{ $capsa['sectionCount'] }} {{ \Illuminate\Support\Str::plural('section', $capsa['sectionCount']) }}</p>
      <div class="dash-tiles">
        <x-dashboard.stat-chip :href="route('capsa.team_management')" class="{{ $tile($capsa['toVerify']) }}" value="{{ $capsa['toVerify'] }}" label="Teams to Verify" sub="{{ $capsa['toVerify'] ? 'Complete, waiting for you' : 'Nothing waiting' }}" color="var(--amber)" />
        <x-dashboard.stat-chip :href="route('capsa.team_management')" class="{{ $tile($capsa['titles']) }}" value="{{ $capsa['titles'] }}" label="Titles to Review" sub="{{ $capsa['titles'] ? 'Turned in by teams' : 'Nothing waiting' }}" color="var(--accent3)" />
        <x-dashboard.stat-chip :href="route('capsa.financial')" class="{{ $tile($capsa['receipts']) }}" value="{{ $capsa['receipts'] }}" label="Receipts to Check" sub="{{ $capsa['receipts'] ? 'Uploaded by students' : 'Nothing waiting' }}" color="var(--green)" />
        <x-dashboard.stat-chip :href="route('capsa.team_management')" class="{{ $tile($capsa['leaveRequests']) }}" value="{{ $capsa['leaveRequests'] }}" label="Member Requests" sub="{{ $capsa['leaveRequests'] ? 'Asking to leave or remove' : 'Nothing waiting' }}" color="var(--red)" />
      </div>

      <div class="grid-2" style="margin-bottom:16px">
        <div class="card">
          <div class="card-header"><span class="card-title">Your Teams</span></div>
          <div class="dash-facts">
            <div class="dash-fact {{ $capsa['behind'] ? 'bad' : '' }}"><span>With overdue checklist items</span><b>{{ $capsa['behind'] }}</b></div>
            <div class="dash-fact"><span>Not verified yet</span><b>{{ $capsa['unverified'] }}</b></div>
            <div class="dash-fact"><span>Teams in your sections</span><b>{{ $capsa['teamCount'] }}</b></div>
          </div>
          <a class="dash-link" href="{{ route('capsa.reports.show', 'progress') }}">See Team Progress →</a>
        </div>
        <x-dashboard.info-card title="Defenses in the Next Two Weeks" :empty="$capsa['defenses']->isEmpty()" emptyMessage="No defenses in the next two weeks.">
          @foreach ($capsa['defenses'] as $d)
            <div class="dash-def">
              <span><b>{{ $d->team?->team_name ?? 'A team' }}</b><small>{{ $d->defense_type }}{{ $d->venue ? ' · '.$d->venue : '' }}</small></span>
              <span style="text-align:right;white-space:nowrap">{{ $d->schedule_date->format('D, M j') }}@if ($d->schedule_date->format('H:i') !== '00:00')<small>{{ $d->schedule_date->format('g:i A') }}</small>@endif</span>
            </div>
          @endforeach
        </x-dashboard.info-card>
      </div>
    @endif

    @if ($admin)
      <p class="dash-lead"><b>System</b> · what the department still needs set up</p>
      <div class="dash-tiles">
        <x-dashboard.stat-chip :href="route('admin.experts')" value="{{ count($admin['uncovered']) }}" label="Sections Without a CaPSA" sub="{{ count($admin['uncovered']) ? 'Nobody verifies these teams' : 'Every section is covered' }}" color="{{ count($admin['uncovered']) ? 'var(--red)' : 'var(--green)' }}" />
        <x-dashboard.stat-chip :href="route('admin.experts')" value="{{ $admin['capsas'] }}" label="CaPSAs" sub="Running the subject" color="var(--accent3)" />
        <x-dashboard.stat-chip :href="route('admin.students')" value="{{ $admin['students'] }}" label="Students" sub="With accounts" color="var(--blue)" />
        <x-dashboard.stat-chip value="{{ $admin['experts'] }}" label="Experts" sub="Approved faculty" color="var(--purple)" />
      </div>
      @if ($admin['uncovered'] || ! $admin['schoolYear'])
        <div class="card" style="margin-bottom:16px">
          <div class="card-header"><span class="card-title">Needs Attention</span></div>
          <div class="dash-facts">
            @if (! $admin['schoolYear'])
              <div class="dash-fact bad"><span>The current school year is not set. Uploads and reports are stamped with it.</span><a class="dash-link" style="margin:0" href="{{ route('admin.settings') }}">Set it →</a></div>
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

<script>
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() { sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar(); }
function openSidebar() { sidebarEl.classList.add('mobile-open'); sidebarOverlay.classList.add('open'); }
function closeSidebar() { sidebarEl.classList.remove('mobile-open'); sidebarOverlay.classList.remove('open'); }
</script>

@include('partials.escape-closes-modals')
</body>
</html>
