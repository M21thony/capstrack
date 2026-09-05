<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Financial Records - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<style>
.filter-select {
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23999' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 8px center;
  background-size: 12px;
  padding-right: 22px !important;
}
</style>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
</head>
<body>

@include('capsa._sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Financial Records</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
      <button class="btn btn-ghost-nav">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><path d="M12 15V3"/></svg>
        Export CSV
      </button>
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="grid-2" style="margin-bottom:18px">
      <div class="card"><div class="sk" style="height:220px;border-radius:12px"></div></div>
      <div class="card"><div class="sk" style="height:220px;border-radius:12px"></div></div>
    </div>
    <div class="card" style="padding:16px;margin-bottom:18px">
      <div class="sk" style="height:40px;border-radius:8px;margin-bottom:8px"></div>
      <div class="sk" style="height:40px;border-radius:8px;margin-bottom:8px"></div>
      <div class="sk" style="height:40px;border-radius:8px"></div>
    </div>
    <div class="grid-2">
      <div class="card"><div class="sk" style="height:260px;border-radius:12px"></div></div>
      <div class="card"><div class="sk" style="height:260px;border-radius:12px"></div></div>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content">

    @if (session('success'))
      <div class="admin-api-success">
        {{ session('success') }}
      </div>
    @endif

    <!-- -- ANALYTICS OVERVIEW -- -->
    <div class="fin-overview">

      <!-- LEFT: Team payment status ring -->
      <div class="fin-split-card">
        <div class="fin-split-label">Team Payment Status</div>
        <div class="fin-ring-wrap">
          @php
            $teamPercentFull = $totalTeams > 0 ? round(($fullyPaidTeams / $totalTeams) * 100, 2) : 0;
            $teamPercentPending = $totalTeams > 0 ? round(($hasPendingTeams / $totalTeams) * 100, 2) : 0;
            $teamPercentNone = $totalTeams > 0 ? round(($noSubmissionTeams / $totalTeams) * 100, 2) : 0;
          @endphp
          <div class="fin-ring">
            <svg viewBox="0 0 36 36">
              <circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--bg4)" stroke-width="4"/>
              <circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--green)"
                stroke-width="4"
                stroke-dasharray="{{ $teamPercentFull }} {{ 100 - $teamPercentFull }}"
                stroke-dashoffset="25"
                transform="rotate(-90 18 18)"/>
              <circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--amber)"
                stroke-width="4"
                stroke-dasharray="{{ $teamPercentPending }} {{ 100 - $teamPercentPending }}"
                stroke-dashoffset="{{ 25 - $teamPercentFull }}"
                transform="rotate(-90 18 18)"/>
              <circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--red)"
                stroke-width="4"
                stroke-dasharray="{{ $teamPercentNone }} {{ 100 - $teamPercentNone }}"
                stroke-dashoffset="{{ 25 - $teamPercentFull - $teamPercentPending }}"
                transform="rotate(-90 18 18)"/>
            </svg>
            <div class="fin-ring-center">
              <span class="fin-ring-n" style="color:var(--text)">{{ $totalTeams }}</span>
              <span class="fin-ring-l">Teams</span>
            </div>
          </div>

          <div class="fin-legend">
            <div class="fin-legend-row">
              <div class="fin-legend-top">
                <span class="fin-legend-label">
                  <span class="fin-legend-dot" style="background:var(--green)"></span>
                  Fully Paid
                </span>
                <span class="fin-legend-count" style="color:var(--green)">{{ $fullyPaidTeams }}</span>
              </div>
              <div class="fin-pbar">
                <div class="fin-pbar-fill" style="width:{{ $teamPercentFull }}%;background:var(--green)"></div>
              </div>
              <div style="font-size:10px;color:var(--text3);margin-top:2px">{{ $teamPercentFull }}% of all teams · All receipts verified</div>
            </div>
            <div class="fin-legend-row">
              <div class="fin-legend-top">
                <span class="fin-legend-label">
                  <span class="fin-legend-dot" style="background:var(--amber)"></span>
                  Has Pending
                </span>
                <span class="fin-legend-count" style="color:var(--amber)">{{ $hasPendingTeams }}</span>
              </div>
              <div class="fin-pbar">
                <div class="fin-pbar-fill" style="width:{{ $teamPercentPending }}%;background:var(--amber)"></div>
              </div>
              <div style="font-size:10px;color:var(--text3);margin-top:2px">{{ $teamPercentPending }}% · Submitted, awaiting verification</div>
            </div>
            <div class="fin-legend-row">
              <div class="fin-legend-top">
                <span class="fin-legend-label">
                  <span class="fin-legend-dot" style="background:var(--red)"></span>
                  No Submission
                </span>
                <span class="fin-legend-count" style="color:var(--red)">{{ $noSubmissionTeams }}</span>
              </div>
              <div class="fin-pbar">
                <div class="fin-pbar-fill" style="width:{{ $teamPercentNone }}%;background:var(--red)"></div>
              </div>
              <div style="font-size:10px;color:var(--text3);margin-top:2px">{{ $teamPercentNone }}% · Have not submitted any receipt</div>
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT: Student payment status quad -->
      <div class="fin-split-card">
        <div class="fin-split-label">Student Payment Status</div>

        @php
          $studPercentPaid = $totalStudents > 0 ? round(($paidStudents / $totalStudents) * 100, 2) : 0;
          $studPercentPending = $totalStudents > 0 ? round(($pendingStudents / $totalStudents) * 100, 2) : 0;
          $studPercentNone = $totalStudents > 0 ? round(($noSubmissionStudents / $totalStudents) * 100, 2) : 0;
        @endphp

        <div style="margin-bottom:10px">
          <div style="display:flex;height:10px;border-radius:5px;overflow:hidden;gap:1px">
            <div style="width:{{ $studPercentPaid }}%;background:var(--green)" title="Paid: {{ $paidStudents }}"></div>
            <div style="width:{{ $studPercentPending }}%;background:var(--amber)" title="Pending: {{ $pendingStudents }}"></div>
            <div style="width:{{ $studPercentNone }}%;background:var(--red)" title="Not Submitted: {{ $noSubmissionStudents }}"></div>
          </div>
          <div style="display:flex;gap:14px;margin-top:6px">
            <span style="font-size:10px;color:var(--text3);display:flex;align-items:center;gap:4px">
              <span style="width:8px;height:8px;border-radius:50%;background:var(--green);display:inline-block"></span>Paid
            </span>
            <span style="font-size:10px;color:var(--text3);display:flex;align-items:center;gap:4px">
              <span style="width:8px;height:8px;border-radius:50%;background:var(--amber);display:inline-block"></span>Pending
            </span>
            <span style="font-size:10px;color:var(--text3);display:flex;align-items:center;gap:4px">
              <span style="width:8px;height:8px;border-radius:50%;background:var(--red);display:inline-block"></span>No Submission
            </span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3);font-variant-numeric:tabular-nums;">{{ $totalStudents }} students total</span>
          </div>
        </div>

        <div class="fin-quad">
          <div class="fin-quad-cell">
            <div class="fin-quad-n" style="color:var(--green)">{{ $paidStudents }}</div>
            <div class="fin-quad-l" style="color:var(--green)">Paid Students</div>
            <div class="fin-quad-sub">{{ $studPercentPaid }}% · All receipts verified</div>
          </div>
          <div class="fin-quad-cell">
            <div class="fin-quad-n" style="color:var(--red)">{{ $noSubmissionStudents }}</div>
            <div class="fin-quad-l" style="color:var(--red)">Unpaid Students</div>
            <div class="fin-quad-sub">{{ $studPercentNone }}% · No submissions yet</div>
          </div>
          <div class="fin-quad-cell">
            <div class="fin-quad-n" style="color:var(--amber)">{{ $pendingStudents }}</div>
            <div class="fin-quad-l" style="color:var(--amber)">Pending Review</div>
            <div class="fin-quad-sub">{{ $studPercentPending }}% · Awaiting verification</div>
          </div>
          <div class="fin-quad-cell">
            <div class="fin-quad-n" style="color:var(--blue)">{{ $paidStudents }}</div>
            <div class="fin-quad-l" style="color:var(--blue)">Partially Paid</div>
            <div class="fin-quad-sub">Various · Some receipts verified</div>
          </div>
        </div>
      </div>

    </div>

    <!-- -- TEAM BREAKDOWN TABLE -- -->
    <div class="card" style="margin-bottom:18px;padding:0;overflow:hidden" id="teamBreakdownCard">
      <div class="tb-header">
        <span class="card-title">Team Breakdown</span>
        <form id="filterForm">
          <select class="input filter-select" name="title_id" style="width:150px;font-size:12px;padding:6px 10px">
            <option value="">All Titles</option>
            @foreach ($expensePosts as $post)
              <option value="{{ $post->id }}" {{ $titleId == $post->id ? 'selected' : '' }}>{{ $post->title }}</option>
            @endforeach
          </select>
          <select class="input filter-select" name="course" style="width:150px;font-size:12px;padding:6px 10px">
            <option value="">All Courses</option>
            @foreach ($courses as $crs)
              <option value="{{ $crs }}" {{ $course === $crs ? 'selected' : '' }}>{{ $crs }}</option>
            @endforeach
          </select>
          <select class="input filter-select" name="status" style="width:150px;font-size:12px;padding:6px 10px">
            <option value="">All Status</option>
            <option value="fully_paid" {{ $status === 'fully_paid' ? 'selected' : '' }}>Fully Paid</option>
            <option value="has_pending" {{ $status === 'has_pending' ? 'selected' : '' }}>Has Pending</option>
            <option value="no_submission" {{ $status === 'no_submission' ? 'selected' : '' }}>No Submission</option>
          </select>
        </form>
      </div>
      <div class="tb-scroll-wrap">
      <table class="table">
        <thead>
          <tr>
            <th style="padding-left:20px">Team</th>
            <th>Title</th>
            <th>Course</th>
            <th>Members</th>
            <th>Paid Members</th>
            <th>Total Verified</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($teams as $team)
            @php
              $stats = $teamStats[$team->team_id] ?? [
                'verified_count' => 0,
                'pending_count' => 0,
                'verified_amount' => 0,
                'post_ids' => collect(),
              ];
              $memberCount = $team->members->count();
              $verifiedCount = $stats['verified_count'];
              $pendingCount = $stats['pending_count'];
              $totalVerifiedAmount = $stats['verified_amount'];
              $postIds = $stats['post_ids'];

              $teamTitles = $postIds->map(fn($id) => $expensePostTitles[$id] ?? null)->filter()->values()->toArray();
              $teamTitleHtml = !empty($teamTitles) ? implode(',<br>', $teamTitles) : '—';

              $teamCourse = \App\Models\StudentSchoolYear::where('team_id', $team->team_id)->whereNotNull('program')->first()?->program ?? $team->members->first()?->course ?? 'N/A';

              if ($verifiedCount === $memberCount && $memberCount > 0) {
                $badge = 'badge-green';
                $statusLabel = 'Fully Paid';
                $dataStatus = 'fully_paid';
              } elseif ($verifiedCount > 0 || $pendingCount > 0) {
                $badge = 'badge-amber';
                $statusLabel = 'Has Pending';
                $dataStatus = 'has_pending';
              } else {
                $badge = 'badge-red';
                $statusLabel = 'No Submission';
                $dataStatus = 'no_submission';
              }

              $initials = collect(explode(' ', $team->team_name))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
            @endphp
            <tr data-course="{{ $teamCourse }}" data-status="{{ $dataStatus }}" data-title-ids="{{ $postIds->implode(',') }}" style="cursor:pointer" onclick="openTeamDetails({{ $team->team_id }})">
              <td style="padding-left:20px">
                <div style="display:flex;align-items:center;gap:8px">
                  <div style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,#16a34a,#0891b2);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">{{ $initials }}</div>
                  <span class="td-main">{{ $team->team_name }}</span>
                </div>
              </td>
              <td style="font-size:11px;max-width:160px;line-height:1.6">{!! $teamTitleHtml !!}</td>
              <td><span class="badge badge-gold" style="font-size:10px">{{ $teamCourse }}</span></td>
              <td style="font-variant-numeric:tabular-nums;font-size:12px">{{ $memberCount }}</td>
              <td>
                <div style="font-variant-numeric:tabular-nums;font-size:12px;font-weight:600;color:var(--green)">{{ $verifiedCount }} / {{ $memberCount }}</div>
              </td>
              <td style="font-variant-numeric:tabular-nums;font-size:12px;color:var(--green)">PHP {{ number_format($totalVerifiedAmount, 2) }}</td>
              <td><span class="badge {{ $badge }}">{{ $statusLabel }}</span></td>
            </tr>
          @empty
            <tr>
              <td colspan="7" style="text-align:center;padding:20px;color:var(--text3)">No teams found</td>
            </tr>
          @endforelse
        </tbody>
      </table>
      </div><!-- .tb-scroll-wrap -->
      <div style="padding:12px 20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
        <span id="teamCount" style="font-size:12px;color:var(--text3)">Showing {{ $teams->count() }} teams</span>
        <div id="paginationControls" style="display:flex;gap:6px;align-items:center">
          <button id="prevPageBtn" class="btn btn-ghost" style="font-size:11px;padding:5px 12px;display:none" onclick="prevPage()">? Prev</button>
          <span id="pageIndicator" style="font-size:11px;color:var(--text3);font-weight:600;display:none">Page 1</span>
          <button id="nextPageBtn" class="btn btn-ghost" style="font-size:11px;padding:5px 12px;display:none" onclick="nextPage()">Next ?</button>
        </div>
      </div>
    </div>

    <!-- -- RECEIPT QUEUE + EXPENSE POSTS GRID -- -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
    <div class="card" style="padding:0;overflow:hidden;min-width:0">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 20px 14px;border-bottom:1px solid var(--border)">
        <div>
          <div style="font-size:14px;font-weight:600;color:var(--text)">Receipt Queue</div>
          <div style="font-size:12px;color:var(--text3);margin-top:2px">Individual student receipt submissions pending admin action</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
          <button class="btn btn-primary" onclick="resetModal();document.getElementById('addPostModal').classList.add('open')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Post
          </button>
        </div>
      </div>
      <div id="receiptQueueContainer" class="gap-12" style="padding:16px 20px">

      @forelse ($financialRecords as $record)
        @php
          $statusColor = match($record->f_record_status) {
            'verified' => 'badge-green',
            'pending' => 'badge-amber',
            'rejected' => 'badge-red',
            default => 'badge-amber',
          };
          $statusText = match($record->f_record_status) {
            'verified' => 'Verified',
            'pending' => 'Pending',
            'rejected' => 'Rejected',
            default => 'Pending',
          };
          $studentName = $record->student ? ($record->student->f_name . ' ' . $record->student->l_name) : 'Unknown';
          $expenseTitle = $record->expensePost?->title ?? $record->payment_for ?? 'N/A';
          $teamName = $record->student?->team?->team_name ?? 'N/A';
        @endphp
        @php
          $receiptImageUrl = $record->receipt_image ? asset('storage/' . $record->receipt_image) : null;
        @endphp
        <div class="receipt-card" @if($record->f_record_status !== 'pending') style="opacity:0.65" @endif data-receipt-image="{{ $receiptImageUrl }}" data-record-id="{{ $record->id }}">
          <div class="receipt-thumb" onclick="event.stopPropagation(); viewImage({{ $record->id }})" @if($record->f_record_status === 'verified') style="background:var(--green-bg);border-color:rgba(22,163,74,0.3)" @endif>
            @if ($receiptImageUrl)
              <img src="{{ $receiptImageUrl }}" alt="Receipt" />
            @else
              <svg viewBox="0 0 24 24" @if($record->f_record_status === 'verified') style="stroke:var(--green)" @endif><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            @endif
          </div>
          <div class="receipt-info">
            <div class="receipt-name">Expense Category: {{ $expenseTitle }}</div>
            @if ($record->receipt_number)<div style="font-size:11px;color:var(--text2);font-weight:600;margin-bottom:3px">Receipt Number: {{ $record->receipt_number }}</div>@endif
            <div class="receipt-meta">Team {{ $teamName }} · <strong style="color:var(--text)">{{ $studentName }}</strong> · Uploaded {{ $record->uploaded_at ? \Carbon\Carbon::parse($record->uploaded_at)->format('M d, Y') : 'N/A' }}</div>
            <div class="receipt-amount">PHP {{ number_format($record->amount ?? 0, 2) }}</div>
            @if ($record->description)
              <div style="font-size:11px;color:var(--text3);margin-bottom:6px">Description: {{ $record->description }}</div>
            @endif
            @if ($record->rejection_reason)
              <div style="font-size:11px;color:var(--red);margin-bottom:6px">Reason: {{ $record->rejection_reason }}</div>
            @endif
            <div class="receipt-actions">
              @if ($record->f_record_status === 'pending')
                <button class="btn btn-sm btn-success" onclick="approveReceipt({{ $record->id }}, this)">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><polyline points="20 6 9 17 4 12"/></svg>
                  Approve
                </button>
                <button class="btn btn-sm btn-danger" onclick="openRejectModal({{ $record->id }})">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                  Reject
                </button>
                <button class="btn btn-sm btn-ghost" onclick="viewImage({{ $record->id }})">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                  View Image
                </button>
                <span class="badge {{ $statusColor }}" style="margin-left:auto">{{ $statusText }}</span>
              @elseif ($record->f_record_status === 'verified')
                <span style="font-size:11px;color:var(--green);display:flex;align-items:center;gap:4px">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><polyline points="20 6 9 17 4 12"/></svg>
                  Verified on {{ $record->verified_at ? \Carbon\Carbon::parse($record->verified_at)->format('M d, Y') : 'N/A' }}
                </span>
                <span class="badge {{ $statusColor }}" style="margin-left:auto">{{ $statusText }}</span>
              @else
                <span class="badge {{ $statusColor }}" style="margin-left:auto">{{ $statusText }}</span>
              @endif
            </div>
          </div>
        </div>
      @empty
        <div style="text-align:center;padding:30px;color:var(--text3);font-size:13px">
          No receipt submissions yet.
        </div>
      @endforelse
      </div>
    </div>

    <!-- -- EXPENSE POSTS LIST -- -->
    <div class="card" style="padding:0;overflow:hidden">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 20px 14px;border-bottom:1px solid var(--border)">
        <div>
          <div style="font-size:14px;font-weight:600;color:var(--text)">Expense Posts</div>
          <div style="font-size:12px;color:var(--text3);margin-top:2px">Manage posted expenses that students can select from</div>
        </div>
      </div>
      <div id="expensePostsContainer" style="padding:16px 20px">
        @forelse ($expensePosts as $post)
          <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)">
            <div style="flex:2;min-width:0">
              <div style="font-size:13px;font-weight:500;color:var(--text);display:flex;align-items:center;gap:6px">
                {{ $post->title }}
                @if($post->defense_type)
                  <span style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.3px;padding:2px 6px;border-radius:4px;background:var(--accent-light,rgba(201,168,44,0.12));color:var(--accent3,#8a6e10)">{{ $post->defense_type }}</span>
                @endif
              </div>
              <div style="font-size:11px;color:var(--text3)">{{ $post->course }} · {{ $post->year_level }}</div>
            </div>
            <div style="flex:1;font-variant-numeric:tabular-nums;font-size:13px;color:var(--text2)">PHP {{ number_format($post->amount, 2) }}</div>
            <div style="flex:2;font-size:11px;color:var(--text3);min-width:0">@if($post->description){{ Str::limit($post->description, 60) }}@else<span style="font-style:italic">No description</span>@endif</div>
            <div style="display:flex;gap:6px;flex-shrink:0">
              <button class="btn btn-sm btn-primary"
                onclick="openEditModal({{ $post->id }}, '{{ addslashes($post->title) }}', '{{ $post->course }}', '{{ $post->year_level }}', '{{ $post->defense_type ?? '' }}', '{{ $post->amount }}', '{{ addslashes($post->description ?? '') }}')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
              </button>
              <button class="btn btn-sm btn-danger"
                data-delete-url="{{ route('capsa.financial.expense-post.destroy', ['expensePost' => $post->id]) }}"
                onclick="openDeleteModal(this, '{{ addslashes($post->title) }}')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                Delete
              </button>
            </div>
          </div>
        @empty
          <div style="text-align:center;padding:20px;color:var(--text3);font-size:13px">
            No expense posts yet. Click "Add Post" to create one.
          </div>
        @endforelse
      </div>
    </div>
    </div>
    <!-- -- end grid -- -->

  </div>
  </div>
</div>
@include('capsa.partials.skeleton-toggle')

<!-- -- ADD / EDIT POST MODAL -- -->
<div id="addPostModal" class="modal-overlay">
  <div class="modal" style="max-width:480px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px">
      <div id="modalTitle" style="font-size:15px;font-weight:600;color:var(--text)">New Expense Post</div>
      <button class="btn-close" onclick="closeModal('addPostModal')">&times;</button>
    </div>
    <form id="postForm">
      <div id="postFormErrors" style="display:none;padding:10px 14px;background:var(--red-bg);border:1px solid var(--red);border-radius:8px;color:var(--red);font-size:12px;margin-bottom:14px"></div>
      <input type="hidden" id="editTitle" name="title"/>
      <div class="form-group">
        <label class="label">Course</label>
        <select class="input" id="editCourse" name="course" required>
          <option value="BSIT">BSIT</option>
          <option value="BSCS">BSCS</option>
          <option value="BLIS">BLIS</option>
          <option value="BSIS">BSIS</option>
        </select>
      </div>
      <div class="form-group">
        <label class="label">Year Level</label>
        <select class="input" id="editYear" name="year_level" required>
          <option value="3rd">3rd Year</option>
          <option value="4th">4th Year</option>
        </select>
      </div>
      <div class="form-group">
        <label class="label">Defense Stage</label>
        <select class="input" id="editDefenseType" name="defense_type" onchange="updateAutoTitle()">
          <option value="">— Not tied to a defense stage —</option>
          <option value="Pre-proposal Defense">Pre-proposal Defense</option>
          <option value="Title Defense">Title Defense</option>
          <option value="Pre Oral Defense">Pre Oral Defense</option>
          <option value="Final Oral Defense">Final Oral Defense</option>
        </select>
        <div style="font-size:11px;color:var(--text3);margin-top:4px">Tagging a post lets Panel Assignment tell which teams have paid for that stage. Title is generated automatically: <strong id="autoTitlePreview">General Payment</strong></div>
      </div>
      <div class="form-group">
        <label class="label">Amount (PHP)</label>
        <input class="input" id="editAmount" name="amount" type="number" step="0.01" min="0" placeholder="e.g. 960.00" required />
      </div>
      <div class="form-group">
        <label class="label">Description</label>
        <textarea class="input" id="editDescription" name="description" rows="3" placeholder="Optional description"></textarea>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn-cancel" onclick="closeModal('addPostModal')">Cancel</button>
        <button id="submitBtn" type="submit" class="btn btn-primary">Post Expense</button>
      </div>
    </form>
  </div>
</div>

<!-- -- DELETE CONFIRM MODAL -- -->
<div id="deleteModal" class="modal-overlay">
  <div class="modal" style="max-width:400px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
      <div style="font-size:15px;font-weight:600;color:var(--text)">Delete Expense Post</div>
      <button class="btn-close" onclick="closeModal('deleteModal')">&times;</button>
    </div>
    <p style="font-size:13px;color:var(--text2);margin:0 0 6px">Are you sure you want to delete this post?</p>
    <p id="deletePostTitle" style="font-size:13px;font-weight:600;color:var(--text);margin:0 0 20px"></p>
    <form id="deleteForm">
      <div id="deleteFormErrors" style="display:none;padding:10px 14px;background:var(--red-bg);border:1px solid var(--red);border-radius:8px;color:var(--red);font-size:12px;margin-bottom:14px"></div>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button type="button" class="btn-cancel" onclick="closeModal('deleteModal')">Cancel</button>
        <button type="submit" class="btn-del">Delete</button>
      </div>
    </form>
  </div>
</div>

<!-- -- REJECT RECEIPT MODAL -- -->
<div id="rejectModal" class="modal-overlay">
  <div class="modal" style="max-width:440px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
      <div style="font-size:15px;font-weight:600;color:var(--text)">Reject Receipt</div>
      <button class="btn-close" onclick="closeModal('rejectModal')">&times;</button>
    </div>
    <p style="font-size:13px;color:var(--text2);margin:0 0 14px">Provide a reason for rejecting this receipt. The student will see this reason.</p>
    <form id="rejectForm">
      <div id="rejectFormErrors" style="display:none;padding:10px 14px;background:var(--red-bg);border:1px solid var(--red);border-radius:8px;color:var(--red);font-size:12px;margin-bottom:14px"></div>
      <div class="form-group">
        <label class="label">Rejection Reason</label>
        <textarea class="input" name="rejection_reason" rows="4" placeholder="e.g. Receipt is unclear, incorrect amount, missing details..." required></textarea>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn-cancel" onclick="closeModal('rejectModal')">Cancel</button>
        <button type="submit" class="btn-del">Reject Receipt</button>
      </div>
    </form>
  </div>
</div>

<!-- -- VIEW IMAGE MODAL -- -->
<div id="imageModal" class="modal-overlay">
  <div class="modal" style="max-width:600px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <div style="font-size:15px;font-weight:600;color:var(--text)">Receipt Image</div>
      <button class="btn-close" onclick="closeModal('imageModal')">&times;</button>
    </div>
    <div style="text-align:center">
      <img id="receiptImageDisplay" src="" alt="Receipt" style="max-width:100%;max-height:500px;border-radius:8px;display:none" />
      <div id="noImagePlaceholder" style="padding:40px;color:var(--text3);font-size:13px">No receipt image available.</div>
    </div>
  </div>
</div>

<!-- -- TEAM DETAILS MODAL -- -->
<div id="teamDetailsModal" class="modal-overlay">
  <div class="modal" style="max-width:680px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
      <div style="font-size:16px;font-weight:700;color:var(--text)" id="tdTeamName">Team Name</div>
      <button class="btn-close" onclick="closeModal('teamDetailsModal')">&times;</button>
    </div>

    <div id="tdSummary" style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px;padding:12px 14px;background:var(--bg1);border-radius:10px;font-size:12px">
      <div><span style="color:var(--text3)">Leader:</span> <span style="color:var(--text);font-weight:600" id="tdLeader">—</span></div>
      <div><span style="color:var(--text3)">Course:</span> <span style="color:var(--text);font-weight:600" id="tdCourse">—</span></div>
      <div><span style="color:var(--text3)">Section:</span> <span style="color:var(--text);font-weight:600" id="tdSection">—</span></div>
      <div><span style="color:var(--text3)">Members:</span> <span style="color:var(--text);font-weight:600" id="tdMemberCount">—</span></div>
    </div>

    <div style="font-size:12px;font-weight:600;color:var(--text3);margin-bottom:8px">TITLES</div>
    <div id="tdTitlesWrap" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px"></div>

    <div style="font-size:12px;font-weight:600;color:var(--text3);margin-bottom:8px">MEMBER STATUS</div>
    <div style="overflow-x:auto">
      <table class="table" style="margin:0">
        <thead>
          <tr>
            <th style="padding-left:14px">Student Name</th>
            <th style="width:140px">Payment Status</th>
          </tr>
        </thead>
        <tbody id="tdMemberBody"></tbody>
      </table>
    </div>
    <div id="tdNoTitles" style="display:none;text-align:center;padding:20px;color:var(--text3);font-size:13px">This team has no submitted receipts yet.</div>
  </div>
</div>

<style>
/* -- MODAL OVERLAY -- */
.modal-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(24, 25, 31, 0.55);
  backdrop-filter: blur(3px);
  -webkit-backdrop-filter: blur(3px);
  z-index: 1000;
  align-items: center;
  justify-content: center;
  padding: 20px;
  animation: overlayIn 0.15s ease;
}
.modal-overlay.open {
  display: flex;
}
.modal-overlay.open .modal {
  animation: modalSlideUp 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

/* -- MODAL CONTAINER -- */
.modal {
  background: var(--bg2);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 24px;
  width: 90%;
  max-height: 85vh;
  overflow-y: auto;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.3);
}
.modal::-webkit-scrollbar { width: 4px; }
.modal::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

