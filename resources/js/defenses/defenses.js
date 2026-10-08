/*
 * Defenses: a team's panel and its defense dates, on one page.
 *
 * A team moves left to right along four steps for the stage in view: its
 * panel made final (Panel not final), whatever still holds it back (Not
 * ready), Ready to schedule, and Scheduled. The steps are the page's main
 * filter; the stage tabs above them pick which defense. Drawn from the JSON
 * the page carries; a stage switch redraws in place, a save reloads.
 */
import { esc, initials, fmtDay, fmtShort, slotLabel, addMinutes, isWeekend, api, toast, reloadWith, showFlash, clashWith } from './shared.js';

const root = document.getElementById('dp');
const $ = id => document.getElementById(id);
const URLS = JSON.parse(root.dataset.urls);
const BOOT = JSON.parse(root.dataset.state);
const TODAY = BOOT.today;
const AWAY_REASONS = BOOT.away_reasons;
const SHORT = { 'Pre-proposal Defense': 'Pre-proposal', 'Title Defense': 'Title', 'Pre Oral Defense': 'Pre Oral', 'Final Oral Defense': 'Final Oral' };
const SEATS = [['chair', 'Panel Chair', "Master's degree or higher"], ['m1', 'Member 1', 'Any degree'], ['m2', 'Member 2', 'Any degree']];
const SEAT_ROLE = { chair: 'Panel Chair', m1: 'Member 1', m2: 'Member 2' };
// Still pickable: the CaPSA may want them anyway, the tag just says so.
const LEAVE = e => (e.leave ? ' <span class="leave">On study leave</span>' : '');
const STEPS = [
  ['panel', 'Panel not final', 'No panel yet, or still a draft'],
  ['blocked', 'Not ready', 'Fee not verified, or previous stage not passed'],
  ['ready', 'Ready to schedule', 'Panel final, fee and previous stage done'],
  ['scheduled', 'Scheduled', 'Has a date, or a result'],
];
const REPLACE_REASONS = ['Resigned', 'Long leave', 'Account deactivated', 'Other'];

let S = BOOT;
let TEAMS = S.teams;
const P = { step: BOOT.step, view: 'list', q: '', section: '', reason: '', others: true, picked: new Set(), rday: null };

const T = id => TEAMS.find(t => t.id === id);
const E = id => S.experts.find(e => e.id === id);
const nameOf = id => E(id)?.name || S.names[id] || 'A panelist';
const shortName = id => nameOf(id).replace(/^(Dr\.|Prof\.|Engr\.|Mr\.|Ms\.|Mrs\.)\s*/, '');
const panelIds = t => (t.panel ? [t.panel.chair, t.panel.m1, t.panel.m2].filter(Boolean) : []);
const win = () => S.windows[S.stage] || {};
const minutes = () => win().minutes || 120;
const endOf = time => addMinutes(time, minutes());

/* ---------------- icons ---------------- */
const svg = (d, extra = '') => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" ${extra}>${d}</svg>`;
const ICON = {
  x: svg('<path d="M18 6 6 18M6 6l12 12"/>'),
  info: svg('<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>'),
  cal: svg('<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'),
  search: svg('<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>'),
  users: svg('<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.6 3.4-5.5 6.5-5.5s5.7 1.9 6.5 5.5"/><circle cx="17" cy="9" r="2.6"/><path d="M16.5 14.6c2.6.2 4.4 2 5 5.4"/>'),
  edit: svg('<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>'),
  lock: svg('<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>', 'width="13" height="13"'),
  spark: svg('<path d="M12 3l1.9 5.8L20 10l-6.1 1.2L12 17l-1.9-5.8L4 10l6.1-1.2z"/>'),
};

/* ---------------- where a team is ---------------- */
const hard = t => t.blockers.filter(b => b.key !== 'req');
const owed = t => t.blockers.filter(b => b.key === 'req');
const missingExperts = t => t.blockers.find(b => b.key === 'experts');
const isDraft = t => !!t.panel && t.panel_status === 'draft';
const isFinal = t => !!t.panel && t.panel_status === 'final';

function stepOf(t) {
  if (t.schedule || t.outcome) return 'scheduled';
  if (!t.panel || isDraft(t)) return 'panel';
  if (hard(t).length) return 'blocked';
  return 'ready';
}

/** The CaPA's go matters only for a date still ahead. */
const needsAnswer = t => t.needs_go && t.schedule && !t.outcome && t.schedule.date >= TODAY;

/** Panelists of this team away on that day. */
const awayOn = (ids, date) => S.away.filter(a => ids.includes(a.expert_id) && date >= a.from && date <= a.to);
const awayText = (ids, date) => {
  const a = awayOn(ids, date)[0];
  return a ? { text: `${nameOf(a.expert_id)} is away that day (${a.reason.toLowerCase()}).`, away: true } : null;
};
const range = (from, to) => (from === to ? fmtDay(from) : `${fmtDay(from)} – ${fmtDay(to)}`);

function matches(t) {
  const q = P.q.trim().toLowerCase();
  return (!q || `${t.name} ${t.title || ''}`.toLowerCase().includes(q)) && (!P.section || t.section === P.section);
}

/* ---------------- small pieces ---------------- */
function panelHtml(t) {
  if (!t.panel) return '<span class="muted">Not assigned</span>';
  return '<div class="people">' + SEATS.map(([k, , ]) => (t.panel[k] ? `<div><b>${k === 'chair' ? 'PC' : k.toUpperCase()}</b><span>${esc(nameOf(t.panel[k]))}</span></div>` : '')).join('') + '</div>';
}

function expertsHtml(t) {
  const line = (k, who, role) => `<div><b>${k}</b>${who ? `<span>${esc(typeof who === 'string' ? who : who.name)}</span>` : `<span class="miss">No ${role}</span>`}</div>`;
  return '<div class="people">' + line('Adv', t.adviser, 'adviser') + line('Con', t.consultant, 'consultant') + line('Gra', t.grammarian, 'grammarian') + '</div>';
}

function teamCell(t, extra = '') {
  const r = t.member_list.filter(m => m.returnee);
  return `<div class="team"><span class="tbadge">${esc(initials(t.name))}</span><span class="meta"><button type="button" data-act="drawer" data-id="${t.id}">${esc(t.name)}</button><span>${esc(t.section)}${extra}</span>`
    + (r.length ? `<span class="ret" title="${esc('Returnee' + (r.length > 1 ? 's' : '') + ': ' + r.map(m => m.name).join('; '))}">↺ ${r.length > 1 ? r.length + ' returnees' : 'Returnee'}</span>` : '') + '</span></div>';
}

function triesPill(t) {
  const last = t.tries[t.tries.length - 1];
  if (!last || !t.waiting) return '';
  if (last.kind === 'cancelled') return `<span class="pill p-red" title="${esc('The CaPA did not give the go' + (last.note ? ': ' + last.note : '') + '.')}">Cancelled ${fmtShort(last.date)} · reschedule</span>`;
  const until = win().end;
  return `<span class="pill p-amber" title="The panel asked for a redefense on ${fmtDay(last.date)}. Waits for a new date.">${esc(last.note || 'Redefense')}${until ? ` (until ${fmtShort(until)})` : ''}</span>`;
}

function reqPill(t) {
  const r = owed(t);
  return r.length ? `<span class="pill p-amber" title="Doesn't stop scheduling. The panel follows it up.">⚠ ${esc(r[0].text)}</span>` : '';
}

function resultPill(t) {
  if (t.outcome) return `<span class="pill ${t.passed ? 'p-green' : t.outcome_tone === 'fail' ? 'p-red' : 'p-amber'}">${esc(t.outcome)}</span>`;
  const past = new Date(`${t.schedule.date}T${t.schedule.time}`) < new Date();
  return past ? `<span class="pill p-red">Awaiting result${t.panel_size ? ` · ${t.graded}/${t.panel_size} graded` : ''}</span>` : '<span class="pill p-blue">Ongoing</span>';
}

function capaPill(t) {
  if (!needsAnswer(t)) return '';
  return t.capa === 'go' ? '<span class="pill p-green">✓ CaPA: Go</span>' : '<span class="pill p-gray">Waiting for CaPA\'s go</span>';
}

function awayPill(t) {
  if (!t.schedule || t.outcome) return '';
  const a = awayOn(panelIds(t), t.schedule.date)[0];
  return a ? `<span class="pill p-amber" title="${esc(nameOf(a.expert_id))} keeps the seat. Move the defense, or hold it without them.">⚠ ${esc(shortName(a.expert_id).split(' ').slice(-1)[0])} away</span>` : '';
}

function panelButton(t, big = false) {
  const sm = big ? '' : ' sm';
  if (isFinal(t)) {
    return `<button type="button" class="btn ghost${sm} lockbtn" data-act="cantsit" data-id="${t.id}" title="Final since ${t.panel_final_since ? fmtDay(t.panel_final_since) : 'it was finalized'}. This panel sits every defense through Final Oral. If someone can't make it, mark them away or replace them.">${ICON.lock}Panelist can't sit</button>`;
  }
  if (isDraft(t)) return `<button type="button" class="btn ghost${sm}" data-act="assign-one" data-id="${t.id}">Change panel</button>`;
  // A panel from before panels were finalized: change it as before.
  return t.panel ? `<button type="button" class="btn ghost${sm}" data-act="assign-one" data-id="${t.id}">Change panel</button>` : '';
}

const table = (heads, rows, empty) => `<div class="tablewrap"><table><thead><tr>${heads.map(h => `<th${h === '' || h === 'Actions' ? ' style="text-align:right"' : ''}>${h}</th>`).join('')}</tr></thead><tbody>`
  + (rows.length ? rows.join('') : `<tr><td colspan="${heads.length}"><div class="empty">${empty}</div></td></tr>`) + '</tbody></table></div>';

