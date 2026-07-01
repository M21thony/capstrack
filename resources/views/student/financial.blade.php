<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Financial Records - CapsTRACK</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/styles.css')
</head>
<body>
@include('student.partials.sidebar')

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="page-title">Financial Records</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'financial'])
<div class="grid-2" style="margin-bottom:16px">
  <div class="card">
    <div class="card-header"><span class="card-title">Upload Receipt</span></div>
    <div class="form-group">
      <label class="label">Expense Category</label>
      <select class="input"><option>Research Materials</option><option>Printing &amp; Binding</option><option>Software License</option><option>Transportation</option><option>Other</option></select>
    </div>
    <div class="form-group">
      <label class="label">Amount (PHP)</label>
      <input class="input" placeholder="e.g. 1500.00" type="number" />
    </div>
    <div class="form-group">
      <label class="label">Description</label>
      <input class="input" placeholder="Brief description of expense" />
    </div>
    <div class="upload-zone" style="margin-bottom:12px">
      <div class="upload-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
      <div style="font-size:12px;color:var(--text2);font-weight:500;margin-bottom:4px">Upload receipt image</div>
      <div style="font-size:10px;color:var(--text3)">JPG, PNG up to 10MB</div>
    </div>
    <button class="btn btn-primary" style="width:100%;justify-content:center">Submit Receipt</button>
  </div>
  <div class="card">
    <div class="card-header"><span class="card-title">Submitted Receipts</span></div>
    <div>
      <div class="receipt-item">
        <div class="receipt-thumb"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
        <div style="flex:1">
          <div style="font-size:12px;font-weight:500;color:var(--text)">Printing &amp; Binding — Ch. 1-2</div>
          <div style="font-size:11px;color:var(--text3)">Apr 20, 2025 · PHP 850.00</div>
        </div>
        <span class="badge badge-green">Verified</span>
      </div>
      <div class="receipt-item">
        <div class="receipt-thumb"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
        <div style="flex:1">
          <div style="font-size:12px;font-weight:500;color:var(--text)">Software — Grammarly Premium</div>
          <div style="font-size:11px;color:var(--text3)">Apr 22, 2025 · PHP 1,200.00</div>
        </div>
        <span class="badge badge-amber">Pending</span>
      </div>
      <div class="receipt-item">
        <div class="receipt-thumb"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
        <div style="flex:1">
          <div style="font-size:12px;font-weight:500;color:var(--text)">Transportation — Library Visit</div>
          <div style="font-size:11px;color:var(--text3)">Apr 28, 2025 · PHP 240.00</div>
        </div>
        <span class="badge badge-red">Rejected</span>
      </div>
    </div>
    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border);display:flex;justify-content:space-between">
      <span style="font-size:12px;color:var(--text3)">Total Approved</span>
      <span style="font-family:'Space Mono',monospace;font-size:14px;font-weight:700;color:var(--green)">PHP 850.00</span>
    </div>
  </div>
</div>
</div>
</div>


</body>
</html>
<!-- SIDEBAR LOADER -->
