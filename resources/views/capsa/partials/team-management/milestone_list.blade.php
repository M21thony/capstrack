{{-- Manage milestones.

     Every milestone on the checklists her sections follow, as one table:
     its status, due date and how many teams are done with it. The name or
     the arrow opens the milestone's own page, where it is edited. In
     checklist order the open ones can be dragged; marking one finished moves
     it to Finished and stops its reminders, and every team's progress on it
     stays as it is. --}}
@include('capsa.partials.team-management._milestone_kit')

@php
  $link = fn (array $change) => route('capsa.team_management', array_merge(['tab' => 'milestone_list', 'state' => $state, 'sort' => $sort], $change));
  $openTotal = $boards->sum('open_count');
  $finishedTotal = $boards->sum('finished_count');
  $single = $boards->count() === 1;
  $canDrag = $state === 'open' && $sort === 'order';
  $flag = fn (int $s) => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 21V4"/><path d="M5 4h12l-2.5 4L17 12H5"/></svg>';
  $teamsOf = fn (int $n) => $n.' '.\Illuminate\Support\Str::plural('team', $n);
@endphp

<style nonce="{{ Vite::cspNonce() }}">
  .mlx{display:flex;flex-direction:column;gap:18px;color:var(--ink)}
  .mlx-head{display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap}
  .mlx-head .grow,.mlx-board-h .grow{flex:1;min-width:0}
  .mlx h1{margin:0;font-size:26px;font-weight:800;letter-spacing:-.01em;color:var(--ink)}
  .mlx h2{margin:0;font-size:17px;font-weight:700;color:var(--ink)}
  .mlx-sub{margin:4px 0 0;color:var(--mut);font-size:14px;display:flex;gap:6px 14px;flex-wrap:wrap;align-items:center}
  .mlx-mono{font-family:var(--f-num)}
  .mlx-mut{color:var(--mut)}
  .mlx-btn{min-height:40px;padding:0 16px;border-radius:8px;border:1px solid var(--line2);background:var(--card);color:var(--ink);font-size:14px;font-weight:600;display:inline-flex;gap:8px;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;white-space:nowrap}
  .mlx-btn:hover:not([disabled]){border-color:var(--ink2)}
  .mlx-btn[disabled]{opacity:.6;cursor:default}
  .mlx-btn.p{background:var(--accent);border-color:var(--accent);color:var(--on-accent)}
  .mlx-btn.p:hover:not([disabled]){filter:brightness(1.06)}
  .mlx-btn.sm{min-height:32px;padding:0 12px;font-size:13px}
  .mlx-btn.icon{width:36px;padding:0}
  .mlx-btn.ghost{border-color:transparent;background:transparent;color:var(--mut)}
  .mlx-btn.ghost:hover{color:var(--ink)}
  .mlx-card{background:var(--card);border:1px solid var(--line);border-radius:12px}
  .mlx-setup{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:14px 18px;border-left:3px solid var(--accent)}
  .mlx-setup div{flex:1;min-width:220px;color:var(--ink2)}
  .mlx-setup b{color:var(--ink)}
  .mlx-rail{display:flex;align-items:stretch;padding:14px 16px}
  .mlx-step{flex:1;display:flex;gap:12px;align-items:flex-start;padding:4px 8px;min-width:0}
  .mlx-step-ic{width:32px;height:32px;border-radius:8px;background:var(--accent-bg);color:var(--gold-ink);display:grid;place-items:center;flex:none}
  .mlx-step-t{display:flex;flex-direction:column;gap:4px;flex:1;min-width:0;font-size:13px;color:var(--mut)}
  .mlx-step-t b{color:var(--ink);font-size:14px}
  .mlx-arrow{display:grid;place-items:center;color:var(--line2);padding:0 4px}
  .mlx-meter{height:6px;border-radius:3px;background:var(--line);overflow:hidden;width:100%}
  .mlx-meter i{display:block;height:100%;background:var(--ok);border-radius:3px}
  .mlx-meter.gold i{background:var(--accent)}
  .mlx-tools{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
  .mlx-tabs{flex:1;display:flex;gap:28px;border-bottom:1px solid var(--line)}
  .mlx-tabs a{padding:10px 2px;font-weight:600;color:var(--mut);border-bottom:2px solid transparent;margin-bottom:-1px;text-decoration:none;display:flex;gap:8px;align-items:center}
  .mlx-tabs a[aria-current]{color:var(--ink);border-color:var(--accent)}
  .mlx-n{font-family:var(--f-num);font-size:12.5px;color:var(--mut);font-weight:500}
  .mlx-sort{display:flex;gap:10px;align-items:center;margin:0}
  .mlx-sort label{font-size:13px;color:var(--mut)}
  .mlx-sel{min-height:36px;border:1px solid var(--line2);border-radius:8px;background:var(--card);font-size:13.5px;font-weight:500;padding:0 10px}
  .mlx-board-h{display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap}
  .mlx-box{max-height:486px;overflow:auto}
  .mlx table{width:100%;border-collapse:separate;border-spacing:0}
  .mlx thead th{position:sticky;top:0;z-index:1;background:var(--tint);text-align:left;font-size:12px;font-weight:600;color:var(--mut);text-transform:uppercase;letter-spacing:.06em;padding:10px 12px;border-bottom:1px solid var(--line)}
  .mlx td{padding:12px;border-bottom:1px solid var(--line);vertical-align:middle}
  .mlx tbody tr:last-child td{border-bottom:0}
  .mlx tbody tr[data-row]:hover td{background:var(--tint)}
  .mlx tbody tr.is-drag{opacity:.45}
  .mlx tr.mlx-div th{text-align:left;font-size:12px;font-weight:700;color:var(--gold-ink);text-transform:uppercase;letter-spacing:.08em;padding:16px 12px 8px;border-bottom:1px solid var(--line)}
  .mlx .c-grip{width:40px;padding-right:0}
  .mlx .ml-grip{border:0;background:none;color:var(--line2);cursor:grab;padding:4px;border-radius:6px;display:grid;margin:0}
  .mlx .ml-grip:hover,.mlx .ml-grip:focus-visible{color:var(--ink2);background:none}
  .mlx .c-num{width:44px;color:var(--mut)}
  .mlx-name{display:inline-flex;gap:8px;align-items:center;font-weight:600;font-size:15px;color:var(--ink);text-decoration:none}
  .mlx-name:hover{text-decoration:underline}
  .mlx-def{color:var(--gold-ink);display:inline-grid}
  .mlx-reqs{display:block;font-size:12.5px;color:var(--mut);margin-top:2px}
  .mlx-st{display:flex;gap:8px;align-items:center;font-weight:600}
  .mlx-dot{width:8px;height:8px;border-radius:50%;flex:none;display:inline-block}
  .mlx-dot.ok{background:var(--ok)}
  .mlx-dot.open{background:var(--info)}
  .mlx-dot.idle{border:1.5px solid var(--mut)}
  .mlx-gate{display:flex;gap:5px;align-items:center;font-size:12.5px;color:var(--mut);margin-top:3px}
  .mlx td.due{width:90px;color:var(--ink2)}
  .mlx-prog{display:flex;gap:10px;align-items:center;width:190px}
  .mlx-prog .mlx-meter{flex:1}
  .mlx-late{display:block;font-size:12.5px;color:var(--bad);font-weight:600;margin-top:3px}
  .mlx .c-act{width:190px;text-align:right;white-space:nowrap}
  .mlx .c-act .mlx-btn{vertical-align:middle;margin-left:6px}
  .mlx-box td:last-child,.mlx-box th:last-child{padding-right:28px}
  .mlx-empty{padding:28px 12px;text-align:center;color:var(--mut)}

  @media (max-width:1100px){
    .mlx-box table,.mlx-box tbody{display:block}
    .mlx-box thead{display:none}
    .mlx-box tr[data-row]{display:grid;grid-template-columns:minmax(0,1fr) auto;grid-template-areas:"name act" "st due" "prog prog";gap:8px 12px;align-items:center;padding:14px 28px 14px 14px;border-bottom:1px solid var(--line)}
    .mlx-box tr[data-row]:last-child{border-bottom:0}
    .mlx-box tr[data-row] td,.mlx-box tr[data-row] td:last-child{padding:0;border:0;width:auto}
    .mlx-box .c-grip,.mlx-box .c-num{display:none}
    .mlx-box td.c-name{grid-area:name}.mlx-box td.c-st{grid-area:st}.mlx-box td.due{grid-area:due;text-align:right}
    .mlx-box td.c-prog{grid-area:prog}.mlx-box td.c-act{grid-area:act}
    .mlx-box .mlx-prog{width:100%}
    .mlx-box tr.mlx-div,.mlx-box tr.mlx-div th{display:block}
    .mlx-box tbody tr[data-row]:hover td{background:none}
    .mlx-box tbody tr[data-row]:hover{background:var(--tint)}
    .mlx-box tr:not([data-row]) td{display:block}
  }
  @media (max-width:1000px){
    .mlx-rail{flex-wrap:wrap;gap:12px}.mlx-arrow{display:none}.mlx-step{flex:1 1 40%}
  }
  @media (max-width:640px){
    .mlx h1{font-size:22px}
    .mlx-head .grow{flex:1 1 220px}
    .mlx-head .mlx-btn{flex:1 1 auto}
    .mlx-step{flex-basis:100%}
  }
</style>

<div class="mlx">
  <div class="mlx-head">
    <div class="grow">
      <h1>Manage milestones</h1>
      @if ($single)
        @php $first = $boards->first(); @endphp
        <p class="mlx-sub"><span>{{ $first['checklist']->title }}</span>@if ($first['sections'])<span>{{ implode(' · ', $first['sections']) }}</span>@endif<span><span class="mlx-mono">{{ $first['teams'] }}</span> {{ \Illuminate\Support\Str::plural('team', $first['teams']) }}</span></p>
      @endif
    </div>
    <a class="mlx-btn" href="{{ route('capsa.team_management', ['tab' => 'milestones']) }}">Open in Milestones</a>
    @if ($single)
      <a class="mlx-btn p" href="{{ route('capsa.team_management.milestones.create', $first['checklist']) }}"><x-tm-icon n="plus" s="16" />New milestone</a>
    @endif
  </div>

  @foreach ($setups as $program => $programSections)
    @php $many = count($programSections) > 1; @endphp
    <div class="mlx-card mlx-setup">
      <div>
        <b>{{ implode(', ', $programSections) ?: $program }} {{ $many ? 'have' : 'has' }} no milestones yet.</b>
        {{ $many ? 'Their' : 'Its' }} teams see nothing until you set them up.
      </div>
      <a class="mlx-btn p sm" href="{{ route('capsa.team_management.milestones.setup', $program) }}">Set up milestones</a>
    </div>
  @endforeach

  @if ($boards->isEmpty())
    @if (! $setups)
      <div class="mlx-card mlx-empty">None of your sections follows a checklist yet.</div>
    @endif
  @else
    @foreach ($boards as $board)
      @php
        $checklist = $board['checklist'];
        // Every defense teams go through, in list order.
        $defenses = $board['milestones']->filter(fn ($row) => ! $row->milestone->archived_at)
          ->flatMap(fn ($row) => $row->milestone->deliverables->where('kind', \App\Models\Deliverable::DEFENSE))
          ->values();
        $captioned = false;
      @endphp

      @unless ($single)
        <div class="mlx-board-h">
          <div class="grow">
            <h2>{{ $checklist->program }} · {{ $checklist->title }}</h2>
            <p class="mlx-sub">@if ($board['sections'])<span>{{ implode(' · ', $board['sections']) }}</span>@endif<span>{{ $teamsOf($board['teams']) }}</span></p>
          </div>
          <a class="mlx-btn p sm" href="{{ route('capsa.team_management.milestones.create', $checklist) }}"><x-tm-icon n="plus" s="14" />New milestone</a>
        </div>
      @endunless

      @if ($defenses->isNotEmpty())
        <section class="mlx-card mlx-rail" aria-label="{{ $single ? 'Defenses' : $checklist->program.' defenses' }}">
          @foreach ($defenses as $defense)
            @php
              $passed = $board['done'][$defense->id] ?? 0;
              $width = $board['teams'] ? round($passed / $board['teams'] * 100) : 0;
            @endphp
            @unless ($loop->first)
              <span class="mlx-arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
            @endunless
            <div class="mlx-step">
              <span class="mlx-step-ic">{!! $flag(16) !!}</span>
              <div class="mlx-step-t">
                <b>{{ $defense->defense_type ?: $defense->title }}</b>
                <span><span class="mlx-mono">{{ $passed }}</span> of {{ $teamsOf($board['teams']) }} passed</span>
                <div class="mlx-meter gold"><i style="width:{{ $width }}%"></i></div>
              </div>
            </div>
          @endforeach
        </section>
      @endif

      @if ($loop->first)
        <div class="mlx-tools">
          <nav class="mlx-tabs" aria-label="Milestone lists">
            <a href="{{ $link(['state' => 'open']) }}" @if ($state === 'open') aria-current="page" @endif>Open <span class="mlx-n">{{ $openTotal }}</span></a>
            <a href="{{ $link(['state' => 'finished']) }}" @if ($state === 'finished') aria-current="page" @endif>Finished <span class="mlx-n">{{ $finishedTotal }}</span></a>
          </nav>
          <form class="mlx-sort" method="get" action="{{ route('capsa.team_management') }}">
            <input type="hidden" name="tab" value="milestone_list">
            <input type="hidden" name="state" value="{{ $state }}">
            <label for="ml-sort">Show</label>
            <select id="ml-sort" class="mlx-sel" name="sort" onchange="this.form.submit()">
              @foreach ($sorts as $key => $label)
                <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
              @endforeach
            </select>
            <noscript><button class="mlx-btn sm" type="submit">Show</button></noscript>
          </form>
        </div>
      @endif

      <section class="mlx-card mlx-box" aria-label="{{ $single ? 'Milestones' : $checklist->program.' milestones' }}">
        <table>
          <thead><tr><th class="c-grip"></th><th class="c-num">#</th><th>Milestone</th><th>Status</th><th>Due</th><th>Teams done</th><th></th></tr></thead>
          <tbody @if ($canDrag) data-order="{{ json_encode($board['order']) }}" data-url="{{ route('capsa.checklists.order.update', $checklist) }}" @endif>
            @forelse ($board['shown'] as $row)
              @php
                $m = $row->milestone;
                $isDefense = $m->deliverables->contains('kind', \App\Models\Deliverable::DEFENSE);
                $allDone = $row->teams > 0 && $row->complete === $row->teams;
                [$dot, $status] = match (true) {
                  (bool) $m->archived_at => ['idle', 'Hidden from teams'],
                  $m->isClosed() => ['ok', 'Finished'],
                  ! $m->isOpened() => ['idle', 'Not open yet'],
                  $allDone => ['ok', 'All teams done'],
                  default => ['open', 'Open'],
                };
                $patch = route('capsa.checklist_milestones.update', $m);
                $page = route('capsa.team_management.milestones.show', $m);
              @endphp
              @if ($sort === 'order' && $row->capstone2 && ! $captioned)
                @php $captioned = true; @endphp
                <tr class="mlx-div"><th colspan="7" scope="rowgroup">Capstone 2</th></tr>
              @endif
              <tr data-row @if ($canDrag) data-id="{{ $m->id }}" @endif>
                <td class="c-grip">
                  @if ($canDrag)
                    <button class="ml-grip" type="button" title="Drag to move" aria-label="Move {{ $m->name }}: drag, or use the up and down arrow keys"><x-tm-icon n="grip" s="18" bold /></button>
                  @endif
                </td>
                <td class="c-num mlx-mono">{{ $row->number ?? '—' }}</td>
                <td class="c-name">
                  <a class="mlx-name" href="{{ $page }}">@if ($isDefense)<span class="mlx-def" title="Defense">{!! $flag(15) !!}</span>@endif{{ $m->name }}</a>
                  <span class="mlx-reqs">{{ $row->deliverables }} {{ \Illuminate\Support\Str::plural('requirement', $row->deliverables) }}</span>
                </td>
                <td class="c-st">
                  <span class="mlx-st"><i class="mlx-dot {{ $dot }}"></i>{{ $status }}</span>
                  @if ($m->requires_previous && $row->previous && ! $m->isClosed())
                    <span class="mlx-gate"><x-tm-icon n="lock" s="13" />Opens after {{ $row->previous }}</span>
                  @endif
                </td>
                <td class="mlx-mono due">{{ $row->due_on?->format('M j') ?? '—' }}</td>
                <td class="c-prog">
                  @if (! $m->isOpened())
                    <span class="mlx-mut mlx-mono">—</span>
                  @else
                    <div class="mlx-prog"><div class="mlx-meter"><i style="width:{{ $row->percent }}%"></i></div><span class="mlx-mono">{{ $row->complete }}<span class="mlx-mut">/{{ $row->teams }}</span></span></div>
                    @if ($row->past_due > 0 && ! $m->isClosed())
                      <span class="mlx-late">{{ $row->past_due }} past due</span>
                    @endif
                  @endif
                </td>
                <td class="c-act">
                  @if ($m->archived_at)
                    <button class="mlx-btn sm" type="button" data-patch="{{ $patch }}" data-body='{"archived":false}'>Show to teams again</button>
                  @elseif (! $m->isOpened())
                    <button class="mlx-btn p sm" type="button" data-patch="{{ $patch }}" data-body='{"opened":true}'>Open to teams</button>
                  @elseif ($m->isClosed())
                    <button class="mlx-btn sm" type="button" data-patch="{{ $patch }}" data-body='{"closed":false}'>Reopen</button>
                  @elseif ($allDone)
                    <button class="mlx-btn sm" type="button" data-patch="{{ $patch }}" data-body='{"closed":true}'>Mark finished</button>
                  @endif
                  <a class="mlx-btn icon sm ghost" href="{{ $page }}" aria-label="Open {{ $m->name }}"><x-tm-icon n="chev" s="16" /></a>
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="mlx-empty">{{ $state === 'finished' ? 'No finished milestones yet.' : 'No open milestones.' }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </section>
    @endforeach
  @endif
</div>

<script nonce="{{ Vite::cspNonce() }}">
  // Finish, reopen or show again, then the list again: the milestone moves tabs.
  document.querySelectorAll('.mlx [data-patch]').forEach(button => {
    button.addEventListener('click', async () => {
      button.disabled = true;
      try {
        await mlSend(button.dataset.patch, 'PATCH', JSON.parse(button.dataset.body));
        location.reload();
      } catch (error) {
        button.disabled = false;
        mlFail(error);
      }
    });
  });

  // Only the open ones are on screen, so they swap among the places they
  // already hold; finished and hidden ones keep theirs.
  document.querySelectorAll('.mlx tbody[data-order]').forEach(list => mlSortable(list, async ids => {
    const order = JSON.parse(list.dataset.order);
    const byId = new Map(order.map(milestone => [milestone.id, milestone]));
    const moved = new Set(ids);
    let next = 0;
    await mlSend(list.dataset.url, 'PUT', { milestones: order.map(milestone => moved.has(milestone.id) ? byId.get(ids[next++]) : milestone) });
    location.reload();
  }));
</script>
