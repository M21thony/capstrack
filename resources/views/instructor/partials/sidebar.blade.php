<div class="sidebar">
  @if(!isset($showBrand) || $showBrand)
    <div class="sidebar-brand">
      <div class="brand-icon">
        <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
      </div>
      <div>
        <div class="brand-name">CapsTRACK</div>
        <div class="brand-sub">Expert Portal</div>
      </div>
    </div>
  @endif

  <!-- Click to open the side panel -->
  <div class="sidebar-user" id="ep-trigger" onclick="openExpertProfile()">
    <div class="avatar">{{ strtoupper(substr(auth('expert')->user()?->email ?? 'E', 0, 2)) }}</div>

    <div class="user-info">
      <div class="user-name">{{ auth('expert')->user()?->first_name }} {{ auth('expert')->user()?->last_name }}</div>
      <div class="user-role">{{ ucfirst(auth('expert')->user()?->roles->pluck('role_name')->join(', ', ' & ')) ?? 'Expert' }} Active</div>
    </div>
    <div class="status-dot"></div>
    <div class="su-caret">
      <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
    </div>
  </div>

  @include('instructor.partials.sidebar-nav', ['currentPage' => $currentPage ?? ''])
</div>
@include('partials.idle-timeout')
