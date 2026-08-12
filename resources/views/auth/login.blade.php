<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Sign in — CapsTrack</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>
<style>
  @font-face {
    font-family: 'Geist Pixel';
    font-style: normal;
    font-weight: 400;
    font-display: swap;
    src: url('{{ asset('fonts/geist-pixel-400.woff2') }}') format('woff2');
  }

  :root {
    /* Department theme: black + yellow. */
    --navy: #0a0a0a;
    --navy-panel: #161616;
    --navy-deep: #000000;
    --gold: #f5b700;
    --gold-bright: #ffd23f;
    --gold-dim: #c99400;
    --cream: #faf9f6;
    --cream-card: #ffffff;
    --ink: #141414;
    --ink-soft: #5c5c58;
    --line: #e6e3da;
    --line-dark: rgba(255,255,255,0.09);
  }

  * { box-sizing: border-box; }

  html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    background: var(--navy);
    color: var(--ink);
    font-family: 'DM Sans', sans-serif;
    -webkit-font-smoothing: antialiased;
  }

  body {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    overflow-x: hidden;
    padding: 28px 24px;
    background-image:
      radial-gradient(circle at center, transparent 20%, var(--navy) 100%),
      linear-gradient(rgba(0,0,0,0.55), rgba(0,0,0,0.55)),
      url('{{ asset('images/bg-ccs.jpg') }}');
    background-size: cover, cover, cover;
    background-position: center, center, center;
    background-repeat: no-repeat, no-repeat, no-repeat;
    background-color: var(--navy-deep);
  }

  a { color: inherit; }

  ::selection { background: rgba(181,148,31,0.28); }

  /* The centered "card" — everything below lives inside this modal shell
     rather than stretching edge-to-edge. */
  .login-modal {
    display: flex;
    width: 100%;
    max-width: 1320px;
    max-height: min(860px, calc(100vh - 56px));
    border-radius: 26px;
    overflow: hidden;
    box-shadow: 0 50px 100px -20px rgba(0,0,0,0.65), 0 0 0 1px rgba(255,255,255,0.04);
  }

  /* ══════════════════════ LEFT — BRAND PANEL ══════════════════════ */
  .brand-panel {
    position: relative;
    flex: 1 1 56%;
    min-width: 0;
    background: var(--navy);
    color: #fff;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: clamp(32px, 4.5vw, 76px);
    overflow: hidden;
  }

  .brand-bg {
    position: absolute;
    inset: 0;
    background-image: url('{{ asset('images/bg-ccs.jpg') }}');
    background-size: cover;
    background-position: center;
    opacity: 0.16;
  }
  .brand-scrim {
    position: absolute;
    inset: 0;
    /* Darker overall than welcome.blade.php's hero vignette (which goes
       fully transparent at center) — this panel should read dark
       throughout, not just at the edges. */
    background: radial-gradient(circle at center, rgba(0,0,0,0.7) 20%, var(--navy) 100%);
  }

  /* Ambient trend-line motif — echoes the logo's own gold upward arrow.
     Purely decorative, so it's aria-hidden and skipped for reduced motion. */
  .trend-motif {
    position: absolute;
    right: -4%;
    bottom: -6%;
    width: min(64%, 620px);
    opacity: 0.5;
    pointer-events: none;
  }
  .trend-motif path {
    fill: none;
    stroke: var(--gold);
    stroke-width: 1.6;
    stroke-linecap: round;
    stroke-linejoin: round;
    vector-effect: non-scaling-stroke;
  }
  .trend-motif .fill {
    stroke: none;
    fill: url(#trendFade);
  }
  .trend-line {
    stroke-dasharray: 900;
    stroke-dashoffset: 900;
    animation: drawTrend 2.6s cubic-bezier(.16,.8,.24,1) 0.3s forwards;
  }
  @media (prefers-reduced-motion: reduce) {
    .trend-line { animation: none; stroke-dashoffset: 0; }
  }
  @keyframes drawTrend { to { stroke-dashoffset: 0; } }

  .brand-top {
    position: relative;
    z-index: 2;
  }

  .lockup {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .lockup img {
    height: 34px;
    width: auto;
    display: block;
    filter: brightness(0) invert(1);
  }

  .eyebrow {
    margin: clamp(32px, 6vh, 64px) 0 0;
    font-family: 'Space Mono', monospace;
    font-size: 11.5px;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--gold-bright);
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .eyebrow::before {
    content: '';
    width: 22px;
    height: 1px;
    background: var(--gold);
  }

  .brand-mid {
    position: relative;
    z-index: 2;
    max-width: 620px;
  }

  h1.headline {
    font-family: 'Geist Pixel', sans-serif;
    font-weight: 400;
    font-size: clamp(2.3rem, 3.2vw, 3.6rem);
    line-height: 1.08;
    letter-spacing: -0.01em;
    margin: 16px 0 20px;
    text-wrap: balance;
    color: #fff;
  }
  h1.headline em {
    font-style: normal;
    color: var(--gold-bright);
  }

  .subhead {
    font-size: 17px;
    line-height: 1.65;
    color: rgba(255,255,255,0.68);
    max-width: 48ch;
    margin: 0;
  }

  .feature-grid {
    position: relative;
    z-index: 2;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 28px 34px;
    margin-top: clamp(34px, 5.5vh, 60px);
  }

  .feature {
    display: flex;
    gap: 13px;
    align-items: flex-start;
  }
  .feature svg {
    flex: none;
    width: 20px;
    height: 20px;
    stroke: var(--gold-bright);
    fill: none;
    stroke-width: 1.9;
    stroke-linecap: round;
    stroke-linejoin: round;
    margin-top: 2px;
  }
  .feature-label {
    font-family: 'Space Mono', monospace;
    font-size: 13px;
    letter-spacing: 0.03em;
    color: #fff;
    margin: 0 0 4px;
    font-weight: 700;
  }
  .feature-desc {
    font-size: 13.5px;
    line-height: 1.5;
    color: rgba(255,255,255,0.5);
    margin: 0;
  }

  .brand-bottom {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 16px;
    padding-top: 28px;
    margin-top: 28px;
    border-top: 1px solid var(--line-dark);
    font-family: 'Space Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.04em;
    color: rgba(255,255,255,0.35);
  }
  .brand-bottom .tag {
    color: rgba(255,255,255,0.5);
  }

  /* ══════════════════════ RIGHT — SIGN-IN PANEL ══════════════════════ */
  .signin-panel {
    flex: 1 1 44%;
    min-width: 0;
    background: var(--cream);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: clamp(24px, 4.5vw, 56px);
    overflow-y: auto;
  }

  .signin-col {
    width: 100%;
    max-width: 420px;
  }

  .mini-wordmark {
    font-family: 'Space Mono', monospace;
    font-weight: 700;
    font-size: 16px;
    letter-spacing: -0.01em;
    color: var(--ink);
    margin-bottom: clamp(16px, 3vh, 40px);
  }
  .mini-wordmark span { color: var(--gold-dim); }

  .signin-heading {
    font-size: 30px;
    font-weight: 700;
    letter-spacing: -0.015em;
    margin: 0 0 9px;
    color: var(--ink);
  }
  .signin-sub {
    font-size: 15px;
    color: var(--ink-soft);
    margin: 0 0 24px;
    line-height: 1.5;
  }

  .error-box {
    margin-bottom: 14px;
    padding: 11px 16px;
    background: rgba(220,38,38,0.06);
    border: 1px solid rgba(220,38,38,0.2);
    border-radius: 11px;
    color: #b91c1c;
    font-size: 13px;
    line-height: 1.5;
  }
  .error-box ul { margin: 0; padding-left: 18px; }

  .google-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 11px;
    padding: 12px 20px;
    background: #fff;
    border: 1px solid #d7d2c4;
    border-radius: 11px;
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    font-weight: 600;
    color: var(--ink);
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.1s ease;
  }
  .google-btn:hover {
    border-color: #b9b2a0;
    box-shadow: 0 3px 10px rgba(23,26,31,0.06);
  }
  .google-btn:active { transform: translateY(1px); }
  .google-btn:focus-visible {
    outline: 2px solid var(--gold);
    outline-offset: 2px;
  }
  .google-btn:disabled { opacity: 0.6; cursor: default; transform: none; }
  .google-btn svg { width: 19px; height: 19px; flex: none; }

  .note-box {
    margin-top: 16px;
    padding: 12px 18px;
    background: #f3f0e8;
    border: 1px solid var(--line);
    border-radius: 11px;
  }
  .note-box p {
    margin: 0 0 9px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--gold-dim);
    font-family: 'Space Mono', monospace;
  }
  .note-box ul {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 7px;
  }
  .note-box li {
    font-size: 12.5px;
    line-height: 1.55;
    color: var(--ink-soft);
    padding-left: 14px;
    position: relative;
  }
  .note-box li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 7px;
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--gold);
  }
  .note-box strong { color: var(--ink); font-weight: 600; }

  .divider {
    display: flex;
    align-items: center;
    gap: 14px;
    margin: 12px 0 12px;
    color: #a8a293;
    font-family: 'Space Mono', monospace;
    font-size: 10.5px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
  }
  .divider::before, .divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--line);
  }

  .request-link {
    display: block;
    text-align: center;
    font-size: 13px;
    color: var(--ink-soft);
    margin: 8px 0;
  }
  .request-link a {
    color: var(--ink);
    font-weight: 700;
    text-decoration: none;
    border-bottom: 1px solid var(--gold);
    padding-bottom: 1px;
  }
  .request-link a:hover { color: var(--gold-dim); }

  .signin-footer {
    margin-top: clamp(12px, 2.5vh, 32px);
    font-size: 11px;
    color: #a8a293;
    text-align: center;
  }

  /* ══════════════════════ EXPERT-REMOVED MODAL ══════════════════════ */
  .modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(10,10,10,0.75);
    backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 3000;
  }
  .modal-card {
    background: var(--cream-card);
    border-radius: 18px;
    box-shadow: 0 30px 60px rgba(0,0,0,0.35);
  }

  /* ══════════════════════ RESPONSIVE ══════════════════════ */
  @media (max-width: 900px) {
    body { padding: 0; align-items: stretch; }
    .login-modal {
      flex-direction: column;
      max-width: 100%;
      max-height: none;
      min-height: 100vh;
      border-radius: 0;
    }
    .brand-panel {
      flex: none;
      min-height: 300px;
      padding: 28px 24px;
    }
    .eyebrow { margin-top: 20px; }
    h1.headline { font-size: clamp(1.7rem, 6vw, 2.2rem); }
    .subhead { font-size: 14px; }
    .feature-grid { display: none; }
    .brand-bottom { display: none; }
    .trend-motif { width: 80%; opacity: 0.35; }
    .signin-panel { padding: 32px 24px 48px; overflow-y: visible; }
    .mini-wordmark { margin-bottom: 28px; }
  }

  @media (max-width: 480px) {
    .brand-panel { min-height: 250px; padding: 22px 18px; }
    .lockup img { height: 26px; }
    h1.headline { font-size: 1.5rem; }
  }
