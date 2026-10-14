{{--
  The sidebar's icon-only width, shared by every portal's sidebar.

  On a laptop the 250px menu took a fifth of the screen from the page beside
  it. From 1025px up the menu can fold to its icons: by default below 1280px,
  and on pages that need the room (grading, with $sidebarRail) at any width.
  The button at its foot folds or opens it, and that choice is remembered -
  separately for the roomy pages, so opening the menu on one does not open it
  everywhere. At 1024px and below the menu is the drawer behind the menu
  button, as before, and none of this applies.

  Included before the sidebar's own markup, so the width is settled before the
  menu is drawn and it never opens wide and then snaps shut.
--}}
<style nonce="{{ Vite::cspNonce() }}">
  .sidebar .sb-toggle{display:none}
  @media (min-width: 1025px){
    .sidebar .sb-toggle{display:flex;align-items:center;gap:10px;margin:6px 10px 10px;padding:0 12px;min-height:40px;border-radius:8px;border:1px solid rgba(255,255,255,.12);background:none;color:rgba(255,255,255,.6);font:inherit;font-size:12.5px;font-weight:600;cursor:pointer;flex-shrink:0}
    .sidebar .sb-toggle:hover{color:#fff;border-color:rgba(255,255,255,.25)}
    .sidebar .sb-toggle:focus-visible{outline:2px solid #c9a82c;outline-offset:2px}
    .sidebar .sb-toggle svg{width:16px;height:16px;flex-shrink:0;stroke:currentColor;fill:none;stroke-width:2;transition:transform .18s}

    html.sb-rail .sidebar{width:68px !important;min-width:68px !important}
    html.sb-rail .sidebar .sidebar-brand{justify-content:center;padding-left:0;padding-right:0}
    html.sb-rail .sidebar .sidebar-brand img{width:36px !important;height:36px !important}
    html.sb-rail .sidebar .brand-name,
    html.sb-rail .sidebar .brand-sub,
    html.sb-rail .sidebar .brand-badge > div,
    html.sb-rail .sidebar .user-info,
    html.sb-rail .sidebar .status-dot,
    html.sb-rail .sidebar .su-caret,
    html.sb-rail .sidebar .sidebar-user-caret{display:none !important}
    html.sb-rail .sidebar .sidebar-user{justify-content:center;padding-left:0;padding-right:0}
    html.sb-rail .sidebar .nav-section{font-size:0;padding:0;margin:10px 18px;height:1px;background:rgba(255,255,255,.08)}
    html.sb-rail .sidebar .nav-item{font-size:0;gap:0;justify-content:center;padding:5px 0;position:relative}
    html.sb-rail .sidebar .nav-item > svg{width:38px;height:38px;padding:9px}
    html.sb-rail .sidebar .nav-badge{position:absolute;top:1px;right:8px;margin:0;font-size:9px;padding:1px 5px;line-height:1.4}
    html.sb-rail .sidebar .sb-toggle{justify-content:center;padding:0;font-size:0}
    html.sb-rail .sidebar .sb-toggle svg{transform:rotate(180deg)}
    html.sb-rail .ep-modal,
    html.sb-rail .profile-modal{left:68px}
  }
  @media (prefers-reduced-motion: reduce){.sidebar .sb-toggle svg{transition:none}}
</style>
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var focus = {{ empty($sidebarRail) ? 'false' : 'true' }};
  var key = focus ? 'sidebarRailFocus' : 'sidebarRail';
  var root = document.documentElement;
  var pref = null;
  try { pref = localStorage.getItem(key); } catch (e) {}

  function isRail() {
    if (pref === '1') return true;
    if (pref === '0') return false;
    return focus || window.innerWidth < 1280;
  }

  function apply() {
    var rail = isRail();
    root.classList.toggle('sb-rail', rail);
    var btn = document.querySelector('.sidebar .sb-toggle');
    if (btn) {
      btn.setAttribute('aria-expanded', rail ? 'false' : 'true');
      btn.setAttribute('aria-label', rail ? 'Expand menu' : 'Collapse menu');
      btn.title = rail ? 'Expand menu' : '';
    }
  }

  apply();
  window.addEventListener('resize', apply);

  document.addEventListener('DOMContentLoaded', function () {
    var sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;

    // Folded, a menu item is only its icon - its name comes back on hover.
    sidebar.querySelectorAll('.nav-item').forEach(function (item) {
      if (!item.title) item.title = item.textContent.replace(/\s+/g, ' ').trim();
    });

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'sb-toggle';
    btn.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg><span>Collapse menu</span>';
    btn.addEventListener('click', function () {
      pref = isRail() ? '0' : '1';
      try { localStorage.setItem(key, pref); } catch (e) {}
      apply();
    });
    sidebar.appendChild(btn);
    apply();
  });
})();
</script>
