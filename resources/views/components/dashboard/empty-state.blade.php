@props([
    'message' => 'No data available.',
    'icon' => null,
    'actionText' => null,
    'actionUrl' => null,
])

<div style="text-align:center;padding:32px 20px;color:var(--text3)">
    @if ($icon)
        <div style="font-size:36px;margin-bottom:10px;opacity:0.3">{!! $icon !!}</div>
    @else
        <div style="font-size:36px;margin-bottom:10px;opacity:0.3">
            <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>
        </div>
    @endif
    <div style="font-size:13px;color:var(--text3)">{{ $message }}</div>
    @if ($actionText && $actionUrl)
        <a href="{{ $actionUrl }}" class="btn btn-ghost" style="margin-top:12px;font-size:11px;padding:5px 12px">{{ $actionText }}</a>
    @endif
</div>
