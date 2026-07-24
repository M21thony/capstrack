@props([
    'headers' => [],
    'rows' => [],
    'emptyMessage' => 'No records found.',
    'loading' => false,
    'actionUrl' => null,
    'actionText' => 'View All',
])

<div class="card" {{ $attributes }}>
    <div class="card-header">
        <span class="card-title">{{ $title ?? '' }}</span>
        @if ($actionUrl)
            <a href="{{ $actionUrl }}" class="btn btn-ghost" style="font-size:11px;padding:4px 10px">{{ $actionText }}</a>
        @endif
    </div>
    @if ($loading)
        <div class="gap-12" style="padding:16px">
            @for ($i = 0; $i < 4; $i++)
                <div class="skeleton-box" style="width:{{ rand(60, 95) }}%;height:13px;border-radius:4px;"></div>
            @endfor
        </div>
    @elseif (count($rows) === 0)
        <x-dashboard.empty-state :message="$emptyMessage" />
    @else
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        @foreach ($headers as $header)
                            <th @if($loop->first) style="padding-left:20px" @endif>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    {{ $slot ?? '' }}
                </tbody>
            </table>
        </div>
    @endif
</div>
