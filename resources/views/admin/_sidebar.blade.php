@php
    $admin = Auth::guard('expert')->user();

    $adminEmail = $admin?->email;
    $adminName = $admin ? trim(($admin->first_name ?? '').' '.($admin->last_name ?? '')) : 'System Administrator';

    $currentRoute = Route::currentRouteName();

    $pendingExpertIds = App\Models\CapstoneExpert::where('approved_status', false)
        ->whereNull('rejection_reason')
        ->pluck('expert_id')
        ->toArray();
    $seenPendingExpertIds = session('admin_seen_pending_expert_ids', []);
    $unseenPendingExpertCount = count(array_diff($pendingExpertIds, $seenPendingExpertIds));

    if ($currentRoute === 'admin.approval') {
        session()->put('admin_seen_pending_expert_ids', array_unique(array_merge($seenPendingExpertIds, $pendingExpertIds)));
    }

    $approvedExpertIds = App\Models\CapstoneExpert::where('approved_status', true)
        ->pluck('expert_id')
        ->toArray();
    $seenExpertIds = session('admin_seen_expert_ids', []);
    $unseenExpertCount = count(array_diff($approvedExpertIds, $seenExpertIds));

    if ($currentRoute === 'admin.experts') {
        session()->put('admin_seen_expert_ids', array_unique(array_merge($seenExpertIds, $approvedExpertIds)));
    }

    $verifiedTeamIds = App\Models\Team::where('is_confirmed', true)
        ->where('team_status', '!=', 'closed')
        ->pluck('team_id')
        ->toArray();
    $seenVerifiedTeamIds = session('admin_seen_verified_team_ids', []);
    $unseenVerifiedTeamCount = count(array_diff($verifiedTeamIds, $seenVerifiedTeamIds));

    if ($currentRoute === 'admin.panel_assignment') {
        session()->put('admin_seen_verified_team_ids', array_unique(array_merge($seenVerifiedTeamIds, $verifiedTeamIds)));
    }

    $pendingVerifyTeamIds = App\Models\Team::where('team_status', 'complete')
        ->where('is_confirmed', false)
        ->pluck('team_id')
        ->toArray();
    $seenPendingVerifyTeamIds = session('admin_seen_pending_verify_team_ids', []);
    $unseenPendingVerifyTeamCount = count(array_diff($pendingVerifyTeamIds, $seenPendingVerifyTeamIds));

    if ($currentRoute === 'admin.verify_teams') {
        session()->put('admin_seen_pending_verify_team_ids', array_unique(array_merge($seenPendingVerifyTeamIds, $pendingVerifyTeamIds)));
    }

    $archivedCount = App\Models\ArchivedDocument::count();

    $pendingReceiptIds = App\Models\FinancialRecord::whereNull('verified_at')
        ->whereNotNull('receipt_image')
        ->pluck('id')
        ->toArray();
    $seenPendingReceiptIds = session('admin_seen_pending_receipt_ids', []);
    $unseenPendingReceiptCount = count(array_diff($pendingReceiptIds, $seenPendingReceiptIds));

    if ($currentRoute === 'admin.financial') {
        session()->put('admin_seen_pending_receipt_ids', array_unique(array_merge($seenPendingReceiptIds, $pendingReceiptIds)));
    }

    $activityIds = App\Models\ActivityLog::orderBy('id')->pluck('id')->toArray();
    $seenActivityIds = session('admin_seen_activity_ids', []);
    $unseenActivityCount = count(array_diff($activityIds, $seenActivityIds));

    if ($currentRoute === 'admin.activitylog') {
        session()->put('admin_seen_activity_ids', array_unique(array_merge($seenActivityIds, $activityIds)));
    }

    // Admins who are ALSO registered as an adviser/consultant/grammarian
    // (e.g. assigned to a team's panel) get the expert-facing nav items
    // merged into this same sidebar, per the consultant's guidance that a
    // CaPSA who is also an Adviser shouldn't have to juggle two separate
    // portals - the instructor.* routes already let any is_admin account
    // through regardless of approval status (see CheckExpertApproval).
    $adminHasExpertRole = $admin && $admin->roles->pluck('role_name')->intersect(['adviser', 'consultant', 'grammarian'])->isNotEmpty();

    if ($adminHasExpertRole) {
        $expertPendingAssignmentIds = App\Models\TeamExpertAssignment::where('expert_id', $admin->expert_id)->where('status', 'pending')->pluck('assignment_id')->toArray();
        $seenExpertAssignmentIds = session('instructor_seen_assignment_ids', []);
        $unseenExpertAssignmentCount = count(array_diff($expertPendingAssignmentIds, $seenExpertAssignmentIds));

        if ($currentRoute === 'instructor.assignments') {
            session()->put('instructor_seen_assignment_ids', array_unique(array_merge($seenExpertAssignmentIds, $expertPendingAssignmentIds)));
        }

        $expertPendingDocReviewIds = Schema::hasTable('capstone_document_review')
            ? DB::table('capstone_document_review')->where('expert_id', $admin->expert_id)->where('review_status', 'pending')->pluck('review_id')->toArray()
            : [];
        $seenExpertDocReviewIds = session('instructor_seen_doc_review_ids', []);
        $unseenExpertDocReviewCount = count(array_diff($expertPendingDocReviewIds, $seenExpertDocReviewIds));

        if ($currentRoute === 'instructor.docreview') {
            session()->put('instructor_seen_doc_review_ids', array_unique(array_merge($seenExpertDocReviewIds, $expertPendingDocReviewIds)));
        }
    }
