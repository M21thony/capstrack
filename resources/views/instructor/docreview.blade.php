<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<title>CapsTRACK — Expert Portal</title>
<link href="{{ asset('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/expert.css') }}" />
<style>
  /* A phone gets the filters stacked full width, and one card per document
     instead of a six-column row. */
  @media (max-width: 700px) {
    .dr-filters { flex-wrap: wrap; }
    .dr-filters > .input { flex: 1 1 100%; width: 100% !important; max-width: none !important; }
    .dr-filters > span[style*="flex:1"] { display: none; }
    .dr-table, .dr-table tbody { display: block; width: 100%; }
    .dr-table thead { display: none; }
    .dr-table tbody tr.dr-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px 10px; padding: 14px; border-bottom: 1px solid var(--border); }
    .dr-table tbody tr.dr-row td { display: block; padding: 0 !important; border: 0; min-width: 0; }
    .dr-table tbody tr.dr-row td:nth-child(1) { grid-column: 1 / -1; }
    .dr-table tbody tr.dr-row td:nth-child(2) { grid-column: 1; }
    .dr-table tbody tr.dr-row td:nth-child(3) { grid-column: 2; text-align: right; color: var(--text3); }
    .dr-table tbody tr.dr-row td:nth-child(4) { grid-column: 1; color: var(--text3); }
    .dr-table tbody tr.dr-row td:nth-child(5) { grid-column: 2; text-align: right; }
    .dr-table tbody tr.dr-row td:nth-child(6) { grid-column: 1 / -1; }
    .dr-table tbody tr.dr-row td:nth-child(6) .btn { width: 100%; justify-content: center; }
    .dr-table tbody tr:not(.dr-row) { display: block; }
    .dr-table tbody tr:not(.dr-row) td { display: block; }
  }
