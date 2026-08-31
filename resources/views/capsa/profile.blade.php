@php
  $currentYear = (int) date('Y');
  $ayStart = $currentYear;           // e.g. 2026
  $ayEnd = $currentYear + 1;        // e.g. 2027

  $shortStart = substr((string) $ayStart, -2); // e.g. 26
  $shortEnd = substr((string) $ayEnd, -2);     // e.g. 27

  // Keep stored dropdown `value` format as `${YY}-1`.
  // Example when $currentYear=2026:
  // - 26-1 => First Semester 2026-2027 (26-1)
  // - 27-1 => Second Semester 2026-2027 (27-1)
  $semOptions = [
    $shortStart . '-1' => 'First Semester ' . $ayStart . '-' . $ayEnd . ' (' . $shortStart . '-1)',
    $shortEnd . '-1' => 'Second Semester ' . $ayStart . '-' . $ayEnd . ' (' . $shortEnd . '-1)',
  ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin Profile - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>

<style>
  /* ============================================================
     Profile page — scoped styles. Built on top of the shared
     admin.css tokens (--accent, --text, --text2, --text3,
     --border, --bg3, --green) with safe fallbacks so this page
     stays visually consistent with the rest of the admin panel.
     ============================================================ */
  .profile-page {
    --pf-accent: #475569;
    --pf-text: var(--text, #101828);
    --pf-text2: var(--text2, #344054);
    --pf-text3: var(--text3, #98a2b3);
    --pf-border: var(--border, #e4e7ec);
    --pf-surface: var(--bg3, #f8fafc);
    --pf-green: var(--green, #16a34a);
    --pf-radius: 16px;
    --pf-radius-sm: 10px;
    --pf-shadow: 0 1px 2px rgba(16, 24, 40, 0.04), 0 1px 3px rgba(16, 24, 40, 0.03);
    max-width: 1040px;
    margin: 0 auto;
  }

  .profile-page .pf-intro {
    margin-bottom: 22px;
  }

  .profile-page .pf-eyebrow {
    font-family: 'Space Mono', monospace;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--pf-accent);
    margin: 0 0 6px;
  }

  .profile-page .pf-subtitle {
    font-size: 13.5px;
    color: var(--pf-text3);
    margin: 0;
  }

  /* Status banner */
  .profile-page .pf-status {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: rgba(22, 163, 74, 0.07);
    border: 1px solid rgba(22, 163, 74, 0.18);
    border-radius: var(--pf-radius-sm);
    font-size: 13.5px;
    color: var(--pf-green);
    margin-bottom: 20px;
  }

  .profile-page .pf-status svg {
    flex-shrink: 0;
  }

  /* Shared card look */
  .profile-page .pf-card {
    background: #fff;
    border: 1px solid var(--pf-border);
    border-radius: var(--pf-radius);
    box-shadow: var(--pf-shadow);
    padding: 24px;
  }

  .profile-page .pf-card + .pf-card {
    margin-top: 20px;
  }

  .profile-page .pf-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 20px;
  }

  .profile-page .pf-card-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--pf-text);
    letter-spacing: -0.01em;
  }

  .profile-page .pf-card-desc {
    font-size: 12.5px;
    color: var(--pf-text3);
    margin-top: 3px;
  }

  /* Identity strip */
  .profile-page .pf-identity {
    display: flex;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
  }

  .profile-page .pf-avatar-well {
    flex-shrink: 0;
    position: relative;
  }

  .profile-page .app-avatar,
  .profile-page .pf-avatar-well img {
    width: 72px;
    height: 72px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    font-weight: 700;
    color: #fff;
    box-shadow: 0 0 0 4px #fff, 0 0 0 5px var(--pf-border);
  }

  .profile-page .pf-identity-meta {
    min-width: 0;
    flex: 1 1 auto;
  }

  .profile-page .pf-name {
    font-size: 17px;
    font-weight: 700;
    color: var(--pf-text);
    line-height: 1.25;
    letter-spacing: -0.01em;
  }

  .profile-page .pf-email {
    font-size: 13px;
    color: var(--pf-text3);
    margin-top: 3px;
    word-break: break-word;
  }

  .profile-page .pf-avatar-actions {
    margin-top: 12px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  .profile-page .pf-btn {
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    font-weight: 600;
    padding: 9px 14px;
    border-radius: 10px;
    border: 1.5px solid var(--pf-border);
    background: #fff;
    color: var(--pf-text2);
    cursor: pointer;
    transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
  }

  .profile-page .pf-btn:hover {
    border-color: var(--pf-accent);
    color: var(--pf-accent);
    background: rgba(71, 85, 105, 0.04);
  }

  .profile-page .pf-btn-primary {
    background: var(--pf-accent);
    border-color: var(--pf-accent);
    color: #fff;
  }

  .profile-page .pf-btn-primary:hover {
    filter: brightness(0.94);
    color: #fff;
  }

  /* Form layout */
  .profile-page .pf-fields {
    margin-top: 22px;
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .profile-page .pf-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
  }

  .profile-page .pf-field label.label {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--pf-text2);
    margin-bottom: 6px;
    display: block;
  }

  .profile-page .pf-field .input {
    width: 100%;
    box-sizing: border-box;
    font-size: 14px;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1.5px solid var(--pf-border);
    color: var(--pf-text);
    background: #fff;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
  }

  .profile-page .pf-field .input:focus {
    outline: none;
    border-color: var(--pf-accent);
    box-shadow: 0 0 0 3px rgba(71, 85, 105, 0.12);
  }

  .profile-page .pf-field-static {
    padding: 10px 12px;
    border: 1.5px solid var(--pf-border);
    border-radius: 10px;
    font-size: 14px;
    color: var(--pf-text2);
    background: var(--pf-surface);
  }

  .profile-page .pf-form-footer {
    display: flex;
    justify-content: flex-end;
    margin-top: 22px;
    padding-top: 18px;
    border-top: 1px solid var(--pf-border);
  }

  /* Sections list */
  .profile-page .pf-section-count {
    font-family: 'Space Mono', monospace;
    font-size: 11.5px;
    font-weight: 700;
    color: var(--pf-accent);
    background: rgba(71, 85, 105, 0.08);
    padding: 4px 10px;
    border-radius: 999px;
    white-space: nowrap;
  }

  .profile-page .pf-search {
    position: relative;
    margin-bottom: 14px;
  }

  .profile-page .pf-search input {
    width: 100%;
    box-sizing: border-box;
    font-size: 13.5px;
    padding: 9px 12px 9px 34px;
    border-radius: 10px;
    border: 1.5px solid var(--pf-border);
    background: var(--pf-surface);
    color: var(--pf-text);
  }

  .profile-page .pf-search input:focus {
    outline: none;
    border-color: var(--pf-accent);
    background: #fff;
  }

  .profile-page .pf-search svg {
    position: absolute;
    left: 11px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--pf-text3);
  }

  .profile-page .pf-sections-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 400px;
    overflow-y: auto;
    padding-right: 2px;
  }

  .profile-page .pf-section-opt {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 12px;
    border: 1.5px solid var(--pf-border);
    border-radius: 10px;
    cursor: pointer;
    transition: border-color 0.15s ease, background 0.15s ease;
  }

  .profile-page .pf-section-opt:hover {
    border-color: var(--pf-accent);
    background: rgba(71, 85, 105, 0.03);
  }
  .profile-page .pf-section-opt:has(input:checked) {
    border-color: var(--pf-accent);
    background: rgba(71, 85, 105, 0.06);
  }

  .profile-page .pf-section-opt input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: var(--pf-accent);
    flex-shrink: 0;
  }

  .profile-page .pf-section-opt span {
    font-size: 13.5px;
    font-weight: 600;
    color: var(--pf-text);
  }

  .profile-page .pf-empty {
    padding: 28px 12px;
    text-align: center;
    color: var(--pf-text3);
    font-size: 13px;
  }

  /* Layout grid for the two main cards */
  .profile-page .pf-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    align-items: stretch;
    margin-top: 20px;
  }

  /* Equal-height cards: each card becomes a column, footer pinned to bottom */
  .profile-page .pf-columns > .pf-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    margin-top: 0; /* override .pf-card + .pf-card so both cards stay top-aligned */
  }

  .profile-page .pf-columns .pf-fields {
    flex: 1 1 auto;
  }

  .profile-page .pf-columns .pf-sections-list {
    flex: 1 1 auto;
    max-height: none;
  }

  .profile-page .pf-columns .pf-form-footer {
    margin-top: auto;
  }

  .profile-page .expertise-chip.active {
    border-color: var(--pf-accent);
    background: rgba(71,85,105,0.08);
    color: var(--pf-accent);
  }

  @media (max-width: 860px) {
    .profile-page .pf-columns {
      grid-template-columns: 1fr;
    }
    .profile-page .pf-row {
      grid-template-columns: 1fr;
    }
    .profile-page .pf-columns > .pf-card {
      height: auto;
    }
    .profile-page .pf-columns .pf-sections-list {
      max-height: 400px;
    }
  }
