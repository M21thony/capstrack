<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Meet the Team — CapsTrack</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  @font-face {
    font-family: 'Geist Pixel';
    font-style: normal;
    font-weight: 400;
    font-display: swap;
    src: url('{{ asset('fonts/geist-pixel-400.woff2') }}') format('woff2');
  }

  :root {
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
    min-height: 100vh;min-height: 100dvh;
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

  ::selection { background: rgba(181,148,31,0.28); }

  .team-page {
    display: flex;
    width: 100%;
    max-width: 1360px;
    min-height: min(820px, calc(100vh - 56px));min-height: min(820px, calc(100dvh - 56px));
    border-radius: 26px;
    overflow: hidden;
    box-shadow: 0 50px 100px -20px rgba(0,0,0,0.65), 0 0 0 1px rgba(255,255,255,0.04);
  }

  /* ══════════════════════ LEFT — NARRATIVE PANEL ══════════════════════ */
  .story-panel {
    position: relative;
    flex: 0 0 40%;
    min-width: 0;
    background: var(--navy);
    color: #fff;
    display: flex;
    flex-direction: column;
    padding: clamp(28px, 4vw, 64px);
    overflow: hidden;
  }
  .story-scrim {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 30% 110%, rgba(245,183,0,0.14), transparent 55%),
                linear-gradient(160deg, rgba(0,0,0,0.55), rgba(0,0,0,0.85));
  }

  .story-mid {
    position: relative;
    z-index: 2;
    margin: auto 0;
    max-width: 460px;
  }

  .eyebrow {
    font-variant-numeric: tabular-nums;
    font-size: 11.5px;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--gold-bright);
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 18px;
  }
  .eyebrow::before {
    content: '';
    width: 22px;
    height: 1px;
    background: var(--gold);
  }

  h1.story-title {
    font-family: 'Geist Pixel', sans-serif;
    font-weight: 400;
    font-size: clamp(2rem, 2.8vw, 3rem);
    line-height: 1.12;
    letter-spacing: -0.01em;
    margin: 0 0 22px;
    color: #fff;
  }
  h1.story-title em {
    font-style: normal;
    color: var(--gold-bright);
  }

  .story-sub {
    font-size: 14.5px;
    line-height: 1.7;
    color: rgba(255,255,255,0.6);
    margin: 0;
    max-width: 42ch;
  }

  /* Ambient trend-line motif, shared with the login page. */
  .trend-motif {
    position: absolute;
    left: -4%;
    bottom: -8%;
    width: min(52%, 440px);
    opacity: 0.45;
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

  .story-bottom {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding-top: 24px;
    border-top: 1px solid var(--line-dark);
    font-variant-numeric: tabular-nums;
    font-size: 11px;
    letter-spacing: 0.04em;
    color: rgba(255,255,255,0.35);
  }
  .back-link {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-variant-numeric: tabular-nums;
    font-size: inherit;
    color: rgba(255,255,255,0.5);
    text-decoration: none;
    transition: color 0.15s ease;
  }
  .back-link svg {
    width: 14px;
    height: 14px;
    stroke: currentColor;
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    transition: transform 0.15s ease;
  }
  .back-link:hover { color: var(--gold-bright); }
  .back-link:hover svg { transform: translateX(-3px); }
  .back-link:focus-visible {
    outline: 2px solid var(--gold);
    outline-offset: 2px;
    border-radius: 4px;
  }

  /* ══════════════════════ RIGHT — PEOPLE PANEL ══════════════════════ */
  .people-panel {
    flex: 1 1 60%;
    min-width: 0;
    background:
      radial-gradient(circle at 70% 15%, rgba(245,183,0,0.08), transparent 45%),
      var(--cream);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: clamp(24px, 4vw, 56px);
    overflow-y: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
  }
  .people-panel::-webkit-scrollbar { display: none; }

  .people-grid {
    /* Six half-width columns so the 3-over-2 rows work: each card spans
       two columns, and the bottom pair starts one column in, which centres
       them under the top row (a plain 3-col grid left-aligns cards 4-5). */
    display: grid;
    grid-template-columns: repeat(6, minmax(80px, 100px));
    gap: clamp(18px, 2.4vw, 34px);
    justify-content: center;
  }

  .person-card {
    grid-column: span 2;
    background: var(--cream-card);
    border-radius: 16px;
    padding: 30px 18px 24px;
    text-align: center;
    box-shadow: 0 2px 6px rgba(23,26,31,0.05), 0 12px 28px rgba(23,26,31,0.06);
    transition: transform 0.18s ease, box-shadow 0.18s ease;
  }
  /* Second row: start one column in, so the pair of cards sits centred. */
  .person-card:nth-child(4) { grid-column: 2 / span 2; }
  .person-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 10px rgba(23,26,31,0.06), 0 20px 40px rgba(23,26,31,0.1);
  }

  .avatar {
    width: 72px;
    height: 72px;
    margin: 0 auto 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(145deg, var(--gold-bright), var(--gold-dim));
    color: #fff;
    font-weight: 700;
    font-size: 20px;
    letter-spacing: 0.02em;
    text-shadow: 0 1px 2px rgba(0,0,0,0.18);
    overflow: hidden;
  }
  .avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  .person-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--ink);
    margin: 0 0 4px;
    letter-spacing: -0.01em;
  }
  .person-role {
    font-variant-numeric: tabular-nums;
    font-size: 12px;
    color: var(--ink-soft);
    margin: 0 0 16px;
  }

  .person-links {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }
  .person-links a,
  .person-links button,
  .person-links .icon-off {
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #f3f0e8;
    color: var(--ink-soft);
    transition: background 0.15s ease, color 0.15s ease;
  }
  .person-links button {
    border: 0;
    padding: 0;
    font: inherit;
    cursor: pointer;
  }
  .person-links a:hover,
  .person-links button:hover {
    background: var(--gold);
    color: #fff;
  }
  .person-links a:focus-visible,
  .person-links button:focus-visible {
    outline: 2px solid var(--gold);
    outline-offset: 2px;
  }
  /* Nothing to link or copy yet: the slot stays so the row of icons never
     reflows, but it reads as visibly empty rather than clickable. */
  .person-links .icon-off {
    opacity: 0.45;
    cursor: default;
  }
  /* Copied: the envelope fills in briefly so the click is acknowledged. */
  .person-links .icon-copy.copied {
    background: var(--gold);
    color: #fff;
  }
  .person-links svg {
    width: 14px;
    height: 14px;
    fill: currentColor;
  }

  /* The "Copied" notice at the side of the screen, so the click is
     acknowledged even when the envelope is scrolled out of sight or too
     small to notice changing colour. */
  .copy-toast {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 1000;
    display: flex;
    align-items: center;
    gap: 12px;
    max-width: calc(100vw - 32px);
    padding: 12px 18px 12px 14px;
    border-radius: 12px;
    border-left: 3px solid var(--gold);
    background: var(--navy-panel);
    color: #fff;
    box-shadow: 0 14px 34px rgba(0, 0, 0, 0.28);
    font-size: 13.5px;
    line-height: 1.35;
    opacity: 0;
    transform: translateX(24px);
    pointer-events: none;
    transition: opacity 0.2s ease, transform 0.2s ease;
  }
  .copy-toast.show {
    opacity: 1;
    transform: none;
  }
  .copy-toast-ic {
    flex: none;
    width: 26px;
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: var(--gold);
    color: var(--ink);
  }
  .copy-toast-ic svg {
    width: 14px;
    height: 14px;
    fill: none;
    stroke: currentColor;
    stroke-width: 3;
    stroke-linecap: round;
    stroke-linejoin: round;
  }
  .copy-toast.failed .copy-toast-ic {
    background: #f87171;
  }
  .copy-toast strong {
    display: block;
    font-weight: 700;
  }
  .copy-toast span {
    display: block;
    color: rgba(255, 255, 255, 0.7);
    overflow-wrap: anywhere;
  }
  @media (max-width: 600px) {
    .copy-toast {
      right: 16px;
      left: 16px;
      bottom: calc(16px + env(safe-area-inset-bottom));
      transform: translateY(16px);
    }
  }
  @media (prefers-reduced-motion: reduce) {
    .copy-toast { transition: none; }
  }

  /* ══════════════════════ RESPONSIVE ══════════════════════ */
  @media (max-width: 1000px) {
    /* body's align-items/justify-content: center only works while the card
       is shorter than the viewport. Stacked, the hero panel + five person
       cards run taller than a phone screen, and a centred flex item that
       overflows gets clipped equally off both edges — the top of the page
       (the "Built by team Bulbasaur" panel) ends up scrolled above y:0
       with no way to reach it. Below this breakpoint the page just flows
       top-to-bottom instead, same as login.blade.php's own phone layout. */
    body { display: block; padding: 0; }
    .team-page { flex-direction: column; min-height: 100vh;min-height: 100dvh; border-radius: 0; max-width: 100%; }
    .story-panel { flex: none; padding: 32px 24px; }
    .story-mid { margin: 28px 0 40px; }
    .trend-motif { width: 60%; opacity: 0.3; }
    .people-panel { flex: none; padding: 36px 20px 56px; overflow-y: visible; }
    .people-grid { grid-template-columns: repeat(2, minmax(150px, 220px)); }
    .person-card, .person-card:nth-child(4) { grid-column: auto; }
    /* The odd 5th card gets its own centred row, sized like its siblings. */
    .person-card:last-child:nth-child(odd) {
      grid-column: 1 / -1;
      justify-self: center;
      width: min(220px, 100%);
    }
  }
  @media (max-width: 480px) {
    .story-panel { padding: 26px 18px; }
    .people-grid { grid-template-columns: minmax(200px, 280px); }
  }
