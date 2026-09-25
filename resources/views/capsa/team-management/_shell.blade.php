{{--
  The shell every level below the team list shares.

  Same chrome as Team Management itself - sidebar, topbar, skeleton - with
  the mockup's stylesheet scoped to .tmx over it, so these pages look like
  the agreed design without the mockup's class names colliding with the
  admin stylesheet's own .card, .btn, .pill and .stat.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>@yield('title') - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/team-management.css') }}"/>
@stack('head')
@include('partials.skeleton-head', ['skReveal' => 'flex'])
</head>
<body>

@include('capsa._sidebar')
<div class="main">
  <div class="topbar">
    <div class="page-title">Team Management</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
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
        @include('capsa.partials.team-management._crumb', ['trail' => $trail])
        @yield('tm-content')
      </div>
    </div>
  </div>
</div>
@include('capsa.partials.skeleton-toggle')
<div id="tm-toast" class="toast"></div>
@stack('scripts')
@include('partials.escape-closes-modals')
</body>
</html>