</style>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
</head>
<body>

@include('capsa._sidebar')
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">My Profile</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="sk" style="width:220px;height:24px;border-radius:4px;margin-bottom:20px"></div>
    <div class="card" style="margin-bottom:16px;display:flex;align-items:center;gap:16px;padding:16px">
      <div class="sk" style="width:64px;height:64px;border-radius:50%;flex-shrink:0"></div>
      <div style="flex:1">
        <div class="sk" style="width:160px;height:16px;border-radius:4px;margin-bottom:8px"></div>
        <div class="sk" style="width:220px;height:12px;border-radius:4px"></div>
      </div>
    </div>
    <div class="grid-2">
      <div class="card">
        <div class="sk" style="height:40px;border-radius:8px;margin-bottom:10px"></div>
        <div class="sk" style="height:40px;border-radius:8px;margin-bottom:10px"></div>
        <div class="sk" style="height:40px;border-radius:8px"></div>
      </div>
      <div class="card">
        <div class="sk" style="height:40px;border-radius:8px;margin-bottom:10px"></div>
        <div class="sk" style="height:40px;border-radius:8px"></div>
      </div>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content">
    <div class="profile-page">

      <div class="pf-intro">
        <p class="pf-eyebrow">Account</p>
        <p class="pf-subtitle">Update your personal details and manage the sections assigned to you.</p>
      </div>

      @if (session('status'))
        <div class="pf-status">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
          <span>{{ session('status') }}</span>
        </div>
      @endif

      @php
        $viewer = auth()->user();
        $avatarUrl = '';
        $initials = method_exists($admin, 'avatarInitials') ? $admin->avatarInitials() : (isset($admin->name) ? strtoupper(substr($admin->name,0,2)) : 'U');

        if ($viewer) {
          if (!empty($viewer->profile_picture) && filter_var($viewer->profile_picture, FILTER_VALIDATE_URL)) {
            $avatarUrl = $viewer->profile_picture;
          } elseif (!empty($viewer->avatar) && filter_var($viewer->avatar, FILTER_VALIDATE_URL)) {
            $avatarUrl = $viewer->avatar;
          } elseif (method_exists($viewer, 'avatarUrl') && $viewer->avatarUrl()) {
            $avatarUrl = $viewer->avatarUrl();
          }

          if (method_exists($viewer, 'avatarInitials')) {
            $initials = $viewer->avatarInitials();
          } elseif (isset($viewer->name)) {
            $initials = strtoupper(substr($viewer->name, 0, 2));
          }
        }

        if (!$avatarUrl) {
          if (!empty($admin->profile_picture) && filter_var($admin->profile_picture, FILTER_VALIDATE_URL)) {
            $avatarUrl = $admin->profile_picture;
          } elseif (!empty($admin->avatar) && filter_var($admin->avatar, FILTER_VALIDATE_URL)) {
            $avatarUrl = $admin->avatar;
          } elseif (method_exists($admin, 'avatarUrl') && $admin->avatarUrl()) {
            $avatarUrl = $admin->avatarUrl();
          }
        }

      @endphp

      <form method="POST" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data">
        @csrf

        {{-- Identity strip: avatar + name/email --}}
        <div class="pf-card">
          <div class="pf-identity">
            <div class="pf-avatar-well" id="avatar-preview" data-initials="{{ $initials }}">
              @if (!empty($avatarUrl))
                <img src="{{ $avatarUrl }}" alt="{{ $admin->name ?? 'User' }}" class="app-avatar" style="object-fit:cover" />
              @else
                <img src="{{ asset('images/default-avatar.jpg') }}" alt="Default avatar" class="app-avatar" style="object-fit:cover" />
              @endif
            </div>

            <div class="pf-identity-meta">
              <div class="pf-name">{{ $admin->name }}</div>
              <div class="pf-email">{{ $admin->email }}</div>
            </div>
          </div>

          <input type="file" id="avatar" name="avatar" accept="image/*" style="display:none" />
        </div>

        <div class="pf-columns">

          {{-- Left column: personal information --}}
          <div class="pf-card" style="display:flex;flex-direction:column">
            <div class="pf-card-header" style="margin-bottom:0;padding-bottom:18px;border-bottom:1px solid var(--pf-border);display:flex;align-items:center;gap:12px">
              <div style="flex:1">
                <div class="pf-card-title" style="display:flex;align-items:center;gap:8px">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--pf-accent);flex-shrink:0"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  Personal Information
                </div>
                <div class="pf-card-desc" style="margin-top:2px">Your name, credentials, and expertise</div>
              </div>
            </div>

            <div style="flex:1;display:flex;flex-direction:column;min-height:0;padding-top:16px">
              <div class="pf-fields" style="gap:14px">
                <div class="pf-row">
                  <div class="pf-field">
                    <label class="label" for="first_name">First Name</label>
                    <input id="first_name" name="first_name" class="input" placeholder="e.g. Juan" value="{{ old('first_name', $admin->first_name) }}" />
                  </div>
                  <div class="pf-field">
                    <label class="label" for="middle_name">Middle Name</label>
                    <input id="middle_name" name="middle_name" class="input" placeholder="Optional" value="{{ old('middle_name', $admin->middle_name) }}" />
                  </div>
                </div>

                <div class="pf-row">
                  <div class="pf-field">
                    <label class="label" for="last_name">Last Name</label>
                    <input id="last_name" name="last_name" class="input" placeholder="e.g. Dela Cruz" value="{{ old('last_name', $admin->last_name) }}" />
                  </div>
                  <div class="pf-field">
                    <label class="label" for="suffix">Suffix (optional)</label>
                    <input id="suffix" name="suffix" class="input" placeholder="e.g. Jr." value="{{ old('suffix', $admin->suffix) }}" />
                  </div>
                </div>

                <div class="pf-row">
                  <div class="pf-field">
                    <label class="label" for="credentials">Credentials</label>
                    <input id="credentials" name="credentials" class="input" placeholder="e.g. MIT, LPT" value="{{ old('credentials', $admin->credentials) }}" />
                  </div>
                  <div class="pf-field">
                    <label class="label" for="school_year">School Year</label>
                    <select name="school_year" id="school_year" class="input">
                      <option value="">— Select school year —</option>
                      @foreach ($semOptions as $code => $label)
                        <option value="{{ $code }}" {{ old('school_year', $schoolYear) === $code ? 'selected' : '' }}>{{ $label }}</option>
                      @endforeach
                    </select>
                  </div>
                </div>

                <div class="pf-row">
                  <div class="pf-field">
                    <label class="label">Program</label>
                    <div class="pf-field-static" style="background:var(--pf-surface);color:var(--pf-text2);padding:10px 12px;border:1.5px solid var(--pf-border);border-radius:10px;font-size:14px">{{ $admin->program ?? '—' }}</div>
                  </div>
                  <div class="pf-field">
                    <label class="label">Max Teams</label>
                    <div class="pf-field-static" style="background:var(--pf-surface);color:var(--pf-text2);padding:10px 12px;border:1.5px solid var(--pf-border);border-radius:10px;font-size:14px">{{ $admin->max_teams ?? '4' }}</div>
                  </div>
                </div>
              </div>

                @php $presetExpertise = ['Machine Learning','Web Development','Mobile Apps','Data Analytics','Cybersecurity','Network Systems','Database Systems','UI/UX Design','Technical Writing','Grammar & Style','Software Engineering','Research Methods']; @endphp
                {{-- Expertise --}}
              <div style="margin-top:18px;padding-top:18px;border-top:1px solid var(--pf-border)">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--pf-accent);flex-shrink:0"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                  <span style="font-size:13px;font-weight:600;color:var(--pf-text2)">Areas of Expertise</span>
                </div>

                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px" id="expertiseTags">
                  @foreach ($expertiseList ?? [] as $exp)
                    <div class="expertise-chip active" data-value="{{ $exp }}" style="display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:8px;font-size:12.5px;font-weight:500;border:1.5px solid var(--pf-accent);background:rgba(71,85,105,0.08);color:var(--pf-accent);user-select:none">
                      {{ $exp }}
                      <span onclick="removeExpertise(this)" style="cursor:pointer;opacity:0.6;display:flex;margin-left:2px" data-value="{{ $exp }}">&times;</span>
                    </div>
                  @endforeach
                </div>

                <div style="position:relative">
                  <div style="display:flex;gap:8px">
                    <div style="flex:1;position:relative">
                      <input type="text" id="expertiseSearchInput" placeholder="Search or add expertise..." autocomplete="off" style="width:100%;box-sizing:border-box;padding:9px 12px;border:1.5px solid var(--pf-border);border-radius:8px;font-size:13px;outline:none;font-family:'DM Sans',sans-serif;color:var(--pf-text)" onkeydown="handleExpertiseKeydown(event)" />
                      <div id="expertiseSuggestions" style="position:absolute;top:100%;left:0;right:0;background:#fff;border:1.5px solid var(--pf-border);border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,0.08);max-height:180px;overflow-y:auto;z-index:10;display:none;margin-top:4px"></div>
                    </div>
                    <button type="button" onclick="addExpertiseFromInput()" style="padding:9px 14px;border:1.5px solid var(--pf-border);border-radius:8px;background:#fff;color:var(--pf-text2);font-size:13px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;white-space:nowrap">Add</button>
                  </div>
                </div>

                <input type="hidden" name="expertise" id="expertiseInput" value="{{ json_encode($expertiseList ?? []) }}" />
              </div>
            </div>

            <div class="pf-form-footer" style="margin-top:18px;padding-top:16px;border-top:1px solid var(--pf-border);display:flex;justify-content:flex-end">
              <button type="submit" style="padding:9px 14px;border:1.5px solid var(--pf-border);border-radius:8px;background:#fff;color:var(--pf-text2);font-size:13px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif">Save Profile</button>
            </div>
          </div>

          {{-- Right column: assigned sections --}}
          <div class="pf-card" style="display:flex;flex-direction:column">
            <div class="pf-card-header" style="margin-bottom:0;padding-bottom:18px;border-bottom:1px solid var(--pf-border);display:flex;align-items:center;gap:12px">
              <div style="flex:1">
                <div class="pf-card-title" style="display:flex;align-items:center;gap:8px">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--pf-accent);flex-shrink:0"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
                  Assigned Sections
                </div>
                <div class="pf-card-desc" style="margin-top:2px">Choose which sections you manage</div>
              </div>
              <span class="pf-section-count" id="pf-section-count" style="font-size:12px;padding:5px 12px;background:rgba(71,85,105,0.08);color:var(--pf-accent);border-radius:8px;font-weight:600;white-space:nowrap;font-family:'DM Sans',sans-serif">{{ count($sectionList) }} / {{ count($allAvailableSections) }}</span>
            </div>

            <div style="flex:1;display:flex;flex-direction:column;min-height:0;padding-top:16px">
              @if (count($allAvailableSections) > 0)
                <div class="pf-search" style="margin-bottom:14px">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                  <input type="text" id="pf-section-search" placeholder="Search sections…" autocomplete="off" />
                </div>
              @endif

              <div class="pf-sections-list" id="pf-sections-list" style="flex:1;min-height:0;max-height:320px;display:flex;flex-direction:column;gap:6px;padding-right:4px">
                @forelse ($allAvailableSections as $section)
                  <label class="pf-section-opt" data-section-name="{{ strtolower($section) }}" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid var(--pf-border);border-radius:10px;cursor:pointer;transition:all .15s;background:#fff">
                    <input type="checkbox" name="sections[]" value="{{ $section }}" class="pf-section-checkbox" {{ in_array($section, $sectionList) ? 'checked' : '' }} style="width:16px;height:16px;accent-color:var(--pf-accent);flex-shrink:0" />
                    <span style="font-size:13.5px;font-weight:500;color:var(--pf-text)">{{ $section }}</span>
                  </label>
                @empty
                  <div class="pf-empty">No sections available.</div>
                @endforelse
              </div>
            </div>

            <div class="pf-form-footer" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--pf-border);display:flex;justify-content:flex-end">
              <button type="submit" style="padding:9px 14px;border:1.5px solid var(--pf-border);border-radius:8px;background:#fff;color:var(--pf-text2);font-size:13px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif">Update Sections</button>
            </div>
          </div>

        </div>
      </form>

      @if ($admin->roles->pluck('role_name')->contains('capstone_adviser'))
        <div class="pf-card" style="margin-top:24px">
          <div class="pf-card-header" style="margin-bottom:0;padding-bottom:18px;border-bottom:1px solid var(--pf-border);display:flex;align-items:center;gap:12px">
            <div style="flex:1">
              <div class="pf-card-title" style="display:flex;align-items:center;gap:8px">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--pf-accent);flex-shrink:0"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                Submission Folders
              </div>
              <div class="pf-card-desc" style="margin-top:2px">Name folders here so students can pick the right one when they submit to you as Subject Adviser</div>
            </div>
          </div>

          <div style="padding-top:16px">
            <div style="display:flex;gap:8px;margin-bottom:16px">
              <input type="text" id="newFolderName" placeholder="e.g. Batch 2026 - Section A" style="flex:1;padding:9px 12px;border:1.5px solid var(--pf-border);border-radius:8px;font-size:13.5px;font-family:'DM Sans',sans-serif" />
              <button type="button" id="addFolderBtn" style="padding:9px 16px;border:none;border-radius:8px;background:var(--pf-accent);color:#fff;font-size:13px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;white-space:nowrap">Add Folder</button>
            </div>
            <div id="folderListContainer" style="display:flex;flex-direction:column;gap:6px">
              <div class="pf-empty" id="folderListEmpty">Loading…</div>
            </div>
          </div>
        </div>
      @endif

    </div>
  </div>
  </div>
