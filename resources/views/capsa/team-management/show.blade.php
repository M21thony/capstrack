{{--
  One team, whole. Everything the seven tabs used to spread out - who is in
  it, which experts accepted, and how far along every milestone is - on the
  page you land on by clicking the team's row.

  On a desktop it fits the window: the page itself never scrolls. The team
  column and the milestone list each keep to their own panel, and only a
  panel whose content is taller than the window scrolls inside itself.
--}}
@extends('capsa.team-management._shell')

@section('title', $row->team->team_name)

@push('head')
<style>
  .tp{display:flex;flex-direction:column;gap:16px}
  .tp-head{display:flex;align-items:flex-end;gap:20px;flex-wrap:wrap}
  .tp-head-text{flex:1;min-width:260px;display:flex;flex-direction:column;gap:5px}
  .tp-name{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
  .tp-name h1{margin:0;font-size:26px;font-weight:800;letter-spacing:-.4px;line-height:1.15}
  .tp-meta{font-size:13.5px;color:var(--ink2)}
  .tp-meta .sep{color:var(--line2);margin:0 6px}
  .tp-head .btn{height:42px;padding:0 16px}

  .tp-grid{display:grid;grid-template-columns:340px minmax(0,1fr);gap:16px;align-items:start}
  .tp-panel{background:var(--card);border:1px solid var(--line);border-radius:14px}

  /* The team: members, experts, papers - one card, rows not boxes. */
  .tp-side{padding:2px 18px}
  .tp-sec{padding:14px 0;border-bottom:1px solid var(--line)}
  .tp-sec:last-child{border-bottom:none}
  .tp-sec-h{display:flex;align-items:baseline;justify-content:space-between;margin:0 0 10px}
  .tp-sec-h h2{margin:0;font-size:13.5px;font-weight:700;color:var(--ink)}
  .tp-sec-h span{font-size:12px;color:var(--mut);font-variant-numeric:tabular-nums}
  .tp-av{width:30px;height:30px;border-radius:50%;flex:none;position:relative;overflow:hidden;
         display:flex;align-items:center;justify-content:center;font-size:11.5px;font-weight:700;color:#fff}
  .tp-av img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
  .tp-people{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:9px}
  .tp-people li{display:flex;align-items:center;gap:10px;min-width:0}
  .tp-people b{flex:1;min-width:0;font-size:13.5px;font-weight:600;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .tp-lead{font-size:11.5px;font-weight:700;color:var(--accent3)}
  .tp-expert{display:flex;gap:10px;align-items:flex-start}
  .tp-expert + .tp-expert{margin-top:12px}
  .tp-expert-body{min-width:0;display:flex;flex-direction:column;gap:2px;font-size:12.5px;color:var(--ink2)}
  .tp-expert-body b{font-size:13.5px;font-weight:600;color:var(--ink)}
  .tp-expert-body .fields{color:var(--mut)}
  .tp-expert-body a{color:var(--accent3);word-break:break-all}
  .tp-seats{display:flex;flex-direction:column;gap:5px;margin-top:12px;font-size:12.5px;color:var(--mut)}
  .tp-seats div{display:flex;justify-content:space-between;gap:10px}
  .tp-papers{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px}
  .tp-papers li{display:flex;align-items:center;gap:10px}
  .tp-papers .fn{flex:1;min-width:0;display:flex;flex-direction:column;gap:1px;font-size:13px;font-weight:600;color:var(--ink);word-break:break-word}
  .tp-papers small{font-size:11.5px;font-weight:500;color:var(--mut)}
  .tp-papers .ok{color:var(--green);font-weight:600}
  .tp-papers .due{color:var(--amber);font-weight:600}
  .tp-papers .btn{flex:none;padding:6px 12px}
  .tp-none{font-size:12.5px;color:var(--mut)}

  /* Milestones: a numbered list on the left, the chosen one on the right. */
  .tp-ms{display:flex;flex-direction:column;min-height:0}
  .tp-ms-h{display:flex;align-items:center;gap:14px;padding:14px 20px;border-bottom:1px solid var(--line)}
  .tp-ms-h h2{margin:0;flex:1;font-size:15px;font-weight:700}
  .tp-ms-h .cnt{font-size:12.5px;color:var(--ink2);font-variant-numeric:tabular-nums}
  .tp-bar{width:120px;height:6px;border-radius:99px;background:var(--tint2);overflow:hidden}
  .tp-bar i{display:block;height:100%;border-radius:99px;background:var(--green)}
  .tp-ms-body{display:grid;grid-template-columns:240px minmax(0,1fr);min-height:0}
  .tp-nav{list-style:none;margin:0;padding:8px;border-right:1px solid var(--line)}
  .tp-nav a{display:flex;align-items:center;gap:9px;min-height:33px;padding:3px 9px;border-radius:8px;
            font-size:13px;font-weight:500;color:var(--ink2);text-decoration:none}
  .tp-nav a:hover{background:var(--tint)}
  .tp-nav a[aria-current="true"]{background:var(--accent-bg);color:var(--ink);font-weight:700}
  .tp-nav .n{width:22px;height:22px;flex:none;border-radius:50%;display:flex;align-items:center;justify-content:center;
             font-size:11px;font-weight:700;background:var(--tint2);color:var(--mut)}
  .tp-nav .t-d .n{background:var(--green);color:#fff}
  .tp-nav .t-o .n{background:var(--red);color:#fff}
  .tp-nav .t-p .n{background:var(--ink);color:#fff}
  .tp-nav a[aria-current="true"] .n{background:var(--accent);color:#18191f}
  .tp-nav .nm{flex:1;min-width:0;line-height:1.3}
  .tp-nav .mt{display:inline-flex;align-items:center;font-size:11.5px;color:var(--mut);font-variant-numeric:tabular-nums}

  .tp-detail{padding:16px 22px;min-width:0}
  .tp-detail-h{display:flex;align-items:baseline;gap:12px;flex-wrap:wrap}
  .tp-detail-h h3{margin:0;font-size:18px;font-weight:700}
  .tp-detail-h span{font-size:13px;color:var(--mut)}
  .tp-gate{display:flex;align-items:center;gap:10px;margin:12px 0 4px;padding:11px 14px;border-radius:10px;
           background:var(--tint);font-size:13px;color:var(--ink2)}
  .tp-gate b{color:var(--ink)}
  .tp-items{list-style:none;margin:6px 0 0;padding:0}
  .tp-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:16px;align-items:start;
           padding:9px 0;border-bottom:1px solid var(--line)}
  .tp-item:last-child{border-bottom:none}
  .tp-item .tt{min-width:0;display:flex;flex-direction:column;gap:2px}
  .tp-item .tt b{font-size:14px;font-weight:600;color:var(--ink)}
  .tp-item .tt small{font-size:12px;color:var(--mut)}
  .tp-item.lk .tt b{color:var(--ink2)}
  .tp-steps{list-style:none;margin:5px 0 0;padding:0;display:flex;align-items:center;gap:4px;flex-wrap:wrap}
  .tp-steps li{display:inline-flex;align-items:center;gap:4px;height:24px;padding:0 9px;border-radius:99px;
               font-size:11.5px;font-weight:600;white-space:nowrap;border:1.5px solid var(--line);color:var(--mut);background:var(--card)}
  .tp-steps li + li::before{content:"";width:0}
  .tp-steps .y{border-color:transparent;background:var(--green-bg);color:var(--green)}
  .tp-steps .c{border-color:var(--accent);background:var(--accent-bg);color:var(--accent3)}
  .tp-steps .x{border-color:rgba(220,38,38,.45);color:var(--red)}
  .tp-steps .ar{border:none;background:none;padding:0;height:auto;color:var(--line2)}
  .tp-state{justify-self:end;display:inline-flex;align-items:center;gap:5px;margin-top:2px;font-size:12.5px;font-weight:600;white-space:nowrap}
  .tp-state.d{color:var(--green)}
  .tp-state.l{color:var(--green)}
  .tp-state.h{color:var(--blue)}
  .tp-state.o{color:var(--red)}
  .tp-state.n,.tp-state.lk{color:var(--mut)}
  .tp-item .btn{justify-self:end;padding:6px 14px}
  .tp-sched{display:flex;align-items:center;gap:10px;padding:12px 22px;border-top:1px solid var(--line);
            font-size:13px;color:var(--ink2);flex:none}
  .tp-sched b{color:var(--ink)}

  /* Desktop: the page is exactly the window. Each panel keeps its own
     scroll, so nothing below the fold has to be scrolled to. */
  @media (min-width:1101px) and (min-height:640px){
    .content:has(.tp){overflow:hidden;display:flex;flex-direction:column}
    .content:has(.tp) > .tmx{flex:1;min-height:0;display:flex;flex-direction:column}
    .tp{flex:1;min-height:0}
    .tp-grid{flex:1;min-height:0;align-items:stretch}
    .tp-side,.tp-nav,.tp-detail{overflow-y:auto;scrollbar-width:thin}
    .tp-side{max-height:100%;align-self:start}
    .tp-ms{max-height:100%;overflow:hidden}
    .tp-ms-body{flex:1;min-height:0}
  }
  @media (max-width:1100px){
    .tp-grid{grid-template-columns:minmax(0,1fr)}
  }
  @media (max-width:700px){
    .tp-ms-body{grid-template-columns:minmax(0,1fr)}
    .tp-nav{border-right:none;border-bottom:1px solid var(--line);display:flex;gap:4px;overflow-x:auto}
    .tp-nav li{flex:none}
    .tp-nav .nm{white-space:nowrap}
  }
</style>
@endpush

@section('tm-content')
@php
  use App\Support\MilestoneProgress;

  $team = $row->team;
  $maxMembers = \App\Models\Team::maxMembers();
  $palette = ['#8a6e10', '#0e7490', '#4338ca', '#1d7a3e', '#9f1239'];
  $avatar = fn ($seed) => $palette[abs((int) $seed) % count($palette)];
  $initialsOf = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))
      ->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('');

  // The panel's average at the latest stage they have actually been graded on.
  $score = $defenseScores->get($team->team_id, collect())->last(fn ($s) => $s->average !== null);
  $filled = collect($experts)->filter(fn ($seat) => $seat->expert);
  $empty = collect($experts)->reject(fn ($seat) => $seat->expert);
  $percent = $row->milestones?->percent ?? 0;
@endphp

<div class="tp">
  <div class="tp-head">
    <div class="tp-head-text">
      <div class="tp-name">
        <h1>{{ $team->team_name }}</h1>
        @if ($team->is_confirmed)
          <span class="pill p-green">Verified</span>
        @elseif ($row->awaitingVerification)
          <span class="pill p-amber">To verify</span>
        @else
          <span class="pill p-gray">{{ ucfirst($team->team_status) }}</span>
        @endif
      </div>
      <div class="tp-meta">
        {{ $team->project_title ?: 'No final title yet' }}<span class="sep">·</span>{{ $row->section }}<span class="sep">·</span>@if ($score){{ \App\Support\DefenseScores::format($score->average) }} at {{ $score->label }}@else No defense yet @endif<span class="sep">·</span>Formed {{ $team->created_at ? \Illuminate\Support\Carbon::parse($team->created_at)->format('M j, Y') : 'date unknown' }}
      </div>
    </div>
    <a class="btn {{ $row->pendingProposalCount ? 'btn-p' : 'btn-o' }}"
       href="{{ route('capsa.team_management.pre_proposals', $team->team_id) }}">
      <x-tm-icon n="file" s="15" />Check Pre-proposals
      @if ($row->pendingProposalCount)
        <span class="nb">{{ $row->pendingProposalCount }} waiting</span>
      @endif
    </a>
  </div>

  <div class="tp-grid">
    <div class="tp-panel tp-side">
      <section class="tp-sec" aria-labelledby="tp-mem">
        <div class="tp-sec-h"><h2 id="tp-mem">Members</h2><span>{{ $row->members->count() }} of {{ $maxMembers }}</span></div>
        <ul class="tp-people">
          {{-- The leader first: the one thing about this list that is not just the roster order. --}}
          @forelse ($row->members->sortByDesc(fn ($m) => (int) $m->student_id === (int) $team->leader_id) as $member)
            @php
              $name = trim(($member->f_name ?? '').' '.($member->l_name ?? ''));
              $isLeader = (int) $member->student_id === (int) $team->leader_id;
            @endphp
            <li>
              <span class="tp-av" style="background:{{ $avatar($member->student_id) }}">{{ $initialsOf($name) }}@include('capsa.team-management._photo', ['url' => $member->avatarUrl()])</span>
              <b>{{ $name ?: 'Unnamed student' }}</b>
              @if ($isLeader)<span class="tp-lead">Leader</span>@endif
            </li>
          @empty
            <li class="tp-none">No members yet</li>
          @endforelse
        </ul>
      </section>

      <section class="tp-sec" aria-labelledby="tp-exp">
        <div class="tp-sec-h"><h2 id="tp-exp">Experts</h2></div>
        @foreach ($filled as $seat)
          <div class="tp-expert">
            <span class="tp-av" style="background:{{ $avatar($seat->expert->expert_id) }}">{{ $initialsOf($seat->expert->name) }}@include('capsa.team-management._photo', ['url' => $seat->expert->avatarUrl()])</span>
            <div class="tp-expert-body">
              <b>{{ $seat->expert->name }}</b>
              <span>{{ ucfirst($seat->role) }}@if ($seat->degree) · {{ $seat->degree }}@endif</span>
              @if ($seat->expertise->isNotEmpty())<span class="fields">{{ $seat->expertise->implode(', ') }}</span>@endif
              <a href="mailto:{{ $seat->expert->email }}">{{ $seat->expert->email }}</a>
            </div>
          </div>
        @endforeach
        {{-- All three seats, always: an empty one is information too. --}}
        @if ($empty->isNotEmpty())
          <div class="tp-seats" @if ($filled->isEmpty()) style="margin-top:0" @endif>
            @foreach ($empty as $seat)
              <div><span>{{ ucfirst($seat->role) }}</span><span>No {{ $seat->role }} yet</span></div>
            @endforeach
          </div>
        @endif
      </section>

      {{-- The Papers tab folded in here. Her format pass on what this team
           sent her as Subject Adviser; what she reviews as an adviser or a
           consultant is a different job and stays in My Expert Duties. --}}
      <section class="tp-sec" aria-labelledby="tp-pap">
        <div class="tp-sec-h"><h2 id="tp-pap">Papers</h2><span>{{ $row->papersToCheck }} to check</span></div>
        <ul class="tp-papers">
          @forelse ($row->papers as $paper)
            @php
              $reviewed = $paper->review_status === 'Reviewed';
              $mark = $reviewed ? 'Checked' : ($paper->review_status === 'Reviewing' ? 'In progress' : 'To check');
              $meta = collect([
                $paper->chapter ?: 'Chapter not set',
                'v'.$paper->file_version,
                $paper->submitted_at ? \Illuminate\Support\Carbon::parse($paper->submitted_at)->format('M j, Y') : null,
              ])->filter()->implode(' · ');
            @endphp
            <li>
              <span class="fn">{{ $paper->file_name }}<small>{{ $meta }} · <span class="{{ $reviewed ? 'ok' : 'due' }}">{{ $mark }}</span></small></span>
              <a class="btn {{ $reviewed ? 'btn-o' : 'btn-p' }}"
                 href="{{ route('instructor.docreview.annotate', $paper->review_id) }}">{{ $reviewed ? 'Open' : 'Check format' }}</a>
            </li>
          @empty
            <li class="tp-none">Nothing to check. Papers appear here when this team sends one to their Subject Adviser.</li>
          @endforelse
        </ul>
      </section>
    </div>

    <section class="tp-panel tp-ms" aria-labelledby="tp-ms-h">
      <div class="tp-ms-h">
        <h2 id="tp-ms-h">Milestones</h2>
        <span class="cnt">{{ $row->milestones?->completed ?? 0 }} of {{ $row->milestones?->total ?? 0 }} items done</span>
        <span class="tp-bar" aria-hidden="true"><i style="width:{{ $percent }}%"></i></span>
      </div>

      @if ($milestones->isEmpty())
        <p class="tp-none" style="padding:18px 22px;margin:0">This section has no checklist yet, so there is nothing to track.</p>
      @else
        <div class="tp-ms-body">
          <ol class="tp-nav" aria-label="Milestones">
            @foreach ($milestones as $i => $ms)
              @php
                [$word] = MilestoneProgress::milestoneState($ms);
                $tone = ['Completed' => 'd', 'Overdue' => 'o', 'Past due' => 'o', 'In progress' => 'p'][$word] ?? 'n';
              @endphp
              <li class="t-{{ $tone }}">
                <a href="{{ route('capsa.team_management.show', [$row->team->team_id, 'milestone' => $i]) }}"
                   aria-current="{{ $i === $selectedIndex ? 'true' : 'false' }}"
                   aria-label="{{ $ms->milestone->name }}: {{ $word }}{{ $ms->locked ? '' : ", {$ms->completed} of {$ms->total} done" }}">
                  <span class="n">@if ($tone === 'd')<x-tm-icon n="check" s="11" bold />@else{{ $i + 1 }}@endif</span>
                  <span class="nm">{{ $ms->milestone->name }}</span>
                  <span class="mt">@if ($ms->locked)<x-tm-icon n="lock" s="12" />@else{{ $ms->completed }}/{{ $ms->total }}@endif</span>
                </a>
              </li>
            @endforeach
          </ol>

          <div class="tp-detail">
            <div class="tp-detail-h">
              <h3>{{ $selected->milestone->name }}</h3>
              <span>
                @if ($selected->due_on)Due {{ \Illuminate\Support\Carbon::parse($selected->due_on)->format('M j, Y') }} · @endif{{ $selected->total }} {{ \Illuminate\Support\Str::plural('requirement', $selected->total) }}
              </span>
            </div>

            @if ($selected->locked)
              {{-- One line for the whole milestone rather than a lock and a
                   red "missed" mark on every row: while it is locked the team
                   cannot hand anything in, so nothing here is theirs to miss. --}}
              <div class="tp-gate"><x-tm-icon n="lock" s="16" />
                <span><b>Opens once {{ $selected->requires }} {{ \Illuminate\Support\Str::contains($selected->requires, ' and ') ? 'are' : 'is' }} complete.</b> Until then the team can't hand anything in here.</span></div>
            @endif

            {{-- Flat: every item of the chosen milestone, nothing folded away. --}}
            <ul class="tp-items">
              @foreach ($selected->items as $item)
                @php
                  $short = ['done' => 'd', 'done_late' => 'l', 'handed_in' => 'h', 'overdue' => 'o'][$item->state] ?? 'n';
                  $word = ['d' => 'Done', 'l' => 'Done late', 'h' => 'Handed in', 'o' => 'Overdue', 'n' => 'Not started'][$short];
                  $note = MilestoneProgress::itemNote($item, $item->locked, $item->waits_for);
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
                      <li class="{{ $dot }}">@if ($dot === 'y')<x-tm-icon n="check" s="10" bold />@endif{{ $step['label'] }}</li>
                    @endforeach
                  </ol>
                  </div>
                  @if ($item->locked && $item->state !== 'handed_in')
                    <span class="tp-state lk"><x-tm-icon n="lock" s="12" />Locked</span>
                  @elseif ($item->waiting_on === MilestoneProgress::WAITING_ON_CAPSA && in_array($item->item->kind, \App\Models\Deliverable::CHECKED_BY_CAPSA, true))
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

          </div>
        </div>
      @endif

      <div class="tp-sched"><x-tm-icon n="cal" s="16" />
        <span><b>Defense schedule</b> ·
          @forelse ($defenseSchedules as $sched)
            {{ $sched->defense_type }} {{ $sched->schedule_date?->format('M j, Y') ?? 'date not set' }}@if (! $loop->last), @endif
          @empty
            Not scheduled yet
          @endforelse
        </span>
      </div>
    </section>
  </div>
</div>
@endsection
