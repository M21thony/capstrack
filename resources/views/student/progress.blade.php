<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Progress Tracker - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/styles.css')
<x-dashboard.styles />
<style>
  @keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .sk-overlay { background: var(--bg); }
  .sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }
</style>
</head>
<body>
@include('student.partials.sidebar')

<div class="main">
  <div class="topbar">
    <div class="page-title">Progress Tracker</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'progress'])
    <div id="skeleton-content" class="sk-overlay">
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;margin-bottom:16px">
        @for ($i = 0; $i < 4; $i++)
        <div class="stat-chip">
          <div class="sk" style="width:30px;height:22px;border-radius:4px;margin-bottom:6px"></div>
          <div class="sk" style="width:60px;height:9px;border-radius:4px"></div>
        </div>
        @endfor
      </div>
      <div class="card">
        <div class="sk" style="width:140px;height:12px;border-radius:4px;margin-bottom:14px"></div>
        @for ($i = 0; $i < 4; $i++)
        <div style="display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)">
          <div class="sk" style="width:20px;height:20px;border-radius:50%;flex-shrink:0"></div>
          <div style="flex:1">
            <div class="sk" style="width:35%;height:12px;border-radius:4px;margin-bottom:6px"></div>
            <div class="sk" style="width:60%;height:9px;border-radius:4px"></div>
          </div>
        </div>
        @endfor
      </div>
    </div>

    <div id="real-content" style="display:none">
@if (session('success'))
<div style="padding:10px 14px;background:rgba(16,185,129,0.1);color:#059669;border-radius:8px;font-size:13px;margin-bottom:12px">{{ session('success') }}</div>
@endif
@if (session('error'))
<div style="padding:10px 14px;background:rgba(239,68,68,0.1);color:#dc2626;border-radius:8px;font-size:13px;margin-bottom:12px">{{ session('error') }}</div>
@endif

@if(!$teamId)
<div class="empty-state" style="text-align:center;padding:60px 20px">
  <div style="font-size:40px;margin-bottom:12px;opacity:0.3">&#128204;</div>
  <div style="font-size:15px;font-weight:600;color:var(--text2);margin-bottom:6px">No Team Yet</div>
  <div style="font-size:12px;color:var(--text3)">Join or create a team to start tracking milestones.</div>
</div>
@else
<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;margin-bottom:16px">
  <div class="stat-chip"><div class="stat-n" style="color:var(--green)">{{ $completed }}</div><div class="stat-l">Completed</div></div>
  <div class="stat-chip"><div class="stat-n" style="color:var(--amber)">{{ $pending }}</div><div class="stat-l">Pending</div></div>
  <div class="stat-chip"><div class="stat-n" style="color:var(--text3)">{{ $todo }}</div><div class="stat-l">To Do</div></div>
  <div class="stat-chip"><div class="stat-n" style="color:var(--red)">{{ $overdue }}</div><div class="stat-l">Overdue</div></div>
</div>

@if($milestoneAnnouncements->isEmpty())
<div class="card">
  <div class="card-header">
    <span class="card-title">Milestone Board</span>
  </div>
  <div style="text-align:center;padding:40px 20px">
    <div style="font-size:13px;color:var(--text3)">No milestone posts from admin yet.</div>
  </div>
