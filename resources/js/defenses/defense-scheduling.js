/*
 * Defense Scheduling: which of the CaPSA's teams can be scheduled for a stage,
 * which cannot and why, what is on the calendar (other sections greyed out),
 * the shared defense window, Generate schedule, and scheduling one team.
 *
 * The stage is switched in place (buttons, not links): moving between
 * Pre-proposal, Title, Pre Oral and Final Oral used to be a page reload each.
 * A single team is scheduled straight from its row - pick the defense type as
 * segmented buttons and a free slot from a grid, no dropdowns.
 */
import { $, esc, initials, fmtDay, fmtShort, slotLabel, addMinutes, isWeekend, api, toast, reloadWith, showFlash, clashWith, wireDialogs } from './shared.js';

const root = document.getElementById('dx-sched');
let S = JSON.parse(root.dataset.state);
const URLS = JSON.parse(root.dataset.urls);
let STAGE = S.stage;
let TEAMS = S.teams;
let EXPERTS = S.experts;
let WINDOWS = S.windows;
let OTHER = [];
let hasFee = () => STAGE !== 'Pre-proposal Defense';
const E = id => EXPERTS.find(e => e.id === id);
const T = id => TEAMS.find(t => t.id === id);
const nameOf = id => E(id)?.name || S.names[id] || 'A panelist';
const SEATS = [['chair', 'Panel Chair'], ['m1', 'Member 1'], ['m2', 'Member 2']];

function recomputeOther() {
  OTHER = (S.other || []).map(o => ({ ...o, other: true, who: o.section }));
}
recomputeOther();

const readyToSchedule = t => !t.schedule && !t.passed && t.blockers.length === 0;
const isBlocked = t => !t.schedule && !t.passed && t.blockers.length > 0;
const panelOf = t => (t.panel ? [t.panel.chair, t.panel.m1, t.panel.m2].filter(Boolean) : []);
const panelNames = t => SEATS.map(([k]) => (t.panel?.[k] ? `${esc(nameOf(t.panel[k]))}${k === 'chair' ? ' <span class="dx-muted">(Chair)</span>' : ''}` : '')).filter(Boolean).join('<br>');
const teamCell = (t, extra = '') => `<div class="dx-team"><span class="dx-tbadge dx-sm">${esc(initials(t.name))}</span><span><span class="dx-strong">${esc(t.name)}</span><span class="dx-muted" style="display:block">${esc(t.section)}${extra}</span></span></div>`;

/**
 * What the panel decided, for the Scheduled tab's Result column - or that
 * there is nothing to decide yet. "Awaiting result" (the defense has already
 * happened) is told apart from "Ongoing" (it hasn't) so a CaPSA can tell a
 * panel that has fallen behind from one that simply hasn't sat yet.
 */
const resultBadge = t => {
  if (!t.schedule) return '';
  if (t.outcome) return `<span class="dx-pill dx-pill-${t.outcome_tone || 'pass'}">${esc(t.outcome)}</span>`;
  const past = new Date(`${t.schedule.date}T${t.schedule.time}`) < new Date();
  return past ? '<span class="dx-pill dx-pill-major">Awaiting result</span>' : '<span class="dx-pill dx-pill-ongoing">Ongoing</span>';
};

/** Pre-proposal Defense reads as "Pre-proposal", Pre Oral as "POD". */
const stageShort = s => ({
  'Pre-proposal Defense': 'Pre-proposal',
  'Title Defense': 'Title',
  'Pre Oral Defense': 'POD',
  'Final Oral Defense': 'FOD',
}[s] || s);

// Every booking a slot must not clash with: this CaPSA's scheduled teams and every other section's.
const allBookings = except => [
  ...TEAMS.filter(t => t.schedule && t !== except).map(t => ({ panel: panelOf(t), ...t.schedule, who: `Team ${t.name}` })),
  ...OTHER,
];

