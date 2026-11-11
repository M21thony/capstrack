{{--
  What makes the Teams hub work: the tabs, the To do's Waiting / Decided
  switch, the Team Applications filters and its application window, the
  Teams filters and sorting, the Approve / Decline dialog, the quiet
  refresh, and - for a CaPSA/TSA - the Team rules menu.
--}}
<dialog id="tm-decide" class="tm-dialog" aria-labelledby="tm-decide-t">
  <form method="post">@csrf
    <div class="dh"><h2 id="tm-decide-t"></h2><p id="tm-decide-sub"></p></div>
    <div class="db">
      <p class="next-note" id="tm-decide-effect" hidden></p>
      <label class="f" for="tm-decide-note"><span id="tm-decide-label"></span><small id="tm-decide-hint"></small></label>
      <textarea id="tm-decide-note" rows="3"></textarea>
      <p class="tm-err" id="tm-decide-err" hidden></p>
    </div>
    <div class="df">
      <button type="button" class="btn btn-o" id="tm-decide-x">Cancel</button>
      <button type="submit" class="btn btn-p" id="tm-decide-ok"></button>
    </div>
  </form>
</dialog>

<script nonce="{{ Vite::cspNonce() }}">
  (function () {
    const hub = document.querySelector('.tm-hub');
    if (!hub) { return; }
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    const toast = (msg, ok) => {
      if (window.ctToast) { return ctToast(msg, ok ? 'success' : 'error'); }
      const el = document.getElementById('tm-toast');
      if (!el) { return; }
      el.textContent = msg;
      el.style.borderLeftColor = ok ? 'var(--green)' : 'var(--red)';
      el.classList.add('show');
      clearTimeout(toast.t);
      toast.t = setTimeout(() => el.classList.remove('show'), 4000);
    };

    /* To do and Teams switch in place, and the address follows, so a
       reload or a link back lands on the same tab. */
    const showTab = (key, push) => {
      hub.querySelectorAll('[data-hub-tab]').forEach(t => {
        if (t.getAttribute('role') === 'tab') { t.setAttribute('aria-selected', t.dataset.hubTab === key); }
      });
      document.getElementById('tm-todo').hidden = key !== 'todo';
      document.getElementById('tm-apps-tab').hidden = key !== 'applications';
      document.getElementById('tm-teams-tab').hidden = key !== 'teams';
      if (push) {
        const url = new URL(location.href);
        url.searchParams.set('tab', key);
        history.replaceState(null, '', url);
      }
    };
    hub.addEventListener('click', e => {
      const t = e.target.closest('[data-hub-tab]');
      if (!t || e.metaKey || e.ctrlKey || e.shiftKey) { return; }
      e.preventDefault();
      showTab(t.dataset.hubTab, true);
      if (t.dataset.quickGo) { setQuick(t.dataset.quickGo); }
    });

    const setTodoMode = mode => {
      hub.querySelectorAll('[data-todo-mode]').forEach(x => x.setAttribute('aria-pressed', x.dataset.todoMode === mode));
      document.getElementById('tm-todo-waiting').hidden = mode !== 'waiting';
      document.getElementById('tm-done').hidden = mode !== 'decided';
    };

    /* Completed: the latest two months, one older month per click, and the
       search looks through every month. The address keeps how many months
       are open and Open remembers the scroll, so coming back lands on the
       same row. */
    const doneBox = document.getElementById('tm-done-box'), doneQ = document.getElementById('tm-done-q');
    let doneShown = Math.max(2, Number(new URL(location.href).searchParams.get('done')) || 0);
    const keepDone = on => {
      const url = new URL(location.href);
      if (on) { url.searchParams.set('done', doneShown); } else { url.searchParams.delete('done'); }
      history.replaceState(null, '', url);
    };
    const applyDone = () => {
      const months = [...document.querySelectorAll('#tm-todo-decided section[data-month]')];
      if (!months.length) { return; }
      const q = doneQ.value.trim().toLowerCase(), next = months[doneShown];
      let total = 0, seen = 0;
      months.forEach((month, i) => {
        const rows = [...month.querySelectorAll('li[data-q]')];
        rows.forEach(li => { li.hidden = !li.dataset.q.includes(q); });
        const hits = rows.filter(li => !li.hidden).length;
        month.hidden = q ? !hits : i >= doneShown;
        total += rows.length;
        seen += month.hidden ? 0 : hits;
      });
      document.getElementById('tm-done-none').hidden = !q || seen > 0;
      document.getElementById('tm-done-more-row').hidden = !!q || !next;
      if (next) { document.getElementById('tm-done-more').textContent = 'Show ' + next.dataset.month + ' · ' + next.dataset.n; }
      const end = document.getElementById('tm-done-end');
      end.hidden = !!q || !!next;
      end.textContent = 'That’s everything since ' + months[months.length - 1].dataset.month + '.';
      document.getElementById('tm-done-total').textContent = q ? seen + ' found' : 'Showing ' + seen + ' of ' + total + ', newest first';
    };
    hub.addEventListener('click', e => {
      if (e.target.closest('#tm-done-more')) {
        const top = doneBox.scrollTop;
        doneShown++;
        applyDone();
        keepDone(true);
        doneBox.scrollTop = top;
      } else if (e.target.closest('[data-done-open]')) {
        try { sessionStorage.setItem('tm-done-top', doneBox.scrollTop); } catch (err) { /* blocked storage: just no scroll back */ }
      }
    });
    if (doneQ) { doneQ.oninput = () => { applyDone(); doneBox.scrollTop = 0; }; }

    hub.querySelectorAll('[data-todo-mode]').forEach(b => b.onclick = () => {
      setTodoMode(b.dataset.todoMode);
      keepDone(b.dataset.todoMode === 'decided');
    });
    applyDone();
    if (new URL(location.href).searchParams.has('done')) {
      setTodoMode('decided');
      try { doneBox.scrollTop = Number(sessionStorage.getItem('tm-done-top')) || 0; sessionStorage.removeItem('tm-done-top'); } catch (err) { /* blocked storage */ }
    }

    /* Team Applications: Waiting / Declined, search and role. */
    let appsMode = 'waiting';
    const appsQ = document.getElementById('tm-apps-q'), appsRole = document.getElementById('tm-apps-role');
    const applyApps = () => {
      const q = appsQ.value.trim().toLowerCase(), r = appsRole.value;
      ['waiting', 'declined'].forEach(mode => {
        const pane = document.getElementById('tm-apps-' + mode);
        pane.hidden = mode !== appsMode;
        document.getElementById('tm-apps-exp-' + mode).hidden = mode !== appsMode;
        const rows = [...pane.querySelectorAll('tbody tr[data-q]')];
        let shown = 0;
        rows.forEach(row => {
          row.hidden = !(row.dataset.q.includes(q) && (!r || row.dataset.role === r));
          shown += row.hidden ? 0 : 1;
        });
        const none = pane.querySelector('.apps-none');
        if (none) { none.hidden = shown > 0; }
        if (mode === appsMode) {
          const total = rows.length, noun = n => n === 1 ? ' team' : ' teams';
          document.getElementById('tm-apps-count').textContent = shown === total
            ? total + noun(total) + (mode === 'waiting' ? ' · waiting longest first' : ' · declined lately')
            : 'Showing ' + shown + ' of ' + total + noun(total);
        }
      });
    };
    hub.querySelectorAll('[data-apps-mode]').forEach(b => b.onclick = () => {
      appsMode = b.dataset.appsMode;
      hub.querySelectorAll('[data-apps-mode]').forEach(x => x.setAttribute('aria-pressed', x === b));
      applyApps();
    });
    appsQ.oninput = applyApps;
    appsRole.onchange = applyApps;
    applyApps();

    /* Read: the application - or a new title - in the PDF window, with what
       there is to decide beside it and the decision under it. */
    const appsData = () => JSON.parse(document.getElementById('tm-apps-data').textContent || '{}');
    const el = (tag, cls, text) => {
      const n = document.createElement(tag);
      if (cls) { n.className = cls; }
      if (text !== undefined && text !== null) { n.textContent = text; }
      return n;
    };
    const sec = (head, ...kids) => {
      const s = el('section', 'tma-sec');
      if (head) { s.append(el('h3', 'tma-h', head)); }
      s.append(...kids.filter(Boolean));
      return s;
    };
    const decideButton = (label, cls, data) => {
      const b = el('button', 'pv-act ' + cls, label);
      b.type = 'button';
      Object.entries(data).forEach(([k, v]) => { if (v !== undefined && v !== null) { b.dataset[k] = v; } });
      return b;
    };
    const verdictOf = d => d.verdict
      ? el('p', 'tma-text', d.verdict + (d.topScore !== null && d.topScore !== undefined ? ' · closest match ' + d.topScore + '% similar' : ''))
      : null;

    const readApplication = id => {
      const all = appsData(), d = (all.details || {})[id];
      if (!d) { return; }
      const role = (d.role || 'expert').toLowerCase(), waiting = d.status === 'pending';

      const who = el('div', 'tma-who');
      const tile = el('span', 'tma-tile', (d.team || '?').slice(0, 2));
      const name = el('div');
      name.append(el('div', 'tma-team', d.team));
      const sub = el('div', 'tma-sub');
      sub.append(waiting ? 'wants you as their ' : 'asked you to be their ', el('b', null, role));
      name.append(sub);
      who.append(tile, name);
      const state = el('div', 'tma-state');
      state.append(el('span', 'tma-pill ' + (waiting ? 'wait' : 'no'), waiting ? 'Waiting for you' : 'Declined'),
        waiting ? 'Applied ' + d.waited : 'Declined ' + d.declinedOn);

      const side = el('div', 'tma');
      side.append(sec(null, who, state));
      if (waiting && all.maxSlots > 0) {
        const after = all.usedSlots + 1;
        side.append(sec('If you accept', el('p', 'tma-text',
          after > all.maxSlots ? 'Your slots are full. Accepting is refused until a slot frees up.'
            : after + ' of ' + all.maxSlots + ' slots used' + (after === all.maxSlots ? ' · your last open slot' : ''))));
      }
      if (d.project) {
        const t = el('p', 'tma-title', d.project);
        side.append(sec(d.recommended ? 'Project title · recommended by their CaPSA/TSA' : 'Project title', t));
      }
      if (d.verdict) { side.append(sec('Duplication check', verdictOf(d))); }
      const people = [el('p', 'tma-text', (d.leader || 'Leader not set') + ' · Leader')];
      if (d.leaderEmail) { people.push(el('p', 'tma-mail', d.leaderEmail)); }
      (d.members || []).forEach(m => people.push(el('p', 'tma-text', m)));
      side.append(sec('People', ...people));
      if (d.message) { side.append(sec('Their message', el('p', 'tma-quote', d.message))); }
      if (!waiting && d.reason) { side.append(sec('Why you declined', el('p', 'tma-quote', d.reason))); }
      if (d.meet) {
        side.append(sec('You asked to meet first',
          el('p', 'tma-text', d.meet.booked ? 'Meeting booked · ' + d.meet.booked + ' · approve or decline after' : 'Waiting for them to book one of your times'),
          d.meet.note ? el('p', 'tma-quote', d.meet.note) : null));
      }

      // Asked to meet and nothing booked yet: nothing to decide until they meet.
      const actions = waiting && !(d.meet && !d.meet.booked) ? [
        decideButton('Decline…', 'no', { decide: 'decline', url: d.acceptUrl, field: 'status', value: 'rejected', note: 'reason', min: '20',
          title: 'Decline: Team ' + d.team, sub: 'Asked you to be their ' + role }),
        d.meet ? null : decideButton('Meet first…', '', { decide: 'meet', url: d.acceptUrl, field: 'status', value: 'meet', note: 'reason', min: '10',
          title: 'Meet Team ' + d.team + ' first?', sub: 'They book one of your consultation times. You approve or decline after.' }),
        decideButton('Accept as ' + role, 'go', { decide: 'approve', url: d.acceptUrl, field: 'status', value: 'approved', note: 'reason',
          title: 'Accept Team ' + d.team, sub: 'As their ' + role,
          effect: 'You become the ' + role + ' of Team ' + d.team + '. The team is told.' + (all.maxSlots > 0 ? ' Uses 1 of your ' + all.maxSlots + ' slots.' : '') }),
      ].filter(Boolean) : [];

      window.PdfViewer.open({
        url: d.document || null,
        title: 'Application from Team ' + d.team,
        meta: d.role + ' · applied ' + d.applied,
        empty: 'Team ' + d.team + ' did not attach a document. What they sent is beside this.',
        side,
        note: waiting ? 'You can\'t remove a team after accepting.' : '',
        actions,
      });
    };

    const readTitle = id => {
      const d = ((appsData().titles) || {})[id];
      if (!d) { return; }
      const side = el('div', 'tma');
      side.append(sec('New title', el('p', 'tma-title', d.title)));
      if ((d.replaces || []).length) { side.append(sec('Replaces', ...d.replaces.map(t => el('p', 'tma-text', t)))); }
      if (d.verdict) { side.append(sec('Duplication check', verdictOf(d))); }
      window.PdfViewer.open({
        url: d.document || null,
        title: 'New title from Team ' + d.team,
        meta: 'Sent ' + d.sent + ' · you are their adviser',
        empty: 'No document came with this title.',
        side,
        actions: [
          decideButton('Ask for another…', 'no', { decide: 'decline', url: d.updateUrl, field: 'decision', value: 'decline', note: 'note', min: '10',
            title: 'Ask for another: Team ' + d.team, sub: d.title }),
          decideButton('Confirm title', 'go', { decide: 'approve', url: d.updateUrl, field: 'decision', value: 'approve', note: 'note',
            title: 'Confirm the new title of Team ' + d.team, sub: d.title,
            effect: 'It becomes the title of Team ' + d.team + '. The team is told.' }),
        ],
      });
    };

    document.addEventListener('click', e => {
      const b = e.target.closest('[data-read]');
      if (!b || !window.PdfViewer) { return; }
      (b.dataset.read === 'title' ? readTitle : readApplication)(b.dataset.readId);
    });

    /* Teams: quick views, search, program, section and role hide rows here and now. */
    const table = document.getElementById('tm-teams');
    const box = document.getElementById('tm-q'), prog = document.getElementById('tm-prog'), secSel = document.getElementById('tm-sec');
    let quick = 'all', role = 'all';
    const rowsOf = () => [...table.querySelectorAll('tbody tr[data-href]')];
    const QUICK = { all: () => true, mine: r => r.dataset.mine === '1', you: r => r.dataset.needs === '1', behind: r => r.dataset.behind === '1' };

    const apply = () => {
      const q = box.value.trim().toLowerCase(), p = prog.value, s = secSel.value;
      let shown = 0;
      rowsOf().forEach(r => {
        r.hidden = !((QUICK[quick] || QUICK.all)(r) && r.dataset.q.includes(q) && (!p || r.dataset.prog === p)
          && (!s || r.dataset.sec === s) && (role === 'all' || r.dataset.role === role));
        shown += r.hidden ? 0 : 1;
      });
      const total = rowsOf().length, none = table.querySelector('.tm-none');
      if (none) { none.hidden = total > 0 && shown > 0; }
      table.querySelector('[data-count]').textContent = shown === total
        ? total + (total === 1 ? ' team' : ' teams')
        : 'Showing ' + shown + ' of ' + total + ' teams';
      const filtered = q || p || s || quick !== 'all' || role !== 'all';
      hub.querySelectorAll('.tools [data-clear]').forEach(b => { b.hidden = !filtered; });
    };
    const setQuick = key => {
      quick = QUICK[key] && hub.querySelector('[data-quick="' + key + '"]') ? key : 'all';
      hub.querySelectorAll('[data-quick]').forEach(b => b.setAttribute('aria-pressed', b.dataset.quick === quick));
      apply();
    };
    hub.querySelectorAll('[data-quick]').forEach(b => b.onclick = () => setQuick(b.dataset.quick));
    hub.querySelectorAll('[data-role]').forEach(b => b.onclick = () => {
      role = b.dataset.role;
      hub.querySelectorAll('[data-role]').forEach(x => x.setAttribute('aria-pressed', x === b));
      apply();
    });
    box.oninput = apply;
    // A section picked on page load limits the rows the server sent; leaving it is a page load.
    const reload = v => { location.search = '?tab=teams' + (v ? '&section=' + encodeURIComponent(v) : ''); };
    prog.onchange = () => {
      [...secSel.options].forEach(o => { o.hidden = !!o.value && !!prog.value && o.dataset.prog !== prog.value; });
      if (secSel.selectedOptions[0].hidden) { secSel.value = ''; }
      if (secSel.dataset.server && secSel.value !== secSel.dataset.server) { return reload(secSel.value); }
      apply();
    };
    secSel.onchange = () => secSel.dataset.server ? reload(secSel.value) : apply();
    hub.addEventListener('click', e => {
      if (!e.target.closest('[data-clear]')) { return; }
      if (secSel.dataset.server) { return reload(''); }
      box.value = ''; prog.value = ''; secSel.value = ''; role = 'all';
      [...secSel.options].forEach(o => { o.hidden = false; });
      hub.querySelectorAll('[data-role]').forEach(x => x.setAttribute('aria-pressed', x.dataset.role === 'all'));
      setQuick('all');
    });
    document.addEventListener('keydown', e => {
      if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) && !table.closest('[hidden]')) {
        e.preventDefault(); box.focus();
      }
    });

    /* Click a heading to sort by it; again to flip the order; a third time
       goes back to the order the page came in (who needs her first). */
    let sortKey = '', sortDir = 1;
    const KEYS = { name: 'name', adviser: 'adviser', progress: 'progress', status: 'rank' };
    table.querySelectorAll('th button.sort').forEach(b => b.onclick = () => {
      const k = b.dataset.sort;
      if (sortKey !== k) { sortKey = k; sortDir = 1; } else if (sortDir === 1) { sortDir = -1; } else { sortKey = ''; }
      const body = table.querySelector('tbody'), none = body.querySelector('.tm-none');
      const rows = rowsOf();
      if (!body.dataset.order) { rows.forEach((r, i) => { r.dataset.i = i; }); body.dataset.order = '1'; }
      rows.sort((a, c) => {
        if (!sortKey) { return a.dataset.i - c.dataset.i; }
        const x = a.dataset[KEYS[sortKey]], y = c.dataset[KEYS[sortKey]];
        return sortDir * (/^\d+$/.test(x) && /^\d+$/.test(y) ? x - y : x.localeCompare(y));
      }).forEach(r => body.insertBefore(r, none));
      table.querySelectorAll('th button.sort').forEach(x => {
        const on = x.dataset.sort === sortKey;
        x.closest('th').setAttribute('aria-sort', on ? (sortDir > 0 ? 'ascending' : 'descending') : 'none');
        x.querySelector('span').textContent = on ? (sortDir > 0 ? '↑' : '↓') : '↕';
        x.querySelector('span').classList.toggle('on', on);
      });
    });

    /* Anywhere on the row opens the team, except the parts that do their own thing. */
    table.onclick = e => {
      const r = e.target.closest('tr[data-href]');
      if (r && !e.target.closest('input,a,button')) { location.href = r.dataset.href; }
    };

    const params = new URLSearchParams(location.search);
    if (params.get('quick')) { setQuick(params.get('quick')); } else { apply(); }

    /* Approve and Decline: one dialog that says what will happen, asks for
       a reason when declining, and sends the decision where it belongs. */
    const dlg = document.getElementById('tm-decide'), note = document.getElementById('tm-decide-note'),
          err = document.getElementById('tm-decide-err'), ok = document.getElementById('tm-decide-ok');
    let current = null;
    // Meet first asks for a note like a decline does, but it is not a no.
    const needsNote = b => ['decline', 'meet'].includes(b.dataset.decide) && !b.dataset.noteOptional;
    const isNo = b => !['approve', 'meet'].includes(b.dataset.decide);
    document.addEventListener('click', e => {
      const b = e.target.closest('[data-decide]');
      if (!b) { return; }
      current = b;
      const declining = isNo(b), min = Number(b.dataset.min || 0), noNote = !!b.dataset.noNote;
      document.getElementById('tm-decide-t').textContent = b.dataset.title;
      document.getElementById('tm-decide-sub').textContent = b.dataset.sub;
      const effect = document.getElementById('tm-decide-effect');
      effect.hidden = !b.dataset.effect;
      effect.textContent = b.dataset.effect ? 'What happens: ' + b.dataset.effect : '';
      document.getElementById('tm-decide-label').textContent = declining ? (b.dataset.noteOptional ? 'What is missing?' : 'Why are you declining?') : 'Note to the team';
      document.getElementById('tm-decide-hint').textContent = needsNote(b)
        ? 'The team sees this' + (min > 1 ? ' · at least ' + min + ' characters' : '')
        : (declining ? 'Optional · the team sees this' : 'Optional');
      note.closest('.db').querySelectorAll('label.f, textarea').forEach(n => { n.hidden = noNote; });
      note.value = ''; note.required = needsNote(b); note.minLength = needsNote(b) ? min : 0;
      err.hidden = true;
      ok.textContent = b.textContent.trim().replace(/…$/, '');
      ok.className = 'btn ' + (declining ? 'btn-r' : 'btn-p');
      dlg.showModal();
      if (!noNote) { note.focus(); }
    });
    document.getElementById('tm-decide-x').onclick = () => dlg.close();
    dlg.querySelector('form').addEventListener('submit', e => {
      e.preventDefault();
      if (!current) { return; }
      const b = current, declining = isNo(b), meet = b.dataset.decide === 'meet';
      if (needsNote(b) && note.value.trim().length < Math.max(1, Number(b.dataset.min || 1))) {
        err.textContent = meet ? 'Write a line or two so the team knows what to bring.' : 'Write a line or two so the team knows why.';
        err.hidden = false; note.focus(); return;
      }
      const body = b.dataset.field ? { [b.dataset.field]: b.dataset.value } : {};
      if (note.value.trim() && b.dataset.note) { body[b.dataset.note] = note.value.trim(); }
      ok.disabled = true;
      fetch(b.dataset.url, {
        method: b.dataset.method || 'PATCH',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(body),
      })
        .then(r => r.json().catch(() => ({})).then(data => ({ ok: r.ok, data })))
        .then(({ ok: fine, data }) => {
          if (!fine || data.success === false) {
            const first = data.errors ? Object.values(data.errors)[0] : null;
            err.textContent = (first && first[0]) || data.message || 'Could not save that. Try again.';
            err.hidden = false;
            return;
          }
          dlg.close();
          if (window.PdfViewer?.isOpen()) { window.PdfViewer.close(); }
          const li = b.closest('li[data-item]');
          if (li) {
            const list = li.parentNode;
            li.remove();
            if (!list.children.length) { list.previousElementSibling?.remove(); list.remove(); }
          }
          // Meet first leaves the request waiting; the refresh redraws its row.
          if (!meet) { b.closest('tr[data-q]')?.remove(); }
          toast(data.message || (declining ? 'Declined' : 'Done'), true);
          refresh(true);
        })
        .catch(() => { err.textContent = 'Network error. Nothing was saved.'; err.hidden = false; })
        .finally(() => { ok.disabled = false; });
    });

    /* The hub keeps itself current: every 15 seconds, while it is being
       looked at, the page is fetched quietly and only the lists and the
       counts are swapped in - search, filters and sorting stay as they are,
       and a team that is newly waiting on her flashes once. */
    const parts = ['#tm-todo-waiting', '#tm-todo-decided', '#tm-apps-waiting', '#tm-apps-declined', '#tm-apps-data',
      '#tm-apps-nw', '#tm-apps-nd', '#tm-teams tbody', '.hub-sum', '.hub-tabs', '.tm-quick'];
    const waitOf = root => new Map([...root.querySelectorAll('#tm-teams tr[data-href]')]
      .map(r => [r.dataset.href, r.querySelector('.tm-what')?.textContent.trim() || '']));
    const markup = root => parts.map(sel => root.querySelector(sel)?.innerHTML ?? '').join('');
    let last = markup(document), busy = false;

    const refresh = async (force) => {
      if ((document.hidden && !force) || busy || dlg.open) { return; }
      busy = true;
      try {
        const res = await fetch(location.href, { headers: { Accept: 'text/html' } });
        // Signed out meanwhile: leave the page as it is.
        if (!res.ok || res.redirected) { return; }
        const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
        if (!doc.querySelector('#tm-teams tbody') || markup(doc) === last) { return; }
        last = markup(doc);
        const before = waitOf(document);
        const tabNow = hub.querySelector('.hub-tabs [aria-selected="true"]')?.dataset.hubTab;
        parts.forEach(sel => {
          const now = document.querySelector(sel), next = doc.querySelector(sel);
          if (now && next) { now.innerHTML = next.innerHTML; }
        });
        if (tabNow) { showTab(tabNow, false); }
        hub.querySelectorAll('[data-quick]').forEach(b => {
          b.setAttribute('aria-pressed', b.dataset.quick === quick);
          b.onclick = () => setQuick(b.dataset.quick);
        });
        document.querySelectorAll('#tm-teams tr[data-href]').forEach(r => {
          const wait = r.querySelector('.tm-what')?.textContent.trim() || '';
          if (!before.has(r.dataset.href) || (wait && wait !== before.get(r.dataset.href))) { r.classList.add('tm-flash'); }
        });
        delete table.querySelector('tbody').dataset.order;
        apply();
        applyApps();
        applyDone();
      } catch (e) {
        /* Offline for a moment: the next round tries again. */
      } finally {
        busy = false;
      }
    };
    setInterval(refresh, 15000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) { refresh(); } });

    @if ($portal === 'capsa')
    /* Team rules: opens under its button. Cancel, Escape or clicking away
       drops the changes, so nothing is saved by accident. */
    /* One max per program; SIZES[program] is how many of her teams have each headcount. */
    const RULES = @json(['max' => collect($rules['programs'])->mapWithKeys(fn ($p) => [$p['program'] => $p['max']]), 'create' => $rules['allowCreation'], 'join' => $rules['allowJoining']]),
          SIZES = @json(collect($rules['programs'])->mapWithKeys(fn ($p) => [$p['program'] => (object) $p['sizes']])),
          MIN = {{ \App\Models\Team::MIN_MEMBERS }}, MAX = {{ \App\Models\Team::MAX_MEMBERS }};
    const setBtn = document.getElementById('setBtn'), setMenu = document.getElementById('setMenu'),
          tsizes = [...setMenu.querySelectorAll('.tsize')],
          acreate = document.getElementById('acreate'), ajoin = document.getElementById('ajoin'),
          setOk = document.getElementById('setOk');

    const checkSize = () => {
      let allFine = true;
      tsizes.forEach(tsize => {
        const p = tsize.dataset.program, tsw = document.getElementById('tsw-' + p), sizes = SIZES[p] || {};
        const v = Number(tsize.value), fine = Number.isInteger(v) && v >= MIN && v <= MAX;
        /* How many teams a lower limit would reach, said before she commits. */
        const over = fine ? Object.keys(sizes).reduce((n, k) => n + (Number(k) > v ? sizes[k] : 0), 0) : 0;
        tsize.setAttribute('aria-invalid', !fine);
        tsw.className = 'opt-w' + (fine ? ' soft' : '');
        tsw.textContent = !fine
          ? 'Type a number from ' + MIN + ' to ' + MAX + '.'
          : over + ' ' + p + ' team' + (over > 1 ? 's' : '') + ' already ' + (over > 1 ? 'have' : 'has')
            + ' more than ' + v + '. They keep their members but cannot add anyone.';
        tsw.hidden = fine && !over;
        allFine = allFine && fine;
      });
      setOk.disabled = !allFine;
    };

    const setOpen = on => {
      if (on) { tsizes.forEach(t => { t.value = RULES.max[t.dataset.program]; }); acreate.checked = RULES.create; ajoin.checked = RULES.join; checkSize(); }
      setMenu.hidden = !on;
      document.getElementById('setScrim').hidden = !on;
      setBtn.setAttribute('aria-expanded', on);
      if (on) { (tsizes[0] || acreate).focus(); }
    };

    setBtn.onclick = () => setOpen(setMenu.hidden);
    // Inside .setw, so the click-away rule below does not see it: it cancels itself.
    document.getElementById('setScrim').onclick = () => { setOpen(false); setBtn.focus(); };
    tsizes.forEach(t => { t.oninput = checkSize; });
    document.getElementById('setX').onclick = () => { setOpen(false); setBtn.focus(); };
    document.addEventListener('click', e => { if (!setMenu.hidden && !e.target.closest('.setw')) { setOpen(false); } });
    setMenu.addEventListener('keydown', e => { if (e.key === 'Escape') { setOpen(false); setBtn.focus(); } });

    setOk.onclick = () => {
      setOk.disabled = true;
      fetch(@json(route('capsa.team_rules.update'), JSON_UNESCAPED_SLASHES), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({
          max_members: Object.fromEntries(tsizes.map(t => [t.dataset.program, Number(t.value)])),
          allow_creation: acreate.checked, allow_joining: ajoin.checked,
        }),
      })
        .then(r => r.json().then(data => ({ ok: r.ok, data })))
        .then(({ ok: fine, data }) => {
          if (!fine || !data.success) { toast(data.message || 'Could not save the team rules.', false); return; }
          RULES.max = data.max_members; RULES.create = data.allow_creation; RULES.join = data.allow_joining;
          setOpen(false);
          setBtn.focus();
          toast(data.message, true);
        })
        .catch(() => toast('Network error. Nothing was saved.', false))
        .finally(() => { setOk.disabled = false; });
    };
    @endif
  })();
</script>
