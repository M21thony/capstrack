@php
    $student = Auth::user();
    $yearParts = explode(' · ', $student->year_section ?? '');
    $sidebarYear = $yearParts[0] ?? '';
    $sidebarSection = $yearParts[1] ?? '';
@endphp
<!-- SIDEBAR -->
<div class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-badge">
      <div class="brand-icon">
        <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
      </div>
      <div>
        <div class="brand-name">CapsTRACK</div>
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
        <div class="user-role">STUDENT</div>
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
    </a>
    <a href="{{ route('student.experts') }}" class="nav-item {{ request()->routeIs('student.experts') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
      Capstone Expert
      <span class="nav-badge warn">2</span>
    </a>
    <div class="nav-section">Documents</div>
    <a href="{{ route('student.submission') }}" class="nav-item {{ request()->routeIs('student.submission') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      Document Submission
    </a>
    <a href="{{ route('student.review') }}" class="nav-item {{ request()->routeIs('student.review') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
      Review Viewer
      <span class="nav-badge">3</span>
    </a>
    <a href="{{ route('student.duplication') }}" class="nav-item {{ request()->routeIs('student.duplication') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      Duplication Checker
    </a>
    <div class="nav-section">Schedule</div>
    <a href="{{ route('student.consultation') }}" class="nav-item {{ request()->routeIs('student.consultation') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Consultation
    </a>
    <div class="nav-section">Tracking</div>
    <a href="{{ route('student.progress') }}" class="nav-item {{ request()->routeIs('student.progress') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
      Progress Tracker
    </a>
    <a href="{{ route('student.financial') }}" class="nav-item {{ request()->routeIs('student.financial') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
      Financial Records
    </a>
    <div class="nav-section">Reference</div>
    <a href="{{ route('student.resources') }}" class="nav-item {{ request()->routeIs('student.resources') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      Resources
      <span class="nav-badge">2</span>
    </a>
    <a href="{{ route('student.archive') }}" class="nav-item {{ request()->routeIs('student.archive') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
      Archived Capstones
    </a>
    <div class="nav-section">Account</div>
    <a href="{{ route('student.personal-info.edit') }}" class="nav-item {{ request()->routeIs('student.personal-info*') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Personal Information
    </a>
    <form id="logout-form" action="{{ route('logout') }}" method="POST">
        @csrf
        <button type="submit" class="nav-item nav-item-button">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Sign Out
        </button>
    </form>
  </nav>
</div>

