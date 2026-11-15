import '../../css/milestones.css';
import * as pdfjsLib from 'pdfjs-dist';
import { el, btn, icon, fmtDate, plural, ordinal, fill, api, toast, redraw } from './common.js';

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
 * A CaPSA/TSA sees one section at a time: a row per team with one strip of
 * blocks, a block per milestone. A team's name opens a page of its own -
 * reached by a breadcrumb, not a dropdown - with every milestone and
 * deliverable, and the signed papers waiting for her check. Changing the
 * milestones themselves is Manage milestones, pages of their own. An
 * adviser sees the teams they advise or consult for, read-only.
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
  // team: the team whose page is open, if any; jump: the milestone to scroll it to.
  const S = { data: overview.current || null, filter: 'all', q: '', focusKey: null, team: null, jump: null };

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

  // A link from elsewhere - the bell, a team's own page - can name the team
  // and the paper: ?team=9&check=1 lands on that team with the scan already
  // open, rather than leaving the CaPSA/TSA to hunt for the row.
  const link = new URLSearchParams(location.search);
  const linked = (S.data?.teams || []).find(t => t.id === Number(link.get('team')));
  if (linked) S.team = linked.id;

  // Where to go once the linked paper is checked or put down - the page the
  // Check button was on. Only a page of this site.
  const returnTo = (() => {
    try {
      const to = new URL(link.get('return') || '', location.origin);
      return link.get('return') && to.origin === location.origin ? to.href : null;
    } catch (e) {
      return null;
    }
  })();

  // Opened once. Left in the address, Back or a reload would open it again.
  if (link.has('check') || link.has('return')) {
    const clean = new URL(location.href);
    clean.searchParams.delete('check');
    clean.searchParams.delete('return');
    history.replaceState(history.state, '', clean);
  }

  render();

  if (linked) {
    const wanted = linked.rows.find(r => r.id === Number(link.get('check')) && r.to_check && r.file_url);
    if (wanted) openCheck(wanted, linked, returnTo);
  }

  function render() {
    redraw(root, () => { root.replaceChildren(); draw(); }, S);
    root.querySelectorAll('table[data-export]').forEach(t => window.txPlace?.(t));
  }

  function draw() {
    const d = S.data;
    // An expert's top bar already says Milestone progress; a CaPSA/TSA's says
    // Team Management, so hers names the tracker and links to its set-up.
    if (CAPSA) {
      const top = el('div', 'ms-top');
      top.append(el('h2', 'ms-h', 'Milestone tracker'), el('span', 'ms-spacer'));
      if (URLS.manage) {
        const manage = el('a', 'ms-btn', 'Manage milestones');
        manage.href = URLS.manage;
        top.append(manage);
      }
      root.append(top);
    }

    if (!d) {
      root.append(el('div', 'ms-panel ms-empty', CAPSA
        ? 'You have no sections yet, so there are no teams to track.'
        : 'None of the teams you advise or consult for are in a section yet.'));
      return;
    }

    const team = d.checklist && d.teams.find(t => t.id === S.team);
    if (team) {
      root.append(teamPage(d, team));
      return;
    }

    root.append(d.checklist ? teamsPanel(d) : noChecklist(d));
  }

  function openTeam(t, milestone = null) {
    S.team = t.id;
    S.jump = milestone;
    S.focusKey = 'crumb';
    render();
  }

  // ── Sections ──────────────────────────────────────────────────────────

  /** Program and section pickers; the program one only when there is more than one. */
  function pickers() {
    const secs = overview.sections || [];
    const progOf = name => name.split('-')[0];
    const mine = progOf(S.data.section);
    const select = (label, options, onChange) => {
      const s = el('select', 'ms-sel');
      s.setAttribute('aria-label', label);
      s.dataset.key = 'pick:' + label;
      options.forEach(([value, text]) => {
        const o = el('option', '', text);
        o.value = value;
        s.append(o);
      });
      s.addEventListener('change', () => { S.focusKey = s.dataset.key; onChange(s.value); });
      return s;
    };

    const programs = [...new Set(secs.map(s => progOf(s.name)))];
    const prog = select('Program', programs.map(p => [p, p]), p => loadSection(secs.find(s => progOf(s.name) === p).name));
    prog.value = mine;
    const sec = select('Section', secs.filter(s => progOf(s.name) === mine)
      .map(s => [s.name, s.name + (CAPSA && s.to_check ? ` · ${s.to_check} to check` : '')]), loadSection);
    sec.value = S.data.section;

    return programs.length > 1 ? [prog, sec] : [sec];
  }

  async function loadSection(name) {
    try {
      S.data = overview.all?.[name] ?? await api('GET', fill(URLS.section, name));
      S.q = '';
      S.filter = 'all';
      S.team = null;
      syncCounts();
      render();
    } catch (e) {
      toast(e.message, null, 'error');
    }
  }

  function syncCounts() {
    const entry = (overview.sections || []).find(s => s.name === S.data?.section);
    if (!entry) return;
    entry.teams = S.data.teams.length;
    entry.to_check = S.data.teams.reduce((n, t) => n + t.rows.filter(r => r.to_check).length, 0);
  }

  // ── The teams ─────────────────────────────────────────────────────────

  function noChecklist(d) {
    const box = el('div', 'ms-panel ms-empty');
    const picks = el('div', 'ms-picks-row');
    picks.append(...pickers());
    box.append(picks);
    const program = d.section.split('-')[0];
    box.append(el('p', '', `There are no ${program} milestones yet, so the teams in ${d.section} have nothing to track.`));
    if (CAPSA && URLS.setup) {
      const setup = el('a', 'ms-btn ms-btn-gold', `Set up ${program} milestones`);
      setup.href = fill(URLS.setup, program);
      box.append(setup);
    } else {
      box.append(el('p', 'ms-hint', 'Their CaPSA/TSA has not set them up yet.'));
    }
    return box;
  }

  /** One team at a glance, counted in milestones: where it is, how many are done and late. */
  function summary(d, t) {
    const blocks = d.milestones
      .map(name => ({ name, ...counts(t.rows.filter(r => r.milestone === name)) }))
      .filter(b => b.total);
    const late = blocks.filter(b => b.overdue);
    return {
      t,
      blocks,
      late,
      overdue: late.length,
      all: counts(t.rows),
      check: CAPSA ? counts(t.rows).check : 0,
      done: blocks.filter(b => b.closed === b.total).length,
      current: blocks.find(b => b.closed < b.total),
    };
  }

  function teamsPanel(d) {
    const teams = d.teams.map(t => summary(d, t));
    const groups = [
      ['all', 'All teams', teams],
      ['behind', 'Behind', teams.filter(x => x.overdue)],
      ...(CAPSA ? [['check', 'Papers to check', teams.filter(x => x.check)]] : []),
      ['track', 'On track', teams.filter(x => !x.overdue && !x.check)],
    ];

    const panel = el('section', 'ms-panel');
    panel.setAttribute('aria-label', 'Teams in ' + d.section);

    const head = el('div', 'ms-panel-head');
    const tabs = el('div', 'ms-ftabs');
    groups.forEach(([key, label, list]) => {
      const tab = el('button', 'ms-ftab', label);
      tab.type = 'button';
      tab.dataset.key = 'filter:' + key;
      tab.setAttribute('aria-pressed', String(S.filter === key));
      tab.append(el('small', '', String(list.length)));
      tab.addEventListener('click', () => { S.filter = key; S.focusKey = 'filter:' + key; render(); });
      tabs.append(tab);
    });
    const search = el('input', 'ms-search');
    search.type = 'search';
    search.placeholder = 'Find a team';
    search.value = S.q;
    search.dataset.key = 'search';
    search.setAttribute('aria-label', 'Find a team');
    search.addEventListener('input', () => { S.q = search.value; S.focusKey = 'search'; render(); });
    head.append(tabs, el('span', 'ms-spacer'), search, ...pickers());

    const shown = (groups.find(([key]) => key === S.filter) || groups[0])[2]
      .filter(x => x.t.name.toLowerCase().includes(S.q.toLowerCase()))
      .sort((a, b) => (b.overdue - a.overdue) || (b.check - a.check) || a.t.name.localeCompare(b.t.name));

    const scroll = el('div', 'ms-scroll');
    scroll.dataset.scroll = 'track:' + d.section;
    if (shown.length) {
      const table = el('table', 'ms-table ms-ttable');
      table.dataset.export = 'Milestone tracker';
      table.dataset.exportInto = '#ms-tracker .ms-tx';
      const labels = table.createTHead().insertRow();
      ['Team', 'Current milestone', 'Requirements checked', 'Milestones completed',
        ...(CAPSA ? ['Papers to check'] : []), 'Overdue requirements', 'Action'].forEach(label => {
        const th = el('th', '', label);
        th.scope = 'col';
        labels.append(th);
      });
      const body = table.createTBody();
      shown.forEach(x => body.append(teamRow(d, x)));
      scroll.append(table);
    } else {
      scroll.append(el('div', 'ms-empty', d.teams.length ? 'No teams here.' : 'No teams in this section yet.'));
    }

    const foot = el('div', 'ms-panel-foot ms-foot-row');
    foot.append(el('span', '', `Showing ${shown.length} of ${plural(d.teams.length, 'team')}`), el('span', 'ms-tx'));
    panel.append(head, scroll, foot);
    return panel;
  }

  /** A team's line in the table; anywhere on it opens the team's page. */
  function teamRow(d, x) {
    const row = el('tr', 'ms-team-row');
    row.addEventListener('click', e => { if (!e.target.closest('button')) openTeam(x.t); });

    const name = el('button', 'ms-tname', x.t.name);
    name.type = 'button';
    name.dataset.key = 'team-' + d.section + ':' + x.t.id;
    name.addEventListener('click', () => openTeam(x.t));
    const cell = (label, ...kids) => {
      const td = el('td');
      td.dataset.label = label;
      td.append(...kids);
      return td;
    };

    // One element, so a phone's label-beside-value card keeps both lines together.
    const now = el('span', '', x.current ? x.current.name : (x.blocks.length ? 'All completed' : 'Nothing to do yet'));
    if (x.current) now.append(el('small', 'ms-tsub', `${x.current.closed} of ${plural(x.current.total, 'requirement')} checked`));

    const late = x.all.overdue ? el('span', 'ms-late', String(x.all.overdue)) : el('span', 'ms-hint', '—');
    if (x.overdue) late.title = 'In ' + x.late.map(b => b.name).join(', ');

    const papers = [el('span', 'ms-hint', '—')];
    if (x.check) {
      const first = x.t.rows.find(r => r.to_check && r.file_url);
      const check = el('button', 'ms-tcheck', 'Check ' + plural(x.check, 'paper'));
      check.type = 'button';
      check.addEventListener('click', () => (first ? openCheck(first, x.t) : openTeam(x.t)));
      papers[0] = check;
    }

    const open = btn('Open team', '', () => openTeam(x.t));
    const act = cell('Action', open);
    act.className = 'ms-tact';

    row.append(
      cell('Team', name, el('small', 'ms-secchip', d.section)),
      cell('Current milestone', now),
      cell('Requirements checked', meter(x.all.closed, x.all.total, 'checked')),
      cell('Milestones completed', meter(x.done, x.blocks.length, 'completed')),
      ...(CAPSA ? [cell('Papers to check', ...papers)] : []),
      cell('Overdue requirements', late),
      act,
    );
    return row;
  }

  /** A bar with "x / n word" beside it; the bar is just the number drawn. */
  function meter(n, total, word) {
    const wrap = el('div', 'ms-meter');
    const track = el('div', 'ms-track');
    track.setAttribute('role', 'img');
    track.setAttribute('aria-label', `${n} of ${total} ${word}`);
    const done = el('i', 'ms-track-done');
    done.style.width = (total ? Math.round(n / total * 100) : 0) + '%';
    track.append(done);
    wrap.append(track, el('small', '', `${n} / ${total} ${word}`));
    return wrap;
  }

  // ── One team's page ───────────────────────────────────────────────────

  /**
   * A team on a page of its own, reached from the table and left by the
   * breadcrumb. Every milestone and every item is on show: nothing to open.
   */
  function teamPage(d, t) {
    const panel = el('section', 'ms-panel');
    panel.setAttribute('aria-label', t.name);

    const head = el('div', 'ms-panel-head');
    const crumb = el('nav', 'ms-crumb');
    crumb.setAttribute('aria-label', 'Breadcrumb');
    const back = el('button', 'ms-crumb-link', 'All teams in ' + d.section);
    back.type = 'button';
    back.dataset.key = 'crumb';
    back.addEventListener('click', () => {
      S.team = null;
      S.focusKey = 'team-' + d.section + ':' + t.id;
      render();
    });
    crumb.append(back, el('span', 'ms-crumb-sep', '›'), el('b', '', t.name));
    head.append(crumb, el('span', 'ms-spacer'), progress(counts(t.rows)));

    const scroll = el('div', 'ms-scroll');
    scroll.dataset.scroll = 'team:' + d.section + ':' + t.id;
    const inner = el('div', 'ms-inner ms-page');

    const people = el('div', 'ms-inner-head');
    people.append('Adviser ', el('b', '', t.adviser || 'not chosen yet'), ' · Consultant ', el('b', '', t.consultant || 'not chosen yet'));
    if (t.members.length) people.append(' · ' + t.members.join(', '));
    inner.append(people);

    const locked = t.locked || {};

    d.milestones.forEach(name => {
      const rows = t.rows.filter(r => r.milestone === name);
      const pc = counts(rows);
      const box = el('section', 'ms-mgroup');
      box.dataset.milestone = name;

      const dues = rows.map(r => r.due).filter(Boolean).sort();
      const [status, tone] = name in locked
        ? [locked[name] ? 'Waits for ' + locked[name] : 'Not open yet', '']
        : pc.overdue ? [pc.overdue + ' overdue', 'ms-t-red']
          : pc.check ? [pc.check + ' to check', 'ms-t-amb']
            : pc.total && pc.closed === pc.total ? ['Complete', 'ms-t-grn'] : ['', ''];

      const row = el('div', 'ms-mrow ms-mhead');
      row.append(
        el('h3', '', name),
        el('span', tone, status),
        el('span', '', `${pc.closed} of ${pc.total} closed`),
        el('span', '', !dues.length ? 'No due date'
          : dues[0] === dues[dues.length - 1] ? 'Due ' + fmtDate(dues[0])
            : `Due ${fmtDate(dues[0])} – ${fmtDate(dues[dues.length - 1])}`),
      );
      box.append(row);
      rows.forEach(r => box.append(item(r, t)));
      inner.append(box);
    });

    scroll.append(inner);
    panel.append(head, scroll, el('div', 'ms-panel-foot', plural(d.milestones.length, 'milestone')));

    // Opened from one milestone's count: start there.
    if (S.jump) {
      const jump = S.jump;
      S.jump = null;
      requestAnimationFrame(() => {
        const target = [...scroll.querySelectorAll('.ms-mgroup')].find(g => g.dataset.milestone === jump);
        if (target) scroll.scrollTop = target.offsetTop - scroll.offsetTop;
      });
    }

    return panel;
  }

  function item(r, t) {
    const row = el('div', 'ms-item');
    const state = stateIcon(r);
    const title = el('span', 'ms-item-title', r.title);
    if (r.note) title.append(el('span', 'ms-note', `“${r.note}”`));
    // Its own place in the milestone's order; a locked milestone says so once, above.
    if (r.waits_for && !(r.milestone in (t.locked || {}))) title.append(el('span', 'ms-note ms-wait', 'Waits for ' + r.waits_for));
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
      case 'capsa': return CAPSA ? 'Waiting for your check' : "Waiting for the CaPSA/TSA's check";
      case 'title_review': return CAPSA ? 'Titles waiting for your recommendation' : "Titles waiting for the CaPSA/TSA's recommendation";
      case 'team': return r.note ? 'Sent back' : ordinal(Math.max(1, r.revision)) + ' revision requested';
      case 'signed_copy': return 'Approved, signed copy not uploaded';
      case 'adviser': return 'With the CaPA';
      case 'consultant':
      case 'grammarian':
      case 'panel': return 'With the ' + r.waiting_on;
      case 'subject_adviser': return CAPSA ? 'Waiting for your approval' : "Waiting for the CaPSA/TSA's approval";
      case 'dean': return "With the Dean's Office";
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
    const who = CAPSA ? 'you check' : 'the CaPSA/TSA checks';
    const signer = (r.role === 'consultant' ? t.consultant : t.adviser) || 'the ' + (r.role || 'adviser');
    if (r.by_recommendation) return `Closed when ${CAPSA ? 'you recommend' : 'the CaPSA/TSA recommends'} one of the team's titles`;
    if (r.by_archive) return `Closes when ${CAPSA ? 'you publish' : 'the CaPSA/TSA publishes'} it in the Archive`;
    if (r.sends_to?.length) return 'Closes when approved by: ' + r.sends_to.join(', ');
    if (r.kind === 'bluebook') return "Closes when the Dean's Office accepts it";
    return {
      form: `Closed when ${who} the signed form`,
      approval: `Closed when ${who} the CLF signed by ${signer}`,
      document: 'Closes when the adviser and the consultant both approve it',
      defense: 'Closes when the panel records a pass',
      custom: CAPSA ? 'Ticked by you' : 'Ticked by the CaPSA/TSA',
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

  // True once saved, so a caller can move on; a refusal stays here to be read.
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
      return true;
    } catch (e) {
      toast(e.message, null, 'error');
      return false;
    }
  }

  function replaceRow(t, row) {
    const at = t.rows.findIndex(x => x.id === row.id);
    if (at >= 0) t.rows[at] = row;
    syncCounts();
  }

  // returnTo: the page the CaPSA/TSA came from. Deciding, or closing without
  // deciding, takes her back there instead of leaving her on the tracker.
  function openCheck(r, t, returnTo = null) {
    const dialog = el('dialog', 'ms-dialog ms-check');
    let deciding = false;
    const decide = async (body, message) => {
      deciding = true;
      dialog.close();
      if (await setCheck(r, t, body, message) && returnTo) location.replace(returnTo);
    };
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
    if (r.file_is_other) {
      // Nothing to draw: the link below saves the file.
    } else if (r.file_is_image) {
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
    const newTab = el('a', 'ms-scan-open', r.file_is_other ? 'Download the file' : 'Open in a new tab');
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
      btn('Complete', 'ms-btn-gold', () => decide({ status: 'complete' }, `${r.title} closed for ${t.name}.`)),
      btn('Send back', 'ms-btn-warn', sendBack),
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
        btn('Send back', 'ms-btn-warn', () => {
          if (!note.value.trim()) return note.focus();
          decide({ status: 'sent_back', note: note.value.trim() }, `${t.name} was asked to fix their ${r.title}.`);
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
    dialog.addEventListener('close', () => {
      dialog.remove();
      if (!deciding && returnTo) location.replace(returnTo);
    });
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
}
