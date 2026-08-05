<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Financial Records - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/styles.css')
<style>
/* ── DISABLED UPLOAD OVERLAY ── */
.upload-card--disabled {
  position: relative;
  opacity: 0.55;
  pointer-events: none;
  cursor: not-allowed;
  user-select: none;
}
.upload-card--disabled .upload-card__lock {
  display: flex;
}
.upload-card__lock {
  display: none;
  position: absolute;
  inset: 0;
  z-index: 10;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  background: rgba(255,255,255,0.65);
  backdrop-filter: blur(2px);
  border-radius: 14px;
  color: #64748b;
  font-size: 0.85rem;
  font-weight: 500;
  text-align: center;
  padding: 24px;
  pointer-events: none;
  cursor: not-allowed;
  animation: fadeIn 0.3s ease;
}
.upload-card__lock svg {
  opacity: 0.5;
  stroke: #94a3b8;
}

/* ── ALERT INFO ── */
.alert-info {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  background: linear-gradient(135deg, #eff6ff 0%, #faf7ed 100%);
  border: 1px solid #c7d2fe;
  border-radius: 10px;
  color: #4338ca;
  font-size: 13px;
  margin-bottom: 20px;
  line-height: 1.6;
  font-weight: 500;
  animation: fadeIn 0.3s ease;
}
.alert-info svg {
  flex-shrink: 0;
  stroke: #6366f1;
}
.alert-info strong {
  color: #1e1b4b;
}

/* ── INPUT ERROR ── */
.input-error {
  border-color: var(--red) !important;
  box-shadow: 0 0 0 3px rgba(220,38,38,0.1) !important;
}

/* ── CONFIRM RECEIPT BOX ── */
.confirm-receipt-box {
  background: var(--bg3);
  border: 2px solid rgba(201,168,44,0.3);
  border-radius: 12px;
  padding: 20px;
  text-align: center;
}
.confirm-receipt-box__label {
  font-size: 10px;
  font-weight: 700;
  color: var(--text3);
  text-transform: uppercase;
  letter-spacing: 0.8px;
  margin-bottom: 8px;
}
.confirm-receipt-box__value {
  font-family: 'Space Mono', monospace;
  font-size: 22px;
  font-weight: 700;
  color: var(--accent3);
  word-break: break-all;
}

/* ── MODAL FOOTER ── */
.modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid var(--border);
}

/* ── FILTER BAR ── */
.filter-bar {
  display: flex;
  flex-wrap: nowrap;
  gap: 8px;
  align-items: center;
}
.filter-search { flex: 1; min-width: 0; }

#pageSkeleton { position: absolute; inset: 0; z-index: 50; padding: 0; opacity: 1; transition: opacity 0.35s ease; pointer-events: auto; }
#pageSkeleton.loaded { opacity: 0; pointer-events: none; }

/* ── HAMBURGER MENU ── */
.hamburger-btn {
  display: none;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 9px;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(245,193,0,0.2);
  cursor: pointer;
  color: var(--navy-text, #fff);
  flex-shrink: 0;
}
.hamburger-btn svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; }

/* ── SIDEBAR OVERLAY ── */
.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(0,0,0,0.5);
}

