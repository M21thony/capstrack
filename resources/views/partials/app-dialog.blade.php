{{--
  The one dialog.

  Before this there were 103 hand-built overlays and three competing definitions
  of .modal-overlay in admin.css, so no two dialogs agreed on their radius,
  padding or shadow, and none of them handled a keyboard at all: focus stayed
  behind the scrim, Tab walked out into the page underneath, and closing one left
  the caret back at the top of the document.

  It is the small centred shape of the CapsTrack modal (partials/modal): a ring
  icon, the title, a line or two, and two buttons of equal width. On a phone it
  is a sheet from the bottom. Being a native <dialog>, it opens above any other
  pop-up on the page instead of behind it.

    confirmSheet(opts) -> Promise<boolean>
      Asks before something is destroyed. Same options the old
      partials/confirm-sheet.blade.php took, so its callers did not change:
      title, names, body, confirmLabel, danger. Focus opens on CANCEL when
      danger is set, because the safe answer should be the reflex, and a
      destructive dialog ignores a click on the scrim.

    outcomeDialog(opts) -> Promise<'primary'|'secondary'|null>
      Reports a result that has to be read - a refusal carrying the server's own
      words, a partial success, the end of a workflow. It is never closed by a
      timer or by clicking away, and Esc closes it without ever counting as yes.
      Options: tone (error|warn|success|info), title, meta, reason, body, note,
      primaryLabel, secondaryLabel.

  Every string is set as text, never markup: these carry team names, folder names
  and server messages, which are data.
--}}
@once
@include('partials.modal')

<dialog class="cm cm-ask" id="ad-scrim" aria-labelledby="ad-title">
  <div class="cm-box" id="ad-box">
    <span class="cm-ring" id="ad-icon" hidden aria-hidden="true"></span>
    <h2 class="cm-title" id="ad-title"></h2>
    <div class="cm-meta" id="ad-meta" hidden></div>
    <div class="cm-names" id="ad-names" hidden></div>
    <div class="cm-reason" id="ad-reason" hidden></div>
    <p class="cm-text" id="ad-body" hidden></p>
    <div class="cm-note" id="ad-note" hidden></div>
    <div class="cm-pair" id="ad-foot">
      <button class="cm-btn" id="ad-cancel" type="button"></button>
      <button class="cm-btn cm-btn-1" id="ad-ok" type="button"></button>
    </div>
  </div>
</dialog>

