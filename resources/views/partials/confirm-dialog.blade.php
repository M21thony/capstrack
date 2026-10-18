{{-- The page's own confirm box, in place of the browser's.

     A form asks before it sends with data-confirm, holding the message, and if it
     wants, data-confirm-title, data-confirm-ok (the button) and
     data-confirm-tone (danger, warn or info). A link asks the same way.
     Script can ask too: confirmDialog({title, body, ok, tone}) resolves to
     true or false.

     Every form sends once: its submit button shows it is working until the
     next page loads, so a second click does not send it twice. --}}
@once
@include('partials.modal')
<style nonce="{{ Vite::cspNonce() }}">
  .cd-msg b{color:var(--text,#1e2128)}
  .is-sending{position:relative;pointer-events:none;opacity:.75}
  .is-sending::after{content:"";width:14px;height:14px;margin-left:8px;border-radius:50%;border:2px solid currentColor;border-right-color:transparent;display:inline-block;vertical-align:-2px;animation:cdSpin .7s linear infinite}
  @keyframes cdSpin{to{transform:rotate(360deg)}}
  @media (prefers-reduced-motion:reduce){.is-sending::after{animation:none}}
</style>
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  // The ring each tone wears; the look itself is partials/modal's small shape.
  var ICONS = {
    danger: '<svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>',
    warn: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>',
    info: '<svg viewBox="0 0 24 24"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><path d="M22 4 12 14l-3-3"/></svg>'
  };
  var RING = {danger: 'error', warn: 'warn', info: 'info'};
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

  window.confirmDialog = function (opts) {
    opts = opts || {};
    var tone = ICONS[opts.tone] ? opts.tone : 'info';
    return new Promise(function (resolve) {
      var d = document.createElement('dialog');
      d.className = 'cm cm-ask cd ' + tone;
      d.setAttribute('role', 'alertdialog');
      d.setAttribute('aria-labelledby', 'cd-title');
      if (opts.body) { d.setAttribute('aria-describedby', 'cd-msg'); }
      d.innerHTML = '<div class="cm-box"><span class="cm-ring cm-tone-' + RING[tone] + '" aria-hidden="true">' + ICONS[tone] + '</span>'
        + '<h2 class="cm-title" id="cd-title">' + esc(opts.title || 'Are you sure?') + '</h2>'
        + (opts.body ? '<p class="cm-text cd-msg" id="cd-msg">' + (opts.html ? opts.body : esc(opts.body)) + '</p>' : '')
        + '<div class="cm-pair"><button type="button" class="cm-btn" data-cd="no">' + esc(opts.cancel || 'Cancel') + '</button>'
        + '<button type="button" class="cm-btn ' + (tone === 'danger' ? 'cm-btn-danger' : 'cm-btn-1') + '" data-cd="yes">' + esc(opts.ok || 'Continue') + '</button></div></div>';
      document.body.appendChild(d);
      var yes = false;
      d.addEventListener('click', function (e) {
        var b = e.target.closest('[data-cd]');
        if (b) { yes = b.dataset.cd === 'yes'; window.CapsModal.close(d); }
      });
      window.CapsModal.open(d, {
        dismissible: true,
        // The safe choice has the focus, so Enter never deletes by accident.
        focus: tone === 'danger' ? '[data-cd="no"]' : '[data-cd="yes"]',
        onClose: function () { setTimeout(function () { d.remove(); }, 0); resolve(yes); }
      });
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
