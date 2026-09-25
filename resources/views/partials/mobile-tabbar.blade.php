{{--
  The phone tab bar: a portal's most-used pages along the bottom of the
  screen, the way a phone app is laid out, and Menu for everything else.
  Shown only at phone width; on a wider screen the sidebar does this job.

  Included by each portal's sidebar partial, which knows the user's roles:
    $tabs = [[
      'label' => 'Home',
      'icon' => '<path d="..."/>',       // inner SVG, 24x24 viewBox
      'href' => route(...),              // or 'onclick' => '...'
      'active' => bool,
      'badge' => int,                    // optional
      'primary' => bool,                 // optional: the raised centre action
    ], ...]

  Menu opens the sidebar by pressing the page's own ☰ button, because every
  page already wires that one up, each in its own way.
--}}
@once
<style>
  .mtab{display:none}
  @media (max-width:768px){
    .mtab{
      display:grid;grid-auto-flow:column;grid-auto-columns:minmax(0,1fr);
      position:fixed;left:0;right:0;bottom:0;z-index:390;
      height:calc(62px + env(safe-area-inset-bottom));padding:0 4px env(safe-area-inset-bottom);
      background:#fff;border-top:1px solid var(--border);
      box-shadow:0 -6px 20px rgba(24,25,31,.07);
    }
    .mtab-item{
      position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;
      min-width:0;padding:6px 2px 4px;border:0;background:none;cursor:pointer;text-decoration:none;
      font-family:inherit;font-size:10.5px;font-weight:600;color:var(--text3);
      -webkit-tap-highlight-color:transparent;
    }
    .mtab-item span.lb{max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .mtab-ic{position:relative;display:flex;align-items:center;justify-content:center;width:44px;height:28px;border-radius:14px;transition:background .15s}
    .mtab-ic svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    .mtab-item:active .mtab-ic{background:var(--bg3)}
    .mtab-item.on{color:var(--text)}
    .mtab-item.on .mtab-ic{background:var(--accent-light,#faf7ed);color:var(--accent3,#8a6e10)}
    .mtab-badge{
      position:absolute;top:-3px;right:4px;min-width:16px;height:16px;padding:0 4px;border-radius:8px;
      background:var(--red,#dc2626);color:#fff;font-size:9.5px;font-weight:700;line-height:16px;text-align:center;
      box-shadow:0 0 0 2px #fff;
    }
    /* The one action a portal is for, raised out of the bar. */
    .mtab-item.primary .mtab-ic{
      width:50px;height:50px;margin-top:-26px;border-radius:17px;
      background:var(--accent);color:#18191f;box-shadow:0 6px 16px rgba(201,168,44,.45);
    }
    .mtab-item.primary .mtab-ic svg{width:24px;height:24px;stroke-width:2.4}
    .mtab-item.primary{color:var(--text)}

    /* The bar sits over the bottom of the screen, so the page ends above it.
       dvh: a phone's address bar comes and goes, and 100vh ignores it. */
    .main{height:100vh;height:100dvh;padding-bottom:calc(62px + env(safe-area-inset-bottom))}
    .toast,.toast-wrap,#submission-toast,#archive-toast{bottom:calc(76px + env(safe-area-inset-bottom))!important}

    /* Menu in the bar replaces the ☰ in the top bar. The button stays in the
       page, hidden, because Menu opens the sidebar by pressing it. */
    .topbar .hamburger-btn{display:none!important}
    .topbar{padding-left:16px}

    /* The notification panel, as a phone sheet: the width of the screen
       less a margin, from under the top bar all the way to the bottom of the
       screen however many notifications there are, with only the list
       scrolling. On a desktop it is a 380px dropdown hung off the bell, which
       on a phone ran past the left edge. !important: two student stylesheets
       set their own phone size with it. */
    .topbar .notif-dropdown{
      position:fixed!important;top:66px!important;left:10px!important;right:10px!important;
      bottom:calc(10px + env(safe-area-inset-bottom))!important;
      width:auto!important;max-width:none!important;height:auto!important;max-height:none!important;
      border-radius:16px!important;overflow:hidden;
      box-shadow:0 18px 50px rgba(15,17,23,.3)!important;
    }
    /* Nothing to show: the message sits in the middle of the sheet. */
    .topbar .notif-dropdown .notif-list:has(.notif-empty){display:flex;align-items:center;justify-content:center}
    .topbar .notif-dropdown.open{display:flex!important;flex-direction:column}
    .topbar .notif-dropdown > *{flex:none}
    .topbar .notif-dropdown .notif-list{flex:1 1 auto;min-height:0;max-height:none!important;overflow-y:auto;overscroll-behavior:contain}
    .topbar .notif-dropdown .notif-item{padding:13px 16px}
    .topbar .notif-dropdown .notif-item-title{font-size:13.5px}
    .topbar .notif-dropdown .notif-item-msg{font-size:12.5px}

    /* Behind it, a dim that closes it: tapping outside closes the panel and
       does nothing else, instead of also opening whatever was under the tap. */
    .mnotif-scrim.on{display:block;position:fixed;inset:0;z-index:9998;background:rgba(15,17,23,.4);animation:mnotifFade .15s ease}
  }
  .mnotif-scrim{display:none}
  @keyframes mnotifFade{from{opacity:0}to{opacity:1}}

  /* The developers' credit, at the foot of every page in every portal. It
     is the last thing in the page's scrolling area, so it is only seen at the
     end of the page and never sits over or beside anything. */
  .sys-credits{flex:none;width:100%;margin-top:40px;padding:16px 12px 18px;border-top:1px dashed var(--border);
    text-align:center;opacity:.55;clear:both}
  .sys-credits .credits-title{font-size:8px;font-weight:600;letter-spacing:1px;text-transform:uppercase;
    color:var(--text3);margin-bottom:4px}
  .sys-credits .credits-names{font-size:9.5px;font-weight:500;letter-spacing:.3px;color:var(--text3)}
</style>
<script>
  // Every portal's bell toggles .open on #notif-dropdown; the dim follows it.
  // A tap on the dim reaches the bells' own "click outside closes" handlers,
  // so this only has to show and hide it.
  document.addEventListener('DOMContentLoaded', function () {
    var panel = document.getElementById('notif-dropdown');
    if (!panel || !window.MutationObserver) {
      return;
    }
    var scrim = document.createElement('div');
    scrim.className = 'mnotif-scrim';
    scrim.setAttribute('aria-hidden', 'true');
    document.body.appendChild(scrim);
    new MutationObserver(function () {
      scrim.classList.toggle('on', panel.classList.contains('open'));
    }).observe(panel, { attributes: true, attributeFilter: ['class'] });
  });

  // The credits go at the end of the page's scrolling area. A page that has
  // no scrolling area - a full-screen tool like the grading sheet - is left
  // alone, since anything added there would take room from the page itself.
  // A page that already carries the credits keeps its own copy; one left
  // outside the scrolling area (pinned to the bottom of the screen) is moved
  // into it, so there is only ever one, and it is always last.
  document.addEventListener('DOMContentLoaded', function () {
    var main = document.querySelector('.main');
    if (!main) {
      return;
    }
    var scrolls = function (el) {
      return !!el && /(auto|scroll)/.test(getComputedStyle(el).overflowY);
    };
    // Where the page scrolls, in order: the page area itself; else a page
    // container of its own (Defense Scheduling, Financial on a phone); else,
    // on pages that hold still and scroll one list, the end of that list.
    // .content here is the page container, not the few buttons that also
    // wear it for its colours (see expert.css).
    // The page is often still hidden behind its loading skeleton here, so
    // sizes cannot tell the real page from the placeholder or a pop-up;
    // what they are called can.
    var isPage = function (el) {
      return el.tagName === 'DIV'
        && !el.closest('#skeleton-content, .sk-overlay, .modal-overlay, dialog, .notif-dropdown, [class*="modal"], [class*="overlay"]');
    };
    var first = function (selector, test) {
      return Array.prototype.find.call(main.querySelectorAll(selector), function (el) { return isPage(el) && test(el); });
    };
    var content = first(':scope > .content, div.content', function () { return true; });
    if (!scrolls(content)) {
      content = first(':scope > *, :scope > #real-content > *', scrolls)
        || first('.list-card, .dx-table-wrap, .fx-scroll', scrolls);
    }
    if (!content) {
      return;
    }
    var style = getComputedStyle(content);
    // A row of side-by-side columns has no "end" to put it at.
    if (style.display.indexOf('flex') !== -1 && style.flexDirection.indexOf('row') === 0 && style.flexWrap === 'nowrap') {
      return;
    }

    var credits = document.querySelector('.legal-credits');
    if (credits && content.contains(credits)) {
      return;
    }
    if (!credits) {
      credits = document.createElement('div');
      credits.innerHTML = '<div class="credits-title">System Developers</div>'
        + '<div class="credits-names">Jolina · John Kenneth · Mark Anthony · Ian Cyrus · Alexa Nicole</div>';
    }
    credits.className = 'legal-credits sys-credits';
    credits.removeAttribute('style');
    content.appendChild(credits);

    // On a short or empty page the credits would ride up under the last
    // card, halfway up the screen. The gap above them grows by whatever
    // room is left, so they sit at the bottom until the page is long enough
    // to push them there itself. Re-measured whenever the page changes size
    // or content (the skeleton lifting, a list loading, a filter emptying it).
    var BASE = 40;
    var queued = false;
    var px = function (el, prop) {
      return parseFloat(getComputedStyle(el)[prop] || 0) || 0;
    };
    // The box held to the screen's height, which is the one the room has to
    // be measured in. It is usually the page container found above, but on
    // some pages (the expert dashboard, Document Review, Team Grading) that
    // container only wears overflow:auto and grows with what is in it, while
    // the box around it is the one that scrolls. Measured in the first, a
    // short page had "no room left" and the credits stayed halfway up the
    // screen. Found by pushing the credits far down for a moment and taking
    // the first box, from the container outwards, that did not grow.
    var holdingBox = function () {
      var chain = [];
      for (var el = content; el && el !== main.parentElement; el = el.parentElement) {
        chain.push([el, el.clientHeight]);
      }
      credits.style.marginTop = (BASE + 5000) + 'px';
      var found = chain.find(function (pair) { return pair[0].clientHeight === pair[1]; });
      credits.style.marginTop = BASE + 'px';
      return found ? found[0] : content;
    };
    var fit = function () {
      queued = false;
      credits.style.marginTop = BASE + 'px';
      var box = holdingBox();
      // Where the credits end, measured from the top of that box's scrolling
      // area, with the padding of everything between them and it (scrollHeight
      // never reports less than the visible height, so it cannot say how much
      // room is left).
      var below = 0;
      for (var el = credits.parentElement; el && el !== box; el = el.parentElement) {
        below += px(el, 'paddingBottom') + px(el, 'borderBottomWidth') + px(el, 'marginBottom');
      }
      var rect = box.getBoundingClientRect();
      var end = credits.getBoundingClientRect().bottom - rect.top - box.clientTop + box.scrollTop + below;
      var free = Math.floor(box.clientHeight - px(box, 'paddingBottom') - end) - 1;
      if (free > 0) {
        credits.style.marginTop = (BASE + free) + 'px';
        // Anything the sum above missed (a collapsed margin, a stray spacer)
        // would leave the page scrolling a few pixels to reach them.
        var over = box.scrollHeight - box.clientHeight;
        if (over > 0) {
          credits.style.marginTop = Math.max(BASE, BASE + free - over) + 'px';
        }
      }
    };
    // A timer rather than an animation frame: frames never come in a tab
    // opened in the background, and the page would be found as it loaded.
    var refit = function () {
      if (!queued) {
        queued = true;
        setTimeout(fit, 30);
      }
    };
    fit();
    window.addEventListener('resize', refit);
    window.addEventListener('load', refit);
    if (window.ResizeObserver) {
      new ResizeObserver(refit).observe(content);
    }
    if (window.MutationObserver) {
      new MutationObserver(function (records) {
        // Its own margin changing is not news.
        if (records.some(function (r) { return !credits.contains(r.target); })) {
          refit();
        }
        // The whole page, not only the scrolling area: the skeleton lifting
        // is a change to the wrapper around it.
      }).observe(main, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class', 'hidden', 'open'] });
    }
  });
</script>

{{-- Every portal page carries this bar, so the idle sign-out rides with it. --}}
@include('partials.idle-logout')
@include('partials.enter-submits')
@endonce

@if ($floating ?? false)
@once
{{--
  The floating bar, as the Facebook app draws it on an iPhone: a rounded
  pill resting above the bottom of the screen rather than a strip along it,
  out of the way while the page is being read downwards, and back the moment
  it is scrolled up again (or reaches the top). Every portal has it.
--}}
<style>
  @media (max-width:768px){
    /* A small white pill with a soft grey shadow for a lip, centred and no
       wider than it needs to be - on a tablet too, rather than stretched
       from edge to edge. The pill and the lip are the bar's two
       pseudo-elements, lip first, so the pill paints over it, then the
       gliding highlight, then the icons over all of them. */
    .mtab.is-floating{
      left:0;right:0;width:calc(100% - 32px);max-width:340px;margin:0 auto;
      bottom:calc(16px + env(safe-area-inset-bottom, 0px));
      height:52px;padding:0 5px;border:0;border-radius:26px;
      background:none;box-shadow:none;isolation:isolate;
      transition:transform .3s cubic-bezier(.2,.8,.2,1),opacity .2s ease;
      will-change:transform;
    }
    .mtab.is-floating::before,.mtab.is-floating::after{content:"";position:absolute;border-radius:inherit;z-index:-1;pointer-events:none}
    .mtab.is-floating::before{left:6px;right:6px;top:6px;bottom:-4px;background:rgba(24,25,31,.1)}
    .mtab.is-floating::after{
      inset:0;background:#fff;border:1px solid rgba(24,25,31,.08);
      box-shadow:0 8px 20px rgba(24,25,31,.1),0 1px 4px rgba(24,25,31,.05);
    }
    .mtab.is-floating.is-away{
      transform:translateY(calc(100% + 32px + env(safe-area-inset-bottom, 0px)));
      opacity:0;pointer-events:none;
    }

    /* The highlight: one pill that glides from the tab left to the tab
       tapped, a column at a time (--i of --n), overshooting a touch the way
       an app's does. */
    .mtab-glide{
      position:absolute;top:0;bottom:0;left:5px;z-index:0;pointer-events:none;
      width:calc((100% - 10px) / var(--n, 5));
      transform:translateX(calc(var(--i, 0) * 100%));
      transition:transform .5s cubic-bezier(.34,1.36,.5,1),opacity .2s ease;
    }
    .mtab-glide::before{
      content:"";position:absolute;left:50%;top:6px;width:44px;height:28px;margin-left:-22px;border-radius:14px;
      background:rgba(201,168,44,.2);
      transition:transform .5s cubic-bezier(.34,1.36,.5,1);
    }
    .mtab.is-floating:not(:has(.mtab-item.on)) .mtab-glide{opacity:0}

    .mtab.is-floating .mtab-item{z-index:1;padding:0;gap:0;color:#6b6f7b}
    .mtab.is-floating .mtab-ic{
      width:38px;height:28px;border-radius:14px;background:none!important;
      transition:transform .45s cubic-bezier(.34,1.56,.64,1),color .2s ease;
    }
    .mtab.is-floating .mtab-ic svg{width:20px;height:20px;stroke-width:2;transition:stroke-width .2s ease}
    /* The tab you are on lifts its icon and says its name beneath it. */
    .mtab.is-floating .mtab-item .lb{
      position:absolute;left:0;right:0;bottom:4px;text-align:center;
      font-size:9px;font-weight:700;letter-spacing:.01em;
      opacity:0;transform:translateY(6px) scale(.9);
      transition:opacity .2s ease,transform .35s cubic-bezier(.34,1.56,.64,1);
    }
    .mtab.is-floating .mtab-item.on{color:var(--accent3,#8a6e10)}
    .mtab.is-floating .mtab-item.on .mtab-ic{transform:translateY(-6px)}
    .mtab.is-floating .mtab-item.on .mtab-ic svg{stroke-width:2.4}
    .mtab.is-floating .mtab-item.on .lb{opacity:1;transform:none}

    /* A finger on it presses it in; a pointer over it lifts it. */
    .mtab.is-floating .mtab-item:active .mtab-ic{transform:scale(.86);transition-duration:.12s}
    .mtab.is-floating .mtab-item.on:active .mtab-ic{transform:translateY(-6px) scale(.86)}
    @media (hover:hover){
      .mtab.is-floating .mtab-item:not(.on):not(.primary):hover{color:#18191f}
      .mtab.is-floating .mtab-item:not(.on):not(.primary):hover .mtab-ic{transform:translateY(-3px)}
    }

    /* The one action: a solid gold disc in the middle, its + turning as it
       is pressed. It and Menu keep their names for screen readers only. */
    .mtab.is-floating .mtab-item.primary .lb,.mtab.is-floating .mtab-item:last-child .lb{width:1px;height:1px;left:auto;right:auto;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
    .mtab.is-floating .mtab-item.primary .mtab-ic{
      width:38px;height:38px;margin:0;border-radius:50%;
      background:var(--accent,#c9a82c)!important;color:#18191f;box-shadow:0 4px 10px rgba(201,168,44,.35);
      transition:transform .35s cubic-bezier(.34,1.56,.64,1),box-shadow .2s ease;
    }
    .mtab.is-floating .mtab-item.primary .mtab-ic svg{width:19px;height:19px;stroke-width:2.6;transition:transform .35s cubic-bezier(.34,1.56,.64,1)}
    .mtab.is-floating .mtab-item.primary:active .mtab-ic{transform:scale(.9);box-shadow:0 3px 8px rgba(201,168,44,.4)}
    .mtab.is-floating .mtab-item.primary:active .mtab-ic svg{transform:rotate(90deg)}
    @media (hover:hover){
      .mtab.is-floating .mtab-item.primary:hover .mtab-ic{transform:translateY(-3px);box-shadow:0 7px 14px rgba(201,168,44,.45)}
      .mtab.is-floating .mtab-item.primary:hover .mtab-ic svg{transform:rotate(90deg)}
    }

    /* Menu wears the student's own picture, as the app's last tab does. */
    .mtab.is-floating .mtab-avatar{width:28px;height:28px;border-radius:50%;padding:0;overflow:hidden;
      background:var(--accent-light,#faf7ed)!important;box-shadow:none;color:#18191f;font-size:11px;font-weight:700}
    .mtab.is-floating .mtab-avatar img{width:100%;height:100%;object-fit:cover;display:block}

    .mtab.is-floating .mtab-badge{top:-1px;right:2px;min-width:14px;height:14px;padding:0 3px;font-size:8.5px;line-height:14px;box-shadow:0 0 0 2px #fff}

    /* The page scrolls clear of the pill; toasts sit above it. Where the
       page's scrolling area can be found (the script below), the room is at
       the end of it instead, so the page runs on underneath the pill like
       the app's feed does, rather than stopping at a line above it. */
    .main{padding-bottom:calc(76px + env(safe-area-inset-bottom, 0px))}
    .mtab-spacer{flex:none;height:calc(86px + env(safe-area-inset-bottom, 0px));pointer-events:none}
    .toast,.toast-wrap,#submission-toast,#archive-toast{bottom:calc(90px + env(safe-area-inset-bottom, 0px))!important}
  }
  @media (max-width:768px) and (prefers-reduced-motion:reduce){
    .mtab.is-floating{transition:opacity .15s linear}
    .mtab.is-floating.is-away{transform:none}
    .mtab-glide,.mtab-glide::before,.mtab.is-floating .mtab-ic,.mtab.is-floating .mtab-ic svg,.mtab.is-floating .lb{transition:none!important;animation:none!important}
    .mtab.is-floating .mtab-item:active .mtab-ic,.mtab.is-floating .mtab-item.primary:active .mtab-ic svg{transform:none}
  }
</style>
<script>
  // The bar is drawn just after this script, so it is looked for once the
  // page has been read.
  document.addEventListener('DOMContentLoaded', function () {
    var bar = document.querySelector('.mtab.is-floating');
    if (!bar) return;

    // The highlight glides to a tab as soon as it is tapped - once. The page
    // it opens starts with the highlight already there, so the glide is not
    // played a second time while that page loads.
    var items = Array.prototype.slice.call(bar.querySelectorAll(':scope > .mtab-item'));
    var current = items.findIndex(function (el) { return el.classList.contains('on'); });
    function select(i) {
      items.forEach(function (el, n) { el.classList.toggle('on', n === i); });
      bar.style.setProperty('--i', i);
    }
    items.forEach(function (el, i) {
      if (el.tagName !== 'A') return;
      el.addEventListener('click', function (e) {
        if (i === current || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button) return;
        select(i);
      });
    });
    // Back to this page from the browser's memory: the tab it is really on.
    window.addEventListener('pageshow', function (e) { if (e.persisted && current >= 0) select(current); });

    var last = new WeakMap();
    function show() { bar.classList.remove('is-away'); }
    function hide() { bar.classList.add('is-away'); }

    // Pages scroll inside their own boxes here, not the window, so every
    // scroll is heard on the way down and the ones that are the page itself
    // are followed: tall enough to be the page, and not a pop-up, the menu or
    // a list inside a card.
    document.addEventListener('scroll', function (e) {
      var box = e.target === document ? (document.scrollingElement || document.documentElement) : e.target;
      if (!box || !box.getBoundingClientRect) return;
      if (box.closest && box.closest('.sidebar, .notif-dropdown, dialog, .modal-overlay, [role="dialog"], [aria-modal="true"], .mtab')) return;
      if (box.clientHeight < window.innerHeight * 0.5 || box.scrollHeight - box.clientHeight < 80) return;

      var top = box.scrollTop;
      var before = last.get(box);
      last.set(box, top);
      if (before === undefined) return;

      if (top < 40) { show(); return; }
      var moved = top - before;
      if (moved > 6) hide();
      else if (moved < -6) show();
    }, { capture: true, passive: true });

    // Tabbing onto it brings it back.
    bar.addEventListener('focusin', show);

    // The room at the end of the page's own scrolling area rather than under
    // the whole page area.
    var main = document.querySelector('.main');
    if (!main || window.innerWidth > 768) return;
    var scrolls = function (el) { return /(auto|scroll)/.test(getComputedStyle(el).overflowY); };
    var scroller = [main.querySelector(':scope > .content')]
      .concat(Array.prototype.slice.call(main.querySelectorAll('div')))
      .find(function (el) {
        return el && scrolls(el) && el.clientHeight >= window.innerHeight * 0.5
          && !el.closest('.modal-overlay, dialog, [role="dialog"], .notif-dropdown, #skeleton-content, .sk-overlay');
      });
    if (!scroller) return;
    main.style.paddingBottom = '0px';
    var spacer = document.createElement('div');
    spacer.className = 'mtab-spacer';
    spacer.setAttribute('aria-hidden', 'true');
    // Less whatever room the page already keeps at its end, so the gap above
    // the pill is the same on every page.
    var own = parseFloat(getComputedStyle(scroller).paddingBottom) || 0;
    spacer.style.height = 'calc(' + Math.max(0, 88 - own) + 'px + env(safe-area-inset-bottom, 0px))';
    scroller.appendChild(spacer);
  });
</script>
@endonce
@endif

@php
  $activeTab = collect($tabs)->search(fn (array $tab): bool => ! empty($tab['active']));
@endphp
@if ($floating ?? false)
<nav class="mtab is-floating" aria-label="Main" style="--n:{{ count($tabs) + 1 }};--i:{{ $activeTab === false ? 0 : $activeTab }}">
  <span class="mtab-glide" aria-hidden="true"></span>
@else
<nav class="mtab" aria-label="Main">
@endif
  @foreach ($tabs as $tab)
    @php
      $classes = 'mtab-item'.(! empty($tab['active']) ? ' on' : '').(! empty($tab['primary']) ? ' primary' : '');
      $badge = (int) ($tab['badge'] ?? 0);
      $inner = '<span class="mtab-ic"><svg viewBox="0 0 24 24" aria-hidden="true">'.$tab['icon'].'</svg>'
          .($badge > 0 ? '<span class="mtab-badge">'.($badge > 9 ? '9+' : $badge).'</span>' : '')
          .'</span><span class="lb">'.e($tab['label']).'</span>';
    @endphp
    @if (! empty($tab['href']))
      <a href="{{ $tab['href'] }}" class="{{ $classes }}" @if (! empty($tab['active'])) aria-current="page" @endif>{!! $inner !!}</a>
    @else
      <button type="button" class="{{ $classes }}" onclick="{{ $tab['onclick'] }}">{!! $inner !!}</button>
    @endif
  @endforeach
  <button type="button" class="mtab-item" onclick="var h = document.querySelector('.hamburger-btn'); if (h) { h.click(); }" aria-label="Open the full menu">
    @if (($floating ?? false) && ! empty($menuAvatar))
      <span class="mtab-ic mtab-avatar" aria-hidden="true">
        @if (! empty($menuAvatar['url']))
          <img src="{{ $menuAvatar['url'] }}" alt="" onerror="this.replaceWith(document.createTextNode(@js($menuAvatar['initials'] ?? '')))">
        @else
          {{ $menuAvatar['initials'] ?? '' }}
        @endif
      </span>
    @else
      <span class="mtab-ic"><svg viewBox="0 0 24 24" aria-hidden="true"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg></span>
    @endif
    <span class="lb">Menu</span>
  </button>
</nav>
