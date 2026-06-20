@extends('layouts.guest')

@section('title', 'Pending Approval - CapsTRACK')

@section('content')
<style>
  :root {
    --primary: #b5941f;
    --primary-dark: #9e811a;
    --navy: #0f1115;
    --navy-light: #17191e;
    --navy-mid: #1e2028;
    --border: rgba(255,255,255,0.06);
    --border2: rgba(255,255,255,0.12);
    --text: #f0f2f7;
    --text2: #9ba3b5;
    --text3: #5c6478;
    --green: #22c55e;
    --red: #ef4444;
    --blue: #3b82f6;
    --amber: #f59e0b;
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--navy);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 16px;
  }

  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image:
      linear-gradient(rgba(181,148,31,0.03) 1px, transparent 1px),
      linear-gradient(90deg, rgba(181,148,31,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
  }

  .page-wrap {
    width: 100%;
    max-width: 500px;
  }

  .card {
    background: var(--navy-light);
    border: 1px solid var(--border);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 8px 40px rgba(0,0,0,0.5);
    padding: 48px 28px;
    text-align: center;
  }

  .icon-wrapper {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: rgba(245,158,11,0.08);
    border: 2px solid rgba(245,158,11,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
  }

  .icon-wrapper svg {
    width: 28px;
    height: 28px;
    stroke: var(--amber);
    fill: none;
    stroke-width: 2.5;
  }

  .title {
    font-family: 'Space Mono', monospace;
    font-size: 18px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 10px;
  }

  .subtitle {
    font-size: 13px;
    color: var(--text2);
    line-height: 1.6;
    max-width: 360px;
    margin: 0 auto 24px;
  }

  .info-box {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 10px;
    background: rgba(59,130,246,0.08);
    border: 1px solid rgba(59,130,246,0.2);
    font-size: 12px;
    color: var(--blue);
    font-weight: 600;
    margin-bottom: 24px;
  }

  .info-box svg {
    width: 14px;
    height: 14px;
    stroke: currentColor;
    fill: none;
    stroke-width: 2;
  }

  .btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
  }

  .btn-primary {
    background: var(--primary);
    color: #0f1115;
  }

  .btn-primary:hover {
    background: var(--primary-dark);
  }
</style>

<div class="page-wrap">
  <div class="card">
    <div class="icon-wrapper">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    </div>
    <div class="title">Account Pending Approval</div>
    <div class="subtitle">Your account request is awaiting administrator review. You will be notified via email once your request is processed.</div>
    <div class="info-box">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Typical review time: 1–3 business days
    </div>
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="btn btn-primary">
        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Sign Out
      </button>
    </form>
  </div>
</div>
@endsection