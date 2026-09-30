{{-- One milestone's due date, its lock, and how many teams are done with it.
     $row is one of ChecklistTracker::board()'s milestones. --}}
@php
  $m = $row->milestone;
  $days = $row->due_on ? (int) today()->diffInDays($row->due_on->copy()->startOfDay(), false) : null;
@endphp
<div class="ml-meta">
  @if ($m->archived_at)
    <span>Hidden from teams</span>
  @endif
  @if ($m->isClosed())
    <span>Finished {{ $m->closed_at->format('M j, Y') }}</span>
  @endif
  @if ($row->due_on)
    <span>Due {{ $row->due_on->format('M j, Y') }}</span>
    @if (! $m->isClosed())
      @if ($days < 0 && $row->past_due > 0)
        <span class="ml-late">Past due for {{ $row->past_due }} {{ \Illuminate\Support\Str::plural('team', $row->past_due) }}</span>
      @elseif ($days === 0)
        <span>Due today</span>
      @elseif ($days > 0)
        <span>in {{ $days }} {{ \Illuminate\Support\Str::plural('day', $days) }}</span>
      @endif
    @endif
  @else
    <span>No due date yet</span>
  @endif
  @if ($m->requires_previous && $row->previous)
    <span><x-tm-icon n="lock" s="12" /> Opens after {{ $row->previous }}</span>
  @endif
</div>
<div class="ml-prog">
  <span class="ml-meter"><i style="width:{{ $row->percent }}%"></i></span>
  <b>{{ $row->percent }}%</b>
  <span>{{ $row->complete }} of {{ $row->teams }} {{ \Illuminate\Support\Str::plural('team', $row->teams) }} done · {{ $row->deliverables }} {{ \Illuminate\Support\Str::plural('requirement', $row->deliverables) }}</span>
</div>
