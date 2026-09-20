{{--
  One archive study the check matched, and how it compares.

  Previous and Next step through the whole ranked list, so reading all ten
  never means going back to the report between each one.
--}}
@extends('capsa.team-management._shell')

@section('title', $match['title'] ?: 'Similar study')

@section('tm-content')
@php
  $team = $row->team;
  $words = $title['duplication']['highlight_words'] ?? [];
  $link = fn ($k) => route('capsa.team_management.similar', [$team->team_id, $preProposal->id, $k]);
  $meta = collect([$match['academic_year'], $match['program'], $match['authors'] ? 'By '.$match['authors'] : null])
      ->filter()->implode(' · ');
@endphp

<div class="th tpg">
  <div class="tile"><x-tm-icon n="file" s="22" /></div>
  <div class="grow">
    <p>Similar study #{{ $rank }} of {{ count($matches) }} &middot; compared with &ldquo;{{ $title['title'] }}&rdquo;</p>
    <h1>{{ $match['title'] ?: 'Untitled study' }}</h1>
    <div class="ps">
      <x-tm-sim :score="$match['score']" />
      @if ($meta)<span class="mm">{{ $meta }}</span>@endif
    </div>
  </div>
</div>

<section class="card ppd">
  @if ($match['reason'])
    <div><h4>Why it matched</h4><p class="msg"><b>{{ $match['reason'] }}</b></p></div>
  @endif

  @if ($match['similarities'] || $match['differences'] || $match['suggestion'])
    <dl class="ans">
      @if ($match['similarities'])<dt>Similarities</dt><dd>{{ $match['similarities'] }}</dd>@endif
      @if ($match['differences'])<dt>Differences</dt><dd>{{ $match['differences'] }}</dd>@endif
      @if ($match['suggestion'])<dt>Suggestion</dt><dd class="sg">{{ $match['suggestion'] }}</dd>@endif
    </dl>
  @endif

  <div class="ab">
    <h4>Their abstract</h4>
    @if ($match['abstract'])
      <div class="k">Words this title shares with it are highlighted.</div>
      {{-- Lit by the same words the check pulled out of the title. --}}
      <p>{!! \App\Services\SimilarityService::highlight($match['abstract'], $words) !!}</p>
    @else
      <p class="none">The archive has no abstract for this study.</p>
    @endif
  </div>
</section>

<nav class="mnav" aria-label="Other similar studies">
  @if ($rank > 1)
    <a class="btn btn-o pv" href="{{ $link($rank - 1) }}">
      <span class="k">Previous &middot; #{{ $rank - 1 }} &middot; {{ (int) round((float) ($matches[$rank - 2]['score'] ?? 0)) }}%</span>
      {{ $matches[$rank - 2]['title'] }}
    </a>
  @else
    <span></span>
  @endif
  @if ($rank < count($matches))
    <a class="btn btn-o nx" href="{{ $link($rank + 1) }}">
      <span class="k">Next &middot; #{{ $rank + 1 }} &middot; {{ (int) round((float) ($matches[$rank]['score'] ?? 0)) }}%</span>
      {{ $matches[$rank]['title'] }}
    </a>
  @else
    <span></span>
  @endif
</nav>
@endsection
