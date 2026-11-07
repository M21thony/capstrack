{{-- Add a requirement to a milestone, or change one: its name, due date,
     instructions, and whether teams upload a file or pass a defense.
     Editing adds hiding and removing it. --}}
@extends('capsa.team-management._shell')

@section('title', $deliverable ? 'Edit requirement' : 'Add requirement')

@push('head')
  @include('capsa.partials.team-management._milestone_kit')
  <style nonce="{{ Vite::cspNonce() }}">
    .tmx .rq{max-width:680px;padding:22px;display:flex;flex-direction:column;gap:18px}
    .tmx .rq-in{margin:0;color:var(--mut);font-size:14px}
    .tmx .rq-in b{color:var(--ink)}
    .tmx .rq-row{display:grid;grid-template-columns:1fr 240px;gap:16px}
    .tmx .rq-f{display:flex;flex-direction:column;gap:6px;font-weight:600;font-size:14px;color:var(--ink)}
    .tmx .rq-f[hidden]{display:none}
    .tmx .rq-inp{min-height:42px;border:1px solid var(--line2);border-radius:8px;padding:0 12px;font-family:inherit;font-size:15px;font-weight:400;color:var(--ink);background:var(--card);width:100%;box-sizing:border-box}
    .tmx textarea.rq-inp{padding:10px 12px;line-height:1.5;resize:vertical;min-height:96px}
    .tmx .rq-types{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .tmx .rq-chk{display:flex;gap:12px;align-items:center;padding:14px;border:1px solid var(--line);border-radius:10px;cursor:pointer;font-weight:600}
    .tmx .rq-chk input{width:18px;height:18px;margin:0;accent-color:var(--accent);flex:none}
    .tmx .rq-chk:has(input:checked){border-color:var(--accent);background:var(--accent-bg)}
    .tmx .rq-err{color:var(--red);font-size:13px;margin:-6px 0 0}
    .tmx .rq-err:empty{display:none}
    .tmx .rq-foot{display:flex;gap:10px;align-items:center;border-top:1px solid var(--line);padding-top:16px;flex-wrap:wrap}
    .tmx .rq-foot .grow{flex:1}
    .tmx .rq-btn{min-height:40px;padding:0 16px;border-radius:8px;border:1px solid var(--line2);background:var(--card);color:var(--ink);font-family:inherit;font-size:14px;font-weight:600;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;white-space:nowrap}
    .tmx .rq-btn:hover:not([disabled]){border-color:var(--ink2)}
    .tmx .rq-btn[disabled]{opacity:.6;cursor:default}
    .tmx .rq-btn.p{background:var(--accent);border-color:var(--accent);color:var(--on-accent)}
    .tmx .rq-btn.p:hover:not([disabled]){filter:brightness(1.06)}
    .tmx .rq-btn.q{border-color:transparent;background:transparent;color:var(--ink2);padding:0 6px}
    .tmx .rq-btn.danger{border-color:transparent;background:transparent;color:var(--red);padding:0 6px}
    @media (max-width:640px){
      .tmx .rq{padding:16px}
      .tmx .rq-row{grid-template-columns:minmax(0,1fr)}
    }
  </style>
@endpush

@section('tm-content')
<form class="card rq" id="ml-requirement" novalidate aria-label="{{ $deliverable ? 'Edit requirement' : 'Add requirement' }}">
  <p class="rq-in">In <b>{{ $milestone->name }}</b></p>
  <div class="rq-row">
    <label class="rq-f">Requirement name
      <input class="rq-inp" name="title" maxlength="255" required value="{{ $deliverable?->title }}" autocomplete="off" @unless ($deliverable) autofocus @endunless>
    </label>
    <label class="rq-f">Due date
      <input class="rq-inp" type="date" name="due_date" value="{{ $deliverable?->due_date?->toDateString() }}">
    </label>
  </div>
  <label class="rq-f">Instructions
    <textarea class="rq-inp" name="instructions" rows="4" maxlength="5000">{{ $deliverable?->instructions }}</textarea>
  </label>
  <div class="rq-f" role="radiogroup" aria-labelledby="ml-type-label"><span id="ml-type-label">Type</span>
    <div class="rq-types">
      <label class="rq-chk"><input type="radio" name="type" value="upload" @checked(! $defense)>Upload file</label>
      <label class="rq-chk"><input type="radio" name="type" value="defense" @checked($defense)>Defense</label>
    </div>
  </div>
  <label class="rq-f" id="ml-stage" @unless ($defense) hidden @endunless>Which defense
    <select class="rq-inp" name="defense_type">
      @foreach ($stages as $stage)
        <option @selected(($deliverable?->defense_type ?: 'Title Defense') === $stage)>{{ $stage }}</option>
      @endforeach
    </select>
  </label>
  <p class="rq-err" id="ml-err" role="alert"></p>

  <div class="rq-foot">
    @if ($deliverable)
      <button class="rq-btn danger" type="button" id="ml-remove">Remove requirement</button>
      <button class="rq-btn q" type="button" id="ml-hide">{{ $deliverable->archived_at ? 'Show to teams again' : 'Hide from teams' }}</button>
    @endif
    <span class="grow"></span>
    <a class="rq-btn" href="{{ route('capsa.team_management.milestones.show', $milestone) }}">Cancel</a>
    <button class="rq-btn p" type="submit">{{ $deliverable ? 'Save requirement' : 'Add requirement' }}</button>
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
    const back = @json(route('capsa.team_management.milestones.show', $milestone));
    const isDefense = () => field('type').value === 'defense';

    form.querySelectorAll('[name=type]').forEach(radio => radio.addEventListener('change', () => {
      document.getElementById('ml-stage').hidden = !isDefense();
    }));

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
      // An upload keeps its own routing (draft, CLF, form) unless it is new or was a defense.
      if (isDefense()) {
        Object.assign(body, { kind: 'defense', defense_type: field('defense_type').value });
      } else if (@json(! $deliverable || $defense)) {
        body.kind = @json($milestone->deliverables()->where('kind', \App\Models\Deliverable::BLUEBOOK)->exists() ? \App\Models\Deliverable::BLUEBOOK : 'form');
      }
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
