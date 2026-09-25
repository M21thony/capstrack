{{-- The student portal is light only; a theme saved back when it had a dark mode is ignored. --}}
<script>document.documentElement.setAttribute('data-theme','light')</script>
@php
    $student = Auth::user();
    $yearParts = explode(' · ', $student->year_section ?? '');
    $sidebarYear = $yearParts[0] ?? '';
    // Only the section's letter: year_section is "4th · A" from the profile
    // form, but "4th Year · BSCS-4A" on older rows, which read "BSCS - 4BSCS-4A".
    $sidebarSection = preg_match('/[A-Za-z]+$/', $yearParts[1] ?? '', $sectionLetter) ? $sectionLetter[0] : '';
    $courseAbbr = \App\Models\Student::mapCourseToProgram($student->course ?? '') ?? $student->course ?? '';
    $sidebarYearNum = preg_replace('/[^0-9]/', '', $sidebarYear);
    $sidebarYearSection = $courseAbbr ? $courseAbbr . ' - ' . $sidebarYearNum . $sidebarSection : $student->year_section ?? '';
    $studentHasTeam = \App\Models\StudentSchoolYear::where('student_id', $student->student_id)->whereNotNull('team_id')->exists();

    $currentRouteName = request()->route()?->getName();

    $lastVisitTeam = session('last_visit_team', now()->subYear());
    $lastVisitExperts = session('last_visit_experts', now()->subYear());
    $lastVisitFinancial = session('last_visit_financial', now()->subYear());
    $lastVisitConsultation = session('last_visit_consultation', now()->subYear());

    if ($currentRouteName === 'student.team') {
        session()->put('last_visit_team', now());
        $lastVisitTeam = now();
    }
    if ($currentRouteName === 'student.experts') {
        session()->put('last_visit_experts', now());
        $lastVisitExperts = now();
    }
    if ($currentRouteName === 'student.financial') {
        session()->put('last_visit_financial', now());
        $lastVisitFinancial = now();
    }
    if ($currentRouteName === 'student.consultation') {
        session()->put('last_visit_consultation', now());
        $lastVisitConsultation = now();
    }

    $studentTeamId = $studentHasTeam ? \App\Models\StudentSchoolYear::where('student_id', $student->student_id)->whereNotNull('team_id')->value('team_id') : null;
    $pendingExpertIds = $studentTeamId ? \App\Models\TeamExpertAssignment::where('team_id', $studentTeamId)->where('status', 'pending')->pluck('assignment_id')->toArray() : [];
    $seenExpertIds = session('seen_expert_ids', []);
    $unseenExpertCount = count(array_diff($pendingExpertIds, $seenExpertIds));

    if ($currentRouteName === 'student.experts') {
        session()->put('seen_expert_ids', array_unique(array_merge($seenExpertIds, $pendingExpertIds)));
    }

    $allPostIds = \App\Models\Recruitment::pluck('recruitment_id')->toArray();
    $seenPostIds = session('seen_post_ids', []);
    $newPostCount = count(array_diff($allPostIds, $seenPostIds));

    if ($currentRouteName === 'student.team') {
        session()->put('seen_post_ids', array_unique(array_merge($seenPostIds, $allPostIds)));
    }

    $pendingReceiptIds = $student ? \App\Models\FinancialRecord::where('student_id', $student->student_id)->whereNull('verified_at')->whereNotNull('receipt_image')->pluck('id')->toArray() : [];
    $seenReceiptIds = session('seen_receipt_ids', []);
    $unseenReceiptCount = count(array_diff($pendingReceiptIds, $seenReceiptIds));

    if ($currentRouteName === 'student.financial') {
        session()->put('seen_receipt_ids', array_unique(array_merge($seenReceiptIds, $pendingReceiptIds)));
    }

    $consultationIds = DB::table('consultation_schedule')->where('specific_date', '>=', now()->toDateString())->where('is_available', 1)->pluck('schedule_id')->toArray();
    $seenConsultationIds = session('seen_consultation_ids', []);
    $unseenConsultationCount = count(array_diff($consultationIds, $seenConsultationIds));

    if ($currentRouteName === 'student.consultation') {
        session()->put('seen_consultation_ids', array_unique(array_merge($seenConsultationIds, $consultationIds)));
    }