/* -- MODAL ANIMATIONS -- */
@keyframes overlayIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
@keyframes modalSlideUp {
  from { opacity: 0; transform: translateY(12px) scale(0.97); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

/* -- CONSISTENT ACTION BUTTON SYSTEM -- */
.btn-sm {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 7px;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  font-family: 'DM Sans', sans-serif;
  white-space: nowrap;
  border: 1.5px solid;
  line-height: 1.4;
}
.btn-sm svg {
  width: 12px;
  height: 12px;
  flex-shrink: 0;
  stroke: currentColor;
  fill: none;
  stroke-width: 2;
}
.btn-sm:active {
  transform: scale(0.97);
}
.btn-sm.btn-success {
  background: transparent;
  color: var(--green);
  border-color: rgba(22, 163, 74, 0.2);
}
.btn-sm.btn-success:hover {
  background: var(--green-bg);
  border-color: var(--green);
  box-shadow: 0 2px 8px rgba(22, 163, 74, 0.12);
}
.btn-sm.btn-danger {
  background: transparent;
  color: var(--red);
  border-color: rgba(220, 38, 38, 0.2);
}
.btn-sm.btn-danger:hover {
  background: var(--red-bg);
  border-color: var(--red);
  box-shadow: 0 2px 8px rgba(220, 38, 38, 0.12);
}
.btn-sm.btn-ghost {
  background: transparent;
  color: var(--text2);
  border-color: var(--border);
}
.btn-sm.btn-ghost:hover {
  background: var(--accent-light);
  color: var(--accent3);
  border-color: var(--accent);
  box-shadow: 0 2px 6px rgba(201, 168, 44, 0.08);
}
.btn-sm.btn-primary {
  background: var(--accent);
  color: #18191f;
  font-weight: 700;
  border: none;
}
.btn-sm.btn-primary:hover {
  background: var(--accent2);
  box-shadow: 0 2px 10px rgba(201, 168, 44, 0.25);
}

/* -- MODAL ACTION BUTTONS -- */
.btn-cancel {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 16px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  font-family: 'DM Sans', sans-serif;
  background: transparent;
  color: var(--text2);
  border: 1.5px solid var(--border);
}
.btn-cancel:hover {
  background: var(--accent-light);
  color: var(--accent3);
  border-color: var(--accent);
}
.btn-cancel:active {
  transform: scale(0.97);
}
.btn-del {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 16px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  font-family: 'DM Sans', sans-serif;
  background: var(--red);
  color: #fff;
  border: none;
}
.btn-del:hover {
  background: #dc2626;
  box-shadow: 0 4px 14px rgba(220, 38, 38, 0.3);
}
.btn-del:active {
  transform: scale(0.97);
}

/* -- CLOSE BUTTON -- */
.btn-close {
  background: none;
  border: none;
  color: var(--text3);
  font-size: 22px;
  cursor: pointer;
  padding: 4px 8px;
  line-height: 1;
  border-radius: 6px;
  transition: all 0.15s ease;
}
.btn-close:hover {
  color: var(--accent3);
  background: var(--accent-light);
}
.btn-close:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}

/* -- HIDE NUMBER INPUT SPINNERS -- */
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
input[type="number"] {
  -moz-appearance: textfield;
}

/* -- ADMIN API SUCCESS ALERT -- */
.admin-api-success {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  background: var(--green-bg);
  border: 1px solid rgba(22, 163, 74, 0.25);
  border-radius: 10px;
  color: var(--green);
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 20px;
  animation: fadeIn 0.3s ease;
}

/* -- UTILITY ANIMATIONS -- */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(6px); }
  to { opacity: 1; transform: translateY(0); }
}