</div>
@else
<div class="card">
  <div class="card-header">
    <span class="card-title">Milestone Board</span>
  </div>
  <div>
    @foreach($milestoneAnnouncements as $ann)
    @php
      $milestoneName = trim(str_replace('Milestone Reminder: ', '', $ann->title));
      $milestoneKey = strtolower($milestoneName);
      $milestoneId = $milestoneMap[$milestoneKey] ?? null;
      $dueDate = $dueDateMap[$milestoneKey] ?? null;
      $isDone = $milestoneId && in_array($milestoneId, $doneMilestoneIds, true);
    @endphp
    <div class="milestone-row">
      <div class="milestone-dot {{ $isDone ? 'm-done' : 'm-todo' }}">
        @if($isDone)
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        @else
        <svg viewBox="0 0 24 24" style="stroke-width:2"><circle cx="12" cy="12" r="4" fill="currentColor"/></svg>
        @endif
      </div>
      <div class="milestone-content" style="flex:1">
        <div class="milestone-title">{{ $milestoneName }}</div>
        <div class="milestone-meta" style="margin-bottom:4px">
          @if($ann->created_at)
            Posted {{ $ann->created_at->format('M j, Y') }}
          @endif
          @if($dueDate)
            &middot; Due {{ $dueDate }}
          @endif
        </div>
        @if($ann->body)
        <div style="font-size:12px;color:var(--text2);line-height:1.5;white-space:pre-wrap">{{ $ann->body }}</div>
        @endif
        @if($isDone && isset($fileMap[$milestoneId]))
        @php
          $doneRecord = $doneByMap[$milestoneId] ?? null;
          $doneStudent = $doneRecord?->student;
          $isVerified = $doneRecord?->is_verified ?? false;
        @endphp
        <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);display:flex;gap:8px">
          <div style="width:28px;height:28px;border-radius:50%;overflow:hidden;flex-shrink:0;background:var(--accent);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#18191f">
            @if($doneStudent && $doneStudent->avatarUrl())
            <img src="{{ $doneStudent->avatarUrl() }}" style="width:100%;height:100%;object-fit:cover" />
            @else
            {{ $doneStudent ? $doneStudent->avatarInitials() : '?' }}
            @endif
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:11px;font-weight:600;color:var(--text);margin-bottom:2px">
              {{ $doneStudent ? $doneStudent->f_name . ' ' . $doneStudent->l_name : 'Unknown' }}
              <span style="font-weight:400;color:var(--text3);font-size:10px">
                &middot; {{ $doneRecord?->completed_at?->diffForHumans() }}
              </span>
            </div>
            <div style="display:flex;align-items:flex-start;gap:8px">
              <div style="position:relative">
                <img src="{{ $fileMap[$milestoneId] }}" style="width:200px;height:112px;object-fit:cover;border-radius:8px;border:1px solid var(--border);cursor:pointer;display:block" onclick="openImageModal(this.src)" title="Click to view full size" />
                @if($isLeader)
                <button onclick="openEditPictureModal({{ $milestoneId }})" style="position:absolute;bottom:6px;right:6px;display:flex;align-items:center;gap:5px;font-size:11px;padding:5px 10px;border-radius:6px;border:none;background:rgba(0,0,0,0.65);color:#fff;font-weight:500;cursor:pointer;font-family:inherit;backdrop-filter:blur(4px);transition:background 0.15s" onmouseenter="this.style.background='rgba(0,0,0,0.85)'" onmouseleave="this.style.background='rgba(0,0,0,0.65)'" title="Replace picture">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                  Edit
                </button>
                @endif
              </div>
            </div>
          </div>
        </div>
        @endif
      </div>
      @if($isDone)
      @php $isVerified = $doneByMap[$milestoneId]?->is_verified ?? false; @endphp
      <span class="badge {{ $isVerified ? 'badge-green' : 'badge-gold' }}" style="flex-shrink:0">{{ $isVerified ? 'Done' : 'Pending' }}</span>
      @elseif($milestoneId)
        @if($isLeader)
        <button style="font-size:10px;padding:4px 10px;flex-shrink:0;display:inline-flex;align-items:center;gap:6px;border-radius:7px;font-weight:600;cursor:pointer;border:1.5px solid var(--border);background:transparent;color:var(--text2);font-family:inherit;white-space:nowrap" onclick="openUploadModal({{ $milestoneId }}, '{{ $milestoneName }}')">Mark as Done</button>
        @else
        <span style="font-size:10px;padding:4px 10px;flex-shrink:0;display:inline-flex;align-items:center;gap:6px;border-radius:7px;font-weight:600;border:1.5px solid var(--border);background:var(--bg2);color:var(--text3);font-family:inherit;white-space:nowrap;opacity:0.5">Leader only</span>
        @endif
      @endif
    </div>
    @endforeach
  </div>
</div>
@endif
@endif
    </div>
</div>
</div>

