<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CapsTRACK - My Teams</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/expert.css') }}" />
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/team-management.css') }}"/>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
  @include('partials.render-whole')
</head>
<body>
@include('instructor.partials.sidebar', ['currentPage' => 'teams', 'totalTeams' => $totalTeams])
@include('partials.sidebar-toggle')

{{-- My Teams: the expert's one hub. Invitations (Team Assignments), papers
     sent to them (Document Review), titles to approve and score sheets due
     are all in To do; every team they sit on is in Teams, each opening its
     own page with the full progress. --}}
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">My Teams</div>
    <div class="topbar-actions">
      @include('instructor.partials.notification-dropdown')
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
    <div class="content">
      @if (session('status'))
        <div class="card" style="padding:12px 16px;margin-bottom:14px;border-left:3px solid var(--green);font-size:12.5px;color:var(--text2)">{!! session('status') !!}</div>
      @endif

      @include('capsa.team-management._hub')
    </div>
  </div>
</div>
<div id="tm-toast" class="toast"></div>
@include('instructor.partials.skeleton-toggle', ['revealDisplay' => 'flex'])
@include('capsa.team-management._hub-script')
@include('partials.escape-closes-modals')
</body>
</html>
