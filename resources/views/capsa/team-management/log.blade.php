{{--
  Everything behind a team page's "See all": every paper sent to her, or
  every update. Only this page grows; the team page never does.
--}}
@extends('capsa.team-management._shell')

@section('title', ($log === 'papers' ? 'Papers' : 'Activity').' - '.$row->team->team_name)

@section('tm-content')
@php
  $team = $row->team;
  $subtitle = $log === 'papers'
      ? $row->papers->count().' '.\Illuminate\Support\Str::plural('paper', $row->papers->count()).', '.$row->papersToCheck.' to check'
      : $activity->count().' '.\Illuminate\Support\Str::plural('update', $activity->count()).', newest first';
@endphp

<div class="th">
  <span class="tile" style="background:linear-gradient(135deg,{{ $row->gradient }})">{{ $row->initials }}</span>
  <div class="grow">
    <h1>{{ $log === 'papers' ? 'Papers' : 'Activity' }}</h1>
    <p>{{ $team->team_name }} · {{ $subtitle }}</p>
  </div>
  <a class="btn btn-o" href="{{ route('capsa.team_management.show', $team->team_id) }}">Back to {{ $team->team_name }}</a>
</div>

<section class="tp-panel" style="max-width:720px;padding:14px 18px">
  @if ($log === 'papers')
    @if ($row->papers->isEmpty())
      <p class="tp-none">Nothing to check. Papers appear here when this team sends one to their Subject Adviser.</p>
    @else
      @include('capsa.team-management._papers', ['papers' => $row->papers])
    @endif
  @else
    @include('capsa.team-management._feed', ['events' => $activity])
  @endif
</section>
@endsection
