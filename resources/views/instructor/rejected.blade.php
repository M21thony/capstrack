@extends('layouts.guest')

@section('title', 'Request Rejected - CapsTRACK')

@section('content')
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg: #0f1117;
  --surface: #16181f;
  --surface2: #1e2029;
  --border: rgba(255,255,255,0.07);
  --border2: rgba(255,255,255,0.12);
  --text1: #f0f2f7;
  --text2: #9ba3b5;
  --text3: #5c6478;
  --red: #ef4444;
  --red-bg: rgba(239,68,68,0.08);
  --radius: 20px;
  --shadow: 0 4px 24px rgba(0,0,0,0.4);
}

body {
  font-family: 'DM Sans', sans-serif;
  background: var(--bg);
  color: var(--text1);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 16px;
  position: relative;
  overflow: hidden;
}

/* Background subtle grid */
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background-image:
    linear-gradient(rgba(239,68,68,0.02) 1px, transparent 1px),
    linear-gradient(90deg, rgba(239,68,68,0.02) 1px, transparent 1px);
  background-size: 40px 40px;
  pointer-events: none;
  z-index: 0;
}

body::after {
  content: '';
  position: fixed;
  top: -200px;
  right: -200px;
  width: 600px;
  height: 600px;
  background: radial-gradient(circle, rgba(239,68,68,0.04) 0%, transparent 70%);
  pointer-events: none;
  z-index: 0;
}

.page-wrap {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 500px;
  text-align: center;
}

.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 40px 32px;
  box-shadow: var(--shadow);
  animation: fadeUp 0.5s ease both;
}

.rejected-icon {
  width: 72px; height: 72px;
  border-radius: 50%;
  background: var(--red-bg);
  border: 2px solid rgba(239,68,68,0.25);
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 24px;
}

.rejected-icon svg {
  width: 32px; height: 32px;
  stroke: var(--red);
  fill: none;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

h1 {
  font-size: 22px;
  font-weight: 600;
  color: var(--text1);
  margin-bottom: 12px;
}

p {
  font-size: 14px;
  color: var(--text2);
  line-height: 1.6;
  margin-bottom: 28px;
}

.reason-box {
  background: rgba(239, 68, 68, 0.04);
  border: 1px solid rgba(239, 68, 68, 0.15);
  border-radius: 12px;
  padding: 16px;
  text-align: left;
  margin-bottom: 32px;
}

.reason-title {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  color: var(--red);
  letter-spacing: 0.5px;
  margin-bottom: 6px;
}

.reason-text {
  font-size: 13.5px;
  color: var(--text1);
  line-height: 1.5;
}

.btn-logout {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: transparent;
  color: var(--text2);
  border: 1px solid var(--border2);
  padding: 10px 24px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
}

.btn-logout:hover {
  background: var(--surface2);
  color: var(--text1);
  border-color: var(--text2);
}

.btn-logout svg {
  width: 14px; height: 14px;
  stroke: currentColor;
  fill: none;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(16px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="page-wrap">
  <div class="card">
    <div class="rejected-icon">
      <svg viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10"/>
        <line x1="15" y1="9" x2="9" y2="15"/>
        <line x1="9" y1="9" x2="15" y2="15"/>
      </svg>
    </div>
    @php
      $rejAttempts = (int) ($accountRequest?->reject_attempts ?? 0);
      $maxRejects = 3;
      $rejCooldownUntil = $accountRequest?->reject_cooldown_until;
      $rejCooldownActive = $rejCooldownUntil && now()->lt($rejCooldownUntil);
      $rejWaitSeconds = $rejCooldownActive ? now()->diffInSeconds($rejCooldownUntil) : null;
      $rejRemaining = max(0, $maxRejects - $rejAttempts);
    @endphp

    <h1>Account Request Rejected</h1>
    <p>We regret to inform you that your request for an instructor account on CapsTRACK has been rejected by the administrator.</p>

    <div style="display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:8px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);font-size:12px;color:#f59e0b;font-weight:600;margin-bottom:16px">
      @if($rejAttempts >= $maxRejects)
        @if($rejCooldownActive)
          Attempt {{ $rejAttempts }}/{{ $maxRejects }} — Cooldown: {{ $rejWaitSeconds }}s remaining
        @else
          Attempt {{ $rejAttempts }}/{{ $maxRejects }} — Cooldown expired, you may retry
        @endif
      @else
        Attempt {{ $rejAttempts }}/{{ $maxRejects }} — {{ $rejRemaining }} attempt{{ $rejRemaining !== 1 ? 's' : '' }} remaining
      @endif
    </div>

    @if($accountRequest && $accountRequest->rejection_reason)
    <div class="reason-box">
      <div class="reason-title">Reason for Rejection</div>
      <div class="reason-text">"{{ $accountRequest->rejection_reason }}"</div>
    </div>
    @else
    <div class="reason-box">
      <div class="reason-title">Reason for Rejection</div>
      <div class="reason-text">No rejection reason was provided by the administrator. Please contact CCS department for more details.</div>
    </div>
    @endif

    {{-- "Request Account Again" is gone with the form it pointed at (see the
         commented routes in routes/web.php). A CaPSA adds faculty now, so
         there is nothing to resubmit - only somebody to ask. --}}
    <div style="margin-top:24px;font-size:13px;color:var(--text3)">
      Ask a CaPSA to add you to the expert list if this was a mistake.
    </div>
  </div>
  @include('partials.system-credits')
</div>
@endsection