</style>
</head>
<body>

<div class="login-modal">
<div class="brand-panel">
  <div class="brand-bg" aria-hidden="true"></div>
  <div class="brand-scrim" aria-hidden="true"></div>

  <svg class="trend-motif" viewBox="0 0 420 260" aria-hidden="true" focusable="false">
    <defs>
      <linearGradient id="trendFade" x1="0" y1="1" x2="0" y2="0">
        <stop offset="0%" stop-color="#f5b700" stop-opacity="0"/>
        <stop offset="100%" stop-color="#f5b700" stop-opacity="0.12"/>
      </linearGradient>
    </defs>
    <path class="fill" d="M0,240 L40,196 L92,214 L150,140 L214,168 L292,72 L360,104 L420,40 L420,260 L0,260 Z"/>
    <path class="trend-line" d="M0,240 L40,196 L92,214 L150,140 L214,168 L292,72 L360,104 L420,40"/>
    <circle cx="420" cy="40" r="4.5" fill="#ffd23f"/>
  </svg>

  <div class="brand-top">
    <div class="lockup">
      <img src="{{ asset('images/capstrack.png') }}" alt="CapsTrack"/>
    </div>
  </div>

  <div class="brand-mid">
    <p class="eyebrow">CSPC · College of Computer Studies</p>
    <h1 class="headline">Centralized <em>Capstone</em> Management</h1>
    <p class="subhead">Form your team, apply to an adviser, and track your capstone through to final defense.</p>

    <div class="feature-grid">
      <div class="feature">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <div>
          <p class="feature-label">Team Formation</p>
          <p class="feature-desc">Recruit within your course and build your capstone team.</p>
        </div>
      </div>
      <div class="feature">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
        <div>
          <p class="feature-label">Expert Applications</p>
          <p class="feature-desc">Apply to advisers, consultants, and grammarians.</p>
        </div>
      </div>
      <div class="feature">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        <div>
          <p class="feature-label">Document Review</p>
          <p class="feature-desc">Submit manuscripts and get annotated feedback inline.</p>
        </div>
      </div>
      <div class="feature">
        <svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        <div>
          <p class="feature-label">Progress Tracking</p>
          <p class="feature-desc">Chapter-based milestones with overdue alerts.</p>
        </div>
      </div>
      <div class="feature">
        <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        <div>
          <p class="feature-label">Duplication Checker</p>
          <p class="feature-desc">AI similarity scoring against archived capstones.</p>
        </div>
      </div>
      <div class="feature">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <div>
          <p class="feature-label">Consultation Scheduling</p>
          <p class="feature-desc">Book adviser slots on an interactive calendar.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="brand-bottom">
    <span>© {{ date('Y') }} CSPC College of Computer Studies</span>
    <span class="tag">CapsTrack</span>
  </div>
