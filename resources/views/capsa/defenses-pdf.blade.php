{{--
  The defense schedule as a PDF: what Export on Defenses shows first, before
  Download PDF or Download Excel. The same rows and order as the Excel file
  (App\Support\DefenseScheduleWorkbook): a band per day, then the teams still
  waiting for a date, each team with its members, experts and panel.

  $rows from DefenseScheduleExport::rows(), $stage, $scope, $window, $by.
--}}
@php
  use App\Support\DefenseScheduleExport;
  use Carbon\Carbon;

  $paid = DefenseScheduleExport::isPaidStage($stage);
  $groups = collect($rows)->groupBy(fn (array $team) => $team['schedule'] !== null ? $team['schedule']['date']->toDateString() : 'waiting');
  $count = fn (callable $test) => count(array_filter($rows, $test));
  $returnees = array_sum(array_map(fn ($r) => collect($r['members'])->where('returnee', true)->count(), $rows));
  $bits = array_values(array_filter([
      count($rows).' '.(count($rows) === 1 ? 'team' : 'teams'),
      $count(fn ($r) => $r['schedule'] !== null).' scheduled',
      $count(fn ($r) => $r['status']['key'] === 'cancelled').' cancelled',
      $count(fn ($r) => $r['status']['key'] === 'redefense').' waiting to redefend',
      $count(fn ($r) => $r['status']['key'] === 'not_scheduled').' not scheduled',
      $returnees.' '.($returnees === 1 ? 'returnee' : 'returnees'),
  ], fn (string $bit, int $i) => $i < 2 || ! str_starts_with($bit, '0 '), ARRAY_FILTER_USE_BOTH));
  $windowText = $window && ($window['start'] ?? null) && ($window['end'] ?? null)
      ? 'Window '.Carbon::parse($window['start'])->format('M j').' – '.Carbon::parse($window['end'])->format('M j, Y')
      : null;
  $tone = ['pass' => 'pass', 'major' => 'major', 'fail' => 'fail', 'ongoing' => 'ongoing'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ $stage }} Schedule</title>
