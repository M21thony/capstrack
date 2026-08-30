<script>
document.addEventListener('DOMContentLoaded', function () {
  var sk = document.getElementById('skeleton-content');
  var rc = document.getElementById('real-content');
  if (!sk || !rc) return;

  var revealDisplay = '{{ $revealDisplay ?? 'block' }}';

  // Only fake-load the FIRST time this page is opened during this login.
  // Pages here end CRUD actions with window.location.reload(), and replaying
  // a 600ms fade on every save just puts a stutter between the click and the
  // result. Scoped to the login session (session()->getId() changes when the
  // session is invalidated at logout) so signing back in shows it again,
  // without every logout button having to clear sessionStorage itself.
  var seenKey = 'sk-seen:{{ substr(sha1(session()->getId()), 0, 12) }}:' + window.location.pathname;
  var alreadySeen = false;
  try { alreadySeen = !!sessionStorage.getItem(seenKey); } catch (e) {}

  if (alreadySeen) {
    sk.remove();
    rc.style.display = revealDisplay;
    return;
  }

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
});
</script>
