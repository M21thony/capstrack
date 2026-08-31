<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Team Verification - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
</head>
<body>

@include('admin._sidebar')
<div class="main">
  <div class="topbar">
    <div class="page-title">Team Verification</div>
    <div class="topbar-actions">
      @include('admin.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="card" style="padding:16px">
      <div class="sk" style="width:280px;height:34px;border-radius:8px;margin-bottom:16px"></div>
      <div class="sk" style="height:44px;border-radius:8px;margin-bottom:10px"></div>
      <div class="sk" style="height:44px;border-radius:8px;margin-bottom:10px"></div>
      <div class="sk" style="height:44px;border-radius:8px;margin-bottom:10px"></div>
      <div class="sk" style="height:44px;border-radius:8px"></div>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content">
    @if($tab === 'pending')
      <div class="card" style="padding:14px 20px;margin-bottom:16px;display:flex;align-items:center;gap:12px;background:rgba(201,168,44,0.06);border:1px solid rgba(201,168,44,0.25)">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="var(--accent3)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <div style="font-size:13px;color:var(--text2)">
          These teams are marked <strong style="color:var(--accent3)">Complete</strong> by their team leaders and are awaiting your verification. Verify them to mark them as <strong>Verified</strong> and unlock panel assignment.
        </div>
      </div>
    @endif

    @if(session('status'))
      <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:13px">
        {!! session('status') !!}
      </div>
    @endif

    @if(session('error'))
      <div style="background:#fef2f2;border:1px solid #fecaca;color:#ef4444;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:13px">
        {!! session('error') !!}
      </div>
    @endif

    <div class="card" style="padding:0;overflow:hidden">
      <div style="padding:16px 20px 0;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div style="display:flex;gap:4px">
          <a href="{{ route('admin.verify_teams', ['tab' => 'pending'] + ($selectedSection ? ['section' => $selectedSection] : [])) }}"
             class="btn {{ $tab === 'pending' ? 'btn-primary' : '' }}"
             style="{{ $tab === 'pending' ? '' : 'background:transparent;color:var(--text2);border:1px solid var(--border)' }};font-size:12px;padding:7px 14px;border-radius:8px;text-decoration:none">
            Pending Verification
            <span style="opacity:0.8;font-weight:600;margin-left:4px">{{ $pendingCount }}</span>
          </a>
          <a href="{{ route('admin.verify_teams', ['tab' => 'verified'] + ($selectedSection ? ['section' => $selectedSection] : [])) }}"
             class="btn {{ $tab === 'verified' ? 'btn-primary' : '' }}"
             style="{{ $tab === 'verified' ? '' : 'background:transparent;color:var(--text2);border:1px solid var(--border)' }};font-size:12px;padding:7px 14px;border-radius:8px;text-decoration:none">
            Verified
            <span style="opacity:0.8;font-weight:600;margin-left:4px">{{ $verifiedCount }}</span>
          </a>
        </div>

        <form method="get" action="{{ route('admin.verify_teams') }}" style="display:flex;align-items:center;gap:8px;padding-bottom:16px">
          <input type="hidden" name="tab" value="{{ $tab }}"/>
          <label for="section-filter" style="font-size:12px;color:var(--text2)">Section</label>
          <select name="section" id="section-filter" onchange="this.form.submit()"
                  style="padding:6px 10px;border-radius:8px;border:1px solid var(--muted);background:#fff;font-size:12px">
            <option value="">All Sections</option>
            @foreach ($allSections as $sectionOption)
              <option value="{{ $sectionOption }}" {{ $selectedSection === $sectionOption ? 'selected' : '' }}>{{ $sectionOption }}</option>
            @endforeach
          </select>
        </form>
      </div>

      <table class="table">
        <thead>
          <tr>
            <th style="padding-left:20px">Team</th>
            <th>Capstone Title</th>
            <th>Tags</th>
            <th>Section</th>
            <th>Members</th>
            <th>Team Leader</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($teams as $team)
            @php
              $initials = collect(explode(' ', $team->team_name))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->implode('');
              $section = $teamSections[$team->team_id] ?? '';
              $colorMap = ['#c9a82c,#8a6e10', '#16a34a,#0891b2', '#1d4ed8,#7c3aed', '#b45309,#dc2626', '#0891b2,#16a34a'];
              $gradient = $colorMap[$loop->index % count($colorMap)];
              $tags = is_array($team->tags) ? array_slice(array_filter(array_map('trim', $team->tags)), 0, 3) : [];
              $members = $teamMembers[$team->team_id] ?? collect();
            @endphp
            <tr>
              <td style="padding-left:20px">
                <div style="display:flex;align-items:center;gap:8px">
                  <div style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,{{ $gradient }});display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">{{ $initials }}</div>
                  <span class="td-main">{{ $team->team_name }}</span>
                </div>
              </td>
              <td style="font-size:12px;color:var(--text2);white-space:normal;word-break:break-word">{{ $team->project_title ?? '—' }}</td>
              <td>
                @forelse ($tags as $tag)
                  <span class="badge badge-gold" style="font-size:10px;margin-right:4px">{{ $tag }}</span>
                @empty
                  <span style="font-size:12px;color:var(--text3)">—</span>
                @endforelse
              </td>
              <td>
                @if ($section)
                  <span class="badge badge-gold" style="font-size:10px">{{ $section }}</span>
                @else
                  <span style="font-size:12px;color:var(--text3)">—</span>
                @endif
              </td>
              <td style="font-size:12px;color:var(--text3)">{{ $members->count() }} / 5</td>
              <td style="font-size:12px;color:var(--text2)">
                {{ $team->leader ? trim(($team->leader->f_name ?? '').' '.($team->leader->l_name ?? '')) : '—' }}
              </td>
              <td>
                @if ($tab === 'pending')
                  <button type="button" class="btn btn-primary" style="font-size:10px;padding:4px 10px"
                    onclick='openVerifyModal({{ json_encode([
                        'team_id' => $team->team_id,
                        'team_name' => $team->team_name,
                        'initials' => $initials,
                        'gradient' => $gradient,
                        'project_title' => $team->project_title ?? '—',
                        'tags' => $tags,
                        'section' => $section ?: '—',
                        'leader' => $team->leader ? trim(($team->leader->f_name ?? '').' '.($team->leader->l_name ?? '')) : '—',
                        'members' => $members->map(fn ($m) => trim(($m->f_name ?? '').' '.($m->l_name ?? '')))->filter()->values(),
                        'member_count' => $members->count(),
                        'verify_url' => route('admin.verify_teams.verify', $team->team_id),
                    ], JSON_HEX_APOS | JSON_HEX_QUOT) }})'>
                    <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    View &amp; Verify
                  </button>
                @else
                  <button type="button" class="btn" style="font-size:10px;padding:4px 10px;background:transparent;color:var(--text2);border:1px solid var(--border)"
                    onclick='openVerifyModal({{ json_encode([
                        'team_id' => $team->team_id,
                        'team_name' => $team->team_name,
                        'initials' => $initials,
                        'gradient' => $gradient,
                        'project_title' => $team->project_title ?? '—',
                        'tags' => $tags,
                        'section' => $section ?: '—',
                        'leader' => $team->leader ? trim(($team->leader->f_name ?? '').' '.($team->leader->l_name ?? '')) : '—',
                        'members' => $members->map(fn ($m) => trim(($m->f_name ?? '').' '.($m->l_name ?? '')))->filter()->values(),
                        'member_count' => $members->count(),
                        'verified' => true,
                    ], JSON_HEX_APOS | JSON_HEX_QUOT) }})'>
                    <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    View
                  </button>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" style="text-align:center;padding:40px;color:var(--text3);font-size:13px">
                @if ($tab === 'pending')
                  No teams awaiting verification. Teams appear here once a team leader marks them as <strong>Complete</strong>.
                @else
                  No verified teams found for the selected section.
                @endif
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
      <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
        <span style="font-size:12px;color:var(--text3)">{{ $teams->count() }} team(s) {{ $tab === 'pending' ? 'awaiting verification' : 'verified' }}</span>
      </div>
    </div>
  </div>
  </div>
