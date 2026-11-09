{{-- One match on the Similarity Report. $i is its place in the whole stored list, so the link opens the right one. --}}
@php
  $mScore = \App\Support\SimilarityBand::scoreOf($m);
  $why = $m['similarities'] ?? ($m['reason'] ?? null);
@endphp
<a class="pc-row" href="{{ route('student.duplication.similar', [$check->id, $i + 1]) }}" title="{{ $m['title'] ?? '' }}">
  <div class="pc-what">
    <b>{{ $m['title'] ?? '' }}</b>
    <small @if ($subClass) class="{{ $subClass }}" @endif>{{ $sub }}</small>
    @if ($why)<small class="wy">{{ $why }}</small>@endif
  </div>
  <span class="band band-{{ \App\Support\SimilarityBand::of($mScore) }}">{{ $mScore === null ? 'Not scored' : $mScore.'%' }}</span>
  <span class="pc-cmp">Compare</span>
</a>
