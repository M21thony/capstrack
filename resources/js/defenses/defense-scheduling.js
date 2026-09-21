/*
 * Defense Scheduling: which of the CaPSA's teams can be scheduled for a stage,
 * which cannot and why, what is on the calendar (other sections greyed out),
 * the shared defense window, Generate schedule, and moving one defense.
 */
import { $, esc, initials, fmtDay, fmtShort, slotRange, slotLabel, addMinutes, durationText, slotsFor, api, toast, reloadWith, showFlash, clashWith, wireDialogs } from './shared.js';

const root = document.getElementById('dx-sched');
const S = JSON.parse(root.dataset.state);
const URLS = JSON.parse(root.dataset.urls);
const STAGE = S.stage;
const TEAMS = S.teams;
const EXPERTS = S.experts;
const WINDOWS = S.windows;
const OTHER = S.other.map(o => ({ ...o, other: true, who: o.section }));
const E = id => EXPERTS.find(e => e.id === id);
const T = id => TEAMS.find(t => t.id === id);
const nameOf = id => E(id)?.name || S.names[id] || 'A panelist';
const hasFee = () => STAGE !== 'Pre-proposal Defense';
const SEATS = [['chair', 'Panel Chair'], ['m1', 'Member 1'], ['m2', 'Member 2']];

const readyToSchedule = t => !t.schedule && !t.passed && t.blockers.length === 0;
const isBlocked = t => !t.schedule && !t.passed && t.blockers.length > 0;
const panelOf = t => (t.panel ? [t.panel.chair, t.panel.m1, t.panel.m2].filter(Boolean) : []);
const panelNames = t => SEATS.map(([k]) => (t.panel?.[k] ? `${esc(nameOf(t.panel[k]))}${k === 'chair' ? ' <span class="dx-muted">(Chair)</span>' : ''}` : '')).filter(Boolean).join('<br>');
const teamCell = (t, extra = '') => `<div class="dx-team"><span class="dx-tbadge dx-sm">${esc(initials(t.name))}</span><span><span class="dx-strong">${esc(t.name)}</span><span class="dx-muted" style="display:block">${esc(t.section)}${extra}</span></span></div>`;

// Every booking a slot must not clash with: this CaPSA's scheduled teams and every other section's.
const allBookings = except => [
  ...TEAMS.filter(t => t.schedule && t !== except).map(t => ({ panel: panelOf(t), ...t.schedule, who: `Team ${t.name}` })),
  ...OTHER,
];

const windowLabel = w => (w?.start && w?.end ? `${fmtShort(w.start)} – ${fmtShort(w.end)}, ${w.end.slice(0, 4)} · ${durationText(w.duration)} each` : 'not set');

