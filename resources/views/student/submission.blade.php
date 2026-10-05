<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content" />
<title>Document Submission - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
@vite(['resources/student-ui/styles.css'])
<x-dashboard.styles />
</head>
<body>
@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Document Submission</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>

  <div class="content">
    {{-- What the team has sent. Sending something new opens the same small
         pop-up as every other upload - there is no form on this page. --}}
    <section class="sub-card" aria-labelledby="sub-list-h">
      <div class="sub-card-h">
        <div>
          <h2 id="sub-list-h">Your team's submissions</h2>
          <p>Newest first. Open the Review Viewer to read a reviewer's remarks.</p>
        </div>
        <button type="button" class="up-btn primary" onclick="window.openSubmission && window.openSubmission('', this)"
                @if ($selectedItemId) data-open-upload="{{ $selectedItemId }}" @endif>
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          Submit a document
        </button>
      </div>
      @if ($submitted->isEmpty())
        <p class="sub-empty">Nothing sent yet. What you submit shows up here.</p>
      @else
        <ul class="sub-list">
          @foreach ($submitted as $doc)
            <li>
              <b title="{{ $doc->name }}">{{ $doc->for }}</b>
              <span class="sub-status {{ $doc->status[1] }}">{{ $doc->status[0] }}</span>
              <small>{{ $doc->name }} &middot; version {{ $doc->version }} &middot; {{ $doc->sent?->format('M j, Y g:i A') }}</small>
            </li>
          @endforeach
        </ul>
      @endif
    </section>
  </div>
</div>

@include('partials.escape-closes-modals')
<script nonce="{{ Vite::cspNonce() }}">
function toggleSidebar() { document.body.classList.toggle('sidebar-open'); }
function closeSidebar() { document.body.classList.remove('sidebar-open'); }
</script>
</body>
</html>
