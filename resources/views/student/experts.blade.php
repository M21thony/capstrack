<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Capstone Expert - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/Styles/experts.css')
<style>
.role-chips { display: flex; flex-wrap: wrap; gap: 5px; margin: 10px 0 8px; justify-content: center; }
.role-chip { font-size: 10px; font-weight: 600; padding: 3px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.4px; }
.expertise-tags { display: flex; flex-wrap: wrap; gap: 4px; justify-content: center; margin-bottom: 8px; }
.expertise-tag { font-size: 10px; color: var(--text3); background: var(--bg3); padding: 2px 8px; border-radius: 4px; }

/* The list of applications this team has out.

   The bottom margin is not decoration: .sticky-controls below is pulled up
   20px (margin-top:-20px) and paints an opaque background at z-index 100, so
   whatever sits directly above it loses its last 20px underneath. Every other
   block up there - .team-slots, .req-notice - already carries margin-bottom
   20px for exactly that reason. This one did not, so the last application
   card was clipped along its bottom edge. */
.req-section { margin-top: 8px; margin-bottom: 20px; }

/* The team's three role slots - see the markup above for why these
   replaced the stat cards and the panel that used to list them. */
.team-slots { background: var(--bg2); border: 1px solid var(--border); border-radius: 14px; padding: 16px 18px; margin-bottom: 20px; }
.team-slots-head { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; color: var(--text); margin-bottom: 12px; flex-wrap: wrap; }
.team-slots-head svg { width: 18px; height: 18px; stroke: var(--accent); fill: none; stroke-width: 2; flex-shrink: 0; }
.team-slots-hint { font-size: 11.5px; font-weight: 400; color: var(--text3); }
.slot-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.slot { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px; background: var(--bg3); min-width: 0; }
.slot.filled { background: var(--bg2); }
.slot.open { border-style: dashed; }
.slot-extra { margin-top: 10px; }
.slot-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.slot-body { min-width: 0; flex: 1; }
.slot-role { font-family: 'Space Mono', monospace; font-size: 9.5px; font-weight: 700; letter-spacing: 0.7px; text-transform: uppercase; }
.slot-state { font-size: 12px; color: var(--text3); margin-top: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.slot.filled .slot-state { color: var(--text); font-weight: 500; }
.slot-remove { background: none; border: none; cursor: pointer; color: var(--text3); padding: 3px; border-radius: 5px; flex-shrink: 0; display: flex; }
.slot-remove:hover { color: var(--red); background: var(--red-bg); }
.slot-pending { margin-top: 11px; padding-top: 11px; border-top: 1px solid var(--border); font-size: 12px; color: var(--text2); display: flex; align-items: center; gap: 7px; }
.slot-pending-none { background: var(--bg4); color: var(--text3); }
.slot-pending-n { font-family: 'Space Mono', monospace; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 999px; background: var(--amber-bg); color: var(--amber); }
.req-notice { font-size: 12.5px; color: var(--text2); background: var(--bg3); border: 1px dashed var(--border); border-radius: 10px; padding: 12px 14px; margin-bottom: 20px; }
.rg-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }

.req-card { display: flex; align-items: center; gap: 12px; padding: 12px 14px; background: var(--bg3); border: 1px solid var(--border); border-radius: 10px; margin-bottom: 8px; transition: 0.2s; }
.req-card:hover { border-color: var(--accent); }
.req-card .status-badge { font-size: 10px; font-weight: 600; padding: 3px 10px; border-radius: 20px; margin-left: auto; flex-shrink: 0; }

/* Button system — clean, simple, user-friendly */
.btn { display: inline-flex; align-items: center; gap: 6px; border: none; font-family: 'DM Sans', sans-serif; font-weight: 600; cursor: pointer; transition: all 0.15s; border-radius: 8px; }
.btn:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-sm { font-size: 12px; padding: 7px 14px; }
.btn-md { font-size: 13px; padding: 10px 18px; }
.btn-full { width: 100%; justify-content: center; }

/* Solid variants */
.btn-solid { background: var(--accent); color: #18191f; }
.btn-solid:hover { filter: brightness(1.08); }
.btn-solid:active { filter: brightness(0.95); }
.btn-solid-green { background: var(--green); color: white; }
.btn-solid-green:hover { filter: brightness(1.08); }
.btn-solid-red { background: var(--red); color: white; }
.btn-solid-red:hover { filter: brightness(1.08); }

/* Soft variants (subtle bg + border) */
.btn-soft { background: var(--bg3); color: var(--text2); border: 1px solid var(--border); }
.btn-soft:hover { background: var(--border); border-color: var(--text3); }
.btn-soft:active { background: var(--bg2); }
.btn-soft-green { background: var(--green-bg); color: var(--green); border: 1px solid var(--green); }
.btn-soft-green:hover { filter: brightness(1.08); }
.btn-soft-red { background: var(--red-bg); color: var(--red); border: 1px solid var(--red); }
.btn-soft-red:hover { filter: brightness(1.08); }

/* Muted disabled */
.btn-muted { background: var(--bg3); color: var(--text3); border: 1px solid var(--border); cursor: not-allowed; }
.btn-muted-green { background: var(--green-bg); color: var(--green); border: 1px solid var(--green); cursor: not-allowed; opacity: 0.8; }
.btn-muted-amber { background: var(--amber-bg); color: var(--amber); border: 1px solid var(--amber); cursor: not-allowed; opacity: 0.8; }

/* ── Skeleton Loading ── */
@keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
.sk-overlay { position: absolute; inset: 0; background: var(--bg); z-index: 10; overflow-y: auto; }
.sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }

