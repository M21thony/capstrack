{{--
  One line-drawn icon, by name.

  The Team Management pages draw the same handful of icons over and over.
  Spelling each SVG out where it is used would triple the length of those
  views, so they live here once: <x-tm-icon n="file" s="18" />
--}}
@props(['n', 's' => 18, 'bold' => false])
@php
  $paths = [
    'home' => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1Z"/>',
    'chev' => '<path d="m9 18 6-6-6-6"/>',
    'back' => '<path d="M19 12H5m7-7-7 7 7 7"/>',
    'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    'clip' => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2M9 12h6M9 16h6"/>',
    'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
    'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M9 13h6M9 17h6"/>',
    'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    'trophy' => '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0Z"/><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/>',
    'pulse' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
    'cal' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    'cap' => '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/>',
    'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
    'star' => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1Z"/>',
    'gear' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 9 19.4a1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.6 9a1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z"/>',
    'lock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    'ext' => '<path d="M14 3h7v7M21 3l-9 9M19 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h5"/>',
    'check' => '<path d="m5 13 4 4 10-10"/>',
    'x' => '<circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/>',
    'warn' => '<path d="M12 3 2 21h20Z"/><path d="M12 10v4M12 17.5v.5"/>',
    'bang' => '<path d="M12 7v6M12 17h.01"/>',
    'dot' => '<circle cx="12" cy="12" r="5"/>',
    'flag' => '<path d="M4 22V4"/><path d="M4 4h12l-2 4 2 4H4"/>',
    'half' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l2.5 2"/>',
    'grip' => '<path d="M9 6h.01M9 12h.01M9 18h.01M15 6h.01M15 12h.01M15 18h.01"/>',
    'plus' => '<path d="M12 5v14M5 12h14"/>',
    'upload' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M12 18v-6m-3 3 3-3 3 3"/>',
    'sign' => '<path d="M3 21h18"/><path d="M15.5 4.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/>',
    'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5"/>',
    'present' => '<rect x="3" y="3" width="18" height="12" rx="1.5"/><path d="M12 15v3m-4 3 4-3 4 3M7 11l3-3 2 2 4-4"/>',
  ];
@endphp
<svg width="{{ $s }}" height="{{ $s }}" viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="{{ $bold ? '2.6' : '2' }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$n] ?? '' !!}</svg>
