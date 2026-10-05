@props([
    'value' => '—',
    'label' => '',
    'sub' => '',
    'color' => 'var(--text)',
    'icon' => null,
    'href' => null,
    'loading' => false,
])

{{-- The address is written as a value of its own. Built as one escaped
     string, its quotes came out as &quot; and the browser took them into the
     address itself: every tile opened ".../%22http://...%22", a page that is
     not there. --}}
<{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'stat-chip'.($icon && ! $loading ? ' has-icon' : '')]) }}>
    @if ($loading)
        <div class="skeleton-box" style="width:50px;height:28px;border-radius:6px;margin-bottom:6px;"></div>
        <div class="skeleton-box" style="width:80px;height:10px;border-radius:4px;"></div>
        @if ($sub)
            <div class="skeleton-box" style="width:60px;height:8px;border-radius:4px;margin-top:4px;"></div>
        @endif
    @else
        @once
        <style nonce="{{ Vite::cspNonce() }}">
            /* The number's own colour, on a tile in the card's corner, so each card
               is told apart by its picture before its label is read. Room is kept
               beside the number only: the tile is no taller than the number's line,
               so the label runs full width below it and a narrow card (three to a
               row on a phone) keeps its width.

               Here rather than in dashboard.styles because Document Review uses
               these cards without the rest of the dashboard; and in this branch,
               not above it, because the student dashboard's loading cards are
               removed once it has loaded and would take the styles with them. */
            .stat-chip.has-icon { position:relative; min-width:0; }
            .stat-chip.has-icon .stat-n { padding-right:48px; }
            .stat-ic {
                position:absolute; top:16px; right:16px; width:38px; height:38px; border-radius:11px;
                display:flex; align-items:center; justify-content:center;
                color:var(--stat-c); background:rgba(127,127,127,.12); background:color-mix(in srgb, var(--stat-c) 13%, transparent);
            }
            .stat-ic svg { width:20px; height:20px; }
            @media (max-width:768px) {
                .stat-chip.has-icon .stat-n { padding-right:36px; }
                .stat-ic { top:12px; right:12px; width:30px; height:30px; border-radius:9px; }
                .stat-ic svg { width:16px; height:16px; }
            }
        </style>
        @endonce
        @if ($icon)
            <span class="stat-ic" style="--stat-c:{{ $color }}"><x-portal-icon :name="$icon" size="20" /></span>
        @endif
        <div class="stat-n" style="color:{{ $color }}">{{ $value }}</div>
        <div class="stat-l">{{ $label }}</div>
        @if ($sub)
            <div class="stat-sub">{{ $sub }}</div>
        @endif
    @endif
</{{ $href ? 'a' : 'div' }}>
