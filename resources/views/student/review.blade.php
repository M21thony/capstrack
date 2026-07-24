<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Review Viewer - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/styles.css')
</head>
<body>
@include('student.partials.sidebar')

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="page-title">Review Viewer</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'review'])
@if($documents->isEmpty())
<div class="empty-state" style="text-align:center;padding:60px 20px">
  <div style="font-size:40px;margin-bottom:12px;opacity:0.3">&#128196;</div>
  <div style="font-size:15px;font-weight:600;color:var(--text2);margin-bottom:6px">No Documents Yet</div>
  <div style="font-size:12px;color:var(--text3)">Submitted documents with expert reviews will appear here.</div>
</div>
@else
<div class="row" style="margin-bottom:14px;gap:8px">
  <select class="input" id="doc-select" style="width:320px" onchange="switchDocument(this)">
    @foreach($documents as $doc)
    <option value="{{ $doc->file_id }}" data-file="{{ $doc->file_name }}">
      {{ $doc->file_name }} — {{ $doc->created_at ? $doc->created_at->format('M j, Y') : 'Unknown date' }}
    </option>
    @endforeach
  </select>
  <span style="flex:1"></span>
</div>
<div class="grid-2">
  <div class="card" style="height:420px;overflow-y:auto">
    <div class="card-title" style="margin-bottom:12px">Document Details</div>
    <div id="doc-details">
      <p style="font-size:13px;color:var(--text2);margin-bottom:4px">Select a document to view its review status and expert feedback.</p>
    </div>
  </div>
  <div class="card" style="height:420px;overflow-y:auto">
    <div class="card-title" style="margin-bottom:12px">Expert Reviews</div>
    <div id="reviews-list">
      <p style="font-size:13px;color:var(--text2)">Select a document to load its reviews.</p>
    </div>
  </div>
</div>

<script>
const reviews = @json($reviews);
const docs = @json($documents->keyBy('file_id'));

function switchDocument(sel) {
  const fileId = sel.value;
  const doc = docs[fileId];
  const docReviews = reviews[fileId] || [];

  const detailsDiv = document.getElementById('doc-details');
  const reviewsDiv = document.getElementById('reviews-list');

  detailsDiv.innerHTML = `
    <div style="margin-bottom:12px">
      <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:4px">${doc.file_name}</div>
      <div style="font-size:11px;color:var(--text3)">Version: ${doc.file_version || 1} &middot; Status: ${doc.document_status || 'submitted'}</div>
      <div style="font-size:11px;color:var(--text3);margin-top:2px">Submitted: ${doc.created_at || 'N/A'}</div>
    </div>
    ${doc.file_path ? `<a href="${doc.file_path}" target="_blank" class="btn btn-ghost" style="font-size:11px;display:inline-flex;gap:6px"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Download</a>` : ''}
  `;

  if (docReviews.length === 0) {
    reviewsDiv.innerHTML = `<p style="font-size:13px;color:var(--text3)">No reviews yet for this document.</p>`;
  } else {
    reviewsDiv.innerHTML = docReviews.map((r, i) => `
      <div class="annotation-box" style="${r.review_status === 'needs_revision' ? 'background:var(--red-bg);border-color:var(--red)' : r.review_status === 'approved' ? 'background:rgba(22,163,74,0.06);border-color:rgba(22,163,74,0.2)' : ''}">
        <div class="annotation-user" style="${r.review_status === 'needs_revision' ? 'color:var(--red)' : ''}">${r.expert?.name || 'Expert'} &middot; Review ${i + 1}</div>
        <div class="annotation-text">${r.comments || 'No comments provided.'}</div>
        <div style="margin-top:6px;display:flex;gap:8px;align-items:center">
          <span class="badge ${r.review_status === 'approved' ? 'badge-green' : r.review_status === 'needs_revision' ? 'badge-red' : 'badge-amber'}">${r.review_status || 'pending'}</span>
          <span style="font-size:10px;color:var(--text3)">${r.reviewed_at || ''}</span>
        </div>
      </div>
    `).join('');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const sel = document.getElementById('doc-select');
  if (sel.options.length > 0) {
    sel.dispatchEvent(new Event('change'));
  }
});
</script>
@endif
</div>
</div>

</body>
</html>
