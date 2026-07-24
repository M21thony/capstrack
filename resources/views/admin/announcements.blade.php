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
#announcements-list::-webkit-scrollbar{width:4px}
#announcements-list::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}
</style>
</head>
<body>

@include('admin._sidebar')
<div class="main">
  <div class="topbar">
    <div class="page-title">Announcements</div>
    <div class="topbar-actions">
      @include('admin.partials.notification-dropdown')
    </div>
  </div>

  <div class="content">
    <div style="display:grid;grid-template-columns:1fr 1.6fr;gap:16px;align-items:start">
      <div style="display:flex;flex-direction:column;gap:14px">
        <div class="card" id="announcement-form-card">
          <div class="card-header">
            <span class="card-title" id="ann-form-title">New Announcement</span>
            <button onclick="resetAnnForm()" style="background:none;border:none;font-size:11px;color:var(--text3);cursor:pointer">Clear</button>
          </div>
          <div class="form-group">
            <label class="label">Title</label>
            <input class="input" id="ann-title" placeholder="e.g. Schedule for Title Defense" />
          </div>
          <div class="form-group">
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
          <div class="form-group">
            <label class="label">Message</label>
            <div class="mention-wrapper">
              <textarea class="input" id="ann-message" style="min-height:220px" placeholder="Type your announcement here... Type @ to mention a team"></textarea>
              <div class="mention-dropdown" id="mentionDropdown"></div>
            </div>
          </div>
          <div style="display:flex;gap:8px;margin-top:16px">
            <button class="btn" style="flex:1;justify-content:center;font-size:13px;background:var(--text);color:var(--navy-text);border:none" onclick="saveAnnouncement()">
              <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              <span id="ann-save-label">Post Announcement</span>
            </button>
            <button class="btn btn-ghost" style="font-size:13px" id="ann-cancel-btn" onclick="resetAnnForm()">Cancel</button>
          </div>
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:12px;height:calc(100vh - 120px)">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-shrink:0">
          <span style="font-size:12px;color:var(--text3)">{{ $announcements->count() }} active announcements</span>
        </div>

        <div id="announcements-list" style="flex:1;min-height:0;overflow-y:auto;display:flex;flex-direction:column;gap:12px">
        @forelse($announcements as $announcement)
          <div class="card announcement-card {{ $announcement->priority }}" data-id="{{ $announcement->id }}">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:8px">
              <div style="flex:1">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;flex-wrap:wrap">
                  <span class="badge {{ $announcement->priority === 'urgent' ? 'badge-red' : ($announcement->priority === 'important' ? 'badge-amber' : 'badge-gray') }}" style="font-size:10px">{{ ucfirst($announcement->priority) }}</span>
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

<script>
var teamsList = @json($teams);
var adminSections = @json($adminSections);
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

// ---- Form ----
function resetAnnForm() {
  document.getElementById('ann-title').value = '';
  document.getElementById('ann-priority').value = 'normal';
  document.getElementById('ann-message').value = '';
  setTargetAudience(['all']);
  document.getElementById('ann-form-title').textContent = 'New Announcement';
  document.getElementById('ann-save-label').textContent = 'Post Announcement';
  document.getElementById('ann-cancel-btn').style.display = 'none';
  editingId = null;
}

function saveAnnouncement() {
  var title = document.getElementById('ann-title').value.trim();
  var body = document.getElementById('ann-message').value.trim();
  var priority = document.getElementById('ann-priority').value;
  var targetAudience = getTargetAudience();

  if (!title) { document.getElementById('ann-title').focus(); return; }
  if (!body) { document.getElementById('ann-message').focus(); return; }
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

  fetch(url, {
    method: method,
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ title: title, body: body, priority: priority, target_audience: targetAudience, team_id: teamId }),
  })
    .then(function(r) { return r.json(); })
    .then(function() { location.reload(); })
    .catch(function() { alert('Failed to save announcement.'); });
}

function editAnnouncement(id) {
  var card = document.querySelector('.announcement-card[data-id="' + id + '"]');
  if (!card) return;

  var titleEl = card.querySelector('.ann-title');
  var bodyEl = card.querySelector('.ann-body');
  var badgeEl = card.querySelector('.badge');
  var audienceEls = card.querySelectorAll('.audience-badge');
  var teamMentionEl = card.querySelector('.team-mention-badge');

  document.getElementById('ann-title').value = titleEl ? titleEl.textContent : '';
  document.getElementById('ann-message').value = bodyEl ? bodyEl.textContent : '';
  document.getElementById('ann-priority').value = badgeEl ? badgeEl.textContent.toLowerCase().trim() : 'normal';

  var audiences = [];
  audienceEls.forEach(function(el) { audiences.push(el.dataset.value || el.textContent.trim().toLowerCase()); });
  if (audiences.length === 0) audiences = ['all'];
  setTargetAudience(audiences);

  var teamName = teamMentionEl ? teamMentionEl.textContent.trim().replace(/^@\s*/, '') : '';
  if (teamName && bodyEl) {
    var bodyText = bodyEl.textContent;
    if (bodyText.indexOf('@' + teamName) === -1) {
      document.getElementById('ann-message').value = bodyText + ' @' + teamName;
    }
  }

  document.getElementById('ann-form-title').textContent = 'Edit Announcement';
  document.getElementById('ann-save-label').textContent = 'Update Announcement';
  document.getElementById('ann-cancel-btn').style.display = 'inline-flex';
  editingId = id;
  document.getElementById('ann-title').scrollIntoView({ behavior: 'smooth' });
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

// Init
setTargetAudience(['all']);
</script>
</body>
</html>
