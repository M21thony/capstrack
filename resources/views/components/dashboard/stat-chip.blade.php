@props([
    'value' => '—',
    'label' => '',
    'sub' => '',
    'color' => 'var(--text)',
    'href' => null,
    'loading' => false,
])

<{{ $href ? 'a' : 'div' }} {{ $href ? "href=\"{$href}\"" : '' }} class="stat-chip" {{ $attributes }}>
    @if ($loading)
        <div class="skeleton-box" style="width:50px;height:28px;border-radius:6px;margin-bottom:6px;"></div>
        <div class="skeleton-box" style="width:80px;height:10px;border-radius:4px;"></div>
        @if ($sub)
            <div class="skeleton-box" style="width:60px;height:8px;border-radius:4px;margin-top:4px;"></div>
        @endif
    @else
        <div class="stat-n" style="color:{{ $color }}">{{ $value }}</div>
        <div class="stat-l">{{ $label }}</div>
        @if ($sub)
            <div class="stat-sub">{{ $sub }}</div>
        @endif
    @endif
</{{ $href ? 'a' : 'div' }}>
