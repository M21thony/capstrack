{{--
  A Team Management modal in the system's pattern: the dark blurred overlay,
  a white card and a round close button (student-ui/Styles/team.css).

  <x-team-modal id="inviteModal" title-id="invite-t" width="440" on-close="closeInvite()">...</x-team-modal>

  A modal that must be answered (Choose your role) passes :closable="false":
  no close button, no closing on the backdrop, and Escape leaves it alone.
--}}
@props(['id', 'titleId', 'width' => 420, 'closable' => true, 'onClose' => null, 'open' => false])
@php $close = $onClose ?? "document.getElementById('{$id}').classList.remove('active')"; @endphp
<div class="modal-overlay{{ $open ? ' active' : '' }}" id="{{ $id }}"@if ($closable) onclick="if (event.target === this) { {{ $close }} }"@else data-no-escape @endif>
  <div role="dialog" aria-modal="true" aria-labelledby="{{ $titleId }}" class="card modal-card team-modal" style="max-width: {{ $width }}px">
    @if ($closable)
      <button type="button" class="pm-close-btn team-modal-close" onclick="{{ $close }}" aria-label="Close">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    @endif
    {{ $slot }}
  </div>
</div>
