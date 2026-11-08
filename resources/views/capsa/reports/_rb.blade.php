{{-- Reports Home and the builder share these: one dialog, one toast, and the calls to the server. --}}
<div class="toast" id="toast" role="status" aria-live="polite" hidden></div>
<dialog id="modal" aria-labelledby="modal-title"></dialog>
<script nonce="{{ Vite::cspNonce() }}">
window.RB = (function () {
  var $ = function (id) { return document.getElementById(id); };
  var modal = $('modal'), timer;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
  function toast(message) { $('toast').textContent = message; $('toast').hidden = false; clearTimeout(timer); timer = setTimeout(function () { $('toast').hidden = true; }, 3500); }
  function openDialog(title, body, foot) {
    modal.innerHTML = '<div class="dialog-head"><h2 id="modal-title">' + title + '</h2><button class="ghost small" data-close aria-label="Close dialog">✕</button></div>'
      + '<div class="dialog-body">' + body + '</div><div class="dialog-foot">' + foot + '</div>';
    modal.showModal();
    var first = modal.querySelector('.dialog-body input:not([disabled]),.dialog-body select');
    if (first) first.focus();
  }
  function closeDialog() { modal.close(); }
  modal.addEventListener('click', function (e) {
    if (e.target.closest('[data-close]')) return closeDialog();
    if (e.target !== modal) return;
    var r = modal.getBoundingClientRect();
    if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) closeDialog();
  });
  // JSON in, JSON out; a refusal throws the server's first message so the caller can toast it.
  function api(method, url, body) {
    return fetch(url, {
      method: method, credentials: 'same-origin',
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (d) {
        if (!r.ok) throw new Error(d.errors ? Object.values(d.errors)[0][0] : (d.message || 'Something went wrong. Try again.'));
        return d;
      });
    });
  }
  function columns(keys, fields) { return keys.map(function (k) { return {key: k, label: fields[k], sources: [k], separator: ' · '}; }); }
  return {$: $, esc: esc, toast: toast, openDialog: openDialog, closeDialog: closeDialog, api: api, columns: columns};
})();
</script>