/* ---------------- The board ---------------- */
let schedTab = 'ready';
function render() {
  $('#window-label').textContent = windowLabel(WINDOWS[STAGE]);
  $('#my-sections').textContent = S.sections.length ? S.sections.join(', ') : 'All sections';
  const q = $('#s-search').value.toLowerCase();
  const match = t => !q || t.name.toLowerCase().includes(q);
  const ready = TEAMS.filter(readyToSchedule);
  const blocked = TEAMS.filter(isBlocked);
  const scheduled = TEAMS.filter(t => t.schedule);
  $('#n-ready').textContent = ready.length;
  $('#n-blocked').textContent = blocked.length;
  $('#n-scheduled').textContent = scheduled.length;
  document.querySelectorAll('[data-tab]').forEach(b => b.setAttribute('aria-selected', String(b.dataset.tab === schedTab)));
  $('#reason-filter').hidden = schedTab !== 'blocked';
  $('#others-toggle').hidden = schedTab !== 'scheduled';

  const table = (cols, heads, rows, empty) => `<colgroup>${cols.map(c => `<col style="width:${c}">`).join('')}</colgroup>
    <thead><tr>${heads.map(h => `<th${h === 'Actions' ? ' style="text-align:right"' : ''}>${h}</th>`).join('')}</tr></thead>
    <tbody>${rows.join('') || `<tr><td colspan="${cols.length}" style="text-align:center;padding:40px;color:var(--dx-ink3)">${empty}</td></tr>`}</tbody>`;

  if (schedTab === 'ready') {
    $('#tab-hint').textContent = hasFee()
      ? 'Panel assigned, requirements done and fees verified. Generate schedule gives each one a date.'
      : 'Panel assigned and requirements done. Pre-proposal Defense has no fee.';
    const passed = ['Experts', S.stages.indexOf(STAGE) > 0 && 'Previous stage passed', 'Requirements done', 'Panel assigned', hasFee() && 'Fees verified'].filter(Boolean);
    $('#sched-table').innerHTML = table(['26%', '38%', '36%'], ['Team', 'Panel', 'Checks'],
      ready.filter(match).map(t => `<tr><td>${teamCell(t, t.attempt > 1 ? ` · Redefense ${t.attempt}` : '')}</td><td class="dx-panel-names">${panelNames(t)}</td><td><div class="dx-checks">${passed.map(p => `<span class="dx-check">✓ ${p}</span>`).join('')}</div></td></tr>`),
      "No team can be scheduled yet. The Can't be scheduled tab says what each one needs.");
  } else if (schedTab === 'blocked') {
    $('#tab-hint').textContent = 'These teams are left out of Generate schedule until they finish what is listed.';
    const key = $('#reason-filter').value;
    $('#sched-table').innerHTML = table(['26%', '34%', '40%'], ['Team', 'Panel', 'Still needs'],
      blocked.filter(match).filter(t => !key || t.blockers.some(b => b.key === key))
        .map(t => `<tr><td>${teamCell(t)}</td><td class="dx-panel-names">${t.panel ? panelNames(t) : '<span class="dx-none">Not assigned</span>'}</td><td><div class="dx-checks">${t.blockers.map(b => `<span class="dx-check dx-no">${esc(b.text)}</span>`).join('')}</div></td></tr>`),
      'No team matches.');
  } else {
    $('#tab-hint').textContent = "Other sections' defense schedules are shown greyed out, so you can see why a room or panelist is taken.";
    const rows = [
      ...scheduled.filter(match).map(t => {
        // Grading and the panel's decision, as they come in.
        const progress = t.outcome ? ` · ${t.outcome}` : t.panel_size ? ` · Graded ${t.graded} of ${t.panel_size}` : '';
        return { at: t.schedule.date + t.schedule.time, html: `<tr><td class="dx-when">${fmtDay(t.schedule.date)}</td><td>${slotRange(t.schedule.time, t.schedule.end)}</td><td>${esc(t.schedule.venue)}</td><td>${teamCell(t, esc(progress))}</td><td class="dx-panel-names">${panelNames(t)}</td><td><div class="dx-acts">${t.passed ? '' : `<button class="dx-btn dx-btn-sm" data-override="${t.id}"><svg><use href="#dx-i-edit"/></svg>Change</button>`}</div></td></tr>` };
      }),
      ...($('#s-others').checked && !q ? OTHER : []).map(o => ({ at: o.date + o.time, html: `<tr class="dx-other"><td class="dx-when">${fmtDay(o.date)}</td><td>${slotRange(o.time, o.end)}</td><td>${esc(o.venue)}</td><td><span class="dx-strong">${esc(o.section)}</span><span class="dx-muted" style="display:block">Other section · ${esc(o.stage)}</span></td><td class="dx-panel-names">${o.panel.map(id => esc(nameOf(id))).join('<br>')}</td><td></td></tr>` })),
    ].sort((a, b) => a.at.localeCompare(b.at)).map(r => r.html);
    $('#sched-table').innerHTML = table(['16%', '15%', '10%', '20%', '29%', '10%'], ['Date', 'Time', 'Venue', 'Team', 'Panel', 'Actions'], rows, `Nothing scheduled for ${STAGE} yet.`);
  }

  const w = WINDOWS[STAGE];
  $('#open-generate').disabled = !(w?.start && w?.end);
  $('#open-generate').title = w?.start ? '' : 'Set the defense window first';
}