/* -- TEAM BREAKDOWN HEADER -- */
.tb-header {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 8px;
  align-items: center;
  padding: 18px 20px 14px;
  border-bottom: 1px solid var(--border);
}
.tb-header #filterForm {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}
.tb-scroll-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }

/* -- HAMBURGER MENU -- */
.hamburger-btn {
  display: none;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 9px;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(201,168,44,0.2);
  cursor: pointer;
  color: var(--navy-text);
  flex-shrink: 0;
}
.hamburger-btn svg { width: 16px; height: 16px; stroke: rgba(255,255,200,0.7); fill: none; stroke-width: 2; stroke-linecap: butt; }

/* -- SIDEBAR OVERLAY -- */
.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(0,0,0,0.5);
}

/* -- RESPONSIVE BREAKPOINTS -- */
@media (max-width: 1200px) {
  .fin-ring-wrap { flex-direction: column; align-items: flex-start; }
}

@media (max-width: 1024px) {
  .fin-overview { grid-template-columns: 1fr; }

  .fin-split-card { padding: 18px; }
}

/* -- Transition gap: 769–795px (sidebar still visible, single-column cards need tighter sizing) -- */
@media (min-width: 769px) and (max-width: 795px) {
  .fin-ring { width: 80px; height: 80px; }
  .fin-ring svg { width: 80px; height: 80px; }
  .fin-ring-n { font-size: 17px; }
  .fin-ring-l { font-size: 8px; }

  .fin-legend { width: 100%; }
  .fin-legend-label { font-size: 11px; }
  .fin-legend-count { font-size: 11px; }

  .fin-split-card { padding: 14px; }
  .fin-split-label { font-size: 9px; margin-bottom: 10px; }

  .fin-quad { grid-template-columns: 1fr 1fr; }
  .fin-quad-cell { padding: 12px 14px; }
  .fin-quad-n { font-size: 18px; }
  .fin-quad-l { font-size: 9px; }
  .fin-quad-sub { font-size: 10px; }
}

