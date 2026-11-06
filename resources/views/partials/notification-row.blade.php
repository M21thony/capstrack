{{-- One notification, in the bell and on the See all page. Takes $n. --}}
@php
  [$kind, $face, $goLabel] = match ($n->type) {
    'document_submitted', 'paper_to_check', 'review_reminder' => ['documents', 'doc', 'Review →'],
    'consultation_booked', 'consultation_cancelled', 'consultation_reminder' => ['consultations', 'cal', 'View →'],
    'announcement' => ['announcement', 'megaphone', null],
    'expert_application' => ['', 'team', 'View →'],
    'leave_request_received', 'expert_slots_short' => ['', 'warn', 'View →'],
    default => ['', 'bell', 'View →'],
  };

  // The colours stay on the element itself: the pop-ups copy them from here.
  [$bg, $fg, $path] = [
    'doc' => ['rgba(29,78,216,0.12)', 'var(--blue)', '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Z"/><path d="M8 13h8M8 17h5"/>'],
    'cal' => ['rgba(234,88,12,0.12)', '#ea580c', '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M5 10h14"/><path d="M8 2v3M16 2v3"/>'],
    'megaphone' => ['rgba(234,88,12,0.12)', '#ea580c', '<path d="M3 11v3a1 1 0 0 0 1 1h3l5 4V6L7 10H4a1 1 0 0 0-1 1Z"/><path d="M16 9a4 4 0 0 1 0 6"/>'],
    'team' => ['rgba(124,58,237,0.12)', 'var(--purple)', '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
    'warn' => ['rgba(220,38,38,0.13)', '#b91c1c', '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>'],
    'bell' => ['rgba(100,116,139,0.12)', '#64748b', '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a2 2 0 0 0 3.4 0"/>'],
  ][$face];
@endphp
<a href="{{ $n->link ?: '#' }}" class="notif-item {{ $n->is_read ? '' : 'is-unread' }}" data-id="{{ $n->id }}" data-type="{{ $n->type }}" data-kind="{{ $kind }}">
  <div class="notif-item-icon" style="background:{{ $bg }};color:{{ $fg }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">{!! $path !!}</svg>
  </div>
  <div class="notif-item-body">
    <div class="np-titlerow">
      <div class="notif-item-title">{{ $n->title }}</div>
      @if ($kind === 'announcement')<span class="np-tag">Announcement</span>@endif
    </div>
    {{-- Messages are written as HTML and escape their own values at
         the source, the same way the student bell renders them. --}}
    <div class="notif-item-msg">{!! $n->message !!}</div>
    <div class="notif-item-time" data-at="{{ $n->created_at?->toIso8601String() }}">{{ $n->created_at?->diffForHumans() }}</div>
  </div>
  @if ($n->link)
    @if ($goLabel)
      <span class="np-go">{{ $goLabel }}</span>
    @else
      <svg class="np-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
    @endif
  @endif
</a>
