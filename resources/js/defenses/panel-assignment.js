/*
 * Panel Assignment: the CaPSA's teams for one stage, their experts and panel,
 * and the window that assigns a panel to one or many teams at once.
 * Drawn from the JSON the page carries; a save reloads with the new state.
 */
import { $, esc, initials, api, toast, reloadWith, showFlash, wireDialogs } from './shared.js';

const root = document.getElementById('dx-panel');
const S = JSON.parse(root.dataset.state);
const URLS = JSON.parse(root.dataset.urls);
const STAGE = S.stage;
const TEAMS = S.teams;
const EXPERTS = S.experts;
const E = id => EXPERTS.find(e => e.id === id);
const T = id => TEAMS.find(t => t.id === id);
const hasFee = () => STAGE !== 'Pre-proposal Defense';

const A = { teams: new Set(), chair: null, m1: null, m2: null };
const SEATS = [['chair', 'Panel Chair', "Master's degree or higher"], ['m1', 'Member 1', 'Any degree'], ['m2', 'Member 2', 'Any degree']];
const expertName = id => E(id)?.name || S.names[id] || 'A panelist';

const expertsBlocker = t => t.blockers.find(b => b.key === 'experts');
const canTakePanel = t => !expertsBlocker(t);
function statusOf(t) {
  if (!canTakePanel(t)) return 'notready';
  if (t.passed) return 'passed';
  if (!t.panel) return 'unassigned';
  return t.schedule ? 'scheduled' : 'assigned';
}
const STATUS = {
  notready: ['Missing experts', 'dx-pill-notready'],
  unassigned: ['Unassigned', 'dx-pill-unassigned'],
  assigned: ['Assigned', 'dx-pill-assigned'],
  scheduled: ['Scheduled', 'dx-pill-scheduled'],
  passed: ['Passed', 'dx-pill-scheduled'],
};

// What the defense schedule maker will say about this team. Read-only here.
function scheduleNote(t) {
  if (t.schedule || t.passed || !canTakePanel(t)) return '';
  const b = t.blockers.filter(x => x.key !== 'panel').map(x => x.text);
  if (b.length) return `<span class="dx-sched-note dx-no">Can't be scheduled: ${esc(b.join(', ').toLowerCase())}</span>`;
  return t.panel ? '<span class="dx-sched-note dx-ok">Ready for scheduling</span>' : '<span class="dx-sched-note dx-wait">Scheduling waits for a panel</span>';
}

// One line per expert, labelled, so three names share a single column.
function expertLine(short, person, role) {
  const name = person ? (typeof person === 'string' ? person : person.name) : null;
  return `<div><b title="${role}">${short}</b>${name ? `<span>${esc(name)}</span>` : `<span class="dx-missing">No ${role}</span>`}</div>`;
}