@endphp




<div class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">
      <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
    </div>
    <div>
      <div class="brand-name">CapsTRACK</div>
      <div class="brand-sub">Admin Portal</div>
    </div>
  </div>
  <div class="sidebar-user">
    <div class="avatar" style="padding:0;overflow:hidden;">
      <img
        src="{{ $admin?->avatarUrl() ?? asset('images/default-avatar.jpg') }}"
        alt="Profile"
        style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;"
        loading="lazy"
        onerror="this.onerror=null; this.src='{{ asset('images/default-avatar.jpg') }}';"
      />
    </div>







    <div class="user-info">
      <div class="user-name">{{ $adminName }}</div>
      <div class="user-role" title="{{ $adminEmail }}">CCS · CSPC</div>
    </div>
    <div class="status-dot"></div>
  </div>
  <nav class="nav">
    <div class="nav-section">Overview</div>
    <a href="{{ route('admin.dashboard') }}" class="nav-item {{ $currentRoute === 'admin.dashboard' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <div class="nav-section">Expert Management</div>
    <a href="{{ route('admin.approval') }}" class="nav-item {{ $currentRoute === 'admin.approval' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
      Account Approval
      @if ($unseenPendingExpertCount > 0)
        <span class="nav-badge red">{{ $unseenPendingExpertCount > 99 ? '99+' : $unseenPendingExpertCount }}</span>
      @endif
    </a>
    <a href="{{ route('admin.experts') }}" class="nav-item {{ $currentRoute === 'admin.experts' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Expert Management
      @if ($unseenExpertCount > 0)
        <span class="nav-badge red">{{ $unseenExpertCount > 99 ? '99+' : $unseenExpertCount }}</span>
      @endif
    </a>
    <a href="{{ route('admin.panel_assignment') }}" class="nav-item {{ $currentRoute === 'admin.panel_assignment' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="23" y1="11" x2="17" y2="11"/><line x1="20" y1="8" x2="20" y2="14"/></svg>
      Panel Assignment
      @if ($unseenVerifiedTeamCount > 0)
        <span class="nav-badge">{{ $unseenVerifiedTeamCount > 99 ? '99+' : $unseenVerifiedTeamCount }}</span>
      @endif
    </a>
    <a href="{{ route('admin.verify_teams') }}" class="nav-item {{ $currentRoute === 'admin.verify_teams' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
      Team Verification
      @if ($unseenPendingVerifyTeamCount > 0)
        <span class="nav-badge red">{{ $unseenPendingVerifyTeamCount > 99 ? '99+' : $unseenPendingVerifyTeamCount }}</span>
      @endif
    </a>
    <a href="{{ route('admin.sections') }}" class="nav-item {{ $currentRoute === 'admin.sections' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Student Section List
    </a>
    <div class="nav-section">Capstone Archive</div>
    <a href="{{ route('admin.archive') }}" class="nav-item {{ $currentRoute === 'admin.archive' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
      Archived Capstones
      @if ($archivedCount > 0)
        <span class="nav-badge">{{ $archivedCount > 99 ? '99+' : $archivedCount }}</span>
      @endif
    </a>
    <div class="nav-section">Resources</div>
    <a href="{{ route('admin.resources') }}" class="nav-item {{ $currentRoute === 'admin.resources' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      Templates &amp; Lessons
    </a>
    <a href="{{ route('admin.rubric_management') }}" class="nav-item {{ $currentRoute === 'admin.rubric_management' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      Grading Rubrics
    </a>
    <div class="nav-section">Communications</div>
    <a href="{{ route('admin.announcements') }}" class="nav-item {{ $currentRoute === 'admin.announcements' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3zm-8.27 4a2 2 0 0 1-3.46 0"/></svg>
      Announcements
    </a>
    <div class="nav-section">Reports</div>
    <a href="{{ route('admin.analytics') }}" class="nav-item {{ $currentRoute === 'admin.analytics' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      Analytics
    </a>
    <a href="{{ route('admin.activitylog') }}" class="nav-item {{ $currentRoute === 'admin.activitylog' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      Activity Log
      @if ($unseenActivityCount > 0)
        <span class="nav-badge red">{{ $unseenActivityCount > 99 ? '99+' : $unseenActivityCount }}</span>
      @endif
    </a>
    @if ($adminHasExpertRole)
      <div class="nav-section">My Expert Duties</div>
      <a href="{{ route('instructor.teams') }}" class="nav-item {{ $currentRoute === 'instructor.teams' ? 'active' : '' }}">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        My Teams
      </a>
      <a href="{{ route('instructor.assignments') }}" class="nav-item {{ $currentRoute === 'instructor.assignments' ? 'active' : '' }}">
        <svg viewBox="0 0 24 24"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/><line x1="4" y1="4" x2="9" y2="9"/></svg>
        Team Assignments
        @if ($unseenExpertAssignmentCount > 0)
          <span class="nav-badge red">{{ $unseenExpertAssignmentCount > 99 ? '99+' : $unseenExpertAssignmentCount }}</span>
        @endif
      </a>
      <a href="{{ route('instructor.schedule') }}" class="nav-item {{ $currentRoute === 'instructor.schedule' ? 'active' : '' }}">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        My Schedule
      </a>
      <a href="{{ route('instructor.consultations') }}" class="nav-item {{ $currentRoute === 'instructor.consultations' ? 'active' : '' }}">
        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        Consultation Logs
      </a>
      <a href="{{ route('instructor.docreview') }}" class="nav-item {{ $currentRoute === 'instructor.docreview' ? 'active' : '' }}">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Document Review
        @if ($unseenExpertDocReviewCount > 0)
          <span class="nav-badge red">{{ $unseenExpertDocReviewCount > 99 ? '99+' : $unseenExpertDocReviewCount }}</span>
        @endif
      </a>
      <a href="{{ route('instructor.progress') }}" class="nav-item {{ $currentRoute === 'instructor.progress' ? 'active' : '' }}">
        <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        Progress Monitoring
      </a>
    @endif
    <div class="nav-section">Account</div>
    <a href="{{ route('admin.profile') }}" class="nav-item {{ $currentRoute === 'admin.profile' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Admin Profile
    </a>
    <a href="{{ route('admin.financial') }}" class="nav-item {{ $currentRoute === 'admin.financial' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
      Financial Records
      @if ($unseenPendingReceiptCount > 0)
        <span class="nav-badge red">{{ $unseenPendingReceiptCount > 99 ? '99+' : $unseenPendingReceiptCount }}</span>
      @endif
    </a>
    <form id="admin-logout-form" action="{{ route('expert.logout') }}" method="POST" style="display: none;">
      @csrf
    </form>
    <a href="#" onclick="event.preventDefault(); document.getElementById('admin-logout-form').submit();" class="nav-item">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Sign Out
    </a>
  </nav>
</div>
@include('partials.idle-timeout')
