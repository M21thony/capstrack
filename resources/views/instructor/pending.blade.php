@extends('layouts.guest')

@section('title', 'Pending Approval - CapsTRACK')

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
  --blue: #3b82f6;
  --blue-dim: rgba(59,130,246,0.12);
  --amber: #f59e0b;
  --amber-bg: rgba(245,158,11,0.08);
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
    linear-gradient(rgba(245,158,11,0.02) 1px, transparent 1px),
    linear-gradient(90deg, rgba(245,158,11,0.02) 1px, transparent 1px);
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
  background: radial-gradient(circle, rgba(245,158,11,0.04) 0%, transparent 70%);
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

.pending-icon {
  width: 72px; height: 72px;
  border-radius: 50%;
  background: var(--amber-bg);
  border: 2px solid rgba(245,158,11,0.25);
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 24px;
  position: relative;
}

.pending-icon svg {
  width: 32px; height: 32px;
  stroke: var(--amber);
  fill: none;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.pending-icon::after {
  content: '';
  position: absolute;
  inset: -6px;
  border-radius: 50%;
  border: 2px solid var(--amber);
  opacity: 0.4;
  animation: pulse 2s cubic-bezier(0.24, 0, 0.38, 1) infinite;
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

.info-box {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 16px;
  text-align: left;
  margin-bottom: 32px;
}

.info-item {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  margin-bottom: 10px;
}

.info-item:last-child {
  margin-bottom: 0;
}

.info-key {
  color: var(--text3);
  font-weight: 500;
}

.info-val {
  color: var(--text1);
  font-weight: 600;
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

@keyframes pulse {
  0% { transform: scale(1); opacity: 0.5; }
  50% { transform: scale(1.15); opacity: 0; }
  100% { transform: scale(1); opacity: 0.5; }
}
</style>

<div class="page-wrap">
  <div class="card">
    <div class="pending-icon">
      <svg viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10"/>
        <polyline points="12 6 12 12 16 14"/>
      </svg>
    </div>
    <h1>Account Pending Approval</h1>
    <p>Your CapsTrack instructor registration is currently under review by the System Administrator. Once approved, you will be notified via email and will gain access to your dashboard.</p>

    <div class="info-box">
      <div class="info-item">
        <span class="info-key">Name</span>
        <span class="info-val">{{ Auth::guard('instructor')->user()->first_name }} {{ Auth::guard('instructor')->user()->last_name }}</span>
      </div>
      <div class="info-item">
        <span class="info-key">Email</span>
        <span class="info-val">{{ Auth::guard('instructor')->user()->email }}</span>
      </div>
      <div class="info-item">
        <span class="info-key">Specialization</span>
        <span class="info-val">{{ ucfirst(Auth::guard('instructor')->user()->specialization) }}</span>
      </div>
      <div class="info-item">
        <span class="info-key">Status</span>
        <span class="info-val" style="color:var(--amber);">Pending Review</span>
      </div>
    </div>

    <form action="{{ route('logout') }}" method="POST">
      @csrf
      <button type="submit" class="btn-logout">
        <svg viewBox="0 0 24 24">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        Sign Out
      </button>
    </form>
  </div>
</div>
@endsection