</div>
@include('capsa.partials.skeleton-toggle')

<script>
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() {
  sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar();
}
function openSidebar() {
  sidebarEl.classList.add('mobile-open');
  sidebarOverlay.classList.add('open');
}
function closeSidebar() {
  sidebarEl.classList.remove('mobile-open');
  sidebarOverlay.classList.remove('open');
}

// Avatar preview & reset
var avatarInput = document.getElementById('avatar');
var avatarPreviewRoot = document.getElementById('avatar-preview');
function readAvatar(file) {
  var reader = new FileReader();
  reader.onload = function(e) {
    var img = document.createElement('img');
    img.src = e.target.result;
    img.alt = '{{ $admin->name }}';
    img.className = 'app-avatar';
    img.style.objectFit = 'cover';
    avatarPreviewRoot.innerHTML = '';
    avatarPreviewRoot.appendChild(img);
  };
  reader.readAsDataURL(file);
}

if (avatarInput) {
  avatarInput.addEventListener('change', function(e) {
    var f = e.target.files && e.target.files[0];
    if (!f) return;
    readAvatar(f);
  });
}

function resetAvatar() {
  if (!avatarInput) return;
  avatarInput.value = '';
  avatarPreviewRoot.innerHTML = '';
  var img = document.createElement('img');
  img.src = '{{ asset('images/default-avatar.jpg') }}';
  img.alt = 'Default avatar';
  img.className = 'app-avatar';
  img.style.objectFit = 'cover';
  avatarPreviewRoot.appendChild(img);
}

