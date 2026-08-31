<script>
(function () {
  var sk = document.getElementById('skeleton-content');
  var rc = document.getElementById('real-content');
  if (!sk || !rc) return;

  // Already decided in <head> by partials/skeleton-head - the real content was
  // painted straight away and no skeleton was ever shown. This used to wait
  // for DOMContentLoaded before even checking, which guaranteed a flash of
  // loading state over content that was already there on every save.
  if (document.documentElement.classList.contains('sk-seen')) {
    sk.remove();

    return;
  }

  var revealDisplay = '{{ $revealDisplay ?? 'block' }}';
  var seenKey = 'sk-seen:{{ substr(sha1(session()->getId()), 0, 12) }}:' + window.location.pathname;

  requestAnimationFrame(function () {
    setTimeout(function () {
      sk.style.transition = 'opacity 0.3s ease';
      sk.style.opacity = '0';
      rc.style.display = revealDisplay;
      rc.style.transition = 'opacity 0.35s ease';
      setTimeout(function () {
        sk.remove();
        rc.style.transition = '';
      }, 350);
      try { sessionStorage.setItem(seenKey, '1'); } catch (e) {}
    }, 600);
  });
})();
</script>
