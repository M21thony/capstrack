import { el, btn, icon, fmtDate, plural, fill, api, toast, say, redraw, confirmRemove, confirmBox } from './common.js';

/*
 * The Edit milestones window.
 *
 * One list: numbered milestones that open to their deliverables. Drag either
 * by its grip, or focus the grip and press the up/down arrows, to reorder.
 * Names, types and due dates are edited in place and kept aside until Save is
 * pressed - saving as you typed once stored a half-typed year. Tick
 * deliverables to change several together. Archive hides something from teams
 * until it is restored; Remove deletes it, with Undo on the message after.
 */

// One Type choice also says who signs a CLF.
const TYPES = [
  ['form', 'Signed form'],
  ['document', 'Upload'],
  ['approval:adviser', 'Signed CLF · Adviser'],
  ['approval:consultant', 'Signed CLF · Consultant'],
  ['defense', 'Defense'],
  ['custom', 'Task'],
];
const typeOf = d => d.kind === 'approval' ? 'approval:' + (d.role || 'adviser') : d.kind;
const typeLabel = d => (TYPES.find(t => t[0] === typeOf(d)) || [null, d.kind])[1];
const splitType = value => {
  const [kind, role] = value.split(':');
  return { kind, role: role || null };
};

// The same range the server accepts, so the date picker cannot offer a year like 200.
const DATE_MIN = '2000-01-01';
const DATE_MAX = '2100-12-31';

function dateInput(cls = '') {
  const input = el('input', cls);
  input.type = 'date';
  input.min = DATE_MIN;
  input.max = DATE_MAX;
  return input;
}