{{-- Upload Modal --}}
<div class="modal-overlay" id="upload-modal">
  <div class="modal-box">
    <form method="POST" action="{{ route('student.progress.milestones.mark-done') }}" enctype="multipart/form-data" id="upload-form">
      @csrf
      <input type="hidden" name="milestone_id" id="upload-milestone-id" value="" />

      {{-- Step 1: Select --}}
      <div id="upload-step-select">
        <div style="margin-bottom:16px">
          <div style="font-size:15px;font-weight:600;color:var(--text);margin-bottom:4px">Mark Milestone as Done</div>
          <div style="font-size:12px;color:var(--text3)" id="upload-milestone-name"></div>
        </div>
        <div style="background:var(--bg3);border-left:3px solid #c9a82c;padding:10px 14px;border-radius:6px;font-size:12px;color:var(--text2);line-height:1.5;margin-bottom:16px" id="upload-tutorial"></div>
        <div class="form-group">
          <label class="label" id="upload-file-label">Upload file</label>
          <input type="file" name="file" id="upload-file-input" accept="image/*" required class="input" style="padding:6px" onchange="onFileSelected()" />
          <div style="font-size:11px;color:var(--text3);margin-top:4px">Max 5MB, image only</div>
        </div>
        <div class="modal-actions" style="display:flex;gap:8px;margin-top:20px">
          <button type="button" style="flex:1;justify-content:center;font-size:13px;display:inline-flex;align-items:center;gap:7px;padding:7px 16px;border-radius:9px;font-weight:500;cursor:pointer;border:1.5px solid var(--border);background:transparent;color:var(--text2);font-family:inherit" onclick="closeUploadModal()">Cancel</button>
        </div>
      </div>

      {{-- Step 2: Review --}}
      <div id="upload-step-review" style="display:none">
        <div style="margin-bottom:16px">
          <div style="font-size:15px;font-weight:600;color:var(--text);margin-bottom:4px">Review &amp; Submit</div>
          <div style="font-size:12px;color:var(--text3)" id="review-milestone-name"></div>
        </div>
        <div style="margin-bottom:16px;text-align:center">
          <img id="review-preview" style="max-width:100%;max-height:280px;border-radius:8px;border:1px solid var(--border)" />
        </div>
        <div style="font-size:12px;color:var(--text2);margin-bottom:16px" id="review-filename"></div>
        <div class="modal-actions" style="display:flex;gap:8px">
          <button type="submit" class="btn" style="flex:1;justify-content:center;font-size:13px;background:var(--text);color:var(--navy-text);border:none">Submit</button>
          <button type="button" style="font-size:13px;display:inline-flex;align-items:center;gap:7px;padding:7px 16px;border-radius:9px;font-weight:500;cursor:pointer;border:1.5px solid var(--border);background:transparent;color:var(--text2);font-family:inherit" onclick="backToSelect()">Back</button>
        </div>
      </div>
    </form>
  </div>
</div>

{{-- Edit Picture Modal --}}
<div class="modal-overlay" id="edit-picture-modal">
  <div class="modal-box" style="max-width:400px">
    <form method="POST" action="{{ route('student.progress.milestones.update-picture') }}" enctype="multipart/form-data" id="edit-picture-form">
      @csrf
      <input type="hidden" name="milestone_id" id="edit-milestone-id" value="" />
      <div style="margin-bottom:20px">
        <div style="font-size:16px;font-weight:600;color:var(--text);margin-bottom:2px">Replace Picture</div>
        <div style="font-size:12px;color:var(--text3)">Upload a new image to update your milestone submission.</div>
      </div>
      <div class="form-group" style="margin-bottom:16px">
        <label class="label">New Picture</label>
        <div style="display:flex;align-items:center;gap:10px;margin-top:6px">
          <label for="edit-picture-input" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;border:1.5px dashed var(--border);background:var(--bg2);color:var(--text2);font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;transition:border-color 0.15s" onmouseenter="this.style.borderColor='var(--accent)'" onmouseleave="this.style.borderColor=''" id="edit-picture-label">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            Choose Image
          </label>
          <input type="file" name="file" accept="image/*" required id="edit-picture-input" style="display:none" onchange="document.getElementById('edit-file-name').textContent = this.files[0] ? this.files[0].name : 'No file chosen'" />
          <span id="edit-file-name" style="font-size:12px;color:var(--text3)">No file chosen</span>
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:6px">Max 5MB, image only</div>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" style="flex:1;display:inline-flex;align-items:center;justify-content:center;gap:6px;font-size:13px;padding:9px 16px;border-radius:8px;font-weight:600;cursor:pointer;border:none;background:var(--text);color:var(--navy-text);font-family:inherit;transition:opacity 0.15s" onmouseenter="this.style.opacity=0.85" onmouseleave="this.style.opacity=1">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Update
        </button>
        <button type="button" style="display:inline-flex;align-items:center;justify-content:center;gap:6px;font-size:13px;padding:9px 16px;border-radius:8px;font-weight:500;cursor:pointer;border:1.5px solid var(--border);background:transparent;color:var(--text2);font-family:inherit;transition:background 0.15s" onmouseenter="this.style.background='var(--bg2)'" onmouseleave="this.style.background='transparent'" onclick="document.getElementById('edit-picture-modal').classList.remove('open')">Cancel</button>
      </div>
    </form>
  </div>
