{{--
  The CapsTrack scrollbar: a small navy handle with gold arrows on the right
  edge of whatever box you point at or scroll - the page, a table, a modal's
  list. Drag it, or click the line under it to jump.

  One script finds every scroll box by itself, so a new page or table needs
  nothing. The handle sits exactly where the list is: no easing, because a bar
  that trails behind the rows reads as lag. Only the fade in and out moves.

  The sidebar still scrolls, it just never gets a handle. Text boxes keep the
  browser's own bar. Sideways scrolling keeps a thin native bar, since the
  handle only does up and down.
--}}
<style nonce="{{ Vite::cspNonce() }}">
@supports selector(::-webkit-scrollbar){
  *:not(textarea)::-webkit-scrollbar{width:0!important}
  *:not(textarea)::-webkit-scrollbar:horizontal{height:8px}
  *:not(textarea)::-webkit-scrollbar-thumb{background:#c9ccd4;border-radius:4px}
}
/* ponytail: Firefox can't hide only the up-down bar, so sideways bars go too. */
@supports not selector(::-webkit-scrollbar){ *:not(textarea){scrollbar-width:none} }
#sb-layer{position:fixed;inset:0;pointer-events:none;z-index:2147483000}
#sb-layer .sb{position:fixed}
#sb-layer .sb-track{position:absolute;right:9px;top:8px;bottom:8px;width:2px;border-radius:2px;background:#d4d7de;cursor:pointer;opacity:0;transition:opacity .4s}
#sb-layer .sb-track::before{content:'';position:absolute;inset:0 -6px}
#sb-layer .sb-handle{position:absolute;right:3px;top:0;width:14px;height:30px;border-radius:7px;background:#18191f;cursor:grab;box-shadow:0 2px 8px rgba(24,25,31,.22);will-change:transform;opacity:0;translate:6px 0;transition:opacity .4s,translate .4s,scale .15s,background-color .15s}
#sb-layer .sb-handle svg{position:absolute;inset:0;margin:auto;width:8px;height:14px;fill:none;stroke:#c9a82c;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
#sb-layer .sb.active .sb-track,#sb-layer .sb.active .sb-handle{opacity:1;translate:0 0;pointer-events:auto;transition-duration:.18s}
#sb-layer .sb-handle:hover{scale:1.12}
#sb-layer .sb-handle.dragging{cursor:grabbing;background:#272a36;scale:1.12}
@media (prefers-reduced-motion:reduce){ #sb-layer .sb-track,#sb-layer .sb-handle{transition:none} }
</style>
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  if (window.__appScrollbar) return; window.__appScrollbar = true;
  var layer, bars = [], hovered = [], root = document.scrollingElement || document.documentElement;

  function scrolls(el) {
    if (el === root) return el.scrollHeight > el.clientHeight;
    if (el.tagName === 'TEXTAREA' || el.closest('.sidebar,.nav')) return false;
    var o = getComputedStyle(el).overflowY;
    return (o === 'auto' || o === 'scroll') && el.scrollHeight > el.clientHeight + 1;
  }
  function barFor(el) {
    for (var i = 0; i < bars.length; i++) if (bars[i].el === el) return bars[i];
    if (!scrolls(el)) return null;
    if (!layer) { layer = document.createElement('div'); layer.id = 'sb-layer'; document.body.appendChild(layer); }
    var sb = document.createElement('div');
    sb.className = 'sb';
    sb.innerHTML = '<i class="sb-track"></i><b class="sb-handle"><svg viewBox="0 0 8 14"><path d="M1.5 4.5 4 2 6.5 4.5M1.5 9.5 4 12 6.5 9.5"/></svg></b>';
    layer.appendChild(sb);
    var bar = {el: el, sb: sb, handle: sb.lastChild, timer: 0};
    // Clip to every box it sits inside, so a table half scrolled off the page shows half a track.
    bar.clips = [];
    for (var p = el.parentElement; p && el !== root; p = p.parentElement) {
      if (p === root || getComputedStyle(p).overflowY !== 'visible') bar.clips.push(p);
    }
    bar.handle.addEventListener('pointerdown', function (e) {
      e.preventDefault(); bar.handle.setPointerCapture(e.pointerId); bar.handle.classList.add('dragging');
      var y0 = e.clientY, top0 = el.scrollTop;
      bar.handle.onpointermove = function (m) {
        var room = el.clientHeight - bar.head - 16 - bar.handle.offsetHeight;
        el.scrollTop = top0 + (m.clientY - y0) * (el.scrollHeight - el.clientHeight) / room;
      };
    });
    bar.handle.addEventListener('pointerup', function () {
      bar.handle.onpointermove = null; bar.handle.classList.remove('dragging');
      wake(bar, hovered.indexOf(bar) > -1);
    });
    // Click the track: jump there.
    sb.firstChild.addEventListener('click', function (e) {
      var r = this.getBoundingClientRect();
      el.scrollTop = (e.clientY - r.top) / r.height * (el.scrollHeight - el.clientHeight);
    });
    bars.push(bar);
    update(bar);
    return bar;
  }

  function wake(bar, stay) {
    bar.sb.classList.add('active'); clearTimeout(bar.timer);
    if (!stay && !bar.handle.classList.contains('dragging')) {
      bar.timer = setTimeout(function () { bar.sb.classList.remove('active'); }, 1000);
    }
  }

  // A table header that stays put while the rows scroll: the bar starts under it.
  function headOf(el) {
    var th = el !== root && el.querySelector('thead th');
    return th && getComputedStyle(th).position === 'sticky' ? th.parentElement.offsetHeight : 0;
  }

  function update(bar) {
    var el = bar.el, sb = bar.sb, max = el.scrollHeight - el.clientHeight;
    if (!el.isConnected) { sb.remove(); bars.splice(bars.indexOf(bar), 1); return; }
    var head = bar.head = headOf(el), h = el.clientHeight - head;
    var r = el === root ? {top: 0, left: 0, bottom: el.clientHeight} : el.getBoundingClientRect();
    r = {top: r.top + el.clientTop + head, left: r.left + el.clientLeft};
    var top = r.top, bottom = r.top + h;
    bar.clips.forEach(function (p) {
      var pr = p === root ? {top: 0, bottom: innerHeight} : p.getBoundingClientRect();
      top = Math.max(top, pr.top); bottom = Math.min(bottom, pr.bottom);
    });
    if (max <= 1 || bottom - top < 24) { sb.style.display = 'none'; return; }
    sb.style.display = '';
    sb.style.left = r.left + 'px'; sb.style.top = r.top + 'px';
    sb.style.width = el.clientWidth + 'px'; sb.style.height = h + 'px';
    sb.style.clipPath = 'inset(' + Math.max(0, top - r.top) + 'px 0 ' + Math.max(0, r.top + h - bottom) + 'px 0)';
    var y = 8 + el.scrollTop / max * (h - 16 - bar.handle.offsetHeight);
    bar.handle.style.transform = 'translateY(' + y + 'px)';
  }

  var queued = false;
  function updateAll() {
    if (queued) return; queued = true;
    requestAnimationFrame(function () { queued = false; bars.slice().forEach(update); });
  }

  // Every scroll box under the pointer, innermost first.
  function boxesAt(node) {
    var out = [];
    for (var el = node; el && el.nodeType === 1; el = el.parentElement) {
      var b = (el === root || el.scrollHeight > el.clientHeight + 1) && barFor(el);
      if (b) out.push(b);
    }
    var rb = barFor(root);
    if (rb && out.indexOf(rb) < 0) out.push(rb);
    return out;
  }

  document.addEventListener('pointerover', function (e) {
    if (e.pointerType === 'touch' || (layer && layer.contains(e.target))) return;
    var now = boxesAt(e.target);
    hovered.forEach(function (b) { if (now.indexOf(b) < 0) wake(b, false); });
    now.forEach(function (b) { wake(b, true); update(b); });
    hovered = now;
  }, {passive: true});
  document.documentElement.addEventListener('pointerleave', function () {
    hovered.forEach(function (b) { wake(b, false); }); hovered = [];
  });
  document.addEventListener('scroll', function (e) {
    var b = barFor(e.target === document ? root : e.target);
    if (b) wake(b, hovered.indexOf(b) > -1);
    updateAll();
  }, {capture: true, passive: true});
  addEventListener('resize', updateAll);
})();
</script>
