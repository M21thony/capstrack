<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>{{ $x->name }} - Expert Evaluations - CapsTRACK</title>
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
  $fmt = fn ($n) => \App\Support\EvaluationResults::format((float) $n);
  $roles = collect($x->roles)->map(fn ($r) => \App\Models\ExpertEvaluation::ROLES[$r] ?? ucfirst($r))->implode(' · ');
  $scale = \App\Models\EvaluationTemplate::SCALE;
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
      <nav class="ev-crumb" aria-label="Breadcrumb">
        <a href="{{ route('capsa.evaluations', array_filter(['tab' => 'results', 'stage' => $resultStage])) }}">Results</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page">{{ $x->name }}</span>
      </nav>

      <div class="ev-lk-head">
        <div>
          <h1>{{ $x->name }}</h1>
          <p>{{ $roles }} · {{ $x->total }} {{ $x->total === 1 ? 'review' : 'reviews' }} from {{ $teamsAnswered }} of {{ $teamsTotal }} {{ $teamsTotal === 1 ? 'team' : 'teams' }} · {{ $resultStage ?? 'All defenses' }}</p>
        </div>
        <dl class="ev-lk-nums">
          <div><dt>Overall</dt><dd>{{ $fmt($x->average) }}<small> / 5</small></dd></div>
          <div><dt>Rated 4 or 5</dt><dd>{{ $high }}%</dd></div>
          <div><dt>Rated 1 or 2</dt><dd>{{ $low }}%</dd></div>
        </dl>
      </div>

      <section class="ev-sum-card ev-lk-card" aria-labelledby="ev-lk-h">
        <div class="ev-lk-top">
          <h2 id="ev-lk-h">How teams answered each question</h2>
          <div class="ev-lk-legend">
            @foreach($scale as $s => $label)
              <span><i class="ev-lk-sw ev-lk-s{{ $s }}"></i>{{ $s }} {{ $label }}</span>
            @endforeach
          </div>
        </div>
        <div class="ev-lk-grid">
          <span class="ev-lk-axis-q"></span>
          <div class="ev-lk-axis"><span>◂ Lower</span><span>Higher ▸</span></div>
          <span class="ev-lk-axis-avg"></span>
          @foreach($spread as $q)
            @php
              $c = $q->counts;
              $w = fn ($n) => $q->total ? round($n / $q->total * 100, 2) : 0;
              $label = collect($c)->map(fn ($n, $s) => $n.' '.$scale[$s])->implode(', ');
            @endphp
            <div class="ev-lk-q">{{ $q->question }}</div>
            <div class="ev-lk-bar" role="img" aria-label="{{ $label }}">
              <div class="ev-lk-half is-left">
                @if($c[1])<span class="ev-lk-seg ev-lk-s1" style="width:{{ $w($c[1]) }}%">{{ $c[1] }}</span>@endif
                @if($c[2])<span class="ev-lk-seg ev-lk-s2" style="width:{{ $w($c[2]) }}%">{{ $c[2] }}</span>@endif
                @if($c[3])<span class="ev-lk-seg ev-lk-s3" style="width:{{ $w($c[3] / 2) }}%">{{ (int) ceil($c[3] / 2) }}</span>@endif
              </div>
              <div class="ev-lk-mid"></div>
              <div class="ev-lk-half is-right">
                @if($c[3])<span class="ev-lk-seg ev-lk-s3" style="width:{{ $w($c[3] / 2) }}%">{{ (int) floor($c[3] / 2) ?: '' }}</span>@endif
                @if($c[4])<span class="ev-lk-seg ev-lk-s4" style="width:{{ $w($c[4]) }}%">{{ $c[4] }}</span>@endif
                @if($c[5])<span class="ev-lk-seg ev-lk-s5" style="width:{{ $w($c[5]) }}%">{{ $c[5] }}</span>@endif
              </div>
            </div>
            <div class="ev-lk-avg">{{ $fmt($q->average) }}</div>
          @endforeach
        </div>
        <p class="ev-sum-foot">The numbers inside the bars are how many teams picked that answer. "Good" sits on the middle line, half on each side.</p>
      </section>

      @if($byRole->count() > 1)
      <section class="ev-sum-card ev-lk-card" aria-labelledby="ev-lk-r">
        <h2 id="ev-lk-r">By role</h2>
        <div class="ev-lk-roles">
          @foreach($byRole as $r)
            <div class="ev-lk-role"><span><b>As {{ $r->label }}</b> · {{ $r->total }} {{ $r->total === 1 ? 'review' : 'reviews' }}</span><strong>{{ $fmt($r->average) }}</strong></div>
          @endforeach
        </div>
      </section>
      @endif

      <section class="ev-sum-card ev-lk-card" aria-labelledby="ev-lk-c">
        <h2 id="ev-lk-c">What teams said</h2>
        @include('partials.evaluation-responses', ['responses' => $x->responses, 'moderate' => true])
      </section>
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
@include('partials.app-scrollbar')
</body>
</html>
