{{--
  Escape closes whatever is open, on every page that includes this.

  Ten-odd modals across the app, each opened and closed by its own pair of
  functions, and only a handful ever bound Escape - so whether the key worked
  depended on which panel you happened to be looking at. That is worse than
  it never working, because a key that works four times out of five is a key
  people keep pressing.

  One listener, and it closes by the same rule the app already uses to open:
  the topmost visible overlay, whatever hid it. Nothing to remember when the
  next modal is written.

  Deliberately last-resort. A panel that must not be dismissed says so by
  carrying data-no-escape - the role choice is the only one today, because
  every roster row and the CaPSA's verification list read the field it sets.
--}}
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  // Every shape a modal takes in this codebase. They accumulated separately;
  // this is the list, not a new convention to migrate them all onto.
  const SHELLS = [
    '.modal-overlay',
    '.upload-scrim',
    '.ct-modal-overlay',
    '.profile-overlay',
    '.pp-modal-overlay',
    '.popover-overlay',
  ].join(',');

  const isShowing = (el) => {
    if (el.hidden || el.dataset.noEscape !== undefined) return false;

    const style = getComputedStyle(el);

    if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
      return false;
    }

    // Zero-sized is another way these hide themselves.
    const box = el.getBoundingClientRect();

    return box.width > 0 && box.height > 0;
  };

  // The one in front, so Escape peels panels off in the order they were
  // stacked rather than closing the form underneath the preview on top of it.
  const topmost = () => {
    const open = Array.prototype.filter.call(document.querySelectorAll(SHELLS), isShowing);

    if (!open.length) return null;

    return open.reduce((front, el) => {
      const a = parseInt(getComputedStyle(el).zIndex, 10) || 0;
      const b = parseInt(getComputedStyle(front).zIndex, 10) || 0;

      return a >= b ? el : front;
    });
  };

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape' || event.defaultPrevented) return;

    const panel = topmost();

    if (!panel) return;

    // Its own close button knows what else to reset - a frame to empty, a
    // form to clear - so prefer that over hiding the box behind it.
    const closer = panel.querySelector(
      '.pm-close-btn, .pp-x, .sheet-x, .ct-modal-close, [data-close], [aria-label="Close"]'
    );

    if (closer) {
      closer.click();

      return;
    }

    // Nothing to click: hide it the way its own stylesheet does.
    panel.classList.remove('active', 'show', 'open');
    if (panel.style.display === 'flex' || panel.style.display === 'block') {
      panel.style.display = 'none';
    }
    panel.hidden = true;
    document.body.style.overflow = '';
  });
})();
</script>
