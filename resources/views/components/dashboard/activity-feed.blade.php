@props([
    'items' => [],
    'emptyMessage' => 'No recent activity.',
    'maxHeight' => 260,
])

@if ($items && count($items) > 0)
    <div class="act-scroll" style="max-height:{{ $maxHeight }}px;overflow-y:auto">
        @foreach ($items as $item)
            <div class="activity-item">
                <div class="act-icon" style="background:{{ $item['iconBg'] ?? 'var(--accent-glow)' }}">
                    @if (isset($item['iconSvg']))
                        {!! $item['iconSvg'] !!}
                    @else
                        <svg viewBox="{{ $item['iconViewBox'] ?? '0 0 24 24' }}" style="stroke:{{ $item['iconColor'] ?? 'var(--accent)' }};width:13px;height:13px;fill:none;stroke-width:2.5">{!! $item['iconPath'] ?? '' !!}</svg>
                    @endif
                </div>
                <div class="act-text">
                    {!! $item['text'] ?? '' !!}
                    @if (isset($item['time']) && $item['time'])
                        <div class="act-time">{{ $item['time'] }}</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@else
    <x-dashboard.empty-state :message="$emptyMessage" />
@endif
