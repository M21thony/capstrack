{{-- One cell's mark. Its shape carries the state, so the grid reads at a
     distance rather than only under a cursor. --}}
@switch($state)
  @case('done')
  @case('done_late')
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4"><polyline points="20 6 9 17 4 12"/></svg>
    @break

  @case('handed_in')
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>
    @break

  @case('overdue')
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><circle cx="12" cy="12" r="9"/><line x1="12" y1="7" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    @break
@endswitch