/* Modal base */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 300; display: none; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
.modal-overlay.open { display: flex; }
.modal-box { background: var(--bg2); border-radius: 16px; padding: 28px; width: 420px; max-width: 90vw; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: modalIn 0.2s ease; }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
.modal-box h3 { font-size: 17px; font-weight: 600; color: var(--text); margin-bottom: 4px; }
.modal-box .modal-sub { font-size: 13px; color: var(--text3); margin-bottom: 20px; }
.modal-box .form-group { margin-bottom: 16px; }
.modal-box .form-group label { font-size: 12px; font-weight: 600; color: var(--text2); display: block; margin-bottom: 6px; }
.modal-box .form-group label .req { color: var(--red); margin-left: 2px; }
.modal-box .form-group select,
.modal-box .form-group textarea { width: 100%; padding: 12px 14px; border-radius: 8px; border: 2px solid var(--border); background: var(--bg); color: var(--text); font-size: 14px; font-family: 'DM Sans', sans-serif; outline: none; box-sizing: border-box; transition: border 0.15s; }
.modal-box .form-group select:focus,
.modal-box .form-group textarea:focus { border-color: var(--accent); }
.modal-box .form-group textarea { resize: vertical; min-height: 140px; line-height: 1.6; }
.modal-box .form-group .file-input-wrap { position: relative; display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 8px; border: 1.5px solid var(--border); background: var(--bg3); cursor: pointer; transition: 0.15s; }
.modal-box .form-group .file-input-wrap:hover { border-color: var(--accent); }
.modal-box .form-group .file-input-wrap input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.modal-box .form-group .file-input-wrap .file-icon { width: 20px; height: 20px; stroke: var(--accent); fill: none; stroke-width: 2; flex-shrink: 0; }
.modal-box .form-group .file-input-wrap .file-label { font-size: 13px; color: var(--text3); flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.modal-box .form-group .file-input-wrap .file-label.has-file { color: var(--text); }
/* An expert's own photo, laid over their initials so a failed load (an
   expired Google URL) simply reveals the initials again. */
.avatar { position: relative; overflow: hidden; }
.avatar .avatar-photo { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; border-radius: inherit; display: block; }

/* Skill filter - a grouped, searchable picker in the controls bar. */
.skill-filter { position: relative; display: flex; align-items: center; gap: 8px; }
.skill-filter-trigger { display: inline-flex; align-items: center; gap: 8px; min-width: 190px; max-width: 260px; padding: 8px 11px; font-size: 13px; font-weight: 600; color: var(--text2); cursor: pointer; text-align: left; }
.skill-filter-trigger span { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.skill-filter-trigger svg { flex-shrink: 0; }
.skill-filter-caret { transition: transform .15s; }
.skill-filter.open .skill-filter-caret { transform: rotate(180deg); }
.skill-filter.open .skill-filter-trigger { border-color: var(--accent); }

.skill-picker { display: none; position: absolute; top: calc(100% + 6px); left: 0; z-index: 60; width: 320px; max-width: 92vw; background: var(--bg2); border: 1.5px solid var(--border); border-radius: 10px; box-shadow: 0 14px 32px -8px rgba(0,0,0,0.22); overflow: hidden; }
.skill-filter.open .skill-picker { display: block; }
.skill-picker-search { display: flex; align-items: center; gap: 7px; padding: 9px 11px; border-bottom: 1px solid var(--border); }
.skill-picker-search svg { width: 13px; height: 13px; stroke: var(--text3); fill: none; stroke-width: 2; flex-shrink: 0; }
.skill-picker-search input { flex: 1; border: none; outline: none; background: none; font-family: 'DM Sans', sans-serif; font-size: 13px; color: var(--text); }
.skill-picker-search input::placeholder { color: var(--text3); }
.skill-picker-list { max-height: 300px; overflow-y: auto; padding: 7px; display: flex; flex-direction: column; gap: 4px; }
.skill-picker-empty { font-size: 12px; color: var(--text3); padding: 10px 8px; margin: 0; }

.skill-group { border: 1px solid var(--border); border-radius: 8px; overflow: hidden; flex-shrink: 0; }
.skill-group-header { display: flex; align-items: center; gap: 8px; padding: 8px 10px; cursor: pointer; background: var(--bg3); user-select: none; }
.skill-group-header:hover { background: var(--bg4); }
.skill-chevron { width: 11px; height: 11px; stroke: var(--text3); fill: none; stroke-width: 2.4; flex-shrink: 0; transition: transform .15s; }
.skill-group.open .skill-chevron { transform: rotate(90deg); }
.skill-group-name { font-size: 12px; font-weight: 700; color: var(--text); }
.skill-group-count { margin-left: auto; font-size: 10px; font-weight: 700; color: var(--text3); background: var(--bg4); padding: 2px 7px; border-radius: 999px; }
.skill-group-body { display: none; flex-direction: column; gap: 2px; padding: 5px; }
.skill-group.open .skill-group-body { display: flex; }

.skill-opt { display: flex; align-items: center; gap: 8px; padding: 6px 9px; border-radius: 6px; font-size: 12.5px; color: var(--text2); text-decoration: none; border: 1px solid transparent; }
.skill-opt:hover { background: var(--bg3); color: var(--text); }
.skill-opt.selected { border-color: var(--accent); background: var(--accent-light); color: var(--accent3); font-weight: 600; }
.skill-opt-name { flex: 1; }
.skill-opt-count { font-size: 10px; font-weight: 700; color: var(--text3); }
.skill-opt-all { font-weight: 600; }
.skill-opt-group { font-style: italic; color: var(--text3); }

.skill-filter-active { font-size: 12px; color: var(--text2); margin-top: 10px; }
.skill-filter-active strong { color: var(--text); }
.skill-filter-clear { font-size: 12px; font-weight: 600; color: var(--text3); text-decoration: none; margin-left: 6px; }
.skill-filter-clear:hover { color: var(--accent3); text-decoration: underline; }

/* Role picker in the apply modal - one card per role the expert holds. */
.role-choice-list { display: flex; flex-wrap: wrap; gap: 8px; }
.role-choice { display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px; border-radius: 8px; border: 2px solid var(--border); background: var(--bg); color: var(--text2); font-size: 13px; font-weight: 600; font-family: 'DM Sans', sans-serif; cursor: pointer; transition: 0.15s; }
.role-choice:hover { border-color: var(--accent); }
.role-choice .role-choice-mark { width: 14px; height: 14px; border-radius: 50%; border: 2px solid var(--border2, var(--border)); flex-shrink: 0; transition: 0.15s; }
.role-choice.selected { border-color: var(--accent); background: var(--accent-light); color: var(--accent3); }
.role-choice.selected .role-choice-mark { border-color: var(--accent); background: var(--accent); box-shadow: inset 0 0 0 2px var(--bg); }
.role-choice-note { font-size: 11px; color: var(--text3); margin-top: 8px; line-height: 1.5; }
.modal-box .form-group .field-error { font-size: 11px; color: var(--red); margin-top: 4px; display: none; }
.modal-box .form-group .field-error.show { display: block; }
.modal-actions { display: flex; gap: 10px; margin-top: 20px; }
.modal-actions .btn { flex: 1; justify-content: center; padding: 10px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.15s; border: none; font-family: 'DM Sans', sans-serif; display: inline-flex; align-items: center; gap: 6px; }
.modal-actions .btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* Confirmation modal */
.confirm-box { width: 380px; text-align: center; }
.confirm-box .confirm-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
.confirm-box .confirm-icon svg { width: 24px; height: 24px; stroke: white; fill: none; stroke-width: 2; }
.confirm-box h3 { font-size: 18px; }
.confirm-box .confirm-desc { font-size: 13px; color: var(--text3); margin: 8px 0 4px; line-height: 1.5; }
.confirm-box .confirm-detail { font-size: 12px; color: var(--text2); background: var(--bg3); border-radius: 8px; padding: 12px 16px; margin-top: 14px; text-align: left; line-height: 1.6; }
.confirm-box .confirm-detail strong { color: var(--text); }

/* Remove confirmation — type confirmation + reason */
.remove-box { width: 420px; text-align: left; }
.remove-field { margin-top: 16px; }
.remove-field label { font-size: 12px; font-weight: 600; color: var(--text2); display: block; margin-bottom: 6px; }
.remove-field label strong { color: var(--red); font-family: 'Space Mono', monospace; }
.modal-input { width: 100%; padding: 12px 14px; border-radius: 10px; border: 1.5px solid var(--border); background: var(--bg3); color: var(--text); font-size: 14px; font-family: 'DM Sans', sans-serif; outline: none; box-sizing: border-box; transition: border 0.2s, box-shadow 0.2s, background 0.2s; }
.modal-input:focus { border-color: var(--accent); background: var(--bg2); box-shadow: 0 0 0 3px var(--accent-glow); }
.modal-input.error { border-color: var(--red); background: var(--red-bg); box-shadow: 0 0 0 3px rgba(220,38,38,0.1); }
.remove-field textarea.modal-input { min-height: 80px; line-height: 1.5; }
.remove-field .field-error { font-size: 11px; color: var(--red); margin-top: 4px; display: none; }
.remove-field .field-error.show { display: block; }

/* Review / Info modals */
.review-box { width: 440px; }
.review-box .review-row { display: flex; padding: 10px 0; border-bottom: 1px solid var(--border); }
.review-box .review-row:last-child { border-bottom: none; }
.review-box .review-label { font-size: 11px; font-weight: 600; color: var(--text3); text-transform: uppercase; letter-spacing: 0.3px; width: 100px; flex-shrink: 0; }
.review-box .review-value { font-size: 13px; color: var(--text); flex: 1; word-break: break-word; }

.msg-toast { position: fixed; bottom: 30px; right: 30px; background: var(--navy-dark); color: white; padding: 14px 22px; border-radius: 12px; font-size: 13px; font-weight: 500; z-index: 500; display: none; align-items: center; gap: 10px; box-shadow: 0 8px 30px rgba(0,0,0,0.3); animation: slideUp 0.3s ease; }
.msg-toast.success { border-left: 4px solid var(--green); }
.msg-toast.error { border-left: 4px solid var(--red); }
.msg-toast.show { display: flex; }
@keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.empty-state { text-align: center; padding: 40px 20px; color: var(--text3); }
.empty-state svg { width: 48px; height: 48px; stroke: var(--border2); margin-bottom: 12px; }
.empty-state p { font-size: 14px; }

</style>
@include('partials.skeleton-head', ['skReveal' => 'opacity'])
</head>
<body>
@include('student.partials.sidebar')

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Capstone Expert</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>

  <div class="content">

    <!-- ── SKELETON LOADER ── -->
    <!-- .content is a 2-column CSS grid (grid-template-columns:1fr 420px) and
         .experts-main/.overview-panel must be direct grid children to place
         correctly — #contentWrapper spans the whole grid as one cell, so
         both layers inside it re-declare the same grid independently via
         .content-grid (a real class, not inline styles, so the existing
         @media(max-width:1024px) override that stacks .content to a single
         column on mobile can reach them too). -->
    <div id="contentWrapper" class="content-grid-wrap">
<div id="skeleton-content" class="sk-overlay content-grid">
      <div class="experts-main">
        {{-- Shaped like the strip that actually loads, so the page does not
             jump when the real content replaces it. --}}
        <div class="team-slots">
          <div class="sk" style="height:16px;width:180px;margin-bottom:14px;"></div>
          <div class="slot-row">
            @for($i = 0; $i < 3; $i++)
            <div class="slot">
              <div class="sk" style="width:8px;height:8px;border-radius:50%;flex-shrink:0;"></div>
              <div style="flex:1">
                <div class="sk" style="height:9px;width:56px;margin-bottom:5px;"></div>
                <div class="sk" style="height:12px;width:80%;"></div>
              </div>
            </div>
            @endfor
          </div>
        </div>

        <div class="sticky-controls">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <div class="sk" style="height:40px;flex:1;max-width:320px;border-radius:8px;"></div>
            <div style="display:flex;gap:6px;">
              @for($i = 0; $i < 4; $i++)
              <div class="sk" style="height:28px;width:72px;border-radius:20px;"></div>
              @endfor
            </div>
          </div>
        </div>

        <div class="expert-grid">
          @for($i = 0; $i < 6; $i++)
          <div class="expert-card-compact">
            <div class="expert-avatar-wrap" style="border:0;padding:0;">
              <div class="sk" style="width:72px;height:72px;border-radius:50%;"></div>
            </div>
            <div class="sk" style="height:16px;width:120px;margin:12px auto;"></div>
            <div style="display:flex;gap:6px;justify-content:center;margin-bottom:10px;">
              <div class="sk" style="height:20px;width:60px;border-radius:20px;"></div>
              <div class="sk" style="height:20px;width:75px;border-radius:20px;"></div>
            </div>
            <div class="sk" style="height:4px;width:100%;border-radius:2px;margin-bottom:6px;"></div>
            <div class="sk" style="height:12px;width:100px;margin:0 auto 12px;"></div>
            <div class="sk" style="height:38px;width:100%;border-radius:10px;"></div>
          </div>
          @endfor
        </div>

      </div>

      <div class="overview-panel">
        <div class="rg-qt-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items: start;">
          <div class="card" style="padding:14px;border-radius:12px;">
            <div class="sk" style="height:13px;width:80px;margin-bottom:12px;"></div>
            @for($i = 0; $i < 3; $i++)
            <div class="sk" style="height:11px;width:90%;margin-bottom:10px;"></div>
            @endfor
          </div>
          <div class="card" style="padding:14px;border-radius:12px;">
            <div class="sk" style="height:13px;width:80px;margin-bottom:12px;"></div>
            @for($i = 0; $i < 4; $i++)
            <div class="sk" style="height:11px;width:85%;margin-bottom:8px;"></div>
            @endfor
          </div>
        </div>
      </div>
    </div>

    <!-- ── REAL CONTENT ── -->
    <div id="real-content" class="content-grid" style="opacity:0">
      @include('student.partials.page-tutorial', ['page' => 'experts'])

    <div class="experts-main">
      {{-- A team takes one adviser, one consultant and one grammarian, so the
           three slots and who fills them are the whole picture. This replaces
           three stat cards ("Available Experts" counted the list directly
           below it; "My Team Experts: 0" and "Pending Requests: 0" each
           repeated a card underneath saying the same thing in words) - and a
           count of 0 never told you WHICH role you were still missing. --}}
      @php
        // Same colours the role chips on the expert cards use, so a
        // consultant reads as a consultant everywhere on this page.
        $roleColors = [
          'adviser'     => ['dot' => 'var(--accent)', 'bg' => 'var(--accent-light)', 'text' => 'var(--accent3)'],
          'consultant'  => ['dot' => '#3b82f6',       'bg' => 'var(--blue-bg)',      'text' => 'var(--blue)'],
          'grammarian'  => ['dot' => '#9333ea',       'bg' => 'var(--purple-bg)',    'text' => 'var(--purple)'],
        ];
        $approvedAssignments = $assignments->where('status', 'approved');

        // Keyed by the role the team actually engaged them as. Anyone approved
        // before roles were recorded has no slot, so they are listed under the
        // three rather than being dropped from the page.
        $filledSlots = [];
        $unslotted = [];
        foreach ($approvedAssignments as $a) {
          $r = $a->engaged_role_name;
          if ($r && isset($roleColors[$r]) && ! isset($filledSlots[$r])) { $filledSlots[$r] = $a; }
          else { $unslotted[] = $a; }
        }
        $pendingCount = $assignments->where('status', 'pending')->count();
      @endphp

      @if($team)
      <div class="team-slots">
        <div class="team-slots-head">
          <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><path d="M12 2a10 10 0 1 0 10 10"/></svg>
          Your Team's Experts
          <span class="team-slots-hint">One adviser, one consultant, one grammarian.</span>
        </div>

        <div class="slot-row">
          @foreach(['adviser', 'consultant', 'grammarian'] as $slotRole)
            @php $a = $filledSlots[$slotRole] ?? null; $rc = $roleColors[$slotRole]; @endphp
            <div class="slot {{ $a ? 'filled' : 'open' }}">
              <span class="slot-dot" style="background: {{ $rc['dot'] }};"></span>
              <div class="slot-body">
                <div class="slot-role" style="color: {{ $rc['text'] }};">{{ ucfirst($slotRole) }}</div>
                <div class="slot-state" @if($a) title="{{ $a->expert?->name }}" @endif>
                  {{ $a ? ($a->expert?->name ?? 'Unknown') : 'Not filled' }}
                </div>
              </div>
              @if($a && $isLeader)
                <button class="slot-remove" title="Remove {{ $a->expert?->name ?? 'this expert' }}"
                        onclick="openRemoveModal({{ $a->assignment_id }}, '{{ $a->expert?->name ?? '' }}')">
                  <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
              @endif
            </div>
          @endforeach
        </div>

        @foreach($unslotted as $a)
          <div class="slot slot-extra">
            <span class="slot-dot" style="background: var(--text3);"></span>
            <div class="slot-body">
              <div class="slot-role" style="color: var(--text3);">Assigned</div>
              <div class="slot-state">{{ $a->expert?->name ?? 'Unknown' }}</div>
            </div>
            @if($isLeader)
              <button class="slot-remove" title="Remove {{ $a->expert?->name ?? 'this expert' }}"
                      onclick="openRemoveModal({{ $a->assignment_id }}, '{{ $a->expert?->name ?? '' }}')">
                <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              </button>
            @endif
          </div>
        @endforeach

        {{-- Always drawn, even at nothing. Hiding it when the team had made
             no applications meant the page said nothing at all about
             requests, so there was no way to learn where they would appear
             before you had one - the Request Experts section below is
             conditional too, so both vanished together. --}}
        <div class="slot-pending">
          @if($pendingCount > 0)
            <span class="slot-pending-n">{{ $pendingCount }}</span>
            {{ $pendingCount === 1 ? 'request waiting on a reply' : 'requests waiting on a reply' }}
          @elseif($assignments->isNotEmpty())
            No requests waiting &mdash; see the list below.
          @else
            <span class="slot-pending-n slot-pending-none">0</span>
            No requests out yet.
          @endif
        </div>
      </div>
      @endif

      {{-- Your own outstanding requests sit above the browse list, not below
           it. They used to be the last thing on the page, so finding them
           meant scrolling past every expert card - and the further the list
           grew, the further down they went.

           Only drawn when there is something in it. An empty "Request Experts"
           card repeated what the slots above already show, and its message was
           a dead end - so the two cases worth a sentence say the useful thing
           instead: you have no team, or you are not the one who can apply. --}}
      @if($assignments->isNotEmpty())
      <div class="req-section">
        <div class="section-title" style="font-size: 15px; font-weight: 600; margin-bottom: 14px; display: flex; align-items: center; gap: 8px;">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          Request Experts
          @if($assignments->isNotEmpty())
            <span style="font-size: 12px; color: var(--text3); font-weight: 400;">({{ $assignments->count() }})</span>
          @endif
        </div>

        @foreach($assignments as $assignment)
          @php
            // Through the public disk explicitly: the file was stored with
            // ->store("proposals", "public"), and a bare Storage::url() call
            // resolves against the DEFAULT disk instead - the same fix the
            // expert side of this link already carries.
            $propUrl = $assignment->proposal_file
                ? Storage::disk("public")->url($assignment->proposal_file)
                : null;
            $reqRole = $assignment->engaged_role_name;
          @endphp
          <div class="req-card">
            <div class="avatar" style="width: 36px; height: 36px; font-size: 12px; background: var(--accent); color: white;">
              {{ $assignment->expert?->avatarInitials() ?? 'NA' }}
              @if ($assignment->expert?->hasAvatarImage())
                <img class="avatar-photo" src="{{ $assignment->expert->avatarUrl() }}" alt="" loading="lazy"
                     referrerpolicy="no-referrer" onerror="this.remove()">
              @endif
            </div>
            <div style="flex: 1; min-width: 0;">
              <div style="font-size: 13px; font-weight: 600; color: var(--text);">{{ $assignment->expert?->name ?? 'Unknown Expert' }}</div>
              <div style="font-size: 11px; color: var(--text3); text-transform: uppercase;">{{ $reqRole ? 'Applied as '.$reqRole : ucfirst($assignment->status) }}</div>
            </div>
            <span class="status-badge" style="background: {{ $assignment->status === 'approved' ? 'var(--green-bg)' : ($assignment->status === 'pending' ? 'var(--amber-bg)' : 'var(--red-bg)') }}; color: {{ $assignment->status === 'approved' ? 'var(--green)' : ($assignment->status === 'pending' ? 'var(--amber)' : 'var(--red)') }};">
              {{ ucfirst($assignment->status) }}
            </span>
            <button class="btn btn-sm btn-soft" onclick="openReqInfoModal({{ json_encode([
              'name' => $assignment->expert?->name ?? 'Unknown',
              'role' => $reqRole ? ucfirst($reqRole) : null,
              'status' => ucfirst($assignment->status),
              'message' => $assignment->message ?? 'No message provided.',
              // Why the expert said no. It was written down and sent in a
              // notification, then never shown again - so a team that missed
              // the notification had a rejected application and no idea why.
              'rejection_reason' => $assignment->rejection_reason,
              // An application made with the CaPSA-approved pre-proposal has
              // no uploaded file, and the details said "None attached" - as
              // though nothing had been sent, when what was sent is the one
              // document the CaPSA had already passed.
              'pre_proposal_title' => $assignment->preProposal?->title,
              'proposal' => $propUrl,
              'date' => $assignment->created_at ? $assignment->created_at->format('M d, Y g:i A') : 'N/A',
            ]) }})" title="View details">View</button>
            @if($isLeader && $assignment->status !== 'approved')
              <button class="btn btn-sm btn-soft-red" onclick="openRemoveModal({{ $assignment->assignment_id }}, '{{ $assignment->expert?->name ?? '' }}')" title="Remove">
                <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              </button>
            @endif
          </div>
        @endforeach
      </div>
      @elseif(! $team)
        <div class="req-notice">You need to join or create a team before you can apply for an expert.</div>
      @elseif(! $isLeader)
        <div class="req-notice">Only your team leader can apply for experts. You can still browse who is available below.</div>
      @endif

      <div class="sticky-controls">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
          <input class="input" id="search-input" placeholder="Search experts..." style="flex: 1; min-width: 180px; max-width: 320px;" oninput="filterExperts()" />

          {{-- Reloads the page rather than hiding cards: the list is
               paginated, so filtering in the browser would only ever search
               the experts already on screen. --}}
          {{-- Grouped, searchable skill picker - the same shape as the
               "Make CaPSA" section list. Experts write their own skills, so
               the roster runs to dozens of entries and several spellings of
               the same thing; grouping them into a handful of areas keeps
               that browsable, and picking a whole area covers every wording
               filed under it. --}}
          <div class="skill-filter">
            <button type="button" class="input skill-filter-trigger" id="skill-filter-trigger" onclick="toggleSkillPicker(event)"
                    aria-haspopup="true" aria-expanded="false">
              <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
              <span id="skill-filter-label">
                @if($activeSkill !== '') {{ $activeSkill }}
                @elseif($activeGroup !== '') {{ $activeGroup }}
                @else All skills @endif
              </span>
              <svg class="skill-filter-caret" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4"><polyline points="6 9 12 15 18 9"/></svg>
            </button>

            <div class="skill-picker" id="skill-picker">
              <div class="skill-picker-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <input id="skillSearch" placeholder="Search skills…" autocomplete="off" oninput="filterSkillPicker(this.value)">
              </div>

              <div class="skill-picker-list" id="skillPickerList">
                <a class="skill-opt skill-opt-all {{ $activeSkill === '' && $activeGroup === '' ? 'selected' : '' }}"
                   href="{{ route('student.experts') }}">All skills</a>

                @forelse($skillGroups as $groupName => $groupSkills)
                  @php $groupCount = $groupSkills->sum('expert_count'); @endphp
                  <div class="skill-group {{ $activeGroup === $groupName ? 'open' : '' }}" data-name="{{ mb_strtolower($groupName) }}">
                    <div class="skill-group-header" onclick="toggleSkillGroup(this)">
                      <svg class="skill-chevron" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                      <span class="skill-group-name">{{ $groupName }}</span>
                      <span class="skill-group-count">{{ $groupSkills->count() }}</span>
                    </div>
                    <div class="skill-group-body">
                      {{-- Pick the whole area without needing to know which
                           wording each expert happened to use. --}}
                      <a class="skill-opt skill-opt-group {{ $activeGroup === $groupName && $activeSkill === '' ? 'selected' : '' }}"
                         data-name="{{ mb_strtolower($groupName) }}"
                         href="{{ route('student.experts', ['skill_group' => $groupName]) }}">
                        Anything in this area
                      </a>
                      @foreach($groupSkills as $skill)
                        <a class="skill-opt {{ mb_strtolower($activeSkill) === mb_strtolower($skill->skill_name) ? 'selected' : '' }}"
                           data-name="{{ mb_strtolower($skill->skill_name) }}"
                           href="{{ route('student.experts', ['skill' => $skill->skill_name]) }}">
                          <span class="skill-opt-name">{{ $skill->skill_name }}</span>
                          <span class="skill-opt-count">{{ $skill->expert_count }}</span>
                        </a>
                      @endforeach
                    </div>
                  </div>
                @empty
                  <p class="skill-picker-empty">No skills recorded yet — they appear here once experts fill in their expertise.</p>
                @endforelse

                <p class="skill-picker-empty" id="skillPickerNoMatch" style="display:none;">No skill matches that.</p>
              </div>
            </div>
          </div>

          <div class="filter-bar" style="margin-bottom: 0;">
            <div class="filter-chip active" data-role="all" onclick="setFilter('all', this)">All</div>
            <div class="filter-chip" data-role="adviser" onclick="setFilter('adviser', this)">Advisers</div>
            <div class="filter-chip" data-role="consultant" onclick="setFilter('consultant', this)">Consultants</div>
            <div class="filter-chip" data-role="grammarian" onclick="setFilter('grammarian', this)">Grammarian</div>
          </div>
        </div>

        @if($activeSkill !== '' || $activeGroup !== '')
          <div class="skill-filter-active">
            @if($activeSkill !== '')
              Experts whose skills mention <strong>{{ $activeSkill }}</strong>
            @else
              Experts with any skill in <strong>{{ $activeGroup }}</strong>
            @endif
            — {{ $experts->total() }} {{ Str::plural('expert', $experts->total()) }} found.
            <a href="{{ route('student.experts') }}" class="skill-filter-clear">Clear</a>
          </div>
        @endif
      </div>

      <div id="expert-grid" class="expert-grid">
        @forelse($experts as $expert)
          @php
            $visibleRoles = $expert->roles;
            $firstRole = $visibleRoles->first();
            $dotColors = ['adviser' => 'var(--accent)', 'consultant' => '#3b82f6', 'grammarian' => '#9333ea'];
            $chipColors = [
              'adviser' => ['bg' => 'var(--accent-light)', 'text' => 'var(--accent3)'],
              'consultant' => ['bg' => 'var(--blue-bg)', 'text' => 'var(--blue)'],
              'grammarian' => ['bg' => 'var(--purple-bg)', 'text' => 'var(--purple)'],
            ];
            $_slotsUsed = $expert->slots_used;
            $_maxSlots = $expert->max_teams ?? 0;
            $_slotPercent = $_maxSlots > 0 ? min(100, ($_slotsUsed / $_maxSlots) * 100) : 0;
            $_isFull = $_slotsUsed >= $_maxSlots;
            $_barColor = $_isFull ? 'var(--red)' : ($_slotPercent > 66 ? 'var(--amber)' : 'var(--accent)');
            $_chips = $visibleRoles->map(fn($r) => [
                'name' => ucfirst($r->role_name),
                'bg' => ($chipColors[$r->role_name]['bg'] ?? 'var(--bg3)'),
                'text' => ($chipColors[$r->role_name]['text'] ?? 'var(--text3)'),
            ])->values()->toArray();
            $_expertiseTags = $expert->expertise->pluck('expertise_field')->toArray();
            $_expertiseMore = max(0, $expert->expertise->count() - 3);
          @endphp
          <div class="expert-card-compact" data-expert-id="{{ $expert->expert_id }}" style="--accent-clr:{{ $dotColors[$firstRole?->role_name ?? 'adviser'] ?? 'var(--accent)' }}" data-roles="{{ $visibleRoles->pluck('role_name')->join(',') }}" data-name="{{ $expert->name }}" data-initials="{{ $expert->avatarInitials() }}" data-avatar="{{ $expert->hasAvatarImage() ? $expert->avatarUrl() : '' }}" data-chips='{{ json_encode($_chips) }}' data-tags='{{ json_encode($_expertiseTags) }}' data-tags-more="{{ $_expertiseMore }}" data-slots="{{ $_slotsUsed }}/{{ $_maxSlots }}" data-slots-pct="{{ $_slotPercent }}" data-slots-color="{{ $_barColor }}" onclick="openExpertPostModal(this)">
            <div class="role-dot" style="background: {{ $dotColors[$firstRole?->role_name ?? 'adviser'] ?? 'var(--accent)' }};"></div>
            <div class="card-accent" style="background:{{ $dotColors[$firstRole?->role_name ?? 'adviser'] ?? 'var(--accent)' }};"></div>
            <div class="expert-avatar-wrap">
              {{-- Experts sign in with Google, so most have a real photo on
                   file. If it fails to load (an expired Google URL, say) the
                   initials underneath show through. --}}
              <div class="avatar" style="width:100%; height:100%; font-size:18px;">
                {{ $expert->avatarInitials() }}
                @if ($expert->hasAvatarImage())
                  <img class="avatar-photo" src="{{ $expert->avatarUrl() }}" alt="" loading="lazy"
                       referrerpolicy="no-referrer" onerror="this.remove()">
                @endif
              </div>
            </div>
            <div style="font-size: 15px; font-weight: 700; color: var(--text);">{{ $expert->name }}</div>

            <div class="role-chips">
              @foreach($visibleRoles as $role)
                @php
                  $chipColors = [
                    'adviser' => ['bg' => 'var(--accent-light)', 'text' => 'var(--accent3)'],
                    'consultant' => ['bg' => 'var(--blue-bg)', 'text' => 'var(--blue)'],
                    'grammarian' => ['bg' => 'var(--purple-bg)', 'text' => 'var(--purple)'],
                  ];
                  $c = $chipColors[$role->role_name] ?? ['bg' => 'var(--bg3)', 'text' => 'var(--text3)'];
                @endphp
                <span class="role-chip" style="background: {{ $c['bg'] }}; color: {{ $c['text'] }};">
                  {{ ucfirst($role->role_name) }}
                </span>
              @endforeach
            </div>

            <div class="expertise-tags">
              @foreach($expert->expertise->take(3) as $exp)
                <span class="expertise-tag">{{ $exp->expertise_field }}</span>
              @endforeach
              @if($expert->expertise->count() > 3)
                <span class="expertise-tag">+{{ $expert->expertise->count() - 3 }} more</span>
              @endif
            </div>

            <div class="mini-slot-bar"><div class="mini-slot-fill" style="width: {{ $_slotPercent }}%; background: {{ $_barColor }};"></div></div>
            <div style="font-size: 10px; font-weight: 700; color: {{ $_isFull ? 'var(--red)' : 'var(--text3)' }}; letter-spacing: 0.5px;">
              {{ $_isFull ? 'FULL CAPACITY' : 'SLOTS: '.$_slotsUsed.'/'.$_maxSlots }}
            </div>

            @if($team && $isLeader && !$teamConfirmed)
              <button class="btn btn-sm btn-full btn-muted" disabled>
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><path d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2zm10-10V7a4 4 0 0 0-8 0v4h8z"/></svg>
                Verified First
              </button>
            @elseif($team && $isLeader)
              @php
                $teamAssignmentsForExpert = $assignments->where('expert_id', $expert->expert_id);
                $hasApproved = $teamAssignmentsForExpert->where('status', 'approved')->isNotEmpty();
                $hasPending = $teamAssignmentsForExpert->where('status', 'pending')->isNotEmpty();
              @endphp
              @if($hasApproved)
                <button class="btn btn-sm btn-full btn-muted-green" disabled>
                  <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                  Approved
                </button>
              @elseif($hasPending)
                <button class="btn btn-sm btn-full btn-muted-amber" disabled>
                  <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                  Pending
                </button>
              @elseif(!$_isFull)
                @php
                  // Each role the leader can apply for, carrying the pivot key
                  // the application records - so "as our consultant" is stored,
                  // not guessed at later.
                  $_roleChoices = $visibleRoles->map(fn($r) => [
                      'expert_role_id' => $r->pivot->expert_role_id,
                      'name' => $r->role_name,
                      'label' => ucfirst($r->role_name),
                  ])->values();
                @endphp
                <button class="btn btn-sm btn-full btn-solid" data-apply-expert="{{ $expert->expert_id }}" onclick="openApplyModal({{ $expert->expert_id }}, {{ Js::from($expert->name) }}, {{ Js::from($_roleChoices) }})">
                  <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
                  Apply Now
                </button>
              @elseif($_isFull)
                <button class="btn btn-sm btn-full btn-muted" disabled>
                  <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                  Full
                </button>
              @else
                <button class="btn btn-sm btn-full btn-muted" disabled>
                  <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                  Already Applied
                </button>
              @endif
            @elseif(!$team)
              <button class="btn btn-sm btn-full btn-muted" disabled>
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                Join a Team First
              </button>
            @elseif(!$isLeader)
              <button class="btn btn-sm btn-full btn-muted" disabled>
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><path d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2zm10-10V7a4 4 0 0 0-8 0v4h8z"/></svg>
                Leader Only
              </button>
            @endif
          </div>
        @empty
          <div class="empty-state" style="grid-column: 1 / -1;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
            @if($activeSkill !== '' || $activeGroup !== '')
              <p>No expert lists a skill matching <strong>{{ $activeSkill !== '' ? $activeSkill : $activeGroup }}</strong>.</p>
              <a href="{{ route('student.experts') }}" class="btn btn-sm btn-soft" style="margin-top:10px;">Show all experts</a>
            @else
              <p>No approved experts available yet.</p>
            @endif
          </div>
        @endforelse
      </div>

      @if ($experts->hasPages())
      <div class="pagination-container">
        @if ($experts->onFirstPage())
          <div class="pg-arrow disabled" style="opacity:0.5;cursor:not-allowed;display:flex;align-items:center;justify-content:center;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
          </div>
        @else
          <a href="{{ $experts->previousPageUrl() }}" class="pg-arrow" style="color:inherit;text-decoration:none;display:flex;align-items:center;justify-content:center;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
          </a>
        @endif

        @foreach ($experts->getUrlRange(1, $experts->lastPage()) as $page => $url)
          @if ($page == $experts->currentPage())
            <div class="pg-btn active">{{ $page }}</div>
          @else
            <a href="{{ $url }}" class="pg-btn" style="color:inherit;text-decoration:none;">{{ $page }}</a>
          @endif
        @endforeach

        @if ($experts->hasMorePages())
          <a href="{{ $experts->nextPageUrl() }}" class="pg-arrow" style="color:inherit;text-decoration:none;display:flex;align-items:center;justify-content:center;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
          </a>
        @else
          <div class="pg-arrow disabled" style="opacity:0.5;cursor:not-allowed;display:flex;align-items:center;justify-content:center;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
          </div>
        @endif
      </div>
      @endif

    </div>

    <div class="overview-panel">
      <div class="rg-qt-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items: start;">
        <div class="card" style="padding: 14px; border-radius: 12px; background: var(--bg2);">
          <div class="section-title" style="font-size: 13px; margin-bottom: 12px;">Role Guide</div>
          <div style="margin-bottom: 12px;">
            <div style="font-size: 11px; font-weight: 600; color: var(--accent3); margin-bottom: 4px; display: flex; align-items: center; gap: 6px;"><span class="rg-dot" style="background: var(--accent);"></span>Adviser</div>
            <div style="font-size: 10px; color: var(--text3); line-height: 1.4;">Guides capstone teams throughout their project journey</div>
          </div>
          <div style="margin-bottom: 12px;">
            <div style="font-size: 11px; font-weight: 600; color: #3b82f6; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;"><span class="rg-dot" style="background: #3b82f6;"></span>Consultant</div>
            <div style="font-size: 10px; color: var(--text3); line-height: 1.4;">Provides subject-matter expertise and technical advice</div>
          </div>
          <div style="margin-bottom: 12px;">
            <div style="font-size: 11px; font-weight: 600; color: #9333ea; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;"><span class="rg-dot" style="background: #9333ea;"></span>Grammarian</div>
            <div style="font-size: 10px; color: var(--text3); line-height: 1.4;">Reviews and ensures quality of written documentation</div>
          </div>
          <div class="divider" style="margin: 16px 0;"></div>
          <p style="font-size: 10px; color: var(--text3); line-height: 1.4; opacity: 0.8;">
            Request approval depends on the expert's current workload and availability.
          </p>
        </div>

        <div class="card" style="padding: 14px; border-radius: 12px; background: var(--bg2);">
          <div class="section-title" style="font-size: 13px; margin-bottom: 12px;">Quick Tips</div>
          <ul style="font-size: 11px; color: var(--text3); line-height: 1.6; padding-left: 16px; margin: 0;">
            <li>Only the Team Leader can apply for experts</li>
            <li>An expert can hold only one role per team - pick which role you need them for</li>
            <li>Attach your approved pre-proposal PDF when applying</li>
            <li>Check the slot status before applying</li>
          </ul>
        </div>
      </div>
    </div>

      {{-- Sits along the bottom of the page, spanning both columns. It used
           to be inside .overview-panel - the 420px right-hand column holding
           the Role Guide and Quick Tips - so it was drawn tucked under those
           at the right instead of under the page. --}}
      <div class="legal-credits">
        <div class="credits-title">System Developers</div>
        <div class="credits-names">Jolina · John Kenneth · Mark Anthony · Ian Cyrus · Alexa Nicole</div>
      </div>
    </div>
  </div>
