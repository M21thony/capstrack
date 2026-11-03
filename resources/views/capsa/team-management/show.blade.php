{{--
  One team, whole: who is in it, who advises and grades it, how far along
  every milestone is, and what it has sent or done lately.

  On a desktop it fits the window and never grows. Papers and Activity show
  only the rows that fit; "See all" opens the rest on a page of its own.
--}}
@extends('capsa.team-management._shell')

@section('title', $row->team->team_name)

@push('head')
<style nonce="{{ Vite::cspNonce() }}">
  .tp{display:flex;flex-direction:column;gap:10px}

  .tp-head{display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap}
  .tp-tav{width:52px;height:52px;border-radius:13px;flex:none;display:flex;align-items:center;justify-content:center;font-size:17px;font-weight:800;color:#fff}
  .tp-head .grow{flex:1;min-width:240px}
  .tp-head h1{margin:0;font-size:24px;font-weight:800;letter-spacing:-.4px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;line-height:1.2}
  .tp-meta{font-size:13px;color:var(--ink2);margin-top:3px;display:flex;gap:4px;white-space:nowrap;min-width:0}
  .tp-meta .t{overflow:hidden;text-overflow:ellipsis;min-width:0}
  .tp-meta a{color:var(--ink2);font-weight:600;text-decoration:underline;text-decoration-color:var(--line2);text-underline-offset:2px}
  .tp-facts{display:flex;flex-wrap:wrap;gap:6px 28px;margin:6px 0 0}
  .tp-facts dt{font-size:10.5px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--mut)}
  .tp-facts dd{margin:2px 0 0;font-size:13px;font-weight:600;color:var(--ink);font-variant-numeric:tabular-nums}
  .tp-acts{display:flex;gap:8px;flex-wrap:wrap}
  .tp-acts .btn{padding:7px 13px;font-size:12.5px;border-radius:8px;box-shadow:none}

  /* What on this team is waiting on her: the first thing, and how many more. */
  .tp-needs{display:flex;align-items:center;gap:14px;padding:10px 18px;border:1px solid #b45309;background:#fffbeb;border-radius:12px;flex-wrap:wrap}
  .tp-needs-h{display:flex;flex-direction:column;gap:3px;font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:#b45309;white-space:nowrap}
  .tp-needs-h a{font-size:12px;letter-spacing:0;text-transform:none;font-weight:600;color:var(--ink2);text-decoration:underline;text-underline-offset:2px}
  .tp-needs-ico{width:36px;height:36px;border-radius:9px;display:grid;place-items:center;background:var(--tint);color:var(--accent3);flex:none}
  .tp-needs-what{flex:1;min-width:200px;display:flex;flex-direction:column;gap:2px;font-size:12.5px;color:var(--mut)}
  .tp-needs-what b{font-size:13.5px;color:var(--ink)}
  .tp-needs .btn{padding:7px 13px;font-size:12.5px;border-radius:8px;box-shadow:none}
  .tp-st{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:var(--mut);letter-spacing:0}
  .tp-st::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}
  .tp-st.ok{color:var(--green)} .tp-st.warn{color:#b45309}
  .tp-facts dd.mono{font-family:'IBM Plex Mono',ui-monospace,monospace}
  .tp .btn{box-shadow:none}

  .tp-grid{display:grid;grid-template-columns:300px minmax(0,1fr);gap:16px;align-items:stretch}
  .tp-col{display:flex;flex-direction:column;gap:16px;min-width:0}
  .tp-col > :last-child{flex:1}
  .tp-sec{padding:12px 18px;border-top:1px solid var(--line)}
  .tp-sec:first-child{border-top:0}
  .tp-sec h3{margin:0 0 9px;font-size:13px;font-weight:700;display:flex;justify-content:space-between;gap:8px}
  .tp-sec h3 small{font-weight:500;color:var(--mut);font-variant-numeric:tabular-nums}
  .tp-note{margin:9px 0 0;font-size:12px;color:var(--mut)}

  /* Progress: a numbered list on the left, the chosen milestone on the right. */
  .tp-strip{display:flex;gap:3px;align-items:center}
  .tp-strip i{width:14px;height:6px;border-radius:3px;background:var(--tint2)}
  .tp-strip .t-d{background:var(--green)} .tp-strip .t-o{background:var(--red)} .tp-strip .t-p{background:var(--accent)}
  .tp-ms{display:grid;grid-template-columns:220px minmax(0,1fr)}
  .tp-nav{list-style:none;margin:0;padding:4px 8px;border-right:1px solid var(--line)}
  .tp-nav a{display:flex;align-items:center;gap:9px;padding:1px 8px;border-radius:7px;font-size:12.5px;font-weight:500;color:var(--ink2);text-decoration:none}
  .tp-nav a:hover{background:var(--tint)}
  .tp-nav a[aria-current="true"]{background:var(--accent-bg);color:var(--ink);font-weight:700}
  .tp-nav .n{width:20px;height:20px;flex:none;border-radius:50%;display:flex;align-items:center;justify-content:center;
             font-size:10.5px;font-weight:700;background:var(--tint2);color:var(--mut)}
  .tp-nav .t-d .n{background:var(--green);color:#fff}
  .tp-nav .t-o .n{background:var(--red);color:#fff}
  .tp-nav .t-p .n{background:var(--accent);color:#18191f}
  .tp-nav .nm{flex:1;min-width:0;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .tp-detail{padding:12px 18px;min-width:0}
  .tp-detail h3{margin:0;font-size:17px;font-weight:700}
  .tp-detail .sub{font-size:12.5px;color:var(--mut);margin-top:2px}
  .tp-gate{display:flex;align-items:center;gap:9px;margin-top:10px;padding:10px 12px;border-radius:9px;background:var(--tint);font-size:12.5px;color:var(--ink2)}
  .tp-gate b{color:var(--ink)}
  .tp-items{list-style:none;margin:6px 0 0;padding:0}
  .tp-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;align-items:start;padding:6px 0;border-top:1px solid var(--line)}
  .tp-item:first-child{border-top:0}
  .tp-item .tt{min-width:0;display:flex;flex-direction:column;gap:2px}
  .tp-item .tt b{font-size:13.5px;font-weight:600;color:var(--ink)}
  .tp-item .tt small{font-size:12px;color:var(--mut)}
  .tp-item.lk .tt b{color:var(--ink2)}
  .tp-steps{list-style:none;margin:3px 0 0;padding:0;display:flex;align-items:center;gap:4px 8px;flex-wrap:wrap;font-size:11.5px;color:var(--mut)}
  .tp-steps li{display:inline-flex;align-items:center;gap:4px;white-space:nowrap}
  .tp-steps .y{color:var(--green);font-weight:600}
  .tp-steps .c{color:var(--accent3);font-weight:700}
  .tp-steps .x{color:var(--red);font-weight:600}
  .tp-steps .ar{color:var(--line2)}
  .tp-state{justify-self:end;display:inline-flex;align-items:center;gap:5px;margin-top:2px;font-size:12.5px;font-weight:600;white-space:nowrap}
  .tp-state.d,.tp-state.l{color:var(--green)}
  .tp-state.h{color:var(--blue)}
  .tp-state.o{color:var(--red)}
  .tp-state.n,.tp-state.lk{color:var(--mut)}
  .tp-item .btn{justify-self:end;padding:5px 14px}

  /* Titles, while the team has none settled. */
  .tp-slots{list-style:none;margin:0;padding:0}
  .tp-slots li{display:grid;grid-template-columns:28px minmax(0,1fr) auto;gap:12px;align-items:center;padding:11px 18px;border-top:1px solid var(--line)}
  .tp-slots li:first-child{border-top:0}
  .tp-slots .k{font-size:12px;color:var(--mut);font-variant-numeric:tabular-nums}
  .tp-slots b{font-size:13.5px;display:block;color:var(--ink)}
  .tp-slots small{font-size:12px;color:var(--mut);display:flex;align-items:center;gap:6px;margin-top:3px}
  .tp-slots .end{display:flex;gap:8px;align-items:center}
  .tp-slots .st{font-size:12.5px;font-weight:600;white-space:nowrap}
  .tp-slots .st.ok{color:var(--green)} .tp-slots .st.wait{color:var(--amber)} .tp-slots .st.bad{color:var(--red)}
  .tp-slots .btn{padding:5px 14px}

  /* Papers | Activity. */
  .tp-tabs{display:inline-flex;gap:2px;padding:3px;border-radius:9px;background:var(--tint);border:1px solid var(--line)}
  .tp-tabs button{border:0;background:none;padding:4px 12px;border-radius:7px;font:inherit;font-size:12.5px;font-weight:600;color:var(--mut);cursor:pointer;white-space:nowrap}
  .tp-tabs button span{font-weight:500;color:var(--mut);font-variant-numeric:tabular-nums}
  .tp-tabs button[aria-pressed="true"]{background:var(--card);color:var(--ink);box-shadow:0 1px 2px rgba(0,0,0,.08)}
  .tp-pane{padding:6px 18px}
  .tp-pane .tp-slots li{padding:9px 0}

  /* Phones and narrow windows: three rows each, the rest behind "See all". */
  .tp-fill > li:nth-child(n+4){display:none}

  /* Desktop: the page is the window. Lists take whatever height is left and
     show only the rows that fit in it; a row that does not fit is not drawn
     at all rather than cut in half. */
  @media (min-width:1101px){
    .content:has(.tp){display:flex;flex-direction:column}
    .content:has(.tp) > .tmx{flex:1 0 auto;display:flex;flex-direction:column;padding-block:20px}
    .content:has(.tp) > .tmx > .crumb{margin-bottom:8px}
    .tp{flex:1 0 auto}
    .tp-grid{flex:1}
    .tp-col > .tp-panel,.tp-grow,.tp-grow > .tp-pane{display:flex;flex-direction:column}
    .tp-grow,.tp-grow > .tp-pane{flex:1;min-height:0}
    .tp-grow > .tp-pane[hidden]{display:none}
    .tp-fill{flex:1;min-height:98px;contain:size;display:grid;grid-template-rows:repeat(auto-fill,49px);grid-auto-rows:0;row-gap:0;overflow:hidden}
    .tp-fill.tp-feed{grid-template-rows:repeat(auto-fill,32px);min-height:96px}
    .tp-fill > li,.tp-fill > li:nth-child(n+4){display:flex;overflow:hidden;align-items:flex-start}
    .tp-fill.tp-feed > li{align-items:center}
    .tp-more{flex:none}
    .tp-col > .tp-panel:has(.tp-ms){flex:1 1 0}
    .tp-ms{flex:1;min-height:0}
    .tp-detail{display:flex;flex-direction:column}
    .tp-items{flex:1;min-height:70px;contain:size;overflow:hidden;position:relative}
  }
  @media (max-width:1100px){
    .tp-grid{grid-template-columns:minmax(0,1fr)}
  }
  @media (max-width:700px){
    .tp-ms{grid-template-columns:minmax(0,1fr)}
    .tp-nav{border-right:none;border-bottom:1px solid var(--line);display:flex;gap:2px;overflow-x:auto}
    .tp-nav li{flex:none}
    .tp-nav .nm{white-space:nowrap}
  }
</style>
@endpush

@section('tm-content')
@php
  use App\Support\MilestoneProgress;
  use Illuminate\Support\Carbon;
  use Illuminate\Support\Str;

  $team = $row->team;
  $portal = $portal ?? 'capsa';
  // What she does here as CaPSA - titles, verifying, signed papers - only on
  // a team in her sections; on a team she only advises, the page is an
  // expert's.
  $asCapsa = $portal === 'capsa' && $row->inSections;
  $showRoute = $portal === 'capsa' ? 'capsa.team_management.show' : 'instructor.teams.show';
  $logRoute = $portal === 'capsa' ? 'capsa.team_management.log' : 'instructor.teams.log';
  $hubRoute = $portal === 'capsa' ? 'capsa.team_management' : 'instructor.teams';
  $maxMembers = $team->memberLimit();
  $palette = ['#8a6e10', '#0e7490', '#4338ca', '#1d7a3e', '#9f1239'];
  $avatar = fn ($seed) => $palette[abs((int) $seed) % count($palette)];
  $initialsOf = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))
      ->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('');

  // The panel's average at the latest stage they have actually been graded on.
  $score = $row->seesScores ? $defenseScores->get($team->team_id, collect())->last(fn ($s) => $s->average !== null) : null;
  $titled = (bool) $team->project_title;
  // Papers normally start after the title, but one sent early still shows.
  $showPapers = $titled || $row->papers->isNotEmpty();

  // Next: the coming defense if one is set, else the first milestone not done.
  $nextDefense = $defenseSchedules->first(fn ($s) => $s->schedule_date && $s->schedule_date->isFuture());
  $states = $milestones->map(fn ($ms) => MilestoneProgress::milestoneState($ms)[0]);
  $toneOf = fn ($word) => ['Completed' => 'd', 'Overdue' => 'o', 'Past due' => 'o', 'In progress' => 'p'][$word] ?? 'n';
  $nextMilestone = $milestones->first(fn ($ms, $i) => $states[$i] !== 'Completed');
  $next = match (true) {
      (bool) $nextDefense => $nextDefense->defense_type.' · '.$nextDefense->schedule_date->format('M j, Y'),
      (bool) $nextMilestone => $nextMilestone->milestone->name.($nextMilestone->due_on ? ' · due '.Carbon::parse($nextMilestone->due_on)->format('M j') : ''),
      default => 'Nothing scheduled',
  };

  $untitledNote = [
      'none_submitted' => 'No titles turned in yet',
      'awaiting_review' => 'Titles waiting for your review',
      'all_rejected' => 'Every title was rejected; waiting for new ones',
      'settling' => 'One title recommended; waiting for the adviser',
      'awaiting_team_choice' => $row->approvedProposalCount.' recommended - the team is choosing',
  ][$row->titleState] ?? 'No final title yet';

  // Below the desktop width only this many rows show before "See all".
  $shown = 3;
@endphp

<div class="tp">
  <div class="tp-head">
    <span class="tp-tav" style="background:{{ Str::before($row->gradient, ',') }}" aria-hidden="true">{{ $row->initials }}</span>
    <div class="grow">
      <h1>{{ $team->team_name }}
        @if ($team->is_confirmed)
          <span class="tp-st ok">Verified</span>
        @elseif ($row->awaitingVerification)
          <span class="tp-st warn">To verify</span>
        @else
          <span class="tp-st">{{ ucfirst($team->team_status) }}</span>
        @endif
      </h1>
      <div class="tp-meta">
        <span class="t" title="{{ $titled ? $team->project_title : $untitledNote }}">{{ $titled ? $team->project_title : $untitledNote }}</span>
        @if ($asCapsa && $titled && $row->proposals->isNotEmpty())
          <span>·</span> <a href="{{ route('capsa.team_management.pre_proposals', $team->team_id) }}">See all {{ $row->proposals->count() }} submitted titles</a>
        @endif
      </div>
      <dl class="tp-facts">
        <div><dt>Section</dt><dd>{{ $row->section }}</dd></div>
        <div><dt>Next</dt><dd>{{ $next }}</dd></div>
        <div><dt>Latest score{{ $score ? ' · '.$score->label : '' }}</dt><dd @class(['mono' => $score])>{{ $score ? \App\Support\DefenseScores::format($score->average) : 'None yet' }}</dd></div>
      </dl>
    </div>
    <div class="tp-acts">
      @if ($asCapsa && $row->canVerify)
        <a class="btn btn-p" href="{{ route('capsa.team_management.pre_proposals', $team->team_id) }}"><x-tm-icon n="shield" s="14" />Verify team</a>
      @endif
      @if ($previousTeam)
        <a class="btn btn-o" href="{{ route($showRoute, $previousTeam->team_id) }}" aria-label="Previous team, {{ $previousTeam->team_name }}">← {{ $previousTeam->team_name }}</a>
        <a class="btn btn-o" href="{{ route($showRoute, $nextTeam->team_id) }}" aria-label="Next team, {{ $nextTeam->team_name }}">{{ $nextTeam->team_name }} →</a>
      @endif
    </div>
  </div>

  @if ($waiting->isNotEmpty())
    @php $first = $waiting->first(); @endphp
    <div class="tp-needs">
      <div class="tp-needs-h">Waiting on you
        @if ($waiting->count() > 1)
          <a href="{{ route($hubRoute, ['tab' => 'todo']) }}">+{{ $waiting->count() - 1 }} more in To do</a>
        @endif
      </div>
      @php $needIcons = ['title' => 'file', 'verify' => 'shield', 'paper' => 'clip', 'signed' => 'star', 'review' => 'file']; @endphp
      <span class="tp-needs-ico"><x-tm-icon :n="$needIcons[$first->kind] ?? 'dot'" s="18" /></span>
      <div class="tp-needs-what"><b>{{ $first->what }}</b><span>{{ $first->why }}</span></div>
      <a class="btn btn-p" href="{{ $first->url }}">{{ $first->label }}</a>
    </div>
  @endif

  <div class="tp-grid">
    <div class="tp-col">
      <div class="tp-panel">
        <section class="tp-sec" aria-labelledby="tp-mem">
          <h3 id="tp-mem">Members <small>{{ $row->members->count() }} of {{ $maxMembers }}</small></h3>
          <ul class="tp-ppl">
            {{-- The leader first: the one thing about this list that is not just the roster order. --}}
            @forelse ($row->members->sortByDesc(fn ($m) => (int) $m->student_id === (int) $team->leader_id) as $member)
              @php $name = trim(($member->f_name ?? '').' '.($member->l_name ?? '')); @endphp
              <li>
                <span class="tp-av" style="background:{{ $avatar($member->student_id) }}">{{ $initialsOf($name) }}@include('capsa.team-management._photo', ['url' => $member->avatarUrl()])</span>
                <b>{{ $name ?: 'Unnamed student' }}</b>
                @if ((int) $member->student_id === (int) $team->leader_id)<span class="lead">Leader</span>@endif
              </li>
            @empty
              <li class="tp-none">No members yet</li>
            @endforelse
          </ul>
        </section>

        <section class="tp-sec" aria-labelledby="tp-exp">
          <h3 id="tp-exp">Experts</h3>
          {{-- All three seats, always: an empty one is information too. --}}
          <ul class="tp-ppl">
            @foreach ($experts as $seat)
              @if ($seat->expert)
                <li>
                  <span class="tp-av" style="background:{{ $avatar($seat->expert->expert_id) }}">{{ $initialsOf($seat->expert->name) }}@include('capsa.team-management._photo', ['url' => $seat->expert->avatarUrl()])</span>
                  <div class="who"><b>{{ $seat->expert->name }}</b><small>{{ ucfirst($seat->role) }}</small></div>
                </li>
              @else
                <li class="empty"><span class="tp-av seat">–</span><div class="who"><b>No {{ $seat->role }} yet</b></div></li>
              @endif
            @endforeach
          </ul>
          @unless ($titled)
            <p class="tp-note">The team can apply to an adviser once {{ $asCapsa ? 'you recommend' : 'the CaPSA recommends' }} one of its titles.</p>
          @endunless
        </section>

        @if ($panel)
          <section class="tp-sec" aria-labelledby="tp-pan">
            <h3 id="tp-pan">Panel</h3>
            <ul class="tp-ppl">
              @foreach ($panel->panelists as $panelist)
                <li>
                  <span class="tp-av" style="background:{{ $avatar($panelist->expert->expert_id) }}">{{ $initialsOf($panelist->expert->name) }}@include('capsa.team-management._photo', ['url' => $panelist->expert->avatarUrl()])</span>
                  <div class="who"><b>{{ $panelist->expert->name }}</b>
                    <small>@if ((int) $panelist->expert->expert_id === $viewerId)<span class="you">You</span> · @endif{{ $panelist->panel_role ?: 'Panelist' }}</small></div>
                </li>
              @endforeach
            </ul>
          </section>
        @endif

      </div>
    </div>

    <div class="tp-col">
      <section class="tp-panel" aria-labelledby="tp-ms-h">
        <div class="tp-panel-h">
          <h2 id="tp-ms-h">Progress</h2>
          <small>{{ $states->filter(fn ($w) => $w === 'Completed')->count() }} of {{ $milestones->count() }} milestones</small>
          <span class="tp-strip" aria-hidden="true">@foreach ($states as $word)<i class="t-{{ $toneOf($word) }}"></i>@endforeach</span>
        </div>

        @if ($milestones->isEmpty())
          <p class="tp-none" style="padding:14px 18px">This section has no checklist yet, so there is nothing to track.</p>
        @else
          <div class="tp-ms">
            <ol class="tp-nav" aria-label="Milestones">
              @foreach ($milestones as $i => $ms)
                @php $tone = $toneOf($states[$i]); @endphp
                <li class="t-{{ $tone }}">
                  <a href="{{ route($showRoute, [$team->team_id, 'milestone' => $i]) }}"
                     aria-current="{{ $i === $selectedIndex ? 'true' : 'false' }}"
                     aria-label="{{ $ms->milestone->name }}: {{ $states[$i] }}{{ $ms->locked ? '' : ", {$ms->completed} of {$ms->total} done" }}">
                    <span class="n">@if ($tone === 'd')<x-tm-icon n="check" s="11" bold />@elseif ($ms->locked)<x-tm-icon n="lock" s="11" />@else{{ $i + 1 }}@endif</span>
                    <span class="nm" title="{{ $ms->milestone->name }}">{{ $ms->milestone->name }}</span>
                  </a>
                </li>
              @endforeach
            </ol>

            <div class="tp-detail">
              <h3>{{ $selected->milestone->name }}</h3>
              <div class="sub">@if ($selected->due_on)Due {{ Carbon::parse($selected->due_on)->format('M j, Y') }} · @endif{{ $selected->completed }} of {{ $selected->total }} {{ Str::plural('requirement', $selected->total) }} done</div>

              @if ($selected->locked)
                {{-- One line for the whole milestone rather than a lock and a
                     red "missed" mark on every row: while it is locked the team
                     cannot hand anything in, so nothing here is theirs to miss. --}}
                <div class="tp-gate"><x-tm-icon n="lock" s="16" />
                  <span><b>{{ $selected->not_open ? 'Not open yet.' : 'Opens after '.$selected->requires.'.' }}</b> Until then the team can't hand anything in here.</span></div>
              @endif

              <ul class="tp-items">
                @foreach ($selected->items as $item)
                  @php
                    $short = ['done' => 'd', 'done_late' => 'l', 'handed_in' => 'h', 'overdue' => 'o'][$item->state] ?? 'n';
                    $word = ['d' => 'Done', 'l' => 'Done late', 'h' => 'Handed in', 'o' => 'Overdue', 'n' => 'Not started'][$short];
                    $note = $selected->not_open ? '' : MilestoneProgress::itemNote($item, $item->locked, $item->waits_for);
                    $steps = MilestoneProgress::steps($item->item->kind, $item->state, $item->waiting_on);
                    $stepsDone = collect($steps)->where('state', 'done')->count();
                  @endphp
                  <li class="tp-item{{ $item->locked ? ' lk' : '' }}">
                    <div class="tt"><b>{{ $item->item->title }}</b>@if ($note)<small>{{ $note }}</small>@endif
                    <ol class="tp-steps" aria-label="{{ $stepsDone }} of {{ count($steps) }} steps done">
                      @foreach ($steps as $s => $step)
                        @php
                          $dot = ['done' => 'y', 'current' => 'c', 'missed' => 'x'][$step['state']] ?? '';
                          // Locked: nothing could be handed in, so nothing was missed.
                          if ($item->locked && $dot === 'x') { $dot = ''; }
                        @endphp
                        @if ($s > 0)<li class="ar" aria-hidden="true">→</li>@endif
                        <li class="{{ $dot }}">@if ($dot === 'y')<x-tm-icon n="check" s="11" bold />@else○@endif {{ $step['label'] }}</li>
                      @endforeach
                    </ol>
                    </div>
                    @if ($item->locked && $item->state !== 'handed_in')
                      <span class="tp-state lk"><x-tm-icon n="lock" s="12" />{{ $selected->not_open ? 'Not open yet' : 'Locked' }}</span>
                    @elseif ($asCapsa && $item->waiting_on === MilestoneProgress::WAITING_ON_CAPSA && in_array($item->item->kind, \App\Models\Deliverable::CHECKED_BY_CAPSA, true))
                      {{-- A signed paper closes on the CaPSA's check, and the check
                           itself lives in the Milestones tracker. Once it is
                           checked, or put down, the tracker brings her back
                           here (return). --}}
                      <a class="btn btn-p" href="{{ route('capsa.team_management', [
                           'tab' => 'milestones', 'section' => $row->section,
                           'team' => $team->team_id, 'check' => $item->item->id,
                           'return' => route('capsa.team_management.show', $team->team_id, false),
                         ]) }}">Check</a>
                    @else
                      <span class="tp-state {{ $short }}">{{ $word }}</span>
                    @endif
                  </li>
                @endforeach
              </ul>
              <a class="tp-more" data-tp-more="{{ $selected->items->count() }}" hidden
                 href="{{ $portal === 'capsa' ? route('capsa.team_management', ['tab' => 'milestones', 'section' => $row->section, 'team' => $team->team_id]) : route('instructor.progress') }}">See all {{ $selected->items->count() }} requirements</a>
            </div>
          </div>
        @endif
      </section>

      {{-- One card under Progress. While the team has no title, its titles
           open first (what used to be the Check Pre-proposals page). After
           that, papers sent to her as Subject Adviser for the format pass;
           what she reviews as somebody's adviser stays in My Expert Duties. --}}
      <section class="tp-panel tp-grow" aria-label="{{ $titled ? 'Papers and activity' : 'Titles and activity' }}">
        <div class="tp-panel-h" style="padding:4px 12px">
          <div class="tp-tabs" role="group" aria-label="Show">
            @unless ($titled)
              <button type="button" data-tp-pane="tp-titles" aria-pressed="true">Titles <span>{{ $row->pendingProposalCount ? $row->pendingProposalCount.' waiting' : $row->proposals->count() }}</span></button>
            @endunless
            @if ($showPapers)
              <button type="button" data-tp-pane="tp-papers" aria-pressed="{{ $titled ? 'true' : 'false' }}">Papers <span>{{ $row->inSections && $portal === 'capsa' ? $row->papersToCheck : $row->reviewsToDo }} to check</span></button>
            @endif
            <button type="button" data-tp-pane="tp-activity" aria-pressed="false">Activity <span>{{ $activity->count() }}</span></button>
          </div>
        </div>
        @unless ($titled)
          <div class="tp-pane" id="tp-titles">
            <ul class="tp-slots">
              @forelse ($row->proposals->sortBy('slot') as $proposal)
                @php
                  $isPending = ! $proposal->approved_status && ! $proposal->rejection_reason;
                  [$stateWord, $stateTone] = $proposal->approved_status ? ['Recommended', 'ok'] : ($isPending ? ['Waiting', 'wait'] : ['Rejected', 'bad']);
                @endphp
                <li>
                  <span class="k">#{{ $proposal->slot }}</span>
                  <div style="min-width:0"><b>{{ $proposal->title }}</b>
                    <small>Closest match <x-tm-sim :score="$proposal->duplicationCheck?->top_score" /></small></div>
                  <span class="end"><span class="st {{ $stateTone }}">{{ $stateWord }}</span>
                    @if ($asCapsa)
                      <a class="btn {{ $isPending ? 'btn-p' : 'btn-o' }}" href="{{ route('capsa.team_management.title', [$team->team_id, $proposal->id]) }}">{{ $isPending ? 'Review' : 'Open' }}</a>
                    @endif</span>
                </li>
              @empty
                <li><span></span><p class="tp-none">No titles turned in yet.</p><span></span></li>
              @endforelse
            </ul>
          </div>
        @endunless
        @if ($showPapers)
          <div class="tp-pane" id="tp-papers"@unless ($titled) hidden @endunless>
            @if ($row->papers->isEmpty())
              <p class="tp-none">{{ $portal === 'capsa' && $row->inSections ? 'Nothing to check. Papers appear here when this team sends one to their Subject Adviser.' : 'Nothing sent to you yet. Papers appear here when this team sends you one to review.' }}</p>
            @else
              @include('capsa.team-management._papers', ['papers' => $row->papers->take(12), 'fill' => true])
              @if ($row->papers->count() > $shown)
                <a class="tp-more" href="{{ route($logRoute, [$team->team_id, 'papers']) }}">See all {{ $row->papers->count() }} papers</a>
              @endif
            @endif
          </div>
        @endif
        <div class="tp-pane" id="tp-activity" hidden>
          @include('capsa.team-management._feed', ['events' => $activity->take(12), 'fill' => true])
          @if ($activity->count() > $shown)
            <a class="tp-more" href="{{ route($logRoute, [$team->team_id, 'activity']) }}">See all {{ $activity->count() }} updates</a>
          @endif
        </div>
      </section>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
  // A milestone's requirements differ in height, so CSS cannot count how many
  // fit; draw the ones that fit whole and point the rest to the tracker.
  function tpFitItems() {
    document.querySelectorAll('.tp-items').forEach(function (list) {
      var more = list.parentNode.querySelector('[data-tp-more]');
      var rows = Array.prototype.slice.call(list.children);
      var clipping = window.matchMedia('(min-width:1101px)').matches;
      var cut = false;
      rows.forEach(function (row) { row.hidden = false; });
      rows.forEach(function (row) {
        cut = cut || (clipping && row.offsetTop + row.offsetHeight > list.clientHeight);
        row.hidden = cut;
      });
      if (more) { more.hidden = !cut; }
    });
  }
  tpFitItems();
  window.addEventListener('resize', tpFitItems);

  document.querySelectorAll('[data-tp-pane]').forEach(function (button) {
    button.addEventListener('click', function () {
      document.querySelectorAll('[data-tp-pane]').forEach(function (other) {
        var on = other === button;
        other.setAttribute('aria-pressed', on ? 'true' : 'false');
        document.getElementById(other.dataset.tpPane).hidden = !on;
      });
    });
  });
</script>
@endpush
