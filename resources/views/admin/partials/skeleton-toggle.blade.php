<script>
(function () {
  var sk = document.getElementById('skeleton-content');
  var rc = document.getElementById('real-content');
  if (!sk || !rc) return;

  var reveal = function () {
    sk.style.display = 'none';
    rc.style.display = '{{ $revealDisplay ?? 'flex' }}';
  };

  // Only fake-load once per page per browser tab (e.g. right after login,
  // or the first time you navigate here) - reject cookies/private-mode can
  // make sessionStorage throw, in which case just fall back to the old
  // always-show-it behavior rather than breaking the page.
  var seenKey = 'sk-seen:' + window.location.pathname;
  var alreadySeen = false;
  try {
    alreadySeen = !!sessionStorage.getItem(seenKey);
  } catch (e) {}

  if (alreadySeen) {
    reveal();
  } else {
    setTimeout(function () {
      reveal();
      try { sessionStorage.setItem(seenKey, '1'); } catch (e) {}
    }, 400);
  }
})();
</script>