/* ---------------- render ---------------- */
function render() {
  $('stages').innerHTML = S.stages.map(stg => {
    const p = S.progress?.[stg];
    return `<button type="button" role="tab" data-act="stage" data-stage="${esc(stg)}" aria-selected="${stg === S.stage}">${SHORT[stg]} ${p ? `<span class="prog">${p.done}/${p.of}</span>` : ''}</button>`;
  }).join('');

  const w = win();
  $('windowChip').innerHTML = ICON.cal + `<span>${SHORT[S.stage]} window: <b>${w.start ? `${fmtDay(w.start)} – ${fmtDay(w.end)}` : 'not set'}</b>${w.start ? ` · ${w.minutes >= 60 && w.minutes % 30 === 0 ? (w.minutes / 60) + ' hr' : w.minutes + ' min'} each` : ''}</span>`
    + (w.set_by ? `<span class="who" title="${esc(`Every section shares this window. Last set by ${w.set_by}${w.set_on ? ' on ' + fmtDay(w.set_on) : ''}.`)}">All sections</span>` : '')
    + `<button type="button" data-act="window">${w.start ? 'Change' : 'Set dates'}</button>`;

  $('genBtn').disabled = !w.start;
  $('genBtn').title = w.start ? 'Generate schedule (G)' : 'Set the defense window first';
  $('exportBtn').href = `${URLS.self}?defense_type=${encodeURIComponent(S.stage)}&export=xlsx`;

  const warn = $('rubricWarn');
  warn.hidden = !!S.has_rubric;
  if (!S.has_rubric) {
    const programs = (S.rubric_missing_programs || []).length ? ` for ${esc(S.rubric_missing_programs.join(', '))}` : '';
    $('rubricWarnText').innerHTML = `No active grading rubric for ${esc(S.stage)}${programs} yet. Panelists can't grade these defenses until one is set up in <a href="${esc(URLS.rubrics)}">Grading Rubrics</a>.`;
  }

  const counts = { panel: 0, blocked: 0, ready: 0, scheduled: 0 };
  TEAMS.forEach(t => { counts[stepOf(t)]++; });
  $('pipe').innerHTML = STEPS.map(([k, label, sub], i) => `<button type="button" class="step" role="tab" data-act="step" data-step="${k}" aria-selected="${P.step === k}"><span class="k"><i>${i + 1}</i>${label}</span><span class="n${k === 'blocked' && counts[k] ? ' red' : k === 'ready' && counts[k] ? ' green' : ''}">${counts[k]}</span><span class="s">${sub}</span></button>`).join('');

  renderMini(counts);
  renderTools();
  renderBoard();
  syncUrl();
}

function renderMini(counts) {
  $('mini').innerHTML = `<div class="stages" role="tablist" aria-label="Defense stage">${S.stages.map(stg => `<button type="button" role="tab" data-act="stage" data-stage="${esc(stg)}" aria-selected="${stg === S.stage}">${SHORT[stg]}</button>`).join('')}</div>`
    + `<div class="mini-steps" role="tablist" aria-label="Where each team is">${STEPS.map(([k, label]) => `<button type="button" role="tab" data-act="step" data-step="${k}" aria-selected="${P.step === k}">${label.replace('Ready to schedule', 'Ready').replace('Panel not final', 'Panel')} <b>${counts[k]}</b></button>`).join('')}</div>`
    + `<label class="search" for="mq">${ICON.search}<span class="sr">Search team or title</span><input id="mq" type="search" placeholder="Search team" value="${esc(P.q)}"></label>`
    + '<span class="grow"></span>'
    + `<button type="button" class="btn gold sm" data-act="generate"${win().start ? '' : ' disabled'}>${ICON.spark}Generate</button>`;
}

function renderTools() {
  const hint = {
    panel: 'Draft panels can be changed, and panelists moved between teams. Finalize a panel when it is set: from then on it sits every defense through Final Oral.',
    blocked: 'These teams are left out of Generate schedule until they finish what is listed. Requirements don\'t hold a team here.',
    ready: 'Teams on their first try, teams the CaPA cancelled, and teams up for a redefense all wait here for a date. Missing requirements don\'t stop scheduling.',
    scheduled: P.view === 'rooms' ? 'Every room, every section, one day at a time.' : 'Other sections\' bookings are shown greyed out, so you can see why a room or panelist is taken.',
  }[P.step];
  const sections = [...new Set(TEAMS.map(t => t.section))].sort();
  let html = `<span class="hint">${hint}</span>`
    + `<label class="search" for="q">${ICON.search}<span class="sr">Search team or title</span><input id="q" type="search" placeholder="Search team or title" aria-keyshortcuts="/" value="${esc(P.q)}"><kbd aria-hidden="true" title="Press / to search">/</kbd></label>`
    + (sections.length > 1 ? `<select class="field" id="secF" aria-label="Section"><option value="">All sections</option>${sections.map(s => `<option${s === P.section ? ' selected' : ''}>${esc(s)}</option>`).join('')}</select>` : '');
  if (P.step === 'blocked') {
    html += `<select class="field" id="reasonF" aria-label="What is missing"><option value="">Everything missing</option>${[['prev', 'Previous stage not passed'], ['fees', 'Fees not verified'], ['experts', 'Missing experts'], ['own-panelist', 'Own adviser on the panel']].map(r => `<option value="${r[0]}"${P.reason === r[0] ? ' selected' : ''}>${r[1]}</option>`).join('')}</select>`;
  }
  if (P.step === 'ready') {
    const c = { new: 0, cancelled: 0, redefense: 0 };
    TEAMS.filter(t => stepOf(t) === 'ready').forEach(t => { c[t.waiting || 'new']++; });
    html += `<select class="field" id="kindF" aria-label="Which teams"><option value="">All waiting (${c.new + c.cancelled + c.redefense})</option>${[['new', 'First try'], ['cancelled', 'Cancelled'], ['redefense', 'Redefense']].map(r => `<option value="${r[0]}"${P.reason === r[0] ? ' selected' : ''}>${r[1]} (${c[r[0]]})</option>`).join('')}</select>`;
  }
  if (P.step === 'scheduled') {
    html += `<label class="toggle" for="othersT"><input type="checkbox" id="othersT"${P.others ? ' checked' : ''}>Other sections</label>`
      + `<div class="viewseg" role="group" aria-label="View">${[['list', 'List'], ['week', 'Week'], ['rooms', 'Rooms']].map(([k, l]) => `<button type="button" data-act="view" data-view="${k}" aria-pressed="${P.view === k}">${l}</button>`).join('')}</div>`;
  }
  if (P.step === 'panel') html += `<button type="button" class="btn gold sm" data-act="assign-open">${ICON.users}Assign panel</button>`;
  $('tools').innerHTML = html;
}

function renderBoard() {
  let list = TEAMS.filter(t => stepOf(t) === P.step && matches(t));
  let body = '';
  $('selbar').hidden = true;

  if (P.step === 'panel') {
    const pickable = list.filter(t => !missingExperts(t));
    const allOn = pickable.length > 0 && pickable.every(t => P.picked.has(t.id));
    const drafts = list.filter(isDraft);
    body = (drafts.length ? `<div class="draftbar"><span class="pill p-blue">Draft</span><span class="txt"><b>${drafts.length} draft ${drafts.length === 1 ? 'panel' : 'panels'}.</b> Change them or move panelists between teams freely. Finalize when set: a final panel sits every defense through Final Oral.</span><button type="button" class="btn gold sm" data-act="finalize-all">${ICON.lock}Finalize all ${drafts.length}</button></div>` : '')
      + table([`<input type="checkbox" id="pickAll" aria-label="Select every team that can take a panel"${allOn ? ' checked' : ''}>`, 'Team', 'Capstone title', 'Team experts', 'Panel', 'Actions'], list.map(t => {
        const miss = missingExperts(t);
        const cell = miss ? `<span class="pill p-red">${esc(miss.text)}</span>`
          : t.panel ? `<div class="draftcell"><span class="pill p-blue">Draft</span>${panelHtml(t)}</div>`
          : '<span class="pill p-gold">No panel yet</span>';
        const acts = miss ? `<button type="button" class="btn ghost sm" disabled title="${esc(miss.text)} first">Assign panel</button>`
          : t.panel ? `<button type="button" class="btn ghost sm" data-act="assign-one" data-id="${t.id}">Change</button><button type="button" class="btn gold sm" data-act="finalize" data-id="${t.id}">Finalize</button>`
          : `<button type="button" class="btn gold sm" data-act="assign-one" data-id="${t.id}">Assign panel</button>`;
        return `<tr class="${P.picked.has(t.id) ? 'picked' : ''}"><td style="width:36px"><input type="checkbox" data-pick="${t.id}"${P.picked.has(t.id) ? ' checked' : ''}${miss ? ' disabled' : ''} aria-label="Select ${esc(t.name)}"></td>`
          + `<td>${teamCell(t, ` · ${t.members} ${t.members === 1 ? 'member' : 'members'}`)}</td><td><span class="clamp">${esc(t.title || '—')}</span></td><td>${expertsHtml(t)}</td><td>${cell}</td><td><div class="acts">${acts}</div></td></tr>`;
      }), 'Every panel is final.');
    const n = P.picked.size;
    if (n) {
      const drafted = [...P.picked].filter(id => T(id) && isDraft(T(id))).length;
      $('selbar').hidden = false;
      $('selbar').innerHTML = `<b>${n} ${n === 1 ? 'team' : 'teams'} selected</b><span class="muted">One panel for all of them.</span><span style="flex:1"></span><button type="button" class="btn ghost sm" data-act="clear-picks">Clear</button>`
        + (drafted ? `<button type="button" class="btn ghost sm" data-act="finalize-picked">${ICON.lock}Finalize ${drafted} ${drafted === 1 ? 'draft' : 'drafts'}</button>` : '')
        + `<button type="button" class="btn gold sm" data-act="assign-open">${ICON.users}Assign panel to selected</button>`;
    }
  } else if (P.step === 'blocked') {
    list = list.filter(t => !P.reason || hard(t).some(b => b.key === P.reason));
    body = table(['Team', 'Panel', 'Still needs', 'Actions'], list.map(t => `<tr><td>${teamCell(t)}</td><td>${panelHtml(t)}</td><td><div class="checks">${triesPill(t)}${hard(t).map(b => `<span class="pill p-red">${esc(b.text)}</span>`).join('')}${reqPill(t)}</div></td><td><div class="acts">${panelButton(t)}</div></td></tr>`),
      'Nothing is holding a team back.');
  } else if (P.step === 'ready') {
    list = list.filter(t => !P.reason || (t.waiting || 'new') === P.reason);
    const checks = ['Experts', S.stage !== S.stages[0] && 'Previous stage passed', 'Panel final', S.stage !== S.stages[0] && 'Fees verified'].filter(Boolean);
    body = table(['Team', 'Panel', 'Checks', 'Actions'], list.map(t => `<tr><td>${teamCell(t)}</td><td>${panelHtml(t)}</td><td><div class="checks">${triesPill(t)}${checks.map(c => `<span class="pill p-green">✓ ${c}</span>`).join('')}${reqPill(t) || '<span class="pill p-green">✓ Requirements done</span>'}</div></td>`
      + `<td><div class="acts">${panelButton(t)}<button type="button" class="btn gold sm" data-act="schedule" data-id="${t.id}">Schedule</button></div></td></tr>`),
      `No team is ready for ${esc(S.stage)} yet. "Not ready" says what each one still needs.`);
  } else {
    body = P.view === 'week' ? weekView(list) : P.view === 'rooms' ? roomsView(list) : scheduledList(list);
  }

  $('boardBody').innerHTML = body + `<div class="foot"><span>Showing ${list.length} of ${TEAMS.length} ${TEAMS.length === 1 ? 'team' : 'teams'}</span><span>${esc(S.stage)}</span></div>`;
}

