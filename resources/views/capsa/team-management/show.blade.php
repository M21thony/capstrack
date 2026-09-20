{{--
  One team, whole. Everything the seven tabs used to spread out - who is in
  it, which experts accepted, and how far along every milestone is - on the
  page you land on by clicking the team's row.
--}}
@extends('capsa.team-management._shell')

@section('title', $row->team->team_name)

@section('tm-content')
@php
  use App\Support\MilestoneProgress;

  $team = $row->team;
  $maxMembers = \App\Models\Team::maxMembers();
  $palette = ['#c9a82c,#8a6e10', '#16a34a,#0891b2', '#1d4ed8,#7c3aed', '#b45309,#dc2626', '#0891b2,#16a34a'];
  $avatar = fn ($seed) => 'linear-gradient(135deg,'.$palette[abs((int) $seed) % count($palette)].')';
  $initialsOf = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))
      ->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('');

  // The panel's average at the latest stage they have actually been graded on.
  $score = $defenseScores->get($team->team_id, collect())->last(fn ($s) => $s->average !== null);
@endphp

<div class="th">
  <div class="tile"><x-tm-icon n="users" s="22" /></div>
  <div class="grow">
    <h1>{{ $team->team_name }}</h1>
    <p>{{ $team->project_title ?: 'No final title yet' }}</p>
  </div>
  <span class="chip-o"><x-tm-icon n="cap" />{{ $row->section }}</span>
  <span class="chip-o">
    <x-tm-icon n="trophy" />
    @if ($score)
      {{ \App\Support\DefenseScores::format($score->average) }}<small>{{ $score->label }}</small>
    @else
      No defense yet
    @endif
  </span>
  @if ($team->is_confirmed)
    <span class="pill p-green">Verified</span>
  @elseif ($row->awaitingVerification)
    <span class="pill p-amber">To verify</span>
  @else
    <span class="pill p-gray">{{ ucfirst($team->team_status) }}</span>
  @endif
  <a class="btn {{ $row->pendingProposalCount ? 'btn-p' : 'btn-o' }}"
     href="{{ route('capsa.team_management.pre_proposals', $team->team_id) }}">
    <x-tm-icon n="file" s="15" />Check Pre-proposals
    @if ($row->pendingProposalCount)
      <span class="nb">{{ $row->pendingProposalCount }} waiting</span>
    @endif
  </a>
</div>

