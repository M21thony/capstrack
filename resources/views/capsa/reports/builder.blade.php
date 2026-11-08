@php($table = collect($tables)->firstWhere('id', $current))
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>{{ $table['name'] }} - Reports - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/report-builder.css') }}"/>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
@include('capsa.reports._styles')
  @include('partials.render-whole')
</head>
<body>

@include('capsa._sidebar')
@include('partials.sidebar-toggle')
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <nav class="crumbs" aria-label="Breadcrumb">
      <a href="{{ route('capsa.reports') }}">Reports</a>
      <span class="sep">›</span>
      <span class="here" aria-current="page" id="crumb-name">{{ $table['name'] }}</span>
    </nav>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="sk" style="height:50px;border-radius:10px;margin-bottom:12px"></div>
    <div class="sk" style="height:520px;border-radius:10px"></div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content rb">
    <div class="heading"><h1>Report builder</h1><button class="primary" id="new-table">＋ New table</button></div>
    <section class="card work">
      <div class="workspace-head">
        <div class="title-tools">
          <span style="color:var(--blue)"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 9v12"/></svg></span>
          <select class="saved-select" id="saved-table" aria-label="Saved table"></select>
          <span class="saved-state" id="save-state">Saved</span>
          <div class="menu-wrap">
            <button id="table-options" aria-label="Table options" aria-expanded="false">⋯</button>
            <div class="menu" id="table-menu" hidden>
              <button id="rename-table">Rename table</button>
              <button id="duplicate-table">Duplicate table</button>
              <button id="split-columns">Split combined columns</button>
              <button class="danger" id="delete-table">Delete saved table</button>
            </div>
          </div>
        </div>
        <div class="actions">
          <button id="save-table" disabled>Save changes</button>
          <div class="menu-wrap">
            <button class="primary" id="export" aria-expanded="false">Export ⌄</button>
            <div class="menu" id="export-menu" hidden>
              <button id="export-csv">Download CSV (Excel)</button>
              <button id="export-print">Print / Save as PDF</button>
            </div>
          </div>
        </div>
      </div>
      <div class="filters">
        <label><span>School year</span><select id="f-year"></select></label>
        <label><span>Semester</span><select id="f-term"></select></label>
        <label><span>Program</span><select id="f-program"></select></label>
        <label><span>Section</span><select id="f-section"></select></label>
        <button class="ghost small reset" id="reset-filters">Reset</button>
      </div>
      <div class="table-tools">
        <button id="edit-columns" aria-expanded="false" aria-controls="column-editor">Edit columns ⌄</button>
        <label class="row-mode">Rows
          <select id="row-mode"><option value="students">Students</option><option value="teams">Teams (grouped)</option><option value="experts">Experts</option></select>
        </label>
        <input class="search" id="search" type="search" aria-label="Search report" placeholder="Search student ID, name, or team">
      </div>
      <div class="column-editor" id="column-editor" hidden>
        <div class="editor-label"><strong>Visible columns</strong></div>
        <div class="chips" id="chips"></div>
        <div class="editor-actions">
          <div><span class="muted" id="selected-count">No fields selected</span><button id="combine" class="small" disabled>Combine selected</button><button id="clear-selection" class="ghost small">Clear selection</button></div>
          <button id="done-columns" class="ghost small">Done</button>
        </div>
      </div>
      <div class="print-title" hidden><h1 id="print-title"></h1><p id="print-meta"></p></div>
      <div class="scroll-area" id="report-scroll" tabindex="0" aria-label="Report table">
        <table id="report"><thead id="table-head"></thead><tbody id="table-body"></tbody></table>
        <div class="empty" id="empty" hidden>No records match these filters.</div>
      </div>
      <div class="caps-x">
        <button id="scroll-left" aria-label="Scroll table left">‹</button>
        <div class="caps-track" id="scroll-track"><div class="caps-thumb" id="scroll-thumb"></div></div>
        <button id="scroll-right" aria-label="Scroll table right">›</button>
      </div>
      <footer class="table-footer">
        <span id="record-count">Loading…</span><span id="column-count"></span>
        <div class="pagination"><span id="page-label"></span><button id="prev-page" aria-label="Previous page">‹</button><button id="current-page" class="current" aria-label="Current page">1</button><button id="next-page" aria-label="Next page">›</button></div>
      </footer>
    </section>

    @include('capsa.reports._rb')
  </div>
  </div>
