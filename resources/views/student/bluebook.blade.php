<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content" />
<title>Bluebook Clearance - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
@vite('resources/student-ui/styles.css')
<x-dashboard.styles />
<style nonce="{{ Vite::cspNonce() }}">
.bs-head{display:flex;align-items:center;gap:12px 20px;flex-wrap:wrap;padding:14px 18px;margin-bottom:12px}
.bs-head b{font-size:16px}
.bs-head span{font-size:13px;color:var(--text2)}
.bs-head .bs-count{margin-left:auto;font-weight:600;color:var(--text)}
.bs-msg{padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:12px}
.bs-msg.ok{background:var(--green-bg);color:var(--green)}
.bs-msg.err{background:var(--red-bg);color:var(--red)}
.bs-list{padding:0}
.bs-line{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,420px);gap:10px 20px;padding:14px 18px;border-bottom:1px solid var(--border)}
.bs-line:last-child{border-bottom:0}
.bs-line h3{margin:0;font-size:14px}
.bs-line p{margin:3px 0 0;font-size:12.5px;color:var(--text2);white-space:pre-line}
.bs-st{font-size:12.5px;font-weight:600;margin-top:6px}
.bs-st.done{color:var(--green)}.bs-st.back{color:var(--red)}.bs-st.dean{color:var(--accent3)}.bs-st.none{color:var(--text3)}
.bs-note{margin-top:4px;font-size:12.5px;color:var(--red)}
.bs-file{display:inline-flex;gap:6px;align-items:center;margin-top:6px;font-size:12.5px;color:var(--text);background:none;border:0;padding:0;font-family:inherit;cursor:pointer;text-decoration:underline}
.bs-form{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:6px;align-self:center}
.bs-form input{min-width:0;font:inherit;font-size:12.5px;border:1px solid var(--border);border-radius:8px;padding:7px 9px;background:var(--bg)}
.bs-form .bs-or{grid-column:1/-1;font-size:11px;color:var(--text3);text-align:center;margin:-2px 0}
.bs-form button{grid-row:1/span 3;grid-column:2;align-self:stretch;min-width:110px}
.bs-video{border:0;border-radius:12px;padding:0;width:min(960px,94vw);margin:auto;background:#000}
.bs-video::backdrop{background:rgba(0,0,0,.7)}
.bs-video iframe{display:block;width:100%;aspect-ratio:16/9;border:0}
.bs-video form{position:absolute;top:6px;right:6px}
.bs-video form button{background:rgba(0,0,0,.6);color:#fff;border:0;border-radius:8px;padding:6px 10px;font:inherit;font-size:12px;cursor:pointer}
@media (max-width:760px){.bs-line{grid-template-columns:minmax(0,1fr)}}
</style>
  @include('partials.render-whole')
</head>
<body>
@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title"><a href="{{ route('student.progress') }}" style="color:var(--text3);text-decoration:none">Our milestones</a> <span style="color:var(--text3)">›</span> Bluebook Clearance</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
@if (! $bluebook)
  <div class="empty-state" style="text-align:center;padding:60px 20px">
    <div style="font-size:15px;font-weight:600;color:var(--text2)">No Bluebook Clearance on your checklist</div>
  </div>
@else
  @if (session('success'))<div class="bs-msg ok" role="status">{{ session('success') }}</div>@endif
  @if ($errors->any())<div class="bs-msg err" role="alert">{{ $errors->first() }}</div>@endif

  <div class="card bs-head">
    <b>Bluebook Clearance</b>
    @if ($bluebook->cleared)
      <span>Cleared {{ $bluebook->cleared_on->format('M j, Y') }} · {{ $bluebook->slip_no }}</span>
    @elseif ($bluebook->locked)
      <span>Opens after {{ $bluebook->requires }}</span>
    @endif
    <span class="bs-count">{{ $bluebook->accepted }} of {{ $bluebook->lines->count() }} accepted</span>
  </div>

  <div class="card bs-list">
    @foreach ($bluebook->lines as $row)
      @php
        $done = \App\Support\Bluebook::done($row);
        $video = $row->file ? \App\Support\Bluebook::driveId($row->file->file_path) : null;
        [$cls, $word] = match (true) {
          $done => ['done', 'Accepted '.$row->closed_at?->format('M j, Y')],
          $row->waiting_on === 'team' => ['back', 'Sent back'],
          $row->waiting_on === 'dean' => ['dean', "With the Dean's Office"],
          default => ['none', 'Not sent'],
        };
      @endphp
      <div class="bs-line">
        <div>
          <h3>{{ $row->item->title }}</h3>
          @if ($row->item->instructions)<p>{{ $row->item->instructions }}</p>@endif
          <div class="bs-st {{ $cls }}">{{ $word }}</div>
          @if ($row->note)<div class="bs-note">{{ $row->note }}</div>@endif
          @if ($video)
            <button type="button" class="bs-file" data-video="https://drive.google.com/file/d/{{ $video }}/preview">▶ Play video</button>
          @elseif ($row->file)
            <a class="bs-file" href="{{ route('student.bluebook.file', $row->file) }}" target="_blank" rel="noopener">{{ $row->file->file_name }}</a>
          @endif
        </div>
        @unless ($done || $row->locked)
          <form class="bs-form" method="POST" action="{{ route('student.bluebook.store', $row->item) }}" enctype="multipart/form-data">
            @csrf
            @php
              $type = $row->item->fileType();
              $endings = array_diff($row->item->accepts(['pdf', 'docx']), ['*']);
              $takesFile = $type !== 'link';
              $takesLink = in_array($type, [null, 'link', 'any'], true);
            @endphp
            @if ($takesFile)
              <input type="file" name="file" accept="{{ implode(',', array_map(fn ($e) => '.'.$e, $endings)) }}" aria-label="{{ $row->item->fileWords() ?? 'PDF or DOCX' }} for {{ $row->item->title }}">
            @endif
            @if ($takesFile && $takesLink)
              <span class="bs-or">or</span>
            @endif
            @if ($takesLink)
              <input type="url" name="link" placeholder="Google Drive video link" aria-label="Drive link for {{ $row->item->title }}">
            @endif
            <button class="btn btn-primary" type="submit">{{ $row->file ? 'Send again' : 'Send' }}</button>
          </form>
        @endunless
      </div>
    @endforeach
  </div>

  <dialog class="bs-video" id="bs-video" aria-label="Video">
    <form method="dialog"><button>Close</button></form>
    <iframe allow="autoplay; fullscreen" allowfullscreen title="Video"></iframe>
  </dialog>
@endif
  </div>
</div>

<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var dialog = document.getElementById('bs-video');
  if (! dialog) return;
  var frame = dialog.querySelector('iframe');
  document.querySelectorAll('[data-video]').forEach(function (b) {
    b.addEventListener('click', function () { frame.src = b.dataset.video; dialog.showModal(); });
  });
  dialog.addEventListener('close', function () { frame.src = 'about:blank'; });
})();
</script>
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
<script nonce="{{ Vite::cspNonce() }}">
function toggleSidebar() { document.body.classList.toggle('sidebar-open'); }
function closeSidebar() { document.body.classList.remove('sidebar-open'); }
</script>
</body>
</html>
