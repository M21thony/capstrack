<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Document Submission - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
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
    {{-- The form, and beside it what the team has already sent - the answer
         to "did it go through?" without leaving the page. --}}
    <div class="sub-page">
      <section class="sub-card" aria-labelledby="sub-form-h">
        <div class="sub-card-h">
          <h2 id="sub-form-h">Submit a document</h2>
          <p>Upload your work and send it for review.</p>
        </div>
        @include('student.partials.submission-form')
      </section>

      <section class="sub-card" aria-labelledby="sub-list-h">
        <div class="sub-card-h">
          <h2 id="sub-list-h">Your team's submissions</h2>
          <p>Newest first. Open the Review Viewer to read a reviewer's remarks.</p>
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
</div>

@include('partials.escape-closes-modals')
<script>
function toggleSidebar() { document.body.classList.toggle('sidebar-open'); }
function closeSidebar() { document.body.classList.remove('sidebar-open'); }
</script>
</body>
</html>