export function openEditor({ urls, checklistId = null, onClose = null }) {
  const E = {
    list: null,
    cl: null,
    tab: 'active',
    picked: new Set(),
    collapsed: new Set(),
    drag: null,
    focusKey: null,
    savedText: '',
    // Edits typed but not saved yet: deliverable id -> changed fields, milestone id -> new name.
    pending: new Map(),
    pendingNames: new Map(),
  };
  let changed = false;

  const dialog = el('dialog', 'ms-dialog ms-editor');
  dialog.setAttribute('aria-label', 'Edit milestones');
  const head = el('div', 'ms-ed-head');
  const body = el('div', 'ms-ed-body');
  body.append(el('div', 'ms-empty', 'Loading…'));
  dialog.append(head, body);
  document.body.append(dialog);
  dialog.addEventListener('close', () => {
    dialog.remove();
    if (changed && onClose) onClose();
  });
  // Escape asks first when there are unsaved edits.
  dialog.addEventListener('cancel', e => {
    if (pendingCount()) {
      e.preventDefault();
      leave(() => dialog.close());
    }
  });
  dialog.showModal();
  load(checklistId);

  // ── Loading and saving ────────────────────────────────────────────────

  async function load(id) {
    try {
      E.list = await api('GET', urls.checklists);
      const target = id ?? E.list.checklists[0]?.id ?? null;
      E.cl = target ? await api('GET', fill(urls.checklist, target)) : null;
      E.picked.clear();
      render();
    } catch (e) {
      toast(e.message);
    }
  }

  async function reload() {
    E.cl = await api('GET', fill(urls.checklist, E.cl.id));
  }

  function saved() {
    changed = true;
    E.savedText = '✓ Saved just now';
    const note = head.querySelector('.ms-saved');
    if (note) note.textContent = E.savedText;
  }

  /** Run a change, redraw, and offer Undo. A failure reloads what the server has. */
  async function run(action, message, undo = null) {
    try {
      await action();
      saved();
      render();
      if (message) {
        toast(message, undo ? async () => { await undo(); saved(); render(); } : null);
      }
    } catch (e) {
      toast(e.message);
      try { await reload(); } catch { /* keep what is shown */ }
      render();
    }
  }

  const pendingCount = () => E.pending.size + E.pendingNames.size;

  /** Do something that would lose unsaved edits - after asking, if there are any. */
  async function leave(action) {
    const n = pendingCount();
    if (n && !await confirmBox('Discard unsaved changes?', `You have ${plural(n, 'unsaved change')}. They will be lost.`, 'Discard changes', 'x')) {
      return;
    }
    E.pending.clear();
    E.pendingNames.clear();
    action();
  }

  const orderUrl = () => fill(urls.order, E.cl.id);
  const live = () => E.cl.milestones.filter(m => !m.archived);
  const orderBody = () => ({
    milestones: E.cl.milestones.map(m => ({ id: m.id, deliverables: m.deliverables.map(d => d.id) })),
  });

  function where(id) {
    for (const m of E.cl.milestones) {
      const i = m.deliverables.findIndex(d => d.id === id);
      if (i >= 0) return { m, i, d: m.deliverables[i] };
    }
    return null;
  }

  function moveDeliverable(id, targetId, refId, after) {
    const from = where(id);
    from.m.deliverables.splice(from.i, 1);
    const target = E.cl.milestones.find(m => m.id === targetId);
    const at = refId == null
      ? target.deliverables.length
      : target.deliverables.findIndex(d => d.id === refId) + (after ? 1 : 0);
    target.deliverables.splice(at, 0, from.d);
  }

  function moveMilestone(id, refId, after) {
    const [moved] = E.cl.milestones.splice(E.cl.milestones.findIndex(m => m.id === id), 1);
    E.cl.milestones.splice(E.cl.milestones.findIndex(m => m.id === refId) + (after ? 1 : 0), 0, moved);
  }

  /** Save a new order. Shown at once; the server's answer replaces it. */
  async function persistOrder(previous, message, undoable) {
    const focus = E.focusKey;
    render();
    try {
      E.cl = await api('PUT', orderUrl(), orderBody());
      saved();
      E.focusKey = focus;
      render();
      if (undoable) {
        toast(message, async () => { E.cl = await api('PUT', orderUrl(), previous); saved(); render(); });
      } else if (message) {
        say(message);
      }
    } catch (e) {
      toast(e.message);
      await load(E.cl.id);
    }
  }

  /** Save the edits typed on these deliverables and milestone names. */
  async function saveEdits(deliverableIds = [...E.pending.keys()], milestoneIds = [...E.pendingNames.keys()]) {
    const rows = deliverableIds.filter(id => E.pending.has(id) && where(id));
    const names = milestoneIds.filter(id => E.pendingNames.has(id));
    if (!rows.length && !names.length) return;

    const before = rows.map(id => {
      const { d } = where(id);
      return { id, title: d.title, kind: d.kind, role: d.role, due_date: d.due };
    });
    const oldNames = names.map(id => ({ id, name: E.cl.milestones.find(m => m.id === id).name }));

    try {
      for (const id of names) {
        const res = await api('PATCH', fill(urls.milestone, id), { name: E.pendingNames.get(id) });
        E.cl.milestones.find(m => m.id === id).name = res.name;
        E.pendingNames.delete(id);
      }
      for (const id of rows) {
        const res = await api('PATCH', fill(urls.deliverable, id), E.pending.get(id));
        Object.assign(where(id).d, { title: res.title, kind: res.kind, role: res.role, due: res.due });
        E.pending.delete(id);
      }
    } catch (e) {
      // Whatever saved stays saved; what the server refused stays in the field to fix.
      toast(e.message);
      render();
      return;
    }

    saved();
    render();
    const count = rows.length + names.length;
    toast(count === 1 ? 'Saved.' : `Saved ${plural(count, 'change')}.`, async () => {
      for (const { id, name } of oldNames) await api('PATCH', fill(urls.milestone, id), { name });
      if (before.length) await api('PATCH', urls.deliverables, { items: before });
      await reload();
      saved();
      render();
    });
  }

  function render() {
    redraw(dialog, () => { drawHead(); drawBody(); }, E);
  }

  // ── Header ────────────────────────────────────────────────────────────

  function drawHead() {
    head.replaceChildren(el('h2', '', 'Edit milestones'));

    const lists = el('div', 'ms-choices');
    lists.setAttribute('role', 'group');
    lists.setAttribute('aria-label', 'Checklist');
    (E.list?.checklists || []).forEach(c => {
      const b = el('button', 'ms-choice');
      b.type = 'button';
      b.setAttribute('aria-pressed', String(E.cl?.id === c.id));
      b.append(el('b', '', `${c.program} · ${c.title}`), el('small', '', c.used_by));
      b.addEventListener('click', () => leave(() => { E.tab = 'active'; load(c.id); }));
      lists.append(b);
    });
    if (E.list?.missing?.length) {
      const b = el('button', 'ms-choice');
      b.type = 'button';
      b.setAttribute('aria-pressed', String(!E.cl));
      b.append(el('b', '', '+ New checklist'), el('small', '', E.list.missing.map(m => m.program).join(', ')));
      b.addEventListener('click', () => leave(() => { E.cl = null; render(); }));
      lists.append(b);
    }

    const close = el('button', 'ms-x', '×');
    close.type = 'button';
    close.setAttribute('aria-label', 'Close');
    close.addEventListener('click', () => leave(() => dialog.close()));

    head.append(lists, el('span', 'ms-saved', E.savedText), close);
  }

  // ── Body ──────────────────────────────────────────────────────────────

  function drawBody() {
    body.replaceChildren();
    if (!E.list) return;

    if (!E.cl) {
      body.append(createPanel());
      return;
    }

    const bar = el('div', 'ms-bulk');
    const main = el('div', 'ms-scroll');
    main.dataset.scroll = `editor:${E.cl.id}:${E.tab}`;
    body.append(bar, main);

    const archived = archivedEntries();
    const tabs = el('div', 'ms-grp');
    [['active', 'Milestones'], ['archived', 'Archived · ' + archived.length]].forEach(([key, label]) => {
      const chip = el('button', 'ms-chip', label);
      chip.type = 'button';
      chip.setAttribute('aria-pressed', String(E.tab === key));
      chip.addEventListener('click', () => { E.tab = key; E.picked.clear(); render(); });
      tabs.append(chip);
    });
    bar.append(tabs, el('span', 'ms-divider'));

    if (E.tab === 'active') {
      activeBar(bar);
      listView(main);
    } else {
      archivedBar(bar, archived);
      archivedView(main, archived);
    }
  }

  /** No checklist yet: start from the department's, or copy another CaPSA's. Nothing is created silently. */
  function createPanel() {
    const box = el('div', 'ms-create');
    box.append(
      el('h3', '', 'Set up a checklist'),
      el('p', 'ms-hint', 'Every section of the program that you handle follows it. You can change anything afterwards.'),
    );

    const missing = E.list.missing || [];
    missing.forEach(m => {
      const row = el('div', 'ms-create-row');
      row.append(el('b', '', m.program), btn(`Start from the ${m.program} department checklist`, 'ms-btn-gold', () => create({ program: m.program }), 'plus'));
      if (m.copies.length) {
        const select = el('select', 'ms-select');
        select.setAttribute('aria-label', `Copy another CaPSA's ${m.program} checklist`);
        select.append(new Option("Or copy another CaPSA's…", ''), ...m.copies.map(c => new Option(c.label, c.id)));
        row.append(select, btn('Copy', '', () => {
          if (select.value) create({ program: m.program, copy_of: Number(select.value) });
        }, 'check'));
      }
      box.append(row);
    });

    if (!missing.length) box.append(el('p', '', 'You already have a checklist for every program you handle.'));
    return box;
  }

  async function create(payload) {
    try {
      const checklist = await api('POST', urls.checklists, payload);
      saved();
      await load(checklist.id);
      toast(`Created the ${checklist.program} checklist.`);
    } catch (e) {
      toast(e.message);
    }
  }

  // ── The bar above the list ────────────────────────────────────────────

  /** Unsaved edits, or the hint when there are none. Refilled in place while typing, so focus stays put. */
  function fillPendingSlot(slot = dialog.querySelector('.ms-pending-slot')) {
    if (!slot) return;
    const n = pendingCount();
    if (!n) {
      slot.replaceChildren(el('span', 'ms-hint', 'Drag by the grip, or focus it and press ↑ ↓, to reorder · edit any name, type or due date, then press Save'));
      return;
    }
    slot.replaceChildren(
      el('b', 'ms-unsaved', plural(n, 'unsaved change')),
      btn('Save all', 'ms-btn-gold', () => saveEdits(), 'check'),
      btn('Discard', '', () => { E.pending.clear(); E.pendingNames.clear(); render(); }, 'undo'),
    );
  }

  function activeBar(bar) {
    const chosen = live().flatMap(m => m.deliverables.filter(d => !d.archived)).filter(d => E.picked.has(d.id));

    if (!chosen.length) {
      const slot = el('span', 'ms-pending-slot');
      bar.append(
        slot,
        el('span', 'ms-spacer'),
        btn('Open all', '', () => { E.collapsed.clear(); render(); }, 'expand'),
        btn('Close all', '', () => { live().forEach(m => E.collapsed.add(m.id)); render(); }, 'collapse'),
        el('span', 'ms-scope', 'Applies to ' + E.cl.used_by),
      );
      fillPendingSlot(slot);
      return;
    }

    bar.classList.add('ms-on');
    const ids = chosen.map(d => d.id);
    const N = plural(chosen.length, 'deliverable');
    const before = chosen.map(d => ({
      id: d.id, kind: d.kind, role: d.role, due_date: d.due, archived: d.archived, checklist_milestone_id: where(d.id).m.id,
    }));
    const previousOrder = orderBody();

    // Each change is picked, then applied with its own button - nothing happens on picking alone.
    const bulk = (change, message) => run(
      async () => { await api('PATCH', urls.deliverables, { ids, change }); await reload(); },
      message,
      async () => {
        await api('PATCH', urls.deliverables, { items: before });
        await api('PUT', orderUrl(), previousOrder);
        await reload();
      },
    );

    const move = el('select', 'ms-select');
    move.setAttribute('aria-label', 'Milestone to move them to');
    move.append(new Option('Move to milestone…', ''), ...live().map(m => new Option(m.name, m.id)));
    const moveGroup = el('div', 'ms-grp');
    moveGroup.append(move, btn('Move', '', () => {
      if (!move.value) return move.focus();
      E.collapsed.delete(Number(move.value));
      bulk({ milestone_id: Number(move.value) }, `Moved ${N} to ${move.selectedOptions[0].text}.`);
    }, 'move'));

    const type = el('select', 'ms-select');
    type.setAttribute('aria-label', 'Type to set');
    type.append(new Option('Set type…', ''), ...TYPES.map(([k, l]) => new Option(l, k)));
    const typeGroup = el('div', 'ms-grp');
    typeGroup.append(type, btn('Set type', '', () => {
      if (!type.value) return type.focus();
      bulk(splitType(type.value), `Set ${N} to ${type.selectedOptions[0].text}.`);
    }, 'check'));

    const due = dateInput('ms-select');
    due.setAttribute('aria-label', 'Due date to set');
    const dueGroup = el('div', 'ms-grp');
    dueGroup.append(due, btn('Set due date', '', () => {
      if (!due.value || !due.checkValidity()) {
        toast('Enter a due date between 2000 and 2100.');
        return due.focus();
      }
      bulk({ due_date: due.value }, `Due date set to ${fmtDate(due.value)} for ${N}.`);
    }, 'calendar'));

    // For when the whole schedule slips: every ticked due date moves by the same number of days.
    const days = el('input', 'ms-select ms-days');
    days.type = 'number';
    days.min = '1';
    days.max = '365';
    days.placeholder = '7';
    days.setAttribute('aria-label', 'How many days to move the due dates');
    const direction = el('select', 'ms-select');
    direction.setAttribute('aria-label', 'Later or earlier');
    direction.append(new Option('days later', '1'), new Option('days earlier', '-1'));
    const shiftGroup = el('div', 'ms-grp');
    shiftGroup.title = 'Moves every ticked due date by the same number of days - for when the whole schedule slips.';
    shiftGroup.append(days, direction, btn('Move due dates', '', () => {
      const n = parseInt(days.value, 10);
      if (!n || n < 1 || n > 365) {
        toast('Enter how many days, from 1 to 365.');
        return days.focus();
      }
      const signed = n * Number(direction.value);
      bulk({ shift_days: signed }, `Moved the due dates of ${N} ${plural(n, 'day')} ${signed > 0 ? 'later' : 'earlier'}.`);
    }, 'calendar'));

    bar.append(
      el('b', '', chosen.length + ' selected'), moveGroup, typeGroup, dueGroup, shiftGroup, el('span', 'ms-spacer'),
      btn('Archive', '', () => {
        E.picked.clear();
        ids.forEach(id => E.pending.delete(id));
        bulk({ archived: true }, `Archived ${N}. Teams no longer see ${chosen.length === 1 ? 'it' : 'them'}.`);
      }, 'archive'),
      btn('Remove', 'ms-btn-warn', () => removeDeliverables(chosen), 'trash'),
      btn('Clear', '', () => { E.picked.clear(); render(); }, 'x'),
    );
  }

  // ── The list ──────────────────────────────────────────────────────────

  function listView(main) {
    const phases = live();
    const wrap = el('div', 'ms-ed-list');
    main.append(wrap);

    const labels = el('div', 'ms-labels');
    ['', '', 'Deliverable', 'Type', 'Due', ''].forEach(text => labels.append(el('span', '', text)));
    wrap.append(labels);

    if (!phases.length) wrap.append(el('p', 'ms-drop-hint', 'No milestones yet. Add one to start.'));
    phases.forEach((m, idx) => wrap.append(group(m, phases, idx)));

    const add = el('button', 'ms-add-ms');
    add.type = 'button';
    const tile = el('span', 'ms-ico');
    tile.append(icon('plus'));
    add.append(tile, 'Add milestone');
    add.addEventListener('click', () => {
      let created;
      run(
        async () => {
          created = await api('POST', fill(urls.milestonesStore, E.cl.id), { name: 'New milestone' });
          await reload();
          E.focusKey = 'name-' + created.id;
        },
        'Added a milestone at the end. Name it, press Save, add deliverables, and drag it where it belongs.',
        async () => { await api('DELETE', fill(urls.milestone, created.id)); await reload(); },
      );
    });
    wrap.append(add);
  }

  function group(m, phases, idx) {
    const items = m.deliverables.filter(d => !d.archived);
    const open = !E.collapsed.has(m.id);
    const g = el('section', 'ms-egroup' + (open ? ' ms-open' : ''));
    g.setAttribute('aria-label', `Milestone ${idx + 1}: ${m.name}`);

    const h = el('div', 'ms-ehead');
    const hd = handle('ph-' + m.id, `Reorder milestone ${m.name}: press up or down, or drag`);
    grip(g, hd, 'phase', m.id, dir => {
      const neighbour = phases[idx + dir];
      if (!neighbour) return;
      const previous = orderBody();
      moveMilestone(m.id, neighbour.id, dir > 0);
      E.focusKey = 'ph-' + m.id;
      persistOrder(previous, `${m.name} is now milestone ${idx + dir + 1} of ${phases.length}.`, false);
    });

    const chev = el('button', 'ms-chev');
    chev.type = 'button';
    chev.dataset.key = 'chev-' + m.id;
    chev.setAttribute('aria-expanded', String(open));
    chev.setAttribute('aria-label', (open ? 'Close ' : 'Open ') + m.name);
    chev.append(icon('chev', 'ms-chevi'));
    chev.addEventListener('click', () => {
      if (E.collapsed.has(m.id)) E.collapsed.delete(m.id); else E.collapsed.add(m.id);
      E.focusKey = 'chev-' + m.id;
      render();
    });

    // The name is kept aside until Save; its Save and Discard appear beside it while it differs.
    const name = el('input', 'ms-ename');
    name.type = 'text';
    name.value = E.pendingNames.get(m.id) ?? m.name;
    name.maxLength = 255;
    name.dataset.key = 'name-' + m.id;
    name.setAttribute('aria-label', 'Milestone name');
    const nameActs = el('span', 'ms-grp');
    const drawNameActs = () => {
      const dirty = E.pendingNames.has(m.id);
      name.classList.toggle('ms-dirty-field', dirty);
      nameActs.replaceChildren(...(dirty ? [
        btn('Save', 'ms-btn-gold', () => saveEdits([], [m.id]), 'check'),
        btn('Discard', '', () => { E.pendingNames.delete(m.id); render(); }, 'undo'),
      ] : []));
      fillPendingSlot();
    };
    name.addEventListener('input', () => {
      const value = name.value.trim();
      if (value && value !== m.name) E.pendingNames.set(m.id, value); else E.pendingNames.delete(m.id);
      drawNameActs();
    });
    name.addEventListener('keydown', e => {
      if (e.key === 'Enter' && E.pendingNames.has(m.id)) saveEdits([], [m.id]);
    });
    drawNameActs();

    h.append(
      hd, chev, el('span', 'ms-ord', String(idx + 1)), name, nameActs,
      el('span', 'ms-ecount', plural(items.length, 'deliverable')),
      // The first milestone has nothing before it to wait for.
      ...(idx === 0 ? [] : [gateBox(m)]),
      el('span', 'ms-spacer'),
      btn('Add deliverables', 'ms-btn-add', () => openAdd(m), 'plus'),
      btn('Archive', '', () => run(
        async () => { await api('PATCH', fill(urls.milestone, m.id), { archived: true }); await reload(); },
        `Archived the ${m.name} milestone. Teams no longer see it or its deliverables.`,
        async () => { await api('PATCH', fill(urls.milestone, m.id), { archived: false }); await reload(); },
      ), 'archive'),
      btn('Remove', 'ms-btn-warn', () => removeMilestone(m), 'trash'),
    );
    g.append(h);

    // A milestone dropped on another goes before or after it.
    g.addEventListener('dragover', e => {
      if (!E.drag || E.drag.type !== 'phase' || E.drag.id === m.id) return;
      e.preventDefault();
      const after = lowerHalf(e, g);
      g.classList.toggle('ms-drop-after', after);
      g.classList.toggle('ms-drop-before', !after);
    });
    g.addEventListener('dragleave', e => {
      if (!g.contains(e.relatedTarget)) g.classList.remove('ms-drop-before', 'ms-drop-after');
    });
    g.addEventListener('drop', e => {
      if (!E.drag || E.drag.type !== 'phase') return;
      e.preventDefault();
      const dragged = E.drag;
      E.drag = null;
      if (dragged.id === m.id) return render();
      const previous = orderBody();
      moveMilestone(dragged.id, m.id, lowerHalf(e, g));
      persistOrder(previous, 'Milestone moved.', false);
    });

    // A deliverable dropped on a milestone's header, or its empty space, goes to the end of it.
    const intoPhase = target => {
      target.addEventListener('dragover', e => {
        if (!E.drag || E.drag.type !== 'item') return;
        e.preventDefault();
        e.stopPropagation();
        target.classList.add('ms-drop-in');
      });
      target.addEventListener('dragleave', e => {
        if (!target.contains(e.relatedTarget)) target.classList.remove('ms-drop-in');
      });
      target.addEventListener('drop', e => {
        if (!E.drag || E.drag.type !== 'item') return;
        e.preventDefault();
        e.stopPropagation();
        const dragged = E.drag;
        E.drag = null;
        const from = where(dragged.id);
        if (from.m.id === m.id) return render();
        const previous = orderBody();
        E.collapsed.delete(m.id);
        moveDeliverable(dragged.id, m.id, null, false);
        persistOrder(previous, `Moved “${from.d.title}” to ${m.name}.`, true);
      });
    };
    intoPhase(h);

    if (!open) return g;

    const list = el('div', 'ms-ebody');
    if (!items.length) list.append(el('div', 'ms-drop-hint', 'No deliverables yet. Add some, or drag them here.'));
    items.forEach((d, i) => list.append(itemRow(d, i, items, m, phases, idx)));
    intoPhase(list);
    g.append(list);
    return g;
  }

  function itemRow(d, i, items, m, phases, idx) {
    const row = el('div', 'ms-erow' + (E.picked.has(d.id) ? ' ms-picked' : ''));

    // Up/down moves within the milestone; past either end it crosses into the neighbouring one.
    const hd = handle('it-' + d.id, `Reorder ${d.title}: press up or down, or drag. Past the first or last one it moves into the next milestone.`);
    grip(row, hd, 'item', d.id, dir => {
      const previous = orderBody();
      const neighbour = items[i + dir];
      if (neighbour) {
        moveDeliverable(d.id, m.id, neighbour.id, dir > 0);
      } else {
        const other = phases[idx + dir];
        if (!other) return;
        const firstLive = other.deliverables.find(x => !x.archived);
        moveDeliverable(d.id, other.id, dir < 0 || !firstLive ? null : firstLive.id, false);
        E.collapsed.delete(other.id);
        say('Moved into ' + other.name + '.');
      }
      E.focusKey = 'it-' + d.id;
      persistOrder(previous, null, false);
    });

    row.addEventListener('dragover', e => {
      if (!E.drag || E.drag.type !== 'item') return;
      e.preventDefault();
      e.stopPropagation();
      if (E.drag.id === d.id) return;
      const after = lowerHalf(e, row);
      row.classList.toggle('ms-drop-after', after);
      row.classList.toggle('ms-drop-before', !after);
    });
    row.addEventListener('dragleave', () => row.classList.remove('ms-drop-before', 'ms-drop-after'));
    row.addEventListener('drop', e => {
      if (!E.drag || E.drag.type !== 'item') return;
      e.preventDefault();
      e.stopPropagation();
      const dragged = E.drag;
      E.drag = null;
      if (dragged.id === d.id) return render();
      const previous = orderBody();
      moveDeliverable(dragged.id, m.id, d.id, lowerHalf(e, row));
      persistOrder(previous, null, false);
    });

    // Edits are kept aside until Save. The row shows Save and Discard while it differs from what is saved.
    const pending = E.pending.get(d.id) || {};
    const current = key => (key in pending ? pending[key] : { title: d.title, kind: d.kind, role: d.role, due_date: d.due }[key]);

    const acts = el('span', 'ms-acts');
    const drawActs = () => {
      const dirty = E.pending.has(d.id);
      row.classList.toggle('ms-dirty', dirty);
      acts.replaceChildren(...(dirty ? [
        btn('Save', 'ms-btn-gold', () => saveEdits([d.id], []), 'check'),
        btn('Discard', '', () => { E.pending.delete(d.id); render(); }, 'undo'),
      ] : [
        // One quiet button per row instead of Archive and Remove repeated down the whole list.
        moreMenu(`More actions for “${d.title}”`, [
          ['Archive', 'archive', '', () => {
            E.picked.delete(d.id);
            run(
              async () => { await api('PATCH', fill(urls.deliverable, d.id), { archived: true }); await reload(); },
              `Archived “${d.title}”. Teams no longer see it.`,
              async () => { await api('PATCH', fill(urls.deliverable, d.id), { archived: false }); await reload(); },
            );
          }],
          ['Remove', 'trash', 'ms-menu-warn', () => removeDeliverables([d])],
        ]),
      ]));
      fillPendingSlot();
    };

    const stored = { title: d.title, kind: d.kind, role: d.role, due_date: d.due };
    const edit = changes => {
      const next = { ...(E.pending.get(d.id) || {}) };
      Object.entries(changes).forEach(([key, value]) => {
        if (value === stored[key]) delete next[key]; else next[key] = value;
      });
      if (Object.keys(next).length) E.pending.set(d.id, next); else E.pending.delete(d.id);
      drawActs();
    };

    const title = el('input');
    title.type = 'text';
    title.value = current('title');
    title.title = d.title;
    title.maxLength = 255;
    title.setAttribute('aria-label', 'Deliverable name');
    title.addEventListener('input', () => edit({ title: title.value.trim() || d.title }));
    title.addEventListener('keydown', e => {
      if (e.key === 'Enter' && E.pending.has(d.id)) saveEdits([d.id], []);
    });

    const type = el('select');
    type.setAttribute('aria-label', 'Type');
    TYPES.forEach(([k, l]) => type.append(new Option(l, k)));
    type.value = typeOf({ kind: current('kind'), role: current('role') });
    type.addEventListener('change', () => {
      const next = splitType(type.value);
      edit({ kind: next.kind, role: next.role });
    });

    const due = dateInput();
    due.value = current('due_date') || '';
    due.setAttribute('aria-label', 'Due date');
    due.addEventListener('input', () => edit({ due_date: due.value || null }));

    drawActs();
    row.append(pickBox(d.id, d.title), hd, title, type, due, acts);
    return row;
  }

  // ── Add, remove ───────────────────────────────────────────────────────

  function openAdd(m) {
    const box = el('dialog', 'ms-dialog ms-small');
    box.setAttribute('aria-label', 'Add deliverables to ' + m.name);

    const lines = el('textarea');
    lines.placeholder = 'Signed Adviser Endorsement Form\nChapter 4 Draft\nSigned CLF Approving Chapter 4';
    const linesLabel = el('label', '', 'Deliverables, one per line');
    linesLabel.append(lines);

    const phase = el('select');
    live().forEach(x => phase.append(new Option(x.name, x.id)));
    phase.value = String(m.id);
    const phaseLabel = el('label', '', 'Milestone');
    phaseLabel.append(phase);

    const type = el('select');
    TYPES.forEach(([k, l]) => type.append(new Option(l, k)));
    const typeLabelEl = el('label', '', 'Type');
    typeLabelEl.append(type);

    const due = dateInput();
    due.value = m.deliverables.map(x => x.due).filter(Boolean).sort().pop() || '';
    const dueLabel = el('label', '', 'Due date');
    dueLabel.append(due);

    const two = el('div', 'ms-two');
    two.append(typeLabelEl, dueLabel);

    const acts = el('div', 'ms-acts');
    acts.append(
      btn('Cancel', '', () => box.close()),
      btn('Add deliverables', 'ms-btn-gold', () => {
        const titles = lines.value.split('\n').map(s => s.trim()).filter(Boolean);
        if (!titles.length) return lines.focus();
        if (due.value && !due.checkValidity()) {
          toast('Enter a due date between 2000 and 2100.');
          return due.focus();
        }
        box.close();
        const target = Number(phase.value);
        const targetName = phase.selectedOptions[0].text;
        let ids = [];
        E.collapsed.delete(target);
        run(
          async () => {
            const res = await api('POST', fill(urls.deliverablesStore, target), { titles, ...splitType(type.value), due_date: due.value || null });
            ids = res.deliverables.map(x => x.id);
            await reload();
          },
          `Added ${plural(titles.length, 'deliverable')} to ${targetName}.`,
          async () => { await api('DELETE', urls.deliverables, { ids }); await reload(); },
        );
      }, 'plus'),
    );

    const form = el('div', 'ms-sd');
    form.append(
      el('h2', '', 'Add deliverables'), linesLabel, phaseLabel, two,
      el('p', 'ms-hint', 'Every line gets these settings. Change any of them per deliverable afterwards, right in the list.'),
      acts,
    );
    box.append(form);
    document.body.append(box);
    box.addEventListener('close', () => box.remove());
    box.showModal();
    lines.focus();
  }

  async function removeDeliverables(list) {
    const one = list.length === 1;
    const what = one ? `“${list[0].title}”` : plural(list.length, 'deliverable');
    const ok = await confirmRemove(
      `Remove ${what}?`,
      `This deletes ${one ? 'it' : 'them'} for ${E.cl.used_by}, along with any progress teams recorded. To only hide ${one ? 'it' : 'them'} for now, archive instead.`,
    );
    if (!ok) return;

    const ids = list.map(d => d.id);
    ids.forEach(id => E.pending.delete(id));
    E.picked.clear();
    run(
      async () => { await api('DELETE', urls.deliverables, { ids }); await reload(); },
      `Removed ${what}.`,
      async () => { await api('PUT', urls.removals, { milestones: [], deliverables: ids }); await reload(); },
    );
  }

  async function removeMilestone(m) {
    const n = m.deliverables.length;
    const ok = await confirmRemove(
      `Remove the “${m.name}” milestone?`,
      `This deletes it${n ? `, with the ${plural(n, 'deliverable')} inside,` : ''} for ${E.cl.used_by}, along with any progress teams recorded. To only hide it for now, archive instead.`,
    );
    if (!ok) return;

    E.pendingNames.delete(m.id);
    m.deliverables.forEach(d => E.pending.delete(d.id));
    run(
      async () => { await api('DELETE', fill(urls.milestone, m.id)); await reload(); },
      `Removed the “${m.name}” milestone.`,
      async () => { await api('PUT', urls.removals, { milestones: [m.id], deliverables: [] }); await reload(); },
    );
  }

  // ── Archived ──────────────────────────────────────────────────────────

  function archivedEntries() {
    return [
      ...E.cl.milestones.filter(m => m.archived).map(m => ({
        key: 'm' + m.id, m, name: m.name, meta: `Milestone · ${plural(m.deliverables.length, 'deliverable')}`,
      })),
      ...E.cl.milestones.filter(m => !m.archived).flatMap(m => m.deliverables.filter(d => d.archived).map(d => ({
        key: 'd' + d.id, d, name: d.title, meta: `Deliverable in ${m.name} · ${typeLabel(d)}${d.due ? ' · due ' + fmtDate(d.due) : ''}`,
      }))),
    ];
  }

  function setArchived(list, archived) {
    const milestones = list.filter(x => x.m).map(x => x.m.id);
    const deliverables = list.filter(x => x.d).map(x => x.d.id);
    return async () => {
      await Promise.all(milestones.map(id => api('PATCH', fill(urls.milestone, id), { archived })));
      if (deliverables.length) await api('PATCH', urls.deliverables, { ids: deliverables, change: { archived } });
      await reload();
    };
  }

  function restoreEntries(list) {
    const one = list.length === 1;
    E.picked.clear();
    run(
      setArchived(list, false),
      `Restored ${one ? `“${list[0].name}”` : plural(list.length, 'item')}. Teams see ${one ? 'it' : 'them'} again.`,
      setArchived(list, true),
    );
  }

  async function removeEntries(list) {
    const one = list.length === 1;
    const what = one ? `“${list[0].name}”` : plural(list.length, 'item');
    const ok = await confirmRemove(`Remove ${what}?`, `This deletes ${one ? 'it' : 'them'} for ${E.cl.used_by}, along with any progress teams recorded.`);
    if (!ok) return;

    const milestones = list.filter(x => x.m).map(x => x.m.id);
    const deliverables = list.filter(x => x.d).map(x => x.d.id);
    E.picked.clear();
    run(
      async () => {
        await Promise.all(milestones.map(id => api('DELETE', fill(urls.milestone, id))));
        if (deliverables.length) await api('DELETE', urls.deliverables, { ids: deliverables });
        await reload();
      },
      `Removed ${what}.`,
      async () => { await api('PUT', urls.removals, { milestones, deliverables }); await reload(); },
    );
  }

  function archivedBar(bar, entries) {
    const chosen = entries.filter(x => E.picked.has(x.key));
    if (!chosen.length) {
      bar.append(el('span', 'ms-hint', 'Archived milestones and deliverables are hidden from teams and the tracker. Restore them any time.'));
      return;
    }
    bar.classList.add('ms-on');
    bar.append(
      el('b', '', chosen.length + ' selected'), el('span', 'ms-spacer'),
      btn('Restore', '', () => restoreEntries(chosen), 'restore'),
      btn('Remove', 'ms-btn-warn', () => removeEntries(chosen), 'trash'),
      btn('Clear', '', () => { E.picked.clear(); render(); }, 'x'),
    );
  }

  function archivedView(main, entries) {
    const list = el('div', 'ms-alist');
    main.append(list);
    if (!entries.length) {
      list.append(el('div', 'ms-empty', 'Nothing archived.'));
      return;
    }
    entries.forEach(x => {
      const row = el('div', 'ms-arow' + (E.picked.has(x.key) ? ' ms-picked' : ''));
      const acts = el('span', 'ms-acts');
      acts.append(btn('Restore', '', () => restoreEntries([x]), 'restore'), btn('Remove', 'ms-btn-warn', () => removeEntries([x]), 'trash'));
      row.append(pickBox(x.key, x.name), el('span', 'ms-arow-title', x.name), el('span', 'ms-hint', x.meta), acts);
      list.append(row);
    });
  }

  // ── Small helpers ─────────────────────────────────────────────────────

  /** The gate: teams cannot hand anything in here until every milestone above it is done. */
  function gateBox(m) {
    const wrap = el('label', 'ms-gate');
    const box = el('input');
    box.type = 'checkbox';
    box.checked = !!m.requires_previous;
    box.dataset.key = 'gate-' + m.id;
    const set = on => api('PATCH', fill(urls.milestone, m.id), { requires_previous: on }).then(reload);
    box.addEventListener('change', () => {
      const on = box.checked;
      E.focusKey = 'gate-' + m.id;
      run(
        () => set(on),
        on
          ? `${m.name} now opens only after every milestone above it is complete.`
          : `${m.name} now opens together with the milestone above it.`,
        () => set(!on),
      );
    });
    wrap.append(box, el('span', '', 'Opens only after every milestone above it is complete'));
    return wrap;
  }

  function pickBox(key, label) {
    const box = el('input');
    box.type = 'checkbox';
    box.checked = E.picked.has(key);
    box.dataset.key = 'pick-' + key;
    box.setAttribute('aria-label', 'Select ' + label);
    box.addEventListener('change', () => {
      if (box.checked) E.picked.add(key); else E.picked.delete(key);
      E.focusKey = 'pick-' + key;
      render();
    });
    return box;
  }

  /**
   * A "More" button opening a short menu of labelled actions. Up/down move
   * between them, Escape or a click elsewhere closes it, and focus goes back
   * to the button.
   */
  function moreMenu(label, items) {
    const trigger = btn('More', 'ms-btn-more', null, 'more');
    trigger.setAttribute('aria-label', label);
    trigger.setAttribute('aria-haspopup', 'menu');
    trigger.setAttribute('aria-expanded', 'false');

    const menu = el('div', 'ms-menu');
    menu.setAttribute('popover', 'auto');
    menu.setAttribute('role', 'menu');
    menu.setAttribute('aria-label', label);
    items.forEach(([text, iconName, cls, action]) => {
      const item = btn(text, ('ms-menu-item ' + cls).trim(), () => { menu.hidePopover(); action(); }, iconName);
      item.setAttribute('role', 'menuitem');
      menu.append(item);
    });

    // The browser's own button-to-popover link: opens and closes it, and puts focus back on the button.
    trigger.popoverTargetElement = menu;
    // The toggle event comes after the menu has already been drawn once, in the page's
    // top-left corner. Keep it invisible from just before it opens until it is placed.
    menu.addEventListener('beforetoggle', e => {
      if (e.newState === 'open') menu.style.visibility = 'hidden';
    });
    menu.addEventListener('toggle', e => {
      const open = e.newState === 'open';
      trigger.setAttribute('aria-expanded', String(open));
      if (!open) return;
      // Under the button, right edges lined up - or above it when there is no room below.
      const r = trigger.getBoundingClientRect();
      const below = r.bottom + 4 + menu.offsetHeight <= window.innerHeight;
      menu.style.top = (below ? r.bottom + 4 : r.top - 4 - menu.offsetHeight) + 'px';
      menu.style.left = Math.max(8, r.right - menu.offsetWidth) + 'px';
      menu.style.visibility = '';
      menu.querySelector('button')?.focus();
    });
    menu.addEventListener('keydown', e => {
      if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
      e.preventDefault();
      const buttons = [...menu.querySelectorAll('button')];
      const at = buttons.indexOf(document.activeElement);
      buttons[(at + (e.key === 'ArrowDown' ? 1 : -1) + buttons.length) % buttons.length].focus();
    });

    const wrap = el('span', 'ms-more');
    wrap.append(trigger, menu);
    return wrap;
  }

  function handle(key, label) {
    const h = el('button', 'ms-handle');
    h.type = 'button';
    h.dataset.key = key;
    h.setAttribute('aria-label', label);
    h.append(icon('grip'));
    return h;
  }

  /** Drag by the grip - or focus the grip and use the up/down arrow keys. */
  function grip(item, h, type, id, keyMove) {
    h.addEventListener('pointerdown', () => { item.draggable = true; });
    h.addEventListener('pointerup', () => { item.draggable = false; });
    item.addEventListener('dragstart', e => {
      e.stopPropagation();
      E.drag = { type, id };
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', String(id));
      item.classList.add('ms-dragging');
    });
    item.addEventListener('dragend', e => {
      e.stopPropagation();
      item.draggable = false;
      E.drag = null;
      item.classList.remove('ms-dragging');
      dialog.querySelectorAll('.ms-drop-before,.ms-drop-after,.ms-drop-in')
        .forEach(x => x.classList.remove('ms-drop-before', 'ms-drop-after', 'ms-drop-in'));
    });
    h.addEventListener('keydown', e => {
      if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
        e.preventDefault();
        keyMove(e.key === 'ArrowUp' ? -1 : 1);
      }
    });
  }

  function lowerHalf(e, node) {
    return e.clientY > node.getBoundingClientRect().top + node.offsetHeight / 2;
  }
}