</div>
</div>

<!-- Expert Post Info Modal (responsive only) -->
<div class="modal-overlay expert-post-modal" id="expert-post-modal">
  <div class="expert-post-box">
    <button class="expert-post-close" onclick="closeModal('expert-post-modal')">
      <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div class="expert-post-header">
      <div class="expert-post-avatar" id="ep-avatar"></div>
      <div class="expert-post-name" id="ep-name"></div>
    </div>
    <div class="expert-post-section">
      <div class="ep-label">Roles</div>
      <div class="ep-chips" id="ep-chips"></div>
    </div>
    <div class="expert-post-section">
      <div class="ep-label">Expertise</div>
      <div class="ep-tags" id="ep-tags"></div>
    </div>
    <div class="expert-post-section">
      <div class="ep-label">Slot Availability</div>
      <div class="ep-slot-row">
        <div class="ep-slot-bar"><div class="ep-slot-fill" id="ep-slot-fill"></div></div>
        <div class="ep-slot-text" id="ep-slot-text"></div>
      </div>
    </div>
    <div class="expert-post-action" id="ep-action"></div>
  </div>
</div>

<!-- Apply Form Modal -->
<div class="modal-overlay" id="apply-modal">
  <div class="modal-box" style="width: 560px; max-width: 94vw;">
    <h3>Apply for Expert</h3>
    <div class="modal-sub" id="apply-expert-name"></div>

    <div class="form-group" id="apply-role-group">
      <label>Apply as <span class="req">*</span></label>
      <div class="role-choice-list" id="apply-role-choices"></div>
      <div class="role-choice-note">An expert can take only one role per team. Once they are your adviser, you cannot also engage them as your consultant or grammarian.</div>
      <div class="field-error" id="err-role">Please choose the role you are applying for.</div>
    </div>

    {{-- A team whose pre-proposal the CaPSA has approved sends that one. It
         is the document that got through, so re-uploading it was busywork -
         and nothing stopped a different, unapproved file being attached in
         its place. Teams still waiting on a decision attach a PDF as before. --}}
    @if($approvedProposal)
    <div class="form-group">
      <label>Approved Pre-Proposal</label>
      <div style="display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border:1px solid var(--green);background:var(--green-bg);border-radius:10px">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="var(--green)" stroke-width="2.4" style="flex-shrink:0;margin-top:1px"><polyline points="20 6 9 17 4 12"/></svg>
        <div style="min-width:0">
          <div style="font-size:12.5px;font-weight:600;color:var(--text);line-height:1.4">{{ $approvedProposal->title }}</div>
          <div style="font-size:11px;color:var(--text3);margin-top:3px">Approved by your CaPSA &mdash; sent with this application, so there is nothing to upload.</div>
        </div>
      </div>
    </div>
    @else
    <div class="form-group">
      <label>Approved Pre-Proposal <span class="req">*</span></label>
      <div class="file-input-wrap">
        <svg class="file-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="12" y2="12"/><line x1="15" y1="15" x2="12" y2="12"/></svg>
        <span class="file-label" id="file-label">Choose PDF file...</span>
        <input type="file" id="proposal-file" accept=".pdf,application/pdf" onchange="handleFileSelect(this)" />
      </div>
      <div class="field-error" id="err-file">Please upload your approved pre-proposal (PDF).</div>
    </div>
    @endif

    <div class="form-group">
      <label>Message to Expert <span class="req">*</span></label>
      <textarea id="apply-message" placeholder="Briefly introduce your team and why you're applying for this expert..." rows="5"></textarea>
      <div style="display: flex; justify-content: space-between; margin-top: 4px;">
        <div class="field-error" id="err-message">Please write a message.</div>
        <span style="font-size: 11px; color: var(--text3);"><span id="msg-count">0</span>/500 words</span>
      </div>
    </div>

    <div class="modal-actions" style="justify-content:flex-end;">
      <button class="btn btn-soft" onclick="closeModal('apply-modal')" style="flex:0 0 auto;">Cancel</button>
      <button class="btn btn-solid" id="btn-review-apply" onclick="reviewApply()" style="flex:0 0 auto;">Review &amp; Submit</button>
    </div>
  </div>
