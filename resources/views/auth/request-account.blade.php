@extends('layouts.guest')

@section('title', 'Account Request - CapsTRACK')

@section('content')
@php
  $rejectAttempts = (int) ($accountRequest?->reject_attempts ?? 0);
  $maxRejects = 3;
  $cooldownUntil = $accountRequest?->reject_cooldown_until;
  $cooldownActive = $cooldownUntil && now()->lt($cooldownUntil);
  $waitSeconds = $cooldownActive ? now()->diffInSeconds($cooldownUntil) : null;
  $attemptsRemaining = max(0, $maxRejects - $rejectAttempts);
@endphp
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
  }

  [data-theme="light"] {
    --navy: #eef1f6;
    --navy-light: #f8f9fc;
    --navy-mid: #ffffff;
    --border: rgba(0,0,0,0.08);
    --border2: rgba(0,0,0,0.18);
    --text: #000000;
    --text2: #1f2937;
    --text3: #6b7280;
  }
  [data-theme="light"] input[type="text"],
  [data-theme="light"] input[type="email"],
  [data-theme="light"] input[type="number"],
  [data-theme="light"] select,
  [data-theme="light"] textarea {
    background: #ffffff;
    border-color: rgba(0,0,0,0.18);
    color: #000000;
  }
  [data-theme="light"] input::placeholder,
  [data-theme="light"] textarea::placeholder { color: #6b7280; }
  [data-theme="light"] .section-label { color: #000000; }
  [data-theme="light"] .field-hint { color: #000000; }
  [data-theme="light"] .card-footer { background: #f9fafb; }
  [data-theme="light"] select {
    appearance: auto;
    background-image: none;
    padding-right: 14px;
  }
  [data-theme="light"] .tag-input-wrapper {
    background: #ffffff;
    border-color: rgba(0,0,0,0.18);
  }
  [data-theme="light"] .tag-input-wrapper .tag { background: #eef0f5; color: #000000; }
  [data-theme="light"] .card { box-shadow: 0 4px 24px rgba(0,0,0,0.06); }
  [data-theme="light"] .info-note { background: #ffffff; border-color: rgba(0,0,0,0.08); color: #000000; }
  [data-theme="light"] .info-note svg { stroke: #000000; }
  [data-theme="light"] .success-info { background: transparent; border-color: var(--border); color: var(--text2); }
  [data-theme="light"] .review-key { color: #374151; }
  [data-theme="light"] .review-value { color: #1f2937; }
  [data-theme="light"] .review-tag { background: #dbeafe; border-color: #93c5fd; color: #1d4ed8; }
  [data-theme="light"] .btn-primary { background: #3b82f6; color: #ffffff; }
  [data-theme="light"] .btn-primary:hover { background: #2563eb; box-shadow: 0 4px 20px rgba(59,130,246,0.3); }
  [data-theme="light"] .signin-link { color: #374151; }
  [data-theme="light"] .signin-link:hover { color: #111827; }
  [data-theme="light"] .tag-opt.selected { background: #e5e7eb; border-color: #9ca3af; color: #111827; }
  [data-theme="light"] .tag-opt:hover { border-color: #9ca3af; color: #111827; }
  [data-theme="light"] input:focus,
  [data-theme="light"] select:focus,
  [data-theme="light"] textarea:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
  [data-theme="light"] select option { background: #ffffff; color: #000000; }

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
    max-width: 660px;
  }

  .card {
    background: var(--navy-light);
    border: 1px solid var(--border);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 8px 40px rgba(0,0,0,0.5);
  }

  .card-hero {
    padding: 24px 28px 20px;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(135deg, rgba(181,148,31,0.04) 0%, rgba(59,130,246,0.03) 100%);
  }

  .card-hero-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 14px;
  }

  .step-dots {
    display: flex;
    gap: 8px;
  }

  .step-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--border2);
    transition: all 0.3s ease;
  }

  .step-dot.active {
    background: var(--primary);
    width: 22px;
    border-radius: 4px;
  }

  .step-dot.done {
    background: var(--green);
  }

  .signin-link {
    font-size: 12px;
    color: var(--primary);
    text-decoration: none;
    white-space: nowrap;
    font-weight: 500;
  }

  .theme-toggle {
    position: fixed; top: 20px; right: 20px; z-index: 999;
    display: flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 50%;
    background: var(--navy-light); border: 1px solid var(--border);
    color: var(--text2); cursor: pointer;
    transition: 0.2s; box-shadow: 0 2px 12px rgba(0,0,0,0.15);
  }
  .theme-toggle:hover { border-color: var(--primary); color: var(--primary); box-shadow: 0 4px 16px rgba(0,0,0,0.2); }
  .theme-toggle svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2; }

  .signin-link:hover {
    color: #d4b23a;
    text-decoration: underline;
  }

  .card-hero h1 {
    font-variant-numeric:tabular-nums;
    font-size: 18px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 4px;
    letter-spacing: -0.3px;
  }

  .card-hero p {
    font-size: 13px;
    color: var(--text2);
    line-height: 1.5;
  }

  .card-body {
    padding: 24px 28px;
  }

  .form-section {
    display: none;
    animation: fadeUp 0.3s ease both;
  }

  .form-section.active {
    display: block;
  }

  .section-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--text3);
    margin-bottom: 16px;
  }

  .section-label svg {
    width: 14px;
    height: 14px;
    stroke: var(--primary);
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
  }

  .field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin-bottom: 14px;
  }

  .field-row.full {
    grid-template-columns: 1fr;
  }

  .field {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  label {
    font-size: 12.5px;
    font-weight: 500;
    color: var(--text2);
  }

  .req {
    color: var(--primary);
  }

  input[type="text"],
  input[type="email"],
  input[type="number"],
  select,
  textarea {
    background: var(--navy-mid);
    border: 1px solid var(--border2);
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 13.5px;
    font-family: 'DM Sans', sans-serif;
    color: var(--text);
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    width: 100%;
  }

  input::placeholder,
  textarea::placeholder {
    color: var(--text3);
  }

  input:focus,
  select:focus,
  textarea:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(181,148,31,0.12);
  }

  input.error,
  select.error,
  textarea.error {
    border-color: var(--red);
    box-shadow: 0 0 0 3px rgba(239,68,68,0.1);
  }

  select {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%239ba3b5' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 36px;
  }

  select option {
    background: var(--navy-mid);
    color: var(--text);
  }

  textarea {
    resize: vertical;
    min-height: 80px;
  }

  .field-hint {
    font-size: 11px;
    color: var(--text3);
    margin-top: 2px;
  }

  .field-error {
    font-size: 11px;
    color: var(--red);
    margin-top: 3px;
    display: none;
    font-weight: 500;
  }

  .field-error.show {
    display: block;
  }

  .tags-picker {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 10px;
  }

  .tag-opt {
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    border: 1px solid var(--border2);
    background: var(--navy-mid);
    color: var(--text2);
    cursor: pointer;
    transition: all 0.2s;
    user-select: none;
  }

  .tag-opt:hover {
    border-color: var(--primary);
    color: #d4b23a;
  }

  .tag-opt.selected {
    background: rgba(181,148,31,0.12);
    border-color: var(--primary);
    color: var(--primary);
  }

  .divider {
    height: 1px;
    background: var(--border);
    margin: 20px 0;
  }

  .review-grid {
    display: flex;
    flex-direction: column;
    gap: 0;
  }

  .review-item {
    display: flex;
    align-items: baseline;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
  }

  .review-item:last-child {
    border-bottom: none;
  }

  .review-key {
    font-size: 12px;
    color: var(--text3);
    font-weight: 600;
    min-width: 120px;
    flex-shrink: 0;
  }

  .review-val {
    font-size: 13.5px;
    color: var(--text);
    font-weight: 500;
  }

  .review-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }

  .review-tag {
    padding: 2px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    background: rgba(181,148,31,0.12);
    color: var(--primary);
    border: 1px solid rgba(181,148,31,0.25);
  }

  .card-footer {
    padding: 18px 28px;
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    background: rgba(255,255,255,0.01);
  }

  .footer-left {
    display: flex;
    align-items: center;
    gap: 10px;
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

  .btn svg {
    width: 14px;
    height: 14px;
    stroke: currentColor;
    fill: none;
    stroke-width: 2.5;
    stroke-linecap: round;
    stroke-linejoin: round;
  }

  .btn-ghost {
    background: transparent;
    color: var(--text2);
    border: 1px solid var(--border2);
  }

  .btn-ghost:hover {
    background: var(--navy-mid);
    color: var(--text);
  }

  .btn-primary {
    background: var(--primary);
    color: #0f1115;
    font-weight: 700;
  }

  .btn-primary:hover {
    background: var(--primary-dark);
    box-shadow: 0 4px 20px rgba(181,148,31,0.3);
  }

  .btn-success {
    background: var(--green);
    color: #fff;
    font-weight: 700;
  }

  .btn-success:hover {
    background: #16a34a;
  }

  .btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
  }

  #step-counter {
    font-size: 12px;
    color: var(--text3);
    font-weight: 500;
  }

  .info-note {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 10px;
    background: rgba(59,130,246,0.08);
    border: 1px solid rgba(59,130,246,0.2);
    font-size: 12px;
    color: #93c5fd;
    line-height: 1.5;
    margin-bottom: 18px;
  }

  .info-note svg {
    width: 15px;
    height: 15px;
    stroke: var(--blue);
    fill: none;
    stroke-width: 2;
    flex-shrink: 0;
    margin-top: 1px;
  }

  .success-wrap {
    padding: 48px 28px;
    text-align: center;
    display: none;
  }

  .success-wrap.show {
    display: block;
  }

  .success-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: rgba(34,197,94,0.08);
    border: 2px solid rgba(34,197,94,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
  }

  .success-icon svg {
    width: 28px;
    height: 28px;
    stroke: var(--green);
    fill: none;
    stroke-width: 2.5;
  }

  .success-title {
    font-variant-numeric:tabular-nums;
    font-size: 18px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 10px;
  }

  .success-sub {
    font-size: 13px;
    color: var(--text2);
    line-height: 1.6;
    max-width: 360px;
    margin: 0 auto 24px;
  }

  .success-info {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 10px;
    background: var(--navy-mid);
    border: 1px solid var(--border);
    font-size: 12px;
    color: var(--text2);
    font-weight: 500;
    margin-bottom: 24px;
  }

  .success-info svg {
    width: 14px;
    height: 14px;
    stroke: currentColor;
    fill: none;
    stroke-width: 2;
  }

  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .field-row-3 {
    grid-template-columns: 1fr 1fr 1fr;
  }

  @media (max-width: 520px) {
    body { padding: 0; }
    .page-wrap { max-width: 100%; }
    .card { border-radius: 0; min-height: 100dvh; display: flex; flex-direction: column; box-shadow: none; border: none; }
    .card-body { padding: 20px 16px; flex: 1; }
    .card-hero { padding: 16px; }
    .card-footer {
      padding: 12px 16px;
      padding-bottom: max(12px, env(safe-area-inset-bottom));
      flex-direction: row;
      gap: 10px;
      position: sticky;
      bottom: 0;
      background: var(--navy-light);
      border-top: 1px solid var(--border);
    }
    .footer-left { width: auto; }
    .btn {
      flex: 0 0 auto;
      justify-content: center;
      padding: 10px 20px;
      border-radius: 8px;
      font-size: 12.5px;
    }
    .btn svg { width: 14px; height: 14px; }
    .btn-ghost { flex: 0 0 auto; padding: 10px 16px; }

    #step-counter { font-size: 11px; }
    .field-row { grid-template-columns: 1fr; }
    .field-row-3 { grid-template-columns: 1fr; }
    .review-key { min-width: 90px; font-size: 11px; }
    .review-val { font-size: 12px; }
    .tag-opt { font-size: 11px; padding: 5px 10px; }
    .theme-toggle { top: 12px; right: 12px; width: 40px; height: 40px; }
    .side-toast { max-width: calc(100vw - 20px); }
    .modal-box { padding: 24px 18px 20px; }
    .attempts-bar { flex-direction: column; gap: 8px; align-items: flex-start; }
    .field-hint { font-size: 10.5px; }
    input[type="text"], input[type="email"], input[type="number"], select, textarea {
      font-size: 15px; padding: 12px 14px;
    }
    .card-hero h1 { font-size: 16px; }
    .card-hero p { font-size: 12px; }
    .section-label { font-size: 10px; }
    .modal-title { font-size: 15px; }
    .modal-msg { font-size: 12px; }
    .success-wrap { padding: 40px 16px 24px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 60dvh; }
    .success-wrap.show { display: flex; }
    .success-icon { width: 56px; height: 56px; margin-bottom: 16px; }
    .success-icon svg { width: 24px; height: 24px; }
    .success-title { font-size: 16px; margin-bottom: 8px; }
    .success-sub { font-size: 12px; max-width: 100%; margin-bottom: 20px; }
    .success-info { font-size: 11px; padding: 8px 14px; margin-bottom: 20px; width: 100%; justify-content: center; }
    .success-wrap .btn { width: 100%; }
  }

  @media (min-width: 521px) and (max-width: 768px) {
    body { padding: 24px 12px; }
    .card-body { padding: 20px 22px; }
    .card-hero { padding: 20px 22px; }
    .card-footer { padding: 16px 22px; }
  }

  .modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.65);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.25s ease;
    padding: 24px;
  }

  .modal-overlay.show {
    opacity: 1;
    pointer-events: all;
  }

  .modal-box {
    background: var(--navy-light);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 36px 32px 28px;
    max-width: 420px;
    width: 100%;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.6);
    transform: scale(0.92) translateY(12px);
    transition: transform 0.25s ease;
  }

  .modal-overlay.show .modal-box {
    transform: scale(1) translateY(0);
  }

  .modal-icon {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
  }

  .modal-icon svg {
    width: 26px;
    height: 26px;
    fill: none;
    stroke-width: 2.5;
    stroke-linecap: round;
    stroke-linejoin: round;
  }

  .modal-icon.success {
    background: rgba(34,197,94,0.1);
    border: 2px solid rgba(34,197,94,0.3);
  }
  .modal-icon.success svg { stroke: var(--green); }

  .modal-icon.error {
    background: rgba(239,68,68,0.1);
    border: 2px solid rgba(239,68,68,0.3);
  }
  .modal-icon.error svg { stroke: var(--red); }

  .modal-icon.warning {
    background: rgba(245,158,11,0.1);
    border: 2px solid rgba(245,158,11,0.3);
  }
  .modal-icon.warning svg { stroke: #f59e0b; }

  .modal-title {
    font-variant-numeric:tabular-nums;
    font-size: 16px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 8px;
  }

  .modal-msg {
    font-size: 13px;
    color: var(--text2);
    line-height: 1.6;
    margin-bottom: 22px;
  }

  .modal-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 26px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
  }

  .modal-btn.primary {
    background: var(--primary);
    color: #0f1115;
  }
  .modal-btn.primary:hover { background: var(--primary-dark); }

  .modal-btn.green {
    background: var(--green);
    color: #fff;
  }
  .modal-btn.green:hover { background: #16a34a; }

  .modal-btn.amber {
    background: #f59e0b;
    color: #0f1115;
  }
  .modal-btn.amber:hover { background: #d97706; }

  .attempts-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    border-radius: 10px;
    background: rgba(245,158,11,0.06);
    border: 1px solid rgba(245,158,11,0.15);
    margin-bottom: 14px;
  }

  .attempts-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 600;
    color: #f59e0b;
  }

  .attempts-dots {
    display: flex;
    gap: 6px;
  }

  .attempt-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--border2);
    transition: background 0.2s;
  }

  .attempt-dot.used {
    background: #f59e0b;
  }

  .info-note.warn {
    background: rgba(239,68,68,0.08);
    border-color: rgba(239,68,68,0.2);
    color: #fca5a5;
  }

  .info-note.warn svg { stroke: var(--red); }

  /* Side toast notification */
  .side-toast-wrap {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    gap: 10px;
    pointer-events: none;
  }

  .side-toast {
    pointer-events: all;
    background: var(--navy-light);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 14px 16px;
    max-width: 360px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.5);
    transform: translateX(120%);
    opacity: 0;
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease;
  }

  .side-toast.show {
    transform: translateX(0);
    opacity: 1;
  }

  .side-toast-icon {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .side-toast-icon svg {
    width: 18px;
    height: 18px;
    fill: none;
    stroke-width: 2.5;
    stroke-linecap: round;
    stroke-linejoin: round;
  }

  .side-toast-icon.warn {
    background: rgba(245,158,11,0.12);
  }
  .side-toast-icon.warn svg { stroke: #f59e0b; }

  .side-toast-icon.error {
    background: rgba(239,68,68,0.12);
  }
  .side-toast-icon.error svg { stroke: var(--red); }

  .side-toast-icon.success {
    background: rgba(34,197,94,0.12);
  }
  .side-toast-icon.success svg { stroke: var(--green); }

  .side-toast-body {
    flex: 1;
  }

  .side-toast-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 3px;
  }

  .side-toast-msg {
    font-size: 12px;
    color: var(--text2);
    line-height: 1.4;
  }

  .side-toast-close {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    border: none;
    background: transparent;
    color: var(--text3);
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 5px;
    transition: background 0.15s;
  }

  .side-toast-close:hover {
    background: var(--navy-mid);
    color: var(--text1);
  }

  .side-toast-close svg {
    width: 14px;
    height: 14px;
    stroke: currentColor;
    fill: none;
    stroke-width: 2;
  }
</style>

<button class="theme-toggle" id="theme-toggle" onclick="toggleTheme()" title="Toggle theme" aria-label="Toggle theme">
  <svg id="theme-icon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
</button>

<div class="page-wrap">

  <div class="card" id="main-card">

    <div class="card-hero">
      @if($rejectAttempts > 0)
        <div class="attempts-bar">
          <div class="attempts-label">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            @if($cooldownActive)
              Attempt {{ $rejectAttempts }}/{{ $maxRejects }} — Cooldown active
            @elseif($rejectAttempts >= $maxRejects)
              Attempt {{ $rejectAttempts }}/{{ $maxRejects }} — Cooldown expired, you may retry
            @else
              Attempt {{ $rejectAttempts }}/{{ $maxRejects }}
            @endif
          </div>
          <div class="attempts-dots">
            @for($i = 1; $i <= $maxRejects; $i++)
              <div class="attempt-dot {{ $i <= $rejectAttempts ? 'used' : '' }}"></div>
            @endfor
          </div>
        </div>
        @if($cooldownActive)
          <div class="info-note warn" id="cooldown-note">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Maximum attempts reached. Please wait <b id="cooldown-timer">{{ $waitSeconds }}</b> seconds before resubmitting.
          </div>
        @elseif($rejectAttempts < $maxRejects)
          <div class="info-note">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Your account was rejected. You have <b>{{ $attemptsRemaining }}</b> attempt{{ $attemptsRemaining !== 1 ? 's' : '' }} remaining before a cooldown. Double-check your details before resubmitting.
          </div>
        @endif
      @endif
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <div class="step-dots" id="step-dots">
          <div class="step-dot active" id="dot-1"></div>
          <div class="step-dot" id="dot-2"></div>
          <div class="step-dot" id="dot-3"></div>
        </div>
      </div>
      <h1 id="hero-title">Personal Information</h1>
      <p id="hero-sub">Fill in your basic details to start your account request. All fields marked with <span style="color:var(--primary)">*</span> are required.</p>
    </div>

    @if($cooldownActive)
    <div class="card-body" style="padding: 48px 28px; text-align: center;">
      <div style="width:56px;height:56px;border-radius:50%;background:rgba(239,68,68,0.1);border:2px solid rgba(239,68,68,0.3);display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
        <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="#ef4444" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <div style="font-variant-numeric:tabular-nums;font-size:16px;font-weight:700;color:var(--text);margin-bottom:8px">Submission Blocked</div>
      <div style="font-size:13px;color:var(--text2);line-height:1.6;margin-bottom:22px;max-width:360px;margin-left:auto;margin-right:auto">
        You have reached the maximum number of attempts ({{ $rejectAttempts }}/{{ $maxRejects }}). 
        Please wait <b id="cooldown-timer-block">{{ $waitSeconds }}</b> seconds before you can resubmit your account request.
      </div>
      <a href="/" style="display:inline-flex;align-items:center;gap:6px;padding:9px 26px;border-radius:10px;font-size:13px;font-weight:600;background:var(--primary);color:#0f1115;text-decoration:none">Back to Home</a>
    </div>
    @else
    <div class="card-body">
      <div class="form-section active" id="step-1">
        <div class="section-label">
          <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Personal Details
        </div>
        <div class="field-row">
          <div class="field">
            <label>First Name <span class="req">*</span></label>
            <input type="text" id="fname" placeholder="e.g. Jose" value="{{ request('first_name', old('first_name', '')) }}"/>
            <div class="field-error" id="err-fname">Please enter your first name.</div>
          </div>
          <div class="field">
            <label>Last Name <span class="req">*</span></label>
            <input type="text" id="lname" placeholder="e.g. Dela Cruz" value="{{ request('last_name', old('last_name', '')) }}"/>
            <div class="field-error" id="err-lname">Please enter your last name.</div>
          </div>
        </div>

        <div class="field-row field-row-3">
          <div class="field">
            <label>Middle Name</label>
            <input type="text" id="mi" placeholder="e.g. Sigabo" length="50" value="{{ old('middle_name', '') }}"/>
            <div class="field-hint">Optional.</div>
          </div>
          <div class="field">
            <label>Suffix</label>
            <input type="text" id="suffix" placeholder="e.g. Jr., III" value="{{ old('suffix', '') }}"/>
            <div class="field-hint">Optional.</div>
          </div>
          <div class="field">
            <label>Credentials</label>
            <input type="text" id="credentials" placeholder="e.g. MIT, PhD" value="{{ old('credentials', '') }}"/>
            <div class="field-hint">Optional.</div>
          </div>
        </div>

        <div class="divider"></div>

        <div class="section-label">
          <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          Account Credentials
        </div>

        <div class="field-row full">
          <div class="field">
            <label>Gmail Address <span class="req">*</span></label>
            <input type="email" id="email" placeholder="yourname@gmail.com" value="{{ request('email', old('email', '')) }}"/>
            <div class="field-hint">Use your Gmail address.</div>
            <div class="field-error" id="err-email">Enter a valid @gmail.com email.</div>
          </div>
        </div>
      </div>

      <div class="form-section" id="step-2">
        <div class="section-label">
          <svg viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
          Areas of Expertise <span class="req" style="font-size:12px">*</span>
        </div>
        <div class="field-hint" style="margin-bottom:14px">Your role (Adviser, Consultant, Grammarian, etc.) is assigned by CAPSA when your request is approved - no need to pick one here.</div>

        <div class="tags-picker" id="tags-picker">
          <div class="tag-opt" onclick="toggleTag(this)">Machine Learning</div>
          <div class="tag-opt" onclick="toggleTag(this)">Web Development</div>
          <div class="tag-opt" onclick="toggleTag(this)">Mobile Apps</div>
          <div class="tag-opt" onclick="toggleTag(this)">Data Analytics</div>
          <div class="tag-opt" onclick="toggleTag(this)">Cybersecurity</div>
          <div class="tag-opt" onclick="toggleTag(this)">Network Systems</div>
          <div class="tag-opt" onclick="toggleTag(this)">Database Systems</div>
          <div class="tag-opt" onclick="toggleTag(this)">UI/UX Design</div>
          <div class="tag-opt" onclick="toggleTag(this)">Technical Writing</div>
          <div class="tag-opt" onclick="toggleTag(this)">Grammar &amp; Style</div>
          <div class="tag-opt" onclick="toggleTag(this)">Software Engineering</div>
          <div class="tag-opt" onclick="toggleTag(this)">Research Methods</div>
        </div>

        <div class="field-row full" style="grid-template-columns: 1fr auto; gap: 10px; margin-bottom: 6px;">
          <div class="field" style="gap:6px;">
            <label style="margin:0;">Add your own expertise</label>
            <input type="text" id="expertiseInput" placeholder="e.g. Machine Learning" onkeydown="handleExpertiseInputKeydown(event)" />

          </div>
          <div class="field" style="justify-content:flex-end;">
            <button type="button" class="btn btn-ghost" style="height: 42px; padding: 9px 14px;" onclick="addExpertiseTag()">
              Add
              <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </button>
          </div>
        </div>

        <div class="field-hint" style="margin-bottom:6px">Select at least one area of expertise.</div>

        <div class="field-error" id="err-tags">Please select at least one area of expertise.</div>

        <div class="divider"></div>

        <div class="section-label">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Team Capacity &amp; Course
        </div>
        <div class="field-row">
          <div class="field">
            <label>Maximum Teams <span class="req">*</span></label>
            <input type="number" id="max-teams" placeholder="e.g. 4" min="1" max="10" value="4"/>
            <div class="field-hint">How many teams you can advise simultaneously.</div>
          </div>
          <div class="field">
            <label>Course</label>
            <select id="course">
              <option value="BSIT - Information Technology">BSIT - Information Technology</option>
              <option value="BSCS - Computer Science">BSCS - Computer Science</option>
              <option value="BSIS - Information System">BSIS - Information System</option>
              <option value="BLIS - Information Science">BLIS - Information Science</option>
            </select>
            <div class="field-hint">Choose your course/program (optional).</div>
          </div>
        </div>

      </div>

      <div class="form-section" id="step-3">
        <div class="info-note">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          Review your information carefully. Your request will be sent to the admin for approval.
        </div>

        <div class="section-label">
          <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          Review Your Application
        </div>

        <div class="review-grid" id="review-panel"></div>
      </div>
    </div>
    <div class="card-footer">
      <div class="footer-left">

        <button class="btn btn-ghost" id="btn-back" style="display:none" onclick="goBack()">
          <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
          Back
        </button>
        <span id="step-counter">Step 1 of 3</span>
      </div>
      <button class="btn btn-primary" id="btn-next" onclick="goNext()">
        Continue
        <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </button>
    </div>

    <div class="success-wrap" id="success-wrap">
      <div class="success-icon">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <div class="success-title">Request Submitted!</div>
      <div class="success-sub">Your account request has been sent to the administrator for review. You will be notified via email once it is processed.</div>
      <div class="success-info">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Typical review time: 1–3 business days
      </div>
      <a href="/">
        <button class="btn btn-primary" style="margin:0 auto">Back to Login</button>
      </a>
    </div>
      <div style="display: flex; justify-content: center; margin-bottom: 10px;">
        <a href="/" class="signin-link">Already have an account? Sign in</a>
      </div>
  @endif
  </div>
</div>

<div class="modal-overlay" id="modal-overlay" onclick="if(event.target===this)hideModal()">
  <div class="modal-box">
    <div class="modal-icon" id="modal-icon"></div>
    <div class="modal-title" id="modal-title"></div>
    <div class="modal-msg" id="modal-msg"></div>
    <button class="modal-btn primary" id="modal-btn" onclick="hideModal()">Got It</button>
  </div>
</div>

<div class="side-toast-wrap" id="side-toast-wrap"></div>

<script>
let currentStep = 1;
const totalSteps = 3;

function goNext() {
  if (!validateStep(currentStep)) return;
  if (currentStep === 3) { submitRequest(); return; }
  currentStep++;
  updateStep();
}

function goBack() {
  if (currentStep === 1) return;
  currentStep--;
  updateStep();
}

function updateStep() {
  document.querySelectorAll('.form-section').forEach((s, i) => {
    s.classList.toggle('active', i + 1 === currentStep);
  });

  for (let i = 1; i <= 3; i++) {
    const dot = document.getElementById(`dot-${i}`);
    dot.className = 'step-dot';
    if (i < currentStep) dot.classList.add('done');
    else if (i === currentStep) dot.classList.add('active');
  }

  document.getElementById('step-counter').textContent = `Step ${currentStep} of ${totalSteps}`;
  document.getElementById('btn-back').style.display = currentStep > 1 ? '' : 'none';

  const nextBtn = document.getElementById('btn-next');
  if (currentStep === 3) {
    nextBtn.innerHTML = `Submit Request <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`;
    nextBtn.className = 'btn btn-success';
    buildReview();
  } else {
    nextBtn.innerHTML = `Continue <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>`;
    nextBtn.className = 'btn btn-primary';
  }

  const titles = ['Personal Information', 'Areas of Expertise', 'Review & Submit'];
  const subs = [
    'Fill in your basic details to start your account request. All fields marked with <span style="color:var(--primary)">*</span> are required.',
    'Highlight your areas of expertise - your role is assigned by CAPSA when your request is approved.',
    'Everything look correct? Submit your request for admin approval.'
  ];
  document.getElementById('hero-title').textContent = titles[currentStep - 1];
  document.getElementById('hero-sub').innerHTML = subs[currentStep - 1];
}

function validateStep(step) {
  let ok = true;

  if (step === 1) {
    const isFnameValid = req('fname', 'err-fname', v => v.trim().length > 0);
    const isLnameValid = req('lname', 'err-lname', v => v.trim().length > 0);
    const isEmailValid = req('email', 'err-email', v => /^[^@]+@gmail\.com$/i.test(v.trim()));
    ok = isFnameValid && isLnameValid && isEmailValid;
  }

  if (step === 2) {
    const tags = getSelectedTags();
    if (tags.length === 0) {
      document.getElementById('err-tags').classList.add('show');
      ok = false;
    } else {
      document.getElementById('err-tags').classList.remove('show');
    }
  }

  return ok;
}

function req(id, errId, test) {
  const el = document.getElementById(id);
  const errEl = document.getElementById(errId);
  const valid = test(el.value);
  el.classList.toggle('error', !valid);
  errEl.classList.toggle('show', !valid);
  return valid;
}

function toggleTag(el) {
  el.classList.toggle('selected');
  document.getElementById('err-tags').classList.remove('show');
}

function handleExpertiseInputKeydown(event) {
  // Enter to add tag (prevents form submit/line breaks)
  if (event.key === 'Enter') {
    event.preventDefault();
    addExpertiseTag();
  }
}

function addExpertiseTag() {
  const input = document.getElementById('expertiseInput');
  const raw = input.value;

  if (!raw) return;

  const normalized = raw.trim().replace(/\s+/g, ' ');
  if (!normalized) return;

  const existing = Array.from(document.querySelectorAll('#tags-picker .tag-opt')).map(t => t.textContent.trim().toLowerCase());
  if (existing.includes(normalized.toLowerCase())) {
    // if it already exists, just select it
    const el = Array.from(document.querySelectorAll('#tags-picker .tag-opt')).find(t => t.textContent.trim().toLowerCase() === normalized.toLowerCase());
    if (el && !el.classList.contains('selected')) {
      el.classList.add('selected');
      document.getElementById('err-tags').classList.remove('show');
    }
    input.value = '';
    return;
  }

  const el = document.createElement('div');
  el.className = 'tag-opt selected';
  el.textContent = normalized;
  el.onclick = function() { toggleTag(this); };
  document.getElementById('tags-picker').appendChild(el);

  input.value = '';
  document.getElementById('err-tags').classList.remove('show');
}


function getSelectedTags() {
  return Array.from(document.querySelectorAll('.tag-opt.selected')).map(t => t.textContent.trim());
}

function buildReview() {
  const panel = document.getElementById('review-panel');
  const fname = document.getElementById('fname').value.trim();
  const mi = document.getElementById('mi').value.trim();
  const lname = document.getElementById('lname').value.trim();
  const suffix = document.getElementById('suffix').value.trim();
  const credentials = document.getElementById('credentials').value.trim();
  const email = document.getElementById('email').value.trim();
  const tags = getSelectedTags();
  const maxTeams = document.getElementById('max-teams').value.trim() || '4';
  const course = document.getElementById('course').value.trim();


  let fullName = fname;
  if (mi) fullName += ` ${mi}`;
  fullName += ` ${lname}`;
  if (suffix) fullName += ` ${suffix}`;
  if (credentials) fullName += `, ${credentials}`;

  panel.innerHTML = `
    <div class="review-item">
      <div class="review-key">Full Name</div>
      <div class="review-val">${fullName}</div>
    </div>
    <div class="review-item">
      <div class="review-key">Email Address</div>
      <div class="review-val">${email}</div>
    </div>
    <div class="review-item">
      <div class="review-key">Expertise</div>
      <div class="review-val"><div class="review-tags">${tags.map(t => `<span class="review-tag">${t}</span>`).join('')}</div></div>
    </div>
    <div class="review-item">
      <div class="review-key">Max Teams</div>
      <div class="review-val">${maxTeams}</div>
    </div>
    ${course ? `<div class="review-item"><div class="review-key">Course</div><div class="review-val">${course}</div></div>` : ''}

  `;
}

function submitRequest() {
  const btn = document.getElementById('btn-next');
  btn.disabled = true;
  btn.innerHTML = `<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation:spin 1s linear infinite"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="4.93"/></svg> Submitting...`;

  const fname = document.getElementById('fname').value.trim();
  const lname = document.getElementById('lname').value.trim();
  const mi = document.getElementById('mi').value.trim();
  const suffix = document.getElementById('suffix').value.trim();
  const credentials = document.getElementById('credentials').value.trim();
  const email = document.getElementById('email').value.trim();
  const tags = getSelectedTags();
  const maxTeams = document.getElementById('max-teams').value.trim() || 4;
  const course = document.getElementById('course')?.value.trim();

  const url = '{{ route("account.request.store") }}';


  fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Accept': 'application/json',
    },
    body: JSON.stringify({
      first_name: fname,
      last_name: lname,
      middle_name: mi,
      suffix: suffix,
      credentials: credentials,
      email: email,
      expertise: tags,
      max_teams: parseInt(maxTeams),
      course: course || null,

    }),
  })
  .then(async response => {
    const text = await response.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error('Non-JSON response:', text);
      throw new Error('Server error. Raw response: ' + text.substring(0, 200));
    }

    if (!response.ok) {
      if (data.errors) {
        Object.entries(data.errors).forEach(([field, messages]) => {
          const errEl = document.getElementById('err-' + field);
          if (errEl) {
            errEl.textContent = messages[0];
            errEl.classList.add('show');
          }
        });
      }
      throw new Error(data.message || 'Submission failed');
    }

    return data;
  })
  .then(data => {
    if (data.success) {
      document.querySelector('.card-body').style.display = 'none';
      document.querySelector('.card-footer').style.display = 'none';
      document.querySelector('.card-hero').style.display = 'none';
      document.getElementById('success-wrap').classList.add('show');
    } else {
      showModal('error', 'Submission Failed', data.message || 'An error occurred. Please try again.');
      btn.disabled = false;
      btn.innerHTML = 'Submit Request';
    }
  })
  .catch(error => {
    console.error('Submit error:', error);
    showModal('error', 'Submission Failed', error.message || 'An error occurred. Please verify your fields and try again.');
    btn.disabled = false;
    btn.innerHTML = 'Submit Request';
  });
}