const otherHere = () => (S.other || []).filter(o => o.stage === S.stage);

function scheduledList(list) {
  const rows = list.map(t => ({
    at: t.schedule.date + t.schedule.time,
    html: `<tr><td class="when"><b>${fmtDay(t.schedule.date)}</b><span>${slotLabel(t.schedule.time, t.schedule.end)}</span></td><td>${esc(t.schedule.venue)}</td><td>${teamCell(t)}</td><td>${panelHtml(t)}</td>`
      + `<td><div class="checks">${resultPill(t)}${capaPill(t)}${awayPill(t)}${t.tries.length ? `<span class="pill p-gray" title="${esc(t.tries.map(x => `${x.kind === 'cancelled' ? 'Cancelled' : 'Redefense'} ${fmtShort(x.date)}`).join(', '))}">${t.tries.length} earlier ${t.tries.length === 1 ? 'try' : 'tries'}</span>` : ''}</div></td>`
      + `<td><div class="acts">${needsAnswer(t) ? `<button type="button" class="btn ghost sm" data-act="capa" data-id="${t.id}">CaPA's answer</button>` : ''}${t.outcome ? '' : `<button type="button" class="btn ghost sm" data-act="move" data-id="${t.id}">${ICON.edit}Change</button>`}</div></td></tr>`,
  }));
  if (P.others && !P.q && !P.section) {
    otherHere().forEach(o => rows.push({
      at: o.date + o.time,
      html: `<tr class="other"><td class="when"><b>${fmtDay(o.date)}</b><span>${slotLabel(o.time, o.end)}</span></td><td>${esc(o.venue)}</td><td><b>${esc(o.section)}</b><br><span class="muted" style="font-size:12px">Another section</span></td><td><div class="people">${o.panel.map(id => `<div><span>${esc(nameOf(id))}</span></div>`).join('')}</div></td><td></td><td></td></tr>`,
    }));
  }
  rows.sort((a, b) => a.at.localeCompare(b.at));
  return table(['When', 'Venue', 'Team', 'Panel', 'Status', 'Actions'], rows.map(r => r.html), `Nothing scheduled for ${esc(S.stage)} yet.`);
}

function windowDays() {
  const w = win();
  const out = [];
  if (!w.start) return out;
  for (let d = new Date(w.start + 'T00:00'); d <= new Date(w.end + 'T00:00'); d.setDate(d.getDate() + 1)) {
    const iso = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    if (!isWeekend(iso)) out.push(iso);
  }
  return out;
}

const slots = () => win().slots || S.slots;

function weekView(list) {
  const days = windowDays();
  if (!days.length) return `<div class="empty">Set the ${SHORT[S.stage]} window to see its week.</div>`;
  let cells = '<div class="wh"></div>' + days.map(d => `<div class="wh">${fmtDay(d)}</div>`).join('');
  const outside = list.filter(t => !days.includes(t.schedule.date)).length;
  slots().forEach(time => {
    cells += `<div class="wt">${slotLabel(time, endOf(time))}</div>`;
    days.forEach(d => {
      const here = list.filter(t => t.schedule.date === d && t.schedule.time === time)
        .map(t => `<button type="button" class="bk" data-act="${t.outcome ? 'drawer' : 'move'}" data-id="${t.id}"><b>${esc(t.name)}</b><span>${esc(t.schedule.venue)} · Chair ${esc(shortName(t.panel?.chair))}</span></button>`);
      if (P.others) {
        otherHere().filter(o => o.date === d && o.time === time).forEach(o => here.push(`<div class="bk oth"><b>${esc(o.section)}</b><span>${esc(o.venue)} · another section</span></div>`));
      }
      cells += `<div>${here.join('')}</div>`;
    });
  });
  return `<div class="week-wrap"><div class="wk" style="--days:${days.length}">${cells}</div></div>`
    + (outside ? `<div class="wk-note">${outside} ${outside === 1 ? 'defense falls' : 'defenses fall'} outside this window. Switch to List to see ${outside === 1 ? 'it' : 'them'}.</div>` : '');
}

/* Every room and every section for one day. */
function roomsView(list) {
  const days = windowDays();
  if (!days.length) return `<div class="empty">Set the ${SHORT[S.stage]} window to see its rooms.</div>`;
  if (!days.includes(P.rday)) P.rday = days.find(d => d >= TODAY) || days[0];
  const all = otherHere();
  const total = S.venues.length * slots().length;
  const head = `<div class="rdays" role="tablist" aria-label="Day">${days.map(d => {
    const n = list.filter(t => t.schedule.date === d).length + all.filter(o => o.date === d).length;
    return `<button type="button" role="tab" data-act="rday" data-d="${d}" aria-selected="${d === P.rday}"><b>${fmtDay(d)}</b><span>${n} of ${total} slots</span></button>`;
  }).join('')}</div>`
    + '<div class="rkey"><span><i class="k mine"></i>Your sections</span><span><i class="k oth"></i>Another CaPSA\'s section</span><span><i class="k free"></i>Free</span></div>';
  const rows = S.venues.map(v => `<tr><th scope="row">${esc(v)}</th>${slots().map(time => {
    const mine = list.find(t => t.schedule.date === P.rday && t.schedule.time === time && t.schedule.venue === v);
    const oth = all.find(o => o.date === P.rday && o.time === time && o.venue === v);
    if (mine) return `<td><button type="button" class="bk" data-act="${mine.outcome ? 'drawer' : 'move'}" data-id="${mine.id}"><b>${esc(mine.name)}</b><span>${esc(mine.section)} · Chair ${esc(shortName(mine.panel?.chair))}</span></button></td>`;
    if (oth) return `<td><div class="bk oth"><b>${esc(oth.section)}</b><span>${oth.chair ? 'Chair ' + esc(shortName(oth.chair)) : 'Another section'}</span></div></td>`;
    return '<td><span class="rfree">Free</span></td>';
  }).join('')}</tr>`).join('');
  return head + `<div class="tablewrap"><table class="rooms"><thead><tr><th>Room</th>${slots().map(t => `<th>${slotLabel(t, endOf(t))}</th>`).join('')}</tr></thead><tbody>${rows}</tbody></table></div>`;
}

/* ---------------- dialogs ---------------- */
const dlg = $('dlg');
function openDialog(html, kind = '') {
  // From the drawer to a window: closed and opened again, so it lands in its own place.
  if (dlg.open) dlg.close();
  dlg.className = 'dlg' + (kind ? ' ' + kind : '');
  dlg.innerHTML = html;
  dlg.showModal();
  return dlg;
}
const head = (title, sub) => `<div class="dh"><div class="ttl"><h2 id="dlgTitle">${title}</h2>${sub ? `<p>${sub}</p>` : ''}</div><button type="button" class="icon-btn" data-act="close" aria-label="Close">${ICON.x}</button></div>`;
dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });

async function send(btn, method, url, body, done) {
  btn.disabled = true;
  try {
    const res = await api(method, url, body);
    reloadWith(done(res));
  } catch (e) {
    toast(e.message, 'error');
    btn.disabled = false;
  }
}

