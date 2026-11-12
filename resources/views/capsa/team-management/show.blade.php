{{--
  One team, whole: its header, then four tabs - Requirements, Papers,
  Activity, Members & experts (and Titles first while it has none settled).

  On a desktop the page is the window: each tab's list scrolls inside its own
  box under a sticky header. Below that the tables turn into labelled cards.
--}}
@extends('capsa.team-management._shell')

@section('title', $row->team->team_name)

@push('head')
<style nonce="{{ Vite::cspNonce() }}">
  .tp{display:flex;flex-direction:column;gap:10px;min-width:0}
  .tp .vh{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}

  .tp-head{display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap}
  .tp-tav{width:46px;height:46px;border-radius:12px;flex:none;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;color:#fff}
  .tp-head .grow{flex:1;min-width:240px}
  .tp-head h1{margin:0;font-size:22px;font-weight:800;letter-spacing:-.4px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;line-height:1.2}
  .tp-meta{font-size:13px;color:var(--ink2);margin-top:2px;display:flex;gap:4px;white-space:nowrap;min-width:0}
  .tp-meta .t{overflow:hidden;text-overflow:ellipsis;min-width:0}
  .tp-meta a{color:var(--ink2);font-weight:600;text-decoration:underline;text-decoration-color:var(--line2);text-underline-offset:2px}
  .tp-facts{display:flex;flex-wrap:wrap;gap:6px 28px;margin:6px 0 0}
  .tp-facts dt{font-size:10.5px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--mut)}
  .tp-facts dd{margin:2px 0 0;font-size:13px;font-weight:600;color:var(--ink);font-variant-numeric:tabular-nums}
  .tp-facts dd.mono{font-family:'IBM Plex Mono',ui-monospace,monospace}
  .tp-acts{display:flex;gap:8px;flex-wrap:wrap}
  .tp .btn{box-shadow:none;padding:6px 12px;font-size:12.5px;border-radius:8px;white-space:nowrap}
  .tp-st{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:var(--mut);letter-spacing:0}
  .tp-st::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}
  .tp-st.ok{color:var(--green)} .tp-st.warn{color:#b45309}

  /* What on this team is waiting on her: the first thing, and how many more. */
  .tp-needs{display:flex;align-items:center;gap:14px;padding:8px 16px;border:1px solid #b45309;background:#fffbeb;border-radius:12px;flex-wrap:wrap}
  .tp-needs-h{display:flex;flex-direction:column;gap:3px;font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:#b45309;white-space:nowrap}
  .tp-needs-h a{font-size:12px;letter-spacing:0;text-transform:none;font-weight:600;color:var(--ink2);text-decoration:underline;text-underline-offset:2px}
  .tp-needs-ico{width:34px;height:34px;border-radius:9px;display:grid;place-items:center;background:#fef3c7;color:#b45309;flex:none}
  .tp-needs-what{flex:1;min-width:200px;display:flex;flex-direction:column;gap:2px;font-size:12.5px;color:var(--mut)}
  .tp-needs-what b{font-size:13.5px;color:var(--ink)}

  /* The tabbed card. */
  .tp-card{background:var(--card);border:1px solid var(--line);border-radius:12px;min-width:0;display:flex;flex-direction:column;overflow:hidden}
  .tp-tabs{display:flex;gap:22px;align-items:center;padding:0 16px;border-bottom:1px solid var(--line);overflow-x:auto;white-space:nowrap;flex:none}
  .tp-tabs button{border:0;border-bottom:2px solid transparent;background:none;padding:11px 0 9px;font:inherit;font-size:13px;font-weight:600;color:var(--mut);cursor:pointer;display:inline-flex;align-items:center;gap:6px}
  .tp-tabs button:hover{color:var(--ink)}
  .tp-tabs button[aria-selected="true"]{border-bottom-color:var(--gold,#c9a82c);color:var(--ink);font-weight:700}
  .tp-tabs button:focus-visible{outline:2px solid var(--gold,#c9a82c);outline-offset:2px;border-radius:4px}
  .tp-badge{font-size:10.5px;font-weight:700;min-width:18px;padding:1px 6px;border-radius:6px;background:var(--tint2);color:var(--ink2);text-align:center;font-variant-numeric:tabular-nums}
  .tp-badge.alert{background:var(--red-bg);color:var(--red)}
  .tp-pane{display:flex;flex-direction:column;min-height:0;flex:1}
  .tp-pane[hidden]{display:none}
  .tp-tools{display:flex;align-items:center;gap:10px 16px;padding:9px 16px;border-bottom:1px solid var(--line);flex-wrap:wrap;flex:none;font-size:12.5px;color:var(--ink2)}
  .tp-tools label{display:flex;align-items:center;gap:8px;font-weight:600;min-width:0;max-width:100%}
  .tp-tools .sel{padding:6px 10px;border-radius:8px;font-size:12.5px;max-width:100%;min-width:0}
  .tp-tools .grow{flex:1}
  .tp-done{display:flex;align-items:center;gap:9px;font-weight:600;white-space:nowrap;font-variant-numeric:tabular-nums}
  .tp-done .bar{width:110px}
  .tp-done .bar i{background:var(--green)}
  .tp-gate{display:flex;align-items:center;gap:8px;padding:8px 16px;border-bottom:1px solid var(--line);background:var(--tint);font-size:12.5px;color:var(--ink2);flex:none}
  .tp-foot{display:flex;gap:8px;padding:8px 16px;border-top:1px solid var(--line);font-size:12px;color:var(--mut);flex:none}

  /* Tables in their own scroll box, header pinned. */
  .tp-scroll{overflow:auto;min-height:0;flex:1;overscroll-behavior:contain}
  .tp-scroll:focus-visible{outline:2px solid var(--gold,#c9a82c);outline-offset:-2px}
  .tp table{width:100%;min-width:0;border-collapse:separate;border-spacing:0;table-layout:fixed}
  .tp th{position:sticky;top:0;z-index:1;background:#18191f;color:#d4d6de;font-size:10.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;padding:9px 14px;text-align:left;border:0;white-space:nowrap}
  .tp td{padding:9px 14px;border-bottom:1px solid var(--line);font-size:13px;color:var(--ink);vertical-align:middle;overflow-wrap:anywhere}
  .tp tbody tr{cursor:default}
  .tp tbody tr:hover td{background:var(--tint)}
  .tp tbody tr:last-child td{border-bottom:0}
  .tp td small{display:block;font-size:11.5px;color:var(--mut);margin-top:2px}
  .tp td .late{color:#b45309;font-weight:600}
  .tp td .fb{color:var(--red)}
  .tp .c-act{text-align:right}
  .tp .doc{display:flex;gap:10px;align-items:center;min-width:0}
  .tp .doc > span:first-child{flex:none;display:grid;place-items:center;width:30px;height:30px;border-radius:8px}
  .tp .doc b{font-weight:600;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .tp .doc > div{min-width:0}
  .tp .k-document{background:#eff6ff;color:#1d4ed8}
  .tp .k-form,.tp .k-custom{background:#ecfeff;color:#0e7490}
  .tp .k-approval{background:#f5f3ff;color:#6d28d9}
  .tp .k-defense{background:#fff7ed;color:#c2410c}
  .tp .k-bluebook{background:#eef2ff;color:#4338ca}
  .tp .ic-pdf{background:#fdecec;color:#c42b2b;font-size:9px;font-weight:800;letter-spacing:.3px}
  .tp .ic-lock{background:var(--tint2);color:var(--mut)}
  .tp-none{font-size:12.5px;color:var(--mut);margin:0;padding:14px 16px}

  /* Titles, while the team has none settled. */
  .tp-slots{list-style:none;margin:0;padding:0}
  .tp-slots li{display:grid;grid-template-columns:28px minmax(0,1fr) auto;gap:12px;align-items:center;padding:10px 16px;border-top:1px solid var(--line)}
  .tp-slots li:first-child{border-top:0}
  .tp-slots .k{font-size:12px;color:var(--mut);font-variant-numeric:tabular-nums}
  .tp-slots b{font-size:13.5px;display:block;color:var(--ink)}
  .tp-slots small{font-size:12px;color:var(--mut);display:flex;align-items:center;gap:6px;margin-top:3px}
  .tp-slots .end{display:flex;gap:8px;align-items:center}

  /* Activity: date headings, one line per event, an icon by kind. */
  .tp-feedx{list-style:none;margin:0;padding:4px 16px 10px}
  .tp-day{position:sticky;top:0;z-index:1;margin:6px 0 2px;padding:6px 10px;background:var(--tint2);border-radius:6px;font-size:10.5px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--ink2)}
  .tp-ev{display:flex;gap:12px;align-items:flex-start;padding:9px 6px;border-bottom:1px solid var(--line)}
  .tp-ev:last-child{border-bottom:0}
  .tp-ev .ei{flex:none;width:26px;height:26px;border-radius:50%;display:grid;place-items:center}
  .tp-ev .ev-sent{background:#e8f0fe;color:#2563eb}
  .tp-ev .ev-checked{background:#e7f6ec;color:#15803d}
  .tp-ev .ev-returned{background:#fdecec;color:#c42b2b}
  .tp-ev .ev-title{background:#f3e8ff;color:#7c3aed}
  .tp-ev .ev-defense{background:#fef3c7;color:#b45309}
  .tp-ev .ev-formed{background:#e0f2f1;color:#0f766e}
  .tp-ev .tx{flex:1;min-width:0;font-size:13px;color:var(--ink2)}
  .tp-ev .tx b{color:var(--ink);font-weight:600}
  .tp-ev time{flex:none;font-size:11.5px;color:var(--mut);font-variant-numeric:tabular-nums}
  .tp-ev blockquote{margin:6px 0 0;padding-left:10px;border-left:3px solid var(--line2);font-size:12.5px;color:var(--mut)}

  /* Members & experts. */
  .tp-people{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:0;flex:1;min-height:0}
  .tp-people > section{display:flex;flex-direction:column;min-height:0}
  .tp-people > section + section{border-left:1px solid var(--line)}
  .tp-people h3{margin:0;padding:10px 16px;font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--mut);display:flex;justify-content:space-between;flex:none}
  .tp-av{width:30px;height:30px;border-radius:50%;flex:none;position:relative;overflow:hidden;display:grid;place-items:center;background:var(--tint2);color:var(--ink2);font-size:11px;font-weight:700}
  .tp-av img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
  .tp-person{display:flex;gap:10px;align-items:center;min-width:0}
  .tp-person b{font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .tp-cards{display:flex;flex-direction:column;gap:8px;padding:0 16px 12px}
  .tp-xcard{display:flex;gap:12px;align-items:center;padding:10px 12px;border:1px solid var(--line);border-radius:9px;min-width:0}
  .tp-xcard .tp-av{width:36px;height:36px}
  .tp-xcard > div{min-width:0;flex:1}
  .tp-xcard small{display:block;font-size:11px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:var(--mut)}
  .tp-xcard b{display:block;font-size:13.5px;font-weight:600;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .tp-xcard span.d{display:block;font-size:12px;color:var(--mut);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .tp-xcard.empty{border-style:dashed}
  .tp-xcard.empty .tp-av{background:none;border:1.5px dashed var(--line2)}
  .tp-xcard.empty b{color:var(--mut);font-weight:500}
  .tp-xcard .you{color:var(--accent3);font-weight:700}

  /* Desktop: the page is the window, the open tab's list scrolls inside. */
  @media (min-width:1101px){
    .content:has(.tp){display:flex;flex-direction:column;overflow:hidden}
    .content:has(.tp) > .tmx{flex:1;min-height:0;display:flex;flex-direction:column;padding-block:16px}
    .tp{flex:1;min-height:0}
    .tp-card{flex:1;min-height:0}
  }
  @media (max-width:1100px){
    .tp-scroll{overflow:visible}
    .tp-people{grid-template-columns:minmax(0,1fr)}
    .tp-meta{white-space:normal;flex-wrap:wrap}
    .tp-people > section + section{border-left:0;border-top:1px solid var(--line)}
  }
  /* Tablet and phone: each row becomes a labelled card. */
  @media (max-width:820px){
    .tp thead{display:none}
    .tp table,.tp tbody,.tp tr,.tp td{display:block}
    .tp tbody tr{padding:10px 16px;border-bottom:1px solid var(--line)}
    .tp tbody tr:last-child{border-bottom:0}
    .tp td{border:0;padding:3px 0}
    .tp td[data-label]{display:flex;align-items:baseline;gap:10px;flex-wrap:wrap}
    .tp td[data-label]::before{content:attr(data-label);flex:0 0 96px;font-size:10.5px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--mut)}
    .tp td.c-first{display:block;padding-bottom:6px}
    .tp td.c-first::before{display:none}
    .tp .c-act{text-align:left;margin-top:6px}
    .tp tbody tr:hover td{background:none}
    .tp-ev time{display:none}
  }
</style>
@endpush

@section('tm-content')
@php
  use App\Models\CapstoneDocumentReview;
  use App\Support\MilestoneProgress;
  use Illuminate\Support\Carbon;
  use Illuminate\Support\Str;

  $team = $row->team;
  $portal = $portal ?? 'capsa';
  // Shown under a photo, and in its place when there is none.
  $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))
      ->reject(fn ($w) => $w === '' || str_ends_with($w, '.'))->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
      ->pipe(fn ($c) => $c->count() > 1 ? $c->first().$c->last() : $c->implode(''));
  // What she does here as CaPSA/TSA - titles, verifying, checks - only on a team
  // in her sections; on a team she only advises, the page is an expert's.
  $asCapsa = $portal === 'capsa' && $row->inSections;
  $showRoute = $portal === 'capsa' ? 'capsa.team_management.show' : 'instructor.teams.show';
  $hubRoute = $portal === 'capsa' ? 'capsa.team_management' : 'instructor.teams';
  $maxMembers = $team->memberLimit();

  // The panel's average at the latest stage they have actually been graded on.
  $score = $row->seesScores ? $defenseScores->get($team->team_id, collect())->last(fn ($s) => $s->average !== null) : null;
  $titled = (bool) $team->project_title;

  // Next: the coming defense if one is set, else the first milestone not done.
  $nextDefense = $defenseSchedules->first(fn ($s) => $s->schedule_date && $s->schedule_date->isFuture());
  $states = $milestones->map(fn ($ms) => MilestoneProgress::milestoneState($ms)[0]);
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

  // Papers: the ones still to check first, newest first within each.
  $papers = $row->papers->sortBy([
      fn ($a, $b) => ($a->review_status === 'Reviewed') <=> ($b->review_status === 'Reviewed'),
      fn ($a, $b) => strcmp((string) $b->submitted_at, (string) $a->submitted_at),
  ])->values();
  $papersWaiting = $papers->where('review_status', '!=', 'Reviewed')->count();
  $paperMilestones = $papers->pluck('milestone_name')->filter()->unique()->values();

  // Activity grouped by day, newest first.
  $days = $activity->groupBy(fn ($e) => $e->at->toDateString());
  $dayLabel = fn ($d) => Carbon::parse($d)->isToday() ? 'Today' : (Carbon::parse($d)->isYesterday() ? 'Yesterday' : Carbon::parse($d)->format('l, M j, Y'));
  $eventIcon = ['sent' => 'file', 'checked' => 'check', 'returned' => 'back', 'title' => 'flag', 'defense' => 'cap', 'formed' => 'users'];

  $tab = $titled ? 'requirements' : 'titles';
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
    @php
      $first = $waiting->first();
      $needIcons = ['title' => 'flag', 'verify' => 'shield', 'paper' => 'file', 'signed' => 'star', 'review' => 'file'];
    @endphp
    <div class="tp-needs">
      <div class="tp-needs-h">Waiting on you
        @if ($waiting->count() > 1)
          <a href="{{ route($hubRoute, ['tab' => 'todo']) }}">+{{ $waiting->count() - 1 }} more in To do</a>
        @endif
      </div>
      <span class="tp-needs-ico"><x-tm-icon :n="$needIcons[$first->kind] ?? 'bang'" s="18" /></span>
      <div class="tp-needs-what"><b>{{ $first->what }}</b><span>{{ $first->why }}</span></div>
      <a class="btn btn-p" href="{{ $first->url }}">{{ $first->label }}</a>
    </div>
  @endif

  <section class="tp-card" aria-label="Team details">
    <div class="tp-tabs" role="tablist" aria-label="Team details">
      @unless ($titled)
        <button type="button" role="tab" id="tt-titles" aria-controls="tp-titles" data-tp-tab="titles">Titles
          <span @class(['tp-badge', 'alert' => $row->pendingProposalCount])>{{ $row->pendingProposalCount ?: $row->proposals->count() }}</span></button>
      @endunless
      <button type="button" role="tab" id="tt-requirements" aria-controls="tp-requirements" data-tp-tab="requirements">Requirements</button>
      <button type="button" role="tab" id="tt-papers" aria-controls="tp-papers" data-tp-tab="papers">Papers
        <span @class(['tp-badge', 'alert' => $papersWaiting]) aria-label="{{ $papersWaiting }} to check">{{ $papersWaiting }}</span></button>
      <button type="button" role="tab" id="tt-activity" aria-controls="tp-activity" data-tp-tab="activity">Activity
        <span class="tp-badge">{{ $activity->count() }}</span></button>
      <button type="button" role="tab" id="tt-people" aria-controls="tp-people" data-tp-tab="people">Members &amp; experts</button>
    </div>

    {{-- Titles --}}
    @unless ($titled)
      <div class="tp-pane" id="tp-titles" role="tabpanel" aria-labelledby="tt-titles" hidden>
        <div class="tp-scroll" tabindex="0">
          <ul class="tp-slots">
            @forelse ($row->proposals->sortBy('slot') as $proposal)
              @php
                $isPending = ! $proposal->approved_status && ! $proposal->rejection_reason;
                [$stateWord, $stateTone] = $proposal->approved_status ? ['Recommended', 'green'] : ($isPending ? ['Waiting', 'amber'] : ['Rejected', 'red']);
              @endphp
              <li>
                <span class="k">#{{ $proposal->slot }}</span>
                <div style="min-width:0"><b>{{ $proposal->title }}</b>
                  <small>Closest match <x-tm-sim :score="$proposal->duplicationCheck?->top_score" /></small></div>
                <span class="end"><span class="pill p-{{ $stateTone }}">{{ $stateWord }}</span>
                  @if ($asCapsa)
                    <a class="btn {{ $isPending ? 'btn-p' : 'btn-o' }}" href="{{ route('capsa.team_management.title', [$team->team_id, $proposal->id]) }}">{{ $isPending ? 'Review' : 'Open' }}</a>
                  @endif</span>
              </li>
            @empty
              <li><span></span><p class="tp-none" style="padding:0">No titles turned in yet.</p><span></span></li>
            @endforelse
          </ul>
        </div>
      </div>
    @endunless

    {{-- Requirements --}}
    <div class="tp-pane" id="tp-requirements" role="tabpanel" aria-labelledby="tt-requirements" hidden>
      @if ($milestones->isEmpty())
        <p class="tp-none">This section has no checklist yet.</p>
      @else
        @php $checked = $selected->items->whereIn('state', MilestoneProgress::COMPLETE_STATES)->count(); @endphp
        <div class="tp-tools">
          <label for="tp-ms">Milestone
            <select class="sel" id="tp-ms">
              @foreach ($milestones as $i => $ms)
                <option value="{{ route($showRoute, [$team->team_id, 'milestone' => $i]) }}#requirements" @selected($i === $selectedIndex)>{{ $i + 1 }}. {{ $ms->milestone->name }}{{ $ms->locked ? ' · Locked' : '' }}</option>
              @endforeach
            </select>
          </label>
          @if ($selected->due_on)<span>Due {{ Carbon::parse($selected->due_on)->format('M j, Y') }}</span>@endif
          <span class="grow"></span>
          <span class="tp-done">{{ $checked }} / {{ $selected->total }} checked
            <span class="bar" role="img" aria-label="{{ $checked }} of {{ $selected->total }} checked"><i style="width:{{ $selected->total ? round($checked / $selected->total * 100) : 0 }}%"></i></span></span>
        </div>
        @if ($selected->locked)
          <div class="tp-gate"><x-tm-icon n="lock" s="15" />
            <span>{{ $selected->not_open ? 'Not open yet.' : 'Opens after '.$selected->requires.'.' }}</span></div>
        @endif
        <div class="tp-scroll" tabindex="0" aria-label="Requirements">
          <table>
            <colgroup><col style="width:36%"><col style="width:19%"><col style="width:14%"><col style="width:17%"><col style="width:14%"></colgroup>
            <thead><tr><th>Requirement</th><th>Submission</th><th>Due</th><th>Review</th><th class="c-act"><span class="vh">Action</span></th></tr></thead>
            <tbody>
              @forelse ($selected->items as $item)
                @php
                  $locked = $item->locked && $item->state !== 'handed_in';
                  [$pill, $tone] = match (true) {
                      $locked => [$selected->not_open ? 'Not open yet' : 'Locked', 'gray'],
                      in_array($item->state, ['done', 'done_late'], true) => ['Checked', 'green'],
                      $item->state === 'handed_in' && $item->changes_requested => ['Returned', 'red'],
                      $item->state === 'handed_in' => ['To check', 'amber'],
                      $item->state === 'overdue' => ['Overdue', 'red'],
                      default => ['Not submitted', 'gray'],
                  };
                  $canCheck = $asCapsa && $item->waiting_on === MilestoneProgress::WAITING_ON_CAPSA
                      && in_array($item->item->kind, \App\Models\Deliverable::CLOSED_BY_CAPSA, true);
                @endphp
                <tr>
                  <td class="c-first" data-label="Requirement"><div class="doc">
                    <span class="{{ $locked ? 'ic-lock' : 'k-'.$item->item->kind }}" aria-hidden="true"><x-tm-icon :n="$locked ? 'lock' : \App\Http\Controllers\Capsa\MilestonePageController::iconOf($item->item)" s="15" /></span>
                    <div><b title="{{ $item->item->title }}">{{ $item->item->title }}</b>
                      @if ($item->note && $item->changes_requested)<small class="fb">{{ $item->note }}</small>@endif</div>
                  </div></td>
                  <td data-label="Submission">
                    @if ($item->submitted_at)
                      {{ $item->submitted_at->format('M j, Y') }}
                      @if ($item->state === 'done_late' || ($item->days_late ?? 0) > 0)<small class="late">Submitted late</small>@endif
                    @else
                      <span class="muted">Not submitted</span>
                    @endif
                  </td>
                  <td data-label="Due">{{ $item->due_on ? Carbon::parse($item->due_on)->format('M j, Y') : '—' }}</td>
                  <td data-label="Review"><span class="pill p-{{ $tone }}">{{ $pill }}</span></td>
                  <td class="c-act">
                    @if ($canCheck)
                      {{-- The check itself lives in the Milestones tracker; it brings her back here. --}}
                      <a class="btn btn-p" href="{{ route('capsa.team_management', [
                           'tab' => 'milestones', 'section' => $row->section,
                           'team' => $team->team_id, 'check' => $item->item->id,
                           'return' => route('capsa.team_management.show', $team->team_id, false),
                         ]) }}">Check</a>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="tp-none">No requirements in this milestone.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      @endif
    </div>

    {{-- Papers --}}
    <div class="tp-pane" id="tp-papers" role="tabpanel" aria-labelledby="tt-papers" hidden>
      @if ($papers->isEmpty())
        <p class="tp-none">{{ $asCapsa ? 'No papers sent for checking yet.' : 'No papers sent to you yet.' }}</p>
      @else
        @if ($paperMilestones->count() > 1)
          <div class="tp-tools">
            <label for="tp-pms">Milestone
              <select class="sel" id="tp-pms">
                <option value="">All milestones</option>
                @foreach ($paperMilestones as $name)<option>{{ $name }}</option>@endforeach
              </select>
            </label>
          </div>
        @endif
        <div class="tp-scroll" tabindex="0" aria-label="Papers">
          <table>
            <colgroup><col style="width:38%"><col style="width:20%"><col style="width:14%"><col style="width:14%"><col style="width:14%"></colgroup>
            <thead><tr><th>Document</th><th>Milestone</th><th>Submitted</th><th>Review</th><th class="c-act"><span class="vh">Action</span></th></tr></thead>
            <tbody>
              @foreach ($papers as $paper)
                @php
                  $reviewed = $paper->review_status === 'Reviewed';
                  [$pill, $tone] = match (true) {
                      $reviewed && $paper->verdict === CapstoneDocumentReview::NEEDS_REVISION => ['Returned', 'red'],
                      $reviewed => ['Checked', 'green'],
                      $paper->review_status === 'Reviewing' => ['In progress', 'blue'],
                      default => ['To check', 'amber'],
                  };
                @endphp
                <tr data-ms="{{ $paper->milestone_name }}">
                  <td class="c-first" data-label="Document"><div class="doc">
                    <span class="ic-pdf" aria-hidden="true">PDF</span>
                    <div><b title="{{ $paper->file_name }}">{{ $paper->file_name }}</b>
                      <small>Version {{ $paper->file_version }}{{ $paper->chapter ? ' · '.$paper->chapter : '' }}</small></div>
                  </div></td>
                  <td data-label="Milestone">{{ $paper->milestone_name ?: '—' }}</td>
                  <td data-label="Submitted">{{ $paper->submitted_at ? Carbon::parse($paper->submitted_at)->format('M j, Y') : '—' }}</td>
                  <td data-label="Review"><span class="pill p-{{ $tone }}">{{ $pill }}</span></td>
                  <td class="c-act"><a class="btn {{ $reviewed ? 'btn-o' : 'btn-p' }}" href="{{ route('instructor.docreview.annotate', $paper->review_id) }}">{{ $reviewed ? 'View' : 'Review' }}</a></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="tp-foot"><span data-tp-pcount>{{ $papers->count() }} {{ Str::plural('file', $papers->count()) }}</span>·<span>{{ $papersWaiting }} to check</span></div>
      @endif
    </div>

    {{-- Activity --}}
    <div class="tp-pane" id="tp-activity" role="tabpanel" aria-labelledby="tt-activity" hidden>
      @if ($activity->isEmpty())
        <p class="tp-none">Nothing yet.</p>
      @else
        <div class="tp-scroll" tabindex="0" aria-label="Activity">
          <ul class="tp-feedx">
            @foreach ($days as $day => $events)
              <li class="tp-day">{{ $dayLabel($day) }}</li>
              @foreach ($events as $event)
                <li class="tp-ev">
                  <span class="ei ev-{{ $event->kind ?? 'sent' }}" aria-hidden="true"><x-tm-icon :n="$eventIcon[$event->kind ?? 'sent'] ?? 'dot'" s="13" bold /></span>
                  <div class="tx"><b>{{ $event->who }}</b> {{ $event->did }}
                    @if (! empty($event->note))<blockquote>{{ $event->note }}</blockquote>@endif</div>
                  @if ($event->at->format('H:i:s') !== '00:00:00')<time datetime="{{ $event->at->toIso8601String() }}">{{ $event->at->format('g:i A') }}</time>@endif
                </li>
              @endforeach
            @endforeach
          </ul>
        </div>
      @endif
    </div>

    {{-- Members & experts --}}
    <div class="tp-pane" id="tp-people" role="tabpanel" aria-labelledby="tt-people" hidden>
      <div class="tp-people">
        <section aria-labelledby="tp-mem">
          <h3 id="tp-mem">Members <span>{{ $row->members->count() }} of {{ $maxMembers }}</span></h3>
          <div class="tp-scroll" tabindex="0" aria-label="Members">
            <table>
              <colgroup><col style="width:52%"><col style="width:26%"><col style="width:22%"></colgroup>
              <thead><tr><th>Name</th><th>Student ID</th><th>Role</th></tr></thead>
              <tbody>
                @forelse ($row->members->sortByDesc(fn ($m) => (int) $m->student_id === (int) $team->leader_id) as $member)
                  @php
                    $name = trim(($member->f_name ?? '').' '.($member->l_name ?? ''));
                    $leads = (int) $member->student_id === (int) $team->leader_id;
                  @endphp
                  <tr>
                    <td class="c-first" data-label="Name"><div class="tp-person">
                      <span class="tp-av">{{ $initials($name) }}@include('capsa.team-management._photo', ['url' => $member->avatarUrl()])</span>
                      <b>{{ $name ?: 'Unnamed student' }}</b></div></td>
                    <td data-label="Student ID">{{ $member->student_number ?: '—' }}</td>
                    <td data-label="Role"><span class="pill {{ $leads ? 'p-gold' : 'p-gray' }}">{{ $leads ? 'Leader' : 'Member' }}</span></td>
                  </tr>
                @empty
                  <tr><td colspan="3" class="tp-none">No members yet.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </section>

        <section aria-labelledby="tp-exp">
          <h3 id="tp-exp">Experts</h3>
          <div class="tp-scroll" tabindex="0" aria-label="Experts">
            <div class="tp-cards">
              {{-- All three seats, always: an empty one is information too. --}}
              @foreach ($experts as $seat)
                <div @class(['tp-xcard', 'empty' => ! $seat->expert])>
                  <span class="tp-av">{{ $initials($seat->expert?->name) }}@if ($seat->expert)@include('capsa.team-management._photo', ['url' => $seat->expert->avatarUrl()])@endif</span>
                  <div><small>{{ ucfirst($seat->role) }}</small>
                    <b>{{ $seat->expert?->name ?? 'Not assigned' }}</b>
                    @if ($seat->degree)<span class="d">{{ $seat->degree }}</span>@endif</div>
                </div>
              @endforeach
              @if ($panel)
                @foreach ($panel->panelists as $panelist)
                  <div class="tp-xcard">
                    <span class="tp-av">{{ $initials($panelist->expert->name) }}@include('capsa.team-management._photo', ['url' => $panelist->expert->avatarUrl()])</span>
                    <div><small>@if ((int) $panelist->expert->expert_id === $viewerId)<span class="you">You</span> · @endif{{ $panelist->panel_role ?: 'Panelist' }}</small>
                      <b>{{ $panelist->expert->name }}</b></div>
                  </div>
                @endforeach
              @endif
            </div>
          </div>
        </section>
      </div>
    </div>
  </section>
</div>
@endsection

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
  (function () {
    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-tp-tab]'));
    function open(name, focus) {
      var hit = tabs.filter(function (t) { return t.dataset.tpTab === name; })[0] || tabs[0];
      tabs.forEach(function (t) {
        var on = t === hit;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
        document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
      });
      if (focus) hit.focus();
      history.replaceState(null, '', '#' + hit.dataset.tpTab);
    }
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { open(t.dataset.tpTab); });
      t.addEventListener('keydown', function (e) {
        var step = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
        if (step) { e.preventDefault(); open(tabs[(i + step + tabs.length) % tabs.length].dataset.tpTab, true); }
      });
    });
    open(location.hash.slice(1) || @json($tab));
    window.addEventListener('hashchange', function () { open(location.hash.slice(1)); });

    var ms = document.getElementById('tp-ms');
    if (ms) ms.addEventListener('change', function () { location.href = ms.value; });

    var pms = document.getElementById('tp-pms');
    if (pms) pms.addEventListener('change', function () {
      var rows = document.querySelectorAll('#tp-papers tbody tr'), n = 0;
      rows.forEach(function (r) { r.hidden = !!pms.value && r.dataset.ms !== pms.value; if (!r.hidden) n++; });
      document.querySelector('[data-tp-pcount]').textContent = n + (n === 1 ? ' file' : ' files');
    });
  })();
</script>
@endpush
