<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>My Profile - CapsTRACK</title>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>

<style nonce="{{ Vite::cspNonce() }}">
  /* ============================================================
     My Profile (CaPSA/TSA / Admin). Built on admin.css tokens, scoped
     to .profile-page. One form, one Save: details, research areas
     and sections are saved together.
     ============================================================ */
  .profile-page {
    --pf-accent: var(--accent, #c9a82c);
    --pf-accent-soft: rgba(201, 168, 44, 0.12);
    --pf-ink: #475569;
    --pf-text: var(--text, #101828);
    --pf-text2: var(--text2, #344054);
    --pf-text3: var(--text3, #98a2b3);
    --pf-border: var(--border, #e4e7ec);
    --pf-surface: var(--bg3, #f8fafc);
    --pf-green: var(--green, #16a34a);
    --pf-radius: 16px;
    --pf-shadow: 0 1px 2px rgba(16, 24, 40, 0.04), 0 1px 3px rgba(16, 24, 40, 0.03);
    max-width: 1080px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 18px;
  }

  /* Messages */
  .profile-page .pf-status {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px; border-radius: 12px; font-size: 13.5px;
    background: rgba(22, 163, 74, 0.07); border: 1px solid rgba(22, 163, 74, 0.18); color: var(--pf-green);
  }
  .profile-page .pf-status.is-error { background: #fdecea; color: #b42318; border-color: #f5c2c0; }

  /* Cards */
  .profile-page .pf-card {
    background: #fff; border: 1px solid var(--pf-border); border-radius: var(--pf-radius);
    box-shadow: var(--pf-shadow); padding: 20px 22px;
  }
  .profile-page .pf-card-head { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 16px; }
  .profile-page .pf-card-ic {
    width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0; display: grid; place-items: center;
    background: var(--pf-surface); color: var(--pf-ink); border: 1px solid var(--pf-border);
  }
  .profile-page .pf-card-ic svg { width: 17px; height: 17px; }
  .profile-page .pf-card-head > div { flex: 1; min-width: 0; }
  .profile-page .pf-card-title { font-size: 15px; font-weight: 700; color: var(--pf-text); letter-spacing: -0.01em; margin: 0; }
  .profile-page .pf-card-desc { font-size: 12.5px; color: var(--pf-text3); margin: 2px 0 0; line-height: 1.45; }
  .profile-page .pf-count {
    font-variant-numeric: tabular-nums; font-size: 12px; font-weight: 700; white-space: nowrap;
    padding: 4px 10px; border-radius: 999px; background: var(--pf-surface); color: var(--pf-ink); border: 1px solid var(--pf-border);
  }

  /* Hero */
  .profile-page .pf-hero { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
  .profile-page .pf-avatar-well { flex-shrink: 0; }
  .profile-page .app-avatar,
  .profile-page .pf-avatar-well img {
    width: 76px; height: 76px; border-radius: 20px; object-fit: cover; display: block;
    box-shadow: 0 0 0 4px #fff, 0 0 0 5px var(--pf-border);
  }
  .profile-page .pf-hero-meta { flex: 1 1 260px; min-width: 0; }
  .profile-page .pf-eyebrow { font-size: 11px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--pf-text3); margin: 0 0 4px; }
  .profile-page .pf-name { font-size: 20px; font-weight: 700; color: var(--pf-text); line-height: 1.2; letter-spacing: -0.01em; margin: 0; }
  .profile-page .pf-email { font-size: 13px; color: var(--pf-text3); margin-top: 3px; word-break: break-word; }
  .profile-page .pf-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
  .profile-page .pf-chip {
    display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 600;
    padding: 4px 10px; border-radius: 999px; background: var(--pf-surface); color: var(--pf-text2); border: 1px solid var(--pf-border);
  }
  .profile-page .pf-chip.is-role { background: var(--pf-accent-soft); color: #7d630e; border-color: rgba(201, 168, 44, 0.35); }
  .profile-page .pf-chip svg { width: 12px; height: 12px; }

  /* Two columns: what you edit, and your sections */
  .profile-page .pf-layout { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(0, 1fr); gap: 18px; align-items: start; }
  .profile-page .pf-main, .profile-page .pf-aside { display: flex; flex-direction: column; gap: 18px; min-width: 0; }
  .profile-page .pf-aside { position: sticky; top: 16px; }

  /* Fields */
  .profile-page .pf-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 14px; }
  .profile-page .pf-field label.label { font-size: 12.5px; font-weight: 600; color: var(--pf-text2); margin-bottom: 6px; display: block; }
  .profile-page .pf-field .input {
    width: 100%; box-sizing: border-box; font: inherit; font-size: 14px; padding: 10px 12px; border-radius: 10px;
    border: 1.5px solid var(--pf-border); color: var(--pf-text); background: #fff; transition: border-color .15s, box-shadow .15s;
  }
  .profile-page .pf-field .input:focus { outline: none; border-color: var(--pf-ink); box-shadow: 0 0 0 3px rgba(71, 85, 105, 0.12); }
  .profile-page .pf-facts { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 16px 0 0; padding: 12px 14px; border-radius: 12px; background: var(--pf-surface); border: 1px solid var(--pf-border); }
  .profile-page .pf-facts dt { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--pf-text3); }
  .profile-page .pf-facts dd { margin: 3px 0 0; font-size: 13.5px; font-weight: 600; color: var(--pf-text); }

  /* Research areas */
  .profile-page .pf-side-tabs { display: flex; gap: 3px; padding: 3px; margin-bottom: 12px; background: var(--pf-surface); border: 1px solid var(--pf-border); border-radius: 11px; width: fit-content; max-width: 100%; flex-wrap: wrap; }
  .profile-page .pf-side-tab { display: inline-flex; align-items: center; gap: 6px; min-height: 32px; padding: 0 12px; border: 0; border-radius: 8px; background: transparent; color: var(--pf-text2); font: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; }
  .profile-page .pf-side-tab.on { background: #fff; color: var(--pf-text); box-shadow: 0 1px 3px rgba(24, 25, 31, .14); }
  .profile-page .pf-side-n { font-size: 10.5px; font-weight: 700; padding: 0 6px; border-radius: 999px; background: rgba(100, 116, 139, .16); }
  .profile-page .pf-side-tab.on .pf-side-n { background: var(--pf-accent); color: #18191f; }
  .profile-page #expertiseTags { display: flex; flex-wrap: wrap; gap: 6px; min-height: 34px; margin-bottom: 12px; }
  .profile-page #expertiseTags:empty::before { content: 'Nothing added for this side yet.'; font-size: 12.5px; color: var(--pf-text3); align-self: center; }
  .profile-page .expertise-chip.active { border-color: var(--pf-ink) !important; background: rgba(71, 85, 105, 0.07) !important; color: var(--pf-ink) !important; }
  .profile-page .pf-add { display: flex; gap: 8px; }
  .profile-page .pf-add-input { flex: 1; position: relative; }
  .profile-page .pf-add-input input {
    width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1.5px solid var(--pf-border); border-radius: 10px;
    font: inherit; font-size: 13.5px; color: var(--pf-text); outline: none; background: #fff;
  }
  .profile-page .pf-add-input input:focus { border-color: var(--pf-ink); box-shadow: 0 0 0 3px rgba(71, 85, 105, 0.12); }
  .profile-page #expertiseSuggestions {
    position: absolute; top: 100%; left: 0; right: 0; margin-top: 4px; z-index: 10; display: none;
    background: #fff; border: 1.5px solid var(--pf-border); border-radius: 10px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); max-height: 200px; overflow-y: auto;
  }

  /* Buttons */
  .profile-page .pf-btn {
    font: inherit; font-size: 13px; font-weight: 600; padding: 9px 14px; border-radius: 10px; white-space: nowrap;
    border: 1.5px solid var(--pf-border); background: #fff; color: var(--pf-text2); cursor: pointer; transition: border-color .15s, background .15s, color .15s;
  }
  .profile-page .pf-btn:hover { border-color: var(--pf-ink); color: var(--pf-text); }
  .profile-page .pf-btn-primary { background: var(--pf-accent); border-color: var(--pf-accent); color: #18191f; font-weight: 700; padding: 10px 18px; }
  .profile-page .pf-btn-primary:hover { filter: brightness(1.05); color: #18191f; border-color: var(--pf-accent); }
  .profile-page .pf-btn-dark { background: var(--pf-ink); border-color: var(--pf-ink); color: #fff; }
  .profile-page .pf-btn-dark:hover { filter: brightness(1.1); color: #fff; }

  /* Sections */
  .profile-page .pf-search { position: relative; margin-bottom: 12px; }
  .profile-page .pf-search input {
    width: 100%; box-sizing: border-box; font: inherit; font-size: 13.5px; padding: 9px 12px 9px 34px; border-radius: 10px;
    border: 1.5px solid var(--pf-border); background: var(--pf-surface); color: var(--pf-text);
  }
  .profile-page .pf-search input:focus { outline: none; border-color: var(--pf-ink); background: #fff; }
  .profile-page .pf-search svg { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--pf-text3); }
  .profile-page .pf-sections-list { display: flex; flex-direction: column; gap: 6px; max-height: 340px; overflow-y: auto; padding-right: 2px; }
  .profile-page .pf-section-opt {
    display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1.5px solid var(--pf-border); border-radius: 10px;
    cursor: pointer; background: #fff; transition: border-color .15s, background .15s;
  }
  .profile-page .pf-section-opt:hover { border-color: var(--pf-ink); }
  .profile-page .pf-section-opt:has(input:checked) { border-color: var(--pf-accent); background: var(--pf-accent-soft); }
  .profile-page .pf-section-opt input[type="checkbox"] { width: 16px; height: 16px; accent-color: #7d630e; flex-shrink: 0; margin: 0; }
  .profile-page .pf-section-opt span { font-size: 13.5px; font-weight: 600; color: var(--pf-text); }
  .profile-page .pf-empty { padding: 22px 12px; text-align: center; color: var(--pf-text3); font-size: 13px; border: 1.5px dashed var(--pf-border); border-radius: 10px; }

  /* The one Save */
  .profile-page .pf-savebar {
    display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
    margin-top: 18px; padding: 12px 14px 12px 18px; border-radius: 14px; background: #fff;
    border: 1px solid var(--pf-border); box-shadow: var(--pf-shadow);
  }
  .profile-page .pf-savebar span { font-size: 12.5px; color: var(--pf-text3); }

  /* Tabs */
  .profile-page .pf-tabs { display: flex; gap: 4px; padding: 4px; margin: 18px 0 0; background: #fff; border: 1px solid var(--pf-border); border-radius: 14px; box-shadow: var(--pf-shadow); overflow-x: auto; scrollbar-width: none; }
  .profile-page .pf-tabs::-webkit-scrollbar { display: none; }
  .profile-page .pf-tab { flex: 1 0 auto; display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 40px; padding: 0 16px; border: 0; border-radius: 10px; background: transparent; color: var(--pf-text2); font: inherit; font-size: 13.5px; font-weight: 700; cursor: pointer; white-space: nowrap; transition: background .15s, color .15s; }
  .profile-page .pf-tab:hover { color: var(--pf-text); background: var(--pf-surface); }
  .profile-page .pf-tab.on { background: var(--pf-ink); color: #fff; }
  .profile-page .pf-tab-n { font-size: 11px; font-weight: 700; padding: 1px 7px; border-radius: 999px; background: rgba(100, 116, 139, .16); }
  .profile-page .pf-tab.on .pf-tab-n { background: var(--pf-accent); color: #18191f; }
  .profile-page .pf-panel[hidden], .profile-page .pf-savebar[hidden] { display: none !important; }
  /* Panels inside the form sit 18px under the tabs; the ones after it get the page's own gap. */
  .profile-page form .pf-panel { margin-top: 18px; }
  .profile-page #pf-panel-research .pf-card { max-width: 760px; }

  /* Below the form */
  .profile-page .pf-lower { display: grid; grid-template-columns: 1fr; gap: 18px; align-items: start; }
  .profile-page .pf-notif .notif-prefs { max-width: none; border-radius: var(--pf-radius); padding: 20px 22px; }

  @media (max-width: 900px) {
    .profile-page .pf-layout { grid-template-columns: 1fr; }
    .profile-page .pf-aside { position: static; }
  }
  @media (max-width: 560px) {
    .profile-page .pf-grid, .profile-page .pf-facts { grid-template-columns: 1fr; }
    .profile-page .pf-card { padding: 16px; }
    .profile-page .pf-hero { gap: 14px; align-items: flex-start; }
    .profile-page .pf-name { font-size: 18px; }
    .profile-page .app-avatar, .profile-page .pf-avatar-well img { width: 60px; height: 60px; border-radius: 16px; }
    .profile-page .pf-savebar .pf-btn-primary { width: 100%; }
  }
</style>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
  @include('partials.render-whole')
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

      @if ($errors->any())
        <div class="pf-status is-error" role="alert"><span>{{ $errors->first() }}</span></div>
      @endif

      @if (session('status'))
        <div class="pf-status" role="status">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
          <span>{{ session('status') }}</span>
        </div>
      @endif

      @php
        $viewer = auth()->user();
        $avatarUrl = '';

        foreach ([$viewer, $admin] as $who) {
          if ($avatarUrl || ! $who) { continue; }
          if (!empty($who->profile_picture) && filter_var($who->profile_picture, FILTER_VALIDATE_URL)) {
            $avatarUrl = $who->profile_picture;
          } elseif (!empty($who->avatar) && filter_var($who->avatar, FILTER_VALIDATE_URL)) {
            $avatarUrl = $who->avatar;
          } elseif (method_exists($who, 'avatarUrl') && $who->avatarUrl()) {
            $avatarUrl = $who->avatarUrl();
          }
        }

        $initials = method_exists($admin, 'avatarInitials') ? $admin->avatarInitials() : strtoupper(substr($admin->name ?? 'U', 0, 2));
        $roleLabels = $admin->roles->pluck('role_name')->map(fn ($r) => \App\Models\Role::labelFor($r))->unique()->values();
        $schoolYearLabel = $schoolYear ? 'School year '.$schoolYear : null;

        // The page in tabs, so nothing is a long scroll away. ?tab= picks one,
        // and a save comes back to the tab it was made from.
        $pfTabs = array_filter([
          'details' => 'Details',
          'research' => 'Research areas',
          'notifications' => 'Notifications',
        ]);
        $pfTab = array_key_exists(request('tab'), $pfTabs) ? request('tab') : 'details';
        // A validation error belongs to the form, so the form's tab shows it.
        if ($errors->any() && ! in_array($pfTab, ['details', 'research'], true)) { $pfTab = 'details'; }
      @endphp

      <form method="POST" action="{{ route('capsa.profile.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        {{-- Who this is, at a glance. --}}
        <section class="pf-card pf-hero">
          <div class="pf-avatar-well" id="avatar-preview" data-initials="{{ $initials }}">
            <img src="{{ $avatarUrl ?: asset('images/default-avatar.jpg') }}" alt="{{ $admin->name ?? 'User' }}" class="app-avatar" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='{{ asset('images/default-avatar.jpg') }}';" />
          </div>
          <div class="pf-hero-meta">
            <p class="pf-eyebrow">My profile</p>
            <h1 class="pf-name">{{ $admin->name }}</h1>
            <div class="pf-email">{{ $admin->email }}</div>
            <div class="pf-chips">
              @foreach ($roleLabels as $label)
                <span class="pf-chip is-role">{{ $label }}</span>
              @endforeach
              @if ($admin->program)
                <span class="pf-chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/></svg>{{ $admin->program }}</span>
              @endif
              <span class="pf-chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>{{ count($sectionList) }} {{ \Illuminate\Support\Str::plural('section', count($sectionList)) }}</span>
              @if ($schoolYearLabel)
                <span class="pf-chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>{{ $schoolYearLabel }}</span>
              @endif
            </div>
          </div>
          <input type="file" id="avatar" name="avatar" accept="image/*" hidden />
        </section>

        <input type="hidden" name="tab" id="pf-tab-input" value="{{ $pfTab }}" />
        <nav class="pf-tabs" role="tablist" aria-label="Profile sections">
          @foreach ($pfTabs as $key => $label)
            <button type="button" role="tab" class="pf-tab{{ $pfTab === $key ? ' on' : '' }}" data-pf-tab="{{ $key }}" aria-selected="{{ $pfTab === $key ? 'true' : 'false' }}" aria-controls="pf-panel-{{ $key }}">
              {{ $label }}
              @if ($key === 'research')<span class="pf-tab-n">{{ collect($expertiseBySide ?? [])->flatten()->unique()->count() }}</span>@endif
            </button>
          @endforeach
        </nav>

        <div class="pf-panel" id="pf-panel-details" data-pf-panel="details" role="tabpanel" @if ($pfTab !== 'details') hidden @endif>
        <div class="pf-layout">
          <div class="pf-main">

            {{-- Personal information --}}
            <section class="pf-card">
              <div class="pf-card-head">
                <span class="pf-card-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                <div>
                  <h2 class="pf-card-title">Personal information</h2>
                  <p class="pf-card-desc">How your name appears to students, experts and on reports.</p>
                </div>
              </div>

              <div class="pf-grid">
                <div class="pf-field">
                  <label class="label" for="first_name">First name</label>
                  <input id="first_name" name="first_name" maxlength="50" class="input" placeholder="e.g. Juan" value="{{ old('first_name', $admin->first_name) }}" />
                </div>
                <div class="pf-field">
                  <label class="label" for="middle_name">Middle name</label>
                  <input id="middle_name" name="middle_name" maxlength="50" class="input" placeholder="Optional" value="{{ old('middle_name', $admin->middle_name) }}" />
                </div>
                <div class="pf-field">
                  <label class="label" for="last_name">Last name</label>
                  <input id="last_name" name="last_name" maxlength="50" class="input" placeholder="e.g. Dela Cruz" value="{{ old('last_name', $admin->last_name) }}" />
                </div>
                <div class="pf-field">
                  <label class="label" for="suffix">Suffix</label>
                  <input id="suffix" name="suffix" maxlength="50" class="input" placeholder="Optional, e.g. Jr." value="{{ old('suffix', $admin->suffix) }}" />
                </div>
                <div class="pf-field">
                  <label class="label" for="credentials">Credentials</label>
                  <input id="credentials" name="credentials" maxlength="50" class="input" placeholder="e.g. MIT, LPT" value="{{ old('credentials', $admin->credentials) }}" />
                </div>
              </div>

              {{-- Set elsewhere, shown for reference. --}}
              <dl class="pf-facts">
                <div><dt>Program</dt><dd>{{ $admin->program ?: 'Not set' }}</dd></div>
                <div><dt>Max teams</dt><dd>{{ $admin->max_teams ?: 'Not set' }}</dd></div>
              </dl>
            </section>

          </div>

          <aside class="pf-aside">
            {{-- Assigned sections --}}
            <section class="pf-card">
              <div class="pf-card-head">
                <span class="pf-card-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg></span>
                <div>
                  <h2 class="pf-card-title">Assigned sections</h2>
                  <p class="pf-card-desc">The sections whose teams you manage.</p>
                </div>
              </div>

              {{-- Set by the Admin in Expert Management; shown here for reference. --}}
              <div class="pf-sections-list">
                @forelse ($sectionList as $section)
                  <div class="pf-section-opt"><span>{{ $section }}</span></div>
                @empty
                  <div class="pf-empty">No sections yet. The administrator assigns them.</div>
                @endforelse
              </div>
              <p class="pf-card-desc" style="margin-top:10px">Set by the administrator.</p>
            </section>
          </aside>
        </div>
        </div>

        <div class="pf-panel" id="pf-panel-research" data-pf-panel="research" role="tabpanel" @if ($pfTab !== 'research') hidden @endif>
            {{-- Research areas, one list per side --}}
            @php $presetExpertise = ['Machine Learning','Web Development','Mobile Apps','Data Analytics','Cybersecurity','Network Systems','Database Systems','UI/UX Design','Technical Writing','Grammar & Style','Software Engineering','Research Methods']; @endphp
            <section class="pf-card">
              <div class="pf-card-head">
                <span class="pf-card-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span>
                <div>
                  <h2 class="pf-card-title">Research areas</h2>
                  <p class="pf-card-desc">Kept separately for each expert role you hold and for defense panels. Students see the one for the role they are looking for.</p>
                </div>
              </div>

              <div class="pf-side-tabs" role="tablist" aria-label="Research areas for">
                @foreach ($expertSides ?? [] as $side => $label)
                  <button type="button" role="tab" class="pf-side-tab{{ $loop->first ? ' on' : '' }}" data-side="{{ $side }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" onclick="selectExpertiseSide('{{ $side }}')">
                    as {{ $label }} <span class="pf-side-n" data-side-count="{{ $side }}">{{ count($expertiseBySide[$side] ?? []) }}</span>
                  </button>
                @endforeach
              </div>

              <div id="expertiseTags"></div>

              <div class="pf-add">
                <div class="pf-add-input">
                  <input type="text" id="expertiseSearchInput" placeholder="Search or type a research area, then press Enter" autocomplete="off" onkeydown="handleExpertiseKeydown(event)" />
                  <div id="expertiseSuggestions"></div>
                </div>
                <button type="button" class="pf-btn" onclick="addExpertiseFromInput()">Add</button>
              </div>

              <input type="hidden" name="expertise_by_side" id="expertiseInput" value="{{ json_encode((object) ($expertiseBySide ?? [])) }}" />
            </section>
        </div>

        {{-- One Save for the Details and Research areas tabs. --}}
        <div class="pf-savebar" data-pf-save @if (! in_array($pfTab, ['details', 'research'], true)) hidden @endif>
          <span>Your details, research areas and sections are saved together.</span>
          <button type="submit" class="pf-btn pf-btn-primary">Save changes</button>
        </div>
      </form>

      <div class="pf-panel pf-notif" id="pf-panel-notifications" data-pf-panel="notifications" role="tabpanel" @if ($pfTab !== 'notifications') hidden @endif>
        @include('partials.notification-settings')
      </div>

    </div>
  </div>
  </div>
</div>
@include('capsa.partials.skeleton-toggle')

<script nonce="{{ Vite::cspNonce() }}">
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

// Tabs: one part of the profile at a time, remembered in the address so a
// reload or a save comes back to it.
(function () {
  var tabs = document.querySelectorAll('.pf-tab');
  if (!tabs.length) return;
  var tabInput = document.getElementById('pf-tab-input');
  var saveBar = document.querySelector('[data-pf-save]');
  function show(key, focus) {
    tabs.forEach(function (t) {
      var on = t.dataset.pfTab === key;
      t.classList.toggle('on', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      t.tabIndex = on ? 0 : -1;
      if (on && focus) t.focus();
    });
    document.querySelectorAll('[data-pf-panel]').forEach(function (p) { p.hidden = p.dataset.pfPanel !== key; });
    if (saveBar) saveBar.hidden = key !== 'details' && key !== 'research';
    if (tabInput) tabInput.value = key;
    try {
      var url = new URL(window.location.href);
      url.searchParams.set('tab', key);
      history.replaceState(null, '', url.pathname + url.search);
    } catch (e) { /* the tab still changes */ }
  }
  tabs.forEach(function (t, i) {
    t.addEventListener('click', function () { show(t.dataset.pfTab); });
    t.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
      e.preventDefault();
      var next = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
      show(next.dataset.pfTab, true);
    });
  });
})();

// Expertise management
var expertiseInput = document.getElementById('expertiseInput');
var expertiseTags = document.getElementById('expertiseTags');
var expertiseSearch = document.getElementById('expertiseSearchInput');
var expertiseSuggestions = document.getElementById('expertiseSuggestions');
var presetExpertise = {{ Js::from($presetExpertise ?? []) }};

// Research areas per side; the chips show the side whose tab is on.
var expertiseState = (function () { try { return JSON.parse(expertiseInput.value || '{}') || {}; } catch (e) { return {}; } })();
var expertiseSide = (document.querySelector('.pf-side-tab.on') || {}).dataset ? document.querySelector('.pf-side-tab.on').dataset.side : 'panel';

function getExpertiseValues() {
  return (expertiseState[expertiseSide] || []).slice();
}

function setExpertiseValues(arr) {
  expertiseState[expertiseSide] = arr;
  expertiseInput.value = JSON.stringify(expertiseState);
  var n = document.querySelector('[data-side-count="' + expertiseSide + '"]');
  if (n) n.textContent = arr.length;
}

function expertiseChip(val) {
  var chip = document.createElement('div');
  chip.className = 'expertise-chip active';
  chip.dataset.value = val;
  chip.style.cssText = 'display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:8px;font-size:12.5px;font-weight:500;border:1.5px solid var(--pf-accent);background:rgba(71,85,105,0.08);color:var(--pf-accent);user-select:none';
  chip.appendChild(document.createTextNode(val));
  var x = document.createElement('span');
  x.setAttribute('onclick', 'removeExpertise(this)');
  x.style.cssText = 'cursor:pointer;opacity:0.6;display:flex;margin-left:2px';
  x.dataset.value = val;
  x.innerHTML = '&times;';
  chip.appendChild(x);
  return chip;
}

function renderExpertiseChips() {
  expertiseTags.innerHTML = '';
  getExpertiseValues().forEach(function (val) { expertiseTags.appendChild(expertiseChip(val)); });
}

function selectExpertiseSide(side) {
  expertiseSide = side;
  document.querySelectorAll('.pf-side-tab').forEach(function (b) {
    var on = b.dataset.side === side;
    b.classList.toggle('on', on);
    b.setAttribute('aria-selected', on ? 'true' : 'false');
  });
  renderExpertiseChips();
}

if (expertiseTags) { renderExpertiseChips(); }

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
  expertiseTags.appendChild(expertiseChip(val));
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
</script>

@include('partials.form-notice')
@include('partials.app-dialog')
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>