/* ---------------- Defense window ---------------- */
const DURATIONS = [15, 20, 30, 45, 60, 75, 90, 105, 120, 150, 180, 240];

/** What the chosen length would make of the defense day, said before anything is saved. */
function previewSlots() {
  const duration = +$('#w-duration').value;
  const slots = slotsFor(S.day.start, S.day.end, duration);
  $('#w-slots').innerHTML = `<div class="dx-slots-head"><b>${slots.length} ${slots.length === 1 ? 'defense' : 'defenses'} a day</b> <span class="dx-muted">in each room, so up to ${slots.length * S.venues.length} across ${S.venues.length} rooms</span></div>`
    + slots.map(x => `<span class="dx-slot">${esc(slotLabel(x, duration))}</span>`).join('');
}

function openWindow() {
  const w = WINDOWS[STAGE] || {};
  $('#w-sub').textContent = `The dates ${STAGE} is defended within, and how long each defense runs, for every section.`;
  $('#w-start').value = w.start || '';
  $('#w-end').value = w.end || '';
  $('#w-duration').innerHTML = DURATIONS.map(m => `<option value="${m}">${durationText(m)}</option>`).join('');
  $('#w-duration').value = w.duration;
  previewSlots();
  $('#d-window').showModal();
}

async function saveWindow() {
  const start = $('#w-start').value, end = $('#w-end').value;
  if (!start || !end || end < start) { toast('Pick a first day, and a last day on or after it.'); return; }
  try {
    const res = await api('PUT', URLS.window.replace('__STAGE__', encodeURIComponent(STAGE)), {
      start_date: start, end_date: end, duration_minutes: +$('#w-duration').value,
    });
    const d = res.data;
    WINDOWS[STAGE] = { start: d.start_date, end: d.end_date, duration: d.duration_minutes, slots: d.time_slots };
    // The Change dialog offers this stage's own times.
    S.slots = d.time_slots;
    S.session_minutes = d.duration_minutes;
    $('#d-window').close();
    render();
    toast(`${STAGE} window saved.${d.already_scheduled ? ` ${d.already_scheduled} already scheduled ${d.already_scheduled === 1 ? 'defense keeps' : 'defenses keep'} the time it has.` : ''}`);
  } catch (e) {
    toast(e.message);
  }
}

/* ---------------- Generate schedule ---------------- */
let gen = null, draft = [];
async function openGenerate() {
  const btn = $('#open-generate');
  btn.disabled = true;
  try {
    gen = (await api('POST', URLS.drafts, { defense_type: STAGE })).data;
  } catch (e) {
    toast(e.message);
    btn.disabled = false;
    return;
  }
  btn.disabled = false;
  draft = gen.draft.map(d => ({ ...d, team: T(d.team_id), panel: panelOf(T(d.team_id)), time: d.start_time, on: true, skipped: d.skipped || '' }));
  const w = gen.window;
  $('#g-sub').textContent = `${STAGE} · ${S.sections.length ? S.sections.join(', ') : 'All sections'} · window ${fmtShort(w.start_date)} – ${fmtShort(w.end_date)}. Checked against the other sections' defense schedules too. Change any slot before saving.`;
  drawGenerate();
  $('#d-generate').showModal();
}