@endphp
<!-- SIDEBAR -->
@include('partials.portal-nav-icons')
<div class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-badge">
      <div class="brand-icon">
        <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
      </div>
      <div>
        <div class="brand-name">CapsTrack</div>
        <div class="brand-sub">Student Portal</div>
      </div>
    </div>
  </div>
  <div class="sidebar-user" id="profile-trigger" onclick="openProfile()" role="button" tabindex="0" aria-label="Open profile">
    <div class="sidebar-avatar">
      @if ($student->avatarUrl())
        <img src="{{ $student->avatarUrl() }}" alt="{{ $student->displayFirstName() }}" class="sidebar-avatar-img" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
        <span class="sidebar-avatar-fallback" style="display:none">{{ $student->avatarInitials() }}</span>
      @else
        <span class="sidebar-avatar-fallback">{{ $student->avatarInitials() }}</span>
      @endif
    </div>
    <div class="user-info">
      <div class="user-name">{{ $student->displayFirstName() }}</div>
      <div class="user-role">
        @if($student->hasCompletedProfile() && $sidebarYearSection)
          {{ $sidebarYearSection }}
        @else
          STUDENT
        @endif
      </div>
    </div>
    <div class="sidebar-user-caret">
      <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
  </div>
  <nav class="nav">
    <div class="nav-section">Main</div>
    <a href="{{ route('student.dashboard') }}" data-tour-target="student.dashboard" class="nav-item {{ request()->routeIs('student.dashboard') ? 'active' : '' }}">
      <x-portal-icon name="dashboard" />
      Dashboard
    </a>
    <a href="{{ route('student.team') }}" data-tour-target="student.team" class="nav-item {{ request()->routeIs('student.team') ? 'active' : '' }}">
      <x-portal-icon name="team" />
      Team Management
      @if ($newPostCount > 0)
        <span class="nav-badge">{{ $newPostCount > 99 ? '99+' : $newPostCount }}</span>
      @endif
    </a>
    <a href="{{ route('student.experts') }}" data-tour-target="student.experts" class="nav-item {{ request()->routeIs('student.experts') ? 'active' : '' }}">
      <x-portal-icon name="expert" />
      Capstone Expert
      @if ($unseenExpertCount > 0)
        <span class="nav-badge warn">{{ $unseenExpertCount > 99 ? '99+' : $unseenExpertCount }}</span>
      @endif
    </a>
    <div class="nav-section">Documents</div>
    {{-- Its own page: the form, and what the team has already sent. A line's
         Upload button on Progress, or Submit revision in the Review Viewer,
         opens the form in place instead. --}}
    <a href="{{ route('student.submission') }}" data-tour-target="student.submission" class="nav-item {{ request()->routeIs('student.submission') ? 'active' : '' }}">
      <x-portal-icon name="file-up" />
      Document Submission
    </a>
    <a href="{{ route('student.review') }}" data-tour-target="student.review" class="nav-item {{ request()->routeIs('student.review') ? 'active' : '' }}">
      <x-portal-icon name="feedback" />
      Review Viewer
    </a>
    <a href="{{ route('student.duplication') }}" data-tour-target="student.duplication" class="nav-item {{ request()->routeIs('student.duplication') ? 'active' : '' }}">
      <x-portal-icon name="similarity" />
      Pre-Proposal Submission
    </a>
    <div class="nav-section">Schedule</div>
    <a href="{{ route('student.consultation') }}" data-tour-target="student.consultation" class="nav-item {{ request()->routeIs('student.consultation') ? 'active' : '' }}">
      <x-portal-icon name="consultation" />
      Consultation
      @if ($unseenConsultationCount > 0)
        <span class="nav-badge red">{{ $unseenConsultationCount > 99 ? '99+' : $unseenConsultationCount }}</span>
      @endif
    </a>
    <div class="nav-section">Tracking</div>
    <a href="{{ route('student.progress') }}" data-tour-target="student.progress" class="nav-item {{ request()->routeIs('student.progress') ? 'active' : '' }}">
      <x-portal-icon name="progress" />
      Progress Tracker
    </a>
    <a href="{{ route('student.financial') }}" data-tour-target="student.financial" class="nav-item {{ request()->routeIs('student.financial') ? 'active' : '' }}">
      <x-portal-icon name="receipt" />
      Financial Records
      @if ($unseenReceiptCount > 0)
        <span class="nav-badge warn">{{ $unseenReceiptCount > 99 ? '99+' : $unseenReceiptCount }}</span>
      @endif
    </a>
    <div class="nav-section">Reference</div>
    <a href="{{ route('student.resources') }}" data-tour-target="student.resources" class="nav-item {{ request()->routeIs('student.resources') ? 'active' : '' }}">
      <x-portal-icon name="book" />
      Resources
    </a>
    <a href="{{ route('student.archive') }}" data-tour-target="student.archive" class="nav-item {{ request()->routeIs('student.archive') ? 'active' : '' }}">
      <x-portal-icon name="archive" />
      Archived Capstones
    </a>
    <div class="nav-section">Help</div>
    <button type="button" class="nav-item nav-item-button" data-tour-target="portal-tour" onclick="openPortalTour()" style="cursor:pointer">
      <x-portal-icon name="tour" />
      Portal Tour
    </button>
    <form id="logout-form" action="{{ route('logout') }}" method="POST">
        @csrf
        <button type="submit" class="nav-item nav-item-button">
            <x-portal-icon name="sign-out" />
            Sign Out
        </button>
    </form>
  </nav>
