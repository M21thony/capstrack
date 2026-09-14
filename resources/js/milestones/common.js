/*
 * Shared pieces for the milestone tracker and the Edit milestones window:
 * building elements, talking to the server, the toast with Undo, and
 * redrawing without losing scroll position or keyboard focus.
 */

const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export function el(tag, cls, text) {
  const node = document.createElement(tag);
  if (cls) node.className = cls;
  if (text != null) node.textContent = text;
  return node;
}

/** A button with its words, and - when one is named - its icon on a tinted tile in front of them. */
export function btn(label, cls = '', onClick = null, iconName = null) {
  const b = el('button', ('ms-btn ' + cls).trim());
  b.type = 'button';
  if (iconName) {
    const tile = el('span', 'ms-ico');
    tile.append(icon(iconName));
    b.append(tile);
  }
  b.append(label);
  if (onClick) b.addEventListener('click', onClick);
  return b;
}

const SPRITE = `<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0" aria-hidden="true">
<symbol id="ms-i-open" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/></symbol>
<symbol id="ms-i-done" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="8 12.5 11 15.5 16 9.5"/></symbol>
<symbol id="ms-i-handed" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></symbol>
<symbol id="ms-i-overdue" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="7.5" x2="12" y2="13"/><circle cx="12" cy="16.3" r=".6" fill="currentColor"/></symbol>
<symbol id="ms-i-chev" viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></symbol>
<symbol id="ms-i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
<symbol id="ms-i-archive" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="5" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/></symbol>
<symbol id="ms-i-trash" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M10 11v6M14 11v6"/></symbol>
<symbol id="ms-i-restore" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></symbol>
<symbol id="ms-i-expand" viewBox="0 0 24 24"><path d="m7 6 5 5 5-5"/><path d="m7 13 5 5 5-5"/></symbol>
<symbol id="ms-i-collapse" viewBox="0 0 24 24"><path d="m17 11-5-5-5 5"/><path d="m17 18-5-5-5 5"/></symbol>
<symbol id="ms-i-calendar" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></symbol>
<symbol id="ms-i-x" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></symbol>
<symbol id="ms-i-check" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></symbol>
<symbol id="ms-i-undo" viewBox="0 0 24 24"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></symbol>
<symbol id="ms-i-move" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></symbol>
<symbol id="ms-i-grip" viewBox="0 0 24 24"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></symbol>
</svg>`;

let spriteAdded = false;

export function icon(name, cls = '', label = null) {
  if (!spriteAdded) {
    document.body.insertAdjacentHTML('afterbegin', SPRITE);
    spriteAdded = true;
  }
  const NS = 'http://www.w3.org/2000/svg';
  const svg = document.createElementNS(NS, 'svg');
  svg.setAttribute('class', ('ms-i ' + cls).trim());
  if (label) {
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', label);
  } else {
    svg.setAttribute('aria-hidden', 'true');
  }
  const use = document.createElementNS(NS, 'use');
  use.setAttribute('href', '#ms-i-' + name);
  svg.append(use);
  return svg;
}

export const fmtDate = iso => iso
  ? new Date(iso + 'T00:00:00').toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
  : '';

export const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;

export function ordinal(n) {
  const suffix = [11, 12, 13].includes(n % 100) ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' })[n % 10] || 'th';
  return n + suffix;
}

/** Route templates carry __ID__ where the id goes. */
export const fill = (url, id) => url.replace('__ID__', encodeURIComponent(id));

/** JSON to and from the server; a refusal comes back as an Error carrying the server's own message. */
export async function api(method, url, body) {
  const res = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': CSRF,
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });

  let data = {};
  try {
    data = await res.json();
  } catch {
    // An empty or non-JSON answer.
  }

  if (!res.ok) {
    const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
    throw new Error(firstError || data.message || 'That did not save. Check your connection and try again.');
  }

  return data;
}

let live;

/** Tells screen reader users what just happened. */
export function say(text) {
  if (!live) {
    live = el('div', 'ms-sr');
    live.setAttribute('aria-live', 'polite');
    document.body.append(live);
  }
  live.textContent = '';
  setTimeout(() => { live.textContent = text; }, 30);
}

let toastEl;
let toastTimer;

/** A message at the bottom of the screen, above any open window, with Undo when there is something to undo. */
export function toast(text, undo = null) {
  if (!toastEl) {
    toastEl = el('div', 'ms-toast');
    toastEl.setAttribute('role', 'status');
    toastEl.setAttribute('popover', 'manual');
    document.body.append(toastEl);
  }

  const hide = () => {
    try { toastEl.hidePopover(); } catch { /* not open */ }
  };

  toastEl.replaceChildren(el('span', '', text));

  if (undo) {
    const b = el('button', '', 'Undo');
    b.type = 'button';
    b.addEventListener('click', async () => {
      hide();
      try {
        await undo();
        say('Undone.');
      } catch (e) {
        toast(e.message);
      }
    });
    toastEl.append(b);
  }

  hide();
  try { toastEl.showPopover(); } catch { /* popovers unsupported */ }
  say(text);
  clearTimeout(toastTimer);
  toastTimer = setTimeout(hide, 7000);
}

let viaKeyboard = false;
window.addEventListener('keydown', () => { viaKeyboard = true; }, true);
window.addEventListener('pointerdown', () => { viaKeyboard = false; }, true);

/**
 * Redraws, keeping every list where it was scrolled and putting keyboard
 * focus back on the element named by state.focusKey. A mouse click gets no
 * focus ring left behind.
 */
export function redraw(rootEl, draw, state) {
  const tops = {};
  rootEl.querySelectorAll('[data-scroll]').forEach(node => { tops[node.dataset.scroll] = node.scrollTop; });

  draw();

  rootEl.querySelectorAll('[data-scroll]').forEach(node => {
    if (tops[node.dataset.scroll] != null) node.scrollTop = tops[node.dataset.scroll];
  });

  if (state.focusKey) {
    const target = rootEl.querySelector(`[data-key="${CSS.escape(state.focusKey)}"]`);
    if (target) {
      const typing = target.type === 'search' || target.type === 'text';
      if (viaKeyboard || typing) {
        target.focus();
        target.scrollIntoView({ block: 'nearest' });
        if (typing) target.setSelectionRange(target.value.length, target.value.length);
      }
      state.focusKey = null;
    }
  }
}

/** A confirmation box for removing something. Resolves true only if Remove was pressed. */
export function confirmRemove(title, body) {
  return confirmBox(title, body, 'Remove', 'trash');
}

/** A confirmation box. Resolves true only if the red button was pressed. */
export function confirmBox(title, body, okLabel, okIcon) {
  return new Promise(resolve => {
    const dialog = el('dialog', 'ms-dialog ms-small');
    dialog.setAttribute('role', 'alertdialog');
    dialog.setAttribute('aria-label', title);

    let answer = false;
    const cancel = btn('Cancel', '', () => dialog.close());
    const ok = btn(okLabel, 'ms-btn-danger', () => { answer = true; dialog.close(); }, okIcon);

    const acts = el('div', 'ms-acts');
    acts.append(cancel, ok);
    const wrap = el('div', 'ms-sd');
    wrap.append(el('h2', '', title), el('p', '', body), acts);
    dialog.append(wrap);
    document.body.append(dialog);

    dialog.addEventListener('close', () => { dialog.remove(); resolve(answer); });
    dialog.showModal();
    cancel.focus();
  });
}
