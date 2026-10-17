{{--
  How many teams this person can still take on: one pill per slot, gold for
  the ones taken. At the top of Teams, beside every tab.
--}}
@php
  $usedSlots = $applications['usedSlots'];
  $maxSlots = $applications['maxSlots'];
@endphp
<span class="slots" aria-label="Your slots: {{ $usedSlots }}{{ $maxSlots > 0 ? ' of '.$maxSlots : '' }} used">
  <span class="slots-l">Your slots</span>
  @if ($maxSlots > 0)
    <span class="pills" aria-hidden="true">
      @for ($i = 0; $i < $maxSlots; $i++)<i @class(['used' => $i < $usedSlots])></i>@endfor
    </span>
    <span><b>{{ $usedSlots }} of {{ $maxSlots }}</b> used ·
      @if ($usedSlots >= $maxSlots)<span class="full">Full</span>@else<span class="open">{{ $maxSlots - $usedSlots }} open</span>@endif
    </span>
  @else
    <span><b>{{ $usedSlots }}</b> {{ $usedSlots === 1 ? 'team' : 'teams' }} · no limit set</span>
  @endif
</span>