</div>
@include('admin.partials.skeleton-toggle')

<div class="modal-overlay" id="verify-modal" onclick="if(event.target===this)closeVerifyModal()">
  <div class="modal-box" style="max-width:560px;max-height:85vh;overflow-y:auto">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px">
      <div style="display:flex;align-items:center;gap:12px">
        <div id="vm-avatar" style="width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0">--</div>
        <div>
          <div style="font-size:16px;font-weight:700;color:var(--text)" id="vm-name"></div>
          <div style="font-size:11px;color:var(--text3)" id="vm-section"></div>
        </div>
      </div>
      <button onclick="closeVerifyModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text3);padding:0;line-height:1">&times;</button>
    </div>

    <div style="font-size:12px;color:var(--text2);line-height:1.5;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--border)">
      <strong style="color:var(--text)">Capstone Title:</strong> <span id="vm-title"></span>
    </div>

    <div style="margin-bottom:16px">
      <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:8px">Tags</div>
      <div id="vm-tags" style="display:flex;flex-wrap:wrap;gap:6px"></div>
    </div>

    <div style="margin-bottom:16px">
      <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:8px">Team Leader</div>
      <div style="font-size:13px;color:var(--text)" id="vm-leader"></div>
    </div>

    <div style="margin-bottom:16px">
      <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:8px">
        Members <span id="vm-member-count" style="color:var(--accent3)"></span>
      </div>
      <div id="vm-members" style="display:flex;flex-direction:column;gap:6px"></div>
    </div>

    <form id="vm-form" method="POST" style="border-top:1px solid var(--border);padding-top:16px">
      @csrf
      <button type="submit" class="btn btn-primary" style="width:100%;padding:10px;font-size:13px">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:6px"><polyline points="20 6 9 17 4 12"/></svg>
        Verify Team
      </button>
    </form>
  </div>
