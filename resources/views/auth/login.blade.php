<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Sign in — CapsTrack</title>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>
@vite(['resources/css/app.css'])
<style nonce="{{ Vite::cspNonce() }}">
  /* Everything below is what Tailwind cannot express: a self-hosted face, a
     background built from asset() URLs, and a drawn-line animation. The rest
     of this page is utility classes. */

  @font-face {
    font-family: 'Geist Pixel';
    font-style: normal;
    font-weight: 400;
    font-display: swap;
    src: url('{{ asset('fonts/geist-pixel-400.woff2') }}') format('woff2');
  }

  body {
    background-image:
      radial-gradient(circle at center, transparent 20%, #0a0a0a 100%),
      linear-gradient(rgba(0,0,0,0.55), rgba(0,0,0,0.55)),
      url('{{ asset('images/bg-ccs.jpg') }}');
    background-size: cover, cover, cover;
    background-position: center, center, center;
    background-repeat: no-repeat, no-repeat, no-repeat;
  }

  .brand-bg {
    background-image: url('{{ asset('images/bg-ccs.jpg') }}');
    background-size: cover;
    background-position: center;
  }

  ::selection { background: rgba(181,148,31,0.28); }

  /* The sign-in column scrolls on short desktop windows, but the scrollbar
     itself would cut across the cream panel. */
  .signin-panel { scrollbar-width: none; -ms-overflow-style: none; }
  .signin-panel::-webkit-scrollbar { display: none; }

  /* Ambient trend-line motif — echoes the logo's own gold upward arrow.
     Purely decorative, so it's aria-hidden and skipped for reduced motion. */
  .trend-motif path { vector-effect: non-scaling-stroke; }
  .trend-line {
    stroke-dasharray: 900;
    stroke-dashoffset: 900;
    animation: drawTrend 2.6s cubic-bezier(.16,.8,.24,1) 0.3s forwards;
  }
  @keyframes drawTrend { to { stroke-dashoffset: 0; } }
  @media (prefers-reduced-motion: reduce) {
    .trend-line { animation: none; stroke-dashoffset: 0; }
  }

  /* From tablet up the note is always open, so its summary is only a control
     on phones. A closed <details> hides its children whatever their display
     is, so the script at the foot of the page opens it to match. */
  @media (min-width: 768px) {
    #signin-note > summary { display: none; }
  }
  #signin-note[open] > summary .chevron { transform: rotate(180deg); }
</style>
</head>
<body class="m-0 flex min-h-dvh flex-col overflow-x-clip bg-navy-deep font-body text-ink antialiased lg:items-center lg:justify-center lg:p-7">

{{-- The centred "card". Stacked on phone and tablet, split from lg up. --}}
<div class="flex w-full flex-1 flex-col lg:max-h-[min(860px,calc(100vh-56px))] lg:max-w-[1320px] lg:flex-none lg:flex-row lg:overflow-hidden lg:rounded-3xl lg:shadow-[0_50px_100px_-20px_rgba(0,0,0,0.65),0_0_0_1px_rgba(255,255,255,0.04)]">

{{-- ═══════ BRAND PANEL — header on phone/tablet, left column on desktop ═══════ --}}
{{-- The whole page is meant to read at a glance, no scrolling to get there:
     this is a fixed-height crop, not a full screen of black waiting to be
     scrolled past. Its bottom edge tucks under the sheet's rounded corner on
     purpose - the subhead trailing off there is the visual cue that reads
     "sign-in continues below", not a bug to chase.

     justify-between here (mobile/tablet) used to spread just two items - the
     logo and the text block - across a much taller box, which shoved the
     text down to the very edge instead of keeping it near the logo.
     justify-start + a real gap keeps both close to the top, where a shorter
     crop actually shows them. Desktop keeps justify-between, because there
     it is genuinely spacing three bands (logo, copy, footer) across a much
     taller column. --}}
<div class="relative z-0 flex h-[42dvh] min-w-0 flex-none flex-col justify-start gap-6 overflow-hidden bg-navy px-5 pt-6 pb-10 text-white md:h-[36dvh] md:gap-8 md:px-10 md:pt-9 md:pb-12 lg:h-auto lg:flex-1 lg:basis-[56%] lg:justify-between lg:gap-0 lg:p-[clamp(32px,4.5vw,76px)]">
  <div class="brand-bg absolute inset-0 opacity-[0.16]" aria-hidden="true"></div>
  {{-- Darker overall than welcome.blade.php's hero vignette (which goes fully
       transparent at centre) — this panel should read dark throughout. --}}
  <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(0,0,0,0.7)_20%,#0a0a0a_100%)]" aria-hidden="true"></div>

  <svg class="trend-motif pointer-events-none absolute right-[-4%] bottom-[-6%] w-4/5 opacity-35 lg:w-[min(64%,620px)] lg:opacity-50" viewBox="0 0 420 260" aria-hidden="true" focusable="false">
    <defs>
      <linearGradient id="trendFade" x1="0" y1="1" x2="0" y2="0">
        <stop offset="0%" stop-color="#f5b700" stop-opacity="0"/>
        <stop offset="100%" stop-color="#f5b700" stop-opacity="0.12"/>
      </linearGradient>
    </defs>
    <path d="M0,240 L40,196 L92,214 L150,140 L214,168 L292,72 L360,104 L420,40 L420,260 L0,260 Z" fill="url(#trendFade)"/>
    <path class="trend-line" d="M0,240 L40,196 L92,214 L150,140 L214,168 L292,72 L360,104 L420,40" fill="none" stroke="#f5b700" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
    <circle cx="420" cy="40" r="4.5" fill="#ffd23f"/>
  </svg>

  <div class="relative z-[2]">
    <div class="flex items-center gap-3">
      <img src="{{ asset('images/brand/capstrack-logo-ui.svg') }}" alt="CapsTrack" class="block h-6 w-auto md:h-7 lg:h-[34px]"/>
    </div>
  </div>

  <div class="relative z-[2] max-w-[620px]">
    <p class="mt-0 flex items-center gap-2.5 text-[10px] tracking-[0.16em] md:text-[11px] text-gold-bright uppercase tabular-nums before:h-px before:w-[22px] before:bg-gold before:content-[''] md:mt-0 lg:mt-[clamp(32px,6vh,64px)]">CSPC · College of Computer Studies</p>
    <h1 class="my-2.5 font-display text-xl leading-[1.2] font-normal tracking-[-0.01em] text-balance text-white md:my-4 md:text-[1.9rem] lg:mt-4 lg:mb-5 lg:text-[clamp(2.3rem,3.2vw,3.6rem)] lg:leading-[1.08]">Centralized <em class="text-gold-bright not-italic">Capstone</em> Management</h1>
    <p class="m-0 max-w-[48ch] text-[13px] leading-relaxed text-white/70 md:text-[14px] lg:text-[17px] lg:leading-[1.65]">Form your team, apply to an expert of your choice, and track your capstone through to final defense.</p>

    {{-- Hidden on phones, where the panel is a header and the button is the
         point. Tablets get four; only desktop has room for all six. --}}
    <div class="mt-8 hidden grid-cols-2 gap-x-8 gap-y-6 md:grid lg:mt-[clamp(34px,5.5vh,60px)] lg:gap-x-[34px] lg:gap-y-7">
      <div class="flex items-start gap-3">
        <svg viewBox="0 0 24 24" class="mt-0.5 size-5 flex-none fill-none stroke-gold-bright stroke-[1.9] [stroke-linecap:round] [stroke-linejoin:round]"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <div>
          <p class="m-0 mb-1 text-[12.5px] font-bold tracking-[0.03em] text-white tabular-nums">Team Formation</p>
          <p class="m-0 text-[13px] leading-normal text-white/50">Recruit within your course and build your capstone team.</p>
        </div>
      </div>
      <div class="flex items-start gap-3">
        <svg viewBox="0 0 24 24" class="mt-0.5 size-5 flex-none fill-none stroke-gold-bright stroke-[1.9] [stroke-linecap:round] [stroke-linejoin:round]"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
        <div>
          <p class="m-0 mb-1 text-[12.5px] font-bold tracking-[0.03em] text-white tabular-nums">Expert Applications</p>
          <p class="m-0 text-[13px] leading-normal text-white/50">Apply to advisers, consultants, and grammarians.</p>
        </div>
      </div>
      <div class="flex items-start gap-3">
        <svg viewBox="0 0 24 24" class="mt-0.5 size-5 flex-none fill-none stroke-gold-bright stroke-[1.9] [stroke-linecap:round] [stroke-linejoin:round]"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        <div>
          <p class="m-0 mb-1 text-[12.5px] font-bold tracking-[0.03em] text-white tabular-nums">Document Review</p>
          <p class="m-0 text-[13px] leading-normal text-white/50">Submit manuscripts and get annotated feedback inline.</p>
        </div>
      </div>
      <div class="flex items-start gap-3">
        <svg viewBox="0 0 24 24" class="mt-0.5 size-5 flex-none fill-none stroke-gold-bright stroke-[1.9] [stroke-linecap:round] [stroke-linejoin:round]"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        <div>
          <p class="m-0 mb-1 text-[12.5px] font-bold tracking-[0.03em] text-white tabular-nums">Progress Tracking</p>
          <p class="m-0 text-[13px] leading-normal text-white/50">Chapter-based milestones with overdue alerts.</p>
        </div>
      </div>
      <div class="hidden items-start gap-3 lg:flex">
        <svg viewBox="0 0 24 24" class="mt-0.5 size-5 flex-none fill-none stroke-gold-bright stroke-[1.9] [stroke-linecap:round] [stroke-linejoin:round]"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        <div>
          <p class="m-0 mb-1 text-[12.5px] font-bold tracking-[0.03em] text-white tabular-nums">Duplication Checker</p>
          <p class="m-0 text-[13px] leading-normal text-white/50">AI similarity scoring against archived capstones.</p>
        </div>
      </div>
      <div class="hidden items-start gap-3 lg:flex">
        <svg viewBox="0 0 24 24" class="mt-0.5 size-5 flex-none fill-none stroke-gold-bright stroke-[1.9] [stroke-linecap:round] [stroke-linejoin:round]"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <div>
          <p class="m-0 mb-1 text-[12.5px] font-bold tracking-[0.03em] text-white tabular-nums">Consultation Scheduling</p>
          <p class="m-0 text-[13px] leading-normal text-white/50">Book adviser slots on an interactive calendar.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="relative z-[2] mt-7 hidden items-baseline justify-between gap-4 border-t border-white/10 pt-7 text-[11px] tracking-[0.04em] text-white/35 tabular-nums lg:flex">
    <span>© {{ date('Y') }} CSPC College of Computer Studies</span>
    <span class="text-white/50">CapsTrack</span>
  </div>