</div>

<!-- Apply Review / Confirmation Modal -->
<div class="modal-overlay" id="review-modal">
  <div class="modal-box review-box">
    <h3>Review Application</h3>
    <div class="modal-sub">Please review your details before submitting.</div>

    <div class="review-row">
      <div class="review-label">Expert</div>
      <div class="review-value" id="review-expert"></div>
    </div>
    <div class="review-row">
      <div class="review-label">Applying as</div>
      <div class="review-value" id="review-role"></div>
    </div>
    <div class="review-row">
      <div class="review-label">Proposal</div>
      <div class="review-value" id="review-file"></div>
    </div>
      <div class="review-row" style="align-items:flex-start;">
      <div class="review-label" style="padding-top:2px;">Message</div>
      <div class="review-value" id="review-message" style="font-size: 12px; color: var(--text2); display:block; white-space: pre-wrap; word-break: break-word; overflow-wrap: anywhere; line-height: 1.5; width: 100%; max-width: 100%; overflow-y: auto; max-height: 200px; border: 1px solid var(--border); border-radius: 6px; padding: 10px; background: var(--bg2);"></div>
    </div>

    <div class="modal-actions">
      <button class="btn btn-soft" onclick="closeModal('review-modal'); openModal('apply-modal')">Edit</button>
      <button class="btn btn-solid" id="btn-confirm-apply" onclick="confirmApply()">Confirm &amp; Send</button>
    </div>
  </div>