@media (max-width: 768px) {
  .hamburger-btn { display: inline-flex; }

  .sidebar {
    display: flex !important;
    position: fixed !important;
    z-index: 1000;
    transform: translateX(-100%);
    transition: transform 0.25s cubic-bezier(0.16,1,0.3,1);
    width: 250px !important;
    min-width: 250px !important;
  }

  .sidebar-open .sidebar { transform: translateX(0); }
  .sidebar-open .sidebar-overlay { display: block; }
  body.sidebar-open { overflow: hidden; }

  .topbar { padding: 0 14px; gap: 10px; }
  .content { padding: 14px; }
  .page-title { font-size: 15px; }

  .fin-overview { grid-template-columns: 1fr; gap: 12px; margin-bottom: 14px; }
  .fin-ring-wrap { flex-direction: column; align-items: flex-start; }

  .fin-ring { width: 80px; height: 80px; }
  .fin-ring svg { width: 80px; height: 80px; }
  .fin-ring-n { font-size: 17px; }
  .fin-ring-l { font-size: 8px; }

  .fin-legend { width: 100%; }
  .fin-legend-row { gap: 3px; }
  .fin-legend-label { font-size: 11px; }
  .fin-legend-count { font-size: 11px; }
  .fin-legend-row > div:last-child { font-size: 9px !important; line-height: 1.4; }

  .fin-quad { grid-template-columns: 1fr; }
  .fin-quad-cell:first-child,
  .fin-quad-cell:nth-child(2),
  .fin-quad-cell:nth-child(3),
  .fin-quad-cell:last-child { border-radius: 0; }
  .fin-quad-cell { padding: 12px 14px; }
  .fin-quad-n { font-size: 18px; }
  .fin-quad-l { font-size: 9px; }
  .fin-quad-sub { font-size: 10px; }

  .fin-split-card { padding: 14px; }
  .fin-split-label { font-size: 9px; margin-bottom: 10px; }

  .tb-scroll-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  #teamDetailsModal .table { display: block; overflow-x: auto; -webkit-overflow-scrolling: touch; }
  #teamBreakdownCard .table th,
  #teamBreakdownCard .table td { white-space: nowrap; }

  [style*="display:grid;grid-template-columns"][style*="1fr 1fr"] { grid-template-columns: 1fr !important; }

  .tb-header {
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    padding: 14px 16px;
  }
  .tb-header .card-title { font-size: 11px; }
  .tb-header #filterForm { display: contents; }
  .tb-header select { width: 100% !important; }

  .receipt-card { flex-direction: column; }
  .receipt-thumb { width: 48px; height: 48px; }
  .receipt-amount { font-size: 13px; }
  .receipt-actions { flex-wrap: wrap; }
  .receipt-actions .btn-sm { font-size: 10px; padding: 4px 10px; }

  .modal { padding: 16px !important; max-width: 95vw !important; }
  .modal-overlay { padding: 10px; align-items: flex-start; padding-top: 30px; }

  #paginationControls { gap: 4px; }
  #paginationControls button { font-size: 10px; padding: 4px 10px; }
}