const windowLabel = w => (w?.start && w?.end ? `${fmtShort(w.start)} – ${fmtShort(w.end)}, ${w.end.slice(0, 4)}` : 'not set');

/* ---------------- Switching stage, in place ---------------- */
function markStages() {
  document.querySelectorAll('#dx-stages [data-stage]').forEach(b => {
    if (b.dataset.stage === STAGE) b.setAttribute('aria-current', 'page');
    else b.removeAttribute('aria-current');
  });
}

function syncUrl() {
  const url = new URL(window.location.href);
  url.searchParams.set('defense_type', STAGE);
  history.replaceState(null, '', url);
}

function updateRubricWarn() {
  const warn = $('#rubric-warn');
  if (!warn) return;
  warn.hidden = !!S.has_rubric;
  const link = warn.dataset.rubricUrl ? `<a href="${esc(warn.dataset.rubricUrl)}">Grading Rubrics</a>` : 'Grading Rubrics';
  $('#rubric-warn-text').innerHTML = `No active grading rubric for ${esc(STAGE)} yet. Panelists can't grade these defenses until one is set up in ${link}.`;
}

async function loadStage(stage) {
  if (stage === STAGE) return;
  try {
    const res = await api('GET', URLS.board + '?defense_type=' + encodeURIComponent(stage));
    S = res.data;
    STAGE = S.stage;
    TEAMS = S.teams;
    EXPERTS = S.experts;
    WINDOWS = S.windows;
    recomputeOther();
    schedTab = 'ready';
    markStages();
    syncUrl();
    updateRubricWarn();
    render();
  } catch (e) {
    toast(e.message, 'error');
  }
}