/* ── RESPONSIVE ── */
@media (max-width: 768px) {
  .hamburger-btn { display: inline-flex; }

  .sidebar {
    position: fixed !important;
    z-index: 1000;
    transform: translateX(-100%);
    transition: transform 0.25s cubic-bezier(0.16,1,0.3,1);
  }

  .sidebar-open .sidebar { transform: translateX(0); }
  .sidebar-open .sidebar-overlay { display: block; }
  body.sidebar-open { overflow: hidden; }

  .topbar { padding: 0 14px; gap: 10px; }
  .content { padding: 14px; }
  .page-title { font-size: 15px; }

  .card { padding: 16px; }
  .card-header { flex-direction: column; align-items: flex-start; gap: 6px; }

  .fin-grid { grid-template-columns: 1fr !important; gap: 14px; }

  .filter-bar { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
  .filter-bar .fg-row1 { grid-row: 1; }
  .filter-bar .fg-row2 { grid-row: 2; }
  .filter-search { width: 100%; }
  .filter-bar select { width: 100% !important; max-width: none !important; }

  .receipt-card { flex-wrap: wrap; gap: 10px; padding: 12px; }
  .receipt-card__thumb { width: 48px; height: 48px; }
  .receipt-card__body { min-width: 0; flex: 1; }
  .receipt-card__aside { width: 100%; flex-direction: row; align-items: center; justify-content: space-between; }
  .receipt-card__receipt-no { font-size: 11px; }
  .receipt-card__category { font-size: 12px; }
  .receipt-card__meta { flex-direction: column; gap: 2px; }

  .modal-content { padding: 16px; max-width: 95vw; }
  .modal-overlay { padding: 10px; align-items: flex-start; padding-top: 30px; }
  .modal-header { flex-direction: column; align-items: flex-start; gap: 8px; }
  .modal-header__right { width: 100%; justify-content: space-between; }
  .info-grid { grid-template-columns: 1fr !important; }
  .modal-footer { padding: 14px 18px; flex-direction: column-reverse; }
  .modal-footer .btn { width: 100%; justify-content: center; }

  .upload-zone { padding: 20px 14px; }
  .upload-preview-img { max-height: 180px; }
  .upload-card__lock { font-size: 0.8rem; padding: 16px; }

  .confirm-receipt-box__value { font-size: 18px; }
  .guidelines-card { margin-top: 12px; }
  .guidelines-card__list { padding-left: 16px; }
  .btn-submit { width: 100%; justify-content: center; }
}
</style>
<x-dashboard.styles />
</head>
<body>
@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Financial Records</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'financial'])
    <div id="contentWrapper" style="position: relative;">

    {{-- Skeleton loader --}}
    <div id="pageSkeleton">
      <div class="fin-grid">
        <div class="card">
          <div class="skeleton-box" style="width:120px;height:12px;border-radius:4px;margin-bottom:16px"></div>
          @for ($i = 0; $i < 4; $i++)
          <div style="margin-bottom:14px">
            <div class="skeleton-box" style="width:100px;height:9px;border-radius:4px;margin-bottom:6px"></div>
            <div class="skeleton-box" style="width:100%;height:38px;border-radius:8px"></div>
          </div>
          @endfor
          <div class="skeleton-box" style="width:100%;height:140px;border-radius:10px;margin-bottom:14px"></div>
          <div class="skeleton-box" style="width:100%;height:38px;border-radius:8px"></div>
        </div>
        <div class="card">
          <div class="skeleton-box" style="width:140px;height:12px;border-radius:4px;margin-bottom:16px"></div>
          @for ($i = 0; $i < 5; $i++)
          <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border)">
            <div style="flex:1">
              <div class="skeleton-box" style="width:65%;height:11px;border-radius:4px;margin-bottom:6px"></div>
              <div class="skeleton-box" style="width:40%;height:9px;border-radius:4px"></div>
            </div>
            <div class="skeleton-box" style="width:70px;height:20px;border-radius:12px"></div>
          </div>
          @endfor
        </div>
      </div>
    </div>

    {{-- Content --}}

    @if (session('success'))
      <div class="alert-success">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        {{ session('success') }}
      </div>
    @endif

    @if ($teamNotComplete)
      <div class="alert-info">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        Your Team Leader must first mark your team as <strong>Complete</strong> in <strong>Team Management</strong> before you can upload receipts.
      </div>
    @endif

    <!-- Main Content Grid -->
    <div class="fin-grid">
      <!-- Upload Receipt Card -->
      <div class="card upload-card @if ($teamStatus !== 'complete') upload-card--disabled @endif">
        @if ($teamStatus !== 'complete')
          <div class="upload-card__lock">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span>Team must be marked as Complete by the Team Leader before uploading receipts.</span>
          </div>
        @endif
        <div class="card-header"><span class="card-title">Upload Receipt</span></div>
        <form method="POST" action="{{ route('student.financial.submit') }}" enctype="multipart/form-data" data-team-status="{{ $teamStatus }}" id="receiptForm">
          @csrf
          <div class="form-group">
            <label class="label">Expense Category</label>
            <select class="input" name="expense_post_id" id="expenseCategory" required {{ $teamStatus !== 'complete' ? 'disabled' : '' }}>
              <option value="">Select Expense</option>
              @foreach ($expensePosts as $post)
                <option value="{{ $post->id }}"
                  data-amount="{{ $post->amount }}"
                  data-description="{{ $post->description }}">
                  {{ $post->title }} — PHP {{ number_format($post->amount, 2) }}
                </option>
              @endforeach
            </select>
            @error('expense_post_id')
              <div class="field-error">{{ $message }}</div>
            @enderror
          </div>
          <div class="form-group">
            <label class="label">Receipt Number</label>
            <input class="input" name="receipt_number" id="receiptNumber" type="text" maxlength="100" placeholder="Enter Receipt Number" value="{{ old('receipt_number') }}" required {{ $teamStatus !== 'complete' ? 'disabled' : '' }} />
            @error('receipt_number')
              <div class="field-error">{{ $message }}</div>
            @enderror
          </div>
          <div class="form-group">
            <label class="label">Amount (PHP)</label>
            <input class="input" id="expenseAmount" name="amount" type="number" step="0.01" readonly placeholder="Select an expense category" />
          </div>
          <div class="form-group">
            <label class="label">Description</label>
            <input class="input" id="expenseDescription" name="description" readonly placeholder="Select an expense category" />
          </div>
          <div class="upload-zone" id="uploadZone" onclick="@if($teamStatus === 'complete')if(event.target===this||event.target.closest('#uploadPlaceholder'))document.getElementById('receiptInput').click()@endif">
            <div id="uploadPreviewContainer" style="display:none;position:relative">
              <button type="button" id="removeBtn" onclick="event.stopPropagation(); removeFile()" style="position:absolute;top:6px;right:6px;width:24px;height:24px;border-radius:50%;border:none;background:rgba(0,0,0,0.5);color:#fff;font-size:14px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:2" title="Remove">✕</button>
              <img id="uploadPreview" src="" alt="Receipt preview" class="upload-preview-img" onclick="event.stopPropagation(); openImagePreview(this.src)" />
              <div id="uploadFileInfo" class="upload-file-info"></div>
            </div>
            <div id="uploadPlaceholder">
              <div class="upload-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
              <div class="upload-label">Upload receipt image</div>
              <div class="upload-hint">JPG, PNG up to 10MB</div>
            </div>
            <input type="file" name="receipt_image" id="receiptInput" accept="image/jpeg,image/png" style="display:none" required {{ $teamStatus !== 'complete' ? 'disabled' : '' }} />
          </div>
          @error('receipt_image')
            <div class="field-error" style="margin-bottom:12px">{{ $message }}</div>
          @enderror
          <button type="submit" class="btn btn-primary btn-submit" {{ $teamStatus !== 'complete' ? 'disabled' : '' }}>Submit Receipt</button>
        </form>

        <!-- Submission Guidelines -->
        <div class="guidelines-card">
          <div class="guidelines-card__header">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            Submission Guidelines
          </div>
          <ul class="guidelines-card__list">
            <li>Upload only clear and readable receipts.</li>
            <li>Ensure the Receipt Number matches the physical receipt.</li>
            <li>The amount entered must match the receipt.</li>
            <li>Blurry or incomplete receipts may be rejected.</li>
          </ul>
        </div>
      </div>

      <!-- Submitted Receipts -->
      <div class="card receipts-card">
        <div class="card-header"><span class="card-title">Submitted Receipts</span></div>

        @php
          $receiptCategories = $submissions->map(function($s) {
            return $s->expensePost?->title ?? $s->payment_for;
          })->unique()->filter()->values();
        @endphp

        <div class="filter-bar">
          <div class="filter-search fg-row1">
            <svg class="filter-search__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="receiptSearch" class="filter-search__input" placeholder="Search" />
          </div>
          <select id="statusFilter" class="filter-select fg-row1" style="width:130px">
            <option value="">All Status</option>
            <option value="verified">Verified</option>
            <option value="pending">Pending</option>
            <option value="rejected">Rejected</option>
          </select>
          <select id="categoryFilter" class="filter-select fg-row2" style="max-width:180px">
            <option value="">All Categories</option>
            @foreach ($receiptCategories as $cat)
              <option value="{{ $cat }}">{{ $cat }}</option>
            @endforeach
          </select>
          <select id="sortFilter" class="filter-select fg-row2" style="width:140px">
            <option value="newest">Newest First</option>
            <option value="oldest">Oldest First</option>
          </select>
        </div>

        <div id="receiptsContainer">
          @forelse ($submissions as $submission)
            @php
              $badgeClass = match($submission->f_record_status) {
                'verified' => 'badge-green',
                'pending' => 'badge-amber',
                'rejected' => 'badge-red',
                default => 'badge-amber',
              };
              $badgeText = match($submission->f_record_status) {
                'verified' => 'Verified',
                'pending' => 'Pending',
                'rejected' => 'Rejected',
                default => 'Pending',
              };
              $expenseTitle = $submission->expensePost?->title ?? $submission->payment_for ?? 'N/A';
              $displayText = $submission->description ? $expenseTitle . ' — ' . $submission->description : $expenseTitle;
              $receiptImageUrl = $submission->receipt_image ? asset('storage/' . $submission->receipt_image) : null;
              $uploadedDt = $submission->uploaded_at ? \Carbon\Carbon::parse($submission->uploaded_at) : null;
              $verifiedDt = $submission->verified_at ? \Carbon\Carbon::parse($submission->verified_at) : null;
            @endphp
            <div class="receipt-card"
              data-status="{{ $submission->f_record_status }}"
              data-category="{{ $expenseTitle }}"
              data-description="{{ $submission->description ?? '' }}"
              data-date="{{ $uploadedDt ? $uploadedDt->format('Y-m-d H:i:s') : '' }}"
              data-amount="{{ $submission->amount ?? 0 }}"
              data-receipt="{{ $receiptImageUrl ?? '' }}"
              data-receipt-number="{{ $submission->receipt_number ?? '' }}"
              data-reviewed-at="{{ $verifiedDt ? $verifiedDt->format('Y-m-d H:i:s') : '' }}"
              data-reviewed-by="{{ $submission->verified_by ?? '' }}"
              data-feedback="{{ $submission->rejection_reason ?? '' }}">
              <div class="receipt-card__thumb" onclick="event.stopPropagation(); openImagePreview('{{ $receiptImageUrl ?? '' }}')">
                @if ($receiptImageUrl)
                  <img src="{{ $receiptImageUrl }}" alt="Receipt" />
                @else
                  <div class="receipt-card__thumb-fallback">
                    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                  </div>
                @endif
              </div>
              <div class="receipt-card__body">
                @if ($submission->receipt_number)
                  <div class="receipt-card__receipt-no">{{ $submission->receipt_number }}</div>
                @endif
                <div class="receipt-card__category">{{ $displayText }}</div>
                <div class="receipt-card__meta">
                  <span class="receipt-card__date">{{ $uploadedDt ? $uploadedDt->format('M d, Y \\a\\t g:i A') : 'N/A' }}</span>
                  <span class="receipt-card__amount">PHP {{ number_format($submission->amount ?? 0, 2) }}</span>
                </div>
              </div>
              <div class="receipt-card__aside">
                <span class="badge {{ $badgeClass }}">{{ $badgeText }}</span>
                <button class="btn btn-view-details" onclick="event.stopPropagation(); openReceiptDetailModal(this.parentElement.parentElement)">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  View Details
                </button>
              </div>
            </div>
          @empty
            <div class="empty-state">
              <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" width="48" height="48"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              <h3 class="empty-state__title">No receipts submitted yet.</h3>
              <p class="empty-state__text">Upload your first capstone expense receipt using the form above.</p>
            </div>
          @endforelse
        </div>

      </div>
    </div>

    <!-- Unified Receipt Details Modal -->
    <div class="modal-overlay" id="receiptDetailModal" onclick="if(event.target===this)closeReceiptDetailModal()">
      <div class="modal-content modal-content--detail">
        <div class="modal-header">
          <div class="modal-header__left">
            <h3 class="modal-title">Receipt Details</h3>
            <div class="modal-subtitle" id="modalReceiptNumber">
              <span class="modal-subtitle__label">Receipt No.</span>
              <span class="modal-subtitle__value" id="detailReceiptNumber">—</span>
            </div>
          </div>
          <div class="modal-header__right">
            <span id="detailStatusBadge"></span>
            <button class="btn btn-modal-close" onclick="closeReceiptDetailModal()">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
        </div>

        <div class="modal-body">
          <!-- Section 1: Receipt Information -->
          <div class="modal-section">
            <div class="modal-section__title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
              Receipt Information
            </div>
            <div class="info-grid">
              <div class="info-grid__item">
                <span class="info-grid__label">Expense Category</span>
                <span class="info-grid__value" id="detailCategory">—</span>
              </div>
              <div class="info-grid__item">
                <span class="info-grid__label">Amount</span>
                <span class="info-grid__value" id="detailAmount">—</span>
              </div>
              <div class="info-grid__item info-grid__item--full">
                <span class="info-grid__label">Description</span>
                <span class="info-grid__value" id="detailDescription">—</span>
              </div>
              <div class="info-grid__item">
                <span class="info-grid__label">Submitted Date</span>
                <span class="info-grid__value" id="detailDate">—</span>
              </div>
              <div class="info-grid__item">
                <span class="info-grid__label">Submitted Time</span>
                <span class="info-grid__value" id="detailTime">—</span>
              </div>
            </div>
          </div>

          <div class="modal-divider"></div>

          <!-- Section 3: Verification Information -->
          <div class="modal-section">
            <div class="modal-section__title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              Verification Information
            </div>
            <div class="info-grid">
              <div class="info-grid__item">
                <span class="info-grid__label">Status</span>
                <span id="detailStatus"></span>
              </div>
              <div class="info-grid__item" id="detailReviewedByWrapper" style="display:none">
                <span class="info-grid__label">Reviewed By</span>
                <span class="info-grid__value" id="detailReviewedBy">—</span>
              </div>
              <div class="info-grid__item" id="detailReviewedOnWrapper" style="display:none">
                <span class="info-grid__label">Reviewed Date</span>
                <span class="info-grid__value" id="detailReviewedOn">—</span>
              </div>
            </div>
          </div>

          <div class="modal-divider"></div>

          <!-- Section 4: Administrator Feedback -->
          <div class="modal-section">
            <div class="modal-section__title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              Administrator Feedback
            </div>
            <div id="detailFeedbackContainer">
              <div class="feedback-placeholder">Loading...</div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Image Preview Modal -->
    <div class="modal-overlay" id="imagePreviewModal" onclick="closeImagePreview()">
      <button onclick="closeImagePreview()" style="position:absolute;top:16px;right:16px;width:36px;height:36px;border-radius:50%;border:none;background:rgba(0,0,0,0.5);color:#fff;font-size:18px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:10" title="Close">✕</button>
      <img id="imagePreviewFull" src="" alt="Receipt preview" class="modal-img" />
    </div>

    <!-- Team Not Complete Modal -->
    <div class="modal-overlay" id="teamNotCompleteModal" onclick="if(event.target===this)closeTeamNotCompleteModal()">
      <div class="modal-content modal-content--compact">
        <div class="modal-header">
          <h3 class="modal-title">Team Not Yet Complete</h3>
          <button class="btn btn-modal-close" onclick="closeTeamNotCompleteModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <div class="modal-body">
          <p style="margin:0;line-height:1.6;color:#475569;font-size:0.95rem;">
            Your Team Leader must first mark your team as <strong>Complete</strong> in <strong>Team Management</strong> before any team member can submit a receipt.
          </p>
        </div>
        <div class="modal-footer" style="border-top:1px solid #e2e8f0;padding:16px 24px;display:flex;justify-content:flex-end;">
          <button class="btn btn-primary" onclick="closeTeamNotCompleteModal()">OK</button>
        </div>
      </div>
    </div>

    <!-- Confirm Receipt Number Modal -->
    <div class="modal-overlay" id="confirmReceiptModal" onclick="if(event.target===this)closeConfirmReceiptModal()">
      <div class="modal-content" style="max-width:440px">
        <div class="modal-header">
          <h3 class="modal-title">Confirm Receipt Number</h3>
          <button class="btn btn-modal-close" onclick="closeConfirmReceiptModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <div class="modal-body">
          <p style="margin:0 0 18px;line-height:1.7;color:#475569;font-size:0.95rem;">
            Please verify that the Receipt Number you entered is correct. Once submitted, it will be sent for Admin verification.
          </p>
          <div class="confirm-receipt-box">
            <div class="confirm-receipt-box__label">Receipt Number</div>
            <div class="confirm-receipt-box__value" id="confirmReceiptNumber">—</div>
          </div>
          <p style="margin:18px 0 0;line-height:1.5;color:#64748b;font-size:0.9rem;text-align:center;">
            Are you sure you want to continue?
          </p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-ghost" onclick="closeConfirmReceiptModal()">Cancel</button>
          <button class="btn btn-primary" id="confirmSubmitBtn" onclick="submitReceipt()">Submit Receipt</button>
        </div>
      </div>
    </div>
    </div>
  </div>
