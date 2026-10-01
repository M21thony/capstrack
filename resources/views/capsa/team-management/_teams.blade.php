{{--
  The Teams tab: every team, one row each, its progress as one strip.

  The old Teams / Progress switch showed the same teams twice; progress is
  now one column - one block per milestone, coloured by state - and the full
  detail is a click away on the team's own page. Quick views, search and
  filters hide rows here and now; Section is a page load, because it decides
  which teams are on the page at all.
--}}
@php
  use App\Support\MilestoneProgress;
  use Illuminate\Support\Carbon;
  use Illuminate\Support\Str;

  $capsaPortal = $portal === 'capsa';
  $showRoute = $capsaPortal ? 'capsa.team_management.show' : 'instructor.teams.show';
  $maxMembers = \App\Models\Team::maxMembers();
  $toneOf = fn ($word) => ['Completed' => 'd', 'Closed' => 'd', 'Overdue' => 'o', 'Past due' => 'o', 'In progress' => 'p', 'Locked' => 'k'][$word] ?? 'n';
  $waitsBy = $todo->groupBy('teamId');

  // Said once, so the Status column and the Status filter cannot disagree.
  $statusOf = fn ($r) => $r->failedStage ? 'Missed deadline'
    : ($r->team->is_confirmed ? 'Verified' : ($r->awaitingVerification ? 'To verify' : ucfirst($r->team->team_status)));
  $statusTone = ['Verified' => 'st-ok', 'To verify' => 'st-warn', 'Missed deadline' => 'st-bad'];
  $statusRank = ['Missed deadline' => 0, 'To verify' => 1, 'Verified' => 3];

  $behind = fn ($r) => ($r->milestones?->overdue ?? 0) > 0 || $r->failedStage;
  $quick = array_filter([
    'all' => ['All teams', $rows->count()],
    'mine' => $twoHats ? ['My teams', $rows->filter(fn ($r) => $r->myRole)->count()] : null,
    'you' => ['To do', $rows->filter(fn ($r) => $waitsBy->has((int) $r->team->team_id))->count()],
    'behind' => ['Overdue', $rows->filter($behind)->count()],
  ]);
@endphp

<div class="tools">
  <div class="seg tm-quick" role="group" aria-label="Quick view">
    @foreach ($quick as $key => [$label, $n])
      <button type="button" data-quick="{{ $key }}" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}">{{ $label }} <span class="num muted">{{ $n }}</span></button>
    @endforeach
  </div>

  <label class="search">
    <x-tm-icon n="search" s="16" />
    <input type="search" id="tm-q" placeholder="Search team, title or member (press /)" aria-label="Search teams">
  </label>

  @if ($capsaPortal)
    <form method="get" action="{{ route('capsa.team_management') }}" style="display:contents">
      <input type="hidden" name="tab" value="teams">
      <select class="sel" name="section" aria-label="Section" onchange="this.form.submit()">
        <option value="">All sections</option>
        @foreach ($sectionOptions as $section)
          <option value="{{ $section }}" @selected($selectedSection === $section)>{{ $section }}</option>
        @endforeach
      </select>
    </form>
  @else
    <div class="seg tm-roles" role="group" aria-label="My role">
      @foreach (['all' => 'All roles', 'adviser' => 'Adviser', 'consultant' => 'Consultant', 'grammarian' => 'Grammarian', 'panel' => 'Panel'] as $role => $label)
        <button type="button" data-role="{{ $role }}" aria-pressed="{{ $role === 'all' ? 'true' : 'false' }}">{{ $label }}</button>
      @endforeach
    </div>
  @endif

  <select class="sel" id="tm-st" aria-label="Status">
    <option value="">All status</option>
    @foreach ($rows->map($statusOf)->unique()->sort() as $status)
      <option value="{{ $status }}">{{ $status }}</option>
    @endforeach
  </select>

  <button type="button" class="btn" data-clear hidden>Clear filters</button>
</div>

