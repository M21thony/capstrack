/*
 * A student's Financial Records: one short row for their own receipt (the
 * button opens the camera on a phone) and the team's list below it.
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

function render() {
  $('#s-fees').innerHTML = FEES.map(x => `<button role="tab" data-fee="${x.id}" aria-selected="${x.id === fee}">${esc(x.stage)} <span class="fx-num fx-muted">${peso(x.amount)}</span></button>`).join('');

  if (!fee) {
    $('#s-mine').innerHTML = '<div class="fx-mine-text"><strong>No defense fee yet</strong><span class="fx-muted">Your CaPSA has not posted a fee for your program. Pre-proposal Defense has no fee.</span></div>';
  } else {
    const f = FEES.find(x => x.id === fee), r = rec(fee, ME), s = st(fee, ME);
    const where = f.note ? ` at the ${esc(f.note)}` : '';
    const thumb = r && s !== 'sent_back' && r.img ? `<button class="fx-mine-thumb" data-full="${esc(r.img)}" aria-label="View your receipt"><img src="${esc(r.img)}" alt=""></button>` : '';
    const text = !S.can_submit && s === 'none'
      ? `<strong>Upload your ${esc(f.stage)} Fee receipt</strong><span class="fx-muted">${S.team ? 'Your team leader has to submit your pre-proposals for checking on the Duplication Checker page first.' : 'Join a team first.'}</span>`
      : {
        none: `<strong>Upload your ${esc(f.stage)} Fee receipt</strong><span class="fx-muted">Pay ${peso(f.amount)}${where}, then take a photo of the official receipt.</span>`,
        pending: `<strong>${statusHtml('pending')}</strong><span class="fx-muted">Uploaded ${esc(r?.at || '')}. Your CaPSA is checking it.</span>`,
        verified: `<strong>${statusHtml('verified')}</strong><span class="fx-muted">Checked on ${esc(r?.on || '')}. Nothing else to do.</span>`,
        sent_back: `<strong>${statusHtml('sent_back')}</strong><span class="fx-reason">${esc(r?.reason || '')}</span>`,
      }[s];
    const label = S.can_submit ? {
      none: '<svg><use href="#fx-i-camera"/></svg>Upload receipt',
      sent_back: '<svg><use href="#fx-i-camera"/></svg>Upload a new photo',
      pending: '<svg><use href="#fx-i-upload"/></svg>Replace photo',
    }[s] : null;
    $('#s-mine').innerHTML = thumb + `<div class="fx-mine-text">${text}</div>`
      + (label ? `<label class="fx-btn ${s === 'pending' ? '' : 'fx-btn-primary'} fx-file">${label}<input type="file" accept="image/*" aria-label="Choose a photo of your receipt"></label>` : '');
  }

  const members = S.members, c = { pending: 0, verified: 0, sent_back: 0, none: 0 };
  members.forEach(m => c[fee ? st(fee, m.id) : 'none']++);
  $('#s-bar').innerHTML = barHtml(c, Math.max(members.length, 1));
  $('#s-count').textContent = `${c.verified}/${members.length}`;
  $('#s-team').innerHTML = members.map(m => `<tr><td>${esc(m.name)}${m.id === ME ? '<span class="fx-leader">You</span>' : m.id === S.team?.leader ? '<span class="fx-leader">Leader</span>' : ''}</td><td>${statusHtml(fee ? st(fee, m.id) : 'none')}</td><td class="fx-col-up fx-num fx-muted">${esc((fee && rec(fee, m.id)?.at) || '—')}</td></tr>`).join('');
}

async function upload(file) {
  if (!file || !file.type.startsWith('image/')) { toast('Use a photo or scan of the receipt (JPG or PNG).'); return; }
  const was = st(fee, ME);
  const form = new FormData();
  form.append('expense_post_id', fee);
  form.append('receipt_image', file);
  toast('Uploading…');
  try {
    const res = await send('POST', URLS.upload, form);
    R[`${fee}:${ME}`] = { id: res.data.id, status: 'pending', img: res.data.receipt_image, at: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) };
    render();
    toast(was === 'pending' ? 'Photo replaced. Your CaPSA sees the new one.' : 'Uploaded. Your CaPSA will check it.');
  } catch (e) {
    toast(e.message);
  }
}

$('#s-fees').addEventListener('click', e => { const b = e.target.closest('[data-fee]'); if (b) { fee = +b.dataset.fee; render(); } });
$('#s-mine').addEventListener('change', e => { if (e.target.type === 'file') upload(e.target.files[0]); });
$('#s-mine').addEventListener('dragover', e => { if (S.can_submit && fee && st(fee, ME) !== 'verified') { e.preventDefault(); $('#s-mine').classList.add('fx-drag'); } });
$('#s-mine').addEventListener('dragleave', () => $('#s-mine').classList.remove('fx-drag'));
$('#s-mine').addEventListener('drop', e => { e.preventDefault(); $('#s-mine').classList.remove('fx-drag'); if (S.can_submit && fee && st(fee, ME) !== 'verified') upload(e.dataTransfer.files[0]); });

wireDialogs();
render();