function showModal(type, title, msg) {
  const iconEl = document.getElementById('modal-icon');
  const titleEl = document.getElementById('modal-title');
  const msgEl = document.getElementById('modal-msg');
  const btnEl = document.getElementById('modal-btn');

  const icons = {
    success: '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
    error: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    warning: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
  };

  const btnClasses = { success: 'green', error: 'primary', warning: 'amber' };

  iconEl.className = 'modal-icon ' + type;
  iconEl.innerHTML = icons[type] || icons.error;
  titleEl.textContent = title;
  msgEl.textContent = msg;
  btnEl.className = 'modal-btn ' + (btnClasses[type] || 'primary');
  btnEl.textContent = 'Got It';

  document.getElementById('modal-overlay').classList.add('show');
  document.body.style.overflow = 'hidden';
}

function hideModal() {
  document.getElementById('modal-overlay').classList.remove('show');
  document.body.style.overflow = '';
}

function showSideToast(type, title, msg, duration) {
  const wrap = document.getElementById('side-toast-wrap');
  const icons = {
    warn: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
    error: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    success: '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
  };

  const el = document.createElement('div');
  el.className = 'side-toast';
  el.innerHTML = `
    <div class="side-toast-icon ${type}">${icons[type] || icons.warn}</div>
    <div class="side-toast-body">
      <div class="side-toast-title">${title}</div>
      <div class="side-toast-msg">${msg}</div>
    </div>
    <button class="side-toast-close" onclick="this.parentElement.remove()">
      <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  `;
  wrap.appendChild(el);
  requestAnimationFrame(() => el.classList.add('show'));

  const dur = duration || 6000;
  const timer = setTimeout(() => {
    el.classList.remove('show');
    setTimeout(() => el.remove(), 350);
  }, dur);

  el.querySelector('.side-toast-close').addEventListener('click', () => {
    clearTimeout(timer);
    el.classList.remove('show');
    setTimeout(() => el.remove(), 350);
  });
}

