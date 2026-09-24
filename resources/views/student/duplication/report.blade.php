{{--
  One saved title's Similarity Report.

  This used to be a modal built from a hidden copy of itself sitting further
  down the pre-proposal page, so the report could not be reloaded, linked,
  printed or shown to an adviser, and was gone the moment the page closed. It
  is a page with an address now, laid out the way the CaPSA's copy is, so both
  sides read the same thing.

  It fits one laptop screen: the team's words beside what came back, the
  suggested experts underneath, and nothing scrolling inside anything. A
  shorter screen scrolls the page itself, once.
--}}
@extends('student.duplication.layout')

@section('page-title', 'Similarity Report')

@section('dup')
@php
  use App\Support\SimilarityBand;
  use Illuminate\Support\Carbon;
  use Illuminate\Support\Str;

  $score = $check->top_score;
  $band = SimilarityBand::of($score);

  $when = $proposal?->submitted_at
      ? 'turned in '.Carbon::parse($proposal->submitted_at)->format('j M Y')
      : 'checked '.($check->created_at?->format('j M Y') ?? 'earlier');

  $standing = match (true) {
      ! $proposal => 'not saved as a pre-proposal',
      $proposal->status === 'approved' => 'recommended by your CaPSA',
      $proposal->status === 'rejected' => 'rejected',
      (bool) $proposal->submitted_at => 'awaiting review',
      default => 'draft',
  };
@endphp

<nav class="dup-crumb" aria-label="Breadcrumb">
  <a href="{{ route('student.duplication') }}">Pre-Proposal</a>
  <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
  <a href="{{ route('student.duplication') }}">Duplication check</a>
  <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
  <span class="here" aria-current="page">{{ Str::limit($title, 46) }} &middot; Similarity Report</span>
</nav>

<section class="card rp-head" aria-label="Result">
  <a class="rp-back" href="{{ route('student.duplication') }}" aria-label="Back to your pre-proposals" title="Back to your pre-proposals">
    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
  </a>
  <div class="rp-score">
    <span class="rp-pc sim-{{ $band }}">{{ $score ?? '—' }}@if ($score !== null)<small>%</small>@endif</span>
    <span class="rp-cap">closest match</span>
  </div>
  <div class="rp-doc">
    <h1 class="rp-title" title="{{ $title }}">{{ $title }}</h1>
    <div class="rp-meta">
      <span class="band band-{{ $band }}">{{ SimilarityBand::BANDS[$band] }}</span>
      <span>{{ count($matches) }} of {{ number_format($archiveTotal) }} archived capstones came back &middot; {{ $when }} &middot; {{ $standing }}</span>
    </div>
  </div>
  <div class="rp-counts" aria-label="How close the matches are">
    <span class="band band-close">{{ $bands['close'] }} close</span>
    <span class="band band-some">{{ $bands['some'] }} some overlap</span>
    <span class="band band-little">{{ $bands['little'] }} little overlap</span>
  </div>
  @if ($proposal && $check->document_path)
    <a class="pbtn" href="{{ route('student.pre-proposal.document', $proposal->id) }}" target="_blank" rel="noopener">
      <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Your PDF
    </a>
  @endif
</section>

