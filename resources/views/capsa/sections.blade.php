<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Student Section List - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<style>
  .ct-modal-overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,0.5);z-index:9998;align-items:center;justify-content:center;animation:ctFadeIn 0.15s ease;padding:20px}
  .ct-modal-card{background:#fff;border-radius:14px;padding:28px;max-width:420px;width:100%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.25);animation:ctPopUp 0.2s cubic-bezier(0.16,1,0.3,1)}
  .ct-modal-title{font-size:17px;font-weight:700;color:#0f172a;margin-bottom:8px}
  .ct-modal-desc{font-size:13px;color:#64748b;line-height:1.5;margin-bottom:16px}
  .ct-modal-input{width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;outline:none;margin-bottom:14px}
  .ct-modal-actions{display:flex;gap:10px;margin-top:4px}
  /* Named alongside .ct-upload-card, because that rule carries two classes
     and would otherwise outrank this one and hold the preview at the width
     of the step before it. As wide as the window allows, so the whole list
     is read rather than scrolled sideways. */
  .ct-modal-card.ct-upload-card.ct-modal-card-wide{max-width:min(1180px,96vw);text-align:left}
  /* Both classes in the selector, so this beats .ct-modal-card wherever that
     is defined - on this page it is defined further down, and a single-class
     rule of equal weight coming later would otherwise win and squeeze the
     card back to the narrow dialog width. width as well as max-width,
     because the card is a flex item and a max-width alone leaves it free to
     shrink to fit its contents. */
  .ct-modal-card.ct-upload-card{width:100%;max-width:660px;text-align:left;padding:24px}
  .ct-up-head{display:flex;align-items:flex-start;gap:14px;margin-bottom:18px}
  .ct-up-head-icon{display:flex;align-items:center;justify-content:center;width:46px;height:46px;border-radius:50%;
                   background:#eff6ff;color:#2563eb;flex-shrink:0}
  .ct-up-head-text{display:flex;flex-direction:column;gap:4px;flex:1;min-width:0}
  .ct-up-head .ct-modal-title{margin-bottom:0;font-size:22px;letter-spacing:-0.4px}
  .ct-up-sub{font-size:13px;color:#64748b}
  .ct-up-close{background:none;border:none;padding:2px;cursor:pointer;color:#64748b;line-height:0;border-radius:6px}
  .ct-up-close:hover{color:#0f172a;background:#f1f5f9}

  .ct-up-hint{display:flex;align-items:center;gap:9px;padding:11px 14px;border-radius:10px;background:#f8fafc;
              font-size:13px;color:#475569;margin-bottom:18px;flex-wrap:wrap}
  .ct-up-hint > svg{color:#94a3b8;flex-shrink:0}
  .ct-template-link{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#2563eb;
                    text-decoration:none}
  .ct-template-link:hover{color:#1d4ed8;text-decoration:underline;text-underline-offset:3px}

  /* The drop zone is the body of the modal, so it is the thing the eye lands
     on and the thing a file can be thrown at without hunting for a button. */
  .ct-dropzone{display:flex;flex-direction:column;align-items:center;gap:10px;padding:32px 20px;
               border:1.5px dashed #cbd5e1;border-radius:12px;text-align:center;transition:border-color .15s,background .15s}
  .ct-dropzone.dragging{border-color:#2563eb;background:#eff6ff}
  .ct-drop-icon{display:flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:50%;
                background:#eff6ff;color:#2563eb}
  .ct-drop-title{font-size:15px;font-weight:600;color:#0f172a}
  .ct-drop-or{font-size:13px;color:#94a3b8}
  .ct-drop-browse{padding:9px 22px;border:1.5px solid #2563eb;border-radius:9px;background:#fff;color:#2563eb;
                  font-family:inherit;font-size:13.5px;font-weight:600;cursor:pointer;transition:background .12s}
  .ct-drop-browse:hover{background:#eff6ff}
  .ct-drop-formats{font-size:12px;color:#94a3b8}
  .ct-drop-formats strong{color:#2563eb;font-weight:600}
  .ct-file-input{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);border:0}

  .ct-file-chip{display:flex;align-items:center;gap:12px;margin-top:16px;padding:12px 14px;border:1px solid #e2e8f0;
                border-radius:11px}
  .ct-file-icon{display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;
                background:#dcfce7;color:#15803d;flex-shrink:0}
  .ct-file-text{display:flex;flex-direction:column;gap:2px;flex:1;min-width:0}
  .ct-file-name{font-size:13.5px;font-weight:600;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .ct-file-meta{font-size:11.5px;color:#94a3b8}
  .ct-file-ok{color:#16a34a;line-height:0;flex-shrink:0}
  .ct-file-remove{background:none;border:none;padding:2px;cursor:pointer;color:#64748b;line-height:0;border-radius:6px;flex-shrink:0}
  .ct-file-remove:hover{color:#0f172a;background:#f1f5f9}
  .ct-preview-summary{display:flex;gap:16px;flex-wrap:wrap;font-size:12.5px;color:#475569;margin-bottom:12px}
  .ct-preview-summary strong{color:#0f172a}
  .ct-preview-scroll{max-height:52vh;overflow:auto;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:14px}
  .ct-preview-table{width:100%;border-collapse:collapse;font-size:12px}
  /* Fixed layout so the columns are shared out rather than each one taking
     whatever its longest value asks for - an email is what used to push the
     table past the modal and force a sideways scroll. */
  .ct-preview-table{table-layout:fixed}
  .ct-preview-table th{position:sticky;top:0;background:#f8fafc;text-align:left;padding:8px 10px;font-weight:600;
                       color:#475569;border-bottom:1px solid #e2e8f0}
  .ct-preview-table td{padding:7px 10px;border-bottom:1px solid #f1f5f9;color:#0f172a;
                       overflow-wrap:anywhere}
  /* Row / Program / Year / Section / School Year hold short values; the room
     saved goes to the name, the email and what is about to happen. */
  .ct-preview-table th:nth-child(1),.ct-preview-table td:nth-child(1){width:46px}
  .ct-preview-table th:nth-child(4),.ct-preview-table td:nth-child(4){width:98px}
  .ct-preview-table th:nth-child(5),.ct-preview-table td:nth-child(5){width:72px}
  .ct-preview-table th:nth-child(6),.ct-preview-table td:nth-child(6){width:56px}
  .ct-preview-table th:nth-child(7),.ct-preview-table td:nth-child(7){width:68px}
  .ct-preview-table th:nth-child(8),.ct-preview-table td:nth-child(8){width:96px}
  .ct-preview-table th:nth-child(9),.ct-preview-table td:nth-child(9){width:150px}
  .ct-preview-table tr:last-child td{border-bottom:none}
  /* The badge is what a CaPSA scans the list by, so the three outcomes have
     to be tellable apart at a glance and not only by their wording. */
  .ct-tag{display:inline-block;padding:2px 8px;border-radius:6px;font-size:10.5px;font-weight:600;white-space:nowrap}
  .ct-tag-create{background:rgba(22,163,74,0.1);color:#15803d}
  .ct-tag-update{background:rgba(217,119,6,0.12);color:#b45309}
  .ct-tag-same{background:rgba(107,114,128,0.1);color:#64748b}
  .ct-preview-changes{color:#64748b;font-size:11px}
  /* Preview sits between Cancel and Save: offered, not insisted on. */
  .ct-btn-outline{background:#fff;color:#2563eb;border:1.5px solid #cbd5e1}
  .ct-btn-outline:hover:not(:disabled){border-color:#2563eb;background:#eff6ff}
  /* .ct-btn is flex:1, which shares a narrow dialog neatly and turns each
     button into half a wide one. On this step they sit at their own size. */
  .ct-modal-card-wide .ct-modal-actions{justify-content:flex-end}
  .ct-modal-card-wide .ct-modal-actions .ct-btn{flex:0 0 auto;min-width:160px}
  .ct-btn{flex:1;padding:10px 16px;font-size:13px;font-weight:600;border-radius:8px;border:none;cursor:pointer;transition:all 0.12s ease}
  .ct-btn-secondary{background:#f1f5f9;color:#475569}
  .ct-btn-secondary:hover{background:#e2e8f0;color:#1e293b}
  .ct-btn-primary{background:#2563eb;color:#fff}
  .ct-btn-primary:hover:not(:disabled){background:#1d4ed8}
  .ct-btn:disabled{background:#f1f5f9;color:#cbd5e1;cursor:not-allowed}
  @keyframes ctFadeIn{from{opacity:0}to{opacity:1}}
  @keyframes ctPopUp{from{transform:scale(0.94) translateY(8px);opacity:0}to{transform:scale(1) translateY(0);opacity:1}}
  #ct-toast-container{position:fixed;bottom:24px;right:24px;z-index:99999;display:flex;flex-direction:column;gap:10px;align-items:flex-end}
  .ct-toast{display:flex;align-items:flex-start;gap:12px;min-width:280px;max-width:360px;background:#fff;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.12),0 0 0 1px rgba(0,0,0,0.06);padding:14px 16px;animation:toastIn 0.25s cubic-bezier(0.16,1,0.3,1)}
  .ct-toast.hiding{opacity:0;transform:translateX(20px);transition:all .2s}
  .ct-toast-icon{width:36px;height:36px;flex-shrink:0;border-radius:8px;display:flex;align-items:center;justify-content:center}
  .ct-toast-icon.success{background:#dcfce7;color:#16a34a}
  .ct-toast-icon.error{background:#fee2e2;color:#dc2626}
  .ct-toast-body{flex:1}
  .ct-toast-title{font-size:13px;font-weight:700;color:#0f172a;margin:0}
  .ct-toast-msg{font-size:12px;color:#64748b;margin:2px 0 0}
  .ct-toast-close{background:none;border:none;color:#94a3b8;cursor:pointer;font-size:16px;line-height:1;padding:0}
  @keyframes toastIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}
</style>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
</head>
<body>

@php
  $viewerIsCapsa = (bool) Auth::guard('expert')->user()?->is_capsa;
@endphp

@include('capsa._sidebar')
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Student Section List</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
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
          @if ($viewerIsCapsa)
          <button type="button" class="btn" onclick="openStudentRosterModal()">Upload List</button>
          @endif
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
              <th>Year</th>
              <th>Section</th>
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
                {{-- year_section is stored joined - "4th · B" - because that is
                     the shape every section lookup matches on. It is shown apart
                     here: Program, Year and Section are three things a CaPSA
                     reads down a column, not one string to pick through. --}}
                @php
                  $ys = array_map('trim', explode('·', (string) $student->year_section));
                  $year = count($ys) === 2 ? $ys[0] : '';
                  $section = count($ys) === 2 ? $ys[1] : ($ys[0] ?? '');
                @endphp
                <td>{{ $year !== '' ? $year : '—' }}</td>
                <td>{{ $section !== '' ? $section : '—' }}</td>
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
                <td colspan="8" style="text-align:center;padding:40px 0;color:var(--text3);font-size:13px">
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
@include('capsa.partials.skeleton-toggle')

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

@if ($viewerIsCapsa)
{{-- ══════════════════════════════════════════
     UPLOAD STUDENT ROSTER — Modal
══════════════════════════════════════════ --}}
<div id="ct-toast-container"></div>
<div id="studentRosterModal" class="ct-modal-overlay">
  <div class="ct-modal-card ct-upload-card" id="studentRosterCard">
    <div class="ct-up-head">
      <span class="ct-up-head-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      </span>
      <span class="ct-up-head-text">
        <span class="ct-modal-title">Upload Student List</span>
        <span class="ct-up-sub">Upload a spreadsheet file containing your students.</span>
      </span>
      <button type="button" class="ct-up-close" onclick="closeStudentRosterModal()" aria-label="Close">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div id="studentRosterStep1">
      {{-- The template carries the exact columns this reads, so nobody has to
           type the headings by hand and get one subtly wrong. --}}
      <div class="ct-up-hint">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        <span>Need the correct format?</span>
        <a class="ct-template-link" href="{{ route('admin.rosters.students.template') }}">
          Download template
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        </a>
      </div>

      <div class="ct-dropzone" id="studentRosterDrop">
        <span class="ct-drop-icon">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        </span>
        <span class="ct-drop-title">Drag &amp; drop your file here</span>
        <span class="ct-drop-or">or</span>
        <button type="button" class="ct-drop-browse" onclick="document.getElementById('studentRosterFile').click()">Browse files</button>
        <span class="ct-drop-formats">Supported format: Microsoft <strong>Excel</strong> (.xlsx)</span>
      </div>

      {{-- Driven by the drop zone and the Browse button above, but still the
           one place the file lives - both Preview and Save read it here. --}}
      <input type="file" id="studentRosterFile" accept=".xlsx" class="ct-file-input" onchange="showStudentRosterFile()">

      <div class="ct-file-chip" id="studentRosterChip" style="display:none">
        <span class="ct-file-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="19"/><line x1="15" y1="13" x2="9" y2="19"/></svg>
        </span>
        <span class="ct-file-text">
          <span class="ct-file-name" id="studentRosterFileName"></span>
          <span class="ct-file-meta" id="studentRosterFileMeta"></span>
        </span>
        <span class="ct-file-ok">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="8.5 12.5 11 15 15.5 9.5"/></svg>
        </span>
        <button type="button" class="ct-file-remove" onclick="clearStudentRosterFile()" aria-label="Remove file">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <div id="studentRosterResult" style="display:none;font-size:12px;text-align:left;margin:4px 0 0;"></div>
      <div class="ct-modal-actions">
        <button type="button" class="ct-btn ct-btn-secondary" onclick="closeStudentRosterModal()">Cancel</button>
        <button type="button" id="studentRosterPreviewBtn" class="ct-btn ct-btn-outline" onclick="previewStudentRoster()">Preview</button>
        <button type="button" class="ct-btn ct-btn-primary" onclick="uploadStudentRoster(this)">Save to system</button>
      </div>
    </div>

    {{-- Nothing has been written at this point. The upload rewrites course,
         section and school year on every account it matches, and there is no
         undo, so the list is shown as it will be applied before it is. --}}
    <div id="studentRosterStep2" style="display:none">
      <div class="ct-preview-summary" id="studentRosterSummary"></div>
      <div class="ct-preview-scroll"><table class="ct-preview-table" id="studentRosterPreviewTable"></table></div>
      <div id="studentRosterErrors" style="display:none"></div>
      <div id="studentRosterSaveResult" style="display:none;font-size:12px;margin-bottom:12px"></div>
      <div class="ct-modal-actions">
        <button type="button" class="ct-btn ct-btn-secondary" onclick="backToStudentRosterFile()">Choose another file</button>
        <button type="button" id="studentRosterUploadBtn" class="ct-btn ct-btn-primary" onclick="uploadStudentRoster()">Save to system</button>
      </div>
    </div>
  </div>
</div>

<script>
function showToast(message, type = 'success') {
  const icons = {
    success: `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>`,
    error: `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`,
  };
  const toast = document.createElement('div');
  toast.className = 'ct-toast';
  toast.innerHTML = `
    <div class="ct-toast-icon ${type}">${icons[type] || icons.success}</div>
    <div class="ct-toast-body">
      <p class="ct-toast-title">${type === 'error' ? 'Error' : 'Success'}</p>
      <p class="ct-toast-msg">${message}</p>
    </div>
    <button class="ct-toast-close" onclick="this.closest('.ct-toast').remove()">&times;</button>
  `;
  document.getElementById('ct-toast-container').appendChild(toast);
  setTimeout(() => { toast.classList.add('hiding'); setTimeout(() => toast.remove(), 200); }, 4000);
}

const studentRosterModal = document.getElementById('studentRosterModal');

function openStudentRosterModal() {
  clearStudentRosterFile();
  backToStudentRosterFile();
  studentRosterModal.style.display = 'flex';
}

/** Nothing chosen: no file, no chip, nothing left over from last time. */
function clearStudentRosterFile() {
  document.getElementById('studentRosterFile').value = '';
  document.getElementById('studentRosterChip').style.display = 'none';
  document.getElementById('studentRosterResult').style.display = 'none';
}

/** Name the chosen file, so what is about to be read is never a guess. */
function showStudentRosterFile() {
  const file = document.getElementById('studentRosterFile').files[0];
  const chip = document.getElementById('studentRosterChip');

  if (!file) {
    chip.style.display = 'none';
    return;
  }

  const kb = file.size / 1024;
  const size = kb >= 1024 ? (kb / 1024).toFixed(1) + ' MB' : Math.max(1, Math.round(kb)) + ' KB';
  const type = (file.name.split('.').pop() || '').toUpperCase();

  document.getElementById('studentRosterFileName').textContent = file.name;
  document.getElementById('studentRosterFileMeta').textContent = size + ' \u2022 ' + type;
  document.getElementById('studentRosterResult').style.display = 'none';
  chip.style.display = 'flex';
}

// Dropping a file has to end up in the file input itself, because Preview and
// Save both read it from there - a dropped file held anywhere else would be
// invisible to them.
(function () {
  const zone = document.getElementById('studentRosterDrop');
  const input = document.getElementById('studentRosterFile');

  if (!zone || !input) return;

  ['dragenter', 'dragover'].forEach(name => zone.addEventListener(name, e => {
    e.preventDefault();
    zone.classList.add('dragging');
  }));

  ['dragleave', 'drop'].forEach(name => zone.addEventListener(name, e => {
    e.preventDefault();
    zone.classList.remove('dragging');
  }));

  zone.addEventListener('drop', e => {
    const file = e.dataTransfer.files[0];

    if (!file) return;

    const box = new DataTransfer();
    box.items.add(file);
    input.files = box.files;

    showStudentRosterFile();
  });

  // Outside the zone a dropped file would otherwise replace the whole page
  // with itself, losing whatever was on screen.
  ['dragover', 'drop'].forEach(name => window.addEventListener(name, e => {
    if (!zone.contains(e.target)) e.preventDefault();
  }));
})();

function closeStudentRosterModal() {
  studentRosterModal.style.display = 'none';
}

/** Back to choosing a file, and back to the narrow card that step needs. */
function backToStudentRosterFile() {
  document.getElementById('studentRosterStep1').style.display = '';
  document.getElementById('studentRosterStep2').style.display = 'none';
  document.getElementById('studentRosterCard').classList.remove('ct-modal-card-wide');
}

/** Text from the file, put on the page as text and never as markup. */
function escRoster(value) {
  const box = document.createElement('div');
  box.textContent = value == null ? '' : String(value);
  return box.innerHTML;
}

const ROSTER_ACTIONS = {
  create: ['ct-tag-create', 'New account'],
  update: ['ct-tag-update', 'Update'],
  unchanged: ['ct-tag-same', 'No change'],
};

/** What the file would do, read back and shown before anything is written. */
function previewStudentRoster() {
  const fileInput = document.getElementById('studentRosterFile');
  const resultBox = document.getElementById('studentRosterResult');

  if (!fileInput.files.length) {
    resultBox.style.display = 'block';
    resultBox.innerHTML = '<span style="color:#dc2626">Choose a file first.</span>';
    return;
  }

  const btn = document.getElementById('studentRosterPreviewBtn');
  btn.disabled = true;
  btn.textContent = 'Reading...';

  const formData = new FormData();
  formData.append('file', fileInput.files[0]);

  fetch('{{ route('admin.rosters.students.preview') }}', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    body: formData,
  })
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        resultBox.style.display = 'block';
        resultBox.innerHTML = `<span style="color:#dc2626">${escRoster(data.message || 'That file could not be read.')}</span>`;
        return;
      }

      renderRosterPreview(data);

      document.getElementById('studentRosterStep1').style.display = 'none';
      document.getElementById('studentRosterStep2').style.display = '';
      document.getElementById('studentRosterCard').classList.add('ct-modal-card-wide');
    })
    .catch(() => {
      resultBox.style.display = 'block';
      resultBox.innerHTML = '<span style="color:#dc2626">Could not read that file. Check the connection and try again.</span>';
    })
    .finally(() => {
      btn.disabled = false;
      btn.textContent = 'Preview';
    });
}

function renderRosterPreview(data) {
  const summary = [
    `<span><strong>${data.rows.length}</strong> row${data.rows.length === 1 ? '' : 's'} read</span>`,
    `<span><strong>${data.create}</strong> new account${data.create === 1 ? '' : 's'}</span>`,
    `<span><strong>${data.update}</strong> to update</span>`,
    `<span><strong>${data.unchanged}</strong> unchanged</span>`,
  ];
  if (data.skipped) summary.push(`<span style="color:#b91c1c"><strong>${data.skipped}</strong> skipped</span>`);
  document.getElementById('studentRosterSummary').innerHTML = summary.join('');

  const head = `<thead><tr><th>Row</th><th>Name</th><th>Email</th><th>Student No.</th>
    <th>Program</th><th>Year</th><th>Section</th><th>School Year</th><th>Action</th></tr></thead>`;

  const body = data.rows.map(row => {
    const [cls, label] = ROSTER_ACTIONS[row.action] || ROSTER_ACTIONS.unchanged;
    // Naming the fields is the point of the preview - "Update" alone does not
    // say that a re-upload is about to rewrite somebody's course.
    const changes = row.changes && row.changes.length
      ? `<div class="ct-preview-changes">${escRoster(row.changes.join(', '))}</div>`
      : '';

    return `<tr>
      <td>${escRoster(row.row)}</td>
      <td>${escRoster(row.name) || '&mdash;'}</td>
      <td>${escRoster(row.email)}</td>
      <td>${escRoster(row.student_number) || '&mdash;'}</td>
      <td>${escRoster(row.program) || '&mdash;'}</td>
      <td>${escRoster(row.year) || '&mdash;'}</td>
      <td>${escRoster(row.section) || '&mdash;'}</td>
      <td>${escRoster(row.school_year)}</td>
      <td><span class="ct-tag ${cls}">${label}</span>${changes}</td>
    </tr>`;
  }).join('');

  document.getElementById('studentRosterPreviewTable').innerHTML = head + '<tbody>' + body + '</tbody>';

  const errorBox = document.getElementById('studentRosterErrors');

  if (data.errors && data.errors.length) {
    errorBox.style.display = 'block';
    errorBox.innerHTML = '<div style="font-size:12px;color:#b91c1c;max-height:120px;overflow-y:auto;margin-bottom:12px">'
      + data.errors.map(e => `<div>${escRoster(e)}</div>`).join('') + '</div>';
  } else {
    errorBox.style.display = 'none';
    errorBox.innerHTML = '';
  }
}

function uploadStudentRoster(btn) {
  const fileInput = document.getElementById('studentRosterFile');
  // Saving is offered from both steps, so a failure has to be reported on
  // whichever one is on screen - the other one's box is hidden.
  const previewing = document.getElementById('studentRosterStep2').style.display !== 'none';
  const resultBox = document.getElementById(previewing ? 'studentRosterSaveResult' : 'studentRosterResult');

  if (!fileInput.files.length) {
    resultBox.style.display = 'block';
    resultBox.innerHTML = '<span style="color:#dc2626">Choose a file first.</span>';
    return;
  }

  btn = btn || document.getElementById('studentRosterUploadBtn');
  btn.disabled = true;
  btn.textContent = 'Saving...';

  const formData = new FormData();
  formData.append('file', fileInput.files[0]);

  fetch('{{ route('admin.rosters.students.store') }}', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    body: formData,
  })
    .then(r => r.json())
    .then(data => {
      resultBox.style.display = 'block';

      if (!data.success) {
        resultBox.innerHTML = `<span style="color:#dc2626">${data.message || 'Upload failed.'}</span>`;
        return;
      }

      let html = `<strong>${data.inserted}</strong> read, <strong>${data.skipped}</strong> skipped.`;
      if (data.created) {
        html += `<div style="margin-top:6px;color:#15803d"><strong>${data.created}</strong> account${data.created === 1 ? '' : 's'} prepared for students who have not signed in yet.</div>`;
      }
      if (data.filled) {
        html += `<div style="margin-top:4px;color:#15803d"><strong>${data.filled}</strong> blank field${data.filled === 1 ? '' : 's'} filled in on ${data.matched} existing account${data.matched === 1 ? '' : 's'}.</div>`;
      }
      if (data.already_complete) {
        html += `<div style="margin-top:4px;color:var(--text3)">${data.already_complete} already had everything &mdash; nothing on those was changed.</div>`;
      }
      if (data.errors && data.errors.length) {
        html += '<div style="max-height:160px;overflow-y:auto;color:var(--text3);margin-top:6px">' +
          data.errors.map(e => `<div>${e}</div>`).join('') + '</div>';
      }
      resultBox.innerHTML = html;
      showToast('Student list uploaded.', 'success');
      setTimeout(() => window.location.reload(), 1200);
    })
    .catch(() => {
      resultBox.style.display = 'block';
      resultBox.innerHTML = '<span style="color:#dc2626">Network error while uploading.</span>';
    })
    .finally(() => {
      btn.disabled = false;
      btn.textContent = 'Upload';
    });
}
</script>
@endif

</body>
</html>
