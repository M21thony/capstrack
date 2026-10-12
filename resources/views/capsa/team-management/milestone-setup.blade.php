{{-- Milestones for a program she handles that has none yet: the
     department's, another CaPSA's, or none to start. --}}
@extends('capsa.team-management._shell')

@section('title', 'Set up '.$program.' milestones')

@push('head')
  @include('capsa.partials.team-management._milestone_kit')
@endpush

@section('tm-content')
<form class="ml-form" id="ml-setup" novalidate>
  <div class="ml-head">
    <div>
      <div class="ml-sub">Every {{ $program }} section you handle will follow these. You can change anything later.</div>
    </div>
  </div>

  <label class="ml-choice">
    <input type="radio" name="from" value="department" checked data-label="Add the {{ $department->count() }} department milestones">
    <span>
      <b>Use the department's milestones</b>
      {{ $department->implode(', ') }}
    </span>
  </label>
  @if ($copies->isNotEmpty())
    <label class="ml-choice">
      <input type="radio" name="from" value="copy" data-label="Copy these milestones">
      <span>
        <b>Copy another CaPSA's milestones</b>
        Their list, due dates and requirements, as a start of your own.
        <span class="ml-more">
          <select name="copy_of" aria-label="Whose milestones to copy">
            @foreach ($copies as $copy)
              <option value="{{ $copy->id }}">{{ $copy->capsa?->name ?? 'Another CaPSA' }} · {{ $copy->milestones_count }} {{ \Illuminate\Support\Str::plural('milestone', $copy->milestones_count) }}</option>
            @endforeach
          </select>
        </span>
      </span>
    </label>
  @endif
  <label class="ml-choice">
    <input type="radio" name="from" value="empty" data-label="Start with no milestones">
    <span>
      <b>Start empty</b>
      Add each milestone yourself.
    </span>
  </label>
  <div class="ml-err" id="ml-err" role="alert"></div>

  <div class="ml-foot">
    <a class="ml-btn" href="{{ route('capsa.team_management', ['tab' => 'milestone_list']) }}">Cancel</a>
    <button class="ml-btn ml-gold" type="submit">Add the {{ $department->count() }} department milestones</button>
  </div>
</form>
@endsection

@push('scripts')
@include('partials.app-dialog')
<script nonce="{{ Vite::cspNonce() }}">
  (() => {
    const form = document.getElementById('ml-setup');
    const button = form.querySelector('[type=submit]');
    const error = document.getElementById('ml-err');
    const chosen = () => form.querySelector('[name=from]:checked');

    form.addEventListener('change', () => { button.textContent = chosen().dataset.label; });

    form.addEventListener('submit', async event => {
      event.preventDefault();
      const body = { program: @json($program) };
      if (chosen().value === 'copy') { body.copy_of = Number(form.elements.namedItem('copy_of').value); }
      if (chosen().value === 'empty') { body.empty = true; }
      error.textContent = '';
      button.disabled = true;
      try {
        await mlSend(@json(route('capsa.checklists.store')), 'POST', body);
        location.href = @json(route('capsa.team_management', ['tab' => 'milestone_list']));
      } catch (failure) {
        button.disabled = false;
        error.textContent = failure.message;
      }
    });
  })();
</script>
@endpush