@media (max-width: 480px) {
  .fin-ring { width: 64px; height: 64px; }
  .fin-ring svg { width: 64px; height: 64px; }
  .fin-ring-n { font-size: 14px; }
  .fin-ring-l { font-size: 7px; }

  .fin-split-card { padding: 12px; }

  .fin-quad-cell { padding: 10px 12px; }
  .fin-quad-n { font-size: 16px; }

  .fin-split-label { font-size: 8px; }
}

/* -- TEAM DETAILS TITLE PILLS -- */
.td-title-pill {
  display: inline-flex;
  align-items: center;
  padding: 6px 14px;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  font-family: 'DM Sans', sans-serif;
  background: var(--bg1);
  color: var(--text2);
  border: 1.5px solid var(--border);
  line-height: 1.4;
  white-space: nowrap;
}
.td-title-pill:hover {
  background: var(--accent-light);
  color: var(--accent3);
  border-color: var(--accent);
}
.td-title-pill.active {
  background: var(--accent);
  color: #18191f;
  border-color: var(--accent);
  font-weight: 700;
}
.td-title-pill:active {
  transform: scale(0.97);
}

/* -- RESPONSIVE -- */
@media (max-width: 640px) {
  .modal { padding: 18px; }
  .modal-overlay { padding: 12px; }
  .btn-sm { font-size: 10px; padding: 4px 10px; }
}
</style>

<script>
var TEAM_DETAILS = {!! $teamDetailsJson !!};
const csrfToken = document.querySelector('meta[name="csrf-token"]');
const csrf = csrfToken ? csrfToken.getAttribute('content') : '';

