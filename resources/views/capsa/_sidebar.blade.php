@php
    $admin = Auth::guard('expert')->user();

    // These pages exist under both prefixes, so each role reads its own in
    // the address bar instead of the other's. Admin wins when somebody is
    // both - the /admin copy is guarded by the stricter of the two.
    $rolePrefix = $admin?->is_admin ? 'admin.' : 'capsa.';

    $adminEmail = $admin?->email;
    $adminName = $admin ? trim(($admin->first_name ?? '').' '.($admin->last_name ?? '')) : 'System Administrator';

    // Same reasoning as the "Duties" group label below: a CaPSA-only
    // account has no admin capabilities, so the portal's own brand label
    // shouldn't tell them they're in the "Admin Portal".
    $portalLabel = match (true) {
        $admin?->is_admin && $admin?->is_capsa => 'Admin & CaPSA Portal',
        $admin?->is_capsa => 'CaPSA Portal',
        default => 'Admin Portal',
    };

    $currentRoute = Route::currentRouteName();

    // Counted from whatever the badge's own page actually lists. Admin's
    // Expert Management is CaPSAs and nothing else, so counting every
    // approved expert put a 10 beside a page showing three.
    $approvedExpertIds = App\Models\CapstoneExpert::where('approved_status', true)
        ->when($rolePrefix === 'admin.', fn ($q) => $q->whereHas(
            'roles',
            fn ($r) => $r->where('role_name', App\Models\CapstoneExpert::CAPSA_ROLE)
        ))
        ->pluck('expert_id')
        ->toArray();
    $seenExpertIds = session('admin_seen_expert_ids', []);
    $unseenExpertCount = count(array_diff($approvedExpertIds, $seenExpertIds));

    if ($currentRoute === $rolePrefix.'experts') {
        session()->put('admin_seen_expert_ids', array_unique(array_merge($seenExpertIds, $approvedExpertIds)));
    }

    $verifiedTeamIds = App\Models\Team::where('is_confirmed', true)
        ->where('team_status', '!=', 'closed')
        ->pluck('team_id')
        ->toArray();
    $seenVerifiedTeamIds = session('admin_seen_verified_team_ids', []);
    $unseenVerifiedTeamCount = count(array_diff($verifiedTeamIds, $seenVerifiedTeamIds));

    if ($currentRoute === 'capsa.panel_assignment') {
        session()->put('admin_seen_verified_team_ids', array_unique(array_merge($seenVerifiedTeamIds, $verifiedTeamIds)));
    }

    $pendingVerifyTeamIds = App\Models\Team::where('team_status', 'complete')
        ->where('is_confirmed', false)
        ->pluck('team_id')
        ->toArray();
    $seenPendingVerifyTeamIds = session('admin_seen_pending_verify_team_ids', []);
    $unseenPendingVerifyTeamCount = count(array_diff($pendingVerifyTeamIds, $seenPendingVerifyTeamIds));

    if ($currentRoute === 'capsa.team_management') {
        session()->put('admin_seen_pending_verify_team_ids', array_unique(array_merge($seenPendingVerifyTeamIds, $pendingVerifyTeamIds)));
    }

    $archivedCount = App\Models\ArchivedDocument::count();

    $pendingReceiptIds = App\Models\FinancialRecord::whereNull('verified_at')
        ->whereNotNull('receipt_image')
        ->pluck('id')
        ->toArray();
    $seenPendingReceiptIds = session('admin_seen_pending_receipt_ids', []);
    $unseenPendingReceiptCount = count(array_diff($pendingReceiptIds, $seenPendingReceiptIds));

    if ($currentRoute === 'capsa.financial') {
        session()->put('admin_seen_pending_receipt_ids', array_unique(array_merge($seenPendingReceiptIds, $pendingReceiptIds)));
    }

    $activityIds = App\Models\ActivityLog::orderBy('id')->pluck('id')->toArray();
    $seenActivityIds = session('admin_seen_activity_ids', []);
    $unseenActivityCount = count(array_diff($activityIds, $seenActivityIds));

    if ($currentRoute === $rolePrefix.'activitylog') {
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
      <div class="brand-sub">{{ $portalLabel }}</div>
    </div>
  </div>
  {{-- Clicking this card opens the same personal-info side panel the
       instructor sidebar has (instructor/partials/expert-profile.blade.php)
       - only when this account actually holds an expert role, since that
       panel's fields (Team Capacity, Areas of Expertise) don't apply to a
       plain Admin/CaPSA account with no expert duties. --}}
  <div class="sidebar-user" @if ($adminHasExpertRole) id="ep-trigger" onclick="openExpertProfile()" style="cursor:pointer" @endif>
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
    @php
      // Sorted by what the page is about, not by which hat the account wears.
      // An account can be Admin, CaPSA and an adviser at once, and grouping by
      // role put team pages at the top, the middle and the bottom. Each item
      // still only shows for the role it belongs to, and a heading only shows
      // when something under it does.
      $isCapsa = (bool) $admin?->is_capsa;
      $isAdmin = (bool) $admin?->is_admin;
      $isExpert = $adminHasExpertRole;

      // Waiting on this CaPSA inside Team Management: teams to verify, and
      // members asking to leave a team that passed Title Defense.
      $pendingLeaveRequests = $isCapsa ? App\Models\MemberLeaveRequest::pendingCountFor($admin) : 0;
      $teamManagementBadge = $unseenPendingVerifyTeamCount + $pendingLeaveRequests;
      $badge = fn (int $n) => $n > 99 ? '99+' : $n;
    @endphp

    <div class="nav-section">Overview</div>
    <a href="{{ route($rolePrefix.'dashboard') }}" class="nav-item {{ $currentRoute === $rolePrefix.'dashboard' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    @if ($isCapsa)
    <a href="{{ route('capsa.announcements') }}" class="nav-item {{ $currentRoute === 'capsa.announcements' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3zm-8.27 4a2 2 0 0 1-3.46 0"/></svg>
      Announcements
    </a>
    @endif

    <div class="nav-section">People</div>
    <a href="{{ route($rolePrefix.'experts') }}" class="nav-item {{ $currentRoute === $rolePrefix.'experts' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Expert Management
      @if ($unseenExpertCount > 0)
        <span class="nav-badge red">{{ $badge($unseenExpertCount) }}</span>
      @endif
    </a>
    @if ($isCapsa)
    <a href="{{ route('capsa.sections') }}" class="nav-item {{ $currentRoute === 'capsa.sections' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Student Section List
    </a>
    @endif
    @if ($isAdmin)
    <a href="{{ route('admin.students') }}" class="nav-item {{ $currentRoute === 'admin.students' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M22 10v6M2 10l10-5 10 5-10 5-10-5z"/><path d="M6 12v5c0 1.66 2.69 3 6 3s6-1.34 6-3v-5"/></svg>
      Student Accounts
    </a>
    @endif

    @if ($isCapsa || $isExpert)
    <div class="nav-section">Teams</div>
    @endif
    @if ($isCapsa)
    <a href="{{ route('capsa.team_management') }}" class="nav-item {{ $currentRoute === 'capsa.team_management' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
      Team Management
      @if ($teamManagementBadge > 0)
        <span class="nav-badge red">{{ $badge($teamManagementBadge) }}</span>
      @endif
    </a>
    @endif
    @if ($isExpert)
    <a href="{{ route('instructor.teams') }}" class="nav-item {{ $currentRoute === 'instructor.teams' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      My Teams
    </a>
    <a href="{{ route('instructor.assignments') }}" class="nav-item {{ $currentRoute === 'instructor.assignments' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/><line x1="4" y1="4" x2="9" y2="9"/></svg>
      Team Assignments
      @if ($unseenExpertAssignmentCount > 0)
        <span class="nav-badge red">{{ $badge($unseenExpertAssignmentCount) }}</span>
      @endif
    </a>
    @endif

    @if ($isCapsa || $isExpert)
    <div class="nav-section">Defenses</div>
    @endif
    @if ($isCapsa)
    <a href="{{ route('capsa.panel_assignment') }}" class="nav-item {{ $currentRoute === 'capsa.panel_assignment' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="23" y1="11" x2="17" y2="11"/><line x1="20" y1="8" x2="20" y2="14"/></svg>
      Panel Assignment
      @if ($unseenVerifiedTeamCount > 0)
        <span class="nav-badge">{{ $badge($unseenVerifiedTeamCount) }}</span>
      @endif
    </a>
    <a href="{{ route('capsa.defense_scheduling') }}" class="nav-item {{ $currentRoute === 'capsa.defense_scheduling' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 16 11 18 15 14"/></svg>
      Defense Scheduling
    </a>
    @endif
    @if ($isExpert)
    <a href="{{ route('instructor.grading') }}" class="nav-item {{ in_array($currentRoute, ['instructor.grading', 'instructor.grading.show'], true) ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
      Team Grading
    </a>
    @endif
    @if ($isCapsa)
    <a href="{{ route('capsa.rubric_management') }}" class="nav-item {{ $currentRoute === 'capsa.rubric_management' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      Grading Rubrics
    </a>
    @endif

    @if ($isExpert)
    <div class="nav-section">Advising</div>
    <a href="{{ route('instructor.schedule') }}" class="nav-item {{ $currentRoute === 'instructor.schedule' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      My Schedule
    </a>
    <a href="{{ route('instructor.docreview') }}" class="nav-item {{ $currentRoute === 'instructor.docreview' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      Document Review
      @if ($unseenExpertDocReviewCount > 0)
        <span class="nav-badge red">{{ $badge($unseenExpertDocReviewCount) }}</span>
      @endif
    </a>
    @endif

    @if ($isCapsa)
    <div class="nav-section">Records</div>
    <a href="{{ route('capsa.financial') }}" class="nav-item {{ $currentRoute === 'capsa.financial' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
      Financial Records
      @if ($unseenPendingReceiptCount > 0)
        <span class="nav-badge red">{{ $badge($unseenPendingReceiptCount) }}</span>
      @endif
    </a>
    <a href="{{ route('capsa.archive') }}" class="nav-item {{ $currentRoute === 'capsa.archive' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
      Archived Capstones
      @if ($archivedCount > 0)
        <span class="nav-badge">{{ $badge($archivedCount) }}</span>
      @endif
    </a>
    <a href="{{ route('capsa.resources') }}" class="nav-item {{ $currentRoute === 'capsa.resources' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      Templates &amp; Lessons
    </a>
    @endif

    <div class="nav-section">Reports</div>
    @if ($isCapsa)
    <a href="{{ route('capsa.reports') }}" class="nav-item {{ str_starts_with($currentRoute ?? '', 'capsa.reports') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
      Reports
    </a>
    @endif
    <a href="{{ route($rolePrefix.'analytics') }}" class="nav-item {{ $currentRoute === $rolePrefix.'analytics' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      Analytics
    </a>
    <a href="{{ route($rolePrefix.'activitylog') }}" class="nav-item {{ $currentRoute === $rolePrefix.'activitylog' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      Activity Log
      @if ($unseenActivityCount > 0)
        <span class="nav-badge red">{{ $badge($unseenActivityCount) }}</span>
      @endif
    </a>

    <div class="nav-section">Account</div>
    @if ($isAdmin)
    <a href="{{ route('admin.settings') }}" class="nav-item {{ $currentRoute === 'admin.settings' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      System Settings
    </a>
    @endif
    <form id="admin-logout-form" action="{{ route('expert.logout') }}" method="POST" style="display: none;">
      @csrf
    </form>
    <a href="#" onclick="event.preventDefault(); try { sessionStorage.clear(); } catch (e) {} document.getElementById('admin-logout-form').submit();" class="nav-item">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Sign Out
    </a>
  </nav>
</div>
@if ($adminHasExpertRole)
  @include('instructor.partials.expert-profile')
@endif
@include('partials.sidebar-scroll-restore')
