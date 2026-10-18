{{-- One match on the Similarity Report. $i is its place in the whole stored list, so the link opens the right one. --}}
@php
  $mScore = \App\Support\SimilarityBand::scoreOf($m);
  $why = $m['similarities'] ?? ($m['reason'] ?? null);
@endphp
<a class="rp-row" href="{{ route('student.duplication.similar', [$check->id, $i + 1]) }}" title="{{ $m['title'] ?? '' }}">
  <span class="rp-rank"><b class="sim-{{ \App\Support\SimilarityBand::of($mScore) }}">{{ $mScore === null ? '—' : $mScore.'%' }}</b></span>
  <span class="rp-what">
    <b>{{ $m['title'] ?? '' }}</b>
    <small><span @if ($subClass) class="{{ $subClass }}" @endif>{{ $sub }}</span>@if ($why) &middot; {{ $why }}@endif</small>
  </span>
</a>
