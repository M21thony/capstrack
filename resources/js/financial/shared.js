/* Helpers for the two Financial Records pages. */

export const $ = (selector, root = document) => root.querySelector(selector);
export const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
export const peso = n => '₱' + Number(n).toLocaleString('en-PH', { maximumFractionDigits: 2 });
export const LABEL = { pending: 'Waiting', verified: 'Verified', sent_back: 'Sent back', none: 'Not uploaded' };
export const statusHtml = s => `<span class="fx-status fx-s-${s}"><i></i>${LABEL[s]}</span>`;
export const barHtml = (c, n) => `<span class="fx-v" style="width:${(c.verified / n) * 100}%"></span><span class="fx-w" style="width:${(c.pending / n) * 100}%"></span><span class="fx-b" style="width:${(c.sent_back / n) * 100}%"></span>`;

const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/** JSON or FormData to the server; a refusal throws with the server's message. */
export async function send(method, url, body) {
  const isForm = body instanceof FormData;
  const res = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', ...(isForm ? {} : { 'Content-Type': 'application/json' }) },
    body: body === undefined ? undefined : (isForm ? body : JSON.stringify(body)),
  });
  let data = {};
  try { data = await res.json(); } catch { /* empty answer */ }
  if (!res.ok || data.success === false) {
    const first = data.errors ? Object.values(data.errors)[0]?.[0] : null;
    throw new Error(first || data.message || 'That did not save. Check your connection and try again.');
  }
  return data;
}

let toastEl, toastTimer;
export function toast(text) {
  if (!toastEl) {
    toastEl = document.createElement('div');
    toastEl.className = 'fx-toast';
    toastEl.setAttribute('popover', 'manual');
    toastEl.setAttribute('role', 'status');
    document.body.append(toastEl);
  }
  toastEl.textContent = text;
  try { toastEl.hidePopover(); } catch { /* not open */ }
  try { toastEl.showPopover(); } catch { /* popovers unsupported */ }
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { try { toastEl.hidePopover(); } catch { /* closed */ } }, 3500);
}

export function reloadWith(text) {
  try { sessionStorage.setItem('fx-flash', text); } catch { /* storage blocked */ }
  window.location.reload();
}

export function showFlash() {
  try {
    const text = sessionStorage.getItem('fx-flash');
    if (text) { sessionStorage.removeItem('fx-flash'); toast(text); }
  } catch { /* storage blocked */ }
}

/** Full-size picture, and closing dialogs from [data-close] or the backdrop. */
export function wireDialogs() {
  document.addEventListener('click', e => {
    const full = e.target.closest('[data-full]');
    if (full) { $('#fx-img-full').src = full.dataset.full; $('#fx-d-image').showModal(); }
    if (e.target.closest('[data-close]')) e.target.closest('dialog')?.close();
  });
  document.querySelectorAll('dialog.fx-dialog').forEach(d => d.addEventListener('click', e => { if (e.target === d || d.id === 'fx-d-image') d.close(); }));
}
