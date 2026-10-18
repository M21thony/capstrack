{{--
  The CapsTrack modal. One look for every pop-up in the app, in three shapes:

    <dialog class="cm cm-ask">    a small box in the middle: confirm a step, or
                                  report how one went. Ring icon, title, a line
                                  or two, and two buttons of equal width.
    <dialog class="cm cm-panel">  a panel from the right for a form or a record:
                                  eyebrow, title and X stay put, only .cm-b
                                  scrolls, and .cm-f holds the buttons.
    either one on a phone         a sheet from the bottom, as tall as what it
                                  holds and never taller than 92% of the screen.

  Both are native <dialog>s opened with CapsModal.open(dialog), so they sit
  above every hand-built overlay without a z-index race, the page behind them
  cannot be clicked or tabbed into, and Esc closes them. CapsModal puts focus
  back where it was when one closes, keeps the page from scrolling underneath,
  and brings a field into view above the phone keyboard.

  A box that scrolls keeps its right edge clear (.cm-b pads 32px on that side),
  so the CapsTrack scrollbar never sits on top of the text.
--}}
@once
<style nonce="{{ Vite::cspNonce() }}">
  dialog.cm{position:fixed;inset:0;box-sizing:border-box;width:100%;height:100%;max-width:none;max-height:none;margin:0;padding:16px;border:0;background:transparent;overflow:hidden;color:var(--text,#1e2128);font-family:'DM Sans','DM Sans Fallback',system-ui,sans-serif;font-size:14px}
  dialog.cm[open]{display:grid;place-items:center}
  dialog.cm::backdrop{background:rgba(24,25,31,.55)}
  .cm *{box-sizing:border-box}
  .cm-box{display:flex;flex-direction:column;width:100%;overflow:hidden;background:var(--bg2,#fff);box-shadow:0 0 0 1px rgba(16,18,26,.06),0 28px 70px rgba(10,12,20,.32)}

  .cm-ask .cm-box{max-width:420px;max-height:calc(100vh - 32px);max-height:calc(100dvh - 32px);overflow-y:auto;padding:28px 24px 22px;border-radius:20px;align-items:center;gap:10px;text-align:center}
  .cm-ask.cm-wide .cm-box{max-width:496px}
  .cm-ring{display:grid;place-items:center;flex-shrink:0;width:56px;height:56px;margin-bottom:6px;border-radius:50%}
  .cm-ring[hidden]{display:none}
  .cm-ring svg{width:24px;height:24px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
  .cm-tone-error{background:#fdeaea;color:#b91c1c;box-shadow:0 0 0 8px #fef5f5}
  .cm-tone-warn{background:#fdf2d6;color:#92400e;box-shadow:0 0 0 8px #fefaf0}
  .cm-tone-success{background:#e6f4ea;color:#15803d;box-shadow:0 0 0 8px #f3faf5}
  .cm-tone-info{background:#faf4de;color:#8a6e10;box-shadow:0 0 0 8px #fdfbf3}
  .cm-title{margin:0;font-size:18px;font-weight:700;line-height:1.3;color:var(--text,#18191f)}
  .cm-meta{font-size:13px;color:var(--text3,#656b7a)}
  .cm-chip{display:inline-flex;align-items:center;gap:8px;max-width:100%;padding:5px 14px;border-radius:999px;background:var(--bg3,#f7f8fa);border:1px solid var(--border,#e0e2e8);font-size:13px;font-weight:500}
  .cm-text{margin:0;font-size:13.5px;line-height:1.55;color:var(--text2,#4a5060)}
  .cm-names,.cm-reason{align-self:stretch;padding:12px 14px;border-radius:12px;font-size:13px;line-height:1.55;text-align:left}
  .cm-names{background:var(--bg3,#f7f8fa);border:1px solid var(--border,#e0e2e8)}
  .cm-reason{background:#fdf7f7;border:1px solid #f3d0d0;color:var(--text,#1e2128)}
  .cm-note{font-size:12.5px;color:var(--text3,#656b7a)}
  .cm-note.cm-note-warn{color:#b91c1c;font-weight:600}
  .cm-ask .cm-field{align-self:stretch;text-align:left}
  .cm-names[hidden],.cm-reason[hidden],.cm-text[hidden],.cm-meta[hidden],.cm-chip[hidden],.cm-note[hidden]{display:none}

  .cm-pair{align-self:stretch;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:8px}
  .cm-pair.cm-one{grid-template-columns:minmax(0,1fr)}
  .cm-btn{min-height:46px;padding:0 16px;border-radius:12px;border:1px solid var(--border2,#d3d6de);background:var(--bg2,#fff);color:var(--text,#1e2128);font:inherit;font-size:14px;font-weight:600;cursor:pointer;box-shadow:0 1px 1px rgba(16,18,26,.04)}
  .cm-btn[hidden]{display:none}
  .cm-btn:hover{background:var(--bg3,#f7f8fa)}
  .cm-btn-1{background:var(--accent,#c9a82c);border-color:var(--accent2,#b5941f);color:#18191f;font-weight:700}
  .cm-btn-1:hover{background:#d4b23a}
  .cm-btn-danger{background:#b91c1c;border-color:#a51818;color:#fff}
  .cm-btn-danger:hover{background:#a51818}
  .cm-btn:focus-visible,.cm-x:focus-visible{outline:2px solid #8a6e10;outline-offset:2px}

  .cm-panel{padding:0}
  .cm-panel[open]{place-items:stretch end}
  .cm-panel .cm-box{max-width:480px;height:100%;border-radius:20px 0 0 20px;box-shadow:0 0 0 1px rgba(16,18,26,.06),-24px 0 70px rgba(10,12,20,.3)}
  .cm-panel.cm-wide .cm-box{max-width:560px}
  .cm-h{flex-shrink:0;display:flex;align-items:flex-start;gap:14px;padding:24px 20px 18px 24px;border-bottom:1px solid var(--border,#eceef2)}
  .cm-ht{flex:1;min-width:0;display:flex;flex-direction:column;gap:4px}
  .cm-eyebrow{font-size:11.5px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:#8a6e10}
  .cm-panel .cm-title{font-size:20px}
  .cm-x{display:grid;place-items:center;flex-shrink:0;width:38px;height:38px;padding:0;border:0;border-radius:50%;background:var(--bg4,#eceef2);color:var(--text2,#4a5060);cursor:pointer}
  .cm-x svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round}
  .cm-b{flex:1 1 auto;min-height:0;overflow-y:auto;overscroll-behavior:contain;scroll-padding:16px 0 24px;padding:20px 32px 20px 24px;display:flex;flex-direction:column;gap:18px}
  .cm-f{flex-shrink:0;display:flex;flex-direction:column;gap:10px;padding:14px 24px 20px;border-top:1px solid var(--border,#eceef2);background:var(--bg2,#fff)}
  .cm-f .cm-pair{margin-top:0}

  html.cm-lock,html.cm-lock body{overflow:hidden}

  dialog.cm[open] .cm-box{animation:cmPop .25s cubic-bezier(.16,.84,.44,1) both}
  dialog.cm-panel[open] .cm-box{animation-name:cmSlide}
  dialog.cm[open]::backdrop{animation:cmFade .25s ease-out both}
  @keyframes cmPop{from{opacity:0;transform:translateY(10px) scale(.97)}to{opacity:1;transform:none}}
  @keyframes cmSlide{from{transform:translateX(40px);opacity:0}to{transform:none;opacity:1}}
  @keyframes cmRise{from{transform:translateY(40px);opacity:0}to{transform:none;opacity:1}}
  @keyframes cmFade{from{opacity:0}to{opacity:1}}

  @media (max-width:768px){
    dialog.cm,.cm-panel{padding:0}
    dialog.cm[open],.cm-panel[open]{place-items:end stretch}
    .cm-ask .cm-box,.cm-panel .cm-box{max-width:none;height:auto;max-height:92vh;max-height:92dvh;border-radius:20px 20px 0 0}
    .cm-box::before{content:"";flex-shrink:0;width:40px;height:5px;margin:8px auto 0;border-radius:3px;background:#d3d6de}
    .cm-ask .cm-box{padding:0 20px calc(24px + env(safe-area-inset-bottom))}
    .cm-ask .cm-box::before{margin-bottom:16px}
    .cm-h{padding:12px 16px 14px 20px}
    .cm-panel .cm-title{font-size:19px}
    .cm-b{padding:16px 28px 16px 20px}
    .cm-f{padding:12px 20px calc(16px + env(safe-area-inset-bottom))}
    .cm input,.cm select,.cm textarea{font-size:16px}
    dialog.cm[open] .cm-box,dialog.cm-panel[open] .cm-box{animation-name:cmRise}
  }
  @media (prefers-reduced-motion:reduce){
    dialog.cm[open] .cm-box,dialog.cm[open]::backdrop{animation:none}
  }
</style>
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  if (window.CapsModal) return;

  var openOnes = [];

  function unlock() {
    if (!openOnes.length) document.documentElement.classList.remove('cm-lock');
  }

  // With the phone keyboard up, the field being typed in goes to the middle of
  // what is still visible instead of under the keys.
  document.addEventListener('focusin', function (e) {
    var dlg = e.target.closest && e.target.closest('dialog.cm[open]');
    if (!dlg || !e.target.matches('input, select, textarea')) return;
    setTimeout(function () {
      var vv = window.visualViewport;
      if (vv && vv.height < window.innerHeight * 0.85) {
        e.target.scrollIntoView({block: 'center', behavior: 'smooth'});
      }
    }, 300);
  });

  // Finishes at once, not on the close event, so a dialog can be reopened in
  // the same tick without the old close landing on the new one.
  function close(dlg, value) {
    if (!dlg.open) return;
    dlg.close(value === undefined ? '' : String(value));
    if (dlg._cmFinish) dlg._cmFinish();
  }

  /**
   * Opens a <dialog class="cm">. Options: dismissible (a click outside closes
   * it), locked (Esc does nothing, for a step that must be answered), focus (an
   * element, or a selector inside the dialog), onClose(returnValue).
   */
  function open(dlg, opts) {
    opts = opts || {};
    if (dlg.open) return dlg;
    var lastFocused = document.activeElement;
    var finished = false;

    function onCancel(e) {
      if (opts.locked) e.preventDefault();
    }
    function onClick(e) {
      // The dialog itself is the dimmed area around the box.
      if (e.target === dlg && opts.dismissible) close(dlg, 'dismiss');
    }
    function onCloseEvent() {
      // A close event from an earlier opening arrives after this one began.
      if (!dlg.open) finish();
    }
    function finish() {
      if (finished) return;
      finished = true;
      dlg._cmFinish = null;
      dlg.removeEventListener('cancel', onCancel);
      dlg.removeEventListener('click', onClick);
      dlg.removeEventListener('close', onCloseEvent);
      if (openOnes.indexOf(dlg) > -1) openOnes.splice(openOnes.indexOf(dlg), 1);
      unlock();
      if (lastFocused && typeof lastFocused.focus === 'function') {
        try { lastFocused.focus(); } catch (e) { /* gone from the page */ }
      }
      if (opts.onClose) opts.onClose(dlg.returnValue);
    }

    dlg._cmFinish = finish;
    dlg.addEventListener('cancel', onCancel);
    dlg.addEventListener('click', onClick);
    dlg.addEventListener('close', onCloseEvent);
    dlg.returnValue = '';
    dlg.showModal();
    openOnes.push(dlg);
    document.documentElement.classList.add('cm-lock');

    var target = typeof opts.focus === 'string' ? dlg.querySelector(opts.focus) : opts.focus;
    if (target) target.focus();
    return dlg;
  }

  window.CapsModal = {open: open, close: close};
})();
</script>
@endonce
