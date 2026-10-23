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

  // Another team's title recommended this year, not an archive study.
  $recommended = $match['recommended'] ?? null;
  $section = $recommended && str_contains($recommended, ' — ') ? Str::after($recommended, ' — ') : null;
  // Turned in by another team and not decided yet.
  $pending = $recommended && str_starts_with($recommended, 'Pending');

  $headline = $score === null
      ? 'This one came back on keywords, but was never scored'
      : ($flagged ? 'Your title is highly similar to' : 'Your title overlaps')
        .($pending ? ' a title still pending this year' : ($recommended ? ' a title already recommended this year' : ' a '.$year.' '.$program.' capstone'));

  // A match opened straight from the Pre-Proposal page has to go back there.
  // Sending them to the full report drops them on a page they never opened.
  // Previous and Next carry the marker so it survives paging between matches.
  $fromPreProposal = request('from') === 'pp';
  $backUrl = $fromPreProposal ? route('student.duplication') : route('student.duplication.report', $check->id);
  $backText = $fromPreProposal ? 'Back to your pre-proposal' : 'Back to all '.$total.' matches';
  $step = fn ($r) => route('student.duplication.similar', ['check' => $check->id, 'rank' => $r] + ($fromPreProposal ? ['from' => 'pp'] : []));

  // How the score above was reached, from the two numbers saved with the match
  // (SimilarityService::enrichWithReasons). Checks run before they were saved have neither.
  $emb = $match['embedding_score'] ?? null;
  $judge = $match['judge_score'] ?? null;
  $read = $match['assessed'] ?? true;
  $same = $match['is_relevant'] ?? true;
  $contra = $read && ! $same && $judge !== null && $judge >= 55;
  $cut = $read && ($contra || ($judge === null && ! $same));
@endphp

<nav class="dup-crumb" aria-label="Breadcrumb">
  <a href="{{ route('student.duplication') }}">Pre-Proposal</a>
  <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
  <a href="{{ route('student.duplication.report', $check->id) }}">{{ Str::limit($mineTitle, 38) }}</a>
  <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
  <span class="here">{{ $groupLabel }} {{ $groupPos }} of {{ $groupTotal }} &middot; {{ $reads($score) }}</span>
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
    @if ($recommended)
      <div class="ty-note">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        @if ($pending)
          <span><b>Pending, not yet decided:</b> another team turned this in and its CaPSA has not decided yet. If it is recommended, two teams can't have the same title in one school year, so make yours different enough.</span>
        @else
          <span><b>Recommended this year:</b> two teams can't have the same title in one school year. Change your title or its focus before you turn it in.</span>
        @endif
      </div>
    @endif
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

<section class="card how" aria-labelledby="how-h">
  <h2 id="how-h">How this score is worked out</h2>
  <ol class="steps">
    <li>
      <span class="no">1</span>
      <div>
        <b>Meaning match</b>
        <p>Both write-ups are turned into meaning vectors by <code>{{ config('services.ollama.embed_model') }}</code> and compared with cosine similarity.
          @if ($emb !== null)
            <code>({{ number_format($emb / 50 - 1, 2) }} + 1) ÷ 2 × 100 = {{ $emb }}%</code>.
          @else
            This check was run before this number was saved.
          @endif
          Under 40% is dropped as noise.</p>
      </div>
      <span class="val">{{ $emb !== null ? $emb.'%' : '—' }}</span>
    </li>
    <li class="{{ $read ? '' : 'skip' }}">
      <span class="no">2</span>
      <div>
        <b>AI judge</b>
        <p>
          @if (! $read)
            Not read. Only the {{ config('services.ollama.judge_limit') }} closest titles from step 1 go to the judge.
          @else
            CapsTrack's judge model (<code>{{ config('services.ollama.judge_model') ?: config('services.ollama.model') }}</code>) reads both and compares the problem, users and features.
            It said: <b>{{ $same ? 'same kind of study' : 'not the same study' }}</b>{{ $judge !== null ? ', '.$judge.'%' : ', with no number' }}.
          @endif
        </p>
      </div>
      <span class="val">{{ $read && $judge !== null ? $judge.'%' : '—' }}</span>
    </li>
    <li>
      <span class="no">3</span>
      <div>
        <b>Score shown</b>
        <p>
          @if (! $read)
            No score is shown for a title the judge did not read.
          @elseif ($cut)
            {{ $contra ? 'The judge said "not the same" but gave 55% or more, which contradicts itself.' : 'The judge said "not the same" and gave no number.' }}
            So the step 1 match is used, cut to 40%@if ($emb !== null): <code>{{ $emb }} × 0.4 = {{ $score }}</code>@endif.
          @elseif ($judge === null && $emb !== null)
            The judge gave no number, so the step 1 match is used.
          @else
            The judge's score is used.
          @endif
          65% and up is close to existing work, 40–64% some overlap, below 40% little overlap.
        </p>
      </div>
      <span class="val">{{ $score !== null ? $score.'%' : '—' }}</span>
    </li>
  </ol>
  <ol class="refs">
    <li>Salton, G., &amp; McGill, M. J. (1983). <i>Introduction to Modern Information Retrieval.</i> McGraw-Hill. Cosine similarity for comparing texts.</li>
    <li>Reimers, N., &amp; Gurevych, I. (2019). <i>Sentence-BERT: Sentence Embeddings using Siamese BERT-Networks.</i> EMNLP-IJCNLP 2019. Turning sentences into meaning vectors.</li>
    <li>Nussbaum, Z., Morris, J. X., Duderstadt, B., &amp; Mulyar, A. (2024). <i>Nomic Embed: Training a Reproducible Long Context Text Embedder.</i> arXiv:2402.01613. The model step 1 uses.</li>
    <li>Zheng, L., et al. (2023). <i>Judging LLM-as-a-Judge with MT-Bench and Chatbot Arena.</i> NeurIPS 2023. Using a language model to judge and score.</li>
  </ol>
</section>

<div class="vs">
  <div class="col mine">
    <div class="who">Your pre-proposal</div>
    <h3>{!! $mark($mineTitle) !!}</h3>
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
    @if ($recommended)
      <div class="who">@if ($section)<span class="ty-tag">{{ $section }}</span> @endif {{ $pending ? 'Pending, not yet decided' : 'Recommended this year' }}</div>
      <h3>{!! $mark($match['title'] ?? '') !!}</h3>
      <div class="sub">{{ $pending ? 'Pending' : 'Recommended' }} pre-proposal{{ $theirYear ? ' · '.$theirYear : '' }}</div>
      @forelse ($theirFields as $label => $value)
        <div class="field">
          <div class="k">{{ $label }}</div>
          <div class="v">{!! $mark($value) !!}</div>
        </div>
      @empty
        <div class="dup-none">Only the title was kept with this check. Run a new check to see their problem, solution and scope.</div>
      @endforelse
    @else
      <div class="who">Archived capstone &middot; {{ $year }}</div>
      <h3>{!! $mark($match['title'] ?? '') !!}</h3>
      <div class="sub">{{ $program ?: 'Program not recorded' }}</div>
      <div class="field">
        <div class="k">Written by</div>
        <div class="v">{{ $match['authors'] ?: 'Authors not recorded yet' }}</div>
      </div>
      <div class="field">
        <div class="k">Abstract</div>
        <div class="v">{!! filled($match['abstract'] ?? null) ? $mark($match['abstract']) : 'No abstract was recorded for this capstone.' !!}</div>
      </div>
    @endif
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
