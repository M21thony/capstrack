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
.stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }
.stat-card { background: var(--bg2); border: 1px solid var(--border); border-radius: 14px; padding: 18px 20px; display: flex; align-items: center; gap: 14px; }
.stat-card-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.stat-card-icon svg { width: 20px; height: 20px; stroke: white; fill: none; stroke-width: 2; }
.stat-card-n { font-size: 24px; font-weight: 700; color: var(--text); line-height: 1; }
.stat-card-l { font-size: 12px; color: var(--text3); margin-top: 4px; }
.role-chips { display: flex; flex-wrap: wrap; gap: 5px; margin: 10px 0 8px; justify-content: center; }
.role-chip { font-size: 10px; font-weight: 600; padding: 3px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.4px; }
.expertise-tags { display: flex; flex-wrap: wrap; gap: 4px; justify-content: center; margin-bottom: 8px; }
.expertise-tag { font-size: 10px; color: var(--text3); background: var(--bg3); padding: 2px 8px; border-radius: 4px; }

/* My Team / Request Experts cards */
.req-section { margin-top: 8px; }
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

.role-overview { background: var(--bg2); border: 1px solid var(--border); border-radius: 14px; padding: 18px 20px; margin-bottom: 20px; }
.role-overview-title { font-size: 14px; font-weight: 600; color: var(--text); margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
.role-overview-title svg { width: 18px; height: 18px; stroke: var(--accent); fill: none; stroke-width: 2; }
.role-overview-row { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border); }
.role-overview-row:last-child { border-bottom: none; }
.role-overview-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.role-overview-role { font-size: 12px; font-weight: 600; color: var(--text2); width: 100px; flex-shrink: 0; text-transform: uppercase; letter-spacing: 0.3px; }
.role-overview-status { display: flex; align-items: center; gap: 6px; flex: 1; min-width: 0; }
.role-overview-status .check { width: 16px; height: 16px; stroke: var(--green); fill: none; stroke-width: 2.5; flex-shrink: 0; }
.role-overview-status .xmark { width: 16px; height: 16px; stroke: var(--red); fill: none; stroke-width: 2.5; flex-shrink: 0; }
.role-overview-status .assigned-name { font-size: 13px; font-weight: 500; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
.role-overview-status .not-assigned { font-size: 12px; color: var(--text3); font-style: italic; }
</style>
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

  <div class="content" style="position: relative;">

    <!-- ── SKELETON LOADER ── -->
    <div id="skeleton-content" class="sk-overlay">
      <div class="experts-main">
        <div class="stat-grid">
          @for($i = 0; $i < 3; $i++)
          <div class="stat-card">
            <div class="sk" style="width:42px;height:42px;border-radius:12px;flex-shrink:0;"></div>
            <div style="flex:1">
              <div class="sk" style="height:24px;width:36px;margin-bottom:6px;"></div>
              <div class="sk" style="height:12px;width:110px;"></div>
            </div>
          </div>
          @endfor
        </div>

        @if($team)
        <div class="role-overview">
          <div class="sk" style="height:18px;width:180px;margin-bottom:14px;"></div>
          @foreach(['','',''] as $r)
          <div class="role-overview-row">
            <div class="sk" style="width:10px;height:10px;border-radius:50%;"></div>
            <div class="sk" style="height:13px;width:90px;"></div>
            <div class="sk" style="width:16px;height:16px;border-radius:50%;margin-left:auto;"></div>
          </div>
          @endforeach
        </div>
        @endif

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

        <div class="req-section">
          <div class="sk" style="height:15px;width:160px;margin-bottom:14px;"></div>
          @for($i = 0; $i < 2; $i++)
          <div class="req-card">
            <div class="sk" style="width:36px;height:36px;border-radius:50%;flex-shrink:0;"></div>
            <div style="flex:1">
              <div class="sk" style="width:50%;height:12px;"></div>
            </div>
            <div class="sk" style="width:60px;height:18px;border-radius:20px;margin-left:auto;"></div>
          </div>
          @endfor
        </div>
      </div>

      <div class="overview-panel">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items: start;">
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
    <div id="real-content" style="opacity:0">
      @include('student.partials.page-tutorial', ['page' => 'experts'])

    <div class="experts-main">
      <div class="stat-grid">
        <div class="stat-card">
          <div class="stat-card-icon" style="background: var(--accent);">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
          </div>
          <div>
            <div class="stat-card-n">{{ $stats['total_experts'] }}</div>
            <div class="stat-card-l">Available Experts</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon" style="background: var(--green);">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <div>
            <div class="stat-card-n">{{ $stats['team_approved'] }}</div>
            <div class="stat-card-l">My Team Experts</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon" style="background: var(--amber);">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          </div>
          <div>
            <div class="stat-card-n">{{ $stats['team_pending'] }}</div>
            <div class="stat-card-l">Pending Requests</div>
          </div>
        </div>
      </div>

      @if($team)
      <div class="role-overview">
        <div class="role-overview-title">
          <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><path d="M12 2a10 10 0 1 0 10 10"/></svg>
          Your Team's Experts
        </div>
        @php $approvedAssignments = $assignments->where('status', 'approved'); @endphp
        @forelse($approvedAssignments as $a)
          <div class="role-overview-row">
            <div class="role-overview-dot" style="background: var(--accent);"></div>
            <div class="role-overview-role">{{ $a->expert?->name ?? 'Unknown' }}</div>
            <div class="role-overview-status">
              <svg class="check" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              <span class="assigned-name">Assigned</span>
              @if($isLeader)
                <button class="btn btn-sm btn-soft-red" onclick="openRemoveModal({{ $a->assignment_id }}, '{{ $a->expert?->name ?? '' }}')" title="Remove" style="margin-left:auto;padding:4px 8px;">
                  <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
              @endif
            </div>
          </div>
        @empty
          <div class="not-assigned" style="padding: 6px 0;">No experts assigned yet.</div>
        @endforelse
      </div>
      @endif

      <div class="sticky-controls">
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <input class="input" id="search-input" placeholder="Search experts..." style="flex: 1; max-width: 320px;" oninput="filterExperts()" />
          <div class="filter-bar" style="margin-bottom: 0;">
            <div class="filter-chip active" data-role="all" onclick="setFilter('all', this)">All</div>
            <div class="filter-chip" data-role="adviser" onclick="setFilter('adviser', this)">Advisers</div>
            <div class="filter-chip" data-role="consultant" onclick="setFilter('consultant', this)">Consultants</div>
            <div class="filter-chip" data-role="grammarian" onclick="setFilter('grammarian', this)">Grammarian</div>
          </div>
        </div>
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
            $_maxSlots = $expert->max_teams ?? 4;
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
          <div class="expert-card-compact" style="--accent-clr:{{ $dotColors[$firstRole?->role_name ?? 'adviser'] ?? 'var(--accent)' }}" data-roles="{{ $visibleRoles->pluck('role_name')->join(',') }}" data-name="{{ $expert->name }}" data-initials="{{ $expert->avatarInitials() }}" data-chips='{{ json_encode($_chips) }}' data-tags='{{ json_encode($_expertiseTags) }}' data-tags-more="{{ $_expertiseMore }}" data-slots="{{ $_slotsUsed }}/{{ $_maxSlots }}" data-slots-pct="{{ $_slotPercent }}" data-slots-color="{{ $_barColor }}" onclick="openExpertPostModal(this)">
            <div class="role-dot" style="background: {{ $dotColors[$firstRole?->role_name ?? 'adviser'] ?? 'var(--accent)' }};"></div>
            <div class="card-accent" style="background:{{ $dotColors[$firstRole?->role_name ?? 'adviser'] ?? 'var(--accent)' }};"></div>
            <div class="expert-avatar-wrap">
              <div class="avatar" style="width:100%; height:100%; font-size:18px;">{{ $expert->avatarInitials() }}</div>
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
                $alreadyAssignedRoleIds = $teamAssignmentsForExpert->pluck('role_id')->toArray();
                $availableRoles = $visibleRoles->reject(fn($r) => in_array($r->role_id, $alreadyAssignedRoleIds));
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
                <button class="btn btn-sm btn-full btn-solid" onclick="openApplyModal({{ $expert->expert_id }}, '{{ $expert->name }}')">
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
            <p>No approved experts available yet.</p>
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

      <div class="req-section">
        <div class="section-title" style="font-size: 15px; font-weight: 600; margin-bottom: 14px; display: flex; align-items: center; gap: 8px;">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          Request Experts
          @if($assignments->isNotEmpty())
            <span style="font-size: 12px; color: var(--text3); font-weight: 400;">({{ $assignments->count() }})</span>
          @endif
        </div>

        @forelse($assignments as $assignment)
          @php
            $propUrl = $assignment->proposal_file ? Storage::url($assignment->proposal_file) : null;
          @endphp
          <div class="req-card">
            <div class="avatar" style="width: 36px; height: 36px; font-size: 12px; background: var(--accent); color: white;">
              {{ $assignment->expert?->avatarInitials() ?? 'NA' }}
            </div>
            <div style="flex: 1; min-width: 0;">
              <div style="font-size: 13px; font-weight: 600; color: var(--text);">{{ $assignment->expert?->name ?? 'Unknown Expert' }}</div>
              <div style="font-size: 11px; color: var(--text3); text-transform: uppercase;">{{ ucfirst($assignment->status) }}</div>
            </div>
            <span class="status-badge" style="background: {{ $assignment->status === 'approved' ? 'var(--green-bg)' : ($assignment->status === 'pending' ? 'var(--amber-bg)' : 'var(--red-bg)') }}; color: {{ $assignment->status === 'approved' ? 'var(--green)' : ($assignment->status === 'pending' ? 'var(--amber)' : 'var(--red)') }};">
              {{ ucfirst($assignment->status) }}
            </span>
            <button class="btn btn-sm btn-soft" onclick="openReqInfoModal({{ json_encode([
              'name' => $assignment->expert?->name ?? 'Unknown',
              'status' => ucfirst($assignment->status),
              'message' => $assignment->message ?? 'No message provided.',
              'proposal' => $propUrl,
              'date' => $assignment->created_at ? $assignment->created_at->format('M d, Y g:i A') : 'N/A',
            ]) }})" title="View details">View</button>
            @if($isLeader && $assignment->status !== 'approved')
              <button class="btn btn-sm btn-soft-red" onclick="openRemoveModal({{ $assignment->assignment_id }}, '{{ $assignment->expert?->name ?? '' }}')" title="Remove">
                <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              </button>
            @endif
          </div>
        @empty
          <div style="text-align: center; padding: 24px; color: var(--text3); font-size: 13px; background: var(--bg3); border-radius: 10px; border: 1px dashed var(--border);">
            @if($team)
              No experts requested yet. Browse and apply for experts above.
            @else
              You need to join or create a team first before applying for experts.
            @endif
          </div>
        @endforelse
      </div>

      <div class="tutorial-note" style="justify-content: flex-start; border-top:none; margin-top:15px;">
        <div class="tutorial-item"><div class="dot-guide" style="background:var(--accent)"></div><span>Adviser</span></div>
        <div class="tutorial-item"><div class="dot-guide" style="background:#3b82f6"></div><span>Consultant</span></div>
        <div class="tutorial-item"><div class="dot-guide" style="background:#9333ea"></div><span>Grammarian</span></div>
      </div>
    </div>

    <div class="overview-panel">
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items: start;">
        <div class="card" style="padding: 14px; border-radius: 12px; background: var(--bg2);">
          <div class="section-title" style="font-size: 13px; margin-bottom: 12px;">Role Guide</div>
          <div style="margin-bottom: 12px;">
            <div style="font-size: 11px; font-weight: 600; color: var(--accent3); margin-bottom: 4px;">Adviser</div>
            <div style="font-size: 10px; color: var(--text3); line-height: 1.4;">Guides capstone teams throughout their project journey</div>
          </div>
          <div style="margin-bottom: 12px;">
            <div style="font-size: 11px; font-weight: 600; color: #3b82f6; margin-bottom: 4px;">Consultant</div>
            <div style="font-size: 10px; color: var(--text3); line-height: 1.4;">Provides subject-matter expertise and technical advice</div>
          </div>
          <div style="margin-bottom: 12px;">
            <div style="font-size: 11px; font-weight: 600; color: #9333ea; margin-bottom: 4px;">Grammarian</div>
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
            <li>You can apply for multiple roles from the same expert</li>
            <li>Attach your approved pre-proposal PDF when applying</li>
            <li>Check the slot status before applying</li>
          </ul>
        </div>
      </div>

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

    <div class="form-group">
      <label>Approved Pre-Proposal <span class="req">*</span></label>
      <div class="file-input-wrap">
        <svg class="file-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="12" y2="12"/><line x1="15" y1="15" x2="12" y2="12"/></svg>
        <span class="file-label" id="file-label">Choose PDF file...</span>
        <input type="file" id="proposal-file" accept=".pdf,application/pdf" onchange="handleFileSelect(this)" />
      </div>
      <div class="field-error" id="err-file">Please upload your approved pre-proposal (PDF).</div>
    </div>

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
    <div class="review-row">
      <div class="review-label">Status</div>
      <div class="review-value" id="req-info-role"></div>
    </div>
    <div class="review-row">
      <div class="review-label">Status</div>
      <div class="review-value" id="req-info-status"></div>
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
let currentExpertId = null;
let currentRoleName = '';
let currentFileName = '';
let currentMessage = '';
let currentRemoveId = null;

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function openExpertPostModal(el) {
  if (window.innerWidth >= 1024) return;
  var name = el.dataset.name;
  var initials = el.dataset.initials;
  var chips = JSON.parse(el.dataset.chips || '[]');
  var tags = JSON.parse(el.dataset.tags || '[]');
  var slots = el.dataset.slots;
  var slotsPct = parseFloat(el.dataset.slotsPct) || 0;
  var slotsColor = el.dataset.slotsColor || 'var(--accent)';

  document.getElementById('ep-avatar').textContent = initials;
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

function openApplyModal(expertId, expertName) {
  currentExpertId = expertId;
  document.getElementById('apply-expert-name').textContent = 'Apply to ' + expertName;
  document.getElementById('file-label').textContent = 'Choose PDF file...';
  document.getElementById('file-label').classList.remove('has-file');
  document.getElementById('proposal-file').value = '';
  document.getElementById('apply-message').value = '';
  document.getElementById('msg-count').textContent = '0';
  document.querySelectorAll('#apply-modal .field-error').forEach(e => e.classList.remove('show'));
  document.getElementById('btn-review-apply').disabled = false;
  openModal('apply-modal');
}

function handleFileSelect(input) {
  const label = document.getElementById('file-label');
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

  if (!fileInput.files || !fileInput.files[0]) {
    document.getElementById('err-file').classList.add('show');
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

  currentFileName = fileInput.files[0].name;
  currentMessage = message;

  document.getElementById('review-expert').textContent = document.getElementById('apply-expert-name').textContent.replace('Apply to ', '');
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
  formData.append('proposal_file', document.getElementById('proposal-file').files[0]);
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

function openReqInfoModal(data) {
  document.getElementById('req-info-sub').textContent = 'Information about this expert request.';
  document.getElementById('req-info-expert').textContent = data.name;
  document.getElementById('req-info-role').textContent = data.status;
  const statusColors = { Approved: 'var(--green)', Pending: 'var(--amber)', Rejected: 'var(--red)' };
  document.getElementById('req-info-status').innerHTML = '<span style="color:' + (statusColors[data.status] || 'var(--text3)') + ';font-weight:600;">' + data.status + '</span>';
  document.getElementById('req-info-message').textContent = data.message;
  document.getElementById('req-info-proposal').innerHTML = data.proposal
    ? '<a href="' + data.proposal + '" target="_blank" style="color:var(--accent);">View Proposal <svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" fill="none" stroke-width="2" style="display:inline;vertical-align:middle;"><path d="M7 17l9.2-9.2M17 17V7H7"/></svg></a>'
    : 'None attached';
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
  if (sk && rc) {
    requestAnimationFrame(function () {
      setTimeout(function () {
        sk.style.transition = 'opacity 0.3s ease';
        sk.style.opacity = '0';
        rc.style.transition = 'opacity 0.35s ease';
        rc.style.opacity = '1';
        setTimeout(function () {
          sk.remove();
          rc.style.transition = '';
        }, 350);
      }, 600);
    });
  }
});
</script>
</body>
</html>