</div>

{{-- ═══════════════════════ SIGN-IN PANEL ═══════════════════════ --}}
{{-- While stacked this is a sheet pulled up over the header, not a second
     band below it - the rounded lip overlapping the dark panel is what says
     the page carries on upwards. Sized to its own content, not to the
     viewport, so the whole card - hero and sheet together - lands in one
     screen with nothing to scroll for. Flush again from lg up, where it is
     simply the right-hand column. --}}
<div class="signin-panel relative z-10 -mt-7 flex min-w-0 flex-1 justify-center rounded-t-[26px] bg-cream px-5 pt-7 pb-[calc(1.5rem+env(safe-area-inset-bottom))] shadow-[0_-12px_32px_rgba(0,0,0,0.28)] md:-mt-9 md:px-10 md:pt-9 md:pb-10 lg:mt-0 lg:basis-[44%] lg:items-center lg:overflow-y-auto lg:rounded-none lg:shadow-none lg:p-[clamp(24px,4.5vw,56px)]">
  {{-- The pill bar every bottom sheet carries - it is what tells a thumb
       this whole surface is the thing that just slid up, before a single
       word on it is read. Gone once the panel is simply a column, on lg. --}}
  <div class="absolute top-2.5 left-1/2 h-1 w-9 -translate-x-1/2 rounded-full bg-ink/15 lg:hidden" aria-hidden="true"></div>

  {{-- A column on every size, so the phone can reorder it: the note sits
       above the button and mt-auto drops the button to the bottom, where a
       thumb already is. From md up the two swap back. --}}
  <div class="flex w-full max-w-sm flex-col md:max-w-md lg:max-w-[420px] lg:flex-none">
    <div class="order-1 mb-5 text-sm font-bold tracking-[-0.01em] text-ink tabular-nums md:mb-8 md:text-base lg:mb-[clamp(16px,3vh,40px)]">Caps<span class="text-gold-dim">TRACK</span></div>

    <h2 class="order-2 m-0 mb-1.5 text-xl font-bold tracking-[-0.015em] text-ink md:mb-2 md:text-3xl">Sign in to CapsTrack</h2>
    <p class="order-3 m-0 mb-4 text-[13px] leading-normal text-ink-soft md:mb-6 md:text-[15px]">Use your CSPC Mail to continue.</p>

    @if (session('session_expired'))
      {{-- Signed out by the idle timer (EndIdleSession / partials/idle-logout). --}}
      <div class="order-4 mb-3.5 flex items-start gap-2.5 rounded-xl border border-amber-500/30 bg-amber-500/[0.08] px-4 py-3 text-[12.5px] leading-normal text-amber-800 md:text-[13px]" role="status">
        <svg class="mt-px h-4 w-4 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5M9 2h6"/></svg>
        <span><strong class="font-semibold">Session expired.</strong> You were signed out after {{ \App\Http\Middleware\EndIdleSession::limitForHumans() }} of inactivity. Sign in again to continue.</span>
      </div>
    @endif

    @if ($errors->any())
      <div class="order-4 mb-3.5 rounded-xl border border-red-600/20 bg-red-600/[0.06] px-4 py-3 text-[12.5px] leading-normal text-red-700 md:text-[13px]">
        <ul class="m-0 list-disc pl-[18px]">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    {{-- order-6 on a phone puts it directly under the note, a normal margin
         apart. Back to its old place directly under the sub-heading from md
         up. --}}
    <form id="google-login-form" method="POST" action="{{ route('login.google.initiate') }}" class="order-6 mt-4 md:order-5 md:mt-0">
      @csrf
      <input type="hidden" name="g-recaptcha-response" id="recaptcha-token">
      <div id="loginNotice" hidden class="mb-3"></div>
      <button type="submit" id="google-signin-btn" class="flex min-h-[50px] w-full cursor-pointer items-center justify-center gap-2.5 rounded-xl border border-[#d7d2c4] bg-white px-5 py-3 font-body text-[14.5px] font-semibold text-ink shadow-[0_1px_2px_rgba(23,26,31,0.04)] transition-[border-color,box-shadow,transform] duration-150 hover:border-[#b9b2a0] hover:shadow-[0_4px_14px_rgba(23,26,31,0.08)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold active:translate-y-px active:scale-[0.99] active:shadow-[0_1px_2px_rgba(23,26,31,0.04)] disabled:translate-y-0 disabled:scale-100 disabled:cursor-default disabled:opacity-60 md:min-h-[52px] md:text-[15px]">
        <svg viewBox="0 0 24 24" class="size-[18px] flex-none md:size-5"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
        Continue with CSPC Mail
      </button>
    </form>

    {{-- Three lines of housekeeping sitting directly under the button pushed
         the button itself up the screen on a phone. Folded away by default
         there; from tablet up there is room, so it stays open. --}}
    <details id="signin-note" class="order-5 mt-0 rounded-xl border border-line bg-sand md:order-6 md:mt-4" open>
      <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-[11px] font-bold tracking-[0.04em] text-gold-dim uppercase md:text-xs tabular-nums [&::-webkit-details-marker]:hidden">
        <span class="flex items-center gap-2">
          <svg viewBox="0 0 24 24" class="size-3.5 flex-none fill-none stroke-current stroke-2 [stroke-linecap:round] [stroke-linejoin:round]" aria-hidden="true"><circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16"/><circle cx="12" cy="7.75" r="0.75" fill="currentColor" stroke="none"/></svg>
          How sign-in works
        </span>
        <svg viewBox="0 0 24 24" class="chevron size-4 flex-none fill-none stroke-current stroke-2 transition-transform duration-200 [stroke-linecap:round] [stroke-linejoin:round]" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <ul class="m-0 flex list-none flex-col gap-2 px-4 pb-4 md:pt-4">
        <li class="relative pl-3.5 text-[12px] leading-[1.5] text-ink-soft md:text-[12.5px] before:absolute before:top-[7px] before:left-0 before:size-1 before:rounded-full before:bg-gold before:content-['']"><strong class="font-semibold text-ink">Students</strong> — sign in with your CSPC Mail <strong class="font-semibold text-ink">once your CaPSA/TSA has uploaded your section list</strong>.</li>
        <li class="relative pl-3.5 text-[12px] leading-[1.5] text-ink-soft md:text-[12.5px] before:absolute before:top-[7px] before:left-0 before:size-1 before:rounded-full before:bg-gold before:content-['']"><strong class="font-semibold text-ink">Experts</strong> — sign in with your CSPC Mail <strong class="font-semibold text-ink">once a CaPSA/TSA has uploaded the expert list</strong>.</li>
      </ul>
    </details>

    {{-- A plain rule, not an "or": there is no second way to sign in, and the
         line about asking a CaPSA/TSA for an account is gone with it. --}}
    <div class="order-7 my-3 h-px bg-line"></div>

    {{-- Privacy Policy always visible here: Google's OAuth review looks for it on the home page. --}}
    <p class="order-9 mt-4 text-center text-[10px] text-[#a8a293] md:text-[11px] lg:mt-[clamp(12px,2.5vh,32px)]">CapsTrack — CSPC College of Computer Studies
      <span class="mt-1 block">
        <a href="{{ route('team') }}" id="meet-team-btn" class="font-semibold text-gold-dim underline-offset-2 hover:underline">Meet the Team</a>
        <span class="mx-1.5 text-[#cfc9ba]">·</span>
        <a href="{{ route('privacy') }}" class="font-semibold text-gold-dim underline-offset-2 hover:underline">Privacy Policy</a>
      </span>
    </p>
  </div>
</div>
</div>

@if($removedExpert ?? false)
{{-- Keeps the modal-overlay class: partials/escape-closes-modals matches it. --}}
<div class="modal-overlay fixed inset-0 z-[3000] flex items-center justify-center bg-navy/75 p-5 backdrop-blur-md" id="expert-removed-modal">
    <div class="w-full max-w-[440px] rounded-2xl bg-white px-6 py-9 text-center shadow-[0_30px_60px_rgba(0,0,0,0.35)] sm:px-8 sm:py-10">
        <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-red-500/[0.08] text-red-500">
            <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </div>
        <h3 class="m-0 mb-1.5 text-[17px] font-bold tracking-[-0.3px] text-[#1e2128]">Account Removed</h3>
        <p class="m-0 mb-3 text-left text-[13px] leading-relaxed text-gray-500">
            Your expert account <strong>{{ $removedExpertName }}</strong> has been removed from the system.
            @if($removedExpertReason)
                <br><br>Reason: {{ $removedExpertReason }}
            @endif
        </p>
        <button id="expert-removed-ok-btn" type="button" class="mt-1 min-h-11 w-full cursor-pointer rounded-[10px] border-none bg-[#1e2128] px-4 py-3 text-[13px] font-bold text-white transition-colors duration-150 hover:bg-black">OK</button>
    </div>
</div>
@endif

{{-- Shown on the first open of this page each visit, but not straight after
     signing out (or being signed out by the idle timer). --}}
<div class="modal-overlay fixed inset-0 z-[2900] flex items-center justify-center bg-navy/75 p-5 backdrop-blur-md" id="privacy-modal" role="dialog" aria-modal="true" aria-labelledby="privacy-modal-title" hidden
     data-skip="{{ session('signed_out') || session('session_expired') ? '1' : '' }}">
    <div class="flex h-[min(660px,100%)] w-full max-w-[940px] overflow-hidden rounded-2xl border border-gold/25 bg-[#161616] shadow-[0_30px_60px_rgba(0,0,0,0.5)]">
        <aside class="hidden w-[220px] flex-none flex-col border-r border-white/10 bg-[#101010] px-3 py-6 md:flex">
            <img src="{{ asset('images/brand/capstrack-logo-ui.svg') }}" alt="CapsTrack" class="mx-3 mb-8 block h-7 w-auto self-start"/>
            <nav class="flex flex-col gap-1" aria-label="Privacy notice sections">
                <button type="button" data-pp-jump="pp-collect" class="flex min-h-10 w-full cursor-pointer items-center gap-2.5 rounded-lg border-none bg-transparent px-3 text-left text-[13px] font-medium text-[#b8b8b2] transition-colors duration-150 hover:bg-white/5 hover:text-white data-[active]:bg-gold/15 data-[active]:text-gold"><svg viewBox="0 0 24 24" class="size-4 flex-none fill-none stroke-current stroke-2 [stroke-linecap:round] [stroke-linejoin:round]" aria-hidden="true"><path d="M4 6c0-1.7 3.6-3 8-3s8 1.3 8 3-3.6 3-8 3-8-1.3-8-3z"/><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg>Information We Collect</button>
                <button type="button" data-pp-jump="pp-use" class="flex min-h-10 w-full cursor-pointer items-center gap-2.5 rounded-lg border-none bg-transparent px-3 text-left text-[13px] font-medium text-[#b8b8b2] transition-colors duration-150 hover:bg-white/5 hover:text-white data-[active]:bg-gold/15 data-[active]:text-gold"><svg viewBox="0 0 24 24" class="size-4 flex-none fill-none stroke-current stroke-2 [stroke-linecap:round] [stroke-linejoin:round]" aria-hidden="true"><path d="M4 20h16M7 16V9M12 16V5M17 16v-4"/></svg>How We Use It</button>
                <button type="button" data-pp-jump="pp-protect" class="flex min-h-10 w-full cursor-pointer items-center gap-2.5 rounded-lg border-none bg-transparent px-3 text-left text-[13px] font-medium text-[#b8b8b2] transition-colors duration-150 hover:bg-white/5 hover:text-white data-[active]:bg-gold/15 data-[active]:text-gold"><svg viewBox="0 0 24 24" class="size-4 flex-none fill-none stroke-current stroke-2 [stroke-linecap:round] [stroke-linejoin:round]" aria-hidden="true"><path d="M12 3l7 3v6c0 4.5-3 7.7-7 9-4-1.3-7-4.5-7-9V6l7-3z"/><path d="M9 12l2 2 4-4"/></svg>Data Protection</button>
                <button type="button" data-pp-jump="pp-rights" class="flex min-h-10 w-full cursor-pointer items-center gap-2.5 rounded-lg border-none bg-transparent px-3 text-left text-[13px] font-medium text-[#b8b8b2] transition-colors duration-150 hover:bg-white/5 hover:text-white data-[active]:bg-gold/15 data-[active]:text-gold"><svg viewBox="0 0 24 24" class="size-4 flex-none fill-none stroke-current stroke-2 [stroke-linecap:round] [stroke-linejoin:round]" aria-hidden="true"><path d="M12 3v18M5 7h14M5 7l-3 7a3 3 0 0 0 6 0zM19 7l-3 7a3 3 0 0 0 6 0z"/></svg>Your Rights</button>
                <button type="button" data-pp-jump="pp-contact" class="flex min-h-10 w-full cursor-pointer items-center gap-2.5 rounded-lg border-none bg-transparent px-3 text-left text-[13px] font-medium text-[#b8b8b2] transition-colors duration-150 hover:bg-white/5 hover:text-white data-[active]:bg-gold/15 data-[active]:text-gold"><svg viewBox="0 0 24 24" class="size-4 flex-none fill-none stroke-current stroke-2 [stroke-linecap:round] [stroke-linejoin:round]" aria-hidden="true"><path d="M4 5h16v12H8l-4 3z"/></svg>Contact</button>
            </nav>
        </aside>
        <div class="flex min-w-0 flex-1 flex-col">
            <div class="flex items-center gap-4 border-b border-gold/40 px-5 py-5 sm:px-7">
                <span class="flex size-12 flex-none items-center justify-center rounded-xl bg-gold/15 text-gold"><svg viewBox="0 0 24 24" class="size-7 fill-none stroke-current stroke-2 [stroke-linecap:round] [stroke-linejoin:round]" aria-hidden="true"><path d="M12 3l7 3v6c0 4.5-3 7.7-7 9-4-1.3-7-4.5-7-9V6l7-3z"/><rect x="9" y="11" width="6" height="5" rx="1"/><path d="M10 11V9.5a2 2 0 0 1 4 0V11"/></svg></span>
                <div>
                    <h3 id="privacy-modal-title" class="m-0 text-[22px] font-bold text-white">Privacy Notice</h3>
                    <p class="m-0 mt-0.5 text-[13px] text-[#9a9a94]">How CapsTrack handles your information</p>
                </div>
            </div>
            <div class="relative flex-1 space-y-5 overflow-y-auto px-5 py-5 sm:px-7" id="privacy-modal-body">
                <p class="mt-0 text-[13px] leading-relaxed text-[#b8b8b2]"><strong class="text-white">Welcome to CapsTrack.</strong> CapsTrack is the capstone tracking system of the CSPC College of Computer Studies. Before you sign in, here is what it collects, why, and your rights under the Data Privacy Act of 2012 (RA 10173).</p>
                <section id="pp-collect" class="flex gap-3">
                    <span class="flex size-6 flex-none items-center justify-center rounded-full bg-gold text-[12px] font-bold text-navy">1</span>
                    <div class="min-w-0 text-[13px] leading-relaxed text-[#b8b8b2]">
                        <h4 class="m-0 mb-1 text-[14px] font-bold text-white">Information We Collect</h4>
                        We collect only what the capstone process needs:<ul class="m-0 mt-1.5 list-disc space-y-1 pl-5"><li>Your CSPC Google account: name, email address and profile picture.</li><li>School records: student number, course, section, year level and school year.</li><li>Capstone work: team details, titles, documents, comments, schedules, grades and evaluations.</li><li>Consultation bookings and the payment receipts you submit.</li><li>Sign-in and activity records, kept for security.</li></ul>
                    </div>
                </section>
                <section id="pp-use" class="flex gap-3">
                    <span class="flex size-6 flex-none items-center justify-center rounded-full bg-gold text-[12px] font-bold text-navy">2</span>
                    <div class="min-w-0 text-[13px] leading-relaxed text-[#b8b8b2]">
                        <h4 class="m-0 mb-1 text-[14px] font-bold text-white">How We Use It</h4>
                        Your information is used only to run CapsTrack:<ul class="m-0 mt-1.5 list-disc space-y-1 pl-5"><li>Signing you in and tracking your capstone progress.</li><li>Checking proposed titles for duplicates.</li><li>Scheduling consultations and defenses, and recording grades and payments.</li><li>Sending notices about your project by email or browser.</li></ul><p class="m-0 mt-1.5">It is never sold, used for advertising or shared outside the college&#039;s capstone program.</p>
                    </div>
                </section>
                <section id="pp-see" class="flex gap-3">
                    <span class="flex size-6 flex-none items-center justify-center rounded-full bg-gold text-[12px] font-bold text-navy">3</span>
                    <div class="min-w-0 text-[13px] leading-relaxed text-[#b8b8b2]">
                        <h4 class="m-0 mb-1 text-[14px] font-bold text-white">Who Can See It</h4>
                        Students see their own team&#039;s records. Advisers and panel members see the teams assigned to them, and the CaPSA/TSA sees the sections they handle.
                    </div>
                </section>
                <section id="pp-protect" class="flex gap-3">
                    <span class="flex size-6 flex-none items-center justify-center rounded-full bg-gold text-[12px] font-bold text-navy">4</span>
                    <div class="min-w-0 text-[13px] leading-relaxed text-[#b8b8b2]">
                        <h4 class="m-0 mb-1 text-[14px] font-bold text-white">Data Protection</h4>
                        Records and files are kept on servers run for the college, and title checks use an AI model hosted by the school. Only CSPC accounts approved by the college can sign in, and idle sessions end on their own.
                    </div>
                </section>
                <section id="pp-rights" class="flex gap-3">
                    <span class="flex size-6 flex-none items-center justify-center rounded-full bg-gold text-[12px] font-bold text-navy">5</span>
                    <div class="min-w-0 text-[13px] leading-relaxed text-[#b8b8b2]">
                        <h4 class="m-0 mb-1 text-[14px] font-bold text-white">Your Rights</h4>
                        Under the Data Privacy Act of 2012 you may:<ul class="m-0 mt-1.5 list-disc space-y-1 pl-5"><li>Be informed about and access your personal information.</li><li>Have errors corrected, or object to its processing.</li><li>Have it erased or blocked.</li><li>File a complaint with the National Privacy Commission.</li></ul>
                    </div>
                </section>
                <section id="pp-google" class="flex gap-3">
                    <span class="flex size-6 flex-none items-center justify-center rounded-full bg-gold text-[12px] font-bold text-navy">6</span>
                    <div class="min-w-0 text-[13px] leading-relaxed text-[#b8b8b2]">
                        <h4 class="m-0 mb-1 text-[14px] font-bold text-white">Google Services</h4>
                        Advisers, panel members and CaPSA/TSA may connect Google Calendar and Drive, limited to the events and files CapsTrack creates. Students are never asked for this access.
                    </div>
                </section>
                <section id="pp-contact" class="flex gap-3">
                    <span class="flex size-6 flex-none items-center justify-center rounded-full bg-gold text-[12px] font-bold text-navy">7</span>
                    <div class="min-w-0 text-[13px] leading-relaxed text-[#b8b8b2]">
                        <h4 class="m-0 mb-1 text-[14px] font-bold text-white">Contact</h4>
                        To see, correct or delete your CapsTrack records, contact your CaPSA/TSA or the CSPC Data Protection Officer.
                    </div>
                </section>
            </div>
            <div class="flex flex-wrap items-center gap-3 border-t border-white/10 px-5 py-3 sm:px-7">
                <a href="{{ route('privacy') }}" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-gold/50 px-4 text-[13px] font-semibold text-gold no-underline transition-colors duration-150 hover:bg-gold/10"><svg viewBox="0 0 24 24" class="size-4 fill-none stroke-current stroke-2 [stroke-linecap:round] [stroke-linejoin:round]" aria-hidden="true"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h6"/></svg>Full policy</a>
                <span class="hidden flex-1 text-center text-[12px] text-[#9a9a94] sm:block">Last updated: <span class="font-semibold text-gold">November 5, 2026</span></span>
                <button id="privacy-ok-btn" type="button" data-close class="ml-auto inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border-none bg-gold px-5 text-[13px] font-bold text-navy transition-colors duration-150 hover:bg-gold-bright"><svg viewBox="0 0 24 24" class="size-4 fill-none stroke-current stroke-2 [stroke-linecap:round] [stroke-linejoin:round]" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>I Understand</button>
            </div>
        </div>
    </div>
</div>

<script nonce="{{ Vite::cspNonce() }}">
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
                    formNotice('loginNotice', {
                        tone: 'error',
                        title: 'Could not start Google sign-in',
                        body: data.message || "Try again. If it keeps happening, check you are using the CSPC address on the department's list \u2014 CapsTrack accounts are created by the CaPSA/TSA.",
                    });
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            }).catch(function(err) {
                console.error(err);
                formNotice('loginNotice', {
                    tone: 'error',
                    title: 'CapsTrack could not be reached',
                    body: 'Nothing was sent. Check your connection and try again.',
                });
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });
    });
});

