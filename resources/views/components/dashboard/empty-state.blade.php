@props([
    'message' => 'No data available.',
    // A name from App\Support\PortalIcons: what the empty space would hold.
    'icon' => 'inbox',
    'actionText' => null,
    'actionUrl' => null,
])

<div style="text-align:center;padding:32px 20px;color:var(--text3)">
    <div style="width:52px;height:52px;margin:0 auto 12px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(127,127,127,.12);background:color-mix(in srgb, var(--text3) 11%, transparent);color:var(--text3)">
        <x-portal-icon :name="$icon" size="24" stroke="1.8" />
    </div>
    <div style="font-size:13px;color:var(--text3)">{{ $message }}</div>
    @if ($actionText && $actionUrl)
        <a href="{{ $actionUrl }}" class="btn btn-ghost" style="margin-top:12px;font-size:11px;padding:5px 12px">{{ $actionText }}</a>
    @endif
</div>
