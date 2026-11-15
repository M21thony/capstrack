{{--
  One match the check turned up, read beside the team's own words: the score
  on top, then the two documents side by side under a Comparison tab, and how
  the score was reached under a second tab.
--}}
@extends('student.duplication.layout')

@section('page-title', 'Match details')

@section('dup')
@php
  use App\Services\SimilarityService;
  use App\Support\SimilarityBand;
  use Illuminate\Support\Str;

  $mark = fn (?string $text) => SimilarityService::highlight($text, $words);

  $score = ($match['assessed'] ?? true) ? ($match['score'] ?? null) : null;
  $band = SimilarityBand::of($score);
  $reads = fn ($s) => $s === null ? 'not assessed' : $s.'%';

  $year = $match['academic_year'] ?? 'N/A';
  $program = $match['program'] ?? '';

  // Another team's title recommended this year, not an archive study.
  $recommended = $match['recommended'] ?? null;
  $section = $recommended && str_contains($recommended, ' — ') ? Str::after($recommended, ' — ') : null;
  // Turned in by another team and not decided yet.
  $pending = $recommended && str_starts_with($recommended, 'Pending');
  $kind = $pending ? 'Pending, not yet decided' : ($recommended ? 'Recommended this year' : 'Archived capstone');
  $meta = $recommended
      ? array_filter([$theirYear, $section, $kind])
      : array_filter([$year, $program, $kind]);

  // A match opened straight from the Pre-Proposal page has to go back there.
  // Sending them to the full report drops them on a page they never opened.
  // Previous and Next carry the marker so it survives paging between matches.
  $fromPreProposal = request('from') === 'pp';
  $backUrl = $fromPreProposal ? route('student.duplication') : route('student.duplication.report', $check->id);
  $backText = $fromPreProposal ? 'Back to your pre-proposal' : 'Back to report';
  $step = fn ($r) => route('student.duplication.similar', ['check' => $check->id, 'rank' => $r] + ($fromPreProposal ? ['from' => 'pp'] : []));

  // How the score above was reached, from the two numbers saved with the match
  // (SimilarityService::enrichWithReasons). Checks run before they were saved have neither.
  $emb = $match['embedding_score'] ?? null;
  // Saved since the meaning match became a scale from unrelated to the same study.
  // A check from before has the old (cosine + 1) / 2 percentage and no cosine.
  $cos = $match['cosine'] ?? null;
  $judge = $match['judge_score'] ?? null;
  // Checks run before the judge answered questions have a number and no answers.
  $answers = $match['answers'] ?? null;
  $exact = $match['exact_score'] ?? null;
  $exactUsed = $exact !== null && $exact >= SimilarityService::EXACT_WORDS_MIN && $exact === $score;
  // An exact copy is scored even when the judge's call failed.
  $read = ($match['assessed'] ?? true) && ! ($exactUsed && $judge === null);
  $same = $match['is_relevant'] ?? true;
  $contra = $read && ! $same && $judge !== null && $judge >= 55;
  $cut = $read && ($contra || ($judge === null && ! $same));
@endphp

<nav class="dup-crumb" aria-label="Breadcrumb">
  <a href="{{ route('student.duplication') }}">Pre-proposal</a>
  <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
  <a href="{{ route('student.duplication.report', $check->id) }}">Similarity report</a>
  <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
  <span class="here" aria-current="page">Match details</span>
</nav>

<div class="pager">
  <a class="pbtn" href="{{ $backUrl }}">
    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    {{ $backText }}
  </a>
  <div class="sm-pos">
    <span>{{ $groupLabel }} {{ $groupPos }} of {{ $groupTotal }}</span>
    <a class="pbtn sm-ic {{ $rank > 1 ? '' : 'off' }}" aria-label="Previous match{{ $rank > 1 ? ', '.$reads($prevScore) : '' }}"
       @if ($rank > 1) href="{{ $step($rank - 1) }}" title="Previous · {{ $reads($prevScore) }}" @endif>
      <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    </a>
    <a class="pbtn sm-ic {{ $rank < $total ? '' : 'off' }}" aria-label="Next match{{ $rank < $total ? ', '.$reads($nextScore) : '' }}"
       @if ($rank < $total) href="{{ $step($rank + 1) }}" title="Next · {{ $reads($nextScore) }}" @endif>
      <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
  </div>
