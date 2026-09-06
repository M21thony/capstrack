<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Student Section List - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
</head>
<body>

@include('admin._sidebar')
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Student Section List</div>
    <div class="topbar-actions">
      @include('admin.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="card">
      <div class="sk" style="width:220px;height:18px;border-radius:4px;margin-bottom:18px"></div>
      <div class="sk" style="height:40px;border-radius:8px;margin-bottom:8px"></div>
      <div class="sk" style="height:40px;border-radius:8px;margin-bottom:8px"></div>
      <div class="sk" style="height:40px;border-radius:8px;margin-bottom:8px"></div>
      <div class="sk" style="height:40px;border-radius:8px"></div>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content">
    <div class="card">
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div>
          <span class="card-title">Students in Your Sections</span>
          @if (!empty($adminSections))
            <div style="font-size:12px;color:var(--text3)">Filtered by: {{ implode(', ', $adminSections) }}</div>
          @endif
        </div>

        <div style="display:flex;align-items:center;gap:8px">
          <form method="get" id="sectionFilterForm">
            <label for="section" style="font-size:13px;color:var(--text2);margin-right:8px">Section</label>
            <select name="section" id="section" onchange="document.getElementById('sectionFilterForm').submit()" style="padding:6px 10px;border-radius:8px;border:1px solid var(--muted);background:#fff">
              <option value="">All (assigned)</option>
              @foreach ($allSections as $section)
                <option value="{{ $section }}" {{ request()->query('section') == $section ? 'selected' : '' }}>{{ $section }}</option>
              @endforeach
            </select>
          </form>
        </div>
      </div>

      <div style="overflow-x:auto">
        @if (! empty($debug))
          <div style="padding:12px;background:#fff6; border:1px dashed var(--muted);margin-bottom:12px;border-radius:8px;font-size:13px">
            <strong>Debug:</strong>
            <div style="font-size:12px;color:var(--text3)">SY records: {{ $debug['sy_count'] }} — Admin sections: {{ implode(', ', $debug['adminSections'] ?? []) }} — Admin programs: {{ implode(', ', $debug['adminPrograms'] ?? []) }}</div>
            <div style="margin-top:8px;overflow:auto;max-height:160px">
              <table style="width:100%;font-size:12px;border-collapse:collapse">
                <thead><tr><th style="text-align:left;padding:6px;border-bottom:1px solid #eee">Student ID</th><th style="text-align:left;padding:6px;border-bottom:1px solid #eee">Team</th><th style="text-align:left;padding:6px;border-bottom:1px solid #eee">Program</th><th style="text-align:left;padding:6px;border-bottom:1px solid #eee">Year Section Raw</th><th style="text-align:left;padding:6px;border-bottom:1px solid #eee">Computed Label</th></tr></thead>
                <tbody>
                  @foreach ($debug['sample'] as $row)
                    <tr>
                      <td style="padding:6px;border-bottom:1px solid #f6f6f6">{{ $row['student_id'] }}</td>
                      <td style="padding:6px;border-bottom:1px solid #f6f6f6">{{ $row['team_id'] }}</td>
                      <td style="padding:6px;border-bottom:1px solid #f6f6f6">{{ $row['program'] }}</td>
                      <td style="padding:6px;border-bottom:1px solid #f6f6f6">{{ $row['year_section_raw'] }}</td>
                      <td style="padding:6px;border-bottom:1px solid #f6f6f6">{{ $row['computed_label'] }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        @endif
        <table class="table">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th>Student Name</th>
              <th>Program</th>
              <th>Year & Section</th>
              <th>Team</th>
              <th>School Year</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($students as $index => $student)
              @php $sy = $student->currentSy; @endphp
              <tr>
                <td>{{ $index + 1 }}</td>
                <td style="font-weight:500">{{ $student->l_name }}, {{ $student->f_name }} {{ $student->m_name ? $student->m_name[0] . '.' : '' }}</td>
                <td>{{ $student->programAbbr ?? '—' }}</td>
                <td>{{ $student->year_section ?? '—' }}</td>
                <td>
                  @if ($sy && $sy->team)
                    {{ $sy->team->team_name ?? 'Team #' . $sy->team->team_id }}
                  @else
                    <span style="color:var(--text3)">—</span>
                  @endif
                </td>
                <td>{{ $sy->school_year ?? '—' }}</td>
                <td>
                  @if ($student->isOnline())
                    <span style="padding:3px 8px;border-radius:6px;font-size:11px;font-weight:600;background:rgba(22,163,74,0.1);color:var(--green)">Active</span>
                  @else
                    <span style="padding:3px 8px;border-radius:6px;font-size:11px;font-weight:600;background:rgba(107,114,128,0.1);color:var(--text3)">Offline</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" style="text-align:center;padding:40px 0;color:var(--text3);font-size:13px">
                  @if (empty($adminSections))
                    No sections assigned yet. <a href="{{ route('admin.profile') }}" style="color:var(--accent)">Set your sections</a> to view students.
                  @else
                    No students found for the assigned sections.
                  @endif
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
  </div>
</div>
@include('admin.partials.skeleton-toggle')

<script>
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() {
  sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar();
}
function openSidebar() {
  sidebarEl.classList.add('mobile-open');
  sidebarOverlay.classList.add('open');
}
function closeSidebar() {
  sidebarEl.classList.remove('mobile-open');
  sidebarOverlay.classList.remove('open');
}
</script>

</body>
</html>