function drawGenerate() {
  const w = gen.window;
  const notReady = TEAMS.filter(isBlocked);
  $('#g-body').innerHTML = (draft.length ? `<table class="dx-draft"><colgroup><col style="width:34px"><col style="width:22%"><col style="width:26%"><col style="width:17%"><col style="width:19%"><col style="width:14%"></colgroup>
      <thead><tr><th></th><th>Team</th><th>Panel</th><th>Date</th><th>Time</th><th>Venue</th></tr></thead><tbody>${draft.map((s, i) => `<tr data-i="${i}" class="${s.on ? '' : 'dx-off'}">
        <td><input type="checkbox" data-g="on" ${s.on ? 'checked' : ''} aria-label="Include ${esc(s.team.name)}"></td>
        <td><span class="dx-strong">${esc(s.team.name)}</span><span class="dx-muted" style="display:block">${esc(s.team.section)}</span><span class="dx-skip-note" data-skip>${esc(s.skipped)}</span><span class="dx-clash-note" data-clash></span></td>
        <td class="dx-panel-names">${panelNames(s.team)}</td>
        <td><input type="date" data-g="date" value="${s.date}" min="${w.start_date}" max="${w.end_date}" aria-label="Date for ${esc(s.team.name)}"></td>
        <td><select data-g="time" aria-label="Time for ${esc(s.team.name)}">${gen.time_slots.map(x => `<option value="${x}" ${x === s.time ? 'selected' : ''}>${slotLabel(x, gen.session_minutes)}</option>`).join('')}</select></td>
        <td><select data-g="venue" aria-label="Venue for ${esc(s.team.name)}">${gen.venues.map(v => `<option ${v === s.venue ? 'selected' : ''}>${esc(v)}</option>`).join('')}</select></td>
      </tr>`).join('')}</tbody></table>`
    : '<div class="dx-empty-note" style="text-align:center;padding:24px">No team is ready to schedule yet. The list below says what each one still needs.</div>')
    + (gen.unplaced.length ? `<div class="dx-info dx-red" style="margin-top:14px"><svg><use href="#dx-i-info"/></svg><span>No free slot in the window for ${gen.unplaced.map(t => esc(t.team_name)).join(', ')}. Widen the window or change the panel.</span></div>` : '')
    + (notReady.length ? `<div style="margin-top:14px"><div class="dx-strong" style="margin-bottom:6px">Can't be scheduled (${notReady.length})</div><div class="dx-list">${notReady.map(t => `<div class="dx-pick" style="cursor:default"><span class="dx-grow"><span class="dx-strong">${esc(t.name)}</span> <span class="dx-muted">${esc(t.blockers.map(b => b.text).join(' · '))}</span></span></div>`).join('')}</div></div>` : '');
  checkClashes();
}

function checkClashes() {
  let clashes = 0;
  const on = draft.filter(s => s.on);
  const fixed = allBookings();
  document.querySelectorAll('#g-body tr[data-i]').forEach(tr => {
    const s = draft[+tr.dataset.i];
    tr.classList.toggle('dx-off', !s.on);
    const others = on.filter(o => o !== s).map(o => ({ panel: o.panel, date: o.date, time: o.time, end: addMinutes(o.time, gen.session_minutes), venue: o.venue, who: `Team ${o.team.name}` }));
    const clash = s.on ? clashWith([...fixed, ...others], s.panel, s.date, s.time, addMinutes(s.time, gen.session_minutes), s.venue, nameOf) : null;
    tr.classList.toggle('dx-clash', !!clash);
    tr.querySelector('[data-clash]').textContent = clash ? clash.text : '';
    tr.querySelector('[data-skip]').hidden = !!clash;
    clashes += clash ? 1 : 0;
  });
  const n = on.length;
  $('#g-summary').textContent = n
    ? `${n} ${n === 1 ? 'team' : 'teams'} will be scheduled, added to each panelist's Google Calendar, and told by email.${clashes ? ` Fix ${clashes} ${clashes === 1 ? 'clash' : 'clashes'} first.` : ''}`
    : 'Tick at least one team.';
  $('#g-save').disabled = !n || clashes > 0;
  $('#g-save').textContent = n ? `Save schedule for ${n} ${n === 1 ? 'team' : 'teams'}` : 'Save schedule';
}