</style>
@include('partials.skeleton-head', ['skReveal' => 'block'])
</head>
<body>
@include('instructor.partials.sidebar', ['currentPage' => 'docreview'])
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Document Review</div>
    <div class="topbar-actions">@include('instructor.partials.notification-dropdown')</div>
  </div>
  <div id="skeleton-content" class="sk-overlay">
    <div class="sk" style="height:56px;border-radius:12px;margin-bottom:14px"></div>
    <div class="row" style="margin-bottom:14px;gap:8px">
      <div class="sk" style="width:280px;height:34px;border-radius:8px"></div>
      <div class="sk" style="width:160px;height:34px;border-radius:8px"></div>
      <div class="sk" style="width:150px;height:34px;border-radius:8px"></div>
    </div>
    <div class="card" style="padding:16px">
      <div class="sk" style="height:44px;border-radius:8px;margin-bottom:10px"></div>
      <div class="sk" style="height:44px;border-radius:8px;margin-bottom:10px"></div>
      <div class="sk" style="height:44px;border-radius:8px;margin-bottom:10px"></div>
      <div class="sk" style="height:44px;border-radius:8px"></div>
    </div>
  </div>

  <div id="real-content" style="display:none">
  <div class="content">
    @if (session('success'))
      <div class="card" style="padding:10px 16px;margin-bottom:14px;background:rgba(22,163,74,0.08);border:1px solid rgba(22,163,74,0.25);color:var(--green);font-size:13px">
        {{ session('success') }}
      </div>
    @endif
    @if (session('error'))
      <div class="card" style="padding:10px 16px;margin-bottom:14px;background:rgba(220,38,38,0.08);border:1px solid rgba(220,38,38,0.25);color:#dc2626;font-size:13px">
        {{ session('error') }}
      </div>
    @endif

    <!-- GOOGLE DRIVE CONNECTION BANNER -->
    <div class="card" style="padding:14px 16px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:12px">
      <div style="display:flex;align-items:center;gap:10px">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><path d="M12 15V3"/>
        </svg>
        @if ($driveConnected)
          <span style="font-size:13px;color:var(--text2)">✓ Google Drive connected — annotated PDFs sync automatically.</span>
        @else
          <span style="font-size:13px;color:var(--text2)">Google Drive isn't connected — annotated PDFs won't sync until you connect your account.</span>
        @endif
      </div>
      <div>
        @if ($driveConnected)
          <button class="btn btn-ghost" id="gdrive-disconnect-btn" style="font-size:12px" onclick="disconnectDrive()">Disconnect</button>
        @else
          <a href="{{ route('gdrive.connect') }}" class="btn btn-primary" style="font-size:12px">Connect Google Drive</a>
        @endif
      </div>
    </div>

    {{-- Waiting, overdue, done. "Over 5 days" is the one that matters: a
         status column says a document is unreviewed, it does not say for how
         long, and length is what a team complains about. --}}
    @php
      $pendingReviews = $reviews->where('review_status', '!=', 'Reviewed');
      $overdueReviews = $pendingReviews->filter(
        fn ($r) => \Illuminate\Support\Carbon::parse($r->submitted_at)->diffInDays(now()) >= 5
      );
    @endphp
    <div class="grid-3" style="margin-bottom:14px">
      <x-dashboard.stat-chip
        value="{{ $pendingReviews->count() }}" label="Waiting on you" icon="hourglass"
        sub="{{ $pendingReviews->isEmpty() ? 'Nothing outstanding' : 'Oldest first below' }}"
        color="var(--accent3)" />
      <x-dashboard.stat-chip
        value="{{ $overdueReviews->count() }}" label="Over 5 days" icon="alarm"
        sub="{{ $overdueReviews->isEmpty() ? 'None running late' : 'These are late' }}"
        color="{{ $overdueReviews->isEmpty() ? 'var(--text3)' : 'var(--red)' }}" />
      <x-dashboard.stat-chip
        value="{{ $reviews->where('review_status', 'Reviewed')->count() }}" label="Reviewed" icon="file-check"
        sub="All time" color="var(--green)" />
    </div>

    <div class="row dr-filters" style="margin-bottom:14px;gap:8px">
      <input class="input" id="dr-search" placeholder="Search documents or teams..." style="max-width:280px"/>
      <select class="input" id="dr-team" style="width:160px">
        <option value="">All Teams</option>
        @foreach ($teams as $team)
          <option value="{{ $team->team_name }}">{{ $team->team_name }}</option>
        @endforeach
      </select>
      <select class="input" id="dr-status" style="width:150px">
        <option value="">All Status</option>
        <option value="pending">Pending</option>
        <option value="reviewed">Reviewed</option>
      </select>
      <span style="flex:1"></span>
      <span style="font-size:12px;color:var(--text3)" id="dr-count">
        {{ $reviews->count() }} documents &middot; {{ $reviews->where('review_status', '!=', 'Reviewed')->count() }} pending
      </span>
    </div>
    <div class="card" style="padding:0;overflow:hidden">
      <table class="table dr-table">
        <thead>
          <tr>
            <th style="padding-left:20px">Document</th>
            <th>Team</th>
            <th>Submitted</th>
            <th>Version</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($reviews as $review)
            @php
              $waitingDays = (int) \Illuminate\Support\Carbon::parse($review->submitted_at)->diffInDays(now());
              $rowState = $review->review_status === 'Reviewed'
                ? 'is-done'
                : ($waitingDays >= 5 ? 'is-late' : 'is-waiting');
            @endphp
            <tr class="dr-row {{ $rowState }}"
              data-search="{{ Str::lower($review->file_name.' '.($review->team_name ?? '').' '.$review->role) }}"
              data-team="{{ $review->team_name ?? '' }}"
              data-status="{{ $review->review_status === 'Reviewed' ? 'reviewed' : 'pending' }}">
              <td style="padding-left:20px">
                <div style="display:flex;align-items:center;gap:10px">
                  <div class="doc-icon" @if($review->review_status === 'Reviewed') style="background:rgba(22,163,74,0.08);border:1px solid rgba(22,163,74,0.2)" @endif>
                    <svg viewBox="0 0 24 24" @if($review->review_status === 'Reviewed') style="stroke:var(--green)" @endif><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                  </div>
                  <div>
                    <div class="td-main">{{ $review->file_name }}</div>
                    <div style="font-size:11px;color:var(--text3)">{{ ucfirst($review->role) }} review &middot; v{{ $review->file_version }}</div>
                  </div>
                </div>
              </td>
              <td><span class="badge badge-gold">{{ $review->team_name ?? '—' }}</span></td>
              <td style="font-size:12px;font-variant-numeric:tabular-nums">{{ \Illuminate\Support\Carbon::parse($review->submitted_at)->format('M j, Y') }}</td>
              <td style="font-variant-numeric:tabular-nums;font-size:12px">v{{ $review->version ?? '1.0' }}</td>
              <td>
                @if ($review->review_status === 'Reviewed')
                  <span class="badge badge-green">Reviewed</span>
                @elseif ($review->review_status === 'Reviewing')
                  <span class="badge badge-gold">In Review{{ $waitingDays >= 1 ? ' · '.$waitingDays.'d' : '' }}</span>
                @elseif ($waitingDays >= 5)
                  <span class="badge badge-red">{{ $waitingDays }} days waiting</span>
                @else
                  <span class="badge badge-gold">{{ $waitingDays < 1 ? 'Waiting' : $waitingDays.' days waiting' }}</span>
                @endif
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:6px">
                  <a
                    href="{{ route('instructor.docreview.annotate', $review->review_id) }}?reviewId={{ $review->review_id }}"
                    class="btn {{ $review->review_status === 'Reviewed' ? 'btn-ghost' : 'btn-primary' }}"
                    style="font-size:11px;padding:5px 14px;text-decoration:none;display:inline-flex;align-items:center;gap:6px"
                  >
                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.52.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    {{ $review->review_status === 'Reviewed' ? 'View Annotations' : ($review->review_status === 'Reviewing' ? 'Continue Review' : 'Open Annotator') }}
                  </a>
                  @if ($review->drive_status === 'uploaded' && $review->drive_web_view_link)
                    <a
                      href="{{ $review->drive_web_view_link }}"
                      target="_blank"
                      rel="noopener"
                      class="btn btn-ghost"
                      style="font-size:11px;padding:5px 14px;text-decoration:none;display:inline-flex;align-items:center;gap:6px"
                      title="Open the Drive copy — use Google Drive's own &quot;Open with Google Docs&quot; if you want it converted"
                    >
                      <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                      View in Drive
                    </a>
                  @endif
                  <a
                    href="{{ route('instructor.docreview.clf', $review->review_id) }}"
                    class="btn btn-ghost"
                    style="font-size:11px;padding:5px 14px;text-decoration:none;display:inline-flex;align-items:center;gap:6px"
                    title="Download Consultation Logs Form"
                  >
                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    CLF
                  </a>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" style="text-align:center;padding:24px;color:var(--text3)">No documents assigned for review yet.</td>
            </tr>
          @endforelse
          <tr id="dr-no-match" style="display:none">
            <td colspan="6" style="text-align:center;padding:24px;color:var(--text3)">No documents match these filters.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
  </div>
