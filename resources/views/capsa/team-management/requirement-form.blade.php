{{-- Add a requirement to a milestone, or change one: its name, instructions,
     type, who signs it, due date and place in the milestone. Editing adds
     hiding and removing it. --}}
@extends('capsa.team-management._shell')

@section('title', $deliverable ? 'Edit requirement' : 'Add requirement')

@php
  $back = route('capsa.team_management.milestones.show', $milestone);
  $siblings = collect($order)->firstWhere('id', $milestone->id)['deliverables'] ?? [];
  $count = count($siblings) + ($deliverable ? 0 : 1);
  $at = $deliverable ? array_search($deliverable->id, $siblings) + 1 : $count;
  $role = $deliverable?->kind === \App\Models\Deliverable::APPROVAL ? $deliverable->role : '';
  $bluebook = $milestone->deliverables()->where('kind', \App\Models\Deliverable::BLUEBOOK)->exists();
  // Who the uploads go to, all of whom must approve; a bluebook line, the title recommendation and the archive entry keep their own way.
  $canSend = ! $bluebook && ! in_array($deliverable?->template_key, [\App\Models\Deliverable::TITLE_RECOMMENDATION, \App\Models\Deliverable::ARCHIVE_ENTRY], true);
  $sendTo = $deliverable?->recipients ?? [];
@endphp

@push('head')
  @include('capsa.partials.team-management._milestone_kit')
@endpush

@section('tm-content')
<form class="mdg" id="ml-requirement" novalidate aria-labelledby="ml-title">
  <div class="mdg-h">
    <h1 id="ml-title">{{ $deliverable ? 'Edit requirement' : 'Add requirement' }}<small>In <b>{{ $milestone->name }}</b></small></h1>
    <a class="mdg-x" href="{{ $back }}" aria-label="Close"><x-tm-icon n="x" s="16" /></a>
  </div>
  <div class="mdg-b">
    <label class="mdg-f">Requirement name
      <input class="mdg-in" name="title" maxlength="255" required value="{{ $deliverable?->title }}" placeholder="e.g. Signed adviser acceptance form" autocomplete="off" @unless ($deliverable) autofocus @endunless>
    </label>
    <label class="mdg-f"><span>Instructions <i>(optional)</i></span>
      <textarea class="mdg-in" name="instructions" rows="3" maxlength="5000" placeholder="What should be submitted?">{{ $deliverable?->instructions }}</textarea>
    </label>
    <div class="mdg-row">
      <label class="mdg-f">Type
        <select class="mdg-in" name="type">
          <option value="upload" @selected(! $defense)>Upload file</option>
          <option value="defense" @selected($defense)>Defense</option>
        </select>
      </label>
      <label class="mdg-f" id="ml-role" @if ($defense) hidden @endif>Assigned role
        <select class="mdg-in" name="role">
          <option value="" @selected(! $role)>Team</option>
          <option value="adviser" @selected($role === 'adviser')>Adviser</option>
          <option value="consultant" @selected($role === 'consultant')>Consultant</option>
        </select>
      </label>
      <label class="mdg-f" id="ml-stage" @unless ($defense) hidden @endunless>Which defense
        <select class="mdg-in" name="defense_type">
          @foreach ($stages as $stage)
            <option @selected(($deliverable?->defense_type ?: 'Title Defense') === $stage)>{{ $stage }}</option>
          @endforeach
        </select>
      </label>
    </div>
    @if ($canSend)
      <div class="mdg-f" id="ml-send" @if ($defense || $role) hidden @endif><span>Send to <i>(all must approve)</i></span>
        <div class="mdg-chks">
          @foreach (\App\Support\SubmissionRecipients::LABELS as $value => $label)
            <label class="mdg-chk"><input type="checkbox" name="recipients" value="{{ $value }}" @checked(in_array($value, $sendTo, true))> {{ $label }}</label>
          @endforeach
        </div>
      </div>
    @endif
    <div class="mdg-row">
      <label class="mdg-f">Due date
        <input class="mdg-in" type="date" name="due_date" value="{{ $deliverable?->due_date?->toDateString() }}">
      </label>
      <label class="mdg-f">Position
        <select class="mdg-in" name="position">
          @for ($i = 1; $i <= $count; $i++)
            <option value="{{ $i }}" @selected($i === $at)>{{ $i }}</option>
          @endfor
        </select>
      </label>
    </div>
    @if ($deliverable && ! $defense)
      <label class="mdg-chk"><input type="checkbox" name="closed" @checked($deliverable->closed_at)> Closed — no team can upload to it</label>
    @endif
    <p class="mdg-err" id="ml-err" role="alert"></p>
  </div>
  <div class="mdg-foot">
    @if ($deliverable)
      <button class="mdg-btn danger" type="button" id="ml-remove">Remove</button>
      <button class="mdg-btn q" type="button" id="ml-hide">{{ $deliverable->archived_at ? 'Show to teams' : 'Hide from teams' }}</button>
    @endif
    <span class="grow"></span>
    <a class="mdg-btn" href="{{ $back }}">Cancel</a>
    <button class="mdg-btn p" type="submit">{{ $deliverable ? 'Save requirement' : 'Add requirement' }}</button>
  </div>
