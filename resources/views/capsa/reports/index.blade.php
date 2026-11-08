<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Reports - CapsTRACK Admin</title>
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
    <nav class="crumbs" aria-label="Breadcrumb"><span class="here" aria-current="page">Reports</span></nav>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="sk" style="height:90px;border-radius:10px;margin-bottom:12px"></div>
    <div class="sk" style="height:420px;border-radius:10px"></div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content rb home">
    <div class="heading"><h1>Reports</h1><button class="primary" id="create-report">＋ Create report</button></div>

    <form method="GET" action="{{ route('capsa.reports') }}" class="card home-filter" id="period" aria-label="Report period and scope">
      <div class="filters">
        <label><span>School year</span>
          <select name="year" id="home-year" data-resets-term>
            @foreach ($ctx['years'] as $y)
              <option value="{{ $y }}" @selected($y === $ctx['year'])>{{ $y }}{{ $y === $ctx['thisYear'] ? ' (current)' : '' }}</option>
            @endforeach
          </select>
        </label>
        <label><span>Semester</span>
          <select name="term" id="home-term">
            @foreach ($ctx['termOptions'] as $v => $label)
              <option value="{{ $v }}" @selected((string) $v === $ctx['term'])>{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <label><span>Program</span>
          <select name="program" id="home-program" @disabled(count($ctx['programOptions']) === 1)>
            @foreach ($ctx['programOptions'] as $v => $label)
              <option value="{{ $v }}" @selected((string) $v === $ctx['program'])>{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <label><span>Section</span>
          <select name="section" id="home-section">
            @foreach ($ctx['sectionOptions'] as $v => $label)
              <option value="{{ $v }}" @selected((string) $v === $ctx['section'])>{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <noscript><button type="submit">Apply</button></noscript>
      </div>
    </form>

    <section class="card home-section saved" aria-labelledby="saved-title">
      <div class="section-heading">
        <div class="title-tools">
          <span class="icon-box"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 9v12"/></svg></span>
          <h2 id="saved-title">Your saved tables</h2>
        </div>
        <input id="find-table" type="search" placeholder="Find a saved table" aria-label="Find a saved table">
      </div>
      <div class="saved-rows">
        <div class="saved-header"><span>Table name</span><span>Rows</span><span>Columns</span><span class="updated-cell">Updated</span><span></span></div>
        <div id="saved-rows"></div>
      </div>
      <div class="menu row-menu" id="row-menu" role="menu" hidden>
        <button type="button" role="menuitem" id="rename-saved"><svg class="mi blue" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>Rename table</button>
        <button type="button" role="menuitem" id="duplicate-saved"><svg class="mi purple" viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg>Duplicate table</button>
        <button type="button" role="menuitem" class="danger" id="delete-saved"><svg class="mi" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 11v6M14 11v6"/></svg>Delete table</button>
      </div>
    </section>

    <section class="card home-section tpl" aria-labelledby="tpl-title">
      <div class="section-heading">
        <div class="title-tools">
          <span class="icon-box purple"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h10l4 4v14H5Z"/><path d="M15 3v5h4M8 12h8M8 16h6"/></svg></span>
          <h2 id="tpl-title">Start from a template</h2>
        </div>
      </div>
      <div class="template-grid" id="templates"></div>
    </section>

    @include('capsa.reports._rb')
  </div>
  </div>
</div>

@include('capsa.partials.skeleton-toggle', ['revealDisplay' => 'flex'])
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var $ = RB.$, esc = RB.esc;
  var saved = @json($tables), templates = @json($templates), fields = @json($fields), openQuery = @json((object) $openQuery);
  var storeUrl = @json(route('capsa.reports.tables.store'));
  var builderUrl = @json(route('capsa.reports.builder', '__ID__')), updateUrl = @json(route('capsa.reports.tables.update', '__ID__')), destroyUrl = @json(route('capsa.reports.tables.destroy', '__ID__'));
  var KIND = {students: 'Students', teams: 'Teams', experts: 'Experts'};
  var ICON = {
    Experts: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M17 5a3 3 0 0 1 0 6M18 15a5 5 0 0 1 3 5"/></svg>',
    Students: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m2 9 10-5 10 5-10 5Z"/><path d="M6 12v6c4 3 8 3 12 0v-6M22 9v8"/></svg>',
    other: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h10l4 4v14H5Z"/><path d="M15 3v5h4M8 12h8M8 16h6"/></svg>'
  };

  // Home's period picks win over a table's saved one only when Home's filters were set.
  function openLink(id) {
    var url = builderUrl.replace('__ID__', id), q = new URLSearchParams(openQuery).toString();
    return q ? url + '?' + q : url;
  }
  function unique(name) {
    var candidate = name, n = 2;
    while (saved.some(function (s) { return s.name.toLowerCase() === candidate.toLowerCase(); })) candidate = name + ' (' + (n++) + ')';
    return candidate;
  }
  function updated(s) { var d = new Date(s.updated); return isNaN(d) ? '' : d.toLocaleDateString(undefined, {month: 'short', day: 'numeric'}); }

  function render() {
    var query = $('find-table').value.toLowerCase();
    var rows = saved.filter(function (s) { return s.name.toLowerCase().indexOf(query) !== -1; });
    $('saved-rows').innerHTML = rows.map(function (s) {
      return '<div class="saved-row"><span class="saved-name"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 9v12"/></svg><span>' + esc(s.name) + '</span></span>'
        + '<span class="kind-cell">' + KIND[s.mode] + '</span><span class="count-cell">' + s.columns.length + ' columns</span>'
        + '<span class="muted updated-cell">' + esc(updated(s)) + '</span>'
        + '<div class="row-actions"><button type="button" class="small" data-open="' + s.id + '">Open builder</button>'
        + '<button type="button" class="small ghost" data-options="' + s.id + '" aria-label="Options for ' + esc(s.name) + '">⋯</button></div></div>';
    }).join('') || '<div class="empty-home">' + (saved.length ? 'No saved tables match your search.' : 'No saved tables yet.') + '</div>';
  }
  $('saved-rows').addEventListener('click', function (e) {
    var open = e.target.closest('[data-open]'), opts = e.target.closest('[data-options]');
    if (open) location.href = openLink(open.dataset.open);
    if (opts) menuTarget && menuTarget.btn === opts ? closeMenu() : openMenu(opts);
  });

  // ⋯ opens a small menu under the button; fixed so the scrolling list can't clip it.
  var menu = $('row-menu'), menuTarget = null;
  function openMenu(btn) {
    closeMenu();
    menuTarget = {btn: btn, table: saved.find(function (s) { return s.id === +btn.dataset.options; })};
    btn.setAttribute('aria-expanded', 'true');
    menu.hidden = false;
    var r = btn.getBoundingClientRect(), top = r.bottom + 5;
    if (top + menu.offsetHeight > innerHeight - 8) top = r.top - menu.offsetHeight - 5;
    menu.style.top = top + 'px';
    menu.style.left = Math.max(8, r.right - menu.offsetWidth) + 'px';
  }
  function closeMenu() {
    if (menuTarget) menuTarget.btn.setAttribute('aria-expanded', 'false');
    menu.hidden = true; menuTarget = null;
  }
  function menuAction(id, fn) {
    $(id).addEventListener('click', function () { var s = menuTarget.table; closeMenu(); fn(s); });
  }
  menuAction('rename-saved', function (s) { nameSaved(s, false); });
  menuAction('duplicate-saved', function (s) { nameSaved(s, true); });
  menuAction('delete-saved', deleteSaved);
  document.addEventListener('click', function (e) { if (menuTarget && !e.target.closest('#row-menu, [data-options]')) closeMenu(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenu(); });
  document.querySelector('.saved-rows').addEventListener('scroll', closeMenu);
  addEventListener('resize', closeMenu);

  function create(template) {
    RB.openDialog(template ? 'Use ' + esc(template.name) : 'Create report',
      '<label>Report name<input id="report-name" maxlength="70" value="' + esc(unique(template ? template.name : 'Untitled report')) + '"></label>'
      + '<label>Starting layout<select id="starting-template"><option value="blank">Custom table — basic fields</option>'
      + templates.map(function (t) { return '<option value="' + t.key + '"' + (template && t.key === template.key ? ' selected' : '') + '>' + esc(t.name) + '</option>'; }).join('') + '</select></label>',
      '<button type="button" data-close>Cancel</button><button type="button" class="primary" id="create-and-open">Create &amp; open builder</button>');
    $('create-and-open').addEventListener('click', function () {
      var btn = this, name = $('report-name').value.trim();
      if (!name) return $('report-name').focus();
      var t = templates.find(function (t) { return t.key === $('starting-template').value; });
      var filters = {};
      ['year', 'term', 'program', 'section'].forEach(function (k) { var el = $('period').elements[k]; if (el && !el.disabled) filters[k] = el.value; });
      btn.disabled = true;
      RB.api('POST', storeUrl, {name: name, mode: t ? t.mode : 'students', columns: RB.columns(t ? t.fields : ['id', 'student', 'team', 'course', 'section'], fields), filters: filters})
        .then(function (d) { location.href = d.url; })
        .catch(function (err) { btn.disabled = false; RB.toast(err.message); });
    });
  }

  function deleteSaved(s) {
    RB.openDialog('Delete saved table?', '<p>Delete “' + esc(s.name) + '”?</p>',
      '<button type="button" data-close>Cancel</button><button type="button" class="danger" id="confirm-delete">Delete table</button>');
    $('confirm-delete').addEventListener('click', function () {
      RB.api('DELETE', destroyUrl.replace('__ID__', s.id)).then(function () {
        saved.splice(saved.indexOf(s), 1); render(); RB.closeDialog(); RB.toast('Table deleted.');
      }).catch(function (err) { RB.toast(err.message); });
    });
  }
  function nameSaved(s, copy) {
    RB.openDialog(copy ? 'Duplicate table' : 'Rename table',
      '<label>Table name<input id="saved-name" maxlength="70" value="' + esc(copy ? unique(s.name + ' — copy') : s.name) + '"></label>',
      '<button type="button" data-close>Cancel</button><button type="button" class="primary" id="apply-name">' + (copy ? 'Duplicate' : 'Rename') + '</button>');
    $('apply-name').addEventListener('click', function () {
      var name = $('saved-name').value.trim();
      if (!name) return $('saved-name').focus();
      var call = copy
        ? RB.api('POST', storeUrl, {name: name, mode: s.mode, columns: s.columns, filters: s.filters})
        : RB.api('PUT', updateUrl.replace('__ID__', s.id), {name: name});
      call.then(function (d) {
        if (copy) saved.unshift(d.table); else saved[saved.indexOf(s)] = d.table;
        render(); RB.closeDialog(); RB.toast(copy ? 'Table duplicated.' : 'Table renamed.');
      }).catch(function (err) { RB.toast(err.message); });
    });
  }

  $('templates').innerHTML = templates.map(function (t, i) {
    return '<button type="button" class="template" data-template="' + t.key + '"><span class="icon-box' + (i % 2 ? ' purple' : '') + '">' + (ICON[t.group] || ICON.other) + '</span>'
      + '<span class="template-copy"><span class="category">' + esc(t.group) + '</span><strong>' + esc(t.name) + '</strong><span class="description">' + esc(t.description) + '</span></span>'
      + '<span class="chevron" aria-hidden="true">›</span></button>';
  }).join('');
  $('templates').addEventListener('click', function (e) {
    var b = e.target.closest('[data-template]');
    if (b) create(templates.find(function (t) { return t.key === b.dataset.template; }));
  });
  $('create-report').addEventListener('click', function () { create(null); });
  $('find-table').addEventListener('input', render);

  var form = $('period');
  form.querySelectorAll('select').forEach(function (select) {
    select.addEventListener('change', function () {
      // Another year has other semesters: let it open on its own running one.
      if (select.hasAttribute('data-resets-term')) form.elements.term.disabled = true;
      form.submit();
    });
  });
  render();
})();
</script>
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>