<style>
  @page { margin: 32px 32px 44px; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: #111; }
  @include('capsa.reports._letterhead', ['part' => 'style'])
  .title { text-align: center; }
  .title .name { font-size: 13px; font-weight: bold; letter-spacing: .06em; text-transform: uppercase; }
  .title .scope { font-size: 9px; margin-top: 2px; }
  .title .counts { font-size: 8.5px; margin-top: 2px; font-weight: bold; }
  table.sched { width: 100%; border-collapse: collapse; margin-top: 10px; }
  .sched th, .sched td { border: 1px solid #111; padding: 2px 4px; vertical-align: top; text-align: left; }
  .sched thead th { background: #1f2028; color: #fff; font-size: 7.5px; text-transform: uppercase; letter-spacing: .03em; }
  .sched tr.day td { background: #faf6e8; color: #7d630e; font-weight: bold; font-size: 8.5px; letter-spacing: .04em; padding: 4px; }
  .sched tr.waiting td { background: #f1f1f4; color: #4b4d57; }
  /* A team is never split across pages, and a day's band stays with its first team. */
  .sched tbody tr { page-break-inside: avoid; }
  .sched tr.day { page-break-after: avoid; }
  .sched td.sec { font-weight: bold; }
  .sched td.when { font-weight: bold; white-space: nowrap; }
  .sched td.when.none, .muted { font-weight: normal; font-style: italic; color: #7a7c86; }
  .sched td.alias { font-weight: bold; }
  .sched td.title-cell { color: #4b4d57; }
  .sched td.r { text-align: right; }
  .st { font-weight: bold; font-size: 7px; }
  .st.pass { color: #1e7a3c; } .st.major { color: #92400e; } .st.fail { color: #b42318; } .st.ongoing { color: #1d4ed8; }
  .k { font-size: 6.5px; font-weight: bold; color: #7a7c86; text-transform: uppercase; }
  .none-yet { font-style: italic; color: #b42318; }
  .paid { font-weight: bold; font-size: 6.5px; color: #1e7a3c; } .unpaid { font-weight: bold; font-size: 6.5px; color: #b42318; }
  .ret { font-weight: bold; font-size: 6.5px; color: #6d28d9; }
  .line { line-height: 1.45; }
  .foot-note { margin-top: 6px; font-size: 7px; font-style: italic; color: #555; }
  .empty { text-align: center; padding: 18px; color: #555; }
  .footer { position: fixed; bottom: -28px; left: 0; right: 0; font-size: 7.5px; color: #555; border-top: 1px solid #bbb; padding-top: 3px; }
</style>
</head>
<body>
  <div class="footer">{{ $stage }} Schedule · {{ $scope }} · generated from CapsTrack on {{ now()->format('F j, Y') }} by {{ $by }}</div>

  @include('capsa.reports._letterhead')
  <div class="title">
    <div class="name">{{ $stage }} Schedule</div>
    <div class="scope">Capstone Project · {{ $scope }}@if ($windowText) · {{ $windowText }}@endif · as of {{ now()->format('F j, Y, g:i A') }}</div>
    <div class="counts">{{ implode('  ·  ', $bits) }}</div>
  </div>

  <table class="sched">
    <thead>
      <tr>
        <th>Section</th><th>Schedule</th><th>Venue</th><th>Status</th>
        <th>Members{{ $paid ? ' · '.$stage.' payment' : '' }}</th>
        <th>Team alias</th><th>Title</th><th>Experts</th><th>Panel</th><th>Secretary</th><th>Similarity</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($groups as $day => $teams)
        <tr class="day {{ $day === 'waiting' ? 'waiting' : '' }}">
          <td colspan="11">
            @if ($day === 'waiting')
              WAITING FOR A DATE · {{ $teams->count() }} {{ $teams->count() === 1 ? 'team' : 'teams' }}
            @else
              {{ mb_strtoupper($teams->first()['schedule']['date']->format('l, F j, Y')) }} · {{ $teams->count() }} {{ $teams->count() === 1 ? 'defense' : 'defenses' }}
            @endif
          </td>
        </tr>
        @foreach ($teams as $team)
          <tr>
            <td class="sec">{{ $team['section'] }}</td>
            @if ($team['schedule'])
              <td class="when">{{ $team['schedule']['date']->format('g:i A') }} – {{ $team['schedule']['end']->format('g:i A') }}</td>
            @else
              <td class="when none">Not yet scheduled @if ($team['note'])<br>{{ $team['note'] }}@endif</td>
            @endif
            <td>{{ $team['venue'] ?? '—' }}</td>
            <td><span class="st {{ $tone[$team['status']['tone']] ?? '' }}">{{ $team['status']['label'] }}</span></td>
            <td class="line">
              @forelse ($team['members'] as $member)
                <div>{{ $member['name'] }}@if (! empty($member['returnee'])) <span class="ret">(Returnee)</span>@endif
                  @if ($member['paid'] !== null) · <span class="{{ $member['paid'] ? 'paid' : 'unpaid' }}">{{ $member['paid'] ? 'PAID' : 'UNPAID' }}</span>@endif</div>
              @empty
                <span class="muted">No members</span>
              @endforelse
            </td>
            <td class="alias">{{ $team['alias'] }}</td>
            <td class="title-cell">{{ $team['title'] ?? '—' }}</td>
            <td class="line">
              @foreach (['adviser' => 'Adviser', 'consultant' => 'Consultant', 'grammarian' => 'Grammarian'] as $key => $label)
                <div><span class="k">{{ $label }}:</span> @if ($team['experts'][$key] ?? null){{ $team['experts'][$key] }}@else<span class="none-yet">None yet</span>@endif</div>
              @endforeach
            </td>
            <td class="line">
              @foreach (['chair' => 'Chair', 'm1' => 'Member 1', 'm2' => 'Member 2'] as $key => $label)
                <div><span class="k">{{ $label }}:</span> @if ($team['panel'][$key] ?? null){{ $team['panel'][$key] }}@else<span class="none-yet">None yet</span>@endif</div>
              @endforeach
            </td>
            <td></td>
            <td class="r">{{ $team['similarity'] !== null ? round($team['similarity']).'%' : '—' }}</td>
          </tr>
        @endforeach
      @empty
        <tr><td class="empty" colspan="11">No teams in your sections yet.</td></tr>
      @endforelse
    </tbody>
  </table>
  <div class="foot-note">CANCELLED: the CaPA did not give the go, or the requirements were not complete on the defense day; RESCHED is the new date the CaPSA/TSA set. REDEFENSE: the team waits for a new date before the deadline shown. (Returnee): a student back from an earlier school year.</div>
</body>
</html>