</div>

<div class="signin-panel">
  <div class="signin-col">
    <div class="mini-wordmark">Caps<span>TRACK</span></div>

    <h2 class="signin-heading">Sign in to CapsTrack</h2>
    <p class="signin-sub">Use your CSPC Mail to continue.</p>

    @if ($errors->any())
      <div class="error-box">
        <ul>
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form id="google-login-form" method="POST" action="{{ route('login.google.initiate') }}">
      @csrf
      <input type="hidden" name="g-recaptcha-response" id="recaptcha-token">
      <button type="submit" id="google-signin-btn" class="google-btn">
        <svg viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
        Continue with CSPC Mail
      </button>
    </form>

    <div class="note-box">
      <p>How sign-in works</p>
      <ul>
        <li><strong>Students</strong> — sign in with your CSPC Mail to reach your dashboard right away.</li>
        <li><strong>Instructors</strong> — sign in the same way, then request account access.</li>
        <li>Approval can take up to <strong>3 days</strong>. You'll get an email once it's reviewed.</li>
      </ul>
    </div>

    <div class="divider">or</div>

    <p class="request-link">Don't have an account yet? <a href="{{ route('account.request') }}">Request Account</a></p>

    <p class="signin-footer">CapsTrack — CSPC College of Computer Studies</p>
  </div>
