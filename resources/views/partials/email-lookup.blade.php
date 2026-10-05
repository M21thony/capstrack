{{--
  Turns an email box into one that suggests people already in CapsTrack.

  Typing a whole address and hoping is how the wrong person gets added. This
  makes the box behave the way people expect from Gmail: type a few letters,
  see who matches, pick one - and see where they already are, so pulling a
  student out of a colleague's section is a visible decision.

  Only for the add-one-at-a-time boxes. The roster upload already carries every
  field for every row, so there is nothing to look up there.

  Usage:  @include('partials.email-lookup', ['input' => 'capsaEmail', 'kind' => 'expert'])
  and optionally 'nameInput' => 'capsaName' to have the name filled in too.
--}}
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  const input = document.getElementById(@json($input));
  if (!input) return;

  const kind = @json($kind ?? 'expert');
  const nameInput = @json($nameInput ?? null);
  // Optional: id of a box per field, filled in when somebody is picked. This
  // is what makes re-adding a student you removed a matter of choosing their
  // section rather than retyping everything you already had.
  const fillMap = @json($fields ?? []);
  const url = @json(route('directory.people'));

  // The list hangs off a wrapper so it lands under the box wherever the box
  // happens to sit, rather than measuring itself against the page.
  const wrap = document.createElement('div');
  wrap.className = 'lookup-wrap';
  input.parentNode.insertBefore(wrap, input);
  wrap.appendChild(input);

  const list = document.createElement('div');
  list.className = 'lookup-list';
  list.hidden = true;
  wrap.appendChild(list);

  let items = [];
  let active = -1;
  let timer = null;
  let lastQuery = '';

  function close() { list.hidden = true; active = -1; }

  function choose(i) {
    const person = items[i];
    if (!person) return;

    input.value = person.email;
    if (nameInput) {
      const el = document.getElementById(nameInput);
      // Only fill a name nobody has typed over.
      if (el && !el.value.trim()) el.value = person.name;
    }

    Object.keys(fillMap).forEach(function (key) {
      const el = document.getElementById(fillMap[key]);
      // Same rule: never overwrite something already typed.
      if (el && !el.value.trim() && person[key]) el.value = person[key];
    });

    close();
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function draw() {
    if (!items.length) {
      // Saying nothing was found is worth a row: a silent empty list reads as
      // "still loading", and this is also where "they are not in the system
      // yet, type the address in full" belongs.
      // A box that shows the school domain itself only needs the name part.
      const next = input.closest('.email-field')
        ? 'Type their name part to add someone new.'
        : 'Type the full address to add someone new.';
      list.innerHTML = '<div class="lookup-empty">Nobody in CapsTrack matches that. ' + next + '</div>';
      list.hidden = false;
      return;
    }

    list.innerHTML = items.map(function (p, i) {
      return '<button type="button" class="lookup-row' + (i === active ? ' on' : '') + '" data-i="' + i + '">'
        + '<span class="lookup-name">' + esc(p.name) + '</span>'
        + '<span class="lookup-mail">' + esc(p.email) + '</span>'
        + '<span class="lookup-detail">' + esc(p.detail) + '</span>'
        + '</button>';
    }).join('');
    list.hidden = false;
  }

  function esc(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function search() {
    const q = input.value.trim();
    if (q === lastQuery) return;
    lastQuery = q;

    if (q.length < 3) { close(); return; }

    fetch(url + '?q=' + encodeURIComponent(q) + '&kind=' + kind, {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    })
      .then(function (r) { return r.ok ? r.json() : { people: [] }; })
      .then(function (data) {
        // A slower earlier request must not overwrite a later one's results.
        if (input.value.trim() !== q) return;
        items = data.people || [];
        active = -1;
        draw();
      })
      .catch(close);
  }

  input.setAttribute('autocomplete', 'off');

  input.addEventListener('input', function () {
    clearTimeout(timer);
    timer = setTimeout(search, 180);
  });

  input.addEventListener('keydown', function (e) {
    if (list.hidden || !items.length) return;

    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      active = (active + (e.key === 'ArrowDown' ? 1 : items.length - 1)) % items.length;
      draw();
    } else if (e.key === 'Enter' && active >= 0) {
      e.preventDefault();
      choose(active);
    } else if (e.key === 'Escape') {
      close();
    }
  });

  list.addEventListener('mousedown', function (e) {
    // mousedown, not click: blur would close the list first.
    const row = e.target.closest('.lookup-row');
    if (row) { e.preventDefault(); choose(Number(row.dataset.i)); }
  });

  input.addEventListener('blur', function () { setTimeout(close, 120); });
})();
</script>
