<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var sk = document.getElementById('skeleton-content');
  var rc = document.getElementById('real-content');
  if (!sk || !rc) return;

  // partials/skeleton-head decided this in <head>, before the browser painted
  // anything: the real content is already on screen and the skeleton never
  // appeared. Nothing to reveal - just take the placeholder out of the page.
  if (document.documentElement.classList.contains('sk-seen')) {
    sk.remove();

    return;
  }

  var seenKey = 'sk-seen:{{ substr(sha1(session()->getId()), 0, 12) }}:' + window.location.pathname;

  setTimeout(function () {
    sk.style.display = 'none';
    rc.style.display = '{{ $revealDisplay ?? 'flex' }}';
    try { sessionStorage.setItem(seenKey, '1'); } catch (e) {}
  }, 400);
})();
</script>
