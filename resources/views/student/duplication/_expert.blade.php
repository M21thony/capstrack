{{-- One suggested expert on the Similarity Report, shown whole, with Apply
     or the lock that says why not yet. --}}
<div class="rp-expert">
  <div class="avatar">{{ strtoupper(substr($i['name'] ?? '?', 0, 1)) }}</div>
  <div class="rp-expert-who">
    @if (! empty($i['role_label']))
      <div class="sx-role">Recommended {{ $i['role_label'] }}</div>
    @endif
    <div class="n">{{ $i['name'] ?? '' }}</div>
    <div class="x">{{ $i['expertise'] ?? '' }}</div>
    <div class="r">{{ $i['reason'] ?? '' }}</div>
  </div>
  @if (! empty($i['id']))
    @if ($applyOpen)
      <a class="dup-apply-btn" href="{{ route('student.experts', ['apply' => $i['id']]) }}">
        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
        Apply
      </a>
    @else
      {{-- Just the lock: the reason is in the heading above, and a label
           here squeezed the expert's name out of the card. The wrapper
           carries the tooltip: a disabled button fires no hover events. --}}
      <span class="dup-apply-wrap" data-tip="{{ $applyLockShort }}" tabindex="0" role="note" aria-label="{{ $applyLockReason }}">
        <button type="button" class="dup-apply-btn locked rp-lock" disabled>
          <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <span class="rp-sr">{{ $applyLockShort }}</span>
        </button>
      </span>
    @endif
  @endif
</div>
