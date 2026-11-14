{{--
  Every title one team turned in, one line each.

  The old review modal showed one title at a time and had to be reopened
  after every decision. This is a page, so a decision reloads it and she is
  still here, and a team with ten titles is ten lines rather than ten
  reopenings.
--}}
@extends('capsa.team-management._shell')

@section('title', 'Pre-proposals - '.$row->team->team_name)

@section('tm-content')
@php
  $team = $row->team;
  $pending = collect($titles)->where('status', 'pending')->count();
  $recommended = collect($titles)->where('status', 'approved')->count();
  $rejected = collect($titles)->where('status', 'rejected')->count();
  $final = $team->selected_pre_proposal_id;
@endphp

<div class="th">
  <div class="tile"><x-tm-icon n="file" s="22" /></div>
  <div class="grow">
    <p>{{ $pending ? "{$pending} of ".count($titles).' titles need your decision' : 'Every title has a decision' }}</p>
  </div>
</div>

@if ($team->is_confirmed)
  <section class="card vfb ok">
    <span class="ic"><x-tm-icon n="shield" s="18" /></span>
    <div class="tx"><b>Team verified</b>Confirmed for this semester. Its members and title are settled.</div>
  </section>
@else
  {{-- No button: the team is verified by its title settling. --}}
  <section class="card vfb">
    <span class="ic"><x-tm-icon n="shield" s="18" /></span>
    <div class="tx"><b>Not verified yet</b>{{ match (true) {
      (bool) $pending => 'This team is verified on its own once every title has a decision and one is final. Nothing else to press.',
      $recommended >= 2 => 'You recommended '.$recommended.' titles, so the team picks one. It is verified the moment they do.',
      $recommended === 0 => 'No title was recommended. The team is verified once a new title they turn in is recommended.',
      default => 'The team is verified as soon as its recommended title becomes final.',
    } }}</div>
  </section>
@endif

<section class="card ppc" aria-label="Submitted titles">
  <div class="ppc-h">
    <label class="fs"><x-tm-icon n="search" s="15" />
      <input type="text" id="ppq" placeholder="Search titles" aria-label="Search titles"/></label>
    {{-- Filtering only hides rows, so the list never moves under her. --}}
    <div class="flt" role="group" aria-label="Show">
      <button type="button" data-f="all" aria-pressed="true">All <b>{{ count($titles) }}</b></button>
      <button type="button" data-f="approved" aria-pressed="false">Recommended <b>{{ $recommended }}</b></button>
      <button type="button" data-f="rejected" aria-pressed="false">Rejected <b>{{ $rejected }}</b></button>
    </div>
  </div>
  <ul class="ppr">
    @foreach ($titles as $i => $title)
      <li data-st="{{ $title['status'] }}" data-q="{{ \Illuminate\Support\Str::lower($title['title']) }}">
        <a class="rw" href="{{ route('capsa.team_management.title', [$team->team_id, $title['id']]) }}">
          <span class="no">#{{ $i + 1 }}</span>
          <span class="tt">{{ $title['title'] }}</span>
          <span class="ps">@include('capsa.team-management._marks')</span>
        </a>
      </li>
    @endforeach
    <li class="empty" @if (count($titles)) hidden @endif>
      {{ count($titles) ? 'No titles match. Clear the search or pick another filter.' : 'This team has not turned in any titles yet.' }}
    </li>
  </ul>
</section>
@endsection

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
  (function () {
    const list = document.querySelector('.ppr'), box = document.getElementById('ppq');
    let filter = 'all';
    const apply = () => {
      const q = box.value.trim().toLowerCase();
      let shown = 0;
      list.querySelectorAll('li[data-st]').forEach(li => {
        li.hidden = !((filter === 'all' || li.dataset.st === filter) && li.dataset.q.includes(q));
        shown += !li.hidden;
      });
      list.querySelector('.empty').hidden = shown > 0;
      document.querySelectorAll('[data-f]').forEach(b => b.setAttribute('aria-pressed', b.dataset.f === filter));
    };
    document.querySelectorAll('[data-f]').forEach(b => b.onclick = () => { filter = b.dataset.f; apply(); });
    box.oninput = apply;
  })();
</script>
@endpush