/* The team's drawer: its people, its panel, and where it is at every stage. */
function openDrawer(id) {
  const t = T(id);
  const step = stepOf(t);
  const journey = t.journey.map((j, i) => {
    let dot = 'jdot', label;
    if (j.passed) { dot += ' done'; label = `${j.outcome} · ${fmtDay(j.date)}`; }
    else if (j.outcome) { dot += ' bad'; label = `${j.outcome}${j.date ? ' · ' + fmtDay(j.date) : ''}`; }
    else if (j.date) { dot += ' now'; label = `${fmtDay(j.date)}, ${slotLabel(j.time)} · ${j.venue}`; }
    else if (j.stage === S.stage && t.panel) { dot += ' now'; label = step === 'ready' ? 'Ready to schedule' : step === 'blocked' ? hard(t).map(b => b.text).join(' · ') : 'Waiting for a final panel'; }
    else label = !t.panel ? 'Waiting for a panel' : j.attempt > 1 ? 'Waiting for a redefense date' : 'Not yet';
    if (j.stage === S.stage && needsAnswer(t)) label += t.capa === 'go' ? ' · CaPA: Go' : ' · waiting for the CaPA\'s go';
    const tries = j.stage === S.stage && t.tries.length
      ? `<ul class="tries">${t.tries.map(x => `<li><b>${x.kind === 'cancelled' ? 'Cancelled' : esc(x.note || 'Redefense')}</b> · ${fmtDay(x.date)}${x.kind === 'cancelled' ? ` · CaPA: not ready${x.note ? ' (' + esc(x.note) + ')' : ''}` : ''}</li>`).join('')}</ul>` : '';
    return `<li><span class="${dot}">${dot.includes('done') ? '✓' : i + 1}</span><div><b>${esc(j.stage)}</b><span>${esc(label)}</span>${tries}</div></li>`;
  }).join('');
  const returnees = t.member_list.filter(m => m.returnee).length;
  const act = step === 'panel'
    ? (missingExperts(t) ? '' : isDraft(t)
      ? `<button type="button" class="btn ghost" data-act="assign-one" data-id="${t.id}">Change panel</button><button type="button" class="btn gold" data-act="finalize" data-id="${t.id}">${ICON.lock}Finalize panel</button>`
      : `<button type="button" class="btn gold" data-act="assign-one" data-id="${t.id}">Assign panel</button>`)
    : panelButton(t, true) + (step === 'ready' ? `<button type="button" class="btn gold" data-act="schedule" data-id="${t.id}">Schedule ${SHORT[S.stage]}</button>` : '');
  openDialog(head(esc(t.name), `${esc(t.section)} · ${t.members} ${t.members === 1 ? 'member' : 'members'}`) + '<div class="db">'
    + `<p style="margin:0;font-weight:600">${esc(t.title || 'No title yet')}</p>`
    + `<div><span class="lbl">Members${returnees ? ` · ${returnees} returnee${returnees > 1 ? 's' : ''}` : ''}</span><ul class="members">${t.member_list.map(m => `<li><span class="grow">${esc(m.name)}</span><label class="toggle ret-toggle${m.returnee ? ' on' : ''}"><input type="checkbox" data-returnee="${m.id}"${m.returnee ? ' checked' : ''}>↺ Returnee</label></li>`).join('') || '<li class="muted">No members on record.</li>'}</ul></div>`
    + `<div><span class="lbl">Team experts</span>${expertsHtml(t)}</div>`
    + `<div><span class="lbl">Panel${isDraft(t) ? ' <span class="pill p-blue">Draft</span>' : ''}</span>${panelHtml(t)}`
    + (isFinal(t) ? `<p class="muted" style="margin:6px 0 0;font-size:12.5px;display:flex;gap:6px;align-items:center">${ICON.lock}Final${t.panel_final_since ? ' since ' + fmtDay(t.panel_final_since) : ''}: sits every defense through Final Oral. If someone can't sit, mark them away or replace just that person.</p>`
      : isDraft(t) ? '<p class="muted" style="margin:6px 0 0;font-size:12.5px">Draft. Change it, or move panelists to another team, until you finalize it.</p>' : '') + '</div>'
    + `<div><span class="lbl">Defenses</span><ol class="journey">${journey}</ol></div></div>`
    + `<div class="df">${act}</div>`, 'drawer');
}

/* Assign panel: teams on the left, three seats on the right, then the experts to fill them. */
let A = null;
function openAssign(ids) {
  const first = ids.length === 1 ? T(ids[0]) : null;
  A = { teams: new Set(ids), seats: first?.panel ? { ...first.panel } : { chair: null, m1: null, m2: null }, active: 'chair', q: '', tq: '' };
  if (A.seats.chair) A.active = !A.seats.m1 ? 'm1' : !A.seats.m2 ? 'm2' : 'chair';
  openDialog(head('Assign panel', 'Saved as a draft: you can change it, or move panelists to another team, until you finalize it. A final panel sits all four defenses, and any redefense.')
    + '<div class="db" id="aBody"></div><div class="df"><span class="sum" id="aSum"></span><button type="button" class="btn ghost" data-act="close">Cancel</button><button type="button" class="btn gold" id="aSave" data-act="assign-save">Save panel</button></div>', 'wide');
  drawAssign();
}

const ownOf = () => new Set([...A.teams].flatMap(id => [T(id)?.adviser?.id, T(id)?.consultant?.id]).filter(Boolean));
const suggested = e => [...A.teams].some(id => T(id)?.suggested.includes(e.id));

function drawAssign() {
  const own = ownOf();
  SEATS.forEach(([k]) => { if (own.has(A.seats[k])) A.seats[k] = null; });
  const teams = TEAMS.filter(t => !A.tq || `${t.name} ${t.title || ''}`.toLowerCase().includes(A.tq.toLowerCase()));
  const left = `<div><div class="col-h"><span>Teams</span><span>${A.teams.size} picked</span></div>`
    + `<label class="search" for="aTq" style="max-width:none;margin-bottom:8px">${ICON.search}<span class="sr">Search teams</span><input id="aTq" type="search" placeholder="Search teams" value="${esc(A.tq)}"></label>`
    + `<div class="list">${teams.map(t => {
      const miss = missingExperts(t);
      const off = !!miss || isFinal(t);
      const note = miss ? `<span class="warn">${esc(miss.text)}</span>`
        : isFinal(t) ? `<span>Final${t.panel_final_since ? ' since ' + fmtDay(t.panel_final_since) : ''}. Sits every defense through Final Oral.</span>`
        : t.panel ? '<span>Has a draft panel. This replaces it.</span>' : `<span>${esc(t.title || '')}</span>`;
      return `<label class="pick${off ? ' off' : ''}"><input type="checkbox" data-ateam="${t.id}"${A.teams.has(t.id) ? ' checked' : ''}${off ? ' disabled' : ''}><span class="tbadge">${esc(initials(t.name))}</span><span class="grow"><b>${esc(t.name)} <span class="muted" style="font-weight:500">${esc(t.section)}</span></b>${note}</span></label>`;
    }).join('')}</div></div>`;
  const seats = `<div class="seats">${SEATS.map(([k, label, rule]) => {
    const e = A.seats[k] ? { name: nameOf(A.seats[k]), degree: E(A.seats[k])?.degree } : null;
    return `<button type="button" class="seat${e ? ' filled' : ''}" data-act="seat" data-seat="${k}" aria-pressed="${A.active === k}"><span class="seat-k">${label}</span><span class="sn">${e ? esc(e.name) : 'Pick someone'}</span><span class="sr2">${e ? esc(e.degree || 'No degree on record') : rule}</span></button>`;
  }).join('')}</div>`;
  const taken = SEATS.filter(([k]) => k !== A.active).map(([k]) => A.seats[k]);
  const exs = S.experts.filter(e => !own.has(e.id))
    .filter(e => !A.q || `${e.name} ${e.tags.join(' ')}`.toLowerCase().includes(A.q.toLowerCase()))
    .sort((a, b) => suggested(b) - suggested(a));
  const right = `<div><div class="col-h"><span>Choosing: ${SEAT_ROLE[A.active] === 'Panel Chair' ? 'Panel Chair' : SEAT_ROLE[A.active]}</span><span>${own.size ? own.size + ' hidden: advise or consult these teams' : ''}</span></div>${seats}`
    + `<label class="search" for="aQ" style="max-width:none;margin-bottom:8px">${ICON.search}<span class="sr">Search name or expertise</span><input id="aQ" type="search" placeholder="Search name or expertise" value="${esc(A.q)}"></label>`
    + `<div class="list">${exs.map(e => {
      const on = A.seats[A.active] === e.id;
      const where = SEATS.find(([k]) => A.seats[k] === e.id);
      const why = A.active === 'chair' && !e.chair ? 'Needs a Master\'s to chair' : taken.includes(e.id) ? 'Already ' + where[1] : '';
      return `<button type="button" class="ex${on ? ' on' : ''}" data-act="pick-ex" data-id="${e.id}"${why ? ' disabled' : ''}><span class="av">${esc(initials(e.name))}</span><span class="grow"><b>${suggested(e) ? '<span class="star" title="Matches the teams\' project tags">★</span>' : ''}${esc(e.name)}${e.degree ? ` <span class="deg">${esc(e.degree)}</span>` : ''}${LEAVE(e)}</b><span>${esc(why || e.tags.join(', ') || 'No expertise listed')}</span></span><span class="use">${on ? 'Picked' : why ? '' : 'Set as ' + SEAT_ROLE[A.active]}</span></button>`;
    }).join('') || '<div class="empty">No expert matches.</div>'}</div></div>`;
  $('aBody').innerHTML = `<div class="assign">${left}${right}</div>`;
  const n = A.teams.size;
  $('aSum').textContent = !n ? 'Tick at least one team.' : !A.seats.chair ? 'A Panel Chair is required.' : `Saves this panel for ${n} ${n === 1 ? 'team' : 'teams'}, as a draft.`;
  $('aSave').disabled = !n || !A.seats.chair;
}

