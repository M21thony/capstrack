{{-- Manage milestones, GitHub style.

     Every milestone on the checklists her sections follow, each with its
     due date and how many teams are done with it. The name opens the
     milestone's own page; Edit, New milestone and the setup card open forms
     of their own. In checklist order the open ones can be dragged; marking
     one finished moves it to Finished and stops its reminders, and every
     team's progress on it stays as it is. --}}
@include('capsa.partials.team-management._milestone_kit')

@php
  $link = fn (array $change) => route('capsa.team_management', array_merge(['tab' => 'milestone_list', 'state' => $state, 'sort' => $sort], $change));
  $openTotal = $boards->sum('open_count');
  $finishedTotal = $boards->sum('finished_count');
  $single = $boards->count() === 1;
  $canDrag = $state === 'open' && $sort === 'order';
@endphp

<div class="ml-page">
  <div class="ml-head">
    <div>
      <h1 class="ml-title">Milestones</h1>
      <div class="ml-sub">The steps your teams go through, and how many teams are done with each.</div>
    </div>
    @if ($single)
      <a class="ml-btn ml-gold" href="{{ route('capsa.team_management.milestones.create', $boards->first()['checklist']) }}"><x-tm-icon n="plus" s="14" />New milestone</a>
    @endif
  </div>

  @foreach ($setups as $program => $programSections)
    @php $many = count($programSections) > 1; @endphp
    <div class="ml-setup">
      <div>
        <b>{{ implode(', ', $programSections) ?: $program }} {{ $many ? 'have' : 'has' }} no milestones yet.</b>
        {{ $many ? 'Their' : 'Its' }} teams see nothing until you set them up.
      </div>
      <a class="ml-btn" href="{{ route('capsa.team_management.milestones.setup', $program) }}">Set up {{ $program }} milestones</a>
    </div>
  @endforeach

  @if ($boards->isEmpty())
    @if (! $setups)
      <div class="ml-list"><div class="ml-empty">None of your sections follows a checklist yet.</div></div>
    @endif
  @else
    <div class="ml-bar">
      <nav class="ml-tabs" aria-label="Open or finished">
        <a class="ml-tab" href="{{ $link(['state' => 'open']) }}" @if ($state === 'open') aria-current="page" @endif>Open <span class="ml-count">{{ $openTotal }}</span></a>
        <a class="ml-tab" href="{{ $link(['state' => 'finished']) }}" @if ($state === 'finished') aria-current="page" @endif>Finished <span class="ml-count">{{ $finishedTotal }}</span></a>
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
      @php $checklist = $board['checklist']; $captioned = false; @endphp
      <section class="ml-board" aria-label="{{ $checklist->program }} {{ $checklist->title }}">
        <div class="ml-board-h">
          <h2>{{ $checklist->program }} · {{ $checklist->title }}<small>{{ implode(', ', $board['sections']) }} · {{ $board['teams'] }} {{ \Illuminate\Support\Str::plural('team', $board['teams']) }}</small></h2>
          @unless ($single)
            <a class="ml-btn ml-gold" href="{{ route('capsa.team_management.milestones.create', $checklist) }}"><x-tm-icon n="plus" s="14" />New milestone</a>
          @endunless
        </div>
        <div class="ml-list"
             @if ($canDrag) data-order="{{ json_encode($board['order']) }}" data-url="{{ route('capsa.checklists.order.update', $checklist) }}" @endif>
          @forelse ($board['shown'] as $row)
            @php $m = $row->milestone; @endphp
            @if ($sort === 'order' && $row->capstone2 && ! $captioned)
              @php $captioned = true; @endphp
              <div class="ml-cap">CAPSTONE 2</div>
            @endif
            <div class="ml-row" @if ($canDrag) data-id="{{ $m->id }}" @endif>
              @if ($canDrag)
                <button class="ml-grip" type="button" title="Drag to move" aria-label="Move {{ $m->name }}: drag, or use the up and down arrow keys"><x-tm-icon n="grip" s="18" bold /></button>
              @endif
              <div class="ml-main">
                <a class="ml-name" href="{{ route('capsa.team_management.milestones.show', $m) }}">{{ $sort === 'order' && $row->number ? $row->number.'. ' : '' }}{{ $m->name }}</a>
                @include('capsa.partials.team-management._milestone_meta', ['row' => $row])
              </div>
              <div class="ml-acts">
                <a class="ml-btn" href="{{ route('capsa.team_management.milestones.edit', $m) }}">Edit</a>
                @if ($m->archived_at)
                  <button class="ml-btn" type="button" data-patch="{{ route('capsa.checklist_milestones.update', $m) }}" data-body='{"archived":false}'>Show to teams again</button>
                @else
                  <button class="ml-btn" type="button" data-patch="{{ route('capsa.checklist_milestones.update', $m) }}" data-body='{"closed":{{ $m->isClosed() ? 'false' : 'true' }}}'>{{ $m->isClosed() ? 'Reopen' : 'Mark finished' }}</button>
                @endif
              </div>
            </div>
          @empty
            <div class="ml-empty">{{ $state === 'finished' ? 'No finished milestones yet.' : 'No open milestones.' }}</div>
          @endforelse
        </div>
      </section>
    @endforeach
  @endif
</div>

<script>
  // Finish, reopen or show again, then the list again: the milestone moves tabs.
  document.querySelectorAll('.ml-page [data-patch]').forEach(button => {
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
  document.querySelectorAll('.ml-page .ml-list[data-order]').forEach(list => mlSortable(list, async ids => {
    const order = JSON.parse(list.dataset.order);
    const byId = new Map(order.map(milestone => [milestone.id, milestone]));
    const moved = new Set(ids);
    let next = 0;
    await mlSend(list.dataset.url, 'PUT', { milestones: order.map(milestone => moved.has(milestone.id) ? byId.get(ids[next++]) : milestone) });
    location.reload();
  }));
</script>
