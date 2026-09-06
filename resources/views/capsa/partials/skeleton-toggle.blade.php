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
  // Scoped to THIS login: session()->getId() changes when the session is
  // invalidated at logout, so signing back in shows the skeleton again on
  // first visit - without needing every logout button to remember to clear
  // sessionStorage (several of them didn't).
  var seenKey = 'sk-seen:{{ substr(sha1(session()->getId()), 0, 12) }}:' + window.location.pathname;
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
