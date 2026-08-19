<script>
document.addEventListener('DOMContentLoaded', function () {
  var sk = document.getElementById('skeleton-content');
  var rc = document.getElementById('real-content');
  if (sk && rc) {
    requestAnimationFrame(function () {
      setTimeout(function () {
        sk.style.transition = 'opacity 0.3s ease';
        sk.style.opacity = '0';
        rc.style.display = '{{ $revealDisplay ?? 'block' }}';
        rc.style.transition = 'opacity 0.35s ease';
        setTimeout(function () {
          sk.remove();
          rc.style.transition = '';
        }, 350);
      }, 600);
    });
  }
});
</script>