<div class="tcols">
  <div class="tcol">
    <section class="card">
      <div class="card-h"><span class="hi"><x-tm-icon n="users" /></span><h2>Team Details</h2></div>

      <div class="blk">
        <div class="blk-h"><span class="di"><x-tm-icon n="users" s="16" /></span>Members
          <span class="n">{{ $row->members->count() }} of {{ $maxMembers }}</span></div>
        <ul class="mrow">
          {{-- The leader first: the one thing about this list that is not just the roster order. --}}
          @foreach ($row->members->sortByDesc(fn ($m) => (int) $m->student_id === (int) $team->leader_id) as $member)
            @php
              $name = trim(($member->f_name ?? '').' '.($member->l_name ?? ''));
              $isLeader = (int) $member->student_id === (int) $team->leader_id;
            @endphp
            <li class="{{ $isLeader ? 'lead' : '' }}">
              <div class="av" style="background:{{ $avatar($member->student_id) }}">{{ $initialsOf($name) }}</div>
              <b>{{ $name ?: 'Unnamed student' }}</b>
              <small>{{ $isLeader ? 'Leader' : 'Member' }}</small>
            </li>
          @endforeach
          @if ($row->members->isEmpty())
            <li><b>No members yet</b></li>
          @endif
        </ul>
      </div>

      <div class="blk">
        <div class="blk-h"><span class="di"><x-tm-icon n="cap" s="16" /></span>Experts</div>
        {{-- All three seats, always: an empty one is information too. --}}
        <ul class="xp">
          @foreach ($experts as $seat)
            @if (! $seat->expert)
              <li class="none">
                <div class="av ghost"><x-tm-icon n="user" /></div>
                <div class="who">
                  <div class="top"><span class="nm">None yet</span><span class="rl">{{ ucfirst($seat->role) }}</span></div>
                  <div class="deg">No {{ $seat->role }} has accepted yet</div>
                </div>
              </li>
            @else
              <li>
                <div class="av" style="background:{{ $avatar($seat->expert->expert_id) }}">{{ $initialsOf($seat->expert->name) }}</div>
                <div class="who">
                  <div class="top"><span class="nm">{{ $seat->expert->name }}</span><span class="rl">{{ ucfirst($seat->role) }}</span></div>
                  @if ($seat->degree)<div class="deg">{{ $seat->degree }}</div>@endif
                  @if ($seat->expertise->isNotEmpty())
                    <div class="tags">@foreach ($seat->expertise as $field)<span>{{ $field }}</span>@endforeach</div>
                  @endif
                  <a class="em" href="mailto:{{ $seat->expert->email }}"><x-tm-icon n="mail" s="13" />{{ $seat->expert->email }}</a>
                </div>
              </li>
            @endif
          @endforeach
        </ul>
      </div>

      {{-- The Papers tab folded in here. Her format pass on what this team
           sent her as Subject Adviser; what she reviews as an adviser or a
           consultant is a different job and stays in My Expert Duties. --}}
      <div class="blk">
        <div class="blk-h"><span class="di"><x-tm-icon n="file" s="16" /></span>Papers
          <span class="n">{{ $row->papersToCheck }} to check</span></div>
        <ul class="pps">
          @forelse ($row->papers as $paper)
            @php
              $reviewed = $paper->review_status === 'Reviewed';
              $mark = $reviewed ? 'Checked' : ($paper->review_status === 'Reviewing' ? 'In progress' : 'To check');
              $meta = collect([
                $paper->chapter ?: 'Chapter not set',
                'v'.$paper->file_version,
                $paper->submitted_at ? \Illuminate\Support\Carbon::parse($paper->submitted_at)->format('M j, Y') : null,
              ])->filter()->implode(' &middot; ');
            @endphp
            <li>
              <span class="fn">{{ $paper->file_name }}<small>{!! $meta !!}</small></span>
              <span class="pill {{ $reviewed ? 'p-green' : 'p-amber' }}">{{ $mark }}</span>
              <a class="btn {{ $reviewed ? 'btn-o' : 'btn-p' }}"
                 href="{{ route('instructor.docreview.annotate', $paper->review_id) }}">{{ $reviewed ? 'Open' : 'Check format' }}</a>
            </li>
          @empty
            <li class="none">Nothing to check. Papers appear here when this team sends one to their Subject Adviser.</li>
          @endforelse
        </ul>
      </div>

      <div class="ir">
        <span class="di"><x-tm-icon n="cal" /></span>
        <span class="k">Date Formed</span>
        <span class="v {{ $team->created_at ? '' : 'none' }}">{{ $team->created_at ? \Illuminate\Support\Carbon::parse($team->created_at)->format('M j, Y') : 'Unknown' }}</span>
      </div>
    </section>
  </div>

  <div class="tcol">
    <section class="card msc">
      <div class="card-h"><span class="hi"><x-tm-icon n="file" /></span><h2>Milestones</h2>
        <span class="cnt">{{ $row->milestones?->completed ?? 0 }} of {{ $row->milestones?->total ?? 0 }} items done</span></div>

      @if ($milestones->isEmpty())
        <p class="none" style="padding:14px 0">This section has no checklist yet, so there is nothing to track.</p>
      @else
        <ul class="trk">
          @foreach ($milestones as $i => $ms)
            @php
              [$word, $pillClass, $iconName] = MilestoneProgress::milestoneState($ms);
              $tone = ['Completed' => 'd', 'Overdue' => 'o', 'In progress' => 'p'][$word] ?? 'n';
            @endphp
            <li>
              <a class="t-{{ $tone }}"
                 href="{{ route('capsa.team_management.show', [$row->team->team_id, 'milestone' => $i]) }}"
                 aria-pressed="{{ $i === $selectedIndex ? 'true' : 'false' }}"
                 aria-label="{{ $ms->milestone->name }}: {{ $word }}{{ $ms->locked ? '' : ", {$ms->completed} of {$ms->total} done" }}">
                <span class="b4"><i style="width:{{ $ms->percent }}%"></i></span>
                <span class="tn">{{ $ms->milestone->name }}</span>
                <span class="tc"><x-tm-icon :n="$iconName" s="12" bold />{{ $ms->locked ? 'Locked' : "{$ms->completed} of {$ms->total}" }}</span>
              </a>
            </li>
          @endforeach
        </ul>

        <div class="msh">
          <h3>{{ $selected->milestone->name }}</h3>
          @if ($selected->due_on)
            <span><x-tm-icon n="cal" s="13" />Due {{ \Illuminate\Support\Carbon::parse($selected->due_on)->format('M j, Y') }}</span>
          @endif
          @if ($selected->milestone->requires_previous && $selected->requires)
            <span><x-tm-icon n="lock" s="13" />Needs {{ $selected->requires }} complete first</span>
          @endif
        </div>

        @if ($selected->locked)
          <div class="lockmsg"><x-tm-icon n="lock" s="16" />
            <span>The team can't hand anything in for {{ $selected->milestone->name }} until {{ $selected->requires }} is complete.</span></div>
        @endif

        {{-- Flat: every item of the chosen milestone, nothing folded away. --}}
        <ul class="its2">
          @foreach ($selected->items as $item)
            @php
              $short = ['done' => 'd', 'done_late' => 'l', 'handed_in' => 'h', 'overdue' => 'o'][$item->state] ?? 'n';
              $mark = [
                'd' => ['Done', 'p-green'], 'l' => ['Done late', 'p-green'], 'h' => ['Handed in', 'p-blue'],
                'o' => ['Overdue', 'p-red'], 'n' => ['Not started', 'p-gray'],
              ][$short];
              $note = MilestoneProgress::itemNote($item, $selected->locked, $selected->requires);
              $steps = MilestoneProgress::steps($item->item->kind, $item->state, $item->waiting_on);
              $stepsDone = collect($steps)->where('state', 'done')->count();
            @endphp
            <li class="it {{ $short }}{{ $selected->locked ? ' lk' : '' }}">
              <div class="nm">{{ $item->item->title }}@if ($note)<small>{{ $note }}</small>@endif</div>
              <ol class="stp" aria-label="{{ $stepsDone }} of {{ count($steps) }} steps done">
                @foreach ($steps as $step)
                  @php $dot = ['done' => 'y', 'current' => 'c', 'missed' => 'x'][$step['state']] ?? ''; @endphp
                  <li class="{{ $dot }}"><span class="sd">@if ($dot === 'y')<x-tm-icon n="check" s="10" bold />@endif</span>{{ $step['label'] }}</li>
                @endforeach
              </ol>
              @if ($selected->locked)
                <span class="pill p-gray"><x-tm-icon n="lock" s="11" />Locked</span>
              @else
                <span class="pill {{ $mark[1] }}">{{ $mark[0] }}</span>
              @endif
            </li>
          @endforeach
        </ul>
      @endif

      <div class="sched">
        <span class="tile"><x-tm-icon n="cal" s="16" /></span>
        <div>
          <div class="k">Defense Schedule</div>
          @forelse ($defenseSchedules as $sched)
            <div class="v">{{ $sched->defense_type }} &middot; {{ $sched->schedule_date?->format('M j, Y') ?? 'date not set' }}</div>
          @empty
            <div class="v none">Not scheduled yet</div>
          @endforelse
        </div>
      </div>
    </section>
  </div>
</div>
@endsection
