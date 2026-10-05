<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content" />
<title>Financial Records - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
@vite(['resources/student-ui/styles.css', 'resources/css/financial.css', 'resources/js/financial/student.js'])
<x-dashboard.styles />
<style nonce="{{ Vite::cspNonce() }}">
  /* The developers' credit (placed by partials/mobile-tabbar) at the size the
     student dashboard gives it. The receipt list's own 1.45 line height and
     type made it read a size larger here than anywhere else in the portal. */
  #fx-student .sys-credits{padding:12px 12px 14px;line-height:normal}
  #fx-student .sys-credits .credits-title{font-size:8px}
  #fx-student .sys-credits .credits-names{font-size:9px}
  /* The how-to card sits straight in .main on this page, with no .content
     around it, so it gets the room .content gives it everywhere else rather
     than touching the top bar and the screen's edge. */
  .main > .page-tutorial{margin:16px 20px}
  @media (max-width:768px){
    .main > .page-tutorial{margin:12px}
    #fx-student .sys-credits{padding:10px 12px}
    #fx-student .sys-credits .credits-title{font-size:7px}
    #fx-student .sys-credits .credits-names{font-size:8px}
  }
</style>
</head>
<body>
@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Financial Records</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>

  @include('student.partials.page-tutorial', ['page' => 'financial'])

  {{-- Drawn by resources/js/financial/student.js from the JSON below. --}}
  <div class="fx" id="fx-student"
       data-state="{{ json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
       data-urls="{{ json_encode(['upload' => route('api.student.financial.submit'), 'submitTeam' => route('api.student.financial.submit-team')], JSON_UNESCAPED_SLASHES) }}">

    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
      <symbol id="fx-i-upload" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/></symbol>
      <symbol id="fx-i-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></symbol>
      <symbol id="fx-i-camera" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3z"/><circle cx="12" cy="13" r="3"/></symbol>
    </svg>

    <div class="fx-student">
      <header class="fx-page-head">
        <div><h1 style="margin-bottom:2px">Financial Records</h1><p class="fx-who">
          @if ($state['team'])
            {{ Str::startsWith(Str::lower($state['team']['name']), 'team') ? '' : 'Team ' }}{{ $state['team']['name'] }}@if ($state['section']) · {{ $state['section'] }}@endif
          @else
            You are not on a team yet.
          @endif
        </p></div>
        <div class="fx-tabs" role="tablist" aria-label="Fee" id="s-fees"></div>
      </header>
      <div class="fx-mine" id="s-mine"></div>
      <div class="fx-team-head"><h2>Your team</h2><span class="fx-muted" style="font-size:13px">All must be verified before the team can be scheduled.</span><span class="fx-grow"></span><div class="fx-bar" id="s-bar" style="width:140px"></div><span class="fx-num fx-muted" id="s-count"></span></div>
      {{-- Members hand their receipt to the leader; only the leader sends the
           team's receipts on to the CaPSA, and only once all are in. --}}
      <div class="fx-team-head" style="border-top:0"><span class="fx-muted" id="s-team-note" style="font-size:13px"></span><span class="fx-grow"></span><button type="button" class="fx-btn fx-btn-primary" id="s-team-submit" hidden>Send to CaPSA</button></div>
      <div class="fx-scroll">
        <table>
          <colgroup><col><col style="width:160px"><col class="fx-col-up" style="width:130px"></colgroup>
          <thead><tr><th>Member</th><th>Status</th><th class="fx-col-up">Uploaded</th></tr></thead>
          <tbody id="s-team"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<dialog id="fx-d-image" class="fx-dialog fx-image" aria-label="Receipt, full size"><img id="fx-img-full" alt=""></dialog>

{{-- Both, together: the OR number as typed and the photo it has to match.
     The CaPSA checks one against the other before verifying. --}}
<dialog id="fx-d-upload" class="fx-dialog" aria-labelledby="fx-d-upload-t">
  <div class="fx-dlg-head"><h2 id="fx-d-upload-t">Upload your receipt</h2><button class="fx-icon-btn" data-close aria-label="Close"><svg><use href="#fx-i-x"/></svg></button></div>
  <div class="fx-dlg-body">
    <div><label class="fx-lbl" for="u-or">OR number</label>
      <input class="fx-field" id="u-or" autocomplete="off" maxlength="40" placeholder="As printed on the official receipt" aria-describedby="u-or-help">
      <div class="fx-muted" id="u-or-help" style="font-size:12px;margin-top:4px">Your CaPSA checks this against the number in the photo once your team leader sends it.</div></div>
    <div><span class="fx-lbl">Photo of the receipt</span>
      {{-- No capture attribute: it made a phone open the camera and nothing
           else, so a receipt already in the gallery could not be chosen. Without
           it the phone offers both - Take Photo and the photo library. --}}
      <label class="fx-btn fx-file" id="u-pick"><svg><use href="#fx-i-camera"/></svg><span id="u-pick-t">Take or choose a photo</span><input type="file" id="u-file" accept="image/*" aria-label="Photo of your receipt"></label>
      <img id="u-preview" alt="The receipt you picked" hidden style="display:block;margin-top:10px;max-width:100%;max-height:220px;border-radius:8px;border:1px solid var(--border,#e0e2e8)"></div>
    <div id="u-err" role="alert" style="font-size:12.5px;color:#b42318" hidden></div>
  </div>
  <div class="fx-dlg-foot"><button class="fx-btn" data-close>Cancel</button><button class="fx-btn fx-btn-primary" id="u-send"><svg><use href="#fx-i-upload"/></svg><span id="u-send-t">Give to team leader</span></button></div>
</dialog>

<script nonce="{{ Vite::cspNonce() }}">
function toggleSidebar() {
  document.body.classList.toggle('sidebar-open');
  document.body.style.overflow = document.body.classList.contains('sidebar-open') ? 'hidden' : '';
}
function closeSidebar() {
  document.body.classList.remove('sidebar-open');
  document.body.style.overflow = '';
}
document.querySelectorAll('.nav-item').forEach(function (el) {
  el.addEventListener('click', function () { if (window.innerWidth <= 768) closeSidebar(); });
});
</script>
@include('partials.escape-closes-modals')
</body>
</html>
