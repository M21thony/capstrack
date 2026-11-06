<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Fees - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
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
    <div class="page-title">Fees</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="card" style="padding:16px">
      <div class="sk" style="height:36px;border-radius:8px;margin-bottom:8px"></div>
      <div class="sk" style="height:36px;border-radius:8px;margin-bottom:8px"></div>
      <div class="sk" style="height:36px;border-radius:8px;margin-bottom:8px"></div>
      <div class="sk" style="height:36px;border-radius:8px"></div>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content list-page">
    <div class="card list-card">
      <table class="table" data-export="Fees">
        <thead>
          <tr>
            <th style="padding-left:20px">Section</th>
            <th>Amount</th>
            <th>Receipts</th>
            <th>Sent On</th>
            <th>Sent By</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
        @forelse ($rows as $row)
          <tr>
            <td class="td-main" style="padding-left:20px" data-label="Section">{{ $row['section'] }}</td>
            <td style="font-variant-numeric:tabular-nums" data-label="Amount">₱{{ number_format($row['amount'], 2) }}</td>
            <td style="font-variant-numeric:tabular-nums" data-label="Receipts">{{ $row['receipts'] }}</td>
            <td data-label="Sent On">{{ $row['sent_on'] ?? '—' }}</td>
            <td data-label="Sent By">{{ $row['sent_by'] ?? '—' }}</td>
            <td data-label="Status">{{ $row['is_sent'] ? 'Sent' : 'Not sent yet' }}</td>
          </tr>
        @empty
          <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text3)">No sections yet.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
  </div>
</div>
@include('capsa.partials.skeleton-toggle')

<script nonce="{{ Vite::cspNonce() }}">
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() { if (sidebarEl) { sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar(); } }
function openSidebar() { if (!sidebarEl || !sidebarOverlay) return; sidebarEl.classList.add('mobile-open'); sidebarOverlay.classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeSidebar() { if (!sidebarEl || !sidebarOverlay) return; sidebarEl.classList.remove('mobile-open'); sidebarOverlay.classList.remove('open'); document.body.style.overflow = ''; }
</script>
@include('partials.app-dialog')
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>
