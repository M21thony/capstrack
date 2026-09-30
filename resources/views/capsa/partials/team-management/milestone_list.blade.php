{{-- All milestones, GitHub style.

     Every milestone on the checklists her sections follow, open or closed,
     each with its due date and how many teams have finished
     it. Closing one takes it off the open list and stops its reminders;
     every team's progress on it stays as it is. The details are edited in
     the Edit milestones window on the tracker. --}}
<style>
  .ml-page{max-width:980px}
  .ml-head{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:16px}
  .ml-title{font-size:22px;font-weight:700;color:var(--text);letter-spacing:-.01em}
  .ml-sub{font-size:13px;color:var(--text3);margin-top:3px;max-width:62ch;line-height:1.5}
  .ml-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}
  .ml-tabs{display:inline-flex;gap:4px;padding:4px;border:1px solid var(--border);border-radius:10px;background:var(--bg2)}
  .ml-tab{font-size:13px;font-weight:600;padding:7px 14px;border-radius:7px;color:var(--text3);text-decoration:none;display:inline-flex;align-items:center;gap:6px}
  .ml-tab[aria-current="page"]{background:var(--bg3);color:var(--text)}
  .ml-count{font-variant-numeric:tabular-nums;padding:1px 7px;border-radius:999px;background:var(--bg4);font-size:11px}
  .ml-sort{display:inline-flex;align-items:center;gap:8px;font-size:12.5px;color:var(--text3)}
  .ml-sort select{font:inherit;font-size:13px;padding:6px 10px;border:1px solid var(--border);border-radius:8px;background:var(--bg2);color:var(--text)}
  .ml-board{margin-bottom:22px}
  .ml-board h2{font-size:14px;font-weight:700;color:var(--text2);margin:0 0 8px}
  .ml-board h2 small{font-weight:500;color:var(--text3);margin-left:6px}
  .ml-list{border:1px solid var(--border);border-radius:12px;background:var(--bg2);overflow:hidden}
  .ml-row{display:flex;gap:16px;align-items:flex-start;padding:16px 18px;border-top:1px solid var(--border)}
  .ml-row:first-child{border-top:0}
  .ml-main{flex:1;min-width:0;display:grid;gap:5px}
  .ml-name{font-size:15.5px;font-weight:700;color:var(--text);line-height:1.3}
  .ml-meta{display:flex;flex-wrap:wrap;align-items:center;gap:4px 10px;font-size:12.5px;color:var(--text3)}
  .ml-late{color:var(--red);font-weight:600}
  .ml-prog{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:3px;font-size:12.5px;color:var(--text3)}
  .ml-prog b{color:var(--text);font-variant-numeric:tabular-nums}
  .ml-meter{width:220px;max-width:100%;height:8px;border-radius:999px;background:var(--bg4);overflow:hidden}
  .ml-meter i{display:block;height:100%;border-radius:999px;background:var(--green)}
  .ml-acts{display:flex;gap:8px;flex:none}
  .ml-btn{font:inherit;font-size:12.5px;font-weight:600;padding:7px 13px;border-radius:8px;cursor:pointer;text-decoration:none;
          border:1px solid var(--border);background:var(--bg2);color:var(--text2)}
  .ml-btn:hover:not([disabled]){color:var(--text);border-color:var(--text3)}
  .ml-btn[disabled]{opacity:.6;cursor:default}
  .ml-empty{padding:28px 18px;text-align:center;font-size:13px;color:var(--text3)}
  @media (max-width:640px){
    .ml-row{flex-direction:column}
    .ml-acts{width:100%}
    .ml-acts .ml-btn{flex:1;text-align:center}
  }
</style>

@php
  $link = fn (array $change) => route('capsa.team_management', array_merge(['tab' => 'milestone_list', 'state' => $state, 'sort' => $sort], $change));
  $openTotal = $boards->sum('open_count');
  $closedTotal = $boards->sum('closed_count');
  $editUrl = route('capsa.team_management', ['tab' => 'milestones']);
@endphp