<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var GLYPH = {
    error: '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="13"></line><line x1="12" y1="16.5" x2="12" y2="16.5"></line>',
    warn: '<path d="M10.3 3.9L1.8 18.1A2 2 0 0 0 3.5 21h17a2 2 0 0 0 1.7-2.9L13.7 3.9a2 2 0 0 0-3.4 0z"></path><line x1="12" y1="9" x2="12" y2="13.5"></line><line x1="12" y1="17" x2="12" y2="17"></line>',
    success: '<circle cx="12" cy="12" r="10"></circle><polyline points="8 12.5 11 15.5 16 9.5"></polyline>',
    info: '<circle cx="12" cy="12" r="10"></circle><polyline points="12 7 12 12 15.5 14"></polyline>',
  };

  var scrim = document.getElementById('ad-scrim');
  var box = document.getElementById('ad-box');
  var els = {
    icon: document.getElementById('ad-icon'),
    title: document.getElementById('ad-title'),
    meta: document.getElementById('ad-meta'),
    names: document.getElementById('ad-names'),
    reason: document.getElementById('ad-reason'),
    body: document.getElementById('ad-body'),
    note: document.getElementById('ad-note'),
    foot: document.getElementById('ad-foot'),
    cancel: document.getElementById('ad-cancel'),
    ok: document.getElementById('ad-ok'),
  };

  var settle = null;
  var config = {};
  var answer;

  function focusable() {
    return Array.prototype.filter.call(
      box.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'),
      function (el) { return !el.hidden && !el.disabled && el.offsetParent !== null; }
    );
  }

  // CapsModal puts the focus back on the control that opened it, and calls
  // onClose before it returns, so the promise is settled here and now.
  function close(value) {
    if (!settle) return;
    answer = value;
    window.CapsModal.close(scrim);
  }

  function open(cfg) {
    // A dialog opening over a dialog would orphan the first one's promise.
    if (settle) close(cfg.reentryAnswer);

    config = cfg;
    answer = cfg.cancelAnswer;

    scrim.className = 'cm cm-ask' + (cfg.size === 'md' || cfg.size === 'lg' ? ' cm-wide' : '');
    scrim.setAttribute('role', cfg.role);

    if (cfg.tone && GLYPH[cfg.tone]) {
      els.icon.className = 'cm-ring cm-tone-' + cfg.tone;
      els.icon.innerHTML = '<svg viewBox="0 0 24 24">' + GLYPH[cfg.tone] + '</svg>';
      els.icon.hidden = false;
    } else {
      els.icon.hidden = true;
    }

    els.title.textContent = cfg.title || '';
    els.meta.textContent = cfg.meta || '';
    els.meta.hidden = !cfg.meta;

    els.names.textContent = '';
    var names = cfg.names || [];
    els.names.hidden = names.length === 0;
    names.forEach(function (name, i) {
      if (i) els.names.appendChild(document.createTextNode(' · '));
      var b = document.createElement('b');
      b.textContent = name;
      els.names.appendChild(b);
    });

    els.reason.textContent = cfg.reason || '';
    els.reason.hidden = !cfg.reason;

    els.body.textContent = cfg.body || '';
    els.body.hidden = !cfg.body;

    els.note.textContent = cfg.note || '';
    els.note.hidden = !cfg.note;
    els.note.className = 'cm-note' + (cfg.noteWarn ? ' cm-note-warn' : '');

    els.cancel.textContent = cfg.cancelLabel || '';
    els.cancel.hidden = !cfg.cancelLabel;
    els.foot.className = 'cm-pair' + (cfg.cancelLabel ? '' : ' cm-one');
    els.ok.textContent = cfg.okLabel || 'Close';
    els.ok.className = 'cm-btn ' + (cfg.danger ? 'cm-btn-danger' : 'cm-btn-1');

    var target = cfg.focus === 'cancel' && !els.cancel.hidden ? els.cancel : els.ok;

    return new Promise(function (resolve) {
      settle = resolve;
      window.CapsModal.open(scrim, {
        // Destructive confirms and outcome reports both ignore a click outside;
        // only a dialog opened to be read can be dismissed that way.
        dismissible: cfg.dismissible,
        focus: target,
        onClose: function () {
          var done = settle;
          settle = null;
          if (done) done(answer);
        },
      });
    });
  }

  els.ok.addEventListener('click', function () { close(config.okAnswer); });
  els.cancel.addEventListener('click', function () { close(config.cancelAnswer); });

  document.addEventListener('keydown', function (e) {
    if (!scrim.open || !settle) return;

    if (e.key === 'Escape') {
      e.preventDefault();
      close(config.cancelAnswer);
      return;
    }
    if (e.key !== 'Tab') return;

    var items = focusable();
    if (items.length === 0) return;
    var first = items[0];
    var last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  });

  window.confirmSheet = function (opts) {
    opts = opts || {};
    var names = opts.names || [];
    return open({
      role: 'alertdialog',
      size: names.length ? 'md' : 'sm',
      tone: opts.danger ? 'error' : 'info',
      title: opts.title || 'Are you sure?',
      names: names,
      body: opts.body || '',
      note: opts.note || '',
      noteWarn: !!opts.noteWarn,
      okLabel: opts.confirmLabel || 'Confirm',
      cancelLabel: opts.cancelLabel || 'Cancel',
      danger: !!opts.danger,
      // The safe answer is the reflex on anything destructive.
      focus: opts.danger ? 'cancel' : 'ok',
      dismissible: !opts.danger,
      okAnswer: true,
      cancelAnswer: false,
      reentryAnswer: false,
    });
  };

  window.outcomeDialog = function (opts) {
    opts = opts || {};
    var tone = GLYPH[opts.tone] ? opts.tone : 'info';
    return open({
      role: tone === 'error' || tone === 'warn' ? 'alertdialog' : 'dialog',
      size: 'md',
      tone: tone,
      title: opts.title || '',
      meta: opts.meta || '',
      reason: opts.reason || '',
      body: opts.body || '',
      note: opts.note || '',
      okLabel: opts.primaryLabel || 'Close',
      cancelLabel: opts.secondaryLabel || '',
      focus: 'ok',
      // Reporting a result is never dismissed by a stray click or a timer.
      dismissible: false,
      okAnswer: 'primary',
      cancelAnswer: 'secondary',
      reentryAnswer: null,
    });
  };

  // Esc resolves an outcome dialog as "closed", not as its secondary action.
  var baseOutcome = window.outcomeDialog;
  window.outcomeDialog = function (opts) {
    return baseOutcome(opts).then(function (answer) {
      return answer === 'secondary' && !(opts && opts.secondaryLabel) ? null : answer;
    });
  };
})();
</script>
@endonce
