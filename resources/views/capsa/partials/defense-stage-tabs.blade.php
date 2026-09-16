{{-- The stage is the first choice on these pages, so every stage is in view
     rather than hidden in a dropdown. --}}
<nav class="dx-stages" aria-label="Defense stage">
  @foreach ($state['stages'] as $stage)
    <a href="{{ request()->fullUrlWithQuery(['defense_type' => $stage]) }}" @if ($stage === $state['stage']) aria-current="page" @endif>{{ $stage }}</a>
  @endforeach
</nav>
