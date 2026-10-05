{{-- One milestone: its due date, how many teams are done, and its
     requirements. Requirements drag into a new order; Edit opens a form. --}}
@extends('capsa.team-management._shell')

@section('title', $milestone->name)

@push('head')
  @include('capsa.partials.team-management._milestone_kit')
@endpush

@php $how = collect(\App\Http\Controllers\Capsa\MilestonePageController::HOW); @endphp

@section('tm-content')
<div class="ml-page">
  <div class="ml-head">
    <div class="ml-main">
      <h1 class="ml-title">{{ $row?->number ? $row->number.'. ' : '' }}{{ $milestone->name }}</h1>
      @if ($row)
        @include('capsa.partials.team-management._milestone_meta', ['row' => $row])
      @endif
    </div>
    <div class="ml-acts">
      <a class="ml-btn" href="{{ route('capsa.team_management.milestones.edit', $milestone) }}">Edit milestone</a>
      @unless ($milestone->archived_at)
        <button class="ml-btn" type="button" id="ml-close" data-closed="{{ $milestone->isClosed() ? 'false' : 'true' }}">{{ $milestone->isClosed() ? 'Reopen' : 'Mark finished' }}</button>
      @endunless
    </div>
  </div>

  <div class="ml-board-h">
    <h2>Requirements <small>{{ $requirements->count() }}</small></h2>
    <a class="ml-btn ml-gold" href="{{ route('capsa.team_management.milestones.requirements.create', $milestone) }}"><x-tm-icon n="plus" s="14" />Add requirement</a>
  </div>
  <div class="ml-list" id="ml-reqs">
    @forelse ($requirements as $requirement)
      @php
        $way = $requirement->kind === \App\Models\Deliverable::APPROVAL ? ($requirement->role ?: 'adviser') : $requirement->kind;
        $finished = $done[$requirement->id] ?? 0;
      @endphp
      <div class="ml-row" data-id="{{ $requirement->id }}">
        @if ($requirements->count() > 1)
          <button class="ml-grip" type="button" title="Drag to move" aria-label="Move {{ $requirement->title }}: drag, or use the up and down arrow keys"><x-tm-icon n="grip" s="18" bold /></button>
        @endif
        <div class="ml-main">
          <span class="ml-req">{{ $requirement->title }}</span>
          <div class="ml-meta">
            @if ($requirement->archived_at)
              <span>Hidden from teams</span>
            @endif
            <span>{{ $how[$way]['label'] ?? ucfirst($way) }} · {{ $finished }} of {{ $teams }} {{ \Illuminate\Support\Str::plural('team', $teams) }} done</span>
            @if ($requirement->due_date)
              <span>Due {{ $requirement->due_date->format('M j, Y') }}</span>
            @endif
          </div>
        </div>
        <div class="ml-acts">
          <a class="ml-btn" href="{{ route('capsa.team_management.milestones.requirements.edit', $requirement) }}">Edit</a>
        </div>
      </div>
    @empty
      <div class="ml-empty">No requirements yet. Teams finish this milestone once they complete every requirement in it.</div>
    @endforelse
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
      await mlSend(@json(route('capsa.checklist_milestones.update', $milestone)), 'PATCH', { closed: button.dataset.closed === 'true' });
      location.reload();
    } catch (error) {
      button.disabled = false;
      mlFail(error);
    }
  });

  // The order endpoint wants every milestone, so only this one's list changes.
  mlSortable(document.getElementById('ml-reqs'), async ids => {
    const order = @json($order).map(milestone => milestone.id === {{ $milestone->id }} ? { id: milestone.id, deliverables: ids } : milestone);
    await mlSend(@json(route('capsa.checklists.order.update', $milestone->checklist_id)), 'PUT', { milestones: order });
  });
</script>
@endpush
