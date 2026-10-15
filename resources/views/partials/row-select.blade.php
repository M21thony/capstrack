{{--
  Turns a table into one you can act on several rows at once.

  Nothing appears until something is ticked: with no selection the page is for
  looking at, and the moment there is one the table's own heading row becomes
  what you can do to those rows. That way there is never a greyed-out "Edit"
  waiting on a selection that is not there - and nothing on the page moves,
  which a bar opening above the table could not manage.

  The bulk actions are supplied by the page, because who may change what is not
  the same on both sides - a CaPSA sets roles, an admin sets roles and sections,
  and how many teams an expert takes is the expert's own answer on their
  profile, which neither of them can touch.

  Usage:
    @include('partials.row-select', [
      'table'   => '.table',              // the table to wire
      'noun'    => 'expert',              // "3 experts selected"
      'actions' => [                      // buttons in the bar
        ['id' => 'bulkRoles', 'label' => 'Set roles'],
        ['id' => 'bulkRemove', 'label' => 'Remove', 'danger' => true],
      ],
    ])

  The page defines what each button does:  onBulk('bulkRoles', ids => { ... })
--}}
<div class="selbar" id="selbar" hidden>
  <span class="selbar-count" id="selbarCount">0 selected</span>
  <span class="selbar-sep"></span>
  @foreach ($actions as $action)
    {{-- 'icon' is the inside of a 24x24 stroke <svg>, written in the page's
         own source - never anything a user typed. --}}
    <button type="button" class="btn btn-ghost selbar-act{{ ($action['danger'] ?? false) ? ' selbar-danger' : '' }}"
            id="{{ $action['id'] }}">@isset($action['icon'])<svg viewBox="0 0 24 24" aria-hidden="true">{!! $action['icon'] !!}</svg>@endisset{{ $action['label'] }}</button>
  @endforeach
  <button type="button" class="selbar-clear" id="selbarClear">Clear</button>
</div>

<script nonce="{{ Vite::cspNonce() }}">
// Deferred, because the bar is included above the table it wires - reading the
// DOM at this point would find nothing and silently do nothing.
(function () {
  // Registered synchronously, because the page calls onBulk() further down the
  // document - long before DOMContentLoaded. Defining it inside the deferred
  // start would make every registration a silent no-op.
  const handlers = {};
  window.onBulk = function (id, fn) { handlers[id] = fn; };

  // Wired the moment the parser is past the end of the table - something
  // after it has arrived - which is before the page is first drawn. Wired at
  // DOMContentLoaded instead, the tick box column arrived after the page was
  // on screen, and every row narrowed, re-wrapped and moved.
  let started = false;
  if (document.readyState === 'loading') {
    const pastTable = function () {
      const table = document.querySelector(@json($table));
      for (let el = table; el && el !== document.body; el = el.parentNode) {
        if (el.nextSibling) return true;
      }
      return false;
    };
    const watch = new MutationObserver(function () {
      if (pastTable()) { watch.disconnect(); start(); }
    });
    watch.observe(document.documentElement, { childList: true, subtree: true });
    document.addEventListener('DOMContentLoaded', function () { watch.disconnect(); start(); });
  } else {
    start();
  }

function start() {
  if (started) return;
  const table = document.querySelector(@json($table));
  if (!table) return;
  started = true;

  const noun = @json($noun);
  const bar = document.getElementById('selbar');
  const count = document.getElementById('selbarCount');

  const TICK = '<span class="rs-tick"><svg viewBox="0 0 24 24">'
    + '<polyline points="20 6 9 17 4 12"/></svg></span>';

  function cell(checked, all) {
    const label = document.createElement(all ? 'th' : 'td');
    label.className = 'rs-cell';
    label.innerHTML = '<label class="rs-box"><input type="checkbox"' + (checked ? ' checked' : '')
      + (all ? ' data-all="1"' : '') + '>' + TICK + '</label>';
    return label;
  }

  // A row the page marked as not yours to change gets no checkbox at all -
  // offering one and refusing the action afterwards is worse than not offering.
  const rows = Array.from(table.querySelectorAll('tbody tr'));

  const head = table.querySelector('thead');
  const headRow = head.querySelector('tr');

  table.querySelectorAll('thead tr').forEach(function (tr) {
    tr.insertBefore(cell(false, true), tr.firstElementChild);
  });

  // The bar becomes a second heading row and the two swap places, so the
  // table keeps its height and the row under the cursor stays there. It is
  // built here rather than written in the markup because only this file knows
  // how many columns the page's table has.
  const barRow = document.createElement('tr');
  barRow.className = 'rs-bar';
  barRow.hidden = true;
  barRow.appendChild(cell(false, true));

  const barCell = document.createElement('th');
  barCell.colSpan = Math.max(1, headRow.children.length - 1);
  barCell.appendChild(bar);
  bar.hidden = false;
  barRow.appendChild(barCell);
  head.appendChild(barRow);

  rows.forEach(function (tr) {
    if (tr.dataset.noSelect === '1') {
      const spacer = document.createElement('td');
      spacer.className = 'rs-cell';
      tr.insertBefore(spacer, tr.firstElementChild);
      return;
    }
    tr.insertBefore(cell(false, false), tr.firstElementChild);
  });

  function boxes() {
    return Array.from(table.querySelectorAll('tbody input[type=checkbox]'));
  }

  function selectedRows() {
    return boxes().filter(b => b.checked).map(b => b.closest('tr'));
  }

  function selectedIds() {
    return selectedRows().map(tr => tr.dataset.id).filter(Boolean);
  }

  function refresh() {
    const chosen = selectedRows();
    const all = boxes();
    const any = chosen.length > 0;

    // One row or the other, never both and never neither.
    barRow.hidden = !any;
    headRow.hidden = any;

    count.textContent = chosen.length + ' ' + noun + (chosen.length === 1 ? '' : 's') + ' selected';

    all.forEach(b => b.closest('tr').classList.toggle('rs-on', b.checked));

    // Both heading rows carry one, so whichever is on screen answers.
    table.querySelectorAll('thead input[data-all]').forEach(function (master) {
      master.checked = all.length > 0 && chosen.length === all.length;
      // Neither all nor none, so the box says "some" rather than lying.
      master.indeterminate = any && chosen.length < all.length;
    });
  }

  table.addEventListener('change', function (e) {
    const box = e.target;
    if (box.type !== 'checkbox') return;

    if (box.dataset.all) {
      // Rows the filter has hidden are not on screen, so acting on them would
      // be acting on things nobody can see.
      boxes().forEach(function (b) {
        if (b.closest('tr').style.display !== 'none') b.checked = box.checked;
      });
    }

    refresh();
  });

  document.getElementById('selbarClear').addEventListener('click', function () {
    boxes().forEach(b => { b.checked = false; });
    refresh();
  });

  bar.querySelectorAll('.selbar-act').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const fn = handlers[btn.id];
      if (fn) fn(selectedIds(), selectedRows());
    });
  });

  window.clearSelection = function () {
    boxes().forEach(b => { b.checked = false; });
    refresh();
  };
  window.selectedCount = function () { return selectedRows().length; };

  refresh();
}
})();
</script>