/* ---------------- The board ---------------- */
let schedTab = 'ready';
function render() {
  $('#window-label').textContent = windowLabel(WINDOWS[STAGE]);
  $('#my-sections').textContent = S.sections.length ? S.sections.join(', ') : 'All sections';
  $('#export-schedule').href = `${URLS.self}?defense_type=${encodeURIComponent(STAGE)}&export=xlsx`;
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
      ? 'Panel assigned, requirements done and fees verified. Schedule any one of them, or use Generate schedule to give every one a date at once.'
      : 'Panel assigned and requirements done. Pre-proposal Defense has no fee.';
    const passed = ['Experts', S.stages.indexOf(STAGE) > 0 && 'Previous stage passed', 'Requirements done', 'Panel assigned', hasFee() && 'Fees verified'].filter(Boolean);
    $('#sched-table').innerHTML = table(['24%', '26%', '34%', '16%'], ['Team', 'Panel', 'Checks', 'Actions'],
      ready.filter(match).map(t => `<tr><td data-label="Team">${teamCell(t, t.attempt > 1 ? ` · Redefense ${t.attempt}` : '')}</td><td class="dx-panel-names" data-label="Panel">${panelNames(t)}</td><td data-label="Checks"><div class="dx-checks">${passed.map(p => `<span class="dx-check">✓ ${p}</span>`).join('')}</div></td><td style="text-align:right" data-label="Actions"><button class="dx-btn dx-btn-sm dx-btn-gold" data-schedule="${t.id}">Schedule</button></td></tr>`),
      "No team can be scheduled yet. The Can't be scheduled tab says what each one needs.");
  } else if (schedTab === 'blocked') {
    $('#tab-hint').textContent = 'These teams are left out of Generate schedule until they finish what is listed.';
    const key = $('#reason-filter').value;
    $('#sched-table').innerHTML = table(['26%', '34%', '40%'], ['Team', 'Panel', 'Still needs'],
      blocked.filter(match).filter(t => !key || t.blockers.some(b => b.key === key))
        .map(t => `<tr><td data-label="Team">${teamCell(t)}</td><td class="dx-panel-names" data-label="Panel">${t.panel ? panelNames(t) : '<span class="dx-none">Not assigned</span>'}</td><td data-label="Still needs"><div class="dx-checks">${t.blockers.map(b => `<span class="dx-check dx-no">${esc(b.text)}</span>`).join('')}</div></td></tr>`),
      'No team matches.');
  } else {
    $('#tab-hint').textContent = "Other sections' defense schedules are shown greyed out, so you can see why a room or panelist is taken.";
    const rows = [
      ...scheduled.filter(match).map(t => {
        // The panel's decision has its own column now; grading is what's left to say here.
        const progress = !t.outcome && t.panel_size ? ` · Graded ${t.graded} of ${t.panel_size}` : '';
        return { at: t.schedule.date + t.schedule.time, html: `<tr><td class="dx-when" data-label="Date">${fmtDay(t.schedule.date)}</td><td data-label="Time">${slotLabel(t.schedule.time, t.schedule.end)}</td><td data-label="Venue">${esc(t.schedule.venue)}</td><td data-label="Team">${teamCell(t, esc(progress))}</td><td class="dx-panel-names" data-label="Panel">${panelNames(t)}</td><td data-label="Result">${resultBadge(t)}</td><td data-label="Actions"><div class="dx-acts">${t.passed ? '' : `<button class="dx-btn dx-btn-sm" data-override="${t.id}"><svg><use href="#dx-i-edit"/></svg>Change</button>`}</div></td></tr>` };
      }),
      ...($('#s-others').checked && !q ? OTHER : []).map(o => ({ at: o.date + o.time, html: `<tr class="dx-other"><td class="dx-when" data-label="Date">${fmtDay(o.date)}</td><td data-label="Time">${slotLabel(o.time, o.end)}</td><td data-label="Venue">${esc(o.venue)}</td><td data-label="Team"><span class="dx-strong">${esc(o.section)}</span><span class="dx-muted" style="display:block">Other section · ${esc(o.stage)}</span></td><td class="dx-panel-names" data-label="Panel">${o.panel.map(id => esc(nameOf(id))).join('<br>')}</td><td data-label="Result"></td><td data-label="Actions"></td></tr>` })),
    ].sort((a, b) => a.at.localeCompare(b.at)).map(r => r.html);
    $('#sched-table').innerHTML = table(['14%', '13%', '9%', '18%', '21%', '15%', '10%'], ['Date', 'Time', 'Venue', 'Team', 'Panel', 'Result', 'Actions'], rows, `Nothing scheduled for ${STAGE} yet.`);
  }

  const w = WINDOWS[STAGE];
  $('#open-generate').disabled = !(w?.start && w?.end);
  $('#open-generate').title = w?.start ? '' : 'Set the defense window first';
}

/* ---------------- Defense window ---------------- */
function openWindow() {
  const w = WINDOWS[STAGE] || {};
  $('#w-sub').textContent = `The dates ${STAGE} is defended within, for every section.`;
  $('#w-start').value = w.start || '';
  $('#w-end').value = w.end || '';
  pickLength(w.minutes || 120);
  $('#d-window').showModal();
}

// Anything other than a preset button is a custom length, typed in minutes.
function pickLength(m, custom = false) {
  let preset = false;
  document.querySelectorAll('#w-length [data-minutes]').forEach(b => {
    const on = !custom && +b.dataset.minutes === m;
    b.classList.toggle('is-on', on);
    preset ||= on;
  });
  $('#w-length [data-custom]').classList.toggle('is-on', !preset);
  $('#w-custom').hidden = preset;
  if (!preset) $('#w-minutes').value = m;
}

