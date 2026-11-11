/*
 * A student's Financial Records: their own receipt (progress, the receipt
 * they sent, or the form to send one) and the team's payments beside it.
 *
 * A receipt goes straight to the CaPSA/TSA, waiting to be checked.
 */
import { $, esc, peso, LABEL, send, toast, wireDialogs } from './shared.js';

const root = document.getElementById('fx-student');
const S = JSON.parse(root.dataset.state);
const URLS = JSON.parse(root.dataset.urls);
const FEES = S.fees;
const R = S.receipts;
const ME = S.me;
let fee = FEES[0]?.id ?? null;
let chosen = null;

const rec = (f, m) => R[`${f}:${m}`];
const st = (f, m) => rec(f, m)?.status || 'none';
const chip = s => `<span class="fx-sb-chip is-${s}"><i></i>${LABEL[s]}</span>`;

// The fee tabs sit on the page's own tab row, at its right end.
const slot = document.getElementById('tm-toprow-end');
if (slot) slot.append($('#s-fees'));

/** Upload receipt → Waiting for CaPSA/TSA → Verified; a send-back turns the middle step red. */
function stepsHtml(s) {
  const at = { none: 0, pending: 1, sent_back: 1, verified: 3 }[s];
  return ['Upload receipt', 'Waiting for CaPSA/TSA', 'Verified'].map((label, i) => {
    const back = s === 'sent_back' && i === 1;
    const state = back ? 'is-back' : i < at ? 'is-done' : i === at ? 'is-now' : '';
    return `<li class="${state}"${i === at || back ? ' aria-current="step"' : ''}><span></span>${back ? 'Sent back' : label}</li>`;
  }).join('');
}

function renderMine() {
  const form = $('#u-form');
  form.hidden = true;

  if (!fee) {
    $('#s-mine-status').innerHTML = '';
    $('#s-steps').innerHTML = '';
    $('#s-mine').innerHTML = '<p class="fx-sb-empty"><b>No defense fee yet</b>Your CaPSA/TSA has not posted a fee for your program. Pre-proposal Defense has no fee.</p>';
    return;
  }

  const f = FEES.find(x => x.id === fee), r = rec(fee, ME), s = st(fee, ME);
  $('#s-mine-status').innerHTML = chip(s);
  $('#s-steps').innerHTML = stepsHtml(s);

  if (!S.can_submit && s === 'none') {
    $('#s-mine').innerHTML = `<p class="fx-sb-empty"><b>Not open yet</b>${S.team ? 'Your team leader has to submit your pre-proposals for checking on the Duplication Checker page first.' : 'Join a team first.'}</p>`;
    return;
  }

  if (s === 'pending' || s === 'verified') {
    const thumb = r?.img
      ? `<button type="button" class="fx-sb-thumb" data-full="${esc(r.img)}" aria-label="View your receipt"><img src="${esc(r.img)}" alt=""></button>`
      : '<span class="fx-sb-thumb is-missing" title="Receipt photo not found"></span>';
    $('#s-mine').innerHTML = `<div class="fx-sb-receipt">${thumb}<dl>`
      + `<div><dt>OR number</dt><dd class="fx-num">${esc(r?.or || '—')}</dd></div>`
      + `<div><dt>Amount</dt><dd>${peso(f.amount)}</dd></div>`
      + `<div><dt>Uploaded</dt><dd>${esc(r?.at || '—')}</dd></div>`
      + (s === 'verified' ? `<div><dt>Checked on</dt><dd>${esc(r?.on || '—')}</dd></div>` : '<div><dt>Status</dt><dd>Waiting for your CaPSA/TSA</dd></div>')
      + '</dl></div>';
    // A photo whose file is gone shows a plain placeholder, not the browser's
    // broken-image box, and no longer opens an empty viewer.
    $('#s-mine').querySelectorAll('.fx-sb-thumb img').forEach(img => img.addEventListener('error', () => {
      const box = img.closest('.fx-sb-thumb');
      box.classList.add('is-missing');
      box.removeAttribute('data-full');
      box.disabled = true;
      box.title = 'Receipt photo not found';
      img.remove();
    }));
    return;
  }

  // Nothing sent, or sent back: the form, with the CaPSA/TSA's reason above it.
  $('#s-mine').innerHTML = s === 'sent_back'
    ? `<div class="fx-sb-back" role="alert"><svg><use href="#fx-i-alert"/></svg><span><b>Sent back by your CaPSA/TSA:</b> ${esc(r?.reason || 'No reason given.')} Send it again below.</span></div>`
    : '';
  const where = f.note ? ` at the ${esc(f.note)}` : '';
  $('#u-how').innerHTML = `Pay ${peso(f.amount)}${where}, then send the official receipt to your CaPSA/TSA.`;
  $('#u-or').value = s === 'sent_back' ? (r?.or || '') : '';
  $('#u-send-t').textContent = s === 'sent_back' ? 'Send again' : 'Send to CaPSA/TSA';
  clearPhoto();
  uploadError('');
  form.hidden = false;
}