</div>
<div class="modal-overlay" id="dr-confirm" onclick="if (event.target === this) closeDisconnectConfirm()">
  <div class="modal-box" style="width:380px">
    <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:6px">Disconnect Google Drive?</div>
    <p style="margin:0 0 16px;font-size:13px;line-height:1.55;color:var(--text2)">
      Annotated PDFs will stop syncing until you reconnect. The files already in
      Drive stay where they are.
    </p>
    <p id="dr-confirm-error" hidden style="margin:0 0 16px;font-size:12px;color:var(--red)">
      Could not disconnect Google Drive. Please try again.
    </p>
    <div style="display:flex;justify-content:flex-end;gap:8px">
      <button type="button" class="btn btn-ghost" style="font-size:12px" onclick="closeDisconnectConfirm()">Cancel</button>
      <button type="button" class="btn btn-primary" style="font-size:12px" id="dr-confirm-ok" onclick="doDisconnectDrive()">Disconnect</button>
    </div>
  </div>
</div>
@include('instructor.partials.expert-profile')
@include('instructor.partials.skeleton-toggle')
<script>
/* The three controls above the table were markup only - typing in the box
   or picking a team did nothing at all. They filter the rows now, and the
   count on the right says what is being shown rather than what exists. */
(function () {
  var search = document.getElementById('dr-search');
  var team   = document.getElementById('dr-team');
  var status = document.getElementById('dr-status');
  var count  = document.getElementById('dr-count');
  var noMatch = document.getElementById('dr-no-match');
  var rows = Array.prototype.slice.call(document.querySelectorAll('tr.dr-row'));

  if (!rows.length) return;

  function filterRows() {
    var q = search ? search.value.toLowerCase().trim() : '';
    var wantTeam = team ? team.value : '';
    var wantStatus = status ? status.value : '';
    var shown = 0;
    var pending = 0;

    rows.forEach(function (row) {
      // Read off the row rather than out of the cells - counting columns to
      // find the status breaks the moment one moves.
      var matches = (!q || (row.dataset.search || '').indexOf(q) !== -1)
        && (!wantTeam || row.dataset.team === wantTeam)
        && (!wantStatus || row.dataset.status === wantStatus);

      row.style.display = matches ? '' : 'none';

      if (matches) {
        shown++;
        if (row.dataset.status === 'pending') pending++;
      }
    });

    if (noMatch) noMatch.style.display = shown ? 'none' : '';
    if (count) {
      count.textContent = shown + (shown === 1 ? ' document' : ' documents')
        + ' · ' + pending + ' pending';
    }
  }

  if (search) search.addEventListener('input', filterRows);
  if (team) team.addEventListener('change', filterRows);
  if (status) status.addEventListener('change', filterRows);
})();

/* Asked in the page's own voice rather than the browser's. A second
   window.confirm() in the same minute offers "don't ask me again" on some
   browsers, which turns the next irreversible action into a silent yes. */
function disconnectDrive(){
  document.getElementById('dr-confirm').classList.add('show');
}

function closeDisconnectConfirm(){
  document.getElementById('dr-confirm').classList.remove('show');
}

function doDisconnectDrive(){
  const btn = document.getElementById('dr-confirm-ok');
  btn.disabled = true;
  btn.textContent = 'Disconnecting...';

  fetch("{{ route('gdrive.connection.destroy') }}", {
    method: 'DELETE',
    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
  })
    .then((r) => r.json())
    .then(() => window.location.reload())
    .catch(() => {
      btn.disabled = false;
      btn.textContent = 'Disconnect';
      document.getElementById('dr-confirm-error').hidden = false;
    });
}


/* Mobile sidebar toggle */
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');

function toggleSidebar() {
  if (!sidebarEl) return;
  var isOpen = sidebarEl.classList.contains('mobile-open');
  if (isOpen) { closeSidebar(); } else { openSidebar(); }
}
function openSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.add('mobile-open');
  sidebarOverlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.remove('mobile-open');
  sidebarOverlay.classList.remove('open');
  document.body.style.overflow = '';
}
</script>
@include('partials.escape-closes-modals')
</body>
</html>