</div>

<!-- Remove Confirmation Modal -->
<div class="modal-overlay" id="remove-modal">
  <div class="modal-box confirm-box remove-box">
    <div class="confirm-icon" style="background: var(--red);">
      <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
    </div>
    <h3>Remove Expert?</h3>
    <div class="confirm-desc">Are you sure you want to remove this expert from your team?</div>
    <div class="confirm-detail" id="remove-detail"></div>

    <div class="remove-field">
      <label>Type <strong>remove</strong> to confirm</label>
      <input type="text" id="remove-confirm-input" class="modal-input" placeholder='type "remove" here' oninput="validateRemoveInput()" autocomplete="off" />
      <div class="field-error" id="err-remove-confirm">Please type "remove" to confirm.</div>
    </div>

    <div class="remove-field">
      <label>Reason for removal <span class="req">*</span></label>
      <textarea id="remove-reason" class="modal-input" rows="3" placeholder="Explain why you're removing this expert..." maxlength="500" oninput="validateRemoveInput()"></textarea>
      <div style="display:flex;justify-content:space-between;margin-top:4px;">
        <div class="field-error" id="err-remove-reason">Please provide a reason.</div>
        <span style="font-size:11px;color:var(--text3);"><span id="remove-reason-count">0</span>/500</span>
      </div>
    </div>

    <div class="modal-actions" style="margin-top: 18px;">
      <button class="btn btn-soft" onclick="closeRemoveModal()">Cancel</button>
      <button class="btn btn-solid-red" id="btn-confirm-remove" onclick="confirmRemove()" disabled>Yes, Remove</button>
    </div>
  </div>
