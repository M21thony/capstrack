{{--
  Keeps what is being typed into above a phone's on-screen keyboard, with its
  label, on every page it is included on (the student sidebar carries it).

  Two kinds of phone:
  - Android Chrome shrinks the page to the room above the keyboard, because
    each student page asks it to (interactive-widget=resizes-content). Fixed
    pop-ups and 100dvh layouts then shrink with it on their own.
  - iPhone Safari and older browsers leave the page its full height and only
    the visible part shrinks (window.visualViewport). A pop-up there stays the
    height of the screen, and its field and label end up under the keyboard -
    so the open pop-up around the field is held to the visible part here.

  Either way: the field is scrolled into view together with its label, and the
  phone tab bar is hidden while the keyboard is up, since it would otherwise
  sit on top of the keyboard taking room from the form.
--}}
@once
<style nonce="{{ Vite::cspNonce() }}">
  html.kb-open .mtab{display:none!important}
  html.kb-open .main{padding-bottom:0!important}

  /* A textarea otherwise types in the browser's monospace, unlike every
     other box on the form. */
  textarea,input,select,button{font-family:inherit}

  /* iPhone Safari zooms the whole page in on any field under 16px when it is
     tapped, and often leaves it zoomed - the layout then looks broken until
     pinched back. 16px on the phone's fields stops the zoom. iPhone only
     (-webkit-touch-callout exists nowhere else), so Android keeps its sizes. */
  @supports (-webkit-touch-callout:none){
    @media (max-width:768px){
      input:not([type=checkbox]):not([type=radio]):not([type=range]),textarea,select{font-size:16px!important}
    }
  }
</style>
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var vv = window.visualViewport;
  var root = document.documentElement;
  // The page's height with no keyboard, per orientation: a phone shrinking
  // the page for its keyboard is told apart from one simply turning.
  var fullHeight = {};
  var held = null;       // the pop-up currently held above the keyboard
  var heldStyle = null;  // its own inline style, to hand back
  var settle = null;

  function orientation() { return window.innerWidth > window.innerHeight ? 'l' : 'p'; }

  function typing() {
    var el = document.activeElement;
    if (!el || el === document.body) return null;
    if (el.isContentEditable) return el;
    if (el.tagName === 'TEXTAREA') return el;
    if (el.tagName === 'INPUT' && !/^(button|submit|reset|checkbox|radio|file|range|color|image|hidden)$/i.test(el.type)) return el;
    return null;
  }

  // The fixed pop-up (overlay, sheet or <dialog>) around a field, if any.
  function popUpAround(el) {
    for (var node = el.parentElement; node && node !== document.body; node = node.parentElement) {
      var cs = getComputedStyle(node);
      if (cs.position !== 'fixed' || node.classList.contains('sidebar')) continue;
      var r = node.getBoundingClientRect();
      if (r.height >= window.innerHeight * 0.5) return node;
    }
    return null;
  }

  // What to bring into view: the field with its label, where the form keeps
  // them together; the field alone otherwise.
  function withLabel(el) {
    var group = el.closest('.form-group, .pp-field, .field, .fx-field-wrap, label');
    if (group && group.getBoundingClientRect().height < (vv ? vv.height : window.innerHeight) * 0.7) return group;
    if (el.id) {
      var label = document.querySelector('label[for="' + (window.CSS && CSS.escape ? CSS.escape(el.id) : el.id) + '"]');
      if (label) {
        var box = el.getBoundingClientRect();
        var lb = label.getBoundingClientRect();
        if (box.bottom - lb.top < (vv ? vv.height : window.innerHeight) * 0.7) return { label: label, field: el };
      }
    }
    return el;
  }

  function reveal(el) {
    var target = withLabel(el);
    var top = target.label || target;
    var bottom = target.field || target;
    var visibleTop = vv ? vv.offsetTop : 0;
    var visibleBottom = visibleTop + (vv ? vv.height : window.innerHeight);
    var t = top.getBoundingClientRect().top;
    var b = bottom.getBoundingClientRect().bottom;
    if (t >= visibleTop + 8 && b <= visibleBottom - 8) return;
    top.scrollIntoView({ block: 'start', behavior: 'auto' });
    // A long textarea taller than the room: at least its top and caret line.
    if (bottom.getBoundingClientRect().top > visibleBottom - 24) bottom.scrollIntoView({ block: 'end', behavior: 'auto' });
  }

  function release() {
    if (!held) return;
    held.setAttribute('style', heldStyle || '');
    if (!heldStyle) held.removeAttribute('style');
    held = null;
    heldStyle = null;
  }

  function hold(popUp, room, offset) {
    if (held && held !== popUp) release();
    if (!held) {
      held = popUp;
      heldStyle = popUp.getAttribute('style');
    }
    popUp.style.setProperty('top', offset + 'px', 'important');
    popUp.style.setProperty('bottom', 'auto', 'important');
    popUp.style.setProperty('height', room + 'px', 'important');
    popUp.style.setProperty('max-height', room + 'px', 'important');
  }

  function update() {
    var key = orientation();
    var field = typing();
    if (!field) {
      // Only learn the full height while nothing is being typed into.
      fullHeight[key] = Math.max(fullHeight[key] || 0, window.innerHeight);
    }

    var full = fullHeight[key] || window.innerHeight;
    var visible = vv ? vv.height : window.innerHeight;
    // Keyboard over the page (iPhone), or the page shrunk for it (Android).
    var covered = vv ? Math.max(0, window.innerHeight - vv.height - vv.offsetTop) : 0;
    var shrunk = Math.max(0, full - window.innerHeight);
    var open = !!field && (covered > 120 || shrunk > 120);

    root.classList.toggle('kb-open', open);
    root.style.setProperty('--kb', (open ? covered : 0) + 'px');

    if (!open) { release(); return; }

    var popUp = popUpAround(field);
    if (popUp && covered > 0) {
      hold(popUp, Math.floor(visible), Math.floor(vv ? vv.offsetTop : 0));
    } else {
      release();
    }

    reveal(field);
  }

  function soon() {
    clearTimeout(settle);
    // After the keyboard has finished sliding in.
    settle = setTimeout(update, 120);
  }

  update();
  if (vv) {
    vv.addEventListener('resize', soon);
    vv.addEventListener('scroll', soon);
  }
  window.addEventListener('resize', soon);
  document.addEventListener('focusin', function () { soon(); setTimeout(update, 450); });
  document.addEventListener('focusout', function () { setTimeout(update, 60); });
})();
</script>
@endonce
