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

/* ---------------- Times ----------------
 * A slot is a start time ("14:00") and a length. Its label is worked out, not
 * looked up: how long a defense runs is set per stage, so there is no fixed
 * list of blocks to name.
 */
const parts = hhmm => { const [h, m] = String(hhmm).split(':').map(Number); return { h, m: m || 0 }; };
const meridiem = h => (h >= 12 ? 'PM' : 'AM');
const clock = ({ h, m }) => `${h % 12 || 12}:${String(m).padStart(2, '0')}`;

/** "14:00" plus 90 minutes is "15:30". */
export const addMinutes = (hhmm, minutes) => {
  const { h, m } = parts(hhmm);
  const total = h * 60 + m + Number(minutes);
  return `${String(Math.floor(total / 60) % 24).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
};

/** "8:00 – 10:00 AM", or "11:00 AM – 12:30 PM" when the two sides differ. */
export const slotRange = (start, end) => {
  const a = parts(start), b = parts(end);
  return meridiem(a.h) === meridiem(b.h)
    ? `${clock(a)} – ${clock(b)} ${meridiem(b.h)}`
    : `${clock(a)} ${meridiem(a.h)} – ${clock(b)} ${meridiem(b.h)}`;
};

export const slotLabel = (start, minutes) => slotRange(start, addMinutes(start, minutes));

/** 90 is "1 hr 30 min"; 45 is "45 min"; 120 is "2 hr". */
export const durationText = minutes => {
  const h = Math.floor(minutes / 60), m = minutes % 60;
  return [h ? `${h} hr` : '', m ? `${m} min` : ''].filter(Boolean).join(' ');
};

/** The same rule as DefenseWindow::slotsFor on the server - used only to preview what a length will do to the day. */
export const slotsFor = (dayStart, dayEnd, duration) => {
  const s = parts(dayStart), e = parts(dayEnd);
  const end = e.h * 60 + e.m;
  let at = s.h * 60 + s.m;
  const out = [];
  while (duration > 0 && at + duration <= end) {
    out.push(`${String(Math.floor(at / 60)).padStart(2, '0')}:${String(at % 60).padStart(2, '0')}`);
    at += duration;
  }
  return out;
};

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
export function clashWith(held, panel, date, time, end, venue, nameOf) {
  const mine = panel.slice().sort().join();
  for (const h of held) {
    if (h.date !== date) continue;
    const theirs = h.panel.slice().sort().join();
    // Overlapping, not starting together: stages can be set to different lengths.
    // "HH:MM" strings compare correctly as text.
    const overlaps = h.time < end && h.end > time;
    if (overlaps && theirs === mine) return { text: `This panel is already with ${h.who} at this time.`, other: !!h.other };
    if (overlaps && h.venue === venue) return { text: `${venue} is taken at this time by ${h.who}.`, other: !!h.other };
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
