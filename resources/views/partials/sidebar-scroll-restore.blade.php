<script>
(function () {
  // Every nav click is a full page load in this app, which otherwise resets
  // the sidebar's scroll position to the top - annoying when the item
  // you're navigating to is itself near the bottom of a long list.
  var nav = document.querySelector('.sidebar .nav');
  if (!nav) return;
  var key = 'sidebarScrollTop';
  var saved = sessionStorage.getItem(key);
  if (saved !== null) nav.scrollTop = parseInt(saved, 10);
  nav.addEventListener('scroll', function () {
    sessionStorage.setItem(key, nav.scrollTop);
  });
})();
</script>