/* ---------------- The table ---------------- */
function renderRows() {
  const q = $('#f-search').value.toLowerCase(), sec = $('#f-section').value, st = $('#f-status').value, fee = $('#f-paid').value;
  const shown = TEAMS.filter(t => (!q || `${t.name} ${t.title || ''}`.toLowerCase().includes(q)) && (!sec || t.section === sec)
    && (!st || statusOf(t) === st) && (!fee || !hasFee() || (fee === 'paid') === t.paid));

  $('#rows').innerHTML = shown.map(t => {
    const s = statusOf(t);
    const seat = (short, id, role) => `<div><b title="${role}">${short}</b>${id ? `<span>${esc(expertName(id))}</span>` : '<span class="dx-muted">Empty</span>'}</div>`;
    const panel = t.panel
      ? `<div class="dx-experts">${seat('PC', t.panel.chair, 'Panel Chair')}${seat('M1', t.panel.m1, 'Member 1')}${seat('M2', t.panel.m2, 'Member 2')}</div>`
      : '<span class="dx-none">Not assigned</span>';
    const need = expertsBlocker(t)?.text;
    const main = s === 'notready'
      ? `<button class="dx-btn dx-btn-sm" disabled title="${esc(need)} first">Assign Panel</button>`
      : t.panel ? `<button class="dx-btn dx-btn-sm dx-btn-dark" data-assign="${t.id}">Change panel</button>`
      : `<button class="dx-btn dx-btn-sm dx-btn-gold" data-assign="${t.id}">Assign Panel</button>`;
    const more = t.panel
      ? `<button class="dx-icon-btn" data-menu="${t.id}" aria-label="More actions for ${esc(t.name)}" aria-haspopup="menu"><svg><use href="#dx-i-more"/></svg></button>`
      : '<span class="dx-icon-btn" aria-hidden="true"></span>';
    const label = s === 'passed' ? (t.outcome || 'Passed') : STATUS[s][0];
    return `<tr class="${A.teams.has(t.id) ? 'dx-is-picked' : ''}">
      <td><input type="checkbox" data-pick="${t.id}" ${s === 'notready' ? `disabled title="${esc(need)} first"` : ''} ${A.teams.has(t.id) ? 'checked' : ''} aria-label="Select ${esc(t.name)}"></td>
      <td><div class="dx-team"><span class="dx-tbadge">${esc(initials(t.name))}</span><span><span class="dx-strong">${esc(t.name)}</span><span class="dx-muted" style="display:block">${esc(t.section)} · ${t.members} ${t.members === 1 ? 'member' : 'members'}</span></span></div></td>
      <td class="dx-title-cell"><span class="dx-clamp" title="${esc(t.title || '')}">${esc(t.title || '—')}</span></td>
      <td><div class="dx-experts">${expertLine('Adv', t.adviser, 'adviser')}${expertLine('Con', t.consultant, 'consultant')}${expertLine('Gra', t.grammarian, 'grammarian')}</div></td>
      <td>${panel}</td>
      <td><span class="dx-pill ${STATUS[s][1]}">${esc(label)}</span>${scheduleNote(t)}</td>
      <td><div class="dx-acts">${main}${more}</div></td>
    </tr>`;
  }).join('') || '<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--dx-ink3)">No team matches these filters.</td></tr>';

  $('#showing').textContent = `Showing ${shown.length} of ${TEAMS.length} ${TEAMS.length === 1 ? 'team' : 'teams'}`;
  const ready = TEAMS.filter(canTakePanel);
  $('#pick-all').checked = ready.length > 0 && ready.every(t => A.teams.has(t.id));
  $('#f-paid').disabled = !hasFee();
  renderSelection();
}

function renderSelection() {
  const n = A.teams.size;
  $('#selbar').hidden = n === 0;
  $('#sel-count').textContent = `${n} ${n === 1 ? 'team' : 'teams'} selected`;
  $('#sel-chips').innerHTML = [...A.teams].map(id => `<span class="dx-chip">${esc(T(id).name)}<button class="dx-icon-btn" data-unpick="${id}" aria-label="Remove ${esc(T(id).name)}"><svg><use href="#dx-i-x"/></svg></button></span>`).join('');
}

/* ---------------- The assign window ---------------- */
// The selected teams' own advisers and consultants never appear in the panel list.
const ownExperts = () => new Set([...A.teams].flatMap(id => [T(id).adviser?.id, T(id).consultant?.id]).filter(Boolean));
const suggested = e => [...A.teams].some(id => T(id).suggested.includes(e.id));

