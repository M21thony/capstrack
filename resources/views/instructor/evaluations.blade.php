<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>My Evaluations - CapsTRACK</title>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/expert.css') }}" />
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('partials.evaluation-styles')
  @include('partials.render-whole')
</head>
<body>
@include('instructor.partials.sidebar', ['currentPage' => 'evaluations'])
<div class="sidebar-overlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">My Evaluations</div>
    <div class="topbar-actions">
      @include('instructor.partials.notification-dropdown')
    </div>
  </div>

  <main class="content">
    <div class="ev-page">
      <div class="ev-head">
        <div>
          <h1>My Evaluations</h1>
          <p>After each defense a team passes, its leader rates you on the CaPSA's questions, <b>1 to 5 stars</b> each. Your rating is the average. Every response is <b>anonymous</b>: no team or student is ever shown.</p>
        </div>
      </div>

      @if(count($stages) > 1)
      <nav class="ev-filter" aria-label="Defense">
        <a href="{{ route('instructor.evaluations') }}" @if(! $stage) aria-current="page" @endif>All defenses</a>
        @foreach($stages as $s)
          <a href="{{ route('instructor.evaluations', ['stage' => $s]) }}" @if($stage === $s) aria-current="page" @endif>{{ $s }}</a>
        @endforeach
      </nav>
      @endif

      @if($total === 0)
        <div class="ev-none">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          <b>No evaluations yet</b>
          When a team you advise passes {{ $stage ?? 'a defense' }}, its leader's evaluation of you appears here.
        </div>
      @else
        <section class="ev-overview" aria-label="Your rating">
          <div class="ev-big">
            <b>{{ \App\Support\EvaluationResults::format($average) }}<small>/5</small></b>
            @include('partials.evaluation-stars', ['rating' => $average, 'large' => true])
            <span>{{ number_format($total) }} {{ $total === 1 ? 'review' : 'reviews' }}{{ $stage ? ' after '.$stage : '' }}</span>
          </div>
          <div>
            <ul class="ev-dist" aria-label="How the ratings split">
              @foreach($distribution as $stars => $n)
                <li><span>{{ $stars }} ★</span><span class="ev-bar"><i style="width: {{ $total ? round($n / $total * 100) : 0 }}%"></i></span><span>{{ $n }}</span></li>
              @endforeach
            </ul>
          </div>
        </section>

        @if(! $stage && $byStage->count() > 1)
          <div class="ev-bystage">
            @foreach($byStage as $s => $row)
              <div><span>{{ $s }}</span><b>@if($row['average'] !== null){{ \App\Support\EvaluationResults::format($row['average']) }}<small> /5 · {{ $row['total'] }}</small>@else – @endif</b></div>
            @endforeach
          </div>
        @endif

        <div class="ev-two">
          <section class="ev-card" aria-labelledby="ev-q-h">
            <div class="ev-sub" id="ev-q-h">By question</div>
            @include('partials.evaluation-question-bars', ['questions' => $questions])
          </section>
          <section class="ev-card" aria-labelledby="ev-r-h">
            <div class="ev-sub" id="ev-r-h">Responses</div>
            @include('partials.evaluation-responses', ['responses' => $responses])
          </section>
        </div>
      @endif
    </div>
  </main>
</div>

<script nonce="{{ Vite::cspNonce() }}">
function toggleSidebar() { document.body.classList.toggle('sidebar-open'); }
function closeSidebar() { document.body.classList.remove('sidebar-open'); }
</script>
@include('partials.escape-closes-modals')
</body>
</html>
