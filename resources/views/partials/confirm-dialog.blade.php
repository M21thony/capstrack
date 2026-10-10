{{-- The page's own confirm box, in place of the browser's.

     A form asks before it sends with data-confirm, holding the message, and if it
     wants, data-confirm-title, data-confirm-ok (the button) and
     data-confirm-tone (danger, warn or info). A link asks the same way.
     Script can ask too: confirmDialog({title, body, ok, tone}) resolves to
     true or false.

     Every form sends once: its submit button shows it is working until the
     next page loads, so a second click does not send it twice. --}}
@once
<style nonce="{{ Vite::cspNonce() }}">
  dialog.cd{border:0;padding:0;margin:auto;width:min(420px,calc(100vw - 32px));border-radius:18px;background:var(--bg2,#fff);color:var(--text,#1e2128);box-shadow:0 30px 80px -20px rgba(15,18,30,.45),0 8px 24px rgba(0,0,0,.12);font-family:'DM Sans','DM Sans Fallback',system-ui,sans-serif;overflow:hidden}
  dialog.cd[open]{animation:cdIn .18s ease-out}
  dialog.cd::backdrop{background:rgba(15,18,30,.5);backdrop-filter:blur(3px)}
  @keyframes cdIn{from{opacity:0;transform:translateY(8px) scale(.98)}to{opacity:1;transform:none}}
  .cd-body{display:flex;gap:14px;padding:22px 22px 18px}
  .cd-ic{width:44px;height:44px;flex-shrink:0;border-radius:12px;display:grid;place-items:center}
  .cd-ic svg{width:22px;height:22px}
  .cd.danger .cd-ic{background:var(--red-bg,rgba(220,38,38,.08));color:var(--red,#dc2626)}
  .cd.warn .cd-ic{background:var(--amber-bg,rgba(180,83,9,.08));color:var(--amber,#b45309)}
  .cd.info .cd-ic{background:rgba(201,168,44,.14);color:#a8891a}
  .cd-text{min-width:0}
  .cd-title{margin:2px 0 0;font-size:16px;font-weight:700;letter-spacing:-.2px;line-height:1.3}
  .cd-msg{margin:6px 0 0;font-size:13.5px;line-height:1.55;color:var(--text2,#4a5060);overflow-wrap:anywhere}
  .cd-msg b{color:var(--text,#1e2128)}
  .cd-foot{display:flex;justify-content:flex-end;gap:8px;padding:14px 22px 18px;background:var(--bg3,#f7f8fa);border-top:1px solid var(--border,#e0e2e8)}
  .cd-btn{min-height:40px;padding:0 16px;border-radius:10px;font:inherit;font-size:13.5px;font-weight:700;cursor:pointer;border:1px solid var(--border,#e0e2e8);background:var(--bg2,#fff);color:var(--text,#1e2128);transition:background .15s,border-color .15s,filter .15s}
  .cd-btn:hover{background:var(--bg4,#eceef2)}
  .cd-btn:focus-visible{outline:2px solid var(--accent,#c9a82c);outline-offset:2px}
  .cd-ok{border-color:transparent}
  .cd.danger .cd-ok{background:var(--red,#dc2626);color:#fff}
  .cd.warn .cd-ok,.cd.info .cd-ok{background:var(--accent,#c9a82c);color:#18191f}
  .cd-ok:hover{filter:brightness(1.07)}
  .is-sending{position:relative;pointer-events:none;opacity:.75}
  .is-sending::after{content:"";width:14px;height:14px;margin-left:8px;border-radius:50%;border:2px solid currentColor;border-right-color:transparent;display:inline-block;vertical-align:-2px;animation:cdSpin .7s linear infinite}
  @keyframes cdSpin{to{transform:rotate(360deg)}}
  @media (max-width:480px){dialog.cd{margin:auto auto 0;width:100%;max-width:none;border-radius:18px 18px 0 0}.cd-foot{flex-direction:column-reverse}.cd-btn{width:100%;min-height:44px}}
  @media (prefers-reduced-motion:reduce){dialog.cd[open]{animation:none}.is-sending::after{animation:none}}
</style>
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var ICONS = {
    danger: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>',
    warn: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>',
    info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><path d="M22 4 12 14l-3-3"/></svg>'
  };
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

  window.confirmDialog = function (opts) {
    opts = opts || {};
    var tone = ICONS[opts.tone] ? opts.tone : 'info';
    return new Promise(function (resolve) {
      var d = document.createElement('dialog');
      d.className = 'cd ' + tone;
      d.setAttribute('aria-labelledby', 'cd-title');
      d.setAttribute('aria-describedby', 'cd-msg');
      d.innerHTML = '<div class="cd-body"><span class="cd-ic" aria-hidden="true">' + ICONS[tone] + '</span><div class="cd-text">'
        + '<h2 class="cd-title" id="cd-title">' + esc(opts.title || 'Are you sure?') + '</h2>'
        + (opts.body ? '<p class="cd-msg" id="cd-msg">' + (opts.html ? opts.body : esc(opts.body)) + '</p>' : '')
        + '</div></div><div class="cd-foot"><button type="button" class="cd-btn" data-cd="no">' + esc(opts.cancel || 'Cancel') + '</button>'
        + '<button type="button" class="cd-btn cd-ok" data-cd="yes">' + esc(opts.ok || 'Continue') + '</button></div>';
      document.body.appendChild(d);
      var answered = false;
      function done(yes) { if (answered) { return; } answered = true; d.close(); resolve(yes); }
      d.addEventListener('click', function (e) {
        var b = e.target.closest('[data-cd]');
        if (b) { done(b.dataset.cd === 'yes'); } else if (e.target === d) { done(false); }
      });
      d.addEventListener('cancel', function (e) { e.preventDefault(); done(false); });
      d.addEventListener('close', function () { setTimeout(function () { d.remove(); }, 0); });
      d.showModal();
      // The safe choice has the focus, so Enter never deletes by accident.
      d.querySelector(tone === 'danger' ? '[data-cd="no"]' : '[data-cd="yes"]').focus();
    });
  };

  function ask(el) {
    return window.confirmDialog({
      title: el.dataset.confirmTitle,
      body: el.dataset.confirm,
      ok: el.dataset.confirmOk,
      tone: el.dataset.confirmTone
    });
  }

  document.addEventListener('submit', function (e) {
    var form = e.target;
    // The page's own checks, on the form itself, run first.
    if (e.defaultPrevented) { return; }
    if (form.dataset.confirm && !form.dataset.confirmed) {
      e.preventDefault();
      var by = e.submitter;
      ask(form).then(function (yes) {
        if (!yes) { return; }
        form.dataset.confirmed = '1';
        form.requestSubmit ? form.requestSubmit(by || undefined) : form.submit();
      });
      return;
    }
    // Sent once: the button says so until the next page arrives.
    var btn = e.submitter || form.querySelector('[type="submit"]');
    if (form.dataset.sending) { e.preventDefault(); return; }
    form.dataset.sending = '1';
    if (btn) { btn.classList.add('is-sending'); btn.setAttribute('aria-disabled', 'true'); }
  });

  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[data-confirm]');
    if (!a || e.defaultPrevented) { return; }
    e.preventDefault();
    ask(a).then(function (yes) { if (yes) { window.location.href = a.href; } });
  });

  // Back from the next page (bfcache): nothing is sending any more.
  window.addEventListener('pageshow', function () {
    document.querySelectorAll('form[data-sending]').forEach(function (f) {
      delete f.dataset.sending; delete f.dataset.confirmed;
      f.querySelectorAll('.is-sending').forEach(function (b) { b.classList.remove('is-sending'); b.removeAttribute('aria-disabled'); });
    });
  });
})();
</script>
@endonce