function drawAssign() {
  $('#a-sub').textContent = `${STAGE} · the defense date is set on the Defense Scheduling page.`;
  $('#a-prop').hidden = STAGE !== 'Pre-proposal Defense';

  const tq = $('#a-team-search').value.toLowerCase();
  $('#a-teams').innerHTML = TEAMS.filter(t => !tq || `${t.name} ${t.title || ''}`.toLowerCase().includes(tq)).map(t => {
    const off = !canTakePanel(t);
    const note = off ? `<span class="dx-warn">${esc(expertsBlocker(t).text)}</span>` : t.panel ? '<span class="dx-muted">Has a panel, this replaces it</span>' : '';
    return `<label class="dx-pick ${off ? 'dx-off' : ''}"><input type="checkbox" data-apick="${t.id}" ${A.teams.has(t.id) ? 'checked' : ''} ${off ? 'disabled' : ''}><span class="dx-tbadge">${esc(initials(t.name))}</span><span class="dx-grow"><span class="dx-strong">${esc(t.name)}</span> <span class="dx-muted">${esc(t.section)}</span><span class="dx-muted" style="display:block">${esc(t.title || '')}</span>${note}</span></label>`;
  }).join('');
  $('#a-team-count').textContent = A.teams.size;

  const own = ownExperts();
  [...own].forEach(id => SEATS.forEach(([k]) => { if (A[k] === id) A[k] = null; }));
  $('#a-hidden').textContent = own.size ? `${own.size} hidden: advise or consult these teams` : '';

  const eq = $('#a-expert-search').value.toLowerCase();
  $('#a-groups').innerHTML = SEATS.map(([k, label, rule]) => {
    const takenElsewhere = new Set(SEATS.filter(s => s[0] !== k).map(s => A[s[0]]).filter(Boolean));
    const rows = EXPERTS
      .filter(e => !own.has(e.id) && !takenElsewhere.has(e.id))
      .filter(e => !eq || `${e.name} ${e.tags.join(' ')}`.toLowerCase().includes(eq))
      .sort((a, b) => (k === 'chair' ? b.chair - a.chair : 0) || suggested(b) - suggested(a))
      .map(e => {
        const noChair = k === 'chair' && !e.chair;
        return `<label class="dx-pick ${noChair ? 'dx-off' : ''}"><input type="radio" name="a-${k}" data-seat="${k}" value="${e.id}" ${A[k] === e.id ? 'checked' : ''} ${noChair ? 'disabled' : ''}><span class="dx-avatar">${esc(initials(e.name))}</span><span class="dx-grow"><span class="dx-strong">${suggested(e) && !noChair ? '<span class="dx-star" title="Matches the teams\' project tags">★</span> ' : ''}${esc(e.name)}</span> <span class="dx-deg">${esc(e.degree)}</span><span class="dx-muted" style="display:block">${noChair ? '<span class="dx-warn">Can\'t chair, needs a Master\'s or higher</span>' : esc(e.tags.join(', '))}</span></span></label>`;
      }).join('') || '<div class="dx-empty-note" style="padding:10px 12px">No expert left for this seat.</div>';
    const picked = A[k] && E(A[k]);
    return `<div class="dx-group"><div class="dx-group-head"><span class="dx-dot ${picked ? 'dx-ok' : ''}"></span>${label} <span class="dx-muted">(${rule})</span><span class="dx-grow"></span>${picked ? `<span class="dx-muted">${esc(picked.name)}</span><button class="dx-icon-btn" data-unseat="${k}" aria-label="Clear ${label}"><svg><use href="#dx-i-x"/></svg></button>` : '<span class="dx-muted">Not selected</span>'}</div><div class="dx-group-list">${rows}</div></div>`;
  }).join('');

  const n = A.teams.size;
  $('#a-summary').textContent = !n ? 'Tick at least one team.' : !A.chair ? 'A Panel Chair is required.' : `Saves this panel for ${n} ${n === 1 ? 'team' : 'teams'}.`;
  $('#a-save').disabled = !n || !A.chair;
  renderRows();
}

function openAssign() { drawAssign(); $('#d-assign').showModal(); }

function startFor(id) {
  const t = T(id);
  A.teams = new Set([id]);
  Object.assign(A, t.panel ? { chair: t.panel.chair, m1: t.panel.m1, m2: t.panel.m2 } : { chair: null, m1: null, m2: null });
  openAssign();
}