async function saveGenerate() {
  const items = draft.filter(s => s.on).map(s => ({ defense_id: s.defense_id, date: s.date, start_time: s.time, venue: s.venue }));
  if (!items.length) return;
  const btn = $('#g-save');
  btn.disabled = true;
  btn.textContent = 'Saving…';
  try {
    const res = await api('PUT', URLS.schedules, { defense_type: STAGE, items });
    const skipped = res.data.skipped || [];
    reloadWith(res.message + (skipped.length ? ' ' + skipped.map(s => `${s.team_name}: ${s.reason}`).join(' ') : ''));
  } catch (e) {
    toast(e.message);
    checkClashes();
  }
}

/* ---------------- Change one defense ---------------- */
let overriding = null, overrideConfirm = false;
function openOverride(id) {
  const t = T(id), w = WINDOWS[STAGE] || {};
  overriding = t;
  overrideConfirm = false;
  $('#o-sub').textContent = `Team ${t.name} · ${STAGE}. Moves this team only.`;
  $('#o-time').innerHTML = S.slots.map(x => `<option value="${x}">${slotLabel(x, S.session_minutes)}</option>`).join('');
  $('#o-venue').innerHTML = S.venues.map(v => `<option>${esc(v)}</option>`).join('');
  Object.assign($('#o-date'), { value: t.schedule.date, min: w.start || '', max: w.end || '' });
  $('#o-time').value = t.schedule.time;
  $('#o-venue').value = t.schedule.venue;
  $('#o-conflict').hidden = true;
  $('#o-save').textContent = 'Save';
  $('#d-override').showModal();
}

async function saveOverride() {
  const t = overriding;
  const body = { schedule_date: $('#o-date').value, start_time: $('#o-time').value, venue: $('#o-venue').value, confirm: overrideConfirm };
  if (!body.schedule_date) { toast('Pick a date.'); return; }
  try {
    await api('PUT', URLS.schedule.replace('__ID__', t.defense_id), body);
    reloadWith(`Moved Team ${t.name}'s defense.`);
  } catch (e) {
    if (e.status !== 409) { toast(e.message); return; }
    // Your own sections: keep it anyway on a second press. Another section: pick another slot.
    const other = e.body.other_section;
    $('#o-conflict-text').textContent = `${(e.body.conflicts || []).join(' ')} ${other ? 'Pick another time or room.' : 'Save again to keep it anyway.'}`;
    $('#o-conflict').hidden = false;
    overrideConfirm = !other;
    $('#o-save').textContent = other ? 'Save' : 'Save anyway';
  }
}

/* ---------------- Events ---------------- */
document.querySelectorAll('[data-tab]').forEach(b => b.addEventListener('click', () => { schedTab = b.dataset.tab; render(); }));
$('#reason-filter').addEventListener('change', render);
$('#s-others').addEventListener('change', render);
$('#s-search').addEventListener('input', render);
$('#sched-table').addEventListener('click', e => { const b = e.target.closest('[data-override]'); if (b) openOverride(+b.dataset.override); });
$('#open-window').addEventListener('click', openWindow);
$('#w-save').addEventListener('click', saveWindow);
$('#open-generate').addEventListener('click', openGenerate);
$('#g-body').addEventListener('change', e => {
  const tr = e.target.closest('tr[data-i]');
  if (!tr) return;
  const s = draft[+tr.dataset.i], k = e.target.dataset.g;
  s[k] = k === 'on' ? e.target.checked : e.target.value;
  if (k !== 'on') { s.skipped = ''; tr.querySelector('[data-skip]').textContent = ''; }
  checkClashes();
});
$('#g-save').addEventListener('click', saveGenerate);
$('#o-save').addEventListener('click', saveOverride);
$('#w-duration').addEventListener('input', previewSlots);
['#o-date', '#o-time', '#o-venue'].forEach(s => $(s).addEventListener('change', () => { overrideConfirm = false; $('#o-conflict').hidden = true; $('#o-save').textContent = 'Save'; }));

wireDialogs();
render();
showFlash();