</div>

{{-- Image Preview Modal --}}
<div class="img-modal-overlay" id="img-modal" onclick="closeImageModal()">
  <div class="img-modal-box" onclick="event.stopPropagation()">
    <button class="img-modal-close" onclick="closeImageModal()">&times;</button>
    <img id="img-modal-src" src="" alt="Submission image" />
  </div>
</div>

<style>
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center;z-index:1000;opacity:0;pointer-events:none;transition:opacity 0.2s}
.modal-overlay.open{opacity:1;pointer-events:auto}
.modal-box{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:28px;max-width:460px;width:90%;box-shadow:0 4px 24px rgba(0,0,0,0.1)}
.form-group{margin-bottom:12px}
.form-group .label{display:block;font-size:12px;font-weight:600;color:var(--text2);margin-bottom:4px}
.img-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:1100;opacity:0;pointer-events:none;transition:opacity 0.2s;cursor:zoom-out}
.img-modal-overlay.open{opacity:1;pointer-events:auto}
.img-modal-box{position:relative;max-width:90vw;max-height:90vh;display:flex;align-items:center;justify-content:center}
.img-modal-box img{max-width:100%;max-height:85vh;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,0.3);display:block}
.img-modal-close{position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.5);border:none;color:#fff;font-size:20px;cursor:pointer;padding:4px 10px;line-height:1;border-radius:6px;opacity:0.8;transition:opacity 0.15s;z-index:1}
.img-modal-close:hover{opacity:1;background:rgba(0,0,0,0.7)}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var sk = document.getElementById('skeleton-content');
  var rc = document.getElementById('real-content');
  if (sk && rc) {
    requestAnimationFrame(function () {
      setTimeout(function () {
        sk.style.transition = 'opacity 0.3s ease';
        sk.style.opacity = '0';
        rc.style.display = 'block';
        rc.style.transition = 'opacity 0.35s ease';
        setTimeout(function () {
          sk.remove();
          rc.style.transition = '';
        }, 350);
      }, 600);
    });
  }
});

function openUploadModal(milestoneId, milestoneName) {
  document.getElementById('upload-milestone-id').value = milestoneId;
  document.getElementById('upload-milestone-name').textContent = milestoneName;
  document.getElementById('review-milestone-name').textContent = milestoneName;
  var label = document.getElementById('upload-file-label');
  var tutorial = document.getElementById('upload-tutorial');
  if (milestoneName === 'Capstone Team') {
    label.textContent = 'Upload screenshot of Team Card';
    tutorial.textContent = 'Screenshot of your team card (verified)';
  } else {
    label.textContent = 'Upload picture';
    tutorial.textContent = 'Take a picture with your team after ' + milestoneName;
  }
  document.getElementById('upload-file-input').value = '';
  document.getElementById('upload-step-select').style.display = '';
  document.getElementById('upload-step-review').style.display = 'none';
  document.getElementById('upload-modal').classList.add('open');
}

function closeUploadModal() {
  document.getElementById('upload-modal').classList.remove('open');
}

function onFileSelected() {
  var input = document.getElementById('upload-file-input');
  if (!input.files || !input.files[0]) return;
  var file = input.files[0];
  document.getElementById('review-filename').textContent = file.name;
  var reader = new FileReader();
  reader.onload = function(e) {
    document.getElementById('review-preview').src = e.target.result;
  };
  reader.readAsDataURL(file);
  document.getElementById('upload-step-select').style.display = 'none';
  document.getElementById('upload-step-review').style.display = '';
}

function backToSelect() {
  document.getElementById('upload-step-select').style.display = '';
  document.getElementById('upload-step-review').style.display = 'none';
}

function openImageModal(src) {
  document.getElementById('img-modal-src').src = src;
  document.getElementById('img-modal').classList.add('open');
}

function closeImageModal() {
  document.getElementById('img-modal').classList.remove('open');
}

function openEditPictureModal(milestoneId) {
  document.getElementById('edit-milestone-id').value = milestoneId;
  document.getElementById('edit-picture-modal').classList.add('open');
}
</script>

</body>
</html>