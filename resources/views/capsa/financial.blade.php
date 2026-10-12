<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Financial Records - CapsTRACK</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
@vite(['resources/css/financial.css', 'resources/js/financial/capsa.js'])
  @include('partials.render-whole')
</head>
<body>

@include('capsa._sidebar')
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Financial Records</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  {{-- Drawn by resources/js/financial/capsa.js from the JSON below. --}}
  <div class="fx" id="fx-capsa"
       data-state="{{ json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
       data-urls="{{ json_encode([
           'record' => route('api.admin.financial.update', '__ID__'),
           'records' => route('api.admin.financial.update-many'),
           'fees' => route('api.admin.financial.expense-posts.store'),
       ], JSON_UNESCAPED_SLASHES) }}">

    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
      <symbol id="fx-i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></symbol>
      <symbol id="fx-i-undo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></symbol>
      <symbol id="fx-i-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></symbol>
      <symbol id="fx-i-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></symbol>
      <symbol id="fx-i-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></symbol>
    </svg>

    {{-- The page's name is in the top bar; the fees and New fee come first here. --}}
    <header class="fx-page-head">
      <div class="fx-tabs" role="tablist" aria-label="Fee" id="c-fees" @if (collect($state['fees'] ?? [])->isEmpty()) hidden @endif></div>
      <div class="fx-actions"><button class="fx-btn" id="c-new-fee"><svg><use href="#fx-i-plus"/></svg>New fee</button></div>
    </header>

    <div id="c-body" style="flex:1;min-height:0;display:flex;flex-direction:column">
      <div class="fx-summary">
        <span id="c-fee-line"></span>
        <div class="fx-bar" id="c-bar" aria-hidden="true"></div>
        <div class="fx-legend" id="c-legend"></div>
        <span class="fx-grow"></span>
        <span class="fx-muted" style="font-size:12.5px">Your sections: {{ ($state['sections'] ?? []) ? implode(', ', $state['sections']) : 'All sections' }}</span>
      </div>
      <div class="fx-split">
        <section class="fx-list" aria-label="Receipts">
          <div class="fx-toolbar">
            <div class="fx-chips" id="c-chips" role="group" aria-label="Show"></div>
            <span class="fx-grow"></span>
            <select class="fx-field" id="c-team" aria-label="Team"><option value="">All teams</option></select>
            <label class="fx-field" style="display:flex;align-items:center;gap:6px;width:200px"><svg width="15" height="15" class="fx-muted"><use href="#fx-i-search"/></svg><input id="c-search" type="search" placeholder="Search member" aria-label="Search member" style="border:0;outline:0;width:100%;background:none"></label>
          </div>
          <div class="fx-bulk" id="c-bulk" hidden>
            <strong id="c-bulk-n"></strong><span class="fx-grow"></span>
            <button class="fx-btn" id="c-bulk-clear">Clear</button>
            <button class="fx-btn fx-btn-ok" id="c-bulk-verify"><svg><use href="#fx-i-check"/></svg>Verify selected</button>
          </div>
          <div class="fx-scroll" tabindex="0" aria-label="Receipt list, use J and K or the arrow keys to move">
            <table data-export="Receipts">
              <colgroup><col style="width:52px"><col><col class="fx-col-team" style="width:150px"><col class="fx-col-up" style="width:130px"><col style="width:150px"></colgroup>
              <thead><tr><th><input type="checkbox" id="c-all" aria-label="Select every waiting receipt shown"></th><th>Member</th><th class="fx-col-team">Section</th><th class="fx-col-up">Uploaded</th><th>Status</th></tr></thead>
              <tbody id="c-rows"></tbody>
            </table>
          </div>
        </section>
        <aside class="fx-inspector" aria-label="Receipt" id="c-insp"></aside>
      </div>
    </div>
  </div>
</div>

<dialog id="fx-d-image" class="fx-dialog fx-image" aria-label="Receipt, full size"><img id="fx-img-full" alt=""></dialog>

<dialog id="fx-d-fee" class="fx-dialog" aria-labelledby="fx-d-fee-t">
  <div class="fx-dlg-head"><h2 id="fx-d-fee-t">New fee</h2><button class="fx-icon-btn" data-close aria-label="Close"><svg><use href="#fx-i-x"/></svg></button></div>
  <div class="fx-dlg-body">
    <div><label class="fx-lbl" for="f-stage">Defense</label>
      <select class="fx-field" id="f-stage"><option>Title Defense</option><option>Pre Oral Defense</option><option>Final Oral Defense</option></select>
      <div class="fx-muted" style="font-size:12px;margin-top:4px">Pre-proposal Defense has no fee.</div></div>
    <div class="fx-two">
      <div><label class="fx-lbl" for="f-amount">Per member (₱)</label><input class="fx-field" id="f-amount" type="number" inputmode="numeric" min="0" step="50"></div>
      <div><label class="fx-lbl" for="f-program">Program</label><select class="fx-field" id="f-program"></select></div>
    </div>
    <div><label class="fx-lbl" for="f-note">Where to pay <span class="fx-muted" style="font-weight:400">(optional)</span></label><input class="fx-field" id="f-note"></div>
  </div>
  <div class="fx-dlg-foot"><button class="fx-btn" data-close>Cancel</button><button class="fx-btn fx-btn-primary" id="f-save">Save fee</button></div>
</dialog>

<script nonce="{{ Vite::cspNonce() }}">
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() { if (sidebarEl) { sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar(); } }
function openSidebar() { if (!sidebarEl || !sidebarOverlay) return; sidebarEl.classList.add('mobile-open'); sidebarOverlay.classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeSidebar() { if (!sidebarEl || !sidebarOverlay) return; sidebarEl.classList.remove('mobile-open'); sidebarOverlay.classList.remove('open'); document.body.style.overflow = ''; }
</script>
@include('partials.app-dialog')
@include('partials.escape-closes-modals')
</body>
</html>
