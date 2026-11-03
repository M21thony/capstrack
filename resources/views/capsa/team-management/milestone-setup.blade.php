{{-- Milestones for a program she handles that has none yet: the
     department's, another CaPSA's, or none to start. --}}
@extends('capsa.team-management._shell')

@section('title', 'Set up '.$program.' milestones')

@php
  $flag = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-label="Defense"><path d="M5 21V4"/><path d="M5 4h12l-2.5 4L17 12H5"/></svg>';
  // What teams would see for each choice, in named groups.
  $lists = ['department' => $department];
  foreach ($copies as $copy) {
      $lists['copy-'.$copy->id] = collect([(object) ['name' => null, 'milestones' => $copy->milestones->map(fn ($milestone) => (object) [
          'name' => $milestone->name,
          'defense' => $milestone->deliverables->contains('kind', \App\Models\Deliverable::DEFENSE),
      ])]]);
  }
  $lists['empty'] = collect();
  $total = $department->sum(fn ($group) => $group->milestones->count());
@endphp

@push('head')
  @include('capsa.partials.team-management._milestone_kit')
  <style nonce="{{ Vite::cspNonce() }}">
    .tmx .su{display:flex;flex-direction:column;gap:18px}
    .tmx .su h1{margin:0;font-size:26px;font-weight:800;letter-spacing:-.01em;color:var(--ink)}
    .tmx .su-sub{margin:4px 0 0;color:var(--mut);font-size:14px;display:flex;gap:6px 14px;flex-wrap:wrap}
    .tmx .su-mono{font-family:'DM Mono',ui-monospace,monospace}
    .tmx .su-cols{display:grid;grid-template-columns:minmax(0,560px) minmax(0,1fr);gap:18px;align-items:start;max-width:1100px}
    .tmx .su-card{background:var(--card);border:1px solid var(--line);border-radius:12px;margin:0}
    .tmx .su-card fieldset{border:0;margin:0;padding:0;min-width:0}
    .tmx .su-lbl{padding:18px 18px 0;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--ink2)}
    .tmx .su-opts{display:flex;flex-direction:column;gap:10px;padding:18px}
    .tmx .su-opt{display:flex;gap:14px;align-items:flex-start;padding:16px;border:1px solid var(--line);border-radius:10px;cursor:pointer;margin:0}
    .tmx .su-opt > input{width:18px;height:18px;margin:3px 0 0;accent-color:var(--accent);flex:none}
    .tmx .su-opt .k{width:36px;height:36px;border-radius:9px;background:var(--tint);border:1px solid var(--line);color:var(--ink2);display:grid;place-items:center;flex:none}
    .tmx .su-opt > div{flex:1;min-width:0}
    .tmx .su-opt b{display:block;font-size:15px;margin-bottom:2px;color:var(--ink)}
    .tmx .su-opt > div > span{font-size:13px;color:var(--mut)}
    .tmx .su-opt:has(> input:checked){border-color:var(--accent);background:var(--accent-bg)}
    .tmx .su-opt:has(> input:checked) .k{background:var(--accent);border-color:var(--accent);color:var(--on-accent)}
    .tmx .su-opt > input:focus-visible,.tmx .su-who label:has(input:focus-visible),.tmx .su-btn:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
    .tmx .su-who{list-style:none;margin:10px 0 0;padding:0;display:flex;flex-direction:column;align-items:flex-start;gap:6px;font-size:13px;color:var(--ink2)}
    .tmx .su-who label{position:relative;display:flex;justify-content:space-between;gap:14px;padding:8px 10px;border:1px solid var(--line);border-radius:8px;background:var(--card);cursor:pointer;margin:0;font-weight:400}
    .tmx .su-opt:has(> input:checked) .su-who label:has(input:checked){border-color:var(--accent)}
    .tmx .su-who input{position:absolute;opacity:0;pointer-events:none}
    .tmx .su-go{padding:0 18px 18px;display:flex;justify-content:flex-end;gap:10px;align-items:center;flex-wrap:wrap}
    .tmx .su-err{color:var(--red);font-size:13.5px;margin:0 18px 12px}
    .tmx .su-err:empty{display:none}
    .tmx .su-btn{min-height:40px;padding:0 16px;border-radius:8px;border:1px solid var(--line2);background:var(--card);color:var(--ink);font-family:inherit;font-size:14px;font-weight:600;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;white-space:nowrap;box-sizing:border-box}
    .tmx .su-btn.p{background:var(--accent);border-color:var(--accent);color:var(--on-accent)}
    .tmx .su-btn[disabled]{opacity:.6;cursor:default}
    .tmx .su-card-h{display:flex;align-items:center;gap:12px;padding:14px 18px;border-bottom:1px solid var(--line)}
    .tmx .su-card-h h2{margin:0;font-size:16px;flex:1;color:var(--ink)}
    .tmx .su-card-h .n{font-family:'DM Mono',ui-monospace,monospace;font-size:13px;color:var(--mut)}
    .tmx .su-pv{list-style:none;margin:0;padding:8px 0;max-height:520px;overflow:auto}
    .tmx .su-pv li{display:flex;gap:10px;align-items:center;padding:7px 28px 7px 18px;font-size:14px;color:var(--ink)}
    .tmx .su-pv li .su-mono{width:22px;font-size:12.5px;color:var(--mut);flex:none}
    .tmx .su-pv .def{color:var(--accent);display:inline-grid}
    .tmx .su-pv .su-div{display:block;margin-top:8px;padding:14px 18px 8px;border-top:1px solid var(--line);font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--accent)}
    @media (max-width:1100px){ .tmx .su-cols{grid-template-columns:minmax(0,1fr)} }
    @media (max-width:640px){
      .tmx .su h1{font-size:22px}
    }
  </style>