<div class="card" id="tm-teams">
  <div class="tscroll"><table data-export="Teams" data-export-into="#tm-teams .tx-host">
    <thead><tr>
      <th aria-sort="none"><button type="button" class="sort" data-sort="name">Team<span aria-hidden="true">↕</span></button></th>
      <th class="c-adv" aria-sort="none"><button type="button" class="sort" data-sort="adviser">Adviser<span aria-hidden="true">↕</span></button></th>
      <th aria-sort="none"><button type="button" class="sort" data-sort="progress">Progress<span aria-hidden="true">↕</span></button></th>
      <th>Next</th>
      <th aria-sort="none"><button type="button" class="sort" data-sort="status">Status<span aria-hidden="true">↕</span></button></th>
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
          $states = $milestones->map(fn ($ms) => MilestoneProgress::milestoneState($ms)[0]);
          $done = $states->filter(fn ($w) => in_array($w, ['Completed', 'Closed'], true))->count();
          $current = $milestones->first(fn ($ms, $i) => ! in_array($states[$i], ['Completed', 'Closed'], true));
          $overdue = $row->milestones?->overdue ?? 0;
          $score = $row->seesScores ? $defenseScores->get($team->team_id, collect())->last(fn ($s) => $s->average !== null) : null;
          $nextDefense = $row->defenseSchedules->filter(fn ($s) => $s->schedule_date && Carbon::parse($s->schedule_date)->isFuture())
            ->sortBy('schedule_date')->first();
          $next = match (true) {
            (bool) $nextDefense => $nextDefense->defense_type.', '.Carbon::parse($nextDefense->schedule_date)->format('M j'),
            $row->failedStage !== null => 'Missed the '.$row->failedStage.' window',
            (bool) $current => $current->milestone->name.($current->due_on ? ', due '.Carbon::parse($current->due_on)->format('M j') : ''),
            $milestones->isNotEmpty() => 'All milestones done',
            default => $row->currentStage ?: 'Nothing scheduled',
          };
          $untitled = [
            'awaiting_team_choice' => $row->approvedProposalCount.' recommended - the team is choosing',
            'settling' => 'One title recommended, waiting for the adviser',
            'awaiting_review' => $row->inSections ? 'Titles to review' : 'Titles with the CaPSA',
            'all_rejected' => 'All titles turned down - the team is writing new ones',
          ][$row->titleState] ?? 'No titles yet';
        @endphp
        <tr data-href="{{ $href }}" data-q="{{ $haystack }}" data-st="{{ $status }}"
            data-needs="{{ $waits->isNotEmpty() ? 1 : 0 }}" data-behind="{{ $behind($row) ? 1 : 0 }}"
            data-mine="{{ $row->myRole ? 1 : 0 }}" data-role="{{ $row->myRole }}"
            data-name="{{ Str::lower($team->team_name) }}" data-adviser="{{ Str::lower($adviser?->name ?? '~') }}"
            data-progress="{{ $done }}" data-rank="{{ $statusRank[$status] ?? 2 }}"
            @class(['wait' => $waits->isNotEmpty()])>
          <td data-label="Team"><div class="tm">
            <div class="av" style="background:{{ Str::before($row->gradient, ',') }}">{{ $row->initials }}</div>
            <div style="min-width:0">
              <a class="tn" href="{{ $href }}">{{ $team->team_name }}</a>
              <span class="tt" title="{{ $team->project_title ?: $untitled }}">@if ($team->project_title){{ $team->project_title }}@else<i>{{ $untitled }}</i>@endif</span>
              <small>@if ($row->myRole && ($twoHats || ! $capsaPortal))<b class="hat">{{ ucfirst($row->myRole) }}</b> · @endif{{ $row->section }} · {{ $row->members->count() }} of {{ $maxMembers }} members</small>
              @if ($waits->isNotEmpty())
                <span class="w tm-what">To do: {{ $waits->pluck('what')->implode(' · ') }}</span>
              @endif
            </div>
          </div></td>
          <td class="c-adv" data-label="Adviser">@if ($adviser){{ $adviser->name }}@else<span class="muted">None yet</span>@endif</td>
          <td data-label="Progress">
            @if ($milestones->isNotEmpty())
              <div class="prog">
                <div class="strip" role="img" aria-label="{{ $done }} of {{ $milestones->count() }} milestones complete">
                  @foreach ($milestones as $i => $ms)<i class="s-{{ $toneOf($states[$i]) }}" title="{{ $ms->milestone->name }}: {{ $states[$i] }}"></i>@endforeach
                </div>
                <small @class(['od' => $overdue])>@if ($overdue){{ $overdue }} overdue · @endif{{ $done }} of {{ $milestones->count() }} · {{ $current ? $current->milestone->name : 'All done' }}</small>
              </div>
            @elseif ($row->milestones === null && $row->myRole && ! $row->inSections && ! in_array($row->myRole, \App\Support\TeamRows::FOLLOWS_PROGRESS, true))
              <span class="muted">Followed by its adviser and consultant</span>
            @else
              <span class="muted">No checklist yet</span>
            @endif
          </td>
          <td data-label="Next">{{ $next }}
            @if ($score)
              <div class="muted" style="font-size:12px" aria-label="Defense scores">{{ \App\Support\DefenseScores::format($score->average) }} at the {{ $score->label }}</div>
            @endif
          </td>
          <td data-label="Status">
            <span class="state {{ $statusTone[$status] ?? 'st-idle' }}">{{ $status }}</span>
          </td>
        </tr>
      @endforeach
      <tr class="tm-none" @if ($rows->isNotEmpty()) hidden @endif>
        <td colspan="5" class="empty">
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
  <div class="legend">
    <span><span class="strip"><i class="s-d"></i></span>Completed</span>
    <span><span class="strip"><i class="s-p"></i></span>In progress</span>
    <span><span class="strip"><i class="s-o"></i></span>Overdue</span>
    <span><span class="strip"><i class="s-n"></i></span>Not started</span>
    <span><span class="strip"><i class="s-k"></i></span>Locked until the one before is done</span>
  </div>
  <div class="foot"><span data-count>{{ $rows->count() }} {{ Str::plural('team', $rows->count()) }}</span><span class="tx-host"></span></div>
</div>
