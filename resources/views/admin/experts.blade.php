<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Expert Management - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<style>
  /* Action buttons in table */
  .btn-transfer,
  .btn-remove {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 600;
    padding: 5px 11px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    cursor: pointer;
    transition: background 0.12s, color 0.12s, border-color 0.12s;
  }
  .btn-transfer:hover { background: #fef9c3; color: #b45309; border-color: #fde68a; }
  .btn-remove  { margin-left: 4px; }
  .btn-remove:hover  { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }

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
</style>
</head>
<body>

@include('admin._sidebar')

<div class="main">
  <div class="topbar">
    <div class="page-title">Expert Management</div>
    <div class="topbar-actions">
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="row" style="margin-bottom:16px;gap:8px">
      <input class="input" placeholder="Search experts by name or expertise..." style="max-width:300px"/>
      <select class="input" style="width:160px">
        <option>All Roles</option>
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
      <span style="flex:1"></span>
      <span style="font-size:12px;color:var(--text3)">{{ $experts->count() }} registered experts</span>
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
            <td style="padding-left:20px">
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
            <td>
              @if ($expert->is_admin)
                <span class="badge badge-red" style="font-size:10px;background:#dc2626;color:#fff;">Admin / Subject Adviser</span>
              @else
                @forelse ($expert->roles as $role)
                  <span class="badge badge-gold" style="font-size:10px;margin-right:4px;">{{ $role->role_name }}</span>
                @empty
                  <span class="badge badge-gray" style="font-size:10px">No Role</span>
                @endforelse
              @endif
            </td>
            <td style="font-size:12px">
              {{ $expert->expertise->pluck('expertise_field')->join(', ') ?: 'None specified' }}
            </td>
            @php
              $maxTeams = $expert->max_teams ?? 4;
              // NOTE: current project doesn't expose expert-team assignment directly here.
              // Default team count to 0 unless relationships are added.
              $teamCount = 0;
              $slotsLeft = max(0, $maxTeams - $teamCount);
              $isFull = $teamCount >= $maxTeams;

              // Offline/Active: no last_seen/online fields in capstone_experts schema.
              // Default to Active (online) for now.
              $isOffline = false;

              // Status order preference: Full > Offline > Active
              $computedStatus = $isFull ? 'full' : ($isOffline ? 'offline' : 'active');
            @endphp

            <td style="font-family:'Space Mono',monospace;font-size:12px">{{ $teamCount }} / {{ $maxTeams }}</td>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div class="pbar" style="width:60px">
                  <div class="pbar-fill pf-green" style="width:{{ $maxTeams > 0 ? round(($teamCount / $maxTeams) * 100) : 0 }}%"></div>
                </div>
                <span style="font-size:11px;color:var(--text3)">{{ $slotsLeft }} left</span>
              </div>
            </td>
            <td>
              @if($computedStatus === 'full')
                <span class="badge badge-amber">Full</span>
              @elseif($computedStatus === 'offline')
                <span class="badge badge-gray">Offline</span>
              @else
                <span class="badge badge-green">Active</span>
              @endif
            </td>
            <td>
              @if ($expert->is_admin)
                <span style="font-size:11px;color:var(--text3);font-weight:600;">Current Admin</span>
              @else
                <button type="button" class="btn-transfer" data-id="{{ $expert->expert_id }}" data-name="{{ $expert->name }}">
                  Transfer Rights
                </button>
                <button type="button" class="btn-remove" data-id="{{ $expert->expert_id }}" data-name="{{ $expert->name }}">
                  Remove
                </button>
              @endif
            </td>
          </tr>
          @endforeach

<script>
  // Client-side filter by computed status badge.
  const statusFilter = document.getElementById('statusFilter');
  if(statusFilter){
    const rows = document.querySelectorAll('table.table tbody tr');

    statusFilter.addEventListener('change', () => {
      const val = statusFilter.value;

      rows.forEach(row => {
        const badge = row.querySelector('td:nth-child(6) span');
        const text = badge ? badge.textContent.trim().toLowerCase() : '';

        let match = true;
        if(val === 'active') match = text === 'active';
        if(val === 'full') match = text === 'full';
        if(val === 'offline') match = text === 'offline';

        row.style.display = match ? '' : 'none';
      });
    });
  }
</script>
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- Toast container --}}
<div id="ct-toast-container"></div>

{{-- ══════════════════════════════════════════
     TRANSFER RIGHTS — Confirmation Modal
══════════════════════════════════════════ --}}
<div id="transferModal" class="ct-modal-overlay" style="display:none;">
  <div class="ct-modal-card">
    <div class="ct-modal-icon warning">
      <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
      </svg>
    </div>
    <h3 class="ct-modal-title">Transfer Admin Rights</h3>
    <p class="ct-modal-desc">
      You are about to transfer admin rights to <strong id="transferExpertName" style="color:#0f172a;"></strong>.<br>
      <span style="color:#ef4444;font-size:12.5px;">Your account will be demoted and you will be logged out immediately.</span>
    </p>
    <div class="ct-modal-actions">
      <button type="button" id="transferCancelBtn" class="ct-btn ct-btn-secondary">Cancel</button>
      <button type="button" id="transferConfirmBtn" class="ct-btn ct-btn-warning">Yes, Transfer</button>
    </div>
  </div>
</div>

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

// ── TRANSFER RIGHTS ──────────────────────────────────────────
const transferModal = document.getElementById('transferModal');

document.querySelectorAll('.btn-transfer').forEach(btn => {
  btn.addEventListener('click', function () {
    transferModal.dataset.expertId = this.dataset.id;
    document.getElementById('transferExpertName').textContent = this.dataset.name;
    transferModal.style.display = 'flex';
  });
});

document.getElementById('transferCancelBtn').addEventListener('click', () => {
  transferModal.style.display = 'none';
});

document.getElementById('transferConfirmBtn').addEventListener('click', function () {
  const expertId = transferModal.dataset.expertId;
  this.disabled = true;
  this.textContent = 'Transferring…';

  fetch("{{ route('admin.turnover') }}", {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ expert_id: expertId }),
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showToast(data.message, 'success');
        setTimeout(() => window.location.href = data.redirect_url, 1500);
      } else {
        showToast(data.message || 'An error occurred during transfer.', 'error');
        this.disabled = false;
        this.textContent = 'Yes, Transfer';
      }
    })
    .catch(() => {
      showToast('A network error occurred.', 'error');
      this.disabled = false;
      this.textContent = 'Yes, Transfer';
    })
    .finally(() => { transferModal.style.display = 'none'; });
});

// ── REMOVE EXPERT ─────────────────────────────────────────────
const removeModal = document.getElementById('removeModal');
const removeInput = document.getElementById('removeConfirmInput');
const removeBtn   = document.getElementById('removeConfirmBtn');

document.querySelectorAll('.btn-remove').forEach(btn => {
  btn.addEventListener('click', function () {
    removeModal.dataset.expertId = this.dataset.id;
    document.getElementById('removeExpertName').textContent = this.dataset.name;
    removeInput.value = '';
    removeBtn.disabled = true;
    removeBtn.textContent = 'Delete';
    removeModal.style.display = 'flex';
    setTimeout(() => removeInput.focus(), 60);
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
    body: JSON.stringify({ _method: 'DELETE' }),
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
</script>

</body>
</html>
