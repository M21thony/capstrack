{{-- New milestone, or one milestone's name, description, due date, place in
     the list and start condition. Editing adds hiding it and deleting it. --}}
@extends('capsa.team-management._shell')

@section('title', $milestone ? 'Edit '.$milestone->name : 'New milestone')

@php
  $back = $milestone ? route('capsa.team_management.milestones.show', $milestone) : route('capsa.team_management', ['tab' => 'milestone_list']);
  // A hidden milestone has no place in the list teams see.
  $placed = ! $milestone || in_array($milestone->id, $active, true);
  $count = count($active) + ($milestone ? 0 : 1);
  $at = $milestone ? array_search($milestone->id, $active, true) + 1 : $count;
@endphp

@push('head')
  @include('capsa.partials.team-management._milestone_kit')
@endpush

@section('tm-content')
<form class="mdg" id="ml-milestone" novalidate aria-labelledby="ml-title">
  <div class="mdg-h">
    <h1 id="ml-title">{{ $milestone ? 'Edit milestone' : 'Add milestone' }}</h1>
    <a class="mdg-x" href="{{ $back }}" aria-label="Close"><x-tm-icon n="x" s="16" /></a>
  </div>
  <div class="mdg-b">
    <label class="mdg-f">Milestone name
      <input class="mdg-in" name="name" maxlength="255" required value="{{ $milestone?->name }}" placeholder="e.g. Chapter 1 to 3 review" autocomplete="off" @unless ($milestone) autofocus @endunless>
    </label>
    <label class="mdg-f"><span>Description <i>(optional)</i></span>
      <textarea class="mdg-in" name="description" rows="3" maxlength="2000" placeholder="What teams finish in this milestone">{{ $milestone?->description }}</textarea>
    </label>
    <div class="mdg-row">
      <label class="mdg-f">Due date
        <input class="mdg-in" type="date" name="due_on" value="{{ $milestone?->due_on?->toDateString() }}">
      </label>
      @if ($placed)
        <label class="mdg-f">Position
          <select class="mdg-in" name="position">
            @for ($i = 1; $i <= $count; $i++)
              <option value="{{ $i }}" @selected($i === $at)>{{ $i }}</option>
            @endfor
          </select>
        </label>
      @endif
    </div>
    @if ($previous)
      <label class="mdg-chk">
        <input type="checkbox" name="requires_previous" @checked($milestone?->requires_previous)>
        Start only after the team passes the defense in {{ $previous }}
      </label>
    @endif
    @if ($milestone)
      <label class="mdg-chk">
        <input type="checkbox" name="archived" @checked($milestone->archived_at)>
        Hide from teams
      </label>
    @endif
    <p class="mdg-err" id="ml-err" role="alert"></p>
  </div>
  <div class="mdg-foot">
    @if ($milestone)
      <button class="mdg-btn danger" type="button" id="ml-delete">Delete</button>
    @endif
    <span class="grow"></span>
    <a class="mdg-btn" href="{{ $back }}">Cancel</a>
    <button class="mdg-btn p" type="submit">{{ $milestone ? 'Save milestone' : 'Add milestone' }}</button>
  </div>
</form>
@endsection

@push('scripts')
@include('partials.app-dialog')
<script nonce="{{ Vite::cspNonce() }}">
  (() => {
    const form = document.getElementById('ml-milestone');
    const error = document.getElementById('ml-err');
    const name = form.elements.namedItem('name');
    @if ($milestone)
      const url = @json(route('capsa.checklist_milestones.update', $milestone));
    @endif

    // Puts the milestone before the chosen visible one; the order endpoint wants the whole checklist.
    const place = async id => {
      if (!form.position || Number(form.position.value) === {{ $at }}) { return; }
      const order = @json($order);
      const self = order.find(row => row.id === id) || { id, deliverables: [] };
      const rest = order.filter(row => row.id !== id);
      const before = @json($active).filter(other => other !== id)[Number(form.position.value) - 1];
      const index = rest.findIndex(row => row.id === before);
      rest.splice(index < 0 ? rest.length : index, 0, self);
      await mlSend(@json(route('capsa.checklists.order.update', $checklist)), 'PUT', { milestones: rest });
    };

    form.addEventListener('submit', async event => {
      event.preventDefault();
      const button = form.querySelector('[type=submit]');
      const body = {
        name: name.value.trim(),
        description: form.description.value.trim() || null,
        due_on: form.due_on.value || null,
        requires_previous: form.requires_previous ? form.requires_previous.checked : false,
      };
      if (form.archived) {
        body.archived = form.archived.checked;
      }
      if (!body.name) {
        error.textContent = 'Give the milestone a name.';
        name.focus();
        return;
      }
      error.textContent = '';
      button.disabled = true;
      try {
        @if ($milestone)
          await mlSend(url, 'PATCH', body);
          await place({{ $milestone->id }});
          location.href = @json($back);
        @else
          const made = await mlSend(@json(route('capsa.checklists.milestones.store', $checklist)), 'POST', body);
          await place(made.id);
          location.href = (@json(route('capsa.team_management.milestones.show', '__ID__'))).replace('__ID__', made.id);
        @endif
      } catch (failure) {
        button.disabled = false;
        error.textContent = failure.message;
      }
    });

    @if ($milestone)
      document.getElementById('ml-delete').addEventListener('click', async () => {
        const sure = await confirmSheet({
          title: 'Delete this milestone?',
          names: [@json($milestone->name)],
          body: 'Its requirements go with it, and teams lose their progress on them. To keep that progress, hide it instead.',
          confirmLabel: 'Delete milestone',
          danger: true,
        });
        if (!sure) { return; }
        try {
          await mlSend(url, 'DELETE');
          location.href = @json(route('capsa.team_management', ['tab' => 'milestone_list']));
        } catch (failure) {
          mlFail(failure);
        }
      });
    @endif
  })();
</script>
@endpush