<div class="profile-overlay" id="profile-overlay" onclick="handleOverlayClick(event)">
  <div class="profile-modal" id="profile-modal" onclick="event.stopPropagation()">
    <div class="pm-header">
      @if ($student->avatarUrl())
        <img src="{{ $student->avatarUrl() }}" alt="" class="pm-avatar-img">
      @else
        <div class="pm-avatar">{{ $student->avatarInitials() }}</div>
      @endif
      <div style="flex:1;min-width:0">
        <div class="pm-header-name">{{ $student->f_name }} {{ $student->m_name }} {{ $student->l_name }}</div>
        <div class="pm-header-role">{{ $student->role ?? 'Student' }}</div>
      </div>
      <button type="button" onclick="closeProfile()" class="pm-close-btn" aria-label="Close">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="pm-note">
      View your details here. For full edits including avatar, use Personal Information.
    </div>
    <div class="pm-body" id="pm-body">
      <div class="pm-field">
        <div class="pm-label">Full Name</div>
      <div class="pm-value pm-view-only">{{ trim($student->f_name.' '.($student->m_name ?? '').' '.$student->l_name) }}</div>
      <div class="pm-edit-only">
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <input class="pm-input" id="edit-fname" value="{{ $student->f_name }}" style="flex:1;min-width:80px" placeholder="First name"/>
          <input class="pm-input" id="edit-mname" value="{{ $student->m_name }}" style="flex:1;min-width:80px" placeholder="Middle name"/>
          <input class="pm-input" id="edit-lname" value="{{ $student->l_name }}" style="flex:1;min-width:80px" placeholder="Last name"/>
        </div>
      </div>
      </div>
      <div class="pm-field">
        <div class="pm-label">Institutional Email</div>
        <div class="pm-value readonly">{{ $student->email }}</div>
      </div>
      <div class="pm-divider"></div>
      <div class="pm-field">
        <div class="pm-label">Course</div>
        <div class="pm-value pm-view-only">{{ $student->course }}</div>
        <div class="pm-edit-only">
          <select class="pm-input" id="edit-course">
            @foreach ([
              'Bachelor of Science in Computer Science',
              'Bachelor of Science in Information Technology',
              'Bachelor of Science in Information Systems',
              'Bachelor of Library and Information Science',
            ] as $courseOption)
              <option value="{{ $courseOption }}" @selected($student->course === $courseOption)>{{ $courseOption }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="pm-field">
        <div class="pm-label">Year &amp; Section</div>
        <div class="pm-value pm-view-only">{{ $student->year_section }}</div>
        <div class="pm-edit-only">
          <div style="display:flex;gap:6px">
            <select class="pm-input" id="edit-year" style="flex:1">
              @foreach (['1st Year', '2nd Year', '3rd Year', '4th Year'] as $yearOption)
                <option value="{{ $yearOption }}" @selected($sidebarYear === $yearOption)>{{ $yearOption }}</option>
              @endforeach
            </select>
            <input class="pm-input" id="edit-section" value="{{ $sidebarSection }}" style="width:90px" placeholder="Section"/>
          </div>
        </div>
      </div>
      <div class="pm-field">
        <div class="pm-label">Student ID</div>
        <div class="pm-value readonly">{{ $student->student_id }}</div>
      </div>
    </div>
    <div class="pm-footer">
      <a href="{{ route('student.personal-info.edit') }}" class="btn btn-ghost content" style="text-decoration:none">Full profile page</a>
      <button type="button" class="btn btn-ghost content" id="pm-edit-btn" onclick="toggleEdit()">Quick edit</button>
      <button type="button" class="btn btn-primary content" id="pm-save-btn" onclick="saveProfile()" style="display:none">Save</button>
      <button type="button" class="btn btn-ghost content" id="pm-cancel-btn" onclick="cancelEdit()" style="display:none">Cancel</button>
    </div>
  </div>
</div>

<script>
  const overlay   = document.getElementById('profile-overlay');
  const trigger   = document.getElementById('profile-trigger');
  const pmBody    = document.getElementById('pm-body');
  const editBtn   = document.getElementById('pm-edit-btn');
  const saveBtn   = document.getElementById('pm-save-btn');
  const cancelBtn = document.getElementById('pm-cancel-btn');
  let isEditing   = false;

  function openProfile() {
    if (!overlay || !trigger) return;
    overlay.classList.add('open');
    trigger.classList.add('active');
  }
  function closeProfile() {
    if (!overlay || !trigger) return;
    overlay.classList.remove('open');
    trigger.classList.remove('active');
    if (isEditing) cancelEdit();
  }
  function handleOverlayClick(e) {
    if (e.target === overlay) closeProfile();
  }
  function toggleEdit() {
    isEditing = true;
    pmBody.classList.add('edit-mode');
    if (editBtn) editBtn.style.display = 'none';
    if (saveBtn) saveBtn.style.display = 'inline-flex';
    if (cancelBtn) cancelBtn.style.display = 'inline-flex';
  }
  function cancelEdit() {
    isEditing = false;
    pmBody.classList.remove('edit-mode');
    if (editBtn) editBtn.style.display = 'inline-flex';
    if (saveBtn) saveBtn.style.display = 'none';
    if (cancelBtn) cancelBtn.style.display = 'none';
  }
  function saveProfile() {
    const tokenEl = document.querySelector('meta[name="csrf-token"]');
    if (!tokenEl) { window.location.href = '{{ route('student.personal-info.edit') }}'; return; }
    const fname   = document.getElementById('edit-fname').value.trim();
    const mname   = document.getElementById('edit-mname')?.value.trim() || '';
    const lname   = document.getElementById('edit-lname').value.trim();
    const course  = document.getElementById('edit-course').value;
    const year    = document.getElementById('edit-year').value;
    const section = document.getElementById('edit-section').value.trim();
    if (!fname || !lname) { document.getElementById('edit-fname').focus(); return; }
    fetch('{{ route('profile.update') }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': tokenEl.getAttribute('content'),
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        first_name: fname,
        middle_name: mname,
        last_name: lname,
        course: course,
        year_section: year + ' · ' + section
      })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        location.reload();
      } else {
        alert(data.message || 'Failed to update profile');
      }
    })
    .catch(() => alert('Error updating profile'));
  }
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeProfile(); });
</script>
