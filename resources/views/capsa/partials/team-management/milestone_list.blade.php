{{-- Manage milestones: configuration only. Every milestone on the checklists
     her sections follow, in order, with its start condition, requirement
     count, due date and whether teams can see it. Team progress lives in the
     tracker. Milestones can be dragged into a new order. --}}
@include('capsa.partials.team-management._milestone_kit')

@php
  $single = $boards->count() === 1;
@endphp

<style nonce="{{ Vite::cspNonce() }}">
  .mlx{display:flex;flex-direction:column;gap:12px;color:var(--ink);min-height:0}
  .mlx-head{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
  .mlx-head h1{flex:1;min-width:200px;margin:0;font-size:22px;font-weight:800;letter-spacing:-.01em;color:var(--ink)}
  .mlx-btn{min-height:34px;padding:0 13px;border-radius:8px;border:1px solid var(--line2);background:var(--card);color:var(--ink);font-size:13px;font-weight:600;display:inline-flex;gap:6px;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;white-space:nowrap}
  .mlx-btn:hover{border-color:var(--ink2);color:var(--ink)}
  .mlx-btn.p{background:var(--gold);border-color:var(--gold);color:var(--on-gold)}
  .mlx-btn.p:hover{filter:brightness(1.05);color:var(--on-gold)}
  .mlx-btn.sm{min-height:30px;padding:0 11px;font-size:12.5px}
  .mlx-btn.ghost{border-color:transparent;background:transparent;color:var(--mut)}
  .mlx-card{background:var(--card);border:1px solid var(--line);border-radius:12px}
  .mlx-setup{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:12px 16px}
  .mlx-setup div{flex:1;min-width:220px;color:var(--ink2)}
  .mlx-setup b{color:var(--ink)}
  .mlx-box{flex:1 1 0;min-height:240px;display:flex;flex-direction:column;overflow:hidden}
  .mlx-box-h{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:12px 16px}
  .mlx-box-h div{flex:1;min-width:0}
  .mlx-box-h h2{margin:0;font-size:16px;font-weight:700;color:var(--ink)}
  .mlx-box-h small{display:block;font-size:12.5px;color:var(--mut)}
  .mlx-scroll{flex:1;min-height:0;overflow:auto}
  .mlx table{width:100%;min-width:0;border-collapse:separate;border-spacing:0}
  .mlx thead th{position:sticky;top:0;z-index:1;background:var(--navy-dark,#18191f);color:#fff;text-align:left;font-size:12.5px;font-weight:600;padding:9px 14px;white-space:nowrap;border:0}
  .mlx td{padding:9px 14px;border-bottom:1px solid var(--line);vertical-align:middle}
  .mlx tbody tr:last-child td{border-bottom:0}
  .mlx tbody tr[data-row]:hover td{background:var(--tint)}
  .mlx tbody tr.is-drag{opacity:.45}
  .mlx tr.mlx-div th{text-align:left;font-size:11.5px;font-weight:700;color:var(--gold-ink);text-transform:uppercase;letter-spacing:.08em;padding:12px 14px 6px;border-bottom:1px solid var(--line);background:var(--card)}
  .mlx .c-grip{width:34px;padding-right:0}
  .mlx .ml-grip{border:0;background:none;color:var(--line2);cursor:grab;padding:4px;border-radius:6px;display:grid;margin:0}
  .mlx .ml-grip:hover,.mlx .ml-grip:focus-visible{color:var(--ink2);background:none}
  .mlx td.c-num{width:56px;color:var(--ink2)}
  .mlx th.c-num{width:56px}
  .mlx a.mlx-name{font-weight:700;font-size:14px;color:var(--gold-ink);text-decoration:none}
  .mlx a.mlx-name:hover{text-decoration:underline}
  .mlx-gate{display:flex;gap:5px;align-items:center;font-size:12px;color:var(--mut);margin-top:1px}
  .mlx .c-req,.mlx .c-due{color:var(--ink2);white-space:nowrap}
  .mlx-pill{display:inline-flex;align-items:center;gap:6px;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:600;white-space:nowrap}
  .mlx-pill::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor}
  .mlx-pill.open{background:#eff6ff;color:var(--info)}
  .mlx-pill.done{background:#f0fdf4;color:var(--ok)}
  .mlx-pill.idle{background:var(--surface2);color:var(--mut)}
  .mlx .c-act{text-align:right;white-space:nowrap}
  .mlx .c-act .mlx-btn + .mlx-btn{margin-left:4px}
  .mlx-empty{padding:28px 12px;text-align:center;color:var(--mut)}

  @media (max-width:820px){
    .mlx-box{flex:none;min-height:0}
    .mlx-scroll{overflow:visible}
    .mlx table,.mlx tbody{display:block}
    .mlx thead{display:none}
    .mlx tr[data-row]{display:grid;grid-template-columns:auto minmax(0,1fr);gap:4px 10px;padding:12px 16px;border-top:1px solid var(--line)}
    .mlx tr[data-row] td{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:0;border:0;width:auto;grid-column:2}
    .mlx tr[data-row] td[data-label]::before{content:attr(data-label);font-size:11.5px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--mut)}
    .mlx tr[data-row] td.c-grip{grid-column:1;grid-row:1 / span 5;align-self:start}
    .mlx tr[data-row] td.c-num{display:none}
    .mlx tr[data-row] td.c-name{display:block}
    .mlx tr[data-row] td.c-act{justify-content:flex-end;margin-top:4px}
    .mlx tr.mlx-div,.mlx tr.mlx-div th{display:block}
    .mlx tr:not([data-row]) td{display:block}
  }
</style>

<div class="mlx">
  <div class="mlx-head">
    <h1>Manage milestones</h1>
    <a class="mlx-btn" href="{{ route('capsa.team_management', ['tab' => 'milestones']) }}">Open tracker</a>
  </div>

  @foreach ($setups as $program => $programSections)
    @php $many = count($programSections) > 1; @endphp
    <div class="mlx-card mlx-setup">
      <div><b>{{ implode(', ', $programSections) ?: $program }} {{ $many ? 'have' : 'has' }} no milestones yet.</b></div>
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
        $rows = $board['milestones'];
        $reqs = $rows->sum('deliverables');
        $captioned = false;
      @endphp
      <section class="mlx-card mlx-box" aria-label="{{ $single ? 'Milestones' : $checklist->program.' milestones' }}">
        <div class="mlx-box-h">
          <div>
            <h2>{{ $single ? 'Milestones' : $checklist->program.' · '.$checklist->title }}</h2>
            <small>{{ $rows->count() }} {{ \Illuminate\Support\Str::plural('milestone', $rows->count()) }} · {{ $reqs }} {{ \Illuminate\Support\Str::plural('requirement', $reqs) }}@if ($board['sections']) · {{ implode(' · ', $board['sections']) }}@endif</small>
          </div>
          <a class="mlx-btn p" href="{{ route('capsa.team_management.milestones.create', $checklist) }}"><x-tm-icon n="plus" s="15" />New milestone</a>
        </div>
        <div class="mlx-scroll">
          <table>
            <thead><tr><th class="c-grip"></th><th class="c-num">Order</th><th>Milestone</th><th>Requirements</th><th>Due date</th><th>Availability</th><th></th></tr></thead>
            <tbody data-order="{{ json_encode($board['order']) }}" data-url="{{ route('capsa.checklists.order.update', $checklist) }}">
              @forelse ($rows as $row)
                @php
                  $m = $row->milestone;
                  [$pill, $availability] = match (true) {
                    (bool) $m->archived_at => ['idle', 'Hidden'],
                    $m->isClosed() => ['done', 'Finished'],
                    ! $m->isOpened() => ['idle', 'Not open yet'],
                    default => ['open', 'Open'],
                  };
                  $page = route('capsa.team_management.milestones.show', $m);
                @endphp
                @if ($row->capstone2 && ! $captioned)
                  @php $captioned = true; @endphp
                  <tr class="mlx-div"><th colspan="7" scope="rowgroup">Capstone 2</th></tr>
                @endif
                <tr data-row data-id="{{ $m->id }}">
                  <td class="c-grip">
                    <button class="ml-grip" type="button" title="Drag to move" aria-label="Move {{ $m->name }}: drag, or use the up and down arrow keys"><x-tm-icon n="grip" s="18" bold /></button>
                  </td>
                  <td class="c-num">{{ $row->number ?? '—' }}</td>
                  <td class="c-name">
                    <a class="mlx-name" href="{{ $page }}">{{ $m->name }}</a>
                    <span class="mlx-gate">
                      @if ($m->requires_previous && $row->previous)
                        <x-tm-icon n="lock" s="12" />After passing {{ $row->previous }}
                      @else
                        Open to teams
                      @endif
                    </span>
                  </td>
                  <td class="c-req" data-label="Requirements">{{ $row->deliverables }}</td>
                  <td class="c-due" data-label="Due date">{{ $row->due_on?->format('M j, Y') ?? '—' }}</td>
                  <td data-label="Availability"><span class="mlx-pill {{ $pill }}">{{ $availability }}</span></td>
                  <td class="c-act">
                    <a class="mlx-btn sm" href="{{ $page }}">Requirements</a>
                    <a class="mlx-btn sm ghost" href="{{ route('capsa.team_management.milestones.edit', $m) }}">Edit</a>
                  </td>
                </tr>
              @empty
                <tr><td colspan="7" class="mlx-empty">No milestones yet.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </section>
    @endforeach
  @endif
</div>

<script nonce="{{ Vite::cspNonce() }}">
  // On a wide screen the list fills the window and scrolls inside its card.
  (() => {
    const box = document.querySelector('.mlx');
    const fit = () => box.style.height = innerWidth > 820
      ? Math.max(420, innerHeight - box.getBoundingClientRect().top - 26) + 'px' : '';
    fit();
    addEventListener('resize', fit);
    const real = document.getElementById('real-content');
    if (real) new MutationObserver(fit).observe(real, { attributes: true, attributeFilter: ['style'] });
  })();

  // Every milestone is on screen, so the dragged order is the whole order.
  document.querySelectorAll('.mlx tbody[data-order]').forEach(list => mlSortable(list, async ids => {
    const byId = new Map(JSON.parse(list.dataset.order).map(milestone => [milestone.id, milestone]));
    await mlSend(list.dataset.url, 'PUT', { milestones: ids.map(id => byId.get(id)) });
    location.reload();
  }));
</script>
