<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Student Section List - CapsTRACK Admin</title>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<style>
  /* A school address box: the domain sits in grey at the right end, so only
     the name part is typed. Typing or pasting a whole address hides it. */
  .email-field{position:relative}
  .email-field .input{padding-right:136px}
  .email-field.has-at .input{padding-right:14px}
  .email-domain{position:absolute;right:14px;top:0;bottom:0;display:flex;align-items:center;
    font-size:14px;color:var(--text3);pointer-events:none;white-space:nowrap}
  .email-field.has-at .email-domain{display:none}
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
  .ct-preview-scroll{max-height:52vh;max-height:52dvh;overflow:auto;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:14px}
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
  .ct-tag-promote{background:rgba(37,99,235,0.1);color:#1d4ed8}
  .ct-promo{border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;margin-bottom:12px;background:#f8fafc}
  .ct-promo-title{font-size:13px;font-weight:700;color:#0f172a;margin-bottom:2px}
  .ct-promo-sub{font-size:12px;color:#64748b;margin-bottom:10px}
  .ct-promo-row{display:flex;flex-wrap:wrap;align-items:center;gap:6px 14px;padding:8px 0;border-top:1px solid #e2e8f0;font-size:12.5px;color:#0f172a}
  .ct-promo-row:first-of-type{border-top:0}
  .ct-promo-move{font-weight:600;min-width:190px}
  .ct-promo-row label{display:flex;align-items:center;gap:7px;cursor:pointer}
  .ct-promo-row input{accent-color:#0f172a;width:15px;height:15px;margin:0}
  .ct-promo-note{color:#64748b;font-size:12px}
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
  <div class="content list-page">
    {{-- What the page holds on the left, the ways in on the right. The count
         used to be a card title with the sections underneath in grey, which
         said the same thing in two lines and left the buttons sharing a row
         with a filter. --}}
@php
      // The one number a CaPSA is looking for on this page that the list
      // itself will not tell them without scrolling all of it.
      $unteamed = $students->filter(fn ($s) => ! ($s->currentSy && $s->currentSy->team))->count();
    @endphp
    <div class="page-head">
      <div class="page-head-text">
        <span class="page-count">{{ $students->count() }}</span>
        <span class="page-count-noun">{{ Str::plural('student', $students->count()) }}</span>
        <span class="page-facts">
          @foreach ($adminSections as $label)
            <span class="fact">{{ $label }}</span>
          @endforeach
          @if ($unteamed)
            <span class="fact fact-warn">{{ $unteamed }} without a team</span>
          @endif
        </span>
      </div>
    </div>

    {{-- Everything you do to the list on one line: look for someone, narrow
         it down, or add to it. They were on two, which put a button directly
         above a dropdown it had nothing to do with. --}}
    <div class="page-filters">
      <div class="experts-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input class="input" id="student-search" placeholder="Search by name, email or student number..." oninput="filterStudents()"/>
      </div>
      <form method="get" id="sectionFilterForm">
        <select class="input page-filter" name="section" id="section" onchange="document.getElementById('sectionFilterForm').submit()">
          <option value="">All my sections</option>
          @foreach ($allSections as $section)
            <option value="{{ $section }}" {{ request()->query('section') == $section ? 'selected' : '' }}>{{ $section }}</option>
          @endforeach
        </select>
      </form>
      {{-- The section filter reloads because the server decides which students
           are yours. These two only hide rows already on the page, so they do
           not - a CaPSA flipping between programs would otherwise wait for a
           round trip each time. --}}
      <select class="input page-filter" id="program-filter" onchange="filterStudents()">
        <option value="">All programs</option>
        @foreach ($students->pluck('programAbbr')->filter()->unique()->sort() as $abbr)
          <option value="{{ $abbr }}">{{ $abbr }}</option>
        @endforeach
      </select>
      <select class="input page-filter" id="team-filter" onchange="filterStudents()">
        <option value="">All students</option>
        <option value="0">Without a team</option>
        <option value="1">In a team</option>
      </select>
      @if ($viewerIsCapsa)
        <span class="page-filters-actions">
          <button type="button" class="btn" onclick="openStudentRosterModal()">Upload List</button>
          <button type="button" class="btn" onclick="openYearPromotion()">Year Promotion</button>
          <button type="button" class="btn btn-primary" onclick="openAddStudent()">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Student
          </button>
        </span>
      @endif
    </div>

    @if ($viewerIsCapsa)
      @include('partials.row-select', [
        'table' => '.table',
        'noun' => 'student',
        'actions' => [
          ['id' => 'bulkProgram', 'label' => 'Set program'],
          ['id' => 'bulkSection', 'label' => 'Set year & section'],
          ['id' => 'bulkRemove', 'label' => 'Remove from section', 'danger' => true],
        ],
      ])
    @endif

    <div class="card list-card">

      <div>
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
              {{-- The unique key first: a student number is the one thing on
                   the row that names exactly one person. Two students share a
                   surname often enough that a name column cannot. --}}
              <th style="padding-left:20px">Student no.</th>
              <th>Student</th>
              <th>Section</th>
              <th>Team</th>
              <th>Status</th>
              @if ($viewerIsCapsa)<th style="text-align:right;padding-right:20px">Action</th>@endif
            </tr>
          </thead>
          <tbody>
            @forelse ($students as $index => $student)
              @php
                $sy = $student->currentSy;
                // year_section is stored joined - "4th | B" - because that is
                // the shape every section lookup matches on. Taken apart up
                // here, because the row's own attributes need it before the
                // Year and Section cells do.
                $ys = array_map('trim', explode('·', (string) $student->year_section));
                $year = count($ys) === 2 ? $ys[0] : '';
                $section = count($ys) === 2 ? $ys[1] : ($ys[0] ?? '');
              @endphp
              <tr data-id="{{ $student->student_id }}"
                  data-program="{{ $student->programAbbr }}"
                  data-year="{{ preg_replace('/[^0-9]/', '', $year) }}"
                  data-letter="{{ strtoupper($section) }}"
                  data-team="{{ $sy && $sy->team ? '1' : '0' }}"
                  data-search="{{ Str::lower($student->f_name.' '.$student->l_name.' '.$student->email.' '.$student->student_number) }}">
                <td class="std-mono" style="padding-left:20px" data-label="Student no.">{{ $student->student_number ?: '—' }}</td>
                <td data-label="Student">
                  {{-- The address under the name rather than nowhere: two
                       students share a surname often enough that it is what
                       tells them apart. --}}
                  <span class="std-stack">
                    <span class="std-name">{{ $student->l_name }}, {{ $student->f_name }} {{ $student->m_name ? $student->m_name[0] . '.' : '' }}</span>
                    <span class="std-mail">{{ $student->email }}</span>
                  </span>
                </td>
                {{-- One column, the way a section is written everywhere else
                     in the app - BSIT-4A. Split across three it read as three
                     unrelated facts, and no other screen names a section that
                     way, so the same student looked different here. --}}
                <td class="std-mono" data-label="Section">{{ \App\Models\Student::computeSectionLabel($student->course, $student->year_section) ?? '—' }}</td>
                <td data-label="Team">
                  @if ($sy && $sy->team)
                    {{ $sy->team->team_name ?? 'Team #' . $sy->team->team_id }}
                  @else
                    <span style="color:var(--text3)">—</span>
                  @endif
                </td>
                <td data-label="Status">
                  {{-- A student added by email exists before they ever sign
                       in. Without a word for that the row looks broken rather
                       than waiting. --}}
                  @if ($student->last_seen_at)
                    <span class="std-tag std-tag-on">Enrolled</span>
                  @else
                    <span class="std-tag std-tag-wait">Not signed in yet</span>
                  @endif
                </td>
                @if ($viewerIsCapsa)
                  <td style="text-align:right;padding-right:20px" data-label="Action">
                    @php
                      // Built above the tag: a multi-line @json inside an
                      // attribute is a parse error, and the message points at
                      // the bracket rather than at the reason.
                      $editPayload = [
                        'id' => $student->student_id,
                        'f_name' => $student->f_name,
                        'm_name' => $student->m_name,
                        'l_name' => $student->l_name,
                        'student_number' => $student->student_number,
                        'course' => $student->programAbbr,
                        'year' => $year,
                        'section' => $section,
                        'email' => $student->email,
                      ];
                    @endphp
                    <div class="rowmenu">
                      <button type="button" class="icon-btn rowmenu-btn" aria-expanded="false"
                              aria-label="Actions for {{ $student->f_name }} {{ $student->l_name }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.7" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.7" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.7" fill="currentColor" stroke="none"/></svg>
                      </button>
                      {{-- Always about this one student, ticked or not. Setting a
                           program or a year for several at once is the bar's job;
                           a name and a student number are one person's. --}}
                      <div class="rowmenu-pop" hidden>
                        <button type="button" class="rowmenu-item"
                                onclick="openEditStudent({{ Illuminate\Support\Js::from($editPayload) }})">
                          <svg viewBox="0 0 24 24"><path d="M11 4H4v16h16v-7"/><path d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4z"/></svg> Edit student details
                        </button>
                        <div class="rowmenu-sep"></div>
                        <button type="button" class="rowmenu-item danger"
                                onclick="removeOneStudent({{ Illuminate\Support\Js::from($editPayload) }})">
                          <svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M7 6l1 14h8l1-14"/></svg> Remove from section
                        </button>
                      </div>
                    </div>
                  </td>
                @endif
              </tr>
            @empty
              <tr>
                <td colspan="6" style="text-align:center;padding:40px 0;color:var(--text3);font-size:13px">
                  @if (empty($adminSections))
                    No sections assigned yet. <a href="{{ route('capsa.profile') }}" style="color:var(--accent)">Set your sections</a> to view students.
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
        <span class="ct-modal-title" id="studentRosterTitle">Upload Student List</span>
        <span class="ct-up-sub" id="studentRosterSub">Upload a spreadsheet file containing your students.</span>
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
        <a class="ct-template-link" href="{{ route('capsa.rosters.students.template') }}">
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
      {{-- Year Promotion: who moves up a year, by section, and whether this
           CaPSA stays on with them. A CaPSA can change in 4th year, so it is
           a choice, not a given. --}}
      <div class="ct-promo" id="studentRosterPromotions" style="display:none"></div>
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

{{-- ══════════════════════════════════════════
     YEAR PROMOTION — pick sections and move them up a year, or upload the
     new school year's list. Either way accounts, teams and work stay.
══════════════════════════════════════════ --}}
<div id="yearPromotionModal" class="ct-modal-overlay">
  <div class="ct-modal-card ct-upload-card ct-modal-card-wide" style="max-width:760px">
    <div class="ct-up-head">
      <span class="ct-up-head-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
      </span>
      <span class="ct-up-head-text">
        <span class="ct-modal-title">Year Promotion</span>
        <span class="ct-up-sub">Move your students up a year: 3rd year to 4th. Their accounts, teams and work stay; only the year on each student changes.</span>
      </span>
      <button type="button" class="ct-up-close" onclick="closeYearPromotion()" aria-label="Close">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="ct-up-hint">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
      <span>Have the new school year's class list? It carries each student's year.</span>
      <a class="ct-template-link" href="#" onclick="closeYearPromotion(); openStudentRosterModal('promotion'); return false;">Upload it instead</a>
    </div>
    <div id="ypBody" class="ct-preview-scroll" style="max-height:48vh;max-height:48dvh;padding:4px 14px;border-radius:10px"><p class="ct-promo-note" style="padding:12px 0">Loading your sections…</p></div>
    <div id="ypResult" style="display:none;font-size:12.5px;margin-bottom:12px"></div>
    <div class="ct-modal-actions">
      <button type="button" class="ct-btn ct-btn-secondary" onclick="closeYearPromotion()">Cancel</button>
      <button type="button" id="ypGo" class="ct-btn ct-btn-primary" disabled onclick="confirmYearPromotion()">Move up</button>
    </div>
  </div>
</div>

<script>
let ypData = [];
let ypConfirming = false;

function openYearPromotion() {
  ypConfirming = false;
  document.getElementById('ypResult').style.display = 'none';
  document.getElementById('ypBody').innerHTML = '<p class="ct-promo-note" style="padding:12px 0">Loading your sections…</p>';
  document.getElementById('yearPromotionModal').style.display = 'flex';
  fetch('{{ route('capsa.students.promotion') }}', { headers: { 'Accept': 'application/json' } })
    .then(r => r.json())
    .then(data => { ypData = data.sections || []; renderYearPromotion(); })
    .catch(() => { document.getElementById('ypBody').innerHTML = '<p style="color:#dc2626;padding:12px 0">Could not load your sections. Try again.</p>'; });
}

function closeYearPromotion() {
  document.getElementById('yearPromotionModal').style.display = 'none';
}

function renderYearPromotion() {
  const body = document.getElementById('ypBody');
  if (!ypData.length) {
    body.innerHTML = '<p class="ct-promo-note" style="padding:12px 0">None of your sections has students below 4th year, so there is nobody to move up.</p>';
    updateYearPromotionButton();
    return;
  }

  body.innerHTML = ypData.map((p, i) => {
    const stay = p.already_mine
      ? `<span class="ct-promo-note">You already cover ${escRoster(p.to)}.</span>`
      : p.covered_by
        ? `<span class="ct-promo-note">${escRoster(p.to)} is covered by ${escRoster(p.covered_by)}.</span>`
        : `<label><input type="checkbox" class="yp-stay" data-to="${escRoster(p.to)}" checked>Stay their CaPSA in ${escRoster(p.to)}</label>`;
    const people = p.students.map(s => `<label style="font-weight:400"><input type="checkbox" class="yp-student" data-section="${i}" value="${s.id}" checked>${escRoster(s.name)}${s.team ? ` <span class="ct-promo-note">· ${escRoster(s.team)}</span>` : ''}</label>`).join('');
    return `<div class="ct-promo-row" style="display:block">
      <div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px 14px">
        <label class="ct-promo-move"><input type="checkbox" class="yp-section" value="${escRoster(p.from)}" data-i="${i}">${escRoster(p.from)} → ${escRoster(p.to)} · ${p.students.length} student${p.students.length === 1 ? '' : 's'}</label>
        ${stay}
        <button type="button" class="ct-btn ct-btn-secondary" style="flex:0 0 auto;width:auto;padding:3px 10px;font-size:12px;margin-left:auto" onclick="const l = this.closest('.ct-promo-row').querySelector('.yp-people'); l.style.display = l.style.display === 'none' ? 'grid' : 'none';">Students</button>
      </div>
      <div class="yp-people" style="display:none;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:6px 14px;padding:10px 0 4px 22px">${people}
        <p class="ct-promo-note" style="grid-column:1/-1;margin:0">Untick anyone who is not moving up, such as a student repeating the year.</p>
      </div>
    </div>`;
  }).join('');

  body.querySelectorAll('input').forEach(i => i.addEventListener('change', () => { ypConfirming = false; updateYearPromotionButton(); }));
  updateYearPromotionButton();
}

function ypCount() {
  const picked = [...document.querySelectorAll('.yp-section:checked')].map(b => b.dataset.i);
  return [...document.querySelectorAll('.yp-student:checked')].filter(b => picked.includes(b.dataset.section)).length;
}

function updateYearPromotionButton() {
  const btn = document.getElementById('ypGo');
  const n = ypCount();
  btn.disabled = n === 0;
  btn.textContent = ypConfirming ? `Yes, move ${n} up` : (n ? `Move ${n} student${n === 1 ? '' : 's'} up` : 'Move up');
}

/** Two clicks: the first says what is about to happen, the second does it. */
function confirmYearPromotion() {
  const result = document.getElementById('ypResult');
  if (!ypConfirming) {
    ypConfirming = true;
    result.style.display = 'block';
    result.innerHTML = `<span style="color:#b45309">This changes the year on ${ypCount()} student${ypCount() === 1 ? '' : 's'} and there is no undo. Click again to go ahead.</span>`;
    updateYearPromotionButton();
    return;
  }

  const sections = [...document.querySelectorAll('.yp-section:checked')];
  const form = new FormData();
  sections.forEach(b => form.append('sections[]', b.value));
  document.querySelectorAll('.yp-student:not(:checked)').forEach(b => form.append('exclude[]', b.value));
  sections.forEach(b => {
    const stay = b.closest('.ct-promo-row').querySelector('.yp-stay:checked');
    if (stay) form.append('stay_on[]', stay.dataset.to);
  });

  const btn = document.getElementById('ypGo');
  btn.disabled = true;
  btn.textContent = 'Moving up…';
  fetch('{{ route('capsa.students.promotion.store') }}', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    body: form,
  })
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        result.innerHTML = `<span style="color:#dc2626">${escRoster(data.message || 'Could not move them up.')}</span>`;
        ypConfirming = false;
        updateYearPromotionButton();
        return;
      }
      result.innerHTML = `<span style="color:#15803d">${escRoster(data.message)}${data.kept_sections && data.kept_sections.length ? ' You now also cover ' + data.kept_sections.map(escRoster).join(', ') + '.' : ''}</span>`;
      showToast(data.message, 'success');
      setTimeout(() => window.location.reload(), 1200);
    })
    .catch(() => {
      result.innerHTML = '<span style="color:#dc2626">Network error. Check the connection and try again.</span>';
      ypConfirming = false;
      updateYearPromotionButton();
    });
}

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

/**
 * The same upload does both jobs. Year Promotion is an upload of the new
 * school year's list: the year and section each student is in come from the
 * list, their account, team and work stay, and the preview says who moves up.
 */
let rosterMode = 'upload';

function openStudentRosterModal(mode) {
  rosterMode = mode === 'promotion' ? 'promotion' : 'upload';
  document.getElementById('studentRosterTitle').textContent = rosterMode === 'promotion' ? 'Year Promotion' : 'Upload Student List';
  document.getElementById('studentRosterSub').textContent = rosterMode === 'promotion'
    ? 'Upload the class list for the new school year. Each student moves to the year and section on the list; their account, team and work stay.'
    : 'Upload a spreadsheet file containing your students.';
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

  fetch('{{ route('capsa.rosters.students.preview') }}', {
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
  const moving = (data.promotions || []).reduce((n, p) => n + p.students, 0);
  if (moving) summary.splice(3, 0, `<span><strong>${moving}</strong> moving up a year</span>`);
  document.getElementById('studentRosterSummary').innerHTML = summary.join('');
  renderPromotions(data.promotions || []);

  const head = `<thead><tr><th>Row</th><th>Name</th><th>Email</th><th>Student No.</th>
    <th>Program</th><th>Year</th><th>Section</th><th>School Year</th><th>Action</th></tr></thead>`;

  const body = data.rows.map(row => {
    const [cls, label] = row.promotion
      ? ['ct-tag-promote', `Moves up: ${row.promotion.from} → ${row.promotion.to}`]
      : (ROSTER_ACTIONS[row.action] || ROSTER_ACTIONS.unchanged);
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

  const corrected = (data.corrected || []).length
    ? '<div style="font-size:12px;color:#15803d;max-height:120px;overflow-y:auto;margin-bottom:12px">'
      + data.corrected.map(e => `<div>${escRoster(e)}</div>`).join('') + '</div>'
    : '';

  if (corrected || (data.errors && data.errors.length)) {
    errorBox.style.display = 'block';
    errorBox.innerHTML = corrected + ((data.errors || []).length
      ? '<div style="font-size:12px;color:#b91c1c;max-height:120px;overflow-y:auto;margin-bottom:12px">'
        + data.errors.map(e => `<div>${escRoster(e)}</div>`).join('') + '</div>'
      : '');
  } else {
    errorBox.style.display = 'none';
    errorBox.innerHTML = '';
  }
}

function renderPromotions(promotions) {
  const box = document.getElementById('studentRosterPromotions');

  if (!promotions.length) {
    box.style.display = rosterMode === 'promotion' ? 'block' : 'none';
    box.innerHTML = '<div class="ct-promo-title">Nobody moves up a year</div>'
      + '<div class="ct-promo-sub" style="margin:0">No student on this list is in a higher year than their account says. Check that this is the new school year\'s list.</div>';
    return;
  }

  const rows = promotions.map(p => {
    const move = `<span class="ct-promo-move">${escRoster(p.from)} → ${escRoster(p.to)} · ${p.students} student${p.students === 1 ? '' : 's'}</span>`;
    let choice;
    if (p.already_mine) {
      choice = `<span class="ct-promo-note">You already cover ${escRoster(p.to)}.</span>`;
    } else if (p.covered_by) {
      choice = `<span class="ct-promo-note">${escRoster(p.to)} is covered by ${escRoster(p.covered_by)}. Ask the Admin to change it.</span>`;
    } else if (!p.mine) {
      choice = `<span class="ct-promo-note">Not your section now; nobody covers ${escRoster(p.to)} yet.</span>`;
    } else {
      choice = `<label><input type="checkbox" name="stay_on" value="${escRoster(p.to)}" checked>Stay their CaPSA in ${escRoster(p.to)}</label>`;
    }
    return `<div class="ct-promo-row">${move}${choice}</div>`;
  }).join('');

  box.style.display = 'block';
  box.innerHTML = '<div class="ct-promo-title">Moving up a year</div>'
    + '<div class="ct-promo-sub">Their teams and work stay. Untick a section if another CaPSA will take it in the new year.</div>' + rows;
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
  // Only what was on screen and ticked: saving from the first step keeps nobody on a new section.
  if (previewing) {
    document.querySelectorAll('#studentRosterPromotions input[name="stay_on"]:checked')
      .forEach(box => formData.append('stay_on[]', box.value));
  }

  fetch('{{ route('capsa.rosters.students.store') }}', {
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
      if (data.corrected && data.corrected.length) {
        html += '<div style="max-height:160px;overflow-y:auto;color:#15803d;margin-top:6px">' +
          data.corrected.map(e => `<div>${escRoster(e)}</div>`).join('') + '</div>';
      }
      if (data.errors && data.errors.length) {
        html += '<div style="max-height:160px;overflow-y:auto;color:var(--text3);margin-top:6px">' +
          data.errors.map(e => `<div>${e}</div>`).join('') + '</div>';
      }
      if (data.promoted) {
        html += `<div style="margin-top:6px;color:#1d4ed8"><strong>${data.promoted}</strong> student${data.promoted === 1 ? '' : 's'} moved up a year.`
          + (data.kept_sections && data.kept_sections.length ? ` You now also cover ${data.kept_sections.map(escRoster).join(', ')}.` : '') + '</div>';
      }
      resultBox.innerHTML = html;
      showToast(data.promoted ? 'Students moved up a year.' : 'Student list uploaded.', 'success');
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


{{-- ══ Add a student by email ══
     For the one the roster upload missed. The account is a placeholder until
     they sign in with this address, which is why nothing here asks for a
     password. --}}
<div class="upload-scrim" id="add-student-scrim" hidden onclick="closeAddStudent(event)">
  <div class="upload-sheet" onclick="event.stopPropagation()">
    <div class="card" style="padding:16px;box-shadow:none;border:0">
      <div class="card-header sheet-head">
        <span class="card-title">Add a student</span>
        <span class="sheet-head-right">
          <button class="sheet-x" onclick="closeAddStudent()" aria-label="Close">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </span>
      </div>

      <div class="form-group">
        <label class="label" for="add-student-email">Email address</label>
        <input class="input" id="add-student-email" type="text" inputmode="email" autocapitalize="off" spellcheck="false" placeholder="name" autocomplete="off">
        <p class="section-hint" id="add-student-hint">They sign in with this address, so it has to be the one the college gave them.</p>
      </div>

      <div class="sheet-row">
        <div class="form-group sheet-col"><label class="label" for="add-student-first">First name</label>
          <input class="input" id="add-student-first" autocomplete="off"></div>
        <div class="form-group sheet-col"><label class="label" for="add-student-middle">Middle name</label>
          <input class="input" id="add-student-middle" placeholder="optional" autocomplete="off"></div>
        <div class="form-group sheet-col"><label class="label" for="add-student-last">Last name</label>
          <input class="input" id="add-student-last" autocomplete="off"></div>
      </div>

      <div class="sheet-row">
        <div class="form-group sheet-col"><label class="label" for="add-student-number">Student no.</label>
          <input class="input" id="add-student-number" placeholder="e.g. 221001135" required aria-required="true" autocomplete="off"></div>
      </div>

      <div class="sheet-row">
        <div class="form-group sheet-col"><label class="label" for="add-student-course">Program</label>
          <select class="input" id="add-student-course">
            <option value="BSIT">BSIT</option>
            <option value="BSCS">BSCS</option>
            <option value="BSIS">BSIS</option>
            <option value="BLIS">BLIS</option>
          </select></div>
        <div class="form-group sheet-col"><label class="label" for="add-student-year">Year</label>
          <select class="input" id="add-student-year">
            <option value="3">3</option><option value="4" selected>4</option>
          </select></div>
        <div class="form-group sheet-col"><label class="label" for="add-student-section">Section</label>
          <input class="input" id="add-student-section" placeholder="A" maxlength="4"></div>
      </div>

      <p class="section-hint" id="add-student-error" style="color:var(--red);display:none"></p>

      <button class="btn btn-primary" id="add-student-btn" style="width:100%;justify-content:center;font-size:13px;margin-top:4px"
              onclick="saveNewStudent()">Add student</button>
    </div>
  </div>
</div>

{{-- ══ Edit one student ══
     Bulk handles what a group shares. This is the only place a name, an
     address or a number can be changed, because those are one per person. --}}
<div class="upload-scrim" id="edit-student-scrim" hidden onclick="closeEditStudent(event)">
  <div class="upload-sheet" onclick="event.stopPropagation()">
    <div class="card" style="padding:16px;box-shadow:none;border:0">
      <div class="card-header sheet-head">
        <span class="card-title" id="edit-student-title">Edit student</span>
        <span class="sheet-head-right">
          <button class="sheet-x" onclick="closeEditStudent()" aria-label="Close">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </span>
      </div>

      <div class="form-group">
        <label class="label" for="edit-student-email">Email address</label>
        <input class="input" id="edit-student-email" type="text" inputmode="email" autocapitalize="off" spellcheck="false" placeholder="name" autocomplete="off" aria-describedby="edit-student-email-hint">
        <p class="section-hint" id="edit-student-email-hint" style="margin:4px 0 0">The school Google account they sign in with.</p>
      </div>

      <div class="sheet-row">
        <div class="form-group sheet-col"><label class="label" for="edit-student-first">First name</label>
          <input class="input" id="edit-student-first"></div>
        <div class="form-group sheet-col"><label class="label" for="edit-student-middle">Middle name</label>
          <input class="input" id="edit-student-middle" placeholder="optional"></div>
        <div class="form-group sheet-col"><label class="label" for="edit-student-last">Last name</label>
          <input class="input" id="edit-student-last"></div>
      </div>

      <div class="sheet-row">
        <div class="form-group sheet-col"><label class="label" for="edit-student-number">Student no.</label>
          <input class="input" id="edit-student-number" placeholder="e.g. 221001135" required aria-required="true"></div>
      </div>

      <div class="sheet-row">
        <div class="form-group sheet-col"><label class="label" for="edit-student-course">Program</label>
          <select class="input" id="edit-student-course">
            <option value="BSIT">BSIT</option>
            <option value="BSCS">BSCS</option>
            <option value="BSIS">BSIS</option>
            <option value="BLIS">BLIS</option>
          </select></div>
        <div class="form-group sheet-col"><label class="label" for="edit-student-year">Year</label>
          <select class="input" id="edit-student-year">
            <option value="3">3</option><option value="4">4</option>
          </select></div>
        <div class="form-group sheet-col"><label class="label" for="edit-student-section">Section</label>
          <input class="input" id="edit-student-section" maxlength="4"></div>
      </div>

      <p class="section-hint" id="edit-student-error" style="color:var(--red);display:none"></p>

      <button class="btn btn-primary" id="edit-student-btn" style="width:100%;justify-content:center;font-size:13px;margin-top:4px"
              onclick="saveEditStudent()">Save changes</button>
    </div>
  </div>
</div>

{{-- ══ set a program for several students ══ --}}
<div class="upload-scrim" id="bulk-program-scrim" hidden onclick="closeBulkProgram(event)">
  <div class="upload-sheet bulk-sheet" onclick="event.stopPropagation()">
    <div class="card" style="padding:16px;box-shadow:none;border:0">
      <div class="card-header sheet-head">
        <span class="card-title" id="bulk-program-title">Set program</span>
        <span class="sheet-head-right">
          <button class="sheet-x" type="button" onclick="closeBulkProgram()" aria-label="Close">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </span>
      </div>

      <div class="cf-names" id="bulk-program-names"></div>

      <div class="form-group">
        <label class="label" for="bulk-program-value">Program</label>
        <select class="input" id="bulk-program-value" onchange="previewBulkProgram()">
          <option value="BSIT">BSIT</option>
          <option value="BSCS">BSCS</option>
          <option value="BSIS">BSIS</option>
          <option value="BLIS">BLIS</option>
        </select>
      </div>

      {{-- The program is half of the section label, so changing it moves them
           between sections. Better said here than discovered after. --}}
      <p class="bulk-result" id="bulk-program-result"></p>
      <p class="bulk-error" id="bulk-program-error"></p>

      <div class="cf-actions" style="margin-top:16px">
        <button type="button" class="btn" onclick="closeBulkProgram()">Cancel</button>
        <button type="button" class="btn btn-primary" id="bulk-program-btn" onclick="saveBulkProgram()">Save</button>
      </div>
    </div>
  </div>
</div>

{{-- ══ set a year and section for several students ══ --}}
<div class="upload-scrim" id="bulk-section-scrim" hidden onclick="closeBulkSection(event)">
  <div class="upload-sheet bulk-sheet" onclick="event.stopPropagation()">
    <div class="card" style="padding:16px;box-shadow:none;border:0">
      <div class="card-header sheet-head">
        <span class="card-title" id="bulk-section-title">Set year &amp; section</span>
        <span class="sheet-head-right">
          <button class="sheet-x" type="button" onclick="closeBulkSection()" aria-label="Close">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </span>
      </div>

      <div class="cf-names" id="bulk-section-names"></div>

      @php
        // Taken apart from the sections this CaPSA actually handles - the
        // endpoint refuses a move into anyone else's, so offering one here
        // would be offering a choice that comes back as an error.
        $myYears = [];
        $myLetters = [];
        foreach ($adminSections as $label) {
          if (preg_match('/-(\d+)([A-Za-z]+)$/', $label, $m)) {
            $myYears[(int) $m[1]] = true;
            $myLetters[strtoupper($m[2])] = true;
          }
        }
        $myYears = array_keys($myYears);
        $myLetters = array_keys($myLetters);
        sort($myYears);
        sort($myLetters);
        $ordinal = fn (int $n) => $n.match ($n) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' };
      @endphp
      <div class="sheet-row">
        <div class="form-group sheet-col">
          <label class="label" for="bulk-section-year">Year</label>
          <select class="input" id="bulk-section-year" onchange="previewBulkSection()">
            @forelse ($myYears as $y)
              <option value="{{ $y }}">{{ $ordinal($y) }}</option>
            @empty
              <option value="3">3rd</option><option value="4">4th</option>
            @endforelse
          </select>
        </div>
        <div class="form-group sheet-col">
          <label class="label" for="bulk-section-letter">Section</label>
          <select class="input" id="bulk-section-letter" onchange="previewBulkSection()">
            @forelse ($myLetters as $letter)
              <option value="{{ $letter }}">{{ $letter }}</option>
            @empty
              <option value="A">A</option><option value="B">B</option>
            @endforelse
          </select>
        </div>
      </div>

      {{-- Two prompts in a row could not do this: say what the change works
           out to, and whether it leaves them in a section you handle. --}}
      <p class="bulk-result" id="bulk-section-result"></p>
      <p class="bulk-error" id="bulk-section-error"></p>

      <div class="cf-actions" style="margin-top:16px">
        <button type="button" class="btn" onclick="closeBulkSection()">Cancel</button>
        <button type="button" class="btn btn-primary" id="bulk-section-btn" onclick="saveBulkSection()">Save</button>
      </div>
    </div>
  </div>
</div>

@include('partials.app-dialog')
@include('partials.row-menu')
@include('partials.email-lookup', [
    'input' => 'add-student-email',
    'kind' => 'student',
    'fields' => [
        'first_name' => 'add-student-first',
        'middle_name' => 'add-student-middle',
        'last_name' => 'add-student-last',
        'student_number' => 'add-student-number',
    ],
])

<script>
// ══ the CaPSA's own students ══
// Every call here goes to one of three routes: post to the collection to take
// somebody on, patch a record to change part of it, delete to take them off
// the roster. Bulk is a loop over the patch, so the rule about which sections
// are yours is written once on the server and cannot drift.

const PROGRAMS = ['BSIT', 'BSCS', 'BSIS', 'BLIS'];

const STUDENT_ROUTES = {
  store: "{{ route('capsa.students.store') }}",
  one: (id) => `{{ url('capsa/students') }}/${id}`,
};

function headers() {
  return {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-CSRF-TOKEN': '{{ csrf_token() }}',
  };
}

// ── search ──
function filterStudents() {
  const q = document.getElementById('student-search').value.trim().toLowerCase();
  const program = document.getElementById('program-filter').value;
  const team = document.getElementById('team-filter').value;

  document.querySelectorAll('.table tbody tr[data-id]').forEach(function (tr) {
    const hit = (!q || (tr.dataset.search || '').includes(q))
      && (!program || tr.dataset.program === program)
      && (!team || tr.dataset.team === team);
    tr.style.display = hit ? '' : 'none';
  });
}

// ── school address boxes ──
// Only the name part is typed; the domain is shown in grey and added on save.
// A whole address, typed, pasted or picked from the suggestions, is used as is.
const STUDENT_DOMAIN = @json(\App\Models\Student::EMAIL_DOMAIN);

function schoolEmailBox(id) {
  const input = document.getElementById(id);
  if (!input || input.closest('.email-field')) return;

  const field = document.createElement('div');
  field.className = 'email-field';
  input.parentNode.insertBefore(field, input);
  field.appendChild(input);

  const domain = document.createElement('span');
  domain.className = 'email-domain';
  domain.setAttribute('aria-hidden', 'true');
  domain.textContent = STUDENT_DOMAIN;
  field.appendChild(domain);

  input.setAttribute('aria-label', 'Email address, ending in ' + STUDENT_DOMAIN);
  const sync = function () { field.classList.toggle('has-at', input.value.includes('@')); };
  ['input', 'change', 'focus'].forEach(function (e) { input.addEventListener(e, sync); });
  input.syncDomain = sync;
  sync();
}

function schoolEmail(id) {
  const value = document.getElementById(id).value.trim();
  if (!value) return '';

  return value.includes('@') ? value : value + STUDENT_DOMAIN;
}

function setSchoolEmail(id, email) {
  const input = document.getElementById(id);
  const value = String(email || '');
  input.value = value.toLowerCase().endsWith(STUDENT_DOMAIN) ? value.slice(0, -STUDENT_DOMAIN.length) : value;
  if (input.syncDomain) input.syncDomain();
}

schoolEmailBox('add-student-email');
schoolEmailBox('edit-student-email');

// ── add by email ──
function openAddStudent() {
  ['add-student-email','add-student-first','add-student-middle','add-student-last','add-student-number','add-student-section']
    .forEach(function (id) { document.getElementById(id).value = ''; });
  setSchoolEmail('add-student-email', '');
  document.getElementById('add-student-error').style.display = 'none';
  document.getElementById('add-student-scrim').hidden = false;
  document.body.style.overflow = 'hidden';
  document.getElementById('add-student-email').focus();
}

function closeAddStudent(e) {
  if (e && e.target !== document.getElementById('add-student-scrim')) return;
  document.getElementById('add-student-scrim').hidden = true;
  document.body.style.overflow = '';
}

function saveNewStudent() {
  const err = document.getElementById('add-student-error');
  const btn = document.getElementById('add-student-btn');
  err.style.display = 'none';

  const number = document.getElementById('add-student-number');
  if (!number.value.trim()) {
    err.textContent = 'Enter the student number.';
    err.style.display = 'block';
    number.focus();
    return;
  }
  btn.disabled = true;
  btn.textContent = 'Adding…';

  fetch(STUDENT_ROUTES.store, {
    method: 'POST',
    headers: headers(),
    body: JSON.stringify({
      email: schoolEmail('add-student-email'),
      f_name: document.getElementById('add-student-first').value.trim(),
      m_name: document.getElementById('add-student-middle').value.trim() || null,
      l_name: document.getElementById('add-student-last').value.trim(),
      student_number: document.getElementById('add-student-number').value.trim(),
      course: document.getElementById('add-student-course').value,
      // The year and the letter go separately - the stored shape has a
      // separator nobody would guess, so the server puts it together.
      year: Number(document.getElementById('add-student-year').value),
      section: document.getElementById('add-student-section').value.trim(),
    }),
  })
    .then(r => r.json().then(d => ({ ok: r.ok, d })))
    .then(function ({ ok, d }) {
      if (!ok) {
        err.textContent = d.message || firstError(d) || 'Could not add that student.';
        err.style.display = 'block';
        return;
      }
      closeAddStudent();
      showToast('Student added.', 'success');
      setTimeout(() => window.location.reload(), 800);
    })
    .catch(function () { err.textContent = 'Network error.'; err.style.display = 'block'; })
    .finally(function () { btn.disabled = false; btn.textContent = 'Add student'; });
}

function firstError(d) {
  if (!d || !d.errors) return null;
  const k = Object.keys(d.errors)[0];
  return k ? d.errors[k][0] : null;
}

// ── one student's whole record ──
let editingStudent = null;

function openEditStudent(student) {
  editingStudent = student;
  document.getElementById('edit-student-title').textContent = student.f_name + ' ' + student.l_name;
  setSchoolEmail('edit-student-email', student.email);
  document.getElementById('edit-student-first').value = student.f_name || '';
  document.getElementById('edit-student-middle').value = student.m_name || '';
  document.getElementById('edit-student-last').value = student.l_name || '';
  document.getElementById('edit-student-number').value = student.student_number || '';
  document.getElementById('edit-student-course').value = student.course || 'BSIT';
  document.getElementById('edit-student-year').value = String(student.year || '4').replace(/[^0-9]/g, '') || '4';
  document.getElementById('edit-student-section').value = student.section || '';
  document.getElementById('edit-student-error').style.display = 'none';
  document.getElementById('edit-student-scrim').hidden = false;
  document.body.style.overflow = 'hidden';
}

function closeEditStudent(e) {
  if (e && e.target !== document.getElementById('edit-student-scrim')) return;
  document.getElementById('edit-student-scrim').hidden = true;
  document.body.style.overflow = '';
}

function saveEditStudent() {
  if (!editingStudent) return;
  const err = document.getElementById('edit-student-error');
  const btn = document.getElementById('edit-student-btn');
  err.style.display = 'none';
  btn.disabled = true;
  btn.textContent = 'Saving…';

  // A new address changes how the student gets in, so it is asked about
  // once before it is sent - the old address stops opening this account.
  const newEmail = schoolEmail('edit-student-email').toLowerCase();
  const oldEmail = String(editingStudent.email || '').trim().toLowerCase();

  if (newEmail && newEmail !== oldEmail) {
    const name = document.getElementById('edit-student-first').value.trim() || 'This student';

    confirmSheet({
      title: 'Change the sign-in email?',
      body: name + ' will sign in with ' + newEmail + '. ' + (oldEmail || 'The old address')
        + ' will no longer open this account. Their team, documents and history stay.',
      confirmLabel: 'Change email',
    }).then(function (agreed) {
      if (agreed) {
        send(false);

        return;
      }

      btn.disabled = false;
      btn.textContent = 'Save changes';
    });
  } else {
    send(false);
  }

  // transfer=true is the second attempt, after the CaPSA has been told what
  // moving a student out of their sections means. The server refuses the
  // first one on purpose - handing a student to a colleague is correct to
  // allow, wrong to do by accident, and impossible to undo from this side.
  function send(transfer) {
    fetch(STUDENT_ROUTES.one(editingStudent.id), {
      method: 'PATCH',
      headers: headers(),
      body: JSON.stringify({
        email: schoolEmail('edit-student-email'),
        f_name: document.getElementById('edit-student-first').value.trim(),
        m_name: document.getElementById('edit-student-middle').value.trim() || null,
        l_name: document.getElementById('edit-student-last').value.trim(),
        student_number: document.getElementById('edit-student-number').value.trim() || null,
        course: document.getElementById('edit-student-course').value,
        year: Number(document.getElementById('edit-student-year').value),
        section: document.getElementById('edit-student-section').value.trim(),
        transfer: transfer,
      }),
    })
      .then(r => r.json().then(d => ({ ok: r.ok, d })))
      .then(function ({ ok, d }) {
        if (!ok && d.needs_transfer) {
          const name = document.getElementById('edit-student-first').value.trim();
          const where = d.section ? ' to ' + d.section : '';

          // Asked in the app's own voice. This page already carries the
          // confirm sheet for removals; a transfer is the more final of the
          // two and was still going through the browser's own box.
          confirmSheet({
            title: 'Transfer ' + (name || 'this student') + where + '?',
            body: 'They will belong to the CaPSA who handles that section, and you '
              + 'will not be able to edit them afterwards.',
            confirmLabel: 'Transfer',
            danger: true,
          }).then(function (agreed) {
            if (agreed) {
              send(true);

              return;
            }

            btn.disabled = false;
            btn.textContent = 'Save changes';
          });

          return;
        }

        if (!ok) {
          err.textContent = d.message || firstError(d) || 'Could not save.';
          err.style.display = 'block';
          btn.disabled = false;
          btn.textContent = 'Save changes';

          return;
        }

        closeEditStudent();
        showToast(d.message || 'Saved.', 'success');
        setTimeout(() => window.location.reload(), 700);
      })
      .catch(function () {
        err.textContent = 'Network error.';
        err.style.display = 'block';
        btn.disabled = false;
        btn.textContent = 'Save changes';
      });
  }
}

// ── several at once ──
function patchEach(ids, body, done) {
  return Promise.all(ids.map(function (id) {
    return fetch(STUDENT_ROUTES.one(id), { method: 'PATCH', headers: headers(), body: JSON.stringify(body) })
      .then(r => r.ok)
      .catch(() => false);
  })).then(function (results) {
    const ok = results.filter(Boolean).length;
    const failed = results.length - ok;
    // A loop can half-succeed, so say which it was rather than claiming it all
    // worked. The usual cause is a section that is not yours.
    showToast(failed ? ok + ' changed, ' + failed + ' could not be' : done(ok),
      failed ? 'error' : 'success');
    setTimeout(() => window.location.reload(), 900);
  });
}

// == bulk editing ==
// The sections this CaPSA handles, so a sheet can say whether the change it is
// about to make leaves the students within reach. The endpoint refuses a move
// out of them, and a red toast afterwards is a worse way to learn that.
const MY_SECTIONS = @json($adminSections);

let bulkIds = [];

function pickedNames(rows) {
  return rows.map(function (tr) {
    const el = tr.querySelector('.std-name');
    return el ? el.textContent.trim() : '';
  }).filter(Boolean);
}

function fillNames(el, names) {
  el.textContent = '';
  names.forEach(function (name, i) {
    if (i) el.appendChild(document.createTextNode(' · '));
    const b = document.createElement('b');
    b.textContent = name;
    el.appendChild(b);
  });
}

function pickedRows() {
  return Array.from(document.querySelectorAll('.table tbody tr[data-id]'))
    .filter(tr => bulkIds.indexOf(tr.dataset.id) !== -1);
}

// "4th" and "B" and BSIT together make BSIT-4B, the string every section
// lookup matches on. Worked out here the same way the server works it out.
function sectionLabel(program, year, letter) {
  return program + '-' + Number(year) + String(letter).toUpperCase();
}

function ordinal(year) {
  return Number(year) + (({ 1: 'st', 2: 'nd', 3: 'rd' })[Number(year)] || 'th');
}

// One line saying what the change comes to, and whether it stays yours.
function saying(el, label, count) {
  const mine = MY_SECTIONS.length === 0 || MY_SECTIONS.indexOf(label) !== -1;
  el.textContent = '';
  el.appendChild(document.createTextNode(count + (count === 1 ? ' student' : ' students') + ' → '));
  const b = document.createElement('b');
  b.textContent = label;
  el.appendChild(b);
  el.appendChild(document.createTextNode(mine
    ? ', which is one of yours.'
    : ', which you do not handle — the save will be refused.'));
}

// -- set program --
function openBulkProgram(ids, rows) {
  bulkIds = ids;
  fillNames(document.getElementById('bulk-program-names'), pickedNames(rows));
  document.getElementById('bulk-program-title').textContent =
    'Set program for ' + ids.length + (ids.length === 1 ? ' student' : ' students');
  document.getElementById('bulk-program-error').style.display = 'none';
  previewBulkProgram();
  document.getElementById('bulk-program-scrim').hidden = false;
  document.body.style.overflow = 'hidden';
}

function closeBulkProgram(e) {
  if (e && e.target !== document.getElementById('bulk-program-scrim')) return;
  document.getElementById('bulk-program-scrim').hidden = true;
  document.body.style.overflow = '';
}

// The program is half of the section label, so changing it moves people
// between sections whether or not that was the intention.
function previewBulkProgram() {
  const program = document.getElementById('bulk-program-value').value;
  const el = document.getElementById('bulk-program-result');
  const rows = pickedRows();

  const targets = rows
    .map(tr => sectionLabel(program, tr.dataset.year || '4', tr.dataset.letter || 'A'))
    .filter((v, i, a) => a.indexOf(v) === i);

  if (targets.length === 1) {
    saying(el, targets[0], rows.length);
    return;
  }

  // A mixed selection lands in several sections at once; naming one of them
  // would be naming the wrong one for everybody else.
  el.textContent = 'They keep their own year and section, so they land in '
    + targets.join(', ') + '.';
}

function saveBulkProgram() {
  const program = document.getElementById('bulk-program-value').value;
  closeBulkProgram();
  patchEach(bulkIds, { course: program }, n => n + ' moved to ' + program);
}

// -- set year and section --
function openBulkSection(ids, rows) {
  bulkIds = ids;
  fillNames(document.getElementById('bulk-section-names'), pickedNames(rows));
  document.getElementById('bulk-section-title').textContent =
    'Set year & section for ' + ids.length + (ids.length === 1 ? ' student' : ' students');
  document.getElementById('bulk-section-error').style.display = 'none';
  previewBulkSection();
  document.getElementById('bulk-section-scrim').hidden = false;
  document.body.style.overflow = 'hidden';
}

function closeBulkSection(e) {
  if (e && e.target !== document.getElementById('bulk-section-scrim')) return;
  document.getElementById('bulk-section-scrim').hidden = true;
  document.body.style.overflow = '';
}

function previewBulkSection() {
  const year = document.getElementById('bulk-section-year').value;
  const letter = document.getElementById('bulk-section-letter').value;
  const el = document.getElementById('bulk-section-result');
  const rows = pickedRows();

  // Everybody keeps their own program, so a mixed selection lands in more
  // than one section.
  const programs = rows.map(tr => tr.dataset.program)
    .filter((v, i, a) => v && a.indexOf(v) === i);

  if (programs.length === 1) {
    saying(el, sectionLabel(programs[0], year, letter), rows.length);
    return;
  }

  el.textContent = '';
  el.appendChild(document.createTextNode('They will read '));
  const b = document.createElement('b');
  b.textContent = ordinal(year) + ' · ' + letter;
  el.appendChild(b);
  el.appendChild(document.createTextNode(
    ', in ' + programs.join(' and ') + ' — each stays in their own program.'));
}

function saveBulkSection() {
  const year = document.getElementById('bulk-section-year').value;
  const letter = document.getElementById('bulk-section-letter').value;
  closeBulkSection();
  patchEach(bulkIds, { year: Number(year), section: letter },
    n => n + ' moved to ' + ordinal(year) + ' · ' + letter);
}

// The row menu's own remove. Same sheet and same request as the bar's, so
// one student and eight are told the same thing before it happens.
function removeOneStudent(student) {
  confirmSheet({
    title: 'Remove ' + student.f_name + ' ' + student.l_name + ' from your section?',
    names: [student.l_name + ', ' + student.f_name],
    body: 'Their account, team and submissions are kept. Only the section is cleared, '
        + 'so whoever handles the section they join next becomes their CaPSA.',
    confirmLabel: 'Remove from section',
    danger: true,
  }).then(function (yes) {
    if (!yes) return;

    fetch(STUDENT_ROUTES.one(student.id), {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    }).then(function (r) {
      showToast(r.ok ? 'Removed from your section' : 'Could not remove them',
        r.ok ? 'success' : 'error');
      if (r.ok) setTimeout(() => window.location.reload(), 900);
    });
  });
}

if (typeof onBulk === 'function') {
  onBulk('bulkProgram', openBulkProgram);
  onBulk('bulkSection', openBulkSection);

  onBulk('bulkRemove', function (ids, rows) {
    confirmSheet({
      title: 'Remove ' + ids.length + (ids.length === 1 ? ' student' : ' students') + ' from your section?',
      names: pickedNames(rows),
      body: 'Their accounts, teams and submissions are kept. Only the section is cleared, '
          + 'so whoever handles the section they join next becomes their CaPSA.',
      confirmLabel: 'Remove from section',
      danger: true,
    }).then(function (yes) {
      if (!yes) return;

      Promise.all(ids.map(function (id) {
        return fetch(STUDENT_ROUTES.one(id), {
          method: 'DELETE',
          headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        }).then(r => r.ok).catch(() => false);
      })).then(function (results) {
        const ok = results.filter(Boolean).length;
        const failed = results.length - ok;
        showToast(failed ? ok + ' removed, ' + failed + ' could not be' : ok + ' removed from your section',
          failed ? 'error' : 'success');
        setTimeout(() => window.location.reload(), 900);
      });
    });
  });
}

document.addEventListener('keydown', function (e) {
  if (e.key !== 'Escape') return;
  if (!document.getElementById('add-student-scrim').hidden) closeAddStudent();
  if (!document.getElementById('edit-student-scrim').hidden) closeEditStudent();
  if (!document.getElementById('bulk-program-scrim').hidden) closeBulkProgram();
  if (!document.getElementById('bulk-section-scrim').hidden) closeBulkSection();
});
</script>

@include('partials.escape-closes-modals')
</body>
</html>
