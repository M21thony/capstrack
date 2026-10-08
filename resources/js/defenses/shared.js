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

/** 'HH:MM' plus some minutes, as 'HH:MM'. */
export const addMinutes = (time, m) => {
  const [h, mm] = time.split(':').map(Number);
  const t = h * 60 + mm + m;
  return `${String(Math.floor(t / 60)).padStart(2, '0')}:${String(t % 60).padStart(2, '0')}`;
};

const clock = time => {
  const [h, m] = time.split(':').map(Number);
  return `${((h + 11) % 12) + 1}:${String(m).padStart(2, '0')} ${h < 12 ? 'AM' : 'PM'}`;
};

/** '08:00', '09:30' as '8:00 AM – 9:30 AM'. */
export const slotLabel = (start, end) => end ? `${clock(start)} – ${clock(end)}` : clock(start);

export const isWeekend = iso => [0, 6].includes(new Date(iso + 'T00:00').getDay());

/** Earliest day first, then room, then time: one room's defenses on a day sit together. */
export const byDayRoomTime = (a, b) => a.date.localeCompare(b.date) || (a.venue || '').localeCompare(b.venue || '') || a.time.localeCompare(b.time);

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
export function toast(text, type) {
  if (window.ctToast) { return window.ctToast(text, type); }
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
 * Why this panel can't sit in $venue from $time to $end on $date, or null when
 * it can - the same rules as the server's clash(). $who names the other booking.
 *
 * - A panelist is never in two defenses whose times overlap.
 * - A panelist who already sits a defense that day stays in that room, so the
 *   whole panel has to use it.
 * - A room holds one defense at a time.
 */
export function clashWith(held, panel, date, time, end, venue, nameOf) {
  for (const h of held) {
    if (h.date !== date) continue;
    const overlaps = h.time < end && time < (h.end || addMinutes(h.time, 120));
    const shared = h.panel.find(x => panel.includes(x));

    if (shared && overlaps) return { text: `${nameOf(shared)} is already on ${h.who}'s panel at this time.`, other: !!h.other };
    if (shared && h.venue !== venue) return { text: `${nameOf(shared)} is in ${h.venue} that day for ${h.who}, so this panel has to use ${h.venue} too.`, other: !!h.other, room: h.venue };
    if (overlaps && h.venue === venue) return { text: `${venue} is taken at this time by ${h.who}.`, other: !!h.other };
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
