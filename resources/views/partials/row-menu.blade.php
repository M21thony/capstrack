{{--
  The little menu at the end of a row.

  One button per row, and everything you can do to that one person behind it
  with its name written out. Icons alone cost a hover to read and say nothing
  at all on a touch screen; three labelled buttons in every row cost more
  width than these tables have.

  A row's menu is always about that row, whether or not anything is ticked -
  which is how editing one student stays reachable while four are selected.
  The selection bar carries the other half: the actions that mean something
  when applied to several people at once.

  Include once per page, then write this in the row's last cell:

    <div class="rowmenu">
      <button type="button" class="icon-btn rowmenu-btn" aria-expanded="false"
              aria-label="Actions for Jerome Abella">
        <svg viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.6"/>...</svg>
      </button>
      <div class="rowmenu-pop" hidden>
        <button type="button" class="rowmenu-item" onclick="...">Edit student</button>
        <button type="button" class="rowmenu-item danger" onclick="...">Remove</button>
      </div>
    </div>
--}}
<script>
(function () {
  let open = null;

  function close() {
    if (!open) return;
    open.pop.hidden = true;
    open.btn.setAttribute('aria-expanded', 'false');
    open = null;
  }

  // Fixed rather than absolute: the table scrolls inside its own card, and an
  // absolutely positioned menu on one of the last rows would be cut off at
  // the card's edge instead of opening.
  function place(btn, pop) {
    pop.hidden = false;
    const b = btn.getBoundingClientRect();
    const p = pop.getBoundingClientRect();
    const gap = 6;

    let top = b.bottom + gap;
    if (top + p.height > window.innerHeight - 8) {
      top = Math.max(8, b.top - p.height - gap);
    }

    pop.style.top = top + 'px';
    pop.style.left = Math.max(8, b.right - p.width) + 'px';
  }

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.rowmenu-btn');

    if (!btn) {
      // An item runs its own onclick and then the menu has done its job.
      if (e.target.closest('.rowmenu-item')) close();
      else if (!e.target.closest('.rowmenu-pop')) close();
      return;
    }

    const pop = btn.parentElement.querySelector('.rowmenu-pop');
    const wasOpen = open && open.pop === pop;
    close();
    if (wasOpen) return;

    open = { btn: btn, pop: pop };
    btn.setAttribute('aria-expanded', 'true');
    place(btn, pop);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') close();
  });

  // Scrolling the list moves the row out from under a menu that is pinned to
  // the window, so the menu goes with it.
  window.addEventListener('scroll', close, true);
  window.addEventListener('resize', close);
})();
</script>