<div class="rp-grid">
  <section class="card rp-mine" aria-labelledby="rp-mine-h">
    <div class="rp-card-h">
      <h2 id="rp-mine-h">Pre-proposal content</h2>
      <span>saved with the title</span>
    </div>
    <div class="rp-body">
      <div class="rp-field">
        <div class="k">Title</div>
        <div class="rp-v-title" title="{{ $title }}">{{ $title }}</div>
      </div>
      @if ($sdgs)
        <div class="rp-field">
          <div class="k">SDG</div>
          <div class="rp-sdgs">
            @foreach (array_slice($sdgs, 0, 3) as $sdg)
              <span>{{ $sdg }}</span>
            @endforeach
            @if (count($sdgs) > 3)
              <button type="button" class="rp-more" onclick="openFullPreProposal()">+{{ count($sdgs) - 3 }} more</button>
            @endif
          </div>
        </div>
      @endif
      @forelse ($lead as $label => $value)
        <div class="rp-field">
          <div class="k">{{ $label }}</div>
          <div class="rp-v">{{ $value }}</div>
        </div>
      @empty
        @unless ($sdgs)
          <div class="dup-none">Nothing was saved with this check.</div>
        @endunless
      @endforelse
    </div>
    @if ($fields)
      <div class="rp-foot">
        <button type="button" class="rp-read" onclick="openFullPreProposal()">
          <svg viewBox="0 0 24 24"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
          Read the full pre-proposal
        </button>
      </div>
    @endif
  </section>

  <section class="card rp-list" aria-labelledby="rp-list-h">
    <div class="rp-card-h">
      <h2 id="rp-list-h">{{ count($matches) }} archived {{ Str::plural('capstone', count($matches)) }} came back</h2>
      <span>highest first &middot; click one to read it</span>
    </div>
    @forelse ($matches as $i => $m)
      @php
        $mScore = SimilarityBand::scoreOf($m);
        $why = $m['similarities'] ?? ($m['reason'] ?? null);
      @endphp
      <a class="rp-row" href="{{ route('student.duplication.similar', [$check->id, $i + 1]) }}" title="{{ $m['title'] ?? '' }}">
        <span class="rp-rank">
          <b class="sim-{{ SimilarityBand::of($mScore) }}">{{ $mScore === null ? '—' : $mScore.'%' }}</b>
          <small>#{{ $i + 1 }}</small>
        </span>
        <span class="rp-what">
          <b>{{ $m['title'] ?? '' }}</b>
          <small><span>{{ $m['academic_year'] ?? 'N/A' }}@if (! empty($m['program'])) &middot; {{ $m['program'] }}@endif</span>@if ($why) &middot; {{ $why }}@endif</small>
        </span>
      </a>
    @empty
      <div class="dup-none">Nothing in the archive came back for this title.</div>
    @endforelse
  </section>
</div>

@if (count($check->instructors_suggested ?? []))
  <section class="card rp-experts" aria-labelledby="rp-experts-h">
    <div class="rp-experts-h">
      <h2 id="rp-experts-h">Suggested experts</h2>
      @unless ($applyOpen)
        <div class="dup-apply-note" title="{{ $applyLockReason }}">{{ $applyLockReason }}</div>
      @endunless
    </div>
    <div class="rp-experts-grid">
      @foreach ($check->suggestionsForDisplay() as $i)
        <div class="rp-expert">
          <div class="avatar">{{ strtoupper(substr($i['name'] ?? '?', 0, 1)) }}</div>
          <div class="rp-expert-who">
            @if (! empty($i['role_label']))
              <div class="sx-role">Recommended {{ $i['role_label'] }}</div>
            @endif
            <div class="n">{{ $i['name'] ?? '' }}</div>
            <div class="x">{{ $i['expertise'] ?? '' }}</div>
            <div class="r">{{ $i['reason'] ?? '' }}</div>
          </div>
          @if (! empty($i['id']))
            @if ($applyOpen)
              <a class="dup-apply-btn" href="{{ route('student.experts', ['apply' => $i['id']]) }}">
                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
                Apply
              </a>
            @else
              {{-- The wrapper carries the tooltip: a disabled button fires no
                   hover events of its own. --}}
              {{-- Just the lock: the reason is in the heading above, and a
                   label here squeezed the expert's name out of the card. --}}
              <span class="dup-apply-wrap" data-tip="{{ $applyLockShort }}" tabindex="0" role="note" aria-label="{{ $applyLockReason }}">
                <button type="button" class="dup-apply-btn locked rp-lock" disabled>
                  <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                  <span class="rp-sr">{{ $applyLockShort }}</span>
                </button>
              </span>
            @endif
          @endif
        </div>
      @endforeach
    </div>
  </section>
@endif

{{-- Everything the team wrote, for when three lines of each is not enough. --}}
<dialog class="rp-dialog" id="rp-full" aria-labelledby="rp-full-h">
  <div class="rp-dialog-h">
    <div>
      <div class="k">Pre-proposal content</div>
      <h2 id="rp-full-h">{{ $title }}</h2>
    </div>
    <button type="button" class="rp-x" aria-label="Close" onclick="document.getElementById('rp-full').close()">
      <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="rp-dialog-b">
    @foreach ($fields as $label => $value)
      <div class="field">
        <div class="k">{{ $label }}</div>
        <div class="v">{{ $value }}</div>
      </div>
    @endforeach
  </div>
</dialog>

<script>
function openFullPreProposal() {
  var d = document.getElementById('rp-full');
  if (d && !d.open) d.showModal();
}
document.getElementById('rp-full').addEventListener('click', function (e) {
  if (e.target === this) this.close();
});
</script>
@endsection
