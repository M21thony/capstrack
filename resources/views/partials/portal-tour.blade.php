{{-- The guided tour, for every portal. The screen dims, one part of the
     portal is outlined in gold, and a card beside it says what it is -
     pointing at the real thing, so a newcomer learns where it is and not just
     that it exists. Opens by itself once for a new account and never again;
     there is no sidebar button to replay it. The Page Guide
     (partials/page-guide) runs it with a page's own steps instead.

     Each portal's sidebar includes a small wrapper that works out its steps
     and passes:
       $tourKey        'student', 'capsa', 'admin' or 'expert'
       $tourSteps      [['key','label','body','target' => [CSS selectors]], ...]
       $tourAutoOpen   whether this account still has to see it
       $tourSaveUrl    where "done with it" is recorded
       $tourNextUrl    optional: the send-off's "your first step" link
       $tourNextLabel  and its title --}}
@php
    $tourSteps = collect($tourSteps ?? [])->values();
    $tourNextUrl = $tourNextUrl ?? null;
@endphp

{{-- $tourPageOnly: a page without a sidebar gets only the engine, for its page tour. --}}
@if ($tourSteps->isNotEmpty() || ($tourPageOnly ?? false))
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/portal-tour.css') }}">
@if ($tourAutoOpen)
  {{-- The site's own fonts (Poppins for the headings among them); the page
       usually has them already, and a replay from the sidebar adds them
       then. The font file itself is fetched only once a heading is drawn. --}}
  <link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" id="gt-font">
@endif

<div class="gt" id="portal-tour" role="dialog" aria-modal="true" aria-labelledby="gt-label" aria-describedby="gt-body" hidden
     data-auto-open="{{ $tourAutoOpen ? '1' : '0' }}"
     data-tour-key="{{ $tourKey }}"
     data-save-url="{{ $tourSaveUrl }}">
  <div class="gt-shield" data-tour="shield"></div>
  <div class="gt-spot" id="gt-spot" aria-hidden="true"></div>

  <div class="gt-note" id="gt-note" aria-live="polite">
    <h2 class="gt-label" id="gt-label"><span class="gt-bulb" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 18h6M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg></span><span id="gt-label-text"></span></h2>
    <p class="gt-body" id="gt-body"></p>
    {{-- Always in the page (the script reads it); only shown on the send-off,
         and only for a portal that names a first step. --}}
    <a href="{{ $tourNextUrl ?? '#' }}" class="gt-next-up" data-tour="finish-link" data-has-next="{{ $tourNextUrl ? '1' : '0' }}" hidden>
      <span class="gt-next-up-label">Your first step</span>
      <span class="gt-next-up-title">{{ $tourNextLabel ?? '' }}</span>
      <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </a>
    <div class="gt-foot" id="gt-foot">
      <span id="gt-foot-count"></span>
      <button type="button" data-tour="note-next">Next</button>
    </div>
  </div>

  <div class="gt-bar">
    <span class="gt-count" id="gt-count"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18h6M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg><span id="gt-count-n"></span><span class="t" id="gt-count-t"></span></span>
    <button type="button" class="gt-pill gt-pill-ghost" data-tour="back">Back</button>
    <button type="button" class="gt-pill gt-pill-ghost" data-tour="skip">Skip tour</button>
    <button type="button" class="gt-pill gt-pill-solid" data-tour="next">Next</button>
  </div>

  <script type="application/json" id="gt-steps">@json($tourSteps)</script>
</div>

