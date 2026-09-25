{{-- The guided tour, for every portal. The screen dims, one part of the
     portal is outlined, and a short note with an arrow says what it is -
     pointing at the real thing, so a newcomer learns where it is and not just
     that it exists. Opens by itself once for a new account; after that only
     from Portal Tour in the sidebar.

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

@if ($tourSteps->isNotEmpty())
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
  <svg class="gt-arrow" id="gt-arrow" aria-hidden="true">
    <path id="gt-arrow-line" />
    <path id="gt-arrow-head" />
  </svg>

  <div class="gt-note" id="gt-note" aria-live="polite">
    <h2 class="gt-label" id="gt-label"></h2>
    <p class="gt-body" id="gt-body"></p>
    {{-- Always in the page (the script reads it); only shown on the send-off,
         and only for a portal that names a first step. --}}
    <a href="{{ $tourNextUrl ?? '#' }}" class="gt-next-up" data-tour="finish-link" data-has-next="{{ $tourNextUrl ? '1' : '0' }}" hidden>
      <span class="gt-next-up-label">Your first step</span>
      <span class="gt-next-up-title">{{ $tourNextLabel ?? '' }}</span>
      <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </a>
  </div>

  <div class="gt-bar">
    <span class="gt-count" id="gt-count"></span>
    <button type="button" class="gt-pill gt-pill-ghost" data-tour="back">Back</button>
    <button type="button" class="gt-pill gt-pill-ghost" data-tour="skip">Skip</button>
    <button type="button" class="gt-pill gt-pill-solid" data-tour="next">Next</button>
  </div>

  <script type="application/json" id="gt-steps">@json($tourSteps)</script>
</div>

<script>
(function () {
  var tour = document.getElementById('portal-tour');
  if (!tour) return;

  var steps = JSON.parse(document.getElementById('gt-steps').textContent);
  var tourKey = tour.getAttribute('data-tour-key') || 'student';
  var last = steps.length - 1;
  var spot = document.getElementById('gt-spot');
  var note = document.getElementById('gt-note');
  var labelEl = document.getElementById('gt-label');
  var bodyEl = document.getElementById('gt-body');
  var countEl = document.getElementById('gt-count');
  var arrow = document.getElementById('gt-arrow');
  var arrowLine = document.getElementById('gt-arrow-line');
  var arrowHead = document.getElementById('gt-arrow-head');
  var finishLink = tour.querySelector('[data-tour="finish-link"]');
  var backBtn = tour.querySelector('[data-tour="back"]');
  var skipBtn = tour.querySelector('[data-tour="skip"]');
  var nextBtn = tour.querySelector('[data-tour="next"]');
  var bar = tour.querySelector('.gt-bar');

  // The welcome and the send-off are not counted: step 1 is the first
  // thing actually pointed at.
  var pointed = steps.filter(function (s) { return s.target.length; }).length;

  var current = 0;
  var direction = 1;
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
    return found;
  }

  function inSidebar(els) {
    return els.some(function (el) { return !!el.closest('.sidebar'); });
  }

  // On a phone the sidebar is off screen; a step about a sidebar link opens
  // it, and a step about anything else puts it back.
  // The student portal slides its sidebar in with a class on <body>; the
  // CaPSA, Admin and Expert portals show it with .mobile-open, through their
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
   * Put the note where there is room for it, far enough from the outline
   * that the arrow has space to curve, and draw the arrow from the note to
   * the outline. Right of the target first - the sidebar is on the left, so
   * that is nearly always free on a computer - then below, left, above.
   */
  function layoutPointed(els) {
    var r = unionRect(els);
    var vw = window.innerWidth;
    var vh = window.innerHeight;
    var edge = 16;
    var gap = isNarrow() ? 56 : 84;
    var reserve = bar.offsetHeight + 28;

    spot.style.left = r.left + 'px';
    spot.style.top = r.top + 'px';
    spot.style.width = r.width + 'px';
    spot.style.height = r.height + 'px';

    var w = Math.min(340, vw - edge * 2);
    note.style.width = w + 'px';
    var h = note.offsetHeight;

    var options = {
      right: { x: r.right + gap, y: r.cy - h / 2 - 34, fits: r.right + gap + w <= vw - edge },
      below: { x: r.cx + 24, y: r.bottom + gap, fits: r.bottom + gap + h <= vh - reserve },
      left: { x: r.left - gap - w, y: r.cy - h / 2 - 34, fits: r.left - gap - w >= edge },
      above: { x: r.cx + 24, y: r.top - gap - h, fits: r.top - gap - h >= edge }
    };
    var side = ['right', 'below', 'left', 'above'].filter(function (s) { return options[s].fits; })[0] || 'below';
    var x = clamp(options[side].x, edge, vw - edge - w);
    var y = clamp(options[side].y, edge, Math.max(edge, vh - reserve - h));

    note.style.left = x + 'px';
    note.style.top = y + 'px';
    note.setAttribute('data-side', side);

    // From beside the heading to just short of the outline. It
    // leaves the words and meets the target square-on, bending in between
    // - the loose S of a note scribbled on top of a screenshot.
    var labelMid = Math.min(14, h / 2); // the middle of the heading's first line
    var bend = 34;
    var start, end, c1, c2;
    if (side === 'right') {
      start = { x: x - 10, y: y + labelMid };
      end = { x: r.right + 10, y: r.cy };
      c1 = { x: start.x - bend, y: start.y };
      c2 = { x: end.x + bend, y: end.y };
    } else if (side === 'left') {
      start = { x: x + w + 10, y: y + labelMid };
      end = { x: r.left - 10, y: r.cy };
      c1 = { x: start.x + bend, y: start.y };
      c2 = { x: end.x - bend, y: end.y };
    } else if (side === 'below') {
      start = { x: x + 28, y: y - 10 };
      end = { x: clamp(r.cx, r.left + 14, r.right - 14), y: r.bottom + 10 };
      c1 = { x: start.x, y: start.y - bend };
      c2 = { x: end.x, y: end.y + bend };
    } else {
      start = { x: x + 28, y: y + h + 10 };
      end = { x: clamp(r.cx, r.left + 14, r.right - 14), y: r.top - 10 };
      c1 = { x: start.x, y: start.y + bend };
      c2 = { x: end.x, y: end.y - bend };
    }

    drawArrow(start, c1, c2, end);
  }

  function drawArrow(start, c1, c2, end) {
    arrowLine.setAttribute('d', 'M' + start.x + ' ' + start.y + ' C' + c1.x + ' ' + c1.y + ' ' + c2.x + ' ' + c2.y + ' ' + end.x + ' ' + end.y);

    // The head follows the curve's last direction, not the straight line.
    var dx = end.x - c2.x;
    var dy = end.y - c2.y;
    var len = Math.sqrt(dx * dx + dy * dy) || 1;
    dx /= len; dy /= len;
    var size = 12;
    var spread = 0.5;
    var a = { x: end.x - size * (dx * Math.cos(spread) - dy * Math.sin(spread)), y: end.y - size * (dy * Math.cos(spread) + dx * Math.sin(spread)) };
    var b = { x: end.x - size * (dx * Math.cos(spread) + dy * Math.sin(spread)), y: end.y - size * (dy * Math.cos(spread) - dx * Math.sin(spread)) };
    arrowHead.setAttribute('d', 'M' + a.x + ' ' + a.y + ' L' + end.x + ' ' + end.y + ' L' + b.x + ' ' + b.y);
    arrow.style.display = '';
  }

  function layoutCentered() {
    var w = Math.min(560, window.innerWidth - 32);
    note.style.width = w + 'px';
    note.style.left = (window.innerWidth - w) / 2 + 'px';
    var reserve = bar.offsetHeight + 28;
    note.style.top = Math.max(16, (window.innerHeight - reserve - note.offsetHeight) / 2) + 'px';
    note.setAttribute('data-side', 'center');
    arrow.style.display = 'none';
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
    bodyEl.textContent = step.body;
    // Only a portal with a first step to send people to shows the link.
    finishLink.hidden = current !== last || finishLink.getAttribute('data-has-next') !== '1';

    var number = steps.slice(0, current + 1).filter(function (s) { return s.target.length; }).length;
    countEl.textContent = step.target.length ? number + ' / ' + pointed : '';

    backBtn.hidden = current === 0;
    skipBtn.hidden = current === last;
    nextBtn.textContent = current === 0 ? 'Start the tour' : current === last ? 'Done' : 'Next';

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
    if (saved || tour.getAttribute('data-auto-open') !== '1') return;
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
    // measure again once it has, so the arrow still starts beside the words.
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
  }

  function next() { direction = 1; current === last ? close() : show(current + 1); }
  function back() { direction = -1; show(current - 1); }

  nextBtn.addEventListener('click', next);
  backBtn.addEventListener('click', back);
  skipBtn.addEventListener('click', close);
  finishLink.addEventListener('click', save);
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

  window.openPortalTour = function () {
    if (typeof closeSidebar === 'function') closeSidebar();
    openedSidebar = false;
    open();
  };

  if (tour.getAttribute('data-auto-open') === '1' && !markedDone()) open();
})();
</script>
@endif
