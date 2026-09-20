/*
 * The slide-in panel for handing in a document. It lives in the student
 * sidebar partial, so it is on every student page and no one has to leave
 * what they were looking at to submit a file.
 *
 * The form inside it is fetched fresh on every open, so the version number,
 * the recipients and the checklist lines are never stale.
 */
import { initSubmissionForm, showToast } from './submission-form.js';

const drawer = document.getElementById('submission-drawer');
const scrim = document.getElementById('submission-scrim');
const slot = drawer ? drawer.querySelector('#submission-drawer-slot') : null;

function close() {
  if (!drawer) return;
  drawer.classList.remove('on');
  scrim.classList.remove('on');
}

export async function openSubmissionDrawer(itemId) {
  if (!drawer) return;

  slot.innerHTML = '<p class="dw-loading">Loading…</p>';
  drawer.classList.add('on');
  scrim.classList.add('on');

  try {
    const url = drawer.dataset.url + (itemId ? '?item=' + encodeURIComponent(itemId) : '');
    const html = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => {
      if (!r.ok) throw new Error(r.status);
      return r.text();
    });
    slot.innerHTML = html;
    initSubmissionForm(slot.querySelector('#submission-form-root'));
  } catch (e) {
    slot.innerHTML = '<p class="dw-loading">The submission form could not be loaded. Please reload the page.</p>';
  }
}

if (drawer) {
  window.openSubmissionDrawer = openSubmissionDrawer;
  window.submissionToast = showToast;

  scrim.addEventListener('click', close);
  // The × in the header, and Cancel inside the fetched form.
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-drawer-close]')) close();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && drawer.classList.contains('on')) close();
  });
}
