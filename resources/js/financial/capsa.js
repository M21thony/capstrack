/*
 * The CaPSA's Financial Records: fees as tabs, every receipt in her sections
 * grouped by team, and the selected receipt shown large with Verify / Send back.
 * A decision moves straight to the next receipt, so a batch needs no mouse.
 */
import { $, esc, peso, statusHtml, barHtml, send, toast, reloadWith, showFlash, wireDialogs } from './shared.js';

const root = document.getElementById('fx-capsa');
const S = JSON.parse(root.dataset.state);
const URLS = JSON.parse(root.dataset.urls);
const FEES = S.fees;
const TEAMS = S.teams;
const R = S.receipts;
const NAME = {}, TEAM_OF = {};
TEAMS.forEach(t => t.members.forEach(m => { NAME[m.id] = m.name; TEAM_OF[m.id] = t; }));

let fee = FEES[0]?.id ?? null, show = 'pending', selected = null, sendingBack = false;
const picked = new Set();

const rec = (f, m) => R[`${f}:${m}`];
const st = (f, m) => rec(f, m)?.status || 'none';
const everyone = () => TEAMS.flatMap(t => t.members.map(m => m.id));
const tally = (f, ids) => { const c = { pending: 0, verified: 0, sent_back: 0, none: 0 }; ids.forEach(m => c[st(f, m)]++); return c; };

function visible() {
  const team = +$('#c-team').value, q = $('#c-search').value.toLowerCase();
  return TEAMS.filter(t => !team || t.id === team)
    .map(t => ({ t, ids: t.members.map(m => m.id).filter(m => (show === 'all' || st(fee, m) === show) && (!q || NAME[m].toLowerCase().includes(q))) }))
    .filter(g => g.ids.length);
}

function render() {
  if (!fee) {
    $('#c-fees').innerHTML = '';
    $('#c-body').innerHTML = '<div class="fx-empty">No defense fee yet. Create one with <b>New fee</b>: Title, Pre Oral or Final Oral Defense. Pre-proposal Defense has no fee.</div>';
    return;
  }
  const f = FEES.find(x => x.id === fee), all = everyone();
  $('#c-fees').innerHTML = FEES.map(x => {
    const w = tally(x.id, all).pending;
    return `<button role="tab" data-fee="${x.id}" aria-selected="${x.id === fee}">${esc(x.stage)}${FEES.filter(y => y.stage === x.stage).length > 1 ? ` · ${esc(x.program)}` : ''}<span class="fx-count ${w ? 'fx-hot' : ''}">${w}</span></button>`;
  }).join('');
  const c = tally(fee, all), n = Math.max(all.length, 1);
  $('#c-fee-line').innerHTML = `<strong>${esc(f.stage)} Fee</strong> <span class="fx-num fx-muted">${peso(f.amount)} / member</span>`;
  $('#c-bar').innerHTML = barHtml(c, n);
  $('#c-legend').innerHTML = `<span><b>${c.verified}</b> of <b>${all.length}</b> verified</span><span><b>${c.pending}</b> waiting</span><span><b>${c.sent_back}</b> sent back</span><span><b>${c.none}</b> not uploaded</span>`;

  $('#c-chips').innerHTML = [['pending', 'Waiting'], ['sent_back', 'Sent back'], ['none', 'Not uploaded'], ['verified', 'Verified'], ['all', 'All']]
    .map(([k, l]) => `<button data-show="${k}" aria-pressed="${k === show}">${l}<span class="fx-n">${k === 'all' ? all.length : c[k]}</span></button>`).join('');

  const groups = visible(), flat = groups.flatMap(g => g.ids);
  if (!flat.includes(selected)) { selected = flat[0] ?? null; sendingBack = false; }
  [...picked].forEach(m => { if (!flat.includes(m) || st(fee, m) !== 'pending') picked.delete(m); });

  $('#c-rows').innerHTML = groups.map(({ t, ids }) => {
    const members = t.members.map(m => m.id), tc = tally(fee, members);
    return `<tr class="fx-group"><td colspan="5"><strong>${esc(t.name)}</strong><span class="fx-bar">${barHtml(tc, members.length)}</span><span class="fx-num">${tc.verified}/${members.length}</span> verified</td></tr>`
      + ids.map(m => {
        const r = rec(fee, m), s = st(fee, m);
        return `<tr class="fx-row" data-id="${m}" aria-selected="${m === selected}">
          <td>${s === 'pending' ? `<input type="checkbox" data-pick="${m}" ${picked.has(m) ? 'checked' : ''} aria-label="Select ${esc(NAME[m])}">` : ''}</td>
          <td>${esc(NAME[m])}${m === t.leader ? '<span class="fx-leader">Leader</span>' : ''}</td>
          <td class="fx-col-team fx-muted">${esc(t.section)}</td>
          <td class="fx-col-up fx-num fx-muted">${esc(r?.at || '—')}</td>
          <td>${statusHtml(s)}</td></tr>`;
      }).join('');
  }).join('') || `<tr><td colspan="5" class="fx-empty">${show === 'pending' ? 'Nothing waiting. You are caught up.' : 'No one matches.'}</td></tr>`;

  const waitingShown = flat.filter(m => st(fee, m) === 'pending');
  $('#c-all').disabled = !waitingShown.length;
  $('#c-all').checked = waitingShown.length > 0 && waitingShown.every(m => picked.has(m));
  $('#c-bulk').hidden = picked.size === 0;
  $('#c-bulk-n').textContent = `${picked.size} selected`;
  renderInspector();
}