async function saveWindow() {
  const start = $('#w-start').value, end = $('#w-end').value;
  const custom = $('#w-length [data-custom]').classList.contains('is-on');
  const minutes = custom ? +$('#w-minutes').value : +($('#w-length .is-on')?.dataset.minutes || 120);
  if (!start || !end || end < start) { toast('Pick a first day, and a last day on or after it.', 'error'); return; }
  const min = +$('#w-minutes').min, max = +$('#w-minutes').max;
  if (!Number.isInteger(minutes) || minutes < min || minutes > max) { toast(`Type a length from ${min} to ${max} minutes.`, 'error'); return; }
  try {
    const res = await api('PUT', URLS.window.replace('__STAGE__', encodeURIComponent(STAGE)), { start_date: start, end_date: end, duration_minutes: minutes });
    WINDOWS[STAGE] = { start: res.data.start_date, end: res.data.end_date, minutes: res.data.duration_minutes, slots: res.data.slots };
    $('#d-window').close();
    render();
    toast(`${STAGE} window saved.`);
  } catch (e) {
    toast(e.message, 'error');
  }
}

/* ---------------- Schedule one team ---------------- */
// The stage here is independent of the page's: a team is scheduled for
// whichever defense type is chosen, and its own board row (with that stage's
// defense id and blockers) is fetched when the choice changes.
let sc = null;

function openSchedule(teamId) {
  const t = T(teamId);
  if (!t) return;
  sc = { team: t, stage: STAGE, date: null, time: null, venue: S.venues[0], board: S, row: t, defenseId: t.defense_id, panel: panelOf(t), blockers: t.blockers, schedule: t.schedule, confirm: false };
  $('#sc-sub').textContent = `Team ${t.name} · ${t.section}`;
  drawScheduleDialog();
  $('#d-schedule').showModal();
}

async function pickScheduleStage(stage) {
  if (!sc || stage === sc.stage) return;
  sc.stage = stage;
  sc.date = null;
  sc.time = null;
  sc.confirm = false;
  // Say something while the stage's rows load.
  $('#sc-days').innerHTML = '<div class="dx-muted" style="padding:14px">Loading…</div>';
  try {
    const res = await api('GET', URLS.board + '?defense_type=' + encodeURIComponent(stage));
    sc.board = res.data;
    sc.row = res.data.teams.find(x => x.id === sc.team.id) || null;
    sc.defenseId = sc.row?.defense_id || null;
    sc.panel = sc.row ? panelOf(sc.row) : [];
    sc.blockers = sc.row?.blockers || [];
    sc.schedule = sc.row?.schedule || null;
  } catch (e) {
    toast(e.message, 'error');
  }
  drawScheduleDialog();
}

const scBookings = () => {
  const list = (sc.board.other || []).map(o => ({ ...o, other: true, who: o.section }));
  (sc.board.teams || []).filter(t => t.schedule && t.id !== sc.team.id).forEach(t => {
    list.push({ panel: panelOf(t), date: t.schedule.date, time: t.schedule.time, end: t.schedule.end, venue: t.schedule.venue, who: `Team ${t.name}` });
  });
  return list;
};

const scMinutes = () => sc.board.windows[sc.stage]?.minutes || 120;

const scName = id => (sc.board.experts.find(e => e.id === id)?.name) || (sc.board.names[id]) || 'A panelist';

