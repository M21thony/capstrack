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
  content until the first frame; student/partials/render-whole holds that
  frame until the whole page has been read, so the skeleton is taken from the
  finished layout. Only the content area takes part; the sidebar and the top
  bar are drawn as they are.
--}}
<style nonce="{{ Vite::cspNonce() }}">
  html.csk-hide .main > .content { opacity: 0 !important; }
  #csk { position: fixed; z-index: 30; overflow: hidden; transition: opacity .4s ease; }
  #csk.csk-out { opacity: 0; pointer-events: none; }
  #csk > div { position: absolute; left: 0; top: 0; }
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
    animation: csk-wave 1s ease-in-out -.25s infinite;
  }
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

  function visibleColour(colour) {
    var match = /rgba?\(([^)]+)\)/.exec(colour || '');
    if (!match) return colour && colour !== 'transparent';
    var parts = match[1].split(/[\s,\/]+/).filter(Boolean);
    return parts.length < 4 || parseFloat(parts[3]) > 0.04;
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

  function build() {
    var content = document.querySelector('.main > .content');
    if (!content) return root.classList.remove('csk-hide');

    var box = content.getBoundingClientRect();
    var originX = box.left - content.scrollLeft;
    var originY = box.top - content.scrollTop;
    var layer = document.createElement('div');
    // The blocks follow the content if it is scrolled.
    function follow() {
      layer.style.left = -content.scrollLeft + 'px';
      layer.style.top = -content.scrollTop + 'px';
    }
    var count = 0;

    function draw(kind, left, top, width, height, radius, edges) {
      if (count++ > LIMIT || width < 2 || height < 2) return;
      var piece = document.createElement('i');
      piece.className = kind;
      piece.style.cssText = 'left:' + (left - originX) + 'px;top:' + (top - originY) + 'px;width:' + width + 'px;height:' + height + 'px;border-radius:' + radius + (edges ? ';border-width:' + edges : '');
      layer.appendChild(piece);
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

      var rect = element.getBoundingClientRect();
      var sized = rect.width >= 2 && rect.height >= 2;
      var radius = style.borderRadius || '0';
      var tag = element.tagName.toUpperCase();

      if (MEDIA[tag] || element.getAttribute('role') === 'button') {
        if (sized) draw('csk-bone', rect.left, rect.top, rect.width, rect.height, radius === '0px' ? '6px' : radius);
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

    var cover = document.createElement('div');
    cover.id = 'csk';
    cover.setAttribute('aria-hidden', 'true');
    var surface = getComputedStyle(content).backgroundColor;
    for (var holder = content; !visibleColour(surface) && holder.parentElement; holder = holder.parentElement) {
      surface = getComputedStyle(holder.parentElement).backgroundColor;
    }
    cover.style.cssText = 'left:' + box.left + 'px;top:' + box.top + 'px;width:' + box.width + 'px;height:' + box.height + 'px;background:' + (visibleColour(surface) ? surface : '#eceef0');
    follow();
    cover.appendChild(layer);
    document.body.appendChild(cover);
    root.classList.remove('csk-hide');

    content.addEventListener('scroll', follow, { passive: true });
    // The whole page is drawn, down to its end, so it can be scrolled while
    // it loads: the wheel, or a finger, over the skeleton scrolls the
    // content beneath.
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

    // The page underneath is already drawn: the skeleton stays long enough
    // to be seen loading, and until the pictures in view
    // have loaded (never more than 1.6s), then fades away over it.
    var shown = performance.now();
    var done = false;
    function reveal() {
      if (done) return;
      done = true;
      window.removeEventListener('resize', reveal);
      cover.classList.add('csk-out');
      setTimeout(function () {
        content.removeEventListener('scroll', follow);
        cover.remove();
      }, 450);
    }
    function picturesReady() {
      return Array.prototype.every.call(content.querySelectorAll('img'), function (image) {
        if (image.complete) return true;
        var at = image.getBoundingClientRect();
        return at.bottom < box.top || at.top > box.bottom || at.width === 0;
      });
    }
    (function wait() {
      if (done) return;
      var waited = performance.now() - shown;
      if (waited >= 1600 || (waited >= 900 && picturesReady())) return reveal();
      setTimeout(wait, 50);
    })();
    window.addEventListener('resize', reveal);
  }

  // A tab that switches in place (Team's Financial Records, the Experts
  // tabs) gets its own first-time skeleton: the page calls show() right
  // after drawing the tab, before the browser paints it, and seen() for the
  // tab it opened on.
  window.contentSkeleton = {
    seen: function (view) { firstOpening(view); },
    show: function (view) {
      if (!firstOpening(view) || document.getElementById('csk')) return;
      try { build(); } catch (e) {}
    },
  };

  if (!firstOpening()) return;
  root.classList.add('csk-hide');

  // The first frame: render-whole holds it until the page has been read.
  requestAnimationFrame(function first() {
    if (document.readyState === 'loading') return requestAnimationFrame(first);
    try { build(); } catch (e) { root.classList.remove('csk-hide'); }
  });
  // Whatever happens, the content never stays hidden.
  setTimeout(function () { root.classList.remove('csk-hide'); }, 3000);
})();
</script>
