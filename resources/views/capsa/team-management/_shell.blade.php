{{--
  The shell every level below the team list shares.

  Same chrome as the list it came from - Team Management for a CaPSA, My
  Teams for an expert - with the mockup's stylesheet scoped to .tmx over it,
  so these pages look like the agreed design without the mockup's class
  names colliding with the portal stylesheet's own .card, .btn, .pill and
  .stat.
--}}
@php $portal = $portal ?? 'capsa'; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>@yield('title') - CapsTRACK {{ $portal === 'capsa' ? 'Admin' : '' }}</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
@if ($portal === 'capsa')
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
@else
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/expert.css') }}"/>
@endif
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/team-management.css') }}"/>
@stack('head')
@include('partials.skeleton-head', ['skReveal' => 'flex'])
</head>
<body>

@if ($portal === 'capsa')
  @include('capsa._sidebar')
@else
  @include('instructor.partials.sidebar', ['currentPage' => 'teams', 'totalTeams' => $totalTeams ?? 0])
@endif
@include('partials.sidebar-toggle')
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    {{-- Where you are, as a path you can click back along - in the top bar,
         as the redesign has it, rather than a row of its own. --}}
    <nav class="page-title tm-topcrumb" aria-label="Breadcrumb">
      @foreach ($trail as [$label, $url])
        @if ($url && ! $loop->last)
          <a href="{{ $url }}">{{ $label }}</a><span class="sep" aria-hidden="true">›</span>
        @else
          <span aria-current="page">{{ $label }}</span>
        @endif
      @endforeach
    </nav>
    <div class="topbar-actions">
      @include($portal === 'capsa' ? 'capsa.partials.notification-dropdown' : 'instructor.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="card" style="padding:16px">
      <div class="sk" style="width:280px;height:34px;border-radius:8px;margin-bottom:16px"></div>
      @for ($i = 0; $i < 4; $i++)
        <div class="sk" style="height:44px;border-radius:8px;margin-bottom:10px"></div>
      @endfor
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
    <div class="content" style="padding:0">
      <div class="tmx">
        @yield('tm-content')
      </div>
    </div>
  </div>
</div>
@include($portal === 'capsa' ? 'capsa.partials.skeleton-toggle' : 'instructor.partials.skeleton-toggle', ['revealDisplay' => 'flex'])
<div id="tm-toast" class="toast"></div>
@stack('scripts')
@if ($portal !== 'capsa')
  @include('instructor.partials.expert-profile')
@endif
@include('partials.escape-closes-modals')
</body>
</html>
