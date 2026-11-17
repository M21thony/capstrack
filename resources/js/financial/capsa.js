/*
 * The CaPSA/TSA's Financial Records: one fee at a time, every student in her
 * sections in one table, and a receipt opened in a drawer on the right with
 * Verify / Send back. A decision moves straight to the next receipt for review.
 */
import { $, esc, peso, send, toast, reloadWith, showFlash, wireDialogs } from './shared.js';

const root = document.getElementById('fx-capsa');
const S = JSON.parse(root.dataset.state);
const URLS = JSON.parse(root.dataset.urls);
const FEES = S.fees;
const TEAMS = S.teams;
const R = S.receipts;
const NAME = {}, TEAM_OF = {};
TEAMS.forEach(t => t.members.forEach(m => { NAME[m.id] = m.name; TEAM_OF[m.id] = t; }));
const SECTIONS = [...new Set([...S.sections, ...TEAMS.map(t => t.section)])].filter(Boolean).sort();

const state = { fee: FEES[0]?.id ?? null, section: '', search: '', status: 'all', page: 1, pageSize: 10, asc: true };
let current = null, sendingBack = false;

const rec = (f, m) => R[`${f}:${m}`];
const st = (f, m) => rec(f, m)?.status || 'none';
const feeOf = id => FEES.find(x => x.id === id);
const feeLabel = x => `${x.stage} Fee` + (FEES.filter(y => y.stage === x.stage).length > 1 ? ` · ${x.program}` : '');
// Verified and not with the dean yet, as [feeId, memberId, receipt].
const unsent = () => Object.entries(R).filter(([, r]) => r.status === 'verified' && !r.sent).map(([key, r]) => { const [f, m] = key.split(':'); return [+f, +m, r]; });
const QUICK = ['The OR number does not match the receipt.', 'The photo is blurry.', 'The amount is wrong.', "It isn't the official receipt.", 'The name is a different person.'];
const today = () => new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
// "or 00123 " and "OR 00123" are the same receipt - as the server compares them.
const normaliseOr = v => v.trim().replace(/\s+/g, ' ').toUpperCase();

const LABEL = { pending: 'For review', verified: 'Verified', sent_back: 'Sent back', none: 'Not uploaded' };
const CLASS = { pending: 'pending', verified: 'verified', sent_back: 'returned', none: 'missing' };
const statusHtml = s => `<span class="ct-status ct-status-${CLASS[s]}">${LABEL[s]}</span>`;
const icon = name => `<svg class="ct-i"><use href="#ct-i-${name}"/></svg>`;

/* ---------------- Rows ---------------- */
const sectionIds = () => TEAMS.filter(t => !state.section || t.section === state.section).flatMap(t => t.members.map(m => m.id));
const searchedIds = () => {
  const q = state.search.trim().toLowerCase();
  return sectionIds().filter(m => !q || NAME[m].toLowerCase().includes(q) || TEAM_OF[m].name.toLowerCase().includes(q));
};
const shownIds = () => searchedIds()
  .filter(m => state.status === 'all' || st(state.fee, m) === state.status)
  .sort((a, b) => NAME[a].localeCompare(NAME[b]) * (state.asc ? 1 : -1));
const tally = ids => { const c = { all: ids.length, pending: 0, verified: 0, sent_back: 0, none: 0 }; ids.forEach(m => c[st(state.fee, m)]++); return c; };