</div>

<!-- Request Expert Info Modal -->
<div class="modal-overlay" id="req-info-modal">
  <div class="modal-box review-box">
    <h3>Request Details</h3>
    <div class="modal-sub" id="req-info-sub"></div>
    <div class="review-row">
      <div class="review-label">Expert</div>
      <div class="review-value" id="req-info-expert"></div>
    </div>
    <div class="review-row" id="req-info-role-row">
      <div class="review-label">Applied as</div>
      <div class="review-value" id="req-info-role"></div>
    </div>
    <div class="review-row">
      <div class="review-label">Status</div>
      <div class="review-value" id="req-info-status"></div>
    </div>
    <div class="review-row" id="req-info-reason-row" style="display:none">
      <div class="review-label">Reason</div>
      <div class="review-value" id="req-info-reason" style="font-size:12px;color:var(--red);"></div>
    </div>
    <div class="review-row">
      <div class="review-label">Message</div>
      <div class="review-value" id="req-info-message" style="font-size:12px;color:var(--text2);"></div>
    </div>
    <div class="review-row">
      <div class="review-label">Proposal</div>
      <div class="review-value" id="req-info-proposal"></div>
    </div>
    <div class="review-row">
      <div class="review-label">Sent</div>
      <div class="review-value" id="req-info-date"></div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-soft" onclick="closeModal('req-info-modal')">Close</button>
    </div>
  </div>
</div>

<div class="msg-toast" id="msg-toast"></div>

<script>
const APPLY_HAS_APPROVED_PROPOSAL = @json((bool) $approvedProposal);
let currentExpertId = null;
let currentRoleId = null;
let currentRoleName = '';
let currentFileName = '';
let currentMessage = '';
let currentRemoveId = null;

// Forces an element to actually be interactive, overriding anything
// external that may have set pointer-events/cursor/disabled on it - some
// browsers/content filters silently zero the opacity and block
// pointer-events of generic full-screen "modal-overlay" elements (mistaken
// for cookie-consent/paywall dark patterns), with no console error at all,
// so the .open class toggle alone isn't reliable.
function forceInteractive(el) {
  if (!el) return;
  el.disabled = false;
  el.removeAttribute('disabled');
  el.removeAttribute('readonly');
  el.readOnly = false;
  el.style.setProperty('pointer-events', 'auto', 'important');
  el.style.setProperty('user-select', 'auto', 'important');
}

function openModal(id) {
  const modal = document.getElementById(id);
  if (!modal) return;
  modal.classList.add('open');
  modal.style.setProperty('display', 'flex', 'important');
  modal.style.setProperty('opacity', '1', 'important');
  modal.style.setProperty('visibility', 'visible', 'important');
  forceInteractive(modal);
  modal.querySelectorAll('input, textarea, select, button, a').forEach(forceInteractive);
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (!modal) return;
  modal.classList.remove('open');
  modal.style.display = 'none';
}

function openExpertPostModal(el) {
  if (window.innerWidth >= 1024) return;
  var name = el.dataset.name;
  var initials = el.dataset.initials;
  var chips = JSON.parse(el.dataset.chips || '[]');
  var tags = JSON.parse(el.dataset.tags || '[]');
  var slots = el.dataset.slots;
  var slotsPct = parseFloat(el.dataset.slotsPct) || 0;
  var slotsColor = el.dataset.slotsColor || 'var(--accent)';

  var epAvatar = document.getElementById('ep-avatar');
  epAvatar.textContent = initials;
  if (el.dataset.avatar) {
    var img = document.createElement('img');
    img.className = 'avatar-photo';
    img.src = el.dataset.avatar;
    img.alt = '';
    img.referrerPolicy = 'no-referrer';
    img.onerror = function () { img.remove(); };
    epAvatar.appendChild(img);
  }
  document.getElementById('ep-name').textContent = name;

  var chipsContainer = document.getElementById('ep-chips');
  chipsContainer.innerHTML = '';
  chips.forEach(function(c) {
    var span = document.createElement('span');
    span.className = 'ep-chip';
    span.style.background = c.bg;
    span.style.color = c.text;
    span.textContent = c.name;
    chipsContainer.appendChild(span);
  });

  var tagsContainer = document.getElementById('ep-tags');
  tagsContainer.innerHTML = '';
  var tagsTotal = tags.length;
  var tagsShow = tags.slice(0, 3);
  var tagsHidden = tags.slice(3);
  tagsShow.forEach(function(t) {
    var span = document.createElement('span');
    span.className = 'ep-tag';
    span.textContent = t;
    tagsContainer.appendChild(span);
  });
  if (tagsHidden.length > 0) {
    var more = document.createElement('span');
    more.className = 'ep-tag ep-tag-more';
    more.textContent = '+' + tagsHidden.length + ' more';
    more.style.cursor = 'pointer';
    more.onclick = function() {
      tagsHidden.forEach(function(t) {
        var span = document.createElement('span');
        span.className = 'ep-tag';
        span.textContent = t;
        tagsContainer.insertBefore(span, more);
      });
      more.remove();
    };
    tagsContainer.appendChild(more);
  }

  document.getElementById('ep-slot-fill').style.width = slotsPct + '%';
  document.getElementById('ep-slot-fill').style.background = slotsColor;
  document.getElementById('ep-slot-text').textContent = slots;
  document.getElementById('ep-slot-text').style.color = slotsPct >= 100 ? 'var(--red)' : 'var(--text3)';

  var actionContainer = document.getElementById('ep-action');
  actionContainer.innerHTML = '';
  var cardBtn = el.querySelector('.btn-full, .btn-sm');
  if (cardBtn) {
    var clone = cardBtn.cloneNode(true);
    clone.removeAttribute('id');
    actionContainer.appendChild(clone);
  }

  openModal('expert-post-modal');
}