</style>
</head>
<body>

<div class="team-page">
  <div class="story-panel">
    <div class="story-scrim" aria-hidden="true"></div>

    <svg class="trend-motif" viewBox="0 0 420 260" aria-hidden="true" focusable="false">
      <path d="M0,240 L40,196 L92,214 L150,140 L214,168 L292,72 L360,104 L420,40"/>
    </svg>

    <div class="story-mid">
      <p class="eyebrow">The Team Behind It</p>
      <h1 class="story-title">Built by team <em>Bulbasaur</em>, for capstone students</h1>
      <p class="story-sub">A CSPC College of Computer Studies capstone team who experienced the challenges students faced this system now solves.</p>
    </div>

    <div class="story-bottom">
      <span>© {{ date('Y') }} CSPC College of Computer Studies</span>
      <a href="{{ route('login') }}" class="back-link">
        <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Back to Sign In
      </a>
    </div>
  </div>

  <div class="people-panel">
    <div class="people-grid">
      @foreach ($team as $member)
        <div class="person-card">
          @php $photo = $member['image'] && file_exists(public_path($member['image'])) ? $member['image'] : null; @endphp
          <div class="avatar{{ $photo ? ' has-photo' : '' }}" aria-hidden="true">
            @if ($photo)
              <img src="{{ asset($photo) }}" alt="{{ $member['name'] }}">
            @else
              {{ $member['initials'] }}
            @endif
          </div>
          <p class="person-name">{{ $member['name'] }}</p>
          <p class="person-role">{{ $member['role'] }}</p>
          <div class="person-links">
            @if ($member['facebook'])
              <a href="{{ $member['facebook'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $member['name'] }} on Facebook">
                <svg viewBox="0 0 24 24"><path d="M13.5 21v-8h2.7l.4-3.1h-3.1V7.9c0-.9.25-1.5 1.55-1.5h1.65V3.6c-.3-.04-1.3-.13-2.45-.13-2.4 0-4.05 1.47-4.05 4.17v2.35H8.5V13h2.7v8h2.3z"/></svg>
              </a>
            @else
              <span class="icon-off" title="Facebook link not added yet" aria-label="Facebook link not added yet">
                <svg viewBox="0 0 24 24"><path d="M13.5 21v-8h2.7l.4-3.1h-3.1V7.9c0-.9.25-1.5 1.55-1.5h1.65V3.6c-.3-.04-1.3-.13-2.45-.13-2.4 0-4.05 1.47-4.05 4.17v2.35H8.5V13h2.7v8h2.3z"/></svg>
              </span>
            @endif
            @if ($member['email'])
              <button type="button" class="icon-copy" data-email="{{ $member['email'] }}" title="Copy {{ $member['name'] }}'s email" aria-label="Copy {{ $member['name'] }}'s email">
                <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
              </button>
            @else
              <span class="icon-off" title="Email not added yet" aria-label="Email not added yet">
                <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
              </span>
            @endif
          </div>
        </div>
      @endforeach
    </div>
  </div>