function drawScheduleDialog() {
  // Defense type, as segmented buttons rather than a dropdown.
  $('#sc-stages').innerHTML = S.stages.map(s => `<button type="button" class="dx-seg-btn${s === sc.stage ? ' is-on' : ''}" data-scstage="${esc(s)}">${scShortLabel(s)}</button>`).join('');

  const w = sc.board.windows[sc.stage];
  const noWindow = !(w && w.start && w.end);
  $('#sc-window-note').hidden = !noWindow;
  if (noWindow) $('#sc-window-note-text').textContent = `No ${sc.stage} window set yet. Set it with the Defense window button first.`;

  // Venue, also segmented - pick it once, then pick a free block.
  const venues = sc.board.venues;
  if (!venues.includes(sc.venue)) sc.venue = venues[0];
  $('#sc-venues').innerHTML = venues.map(v => `<button type="button" class="dx-seg-btn${v === sc.venue ? ' is-on' : ''}" data-scvenue="${esc(v)}">${esc(v)}</button>`).join('');

  drawDays();

  $('#sc-save').textContent = 'Save schedule';
  $('#sc-save').disabled = !sc.defenseId || !sc.date || !sc.time || sc.blockers.length > 0 || noWindow;

  if (!sc.defenseId) $('#sc-summary').textContent = 'This team has no defense row for this stage yet.';
  else if (sc.blockers.length) $('#sc-summary').textContent = 'Not ready yet: ' + sc.blockers.map(b => b.text).join(' · ');
  else if (sc.date && sc.time) $('#sc-summary').textContent = `${fmtDay(sc.date)}, ${slotLabel(sc.time, addMinutes(sc.time, scMinutes()))} · ${sc.venue}`;
  else if (sc.schedule) $('#sc-summary').textContent = `Now: ${fmtDay(sc.schedule.date)}, ${slotLabel(sc.schedule.time, sc.schedule.end)} · ${sc.schedule.venue}. Pick another slot to move it.`;
  else $('#sc-summary').textContent = 'Pick a free slot.';
}

const scShortLabel = stageShort;

/** Why a start can't be used whoever sits it: weekends and times already gone. */
const unusable = (date, time) => (isWeekend(date) ? 'Weekends are not used.' : new Date(`${date}T${time}`) < new Date() ? 'That time has already passed.' : null);

/** The window's weekdays down the side, their start times across; taken ones greyed. */
function drawDays() {
  const wrap = $('#sc-days');
  const w = sc.board.windows[sc.stage];
  if (!(w && w.start && w.end)) { wrap.innerHTML = ''; return; }

  const bookings = scBookings();
  const days = [];
  for (let d = new Date(w.start + 'T00:00'); d <= new Date(w.end + 'T00:00'); d.setDate(d.getDate() + 1)) {
    // Local date parts: toISOString() is UTC, a day behind here before 8 AM.
    const iso = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    if (!isWeekend(iso)) days.push(iso);
  }
  const minutes = scMinutes();
  const slots = sc.board.windows[sc.stage]?.slots || sc.board.slots;

  wrap.innerHTML = days.map(date => {
    const cells = slots.map(time => {
      const end = addMinutes(time, minutes);
      const gone = unusable(date, time);
      const found = gone ? { text: gone } : sc.panel.length ? clashWith(bookings, sc.panel, date, time, end, sc.venue, scName) : null;
      const taken = found;
      const chosen = sc.date === date && sc.time === time;
      const note = found ? ` title="${esc(found.text)}"` : '';
      return `<button type="button" class="dx-slot${chosen ? ' is-on' : ''}${taken ? ' is-taken' : ''}"${note}
        ${taken ? 'disabled' : `data-scdate="${date}" data-sctime="${time}"`}>
        <span>${slotLabel(time, end)}</span>
        ${gone ? '<small>Passed</small>' : taken?.room ? `<small>In ${esc(taken.room)}</small>` : taken ? '<small>Taken</small>' : '<small>Free</small>'}
      </button>`;
    }).join('');
    return `<div class="dx-day"><span class="dx-day-lbl">${fmtDay(date)}</span><div class="dx-day-slots">${cells}</div></div>`;
  }).join('');
}