function renderRoleChoices(roles) {
  const list = document.getElementById('apply-role-choices');
  const group = document.getElementById('apply-role-group');
  list.innerHTML = '';
  currentRoleId = null;
  currentRoleName = '';

  // With only one role on offer there is nothing to decide, so it is
  // selected up front and shown as a statement rather than a question.
  const single = roles.length === 1;
  group.querySelector('label').innerHTML = single
    ? 'Applying as'
    : 'Apply as <span class="req">*</span>';

  roles.forEach(function (role) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'role-choice';
    btn.dataset.roleId = role.expert_role_id;
    btn.dataset.roleName = role.label;
    btn.innerHTML = '<span class="role-choice-mark"></span>' + role.label;
    btn.onclick = function () { selectRoleChoice(btn); };
    list.appendChild(btn);
  });

  if (single && list.firstChild) selectRoleChoice(list.firstChild);
}

function selectRoleChoice(btn) {
  document.querySelectorAll('#apply-role-choices .role-choice').forEach(function (b) {
    b.classList.remove('selected');
  });
  btn.classList.add('selected');
  currentRoleId = btn.dataset.roleId;
  currentRoleName = btn.dataset.roleName;
  document.getElementById('err-role').classList.remove('show');
}

function openApplyModal(expertId, expertName, roles) {
  currentExpertId = expertId;
  renderRoleChoices(roles || []);
  document.getElementById('apply-expert-name').textContent = 'Apply to ' + expertName;

  // Only there when the team still has to attach a PDF. A team whose
  // pre-proposal the CaPSA approved sends that one instead, so the whole
  // upload field is off the page and every line below it used to throw.
  const fileLabel = document.getElementById('file-label');
  const fileField = document.getElementById('proposal-file');
  if (fileLabel) {
    fileLabel.textContent = 'Choose PDF file...';
    fileLabel.classList.remove('has-file');
  }
  if (fileField) fileField.value = '';

  document.getElementById('apply-message').value = '';
  document.getElementById('msg-count').textContent = '0';
  document.querySelectorAll('#apply-modal .field-error').forEach(e => e.classList.remove('show'));
  document.getElementById('btn-review-apply').disabled = false;
  openModal('apply-modal');
}

function handleFileSelect(input) {
  const label = document.getElementById('file-label');
  if (!label) return;
  if (input.files && input.files[0]) {
    label.textContent = input.files[0].name;
    label.classList.add('has-file');
  } else {
    label.textContent = 'Choose PDF file...';
    label.classList.remove('has-file');
  }
}

document.addEventListener('DOMContentLoaded', function() {
  var msgInput = document.getElementById('apply-message');
  if (msgInput) {
    msgInput.addEventListener('input', function() {
      var text = this.value.trim();
      var words = text ? text.split(/\s+/).length : 0;
      document.getElementById('msg-count').textContent = words;
      if (words > 500) {
        var trimmed = text.split(/\s+/).slice(0, 500).join(' ');
        this.value = trimmed;
        document.getElementById('msg-count').textContent = 500;
      }
    });
  }
  var removeReason = document.getElementById('remove-reason');
  if (removeReason) {
    removeReason.addEventListener('input', function() {
      document.getElementById('remove-reason-count').textContent = this.value.length;
    });
  }
});

function reviewApply() {
  const fileInput = document.getElementById('proposal-file');
  const message = document.getElementById('apply-message').value.trim();
  let valid = true;

  document.querySelectorAll('#apply-modal .field-error').forEach(e => e.classList.remove('show'));

  if (!currentRoleId) {
    document.getElementById('err-role').classList.add('show');
    valid = false;
  }
  if (!APPLY_HAS_APPROVED_PROPOSAL && (!fileInput || !fileInput.files || !fileInput.files[0])) {
    const fileError = document.getElementById('err-file');
    if (fileError) fileError.classList.add('show');
    valid = false;
  }
  if (!message) {
    document.getElementById('err-message').classList.add('show');
    valid = false;
  } else {
    var wordCount = message.split(/\s+/).length;
    if (wordCount > 500) {
      document.getElementById('err-message').textContent = 'Message exceeds 500 words.';
      document.getElementById('err-message').classList.add('show');
      valid = false;
    }
  }

  if (!valid) return;

  // '+' here, not PHP's '.': this is JavaScript by the time the browser
  // sees it, and the directive below has already rendered a quoted string.
  currentFileName = APPLY_HAS_APPROVED_PROPOSAL
    ? @json($approvedProposal?->title ?? '') + ' (approved pre-proposal)'
    : fileInput.files[0].name;
  currentMessage = message;

  document.getElementById('review-expert').textContent = document.getElementById('apply-expert-name').textContent.replace('Apply to ', '');
  document.getElementById('review-role').textContent = currentRoleName;
  document.getElementById('review-file').textContent = currentFileName;
  document.getElementById('review-message').textContent = message;

  closeModal('apply-modal');
  openModal('review-modal');
}

function confirmApply() {
  const btn = document.getElementById('btn-confirm-apply');
  btn.disabled = true;
  btn.textContent = 'Sending...';

  const formData = new FormData();
  formData.append('expert_id', currentExpertId);
  formData.append('expert_role_id', currentRoleId);
  var pf = document.getElementById('proposal-file');
  if (pf && pf.files && pf.files[0]) {
    formData.append('proposal_file', pf.files[0]);
  }
  formData.append('message', currentMessage);

  fetch('{{ route("api.student.assignments.store") }}', {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Accept': 'application/json',
    },
    body: formData,
  })
  .then(r => r.json())
  .then(data => {
    closeModal('review-modal');
    if (data.success) {
      showToast(data.message, 'success');
      setTimeout(() => location.reload(), 1200);
    } else {
      showToast(data.message || 'Failed to apply', 'error');
      btn.disabled = false;
      btn.textContent = 'Confirm & Send';
    }
  })
  .catch(err => {
    closeModal('review-modal');
    showToast('Network error. Please try again.', 'error');
    btn.disabled = false;
    btn.textContent = 'Confirm & Send';
  });
}

/** Titles are written by students, so they are escaped before going in. */
function escapeHtmlText(value) {
  const div = document.createElement('div');
  div.textContent = value == null ? '' : String(value);

  return div.innerHTML;
}

function openReqInfoModal(data) {
  document.getElementById('req-info-sub').textContent = 'Information about this expert request.';
  document.getElementById('req-info-expert').textContent = data.name;
  document.getElementById('req-info-role').textContent = data.role || '';
  document.getElementById('req-info-role-row').style.display = data.role ? '' : 'none';
  const statusColors = { Approved: 'var(--green)', Pending: 'var(--amber)', Rejected: 'var(--red)' };
  document.getElementById('req-info-status').innerHTML = '<span style="color:' + (statusColors[data.status] || 'var(--text3)') + ';font-weight:600;">' + data.status + '</span>';
  var reasonRow = document.getElementById('req-info-reason-row');
  if (data.rejection_reason) {
    document.getElementById('req-info-reason').textContent = data.rejection_reason;
    reasonRow.style.display = '';
  } else {
    reasonRow.style.display = 'none';
  }
  document.getElementById('req-info-message').textContent = data.message;
  const proposalEl = document.getElementById('req-info-proposal');
  if (data.pre_proposal_title) {
    // Named, not linked: the team already has this on their Duplication
    // Checker page, and there is no student-facing route to the file.
    proposalEl.innerHTML =
      '<div style="color:var(--green);font-weight:600;font-size:12px">&#10003; CaPSA-approved pre-proposal</div>'
      + '<div style="font-size:12px;color:var(--text2);margin-top:2px">' + escapeHtmlText(data.pre_proposal_title) + '</div>';
  } else if (data.proposal) {
    proposalEl.innerHTML = '<a href="' + data.proposal + '" target="_blank" style="color:var(--accent);">View Proposal <svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" fill="none" stroke-width="2" style="display:inline;vertical-align:middle;"><path d="M7 17l9.2-9.2M17 17V7H7"/></svg></a>';
  } else {
    proposalEl.textContent = 'None attached';
  }
  document.getElementById('req-info-date').textContent = data.date;
  openModal('req-info-modal');
}