</div>
</div>

@if($removedExpert ?? false)
<div class="modal-overlay active" id="expert-removed-modal">
    <div class="modal-card" style="max-width: 440px; text-align: center; padding: 40px 30px;">
        <div style="width:56px;height:56px;border-radius:50%;background:rgba(239,68,68,0.08);color:#ef4444;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
            <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </div>
        <h3 style="font-size:17px;font-weight:700;color:#1e2128;margin:0 0 6px;letter-spacing:-0.3px;">Account Removed</h3>
        <p style="font-size:13px;color:#6b7280;line-height:1.6;margin:0 0 12px;text-align:left;">
            Your expert account <strong>{{ $removedExpertName }}</strong> has been removed from the system.
            @if($removedExpertReason)
                <br><br>Reason: {{ $removedExpertReason }}
            @endif
        </p>
        <button id="expert-removed-ok-btn" type="button" style="width:100%;padding:11px 16px;border-radius:10px;border:none;background:#1e2128;color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:background 0.15s;margin-top:4px;">OK</button>
    </div>
</div>
@endif

<script>
document.getElementById('google-login-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('google-signin-btn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.textContent = 'Redirecting...';
    grecaptcha.ready(function() {
        grecaptcha.execute('{{ config('services.recaptcha.site_key') }}', {action: 'login'}).then(function(token) {
            document.getElementById('recaptcha-token').value = token;
            fetch('{{ route('login.google.initiate') }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({'g-recaptcha-response': token}),
            }).then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            }).then(function(data) {
                if (data.success && data.auth_url) {
                    window.location.replace(data.auth_url);
                } else {
                    alert(data.message || 'Login failed. Please try again.');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            }).catch(function(err) {
                console.error(err);
                alert('Error: ' + (err.message || 'Network error. Please try again.'));
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });
    });
});

const expertRemovedModal = document.getElementById('expert-removed-modal');
const expertRemovedOkBtn = document.getElementById('expert-removed-ok-btn');
if (expertRemovedModal && expertRemovedOkBtn) {
    expertRemovedOkBtn.addEventListener('click', function() {
        expertRemovedModal.classList.remove('active');
        expertRemovedModal.style.display = 'none';
        const url = new URL(window.location);
        url.searchParams.delete('removed_expert');
        url.searchParams.delete('name');
        url.searchParams.delete('reason');
        window.history.replaceState({}, '', url);
    });
}
</script>
</body>
</html>
