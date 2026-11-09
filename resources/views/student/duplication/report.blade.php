{{--
  One saved title's Similarity Report: the team's words on the left, the
  same results table as the check page on the right, and the suggested
  experts behind a button. It fits one laptop screen; a shorter screen
  scrolls the page itself, once.
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
      ? 'Turned in '.Carbon::parse($proposal->submitted_at)->format('j M Y')
      : 'Checked '.($check->created_at?->format('j M Y') ?? 'earlier');

  $standing = match (true) {
      ! $proposal => 'not saved as a pre-proposal',
      $proposal->status === 'approved' => 'recommended by your CaPSA',
      $proposal->status === 'rejected' => 'rejected',
      (bool) $proposal->submitted_at => 'awaiting review',
      default => 'draft',
  };

  $suggested = collect($check->suggestionsForDisplay())->sortBy(fn ($i) => ['Adviser' => 0, 'Consultant' => 1][$i['role_label'] ?? ''] ?? 2)->values();
@endphp

<nav class="dup-crumb" aria-label="Breadcrumb">
  <a href="{{ route('student.duplication') }}">Pre-proposal</a>
  <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
  <span class="here" aria-current="page">Similarity report</span>
</nav>

<section class="card rp-head" aria-label="Result">
  <div class="rp-doc">
    <h1 class="rp-title" title="{{ $title }}">{{ $title }}</h1>
    <div class="rp-meta">
      <span class="band band-{{ $band }}">{{ $score === null ? 'Not scored' : $score.'% closest assessed match' }}</span>
      <span>{{ $when }} &middot; {{ $standing }}</span>
    </div>
    @if (($top = reset($thisYear)) && SimilarityBand::scoreOf($top) >= SimilarityBand::SOME)
      <div class="ty-note">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span><b>{{ str_starts_with($top['recommended'], 'Pending') ? 'Pending this year' : 'Recommended this year' }}:</b> {{ $top['title'] ?? '' }}@if (str_contains($top['recommended'], ' — ')) ({{ Str::after($top['recommended'], ' — ') }})@endif is {{ SimilarityBand::scoreOf($top) }}% close to yours.</span>
      </div>
    @endif
  </div>
  <div class="rp-acts">
    @if ($suggested->isNotEmpty())
      <button type="button" class="pc-btn" onclick="openDlg('rp-sx-dlg')">
        <svg viewBox="0 0 24 24" style="stroke:#0f766e"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Experts <span class="pc-n">{{ $suggested->count() }}</span>
      </button>
    @endif
    @if ($proposal && $check->document_path)
      <a class="pc-btn pc-gold" href="{{ route('student.pre-proposal.document', $proposal->id) }}" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Open PDF
      </a>
    @endif
  </div>
</section>

<div class="rp-grid">
  <section class="card rp-mine" aria-labelledby="rp-mine-h">
    <h2 class="pc-card-h" id="rp-mine-h"><svg viewBox="0 0 24 24" style="stroke:#c62828"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg>Your pre-proposal</h2>
    <div class="rp-body">
      <div class="rp-v-title">{{ $title }}</div>
      @if ($sdgs)
        <div class="rp-sdgs">
          @foreach ($sdgs as $sdg)
            <span>{{ $sdg }}</span>
          @endforeach
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
        <button type="button" class="pc-btn" onclick="openFullPreProposal()">
          <svg viewBox="0 0 24 24"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
          Read full pre-proposal
        </button>
      </div>
    @endif
  </section>

  <section class="card pc-results" aria-labelledby="pc-res-h">
    <h2 class="pc-card-h" id="pc-res-h"><svg viewBox="0 0 24 24" style="stroke:#7c3aed"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>Similarity results</h2>
    <div class="pc-tabs" role="tablist">
      <button type="button" role="tab" id="tab-ty" aria-selected="false" aria-controls="ty-list" onclick="showTab('ty')">Recommended this year <span class="pc-n">{{ count($thisYear) }}</span></button>
      <button type="button" role="tab" id="tab-ar" aria-selected="true" aria-controls="matches-list" onclick="showTab('ar')">Top 10 archived <span class="pc-n">{{ count($archive) }}</span></button>
    </div>
    <div class="pc-thead" aria-hidden="true"><span>Study</span><span>Similarity</span><span>Action</span></div>
    <div class="pc-rows" id="ty-list" role="tabpanel" aria-labelledby="tab-ty" hidden>
      @forelse ($thisYear as $i => $m)
        @include('student.duplication._report-row', ['sub' => Str::after($m['recommended'], ' — ').(str_starts_with($m['recommended'], 'Pending') ? ' · Pending, not yet decided' : ''), 'subClass' => 'rp-sec'])
      @empty
        <div class="dup-none">No recommended titles from this year came back.</div>
      @endforelse
    </div>
    <div class="pc-rows" id="matches-list" role="tabpanel" aria-labelledby="tab-ar">
      @forelse ($archive as $i => $m)
        @include('student.duplication._report-row', ['sub' => ($m['academic_year'] ?? 'N/A').(! empty($m['program']) ? ' · '.$m['program'] : ''), 'subClass' => null])
      @empty
        <div class="dup-none">Nothing in the archive came back for this title.</div>
      @endforelse
    </div>
    <div class="pc-foot">
      <span id="match-count" data-ty="{{ count($thisYear) }} recommended this year" data-ar="{{ count($archive) }} archived matches">{{ count($archive) }} archived matches</span>
      <a class="golink" href="{{ route('student.duplication') }}"><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg> Back to check</a>
    </div>
  </section>
</div>

@if ($suggested->isNotEmpty())
  <dialog class="rp-dialog pc-dlg" id="rp-sx-dlg" aria-labelledby="rp-sx-h">
    <div class="rp-dialog-h">
      <h2 id="rp-sx-h">Suggested experts</h2>
      <button type="button" class="rp-x" aria-label="Close" onclick="this.closest('dialog').close()">
        <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="rp-dialog-b">
      <div class="rp-sx">
        @unless ($applyOpen)
          <div class="dup-apply-note" title="{{ $applyLockReason }}">{{ $applyLockReason }}</div>
        @endunless
        @foreach ($suggested as $i)
          @include('student.duplication._expert', ['i' => $i])
        @endforeach
      </div>
    </div>
  </dialog>
@endif

{{-- Everything the team wrote, for when the left card is not enough. --}}
<dialog class="rp-dialog" id="rp-full" aria-labelledby="rp-full-h">
  <div class="rp-dialog-h">
    <div>
      <div class="k">Pre-proposal content</div>
      <h2 id="rp-full-h">{{ $title }}</h2>
    </div>
    <button type="button" class="rp-x" aria-label="Close" onclick="this.closest('dialog').close()">
      <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="rp-dialog-b">
    @php($tagged = ['SDG', 'Research agenda and priority areas', 'Development method', 'Tags'])
    @if (array_intersect_key($fields, array_flip($tagged)))
      <div class="rf-facts">
        @foreach (array_intersect_key($fields, array_flip($tagged)) as $label => $value)
          <div class="rf-fact">
            <div class="rf-k">{{ $label }}</div>
            <div class="rf-tags">
              @foreach (explode("\n", $value) as $tag)
                @if (trim($tag) !== '')<span>{{ trim($tag) }}</span>@endif
              @endforeach
            </div>
          </div>
        @endforeach
      </div>
    @endif
    @foreach (array_diff_key($fields, array_flip($tagged)) as $label => $value)
      <section class="rf-sec">
        <h3 class="rf-h">{{ $label }}</h3>
        @if ($label === 'Limitations' && count($items = preg_split('/(?<=\.)\s+(?=[A-Z])/', trim($value))) > 1)
          <ul class="rf-list">
            @foreach ($items as $item)<li>{{ $item }}</li>@endforeach
          </ul>
        @else
          <p class="rf-p">{{ $value }}</p>
        @endif
      </section>
    @endforeach
  </div>
</dialog>

<script nonce="{{ Vite::cspNonce() }}">
function openDlg(id) {
  var d = document.getElementById(id);
  if (d && !d.open) d.showModal();
}
function openFullPreProposal() { openDlg('rp-full'); }
document.querySelectorAll('dialog').forEach(function (d) {
  d.addEventListener('click', function (e) { if (e.target === d) d.close(); });
});
function showTab(which) {
  var ty = which === 'ty', c = document.getElementById('match-count');
  document.getElementById('tab-ty').setAttribute('aria-selected', ty);
  document.getElementById('tab-ar').setAttribute('aria-selected', !ty);
  document.getElementById('ty-list').hidden = !ty;
  document.getElementById('matches-list').hidden = ty;
  c.textContent = ty ? c.dataset.ty : c.dataset.ar;
}
</script>
@endsection
