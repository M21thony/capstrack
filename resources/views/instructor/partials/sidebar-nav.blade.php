<nav class="nav">
  <div class="nav-section">Overview</div>
  <a href="{{ route('instructor.dashboard') }}" class="nav-item {{ (isset($currentPage) && $currentPage === 'dashboard') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    Dashboard
  </a>
  <div class="nav-section">Teams</div>
  <a href="{{ route('instructor.teams') }}" class="nav-item {{ (isset($currentPage) && $currentPage === 'teams') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    Team Overview
    @if (($totalTeams ?? 0) > 0)
      <span class="nav-badge">{{ $totalTeams > 99 ? '99+' : $totalTeams }}</span>
    @endif
  </a>
  <a href="{{ route('instructor.grading') }}" class="nav-item {{ (isset($currentPage) && $currentPage === 'grading') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
    Team Grading
    @if (($totalTeams ?? 0) > 0)
      <span class="nav-badge">{{ $totalTeams > 99 ? '99+' : $totalTeams }}</span>
    @endif
  </a>
  <a href="{{ route('instructor.assignments') }}" class="nav-item {{ (isset($currentPage) && $currentPage === 'assignments') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/><line x1="4" y1="4" x2="9" y2="9"/></svg>
    Team Assignments
    @if (isset($unseenAssignmentCount) && $unseenAssignmentCount > 0)
      <span class="nav-badge red">{{ $unseenAssignmentCount > 99 ? '99+' : $unseenAssignmentCount }}</span>
    @endif
  </a>
  <div class="nav-section">Schedule</div>
  <a href="{{ route('instructor.schedule') }}" class="nav-item {{ (isset($currentPage) && $currentPage === 'schedule') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    Schedule Management
    @if (isset($unseenScheduleCount) && $unseenScheduleCount > 0)
      <span class="nav-badge red">{{ $unseenScheduleCount > 99 ? '99+' : $unseenScheduleCount }}</span>
    @endif
  </a>
  <a href="{{ route('instructor.consultations') }}" class="nav-item {{ (isset($currentPage) && $currentPage === 'consultations') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    Consultation Logs
  </a>
  <div class="nav-section">Documents</div>
  <a href="{{ route('instructor.docreview') }}" class="nav-item {{ (isset($currentPage) && $currentPage === 'docreview') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
    Document Review
    @if (isset($unseenDocReviewCount) && $unseenDocReviewCount > 0)
      <span class="nav-badge red">{{ $unseenDocReviewCount > 99 ? '99+' : $unseenDocReviewCount }}</span>
    @endif
  </a>
  <div class="nav-section">Progress</div>
  <a href="{{ route('instructor.progress') }}" class="nav-item {{ (isset($currentPage) && $currentPage === 'progress') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
    Progress Monitoring
  </a>
  <div class="nav-section">Account</div>
  <form id="expert-logout-form" action="{{ route('expert.logout') }}" method="POST" style="display: none;">
    @csrf
  </form>
  <a href="#" onclick="event.preventDefault(); document.getElementById('expert-logout-form').submit();" class="nav-item">
    <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    Sign Out
  </a>
</nav>
