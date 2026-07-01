@php
    $admin = Auth::guard('expert')->user();

    // Use the email of the currently logged-in admin/expert account.
    // (This is the Gmail profile email they log in with.)
    $adminEmail = $admin?->email;
    $adminAvatar = $adminEmail ?: 'system';
    $adminName = $admin ? trim(($admin->first_name ?? '').' '.($admin->last_name ?? '')) : 'System Administrator';

    // Render actual stored profile picture for the logged-in admin/expert.
    $adminProfilePicture = $admin ? ($admin->profile_picture ?: null) : null;

    $currentRoute = Route::currentRouteName();
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
    <div class="avatar">
      @php
        $defaultAvatarUrl = asset('images/default-avatar.jpg');
        $profilePictureUrl = null;
        if (!empty($adminProfilePicture)) {
          // capstrackdb shows profile_picture may be a filename like "avatar-07".
          $profilePictureUrl = asset('images/avatars/'.$adminProfilePicture);
        }
      @endphp

      <img
        src="{{ $profilePictureUrl ?: $defaultAvatarUrl }}"
        alt="Profile"
        style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;"
        loading="lazy"
        onerror="this.onerror=null; this.src='{{ $defaultAvatarUrl }}';"
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
    </a>
    <a href="{{ route('admin.experts') }}" class="nav-item {{ $currentRoute === 'admin.experts' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Expert Management
    </a>
    <a href="{{ route('admin.panel_assignment') }}" class="nav-item {{ $currentRoute === 'admin.panel_assignment' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="23" y1="11" x2="17" y2="11"/><line x1="20" y1="8" x2="20" y2="14"/></svg>
      Panel Assignment
    </a>
    <div class="nav-section">Capstone Archive</div>
    <a href="{{ route('admin.archive') }}" class="nav-item {{ $currentRoute === 'admin.archive' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
      Archived Capstones
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
    </a>
    <div class="nav-section">Account</div>
    <a href="{{ route('admin.financial') }}" class="nav-item {{ $currentRoute === 'admin.financial' ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
      Financial Records
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