function openModal(id) {
  document.getElementById(id).classList.add('open');
}

function closeModal(id) {
  document.getElementById(id).classList.remove('open');
  if (id === 'addPostModal') resetModal();
}

// Close modals by clicking overlay
document.querySelectorAll('.modal-overlay').forEach(function(el) {
  el.addEventListener('click', function(e) {
    if (e.target === this) closeModal(this.id);
  });
});

// -- Expense Post Modal State --
let editingPostId = null;

// Admins pick a defense stage, not type a title - the title is always
// derived from that choice so nobody has to invent naming conventions by hand.
function titleForDefenseType(defenseType) {
  const labels = {
    'Pre-proposal Defense': 'PPD Payment',
    'Title Defense': 'TD Payment',
    'Pre Oral Defense': 'POD Payment',
    'Final Oral Defense': 'FOD Payment',
  };
  return labels[defenseType] || 'General Payment';
}

function updateAutoTitle() {
  const title = titleForDefenseType(document.getElementById('editDefenseType').value);
  document.getElementById('editTitle').value = title;
  const preview = document.getElementById('autoTitlePreview');
  if (preview) preview.textContent = title;
}

function openEditModal(id, title, course, yearLevel, defenseType, amount, description) {
  editingPostId = id;
  document.getElementById('modalTitle').textContent = 'Edit Expense Post';
  document.getElementById('editCourse').value = course;
  document.getElementById('editYear').value = yearLevel;
  document.getElementById('editDefenseType').value = defenseType || '';
  document.getElementById('editAmount').value = amount;
  document.getElementById('editDescription').value = description;
  document.getElementById('submitBtn').textContent = 'Update Expense';
  document.getElementById('postFormErrors').style.display = 'none';
  updateAutoTitle();
  openModal('addPostModal');
}

function openDeleteModal(btn, title) {
  const recordId = btn.dataset.deleteUrl.split('/').pop();
  document.getElementById('deleteForm').dataset.recordId = recordId;
  document.getElementById('deletePostTitle').textContent = title;
  document.getElementById('deleteFormErrors').style.display = 'none';
  openModal('deleteModal');
}

function resetModal() {
  editingPostId = null;
  document.getElementById('postForm').reset();
  document.getElementById('modalTitle').textContent = 'New Expense Post';
  document.getElementById('submitBtn').textContent = 'Post Expense';
  document.getElementById('postFormErrors').style.display = 'none';
  updateAutoTitle();
}

let rejectingRecordId = null;

function openRejectModal(recordId) {
  rejectingRecordId = recordId;
  document.getElementById('rejectForm').querySelector('textarea').value = '';
  document.getElementById('rejectFormErrors').style.display = 'none';
  openModal('rejectModal');
}

// -- Approve Receipt via API --
async function approveReceipt(recordId, btn) {
  btn.disabled = true;
  btn.textContent = 'Approving...';

  try {
    const response = await fetch('/api/admin/financial/records/' + recordId, {
      method: 'PATCH',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ status: 'approved' }),
    });

    const result = await response.json();

    if (!response.ok) {
      const alertDiv = document.createElement('div');
      alertDiv.style.cssText = 'padding:10px 16px;background:var(--red-bg);border:1px solid var(--red);border-radius:8px;color:var(--red);font-size:13px;margin-bottom:16px';
      alertDiv.textContent = result.message || 'Something went wrong.';
      document.querySelector('.content').insertBefore(alertDiv, document.querySelector('.content').firstChild);
      btn.disabled = false;
      btn.textContent = 'Approve';
      return;
    }

    // Update the UI
    updateReceiptCardAfterApprove(recordId, result.data);
    showAdminSuccess('Receipt verified successfully.');
  } catch (error) {
    const alertDiv = document.createElement('div');
    alertDiv.style.cssText = 'padding:10px 16px;background:var(--red-bg);border:1px solid var(--red);border-radius:8px;color:var(--red);font-size:13px;margin-bottom:16px';
    alertDiv.textContent = 'Something went wrong.';
    document.querySelector('.content').insertBefore(alertDiv, document.querySelector('.content').firstChild);
    btn.disabled = false;
    btn.textContent = 'Approve';
  }
}

function updateReceiptCardAfterApprove(recordId, data) {
  const card = document.querySelector('.receipt-card[data-record-id="' + recordId + '"]');
  if (!card) return;

  card.style.opacity = '0.65';
  const actions = card.querySelector('.receipt-actions');
  if (actions) {
    const verifiedDate = data.verified_at_formatted || 'N/A';
    actions.innerHTML =
      '<span style="font-size:11px;color:var(--green);display:flex;align-items:center;gap:4px">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><polyline points="20 6 9 17 4 12"/></svg>' +
      'Verified on ' + verifiedDate +
      '</span>' +
      '<span class="badge badge-green" style="margin-left:auto">Verified</span>';
  }

  const thumb = card.querySelector('.receipt-thumb');
  if (thumb) {
    thumb.style.cssText = 'background:var(--green-bg);border-color:rgba(22,163,74,0.3)';
  }
}

// -- Submit Reject via API --
document.getElementById('rejectForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();

  const textarea = this.querySelector('textarea');
  const reason = textarea.value.trim();
  if (!reason) return;

  const submitBtn = this.querySelector('.btn-del');
  submitBtn.disabled = true;
  submitBtn.textContent = 'Rejecting...';
  document.getElementById('rejectFormErrors').style.display = 'none';

  try {
    const response = await fetch('/api/admin/financial/records/' + rejectingRecordId, {
      method: 'PATCH',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ status: 'rejected', rejection_reason: reason }),
    });

    const result = await response.json();

    if (!response.ok) {
      const errorsDiv = document.getElementById('rejectFormErrors');
      if (response.status === 422 && result.errors) {
        errorsDiv.textContent = Object.values(result.errors).flat().join('; ');
      } else {
        errorsDiv.textContent = result.message || 'Something went wrong.';
      }
      errorsDiv.style.display = 'block';
      submitBtn.disabled = false;
      submitBtn.textContent = 'Reject Receipt';
      return;
    }

    updateReceiptCardAfterReject(rejectingRecordId, reason);
    closeModal('rejectModal');
    showAdminSuccess('Receipt rejected.');
    submitBtn.disabled = false;
    submitBtn.textContent = 'Reject Receipt';
  } catch (error) {
    document.getElementById('rejectFormErrors').textContent = 'Something went wrong.';
    document.getElementById('rejectFormErrors').style.display = 'block';
    submitBtn.disabled = false;
    submitBtn.textContent = 'Reject Receipt';
  }
});

function updateReceiptCardAfterReject(recordId, reason) {
  const card = document.querySelector('.receipt-card[data-record-id="' + recordId + '"]');
  if (!card) return;

  const oldReason = card.querySelector('.receipt-info > div[style*="color:var(--red)"]');
  if (!oldReason) {
    const reasonDiv = document.createElement('div');
    reasonDiv.style.cssText = 'font-size:11px;color:var(--red);margin-bottom:6px';
    reasonDiv.textContent = 'Reason: ' + reason;
    const amountDiv = card.querySelector('.receipt-amount');
    if (amountDiv) amountDiv.parentNode.insertBefore(reasonDiv, amountDiv.nextSibling);
  } else {
    oldReason.textContent = 'Reason: ' + reason;
  }

  const actions = card.querySelector('.receipt-actions');
  if (actions) {
    actions.innerHTML = '<span class="badge badge-red" style="margin-left:auto">Rejected</span>';
  }
}

