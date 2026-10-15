{{--
  "Who grades?" - one panelist grading for the others, or everyone on their own.
  Opened by openWhoGrades(data), data being SharedGrading::dialog() for one
  defense. Used by the Team Grading list and the grading sheet.
--}}
@once
<style nonce="{{ Vite::cspNonce() }}">
  .wg-overlay{position:fixed;inset:0;z-index:2100;background:rgba(24,25,31,.55);display:none;align-items:center;justify-content:center;padding:16px}
  .wg-overlay.show{display:flex}
  .wg-box{width:min(520px,100%);max-height:calc(100dvh - 32px);overflow:auto;box-sizing:border-box;background:#fff;border-radius:16px;box-shadow:0 24px 60px rgba(0,0,0,.25);padding:24px;color:var(--text,#1e2128);font-family:'DM Sans','DM Sans Fallback',sans-serif}
  .wg-box h2{margin:0;font-size:19px;font-weight:700}
  .wg-box .wg-sub{margin:2px 0 18px;font-size:13px;color:var(--text3,#656b7a)}
  .wg-cards{border:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
  .wg-card{position:relative;display:flex;align-items:center;gap:12px;padding:12px;min-height:64px;box-sizing:border-box;border-radius:12px;cursor:pointer;border:1px solid var(--border,#e0e2e8);background:#fff}
  .wg-card:hover{border-color:var(--border2,#c8cad4)}
  .wg-card:has(input:checked){border:2px solid var(--accent,#c9a82c);background:#faf3d9}
  .wg-card:focus-within{outline:2px solid var(--accent3,#8a6e10);outline-offset:2px}
  .wg-card input{position:absolute;opacity:0;width:1px;height:1px}
  .wg-av{flex:0 0 36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;background:var(--bg4,#eceef2);color:var(--text2,#4a5060)}
  .wg-av svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
  .wg-card:has(input:checked) .wg-av{background:#18191f;color:#fff}
  .wg-card .t{display:flex;flex-direction:column;min-width:0}
  .wg-card .t b{font-size:14px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .wg-card .t small{font-size:12px;color:var(--text3,#656b7a)}
  .wg-with{margin-top:18px}
  .wg-with[hidden]{display:none}
  .wg-with > b{display:block;font-size:13px;margin-bottom:8px}
  .wg-chips{display:flex;flex-wrap:wrap;gap:8px}
  .wg-chip{font:inherit;font-size:13px;font-weight:700;display:inline-flex;align-items:center;gap:6px;padding:0 14px;min-height:38px;border-radius:999px;cursor:pointer;background:#fff;color:var(--text2,#4a5060);border:1px dashed #b5941f}
  .wg-chip[aria-pressed="true"]{background:#18191f;color:#fff;border:1px solid #18191f}
  .wg-chip:disabled{cursor:not-allowed;opacity:.55;border-style:solid;border-color:var(--border,#e0e2e8)}
  .wg-chip svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}
  .wg-chip:focus-visible{outline:2px solid var(--accent3,#8a6e10);outline-offset:2px}
  .wg-solo{margin:8px 0 0;font-size:12.5px;color:var(--text3,#656b7a)}
  .wg-err{margin:14px 0 0;padding:8px 12px;border-radius:8px;font-size:13px;border:1px solid var(--red,#dc2626);background:var(--red-bg,rgba(220,38,38,.07));color:var(--red,#dc2626)}
  .wg-err[hidden]{display:none}
  .wg-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:24px}
  .wg-btn{font:inherit;font-size:13.5px;font-weight:600;display:inline-flex;align-items:center;padding:0 16px;min-height:42px;border-radius:8px;border:1px solid var(--border,#e0e2e8);background:#fff;color:var(--text2,#4a5060);cursor:pointer}
  .wg-btn.primary{font-weight:700;padding:0 20px;background:#18191f;border-color:#18191f;color:#fff}
  .wg-btn:disabled{opacity:.6;cursor:default}
  @media (max-width:520px){.wg-cards{grid-template-columns:1fr}}
</style>

<div class="wg-overlay" id="wg-overlay" onclick="if (event.target === this) closeWhoGrades()">
  <div class="wg-box" role="dialog" aria-modal="true" aria-labelledby="wg-title">
    <h2 id="wg-title">Who grades?</h2>
    <p class="wg-sub" id="wg-sub"></p>
    <fieldset class="wg-cards" id="wg-cards">
      <legend style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">Who grades</legend>
    </fieldset>
    <div class="wg-with" id="wg-with" hidden>
      <b>Graded together with</b>
      <div class="wg-chips" id="wg-chips"></div>
      <p class="wg-solo" id="wg-solo" hidden></p>
    </div>
    <p class="wg-err" id="wg-err" role="alert" hidden></p>
    <div class="wg-actions">
      <button type="button" class="wg-btn" onclick="closeWhoGrades()">Cancel</button>
      <button type="button" class="wg-btn primary" id="wg-save">Save</button>
    </div>
  </div>
</div>

<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var data = null, grader = null, joined = {};
  var $ = function (id) { return document.getElementById(id); };
  var make = function (tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; };
  var name = function (p) { return p.id === data.me ? 'You' : p.name; };

  function render() {
    var cards = $('wg-cards');
    Array.prototype.slice.call(cards.querySelectorAll('.wg-card')).forEach(function (c) { c.remove(); });

    var options = [{ id: null, label: 'Everyone', sub: 'Each grades on their own' }].concat(data.panel.map(function (p) {
      return { id: p.id, label: p.id === data.me ? 'Me' : p.name, sub: p.role + ' grades for all', initials: p.initials };
    }));
    options.forEach(function (o) {
      var label = make('label', 'wg-card');
      var input = make('input'); input.type = 'radio'; input.name = 'wg-grader'; input.checked = o.id === grader;
      input.addEventListener('change', function () { grader = o.id; render(); });
      var av = make('span', 'wg-av');
      if (o.id === null) av.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
      else av.textContent = o.initials;
      var t = make('span', 't'); t.append(make('b', '', o.label), make('small', '', o.sub));
      label.append(input, av, t);
      cards.append(label);
    });

    var with_ = $('wg-with'), chips = $('wg-chips'), solo = $('wg-solo');
    with_.hidden = grader === null;
    chips.innerHTML = '';
    if (grader === null) return;
    var alone = [];
    data.panel.filter(function (p) { return p.id !== grader; }).forEach(function (p) {
      var on = !p.turnedIn && joined[p.id] !== false;
      var b = make('button', 'wg-chip'); b.type = 'button';
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
      b.disabled = p.turnedIn;
      if (p.turnedIn) b.title = 'Already turned in a grade of their own';
      if (on) b.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
      b.append(name(p) + (p.turnedIn ? ' · submitted' : ''));
      b.addEventListener('click', function () { joined[p.id] = !on; render(); });
      chips.append(b);
      if (!on) alone.push(name(p));
    });
    solo.hidden = !alone.length;
    solo.textContent = alone.join(' and ') + ' will grade separately.';
  }

  window.openWhoGrades = function (d) {
    data = d;
    grader = d.grader;
    joined = {};
    d.panel.forEach(function (p) { joined[p.id] = d.grader === null || d.joined.indexOf(p.id) !== -1; });
    $('wg-sub').textContent = d.team + ' · ' + d.type;
    $('wg-err').hidden = true;
    render();
    $('wg-overlay').classList.add('show');
    var first = document.querySelector('#wg-cards input:checked') || document.querySelector('#wg-cards input');
    if (first) first.focus();
  };

  window.closeWhoGrades = function () { $('wg-overlay').classList.remove('show'); };

  $('wg-save').addEventListener('click', function () {
    var btn = this;
    var ids = grader === null ? [] : data.panel.filter(function (p) { return p.id !== grader && !p.turnedIn && joined[p.id] !== false; }).map(function (p) { return p.id; });
    // A grader with nobody joined is everyone grading on their own.
    var body = { grader_id: ids.length ? grader : null, joined: ids };
    btn.disabled = true;
    fetch(data.url, {
      method: 'PUT',
      credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(body),
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        btn.disabled = false;
        if (!res.success) { $('wg-err').textContent = res.message || 'Could not save.'; $('wg-err').hidden = false; return; }
        // Grading yourself, or joined to somebody: the sheet shows which.
        if (body.grader_id === data.me || ids.indexOf(data.me) !== -1) window.location.href = data.sheet;
        else window.location.reload();
      })
      .catch(function () { btn.disabled = false; $('wg-err').textContent = 'Could not reach the server.'; $('wg-err').hidden = false; });
  });
})();
</script>
@endonce