</div>

@include('capsa.partials.skeleton-toggle', ['revealDisplay' => 'flex'])
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var $ = RB.$, esc = RB.esc, toast = RB.toast, openDialog = RB.openDialog, closeDialog = RB.closeDialog;
  var tables = @json($tables), fields = @json($fields), urlFilters = @json($urlFilters);
  var URLS = {
    data: @json(route('capsa.reports.data')), store: @json(route('capsa.reports.tables.store')),
    builder: @json(route('capsa.reports.builder', '__ID__')), update: @json(route('capsa.reports.tables.update', '__ID__')),
    destroy: @json(route('capsa.reports.tables.destroy', '__ID__'))
  };
  var FILTERS = ['year', 'term', 'program', 'section'];
  var EXPERT_KEYS = ['expert', 'role', 'load', 'team', 'course', 'section'];
  var clone = function (o) { return JSON.parse(JSON.stringify(o)); };
  var url = function (k, id) { return URLS[k].replace('__ID__', id); };

  var saved = tables.find(function (t) { return t.id === @json($current); });
  saved.filters = Object.assign({}, saved.filters);
  var state = clone(saved);
  state.filters = Object.assign({}, state.filters, urlFilters);
  var data = null, selection = new Set(), dirty = false, page = 1, pageSize = 10, dragKey = null, loading = 0;

  // ---- saved / dirty ----------------------------------------------------
  function snapshot(s) { return JSON.stringify({name: s.name, mode: s.mode, columns: s.columns, filters: s.filters}); }
  function setDirty(on) {
    dirty = on;
    $('save-state').textContent = on ? 'Unsaved changes' : 'Saved';
    $('save-state').classList.toggle('dirty', on);
    $('save-table').disabled = !on;
  }
  function markDirty() { setDirty(snapshot(state) !== snapshot(saved)); }
  function refreshSaved() {
    $('saved-table').innerHTML = tables.map(function (t) { return '<option value="' + t.id + '">' + esc(t.id === state.id ? state.name : t.name) + '</option>'; }).join('');
    $('saved-table').value = state.id;
    $('crumb-name').textContent = state.name;
  }
  function unique(name) {
    var c = name, n = 2;
    while (tables.some(function (t) { return t.name.toLowerCase() === c.toLowerCase(); })) c = name + ' (' + (n++) + ')';
    return c;
  }
  function taken(name, exceptId) { return tables.some(function (t) { return t.id !== exceptId && t.name.toLowerCase() === name.toLowerCase(); }); }

  // ---- data from the server --------------------------------------------
  function load() {
    var q = new URLSearchParams();
    FILTERS.forEach(function (k) { if (state.filters[k] != null && state.filters[k] !== '') q.set(k, state.filters[k]); });
    var mine = ++loading;
    $('record-count').textContent = 'Loading…';
    return fetch(URLS.data + '?' + q, {headers: {Accept: 'application/json'}, credentials: 'same-origin'})
      .then(function (r) { if (!r.ok) throw new Error('Could not load the records. Try again.'); return r.json(); })
      .then(function (d) {
        if (mine !== loading) return;
        data = d;
        // The server settles each pick (an unknown section falls back to all); keep what it used.
        FILTERS.forEach(function (k) {
          $('f-' + k).innerHTML = d.options[k].map(function (o) { return '<option value="' + esc(o[0]) + '">' + esc(o[1]) + '</option>'; }).join('');
          $('f-' + k).value = d.filters[k];
          $('f-' + k).disabled = d.options[k].length < 2;
          state.filters[k] = d.filters[k];
          // A filter the table never stored means "the default": the server's pick is not a change.
          if (saved.filters[k] == null) saved.filters[k] = d.filters[k];
        });
        markDirty();
        render();
      })
      .catch(function (e) { toast(e.message); });
  }

  // ---- rows ---------------------------------------------------------------
  function reportRows() {
    if (!data) return [];
    var source = state.mode === 'experts' ? data.expertRows : data.rows;
    var s = (state.filters.search || '').toLowerCase();
    if (s) source = source.filter(function (r) { return Object.values(r).join(' ').toLowerCase().indexOf(s) !== -1; });
    if (state.mode !== 'teams') return source;
    var groups = new Map();
    source.forEach(function (r) { var k = r.course + '|' + r.section + '|' + r.team; if (!groups.has(k)) groups.set(k, []); groups.get(k).push(r); });
    return Array.from(groups.values()).map(function (rs) {
      var out = {};
      Object.keys(rs[0]).forEach(function (k) { out[k] = Array.from(new Set(rs.map(function (r) { return r[k]; }))).join('\n'); });
      out.fee = rs.reduce(function (sum, r) { return sum + (Number(r.fee) || 0); }, 0).toFixed(2);
      return out;
    });
  }
  function value(row, c) { return c.sources.map(function (k) { return row[k] == null || row[k] === '' ? '—' : row[k]; }).join(c.separator); }
  function fieldKeys() {
    var keys = Object.keys(fields);
    return state.mode === 'experts' ? EXPERT_KEYS : keys.filter(function (k) { return ['expert', 'role', 'load'].indexOf(k) === -1; });
  }

  // ---- render -------------------------------------------------------------
  function renderChips() {
    selection = new Set(Array.from(selection).filter(function (k) { return state.columns.some(function (c) { return c.key === k; }); }));
    $('chips').innerHTML = state.columns.map(function (c) {
      var on = selection.has(c.key);
      return '<div class="chip' + (on ? ' selected' : '') + '" draggable="true" data-key="' + esc(c.key) + '" role="button" tabindex="0" aria-pressed="' + on + '" aria-label="Select ' + esc(c.label) + ' column">'
        + '<span class="grip" aria-hidden="true">⠿</span><span>' + esc(c.label) + '</span>'
        + (c.sources.length > 1 ? '<span class="combined">' + c.sources.length + ' fields</span>' : '')
        + '<button class="remove" data-remove="' + esc(c.key) + '" aria-label="Hide ' + esc(c.label) + ' column">×</button></div>';
    }).join('') + '<button id="add-field" class="small">＋ Field</button>';
    $('selected-count').textContent = selection.size ? selection.size + ' fields selected' : 'No fields selected';
    $('combine').disabled = selection.size < 2;
  }
  function render(all) {
    renderChips();
    var rows = reportRows(), pages = Math.max(1, Math.ceil(rows.length / pageSize));
    page = Math.min(page, pages);
    var shown = all ? rows : rows.slice((page - 1) * pageSize, page * pageSize);
    $('table-head').innerHTML = '<tr>' + state.columns.map(function (c) { return '<th scope="col">' + esc(c.label) + '</th>'; }).join('') + '</tr>';
    $('table-body').innerHTML = shown.map(function (r) {
      return '<tr>' + state.columns.map(function (c) { return '<td data-key="' + esc(c.key) + '">' + esc(value(r, c)) + '</td>'; }).join('') + '</tr>';
    }).join('');
    $('empty').hidden = !data || rows.length > 0;
    if (data) $('record-count').textContent = rows.length + ' ' + ({students: 'students', teams: 'teams', experts: 'experts'})[state.mode];
    $('column-count').textContent = state.columns.length + ' columns';
    $('page-label').textContent = rows.length ? 'Showing ' + ((page - 1) * pageSize + 1) + '–' + Math.min(page * pageSize, rows.length) : '0 records';
    $('current-page').textContent = page;
    $('prev-page').disabled = page <= 1;
    $('next-page').disabled = page >= pages;
    requestAnimationFrame(syncScroll);
  }
  // As many rows per page as the box shows without scrolling.
  function fitPageSize() {
    var area = $('report-scroll'), head = $('table-head').offsetHeight || 34;
    var heights = Array.from($('table-body').rows).map(function (r) { return r.offsetHeight; });
    var row = heights.length ? Math.min.apply(null, heights) : 34;
    var next = Math.max(5, Math.floor((area.clientHeight - head) / row));
    if (next !== pageSize) { pageSize = next; render(); }
  }

  // ---- chips --------------------------------------------------------------
  function toggleSelect(key) { selection.has(key) ? selection.delete(key) : selection.add(key); renderChips(); }
  function moveColumn(from, to) { state.columns.splice(to, 0, state.columns.splice(from, 1)[0]); markDirty(); render(); }
  var chips = $('chips');
  chips.addEventListener('click', function (e) {
    if (e.target.id === 'add-field') return addFields();
    var rm = e.target.closest('[data-remove]');
    if (rm) {
      if (state.columns.length === 1) return toast('Keep at least one visible column.');
      state.columns = state.columns.filter(function (c) { return c.key !== rm.dataset.remove; });
      markDirty(); return render();
    }
    var chip = e.target.closest('.chip');
    if (chip) toggleSelect(chip.dataset.key);
  });
  chips.addEventListener('keydown', function (e) {
    var chip = e.target.closest('.chip');
    if (!chip || e.target !== chip) return;
    if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); toggleSelect(chip.dataset.key); }
    if (e.altKey && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) {
      e.preventDefault();
      var i = state.columns.findIndex(function (c) { return c.key === chip.dataset.key; }), j = i + (e.key === 'ArrowLeft' ? -1 : 1);
      if (j < 0 || j >= state.columns.length) return;
      moveColumn(i, j);
      chips.querySelector('[data-key="' + chip.dataset.key + '"]').focus();
    }
  });
  chips.addEventListener('dragstart', function (e) { var c = e.target.closest('.chip'); if (!c) return; dragKey = c.dataset.key; c.classList.add('dragging'); e.dataTransfer.setData('text/plain', dragKey); });
  chips.addEventListener('dragend', function () { dragKey = null; });
  chips.addEventListener('dragover', function (e) { if (e.target.closest('.chip')) e.preventDefault(); });
  chips.addEventListener('drop', function (e) {
    var c = e.target.closest('.chip'); if (!c) return;
    e.preventDefault();
    var from = state.columns.findIndex(function (x) { return x.key === dragKey; }), to = state.columns.findIndex(function (x) { return x.key === c.dataset.key; });
    if (from >= 0 && to >= 0 && from !== to) moveColumn(from, to);
  });

  function addFields() {
    openDialog('Add fields', '<div class="check-grid">' + fieldKeys().map(function (k) {
      var inTable = state.columns.some(function (c) { return c.key === k; });
      return '<label><input type="checkbox" value="' + k + '"' + (inTable ? ' disabled' : '') + '>' + esc(fields[k]) + '</label>';
    }).join('') + '</div>', '<button data-close>Cancel</button><button class="primary" id="apply-fields">Add selected fields</button>');
    $('apply-fields').addEventListener('click', function () {
      var chosen = Array.from($('modal').querySelectorAll('input:checked'));
      if (!chosen.length) return toast('Select at least one field.');
      chosen.forEach(function (el) { state.columns.push({key: el.value, label: fields[el.value], sources: [el.value], separator: ' · '}); });
      markDirty(); closeDialog(); render();
    });
  }

  function combine() {
    var chosen = state.columns.filter(function (c) { return selection.has(c.key); });
    if (chosen.length < 2) return;
    openDialog('Combine columns',
      '<label>Combined column name<input id="combined-name" maxlength="60" value="' + esc(chosen.map(function (c) { return c.label; }).join(' & ').slice(0, 60)) + '"></label>'
      + '<label>Separator<select id="separator"><option value=" · ">Middle dot ·</option><option value=" – ">Dash –</option><option value=", ">Comma ,</option><option value="newline">New line</option></select></label>'
      + '<div class="preview-box" id="combine-preview"></div>',
      '<button data-close>Cancel</button><button class="primary" id="apply-combine">Combine columns</button>');
    var sep = function () { return $('separator').value === 'newline' ? '\n' : $('separator').value; };
    var preview = function () { var r = reportRows()[0] || {}; $('combine-preview').textContent = chosen.map(function (c) { return value(r, c); }).join(sep()); };
    preview();
    $('separator').addEventListener('change', preview);
    $('apply-combine').addEventListener('click', function () {
      var label = $('combined-name').value.trim();
      if (!label) return $('combined-name').focus();
      var first = state.columns.findIndex(function (c) { return selection.has(c.key); });
      var sources = Array.from(new Set([].concat.apply([], chosen.map(function (c) { return c.sources; }))));
      state.columns = state.columns.filter(function (c) { return !selection.has(c.key); });
      state.columns.splice(first, 0, {key: 'combined_' + Date.now(), label: label, sources: sources, separator: sep()});
      selection.clear(); markDirty(); closeDialog(); render(); toast('Columns combined.');
    });
  }
  $('combine').addEventListener('click', combine);
  $('clear-selection').addEventListener('click', function () { selection.clear(); renderChips(); });
  function setEditor(open) {
    $('column-editor').hidden = !open;
    $('edit-columns').textContent = open ? 'Edit columns ⌃' : 'Edit columns ⌄';
    $('edit-columns').setAttribute('aria-expanded', String(open));
    requestAnimationFrame(fitPageSize);
  }
  $('edit-columns').addEventListener('click', function () { setEditor($('column-editor').hidden); });
  $('done-columns').addEventListener('click', function () { setEditor(false); });

  // ---- filters ------------------------------------------------------------
  FILTERS.forEach(function (k) {
    $('f-' + k).addEventListener('change', function () {
      state.filters[k] = this.value;
      // Another year has other semesters: let it open on its own running one.
      if (k === 'year') delete state.filters.term;
      page = 1; load();
    });
  });
  $('search').addEventListener('input', function () { state.filters.search = this.value; page = 1; markDirty(); render(); });
  $('reset-filters').addEventListener('click', function () {
    state.filters = {search: ''}; $('search').value = ''; page = 1; load();
  });
  $('row-mode').addEventListener('change', function () { state.mode = this.value; page = 1; markDirty(); render(); });
  $('prev-page').addEventListener('click', function () { page--; render(); });
  $('next-page').addEventListener('click', function () { page++; render(); });

  // ---- saving -------------------------------------------------------------
  function body(s) { return {name: s.name, mode: s.mode, columns: s.columns, filters: s.filters}; }
  function save() {
    $('save-table').disabled = true;
    return RB.api('PUT', url('update', state.id), body(state)).then(function (d) {
      saved = d.table; state.name = saved.name;
      tables = tables.map(function (t) { return t.id === saved.id ? saved : t; });
      setDirty(false); refreshSaved(); toast('Table saved.');
    }).catch(function (e) { $('save-table').disabled = false; toast(e.message); throw e; });
  }
  $('save-table').addEventListener('click', function () { save().catch(function () {}); });
  function go(href) { setDirty(false); location.href = href; }
  function withUnsaved(action) {
    if (!dirty) return action();
    openDialog('Unsaved changes', '<p>Save your current table before continuing?</p>', '<button id="discard">Discard changes</button><button id="save-continue" class="primary">Save &amp; continue</button>');
    $('discard').addEventListener('click', function () { closeDialog(); action(); });
    $('save-continue').addEventListener('click', function () { save().then(function () { closeDialog(); action(); }).catch(function () {}); });
  }
  $('saved-table').addEventListener('change', function () {
    var next = this.value;
    this.value = state.id;
    withUnsaved(function () { go(url('builder', next)); });
  });

  function nameDialog(mode) {
    var titles = {rename: 'Rename table', 'new': 'Create custom table', duplicate: 'Duplicate table'};
    var start = mode === 'rename' ? state.name : unique(mode === 'new' ? 'Untitled report' : state.name + ' — copy');
    openDialog(titles[mode], '<label>Table name<input id="table-name" maxlength="70" value="' + esc(start) + '"></label>',
      '<button data-close>Cancel</button><button id="confirm-name" class="primary">' + (mode === 'rename' ? 'Rename' : 'Create table') + '</button>');
    $('confirm-name').addEventListener('click', function () {
      var btn = this, name = $('table-name').value.trim();
      if (!name) return $('table-name').focus();
      if (taken(name, mode === 'rename' ? state.id : null)) return toast('A saved table already has that name.');
      if (mode === 'rename') { state.name = name; markDirty(); refreshSaved(); closeDialog(); return toast('Table renamed. Save changes to keep it.'); }
      var copy = mode === 'new'
        ? {name: name, mode: 'students', columns: RB.columns(['id', 'student', 'team', 'course', 'section'], fields), filters: state.filters}
        : Object.assign(body(state), {name: name});
      btn.disabled = true;
      RB.api('POST', URLS.store, copy).then(function (d) { go(d.url); }).catch(function (e) { btn.disabled = false; toast(e.message); });
    });
  }
  $('new-table').addEventListener('click', function () { withUnsaved(function () { nameDialog('new'); }); });
  $('rename-table').addEventListener('click', function () { closeMenus(); nameDialog('rename'); });
  $('duplicate-table').addEventListener('click', function () { closeMenus(); nameDialog('duplicate'); });
  $('delete-table').addEventListener('click', function () {
    closeMenus();
    openDialog('Delete saved table?', '<p>Delete “' + esc(saved.name) + '”?</p>', '<button data-close>Cancel</button><button class="danger" id="confirm-delete">Delete table</button>');
    $('confirm-delete').addEventListener('click', function () {
      RB.api('DELETE', url('destroy', state.id)).then(function (d) { go(d.url); }).catch(function (e) { toast(e.message); });
    });
  });
  $('split-columns').addEventListener('click', function () {
    closeMenus();
    var count = 0, seen = new Set();
    state.columns = [].concat.apply([], state.columns.map(function (c) {
      if (c.sources.length === 1) return [c];
      count++;
      return c.sources.map(function (k) { return {key: k, label: fields[k], sources: [k], separator: ' · '}; });
    })).filter(function (c) { if (seen.has(c.key)) return false; seen.add(c.key); return true; });
    if (!count) return toast('There are no combined columns in this table.');
    markDirty(); render(); toast('Combined columns split back into separate fields.');
  });

  // ---- menus ------------------------------------------------------------
  function closeMenus() {
    [['table-options', 'table-menu'], ['export', 'export-menu']].forEach(function (p) { $(p[1]).hidden = true; $(p[0]).setAttribute('aria-expanded', 'false'); });
  }
  [['table-options', 'table-menu'], ['export', 'export-menu']].forEach(function (p) {
    $(p[0]).addEventListener('click', function () {
      var open = $(p[1]).hidden;
      closeMenus();
      $(p[1]).hidden = !open;
      $(p[0]).setAttribute('aria-expanded', String(open));
    });
  });
  document.addEventListener('click', function (e) { if (!e.target.closest('.menu-wrap')) closeMenus(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenus(); });

  // ---- export -------------------------------------------------------------
  // A leading = + @ - would run as a formula in Excel.
  function csvCell(v) { var s = String(v == null ? '' : v); if (/^[=+@-]/.test(s)) s = "'" + s; return '"' + s.replace(/"/g, '""') + '"'; }
  $('export-csv').addEventListener('click', function () {
    closeMenus();
    var rows = reportRows();
    var csv = '﻿' + [state.columns.map(function (c) { return csvCell(c.label); }).join(',')].concat(rows.map(function (r) {
      return state.columns.map(function (c) { return csvCell(value(r, c)); }).join(',');
    })).join('\r\n');
    var href = URL.createObjectURL(new Blob([csv], {type: 'text/csv;charset=utf-8;'})), a = document.createElement('a');
    a.href = href; a.download = (state.name.replace(/[^a-z0-9 -]/gi, '').trim() || 'report') + '.csv'; a.click();
    setTimeout(function () { URL.revokeObjectURL(href); }, 1000);
    toast('Exported all ' + rows.length + ' filtered records.');
  });
  $('export-print').addEventListener('click', function () {
    closeMenus();
    var picked = FILTERS.map(function (k) { var s = $('f-' + k); return s.options[s.selectedIndex] ? s.options[s.selectedIndex].text : ''; });
    $('print-title').textContent = state.name;
    $('print-meta').textContent = picked.concat($('record-count').textContent).filter(Boolean).join(' · ');
    render(true);
    requestAnimationFrame(function () { window.print(); });
  });
  window.addEventListener('afterprint', function () { render(); });

  // ---- horizontal scroll bar ---------------------------------------------
  var scroller = $('report-scroll'), track = $('scroll-track'), thumb = $('scroll-thumb'), thumbStart = null;
  function syncScroll() {
    var w = track.clientWidth, max = scroller.scrollWidth - scroller.clientWidth;
    thumb.style.width = Math.max(25, w * Math.min(1, scroller.clientWidth / scroller.scrollWidth)) + 'px';
    thumb.style.left = (max > 0 ? (w - thumb.offsetWidth) * scroller.scrollLeft / max : 0) + 'px';
    $('scroll-left').disabled = scroller.scrollLeft <= 1;
    $('scroll-right').disabled = scroller.scrollLeft >= max - 1;
  }
  $('scroll-left').addEventListener('click', function () { scroller.scrollBy({left: -300, behavior: 'smooth'}); });
  $('scroll-right').addEventListener('click', function () { scroller.scrollBy({left: 300, behavior: 'smooth'}); });
  scroller.addEventListener('scroll', syncScroll);
  new ResizeObserver(function () { syncScroll(); fitPageSize(); }).observe(scroller);
  thumb.addEventListener('pointerdown', function (e) { thumbStart = {x: e.clientX, left: scroller.scrollLeft}; thumb.setPointerCapture(e.pointerId); e.stopPropagation(); });
  thumb.addEventListener('pointermove', function (e) {
    if (!thumbStart) return;
    var span = track.clientWidth - thumb.offsetWidth;
    if (span) scroller.scrollLeft = thumbStart.left + (e.clientX - thumbStart.x) * (scroller.scrollWidth - scroller.clientWidth) / span;
  });
  thumb.addEventListener('pointerup', function () { thumbStart = null; });
  thumb.addEventListener('lostpointercapture', function () { thumbStart = null; });
  track.addEventListener('click', function (e) {
    if (e.target !== track) return;
    var r = track.getBoundingClientRect();
    scroller.scrollLeft = (e.clientX - r.left) / r.width * (scroller.scrollWidth - scroller.clientWidth);
  });

  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  $('row-mode').value = state.mode;
  $('search').value = state.filters.search || '';
  refreshSaved();
  render();
  load();
})();
</script>
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>
