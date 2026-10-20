/*
 * A student's Financial Records: one short row for their own receipt (the
 * button offers the camera or the photo library on a phone) and the team's list below it.
 *
 * A receipt goes straight to the CaPSA, waiting to be checked.
 */
import { $, esc, peso, statusHtml, barHtml, send, toast, wireDialogs } from './shared.js';

const root = document.getElementById('fx-student');
const S = JSON.parse(root.dataset.state);
const URLS = JSON.parse(root.dataset.urls);
const FEES = S.fees;
const R = S.receipts;
const ME = S.me;
let fee = FEES[0]?.id ?? null;

const rec = (f, m) => R[`${f}:${m}`];
const st = (f, m) => rec(f, m)?.status || 'none';
const editable = s => ['none', 'sent_back'].includes(s);

function render() {
  $('#s-fees').innerHTML = FEES.map(x => `<button role="tab" data-fee="${x.id}" aria-selected="${x.id === fee}">${esc(x.stage)} <span class="fx-num fx-muted">${peso(x.amount)}</span></button>`).join('');

  if (!fee) {
    $('#s-mine').innerHTML = '<div class="fx-mine-text"><strong>No defense fee yet</strong><span class="fx-muted">Your CaPSA has not posted a fee for your program. Pre-proposal Defense has no fee.</span></div>';
  } else {
    const f = FEES.find(x => x.id === fee), r = rec(fee, ME), s = st(fee, ME);
    const where = f.note ? ` at the ${esc(f.note)}` : '';
    const thumb = r && s !== 'sent_back' && r.img ? `<button class="fx-mine-thumb" data-full="${esc(r.img)}" aria-label="View your receipt"><img src="${esc(r.img)}" alt=""></button>` : '';
    const or = r?.or && s !== 'sent_back' ? ` · OR ${esc(r.or)}` : '';
    const text = !S.can_submit && s === 'none'
      ? `<strong>Upload your ${esc(f.stage)} Fee receipt</strong><span class="fx-muted">${S.team ? 'Your team leader has to submit your pre-proposals for checking on the Duplication Checker page first.' : 'Join a team first.'}</span>`
      : {
        none: `<strong>Upload your ${esc(f.stage)} Fee receipt</strong><span class="fx-muted">Pay ${peso(f.amount)}${where}, then enter the OR number and take a photo of the official receipt.</span>`,
        pending: `<strong>${statusHtml('pending')}</strong><span class="fx-muted">Uploaded ${esc(r?.at || '')}${or}. Waiting for your CaPSA.</span>`,
        verified: `<strong>${statusHtml('verified')}</strong><span class="fx-muted">Checked on ${esc(r?.on || '')}${or}. Nothing else to do.</span>`,
        sent_back: `<strong>${statusHtml('sent_back')}</strong><span class="fx-reason">${esc(r?.reason || '')}</span>`,
      }[s];
    const label = S.can_submit ? {
      none: '<svg><use href="#fx-i-camera"/></svg>Upload receipt',
      sent_back: '<svg><use href="#fx-i-camera"/></svg>Replace photo',
    }[s] : null;
    $('#s-mine').innerHTML = thumb + `<div class="fx-mine-text">${text}</div>`
      + (label ? `<button type="button" class="fx-btn fx-btn-primary" data-fx-upload>${label}</button>` : '');
    // A photo whose file is gone shows a plain placeholder, not the browser's
    // broken-image box, and no longer opens an empty viewer.
    $('#s-mine').querySelectorAll('.fx-mine-thumb img').forEach(img => img.addEventListener('error', () => {
      const box = img.closest('.fx-mine-thumb');
      box.classList.add('is-missing');
      box.removeAttribute('data-full');
      box.disabled = true;
      box.title = 'Receipt photo not found';
      img.remove();
    }));
  }

  const members = S.members, c = { pending: 0, verified: 0, sent_back: 0, none: 0 };
  members.forEach(m => c[fee ? st(fee, m.id) : 'none']++);
  $('#s-bar').innerHTML = barHtml(c, Math.max(members.length, 1));
  $('#s-count').textContent = `${c.verified}/${members.length}`;
  $('#s-team').innerHTML = members.map(m => `<tr><td>${esc(m.name)}${m.id === ME ? '<span class="fx-leader">You</span>' : m.id === S.team?.leader ? '<span class="fx-leader">Leader</span>' : ''}</td><td>${statusHtml(fee ? st(fee, m.id) : 'none')}</td><td class="fx-col-up fx-num fx-muted">${esc((fee && rec(fee, m.id)?.at) || '—')}</td></tr>`).join('');
}

/* ---------- Upload: the OR number and the photo go together ---------- */
const dlg = $('#fx-d-upload');
let chosen = null;

function uploadError(text) {
  $('#u-err').textContent = text;
  $('#u-err').hidden = !text;
}

function pick(file) {
  if (!file) return;
  if (!file.type.startsWith('image/')) { uploadError('Use a photo or scan of the receipt (JPG or PNG).'); return; }
  chosen = file;
  uploadError('');
  const img = $('#u-preview');
  img.src = URL.createObjectURL(file);
  img.hidden = false;
  $('#u-pick-t').textContent = 'Choose a different photo';
}

function openUpload(file) {
  chosen = null;
  $('#u-or').value = '';
  $('#u-file').value = '';
  $('#u-preview').hidden = true;
  $('#u-pick-t').textContent = 'Take or choose a photo';
  uploadError('');
  dlg.showModal();
  if (file) pick(file);
  $('#u-or').focus();
}

async function upload() {
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
    dlg.close();
    render();
    toast(was === 'sent_back' ? 'Sent again to your CaPSA.' : 'Sent to your CaPSA.');
  } catch (e) {
    uploadError(e.message);
  } finally {
    btn.disabled = false;
  }
}

$('#u-file').addEventListener('change', e => pick(e.target.files[0]));
$('#u-send').addEventListener('click', upload);
$('#u-or').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); upload(); } });
// Its own attribute: data-open-upload is what the document upload pop-up
// looks for to open itself on page load, and finding the receipt button it
// opened over Financial Records on every visit.
$('#s-mine').addEventListener('click', e => { if (e.target.closest('[data-fx-upload]')) openUpload(); });

$('#s-fees').addEventListener('click', e => { const b = e.target.closest('[data-fee]'); if (b) { fee = +b.dataset.fee; render(); } });
$('#s-mine').addEventListener('dragover', e => { if (S.can_submit && fee && editable(st(fee, ME))) { e.preventDefault(); $('#s-mine').classList.add('fx-drag'); } });
$('#s-mine').addEventListener('dragleave', () => $('#s-mine').classList.remove('fx-drag'));
$('#s-mine').addEventListener('drop', e => { e.preventDefault(); $('#s-mine').classList.remove('fx-drag'); if (S.can_submit && fee && editable(st(fee, ME))) openUpload(e.dataTransfer.files[0]); });

wireDialogs();
render();
