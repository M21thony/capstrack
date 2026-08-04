<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Account Approval - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<style>
@keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
.sk-overlay { background: var(--bg); min-height: calc(100vh - 60px); padding: 20px; }
.sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }
</style>
</head>
<body>

@include('admin._sidebar')
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Expert Account Approval</div>
    <div class="topbar-actions">
      @include('admin.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="content">
      <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
        <div class="sk" style="height:38px;width:220px;border-radius:8px;"></div>
        <div class="sk" style="height:38px;width:140px;border-radius:8px;"></div>
        <div class="sk" style="height:38px;width:140px;border-radius:8px;"></div>
        <div class="sk" style="height:38px;width:160px;border-radius:20px;"></div>
      </div>
      <div style="display:flex;flex-direction:column;gap:14px;">
        <div class="sk" style="height:140px;border-radius:14px;"></div>
        <div class="sk" style="height:140px;border-radius:14px;"></div>
        <div class="sk" style="height:140px;border-radius:14px;"></div>
      </div>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content">
    <!-- Toolbar -->
    <div class="approval-toolbar">
      <div class="approval-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input class="input" id="search-input" placeholder="Search pending accounts..." style="max-width:260px" oninput="filterCards()"/>
      </div>
      <div class="filter-row">
        <select class="input" id="role-filter" style="width:155px" onchange="filterCards()">
          <option value="">All Roles</option>
          <option value="Adviser">Adviser</option>
          <option value="Consultant">Consultant</option>
          <option value="Grammarian">Grammarian</option>
          <option value="CaPSA/TSA">CaPSA/TSA</option>
        </select>
        <select class="input" id="status-filter" style="width:155px" onchange="filterCards()">
          <option value="pending">Pending Only</option>
          <option value="all">All (incl. reviewed)</option>
        </select>
      </div>
      <span class="pending-count" id="pending-count">{{ $pendingCount }} pending accounts</span>
    </div>

    <!-- Card list -->
    <div class="gap-12" id="card-list">
      @forelse ($instructors as $instructor)
      @php
        $specList = is_array($instructor->specialization) ? $instructor->specialization : (array) $instructor->specialization;
        $expertiseList = is_array($instructor->expertise) ? $instructor->expertise : [];
        $primaryRole = !empty($specList) ? ucfirst($specList[0]) : 'N/A';
      @endphp
      <div class="expert-card" data-id="{{ $instructor->expert_id }}" data-role="{{ $primaryRole }}" data-status="{{ $instructor->status }}" data-name="{{ $instructor->first_name }} {{ $instructor->last_name }}" data-dept="{{ $instructor->department }}">
        <div style="display:flex;align-items:flex-start;gap:16px">
          <div class="expert-av" style="background:linear-gradient(135deg,#1d4ed8,#7c3aed);width:52px;height:52px;border-radius:14px;font-size:15px">
            {{ strtoupper(substr($instructor->first_name ?? 'I', 0, 1) . substr($instructor->last_name ?? '', 0, 1)) }}
          </div>
      <div style="flex:1">
            <div class="expert-meta">

              <div class="expert-name">{{ $instructor->first_name }} {{ $instructor->last_name }}@if($instructor->credentials), {{ $instructor->credentials }}@endif</div>
              @if($instructor->status === 'approved')
                <span class="badge badge-green" id="status-badge-{{ $instructor->expert_id }}">Approved</span>
              @elseif($instructor->status === 'rejected')
                <span class="badge badge-red" id="status-badge-{{ $instructor->expert_id }}">Rejected @if(isset($instructor->reject_attempts) && $instructor->reject_attempts > 0)({{ $instructor->reject_attempts }}/3)@endif</span>
              @else
                <span class="badge badge-amber" id="status-badge-{{ $instructor->expert_id }}">Pending</span>
              @endif
              <span class="expert-reg">{{ $instructor->created_at ? \Carbon\Carbon::parse($instructor->created_at)->format('M d, Y') : 'N/A' }}</span>
            </div>
            <div class="expert-email" style="margin-bottom:10px">{{ $instructor->email }}</div>
            <div class="tags-wrap">
              @foreach($expertiseList as $tag)
                @if(is_string($tag) && trim($tag) !== '')
                  <span class="atag atag-web">{{ $tag }}</span>
                @elseif(is_object($tag) && isset($tag->expertise_field))
                  <span class="atag atag-web">{{ $tag->expertise_field }}</span>
                @endif
              @endforeach
            </div>
            <div class="info-block">
              <span><strong>Role</strong><em>{{ implode(', ', array_map('ucfirst', $specList)) ?: 'N/A' }}</em></span>
              <span><strong>Max Teams</strong><em>{{ $instructor->max_teams }}</em></span>
              <span><strong>Department</strong><em>{{ $instructor->department }}</em></span>
              @if($instructor->status === 'rejected' && isset($instructor->reject_attempts) && $instructor->reject_attempts > 0)
              <span><strong>Attempts</strong><em>{{ $instructor->reject_attempts }}/3</em></span>
              @endif
            </div>
            <div class="card-actions" id="actions-{{ $instructor->expert_id }}">
@if($instructor->status === 'pending')

                <button onclick="openReview({{ $instructor->expert_id }})" style="background:#28a745;color:#fff;padding:6px 12px;border:none;border-radius:10px;cursor:pointer;font-size:12px;font-weight:600;" title="Approve">
                  Approve
                </button>
                <button onclick="openRejectConfirmModal({{ $instructor->expert_id }})" style="background:#dc3545;color:#fff;padding:6px 12px;border:none;border-radius:10px;cursor:pointer;font-size:12px;font-weight:600;" title="Reject">
                  Reject
                </button>
              @elseif($instructor->status === 'approved')

                <span class="action-done approved"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Account Approved</span>
                <span style="font-size:11px;color:var(--text3);margin-left:6px">Expert can now log in</span>
              @else
                <span class="action-done rejected"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Account Rejected</span>
                <span style="font-size:11px;color:var(--text3);margin-left:6px">No access granted</span>
              @endif
              <button class="btn btn-review" onclick="openReview({{ $instructor->expert_id }})" style="font-size:12px;padding:7px 18px;margin-left:auto">
                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Review Details
              </button>
            </div>
          </div>
        </div>
      </div>
      @empty
      @endforelse
    </div><!-- /card-list -->

    <!-- Empty state (hidden by default) -->
    <div class="empty-state" id="empty-state" style="display:none">
      <div class="empty-icon">
        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      </div>
      <div class="empty-title">All accounts reviewed!</div>
      <div class="empty-sub">No more pending expert accounts to action.</div>
    </div>
  </div><!-- /content -->
  </div><!-- /real-content -->
</div><!-- /main -->

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<!-- ═══ REVIEW DETAILS MODAL ═══ -->
<div class="review-overlay" id="review-overlay" onclick="closeReviewOnBg(event)">
  <div class="review-modal" id="review-modal">
    <div class="modal-header">
      <div class="modal-header-av" id="rm-av" style="background:linear-gradient(135deg,#1d4ed8,#7c3aed)">JD</div>
      <div class="modal-header-info">
        <div class="modal-header-name" id="rm-name">Dr. Jose Dela Cruz</div>
        <div class="modal-header-email" id="rm-email">jose.delacruz@cspc.edu.ph</div>
      </div>
      <button class="modal-close" onclick="closeReview()">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <!-- Personal Details -->
      <div class="modal-section">
        <div class="modal-section-label">
          <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Personal Information
        </div>
        <div class="modal-detail-grid">
          <div class="modal-detail-item">
            <div class="modal-detail-key">Full Name</div>
            <div class="modal-detail-val" id="rm-fullname">Dr. Jose Dela Cruz</div>
          </div>
          <div class="modal-detail-item">
            <div class="modal-detail-key">Date Registered</div>
            <div class="modal-detail-val date" id="rm-date">April 3, 2026</div>
          </div>
    <div class="modal-detail-item full">
            <div class="modal-detail-key">CSPC Email Address</div>
            <div class="modal-detail-val email" id="rm-email2">jose.delacruz@cspc.edu.ph</div>
          </div>
        </div>
      </div>

      <!-- Role & Assignment -->
      <div class="modal-section">
        <div class="modal-section-label">
          <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Role &amp; Assignment
        </div>
        <div class="modal-detail-grid">
          <div class="modal-detail-item">
            <div class="modal-detail-key">Expert Role</div>
            <div class="modal-detail-val" id="rm-role">Adviser &amp; Consultant</div>
          </div>
          <div class="modal-detail-item">
            <div class="modal-detail-key">Maximum Teams</div>
            <div class="modal-detail-val" id="rm-maxteams">3</div>
          </div>
          <div class="modal-detail-item full">
            <div class="modal-detail-key">Department</div>
            <div class="modal-detail-val" id="rm-dept">Computer Science</div>
          </div>
        </div>
      </div>

      <!-- Skills / Expertise Tags -->
      <div class="modal-section">
        <div class="modal-section-label">
          <svg viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
          Areas of Expertise
        </div>
        <div class="modal-tags-area" id="rm-tags"></div>
      </div>

      <!-- Status indicator if already reviewed -->
      <div id="rm-reviewed-note" style="display:none;padding:12px 14px;border-radius:10px;font-size:13px;font-weight:500;margin-bottom:4px"></div>
    </div>
    <div class="modal-footer">
      <span class="modal-footer-note" id="rm-footer-note">Review the expert's details carefully before approving or rejecting their account.</span>

      <div id="rm-actions-wrap" style="display:flex;gap:10px;align-items:center;justify-content:flex-end;">
        <button class="btn btn-danger" id="rm-reject-btn" onclick="openRejectConfirmModal(activeReviewId)" style="font-size:12px;padding:7px 18px">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          Reject
        </button>

<button class="btn btn-success" id="rm-approve-btn" type="button" onclick="openConfirmApproveFromReview()" style="font-size:12px;padding:7px 18px">

          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          Approve Account
        </button>

        <button class="btn btn-success" id="rm-confirm-approve-btn" onclick="confirmApproveFromReview()" style="display:none;font-size:12px;padding:7px 18px">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          Confirm Approve
        </button>
      </div>
    </div>
  </div>
</div>



<!-- ═══ REJECT CONFIRM MODAL ═══ -->
<div class="confirm-overlay" id="reject-confirm-overlay" onclick="closeRejectConfirmOnBg(event)">
  <div class="confirm-box" role="dialog" aria-modal="true" aria-labelledby="reject-confirm-title">
    <div class="confirm-icon reject" style="margin:0 auto 14px">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </div>
    <div class="confirm-title" id="reject-confirm-title">Reject Account</div>
    <div class="confirm-sub">Provide a reason before rejecting.</div>

    <div class="reject-reason-wrap">
      <label for="reject-reason-modal">Reason <span class="required-star">*</span></label>
      <textarea id="reject-reason-modal" placeholder="Type a short reason..." rows="4" maxlength="500"></textarea>
      <div id="reject-reason-error" style="display:none;margin-top:8px;color:var(--red);font-size:12px;font-weight:600">Reason is required.</div>
    </div>

    <div class="confirm-actions" style="margin-top:18px">
      <button class="btn" onclick="closeRejectConfirm()" style="font-size:12px;padding:7px 14px;background:transparent;border:1.5px solid rgba(255,255,255,.25);color:inherit">Cancel</button>
      <button class="btn btn-danger" onclick="confirmRejectFromModal()" style="font-size:12px;padding:7px 14px;background:#dc3545;border:none;color:#fff">
        Reject
      </button>
    </div>
  </div>
</div>



<!-- ═══ TOAST CONTAINER ═══ -->
<div class="toast-wrap" id="toast-wrap"></div>

<script>
// ── DATA ──────────────────────────────────────────────────────────────────
const experts = {
@foreach($instructors as $instructor)
@php
  $specList = is_array($instructor->specialization) ? $instructor->specialization : (array) $instructor->specialization;
  $expertiseList = is_array($instructor->expertise) ? $instructor->expertise : [];
  $primaryRole = !empty($specList) ? ucfirst($specList[0]) : 'N/A';
@endphp
  {{ $instructor->expert_id }}: {
    initials: '{{ strtoupper(substr($instructor->first_name ?? "I", 0, 1) . substr($instructor->last_name ?? "", 0, 1)) }}',
    grad: 'linear-gradient(135deg,#1d4ed8,#7c3aed)',
    name: '{{ $instructor->first_name }} {{ $instructor->last_name }}',
    email: '{{ $instructor->email }}',
    date: '{{ $instructor->created_at ? \Carbon\Carbon::parse($instructor->created_at)->format("F d, Y") : "N/A" }}',
    role: '{{ $primaryRole }}',
    maxTeams: {{ $instructor->max_teams ?? 0 }},
    dept: '{{ $instructor->department }}',
    tags: [
      @foreach($expertiseList as $tag)
        {label: '{{ is_string($tag) ? $tag : ($tag->expertise_field ?? $tag) }}', cls: 'atag-web'},
      @endforeach
    ],
    status: '{{ $instructor->status }}',
    rejectAttempts: {{ $instructor->reject_attempts ?? 0 }}
  },
  @endforeach
};

let activeReviewId = null;


// ── FILTER ────────────────────────────────────────────────────────────────
function filterCards(){
  const q = document.getElementById('search-input').value.toLowerCase();
  const role = document.getElementById('role-filter').value;
  const statusFilter = document.getElementById('status-filter').value;
  const cards = document.querySelectorAll('.expert-card');
  let visible = 0;

  cards.forEach(card => {
    const id = +card.dataset.id;
    const ex = experts[id];
    const matchQ = !q || ex.name.toLowerCase().includes(q) || ex.email.toLowerCase().includes(q) || ex.dept.toLowerCase().includes(q);
    const matchRole = !role || ex.role.includes(role);
    const matchStatus = statusFilter === 'all' || ex.status === 'pending';
    const show = matchQ && matchRole && matchStatus;
    card.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  // empty state
  document.getElementById('empty-state').style.display = visible === 0 ? 'block' : 'none';
  updatePendingCount();
}

function updatePendingCount(){
  const count = Object.values(experts).filter(e => e.status==='pending').length;
  document.getElementById('pending-count').textContent = count + ' pending account' + (count!==1?'s':'');
  const badge = document.getElementById('sidebar-badge');
  if(count > 0){ badge.textContent = count; badge.style.display=''; }
  else badge.style.display='none';
}

// ── REVIEW MODAL ──────────────────────────────────────────────────────────
function openReview(id){

  const ex = experts[id];
  activeReviewId = id;

  document.getElementById('rm-av').textContent = ex.initials;
  document.getElementById('rm-av').style.background = ex.grad;
  document.getElementById('rm-name').textContent = ex.name;
  document.getElementById('rm-email').textContent = ex.email;
  document.getElementById('rm-fullname').textContent = ex.name;
  document.getElementById('rm-date').textContent = ex.date;
  document.getElementById('rm-email2').textContent = ex.email;
  document.getElementById('rm-role').textContent = ex.role;
  document.getElementById('rm-maxteams').textContent = ex.maxTeams + ' team' + (ex.maxTeams!==1?'s':'');
  document.getElementById('rm-dept').textContent = ex.dept;

  const tagsEl = document.getElementById('rm-tags');
  tagsEl.innerHTML = ex.tags.map(t=>`<span class="atag ${t.cls}">${t.label}</span>`).join('');

  const reviewedNote = document.getElementById('rm-reviewed-note');
  const approveBtn = document.getElementById('rm-approve-btn');
  const rejectBtn = document.getElementById('rm-reject-btn');
  const footerNote = document.getElementById('rm-footer-note');

  if(ex.status === 'approved'){
    reviewedNote.style.display='block';
    reviewedNote.style.cssText='display:block;padding:12px 14px;border-radius:10px;font-size:13px;font-weight:500;margin-bottom:4px;background:var(--green-bg);color:var(--green);border:1px solid rgba(22,163,74,.2)';
    reviewedNote.innerHTML='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:6px"><polyline points="20 6 9 17 4 12"/></svg>This account has already been approved.';
    approveBtn.style.display='none'; rejectBtn.style.display='none';
    footerNote.textContent='This account was approved and the expert can now log in.';
  } else if(ex.status === 'rejected'){
    reviewedNote.style.display='block';
    reviewedNote.style.cssText='display:block;padding:12px 14px;border-radius:10px;font-size:13px;font-weight:500;margin-bottom:4px;background:var(--red-bg);color:var(--red);border:1px solid rgba(220,38,38,.2)';
    reviewedNote.innerHTML='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:6px"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>This account has been rejected.';
    approveBtn.style.display='none'; rejectBtn.style.display='none';
    footerNote.textContent='This account was rejected. No login access granted.';
  } else {
    reviewedNote.style.display='none';
    approveBtn.style.display=''; rejectBtn.style.display='';
    footerNote.textContent='Review the expert\'s details carefully before approving or rejecting.';
  }

  document.getElementById('review-overlay').classList.add('show');
  document.body.style.overflow='hidden';
}

function closeReview(){
  document.getElementById('review-overlay').classList.remove('show');
  // keep body scroll for reject modal if it's open
  if(!document.getElementById('reject-confirm-overlay').classList.contains('show')){
    document.body.style.overflow='';
  }
  activeReviewId = null;
}


function closeReviewOnBg(e){
  if(e.target === document.getElementById('review-overlay')) closeReview();
}

function openConfirmApproveFromReview(){
  const id = activeReviewId;
  if(!id) return;

  // toggle buttons
  document.getElementById('rm-approve-btn').style.display = 'none';
  document.getElementById('rm-confirm-approve-btn').style.display = '';
}

function confirmApproveFromReview(){
  const id = activeReviewId;
  if(!id) return;

  closeReview();
  setTimeout(()=>executeAction(id, 'approve'), 120);
}

function openRejectConfirmModal(id){
  if(!id) return;

  activeReviewId = id;

  // reset
  const txt = document.getElementById('reject-reason-modal');
  const err = document.getElementById('reject-reason-error');
  if(txt) txt.value = '';
  if(err) err.style.display = 'none';

  document.getElementById('reject-confirm-overlay').classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closeRejectConfirm(){
  document.getElementById('reject-confirm-overlay').classList.remove('show');
  document.body.style.overflow = '';
}

function closeRejectConfirmOnBg(e){
  if(e.target === document.getElementById('reject-confirm-overlay')) closeRejectConfirm();
}

function confirmRejectFromModal(){
  const id = activeReviewId;
  if(!id) return;

  const reasonEl = document.getElementById('reject-reason-modal');
  const errEl = document.getElementById('reject-reason-error');
  const reason = (reasonEl?.value ?? '').trim();

  if(!reason){
    if(errEl) errEl.style.display = 'block';
    reasonEl?.focus?.();
    return;
  }

  closeRejectConfirm();
  closeReview();

  setTimeout(()=>executeAction(id, 'reject', reason), 140);
}

// ── SIDE NOTIFICATION ACTION (no confirmation modal) ────────────────
function setActionBusy(id, type, busy){
  const actions = document.getElementById(`actions-${id}`);
  if(!actions) return;

  // Disable any approve/reject buttons for this card while request is running
  const buttons = actions.querySelectorAll('button');
  const needle = type === 'approve' ? 'approve' : 'reject';
  buttons.forEach(b => {
    const txt = (b.textContent || '').toLowerCase();
    if(txt.includes(needle)){
      b.disabled = busy;
      b.style.opacity = busy ? '.7' : '1';
      b.style.cursor = busy ? 'not-allowed' : 'pointer';
    }
  });
}

// (Deprecated) showActionToast + inline reject UI removed from usage.
// Kept only if other templates still call it.
function showActionToast(id, type){
  // Redirect to new modal flows for backward compatibility.
  if(type === 'approve'){
    openReview(id);
    openConfirmApproveFromReview();
    return;
  }
  if(type === 'reject'){
    openRejectConfirmModal(id);
    return;
  }
}

function closeInlineReject(id){
  const toast = window._inlineRejectToast;
  if(toast){
    clearTimeout(toast._timer);
    toast.style.animation = 'toastOut .3s ease forwards';
    setTimeout(()=>toast.remove(), 300);
  }
}

function confirmInlineReject(id){
  // Deprecated: inline reject confirmation removed.
  // Fallback to modal flow.
  openRejectConfirmModal(id);
}

function executeAction(id, type, reasonOverride){
  if(!id || !type) return;

  let body = null;
  if(type === 'reject'){
    const reason = typeof reasonOverride === 'string' ? reasonOverride : '';
    body = JSON.stringify({ reason });
  }

  setActionBusy(id, type, true);

  // basic front-end lock so user can't double-submit rapidly
  const cardEl = document.querySelector(`.expert-card[data-id="${id}"]`);
  if(cardEl) cardEl.style.pointerEvents = 'none';

  fetch(`/admin/approval/${id}/${type}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Accept': 'application/json'
    },
    body: body
  })
  .then(response => {
    if (!response.ok) throw new Error('Action failed.');
    return response.json().catch(() => ({}));
  })
  .then(()=>{
    applyDecision(id, type);
  })
  .catch(error => {
    console.error('Error:', error);
    showToast(type, 'Failed to process request. Please try again.');
    setActionBusy(id, type, false);
  });
}


// ── APPLY DECISION ────────────────────────────────────────────────────────

function applyDecision(id, type){
  const ex = experts[id];
  ex.status = type === 'approve' ? 'approved' : 'rejected';

  const card = document.querySelector(`.expert-card[data-id="${id}"]`);
  const badge = document.getElementById(`status-badge-${id}`);
  const actions = document.getElementById(`actions-${id}`);
  const av = card.querySelector('.expert-av');

  // Update badge
  badge.className = type==='approve' ? 'badge badge-green' : 'badge badge-red';
  badge.textContent = type==='approve' ? 'Approved' : 'Rejected';

  // Update card style
  card.classList.add(type==='approve' ? 'approved-card' : 'rejected-card');
  if(type==='approved') av.classList.add('approved-av');
  else av.classList.add('rejected-av');

  // Replace action buttons with status chip
  const chip = document.createElement('div');
  chip.className='card-actions';
  chip.innerHTML = type==='approve'
    ? `<span class="action-done approved"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Account Approved</span>
       <span style="font-size:11px;color:var(--text3);margin-left:6px">Expert can now log in</span>`
    : `<span class="action-done rejected"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Account Rejected</span>
       <span style="font-size:11px;color:var(--text3);margin-left:6px">No access granted</span>`;
  actions.replaceWith(chip);

  // Show toast
  showToast(
    type,
    type==='approve'
      ? `${ex.name} has been approved and can now log in.`
      : `${ex.name}'s account was rejected.`
  );

  updatePendingCount();

  // Check if should hide under current filter
  // If current view is Pending Only, remove immediately after decision
  const statusFilter = document.getElementById('status-filter').value;
  if(statusFilter === 'pending'){
    card.classList.add('removing');
    setTimeout(()=>{ card.style.display='none'; filterCards(); }, 250);
  }
}

// ── TOAST ─────────────────────────────────────────────────────────────────
function showToast(type, msg){
  const wrap = document.getElementById('toast-wrap');
  const t = document.createElement('div');
  t.className = `toast ${type==='approve'?'success':'danger'}`;
  t.style.pointerEvents = 'auto';

  const checkSvg = type==='approve'
    ? '<polyline points="20 6 9 17 4 12"/>'
    : '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';

  t.innerHTML=`
    <div class="toast-icon">
      <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5">${checkSvg}</svg>
    </div>
    <span>${msg}</span>
    <button type="button" aria-label="Dismiss" onclick="this.parentElement && this.parentElement.remove()" style="margin-left:10px;background:transparent;border:none;color:inherit;cursor:pointer;opacity:.7">✕</button>`;

  wrap.appendChild(t);

  const DURATION_MS = 5200;
  setTimeout(()=>{
    t.style.animation='toastOut .35s ease forwards';
    setTimeout(()=>t.remove(), 360);
  }, DURATION_MS);

}

// ── ESC KEY ───────────────────────────────────────────────────────────────
document.addEventListener('keydown', e=>{
  if(e.key==='Escape'){
    if(document.getElementById('review-overlay').classList.contains('show')) closeReview();
  }
});


// Apply default filter on page load (Pending Only)
filterCards();

// Clean up inline reject toast handle on page unload/navigation
window.addEventListener('beforeunload', () => {
  try {
    if (window._inlineRejectToast) window._inlineRejectToast.remove();
  } catch (e) {}
});

</script>

<script>
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

