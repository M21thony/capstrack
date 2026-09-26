{{--
  An inline notice that lives on the form it belongs to.

  This exists because a dozen failures in this app were reported with
  window.alert(): it hides the values that were typed behind a modal the browser
  draws, it cannot name a field, it cannot carry a retry, and on the login page it
  covered the e-mail address the reader needed to check. A notice drawn in the
  page can do all four.

  A failure the reader is still looking at the form for belongs here. A failure
  they have already walked away from belongs in a dialog.

  Usage, on any page that includes this once:

    formNotice('folder-notice', {
      tone: 'error',                          // error | warn | info | success
      title: 'Chapter 1-3 was not created',
      body: 'Google Drive is not linked.',
      actionLabel: 'Link Drive',              // optional
      onAction: () => location.assign(url),   // optional
    });

    formNotice.clear('folder-notice');

  The host is an element or its id. Title and body are set as text, never as
  markup: they carry folder names and server messages, which are data.
--}}
<style>
  .fn-notice{border-radius:10px;padding:11px 13px;display:flex;align-items:flex-start;gap:9px}
  .fn-notice + .fn-notice{margin-top:8px}
  .fn-notice svg{width:16px;height:16px;flex-shrink:0;margin-top:1px;fill:none;stroke-width:2.2;stroke-linecap:round}
  .fn-text{display:flex;flex-direction:column;gap:3px;min-width:0}
  .fn-title{font-size:13px;font-weight:700;line-height:1.35}
  .fn-body{font-size:12.5px;color:var(--text2,#4a5060);line-height:1.45}
  .fn-action{align-self:flex-start;margin-top:3px;min-height:34px;padding:7px 13px;border-radius:8px;font-size:12.5px;font-weight:600;cursor:pointer;background:var(--bg2,#ffffff);font-family:'DM Sans','DM Sans Fallback',sans-serif}
  .fn-error{background:#fdeaea;border:1px solid #f1c4c4}
  .fn-error svg{stroke:#b91c1c}.fn-error .fn-title{color:#b91c1c}
  .fn-error .fn-action{color:#b91c1c;border:1px solid #f1c4c4}
  .fn-warn{background:#fdf5ec;border:1px solid #efd9be}
  .fn-warn svg{stroke:#92400e}.fn-warn .fn-title{color:#92400e}
  .fn-warn .fn-action{color:#92400e;border:1px solid #efd9be}
  .fn-info{background:#eef2fd;border:1px solid #c9d5f7}
  .fn-info svg{stroke:#1d4ed8}.fn-info .fn-title{color:#1d4ed8}
  .fn-info .fn-action{color:#1d4ed8;border:1px solid #c9d5f7}
  .fn-success{background:#f0f7f2;border:1px solid #c3e0cd}
  .fn-success svg{stroke:#15803d}.fn-success .fn-title{color:#15803d}
  .fn-success .fn-action{color:#15803d;border:1px solid #c3e0cd}
</style>

<script>
(function () {
  var GLYPH = {
    error: '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="13"></line><line x1="12" y1="16.5" x2="12" y2="16.5"></line>',
    warn: '<path d="M10.3 3.9L1.8 18.1A2 2 0 0 0 3.5 21h17a2 2 0 0 0 1.7-2.9L13.7 3.9a2 2 0 0 0-3.4 0z"></path><line x1="12" y1="9" x2="12" y2="13.5"></line><line x1="12" y1="17" x2="12" y2="17"></line>',
    info: '<circle cx="12" cy="12" r="10"></circle><polyline points="12 7 12 12 15.5 14"></polyline>',
    success: '<circle cx="12" cy="12" r="10"></circle><polyline points="8 12.5 11 15.5 16 9.5"></polyline>',
  };

  function hostOf(host) {
    return typeof host === 'string' ? document.getElementById(host) : host;
  }

  window.formNotice = function (host, opts) {
    var el = hostOf(host);
    if (!el) return null;

    var tone = GLYPH[opts.tone] ? opts.tone : 'error';
    el.textContent = '';

    var box = document.createElement('div');
    box.className = 'fn-notice fn-' + tone;
    // A failure interrupts what the reader was doing, so it is announced; the
    // other two only report, and are read when the reader next pauses.
    box.setAttribute('role', tone === 'error' || tone === 'warn' ? 'alert' : 'status');

    var icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    icon.setAttribute('viewBox', '0 0 24 24');
    icon.setAttribute('aria-hidden', 'true');
    icon.innerHTML = GLYPH[tone];
    box.appendChild(icon);

    var text = document.createElement('div');
    text.className = 'fn-text';

    if (opts.title) {
      var title = document.createElement('div');
      title.className = 'fn-title';
      title.textContent = opts.title;
      text.appendChild(title);
    }
    if (opts.body) {
      var body = document.createElement('div');
      body.className = 'fn-body';
      body.textContent = opts.body;
      text.appendChild(body);
    }
    if (opts.actionLabel) {
      var action = document.createElement('button');
      action.type = 'button';
      action.className = 'fn-action';
      action.textContent = opts.actionLabel;
      if (opts.onAction) action.addEventListener('click', opts.onAction);
      text.appendChild(action);
    }

    box.appendChild(text);
    el.appendChild(box);
    el.hidden = false;
    return box;
  };

  window.formNotice.clear = function (host) {
    var el = hostOf(host);
    if (!el) return;
    el.textContent = '';
    el.hidden = true;
  };
})();
</script>