// The note ships open by default at every width. From tablet up its summary
// is hidden by media query, so there's no control left to reopen it if a
// resize (e.g. a phone rotating past the breakpoint) ever left it closed -
// this just re-forces it open once there's no toggle to do that by hand. On
// phones, where the summary stays visible, a visitor's own toggle is left
// alone.
(function () {
    const note = document.getElementById('signin-note');
    if (!note) { return; }
    const wide = window.matchMedia('(min-width: 768px)');
    const forceOpenWhenWide = function () { if (wide.matches) { note.open = true; } };
    forceOpenWhenWide();
    wide.addEventListener('change', forceOpenWhenWide);
})();

(function () {
    const modal = document.getElementById('privacy-modal');
    const KEY = 'privacy-notice-seen';
    const seen = function () { try { return sessionStorage.getItem(KEY) === '1'; } catch (e) { return false; } };
    const markSeen = function () { try { sessionStorage.setItem(KEY, '1'); } catch (e) {} };

    if (modal.dataset.skip) { markSeen(); return; }
    if (seen()) { return; }

    modal.hidden = false;
    // The side list jumps the notice to its section (the body is relative, so offsetTop is inside it),
    // and lights up the section being read as the notice scrolls.
    const body = document.getElementById('privacy-modal-body');
    const links = Array.from(modal.querySelectorAll('[data-pp-jump]'));
    const target = function (link) { return document.getElementById(link.dataset.ppJump); };
    // A clicked item stays lit until the reader scrolls by hand - the last sections are too
    // short to reach the top, so the scroll position alone would light Contact instead.
    let picked = null;
    ['wheel', 'touchstart', 'keydown'].forEach(function (type) {
        body.addEventListener(type, function () { picked = null; }, { passive: true });
    });
    const highlight = function () {
        let current = picked || links[0];
        if (!picked) {
            links.forEach(function (link) {
                if (target(link).offsetTop - 30 <= body.scrollTop) { current = link; }
            });
            if (body.scrollTop + body.clientHeight >= body.scrollHeight - 2) { current = links[links.length - 1]; }
        }
        links.forEach(function (l) { l.toggleAttribute('data-active', l === current); });
    };
    links.forEach(function (link) {
        link.addEventListener('click', function () {
            picked = link;
            highlight();
            body.scrollTo({ top: target(link).offsetTop - 20, behavior: 'smooth' });
        });
    });
    body.addEventListener('scroll', highlight, { passive: true });
    highlight();
    document.getElementById('privacy-ok-btn').addEventListener('click', function () {
        markSeen();
        modal.hidden = true;
    });
})();

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
@include('partials.form-notice')
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>
