{{-- A ring filled to $percent, the figure in the middle, and a line or two beside it. --}}
@props(['percent' => null, 'title' => '', 'sub' => '', 'color' => 'var(--accent)'])
<div class="dc-ring-row">
    <div class="dc-ring" style="--p:{{ $percent ?? 0 }};--c:{{ $color }}" role="img" aria-label="{{ $title }}: {{ $percent === null ? 'no data yet' : $percent.'%' }}">
        <b>{{ $percent === null ? '—' : $percent.'%' }}</b>
    </div>
    <div class="dc-ring-text"><strong>{{ $title }}</strong><small>{{ $sub }}</small>{{ $slot }}</div>
</div>
