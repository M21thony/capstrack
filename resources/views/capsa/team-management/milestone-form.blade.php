{{-- New milestone, or one milestone's name, due date and lock. Editing adds
     hiding it from teams and deleting it. --}}
@extends('capsa.team-management._shell')

@section('title', $milestone ? 'Edit '.$milestone->name : 'New milestone')

@push('head')
  @include('capsa.partials.team-management._milestone_kit')
@endpush

@section('tm-content')
<form class="ml-form" id="ml-milestone" novalidate>
  <div class="ml-head">
    <h1 class="ml-title">{{ $milestone ? 'Edit milestone' : 'New milestone' }}</h1>
  </div>

  <label class="ml-f">Name
    <input name="name" maxlength="255" required value="{{ $milestone?->name }}" autocomplete="off" @unless ($milestone) autofocus @endunless>
  </label>
  <label class="ml-f">Due date <small>Optional. Teams see it, and reminders go out before it.</small>
    <input type="date" name="due_on" value="{{ $milestone?->due_on?->toDateString() }}">
  </label>
  @if ($previous)
    <label class="ml-check">
      <input type="checkbox" name="requires_previous" @checked($milestone?->requires_previous)>
      <span>Teams can start this only after they finish {{ $previous }}</span>
    </label>
  @endif
  @unless ($milestone)
    <p class="ml-note">It goes at the end of the list. Drag it up if it belongs earlier.</p>
  @endunless
  <div class="ml-err" id="ml-err" role="alert"></div>

  <div class="ml-foot">
    <a class="ml-btn" href="{{ $milestone ? route('capsa.team_management.milestones.show', $milestone) : route('capsa.team_management', ['tab' => 'milestone_list']) }}">Cancel</a>
    <button class="ml-btn ml-gold" type="submit">{{ $milestone ? 'Save changes' : 'Create milestone' }}</button>
  </div>

  @if ($milestone)
    <div class="ml-danger">
      <div>
        <b>{{ $milestone->archived_at ? 'Hidden from teams' : 'Hide or delete' }}</b>
        {{ $milestone->archived_at ? 'Teams do not see this milestone. Show it again to bring it back with their progress.' : 'Hiding keeps every team\'s progress. Deleting removes the milestone and its requirements for good.' }}
      </div>
      <button class="ml-btn" type="button" id="ml-hide">{{ $milestone->archived_at ? 'Show to teams again' : 'Hide from teams' }}</button>
      <button class="ml-btn ml-red" type="button" id="ml-delete">Delete</button>
    </div>
  @endif
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
      const back = @json(route('capsa.team_management.milestones.show', $milestone));
    @endif

    form.addEventListener('submit', async event => {
      event.preventDefault();
      const button = form.querySelector('[type=submit]');
      const body = {
        name: name.value.trim(),
        due_on: form.due_on.value || null,
        requires_previous: form.requires_previous ? form.requires_previous.checked : false,
      };
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
          location.href = back;
        @else
          const made = await mlSend(@json(route('capsa.checklists.milestones.store', $checklist)), 'POST', body);
          location.href = @json(route('capsa.team_management.milestones.show', '__ID__')).replace('__ID__', made.id);
        @endif
      } catch (failure) {
        button.disabled = false;
        error.textContent = failure.message;
      }
    });

    @if ($milestone)
      document.getElementById('ml-hide').addEventListener('click', async event => {
        const button = event.currentTarget;
        button.disabled = true;
        try {
          await mlSend(url, 'PATCH', { archived: {{ $milestone->archived_at ? 'false' : 'true' }} });
          location.reload();
        } catch (failure) {
          button.disabled = false;
          mlFail(failure);
        }
      });

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
