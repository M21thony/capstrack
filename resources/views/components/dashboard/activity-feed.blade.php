@props([
    'items' => [],
    'emptyMessage' => 'No recent activity.',
    'maxHeight' => 320,
])

{{--
  A portal's recent activity, as a timeline: newest first, grouped under
  Today / Yesterday / This week / Earlier, each entry with the icon and colour
  of what happened (App\Support\ActivityIcons) and the exact time on hover.

  An item is ['type' => 'team_joined', 'text' => '<strong>..</strong> ..',
  'at' => Carbon]. The older shape - 'iconSvg'/'iconPath' and a 'time' string -
  still draws, just without the grouping.
--}}
@php
    $now = now();
    $groupOf = function ($at) use ($now) {
        if (! $at) {
            return null;
        }
        $at = \Illuminate\Support\Carbon::parse($at);

        return match (true) {
            $at->isSameDay($now) => 'Today',
            $at->isSameDay($now->copy()->subDay()) => 'Yesterday',
            $at->greaterThan($now->copy()->subDays(7)) => 'This week',
            default => 'Earlier',
        };
    };
    $rows = collect($items)->map(function ($item) use ($groupOf) {
        $at = $item['at'] ?? null;
        // Its own icon only when it brings one and names no type.
        $face = isset($item['type']) || (! isset($item['iconSvg']) && ! isset($item['iconPath']))
            ? \App\Support\ActivityIcons::for($item['type'] ?? null)
            : null;

        return [
            'text' => $item['text'] ?? '',
            'group' => $groupOf($at),
            'time' => $at ? \Illuminate\Support\Carbon::parse($at)->diffForHumans() : ($item['time'] ?? null),
            'exact' => $at ? \Illuminate\Support\Carbon::parse($at)->format('F j, Y · g:i A') : null,
            'label' => $face['label'] ?? null,
            'tone' => $face['tone'] ?? 'gray',
            'svg' => $face
                ? $face['svg']
                : ($item['iconSvg'] ?? '<svg viewBox="'.($item['iconViewBox'] ?? '0 0 24 24').'">'.($item['iconPath'] ?? '').'</svg>'),
        ];
    });
@endphp

@once
<style>
  .afeed{position:relative;overflow-y:auto;overscroll-behavior:contain;padding-right:4px}
  .afeed-group{position:sticky;top:0;z-index:1;margin:0;padding:8px 0 6px;font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text3);background:var(--bg2,#fff)}
  .afeed-list{position:relative;margin:0 0 6px;padding:0;list-style:none}
  /* The line the icons sit on. */
  .afeed-list::before{content:"";position:absolute;left:17px;top:18px;bottom:18px;width:2px;border-radius:2px;background:var(--border)}
  .afeed-item{position:relative;display:flex;align-items:flex-start;gap:12px;padding:8px 8px 8px 0;border-radius:10px;transition:background .15s}
  .afeed-item:hover{background:var(--bg3)}
  .afeed-ic{position:relative;z-index:1;width:36px;height:36px;flex:none;border-radius:50%;display:grid;place-items:center;
    background:var(--afeed-bg);color:var(--afeed-fg);box-shadow:0 0 0 3px var(--bg2,#fff)}
  .afeed-ic svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
  .afeed-body{flex:1;min-width:0;padding-top:1px}
  .afeed-label{display:inline-block;margin-bottom:2px;font-size:10.5px;font-weight:700;letter-spacing:.03em;color:var(--afeed-fg)}
  .afeed-text{font-size:13px;line-height:1.45;color:var(--text2);overflow-wrap:anywhere}
  .afeed-text strong{color:var(--text);font-weight:600}
  .afeed-time{display:inline-flex;align-items:center;gap:4px;margin-top:3px;font-size:11px;color:var(--text3)}
  .afeed-time svg{width:11px;height:11px;fill:none;stroke:currentColor;stroke-width:2.2}
  .afeed-tone-green{--afeed-bg:rgba(22,163,74,.12);--afeed-fg:#15803d}
  .afeed-tone-red{--afeed-bg:rgba(220,38,38,.1);--afeed-fg:#dc2626}
  .afeed-tone-amber{--afeed-bg:rgba(217,119,6,.12);--afeed-fg:#b45309}
  .afeed-tone-blue{--afeed-bg:rgba(37,99,235,.1);--afeed-fg:#2563eb}
  .afeed-tone-purple{--afeed-bg:rgba(124,58,237,.1);--afeed-fg:#7c3aed}
  .afeed-tone-gold{--afeed-bg:rgba(201,168,44,.16);--afeed-fg:#8a6e10}
  .afeed-tone-gray{--afeed-bg:var(--bg3);--afeed-fg:var(--text3)}
  @media (prefers-reduced-motion:reduce){.afeed-item{transition:none}}
</style>
@endonce

@if ($rows->isNotEmpty())
  <div class="afeed act-scroll" style="max-height:{{ $maxHeight }}px">
    @foreach ($rows->groupBy(fn ($r) => $r['group'] ?? '') as $group => $groupRows)
      @if ($group !== '')
        <p class="afeed-group">{{ $group }}</p>
      @endif
      <ul class="afeed-list">
        @foreach ($groupRows as $row)
          <li class="afeed-item afeed-tone-{{ $row['tone'] }}">
            <span class="afeed-ic">{!! $row['svg'] !!}</span>
            <div class="afeed-body">
              @if ($row['label'])
                <span class="afeed-label">{{ $row['label'] }}</span>
              @endif
              <div class="afeed-text">{!! $row['text'] !!}</div>
              @if ($row['time'])
                <span class="afeed-time" @if ($row['exact']) title="{{ $row['exact'] }}" @endif>
                  <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>
                  {{ $row['time'] }}
                </span>
              @endif
            </div>
          </li>
        @endforeach
      </ul>
    @endforeach
  </div>
@else
  <x-dashboard.empty-state :message="$emptyMessage" icon="history" />
@endif
