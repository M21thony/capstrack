{{-- The evaluation pages' banners: closed with their X, and a success one
     fades on its own after a few seconds. --}}
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  function hide(flash) {
    flash.classList.add('is-leaving');
    setTimeout(function () { flash.remove(); }, 220);
  }
  document.addEventListener('click', function (e) {
    var x = e.target.closest('[data-flash-close]');
    if (x) { hide(x.closest('[data-flash]')); }
  });
  document.querySelectorAll('[data-flash]:not(.is-bad)').forEach(function (flash) {
    setTimeout(function () { if (flash.isConnected && !flash.matches(':hover')) { hide(flash); } }, 6000);
  });
})();
</script>
