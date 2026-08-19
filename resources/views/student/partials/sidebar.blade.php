<script>document.documentElement.setAttribute('data-theme',localStorage.getItem('theme')||'light')</script>
@php
    $student = Auth::user();
    $yearParts = explode(' · ', $student->year_section ?? '');
    $sidebarYear = $yearParts[0] ?? '';
    $sidebarSection = $yearParts[1] ?? '';
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
  @if ($student->hasCompletedProfile())
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
  @else
    <a href="{{ route('student.personal-info') }}" class="sidebar-user sidebar-user-incomplete">
      <div class="sidebar-avatar">
        <span class="sidebar-avatar-fallback">!</span>
      </div>
      <div class="user-info">
        <div class="user-name">Complete your profile</div>
        <div class="user-role">Required before use</div>
      </div>
    </a>
  @endif
  <nav class="nav">
    <div class="nav-section">Main</div>
    <a href="{{ route('student.dashboard') }}" class="nav-item {{ request()->routeIs('student.dashboard') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <a href="{{ route('student.team') }}" class="nav-item {{ request()->routeIs('student.team') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Team Management
      @if ($newPostCount > 0)
        <span class="nav-badge">{{ $newPostCount > 99 ? '99+' : $newPostCount }}</span>
      @endif
    </a>
    <a href="{{ route('student.experts') }}" class="nav-item {{ request()->routeIs('student.experts') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
      Capstone Expert
      @if ($unseenExpertCount > 0)
        <span class="nav-badge warn">{{ $unseenExpertCount > 99 ? '99+' : $unseenExpertCount }}</span>
      @endif
    </a>
    <div class="nav-section">Documents</div>
    <a href="{{ route('student.submission') }}" class="nav-item {{ request()->routeIs('student.submission') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      Document Submission
    </a>
    <a href="{{ route('student.review') }}" class="nav-item {{ request()->routeIs('student.review') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
      Review Viewer
    </a>
    <a href="{{ route('student.duplication') }}" class="nav-item {{ request()->routeIs('student.duplication') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      Duplication Checker
    </a>
    <div class="nav-section">Schedule</div>
    <a href="{{ route('student.consultation') }}" class="nav-item {{ request()->routeIs('student.consultation') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Consultation
      @if ($unseenConsultationCount > 0)
        <span class="nav-badge red">{{ $unseenConsultationCount > 99 ? '99+' : $unseenConsultationCount }}</span>
      @endif
    </a>
    <div class="nav-section">Tracking</div>
    <a href="{{ route('student.progress') }}" class="nav-item {{ request()->routeIs('student.progress') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
      Progress Tracker
    </a>
    <a href="{{ route('student.financial') }}" class="nav-item {{ request()->routeIs('student.financial') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
      Financial Records
      @if ($unseenReceiptCount > 0)
        <span class="nav-badge warn">{{ $unseenReceiptCount > 99 ? '99+' : $unseenReceiptCount }}</span>
      @endif
    </a>
    <div class="nav-section">Reference</div>
    <a href="{{ route('student.resources') }}" class="nav-item {{ request()->routeIs('student.resources') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      Resources
    </a>
    <a href="{{ route('student.archive') }}" class="nav-item {{ request()->routeIs('student.archive') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
      Archived Capstones
    </a>
    <div class="nav-section">Account</div>
    <a href="{{ route('student.personal-info.edit') }}" class="nav-item {{ request()->routeIs('student.personal-info*') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Personal Information  
      @if ($studentHasTeam)
      @endif
    </a>
    <div class="nav-section">Appearance</div>
    <button class="nav-item nav-item-button" onclick="toggleTheme()" style="cursor:pointer">
      <svg id="themeIcon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      <span id="themeLabel">Dark Mode</span>
    </button>
    <form id="logout-form" action="{{ route('logout') }}" method="POST">
        @csrf
        <button type="submit" class="nav-item nav-item-button">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Sign Out
        </button>
    </form>
  </nav>
</div>

<style>
  .pm-close-btn { display: inline-flex; align-items: center; justify-content: center; margin-left: auto; flex-shrink: 0; padding: 4px; background: none; color: rgba(255,255,255,0.4); border: none; line-height: 1; cursor: pointer; transition: color 0.13s ease-in-out; outline: none; }
  .pm-close-btn:hover { color: rgba(255,255,255,0.85); }
  .pm-close-btn svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2.5; }
  .pm-edit-profile-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; flex: 1; padding: 10px 16px; background-color: #2563eb; color: #ffffff !important; font-size: 13px; font-weight: 600; text-decoration: none; border-radius: 8px; border: none; transition: background-color 0.15s ease, transform 0.1s ease; box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2); text-align: center; }
  .pm-edit-profile-btn:hover { background-color: #1d4ed8; box-shadow: 0 4px 6px rgba(37, 99, 235, 0.3); }
  .pm-edit-profile-btn:active { transform: scale(0.98); }
</style>

<div class="profile-overlay" id="profile-overlay" onclick="handleOverlayClick(event)">
  <div class="profile-modal" id="profile-modal" onclick="event.stopPropagation()">
    <div class="pm-header">
      @if ($student->avatarUrl())
        <img src="{{ $student->avatarUrl() }}" alt="" class="pm-avatar-img">
      @else
        <div class="pm-avatar">{{ $student->avatarInitials() }}</div>
      @endif
      <div style="flex:1;min-width:0">
        <div class="pm-header-name">{{ $student->f_name }} {{ $student->m_name }} {{ $student->l_name }}{{ $student->suffix ? ', ' . $student->suffix : '' }}</div>
        <div class="pm-header-role">{{ $student->role ?? 'Student' }}</div>
      </div>
      <button type="button" onclick="closeProfile()" class="pm-close-btn" aria-label="Close">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="pm-body" id="pm-body">
      <div class="pm-field">
        <div class="pm-label">Full Name</div>
        <div class="pm-value">{{ trim($student->l_name . ', ' . $student->f_name . ($student->m_name ? ' ' . $student->m_name : '') . ($student->suffix ? ' ' . $student->suffix : '')) . '.' }}</div>
      </div>
      <div class="pm-field">
        <div class="pm-label">Institutional Email</div>
        <div class="pm-value readonly">
          <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          {{ $student->email }}
        </div>
      </div>
      <div class="pm-divider"></div>
      <div class="pm-field">
        <div class="pm-label">Course</div>
        <div class="pm-value">{{ $student->course }}</div>
      </div>
      <div class="pm-field">
        <div class="pm-label">Year &amp; Section</div>
        <div class="pm-value">{{ $sidebarYearSection }}</div>
      </div>
      <div class="pm-field">
        <div class="pm-label">Student ID</div>
        <div class="pm-value">{{ $student->student_number ?? '—' }}</div>
      </div>
    </div>
    <div class="pm-footer">
      <a href="{{ route('student.personal-info.edit') }}" class="pm-edit-profile-btn" style="text-decoration:none;">
        Edit Profile
        @if ($studentHasTeam)

        @endif
      </a>
    </div>
  </div>
</div>

@include('partials.idle-timeout')

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

  function toggleTheme() {
    var html = document.documentElement;
    var isDark = html.getAttribute('data-theme') === 'dark';
    html.setAttribute('data-theme', isDark ? 'light' : 'dark');
    localStorage.setItem('theme', isDark ? 'light' : 'dark');
    updateThemeUI();
  }
  function updateThemeUI() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var icon = document.getElementById('themeIcon');
    var label = document.getElementById('themeLabel');
    if (!icon || !label) return;
    if (isDark) {
      icon.setAttribute('viewBox', '0 0 24 24');
      icon.innerHTML = '<circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>';
      label.textContent = 'Light Mode';
    } else {
      icon.setAttribute('viewBox', '0 0 24 24');
      icon.innerHTML = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';
      label.textContent = 'Dark Mode';
    }
  }
  updateThemeUI();
</script>

