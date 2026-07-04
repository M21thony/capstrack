@php
  $idleLogoutRoute = Auth::guard('expert')->check() ? 'expert.logout' : 'logout';
@endphp

<style>
  #idle-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 999999;
    display: none;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.25s ease;
  }
  #idle-overlay.open {
    display: flex;
    opacity: 1;
  }
  #idle-modal {
    background: #fff;
    border-radius: 16px;
    padding: 36px 40px 30px;
    max-width: 400px;
    width: 90%;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: idleFadeIn 0.3s ease;
  }
  @keyframes idleFadeIn {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
  }
  #idle-modal .idle-icon {
    width: 52px;
    height: 52px;
    margin: 0 auto 16px;
    background: #fef3c7;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  #idle-modal .idle-icon svg {
    width: 26px;
    height: 26px;
    stroke: #d97706;
    fill: none;
    stroke-width: 2.5;
    stroke-linecap: round;
    stroke-linejoin: round;
  }
  #idle-modal .idle-title {
    font-size: 20px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
  }
  #idle-modal .idle-message {
    font-size: 14px;
    color: #64748b;
    line-height: 1.5;
    margin-bottom: 20px;
  }
  #idle-modal .idle-timer {
    font-size: 42px;
    font-weight: 700;
    color: #ef4444;
    font-variant-numeric: tabular-nums;
    letter-spacing: 1px;
    margin-bottom: 24px;
  }
  #idle-modal .idle-actions {
    display: flex;
    gap: 10px;
  }
  #idle-modal .idle-actions button {
    flex: 1;
    padding: 11px 0;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: background 0.15s, transform 0.1s;
  }
  #idle-modal .idle-actions button:active {
    transform: scale(0.97);
  }
  #idle-modal .btn-stay {
    background: #2563eb;
    color: #fff;
  }
  #idle-modal .btn-stay:hover {
    background: #1d4ed8;
  }
  #idle-modal .btn-logout {
    background: #f1f5f9;
    color: #475569;
  }
  #idle-modal .btn-logout:hover {
    background: #e2e8f0;
  }
</style>

<div id="idle-overlay">
  <div id="idle-modal">
    <div class="idle-icon">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    </div>
    <div class="idle-title">Session Timeout</div>
    <div class="idle-message">
      You have been inactive for 10 minutes.<br>
      For security, your session will expire soon.
    </div>
    <div class="idle-timer" id="idle-countdown">01:00</div>
    <div class="idle-actions">
      <button class="btn-stay" onclick="staySignedIn()">Stay Signed In</button>
      <button class="btn-logout" onclick="logoutNow()">Logout</button>
    </div>
  </div>
</div>

<form id="idle-logout-form" action="{{ route($idleLogoutRoute) }}" method="POST" style="display:none">
  @csrf
</form>

<script>
  const INACTIVITY_LIMIT = 10 * 60 * 1000;
  const COUNTDOWN_DURATION = 1 * 60;

  let idleTimer = null;
  let countdownTimer = null;
  let countdownSeconds = COUNTDOWN_DURATION;
  const overlay = document.getElementById('idle-overlay');
  const countdownEl = document.getElementById('idle-countdown');

  function pad(n) {
    return String(n).padStart(2, '0');
  }

  function formatTime(sec) {
    const m = Math.floor(sec / 60);
    const s = sec % 60;
    return pad(m) + ':' + pad(s);
  }

  function startIdleTimer() {
    if (idleTimer) clearTimeout(idleTimer);
    idleTimer = setTimeout(showIdleModal, INACTIVITY_LIMIT);
  }

  function clearAllTimers() {
    if (idleTimer) { clearTimeout(idleTimer); idleTimer = null; }
    if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
  }

  function showIdleModal() {
    clearAllTimers();
    countdownSeconds = COUNTDOWN_DURATION;
    countdownEl.textContent = formatTime(countdownSeconds);
    overlay.classList.add('open');
    countdownTimer = setInterval(function() {
      countdownSeconds--;
      countdownEl.textContent = formatTime(countdownSeconds);
      if (countdownSeconds <= 0) {
        clearInterval(countdownTimer);
        countdownTimer = null;
        logoutNow();
      }
    }, 1000);
  }

  function staySignedIn() {
    fetch('/session/keep-alive', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        'Accept': 'application/json',
      },
    }).then(function(res) {
      if (res.ok) {
        overlay.classList.remove('open');
        clearAllTimers();
        startIdleTimer();
      }
    });
  }

  function logoutNow() {
    clearAllTimers();
    document.getElementById('idle-logout-form').submit();
  }

  function onUserActivity() {
    if (!overlay.classList.contains('open')) {
      startIdleTimer();
    }
  }

  var activityEvents = ['mousemove', 'keydown', 'mousedown', 'touchstart', 'scroll', 'wheel'];
  for (var i = 0; i < activityEvents.length; i++) {
    document.addEventListener(activityEvents[i], onUserActivity, { passive: true });
  }

  startIdleTimer();
</script>