// -- Submit Expense Post (Create/Update) via API --
document.getElementById('postForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();

  const submitBtn = document.getElementById('submitBtn');
  submitBtn.disabled = true;
  submitBtn.textContent = 'Posting...';
  document.getElementById('postFormErrors').style.display = 'none';

  const data = {
    title: document.getElementById('editTitle').value,
    course: document.getElementById('editCourse').value,
    year_level: document.getElementById('editYear').value,
    defense_type: document.getElementById('editDefenseType').value || null,
    amount: document.getElementById('editAmount').value,
    description: document.getElementById('editDescription').value,
  };

  const isUpdate = editingPostId !== null;
  const url = isUpdate
    ? '/api/admin/financial/expense-posts/' + editingPostId
    : '/api/admin/financial/expense-posts';
  const method = isUpdate ? 'PUT' : 'POST';

  try {
    const response = await fetch(url, {
      method: method,
      headers: {
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(data),
    });

    const result = await response.json();

    if (!response.ok) {
      const errorsDiv = document.getElementById('postFormErrors');
      if (response.status === 422 && result.errors) {
        errorsDiv.textContent = Object.values(result.errors).flat().join('; ');
      } else {
        errorsDiv.textContent = result.message || 'Something went wrong.';
      }
      errorsDiv.style.display = 'block';
      submitBtn.disabled = false;
      submitBtn.textContent = isUpdate ? 'Update Expense' : 'Post Expense';
      return;
    }

    closeModal('addPostModal');
    showAdminSuccess(result.message || (isUpdate ? 'Expense post updated successfully.' : 'Expense post created successfully.'));
    // Reload the expense posts list
    await refreshExpensePosts();
    submitBtn.disabled = false;
    submitBtn.textContent = isUpdate ? 'Update Expense' : 'Post Expense';
  } catch (error) {
    document.getElementById('postFormErrors').textContent = 'Something went wrong.';
    document.getElementById('postFormErrors').style.display = 'block';
    submitBtn.disabled = false;
    submitBtn.textContent = isUpdate ? 'Update Expense' : 'Post Expense';
  }
});

// -- Delete Expense Post via API --
document.getElementById('deleteForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();

  const recordId = this.dataset.recordId;
  const submitBtn = this.querySelector('.btn-del');
  submitBtn.disabled = true;
  submitBtn.textContent = 'Deleting...';
  document.getElementById('deleteFormErrors').style.display = 'none';

  try {
    const response = await fetch('/api/admin/financial/expense-posts/' + recordId, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    });

    const result = await response.json();

    if (!response.ok) {
      document.getElementById('deleteFormErrors').textContent = result.message || 'Something went wrong.';
      document.getElementById('deleteFormErrors').style.display = 'block';
      submitBtn.disabled = false;
      submitBtn.textContent = 'Delete';
      return;
    }

    closeModal('deleteModal');
    showAdminSuccess('Expense post deleted successfully.');
    await refreshExpensePosts();
    submitBtn.disabled = false;
    submitBtn.textContent = 'Delete';
  } catch (error) {
    document.getElementById('deleteFormErrors').textContent = 'Something went wrong.';
    document.getElementById('deleteFormErrors').style.display = 'block';
    submitBtn.disabled = false;
    submitBtn.textContent = 'Delete';
  }
});

// -- Refresh Expense Posts List --
async function refreshExpensePosts() {
  try {
    const response = await fetch('/api/admin/financial/expense-posts', {
      headers: { 'Accept': 'application/json' },
    });
    const result = await response.json();
    if (response.ok && result.data) {
      renderExpensePosts(result.data);
      // Also update the team breakdown filter dropdown
      updateTeamBreakdownTitleFilter(result.data);
    }
  } catch (error) {
    // Silent fail
  }
}

function renderExpensePosts(posts) {
  const container = document.getElementById('expensePostsContainer');
  if (!container) return;

  if (posts.length === 0) {
    container.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text3);font-size:13px">No expense posts yet. Click "Add Post" to create one.</div>';
    return;
  }

  container.innerHTML = posts.map(function(post) {
    const desc = post.description
      ? post.description.length > 60
        ? post.description.substring(0, 60) + '...'
        : post.description
      : '<span style="font-style:italic">No description</span>';
    const safeTitle = post.title.replace(/'/g, "\\'").replace(/"/g, '&quot;');
    const safeDesc = (post.description || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    const defenseTypeBadge = post.defense_type
      ? '<span style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.3px;padding:2px 6px;border-radius:4px;background:var(--accent-light,rgba(201,168,44,0.12));color:var(--accent3,#8a6e10)">' + post.defense_type + '</span>'
      : '';

    return '<div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)">' +
      '<div style="flex:2;min-width:0">' +
      '<div style="font-size:13px;font-weight:500;color:var(--text);display:flex;align-items:center;gap:6px">' + post.title + defenseTypeBadge + '</div>' +
      '<div style="font-size:11px;color:var(--text3)">' + post.course + ' · ' + post.year_level + '</div>' +
      '</div>' +
      '<div style="flex:1;font-variant-numeric:tabular-nums;font-size:13px;color:var(--text2)">PHP ' + parseFloat(post.amount).toLocaleString('en-US', {minimumFractionDigits: 2}) + '</div>' +
      '<div style="flex:2;font-size:11px;color:var(--text3);min-width:0">' + desc + '</div>' +
      '<div style="display:flex;gap:6px;flex-shrink:0">' +
      '<button class="btn btn-sm btn-primary" onclick="openEditModal(' + post.id + ', \'' + safeTitle + '\', \'' + post.course + '\', \'' + post.year_level + '\', \'' + (post.defense_type || '') + '\', \'' + post.amount + '\', \'' + safeDesc + '\')">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>' +
      ' Edit</button>' +
      '<button class="btn btn-sm btn-danger" data-delete-url="/api/admin/financial/expense-posts/' + post.id + '" onclick="openDeleteModal(this, \'' + safeTitle + '\')">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>' +
      ' Delete</button>' +
      '</div>' +
      '</div>';
  }).join('');
}

function updateTeamBreakdownTitleFilter(posts) {
  const select = document.querySelector('select[name="title_id"]');
  if (!select) return;
  const currentValue = select.value;
  select.innerHTML = '<option value="">All Titles</option>';
  posts.forEach(function(post) {
    const opt = document.createElement('option');
    opt.value = post.id;
    opt.textContent = post.title;
    if (String(post.id) === currentValue) opt.selected = true;
    select.appendChild(opt);
  });
}

// -- Show Admin Success Message --
function showAdminSuccess(message) {
  const existing = document.querySelector('.admin-api-success');
  if (existing) existing.remove();

  const alertDiv = document.createElement('div');
  alertDiv.className = 'admin-api-success';
  alertDiv.style.cssText = 'padding:10px 16px;background:var(--green-bg);border:1px solid var(--green);border-radius:8px;color:var(--green);font-size:13px;margin-bottom:16px';
  alertDiv.textContent = message;
  document.querySelector('.content').insertBefore(alertDiv, document.querySelector('.content').firstChild);

  setTimeout(function() { alertDiv.remove(); }, 5000);
}

// -- Pagination State --
var currentPage = 1;
var rowsPerPage = 10;
var filteredRows = [];

// -- Client-side Team Breakdown Filtering + Pagination --
function filterTeamBreakdown() {
  var titleId = document.querySelector('select[name="title_id"]').value;
  var course = document.querySelector('select[name="course"]').value;
  var status = document.querySelector('select[name="status"]').value;

  var tbody = document.querySelector('#teamBreakdownCard tbody');
  var rows = tbody.querySelectorAll('tr');
  var emptyRow = tbody.querySelector('.empty-filter-row');

  if (emptyRow) emptyRow.remove();

  filteredRows = [];
  rows.forEach(function(row) {
    if (!row.hasAttribute('data-course')) return;
    var rowCourse = row.getAttribute('data-course') || '';
    var rowStatus = row.getAttribute('data-status') || '';
    var rowTitleIds = row.getAttribute('data-title-ids') || '';

    var matchTitle = !titleId || rowTitleIds.split(',').indexOf(titleId) !== -1;
    var matchCourse = !course || rowCourse === course;
    var matchStatus = !status || rowStatus === status;

    if (matchTitle && matchCourse && matchStatus) {
      filteredRows.push(row);
    }
  });

  currentPage = 1;
  renderPage();
}

