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
<style>
  .sidebar .nav-item{gap:11px;padding-top:6px;padding-bottom:6px}
  .sidebar .nav-item > svg{
    box-sizing:border-box;width:30px;height:30px;padding:6.5px;flex-shrink:0;
    border-radius:9px;background:rgba(255,255,255,.05);
    color:rgba(255,255,255,.62);opacity:1!important;
    fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round;
    transition:background .15s,color .15s,box-shadow .15s,transform .15s;
  }
  .sidebar .nav-item:hover > svg{background:rgba(255,255,255,.1);color:#fff}
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
</style>
@endonce
