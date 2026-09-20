{{--
  A title's closest match, said the way the adviser asked for it.

  The check flags, it does not judge. 65% and up is called out; below that
  the number stands on its own with no word after it, so the page never
  looks like it has already decided for her.
--}}
@props(['score' => null])
@php $s = (int) round((float) $score); @endphp
@if ($s <= 0)
  <span class="pill p-gray">No matches</span>
@elseif ($s >= 65)
  <span class="pill p-red"><x-tm-icon n="warn" s="12" />{{ $s }}% &middot; Highly similar</span>
@else
  <span class="pill p-gray">{{ $s }}%</span>
@endif
