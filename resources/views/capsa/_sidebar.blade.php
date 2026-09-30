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


@include('partials.portal-nav-icons')
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
       plain Admin/CaPSA account with no expert duties. Such an account's card
       opens its own profile page instead: it used to do nothing at all, and
       on a phone the menu is the only way there. --}}
  @if ($adminHasExpertRole)
  <div class="sidebar-user" id="ep-trigger" onclick="openExpertProfile()" style="cursor:pointer">
  @else
  <a class="sidebar-user sidebar-user-link" href="{{ route($rolePrefix.'profile') }}" aria-label="My profile" @if ($currentRoute === $rolePrefix.'profile') aria-current="page" @endif>
  @endif
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
  @if ($adminHasExpertRole)
  </div>
  @else
  </a>
  @endif
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
      <x-portal-icon name="dashboard" />
      Dashboard
    </a>
    @if ($isCapsa)
    <a href="{{ route('capsa.announcements') }}" class="nav-item {{ $currentRoute === 'capsa.announcements' ? 'active' : '' }}">
      <x-portal-icon name="announcement" />
      Announcements
    </a>
    @endif

    <div class="nav-section">People</div>
    <a href="{{ route($rolePrefix.'experts') }}" class="nav-item {{ $currentRoute === $rolePrefix.'experts' ? 'active' : '' }}">
      <x-portal-icon name="expert" />
      Expert Management
      @if ($unseenExpertCount > 0)
        <span class="nav-badge red">{{ $badge($unseenExpertCount) }}</span>
      @endif
    </a>
    @if ($isCapsa)
    <a href="{{ route('capsa.sections') }}" class="nav-item {{ $currentRoute === 'capsa.sections' ? 'active' : '' }}">
      <x-portal-icon name="section-list" />
      Student Section List
    </a>
    @endif
    @if ($isAdmin)
    <a href="{{ route('admin.students') }}" class="nav-item {{ $currentRoute === 'admin.students' ? 'active' : '' }}">
      <x-portal-icon name="student" />
      Student Accounts
    </a>
    @endif

    @if ($isCapsa || $isExpert)
    <div class="nav-section">Teams</div>
    @endif
    @if ($isCapsa)
    <a href="{{ route('capsa.team_management') }}" class="nav-item {{ $currentRoute === 'capsa.team_management' ? 'active' : '' }}">
      <x-portal-icon name="teams-manage" />
      Team Management
      @if ($teamManagementBadge > 0)
        <span class="nav-badge red">{{ $badge($teamManagementBadge) }}</span>
      @endif
    </a>
    @endif
    @if ($isExpert)
    <a href="{{ route('instructor.teams') }}" class="nav-item {{ $currentRoute === 'instructor.teams' ? 'active' : '' }}">
      <x-portal-icon name="team" />
      My Teams
    </a>
    <a href="{{ route('instructor.assignments') }}" class="nav-item {{ $currentRoute === 'instructor.assignments' ? 'active' : '' }}">
      <x-portal-icon name="inbox" />
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
      <x-portal-icon name="user-plus" />
      Panel Assignment
      @if ($unseenVerifiedTeamCount > 0)
        <span class="nav-badge">{{ $badge($unseenVerifiedTeamCount) }}</span>
      @endif
    </a>
    <a href="{{ route('capsa.defense_scheduling') }}" class="nav-item {{ $currentRoute === 'capsa.defense_scheduling' ? 'active' : '' }}">
      <x-portal-icon name="defense" />
      Defense Scheduling
    </a>
    @endif
    @if ($isExpert)
    <a href="{{ route('instructor.grading') }}" class="nav-item {{ in_array($currentRoute, ['instructor.grading', 'instructor.grading.show'], true) ? 'active' : '' }}">
      <x-portal-icon name="grading" />
      Team Grading
    </a>
    @endif
    @if ($isCapsa)
    <a href="{{ route('capsa.rubric_management') }}" class="nav-item {{ $currentRoute === 'capsa.rubric_management' ? 'active' : '' }}">
      <x-portal-icon name="rubric" />
      Grading Rubrics
    </a>
    @endif

    @if ($isExpert)
    <div class="nav-section">Advising</div>
    <a href="{{ route('instructor.schedule') }}" class="nav-item {{ $currentRoute === 'instructor.schedule' ? 'active' : '' }}">
      <x-portal-icon name="consultation" />
      My Schedule
    </a>
    <a href="{{ route('instructor.docreview') }}" class="nav-item {{ $currentRoute === 'instructor.docreview' ? 'active' : '' }}">
      <x-portal-icon name="file-review" />
      Document Review
      @if ($unseenExpertDocReviewCount > 0)
        <span class="nav-badge red">{{ $badge($unseenExpertDocReviewCount) }}</span>
      @endif
    </a>
    @endif

    @if ($isCapsa)
    <div class="nav-section">Records</div>
    <a href="{{ route('capsa.financial') }}" class="nav-item {{ $currentRoute === 'capsa.financial' ? 'active' : '' }}">
      <x-portal-icon name="receipt" />
      Financial Records
      @if ($unseenPendingReceiptCount > 0)
        <span class="nav-badge red">{{ $badge($unseenPendingReceiptCount) }}</span>
      @endif
    </a>
    <a href="{{ route('capsa.archive') }}" class="nav-item {{ $currentRoute === 'capsa.archive' ? 'active' : '' }}">
      <x-portal-icon name="archive" />
      Archived Capstones
      @if ($archivedCount > 0)
        <span class="nav-badge">{{ $badge($archivedCount) }}</span>
      @endif
    </a>
    <a href="{{ route('capsa.resources') }}" class="nav-item {{ $currentRoute === 'capsa.resources' ? 'active' : '' }}">
      <x-portal-icon name="book" />
      Templates &amp; Lessons
    </a>
    @endif

    <div class="nav-section">Reports</div>
    @if ($isCapsa)
    <a href="{{ route('capsa.reports') }}" class="nav-item {{ str_starts_with($currentRoute ?? '', 'capsa.reports') ? 'active' : '' }}">
      <x-portal-icon name="reports" />
      Reports
    </a>
    @endif
    <a href="{{ route($rolePrefix.'analytics') }}" class="nav-item {{ $currentRoute === $rolePrefix.'analytics' ? 'active' : '' }}">
      <x-portal-icon name="analytics" />
      Analytics
    </a>
    <a href="{{ route($rolePrefix.'activitylog') }}" class="nav-item {{ $currentRoute === $rolePrefix.'activitylog' ? 'active' : '' }}">
      <x-portal-icon name="history" />
      Activity Log
      @if ($unseenActivityCount > 0)
        <span class="nav-badge red">{{ $badge($unseenActivityCount) }}</span>
      @endif
    </a>

    <div class="nav-section">Account</div>
    @if ($isAdmin)
    <a href="{{ route('admin.settings') }}" class="nav-item {{ $currentRoute === 'admin.settings' ? 'active' : '' }}">
      <x-portal-icon name="settings" />
      System Settings
    </a>
    @endif
    <form id="admin-logout-form" action="{{ route('expert.logout') }}" method="POST" style="display: none;">
      @csrf
    </form>
    <a href="#" onclick="event.preventDefault(); try { sessionStorage.clear(); } catch (e) {} document.getElementById('admin-logout-form').submit();" class="nav-item">
      <x-portal-icon name="sign-out" />
      Sign Out
    </a>
  </nav>
