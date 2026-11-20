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
           'dean' => route('api.admin.financial.send-to-dean'),
       ], JSON_UNESCAPED_SLASHES) }}">

    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
      <defs>
        <symbol id="ct-i-search" viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="7.3"/><path d="m16.1 16.1 4.5 4.5"/></symbol>
        <symbol id="ct-i-down" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
        <symbol id="ct-i-eye" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></symbol>
        <symbol id="ct-i-download" viewBox="0 0 24 24"><path d="M12 3v12m-5-5 5 5 5-5M4 15v5h16v-5"/></symbol>
        <symbol id="ct-i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
        <symbol id="ct-i-close" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></symbol>
        <symbol id="ct-i-left" viewBox="0 0 24 24"><path d="m14 6-6 6 6 6"/></symbol>
        <symbol id="ct-i-right" viewBox="0 0 24 24"><path d="m10 6 6 6-6 6"/></symbol>
        <symbol id="ct-i-sort" viewBox="0 0 24 24"><path d="M8 18V5M4 9l4-4 4 4M16 6v13m-4-4 4 4 4-4"/></symbol>
        <symbol id="ct-i-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol>
        <symbol id="ct-i-report" viewBox="0 0 24 24"><path d="M5 20V10M12 20V4M19 20v-7"/></symbol>
        <symbol id="ct-i-send" viewBox="0 0 24 24"><path d="M21 3 10 14M21 3l-7 18-4-7-7-4 18-7Z"/></symbol>
      </defs>
    </svg>

    <div class="ct-page">
      <div class="ct-top">
        <div class="ct-feebar" id="ct-feebar">
          <label class="ct-select-wrap"><span class="ct-sr">Fee</span>
            <select class="ct-select" id="ct-fee"></select><svg class="ct-i"><use href="#ct-i-down"/></svg></label>
          <span class="ct-amount" id="ct-amount"></span>
          <div class="ct-progress-group">
            <span class="ct-progress-label" id="ct-progress-label"></span>
            <progress id="ct-progress" max="1" value="0" aria-label="Verified"></progress>
          </div>
        </div>
        <div class="ct-actions">
          <button class="ct-btn" id="c-send" type="button" disabled><svg class="ct-i"><use href="#ct-i-send"/></svg>Send to Dean<span class="ct-count" id="c-send-n">0</span></button>
          <button class="ct-btn ct-btn-primary" id="c-new-fee" type="button"><svg class="ct-i"><use href="#ct-i-plus"/></svg>New fee</button>
        </div>
      </div>

      <div class="ct-toolbar">
        <div class="ct-filters">
          <label class="ct-search"><span class="ct-sr">Search student or team</span>
            <svg class="ct-i"><use href="#ct-i-search"/></svg>
            <input id="ct-search" type="search" placeholder="Search student or team" autocomplete="off"></label>
          <label class="ct-section"><span>Section</span>
            <span class="ct-select-wrap"><select class="ct-select" id="ct-section"><option value="">All sections</option></select><svg class="ct-i"><use href="#ct-i-down"/></svg></span></label>
        </div>
        <div class="ct-actions">
          <button class="ct-btn" id="ct-report-btn" type="button"><svg class="ct-i"><use href="#ct-i-report"/></svg>View report</button>
          <button class="ct-btn" id="ct-export" type="button"><svg class="ct-i"><use href="#ct-i-download"/></svg>Export</button>
        </div>
      </div>

      <div class="ct-tabs" role="group" aria-label="Receipt status">
        <button type="button" data-status="all" aria-pressed="true">All <span class="ct-count" data-count="all">0</span></button>
        <button type="button" data-status="pending" aria-pressed="false">For review <span class="ct-count" data-count="pending">0</span></button>
        <button type="button" data-status="verified" class="ct-tab-ok" aria-pressed="false">Verified <span class="ct-count" data-count="verified">0</span></button>
        <button type="button" data-status="sent_back" aria-pressed="false">Sent back <span class="ct-count" data-count="sent_back">0</span></button>
        <button type="button" data-status="none" aria-pressed="false">Not uploaded <span class="ct-count" data-count="none">0</span></button>
        <span class="ct-student-count" id="ct-student-count"></span>
      </div>

      <div class="ct-table-box" id="ct-table-box">
        <table class="ct-table">
          <thead><tr>
            <th><button class="ct-sort" id="ct-sort" type="button" aria-label="Sort by student name">Student <svg class="ct-i"><use href="#ct-i-sort"/></svg></button></th>
            <th>Section</th><th>Uploaded</th><th>Status</th><th>Receipt</th>
          </tr></thead>
          <tbody id="c-rows"></tbody>
        </table>
        <div class="ct-empty" id="ct-empty" hidden>
          <svg class="ct-i"><use href="#ct-i-search"/></svg>
          <h3 id="ct-empty-t">No students found</h3>
          <p id="ct-empty-p">Try another name, section, or receipt status.</p>
          <button class="ct-btn" id="ct-clear" type="button">Clear filters</button>
        </div>
      </div>

      <footer class="ct-foot">
        <span id="ct-range"></span>
        <div class="ct-pager">
          <label class="ct-page-size">Rows
            <select id="ct-page-size"><option>10</option><option>25</option><option>50</option></select></label>
          <nav class="ct-pages" id="ct-pages" aria-label="Pages"></nav>
        </div>
      </footer>
    </div>

    {{-- The receipt, on the right; Verify and Send back are here. --}}
    <dialog class="ct-dialog ct-drawer" id="ct-drawer" aria-labelledby="ct-drawer-t">
      <div class="ct-dialog-head">
        <div><h2 id="ct-drawer-t"></h2><p id="ct-drawer-sub"></p></div>
        <button class="ct-icon-btn" type="button" data-close aria-label="Close"><svg class="ct-i"><use href="#ct-i-close"/></svg></button>
      </div>
      <div class="ct-dialog-body" id="ct-drawer-body"></div>
      <form id="ct-return-form" hidden>
        <label class="ct-field"><span>What needs to be corrected?</span>
          <textarea id="c-reason" rows="3" maxlength="600"></textarea></label>
        <div class="ct-quick" id="ct-quick"></div>
        <div class="ct-return-actions"><button class="ct-btn" type="button" id="ct-return-cancel">Cancel</button><button class="ct-btn ct-btn-danger" type="submit">Send back</button></div>
      </form>
      <div class="ct-dialog-foot" id="ct-drawer-foot">
        <button class="ct-btn" type="button" data-close>Close</button>
        <button class="ct-btn ct-btn-danger" type="button" id="ct-back-btn">Send back</button>
        <button class="ct-btn ct-btn-primary" type="button" id="ct-verify-btn"><svg class="ct-i"><use href="#ct-i-check"/></svg>Verify receipt</button>
      </div>
    </dialog>

    <dialog class="ct-dialog" id="ct-report" aria-labelledby="ct-report-t">
      <div class="ct-dialog-head">
        <div><h2 id="ct-report-t">Fee report</h2><p id="ct-report-sub"></p></div>
        <button class="ct-icon-btn" type="button" data-close aria-label="Close"><svg class="ct-i"><use href="#ct-i-close"/></svg></button>
      </div>
      <div class="ct-dialog-body">
        <div class="ct-report-grid" id="ct-report-grid"></div>
        <table class="ct-report-sections"><thead><tr><th>Section</th><th>Verified</th><th>Total</th></tr></thead><tbody id="ct-report-sections"></tbody></table>
        <div class="ct-report-total"><span>Verified amount</span><strong id="ct-report-total"></strong></div>
      </div>
      <div class="ct-dialog-foot">
        <button class="ct-btn" type="button" data-close>Close</button>
        <button class="ct-btn ct-btn-primary" type="button" id="ct-report-csv"><svg class="ct-i"><use href="#ct-i-download"/></svg>Download CSV</button>
      </div>
    </dialog>

    <dialog class="ct-dialog" id="ct-handoff" aria-labelledby="ct-handoff-t">
      <div class="ct-dialog-head">
        <div><h2 id="ct-handoff-t">Records for the Dean</h2><p>Verified receipts not sent yet, every fee and section.</p></div>
        <button class="ct-icon-btn" type="button" data-close aria-label="Close"><svg class="ct-i"><use href="#ct-i-close"/></svg></button>
      </div>
      <div class="ct-dialog-body">
        <div class="ct-handoff-summary">
          <div><span>Receipts</span><strong id="ct-handoff-n">0</strong></div>
          <div><span>Total</span><strong id="ct-handoff-total">₱0</strong></div>
        </div>
        <p class="ct-warn">A receipt cannot be changed once it is sent.</p>
      </div>
      <div class="ct-dialog-foot">
        <button class="ct-btn" type="button" data-close>Cancel</button>
        <button class="ct-btn" type="button" id="ct-handoff-csv"><svg class="ct-i"><use href="#ct-i-download"/></svg>Download report</button>
        <button class="ct-btn ct-btn-primary" type="button" id="ct-handoff-send"><svg class="ct-i"><use href="#ct-i-send"/></svg>Send to Dean</button>
      </div>
    </dialog>

    <dialog class="ct-dialog" id="fx-d-fee" aria-labelledby="fx-d-fee-t">
      <div class="ct-dialog-head">
        <div><h2 id="fx-d-fee-t">New fee</h2><p>Pre-proposal Defense has no fee.</p></div>
        <button class="ct-icon-btn" type="button" data-close aria-label="Close"><svg class="ct-i"><use href="#ct-i-close"/></svg></button>
      </div>
      <div class="ct-dialog-body">
        <label class="ct-field"><span>Defense</span>
          <span class="ct-select-wrap"><select class="ct-select" id="f-stage"><option>Title Defense</option><option>Pre Oral Defense</option><option>Final Oral Defense</option></select><svg class="ct-i"><use href="#ct-i-down"/></svg></span></label>
        <div class="ct-two">
          <label class="ct-field"><span>Per member (₱)</span><input id="f-amount" type="number" inputmode="numeric" min="0" step="50"></label>
          <label class="ct-field"><span>Program</span>
            <span class="ct-select-wrap"><select class="ct-select" id="f-program"></select><svg class="ct-i"><use href="#ct-i-down"/></svg></span></label>
        </div>
        <label class="ct-field"><span>Where to pay <em>(optional)</em></span><input id="f-note"></label>
      </div>
      <div class="ct-dialog-foot"><button class="ct-btn" type="button" data-close>Cancel</button><button class="ct-btn ct-btn-primary" type="button" id="f-save">Save fee</button></div>
    </dialog>
  </div>
</div>

<dialog id="fx-d-image" class="fx-dialog fx-image" aria-label="Receipt, full size"><img id="fx-img-full" alt=""></dialog>

<script nonce="{{ Vite::cspNonce() }}">
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() { if (sidebarEl) { sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar(); } }
function openSidebar() { if (!sidebarEl || !sidebarOverlay) return; sidebarEl.classList.add('mobile-open'); sidebarOverlay.classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeSidebar() { if (!sidebarEl || !sidebarOverlay) return; sidebarEl.classList.remove('mobile-open'); sidebarOverlay.classList.remove('open'); document.body.style.overflow = ''; }
</script>
@include('partials.app-dialog')
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>
