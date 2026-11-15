{{--
  Team Rating rubrics, on Grading Rubrics: the department's rubric, the ones
  the CaPSA/TSA wrote, and which one new ratings are made with.

  A rating keeps its own copy of the rubric it was made with, so writing or
  editing one here changes only the ratings made after.

  The page's Team Rating tile opens it with openTeamRatingRubrics().
--}}
@php
  use App\Http\Controllers\Capsa\TeamRatingRubricController;
  use App\Support\PeerRatingRubric;
@endphp
<style nonce="{{ Vite::cspNonce() }}">
  dialog.rb-tr .cm-box{max-width:1180px}
  .rb-tr .mk-scroll{overflow:auto;border:1px solid var(--border);border-radius:10px;max-height:min(58vh,560px)}
  .rb-tr .mk-t td{white-space:normal;vertical-align:top;font-size:12.5px;line-height:1.45}
  .rb-tr .mk-foot{display:flex;justify-content:flex-end;gap:8px}
  .trb-top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
  .trb-list{display:grid;gap:8px}
  .trb-row{display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--border);border-radius:10px;background:#fff}
  .trb-row.on{border-color:var(--accent);background:var(--accent-light)}
  .trb-row .who{flex:1;min-width:0}
  .trb-row .who b{display:block;font-size:14px;color:var(--text);overflow-wrap:anywhere}
  .trb-row .who span{font-size:12px;color:var(--text3)}
  .trb-tag{font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;background:#18191f;color:#fff;white-space:nowrap}
  .trb-acts{display:flex;flex-wrap:wrap;gap:6px;justify-content:flex-end}
  .trb-acts .mk-btn{min-height:34px;padding:0 12px;font-size:12.5px}
  .trb-acts .mk-btn.danger{color:var(--red,#dc2626)}
  .trb-edit{display:grid;gap:14px;max-height:min(60vh,600px);overflow:auto;padding-right:4px}
  .trb-crit{border:1px solid var(--border);border-radius:10px;padding:12px;display:grid;gap:10px;background:#fff}
  .trb-crit-h{display:grid;grid-template-columns:28px minmax(0,2fr) minmax(0,1fr) auto;gap:8px;align-items:end}
  .trb-crit-h .n{width:28px;height:28px;border-radius:50%;background:var(--bg3);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;margin-bottom:5px}
  .trb-levels{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px}
  .trb-levels textarea{min-height:104px;font-size:12.5px;line-height:1.4;resize:vertical}
  .trb-err{margin:0;padding:8px 12px;border-radius:8px;font-size:13px;border:1px solid var(--red,#dc2626);background:rgba(220,38,38,.07);color:var(--red,#dc2626)}
  .trb-err[hidden]{display:none}
  @media (max-width:900px){
    .trb-levels{grid-template-columns:1fr}
    .trb-crit-h{grid-template-columns:28px minmax(0,1fr)}
    .trb-row{flex-wrap:wrap}
  }
</style>

<dialog class="cm rb-new rb-tr" id="rb-tr-dialog" aria-labelledby="rb-tr-title"><div class="cm-box">
  <div class="trb-top">
    <div>
      <h3 id="rb-tr-title">Team Rating rubrics</h3>
      <div class="sub" id="rb-tr-sub"></div>
    </div>
    <button type="button" class="mk-btn pri" id="trb-new">New rubric</button>
  </div>
  <p class="trb-err" id="trb-err" role="alert" hidden></p>
  <div id="trb-body"></div>
  <div class="mk-foot" id="trb-foot"></div>
</div></dialog>

<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var LEVELS = @json(collect(PeerRatingRubric::LEVELS)->map(fn ($word, $points) => ['points' => $points, 'word' => $word])->values());
  var MAX_CRITERIA = {{ PeerRatingRubric::MAX_CRITERIA }};
  var URLS = {
    store: @json(route('capsa.team_rating_rubrics.store')),
    one: @json(route('capsa.team_rating_rubrics.update', ['rubric' => '__ID__'])),
    use: @json(route('capsa.team_rating_rubrics.use', ['rubric' => '__ID__'])),
  };
  var rubrics = @json(TeamRatingRubricController::listing());
  var dlg = document.getElementById('rb-tr-dialog');
  var body = document.getElementById('trb-body');
  var foot = document.getElementById('trb-foot');
  var sub = document.getElementById('rb-tr-sub');
  var err = document.getElementById('trb-err');
  var newBtn = document.getElementById('trb-new');
  var draft = null; // the rubric being written: {id, name, criteria}

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }
  function button(label, cls, onClick) {
    var b = el('button', 'mk-btn' + (cls ? ' ' + cls : ''), label);
    b.type = 'button';
    b.addEventListener('click', onClick);
    return b;
  }
  function fail(message) { err.textContent = message; err.hidden = !message; }
  function inUse() { return rubrics.filter(function (r) { return r.in_use; })[0] || rubrics[0]; }

  async function send(url, method, payload) {
    var res = await fetch(url, {
      method: method,
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: payload ? JSON.stringify(payload) : undefined,
    });
    var json = await res.json().catch(function () { return {}; });
    if (!res.ok) {
      var first = json.errors ? Object.values(json.errors)[0][0] : null;
      throw new Error(first || json.message || 'Could not save. Try again.');
    }
    if (json.rubrics) rubrics = json.rubrics;
    window.dispatchEvent(new CustomEvent('team-rating-rubrics', { detail: summary() }));
    return json;
  }

  /** What the page's Team Rating tile shows. */
  function summary() { return { count: rubrics.length, inUse: inUse().name }; }
  window.teamRatingRubricSummary = summary;

  // ── the list ──
  function showList() {
    draft = null;
    fail('');
    newBtn.hidden = false;
    sub.textContent = 'How teammates rate each other. Each criterion gets ' + LEVELS.map(function (l) { return l.points; }).join(', ').replace(/, (\d+)$/, ' or $1')
      + ' points. A rating keeps the rubric it was made with, so a change here applies to ratings you create after.';
    body.textContent = '';
    var list = el('div', 'trb-list');
    rubrics.forEach(function (r) {
      var row = el('div', 'trb-row' + (r.in_use ? ' on' : ''));
      var who = el('div', 'who');
      who.appendChild(el('b', null, r.name));
      who.appendChild(el('span', null, r.criteria.length + (r.criteria.length === 1 ? ' criterion' : ' criteria') + ' · ' + r.max + ' points'
        + (r.fixed ? ' · the department\'s rubric, cannot be changed' : '')));
      row.appendChild(who);
      if (r.in_use) row.appendChild(el('span', 'trb-tag', 'In use for new ratings'));
      var acts = el('div', 'trb-acts');
      if (!r.in_use) acts.appendChild(button('Use for new ratings', '', function () { use(r); }));
      acts.appendChild(button('View', 'ghost', function () { showRubric(r); }));
      if (r.fixed) {
        acts.appendChild(button('Copy to edit', 'ghost', function () { edit({ id: null, name: '', criteria: r.criteria }); }));
      } else {
        acts.appendChild(button('Edit', 'ghost', function () { edit(r); }));
        acts.appendChild(button('Delete', 'ghost danger', function () { askRemove(r, acts); }));
      }
      row.appendChild(acts);
      list.appendChild(row);
    });
    body.appendChild(list);
    foot.textContent = '';
    foot.appendChild(button('Close', 'pri', function () { dlg.close(); }));
  }

  async function use(r) {
    try { await send(URLS.use.replace('__ID__', r.fixed ? 'default' : r.id), 'PUT'); showList(); }
    catch (e) { fail(e.message); }
  }

  // Asked in the row itself: a second window over this one would sit behind it.
  function askRemove(r, acts) {
    acts.textContent = '';
    acts.appendChild(el('span', 'sub', 'Delete it? Ratings already made keep their copy.'));
    acts.appendChild(button('Keep', 'ghost', showList));
    acts.appendChild(button('Delete', 'danger', async function () {
      try { await send(URLS.one.replace('__ID__', r.id), 'DELETE'); showList(); }
      catch (e) { fail(e.message); }
    }));
  }

  // ── one rubric, to read ──
  function showRubric(r) {
    fail('');
    newBtn.hidden = true;
    sub.textContent = r.name + ' · ' + r.criteria.length + (r.criteria.length === 1 ? ' criterion' : ' criteria') + ', ' + r.max + ' points.';
    body.textContent = '';
    var wrap = el('div', 'mk-scroll');
    var table = el('table', 'mk-t');
    var head = el('tr');
    head.appendChild(el('th', null, 'Criteria'));
    LEVELS.forEach(function (l) { head.appendChild(el('th', null, l.word + ' (' + l.points + ')')); });
    table.appendChild(el('thead')).appendChild(head);
    var rows = table.appendChild(el('tbody'));
    r.criteria.forEach(function (c) {
      var tr = el('tr');
      tr.appendChild(el('td')).appendChild(el('b', null, c.name));
      c.levels.forEach(function (d) { tr.appendChild(el('td', null, d)); });
      rows.appendChild(tr);
    });
    wrap.appendChild(table);
    body.appendChild(wrap);
    foot.textContent = '';
    foot.appendChild(button('Back', 'ghost', showList));
  }

  // ── the editor ──
  function blankCriterion() { return { name: '', short: '', levels: LEVELS.map(function () { return ''; }) }; }

  function edit(r) {
    draft = { id: r.id, name: r.name, criteria: r.criteria.map(function (c) { return { name: c.name, short: c.short === c.name ? '' : (c.short || ''), levels: c.levels.slice() }; }) };
    if (!draft.criteria.length) draft.criteria.push(blankCriterion());
    drawEditor();
  }

  function field(labelText, input) {
    var f = el('div', 'mk-f');
    var label = el('label', 'label', labelText);
    f.appendChild(label);
    f.appendChild(input);
    label.htmlFor = input.id;
    return f;
  }

  function drawEditor() {
    fail('');
    newBtn.hidden = true;
    sub.textContent = (draft.id ? 'Editing this rubric changes ratings you create after. Ratings already made keep the rubric they had.' : 'A new rubric. Ratings you create after choosing it use it.');
    body.textContent = '';
    var form = el('div', 'trb-edit');

    var name = el('input', 'input');
    name.id = 'trb-name'; name.maxLength = 120; name.value = draft.name; name.placeholder = 'e.g. Midterm participation rubric';
    name.addEventListener('input', function () { draft.name = name.value; });
    form.appendChild(field('Rubric name', name));

    draft.criteria.forEach(function (c, i) {
      var card = el('div', 'trb-crit');
      var headRow = el('div', 'trb-crit-h');
      headRow.appendChild(el('span', 'n', String(i + 1)));
      var cn = el('input', 'input'); cn.id = 'trb-c' + i + '-name'; cn.maxLength = 80; cn.value = c.name; cn.placeholder = 'e.g. Completion of Assigned Tasks';
      cn.addEventListener('input', function () { c.name = cn.value; });
      headRow.appendChild(field('Criterion', cn));
      var cs = el('input', 'input'); cs.id = 'trb-c' + i + '-short'; cs.maxLength = 24; cs.value = c.short; cs.placeholder = 'e.g. Tasks';
      cs.addEventListener('input', function () { c.short = cs.value; });
      headRow.appendChild(field('Short heading (optional)', cs));
      var rm = button('Remove', 'ghost', function () { draft.criteria.splice(i, 1); drawEditor(); });
      rm.disabled = draft.criteria.length === 1;
      rm.setAttribute('aria-label', 'Remove criterion ' + (i + 1));
      headRow.appendChild(rm);
      card.appendChild(headRow);

      var levels = el('div', 'trb-levels');
      LEVELS.forEach(function (l, li) {
        var ta = el('textarea', 'input'); ta.id = 'trb-c' + i + '-l' + li; ta.maxLength = 600; ta.value = c.levels[li] || '';
        ta.placeholder = 'What ' + l.word.toLowerCase() + ' looks like';
        ta.addEventListener('input', function () { c.levels[li] = ta.value; });
        levels.appendChild(field(l.word + ' (' + l.points + ')', ta));
      });
      card.appendChild(levels);
      form.appendChild(card);
    });

    var add = button('Add criterion', '', function () { draft.criteria.push(blankCriterion()); drawEditor(); var last = document.getElementById('trb-c' + (draft.criteria.length - 1) + '-name'); if (last) last.focus(); });
    add.disabled = draft.criteria.length >= MAX_CRITERIA;
    form.appendChild(add);
    body.appendChild(form);

    foot.textContent = '';
    foot.appendChild(el('span', 'sub', draft.criteria.length + (draft.criteria.length === 1 ? ' criterion' : ' criteria') + ' · ' + draft.criteria.length * LEVELS[0].points + ' points')).style.marginRight = 'auto';
    foot.appendChild(button('Cancel', 'ghost', showList));
    var save = button(draft.id ? 'Save changes' : 'Create rubric', 'pri', async function () {
      save.disabled = true;
      try {
        var payload = { name: draft.name, criteria: draft.criteria };
        if (draft.id) await send(URLS.one.replace('__ID__', draft.id), 'PUT', payload);
        else await send(URLS.store, 'POST', payload);
        if (window.ctToast) ctToast(draft.id ? 'Rubric saved.' : 'Rubric created.', 'success');
        showList();
      } catch (e) { fail(e.message); save.disabled = false; }
    });
    save.id = 'trb-save';
    foot.appendChild(save);
  }

  newBtn.addEventListener('click', function () { edit({ id: null, name: '', criteria: [] }); });
  window.openTeamRatingRubrics = function () { showList(); dlg.showModal(); };
})();
</script>