/* Finalize: a draft panel becomes the team's panel through Final Oral. */
let FZ = [];
function openFinalize(ids) {
  FZ = ids;
  const n = ids.length;
  if (!n) { toast('No draft panel to finalize.', 'error'); return; }
  openDialog(head(n === 1 ? `Finalize ${esc(T(ids[0]).name)}'s panel` : `Finalize ${n} panels`, 'After this the panel can\'t be changed. It sits every defense through Final Oral.')
    + `<div class="db"><div class="list" style="max-height:300px">${ids.map(id => { const t = T(id); return `<div class="fz-row"><span class="tbadge">${esc(initials(t.name))}</span><span class="grow"><b>${esc(t.name)} <span class="muted" style="font-weight:500">${esc(t.section)}</span></b></span>${panelHtml(t)}</div>`; }).join('')}</div>`
    + `<div class="banner blue">${ICON.info}<span class="txt">If a panelist later can't sit, you can still mark them <b>away</b> for a few days, or <b>replace</b> just that person.</span></div></div>`
    + `<div class="df"><button type="button" class="btn ghost" data-act="close">Cancel</button><button type="button" class="btn gold" data-act="finalize-save">${ICON.lock}Finalize ${n === 1 ? 'panel' : n + ' panels'}</button></div>`);
}

/* A panelist on a final panel who can't sit: away for some days, or replaced for good. */
let RP = null;
function openCantSit(id) {
  RP = { team: T(id), seat: null, mode: null, from: '', to: '', why: AWAY_REASONS[0], reason: null, other: '', pick: null, q: '' };
  openDialog(head(`Panelist can't sit · ${esc(RP.team.name)}`, 'The panel stays the same through Final Oral. Someone away for a few days keeps the seat.')
    + '<div class="db" id="rpBody"></div><div class="df"><span class="sum" id="rpSum"></span><button type="button" class="btn ghost" data-act="close">Cancel</button><button type="button" class="btn gold" id="rpSave" data-act="cantsit-save" disabled>Save</button></div>', 'wide');
  drawCantSit();
}

function awayHits(id) {
  if (!RP.from || !RP.to || RP.to < RP.from) return [];
  return TEAMS.filter(t => t.schedule && !t.outcome && panelIds(t).includes(id) && t.schedule.date >= RP.from && t.schedule.date <= RP.to);
}

function drawCantSit() {
  const t = RP.team;
  const seats = SEATS.filter(([k]) => t.panel[k]).map(([k, label]) => {
    const mine = S.away.filter(a => a.expert_id === t.panel[k]);
    return `<button type="button" class="rp-seat" data-act="rp-seat" data-seat="${k}" aria-pressed="${RP.seat === k}"><span class="seat-k">${label}</span><span class="sn">${esc(nameOf(t.panel[k]))}</span>${mine.length ? `<span class="sa">Away ${mine.map(a => range(a.from, a.to)).join(', ')}</span>` : ''}</button>`;
  }).join('');
  let html = `<div><span class="lbl">1. Who can't sit?</span><div class="rp-seats${RP.mode === 'replace' ? ' out' : ''}">${seats}</div></div>`;
  if (RP.seat) {
    const out = t.panel[RP.seat];
    html += `<div><span class="lbl">2. For how long?</span><div class="rp-modes"><button type="button" class="rp-mode" data-act="rp-mode" data-m="away" aria-pressed="${RP.mode === 'away'}"><b>Away for a few days</b><span>Travel, seminar, sick. Keeps the seat.</span></button><button type="button" class="rp-mode" data-act="rp-mode" data-m="replace" aria-pressed="${RP.mode === 'replace'}"><b>For good</b><span>Resigned, long leave. Someone takes the seat.</span></button></div></div>`;
    if (RP.mode === 'away') {
      const hits = awayHits(out);
      html += `<div class="two"><div><label class="lbl" for="rpFrom">3. Away from</label><input class="field" type="date" id="rpFrom" value="${RP.from}" min="${TODAY}"></div><div><label class="lbl" for="rpTo">Last day away</label><input class="field" type="date" id="rpTo" value="${RP.to}" min="${RP.from || TODAY}"></div></div>`
        + `<div><span class="lbl">Why</span><div class="seg">${AWAY_REASONS.map(r => `<button type="button" data-act="rp-why" data-r="${r}" aria-pressed="${RP.why === r}">${r}</button>`).join('')}</div></div>`
        + `<div class="banner blue">${ICON.info}<span class="txt"><b>${esc(nameOf(out))}</b> stays on the panel. Those days show as <b>Panelist away</b> when you schedule, and Generate skips them, for every panel they sit on.</span></div>`
        + (hits.length ? `<div class="banner gold">${ICON.info}<span class="txt">Already scheduled on those days: ${hits.map(h => `<b>${esc(h.name)}</b> (${fmtDay(h.schedule.date)})`).join(', ')}. They stay put and get an <b>away</b> flag. Move them, or hold them without ${esc(nameOf(out))}.</span></div>` : '');
    } else if (RP.mode === 'replace') {
      html += `<div><span class="lbl">3. Why</span><div class="seg">${REPLACE_REASONS.map(r => `<button type="button" data-act="rp-reason" data-r="${r}" aria-pressed="${RP.reason === r}">${r}</button>`).join('')}</div>`
        + (RP.reason === 'Other' ? `<label class="sr" for="rpOther">Reason</label><input class="field reason-other" id="rpOther" type="text" maxlength="255" placeholder="Say why, for the record" value="${esc(RP.other)}">` : '') + '</div>';
      const own = [t.adviser?.id, t.consultant?.id];
      const keep = SEATS.filter(([k]) => k !== RP.seat).map(([k]) => t.panel[k]);
      const list = S.experts.filter(e => e.id !== out && !own.includes(e.id) && !keep.includes(e.id))
        .filter(e => !RP.q || `${e.name} ${e.tags.join(' ')}`.toLowerCase().includes(RP.q.toLowerCase()));
      html += `<div><span class="lbl">4. Who takes the ${SEAT_ROLE[RP.seat]} seat</span>`
        + `<label class="search" for="rpQ" style="max-width:none;margin-bottom:8px">${ICON.search}<span class="sr">Search name or expertise</span><input id="rpQ" type="search" placeholder="Search name or expertise" value="${esc(RP.q)}"></label>`
        + `<div class="list" style="max-height:260px">${list.map(e => {
          const why = RP.seat === 'chair' && !e.chair ? 'Needs a Master\'s to chair' : '';
          const on = RP.pick === e.id;
          return `<button type="button" class="ex${on ? ' on' : ''}" data-act="rp-pick" data-id="${e.id}"${why ? ' disabled' : ''}><span class="av">${esc(initials(e.name))}</span><span class="grow"><b>${esc(e.name)}${e.degree ? ` <span class="deg">${esc(e.degree)}</span>` : ''}${LEAVE(e)}</b><span>${esc(why || e.tags.join(', ') || 'No expertise listed')}</span></span><span class="use">${on ? 'Picked' : why ? '' : 'Pick'}</span></button>`;
        }).join('') || '<div class="empty">No expert matches.</div>'}</div></div>`
        + `<div class="banner blue">${ICON.info}<span class="txt">Defenses already held keep <b>${esc(nameOf(out))}</b> on record. The new panelist sits every defense still to come, and both are told.</span></div>`;
    }
  }
  $('rpBody').innerHTML = html;
  syncCantSit();
}

function syncCantSit() {
  const t = RP.team;
  let ok = false, sum;
  if (!RP.seat) sum = 'Pick the panelist who can\'t sit.';
  else if (!RP.mode) sum = 'Away for a few days, or for good?';
  else if (RP.mode === 'away') {
    ok = RP.from && RP.to && RP.to >= RP.from;
    sum = !RP.from || !RP.to ? 'Set the days they are away.' : RP.to < RP.from ? 'The last day comes before the first.' : `${nameOf(t.panel[RP.seat])} away ${range(RP.from, RP.to)}`;
  } else {
    const reasonOk = RP.reason && (RP.reason !== 'Other' || RP.other.trim().length >= 5);
    ok = reasonOk && RP.pick;
    sum = !reasonOk ? 'Say why, for the record.' : !RP.pick ? 'Pick who takes the seat.' : `${nameOf(t.panel[RP.seat])} → ${nameOf(RP.pick)}`;
  }
  $('rpSave').disabled = !ok;
  $('rpSave').textContent = RP.mode === 'away' ? 'Mark away' : RP.mode === 'replace' ? 'Replace' : 'Save';
  $('rpSum').textContent = sum;
}

/* Schedule one team: venue, then a free slot from the window's grid. */
let SC = null;
function bookings(except) {
  return [
    ...TEAMS.filter(t => t.schedule && !t.outcome && t !== except).map(t => ({ panel: panelIds(t), ...t.schedule, who: `Team ${t.name}` })),
    ...(S.other || []).map(o => ({ ...o, other: true, who: o.section })),
  ];
}

/** Why nobody can defend at this time, whoever sits it. */
const unusable = (date, time) => (isWeekend(date) ? { text: 'Weekends are not used.' } : new Date(`${date}T${time}`) < new Date() ? { text: 'That time has already passed.' } : null);

function slotIssue(list, panel, date, time, venue) {
  return unusable(date, time) || awayText(panel, date) || clashWith(list, panel, date, time, endOf(time), venue, nameOf);
}

