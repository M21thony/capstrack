@props([
    'title' => '',
    'actionUrl' => null,
    'actionText' => 'View All',
    'badge' => null,
    'badgeClass' => 'badge-blue',
    'empty' => false,
    'emptyMessage' => 'No data available.',
    'loading' => false,
    'scrollable' => false,
    'maxHeight' => null,
])

<div class="card" {{ $attributes }}>
    <div class="card-header">
        <span class="card-title">{{ $title }}</span>
        <div style="display:flex;align-items:center;gap:8px">
            @if ($badge)
                <span class="badge {{ $badgeClass }}">{{ $badge }}</span>
            @endif
            @if ($actionUrl)
                <a href="{{ $actionUrl }}" class="btn btn-ghost" style="font-size:11px;padding:4px 10px">{{ $actionText }}</a>
            @endif
        </div>
    </div>
    @if ($loading)
        <div class="gap-12">
            <div class="skeleton-box" style="width:85%;height:11px;border-radius:4px;margin-bottom:6px;"></div>
            <div class="skeleton-box" style="width:60%;height:11px;border-radius:4px;margin-bottom:6px;"></div>
            <div class="skeleton-box" style="width:70%;height:11px;border-radius:4px;"></div>
        </div>
    @elseif ($empty)
        <x-dashboard.empty-state :message="$emptyMessage" />
    @else
        <div @if($scrollable) class="act-scroll" @if($maxHeight) style="max-height:{{ $maxHeight }}px" @endif @endif>
            {{ $slot }}
        </div>
    @endif
</div>
