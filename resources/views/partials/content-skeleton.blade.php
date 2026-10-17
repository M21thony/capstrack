{{--
  A student page's first opening since signing in, shown as its skeleton.

  The skeleton is drawn from the page itself, at the moment it first comes on
  screen: every card and container becomes a plain white card in its exact
  place and size, and every line of words, picture, icon, button, badge and
  field inside becomes a solid grey block where it sits. Nothing of the real
  page shows through - no faded buttons, no ghost text. Then the skeleton
  fades away over the page, which is already drawn underneath, so nothing
  moves, jumps or flashes.

  Once per page per sign-in: a page already opened since signing in comes
  straight up. A tab that switches in place without loading a page (Team's
  Financial Records, the Experts tabs) is treated as a page of its own. "Opened" is kept in sessionStorage under the sign-in's
  session, so signing in again starts over.

  This runs from the sidebar, before the content has been read, and hides the
  content until the first frame; partials/render-whole holds that
  frame until the whole page has been read, so the skeleton is taken from the
  finished layout. Only the content area takes part; the sidebar and the top
  bar are drawn as they are.
--}}
<style nonce="{{ Vite::cspNonce() }}">
  /* Hidden until the skeleton is drawn over it, on the first frame. The id
     outranks partials/skeleton-head's "#real-content is shown" on staff
     pages. */
  html.csk-hide .main > :not(.topbar),
  html.csk-hide .main > #real-content { opacity: 0 !important; }
  #csk { position: fixed; z-index: 30; pointer-events: none; }
  #csk > .csk-clip { position: absolute; inset: 0; overflow: hidden; }
  #csk .csk-layer { position: absolute; left: 0; top: 0; }
  #csk .csk-sheet { position: absolute; left: 0; overflow: hidden; pointer-events: auto; transition: opacity .4s ease; }
  #csk .csk-sheet.csk-out { opacity: 0; pointer-events: none; }
  #csk i { position: absolute; display: block; box-sizing: border-box; }
  #csk .csk-card { background: #fff; border: 1px solid #e3e6ea; }
  #csk .csk-frame { border: 1px solid #e3e6ea; }
  /* Loading, plainly: a bright streak runs across every block, over and
     over, already part-way across on the first frame. It moves by transform
     alone, which the browser animates smoothly off the main thread. */
  #csk .csk-bone { background: #dde1e7; overflow: hidden; }
  #csk .csk-bone::after {
    content: ""; position: absolute; inset: 0;
    background: linear-gradient(90deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, .85) 50%, rgba(255, 255, 255, 0) 100%);
    transform: translateX(-100%);
    animation: csk-wave 1s ease-in-out var(--csk-phase, -.25s) infinite;
  }
  /* A picture or a document page: a narrower, softer streak, so the block
     still reads as grey. */
  #csk .csk-wide::after { background: linear-gradient(90deg, rgba(255, 255, 255, 0) 35%, rgba(255, 255, 255, .5) 50%, rgba(255, 255, 255, 0) 65%); }
  @keyframes csk-wave { to { transform: translateX(100%); } }
  /* With reduced motion asked for (Windows' "Animation effects" off), no
     streak, but the blocks still breathe softly, so it still reads as
     loading. */
  @keyframes csk-breathe { 50% { opacity: .5; } }
  @media (prefers-reduced-motion: reduce) {
    #csk .csk-bone::after { animation: none; display: none; }
    #csk .csk-bone { animation: csk-breathe 1.2s ease-in-out infinite; }
  }
