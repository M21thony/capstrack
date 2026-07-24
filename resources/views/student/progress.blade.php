<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Progress Tracker - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/styles.css')
</head>
<body>
@include('student.partials.sidebar')

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="page-title">Progress Tracker</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'progress'])
<div class="grid-4" style="margin-bottom:16px">
  <div class="stat-chip"><div class="stat-n" style="color:var(--green)">{{ $completed }}</div><div class="stat-l">Completed</div></div>
  <div class="stat-chip"><div class="stat-n" style="color:var(--accent)">{{ $inProgress }}</div><div class="stat-l">In Progress</div></div>
  <div class="stat-chip"><div class="stat-n" style="color:var(--text3)">{{ $todo }}</div><div class="stat-l">To Do</div></div>
  <div class="stat-chip"><div class="stat-n" style="color:var(--red)">{{ $overdue }}</div><div class="stat-l">Overdue</div></div>
</div>

@if(!$teamId)
<div class="empty-state" style="text-align:center;padding:60px 20px">
  <div style="font-size:40px;margin-bottom:12px;opacity:0.3">&#128204;</div>
  <div style="font-size:15px;font-weight:600;color:var(--text2);margin-bottom:6px">No Team Yet</div>
  <div style="font-size:12px;color:var(--text3)">Join or create a team to start tracking milestones.</div>
</div>
@elseif($milestones->isEmpty())
<div class="card">
  <div class="card-header">
    <span class="card-title">Milestone Board</span>
    <button class="btn btn-primary" style="font-size:11px;padding:5px 12px" onclick="openAddMilestone()">+ Add Milestone</button>
  </div>
  <div style="text-align:center;padding:40px 20px">
    <div style="font-size:13px;color:var(--text3)">No milestones created yet. Create one to start tracking your progress.</div>
  </div>
</div>
@else
<div class="card">
  <div class="card-header">
    <span class="card-title">Milestone Board</span>
    <button class="btn btn-primary" style="font-size:11px;padding:5px 12px" onclick="openAddMilestone()">+ Add Milestone</button>
  </div>
  <div>
    @foreach($milestones as $milestone)
    @php
      $dotClass = match($milestone->status) {
        'completed' => 'm-done',
        'in_progress' => 'm-active',
        'overdue' => '',
        default => 'm-todo',
      };
      $badgeClass = match($milestone->status) {
        'completed' => 'badge-green',
        'in_progress' => 'badge-blue',
        'overdue' => 'badge-red',
        default => 'badge-cyan',
      };
      $badgeLabel = match($milestone->status) {
        'completed' => 'Done',
        'in_progress' => 'Active',
        'overdue' => 'Overdue',
        default => 'Queued',
      };
    @endphp
    <div class="milestone-row">
      <div class="milestone-dot {{ $dotClass }}">
        @if($milestone->status === 'completed')
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        @elseif($milestone->status === 'overdue')
        <svg viewBox="0 0 24 24" width="10" height="10" style="stroke:var(--red);stroke-width:3;fill:none"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        @else
        <svg viewBox="0 0 24 24" style="stroke-width:2"><circle cx="12" cy="12" r="4" fill="currentColor"/></svg>
        @endif
      </div>
      <div class="milestone-content">
        <div class="milestone-title">{{ $milestone->title }}</div>
        <div class="milestone-meta">
          @if($milestone->due_date)
            {{ $milestone->status === 'completed' ? 'Completed' : 'Due' }} {{ $milestone->due_date->format('M j, Y') }}
          @endif
        </div>
      </div>
      <span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
    </div>
    @endforeach
  </div>
</div>
@endif
</div>
</div>

<div class="modal-overlay" id="milestone-modal" onclick="if(event.target===this)closeMilestoneModal()">
  <div class="modal-box" style="text-align:left;max-width:460px">
    <div class="modal-title" style="text-align:center;margin-bottom:20px">Add Milestone</div>
    <div class="form-group">
      <label class="label">Title *</label>
      <input class="input" id="ms-title" placeholder="e.g. Chapter 1 Submission"/>
    </div>
    <div class="form-group" style="margin-top:12px">
      <label class="label">Description</label>
      <textarea class="input" id="ms-desc" style="min-height:80px" placeholder="Brief description of this milestone"></textarea>
    </div>
    <div class="form-group" style="margin-top:12px">
      <label class="label">Due Date</label>
      <input class="input" id="ms-due" type="date"/>
    </div>
    <div class="modal-actions" style="margin-top:20px">
      <button class="btn btn-ghost" onclick="closeMilestoneModal()">Cancel</button>
      <button class="btn btn-primary" onclick="saveMilestone()">Add Milestone</button>
    </div>
  </div>
</div>

<style>
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center;z-index:1000;opacity:0;pointer-events:none;transition:opacity 0.2s}
.modal-overlay.open{opacity:1;pointer-events:auto}
.modal-box{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:28px;max-width:460px;width:90%;box-shadow:0 4px 24px rgba(0,0,0,0.1)}
.modal-title{font-size:18px;font-weight:700;color:#1e293b}
.form-group{margin-bottom:4px}
.label{display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px}
.modal-actions{display:flex;gap:10px;justify-content:flex-end}
.modal-actions .btn-ghost{color:#475569}
.modal-actions .btn-ghost:hover{color:#1e293b}
</style>

<script>
function openAddMilestone() {
  document.getElementById('milestone-modal').classList.add('open');
}

function closeMilestoneModal() {
  document.getElementById('milestone-modal').classList.remove('open');
  document.getElementById('ms-title').value = '';
  document.getElementById('ms-desc').value = '';
  document.getElementById('ms-due').value = '';
}

function saveMilestone() {
  const title = document.getElementById('ms-title').value.trim();
  if (!title) { document.getElementById('ms-title').focus(); return; }

  fetch('{{ route("student.progress.milestones.store") }}', {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({
      title: title,
      description: document.getElementById('ms-desc').value.trim(),
      due_date: document.getElementById('ms-due').value || null,
    }),
  })
    .then(r => r.json())
    .then(() => { location.reload(); })
    .catch(() => { alert('Failed to create milestone.'); });
}
</script>

</body>
</html>