const QUICK = ['The photo is blurry.', 'The amount is wrong.', "It isn't the official receipt.", 'The name is a different person.'];
function renderInspector() {
  const el = $('#c-insp');
  if (!selected) { el.innerHTML = '<div class="fx-insp-none">Pick a receipt from the list.</div>'; return; }
  const m = selected, r = rec(fee, m), s = st(fee, m), t = TEAM_OF[m], f = FEES.find(x => x.id === fee);
  const head = `<div class="fx-insp-head"><h2>${esc(NAME[m])}</h2><div class="fx-muted" style="font-size:13px">Team ${esc(t.name)} · ${esc(t.section)}</div></div>`;
  if (!r) { el.innerHTML = head + `<div class="fx-insp-none">No receipt uploaded for the ${esc(f.stage)} Fee yet.</div>`; return; }
  const decide = s !== 'pending' ? '' : sendingBack
    ? `<div class="fx-decide"><label class="fx-lbl" for="c-reason" style="margin:0">Why is it being sent back?</label>
        <textarea class="fx-field" id="c-reason" placeholder="${esc(NAME[m].split(' ')[0])} sees this."></textarea>
        <div class="fx-quick">${QUICK.map(q => `<button type="button" data-quick>${q}</button>`).join('')}</div>
        <div class="fx-pair"><button class="fx-btn" data-cancel>Cancel</button><button class="fx-btn fx-btn-bad" data-confirm-back><svg><use href="#fx-i-undo"/></svg>Send back</button></div></div>`
    : `<div class="fx-decide"><div class="fx-pair"><button class="fx-btn fx-btn-bad" data-back><svg><use href="#fx-i-undo"/></svg>Send back</button><button class="fx-btn fx-btn-ok" data-verify><svg><use href="#fx-i-check"/></svg>Verify</button></div>
        <div class="fx-keys"><kbd>V</kbd> verify · <kbd>B</kbd> send back · <kbd>J</kbd><kbd>K</kbd> next / previous</div></div>`;
  el.innerHTML = head
    + `<button class="fx-insp-img" data-full="${esc(r.img)}" aria-label="Open full size"><img src="${esc(r.img)}" alt="${esc(NAME[m])}'s receipt"></button>
       <dl class="fx-meta"><dt>Status</dt><dd>${statusHtml(s)}</dd><dt>Amount due</dt><dd class="fx-num">${peso(f.amount)}</dd><dt>Uploaded</dt><dd class="fx-num">${esc(r.at || '—')}</dd>
       ${s === 'verified' ? `<dt>Verified</dt><dd class="fx-num">${esc(r.on || '')}</dd>` : ''}${s === 'sent_back' ? `<dt>Reason</dt><dd style="color:var(--fx-bad)">${esc(r.reason)}</dd>` : ''}</dl>`
    + decide;
  if (sendingBack) $('#c-reason').focus();
}

const today = () => new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

// Save, then move straight to the next receipt in the list.
async function decide(ids, status, reason = '') {
  const before = visible().flatMap(g => g.ids), at = before.indexOf(selected);
  try {
    if (ids.length > 1) {
      await send('PATCH', URLS.records, { ids: ids.map(m => rec(fee, m).id), status: 'approved' });
    } else {
      await send('PATCH', URLS.record.replace('__ID__', rec(fee, ids[0]).id), status === 'verified' ? { status: 'approved' } : { status: 'rejected', rejection_reason: reason });
    }
  } catch (e) {
    toast(e.message);
    return false;
  }
  ids.forEach(m => Object.assign(R[`${fee}:${m}`], status === 'verified' ? { status, on: today() } : { status, reason }));
  ids.forEach(m => picked.delete(m));
  sendingBack = false;
  const after = visible().flatMap(g => g.ids);
  selected = after[Math.min(Math.max(at, 0), after.length - 1)] ?? null;
  render();
  document.querySelector(`tr.fx-row[data-id="${selected}"]`)?.scrollIntoView({ block: 'nearest' });
  return true;
}