</div>

<script>
var skeleton = document.getElementById('pageSkeleton');
if (skeleton) { setTimeout(function() { skeleton.classList.add('loaded'); }, 450); }

let prevFileName = '';

document.getElementById('expenseCategory')?.addEventListener('change', function() {
  const selected = this.options[this.selectedIndex];
  const amountInput = document.getElementById('expenseAmount');
  const descInput = document.getElementById('expenseDescription');
  if (selected && selected.value) {
    amountInput.value = selected.dataset.amount || '';
    descInput.value = selected.dataset.description || '';
  } else {
    amountInput.value = '';
    descInput.value = '';
  }
});

document.getElementById('receiptInput')?.addEventListener('change', function() {
  if (this.files && this.files[0]) {
    const file = this.files[0];
    prevFileName = file.name;
    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('uploadPreview').src = e.target.result;
      document.getElementById('uploadPreviewContainer').style.display = 'block';
      document.getElementById('uploadPlaceholder').style.display = 'none';
      const size = file.size > 1024 * 1024
        ? (file.size / (1024 * 1024)).toFixed(1) + ' MB'
        : (file.size / 1024).toFixed(1) + ' KB';
      document.getElementById('uploadFileInfo').innerHTML = '<span class="upload-filename">' + file.name + '</span><span class="upload-filesize">' + size + '</span>';
      document.getElementById('uploadZone').classList.add('has-file');
    };
    reader.readAsDataURL(file);
  }
});