// Section search filter (display-only; does not change submitted values)
var sectionSearch = document.getElementById('pf-section-search');
if (sectionSearch) {
  sectionSearch.addEventListener('input', function (e) {
    var q = e.target.value.trim().toLowerCase();
    document.querySelectorAll('#pf-sections-list .pf-section-opt').forEach(function (opt) {
      var name = opt.getAttribute('data-section-name') || '';
      opt.style.display = name.indexOf(q) !== -1 ? '' : 'none';
    });
  });
}

// Live selected-sections counter
var sectionCheckboxes = document.querySelectorAll('.pf-section-checkbox');
var sectionCountEl = document.getElementById('pf-section-count');
if (sectionCheckboxes.length && sectionCountEl) {
  var total = sectionCheckboxes.length;
  sectionCheckboxes.forEach(function (cb) {
    cb.addEventListener('change', function () {
      var selected = document.querySelectorAll('.pf-section-checkbox:checked').length;
      sectionCountEl.textContent = selected + ' / ' + total;
    });
  });
}

// Expertise management
var expertiseInput = document.getElementById('expertiseInput');
var expertiseTags = document.getElementById('expertiseTags');
var expertiseSearch = document.getElementById('expertiseSearchInput');
var expertiseSuggestions = document.getElementById('expertiseSuggestions');
var presetExpertise = {{ Js::from($presetExpertise ?? []) }};

