<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Review Viewer - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite(['resources/student-ui/styles.css', 'resources/js/student-review.jsx'])
<x-dashboard.styles />
<style>
  #pageSkeleton { position: fixed; inset: 0; z-index: 9999; background: var(--bg, #f2f3f5); transition: opacity 0.6s ease, visibility 0.6s ease; }
  #pageSkeleton.hidden { opacity: 0; visibility: hidden; pointer-events: none; }
  #contentWrapper { opacity: 1; transition: opacity 0.6s ease; }
  #contentWrapper.loading { opacity: 0; }
</style>
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
    <div id="contentWrapper" class="loading">

    {{-- Skeleton loader --}}
    <div id="pageSkeleton">
      <div class="row" style="margin-bottom:14px;gap:8px">
        <div class="skeleton-box" style="width:320px;height:36px;border-radius:8px"></div>
        <div class="skeleton-box" style="width:90px;height:20px;border-radius:12px"></div>
        <span style="flex:1"></span>
        <div class="skeleton-box" style="width:100px;height:28px;border-radius:8px"></div>
      </div>
      <div class="grid-2">
        <div class="card" style="height:720px;padding:16px">
          <div class="skeleton-box" style="width:100%;height:100%;border-radius:8px"></div>
        </div>
        <div class="card" style="height:720px">
          <div class="skeleton-box" style="width:160px;height:14px;border-radius:4px;margin-bottom:16px"></div>
          @for ($i = 0; $i < 4; $i++)
          <div class="annotation-box">
            <div class="skeleton-box" style="width:40%;height:10px;border-radius:4px;margin-bottom:8px"></div>
            <div class="skeleton-box" style="width:90%;height:11px;border-radius:4px;margin-bottom:6px"></div>
            <div class="skeleton-box" style="width:70%;height:11px;border-radius:4px"></div>
          </div>
          @endfor
        </div>
      </div>
    </div>

    {{-- Content --}}

@if ($turnedIn->isEmpty())
    <div class="card" style="padding:24px;text-align:center;color:var(--text3)">
        No turned-in reviews yet. Once a reviewer marks one of your submissions as reviewed, it'll show up here.
    </div>
@else
<div class="row" style="margin-bottom:14px;gap:8px">
  <select class="input" style="width:320px" onchange="window.location.href='{{ route('student.review') }}?review_id=' + this.value">
    @foreach ($turnedIn as $entry)
      <option value="{{ $entry['review']->review_id }}" @selected($selected && $entry['review']->review_id === $selected['review']->review_id)>
        {{ $entry['document']->file_name }} — {{ $entry['document']->chapter }} — {{ ucfirst($entry['review']->role) }}
        by {{ optional($entry['review']->expert)->name ?? 'Unknown Reviewer' }}
        ({{ optional($entry['review']->reviewed_at)->format('M j, Y') ?? '—' }})
      </option>
    @endforeach
  </select>
  <span class="badge badge-amber">{{ $totalAnnotations }} Annotation{{ $totalAnnotations === 1 ? '' : 's' }}</span>
  @if ($selected)
    <span class="badge badge-blue">Reviewed by {{ optional($selected['review']->expert)->name ?? 'Unknown Reviewer' }}</span>
  @endif
  <span style="flex:1"></span>
  @if ($selected)
    <a href="{{ route('student.review.clf', $selected['review']->review_id) }}" class="btn btn-ghost" style="font-size:11px;text-decoration:none">Download CLF</a>
  @endif
  @if ($selected && $selected['review']->drive_web_view_link)
    <a href="{{ $selected['review']->drive_web_view_link }}" target="_blank" rel="noopener" class="btn btn-ghost" style="font-size:11px;text-decoration:none">Download Annotated PDF</a>
  @endif
</div>
<div class="grid-2">
  <div class="card" style="height:720px;overflow-y:auto;padding:0">
    <div class="card-title" style="padding:16px 16px 0">Document Preview</div>
    @if ($selected)
      <div
        id="student-pdf-viewer-root"
        data-pdf-url="{{ route('student.review.pdf', $selected['review']->review_id) }}"
        data-remarks-url="{{ route('student.review.remarks', $selected['review']->review_id) }}"
      ></div>
    @else
      <div class="doc-preview" style="margin:16px">
        No document to preview.
      </div>
    @endif
  </div>
  <div class="card" style="height:720px;overflow-y:auto">
    <div class="card-title" style="margin-bottom:12px">
      Expert Annotations
      @if ($selected)
        &mdash; {{ optional($selected['review']->expert)->name ?? 'Unknown Reviewer' }}
      @endif
    </div>

    @if ($generalRemarks->isEmpty() && $remarksBySection->isEmpty() && $remarks->isEmpty())
      <p style="font-size:13px;color:var(--text3)">No comments were left on this review.</p>
    @endif

    @if ($generalRemarks->isNotEmpty())
      <div class="annotation-box" style="background:var(--blue-bg);border-color:var(--blue)">
        <div class="annotation-user" style="color:var(--blue)">General Comment</div>
        @foreach ($generalRemarks as $index => $comment)
          <div class="annotation-text" style="margin-bottom:{{ $loop->last ? '0' : '8px' }}">{{ $loop->iteration }}. {{ $comment }}</div>
        @endforeach
      </div>
    @endif

    @foreach ($remarksBySection as $section => $items)
      <div class="annotation-box">
        <div class="annotation-user">{{ $section }}</div>
        @foreach ($items as $item)
          <div
            class="annotation-text"
            style="margin-bottom:{{ $loop->last ? '0' : '8px' }}{{ $item->is_general ? '' : ';cursor:pointer' }}"
            @unless($item->is_general)
              onclick="window.dispatchEvent(new CustomEvent('student-review:scroll-to-remark', { detail: { page: {{ $item->page_number }}, remarkId: {{ $item->id }} } }))"
              title="Click to jump to this comment in the document"
            @endunless
          >&bull; {{ $item->comment }}</div>
        @endforeach
      </div>
    @endforeach

    @if ($remarks->isNotEmpty())
      <div class="annotation-box">
        @foreach ($remarks as $remark)
          <div
            class="annotation-text"
            style="margin-bottom:{{ $loop->last ? '0' : '8px' }};cursor:pointer"
            onclick="window.dispatchEvent(new CustomEvent('student-review:scroll-to-remark', { detail: { page: {{ $remark->page_number }}, remarkId: {{ $remark->id }} } }))"
            title="Click to jump to this comment in the document"
          >&bull; {{ $remark->comment }}</div>
        @endforeach
      </div>
    @endif
  </div>
</div>
@endif
    </div>
</div>
</div>

<script>
var __skeleton = document.getElementById('pageSkeleton');
var __content = document.getElementById('contentWrapper');
if (__skeleton && __content) {
  (function() {
    function hideSkeleton() {
      __skeleton.classList.add('hidden');
      __content.classList.remove('loading');
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', hideSkeleton);
    } else {
      hideSkeleton();
    }
  })();
}
</script>

</body>
</html>
