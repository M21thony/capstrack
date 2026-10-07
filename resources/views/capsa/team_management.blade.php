<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Team Management - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/team-management.css') }}"/>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
  @include('partials.render-whole')
</head>
<body>

@include('capsa._sidebar')
@include('partials.sidebar-toggle')
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
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
  <div class="content">

    @if (session('status'))
      <div class="card" style="padding:12px 16px;margin-bottom:14px;border-left:3px solid var(--green);font-size:12.5px;color:var(--text2)">{!! session('status') !!}</div>
    @endif
    @if (session('error'))
      <div class="card" style="padding:12px 16px;margin-bottom:14px;border-left:3px solid var(--red);font-size:12.5px;color:var(--text2)">{!! session('error') !!}</div>
    @endif

    @if (! in_array($tab, \App\Http\Controllers\Capsa\TeamManagementController::HUB_TABS, true))

      {{-- The pages that are still lists of their own - the Milestones
           tracker (where signed papers are checked) and Leave Requests - keep
           the same trail as every other level, so they are named and have a
           way back. --}}
      @include('capsa.partials.team-management._crumb', ['trail' => [
        ['Team Management', route('capsa.team_management')],
        [$tab === 'milestones' ? 'Milestone tracker' : 'Leave Requests', null],
      ]])
      @include('capsa.partials.team-management.' . $tab, $tabData)

    @elseif ($allSections === [] && $rows->isEmpty())

      <div class="card"><div class="tm-empty">
        You have no sections assigned yet, so there are no teams to manage.<br>
        An Admin assigns sections when they make someone a CaPSA.
      </div></div>

    @else

      @include('capsa.team-management._hub')

    @endif

  </div>
  </div>
</div>
@include('capsa.partials.skeleton-toggle')
@include('partials.app-dialog')
<div id="tm-toast" class="toast"></div>

@if (in_array($tab, ['todo', 'teams'], true) && ($allSections !== [] || $rows->isNotEmpty()))
  @include('capsa.team-management._hub-script')
@endif

@include('partials.escape-closes-modals')
</body>
</html>