window.removeFile = function() {
  const input = document.getElementById('receiptInput');
  input.value = '';
  document.getElementById('uploadPreview').src = '';
  document.getElementById('uploadPreviewContainer').style.display = 'none';
  document.getElementById('uploadPlaceholder').style.display = 'block';
  document.getElementById('uploadFileInfo').innerHTML = '';
  document.getElementById('uploadZone').classList.remove('has-file');
};

window.openImagePreview = function(url) {
  if (!url) return;
  document.getElementById('imagePreviewFull').src = url;
  document.getElementById('imagePreviewModal').classList.add('active');
};

window.closeImagePreview = function() {
  document.getElementById('imagePreviewModal').classList.remove('active');
};

window.openReceiptDetailModal = function(card) {
  const modal = document.getElementById('receiptDetailModal');

  // Modal header: receipt number
  const rn = card.dataset.receiptNumber || 'N/A';
  document.getElementById('detailReceiptNumber').textContent = rn;

  // Status badge in header
  const badge = card.querySelector('.badge');
  document.getElementById('detailStatusBadge').innerHTML = '<span class="' + badge.className + '">' + badge.textContent + '</span>';

  // Receipt Information
  document.getElementById('detailCategory').textContent = card.dataset.category;
  document.getElementById('detailAmount').textContent = 'PHP ' + parseFloat(card.dataset.amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  document.getElementById('detailDescription').textContent = card.dataset.description || 'N/A';

  const dateStr = card.dataset.date;
  if (dateStr) {
    const d = new Date(dateStr);
    document.getElementById('detailDate').textContent = d.toLocaleDateString('en-US', {year:'numeric',month:'long',day:'numeric'});
    document.getElementById('detailTime').textContent = d.toLocaleTimeString('en-US', {hour:'numeric',minute:'2-digit'});
  } else {
    document.getElementById('detailDate').textContent = 'N/A';
    document.getElementById('detailTime').textContent = 'N/A';
  }

  // Verification Information
  const statusText = badge.textContent;
  const statusClass = badge.className;
  document.getElementById('detailStatus').innerHTML = '<span class="' + statusClass + '">' + statusText + '</span>';

  const reviewedAt = card.dataset.reviewedAt;
  const reviewedBy = card.dataset.reviewedBy;
  const reviewedByWrapper = document.getElementById('detailReviewedByWrapper');
  const reviewedOnWrapper = document.getElementById('detailReviewedOnWrapper');

  if (reviewedAt && reviewedBy) {
    reviewedByWrapper.style.display = 'block';
    reviewedOnWrapper.style.display = 'block';
    document.getElementById('detailReviewedBy').textContent = reviewedBy;
    const d = new Date(reviewedAt);
    document.getElementById('detailReviewedOn').textContent = d.toLocaleDateString('en-US', {year:'numeric',month:'long',day:'numeric'});
  } else {
    reviewedByWrapper.style.display = 'none';
    reviewedOnWrapper.style.display = 'none';
  }

  // Administrator Feedback
  const feedbackContainer = document.getElementById('detailFeedbackContainer');
  const feedback = card.dataset.feedback || '';

  if (statusText === 'Verified') {
    feedbackContainer.innerHTML = '<div class="feedback-info feedback-info--verified">' +
      '<div class="feedback-info__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>' +
      '<div class="feedback-info__text">This receipt has been successfully verified.</div>' +
      '</div>';
  } else if (statusText === 'Rejected' && feedback) {
    feedbackContainer.innerHTML = '<div class="feedback-info feedback-info--rejected">' +
      '<div class="feedback-info__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>' +
      '<div class="feedback-info__content"><div class="feedback-info__label">Administrator Feedback</div><div class="feedback-info__message">' + feedback + '</div></div>' +
      '</div>';
  } else {
    feedbackContainer.innerHTML = '<div class="feedback-info feedback-info--pending">' +
      '<div class="feedback-info__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>' +
      '<div class="feedback-info__text">No feedback available yet. This receipt is still waiting for administrator review.</div>' +
      '</div>';
  }

  modal.classList.add('active');
};

window.closeReceiptDetailModal = function() {
  document.getElementById('receiptDetailModal').classList.remove('active');
};

window.openTeamNotCompleteModal = function() {
  document.getElementById('teamNotCompleteModal').classList.add('active');
};

window.closeTeamNotCompleteModal = function() {
  document.getElementById('teamNotCompleteModal').classList.remove('active');
};

window.openConfirmReceiptModal = function() {
  document.getElementById('confirmReceiptModal').classList.add('active');
};

window.closeConfirmReceiptModal = function() {
  document.getElementById('confirmReceiptModal').classList.remove('active');
};

// ── Submit Receipt via REST API (Confirmation Flow) ──
document.getElementById('receiptForm')?.addEventListener('submit', function(e) {
  e.preventDefault();

  const teamStatus = this.dataset.teamStatus;
  if (teamStatus !== 'complete') {
    openTeamNotCompleteModal();
    return;
  }

  // Clear previous errors
  this.querySelectorAll('.field-error').forEach(function(el) { el.remove(); });
  this.querySelectorAll('.input-error').forEach(function(el) { el.classList.remove('input-error'); });
  const existingAlert = document.querySelector('.alert-success.api');
  if (existingAlert) existingAlert.remove();

  // Show confirmation modal with the entered receipt number
  const receiptNumber = document.getElementById('receiptNumber').value;
  document.getElementById('confirmReceiptNumber').textContent = receiptNumber || '—';
  openConfirmReceiptModal();
});

// ── Actual submission logic (called after confirmation) ──
window.submitReceipt = async function() {
  closeConfirmReceiptModal();

  const form = document.getElementById('receiptForm');
  const submitBtn = form.querySelector('.btn-submit');
  submitBtn.disabled = true;
  submitBtn.textContent = 'Submitting...';

  const formData = new FormData(form);

  try {
    const response = await fetch('/api/student/financial/submit', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        'Accept': 'application/json',
      },
      body: formData,
    });

    const result = await response.json();

    if (!response.ok) {
      if (response.status === 422 && result.errors) {
        for (const [field, messages] of Object.entries(result.errors)) {
          const input = form.querySelector('[name="' + field + '"]');
          if (input) {
            input.classList.add('input-error');
            const errorDiv = document.createElement('div');
            errorDiv.className = 'field-error';
            errorDiv.textContent = messages[0];
            if (field === 'receipt_image') {
              const container = document.querySelector('.upload-zone');
              if (container) container.parentNode.insertBefore(errorDiv, container.nextSibling);
            } else if (input.parentNode) {
              input.parentNode.appendChild(errorDiv);
            }
          }
        }
        if (result.errors.team_status) {
          openTeamNotCompleteModal();
        }
      } else {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert-success api';
        alertDiv.style.cssText = 'background:#fef2f2;border-color:#fca5a5;color:#dc2626;';
        alertDiv.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>' + (result.message || 'Something went wrong.');
        document.querySelector('.content').insertBefore(alertDiv, document.querySelector('.fin-grid'));
      }
      submitBtn.disabled = false;
      submitBtn.textContent = 'Submit Receipt';
      return;
    }

    // Success
    form.reset();
    document.getElementById('uploadPreviewContainer').style.display = 'none';
    document.getElementById('uploadPlaceholder').style.display = 'block';
    document.getElementById('uploadFileInfo').innerHTML = '';
    document.getElementById('uploadZone').classList.remove('has-file');

    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert-success api';
    alertDiv.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' + (result.message || 'Receipt submitted successfully.');
    document.querySelector('.content').insertBefore(alertDiv, document.querySelector('.fin-grid'));

    addSubmissionToDOM(result.data);
    removeExpensePostFromDropdown(result.data.expense_post_id);

    submitBtn.disabled = false;
    submitBtn.textContent = 'Submit Receipt';

    window.scrollTo({ top: 0, behavior: 'smooth' });
  } catch (error) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert-success api';
    alertDiv.style.cssText = 'background:#fef2f2;border-color:#fca5a5;color:#dc2626;';
    alertDiv.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>Something went wrong. Please try again.';
    document.querySelector('.content').insertBefore(alertDiv, document.querySelector('.fin-grid'));
    submitBtn.disabled = false;
    submitBtn.textContent = 'Submit Receipt';
  }
};