function render() {
  const f = feeOf(state.fee), sends = unsent();
  $('#c-send').disabled = !sends.length;
  $('#c-send-n').textContent = sends.length;

  $('#ct-fee').innerHTML = FEES.map(x => `<option value="${x.id}" ${x.id === state.fee ? 'selected' : ''}>${esc(feeLabel(x))}</option>`).join('');
  $('#ct-feebar').classList.toggle('is-empty', !f);
  $('#ct-amount').innerHTML = f ? `<b>${peso(f.amount)}</b> / student` : 'No defense fee yet. Create one with <b>New fee</b>.';
  const all = sectionIds(), c = tally(all);
  $('#ct-progress').value = all.length ? c.verified / all.length : 0;
  $('#ct-progress-label').textContent = f ? `${c.verified} of ${all.length} verified` : '';

  const searched = searchedIds(), counts = tally(searched);
  root.querySelectorAll('[data-count]').forEach(el => { el.textContent = f ? counts[el.dataset.count] : 0; });
  root.querySelectorAll('[data-status]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.status === state.status)));
  $('#ct-student-count').textContent = `${searched.length} ${searched.length === 1 ? 'student' : 'students'}`;

  const ids = f ? shownIds() : [], pages = Math.max(1, Math.ceil(ids.length / state.pageSize));
  state.page = Math.min(state.page, pages);
  const from = (state.page - 1) * state.pageSize, slice = ids.slice(from, from + state.pageSize);
  $('#c-rows').innerHTML = slice.map(m => {
    const r = rec(state.fee, m), s = st(state.fee, m), t = TEAM_OF[m];
    return `<tr>
      <td><div class="ct-student-name">${esc(NAME[m])}${t.leader === m ? ' <span class="ct-leader">Leader</span>' : ''}</div><div class="ct-team-name">${esc(t.name)}</div></td>
      <td>${esc(t.section)}</td>
      <td>${r?.at ? esc(r.at) : '<span class="ct-dash">—</span>'}</td>
      <td>${statusHtml(s)}</td>
      <td>${r ? `<button class="ct-link" type="button" data-open="${m}">${icon('eye')}View receipt</button>` : '<span class="ct-dash">—</span>'}</td></tr>`;
  }).join('');
  $('#ct-empty').hidden = slice.length > 0;
  $('#ct-empty-t').textContent = f ? 'No students found' : 'No defense fee yet';
  $('#ct-empty-p').textContent = f ? 'Try another name, section, or receipt status.' : 'Create one with New fee.';
  $('#ct-clear').hidden = !f;
  $('#ct-export').disabled = !ids.length;
  $('#ct-report-btn').disabled = !f;
  $('#ct-sort').closest('th').setAttribute('aria-sort', state.asc ? 'ascending' : 'descending');

  $('#ct-range').textContent = ids.length ? `Showing ${from + 1}–${from + slice.length} of ${ids.length} students` : '0 students';
  // First, last, and the pages either side of this one.
  const nums = [...new Set([1, state.page - 1, state.page, state.page + 1, pages])].filter(n => n >= 1 && n <= pages).sort((a, b) => a - b);
  $('#ct-pages').innerHTML = `<button class="ct-page" type="button" data-page="${state.page - 1}" ${state.page === 1 ? 'disabled' : ''} aria-label="Previous page">${icon('left')}</button>`
    + nums.map((n, i) => (i && n - nums[i - 1] > 1 ? '<span class="ct-gap">…</span>' : '')
      + `<button class="ct-page" type="button" data-page="${n}" ${n === state.page ? 'aria-current="page"' : ''}>${n}</button>`).join('')
    + `<button class="ct-page" type="button" data-page="${state.page + 1}" ${state.page === pages ? 'disabled' : ''} aria-label="Next page">${icon('right')}</button>`;
}

/* ---------------- Receipt drawer ---------------- */
function openDrawer(m) {
  current = m; sendingBack = false;
  renderDrawer();
  const d = $('#ct-drawer');
  if (!d.open) d.showModal();
}

function renderDrawer() {
  const m = current, r = rec(state.fee, m), s = st(state.fee, m), t = TEAM_OF[m], f = feeOf(state.fee);
  const pending = s === 'pending';
  $('#ct-drawer-t').textContent = NAME[m];
  $('#ct-drawer-sub').textContent = `Team ${t.name} · ${t.section}`;
  // The number the student typed, beside the photo it has to match. While
  // the receipt waits, she can put right a number they mistyped; it is
  // saved with the verification.
  const orRow = r.or && pending
    ? `<dt><label for="c-or">OR number</label></dt><dd><input class="ct-or-edit" id="c-or" value="${esc(r.or)}" maxlength="40" autocomplete="off" spellcheck="false" aria-describedby="c-or-hint">
        <div class="ct-or-hint" id="c-or-hint">As the student typed it. Fix it here if it does not match the photo.</div></dd>`
    : r.or
    ? `<dt>OR number</dt><dd class="ct-or">${esc(r.or)}</dd>`
    : '<dt>OR number</dt><dd class="ct-muted">Not given (uploaded before OR numbers were asked for)</dd>';
  $('#ct-drawer-body').innerHTML = `
    <div class="ct-drawer-status">${statusHtml(s)}<span>${esc(feeLabel(f))}</span></div>
    <button class="ct-receipt" type="button" data-full="${esc(r.img)}" aria-label="Open full size"><img src="${esc(r.img)}" alt="${esc(NAME[m])}'s receipt"></button>
    ${r.or && r.or_also?.length ? `<div class="ct-or-warn">This OR number is also on ${esc(r.or_also.join(', '))}'s receipt. Check it is not the same payment claimed twice.</div>` : ''}
    <dl class="ct-details">${orRow}
      <dt>Amount due</dt><dd>${peso(f.amount)}</dd>
      <dt>Uploaded</dt><dd>${esc(r.at || '—')}</dd>
      ${s === 'verified' ? `<dt>Verified</dt><dd>${esc(r.on || '')}</dd>` : ''}
      ${r.sent ? `<dt>Sent to Dean</dt><dd>${esc(r.sent)}</dd>` : ''}</dl>
    ${s === 'sent_back' ? `<div class="ct-feedback"><strong>Correction requested</strong>${esc(r.reason)}</div>` : ''}
    ${pending && r.or ? `<label class="ct-or-check"><input type="checkbox" id="c-or-ok"> The OR number on the photo is <b id="c-or-echo">${esc(r.or)}</b></label>` : ''}`;
  $('#ct-return-form').hidden = !sendingBack;
  $('#ct-back-btn').hidden = $('#ct-verify-btn').hidden = !pending || sendingBack;
  $('#ct-verify-btn').disabled = !!r.or;
  if (sendingBack) { $('#c-reason').value = ''; $('#c-reason').focus(); }
}

// Save, then open the next receipt for review in the list, or close.
async function decide(m, status, reason = '', orNumber = null) {
  const before = shownIds(), at = before.indexOf(m);
  try {
    await send('PATCH', URLS.record.replace('__ID__', rec(state.fee, m).id), status === 'verified'
      ? { status: 'approved', or_confirmed: true, ...(orNumber !== null ? { receipt_number: orNumber } : {}) }
      : { status: 'rejected', rejection_reason: reason });
  } catch (e) {
    await outcomeDialog({ tone: 'error', title: 'That receipt was not updated', reason: e.message, body: 'Nothing changed. The list is exactly as it was before you clicked.', primaryLabel: 'Back to receipts' });
    return false;
  }
  Object.assign(R[`${state.fee}:${m}`], status === 'verified' ? { status, on: today(), ...(orNumber !== null ? { or: orNumber } : {}) } : { status, reason });
  const next = [...before.slice(at + 1), ...before.slice(0, Math.max(at, 0))].find(x => st(state.fee, x) === 'pending');
  const after = shownIds();
  if (next) state.page = Math.floor(after.indexOf(next) / state.pageSize) + 1;
  render();
  if (next) openDrawer(next); else $('#ct-drawer').close();
  return true;
}

// One receipt, after a plain yes or no: whose it is, and the OR number that
// will be saved - with what the student typed, if she changed it.
async function verifyOne(m) {
  const r = rec(state.fee, m), f = feeOf(state.fee), field = $('#c-or');
  const number = field ? normaliseOr(field.value) : null;
  if (field && !number) { toast('Type the OR number printed on the receipt.', 'error'); field.focus(); return; }
  const changed = number !== null && number !== normaliseOr(r.or || '');
  const ok = await confirmSheet({
    title: `Verify ${NAME[m]}'s receipt?`,
    body: `${f.stage} Fee · ${peso(f.amount)}.` + (number ? ` OR number ${number}.` : ' No OR number was given.')
      + ' A verification cannot be taken back — one made by mistake has to be sent back instead.',
    note: changed ? `You changed the OR number: the student typed ${r.or}, it will be saved as ${number}.` : '',
    noteWarn: changed,
    confirmLabel: 'Verify',
    cancelLabel: 'No',
  });
  if (!ok) return;
  if (await decide(m, 'verified', '', changed ? number : null)) toast(`Verified ${NAME[m]}.`);
}

