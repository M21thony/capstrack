/*
 * Helpers shared by Panel Assignment and Defense Scheduling.
 */

export const $ = (selector, root = document) => root.querySelector(selector);

export const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

export const initials = name => String(name || '')
  .replace(/^(Dr|Engr|Ms|Mrs|Mr|Prof)\.\s*/i, '')
  .split(/\s+/).filter(Boolean).map(w => w[0]).slice(0, 2).join('').toUpperCase();

export const fmtDay = iso => new Date(iso + 'T00:00').toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
export const fmtShort = iso => new Date(iso + 'T00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

export const SLOT_LABEL = { '08:00': '8:00 – 10:00 AM', '10:00': '10:00 AM – 12:00 PM', '12:00': '12:00 – 2:00 PM', '14:00': '2:00 – 4:00 PM' };

const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/** JSON to and from the server. A refusal throws an Error carrying the status and the server's body. */
export async function api(method, url, body) {
  const res = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  let data = {};
  try { data = await res.json(); } catch { /* empty answer */ }
  if (!res.ok || data.success === false) {
    const first = data.errors ? Object.values(data.errors)[0]?.[0] : null;
    const error = new Error(first || data.message || 'That did not save. Check your connection and try again.');
    error.status = res.status;
    error.body = data;
    throw error;
  }
  return data;
}

let toastEl, toastTimer;
export function toast(text) {
  if (!toastEl) {
    toastEl = document.createElement('div');
    toastEl.className = 'dx-toast';
    toastEl.setAttribute('popover', 'manual');
    toastEl.setAttribute('role', 'status');
    document.body.append(toastEl);
  }
  toastEl.textContent = text;
  try { toastEl.hidePopover(); } catch { /* not open */ }
  try { toastEl.showPopover(); } catch { /* popovers unsupported */ }
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { try { toastEl.hidePopover(); } catch { /* closed */ } }, 4000);
}

/** A message shown after the page reloads with the saved change. */
export function reloadWith(text) {
  try { sessionStorage.setItem('dx-flash', text); } catch { /* storage blocked */ }
  window.location.reload();
}

export function showFlash() {
  try {
    const text = sessionStorage.getItem('dx-flash');
    if (text) { sessionStorage.removeItem('dx-flash'); toast(text); }
  } catch { /* storage blocked */ }
}

/**
 * Why this panel can't take this block, or null when it is free - the same
 * rules the server's schedule maker uses. $who names the other booking.
 */
export function clashWith(held, panel, date, time, venue, nameOf) {
  const mine = panel.slice().sort().join();
  for (const h of held) {
    if (h.date !== date) continue;
    const theirs = h.panel.slice().sort().join();
    if (h.time === time && theirs === mine) return { text: `This panel is already with ${h.who} at this time.`, other: !!h.other };
    if (h.time === time && h.venue === venue) return { text: `${venue} is taken at this time by ${h.who}.`, other: !!h.other };
    const shared = h.panel.find(x => panel.includes(x));
    if (theirs !== mine && shared) return { text: `${nameOf(shared)} sits on ${h.who}'s panel that day.`, other: !!h.other };
  }
  return null;
}

/** Close any dialog from a [data-close] button or a click on its backdrop. */
export function wireDialogs() {
  document.addEventListener('click', e => {
    const close = e.target.closest('[data-close]');
    if (close) close.closest('dialog')?.close();
  });
  document.querySelectorAll('dialog.dx-dialog').forEach(d => d.addEventListener('click', e => { if (e.target === d) d.close(); }));
}
