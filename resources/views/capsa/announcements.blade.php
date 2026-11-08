<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Announcements - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
<style nonce="{{ Vite::cspNonce() }}">
/* The feed scrolls in its own box; the page itself never does. */
#real-content .content{display:flex;flex-direction:column;overflow:hidden}
.ann{--ink:#1e2128;--ink-2:#4a5060;--ink-3:#656b7a;--line:#e0e2e8;--line-2:#c8cad4;--nav-2:#1e2028;--gold:#c9a82c;--gold-ink:#7d630e;--gold-soft:#faf6e8;--red:#b91c1c;
  flex:1;min-height:0;width:100%;display:grid;grid-template-columns:minmax(0,1fr) 370px;gap:16px;font-size:13px;color:var(--ink)}
.ann [hidden]{display:none!important}
.ann-list{display:flex;flex-direction:column;gap:10px;min-height:0}
.ann-tools{display:flex;gap:8px;align-items:center;flex:none}
.ann-tools .ann-input{width:auto}
.ann-tools #ann-search{flex:1;min-width:0}
.ann-feed{overflow-y:auto;overflow-x:hidden;min-height:0;display:flex;flex-direction:column;gap:10px;padding-right:22px}
.ann-day{display:flex;flex-direction:column;gap:10px;flex:none}
.ann-dayh{flex:none;display:flex;align-items:baseline;gap:8px;padding:6px 0 4px;font-size:14px;font-weight:700;color:var(--ink);border-bottom:2px solid var(--ink)}
.ann-dayh span{font-size:12px;font-weight:500;color:var(--ink-3)}
.ann-card{background:#fff;border:1px solid var(--line);border-radius:12px}
.ann-post{flex:none;padding:13px 16px 12px}
.ann-post .ann-title{margin:3px 0 2px;font-size:15px;font-weight:700;color:var(--ink)}
.ann-meta{font-size:12px;color:var(--ink-3)}
.ann-meta strong{color:var(--ink-2);font-weight:600}
.ann-msg{margin-top:8px;white-space:pre-line;line-height:1.6;color:var(--ink-2);overflow-wrap:anywhere}
.ann-mention{background:var(--gold-soft);color:var(--gold-ink);font-weight:600;border-radius:4px;padding:0 2px}
.ann-acts{display:flex;gap:6px;justify-content:flex-end;margin-top:10px}
.ann-empty{padding:40px 0;text-align:center;color:var(--ink-3)}
.ann-btn{font:inherit;font-size:12.5px;font-weight:600;padding:7px 13px;border-radius:8px;border:1px solid var(--line);background:#fff;color:var(--ink-2);cursor:pointer}
.ann-btn:hover{border-color:var(--line-2);color:var(--ink)}
.ann-btn:disabled{opacity:.6;cursor:default}
.ann-btn.gold{background:var(--gold);border-color:var(--gold);color:#18191f}
.ann-btn.gold:hover{background:#b5941f;border-color:#b5941f}
.ann-btn.sm{padding:3px 10px;font-size:12px}
.ann-btn.del{color:var(--red)}
.ann-input{width:100%;font:inherit;font-size:13px;color:var(--ink);background:#fff;border:1px solid var(--line-2);border-radius:8px;padding:8px 10px;outline:none}
.ann-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,168,44,.12)}
textarea.ann-input{resize:vertical;min-height:150px;line-height:1.5}
.ann-select{position:relative;text-align:left;cursor:pointer;padding-right:28px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ann-select::after{content:'';position:absolute;right:12px;top:50%;width:6px;height:6px;margin-top:-5px;border-right:2px solid var(--ink-3);border-bottom:2px solid var(--ink-3);transform:rotate(45deg)}
.ann-writer{display:flex;flex-direction:column;position:relative;align-self:start}
.ann-wh{background:var(--nav-2);color:#fff;border-radius:11px 11px 0 0;padding:11px 16px;font-weight:600;font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ann-wb{padding:14px 16px;display:flex;flex-direction:column;gap:6px}
.ann-fl{font-size:12px;font-weight:600;color:var(--ink-2);margin-top:6px}
.ann-warn{font-size:12px;background:#fdf2d6;color:#92400e;border-radius:8px;padding:7px 10px;margin-top:4px}
.ann-note{font-size:12px;background:var(--gold-soft);color:var(--gold-ink);border-radius:8px;padding:7px 10px}
.ann-wf{padding:12px 16px;border-top:1px solid var(--line);display:flex;gap:8px;justify-content:flex-end}
.ann-mwrap,.ann-forwrap{position:relative}
.ann-drop,.ann-mdrop{position:absolute;left:0;right:0;background:#fff;border:1px solid var(--line);border-radius:10px;box-shadow:0 10px 28px rgba(0,0,0,.16);padding:6px;z-index:2147483001}
.ann-drop{top:calc(100% + 4px)}
.ann-drop.up{top:auto;bottom:calc(100% + 4px)}
.ann-dl{font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:var(--ink-3);font-weight:600;padding:8px 8px 3px}
.ann-op{display:block;width:100%;text-align:left;font:inherit;font-size:13px;color:var(--ink);background:none;border:0;padding:7px 8px;border-radius:6px;cursor:pointer}
.ann-op:hover{background:#f7f8fa}
.ann-op.on{background:var(--gold-soft);font-weight:600}
.ann-hr{height:1px;background:var(--line);margin:5px 4px}
.ann-ck{display:flex;align-items:center;gap:9px;font-size:12.5px;padding:6px 8px;border-radius:6px;cursor:pointer}
.ann-ck:hover{background:#f7f8fa}
.ann-ck input{accent-color:var(--gold)}
.ann-mdrop{bottom:calc(100% + 4px);max-height:180px;overflow-y:auto;display:none}
.ann-mdrop.open{display:block}
.mention-item{padding:7px 8px;border-radius:6px;font-weight:600;color:var(--ink-2);cursor:pointer}
.mention-item.active{background:var(--gold-soft);color:var(--gold-ink)}
@media (max-width:900px){
  #real-content .content{display:block;overflow-y:auto}
  .ann{grid-template-columns:minmax(0,1fr)}
  .ann-writer{order:-1}
  .ann-feed{overflow:visible;padding-right:0}
}

/* Delete confirmation */
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
</style>
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
    <div class="page-title">Announcements</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="ann-post-feed-grid" style="gap:16px;align-items:start;grid-template-columns:1fr 370px">
      <div class="card">
        <div class="sk" style="height:34px;border-radius:8px;margin-bottom:14px"></div>
        <div class="sk" style="height:96px;border-radius:12px;margin-bottom:10px"></div>
        <div class="sk" style="height:96px;border-radius:12px;margin-bottom:10px"></div>
        <div class="sk" style="height:96px;border-radius:12px"></div>
      </div>
      <div class="card">
        <div class="sk" style="width:140px;height:16px;border-radius:4px;margin-bottom:16px"></div>
        <div class="sk" style="height:38px;border-radius:8px;margin-bottom:10px"></div>
        <div class="sk" style="height:150px;border-radius:8px;margin-bottom:10px"></div>
        <div class="sk" style="height:38px;border-radius:8px"></div>
      </div>
    </div>
  </div>

  @php
    $me = auth('expert')->id();
    $adminSections = array_values((array) $adminSections);

    // Who a post went to, in the words the meta line uses.
    $sentTo = function ($a) {
        $aud = (array) $a->target_audience;
        $secs = array_values(array_map(fn ($v) => substr($v, 8), array_filter($aud, fn ($v) => str_starts_with($v, 'section:') && $v !== 'section:all')));
        $in = $secs ? ' in '.implode(', ', $secs) : '';
        $all = in_array('all', $aud, true);
        $stu = in_array('student', $aud, true);
        $exp = in_array('panel', $aud, true) || in_array('expert', $aud, true);
        $students = $a->team && ($all || $stu) ? '@'.$a->team->team_name : 'Students'.$in;

        if ($all || ($stu && $exp)) {
            return ($a->team || $secs) ? 'Experts and '.($a->team ? $students : 'students'.$in) : 'Everyone';
        }

        return $stu ? $students : ($exp ? 'Experts' : '');
    };

    $kinds = function ($a) use ($me) {
        $aud = (array) $a->target_audience;
        $k = [];
        if ($a->admin_id == $me) $k[] = 'mine';
        if (array_intersect(['all', 'student'], $aud)) $k[] = 'students';
        if (array_intersect(['all', 'panel', 'expert'], $aud)) $k[] = 'experts';
        if ($a->team_id) $k[] = 'team';
        return $k;
    };

    $count = fn ($kind) => $announcements->filter(fn ($a) => in_array($kind, $kinds($a), true))->count();
    $days = $announcements->groupBy(fn ($a) => $a->created_at->toDateString());
  @endphp

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content">
    <div class="ann">
      <div class="ann-list">
        <div class="ann-tools">
          <input class="ann-input" id="ann-search" type="search" placeholder="Search posts…" aria-label="Search posts">
          <select class="ann-input" id="ann-show" aria-label="Show">
            <option value="all">All posts ({{ $announcements->count() }})</option>
            <option value="mine">Posted by me ({{ $count('mine') }})</option>
            <optgroup label="Sent to">
              <option value="students">Students ({{ $count('students') }})</option>
              <option value="experts">Experts ({{ $count('experts') }})</option>
              <option value="team">One team (@mention) ({{ $count('team') }})</option>
            </optgroup>
          </select>
        </div>

        <div class="ann-feed" id="announcements-list">
          @forelse ($days as $date => $posts)
            @php
              $d = $posts->first()->created_at;
              $n = $posts->count().' '.\Illuminate\Support\Str::plural('post', $posts->count());
              $sameYear = $d->year === now()->year;
            @endphp
            <div class="ann-day">
              <div class="ann-dayh">
                @if ($d->isToday() || $d->isYesterday())
                  {{ $d->isToday() ? 'Today' : 'Yesterday' }} <span>{{ $d->format('D, M j') }} · {{ $n }}</span>
                @else
                  {{ $d->format($sameYear ? 'l, M j' : 'l, M j, Y') }} <span>{{ $n }}</span>
                @endif
              </div>
              @foreach ($posts as $announcement)
                @php
                  $aud = array_values((array) $announcement->target_audience);
                  $team = $announcement->team?->team_name;
                  $to = $sentTo($announcement);
                @endphp
                <article class="ann-card ann-post announcement-card" data-id="{{ $announcement->id }}" data-audience='@json($aud)' data-team="{{ $team }}" data-mine="{{ $announcement->admin_id == $me ? 1 : 0 }}" data-today="{{ $d->isToday() ? 1 : 0 }}" data-kinds="{{ implode(' ', $kinds($announcement)) }}">
                  <h3 class="ann-title">{{ $announcement->title }}</h3>
                  <div class="ann-meta">@if ($to)To <strong>{{ $to }}</strong> · @endif{{ $announcement->created_at->format('g:i A') }} · by {{ $announcement->admin?->name ?? 'CaPSA' }}</div>
                  <div class="ann-msg ann-body">@if ($team){!! str_replace(e('@'.$team), '<span class="ann-mention">'.e('@'.$team).'</span>', e($announcement->body)) !!}@else{{ $announcement->body }}@endif</div>
                  <div class="ann-acts">
                    <button type="button" class="ann-btn sm" onclick="editAnnouncement({{ $announcement->id }})">Edit</button>
                    <button type="button" class="ann-btn sm del" onclick="deleteAnnouncement({{ $announcement->id }})">Delete</button>
                  </div>
                </article>
              @endforeach
            </div>
          @empty
            <div class="ann-empty">No announcements yet.</div>
          @endforelse
          <div class="ann-empty" id="ann-nomatch" hidden>No posts match.</div>
        </div>
      </div>

      <section class="ann-card ann-writer" id="announcement-form-card">
        <div class="ann-wh" id="ann-writer-head">New announcement</div>
        <div class="ann-wb">
          <label class="ann-fl" for="ann-title">Title</label>
          <input class="ann-input" id="ann-title" maxlength="255" placeholder="e.g. Room change for Monday">

          <label class="ann-fl" for="ann-message">Message</label>
          <div class="ann-mwrap">
            <textarea class="ann-input" id="ann-message" rows="7" placeholder="Write the announcement. Type @ to name one team."></textarea>
            <div class="ann-mdrop" id="mentionDropdown"></div>
          </div>
          <div class="ann-note" id="ann-team-warn" hidden>Only <strong></strong> gets this.</div>

          <span class="ann-fl" id="ann-for-label">For</span>
          <div class="ann-forwrap" id="ann-forwrap">
            <button type="button" class="ann-input ann-select" id="ann-for" aria-haspopup="true" aria-expanded="false" aria-labelledby="ann-for-label ann-for"><span></span></button>
            <div class="ann-drop" id="ann-for-drop" hidden>
              <div class="ann-dl">People</div>
              <button type="button" class="ann-op" data-mode="all">Everyone</button>
              <button type="button" class="ann-op" data-mode="expert">Experts</button>
              <button type="button" class="ann-op" data-mode="student">Students</button>
              <div id="ann-for-sections">
                <div class="ann-hr"></div>
                <div class="ann-dl">Only these sections</div>
                @foreach ($adminSections as $section)
                  <label class="ann-ck"><input type="checkbox" value="{{ $section }}"> {{ $section }}</label>
                @endforeach
              </div>
            </div>
          </div>
          <div class="ann-warn" id="ann-mail-warn" hidden><span></span> already got <strong></strong> today.</div>
          <div id="annNotice" hidden style="margin-top:6px"></div>
        </div>
        <div class="ann-wf">
          <button type="button" class="ann-btn" onclick="cancelPost()">Cancel</button>
          <button type="button" class="ann-btn gold" id="ann-post-btn" onclick="savePost()"><span id="ann-save-label">Post Announcement</span></button>
        </div>
      </section>
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

<script nonce="{{ Vite::cspNonce() }}">
var teamsList = @json($teams);
var adminSections = @json($adminSections);
var editingId = null;
var deleteId = null;

var titleInput = document.getElementById('ann-title');
var mentionTextarea = document.getElementById('ann-message');
var mentionDropdown = document.getElementById('mentionDropdown');
var forWrap = document.getElementById('ann-forwrap');
var forBtn = document.getElementById('ann-for');
var forDrop = document.getElementById('ann-for-drop');
var forSections = document.getElementById('ann-for-sections');

// ---- Who it goes to ----
// One of Everyone / Experts / Students, narrowed to some of the CaPSA's own
// sections. With no section ticked the backend still sends to all of them,
// so the last ticked section can't be unticked: what the menu shows is what
// is sent.
var audMode = 'student';
var audSections = adminSections.slice();

function getTargetAudience() {
  // The expert dashboard reads 'panel', the bell and email read 'expert'.
  if (audMode === 'expert') return ['panel', 'expert'];
  return [audMode].concat(audSections.map(function(s) { return 'section:' + s; }));
}

function setTargetAudience(arr) {
  var has = function(v) { return arr.indexOf(v) !== -1; };
  var stu = has('student'), exp = has('panel') || has('expert');
  audMode = has('all') || (stu && exp) ? 'all' : stu ? 'student' : exp ? 'expert' : 'all';
  var picked = adminSections.filter(function(s) { return has('section:' + s); });
  audSections = picked.length ? picked : adminSections.slice();
  renderFor();
}

function renderFor() {
  forDrop.querySelectorAll('[data-mode]').forEach(function(b) {
    b.classList.toggle('on', b.dataset.mode === audMode);
  });
  forSections.hidden = audMode === 'expert' || adminSections.length === 0;
  forSections.querySelectorAll('input').forEach(function(cb) { cb.checked = audSections.indexOf(cb.value) !== -1; });

  var some = audSections.length < adminSections.length;
  var inSecs = audSections.length ? ' in ' + audSections.join(', ') : '';
  forBtn.firstChild.textContent = audMode === 'expert' ? 'Experts'
    : audMode === 'student' ? 'Students' + inSecs
    : some ? 'Experts and students' + inSecs : 'Everyone';
  updateWarnings();
}

function openFor(open) {
  forDrop.hidden = !open;
  forBtn.setAttribute('aria-expanded', open);
  forDrop.classList.remove('up');
  if (open && forDrop.getBoundingClientRect().bottom > window.innerHeight - 8) forDrop.classList.add('up');
}

forBtn.addEventListener('click', function() { openFor(forDrop.hidden); });
forDrop.querySelectorAll('[data-mode]').forEach(function(b) {
  b.addEventListener('click', function() { audMode = b.dataset.mode; renderFor(); });
});
forSections.addEventListener('change', function(e) {
  var picked = Array.prototype.filter.call(forSections.querySelectorAll('input'), function(cb) { return cb.checked; })
    .map(function(cb) { return cb.value; });
  if (picked.length === 0) { e.target.checked = true; return; }
  audSections = picked;
  renderFor();
});
document.addEventListener('click', function(e) {
  if (!forDrop.hidden && !forWrap.contains(e.target)) openFor(false);
});
document.addEventListener('keydown', function(e) {
  if (e.key !== 'Escape' || forDrop.hidden) return;
  e.preventDefault();
  openFor(false);
  forBtn.focus();
});

// ---- Warnings ----
// The team an @mention names. Matched against the team list rather than
// read off the text: "@Bug Busters please submit" used to be taken as a
// team called "Bug Busters please submit", so a mention anywhere but the
// very end of the post was dropped. The longest name wins, so "@Code
// Crafters 2" is not taken for "Code Crafters".
function mentionedTeam(body) {
  var lowerBody = body.toLowerCase();
  var mentioned = teamsList
    .filter(function(t) {
      var at = lowerBody.indexOf('@' + t.team_name.toLowerCase());
      if (at === -1) return false;
      var next = lowerBody.charAt(at + 1 + t.team_name.length);
      return next === '' || !/[a-z0-9_]/.test(next);
    })
    .sort(function(a, b) { return b.team_name.length - a.team_name.length; });
  return mentioned.length ? mentioned[0] : null;
}

function joinNames(names) {
  return names.length < 2 ? names.join('') : names.slice(0, -1).join(', ') + ' and ' + names[names.length - 1];
}

function updateWarnings() {
  var team = audMode === 'expert' ? null : mentionedTeam(mentionTextarea.value);
  var teamWarn = document.getElementById('ann-team-warn');
  teamWarn.hidden = !team;
  if (team) teamWarn.querySelector('strong').textContent = '@' + team.team_name;

  // Today's student posts already sent to the chosen sections. A post with no
  // section went to its poster's sections, which are only known for our own.
  // ponytail: counts the posts on this page only, which is every active one.
  var counts = {};
  if (audMode !== 'expert' && !team) {
    document.querySelectorAll('.announcement-card[data-today="1"]').forEach(function(card) {
      if (String(card.dataset.id) === String(editingId) || card.dataset.team) return;
      var aud = JSON.parse(card.dataset.audience || '[]');
      if (aud.indexOf('all') === -1 && aud.indexOf('student') === -1) return;
      var secs = aud.filter(function(v) { return v.indexOf('section:') === 0 && v !== 'section:all'; })
        .map(function(v) { return v.slice(8); });
      if (secs.length === 0) {
        if (card.dataset.mine !== '1') return;
        secs = adminSections;
      }
      secs.forEach(function(s) { if (audSections.indexOf(s) !== -1) counts[s] = (counts[s] || 0) + 1; });
    });
  }
  var names = adminSections.filter(function(s) { return counts[s]; });
  var most = names.reduce(function(m, s) { return Math.max(m, counts[s]); }, 0);
  var mailWarn = document.getElementById('ann-mail-warn');
  mailWarn.hidden = names.length === 0;
  mailWarn.querySelector('span').textContent = joinNames(names);
  mailWarn.querySelector('strong').textContent = most + ' announcement email' + (most === 1 ? '' : 's');
}

// ---- @mention ----
var mentionActiveIdx = -1;

mentionTextarea.addEventListener('input', function() {
  updateWarnings();
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
  showMention(filtered);
});

mentionTextarea.addEventListener('keydown', function(e) {
  if (!mentionDropdown.classList.contains('open')) return;
  if (e.key === 'ArrowDown') { e.preventDefault(); moveMention(1); }
  else if (e.key === 'ArrowUp') { e.preventDefault(); moveMention(-1); }
  else if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); selectMention(); }
  else if (e.key === 'Escape') { e.preventDefault(); closeMention(); }
});

function showMention(list) {
  mentionDropdown.innerHTML = '';
  list.forEach(function(t, i) {
    var div = document.createElement('div');
    div.className = 'mention-item' + (i === 0 ? ' active' : '');
    div.textContent = '@' + t.team_name;
    div.dataset.name = t.team_name;
    div.dataset.idx = i;
    div.addEventListener('mousedown', function(e) { e.preventDefault(); mentionActiveIdx = parseInt(this.dataset.idx); selectMention(); });
    div.addEventListener('mouseenter', function() {
      mentionDropdown.querySelectorAll('.mention-item').forEach(function(el) { el.classList.remove('active'); });
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
  var beforeAt = before.substring(0, before.lastIndexOf('@'));
  var insertion = '@' + name + ' ';
  mentionTextarea.value = beforeAt + insertion + after;
  mentionTextarea.selectionStart = mentionTextarea.selectionEnd = beforeAt.length + insertion.length;
  closeMention();
  mentionTextarea.focus();
  updateWarnings();
}

function closeMention() {
  mentionDropdown.classList.remove('open');
  mentionDropdown.innerHTML = '';
  mentionActiveIdx = -1;
}

mentionTextarea.addEventListener('blur', function() { setTimeout(closeMention, 150); });

// ---- Feed: search and Show ----
function applyFilter() {
  var q = document.getElementById('ann-search').value.trim().toLowerCase();
  var show = document.getElementById('ann-show').value;
  var any = false;
  document.querySelectorAll('.ann-day').forEach(function(day) {
    var shown = 0;
    day.querySelectorAll('.announcement-card').forEach(function(card) {
      var text = (card.querySelector('.ann-title').textContent + ' ' + card.querySelector('.ann-body').textContent).toLowerCase();
      var ok = (show === 'all' || card.dataset.kinds.split(' ').indexOf(show) !== -1) && (!q || text.indexOf(q) !== -1);
      card.hidden = !ok;
      if (ok) shown++;
    });
    day.hidden = shown === 0;
    if (shown) any = true;
  });
  document.getElementById('ann-nomatch').hidden = any || !document.querySelector('.announcement-card');
}
document.getElementById('ann-search').addEventListener('input', applyFilter);
document.getElementById('ann-show').addEventListener('change', applyFilter);

// ---- Form ----
function resetForm() {
  titleInput.value = '';
  mentionTextarea.value = '';
  editingId = null;
  setTargetAudience(['student']);
  document.getElementById('ann-writer-head').textContent = 'New announcement';
  document.getElementById('ann-save-label').textContent = 'Post Announcement';
}

function cancelPost() {
  resetForm();
  if (window.formNotice) formNotice.clear('annNotice');
}

function savePost() {
  var title = titleInput.value.trim();
  var body = mentionTextarea.value.trim();

  if (!title || !body) {
    formNotice('annNotice', { tone: 'error', title: !title ? 'Add a title' : 'Write the message' });
    (!title ? titleInput : mentionTextarea).focus();
    return;
  }
  formNotice.clear('annNotice');

  var team = mentionedTeam(body);
  var url = editingId ? '/api/admin/announcements/' + editingId : '/api/admin/announcements';
  var method = editingId ? 'PUT' : 'POST';
  // Every post is emailed: the backend emails anything above normal.
  var payload = { title: title, body: body, priority: 'important', target_audience: getTargetAudience(), team_id: team ? team.team_id : null };

  var btn = document.getElementById('ann-post-btn');
  var label = document.getElementById('ann-save-label');
  var idle = label.textContent;
  btn.disabled = true;
  label.textContent = editingId ? 'Saving…' : 'Posting…';

  fetch(url, {
    method: method,
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify(payload),
  })
    // A refused post (422, an expired session) comes back as JSON too, and
    // used to reload the page as if it had gone out, losing the draft.
    .then(function(r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function() { location.reload(); })
    .catch(function() {
      btn.disabled = false;
      label.textContent = idle;
      formNotice('annNotice', {
        tone: 'error',
        title: 'The post was not saved',
        body: 'Your draft is still here and nothing reached the sections.',
        actionLabel: 'Post again',
        onAction: savePost,
      });
    });
}

function editAnnouncement(id) {
  var card = document.querySelector('.announcement-card[data-id="' + id + '"]');
  if (!card) return;

  var body = card.querySelector('.ann-body').textContent;
  var team = card.dataset.team;
  if (team && body.indexOf('@' + team) === -1) body += ' @' + team;

  titleInput.value = card.querySelector('.ann-title').textContent;
  mentionTextarea.value = body;
  editingId = id;
  setTargetAudience(JSON.parse(card.dataset.audience || '[]'));
  document.getElementById('ann-writer-head').textContent = 'Editing: ' + titleInput.value;
  document.getElementById('ann-save-label').textContent = 'Save changes';
  formNotice.clear('annNotice');
  titleInput.focus();
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
    .then(function(r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      location.reload();
    })
    .catch(function() {
      btn.textContent = 'Delete';
      btn.disabled = false;
      closeConfirmModal();
      formNotice('annNotice', {
        tone: 'error',
        title: 'That announcement was not deleted',
        body: 'It is still posted. CapsTrack could not reach the server.',
      });
    });
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
@include('partials.form-notice')
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>
