{{-- One bar split into parts, with a legend. $parts: [['label', 'value', 'tone']]. --}}
@props(['parts' => [], 'hideZero' => false])
@php
    $sum = collect($parts)->sum('value');
    $tones = \App\Support\DashboardStats::TONES;
@endphp
@if ($sum <= 0)
    <div class="dc-empty">Nothing to show yet.</div>
@else
    <div class="dc-stack" role="img" aria-label="{{ collect($parts)->map(fn ($p) => $p['label'].' '.$p['value'])->implode(', ') }}">
        @foreach ($parts as $p)
            @if ($p['value'] > 0)<i title="{{ $p['label'] }}: {{ $p['value'] }}" style="width:{{ $p['value'] / $sum * 100 }}%;background:{{ $tones[$p['tone'] ?? 'none'] }}"></i>@endif
        @endforeach
    </div>
    <div class="dc-legend">
        @foreach ($parts as $p)
            @if (! $hideZero || $p['value'] > 0)
                <div><span class="dc-dot" style="background:{{ $tones[$p['tone'] ?? 'none'] }}"></span><span>{{ $p['label'] }}</span><b>{{ $p['value'] }}</b></div>
            @endif
        @endforeach
    </div>
@endif