</div>

<div class="copy-toast" id="copy-toast" role="status" aria-live="polite">
  <span class="copy-toast-ic" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m5 13 4 4 10-10"/></svg></span>
  <div><strong id="copy-toast-title">Copied</strong><span id="copy-toast-text"></span></div>
</div>

<script>
  // The envelope copies the address rather than opening a mail client: this is
  // a showcase page people land on from the sign-in screen, so a mail window
  // for one of five strangers would be noise.
  document.querySelectorAll('.icon-copy').forEach(function (button) {
    button.addEventListener('click', function () {
      var email = button.getAttribute('data-email') || '';
      var markCopied = function () {
        button.classList.add('copied');
        window.setTimeout(function () { button.classList.remove('copied'); }, 1200);
        showToast(true, email);
      };
      var markFailed = function () { showToast(false, email); };

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(email).then(markCopied, function () { copyFromField(email, markCopied, markFailed); });
        return;
      }

      copyFromField(email, markCopied, markFailed);
    });
  });

  // Says so at the side of the screen, with the address itself - which is
  // also how it can still be copied by hand when the browser refuses.
  var toast = document.getElementById('copy-toast');
  var toastTimer = null;
  function showToast(copied, email) {
    toast.classList.toggle('failed', !copied);
    document.getElementById('copy-toast-title').textContent = copied ? 'Copied' : "Couldn't copy";
    document.getElementById('copy-toast-text').textContent = copied ? email + ' is on your clipboard.' : 'The address is ' + email + '.';
    toast.classList.add('show');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(function () { toast.classList.remove('show'); }, 4000);
  }

  // Older browsers and non-secure origins have no async clipboard API, so the
  // address goes through a hidden field and a copy command instead.
  function copyFromField(text, done, failed) {
    var field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', 'readonly');
    field.style.position = 'fixed';
    field.style.top = '-1000px';
    document.body.appendChild(field);
    field.select();

    try {
      if (document.execCommand('copy')) {
        done();
      } else {
        failed();
      }
    } catch (error) {
      // Clipboard access can still be refused; the notice then shows the
      // address so it can be copied by hand.
      failed();
    }

    document.body.removeChild(field);
  }
</script>

</body>
</html>