function openSchedule(id) {
  const t = T(id);
  SC = { team: t, venue: S.venues[0], date: null, time: null, confirm: false };
  openDialog(head(`Schedule ${esc(t.name)}`, `${esc(S.stage)} · ${esc(t.section)} · Chair ${esc(nameOf(t.panel.chair))}`)
    + '<div class="db" id="scBody"></div><div class="df"><span class="sum" id="scSum"></span><button type="button" class="btn ghost" data-act="close">Cancel</button><button type="button" class="btn gold" id="scSave" data-act="sched-save" disabled>Save schedule</button></div>', 'wide');
  drawSchedule();
}

function drawSchedule() {
  const list = bookings(SC.team);
  const panel = panelIds(SC.team);
  let html = `<div><span class="lbl">Venue</span><div class="seg">${S.venues.map(v => `<button type="button" data-act="venue" data-v="${esc(v)}" aria-pressed="${SC.venue === v}">${esc(v)}</button>`).join('')}</div></div>`;
  const days = windowDays();
  if (!days.length) {
    html += `<div class="banner gold">${ICON.info}<span class="txt">No ${SHORT[S.stage]} window yet. Set the window first.</span></div>`;
  } else {
    html += `<div><span class="lbl">Pick a free slot · gold is free</span><div class="days">${days.map(d => `<div class="day"><span class="day-l">${fmtDay(d)}</span><div class="slots">${slots().map(time => {
      const c = slotIssue(list, panel, d, time, SC.venue);
      const on = SC.date === d && SC.time === time;
      const label = !c ? (on ? 'Selected' : 'Free') : c.away ? 'Panelist away' : c.room ? 'In ' + c.room : /taken/.test(c.text) ? 'Room taken' : /passed|Weekend/.test(c.text) ? 'Passed' : 'Panelist busy';
      return `<button type="button" class="slot" data-act="slot" data-d="${d}" data-t="${time}"${c ? ` disabled title="${esc(c.text)}"` : ''} aria-pressed="${on}"><b>${slotLabel(time, endOf(time))}</b><span>${label}</span></button>`;
    }).join('')}</div></div>`).join('')}</div></div>`;
  }
  html += `<div class="banner red" id="scWarn" hidden>${ICON.info}<span class="txt" id="scWarnT"></span></div>`;
  $('scBody').innerHTML = html;
  $('scSum').textContent = SC.date ? `${fmtDay(SC.date)}, ${slotLabel(SC.time, endOf(SC.time))} · ${SC.venue}` : 'Pick a free slot. Hover a grey one to see why it is taken.';
  $('scSave').disabled = !SC.date;
}

/* Move one scheduled defense. */
let MV = null;
function openMove(id) {
  const t = T(id);
  MV = { team: t, confirm: false };
  const w = win();
  openDialog(head(`Change ${esc(t.name)}'s defense`, `${esc(S.stage)}. Moves this team only. The panel and the team are told by email.`)
    + `<div class="db"><div class="banner red" id="mvWarn" hidden>${ICON.info}<span class="txt" id="mvWarnT"></span></div>`
    + `<div><label class="lbl" for="mvD">Date</label><input class="field" type="date" id="mvD" value="${t.schedule.date}" min="${TODAY > (w.start || '') ? TODAY : w.start}" ${w.end ? `max="${w.end}"` : ''} style="width:100%"></div>`
    + `<div class="two"><div><label class="lbl" for="mvT">Time</label><select class="field" id="mvT">${slots().map(s => `<option value="${s}"${s === t.schedule.time ? ' selected' : ''}>${slotLabel(s, endOf(s))}</option>`).join('')}</select></div>`
    + `<div><label class="lbl" for="mvV">Venue</label><select class="field" id="mvV">${S.venues.map(v => `<option${v === t.schedule.venue ? ' selected' : ''}>${esc(v)}</option>`).join('')}</select></div></div></div>`
    + '<div class="df"><button type="button" class="btn ghost" data-act="close">Cancel</button><button type="button" class="btn gold" id="mvSave" data-act="move-save">Save</button></div>');
}

/* The CaPA's answer, as they gave it to the CaPSA in person. */
let CP = null;
function openCapa(id) {
  const t = T(id);
  CP = { team: t, answer: t.capa === 'go' ? 'go' : null, note: '' };
  openDialog(head(`CaPA's answer · ${esc(t.name)}`, `${esc(S.stage)} · ${fmtDay(t.schedule.date)}, ${slotLabel(t.schedule.time, t.schedule.end)}${t.adviser ? ' · Adviser ' + esc(t.adviser.name) : ''}`)
    + '<div class="db" id="cpBody"></div><div class="df"><span class="sum" id="cpSum"></span><button type="button" class="btn ghost" data-act="close">Cancel</button><button type="button" class="btn gold" id="cpSave" data-act="capa-save" disabled>Save</button></div>');
  drawCapa();
}

function drawCapa() {
  const t = CP.team;
  let html = `<p class="muted" style="margin:0;font-size:12.5px">${t.adviser ? esc(t.adviser.name) : 'The CaPA'} answers on their Team Assignments page. Record it here only if they told you directly.</p>`
    + `<div class="capa-opts"><button type="button" class="rp-mode" data-act="cp-ans" data-a="go" aria-pressed="${CP.answer === 'go'}"><b>Go</b><span>The team defends on this date.</span></button><button type="button" class="rp-mode" data-act="cp-ans" data-a="not_ready" aria-pressed="${CP.answer === 'not_ready'}"><b>Not ready</b><span>The defense is cancelled.</span></button></div>`;
  if (CP.answer === 'not_ready') {
    html += `<div><label class="lbl" for="cpNote">What the CaPA said (optional)</label><input class="field" id="cpNote" type="text" maxlength="255" style="width:100%" placeholder="For example, system not working yet" value="${esc(CP.note)}"></div>`
      + `<div class="banner red">${ICON.info}<span class="txt">The status becomes <b>Cancelled</b> and the slot is freed. Team ${esc(t.name)} goes back in line, marked Cancelled, and waits for you to give it a new date. The team and the panel are told.</span></div>`;
  }
  $('cpBody').innerHTML = html;
  $('cpSave').disabled = !CP.answer;
  $('cpSave').textContent = CP.answer === 'not_ready' ? 'Cancel the defense' : 'Save';
  $('cpSave').className = 'btn ' + (CP.answer === 'not_ready' ? 'danger' : 'gold');
  $('cpSum').textContent = CP.answer ? '' : 'Did the CaPA give the go?';
}

/* Defense window: shared by every section. */
function openWindow() {
  const w = win();
  const m = w.minutes || 120;
  const preset = [60, 90, 120].includes(m);
  openDialog(head(`${SHORT[S.stage]} window`, `Every section shares this window, as agreed at the CaPSA meeting.${w.set_by ? ` Last set by ${esc(w.set_by)}${w.set_on ? ' on ' + fmtDay(w.set_on) : ''}.` : ''}`)
    + `<div class="db"><div class="two"><div><label class="lbl" for="wS">First day</label><input class="field" type="date" id="wS" value="${w.start || ''}" min="${TODAY}"></div><div><label class="lbl" for="wE">Last day</label><input class="field" type="date" id="wE" value="${w.end || ''}" min="${TODAY}"></div></div>`
    + `<div><span class="lbl">Length of each defense</span><div class="seg" id="wLen">${[[60, '1 hour'], [90, '1½ hours'], [120, '2 hours']].map(o => `<button type="button" data-act="len" data-min="${o[0]}" aria-pressed="${m === o[0]}">${o[1]}</button>`).join('')}<button type="button" data-act="len" data-min="custom" aria-pressed="${!preset}">Custom</button></div>`
    + `<div id="wCustom" style="margin-top:8px"${preset ? ' hidden' : ''}><label class="lbl" for="wM">Minutes per defense (30–240)</label><input class="field" type="number" id="wM" min="30" max="240" step="5" value="${m}" style="width:140px"></div></div>`
    + '<p class="muted" style="margin:0;font-size:12.5px">Weekdays, 8 AM – 5 PM, with lunch from 12 to 1 kept free. Saving changes it for every section and tells the other CaPSAs. Nobody is scheduled until someone uses Generate schedule.</p></div>'
    + '<div class="df"><button type="button" class="btn ghost" data-act="close">Cancel</button><button type="button" class="btn gold" data-act="window-save">Save window</button></div>');
}

/* Generate: a draft for every ready team, editable, checked for clashes. */
let G = null;
async function openGenerate() {
  if (!win().start) { openWindow(); return; }
  try {
    G = (await api('POST', URLS.drafts, { defense_type: S.stage })).data;
  } catch (e) {
    toast(e.message, 'error');
    return;
  }
  G.rows = G.draft.map(d => ({ ...d, team: T(d.team_id), time: d.start_time, on: true }));
  openDialog(head('Generate schedule', `${esc(S.stage)} · window ${fmtShort(G.window.start_date)} – ${fmtShort(G.window.end_date)}. Checked against other sections and panelists' days away. Change any slot before saving.`)
    + '<div class="db" id="gBody"></div><div class="df"><span class="sum" id="gSum"></span><button type="button" class="btn ghost" data-act="close">Cancel</button><button type="button" class="btn gold" id="gSave" data-act="gen-save">Save schedule</button></div>', 'wide');
  drawGenerate();
}

