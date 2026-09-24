<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Activity Log - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
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
    <div class="page-title">Activity Log</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="row" style="margin-bottom:16px;gap:8px">
      <div class="sk" style="width:300px;height:34px;border-radius:8px"></div>
      <div class="sk" style="width:180px;height:34px;border-radius:8px"></div>
      <div class="sk" style="width:80px;height:34px;border-radius:8px"></div>
    </div>
    <div class="card" style="padding:16px">
      <div class="sk" style="height:36px;border-radius:8px;margin-bottom:8px"></div>
      <div class="sk" style="height:36px;border-radius:8px;margin-bottom:8px"></div>
      <div class="sk" style="height:36px;border-radius:8px;margin-bottom:8px"></div>
      <div class="sk" style="height:36px;border-radius:8px;margin-bottom:8px"></div>
      <div class="sk" style="height:36px;border-radius:8px"></div>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content">
    <form method="GET" class="row log-filters" style="margin-bottom:16px;gap:8px">
      <input class="input" name="search" placeholder="Search by student or action..." style="max-width:300px" value="{{ request('search') }}"/>
      <select class="input" name="type" style="width:180px" onchange="this.form.submit()">
        <option value="">All Actions</option>
        @foreach($types as $t)
        <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>{{ \App\Support\ActivityIcons::for($t)['label'] }}</option>
        @endforeach
      </select>
      <button class="btn btn-ghost" style="font-size:11px;padding:5px 12px">Filter</button>
    </form>
    <div class="card" style="padding:0;overflow:hidden">
      <table class="table">
        <thead>
          <tr>
            <th style="padding-left:20px">ID</th>
            <th>Time</th>
            <th>Student</th>
            <th>Team</th>
            <th>Action</th>
            <th>Description</th>
          </tr>
        </thead>
        <tbody>
        @forelse($logs as $log)
          <tr>
            <td style="padding-left:20px" data-label="ID"><a href="javascript:void(0)" onclick="openLogModal({{ $log->id }})" style="color:var(--accent);text-decoration:underline;font-size:12px;font-variant-numeric:tabular-nums;">#{{ $log->id }}</a></td>
            <td style="font-variant-numeric:tabular-nums;font-size:11px;color:var(--text3)" data-label="Time">{{ $log->created_at->format('M j · H:i') }}</td>
            <td class="td-main" data-label="Student">{{ $log->student?->f_name }} {{ $log->student?->l_name }}</td>
            <td data-label="Team"><span class="badge badge-gold" style="font-size:10px">{{ $log->student?->currentTeam?->team_name ?? $log->metadata['team_name'] ?? 'N/A' }}</span></td>
            @php $face = \App\Support\ActivityIcons::for($log->type); @endphp
            <td data-label="Action"><span class="al-action al-tone-{{ $face['tone'] }}" title="{{ $log->type }}">{!! $face['svg'] !!}{{ $face['label'] }}</span></td>
            <td style="font-size:12px;color:var(--text2)" data-label="Description">{!! Str::limit($log->description, 60) !!}</td>
          </tr>
        @empty
          <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text3)">No activity logs found.</td></tr>
        @endforelse
        </tbody>
      </table>
      <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
        <span style="font-size:12px;color:var(--text3)">Showing {{ $logs->firstItem() }}–{{ $logs->lastItem() }} of {{ $logs->total() }} records</span>
        <div style="display:flex;gap:6px">
          @if($logs->onFirstPage())
            <span class="btn btn-ghost" style="font-size:11px;padding:5px 12px;opacity:0.5">Prev</span>
          @else
            <a href="{{ $logs->previousPageUrl() }}" class="btn btn-ghost" style="font-size:11px;padding:5px 12px">Prev</a>
          @endif
          @if($logs->hasMorePages())
            <a href="{{ $logs->nextPageUrl() }}" class="btn btn-ghost" style="font-size:11px;padding:5px 12px">Next</a>
          @else
            <span class="btn btn-ghost" style="font-size:11px;padding:5px 12px;opacity:0.5">Next</span>
          @endif
        </div>
      </div>
    </div>
  </div>
  </div>
</div>
@include('capsa.partials.skeleton-toggle')

<div class="modal-overlay" id="logModal" onclick="if(event.target===this)closeLogModal()">
  <div class="modal-box" style="text-align:left;max-width:480px">
    <div class="modal-title" style="text-align:center;margin-bottom:20px">Activity Log Details</div>
    <div id="logModalBody" style="font-size:13px;color:var(--text2)">
      <div style="margin-bottom:10px"><strong style="color:var(--text)">Time:</strong> <span id="modalTime"></span></div>
      <div style="margin-bottom:10px"><strong style="color:var(--text)">Student:</strong> <span id="modalStudent"></span></div>
      <div style="margin-bottom:10px"><strong style="color:var(--text)">Team:</strong> <span id="modalTeam"></span></div>
      <div style="margin-bottom:10px"><strong style="color:var(--text)">Action:</strong> <span id="modalAction"></span></div>
      <div style="margin-bottom:10px"><strong style="color:var(--text)">Description:</strong> <span id="modalDesc"></span></div>
    </div>
    <div class="modal-actions" style="margin-top:20px;justify-content:center">
      <button class="btn btn-ghost" onclick="closeLogModal()">Close</button>
    </div>
  </div>
</div>

<style>
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:1000;opacity:0;pointer-events:none;transition:opacity 0.2s}
.modal-overlay.open{opacity:1;pointer-events:auto}
.modal-box{background:#fff;border:1px solid var(--border);border-radius:16px;padding:32px;max-width:480px;width:90%}
.modal-title{font-size:17px;font-weight:700;color:var(--text)}
.modal-actions{display:flex;gap:10px;justify-content:flex-end}
</style>

<script>
const logData = {!! $logDataJson !!};

function openLogModal(id) {
    const log = logData.find(l => l.id === id);
    if (!log) return;
    document.getElementById('modalTime').textContent = log.time;
    document.getElementById('modalStudent').textContent = log.student;
    document.getElementById('modalTeam').textContent = log.team;
    document.getElementById('modalAction').textContent = log.action;
    document.getElementById('modalDesc').innerHTML = log.description;
    document.getElementById('logModal').classList.add('open');
}

function closeLogModal() {
    document.getElementById('logModal').classList.remove('open');
}


/* Mobile sidebar toggle */
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
@include('partials.escape-closes-modals')
</body>
</html>
