{{--
  The trail, and a Back button named after where it goes.

  $trail is [label, url] pairs ending on the page you are on, whose url is
  null. The Back button is the pair before it: the breadcrumb alone was too
  easy to miss as a way back, so the one step up is a button of its own.
--}}
@php
  $back = count($trail) > 1 ? $trail[count($trail) - 2] : null;
@endphp
<nav class="crumb {{ $back ? 'has-bk' : '' }}" aria-label="Breadcrumb">
  @if ($back)
    <a class="bk" href="{{ $back[1] }}">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5m7-7-7 7 7 7"/></svg>
      {{ $back[0] }}
    </a>
  @endif
  <span class="hm" aria-hidden="true">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/></svg>
  </span>
  @foreach ($trail as $step)
    <span class="sep" aria-hidden="true">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
    </span>
    @if ($step[1])
      <a href="{{ $step[1] }}">{{ $step[0] }}</a>
    @else
      <span aria-current="page">{{ $step[0] }}</span>
    @endif
  @endforeach
</nav>