<div class="ml-page">
  <div class="ml-head">
    <div>
      <div class="ml-title">All milestones</div>
      <div class="ml-sub">Each milestone's due date and how many teams have finished it. A closed milestone leaves this list and sends no more reminders; the teams' progress on it stays as it is.</div>
    </div>
    <a class="ml-btn" href="{{ $editUrl }}">Edit milestones</a>
  </div>

  @if ($boards->isEmpty())
    <div class="ml-list"><div class="ml-empty">None of your sections has a checklist yet. Make one under Milestones → Edit milestones.</div></div>
  @else
    <div class="ml-bar">
      <nav class="ml-tabs" aria-label="Open or closed">
        <a class="ml-tab" href="{{ $link(['state' => 'open']) }}" @if ($state === 'open') aria-current="page" @endif>Open <span class="ml-count">{{ $openTotal }}</span></a>
        <a class="ml-tab" href="{{ $link(['state' => 'closed']) }}" @if ($state === 'closed') aria-current="page" @endif>Closed <span class="ml-count">{{ $closedTotal }}</span></a>
      </nav>
      <form class="ml-sort" method="get" action="{{ route('capsa.team_management') }}">
        <input type="hidden" name="tab" value="milestone_list">
        <input type="hidden" name="state" value="{{ $state }}">
        <label for="ml-sort">Sort by</label>
        <select id="ml-sort" name="sort" onchange="this.form.submit()">
          @foreach ($sorts as $key => $label)
            <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
          @endforeach
        </select>
        <noscript><button class="ml-btn" type="submit">Sort</button></noscript>
      </form>
    </div>

    @foreach ($boards as $board)
      <section class="ml-board" aria-label="{{ $board['checklist']->title }}">
        @if ($boards->count() > 1)
          <h2>{{ $board['checklist']->title }}<small>{{ implode(', ', $board['sections']) }} · {{ $board['teams'] }} {{ \Illuminate\Support\Str::plural('team', $board['teams']) }}</small></h2>
        @endif
        <div class="ml-list">
          @forelse ($board['shown'] as $row)
            @php
              $m = $row->milestone;
              $days = $row->due_on ? (int) today()->diffInDays($row->due_on->copy()->startOfDay(), false) : null;
            @endphp
            <div class="ml-row" data-milestone="{{ $m->id }}">
              <div class="ml-main">
                <div class="ml-name">{{ $m->name }}</div>
                <div class="ml-meta">
                  @if ($m->isClosed())
                    <span>Closed {{ $m->closed_at->format('M j, Y') }}</span>
                  @endif
                  @if ($row->due_on)
                    <span>Due {{ $row->due_on->format('M j, Y') }}</span>
                    @if (! $m->isClosed())
                      @if ($days < 0 && $row->past_due > 0)
                        <span class="ml-late">Past due for {{ $row->past_due }} {{ \Illuminate\Support\Str::plural('team', $row->past_due) }}</span>
                      @elseif ($days === 0)
                        <span>Due today</span>
                      @elseif ($days > 0)
                        <span>in {{ $days }} {{ \Illuminate\Support\Str::plural('day', $days) }}</span>
                      @endif
                    @endif
                  @else
                    <span>No due date</span>
                  @endif
                  @if ($m->requires_previous)
                    <span>Opens after the milestone before it</span>
                  @endif
                </div>
                <div class="ml-prog">
                  <span class="ml-meter"><i style="width:{{ $row->percent }}%"></i></span>
                  <b>{{ $row->percent }}%</b>
                  <span>{{ $row->complete }} of {{ $row->teams }} {{ \Illuminate\Support\Str::plural('team', $row->teams) }} complete · {{ $row->open }} open · {{ $row->deliverables }} {{ \Illuminate\Support\Str::plural('deliverable', $row->deliverables) }}</span>
                </div>
              </div>
              <div class="ml-acts">
                <button class="ml-btn" type="button"
                        data-close-url="{{ route('capsa.checklist_milestones.update', $m->id) }}"
                        data-closed="{{ $m->isClosed() ? '0' : '1' }}">{{ $m->isClosed() ? 'Reopen' : 'Close' }}</button>
              </div>
            </div>
          @empty
            <div class="ml-empty">{{ $state === 'closed' ? 'No closed milestones.' : 'No open milestones.' }}</div>
          @endforelse
        </div>
      </section>
    @endforeach
  @endif
</div>

<script>
  // Close or reopen, then show the list again: the milestone moves tabs.
  document.querySelectorAll('[data-close-url]').forEach(button => {
    button.addEventListener('click', async () => {
      button.disabled = true;
      const response = await fetch(button.dataset.closeUrl, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ closed: button.dataset.closed === '1' }),
      });
      if (response.ok) {
        location.reload();
        return;
      }
      button.disabled = false;
      const body = await response.json().catch(() => ({}));
      outcomeDialog({ tone: 'error', title: 'That did not save', reason: body.message || 'Please try again.' });
    });
  });
</script>
