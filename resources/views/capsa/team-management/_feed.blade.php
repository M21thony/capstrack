{{--
  A team's updates, one line each: who, what, and the day. Initials only, no
  photos, so the list stays light however long it gets.
  $events from TeamManagementController::activityFor(); $fill lets the list
  take the height left on the team page and show only the rows that fit.
--}}
@php
  $feedPalette = ['#8a6e10', '#0e7490', '#4338ca', '#1d7a3e', '#9f1239'];
  $feedInitials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))
      ->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('');
@endphp
@if ($events->isEmpty())
  <p class="tp-none">Nothing yet.</p>
@else
  <ul class="tp-ppl tp-feed{{ ! empty($fill) ? ' tp-fill' : '' }}">
    @foreach ($events as $event)
      <li>
        @if ($event->student)
          <span class="tp-av" style="background:{{ $feedPalette[abs((int) $event->student->student_id) % count($feedPalette)] }}" aria-hidden="true">{{ $feedInitials($event->who) }}</span>
        @else
          <span class="tp-av" style="background:linear-gradient(135deg,{{ $row->gradient }})" aria-hidden="true">{{ $row->initials }}</span>
        @endif
        <span class="line"><b>{{ $event->who }}</b> {{ $event->did }}</span>
        <time datetime="{{ $event->at->toDateString() }}" title="{{ $event->at->format('M j, Y') }}">{{ $event->at->format($event->at->isCurrentYear() ? 'M j' : 'M j, Y') }}</time>
      </li>
    @endforeach
  </ul>
@endif