function getExpertiseValues() {
  try { return JSON.parse(expertiseInput.value || '[]'); } catch(e) { return []; }
}

function setExpertiseValues(arr) {
  expertiseInput.value = JSON.stringify(arr);
}

function removeExpertise(el) {
  var val = el.getAttribute('data-value');
  var values = getExpertiseValues();
  var idx = values.indexOf(val);
  if (idx !== -1) values.splice(idx, 1);
  setExpertiseValues(values);
  el.closest('.expertise-chip').remove();
}

function addExpertiseChip(val) {
  var values = getExpertiseValues();
  if (values.indexOf(val) !== -1) return;
  var chip = document.createElement('div');
  chip.className = 'expertise-chip active';
  chip.style.cssText = 'display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:8px;font-size:12.5px;font-weight:500;border:1.5px solid var(--pf-accent);background:rgba(71,85,105,0.08);color:var(--pf-accent);user-select:none';
  chip.innerHTML = val + '<span onclick="removeExpertise(this)" style="cursor:pointer;opacity:0.6;display:flex;margin-left:2px" data-value="' + val.replace(/"/g, '&quot;') + '">&times;</span>';
  expertiseTags.appendChild(chip);
  values.push(val);
  setExpertiseValues(values);
}

function addExpertiseFromInput() {
  var val = expertiseSearch.value.trim();
  if (!val) return;
  addExpertiseChip(val);
  expertiseSearch.value = '';
  closeSuggestions();
}

function handleExpertiseKeydown(e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    var active = expertiseSuggestions.querySelector('.active');
    if (active) {
      addExpertiseChip(active.dataset.value);
    } else {
      addExpertiseFromInput();
    }
    expertiseSearch.value = '';
    closeSuggestions();
  }
  if (e.key === 'Escape') closeSuggestions();
  if (e.key === 'ArrowDown') { e.preventDefault(); moveSuggestion(1); }
  if (e.key === 'ArrowUp') { e.preventDefault(); moveSuggestion(-1); }
}

