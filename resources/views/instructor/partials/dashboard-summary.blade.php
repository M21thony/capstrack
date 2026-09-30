{{-- The expert's own numbers, above what is waiting on them: the teams they
     advise and in which role, how full their slots are, how their reviews and
     consultations stand, and their defense panels. Figures: DashboardStats::expert(). --}}
@php
  $teamWord = fn ($n) => \Illuminate\Support\Str::plural('team', $n);
  $slotsUsed = $stats['slots']['used'];
  $slotsMax = $stats['slots']['max'];
  $slotPercent = $slotsMax > 0 ? min(100, (int) round($slotsUsed / $slotsMax * 100)) : null;
  $reviews = $stats['reviews'];
  $consults = $stats['consultations'];
  $panels = $stats['panels'];
@endphp
<p class="dc-lead"><b>Your work at a glance</b></p>
<div class="dc-tiles">
  <x-dashboard.stat-chip value="{{ $stats['teams'] }}" label="Teams You Advise" icon="team"
    sub="{{ $slotsMax > 0 ? $stats['slots']['left'].' of '.$slotsMax.' slots open' : 'Team limit not set' }}" color="var(--accent3)" />
  <x-dashboard.stat-chip value="{{ $reviews['percent'] === null ? '—' : $reviews['percent'].'%' }}" label="Documents Reviewed" icon="file-review"
    sub="{{ $reviews['reviewed'] }} of {{ $reviews['total'] }} sent to you" color="var(--green)" />
  <x-dashboard.stat-chip value="{{ $consults['done'] }}" label="Consultations Held" icon="consultation"
    sub="{{ $consults['upcoming'] }} upcoming" color="var(--blue)" />
  <x-dashboard.stat-chip value="{{ $panels['graded'] }}/{{ $panels['held'] }}" label="Defenses Graded" icon="grading"
    sub="{{ $panels['upcoming'] }} more coming up" color="var(--purple)" />
</div>

<div class="dc-grid wide">
  <div class="card">
    <div class="card-header"><span class="card-title">Your Teams</span></div>
    <p class="dc-sub">A team counts under each role you hold on it.</p>
    <x-dashboard.bars :rows="$stats['byRole']" color="var(--accent)" />
    @if ($slotPercent !== null)
      <div style="margin-top:18px">
        <x-dashboard.ring :percent="$slotPercent" title="{{ $slotsUsed }} of {{ $slotsMax }} team slots used" sub="{{ $stats['slots']['left'] }} open for new requests" color="var(--accent)" />
      </div>
    @endif
    @if (count($stats['bySection']) > 0)
      <h4 class="dc-h" style="margin-top:18px">Teams per section</h4>
      <x-dashboard.bars :rows="$stats['bySection']" color="var(--blue)" />
    @endif
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title">Reviews, Consultations and Panels</span></div>
    <div class="dc-rows">
      <div class="dc-row">
        <div class="dc-row-head"><b>Document reviews</b><span>{{ $reviews['total'] }} {{ \Illuminate\Support\Str::plural('document', $reviews['total']) }}</span></div>
        <x-dashboard.stack :parts="[
          ['label' => 'Reviewed', 'value' => $reviews['reviewed'], 'tone' => 'ok'],
          ['label' => 'Still with you', 'value' => $reviews['waiting'], 'tone' => 'warn'],
        ]" />
      </div>
      <div class="dc-row">
        <div class="dc-row-head"><b>Consultations</b><span>{{ $consults['total'] }} booked in all</span></div>
        <x-dashboard.stack :parts="[
          ['label' => 'Held', 'value' => $consults['done'], 'tone' => 'ok'],
          ['label' => 'Upcoming', 'value' => $consults['upcoming'], 'tone' => 'info'],
          ['label' => 'Cancelled', 'value' => $consults['cancelled'], 'tone' => 'none'],
        ]" />
      </div>
      <div class="dc-row">
        <div class="dc-row-head"><b>Defense panels</b><span>{{ $panels['total'] }} {{ \Illuminate\Support\Str::plural('defense', $panels['total']) }} you sit on</span></div>
        <x-dashboard.stack :parts="[
          ['label' => 'Graded', 'value' => $panels['graded'], 'tone' => 'ok'],
          ['label' => 'To grade', 'value' => $panels['toGrade'], 'tone' => 'warn'],
          ['label' => 'Coming up', 'value' => $panels['upcoming'], 'tone' => 'info'],
        ]" />
      </div>
    </div>
  </div>
</div>

<p class="dc-lead" style="margin-top:6px"><b>Waiting on you</b></p>
