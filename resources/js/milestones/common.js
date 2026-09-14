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

export function btn(label, cls = '', onClick = null) {
  const b = el('button', ('ms-btn ' + cls).trim(), label);
  b.type = 'button';
  if (onClick) b.addEventListener('click', onClick);
  return b;
}

const SPRITE = `<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0" aria-hidden="true">
<symbol id="ms-i-open" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/></symbol>
<symbol id="ms-i-done" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="8 12.5 11 15.5 16 9.5"/></symbol>
<symbol id="ms-i-handed" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></symbol>
<symbol id="ms-i-overdue" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="7.5" x2="12" y2="13"/><circle cx="12" cy="16.3" r=".6" fill="currentColor"/></symbol>
<symbol id="ms-i-chev" viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></symbol>
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

/** A confirmation box. Resolves true only if Remove was pressed. */
export function confirmRemove(title, body) {
  return new Promise(resolve => {
    const dialog = el('dialog', 'ms-dialog ms-small');
    dialog.setAttribute('role', 'alertdialog');
    dialog.setAttribute('aria-label', title);

    let answer = false;
    const cancel = btn('Cancel', '', () => dialog.close());
    const ok = btn('Remove', 'ms-btn-danger', () => { answer = true; dialog.close(); });

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