@endpush

@section('tm-content')
<div class="su">
  <div>
    <h1>Set up {{ $program }} milestones</h1>
    @if ($sections->isNotEmpty())
      <p class="su-sub"><span>{{ $sections->first() }}{{ $sections->slice(1)->map(fn ($section) => ' · '.\Illuminate\Support\Str::after($section, '-'))->implode('') }}</span></p>
    @endif
  </div>

  <div class="su-cols">
    <form class="su-card" id="ml-setup" aria-label="How to start" novalidate>
      <fieldset>
        <legend class="su-lbl">Start from</legend>
        <div class="su-opts">
          <label class="su-opt">
            <input type="radio" name="from" value="department" checked>
            <span class="k"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 21V9l8-5 8 5v12"/><path d="M9 21v-6h6v6M3 21h18"/></svg></span>
            <div><b>Use the department's milestones</b><span><span class="su-mono">{{ $total }}</span> milestones</span></div>
          </label>
          @if ($copies->isNotEmpty())
            {{-- A div, not a label: the CaPSAs below are labels of their own. --}}
            <div class="su-opt" id="su-copy">
              <input type="radio" name="from" value="copy" aria-labelledby="su-copy-b">
              <span class="k"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M4 16V6a2 2 0 0 1 2-2h10"/></svg></span>
              <div><b id="su-copy-b">Copy another CaPSA's milestones</b><span>Names, dates and requirements</span>
                <ul class="su-who" aria-label="Whose milestones to copy">
                  @foreach ($copies as $copy)
                    <li><label>
                      <input type="radio" name="copy_of" value="{{ $copy->id }}" @checked($loop->first)>
                      <span>{{ $copy->capsa?->name ?? 'Another CaPSA' }}</span>
                      <span class="su-mono">{{ $copy->milestones->count() }} {{ \Illuminate\Support\Str::plural('milestone', $copy->milestones->count()) }}</span>
                    </label></li>
                  @endforeach
                </ul>
              </div>
            </div>
          @endif
          <label class="su-opt">
            <input type="radio" name="from" value="empty">
            <span class="k"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3" stroke-dasharray="3 3"/></svg></span>
            <div><b>Start empty</b><span>No milestones</span></div>
          </label>
        </div>
      </fieldset>
      <p class="su-err" id="ml-err" role="alert"></p>
      <div class="su-go">
        <button class="su-btn p" type="submit">Set up {{ $program }} milestones</button>
      </div>
    </form>

    <section class="su-card" aria-labelledby="su-pv-h">
      <div class="su-card-h"><h2 id="su-pv-h">What teams will see</h2><span class="n" id="su-n">{{ $total }}</span></div>
      @foreach ($lists as $key => $groups)
        @php $number = 0; @endphp
        <ol class="su-pv" data-list="{{ $key }}" data-count="{{ $groups->sum(fn ($group) => $group->milestones->count()) }}" @unless ($key === 'department') hidden @endunless>
          @foreach ($groups as $group)
            @if (! $loop->first && $group->name)<li class="su-div" aria-hidden="true">{{ $group->name }}</li>@endif
            @foreach ($group->milestones as $milestone)
              <li><span class="su-mono">{{ ++$number }}</span>@if ($milestone->defense)<span class="def">{!! $flag !!}</span>@endif<span>{{ $milestone->name }}</span></li>
            @endforeach
          @endforeach
        </ol>
      @endforeach
    </section>
  </div>
</div>
@endsection

@push('scripts')
@include('partials.app-dialog')
<script nonce="{{ Vite::cspNonce() }}">
  (() => {
    const form = document.getElementById('ml-setup');
    const button = form.querySelector('[type=submit]');
    const error = document.getElementById('ml-err');
    const chosen = () => form.querySelector('[name=from]:checked').value;
    const copyOf = () => form.querySelector('[name=copy_of]:checked')?.value;
    const lists = document.querySelectorAll('[data-list]');

    // Picking a CaPSA picks Copy; the preview follows whatever is picked.
    const show = () => {
      const key = chosen() === 'copy' ? 'copy-' + copyOf() : chosen();
      lists.forEach(list => {
        list.hidden = list.dataset.list !== key;
        if (!list.hidden) { document.getElementById('su-n').textContent = list.dataset.count; }
      });
    };
    document.getElementById('su-copy')?.addEventListener('click', event => {
      if (!event.target.closest('.su-who')) {
        form.querySelector('[name=from][value=copy]').checked = true;
        show();
      }
    });
    form.addEventListener('change', event => {
      if (event.target.name === 'copy_of') { form.querySelector('[name=from][value=copy]').checked = true; }
      show();
    });

    form.addEventListener('submit', async event => {
      event.preventDefault();
      const body = { program: @json($program) };
      if (chosen() === 'copy') { body.copy_of = Number(copyOf()); }
      if (chosen() === 'empty') { body.empty = true; }
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