</div>

<style>
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:1000;opacity:0;pointer-events:none;transition:opacity 0.2s}
.modal-overlay.open{opacity:1;pointer-events:auto}
.modal-box{background:#fff;border:1px solid var(--border);border-radius:16px;padding:28px;max-width:560px;width:90%}
.vm-member-row{display:flex;align-items:center;gap:10px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:8px 12px}
.vm-member-row .vm-member-avatar{width:26px;height:26px;border-radius:6px;background:linear-gradient(135deg,#c9a82c,#8a6e10);display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;color:#fff;flex-shrink:0}
.vm-member-row .vm-member-name{font-size:13px;color:var(--text)}
.vm-member-row .vm-member-role{font-size:11px;color:var(--text3)}
</style>

<script>
var vmData = null;
function openVerifyModal(data) {
  vmData = data;
  document.getElementById('vm-avatar').style.background = 'linear-gradient(135deg,' + data.gradient + ')';
  document.getElementById('vm-avatar').textContent = data.initials;
  document.getElementById('vm-name').textContent = data.team_name;
  document.getElementById('vm-section').textContent = 'Section: ' + data.section;
  document.getElementById('vm-title').textContent = data.project_title;

  var tagsEl = document.getElementById('vm-tags');
  tagsEl.innerHTML = '';
  if (data.tags && data.tags.length) {
    data.tags.forEach(function (tag) {
      var s = document.createElement('span');
      s.className = 'badge badge-gold';
      s.style.cssText = 'font-size:10px';
      s.textContent = tag;
      tagsEl.appendChild(s);
    });
  } else {
    tagsEl.innerHTML = '<span style="font-size:12px;color:var(--text3)">—</span>';
  }

  document.getElementById('vm-leader').textContent = data.leader;
  document.getElementById('vm-member-count').textContent = '(' + data.member_count + ' / 5)';

  var membersEl = document.getElementById('vm-members');
  membersEl.innerHTML = '';
  if (data.members && data.members.length) {
    data.members.forEach(function (name) {
      var row = document.createElement('div');
      row.className = 'vm-member-row';
      var av = document.createElement('div');
      av.className = 'vm-member-avatar';
      av.textContent = name.split(' ').filter(Boolean).map(function (w) { return w[0].toUpperCase(); }).slice(0, 2).join('');
      var nm = document.createElement('div');
      nm.className = 'vm-member-name';
      nm.textContent = name;
      row.appendChild(av);
      row.appendChild(nm);
      membersEl.appendChild(row);
    });
  } else {
    membersEl.innerHTML = '<span style="font-size:12px;color:var(--text3)">No members yet</span>';
  }

  var form = document.getElementById('vm-form');
  form.style.display = data.verified ? 'none' : 'block';
  form.action = data.verify_url;

  document.getElementById('verify-modal').classList.add('open');
}
function closeVerifyModal() {
  document.getElementById('verify-modal').classList.remove('open');
}
</script>
</body>
</html>