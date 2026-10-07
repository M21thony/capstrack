{{--
  View Report and Download Excel for the tables on CaPSA, Admin and Expert pages.

  Included once, from each staff sidebar, so every page has it. A table opts in
  with data-export="Name of the report". Nothing else needs wiring: the script
  puts the two buttons above the table, and reads the table itself when one is
  clicked - so what is reported is what is on screen, filters included, and a
  table drawn by JavaScript works the same as one drawn by the server.

    data-export="Title"            the report's name
    data-export-into="#selector"   put the buttons in an existing toolbar instead
                                   of above the table

  View Report sends the table to TableReportController and opens the Reports'
  own screen over it: column picker (drag to reorder), search and sort, Preview
  PDF, and a PDF or Excel download. Download Excel asks which columns first and
  builds the file from the same table (TableExportController).
--}}
<style nonce="{{ Vite::cspNonce() }}">
.tx-bar{display:flex;justify-content:flex-end;flex-wrap:wrap;gap:8px;margin:0 0 8px;flex:none}
.tx-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:8px;border:1px solid var(--border,#e2e4ea);background:var(--bg2,#fff);color:var(--text2,#4a4d57);font:600 12px/1 inherit;font-family:inherit;cursor:pointer;white-space:nowrap;text-decoration:none}
.tx-btn:hover:not(:disabled){border-color:var(--accent,#c9a82c);color:var(--text,#18191f)}
.tx-btn:disabled{opacity:.5;cursor:not-allowed}
.tx-btn svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex:none}
.tx-btn.tx-gold{background:var(--accent,#c9a82c);border-color:var(--accent,#c9a82c);color:#18191f}
.tx-btn.tx-gold:hover:not(:disabled){filter:brightness(.95);color:#18191f}

dialog.tx-dialog{border:0;padding:0;border-radius:14px;width:min(460px,calc(100vw - 24px));max-height:calc(100vh - 32px);max-height:calc(100dvh - 32px);background:var(--bg2,#fff);color:var(--text,#18191f);box-shadow:0 24px 60px rgba(15,23,42,.35);font-family:inherit}
dialog.tx-dialog::backdrop{background:rgba(15,23,42,.5);backdrop-filter:blur(3px)}
.tx-in{display:flex;flex-direction:column;max-height:calc(100vh - 32px);max-height:calc(100dvh - 32px)}
.tx-head{display:flex;align-items:flex-start;gap:12px;padding:18px 22px 12px}
.tx-head h2{margin:0;font-size:17px;font-weight:700}
.tx-head p{margin:2px 0 0;font-size:12.5px;color:var(--text3,#7a7d88)}
.tx-x{margin-left:auto;background:none;border:0;cursor:pointer;color:var(--text3,#7a7d88);padding:4px;line-height:0;border-radius:6px}
.tx-x:hover{color:var(--text,#18191f)}
.tx-x svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round}
.tx-cols-wrap{padding:0 22px 10px;overflow:auto}
.tx-cols-label{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text3,#7a7d88);margin:0 0 6px}
.tx-cols{display:flex;flex-wrap:wrap;gap:6px}
.tx-chip{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:999px;border:1px solid var(--border,#e2e4ea);font-size:12px;cursor:pointer;user-select:none;color:var(--text2,#4a4d57);background:var(--bg2,#fff)}
.tx-chip input{accent-color:#2563eb;margin:0;width:13px;height:13px}
.tx-chip.on{border-color:var(--accent,#c9a82c);background:rgba(201,168,44,.14);color:var(--text,#18191f)}
.tx-quick{display:flex;gap:14px;margin-top:8px}
.tx-quick button{background:none;border:0;padding:0;font:600 12px/1 inherit;font-family:inherit;color:#2563eb;cursor:pointer}
.tx-foot{display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:14px 22px 18px}
.tx-msg{margin-right:auto;font-size:12px;color:#b42318}
@media (max-width:640px){
  .tx-bar{justify-content:stretch}
  .tx-bar .tx-btn{flex:1;justify-content:center;padding:10px 12px}
  dialog.tx-dialog{width:calc(100vw - 16px)}
  .tx-head{padding:16px 16px 10px}
  .tx-cols-wrap{padding:0 16px 10px}
  .tx-foot{padding:12px 16px 16px}
  .tx-foot .tx-btn{padding:11px 14px}
  .tx-chip{padding:8px 12px;font-size:13px}
}
</style>

<dialog class="tx-dialog" id="tx-dialog" aria-labelledby="tx-title">
  <div class="tx-in">
    <div class="tx-head">
      <div><h2 id="tx-title"></h2><p>Tick the columns you want in the Excel file.</p></div>
      <button type="button" class="tx-x" data-tx-close aria-label="Close"><svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
    </div>
    <div class="tx-cols-wrap">
      <p class="tx-cols-label">Columns</p>
      <div class="tx-cols" id="tx-cols"></div>
      <div class="tx-quick"><button type="button" data-tx-all>Select all</button><button type="button" data-tx-first>First column only</button></div>
    </div>
    <div class="tx-foot">
      <span class="tx-msg" id="tx-msg" role="alert"></span>
      <button type="button" class="tx-btn" data-tx-close>Close</button>
      <button type="button" class="tx-btn tx-gold" id="tx-go"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>Download Excel</button>
    </div>
  </div>
</dialog>

<script nonce="{{ Vite::cspNonce() }}">
(function () {
  if (window.__txReady) { return; }
  window.__txReady = true;

  var URL_EXPORT = @json(route('table.export', [], false));
  var URL_REPORT = @json(route('table.report.store', [], false));
  var CSRF = @json(csrf_token());
  var ICON_VIEW = '<svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
  var ICON_DOWN = '<svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>';

  var dlg, state = null;

  function clean(text) {
    return String(text || '').replace(/ /g, ' ').replace(/[ \t]+/g, ' ').replace(/ *\n */g, '\n').replace(/\n{2,}/g, '\n').trim();
  }

  // What a cell says. Fields are read for their value; the rest as it is drawn.
  function cellText(cell) {
    var field = cell.querySelector('select, input:not([type=checkbox]):not([type=radio]):not([type=hidden]), textarea');
    if (field && !clean(cell.innerText)) {
      return clean(field.tagName === 'SELECT' ? (field.options[field.selectedIndex] || {}).text : field.value);
    }
    return clean(cell.innerText != null ? cell.innerText : cell.textContent);
  }

  // The table as it is on screen right now: its columns, and the rows not
  // hidden by a filter. Columns with no heading, a checkbox or "Action" are
  // controls rather than data and are left out.
  function readTable(table) {
    var headRow = table.tHead && table.tHead.rows.length ? table.tHead.rows[table.tHead.rows.length - 1] : null;
    if (!headRow) {
      headRow = Array.prototype.find.call(table.rows, function (r) { return r.querySelector('th'); }) || null;
    }
    var headings = [], skip = [];
    if (headRow) {
      Array.prototype.forEach.call(headRow.cells, function (th) {
        var text = clean(th.innerText != null ? th.innerText : th.textContent).replace(/\n/g, ' ');
        for (var i = 0; i < (th.colSpan || 1); i++) {
          headings.push(text);
          skip.push(!text || /^actions?$/i.test(text) || !!th.querySelector('input[type=checkbox]'));
        }
      });
    }

    var rows = [];
    Array.prototype.forEach.call(table.tBodies, function (body) {
      Array.prototype.forEach.call(body.rows, function (tr) {
        if (tr.hidden || getComputedStyle(tr).display === 'none') { return; }
        var cells = tr.cells;
        // A row that is a message or a group heading spans the table; it is not data.
        if (!cells.length || cells.length !== headings.length) { return; }
        rows.push(Array.prototype.map.call(cells, cellText));
      });
    });

    var columns = [];
    headings.forEach(function (h, i) { if (!skip[i]) { columns.push({ index: i, label: h }); } });
    return { columns: columns, rows: rows };
  }

  function el(tag, cls, html) {
    var e = document.createElement(tag);
    if (cls) { e.className = cls; }
    if (html != null) { e.innerHTML = html; }
    return e;
  }

  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body)
    });
  }

  function titleOf(table) { return table.getAttribute('data-export') || 'Report'; }

  function scopeOf(rows) { return rows + (rows === 1 ? ' row' : ' rows') + ' as shown on the page'; }

  // ---- View Report: the Reports' own screen, opened over this table.
  function viewReport(table, button) {
    var data = readTable(table);
    var label = button.innerHTML;
    button.disabled = true;
    button.textContent = 'Opening...';

    post(URL_REPORT, {
      title: titleOf(table),
      scope: scopeOf(data.rows.length),
      from: location.pathname + location.search,
      headings: data.columns.map(function (c) { return c.label; }),
      rows: data.rows.map(function (row) { return data.columns.map(function (c) { return row[c.index]; }); })
    }).then(function (response) {
      if (!response.ok) { throw new Error('failed'); }
      return response.json();
    }).then(function (saved) {
      location.href = saved.url;
    }).catch(function () {
      button.disabled = false;
      button.innerHTML = label;
      window.ctToast('Could not open the report. Try again.', 'error');
    });
  }

  // ---- Download Excel: pick the columns, then the file.
  function chosenColumns() {
    return state.data.columns.filter(function (c) { return state.on[c.index]; });
  }

  function drawCols() {
    var box = document.getElementById('tx-cols');
    box.innerHTML = '';
    state.data.columns.forEach(function (c) {
      var chip = el('label', 'tx-chip' + (state.on[c.index] ? ' on' : ''));
      var cb = el('input');
      cb.type = 'checkbox';
      cb.checked = !!state.on[c.index];
      cb.addEventListener('change', function () {
        state.on[c.index] = cb.checked;
        chip.classList.toggle('on', cb.checked);
        sync();
      });
      chip.appendChild(cb);
      chip.appendChild(document.createTextNode(c.label));
      box.appendChild(chip);
    });
  }

  function sync() {
    var none = !chosenColumns().length;
    document.getElementById('tx-msg').textContent = none ? 'Tick at least one column.' : '';
    document.getElementById('tx-go').disabled = none;
  }

  function pickColumns(table) {
    var data = readTable(table);
    state = { title: titleOf(table), data: data, on: {} };
    data.columns.forEach(function (c) { state.on[c.index] = true; });
    document.getElementById('tx-title').textContent = state.title;
    drawCols();
    sync();
    if (!dlg.open) { dlg.showModal(); }
  }

  function fileName(response) {
    var match = /filename="?([^";]+)"?/.exec(response.headers.get('Content-Disposition') || '');
    return match ? match[1] : 'report.xlsx';
  }

  function download() {
    var cols = chosenColumns();
    if (!cols.length) { return; }
    var go = document.getElementById('tx-go');
    var msg = document.getElementById('tx-msg');
    go.disabled = true;
    msg.textContent = '';

    post(URL_EXPORT, {
      title: state.title,
      scope: scopeOf(state.data.rows.length),
      headings: cols.map(function (c) { return c.label; }),
      rows: state.data.rows.map(function (row) { return cols.map(function (c) { return row[c.index]; }); })
    }).then(function (response) {
      if (!response.ok) { throw new Error('failed'); }
      return response.blob().then(function (blob) { return { blob: blob, name: fileName(response) }; });
    }).then(function (file) {
      var a = document.createElement('a');
      a.href = URL.createObjectURL(file.blob);
      a.download = file.name;
      document.body.appendChild(a);
      a.click();
      setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
      dlg.close();
    }).catch(function () {
      msg.textContent = 'Could not make the Excel file. Try again.';
    }).then(function () {
      sync();
    });
  }

  function buttonsFor(table) {
    var bar = el('div', 'tx-bar');
    bar.setAttribute('role', 'group');
    bar.setAttribute('aria-label', titleOf(table) + ' report');
    var view = el('button', 'tx-btn', ICON_VIEW + 'View Report');
    var down = el('button', 'tx-btn', ICON_DOWN + 'Download Excel');
    view.type = down.type = 'button';
    view.addEventListener('click', function () { viewReport(table, view); });
    down.addEventListener('click', function () { pickColumns(table); });
    bar.appendChild(view);
    bar.appendChild(down);
    return bar;
  }

  // Above the table, or beside the outermost thing that holds only the table
  // (its card), so the buttons sit over it instead of inside a scroller.
  function place(table) {
    var into = table.getAttribute('data-export-into');
    var host = into ? document.querySelector(into) : null;
    var bar = buttonsFor(table);

    if (host) {
      // An existing toolbar: the two buttons go in as they are, no bar around them.
      while (bar.firstChild) { host.appendChild(bar.firstChild); }
      return;
    }

    var anchor = table;
    while (anchor.parentElement && anchor.parentElement.children.length === 1
      && !anchor.parentElement.matches('body, main, .main, .content')) {
      anchor = anchor.parentElement;
    }
    // A card that holds the table and other things (a footer, a credit line):
    // the buttons go over the card, not inside it against its edge.
    var holder = anchor.parentElement;
    var look = holder ? getComputedStyle(holder) : null;
    if (look && !holder.matches('body, main, .main, .content, #real-content') && holder.parentElement
      && (parseFloat(look.borderTopWidth) > 0 || look.boxShadow !== 'none')) {
      anchor = holder;
    }
    anchor.parentElement.insertBefore(bar, anchor);
  }

  document.addEventListener('DOMContentLoaded', function () {
    dlg = document.getElementById('tx-dialog');
    dlg.addEventListener('click', function (e) { if (e.target === dlg) { dlg.close(); } });
    dlg.querySelectorAll('[data-tx-close]').forEach(function (b) { b.addEventListener('click', function () { dlg.close(); }); });
    dlg.querySelector('[data-tx-all]').addEventListener('click', function () {
      state.data.columns.forEach(function (c) { state.on[c.index] = true; });
      drawCols(); sync();
    });
    dlg.querySelector('[data-tx-first]').addEventListener('click', function () {
      state.data.columns.forEach(function (c, i) { state.on[c.index] = i === 0; });
      drawCols(); sync();
    });
    document.getElementById('tx-go').addEventListener('click', download);

    document.querySelectorAll('table[data-export]').forEach(place);
  });
})();
</script>
