{{-- A row per thing, each bar sized against the biggest. $rows: [['label', 'value']]. --}}
@props(['rows' => [], 'color' => 'var(--accent)', 'empty' => 'Nothing to show yet.'])
@php $max = max(1, collect($rows)->max('value') ?? 0); @endphp
@if (count($rows) === 0)
    <div class="dc-empty">{{ $empty }}</div>
@else
    <div class="dc-bars">
        @foreach ($rows as $r)
            <div class="dc-bar" title="{{ $r['label'] }}: {{ $r['value'] }}">
                <span>{{ $r['label'] }}</span>
                <u><i style="width:{{ $r['value'] / $max * 100 }}%;background:{{ $color }}"></i></u>
                <b>{{ $r['value'] }}</b>
            </div>
        @endforeach
    </div>
@endif