function move(step) {
  const flat = visible().flatMap(g => g.ids), at = flat.indexOf(selected) + step;
  if (at < 0 || at >= flat.length) return;
  selected = flat[at];
  sendingBack = false;
  render();
  document.querySelector(`tr.fx-row[data-id="${selected}"]`)?.scrollIntoView({ block: 'nearest' });
}

/* ---------------- Events ---------------- */
$('#c-fees').addEventListener('click', e => { const b = e.target.closest('[data-fee]'); if (b) { fee = +b.dataset.fee; picked.clear(); render(); } });
$('#c-chips').addEventListener('click', e => { const b = e.target.closest('[data-show]'); if (b) { show = b.dataset.show; picked.clear(); render(); } });
$('#c-team').addEventListener('change', render);
$('#c-search').addEventListener('input', render);
$('#c-rows').addEventListener('click', e => {
  if (e.target.dataset.pick) { const m = +e.target.dataset.pick; e.target.checked ? picked.add(m) : picked.delete(m); render(); return; }
  const row = e.target.closest('tr.fx-row');
  if (row) { selected = +row.dataset.id; sendingBack = false; render(); }
});
$('#c-all').addEventListener('change', e => { visible().flatMap(g => g.ids).filter(m => st(fee, m) === 'pending').forEach(m => e.target.checked ? picked.add(m) : picked.delete(m)); render(); });
$('#c-bulk-clear').addEventListener('click', () => { picked.clear(); render(); });
$('#c-bulk-verify').addEventListener('click', async () => {
  const ids = [...picked];
  if (!confirm(`Verify ${ids.length} ${ids.length === 1 ? 'receipt' : 'receipts'} without opening each one?`)) return;
  if (await decide(ids, 'verified')) toast(`Verified ${ids.length} ${ids.length === 1 ? 'receipt' : 'receipts'}.`);
});
$('#c-insp').addEventListener('click', async e => {
  const b = e.target.closest('button');
  if (!b) return;
  if (b.hasAttribute('data-verify')) { const m = selected; if (await decide([m], 'verified')) toast(`Verified ${NAME[m]}.`); }
  if (b.hasAttribute('data-back')) { sendingBack = true; renderInspector(); }
  if (b.hasAttribute('data-cancel')) { sendingBack = false; renderInspector(); }
  if (b.hasAttribute('data-quick')) { $('#c-reason').value = b.textContent; }
  if (b.hasAttribute('data-confirm-back')) {
    const reason = $('#c-reason').value.trim();
    if (!reason) { toast('Say why, so the student knows what to fix.'); $('#c-reason').focus(); return; }
    const m = selected;
    if (await decide([m], 'sent_back', reason)) toast(`Sent back to ${NAME[m]}.`);
  }
});
document.addEventListener('keydown', async e => {
  if (e.target.closest('input,textarea,select') || document.querySelector('dialog[open]') || !fee) return;
  const k = e.key.toLowerCase();
  if (k === 'j' || e.key === 'ArrowDown') { e.preventDefault(); move(1); }
  if (k === 'k' || e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
  if (k === 'v' && selected && st(fee, selected) === 'pending') { const m = selected; if (await decide([m], 'verified')) toast(`Verified ${NAME[m]}.`); }
  if (k === 'b' && selected && st(fee, selected) === 'pending') { e.preventDefault(); sendingBack = true; renderInspector(); }
});
$('#c-new-fee').addEventListener('click', () => { $('#f-amount').value = ''; $('#f-note').value = ''; $('#fx-d-fee').showModal(); });
$('#f-save').addEventListener('click', async () => {
  const stage = $('#f-stage').value, amount = +$('#f-amount').value, program = $('#f-program').value;
  if (!amount) { toast('Enter the amount each member pays.'); return; }
  try {
    await send('POST', URLS.fees, { defense_type: stage, course: program, amount, description: $('#f-note').value.trim() || null });
    reloadWith(`${stage} Fee saved.`);
  } catch (e) {
    toast(e.message);
  }
});

// The team filter and the programs a new fee can be for.
$('#c-team').insertAdjacentHTML('beforeend', TEAMS.map(t => `<option value="${t.id}">${esc(t.name)}</option>`).join(''));
const programs = [...new Set(S.sections.map(s => s.split('-')[0].toUpperCase()))].filter(p => ['BSIT', 'BSIS', 'BSCS', 'BLIS'].includes(p));
$('#f-program').innerHTML = (programs.length ? programs : ['BSIT', 'BSIS', 'BSCS', 'BLIS']).map(p => `<option>${p}</option>`).join('');

wireDialogs();
render();
showFlash();
