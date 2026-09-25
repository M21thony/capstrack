{{--
  The one dialog.

  Before this there were 103 hand-built overlays and three competing definitions
  of .modal-overlay in admin.css, so no two dialogs agreed on their radius,
  padding or shadow, and none of them handled a keyboard at all: focus stayed
  behind the scrim, Tab walked out into the page underneath, and closing one left
  the caret back at the top of the document.

  Two shapes, one box:

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
<style>
  .ad-scrim{position:fixed;inset:0;z-index:1600;background:rgba(24,25,31,.45);display:flex;align-items:center;justify-content:center;padding:20px;animation:adScrimIn 250ms ease-out both}
  .ad-scrim[hidden]{display:none}
  .ad-box{width:440px;max-width:100%;box-sizing:border-box;background:var(--bg2,#fff);border:1px solid var(--border,#e0e2e8);border-radius:16px;box-shadow:0 24px 80px rgba(0,0,0,.25);padding:24px;display:flex;flex-direction:column;gap:14px;max-height:calc(100vh - 40px);overflow-y:auto;animation:adBoxIn 250ms cubic-bezier(.16,.84,.44,1) both;font-family:'DM Sans',sans-serif}
  .ad-box.ad-md{width:496px}
  .ad-box.ad-lg{width:720px}
  @keyframes adScrimIn{from{opacity:0}to{opacity:1}}
  @keyframes adBoxIn{from{opacity:0;transform:translateY(10px) scale(.97)}to{opacity:1;transform:none}}
  @media (prefers-reduced-motion:reduce){
    .ad-box{animation:adScrimIn 250ms ease-out both}
  }
  .ad-head{display:flex;align-items:flex-start;gap:13px}
  .ad-icon{width:40px;height:40px;border-radius:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
  .ad-icon svg{width:19px;height:19px;fill:none;stroke-width:2;stroke-linecap:round}
  .ad-icon[hidden]{display:none}
  .ad-tone-error{background:#fdeaea}.ad-tone-error svg{stroke:#b91c1c}
  .ad-tone-warn{background:#fdf5ec}.ad-tone-warn svg{stroke:#92400e}
  .ad-tone-success{background:#f0f7f2}.ad-tone-success svg{stroke:#15803d}
  .ad-tone-info{background:#eef2fd}.ad-tone-info svg{stroke:#1d4ed8}
  .ad-headtext{flex:1;display:flex;flex-direction:column;gap:3px;min-width:0}
  .ad-title{font-size:16.5px;font-weight:700;color:var(--text,#18191f);line-height:1.3}
  .ad-meta{font-size:12.5px;color:var(--text3,#656b7a)}
  .ad-meta[hidden]{display:none}
  .ad-x{width:34px;height:34px;flex-shrink:0;border-radius:9px;background:var(--bg3,#f7f8fa);border:1px solid var(--border,#e0e2e8);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text3,#656b7a)}
  .ad-x svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round}
  .ad-x[hidden]{display:none}
  .ad-names,.ad-reason{font-size:13px;line-height:1.55;border-radius:10px;padding:12px 14px}
  .ad-names{border:1px solid var(--border,#e0e2e8);background:var(--bg3,#f7f8fa);color:var(--text,#1e2128)}
  .ad-reason{border-left:3px solid var(--border2,#c8cad4);background:var(--bg3,#f7f8fa);color:var(--text,#1e2128)}
  .ad-tone-error ~ .ad-reason,.ad-box.ad-is-error .ad-reason{border-left-color:#f1c4c4;background:#fdf7f7}
  .ad-names[hidden],.ad-reason[hidden]{display:none}
  .ad-body{font-size:13.5px;color:var(--text2,#4a5060);line-height:1.55;margin:0}
  .ad-body[hidden]{display:none}
  .ad-foot{display:flex;align-items:center;gap:8px;justify-content:flex-end;flex-wrap:wrap}
  .ad-note{flex:1;min-width:120px;font-size:12px;color:var(--text3,#656b7a)}
  .ad-note.ad-note-warn{color:#b91c1c;font-weight:600}
  .ad-btn{min-height:44px;padding:11px 18px;border-radius:9px;font-size:13.5px;cursor:pointer;font-family:'DM Sans',sans-serif}
  .ad-btn[hidden]{display:none}
  .ad-btn-2{font-weight:500;background:var(--bg,#f2f3f5);color:var(--text,#1e2128);border:1px solid var(--border,#e0e2e8)}
  .ad-btn-1{font-weight:700;background:var(--accent,#c9a82c);color:#18191f;border:1px solid var(--accent2,#b5941f)}
  .ad-btn-1.ad-danger{background:#b91c1c;color:#fff;border-color:#b91c1c;font-weight:600}
  @media (max-width:520px){
    .ad-scrim{align-items:flex-end;padding:0}
    .ad-box,.ad-box.ad-md,.ad-box.ad-lg{width:100%;border-radius:20px 20px 0 0;max-height:90vh}
    .ad-foot{flex-direction:column-reverse;align-items:stretch}
    .ad-btn{width:100%}
    .ad-note{order:-1;text-align:center}
  }
</style>

<div class="ad-scrim" id="ad-scrim" hidden>
  <div class="ad-box" id="ad-box" role="alertdialog" aria-modal="true" aria-labelledby="ad-title">
    <div class="ad-head">
      <div class="ad-icon" id="ad-icon" hidden aria-hidden="true"></div>
      <div class="ad-headtext">
        <div class="ad-title" id="ad-title"></div>
        <div class="ad-meta" id="ad-meta" hidden></div>
      </div>
      <button class="ad-x" id="ad-x" type="button" aria-label="Close"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
    </div>
    <div class="ad-names" id="ad-names" hidden></div>
    <div class="ad-reason" id="ad-reason" hidden></div>
    <p class="ad-body" id="ad-body" hidden></p>
    <div class="ad-foot">
      <span class="ad-note" id="ad-note"></span>
      <button class="ad-btn ad-btn-2" id="ad-cancel" type="button"></button>
      <button class="ad-btn ad-btn-1" id="ad-ok" type="button"></button>
    </div>
  </div>
</div>

<script>
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
    x: document.getElementById('ad-x'),
    names: document.getElementById('ad-names'),
    reason: document.getElementById('ad-reason'),
    body: document.getElementById('ad-body'),
    note: document.getElementById('ad-note'),
    cancel: document.getElementById('ad-cancel'),
    ok: document.getElementById('ad-ok'),
  };

  var settle = null;
  var config = {};
  var lastFocused = null;

  function focusable() {
    return Array.prototype.filter.call(
      box.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'),
      function (el) { return !el.hidden && !el.disabled && el.offsetParent !== null; }
    );
  }

  function close(answer) {
    if (!settle) return;
    var done = settle;
    settle = null;
    scrim.hidden = true;
    document.body.style.overflow = '';
    // Back to the control that opened it, so the keyboard does not restart at
    // the top of the page.
    if (lastFocused && typeof lastFocused.focus === 'function') {
      try { lastFocused.focus(); } catch (e) { /* gone from the DOM */ }
    }
    done(answer);
  }

  function open(cfg) {
    // A dialog opening over a dialog would orphan the first one's promise.
    if (settle) close(cfg.reentryAnswer);

    config = cfg;
    lastFocused = document.activeElement;

    box.className = 'ad-box' + (cfg.size === 'lg' ? ' ad-lg' : cfg.size === 'md' ? ' ad-md' : '') + (cfg.tone === 'error' ? ' ad-is-error' : '');
    box.setAttribute('role', cfg.role);

    if (cfg.tone && GLYPH[cfg.tone]) {
      els.icon.className = 'ad-icon ad-tone-' + cfg.tone;
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
    els.note.className = 'ad-note' + (cfg.noteWarn ? ' ad-note-warn' : '');

    els.cancel.textContent = cfg.cancelLabel || '';
    els.cancel.hidden = !cfg.cancelLabel;
    els.ok.textContent = cfg.okLabel || 'Close';
    els.ok.className = 'ad-btn ad-btn-1' + (cfg.danger ? ' ad-danger' : '');
    els.x.hidden = cfg.hideClose === true;

    scrim.hidden = false;
    document.body.style.overflow = 'hidden';

    var target = cfg.focus === 'cancel' && !els.cancel.hidden ? els.cancel : els.ok;
    target.focus();

    return new Promise(function (resolve) { settle = resolve; });
  }

  els.ok.addEventListener('click', function () { close(config.okAnswer); });
  els.cancel.addEventListener('click', function () { close(config.cancelAnswer); });
  els.x.addEventListener('click', function () { close(config.cancelAnswer); });

  scrim.addEventListener('click', function (e) {
    // Destructive confirms and outcome reports both ignore this; only a dialog
    // opened to be read can be dismissed by clicking away from it.
    if (e.target === scrim && config.dismissible) close(config.cancelAnswer);
  });

  document.addEventListener('keydown', function (e) {
    if (scrim.hidden || !settle) return;

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
