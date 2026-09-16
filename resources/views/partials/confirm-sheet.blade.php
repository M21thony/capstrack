{{--
  Asking "are you sure" in the app's own voice.

  window.confirm() was doing this on three pages. It cannot be read at a
  glance, it cannot show a list of who is about to be affected in any shape
  worth reading, and on several browsers a second one in the same minute
  offers a "don't ask me again" box - which turns the next irreversible
  action into a silent yes.

  Usage, from anywhere on a page that includes this once:

    const ok = await confirmSheet({
      title: 'Remove 4 students from your section?',
      names: ['Abella, Jerome D.', 'Bermudo, Angelo M.'],   // optional
      body: 'Their accounts and history are kept.',
      confirmLabel: 'Remove from section',
      danger: true,
    });
    if (!ok) return;
--}}
<div class="upload-scrim" id="cf-scrim" style="z-index:600" hidden onclick="cfDismiss(event)">
  <div class="upload-sheet cf-sheet" onclick="event.stopPropagation()" role="alertdialog"
       aria-modal="true" aria-labelledby="cf-title">
    <div class="card" style="padding:16px;box-shadow:none;border:0">
      <div class="card-header sheet-head">
        <span class="card-title" id="cf-title"></span>
        <span class="sheet-head-right">
          <button class="sheet-x" type="button" onclick="cfClose(false)" aria-label="Close">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </span>
      </div>

      <div class="cf-names" id="cf-names" hidden></div>
      <p class="section-hint" id="cf-body" style="margin:0 0 16px"></p>

      <div class="cf-actions">
        <button type="button" class="btn" id="cf-cancel" onclick="cfClose(false)">Cancel</button>
        <button type="button" class="btn btn-primary" id="cf-ok" onclick="cfClose(true)"></button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  let settle = null;

  const scrim = document.getElementById('cf-scrim');
  const okBtn = document.getElementById('cf-ok');
  const namesEl = document.getElementById('cf-names');

  window.confirmSheet = function (opts) {
    document.getElementById('cf-title').textContent = opts.title || 'Are you sure?';
    document.getElementById('cf-body').textContent = opts.body || '';

    // Names are set as text, one node each - a student called O'Brien is a
    // name, not markup, and this list is built from what a CaPSA typed.
    namesEl.textContent = '';
    const names = opts.names || [];
    namesEl.hidden = names.length === 0;
    names.forEach(function (name, i) {
      if (i) namesEl.appendChild(document.createTextNode(' · '));
      const b = document.createElement('b');
      b.textContent = name;
      namesEl.appendChild(b);
    });

    okBtn.textContent = opts.confirmLabel || 'Confirm';
    okBtn.classList.toggle('btn-danger-solid', !!opts.danger);
    okBtn.classList.toggle('btn-primary', !opts.danger);

    scrim.hidden = false;
    document.body.style.overflow = 'hidden';
    okBtn.focus();

    return new Promise(function (resolve) { settle = resolve; });
  };

  window.cfClose = function (answer) {
    scrim.hidden = true;
    document.body.style.overflow = '';
    const done = settle;
    settle = null;
    if (done) done(answer);
  };

  window.cfDismiss = function (e) {
    if (e && e.target !== scrim) return;
    cfClose(false);
  };

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !scrim.hidden) cfClose(false);
  });
})();
</script>