</div>

<section class="card sm-head" aria-label="Match">
  <div class="sm-score">
    <div class="bigscore sim-{{ $band }}">@if ($score === null)—@else{{ $score }}<span style="font-size:20px">%</span>@endif</div>
    <small>similarity</small>
  </div>
  <div class="sm-doc">
    <h1 class="sm-title">{{ $match['title'] ?? '' }}</h1>
    <div class="sm-meta">{{ implode(' · ', $meta) }}</div>
    @if ($recommended)
      <div class="ty-note">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        @if ($pending)
          <span><b>Pending, not yet decided:</b> another team turned this in and its CaPSA/TSA has not decided yet. If it is recommended, two teams can't have the same title in one school year, so make yours different enough.</span>
        @else
          <span><b>Recommended this year:</b> two teams can't have the same title in one school year. Change your title or its focus before you turn it in.</span>
        @endif
      </div>
    @endif
  </div>
  <span class="band band-{{ $band }}">{{ SimilarityBand::label($score) }}</span>
</section>

<section class="card sm-card" aria-label="Comparison">
  <div class="pc-tabs" role="tablist">
    <button type="button" role="tab" id="sm-tab-cmp" aria-selected="true" aria-controls="sm-cmp" onclick="smTab('cmp')">Comparison</button>
    <button type="button" role="tab" id="sm-tab-how" aria-selected="false" aria-controls="sm-how" onclick="smTab('how')">Score explanation</button>
  </div>

  <div class="sm-panel" id="sm-cmp" role="tabpanel" aria-labelledby="sm-tab-cmp">
    @if (! empty($match['similarities']) || ! empty($match['differences']) || ! empty($match['suggestion']))
      <div class="sm-why">
        @if (! empty($match['similarities']))
          <div>
            <svg viewBox="0 0 24 24" style="stroke:#3f55b5"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
            <div><b>What is similar</b><p>{{ $match['similarities'] }}</p></div>
          </div>
        @endif
        @if (! empty($match['differences']))
          <div>
            <svg viewBox="0 0 24 24" style="stroke:#0f766e"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/><line x1="4" y1="4" x2="9" y2="9"/></svg>
            <div><b>What makes them different</b><p>{{ $match['differences'] }}</p></div>
          </div>
        @endif
        @if (! empty($match['suggestion']))
          <div>
            <svg viewBox="0 0 24 24" style="stroke:#b45309"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z"/></svg>
            <div><b>What to do</b><p>{{ $match['suggestion'] }}</p></div>
          </div>
        @endif
      </div>
    @endif

    <div class="sm-vs">
      <div class="sm-col">
        <div class="sm-colh">
          <div class="sm-who">Your pre-proposal</div>
          <h3>{!! $mark($mineTitle) !!}</h3>
        </div>
        <div class="sm-colb">
          @forelse ($mineFields as $label => $value)
            <div class="field">
              <div class="k">{{ $label }}</div>
              <div class="v">{!! $mark($value) !!}</div>
            </div>
          @empty
            <div class="dup-none">Nothing but the title was saved with this check.</div>
          @endforelse
        </div>
      </div>

      <div class="sm-col">
        <div class="sm-colh">
          @if ($recommended)
            <div class="sm-who">@if ($section)<span class="ty-tag">{{ $section }}</span> @endif{{ $pending ? 'Pending pre-proposal' : 'Recommended pre-proposal' }}{{ $theirYear ? ' · '.$theirYear : '' }}</div>
          @else
            <div class="sm-who">Archived capstone · {{ $year }}{{ $program ? ' · '.$program : '' }}</div>
          @endif
          <h3>{!! $mark($match['title'] ?? '') !!}</h3>
          @unless ($recommended)
            <div class="sm-by">Authors: {{ $match['authors'] ?: 'not recorded yet' }}</div>
          @endunless
        </div>
        <div class="sm-colb">
          @if ($recommended)
            @forelse ($theirFields as $label => $value)
              <div class="field">
                <div class="k">{{ $label }}</div>
                <div class="v">{!! $mark($value) !!}</div>
              </div>
            @empty
              <div class="dup-none">Only the title was kept with this check. Run a new check to see their problem, solution and scope.</div>
            @endforelse
          @else
            <div class="field">
              <div class="k">Abstract</div>
              <div class="v">{!! filled($match['abstract'] ?? null) ? $mark($match['abstract']) : 'No abstract was recorded for this capstone.' !!}</div>
            </div>
          @endif
          @if (! empty($match['reason']))
            <div class="field">
              <div class="k">Comparison note</div>
              <div class="v">{{ $match['reason'] }}</div>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="sm-panel" id="sm-how" role="tabpanel" aria-labelledby="sm-tab-how" hidden>
    <div class="sm-how how">
      <ol class="steps">
        <li>
          <span class="no">1</span>
          <div>
            <b>Meaning match</b>
            <p>Both write-ups are turned into meaning vectors by <code>{{ config('services.ollama.embed_model') }}</code> and compared with cosine similarity.
              @if ($cos !== null && $emb !== null)
                Studies with nothing in common measure about {{ number_format(SimilarityService::COSINE_UNRELATED, 2) }} and the same study about {{ number_format(SimilarityService::COSINE_SAME, 2) }}, so this one's {{ number_format($cos, 2) }} is placed between the two:
                <code>({{ number_format($cos, 2) }} − {{ number_format(SimilarityService::COSINE_UNRELATED, 2) }}) ÷ {{ number_format(SimilarityService::COSINE_SAME - SimilarityService::COSINE_UNRELATED, 2) }} × 100 = {{ $emb }}%</code>.
                Below {{ number_format(SimilarityService::COSINE_UNRELATED, 2) }} is dropped as noise.
              @elseif ($emb !== null)
                <code>({{ number_format($emb / 50 - 1, 2) }} + 1) ÷ 2 × 100 = {{ $emb }}%</code>.
                Under 40% is dropped as noise.
              @else
                This check was run before this number was saved.
              @endif
            </p>
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
                @if ($answers && $judge !== null)
                  It answered three questions: same thing handled, <b>{{ $answers['things'] }}</b>; same users, <b>{{ $answers['users'] }}</b>; same features, <b>{{ $answers['features'] }}</b>.
                  Those answers are worth {{ $judge }}%, so it is <b>{{ $same ? 'the same kind of study' : 'not the same study' }}</b>.
                @else
                  It said: <b>{{ $same ? 'same kind of study' : 'not the same study' }}</b>{{ $judge !== null ? ', '.$judge.'%' : ', with no number' }}.
                @endif
              @endif
            </p>
          </div>
          <span class="val">{{ $read && $judge !== null ? $judge.'%' : '—' }}</span>
        </li>
        <li>
          <span class="no">3</span>
          <div>
            <b>Exact words</b>
            <p>
              @if ($exact === null)
                Not compared. This check was run before it was added, or {{ $recommended ? 'their PDF' : 'the abstract' }} is too short.
              @else
                Your PDF and {{ $recommended ? 'their PDF' : 'the abstract' }} are compared three words at a time.
              @endif
              Under {{ SimilarityService::EXACT_WORDS_MIN }}% can be the form's own headings, so it is not used.
            </p>
          </div>
          <span class="val">{{ $exact !== null ? $exact.'%' : '—' }}</span>
        </li>
        <li>
          <span class="no">4</span>
          <div>
            <b>Score shown</b>
            <p>
              @if ($exactUsed)
                The exact words are used, since they are the highest number.
              @elseif (! $read)
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
    </div>
  </div>
</section>

<script nonce="{{ Vite::cspNonce() }}">
function smTab(which) {
  ['cmp', 'how'].forEach(function (k) {
    document.getElementById('sm-tab-' + k).setAttribute('aria-selected', k === which);
    document.getElementById('sm-' + k).hidden = k !== which;
  });
}
</script>
@endsection