</form>
@endsection

@push('scripts')
@include('partials.app-dialog')
<script nonce="{{ Vite::cspNonce() }}">
  (() => {
    const form = document.getElementById('ml-requirement');
    const field = name => form.elements.namedItem(name);
    const error = document.getElementById('ml-err');
    const back = @json($back);
    const isDefense = () => field('type').value === 'defense';

    const send = document.getElementById('ml-send');
    const showSend = () => { if (send) { send.hidden = isDefense() || !!field('role').value; } };
    field('role').addEventListener('change', showSend);
    field('type').addEventListener('change', () => {
      document.getElementById('ml-stage').hidden = !isDefense();
      document.getElementById('ml-role').hidden = isDefense();
      showSend();
    });

    // Moves this requirement to the chosen place; the order endpoint wants the whole checklist.
    const place = async id => {
      const at = Number(field('position').value);
      if (at === {{ $at }}) { return; }
      const order = @json($order);
      const mine = order.find(milestone => milestone.id === {{ $milestone->id }});
      mine.deliverables = mine.deliverables.filter(other => other !== id);
      mine.deliverables.splice(at - 1, 0, id);
      await mlSend(@json(route('capsa.checklists.order.update', $milestone->checklist_id)), 'PUT', { milestones: order });
    };

    form.addEventListener('submit', async event => {
      event.preventDefault();
      const button = form.querySelector('[type=submit]');
      const title = field('title').value.trim();
      if (!title) {
        error.textContent = 'Give the requirement a name.';
        field('title').focus();
        return;
      }
      const body = { instructions: field('instructions').value.trim() || null, due_date: field('due_date').value || null };
      if (isDefense()) {
        Object.assign(body, { kind: 'defense', defense_type: field('defense_type').value });
      } else if (field('role').value) {
        Object.assign(body, { kind: 'approval', role: field('role').value });
      } else if (@json(! $deliverable || $defense || $role)) {
        // An upload keeps its own routing (draft, form) unless it is new or changes type.
        body.kind = @json($bluebook ? \App\Models\Deliverable::BLUEBOOK : 'form');
      }
      if (send && !send.hidden) {
        body.recipients = [...send.querySelectorAll('input:checked')].map(box => box.value);
      }
      if (field('closed')) {
        body.closed = field('closed').checked;
      }
      error.textContent = '';
      button.disabled = true;
      try {
        @if ($deliverable)
          await mlSend(@json(route('capsa.deliverables.update', $deliverable)), 'PATCH', { title, ...body });
          await place({{ $deliverable->id }});
        @else
          const made = await mlSend(@json(route('capsa.checklist_milestones.deliverables.store', $milestone)), 'POST', { titles: [title], ...body });
          await place(made.deliverables[0].id);
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
