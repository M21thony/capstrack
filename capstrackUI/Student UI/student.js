async function loadSidebar() {
  const slot = document.getElementById('sidebar-slot');
  if (!slot) return;

  // If sidebar.html is in /partials:
  const res = await fetch('partials/sidebar.html');
  const html = await res.text();
  slot.innerHTML = html;

  setActiveNav();
}

function setActiveNav() {
  const file = (location.pathname.split('/').pop() || 'dashboard.html').toLowerCase();

  const map = {
    'dashboard.html': 'nav-dashboard',
    'team.html': 'nav-team',
    'experts.html': 'nav-experts',
    'submission.html': 'nav-submission',
    'review.html': 'nav-review',
    'duplication.html': 'nav-duplication',
    'consultation.html': 'nav-consultation',
    'progress.html': 'nav-progress',
    'financial.html': 'nav-financial',
    'resources.html': 'nav-resources',   // add this id in sidebar if missing
    'archive.html': 'nav-archive',
  };

  const id = map[file];
  if (!id) return;

  document.querySelectorAll('.nav-item').forEach(a => a.classList.remove('active'));
  const active = document.getElementById(id);
  if (active) active.classList.add('active');
}

document.addEventListener('DOMContentLoaded', loadSidebar);