function drawGenerate() {
  const w = G.window;
  const min = TODAY > w.start_date ? TODAY : w.start_date;
  let html = G.rows.length ? `<div class="tablewrap"><table class="draft"><thead><tr><th></th><th>Team</th><th>Panel</th><th>Date</th><th>Time</th><th>Venue</th></tr></thead><tbody>${G.rows.map((x, i) => `<tr data-i="${i}"><td><input type="checkbox" data-g="on"${x.on ? ' checked' : ''} aria-label="Include ${esc(x.team.name)}"></td><td><b>${esc(x.team.name)}</b><br><span class="muted" style="font-size:12px">${esc(x.team.section)}</span>${x.skipped ? `<span class="muted" style="display:block;font-size:11.5px" data-skip>${esc(x.skipped)}</span>` : ''}<span class="clash-note" data-clash></span></td><td>${panelHtml(x.team)}</td>`
    + `<td><input type="date" data-g="date" value="${x.date}" min="${min}" max="${w.end_date}" aria-label="Date"></td>`
    + `<td><select data-g="time" aria-label="Time">${G.time_slots.map(s => `<option value="${s}"${s === x.time ? ' selected' : ''}>${slotLabel(s, addMinutes(s, G.minutes))}</option>`).join('')}</select></td>`
    + `<td><select data-g="venue" aria-label="Venue">${G.venues.map(v => `<option${v === x.venue ? ' selected' : ''}>${esc(v)}</option>`).join('')}</select></td></tr>`).join('')}</tbody></table></div>`
    : '<div class="empty">No team is ready to schedule yet.</div>';
  if (G.unplaced.length) html += `<div class="banner red">${ICON.info}<span class="txt">No free slot in the window for ${G.unplaced.map(u => esc(u.team_name)).join(', ')}. Widen the window or change the panel.</span></div>`;
  if (G.not_ready.length) html += `<div class="banner gold">${ICON.info}<span class="txt">Not ready, so left out (${G.not_ready.length}): ${G.not_ready.map(u => `<b>${esc(u.team_name)}</b> ${esc(u.reason)}`).join(' · ')}</span></div>`;
  $('gBody').innerHTML = html;
  checkGenerate();
}

function checkGenerate() {
  const fixed = bookings();
  const on = G.rows.filter(x => x.on);
  let clashes = 0;
  document.querySelectorAll('#gBody tr[data-i]').forEach(tr => {
    const x = G.rows[+tr.dataset.i];
    tr.classList.toggle('off', !x.on);
    const others = on.filter(o => o !== x).map(o => ({ panel: panelIds(o.team), date: o.date, time: o.time, end: addMinutes(o.time, G.minutes), venue: o.venue, who: `Team ${o.team.name}` }));
    const c = x.on ? (unusable(x.date, x.time) || awayText(panelIds(x.team), x.date) || clashWith([...fixed, ...others], panelIds(x.team), x.date, x.time, addMinutes(x.time, G.minutes), x.venue, nameOf)) : null;
    tr.classList.toggle('clash', !!c);
    tr.querySelector('[data-clash]').textContent = c ? c.text : '';
    clashes += c ? 1 : 0;
  });
  const n = on.length;
  $('gSum').textContent = n ? `${n} ${n === 1 ? 'team' : 'teams'} will be scheduled, added to each panelist's Google Calendar, and told by email.${clashes ? ` Fix ${clashes} ${clashes === 1 ? 'clash' : 'clashes'} first.` : ''}` : 'Tick at least one team.';
  $('gSave').disabled = !n || clashes > 0;
  $('gSave').textContent = n ? `Save schedule for ${n} ${n === 1 ? 'team' : 'teams'}` : 'Save schedule';
}

/* Keyboard shortcuts. */
const KEYS = [
  ['Finding', [['Search teams', ['/']], ['Leave the search, or close a window', ['Esc']]]],
  ['Moving around', [['Panel not final · Not ready · Ready · Scheduled', ['1', '2', '3', '4']], ['Previous or next defense', ['[', ']']], ['List, Week or Rooms (when Scheduled)', ['V']], ['Back to the top', ['T']]]],
  ['Doing', [['Assign panel', ['A']], ['Generate schedule', ['G']], ['Defense window', ['W']], ['These shortcuts', ['?']]]],
];
function openKeys() {
  openDialog(head('Keyboard shortcuts', 'Shortcuts work when you are not typing in a box.')
    + `<div class="db"><div class="keys">${KEYS.map(([h, rows]) => `<div><h3>${h}</h3><dl>${rows.map(([what, keys]) => `<div><dt>${what}</dt><dd>${keys.map(k => `<kbd>${esc(k)}</kbd>`).join('')}</dd></div>`).join('')}</dl></div>`).join('')}</div></div>`
    + '<div class="df"><button type="button" class="btn gold" data-act="close">Got it</button></div>');
}

/* ---------------- stage switching, in place ---------------- */
async function loadStage(stage) {
  if (stage === S.stage) return;
  try {
    const res = await api('GET', `${URLS.board}?defense_type=${encodeURIComponent(stage)}`);
    S = { ...S, ...res.data };
    TEAMS = S.teams;
    P.reason = '';
    P.picked.clear();
    render();
  } catch (e) {
    toast(e.message, 'error');
  }
}

function syncUrl() {
  const url = new URL(window.location.href);
  url.searchParams.set('defense_type', S.stage);
  url.searchParams.set('step', P.step);
  history.replaceState(null, '', url);
}

/* ---------------- actions ---------------- */
const closeDlg = () => dlg.close();
const ACT = {
  close: closeDlg,
  stage: el => { loadStage(el.dataset.stage); keepBoardInView(el); },
  step: el => { P.step = el.dataset.step; P.reason = ''; render(); keepBoardInView(el); },
  view: el => { P.view = el.dataset.view; render(); },
  rday: el => { P.rday = el.dataset.d; renderBoard(); },
  top: () => root.scrollTo({ top: 0, behavior: 'smooth' }),
  keys: openKeys,
  window: openWindow,
  generate: openGenerate,
  drawer: el => openDrawer(+el.dataset.id),
  'assign-open': () => openAssign([...P.picked]),
  'assign-one': el => openAssign([+el.dataset.id]),
  'clear-picks': () => { P.picked.clear(); renderBoard(); },
  seat: el => { A.active = el.dataset.seat; drawAssign(); },
  'pick-ex': el => {
    A.seats[A.active] = +el.dataset.id;
    const next = SEATS.find(([k]) => !A.seats[k]);
    if (next) A.active = next[0];
    drawAssign();
  },
  'assign-save': el => send(el, 'POST', URLS.bulk, { team_ids: [...A.teams], chair_expert_id: A.seats.chair, member1_expert_id: A.seats.m1, member2_expert_id: A.seats.m2 }, res => {
    const done = res.data.assigned.length, skipped = res.data.skipped;
    return `Draft panel saved for ${done} ${done === 1 ? 'team' : 'teams'}. Finalize it when it is set.` + (skipped.length ? ` Skipped ${skipped.map(s => `${s.team_name} (${s.reason})`).join('; ')}` : '');
  }),
  finalize: el => openFinalize([+el.dataset.id]),
  'finalize-all': () => openFinalize(TEAMS.filter(t => isDraft(t) && matches(t)).map(t => t.id)),
  'finalize-picked': () => openFinalize([...P.picked].filter(id => T(id) && isDraft(T(id)))),
  'finalize-save': el => send(el, 'POST', URLS.finalize, { team_ids: FZ }, res => res.message + ' They sit every defense through Final Oral.'),
  cantsit: el => openCantSit(+el.dataset.id),
  'rp-seat': el => { RP.seat = el.dataset.seat; RP.pick = null; drawCantSit(); },
  'rp-mode': el => { RP.mode = el.dataset.m; drawCantSit(); if (RP.mode === 'away' && !RP.from) $('rpFrom').focus(); },
  'rp-why': el => { RP.why = el.dataset.r; drawCantSit(); },
  'rp-reason': el => { RP.reason = el.dataset.r; drawCantSit(); if (RP.reason === 'Other') $('rpOther').focus(); },
  'rp-pick': el => { RP.pick = +el.dataset.id; drawCantSit(); },
  'cantsit-save': el => {
    const out = RP.team.panel[RP.seat];
    if (RP.mode === 'away') {
      send(el, 'POST', URLS.away, { expert_id: out, starts_on: RP.from, ends_on: RP.to, reason: RP.why }, res => res.message);
    } else {
      send(el, 'POST', URLS.replace.replace('__ID__', RP.team.id), { seat: RP.seat, expert_id: RP.pick, reason: RP.reason === 'Other' ? RP.other.trim() : RP.reason }, res => res.message);
    }
  },
  schedule: el => openSchedule(+el.dataset.id),
  venue: el => { SC.venue = el.dataset.v; SC.date = null; SC.time = null; SC.confirm = false; drawSchedule(); },
  slot: el => { SC.date = el.dataset.d; SC.time = el.dataset.t; SC.confirm = false; drawSchedule(); },
  'sched-save': async el => {
    el.disabled = true;
    try {
      await api('PUT', URLS.schedule.replace('__ID__', SC.team.defense_id), { schedule_date: SC.date, start_time: SC.time, venue: SC.venue, confirm: SC.confirm });
      reloadWith(`Team ${SC.team.name} scheduled for ${fmtDay(SC.date)}. The panel and the team are told by email.`);
    } catch (e) {
      el.disabled = false;
      if (e.status !== 409) { toast(e.message, 'error'); return; }
      SC.confirm = !e.body.other_section;
      $('scWarn').hidden = false;
      $('scWarnT').textContent = `${(e.body.conflicts || []).join(' ')} ${e.body.other_section ? 'Pick another slot.' : 'Save again to keep it anyway.'}`;
      el.textContent = e.body.other_section ? 'Save schedule' : 'Save anyway';
    }
  },
  move: el => openMove(+el.dataset.id),
  'move-save': async el => {
    const t = MV.team;
    const body = { schedule_date: $('mvD').value, start_time: $('mvT').value, venue: $('mvV').value, confirm: MV.confirm };
    if (!body.schedule_date) { toast('Pick a date.', 'error'); return; }
    el.disabled = true;
    try {
      await api('PUT', URLS.schedule.replace('__ID__', t.defense_id), body);
      reloadWith(`Moved Team ${t.name}'s defense to ${fmtDay(body.schedule_date)}.`);
    } catch (e) {
      el.disabled = false;
      if (e.status !== 409) { toast(e.message, 'error'); return; }
      const other = e.body.other_section;
      $('mvWarn').hidden = false;
      $('mvWarnT').textContent = `${(e.body.conflicts || []).join(' ')} ${other ? 'Another section\'s booking can\'t be overruled. Pick another time or room.' : 'Save again to keep it anyway.'}`;
      MV.confirm = !other;
      el.textContent = other ? 'Save' : 'Save anyway';
    }
  },
  capa: el => openCapa(+el.dataset.id),
  'cp-ans': el => { CP.answer = el.dataset.a; drawCapa(); if (CP.answer === 'not_ready') $('cpNote').focus(); },
  'capa-save': el => send(el, 'POST', URLS.capa.replace('__ID__', CP.team.defense_id), { answer: CP.answer, note: CP.note.trim() || null }, res => res.message),
  len: el => {
    el.parentNode.querySelectorAll('button').forEach(b => b.setAttribute('aria-pressed', String(b === el)));
    $('wCustom').hidden = el.dataset.min !== 'custom';
    if (el.dataset.min === 'custom') $('wM').focus();
  },
  'window-save': async el => {
    const start = $('wS').value, end = $('wE').value;
    const on = document.querySelector('#wLen [aria-pressed="true"]');
    const m = !on || on.dataset.min === 'custom' ? +$('wM').value : +on.dataset.min;
    if (!start || !end || end < start) { toast('Pick a first day, and a last day on or after it.', 'error'); return; }
    if (!Number.isInteger(m) || m < 30 || m > 240) { toast('Type a length from 30 to 240 minutes.', 'error'); return; }
    el.disabled = true;
    try {
      const res = await api('PUT', URLS.window.replace('__STAGE__', encodeURIComponent(S.stage)), { start_date: start, end_date: end, duration_minutes: m });
      S.windows[S.stage] = { start: res.data.start_date, end: res.data.end_date, minutes: res.data.duration_minutes, slots: res.data.slots, set_by: res.data.set_by, set_on: res.data.set_on };
      closeDlg();
      render();
      toast(`${SHORT[S.stage]} window saved for every section. The other CaPSAs are told.`);
    } catch (e) {
      toast(e.message, 'error');
      el.disabled = false;
    }
  },
  'gen-save': async el => {
    const items = G.rows.filter(x => x.on).map(x => ({ defense_id: x.defense_id, date: x.date, start_time: x.time, venue: x.venue }));
    if (!items.length) return;
    el.disabled = true;
    el.textContent = 'Saving…';
    try {
      const res = await api('PUT', URLS.schedules, { defense_type: S.stage, items });
      const skipped = res.data.skipped || [];
      reloadWith(res.message + (skipped.length ? ' ' + skipped.map(s => `${s.team_name}: ${s.reason}`).join(' ') : ''));
    } catch (e) {
      toast(e.message, 'error');
      checkGenerate();
    }
  },
};

