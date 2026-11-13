{{--
  The Teams tab: every team, one compact row each - how many requirements
  it has checked, what it needs next, its status, and Open team.
  Quick views, search, program and section hide rows here and now.
--}}
@php
  use App\Support\MilestoneProgress;
  use App\Support\TeamChecklist;
  use Illuminate\Support\Carbon;
  use Illuminate\Support\Str;

  $capsaPortal = $portal === 'capsa';
  $showRoute = $capsaPortal ? 'capsa.team_management.show' : 'instructor.teams.show';
  $waitsBy = $todo->groupBy('teamId');

  // Said once, so the Status column and the sort cannot disagree.
  $statusOf = fn ($r) => $r->failedStage ? 'Failed'
    : ($r->team->is_confirmed ? 'Verified' : ($r->awaitingVerification ? 'To verify' : ucfirst($r->team->team_status)));
  $statusTone = ['Verified' => 'green', 'To verify' => 'amber', 'Failed' => 'red'];
  $statusRank = ['Failed' => 0, 'To verify' => 1, 'Verified' => 3];

  $quick = array_filter([
    'all' => ['All teams', $rows->count()],
    'you' => ['Needs action', $rows->filter(fn ($r) => $waitsBy->has((int) $r->team->team_id))->count()],
    'mine' => $twoHats ? ['My teams', $rows->filter(fn ($r) => $r->myRole)->count()] : null,
  ]);

  // Programs and sections from the sections this person actually has.
  $sectionList = collect($capsaPortal ? $sectionOptions : $rows->pluck('section'))->filter()->unique()->sort()->values();
  $programs = $sectionList->map(fn ($s) => TeamChecklist::programOf($s))->unique()->sort()->values();
@endphp

<div class="tools">
  <div class="seg tm-quick" role="group" aria-label="Quick view">
    @foreach ($quick as $key => [$label, $n])
      <button type="button" data-quick="{{ $key }}" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}">{{ $label }} <span class="num muted">{{ $n }}</span></button>
    @endforeach
  </div>

  @unless ($capsaPortal)
    <div class="seg tm-roles" role="group" aria-label="My role">
      @foreach (['all' => 'All roles', 'adviser' => 'Adviser', 'consultant' => 'Consultant', 'grammarian' => 'Grammarian', 'panel' => 'Panel'] as $role => $label)
        <button type="button" data-role="{{ $role }}" aria-pressed="{{ $role === 'all' ? 'true' : 'false' }}">{{ $label }}</button>
      @endforeach
    </div>
  @endunless

  <label class="search">
    <x-tm-icon n="search" s="16" />
    <input type="search" id="tm-q" placeholder="Search team, project or member (press /)" aria-label="Search teams">
  </label>

  <select class="sel" id="tm-prog" aria-label="Program">
    <option value="">All programs</option>
    @foreach ($programs as $program)
      <option value="{{ $program }}" @selected($capsaPortal && $selectedSection && TeamChecklist::programOf($selectedSection) === $program)>{{ $program }}</option>
    @endforeach
  </select>
  <select class="sel" id="tm-sec" aria-label="Section" data-server="{{ $capsaPortal ? $selectedSection : '' }}">
    <option value="">All sections</option>
    @foreach ($sectionList as $section)
      <option value="{{ $section }}" data-prog="{{ TeamChecklist::programOf($section) }}" @selected($capsaPortal && $selectedSection === $section)>{{ $section }}</option>
    @endforeach
  </select>

  <button type="button" class="btn" data-clear hidden>Clear filters</button>
</div>

