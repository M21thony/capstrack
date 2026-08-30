<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Announcements - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<style>
.target-group { display:flex;flex-wrap:wrap;gap:8px;margin-top:6px; }
.target-opt { display:flex;align-items:center;gap:6px;padding:6px 14px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer;transition:all .15s ease;font-size:13px;font-weight:500;color:var(--text2);background:transparent;user-select:none; }
.target-opt:hover { border-color:var(--accent);color:var(--accent); }
.target-opt.active { border-color:var(--accent);background:rgba(79,70,229,0.06);color:var(--accent);font-weight:600; }
.target-opt input { display:none; }
.target-opt.disabled { opacity:0.4;cursor:not-allowed;pointer-events:none; }

/* @mention */
.mention-wrapper { position:relative; }
.mention-dropdown { position:absolute;bottom:100%;left:0;right:0;background:#fff;border:1px solid var(--border);border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,0.12);max-height:180px;overflow-y:auto;z-index:100;display:none;margin-bottom:4px; }
.mention-dropdown.open { display:block; }
.mention-item { padding:8px 12px;font-size:13px;font-weight:500;color:var(--text2);cursor:pointer;border-bottom:1px solid #f0f0f0;transition:background .1s; }
.mention-item:last-child { border-bottom:none;border-radius:0 0 10px 10px; }
.mention-item:first-child { border-radius:10px 10px 0 0; }
.mention-item:hover,.mention-item.active { background:rgba(79,70,229,0.06);color:var(--text); }
.mention-item .m-sub { font-size:11px;color:var(--text3);font-weight:400;margin-left:6px; }

.audience-badge { display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.3px; }
.audience-badge.all { background:rgba(99,102,241,0.1);color:#6366f1; }
.audience-badge.student { background:rgba(16,185,129,0.1);color:#059669; }
.audience-badge.expert { background:rgba(245,158,11,0.1);color:#d97706; }
.audience-badge.panel { background:rgba(147,51,234,0.1);color:#7c3aed; }
.team-mention-badge { display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:600;background:rgba(59,130,246,0.1);color:#2563eb; }

/* Simple modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center;z-index:1000;opacity:0;pointer-events:none;transition:opacity .2s}
.modal-overlay.open{opacity:1;pointer-events:auto}
.modal-box{background:#fff;border-radius:12px;padding:24px;max-width:360px;width:90%}
.modal-title{font-size:15px;font-weight:700;color:#101828;margin-bottom:6px}
.modal-desc{font-size:13px;color:#667085;margin-bottom:20px;line-height:1.5}
.modal-actions{display:flex;gap:8px;justify-content:flex-end}
.modal-btn{padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;border:1.5px solid transparent;cursor:pointer;transition:all .15s}
.modal-btn-cancel{background:#fff;border-color:#d0d5dd;color:#344054}
.modal-btn-cancel:hover{background:#f9fafb}
.modal-btn-danger{background:#d92d20;color:#fff}
.modal-btn-danger:hover{background:#b42318}
/* Tabs */
.tab-bar{display:flex;gap:4px;margin-left:12px}
.tab-btn{padding:4px 14px;font-size:11px;font-weight:600;border:none;border-radius:6px;cursor:pointer;background:transparent;color:var(--text3);transition:all .15s}
.tab-btn:hover{background:rgba(79,70,229,0.06);color:var(--accent)}
.tab-btn.active{background:rgba(79,70,229,0.1);color:var(--accent)}
.tab-content{display:none}
.tab-content.active{display:block}
.card.inactive{opacity:0.55;border-color:var(--border2)}.card.inactive:hover{opacity:0.75}
.badge-gray{background:var(--bg4);color:var(--text3)}
</style>
</head>
<body>

@include('capsa._sidebar')
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Announcements</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="ann-post-feed-grid" style="gap:16px;align-items:start">
      <div class="card">
        <div class="sk" style="width:120px;height:16px;border-radius:4px;margin-bottom:16px"></div>
        <div class="sk" style="height:38px;border-radius:8px;margin-bottom:10px"></div>
        <div class="sk" style="height:120px;border-radius:8px;margin-bottom:10px"></div>
        <div class="sk" style="height:38px;border-radius:8px"></div>
      </div>
      <div class="card">
        <div class="sk" style="width:140px;height:16px;border-radius:4px;margin-bottom:16px"></div>
        <div class="sk" style="height:72px;border-radius:10px;margin-bottom:10px"></div>
        <div class="sk" style="height:72px;border-radius:10px;margin-bottom:10px"></div>
        <div class="sk" style="height:72px;border-radius:10px"></div>
      </div>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content">
    <div class="ann-post-feed-grid" style="gap:16px;align-items:start">
      <div style="display:flex;flex-direction:column;gap:14px">
        <div class="card" id="announcement-form-card">
          <div class="card-header">
            <span class="card-title">New Post</span>
            <div class="tab-bar">
              <button class="tab-btn active" data-tab="announcement" onclick="switchTab('announcement')">Announcement</button>
              <button class="tab-btn" data-tab="milestone" onclick="switchTab('milestone')">Milestone</button>
            </div>
            <button onclick="resetForm()" style="background:none;border:none;font-size:11px;color:var(--text3);cursor:pointer">Clear</button>
          </div>

          <div id="tab-announcement" class="tab-content active">
            <div class="form-group">
              <label class="label">Title</label>
              <input class="input" id="ann-title" placeholder="e.g. Schedule for Title Defense" />
            </div>
            <div class="form-group">
              <label class="label">Message</label>
              <div class="mention-wrapper">
                <textarea class="input" id="ann-message" style="min-height:220px" placeholder="Type your announcement here... Type @ to mention a team"></textarea>
                <div class="mention-dropdown" id="mentionDropdown"></div>
              </div>
            </div>
          </div>

          <div id="tab-milestone" class="tab-content">
            <div class="form-group">
              <label class="label">Milestone</label>
              <select class="input" id="ms-select" onchange="onMilestoneSelect()">
                <option value="">-- Select Milestone --</option>
                @foreach($milestones as $ms)
                <option value="{{ $ms['title'] }}" data-desc="{{ $ms['description'] }}" data-due="{{ $ms['due_date_raw'] }}">
                  {{ $ms['title'] }}
                </option>
                @endforeach
              </select>
            </div>
            <div class="form-group">
              <label class="label">Due Date</label>
              <input class="input" id="ms-due" type="date" />
            </div>
            <div class="form-group">
              <label class="label">Message</label>
              <textarea class="input" id="ms-message" style="min-height:220px" placeholder="Auto-filled from milestone description"></textarea>
            </div>
          </div>

          <div class="form-group" id="priorityGroup">
            <label class="label">Priority</label>
            <select class="input" id="ann-priority">
              <option value="normal">Normal</option>
              <option value="important">Important</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
          <div class="form-group">
            <label class="label">Send To</label>
            <div class="target-group" id="targetGroup">
              <label class="target-opt" data-value="all">
                <input type="checkbox" value="all" /> All
              </label>
              <label class="target-opt" data-value="panel">
                <input type="checkbox" value="panel" /> Panel
              </label>
              <label class="target-opt" data-value="expert">
                <input type="checkbox" value="expert" /> Expert
              </label>
              <label class="target-opt" data-value="student" id="studentOpt">
                <input type="checkbox" value="student" /> Student
              </label>
            </div>
            <div id="sectionGroup" style="display:none;margin-top:8px;padding-left:8px;border-left:2px solid var(--border);">
              <div style="font-size:11px;color:var(--text3);margin-bottom:6px;font-weight:600;">Sections</div>
              <div class="target-group" id="sectionCheckboxes"></div>
            </div>
          </div>
          <div style="display:flex;gap:8px;margin-top:16px">
            <button class="btn" style="flex:1;justify-content:center;font-size:13px;background:var(--text);color:var(--navy-text);border:none" onclick="savePost()">
              <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              <span id="ann-save-label">Post Announcement</span>
            </button>
            <button class="btn btn-ghost" style="font-size:13px" id="ann-cancel-btn" onclick="resetForm()">Cancel</button>
          </div>
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:12px;height:calc(100vh - 120px);overflow-y:auto">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-shrink:0;gap:8px">
          <span style="font-size:12px;color:var(--text3)">
            @php $activeCount = $announcements->where('is_active', true)->count(); @endphp
            {{ $activeCount }} active @if(!request()->query('show_all'))announcements @else(of {{ $announcements->count() }}) @endif
          </span>
          <a href="{{ request()->fullUrlWithQuery(['show_all' => request()->query('show_all') === '1' ? null : '1']) }}" class="btn btn-ghost" style="font-size:10px;padding:3px 10px">
            {{ request()->query('show_all') === '1' ? 'Show Active Only' : 'Show All' }}
          </a>
        </div>

        <div id="announcements-list" style="display:flex;flex-direction:column;gap:12px">
        @forelse($announcements as $announcement)
          @php $annMs = $announcement->milestone; @endphp
          <div class="card announcement-card {{ $announcement->priority }} {{ $announcement->is_active ? '' : 'inactive' }}" data-id="{{ $announcement->id }}"
            @if($annMs)
            data-ms-title="{{ $annMs->title }}" data-ms-due="{{ $annMs->due_date?->format('Y-m-d') }}" data-ms-id="{{ $annMs->id }}"
            @endif
          >
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:8px" @if($annMs) onclick="openReviewModal(this)" style="cursor:pointer" @endif>
              <div style="flex:1">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;flex-wrap:wrap">
                  @if ($annMs)
                  <span class="badge" style="font-size:10px;background:rgba(139,92,246,0.15);color:#7c3aed">Milestone</span>
                  @else
                  <span class="badge {{ $announcement->priority === 'urgent' ? 'badge-red' : ($announcement->priority === 'important' ? 'badge-amber' : 'badge-gray') }}" style="font-size:10px">{{ ucfirst($announcement->priority) }}</span>
                  @endif
                  @if (!$announcement->is_active)
                    <span class="badge" style="font-size:10px;background:var(--bg4);color:var(--text3)">Inactive</span>
                  @endif
                  @if ($announcement->target_audience)
                    @foreach ((array) $announcement->target_audience as $ta)
                      @if (str_starts_with($ta, 'section:'))
                        <span class="audience-badge section-badge" data-value="{{ $ta }}" style="background:rgba(16,185,129,0.1);color:#059669;">{{ substr($ta, 8) }}</span>
                      @else
                        <span class="audience-badge {{ $ta }}" data-value="{{ $ta }}">{{ $ta }}</span>
                      @endif
                    @endforeach
                  @endif
                  @if ($announcement->team)
                    <span class="team-mention-badge">@ {{ $announcement->team->team_name }}</span>
                  @endif
                </div>
                <div class="ann-title" style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:4px">{{ $announcement->title }}</div>
                @if ($annMs && $annMs->due_date)
                  <div style="font-size:12px;color:var(--amber);font-weight:500;margin-bottom:4px">
                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="vertical-align:text-bottom;margin-right:2px"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Due {{ $annMs->due_date->format('M j, Y') }}
                  </div>
                @endif
                <div class="ann-body" style="font-size:12px;color:var(--text2);line-height:1.5">{{ $announcement->body }}</div>
              </div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;padding-top:10px;border-top:1px solid var(--border)">
              <div style="font-size:11px;color:var(--text3)">Posted {{ $announcement->created_at->format('M j, Y') }}</div>
              <div style="display:flex;gap:6px">
                <button class="btn btn-ghost" style="font-size:10px;padding:4px 10px" onclick="editAnnouncement({{ $announcement->id }})">Edit</button>
                <button class="btn btn-danger" style="font-size:10px;padding:4px 10px" onclick="deleteAnnouncement({{ $announcement->id }})">Delete</button>
              </div>
            </div>
          </div>
        @empty
          <div style="text-align:center;padding:40px;color:var(--text3);font-size:13px">No announcements yet.</div>
        @endforelse
        </div>
      </div>
    </div>
  </div>
  </div>
</div>
@include('capsa.partials.skeleton-toggle')

{{-- Delete confirmation modal --}}
<div class="modal-overlay" id="confirm-modal" onclick="if(event.target===this)closeConfirmModal()">
  <div class="modal-box">
    <div class="modal-title">Delete announcement?</div>
    <div class="modal-desc">This action cannot be undone.</div>
    <div class="modal-actions">
      <button class="modal-btn modal-btn-cancel" onclick="closeConfirmModal()">Cancel</button>
      <button class="modal-btn modal-btn-danger" id="confirm-delete-btn" onclick="confirmDelete()">Delete</button>
    </div>
  </div>
</div>

{{-- Milestone Review Modal --}}
<div class="modal-overlay" id="review-modal" onclick="if(event.target===this)closeReviewModal()">
  <div class="modal-box" style="max-width:560px;max-height:80vh;overflow-y:auto">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px">
      <div>
        <div style="font-size:15px;font-weight:600;color:var(--text);margin-bottom:2px" id="review-post-title"></div>
        <div style="font-size:11px;color:var(--text3)" id="review-post-meta"></div>
      </div>
      <button onclick="closeReviewModal()" style="background:none;border:none;font-size:18px;cursor:pointer;color:var(--text3);padding:0;line-height:1">&times;</button>
    </div>
    <div style="font-size:12px;color:var(--text2);line-height:1.5;white-space:pre-wrap;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--border)" id="review-post-body"></div>
    <div id="review-submissions" style="display:flex;flex-direction:column;gap:14px"></div>
  </div>
</div>

<script>
var teamsList = @json($teams);
var milestonesList = @json($milestones);
var adminSections = @json($adminSections);
var milestoneSubmissions = @json($milestoneSubmissions);
var editingId = null;
var deleteId = null;

// ---- Target Audience ----
function renderSectionCheckboxes() {
  var container = document.getElementById('sectionCheckboxes');
  container.innerHTML = '';
  if (!adminSections || adminSections.length === 0) return;
  var allLabel = document.createElement('label');
  allLabel.className = 'target-opt section-opt';
  allLabel.dataset.value = 'section:all';
  var allCb = document.createElement('input');
  allCb.type = 'checkbox';
  allCb.value = 'section:all';
  allLabel.appendChild(allCb);
  allLabel.appendChild(document.createTextNode(' All Sections'));
  container.appendChild(allLabel);
  adminSections.forEach(function(section) {
    var label = document.createElement('label');
    label.className = 'target-opt section-opt';
    label.dataset.value = 'section:' + section;
    var cb = document.createElement('input');
    cb.type = 'checkbox';
    cb.value = 'section:' + section;
    label.appendChild(cb);
    label.appendChild(document.createTextNode(' ' + section));
    container.appendChild(label);
  });
}

function toggleSectionGroup() {
  var studentCb = document.querySelector('#studentOpt input');
  var sectionGroup = document.getElementById('sectionGroup');
  if (studentCb.checked && adminSections && adminSections.length > 0) {
    sectionGroup.style.display = 'block';
  } else {
    sectionGroup.style.display = 'none';
    document.querySelectorAll('.section-opt input').forEach(function(cb) { cb.checked = false; cb.closest('.target-opt').classList.remove('active'); });
    var allSectionCb = document.querySelector('.section-opt[data-value="section:all"] input');
    if (allSectionCb) { allSectionCb.checked = false; allSectionCb.closest('.target-opt').classList.remove('active'); }
  }
}

renderSectionCheckboxes();

document.querySelectorAll('.target-opt input').forEach(function(cb) {
  cb.addEventListener('change', function() {
    if (this.value === 'all') {
      if (this.checked) {
        document.querySelectorAll('.target-opt input').forEach(function(other) {
          if (other.value !== 'all') {
            other.checked = false;
            other.closest('.target-opt').classList.remove('active');
          }
        });
        toggleSectionGroup();
      }
    } else {
      var allCb = document.querySelector('.target-opt[data-value="all"] input');
      if (allCb) {
        allCb.checked = false;
        allCb.closest('.target-opt').classList.remove('active');
      }
    }
    this.closest('.target-opt').classList.toggle('active', this.checked);
    if (this.value === 'student') toggleSectionGroup();
    var individualValues = ['panel', 'expert', 'student'];
    var allIndividualChecked = individualValues.every(function(v) {
      var cb = document.querySelector('.target-opt[data-value="' + v + '"] input');
      return cb && cb.checked;
    });
    if (allIndividualChecked) {
      individualValues.forEach(function(v) {
        var cb = document.querySelector('.target-opt[data-value="' + v + '"] input');
        if (cb) { cb.checked = false; cb.closest('.target-opt').classList.remove('active'); }
      });
      var allCb = document.querySelector('.target-opt[data-value="all"] input');
      if (allCb) { allCb.checked = true; allCb.closest('.target-opt').classList.add('active'); }
      toggleSectionGroup();
    }
  });
});

document.getElementById('sectionCheckboxes').addEventListener('change', function(e) {
  var cb = e.target;
  if (cb.tagName !== 'INPUT' || cb.type !== 'checkbox') return;
  cb.closest('.target-opt').classList.toggle('active', cb.checked);
  if (cb.value === 'section:all') {
    if (cb.checked) {
      document.querySelectorAll('.section-opt input').forEach(function(other) {
        if (other.value !== 'section:all') {
          other.checked = true;
          other.closest('.target-opt').classList.add('active');
        }
      });
    }
  } else {
    var allCb = document.querySelector('.section-opt[data-value="section:all"] input');
    if (allCb) {
      allCb.checked = false;
      allCb.closest('.target-opt').classList.remove('active');
    }
    var sectionOpts = document.querySelectorAll('.section-opt:not([data-value="section:all"]) input');
    var allChecked = Array.from(sectionOpts).every(function(input) { return input.checked; });
    if (allChecked && sectionOpts.length > 0) {
      if (allCb) {
        allCb.checked = true;
        allCb.closest('.target-opt').classList.add('active');
      }
    }
  }
});

function getTargetAudience() {
  var values = [];
  document.querySelectorAll('.target-opt input:checked').forEach(function(cb) {
    values.push(cb.value);
  });
  document.querySelectorAll('.section-opt input:checked').forEach(function(cb) {
    if (cb.value === 'section:all') return;
    if (values.indexOf(cb.value) === -1) values.push(cb.value);
  });
  return values;
}

function setTargetAudience(arr) {
  document.querySelectorAll('.target-opt input').forEach(function(cb) {
    var checked = arr.indexOf(cb.value) !== -1;
    cb.checked = checked;
    cb.closest('.target-opt').classList.toggle('active', checked);
  });
  document.querySelectorAll('.section-opt input').forEach(function(cb) {
    var checked = arr.indexOf(cb.value) !== -1;
    cb.checked = checked;
    cb.closest('.target-opt').classList.toggle('active', checked);
  });
  var sectionOpts = document.querySelectorAll('.section-opt:not([data-value="section:all"]) input');
  var allSectionsChecked = Array.from(sectionOpts).every(function(input) { return input.checked; });
  var allSectionCb = document.querySelector('.section-opt[data-value="section:all"] input');
  if (allSectionsChecked && sectionOpts.length > 0 && allSectionCb) {
    allSectionCb.checked = true;
    allSectionCb.closest('.target-opt').classList.add('active');
  }
  var studentChecked = arr.indexOf('student') !== -1;
  var sectionGroup = document.getElementById('sectionGroup');
  if (studentChecked && adminSections && adminSections.length > 0) {
    sectionGroup.style.display = 'block';
  } else {
    sectionGroup.style.display = 'none';
  }
}

// ---- @mention ----
var mentionTextarea = document.getElementById('ann-message');
var mentionDropdown = document.getElementById('mentionDropdown');
var mentionActiveIdx = -1;

mentionTextarea.addEventListener('input', function() {
  var pos = this.selectionStart;
  var text = this.value;
  var before = text.substring(0, pos);
  var atIdx = before.lastIndexOf('@');
  if (atIdx === -1 || (atIdx > 0 && before[atIdx - 1].match(/\w/))) {
    closeMention();
    return;
  }
  var query = before.substring(atIdx + 1).toLowerCase();
  if (query.indexOf(' ') !== -1 && before[atIdx + 1] !== ' ') {
    closeMention();
    return;
  }
  var filtered = teamsList.filter(function(t) { return t.team_name.toLowerCase().indexOf(query) !== -1; });
  if (filtered.length === 0) { closeMention(); return; }
  showMention(filtered, atIdx);
});

mentionTextarea.addEventListener('keydown', function(e) {
  if (!mentionDropdown.classList.contains('open')) return;
  if (e.key === 'ArrowDown') { e.preventDefault(); moveMention(1); }
  else if (e.key === 'ArrowUp') { e.preventDefault(); moveMention(-1); }
  else if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); selectMention(); }
  else if (e.key === 'Escape') { closeMention(); }
});

function showMention(list, atIdx) {
  mentionDropdown.innerHTML = '';
  list.forEach(function(t, i) {
    var div = document.createElement('div');
    div.className = 'mention-item' + (i === 0 ? ' active' : '');
    div.innerHTML = '<span>@' + t.team_name + '</span>';
    div.dataset.id = t.team_id;
    div.dataset.name = t.team_name;
    div.dataset.idx = i;
    div.addEventListener('click', function() { mentionActiveIdx = parseInt(this.dataset.idx); selectMention(); });
    div.addEventListener('mouseenter', function() {
      document.querySelectorAll('.mention-item').forEach(function(el) { el.classList.remove('active'); });
      this.classList.add('active');
      mentionActiveIdx = parseInt(this.dataset.idx);
    });
    mentionDropdown.appendChild(div);
  });
  mentionDropdown.classList.add('open');
  mentionActiveIdx = 0;
}

function moveMention(dir) {
  var items = mentionDropdown.querySelectorAll('.mention-item');
  if (items.length === 0) return;
  items[mentionActiveIdx]?.classList.remove('active');
  mentionActiveIdx = (mentionActiveIdx + dir + items.length) % items.length;
  items[mentionActiveIdx].classList.add('active');
}

function selectMention() {
  var active = mentionDropdown.querySelector('.mention-item.active');
  if (!active) return;
  var name = active.dataset.name;
  var pos = mentionTextarea.selectionStart;
  var text = mentionTextarea.value;
  var before = text.substring(0, pos);
  var after = text.substring(pos);
  var atIdx = before.lastIndexOf('@');
  var beforeAt = before.substring(0, atIdx);
  var insertion = '@' + name + ' ';
  mentionTextarea.value = beforeAt + insertion + after;
  var newPos = beforeAt.length + insertion.length;
  mentionTextarea.selectionStart = mentionTextarea.selectionEnd = newPos;
  closeMention();
  mentionTextarea.focus();
}

function closeMention() {
  mentionDropdown.classList.remove('open');
  mentionDropdown.innerHTML = '';
  mentionActiveIdx = -1;
}

// Close mention on blur
mentionTextarea.addEventListener('blur', function() {
  setTimeout(closeMention, 200);
});

// ---- Tab Switching ----
var currentTab = 'announcement';

function switchTab(tab) {
  editingId = null;
  currentTab = tab;
  document.querySelectorAll('.tab-btn').forEach(function(btn) {
    btn.classList.toggle('active', btn.dataset.tab === tab);
  });
  document.querySelectorAll('.tab-content').forEach(function(el) {
    el.classList.toggle('active', el.id === 'tab-' + tab);
  });
  var label = document.getElementById('ann-save-label');
  label.textContent = tab === 'announcement' ? 'Post Announcement' : 'Post Milestone';

  var isMilestone = tab === 'milestone';
  document.getElementById('priorityGroup').style.display = isMilestone ? 'none' : '';
  var disableAudience = isMilestone;
  ['all', 'panel', 'expert'].forEach(function(v) {
    var opt = document.querySelector('.target-opt[data-value="' + v + '"]');
    if (!opt) return;
    opt.classList.toggle('disabled', disableAudience);
    var cb = opt.querySelector('input');
    if (cb) cb.disabled = disableAudience;
  });

  if (tab === 'milestone') {
    setTargetAudience(['student']);
    document.getElementById('ms-select').value = '';
    document.getElementById('ms-due').value = '';
    document.getElementById('ms-message').value = '';
  } else {
    document.getElementById('ann-title').value = '';
    document.getElementById('ann-message').value = '';
    document.getElementById('ann-priority').value = 'normal';
    setTargetAudience(['all']);
  }
}

// ---- Milestone Tab ----
function onMilestoneSelect() {
  var sel = document.getElementById('ms-select');
  var opt = sel.options[sel.selectedIndex];
  if (!opt || !opt.value) {
    document.getElementById('ms-due').value = '';
    document.getElementById('ms-message').value = '';
    return;
  }
  document.getElementById('ms-message').value = opt.dataset.desc || '';
  document.getElementById('ms-due').value = opt.dataset.due || '';
}

// ---- Form ----
function resetForm() {
  document.getElementById('ann-title').value = '';
  document.getElementById('ann-message').value = '';
  document.getElementById('ms-select').value = '';
  document.getElementById('ms-due').value = '';
  document.getElementById('ms-message').value = '';
  document.getElementById('ann-priority').value = 'normal';
  setTargetAudience(['all']);
  document.getElementById('ann-save-label').textContent = 'Post Announcement';
  document.getElementById('ann-cancel-btn').style.display = 'none';
  editingId = null;
  switchTab('announcement');
}

function savePost() {
  var priority = document.getElementById('ann-priority').value;
  var targetAudience = getTargetAudience();
  var title, body;

  var milestoneTitle, milestoneDueDate;

  if (currentTab === 'announcement') {
    title = document.getElementById('ann-title').value.trim();
    body = document.getElementById('ann-message').value.trim();
  } else {
    var sel = document.getElementById('ms-select');
    var opt = sel.options[sel.selectedIndex];
    milestoneTitle = opt && opt.value ? opt.value : '';
    title = milestoneTitle ? 'Milestone Reminder: ' + milestoneTitle : '';
    body = document.getElementById('ms-message').value.trim();
    milestoneDueDate = document.getElementById('ms-due').value || null;
  }

  if (!title) { return; }
  if (!body) { return; }
  if (targetAudience.length === 0) { alert('Please select at least one target audience.'); return; }

  // Extract @mentions from body to determine team_id
  var teamId = null;
  var mentionMatch = body.match(/@(\w[\w\s]*\w)/);
  if (mentionMatch) {
    var mentionedName = mentionMatch[1].trim().toLowerCase();
    var found = teamsList.find(function(t) { return t.team_name.toLowerCase() === mentionedName; });
    if (found) teamId = found.team_id;
  }

  var url = editingId ? '/api/admin/announcements/' + editingId : '/api/admin/announcements';
  var method = editingId ? 'PUT' : 'POST';

  var payload = { title: title, body: body, priority: priority, target_audience: targetAudience, team_id: teamId };
  if (milestoneTitle) {
    payload.milestone_title = milestoneTitle;
    payload.milestone_due_date = milestoneDueDate;
  }

  fetch(url, {
    method: method,
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify(payload),
  })
    .then(function(r) { return r.json(); })
    .then(function() { location.reload(); })
    .catch(function() { alert('Failed to save post.'); });
}

function editAnnouncement(id) {
  var card = document.querySelector('.announcement-card[data-id="' + id + '"]');
  if (!card) return;

  var titleEl = card.querySelector('.ann-title');
  var bodyEl = card.querySelector('.ann-body');
  var badgeEl = card.querySelector('.badge');
  var audienceEls = card.querySelectorAll('.audience-badge');
  var teamMentionEl = card.querySelector('.team-mention-badge');

  var isMilestone = card.dataset.msTitle ? true : false;

  if (isMilestone) {
    switchTab('milestone');
    var sel = document.getElementById('ms-select');
    var msTitle = card.dataset.msTitle;
    for (var i = 0; i < sel.options.length; i++) {
      if (sel.options[i].value === msTitle) {
        sel.selectedIndex = i;
        break;
      }
    }
    onMilestoneSelect();
    document.getElementById('ms-due').value = card.dataset.msDue || '';
    document.getElementById('ms-message').value = bodyEl ? bodyEl.textContent : '';
  } else {
    switchTab('announcement');
    document.getElementById('ann-title').value = titleEl ? titleEl.textContent : '';
    document.getElementById('ann-message').value = bodyEl ? bodyEl.textContent : '';
    document.getElementById('ann-priority').value = badgeEl ? badgeEl.textContent.toLowerCase().trim() : 'normal';
  }

  var audiences = [];
  audienceEls.forEach(function(el) { audiences.push(el.dataset.value || el.textContent.trim().toLowerCase()); });
  if (audiences.length === 0) audiences = ['all'];
  setTargetAudience(audiences);

  var teamName = teamMentionEl ? teamMentionEl.textContent.trim().replace(/^@\s*/, '') : '';
  if (teamName && bodyEl) {
    var bodyText = bodyEl.textContent;
    if (bodyText.indexOf('@' + teamName) === -1) {
      document.getElementById(isMilestone ? 'ms-message' : 'ann-message').value = bodyText + ' @' + teamName;
    }
  }

  document.getElementById('ann-save-label').textContent = isMilestone ? 'Update Milestone' : 'Update Announcement';
  document.getElementById('ann-cancel-btn').style.display = 'inline-flex';
  editingId = id;
  (isMilestone ? document.getElementById('ms-select') : document.getElementById('ann-title')).scrollIntoView({ behavior: 'smooth' });
}

function deleteAnnouncement(id) {
  deleteId = id;
  document.getElementById('confirm-modal').classList.add('open');
}

function closeConfirmModal() {
  document.getElementById('confirm-modal').classList.remove('open');
  deleteId = null;
}

function confirmDelete() {
  if (!deleteId) return;
  var btn = document.getElementById('confirm-delete-btn');
  btn.textContent = 'Deleting...';
  btn.disabled = true;
  fetch('/api/admin/announcements/' + deleteId, {
    method: 'DELETE',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Accept': 'application/json',
    },
  })
    .then(function() { location.reload(); })
    .catch(function() { alert('Failed to delete.'); btn.textContent = 'Delete'; btn.disabled = false; });
}

// ---- Milestone Review Modal ----
function openReviewModal(el) {
  var card = el.closest('.announcement-card');
  if (!card) return;
  var msId = card.dataset.msId;
  var msTitle = card.dataset.msTitle;
  var due = card.dataset.msDue;
  var annId = card.dataset.id;
  var titleEl = card.querySelector('.ann-title');
  var bodyEl = card.querySelector('.ann-body');

  document.getElementById('review-post-title').textContent = msTitle || 'Milestone Post';
  document.getElementById('review-post-meta').textContent = (due ? 'Due ' + due : '');
  document.getElementById('review-post-body').textContent = bodyEl ? bodyEl.textContent.trim() : '';

  var container = document.getElementById('review-submissions');
  container.innerHTML = '';
  var subs = msId && milestoneSubmissions[msId] ? milestoneSubmissions[msId] : [];
  if (subs.length === 0) {
    container.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text3);font-size:12px">No submissions yet.</div>';
  } else {
    subs.forEach(function(s) {
      var div = document.createElement('div');
      div.style.cssText = 'padding:10px 0;border-bottom:1px solid var(--border)';
      if (subs.indexOf(s) === subs.length - 1) div.style.borderBottom = 'none';
      var avatarHtml = s.student && s.student.avatar
        ? '<img src="' + s.student.avatar + '" style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0" />'
        : '<div style="width:32px;height:32px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#18191f;flex-shrink:0">' + (s.student ? s.student.initials : '?') + '</div>';
      var name = s.student ? s.student.f_name + ' ' + s.student.l_name : 'Unknown';
      var team = s.team_name || 'Unknown Team';
      var time = s.completed_at ? new Date(s.completed_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
      var verifiedBadge = s.is_verified
        ? '<span style="font-size:11px;font-weight:600;color:var(--green);white-space:nowrap">&#10003; Verified</span>'
        : '<span style="font-size:11px;font-weight:600;color:var(--amber);white-space:nowrap">Pending</span>';
      var verifyBtn = s.is_verified ? '' :
        '<button onclick="verifySubmission(' + annId + ', ' + s.submission_id + ', true)" style="font-size:11px;padding:5px 12px;border-radius:6px;border:none;background:var(--green);color:#fff;font-weight:600;cursor:pointer;font-family:inherit;white-space:nowrap;flex-shrink:0">Mark as Verified</button>';
      var imgHtml = s.file_url ? '<div style="margin-top:6px"><img src="' + s.file_url + '" style="max-width:200px;max-height:120px;object-fit:cover;border-radius:6px;border:1px solid var(--border);cursor:pointer" onclick="openSubmissionImage(\'' + s.file_url + '\')" /></div>' : '';
      div.innerHTML =
        '<div style="display:flex;align-items:center;gap:10px">' +
          avatarHtml +
          '<div style="flex:1;min-width:0;font-size:12px;color:var(--text);font-weight:500">' + name + ' <span style="font-weight:400;color:var(--text3)">&middot; ' + team + '</span>' + (time ? ' <span style="font-weight:400;color:var(--text3)">&middot; ' + time + '</span>' : '') + '</div>' +
          verifiedBadge +
          verifyBtn +
        '</div>' +
        imgHtml;
      container.appendChild(div);
    });
  }
  document.getElementById('review-modal').classList.add('open');
}

function verifySubmission(annId, submissionId, verified) {
  fetch('/api/admin/announcements/' + annId + '/verify-submission', {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ submission_id: submissionId, verified: verified }),
  })
    .then(function(r) { return r.json(); })
    .then(function() { location.reload(); })
    .catch(function() { alert('Failed to verify submission.'); });
}

function closeReviewModal() {
  document.getElementById('review-modal').classList.remove('open');
}

function openSubmissionImage(url) {
  var overlay = document.createElement('div');
  overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.85);display:flex;align-items:center;justify-content:center;cursor:pointer';
  overlay.onclick = function() { overlay.remove(); };
  var img = document.createElement('img');
  img.src = url;
  img.style.cssText = 'max-width:90vw;max-height:90vh;object-fit:contain;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,0.5)';
  var closeBtn = document.createElement('button');
  closeBtn.innerHTML = '&times;';
  closeBtn.style.cssText = 'position:absolute;top:16px;right:16px;background:rgba(0,0,0,0.5);color:#fff;border:none;border-radius:50%;width:36px;height:36px;font-size:22px;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;padding:0';
  closeBtn.onclick = function(e) { e.stopPropagation(); overlay.remove(); };
  var wrapper = document.createElement('div');
  wrapper.style.cssText = 'position:relative;display:inline-block';
  wrapper.appendChild(img);
  wrapper.appendChild(closeBtn);
  overlay.appendChild(wrapper);
  document.body.appendChild(overlay);
}

// Init
resetForm();


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
</body>
</html>
