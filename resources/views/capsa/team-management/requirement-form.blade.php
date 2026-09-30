{{-- Add a requirement to a milestone, or change one: its name, how teams
     complete it, and its due date. Editing adds hiding and removing it. --}}
@extends('capsa.team-management._shell')

@section('title', $deliverable ? 'Edit requirement' : 'Add requirement')

@push('head')
  @include('capsa.partials.team-management._milestone_kit')
@endpush

@section('tm-content')
<form class="ml-form" id="ml-requirement" novalidate>
  <div class="ml-head">
    <div>
      <h1 class="ml-title">{{ $deliverable ? 'Edit requirement' : 'Add requirement' }}</h1>
      <div class="ml-sub">In {{ $milestone->name }}</div>
    </div>
  </div>

  <label class="ml-f">Name
    <input name="title" maxlength="255" required value="{{ $deliverable?->title }}" autocomplete="off" @unless ($deliverable) autofocus @endunless>
  </label>
  <label class="ml-f">How teams complete it
    <select name="how">
      @foreach ($ways as $key => $way)
        <option value="{{ $key }}" @selected($how === $key)>{{ $way['label'] }}</option>
      @endforeach
    </select>
    <small id="ml-how-help">{{ $ways[$how]['help'] ?? '' }}</small>
  </label>
  <label class="ml-f">Due date <small>Optional.</small>
    <input type="date" name="due_date" value="{{ $deliverable?->due_date?->toDateString() }}">
  </label>
  <div class="ml-err" id="ml-err" role="alert"></div>

  <div class="ml-foot">
    <a class="ml-btn" href="{{ route('capsa.team_management.milestones.show', $milestone) }}">Cancel</a>
    <button class="ml-btn ml-gold" type="submit">{{ $deliverable ? 'Save changes' : 'Add requirement' }}</button>
  </div>

  @if ($deliverable)
    <div class="ml-danger">
      <div>
        <b>{{ $deliverable->archived_at ? 'Hidden from teams' : 'Hide or remove' }}</b>
        {{ $deliverable->archived_at ? 'Teams do not see this requirement. Show it again to bring it back with their progress.' : 'Hiding keeps what teams already sent. Removing takes it off the milestone for good.' }}
      </div>
      <button class="ml-btn" type="button" id="ml-hide">{{ $deliverable->archived_at ? 'Show to teams again' : 'Hide from teams' }}</button>
      <button class="ml-btn ml-red" type="button" id="ml-remove">Remove requirement</button>
    </div>
  @endif
</form>
@endsection

@push('scripts')
@include('partials.app-dialog')
<script>
  (() => {
    const ways = @json($ways);
    const form = document.getElementById('ml-requirement');
    const field = name => form.elements.namedItem(name);
    const error = document.getElementById('ml-err');
    const back = @json(route('capsa.team_management.milestones.show', $milestone));

    field('how').addEventListener('change', () => {
      document.getElementById('ml-how-help').textContent = ways[field('how').value].help;
    });

    form.addEventListener('submit', async event => {
      event.preventDefault();
      const button = form.querySelector('[type=submit]');
      const title = field('title').value.trim();
      const way = ways[field('how').value];
      if (!title) {
        error.textContent = 'Give the requirement a name.';
        field('title').focus();
        return;
      }
      const body = { kind: way.kind, role: way.role, due_date: field('due_date').value || null };
      error.textContent = '';
      button.disabled = true;
      try {
        @if ($deliverable)
          await mlSend(@json(route('capsa.deliverables.update', $deliverable)), 'PATCH', { title, ...body });
        @else
          await mlSend(@json(route('capsa.checklist_milestones.deliverables.store', $milestone)), 'POST', { titles: [title], ...body });
        @endif
        location.href = back;
      } catch (failure) {
        button.disabled = false;
        error.textContent = failure.message;
      }
    });

    @if ($deliverable)
      document.getElementById('ml-hide').addEventListener('click', async event => {
        const button = event.currentTarget;
        button.disabled = true;
        try {
          await mlSend(@json(route('capsa.deliverables.update', $deliverable)), 'PATCH', { archived: {{ $deliverable->archived_at ? 'false' : 'true' }} });
          location.href = back;
        } catch (failure) {
          button.disabled = false;
          mlFail(failure);
        }
      });

      document.getElementById('ml-remove').addEventListener('click', async () => {
        const sure = await confirmSheet({
          title: 'Remove this requirement?',
          names: [@json($deliverable->title)],
          body: 'Teams lose their progress on it. To keep that, hide it instead.',
          confirmLabel: 'Remove requirement',
          danger: true,
        });
        if (!sure) { return; }
        try {
          await mlSend(@json(route('capsa.deliverables.destroy_many')), 'DELETE', { ids: [{{ $deliverable->id }}] });
          location.href = back;
        } catch (failure) {
          mlFail(failure);
        }
      });
    @endif
  })();
</script>
@endpush