<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var tour = document.getElementById('portal-tour');
  if (!tour) return;

  var steps = JSON.parse(document.getElementById('gt-steps').textContent);
  var portalSteps = steps;
  var pageTour = false;
  var tourKey = tour.getAttribute('data-tour-key') || 'student';
  var last = steps.length - 1;
  var spot = document.getElementById('gt-spot');
  var note = document.getElementById('gt-note');
  var labelEl = document.getElementById('gt-label-text');
  var bodyEl = document.getElementById('gt-body');
  var countEl = document.getElementById('gt-count');
  var countNum = document.getElementById('gt-count-n');
  var countLabel = document.getElementById('gt-count-t');
  var foot = document.getElementById('gt-foot');
  var footCount = document.getElementById('gt-foot-count');
  var noteNext = tour.querySelector('[data-tour="note-next"]');
  var finishLink = tour.querySelector('[data-tour="finish-link"]');
  var backBtn = tour.querySelector('[data-tour="back"]');
  var skipBtn = tour.querySelector('[data-tour="skip"]');
  var nextBtn = tour.querySelector('[data-tour="next"]');
  var bar = tour.querySelector('.gt-bar');

  // The welcome and the send-off are not counted: step 1 is the first
  // thing actually pointed at.
  var pointed = steps.filter(function (s) { return s.target.length; }).length;

  function useSteps(list, isPage) {
    steps = list;
    last = steps.length - 1;
    pointed = steps.filter(function (s) { return s.target.length; }).length;
    pageTour = isPage;
  }

  var current = 0;
  var direction = 1;
  var onPageDone = null;
  var saved = false;
  var openedSidebar = false;
  var returnFocus = null;
  var layoutTimer = null;

  function isNarrow() { return window.matchMedia('(max-width: 768px)').matches; }

  function targetsOf(step) {
    var found = [];
    step.target.forEach(function (selector) {
      document.querySelectorAll(selector).forEach(function (el) {
        // Only the copy that is on screen: a page can carry a hidden
        // duplicate of a control for another layout.
        if (el.getClientRects().length) found.push(el);
      });
    });
    // One button of a list (each row's Remove): only the first is outlined.
    return step.one ? found.slice(0, 1) : found;
  }

  function inSidebar(els) {
    return els.some(function (el) { return !!el.closest('.sidebar'); });
  }

  // On a phone the sidebar is off screen; a step about a sidebar link opens
  // it, and a step about anything else puts it back.
  // The student portal slides its sidebar in with a class on <body>; the
  // CaPSA/TSA, Admin and Expert portals show it with .mobile-open, through their
  // own openSidebar()/closeSidebar().
  function sidebarIsOpen() {
    return document.body.classList.contains('sidebar-open') || !!document.querySelector('.sidebar.mobile-open');
  }
  function openSide() {
    if (typeof window.openSidebar === 'function') window.openSidebar();
    else document.body.classList.add('sidebar-open');
  }
  function closeSide() {
    if (document.querySelector('.sidebar.mobile-open') && typeof window.closeSidebar === 'function') window.closeSidebar();
    else document.body.classList.remove('sidebar-open');
  }
  function setSidebar(open) {
    if (open && !sidebarIsOpen()) {
      openSide();
      openedSidebar = true;
      return true;
    }
    if (!open && openedSidebar) {
      closeSide();
      openedSidebar = false;
      return true;
    }
    return false;
  }

  function unionRect(els) {
    var pad = 6;
    var box = null;
    els.forEach(function (el) {
      var r = el.getBoundingClientRect();
      box = box
        ? { left: Math.min(box.left, r.left), top: Math.min(box.top, r.top), right: Math.max(box.right, r.right), bottom: Math.max(box.bottom, r.bottom) }
        : { left: r.left, top: r.top, right: r.right, bottom: r.bottom };
    });
    box.left = Math.max(4, box.left - pad);
    box.top = Math.max(4, box.top - pad);
    box.right = Math.min(window.innerWidth - 4, box.right + pad);
    box.bottom = box.bottom + pad;
    box.width = box.right - box.left;
    box.height = box.bottom - box.top;
    box.cx = box.left + box.width / 2;
    box.cy = box.top + box.height / 2;
    return box;
  }

  function clamp(value, min, max) { return Math.max(min, Math.min(max, value)); }

  /**
   * Put the card just beside the outline, where there is room for it.
   * Right of the target first - the sidebar is on the left, so that is
   * nearly always free on a computer - then below, left, above.
   */
  function layoutPointed(els) {
    var r = unionRect(els);
    var vw = window.innerWidth;
    var vh = window.innerHeight;
    var edge = 16;
    var gap = isNarrow() ? 12 : 18;
    var reserve = bar.offsetHeight + 28;

    spot.style.left = r.left + 'px';
    spot.style.top = r.top + 'px';
    spot.style.width = r.width + 'px';
    spot.style.height = r.height + 'px';

    var w = Math.min(340, vw - edge * 2);
    note.style.width = w + 'px';
    var h = note.offsetHeight;

    var options = {
      right: { x: r.right + gap, y: r.cy - h / 2, fits: r.right + gap + w <= vw - edge },
      below: { x: r.left, y: r.bottom + gap, fits: r.bottom + gap + h <= vh - reserve },
      left: { x: r.left - gap - w, y: r.cy - h / 2, fits: r.left - gap - w >= edge },
      above: { x: r.left, y: r.top - gap - h, fits: r.top - gap - h >= edge }
    };
    var side = ['right', 'below', 'left', 'above'].filter(function (s) { return options[s].fits; })[0] || 'below';
    var x = clamp(options[side].x, edge, vw - edge - w);
    var y = clamp(options[side].y, edge, Math.max(edge, vh - reserve - h));

    note.style.left = x + 'px';
    note.style.top = y + 'px';
    note.setAttribute('data-side', side);
  }

  function layoutCentered() {
    var w = Math.min(560, window.innerWidth - 32);
    note.style.width = w + 'px';
    note.style.left = (window.innerWidth - w) / 2 + 'px';
    var reserve = bar.offsetHeight + 28;
    note.style.top = Math.max(16, (window.innerHeight - reserve - note.offsetHeight) / 2) + 'px';
    note.setAttribute('data-side', 'center');
  }

  function layout() {
    var step = steps[current];
    var els = step.target.length ? targetsOf(step) : [];
    tour.classList.toggle('is-centered', !els.length);

    if (!els.length) {
      layoutCentered();
      return;
    }

    layoutPointed(els);
  }

  function show(index) {
    current = clamp(index, 0, last);
    var step = steps[current];
    var els = step.target.length ? targetsOf(step) : [];

    // A target this page does not have (no bell on some layout, say) is
    // skipped in the direction of travel rather than pointed at nothing.
    // Opening the phone sidebar here counts as moving it: the note below
    // must wait for the slide, or it outlines a sliver at the screen's edge.
    var openedForTarget = false;
    if (step.target.length && !els.length && isNarrow() && inSidebarSelector(step)) {
      openedForTarget = setSidebar(true);
      els = targetsOf(step);
    }
    if (step.target.length && !els.length) {
      if (current > 0 && current < last) {
        show(current + direction);
      }
      return;
    }

    labelEl.textContent = step.label;
    // **words** in the copy show in bold; built as nodes, never as HTML.
    bodyEl.textContent = '';
    step.body.split(/\*\*(.+?)\*\*/).forEach(function (part, i) {
      if (!part) return;
      var node = i % 2 ? document.createElement('b') : document.createTextNode(part);
      if (i % 2) node.textContent = part;
      bodyEl.appendChild(node);
    });
    // Only a portal with a first step to send people to shows the link.
    finishLink.hidden = pageTour || current !== last || finishLink.getAttribute('data-has-next') !== '1';

    var number = steps.slice(0, current + 1).filter(function (s) { return s.target.length; }).length;
    countEl.hidden = foot.hidden = !step.target.length;
    countNum.textContent = 'Step ' + number + ' of ' + pointed;
    countLabel.textContent = '• ' + step.label;
    footCount.textContent = number + ' / ' + pointed;

    // A tab in the tour can be clicked through its outline: the tab opens
    // and the tour goes on with that tab's own steps.
    tour.classList.toggle('gt-can-click', !!step.click && els.length > 0);

    backBtn.hidden = current === 0;
    skipBtn.hidden = current === last;
    nextBtn.textContent = current === last ? 'Finish' : current === 0 && !step.target.length ? 'Start the tour' : 'Next';
    noteNext.textContent = current === last ? 'Finish' : 'Next';

    var moved = openedForTarget;
    if (isNarrow()) {
      moved = setSidebar(els.length > 0 && inSidebar(els)) || moved;
    }
    if (els.length) {
      els[0].scrollIntoView({ block: 'center', inline: 'nearest' });
    }

    note.classList.remove('is-in');
    clearTimeout(layoutTimer);
    // The phone sidebar slides for 250ms; measure where it ends up.
    layoutTimer = setTimeout(function () {
      layout();
      note.classList.add('is-in');
    }, moved ? 280 : 30);
  }

  function inSidebarSelector(step) {
    return step.target.some(function (selector) {
      return selector.indexOf('data-tour-target') !== -1 || selector.indexOf('.sidebar') === 0
        || selector === '#profile-trigger' || selector === '#ep-trigger';
    });
  }

  // The browser remembers it too, so that if the server refuses to record
  // the tour the student still stops seeing it for this visit, instead of
  // being shown it again on every page with nothing to say why.
  function markedDone() {
    try { return sessionStorage.getItem('gt-done:' + tourKey) === '1'; } catch (e) { return false; }
  }

  function save() {
    // A page's tour from the Page Guide is not the first-login one.
    if (pageTour || saved || tour.getAttribute('data-auto-open') !== '1') return;
    saved = true;
    try { sessionStorage.setItem('gt-done:' + tourKey, '1'); } catch (e) {}
    // keepalive: "Your first step" navigates away straight after this.
    fetch(tour.getAttribute('data-save-url'), {
      method: 'PUT',
      keepalive: true,
      headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).then(function (r) {
      if (!r.ok) console.warn('The tour could not be recorded as finished:', r.status);
    }).catch(function () {});
  }

  function loadFont() {
    if (document.getElementById('gt-font')) return;
    var link = document.createElement('link');
    link.id = 'gt-font';
    link.rel = 'stylesheet';
    link.href = @js(\App\Support\PublicAsset::url('css/fonts.css'));
    document.head.appendChild(link);
  }

  function open() {
    loadFont();
    // Poppins arriving after the first layout changes how tall the note is;
    // measure again once it has, so the card still sits beside the outline.
    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(function () { if (!tour.hidden) layout(); });
    }
    returnFocus = document.activeElement;
    tour.hidden = false;
    document.body.classList.add('tour-open');
    direction = 1;
    show(0);
    nextBtn.focus({ preventScroll: true });
  }

  function close() {
    save();
    clearTimeout(layoutTimer);
    tour.hidden = true;
    document.body.classList.remove('tour-open');
    setSidebar(false);
    if (returnFocus && returnFocus.focus) returnFocus.focus({ preventScroll: true });
    if (pageTour) useSteps(portalSteps, false);
    pageDone();
    release();
  }

  // The tour goes first. A new account can have an invitation, unread
  // notifications or an evaluation waiting on its very first page, and each
  // of those opens a pop-up of its own; on top of the tour they covered its
  // outline and note. They wait here instead, and come once it is closed.
  var pending = tour.getAttribute('data-auto-open') === '1' && !markedDone();
  var waiting = [];
  function run(fn) { try { fn(); } catch (e) { console.error(e); } }
  // A moment after the tour fades: the corner pop-ups at once, and the
  // pop-ups that cover the page one at a time - the next only when the one
  // before it has been closed.
  function release() {
    pending = false;
    var queued = waiting;
    waiting = [];
    var covering = queued.filter(function (q) { return q.isOpen; });
    queued.filter(function (q) { return !q.isOpen; }).forEach(function (q, i) {
      setTimeout(function () { run(q.fn); }, 250 + i * 150);
    });
    (function nextCovering() {
      var q = covering.shift();
      if (!q) return;
      setTimeout(function () {
        run(q.fn);
        var watch = setInterval(function () {
          if (!q.isOpen()) { clearInterval(watch); nextCovering(); }
        }, 300);
      }, 250);
    })();
  }
  // fn opens the pop-up; isOpen, for one that covers the page, says whether
  // it is still open, so the next waits for it.
  window.afterPortalTour = function (fn, isOpen) {
    if (pending || !tour.hidden) waiting.push({ fn: fn, isOpen: isOpen || null });
    else fn();
  };

  // A page tour's "seen": called once, when it closes or gives way to a tab's.
  function pageDone() {
    var fn = onPageDone;
    onPageDone = null;
    if (fn) run(fn);
  }

  function next() { direction = 1; current === last ? close() : show(current + 1); }
  function back() { direction = -1; show(current - 1); }

  nextBtn.addEventListener('click', next);
  noteNext.addEventListener('click', next);
  backBtn.addEventListener('click', back);
  skipBtn.addEventListener('click', close);
  finishLink.addEventListener('click', save);
  spot.addEventListener('click', function () {
    var els = targetsOf(steps[current]);
    if (steps[current].click && els.length) els[0].click();
  });
  // Tapping the dimmed screen moves on, as a walkthrough usually does. Not
  // on the welcome or the send-off, where a stray tap would skip the one
  // thing on screen.
  tour.querySelector('[data-tour="shield"]').addEventListener('click', function () {
    if (steps[current].target.length) next();
  });

  window.addEventListener('resize', function () {
    if (!tour.hidden) layout();
  });

  // Measure again once the phone sidebar has actually finished sliding: a
  // slow phone can take longer than the timer above allows for.
  document.querySelectorAll('.sidebar').forEach(function (side) {
    side.addEventListener('transitionend', function (e) {
      if (e.target === side && e.propertyName === 'transform' && !tour.hidden) layout();
    });
  });

  document.addEventListener('keydown', function (e) {
    if (tour.hidden) return;
    if (e.key === 'Escape') { e.preventDefault(); close(); }
    else if (e.key === 'ArrowRight') { e.preventDefault(); next(); }
    else if (e.key === 'ArrowLeft' && current > 0) { e.preventDefault(); back(); }
    else if (e.key === 'Tab') {
      // Keep focus on the tour's own controls while it is open.
      var focusable = Array.prototype.filter.call(
        tour.querySelectorAll('button, a[href]'),
        function (el) { return !el.hidden && el.offsetParent !== null; }
      );
      if (!focusable.length) return;
      var first = focusable[0], end = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); end.focus(); }
      else if (!e.shiftKey && document.activeElement === end) { e.preventDefault(); first.focus(); }
      else if (!tour.contains(document.activeElement)) { e.preventDefault(); first.focus(); }
    }
  });

  // With no steps, the portal's own tour; with a page's steps (the Page
  // Guide), those, leaving out any part this page is not showing right now.
  // onDone runs when that page tour closes. A page tour already running
  // gives way to the new one (a tab clicked through its outline).
  // Says whether it opened.
  window.openPortalTour = function (pageSteps, onDone) {
    var swap = !tour.hidden && pageTour && Array.isArray(pageSteps);
    if (!tour.hidden && !swap) return false;
    var here = null;
    if (Array.isArray(pageSteps)) {
      here = pageSteps.filter(function (s) { return targetsOf(s).length || (isNarrow() && inSidebarSelector(s)); });
      if (!here.length) return false;
    }
    if (swap) {
      pageDone();
      useSteps(here, true);
      onPageDone = onDone || null;
      direction = 1;
      show(0);
      return true;
    }
    if (typeof closeSidebar === 'function') closeSidebar();
    openedSidebar = false;
    if (here) useSteps(here, true);
    onPageDone = here ? onDone || null : null;
    open();
    return true;
  };

  // The staff welcome ("Welcome to CapsTRACK!") is said first, and the tour
  // follows once it is dismissed. Looked for once the page is read, since the
  // welcome comes after the sidebar in the page.
  function autoOpen() {
    var welcome = document.getElementById('welcome-modal');
    var dismiss = document.getElementById('welcome-dismiss');
    if (welcome && dismiss && welcome.classList.contains('show')) {
      dismiss.addEventListener('click', function () { setTimeout(open, 250); }, { once: true });
      return;
    }
    open();
  }

  if (pending) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', autoOpen, { once: true });
    else autoOpen();
  }
})();
</script>
@endif
