{{-- One milestone: its due date and lock, its requirements with how many
     teams are done with each, and which teams are past due. Requirements
     drag into a new order by their icon; Edit opens a form. --}}
@extends('capsa.team-management._shell')

@section('title', $milestone->name)

@php
  $shown = $requirements->whereNull('archived_at')->count();
  $hidden = $requirements->count() - $shown;
  $drag = $requirements->count() > 1;
  $complete = $row?->complete ?? 0;
  $pastDue = $row?->past_due ?? 0;
  $pencil = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4z"/><path d="m13.5 6.5 4 4"/></svg>';
  $eyeOff = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 6.1A9 9 0 0 1 12 6c5 0 9 6 9 6a17 17 0 0 1-3 3.6M6.3 7.6A17 17 0 0 0 3 12s4 6 9 6a9 9 0 0 0 4-.9"/></svg>';
@endphp

@push('head')
  @include('capsa.partials.team-management._milestone_kit')
  <style nonce="{{ Vite::cspNonce() }}">
    .tmx .ms{display:flex;flex-direction:column;gap:18px}
    .tmx .ms-head{display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap}
    .tmx .ms-head .grow{flex:1;min-width:0}
    .tmx .ms-num{width:44px;height:44px;border-radius:50%;border:1.5px solid var(--accent);color:var(--ink);display:grid;place-items:center;font-family:'DM Mono',ui-monospace,monospace;font-weight:500;font-size:16px;flex:none;align-self:flex-start}
    .tmx .ms h1{margin:0;font-size:26px;font-weight:800;letter-spacing:-.01em;color:var(--ink)}
    .tmx .ms-sub{margin:4px 0 0;color:var(--mut);font-size:14px;display:flex;gap:6px 14px;flex-wrap:wrap;align-items:center}
    .tmx .ms-sub span{display:inline-flex;gap:6px;align-items:center}
    .tmx .ms-desc{margin:10px 0 0;max-width:760px;color:var(--ink2);font-size:14.5px;line-height:1.55;white-space:pre-line}
    .tmx .ms-dot{width:8px;height:8px;border-radius:50%;flex:none;display:inline-block;box-sizing:border-box}
    .tmx .ms-dot.ok{background:#16a34a}
    .tmx .ms-dot.open{background:#1d4ed8}
    .tmx .ms-dot.idle{border:1.5px solid var(--mut)}
    .tmx .ms-btn{min-height:40px;padding:0 16px;border-radius:8px;border:1px solid var(--line2);background:var(--card);color:var(--ink);font-family:inherit;font-size:14px;font-weight:600;display:inline-flex;gap:8px;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;white-space:nowrap;box-sizing:border-box}
    .tmx .ms-btn:hover:not([disabled]){border-color:var(--ink2)}
    .tmx .ms-btn[disabled]{opacity:.6;cursor:default}
    .tmx .ms-btn.p{background:var(--accent);border-color:var(--accent);color:var(--on-accent)}
    .tmx .ms-btn.p:hover:not([disabled]){filter:brightness(1.06)}
    .tmx .ms-btn.sm{min-height:32px;padding:0 12px;font-size:13px}
    .tmx .ms-cols{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:18px;align-items:start}
    .tmx .ms-card{background:var(--card);border:1px solid var(--line);border-radius:12px}
    .tmx .ms-card-h{display:flex;align-items:center;flex-wrap:wrap;gap:10px 12px;padding:14px 18px;border-bottom:1px solid var(--line)}
    .tmx .ms-card-h h2{margin:0;font-size:16px;flex:1;color:var(--ink)}
    .tmx .ms-n{white-space:nowrap;font-family:'DM Mono',ui-monospace,monospace;font-size:12.5px;color:var(--mut);font-weight:500}
    .tmx .ms-mono{font-family:'DM Mono',ui-monospace,monospace}
    .tmx .ms-mut{color:var(--mut)}
    .tmx .ms-list{list-style:none;margin:0;padding:0}
    .tmx .ms-req{display:grid;grid-template-columns:36px minmax(0,1fr) 220px 64px 80px;gap:14px;align-items:center;padding:14px 18px;border-bottom:1px solid var(--line)}
    .tmx .ms-req:last-child{border-bottom:0}
    .tmx .ms-req.is-drag{opacity:.45}
    .tmx .ms-kind{width:36px;height:36px;border-radius:9px;background:var(--bg3);border:1px solid var(--line);display:grid;place-items:center;color:var(--ink2);padding:0;margin:0;box-sizing:border-box}
    .tmx button.ms-kind{cursor:grab}
    .tmx button.ms-kind:hover,.tmx button.ms-kind:focus-visible{border-color:var(--ink2);color:var(--ink)}
    .tmx .ms-req-t{display:flex;flex-direction:column;gap:2px;min-width:0;font-size:13px}
    .tmx .ms-req-name{font-weight:600;font-size:15px;color:var(--ink);text-decoration:none;overflow-wrap:anywhere}
    .tmx .ms-req-name:hover{text-decoration:underline}
    .tmx .ms-check{color:var(--accent);font-weight:600}
    .tmx .ms-req-p{display:flex;flex-direction:column;gap:6px;font-size:13px;color:var(--ink)}
    .tmx .ms-meter{height:6px;border-radius:3px;background:var(--line);overflow:hidden;width:100%}
    .tmx .ms-meter i{display:block;height:100%;background:#16a34a;border-radius:3px}
    .tmx .ms-due{color:var(--ink2);font-size:13px}
    .tmx .ms-req.hidden .ms-req-name,.tmx .ms-req.hidden .ms-kind{opacity:.55}
    .tmx .ms-req.hidden .ms-btn{grid-column:4 / span 2;justify-self:end}
    .tmx .ms-empty{margin:0;padding:28px 18px;color:var(--mut);font-size:14px}
    .tmx .ms-stats{display:grid;grid-template-columns:repeat(3,1fr);border-bottom:1px solid var(--line)}
    .tmx .ms-stat{padding:16px 18px;display:flex;flex-direction:column;gap:2px;border-right:1px solid var(--line)}
    .tmx .ms-stat:last-child{border-right:0}
    .tmx .ms-stat b{font-family:'DM Mono',ui-monospace,monospace;font-weight:500;font-size:26px;color:var(--ink)}
    .tmx .ms-stat span{font-size:12.5px;color:var(--mut)}
    .tmx .ms-stat.bad b{color:var(--red)}
    .tmx .ms-lbl{font-size:11.5px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--mut);margin:0;padding:14px 18px 0}
    .tmx .ms-late{list-style:none;margin:0;padding:6px 0;max-height:300px;overflow:auto}
    .tmx .ms-late li{display:flex;justify-content:space-between;gap:10px;padding:8px 18px;font-size:13.5px;color:var(--ink)}
    .tmx .ms-late li span:last-child{color:var(--mut);text-align:right}
    .tmx .ms-foot{padding:14px 18px;border-top:1px solid var(--line)}
    .tmx .ms-foot .ms-btn{width:100%}
    @media (max-width:1100px){
      .tmx .ms-cols{grid-template-columns:minmax(0,1fr)}
    }
    @media (max-width:760px){
      .tmx .ms-req{grid-template-columns:36px minmax(0,1fr) auto;gap:8px 12px}
      .tmx .ms-req .ms-btn,.tmx .ms-req.hidden .ms-btn{grid-column:3;grid-row:1;justify-self:end}
      .tmx .ms-req-p{grid-column:2 / -1;grid-row:2}
      .tmx .ms-req .ms-due{grid-column:2 / -1;grid-row:3}
      .tmx .ms-req.hidden>div:empty{display:none}
    }
    @media (max-width:640px){
      .tmx .ms h1{font-size:22px}
      .tmx .ms-head .grow{flex:1 1 220px}
      .tmx .ms-head .ms-btn{flex:1 1 auto}
      .tmx .ms-stat{padding:12px 14px}
    }
  </style>
@endpush

@section('tm-content')
<div class="ms">
  <div class="ms-head">
    @if ($row?->number)
      <span class="ms-num" aria-label="Milestone {{ $row->number }}">{{ $row->number }}</span>
    @endif
    <div class="grow">
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
          <span><x-tm-icon n="cal" s="15" />Due {{ $row->due_on->format('M j, Y') }}</span>
        @endif
        @if ($milestone->requires_previous && $row?->previous)
          <span><x-tm-icon n="lock" s="15" />Opens after {{ $row->previous }}</span>
        @endif
      </p>
      @if ($milestone->description)
        <p class="ms-desc">{{ $milestone->description }}</p>
      @endif
    </div>
    <a class="ms-btn" href="{{ route('capsa.team_management.milestones.edit', $milestone) }}">{!! $pencil !!}Edit milestone</a>
    @if ($milestone->archived_at)
    @elseif (! $milestone->isOpened())
      <button class="ms-btn p" type="button" id="ml-close" data-body='{"opened":true}'>Open to teams</button>
    @elseif ($milestone->isClosed())
      <button class="ms-btn" type="button" id="ml-close" data-body='{"closed":false}'>Reopen</button>
    @else
      <button class="ms-btn" type="button" id="ml-close" data-body='{"closed":true}'><x-tm-icon n="check" s="15" />Mark finished</button>
    @endif
  </div>

  <div class="ms-cols">
    <section class="ms-card" aria-labelledby="ms-reqs-h">
      <div class="ms-card-h">
        <h2 id="ms-reqs-h">Requirements</h2>
        <span class="ms-n">{{ $shown }} shown{{ $hidden ? ' · '.$hidden.' hidden' : '' }}</span>
        <a class="ms-btn p sm" href="{{ route('capsa.team_management.milestones.requirements.create', $milestone) }}"><x-tm-icon n="plus" s="14" />Add requirement</a>
      </div>
      @if ($requirements->isEmpty())
        <p class="ms-empty">No requirements yet.</p>
      @else
        <ol class="ms-list" id="ml-reqs">
          @foreach ($requirements as $requirement)
            @php
              $type = \App\Http\Controllers\Capsa\MilestonePageController::typeOf($requirement);
              $finished = $done[$requirement->id] ?? 0;
              $check = $toCheck[$requirement->id] ?? 0;
              $edit = route('capsa.team_management.milestones.requirements.edit', $requirement);
              $icon = $requirement->kind === \App\Models\Deliverable::DEFENSE ? 'shield' : 'file';
            @endphp
            <li class="ms-req {{ $requirement->archived_at ? 'hidden' : '' }}" data-id="{{ $requirement->id }}">
              @if ($drag)
                <button class="ms-kind ml-grip" type="button" title="Drag to move" aria-label="Move {{ $requirement->title }}: drag, or use the up and down arrow keys">@if ($requirement->archived_at){!! $eyeOff !!}@else<x-tm-icon :n="$icon" s="18" />@endif</button>
              @else
                <span class="ms-kind">@if ($requirement->archived_at){!! $eyeOff !!}@else<x-tm-icon :n="$icon" s="18" />@endif</span>
              @endif
              <div class="ms-req-t">
                <a class="ms-req-name" href="{{ $edit }}">{{ $requirement->title }}</a>
                @if ($requirement->archived_at)
                  <span class="ms-mut">Hidden from teams</span>
                @elseif ($type || $check)
                  <span class="ms-mut">{{ $type }}{{ $type && $check ? ' · ' : '' }}@if ($check)<b class="ms-check">{{ $check }} to check</b>@endif</span>
                @endif
              </div>
              @if ($requirement->archived_at)
                <div></div>
                <button class="ms-btn sm" type="button" data-show="{{ route('capsa.deliverables.update', $requirement) }}">Show to teams</button>
              @else
                <div class="ms-req-p">
                  <div class="ms-meter"><i style="width:{{ $teams ? round($finished / $teams * 100) : 0 }}%"></i></div>
                  <span class="ms-mono">{{ $finished }}<span class="ms-mut"> of {{ $teams }} done</span></span>
                </div>
                <span class="ms-mono ms-due">{{ $requirement->due_date?->format('M j') ?? '—' }}</span>
                <a class="ms-btn sm" href="{{ $edit }}" aria-label="Edit {{ $requirement->title }}">{!! $pencil !!}Edit</a>
              @endif
            </li>
          @endforeach
        </ol>
      @endif
    </section>

    <aside class="ms-card" aria-labelledby="ms-teams-h">
      <div class="ms-card-h"><h2 id="ms-teams-h">Teams</h2></div>
      <div class="ms-stats">
        <div class="ms-stat"><b>{{ $complete }}</b><span>Done</span></div>
        <div class="ms-stat"><b>{{ max(0, ($row?->open ?? 0) - $pastDue) }}</b><span>Still working</span></div>
        <div class="ms-stat {{ $pastDue ? 'bad' : '' }}"><b>{{ $pastDue }}</b><span>Past due</span></div>
      </div>
      @if ($late->isNotEmpty())
        <p class="ms-lbl">Past due</p>
        <ul class="ms-late">
          @foreach ($late as [$team, $title])
            <li><span>{{ $team }}</span><span>{{ $title }}</span></li>
          @endforeach
        </ul>
      @endif
      <div class="ms-foot">
        <a class="ms-btn" href="{{ route('capsa.team_management', ['tab' => 'milestones']) }}"><x-tm-icon n="users" s="16" />Open in Milestones</a>
      </div>
    </aside>
  </div>
</div>
@endsection

@push('scripts')
@include('partials.app-dialog')
<script nonce="{{ Vite::cspNonce() }}">
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
  if (reqs) {
    mlSortable(reqs, async ids => {
      const order = (@json($order)).map(milestone => milestone.id === {{ $milestone->id }} ? { id: milestone.id, deliverables: ids } : milestone);
      await mlSend(@json(route('capsa.checklists.order.update', $milestone->checklist_id)), 'PUT', { milestones: order });
    });
  }
</script>
@endpush
