{{--
  The Success / Error card every page shows after an action - the admin
  card, moved to the top center and made bigger so nobody misses it.

  window.ctToast(message, type, { title, undo }) - type is 'success',
  'error' or 'info'. Each page's own showToast()/toast() hands off to it.
  It sits in the top layer (a popover), so it shows above open windows too.

  The bell pop-ups (partials/notification-toasts) are a different thing and
  stay where they are.
--}}
@once
<div id="ct-action-toasts" popover="manual" aria-live="polite"></div>

<style>
  #ct-action-toasts {
    position: fixed; inset: 18px 0 auto 0; margin: 0 auto; padding: 0;
    width: min(480px, calc(100vw - 32px)); border: 0; background: none; overflow: visible;
    flex-direction: column; gap: 10px; align-items: stretch;
  }
  #ct-action-toasts:popover-open { display: flex; }
  .ct-at {
    display: flex; align-items: flex-start; gap: 14px; box-sizing: border-box;
    background: #fff; border: 1px solid #bbf7d0; border-top: 5px solid #16a34a; border-radius: 14px;
    box-shadow: 0 20px 50px rgba(15, 23, 42, .30), 0 0 0 1px rgba(15, 23, 42, .05);
    padding: 16px 18px; font-family: inherit; text-align: left;
    animation: ctAtIn .45s cubic-bezier(.2, 1.4, .4, 1);
    transition: opacity .25s, transform .25s;
  }
  .ct-at.error { border-color: #fecaca; border-top-color: #dc2626; }
  .ct-at.info { border-color: #bfdbfe; border-top-color: #2563eb; }
  .ct-at.hiding { opacity: 0; transform: translateY(-16px); }
  .ct-at-icon {
    width: 44px; height: 44px; flex-shrink: 0; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    background: #dcfce7; color: #16a34a;
  }
  .ct-at.error .ct-at-icon { background: #fee2e2; color: #dc2626; }
  .ct-at.info .ct-at-icon { background: #dbeafe; color: #2563eb; }
  .ct-at-body { flex: 1; min-width: 0; }
  .ct-at-title { font-size: 16px; font-weight: 800; color: #0f172a; margin: 0 0 3px; }
  .ct-at-msg { font-size: 14px; color: #334155; margin: 0; line-height: 1.45; overflow-wrap: anywhere; }
  .ct-at-why {
    margin: 8px 0 0; padding: 7px 10px; border-radius: 8px; background: #fef2f2;
    font-size: 13px; color: #7f1d1d; line-height: 1.4;
  }
  .ct-at-undo {
    margin-top: 8px; padding: 5px 12px; border-radius: 8px; border: 1px solid #cbd5e1;
    background: #f8fafc; color: #0f172a; font: inherit; font-size: 13px; font-weight: 700; cursor: pointer;
  }
  .ct-at-close {
    background: none; border: none; cursor: pointer; color: #94a3b8;
    font-size: 22px; line-height: 1; padding: 0 2px;
  }
  .ct-at-close:hover { color: #334155; }
  @keyframes ctAtIn { from { opacity: 0; transform: translateY(-40px) scale(.94); } to { opacity: 1; transform: none; } }
  @media (prefers-reduced-motion: reduce) { .ct-at { animation: none; } }
</style>

<script>
(function () {
  const host = document.getElementById('ct-action-toasts');
  const icons = {
    success: '<polyline points="20 6 9 17 4 12"/>',
    error: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
    info: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
  };
  const titles = { success: 'Success', error: 'Error', info: 'Notice' };

  // Why the last request failed. Pages only say "Network error" or
  // "Something went wrong" - also when the session simply expired - so the
  // error card that follows adds the real cause.
  let lastFailure = null;
  const reasonFor = (status) => ({
    401: 'You were signed out. Sign in again, then retry.',
    403: 'Your account is not allowed to do this.',
    404: 'That item no longer exists. Reload the page to see the latest.',
    413: 'The file is too large to upload.',
    419: 'Your session expired. Reload the page, then try again.',
    429: 'Too many tries in a short time. Wait a minute, then try again.',
  })[status] || (status >= 500 ? 'The server ran into a problem. Try again; if it keeps happening, tell the admin.' : null);

  const nativeFetch = window.fetch;
  window.fetch = function () {
    return nativeFetch.apply(this, arguments).then((res) => {
      // A 403/404 that answers in JSON carries its own reason, which the page shows.
      const explained = (res.status === 403 || res.status === 404) && (res.headers.get('content-type') || '').includes('json');
      if (!res.ok && !explained && reasonFor(res.status)) {
        lastFailure = { reason: reasonFor(res.status), http: true, at: Date.now() };
      }
      return res;
    }, (err) => {
      if (!err || err.name !== 'AbortError') {
        lastFailure = {
          reason: navigator.onLine ? 'The server could not be reached. Try again in a moment.' : 'You are offline. Check your internet connection, then try again.',
          http: false,
          at: Date.now(),
        };
      }
      throw err;
    });
  };

  window.ctToast = function (message, type, options) {
    type = icons[type] ? type : 'success';
    options = options || {};

    let why = null;
    if (type === 'error' && lastFailure && Date.now() - lastFailure.at < 5000) {
      why = lastFailure.reason;
      // The server did answer, so it was not the connection.
      if (lastFailure.http) {
        message = String(message).replace(/^Network error/i, 'Error').replace(/\s*Check your connection and try again\.?/i, '');
      }
      lastFailure = null;
    }

    const card = document.createElement('div');
    card.className = 'ct-at ' + type;
    card.setAttribute('role', type === 'error' ? 'alert' : 'status');
    card.innerHTML =
      '<div class="ct-at-icon"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">' + icons[type] + '</svg></div>' +
      '<div class="ct-at-body"><p class="ct-at-title"></p><p class="ct-at-msg"></p></div>' +
      '<button type="button" class="ct-at-close" aria-label="Close">&times;</button>';
    // Messages carry names people typed: text, never markup.
    card.querySelector('.ct-at-title').textContent = options.title || titles[type];
    card.querySelector('.ct-at-msg').textContent = message;
    if (why) {
      const whyEl = document.createElement('p');
      whyEl.className = 'ct-at-why';
      whyEl.append(Object.assign(document.createElement('b'), { textContent: 'Why: ' }), why);
      card.querySelector('.ct-at-body').append(whyEl);
    }

    const close = () => {
      card.classList.add('hiding');
      setTimeout(() => {
        card.remove();
        if (!host.children.length) { try { host.hidePopover(); } catch (e) { /* closed */ } }
      }, 250);
    };
    card.querySelector('.ct-at-close').addEventListener('click', close);

    if (options.undo) {
      const undo = document.createElement('button');
      undo.type = 'button';
      undo.className = 'ct-at-undo';
      undo.textContent = 'Undo';
      undo.addEventListener('click', () => { close(); options.undo(); });
      card.querySelector('.ct-at-body').append(undo);
    }

    // Once in, a card stops its drop-in, so re-raising the host below
    // does not replay it on the cards already showing.
    card.addEventListener('animationend', () => { card.style.animation = 'none'; }, { once: true });

    host.append(card);
    while (host.children.length > 3) { host.firstElementChild.remove(); }

    // Hide and show again so it lands above any window opened since.
    try { host.hidePopover(); } catch (e) { /* not open */ }
    try { host.showPopover(); } catch (e) { host.style.display = 'flex'; host.style.zIndex = '2147483000'; }

    setTimeout(close, 4000);
  };
})();
</script>
@endonce