</div>
@php
    // The phone tab bar: only pages this account can open, most-used first.
    $phoneTabs = [[
        'label' => 'Home', 'href' => route($rolePrefix.'dashboard'), 'active' => $currentRoute === $rolePrefix.'dashboard',
        'icon' => App\Support\PortalIcons::paths('home'),
    ]];
    if ($isCapsa) {
        $phoneTabs[] = ['label' => 'Teams', 'href' => route('capsa.team_management'), 'active' => str_starts_with((string) $currentRoute, 'capsa.team_management'),
            'badge' => $teamManagementBadge ?? 0,
            'icon' => App\Support\PortalIcons::paths('teams-manage')];
        $phoneTabs[] = ['label' => 'Defenses', 'href' => route('capsa.defense_scheduling'), 'active' => $currentRoute === 'capsa.defense_scheduling',
            'icon' => App\Support\PortalIcons::paths('defense')];
    }
    // An admin who is not also a CaPSA runs accounts and reports, so their
    // bar is the people they manage and the numbers they read.
    if ($isAdmin && ! $isCapsa) {
        $phoneTabs[] = ['label' => 'Experts', 'href' => route('admin.experts'), 'active' => $currentRoute === 'admin.experts',
            'badge' => $unseenExpertCount ?? 0,
            'icon' => App\Support\PortalIcons::paths('expert')];
    }
    if ($isAdmin) {
        $phoneTabs[] = ['label' => 'Students', 'href' => route('admin.students'), 'active' => $currentRoute === 'admin.students',
            'icon' => App\Support\PortalIcons::paths('student')];
    }
    if ($isAdmin && ! $isCapsa) {
        $phoneTabs[] = ['label' => 'Analytics', 'href' => route('admin.analytics'), 'active' => $currentRoute === 'admin.analytics',
            'icon' => App\Support\PortalIcons::paths('analytics')];
    }
    if ($isExpert) {
        $phoneTabs[] = ['label' => 'Grading', 'href' => route('instructor.grading'), 'active' => in_array($currentRoute, ['instructor.grading', 'instructor.grading.show'], true),
            'icon' => App\Support\PortalIcons::paths('grading')];
        $phoneTabs[] = ['label' => 'Reviews', 'href' => route('instructor.docreview'), 'active' => $currentRoute === 'instructor.docreview',
            'badge' => $unseenExpertDocReviewCount ?? 0,
            'icon' => App\Support\PortalIcons::paths('file-review')];
    }
@endphp
@include('partials.mobile-tabbar', ['floating' => true, 'menuAvatar' => $admin ? ['url' => $admin->hasAvatarImage() ? $admin->avatarUrl() : null, 'initials' => $admin->avatarInitials()] : null, 'tabs' => array_slice($phoneTabs, 0, 4)])
{{-- The guided tour: the CaPSA's, or the Admin's for an admin who is not also a CaPSA. --}}
@include('partials.staff-portal-tour', ['tourKey' => $isCapsa ? 'capsa' : 'admin', 'expert' => $admin])
@if ($adminHasExpertRole)
  @include('instructor.partials.expert-profile')
@endif
@include('partials.sidebar-scroll-restore')
