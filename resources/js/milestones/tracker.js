import '../../css/milestones.css';
import * as pdfjsLib from 'pdfjs-dist';
import { el, btn, icon, fmtDate, plural, ordinal, fill, api, toast, redraw } from './common.js';
import { openEditor } from './editor.js';

pdfjsLib.GlobalWorkerOptions.workerSrc = new URL('pdfjs-dist/build/pdf.worker.min.mjs', import.meta.url).toString();

/**
 * A scanned PDF drawn page by page onto canvases, the width of the box. No
 * iframe: the browser's own viewer brought its toolbar, and sometimes a
 * download prompt instead of the paper.
 */
async function drawPdf(box, url, label) {
  box.replaceChildren(el('div', 'ms-scan-note', 'Loading the scan…'));
  try {
    const pdf = await pdfjsLib.getDocument({ url, withCredentials: true }).promise;
    box.replaceChildren();
    const width = Math.max(320, box.clientWidth - 8);
    const ratio = window.devicePixelRatio || 1;
    for (let n = 1; n <= pdf.numPages; n++) {
      const page = await pdf.getPage(n);
      const viewport = page.getViewport({ scale: (width / page.getViewport({ scale: 1 }).width) * ratio });
      const canvas = el('canvas');
      canvas.width = viewport.width;
      canvas.height = viewport.height;
      canvas.setAttribute('role', 'img');
      canvas.setAttribute('aria-label', `${label}, page ${n} of ${pdf.numPages}`);
      box.append(canvas);
      await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
    }
  } catch {
    box.replaceChildren(el('div', 'ms-scan-note', 'This file cannot be shown here. Open it in a new tab to check it.'));
  }
}

/*
 * The milestone tracker.
 *
 * A CaPSA sees one section at a time: its teams in a table, each opening to
 * its milestones and their deliverables, with the signed papers waiting for
 * her check. An adviser sees the teams they advise or consult for, read-only.
 * Only the lists scroll, never the page.
 */

const root = document.getElementById('ms-tracker');

if (root) {
  start(root);
}