function renderTeam() {
  const members = S.members;
  const n = Math.max(members.length, 1);
  const order = { verified: 0, pending: 1, sent_back: 2, none: 3 };
  const statuses = members.map(m => (fee ? st(fee, m.id) : 'none'));
  const verified = statuses.filter(s => s === 'verified').length;
  const f = FEES.find(x => x.id === fee);

  $('#s-count').innerHTML = `<b>${verified}</b> / ${members.length} verified`;
  $('#s-bar').setAttribute('aria-label', `${verified} of ${members.length} verified`);
  $('#s-bar').innerHTML = statuses.slice().sort((a, b) => order[a] - order[b]).map(s => `<span class="is-${s}"></span>`).join('') || '<span class="is-none"></span>';
  $('#s-rule').innerHTML = `<svg><use href="#fx-i-info"/></svg><span>All ${n} ${n === 1 ? 'receipt' : 'receipts'} must be verified before ${f ? esc(f.stage) : 'defense'} scheduling.</span>`;
  $('#s-team').innerHTML = members.map((m, i) => {
    const s = statuses[i];
    const tag = m.id === ME ? ' <em>· YOU</em>' : m.id === S.team?.leader ? ' <em>· LEADER</em>' : '';
    const at = fee && rec(fee, m.id)?.at;
    return `<li><span class="fx-sb-member"><b>${esc(m.name)}${tag}</b><span>${at ? 'Uploaded ' + esc(at) : 'No receipt yet'}</span></span>${chip(s)}</li>`;
  }).join('');
}

function render() {
  $('#s-fees').innerHTML = FEES.map(x => `<button type="button" role="tab" data-fee="${x.id}" aria-selected="${x.id === fee}"><b>${esc(x.stage)}</b><span class="fx-num">${peso(x.amount)}</span></button>`).join('');
  $('#s-fees').hidden = FEES.length === 0;
  renderMine();
  renderTeam();
}

/* ---------- The form: the OR number and the photo go together ---------- */

function uploadError(text) {
  $('#u-err').textContent = text;
  $('#u-err').hidden = !text;
}

function sizeOf(bytes) {
  return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
}

function clearPhoto() {
  chosen = null;
  $('#u-file').value = '';
  $('#u-preview').hidden = true;
  $('#u-pick-icon').hidden = false;
  $('#u-replace').hidden = true;
  $('#u-pick').classList.remove('is-picked');
  $('#u-pick-t').textContent = 'Take or choose a photo';
  $('#u-pick-s').textContent = 'JPG or PNG, up to 10 MB';
  refreshSend();
}

function pick(file) {
  if (!file) return;
  if (!file.type.startsWith('image/')) { uploadError('Use a photo or scan of the receipt (JPG or PNG).'); return; }
  chosen = file;
  uploadError('');
  const img = $('#u-preview');
  img.src = URL.createObjectURL(file);
  img.hidden = false;
  $('#u-pick-icon').hidden = true;
  $('#u-replace').hidden = false;
  $('#u-pick').classList.add('is-picked');
  $('#u-pick-t').textContent = file.name;
  $('#u-pick-s').textContent = sizeOf(file.size);
  refreshSend();
}

/** Send waits for the photo; the OR number is asked for when it is pressed. */
function refreshSend() {
  $('#u-send').disabled = !chosen;
  $('#u-hint').hidden = !!chosen;
}

async function upload(e) {
  e.preventDefault();
  const number = $('#u-or').value.trim();
  if (!number) { uploadError('Enter the OR number printed on your official receipt.'); $('#u-or').focus(); return; }
  if (!chosen) { uploadError('Add a photo of the receipt.'); return; }
  const was = st(fee, ME);
  const form = new FormData();
  form.append('expense_post_id', fee);
  form.append('receipt_number', number);
  form.append('receipt_image', chosen);
  const btn = $('#u-send');
  btn.disabled = true;
  try {
    const res = await send('POST', URLS.upload, form);
    R[`${fee}:${ME}`] = { id: res.data.id, status: 'pending', img: res.data.receipt_image, or: res.data.receipt_number, at: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) };
    render();
    toast(was === 'sent_back' ? 'Sent again to your CaPSA/TSA.' : 'Sent to your CaPSA/TSA.');
  } catch (err) {
    uploadError(err.message);
    btn.disabled = false;
  }
}

$('#u-file').addEventListener('change', e => pick(e.target.files[0]));
$('#u-form').addEventListener('submit', upload);
$('#u-pick').addEventListener('dragover', e => { e.preventDefault(); $('#u-pick').classList.add('fx-drag'); });
$('#u-pick').addEventListener('dragleave', () => $('#u-pick').classList.remove('fx-drag'));
$('#u-pick').addEventListener('drop', e => { e.preventDefault(); $('#u-pick').classList.remove('fx-drag'); pick(e.dataTransfer.files[0]); });
$('#s-fees').addEventListener('click', e => { const b = e.target.closest('[data-fee]'); if (b) { fee = +b.dataset.fee; render(); } });

wireDialogs();
render();
