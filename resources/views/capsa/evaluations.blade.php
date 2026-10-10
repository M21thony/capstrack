<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Expert Evaluations - CapsTRACK</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
@include('partials.evaluation-styles')
  @include('partials.render-whole')
</head>
<body>

@include('capsa._sidebar')
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
@php
  $dueCount = $upcoming->count();
@endphp
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Expert Evaluations</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <main class="content">
    <div class="ev-page">
      <div class="ev-head">
        <div>
          <h1>Expert Evaluations</h1>
          <p>After a team passes a defense, its leader answers your questions about the team's experts, <b>1 to 5 stars</b> per question. The expert's rating is the average. Answers are <b>anonymous</b>: you and the expert see what was said, never who said it.</p>
        </div>
        <a class="ev-btn gold" href="{{ route('capsa.evaluations.create') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Create new evaluation</a>
      </div>

      @if(session('evaluation_status'))
        <div class="ev-flash" role="status" data-flash>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
          <span>{{ session('evaluation_status') }}</span>
          <button type="button" class="ev-flash-x" data-flash-close aria-label="Dismiss"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>
      @endif
      @if($errors->any())
        <div class="ev-flash is-bad" role="alert" data-flash>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
          <span>{{ $errors->first() }}</span>
          <button type="button" class="ev-flash-x" data-flash-close aria-label="Dismiss"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>
      @endif

      <nav class="ev-tabs" aria-label="Expert Evaluations">
        <a href="{{ route('capsa.evaluations') }}" @if($tab === 'questions') aria-current="page" @endif>Evaluations <span class="ev-n{{ $dueCount ? ' is-due' : '' }}" @if($dueCount) title="Defenses coming up without an evaluation" @endif>{{ $dueCount ?: $active->count() }}</span></a>
        <a href="{{ route('capsa.evaluations', ['tab' => 'results']) }}" @if($tab === 'results') aria-current="page" @endif>Results <span class="ev-n">{{ $totalAnswered }}</span></a>
      </nav>

      @if($tab === 'questions')
        {{-- A defense on the calendar with no evaluation: the CaPSA is asked
             for it here, the same as in the popup. --}}
        @foreach($upcoming as $due)
          <div class="ev-req" role="alert">
            <span class="ev-req-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg></span>
            <div class="ev-req-text">
              <b>{{ $due->stage }} needs an evaluation</b>
              <span>{{ $due->teams }} {{ $due->teams === 1 ? 'team defends' : 'teams defend' }} from {{ $due->first?->format('M j, Y') }}. Set it up before the results are in, or their leaders will not be asked about their experts.</span>
            </div>
            <div class="ev-req-acts">
              <form method="POST" action="{{ route('capsa.evaluations.store') }}">
                @csrf
                <input type="hidden" name="standard" value="1">
                <input type="hidden" name="defense_type" value="{{ $due->stage }}">
                <button type="submit" class="ev-btn gold sm">Use the 5 standard questions</button>
              </form>
              <a class="ev-btn sm" href="{{ route('capsa.evaluations.create', ['stage' => $due->stage]) }}">Write my own</a>
            </div>
          </div>
        @endforeach

        @if($active->isEmpty())
          <section class="ev-start" aria-labelledby="ev-start-h">
            <span class="ev-start-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
            <h2 id="ev-start-h">No evaluations yet</h2>
            <p>Create one for a defense. Team leaders answer it after their team passes, and their answers become each expert's rating.</p>
          </section>
        @else
          <div class="ev-tpls">
            @foreach($active as $t)
              @php $avg = $t->evaluations_avg_rating; @endphp
              <article class="ev-card2" aria-labelledby="ev-t-{{ $t->id }}">
                <header class="ev-card2-head">
                  <span class="ev-stage-tag">{{ $t->defense_type }}</span>
                  <span class="ev-live"><i aria-hidden="true"></i>Active</span>
                </header>
                <h3 id="ev-t-{{ $t->id }}">{{ $t->title }}</h3>
                <div class="ev-x-roles">@foreach($t->roles ?? [] as $r)<span class="ev-role r-{{ $r }}">{{ \App\Models\ExpertEvaluation::ROLES[$r] ?? ucfirst($r) }}</span>@endforeach</div>
                <dl class="ev-card2-stats">
                  <div><dt>Questions</dt><dd>{{ $t->questions->count() }}</dd></div>
                  <div><dt>Answered</dt><dd>{{ number_format($t->evaluations_count) }}</dd></div>
                  <div><dt>Average</dt><dd>@if($avg !== null){{ \App\Support\EvaluationResults::format((float) $avg) }}<span class="ev-mini-star" aria-hidden="true">★</span>@else<span class="ev-dim">–</span>@endif</dd></div>
                </dl>
                <details class="ev-peek">
                  <summary>See the {{ $t->questions->count() }} {{ $t->questions->count() === 1 ? 'question' : 'questions' }}</summary>
                  <ol>@foreach($t->questions as $q)<li>{{ $q->question }}</li>@endforeach</ol>
                </details>
                <footer class="ev-card2-foot">
                  <a class="ev-btn sm" href="{{ route('capsa.evaluations.edit', $t) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>Edit</a>
                  <form method="POST" action="{{ route('capsa.evaluations.turn_off', $t) }}"
                        data-confirm-title="Turn off this evaluation?"
                        data-confirm="Leaders who still owe “{{ $t->title }}” will not be asked any more. What was already answered stays in the results."
                        data-confirm-ok="Turn off" data-confirm-tone="warn">
                    @csrf @method('PATCH')
                    <button type="submit" class="ev-btn sm ghost-danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18.4 6.6a9 9 0 1 1-12.8 0"/><path d="M12 2v10"/></svg>Turn off</button>
                  </form>
                </footer>
              </article>
            @endforeach
          </div>
        @endif

        @if($turnedOff->isNotEmpty())
          <section class="ev-offs" aria-labelledby="ev-off-h">
            <div class="ev-offs-head">
              <h2 id="ev-off-h">Turned off <span class="ev-n">{{ $turnedOff->count() }}</span></h2>
              <span>Not asked of anyone. What was answered stays in the results.</span>
            </div>
            <ul>
              @foreach($turnedOff as $t)
                <li class="ev-off-row">
                  <div class="ev-off-main">
                    <b>{{ $t->title }}</b>
                    <span><span class="ev-stage-tag is-muted">{{ $t->defense_type }}</span>{{ $t->questions->count() }} {{ $t->questions->count() === 1 ? 'question' : 'questions' }} · {{ $t->evaluations_count }} answered</span>
                  </div>
                  <div class="ev-off-acts">
                    <a class="ev-btn sm" href="{{ route('capsa.evaluations.create', ['from' => $t->id]) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>Use again</a>
                    <form method="POST" action="{{ route('capsa.evaluations.destroy', $t) }}"
                          data-confirm-title="Delete “{{ $t->title }}”?"
                          data-confirm="This cannot be undone.{{ $t->evaluations_count ? ' Its '.$t->evaluations_count.' '.($t->evaluations_count === 1 ? 'answer stays' : 'answers stay').' in the results, so no expert’s rating changes.' : '' }}"
                          data-confirm-ok="Delete" data-confirm-tone="danger">
                      @csrf @method('DELETE')
                      <button type="submit" class="ev-btn sm danger-solid"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>Delete</button>
                    </form>
                  </div>
                </li>
              @endforeach
            </ul>
          </section>
        @endif
      @else
        @if(count($resultStages) > 1)
        <nav class="ev-filter" aria-label="Defense">
          <a href="{{ route('capsa.evaluations', ['tab' => 'results']) }}" @if(! $resultStage) aria-current="page" @endif>All defenses</a>
          @foreach($resultStages as $stage)
            <a href="{{ route('capsa.evaluations', ['tab' => 'results', 'stage' => $stage]) }}" @if($resultStage === $stage) aria-current="page" @endif>{{ $stage }}</a>
          @endforeach
        </nav>
        @endif

        <div class="ev-stats">
          <div class="ev-stat"><span>Average rating</span><b>@if($overall !== null){{ \App\Support\EvaluationResults::format($overall) }}<small>/ 5</small>@else – @endif</b></div>
          <div class="ev-stat"><span>Evaluations</span><b>{{ number_format($totalAnswered) }}</b></div>
          <div class="ev-stat"><span>Experts evaluated</span><b>{{ $results->count() }}</b></div>
        </div>

        <p class="ev-anon-note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>Every response is anonymous. No team or student is shown, here or to the expert.</p>

        @if($results->isEmpty())
          <div class="ev-none">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <b>No answers yet</b>
            Results appear here once team leaders evaluate their experts after a passed defense{{ $resultStage ? ' at '.$resultStage : '' }}.
          </div>
        @else
          <div class="ev-list">
            @foreach($results as $x)
              <details class="ev-x">
                <summary>
                  <span class="ev-av" aria-hidden="true">{{ $x->expert?->avatarInitials() }}@if($x->expert?->hasAvatarImage())<img src="{{ $x->expert->avatarUrl() }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">@endif</span>
                  <span class="ev-x-main">
                    <b>{{ $x->name }}</b>
                    <span class="ev-x-roles">@foreach($x->roles as $r)<span class="ev-role r-{{ $r }}">{{ \App\Models\ExpertEvaluation::ROLES[$r] ?? ucfirst($r) }}</span>@endforeach</span>
                  </span>
                  <span class="ev-score">@include('partials.evaluation-stars', ['rating' => $x->average])<b>{{ \App\Support\EvaluationResults::format($x->average) }}<small>/5</small></b>{{ $x->total }} {{ $x->total === 1 ? 'review' : 'reviews' }}</span>
                  <svg class="ev-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                <div class="ev-x-body">
                  <div>
                    <div class="ev-sub">By question</div>
                    @include('partials.evaluation-question-bars', ['questions' => $x->questions])
                  </div>
                  <div>
                    <div class="ev-sub">Responses</div>
                    @include('partials.evaluation-responses', ['responses' => $x->responses])
                  </div>
                </div>
              </details>
            @endforeach
          </div>
        @endif
      @endif
    </div>
  </main>
</div>

<script nonce="{{ Vite::cspNonce() }}">
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() { if (sidebarEl) { sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar(); } }
function openSidebar() { if (!sidebarEl || !sidebarOverlay) return; sidebarEl.classList.add('mobile-open'); sidebarOverlay.classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeSidebar() { if (!sidebarEl || !sidebarOverlay) return; sidebarEl.classList.remove('mobile-open'); sidebarOverlay.classList.remove('open'); document.body.style.overflow = ''; }
</script>
@include('partials.evaluation-page-script')
@include('partials.confirm-dialog')
</body>
</html>