<div class="card" id="tm-teams">
  <div class="tscroll"><table data-export="Teams" data-export-into="#tm-teams .tx-host">
    <thead><tr>
      <th aria-sort="none"><button type="button" class="sort" data-sort="name">Team &amp; project<span aria-hidden="true">↕</span></button></th>
      <th class="c-sec">Section</th>
      <th class="c-adv" aria-sort="none"><button type="button" class="sort" data-sort="adviser">Adviser<span aria-hidden="true">↕</span></button></th>
      <th aria-sort="none"><button type="button" class="sort" data-sort="progress">Requirements checked<span aria-hidden="true">↕</span></button></th>
      <th>Next action</th>
      <th aria-sort="none"><button type="button" class="sort" data-sort="status">Status<span aria-hidden="true">↕</span></button></th>
      <th class="c-act"><span class="vh">Action</span></th>
    </tr></thead>
    <tbody>
      @foreach ($rows as $row)
        @php
          $team = $row->team;
          $status = $statusOf($row);
          $href = route($showRoute, $team->team_id);
          $waits = $waitsBy->get((int) $team->team_id, collect());
          $haystack = Str::lower(collect([
            $team->team_name, $team->project_title, $row->section, $row->leaderName,
          ])->merge($row->members->map(fn ($m) => trim(($m->f_name ?? '').' '.($m->l_name ?? ''))))
            ->filter()->implode(' '));
          $adviser = $row->engagedRoles->get('adviser');
          $milestones = $row->milestones?->milestones ?? collect();
          $items = $milestones->flatMap(fn ($ms) => $ms->items);
          $done = $items->whereIn('state', MilestoneProgress::COMPLETE_STATES)->count();
          $total = $items->count();
          $current = $milestones->first(fn ($ms) => ! in_array(MilestoneProgress::milestoneState($ms)[0], ['Completed', 'Closed'], true));
          $overdue = $row->milestones?->overdue ?? 0;
          $score = $row->seesScores ? $defenseScores->get($team->team_id, collect())->last(fn ($s) => $s->average !== null) : null;
          $nextDefense = $row->defenseSchedules->filter(fn ($s) => $s->schedule_date && Carbon::parse($s->schedule_date)->isFuture())
            ->sortBy('schedule_date')->first();
          [$next, $when] = match (true) {
            $waits->isNotEmpty() => [$waits->first()->what.($waits->count() > 1 ? ' +'.($waits->count() - 1).' more' : ''), null],
            (bool) $nextDefense => [$nextDefense->defense_type, Carbon::parse($nextDefense->schedule_date)->format('M j, Y')],
            $row->failedStage !== null => ['Did not pass the '.$row->failedStage, null],
            (bool) $current => [$current->milestone->name, $current->due_on ? 'Due '.Carbon::parse($current->due_on)->format('M j, Y') : null],
            $milestones->isNotEmpty() => ['All milestones done', null],
            default => [$row->currentStage ?: 'Nothing scheduled', null],
          };
          $untitled = [
            'awaiting_team_choice' => $row->approvedProposalCount.' recommended - the team is choosing',
            'settling' => 'One title recommended, waiting for the adviser',
            'awaiting_review' => $row->inSections ? 'Titles to review' : 'Titles with the CaPSA/TSA',
            'all_rejected' => 'All titles turned down - the team is writing new ones',
          ][$row->titleState] ?? 'No titles yet';
        @endphp
        <tr data-href="{{ $href }}" data-q="{{ $haystack }}" data-st="{{ $status }}"
            data-prog="{{ TeamChecklist::programOf((string) $row->section) }}" data-sec="{{ $row->section }}"
            data-needs="{{ $waits->isNotEmpty() ? 1 : 0 }}" data-behind="{{ $overdue > 0 || $row->failedStage ? 1 : 0 }}"
            data-mine="{{ $row->myRole ? 1 : 0 }}" data-role="{{ $row->myRole }}"
            data-name="{{ Str::lower($team->team_name) }}" data-adviser="{{ Str::lower($adviser?->name ?? '~') }}"
            data-progress="{{ $done }}" data-rank="{{ $statusRank[$status] ?? 2 }}">
          <td data-label="Team"><div class="tm">
            <div class="av" style="background:{{ Str::before($row->gradient, ',') }}">{{ $row->initials }}</div>
            <div style="min-width:0">
              <a class="tn" href="{{ $href }}">{{ $team->team_name }}</a>
              <span class="tt" title="{{ $team->project_title ?: $untitled }}">@if ($team->project_title){{ $team->project_title }}@else<i>{{ $untitled }}</i>@endif</span>
              @if ($row->myRole && ($twoHats || ! $capsaPortal))<small><b class="hat">{{ ucfirst($row->myRole) }}</b></small>@endif
            </div>
          </div></td>
          <td class="c-sec" data-label="Section">{{ $row->section }}</td>
          <td class="c-adv" data-label="Adviser">@if ($adviser){{ $adviser->name }}@else<span class="muted">No adviser</span>@endif</td>
          <td data-label="Requirements">
            @if ($total)
              <div class="req">
                <div class="bar" role="img" aria-label="{{ $done }} of {{ $total }} requirements checked"><i style="width:{{ round($done / $total * 100) }}%"></i></div>
                <small>{{ $done }} of {{ $total }} checked</small>
              </div>
            @elseif ($row->milestones === null && $row->myRole && ! $row->inSections && ! in_array($row->myRole, \App\Support\TeamRows::FOLLOWS_PROGRESS, true))
              <span class="muted">Followed by its adviser and consultant</span>
            @else
              <span class="muted">No checklist yet</span>
            @endif
          </td>
          <td data-label="Next action">
            <span @class(['nx', 'tm-what' => $waits->isNotEmpty()])>{{ $next }}</span>
            @if ($when)<small class="muted nx-d">{{ $when }}</small>@endif
            @if ($score)
              <small class="muted nx-d" aria-label="Defense scores">{{ \App\Support\DefenseScores::format($score->average) }} at the {{ $score->label }}</small>
            @endif
          </td>
          <td data-label="Status">
            <span class="pill p-{{ $statusTone[$status] ?? 'gray' }}">{{ $status }}</span>
            @if ($overdue)<span class="pill p-red">{{ $overdue }} overdue</span>@endif
          </td>
          <td class="c-act"><a class="btn" href="{{ $href }}">Open team</a></td>
        </tr>
      @endforeach
      <tr class="tm-none" @if ($rows->isNotEmpty()) hidden @endif>
        <td colspan="7" class="empty">
          @if ($rows->isEmpty())
            @if ($capsaPortal)
              No teams in {{ $selectedSection ? 'section '.$selectedSection : 'your sections' }} yet.
            @else
              No teams yet. Teams you accept as adviser, consultant or grammarian, and teams you sit on a panel for, show up here.
            @endif
          @else
            No teams match. <button type="button" class="btn" data-clear style="margin-left:8px">Clear filters</button>
          @endif
        </td>
      </tr>
    </tbody>
  </table></div>
  <div class="foot"><span data-count>{{ $rows->count() }} {{ Str::plural('team', $rows->count()) }}</span><span class="tx-host"></span></div>
</div>