// Cooldown countdown timer
@if($cooldownActive && $waitSeconds)
(function() {
  let seconds = {{ $waitSeconds }};
  const timerEl = document.getElementById('cooldown-timer');
  if (!timerEl) return;
  const interval = setInterval(() => {
    seconds--;
    if (seconds <= 0) {
      clearInterval(interval);
      timerEl.textContent = '0';
      location.reload();
    } else {
      timerEl.textContent = seconds;
    }
  }, 1000);
})();
@endif

// Show warning side toast on page load if user has been rejected
@if($rejectAttempts > 0 && !$cooldownActive && $rejectAttempts < $maxRejects)
document.addEventListener('DOMContentLoaded', function() {
  showSideToast(
    'warn',
    'Attempt {{ $rejectAttempts }}/{{ $maxRejects }}',
    'Your account was rejected. You have {{ $attemptsRemaining }} attempt{{ $attemptsRemaining !== 1 ? "s" : "" }} remaining before a 1-minute cooldown.'
  );
});
@elseif($rejectAttempts >= $maxRejects && !$cooldownActive)
document.addEventListener('DOMContentLoaded', function() {
  showSideToast(
    'warn',
    'Cooldown Expired',
    'Your cooldown period has ended. You may resubmit your account request.'
  );
});
@endif

const style = document.createElement('style');
style.textContent = '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
document.head.appendChild(style);

document.querySelectorAll('input, select, textarea').forEach(el => {
  el.addEventListener('input', () => {
    el.classList.remove('error');
  });
});

// ── THEME TOGGLE ──
const moonSvg = '<svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
const sunSvg = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
const icon = document.getElementById('theme-icon');

function toggleTheme() {
  const html = document.documentElement;
  const isLight = html.getAttribute('data-theme') === 'light';
  if (isLight) {
    html.removeAttribute('data-theme');
    localStorage.setItem('theme', 'dark');
    icon.innerHTML = moonSvg;
  } else {
    html.setAttribute('data-theme', 'light');
    localStorage.setItem('theme', 'light');
    icon.innerHTML = sunSvg;
  }
}

(function initTheme() {
  const saved = localStorage.getItem('theme');
  if (saved === 'light') {
    document.documentElement.setAttribute('data-theme', 'light');
    icon.innerHTML = sunSvg;
  }
})();
</script>
@endsection
