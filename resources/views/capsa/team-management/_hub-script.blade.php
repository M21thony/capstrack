{{--
  What makes the Teams hub work: the tabs, the To do's Waiting / Decided
  switch, the Teams filters and sorting, the Approve / Decline dialog, the
  quiet refresh, and - for a CaPSA - the Team rules menu.
--}}
<dialog id="tm-decide" class="tm-dialog" aria-labelledby="tm-decide-t">
  <form method="dialog">
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

<script>
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

    hub.querySelectorAll('[data-todo-mode]').forEach(b => b.onclick = () => {
      hub.querySelectorAll('[data-todo-mode]').forEach(x => x.setAttribute('aria-pressed', x === b));
      document.getElementById('tm-todo-waiting').hidden = b.dataset.todoMode !== 'waiting';
      document.getElementById('tm-todo-decided').hidden = b.dataset.todoMode !== 'decided';
    });

    /* Teams: quick views, search, status and role hide rows here and now. */
    const table = document.getElementById('tm-teams');
    const box = document.getElementById('tm-q'), status = document.getElementById('tm-st');
    let quick = 'all', role = 'all';
    const rowsOf = () => [...table.querySelectorAll('tbody tr[data-href]')];
    const QUICK = { all: () => true, mine: r => r.dataset.mine === '1', you: r => r.dataset.needs === '1', behind: r => r.dataset.behind === '1' };

    const apply = () => {
      const q = box.value.trim().toLowerCase(), st = status.value;
      let shown = 0;
      rowsOf().forEach(r => {
        r.hidden = !((QUICK[quick] || QUICK.all)(r) && r.dataset.q.includes(q) && (!st || r.dataset.st === st)
          && (role === 'all' || r.dataset.role === role));
        shown += r.hidden ? 0 : 1;
      });
      const total = rowsOf().length, none = table.querySelector('.tm-none');
      if (none) { none.hidden = total > 0 && shown > 0; }
      table.querySelector('[data-count]').textContent = shown === total
        ? total + (total === 1 ? ' team' : ' teams')
        : 'Showing ' + shown + ' of ' + total + ' teams';
      const filtered = q || st || quick !== 'all' || role !== 'all';
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
    status.onchange = apply;
    hub.addEventListener('click', e => {
      if (!e.target.closest('[data-clear]')) { return; }
      box.value = ''; status.value = ''; role = 'all';
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
    hub.addEventListener('click', e => {
      const b = e.target.closest('[data-decide]');
      if (!b) { return; }
      current = b;
      const declining = b.dataset.decide === 'decline', min = Number(b.dataset.min || 0);
      document.getElementById('tm-decide-t').textContent = b.dataset.title;
      document.getElementById('tm-decide-sub').textContent = b.dataset.sub;
      const effect = document.getElementById('tm-decide-effect');
      effect.hidden = declining || !b.dataset.effect;
      effect.textContent = b.dataset.effect ? 'What happens: ' + b.dataset.effect : '';
      document.getElementById('tm-decide-label').textContent = declining ? 'Why are you declining?' : 'Note to the team';
      document.getElementById('tm-decide-hint').textContent = declining
        ? 'The team sees this' + (min > 1 ? ' · at least ' + min + ' characters' : '')
        : 'Optional';
      note.value = ''; note.required = declining; note.minLength = declining ? min : 0;
      err.hidden = true;
      ok.textContent = b.textContent.trim();
      ok.className = 'btn ' + (declining ? 'btn-r' : 'btn-p');
      dlg.showModal();
      note.focus();
    });
    document.getElementById('tm-decide-x').onclick = () => dlg.close();
    dlg.querySelector('form').addEventListener('submit', e => {
      e.preventDefault();
      if (!current) { return; }
      const b = current, declining = b.dataset.decide === 'decline';
      if (declining && note.value.trim().length < Math.max(1, Number(b.dataset.min || 1))) {
        err.textContent = 'Write a line or two so the team knows why.'; err.hidden = false; note.focus(); return;
      }
      const body = { [b.dataset.field]: b.dataset.value };
      if (note.value.trim()) { body[b.dataset.note] = note.value.trim(); }
      ok.disabled = true;
      fetch(b.dataset.url, {
        method: 'PATCH',
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
          const li = b.closest('li'), list = li.parentNode;
          li.remove();
          if (!list.children.length) { list.previousElementSibling?.remove(); list.remove(); }
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
    const parts = ['#tm-todo-waiting', '#tm-todo-decided', '#tm-teams tbody', '.hub-sum', '.hub-tabs', '.tm-quick'];
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
    const RULES = @json(['max' => $rules['maxMembers'], 'create' => $rules['allowCreation'], 'join' => $rules['allowJoining']]),
          SIZES = @json((object) $rules['teamSizes']),
          MIN = {{ \App\Models\Team::MIN_MEMBERS }}, MAX = {{ \App\Models\Team::MAX_MEMBERS }};
    const setBtn = document.getElementById('setBtn'), setMenu = document.getElementById('setMenu'),
          tsize = document.getElementById('tsize'), tsw = document.getElementById('tsw'),
          acreate = document.getElementById('acreate'), ajoin = document.getElementById('ajoin'),
          setOk = document.getElementById('setOk');

    const checkSize = () => {
      const v = Number(tsize.value), fine = Number.isInteger(v) && v >= MIN && v <= MAX;
      /* How many teams a lower limit would reach, said before she commits. */
      const over = fine ? Object.keys(SIZES).reduce((n, k) => n + (Number(k) > v ? SIZES[k] : 0), 0) : 0;
      tsize.setAttribute('aria-invalid', !fine);
      tsw.className = 'opt-w' + (fine ? ' soft' : '');
      tsw.textContent = !fine
        ? 'Type a number from ' + MIN + ' to ' + MAX + '.'
        : over + ' team' + (over > 1 ? 's' : '') + ' already ' + (over > 1 ? 'have' : 'has')
          + ' more than ' + v + '. They keep their members but cannot add anyone.';
      tsw.hidden = fine && !over;
      setOk.disabled = !fine;
    };

    const setOpen = on => {
      if (on) { tsize.value = RULES.max; acreate.checked = RULES.create; ajoin.checked = RULES.join; checkSize(); }
      setMenu.hidden = !on;
      document.getElementById('setScrim').hidden = !on;
      setBtn.setAttribute('aria-expanded', on);
      if (on) { tsize.focus(); }
    };

    setBtn.onclick = () => setOpen(setMenu.hidden);
    // Inside .setw, so the click-away rule below does not see it: it cancels itself.
    document.getElementById('setScrim').onclick = () => { setOpen(false); setBtn.focus(); };
    tsize.oninput = checkSize;
    document.getElementById('setX').onclick = () => { setOpen(false); setBtn.focus(); };
    document.addEventListener('click', e => { if (!setMenu.hidden && !e.target.closest('.setw')) { setOpen(false); } });
    setMenu.addEventListener('keydown', e => { if (e.key === 'Escape') { setOpen(false); setBtn.focus(); } });

    setOk.onclick = () => {
      setOk.disabled = true;
      fetch(@json(route('capsa.team_rules.update'), JSON_UNESCAPED_SLASHES), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ max_members: Number(tsize.value), allow_creation: acreate.checked, allow_joining: ajoin.checked }),
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