function moveSuggestion(dir) {
  var items = expertiseSuggestions.querySelectorAll('.expertise-suggestion');
  if (!items.length) return;
  var activeIdx = -1;
  items.forEach(function(item, i) { if (item.classList.contains('active')) activeIdx = i; });
  items[activeIdx]?.classList.remove('active');
  activeIdx = (activeIdx + dir + items.length) % items.length;
  items[activeIdx].classList.add('active');
  items[activeIdx].scrollIntoView({ block: 'nearest' });
}

function closeSuggestions() {
  expertiseSuggestions.style.display = 'none';
  expertiseSuggestions.innerHTML = '';
}

// Search suggestions
if (expertiseSearch) {
  expertiseSearch.addEventListener('input', function() {
    var q = this.value.trim().toLowerCase();
    if (!q) { closeSuggestions(); return; }
    var values = getExpertiseValues();
    var filtered = presetExpertise.filter(function(p) {
      return p.toLowerCase().indexOf(q) !== -1 && values.indexOf(p) === -1;
    });
    if (!filtered.length) { closeSuggestions(); return; }
    expertiseSuggestions.innerHTML = '';
    filtered.forEach(function(p, i) {
      var div = document.createElement('div');
      div.className = 'expertise-suggestion' + (i === 0 ? ' active' : '');
      div.dataset.value = p;
      div.textContent = p;
      div.style.cssText = 'padding:8px 12px;font-size:13px;cursor:pointer;transition:background .1s';
      div.onmouseenter = function() {
        expertiseSuggestions.querySelectorAll('.expertise-suggestion').forEach(function(el) { el.classList.remove('active'); });
        this.classList.add('active');
      };
      div.onclick = function() {
        addExpertiseChip(this.dataset.value);
        expertiseSearch.value = '';
        closeSuggestions();
        expertiseSearch.focus();
      };
      expertiseSuggestions.appendChild(div);
    });
    expertiseSuggestions.style.display = 'block';
  });

  expertiseSearch.addEventListener('blur', function() {
    setTimeout(closeSuggestions, 200);
  });

  expertiseSearch.addEventListener('focus', function() {
    if (this.value.trim()) {
      this.dispatchEvent(new Event('input'));
    }
  });
}