function start(root) {
  const CAPSA = root.dataset.mode === 'capsa';
  const URLS = JSON.parse(root.dataset.urls || '{}');
  const overview = JSON.parse(root.dataset.state || '{}');
  const S = { data: overview.current || null, filter: 'all', q: '', focusKey: null };

  // One dropdown open at a time: a team, and one milestone inside it.
  const teamOpen = new Set();
  const phaseOpen = new Set();
  const phaseSeen = new Set();

  const closed = r => r.state === 'done' || r.state === 'done_late';

  const fit = () => root.style.setProperty(
    '--ms-h',
    Math.max(460, window.innerHeight - root.getBoundingClientRect().top - 26) + 'px',
  );
  fit();
  window.addEventListener('resize', fit);
  // The page shows a loading skeleton first; measure again once the real content is revealed.
  const realContent = document.getElementById('real-content');
  if (realContent) new MutationObserver(fit).observe(realContent, { attributes: true, attributeFilter: ['style'] });
  render();

  function render() {
    redraw(root, () => { root.replaceChildren(); draw(); }, S);
  }

  function draw() {
    const d = S.data;
    const top = el('div', 'ms-top');
    top.append(el('h2', 'ms-h', CAPSA ? 'Milestones' : 'Milestone progress'));
    if (CAPSA) top.append(btn('Edit milestones', 'ms-btn-gold', edit));
    top.append(el('span', 'ms-spacer'));
    if ((overview.sections || []).length) top.append(sectionTabs());
    root.append(top);

    if (!d) {
      root.append(el('div', 'ms-panel ms-empty', CAPSA
        ? 'You have no sections yet, so there are no teams to track.'
        : 'None of the teams you advise or consult for are in a section yet.'));
      return;
    }

    root.append(band(d));
    root.append(d.checklist ? teamsPanel(d) : noChecklist(d));
  }

  function edit() {
    openEditor({
      urls: URLS,
      checklistId: S.data?.checklist?.id ?? null,
      onClose: () => { if (S.data) loadSection(S.data.section); },
    });
  }

  // ── Sections ──────────────────────────────────────────────────────────

  function sectionTabs() {
    const nav = el('div', 'ms-choices');
    nav.setAttribute('role', 'tablist');
    nav.setAttribute('aria-label', 'Section');
    nav.append(el('span', 'ms-lab', CAPSA ? 'Your sections' : 'Sections'));

    overview.sections.forEach(s => {
      const b = el('button', 'ms-choice');
      b.type = 'button';
      b.setAttribute('role', 'tab');
      b.setAttribute('aria-selected', String(s.name === S.data?.section));
      b.append(el('b', '', s.name), el('small', '', plural(s.teams, 'team')));
      if (CAPSA && s.to_check) {
        const badge = el('span', 'ms-badge', String(s.to_check));
        badge.title = plural(s.to_check, 'signed paper') + ' to check';
        b.append(badge);
      }
      b.setAttribute('aria-label', `${s.name}, ${plural(s.teams, 'team')}` + (CAPSA ? `, ${plural(s.to_check, 'signed paper')} to check` : ''));
      b.addEventListener('click', () => loadSection(s.name));
      nav.append(b);
    });

    return nav;
  }

  async function loadSection(name) {
    try {
      S.data = overview.all?.[name] ?? await api('GET', fill(URLS.section, name));
      S.q = '';
      syncCounts();
      render();
    } catch (e) {
      toast(e.message);
    }
  }

  function syncCounts() {
    const entry = (overview.sections || []).find(s => s.name === S.data?.section);
    if (!entry) return;
    entry.teams = S.data.teams.length;
    entry.to_check = S.data.teams.reduce((n, t) => n + t.rows.filter(r => r.to_check).length, 0);
  }

  // ── Section summary ───────────────────────────────────────────────────

  function band(d) {
    const all = d.teams.flatMap(t => t.rows);
    const box = el('div', 'ms-band');
    const now = el('div', 'ms-now');
    now.append(
      el('small', '', CAPSA ? 'You are viewing section' : 'Section'),
      el('b', '', d.section),
      el('span', '', (d.checklist ? `${d.checklist.program} ${d.checklist.title} · ` : '') + plural(d.teams.length, 'team')),
    );
    box.append(now);

    if (d.checklist) {
      const stats = el('div', 'ms-stats');
      const stat = (cls, n, label) => {
        const s = el('div', ('ms-stat ' + cls).trim());
        s.append(el('b', '', String(n)), el('span', '', label));
        stats.append(s);
      };
      stat('', (all.length ? Math.round(all.filter(closed).length / all.length * 100) : 0) + '%', 'closed');
      stat('ms-stat-red', d.teams.filter(t => t.rows.some(r => r.state === 'overdue')).length, 'teams with overdue items');
      if (CAPSA) stat('ms-stat-gold', all.filter(r => r.to_check).length, 'signed papers to check');
      stat('', d.teams.filter(t => !t.rows.some(r => r.state === 'overdue' || r.to_check)).length, 'teams on track');
      box.append(stats);
    }

    return box;
  }

  function noChecklist(d) {
    const box = el('div', 'ms-panel ms-empty');
    const program = d.section.split('-')[0];
    box.append(el('p', '', `There is no ${program} checklist yet, so the teams in ${d.section} have nothing to track.`));
    if (CAPSA) {
      box.append(btn(`Set up the ${program} checklist`, 'ms-btn-gold', edit));
    } else {
      box.append(el('p', 'ms-hint', 'Their CaPSA has not set one up yet.'));
    }
    return box;
  }

  // ── Teams table ───────────────────────────────────────────────────────

  function teamsPanel(d) {
    const panel = el('section', 'ms-panel');
    panel.setAttribute('aria-label', 'Teams in ' + d.section);

    const head = el('div', 'ms-panel-head');
    head.append(el('b', '', d.checklist.title), el('span', 'ms-spacer'));
    [['all', 'All'], ['overdue', 'Overdue'], ...(CAPSA ? [['check', 'Papers to check']] : [])].forEach(([key, label]) => {
      const chip = el('button', 'ms-chip', label);
      chip.type = 'button';
      chip.setAttribute('aria-pressed', String(S.filter === key));
      chip.addEventListener('click', () => { S.filter = key; render(); });
      head.append(chip);
    });
    const search = el('input', 'ms-search');
    search.type = 'search';
    search.placeholder = 'Find a team';
    search.value = S.q;
    search.dataset.key = 'search';
    search.setAttribute('aria-label', 'Find a team');
    search.addEventListener('input', () => { S.q = search.value; S.focusKey = 'search'; render(); });
    head.append(search);

    const summaries = d.teams.map(t => ({ t, c: counts(t.rows) }));
    const shown = summaries
      .filter(({ c }) => S.filter === 'all' || (S.filter === 'overdue' ? c.overdue : c.check))
      .filter(({ t }) => t.name.toLowerCase().includes(S.q.toLowerCase()))
      .sort((a, b) => (b.c.overdue - a.c.overdue) || (b.c.check - a.c.check) || a.t.name.localeCompare(b.t.name));

    const scroll = el('div', 'ms-scroll');
    scroll.dataset.scroll = 'track:' + d.section;
    const foot = el('div', 'ms-panel-foot', `${plural(shown.length, 'team')} · click a team to see its milestones`);
    panel.append(head, scroll, foot);

    const table = el('table', 'ms-table');
    const headRow = el('tr');
    headRow.append(el('th', 'ms-w-team', 'Team'));
    d.milestones.forEach(name => headRow.append(el('th', '', name)));
    headRow.append(el('th', 'ms-w-total', 'Total'), el('th', 'ms-w-st', ''));
    const thead = el('thead');
    thead.append(headRow);
    table.append(thead);
    scroll.append(table);

    if (!shown.length) {
      const body = el('tbody');
      const tr = el('tr');
      const td = el('td', 'ms-none', d.teams.length ? 'No teams match.' : 'No teams in this section yet.');
      td.colSpan = d.milestones.length + 3;
      tr.append(td);
      body.append(tr);
      table.append(body);
    }

    shown.forEach(({ t, c }) => table.append(teamBody(d, t, c)));

    // An opened team's row sticks just under the column names.
    requestAnimationFrame(() => scroll.style.setProperty('--ms-thead', thead.offsetHeight + 'px'));

    return panel;
  }

  /** A team's own tbody, so its sticky row lets go once its list ends. */
  function teamBody(d, t, c) {
    const key = d.section + ':' + t.id;
    const open = teamOpen.has(key);
    const body = el('tbody');

    const tr = el('tr', 'ms-team-row' + (open ? ' ms-open' : ''));
    const first = el('td');
    const expander = el('button', 'ms-expander');
    expander.type = 'button';
    expander.dataset.key = 'team-' + key;
    expander.setAttribute('aria-expanded', String(open));
    expander.append(icon('chev', 'ms-chevi'), el('span', '', t.name));
    first.append(expander);
    tr.append(first);

    d.milestones.forEach(name => {
      const pc = counts(t.rows.filter(r => r.milestone === name));
      const full = pc.total > 0 && pc.closed === pc.total;
      const cls = pc.overdue ? 'ms-cell-late' : full ? 'ms-cell-full' : pc.handed ? 'ms-cell-wait' : pc.closed ? 'ms-cell-part' : '';
      const td = el('td');
      const cell = el('span', ('ms-cell ' + cls).trim(), (pc.overdue ? '! ' : full ? '✓ ' : '') + `${pc.closed}/${pc.total}`);
      cell.title = `${name}: ${pc.closed} closed, ${pc.handed} waiting, ${pc.overdue} overdue. Click to open it.`;
      // Clicking a count opens this team at that milestone.
      td.addEventListener('click', e => {
        e.stopPropagation();
        teamOpen.clear();
        teamOpen.add(key);
        phaseSeen.add(key);
        phaseOpen.clear();
        phaseOpen.add(key + '|' + name);
        render();
      });
      td.append(cell);
      tr.append(td);
    });

    const total = el('td');
    total.append(progress(c));
    tr.append(total);

    const status = el('td');
    status.append(c.overdue
      ? pill('ms-pill-red', c.overdue + ' overdue')
      : c.check ? pill('ms-pill-amb', c.check + ' to check') : pill('ms-pill-grn', 'On track'));
    tr.append(status);

    tr.addEventListener('click', () => { openOnly(teamOpen, key); S.focusKey = 'team-' + key; render(); });
    body.append(tr);

    if (open) body.append(detail(d, t, key));

    return body;
  }

  function detail(d, t, key) {
    const tr = el('tr', 'ms-detail-row');
    const td = el('td');
    td.colSpan = d.milestones.length + 3;
    const inner = el('div', 'ms-inner');

    const people = el('div', 'ms-inner-head');
    people.append('Adviser ', el('b', '', t.adviser || 'not chosen yet'), ' · Consultant ', el('b', '', t.consultant || 'not chosen yet'));
    if (t.members.length) people.append(' · ' + t.members.join(', '));
    inner.append(people);

    const groups = d.milestones.map(name => ({ name, rows: t.rows.filter(r => r.milestone === name) }));

    // First time a team opens: the milestone with a paper to check, or else the first one not finished.
    if (!phaseSeen.has(key)) {
      phaseSeen.add(key);
      const first = groups.find(g => g.rows.some(r => r.to_check)) || groups.find(g => g.rows.some(r => !closed(r)));
      if (first) openOnly(phaseOpen, key + '|' + first.name);
    }

    groups.forEach(g => {
      const phaseKey = key + '|' + g.name;
      const open = phaseOpen.has(phaseKey);
      const pc = counts(g.rows);
      const box = el('div', 'ms-mgroup');

      const row = el('button', 'ms-mrow');
      row.type = 'button';
      row.dataset.key = 'ph-' + phaseKey;
      row.setAttribute('aria-expanded', String(open));
      const dues = g.rows.map(r => r.due).filter(Boolean).sort();
      row.append(
        icon('chev', 'ms-chevi'),
        el('b', '', g.name),
        pc.overdue ? pill('ms-pill-red', pc.overdue + ' overdue')
          : pc.check ? pill('ms-pill-amb', pc.check + ' to check')
            : pc.total && pc.closed === pc.total ? pill('ms-pill-grn', 'Done') : el('span'),
        el('span', '', `${pc.closed} of ${pc.total} closed`),
        el('span', '', !dues.length ? 'No due date'
          : dues[0] === dues[dues.length - 1] ? 'Due ' + fmtDate(dues[0])
            : `Due ${fmtDate(dues[0])} – ${fmtDate(dues[dues.length - 1])}`),
      );
      row.addEventListener('click', () => { openOnly(phaseOpen, phaseKey); S.focusKey = 'ph-' + phaseKey; render(); });
      box.append(row);

      if (open) g.rows.forEach(r => box.append(item(r, t)));
      inner.append(box);
    });

    td.append(inner);
    tr.append(td);
    return tr;
  }

  function item(r, t) {
    const row = el('div', 'ms-item');
    const state = stateIcon(r);
    const title = el('span', 'ms-item-title', r.title);
    if (r.note) title.append(el('span', 'ms-note', `“${r.note}”`));
    const when = r.due ? (closed(r) ? 'was due ' : 'due ') + fmtDate(r.due) : 'no due date';
    const act = el('span', 'ms-item-act');
    const action = actionFor(r, t);
    if (action) act.append(action);
    row.append(icon(state, 'ms-st-' + state, word(r)), title, el('span', '', `${word(r)} · ${when}`), el('span', 'ms-who', closer(r, t)), act);
    return row;
  }

  // ── Words ─────────────────────────────────────────────────────────────

  function word(r) {
    if (r.state === 'done') return 'Closed';
    if (r.state === 'done_late') return 'Closed late';
    if (r.state === 'overdue') return 'Overdue';
    if (r.state === 'not_started') return 'Open';
    switch (r.waiting_on) {
      case 'capsa': return CAPSA ? 'Waiting for your check' : "Waiting for the CaPSA's check";
      case 'team': return r.note ? 'Sent back' : ordinal(Math.max(1, r.revision)) + ' revision requested';
      case 'signed_copy': return 'Approved, signed copy not uploaded';
      case 'adviser':
      case 'consultant': return 'With the ' + r.waiting_on;
      case 'redefense': return r.revision > 1 ? `Re-defense needed (${ordinal(r.revision)} time)` : 'Re-defense needed';
      default: return r.kind === 'defense' && r.revision ? 'Re-defense scheduled' : 'Scheduled';
    }
  }

  function stateIcon(r) {
    if (closed(r)) return 'done';
    if (r.state === 'overdue') return 'overdue';
    return r.state === 'handed_in' ? 'handed' : 'open';
  }

  function closer(r, t) {
    const who = CAPSA ? 'you check' : 'the CaPSA checks';
    const signer = (r.role === 'consultant' ? t.consultant : t.adviser) || 'the ' + (r.role || 'adviser');
    return {
      form: `Closed when ${who} the signed form`,
      approval: `Closed when ${who} the CLF signed by ${signer}`,
      document: 'Closes when the team uploads it',
      defense: 'Closes when the panel records a pass',
      custom: CAPSA ? 'Ticked by you' : 'Ticked by the CaPSA',
    }[r.kind] || '';
  }

  // ── Checking a signed paper ───────────────────────────────────────────

  function actionFor(r, t) {
    if (!CAPSA || !r.check_url) return null;
    if (r.to_check && r.file_url) {
      return btn(r.kind === 'approval' ? 'Check CLF' : 'Check form', 'ms-btn-gold', e => { e.stopPropagation(); openCheck(r, t); });
    }
    if (r.kind === 'custom' && !closed(r)) {
      return btn('Mark done', '', () => setCheck(r, t, { status: 'complete' }, `Marked “${r.title}” done for ${t.name}.`));
    }
    return null;
  }

  async function setCheck(r, t, body, message) {
    const before = r.check;
    try {
      const row = await api('PUT', r.check_url, { ...body, file_id: r.file_id });
      replaceRow(t, row);
      render();
      toast(message, async () => {
        const back = before
          ? await api('PUT', r.check_url, { status: before.status, note: before.note, file_id: r.file_id })
          : await api('DELETE', r.check_url);
        replaceRow(t, back);
        render();
      });
    } catch (e) {
      toast(e.message);
    }
  }

  function replaceRow(t, row) {
    const at = t.rows.findIndex(x => x.id === row.id);
    if (at >= 0) t.rows[at] = row;
    syncCounts();
  }

  function openCheck(r, t) {
    const dialog = el('dialog', 'ms-dialog ms-check');
    dialog.setAttribute('aria-label', `Check ${r.title} for ${t.name}`);

    const head = el('div', 'ms-ck-head');
    const who = el('div');
    who.append(el('b', '', t.name), el('small', '', ` ${S.data.section} · ${r.milestone}`));
    const close = el('button', 'ms-x', '×');
    close.type = 'button';
    close.setAttribute('aria-label', 'Close');
    close.addEventListener('click', () => dialog.close());
    head.append(who, close);

    const scan = el('div', 'ms-scan');
    if (r.file_is_image) {
      const img = el('img');
      img.src = r.file_url;
      img.alt = 'Uploaded scan of ' + r.title;
      scan.append(img);
    } else {
      const pages = el('div', 'ms-scan-pages');
      scan.append(pages);
      // After the window opens, so the pages are drawn at its real width.
      requestAnimationFrame(() => drawPdf(pages, r.file_url, 'Uploaded scan of ' + r.title));
    }
    const newTab = el('a', 'ms-scan-open', 'Open in a new tab');
    newTab.href = r.file_url;
    newTab.target = '_blank';
    newTab.rel = 'noopener';
    scan.append(newTab);

    const side = el('div', 'ms-ck-side');
    const signer = r.kind === 'approval' ? (r.role === 'consultant' ? t.consultant : t.adviser) : null;
    side.append(
      el('h3', '', r.title),
      el('div', 'ms-from', [
        r.due ? 'Due ' + fmtDate(r.due) : null,
        signer ? 'Must be signed by ' + signer : null,
        r.approved ? 'Draft approved in Document Review on ' + fmtDate(r.approved) : null,
      ].filter(Boolean).join(' · ')),
      el('p', 'ms-q', 'Is this paper complete?'),
    );
    const checks = el('ul');
    ['Everyone who should sign has signed', 'The team and title match', 'The whole page can be read']
      .forEach(text => checks.append(el('li', '', text)));
    const picks = el('div', 'ms-picks');
    side.append(checks, picks);

    const ask = () => picks.replaceChildren(
      btn("Yes, it's complete", 'ms-btn-gold', () => {
        dialog.close();
        setCheck(r, t, { status: 'complete' }, `${r.title} closed for ${t.name}.`);
      }),
      btn('No, send it back', 'ms-btn-warn', sendBack),
    );

    const sendBack = () => {
      const note = el('textarea');
      note.id = 'ms-check-note';
      note.maxLength = 1000;
      note.placeholder = 'e.g. The adviser has not signed yet.';
      const label = el('label', 'ms-label', 'What should the team fix?');
      label.htmlFor = note.id;
      picks.replaceChildren(
        label,
        note,
        btn('Send back with this note', 'ms-btn-warn', () => {
          if (!note.value.trim()) return note.focus();
          dialog.close();
          setCheck(r, t, { status: 'sent_back', note: note.value.trim() }, `${t.name} was asked to fix their ${r.title}.`);
        }),
        btn('Cancel', '', ask),
      );
      note.focus();
    };

    ask();

    const body = el('div', 'ms-ck-body');
    body.append(scan, side);
    dialog.append(head, body);
    document.body.append(dialog);
    dialog.addEventListener('close', () => dialog.remove());
    dialog.showModal();
  }

  // ── Small helpers ─────────────────────────────────────────────────────

  function counts(rows) {
    return {
      total: rows.length,
      closed: rows.filter(closed).length,
      handed: rows.filter(r => r.state === 'handed_in').length,
      overdue: rows.filter(r => r.state === 'overdue').length,
      check: rows.filter(r => r.to_check).length,
    };
  }

  function progress(c) {
    const wrap = el('div', 'ms-prog');
    const pct = c.total ? Math.round(c.closed / c.total * 100) : 0;
    wrap.title = pct + '% closed';
    const track = el('div', 'ms-track');
    track.setAttribute('role', 'progressbar');
    track.setAttribute('aria-valuenow', String(pct));
    track.setAttribute('aria-valuemin', '0');
    track.setAttribute('aria-valuemax', '100');
    const done = el('i', 'ms-track-done');
    done.style.width = pct + '%';
    const waiting = el('i', 'ms-track-wait');
    waiting.style.width = (c.total ? c.handed / c.total * 100 : 0) + '%';
    track.append(done, waiting);
    wrap.append(track, el('span', '', `${c.closed}/${c.total}`));
    return wrap;
  }

  function pill(cls, text) {
    return el('span', 'ms-pill ' + cls, text);
  }

  /** Opening one closes whichever was open; opening the open one closes it. */
  function openOnly(set, key) {
    const was = set.has(key);
    set.clear();
    if (!was) set.add(key);
  }
}