</div>

@include('partials.mobile-tabbar', ['floating' => true, 'menuAvatar' => ['url' => $student->avatarUrl(), 'initials' => $student->avatarInitials()], 'tabs' => array_values(array_filter([
    ['label' => 'Home', 'href' => route('student.dashboard'), 'active' => request()->routeIs('student.dashboard'),
        'icon' => App\Support\PortalIcons::paths('home')],
    ['label' => 'Team', 'href' => route('student.team'), 'active' => request()->routeIs('student.team'), 'badge' => $newPostCount,
        'icon' => App\Support\PortalIcons::paths('team')],
    // Handing in a document is what a student comes here to do most - but
    // not on Financial Records. There the page is about uploading a receipt,
    // and a big Submit button in the middle of the bar read as the way to do
    // that, then opened the document form instead.
    request()->routeIs('student.financial') ? null : ['label' => 'Submit', 'onclick' => "window.openSubmissionDrawer && window.openSubmissionDrawer('')", 'primary' => true,
        'icon' => App\Support\PortalIcons::paths('add')],
    ['label' => 'Progress', 'href' => route('student.progress'), 'active' => request()->routeIs('student.progress'),
        'icon' => App\Support\PortalIcons::paths('progress')],
]))])

<style>
  .profile-modal .pm-close-btn { display: inline-flex; align-items: center; justify-content: center; margin-left: auto; flex-shrink: 0; width: auto; height: auto; padding: 4px; background: none; background-color: transparent; color: rgba(255,255,255,0.4); border: none; border-radius: 0; line-height: 1; cursor: pointer; transition: color 0.13s ease-in-out; outline: none; }
  .profile-modal .pm-close-btn:hover { background-color: transparent; color: rgba(255,255,255,0.85); transform: none; }
  .profile-modal .pm-close-btn svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2.5; }

  /* ---- profile panel, rebuilt without the avatar ----
     The stylesheets under resources/student-ui carry the old .pm-* rules and
     there is a copy of them per page, so the new ones live here: one file,
     included on every student page, and no build step to forget. */
  .pm-header { align-items: flex-start; }

  /* On a phone the panel sat 250px from the left, beside a sidebar that is
     not there, so most of it ran off the screen. It rises from the bottom
     instead, the full width, over a dimmed page. */
  @media (max-width: 768px) {
    .profile-overlay.open { background: rgba(15,17,23,.45); pointer-events: all; }
    .profile-modal {
      left: 0 !important; right: 0; top: auto !important; bottom: 0;
      width: 100% !important; height: auto !important; max-height: 88vh;max-height: 88dvh;
      border-right: 0; border-radius: 18px 18px 0 0;
      transform: translateY(24px) !important;
      box-shadow: 0 -12px 40px rgba(0,0,0,.25);
      padding-bottom: env(safe-area-inset-bottom);
      z-index: 1001 !important;
    }
    .profile-overlay.open .profile-modal { transform: none !important; }
    .profile-overlay { z-index: 1000 !important; }
  }
  .pm-header-name { font-size: 16px; font-weight: 700; letter-spacing: -0.02em; line-height: 1.25; white-space: normal; overflow-wrap: anywhere; }
  .pm-header-sub { font-size: 11.5px; color: rgba(255,255,255,0.6); margin-top: 5px; display: flex; align-items: center; flex-wrap: wrap; }
  .pm-sep { margin: 0 6px; color: rgba(255,255,255,0.3); }
  .pm-no { font-variant-numeric: tabular-nums; letter-spacing: 0.02em; }

  .pm-body { padding: 0; }
  .pm-zone { padding: 15px 18px; }
  .pm-zone + .pm-zone { border-top: 1px solid var(--border); }
  .pm-zone-capstone { background: var(--bg3); }
  .pm-zone-head { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
  .pm-zone-head .pm-label { margin-bottom: 0; color: var(--text2); }
  .pm-rule { flex: 1; height: 1px; background: var(--border); }

  .pm-team { display: flex; align-items: center; gap: 11px; background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; padding: 11px 12px; }
  .pm-team-mark { width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0; background: var(--accent); color: #1a1a1a; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; letter-spacing: -0.02em; }
  .pm-team-name { font-size: 14px; font-weight: 600; line-height: 1.3; letter-spacing: -0.01em; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .pm-team-meta { font-size: 11px; color: var(--text3); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .pm-pill { flex-shrink: 0; padding: 3px 9px; border-radius: 20px; font-size: 9.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; }
  .pm-pill-ok { background: var(--green-bg); color: var(--green); }
  .pm-pill-wait { background: var(--amber-bg); color: var(--amber); }

  .pm-role { display: flex; align-items: center; gap: 12px; margin-top: 9px; background: var(--accent-light, rgba(201,168,44,0.09)); border: 1px solid rgba(201,168,44,0.35); border-radius: 10px; padding: 11px 12px; }
  .pm-role-name { font-size: 12.5px; font-weight: 700; color: var(--accent3); line-height: 1.35; overflow-wrap: anywhere; }
  .pm-lock { flex-shrink: 0; color: var(--accent3); opacity: 0.55; }

  .pm-rows { display: flex; flex-direction: column; gap: 11px; }
  .pm-row { display: flex; align-items: baseline; justify-content: space-between; gap: 14px; }
  .pm-row .pm-label { margin-bottom: 0; flex-shrink: 0; }
  /* Beats the boxed .pm-value the old stylesheets still define. */
  .pm-rows .pm-value { background: none; border: 0; padding: 0; font-size: 12.5px; text-align: right; min-width: 0; overflow-wrap: anywhere; }
</style>

<div class="profile-overlay" id="profile-overlay" onclick="handleOverlayClick(event)">
  {{-- No picture and no Sign Out: both are in the sidebar this panel opens
       from, four inches away. What is left is what the sidebar cannot show -
       the details, and where this student stands in their capstone. --}}
  <div class="profile-modal" id="profile-modal" onclick="event.stopPropagation()">
    <div class="pm-header">
      <div style="flex:1;min-width:0">
        {{-- With the avatar gone the name is the only thing anchoring the
             header, so it carries the weight the picture used to. --}}
        <div class="pm-header-name">{{ $student->f_name }} {{ $student->m_name }} {{ $student->l_name }}{{ $student->suffix ? ', ' . $student->suffix : '' }}</div>
        <div class="pm-header-sub">
          {{ $sidebarYearSection ?: 'Student' }}
          @if ($student->student_number)
            <span class="pm-sep">·</span><span class="pm-no">{{ $student->student_number }}</span>
          @endif
        </div>
      </div>
      <button type="button" onclick="closeProfile()" class="pm-close-btn" aria-label="Close">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>

    <div class="pm-body" id="pm-body">
      @if ($panelTeam)
        <div class="pm-zone pm-zone-capstone">
          <div class="pm-zone-head"><span class="pm-label">Capstone</span><span class="pm-rule"></span></div>

          <div class="pm-team">
            <div class="pm-team-mark">{{ mb_strtoupper(mb_substr($panelTeam->team_name, 0, 1)) }}{{ mb_strtolower(mb_substr($panelTeam->team_name, 1, 1)) }}</div>
            <div style="min-width:0;flex:1">
              <div class="pm-team-name">{{ $panelTeam->team_name }}</div>
              <div class="pm-team-meta">{{ $panelTeam->project_title ? Str::limit($panelTeam->project_title, 42) : 'No title chosen yet' }}</div>
            </div>
            @if ($panelTeam->is_confirmed)
              <span class="pm-pill pm-pill-ok">Verified</span>
            @else
              <span class="pm-pill pm-pill-wait">Pending</span>
            @endif
          </div>

          {{-- Chosen once when they joined, then settled for the semester.
               The padlock says so without a dead button inviting clicks. --}}
          <div class="pm-role">
            <div style="min-width:0;flex:1">
              <div class="pm-label" style="margin-bottom:3px">Your role</div>
              <div class="pm-role-name">{{ $panelRole ?: 'Not chosen yet' }}</div>
            </div>
            @if ($panelRole)
              <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" class="pm-lock" aria-label="Set for the semester">
                <rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>
              </svg>
            @endif
          </div>
        </div>
      @endif

      <div class="pm-zone">
        <div class="pm-zone-head"><span class="pm-label">Account</span><span class="pm-rule"></span></div>
        <div class="pm-rows">
          <div class="pm-row"><span class="pm-label">Email</span><span class="pm-value">{{ $student->email }}</span></div>
          <div class="pm-row"><span class="pm-label">Course</span><span class="pm-value">{{ $student->course }}</span></div>
        </div>
      </div>
    </div>

  </div>
</div>


@include('student.partials.join-request-popup')

<script>
  function openProfile() {
    var overlay = document.getElementById('profile-overlay');
    var trigger = document.getElementById('profile-trigger');
    if (!overlay || !trigger) return;
    overlay.classList.add('open');
    trigger.classList.add('active');
  }
  function closeProfile() {
    var overlay = document.getElementById('profile-overlay');
    var trigger = document.getElementById('profile-trigger');
    if (!overlay || !trigger) return;
    overlay.classList.remove('open');
    trigger.classList.remove('active');
  }
  function handleOverlayClick(e) {
    if (e.target.id === 'profile-overlay') closeProfile();
  }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeProfile(); });

</script>
{{-- ── The upload pop-up every Upload / Submit button opens. ── --}}
<link rel="stylesheet" href="{{ asset('css/submission-drawer.css') }}">

{{-- Where the pop-up asks, checks and sends. --}}
@php
  $uploadConfig = [
      'plan' => route('student.submission.plan'),
      'check' => route('student.submission.warnings'),
      'store' => route('student.submission.store'),
      'folders' => route('student.submission.subject-adviser-folders'),
      'review' => route('student.review'),
      'csrf' => csrf_token(),
  ];
@endphp
<script type="application/json" id="upload-config">@json($uploadConfig)</script>

{{-- One preview window and one message box for the whole student side. --}}
<dialog class="preview-dialog" id="previewDialog">
  <div class="preview-head">
    <h2 id="previewTitle">Preview</h2>
    <button class="x" type="button" onclick="document.getElementById('previewDialog').close()" aria-label="Close preview">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="preview-body" id="previewBody"></div>
</dialog>

<div id="submission-toast"></div>

@vite(['resources/js/submission-drawer.js', 'resources/js/submission-preview.js'])

{{-- The app's own ask-first dialog. --}}
@include('partials.app-dialog')
@include('partials.sidebar-scroll-restore')
@include('partials.keyboard-aware')

@include('student.partials.portal-tour')
