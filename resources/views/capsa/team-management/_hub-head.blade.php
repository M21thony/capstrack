{{--
  The head of the Teams hub: one line on what is waiting, Team rules (CaPSA
  only) beside it, and the tabs. The page's name is already in the top bar,
  so it is not said again here.

  To do, Team Applications and Teams switch in place; Milestones is a page of
  its own.
--}}
@php
  $capsaPortal = $portal === 'capsa';
  $hubRoute = $capsaPortal ? 'capsa.team_management' : 'instructor.teams';
  $late = $todo->filter(fn ($i) => $i->age >= \App\Support\TeamTodo::LATE_AFTER_DAYS)->count();
  $verified = $rows->filter(fn ($r) => $r->team->is_confirmed)->count();
  $tabs = array_filter([
    'todo' => ['To do', $todo->count()],
    'applications' => $applications['open'] ? ['Team Applications', $applications['waiting']->count()] : null,
    'teams' => ['Teams', $rows->count()],
    'milestone_list' => $capsaPortal ? ['Milestones', null] : null,
  ]);
@endphp

<div class="hub-head">
  <div class="grow">
    <p class="hub-sum">
      @if ($todo->isNotEmpty())
        <b class="w">{{ $todo->count() }} to do</b>@if ($late), <b class="late">{{ $late }} waiting 3+ days</b>@endif ·
      @endif
      {{ $rows->count() }} {{ \Illuminate\Support\Str::plural('team', $rows->count()) }} · {{ $verified }} verified
      @if ($capsaPortal)
        @if ($allSections !== []) · {{ implode(', ', $allSections) }}@endif
      @else
      @endif
    </p>
  </div>

  {{-- How many more teams this person can take on, beside every tab: the
       To do's applications and the Team Applications list both lean on it. --}}
  @if ($applications['open'])
    @include('capsa.team-management._slots')
  @endif

  @if ($capsaPortal)
    {{-- The three team rules are three small answers, so they open in a
         menu under their button rather than sending her away. --}}
    <div class="setw">
      <button class="btn" type="button" id="setBtn" aria-expanded="false" aria-controls="setMenu">
        <x-tm-icon n="gear" s="15" />Team rules
      </button>
      {{-- On a phone the panel is a bottom sheet; this dims the page behind it,
           and tapping it cancels the same as clicking away does. --}}
      <div class="set-scrim" id="setScrim" hidden></div>
      <div class="menu" id="setMenu" hidden>
        <h3>Team rules</h3>
        {{-- One max per program she handles; these rules reach only her own
             sections, other CaPSAs set theirs. --}}
        @foreach ($rules['programs'] as $p)
          <label class="opt" for="tsize-{{ $p['program'] }}">
            <span><b>Max members per team · {{ $p['program'] }}</b><small>{{ implode(', ', $p['sections']) }} · {{ \App\Models\Team::MIN_MEMBERS }} to {{ \App\Models\Team::MAX_MEMBERS }}</small></span>
            <input type="number" class="tsize" id="tsize-{{ $p['program'] }}" data-program="{{ $p['program'] }}" min="{{ \App\Models\Team::MIN_MEMBERS }}" max="{{ \App\Models\Team::MAX_MEMBERS }}" step="1" inputmode="numeric">
          </label>
          <p class="opt-w" id="tsw-{{ $p['program'] }}" hidden></p>
        @endforeach
        <label class="opt" for="acreate">
          <span><b>Allow students to create teams</b><small>Students in your sections can start a new team</small></span>
          <input type="checkbox" class="tg" role="switch" id="acreate">
        </label>
        <label class="opt" for="ajoin">
          <span><b>Allow students to join teams</b><small>Students in your sections can ask to join a team</small></span>
          <input type="checkbox" class="tg" role="switch" id="ajoin">
        </label>
        <div class="mact">
          <button class="btn btn-o" type="button" id="setX">Cancel</button>
          <button class="btn btn-p" type="button" id="setOk">Save</button>
        </div>
      </div>
    </div>
  @endif
</div>

<div class="hub-tabs" role="tablist">
  @foreach ($tabs as $key => [$label, $n])
    <a role="tab" href="{{ route($hubRoute, array_filter(['tab' => $key, 'section' => $capsaPortal ? ($selectedSection ?? null) : null])) }}"
       @if ($key !== 'milestone_list') data-hub-tab="{{ $key }}" @endif
       aria-selected="{{ $tab === $key ? 'true' : 'false' }}">
      {{ $label }}@if ($n !== null)<span @class(['n', 'hot' => $key === 'todo' && $n > 0])>{{ $n }}</span>@endif
    </a>
  @endforeach
</div>
