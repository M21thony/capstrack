{{--
  One archived capstone the check turned up, read beside the team's own words.

  Everything here used to be folded away behind "Show similarities, differences
  & suggestion" and "Show abstract to compare similarities" inside a modal, so
  comparing two documents meant opening two flaps and scrolling between them.
--}}
@extends('student.duplication.layout')

@section('page-title', 'Matched capstone')

@section('dup')
@php
  use App\Services\SimilarityService;
  use Illuminate\Support\Str;

  $mark = fn (?string $text) => SimilarityService::highlight($text, $words);

  $score = ($match['assessed'] ?? true) ? ($match['score'] ?? null) : null;
  // 65% and above is highly similar. Below that nothing is claimed at all.
  $flagged = $score !== null && $score >= 65;
  $reads = fn ($s) => $s === null ? 'not assessed' : $s.'%';

  $year = $match['academic_year'] ?? 'N/A';
  $program = $match['program'] ?? '';

  $headline = $score === null
      ? 'This one came back on keywords, but was never scored'
      : ($flagged ? 'Your title is highly similar to' : 'Your title overlaps')
        .' a '.$year.' '.$program.' capstone';

  // A match opened straight from the Pre-Proposal page has to go back there.
  // Sending them to the full report drops them on a page they never opened.
  // Previous and Next carry the marker so it survives paging between matches.
  $fromPreProposal = request('from') === 'pp';
  $backUrl = $fromPreProposal ? route('student.duplication') : route('student.duplication.report', $check->id);
  $backText = $fromPreProposal ? 'Back to your pre-proposal' : 'Back to all '.$total.' matches';
  $step = fn ($r) => route('student.duplication.similar', ['check' => $check->id, 'rank' => $r] + ($fromPreProposal ? ['from' => 'pp'] : []));
@endphp

<nav class="dup-crumb" aria-label="Breadcrumb">
  <a href="{{ route('student.duplication') }}">Pre-Proposal</a>
  <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
  <a href="{{ route('student.duplication.report', $check->id) }}">{{ Str::limit($mineTitle, 38) }}</a>
  <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
  <span class="here">Match {{ $rank }} of {{ $total }} &middot; {{ $reads($score) }}</span>
</nav>

<div class="pager">
  <a class="pbtn" href="{{ $backUrl }}">
    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    {{ $backText }}
  </a>
  <div style="display:flex;gap:8px">
    <a class="pbtn {{ $rank > 1 ? '' : 'off' }}"
       @if ($rank > 1) href="{{ $step($rank - 1) }}" @endif>
      <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
      Previous{{ $rank > 1 ? ' · '.$reads($prevScore) : '' }}
    </a>
    <a class="pbtn {{ $rank < $total ? '' : 'off' }}"
       @if ($rank < $total) href="{{ $step($rank + 1) }}" @endif>
      Next{{ $rank < $total ? ' · '.$reads($nextScore) : '' }}
      <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
  </div>
</div>

<div class="card verdict">
  <div class="bigscore" style="color:{{ $flagged ? 'var(--red)' : 'var(--text2)' }}">
    @if ($score === null)—@else{{ $score }}<span style="font-size:22px">%</span>@endif
  </div>
  <div class="doc">
    <div class="t">{{ $headline }}</div>
    <div class="m">Gold marks below are words that appear in both documents. They are matched literally, not by the AI.</div>
  </div>
  @if ($flagged)
    <span class="stateline s-red">
      <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      Highly similar
    </span>
  @elseif ($score === null)
    <span class="stateline s-grey">Not assessed by the AI</span>
  @endif
</div>

<div class="vs">
  <div class="col mine">
    <div class="who">Your pre-proposal</div>
    <h3>{!! $mark($mineTitle) !!}</h3>
    <div class="sub">What your team wrote</div>
    @forelse ($mineFields as $label => $value)
      <div class="field">
        <div class="k">{{ $label }}</div>
        <div class="v">{!! $mark($value) !!}</div>
      </div>
    @empty
      <div class="dup-none">Nothing but the title was saved with this check.</div>
    @endforelse
  </div>

  <div class="col">
    <div class="who">Archived capstone &middot; {{ $year }}</div>
    <h3>{!! $mark($match['title'] ?? '') !!}</h3>
    <div class="sub">{{ $program ?: 'Program not recorded' }}</div>
    <div class="field">
      <div class="k">Written by</div>
      <div class="v">{{ $match['authors'] ?: 'Authors not recorded yet' }}</div>
    </div>
    <div class="field">
      <div class="k">Abstract</div>
      <div class="v">{!! $match['abstract'] ? $mark($match['abstract']) : 'No abstract was recorded for this capstone.' !!}</div>
    </div>
  </div>
</div>

@if (! empty($match['similarities']) || ! empty($match['differences']) || ! empty($match['suggestion']))
  <div class="grid3">
    @if (! empty($match['similarities']))
      <div class="box box-sim">
        <h3><svg viewBox="0 0 24 24" stroke="currentColor" fill="none"><circle cx="12" cy="12" r="9"/><line x1="8" y1="12" x2="16" y2="12"/></svg>Similarities</h3>
        <p>{{ $match['similarities'] }}</p>
      </div>
    @endif
    @if (! empty($match['differences']))
      <div class="box box-dif">
        <h3><svg viewBox="0 0 24 24" stroke="currentColor" fill="none"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Differences</h3>
        <p>{{ $match['differences'] }}</p>
      </div>
    @endif
    @if (! empty($match['suggestion']))
      <div class="box box-sug">
        <h3><svg viewBox="0 0 24 24" stroke="currentColor" fill="none"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z"/></svg>What to do</h3>
        <p>{{ $match['suggestion'] }}</p>
      </div>
    @endif
  </div>
@endif
@endsection
