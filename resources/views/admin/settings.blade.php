<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>System Settings - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
</head>
<body>

@include('capsa._sidebar')

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">System Settings</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div class="content">
    @if (session('success'))
      <div class="card" style="border-color:var(--green);background:var(--green-bg);margin-bottom:16px;padding:12px 16px;color:var(--green);font-size:13px">{{ session('success') }}</div>
    @endif

    <div class="card" style="max-width:480px">
      <div class="card-header"><span class="card-title">Current School Year</span></div>
      <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        <div style="margin-bottom:14px">
          <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text3);margin-bottom:6px">School Year</label>
          <input class="input" type="text" name="current_school_year" value="{{ old('current_school_year', $currentSchoolYear) }}" placeholder="2026-2027">
          @error('current_school_year')
            <div style="font-size:11px;color:var(--red);margin-top:4px">{{ $message }}</div>
          @enderror
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
