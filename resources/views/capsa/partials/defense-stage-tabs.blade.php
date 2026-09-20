{{-- The stage is the first choice on these pages, so every stage is in view
     rather than hidden in a dropdown.

     When $sched is set (Defense Scheduling), these are in-page buttons: the
     board switches stage without a reload, so moving between Pre-proposal,
     Title, Pre Oral and Final Oral is one click instead of a page each. Any
     other page keeps the plain links. --}}
@if (! empty($sched))
  <nav class="dx-stages" aria-label="Defense stage" id="dx-stages">
    @foreach ($state['stages'] as $stage)
      <button type="button" class="dx-stage" data-stage="{{ $stage }}" @if ($stage === $state['stage']) aria-current="page" @endif>{{ $stage }}</button>
    @endforeach
  </nav>
@else
  <nav class="dx-stages" aria-label="Defense stage">
    @foreach ($state['stages'] as $stage)
      <a href="{{ request()->fullUrlWithQuery(['defense_type' => $stage]) }}" @if ($stage === $state['stage']) aria-current="page" @endif>{{ $stage }}</a>
    @endforeach
  </nav>
@endif