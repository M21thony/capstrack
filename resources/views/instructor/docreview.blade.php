<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<title>CapsTRACK — Expert Portal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/expert.css') }}" />
</head>
<body>
@include('instructor.partials.sidebar', ['currentPage' => 'docreview'])
<div class="main">
  <div class="topbar">
    <div class="page-title">Document Review</div>
    <div class="topbar-actions"><div class="notif-btn"><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><div class="notif-dot"></div></div></div>
  </div>
  <div class="content">
    <div class="row" style="margin-bottom:14px;gap:8px">
      <input class="input" placeholder="Search documents or teams..." style="max-width:280px"/>
      <select class="input" style="width:160px">
        <option>All Teams</option>
        @foreach ($teams as $team)
          <option>{{ $team->team_name }}</option>
        @endforeach
      </select>
      <select class="input" style="width:150px"><option>All Status</option><option>Pending</option><option>Reviewed</option></select>
      <span style="flex:1"></span>
      <span style="font-size:12px;color:var(--text3)">
        {{ $reviews->count() }} documents &middot; {{ $reviews->where('review_status', 'To Review')->count() }} pending
      </span>
    </div>
    <div class="card" style="padding:0;overflow:hidden">
      <table class="table">
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
            <tr @if($review->review_status === 'Reviewed') style="opacity:0.65" @endif>
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
              <td style="font-size:12px">{{ \Illuminate\Support\Carbon::parse($review->submitted_at)->format('M j, Y') }}</td>
              <td style="font-family:'Space Mono',monospace;font-size:12px">v{{ $review->version ?? '1.0' }}</td>
              <td>
                @if ($review->review_status === 'Reviewed')
                  <span class="badge badge-green">Reviewed</span>
                @elseif ($review->review_status === 'Reviewing')
                  <span class="badge badge-gold">In Review</span>
                @else
                  <span class="badge badge-red">To Review</span>
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
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>