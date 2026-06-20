@extends('layouts.guest')

@section('title', 'Account Request - CapsTRACK')

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

  .signin-link:hover {
    color: #d4b23a;
    text-decoration: underline;
  }

  .card-hero h1 {
    font-family: 'Space Mono', monospace;
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

  .role-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 10px;
  }

  .role-card {
    border: 1.5px solid var(--border2);
    background: var(--navy-mid);
    border-radius: 12px;
    padding: 14px 10px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
    user-select: none;
  }

  .role-card:hover {
    border-color: rgba(181,148,31,0.4);
    background: rgba(181,148,31,0.04);
  }

  .role-card.selected {
    border-color: var(--primary);
    background: rgba(181,148,31,0.1);
  }

  .role-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: rgba(255,255,255,0.04);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 8px;
    transition: background 0.2s;
  }

  .role-card.selected .role-icon {
    background: rgba(181,148,31,0.2);
  }

  .role-icon svg {
    width: 18px;
    height: 18px;
    stroke: var(--text2);
    fill: none;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
    transition: stroke 0.2s;
  }

  .role-card.selected .role-icon svg {
    stroke: var(--primary);
  }

  .role-name {
    font-size: 12px;
    font-weight: 600;
    color: var(--text2);
    transition: color 0.2s;
  }

  .role-card.selected .role-name {
    color: var(--primary);
  }

  .role-desc {
    font-size: 10.5px;
    color: var(--text3);
    margin-top: 3px;
    line-height: 1.3;
  }

  .divider {
    height: 1px;
    background: var(--border);
    margin: 20px 0;
  }

  .review-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }

  .review-item {
    background: var(--navy-mid);
    border-radius: 10px;
    padding: 12px 14px;
  }

  .review-item.full {
    grid-column: 1 / -1;
  }

  .review-key {
    font-size: 10px;
    color: var(--text3);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    font-weight: 700;
    margin-bottom: 4px;
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
    margin-top: 6px;
  }

  .review-tag {
    padding: 3px 10px;
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
    font-family: 'Space Mono', monospace;
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
    background: rgba(245,158,11,0.08);
    border: 1px solid rgba(245,158,11,0.2);
    font-size: 12px;
    color: #fbbf24;
    font-weight: 600;
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

  @media (max-width: 520px) {
    .field-row { grid-template-columns: 1fr; }
    .role-grid { grid-template-columns: 1fr; }
    .review-grid { grid-template-columns: 1fr; }
    .card-body, .card-footer, .card-hero { padding-left: 18px; padding-right: 18px; }
  }
</style>

<div class="page-wrap">

  <div class="card" id="main-card">

    <div class="card-hero">
      <div class="step-dots" id="step-dots">
        <div class="step-dot active" id="dot-1"></div>
        <div class="step-dot" id="dot-2"></div>
        <div class="step-dot" id="dot-3"></div>
      </div>
      <h1 id="hero-title">Personal Information</h1>
      <p id="hero-sub">Fill in your basic details to start your account request. All fields marked with <span style="color:var(--primary)">*</span> are required.</p>
    </div>

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

        <div class="field-row">
          <div class="field">
            <label>Middle Initial</label>
            <input type="text" id="mi" placeholder="e.g. L" maxlength="3" value="{{ old('middle_name', '') }}"/>
            <div class="field-hint">Optional. Single letter only.</div>
          </div>
          <div class="field">
            <label>Credentials / Suffix</label>
            <input type="text" id="credentials" placeholder="e.g. MIT, PhD" value="{{ old('credentials', '') }}"/>
            <div class="field-hint">Optional. Academic credentials.</div>
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
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Select Your Role <span class="req" style="font-size:12px">*</span>
        </div>

        <div class="role-grid" id="role-grid">
          <div class="role-card" onclick="toggleRole('capstone_adviser')" id="role-capstone_adviser">
            <div class="role-icon">
              <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </div>
            <div class="role-name">CaPSA / Thesis Adviser</div>
            <div class="role-desc">Serves as Capstone Project Subject Adviser or Thesis Subject Adviser</div>
          </div>
          <div class="role-card" onclick="toggleRole('adviser')" id="role-adviser">
            <div class="role-icon">
              <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
            </div>
            <div class="role-name">Adviser</div>
            <div class="role-desc">Guides capstone teams throughout their project journey</div>
          </div>
          <div class="role-card" onclick="toggleRole('consultant')" id="role-consultant">
            <div class="role-icon">
              <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <div class="role-name">Consultant</div>
            <div class="role-desc">Provides subject-matter expertise and technical advice</div>
          </div>
          <div class="role-card" onclick="toggleRole('grammarian')" id="role-grammarian">
            <div class="role-icon">
              <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            </div>
            <div class="role-name">Grammarian</div>
            <div class="role-desc">Reviews and ensures quality of written documentation</div>
          </div>
        </div>
        <div class="field-error" id="err-role" style="margin-bottom:12px">Please select a role.</div>

        <div class="divider"></div>

        <div class="section-label">
          <svg viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
          Areas of Expertise <span class="req" style="font-size:12px">*</span>
        </div>

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
        <div class="field-hint" style="margin-bottom:6px">Select at least one area of expertise.</div>
        <div class="field-error" id="err-tags">Please select at least one area of expertise.</div>

        <div class="divider"></div>

        <div class="section-label">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Team Capacity
        </div>
        <div class="field-row">
          <div class="field">
            <label>Maximum Teams <span class="req">*</span></label>
            <input type="number" id="max-teams" placeholder="e.g. 4" min="1" max="10" value="4"/>
            <div class="field-hint">How many teams you can advise simultaneously.</div>
          </div>
        </div>

        <div class="divider"></div>

        <div class="section-label">
          <svg viewBox="0 0 24 24"><path d="M3 7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7z"/><path d="M8 11h8"/><path d="M8 15h5"/></svg>
          Department
        </div>
        <div class="field-row full">
          <div class="field">
            <label>Department</label>
            <input type="text" id="department" placeholder="e.g. Computer Science" value=""/>
            <div class="field-hint">Your academic department (optional).</div>
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
  </div>
</div>

<script>
let currentStep = 1;
const totalSteps = 3;
let selectedRoles = [];

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

  const titles = ['Personal Information', 'Role & Expertise', 'Review & Submit'];
  const subs = [
    'Fill in your basic details to start your account request. All fields marked with <span style="color:var(--primary)">*</span> are required.',
    'Select your role in capstone reviews and highlight your areas of expertise.',
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
    if (selectedRoles.length === 0) {
      document.getElementById('err-role').classList.add('show');
      ok = false;
    } else {
      document.getElementById('err-role').classList.remove('show');
    }

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

function toggleRole(role) {
  const idx = selectedRoles.indexOf(role);
  if (idx >= 0) {
    selectedRoles.splice(idx, 1);
  } else {
    selectedRoles.push(role);
  }
  document.getElementById(`role-${role}`).classList.toggle('selected', selectedRoles.includes(role));
  if (selectedRoles.length > 0) {
    document.getElementById('err-role').classList.remove('show');
  }
}

function toggleTag(el) {
  el.classList.toggle('selected');
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
  const credentials = document.getElementById('credentials').value.trim();
  const email = document.getElementById('email').value.trim();
  const tags = getSelectedTags();
  const maxTeams = document.getElementById('max-teams').value.trim() || '4';
  const department = document.getElementById('department').value.trim();

  let fullName = fname;
  if (mi) fullName += ` ${mi.replace('.','')}.`;
  fullName += ` ${lname}`;
  if (credentials) fullName += `, ${credentials}`;

  panel.innerHTML = `
    <div class="review-item">
      <div class="review-key">Full Name</div>
      <div class="review-val">${fullName}</div>
    </div>
    <div class="review-item full">
      <div class="review-key">Roles</div>
      <div class="review-tags">${selectedRoles.map(r => `<span class="review-tag">${r}</span>`).join('')}</div>
    </div>
    <div class="review-item full">
      <div class="review-key">Email Address</div>
      <div class="review-val">${email}</div>
    </div>
    <div class="review-item full">
      <div class="review-key">Areas of Expertise</div>
      <div class="review-tags">${tags.map(t => `<span class="review-tag">${t}</span>`).join('')}</div>
    </div>
    <div class="review-item">
      <div class="review-key">Max Teams</div>
      <div class="review-val">${maxTeams}</div>
    </div>
    ${department ? `<div class="review-item full"><div class="review-key">Department</div><div class="review-val">${department}</div></div>` : ''}
  `;
}

function submitRequest() {
  const btn = document.getElementById('btn-next');
  btn.disabled = true;
  btn.innerHTML = `<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation:spin 1s linear infinite"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="4.93"/></svg> Submitting...`;

  const fname = document.getElementById('fname').value.trim();
  const lname = document.getElementById('lname').value.trim();
  const mi = document.getElementById('mi').value.trim();
  const credentials = document.getElementById('credentials').value.trim();
  const email = document.getElementById('email').value.trim();
  const tags = getSelectedTags();
  const maxTeams = document.getElementById('max-teams').value.trim() || 4;
  const department = document.getElementById('department').value.trim();
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
      credentials: credentials,
      email: email,
      specialization: selectedRoles,
      expertise: tags,
      max_teams: parseInt(maxTeams),
      department: department || null,
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
      alert(data.message || 'An error occurred. Please try again.');
      btn.disabled = false;
      btn.innerHTML = 'Submit Request';
    }
  })
  .catch(error => {
    console.error('Submit error:', error);
    alert(error.message || 'An error occurred. Please verify your fields and try again.');
    btn.disabled = false;
    btn.innerHTML = 'Submit Request';
  });
}

const style = document.createElement('style');
style.textContent = '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
document.head.appendChild(style);

document.querySelectorAll('input, select, textarea').forEach(el => {
  el.addEventListener('input', () => {
    el.classList.remove('error');
  });
});
</script>
@endsection
