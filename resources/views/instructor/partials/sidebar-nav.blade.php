<nav class="nav">
  <div class="nav-section">Overview</div>
  <a href="{{ route('instructor.dashboard') }}" class="nav-item {{ (isset($currentPage) && $currentPage === 'dashboard') ? 'active' : '' }}">
    <x-portal-icon name="dashboard" />
    Dashboard
  </a>
  <div class="nav-section">Teams</div>
  <a href="{{ route('instructor.teams') }}" class="nav-item {{ (isset($currentPage) && $currentPage === 'teams') ? 'active' : '' }}">
    <x-portal-icon name="team" />
    Team Overview
    @if (($totalTeams ?? 0) > 0)
      <span class="nav-badge">{{ $totalTeams > 99 ? '99+' : $totalTeams }}</span>
    @endif
  </a>
  <a href="{{ route('instructor.grading') }}" class="nav-item {{ (isset($currentPage) && $currentPage === 'grading') ? 'active' : '' }}">
    <x-portal-icon name="grading" />
    Team Grading
    @if (($totalTeams ?? 0) > 0)
      <span class="nav-badge">{{ $totalTeams > 99 ? '99+' : $totalTeams }}</span>
    @endif
  </a>
  <a href="{{ route('instructor.assignments') }}" class="nav-item {{ (isset($currentPage) && $currentPage === 'assignments') ? 'active' : '' }}">
    <x-portal-icon name="inbox" />
    Team Assignments
    @if (isset($unseenAssignmentCount) && $unseenAssignmentCount > 0)
      <span class="nav-badge red">{{ $unseenAssignmentCount > 99 ? '99+' : $unseenAssignmentCount }}</span>
    @endif
  </a>
  <div class="nav-section">Schedule</div>
  <a href="{{ route('instructor.schedule') }}" class="nav-item {{ (isset($currentPage) && $currentPage === 'schedule') ? 'active' : '' }}">
    <x-portal-icon name="consultation" />
    Schedule Management
    @if (isset($unseenScheduleCount) && $unseenScheduleCount > 0)
      <span class="nav-badge red">{{ $unseenScheduleCount > 99 ? '99+' : $unseenScheduleCount }}</span>
    @endif
  </a>
  <div class="nav-section">Documents</div>
  <a href="{{ route('instructor.docreview') }}" class="nav-item {{ (isset($currentPage) && $currentPage === 'docreview') ? 'active' : '' }}">
    <x-portal-icon name="file-review" />
    Document Review
    @if (isset($unseenDocReviewCount) && $unseenDocReviewCount > 0)
      <span class="nav-badge red">{{ $unseenDocReviewCount > 99 ? '99+' : $unseenDocReviewCount }}</span>
    @endif
  </a>
  <div class="nav-section">Help</div>
  <button type="button" class="nav-item" onclick="openPortalTour()" style="cursor:pointer;width:100%;background:none;border:0;border-left:2px solid transparent;font-family:inherit;text-align:left">
    <x-portal-icon name="tour" />
    Portal Tour
  </button>
  <div class="nav-section">Account</div>
  <form id="expert-logout-form" action="{{ route('expert.logout') }}" method="POST" style="display: none;">
    @csrf
  </form>
  <a href="#" onclick="event.preventDefault(); document.getElementById('expert-logout-form').submit();" class="nav-item">
    <x-portal-icon name="sign-out" />
    Sign Out
  </a>
</nav>