function addSubmissionToDOM(data) {
  const container = document.getElementById('receiptsContainer');
  // Remove empty state if present
  const emptyState = container.querySelector('.empty-state');
  if (emptyState) emptyState.remove();

  const statusBadgeClass = data.status === 'verified' ? 'badge-green' : data.status === 'rejected' ? 'badge-red' : 'badge-amber';
  const statusBadgeText = data.status.charAt(0).toUpperCase() + data.status.slice(1);
  const formattedAmount = 'PHP ' + parseFloat(data.amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  const displayDate = data.uploaded_at_formatted || 'N/A';

  const card = document.createElement('div');
  card.className = 'receipt-card';
  card.setAttribute('data-status', data.status);
  card.setAttribute('data-category', data.category);
  card.setAttribute('data-description', data.description || '');
  card.setAttribute('data-date', data.uploaded_at || '');
  card.setAttribute('data-amount', data.amount);
  card.setAttribute('data-receipt', data.receipt_image || '');
  card.setAttribute('data-receipt-number', data.receipt_number || '');
  card.setAttribute('data-reviewed-at', data.verified_at || '');
  card.setAttribute('data-reviewed-by', data.verified_by || '');
  card.setAttribute('data-feedback', data.rejection_reason || '');

  card.innerHTML =
    '<div class="receipt-card__thumb" onclick="event.stopPropagation(); openImagePreview(\'' + (data.receipt_image || '') + '\')">' +
    (data.receipt_image
      ? '<img src="' + data.receipt_image + '" alt="Receipt" />'
      : '<div class="receipt-card__thumb-fallback"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>') +
    '</div>' +
    '<div class="receipt-card__body">' +
    (data.receipt_number ? '<div class="receipt-card__receipt-no">' + data.receipt_number + '</div>' : '') +
    '<div class="receipt-card__category">' + data.display_text + '</div>' +
    '<div class="receipt-card__meta">' +
    '<span class="receipt-card__date">' + displayDate + '</span>' +
    '<span class="receipt-card__amount">' + formattedAmount + '</span>' +
    '</div>' +
    '</div>' +
    '<div class="receipt-card__aside">' +
    '<span class="badge ' + statusBadgeClass + '">' + statusBadgeText + '</span>' +
    '<button class="btn btn-view-details" onclick="event.stopPropagation(); openReceiptDetailModal(this.parentElement.parentElement)">' +
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>' +
    ' View Details' +
    '</button>' +
    '</div>';

  container.insertBefore(card, container.firstChild);

  // Update category filter options
  updateCategoryFilter(data.category);
}

function removeExpensePostFromDropdown(expensePostId) {
  const select = document.getElementById('expenseCategory');
  if (!select) return;
  const option = select.querySelector('option[value="' + expensePostId + '"]');
  if (option) option.remove();
}

function updateCategoryFilter(category) {
  if (!category) return;
  const filter = document.getElementById('categoryFilter');
  if (!filter) return;
  const exists = filter.querySelector('option[value="' + category + '"]');
  if (!exists) {
    const opt = document.createElement('option');
    opt.value = category;
    opt.textContent = category;
    filter.appendChild(opt);
  }
}

document.getElementById('receiptSearch')?.addEventListener('input', filterReceipts);
document.getElementById('statusFilter')?.addEventListener('change', filterReceipts);
document.getElementById('categoryFilter')?.addEventListener('change', filterReceipts);
document.getElementById('sortFilter')?.addEventListener('change', filterReceipts);

function filterReceipts() {
  const search = (document.getElementById('receiptSearch').value || '').toLowerCase();
  const status = document.getElementById('statusFilter').value;
  const category = document.getElementById('categoryFilter').value;
  const sort = document.getElementById('sortFilter').value;

  const container = document.getElementById('receiptsContainer');
  const cards = container.querySelectorAll('.receipt-card');
  let visibleCards = [];

  cards.forEach(function(card) {
    const cardCategory = (card.dataset.category || '').toLowerCase();
    const cardStatus = card.dataset.status || '';

    const matchesSearch = !search || cardCategory.includes(search);
    const matchesStatus = !status || cardStatus === status;
    const matchesCategory = !category || cardCategory === category.toLowerCase();

    if (matchesSearch && matchesStatus && matchesCategory) {
      card.style.display = 'flex';
      visibleCards.push(card);
    } else {
      card.style.display = 'none';
    }
  });

  if (sort === 'oldest') {
    visibleCards.sort(function(a, b) {
      return new Date(a.dataset.date) - new Date(b.dataset.date);
    });
  } else {
    visibleCards.sort(function(a, b) {
      return new Date(b.dataset.date) - new Date(a.dataset.date);
    });
  }

  visibleCards.forEach(function(card) {
    container.appendChild(card);
  });

  const existingEmpty = container.querySelector('.empty-state-filtered');
  if (existingEmpty) existingEmpty.remove();

  if (visibleCards.length === 0 && cards.length > 0) {
    const empty = document.createElement('div');
    empty.className = 'empty-state empty-state-filtered';
    empty.innerHTML = '<svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" width="48" height="48"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><h3 class="empty-state__title">No matching receipts.</h3><p class="empty-state__text">Try adjusting your search or filter criteria.</p>';
    container.appendChild(empty);
  }
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
</body>
</html>
<!-- SIDEBAR LOADER -->