function renderPage() {
  var tbody = document.querySelector('#teamBreakdownCard tbody');
  var rows = tbody.querySelectorAll('tr');

  // Hide all rows first
  rows.forEach(function(row) {
    row.style.display = 'none';
  });

  var totalFiltered = filteredRows.length;
  var totalPages = Math.ceil(totalFiltered / rowsPerPage) || 1;
  if (currentPage > totalPages) currentPage = totalPages;

  var start = (currentPage - 1) * rowsPerPage;
  var end = Math.min(start + rowsPerPage, totalFiltered);
  var pageRows = filteredRows.slice(start, end);

  pageRows.forEach(function(row) {
    row.style.display = '';
  });

  // Show empty state if no matches
  var existingEmpty = tbody.querySelector('.empty-filter-row');
  if (existingEmpty) existingEmpty.remove();
  if (totalFiltered === 0) {
    var empty = document.createElement('tr');
    empty.className = 'empty-filter-row';
    empty.innerHTML = '<td colspan="7" style="text-align:center;padding:20px;color:var(--text3)">No teams match the selected filters</td>';
    tbody.appendChild(empty);
  }

  // Update count display
  var countEl = document.getElementById('teamCount');
  if (countEl) {
    if (totalFiltered === 0) {
      countEl.textContent = 'Showing 0 teams';
    } else {
      countEl.textContent = 'Showing Teams ' + (start + 1) + '\u2013' + end + ' of ' + totalFiltered;
    }
  }

  // Update pagination controls
  var prevBtn = document.getElementById('prevPageBtn');
  var nextBtn = document.getElementById('nextPageBtn');
  var pageInd = document.getElementById('pageIndicator');

  if (totalPages <= 1) {
    prevBtn.style.display = 'none';
    nextBtn.style.display = 'none';
    pageInd.style.display = 'none';
  } else {
    pageInd.style.display = '';
    pageInd.textContent = 'Page ' + currentPage + ' of ' + totalPages;

    if (currentPage === 1) {
      prevBtn.style.display = 'none';
      nextBtn.style.display = '';
    } else if (currentPage === totalPages) {
      prevBtn.style.display = '';
      nextBtn.style.display = 'none';
    } else {
      prevBtn.style.display = '';
      nextBtn.style.display = '';
    }
  }
}

function prevPage() {
  if (currentPage > 1) {
    currentPage--;
    renderPage();
  }
}

function nextPage() {
  var totalPages = Math.ceil(filteredRows.length / rowsPerPage) || 1;
  if (currentPage < totalPages) {
    currentPage++;
    renderPage();
  }
}

// Initial pagination render after page load
document.addEventListener('DOMContentLoaded', function() {
  // Collect all data rows into filteredRows
  var tbody = document.querySelector('#teamBreakdownCard tbody');
  var rows = tbody.querySelectorAll('tr');
  rows.forEach(function(row) {
    if (row.hasAttribute('data-course')) {
      filteredRows.push(row);
    }
  });
  renderPage();
});

document.querySelector('select[name="title_id"]')?.addEventListener('change', filterTeamBreakdown);
document.querySelector('select[name="course"]')?.addEventListener('change', filterTeamBreakdown);
document.querySelector('select[name="status"]')?.addEventListener('change', filterTeamBreakdown);

// Remove the filter form's native submit behavior
document.getElementById('filterForm')?.addEventListener('submit', function(e) { e.preventDefault(); });

// -- Team Details Modal --
var tdCurrentTeamId = null;
var tdCurrentTitleId = null;

function openTeamDetails(teamId) {
  var team = TEAM_DETAILS.find(function(t) { return t.id === teamId; });
  if (!team) return;

  tdCurrentTeamId = teamId;

  document.getElementById('tdTeamName').textContent = team.name;
  document.getElementById('tdLeader').textContent = team.leader;
  document.getElementById('tdCourse').textContent = team.course;
  document.getElementById('tdSection').textContent = team.section;
  document.getElementById('tdMemberCount').textContent = team.member_count;

  // Render title pills
  var wrap = document.getElementById('tdTitlesWrap');
  var noTitles = document.getElementById('tdNoTitles');

  if (team.titles.length === 0) {
    wrap.innerHTML = '';
    noTitles.style.display = 'block';
    document.querySelector('#teamDetailsModal .table').style.display = 'none';
    openModal('teamDetailsModal');
    return;
  }

  noTitles.style.display = 'none';
  document.querySelector('#teamDetailsModal .table').style.display = '';

  wrap.innerHTML = team.titles.map(function(t) {
    return '<button class="td-title-pill' + (tdCurrentTitleId === t.post_id ? ' active' : '') + '" onclick="selectTitle(' + teamId + ', ' + t.post_id + ')">' + t.title.replace(/</g, '&lt;') + '</button>';
  }).join('');

  // Select the first title if none selected
  if (tdCurrentTitleId === null || !team.titles.some(function(t) { return t.post_id === tdCurrentTitleId; })) {
    selectTitle(teamId, team.titles[0].post_id);
  } else {
    selectTitle(teamId, tdCurrentTitleId);
  }

  openModal('teamDetailsModal');
}

function selectTitle(teamId, postId) {
  var team = TEAM_DETAILS.find(function(t) { return t.id === teamId; });
  if (!team) return;

  tdCurrentTeamId = teamId;
  tdCurrentTitleId = postId;

  // Re-render pills so the active state is always correct
  document.getElementById('tdTitlesWrap').innerHTML = team.titles.map(function(t) {
    return '<button class="td-title-pill' + (postId === t.post_id ? ' active' : '') + '" onclick="selectTitle(' + teamId + ', ' + t.post_id + ')">' + t.title.replace(/</g, '&lt;') + '</button>';
  }).join('');

  var title = team.titles.find(function(t) { return t.post_id === postId; });
  if (!title) return;

  var tbody = document.getElementById('tdMemberBody');
  tbody.innerHTML = title.members.map(function(m) {
    var badgeClass = 'badge-' + (m.status === 'Verified' ? 'green' : m.status === 'Has Pending' ? 'amber' : 'red');
    return '<tr>' +
      '<td style="padding-left:14px"><span style="font-size:13px;color:var(--text)">' + m.name.replace(/</g, '&lt;') + '</span></td>' +
      '<td><span class="badge ' + badgeClass + '">' + m.status + '</span></td>' +
      '</tr>';
  }).join('');
}

function viewImage(recordId) {
  var card = document.querySelector('.receipt-card[data-record-id="' + recordId + '"]');
  var imageUrl = card ? card.getAttribute('data-receipt-image') : null;
  var img = document.getElementById('receiptImageDisplay');
  var placeholder = document.getElementById('noImagePlaceholder');
  if (imageUrl && imageUrl !== '') {
    img.src = imageUrl;
    img.style.display = 'inline';
    placeholder.style.display = 'none';
  } else {
    img.style.display = 'none';
    placeholder.style.display = 'block';
  }
  openModal('imageModal');
}

// -- Mobile Sidebar --
function toggleSidebar() {
    document.body.classList.toggle('sidebar-open');
    document.body.style.overflow = document.body.classList.contains('sidebar-open') ? 'hidden' : '';
}

function closeSidebar() {
    document.body.classList.remove('sidebar-open');
    document.body.style.overflow = '';
}

document.querySelectorAll('.nav-item').forEach(function(el) {
    el.addEventListener('click', function() {
        if (window.innerWidth <= 768) closeSidebar();
    });
});
</script>

</body>
</html>