/* ---------------- CSV ---------------- */
// Quoted, with a ' before anything a spreadsheet would run as a formula.
const cell = v => { let s = String(v ?? ''); if (/^[=+\-@\t\r]/.test(s)) s = `'${s}`; return `"${s.replace(/"/g, '""')}"`; };
function download(pairs, prefix, allSections = !state.section) {
  const lines = [['Student', 'Team', 'Section', 'Fee', 'Amount due', 'Uploaded', 'Status', 'OR number'],
    ...pairs.filter(([f]) => f).map(([f, m]) => { const r = rec(f.id, m), t = TEAM_OF[m]; return [NAME[m], t?.name, t?.section, feeLabel(f), f.amount, r?.at, LABEL[st(f.id, m)], r?.or]; })];
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob(['﻿' + lines.map(l => l.map(cell).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8' }));
  a.download = `${prefix}-${allSections ? 'all-sections' : state.section.toLowerCase().replace(/[^a-z0-9]+/g, '-')}.csv`;
  a.click();
  setTimeout(() => URL.revokeObjectURL(a.href), 0);
}
const feePairs = ids => ids.map(m => [feeOf(state.fee), m]);
const deanPairs = () => unsent().map(([f, m]) => [feeOf(f), m]);

/* ---------------- Events ---------------- */
const reset = () => { state.page = 1; render(); };
$('#ct-fee').addEventListener('change', e => { state.fee = +e.target.value; state.status = 'all'; reset(); });
$('#ct-section').innerHTML += SECTIONS.map(s => `<option>${esc(s)}</option>`).join('');
$('#ct-section').addEventListener('change', e => { state.section = e.target.value; reset(); });
$('#ct-search').addEventListener('input', e => { state.search = e.target.value; reset(); });
$('#ct-page-size').addEventListener('change', e => { state.pageSize = +e.target.value; reset(); });
root.querySelector('.ct-tabs').addEventListener('click', e => { const b = e.target.closest('[data-status]'); if (b) { state.status = b.dataset.status; reset(); } });
$('#ct-sort').addEventListener('click', () => { state.asc = !state.asc; render(); });
$('#ct-pages').addEventListener('click', e => { const b = e.target.closest('[data-page]'); if (b && !b.disabled) { state.page = +b.dataset.page; render(); $('#ct-table-box').scrollTop = 0; } });
$('#ct-clear').addEventListener('click', () => {
  Object.assign(state, { search: '', section: '', status: 'all' });
  $('#ct-search').value = ''; $('#ct-section').value = '';
  reset();
});
$('#c-rows').addEventListener('click', e => { const b = e.target.closest('[data-open]'); if (b) openDrawer(+b.dataset.open); });
$('#ct-export').addEventListener('click', () => download(feePairs(shownIds()), 'capstrack-financial-records'));

// The drawer.
const drawer = $('#ct-drawer');
drawer.addEventListener('close', () => { current = null; sendingBack = false; });
drawer.addEventListener('change', e => { if (e.target.id === 'c-or-ok') $('#ct-verify-btn').disabled = !e.target.checked; });
// A changed number has to be checked against the photo again.
drawer.addEventListener('input', e => {
  if (e.target.id !== 'c-or') return;
  const r = rec(state.fee, current), number = normaliseOr(e.target.value);
  $('#c-or-echo').textContent = number || '—';
  $('#c-or-ok').checked = false;
  $('#ct-verify-btn').disabled = true;
  $('#c-or-hint').textContent = number && number !== normaliseOr(r.or || '')
    ? `Changed from ${r.or}. Tick the box again once it matches the photo.`
    : 'As the student typed it. Fix it here if it does not match the photo.';
});
$('#ct-verify-btn').addEventListener('click', () => { if (current) verifyOne(current); });
$('#ct-back-btn').addEventListener('click', () => { sendingBack = true; renderDrawer(); });
$('#ct-return-cancel').addEventListener('click', () => { sendingBack = false; renderDrawer(); });
$('#ct-quick').innerHTML = QUICK.map(q => `<button type="button">${esc(q)}</button>`).join('');
$('#ct-quick').addEventListener('click', e => { const b = e.target.closest('button'); if (b) $('#c-reason').value = b.textContent; });
$('#ct-return-form').addEventListener('submit', async e => {
  e.preventDefault();
  const reason = $('#c-reason').value.trim(), m = current;
  if (!reason) { toast('Say why, so the student knows what to fix.', 'error'); $('#c-reason').focus(); return; }
  if (await decide(m, 'sent_back', reason)) toast(`Sent back to ${NAME[m]}.`);
});

// Report: the chosen fee and section.
$('#ct-report-btn').addEventListener('click', () => {
  const f = feeOf(state.fee), c = tally(sectionIds());
  $('#ct-report-sub').textContent = `${feeLabel(f)} · ${state.section || 'All sections'}`;
  $('#ct-report-grid').innerHTML = [['Students', c.all], ['Verified', c.verified], ['For review', c.pending], ['Sent back', c.sent_back], ['Not uploaded', c.none]]
    .map(([l, n]) => `<div class="ct-report-stat"><span>${l}</span><strong>${n}</strong></div>`).join('');
  $('#ct-report-total').textContent = peso(c.verified * f.amount);
  $('#ct-report').showModal();
});
$('#ct-report-csv').addEventListener('click', () => download(feePairs(sectionIds().sort((a, b) => NAME[a].localeCompare(NAME[b]))), 'capstrack-fee-report'));

// Send to Dean: every verified receipt not sent yet, whatever the filters.
$('#c-send').addEventListener('click', () => {
  const list = unsent();
  $('#ct-handoff-n').textContent = list.length;
  $('#ct-handoff-total').textContent = peso(list.reduce((sum, [id]) => sum + (+feeOf(id)?.amount || 0), 0));
  $('#ct-handoff-send').disabled = $('#ct-handoff-csv').disabled = !list.length;
  $('#ct-handoff').showModal();
});
$('#ct-handoff-csv').addEventListener('click', () => download(deanPairs(), 'capstrack-dean-report', true));
$('#ct-handoff-send').addEventListener('click', async e => {
  const list = unsent();
  e.currentTarget.disabled = true;
  try {
    const res = await send('POST', URLS.dean, {});
    list.forEach(([, , r]) => { r.sent = res.data.on; });
    $('#ct-handoff').close();
    render();
    toast(res.message);
  } catch (err) {
    await outcomeDialog({ tone: 'error', title: 'Nothing was sent', reason: err.message, body: 'The receipts are exactly as they were.', primaryLabel: 'Back to receipts' });
    $('#ct-handoff-send').disabled = false;
  }
});

// New fee.
$('#c-new-fee').addEventListener('click', () => { $('#f-amount').value = ''; $('#f-note').value = ''; $('#fx-d-fee').showModal(); });
$('#f-save').addEventListener('click', async () => {
  const stage = $('#f-stage').value, amount = +$('#f-amount').value, program = $('#f-program').value;
  if (!amount) { toast('Enter the amount each member pays.', 'error'); return; }
  try {
    await send('POST', URLS.fees, { defense_type: stage, course: program, amount, description: $('#f-note').value.trim() || null });
    reloadWith(`${stage} Fee saved.`);
  } catch (e) {
    await outcomeDialog({
      tone: 'error',
      title: 'The fee was not saved',
      reason: e.message,
      body: 'No fee was created, and the amounts already set are untouched. Your entries are still in the form.',
      primaryLabel: 'Back to the form',
    });
  }
});
// The programs a new fee can be for.
const programs = [...new Set(S.sections.map(s => s.split('-')[0].toUpperCase()))].filter(p => ['BSIT', 'BSIS', 'BSCS', 'BLIS'].includes(p));
$('#f-program').innerHTML = (programs.length ? programs : ['BSIT', 'BSIS', 'BSCS', 'BLIS']).map(p => `<option>${p}</option>`).join('');

// A click on the backdrop closes the page's own dialogs.
root.querySelectorAll('dialog.ct-dialog').forEach(d => d.addEventListener('click', e => {
  if (e.target !== d) return;
  const b = d.getBoundingClientRect();
  if (e.clientX < b.left || e.clientX > b.right || e.clientY < b.top || e.clientY > b.bottom) d.close();
}));

wireDialogs();
render();
showFlash();
