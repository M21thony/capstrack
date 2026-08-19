<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Expert Management - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<style>
  /* Expert Management: role badges + action buttons */
  .expert-role-badges{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    align-items:center;
  }
  .expert-role-stack{
    display:inline-flex;
    flex-direction:column;
    gap:5px;
    width:fit-content;
    max-width:100%;
  }
  .expert-role-badge{
    font-size:10px;
    font-weight:600;
    padding:5px 10px;
    letter-spacing:0;
    text-transform:none;
    border-radius:999px;
    box-shadow:none;
  }
  .expert-role-meta{
    display:inline-flex;
    align-items:center;
    gap:6px;
    width:fit-content;
    font-size:10px;
    font-weight:600;
    color:var(--text3);
    text-transform:none;
    letter-spacing:0;
  }
  .expert-role-meta::before{
    content:'';
    width:4px;
    height:4px;
    border-radius:999px;
    background:var(--accent);
  }

  .expert-actions{
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:8px;
  }
  .expert-action-note{
    font-size:11px;
    font-weight:600;
  }


  .btn-remove {
    display: inline-flex;
    align-items: center;
    justify-content:center;
    gap: 7px;
    min-height: 32px;
    font-size: 12px;
    font-weight: 600;
    padding: 7px 14px;
    border-radius: 999px;
    cursor: pointer;
    transition: transform 0.12s ease, box-shadow 0.12s ease, background 0.12s ease, border-color 0.12s ease, color 0.12s ease;
    user-select: none;
    border: 1.5px solid transparent;
    white-space: nowrap;
    box-shadow: none;
  }

  .btn-remove {
    background: rgba(220, 38, 38, 0.06);
    color: var(--red);
    border-color: rgba(220, 38, 38, 0.16);
  }
  .btn-remove:hover {
    background: rgba(220, 38, 38, 0.1);
    border-color: rgba(220, 38, 38, 0.28);
  }

  .btn-remove:active {
    transform: translateY(1px);
  }

  .btn-remove:focus-visible{
    outline: 2px solid rgba(220, 38, 38, 0.2);
    outline-offset: 2px;
  }

  .btn-remove svg{
    width:12px;
    height:12px;
    flex-shrink:0;
    stroke:currentColor;
    fill:none;
    stroke-width:2.2;
  }

  .btn-transfer:disabled,
  .btn-remove:disabled {
    opacity: 0.55;
    cursor: not-allowed;
    transform: none;
  }


  /* ── Modal overlay ── */
  .ct-modal-overlay {
    position: fixed; inset: 0;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(5px);
    display: flex; align-items: center; justify-content: center;
    z-index: 9999;
    animation: ctFadeIn 0.18s ease;
  }

  /* ── Modal card ── */
  .ct-modal-card {
    background: #ffffff;
    width: 100%; max-width: 400px;
    border-radius: 16px;
    box-shadow: 0 24px 48px -8px rgba(0,0,0,0.18), 0 0 0 1px rgba(0,0,0,0.06);
    padding: 32px 28px 24px;
    text-align: center;
    animation: ctPopUp 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  }

  /* ── Modal icon circle ── */
  .ct-modal-icon {
    width: 52px; height: 52px;
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
  }
  .ct-modal-icon.danger  { background: #fee2e2; color: #ef4444; }
  .ct-modal-icon.warning { background: #fef9c3; color: #d97706; }

  /* ── Modal text ── */
  .ct-modal-title {
    margin: 0 0 8px;
    font-size: 18px; font-weight: 700; color: #0f172a;
  }
  .ct-modal-desc {
    font-size: 13.5px; color: #64748b; line-height: 1.5;
    margin: 0 0 20px;
  }

  /* ── Text input in modal ── */
  .ct-modal-label {
    display: block;
    font-size: 11px; font-weight: 700; color: #94a3b8;
    text-transform: uppercase; letter-spacing: 0.05em;
    text-align: left; margin-bottom: 6px;
  }
  .ct-modal-input {
    width: 100%; box-sizing: border-box;
    padding: 10px 13px;
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    background: #f8fafc; color: #1e293b;
    font-size: 13px; outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
    margin-bottom: 20px;
  }
  .ct-modal-input:focus {
    border-color: #94a3b8;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.2);
  }

  /* ── Modal action buttons ── */
  .ct-modal-actions { display: flex; gap: 10px; }
  .ct-btn {
    flex: 1;
    padding: 10px 16px;
    font-size: 13px; font-weight: 600;
    border-radius: 8px; border: none;
    cursor: pointer; transition: all 0.12s ease;
    display: inline-flex; align-items: center; justify-content: center;
  }
  .ct-btn-secondary { background: #f1f5f9; color: #475569; }
  .ct-btn-secondary:hover { background: #e2e8f0; color: #1e293b; }
  .ct-btn-danger { background: #dc2626; color: #fff; }
  .ct-btn-danger:hover:not(:disabled) { background: #b91c1c; }
  .ct-btn-danger:disabled { background: #f1f5f9; color: #cbd5e1; cursor: not-allowed; }
  .ct-btn-warning { background: #d97706; color: #fff; }
  .ct-btn-warning:hover { background: #b45309; }

  /* ── Animations ── */
  @keyframes ctFadeIn { from { opacity: 0; } to { opacity: 1; } }
  @keyframes ctPopUp  { from { transform: scale(0.94) translateY(8px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }

  /* ── Toast Notifications ── */
  #ct-toast-container {
    position: fixed;
    bottom: 24px; right: 24px;
    z-index: 99999;
    display: flex; flex-direction: column; gap: 10px;
    align-items: flex-end;
  }
  .ct-toast {
    display: flex; align-items: flex-start; gap: 12px;
    min-width: 280px; max-width: 360px;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12), 0 0 0 1px rgba(0,0,0,0.06);
    padding: 14px 16px;
    animation: toastIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    transition: opacity 0.2s, transform 0.2s;
  }
  .ct-toast.hiding { opacity: 0; transform: translateX(20px); }
  .ct-toast-icon {
    width: 36px; height: 36px; flex-shrink: 0;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
  }
  .ct-toast-icon.success { background: #dcfce7; color: #16a34a; }
  .ct-toast-icon.error   { background: #fee2e2; color: #dc2626; }
  .ct-toast-icon.info    { background: #dbeafe; color: #2563eb; }
  .ct-toast-body { flex: 1; }
  .ct-toast-title { font-size: 13px; font-weight: 700; color: #0f172a; margin: 0 0 2px; }
  .ct-toast-msg   { font-size: 12.5px; color: #64748b; margin: 0; line-height: 1.4; }
  .ct-toast-close {
    background: none; border: none; cursor: pointer;
    color: #94a3b8; font-size: 16px; line-height: 1;
    padding: 2px 4px; border-radius: 4px;
    transition: color 0.1s;
  }
  .ct-toast-close:hover { color: #475569; }
  @keyframes toastIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
  /* Skeleton styles now live in admin.css, shared across every admin page */
</style>
</head>
<body>

@include('admin._sidebar')

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Expert Management</div>
    <div class="topbar-actions">
      @include('admin.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="content">
      <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center;">
        <div class="sk" style="height:38px;width:260px;border-radius:8px;"></div>
        <div class="sk" style="height:38px;width:140px;border-radius:8px;"></div>
        <div class="sk" style="height:38px;width:140px;border-radius:8px;"></div>
        <div class="sk" style="height:38px;width:160px;border-radius:20px;"></div>
      </div>
      <div class="card" style="padding:0;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;">
          <thead><tr><th style="padding:12px 20px;"><div class="sk" style="height:14px;width:40px;"></div></th><th style="padding:12px"><div class="sk" style="height:14px;width:50px;"></div></th><th style="padding:12px"><div class="sk" style="height:14px;width:60px;"></div></th><th style="padding:12px"><div class="sk" style="height:14px;width:50px;"></div></th><th style="padding:12px"><div class="sk" style="height:14px;width:70px;"></div></th><th style="padding:12px"><div class="sk" style="height:14px;width:40px;"></div></th><th style="padding:12px 20px;"><div class="sk" style="height:14px;width:50px;"></div></th></tr></thead>
          <tbody>
            @for($i = 0; $i < 4; $i++)
            <tr>
              <td style="padding:14px 20px;"><div style="display:flex;align-items:center;gap:10px;"><div class="sk" style="width:32px;height:32px;border-radius:8px;"></div><div><div class="sk" style="height:13px;width:110px;margin-bottom:5px;"></div><div class="sk" style="height:11px;width:80px;"></div></div></div></td>
              <td style="padding:14px 12px;"><div class="sk" style="height:20px;width:60px;border-radius:10px;"></div></td>
              <td style="padding:14px 12px;"><div class="sk" style="height:12px;width:100px;"></div></td>
              <td style="padding:14px 12px;"><div class="sk" style="height:12px;width:50px;"></div></td>
              <td style="padding:14px 12px;"><div style="display:flex;align-items:center;gap:8px;"><div class="sk" style="height:6px;width:60px;border-radius:3px;"></div><div class="sk" style="height:11px;width:40px;"></div></div></td>
              <td style="padding:14px 12px;"><div class="sk" style="height:20px;width:50px;border-radius:10px;"></div></td>
              <td style="padding:14px 20px;"><div class="sk" style="height:28px;width:120px;border-radius:6px;"></div></td>
            </tr>
            @endfor
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content">
    <div class="experts-toolbar">
      <div class="experts-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input class="input" id="experts-search-input" placeholder="Search experts by name or expertise..."/>
      </div>
      <div class="filter-row">
        <select class="input" id="experts-role-filter" style="width:160px">
          <option>All Roles</option>
          <option>Admin</option>
          <option>Adviser</option>
          <option>Consultant</option>
          <option>Grammarian</option>
        </select>
        <select class="input" style="width:160px" id="statusFilter">
          <option value="all">All Status</option>
          <option value="active">Active</option>
          <option value="full">Full</option>
          <option value="offline">Offline</option>
        </select>
      </div>
      <span class="experts-count">{{ $experts->count() }} registered experts</span>
    </div>

    <div class="card" style="padding:0;overflow:hidden">
      <table class="table">
        <thead>
          <tr>
            <th style="padding-left:20px">Expert</th>
            <th>Role(s)</th>
            <th>Expertise</th>
            <th>Teams</th>
            <th>Slots Left</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          @foreach($experts as $expert)
          <tr>
            <td style="padding-left:20px" data-label="Expert">
              <div style="display:flex;align-items:center;gap:10px">
                <div class="expert-av" style="background:linear-gradient(135deg,#c9960c,#8a6508);width:32px;height:32px;border-radius:8px;font-size:11px">
                  {{ $expert->avatarInitials() }}
                </div>
                <div>
                  <div class="td-main">{{ $expert->name }}</div>
                  <div style="font-size:11px;color:var(--text3)">{{ $expert->email }}</div>
                </div>
              </div>
            </td>
            <td data-label="Role">
              @if ($expert->is_admin)
                <div class="expert-role-stack">
                  <div class="expert-role-badges">
                    <span class="badge badge-red expert-role-badge">Admin/Subject Adviser</span>
                  </div>
                  <span class="expert-role-meta">System-wide access</span>
                </div>

              @else
                <div class="expert-role-stack">
                  <div class="expert-role-badges">
                  @forelse ($expert->roles as $role)
                    <span class="badge badge-gold expert-role-badge">{{ \Illuminate\Support\Str::of($role->role_name)->replace('_', ' ')->title() }}</span>
                  @empty
                    <span class="badge badge-gray expert-role-badge">No Role</span>
                  @endforelse
                  </div>
                  <span class="expert-role-meta">{{ $expert->roles->count() }} role{{ $expert->roles->count() === 1 ? '' : 's' }}</span>
                </div>

              @endif
            </td>

            <td style="font-size:12px" data-label="Expertise">
              {{ $expert->expertise->pluck('expertise_field')->join(', ') ?: 'None specified' }}
            </td>
            @php
              $maxTeams = $expert->max_teams ?? 4;
              $teamCount = $expert->team_assignments_count ?? 0;
              $slotsLeft = max(0, $maxTeams - $teamCount);
              $isFull = $teamCount >= $maxTeams;
              $isOnline = $expert->is_online;
              $computedStatus = $isFull ? 'full' : ($isOnline ? 'active' : 'offline');
            @endphp

            <td style="font-family:'Space Mono',monospace;font-size:12px" data-label="Teams">{{ $teamCount }} / {{ $maxTeams }}</td>
            <td data-label="Slots Left">
              <div style="display:flex;align-items:center;gap:8px">
                <div class="pbar" style="width:60px">
                  <div class="pbar-fill pf-green" style="width:{{ $maxTeams > 0 ? round(($teamCount / $maxTeams) * 100) : 0 }}%"></div>
                </div>
                <span style="font-size:11px;color:var(--text3)">{{ $slotsLeft }} left</span>
              </div>
            </td>
            <td data-label="Status">
              @if($computedStatus === 'full')
                <span class="badge badge-amber">Full</span>
              @elseif($computedStatus === 'offline')
                <span class="badge badge-gray">Offline</span>
              @else
                <span class="badge badge-green">Active</span>
              @endif
            </td>
            <td data-label="Action">
              <div class="expert-actions">
                @if ($expert->is_admin)
                  <span class="badge badge-blue expert-action-note">Current Admin</span>
                @else
                  <button type="button" class="btn-remove" data-id="{{ $expert->expert_id }}" data-name="{{ $expert->name }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M7 6l1 14h8l1-14"/><path d="M10 10v6"/><path d="M14 10v6"/></svg>
                    <span>Remove</span>
                  </button>
                @endif
              </div>
            </td>
          </tr>
          @endforeach

<script>
  // Client-side filtering: search, role, and status.
  (function() {
    var searchInput = document.getElementById('experts-search-input');
    var roleFilter = document.getElementById('experts-role-filter');
    var statusFilter = document.getElementById('statusFilter');
    var rows = document.querySelectorAll('table.table tbody tr');

    function filterRows() {
      var q = searchInput ? searchInput.value.toLowerCase().trim() : '';
      var role = roleFilter ? roleFilter.value : 'All Roles';
      var status = statusFilter ? statusFilter.value : 'all';

      rows.forEach(function(row) {
        var name = (row.querySelector('.td-main')?.textContent || '').toLowerCase();
        var email = (row.querySelector('td:first-child div[style*="font-size:11px"]')?.textContent || '').toLowerCase();
        var expertise = (row.querySelector('td[data-label="Expertise"]')?.textContent || '').toLowerCase();
        var roleTd = row.querySelector('td[data-label="Role"]');
        var isAdmin = roleTd && roleTd.querySelector('.badge-red');
        var roleBadges = roleTd ? roleTd.querySelectorAll('.badge-gold') : [];
        var roleText = '';
        roleBadges.forEach(function(b) { roleText += b.textContent.trim().toLowerCase() + ' '; });

        var badge = row.querySelector('td:nth-child(6) span, td[data-label="Status"] span');
        var statusText = badge ? badge.textContent.trim().toLowerCase() : '';

        var matchSearch = !q || name.includes(q) || email.includes(q) || expertise.includes(q);
        var matchRole = role === 'All Roles' || (role === 'Admin' && isAdmin) || (role !== 'Admin' && !isAdmin && roleText.includes(role.toLowerCase()));
        var matchStatus = true;
        if (status === 'active') matchStatus = statusText === 'active';
        else if (status === 'full') matchStatus = statusText === 'full';
        else if (status === 'offline') matchStatus = statusText === 'offline';

        row.style.display = (matchSearch && matchRole && matchStatus) ? '' : 'none';
      });
    }

    if (searchInput) searchInput.addEventListener('input', filterRows);
    if (roleFilter) roleFilter.addEventListener('change', filterRows);
    if (statusFilter) statusFilter.addEventListener('change', filterRows);
  })();
</script>
        </tbody>
      </table>
    </div>
  </div>
  </div><!-- /real-content -->
</div>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

{{-- Toast container --}}
<div id="ct-toast-container"></div>

{{-- ══════════════════════════════════════════
     REMOVE EXPERT — Confirmation Modal
══════════════════════════════════════════ --}}
<div id="removeModal" class="ct-modal-overlay" style="display:none;">
  <div class="ct-modal-card">
    <div class="ct-modal-icon danger">
      <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
        <polyline points="3 6 5 6 21 6"/>
        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
        <line x1="10" y1="11" x2="10" y2="17"/>
        <line x1="14" y1="11" x2="14" y2="17"/>
      </svg>
    </div>
    <h3 class="ct-modal-title">Remove Expert</h3>
    <p class="ct-modal-desc">
      You are about to permanently remove <strong id="removeExpertName" style="color:#0f172a;"></strong> from the system.<br>
      This action <span style="color:#ef4444;font-weight:600;">cannot be undone</span>.
    </p>
    <div style="margin-bottom:16px;text-align:left;">
      <label class="ct-modal-label">
        Reason for removal <span style="color:#ef4444;font-weight:400;">(sent to affected teams)</span>
      </label>
      <textarea id="removeReasonInput" class="ct-modal-input" rows="3" placeholder="Why is this expert being removed? This message will be sent to all affected team members..." style="resize:vertical;min-height:60px;font-family:inherit;"></textarea>
    </div>
    <div style="margin-bottom:20px;text-align:left;">
      <label class="ct-modal-label">
        Type <code style="background:#fee2e2;color:#dc2626;padding:2px 6px;border-radius:4px;font-family:monospace;">remove</code> to confirm
      </label>
      <input type="text" id="removeConfirmInput" class="ct-modal-input" placeholder="type remove" autocomplete="off">
    </div>
    <div class="ct-modal-actions">
      <button type="button" id="removeCancelBtn" class="ct-btn ct-btn-secondary">Cancel</button>
      <button type="button" id="removeConfirmBtn" class="ct-btn ct-btn-danger" disabled>Delete</button>
    </div>
  </div>
</div>

<script>
// ── TOAST NOTIFICATION ───────────────────────────────────────
function showToast(message, type = 'info', title = null) {
  const icons = {
    success: `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>`,
    error:   `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`,
    info:    `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
  };
  const defaultTitles = { success: 'Success', error: 'Error', info: 'Info' };

  const toast = document.createElement('div');
  toast.className = 'ct-toast';
  toast.innerHTML = `
    <div class="ct-toast-icon ${type}">${icons[type] || icons.info}</div>
    <div class="ct-toast-body">
      <p class="ct-toast-title">${title || defaultTitles[type]}</p>
      <p class="ct-toast-msg">${message}</p>
    </div>
    <button class="ct-toast-close" onclick="this.closest('.ct-toast').remove()">&times;</button>
  `;

  document.getElementById('ct-toast-container').appendChild(toast);

  // Auto-dismiss after 4s
  setTimeout(() => {
    toast.classList.add('hiding');
    setTimeout(() => toast.remove(), 200);
  }, 4000);
}

// ── REMOVE EXPERT ─────────────────────────────────────────────
const removeModal = document.getElementById('removeModal');
const removeInput = document.getElementById('removeConfirmInput');
const removeReason = document.getElementById('removeReasonInput');
const removeBtn   = document.getElementById('removeConfirmBtn');

document.querySelectorAll('.btn-remove').forEach(btn => {
  btn.addEventListener('click', function () {
    removeModal.dataset.expertId = this.dataset.id;
    document.getElementById('removeExpertName').textContent = this.dataset.name;
    removeInput.value = '';
    removeReason.value = '';
    removeBtn.disabled = true;
    removeBtn.textContent = 'Delete';
    removeModal.style.display = 'flex';
    setTimeout(() => removeReason.focus(), 60);
  });
});

removeInput.addEventListener('input', function () {
  removeBtn.disabled = this.value.trim().toLowerCase() !== 'remove';
});

document.getElementById('removeCancelBtn').addEventListener('click', () => {
  removeModal.style.display = 'none';
});

removeBtn.addEventListener('click', function () {
  const expertId = removeModal.dataset.expertId;
  if (removeInput.value.trim().toLowerCase() !== 'remove') { return; }

  this.disabled = true;
  this.textContent = 'Deleting…';

  fetch(`{{ route('admin.experts.destroy', ['expert' => 'EXPERT_ID']) }}`.replace('EXPERT_ID', expertId), {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Accept': 'application/json',
    },
    body: JSON.stringify({
      _method: 'DELETE',
      reason: removeReason.value.trim(),
    }),
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showToast(data.message, 'success');
        setTimeout(() => window.location.reload(), 1500);
      } else {
        showToast(data.message || 'Failed to delete expert.', 'error');
        this.disabled = false;
        this.textContent = 'Delete';
      }
    })
    .catch(() => {
      showToast('Network error during deletion.', 'error');
      this.disabled = false;
      this.textContent = 'Delete';
    })
    .finally(() => { removeModal.style.display = 'none'; });
});

// ── SKELETON SWAP ──────────────────────────────────────────────
(function() {
  var sk = document.getElementById('skeleton-content');
  var rc = document.getElementById('real-content');
  if (sk && rc) {
    setTimeout(function () {
      sk.style.display = 'none';
      rc.style.display = 'flex';
    }, 400);
  }
})();

/* ── Mobile sidebar toggle ── */
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() {
  if (!sidebarEl) return;
  sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar();
}
function openSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.add('mobile-open');
  sidebarOverlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.remove('mobile-open');
  sidebarOverlay.classList.remove('open');
  document.body.style.overflow = '';
}
</script>

</body>
</html>