async function saveAssign() {
  const btn = $('#a-save');
  btn.disabled = true;
  try {
    const res = await api('POST', URLS.bulk, {
      team_ids: [...A.teams], defense_type: STAGE,
      chair_expert_id: A.chair, member1_expert_id: A.m1, member2_expert_id: A.m2,
    });
    const done = res.data.assigned.length, skipped = res.data.skipped;
    reloadWith(`Panel saved for ${done} ${done === 1 ? 'team' : 'teams'}.`
      + (skipped.length ? ` Skipped ${skipped.map(s => `${s.team_name} (${s.reason})`).join('; ')}` : ''));
  } catch (e) {
    toast(e.message);
    btn.disabled = false;
  }
}

/* ---------------- Row menu ---------------- */
let menuFor = null;
function openMenu(btn, id) {
  const m = $('#row-menu');
  menuFor = id;
  m.showPopover();
  const r = btn.getBoundingClientRect();
  const below = r.bottom + 4 + m.offsetHeight <= innerHeight;
  m.style.top = (below ? r.bottom + 4 : r.top - 4 - m.offsetHeight) + 'px';
  m.style.left = Math.max(8, r.right - m.offsetWidth) + 'px';
  m.querySelector('button').focus();
}

async function removePanel() {
  $('#row-menu').hidePopover();
  const t = T(menuFor);
  if (!confirm(`Remove Team ${t.name}'s ${STAGE} panel? A defense already scheduled for it is cancelled too.`)) return;
  try {
    await api('POST', URLS.store.replace('__ID__', t.id), { defense_type: STAGE, chair_expert_id: null });
    reloadWith(`Removed Team ${t.name}'s panel.`);
  } catch (e) {
    toast(e.message);
  }
}

/* ---------------- Events ---------------- */
$('#f-stage').addEventListener('change', e => { window.location.href = `${URLS.self}?defense_type=${encodeURIComponent(e.target.value)}`; });
['#f-search', '#f-section', '#f-status', '#f-paid'].forEach(s => $(s).addEventListener('input', renderRows));
$('#rows').addEventListener('change', e => {
  const id = +e.target.dataset.pick;
  if (!id) return;
  e.target.checked ? A.teams.add(id) : A.teams.delete(id);
  renderRows();
});
$('#pick-all').addEventListener('change', e => { TEAMS.filter(canTakePanel).forEach(t => e.target.checked ? A.teams.add(t.id) : A.teams.delete(t.id)); renderRows(); });
$('#rows').addEventListener('click', e => {
  const b = e.target.closest('button');
  if (!b) return;
  if (b.dataset.assign) startFor(+b.dataset.assign);
  if (b.dataset.menu) openMenu(b, +b.dataset.menu);
});
$('#menu-remove').addEventListener('click', removePanel);
$('#sel-chips').addEventListener('click', e => { const b = e.target.closest('[data-unpick]'); if (b) { A.teams.delete(+b.dataset.unpick); renderRows(); } });
$('#sel-clear').addEventListener('click', () => { A.teams.clear(); renderRows(); });
$('#sel-assign').addEventListener('click', openAssign);
$('#open-assign').addEventListener('click', openAssign);
$('#a-team-search').addEventListener('input', drawAssign);
$('#a-expert-search').addEventListener('input', drawAssign);
$('#a-teams').addEventListener('change', e => { const id = +e.target.dataset.apick; if (!id) return; e.target.checked ? A.teams.add(id) : A.teams.delete(id); drawAssign(); });
$('#a-groups').addEventListener('change', e => { if (e.target.dataset.seat) { A[e.target.dataset.seat] = +e.target.value; drawAssign(); } });
$('#a-groups').addEventListener('click', e => { const b = e.target.closest('[data-unseat]'); if (b) { e.preventDefault(); A[b.dataset.unseat] = null; drawAssign(); } });
$('#a-save').addEventListener('click', saveAssign);

// The sections filter lists only the sections these teams are in.
$('#f-section').insertAdjacentHTML('beforeend', [...new Set(TEAMS.map(t => t.section))].sort().map(s => `<option>${esc(s)}</option>`).join(''));
$('#f-stage').value = STAGE;

wireDialogs();
renderRows();
showFlash();
