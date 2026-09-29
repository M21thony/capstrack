<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Meet the Team — CapsTrack</title>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
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
    font-family: 'DM Sans','DM Sans Fallback', sans-serif;
    -webkit-font-smoothing: antialiased;
  }

  /* The card is centred by its own auto margins, not the body's flex
     centring: a centred box taller than the screen spilled off the top,
     out of reach, on a 768px-tall laptop. Auto margins stop at zero. */
  body {
    display: flex;
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
    margin: auto;
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
      radial-gradient(circle at 70% 15%, rgba(245,183,0,0.10), transparent 45%),
      radial-gradient(circle at 15% 90%, rgba(245,183,0,0.06), transparent 40%),
      var(--cream);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 22px;
    padding: clamp(24px, 4vw, 56px);
    overflow-y: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
  }
  .people-panel::-webkit-scrollbar { display: none; }

  .people-head {
    text-align: center;
  }
  .people-head h2 {
    margin: 0;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--ink);
  }
  .people-head p {
    margin: 4px 0 0;
    font-size: 12.5px;
    color: var(--ink-soft);
  }

  .people-grid {
    /* Six half-width columns so the 3-over-2 rows work: each card spans two,
       and the bottom pair starts one column in, centred under the top row. */
    display: grid;
    grid-template-columns: repeat(6, minmax(72px, 96px));
    gap: clamp(16px, 2vw, 26px);
    justify-content: center;
  }

  /* ── A profile, drawn as a trading card ──
     A gold foil frame, the member's number in the set, their role as the
     card's type, the photo as the art, and a sheen that runs across it on
     hover - the collectible card the phone's deck below deals out. */
  .person-card {
    grid-column: span 2;
    position: relative;
    border-radius: 18px;
    padding: 3px;
    background: linear-gradient(145deg, var(--gold-bright), var(--gold-dim) 45%, #fff3c4 60%, var(--gold) 100%);
    box-shadow: 0 2px 6px rgba(23,26,31,0.06), 0 16px 32px rgba(23,26,31,0.10);
    transition: transform 0.22s cubic-bezier(.2,.8,.2,1), box-shadow 0.22s ease;
  }
  .person-card:nth-child(4) { grid-column: 2 / span 2; }

  /* ── The flip ──
     On tablet and phone a tap turns the card over. Both faces sit in
     .card-inner, which is what rotates; the gold frame is the card itself, so
     it stays put and frames whichever side is showing. */
  .person-card { perspective: 1200px; }
  .card-inner {
    position: relative;
    height: 100%;
    transform-style: preserve-3d;
    transition: transform 0.6s cubic-bezier(.2,.8,.2,1);
  }
  .person-card.is-flipped .card-inner { transform: rotateY(180deg); }
  .card-face,
  .card-back {
    -webkit-backface-visibility: hidden;
    backface-visibility: hidden;
  }

  /* ── The card back ──
     A Pokémon-style back in CSPC's own colours: a blue sunburst, the CSPC seal
     in the middle where the ball would be, the CCS shield pinned to it, and
     the wordmark and college names in gold. */
  .card-back {
    position: absolute;
    inset: 0;
    transform: rotateY(180deg);
    border-radius: 15px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 20px 14px 18px;
    text-align: center;
    color: #fff;
    background:
      radial-gradient(circle at 50% 46%, rgba(255,210,63,0.32), transparent 36%),
      repeating-conic-gradient(from 0deg at 50% 46%, rgba(255,255,255,0.07) 0deg 5deg, transparent 5deg 10deg),
      radial-gradient(circle at 50% 46%, #2458c4 0%, #0c2f7d 52%, #051640 100%);
  }
  /* The gold inner border every trading-card back has. */
  .card-back::before {
    content: '';
    position: absolute;
    inset: 7px;
    border: 1.5px solid rgba(255,210,63,0.6);
    border-radius: 10px;
    pointer-events: none;
  }
  /* A faint diamond weave over the whole back, so it reads as printed card stock. */
  .card-back::after {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    background-image:
      linear-gradient(45deg, rgba(255,255,255,0.035) 25%, transparent 25%, transparent 75%, rgba(255,255,255,0.035) 75%),
      linear-gradient(45deg, rgba(255,255,255,0.035) 25%, transparent 25%, transparent 75%, rgba(255,255,255,0.035) 75%);
    background-size: 14px 14px;
    background-position: 0 0, 7px 7px;
  }
  .back-top,
  .back-emblem,
  .back-foot { position: relative; z-index: 1; }
  .back-top {
    font-family: 'Geist Pixel', monospace;
    font-size: 14px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--gold-bright);
    text-shadow: 0 1px 6px rgba(0,0,0,0.35);
  }
  .back-emblem {
    position: relative;
    width: 62%;
    aspect-ratio: 1 / 1;
    margin-bottom: 14px;
  }
  .back-seal {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: #fff;
    display: grid;
    place-items: center;
    box-shadow:
      0 0 0 3px var(--gold-bright),
      0 0 0 7px rgba(255,210,63,0.28),
      0 12px 30px rgba(0,0,0,0.45);
  }
  .back-seal img {
    width: 84%;
    height: 84%;
    object-fit: contain;
    -webkit-user-drag: none;
    user-select: none;
  }
  /* The CCS shield, pinned to the foot of the seal like a badge. */
  .back-ccs {
    position: absolute;
    left: 50%;
    bottom: -16px;
    width: 38%;
    aspect-ratio: 1 / 1;
    transform: translateX(-50%);
    border-radius: 50%;
    background: #0c2f7d;
    display: grid;
    place-items: center;
    box-shadow: 0 0 0 2.5px var(--gold-bright), 0 6px 14px rgba(0,0,0,0.4);
  }
  .back-ccs img {
    width: 64%;
    height: 64%;
    object-fit: contain;
    -webkit-user-drag: none;
    user-select: none;
  }
  .back-foot {
    display: grid;
    gap: 3px;
  }
  .back-foot b {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--gold-bright);
  }
  .back-foot span {
    font-size: 10px;
    letter-spacing: 0.04em;
    color: rgba(255,255,255,0.72);
  }

  /* The flip is for tablet and phone; the tap hint says so there only. */
  .flip-hint { display: none; }

  .card-face {
    position: relative;
    height: 100%;
    border-radius: 15px;
    background: var(--cream-card);
    padding: 10px 10px 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    overflow: hidden;
  }
  .card-top {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    margin-bottom: 8px;
    font-variant-numeric: tabular-nums;
  }
  .card-no {
    font-family: 'Geist Pixel', monospace;
    font-size: 12px;
    color: var(--gold-dim);
  }
  .card-set {
    font-size: 9.5px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--ink-soft);
  }
  .avatar {
    width: 100%;
    aspect-ratio: 5 / 4;
    margin: 0 0 12px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(145deg, var(--gold-bright), var(--gold-dim));
    color: #fff;
    font-weight: 700;
    font-size: 30px;
    letter-spacing: 0.02em;
    text-shadow: 0 1px 2px rgba(0,0,0,0.18);
    overflow: hidden;
    box-shadow: inset 0 0 0 1px rgba(0,0,0,0.06);
  }
  .avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    -webkit-user-drag: none;
    user-select: none;
  }
  /* The photos are portraits, and on desktop and tablet the box is landscape
     (5:4), so a centred crop cut off the tops of heads. Anchoring to the top
     keeps the whole head in frame. Phones use a square box and crop fine
     centred, so they keep it. */
  @media (min-width: 601px) {
    .avatar img { object-position: center top; }
  }
  .person-name {
    font-size: 14.5px;
    font-weight: 700;
    color: var(--ink);
    margin: 0 0 6px;
    letter-spacing: -0.01em;
    line-height: 1.25;
  }
  .person-role {
    display: inline-block;
    font-size: 11px;
    font-weight: 600;
    color: #7a5c00;
    background: rgba(245,183,0,0.14);
    border-radius: 99px;
    padding: 3px 10px;
    margin: 0 0 12px;
  }
  .card-sheen {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: linear-gradient(115deg, transparent 30%, rgba(255,255,255,0.55) 45%, rgba(255,230,140,0.25) 52%, transparent 65%);
    transform: translateX(-120%);
    transition: transform 0.7s ease;
  }
  @media (hover: hover) {
    .person-card:hover {
      transform: translateY(-6px) rotate(-0.6deg);
      box-shadow: 0 6px 14px rgba(23,26,31,0.08), 0 26px 46px rgba(23,26,31,0.16);
    }
    .person-card:hover .card-sheen { transform: translateX(120%); }
  }

  .person-links {
    margin-top: auto;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }
  .person-links a,
  .person-links button,
  .person-links .icon-off {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #f3f0e8;
    color: var(--ink-soft);
    transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
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
  .person-links a:active,
  .person-links button:active { transform: scale(0.9); }
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

  /* The deck's controls, shown on a phone only. */
  .deck-nav { display: none; }

  /* "Copied" - one word in a small pill, and nothing else. */
  .copy-toast {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 1000;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 16px 9px 10px;
    border-radius: 99px;
    background: var(--navy-panel);
    color: #fff;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.28);
    font-size: 13.5px;
    font-weight: 600;
    opacity: 0;
    transform: translateY(12px);
    pointer-events: none;
    transition: opacity 0.2s ease, transform 0.2s ease;
  }
  .copy-toast.show {
    opacity: 1;
    transform: none;
  }
  .copy-toast-ic {
    flex: none;
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: var(--gold);
    color: var(--ink);
  }
  .copy-toast-ic svg {
    width: 12px;
    height: 12px;
    fill: none;
    stroke: currentColor;
    stroke-width: 3;
    stroke-linecap: round;
    stroke-linejoin: round;
  }
  .copy-toast.failed .copy-toast-ic { background: #f87171; }
  @media (max-width: 600px) {
    .copy-toast {
      right: auto;
      left: 50%;
      bottom: calc(20px + env(safe-area-inset-bottom));
      transform: translate(-50%, 12px);
    }
    .copy-toast.show { transform: translate(-50%, 0); }
  }
  @media (prefers-reduced-motion: reduce) {
    .copy-toast, .person-card, .card-sheen, .card-inner { transition: none; }
  }

  /* ══════════════════════ TABLET (601–1000px) ══════════════════════ */
  @media (max-width: 1000px) {
    /* The card only centres in the viewport while it is shorter than it;
       stacked, the page flows top to bottom instead, as the login page does. */
    body { display: block; padding: 0; }
    .team-page { flex-direction: column; min-height: 100vh;min-height: 100dvh; border-radius: 0; max-width: 100%; }
    .story-panel { flex: none; padding: 34px clamp(24px, 6vw, 56px) 26px; }
    .story-mid { margin: 20px 0 28px; max-width: 620px; }
    h1.story-title { font-size: clamp(1.9rem, 5vw, 2.6rem); }
    .trend-motif { width: 46%; opacity: 0.25; }
    .people-panel { flex: 1; justify-content: flex-start; padding: 40px clamp(20px, 5vw, 48px) 56px; overflow-y: visible; }
    .people-grid { grid-template-columns: repeat(6, minmax(64px, 104px)); }
    .person-card { cursor: pointer; }
    .flip-hint {
      display: block;
      margin: -6px 0 0;
      font-size: 11.5px;
      color: var(--ink-soft);
      text-align: center;
    }
  }
  @media (max-width: 760px) and (min-width: 601px) {
    /* Two across, the odd one out centred under them. */
    .people-grid { grid-template-columns: repeat(2, minmax(170px, 230px)); }
    .person-card, .person-card:nth-child(4) { grid-column: auto; }
    .person-card:last-child:nth-child(odd) { grid-column: 1 / -1; justify-self: center; width: min(230px, 100%); }
  }

  /* ══════════════════════ PHONE (≤600px): the deck ══════════════════════
     The cards are dealt as a deck: the top one in full, the next two peeking
     out behind it. Swipe the top card either way and it flies off to the
     bottom of the deck, the way a pack is flicked through in Pokémon TCG
     Pocket - and after the last it comes round to the first again. */
  @media (max-width: 600px) {
    .story-panel { padding: 24px 20px 20px; }
    .story-mid { margin: 14px 0 18px; }
    .story-sub { font-size: 13.5px; }
    .people-panel { padding: 28px 16px calc(36px + env(safe-area-inset-bottom)); gap: 16px; overflow: hidden; }
    .people-grid {
      display: grid;
      grid-template-columns: min(272px, calc(100vw - 72px));
      grid-template-areas: "deck";
      gap: 0;
      padding-bottom: 30px;
      touch-action: pan-y;
    }
    .person-card,
    .person-card:nth-child(4),
    .person-card:last-child:nth-child(odd) {
      grid-area: deck;
      width: auto;
      --pos: 0;
      z-index: var(--z, 1);
      transform-origin: 50% 90%;
      transform:
        translateY(calc(min(var(--pos), 2) * 14px))
        scale(calc(1 - min(var(--pos), 2) * 0.05))
        rotate(calc(var(--tilt, 0) * 1deg));
      opacity: calc(1 - max(var(--pos) - 2, 0));
      transition: transform 0.32s cubic-bezier(.2,.8,.2,1), opacity 0.2s ease, box-shadow 0.2s ease;
      user-select: none;
      -webkit-user-select: none;
    }
    .person-card.is-top { cursor: grab; }
    /* The phone's deck hint already says "tap to flip", so the tablet's goes. */
    .flip-hint { display: none; }
    .person-card.is-dragging { transition: none; cursor: grabbing; }
    .person-card.is-still { transition: none; }
    .avatar { font-size: 40px; aspect-ratio: 1 / 1; }
    .person-name { font-size: 16px; }
    .story-bottom { flex-direction: column-reverse; align-items: flex-start; gap: 10px; }

    .deck-nav {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 14px;
    }
    .deck-btn {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      border: 1px solid var(--line);
      background: #fff;
      color: var(--ink);
      display: grid;
      place-items: center;
      cursor: pointer;
      box-shadow: 0 2px 6px rgba(23,26,31,0.06);
    }
    .deck-btn svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2.4; stroke-linecap: round; stroke-linejoin: round; }
    .deck-btn:active { transform: scale(0.92); }
    .deck-count {
      min-width: 56px;
      text-align: center;
      font-family: 'Geist Pixel', monospace;
      font-size: 14px;
      color: var(--ink);
      font-variant-numeric: tabular-nums;
    }
    .deck-hint {
      margin: -6px 0 0;
      font-size: 11.5px;
      color: var(--ink-soft);
      text-align: center;
    }
  }
  @media (max-width: 600px) and (prefers-reduced-motion: reduce) {
    .person-card, .person-card:nth-child(4) { transition: opacity 0.15s linear; }
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
    <div class="people-head">
      <h2>Meet the Team</h2>
      <p>{{ count($team) }} members of team Bulbasaur</p>
    </div>
    <p class="flip-hint">Tap a card to flip it over</p>

    <div class="people-grid" id="team-deck" aria-roledescription="deck of cards" aria-label="Team members">
      @foreach ($team as $member)
        <article class="person-card" data-card="{{ $loop->index }}" aria-label="{{ $member['name'] }}, {{ $member['role'] }}">
          <div class="card-inner">
          <div class="card-face">
            <div class="card-top">
              <span class="card-no">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}/{{ str_pad(count($team), 2, '0', STR_PAD_LEFT) }}</span>
              <span class="card-set">CapsTrack</span>
            </div>
            @php $photo = $member['image'] && file_exists(public_path($member['image'])) ? $member['image'] : null; @endphp
            <div class="avatar{{ $photo ? ' has-photo' : '' }}" aria-hidden="true">
              @if ($photo)
                <img src="{{ asset($photo) }}" alt="{{ $member['name'] }}" draggable="false">
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
            <span class="card-sheen" aria-hidden="true"></span>
          </div>
          <div class="card-back" aria-hidden="true">
            <span class="back-top">CapsTrack</span>
            <div class="back-emblem">
              <div class="back-seal"><img src="{{ asset('images/brand/cspc.png') }}" alt="" draggable="false"></div>
              <div class="back-ccs"><img src="{{ asset('images/brand/ccs.png') }}" alt="" draggable="false"></div>
            </div>
            <div class="back-foot">
              <b>College of Computer Studies</b>
              <span>Camarines Sur Polytechnic Colleges</span>
            </div>
          </div>
          </div>
        </article>
      @endforeach
    </div>

    {{-- The deck's own controls, on a phone: the same as a swipe, for anyone
         who would rather tap, or cannot swipe. --}}
    <div class="deck-nav" aria-label="Browse the team">
      <button type="button" class="deck-btn" id="deck-prev" aria-label="Previous member"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg></button>
      <span class="deck-count" id="deck-count" aria-live="polite">1 / {{ count($team) }}</span>
      <button type="button" class="deck-btn" id="deck-next" aria-label="Next member"><svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg></button>
    </div>
    <p class="deck-hint deck-nav">Swipe for the next card · tap it to flip it over</p>
  </div>
</div>

<div class="copy-toast" id="copy-toast" role="status" aria-live="polite">
  <span class="copy-toast-ic" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m5 13 4 4 10-10"/></svg></span>
  <span id="copy-toast-title">Copied</span>
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
        showToast(true);
      };
      var markFailed = function () { showToast(false); };

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(email).then(markCopied, function () { copyFromField(email, markCopied, markFailed); });
        return;
      }

      copyFromField(email, markCopied, markFailed);
    });
  });

  // "Copied", and nothing more: the address is already on the clipboard.
  var toast = document.getElementById('copy-toast');
  var toastTimer = null;
  function showToast(copied) {
    toast.classList.toggle('failed', !copied);
    document.getElementById('copy-toast-title').textContent = copied ? 'Copied' : "Couldn't copy";
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
      // Clipboard access can still be refused; the notice says so.
      failed();
    }

    document.body.removeChild(field);
  }

  // ── The deck, on a phone ──
  // The cards stack: order[0] is on top. A swipe either way flings the top
  // card off and it goes to the bottom, so the deck loops - after the last
  // member comes the first again. The buttons (and arrow keys) do the same.
  (function () {
    var deck = document.getElementById('team-deck');
    if (!deck) return;
    var cards = Array.prototype.slice.call(deck.querySelectorAll('.person-card'));
    var count = document.getElementById('deck-count');
    var phone = window.matchMedia('(max-width: 600px)');
    var small = window.matchMedia('(max-width: 1000px)');
    var still = window.matchMedia('(prefers-reduced-motion: reduce)');
    // A swipe ends in a click too; this keeps it from also flipping the card.
    var suppressClick = false;
    var order = cards.map(function (card, i) { return i; });
    var TILTS = [0, -3, 3];
    var busy = false;

    function lay() {
      order.forEach(function (index, pos) {
        var card = cards[index];
        card.style.setProperty('--pos', pos);
        card.style.setProperty('--z', String(cards.length - pos));
        card.style.setProperty('--tilt', String(pos < 3 ? TILTS[pos] : 0));
        card.classList.toggle('is-top', pos === 0);
        card.inert = pos !== 0;
        card.setAttribute('aria-hidden', pos === 0 ? 'false' : 'true');
      });
      count.textContent = (order[0] + 1) + ' / ' + cards.length;
    }

    function unlay() {
      cards.forEach(function (card) {
        ['--pos', '--z', '--tilt'].forEach(function (p) { card.style.removeProperty(p); });
        card.style.transform = '';
        card.classList.remove('is-top', 'is-dragging', 'is-still');
        card.inert = false;
        card.removeAttribute('aria-hidden');
      });
    }

    // Held still for a frame, so a card can be put somewhere without being
    // seen to travel there.
    function still1(card, then) {
      card.classList.add('is-still');
      then();
      void card.offsetWidth;
      card.classList.remove('is-still');
    }

    function next(direction) {
      if (busy) return;
      var card = cards[order[0]];
      var dir = direction || 1;
      if (still.matches) {
        card.classList.remove('is-flipped');
        order.push(order.shift());
        lay();
        return;
      }
      busy = true;
      card.classList.remove('is-dragging');
      card.style.transform = 'translateX(' + (dir * 130) + '%) rotate(' + (dir * 22) + 'deg)';
      setTimeout(function () {
        still1(card, function () {
          card.style.transform = '';
          // Back to the bottom of the deck face up, the way it was dealt.
          card.classList.remove('is-flipped');
          order.push(order.shift());
          lay();
        });
        busy = false;
      }, 280);
    }

    function previous() {
      if (busy) return;
      order.unshift(order.pop());
      var card = cards[order[0]];
      if (still.matches) { card.classList.remove('is-flipped'); lay(); return; }
      busy = true;
      still1(card, function () {
        card.classList.remove('is-flipped');
        lay();
        card.style.transform = 'translateX(-130%) rotate(-22deg)';
      });
      requestAnimationFrame(function () {
        card.style.transform = '';
        setTimeout(function () { busy = false; }, 300);
      });
    }

    // Dragging the top card: it follows the finger and leans with it, and
    // either flies off (far or fast enough) or springs back.
    var drag = null;
    deck.addEventListener('pointerdown', function (e) {
      if (!phone.matches || busy) return;
      var card = e.target.closest('.person-card.is-top');
      if (!card || e.target.closest('a, button')) return;
      drag = { card: card, id: e.pointerId, x: e.clientX, y: e.clientY, t: Date.now(), dx: 0, moving: false };
    });
    deck.addEventListener('pointermove', function (e) {
      if (!drag || e.pointerId !== drag.id) return;
      var dx = e.clientX - drag.x;
      var dy = e.clientY - drag.y;
      if (!drag.moving) {
        if (Math.abs(dx) < 8 || Math.abs(dx) < Math.abs(dy)) {
          if (Math.abs(dy) > 12) drag = null;
          return;
        }
        drag.moving = true;
        drag.card.classList.add('is-dragging');
        try { drag.card.setPointerCapture(e.pointerId); } catch (err) {}
      }
      drag.dx = dx;
      drag.card.style.transform = 'translateX(' + dx + 'px) rotate(' + (dx / 16) + 'deg)';
    });
    function release(e) {
      if (!drag || e.pointerId !== drag.id) return;
      var d = drag;
      drag = null;
      if (!d.moving) return;
      suppressClick = true;
      setTimeout(function () { suppressClick = false; }, 400);
      var speed = Math.abs(d.dx) / Math.max(1, Date.now() - d.t);
      if (Math.abs(d.dx) > 80 || speed > 0.6) {
        next(d.dx < 0 ? -1 : 1);
      } else {
        d.card.classList.remove('is-dragging');
        d.card.style.transform = '';
      }
    }
    deck.addEventListener('pointerup', release);
    deck.addEventListener('pointercancel', release);

    document.getElementById('deck-next').addEventListener('click', function () { next(-1); });
    document.getElementById('deck-prev').addEventListener('click', previous);
    // ── Flip, on tablet and phone ──
    // A tap anywhere on a card turns it over - except on its Facebook link or
    // copy-email button, which keep doing their own thing. On the phone only
    // the top card can be flipped; the ones under it are inert.
    function flip(card) {
      if (!card || card.inert || busy) return;
      card.classList.toggle('is-flipped');
    }
    deck.addEventListener('click', function (e) {
      if (!small.matches) return;
      if (suppressClick) { suppressClick = false; return; }
      if (e.target.closest('a, button')) return;
      flip(e.target.closest('.person-card'));
    });

    deck.addEventListener('keydown', function (e) {
      var flipKey = e.key === 'Enter' || e.key === ' ';
      if (flipKey && small.matches && !e.target.closest('a, button')) {
        // On the phone the deck holds focus, so it's the top card that turns.
        var card = phone.matches ? cards[order[0]] : e.target.closest('.person-card');
        if (card) { e.preventDefault(); flip(card); }
        return;
      }
      if (!phone.matches) return;
      if (e.key === 'ArrowRight') { e.preventDefault(); next(-1); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); previous(); }
    });

    function apply() {
      if (phone.matches) {
        deck.tabIndex = 0;
        lay();
      } else {
        deck.removeAttribute('tabindex');
        unlay();
      }
      // On the tablet grid each card takes focus of its own, so it can be
      // flipped from the keyboard; on desktop the cards don't flip at all.
      cards.forEach(function (card) {
        if (small.matches && !phone.matches) card.tabIndex = 0;
        else card.removeAttribute('tabindex');
        if (!small.matches) card.classList.remove('is-flipped');
      });
    }
    apply();
    [phone, small].forEach(function (mq) {
      if (mq.addEventListener) mq.addEventListener('change', apply);
      else mq.addListener(apply);
    });
  })();
</script>

</body>
</html>