/* Switching from the compact bar lands you at the top of the new list, not halfway down it. */
function keepBoardInView(el) {
  if (!el.closest('#mini')) return;
  const board = root.querySelector('.board');
  root.scrollTo({ top: Math.max(0, board.offsetTop - $('mini').offsetHeight - 8) });
}

root.addEventListener('click', e => {
  const el = e.target.closest('[data-act]');
  if (!el || el.disabled) return;
  const fn = ACT[el.dataset.act];
  if (fn) { e.preventDefault(); fn(el); }
});

root.addEventListener('input', e => {
  const t = e.target;
  if (t.id === 'q' || t.id === 'mq') {
    P.q = t.value;
    renderBoard();
    const other = $(t.id === 'q' ? 'mq' : 'q');
    if (other) other.value = P.q;
  } else if (t.id === 'aTq' || t.id === 'aQ') {
    A[t.id === 'aTq' ? 'tq' : 'q'] = t.value;
    drawAssign();
    const i = $(t.id);
    i.focus();
    i.setSelectionRange(i.value.length, i.value.length);
  } else if (t.id === 'rpQ') {
    RP.q = t.value;
    drawCantSit();
    const i = $('rpQ');
    i.focus();
    i.setSelectionRange(i.value.length, i.value.length);
  } else if (t.id === 'rpOther') {
    RP.other = t.value;
    syncCantSit();
  } else if (t.id === 'rpFrom' || t.id === 'rpTo') {
    RP[t.id === 'rpFrom' ? 'from' : 'to'] = t.value;
    if (t.id === 'rpFrom' && (!RP.to || RP.to < RP.from)) RP.to = RP.from;
    drawCantSit();
    $(t.id).focus();
  } else if (t.id === 'cpNote') {
    CP.note = t.value;
  } else if (['mvD', 'mvT', 'mvV'].includes(t.id)) {
    MV.confirm = false;
    $('mvWarn').hidden = true;
    $('mvSave').textContent = 'Save';
  }
});

root.addEventListener('change', async e => {
  const t = e.target;
  if (t.id === 'secF') { P.section = t.value; renderBoard(); }
  else if (t.id === 'reasonF' || t.id === 'kindF') { P.reason = t.value; renderBoard(); }
  else if (t.id === 'othersT') { P.others = t.checked; renderBoard(); }
  else if (t.id === 'pickAll') {
    TEAMS.filter(x => stepOf(x) === 'panel' && !missingExperts(x) && matches(x)).forEach(x => (t.checked ? P.picked.add(x.id) : P.picked.delete(x.id)));
    renderBoard();
  } else if (t.dataset.pick) {
    const id = +t.dataset.pick;
    t.checked ? P.picked.add(id) : P.picked.delete(id);
    renderBoard();
  } else if (t.dataset.ateam) {
    const id = +t.dataset.ateam;
    t.checked ? A.teams.add(id) : A.teams.delete(id);
    drawAssign();
  } else if (t.dataset.returnee) {
    t.disabled = true;
    try {
      const res = await api('PATCH', URLS.returnee.replace('__ID__', t.dataset.returnee), { is_returnee: t.checked });
      TEAMS.forEach(team => team.member_list.forEach(m => { if (m.id === +t.dataset.returnee) m.returnee = t.checked; }));
      toast(res.message);
      const open = TEAMS.find(team => team.member_list.some(m => m.id === +t.dataset.returnee));
      renderBoard();
      if (open) openDrawer(open.id);
    } catch (err) {
      toast(err.message, 'error');
      t.checked = !t.checked;
      t.disabled = false;
    }
  } else if (t.dataset.g && G) {
    const x = G.rows[+t.closest('tr').dataset.i];
    x[t.dataset.g === 'on' ? 'on' : t.dataset.g] = t.dataset.g === 'on' ? t.checked : t.value;
    checkGenerate();
  }
});

/* ---------------- the compact bar and back-to-top on a long list ---------------- */
let miniOn = false;
function syncScroll() {
  const pipe = $('pipe');
  const on = pipe.offsetTop + pipe.offsetHeight < root.scrollTop;
  if (on !== miniOn) {
    miniOn = on;
    $('mini').classList.toggle('on', on);
    $('mini').inert = !on;
  }
  root.style.setProperty('--stick', on ? $('mini').offsetHeight + 'px' : '0px');
  $('totop').classList.toggle('on', root.scrollTop > 600);
}
root.addEventListener('scroll', syncScroll, { passive: true });
window.addEventListener('resize', syncScroll);

/* ---------------- keyboard ---------------- */
document.addEventListener('keydown', e => {
  const typing = e.target.closest('input, select, textarea, [contenteditable="true"]');
  if (e.key === 'Escape' && typing && (e.target.id === 'q' || e.target.id === 'mq')) {
    if (e.target.value) { P.q = ''; e.target.value = ''; renderBoard(); } else { e.target.blur(); }
    e.preventDefault();
    return;
  }
  if (typing || e.ctrlKey || e.metaKey || e.altKey || dlg.open) return;
  const k = e.key;
  const flash = el => { if (el) { el.classList.remove('keyflash'); void el.offsetWidth; el.classList.add('keyflash'); } };
  if (k === '/') { e.preventDefault(); const q = miniOn ? $('mq') : $('q'); q.focus(); q.select(); return; }
  if (k === '?') { e.preventDefault(); openKeys(); return; }
  if (['1', '2', '3', '4'].includes(k)) { P.step = STEPS[+k - 1][0]; P.reason = ''; render(); flash(root.querySelector(`#pipe [data-step="${P.step}"]`)); return; }
  if (k === '[' || k === ']') {
    const i = S.stages.indexOf(S.stage) + (k === ']' ? 1 : -1);
    if (i >= 0 && i < S.stages.length) loadStage(S.stages[i]);
    return;
  }
  const lower = k.toLowerCase();
  if (lower === 'v' && P.step === 'scheduled') { P.view = { list: 'week', week: 'rooms', rooms: 'list' }[P.view]; render(); return; }
  if (lower === 'a') { openAssign([...P.picked]); return; }
  if (lower === 'g') { openGenerate(); return; }
  if (lower === 'w') { openWindow(); return; }
  if (lower === 't') { ACT.top(); }
});

render();
syncScroll();
showFlash();
