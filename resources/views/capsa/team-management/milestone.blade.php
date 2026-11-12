{{-- One milestone: its status, due date and lock, and the requirements teams
     hand in for it. Configuration only; team progress lives in the tracker.
     Requirements drag into a new order by their icon; Edit opens a form. --}}
@extends('capsa.team-management._shell')

@section('title', $milestone->name)

@php
  $count = $requirements->count();
  $drag = $count > 1;
  $pencil = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4z"/><path d="m13.5 6.5 4 4"/></svg>';
  $eyeOff = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 6.1A9 9 0 0 1 12 6c5 0 9 6 9 6a17 17 0 0 1-3 3.6M6.3 7.6A17 17 0 0 0 3 12s4 6 9 6a9 9 0 0 0 4-.9"/></svg>';
@endphp

@push('head')
  @include('capsa.partials.team-management._milestone_kit')
  <style nonce="{{ Vite::cspNonce() }}">
    .tmx .ms{display:flex;flex-direction:column;gap:14px;min-height:0}
    .tmx .ms-head{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
    .tmx .ms-head .grow{flex:1;min-width:220px;display:flex;gap:12px;align-items:flex-start}
    .tmx .ms-num{width:40px;height:40px;border-radius:50%;border:1.5px solid var(--accent);color:var(--ink);display:grid;place-items:center;font-weight:600;font-size:16px;flex:none}
    .tmx .ms h1{margin:0;font-size:24px;font-weight:800;letter-spacing:-.01em;color:var(--ink)}
    .tmx .ms-sub{margin:3px 0 0;color:var(--mut);font-size:13px;display:flex;gap:4px 14px;flex-wrap:wrap;align-items:center}
    .tmx .ms-sub span{display:inline-flex;gap:6px;align-items:center}
    .tmx .ms-desc{margin:6px 0 0;color:var(--ink2);font-size:13.5px;line-height:1.5;white-space:pre-line}
    .tmx .ms-dot{width:7px;height:7px;border-radius:50%;flex:none;display:inline-block;box-sizing:border-box}
    .tmx .ms-dot.ok{background:#16a34a}
    .tmx .ms-dot.open{background:var(--blue)}
    .tmx .ms-dot.idle{border:1.5px solid var(--mut)}
    .tmx .ms-btn{min-height:34px;padding:0 13px;border-radius:8px;border:1px solid var(--line2);background:var(--card);color:var(--ink);font-family:inherit;font-size:13px;font-weight:600;display:inline-flex;gap:6px;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;white-space:nowrap;box-sizing:border-box}
    .tmx .ms-btn:hover:not([disabled]){border-color:var(--ink2);color:var(--ink)}
    .tmx .ms-btn[disabled]{opacity:.6;cursor:default}
    .tmx .ms-btn.p{background:var(--accent);border-color:var(--accent);color:var(--on-accent)}
    .tmx .ms-btn.p:hover:not([disabled]){filter:brightness(1.05);color:var(--on-accent)}
    .tmx .ms-btn.sm{min-height:28px;padding:0 10px;font-size:12.5px}
    .tmx .ms-card{flex:1 1 0;min-height:240px;display:flex;flex-direction:column;overflow:hidden;background:var(--card);border:1px solid var(--line);border-radius:12px}
    .tmx .ms-card-h{display:flex;align-items:center;flex-wrap:wrap;gap:8px 12px;padding:12px 16px}
    .tmx .ms-card-h h2{margin:0;font-size:16px;font-weight:700;flex:1;color:var(--ink)}
    .tmx .ms-n{white-space:nowrap;font-size:12.5px;color:var(--mut)}
    .tmx .ms-scroll{flex:1;min-height:0;overflow:auto;margin:0 16px 16px;border-radius:8px}
    .tmx .ms table{width:100%;min-width:0;border-collapse:separate;border-spacing:0}
    .tmx .ms thead th{position:sticky;top:0;z-index:1;background:var(--navy-dark,#18191f);color:#fff;text-align:left;font-size:12.5px;font-weight:600;letter-spacing:0;text-transform:none;padding:9px 12px;white-space:nowrap;border:0}
    .tmx .ms td{padding:9px 12px;border-bottom:1px solid var(--line);vertical-align:middle;color:var(--ink2);font-size:13px}
    .tmx .ms tbody tr:last-child td{border-bottom:0}
    .tmx .ms tbody tr:hover td{background:var(--tint)}
    .tmx .ms tbody tr.is-drag{opacity:.45}
    .tmx .ms-req{display:flex;align-items:center;gap:12px;min-width:0}
    .tmx .ms-kind{width:32px;height:32px;border-radius:8px;background:var(--tint);border:1px solid var(--line);display:grid;place-items:center;color:var(--ink2);padding:0;margin:0;flex:none;box-sizing:border-box}
    .tmx .ms-kind.k-document{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}
    .tmx .ms-kind.k-form,.tmx .ms-kind.k-custom{background:#ecfeff;border-color:#a5f3fc;color:#0e7490}
    .tmx .ms-kind.k-approval{background:#f5f3ff;border-color:#ddd6fe;color:#6d28d9}
    .tmx .ms-kind.k-defense{background:#fff7ed;border-color:#fed7aa;color:#c2410c}
    .tmx .ms-kind.k-bluebook{background:#eef2ff;border-color:#c7d2fe;color:#4338ca}
    .tmx button.ms-kind{cursor:grab}
    .tmx button.ms-kind:hover,.tmx button.ms-kind:focus-visible{border-color:var(--ink2)}
    .tmx .ms a.ms-name{font-weight:600;font-size:13.5px;color:var(--ink);text-decoration:none;overflow-wrap:anywhere}
    .tmx .ms a.ms-name:hover{text-decoration:underline}
    .tmx .ms-hid{display:block;font-size:12px;color:var(--mut)}
    .tmx .ms tr.hidden .ms-name,.tmx .ms tr.hidden .ms-kind{opacity:.55}
    .tmx .ms .c-due,.tmx .ms .c-act{white-space:nowrap}
    .tmx .ms .c-act .ms-btn + .ms-btn{margin-left:4px}
    .tmx .ms-empty{margin:0;padding:28px 16px;color:var(--mut);font-size:13.5px;text-align:center}
    @media (max-width:820px){
      .tmx .ms-card{flex:none;min-height:0}
      .tmx .ms-scroll{overflow:visible;margin:0}
      .tmx .ms table,.tmx .ms tbody{display:block}
      .tmx .ms thead{display:none}
      .tmx .ms tbody tr{display:flex;flex-direction:column;gap:5px;padding:12px 16px;border-top:1px solid var(--line)}
      .tmx .ms tbody tr td{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:0;border:0}
      .tmx .ms tbody tr td[data-label]::before{content:attr(data-label);font-size:11.5px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--mut)}
      .tmx .ms tbody tr td.c-act{justify-content:flex-end;margin-top:2px}
      .tmx .ms tbody tr:hover td{background:none}
    }
    @media (max-width:640px){
      .tmx .ms h1{font-size:21px}
      .tmx .ms-head>.ms-btn{flex:1 1 auto}
    }
  </style>
@endpush

@section('tm-content')
<div class="ms">
  <div class="ms-head">
    <div class="grow">
      @if ($row?->number)
        <span class="ms-num" aria-label="Milestone {{ $row->number }}">{{ $row->number }}</span>
      @endif
      <div>
        <h1>{{ $milestone->name }}</h1>
        <p class="ms-sub">
          @if ($milestone->archived_at)
            <span><i class="ms-dot idle"></i>Hidden from teams</span>
          @elseif ($milestone->isClosed())
            <span><i class="ms-dot ok"></i>Finished {{ $milestone->closed_at->format('M j, Y') }}</span>
          @elseif (! $milestone->isOpened())
            <span><i class="ms-dot idle"></i>Not open yet</span>
          @else
            <span><i class="ms-dot open"></i>Open to teams</span>
          @endif
          @if ($row?->due_on)
            <span><x-tm-icon n="cal" s="14" />Due {{ $row->due_on->format('M j, Y') }}</span>
          @endif
          @if ($milestone->requires_previous && $row?->previous)
            <span><x-tm-icon n="lock" s="14" />Opens after {{ $row->previous }}</span>
          @endif
        </p>
        @if ($milestone->description)
          <p class="ms-desc">{{ $milestone->description }}</p>
        @endif
      </div>
    </div>
    <a class="ms-btn" href="{{ route('capsa.team_management.milestones.edit', $milestone) }}">{!! $pencil !!}Edit milestone</a>
    @if ($milestone->archived_at)
    @elseif (! $milestone->isOpened())
      <button class="ms-btn p" type="button" id="ml-close" data-body='{"opened":true}'>Open milestone</button>
    @elseif ($milestone->isClosed())
      <button class="ms-btn" type="button" id="ml-close" data-body='{"closed":false}'>Open milestone</button>
    @else
      <button class="ms-btn" type="button" id="ml-close" data-body='{"closed":true}'><x-tm-icon n="check" s="14" />Close milestone</button>
    @endif
  </div>

  <section class="ms-card" aria-labelledby="ms-reqs-h">
    <div class="ms-card-h">
      <h2 id="ms-reqs-h">Requirements</h2>
      <span class="ms-n">{{ $count }} {{ \Illuminate\Support\Str::plural('requirement', $count) }}</span>
      <a class="ms-btn p" href="{{ route('capsa.team_management.milestones.requirements.create', $milestone) }}"><x-tm-icon n="plus" s="14" />Add requirement</a>
    </div>
    @if ($requirements->isEmpty())
      <p class="ms-empty">No requirements yet.</p>
    @else
      <div class="ms-scroll">
        <table>
          <thead><tr><th>Requirement</th><th>Submission type</th><th>Due</th><th>Action</th></tr></thead>
          <tbody id="ml-reqs">
            @foreach ($requirements as $requirement)
              @php
                $type = \App\Http\Controllers\Capsa\MilestonePageController::typeOf($requirement);
                $edit = route('capsa.team_management.milestones.requirements.edit', $requirement);
                $icon = \App\Http\Controllers\Capsa\MilestonePageController::iconOf($requirement);
              @endphp
              <tr class="{{ $requirement->archived_at ? 'hidden' : '' }}" data-id="{{ $requirement->id }}">
                <td>
                  <div class="ms-req">
                    @if ($drag)
                      <button class="ms-kind ml-grip k-{{ $requirement->kind }}" type="button" title="Drag to move" aria-label="Move {{ $requirement->title }}: drag, or use the up and down arrow keys">@if ($requirement->archived_at){!! $eyeOff !!}@else<x-tm-icon :n="$icon" s="16" />@endif</button>
                    @else
                      <span class="ms-kind k-{{ $requirement->kind }}">@if ($requirement->archived_at){!! $eyeOff !!}@else<x-tm-icon :n="$icon" s="16" />@endif</span>
                    @endif
                    <div>
                      <a class="ms-name" href="{{ $edit }}">{{ $requirement->title }}</a>
                      @if ($requirement->archived_at)<span class="ms-hid">Hidden from teams</span>@endif
                    </div>
                  </div>
                </td>
                <td data-label="Submission type">{{ $type ?? '—' }}</td>
                <td class="c-due" data-label="Due">{{ $requirement->due_date?->format('M j, Y') ?? '—' }}</td>
                <td class="c-act">
                  @if ($requirement->archived_at)
                    <button class="ms-btn sm" type="button" data-show="{{ route('capsa.deliverables.update', $requirement) }}">Show to teams</button>
                  @endif
                  <a class="ms-btn sm" href="{{ $edit }}" aria-label="Edit {{ $requirement->title }}">{!! $pencil !!}Edit</a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </section>
</div>
@endsection

@push('scripts')
@include('partials.app-dialog')
<script nonce="{{ Vite::cspNonce() }}">
  // On a wide screen the page fills the window and the list scrolls inside its card.
  (() => {
    const box = document.querySelector('.tmx .ms');
    const fit = () => box.style.height = innerWidth > 820
      ? Math.max(380, innerHeight - box.getBoundingClientRect().top - 26) + 'px' : '';
    fit();
    addEventListener('resize', fit);
    const real = document.getElementById('real-content');
    if (real) new MutationObserver(fit).observe(real, { attributes: true, attributeFilter: ['style'] });
  })();

  document.getElementById('ml-close')?.addEventListener('click', async event => {
    const button = event.currentTarget;
    button.disabled = true;
    try {
      await mlSend(@json(route('capsa.checklist_milestones.update', $milestone)), 'PATCH', JSON.parse(button.dataset.body));
      location.reload();
    } catch (error) {
      button.disabled = false;
      mlFail(error);
    }
  });

  document.querySelectorAll('[data-show]').forEach(button => button.addEventListener('click', async () => {
    button.disabled = true;
    try {
      await mlSend(button.dataset.show, 'PATCH', { archived: false });
      location.reload();
    } catch (error) {
      button.disabled = false;
      mlFail(error);
    }
  }));

  // The order endpoint wants every milestone, so only this one's list changes.
  const reqs = document.getElementById('ml-reqs');
  if (reqs && reqs.children.length > 1) {
    mlSortable(reqs, async ids => {
      const order = (@json($order)).map(milestone => milestone.id === {{ $milestone->id }} ? { id: milestone.id, deliverables: ids } : milestone);
      await mlSend(@json(route('capsa.checklists.order.update', $milestone->checklist_id)), 'PUT', { milestones: order });
    });
  }
</script>
@endpush