// -- Submission Folders (Subject Adviser only) --
var folderListContainer = document.getElementById('folderListContainer');
if (folderListContainer) {
  var FOLDER_CSRF = document.querySelector('meta[name="csrf-token"]').content;
  var folderListEmpty = document.getElementById('folderListEmpty');
  var newFolderInput = document.getElementById('newFolderName');
  var addFolderBtn = document.getElementById('addFolderBtn');

  function renderFolders(folders) {
    folderListContainer.querySelectorAll('.folder-row').forEach(function (el) { el.remove(); });
    if (!folders.length) {
      folderListEmpty.textContent = 'No folders yet — add one above.';
      folderListEmpty.style.display = '';
      return;
    }
    folderListEmpty.style.display = 'none';
    folders.forEach(function (folder) {
      var row = document.createElement('div');
      row.className = 'folder-row';
      row.style.cssText = 'display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid var(--pf-border);border-radius:10px;background:#fff';
      row.innerHTML =
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--pf-accent);flex-shrink:0"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>' +
        '<span style="flex:1;font-size:13.5px;font-weight:500;color:var(--pf-text)">' + folder.name.replace(/</g, '&lt;') + '</span>' +
        '<button type="button" class="folder-delete-btn" data-id="' + folder.id + '" style="border:none;background:none;color:var(--pf-text2);cursor:pointer;padding:4px" title="Delete folder">' +
        '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>' +
        '</button>';
      folderListContainer.appendChild(row);
    });
  }

  function loadFolders() {
    fetch("{{ route('gdrive.submission-folders.index') }}")
      .then(function (r) { return r.json(); })
      .then(function (data) { renderFolders(data.folders || []); })
      .catch(function () {
        folderListEmpty.textContent = 'Could not load folders.';
      });
  }

  addFolderBtn.addEventListener('click', function () {
    var name = newFolderInput.value.trim();
    if (!name) return;
    addFolderBtn.disabled = true;
    addFolderBtn.textContent = 'Adding…';
    fetch("{{ route('gdrive.submission-folders.store') }}", {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': FOLDER_CSRF, 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: name }),
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        addFolderBtn.disabled = false;
        addFolderBtn.textContent = 'Add Folder';
        if (!data.success) {
          alert(data.message || 'Could not create folder.');
          return;
        }
        newFolderInput.value = '';
        loadFolders();
      })
      .catch(function () {
        addFolderBtn.disabled = false;
        addFolderBtn.textContent = 'Add Folder';
        alert('Could not create folder.');
      });
  });

  folderListContainer.addEventListener('click', function (e) {
    var btn = e.target.closest('.folder-delete-btn');
    if (!btn) return;
    if (!confirm('Delete this folder? Students will no longer be able to pick it.')) return;
    fetch("{{ url('/admin/gdrive/submission-folders') }}/" + btn.dataset.id, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': FOLDER_CSRF },
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) {
          alert(data.message || 'Could not delete folder.');
          return;
        }
        loadFolders();
      })
      .catch(function () {
        alert('Could not delete folder.');
      });
  });

  loadFolders();
}
</script>

</body>
</html>