</style>
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  if (window.__contentSkeleton) return;
  window.__contentSkeleton = true;

  var root = document.documentElement;
  var key = 'csk:{{ substr(sha1(session()->getId()), 0, 12) }}:' + (location.pathname.replace(/\/+$/, '') || '/');

  // Whether this page - or one of its tabs - is being opened for the first
  // time since signing in; marks it opened either way.
  function firstOpening(view) {
    var opened = true;
    try {
      opened = sessionStorage.getItem(key + (view ? '#' + view : '')) === '1';
      sessionStorage.setItem(key + (view ? '#' + view : ''), '1');
    } catch (e) {}
    return !opened;
  }

  var TABLE = { TABLE: 1, THEAD: 1, TBODY: 1, TFOOT: 1, TR: 1, TD: 1, TH: 1 };
  var MEDIA = { IMG: 1, SVG: 1, CANVAS: 1, VIDEO: 1, IFRAME: 1, INPUT: 1, SELECT: 1, TEXTAREA: 1, BUTTON: 1, PROGRESS: 1, METER: 1 };
  var SKIP = { SCRIPT: 1, STYLE: 1, TEMPLATE: 1, NOSCRIPT: 1, BR: 1, WBR: 1 };
  var LIMIT = 5000;
  var active = null;

  function visibleColour(colour) {
    var match = /rgba?\(([^)]+)\)/.exec(colour || '');
    if (!match) return colour && colour !== 'transparent';
    var parts = match[1].split(/[\s,\/]+/).filter(Boolean);
    return parts.length < 4 || parseFloat(parts[3]) > 0.04;
  }

  function alphaOf(colour) {
    var match = /rgba?\(([^)]+)\)/.exec(colour || '');
    if (!match) return colour && colour !== 'transparent' ? 1 : 0;
    var parts = match[1].split(/[\s,\/]+/).filter(Boolean);
    return parts.length < 4 ? 1 : parseFloat(parts[3]);
  }

  var SIDES = ['Top', 'Right', 'Bottom', 'Left'];

  function edged(style, side) {
    return parseFloat(style['border' + side + 'Width']) > 0 && style['border' + side + 'Style'] !== 'none' && visibleColour(style['border' + side + 'Color']);
  }

  function hasEdge(style) {
    return SIDES.some(function (side) { return edged(style, side); });
  }

  // A rule along one side (a divider) stays a rule, not a box.
  function edges(style) {
    return SIDES.map(function (side) { return edged(style, side) ? '1px' : '0'; }).join(' ');
  }

  // How many of an element's children hold words: a row like "Team name -
  // Alpha" or a tile like "25 / Total students" is a small card with lines
  // in it, where a badge holding one word is a single block.
  function worded(element) {
    var found = 0;
    for (var child = element.firstElementChild; child; child = child.nextElementSibling) {
      if (child.textContent.trim() !== '') found++;
    }
    return found;
  }

  // Puts a sheet where it covers, its skeleton lined up with the page.
  function place(sheet) {
    sheet.node.style.top = sheet.top + 'px';
    sheet.node.style.height = (sheet.bottom - sheet.top) + 'px';
    sheet.node.firstChild.style.top = (sheet.origin - sheet.top) + 'px';
    return sheet;
  }

  // The page's own part, beside the sidebar and under the top bar: the
  // largest thing in .main that is not the top bar - .content on student
  // pages, a wrapper around it on most staff pages, the whole Defenses or
  // Financial board on theirs.
  function areaOf(main) {
    var best = null;
    var largest = 0;
    for (var child = main.firstElementChild; child; child = child.nextElementSibling) {
      if (SKIP[child.tagName] || child.classList.contains('topbar')) continue;
      var style = getComputedStyle(child);
      if (style.display === 'none' || style.position === 'fixed') continue;
      var rect = child.getBoundingClientRect();
      if (rect.width * rect.height > largest) {
        largest = rect.width * rect.height;
        best = child;
      }
    }
    return best;
  }

  // What scrolls in it: the area itself, or the box filling it that does.
  function scrollerIn(area) {
    for (var element = area; element;) {
      if (/(auto|scroll|overlay)/.test(getComputedStyle(element).overflowY)) return element;
      var inside = null;
      for (var child = element.firstElementChild; child; child = child.nextElementSibling) {
        var rect = child.getBoundingClientRect();
        if (rect.height >= element.clientHeight * 0.6 && rect.width >= element.clientWidth * 0.6) {
          inside = child;
          break;
        }
      }
      element = inside;
    }
    return area;
  }

  function build(since) {
    var main = document.querySelector('.main');
    var area = main && areaOf(main);
    if (!area) {
      if (document.readyState === 'loading') return requestAnimationFrame(function () { build(since); });
      return root.classList.remove('csk-hide');
    }
    var content = scrollerIn(area);

    var box = content.getBoundingClientRect();
    var originX = box.left - content.scrollLeft;
    var originY = box.top - content.scrollTop;
    var layer = document.createElement('div');
    layer.className = 'csk-layer';
    var cover = null;
    var sheets = [];
    var tail = null;
    var fixed = [];
    // The skeleton keeps to the content area as it is now, not as it was on
    // the first frame: the area can still grow or move as the page settles,
    // which used to leave a strip of the real page showing below the
    // skeleton. The blocks follow the content as it is scrolled.
    function follow() {
      if (!cover) return;
      var now = content.getBoundingClientRect();
      var top = Math.max(now.top, 0);
      var bottom = Math.min(now.bottom, window.innerHeight);
      cover.style.left = now.left + 'px';
      cover.style.top = top + 'px';
      cover.style.width = now.width + 'px';
      cover.style.height = Math.max(0, bottom - top) + 'px';
      layer.style.left = -content.scrollLeft + 'px';
      layer.style.top = (now.top - top - content.scrollTop) + 'px';
      if (tail && !tail.done && content.scrollHeight > tail.bottom) {
        tail.bottom = content.scrollHeight;
        place(tail);
      }
      fixed.forEach(function (part) {
        var at = part.element.getBoundingClientRect();
        part.node.style.left = (at.left - now.left) + 'px';
        part.node.style.top = (at.top - top) + 'px';
      });
    }
    var count = 0;
    var pieces = [];

    function draw(kind, left, top, width, height, radius, edges) {
      if (count++ > LIMIT || width < 2 || height < 2) return;
      var piece = document.createElement('i');
      piece.className = kind;
      piece.style.cssText = 'left:' + (left - originX) + 'px;width:' + width + 'px;height:' + height + 'px;border-radius:' + radius + (edges ? ';border-width:' + edges : '');
      pieces.push({ node: piece, top: top - originY, bottom: top - originY + height });
    }

    // A line of words is a bar as wide as the words and a little shorter
    // than the letters, so lines of a paragraph sit apart as they do.
    function lines(node, style) {
      var range = document.createRange();
      range.selectNodeContents(node);
      var size = parseFloat(style.fontSize) || 14;
      var bar = Math.max(6, Math.round(size * 0.72));
      var last = null;
      Array.prototype.forEach.call(range.getClientRects(), function (rect) {
        if (rect.width < 1 || rect.height < 1) return;
        if (last && Math.abs(rect.top - last.top) < 2 && rect.left - last.right < 8) {
          last.right = Math.max(last.right, rect.right);
          return;
        }
        if (last) draw('csk-bone', last.left, last.top + (last.height - bar) / 2, last.right - last.left, bar, '4px');
        last = { left: rect.left, right: rect.right, top: rect.top, height: rect.height };
      });
      if (last) draw('csk-bone', last.left, last.top + (last.height - bar) / 2, last.right - last.left, bar, '4px');
    }

    function walk(element) {
      if (count > LIMIT || SKIP[element.tagName] || element.id === 'csk') return;
      var style = getComputedStyle(element);
      if (style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity) === 0 || style.position === 'fixed') return;
      // Laid out but never drawn - the menu inside a closed dropdown - is not
      // part of what shows.
      if (element.checkVisibility && !element.checkVisibility({ contentVisibilityAuto: true })) return;

      var rect = element.getBoundingClientRect();
      var sized = rect.width >= 2 && rect.height >= 2;
      var radius = style.borderRadius || '0';
      var tag = element.tagName.toUpperCase();

      // A big button - a remark card, a list row - is a card with words in
      // it, like anything else that size, not one solid block.
      var pressable = tag === 'BUTTON' || element.getAttribute('role') === 'button';
      var big = rect.height > 64 || (rect.width > 360 && rect.height > 44);
      if ((MEDIA[tag] && !(pressable && big)) || (pressable && !big)) {
        if (sized) draw(rect.width > 240 && rect.height > 160 ? 'csk-bone csk-wide' : 'csk-bone', rect.left, rect.top, rect.width, rect.height, radius === '0px' ? '6px' : radius);
        return;
      }

      // A table keeps its rules between rows; its cells are never blocks,
      // only the words and buttons in them.
      if (TABLE[tag] && tag !== 'TABLE') {
        if (sized && hasEdge(style)) draw('csk-frame', rect.left, rect.top, rect.width, rect.height, '0', edges(style));
        for (var cell = element.firstChild; cell; cell = cell.nextSibling) {
          if (cell.nodeType === 3 && cell.nodeValue.trim() !== '') lines(cell, style);
          else if (cell.nodeType === 1) walk(cell);
        }
        return;
      }

      var filled = visibleColour(style.backgroundColor) || style.backgroundImage !== 'none';
      var bordered = hasEdge(style);
      var lifted = style.boxShadow !== 'none';
      // A see-through mark laid over something else - a highlight on a
      // document page - is not a card of its own.
      if (style.position === 'absolute' && style.backgroundImage === 'none' && alphaOf(style.backgroundColor) < 0.5) {
        filled = bordered = lifted = false;
      }
      if (sized && (filled || bordered || lifted)) {
        // Badges, pills, chips, small buttons and bars are one solid block;
        // anything larger - or holding buttons of its own, like a row of
        // tabs, or several pieces of words - is a card, drawn in place with
        // its own contents.
        var small = (rect.height <= 56 && rect.width <= 360) || rect.height <= 14 || (tag === 'A' && rect.height <= 64);
        if (small && tag !== 'TABLE' && (tag === 'A' || !(element.querySelector('button, a, input, select, textarea, [role="button"]') || worded(element) >= 2))) {
          draw('csk-bone', rect.left, rect.top, rect.width, rect.height, radius === '0px' ? '6px' : radius);
          return;
        }
        if (filled || lifted) draw('csk-card', rect.left, rect.top, rect.width, rect.height, radius);
        else draw('csk-frame', rect.left, rect.top, rect.width, rect.height, radius, edges(style));
      }

      for (var node = element.firstChild; node; node = node.nextSibling) {
        if (node.nodeType === 3 && node.nodeValue.trim() !== '') lines(node, style);
        else if (node.nodeType === 1) walk(node);
      }
    }

    for (var child = content.firstElementChild; child; child = child.nextElementSibling) walk(child);

    // What sits around the part that scrolls, in the page itself rather
    // than in a dialog - Team Management's "Find members / Find team" key, a
    // staff page's header above its list - is part of the page too: each its
    // own piece of skeleton, which stays where it is as the content scrolls.
    var parts = [];
    for (var inner = content; inner && inner !== main && inner.parentElement; inner = inner.parentElement) {
      for (var beside = inner.parentElement.firstElementChild; beside; beside = beside.nextElementSibling) {
        if (beside === inner || SKIP[beside.tagName] || beside.classList.contains('topbar')) continue;
        var placed = getComputedStyle(beside);
        var sized = beside.getBoundingClientRect();
        if (placed.display !== 'none' && placed.visibility !== 'hidden' && placed.position !== 'fixed' && sized.width >= 2 && sized.height >= 2) {
          parts.push({ element: beside, pieces: [], box: sized });
        }
      }
    }
    var contentPieces = pieces;
    parts.forEach(function (part) {
      pieces = part.pieces;
      originX = part.box.left;
      originY = part.box.top;
      walk(part.element);
    });
    pieces = contentPieces;

    cover = document.createElement('div');
    cover.id = 'csk';
    cover.setAttribute('aria-hidden', 'true');
    var surface = getComputedStyle(content).backgroundColor;
    for (var holder = content; !visibleColour(surface) && holder.parentElement; holder = holder.parentElement) {
      surface = getComputedStyle(holder.parentElement).backgroundColor;
    }
    surface = visibleColour(surface) ? surface : '#eceef0';

    // The skeleton comes in bands - each run of cards with nothing between
    // them - laid edge to edge down the whole page.
    pieces.sort(function (one, other) { return one.top - other.top; });
    var bands = [];
    pieces.forEach(function (piece) {
      var band = bands[bands.length - 1];
      if (band && piece.top <= band.bottom + 1) {
        band.bottom = Math.max(band.bottom, piece.bottom);
        band.pieces.push(piece);
      } else {
        bands.push({ top: piece.top, bottom: piece.bottom, pieces: [piece] });
      }
    });
    if (!bands.length) bands.push({ top: 0, bottom: 0, pieces: [] });
    var end = Math.max(content.scrollHeight, bands[bands.length - 1].bottom);
    var wide = Math.max(content.scrollWidth, box.width);
    sheets = bands.map(function (band, at) {
      var top = at === 0 ? 0 : (bands[at - 1].bottom + band.top) / 2;
      var bottom = at === bands.length - 1 ? end : (band.bottom + bands[at + 1].top) / 2;
      var sheet = document.createElement('div');
      sheet.className = 'csk-sheet';
      sheet.style.cssText = 'width:' + wide + 'px;background:' + surface;
      var inner = document.createElement('div');
      inner.className = 'csk-layer';
      band.pieces.forEach(function (piece) {
        piece.node.style.top = (piece.top - top) + 'px';
        inner.appendChild(piece.node);
      });
      sheet.appendChild(inner);
      layer.appendChild(sheet);
      return place({ node: sheet, origin: top, top: top, bottom: bottom, done: false });
    });
    tail = sheets[sheets.length - 1];

    var clip = document.createElement('div');
    clip.className = 'csk-clip';
    clip.appendChild(layer);
    cover.appendChild(clip);

    parts.forEach(function (part) {
      var partSheet = document.createElement('div');
      partSheet.className = 'csk-sheet';
      partSheet.style.cssText = 'width:' + part.box.width + 'px;height:' + part.box.height + 'px;background:' + surface;
      part.pieces.forEach(function (piece) {
        piece.node.style.top = piece.top + 'px';
        partSheet.appendChild(piece.node);
      });
      cover.appendChild(partSheet);
      var record = { node: partSheet, element: part.element, top: 0, bottom: 0, done: false, fixed: true };
      fixed.push(record);
      sheets.push(record);
    });

    follow();
    document.body.appendChild(cover);
    // A page still being read stays hidden under its skeleton; the skeleton
    // is drawn again as the rest comes in, and the last time, once all of
    // it has, lets it show.
    if (document.readyState !== 'loading') root.classList.remove('csk-hide');

    // The page can be scrolled while it loads: the wheel, or a finger, over
    // the skeleton scrolls the content beneath.
    cover.addEventListener('wheel', function (event) {
      content.scrollBy(event.deltaX, event.deltaY);
    }, { passive: true });
    var fingerY = null;
    cover.addEventListener('touchstart', function (event) { fingerY = event.touches[0].clientY; }, { passive: true });
    cover.addEventListener('touchmove', function (event) {
      if (fingerY === null) return;
      content.scrollBy(0, fingerY - event.touches[0].clientY);
      fingerY = event.touches[0].clientY;
    }, { passive: true });

    var shown = since || performance.now();
    var finished = false;
    var revealed = false;
    // Drawn again, the streak carries on from where it was.
    cover.style.setProperty('--csk-phase', -((performance.now() - shown + 250) % 1000) + 'ms');

    function picturesReady() {
      var view = content.getBoundingClientRect();
      return Array.prototype.every.call(content.querySelectorAll('img'), function (image) {
        if (image.complete) return true;
        var at = image.getBoundingClientRect();
        return at.bottom < view.top || at.top > view.bottom || at.width === 0;
      });
    }
    function lift(sheet) {
      sheet.done = true;
      sheet.node.classList.add('csk-out');
      setTimeout(function () { sheet.node.remove(); }, 450);
    }
    // The whole page gives way together, down to its end: rows further down
    // a list are not left loading on their own when they are scrolled to.
    function reveal() {
      revealed = true;
      if (watching) watching.disconnect();
      finish();
    }
    function finish() {
      if (finished) return;
      finished = true;
      clearInterval(ticking);
      if (resizing) resizing.disconnect();
      if (watching) watching.disconnect();
      content.removeEventListener('scroll', onScroll);
      window.removeEventListener('resize', finishNow);
      sheets.forEach(function (sheet) { if (!sheet.done) lift(sheet); });
      setTimeout(function () { cover.remove(); }, 450);
      if (active === stop) active = null;
    }
    function finishNow() {
      sheets.forEach(function (sheet) { sheet.done = true; });
      finish();
      cover.remove();
    }
    var stop = finishNow;
    active = stop;

    // The page shows loading for 0.9s from the first frame, and until the
    // pictures in view are in - never more than 0.7s longer - then all of
    // it gives way at once, wherever it has been scrolled to meanwhile.
    var due = Math.max(performance.now(), shown) + 900;
    function onScroll() { follow(); }
    function tick() {
      follow();
      var now = performance.now();
      // A page left alone for long may change under the skeleton; past
      // 15s whatever is left gives way.
      if (now - shown > 15000) return finish();
      // A page that draws part of itself once its data is in (the Review
      // viewer's document and remarks) holds the first moment until it has
      // - never more than 4s.
      // Nor does it give way while it is about to be drawn again: the page
      // changed, and the skeleton showing is the one from before.
      var held = !revealed && ((holds > 0 && now < shown + 4000) || redrawing);
      if (!held && now >= due && (now >= due + 700 || picturesReady())) reveal();
      if (sheets.every(function (sheet) { return sheet.done; })) finish();
    }
    var ticking = setInterval(tick, 50);
    content.addEventListener('scroll', onScroll, { passive: true });
    // Checked again before every frame the content area changes size in,
    // so the skeleton never falls short of it.
    var resizing = window.ResizeObserver ? new ResizeObserver(follow) : null;
    if (resizing) resizing.observe(content);
    window.addEventListener('resize', finishNow);

    // Until the first part gives way, the skeleton is drawn again whenever
    // the page draws more of itself - a script filling in a document,
    // remarks, a list - so it matches what will be there, not what was
    // there on the first frame. At most every 150ms; the page stays covered
    // in between.
    var built = performance.now();
    var redrawing = false;
    function redraw(soon) {
      if (redrawing || revealed || finished) return;
      redrawing = true;
      setTimeout(function () {
        requestAnimationFrame(function () {
          redrawing = false;
          if (revealed || finished) return;
          finished = true;
          clearInterval(ticking);
          if (resizing) resizing.disconnect();
          watching.disconnect();
          content.removeEventListener('scroll', onScroll);
          window.removeEventListener('resize', finishNow);
          cover.remove();
          if (active === stop) active = null;
          try { build(shown); } catch (e) { root.classList.remove('csk-hide'); }
        });
      }, soon ? 0 : Math.max(0, 150 - (performance.now() - built)));
    }
    redrawNow = function () { redraw(true); };
    var watching = window.MutationObserver ? new MutationObserver(function () { redraw(false); }) : null;
    if (watching) watching.observe(document.readyState === 'loading' ? main : content, { childList: true, subtree: true });
  }

  // A tab that switches in place (Team's Financial Records, the Experts
  // tabs) gets its own first-time skeleton: the page calls show() right
  // after drawing the tab, before the browser paints it, and seen() for the
  // tab it opened on.
  var holds = 0;
  var redrawNow = null;
  // The page has been read to its end: the skeleton is drawn from all of it.
  document.addEventListener('readystatechange', function () {
    if (document.readyState === 'interactive' && redrawNow) redrawNow();
  });
  window.contentSkeleton = {
    seen: function (view) { firstOpening(view); },
    // A page that draws part of itself once its data is in holds the
    // skeleton until it has, by calling the function this returns.
    hold: function () {
      holds++;
      var released = false;
      return function () {
        if (released) return;
        released = true;
        holds--;
      };
    },
    show: function (view) {
      // What is left of the skeleton further down belongs to the tab that was
      // there before: it goes, whether or not this tab gets its own.
      if (active) active();
      if (!firstOpening(view)) return;
      try { build(); } catch (e) {}
    },
  };

  if (!firstOpening()) return;
  root.classList.add('csk-hide');

  // The first frame: render-whole holds it until the page has been read.
  requestAnimationFrame(function () {
    try { build(); } catch (e) { root.classList.remove('csk-hide'); }
  });
  // Whatever happens, the content never stays hidden.
  setTimeout(function () { root.classList.remove('csk-hide'); }, 3000);
})();
</script>