function openRemoveModal(assignmentId, expertName) {
  currentRemoveId = assignmentId;
  document.getElementById('remove-detail').innerHTML =
    '<strong>' + expertName + '</strong><br><span style="font-size:11px;color:var(--red);">This action cannot be undone.</span>';
  document.getElementById('remove-confirm-input').value = '';
  document.getElementById('remove-confirm-input').classList.remove('error');
  document.getElementById('err-remove-confirm').classList.remove('show');
  document.getElementById('remove-reason').value = '';
  document.getElementById('remove-reason').classList.remove('error');
  document.getElementById('err-remove-reason').classList.remove('show');
  document.getElementById('remove-reason-count').textContent = '0';
  document.getElementById('btn-confirm-remove').disabled = true;
  openModal('remove-modal');
}

function closeRemoveModal() {
  closeModal('remove-modal');
}

function validateRemoveInput() {
  var confirmInput = document.getElementById('remove-confirm-input').value.trim();
  var reason = document.getElementById('remove-reason').value.trim();
  var confirmOk = confirmInput.toLowerCase() === 'remove';
  var reasonOk = reason.length > 0;

  document.getElementById('remove-confirm-input').classList.toggle('error', confirmInput.length > 0 && !confirmOk);
  document.getElementById('err-remove-confirm').classList.toggle('show', confirmInput.length > 0 && !confirmOk);
  document.getElementById('remove-reason').classList.toggle('error', reason.length > 0 && !reasonOk);
  document.getElementById('err-remove-reason').classList.toggle('show', reason.length > 0 && !reasonOk);

  document.getElementById('btn-confirm-remove').disabled = !(confirmOk && reasonOk);
}

function confirmRemove() {
  if (!currentRemoveId) return;

  var reason = document.getElementById('remove-reason').value.trim();
  if (!reason) return;

  var btn = document.getElementById('btn-confirm-remove');
  btn.disabled = true;
  btn.textContent = 'Removing...';

  fetch('/api/assignments/' + currentRemoveId, {
    method: 'DELETE',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ reason: reason }),
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    closeModal('remove-modal');
    if (data.success) {
      showToast(data.message, 'success');
      setTimeout(function() { location.reload(); }, 1200);
    } else {
      showToast(data.message || 'Failed to remove', 'error');
      btn.disabled = false;
      btn.textContent = 'Yes, Remove';
    }
  })
  .catch(function() {
    closeModal('remove-modal');
    showToast('Network error. Please try again.', 'error');
    btn.disabled = false;
    btn.textContent = 'Yes, Remove';
  });
}

function showToast(msg, type) {
  const toast = document.getElementById('msg-toast');
  toast.textContent = msg;
  toast.className = 'msg-toast show ' + type;
  setTimeout(() => toast.classList.remove('show'), 4000);
}

function setFilter(role, el) {
  document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
  el.classList.add('active');
  filterExperts();
}

function filterExperts() {
  const query = document.getElementById('search-input').value.toLowerCase();
  const activeRole = document.querySelector('.filter-chip.active')?.dataset.role || 'all';
  const cards = document.querySelectorAll('.expert-card-compact');

  cards.forEach(card => {
    const nameEl = card.querySelector('[style*="font-weight: 700"]');
    const name = nameEl?.textContent?.toLowerCase() || '';
    const roles = (card.dataset.roles || '').toLowerCase();
    const matchesSearch = name.includes(query);
    const matchesRole = activeRole === 'all' || roles.split(',').includes(activeRole);
    card.style.display = (matchesSearch && matchesRole) ? '' : 'none';
  });
}

// ── Skill picker ──
function toggleSkillPicker(event) {
  if (event) event.stopPropagation();
  var wrap = document.querySelector('.skill-filter');
  if (!wrap) return;
  var opening = !wrap.classList.contains('open');
  wrap.classList.toggle('open', opening);
  document.getElementById('skill-filter-trigger').setAttribute('aria-expanded', opening ? 'true' : 'false');
  if (opening) document.getElementById('skillSearch').focus();
}

function toggleSkillGroup(header) {
  header.closest('.skill-group').classList.toggle('open');
}

// Typing here narrows the picker itself. It does not filter the expert grid -
// that only changes when a skill is chosen, so the page never half-applies a
// search the student is still typing.
function filterSkillPicker(query) {
  var q = (query || '').trim().toLowerCase();
  var anyVisible = false;

  document.querySelectorAll('#skillPickerList .skill-group').forEach(function (group) {
    var groupMatches = q !== '' && (group.dataset.name || '').includes(q);
    var shown = 0;

    group.querySelectorAll('.skill-opt').forEach(function (opt) {
      // "Anything in this area" belongs to the group, not to a skill name.
      var isGroupOpt = opt.classList.contains('skill-opt-group');
      var match = q === '' || groupMatches || (opt.dataset.name || '').includes(q);
      if (isGroupOpt) match = q === '' || groupMatches;
      opt.style.display = match ? '' : 'none';
      if (match && !isGroupOpt) shown++;
    });

    var visible = shown > 0 || groupMatches;
    group.style.display = visible ? '' : 'none';
    group.classList.toggle('open', q !== '' ? visible : group.classList.contains('open'));
    if (visible) anyVisible = true;
  });

  var all = document.querySelector('#skillPickerList .skill-opt-all');
  if (all) all.style.display = q === '' ? '' : 'none';

  var none = document.getElementById('skillPickerNoMatch');
  if (none) none.style.display = anyVisible || q === '' ? 'none' : '';
}

document.addEventListener('click', function (e) {
  var wrap = document.querySelector('.skill-filter');
  if (wrap && wrap.classList.contains('open') && !wrap.contains(e.target)) {
    wrap.classList.remove('open');
    document.getElementById('skill-filter-trigger').setAttribute('aria-expanded', 'false');
  }
});

document.addEventListener('keydown', function (e) {
  if (e.key !== 'Escape') return;
  var wrap = document.querySelector('.skill-filter');
  if (wrap) {
    wrap.classList.remove('open');
    var t = document.getElementById('skill-filter-trigger');
    if (t) t.setAttribute('aria-expanded', 'false');
  }
});

// Arriving from the duplication report's "Apply" link: find that expert's
// card and open the apply modal on it. The card may not have an apply button
// (already engaged, full, or the team isn't verified yet), in which case the
// card itself explains why - so scroll to it and say so rather than failing
// silently.
document.addEventListener('DOMContentLoaded', function () {
  var wanted = new URLSearchParams(window.location.search).get('apply');
  if (!wanted) return;

  var card = document.querySelector('.expert-card-compact[data-expert-id="' + CSS.escape(wanted) + '"]');
  var btn = card ? card.querySelector('[data-apply-expert]') : null;

  if (!card) {
    // Not on this page of results - a skill filter or pagination can hide it.
    showToast('That expert is not in the current list. Clear any filters to find them.', 'error');
    return;
  }

  card.scrollIntoView({ behavior: 'smooth', block: 'center' });
  card.style.outline = '2px solid var(--accent)';
  card.style.outlineOffset = '3px';
  setTimeout(function () { card.style.outline = ''; }, 2600);

  if (btn && !btn.disabled) {
    setTimeout(function () { btn.click(); }, 450);
  } else {
    var why = card.querySelector('button[disabled]');
    showToast(why ? 'You cannot apply to this expert right now: ' + why.textContent.trim() : 'You cannot apply to this expert right now.', 'error');
  }
});

// ── Mobile Sidebar ──
function toggleSidebar() {
  document.body.classList.toggle('sidebar-open');
  document.body.style.overflow = document.body.classList.contains('sidebar-open') ? 'hidden' : '';
}
function closeSidebar() {
  document.body.classList.remove('sidebar-open');
  document.body.style.overflow = '';
}
document.querySelectorAll('.nav-item').forEach(function(el) {
  el.addEventListener('click', function() {
    if (window.innerWidth <= 768) closeSidebar();
  });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var sk = document.getElementById('skeleton-content');
  var rc = document.getElementById('real-content');
  var scroller = document.querySelector('.content');
  if (sk && rc) {
    // partials/skeleton-head already decided this in <head>, before the first
    // paint: the content is on screen and no skeleton was ever shown. Without
    // this, every save replayed the 600ms fade over content already there.
    if (document.documentElement.classList.contains('sk-seen')) {
      sk.remove();

      return;
    }

    // #real-content stays in normal flow at opacity:0 (not display:none, to
    // avoid a layout jump on reveal) — but that means its full real height
    // already counts toward .content's scrollable area while only the
    // skeleton is visible. If real content is taller than the skeleton
    // placeholder, that produced a scrollbar you could drag into blank
    // space below the skeleton. Locking scroll during loading and
    // restoring it on reveal avoids that.
    var prevOverflow = scroller ? scroller.style.overflowY : '';
    if (scroller) scroller.style.overflowY = 'hidden';

    requestAnimationFrame(function () {
      setTimeout(function () {
        sk.style.transition = 'opacity 0.3s ease';
        sk.style.opacity = '0';
        rc.style.transition = 'opacity 0.35s ease';
        rc.style.opacity = '1';
        if (scroller) scroller.style.overflowY = prevOverflow || 'auto';
        setTimeout(function () {
          sk.remove();
          rc.style.transition = '';
        }, 350);
        try {
          sessionStorage.setItem('sk-seen:{{ substr(sha1(session()->getId()), 0, 12) }}:' + window.location.pathname, '1');
        } catch (e) {}
      }, 600);
    });
  }
});
</script>
</body>
</html>
