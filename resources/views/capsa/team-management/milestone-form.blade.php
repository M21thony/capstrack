{{-- New milestone, or one milestone's name, description, due date and lock.
     Editing adds hiding it from teams and deleting it. --}}
@extends('capsa.team-management._shell')

@section('title', $milestone ? 'Edit '.$milestone->name : 'New milestone')

@push('head')
  @include('capsa.partials.team-management._milestone_kit')
  <style nonce="{{ Vite::cspNonce() }}">
    .tmx .mf{max-width:680px;padding:22px;display:flex;flex-direction:column;gap:18px;background:var(--card);border:1px solid var(--line);border-radius:12px;box-sizing:border-box}
    .tmx .mf-f{display:flex;flex-direction:column;gap:6px;font-weight:600;font-size:14px;color:var(--ink);margin:0}
    .tmx .mf-inp{min-height:42px;border:1px solid var(--line2);border-radius:8px;padding:0 12px;font-family:inherit;font-size:15px;font-weight:400;color:var(--ink);background:var(--card);width:100%;box-sizing:border-box}
    .tmx textarea.mf-inp{padding:10px 12px;line-height:1.5;resize:vertical;min-height:96px}
    .tmx .mf-inp[readonly]{background:var(--tint);color:var(--ink2);font-family:var(--f-num)}
    .tmx .mf-inp:focus-visible,.tmx .mf-chk input:focus-visible,.tmx .mf-btn:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
    .tmx .mf-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .tmx .mf-chk{display:flex;gap:12px;align-items:center;padding:14px;border:1px solid var(--line);border-radius:10px;cursor:pointer;font-weight:600;font-size:14px;color:var(--ink);margin:0}
    .tmx .mf-chk input{width:18px;height:18px;margin:0;accent-color:var(--accent);flex:none}
    .tmx .mf-chk:has(input:checked){border-color:var(--accent);background:var(--accent-bg)}
    .tmx .mf-err{color:var(--red);font-size:13.5px;margin:0}
    .tmx .mf-err:empty{display:none}
    .tmx .mf-foot{display:flex;gap:10px;align-items:center;border-top:1px solid var(--line);padding-top:16px;flex-wrap:wrap}
    .tmx .mf-foot .grow{flex:1}
    .tmx .mf-btn{min-height:40px;padding:0 16px;border-radius:8px;border:1px solid var(--line2);background:var(--card);color:var(--ink);font-family:inherit;font-size:14px;font-weight:600;display:inline-flex;gap:8px;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;white-space:nowrap;box-sizing:border-box}
    .tmx .mf-btn:hover:not([disabled]){border-color:var(--ink2)}
    .tmx .mf-btn[disabled]{opacity:.6;cursor:default}
    .tmx .mf-btn.p{background:var(--accent);border-color:var(--accent);color:var(--on-accent)}
    .tmx .mf-btn.p:hover:not([disabled]){filter:brightness(1.06)}
    .tmx .mf-btn.danger{border-color:transparent;background:transparent;color:var(--red);padding:0 6px}
    @media (max-width:640px){
      .tmx .mf{padding:16px}
      .tmx .mf-row{grid-template-columns:minmax(0,1fr)}
      .tmx .mf-foot .danger{flex-basis:100%;justify-content:flex-start}
    }
  </style>
@endpush

@section('tm-content')
<form class="mf" id="ml-milestone" aria-label="{{ $milestone ? 'Edit milestone' : 'New milestone' }}" novalidate>
  <label class="mf-f">Milestone name
    <input class="mf-inp" name="name" maxlength="255" required value="{{ $milestone?->name }}" autocomplete="off" @unless ($milestone) autofocus @endunless>
  </label>
  <label class="mf-f">Description
    <textarea class="mf-inp" name="description" rows="4" maxlength="2000">{{ $milestone?->description }}</textarea>
  </label>
  <div class="mf-row">
    <label class="mf-f">Due date
      <input class="mf-inp" type="date" name="due_on" value="{{ $milestone?->due_on?->toDateString() }}">
    </label>
    <label class="mf-f">Place in the list
      <input class="mf-inp" value="{{ $place }}" readonly tabindex="-1">
    </label>
  </div>
  @if ($previous)
    <label class="mf-chk">
      <input type="checkbox" name="requires_previous" @checked($milestone?->requires_previous)>
      Teams can start this only after they pass the defense in {{ $previous }}
    </label>
  @endif
  @if ($milestone)
    <label class="mf-chk">
      <input type="checkbox" name="archived" @checked($milestone->archived_at)>
      Hide from teams
    </label>
  @endif
  <p class="mf-err" id="ml-err" role="alert"></p>

  <div class="mf-foot">
    @if ($milestone)
      <button class="mf-btn danger" type="button" id="ml-delete">Delete milestone</button>
    @endif
    <span class="grow"></span>
    <a class="mf-btn" href="{{ $milestone ? route('capsa.team_management.milestones.show', $milestone) : route('capsa.team_management', ['tab' => 'milestone_list']) }}">Cancel</a>
    <button class="mf-btn p" type="submit">{{ $milestone ? 'Save changes' : 'Create milestone' }}</button>
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
      const back = @json(route('capsa.team_management.milestones.show', $milestone));
    @endif

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
          location.href = back;
        @else
          const made = await mlSend(@json(route('capsa.checklists.milestones.store', $checklist)), 'POST', body);
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
