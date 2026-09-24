/*
 * Handing in a document, where the student already is.
 *
 * On the Document Submission page the form is part of the page. Anywhere
 * else a button opens it in place - under a line on Progress, inside the
 * Review Viewer's top bar - on the line it was opened from, so there is
 * nothing to pick: just the file, who it goes to, and Submit.
 *
 * The in-place form is fetched fresh every time, so the version number, the
 * recipients and the checklist lines are never stale.
 */
import { initSubmissionForm, showToast } from './submission-form.js';

const FORM_URL = document.querySelector('meta[name="submission-form-url"]')?.content;

function closeInline() {
  document.querySelectorAll('.sub-inline').forEach(panel => panel.remove());
}

/**
 * Open the form under (or inside) whatever asked for it. The button's nearest
 * [data-submission-host] decides where: after it, or - for a host marked
 * "inside" - as its last child.
 */
export async function openSubmission(itemId, from) {
  if (!FORM_URL) return;
  closeInline();

  const host = (from && from.closest('[data-submission-host]')) || (from && from.parentElement) || document.body;
  const panel = document.createElement('section');
  panel.className = 'sub-inline';
  panel.setAttribute('aria-label', 'Submit a document');

  const head = document.createElement('div');
  head.className = 'sub-inline-head';
  const title = document.createElement('b');
  title.textContent = 'Submit a document';
  head.append(title);
  const slot = document.createElement('div');
  slot.innerHTML = '<p class="dw-loading">Loading…</p>';
  panel.append(head, slot);

  if (host.dataset.submissionHost === 'inside') host.append(panel);
  else host.after(panel);
  panel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });

  try {
    const url = FORM_URL + '?inline=1' + (itemId ? '&item=' + encodeURIComponent(itemId) : '');
    const html = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => {
      if (!r.ok) throw new Error(r.status);
      return r.text();
    });
    slot.innerHTML = html;
    const root = slot.querySelector('#submission-form-root');
    // Say which line it is for, since the step asking for it is not shown.
    const picked = root.querySelector('#checklistItem option:checked');
    if (picked && picked.value) {
      title.textContent = 'Submit: ' + picked.textContent.split(' — ')[0];
    }
    initSubmissionForm(root);
    root.querySelector('#dropZone')?.focus();
  } catch (e) {
    slot.innerHTML = '<p class="dw-loading">The submission form could not be loaded. Please reload the page.</p>';
  }
}

window.openSubmission = openSubmission;
// Older buttons still call it by its old name.
window.openSubmissionDrawer = openSubmission;
window.submissionToast = showToast;

document.addEventListener('click', (e) => {
  if (e.target.closest('[data-submission-close]')) closeInline();
});
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && document.activeElement?.closest('.sub-inline')) closeInline();
});

// The Document Submission page: the form is already there.
const pageForm = document.querySelector('#submission-form-root[data-mode="page"]');
if (pageForm) initSubmissionForm(pageForm);
