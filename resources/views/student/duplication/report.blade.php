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
        $from = $m['recommended'] ?? (($m['academic_year'] ?? 'N/A').(! empty($m['program']) ? ' · '.$m['program'] : ''));
      @endphp
      <a class="rp-row" href="{{ route('student.duplication.similar', [$check->id, $i + 1]) }}" title="{{ $m['title'] ?? '' }}">
        <span class="rp-rank">
          <b class="sim-{{ SimilarityBand::of($mScore) }}">{{ $mScore === null ? '—' : $mScore.'%' }}</b>
          <small>#{{ $i + 1 }}</small>
        </span>
        <span class="rp-what">
          <b>{{ $m['title'] ?? '' }}</b>
          <small><span>{{ $from }}</span>@if ($why) &middot; {{ $why }}@endif</small>
        </span>
      </a>
    @empty
      <div class="dup-none">Nothing in the archive came back for this title.</div>
    @endforelse
  </section>
</div>

@if (count($check->instructors_suggested ?? []))
  <section class="card rp-experts" aria-labelledby="rp-experts-h">
    @php
      // Three full cards at a time, advisers first. A card for every
      // suggestion wrapped onto more rows and pushed the page past the fold;
      // paging keeps the section one row tall with nothing cut off or folded.
      $suggested = collect($check->suggestionsForDisplay())->sortBy(fn ($i) => ['Adviser' => 0, 'Consultant' => 1][$i['role_label'] ?? ''] ?? 2)->values();
      $perPage = 3;
    @endphp
    <div class="rp-experts-h">
      <h2 id="rp-experts-h">Suggested experts</h2>
      @unless ($applyOpen)
        <div class="dup-apply-note" title="{{ $applyLockReason }}">{{ $applyLockReason }}</div>
      @endunless
      @if ($suggested->count() > $perPage)
        <div class="rp-pager" data-per-page="{{ $perPage }}" data-total="{{ $suggested->count() }}">
          <span class="rp-pager-count" aria-live="polite">1&ndash;{{ $perPage }} of {{ $suggested->count() }}</span>
          <button type="button" class="rp-page-btn" data-step="-1" aria-label="Previous experts" disabled>
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <button type="button" class="rp-page-btn" data-step="1" aria-label="Next experts">
            <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>
      @endif
    </div>
    <div class="rp-experts-grid" id="rp-experts-grid">
      @foreach ($suggested as $n => $i)
        <div class="rp-card-slot" @if ($n >= $perPage) hidden @endif>
          @include('student.duplication._expert', ['i' => $i])
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

<script nonce="{{ Vite::cspNonce() }}">
// Suggested experts, three at a time.
(function () {
  var pager = document.querySelector('.rp-pager');
  if (!pager) return;
  var per = +pager.dataset.perPage, total = +pager.dataset.total, start = 0;
  var slots = document.querySelectorAll('#rp-experts-grid .rp-card-slot');
  var count = pager.querySelector('.rp-pager-count');
  var prev = pager.querySelector('[data-step="-1"]'), next = pager.querySelector('[data-step="1"]');
  pager.addEventListener('click', function (e) {
    var btn = e.target.closest('.rp-page-btn');
    if (!btn || btn.disabled) return;
    start = Math.min(Math.max(0, start + (+btn.dataset.step) * per), Math.floor((total - 1) / per) * per);
    slots.forEach(function (slot, n) { slot.hidden = n < start || n >= start + per; });
    count.textContent = (start + 1) + '\u2013' + Math.min(start + per, total) + ' of ' + total;
    prev.disabled = start === 0;
    next.disabled = start + per >= total;
  });
})();

function openFullPreProposal() {
  var d = document.getElementById('rp-full');
  if (d && !d.open) d.showModal();
}
document.getElementById('rp-full').addEventListener('click', function (e) {
  if (e.target === this) this.close();
});
</script>
@endsection
