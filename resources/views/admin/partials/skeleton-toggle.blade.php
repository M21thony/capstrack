<script>
(function () {
  var sk = document.getElementById('skeleton-content');
  var rc = document.getElementById('real-content');
  if (sk && rc) {
    setTimeout(function () {
      sk.style.display = 'none';
      rc.style.display = '{{ $revealDisplay ?? 'flex' }}';
    }, 400);
  }
})();
</script>