async function saveSchedule() {
  if (!sc?.defenseId || !sc.date || !sc.time) return;
  const btn = $('#sc-save');
  btn.disabled = true;
  btn.textContent = 'Saving…';
  try {
    await api('PUT', URLS.schedule.replace('__ID__', sc.defenseId), { schedule_date: sc.date, start_time: sc.time, venue: sc.venue, confirm: sc.confirm });
    reloadWith(`Team ${sc.team.name} scheduled for ${sc.stage}.`);
  } catch (e) {
    if (e.status === 409) {
      // Same rule as Change: another section's booking cannot be overruled.
      sc.confirm = !e.body.other_section;
      $('#sc-conflict-text').textContent = `${(e.body.conflicts || []).join(' ')} ${e.body.other_section ? 'Pick another slot.' : 'Save again to keep it anyway.'}`;
      $('#sc-conflict').hidden = false;
      $('#sc-save').disabled = false;
      $('#sc-save').textContent = e.body.other_section ? 'Save schedule' : 'Save anyway';
      return;
    }
    toast(e.message, 'error');
    btn.disabled = false;
    btn.textContent = 'Save schedule';
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
    toast(e.message, 'error');
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
        <td><select data-g="time" aria-label="Time for ${esc(s.team.name)}">${gen.time_slots.map(x => `<option value="${x}" ${x === s.time ? 'selected' : ''}>${slotLabel(x, addMinutes(x, gen.minutes))}</option>`).join('')}</select></td>
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
    const others = on.filter(o => o !== s).map(o => ({ panel: o.panel, date: o.date, time: o.time, end: addMinutes(o.time, gen.minutes), venue: o.venue, who: `Team ${o.team.name}` }));
    const gone = s.on ? unusable(s.date, s.time) : null;
    const clash = gone ? { text: gone } : s.on ? clashWith([...fixed, ...others], s.panel, s.date, s.time, addMinutes(s.time, gen.minutes), s.venue, nameOf) : null;
    const note = tr.querySelector('[data-clash]');
    tr.classList.toggle('dx-clash', !!clash);
    note.textContent = clash ? clash.text : '';
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
    toast(e.message, 'error');
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
  const minutes = w.minutes || 120;
  $('#o-time').innerHTML = (w.slots || S.slots).map(x => `<option value="${x}">${slotLabel(x, addMinutes(x, minutes))}</option>`).join('');
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
  if (!body.schedule_date) { toast('Pick a date.', 'error'); return; }
  try {
    await api('PUT', URLS.schedule.replace('__ID__', t.defense_id), body);
    reloadWith(`Moved Team ${t.name}'s defense.`);
  } catch (e) {
    if (e.status !== 409) { toast(e.message, 'error'); return; }
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
$('#sched-table').addEventListener('click', e => {
  const sched = e.target.closest('[data-schedule]');
  if (sched) { openSchedule(+sched.dataset.schedule); return; }
  const b = e.target.closest('[data-override]');
  if (b) openOverride(+b.dataset.override);
});
document.querySelectorAll('#dx-stages [data-stage]').forEach(b => b.addEventListener('click', () => loadStage(b.dataset.stage)));
$('#open-window').addEventListener('click', openWindow);
$('#w-save').addEventListener('click', saveWindow);
$('#w-length').addEventListener('click', e => {
  const b = e.target.closest('[data-minutes]');
  if (b) pickLength(+b.dataset.minutes);
  else if (e.target.closest('[data-custom]')) { pickLength(+$('#w-minutes').value || 45, true); $('#w-minutes').focus(); }
});
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
['#o-date', '#o-time', '#o-venue'].forEach(s => $(s).addEventListener('change', () => { overrideConfirm = false; $('#o-conflict').hidden = true; $('#o-save').textContent = 'Save'; }));

// The per-team schedule dialog.
$('#sc-stages').addEventListener('click', e => { const b = e.target.closest('[data-scstage]'); if (b) pickScheduleStage(b.dataset.scstage); });
$('#sc-venues').addEventListener('click', e => { const b = e.target.closest('[data-scvenue]'); if (b) { sc.venue = b.dataset.scvenue; sc.confirm = false; drawScheduleDialog(); } });
$('#sc-days').addEventListener('click', e => {
  const b = e.target.closest('[data-scdate]');
  if (!b) return;
  sc.date = b.dataset.scdate;
  sc.time = b.dataset.sctime;
  sc.confirm = false;
  $('#sc-conflict').hidden = true;
  drawScheduleDialog();
});
$('#sc-save').addEventListener('click', saveSchedule);

wireDialogs();
markStages();
updateRubricWarn();
render();
showFlash();