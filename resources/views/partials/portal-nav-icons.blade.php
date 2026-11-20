{{--
  How a sidebar link wears its icon, in every portal.

  Each portal has its own stylesheet (admin.css, expert.css and the student
  ones), and each drew the icon as a 16px outline at 60% opacity - small and
  faint enough on the navy sidebar that the links read as text alone. This
  sets one look for all of them: the icon sits on its own small tile, the
  tile lifts on hover, and the page you are on gets a solid gold one, so
  where you are shows at a glance even with the labels skimmed.

  Included by each portal's sidebar partial, like partials/mobile-tabbar.
--}}
@once
<style nonce="{{ Vite::cspNonce() }}">
  .sidebar .nav-item{gap:11px;padding-top:6px;padding-bottom:6px}
  .sidebar .nav-item > svg{
    --ic:#cbd5e1;
    box-sizing:border-box;width:30px;height:30px;padding:6.5px;flex-shrink:0;
    border-radius:9px;background:color-mix(in srgb,var(--ic) 14%,transparent);
    color:var(--ic);opacity:1!important;
    fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round;
    transition:background .15s,color .15s,box-shadow .15s,transform .15s;
  }
  /* Each kind of page keeps its own colour, so the list can be skimmed by colour. */
  .sidebar svg:is([data-icon=dashboard],[data-icon=home]){--ic:#7dd3fc}
  .sidebar svg:is([data-icon=team],[data-icon=teams-manage],[data-icon=expert],[data-icon=student],[data-icon=section-list],[data-icon^=user-]){--ic:#c4b5fd}
  .sidebar svg:is([data-icon^=file-],[data-icon=feedback],[data-icon=similarity],[data-icon=inbox],[data-icon=submit],[data-icon=add]){--ic:#93c5fd}
  .sidebar svg:is([data-icon=archive],[data-icon=book]){--ic:#a5b4fc}
  .sidebar svg:is([data-icon=defense],[data-icon=grading],[data-icon=rubric],[data-icon=hourglass],[data-icon=alarm],[data-icon=milestone],[data-icon=target],[data-icon=verified]){--ic:#86efac}
  .sidebar svg[data-icon=consultation]{--ic:#fdba74}
  .sidebar svg[data-icon=announcement]{--ic:#fda4af}
  .sidebar svg[data-icon=trophy]{--ic:#f9a8d4}
  .sidebar svg[data-icon=receipt]{--ic:#5eead4}
  .sidebar svg:is([data-icon=reports],[data-icon=analytics],[data-icon=progress]){--ic:#67e8f9}
  .sidebar svg[data-icon=sign-out]{--ic:#fca5a5}
  .sidebar .nav-item:hover > svg{background:color-mix(in srgb,var(--ic) 26%,transparent);color:#fff}
  .sidebar .nav-item:active > svg{transform:scale(.94)}
  .sidebar .nav-item.active > svg{
    background:var(--accent,#c9a82c);color:#18191f;
    box-shadow:0 4px 12px rgba(201,168,44,.32);
  }
  .sidebar .nav-item:focus-visible{outline:2px solid var(--accent,#c9a82c);outline-offset:-2px}
  @media (prefers-reduced-motion:reduce){
    .sidebar .nav-item > svg{transition:none}
    .sidebar .nav-item:active > svg{transform:none}
  }

  /* The sidebar's height is the screen that is actually showing. 100vh on a
     phone is the screen with the browser's bars hidden - Safari's toolbar,
     the address bar in Chrome and Brave - so while they were showing, the
     bottom of the sidebar sat behind them and Sign Out could not be seen.
     Every stylesheet now gives 100dvh after 100vh; a browser too old for dvh
     gets the visible height from the script below instead. The last link
     also clears the iPhone's home indicator. */
  .sidebar .nav{padding-bottom:calc(8px + env(safe-area-inset-bottom, 0px))}
  @supports not (height:100dvh){
    .sidebar,.sidebar.mobile-open{height:var(--app-height,100vh)!important}
    @media (max-width:768px){.main{height:var(--app-height,100vh)!important}}
  }
</style>
<script nonce="{{ Vite::cspNonce() }}">
  (function () {
    if (window.CSS && CSS.supports && CSS.supports('height', '100dvh')) return;
    var root = document.documentElement;
    function fit() { root.style.setProperty('--app-height', window.innerHeight + 'px'); }
    fit();
    window.addEventListener('resize', fit);
    window.addEventListener('orientationchange', function () { setTimeout(fit, 250); });
  })();
</script>
@endonce
